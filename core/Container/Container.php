<?php

namespace Carbon_Fields\Container;

use Carbon_Fields\Carbon_Fields;
use Carbon_Fields\Helper\Helper;
use Carbon_Fields\Field\Field;
use Carbon_Fields\Field\Group_Field;
use Carbon_Fields\Container\Fulfillable\Fulfillable_Collection;
use Carbon_Fields\Datastore\Datastore_Interface;
use Carbon_Fields\Datastore\Datastore_Holder_Interface;
use Carbon_Fields\Exception\Incorrect_Syntax_Exception;

/**
 * Base container class.
 * Defines the key container methods and their default implementations.
 */
abstract class Container implements Datastore_Holder_Interface {
	/**
	 * Where to put a particular tab -- at the head or the tail. Tail by default
	 */
	const TABS_TAIL = 1;
	const TABS_HEAD = 2;

	/**
	 * Separator signifying field hierarchy relation
	 * Used when searching for fields in a specific complex field
	 */
	const HIERARCHY_FIELD_SEPARATOR = '/';

	/**
	 * Separator signifying complex_field->group relation
	 * Used when searching for fields in a specific complex field group
	 */
	const HIERARCHY_GROUP_SEPARATOR = ':';

	/**
	 * Visual layout type constants
	 */
	const LAYOUT_TABBED_HORIZONTAL = 'tabbed-horizontal';
	const LAYOUT_TABBED_VERTICAL = 'tabbed-vertical';


	/**
	 * Stores if the container is active on the current page
	 *
	 * @see activate()
	 * @var bool
	 */
	protected $active = false;

	/**
	 * List of registered unique field names for this container instance
	 *
	 * @see register_field_name()
	 * @var array
	 */
	protected $registered_field_names = array();

	/**
	 * Complex field layout
	 *
	 * @var string static::LAYOUT_* constant
	 */
	protected $layout = self::LAYOUT_TABBED_HORIZONTAL;

	/**
	 * Tabs available
	 */
	protected $tabs = array();

	/**
	 * List of default container settings
	 *
	 * @see init()
	 * @var array
	 */
	public $settings = array();

	/**
	 * Unique ID of the container
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Title of the container
	 *
	 * @var string
	 */
	public $title = '';

	/**
	 * Type of the container
	 *
	 * @var string
	 */
	public $type;

	/**
	 * List of notification messages to be displayed on the front-end
	 *
	 * @var array
	 */
	protected $notifications = array();

	/**
	 * List of error messages to be displayed on the front-end
	 *
	 * @var array
	 */
	protected $errors = array();

	/**
	 * List of container fields
	 *
	 * @see add_fields()
	 * @var array
	 */
	protected $fields = array();

	/**
	 * Array of custom CSS classes.
	 *
	 * @see set_classes()
	 * @see get_classes()
	 * @var array<string>
	 */
	protected $classes = array();

	/**
	 * Container datastores. Propagated to all container fields
	 *
	 * @see set_datastore()
	 * @see get_datastore()
	 * @var Datastore_Interface
	 */
	protected $datastore;

	/**
	 * Flag whether the datastore is the default one or replaced with a custom one
	 *
	 * @see set_datastore()
	 * @see get_datastore()
	 * @var boolean
	 */
	protected $has_default_datastore = true;

	/**
	 * Fulfillable_Collection to use when checking attachment/saving conditions
	 *
	 * @var Fulfillable_Collection
	 */
	protected $condition_collection;

	/**
	 * Translator to use when translating conditions to json
	 *
	 * @var \Carbon_Fields\Container\Fulfillable\Translator\Translator
	 */
	protected $condition_translator;

