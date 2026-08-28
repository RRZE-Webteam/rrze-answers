/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';
import type { BlockConfiguration } from '@wordpress/blocks';
import './placeholder-format'; // Registers the inline toolbar format.

/**
 * Internal dependencies
 */
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import { placeholderDeprecations } from '../../editor/deprecations';
import type { ListBlockAttributes } from '../../editor/migrations/legacy-attributes';

registerBlockType< ListBlockAttributes >(
	metadata as unknown as BlockConfiguration< ListBlockAttributes >,
	{
		edit: Edit,
		save,
		deprecated: placeholderDeprecations,
	}
);
