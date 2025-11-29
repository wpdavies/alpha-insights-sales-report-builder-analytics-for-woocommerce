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
 * AJAX handler for getting available dashboard reports
 *
 */
function wpd_get_available_react_reports() {

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
function wpd_is_valid_reporting_utm_key_value_pair( $key, $value ) {

    // Handle arrays (when query parameter appears multiple times, parse_str returns an array)
    if ( is_array( $value ) ) {
        // For arrays, validate each value - return true if at least one is valid
        foreach ( $value as $single_value ) {
            if ( wpd_is_valid_reporting_utm_key_value_pair( $key, $single_value ) ) {
                return true;
            }
        }
        return false;
    }

    // Default to true
    $valid = true;

    // Make sure the key is in our allowed keys
    if ( ! wpd_is_valid_reporting_utm_key( $key ) ) $valid = false;

    // Make sure the value is valid
    if ( ! wpd_is_valid_reporting_utm_value( $value ) ) $valid = false;

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
function wpd_is_valid_reporting_utm_value( $value ) {

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
function wpd_is_valid_reporting_utm_key( $key ) {

	$valid_utm_keys = wpd_get_valid_reporting_utm_keys();

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
function wpd_get_valid_reporting_utm_keys() {

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