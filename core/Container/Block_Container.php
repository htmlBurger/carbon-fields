<?php

namespace Carbon_Fields\Container;

use Carbon_Fields\Datastore\Datastore;
use Carbon_Fields\Helper\Helper;
use Carbon_Fields\Exception\Incorrect_Syntax_Exception;

class Block_Container extends Container {
	/**
	 * {@inheritDoc}
	 */
	public $settings = array(
		'mode' => 'edit',
		'preview' => true,
		'parent' => null,
		'icon' => 'block-default',
		'inner_blocks' => array(
			'enabled' => false,
			'position' => 'above',
			'template' => null,
			'template_lock' => null,
			'allowed_blocks' => null,
		),
		'align' => false,
		'align_default' => false,
		'category' => array(
			'slug' => 'common',
		),
	);

	/**
	 * Mode map for settings
	 *
	 * @see set_mode()
	 * @var array
	 */
	protected $mode_map = array(
		'both' => array(
			'mode' => 'edit',
			'preview' => true,
		),
		'edit' => array(
			'mode' => 'edit',
			'preview' => false,
		),
		'preview' => array(
			'mode' => 'preview',
			'preview' => false,
		),
	);

	/***
	 * Block type render callback.
	 *
	 * @var callable
	 */
	protected $render_callback;

