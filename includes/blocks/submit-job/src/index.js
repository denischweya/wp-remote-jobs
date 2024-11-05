/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * All files containing `style` keyword are bundled together. The code used
 * gets applied both to the front of your site and to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './style.scss';

/**
 * Internal dependencies
 */
import Edit from './edit';
import save from './save';
import metadata from './block.json';

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
registerBlockType( metadata.name, {
	icon: {
		src: <svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 130 130'>
			<line fill='none' stroke='#3B64F3' strokeMiterlimit='10' strokeWidth='2' x1='29.4' x2='52.588' y1='82.311' y2='82.311'/>
			<line fill='none' stroke='#3B64F3' strokeMiterlimit='10' strokeWidth='2' x1='29.4' x2='52.588' y1='63.641' y2='63.641'/>
			<line fill='none' stroke='#3B64F3' strokeMiterlimit='10' strokeWidth='2' x1='29.4' x2='52.588' y1='43.756' y2='43.756'/>
			<path d='M92.926,56.384v51.298c0,2.569-2.081,4.66-4.639,4.66H23.564c-2.558,0-4.639-2.091-4.639-4.66v-86.18c0-2.57,2.081-4.66,4.639-4.66h64.723c2.558,0,4.639,2.09,4.639,4.66v17.131l2-2.002V21.502c0-3.678-2.973-6.66-6.639-6.66H23.564c-3.666,0-6.639,2.982-6.639,6.66v86.18c0,3.678,2.973,6.66,6.639,6.66h64.723c3.666,0,6.639-2.982,6.639-6.66v-53.3L92.926,56.384z' fill='#3F65F0'/>
			<path d='M120.463,25.534c-0.557-0.898-1.405-1.936-2.393-2.923c-1.194-1.195-3.462-3.199-5.224-3.199c-0.649,0-1.247,0.242-1.681,0.677l-7.667,7.666L66.504,64.834c-0.802,0.802-1.354,1.759-1.662,2.775L56.718,83.41l16.313-7.45c1.085-0.305,2.081-0.879,2.9-1.698l37.688-37.771l6.975-6.975C121.251,28.858,121.762,27.634,120.463,25.534z M64.922,71.716c0.318,0.93,0.841,1.805,1.582,2.546c0.652,0.653,1.419,1.15,2.252,1.48l-8.193,4.07L64.922,71.716z M74.518,72.848c-0.882,0.881-2.054,1.366-3.301,1.366c-1.246,0-2.418-0.485-3.299-1.366c-0.879-0.88-1.363-2.052-1.363-3.3c0-1.249,0.484-2.421,1.363-3.3l34.402-34.4c0.484,1.073,1.402,2.305,2.805,3.709c0.894,0.894,2.387,2.239,3.82,2.861L74.518,72.848z M119.18,28.102l-8.564,8.564c-0.075,0.075-0.186,0.09-0.266,0.09c-0.414,0-1.743-0.546-3.811-2.614c-2.402-2.405-2.823-3.776-2.523-4.076l8.564-8.563c0.079-0.08,0.201-0.091,0.267-0.091c0.414,0,1.743,0.546,3.81,2.613c0.867,0.867,1.635,1.8,2.106,2.562C118.838,26.708,119.491,27.79,119.18,28.102z' fill='#3F65F0'/>
		</svg>
	},
	/**
	 * @see ./edit.js
	 */
	edit: Edit,

	/**
	 * @see ./save.js
	 */
	save,
} );
