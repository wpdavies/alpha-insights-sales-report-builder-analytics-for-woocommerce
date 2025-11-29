<?php
/**
 *
 * Statistic Fetch Related Functions
 * 
 * Typically used to fetch specific or small groups of analytics / calculations
 *
 * @package Alpha Insights
 * @version 3.2.1
 * @since 3.2.1
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 *
 *	Webhook data request
 *
 */
function wpd_webhook_data_request( $from_date = null, $to_date = null, $JSON = true ) {

	// Collect data
	if ( is_string( $from_date ) && is_string( $to_date ) ) {

		$data_warehouse = new WPD_Data_Warehouse_React( array( 'date_from' => $from_date, 'date_to' => $to_date ) );
		$data_warehouse->fetch_store_profit_data();

	} else {

		$data_warehouse = new WPD_Data_Warehouse_React( array( 'date_preset' => 'all_time' ) );
		$data_warehouse->fetch_store_profit_data();

	}

	// Default array
	$webhook_data = array(
		'order_data' => $data_warehouse->get_data('orders', 'totals'),
		'expense_data' => $data_warehouse->get_data('expenses', 'totals'),
		'store_profit_data' => $data_warehouse->get_data('store_profit', 'totals')
	);

	// Prety print JSON
	if ( $JSON ) $webhook_data = json_encode( $webhook_data, JSON_PRETTY_PRINT );

	// Return results
	return $webhook_data;

}

/**
 *
 *	Execute Webhook Post
 *
 */
function wpd_webhook_post_data( $from_date = null, $to_date = null ) {

	// Vars and data
	$response 			= array();
	$webhook_settings 	= get_option( 'wpd_ai_webhook_settings' );
	$url 				= $webhook_settings['webhook_url'];

	// Format according to date if necessary
	if ( $from_date === null && $to_date === null ) {

		$json_data = wpd_webhook_data_request();

	} else {

		$json_data = wpd_webhook_data_request( $from_date, $to_date );

	}

	// Post request
	$data = wp_remote_post( $url, array(

	    'headers'     => array( 'Content-Type' => 'application/json; charset=utf-8' ),
	    'body'        => $json_data,
	    'method'      => 'POST',
	    'data_format' => 'body',

	) );

	// Store data for debug
	$response['time_executed'] 	= wpd_site_date_time( "D M d, Y G:i" );
	$response['url_sent_to'] 	= $url;

	// Check response
	if ( is_wp_error( $data ) ) {

		$response['message'] = 'Failed to connect to the provided URL - please check that your URL is correct.';
		$response['success'] = false;

	} else {

		$response_code = $data['response']['code'];
		$response_message = $data['response']['message'];

		$response['response_code'] 	= $response_code;
		$response['response_message'] 	= $response_message;

		if ( $response_code == 200 ) {

			$response['success'] = true;
			$response['success_message'] = 'Your data was succesfully posted to your requested URL.';

			// Store last succesful date
			$webhook_settings['webhook_schedule_last_run'] = $response['time_executed'];
			update_option( 'wpd_ai_webhook_settings', $webhook_settings );

		} else {

			$response['message'] = 'Failed to connect to the provided URL - ' . $response_message;
			$response['success'] = false;

		}

	}

	// Show data sent and response received
	$response['data_sent'] = json_decode( $json_data );

	wpd_write_log( 'Webhook log report (' . $response['time_executed'] . '):', 'webhook' );
	wpd_write_log( $response, 'webhook' );

	return $response;

}
