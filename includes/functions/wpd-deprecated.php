<?php
/**
 *
 * Deprecated Functions
 *
 * @package Alpha Insights
 * @version 4.9.0
 * @since 4.9.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * 
 * 	Loop through orders cache and recalculate everything by using force refresh with wpd_calculate_cost_profit_by_order
 * 
 * 	@param int $batch_size number of records to iterate through each loop
 * 	@return int Total count of records found that we iterated through
 * 
 **/
function wpd_update_all_orders_cache( $batch_size = 1000 ) {

	// Init log
	wpd_write_log( 'Attempting to execute WC Orders cache refresh.', 'cache' );

	// If it's already running, dont run again
	if ( get_transient('_wpd_updating_all_orders_cache') == 1 ) {

		// Log discontinue
		wpd_write_log( 'Not continuing execution of WC Orders cache refresh because there is a transient set at the moment.', 'cache' );

		// Return function not run
		return false;

	}

	// Let's figure out our timeout length -> Assume 15 orders cached per second
	$php_execution_time_limit 		= wpd_get_php_max_execution_time();
	$orders_cached_per_second 		= 15; // Logged records say that it should be 60 order per second
	$minimum_cache_timeout_limit 	= (int) wpd_divide( wpd_get_store_order_count(), $orders_cached_per_second, 0 );

	// Log the transient timeout
	wpd_write_log( 'Setting php execution & transient timeout to ' . $minimum_cache_timeout_limit . ' seconds to match order volumes of this store.', 'cache' );
	wpd_write_log( 'This store\'s PHP Execution Time Limit is currently set to ' . $php_execution_time_limit . ' in php.ini.', 'cache' );

	// Set timeouts and transient length to the calculated cache time limit
	set_time_limit( $minimum_cache_timeout_limit );
	set_transient( '_wpd_updating_all_orders_cache', 1, $minimum_cache_timeout_limit );

	// Setup main vars
	$start 				= microtime( true );
	$batch_size 		= 1000;
	$order_count 		= (array) wc_get_orders( array('limit' => -1, 'status' => 'any', 'return' => 'ids' ) );
	$total_order_count 	= count( $order_count );
	$page_count 		= ceil( wpd_divide( $total_order_count, $batch_size ) );
	$current_page 		= 1;

	// Delete all cache, just in case
	wpd_delete_all_order_data_cache();
	wpd_write_log( 'Deleting entire order cache, as a start.', 'cache' );

	// Log the initialization of this function
	wpd_write_log( 'Executing WC Orders cache refresh on ' . $total_order_count . ' orders in batches of ' . $batch_size . '.', 'cache' );
	
	// Loop through batches
	while( $current_page <= $page_count ) {

		// Conserve memory
		wp_cache_flush();

		// Collect a batch of order IDs
		$order_ids = wc_get_orders( array('limit' => $batch_size, 'status' => 'any', 'return' => 'ids', 'paged' => $current_page ) ); // $paged starts at 1

		// Loop through Order IDs
		foreach( $order_ids as $order_id ) {

			wpd_calculate_cost_profit_by_order( $order_id, true );

		}

		// Log batch run
		wpd_write_log( 'Executed batch ' . $current_page . ' of ' . $page_count . ', current memory usage: ' . wpd_get_peak_memory_usage(), 'cache' );

		// Iterate to next page
		$current_page++;

	}

	// Logging Vars
	$finish = microtime( true );
	$total_time_elapsed = $finish - $start;

	// Log execution complete
	wpd_write_log( 'Execution of WC Orders cache refresh succesfully completed. Process took ' . $total_time_elapsed . ' seconds.', 'cache' );

	// Delete the transient that this is running
	delete_transient( '_wpd_updating_all_orders_cache' );

	// Return the number of orders refreshed
	return $total_order_count;

}

/**
 *
 *	Delete order data cache from meta table
 *
 * 	Deletes the meta cache for a given order / order_id
 *	@param WC_Order|int Accepts a WC_Order or order_id
 *	@return bool True on success, false on failure (if we couldn't find an order)
 *
 */
