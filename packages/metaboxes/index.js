/**
 * External dependencies.
 */
import { setLocaleData } from '@wordpress/i18n';
import { addAction } from '@wordpress/hooks';

/**
 * Internal dependencies.
 */
import './store';
import './fields';
import initializeMonitors from './monitors';
import { default as initializeContainers, registerContainerType, Container } from './containers';

import isGutenberg from './utils/is-gutenberg';

/**
 * Public API
 */
export { registerContainerType, Container };

/**
 * Sets the locale data for the package type
 */
setLocaleData( window.cf.config.locale, 'carbon-fields-ui' );

/**
 * Determines the rendering context.
 *
 * @type {string}
 */
const context = isGutenberg() ? 'gutenberg' : 'classic';

/**
 * Abracadabra! Poof! Containers everywhere ...
 */
addAction( 'carbon-fields.init', 'carbon-fields/metaboxes', () => {
	initializeContainers( context );
	initializeMonitors( context );
} );
