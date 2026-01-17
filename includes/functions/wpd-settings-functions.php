<?php
/**
 *
 * Settings Related Functions
 * 
 * Returns settings with appropriate defaults, sanitization and cleaning to ensure correct formatting
 *
 * @package Alpha Insights
 * @version 4.7.0
 * @since 4.7.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * 
 *  Get payment gateway costs settings
 * 
 *  @return array
 * 
 **/
function wpdai_get_payment_gateway_cost_settings() {

	$settings = array(
		'default' => array(
			'percent_of_sales' => 0,
			'static_fee' => 0,
		)
	);

	// Get legacy settings
	$legacy_setting = get_option( 'wpd_ai_cost_defaults' );
	if ( is_array($legacy_setting) && ! empty($legacy_setting) ) {

		// Process legacy settings
		$settings['default']['percent_of_sales'] = $legacy_setting['default_payment_cost_percent'] ?? 0;
		$settings['default']['static_fee'] = $legacy_setting['default_payment_cost_fee'] ?? 0;

	}

	// Process new settings
	$new_settings = get_option( 'wpd_ai_payment_gateway_costs', array() );
	if ( is_array($new_settings) && ! empty($new_settings) ) {

		// Merge with existing settings
		$settings = array_merge( $settings, $new_settings );

	}

	// If we don't have any gateway specific costs, let's make an assumption it's an old install and check things
	if ( count($settings) == 1 ) {

		// Get available payment gateways
		$available_payment_gateways = wpdai_get_available_payment_gateways();

		// If we have available payment gateways, let's add them to the settings
		if ( count($available_payment_gateways) > 0 ) {
			foreach( $available_payment_gateways as $payment_gateway_id => $payment_gateway_data ) {
				$settings[$payment_gateway_id] = array(
					'percent_of_sales' => $legacy_setting['default_payment_cost_percent'] ?? 0,
					'static_fee' => $legacy_setting['default_payment_cost_fee'] ?? 0,
				);
			}
		}

	}

	// Make sure we have default settings
	if ( ! isset($settings['default']) ) {
		$settings['default'] = array(
			'percent_of_sales' => 0,
			'static_fee' => 0,
		);
	}


	return $settings;
}

/**
 * 
 *  Get refunded order costs settings
 * 
 *  @return array
 * 
 **/
function wpdai_get_refunded_order_costs_settings() {
    
    $default_settings = array(
        'total_product_cost_of_goods' 	=> 0,
        'total_product_custom_costs' 	=> 0,
        'total_shipping_cost' 			=> 0,
        'payment_gateway_cost' 			=> 0,
        'total_custom_order_costs' 		=> 0
    );
    
    $settings = get_option( 'wpd_ai_refunded_order_costs', $default_settings );
    
    // Ensure all expected keys exist with default values
    return wp_parse_args( $settings, $default_settings );
	
}

/**
 *
 *	Just check if analytics is enabled
 *
 */
function wpdai_is_analytics_enabled() {

	// Get settings
	$analytics_settings = get_option( 'wpd_ai_analytics', array() );

	// Have specifically set no
	if ( isset($analytics_settings['enable_woocommerce_analytics'] ) && $analytics_settings['enable_woocommerce_analytics'] == 0 ) return false;

	// Default to true
	return true;

}

/**
 * 
 *	Array of Alpha Insights column extensions for the WordPress admin 
 *	
 *	This array is used to populate the fields in settings and will dynamically
 *	populate the custom columns that are registered in the relevant WP Admin area
 *	When displaying data, the column key must match the key found in this array.
 *	Key => Value pairs with the slug as the key and the label as the value.
 *	
 *	Array Keys Available: products, orders, users
 *	
 *	@return array Multidimension array with all columns that might be used by Alpha Insights
 *	Users can opt out of this in General Settings
 *
 * 	@since 3.1.2 Removed 7 unnecessary keys from defaults, adds unnecessary load
 * 
 **/
