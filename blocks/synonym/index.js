/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';
import './synonym-format'; // <-- registers the toolbar-button to use it inline

/**
 * Internal dependencies
 */
import metadata from './block.json';
import Edit from './edit'; // <-- we need the block to output the list of synonyms
import save from './save';

registerBlockType( metadata.name, {
    edit: Edit,
    save
} );