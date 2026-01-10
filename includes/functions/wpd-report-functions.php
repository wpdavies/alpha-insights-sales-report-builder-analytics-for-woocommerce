<?php
/**
 *
 * Functions related to reporting
 * 
 * @package Alpha Insights
 * @version 5.0.0
 * @since 5.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * 
 *  Function for retrieving the IDs of all the default React Reports that come with Alpha Insights
 * 
 *  @return array $report_ids An array of the dashboard_ids of all the default React Reports that come with Alpha Insights
 * 
 *  @since 5.0.0
 * 
 *  @author WPDavies
 *  @link https://wpdavies.dev/
 * 
 */
function wpdai_get_default_react_report_ids() {

    $default_reports = wpdai_get_default_react_reports();
    $report_ids = array();
    foreach ( $default_reports as $report ) {
        $report_ids[] = $report['dashboard_id'] ?? 'unknown';
    }
    return $report_ids;
}

/**
 * 
 *  Function for retrieving all the default React Reports that come with Alpha Insights
 * 
 *  @return array $reports An array of default React reports with some basic meta data about the report. Does not include the configuration data.
 * 
 *  @since 5.0.0
 * 
 *  @author WPDavies
 *  @link https://wpdavies.dev/
 * 
 */
function wpdai_get_default_react_reports() {

    $response = array();

    try {
        // Get the reports directory path
        $reports_dir = WPD_AI_PATH . 'includes/reports/';
        
        if ( ! is_dir( $reports_dir ) ) {
            throw new Exception( 'Reports directory not found' );
        }

        // Get all JSON files in the reports directory
        $json_files = glob( $reports_dir . '*.json' );
        
        if ( empty( $json_files ) ) {
            throw new Exception( 'No default reports found' );
        }

        // Get currently installed reports
        $default_reports = array();

        foreach ( $json_files as $file_path ) {
            $filename = basename( $file_path );
            $slug = str_replace( array( 'dashboard-config-', '.json' ), '', $filename );
            
            // Read and parse the JSON file
            $json_content = file_get_contents( $file_path );
            if ( $json_content === false ) {
                continue;
            }

            $report_data = json_decode( $json_content, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                continue;
            }

            // Get the actual dashboard_id from the JSON file
            $actual_dashboard_id = isset( $report_data['dashboard_id'] ) ? $report_data['dashboard_id'] : $slug;

            $default_reports[] = array(
                'dashboard_id' => $actual_dashboard_id,
                'name' => isset( $report_data['name'] ) ? $report_data['name'] : ucwords( str_replace( '-', ' ', $actual_dashboard_id ) ),
                'category' => isset( $report_data['report_category'] ) ? $report_data['report_category'] : 'sales_reports',
                'version' => isset( $report_data['version_number'] ) ? $report_data['version_number'] : '1.0',
                'icon' => isset( $report_data['icon'] ) ? $report_data['icon'] : 'bar_chart',
                'color' => isset( $report_data['color'] ) ? $report_data['color'] : 'blue',
                'file_path' => $file_path
            );
        }

    } catch ( Exception $e ) {
        WPD_React_Report::log_error( $e->getMessage() );
        $default_reports = array();
    }

    return $default_reports;

}

/**
 *
 *  Function for retrieving all installed React reports with their configuration data
 * 
 *  @return array $reports An array of installed React reports with their configuration data
 * 
 *  @since 5.0.0
 * 
 *  @author WPDavies
 *  @link https://wpdavies.dev/
 * 
 */
function wpdai_get_installed_react_reports() {

    $response = array();

    // Get all options that start with wpd_dashboard_config_
    global $wpdb;
    $dashboard_configs = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
            'wpd_dashboard_config_%'
        )
    );

    $reports = array();
    
    if ( $dashboard_configs ) {

        foreach ( $dashboard_configs as $config ) {

            // Parse the config data
            $config_data = maybe_unserialize( $config->option_value );
            
            if ( $config_data && is_array( $config_data ) ) {

                $reports[] = $config_data;
            }
        }
    }

    // Sort reports by menu_order (lowest first)
    usort($reports, function($a, $b) {
        $menu_order_a = isset($a['menu_order']) ? intval($a['menu_order']) : 0;
        $menu_order_b = isset($b['menu_order']) ? intval($b['menu_order']) : 0;
        
        return $menu_order_a - $menu_order_b;
    });

    return $reports;

}

/**
 * 
 * 	Checks if a given UTM key and value pair is valid for reporting
 * 
 * 	@param string $key The UTM key to check
 * 	@param string $value The UTM value to check
 * 	@return bool True if the key and value pair is valid, false otherwise
 * 
 **/
function wpdai_is_valid_reporting_utm_key_value_pair( $key, $value ) {

    // Handle arrays (when query parameter appears multiple times, parse_str returns an array)
    if ( is_array( $value ) ) {
        // For arrays, validate each value - return true if at least one is valid
        foreach ( $value as $single_value ) {
            if ( wpdai_is_valid_reporting_utm_key_value_pair( $key, $single_value ) ) {
                return true;
            }
        }
        return false;
    }

    // Default to true
    $valid = true;

    // Make sure the key is in our allowed keys
    if ( ! wpdai_is_valid_reporting_utm_key( $key ) ) $valid = false;

    // Make sure the value is valid
    if ( ! wpdai_is_valid_reporting_utm_value( $value ) ) $valid = false;

    // Pass through filter before returning
    return apply_filters( 'wpd_ai_is_valid_reporting_utm_key_value_pair', $valid, $key, $value );

}

