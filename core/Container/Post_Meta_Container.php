<?php

namespace Carbon_Fields\Container;

use Carbon_Fields\Helper\Helper;
use Carbon_Fields\Datastore\Datastore;
use Carbon_Fields\Exception\Incorrect_Syntax_Exception;

/**
 * Field container designed to extend WordPress custom fields functionality,
 * providing easier user interface to add, edit and delete text, media files,
 * location information and more.
 */
class Post_Meta_Container extends Container
{
	/**
	 * ID of the post the container is working with
	 *
	 * @see init()
	 * @var int
	 */
	protected $post_id;

	/**
	 * Status of the post the container is working with
	 *
	 */
	protected $post_status;

	/**
	 * Post Types
	 *
	 */
	protected $post_types;

	/**
	 * Determines whether revisions are disabled for this container
	 *
	 * @var bool
	 */
	protected $revisions_disabled = false;

	/**
	 * List of default container settings
	 *
	 * @see init()
	 * @var array
	 */
	public $settings = array(
		'meta_box_context' => 'normal',
		'meta_box_priority' => 'high',
	);

	/**
	 * {@inheritDoc}
	 */
	public function __construct($id, $title, $type, $condition_collection, $condition_translator)
	{
		parent::__construct($id, $title, $type, $condition_collection, $condition_translator);

		if (! $this->get_datastore()) {
			$this->set_datastore(Datastore::make('post_meta'), $this->has_default_datastore());
		}
	}

	/**
	 * Create DataStore instance, set post ID to operate with (if such exists).
	 * Bind attach() and save() to the appropriate WordPress actions.
	 */
	public function init()
	{
		$input = stripslashes_deep($_GET);
		$request_post_id = isset($input['post']) ? intval($input['post']) : 0;
		if ($request_post_id > 0) {
			$this->set_post_id($request_post_id);
		}

		add_action('admin_init', array($this, '_attach'));
		add_action('save_post', array($this, '_save'));

		// support for attachments
		add_action('add_attachment', array($this, '_save'));
		add_action('edit_attachment', array($this, '_save'));
	}

	/**
	 * Checks whether the current save request is valid
	 * Possible errors are triggering save() for autosave requests
	 * or performing post save outside of the post edit page (like Quick Edit)
	 *
	 * @return bool
	 */
	public function is_valid_save()
	{
		$params = func_get_args();
		$post_id = $params[0];
		$post_type = get_post_type($post_id);

		$wp_preview = (! empty($_POST['wp-preview'])) ? $_POST['wp-preview'] : '';
		$in_preview = $wp_preview === 'dopreview';
		$doing_autosave = defined('DOING_AUTOSAVE') && DOING_AUTOSAVE;
		$is_revision = $post_type === 'revision';

		if (($doing_autosave || $is_revision) && (! $in_preview || $this->revisions_disabled)) {
			return false;
		}

		if (! $this->verified_nonce_in_request()) {
			return false;
		}

		return $this->is_valid_attach_for_object($post_id);
	}

	/**
	 * Perform save operation after successful is_valid_save() check.
	 * The call is propagated to all fields in the container.
	 *
	 * @param int $post_id ID of the post against which save() is ran
	 */
	public function save($post_id = null)
	{
		// Unhook action to garantee single save
		remove_action('save_post', array($this, '_save'));

		$this->set_post_id($post_id);

		foreach ($this->fields as $field) {
			$field->set_value_from_input(Helper::input());
			$field->save();
		}

		do_action('carbon_fields_post_meta_container_saved', $post_id, $this);
	}

	/**
	 * Get environment array for page request (in admin)
	 *
	 * @return array
	 */
	protected function get_environment_for_request()
	{
		global $pagenow;

		$input = stripslashes_deep($_GET);
		$request_post_type = isset($input['post_type']) ? $input['post_type'] : '';
		$post_type = '';

		if ($this->post_id) {
			$post_type = get_post_type($this->post_id);
		} elseif (! empty($request_post_type)) {
			$post_type = $request_post_type;
		} elseif ($pagenow === 'post-new.php') {
			$post_type = 'post';
		}

		$post = get_post($this->post_id);
		$post = $post ? $post : null;
		$environment = array(
			'post_id' => $post ? $post->ID : 0,
			'post_status' => $post ? $post->post_status : 'draft',
			'post_type' => $post ? $post->post_type : $post_type,
			'post' => $post,
		);
		return $environment;
	}

