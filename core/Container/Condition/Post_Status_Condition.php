<?php

namespace Carbon_Fields\Container\Condition;

/**
 * Check if post has specified status
 */
class Post_Status_Condition extends Condition
{

	/**
	 * Check if the condition is fulfilled
	 *
	 * @param  array $environment
	 * @return bool
	 */
	public function is_fulfilled($environment)
	{
		$post_status = $environment['post_status'];
		return $this->compare(
			$post_status,
			$this->get_comparison_operator(),
			$this->get_value()
		);
	}
}
