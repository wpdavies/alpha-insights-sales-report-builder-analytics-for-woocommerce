<?php
/**
 *
 * Data Fetch Related Functions
 * Typically used to fetch specific or small groups of analytics / calculations or data from the DB
 *
 * @package Alpha Insights
 * @version 5.0.0
 * @since 3.2.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;


/**
 * 
 * 	Calculates whether this order is the first order for a particular customer
 * 	Will check the order's date created and search backward to see if any other orders have been placed by this email address (so that we are checking guests too)
 * 
 * 	@author Christopher Davies -> WP Davies
 * 	@link https://wpdavies.dev/woocommerce-new-vs-returning-customers/
 *  @param WC_Order|int $order Order object or order_id
 * 	@param bool $use_cache, Defaults to True, will check transients, if not set we will force a recalculation
 *  @return bool $bool True if this is this customer's first order, false if they've ordered prior to this order date
 * 
 **/
function wpdai_customers_first_order( $order, $use_cache = true ) {
	
	// Check if we've passed an Order ID
	if ( is_int($order) && $order > 0 ) {
		$order = wc_get_order( $order );
	}

	// Safety check
	if ( ! is_a( $order, 'WC_Order' ) ) return false;

	// Transient Management
	if ( $use_cache ) {

		$order_id 		= $order->get_id();
		$transient_key 	= '_wpd_customer_first_order_id_' . $order_id;
		$cached_result 	= get_transient( $transient_key );
		if ( is_numeric( $cached_result ) ) return $cached_result;
		
	}

	// Date created
	$date_created =  $order->get_date_created();
	if ( is_a($date_created, 'WC_DateTime') ) {
		$date_created = $date_created->getTimestamp(); // Must use gettimestamp not getoffsettimestamp
	} else {
		return false; // Can't check a date?
	}

	// Unique Identifier
	$billing_email = $order->get_billing_email();

	// New customer if they haven't got a billing email?
	if ( empty($billing_email) ) {
		return true;
	}

	// Search for orders with the same billing address that are older than this order
	$args = array(
		'limit' 		=> 1,
		'billing_email' => $billing_email,
		'date_created' 	=> '<' . $date_created, // This must be in UTC -> causing issues when we compare against offset timestamp
		'return' 		=> 'ids',
		'status' 		=> wpdai_paid_order_statuses()
	);

	// Search for orders
	$orders = wc_get_orders( $args );

	// Store result as var so we can update transients
	$result = false;

	if ( empty($orders) ) {
		$result = true; // No orders found, this must be the first
	}

	//  30 Days
	$expiration = 86400 * 30;

	// Update transient -> No expiry set. Store as 1 or 0
	set_transient( $transient_key, (int) $result, $expiration );

	// Return result
	return $result;

}

/**
 * 
 * 	Calculates whether this order is the first order for a particular customer
 * 	Will check the order's date created and search backward to see if any other orders have been placed by this email address (so that we are checking guests too)
 * 
 *  @param string $email_address The email address to check for orders
 *  @return int Count of orders found from the email address
 *  @author WP Davies - Christopher Davies
 *  @since 2.0.50
 *
 **/
function wpdai_customer_order_ids_by_email_address( string $email_address ) {

	// Safety check
	if ( empty($email_address) || ! is_string($email_address) ) return 0;

	$args = array(
		'limit' 		=> -1,
		'billing_email' => $email_address,
		'status' 		=> wpdai_paid_order_statuses(),
		'return' 		=> 'ids',
	);

	$orders = wc_get_orders( $args );

	return $orders;

}

/**
 * 
 * 	Calculates whether this order is the first order for a particular customer
 * 	Will check the order's date created and search backward to see if any other orders have been placed by this email address (so that we are checking guests too)
 * 
 *  @param string $email_address The email address to check for orders
 *  @return int Count of orders found from the email address
 *  @author WP Davies - Christopher Davies
 *  @since 2.0.50
 * 	@todo cache this with transients
 *
 **/
function wpdai_customer_order_count_by_email_address( string $email_address ) {

	// Safety check
	if ( empty($email_address) || ! is_string($email_address) ) return 0;

	$orders = wpdai_customer_order_ids_by_email_address( $email_address );

	if ( is_array($orders) && ! empty($orders) ) {
		return count($orders);
	} else {
		return 0;
	}

}

/**
 * 
 * 	Get total lifetime value of a customer by their User ID
 * 
 * 	Will check the orders from the user_id and merge that with a check on the email address
 *  in case they checked out as a guest
 * 
 *  @param int $user_id The User ID
 *  @return float The total revenue reported from WooCommerce against the User ID
 *  @author WP Davies - Christopher Davies
 *  @since 2.0.50
 * 
 **/
function wpdai_customer_lifetime_value_by_email_address( string $email_address ) {

	$lifetime_value = (float) 0;
	$customer_order_ids = wpdai_customer_order_ids_by_email_address( $email_address );

	if ( is_array($customer_order_ids) && ! empty($customer_order_ids) ) {

		foreach( $customer_order_ids as $order_id ) {

			$order = wc_get_order( $order_id );

			if ( ! is_a($order, 'WC_Order') ) continue; 

			$order_revenue = $order->get_total();
			$lifetime_value += $order_revenue;

		}

		return $lifetime_value;

	} else {

		return $lifetime_value;

	}

}

