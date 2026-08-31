function getTabs( tab ) {
	const tabList = tab.closest( '[role="tablist"]' );

	return tabList
		? Array.from( tabList.querySelectorAll( '[role="tab"]' ) )
		: [];
}

function getPanel( tab ) {
	const panelId = tab.getAttribute( 'aria-controls' );

	return panelId ? document.getElementById( panelId ) : null;
}

function activateTab( activeTab, moveFocus = false ) {
	getTabs( activeTab ).forEach( ( tab ) => {
		const isActive = tab === activeTab;
		const panel = getPanel( tab );

		tab.setAttribute( 'aria-selected', String( isActive ) );
		tab.setAttribute( 'tabindex', isActive ? '0' : '-1' );

		if ( panel ) {
			panel.hidden = ! isActive;
		}
	} );

	if ( moveFocus ) {
		activeTab.focus();
	}
}

document.addEventListener( 'click', ( event ) => {
	const tab = event.target.closest?.( '.rrze-answers-tab[role="tab"]' );

	if ( tab ) {
		activateTab( tab );
	}
} );

document.addEventListener( 'keydown', ( event ) => {
	const activeTab = event.target.closest?.( '.rrze-answers-tab[role="tab"]' );

	if ( ! activeTab ) {
		return;
	}

	const tabs = getTabs( activeTab );
	const index = tabs.indexOf( activeTab );
	let nextIndex;

	switch ( event.key ) {
		case 'ArrowLeft':
		case 'ArrowUp':
			nextIndex = ( index - 1 + tabs.length ) % tabs.length;
			break;
		case 'ArrowRight':
		case 'ArrowDown':
			nextIndex = ( index + 1 ) % tabs.length;
			break;
		case 'Home':
			nextIndex = 0;
			break;
		case 'End':
			nextIndex = tabs.length - 1;
			break;
		default:
			return;
	}

	event.preventDefault();
	activateTab( tabs[ nextIndex ], true );
} );