	/**
	 * Create a new container of type $type and name $name.
	 *
	 * @param  string    $raw_type
	 * @param  string    $id        Unique id for the container. Optional
	 * @param  string    $name      Human-readable name of the container
	 * @return Container $container
	 */
	public static function factory( $raw_type, $id, $name = '' ) {
		// no name provided - switch input around as the id is optionally generated based on the name
		if ( $name === '' ) {
			$name = $id;
			$id = '';
		}

		$type = Helper::normalize_type( $raw_type );
		$repository = Carbon_Fields::resolve( 'container_repository' );
		$id = $repository->get_unique_container_id( ( $id !== '' ) ? $id : $name );

		if ( ! Helper::is_valid_entity_id( $id ) ) {
			Incorrect_Syntax_Exception::raise( 'Container IDs can only contain lowercase alphanumeric characters, dashes and underscores ("' . $id . '" passed).' );
			return null;
		}

		if ( ! $repository->is_unique_container_id( $id ) ) {
			Incorrect_Syntax_Exception::raise( 'The passed container id is already taken ("' . $id . '" passed).' );
			return null;
		}

		$container = null;
		if ( Carbon_Fields::has( $type, 'containers' ) ) {
			$container = Carbon_Fields::resolve_with_arguments( $type, array(
				'id' => $id,
				'name' => $name,
				'type' => $type,
			), 'containers' );
		} else {
			// Fallback to class name-based resolution
			$class = Helper::type_to_class( $type, __NAMESPACE__, '_Container' );

			if ( ! class_exists( $class ) ) {
				Incorrect_Syntax_Exception::raise( 'Unknown container "' . $raw_type . '".' );
				$class = __NAMESPACE__ . '\\Broken_Container';
			}

			$fulfillable_collection = Carbon_Fields::resolve( 'container_condition_fulfillable_collection' );
			$condition_translator = Carbon_Fields::resolve( 'container_condition_translator_json' );
			$container = new $class( $id, $name, $type, $fulfillable_collection, $condition_translator );
		}

		$repository->register_container( $container );

		return $container;
	}

	/**
	 * An alias of factory().
	 *
	 * @see    Container::factory()
	 * @return Container
	 */
	public static function make() {
		return call_user_func_array( array( static::class, 'factory' ), func_get_args() );
	}

	/**
	 * Create a new container
	 *
	 * @param string                 $id                   Unique id of the container
	 * @param string                 $title                Title of the container
	 * @param string                 $type                 Type of the container
	 * @param Fulfillable_Collection $condition_collection
	 * @param \Carbon_Fields\Container\Fulfillable\Translator\Translator $condition_translator
	 */
	public function __construct( $id, $title, $type, $condition_collection, $condition_translator ) {
		Carbon_Fields::verify_boot();

		if ( empty( $title ) ) {
			Incorrect_Syntax_Exception::raise( 'Empty container title is not supported' );
		}

		$this->id = $id;
		$this->title = $title;
		$this->type = $type;
		$this->condition_collection = $condition_collection;
		$this->condition_collection->set_condition_type_list(
			array_merge( $this->get_condition_types( true ), $this->get_condition_types( false ) ),
			true
		);
		$this->condition_translator = $condition_translator;
	}

	/**
	 * Return the container id
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get array of all static condition types
	 *
	 * @param  boolean       $static
	 * @return array<string>
	 */
	protected function get_condition_types( $static ) {
		$group = $static ? 'static' : 'dynamic';
		$container_type = Helper::class_to_type( get_class( $this ), '_Container' );

		$condition_types = array();
		$condition_types = apply_filters( 'carbon_fields_' . $container_type . '_container_' . $group . '_condition_types', $condition_types, $container_type, $this );
		$condition_types = apply_filters( 'carbon_fields_container_' . $group . '_condition_types', $condition_types, $container_type, $this );

		return $condition_types;
	}

	/**
	 * Return whether the container is active
	 */
	public function is_active() {
		return $this->active;
	}

	/**
	 * Activate the container and trigger an action
	 */
	protected function activate() {
		$this->active = true;
		$this->boot();
		do_action( 'carbon_fields_container_activated', $this );

		$fields = $this->get_fields();
		foreach ( $fields as $field ) {
			$field->activate();
		}
	}

	/**
	 * Perform instance initialization
	 */
	abstract public function init();

	/**
	 * Boot the container once it's attached.
	 */
	protected function boot() {

	}

	/**
	 * Load the value for each field in the container.
	 * Could be used internally during container rendering
	 */
	public function load() {
		foreach ( $this->fields as $field ) {
			$field->load();
		}
	}

