import fs from 'fs';
import path from 'path';
import vm from 'vm';
import { initializeElementsAccordionKeyboard } from '../../src/js/elements-accordion-keyboard';

let cleanup;

/**
 * Create the same wrapper, button and panel structure rendered by Elements.
 *
 * @param {string} id Stable entry anchor.
 * @param {boolean} open Initial load-open setting.
 * @param {boolean} owned Whether Answers generated this Collapse item.
 * @return {string} One accordion item fixture.
 */
function itemMarkup( id = 'entry', open = false, owned = true ) {
	return `<div class="wp-block-rrze-elements-collapse ${
		owned ? 'rrze-answers-block-item' : ''
	}">
		<div class="accordion-group"><h2 class="accordion-heading">
		<button class="accordion-toggle ${
			open ? 'active' : ''
		}" id="${ id }" type="button"
		data-href="#${ id }" data-name="${ id }" aria-controls="${ id }-section" aria-expanded="${ open }">Question</button>
		</h2><div class="accordion-body ${
			open ? 'open' : ''
		}" id="${ id }-section" role="region">Answer</div></div></div>`;
}

/**
 * Dispatch an actual DOM keyboard event with legacy keyCode for Elements 1.0.5.
 *
 * @param {Element} target Focused accordion control.
 * @param {string} type Keyboard event type.
 * @param {string} key Key name.
 * @param {Object} options Additional event properties.
 * @return {KeyboardEvent} Event for cancellation assertions.
 */
function keyEvent( target, type, key = 'Enter', options = {} ) {
	const event = new KeyboardEvent( type, {
		bubbles: true,
		cancelable: true,
		key,
		keyCode: key === 'Enter' ? 13 : 32,
		...options,
	} );
	target.dispatchEvent( event );
	return event;
}

/**
 * Simulate a dependency activation while retaining its accessible state contract.
 *
 * @param {Event} event Dependency activation event.
 * @return {void}
 */
function toggleDependency( event ) {
	event.preventDefault();
	const button = event.currentTarget;
	button.setAttribute(
		'aria-expanded',
		String( button.getAttribute( 'aria-expanded' ) !== 'true' )
	);
	button.classList.toggle( 'active' );
}

beforeEach( () => {
	document.body.innerHTML = `<div class="rrze-answers"><div class="accordion">${ itemMarkup() }</div></div>`;
	window.history.replaceState( null, '', '/' );
} );

afterEach( () => {
	cleanup?.();
	cleanup = undefined;
	document.body.innerHTML = '';
	window.history.replaceState( null, '', '/' );
} );

