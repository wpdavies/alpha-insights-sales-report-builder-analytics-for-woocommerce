<?php
/**
 *
 * Alpha Insights Settings
 *
 * @package Alpha Insights
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 *	Handle default options and saving of options
 *	@link https://wpdavies.dev/docs/wpd-alpha-insights/settings/
 *	@author WPDavies
 *	@link https://wpdavies.dev/
 * 	@since 1.0.0
 * 	@version 5.0.0
 * 	@todo Convert this into a static class
 */
add_action( 'admin_init', 'wpd_ai_register_settings' );
function wpd_ai_register_settings() {

	/**
	 *
	 *	Currency Table Defaults
	 *
	 */
	$currency_table_default_data = wpd_get_default_currency_conversion_rates();
	add_option( 'wpd_ai_currency_table', $currency_table_default_data ); 

	/**
	 *
	 *	Order Status Defaults
	 *
	 */
	$order_status_default_data = wpd_paid_order_statuses();
	add_option( 'wpd_ai_order_status', $order_status_default_data );

	/**
	 * 
	 * 	Default batch size for cache building (orders)
	 * 
	 **/
	add_option( 'wpd_ai_cache_build_batch_size', 250 );

	/**
	 *
	 *	Order Status Defaults
	 *
	 */
	global $wp_roles;
	$all_roles = array_keys( $wp_roles->roles );
	add_option( 'wpd_ai_plugin_visibility', $all_roles );

	/**
	 *
	 *	Cost Defaults
	 *
	 */
    $cost_default_data = array(
        'default_product_cost_percent' 						=> 0,
        'default_shipping_cost_percent' 					=> 0,
        'default_shipping_cost_percent_shipping_charged' 	=> 0,
        'default_shipping_cost_fee' 						=> 0,
    );
	add_option( 'wpd_ai_cost_defaults', $cost_default_data );

	$payment_gateway_cost_settings = wpd_get_payment_gateway_cost_settings();
	add_option( 'wpd_ai_payment_gateway_costs', $payment_gateway_cost_settings );

	/**
	 *
	 *	Custom Admin Columns
	 *
	 */
    $custom_admin_columns = wpd_get_admin_custom_column_settings();
	add_option( 'wpd_ai_admin_custom_columns', $custom_admin_columns );

	/**
	 * 
	 * 	Costs to include when an order is fully refunded
	 * 
	 **/
	$refunded_order_costs = array(

		'total_product_cost_of_goods' 	=> 0,
		'total_product_custom_costs' 	=> 0,
		'total_shipping_cost' 			=> 0,
		'payment_gateway_cost' 			=> 0,
		'total_custom_order_costs' 		=> 0

	);
	add_option( 'wpd_ai_refunded_order_costs', $refunded_order_costs );

	/**
	 *
	 *	Facebook Settings
	 *
	 */
    $facebook_integration_data = array(
        'access_token' 						=> null,
        'access_token_validated' 			=> null,
        'access_token_expiry_date_unix' 	=> null,
        'ad_account_id' 					=> null,
        'last_api_test_unix' 				=> null,
        'api_status' 		 				=> "Not Configured",
        'api_status_message' 		 		=> null,
        'api_ad_account_name' 				=> null,
        'request_timeout' 					=> 5,
        'collect_daily_ad_spend' 			=> "true",
        'facebook_api_call_schedule' 		=> "3-hrs",
        'collect_campaign_insights' 		=> "true",
        'api_limit_per_page' 				=> 50,
        'facebook_expense_category_id' 		=> '',
    );
	add_option( 'wpd_ai_facebook_integration', $facebook_integration_data );

	/**
	 *
	 *	Google Settings
	 *
	 */
    $google_ads_api = array(
        'refresh_token' 					=> null,
        'ad_account_id' 					=> null,
        'last_api_test_unix' 				=> null,
        'api_status' 		 				=> "Not Configured",
        'api_status_message' 		 		=> null,
        'request_timeout' 					=> 5,
        'collect_daily_ad_spend' 			=> "true",
        'api_call_schedule' 				=> "3-hrs",
        'collect_campaign_insights' 		=> "true",
        'api_limit_per_page' 				=> 50,
        'expense_category_id' 				=> null,
		'account_age_years' 				=> 10
    );
	add_option( 'wpd_ai_google_ads_api', $google_ads_api );

	/**
	 *
	 *	Analytics_settings
	 *
	 */
    $analytics_settings = array(
        'enable_woocommerce_analytics' 		=> 1,
        'exclude_roles' 					=> array(),
    );
	add_option( 'wpd_ai_analytics', $analytics_settings );

	/**
	 *
	 *	Admin Style Settings
	 *
	 */
	add_option( 'wpd_ai_admin_style_override', 0 );
	add_option( 'wpd_ai_prevent_wp_notices', 0 );

	/**
	 *
	 *	Default email settings
	 *
	 */
	$admin_email = get_option( 'admin_email' );
	$email_default_settings = array (
		'appearance' => array(
			'header' => 1,
			'footer' => 1,
		),
        'profit-report' => array (
            'recipients' => $admin_email,
            'frequency' => array (
            ),
            'details' => array (
                'order_revenue' => 1,
                'order_cost' => 1,
                'order_profit' => 1,
                'order_count' => 1,
                'average_order_value' => 1,
                'average_profit_per_order' => 1,
                'total_products_sold' => 1,
                'total_product_discounts' => 1,
                'total_refunds' => 1,
                'additional_expenses' => 1,
                'net_profit' => 1,
            ),
            'attachment' => array(
                'pl-statement' => 1,
            ),
        ),
        'expense-report' => array (
            'recipients' => $admin_email,
            'frequency' => array(
            ),
            'details' => array(
                'total_expenses_paid' => 1,
                'total_no_expenses' => 1,
                'average_expenses_per_day' => 1,
                'parent_expenses' => 1,
                'child_expenses' => 1,
            ),
            'attachment' => array(
                'expense-report' => 1,
            ),
        )
	);

	add_option( 'wpd_ai_email_settings', $email_default_settings );

	/**
	 *
	 *	License
	 *
	 */
	add_option( 'wpd_ai_api_key', null );
	add_option( 'wpd_ai_license_status',  null );
	add_option( 'wpd_ai_license_details',  null );

	/**
	 *
	 *	Webhooks
	 *
	 */
	$default_webhook_data = array(
		'webhook_url' 				=> '',
		'webhook_schedule' 			=> 'none',
		'webhook_schedule_last_run' => false,
	);
	add_option( 'wpd_ai_webhooks', $default_webhook_data );

	/**
	 *
	 *	Submit POST data
	 *
	 */
	if ( isset($_GET['page']) && sanitize_text_field( $_GET['page'] ) === WPD_Admin_Menu::$settings_slug ) {
		
		// Only process form submission if POST data exists and submit button was clicked
		if ( isset( $_POST['submit'] ) && ! empty( $_POST['submit'] ) ) {
			
			// Security: Only allow authorized users to save settings
			if ( wpd_is_user_authorized_to_view_alpha_insights() ) {
				// Verify nonce for settings form submission
				if ( isset( $_POST['wpd_alpha_insights_settings_nonce'] ) && wp_verify_nonce( $_POST['wpd_alpha_insights_settings_nonce'], 'wpd_alpha_insights_settings' ) ) {
					wpd_save_settings();
				} else {
					// Nonce verification failed - show error but don't die
					add_action( 'admin_notices', function() {
						echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Security check failed. Please try again.', 'wpd-alpha-insights' ) . '</p></div>';
					} );
				}
			}
			
		}
		
	}

}