	/**
	 * Called first as part of the container save procedure.
	 * Responsible for checking the request validity and
	 * calling the container-specific save() method
	 *
	 * @see save()
	 * @see is_valid_save()
	 */
	public function _save() {
		$param = func_get_args();
		if ( call_user_func_array( array( $this, '_is_valid_save' ), $param ) ) {
			call_user_func_array( array( $this, 'save' ), $param );
		}
	}

	/**
	 * Load submitted data and save each field in the container
	 *
	 * @see is_valid_save()
	 */
	public function save( $data = null ) {
		foreach ( $this->fields as $field ) {
			$field->set_value_from_input( Helper::input() );
			$field->save();
		}
	}

	/**
	 * Checks whether the current save request is valid
	 *
	 * @return bool
	 */
	final protected function _is_valid_save() {
		$params = func_get_args();
		$is_valid_save = call_user_func_array( array( $this, 'is_valid_save' ), $params );
		return apply_filters( 'carbon_fields_container_is_valid_save', $is_valid_save, $this );
	}

	/**
	 * Checks whether the current save request is valid
	 *
	 * @return bool
	 */
	abstract protected function is_valid_save();

	/**
	 * Called first as part of the container attachment procedure.
	 * Responsible for checking it's OK to attach the container
	 * and if it is, calling the container-specific attach() method
	 *
	 * @see attach()
	 * @see is_valid_attach()
	 */
	public function _attach() {
		if ( ! $this->is_valid_attach() ) {
			return;
		}

		$param = func_get_args();
		call_user_func_array( array( $this, 'attach' ), $param );

		// Allow containers to initialize but not activate (useful in cases such as theme options)
		if ( $this->should_activate() ) {
			$this->activate();
		}
	}

	/**
	 * Attach the container rendering and helping methods
	 * to concrete WordPress Action hooks
	 */
	public function attach() {}

	/**
	 * Perform checks whether the container should be attached during the current request
	 *
	 * @return bool True if the container is allowed to be attached
	 */
	final public function is_valid_attach() {
		$is_valid_attach = $this->is_valid_attach_for_request();
		return apply_filters( 'carbon_fields_container_is_valid_attach', $is_valid_attach, $this );
	}

	/**
	 * Get environment array for page request (in admin)
	 *
	 * @return array
	 */
	abstract protected function get_environment_for_request();

	/**
	 * Check container attachment rules against current page request (in admin)
	 *
	 * @return bool
	 */
	abstract protected function is_valid_attach_for_request();

	/**
	 * Check if conditions pass for request
	 *
	 * @return bool
	 */
	protected function static_conditions_pass() {
		$environment = $this->get_environment_for_request();
		$static_condition_collection = $this->condition_collection->evaluate(
			$this->get_condition_types( false ),
			true
		);
		return $static_condition_collection->is_fulfilled( $environment );
	}

	/**
	 * Get environment array for object id
	 *
	 * @param integer $object_id
	 * @return array
	 */
	abstract protected function get_environment_for_object( $object_id );

	/**
	 * Check container attachment rules against object id
	 *
	 * @param int $object_id
	 * @return bool
	 */
	abstract public function is_valid_attach_for_object( $object_id );

	/**
	 * Check if all conditions pass for object
	 *
	 * @param  integer $object_id
	 * @return bool
	 */
	protected function all_conditions_pass( $object_id ) {
		$environment = $this->get_environment_for_object( $object_id );
		return $this->condition_collection->is_fulfilled( $environment );
	}

	/**
	 * Whether this container is currently viewed.
	 */
	public function should_activate() {
		return true;
	}

	/**
	 * Perform a check whether the current container has fields
	 *
	 * @return bool
	 */
	public function has_fields() {
		return (bool) $this->fields;
	}

	/**
	 * Returns the private container array of fields.
	 * Use only if you are completely aware of what you are doing.
	 *
	 * @return Field[]
	 */
	public function get_fields() {
		return $this->fields;
	}