/**
 * 
 * 	Get total lifetime value of a customer by their User ID
 * 
 * 	Will check the orders from the user_id and merge that with a check on the email address
 *  in case they checked out as a guest
 * 
 *  @param int $user_id The User ID
 *  @return float The total revenue reported from WooCommerce against the User ID
 *  @author WP Davies - Christopher Davies
 *  @since 2.0.50
 * 
 **/
function wpdai_customer_average_order_value_by_email_address( string $email_address) {

	$order_count = wpdai_customer_order_count_by_email_address( $email_address );
	$lifetime_value = wpdai_customer_lifetime_value_by_email_address( $email_address );

	$aov = wpdai_divide( $lifetime_value, $order_count );
	return $aov;

}

/**
 * 
 * 	Get all order_ids found under the User ID
 * 
 * 	Will check the orders from the user_id and merge that with a check on the email address
 *  in case they checked out as a guest
 * 
 *  @param int $user_id The User ID
 *  @return array Array of Order Ids, empty array if nothing found
 *  @author WP Davies - Christopher Davies
 *  @since 2.0.50
 * 
 **/
function wpdai_customer_order_ids_by_user_id( int $user_id ) {

	// Safety check
	if ( ! is_numeric($user_id) || $user_id === 0 ) return 0;

	// Get orders by user_id
	$args = array(
		'limit' 		=> -1,
		'customer_id' 	=> $user_id,
		'return' 		=> 'ids',
		'type' 			=> array( 'shop_order' ),
		'status' 		=> wpdai_paid_order_statuses()
	);

	$orders = wc_get_orders( $args );

	if ( is_array($orders) && ! empty($orders) ) {

		$user_id_orders = $orders;

	} else {

		$user_id_orders = array();

	}

	// // Now search by their email address in case they checkout out as a guest
	// $user = get_userdata( $user_id );
	// if ( is_a( $user, 'WP_User' ) ) {

	// 	$email_address = $user->user_email;

	// 	if ( is_string($email_address) && ! empty($email_address) ) {

	// 		// Get orders by email address
	// 		$args = array(
	// 			'limit' 		=> -1,
	// 			'billing_email' => $email_address,
	// 			'return' 		=> 'ids',
	// 			'type' 			=> array( 'shop_order' ),
	// 			'status' 		=> wpdai_paid_order_statuses()
	// 		);
	// 		$orders = wc_get_orders( $args );
	// 		if ( is_array($orders) && ! empty($orders) ) {
	// 			$email_address_orders = $orders;
	// 		} else {
	// 			$email_address_orders = array();
	// 		}

	// 		// Now merge the two for unique id's, and return the results.
	// 		$merged_order_ids = array_unique( array_merge( $email_address_orders, $user_id_orders ) );

	// 		// Return unique merged count
	// 		return $merged_order_ids;

	// 	} else {

	// 		// Return user id orders, email address check failed
	// 		return $user_id_orders;

	// 	}

	// } else {

	// 	// Return user id orders, user check failed
	// 	return $user_id_orders;

	// }

	return $user_id_orders;

}

/**
 * 
 * 	Get total order count by User ID
 * 
 * 	Will check the orders from the user_id and merge that with a check on the email address
 *  in case they checked out as a guest
 * 
 *  @param int $user_id The User ID
 *  @return int Count of orders, returns 0 if we found nothing
 *  @author WP Davies - Christopher Davies
 *  @since 2.0.50
 * 
 **/
function wpdai_customer_order_count_by_user_id( int $user_id ) {

	$customer_order_ids = wpdai_customer_order_ids_by_user_id( $user_id );

	if ( is_array($customer_order_ids) && ! empty($customer_order_ids) ) {

		return count($customer_order_ids);

	} else {

		return 0;

	}

}

/**
 * 
 * 	Get total lifetime value of a customer by their User ID
 * 
 * 	Will check the orders from the user_id and merge that with a check on the email address
 *  in case they checked out as a guest
 * 
 *  @param int $user_id The User ID
 *  @return float The total revenue reported from WooCommerce against the User ID
 *  @author WP Davies - Christopher Davies
 *  @since 2.0.50
 * 
 **/
function wpdai_customer_lifetime_value_by_user_id( int $user_id ) {

	$lifetime_value = (float) 0;
	$customer_order_ids = wpdai_customer_order_ids_by_user_id( $user_id );

	if ( is_array($customer_order_ids) && ! empty($customer_order_ids) ) {

		foreach( $customer_order_ids as $order_id ) {

			$order = wc_get_order( $order_id );

			if ( ! is_a($order, 'WC_Order') ) continue; 

			$order_revenue = $order->get_total();
			$lifetime_value += $order_revenue;

		}

		return $lifetime_value;

	} else {

		return $lifetime_value;

	}

}

/**
 * 
 * 	Fetches all standard customer statistics relating to transactional data
 * 
 * 	@return array An array of data
 * 
 **/
