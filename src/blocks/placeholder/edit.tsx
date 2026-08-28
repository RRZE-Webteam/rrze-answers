import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import type { BlockEditProps } from '@wordpress/blocks';

import { LanguageControl } from '../../editor/components/common-controls';
import { EntityMultiSelectControl } from '../../editor/components/entity-controls';
import { usePostOptions } from '../../editor/hooks/use-entity-options';
import { useMultiSelect } from '../../editor/hooks/use-multi-select';
import type { ListBlockAttributes } from '../../editor/migrations/legacy-attributes';

export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< ListBlockAttributes > ) {
	const { id, lang } = attributes;
	const blockProps = useBlockProps();
	const posts = usePostOptions(
		'rrze_placeholder',
		__( 'all', 'rrze-answers' )
	);
	const selection = useMultiSelect( id, 'id', setAttributes, 'integer' );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Filter options', 'rrze-answers' ) }>
					<EntityMultiSelectControl
						label={ __( 'Placeholders', 'rrze-answers' ) }
						help={ __(
							'Show a selection of individual placeholders.',
							'rrze-answers'
						) }
						value={ selection.selectedValues }
						options={ posts.options }
						onChange={ selection.onChange }
						isLoading={ posts.isLoading }
						emptyMessage={ __(
							'No placeholders found.',
							'rrze-answers'
						) }
					/>
					<LanguageControl
						value={ lang }
						help={ __(
							'Show only placeholders matching the selected language.',
							'rrze-answers'
						) }
						onChange={ ( value ) =>
							setAttributes( { lang: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="rrze-answers/placeholder"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
