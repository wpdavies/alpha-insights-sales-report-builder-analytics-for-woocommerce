<?php
/**
 *
 * Functions relating to AJAX requests
 *
 * @package Alpha Insights
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * Verify AJAX request security (nonce and capability)
 *
 * @since 5.0.15
 * @param string $nonce_action Nonce action name. Default uses WPD_AI_AJAX_NONCE_ACTION constant.
 * @param string $capability Required capability. Default 'manage_options'.
 * @return bool True if verified, false otherwise (sends JSON error and dies).
 */
function wpdai_verify_ajax_request( $nonce_action = null, $capability = 'manage_options' ) {
	// Use constant if no action specified
	if ( null === $nonce_action ) {
		$nonce_action = WPD_AI_AJAX_NONCE_ACTION;
	}
	// Verify nonce
	$nonce_key = isset( $_POST['nonce'] ) ? 'nonce' : 'security';
	if ( ! isset( $_POST[ $nonce_key ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) ), $nonce_action ) ) {
		wp_send_json_error( array( 
			'message' => __( 'Security check failed. Please refresh the page and try again.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) 
		) );
		return false;
	}
	
	// Check capability
	if ( ! current_user_can( $capability ) ) {
		wp_send_json_error( array( 
			'message' => __( 'You do not have permission to perform this action.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) 
		) );
		return false;
	}
	
	return true;
}


/**
 * 
 * Ajax Request to delete all order meta overrides
 * 
 */
add_action( 'wp_ajax_wpd_reset_order_meta', 'wpdai_reset_order_meta' );
function wpdai_reset_order_meta() {

	// Verify security
	if ( ! wpdai_verify_ajax_request() ) {
		return;
	}

	// Default
	$response = array();

	// Execute the delete function
	$deleted_rows = wpdai_delete_all_order_meta_overrides();

	if ( is_numeric($deleted_rows) ) {

		$response['success']	= true;
		/* translators: %d: Number of rows deleted */
		$response['message']	= sprintf( __( '%d rows were deleted.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $deleted_rows );

	} else {

		$response['success']	= false;
		$response['message'] 	= __( 'Unfortunately we could not complete this action. Please check the DB Error Log.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	wp_send_json( $response );

}

/**
 * 
 * Ajax Request to delete all order line item COGS
 * 
 */
add_action( 'wp_ajax_wpd_delete_order_line_item_cogs', 'wpdai_delete_order_line_item_cogs_ajax' );
function wpdai_delete_order_line_item_cogs_ajax() {

	// Verify security
	if ( ! wpdai_verify_ajax_request() ) {
		return;
	}

	// Default
	$response = array();

	// Execute the delete function
	$deleted_rows = wpdai_delete_all_order_line_item_meta_cogs();

	// Build a response
	if ( is_numeric($deleted_rows) ) {

		$response['success']	= true;
		/* translators: %d: Number of rows deleted */
		$response['message']	= sprintf( __( '%d rows were deleted.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $deleted_rows );

	} else {

		$response['success']	= false;
		$response['message'] 	= __( 'Unfortunately we could not complete this action. Please check the DB Error Log.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	// Return formatted response
	wp_send_json( $response );

}

/**
 * 
 * Ajax Request to delete all report caches
 * 
 */
add_action( 'wp_ajax_wpd_delete_all_cache', 'wpdai_delete_all_cache_ajax', 10 );
function wpdai_delete_all_cache_ajax() {

	// Verify security
	if ( ! wpdai_verify_ajax_request() ) {
		return;
	}

	// Immediately delete
	$delete_all_cache = wpdai_delete_all_data_caches();

	// Schedule rebuild
	$response = array();

	if ( $delete_all_cache ) {

		$response['success']	= true;
		$response['message']	= __( 'Succesfully deleted all cached data, we will rebuild this over time or as you view reports.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
	
	} else {

		$response['success']	= false;
		$response['message']	= __( 'Unable to delete cached data, check the error logs for more info.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
	
	}

	wp_send_json( $response );

}

/**
 * Legacy inventory export - replaced by WPD_Cost_Of_Goods_Manager::ajax_export_csv()
 * Kept for backward compatibility with old AJAX calls
 */
add_action('wp_ajax_wpd_export_inventory_to_csv', 'wpdai_export_inventory_to_csv' );
function wpdai_export_inventory_to_csv() {
	// Verify security - the method will handle its own checks, but verify here too
	if ( ! wpdai_verify_ajax_request() ) {
		return;
	}
	// Redirect to new export method
	WPD_Cost_Of_Goods_Manager::ajax_export_csv();
}


/**
 * Ajax handler for generating PDF from live link (React Reports)
 * 
 * This is an example AJAX handler that demonstrates how to use the
 * wpdai_generate_pdf_from_report_slug() function for React-based reports.
 * 
 * @since 4.7.0
 */
add_action('wp_ajax_wpd_export_react_report_to_pdf', 'wpdai_export_react_report_to_pdf' );
function wpdai_export_react_report_to_pdf() {

	// Verify security
	if ( ! wpdai_verify_ajax_request() ) {
		return;
	}

	try {

		// Get report slug from POST data
		$report_slug = isset( $_POST['report_slug'] ) ? sanitize_text_field( $_POST['report_slug'] ) : '';

		if ( empty( $report_slug ) ) {
			wp_send_json_error( array(
				'error_messages' => __( 'Report slug is required', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
			) );
			return;
		}

		// Generate the PDF using report slug (file name auto-generated from report name)
		$response = wpdai_generate_pdf_from_report_slug( $report_slug );

		if ( isset( $response['success'] ) && $response['success'] ) {
			wp_send_json_success( $response );
		} else {
			wp_send_json_error( $response );
		}

	} catch ( Exception $e ) {

		wp_send_json_error( array(
			'error_messages' => esc_html( $e->getMessage() )
		) );

	}

}



/**
 *
 *	Ajax request for sending email
 *
 */
add_action('wp_ajax_wpd_send_email', 'wpdai_send_email_ajax' );
function wpdai_send_email_ajax() {

	// Verify security
	if ( ! wpdai_verify_ajax_request() ) {
		return;
	}

	$requesting_url = isset( $_POST['url'] ) ? wpdai_sanitize_url( $_POST['url'] ) : '';
	$email_type = isset( $_POST['email'] ) ? sanitize_text_field( $_POST['email'] ) : '';
	
	// Validate email type is one of the allowed types
	$allowed_email_types = array( 'wpd_profit_report', 'wpd_expense_report' );
	if ( empty( $email_type ) || ! in_array( $email_type, $allowed_email_types, true ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid email type.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
		return;
	}
	
	$response = wpdai_email( $email_type, false );
	
	// Format response for JavaScript - check if email was sent
	if ( isset( $response['email_sent'] ) && $response['email_sent'] === true ) {
		$response['success'] = true;
		if ( ! isset( $response['message'] ) ) {
			/* translators: %s: Email recipients */
			$response['message'] = sprintf( __( 'Email sent successfully to %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( isset( $response['recipients'] ) ? $response['recipients'] : '' ) );
		}
		wp_send_json_success( $response );
	} else {
		$response['success'] = false;
		if ( ! isset( $response['message'] ) ) {
			$response['message'] = __( 'Email was not sent. Please check your email settings and recipients.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		}
		wp_send_json_error( $response );
	}

}

/**
 *
 *	Manually send out data to webhook
 *
 */
add_action('wp_ajax_wpd_webhook_export_manual', 'wpdai_webhook_export_manual' );
function wpdai_webhook_export_manual() {

	// Verify security
	if ( ! wpdai_verify_ajax_request() ) {
		return;
	}

	$response = wpdai_webhook_post_data();
	wp_send_json( $response );

}

/**
 * 
 * Ajax Request to manually upgrade DB
 * 
 */
add_action( 'wp_ajax_wpd-update_db_manually', 'wpdai_update_wpd_ai_database' );
function wpdai_update_wpd_ai_database() {

	// Verify security
	if ( ! wpdai_verify_ajax_request() ) {
		return;
	}

	$response = array();

	$db_interactor = new WPD_Database_Interactor();

	if ( is_object( $db_interactor ) && method_exists( $db_interactor, 'create_update_tables_columns' ) ) {

		$db_upgrade_response = $db_interactor->create_update_tables_columns();

		if ( $db_upgrade_response ) {
			$response['success'] = true;
			$response['message'] = __( 'DB Upgrade completed succesfully, you can check the Alpha Insights logs for more details if required.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		} else {
			$response['success'] = false;
			$response['message'] = __( 'Error occurred during DB upgrade, please check the Alpha Insights logs for more details.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		}

	} else {

		$response['success'] = false;
		$response['message'] = __( 'We couldnt complete this action unfortunately, feel free to shoot us an email and we\'ll help you resolve this.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	wp_send_json( $response );

}

/**
 * 
 * Delete a log file
 * 
 */
add_action( 'wp_ajax_wpd_delete_log', 'wpdai_delete_log_ajax' );
function wpdai_delete_log_ajax() {

	// Verify security
	if ( ! wpdai_verify_ajax_request() ) {
		return;
	}

	$response = array();

	if ( isset( $_POST['log_file'] ) && ! empty( $_POST['log_file'] ) ) {

		$log_file = sanitize_text_field( $_POST['log_file'] );
		$log_dir = WPD_AI_UPLOADS_FOLDER_SYSTEM . 'log/';
		
		// Validate file is within log directory (prevent directory traversal)
		$real_log_dir = realpath( $log_dir );
		$real_file_path = realpath( $log_dir . basename( $log_file ) );
		
		if ( $real_log_dir && $real_file_path && strpos( $real_file_path, $real_log_dir ) === 0 ) {

			if ( file_exists( $real_file_path ) ) {

				$delete = wp_delete_file( $real_file_path );
				$response['success'] = true;
				$response['message'] = __( 'Log file has been succesfully deleted.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

			} else {

				$response['success'] = true;
				$response['message'] = __( 'Could not find the log file, it may have already been deleted.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

			}

		} else {

			$response['success'] = false;
			$response['message'] = __( 'Invalid file path.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

		}

	} else {

		$response['success'] = false;
		$response['message'] = __( 'Log file not specified.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	wp_send_json( $response );

}

/**
 * Load all documentation files
 */
add_action('wp_ajax_wpd_load_documentation', 'wpdai_load_documentation_ajax');
function wpdai_load_documentation_ajax() {

	// Verify security using standard helper
	if ( ! wpdai_verify_ajax_request() ) {
		return;
	}

	$response = array(
		'success' => false,
		'message' => '',
		'data'    => array()
	);

	try {
		$docs_path = WPD_AI_PATH . 'assets/documentation/alpha-insights/';
		
		if (!file_exists($docs_path) || !is_dir($docs_path)) {
			throw new Exception(__('Documentation directory not found.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'));
		}

		// Recursively load all JSON files
		$docs_data = wpdai_load_docs_recursive($docs_path);

		$response['success'] = true;
		$response['data']    = $docs_data;
		/* translators: %d: Number of documentation files loaded */
		$response['message'] = sprintf(__('Loaded %d documentation files.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), count($docs_data, COUNT_RECURSIVE) - count($docs_data));

	} catch (Exception $e) {
		$response['message'] = esc_html( $e->getMessage() );
	}

	wp_send_json( $response );
}

/**
 * Strip numeric prefix from folder/file names for display
 * Removes patterns like "00_", "01_", "10_" etc. from the beginning
 * 
 * @param string $name Folder or file name
 * @return string Name without numeric prefix
 */
function wpdai_strip_numeric_prefix($name) {
	// Remove pattern: two digits followed by underscore (e.g., "00_", "01_", "10_")
	return preg_replace('/^\d{2}_/', '', $name);
}

/**
 * Recursively load documentation HTML files
 * 
 * @param string $dir Directory path
 * @param string $relative_path Relative path for structuring data
 * @return array Documentation data organized by folders
 */
	function wpdai_load_docs_recursive($dir, $relative_path = '') {
	$result = array();

	// Validate directory path - must be within plugin directory
	$real_plugin_dir = realpath( WPD_AI_PATH );
	$real_dir = realpath( $dir );
	
	if ( ! $real_dir || ! $real_plugin_dir || strpos( $real_dir, $real_plugin_dir ) !== 0 ) {
		return $result; // Invalid path outside plugin directory
	}

	if (!is_dir($dir)) {
		return $result;
	}

	$items = scandir($dir);

	foreach ($items as $item) {
		if ($item === '.' || $item === '..' || $item === 'README.md' || $item === 'FILTERS_SUMMARY.md') {
			continue;
		}

		// Sanitize item name to prevent directory traversal
		$item = basename( $item );
		
		$full_path = $dir . $item;
		
		// Validate full path is still within plugin directory
		$real_full_path = realpath( $full_path );
		if ( ! $real_full_path || strpos( $real_full_path, $real_plugin_dir ) !== 0 ) {
			continue; // Skip paths outside plugin directory
		}
		
		$item_relative_path = $relative_path ? $relative_path . '/' . $item : $item;

		if (is_dir($full_path)) {
			// Recursively process subdirectories
			$subfolder_data = wpdai_load_docs_recursive($full_path . '/', $item_relative_path);
			
			if (!empty($subfolder_data)) {
				// Strip numeric prefix from folder name for display
				$display_name = wpdai_strip_numeric_prefix($item);
				$display_name = ucwords(str_replace('-', ' ', $display_name));
				
				$result[$item] = array(
					'type'  => 'folder',
					'name'  => $display_name,
					'slug'  => $item,
					'path'  => $item_relative_path,
					'items' => $subfolder_data
				);
			}
		} elseif (pathinfo($full_path, PATHINFO_EXTENSION) === 'html') {
			// Load HTML file
			$html_content = file_get_contents($full_path);
			
			if ($html_content !== false) {
				// Extract title from first h2 element
				$title = '';
				if (preg_match('/<h2[^>]*>(.*?)<\/h2>/i', $html_content, $matches)) {
					$title = wp_strip_all_tags($matches[1]);
				}
				
				// If no title found, use filename
				if (empty($title)) {
					$title = pathinfo($item, PATHINFO_FILENAME);
					$title = ucwords(str_replace('-', ' ', $title));
				}
				
				$key = pathinfo($item, PATHINFO_FILENAME);
				
				$result[$key] = array(
					'type'     => 'document',
					'title'    => $title,
					'content'  => $html_content,
					'path'     => $item_relative_path,
					'filename' => $item
				);
			}
		}
	}

	return $result;
}

/**
 * Save Getting Started Settings
 * 
 * Handles AJAX request to save initial configuration settings from getting started wizard
 * 
 * @since 5.0.0
 * @return void
 */
add_action( 'wp_ajax_wpd_save_getting_started_settings', 'wpdai_save_getting_started_settings' );
function wpdai_save_getting_started_settings() {
	
	// Check nonce
	if ( ! check_ajax_referer( WPD_AI_AJAX_NONCE_ACTION, 'nonce', false ) ) {
		wp_send_json_error( array( 
			'message' => __( 'Security check failed', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) 
		) );
	}

	// Check user permissions
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array(
			'message' => __( 'You do not have permission to save settings', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
		) );
	}

	// Get and sanitize settings data
	$settings = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? $_POST['settings'] : array();
	
	// Sanitize settings array
	if ( ! empty( $settings ) ) {
		$settings = map_deep( $settings, 'sanitize_text_field' );
	}

	if ( empty( $settings ) ) {
		wp_send_json_error( array(
			'message' => __( 'No settings data received', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
		) );
	}

	// Process and save payment gateway costs
	if ( isset( $settings['payment_gateway_costs'] ) && is_array( $settings['payment_gateway_costs'] ) ) {
		
		// Get existing settings to merge with
		$existing_settings = get_option( 'wpd_ai_payment_gateway_costs', array() );
		
		$payment_gateway_costs = array();
		
		foreach ( $settings['payment_gateway_costs'] as $gateway_id => $gateway_costs ) {
			$payment_gateway_costs[ $gateway_id ] = array(
				'percent_of_sales' => isset( $gateway_costs['percent_of_sales'] ) ? floatval( $gateway_costs['percent_of_sales'] ) : 0,
				'static_fee' => isset( $gateway_costs['static_fee'] ) ? floatval( $gateway_costs['static_fee'] ) : 0,
			);
		}

		// Merge with existing settings to preserve other gateways
		$merged_settings = array_merge( $existing_settings, $payment_gateway_costs );
		
		// Make sure we have default settings
		if ( ! isset($merged_settings['default']) ) {
			$merged_settings['default'] = array(
				'percent_of_sales' => 0,
				'static_fee' => 0,
			);
		}

		// Save payment gateway costs
		$updated = update_option( 'wpd_ai_payment_gateway_costs', $merged_settings );
		
		// Always delete cache when this function is called, regardless of whether update_option returned true
		wpdai_delete_all_order_data_cache();
	}

	// Return success
	wp_send_json_success( array(
		'message' => __( 'Settings saved successfully', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
	) );
}