function wpdai_get_customer_transaction_statistics_by_user_id( $user_id ) {

	// Defaults
	$customer_behaviour = array(
		'order_count' => 0,
		'lifetime_value' => 0,
		'average_order_value' => 0,
		'order_ids' => 0
	);

	// Safety Check
	if ( ! is_numeric($user_id) || $user_id < 1 ) return $customer_behaviour;

	$lifetime_value 		= 0;
	$order_count 			= 0;
	$average_order_value 	= 0;
	$customer_order_ids 	= wpdai_customer_order_ids_by_user_id( $user_id );

	if ( is_array($customer_order_ids) && ! empty($customer_order_ids) ) {

		foreach( $customer_order_ids as $order_id ) {

			$order = wc_get_order( $order_id );

			if ( ! is_a($order, 'WC_Order') ) continue; 

			$order_revenue = $order->get_total();
			$lifetime_value += $order_revenue;

		}

		// Calcs
		$order_count = count( $customer_order_ids );
		$average_order_value = wpdai_divide( $lifetime_value, $order_count );

		// Update values
		$customer_behaviour['order_count'] = $order_count;
		$customer_behaviour['lifetime_value'] = $lifetime_value;
		$customer_behaviour['average_order_value'] = $average_order_value;
		$customer_behaviour['order_ids'] = $customer_order_ids;

	}

	return $customer_behaviour;

}

/**
 * 
 * 	Get total lifetime value of a customer by their User ID
 * 
 * 	Will check the orders from the user_id and merge that with a check on the email address
 *  in case they checked out as a guest
 * 
 *  @param int $user_id The User ID
 *  @return float The total revenue reported from WooCommerce against the User ID
 *  @author WP Davies - Christopher Davies
 *  @since 2.0.50
 * 
 **/
function wpdai_customer_average_order_value_by_user_id( int $user_id ) {

	$order_count = wpdai_customer_order_count_by_user_id( $user_id );
	$lifetime_value = wpdai_customer_lifetime_value_by_user_id( $user_id );

	$aov = wpdai_divide( $lifetime_value, $order_count );
	return $aov;

}

/**
 * 
 * Fetch all data from a session using a session id
 * 
 * @param string $session_id
 * @return array with key value pairs e.g. page_views, etc etc or empty array if no data found
 * 
 */
function wpdai_fetch_session_data( $session_id ) {

	// Safety check
	if ( ! is_string($session_id) || empty($session_id) ) return array();

	$filter = array(
		'date_preset' => 'all_time',
		'data_filters' => array(
			'website_traffic' => array(
				'session_id' => array( $session_id )
			)
		)
	);
	$wpd_data = new WPDAI_Data_Warehouse( $filter );
	$wpd_data->fetch_analytics_data();
	$session_tables = $wpd_data->get_data('analytics', 'data_table');

	// Sanity check
	if ( ! is_array($session_tables) || ! isset($session_tables['sessions']) || ! is_array($session_tables['sessions']) ) return array();

	// Otherwise return the results
	$session = reset( $session_tables['sessions'] );
	return $session;

}

/**
 * 
 * Get the total count of sessions by User ID
 * 
 * @param int $user_id The user id to check in the analytics DB
 * @return int|false Count of sessions found for User ID or false on error
 * 
 */
function wpdai_get_session_count_by_user_id( $user_id ) {

	// Safety check
	if ( ! is_numeric($user_id) || empty($user_id) ) return false;

	$filter = array(
		'date_preset' => 'all_time',
		'data_filters' => array(
			'website_traffic' => array(
				'user_id' => array( $user_id )
			)
		)
	);
	$wpd_data = new WPDAI_Data_Warehouse( $filter );
	$session_data = $wpd_data->get_analytics_session_count();

	return $session_data;

}

/**
 * 
 * Get the total count of sessions by Ip Address
 * 
 * @param string $ip_address The IP Address to check in the analytics DB
 * @return int|false Count of sessions found for Ip Address or false on error
 * 
 */
function wpdai_get_session_count_by_ip_address( $ip_address ) {

	// Safety check
	if ( ! is_string($ip_address) || empty($ip_address) ) return false;

	$filter = array(
		'date_preset' => 'all_time',
		'data_filters' => array(
			'website_traffic' => array(
				'ip_address' => array( $ip_address )
			)
		)
	);
	$wpd_data = new WPDAI_Data_Warehouse( $filter );
	$session_data = $wpd_data->get_analytics_session_count();

	return $session_data;

}

/**
 * 
 * 	Fetches single product stat
 * 
 * 	@param string $product_stat
 * 	@param int $product_id
 * 
 * 	@return int The Result, default null on nothing
 * 
 */
function wpdai_get_product_statistic( $event_type, $product_id ) {

	// Convert non-standard event
	if ( $event_type === 'product_page_view' ) $event_type = 'page_view';

	$filter = array(
		'date_preset' => 'all_time',
		'data_filters' => array(
			'website_traffic' => array(
				'product_id' => array( $product_id ),
				'event_type' => array( $event_type )
			)
		)
	);
	$wpd_data = new WPDAI_Data_Warehouse( $filter );
	return $wpd_data->get_analytics_event_count();

}

/**
 * 
 * 	Fetches product analytics directly from DB, used for calculating actuals
 * 
 * 	@param int $product_id
 *  @param int $variation_id (Optional)
 * 	@param bool $cache, whether to check against transients & product meta or not
 * 	@return array
 * 
 */
