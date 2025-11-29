<?php
/**
 *
 * One off event functions that are run by WPD_Action_Scheduler
 *
 * @package Alpha Insights
 * @version 4.4.0
 * @since 4.4.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * 
 *	 Schedule a full clean of the the order cache
 *
 *   This function will also wipe out all caches as it works through to try and preserve memory consumption
 *
 *   @param int Number of seconds by which to delay the execution of this function, 120 default.
 *   @return bool|WP_Error Bool on success, WP_Error on failure    
 * 
 **/
function wpd_schedule_once_off_cron_event_rebuild_orders_cache( $execution_delay_in_second = 0 ) {

    $action_scheduler = new WPD_Action_Scheduler();
    return $action_scheduler->schedule_one_off_event( $action_scheduler::SINGLE_EVENT_REBUILD_ORDER_CACHE, $execution_delay_in_second );

}

/**
 * 
 *	 Schedule a full clean of the the product cache
 * *
 *   @param int Number of seconds by which to delay the execution of this function, 120 default.
 *   @return bool|WP_Error Bool on success, WP_Error on failure    
 * 
 **/
function wpd_schedule_once_off_cron_event_delete_products_cache( $execution_delay_in_second = 0 ) {

    $action_scheduler = new WPD_Action_Scheduler();
    return $action_scheduler->schedule_one_off_event( $action_scheduler::SINGLE_EVENT_REBUILD_PRODUCT_CACHE, $execution_delay_in_second );

}

/**
 * 
 *	 Schedule a Google Ads order profit conversion from a order ID
 *   Will not run unless we have set up a conversion action in the Google Ads settings and have an option saved for the conversion action ID using wpd_ai_google_ads_profit_conversion_action_id
 * *
 *   @param int Number of seconds by which to delay the execution of this function, 120 default.
 *   @param array $args The arguments to pass to the function. Expects $args = array( 'order_id' => null )
 *   @return bool|WP_Error Bool on success, WP_Error on failure    
 * 
 **/
function wpd_schedule_once_off_cron_event_google_ads_profit_conversion_action_from_order_id( $execution_delay_in_second = 0, $args = array( 'order_id' => null ) ) {

    if ( empty(get_option( 'wpd_ai_google_ads_profit_conversion_action_id', null ) ) ) {
        return false;
    }

    $action_scheduler = new WPD_Action_Scheduler();
    return $action_scheduler->schedule_one_off_event( $action_scheduler::SINGLE_EVENT_TRACK_GOOGLE_ADS_ORDER_PROFIT_CONVERSION, $execution_delay_in_second, $args );

}
/* 
 * 
 *  Create a Google Ads profit conversion action from a order ID, track the profit value of an order back to Google Ads.
 *  Will not run unless we have set up a conversion action in the Google Ads settings and have an option saved for the conversion action ID using wpd_ai_google_ads_profit_conversion_action_id
 * 
 *  @param int $order_id The order ID to upload the conversion for
 *  @return bool|WP_Error Bool on success, WP_Error on failure    
 * 
 */
function wpd_google_ads_track_profit_conversion_from_order_id( $order_id ) {

    $profit_conversion_action_id = get_option( 'wpd_ai_google_ads_profit_conversion_action_id', null );
    if ( empty($profit_conversion_action_id) ) return false;

    $google_api = new WPD_Google_Ads_API(array( 'load_api' => true ));

    if ( empty($order_id) || ! is_numeric($order_id) ) return false;

    $order = wc_get_order( $order_id );
    if ( ! $order ) return false;

    $order_data = wpd_calculate_cost_profit_by_order( $order );
    if ( ! $order_data ) return false;

    $gclid = WPD_Google_Ads_API::get_gclid_from_order( $order );
    if ( empty($gclid) ) return false;

    $result = $google_api->upload_custom_conversion( $gclid, $order->get_date_created(), $order_data['total_order_profit'], $profit_conversion_action_id );

    if ( ! $result ) return false;
    
    return true;

}

/**
 * 
 *	 Schedule a Google Ads add to cart conversion from a landing page
 *   Will not run unless we have set up a conversion action in the Google Ads settings and have an option saved for the conversion action ID using wpd_ai_google_ads_add_to_cart_conversion_action_id
 *
 *   @param int Number of seconds by which to delay the execution of this function, 120 default.
 *   @param array $args The arguments to pass to the function. Expects $args = array( 'landing_page' => null, 'conversion_value' => null )
 *   @return bool|WP_Error Bool on success, WP_Error on failure    
 * 
 **/
function wpd_schedule_once_off_cron_event_google_ads_add_to_cart_conversion_action_from_gclid( $execution_delay_in_second = 0, $args = array( 'landing_page' => null, 'conversion_value' => null ) ) {

    if ( empty(get_option( 'wpd_ai_google_ads_add_to_cart_conversion_action_id', null ) ) ) {
        return false;
    }

    $action_scheduler = new WPD_Action_Scheduler();
    return $action_scheduler->schedule_one_off_event( $action_scheduler::SINGLE_EVENT_TRACK_GOOGLE_ADS_ADD_TO_CART_CONVERSION, $execution_delay_in_second, $args );

}
/* 
 * 
 *  Create a Google Ads add to cart conversion action from a landing page.
 *  Will not run unless we have set up a conversion action in the Google Ads settings and have an option saved for the conversion action ID using wpd_ai_google_ads_add_to_cart_conversion_action_id
 * 
 *  @param string $landing_page The landing page to upload the conversion for, will check for gclid in the landing page
 *  @param float $conversion_value The conversion value
 *  @return bool|WP_Error Bool on success, WP_Error on failure    
 * 
 */
function wpd_google_ads_track_add_to_cart_conversion_from_landing_page( $landing_page, $conversion_value ) {

    $add_to_cart_conversion_action_id = get_option( 'wpd_ai_google_ads_add_to_cart_conversion_action_id', null );
    if ( empty($add_to_cart_conversion_action_id) ) {
        return false;
    }

    $gclid = WPD_Google_Ads_API::get_gclid_from_landing_page( $landing_page );
    if ( empty($gclid) ) {
        return false;
    }

    if ( empty($gclid) || ! is_numeric($conversion_value) || $conversion_value <= 0 ){
        return false;
    }

    $conversion_date_time = current_time( 'Y-m-d H:i:s' );

    $google_api = new WPD_Google_Ads_API(array( 'load_api' => true ));

    if ( empty($google_api) ) {
        return false;
    }

    $result = $google_api->upload_custom_conversion( $gclid, $conversion_date_time, $conversion_value, $add_to_cart_conversion_action_id );

    if ( ! $result ) {
        return false;
    }
    
    return true;

}