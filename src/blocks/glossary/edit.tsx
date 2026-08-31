import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

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
import type { GlossaryAttributes } from '../../editor/migrations/legacy-attributes';

export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< GlossaryAttributes > ) {
	const {
		category,
		hstart,
		id,
		lang,
		order,
		register,
		registerstyle,
		sort,
		style,
		tag,
	} = attributes;
	const blockProps = useBlockProps( {
		className: style === 'dark' ? 'is-style-dark' : undefined,
	} );
	const allLabel = __( 'all', 'rrze-answers' );
	const categories = useTermOptions( 'rrze_glossary_category', allLabel, '', {
		hierarchical: true,
	} );
	const tags = useTermOptions( 'rrze_glossary_tag', allLabel );
	const posts = usePostOptions( 'rrze_glossary', allLabel );
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
							'Only show glossary entries with these selected categories.',
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
							'Only show glossary entries with these selected tags.',
							'rrze-answers'
						) }
						value={ tagSelection.selectedValues }
						options={ tags.options }
						onChange={ tagSelection.onChange }
						isLoading={ tags.isLoading }
					/>
					<EntityMultiSelectControl
						label={ __(
							'Single glossary entries',
							'rrze-answers'
						) }
						help={ __(
							'Only show these glossary entries.',
							'rrze-answers'
						) }
						value={ postSelection.selectedValues }
						options={ posts.options }
						onChange={ ( values ) => {
							postSelection.onChange( values );
							if (
								values.some( Boolean ) &&
								[ 'tabs', 'tagcloud' ].includes( registerstyle )
							) {
								setAttributes( { registerstyle: 'a-z' } );
							}
						} }
						isLoading={ posts.isLoading }
						emptyMessage={ __(
							'No glossary entries found.',
							'rrze-answers'
						) }
					/>
					<LanguageControl
						value={ lang }
						help={ __(
							'Only show glossary entries in this language.',
							'rrze-answers'
						) }
						onChange={ ( value ) =>
							setAttributes( { lang: value } )
						}
					/>
					<SelectControl
						label={ __(
							'Group register content by',
							'rrze-answers'
						) }
						help={
							id.length
								? __(
										'Grouping is unavailable while individual glossary entries are selected.',
										'rrze-answers'
								  )
								: __(
										'Group glossary entries by categories or tags.',
										'rrze-answers'
								  )
						}
						value={ register || '' }
						options={ getGroupingOptions() }
						disabled={ id.length > 0 }
						onChange={ ( value ) => {
							setAttributes( {
								register: value,
								...( ! value &&
								[ 'tabs', 'tagcloud' ].includes( registerstyle )
									? { registerstyle: 'a-z' }
									: {} ),
							} );
						} }
					/>
				</PanelBody>
				<AppearancePanel
					attributes={ attributes }
					setAttributes={ setAttributes }
					indexAttribute="registerstyle"
					indexLabel={ __( 'Register style', 'rrze-answers' ) }
					allowGroupedIndexStyles={
						Boolean( register ) && ! id.length
					}
				/>
				<SortingPanel
					order={ order }
					sort={ sort }
					setAttributes={ setAttributes }
				/>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="rrze-answers/glossary"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
