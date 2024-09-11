/* eslint no-console: [ 'error', { allow: [ 'error' ] } ] */

/**
 * External dependencies.
 */
import { dispatch } from '@wordpress/data';
import { registerBlockType } from '@wordpress/blocks';
import { setLocaleData } from '@wordpress/i18n';
import {
	get,
	kebabCase,
	isPlainObject
} from 'lodash';

/**
 * Internal dependencies.
 */
import './fields';
import './store';
import BlockEdit from './components/block-edit';
import BlockSave from './components/block-save';
import transformFieldsToAttributes from './utils/transform-fields-to-attributes';

/**
 * Sets the locale data for the package type
 */
setLocaleData( window.cf.config.locale, 'carbon-fields-ui' );

/**
 * Register the blocks.
 */
const containerDefinitions = {};
const fieldDefinitions = {};

get( window.cf, 'preloaded.blocks', [] ).forEach( ( container ) => {
	const name = kebabCase( container.id ).replace( 'carbon-fields-container-', '' );
	const fields = transformFieldsToAttributes( container.fields );

	const getBlockSetting = ( key, def = null ) => get( container, `settings.${ key }`, def );

	containerDefinitions[ name ] = container;
	fieldDefinitions[ name ] = container.fields.map( ( field ) => ( { ...field } ) );

	let attributes = {};

	let alignDefault = getBlockSetting( 'align_default', false );
	if(alignDefault) {
		attributes['align'] = {
			'type': 'string',
			'default': alignDefault
		};
	}

	registerBlockType( `carbon-fields/${ name }`, {
		title: container.title,
		icon: getBlockSetting( 'icon' ),
		parent: getBlockSetting( 'parent', [] ),
		category: getBlockSetting( 'category.slug' ),
		keywords: getBlockSetting( 'keywords', [] ),
		description: getBlockSetting( 'description', '' ),
		attributes: {
			data: {
				type: 'object',
				default: fields
			},
			...attributes
		},
		supports: {
			tabs: isPlainObject( getBlockSetting( 'tabs' ) ),
			preview: getBlockSetting( 'preview' ),
			innerBlocks: getBlockSetting( 'inner_blocks.enabled' ),
			align: getBlockSetting( 'align', false ),
			anchor: false,
			html: false
		},
		edit: BlockEdit,
		save: BlockSave,
		example: true,
	} );
} );

/**
 * Load the definitions in store.
 */
dispatch( 'carbon-fields/blocks' ).setupContainerDefinitions( containerDefinitions );
dispatch( 'carbon-fields/blocks' ).setupFieldDefinitions( fieldDefinitions );
