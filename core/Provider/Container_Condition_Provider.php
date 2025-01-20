<?php

namespace Carbon_Fields\Provider;

use Carbon_Fields\Pimple\Container as PimpleContainer;
use Carbon_Fields\Pimple\ServiceProviderInterface;
use Carbon_Fields\Container\Condition\Factory as ConditionFactory;
use Carbon_Fields\Container\Fulfillable\Fulfillable_Collection;

class Container_Condition_Provider implements ServiceProviderInterface
{

	/**
	 * Install dependencies in IoC container
	 *
	 * @param  PimpleContainer $ioc
	 */
	public function register(PimpleContainer $ioc)
	{
		$this->install_conditions($ioc);
		$this->install_comparers($ioc);
		$this->install_translators($ioc);
		$this->install_container_conditions($ioc);
	}

	/**
	 * Install all codition types and the condition factory
	 *
	 * @param  PimpleContainer $ioc
	 */
	protected function install_conditions($ioc)
	{
		$ioc['container_condition_factory'] = function ($ioc) {
			return new ConditionFactory($ioc['container_conditions']);
		};

		$ioc['container_condition_fulfillable_collection'] = $ioc->factory(function ($ioc) {
			return new Fulfillable_Collection($ioc['container_condition_factory'], $ioc['container_condition_translator_array']);
		});

		$ioc['container_conditions'] = function () {
			return new PimpleContainer();
		};

		$cc_ioc = $ioc['container_conditions'];

		$cc_ioc['boolean'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\Boolean_Condition();
			$condition->set_comparers(array(
				$ioc['container_condition_comparers']['equality'],
			));
			return $condition;
		});