function wpdai_fetch_product_analytics_by_product_id( $product_id = 0, $cache = true ) {

	// Safety check
	if ( ! is_numeric($product_id) || $product_id < 1 ) {
		return false;
	}

	// Key used for transient fetching
	$transient_key 	= '_wpd_product_statistics_' . $product_id;
	$transient_expiry = 60 * 60; // 1 hour

	// Transient Management
	if ( $cache ) {

		// Check transients first
		$cached_result 	= get_transient( $transient_key );
		if ( is_array( $cached_result ) ) return $cached_result;

		// Otherwise check post meta
		$post_meta = get_post_meta( $product_id, '_wpd_ai_product_analytics', true );
		if ( is_array( $post_meta ) ) return $post_meta;
		
	}

	$sales_data 					= wpdai_collect_product_sales_data_db_direct( $product_id );
	$product_page_views 			= (int) wpdai_get_product_statistic( 'page_view', $product_id );
	$product_cat_page_clicks 		= (int) wpdai_get_product_statistic( 'product_click', $product_id );
	$product_add_to_carts 			= (int) wpdai_get_product_statistic( 'add_to_cart', $product_id );
	$product_purchases_wpd_ai 		= (int) wpdai_get_product_statistic( 'product_purchase', $product_id );
	$product_qty_purchased_wpd_ai 	= (int) 0;
	$wc_total_qty_purchased 		= (int) $sales_data['total_qty_sold'];
	$total_revenue_pre_discount 	= (float) $sales_data['total_revenue_pre_discount'];
	$total_revenue_post_discount	= (float) $sales_data['total_revenue_post_discount'];
	$average_price_pre_discount		= (float) $sales_data['average_price_pre_discount'];
	$average_price_post_discount	= (float) $sales_data['average_price_post_discount'];
	$total_profit					= (float) $sales_data['total_profit'];

	$product_analytics = array(

		'product_id' 								=> $product_id,
		'product_page_views' 						=> $product_page_views,
		'product_cat_page_clicks' 					=> $product_cat_page_clicks,
		'product_add_to_carts' 						=> $product_add_to_carts,
		'product_purchases' 						=> $product_purchases_wpd_ai,
		'product_qty_purchased' 					=> $product_qty_purchased_wpd_ai,
		'page_view_to_atc_conversion_rate' 			=> wpdai_calculate_percentage( $product_add_to_carts, $product_page_views, 2 ),
		'page_view_to_purchase_conversion_rate' 	=> wpdai_calculate_percentage( $product_purchases_wpd_ai, $product_page_views, 2 ),
		'atc_to_purchase_conversion_rate' 			=> wpdai_calculate_percentage( $product_purchases_wpd_ai, $product_add_to_carts, 2 ),
		'wc_total_qty_purchased' 					=> $wc_total_qty_purchased,
        'total_revenue_pre_discount' 				=> $total_revenue_pre_discount,
        'total_revenue_post_discount' 				=> $total_revenue_post_discount,
        'average_price_pre_discount' 				=> $average_price_pre_discount,
        'average_price_post_discount' 				=> $average_price_post_discount,
		'total_profit' 								=> $total_profit,

	);

	// Save these values to the product -> we will update these twice daily in Cron
	update_post_meta( $product_id, '_wpd_ai_product_analytics', $product_analytics );

	// Set last updated
	update_post_meta( $product_id, '_wpd_ai_last_updated', current_time('timestamp', true) ); // Saved in GMT timestamp

	// Save these values for one hour
	set_transient( $transient_key, $product_analytics, $transient_expiry );

	// Return the raw results
	return $product_analytics;

}

/**
 * 
 * Fetches some product sales data
 * 
 * @param int $product_id
 * @return array $product_data
 * 
 */
