<?php defined('SYSPATH') or die('No direct script access.');

class Model_Simplestat_Core extends Model {

	/*
	 * Gets current statistics if no date is set.
	 *
	 * @param   string  $main_table
	 * @param   string  $item_id
	 * @param   string  $name
	 * @return  mixed   statistics for the item or FALSE if nothing found
	 * @uses    DB::select
	 */
	public function current_stats($main_table, $item_id, $name)
	{
		// Search for statistics
		$stats = DB::select()
				->from($main_table)
				->where('item_id', '=', $item_id)
				->where('name', '=', $name)
				->limit(1)
				->as_object()
				->execute();

		if ($stats->count() === 0)
		{
			// No statistics exist for the item
			return FALSE;
		}

		// Get current record
		$stats = $stats->current();

		if (max($stats->created, $stats->updated) < strtotime('midnight'))
		{
			// If the last update for that item happened before today it means there were no visits this day
			$today = 0;
		}
		else
		{
			// If the last update happened today get the value of the daily counter
			$today = $stats->counter_daily;
		}

		// Return the results for today and the sum
		return array(
			'today' => $today,
			'sum'   => $stats->counter_sum,
		);
	}

	/*
	 * Gets historical statistics, falls back to current statistics if no history exists.
	 *
	 * Date format should be in UNIX timestamp and it can be set as an integer for
	 * a certain date or an array of integers for a period. For example:
	 *
	 *     array(11281823200, 11280786400)
	 *
	 * @param   string  $main_table
	 * @param   string  $history_table
	 * @param   string  $item_id
	 * @param   string  $name
	 * @param   mixed   $date
	 * @return  mixed   statistics for the item or FALSE if nothing found
	 * @uses    DB::select
	 * @uses    Arr::merge
	 */
	public function historical_stats($main_table, $history_table, $item_id, $name, $date)
	{
		if (is_array($date))
		{
			// Set the start and the end dates for historical query
			$start_date = strtotime(date('Y-m-d', $date[0]));
			$end_date   = strtotime(date('Y-m-d', $date[1]));

			if ($start_date > $end_date)
			{
				// End date is earlier than start date
				throw new Simplestats_Exception('End date [ :end ] is earlier than start date [ :start ].',
					array(':end' => date('Y-m-d', $end_date), ':start' => date('Y-m-d', $start_date)));
			}

			// Set current date
			$current_date = $start_date;

			// Get the timestamps for each day between start and end dates
			while($current_date < $end_date)
			{
				// Add this new day to the array
				$days_between[$current_date] = 0;

				// Add a day to the current date
				$current_date = strtotime('+1 day', $current_date);
			}
		}
		else
		{
			// If only one date is set get the statistics for only that day
			$start_date = strtotime(date('Y-m-d', $date));
			$end_date = $start_date;
		}

		// Run the historical query
		$stats = DB::select()
			->from($main_table)
			->join($history_table)
			->on($main_table . '.id', '=', $history_table . '.stat_id')
			->where($main_table . '.item_id', '=', $item_id)
			->where($main_table . '.name', '=', $name)
			->where($history_table . '.date', '>=', $start_date)
			->where($history_table . '.date', '<=', $end_date)
			->as_object()
			->execute();

		if ($stats->count() == 1 AND ! is_array($date))
		{
			// If one result is returned get the details
			$stats = $stats->current();

			if (max($stats->created, $stats->updated) < strtotime('midnight'))
			{
				// If the last update for that item happened before today it means there were no visits this day
				$today = 0;
			}
			else
			{
				// If the last update happened today get the value of the daily counter
				$today = $stats->counter_daily;
			}

			// Return the results for today, the sum and for the chosen date
			return array(
				'today' => $today,
				'sum'   => $stats->counter_sum,
				$date   => $stats->counter,
			);
		}
		elseif ($stats->count() >= 1 AND is_array($date))
		{
			// If multiple results are returned create a history array with all the available statistics
			$history = array();

			foreach ($stats as $stat)
			{
				$history[$stat->date] = $stat->counter;
				$history['today']     = $stat->counter_daily;
				$history['sum']       = $stat->counter_sum;
			}

			// Return the merged results
			return Arr::merge($days_between, $history);
		}
		else
		{
			// If no statistics exist for the item try falling back to current stats
			return $this->current_stats($main_table, $item_id, $name);
		}
	}

	/**
	 * Updates or creates statistics for an item.
	 *
	 * @param   string  $main_table
	 * @param   string  $history_table
	 * @param   string  $item_id
	 * @param   string  $name
	 * @return  mixed   updated daily and summarized statistics for the item
	 * @uses    DB::select
	 * @uses    DB::update
	 * @uses    DB::insert
	 */
	public function update($main_table, $history_table, $item_id, $name)
	{
		// Find statistics for the item
		$stats = DB::select('id', 'counter_daily', 'counter_sum', 'updated', 'created')
			->from($main_table)
			->where('item_id', '=', $item_id)
			->where('name', '=', $name)
			->limit(1)
			->as_object()
			->execute();

		if ($stats->count() > 0)
		{
			// If statistics exist for the item load the record
			$stats = $stats->current();

			// Set update
			$update = DB::update($main_table);

			// Set last update
			$last_update = max($stats->created, $stats->updated);

			// If historical stats are needed and the last update for the item happened before today we have to update the history
			if ($history_table !== FALSE AND $last_update < strtotime('midnight'))
			{
				// Reset daily counter to 1, update sum counter
				$update->set(array('counter_daily' => 1));
				$update->set(array('counter_sum' => $stats->counter_sum + 1));
				$update->set(array('updated' => time()));

				// Close statistics for the previous day
				DB::insert($history_table)
					->columns(array(
						'id',
						'stat_id',
						'counter',
						'date',
					))
					->values(array(
						NULL,
						$stats->id,
						$stats->counter_daily,
						strtotime(date('Y-m-d', $last_update)),
					))
					->execute();

				// Set today's statistics
				$today = 1;
			}
			else
			{
				// If the last update happened today or no historical stats are needed update statistics
				$update->set(array('counter_daily' => $stats->counter_daily + 1));
				$update->set(array('counter_sum' => $stats->counter_sum + 1));
				$update->set(array('updated' => time()));

				// Set today's statistics
				$today = $stats->counter_daily + 1;
			}

			// Update statistics
			$update->where('id', '=', $stats->id)
				   ->execute();

			// Return new stats
			return array(
				'today' => $today,
				'sum'   => $stats->counter_sum + 1,
			);
		}
		else
		{
			// If no statistics found create a new record
			$stats = DB::insert($main_table)
				->columns(array(
					'id',
					'item_id',
					'name',
					'counter_daily',
					'counter_sum',
					'created',
					'updated',
				))
				->values(array(
					NULL,
					$item_id,
					$name,
					1,
					1,
					time(),
					NULL,
				))
				->execute();

			// Return statistics
			return array(
				'today' => 1,
				'sum'   => 1,
			);
		}
	}
} // End Model_Simplestat_Core