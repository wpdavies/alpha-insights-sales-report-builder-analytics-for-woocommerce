<?php
/**
 *
 * Cache Related Functions
 *
 * @package Alpha Insights
 * @version 3.2.0
 * @since 3.2.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * 
 * 	Deletes all stored caches
 * 
 **/
function wpdai_delete_all_data_caches() {

	wpdai_delete_all_order_data_cache();
	wpdai_delete_all_product_cache();

	return true;

}

/**
 * 
 * 	Deletes product cache for all products and variations
 * 	Will also delete the product statistics cache
 * 
 **/
function wpdai_delete_all_product_cache() {

	wpdai_write_log( 'Attempting to delete all product cache', 'cache' );

    $products_IDs = new WP_Query( 
    	array(
	        'post_type' 	=> array( 'product', 'product_variation' ),
	        'post_status' 	=> 'publish',
	        'fields' 		=> 'ids',
	        'posts_per_page' => -1,
    	) 
    );

    $products_IDs 		= $products_IDs->posts;
	$total_records 		= count( $products_IDs );
	$records_updated 	= 0;

	wpdai_write_log('About to loop through ' . $total_records . ' products and delete their transients & cache(s).', 'cache');

	foreach( $products_IDs as $product_id ) {

		$deleted = wpdai_delete_product_cache_by_product_id( $product_id );

		if ( $deleted ) $records_updated++;
		
	}

	if ( $products_IDs ) {

		$response['success']			= true;
		/* translators: 1: Total number of products found, 2: Number of products with cache refreshed */
		$response['message']	= sprintf( __( '%1$d products were found, %2$d products have had their cache refreshed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $total_records, $records_updated );

	} else {

		$response['success']			= false;
		$response['message'] 			= __( 'Unfortunately we could not complete this action, no product IDs were found.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	wpdai_write_log( 'Product cache delete complete, dumping response.', 'cache' );
	wpdai_write_log( $response, 'cache' );

	return $response;

}

/**
 *
 *	Delete product data cache by product ID
 *
 */
function wpdai_delete_product_cache_by_product_id( $product_id ) {

	// Deletes the transient used for analytics reporting
	$delete_transient = delete_transient( '_wpd_product_statistics_' . $product_id );

	// Deletes the data storage cache
	$delete_cache = delete_post_meta( $product_id, '_wpd_ai_product_data_store' );
	$delete_cache = delete_post_meta( $product_id, '_wpd_ai_product_analytics' );

	return $delete_cache;

}

/**
 *
 *	Store prodct cache
 *
 */
function wpdai_update_product_cache_by_product_id( $product_id ) {

	$product_data_collection 	= wpdai_product_data_collection( $product_id );
	$product_data_store 		= update_post_meta( $product_id, '_wpd_ai_product_data_store', $product_data_collection );

	return array(

		'success' => $product_data_store,
		'data' => $product_data_collection

	);

}

/**
 *
 *	Delete order data cache from custom cache table
 *
 * 	Deletes the meta cache for a given order / order_id
 *	@param WC_Order|int Accepts a WC_Order or order_id
 *	@return bool True on success, false on failure (if we couldn't find an order)
 *
 */
function wpdai_delete_order_cache_by_order_id( $order_id ) {

	// Safety check our order ID
	if ( ! is_numeric($order_id) || $order_id < 1 ) {
		return false;
	}


	// Load the DB Interactor
	$db_interactor = new WPDAI_Database_Interactor();
	$order_calculations_table = $db_interactor->order_calculations_table;

	// Load WPDB
	global $wpdb;

	// Sanitize query
	$sql_query = $wpdb->prepare( "DELETE FROM $order_calculations_table WHERE order_id = %d", $order_id );

	// Fetch result
	$result = $wpdb->query( $sql_query );

	// Check for DB errors
	if ( $wpdb->last_error ) {

		$result = $wpdb->last_error;
		wpdai_write_log( 'Error deleting the order cache for order id: ' . $order_id, 'db_error' );
		wpdai_write_log( $result, 'db_error' );
		return false;

	}
	
	// On Success
	return true;

}

/**
 *
 *	Delete order data cache from custom cache table for multiple orders
 *
 * 	Deletes the meta cache for an array of order IDs
 *	@param array $order_ids Array of order IDs to delete cache for
 *	@return int|bool Number of rows deleted on success, false on failure, true if no rows were deleted
 *	@since 4.9.0
 *
 */
function wpdai_delete_order_cache_by_order_ids( $order_ids ) {

	// Safety check - must be an array
	if ( ! is_array( $order_ids ) || empty( $order_ids ) ) {
		return false;
	}

	// Sanitize all order IDs - convert to integers and filter out invalid values
	$sanitized_ids = array_filter( array_map( 'absint', $order_ids ), function( $id ) {
		return $id > 0;
	});

	// If no valid IDs after sanitization, return false
	if ( empty( $sanitized_ids ) ) {
		return false;
	}

	// Load the DB Interactor
	$db_interactor = new WPDAI_Database_Interactor();
	$order_calculations_table = $db_interactor->order_calculations_table;

	// Load WPDB
	global $wpdb;

	// Create placeholders for prepared statement
	$placeholders = implode( ', ', array_fill( 0, count( $sanitized_ids ), '%d' ) );

	// Prepare and execute query
	$sql_query = $wpdb->prepare( 
		"DELETE FROM $order_calculations_table WHERE order_id IN ($placeholders)", 
		$sanitized_ids 
	);

	// Fetch result
	$result = $wpdb->query( $sql_query );

	// Check for DB errors
	if ( $wpdb->last_error ) {

		$error = $wpdb->last_error;
		wpdai_write_log( 'Error deleting order cache for ' . count( $sanitized_ids ) . ' order IDs', 'db_error' );
		wpdai_write_log( $error, 'db_error' );
		return false;

	}
	
	// Return true, if no rows were deleted
	if ( $result === 0 ) return true;

	// If we've deleted records, invalidate the cache
	if ( $result > 0 ) {

		// Invalidate the cache
		delete_option( 'wpd_ai_all_orders_cached' );

	}
	
	// On Success - return count of deleted rows
	return (int) $result;

}

/**
 *
 *	Delete order data cache from custom cache table by product IDs
 *
 * 	Gets all orders that contain the given product IDs and deletes their cache
 *	@param array $product_ids Array of product IDs
 *	@return int|bool Number of cache rows deleted on success, false on failure, true if no rows were found/deleted
 *	@since 4.9.0
 *
 */
function wpdai_delete_order_cache_by_product_ids( $product_ids ) {

	wpdai_write_log( 'Attempting to delete order cache by product IDs', 'cache' );

	// Safety check - must be an array
	if ( ! is_array( $product_ids ) || empty( $product_ids ) ) {
		wpdai_write_log( 'Product IDs not an array or empty', 'cache' );
		return false;
	}
	wpdai_write_log( 'Product IDs', 'cache' );
	wpdai_write_log( $product_ids, 'cache' );

	// Get all order IDs that contain these products
	$order_ids = wpdai_get_order_ids_by_product_ids( $product_ids );

	wpdai_write_log( 'Order IDs found', 'cache' );
	wpdai_write_log( $order_ids, 'cache' );

	// If no orders found with these products, return true (nothing to delete, but not an error)
	if ( empty( $order_ids ) ) {
		wpdai_write_log( 'No order IDs found, returning true', 'cache' );
		return true;
	}

	// Delete cache for all related orders
	$deleted_records = wpdai_delete_order_cache_by_order_ids( $order_ids );
	wpdai_write_log( 'Deleted ' . $deleted_records . ' cache records', 'cache' );
	return $deleted_records;

}

/**
 * 
 * 	Build order cache in batches
 * 	@param int $batch_size The number of orders to build cache for
 * 	@return int|bool The number of orders built, false on failure
 * 
 **/
function wpdai_build_order_cache_in_batch( $batch_size = 500 ) {

	$orders = wpdai_get_order_ids_without_calculation_cache($batch_size);

	$i = 0;
	if ( is_array($orders) ) {

		// Check for 0 if complete
		if ( count($orders) === 0 ) {
			update_option( 'wpd_ai_all_orders_cached', 1 );
			return 0;
		}

		foreach( $orders as $order ) {
			wpdai_calculate_cost_profit_by_order( $order );
			$i++;
		}

		return $i;

	} else {

		return false;
		
	}

}

/**
 * 
 * 	Deletes All order cache calculations from the custom cache table
 * 
 * 	@return bool|int Will return false on failure, number of records deleted on success
 * 
 **/
function wpdai_delete_all_order_data_cache() {

	$start = microtime( true );

	// Log
	wpdai_write_log( 'Executing WC Orders cache delete on orders from custom table.', 'cache' );

	// Load the DB Interactor
	$db_interactor = new WPDAI_Database_Interactor();
	$order_calculations_table = $db_interactor->order_calculations_table;

	// Load WPDB
	global $wpdb;

	// Fetch result
	$result = $wpdb->query( "TRUNCATE $order_calculations_table" );

	// Check for DB errors
	if ( $wpdb->last_error ) {

		$result = $wpdb->last_error;
		wpdai_write_log( 'Error deleting the order meta cache', 'db_error' );
		wpdai_write_log( $result, 'db_error' );
		return false;

	}

	$order_count = 0;

	$finish = microtime( true );
	$total_time_elapsed = $finish - $start;
	$memory_usage = wpdai_get_peak_memory_usage();

	wpdai_write_log( 'Execution of WC Orders cache delete succesfully completed. Process took ' . $total_time_elapsed . ' seconds and ' . $memory_usage . ' memory usage.', 'cache' );

	delete_option( 'wpd_ai_all_orders_cached' );

	return true;

}

/**
 * 
 * 	Clears all order object cache data for given Order Object
 * 
 * 	@param WC_Order|WC_Order_Refund $order
 * 	
 **/
function wpdai_clear_wc_order_object_cache( $order ) {

	if ( ! method_exists($order, 'get_id') ) {
		return false;
	}

	clean_post_cache( $order->get_id() );
	wc_delete_shop_order_transients( $order );
	wp_cache_delete( 'order-items-' . $order->get_id(), 'orders' );
	return true;

}

/**
 * 
 *	Deletes all order meta overrides
 * 
 */
function wpdai_delete_order_meta_overrides( $order_id ) {

	$order = wc_get_order( $order_id );

	if ( ! is_a($order, 'WC_Order') ) return false;

	$order->delete_meta_data( '_wpd_ai_total_shipping_cost' );
	$order->delete_meta_data( '_wpd_ai_total_payment_gateway_cost' );
	$order->delete_meta_data( '_wpd_ai_total_product_cost' );

	// Save changes
	$order->save_meta_data();
	
	return true;
}

/**
 *
 *	Collect WC product data to store in array format
 *
 * 	@see Used for WPD Product Cache - stored in meta
 *
 */
function wpdai_product_data_collection( $active_product_id ) {

	// Get product object
	$product = wc_get_product( $active_product_id );

	if ( ! is_object($product) ) {
		return array(

			'product_name' 						=> 'Unknown',
			'product_sku' 						=> 'N/A',
			'product_date_created'				=> null, // local timestamp
			'product_status' 					=> null,
			'product_link' 						=> null,
			'product_stock_qty' 				=> null,
			'product_stock_status' 				=> null,
			'product_type' 						=> null,
			'product_rrp' 						=> null,
			'product_cost_price' 				=> null,
			'product_image' 					=> null,
			'parent_id' 						=> null,
			'product_category' 					=> null,
			'product_tags' 						=> null,
			'variation_attributes' 				=> null,
			'combine_variations_product_image' 	=> null,
			'combine_variations_product_name' 	=> null,
			'combine_variations_product_sku' 	=> null,
	
		);
	}

	$product_name 							= $product->get_name();
	$product_status 						= $product->get_status();
	$product_sku 							= $product->get_sku();
	// Convert time object into local timestamp
	$product_date_created_obj 				= $product->get_date_created();
	$product_date_created 					= null;
	if ( is_a( $product_date_created_obj, 'WC_DateTime' ) ) {
		if ( method_exists( $product_date_created_obj, 'getOffsetTimestamp' ) ) {
			$product_date_created = $product_date_created_obj->getOffsetTimestamp();
		} elseif ( method_exists( $product_date_created_obj, 'getTimestamp' ) ) {
			$product_date_created = $product_date_created_obj->getTimestamp();
		}
		if ( null !== $product_date_created && $product_date_created <= 0 ) {
			$product_date_created = null;
		}
	}
	$product_link 							= get_permalink( $active_product_id );
	$product_stock_qty 						= $product->get_stock_quantity();
	$product_stock_status 					= $product->get_stock_status();
	$product_type 							= $product->get_type();
	$product_rrp 							= (float) $product->get_regular_price();
	$product_cost_price 					= wpdai_get_cost_price_by_product_id( $active_product_id );
	$parent_id 								= '';
	$combine_variations_product_image 	   	= '';
	$combine_variations_product_name 	   	= '';
	$combine_variations_product_sku 	   	= '';
	$variation_data 						= [];
	$image_src 								= wp_get_attachment_image_src( $product->get_image_id(), 'thumbnail' );
	$product_image 							= ( is_array($image_src) && isset($image_src[0]) ) ? $image_src[0] : null;

	// Fallback image
	if ( ! $product_image ) {
		$product_image = WPD_AI_URL_PATH . 'assets/img/' . 'no_image.png';
	}

	/**
	 * 
	 *	If is product variation, we'll have to check parent ID
	 *
	 */
	if ( $product->get_parent_id() ) {

		$parent_id 			= $product->get_parent_id();
		$product_category	= get_the_terms( $parent_id, 'product_cat' );
		$product_tags 		= get_the_terms( $parent_id, 'product_tag' );
		$parent_product	   	= wc_get_product( $parent_id );

		if ( is_a( $parent_product, 'WC_Product' ) ) {

			$combine_variations_product_image 	   	= get_the_post_thumbnail_url( $parent_id, 'thumbnail' );
			$combine_variations_product_name 	   	= $parent_product->get_name();
			$combine_variations_product_sku 	   	= $parent_product->get_sku();

		}

		// Get variation labels
		$attributes = $product->get_attributes();

		if ( is_array($attributes) && ! empty($attributes) ) {

			foreach ( $attributes as $taxonomy => $term_slug ) {

				if ( taxonomy_exists( $taxonomy ) ) {
					// Get readable label (e.g. "Color")
					$label = wc_attribute_label( $taxonomy );
	
					// Get term name (e.g. "Red" instead of "red")
					$term = get_term_by( 'slug', $term_slug, $taxonomy );
					$value =  $term ? $term->name : $term_slug;
	
				} else {
					// Custom (non-global) attribute
					$label = ucfirst( str_replace( '-', ' ', $taxonomy ) );
					$value = ucfirst( str_replace( '-', ' ', $term_slug ) );
				}
	
				$variation_data[$label] = $value;
	
			}

		}


	} else {

		$product_category	= get_the_terms( $active_product_id, 'product_cat' );
		$product_tags 		= get_the_terms( $active_product_id, 'product_tag' );						

	}

	// store the results
	$product_data_collection = array(

		'product_name' 						=> $product_name,
		'product_status' 					=> $product_status,
		'product_date_created'				=> $product_date_created,
		'product_sku' 						=> $product_sku,
		'product_link' 						=> $product_link,
		'product_stock_qty' 				=> $product_stock_qty,
		'product_stock_status' 				=> $product_stock_status,
		'product_type' 						=> $product_type,
		'product_rrp' 						=> $product_rrp,
		'product_cost_price' 				=> $product_cost_price,
		'product_image' 					=> $product_image,
		'parent_id' 						=> $parent_id,
		'product_category' 					=> $product_category,
		'product_tags' 						=> $product_tags,
		'variation_attributes' 				=> $variation_data,
		'combine_variations_product_image' 	=> $combine_variations_product_image,
		'combine_variations_product_name' 	=> $combine_variations_product_name,
		'combine_variations_product_sku' 	=> $combine_variations_product_sku,

	);

	return $product_data_collection;

}

/**
 * 
 * 	Deletes saved Meta Data against the order line items
 * 
 * 	This query will hit the database directly based on 
 *  '_wpd_ai_product_cogs' & '_wpd_ai_product_cogs_currency'
 * 
 * 	@return int|bool The number of records deleted, false on failure
 * 
 **/
function wpdai_delete_all_order_line_item_meta_cogs() {

	// Default count
	$result = 0;

	// Call the database directly
	global $wpdb;

	// Meta table name
	$order_item_meta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';

	// Sanitize the query
	$query = $wpdb->prepare( "DELETE FROM $order_item_meta_table WHERE meta_key = %s OR meta_key = %s", '_wpd_ai_product_cogs', '_wpd_ai_product_cogs_currency' );

	// Execute the query & transform if required
	$result = $wpdb->query( $query );

	// Ran into an error
	if ( $wpdb->last_error ) {

		// Log the error
		wpdai_write_log( 'Error trying to delete all line item COGS data.', 'db_error' );
		wpdai_write_log( $wpdb->last_error, 'db_error' );

		// Return fail
		return false;

	}

	// Return the count of effected rows
	return $result;

}

/**
 * 
 * 	Deletes all order meta overrides saved to an order object
 * 	Will scan the postmeta table and the HPOS meta table
 * 	Searches for '_wpd_ai_total_shipping_cost', '_wpd_ai_total_payment_gateway_cost', '_wpd_ai_total_product_cost'
 * 
 * 	@return int|bool The number of records deleted, false on failure
 * 
 **/
function wpdai_delete_all_order_meta_overrides() {

	// Default count
	$result = 0;

	// Call the database directly
	global $wpdb;

	// Meta table names
	$wp_posts_meta_table = $wpdb->prefix . 'postmeta';
	$wc_orders_meta_table = $wpdb->prefix . 'wc_orders_meta';

	// Sanitize the query
	$query = $wpdb->prepare(
		"DELETE FROM $wp_posts_meta_table WHERE meta_key = %s OR meta_key = %s OR meta_key = %s", 
		'_wpd_ai_total_shipping_cost', 
		'_wpd_ai_total_payment_gateway_cost',
		'_wpd_ai_total_product_cost',
	);

	// Execute the query & transform if required
	$post_meta_delete_count = $wpdb->query( $query );

	// Ran into an error
	if ( $wpdb->last_error ) {

		// Log the error
		wpdai_write_log( 'Error trying to delete all order meta overrides from the posts table.', 'db_error' );
		wpdai_write_log( $wpdb->last_error, 'db_error' );

		// Return a fail
		return false;

	}

	// Add to the total
	if ( is_numeric($post_meta_delete_count) ) $result += $post_meta_delete_count; 

	// Now process the WC meta table
	if ( wpdai_is_hpos_enabled() ) {

		// Sanitize the query
		$query = $wpdb->prepare(
			"DELETE FROM $wc_orders_meta_table WHERE meta_key = %s OR meta_key = %s OR meta_key = %s", 
			'_wpd_ai_total_shipping_cost', 
			'_wpd_ai_total_payment_gateway_cost',
			'_wpd_ai_total_product_cost',
		);

		// Execute the query & transform if required
		$wc_orders_meta_delete_count = $wpdb->query( $query );

		// Ran into an error
		if ( $wpdb->last_error ) {

			// Log the error
			wpdai_write_log( 'Error trying to delete all order meta overrides from the WC order meta table.', 'db_error' );
			wpdai_write_log( $wpdb->last_error, 'db_error' );

			// Return a fail
			return false;

		}

		// Add to the total
		if ( is_numeric($post_meta_delete_count) ) $result += $wc_orders_meta_delete_count; 

	}

	// Return the count of effected rows
	return $result;

}

/**
 * 
 * 	Stores the order calculation in custom WP Table
 * 
 * 	@param int The Order ID
 * 	@param array Expects the order calculation as part of wpdai_calculate_cost_profit_by_order
 * 
 * 	@return bool|int Returns false on failure, or the number of updated rows if correct
 * 
 **/
function wpdai_set_order_calculations_cache( $order_id, $order_calculation ) {

	// Check the order id
	if ( ! is_numeric($order_id) ) return false;

	$order_id = intval( $order_id );

	// Check if we've got the right data
	if ( ! is_array($order_calculation) || ! isset($order_calculation['order_id']) ) return false;

	// Load DB Interactor
	$db_interactor = new WPDAI_Database_Interactor();
	$order_calculations_table = $db_interactor->order_calculations_table;

	// Set last updated to GMT timestamp
	$last_updated_gmt = current_time( WPD_AI_PHP_ISO_DATETIME, true );

	// Insert or update row
	$updated_rows = $db_interactor->insert_update_row(

		// Table Name
		$order_calculations_table, 

		// Data
		array(
			'order_id' => $order_id, 
			'order_calculation' => serialize( $order_calculation ),
			'calculation_last_updated_gmt' => $last_updated_gmt
		),
		
		// Where Condition
		array( 'order_id' => $order_id )

	);

	// Failure
	if ( $updated_rows === false ) {
		return false;
	}
	
	// Store in memory
	wp_cache_set( $order_id, $order_calculation, '_wpd_ai_order_cache' );

	// Count of updated / inserted rows
	return $updated_rows;

}

/**
 * 
 * 	Fetches the order calculation cache from the WP Object Cache, if set
 * 	Otherwise will query the custom calculations cache DB table directly
 * 
 * 	@param int $order_id The order id to query
 * 	@return bool|array Will return false on failure, otherwise the array for the order calculation
 * 
 **/
function wpdai_get_order_calculation_cache( $order_id ) {

	// Safety Check
	if ( ! is_numeric($order_id) ) return false;

	// Check the cache
	$cached_result = wp_cache_get( $order_id, '_wpd_ai_order_cache' );

	// Return the results in the cache
	if ( $cached_result !== false ) {
		
		// Format into array
		$result = maybe_unserialize($cached_result);

		// If format looks good, return the result
		if ( is_array($result) && ! empty($result) ) return $result;

	}

	// Load the DB Interactor
	$db_interactor = new WPDAI_Database_Interactor();
	$order_calculations_table = $db_interactor->order_calculations_table;

	// Load WPDB
	global $wpdb;

	// Sanitize query
	$sql_query = $wpdb->prepare( "SELECT order_calculation FROM $order_calculations_table WHERE order_id = %d", $order_id );

	// Fetch result
	$result = $wpdb->get_var( $sql_query );

	// Check for DB errors
	if ( $wpdb->last_error ) {

		$result = $wpdb->last_error;
		wpdai_write_log( 'Error pulling the order meta for order id' . $order_id, 'db_error' );
		wpdai_write_log( $result, 'db_error' );
		return false;

	}

	// Something has gone wrong
	if ( is_null($result) ) return false;

	// Format into array
	$result = maybe_unserialize($result);

	// If not correct, something has gone wrong.
	if ( ! is_array($result) ) return false;

	// Save in cache if we got it
	wp_cache_set( $order_id, $result, '_wpd_ai_order_cache' );

	// Return format
	return $result;

}

/**
 * 
 * 	Returns an array of Order ID's that do not have a cache stored in the custom calculations table
 * 	If a limit is supplied, it will return the most recent n results
 * 	This is the old version, which checks against order meta. Have moved to custom table
 * 
 * 	@param $limit The number of results to return, will default to all
 * 	@return array An array of order IDs, empty if none found
 * 
 **/
function wpdai_get_order_ids_without_calculation_cache( $limit = -1 ) {

	// Get all Order IDS
	$all_order_ids = (array) wpdai_get_all_order_ids();

	// Get Order IDS that have a cache set
	$order_ids_with_cache = (array) wpdai_get_order_ids_with_calculation_cache();

	// Get the difference between the two (uncached)
	$without_cache = array_diff( $all_order_ids, $order_ids_with_cache );

	if ( is_numeric($limit) && $limit > 0 ) $without_cache = array_slice( $without_cache, 0, $limit );

	// Return results
	return $without_cache;

}

/**
 * 
 * 	Returns an array of Order ID's that do not have a cache stored
 * 
 * 	@return array|bool An array of order IDs, empty if none found, false on failure
 * 
 **/
function wpdai_get_order_ids_with_calculation_cache() {

	// Load WPDB
	global $wpdb;

	// Load the DB Interactor
	$db_interactor = new WPDAI_Database_Interactor();
	$order_calculations_table = $db_interactor->order_calculations_table;

	// Sanitize query
	$sql_query = $wpdb->prepare( "SELECT order_id FROM $order_calculations_table" );

	// Fetch result
	$result = $wpdb->get_col( $sql_query );

	// Check for DB errors
	if ( $wpdb->last_error ) {

		wpdai_write_log( 'Error getting the list of order IDs with a calculation cache', 'db_error' );
		wpdai_write_log( $wpdb->last_error, 'db_error' );

		return false;

	}

	// Return the result
	return $result;

}

/**
 * 
 * 	This function will call all order calculations set in custom calculations table
 * 	in a single SQL Query, and store them in the object cache with key $order_id, $group = _wpd_ai_order_cache
 * 
 *  @param array An array of order IDs, if none passed in we'll search for all of them
 * 	@return int|bool Will return the count of orders stored in cache, or false on failure
 * 
 **/
function wpdai_setup_order_calculations_in_object_cache( $order_ids = array() ) {

	// return true;

	// Object cache: https://developer.wordpress.org/reference/classes/wp_object_cache/

	// Load WPDB
	global $wpdb;

	// Load the DB Interactor
	$db_interactor = new WPDAI_Database_Interactor();
	$order_calculations_table = $db_interactor->order_calculations_table;

	// Load only the order IDs we would like if relevant
	if ( is_array($order_ids) && ! empty($order_ids) ) {

		// Convert array into comma seperated values
		$order_ids = $db_interactor->convert_array_to_in_statement_int( $order_ids );

		// Fetch all results
		$result = $wpdb->get_results( "SELECT order_id, order_calculation FROM $order_calculations_table WHERE order_id IN ($order_ids)", ARRAY_A );

	} else {
		
		// Fetch all results
		$result = $wpdb->get_results( "SELECT order_id, order_calculation FROM $order_calculations_table", ARRAY_A );

	}

	// Check for DB errors
	if ( $wpdb->last_error ) {

		wpdai_write_log( 'Error setting order calculations in the object cache.', 'db_error' );
		wpdai_write_log( $wpdb->last_error, 'db_error' );

		return false;

	}

	// Fact check the format
	if ( ! is_array($result) ) {
		return false;
	}

	// Set in the object cache
	foreach( $result as $row ) wp_cache_set( $row['order_id'], $row['order_calculation'], '_wpd_ai_order_cache' );

	// Return the count of records cached
	return count( $result );

}

/**
 * 
 * 	This function will call all order calculations set in custom calculations table
 * 	in a single SQL Query, and store them in the object cache with key $order_id, $group = _wpd_ai_order_cache
 * 
 *  @param array An array of order IDs, if none passed in we'll search for all of them
 * 	@return int|bool Will return the count of orders stored in cache, or false on failure
 * 
 **/
function wpdai_delete_order_calculations_in_object_cache( $order_ids = array() ) {

	// Safety check
	if ( ! is_array($order_ids) || empty($order_ids) ) return false;

	// Set in the object cache
	foreach( $order_ids as $order_id ) wp_cache_delete( $order_id, '_wpd_ai_order_cache' );

	// Return the count of records cached
	return count( $order_ids );

}