	/**
	 * Return root field from container with specified name
	 *
	 * @example crb_complex
	 *
	 * @param string $field_name
	 * @return Field
	 */
	public function get_root_field_by_name( $field_name ) {
		$fields = $this->get_fields();
		foreach ( $fields as $field ) {
			if ( $field->get_base_name() === $field_name ) {
				return $field;
			}
		}
		return null;
	}

	/**
	 * Get a regex to match field name patterns used to fetch specific fields
	 *
	 * @return string
	 */
	protected function get_field_pattern_regex() {
		$field_name_characters = Helper::get_field_name_characters_pattern();

		// matches:
		// field_name
		// field_name[0]
		// field_name[0]:group_name
		// field_name:group_name
		$regex = '/
			\A
			(?P<field_name>[' . $field_name_characters . ']+)
			(?:\[(?P<group_index>\d+)\])?
			(?:' .  preg_quote( static::HIERARCHY_GROUP_SEPARATOR, '/' ). '(?P<group_name>[' . $field_name_characters . ']+))?
			\z
		/x';
		return $regex;
	}

	/**
	 * Return field from container with specified name
	 *
	 * @example $field_name = 'crb_complex/text_field'
	 * @example $field_name = 'crb_complex/complex_2'
	 * @example $field_name = 'crb_complex/complex_2:text_group/text_field'
	 * @example $field_name = 'crb_complex[3]/complex_2[1]:text_group/text_field'
	 *
	 * @param string $field_name
	 * @return Field
	 */
	public function get_field_by_name( $field_name ) {
		$hierarchy = array_filter( explode( static::HIERARCHY_FIELD_SEPARATOR, $field_name ) );
		$field = null;

		$field_group = $this->get_fields();
		$hierarchy_left = $hierarchy;
		$field_pattern_regex = $this->get_field_pattern_regex();
		$hierarchy_index = array();

		while ( ! empty( $hierarchy_left ) ) {
			$segment = array_shift( $hierarchy_left );
			$segment_pieces = array();
			if ( ! preg_match( $field_pattern_regex, $segment, $segment_pieces ) ) {
				return null;
			}

			$segment_field_name = $segment_pieces['field_name'];
			$segment_group_index = isset( $segment_pieces['group_index'] ) ? $segment_pieces['group_index'] : 0;
			$segment_group_name = isset( $segment_pieces['group_name'] ) ? $segment_pieces['group_name'] : Group_Field::DEFAULT_GROUP_NAME;

			foreach ( $field_group as $f ) {
				if ( $f->get_base_name() !== $segment_field_name ) {
					continue;
				}

				if ( empty( $hierarchy_left ) ) {
					$field = clone $f;
					$field->set_hierarchy_index( $hierarchy_index );
				} else {
					if ( ! ( $f instanceof \Carbon_Fields\Field\Complex_Field ) ) {
						return null;
					}

					$group = $f->get_group_by_name( $segment_group_name );
					if ( ! $group ) {
						return null;
					}
					$field_group = $group->get_fields();
					$hierarchy_index[] = $segment_group_index;
				}
				break;
			}
		}

		return $field;
	}

	/**
	 * Perform checks whether there is a field registered with the name $name.
	 * If not, the field name is recorded.
	 *
	 * @param string $name
	 * @return boolean
	 */
	protected function register_field_name( $name ) {
		if ( in_array( $name, $this->registered_field_names ) ) {
			Incorrect_Syntax_Exception::raise( 'Field name "' . $name . '" already registered' );
			return false;
		}

		$this->registered_field_names[] = $name;
		return true;
	}

	/**
	 * Return whether the datastore instance is the default one or has been overriden
	 *
	 * @return boolean
	 */
	public function has_default_datastore() {
		return $this->has_default_datastore;
	}

	/**
	 * Set datastore instance
	 *
	 * @param Datastore_Interface $datastore
	 * @param bool                $set_as_default (optional)
	 * @return Container $this
	 */
	public function set_datastore( Datastore_Interface $datastore, $set_as_default = false ) {
		if ( $set_as_default && ! $this->has_default_datastore() ) {
			return $this; // datastore has been overriden with a custom one - abort changing to a default one
		}
		$this->datastore = $datastore;
		$this->has_default_datastore = $set_as_default;

		foreach ( $this->fields as $field ) {
			$field->set_datastore( $this->get_datastore(), true );
		}
		return $this;
	}

