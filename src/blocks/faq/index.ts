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
import { faqDeprecations } from '../../editor/deprecations';
import type { FaqAttributes } from '../../editor/migrations/legacy-attributes';

registerBlockType< FaqAttributes >(
	metadata as unknown as BlockConfiguration< FaqAttributes >,
	{
		edit: Edit,
		save,
		deprecated: faqDeprecations,
	}
);
