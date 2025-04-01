/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';

/**
 * Internal dependencies
 */
import Edit from './edit';
import save from './save';

// Import styles
import './style.scss';
import './editor.scss';

registerBlockType(metadata.name, {
    icon: {
        src: (
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M20 6H4V4h16v2zm0 2H4v2h16V8zm0 4H4v2h16v-2zm0 4H4v2h16v-2z" />
            </svg>
        )
    },
    edit: Edit,
    save
});