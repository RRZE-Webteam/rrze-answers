/**
 * RRZE Answers admin tours: About guide + contextual setup tour.
 */
import { useEffect, useState } from '@wordpress/element';
import { render } from '@wordpress/element';
import { Guide } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { SetupTour } from './setup-tour';

function GuideIcon( { dashicon } ) {
	return (
		<div className="rrze-answers-guided-tour__icon" aria-hidden="true">
			<span className={ `dashicons ${ dashicon }` } />
		</div>
	);
}

function dismissTour() {
	if ( typeof rrzeAnswersGuide === 'undefined' ) {
		return Promise.resolve();
	}

	const body = new FormData();
	body.append( 'action', 'rrze_answers_dismiss_guided_tour' );
	body.append( 'nonce', rrzeAnswersGuide.nonce );

	return fetch( rrzeAnswersGuide.ajaxUrl, {
		method: 'POST',
		body,
		credentials: 'same-origin',
	} );
}

function ToursApp( { autoStartGuide, autoStartSetup, setupTourStepId } ) {
	const setupTourActive =
		Boolean( autoStartSetup ) || setupTourStepId.length > 0;
	const [ guideOpen, setGuideOpen ] = useState(
		Boolean( autoStartGuide ) && ! setupTourActive
	);
	const [ setupOpen, setSetupOpen ] = useState( setupTourActive );
	const [ setupTourKey, setSetupTourKey ] = useState( 0 );
	const [ setupStepId, setSetupStepId ] = useState( setupTourStepId );

	useEffect( () => {
		const guideButton = document.getElementById(
			'rrze-answers-start-guided-tour'
		);
		const setupButton = document.getElementById(
			'rrze-answers-start-setup-tour'
		);

		const openGuide = () => {
			setSetupOpen( false );
			setGuideOpen( true );
		};
		const openSetup = () => {
			setGuideOpen( false );
			setSetupStepId( '' );
			setSetupTourKey( ( key ) => key + 1 );
			setSetupOpen( true );
		};

		guideButton?.addEventListener( 'click', openGuide );
		setupButton?.addEventListener( 'click', openSetup );

		return () => {
			guideButton?.removeEventListener( 'click', openGuide );
			setupButton?.removeEventListener( 'click', openSetup );
		};
	}, [] );

	const finishGuide = () => {
		setGuideOpen( false );
		dismissTour();
	};

	const githubUrl =
		typeof rrzeAnswersGuide !== 'undefined'
			? rrzeAnswersGuide.githubUrl
			: 'https://github.com/RRZE-Webteam/rrze-answers';

	const guidePages = [
		{
			image: <GuideIcon dashicon="dashicons-welcome-learn-more" />,
			content: (
				<>
					<h1 className="rrze-answers-guided-tour__heading">
						{ __( 'Welcome to RRZE Answers', 'rrze-answers' ) }
					</h1>
					<p className="rrze-answers-guided-tour__text">
						{ __(
							'This plugin helps you manage FAQ entries, glossary terms, synonyms, and placeholders — and embed them in pages with blocks or shortcodes.',
							'rrze-answers'
						) }
					</p>
				</>
			),
		},
		{
			image: <GuideIcon dashicon="dashicons-editor-help" />,
			content: (
				<>
					<h1 className="rrze-answers-guided-tour__heading">
						{ __( 'Create and manage content', 'rrze-answers' ) }
					</h1>
					<p className="rrze-answers-guided-tour__text">
						{ __(
							'Use the FAQ and Glossary menus in the WordPress admin to add questions, terms, categories, and tags.',
							'rrze-answers'
						) }
					</p>
				</>
			),
		},
		{
			image: <GuideIcon dashicon="dashicons-cloud" />,
			content: (
				<>
					<h1 className="rrze-answers-guided-tour__heading">
						{ __( 'Import from other sites', 'rrze-answers' ) }
					</h1>
					<p className="rrze-answers-guided-tour__text">
						{ __(
							'Use the setup tour to configure permissions, register domains, select categories, and synchronize FAQ and glossary content.',
							'rrze-answers'
						) }
					</p>
				</>
			),
		},
		{
			image: <GuideIcon dashicon="dashicons-media-text" />,
			content: (
				<>
					<h1 className="rrze-answers-guided-tour__heading">
						{ __( 'Logfile and blocks', 'rrze-answers' ) }
					</h1>
					<p className="rrze-answers-guided-tour__text">
						{ __(
							'After each sync, details are written to the logfile. Insert FAQ and glossary blocks in the editor or use shortcodes.',
							'rrze-answers'
						) }
					</p>
				</>
			),
		},
		{
			image: <GuideIcon dashicon="dashicons-heart" />,
			content: (
				<>
					<h1 className="rrze-answers-guided-tour__heading">
						{ __( 'Feedback and open source', 'rrze-answers' ) }
					</h1>
					<p className="rrze-answers-guided-tour__text">
						{ __(
							'We welcome your feedback. Everyone who wants to contribute is invited to take part.',
							'rrze-answers'
						) }
					</p>
					<p className="rrze-answers-guided-tour__text">
						{ __( 'RRZE Answers is open source on', 'rrze-answers' ) }{ ' ' }
						<a
							href={ githubUrl }
							target="_blank"
							rel="noopener noreferrer"
						>
							GitHub
						</a>
						.
					</p>
				</>
			),
		},
	];

	return (
		<>
			{ guideOpen && (
				<Guide
					className="rrze-answers-guided-tour"
					contentLabel={ __(
						'About RRZE Answers',
						'rrze-answers'
					) }
					finishButtonText={ __( 'Get started', 'rrze-answers' ) }
					onFinish={ finishGuide }
					pages={ guidePages }
				/>
			) }
			{ setupOpen && (
				<SetupTour
					key={ setupTourKey }
					initialStepId={ setupStepId }
					onClose={ () => setSetupOpen( false ) }
				/>
			) }
		</>
	);
}

const root = document.getElementById( 'rrze-answers-guided-tour-root' );

if ( root && typeof rrzeAnswersGuide !== 'undefined' ) {
	render(
		<ToursApp
			autoStartGuide={ rrzeAnswersGuide.autoStart }
			autoStartSetup={ rrzeAnswersGuide.autoStartSetup }
			setupTourStepId={ rrzeAnswersGuide.setupTourStepId }
		/>,
		root
	);
}
