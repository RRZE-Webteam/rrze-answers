/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';
import type { BlockConfiguration } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import { faqWidgetDeprecations } from '../../editor/deprecations';
import type { FaqWidgetAttributes } from '../../editor/migrations/legacy-attributes';

registerBlockType< FaqWidgetAttributes >(
	metadata as unknown as BlockConfiguration< FaqWidgetAttributes >,
	{
		edit: Edit,
		save,
		deprecated: faqWidgetDeprecations,
	}
);