	/**
	 * Get the DataStore instance
	 *
	 * @return Datastore_Interface $datastore
	 */
	public function get_datastore() {
		return $this->datastore;
	}

	/**
	 * Return WordPress nonce name used to identify the current container instance
	 *
	 * @return string
	 */
	protected function get_nonce_name() {
		return $this->get_id() . '_nonce';
	}

	/**
	 * Return WordPress nonce name used to identify the current container instance
	 *
	 * @return string
	 */
	protected function get_nonce_value() {
		return wp_create_nonce( $this->get_nonce_name() );
	}

	/**
	 * Check if the nonce is present in the request and that it is verified
	 *
	 * @return bool
	 */
	protected function verified_nonce_in_request() {
		$input = Helper::input();
		$nonce_name = $this->get_nonce_name();
		$nonce_value = isset( $input[ $nonce_name ] ) ? $input[ $nonce_name ] : '';
		return wp_verify_nonce( $nonce_value, $nonce_name );
	}

	/**
	 * Internal function that creates the tab and associates it with particular field set
	 *
	 * @param string $tab_name
	 * @param array $fields
	 * @param int $queue_end
	 * @return object $this
	 */
	private function create_tab( $tab_name, $fields, $queue_end = self::TABS_TAIL ) {
		if ( isset( $this->tabs[ $tab_name ] ) ) {
			Incorrect_Syntax_Exception::raise( "Tab name duplication for $tab_name" );
		}

		if ( $queue_end === static::TABS_TAIL ) {
			$this->tabs[ $tab_name ] = array();
		} else if ( $queue_end === static::TABS_HEAD ) {
			$this->tabs = array_merge(
				array( $tab_name => array() ),
				$this->tabs
			);
		}

		foreach ( $fields as $field ) {
			$field_name = $field->get_name();
			$this->tabs[ $tab_name ][ $field_name ] = $field;
		}

		$this->settings['tabs'] = $this->get_tabs_json();
	}

	/**
	 * Whether the container is tabbed or not
	 *
	 * @return bool
	 */
	public function is_tabbed() {
		return (bool) $this->tabs;
	}

	/**
	 * Retrieve all fields that are not defined under a specific tab
	 *
	 * @return array
	 */
	protected function get_untabbed_fields() {
		$tabbed_fields_names = array();
		foreach ( $this->tabs as $tab_fields ) {
			$tabbed_fields_names = array_merge( $tabbed_fields_names, array_keys( $tab_fields ) );
		}

		$untabbed_fields = array_filter( $this->fields, function( $field ) use ( $tabbed_fields_names ) {
			return ! in_array( $field->get_name(), $tabbed_fields_names );
		} );

		return $untabbed_fields;
	}

	/**
	 * Retrieve all tabs.
	 * Create a default tab if there are any untabbed fields.
	 *
	 * @return array
	 */
	protected function get_tabs() {
		$untabbed_fields = $this->get_untabbed_fields();

		if ( ! empty( $untabbed_fields ) ) {
			$this->create_tab(
				apply_filters( 'carbon_fields_untabbed_fields_tab_title', __( 'General', 'carbon-fields' ), $this ),
				$untabbed_fields,
				static::TABS_HEAD
			);
		}

		return $this->tabs;
	}

	/**
	 * Build the tabs JSON
	 *
	 * @return array
	 */
	protected function get_tabs_json() {
		$tabs_json = array();
		$tabs = $this->get_tabs();

		foreach ( $tabs as $tab_name => $fields ) {
			foreach ( $fields as $field_name => $field ) {
				$tabs_json[ $tab_name ][] = $field_name;
			}
		}

		return $tabs_json;
	}

	/**
	 * Get custom CSS classes.
	 *
	 * @return array<string>
	 */
	public function get_classes() {
		return $this->classes;
	}

