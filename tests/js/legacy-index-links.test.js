/* eslint-disable jest/expect-expect -- Assertions are in the documented named test callbacks. */
import { initializeLegacyIndexLinks } from '../../src/js/legacy-index-links';

let cleanup;

/**
 * Start every navigation example with a clean URL and controlled browser APIs.
 *
 * @return {void}
 */
function prepareDocument() {
	jest.useFakeTimers();
	window.history.replaceState( null, '', '/' );
	document.body.innerHTML = '';
	jest.spyOn( window, 'scrollTo' ).mockImplementation( jest.fn() );
	jest.spyOn( document, 'readyState', 'get' ).mockReturnValue( 'complete' );
}

/**
 * Remove listeners and mocked APIs so examples cannot affect each other.
 *
 * @return {void}
 */
function resetDocument() {
	cleanup?.();
	cleanup = undefined;
	jest.restoreAllMocks();
	jest.useRealTimers();
}

beforeEach( prepareDocument );
afterEach( resetDocument );

/**
 * Render a unique index target with the public renderer's legacy mapping.
 *
 * @param {string}      id     Current unique index target.
 * @param {string}      legacy Old fragment to restore.
 * @param {number}      top    Viewport position used to distinguish duplicate mappings.
 * @param {HTMLElement} parent Container receiving the list wrapper.
 * @return {HTMLElement} Mapped target used by assertions.
 */
function addTarget( id, legacy, top = 300, parent = document.body ) {
	const wrapper = document.createElement( 'div' );
	wrapper.className = 'rrze-answers';
	wrapper.dataset.scrollOffset = '96';
	const target = document.createElement( 'h2' );
	target.id = id;
	target.setAttribute( 'data-rrze-answers-legacy-anchor', legacy );
	target.getBoundingClientRect = jest.fn().mockReturnValue( { top } );
	wrapper.append( target );
	parent.append( wrapper );
	return target;
}

/**
 * Dispatch a cancellable ordinary link click without jsdom navigation.
 *
 * @param {string} href       Destination URL or fragment.
 * @param {Object} options    Optional modifier keys for the click.
 * @param {Object} attributes Optional link attributes.
 * @return {MouseEvent} Event exposing whether our handler consumed navigation.
 */
function clickLink( href, options = {}, attributes = {} ) {
	const link = document.createElement( 'a' );
	link.href = href;
	for ( const [ key, value ] of Object.entries( attributes ) ) {
		link.setAttribute( key, value );
	}
	document.body.append( link );
	const event = new window.MouseEvent( 'click', {
		bubbles: true,
		cancelable: true,
		button: 0,
		...options,
	} );
	link.dispatchEvent( event );
	link.remove();
	return event;
}

/**
 * Start the adapter with a fragment, then finish its initial deferred pass.
 *
 * @param {string} hash Requested fragment.
 * @return {void}
 */
function initializeAt( hash ) {
	window.history.replaceState( null, '', hash );
	cleanup = initializeLegacyIndexLinks();
	jest.runOnlyPendingTimers();
}

/**
 * Demonstrate deterministic first-list mapping and retention of the old URL.
 *
 * @return {void}
 */
function firstMatchingList() {
	addTarget( 'index-1-A', 'letter-A', 300 );
	addTarget( 'index-2-A', 'letter-A', 700 );
	initializeAt( '#letter-A' );
	expect( window.scrollTo ).toHaveBeenCalledWith( {
		top: 204,
		behavior: 'auto',
	} );
	expect( window.location.hash ).toBe( '#letter-A' );
	expect( document.getElementById( 'letter-A' ) ).toBeNull();
}
test(
	'old letters select the first matching list without duplicate IDs',
	firstMatchingList
);

/**
 * Preserve a real entry anchor even when its element follows mapped lists.
 *
 * @return {void}
 */
function actualIdWins() {
	addTarget( 'index-1-term-12', 'ID-12' );
	const entry = document.createElement( 'article' );
	entry.id = 'ID-12';
	document.body.append( entry );
	initializeAt( '#ID-12' );
	expect( window.scrollTo ).not.toHaveBeenCalled();
	expect( clickLink( '#ID-12' ).defaultPrevented ).toBe( false );
}
test(
	'actual IDs anywhere in the document retain native precedence',
	actualIdWins
);

/**
 * Verify ordinary clicks, repeated clicks and browser fragment navigation.
 *
 * @return {void}
 */
function repeatedNavigation() {
	addTarget( 'index-1-term-12', 'ID-12' );
	initializeAt( '#unrelated' );
	expect( clickLink( '#ID-12' ).defaultPrevented ).toBe( true );
	expect( window.location.hash ).toBe( '#ID-12' );
	expect( clickLink( '#ID-12' ).defaultPrevented ).toBe( true );
	window.dispatchEvent( new window.HashChangeEvent( 'hashchange' ) );
	expect( window.scrollTo ).toHaveBeenCalledTimes( 3 );
}
test(
	'legacy links work on clicks, repeated clicks and hash changes',
	repeatedNavigation
);

/**
 * Keep unrelated URLs, modified clicks and alternate browsing targets native.
 *
 * @return {void}
 */
