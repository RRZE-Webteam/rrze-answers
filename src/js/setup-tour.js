/**
 * Contextual setup tour for domains, import, and sync.
 */
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { SetupTourStepPanel } from './setup-tour-step';

function getSetupSteps() {
	return [
		{
			id: 'tab-domains',
			tab: 'domains',
			target: '[data-rrze-tour="tab-domains"]',
			title: __( 'Domains tab', 'rrze-answers' ),
			text: __(
				'Open the Domains tab to register websites you want to import FAQ or glossary content from.',
				'rrze-answers'
			),
		},
		{
			id: 'new-domain',
			tab: 'domains',
			target: '[data-rrze-tour="new-domain"]',
			title: __( 'Add a domain', 'rrze-answers' ),
			text: __(
				'Enter the URL of the source website (for example https://example.org) and save your settings.',
				'rrze-answers'
			),
		},
		{
			id: 'save-domain',
			tab: 'domains',
			target: '[data-rrze-tour="save-settings"]',
			title: __( 'Save the domain', 'rrze-answers' ),
			text: __(
				'Click Save changes to validate and store the new domain before configuring the import.',
				'rrze-answers'
			),
		},
		{
			id: 'tab-import',
			tab: 'import',
			target: '[data-rrze-tour="tab-import"]',
			title: __( 'Import tab', 'rrze-answers' ),
			text: __(
				'Switch to Import to choose which FAQ and glossary categories should be synchronized for each domain.',
				'rrze-answers'
			),
		},
		{
			id: 'import-categories',
			tab: 'import',
			target: '[data-rrze-tour="import-categories"]',
			title: __( 'Select categories', 'rrze-answers' ),
			text: __(
				'Pick one or more categories per content type. Only entries from the selected categories will be imported.',
				'rrze-answers'
			),
			optional: true,
		},
		{
			id: 'import-frequency',
			tab: 'import',
			target: '[data-rrze-tour="import-frequency"]',
			title: __( 'Automatic sync', 'rrze-answers' ),
			text: __(
				'Optionally schedule automatic synchronization (daily or twice daily) instead of importing manually each time.',
				'rrze-answers'
			),
		},
		{
			id: 'run-sync',
			tab: 'import',
			target: '[data-rrze-tour="save-settings"]',
			title: __( 'Run synchronization', 'rrze-answers' ),
			text: __(
				'Save changes to start the import. Progress and results are written to the logfile.',
				'rrze-answers'
			),
		},
		{
			id: 'tab-faqlog',
			tab: 'faqlog',
			target: '[data-rrze-tour="tab-faqlog"]',
			title: __( 'Logfile tab', 'rrze-answers' ),
			text: __(
				'Open the logfile to review sync results, errors, and timing details after each import.',
				'rrze-answers'
			),
		},
		{
			id: 'logfile-content',
			tab: 'faqlog',
			target: '[data-rrze-tour="logfile-content"]',
			title: __( 'Review the log', 'rrze-answers' ),
			text: __(
				'Each sync appends a timestamped entry here. Use it to verify imports or troubleshoot connection issues.',
				'rrze-answers'
			),
			optional: true,
		},
	];
}

function dismissSetupTour() {
	if ( typeof rrzeAnswersGuide === 'undefined' ) {
		return Promise.resolve();
	}

	const body = new FormData();
	body.append( 'action', 'rrze_answers_dismiss_setup_tour' );
	body.append( 'nonce', rrzeAnswersGuide.setupTourNonce );

	return fetch( rrzeAnswersGuide.ajaxUrl, {
		method: 'POST',
		body,
		credentials: 'same-origin',
	} );
}

function buildSettingsUrl( tab, stepId ) {
	const url = new URL( rrzeAnswersGuide.settingsUrl, window.location.origin );
	url.searchParams.set( 'tab', tab );
	url.searchParams.set( 'rrze_setup_tour', '1' );
	url.searchParams.set( 'rrze_setup_tour_step', stepId );
	return url.toString();
}

function findStepTarget( step ) {
	return document.querySelector( step.target );
}

function buildTourPath( steps ) {
	const seenTargets = new Set();

	return steps.filter( ( step ) => {
		if ( ! findStepTarget( step ) ) {
			return false;
		}

		const isTabStep = step.id.startsWith( 'tab-' );
		if ( ! isTabStep && step.tab !== rrzeAnswersGuide.activeTab ) {
			return false;
		}

		if ( seenTargets.has( step.target ) ) {
			return false;
		}

		seenTargets.add( step.target );
		return true;
	} );
}