	/**
	 * Perform checks whether the container should be attached during the current request
	 *
	 * @return bool True if the container is allowed to be attached
	 */
	public function is_valid_attach_for_request()
	{
		global $pagenow;

		if ($pagenow !== 'post.php' && $pagenow !== 'post-new.php') {
			return false;
		}

		$environment = $this->get_environment_for_request();
		if (! $environment['post_type']) {
			return false;
		}

		return $this->static_conditions_pass();
	}

	/**
	 * Get environment array for object id
	 *
	 * @return array
	 */
	protected function get_environment_for_object($object_id)
	{
		$post = get_post(intval($object_id));
		$post_type = $post->post_type;

		if (wp_is_post_revision($post) !== false) {
			$post = get_post(intval($post->post_parent));
			$post_type = $post->post_type;
		}

		$environment = array(
			'post_id' => $post->ID,
			'post_status' => $post->post_status,
			'post' => $post,
			'post_type' => $post_type,
		);
		return $environment;
	}

	/**
	 * Check container attachment rules against object id
	 *
	 * @param int $object_id
	 * @return bool
	 */
	public function is_valid_attach_for_object($object_id = null)
	{
		$post = get_post(intval($object_id));

		if (! $post) {
			return false;
		}

		return $this->all_conditions_pass(intval($post->ID));
	}

	/**
	 * Add meta box for each of the container post types
	 */
	public function attach()
	{
		$this->post_types = $this->get_post_type_visibility();

		foreach ($this->post_types as $post_type) {
			add_meta_box(
				$this->get_id(),
				$this->title,
				array($this, 'render'),
				$post_type,
				$this->settings['meta_box_context'],
				$this->settings['meta_box_priority']
			);

			$container_id = $this->get_id();
			add_filter("postbox_classes_{$post_type}_{$container_id}", array($this, 'add_postbox_classes'));
		}
	}

	/**
	 * Classes to add to the post meta box
	 */
	public function add_postbox_classes($classes)
	{
		$classes[] = 'carbon-box';
		return $classes;
	}

	/**
	 * Output the container markup
	 */
	public function render()
	{
		include \Carbon_Fields\DIR . '/templates/Container/post_meta.php';
	}

	/**
	 * Set the post ID the container will operate with.
	 *
	 * @param int $post_id
	 */
	public function set_post_id($post_id)
	{
		$this->post_id = $post_id;
		$this->get_datastore()->set_object_id($post_id);

		foreach ($this->fields as $field) {
			$datastore = $field->get_datastore();
			if ($datastore->get_object_id() === 0) {
				$datastore->set_object_id($post_id);
			}
		}
	}

	/**
	 * Get array of post types this container can appear on conditionally
	 *
	 * @return array<string>
	 */
	public function get_post_type_visibility()
	{
		$all_post_types = get_post_types();
		$evaluated_collection = $this->condition_collection->evaluate(array('post_type'), true, array(), true);

		$shown_on = array();
		foreach ($all_post_types as $post_type) {
			$environment = array(
				'post_type' => $post_type,
			);
			if ($evaluated_collection->is_fulfilled($environment)) {
				$shown_on[] = $post_type;
			}
		}
		return $shown_on;
	}

	/**
	 * COMMON USAGE METHODS
	 */

	/**
	 * Show the container only on particular page referenced by its path.
	 *
	 * @deprecated
	 * @param int|string $page page ID or page path
	 * @return object $this
	 */
	public function show_on_page($page)
	{
		$page_id = absint($page);

		if ($page_id && $page_id == $page) {
			$page_obj = get_post($page_id);
		} else {
			$page_obj = get_page_by_path($page);
		}
		$page_id = ($page_obj) ? $page_obj->ID : -1;

		$this->where('post_id', '=', $page_id);

		return $this;
	}

