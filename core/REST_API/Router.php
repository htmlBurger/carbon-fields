<?php

namespace Carbon_Fields\REST_API;

use Carbon_Fields\Helper\Helper;
use Carbon_Fields\Container\Repository as ContainerRepository;

/**
* Register custom routes for REST API
*/
class Router {

	/**
	 * Carbon Fields routes
	 *
	 * @var array
	 */
	protected $routes = array(
		'post_meta' => array(
			'path'                => '/posts/(?P<id>\d+)',
			'callback'            => 'get_post_meta',
			'permission_callback' => 'allow_access',
			'methods'             => 'GET',
		),
		'term_meta' => array(
			'path'                => '/terms/(?P<id>\d+)',
			'callback'            => 'get_term_meta',
			'permission_callback' => 'allow_access',
			'methods'             => 'GET',
		),
		'user_meta' => array(
			'path'                => '/users/(?P<id>\d+)',
			'callback'            => 'get_user_meta',
			'permission_callback' => 'allow_access',
			'methods'             => 'GET',
		),
		'comment_meta' => array(
			'path'                => '/comments/(?P<id>\d+)',
			'callback'            => 'get_comment_meta',
			'permission_callback' => 'allow_access',
			'methods'             => 'GET',
		),
		'theme_options' => array(
			'path'                => '/options/',
			'callback'            => 'options_accessor',
			'permission_callback' => 'options_permission',
			'methods'             => array( 'GET', 'POST' ),
		),
		'association_data' => array(
			'path'                => '/association',
			'callback'            => 'get_association_data',
			'permission_callback' => 'allow_access',
			'methods'             => 'GET',
		),
		'association_options' => array(
			'path'                => '/association/options',
			'callback'            => 'get_association_options',
			'permission_callback' => 'allow_access',
			'methods'             => 'GET',
		),
		'attachment_data' => array(
			'path'                => '/attachment',
			'callback'            => 'get_attachment_data',
			'permission_callback' => 'allow_access',
			'methods'             => array( 'GET', 'POST' ),
			'args'                => 'attachment_data_args_schema',
		),
		'block_renderer' => array(
			'path'                => '/block-renderer',
			'callback'            => 'block_renderer',
			'permission_callback' => 'block_renderer_permission',
			'methods'             => 'POST',
			'args'                => 'block_renderer_args_schema',
		)
	);

	/**
	 * Version of the API
	 *
	 * @see set_version()
	 * @see get_version()
	 * @var string
	 */
	protected $version = '1';

	/**
	 * Vendor slug for the API
	 *
	 * @see set_vendor()
	 * @see get_vendor()
	 * @var string
	 */
	protected $vendor = 'carbon-fields';

	/**
	 * ContainerRepository instance
	 *
	 * @var ContainerRepository
	 */
	protected $container_repository;

	/**
	 * @param ContainerRepository $container_repository
	 */
	public function __construct( ContainerRepository $container_repository ) {
		$this->container_repository = $container_repository;
	}

