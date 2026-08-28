import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { CheckboxControl, PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import type { BlockEditProps } from '@wordpress/blocks';

import { EntitySelectControl } from '../../editor/components/entity-controls';
import {
	usePostOptions,
	useTermOptions,
} from '../../editor/hooks/use-entity-options';
import type { FaqWidgetAttributes } from '../../editor/migrations/legacy-attributes';

export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< FaqWidgetAttributes > ) {
	const { id, catID, hide_title: hideTitle } = attributes;
	const blockProps = useBlockProps();
	const posts = usePostOptions(
		'rrze_faq',
		__( '— Select FAQ —', 'rrze-answers' ),
		0
	);
	const categories = useTermOptions(
		'rrze_faq_category',
		__( '— Select category —', 'rrze-answers' ),
		0,
		{ valueField: 'id' }
	);

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'FAQ selection', 'rrze-answers' ) }
					initialOpen
				>
					<EntitySelectControl
						label={ __( 'Choose a FAQ', 'rrze-answers' ) }
						value={ id }
						options={ posts.options }
						onChange={ ( value ) =>
							setAttributes( { id: parseInt( value, 10 ) || 0 } )
						}
						isLoading={ posts.isLoading }
						emptyMessage={ __(
							'No FAQ entries found.',
							'rrze-answers'
						) }
					/>
					<EntitySelectControl
						label={ __( 'Or', 'rrze-answers' ) }
						value={ catID }
						options={ categories.options }
						onChange={ ( value ) =>
							setAttributes( {
								catID: parseInt( value, 10 ) || 0,
							} )
						}
						isLoading={ categories.isLoading }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Display options', 'rrze-answers' ) }
					initialOpen={ false }
				>
					<CheckboxControl
						label={ __( 'Hide question title', 'rrze-answers' ) }
						checked={ !! hideTitle }
						onChange={ ( value ) =>
							setAttributes( { hide_title: value } )
						}
						help={ __(
							'If enabled, the FAQ title will be hidden.',
							'rrze-answers'
						) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="rrze-answers/faq-widget"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
