/**
 * External dependencies.
 */
import { render } from '@wordpress/element';
import { select } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { forEach } from 'lodash';

/**
 * Internal dependencies.
 */
import './hooks';
import './widget';
import './term-meta';
import './theme-options';
import './user-meta';
import Container from '../components/container';
import { getContainerType, registerContainerType } from './registry';

export { registerContainerType, Container };

/**
 * Registers the containers.
 */
[
	'post_meta',
	'term_meta',
	'user_meta',
	'comment_meta',
	'network',
	'theme_options',
	'nav_menu_item',
	'widget'
].forEach( ( type ) => registerContainerType( type, Container ) );

/**
 * Renders the given container.
 *
 * @param  {Object} container
 * @param  {string} context
 * @return {void}
 */
export function renderContainer( container, context ) {
	const node = document.querySelector( `.container-${ container.id }` );
	const Component = getContainerType( container.type, context );

	if ( node ) {
		render(
			<Component id={ container.id } />,
			node
		);
	} else {
		// eslint-disable-next-line no-console
		console.error( sprintf( __( 'Could not find DOM element for container "%1$s".', 'carbon-fields-ui' ), container.id ) );
	}
}

/**
 * Initializes the containers.
 *
 * @param  {string} context
 * @return {void}
 */
export default function initializeContainers( context ) {
	const containers = select( 'carbon-fields/metaboxes' ).getContainers();

	forEach( containers, ( container ) => {
		renderContainer( container, context );
	} );
}
