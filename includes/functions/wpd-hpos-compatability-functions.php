<?php
/**
 *
 * HPOS related functions
 * 
 * @package Alpha Insights
 * @version 3.3.5
 * @since 3.3.5
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * 
 * 	Will fetch order meta by order ID without loading the order object
 * 	Compatible with WooCommerce HPOS / standard
 * 
 * 	@param int $order_id The order ID
 * 	@param string $meta_key The meta key to search for
 * 
 * 	@return mixed The result for the meta key fetch, will always return a single result if succesful. Will return Null if nothing found, and false on bad params.
 * 
 * 	@see https://wpdavies.dev/how-to-get-order-meta-from-woocommerce-order-without-loading-order-object-hpos/
 * 	@author Christopher Davies - WP Davies
 * 
 **/
function wpd_get_order_meta_by_order_id( $order_id, $meta_key ) {

	// Default result
	$result = null;

	// Make sure we have an order id formatted correctly
	if ( ! is_numeric($order_id) || $order_id < 1 ) return false;

	// Make sure we have an appropriately formatted meta key
	if ( ! is_string($meta_key) ) return false;

	// If we are using HPOS
	if ( wpd_is_hpos_enabled() ) {

		// Call the database directly
		global $wpdb;

		// Meta table name
		$meta_table_name = $wpdb->prefix . 'wc_orders_meta';

		// Sanitize the query
		$query = $wpdb->prepare( "SELECT meta_value FROM $meta_table_name WHERE order_id = %d AND meta_key = %s", $order_id, $meta_key );

		// Execute the query & transform if required
		$result = maybe_unserialize( $wpdb->get_var( $query ) );

	} else {

		// Call get_post_meta
		$result = get_post_meta( $order_id, $meta_key, true );

	}

	// Return the finding
	return $result;

}

/**
 * 
 * 	Deletes all post meta that matches an Order ID & Meta Key, will delete via post_meta as well as the WC Meta table if HPOS is enabled
 * 	Compatible with WooCommerce HPOS / standard
 * 
 * 	@param int $order_id The order ID
 * 	@param string $meta_key The meta key to search for
 * 
 * 	@return mixed The result for the meta key fetch, will always return a single result if succesful. Will return Null if nothing found, and false on bad params.
 * 
 * 	@see https://wpdavies.dev/how-to-get-order-meta-from-woocommerce-order-without-loading-order-object-hpos/
 * 	@author Christopher Davies - WP Davies
 * 
 **/
function wpd_delete_order_meta_by_order_id( $order_id, $meta_key ) {

	// Default result
	$result = null;

	// Make sure we have an order id formatted correctly
	if ( ! is_numeric($order_id) || $order_id < 1 ) return false;

	// Make sure we have an appropriately formatted meta key
	if ( ! is_string($meta_key) ) return false;

	// If we are using HPOS
	if ( wpd_is_hpos_enabled() ) {

		// Call the database directly
		global $wpdb;

		// Meta table name
		$meta_table_name = $wpdb->prefix . 'wc_orders_meta';

		// Sanitize the query
		$query = $wpdb->prepare( "DELETE FROM $meta_table_name WHERE order_id = %d AND meta_key = %s", $order_id, $meta_key );

		// Execute the query & transform if required
		$result = $wpdb->query( $query );

	}

	// Delete Post Meta Too
	$result = delete_post_meta( $order_id, $meta_key );

	// Return the finding
	return $result;

}

/**
 * 
 * 	Check if the customer is using HPOS (High Performance Order Storage)
 * 
 * 	@return bool True if HPOS is enabled, false if not
 * 
 *  @see https://wpdavies.dev/how-to-get-order-meta-from-woocommerce-order-without-loading-order-object-hpos/
 * 	@author Christopher Davies - WP Davies
 *	 
 **/
function wpd_is_hpos_enabled() {

	// Fixed: Added quotes around class name and backslash for namespace
	if ( class_exists('\\Automattic\\WooCommerce\\Utilities\\OrderUtil') ) {

		return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

	} else {

		return false;

	}

}