function wpdai_get_admin_custom_column_defaults() {

	return array(

		'products' => array(
			'wpd_ai_cost_of_goods' 							=> 'Cost Of Goods',
			'wpd_ai_margin' 								=> 'Margin (%)',
			// 'wpd_ai_analytics_clicks' 					=> 'Clicks',
			// 'wpd_ai_analytics_page_views' 					=> 'Page Views',
			// 'wpd_ai_analytics_add_to_carts' 				=> 'Add To Cart',
			// 'wpd_ai_analytics_times_sold_tracked' 		=> 'Times Sold (Tracked)',
			// 'wpd_ai_analytics_atc_conversion_rate' 		=> 'ATC Conversion Rate',
			// 'wpd_ai_analytics_purchase_conversion_rate' 	=> 'Purchase Conversion Rate',
			'wpd_ai_analytics_total_qty_sold' 				=> 'Total Qty Sold',
			'wpd_ai_analytics_total_revenue' 				=> 'Total Revenue',
			'wpd_ai_analytics_total_profit' 				=> 'Total Profit',
			// 'wpd_ai_analytics_avg_sell_price' 			=> 'Avg. Sell Price'
		),
		'orders' => array(
			'wpd_ai_new_vs_returning' 						=> 'New vs Returning',
			'wpd_ai_traffic_source' 						=> 'Traffic Source',
			'wpd_ai_campaign' 								=> 'Campaign'
		),
		'users'	=> array(
			'wpd_ai_sessions' 								=> 'Sessions',
			'wpd_ai_orders' 								=> 'Orders',
			'wpd_ai_ltv' 									=> 'Lifetime Value',
			'wpd_ai_aov' 									=> 'Average Order Value',
			'wpd_ai_conversion_rate' 						=> 'Conversion Rate',
			'wpd_ai_date_registered' 						=> 'Registration Date'
		)
		
	);

}

/**
 * 
 *	Array of Alpha Insights column extensions for the WordPress admin 
 *	
 *	This function will retrieve the saved settings for the columns returning an key => value
 *	array with the value being set to 1 or 0 dictating whether or not the columns should be used
 *	Will default to all active results from wpd_get_admin_custom_columns if the setting does not exist
 *	
 *	Array Keys Available: products, orders, users
 *
 * 	Store meta key is: wpd_ai_admin_custom_columns
 *	
 *	@return array Multidimension array with all columns that are set to active to be used by Alpha Insights
 *	Users can opt out of this in General Settings
 * 
 **/
function wpdai_get_admin_custom_column_settings() {

	$admin_column_settings = wpdai_get_admin_custom_column_defaults();
	$user_admin_column_settings = get_option( 'wpd_ai_admin_custom_columns' );

	// Make sure the 
	if ( is_array( $user_admin_column_settings ) ) {

		// New Custom Array
		$custom_column_settings = array();

		// Check user settings against the default array to match format & available keys
		foreach( $user_admin_column_settings as $post_type => $post_type_columns ) {

			foreach( $post_type_columns as $column_key => $column_label ) {

				// Check if the key exists in default, and then set the appropriate key in the new array with the default's label
				if ( isset($admin_column_settings[$post_type][$column_key]) ) $custom_column_settings[$post_type][$column_key] = $admin_column_settings[$post_type][$column_key];

			}

		}

		// Set new customized array that's bee checked against our defaults
		$admin_column_settings = $custom_column_settings;

	}

	return $admin_column_settings;

}

/**
 * 
 * 	Get permitted roles to view Alpha Insights
 * 
 * 	@return array of user roles slug strings that are permitted to view Alpha Insights
 * 
 * 	@since 5.0.0
 * 	@version 5.0.0
 * 	@author WPDavies
 * 	@link https://wpdavies.dev/
 * 
 **/
function wpdai_get_authorized_user_roles_settings() {

	$authorized_roles = get_option( 'wpd_ai_plugin_visibility' );

	if ( ! is_array( $authorized_roles ) || empty( $authorized_roles ) ) {
		global $wp_roles;

		$authorized_roles = array();

		foreach ( $wp_roles->roles as $role => $details ) {
			if ( ! empty( $details['capabilities']['publish_posts'] ) ) {
				$authorized_roles[] = $role;
			}
		}
	}

	if ( ! in_array( 'administrator', $authorized_roles, true ) ) {
		$authorized_roles[] = 'administrator';
	}

	return array_values( array_unique( $authorized_roles ) );

}