<?php
/**
 *
 * Date Functions
 *
 * @package Alpha Insights
 * @version 2.2.0
 * @since 2.2.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * 
 * 	Checks whether a date is in the DateString format we are expecting
 *  
 * 	@param string Date to check
 * 	@param string Fromat to validate
 * 
 * 	@return bool True on correct, false on failure
 *
 **/
function wpdai_validate_date_format( $date, $format = 'Y-m-d' ){

    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;

}

/**
 * 
 * 	Calculates the days between two dates inclusive of the beginning day and end day
 * 	If the to date is omitted, we will use today instead
 * 
 * 	@param string $from_date The start date, in 'Y-m-d' format
 * 	@param string $to_date The end date, in 'Y-m-d' format
 * 
 * 	@return bool|int Will return false if there's an issue, otherwise return days of difference
 * 
 **/
function wpdai_calculate_days_between_dates( $from_date, $to_date = false ) {

	// Setup default day to today
	if ( $to_date === false ) {
		$to_date = current_time( 'Y-m-d' );
	}

	// Wrong formats, stop
	if ( ! is_string($from_date) || ! is_string($to_date) ) {
		return false;
	}

	// Create date objects
	$start 	= new DateTime( $from_date );
	$end 	= new DateTime( $to_date );

	// Only execute this if we're sure these are now objects
	if ( is_a($start, 'DateTime') && is_a($end, 'DateTime') ) {

		$days = $end->diff( $start )->format('%a') + 1;
		return (int) $days;

	}

	// Failed to init objects
	return false;

}

/**
 * Get the Unix timestamp for the next occurrence of 1am in the site's timezone
 *
 * Used for scheduling daily tasks (e.g. webhooks) to run at 1am local time,
 * ensuring a full day of data is available for the previous period.
 *
 * @return int Unix timestamp for the next 1am local time
 */
function wpdai_next_1am_local_timestamp() {
	if ( function_exists( 'wp_timezone' ) ) {
		$tz = wp_timezone();
	} elseif ( function_exists( 'wp_timezone_string' ) ) {
		$tz = new DateTimeZone( wp_timezone_string() );
	} else {
		$tz_string = get_option( 'timezone_string', 'UTC' );
		$tz        = $tz_string ? new DateTimeZone( $tz_string ) : new DateTimeZone( 'UTC' );
	}
	$now = new DateTimeImmutable( 'now', $tz );
	$next_1am = $now->modify( 'today 01:00:00' );
	if ( $next_1am <= $now ) {
		$next_1am = $next_1am->modify( '+1 day' );
	}
	return $next_1am->getTimestamp();
}

/**
 *
 *	Return the current website's time
 *	@default = 2020-09-24 10:50:22
 *	@link https://www.php.net/manual/en/datetime.formats.relative.php relative formats
 *
 */
function wpdai_site_date_time( $format = 'Y-m-d H:i:s', $modify = false ) {

    // $date = new DateTime( current_time( 'Y-m-d H:i:s' ) );
    $date = date_create( current_time( 'Y-m-d H:i:s' ) );

    if ( $modify ) {
        $date = date_modify( $date, $modify );
    }

    $date = date_format( $date, $format );

    return $date;

}

/**
 *
 *	Date picker
 *
 */
function wpdai_date_picker( $selected_date = null, $name = '_wpd_date_paid', $classes = '', $placeholder = 'yyyy-mm-dd' ) {

    return '<span class="wpd-date-picker-input"><input type="text" placeholder="' . $placeholder . '" class="wpd-input wpd-jquery-datepicker ' . $classes . '" name="'.$name.'" value="' . $selected_date . '" autocomplete="off"></span>';

}

/**
 * 
 * 	Calculate the difference in time in a readable format
 * 
 * 	The difference is returned in a human-readable format such as "1 hour", "5 mins", "2 days".
 *
 * 	@param int $from Unix timestamp from which the difference begins
 * 	@param int $to Unix timestamp to end the time difference. Default becomes time() if not set.
 * 
 **/
function wpdai_calculate_time_difference( $from, $to = 0 ) {

	// Convert $to to store time
	if ( $to === 0 ) {
		$to = current_time('timestamp');
	}

	// Convert from timestring to timestamp
	if ( is_string($from) ) {
		$from = strtotime( $from );
	}

	// Conveert timestring to timestamp
	if ( is_string($to) ) {
		$to = strtotime( $to );
	}

	return human_time_diff( $from, $to );

}

/**
 * Calculate when a scheduled hook will next run in human-readable format.
 *
 * Returns a string like "23 hours 16 minutes" or "1 hour 5 minutes 30 seconds",
 * similar to Action Scheduler's display.
 *
 * Inspired by Action Scheduler's ActionScheduler_ListTable::human_interval().
 *
 * @param string $hook_name        The hook name (e.g. 'wpd_ai_starshipit_sync_costs_with_orders').
 * @param int    $from_timestamp   Unix timestamp to calculate from. Default: current time (gmt).
 * @param int    $periods_to_include Depth of time periods to include. E.g. 2 = "23 hours 16 minutes", 3 = "23 hours 16 minutes 45 seconds".
 * @return string Human-readable duration (e.g. "23 hours 16 minutes"), or empty string if not scheduled or in the past.
 */