function wpd_delete_order_cache_by_order_id_deprecated( $order_id_or_object ) {

	// Convert order ID into WC_Order
	if ( is_numeric($order_id_or_object) ) {
		$order_id = $order_id_or_object;
	}

	// Object is passed in, check if it's an order
	if ( is_a( $order_id_or_object, 'WC_Order' ) ) {
		$order_id = $order_id_or_object->get_id();
	}

	// Safety check our order ID
	if ( ! is_numeric($order_id) || $order_id < 1 ) {
		return false;
	}

	// Will delete post meta and meta table values
	wpd_delete_order_meta_by_order_id( $order_id, '_wpd_ai_order_data_store' );

	// Delete the transient
	delete_transient( '_wpd_order_calculations_' . $order_id );
	
	// On Success
	return true;

}


/**
 * 
 * 	Delete all order meta calculation transients
 * 
 * 	@return int The count of orders deleted from the transients table
 * 
 **/
function wpd_delete_all_order_calculation_transients() {

	// Get all order IDs
	$all_order_ids = wpd_get_all_order_ids();

	// Deletion count
	$deleted_count = 0;

	// Loop through order IDs
	foreach( $all_order_ids as $order_id ) {

		// Delete the transient
		$delete = delete_transient( '_wpd_order_calculations_' . $order_id );

		// Iterate delete counter
		if ( $delete ) $deleted_count++;

	}

	// Return the count of deleted transients
	return $deleted_count;

}


/**
 *
 *	Set defaults for line chart
 *	rgb(132,103,214) Purple
 *	rgb(19,143,221) Dark Blue
 *	rgb(48, 193, 241) Blue
 *	rgb(48, 229, 241) Light Blue
 *	
 *
 */
