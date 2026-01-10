<?php
/**
 *
 * One off event functions that are run by WPDAI_Action_Scheduler
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
 *	 Schedule a full clean of the the product cache
 * *
 *   @param int Number of seconds by which to delay the execution of this function, 120 default.
 *   @return bool|WP_Error Bool on success, WP_Error on failure    
 * 
 **/
function wpdai_schedule_once_off_cron_event_delete_products_cache( $execution_delay_in_second = 0 ) {

    $action_scheduler = new WPDAI_Action_Scheduler();
    return $action_scheduler->schedule_one_off_event( $action_scheduler::SINGLE_EVENT_REBUILD_PRODUCT_CACHE, $execution_delay_in_second );

}