function wpdai_collect_product_sales_data_db_direct( $product_id ) {

    $product_data = array(

        'total_qty_sold' => 0,
        'total_revenue_pre_discount' => 0,
        'total_revenue_post_discount' => 0,
        'average_price_pre_discount' => 0,
        'average_price_post_discount' => 0,
		'total_revenue' => 0,
		'total_profit' => 0,
		'unit_cost_price' => 0

    );

    global $wpdb;

	$item_meta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
	$order_item_table = $wpdb->prefix . 'woocommerce_order_items';
	$posts_table = $wpdb->prefix . 'posts';
	$hpos_orders_table = $wpdb->prefix . 'wc_orders';

	// Validate table names are safe (constructed from trusted prefix + known strings)
	// WordPress's $wpdb->prepare() doesn't support %i placeholder, so we validate and use direct concatenation
	$item_meta_table = esc_sql( $item_meta_table );
	$order_item_table = esc_sql( $order_item_table );
	$posts_table = esc_sql( $posts_table );
	$hpos_orders_table = esc_sql( $hpos_orders_table );

	if ( wpdai_is_hpos_enabled() ) {

		// Note: Table names are validated above, product_id is prepared with %d
		$sql_query = $wpdb->prepare("
			SELECT *
			FROM {$item_meta_table} AS item_meta
			WHERE item_meta.order_item_id IN (
				SELECT order_items.order_item_id
				FROM {$order_item_table} AS order_items
				LEFT JOIN {$item_meta_table} AS item_meta ON order_items.order_item_id = item_meta.order_item_id
				LEFT JOIN {$hpos_orders_table} AS post ON order_items.order_id = post.id
				where order_items.order_item_type = 'line_item'
				AND item_meta.meta_key = '_product_id'
				AND item_meta.meta_value = %d
				AND post.status IN ('wc-completed', 'wc-processing')
			)
			AND item_meta.meta_key IN ('_qty', '_line_total', '_line_subtotal')
			ORDER BY item_meta.order_item_id DESC",
			absint( $product_id )
		);

	} else {

		// Note: Table names are validated above, product_id is prepared with %d
		$sql_query = $wpdb->prepare("
			SELECT *
			FROM {$item_meta_table} AS item_meta
			WHERE item_meta.order_item_id IN (
				SELECT order_items.order_item_id
				FROM {$order_item_table} AS order_items
				LEFT JOIN {$item_meta_table} AS item_meta ON order_items.order_item_id = item_meta.order_item_id
				LEFT JOIN {$posts_table} AS post ON order_items.order_id = post.id
				where order_items.order_item_type = 'line_item'
				AND item_meta.meta_key = '_product_id'
				AND item_meta.meta_value = %d
				AND post.post_status IN ('wc-completed', 'wc-processing')
			)
			AND item_meta.meta_key IN ('_qty', '_line_total', '_line_subtotal')
			ORDER BY item_meta.order_item_id DESC",
			absint( $product_id )
		);

	}

	$results = $wpdb->get_results( $sql_query, ARRAY_A );

    // DB Error
    if ( $wpdb->last_error ) {

      wpdai_write_log( $wpdb->last_error, 'db_error' );
      return $product_data;

    }

    // Process data
    if ( is_array($results) && ! empty($results) ) {
        foreach( $results as $line_item_array ) {

            $line_item_key = $line_item_array['meta_key'];
            $line_item_value = $line_item_array['meta_value'];

            // Sum the qty
            if ( $line_item_key == '_qty' ) {

                $product_data['total_qty_sold'] += $line_item_value;

            } 

            // Sum the subtotal
            if ( $line_item_key == '_line_subtotal' ) {

                $product_data['total_revenue_pre_discount'] += $line_item_value;

                if ( $product_data['total_revenue_pre_discount'] && $product_data['total_qty_sold'] ) {
                    $product_data['average_price_pre_discount'] = wpdai_divide( $product_data['total_revenue_pre_discount'], $product_data['total_qty_sold'], 2);
                }

            } 

            // Sum the final price
            if ( $line_item_key == '_line_total' ) {

                $product_data['total_revenue_post_discount'] += $line_item_value;

                if ( $product_data['total_revenue_post_discount'] && $product_data['total_qty_sold'] ) {
                    $product_data['average_price_post_discount'] = wpdai_divide( $product_data['total_revenue_post_discount'], $product_data['total_qty_sold'], 2);
                }

            }

        }

		// And a few calculations
		$total_revenue = (float) $product_data['total_revenue_post_discount'];
		$unit_cost_price = wpdai_get_cost_price_by_product_id( $product_id );
		$total_cost = $unit_cost_price * $product_data['total_qty_sold'];
		$total_profit = (float) $total_revenue - $total_cost;
		$product_data['total_revenue'] = $total_revenue;
		$product_data['total_profit'] = $total_profit;
		$product_data['unit_cost_price'] = $unit_cost_price;

    }

    return $product_data;

}

/**
 * 
 * 	Fetches array of useful information by User ID
 * 
 * 	@return array|false $customer_analytics An array of data relating to this customer's behaviour, returns false if a bad user_id has been passed in
 * 
 **/