	/**
	 * {@inheritDoc}
	 */
	public function __construct( $id, $title, $type, $condition_collection, $condition_translator ) {
		parent::__construct( $id, $title, $type, $condition_collection, $condition_translator );

		if ( ! $this->get_datastore() ) {
			$this->set_datastore( Datastore::make( 'empty' ), $this->has_default_datastore() );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function init() {
		add_action( 'init', array( $this, '_attach' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_valid_save() {
		// Return false because Gutenberg
		// will handle saving.
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function save( $data = null ) {
		// Nothing to do here because
		// the data is saved by Gutenberg.
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_environment_for_request() {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_valid_attach_for_request() {
		return function_exists( 'register_block_type' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_environment_for_object( $object_id ) {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_valid_attach_for_object( $object_id = null ) {
		return function_exists( 'register_block_type' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function attach() {
		add_filter( 'block_categories_all', array( $this, 'attach_block_category' ), 10, 2 );

		$this->register_block();
	}

	/**
	 * Attach the category of the block type.
	 *
	 * @param  array $categories
	 * @return array
	 */
	public function attach_block_category( $categories ) {
		foreach ( $categories as $category ) {
			if ( $category[ 'slug' ] === $this->settings[ 'category' ][ 'slug' ] ) {
				return $categories;
			}
		}

		return array_merge( $categories, array( $this->settings[ 'category' ] ) );
	}

	/**
	 * Set the description of the block type.
	 *
	 * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/block-api/block-registration/#description-optional
	 *
	 * @param  string $description
	 * @return Block_Container
	 */
	public function set_description( $description ) {
		$this->settings[ 'description' ] = $description;

		return $this;
	}

	/**
	 * Set the category of the block type.
	 *
	 * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/block-api/block-registration/#category
	 *
	 * @param  string $slug
	 * @param  string $title
	 * @param  string $icon
	 * @return Block_Container
	 */
	public function set_category( $slug, $title = null, $icon = null ) {
		$this->settings[ 'category' ][ 'slug' ] = $slug;
		$this->settings[ 'category' ][ 'icon' ] = $icon;
		$this->settings[ 'category' ][ 'title' ] = $title ?: Helper::normalize_label( $slug );

		return $this;
	}

	/**
	 * Set the icon of the block type.
	 *
	 * @see https://developer.wordpress.org/resource/dashicons
	 * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/block-api/block-registration/#icon-optional
	 *
	 * @param  string $icon
	 * @return Block_Container
	 */
	public function set_icon( $icon ) {
		$this->settings[ 'icon' ] = $icon;

		return $this;
	}

	/**
	 * Set the keywords of the block type.
	 *
	 * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/block-api/block-registration/#keywords-optional
	 *
	 * @param  array $keywords
	 * @return Block_Container
	 */
	public function set_keywords( $keywords = array() ) {
		$this->settings[ 'keywords' ] = array_slice( $keywords, 0, 3 );

		return $this;
	}

	/**
	 * Set a style handle.
	 *
	 * @param  string $key
	 * @param  string $handle
	 * @return Block_Container
	 */
	protected function set_style_handle( $key, $handle ) {
		if ( ! wp_style_is( $handle, 'registered' ) ) {
			throw new \Exception( __( "Style '$handle' is not registered.", 'crb' ) );
		}

		$this->settings[ $key ] = $handle;

		return $this;
	}

	/**
	 * Set the style of the block type.
	 *
	 * @param  string $handle
	 * @return Block_Container
	 */
	public function set_style( $handle ) {
		return $this->set_style_handle( 'style', $handle );
	}

	/**
	 * Set the editor style of the block type.
	 *
	 * @param  string $handle
	 * @return Block_Container
	 */
	public function set_editor_style( $handle ) {
		return $this->set_style_handle( 'editor_style', $handle );
	}

	/**
	 * Set whether the preview mode is available for the block type.
	 *
	 * @param  boolean $preview
	 * @return Block_Container
	 */
	public function set_preview_mode( $preview = true ) {
		_deprecated_function( __FUNCTION__, '3.0', 'set_mode()' );

		$mode = $preview ? 'both' : 'edit';
		$this->set_mode( $mode );

		return $this;
	}

	/**
	 * Set the mode for the block type.
	 *
	 * @param  string $mode
	 * @return Block_Container
	 */
	public function set_mode( $mode ) {
		$modes = array_keys( $this->mode_map );
		if ( ! in_array( $mode, $modes ) ) {
			Incorrect_Syntax_Exception::raise( 'The mode must be one of the following: ' . implode( ', ', $modes ) );
		}

		$this->settings[ 'mode' ] = $this->mode_map[ $mode ][ 'mode' ];
		$this->settings[ 'preview' ] = $this->mode_map[ $mode ][ 'preview' ];

		return $this;
	}

	/**
	 * Set the parent block(s) in which the block type can be inserted.
	 *
	 * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/block-api/block-registration/#parent-optional
	 *
	 * @param  string|string[]|null $parent
	 * @return Block_Container
	 */
	public function set_parent( $parent = null ) {
		if ( ! is_array( $parent ) && ! is_string( $parent ) && ! is_null( $parent ) ) {
			throw new \Exception( __( "The parent must be 'array', 'string' or 'null'.", 'crb' ) );
		}

		$this->settings[ 'parent' ] = is_string( $parent ) ? array( $parent ) : $parent;

		return $this;
	}

	/**
	 * Set whether the inner blocks are available for the block type.
	 *
	 * @param  boolean $inner_blocks
	 * @return Block_Container
	 */
	public function set_inner_blocks( $inner_blocks = true ) {
		$this->settings[ 'inner_blocks' ][ 'enabled' ] = $inner_blocks;

		return $this;
	}

	/**
	 * Set the align options available for this block.
	 *
	 * Either pass a boolean or an array of values `left`, `center`, `right`, `wide`, and `full`.
	 *
	 * @param  array|boolean $inner_blocks
	 * @param  string|boolean $default
	 * @return Block_Container
	 */
	public function set_align( $align = false, $default = false ) {
		$this->settings[ 'align' ] = $align;
		$this->settings[ 'align_default' ] = $default;

		return $this;
	}

	/**
	 * Set the position of the inner blocks to be rendered
	 * above or below the fields.
	 *
	 * @param  string $position
	 * @return Block_Container
	 */
	public function set_inner_blocks_position( $position = 'above' ) {
		if ( ! in_array( $position, [ 'above', 'below' ] ) ) {
			throw new \Exception( __( "The position of inner blocks must be 'above' or 'below'.", 'crb' ) );
		}

		$this->settings[ 'inner_blocks' ][ 'position' ] = $position;

		return $this;
	}

	/**
	 * Set the default template that should be rendered in inner blocks.
	 *
	 * @see https://github.com/WordPress/gutenberg/tree/master/packages/editor/src/components/inner-blocks#template
	 *
	 * @param  array[]|null $template
	 * @return Block_Container
	 */
	public function set_inner_blocks_template( $template = null ) {
		if ( ! is_array( $template ) && ! is_null( $template ) ) {
			throw new \Exception( __( "The template must be an 'array' or 'null'.", 'crb' ) );
		}

		$this->settings[ 'inner_blocks' ][ 'template' ] = $template;

		return $this;
	}

	/**
	 * Set the lock mode used by template of inner blocks.
	 *
	 * @see https://github.com/WordPress/gutenberg/tree/master/packages/editor/src/components/inner-blocks#templatelock
	 *
	 * @param  string|boolean|null $lock
	 * @return Block_Container
	 */
	public function set_inner_blocks_template_lock( $lock = null ) {
		if ( is_string( $lock ) && ! in_array( $lock, [ 'all', 'insert' ] ) ) {
			throw new \Exception( __( "The template lock must be 'all', 'insert', 'false' or 'null'.", 'crb' ) );
		}

		$this->settings[ 'inner_blocks' ][ 'template_lock' ] = $lock;

		return $this;
	}

	/**
	 * Set the list of allowed blocks that can be inserted.
	 *
	 * @see https://github.com/WordPress/gutenberg/tree/master/packages/editor/src/components/inner-blocks#allowedblocks
	 *
	 * @param  string[]|null $blocks
	 * @return Block_Container
	 */
	public function set_allowed_inner_blocks( $blocks = null ) {
		if ( ! is_array( $blocks ) && ! is_null( $blocks ) ) {
			throw new \Exception( __( "The allowed blocks must be an 'array' or 'null'.", 'crb' ) );
		}

		if ( is_array( $blocks ) ) {
			$this->settings[ 'inner_blocks' ][ 'allowed_blocks' ] = array_map( function ( $block ) {
				if ( $block instanceof self ) {
					return $block->get_block_type_name();
				}

				return $block;
			}, $blocks );
		} else {
			$this->settings[ 'inner_blocks' ][ 'allowed_blocks' ] = $blocks;
		}

		return $this;
	}

	/**
	 * Set the render callback of the block type.
	 *
	 * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/tutorials/block-tutorial/creating-dynamic-blocks/
	 *
	 * @param  callable $render_callback
	 * @return Block_Container
	 */
	public function set_render_callback( $render_callback ) {
		$this->render_callback = $render_callback;

		return $this;
	}

	/**
	 * Get the post id where the block is used in.
	 * Try with the GET param, and if this is an
	 * AJAX request get previuos (admin) edit page.
	 * 
	 * @return int $post_id
	 */
	public function get_post_id() {
		$post_id = isset( $_GET['post'] ) && (int) $_GET['post'] > 0 ? (int) $_GET['post'] : null;
		if ( ! empty( $post_id ) ) {
			return $post_id;
		}

        $admin_url = isset( $_SERVER['HTTP_REFERER'] ) && ! empty( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : null;
        if ( ! empty( $admin_url ) ) {
            $parsed_url = parse_url( $admin_url );
            if ( isset( $parsed_url['query']) && ! empty( $parsed_url['query'] ) ) {
                $params = explode( '&', $parsed_url['query'] );
                foreach ( $params as $param ) {
                    $param = explode( '=', $param );
                    if ( $param[0] === 'post' ) {
                        $post_id = $param[1];
                    }
                }
            }
        }

		if ( empty( $post_id ) ) {
			$post_id = get_the_ID();
		}

        return $post_id;
	}

	/**
	 * Render the block type.
	 *
	 * @param  array  $attributes
	 * @param  string $content
	 * @param  \WP_Block $block
	 * @return string
	 */
	public function render_block( $attributes, $content, $block = null ) { // added variable at the end 
		$fields = $attributes['data'];
		$post_id = $this->get_post_id();
		// Unset the "data" property because we
		// pass it as separate argument to the callback.
		unset($attributes['data']);
		ob_start();
		call_user_func( $this->render_callback , $fields, $attributes, $content, $post_id, $block );
		$toReturn = ob_get_contents();
		ob_clean();
		return $toReturn;
	}

	/**
	 * Returns the block type name, e.g. "carbon-fields/testimonial"
	 */
	private function get_block_type_name() {
		return str_replace( 'carbon-fields-container-', 'carbon-fields/', str_replace( '_', '-', $this->id ) );
	}

	/**
	 * Register the block type.
	 *
	 * @return void
	 */
	protected function register_block() {
		if ( is_null( $this->render_callback ) ) {
			throw new \Exception( __( "'render_callback' is required for the blocks.", 'crb' ) );
		}

		if ( ! is_callable( $this->render_callback ) ) {
			throw new \Exception( __( "'render_callback' must be a callable.", 'crb' ) );
		}

		$style = isset( $this->settings[ 'style' ] ) ? $this->settings[ 'style' ] : null;
		$editor_style = isset( $this->settings[ 'editor_style' ] ) ? $this->settings[ 'editor_style' ] : null;
		$attributes = array_reduce( $this->get_fields(), function( $attributes, $field ) {
			$attributes[ 'data' ][ 'default' ][ $field->get_base_name() ] = $field->get_default_value();

			return $attributes;
		}, array(
			'data' => array(
				'type' => 'object',
				'default' => array(),
			),
		) );

		register_block_type( $this->get_block_type_name(), array(
			'style' => $style,
			'editor_style' => $editor_style,
			'attributes' => $attributes,
			'render_callback' => array( $this, 'render_block' ),
		) );
	}
}
