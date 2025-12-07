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
if ( ! function_exists( 'wpd_verify_ajax_request' ) ) {
	function wpd_verify_ajax_request( $nonce_action = null, $capability = 'manage_options' ) {
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
}

/**
 *
 *	Send email via ajax
 *
 */
if ( ! function_exists('wpd_javascript_email_ajax') ) {

	function wpd_javascript_email_ajax( $click_selector, $email_to_send ) {

	?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				jQuery('<?php echo esc_js( $click_selector ); ?>').click(function(e) {
					e.preventDefault();
					wpdPopNotification( 'loading', '<?php echo esc_js( __( 'Processing...', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>', '<?php echo esc_js( __( 'We are working on it!', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>' );
			        var data = {
			            'action': 'wpd_send_email',
			            'email' : '<?php echo esc_js( $email_to_send ); ?>',
			            'url'   : window.location.href,
			            'nonce' : (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.nonce) ? wpdAlphaInsights.nonce : ''
			        };
			        var ajaxurl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
			        $.post(ajaxurl, data)
			        .done(function( response ) {
			    		var parsedResponse = wpdHandleAjaxResponse(
			    			response,
			    			'<?php echo esc_js( __( 'Your email has been successfully sent.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>',
			    			'<?php echo esc_js( __( 'Your email was not sent.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>'
			    		);
			        })
			        .fail(function( jqXHR, textStatus, errorThrown ) {
			    		var errorMessage = '<?php echo esc_js( __( 'Your email was not sent.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>';
			    		if (jqXHR.responseText) {
			    			try {
			    				var errorResponse = JSON.parse(jqXHR.responseText);
			    				errorMessage = wpdExtractResponseMessage(errorResponse, errorMessage);
			    			} catch(e) {
			    				// If we can't parse the error, use default message
			    			}
			    		}
			    		wpdPopNotification( 'fail', '<?php echo esc_js( __( 'Email Failed', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>', errorMessage );
			        });
			    });
			});
		</script>
	<?php

	}

}

/**
 *
 *	Send email via ajax
 *
 */
if ( ! function_exists('wpd_javascript_ajax_action') ) {

	function wpd_javascript_ajax_action( $click_selector, $action, $args = null ) {

		$form_selector = 'form';

		// Process args
		if ( isset( $args['form_selector'] ) && ! empty($args['form_selector']) ) {
			$form_selector = $args['form_selector'];
		}

	?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				jQuery('<?php echo esc_js( $click_selector ); ?>').click(function(e) {
					e.preventDefault();
					wpdPopNotification( 'loading', '<?php echo esc_js( __( 'Processing...', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>', '<?php echo esc_js( __( 'We are working on it!', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>' );
					var formData = $('<?php echo esc_js( $form_selector ); ?>').serializeArray();
			        var data = {
			            'action': '<?php echo esc_js( $action ); ?>',
			            'url'   : window.location.href,
			            'form' 	: formData,
			            'nonce' : (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.nonce) ? wpdAlphaInsights.nonce : ''
			        };
			        var ajaxurl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
			        $.post(ajaxurl, data)
			        .done(function( response ) {
			    		var parsedResponse = wpdHandleAjaxResponse(
			    			response,
			    			'<?php echo esc_js( __( 'Your request has been successfully completed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>',
			    			'<?php echo esc_js( __( 'Your action could not be completed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>'
			    		);
			    		if (parsedResponse && parsedResponse.success) {
			    			window.postMessage(parsedResponse, "*"); // jQuery(window).on("message", function(e) {});
			    		}
			        })
			        .fail(function( jqXHR, textStatus, errorThrown ) {
			    		var errorMessage = '<?php echo esc_js( __( 'Your action could not be completed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>';
			    		if (jqXHR.responseText) {
			    			try {
			    				var errorResponse = JSON.parse(jqXHR.responseText);
			    				errorMessage = wpdExtractResponseMessage(errorResponse, errorMessage);
			    			} catch(e) {
			    				// If we can't parse the error, use default message
			    			}
			    		}
			    		wpdPopNotification( 'fail', '<?php echo esc_js( __( 'Request Failed', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>', errorMessage );
			        });
			    });
			});
		</script>
	<?php

	}

}

/**
 * 
 * Ajax Request to delete all order meta overrides
 * 
 */