function wpdai_fetch_customer_analytics_by_user_id( $user_id, $cache = true ) {

	// Safety Check for correct data
	if ( ! is_numeric($user_id) || $user_id < 1  ) return false;

	// Set transient keys
	$transient_key 	= '_wpd_user_analytics_' . $user_id;
	$transient_expiry = 60 * 60; // 1 hour

	// Transient Management
	if ( $cache ) {

		// Check transients first
		$cached_result 	= get_transient( $transient_key );
		if ( is_array( $cached_result ) ) return $cached_result;

		// Otherwise check post meta
		$post_meta = get_user_meta( $user_id, '_wpd_user_analytics', true );
		if ( is_array( $post_meta ) ) return $post_meta;
		
	}

	// Check for user
	$user = get_userdata( $user_id );

	// Make sure this user exists
	if ( ! is_a( $user, 'WP_User' ) ) return false;

	// Core data
	$user_id 					= $user->ID;
	$first_name 				= $user->user_firstname;
	$last_name 					= $user->user_lastname;
	$email_address 				= $user->user_email;
	$registration_date 			= strtotime( get_date_from_gmt( $user->user_registered ) );
	$registration_date_pretty 	= ( is_numeric($registration_date) && $registration_date > 0 ) ? gmdate( WPD_AI_PHP_PRETTY_DATETIME, $registration_date ) : null;
	$full_name 					= $first_name . ' ' . $last_name;
	$roles 						= $user->roles;

	// Registration Data
	$registration_session_landing_page 	= (string) get_user_meta( $user_id, '_wpd_ai_landing_page', true );
	$registration_session_referral_url 	= (string) get_user_meta( $user_id, '_wpd_ai_referral_source', true );
	$registration_url 					= (string) get_user_meta( $user_id, '_wpd_ai_registration_url_current', true );
	$registration_url_referral 			= (string) get_user_meta( $user_id, '_wpd_ai_registration_url_referral', true );
	$last_login_date 		 			= get_user_meta( $user_id, '_wpd_ai_last_login_unix', true );
	$last_login_date_pretty 			= ( is_numeric($last_login_date) && $last_login_date > 0 ) ? get_date_from_gmt( gmdate( WPD_AI_PHP_ISO_DATETIME, $last_login_date ), WPD_AI_PHP_PRETTY_DATETIME) : null;

	// Calculate session source
	$query_params 						= wpdai_get_query_params( $registration_session_landing_page );
	$traffic_source 					= wpdai_get_traffic_type( $registration_session_referral_url, $query_params );

	// Expensive analytics calculations
	$session_count 						= wpdai_get_session_count_by_user_id( $user_id );
	$transactional_data 				= wpdai_get_customer_transaction_statistics_by_user_id( $user_id );
	$order_count 						= $transactional_data['order_count'];
	$lifetime_value 					= $transactional_data['lifetime_value'];
	$average_order_value 				= $transactional_data['average_order_value'];
	$order_ids 							= $transactional_data['order_ids'];
	$conversion_rate 					= wpdai_calculate_percentage( $order_count, $session_count );

	// Build Payload
	$customer_analytics = array(

		'user_id' 					=> $user_id,
		'first_name' 				=> $last_name,
		'last_name' 				=> $first_name,
		'display_name' 				=> $full_name,
		'email_address' 			=> $email_address,
		'registration_date_unix' 	=> $registration_date,
		'registration_date_pretty' 	=> $registration_date_pretty,
		'last_login_date_unix' 		=> $last_login_date,
		'last_login_date_pretty' 	=> $last_login_date_pretty,
		'registration_url' 			=> $registration_url, 					// The actual sign up url
		'registration_referral_url' => $registration_url_referral, 			// The page before signing up
		'landing_page_url' 			=> $registration_session_landing_page, 	// The landing page of the session
		'referral_source' 			=> $traffic_source, 					// The referral source for the actual session
		'total_session_count' 		=> $session_count,
		'total_order_count' 		=> $order_count,
		'lifetime_value' 			=> $lifetime_value,
		'average_order_value' 		=> $average_order_value,
		'conversion_rate' 			=> $conversion_rate,
		'order_ids' 				=> $order_ids,
		'user_roles' 				=> $roles,

	);

	// Save these values to the product -> we will update these twice daily in Cron
	update_user_meta( $user_id, '_wpd_user_analytics', $customer_analytics );

	// Set last updated
	update_user_meta( $user_id, '_wpd_ai_last_updated', current_time('timestamp', true) ); // Saved in GMT timestamp

	// Save these values for one hour
	set_transient( $transient_key, $customer_analytics, $transient_expiry );

	// Return Results
	return $customer_analytics;

}

/**
 * Get all WooCommerce order IDs that contain certain product IDs.
 *
 * Works with both HPOS (High Performance Order Storage) and legacy (posts) systems.
 *
 * @param array $product_ids Array of product IDs to match.
 * @return array Array of matching order IDs.
 */
function wpdai_get_order_ids_by_product_ids( $product_ids = [] ) {
	
    if ( empty( $product_ids ) || ! is_array( $product_ids ) ) {
        return [];
    }

    global $wpdb;

    // Detect if HPOS is enabled
    $is_hpos_enabled = wpdai_is_hpos_enabled();

    if ( $is_hpos_enabled ) {
        // ✅ HPOS query
        $order_itemmeta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
        $order_items_table    = $wpdb->prefix . 'woocommerce_order_items';
        $orders_table         = $wpdb->prefix . 'wc_orders';

        $placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );

        $query = $wpdb->prepare(
            "
            SELECT DISTINCT o.id
            FROM $orders_table o
            INNER JOIN $order_items_table oi ON o.id = oi.order_id
            INNER JOIN $order_itemmeta_table oim ON oi.order_item_id = oim.order_item_id
            WHERE (oim.meta_key = '_product_id' OR oim.meta_key = '_variation_id')
            AND oim.meta_value IN ($placeholders)
            ",
            $product_ids
        );

        $order_ids = $wpdb->get_col( $query );

    } else {
        // ✅ Legacy query (posts-based)
        $posts_table           = $wpdb->posts;
        $order_items_table     = $wpdb->prefix . 'woocommerce_order_items';
        $order_itemmeta_table  = $wpdb->prefix . 'woocommerce_order_itemmeta';

        $placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );

        $query = $wpdb->prepare(
            "
            SELECT DISTINCT p.ID
            FROM $posts_table p
            INNER JOIN $order_items_table oi ON p.ID = oi.order_id
            INNER JOIN $order_itemmeta_table oim ON oi.order_item_id = oim.order_item_id
            WHERE p.post_type = 'shop_order'
            AND (oim.meta_key = '_product_id' OR oim.meta_key = '_variation_id')
            AND oim.meta_value IN ($placeholders)
            ",
            $product_ids
        );

        $order_ids = $wpdb->get_col( $query );
    }

    return array_map( 'intval', $order_ids );
	
}

/**
 *
 *	Get all post meta keys
 *
 */
function wpdai_product_meta_keys() {

	global $wpdb;
	$query = "
		SELECT DISTINCT($wpdb->postmeta.meta_key) 
		FROM $wpdb->posts 
		LEFT JOIN $wpdb->postmeta 
		ON $wpdb->posts.ID = $wpdb->postmeta.post_id 
		WHERE $wpdb->posts.post_type IN ('product', 'product_variation')
	";
	$meta_keys = $wpdb->get_col( $query );
	set_transient('wpdai_product_meta_keys', $meta_keys, 60*60*24); # create 1 Day Expiration
	return $meta_keys;

}