		$cc_ioc['post_id'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\Post_ID_Condition();
			$condition->set_comparers($ioc['container_condition_comparer_collections']['generic']);
			return $condition;
		});
		$cc_ioc['post_status'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\Post_Status_Condition();
			$condition->set_comparers($ioc['container_condition_comparer_collections']['generic']);
			return $condition;
		});
		$cc_ioc['post_parent_id'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\Post_Parent_ID_Condition();
			$condition->set_comparers($ioc['container_condition_comparer_collections']['generic']);
			return $condition;
		});
		$cc_ioc['post_ancestor_id'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\Post_Ancestor_ID_Condition();
			$condition->set_comparers($ioc['container_condition_comparer_collections']['array']);
			return $condition;
		});
		$cc_ioc['post_type'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\Post_Type_Condition();
			$condition->set_comparers($ioc['container_condition_comparer_collections']['nonscalar']);
			return $condition;
		});
		$cc_ioc['post_format'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\Post_Format_Condition();
			$condition->set_comparers($ioc['container_condition_comparer_collections']['nonscalar']);
			return $condition;
		});
		$cc_ioc['post_level'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\Post_Level_Condition();
			$condition->set_comparers($ioc['container_condition_comparer_collections']['generic']);
			return $condition;
		});
		$cc_ioc['post_template'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\Post_Template_Condition();
			$condition->set_comparers($ioc['container_condition_comparer_collections']['nonscalar']);
			return $condition;
		});
		$cc_ioc['post_term'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\Post_Term_Condition($ioc['wp_toolset']);
			$condition->set_comparers(array(
				// Only support the custom comparer as this condition has its own comparison methods
				$ioc['container_condition_comparers']['custom'],
			));
			return $condition;
		});

		$cc_ioc['term'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\Term_Condition($ioc['wp_toolset']);
			$condition->set_comparers(array(
				// Only support the custom comparer as this condition has its own comparison methods
				$ioc['container_condition_comparers']['custom'],
			));
			return $condition;
		});
		$cc_ioc['term_taxonomy'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\Term_Taxonomy_Condition();
			$condition->set_comparers($ioc['container_condition_comparer_collections']['nonscalar']);
			return $condition;
		});
		$cc_ioc['term_level'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\Term_Level_Condition();
			$condition->set_comparers($ioc['container_condition_comparer_collections']['generic']);
			return $condition;
		});
		$cc_ioc['term_parent'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\Term_Parent_Condition($ioc['wp_toolset']);
			$condition->set_comparers($ioc['container_condition_comparer_collections']['generic']);
			return $condition;
		});
		$cc_ioc['term_ancestor'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\Term_Ancestor_Condition($ioc['wp_toolset']);
			$condition->set_comparers($ioc['container_condition_comparer_collections']['array']);
			return $condition;
		});

		$cc_ioc['user_id'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\User_ID_Condition();
			$condition->set_comparers($ioc['container_condition_comparer_collections']['generic']);
			return $condition;
		});
		$cc_ioc['user_role'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\User_Role_Condition();
			$condition->set_comparers($ioc['container_condition_comparer_collections']['array']);
			return $condition;
		});
		$cc_ioc['user_capability'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\User_Capability_Condition();
			$condition->set_comparers(array(
				// Only support the custom comparer as this condition has its own comparison methods
				$ioc['container_condition_comparers']['custom'],
			));
			return $condition;
		});

		$cc_ioc['blog_id'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\Blog_ID_Condition();
			$condition->set_comparers($ioc['container_condition_comparer_collections']['generic']);
			return $condition;
		});

		$cc_ioc['current_user_id'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\Current_User_ID_Condition();
			$condition->set_comparers($ioc['container_condition_comparer_collections']['generic']);
			return $condition;
		});
		$cc_ioc['current_user_role'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\Current_User_Role_Condition();
			$condition->set_comparers($ioc['container_condition_comparer_collections']['array']);
			return $condition;
		});
		$cc_ioc['current_user_capability'] = $cc_ioc->factory(function () use ($ioc) {
			$condition = new \Carbon_Fields\Container\Condition\Current_User_Capability_Condition();
			$condition->set_comparers(array(
				// Only support the custom comparer as this condition has its own comparison methods
				$ioc['container_condition_comparers']['custom'],
			));
			return $condition;
		});
	}

	/**
	 * Install all condition comparers
	 *
	 * @param  PimpleContainer $ioc
	 */
	protected function install_comparers($ioc)
	{
		$ioc['container_condition_comparers'] = function () {
			return new PimpleContainer();
		};

		$ioc['container_condition_comparers']['equality'] = function () {
			return new \Carbon_Fields\Container\Condition\Comparer\Equality_Comparer();
		};

		$ioc['container_condition_comparers']['any_equality'] = function () {
			return new \Carbon_Fields\Container\Condition\Comparer\Any_Equality_Comparer();
		};

		$ioc['container_condition_comparers']['contain'] = function () {
			return new \Carbon_Fields\Container\Condition\Comparer\Contain_Comparer();
		};

		$ioc['container_condition_comparers']['any_contain'] = function () {
			return new \Carbon_Fields\Container\Condition\Comparer\Any_Contain_Comparer();
		};

		$ioc['container_condition_comparers']['scalar'] = function () {
			return new \Carbon_Fields\Container\Condition\Comparer\Scalar_Comparer();
		};

		$ioc['container_condition_comparers']['custom'] = function () {
			return new \Carbon_Fields\Container\Condition\Comparer\Custom_Comparer();
		};

		$ioc['container_condition_comparer_collections'] = function () {
			return new PimpleContainer();
		};

		$ioc['container_condition_comparer_collections']['generic'] = function () use ($ioc) {
			return array(
				$ioc['container_condition_comparers']['equality'],
				$ioc['container_condition_comparers']['contain'],
				$ioc['container_condition_comparers']['scalar'],
				$ioc['container_condition_comparers']['custom'],
			);
		};
		$ioc['container_condition_comparer_collections']['nonscalar'] = function () use ($ioc) {
			return array(
				$ioc['container_condition_comparers']['equality'],
				$ioc['container_condition_comparers']['contain'],
				$ioc['container_condition_comparers']['custom'],
			);
		};
		$ioc['container_condition_comparer_collections']['array'] = function () use ($ioc) {
			return array(
				$ioc['container_condition_comparers']['any_equality'],
				$ioc['container_condition_comparers']['any_contain'],
				$ioc['container_condition_comparers']['custom'],
			);
		};
	}

	/**
	 * Install all codition translators
	 *
	 * @param  PimpleContainer $ioc
	 */
	protected static function install_translators($ioc)
	{
		$ioc['container_condition_translator_array'] = function ($ioc) {
			return new \Carbon_Fields\Container\Fulfillable\Translator\Array_Translator($ioc['container_condition_factory']);
		};

		$ioc['container_condition_translator_json'] = function ($ioc) {
			return new \Carbon_Fields\Container\Fulfillable\Translator\Json_Translator($ioc['container_condition_factory']);
		};
	}

	/**
	 * Install all container coditions
	 *
	 * @param  PimpleContainer $ioc
	 */
	protected function install_container_conditions($ioc)
	{
		// add current_user_* static condition types to all containers
		add_filter('carbon_fields_container_static_condition_types', function ($condition_types, $container_type, $container) {
			return array_merge(
				$condition_types,
				array('current_user_id', 'current_user_role', 'current_user_capability')
			);
		}, 10, 3);

		// add container-specific conditions
		add_filter('carbon_fields_post_meta_container_static_condition_types', array($this, 'filter_post_meta_container_static_condition_types'), 10, 3);
		add_filter('carbon_fields_post_meta_container_dynamic_condition_types', array($this, 'filter_post_meta_container_dynamic_condition_types'), 10, 3);

		add_filter('carbon_fields_term_meta_container_static_condition_types', array($this, 'filter_term_meta_container_static_condition_types'), 10, 3);
		add_filter('carbon_fields_term_meta_container_dynamic_condition_types', array($this, 'filter_term_meta_container_dynamic_condition_types'), 10, 3);

		add_filter('carbon_fields_user_meta_container_static_condition_types', array($this, 'filter_user_meta_container_static_condition_types'), 10, 3);
		add_filter('carbon_fields_user_meta_container_dynamic_condition_types', array($this, 'filter_user_meta_container_dynamic_condition_types'), 10, 3);

		add_filter('carbon_fields_theme_options_container_static_condition_types', array($this, 'filter_theme_options_container_static_condition_types'), 10, 3);
	}

	/**
	 * Filter the Post_Meta_Container static condition types
	 *
	 * @param  array<string>                     $condition_types
	 * @param  \Carbon_Fields\Container\Container $container
	 * @return array<string>
	 */
	public function filter_post_meta_container_static_condition_types($condition_types, $container_type, $container)
	{
		return array_merge(
			$condition_types,
			array('post_id', 'post_status', 'post_type')
		);
	}

	/**
	 * Filter the Post_Meta_Container dynamic condition types
	 *
	 * @param  array<string>                      $condition_types
	 * @param  string                             $container_type
	 * @param  \Carbon_Fields\Container\Container $container
	 * @return array<string>
	 */
	public function filter_post_meta_container_dynamic_condition_types($condition_types, $container_type, $container)
	{
		return array_merge(
			$condition_types,
			array('post_parent_id', 'post_ancestor_id', 'post_format', 'post_level', 'post_template', 'post_term')
		);
	}

	/**
	 * Filter the Term_Meta_Container static condition types
	 *
	 * @param  array<string>                      $condition_types
	 * @param  string                             $container_type
	 * @param  \Carbon_Fields\Container\Container $container
	 * @return array<string>
	 */
	public function filter_term_meta_container_static_condition_types($condition_types, $container_type, $container)
	{
		return array_merge(
			$condition_types,
			array('term', 'term_taxonomy')
		);
	}

	/**
	 * Filter the Term_Meta_Container dynamic condition types
	 *
	 * @param  array<string>                      $condition_types
	 * @param  string                             $container_type
	 * @param  \Carbon_Fields\Container\Container $container
	 * @return array<string>
	 */
	public function filter_term_meta_container_dynamic_condition_types($condition_types, $container_type, $container)
	{
		return array_merge(
			$condition_types,
			array('term_level', 'term_parent', 'term_ancestor')
		);
	}

	/**
	 * Filter the User_Meta_Container static condition types
	 *
	 * @param  array<string>                      $condition_types
	 * @param  string                             $container_type
	 * @param  \Carbon_Fields\Container\Container $container
	 * @return array<string>
	 */
	public function filter_user_meta_container_static_condition_types($condition_types, $container_type, $container)
	{
		return array_merge(
			$condition_types,
			array('user_id', 'user_capability')
		);
	}

	/**
	 * Filter the User_Meta_Container dynamic condition types
	 *
	 * @param  array<string>                      $condition_types
	 * @param  string                             $container_type
	 * @param  \Carbon_Fields\Container\Container $container
	 * @return array<string>
	 */
	public function filter_user_meta_container_dynamic_condition_types($condition_types, $container_type, $container)
	{
		return array_merge(
			$condition_types,
			array('user_role')
		);
	}

	/**
	 * Filter the Theme_Options_Container static condition types
	 *
	 * @param  array<string>                      $condition_types
	 * @param  string                             $container_type
	 * @param  \Carbon_Fields\Container\Container $container
	 * @return array<string>
	 */
	public function filter_theme_options_container_static_condition_types($condition_types, $container_type, $container)
	{
		return array_merge(
			$condition_types,
			array('blog_id')
		);
	}
}