	/**
	 * Set CSS classes that the container should use.
	 *
	 * @param string|array<string> $classes
	 * @return Container $this
	 */
	public function set_classes( $classes ) {
		$this->classes = Helper::sanitize_classes( $classes );
		return $this;
	}

	/**
 	 * Adds a notification to be shown.
     *
	 * @param string $notification
  	 */
	public function add_notification ($notification) {
        $this->notifications[] = $notification;
    }

	/**
	 * Returns an array that holds the container data, suitable for JSON representation.
	 *
	 * @param bool $load  Should the value be loaded from the database or use the value from the current instance.
	 * @return array
	 */
	public function to_json( $load ) {
		$conditions = $this->condition_collection->evaluate( $this->get_condition_types( true ), $this->get_environment_for_request(), array( 'CUSTOM' ) );
		$conditions = $this->condition_translator->fulfillable_to_foreign( $conditions );

		$container_data = array(
			'id' => $this->get_id(),
			'type' => $this->type,
			'title' => $this->title,
			'layout' => $this->layout,
			'classes' => $this->get_classes(),
			'settings' => $this->settings,
			'conditions' => $conditions,
			'fields' => array(),
			'nonce' => array(
				'name' => $this->get_nonce_name(),
				'value' => $this->get_nonce_value(),
			),
		);

		$fields = $this->get_fields();
		foreach ( $fields as $field ) {
			$field_data = $field->to_json( $load );
			$container_data['fields'][] = $field_data;
		}

		return $container_data;
	}

	/**
	 * COMMON USAGE METHODS
	 */

	/**
	 * Append array of fields to the current fields set. All items of the array
	 * must be instances of Field and their names should be unique for all
	 * Carbon containers.
	 * If a field does not have DataStore already, the container datastore is
	 * assigned to them instead.
	 *
	 * @param array $fields
	 * @return Container $this
	 */
	public function add_fields( $fields ) {
		foreach ( $fields as $field ) {
			if ( ! ( $field instanceof Field ) ) {
				Incorrect_Syntax_Exception::raise( 'Object must be of type Carbon_Fields\\Field\\Field' );
				return $this;
			}

			$unique = $this->register_field_name( $field->get_name() );
			if ( ! $unique ) {
				return $this;
			}

			$field->set_context( $this->type );
			if ( ! $field->get_datastore() ) {
				$field->set_datastore( $this->get_datastore(), $this->has_default_datastore() );
			}
		}

		$this->fields = array_merge( $this->fields, $fields );

		return $this;
	}

	/**
	 * Configuration function for adding tab with fields
	 *
	 * @param string $tab_name
	 * @param array $fields
	 * @return Container $this
	 */
	public function add_tab( $tab_name, $fields ) {
		$this->add_fields( $fields );
		$this->create_tab( $tab_name, $fields );
		return $this;
	}

	/**
	 * Proxy function to set attachment conditions
	 *
	 * @see    Fulfillable_Collection::where()
	 * @return Container $this
	 */
	public function where() {
		call_user_func_array( array( $this->condition_collection, 'where' ), func_get_args() );
		return $this;
	}

	/**
	 * Proxy function to set attachment conditions
	 *
	 * @see    Fulfillable_Collection::or_where()
	 * @return Container $this
	 */
	public function or_where() {
		call_user_func_array( array( $this->condition_collection, 'or_where' ), func_get_args() );
		return $this;
	}

	/**
	 * Modify the layout of this field.
	 *
	 * @param  string $layout
	 * @return self   $this
	 */
	public function set_layout( $layout ) {
		$available_layouts = array(
			static::LAYOUT_TABBED_HORIZONTAL,
			static::LAYOUT_TABBED_VERTICAL,
		);

		if ( ! in_array( $layout,  $available_layouts ) ) {
			$error_message = 'Incorrect layout ``' . $layout . '" specified. ' .
				'Available layouts: ' . implode( ', ', $available_layouts );

			Incorrect_Syntax_Exception::raise( $error_message );
			return $this;
		}

		$this->layout = $layout;

		return $this;
	}
}