function wpd_chart_defaults() {

	$tension = 0; // Default linear
	$fill = 'true';
	$point_radius = 2; // 2px diameter = showing, or 0 = not showing -> Default showing
	$ui_settings = get_option( 'wpd_ai_user_interface_display_settings' );

	// Line Chart Tension
	if ( isset($ui_settings['graphs']['line_chart_tension']) && $ui_settings['graphs']['line_chart_tension'] >= 0 && $ui_settings['graphs']['line_chart_tension'] <= 0.5 ) {
		$tension = (float) $ui_settings['graphs']['line_chart_tension'];
	}
	// Line Chart Background Fill
	if ( isset($ui_settings['graphs']['line_chart_background_fill']) ) {
		if ($ui_settings['graphs']['line_chart_background_fill'] == 1) {
			$fill = 'true';
		} else if ($ui_settings['graphs']['line_chart_background_fill'] == 0) {
			$fill = 'false';
		}
	}
	// Line Chart Points Always Visible
	if ( isset($ui_settings['graphs']['line_chart_always_display_points']) ) {
		if ($ui_settings['graphs']['line_chart_always_display_points'] == 1) {
			$point_radius = 2;
		} else if ($ui_settings['graphs']['line_chart_always_display_points'] == 0) {
			$point_radius = 0;
		}
	}
	?>
		<script type="text/javascript">
			jQuery(document).ready(function() {

				// Doughnut, pie
				Chart.defaults.elements.arc = {
					backgroundColor: ["rgb(132,103,214,0.75)", "rgb(19,143,221,0.75)", "rgb(48, 193, 241,0.75)", "rgb(48, 229, 241,0.75)", "rgb(48,241,191,0.75)"],
					borderAlign: "center",
					borderColor: "#fff",
					borderWidth: 2,
				};
				Chart.defaults.elements.line = {
					backgroundColor: ["rgb(132,103,214,0.0)", "rgb(19,143,221,0.0)", "rgb(48, 193, 241,0.0)", "rgb(48, 229, 241,0.0)", "rgb(48,241,191,0.0)"],
					hoverBackgroundColor: ["rgb(132,103,214)", "rgb(19,143,221)", "rgb(48, 193, 241)", "rgb(48, 229, 241)", "rgb(48,241,191)"],
					borderCapStyle: "butt",
					borderColor: ["rgb(132,103,214)", "rgb(19,143,221)", "rgb(48, 193, 241)", "rgb(48, 229, 241)", "rgb(48,241,191)"],
					borderDash: [],
					borderDashOffset: 0,
					borderJoinStyle: "round",
					borderWidth: 2,
					capBezierPoints: true,
					fill: <?php echo esc_js( $fill ); ?>, // <- Create setting for this
					tension: <?php echo esc_js( $tension ); ?>, // Lower value is more linear (0-1), 0.25 is okay <- Create setting for this
				};
				Chart.defaults.elements.point = {
					backgroundColor: "rgba(0,0,0,0.1)",
					borderColor: "rgba(0,0,0,0.1)",
					borderWidth: 4,
					hitRadius: 5,
					hoverBorderWidth: 15,
					hoverRadius: 5,
					pointStyle: "circle",
					radius: <?php echo $point_radius ?>, // 0 to get rid of point <- Create setting for this
				};
				Chart.defaults.elements.square = {
					backgroundColor: "rgba(0,0,0,0.1)",
					borderColor: "rgba(0,0,0,0.1)",
					borderSkipped: "bottom",
					borderWidth: 20,
				};
				Chart.defaults.hover.mode = 'index';
				Chart.defaults.hover.intersect = false;	
				Chart.defaults.interaction.mode = 'index';
				Chart.defaults.interaction.intersect = false;
				Chart.defaults.defaultFontFamily = "'Poppins', 'Helvetica Neue', 'Helvetica', 'Arial', sans-serif";
				Chart.defaults.scale.ticks.beginAtZero = true;
				Chart.defaults.plugins.tooltip.bodySpacing = 5;
				Chart.defaults.plugins.tooltip.padding = 12;
				Chart.defaults.plugins.legend.onHover = (event, chartElement) => { event.native.target.style.cursor = 'pointer'; };

			});
		</script>
	<?php
}

/**
 *
 *	Per page selector
 *
 */
