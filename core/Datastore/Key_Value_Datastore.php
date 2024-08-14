<?php

namespace Carbon_Fields\Datastore;

use Carbon_Fields\Helper\Helper;
use Carbon_Fields\Field\Field;
use Carbon_Fields\Exception\Incorrect_Syntax_Exception;

/**
 * Key Value Datastore
 * Provides basic functionality to save in a key-value storage
 */
abstract class Key_Value_Datastore extends Datastore {

	/**
	 * Key Toolset for key generation and comparison utilities
	 *
	 * @var \Carbon_Fields\Toolset\Key_Toolset
	 */
	protected $key_toolset;

	/**
	 * Initialize the datastore.
	 */
	public function __construct() {
		$this->key_toolset = \Carbon_Fields\Carbon_Fields::resolve( 'key_toolset' );
		parent::__construct();
	}

	/**
	 * Get array of ancestors (ordered top-bottom) with the field name appended to the end
	 *
	 * @param Field $field
	 * @return array<string>
	 */
	protected function get_full_hierarchy_for_field( Field $field ) {
		$full_hierarchy = array_merge( $field->get_hierarchy(), array( $field->get_base_name() ) );
		return $full_hierarchy;
	}

	/**
	 * Get array of ancestor group indexes (ordered top-bottom)
	 * Indexes show which entry/group this field belongs to in a Complex_Field
	 *
	 * @param Field $field
	 * @return array<int>
	 */
	protected function get_full_hierarchy_index_for_field( Field $field ) {
		$hierarchy_index = $field->get_hierarchy_index();
		$full_hierarchy_index = ! empty( $hierarchy_index ) ? $hierarchy_index : array();
		return $full_hierarchy_index;
	}

	/**
	 * Convert a cascading storage array to a value tree array
	 *
	 * @see Internal Glossary in DEVELOPMENT.MD
	 * @param \stdClass[] $storage_array
	 * @return array
	 */
	protected function cascading_storage_array_to_value_tree_array( $storage_array ) {
		$tree = array();
		$found_keepalive = false;

		foreach ( $storage_array as $row ) {
			$parsed_storage_key = $this->key_toolset->parse_storage_key( $row->key );

			if ( $parsed_storage_key['property'] === $this->key_toolset->get_keepalive_property() ) {
				$found_keepalive = true;
				continue;
			}

			$level = &$tree;
			foreach ( $parsed_storage_key['full_hierarchy'] as $i => $field_name ) {
				$index = isset( $parsed_storage_key['hierarchy_index'][ $i ] ) ? $parsed_storage_key['hierarchy_index'][ $i ] : 0;

				if ( ! isset( $level[ $field_name ] ) ) {
					$level[ $field_name ] = array();
				}
				$level = &$level[ $field_name ];

				if ( $i < count( $parsed_storage_key['full_hierarchy'] ) - 1 ) {
					if ( ! isset( $level[ $index ] ) ) {
						$level[ $index ] = array();
					}
					$level = &$level[ $index ];
				} else  {
					if ( ! isset( $level[ $parsed_storage_key['value_index'] ] ) ) {
						$level[ $parsed_storage_key['value_index'] ] = array();
					}
					$level = &$level[ $parsed_storage_key['value_index'] ];

					$level[ $parsed_storage_key['property'] ] = $row->value;
				}
			}
			$level = &$tree;
		}
		Helper::ksort_recursive( $tree );

		if ( empty( $tree ) && ! $found_keepalive ) {
			return null;
		}

		return $tree;
	}

	/**
	 * Convert a value set tree to a value tree for the specified field
	 * (get a single value tree from the collection)
	 *
	 * @see Internal Glossary in DEVELOPMENT.MD
	 * @param  array $value_tree_array
	 * @param  Field $field
	 * @return array
	 */
	protected function value_tree_array_to_value_tree( $value_tree_array, Field $field ) {
		$value_tree = $value_tree_array;
		$hierarchy = $field->get_hierarchy();
		$hierarchy_index = $field->get_hierarchy_index();

		foreach ( $hierarchy as $index => $parent_field ) {
			$hierarchy_index_value = isset( $hierarchy_index[ $index ] ) ? $hierarchy_index[ $index ] : 0;
			if ( isset( $value_tree[ $parent_field ][ $hierarchy_index_value ] ) ) {
				$value_tree = $value_tree[ $parent_field ][ $hierarchy_index_value ];
			}
		}

		if ( isset( $value_tree[ $field->get_base_name() ] ) ) {
			$value_tree = $value_tree[ $field->get_base_name() ];
		}

		return $value_tree;
	}

	/**
	 * Get a raw database query results array for a field
	 *
	 * @param Field $field The field to retrieve value for.
	 * @param array $storage_key_patterns
	 * @return \stdClass[] Array of {key, value} objects
	 */
	abstract protected function get_storage_array( Field $field, $storage_key_patterns );

	/**
	 * Get the field value(s)
	 *
	 * @param Field $field The field to get value(s) for
	 * @return null|array<array>
	 */
	public function load( Field $field ) {
		$storage_key_patterns = $this->key_toolset->get_storage_key_getter_patterns( $field->is_simple_root_field(), $this->get_full_hierarchy_for_field( $field ) );
		$cascading_storage_array = $this->get_storage_array( $field, $storage_key_patterns );
		$value_tree_array = $this->cascading_storage_array_to_value_tree_array( $cascading_storage_array );
		if ( $value_tree_array === null ) {
			return $value_tree_array;
		}
		$value_tree = $this->value_tree_array_to_value_tree( $value_tree_array, $field );
		return $value_tree;
	}

	/**
	 * Save a single key-value pair to the database
	 *
	 * @param string $key
	 * @param string $value
	 */
	abstract protected function save_key_value_pair( $key, $value );

	/**
	 * Save the field value(s)
	 *
	 * @param Field $field The field to save.
	 */
	public function save( Field $field ) {
		$value_set = $field->get_full_value();

		if ( empty( $value_set ) || $field->get_value_set()->keepalive() ) {
			$storage_key = $this->key_toolset->get_storage_key(
				$field->is_simple_root_field(),
				$this->get_full_hierarchy_for_field( $field ),
				$this->get_full_hierarchy_index_for_field( $field ),
				0,
				$this->key_toolset->get_keepalive_property()
			);
			$this->save_key_value_pair( $storage_key, '' );
		}
		foreach ( $value_set as $value_group_index => $values ) {
			foreach ( $values as $property => $value ) {
				$storage_key = $this->key_toolset->get_storage_key(
					$field->is_simple_root_field(),
					$this->get_full_hierarchy_for_field( $field ),
					$this->get_full_hierarchy_index_for_field( $field ),
					$value_group_index,
					$property
				);
				$this->save_key_value_pair( $storage_key, $value );
			}
		}
	}
}
