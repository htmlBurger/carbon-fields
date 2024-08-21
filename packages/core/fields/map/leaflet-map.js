/**
 * Based on `google-maps.js`, adapted for Leaflet.js
 */

// External dependencies
import observeResize from 'observe-resize';
import { Component, createRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export async function nominatimGeocode( address ) {
	// Usage Policy: https://operations.osmfoundation.org/policies/nominatim/
	const nominatimSearchResults = await new Promise( ( resolve, reject ) => {
		const request = window.jQuery.ajax( {
			url: 'https://nominatim.openstreetmap.org/search',
			type: 'GET',
			data: { q: address, format: 'json', limit: 1 },
			headers: { 'User-Agent': 'Carbon Fields Map Field' }
		} );

		request.done( resolve );
		request.fail( () => {
			reject( __( 'An error occured.', 'carbon-fields-ui' ) );
		} );
	} );

	if ( nominatimSearchResults?.length !== 1 ) {
		throw __( 'The address could not be found.', 'carbon-fields-ui' );
	}

	const { lat, lon: lng } = nominatimSearchResults[ 0 ];
	return { lat, lng };
}

class LeafletMap extends Component {
	/**
	 * Keeps references to the DOM node.
	 *
	 * @type {Object}
	 */
	node = createRef();

	/**
	 * Lifecycle hook.
	 *
	 * @return {void}
	 */
	componentDidMount() {
		this.setupMap();
		this.setupMapEvents();
	}

	/**
	 * Lifecycle hook.
	 *
	 * @return {void}
	 */
	componentDidUpdate() {
		const {
			lat,
			lng,
			zoom
		} = this.props;

		const markerPosition = this.marker.getLatLng();
		if ( ! markerPosition.equals( [ lat, lng ] ) ) {
			this.marker.setLatLng( [ lat, lng ] );
			this.map.panTo( [ lat, lng ] );
		}

		const mapZoom = this.map.getZoom();
		if ( zoom !== mapZoom ) {
			this.map.setZoom( zoom );
		}
	}

	/**
	 * Lifecycle hook.
	 *
	 * @return {void}
	 */
	componentWillUnmount() {
		this.cancelResizeObserver();
		this.map.remove();
	}

	/**
	 * Initializes the map into placeholder element.
	 *
	 * @return {void}
	 */
	setupMap() {
		const { lat, lng, zoom } = this.props;
		const Leaflet = window.L;

		this.map = Leaflet.map( this.node.current, {
			center: [ lat, lng ],
			zoom,
			scrollWheelZoom: false
		} );

		Leaflet.tileLayer( 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
		} ).addTo( this.map );

		this.marker = Leaflet.marker( [ lat, lng ], {
			draggable: true
		} ).addTo( this.map );

		this.cancelResizeObserver = observeResize( this.node.current, () => {
			this.map.invalidateSize();
		} );
	}

	/**
	 * Adds the listeners for the map's events.
	 *
	 * @return {void}
	 */
	setupMapEvents() {
		// Enable scroll wheel zoom on focus
		this.map.once( 'focus', () => {
			this.map.scrollWheelZoom.enable();
		} );

		// Update zoom when changed
		this.map.on( 'zoomend', () => {
			this.props.onChange( { zoom: this.map.getZoom() } );
		} );

		// Update the position when the marker is moved
		this.marker.on( 'dragend', () => {
			const position = this.marker.getLatLng();
			this.map.panTo( position );
			this.props.onChange( { lat: position.lat, lng: position.lng } );
		} );
	}

	/**
	 * Renders the component.
	 *
	 * @return {Object}
	 */
	render() {
		return <div ref={ this.node } className={ this.props.className }></div>;
	}
}

export default LeafletMap;