function wpd_per_page_selector( $per_page = 25 ) {

	?>
		<span class="wpd-per-page-wrapper">
			<label for="wpd-per-page"><?php esc_html_e( 'Per Page', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ?></label>
			<select name="wpd-per-page" class="wpd-input">
				<?php 
					$per_page_array = array( '25', '50', '100', '250', '500', 'all' );	
					foreach( $per_page_array as $per_page_array_value ) {
						( $per_page_array_value == $per_page ) ? $selected = 'selected="selected"' : $selected = '';
						echo '<option value="' . esc_attr( $per_page_array_value ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $per_page_array_value ) . '</option>';
					}
				?>
			</select>
		</span>
	<?php

}

/**
 *
 *	Convert device category into icon
 *
 */
if ( ! function_exists( 'wpd_device_category_icon' ) ) {

	function wpd_device_category_icon( $device_category ) {

		$device_category = strtolower($device_category);

		if ( $device_category === 'mobile' ) {

			$result = '<span class="dashicons dashicons-smartphone"></span>';

		} elseif ( $device_category === 'tablet' ) {

			$result = '<span class="dashicons dashicons-tablet"></span>';


		} elseif ( $device_category === 'desktop' ) {

			$result = '<span class="dashicons dashicons-desktop"></span>';

		} else {

			return false;

		}

		return '<span class="wpd-device-category-icon wpd-icon">' . $result . '</span>';

	}

}

/**
 *
 *	Post edit link for admin
 *
 */
function wpd_admin_post_url( $post_id ) {

	return admin_url( 'post.php?post=' . $post_id ) . '&action=edit';

}

/**
 *
 *	Stock Status Message
 *
 */
if ( ! function_exists( 'wpd_stock_status_html' ) ) {

	function wpd_stock_status_html( $product_object ) {

		if ( ! is_object( $product_object ) ) return false;

		$result 				= null;
		$manage_stock 			= $product_object->get_manage_stock();
		$stock_quantity 		= $product_object->get_stock_quantity();
		$stock_status 			= $product_object->get_stock_status();
		$backorders 			= $product_object->get_backorders();

		if ( $manage_stock ) {

			if ( $stock_quantity < 1 ) {

				// Out of stock
				$result = __( 'Out Stock', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) . '(' . $stock_quantity . ')';
				$result	.= '<div class="wpd-meta">' . __( 'Backorders:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') . ' ' . $backorders . '</div>';

			} else {

				// In stock
				$result = __( 'In Stock', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) . ' (' . $stock_quantity . ')';

			}

		} else {

			$result = __( 'In Stock', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') . ' (N/A)' . '<div class="wpd-meta">'. __( 'Stock Not Managed', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') . '</div>';

		}

		return $result;

	}

}

/**
 *
 *	CSV Icon
 *
 */
if ( ! function_exists( 'wpd_export_to_csv_icon' ) ) {

	function wpd_export_to_csv_icon( $id = null, $text = 'Export To CSV' ) {

	?>
		<div class="wpd-download-csv wpd-download" id="<?php echo $id; ?>">
			<div class="wpd-icon">
				<span class="dashicons dashicons-media-spreadsheet"></span>
			</div>
			<p><?php echo $text ?></p>
		</div>
	<?php

	}

}

// Performance Reports
function wpd_performance_reports( $data ) {

	// Must input an array
	if ( ! is_array($data) ) {
		return false;
	}

	// Load vars
	$file_name 					= 'wpd-performance-reports';
	$start 						= microtime( true );
	$response 					= array();
	$system_path 				= WPD_AI_CSV_SYSTEM_PATH;
	$file 						= $system_path . $file_name . '.csv';

	// Ideal Array
	$accepted_array_format = array(
		'report' => 'default',
		'store_baseline_memory_usage' => 0,
		'total_memory_usage' => 0,
		'report_memory_usage' => 0,
		'report_memory_usage_per_record' => 0,
		'report_memory_usage_per_thousand_records' => 0,
		'report_load_time' => 0,
		'report_load_time_per_record' => 0,
		'report_load_time_per_thousand_records' => 0
	);

	// Let's load our array with the target keys
	foreach( $accepted_array_format as $key => $default_value ) {
		$accepted_array_format[$key] = (isset($data[$key])) ? $data[$key] : $default_value;
	}

	// Move new array into our data payload
	$data = $accepted_array_format;

	// Add headers if file doesnt exist
    if ( ! file_exists($file)) {
    	$output 					= fopen( $file, "w");
	   	$write_csv = fputcsv( $output, array_keys($data) );  //output the user info line to the csv file
	   	fclose( $output ); 
    }
	$output 					= fopen( $file, "a");
    $i 							= 0;
    $success 					= true;
	$response['file_type'] 		= 'CSV';
	$error_message 				= array();

    if ( ! $output ) {

		$error_message['file-creation-failure'] = __( 'Failed to create the CSV file, check to make sure folder permissions are okay.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		$success = false;

    }

    // Add headers if file doesnt exist
    if ( ! file_exists($file)) {
	   	$write_csv = fputcsv( $output, array_keys($data) );  //output the user info line to the csv file
    }

   	$write_csv = fputcsv( $output, array_values($data) );  //output the user info line to the csv file

     if ( ! $write_csv ) {

    	$error_message['write-failure'] = __( 'Failed to write CSV, check to make sure folder permissions are okay.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		$success = false;

    } else {

    	$i++;

    }


    /**
     *
     *	Send fail if 0 or 1 rows are written
     *
     */
    if ( $i === 0 || $i === 1 ) {

    	$error_message['data-failure'] = __( 'We couldn\'t find any data for the given range. Please check your filter and try again.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		$success = false;

    }

 	/**
 	 *
 	 *	Close output
 	 *
 	 */
    fclose( $output ); 

    /**
     *
     *	Store results
     *
     */
    $download_link 				= WPD_AI_CSV_PATH . $file_name;  //make a link to the file so the user can download.
	$finish 					= microtime( true );
	$execution_time 			= $finish - $start;
	$response = array(

		'execution_time' => $execution_time,
		'download_link'  => $download_link,
		'error_messages' => $error_message,
		'rows_found' 	 => $i,
		'success' 		 => $success,

	);

	return $response;

}

/**
 *
 *	Read, organise & average performance reports
 *
 * 	@return array Associative array with average values for each report
 *
 */
function wpd_read_performance_reports() {

	$file = WPD_AI_CSV_SYSTEM_PATH . 'wpd-performance-reports.csv';

	if ( ! file_exists($file) ) {
		return array();
	}
	$performance_report = wpd_csv_to_array( $file );
	$performance_array = array();

	// Organise array
	foreach( $performance_report as $performance_reports ) {

	    $row = 0;
	    foreach( $performance_reports as $key => $value ) {

	        if ( $key == 'report' ) $report_key = $value;

	        $performance_array[$report_key][$key][] = $value;

	        $row++;

	    }

	}

	$average_performance = array();
	foreach( $performance_array as $report_type => $data ) {

	    foreach( $data as $column => $data_array ) {

	        if ( $column === 'report' ) continue;

	        $data_array = array_filter($data_array);
	        $average = wpd_divide( array_sum( $data_array ), count($data_array), 4 );
	        $average_performance[$report_type][$column] = $average;

	    }

	}

	return $average_performance;

}

/**
 *
 *	Store COGS CSV Upload
 *
 */
function wpd_load_cogs_via_csv_upload() {

	if ( ! empty($_FILES) ) {

		$result 			= array();
		$csv_file 			= $_FILES['csv_file'];
		$csv_to_array 		= array_splice( array_map( 'str_getcsv', file( $csv_file['tmp_name']) ), 1 );
		$result['count'] 	= count( $csv_to_array );
		$result['data'] 	= $csv_to_array;

	} else {

		$result = false;

	}

	return $result;

}

/**
 *
 *	Collect dtaa for COGS
 *
 */
function wpd_download_product_cogs_by_csv() {

	$product_ids 	= wpd_get_all_product_ids();
	$row_number 	= 0;
	$csv_results 	= array();
	$target_fields 	= array(

		'product_id' 		=> 'Product ID',
		'cost_of_goods' 	=> 'Cost Of Goods',
		'product_name' 		=> 'Product Name',
		'sku' 				=> 'SKU',
		'rrp_price' 		=> 'RRP Price',
		
	);

	/**
	 *
	 *	Store Header Rows
	 *
	 */	
	foreach( $target_fields as $key => $value ) {

		$csv_results[$row_number][] = $value;

	}

	$row_number++;

	/**
	 *
	 *	Loop through products
	 *
	 */
	foreach( $product_ids as $product_id ) {

		$csv_results[$row_number][] 	= $product_id;
		$csv_results[$row_number][] 	= get_post_meta( $product_id, '_wpd_ai_product_cost', true );
		$csv_results[$row_number][] 	= html_entity_decode( get_the_title( $product_id ) );
		$csv_results[$row_number][] 	= get_post_meta( $product_id, '_sku', true ); // _sku
		$csv_results[$row_number][] 	= get_post_meta( $product_id, '_regular_price', true );
		$row_number++;

	}

	return $csv_results;

}


/**
 * 
 * Returns empty table row string
 * 
 */
function wpd_no_results_table_row( $colspan = 1) {

	$table_row = '<tr><td colspan="'.$colspan.'">No results found.</td></tr>';
	
	return $table_row;

}

/**
 *
 *	Ajax request for order meta
 *
 */
add_action('wp_ajax_wpd_export_order_meta', 'wpd_export_order_meta_to_csv' );
add_action('wp_ajax_nopriv_wpd_export_order_meta', 'wpd_export_order_meta_to_csv' );
function wpd_export_order_meta_to_csv() {

	try {

		if ( isset($_POST['additional_data']['order_ids']) ) {
			$order_ids 					= (array) $_POST['additional_data']['order_ids'];
			$data 						= wpd_download_order_meta_by_csv($order_ids);
		} else {
			$data 						= wpd_download_order_meta_by_csv();
		}
		$date_time_stamp 			= current_time( 'Y-m-d-h-i-s' );
		$file_name 					= 'alpha-insights-order-meta-' . $date_time_stamp . '.csv';
		$response 					= wpd_create_csv_file( $file_name, $data );
		$response['headers'] 		= $data[0];

	} catch (Throwable $e) {

		$response = array();
		$response['error'] = error_get_last();
		
	}

	wp_die( json_encode( $response ) );

}

/**
 *
 *	Collect dtaa for order meta
 *
 */
function wpd_download_order_meta_by_csv( $order_ids = false ) {

	// $order_query = new WC_Order_Query( array('limit' => -1, 'status' => 'any', 'return' => 'ids') );
	// $order_ids = $order_query->get_orders();
	if ( is_array($order_ids) && ! empty($order_ids) ) {
		// Use the passed order ids
	} else {
		$order_ids = wc_get_orders( array('limit' => -1, 'status' => 'any', 'return' => 'ids' ) ); // offset?
	}
	$row_number 	= 0;
	$csv_results 	= array();
	$target_fields 	= array(
		'post_id' 				=> 'Post ID',
		'order_id' 				=> 'Order ID',
		'customer' 				=> 'Customer',
		'order_total' 			=> 'Order Total',
		'order_status' 			=> 'Order Status',
		'shipping_cost' 		=> 'Shipping Cost',
		'product_cost' 			=> 'Product Cost',
		'payment_gateway_cost' 	=> 'Payment Gateway Cost',
	);

	/**
	 *
	 *	Store Header Rows
	 *
	 */	
	foreach( $target_fields as $key => $value ) {

		$csv_results[$row_number][] = $key;

	}

	$row_number++;

	/**
	 *
	 *	Loop through order IDS (Post IDs)
	 *
	 */
	foreach( $order_ids as $order_id ) {

		$order = wc_get_order( $order_id );

		if ( ! is_a( $order, 'WC_Order' ) ) continue;
		
		$shipping_cost 			= $order->get_meta( '_wpd_ai_total_shipping_cost', true );
		$product_cost 			= $order->get_meta( '_wpd_ai_total_product_cost', true );
		$payment_gateway_cost 	= $order->get_meta( '_wpd_ai_total_payment_gateway_cost', true );
		$order_total 			= $order->get_total();
		$order_status 			= wc_get_order_status_name( $order->get_status() );
		$billing_first_name 	= $order->get_billing_first_name();
		$billing_last_name 		= $order->get_billing_last_name();
		$customer_name 			= $billing_first_name . ' ' . $billing_last_name;
		$order_number 			= $order->get_order_number();

		$csv_results[$row_number][] 	= $order_id; // Post ID
		$csv_results[$row_number][] 	= $order_number; // Order ID
		$csv_results[$row_number][] 	= $customer_name; // Customer Name
		$csv_results[$row_number][] 	= $order_total; // Order Total
		$csv_results[$row_number][] 	= $order_status; // Order Status
		$csv_results[$row_number][] 	= $shipping_cost; // Shipping Cost
		$csv_results[$row_number][] 	= $product_cost; // Product Cost
		$csv_results[$row_number][] 	= $payment_gateway_cost; // Payment Gateway Cost

		$row_number++;

	}

	return $csv_results;

}

/**
 * 
 * 	Takes an order status and returns nicely formatted HTML for the relevant order status
 * 
 * 	@return string $order_status nicely formatted in HTML
 * 
 **/
function wpd_html_order_status( $order_status ) {

	return 	'<span class="wpd-order-status wpd-status-' . $order_status . '">' . wc_get_order_status_name( $order_status ) . '</span>';

}

/**
 *
 *	Image URL
 *	wp-content/plugins/wpdavies-alpha-insights/assets/img/
 *
 */
if ( ! function_exists( 'wpd_img_url' ) ) {

	function wpd_img_url( $image ) {

		return WPD_AI_URL_PATH . 'assets/img/' . $image;

	}

}

/**
 *
 *	Prevent undefined notice
 *
 */
if ( ! function_exists( 'wpd_increment' ) ) {

	function wpd_increment( $var ) {

		if ( ! empty($var) && is_numeric($var) ) {

			return $var++;

		} else {

			return 1; // This is our first increment so it can be one

		}

	}

}

/**
 *
 *	Prevent undefined notice
 *
 */
function wpd_increment_by_val( $var1, $var2, $operator = '+=' ) {

	if ( ! empty($var2) && is_numeric($var2) ) {

		return $var1 + $var2;

	} else {

		return $var2; // if var1 doesnt exist, were just at var2

	}

}

/**
 *
 *	JS Ajax Request Template mostly used for exporting CSV files
 *
 *	@param string $click_selector which button click will activate the response
 *	@param string $ajax_action which function will be called
 *	@param string $form_selector which form to grab data from to pass onto the AJAX action, will default to 'form' capturing all form data on the page -> false will stop form data being sent.
 *	@param string $additional_data MUST be a json string
 *
 */
if ( ! function_exists('wpd_javascript_ajax') ) {

	function wpd_javascript_ajax( $click_selector, $ajax_action, $form_selector = 'form', $additional_data = "{}" ) {

		?>
		<div id="wpd-csv-export">
			<div class="wpd-loading" style="text-align: center;">
				<?php echo wpd_preloader( 100 ); ?>
				<?php echo wpd_success( 100, false ); ?>
				<p class="wpd-loading-message"><?php _e( 'Processing Your Data', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>...</p>
				<div class="wpd-results"></div>
				<div class="wpd-cta"><a href="#" class="wpd-button" id="wpd-csv-download" style="display:none;" target="_blank"><?php _e( 'Download File', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></a></div>
				<p class="wpd-results-summary wpd-meta"></p>
			</div>
		</div>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				jQuery('<?php echo $click_selector; ?>').click(function(e) {

					e.preventDefault();

					$('.wpd-preloader').show();
	            	$('.wpd-success').hide();
	            	$('.wpd-loading-message').text( '<?php _e( 'Processing Your Data', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ?>...');
	            	$('.wpd-results').text('');
	            	$('#wpd-csv-download').attr('href', '#');
	            	$('#wpd-csv-download').hide();
	            	$('.wpd-results-summary').text('');

					var additionalData = <?php echo $additional_data ?>;

					 ///open the dialog window
		       		$("#wpd-csv-export").dialog("open");
					<?php if ( $form_selector ) : ?>
					var formData = $('<?php echo $form_selector ?>').serializeArray();
					<?php else : ?>
					var formData = {};
					<?php endif; ?>
		            var data = {

		                'action': '<?php echo $ajax_action; ?>',
		                'url'   : window.location.href,
		                'form' : formData,
						'additional_data' : additionalData

		            };

		            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
		            
		            $.post(ajaxurl, data, function( response ) {
	            		var response = JSON.parse( response );
		            	if ( response.success ) {
			            	var url = response.download_link;
			            	$('.wpd-preloader').hide();
			            	$('.wpd-success').show();
			            	$('.wpd-loading-message').text('<?php _e( 'Success!', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ?>');
			            	$('.wpd-results').text( '<?php _e( 'Your CSV was succesfully created, click the link to download.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ?>');
			            	$('#wpd-csv-download').attr('href', url);
			            	$('#wpd-csv-download').show();
	            			$('.wpd-results-summary').text( (response.rows_found - 1) + ' <?php _e( 'records were found', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ?>.');

	            			if ( response.file_type == 'PDF' ) {

			            		$('.wpd-results').text( '<?php _e( 'Your PDF was succesfully created, click the link to download.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>' );
	            				$('.wpd-results-summary').text('');

	            			}

		            	} else {

		            		var error_string = '';
		            		if ( response.error_messages ) {
			            		for ( var key in response.error_messages ) {
								  	error_string += "<p><strong><?php _e('Error', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ?>:</strong> " + response.error_messages[key] + "</p>";
								}
		            		}
			            	$('.wpd-preloader').hide();
			            	$('.wpd-loading-message').text('<?php _e('Something went wrong', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>');
			            	$('.wpd-results').html('<p><?php _e('Hm, something went wrong. We were unable to create your CSV file.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?> </p> ' + error_string);

			            	if ( response.file_type == 'PDF' ) {
			            		$('.wpd-results').html('<p><?php _e('Hm, something went wrong. We were unable to create your PDF file.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?> </p> ' + error_string);
	            			}

		            	}

		            }).fail(function( xhr, textStatus, errorThrown ) {

		            	$('.wpd-preloader').hide();
		            	$('.wpd-loading-message').text('<?php _e('Something went wrong.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>');
		            	$('.wpd-results').text('<?php _e('Hm, something went wrong. We were unable to create your document. Check the console for errors.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>');

		            }).done(function(response) {

					});

			    });
			    var width = $(window).width() * .5; // 50%
		        $("#wpd-csv-export").dialog({
		        	dialogClass: 'wpd-dialog',
		            autoOpen: false,
		            title: 'Alpha Insights Exporter',
					modal: true,
					height: "auto",
					width: width,
					draggable: false, // disable dragging
					resizable: false, // optional: disables resizing too
					show: { duration: 300 },
					hide: { duration: 300 },
					position: { my: "center", at: "center", of: window }, // keep centered
					open: function () {
						// make it fixed so it doesn’t move on scroll
						$(".ui-dialog").css({
							position: "fixed",
							top: "50%",
							left: "50%",
							transform: "translate(-50%, -50%)"
						});
					}
		        });
		    });
		</script>
		<?php

	}

}

/**
 *
 *	Add HTML container for dialogs
 *
 */
// add_action('admin_footer', 'wpd_footer_dialog_html');
function wpd_footer_dialog_html() {

	if ( is_wpd_page() ) {

		?><div class="wpd-dialog" id="wpd-dialog" style="display: none;">
			<p>Alpha Insights by WP Davies allows you to track your profitability with razor sharp precision. Focus on the one metric that matters - profitability and your business can do nothing but flourish.</p>
			<strong>Documentation</strong>
			<p>Not sure about something? Check out our <a href="https://wpdavies.dev/documentation/alpha-insights/getting-started/?utm_campaign=Alpha+Insights+Documentation&utm_source=Alpha+Insights+Plugin" style="color:rgb(3, 170, 237);" target="_blank">documentation</a> to learn more about Alpha Insights.</p>
			<strong>Open A Ticket</strong>
			<p>Need to open a support Ticket? You can do so from <a href="https://wpdavies.dev/my-account/submit-ticket/?utm_campaign=Alpha+Insights+Ticket+Submission&utm_source=Alpha+Insights+Plugin" style="color:rgb(3, 170, 237);" target="_blank">Your Account</a>.</p>
			<strong>Support</strong>
			<p>Need to talk to someone? <a href="mailto:support@wpdavies.dev" style="color:rgb(3, 170, 237);">support@wpdavies.dev</a></p>
			<strong>Suggest A Feature</strong>
			<p>Have you thought of something that would make Alpha Insights even better? Feel free to <a target="_blank" href="mailto:chris@wpdavies.dev" style="color:rgb(3, 170, 237);">Email Us</a>.</p>
		</div><?php
		
	}

}