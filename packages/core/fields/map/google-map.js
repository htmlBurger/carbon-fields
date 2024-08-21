/**
 * External dependencies.
 */
import { Component, createRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export async function googleGeocode( address ) {
	const geocoder = new window.google.maps.Geocoder();

	return new Promise( ( resolve, reject ) => {
		geocoder.geocode( { address }, ( results, status ) => {
			if ( status === window.google.maps.GeocoderStatus.OK ) {
				const { location } = results[ 0 ].geometry;

				resolve( {
					lat: location.lat(),
					lng: location.lng()
				} );
			} else if ( status === 'ZERO_RESULTS' ) {
				reject( __( 'The address could not be found.', 'carbon-fields-ui' ) );
			} else {
				reject( `${ __( 'Geocode was not successful for the following reason: ', 'carbon-fields-ui' ) } ${ status }` );
			}
		} );
	} );
}

class GoogleMap extends Component {
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
		this.updateMap( this.props );

		const resizeObserver = new ResizeObserver( () => {
			this.updateMap( this.props );
		} );

		resizeObserver.observe( this.node.current );

		this.observer = resizeObserver;
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

		if ( this.marker ) {
			const markerLat = this.marker.getPosition().lat();
			const markerLng = this.marker.getPosition().lng();
			const mapZoom = this.map.getZoom();

			if ( lat !== markerLat || lng !== markerLng ) {
				const location = new window.google.maps.LatLng( lat, lng );

				this.marker.setPosition( location );
				this.map.setCenter( location );
			}

			if ( zoom !== mapZoom ) {
				this.map.setZoom( zoom );
			}
		}

		this.updateMap( this.props );
	}

	/**
	 * Lifecycle hook.
	 *
	 * @return {void}
	 */
	componentWillUnmount() {
		this.observer.disconnect();

		window.google.maps.event.clearInstanceListeners( this.map );
	}

	/**
	 * Initializes the map into placeholder element.
	 *
	 * @return {void}
	 */
	setupMap() {
		const {
			lat,
			lng,
			zoom
		} = this.props;

		const position = new window.google.maps.LatLng( lat, lng );

		this.map = new window.google.maps.Map( this.node.current, {
			zoom,
			center: position,
			mapTypeId: window.google.maps.MapTypeId.ROADMAP,
			scrollwheel: false
		} );

		this.marker = new window.google.maps.Marker( {
			position,
			map: this.map,
			draggable: true
		} );
	}

	/**
	 * Adds the listeners for the map's events.
	 *
	 * @return {void}
	 */
	setupMapEvents() {
		const enableScrollZoom = () => {
			this.map.setOptions( {
				scrollwheel: true
			} );
		};

		window.google.maps.event.addListenerOnce( this.map, 'click', enableScrollZoom );
		window.google.maps.event.addListenerOnce( this.map, 'dragend', enableScrollZoom );

		// Update the zoom when the map is changed.
		window.google.maps.event.addListener( this.map, 'zoom_changed', () => {
			this.props.onChange( {
				zoom: this.map.getZoom()
			} );
		} );

		// Update the position when the marker is moved.
		window.google.maps.event.addListener( this.marker, 'dragend', () => {
			this.props.onChange( {
				lat: this.marker.getPosition().lat(),
				lng: this.marker.getPosition().lng()
			} );
		} );
	}

	/**
	 * Re-draws the map.
	 *
	 * @param  {Object} props
	 * @return {void}
	 */
	updateMap( props ) {
		const { lat, lng } = props;
		const location = new window.google.maps.LatLng( lat, lng );

		setTimeout( () => {
			window.google.maps.event.trigger( this.map, 'resize' );

			this.map.setCenter( location );
		}, 10 );
	}

	/**
	 * Renders the component.
	 *
	 * @return {Object}
	 */
	render() {
		return (
			<div ref={ this.node } className={ this.props.className }></div>
		);
	}
}

export default GoogleMap;