/**
 *
 *	Collect product IDS from SQL Query
 *
 */
function wpdai_get_product_meta_keys() {

	$cache = get_transient('wpdai_product_meta_keys');
	$meta_keys = $cache ? $cache : wpdai_product_meta_keys();
	return $meta_keys;

}


/**
 * 
 * 	Grabs the last N product ID's that need an update 
 * 
 * 	@return array An array of product ID's that are next in line for updating
 * 
 **/
function wpdai_query_product_ids_for_analytics_collector( $limit = 25 ) {

	$product_ids = array();

	// For values that haven't been checked yet
	$args = array(

		'posts_per_page' 	=> $limit,
		'post_type' 		=> array( 'product', 'product_variation' ),
		'post_status'    	=> 'publish',
		'fields' 			=> 'ids',
		'meta_query' 		=> array(
			array(
				'key' => '_wpd_ai_last_updated',
				'compare' => 'NOT EXISTS'
			)
		),

	);

	// Make query
	$query 					= new WP_Query( $args );
	$product_ids 			= (array) $query->posts;	

	// Reset post data
	wp_reset_postdata();

	// Return unchecked results if they're available
	if ( ! empty($product_ids) ) return $product_ids; 

	// Otherwise pull the oldest data
	$args = array(

		'post_type' 		=> array( 'product', 'product_variation' ),
		'post_status'    	=> 'publish',
		'fields' 			=> 'ids',
		'meta_key' 			=> '_wpd_ai_last_updated',
		'orderby' 			=> 'meta_value_num',
		'order' 			=> 'asc',
		'posts_per_page' 	=> $limit,

	);

	// Make query
	$query 					= new WP_Query( $args );
	$product_ids 			= (array) $query->posts;	

	// Reset post data
	wp_reset_postdata();

	// Return results
	return $product_ids;

}

/**
 *
 *	Collect array of User ID's
 *
 * 	@return array|bool Returns an array of results if succesful, returns false on failure
 *
 */
function wpdai_query_user_ids_for_analytics_collector( $limit = 25 ) {

	$user_ids = array();

	// For values that haven't been checked yet
	$args = array(

		'number' 			=> $limit,
		'fields' 			=> 'ID',
		'meta_query' 		=> array(
			array(
				'key' => '_wpd_ai_last_updated',
				'compare' => 'NOT EXISTS'
			)
		),

	);

	// Make query
	$user_query 			= new WP_User_Query( $args );
	$user_ids 				= (array) $user_query->get_results();

	// Return untouched results if they are set
	if ( is_array($user_ids) && ! empty($user_ids) ) return $user_ids;

	// For values that haven't been checked yet
	$args = array(

		'number' 			=> $limit,
		'fields' 			=> 'ID',
		'meta_key' 			=> '_wpd_ai_last_updated',
		'orderby' 			=> 'meta_value_num',
		'order' 			=> 'asc',

	);

	// Make query
	$user_query 			= new WP_User_Query( $args );
	$user_ids 				= (array) $user_query->get_results();

	return $user_ids;

}

/**
 *
 *	Collect array of User ID's
 *
 * 	@return array|bool Returns an array of results if succesful, returns false on failure
 *
 */
function wpdai_collect_user_ids() {

	// DB Global
	global $wpdb;

	// Query
	$sql_query = "SELECT {$wpdb->users}.ID FROM {$wpdb->users} ORDER BY {$wpdb->users}.ID DESC";

	// Query Results
	$user_ids = $wpdb->get_col($sql_query);

	// DB Error
	if ( $wpdb->last_error  ) {
		wpdai_write_log( 'Error capturing list of users from DB, dumping the error and query.', 'db_error' );
		wpdai_write_log( $wpdb->last_error, 'db_error' );
		wpdai_write_log( $wpdb->last_query, 'db_error' );
		return false;
	}

	// Return results
	return $user_ids;

}

/**
 * 
 * 	Get all Alpha Insights Options
 * 	Fetches all wpd_ai keys from the wp_options table
 * 
 * 	@return array An array of option keys
 * 
 **/
function wpdai_fetch_all_option_keys() {

	global $wpdb;

	// Sanitize query
	$sql_query = $wpdb->prepare( "SELECT DISTINCT option_name FROM $wpdb->options WHERE option_name LIKE %s", '%wpd_ai_%' );

	// Execute query
	$result = $wpdb->get_col( $sql_query );

	// Return results
	return $result;

}


/**
 *
 *	Gets all published product IDs and returns as an array of product ids
 *  Must use WP_Query so that we can fetch product_variations
 * 
 * 	@param bool $product_variations Set true to include variations, false to remove
 *
 * 	@return array An array of product ids
 *
 */
function wpdai_get_all_product_ids( $include_variations = true ) {

	// Args
	$args = array(

		'post_type' 		=> array( 'product', 'product_variation' ),
		'post_status'    	=> 'publish',
		'fields' 			=> 'ids',
		'posts_per_page' 	=> -1,

	);

	// Remove variations
	if ( ! $include_variations ) $args['post_type'] = array( 'product' );

	// Make the query
	$query 					= new WP_Query( $args );
	$product_ids 			= (array) $query->posts;	
	
	// Rest post data
	wp_reset_postdata();

	// Return results
	return $product_ids;

}

