/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';
import type { BlockConfiguration } from '@wordpress/blocks';
import { ReactComponent as GlossaryIcon } from '../../../assets/svg/glossary.svg';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import { glossaryDeprecations } from '../../editor/deprecations';
import type { GlossaryAttributes } from '../../editor/migrations/legacy-attributes';

registerBlockType< GlossaryAttributes >(
	metadata as unknown as BlockConfiguration< GlossaryAttributes >,
	{
		icon: {
			src: GlossaryIcon,
		},
		edit: Edit,
		save,
		deprecated: glossaryDeprecations,
	}
);