	/**
	 * Boot up functionality
	 */
	public function boot() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 15 );
	}

	/**
	 * Set routes
	 *
	 * @param array $routes
	 */
	public function set_routes( $routes ) {
		$this->routes = $routes;
	}

	/**
	 * Return routes
	 *
	 * @return array
	 */
	public function get_routes() {
		return $this->routes;
	}

	/**
	 * Set version
	 *
	 * @param string $version
	 */
	public function set_version( $version ) {
		$this->version = $version;
	}

	/**
	 * Return version
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Set vendor
	 *
	 * @param string $vendor
	 */
	public function set_vendor( $vendor ) {
		$this->vendor = $vendor;
	}

	/**
	 * Return vendor
	 *
	 * @return string
	 */
	public function get_vendor() {
		return $this->vendor;
	}

	/**
	 * Allow access to an endpoint
	 *
	 * @return bool
	 */
	public function allow_access() {
		return true;
	}

	/**
	 * Register custom routes
	 *
	 * @see  register_route()
	 */
	public function register_routes() {
		foreach ( $this->routes as $route ) {
			$this->register_route( $route );
		}
	}

	/**
	 * Register a custom REST route
	 *
	 * @param  array $route
	 */
	protected function register_route( $route ) {
		register_rest_route( $this->get_vendor() . '/v' . $this->get_version(), $route['path'], array(
			'methods'             => $route['methods'],
			'permission_callback' => array( $this, $route['permission_callback'] ),
			'callback'            => array( $this, $route['callback'] ),
			'args'                => isset( $route['args'] ) ? call_user_func( array( $this, $route['args'] ) ) : array(),
		) );
	}

	/**
	 * Proxy method for handling get/set for theme options
	 *
	 * @param  \WP_REST_Request $request
	 * @return array|\WP_REST_Response
	 */
	public function options_accessor( $request ) {
		$request_type = $request->get_method();

		if ( $request_type === 'POST' ) {
			return $this->set_options( $request );
		}

		return $this->get_options();
	}

	/**
	 * Proxy method for handling theme options permissions
	 *
	 * @param  \WP_REST_Request $request
	 * @return bool
	 */
	public function options_permission( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Wrapper method used for retrieving data from Data_Manager
	 *
	 * @param  string $container_type
	 * @param  string $object_id
	 * @return array
	 */
	protected function get_all_field_values( $container_type, $object_id = null ) {
		$object_id = ( $object_id !== '' ) ? $object_id : null;

		$containers = $this->container_repository->get_containers( $container_type );
		$fields = array();
		foreach ( $containers as $container ) {
			$fields = array_merge( $fields, $container->get_fields() );
		}

		$values = array();
		foreach ( $fields as $field ) {
			if ( ! $field->get_visible_in_rest_api() ) {
				continue;
			}

			$value = Helper::get_value( $object_id, $container_type, '', $field->get_base_name() );

			if ( apply_filters( 'carbon_fields_rest_api_return_attachments_as_urls', false, $value, $field, $object_id ) ) {
				$attachments_class = [
					"Carbon_Fields\Field\Media_Gallery_Field",
					"Carbon_Fields\Field\File_Field",
					"Carbon_Fields\Field\Image_Field"
				];

				if ( in_array( get_class( $field ), $attachments_class ) ) {
					$value = Helper::get_attachments_urls($value);
				}
			}

			$values[ $field->get_base_name() ] = $value;
		}

		return $values;
	}

	/**
	 * Get Carbon Fields post meta values
	 *
	 * @param  array $data
	 * @return array
	 */
	public function get_post_meta( $data ) {
		$carbon_data = $this->get_all_field_values( 'post_meta', $data['id'] );
		return array( 'carbon_fields' => $carbon_data );
	}

	/**
	 * Get Carbon Fields user meta values
	 *
	 * @param  array $data
	 * @return array
	 */
	public function get_user_meta( $data ) {
		$carbon_data = $this->get_all_field_values( 'user_meta', $data['id'] );
		return array( 'carbon_fields' => $carbon_data );
	}

	/**
	 * Get Carbon Fields term meta values
	 *
	 * @param  array $data
	 * @return array
	 */
	public function get_term_meta( $data ) {
		$carbon_data = $this->get_all_field_values( 'term_meta', $data['id'] );
		return array( 'carbon_fields' => $carbon_data );
	}

	/**
	 * Get Carbon Fields comment meta values
	 *
	 * @param  array $data
	 * @return array
	 */
	public function get_comment_meta( $data ) {
		$carbon_data = $this->get_all_field_values( 'comment_meta', $data['id'] );
		return array( 'carbon_fields' => $carbon_data );
	}

	/**
	 * Get Carbon Fields association selected options.
	 *
	 * @access public
	 *
	 * @return array
	 */
	public function get_association_data() {
		$container_id = $_GET['container_id'];
		$field_id     = $_GET['field_id'];
		$options      = isset( $_GET['options'] ) ? explode( ';', $_GET['options'] ) : array();
		$return_value = array();

		/** @var \Carbon_Fields\Field\Association_Field $field */
		$field = Helper::get_field( null, $container_id, $field_id );

		$options = array_map( function ( $option ) {
			$option = explode( ':', $option );

			return [
				'id'      => $option[0],
				'type'    => $option[1],
				'subtype' => $option[2],
			];
		}, $options );

		foreach ( $options as $option ) {
			$item = array(
				'type'       => $option['type'],
				'subtype'    => $option['subtype'],
				'thumbnail'  => $field->get_thumbnail_by_type( $option['id'], $option['type'], $option['subtype'] ),
				'id'         => intval( $option['id'] ),
				'title'      => $field->get_title_by_type( $option['id'], $option['type'], $option['subtype'] ),
				'label'      => $field->get_item_label( $option['id'], $option['type'], $option['subtype'] ),
				'is_trashed' => ( $option['type'] == 'post' && get_post_status( $option['id'] ) === 'trash' ),
			);

			$return_value[] = $item;
		}

		return $return_value;
	}

	/**
	 * Get Carbon Fields association options data.
	 *
	 * @access public
	 *
	 * @return array
	 */
	public function get_association_options() {
		$page = isset( $_GET['page'] ) ? absint( $_GET['page'] )              : 1;
		$term = isset( $_GET['term'] ) ? sanitize_text_field( $_GET['term'] ) : '';

		$container_id = $_GET['container_id'];
		$field_id     = $_GET['field_id'];

		/** @var \Carbon_Fields\Field\Association_Field $field */
		$field = Helper::get_field( null, $container_id, $field_id );

		return $field->get_options( array(
			'page' => $page,
			'term' => $term,
		) );
	}

	/**
	 * Get attachment data by given ID or URL.
	 *
	 * @return array
	 */
	public function get_attachment_data( $request ) {
        $options = $request->get_params();
		return Helper::get_attachment_metadata( $options['value'], $options['type'] );
	}

	/**
	 * Retrieve Carbon theme options
	 *
	 * @return array
	 */
	protected function get_options() {
		$carbon_data = $this->get_all_field_values( 'theme_options' );
		return array( 'carbon_fields' => $carbon_data );
	}

	/**
	 * Set Carbon theme options
	 *
	 * @param \WP_REST_Request $request Full data about the request.
	 * @return \WP_Error|\WP_REST_Response
	 */
	protected function set_options( $request ) {
		$options = $request->get_params();

		if ( empty( $options ) ) {
			return new \WP_REST_Response( __( 'No option names provided', 'carbon-fields' ) );
		}

		foreach ( $options as $key => $value ) {
			try {
				Helper::set_value( null, 'Theme_Options', '', $key, $value );
			} catch ( \Exception $e ) {
				return new \WP_REST_Response( wp_strip_all_tags( $e->getMessage() ) );
			}
		}

		return new \WP_REST_Response( __( 'Theme Options updated.', 'carbon-fields' ), 200 );
	}

	/**
	 * Checks if a given request has access to read blocks.
	 *
	 * @see https://github.com/WordPress/WordPress/blob/master/wp-includes/rest-api/endpoints/class-wp-rest-block-renderer-controller.php#L78-L116
	 *
	 * @param  \WP_REST_Request
	 * @return true|\WP_Error
	 */
	public function block_renderer_permission( $request ) {
		global $post;

		$post_id = isset( $request['post_id'] ) ? intval( $request['post_id'] ) : 0;

		if ( 0 < $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
				return new \WP_Error(
					'block_cannot_read',
					__( 'Sorry, you are not allowed to read blocks of this post.', 'carbon-fields' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}
		} else {
			if ( ! current_user_can( 'edit_posts' ) ) {
				return new \WP_Error(
					'block_cannot_read',
					__( 'Sorry, you are not allowed to read blocks as this user.', 'carbon-fields' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}
		}

		return true;
	}

	/**
	 * Returns the schema of the accepted arguments.
	 *
	 * @return array
	 */
	public function attachment_data_args_schema() {
		return array(
			'type'       => array(
				'type'        => 'string',
				'required'    => true,
				'description' => __( 'The requested type: ID or URL.', 'carbon-fields' ),
			),
			'value'    => array(
				'type'        => 'string',
				'required'    => true,
				'description' => __( 'The ID / URL of the attachment', 'carbon-fields' ),
			),
		);
	}

	/**
	 * Returns the schema of the accepted arguments.
	 *
	 * @see https://github.com/WordPress/WordPress/blob/master/wp-includes/rest-api/endpoints/class-wp-rest-block-renderer-controller.php#L56-L71
	 *
	 * @return array
	 */
	public function block_renderer_args_schema() {
		return array(
			'name'       => array(
				'type'        => 'string',
				'required'    => true,
				'description' => __( 'The name of the block.', 'carbon-fields' ),
			),
			'content'    => array(
				'type'        => 'string',
				'required'    => true,
				'description' => __( 'The content of the block.', 'carbon-fields' ),
			),
			'post_id'    => array(
				'type'        => 'integer',
				'description' => __( 'ID of the post context.', 'carbon-fields' ),
			),
		);
	}

	/**
	 * Returns block output from block's registered render_callback.
	 *
	 * @see https://github.com/WordPress/WordPress/blob/master/wp-includes/rest-api/endpoints/class-wp-rest-block-renderer-controller.php#L118-L154
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function block_renderer( $request ) {
		global $post;

		$post_id = isset( $request['post_id'] ) ? intval( $request['post_id'] ) : 0;

		if ( 0 < $post_id ) {
			$post = get_post( $post_id );

			// Set up postdata since this will be needed if post_id was set.
			setup_postdata( $post );
		}

		$registry = \WP_Block_Type_Registry::get_instance();
		$block    = $registry->get_registered( $request['name'] );

		if ( null === $block ) {
			return new \WP_Error(
				'block_invalid',
				__( 'Invalid block.' ),
				array(
					'status' => 404,
				)
			);
		}

		$data = array(
			'rendered' => do_blocks( $request['content'] ),
		);

		return rest_ensure_response( $data );
	}
}