add_action( 'wp_ajax_wpd_reset_order_meta', 'wpd_reset_order_meta' );
function wpd_reset_order_meta() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	// Default
	$response = array();

	// Execute the delete function
	$deleted_rows = wpd_delete_all_order_meta_overrides();

	if ( is_numeric($deleted_rows) ) {

		$response['success']	= true;
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
add_action( 'wp_ajax_wpd_delete_order_line_item_cogs', 'wpd_delete_order_line_item_cogs_ajax' );
function wpd_delete_order_line_item_cogs_ajax() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	// Default
	$response = array();

	// Execute the delete function
	$deleted_rows = wpd_delete_all_order_line_item_meta_cogs();

	// Build a response
	if ( is_numeric($deleted_rows) ) {

		$response['success']	= true;
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
add_action( 'wp_ajax_wpd_delete_all_cache', 'wpd_delete_all_cache_ajax', 10 );
function wpd_delete_all_cache_ajax() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	// Immediately delete
	$delete_all_cache = wpd_delete_all_data_caches();

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
add_action('wp_ajax_wpd_export_inventory_to_csv', 'wpd_export_inventory_to_csv' );
function wpd_export_inventory_to_csv() {
	// Verify security - the method will handle its own checks, but verify here too
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}
	// Redirect to new export method
	WPD_Cost_Of_Goods_Manager::ajax_export_csv();
}


/**
 * Ajax handler for generating PDF from live link (React Reports)
 * 
 * This is an example AJAX handler that demonstrates how to use the
 * wpd_generate_pdf_from_report_slug() function for React-based reports.
 * 
 * @since 4.7.0
 */
add_action('wp_ajax_wpd_export_react_report_to_pdf', 'wpd_export_react_report_to_pdf' );
function wpd_export_react_report_to_pdf() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
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
		$response = wpd_generate_pdf_from_report_slug( $report_slug );

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
add_action('wp_ajax_wpd_send_email', 'wpd_send_email_ajax' );
function wpd_send_email_ajax() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	$requesting_url = isset( $_POST['url'] ) ? wpd_sanitize_url( $_POST['url'] ) : '';
	$email_to_send = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
	
	if ( empty( $email_to_send ) || ! is_email( $email_to_send ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid email address.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
		return;
	}
	
	$response = wpd_email( $email_to_send, false );
	wp_send_json( $response );

}

/**
 *
 *	Manually send out data to webhook
 *
 */
add_action('wp_ajax_wpd_webhook_export_manual', 'wpd_webhook_export_manual' );
function wpd_webhook_export_manual() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	$response = wpd_webhook_post_data();
	wp_send_json( $response );

}

/**
 *
 *	Ajax request for deactivating license
 *
 */
