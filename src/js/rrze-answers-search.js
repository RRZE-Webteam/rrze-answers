( function () {
	'use strict';

	const itemSelector = 'details.rrze-answers-item, .rrze-answers-block-item';

	/**
	 * Normalize a string for comparison
	 *
	 * @param {unknown} str Value to normalize.
	 * @return {string} Normalized value.
	 */
	function normalize( str ) {
		return ( str || '' ).toString().trim().toLowerCase();
	}

	/**
	 * When Schema.org markup is active, <details> is wrapped
	 * in a Question <div>. We must hide/show that wrapper instead.
	 *
	 * @param {Element} itemEl Accordion item.
	 * @return {Element} Element that should be hidden or shown.
	 */
	function getToggleElement( itemEl ) {
		const schemaWrapper = itemEl.closest(
			'[itemscope][itemtype="https://schema.org/Question"]'
		);
		return schemaWrapper || itemEl;
	}

	/**
	 * Locate the visible question label for every supported accordion backend.
	 *
	 * @param {Element} itemEl Accordion item.
	 * @return {Element|null} Question label element.
	 */
	function getQuestionElement( itemEl ) {
		if ( itemEl.matches( 'details.rrze-answers-item' ) ) {
			return itemEl.querySelector( 'summary' );
		}

		return itemEl.querySelector(
			'.wp-block-accordion-heading__toggle-title, .accordion-toggle'
		);
	}

	/**
	 * Category/tag groups are wrapped in <section> with .answers-term-content.
	 * Show the section only if at least one accordion item inside is visible.
	 *
	 * @param {Element} wrapper Answers wrapper.
	 */
	function resetGroupedTermSections( wrapper ) {
		wrapper
			.querySelectorAll( '.answers-term-content' )
			.forEach( ( termContent ) => {
				const section = termContent.closest( 'section' );
				if ( section ) {
					section.style.display = '';
				}
			} );
	}

	/**
	 * Hide taxonomy sections that no longer contain a visible result.
	 *
	 * @param {Element} wrapper Answers wrapper.
	 */
	function syncGroupedTermSections( wrapper ) {
		wrapper
			.querySelectorAll( '.answers-term-content' )
			.forEach( ( termContent ) => {
				const section = termContent.closest( 'section' );
				if ( ! section ) {
					return;
				}
				const itemsInGroup =
					termContent.querySelectorAll( itemSelector );
				if ( ! itemsInGroup.length ) {
					return;
				}
				const anyVisible = Array.from( itemsInGroup ).some(
					( item ) =>
						getToggleElement( item ).style.display !== 'none'
				);
				section.style.display = anyVisible ? '' : 'none';
			} );
	}

	/**
	 * Initialize search for a single FAQ wrapper
	 *
	 * @param {HTMLElement} wrapper Answers wrapper.
	 */
	function initFAQSearch( wrapper ) {
		if ( ! wrapper || wrapper.dataset.rrzeFaqSearchInit === '1' ) {
			return;
		}

		const input = wrapper.querySelector( '.rrze-answers-search__input' );
		if ( ! input ) {
			return;
		}

		const accordionItems = Array.from(
			wrapper.querySelectorAll( itemSelector )
		);
		if ( ! accordionItems.length ) {
			return;
		}

		const minLen = parseInt(
			input.getAttribute( 'data-minlen' ) || '3',
			10
		);

		const items = accordionItems.map( ( item ) => {
			const question = getQuestionElement( item );
			return {
				item,
				question: normalize( question ? question.textContent : '' ),
			};
		} );

		function applyFilter( value ) {
			const query = normalize( value );
			if ( query.length < minLen ) {
				items.forEach( ( { item } ) => {
					getToggleElement( item ).style.display = '';
				} );
				resetGroupedTermSections( wrapper );
				return;
			}
			items.forEach( ( { item, question } ) => {
				const match = question.includes( query );
				getToggleElement( item ).style.display = match ? '' : 'none';
			} );
			syncGroupedTermSections( wrapper );
		}

		input.addEventListener( 'input', () => applyFilter( input.value ) );
		input.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Escape' ) {
				input.value = '';
				applyFilter( '' );
			}
		} );

		wrapper.dataset.rrzeFaqSearchInit = '1';
	}

	function initAll( root = document ) {
		root.querySelectorAll( '.rrze-answers' ).forEach( initFAQSearch );
	}

	initAll();

	/**
	 * Observer initialization after document.body is available
	 */
	function initObserver() {
		if ( ! document.body ) {
			return setTimeout( initObserver, 50 ); // warten, falls body noch nicht da
		}

		const observer = new window.MutationObserver( ( mutations ) => {
			for ( const mutation of mutations ) {
				for ( const node of mutation.addedNodes ) {
					if ( ! ( node instanceof window.HTMLElement ) ) {
						continue;
					}

					if ( node.matches && node.matches( '.rrze-answers' ) ) {
						initFAQSearch( node );
					}
					if ( node.querySelectorAll ) {
						initAll( node );
					}
				}
			}
		} );

		observer.observe( document.body, { childList: true, subtree: true } );
	}

	initObserver();
} )();