/** 
 *
 *	Save and Process all settings on admin page
 *
 */
function wpd_save_settings() {

	$saved = array();

	// Save Payment Gateway Costs
	if ( isset($_POST['wpd_ai_payment_gateway_costs']) ) {

		if ( is_array($_POST['wpd_ai_payment_gateway_costs']) ) {

			$sanitized_array = array();

			foreach($_POST['wpd_ai_payment_gateway_costs'] as $payment_gateway_id => $payment_gateway_cost_data ) {

				$sanitized_array[$payment_gateway_id] = array(
					'percent_of_sales' => (float) $payment_gateway_cost_data['percent_of_sales'] ?? 0,
					'static_fee' => (float) $payment_gateway_cost_data['static_fee'] ?? 0,
				);

			}

			// Make sure we have default settings
			if ( ! isset($sanitized_array['default']) ) {
				$sanitized_array['default'] = array(
					'percent_of_sales' => 0,
					'static_fee' => 0,
				);
			}
			
			// Merge with existing settings
			$sanitized_array = array_merge( get_option( 'wpd_ai_payment_gateway_costs', array() ), $sanitized_array );

		}

		// Save settings
		$saved['Payment Gateway Costs'] = update_option( 'wpd_ai_payment_gateway_costs', $sanitized_array );

		// Delete cache if updated
		if ($saved['Payment Gateway Costs']) $delete_cache = wpd_delete_all_order_data_cache();

	}

	// Custom Columns In WP Admin
	if ( isset( $_POST['wpd_ai_admin_custom_columns'] ) ) {

		if ( is_array($_POST['wpd_ai_admin_custom_columns']) ) {

			$saved_custom_column_settings 	= $_POST['wpd_ai_admin_custom_columns'];
			$default_column_data 			= wpd_get_admin_custom_column_defaults();
			$new_settings_array 			= array();

			foreach( $saved_custom_column_settings as $group_key => $saved_keys ) {

				if ( ! is_array($saved_keys) ) continue;

				$group_key = sanitize_text_field( $group_key );

				foreach( $saved_keys as $saved_key ) {

					$saved_key = sanitize_text_field( $saved_key );
					$new_settings_array[$group_key][$saved_key] = $default_column_data[$group_key][$saved_key];

				}

			}

			$saved['Admin Custom Columns'] = update_option( 'wpd_ai_admin_custom_columns', $new_settings_array );

		}

	}

	// Refunded order costs
	if ( isset($_POST['wpd-refunded-order-costs']) && is_array($_POST['wpd-refunded-order-costs']) ) {

		// Default values
		$refunded_order_costs = array(
			'total_product_cost_of_goods' 	=> 0,
			'total_product_custom_costs' 	=> 0,
			'total_shipping_cost' 			=> 0,
			'payment_gateway_cost' 			=> 0,
			'total_custom_order_costs' 		=> 0
		);

		// Merge with existing values
		$refunded_order_costs = array_merge( $refunded_order_costs, array_map( 'intval', $_POST['wpd-refunded-order-costs'] ) );
		$saved['Refunded Order Costs'] = update_option( 'wpd_ai_refunded_order_costs', $refunded_order_costs );
		if ( $saved['Refunded Order Costs'] ) $delete_cache = wpd_delete_all_order_data_cache();

	}

	// Custom Order Costs
	if ( isset($_POST['wpd_ai_custom_order_cost']) && is_array($_POST['wpd_ai_custom_order_cost']) ) {

		// Empty array
		$custom_order_cost_settings = array();

		// Loop through passed details
		foreach( $_POST['wpd_ai_custom_order_cost'] as $slug => $custom_order_cost_data ) {

			// Skip over empty payloads
			if ( ! array_filter($custom_order_cost_data) ) continue;

			// Get the label
			$label = ( isset($custom_order_cost_data['label']) && ! empty($custom_order_cost_data['label']) ) ? sanitize_text_field( $custom_order_cost_data['label'] ) : 'Unknown';

			// Collect and sanitize other values
			$slug 					= ( isset($custom_order_cost_data['slug']) && ! empty($custom_order_cost_data['slug']) ) ? sanitize_text_field( $custom_order_cost_data['slug'] ) : sanitize_title( $label );
			$static_fee 			= ( isset($custom_order_cost_data['static_fee']) ) ? wc_format_decimal( $custom_order_cost_data['static_fee'] ) : 0;
			$percent_of_order_value = ( isset($custom_order_cost_data['percent_of_order_value']) ) ? wc_format_decimal( $custom_order_cost_data['percent_of_order_value'] ) : 0;

			// Store results in array
			$custom_order_cost_settings[$slug] = array(
				'label' => $label,
				'static_fee' => $static_fee,
				'percent_of_order_value' => $percent_of_order_value
			);

		}

		// If weve got valid data, let's save
		if ( is_array($custom_order_cost_settings) ) {
			$saved['Custom Order Costs'] = update_option( 'wpd_ai_custom_order_costs',  $custom_order_cost_settings );
			if ($saved['Custom Order Costs']) $delete_cache = wpd_delete_all_order_data_cache();
		}

	}

	// Custom Product Costs
	if ( isset($_POST['wpd_ai_custom_product_cost']) && is_array($_POST['wpd_ai_custom_product_cost']) ) {

		// Empty array
		$custom_product_cost_settings = array();

		// Loop through passed details
		foreach( $_POST['wpd_ai_custom_product_cost'] as $slug => $custom_product_cost_data ) {

			// Skip over empty payloads
			if ( ! array_filter($custom_product_cost_data) ) continue;

			// Get the label
			$label = ( isset($custom_product_cost_data['label']) && ! empty($custom_product_cost_data['label']) ) ? sanitize_text_field( $custom_product_cost_data['label'] ) : 'Unknown';

			// Collect and sanitize other values
			$slug 					= ( isset($custom_product_cost_data['slug']) && ! empty($custom_product_cost_data['slug']) ) ? sanitize_text_field( $custom_product_cost_data['slug'] ) : sanitize_title( $label );
			$static_fee 			= ( isset($custom_product_cost_data['static_fee']) ) ? wc_format_decimal( $custom_product_cost_data['static_fee'] ) : 0;
			$percent_of_order_value = ( isset($custom_product_cost_data['percent_of_sell_price']) ) ? wc_format_decimal( $custom_product_cost_data['percent_of_sell_price'] ) : 0;

			// Store results in array
			$custom_product_cost_settings[$slug] = array(
				'label' => $label,
				'static_fee' => $static_fee,
				'percent_of_sell_price' => $percent_of_order_value
			);

		}

		// If weve got valid data, let's save
		if ( is_array($custom_product_cost_settings) ) {
			$saved['Custom Product Costs'] = update_option( 'wpd_ai_custom_product_costs',  $custom_product_cost_settings );
			if ($saved['Custom Product Costs']) $delete_cache = wpd_delete_all_order_data_cache();
		}

	}

	// Analytics Settings
	if ( isset($_POST['wpd_ai_analytics']) ) {
		$enable_woocommerce_analytics = ( isset($_POST['wpd_ai_analytics']['enable_woocommerce_analytics']) ) ? intval($_POST['wpd_ai_analytics']['enable_woocommerce_analytics']) : 0;
		$exclude_roles = ( isset($_POST['wpd_ai_analytics']['exclude_roles']) ) ? array_map('sanitize_text_field', $_POST['wpd_ai_analytics']['exclude_roles']) : array();
		$analytics_settings = array(
			'enable_woocommerce_analytics' => $enable_woocommerce_analytics,
			'exclude_roles' => $exclude_roles
		);
		$saved['Analytics Settings'] = update_option( 'wpd_ai_analytics',  $analytics_settings );
	}

	// Cache Build Batch Size
	if ( isset($_POST['wpd_ai_cache_build_batch_size']) && is_numeric($_POST['wpd_ai_cache_build_batch_size']) ) {
		$batch_size = intval( $_POST['wpd_ai_cache_build_batch_size'] );
		if ( $batch_size > 0 ) {
			$saved['Cache Build Batch Size'] = update_option( 'wpd_ai_cache_build_batch_size',  $batch_size );
		}
	}

	// Cost Defaults
	if ( isset( $_POST['wpd_ai_cost_defaults'] ) ) {
		$cost_default_data = array(
			'default_product_cost_percent' 						=> 0,
			'default_shipping_cost_percent' 					=> 0,
			'default_shipping_cost_percent_shipping_charged' 	=> 0,
			'default_shipping_cost_fee' 						=> 0,
		);
		$cost_default_data = array_merge( $cost_default_data, array_map( 'floatval', $_POST['wpd_ai_cost_defaults'] ) );
		$saved['Product Cost Defaults'] = update_option( 'wpd_ai_cost_defaults',  $cost_default_data );

		// Wipe cache if weve updated our calculations
		if ( $saved['Product Cost Defaults'] ) {

			$delete_cache = wpd_delete_all_order_data_cache();

			if ( $delete_cache === true ) {
				wpd_notice(
					__( 'Your reports cache will be updated in the background to reflect your new calculation settings.', 'wpd-alpha-insights' )
				);
			} else {
				wpd_notice( 
					 __( 'We could not refresh your cache, try using the cache refresh buttons at the bottom of this page to reflect your new calculation settings.', 'wpd-alpha-insights' )
				);
			}

		}

	}

	//Facebook Integration
	if ( isset( $_POST['wpd_ai_facebook_integration'] ) ) {
		if ( is_array($_POST['wpd_ai_facebook_integration']) ) {
			$stored_facebook_settings = get_option( 'wpd_ai_facebook_integration', array() );
			$facebook_setting = array_map( 'sanitize_text_field', $_POST['wpd_ai_facebook_integration'] );
			$facebook_setting = array_merge( $stored_facebook_settings, $facebook_setting );
			$saved['Facebook'] = update_option( 'wpd_ai_facebook_integration',  $facebook_setting );
			if ($saved['Facebook']) {
				as_unschedule_all_actions('wpd_schedule_facebook_api_call');
			}
		}
	}

	// Google Ads API
	if ( isset( $_POST['wpd_ai_google_ads_api'] ) ) {
		if ( is_array($_POST['wpd_ai_google_ads_api']) ) {
			$store_settings = get_option( 'wpd_ai_google_ads_api', false );
			$updated_api_settings = array_map('sanitize_text_field', $_POST['wpd_ai_google_ads_api']);
			$google_api_settings = array_merge( $store_settings, $updated_api_settings );
			$saved['Google Ads API'] = update_option( 'wpd_ai_google_ads_api',  $google_api_settings );
			if ($saved['Google Ads API']) {
				as_unschedule_all_actions('wpd_schedule_google_data_fetch');
			}
		}
	}

	// Google Ads Profit Conversion Action
	if ( isset( $_POST['wpd_ai_google_ads_profit_conversion_action_id'] ) ) {
		$profit_conversion_action_id = sanitize_text_field( $_POST['wpd_ai_google_ads_profit_conversion_action_id'] );
		$saved['Google Ads Profit Conversion Action'] = update_option( 'wpd_ai_google_ads_profit_conversion_action_id', $profit_conversion_action_id );
	}

	// Order Status Settings - wpd_ai_order_status
	if ( isset( $_POST['wpd_ai_order_status'] ) ) {
		$saved['Default Order Status'] = update_option( 'wpd_ai_order_status',  array_map( 'sanitize_text_field', $_POST['wpd_ai_order_status'] ));
	}

	// Plugin visibility - wpd_ai_plugin_visibility
	if ( isset( $_POST['wpd_ai_plugin_visibility'] ) ) {

		// Initialize authorized roles array
		$authorized_roles = array();
		if ( ! is_array($_POST['wpd_ai_plugin_visibility']) ) {
			$authorized_roles = array();
		} else {
			$authorized_roles = array_map( 'sanitize_text_field', $_POST['wpd_ai_plugin_visibility'] );
		}
		// Force administrators to be included
		if ( ! in_array('administrator', $authorized_roles) ) {
			$authorized_roles[] = 'administrator';
		}
		$saved['Plugin Visibility'] = update_option( 'wpd_ai_plugin_visibility',  $authorized_roles );
	}

	// Override WP CSS
	if ( isset( $_POST['wpd_ai_admin_style_override'] ) ) {
		$admin_override = wpd_numbers_only( $_POST['wpd_ai_admin_style_override'] );
		if ( is_numeric( $admin_override ) ) {
			$saved['Admin Override'] = update_option( 'wpd_ai_admin_style_override',  $admin_override );
		}
	}

	// Prevent WP Notices
	if ( isset( $_POST['wpd_ai_prevent_wp_notices'] ) ) {
		$prevent_notices = wpd_numbers_only( $_POST['wpd_ai_prevent_wp_notices'] );
		if ( is_numeric($prevent_notices) ) {
			$saved['Prevent WP Notices'] = update_option( 'wpd_ai_prevent_wp_notices',  $prevent_notices );
		}
	}

	if ( isset( $_POST['wpd-email'] ) ) {

		$emails = $_POST['wpd-email'];

		if ( ! is_array($emails) ) {

			$emails = array();

		} else {

			// Check & store email addresses
			$emails['profit-report']['recipients'] = sanitize_text_field( $emails['profit-report']['recipients'] );
			$emails['expense-report']['recipients'] = sanitize_text_field( $emails['expense-report']['recipients'] );

			// Only allows numbers through details
			$emails['profit-report']['details'] = array_map( 'wpd_numbers_only', $emails['profit-report']['details'] );
			$emails['expense-report']['details'] = array_map( 'wpd_numbers_only', $emails['expense-report']['details'] );

		}

		// Store details
		$saved['Email'] = update_option( 'wpd_ai_email_settings',  $emails );

	}

	// Webhook data
	if ( isset( $_POST['wpd_ai_webhook_settings'] ) ) {

		$webhook_data = array_map( 'sanitize_text_field', $_POST['wpd_ai_webhook_settings'] );
		$saved['Webhook Settings'] = update_option( 'wpd_ai_webhook_settings', $webhook_data );

		if ( $saved['Webhook Settings'] ) {
			as_unschedule_all_actions('wpd_schedule_webhook');
		}

	}

	// License key
	if ( isset($_POST['wpd_ai_api_key']) ) {
		
		$api_key = sanitize_text_field( $_POST['wpd_ai_api_key'] );
		$saved['API key'] = update_option( 'wpd_ai_api_key',  $api_key );

		$authenticator = new WPD_Authenticator();
		$activate_license = $authenticator->activate_license();

		if ( isset($activate_license['message']) ) {
			wpd_notice( $activate_license['message'] );
		}

	}

	/**
	 *
	 *	Output notice for those settings that have been saved
	 *
	 */
	foreach( $saved as $setting => $save_status ) {

		if ( $save_status === true ) {

			wpd_notice( 
				sprintf( __( '%s settings have been updated', 'wpd-alpha-insights' ), $setting )
			);

		}

	}

	/**
	 *
	 *	Also, lets call any other notices we want at this point
	 *
	 */
	wpd_output_additional_notices();

}

/** 
 *
 *	Output any arbitrary notices
 *
 */
function wpd_output_additional_notices() {

	if ( isset($_GET['wpd-notice']) && sanitize_text_field( $_GET['wpd-notice'] ) === 'invalid-license' ) {

		$authenticator = new WPD_Authenticator();
		$license_status = $authenticator->is_license_active();

		if ( $license_status ) {

			// Redirect away from ntoice if they've updated their settings
			$license_page = wpd_admin_page_url( 'settings-license' );
			wp_redirect( $license_page );
			exit;

		}
	}

}

/**
 *
 *	Prevent notices if setting is enabled
 *
 */
add_action('admin_head', 'wpd_hide_wp_notices');
function wpd_hide_wp_notices() {

	$prevent_notices = get_option( 'wpd_ai_prevent_wp_notices' );

	if ( $prevent_notices ) {

		?>
		<style type="text/css">
			/* Hide admin notices on my pages */
			.notice, .updated, .update-nag {
			    display: none !important;
			}
			.woocommerce-embed-page .woocommerce-store-alerts {
			    display: none;
			}
			.notice.wpd-notice, .plugin-update .notice {
			    display: block !important;
			}
	  	</style>
		<?php

	}

}