<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Simplestats: a simple statistics system for Kohana.
 *
 * (The part that is responsible for the configuration is mostly from the Pagination module of Kohana)
 *
 * @author     creatoro
 * @copyright  (c) 2010 creatoro
 * @license    http://creativecommons.org/licenses/by-sa/3.0/legalcode
 */
class Simplestats_Core {

	public $config = array(
		'unique' => 1800,
		'view' => 0,
		'main_table' => 'stats',
		'history_table' => 'stats_history',
	);

	/**
	 * Creates a new Simplestats object.
	 *
	 * @param   array  configuration
	 * @return  Simplestats
	 */
	public static function factory($config = array())
	{
		// If configuration is not an array try to load the configuration by name
		if ( ! is_array($config))
		{
			// If the configuration is not found set the config to an empty array
			if ( ! $config = Kohana::config('simplestats.' . $config))
				$config = array();
		}

		return new Simplestats($config);
	}

	/**
	 * Creates a new Simplestats object.
	 *
	 * @param   array  configuration
	 * @return  void
	 */
	public function __construct(array $config = array())
	{
		// Overwrite system defaults with application defaults
		$this->config = $this->config_group() + $this->config;

		// Pagination setup
		$this->setup($config);
	}

	/**
	 * Retrieves a simplestats config group from the config file. One config group can
	 * refer to another as its parent, which will be recursively loaded.
	 *
	 * @param   string  simplestats config group; "default" if none given
	 * @return  array   config settings
	 */
	public function config_group($group = 'default')
	{
		// Load the simplestats config file
		$config_file = Kohana::config('simplestats');

		// Initialize the $config array
		$config['group'] = (string) $group;

		// Recursively load requested config groups
		while (isset($config['group']) AND isset($config_file->$config['group']))
		{
			// Temporarily store config group name
			$group = $config['group'];
			unset($config['group']);

			// Add config group values, not overwriting existing keys
			$config += $config_file->$group;
		}

		// Get rid of possible stray config group names
		unset($config['group']);

		// Return the merged config group settings
		return $config;
	}

	/**
	 * Loads configuration settings into the object.
	 *
	 * @param   array   configuration
	 * @return  object  Simplestats
	 */
	public function setup(array $config = array())
	{
		if (isset($config['group']))
		{
			// Recursively load requested config groups
			$config += $this->config_group($config['group']);
		}

		// Overwrite the current config settings
		$this->config = $config + $this->config;

		// Chainable method
		return $this;
	}

	/**
	 * Gets statistics for a certain item. If no date is set or there are
	 * no statistics for the specified day / period, the daily and summarized
	 * statistics are returned.
	 *
	 * 		// Get 'view' statistics for item with id '1'
	 * 		Simplestats::factory()->get('1', 'view');
	 *
	 * 		// Get 'download' statistics for item with id '2' on 03-09-2010
	 * 		Simplestats::factory()->get('2', 'view', 11280786400);
	 *
	 *		// Get 'print' statistics for item with id '3' between 15-08-2010 and 03-09-2010
	 * 		Simplestats::factory()->get('3', 'view', array(11281823200, 11280786400));
	 *
	 *
	 * @param   string   id of the item
	 * @param   string   name of statistics
	 * @param   mixed   a certain date or start and end dates in array in UNIX timestamps
	 * @return  mixed  statistics for the item or FALSE if nothing found
	 */
	public function get($item_id, $name, $date = NULL)
	{
		// If no date set or historical stats are turned off or date is today's date get the current statistics
		if ($date === NULL OR $this->config['history_table'] === FALSE OR ( ! is_array($date) AND strtotime(date('Y-m-d', $date)) == strtotime('midnight')))
		{
			return Model::factory('Stat')->current_stats($this->config['main_table'], $item_id, $name);
		}
		else
		{
			// Return historical statistics if valid date is set
			return Model::factory('Stat')->historical_stats($this->config['main_table'], $this->config['history_table'], $item_id, $name, $date);
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
	 * @param   string   id of the item
	 * @param   string   name of statistics
	 * @param   string   type of the statistics set in configuration
	 * @return  mixed  updated daily and summarized statistics for the item
	 */
	public function update($item_id, $name, $type = 'unique')
	{
		// Check if the expiration time is set to higher than zero
		if ($this->config[$type] > 0)
		{
			// If expiration time is set check for cookie
			if (Cookie::get($name . '_' . $item_id))
			{
				// If cookie still exists, then the user is still the same, delete the cookie
				Cookie::delete($name . '_' . $item_id);

				// Create a new cookie to extend the current visit
				Cookie::set($name . '_' . $item_id, time(), $this->config[$type]);

				return Model::factory('Stat')->current_stats($this->config['main_table'], $item_id, $name);
			}
			else
			{
				// Create a new cookie for the visit
				Cookie::set($name . '_' . $item_id, time(), $this->config[$type]);
			}
		}

		// Update statistics
		return Model::factory('Stat')->update($this->config['main_table'], $this->config['history_table'], $item_id, $name);
	}

}