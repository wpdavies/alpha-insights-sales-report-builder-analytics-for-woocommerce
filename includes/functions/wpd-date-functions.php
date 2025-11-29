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
function wpd_validate_date_format( $date, $format = 'Y-m-d' ){

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
function wpd_calculate_days_between_dates( $from_date, $to_date = false ) {

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
 *
 *	Return the current website's time
 *	@default = 2020-09-24 10:50:22
 *	@link https://www.php.net/manual/en/datetime.formats.relative.php relative formats
 *
 */
function wpd_site_date_time( $format = 'Y-m-d H:i:s', $modify = false ) {

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
function wpd_date_picker( $selected_date = null, $name = '_wpd_date_paid', $classes = '', $placeholder = 'yyyy-mm-dd' ) {

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
function wpd_calculate_time_difference( $from, $to = 0 ) {

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
 *
 *	Quick select date periods
 *
 */
function wpd_quick_select_dates() {

	$current_quick_select = null;

	if ( isset($_COOKIE['wpd-date-quick-select']) && is_string($_COOKIE['wpd-date-quick-select']) ) {

		$current_quick_select = $_COOKIE['wpd-date-quick-select'];

	}

	ob_start();
	?>
		<span class="wpd-quick-select-date<?php if ($current_quick_select === 'today') echo ' selected'; ?>" data-wpd-quick-select="today">Today</span>
		<span class="wpd-quick-select-date<?php if ($current_quick_select === 'yesterday') echo ' selected'; ?>" data-wpd-quick-select="yesterday">Yesterday</span>
		<span class="wpd-quick-select-date<?php if ($current_quick_select === 'this-month') echo ' selected'; ?>" data-wpd-quick-select="this-month">This Month</span>
		<span class="wpd-quick-select-date<?php if ($current_quick_select === 'last-month') echo ' selected'; ?>" data-wpd-quick-select="last-month">Last Month</span>
		<span class="wpd-quick-select-date<?php if ($current_quick_select === 'this-year') echo ' selected'; ?>" data-wpd-quick-select="this-year">This Year</span>
		<span class="wpd-quick-select-date<?php if ($current_quick_select === 'last-year') echo ' selected'; ?>" data-wpd-quick-select="last-year">Last Year</span>
		<span class="wpd-quick-select-date<?php if ($current_quick_select === 'all-time') echo ' selected'; ?>" data-wpd-quick-select="all-time">All Time</span>
	<?php
	return ob_get_clean();

}
