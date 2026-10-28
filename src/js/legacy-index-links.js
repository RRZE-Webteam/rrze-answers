const initializedDocuments = new WeakMap();
const legacyAttribute = 'data-rrze-answers-legacy-anchor';

/**
 * Resolve an old fragment only when no actual element already owns that ID.
 *
 * DOM order makes repeated lists deterministic without allocating duplicate
 * IDs. An entry anchor anywhere on the page takes priority over every mapping.
 *
 * @param {Document} root Document containing all rendered lists.
 * @param {string}   hash Encoded URL fragment, including the leading hash.
 * @return {HTMLElement|null} First mapped target, or null for native navigation.
 */
function resolveLegacyTarget( root, hash ) {
	let fragment;
	try {
		fragment = decodeURIComponent( hash.slice( 1 ) );
	} catch {
		return null;
	}
	if ( ! fragment || root.getElementById( fragment ) ) {
		return null;
	}
	for ( const target of root.querySelectorAll(
		`.rrze-answers [${ legacyAttribute }]`
	) ) {
		if ( target.getAttribute( legacyAttribute ) === fragment ) {
			return target;
		}
	}
	return null;
}

/**
 * Reveal containing tabs through their existing activation handlers.
 *
 * Outer panels activate first so nested tabs are visible before scrolling. The
 * dependency continues to own selection, accessibility state and panel styles.
 *
 * @param {HTMLElement} target Mapped heading or group target.
 * @return {void}
 */
function revealContainingTabs( target ) {
	const panels = [];
	let panel = target.closest( '[role="tabpanel"]' );
	while ( panel ) {
		panels.unshift( panel );
		panel = panel.parentElement?.closest( '[role="tabpanel"]' );
	}
	for ( const currentPanel of panels ) {
		if ( ! currentPanel.id ) {
			continue;
		}
		for ( const tab of target.ownerDocument.querySelectorAll(
			'[role="tab"][aria-controls]'
		) ) {
			if ( tab.getAttribute( 'aria-controls' ) === currentPanel.id ) {
				if ( tab.getAttribute( 'aria-selected' ) !== 'true' ) {
					tab.click();
				}
				break;
			}
		}
	}
}

/**
 * Restore legacy group and letter links without replacing current native IDs.
 *
 * Initial navigation waits until the load handlers of the tab dependency have
 * run. Hash changes and same-page clicks also work, including a second click on
 * the current fragment. JavaScript is required only for these legacy mappings.
 *
 * @param {Document} root Document containing the rendered Answers content.
 * @return {Function} Remove listeners and pending navigation for this document.
 */
export function initializeLegacyIndexLinks( root = document ) {
	if ( initializedDocuments.has( root ) ) {
		return initializedDocuments.get( root );
	}
	const view = root.defaultView;
	let pendingNavigation;

	/**
	 * Activate and scroll to a mapped target using its wrapper's header offset.
	 *
	 * @param {HTMLElement} target Existing unique target selected by the mapping.
	 * @return {void}
	 */
	function navigateToTarget( target ) {
		revealContainingTabs( target );
		const wrapper = target.closest( '.rrze-answers' );
		const offset =
			Number.parseInt( wrapper?.dataset.scrollOffset || '0', 10 ) || 0;
		view.scrollTo( {
			top: Math.max(
				0,
				view.scrollY + target.getBoundingClientRect().top - offset
			),
			behavior: 'auto',
		} );
	}

	/**
	 * Follow the current URL only when it resolves to a legacy mapping.
	 *
	 * @return {void}
	 */
	function navigateToHash() {
		const target = resolveLegacyTarget( root, view.location.hash );
		if ( target ) {
			navigateToTarget( target );
		}
	}

	/**
	 * Defer initial navigation until all synchronous load handlers finish.
	 *
	 * @return {void}
	 */
	function scheduleInitialNavigation() {
		view.clearTimeout( pendingNavigation );
		pendingNavigation = view.setTimeout( navigateToHash, 0 );
	}

	/**
	 * Follow ordinary same-document legacy links while retaining their URLs.
	 *
	 * @param {MouseEvent} event Bubbled document click.
	 * @return {void}
	 */
	function handleClick( event ) {
		if (
			event.defaultPrevented ||
			event.button !== 0 ||
			event.altKey ||
			event.ctrlKey ||
			event.metaKey ||
			event.shiftKey
		) {
			return;
		}
		const link = event.target?.closest?.( 'a[href]' );
		if (
			! link ||
			link.hasAttribute( 'download' ) ||
			( link.target && link.target !== '_self' )
		) {
			return;
		}
		let url;
		try {
			url = new view.URL( link.href, view.location.href );
		} catch {
			return;
		}
		if (
			url.origin !== view.location.origin ||
			url.pathname !== view.location.pathname ||
			url.search !== view.location.search
		) {
			return;
		}
		const target = resolveLegacyTarget( root, url.hash );
		if ( ! target ) {
			return;
		}
		event.preventDefault();
		if ( view.location.hash !== url.hash ) {
			view.history.pushState( null, '', url.hash );
		}
		navigateToTarget( target );
	}

	/**
	 * Detach listeners and cancel deferred initial navigation.
	 *
	 * @return {void}
	 */
	function cleanup() {
		root.removeEventListener( 'click', handleClick );
		view.removeEventListener( 'hashchange', navigateToHash );
		view.removeEventListener( 'load', scheduleInitialNavigation );
		view.clearTimeout( pendingNavigation );
		initializedDocuments.delete( root );
	}

	root.addEventListener( 'click', handleClick );
	view.addEventListener( 'hashchange', navigateToHash );
	if ( root.readyState === 'complete' ) {
		scheduleInitialNavigation();
	} else {
		view.addEventListener( 'load', scheduleInitialNavigation, {
			once: true,
		} );
	}
	initializedDocuments.set( root, cleanup );
	return cleanup;
}