function resolveStepIndex( path, stepId ) {
	if ( ! stepId ) {
		return 0;
	}

	const index = path.findIndex( ( step ) => step.id === stepId );

	return index >= 0 ? index : 0;
}

export function SetupTour( { initialStepId = '', onClose } ) {
	const allSteps = useMemo( getSetupSteps, [] );
	const path = useMemo( () => buildTourPath( allSteps ), [ allSteps ] );
	const [ stepIndex, setStepIndex ] = useState( () =>
		resolveStepIndex( path, initialStepId )
	);
	const [ anchor, setAnchor ] = useState( null );

	const currentStep = path[ stepIndex ];

	const syncAnchor = useCallback( () => {
		if ( ! currentStep ) {
			setAnchor( null );
			return;
		}

		if ( currentStep.tab !== rrzeAnswersGuide.activeTab ) {
			setAnchor( null );
			return;
		}

		const target = findStepTarget( currentStep );
		setAnchor( target );

		if ( target ) {
			target.classList.add( 'rrze-answers-setup-tour__highlight' );
			target.scrollIntoView( { block: 'center', behavior: 'smooth' } );
		}
	}, [ currentStep ] );

	useEffect( () => {
		syncAnchor();

		const onLayoutChange = () => syncAnchor();
		window.addEventListener( 'resize', onLayoutChange );
		window.addEventListener( 'scroll', onLayoutChange, true );

		return () => {
			window.removeEventListener( 'resize', onLayoutChange );
			window.removeEventListener( 'scroll', onLayoutChange, true );
			document
				.querySelectorAll( '.rrze-answers-setup-tour__highlight' )
				.forEach( ( element ) => {
					element.classList.remove(
						'rrze-answers-setup-tour__highlight'
					);
				} );
		};
	}, [ syncAnchor, stepIndex ] );

	const goToStep = ( nextIndex ) => {
		if ( nextIndex < 0 || nextIndex >= path.length ) {
			return;
		}

		const nextStep = path[ nextIndex ];

		if ( nextStep.tab !== rrzeAnswersGuide.activeTab ) {
			window.location.href = buildSettingsUrl( nextStep.tab, nextStep.id );
			return;
		}

		setStepIndex( nextIndex );
	};

	const finishTour = () => {
		dismissSetupTour();
		onClose?.();

		const url = new URL( window.location.href );
		url.searchParams.delete( 'rrze_setup_tour' );
		url.searchParams.delete( 'rrze_setup_tour_step' );
		window.history.replaceState( {}, '', url.toString() );
	};

	if ( ! currentStep || path.length === 0 ) {
		return null;
	}

	const needsTabSwitch = currentStep.tab !== rrzeAnswersGuide.activeTab;
	const isLast = stepIndex >= path.length - 1;
	const stepText =
		needsTabSwitch && ! anchor
			? __(
					'Continue to the next tab to see the highlighted field.',
					'rrze-answers'
			  )
			: currentStep.text;
	const nextLabel = needsTabSwitch
		? __( 'Open tab', 'rrze-answers' )
		: __( 'Next', 'rrze-answers' );

	const handleNext = () => {
		if ( isLast ) {
			finishTour();
			return;
		}

		if ( needsTabSwitch ) {
			goToStep( stepIndex );
			return;
		}

		goToStep( stepIndex + 1 );
	};

	return (
		<>
			<button
				type="button"
				className="rrze-answers-setup-tour__overlay"
				aria-label={ __( 'Close setup tour', 'rrze-answers' ) }
				onClick={ finishTour }
			/>
			<div
				className="rrze-answers-setup-tour__card"
				role="dialog"
				aria-modal="true"
				aria-label={ currentStep.title }
			>
				<SetupTourStepPanel
					stepNumber={ stepIndex + 1 }
					totalSteps={ path.length }
					title={ currentStep.title }
					text={ stepText }
					showPrevious={ stepIndex > 0 }
					isLast={ isLast }
					nextLabel={ nextLabel }
					onPrevious={ () => goToStep( stepIndex - 1 ) }
					onSkip={ finishTour }
					onNext={ handleNext }
				/>
			</div>
		</>
	);
}
