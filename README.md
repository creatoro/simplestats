Simplestats
===========

Very simple statistics for Kohana, made for the KohanaJobs project.
It can be used to track unique or per-view visitors and provides summarized and historical statistics.

Configuration
-------------

Your first step is to create the tables needed for storing the stats by using the `mysql.sql` file.

After this create a `simplestats.php` file in your `config` folder and set the configuration options.
Example configuration (default settings):

	'default' => array( // 'default' is the name of the config
			'unique' => 1800, // 1800 seconds is the cookie expiration time for a unique user
			'view' => 0, // 0 seconds is the expiration time for a per-view user
			'main_table' => 'stats', // the main table for stats
			'history_table' => 'stats_history', // the table for storing historical stats, set it to FALSE if not needed
	)

The `unique` and `view` settings are just names and they could be anything, for example `mytype`.
These are used to set the expiration time in seconds for the cookie. Any name that is not `main_table` or `history_table` can be used for types.

If `history_table` is set to `FALSE` no historical stats are going to be saved, therefore you don't need a history table.

By setting up multiple configurations you can save different stats to different tables which could be
very important in certain cases.


Create and update stats
-----------------------

When creating statistics there are 4 things you have to consider:

1. You can set which configuration to use when calling the `factory()` method using the name of the configuration (like in the first example) or an array. This is optional, if not set the config with `default` name is used (see configuration file).

2. A unique identifier for the item you are creating the stats for. This could be anything from an integer to a string (depends on your table).

3. The name of the statistics. Try to be descriptive, for example use `view` for stats that represent the number of views for a certain item.

4. Choose if you want to track unique visitors or per-view visitors by defining the type of the stats (the types are set in the config as shown above). This is an optional setting, the default type is unique visitor tracking.


Example 1: update or create `view` statistics for item with id `1`, no type set (default `unique` used), `myconfig` configuration is used
	Simplestats::factory('myconfig')->update('1', 'view');

Example 2: update or create `download` statistics for item with id `2`, `view` type used
	Simplestats::factory()->update('2', 'download', 'view');




Get stats
---------

Example 1: get `view` statistics for item with id `1`
	Simplestats::factory()->get('1', 'view');

Example 2: get `download` statistics for item with id `2` on `03-09-2010`
	Simplestats::factory()->get('2', 'view', 11280786400);

Example 3: get `print` statistics for item with id `3` between `15-08-2010` and `03-09-2010`
	Simplestats::factory()->get('3', 'view', array(11281823200, 11280786400));