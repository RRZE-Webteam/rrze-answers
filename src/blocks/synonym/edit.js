import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import { LanguageControl } from '../../editor/components/common-controls';
import { EntityMultiSelectControl } from '../../editor/components/entity-controls';
import { usePostOptions } from '../../editor/hooks/use-entity-options';
import { useLegacyMultiSelect } from '../../editor/hooks/use-legacy-multi-select';

export default function Edit( { attributes, setAttributes } ) {
	const { id, lang } = attributes;
	const blockProps = useBlockProps();
	const posts = usePostOptions( 'rrze_synonym', __( 'all', 'rrze-answers' ) );
	const selection = useLegacyMultiSelect( id, 'id', setAttributes );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Filter options', 'rrze-answers' ) }>
					<EntityMultiSelectControl
						label={ __( 'Synonyms', 'rrze-answers' ) }
						help={ __(
							'Show a selection of individual synonyms.',
							'rrze-answers'
						) }
						value={ selection.selectedValues }
						options={ posts.options }
						onChange={ selection.onChange }
						isLoading={ posts.isLoading }
						emptyMessage={ __(
							'No synonyms found.',
							'rrze-answers'
						) }
					/>
					<LanguageControl
						value={ lang }
						help={ __(
							'Show only synonyms matching the selected language.',
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
					block="rrze-answers/synonym"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