/**
 * Get all WooCommerce order IDs safely + cached for 30 minutes.
 *
 * @return array
 */
function wpdai_get_all_order_ids() {

    $transient_key = 'wpd_all_order_ids';
    $lock_key      = 'wpd_all_order_ids_lock';

    // --- 1. Use cached value if available ---
    $cached = get_transient( $transient_key );
    if ( $cached !== false && is_array( $cached ) ) {
        return $cached;
    }

    // --- 2. Prevent concurrent builds ---
    if ( get_transient( $lock_key ) ) {
        // Another process is building — avoid double-load.
        // Return empty array or last known cached value.
        return is_array( $cached ) ? $cached : [];
    }

    // Set a short-lived lock (2 minutes)
    set_transient( $lock_key, true, 120 );

    // --- 3. Safe batch processing ---
    $page        = 1;
    $chunk_size  = 2000;
    $ids         = [];

    try {

        do {
            $query = wc_get_orders([
                'limit'   => $chunk_size,
                'paged'   => $page,
                'return'  => 'ids',
                'orderby' => 'date',
                'order'   => 'DESC',
                'status'  => 'any',
            ]);

            // Safety check: If result is WP_Error → abort
            if ( $query instanceof WP_Error ) {
                wpdai_write_log( 'WPD: Failed to fetch order IDs: ' . $query->get_error_message(), 'db_error' );
                break;
            }

            // Safety check: Prevent runaway loops or huge stores
            if ( $page > 10000 ) { 
                // 10000 pages × 2000 per page = 20M orders — we bail safely
                wpdai_write_log( 'WPD: Page limit exceeded while fetching orders.', 'db_error' );
                break;
            }

            if ( !empty( $query ) ) {
                $ids = array_merge( $ids, $query );
            }

            $page++;

        } while ( ! empty( $query ) );

    } catch ( Exception $e ) {
        wpdai_write_log( 'WPD: Exception while fetching order IDs: ' . $e->getMessage(), 'db_error' );
    }

    // Remove lock
    delete_transient( $lock_key );

    // --- 4. Cache results (30 minutes) ---
    if ( ! empty( $ids ) ) {
        set_transient( $transient_key, $ids, MINUTE_IN_SECONDS * 30 );
    }

    return (array) $ids;
	
}

/**
 * 
 * 	Fetches all orders, will only include shop_order
 * 
 **/
function wpdai_get_all_order_ids_legacy() {

	// Legacy Fetch All
	$args = array(
		'limit' 		=> -1,
		'return' 		=> 'ids',
		'orderby' 		=> 'date',
		'order' 		=> 'DESC',
		'status' 		=> 'any',
		'type' 			=> array('shop_order')
	);

	// Get the orders
	$orders = wc_get_orders( $args );

	// Return the results
	return (array) $orders;

}

/**
 * 
 * 	Fetches a count of all orders under shop_order (no refunds or other custom objects)
 * 	Default status will be wc_get_order_statuses()
 * 	Relies on wpdai_get_all_order_ids()
 * 
 * 	@return int $order_count A count of all orders found
 * 
 **/
function wpdai_get_store_order_count() {

	// Fetch all order IDs
	$order_ids = wpdai_get_all_order_ids();

	if ( is_array($order_ids) ) {

		return count( $order_ids );

	} else {

		return 0;

	}

}

/**
 * 
 * 	Will search DB for the oldest user registration date, this is assumed to be the site creation date
 * 	Returns this date in specified format (defaults to Y-m-d H:i:s) or false on failure
 * 	Will save this value in the wp_options table for quick use in the future.
 * 
 * 	@param string $date_format the date format, gets washed against date() function
 * 	@return bool|string Returns false on failure, Y-m-d H:i:s on success in local time as default or timestamp as specified
 * 
 **/
function wpdai_get_site_creation_date( $date_format = null ) {

	// Set ISO DateTime format as default date
	if ( is_null($date_format) ) $date_format = WPD_AI_PHP_ISO_DATETIME;

	// Get stored value
	$site_creation_date = get_option( 'wpd_ai_site_creation_date', null );

	// Return the stored value in the desired format if found
	if ( $site_creation_date !== null && is_numeric($site_creation_date) ) return gmdate( $date_format, $site_creation_date );

	// Load global var
	global $wpdb;	

	// Get the earliest user directly via DB
	$first_user_registration_date = $wpdb->get_var( "SELECT user_registered FROM $wpdb->users ORDER BY user_registered ASC LIMIT 1" );

	// DB Error
	if ( $wpdb->last_error || $first_user_registration_date === null  ) {

		wpdai_write_log( 'Error finding earliest user from DB, dumping the error & query.', 'db_error' );
		wpdai_write_log( $wpdb->last_error, 'db_error' );
		wpdai_write_log( $wpdb->last_query, 'db_error' );

		return false;
	}

	// Make sure data is in correct format
	if ( ! is_string($first_user_registration_date) || empty($first_user_registration_date) ) return false;

	// Returns registration date in local timestamp so that we can format it
	$site_creation_date = strtotime( get_date_from_gmt( $first_user_registration_date ) );

	// Stored in wp_options table in local timestamp
	update_option( 'wpd_ai_site_creation_date', $site_creation_date );

	// Returns registration date formatted to user's spec
	return gmdate( $date_format, $site_creation_date );

}