add_action('wp_ajax_wpd_deactivate_license', 'wpd_deactivate_license_ajax_function' );
function wpd_deactivate_license_ajax_function() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	$authenticator = new WPD_Authenticator();
	$deactivate = $authenticator->deactivate_license();

	if ( $deactivate['result'] === 'success' ) {

		$response['success']	= true;
		$response['message']	= __( 'License deactivated succesfully, please refresh this page.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		update_option( 'wpd_ai_license_status', null );

	} else {

		$response['success']	= false;
		$response['message'] 	= __( 'Could not deactivate your license key, try again later.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	wp_send_json( $response );

}

/**
 *
 *	Ajax request for activating license
 *
 */
add_action('wp_ajax_wpd_activate_license', 'wpd_activate_license_ajax_function' );
function wpd_activate_license_ajax_function() {

	// Verify security
	if ( ! wpd_verify_ajax_request()) {
		return;
	}

	if ( ! WPD_AI_PRO || ! class_exists('WPD_Authenticator') ) {	

		$response['success'] = false;
		$response['message'] = __( 'This is a Pro feature.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		wp_send_json( $response );
		return;

	}

	$authenticator = new WPD_Authenticator();
	$activate = $authenticator->activate_license();

	if ( $activate['result'] === 'success' ) {

		$response['success'] = true;
		$response['message'] = __( 'License activated succesfully, please refresh this page.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	} elseif ( $activate['result'] == 'error' ) {

		$response['success'] = false;
		$error_message = isset( $activate['message'] ) ? sanitize_text_field( $activate['message'] ) : '';
		$response['message'] = sprintf( __( 'Could not activate license, %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $error_message );

	} else {

		$response['success'] = false;

		if ( ! empty( $activate['message'] ) ) {

			$response['message'] = sanitize_text_field( $activate['message'] );

		} else {

			$response['message'] = __( 'Could not activate your license key, try again later.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

		}

	}

	wp_send_json( $response );

}

/**
 *
 *	Ajax request for refreshing license status
 *
 */
add_action('wp_ajax_wpd_refresh_license', 'wpd_refresh_license_ajax_function' );
function wpd_refresh_license_ajax_function() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	$authenticator = new WPD_Authenticator();
	$license_status = $authenticator->hard_refresh_license();

	if ( $license_status ) {

		$response['success'] = true;
		$response['message'] = sprintf( __( 'Your license is currently %s. All your license details have been updated, please refresh this page.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $license_status ) );

	} else {

		$response['success'] = false;
		$response['message'] = __( 'Could not fetch your license data, try again later.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	wp_send_json( $response );

}

/**
 *
 *	Ajax request for capturing all Facebook data
 *
 */
add_action('wp_ajax_wpd_refresh_all_facebook_api_data', 'wpd_refresh_all_facebook_api_data_ajax_function' );
function wpd_refresh_all_facebook_api_data_ajax_function() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	// Take action
	$result = wpd_schedule_facebook_api_call_function();

	if ( ! empty( $result ) ) {

		$campaigns_found = isset( $result['campaigns_found'] ) ? absint( $result['campaigns_found'] ) : 0;
		$response['success'] = true;
		$response['message'] = sprintf( __( 'Success! %s campaign were found, we\'ve created and updated all of your Facebook data, please refresh this page.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $campaigns_found );

	} else {

		$response['success'] = false;
		$response['message'] = __( 'Could not load Facebook API data. You might not have any data to access or your connection may not be configured correctly.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	wp_send_json( $response );

}

/**
 *
 *	Ajax request for deleting all facebook ad expenses
 *
 */
add_action('wp_ajax_wpd_delete_all_facebook_api_expense_data', 'wpd_delete_all_facebook_api_expense_data_ajax_function' );
function wpd_delete_all_facebook_api_expense_data_ajax_function() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	// Take action
	$response = array();
	if ( WPD_AI_PRO ) {

		$facebook_api = new WPD_Facebook_API();
		$result = $facebook_api->delete_all_api_ad_spend_data();
		
		if ( $result ) {
	
			$response['success'] = true;
			$response['message'] = sprintf( __( 'Sucesfully deleted %s Facebook API Ad Spend data points.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), absint( $result ) );
	
		} else {
	
			$response['success'] = false;
			$response['message'] = __( 'No expenses were removed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
	
		}

	} else {

		$response['success'] = false;
		$response['message'] = __( 'This is a Pro feature.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	wp_send_json( $response );

}

/**
 *
 *	Ajax request for deleting all facebook campaign data
 *
 */
add_action('wp_ajax_wpd_delete_all_facebook_api_campaign_data', 'wpd_delete_all_facebook_api_campaign_data_ajax_function' );
function wpd_delete_all_facebook_api_campaign_data_ajax_function() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	// Take action
	$response = array();
	if ( WPD_AI_PRO ) {

		$facebook_api = new WPD_Facebook_API();
		$result = $facebook_api->delete_all_api_campaign_data();
		
		if ( $result ) {
	
			$response['success'] = true;
			$response['message'] = sprintf( __( 'Sucesfully deleted %s Facebook API campaign data points.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), absint( $result ) );
	
		} else {
	
			$response['success'] = false;
			$response['message'] = __( 'No campaigns were removed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
	
		}

	} else {

		$response['success'] = false;
		$response['message'] = __( 'This is a Pro feature.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	wp_send_json( $response );

}

/**
 *
 *	Ajax request for testing Facebook API status
 *
 */
add_action('wp_ajax_wpd_test_facebook_api_status', 'wpd_test_facebook_api_status_ajax_function' );
function wpd_test_facebook_api_status_ajax_function() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	// Take action
	$response = array();
	if ( WPD_AI_PRO ) {

		$facebook_api = new WPD_Facebook_API( array('load_api' => true) );
		$result = $facebook_api->test_api();
		
		if ( $result && is_array( $result ) ) {
	
			$message = isset( $result['message'] ) ? sanitize_text_field( $result['message'] ) : '';
			
			if ( strpos( $message, 'succesful' ) !== false ) {
	
				$response['success'] = true;
				$response['message'] = esc_html( $message );
	
			} else {
	
				$response['success'] = false;
				$response['message'] = esc_html( $message );
	
			}
	
		} else {
	
			$response['success'] = false;
			$response['message'] = __( 'Could not check API status.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
	
		}

	} else {

		$response['success'] = false;
		$response['message'] = __( 'This is a Pro feature.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	wp_send_json( $response );

}

/**
 *
 *	Ajax request for deleting all facebook ad expenses
 *
 */
add_action('wp_ajax_wpd_save_settings_ajax', 'wpd_save_settings_ajax_function' );
function wpd_save_settings_ajax_function() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	if ( ! WPD_AI_PRO ) {

		// Return response
		$response['message'] = __( 'This is a Pro feature.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		$response['target'] = 'save-facebook-settings';
		$response['success'] = false;
		wp_send_json( $response );
		return;

	}

	$response = array();

	// Sanitize inputs
	$action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : '';
	$url = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';
	$form = isset( $_POST['form'] ) && is_array( $_POST['form'] ) ? $_POST['form'] : array();
	
	$facebook_settings = get_option( 'wpd_ai_facebook_integration', array() );

	// Cleanse form into same format as $_POST
	$form_array = array();
	foreach( $form as $form_input ) {
		if ( isset( $form_input['name'] ) && isset( $form_input['value'] ) ) {
			$form_array[ sanitize_text_field( $form_input['name'] ) ] = sanitize_text_field( $form_input['value'] );
		}
	}

	foreach( $form as $form_field ) {

		if ( ! isset( $form_field['name'] ) || ! isset( $form_field['value'] ) ) {
			continue;
		}

		$field_name = sanitize_text_field( $form_field['name'] );
		$form_value = sanitize_text_field( $form_field['value'] );

		if ( $field_name === 'wpd_ai_facebook_integration[access_token]' ) {

			$facebook_settings['access_token'] = $form_value;
			update_option( 'wpd_ai_facebook_integration', $facebook_settings );

		}

		if ( $field_name === 'wpd_ai_facebook_integration[ad_account_id]' ) {

			// Save settings
			$facebook_settings['ad_account_id'] = $form_value;
			update_option( 'wpd_ai_facebook_integration', $facebook_settings );

		}

	}

	// Check that we have values saved for the api Connection
	$facebook_auth = new WPD_Facebook_API( array('load_api' => true) );
	$check_api = $facebook_auth->test_api();
	
	if ( isset( $check_api['error'] ) && $check_api['error'] === true ) {
		$response['success'] = false;
	} else {
		$response['success'] = true;
	}

	// Return response
	$response['action'] = sanitize_text_field( $action );
	$response['target'] = 'save-facebook-settings';
	$response['url'] = esc_url( $url );
	$response['form'] = $form_array;
	$response['message'] = isset( $check_api['message'] ) ? sanitize_text_field( $check_api['message'] ) : '';

	wp_send_json( $response );

}

/**
 *
 *	Ajax request for Revoking the refresh token for the Google Ads API
 *
 */
add_action('wp_ajax_wpd_test_google_ads_api_status', 'wpd_test_google_ads_api_status' );
function wpd_test_google_ads_api_status() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	// Capture Vars
	$response 		= array();

	if ( WPD_AI_PRO ) {

		$google_ads_api = new WPD_Google_Ads_API();
		$api_test 		= $google_ads_api->test_api();
	
		// Unexpected results
		if ( ! is_array($api_test) || empty($api_test) || ! isset($api_test['success']) || ! isset($api_test['message']) ) {
	
			// Return response
			$response['success'] = false;
			$response['message'] = __( 'Could not complete the test - we received unexpected result, check your WordPress debug.log.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
	
			wp_send_json( $response );
			return;
	
		}
	
		// Correct response from the API test
		$response['success'] = $api_test['success'];
		$response['message'] = isset( $api_test['message'] ) ? sanitize_text_field( $api_test['message'] ) : '';

	} else {

		// Correct response from the API test
		$response['success'] = false;
		$response['message'] = __( 'This is a Pro feature.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	// Kill script
	wp_send_json( $response );

}

/**
 *
 *	Ajax request for deleting all Google Ad expenses
 *
 */
add_action('wp_ajax_wpd_delete_all_google_api_expense_data', 'wpd_delete_all_google_api_expense_data_ajax_function' );
function wpd_delete_all_google_api_expense_data_ajax_function() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	$response = array();

	if ( WPD_AI_PRO ) {

		// Take action
		$google_api = new WPD_Google_Ads_API(array(), false); // Dont load the API
		$result = $google_api->delete_all_api_ad_spend_data();
		
		if ( is_array( $result ) ) {

			$found = $result['found'];
			$deleted = $result['deleted'];

			$response['success'] = true;

			if ( $found === 0 ) {
				$response['message'] = __( 'No ad expenses were found, all good.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
			} else {
				/* translators: 1: Number of deleted expenses, 2: Total number of expenses found */
				$response['message'] = sprintf( __( 'Sucesfully deleted %1$s out of %2$s Google API expenses.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), absint( $deleted ), absint( $found ) );
			}

		} else {

			$response['success'] = false;
			$response['message'] = __( 'Something doesnt quite seem right, check your WordPress debug.log.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

		}

	} else {

		$response['success'] = false;
		$response['message'] = __( 'This is a Pro feature.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	wp_send_json( $response );

}

/**
 *
 *	Ajax request for deleting all Google Ad expenses
 *
 */
add_action('wp_ajax_wpd_delete_all_google_api_campaign_data', 'wpd_delete_all_google_api_campaign_data_ajax_function' );
function wpd_delete_all_google_api_campaign_data_ajax_function() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	$response = array();
	if ( WPD_AI_PRO ) {

		// Take action
		$google_api = new WPD_Google_Ads_API();
		$result = $google_api->delete_all_api_campaign_data();
		
		if ( is_array( $result ) ) {

			$found = $result['found'];
			$deleted = $result['deleted'];

			$response['success'] = true;
			if ( $found === 0 ) {
				$response['message'] = __( 'No campaign data was found, all good.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
			} else {
				/* translators: 1: Number of deleted campaign data points, 2: Total number of campaign data points found */
				$response['message'] = sprintf( __( 'Sucesfully deleted %1$s out of %2$s Google API campaign data points.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), absint( $deleted ), absint( $found ) );
			}

		} else {

			$response['success'] = false;
			$response['message'] = __( 'Something doesnt quite seem right, check your WordPress debug.log.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

		}

	} else {

		$response['success'] = false;
		$response['message'] = __( 'This is a Pro feature.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	wp_send_json( $response );

}

/**
 *
 *	Ajax request for updating all Google API data
 *
 */
add_action('wp_ajax_wpd_refresh_all_google_data', 'wpd_refresh_all_google_data_ajax_function' );
function wpd_refresh_all_google_data_ajax_function() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	$response = array();
	if ( WPD_AI_PRO ) {

		// Take action
		$google_api = new WPD_Google_Ads_API( array( 'fetch_all' => true ) ); // Dont load the API
		$result = $google_api->create_update_campaign_and_expense_data();

		if ( is_array( $result ) ) {

			// We got the expected response
			$response['success'] = true;

			// We should have a message, but let's check
			if ( isset($result['message']) ) {
				$response['message'] = sanitize_text_field( $result['message'] );
			} else {
				$response['message'] = __( 'Looks like this was completed succesfully, but we got an unexpected result. Check your log and the reports for new data.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
			}

		} else {

			$response['success'] = false;
			$response['message'] = __( 'Something doesnt quite seem right, check your WordPress debug.log.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

		}

	} else {

		$response['success'] = false;
		$response['message'] = __( 'This is a Pro feature.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	wp_send_json( $response );

}

/**
 *
 *	Ajax request for creating Google Ads conversion action for profit tracking
 *
 */
add_action('wp_ajax_wpd_create_google_conversion_action', 'wpd_create_google_conversion_action_ajax_function' );
function wpd_create_google_conversion_action_ajax_function() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	$response = array();

	if ( WPD_AI_PRO ) {

		$google_ads_api = new WPD_Google_Ads_API();
		$conversion_action_result = $google_ads_api->api_create_profit_conversion_action();

		if ( $conversion_action_result === false ) {

			// Return response
			$response['success'] = false;
			$response['message'] = __( 'Something went wrong while creating the conversion action, refresh the page and check the error log at the bottom of this page.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

		} elseif( is_array($conversion_action_result) && isset($conversion_action_result['success']) && $conversion_action_result['success'] ) {

			// Check if fallback mechanism was used
			$action_name = isset( $conversion_action_result['conversion_action_name'] ) ? esc_html( $conversion_action_result['conversion_action_name'] ) : '';
			$re_enabled = isset( $conversion_action_result['details']['re_enabled'] ) && $conversion_action_result['details']['re_enabled'];
			
			if ( isset($conversion_action_result['fallback_used']) && $conversion_action_result['fallback_used'] ) {
				
				// Return response for fallback case
				$response['success'] = true;
				$fallback_message = $re_enabled ? __( 'enabled it', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) : __( 'it was already enabled', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
				/* translators: 1: Conversion action name, 2: Status message (enabled it / it was already enabled) */
				$response['message'] = sprintf( __( 'Found existing conversion action "%1$s" and %2$s. Please refresh the page to see the updated status. You can view this conversion action in your Google Ads account under Goals > Summary.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $action_name, $fallback_message );
				
			} else {
				
				// Return response for normal creation
				$response['success'] = true;
				$response['message'] = sprintf( __( 'Successfully created conversion action "%s". Please refresh the page to see the updated status. You can view this conversion action in your Google Ads account under Goals > Summary.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $action_name );
				
			}

		} else {

			// Return response
			$response['success'] = false;
			$response['message'] = __( 'The response looks malformed, refresh this page and check the error log at the bottom of this page.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

		}

	} else {

		// Return response
		$response['success'] = false;
		$response['message'] = __( 'This is a Pro feature.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	wp_send_json( $response );

}

/**
 *
 *	Ajax request for deleting Google Ads conversion action for profit tracking
 *
 */
add_action('wp_ajax_wpd_delete_google_conversion_action', 'wpd_delete_google_conversion_action_ajax_function' );
function wpd_delete_google_conversion_action_ajax_function() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	$response = array();

	if ( WPD_AI_PRO ) {

		$google_ads_api = new WPD_Google_Ads_API();
		$delete_result = $google_ads_api->api_delete_profit_conversion_action();

		if ( $delete_result === false ) {

			// Return response
			$response['success'] = false;
			$response['message'] = __( 'Something went wrong while deleting the conversion action, refresh the page and check the error log at the bottom of this page.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

		} elseif( is_array($delete_result) && isset($delete_result['success']) && $delete_result['success'] ) {

			// Return response
			$action_id = isset( $delete_result['conversion_action_id'] ) ? absint( $delete_result['conversion_action_id'] ) : 0;
			$response['success'] = true;
			$response['message'] = sprintf( __( 'Successfully deleted conversion action (ID: %d). Please refresh the page to see the updated status.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $action_id );

		} else {

			// Return response
			$response['success'] = false;
			$response['message'] = __( 'The response looks malformed, refresh this page and check the error log at the bottom of this page.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

		}

	} else {

		// Return response
		$response['success'] = false;
		$response['message'] = __( 'This is a Pro feature.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	wp_send_json( $response );

}

/**
 *
 *	Ajax request for scanning all orders with utm_campaign set over the last 90 days
 *
 */
add_action('wp_ajax_wpd_scan_utm_campaigns_via_order', 'wpd_scan_utm_campaigns_via_order_ajax_function' );
function wpd_scan_utm_campaigns_via_order_ajax_function() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	$response = array();

	if ( WPD_AI_PRO ) {

		// Take action
		$google_api = new WPD_Google_Ads_API( array( 'load_api' => false ) ); // Dont load the API
		$result = $google_api->set_order_campaign_id_via_query_param( 30 );

		if ( is_numeric( $result ) || is_bool( $result ) ) {

			// We got the expected response
			$response['success'] = true;

			// We should have a message, but let's check
			if ( isset($result['message']) ) {

				$response['message'] = sanitize_text_field( $result['message'] );

			} else {

				if ( $result === false ) {
					$response['message'] = __( 'Make sure you configure utm_campaign values against campaigns on the settings page before you run this function.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
				}
				elseif ( $result === 0 ) {
					$response['message'] = __( 'No matches were found between your configured utm_campaigns and the values found in your orders. Check the stored query parameters on an order to determine the utm_campaign.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
				} else {
					$response['message'] = sprintf( __( 'Succesfully updated %d orders via your configured utm_campaigns', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), absint( $result ) );
				}
				
			}

		} else {

			$response['success'] = false;
			$response['message'] = __( 'Something doesnt quite seem right, check your WordPress debug.log.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

		}

	} else {

		$response['success'] = false;
		$response['message'] = __( 'This is a Pro feature.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	wp_send_json( $response );

}

/**
 *
 *	Ajax request for scanning all orders with utm_campaign set over the last 90 days
 *
 */
add_action('wp_ajax_wpd_scan_order_gclids', 'wpd_scan_order_gclids_ajax_function' );
function wpd_scan_order_gclids_ajax_function() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	$response = array();

	if ( WPD_AI_PRO ) {

		// Take action
		$google_api = new WPD_Google_Ads_API(); // Dont load the API

		// Check orders for a GCLID and store the campaign ID
		$result = $google_api->set_order_campaign_id_via_api_last_x_days( 30, true );

		if ( is_array( $result ) && ! empty($result) ) {

			// We got the expected response
			$response['success'] = true;

			// We should have a message, but let's check
			if ( isset($result['message']) ) {

				$response['message'] = sanitize_text_field( $result['message'] );

			} else {

				$orders_checked = isset( $result['order_count'] ) ? absint( $result['order_count'] ) : 0;
				$updates = isset( $result['updates'] ) ? absint( $result['updates'] ) : 0;
				$errors = isset( $result['errors'] ) ? absint( $result['errors'] ) : 0;
				$gclids_found = isset( $result['gclids_found'] ) ? absint( $result['gclids_found'] ) : 0;

				/* translators: 1: Number of orders checked, 2: Number of GCLIDs found, 3: Number of orders associated, 4: Number of API errors */
				$response['message'] = sprintf( __( '%1$d Orders were checked, we found %2$d GCLIDs, associated %3$d orders to campaigns and there were %4$d API errors.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $orders_checked, $gclids_found, $updates, $errors );
				
			}

		} else {

			$response['success'] = false;
			$response['message'] = __( 'Something doesnt quite seem right, check your WordPress debug.log.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

		}

	} else {

		$response['success'] = false;
		$response['message'] = __( 'This is a Pro feature.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	wp_send_json( $response );

}

/**
 * 
 * Ajax Request to manually upgrade DB
 * 
 */
add_action( 'wp_ajax_wpd-update_db_manually', 'wpd_update_wpd_ai_database' );
function wpd_update_wpd_ai_database() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
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
add_action( 'wp_ajax_wpd_delete_log', 'wpd_delete_log_ajax' );
function wpd_delete_log_ajax() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
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
 *
 *	Ajax request for creating Google Ads Add To Cart conversion action
 *
 */
add_action('wp_ajax_wpd_create_google_add_to_cart_conversion_action', 'wpd_create_google_add_to_cart_conversion_action_ajax_function' );
function wpd_create_google_add_to_cart_conversion_action_ajax_function() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	$response = array();

	if ( WPD_AI_PRO ) {

		$google_ads_api = new WPD_Google_Ads_API();
		$conversion_action_result = $google_ads_api->api_create_add_to_cart_conversion_action();

		if ( $conversion_action_result === false ) {

			// Return response
			$response['success'] = false;
			$response['message'] = __( 'Something went wrong while creating the Add To Cart conversion action, refresh the page and check the error log at the bottom of this page.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

		} elseif( is_array($conversion_action_result) && isset($conversion_action_result['success']) && $conversion_action_result['success'] ) {

			// Check if fallback mechanism was used
			$action_name = isset( $conversion_action_result['conversion_action_name'] ) ? esc_html( $conversion_action_result['conversion_action_name'] ) : '';
			$re_enabled = isset( $conversion_action_result['details']['re_enabled'] ) && $conversion_action_result['details']['re_enabled'];
			
			if ( isset($conversion_action_result['fallback_used']) && $conversion_action_result['fallback_used'] ) {
				
				// Return response for fallback case
				$response['success'] = true;
				$fallback_message = $re_enabled ? __( 'enabled it', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) : __( 'it was already enabled', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
				/* translators: 1: Add To Cart conversion action name, 2: Status message (enabled it / it was already enabled) */
				$response['message'] = sprintf( __( 'Found existing Add To Cart conversion action "%1$s" and %2$s. Please refresh the page to see the updated status. You can view this conversion action in your Google Ads account under Goals > Summary.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $action_name, $fallback_message );
				
			} else {
				
				// Return response for normal creation
				$response['success'] = true;
				$response['message'] = sprintf( __( 'Successfully created Add To Cart conversion action "%s". Please refresh the page to see the updated status. You can view this conversion action in your Google Ads account under Goals > Summary.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $action_name );
				
			}

		} else {

			// Return response
			$response['success'] = false;
			$response['message'] = __( 'The response looks malformed, refresh this page and check the error log at the bottom of this page.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

		}

	} else {

		// Return response
		$response['success'] = false;
		$response['message'] = __( 'This is a Pro feature.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	wp_send_json( $response );

}

/**
 *
 *	Ajax request for deleting Google Ads Add To Cart conversion action
 *
 */
add_action('wp_ajax_wpd_delete_google_add_to_cart_conversion_action', 'wpd_delete_google_add_to_cart_conversion_action_ajax_function' );
function wpd_delete_google_add_to_cart_conversion_action_ajax_function() {

	// Verify security
	if ( ! wpd_verify_ajax_request() ) {
		return;
	}

	$response = array();

	if ( WPD_AI_PRO ) {

		$google_ads_api = new WPD_Google_Ads_API();
		$conversion_action_result = $google_ads_api->api_delete_add_to_cart_conversion_action();

		if ( $conversion_action_result === false ) {

			// Return response
			$response['success'] = false;
			$response['message'] = __( 'Something went wrong while deleting the Add To Cart conversion action, refresh the page and check the error log at the bottom of this page.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

		} elseif( is_array($conversion_action_result) && isset($conversion_action_result['success']) && $conversion_action_result['success'] ) {

			// Return response
			$response['success'] = true;
			$response['message'] = __( 'Successfully deleted Add To Cart conversion action. Please refresh the page to see the updated status.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

		} else {

			// Return response
			$response['success'] = false;
			$response['message'] = __( 'The response looks malformed, refresh this page and check the error log at the bottom of this page.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

		}

	} else {

		// Return response
		$response['success'] = false;
		$response['message'] = __( 'This is a Pro feature.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	wp_send_json( $response );

}

/**
 * Load all documentation files
 */
add_action('wp_ajax_wpd_load_documentation', 'wpd_load_documentation_ajax');
function wpd_load_documentation_ajax() {

	// Verify security using standard helper
	if ( ! wpd_verify_ajax_request() ) {
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
		$docs_data = wpd_load_docs_recursive($docs_path);

		$response['success'] = true;
		$response['data']    = $docs_data;
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
function wpd_strip_numeric_prefix($name) {
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
	function wpd_load_docs_recursive($dir, $relative_path = '') {
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
			$subfolder_data = wpd_load_docs_recursive($full_path . '/', $item_relative_path);
			
			if (!empty($subfolder_data)) {
				// Strip numeric prefix from folder name for display
				$display_name = wpd_strip_numeric_prefix($item);
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
add_action( 'wp_ajax_wpd_save_getting_started_settings', 'wpd_save_getting_started_settings' );
function wpd_save_getting_started_settings() {
	
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
		wpd_delete_all_order_data_cache();
	}

	// Return success
	wp_send_json_success( array(
		'message' => __( 'Settings saved successfully', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
	) );
}

/**
 * Save and Activate License from Getting Started
 * 
 * Handles AJAX request to save and activate license from getting started wizard
 * 
 * @since 5.0.0
 * @return void
 */
add_action( 'wp_ajax_wpd_getting_started_activate_license', 'wpd_getting_started_activate_license' );
function wpd_getting_started_activate_license() {
	
	// Check nonce
	if ( ! check_ajax_referer( WPD_AI_AJAX_NONCE_ACTION, 'nonce', false ) ) {
		wp_send_json_error( __( 'Security check failed', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) );
	}

	// Check user permissions
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'You do not have permission to activate licenses', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) );
	}

	// Get license key
	$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( $_POST['license_key'] ) : '';

	if ( empty( $license_key ) ) {
		wp_send_json_error( __( 'Please enter a license key', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) );
	}

	// Save license key
	update_option( 'wpd_ai_api_key', $license_key );

	// Try to activate license
	$authenticator = new WPD_Authenticator();
	$activate_result = $authenticator->activate_license();

	// Check result
	if ( isset( $activate_result['result'] ) && $activate_result['result'] === 'success' ) {
		
		// Success
		wp_send_json_success( array(
			'message' => isset( $activate_result['message'] ) ? $activate_result['message'] : __( 'License activated successfully!', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
		) );

	} else {
		
		// Error
		$error_message = isset( $activate_result['message'] ) ? $activate_result['message'] : __( 'Could not activate your license key. Please check your license key and try again.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		wp_send_json_error( $error_message );

	}
}