function wpdai_get_time_until_next_scheduled_by_hook_name( $hook_name, $from_timestamp = null, $periods_to_include = 2 ) {

	$scheduled_timestamp = wpdai_get_next_scheduled_timestamp( $hook_name );

	if ( $scheduled_timestamp === null || $scheduled_timestamp <= 0 ) {
		return '';
	}

	if ( $from_timestamp === null ) {
		$from_timestamp = time();
	}

	$interval = (int) $scheduled_timestamp - (int) $from_timestamp;

	if ( $interval <= 0 ) {
		return '';
	}

	$time_periods = array(
		array(
			'seconds' => DAY_IN_SECONDS,
			'names'   => _n_noop( '%s day', '%s days', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		),
		array(
			'seconds' => HOUR_IN_SECONDS,
			'names'   => _n_noop( '%s hour', '%s hours', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		),
		array(
			'seconds' => MINUTE_IN_SECONDS,
			'names'   => _n_noop( '%s minute', '%s minutes', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		),
		array(
			'seconds' => 1,
			'names'   => _n_noop( '%s second', '%s seconds', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		),
	);

	$output           = '';
	$seconds_remaining = $interval;
	$periods_included  = 0;
	$num_periods       = count( $time_periods );
	$periods_to_include = max( 1, min( (int) $periods_to_include, $num_periods ) );

	for ( $i = 0; $i < $num_periods && $seconds_remaining > 0 && $periods_included < $periods_to_include; $i++ ) {

		$periods_in_interval = (int) floor( $seconds_remaining / $time_periods[ $i ]['seconds'] );

		if ( $periods_in_interval > 0 ) {
			if ( ! empty( $output ) ) {
				$output .= ' ';
			}
			$output            .= sprintf( translate_nooped_plural( $time_periods[ $i ]['names'], $periods_in_interval, 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $periods_in_interval );
			$seconds_remaining -= $periods_in_interval * $time_periods[ $i ]['seconds'];
			$periods_included++;
		}
	}

	return $output;
}

/**
 * Get the Unix timestamp when a scheduled hook will next run (future actions only).
 *
 * Prefers future scheduled actions over past-due ones using as_get_scheduled_actions
 * when available. Falls back to as_next_scheduled_action.
 *
 * @param string $hook_name The hook name (e.g. 'wpd_ai_starshipit_sync_costs_with_orders').
 * @return int|null Unix timestamp of next run, or null if not scheduled.
 */
function wpdai_get_next_scheduled_timestamp( $hook_name ) {

	if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
		$next_run = function_exists( 'as_next_scheduled_action' ) ? as_next_scheduled_action( $hook_name ) : false;
		return ( is_numeric( $next_run ) && $next_run > 0 ) ? (int) $next_run : null;
	}

	$actions = as_get_scheduled_actions(
		array(
			'hook'         => $hook_name,
			'status'       => \ActionScheduler_Store::STATUS_PENDING,
			'date'         => time(),
			'date_compare' => '>=',
			'orderby'      => 'date',
			'order'        => 'ASC',
			'per_page'     => 1,
		),
		'ids'
	);

	if ( empty( $actions ) || ! is_array( $actions ) ) {
		if ( function_exists( 'as_next_scheduled_action' ) ) {
			$next_run = as_next_scheduled_action( $hook_name );
			return ( is_numeric( $next_run ) && $next_run > 0 ) ? (int) $next_run : null;
		}
		return null;
	}

	$store   = \ActionScheduler::store();
	$action  = $store->fetch_action( $actions[0] );
	$schedule = $action->get_schedule();
	$date    = method_exists( $schedule, 'get_date' ) ? $schedule->get_date() : null;

	if ( $date && is_a( $date, 'DateTime' ) ) {
		return (int) $date->format( 'U' );
	}

	return null;
}

/**
 * Get the local date/time when a scheduled hook will next run.
 *
 * Uses Action Scheduler and formats the result in the site's local timezone.
 * Prefers future scheduled actions over past-due ones.
 *
 * @param string $hook_name The hook name (e.g. 'wpd_ai_starshipit_sync_costs_with_orders').
 * @param string $format    Optional. PHP date format for output. Default: WPD_AI_PHP_PRETTY_DATETIME or site format.
 * @return string|null Formatted local date/time string, or null if not scheduled.
 */
function wpdai_get_next_scheduled_date_by_hook_name( $hook_name, $format = null ) {

	$next_run = wpdai_get_next_scheduled_timestamp( $hook_name );

	if ( $next_run === null || $next_run <= 0 ) {
		return null;
	}

	if ( $format === null ) {
		$format = defined( 'WPD_AI_PHP_PRETTY_DATETIME' ) ? WPD_AI_PHP_PRETTY_DATETIME : ( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
	}

	$site_timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );

	return wp_date( $format, $next_run, $site_timezone );

}

/**
 * Get the HTML for the next scheduled date and time until next sync.
 *
 * @param string $hook_name The hook name (e.g. 'wpd_ai_starshipit_sync_costs_with_orders').
 * @return string HTML for the next scheduled date and time until next sync.
 */
function wpdai_next_scheduled_event_date_html( $hook_name ) {

	$next_scheduled_date = wpdai_get_next_scheduled_date_by_hook_name( $hook_name );
	$time_until = wpdai_get_time_until_next_scheduled_by_hook_name( $hook_name );

	if ( empty( $next_scheduled_date ) || empty( $time_until ) ) {
		return '';
	}

	return '<p>' . esc_html( $next_scheduled_date ) . '<br><b>' . esc_html__( 'Time until next sync:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) . '</b> ' . esc_html( $time_until ) . '</p>';

}