/**
 * 
 * 	Checks if a given UTM value is valid for reporting
 * 
 * 	@param string $value The UTM value to check
 * 	@return bool True if the value is valid, false otherwise
 * 
 **/
function wpdai_is_valid_reporting_utm_value( $value ) {

    // Default to true
    $valid = true;

    // Ensure value is a string for string operations
    if ( ! is_string( $value ) ) {

        $valid = false;

    } else {

        // Make sure value is not empty
        if ( empty( $value ) ) $valid = false;

        // // Make sure the value is less than 255 characters
        if ( strlen( $value ) > 255 ) $valid = false;

        // // Make sure the value is greater than 1 character
        if ( strlen( $value ) < 2 ) $valid = false;

    }

    // Return filterable results before returning
    return apply_filters( 'wpd_ai_is_valid_reporting_utm_value', $valid, $value );

}

/**
 * 
 * 	Checks if a given UTM key is valid for reporting
 * 
 * 	@param string $key The UTM key to check
 * 	@return bool True if the key is valid, false otherwise
 * 
 **/
function wpdai_is_valid_reporting_utm_key( $key ) {

	$valid_utm_keys = wpdai_get_valid_reporting_utm_keys();

    $is_valid = in_array( $key, $valid_utm_keys );

    return apply_filters( 'wpd_ai_is_valid_reporting_utm_key', $is_valid, $key );

}

/**
 * 
 * 	Returns an array of valid UTM keys for reporting
 * 	These keys are used to filter reports by UTM parameters
 * 
 * 	@return array $valid_utm_keys An array of valid UTM keys
 * 
 **/
function wpdai_get_valid_reporting_utm_keys() {

	$valid_utm_keys = array(
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'utm_id',
        'ref',
        'coupon',
        'source',
        'campaign',
        'content',
        'medium',
        'term',
        's',
        'search',
        'google_cid',
        'meta_cid'
    );

	return apply_filters( 'wpd_ai_valid_reporting_utm_keys', $valid_utm_keys );

}

/**
 * Sanitize and decode JSON config data from POST request
 * 
 * Handles unslashing, URL decoding, HTML entity decoding, and JSON parsing
 * with fallback handling for double-escaped JSON.
 * 
 * @since 5.0.0
 * 
 * @param string|array $raw_data The raw POST data (string or already unslashed)
 * @param bool $url_decode Whether to URL decode the data (default: true for form-urlencoded)
 * @return array|WP_Error Decoded config array on success, WP_Error on failure
 */
function wpdai_sanitize_and_decode_json_config( $raw_data, $url_decode = true ) {
	
	// Handle array input (already processed)
	if ( is_array( $raw_data ) ) {
		return $raw_data;
	}
	
	// Ensure we have a string
	if ( ! is_string( $raw_data ) ) {
		return new WP_Error(
			'invalid_input',
			__( 'Config data must be a string or array.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
		);
	}
	
	// Return empty array if empty
	if ( empty( trim( $raw_data ) ) ) {
		return array();
	}
	
	// Unslash if needed (check if it looks like it needs unslashing)
	$config_json = wp_unslash( $raw_data );
	
	// URL decode if requested (for form-urlencoded POST data)
	if ( $url_decode ) {
		$config_json = urldecode( $config_json );
	}
	
	// Decode HTML entities if present (e.g., &quot; -> ")
	$config_json = html_entity_decode( $config_json, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	
	// First try to decode normally
	$config_data = json_decode( $config_json, true );
	$json_error = json_last_error();
	
	// If that fails, try to decode the escaped JSON (double-encoded case)
	if ( $json_error !== JSON_ERROR_NONE ) {
		$unescaped_json = stripslashes( $config_json );
		$config_data = json_decode( $unescaped_json, true );
		$json_error = json_last_error();
	}
	
	// If still failing, try removing outer quotes
	if ( $json_error !== JSON_ERROR_NONE ) {
		$trimmed_json = trim( $config_json, '"' );
		$trimmed_json = stripslashes( $trimmed_json );
		$config_data = json_decode( $trimmed_json, true );
		$json_error = json_last_error();
	}
	
	// If all attempts failed, return error
	if ( $json_error !== JSON_ERROR_NONE ) {
		return new WP_Error(
			'json_decode_failed',
			sprintf(
				/* translators: %s: JSON error message */
				__( 'Failed to parse JSON configuration: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				esc_html( json_last_error_msg() )
			),
			array(
				'json_error' => $json_error,
				'json_error_msg' => json_last_error_msg(),
				'raw_data_preview' => substr( $raw_data, 0, 200 ),
			)
		);
	}
	
	// Ensure we got an array
	if ( ! is_array( $config_data ) ) {
		return new WP_Error(
			'invalid_config_format',
			__( 'Decoded config must be an array.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
		);
	}
	
	// Sanitize the decoded array if the function exists
	if ( function_exists( 'wpdai_sanitize_json_decoded_array' ) ) {
		$config_data = wpdai_sanitize_json_decoded_array( $config_data );
	}
	
	return $config_data;
	
}