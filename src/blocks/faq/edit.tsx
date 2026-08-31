import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { ServerSideRender } from '@wordpress/server-side-render';

import type { BlockEditProps } from '@wordpress/blocks';

import {
	AppearancePanel,
	getGroupingOptions,
	HeadingLevelToolbar,
	LanguageControl,
	SortingPanel,
} from '../../editor/components/common-controls';
import { EntityMultiSelectControl } from '../../editor/components/entity-controls';
import {
	usePostOptions,
	useTermOptions,
} from '../../editor/hooks/use-entity-options';
import { useMultiSelect } from '../../editor/hooks/use-multi-select';
import type { FaqAttributes } from '../../editor/migrations/legacy-attributes';

export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< FaqAttributes > ) {
	const { category, glossary, hstart, id, lang, order, sort, style, tag } =
		attributes;
	const blockProps = useBlockProps( {
		className: style === 'dark' ? 'is-style-dark' : 'is-style-light',
	} );
	const allLabel = __( 'all', 'rrze-answers' );
	const categories = useTermOptions( 'rrze_faq_category', allLabel, '', {
		hierarchical: true,
	} );
	const tags = useTermOptions( 'rrze_faq_tag', allLabel );
	const posts = usePostOptions( 'rrze_faq', allLabel );
	const categorySelection = useMultiSelect(
		category,
		'category',
		setAttributes
	);
	const tagSelection = useMultiSelect( tag, 'tag', setAttributes );
	const postSelection = useMultiSelect( id, 'id', setAttributes, 'integer' );

	return (
		<>
			<HeadingLevelToolbar
				value={ hstart }
				onChange={ ( value ) => setAttributes( { hstart: value } ) }
			/>
			<InspectorControls>
				<PanelBody title={ __( 'Filter options', 'rrze-answers' ) }>
					<EntityMultiSelectControl
						label={ __( 'Categories', 'rrze-answers' ) }
						help={ __(
							'Only show FAQ entries with these selected categories.',
							'rrze-answers'
						) }
						value={ categorySelection.selectedValues }
						options={ categories.options }
						onChange={ categorySelection.onChange }
						isLoading={ categories.isLoading }
					/>
					<EntityMultiSelectControl
						label={ __( 'Tags', 'rrze-answers' ) }
						help={ __(
							'Only show FAQ entries with these selected tags.',
							'rrze-answers'
						) }
						value={ tagSelection.selectedValues }
						options={ tags.options }
						onChange={ tagSelection.onChange }
						isLoading={ tags.isLoading }
					/>
					<EntityMultiSelectControl
						label={ __( 'Single FAQ entries', 'rrze-answers' ) }
						help={ __(
							'Only show these FAQ entries.',
							'rrze-answers'
						) }
						value={ postSelection.selectedValues }
						options={ posts.options }
						onChange={ postSelection.onChange }
						isLoading={ posts.isLoading }
						emptyMessage={ __(
							'No FAQ entries found.',
							'rrze-answers'
						) }
					/>
					<LanguageControl
						value={ lang }
						help={ __(
							'Only show FAQ entries in this language.',
							'rrze-answers'
						) }
						onChange={ ( value ) =>
							setAttributes( { lang: value } )
						}
					/>
					<SelectControl
						label={ __(
							'Group glossary content by',
							'rrze-answers'
						) }
						help={ __(
							'Group FAQ entries by categories or tags.',
							'rrze-answers'
						) }
						value={ glossary || '' }
						options={ getGroupingOptions() }
						onChange={ ( value ) =>
							setAttributes( { glossary: value } )
						}
					/>
				</PanelBody>
				<AppearancePanel
					attributes={ attributes }
					setAttributes={ setAttributes }
					indexAttribute="glossarystyle"
					indexLabel={ __( 'Glossary style', 'rrze-answers' ) }
					showSearch
				/>
				<SortingPanel
					order={ order }
					sort={ sort }
					setAttributes={ setAttributes }
				/>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="rrze-answers/faq"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