describe( 'Answers Elements keyboard adapter', () => {
	it( 'activates once and suppresses future Enter handlers on both key phases', () => {
		const button = document.querySelector( 'button' );
		const activate = jest.fn( toggleDependency );
		const futureKeyboard = jest.fn( toggleDependency );
		button.addEventListener( 'mousedown', activate );
		button.addEventListener( 'keydown', futureKeyboard );
		button.addEventListener( 'keyup', futureKeyboard );
		button.addEventListener( 'click', activate );
		cleanup = initializeElementsAccordionKeyboard();

		expect( keyEvent( button, 'keydown' ).defaultPrevented ).toBe( true );
		expect( keyEvent( button, 'keyup' ).defaultPrevented ).toBe( true );
		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( activate ).toHaveBeenCalledTimes( 1 );
		expect( futureKeyboard ).not.toHaveBeenCalled();
	} );

	it( 'uses click once when a future dependency no longer handles mousedown', () => {
		const button = document.querySelector( 'button' );
		const activate = jest.fn( toggleDependency );
		button.addEventListener( 'click', activate );
		cleanup = initializeElementsAccordionKeyboard();
		keyEvent( button, 'keydown' );
		keyEvent( button, 'keyup' );
		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( activate ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'does not add a click after an uncanceled mousedown already changed state', () => {
		const button = document.querySelector( 'button' );
		button.addEventListener( 'mousedown', () =>
			button.setAttribute( 'aria-expanded', 'true' )
		);
		const click = jest.fn();
		button.addEventListener( 'click', click );
		cleanup = initializeElementsAccordionKeyboard();
		keyEvent( button, 'keydown' );
		expect( click ).not.toHaveBeenCalled();
	} );

	it( 'keeps Space with the installed handler and ignores repeated or modified Enter', () => {
		const button = document.querySelector( 'button' );
		const activate = jest.fn( toggleDependency );
		const space = jest.fn( ( event ) => {
			if ( event.key === ' ' ) {
				toggleDependency( event );
			}
		} );
		button.addEventListener( 'mousedown', activate );
		button.addEventListener( 'keydown', space );
		cleanup = initializeElementsAccordionKeyboard();
		keyEvent( button, 'keydown', ' ' );
		keyEvent( button, 'keyup', ' ' );
		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( space ).toHaveBeenCalledTimes( 1 );
		keyEvent( button, 'keydown', 'Enter', { repeat: true } );
		keyEvent( button, 'keydown', 'Enter', { ctrlKey: true } );
		keyEvent( button, 'keydown', 'Enter', { isComposing: true } );
		expect( activate ).not.toHaveBeenCalled();
	} );

	it( 'does not change already handled Enter or disabled controls', () => {
		const button = document.querySelector( 'button' );
		const activate = jest.fn( toggleDependency );
		button.addEventListener( 'mousedown', activate );
		/**
		 * Model an earlier capture listener which has already consumed Enter.
		 *
		 * @param {KeyboardEvent} event Captured keyboard event.
		 * @return {void}
		 */
		const handled = ( event ) => event.preventDefault();
		document.addEventListener( 'keydown', handled, true );
		cleanup = initializeElementsAccordionKeyboard();
		try {
			keyEvent( button, 'keydown' );
		} finally {
			document.removeEventListener( 'keydown', handled, true );
		}
		button.disabled = true;
		keyEvent( button, 'keydown' );
		expect( activate ).not.toHaveBeenCalled();
	} );

	it( 'excludes unrelated, nested and Core accordion controls', () => {
		document.body.innerHTML =
			itemMarkup( 'outside' ) +
			`<div class="rrze-answers">${ itemMarkup(
				'standalone',
				false,
				false
			) }${ itemMarkup( 'outer' ) }
		<button class="wp-block-accordion-heading__toggle">Core</button></div>`;
		document.getElementById( 'outer-section' ).innerHTML = itemMarkup(
			'nested',
			false,
			false
		);
		document.getElementById( 'outer-section' ).insertAdjacentHTML(
			'beforeend',
			'<button id="answer-button" class="accordion-toggle">Unrelated answer control</button>'
		);
		const activate = jest.fn();
		document.addEventListener( 'mousedown', activate );
		cleanup = initializeElementsAccordionKeyboard();
		try {
			for ( const selector of [
				'#outside',
				'#standalone',
				'#nested',
				'#answer-button',
				'.wp-block-accordion-heading__toggle',
			] ) {
				expect(
					keyEvent( document.querySelector( selector ), 'keydown' )
						.defaultPrevented
				).toBe( false );
			}
			expect( activate ).not.toHaveBeenCalled();
		} finally {
			document.removeEventListener( 'mousedown', activate );
		}
	} );

	it( 'initializes idempotently and detaches cleanly', () => {
		const button = document.querySelector( 'button' );
		const activate = jest.fn( toggleDependency );
		button.addEventListener( 'mousedown', activate );
		cleanup = initializeElementsAccordionKeyboard();
		expect( initializeElementsAccordionKeyboard() ).toBe( cleanup );
		keyEvent( button, 'keydown' );
		expect( activate ).toHaveBeenCalledTimes( 1 );
		cleanup();
		keyEvent( button, 'keydown' );
		expect( activate ).toHaveBeenCalledTimes( 1 );
		const previousCleanup = cleanup;
		cleanup = initializeElementsAccordionKeyboard();
		previousCleanup();
		expect( initializeElementsAccordionKeyboard() ).toBe( cleanup );
		keyEvent( button, 'keydown' );
		expect( activate ).toHaveBeenCalledTimes( 2 );
	} );
} );

const root = path.resolve( __dirname, '../..' );
const dependencyAsset =
	process.env.RRZE_ELEMENTS_ACCORDION_SCRIPT ||
	path.resolve(
		root,
		'../rrze-elements-blocks/assets/js/accordion/rrze-accordion.js'
	);
const jqueryAsset =
	process.env.RRZE_WORDPRESS_JQUERY_SCRIPT ||
	path.resolve( root, '../../../wp-includes/js/jquery/jquery.js' );
const hasLocalDependency =
	fs.existsSync( dependencyAsset ) && fs.existsSync( jqueryAsset );

( hasLocalDependency ? describe : describe.skip )(
	'installed Elements accordion integration',
	() => {
		let jquery;
		let documentListeners;
		let previousJquery;
		let previousDollar;

		/**
		 * Evaluate the installed jQuery and Elements scripts against the test DOM.
		 *
		 * Only translation lookup is replaced. Real dependency handlers, animations
		 * (with jQuery's documented effects-off switch), hashes and DOM state run.
		 * Captured native listeners are removed after each isolated fixture.
		 * The global alias follows window.jQuery for WordPress's noConflict call.
		 *
		 * @return {Promise<void>} Resolves after the dependency's ready callback.
		 */
		async function loadInstalledDependency() {
			documentListeners = jest.spyOn( document, 'addEventListener' );
			previousJquery = window.jQuery;
			previousDollar = window.$;
			vm.runInNewContext( fs.readFileSync( jqueryAsset, 'utf8' ), {
				window,
				/**
				 * Expose the browser global after jQuery's UMD initialization.
				 *
				 * @return {*} Installed browser jQuery instance.
				 */
				get jQuery() {
					return window.jQuery;
				},
			} );
			jquery = window.jQuery;
			jquery.fx.off = true;
			vm.runInNewContext( fs.readFileSync( dependencyAsset, 'utf8' ), {
				window,
				document,
				jQuery: jquery,
				wp: { i18n: { __: ( value ) => value } },
			} );
			await new Promise( ( resolve ) => jquery( resolve ) );
		}

		afterEach( () => {
			jquery?.( window ).off();
			jquery?.( document ).off();
			for ( const [ type, listener, options ] of documentListeners?.mock
				.calls || [] ) {
				document.removeEventListener( type, listener, options );
			}
			documentListeners?.mockRestore();
			window.jQuery = previousJquery;
			window.$ = previousDollar;
		} );

		it( 'opens and closes once with Enter and Space using the real dependency', async () => {
			await loadInstalledDependency();
			cleanup = initializeElementsAccordionKeyboard();
			const button = document.getElementById( 'entry' );
			const body = document.getElementById( 'entry-section' );
			keyEvent( button, 'keydown' );
			keyEvent( button, 'keyup' );
			expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
			expect( body.style.display ).not.toBe( 'none' );
			expect( window.location.hash ).toBe( '#entry' );
			keyEvent( button, 'keydown' );
			keyEvent( button, 'keyup' );
			expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
			expect( body.style.display ).toBe( 'none' );
			keyEvent( button, 'keydown', ' ' );
			keyEvent( button, 'keyup', ' ' );
			expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
			expect( body.style.display ).not.toBe( 'none' );
		} );

		it( 'preserves load-open and the dependency browser-search shortcut', async () => {
			document.body.innerHTML = `<div class="rrze-answers"><div class="accordion">${ itemMarkup(
				'entry',
				true
			) }${ itemMarkup( 'other' ) }</div></div>`;
			await loadInstalledDependency();
			cleanup = initializeElementsAccordionKeyboard();
			expect(
				document
					.getElementById( 'entry' )
					.getAttribute( 'aria-expanded' )
			).toBe( 'true' );
			expect(
				document.getElementById( 'entry-section' ).style.display
			).not.toBe( 'none' );
			expect(
				document.getElementById( 'other-section' ).style.display
			).toBe( 'none' );
			keyEvent( document.body, 'keydown', 'f', { ctrlKey: true } );
			expect(
				document.getElementById( 'other-section' ).style.display
			).not.toBe( 'none' );
		} );

		it( 'retains initial hash activation without toggling the selected item again', async () => {
			window.history.replaceState( null, '', '#entry' );
			await loadInstalledDependency();
			cleanup = initializeElementsAccordionKeyboard();
			expect(
				document
					.getElementById( 'entry' )
					.getAttribute( 'aria-expanded' )
			).toBe( 'true' );
			expect(
				document.getElementById( 'entry-section' ).style.display
			).not.toBe( 'none' );
			expect( window.location.hash ).toBe( '#entry' );
		} );
	}
);
