<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Simplestats: a simple statistics system for Kohana.
 *
 * (The part that is responsible for the configuration is mostly from the Pagination module of Kohana)
 *
 * @author     creatoro
 * @copyright  (c) 2011 creatoro
 * @license    http://creativecommons.org/licenses/by-sa/3.0/legalcode
 */
class Simplestats_Core {

	/**
	 * Creates a new Simplestats object.
	 *
	 * @param   mixed  $config
	 * @return  Simplestats
	 * @uses    Kohana::$config
	 */
	public static function factory($config = 'default')
	{
		if (is_string($config) AND ($config = Kohana::$config->load('simplestats.'.$config)) === NULL)
		{
			// No such configuration group exist
			throw new Simplestats_Exception('Configuration group does not exist.');
		}

		return new Simplestats($config);
	}

	/**
	 * Sets the configuration and checks for required settings.
	 *
	 * @param   array  $config
	 * @return  void
	 */
	public function __construct(array $config = array())
	{
		if ( ! isset($config['main_table']) OR empty($config['main_table']))
		{
			// Main table has to be set
			throw new Simplestats_Exception("The 'main_table' is not set in configuration.");
		}

		// Set config
		$this->config = $config;
	}

	/**
	 * Gets statistics for a certain item. If no date is set or there are
	 * no statistics for the specified day / period, the daily and summarized
	 * statistics are returned.
	 *
	 * The requested day / period should be given in UNIX timestamp.
	 *
	 * 		// Get 'view' statistics for item with id '1'
	 * 		Simplestats::factory()->get('1', 'view');
	 *
	 * 		// Get 'download' statistics for item with id '2' on 03-09-2010
	 * 		Simplestats::factory()->get('2', 'download', 11280786400);
	 *
	 *		// Get 'print' statistics for item with id '3' between 15-08-2010 and 03-09-2010
	 * 		Simplestats::factory()->get('3', 'print', array(11281823200, 11280786400));
	 *
	 *
	 * @param   string  $item_id
	 * @param   string  $name
	 * @param   mixed   $date
	 * @return  mixed   statistics for the item or FALSE if nothing found
	 * @uses    Model::factory
	 */
	public function get($item_id, $name, $date = NULL)
	{
		if ($date === NULL OR Arr::get($this->config, 'history_table', FALSE) === FALSE OR ( ! is_array($date) AND strtotime(date('Y-m-d', $date)) >= strtotime('midnight')))
		{
			// If no date set or historical stats are turned off or date is today's date (or earlier) get the current statistics
			return Model::factory('Simplestat')->current_stats($this->config['main_table'], $item_id, $name);
		}
		else
		{
			// Return historical statistics
			return Model::factory('Simplestat')->historical_stats($this->config['main_table'], $this->config['history_table'], $item_id, $name, $date);
		}
	}

	/**
	 * Updates or creates statistics for an item. Checks for cookie if expiration time is set based
	 * on statistics type.
	 *
	 * 		// Update or create 'view' statistics for item with id '1', no type set (default 'unique' used)
	 * 		Simplestats::factory()->update('1', 'view');
	 *
	 * 		// Update or create 'download' statistics for item with id '2', 'view' type used
	 * 		Simplestats::factory()->update('2', 'download', 'view');
	 *
	 *
	 * @param   string  $item_id
	 * @param   string  $name
	 * @param   string  $type
	 * @return  mixed   updated daily and summarized statistics for the item
	 * @uses    Cookie::get
	 * @uses    Cookie::delete
	 * @uses    Cookie::set
	 * @uses    Model::factory
	 */
	public function update($item_id, $name, $type = 'unique')
	{
		// Check if the expiration time is set to higher than zero
		if (Arr::get($this->config, $type, 0) > 0)
		{
			// If expiration time is set check for cookie
			if (Cookie::get($name.'_'.$item_id))
			{
				// If cookie still exists, then the user is still the same, delete the cookie
				Cookie::delete($name.'_'.$item_id);

				// Create a new cookie to extend the current visit
				Cookie::set($name.'_'.$item_id, time(), $this->config[$type]);

				// Return statistics
				return Model::factory('Simplestat')->current_stats($this->config['main_table'], $item_id, $name);
			}
			else
			{
				// New visitor, create cookie
				Cookie::set($name.'_'.$item_id, time(), $this->config[$type]);
			}
		}

		// Update statistics
		return Model::factory('Simplestat')->update($this->config['main_table'], Arr::get($this->config, 'history_table', FALSE), $item_id, $name);
	}

} // End Simplestats_Core