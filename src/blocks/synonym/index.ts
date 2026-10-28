/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';
import type { BlockConfiguration } from '@wordpress/blocks';
import { ReactComponent as SynonymIcon } from '../../../assets/svg/synonym.svg';
import './synonym-format'; // Registers the inline toolbar format.

/**
 * Internal dependencies
 */
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import { synonymDeprecations } from '../../editor/deprecations';
import type { SynonymAttributes } from '../../editor/migrations/legacy-attributes';

registerBlockType< SynonymAttributes >(
	metadata as unknown as BlockConfiguration< SynonymAttributes >,
	{
		icon: {
			src: SynonymIcon,
		},
		edit: Edit,
		save,
		deprecated: synonymDeprecations,
	}
);