function unrelatedLinks() {
	addTarget( 'index-1-A', 'letter-A' );
	initializeAt( '#unknown' );
	for ( const [ href, options, attributes ] of [
		[ '#letter-A', { ctrlKey: true }, {} ],
		[ '#letter-A', { metaKey: true }, {} ],
		[ '#letter-A', { shiftKey: true }, {} ],
		[ '#letter-A', { altKey: true }, {} ],
		[ '#letter-A', {}, { target: '_blank' } ],
		[ '#letter-A', {}, { download: '' } ],
		[ '/another-page#letter-A', {}, {} ],
		[ '/?other=query#letter-A', {}, {} ],
		[ 'https://other.example/#letter-A', {}, {} ],
		[ 'http://[invalid/#letter-A', {}, {} ],
		[ '#unknown', {}, {} ],
		[ '#index-1-A', {}, {} ],
	] ) {
		expect( clickLink( href, options, attributes ).defaultPrevented ).toBe(
			false
		);
	}
	expect( window.scrollTo ).not.toHaveBeenCalled();
}
test( 'unrelated links and modified clicks remain native', unrelatedLinks );

/**
 * Decode encoded fragments and ignore malformed input without selector errors.
 *
 * @return {void}
 */
function encodedFragments() {
	addTarget( 'index-special', 'letter-Ä' );
	initializeAt( '#letter-%C3%84' );
	expect( window.scrollTo ).toHaveBeenCalledTimes( 1 );
	expect( clickLink( '#letter-%E0%A4%A' ).defaultPrevented ).toBe( false );
	expect( clickLink( '#letter-%22%5D' ).defaultPrevented ).toBe( false );
	expect( window.scrollTo ).toHaveBeenCalledTimes( 1 );
}
test( 'encoded and malformed fragments are handled safely', encodedFragments );

/**
 * Keep similarly named mappings outside an Answers wrapper unrelated.
 *
 * @return {void}
 */
function outsideMappings() {
	document.body.innerHTML =
		'<h2 data-rrze-answers-legacy-anchor="letter-A">A</h2>';
	initializeAt( '#letter-A' );
	expect( window.scrollTo ).not.toHaveBeenCalled();
}
test( 'only Answers-owned mappings are resolved', outsideMappings );

/**
 * Exercise nested tab activation after a dependency's load initialization.
 *
 * @return {void}
 */
function activateNestedTabsAfterLoad() {
	jest.spyOn( document, 'readyState', 'get' ).mockReturnValue( 'loading' );
	document.body.innerHTML = `
		<button role="tab" aria-controls="outer-panel" aria-selected="false">Outer</button>
		<div id="outer-panel" role="tabpanel" hidden>
			<button role="tab" aria-controls="inner-panel" aria-selected="false">Inner</button>
			<div id="inner-panel" role="tabpanel" hidden></div>
		</div>`;
	const inner = document.getElementById( 'inner-panel' );
	addTarget( 'index-1-term-12', 'ID-12', 300, inner );
	const activated = [];
	window.history.replaceState( null, '', '#ID-12' );
	cleanup = initializeLegacyIndexLinks();
	/**
	 * Model the dependency installing its existing tab-click handlers on load.
	 *
	 * @return {void}
	 */
	function initializeTabs() {
		for ( const tab of document.querySelectorAll( '[role="tab"]' ) ) {
			/**
			 * Expose the selected panel, as the dependency would do on click.
			 *
			 * @return {void}
			 */
			function activate() {
				const panelId = tab.getAttribute( 'aria-controls' );
				activated.push( panelId );
				document.getElementById( panelId ).hidden = false;
				tab.setAttribute( 'aria-selected', 'true' );
			}
			tab.addEventListener( 'click', activate );
		}
	}
	window.addEventListener( 'load', initializeTabs, { once: true } );
	window.dispatchEvent( new Event( 'load' ) );
	expect( window.scrollTo ).not.toHaveBeenCalled();
	jest.runOnlyPendingTimers();
	expect( activated ).toEqual( [ 'outer-panel', 'inner-panel' ] );
	expect( inner.hidden ).toBe( false );
	expect( document.getElementById( 'outer-panel' ).hidden ).toBe( false );
	expect( window.scrollTo ).toHaveBeenCalledTimes( 1 );
	clickLink( '#ID-12' );
	expect( activated ).toHaveLength( 2 );
}
test(
	'old group links activate containing tabs after dependency initialization',
	activateNestedTabsAfterLoad
);

/**
 * Avoid duplicate listeners and honor cleanup before the pending initial pass.
 *
 * @return {void}
 */
function idempotentInitialization() {
	addTarget( 'index-1-A', 'letter-A' );
	window.history.replaceState( null, '', '#letter-A' );
	cleanup = initializeLegacyIndexLinks();
	expect( initializeLegacyIndexLinks() ).toBe( cleanup );
	cleanup();
	jest.runOnlyPendingTimers();
	window.dispatchEvent( new window.HashChangeEvent( 'hashchange' ) );
	expect( window.scrollTo ).not.toHaveBeenCalled();
	cleanup = initializeLegacyIndexLinks();
	jest.runOnlyPendingTimers();
	expect( window.scrollTo ).toHaveBeenCalledTimes( 1 );
}
test(
	'setup is idempotent and cleanup cancels pending navigation',
	idempotentInitialization
);
