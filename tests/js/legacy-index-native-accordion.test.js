import fs from 'fs';
import path from 'path';
import vm from 'vm';

const root = path.resolve( __dirname, '../..' );
const jqueryAsset =
	process.env.RRZE_WORDPRESS_JQUERY_SCRIPT ||
	path.resolve( root, '../../../wp-includes/js/jquery/jquery.js' );

( fs.existsSync( jqueryAsset ) ? describe : describe.skip )(
	'legacy index navigation with the native accordion and WordPress jQuery',
	() => {
		let jquery;
		let previousJquery;
		let previousDollar;
		let documentListeners;
		let windowListeners;
		let readyState;
		let scrollTo;
		let cleanupIndex;
		let cleanupKeyboard;

		/**
		 * Load both production entry points against the actual WordPress jQuery.
		 *
		 * DOM ready runs before window load, reproducing the ordering in which the
		 * accordion initializes before the legacy index follows its direct link.
		 * Effects are disabled only to make completed DOM state deterministic.
		 * The VM global follows window.jQuery for WordPress's noConflict call.
		 *
		 * @return {Promise<void>} Resolves once the accordion ready handler ran.
		 */
		async function loadEntriesUntilReady() {
			vm.runInNewContext( fs.readFileSync( jqueryAsset, 'utf8' ), {
				window,
				/**
				 * Expose jQuery after its UMD browser initialization.
				 *
				 * @return {*} Installed browser jQuery instance.
				 */
				get jQuery() {
					return window.jQuery;
				},
			} );
			jquery = window.jQuery;
			jquery.fx.off = true;
			jest.isolateModules( () => {
				require( '../../src/js/rrze-answers-accordion' );
				require( '../../src/js/rrze-answers-index' );
				cleanupIndex =
					require( '../../src/js/legacy-index-links' ).initializeLegacyIndexLinks();
				cleanupKeyboard =
					require( '../../src/js/elements-accordion-keyboard' ).initializeElementsAccordionKeyboard();
			} );
			document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
			await new Promise( ( resolve ) => jquery( resolve ) );
		}

		/**
		 * Finish page loading and wait for the index's deferred initial navigation.
		 *
		 * @return {Promise<void>} Resolves after initial legacy navigation.
		 */
		async function finishLoading() {
			readyState.mockReturnValue( 'complete' );
			window.dispatchEvent( new Event( 'load' ) );
			await new Promise( ( resolve ) => window.setTimeout( resolve, 0 ) );
		}

		beforeEach( () => {
			previousJquery = window.jQuery;
			previousDollar = window.$;
			documentListeners = jest.spyOn( document, 'addEventListener' );
			windowListeners = jest.spyOn( window, 'addEventListener' );
			readyState = jest
				.spyOn( document, 'readyState', 'get' )
				.mockReturnValue( 'loading' );
			scrollTo = jest
				.spyOn( window, 'scrollTo' )
				.mockImplementation( () => {} );
			window.history.replaceState( null, '', '/' );
			document.body.innerHTML = `<div class="rrze-answers" data-accordion="single" data-load-open="true" data-scroll-offset="96">
				<details id="ID-11" class="rrze-answers-item"><summary>Alpha</summary><div>First answer</div></details>
				<h2 id="rrze-answers-index-1-B" data-rrze-answers-legacy-anchor="letter-B">B</h2>
				<div id="rrze-answers-index-1-term-42" data-rrze-answers-legacy-anchor="ID-42">Group</div>
				<details id="ID-12" class="rrze-answers-item"><summary>Beta</summary><div>Second answer</div></details>
			</div>`;
		} );

		afterEach( () => {
			cleanupIndex?.();
			cleanupKeyboard?.();
			jquery?.( window ).off();
			jquery?.( document ).off();
			for ( const [ type, listener, options ] of documentListeners.mock
				.calls ) {
				document.removeEventListener( type, listener, options );
			}
			for ( const [ type, listener, options ] of windowListeners.mock
				.calls ) {
				window.removeEventListener( type, listener, options );
			}
			jest.restoreAllMocks();
			window.jQuery = previousJquery;
			window.$ = previousDollar;
			jquery = undefined;
			cleanupIndex = undefined;
			cleanupKeyboard = undefined;
			document.body.innerHTML = '';
			document.documentElement.style.removeProperty( '--header-height' );
			window.history.replaceState( null, '', '/' );
		} );

		it.each( [
			[ '#letter-B', 'rrze-answers-index-1-B' ],
			[ '#ID-42', 'rrze-answers-index-1-term-42' ],
		] )(
			'preserves %s until legacy navigation and updates the URL on user activation',
			async ( hash, targetId ) => {
				window.history.replaceState( null, '', hash );
				jest.spyOn(
					document.getElementById( targetId ),
					'getBoundingClientRect'
				).mockReturnValue( { top: 500 } );
				await loadEntriesUntilReady();
				expect( document.getElementById( 'ID-11' ).open ).toBe( true );
				expect( window.location.hash ).toBe( hash );
				expect( scrollTo ).not.toHaveBeenCalled();
				await finishLoading();
				expect( scrollTo ).toHaveBeenCalledTimes( 1 );
				expect( scrollTo ).toHaveBeenCalledWith( {
					top: 404,
					behavior: 'auto',
				} );
				expect( window.location.hash ).toBe( hash );

				document.querySelector( '#ID-12 summary' ).click();
				expect( document.getElementById( 'ID-11' ).open ).toBe( false );
				expect( document.getElementById( 'ID-12' ).open ).toBe( true );
				expect( window.location.hash ).toBe( '#ID-12' );
			}
		);

		it( 'retains the first-item hash when load-open starts without a fragment', async () => {
			await loadEntriesUntilReady();
			await finishLoading();
			expect( document.getElementById( 'ID-11' ).open ).toBe( true );
			expect( window.location.hash ).toBe( '#ID-11' );
			expect( scrollTo ).not.toHaveBeenCalled();
		} );

		it( 'continues opening an existing entry anchor instead of the first item', async () => {
			window.history.replaceState( null, '', '#ID-12' );
			await loadEntriesUntilReady();
			await finishLoading();
			expect( document.getElementById( 'ID-11' ).open ).toBe( false );
			expect( document.getElementById( 'ID-12' ).open ).toBe( true );
			expect( window.location.hash ).toBe( '#ID-12' );
			expect( scrollTo ).not.toHaveBeenCalled();
		} );
	}
);
