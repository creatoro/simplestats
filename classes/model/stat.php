<?php defined('SYSPATH') or die('No direct script access.');

class Model_Stat extends Model {

	/**
	 * Gets current statistics if no date is set.
	 *
	 * @param   string   main table name
	 * @param   string   id of the item
	 * @param   string   name of statistics
	 * @return  mixed  statistics for the item or FALSE if nothing found
	 */
	public function current_stats($main_table, $item_id, $name)
	{
		$stats = DB::select()
				->from($main_table)
				->where('item_id', '=', $item_id)
				->where('name', '=', $name)
				->limit(1)
				->as_object()
				->execute();

			// If statistics exist for that item get details
			if ($stats->count() > 0)
			{
				$stats = $stats->current();

				// Check for last update
				if ( ! $stats->updated)
				{
					$last_updated = $stats->created;
				}
				else
				{
					$last_updated = $stats->updated;
				}

				// If the last update for that item happened before today it means there were no visits this day
				if ($last_updated < strtotime('midnight'))
				{
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
					'sum' => $stats->counter_sum,
				);
			}
			else
			{
				// If no statistics exist for the item return FALSE
				return FALSE;
			}
	}

	/**
	 * Gets historical statistics, falls back to current statistics if no history exists.
	 *
	 * @param   string   main table name
	 * @param   string   history table name
	 * @param   string   id of the item
	 * @param   string   name of statistics
	 * @param   mixed   a certain date or start and end dates in array in UNIX timestamps
	 * @return  mixed  statistics for the item or FALSE if nothing found
	 */
	public function historical_stats($main_table, $history_table, $item_id, $name, $date)
	{
		// If date is set perform a historical query
		if (is_array($date))
		{
			// Set the start and the end dates
			$start_date = strtotime(date('Y-m-d', $date[0]));
			$end_date = strtotime(date('Y-m-d', $date[1]));

			// Return FALSE if ending date is eariler than start date
			if ($start_date > $end_date)
			{
				return array('error' => 'Start date must be eariler than end date');
			}

			// Get the timestamps for each day between start and end dates
			$current_date = $start_date;

			while($current_date < $end_date)
			{
				// Add this new day to the array
				$days_between[$current_date] = 0;

				// Add a day to the current date
				$current_date = strtotime("+1 day", $current_date);
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

		// If one result is returned get the details
		if ($stats->count() == 1 AND ! is_array($date))
		{
			$stats = $stats->current();

			// Check for last update
			if ( ! $stats->updated)
			{
				$last_updated = $stats->created;
			}
			else
			{
				$last_updated = $stats->updated;
			}

			// If the last update for that item happened before today it means there were no visits this day
			if ($last_updated < strtotime('midnight'))
			{
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
				'sum' => $stats->counter_sum,
				$date => $stats->counter,
			);
		}
		elseif ($stats->count() > 1 OR is_array($date))
		{
			// If multiple results are returned create a history array with all the available statistics
			foreach ($stats as $stat)
			{
				$history[$stat->date] = $stat->counter;
				$history['today'] = $stat->counter_daily;
				$history['sum'] = $stat->counter_sum;
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
	 * @param   string   main table name
	 * @param   string   history table name
	 * @param   string   id of the item
	 * @param   string   name of statistics
	 * @return  mixed  updated daily and summarized statistics for the item
	 */
	public function update($main_table, $history_table, $item_id, $name)
	{
		// Find statistics for the item
		$stats = DB::select('id','counter_daily', 'counter_sum', 'updated', 'created')
			->from($main_table)
			->where('item_id', '=', $item_id)
			->where('name', '=', $name)
			->limit(1)
			->as_object()
			->execute();

		// Check if statistics exist for the item
		if ($stats->count() > 0)
		{
			// Load the record
			$stats = $stats->current();

			// Check for last update
			if ( ! $stats->updated)
			{
				$last_updated = $stats->created;
			}
			else
			{
				$last_updated = $stats->updated;
			}

			// Set update
			$update = DB::update($main_table);

			// If the last update for the item happened before today we have to update the xhistory
			if ($last_updated < strtotime('midnight'))
			{
				// Reset daily counter to 1, update sum counter
				$update->set(array('counter_daily' => 1));
				$update->set(array('counter_sum' => $stats->counter_sum + 1));
				$update->set(array('updated' => time()));

				// Close statistics for the previous day
				DB::insert($history_table)
					->values(array(
						'id' => NULL,
						'stat_id' => $stats->id,
						'counter' => $stats->counter_daily,
						'date' => strtotime(date('Y-m-d', $last_updated)),
					))
					->execute();

				// Set today's statistics
				$today = 1;
			}
			else
			{
				// If the last update happened today update statistics
				$update->set(array('counter_daily' => $stats->counter_daily + 1));
				$update->set(array('counter_sum' => $stats->counter_sum + 1));
				$update->set(array('updated' => time()));

				// Set today's statistics
				$today = $stats->counter_daily + 1;
			}

			// Update statistics
			$update->where('item_id', '=', $item_id)
				->where('name', '=', $name)
				->execute();

			// Return new stats
			return array(
				'today' => $today,
				'sum' => $stats->counter_sum + 1,
			);
		}
		else
		{
			// If no statistics found create a new record
			$stats = DB::insert($main_table)
				->values(array(
					'id' => NULL,
					'item_id' => $item_id,
					'name' => $name,
				    'counter_daily' => 1,
					'counter_sum' => 1,
					'created' => time(),
					'updated' => NULL,
				))
				->execute();

			// Return statistics
			return array(
				'today' => 1,
				'sum' => 1,
			);
		}
	}
}