<?php

namespace Carbon_Fields\Widget;

use Carbon_Fields\Helper\Helper;
use Carbon_Fields\Container\Container;
use Carbon_Fields\Datastore\Datastore;
use Carbon_Fields\Exception\Incorrect_Syntax_Exception;

/**
 * Widget, datastore and container handler class.
 */
abstract class Widget extends \WP_Widget {
	public static $registered_widget_ids = array();
    private $registered_field_names = array();

	/**
	 * Widget Datastore
	 *
	 * @var \Carbon_Fields\Datastore\Widget_Datastore
	 */
	protected $datastore;

	/**
	 * Determines if widget wrapper html should be printed
	 *
	 * @see widget()
	 * @var bool
	 */
	protected $print_wrappers = true;

	/**
	 * Control options to pass to WordPress Widget constructor
	 *
	 * @see setup()
	 * @var array
	 */
	protected $widget_control_options = array( 'width' => 295 );

	/**
	 * Array of Carbon Fields for the widget
	 *
	 * @var array
	 */
	protected $custom_fields = array();

	/**
	 * String prefix for widget ids
	 *
	 * @var string
	 */
	protected $widget_id_prefix = 'carbon_fields_';

	/**
	 * Create the widget.
	 * A wrapper around the default WP widget constructor.
	 *
	 * @param string $widget_id   Widget id
	 * @param string $title       Widget name
	 * @param string $description Widget description
	 * @param array  $fields      Array of fields
	 * @param string $classname   String of CSS classes
	 */
	public function setup( $widget_id, $title, $description, $fields, $classname = '' ) {
		\Carbon_Fields\Carbon_Fields::verify_boot();
		$widget_id = $this->widget_id_prefix . $widget_id;

		$this->datastore = Datastore::make( 'widget' );
		if ( empty( $title ) ) {
			Incorrect_Syntax_Exception::raise( 'Empty widget title is not supported' );
		}

		$this->add_fields( $fields );

		$this->register_widget_id( $widget_id );

		# Generate Classes
		if ( ! is_array( $classname ) ) {
			$classname = (array) $classname;
		}
		$classname[] = $widget_id;
		$classname = array_filter( $classname );
		$classname = implode( ' ', $classname );

		$widget_options = array(
			'description' => $description,
			'classname' => $classname,
			'widget_ID' => $widget_id,
		);

		parent::__construct( $widget_id, $title, $widget_options, $this->widget_control_options );
	}

	/**
	 * Updates a particular instance of a widget.
	 *
	 * @param array $new_instance New settings for this instance as input by the user via
	 *                            WP_Widget::form().
	 * @param array $old_instance Old settings for this instance.
	 * @return array Settings to save or bool false to cancel saving.
	 */
	public function update( $new_instance, $old_instance ) {
		// Support compacted input
		$new_instance = Helper::expand_compacted_input( $new_instance );
		$compact_key = 'widget-' . $this->id_base;
		if ( ! empty( $new_instance[ $compact_key ][0] ) ) {
			$new_instance = array_merge( $new_instance, $new_instance[ $compact_key ][0] );
			unset( $new_instance[ $compact_key ] );
		}

		// Handle update
		$this->datastore->import_storage( $old_instance );

		foreach ( $this->custom_fields as $field ) {
			$field->set_value_from_input( $new_instance );
			$field->save();
		}

		return $this->datastore->export_storage();
	}

	/**
	 * Outputs the settings update form.
	 *
	 * @param  array $instance Current settings.
	 * @return void
	 */
	public function form( $instance ) {
		$this->datastore->import_storage( $instance );

		$this->register_container( true );
	}

	/**
	 * Registers the container definition for the widget.
	 *
	 * @param  boolean $render Whether the form should be rendered
	 * @return void
	 */
	public function register_container( $render = false ) {
		$custom_fields = array();

		foreach ( $this->custom_fields as $field ) {
			$tmp_field = clone $field;
			$tmp_field->load();

			$field_name = $this->get_field_name( $tmp_field->get_name() );
			$tmp_field->set_name( $field_name );

			$custom_fields[] = $tmp_field;
		}

		$container = Container::factory( 'widget', $this->id, $this->id )
			->add_fields( $custom_fields )
			->init();

		if ( $render ) {
			$container->render();
		}
	}

	/**
	 * Echoes the widget content.
	 * Sub-classes can over-ride this method to generate their widget code
	 * but it is best to override front_end().
	 *
	 * @param array $args     Display arguments including 'before_title', 'after_title',
	 *                        'before_widget', and 'after_widget'.
	 * @param array $instance The settings for the particular instance of the widget.
	 */
	public function widget( $args, $instance ) {
		$this->datastore->import_storage( $instance );

		if ( $this->print_wrappers ) {
			echo $args['before_widget'];
		}

		$instance_values = array();
		foreach ( $this->custom_fields as $field ) {
			$clone = clone $field;
			$clone->load();
			$instance_values[ $clone->get_base_name() ] = $clone->get_formatted_value();
		}
		$this->front_end( $args, $instance_values );

		if ( $this->print_wrappers ) {
			echo $args['after_widget'];
		}
	}

	/**
	 * The actual content of the widget.
	 * Generally should be overriden by the specific widget classes.
	 * @param array $args     Display arguments including 'before_title', 'after_title',
	 *                        'before_widget', and 'after_widget'.
	 * @param array $instance The settings for the particular instance of the widget.
	 */
	public function front_end( $args, $instance ) { }

	/**
	 * Append array of fields to the current fields set. All items of the array
	 * must be instances of Field and their names should be unique for all
	 * Carbon containers.
	 *
	 * @param array $fields
	 */
	public function add_fields( $fields ) {
		foreach ( $fields as $field ) {
			if ( ! ( $field instanceof \Carbon_Fields\Field\Field ) ) {
				Incorrect_Syntax_Exception::raise( 'Object must be of type Carbon_Fields\\Field\\Field' );
				return;
			}
			$this->register_field_name( $field->get_name() );
			$field->set_name_prefix( '' );
			$field->set_datastore( $this->datastore, true );
		}
		$this->custom_fields = array_merge( $this->custom_fields, $fields );
	}

	/**
	 * Verify widget field names are unique.
	 *
	 * @param  string $name Field name
	 * @return boolean
	 */
	public function register_field_name( $name ) {
		if ( in_array( $name, $this->registered_field_names ) ) {
			Incorrect_Syntax_Exception::raise( 'Field name "' . $name . '" already registered' );
			return false;
		}

		$this->registered_field_names[] = $name;
		return true;
	}

	/**
	 * Verify widget IDs are unique.
	 *
	 * @param  string $id Widget ID
	 */
	public function register_widget_id( $id ) {
		if ( in_array( $id, static::$registered_widget_ids ) ) {
			Incorrect_Syntax_Exception::raise( 'Widget with ID "' . $id . '" already registered. Please change the widget title' );
			return;
		}

		static::$registered_widget_ids[] = $id;
	}
}
