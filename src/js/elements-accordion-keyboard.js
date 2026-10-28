const initializedDocuments = new WeakMap();

/**
 * Find an Elements toggle belonging to an Answers-generated item.
 *
 * Checking the nearest Collapse wrapper excludes unrelated Elements blocks
 * nested inside an answer, even though they share the outer Answers region.
 *
 * @param {EventTarget|null} target Keyboard event target.
 * @return {HTMLButtonElement|null} Answers-owned toggle, or null.
 */
function getAnswersToggle( target ) {
	const button = target?.closest?.( 'button.accordion-toggle' );
	const item = button?.closest( '.wp-block-rrze-elements-collapse' );
	return item?.classList.contains( 'rrze-answers-block-item' ) &&
		button.matches(
			'.wp-block-rrze-elements-collapse > .accordion-group > .accordion-heading > button.accordion-toggle'
		) &&
		item.closest( '.rrze-answers' )
		? button
		: null;
}

/**
 * Observe the synchronous state exposed by Elements' activation handlers.
 *
 * @param {HTMLButtonElement} button Accordion toggle.
 * @return {string} Accessible and visual expansion state.
 */
function expansionState( button ) {
	return `${ button.getAttribute(
		'aria-expanded'
	) }:${ button.classList.contains( 'active' ) }`;
}

/**
 * Activate the installed Elements implementation once using its existing path.
 *
 * Older releases handle mousedown only. A canceled event or changed expansion
 * state means that handler consumed activation. Otherwise use the native click
 * path so a future click-based implementation remains compatible.
 *
 * @param {HTMLButtonElement} button Answers-owned Elements toggle.
 * @return {void}
 */
function activateToggle( button ) {
	const before = expansionState( button );
	const pointerEvent = new button.ownerDocument.defaultView.MouseEvent(
		'mousedown',
		{ bubbles: true, cancelable: true, button: 0 }
	);
	button.dispatchEvent( pointerEvent );
	if (
		! pointerEvent.defaultPrevented &&
		expansionState( button ) === before
	) {
		button.click();
	}
}

/**
 * Add Enter activation only to Answers-generated Elements accordion buttons.
 *
 * Capture consumes the Enter key sequence before target/delegated handlers and
 * prevents the browser's generated click. This avoids double toggles when a
 * future Elements release implements Enter itself. Space, mouse input, hash
 * navigation and initial expansion remain owned by Elements. Repeated setup
 * reuses one listener pair; the returned cleanup is useful for embedded views.
 *
 * @param {Document} root Document containing the rendered Answers content.
 * @return {Function} Remove this document's keyboard adapter.
 */
export function initializeElementsAccordionKeyboard( root = document ) {
	if ( initializedDocuments.has( root ) ) {
		return initializedDocuments.get( root );
	}
	const handledButtons = new WeakSet();

	/**
	 * Route one unmodified Enter press through the dependency's activation path.
	 *
	 * @param {KeyboardEvent} event Captured keydown event.
	 * @return {void}
	 */
	function handleKeydown( event ) {
		if (
			event.key !== 'Enter' ||
			event.defaultPrevented ||
			event.isComposing ||
			event.altKey ||
			event.ctrlKey ||
			event.metaKey ||
			event.shiftKey
		) {
			return;
		}
		const button = getAnswersToggle( event.target );
		if (
			! button ||
			button.disabled ||
			button.getAttribute( 'aria-disabled' ) === 'true'
		) {
			return;
		}
		event.preventDefault();
		event.stopImmediatePropagation();
		handledButtons.add( button );
		if ( ! event.repeat ) {
			activateToggle( button );
		}
	}

	/**
	 * Consume the release belonging to an Enter press already handled here.
	 *
	 * @param {KeyboardEvent} event Captured keyup event.
	 * @return {void}
	 */
	function handleKeyup( event ) {
		const button =
			event.key === 'Enter' ? getAnswersToggle( event.target ) : null;
		if ( button && handledButtons.has( button ) ) {
			handledButtons.delete( button );
			event.preventDefault();
			event.stopImmediatePropagation();
		}
	}

	/**
	 * Detach both listeners so this document can be initialized again cleanly.
	 *
	 * @return {void}
	 */
	function cleanup() {
		if ( initializedDocuments.get( root ) !== cleanup ) {
			return;
		}
		root.removeEventListener( 'keydown', handleKeydown, true );
		root.removeEventListener( 'keyup', handleKeyup, true );
		initializedDocuments.delete( root );
	}

	root.addEventListener( 'keydown', handleKeydown, true );
	root.addEventListener( 'keyup', handleKeyup, true );
	initializedDocuments.set( root, cleanup );
	return cleanup;
}