	/**
	 * Show the container only on pages whose parent is referenced by $parent_page_path.
	 *
	 * @deprecated
	 * @param string $parent_page_path
	 * @return object $this
	 */
	public function show_on_page_children($parent_page_path)
	{
		$page = get_page_by_path($parent_page_path);
		$page_id = ($page) ? $page->ID : -1;
		$this->where('post_parent_id', '=', $page_id);
		return $this;
	}

	/**
	 * Show the container only on pages whose template has filename $template_path.
	 *
	 * @deprecated
	 * @param string|array $template_path
	 * @return object $this
	 */
	public function show_on_template($template_path)
	{
		// Backwards compatibility where only pages support templates
		if (version_compare(get_bloginfo('version'), '4.7', '<')) {
			$this->show_on_post_type('page');
		}

		$template_paths = is_array($template_path) ? $template_path : array($template_path);
		$this->where('post_template', 'IN', $template_paths);
		return $this;
	}

	/**
	 * Hide the container from pages whose template has filename $template_path.
	 *
	 * @deprecated
	 * @param string|array $template_path
	 * @return object $this
	 */
	public function hide_on_template($template_path)
	{
		$template_paths = is_array($template_path) ? $template_path : array($template_path);
		$this->where('post_template', 'NOT IN', $template_paths);
		return $this;
	}

	/**
	 * Show the container only on hierarchical posts of level $level.
	 * Levels start from 1 (top level post)
	 *
	 * @deprecated
	 * @param int $level
	 * @return object $this
	 */
	public function show_on_level($level)
	{
		$this->where('post_level', '=', intval($level));
		return $this;
	}

	/**
	 * Show the container only on posts from the specified format.
	 * Learn more about {@link http://codex.wordpress.org/Post_Formats Post Formats (Codex)}
	 *
	 * @deprecated
	 * @param string|array $post_format Name of the format as listed on Codex
	 * @return object $this
	 */
	public function show_on_post_format($post_format)
	{
		$post_formats = is_array($post_format) ? $post_format : array($post_format);
		$this->where('post_format', 'IN', $post_formats);
		return $this;
	}

	/**
	 * Show the container only on posts from the specified type(s).
	 *
	 * @deprecated
	 * @param string|array $post_types
	 * @return object $this
	 */
	public function show_on_post_type($post_types)
	{
		$post_types = is_array($post_types) ? $post_types : array($post_types);
		$this->where('post_type', 'IN', $post_types);
		return $this;
	}

	/**
	 * Show the container only on posts from the specified category.
	 *
	 * @see show_on_taxonomy_term()
	 *
	 * @deprecated
	 * @param string $category_slug
	 * @return object $this
	 */
	public function show_on_category($category_slug)
	{
		$this->where('post_term', '=', array(
			'value' => $category_slug,
			'field' => 'slug',
			'taxonomy' => 'category',
		));
		return $this;
	}

	/**
	 * Show the container only on posts which have term $term_slug from the $taxonomy_slug taxonomy.
	 *
	 * @deprecated
	 * @param string $taxonomy_slug
	 * @param string $term_slug
	 * @return object $this
	 */
	public function show_on_taxonomy_term($term_slug, $taxonomy_slug)
	{
		$this->where('post_term', '=', array(
			'value' => $term_slug,
			'field' => 'slug',
			'taxonomy' => $taxonomy_slug,
		));
		return $this;
	}

	/**
	 * Sets the meta box container context
	 *
	 * @see https://codex.wordpress.org/Function_Reference/add_meta_box
	 * @param string $context ('normal', 'advanced', 'side' or the custom `carbon_fields_after_title`)
	 */
	public function set_context($context)
	{
		$this->settings['meta_box_context'] = $context;
		return $this;
	}

	/**
	 * Sets the meta box container priority
	 *
	 * @see https://codex.wordpress.org/Function_Reference/add_meta_box
	 * @param string $priority ('high', 'core', 'default' or 'low')
	 */
	public function set_priority($priority)
	{
		$this->settings['meta_box_priority'] = $priority;
		return $this;
	}

	public function set_revisions_disabled($revisions_disabled)
	{
		$this->revisions_disabled = $revisions_disabled;
		return $this;
	}

	public function get_revisions_disabled()
	{
		return $this->revisions_disabled;
	}
}
