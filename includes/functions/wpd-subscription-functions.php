<?php
/**
 *
 * WooCommerce Subscription related functions
 *
 * @package Alpha Insights
 * @version 3.4.0
 * @since 3.4.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 *
 *	Check if user has wc_subscriptions
 *
 */
function wpdai_is_wc_subscriptions_active() {

	if ( class_exists( 'WC_Subscriptions' ) ) {

		return true;

	} else {

		return false;

	}

}

/**
 * 
 * 	Checks if a subscription was active on a given date, expects all inputs to be a timestamp
 * 	Will default to today's date if date_to_check is ommitted
 * 
 * 	@param int $date_created a timestamp to check the date this subscription was created
 * 	@param int $date_cancelled a timestamp to check for the cancellation date, expects 0 if there is no cancellation date
 * 	@param int $date_to_check a timestamp to check for the given date
 * 
 * 	@return bool Returns true if active on date, otherwise false
 * 
 **/
function wpdai_is_subscription_active_on_date( $date_created_timestamp, $date_cancelled_timestamp, $date_to_check_timestamp = null ) {

	// Normalize all inputs to start-of-day in the site timezone.
	$date_created_timestamp = wpdai_normalize_to_local_day_start_timestamp( $date_created_timestamp );
	$date_cancelled_timestamp = ( is_numeric( $date_cancelled_timestamp ) && $date_cancelled_timestamp > 0 )
		? wpdai_normalize_to_local_day_start_timestamp( $date_cancelled_timestamp )
		: false;

	if ( null === $date_to_check_timestamp ) {
		$date_to_check_timestamp = wpdai_local_date_string_to_utc_timestamp( wp_date( 'Y-m-d' ), '00:00:00' );
	} else {
		$date_to_check_timestamp = wpdai_normalize_to_local_day_start_timestamp( $date_to_check_timestamp );
	}

	// Date created must be in a good format
	if ( $date_created_timestamp === false ) return false;

	// Check if the subscription was created before the date to check
	if ( $date_created_timestamp <= $date_to_check_timestamp  ) {

		// If there's a cancellation date, did it occur before the date to check
		if ( $date_cancelled_timestamp && $date_cancelled_timestamp <= $date_to_check_timestamp ) {

			return false;

		} else {

			return true;

		}

	} else {

		// This subscription was created after the date to check
		return false;

	}

}