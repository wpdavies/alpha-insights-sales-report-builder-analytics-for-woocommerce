<?php
/**
 *
 * Action Scheduler class responsible for single events & cron tasks
 * Implements the WooCommerce Action Scheduler
 *
 * @package Alpha Insights
 * @version 4.4.0
 * @since 4.4.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

class WPD_Action_Scheduler {

    public const EVENT_GROUP_SLUG                   = 'WP Davies';
    public const SINGLE_EVENT_REBUILD_PRODUCT_CACHE = 'wpd_rebuild_product_cache';
    public const SINGLE_EVENT_TRACK_GOOGLE_ADS_ORDER_PROFIT_CONVERSION = 'wpd_google_ads_track_order_profit_conversion';
    public const SINGLE_EVENT_TRACK_GOOGLE_ADS_ADD_TO_CART_CONVERSION = 'wpd_google_ads_track_add_to_cart_conversion';
    public const SINGLE_EVENT_MIGRATION_RUNNER = 'wpd_migration_runner';
    public const RECURRING_EVENT_MIGRATION_CHECK = 'wpd_schedule_migration_check';
    /**
     * Class constructor: hooks into WooCommerce's Action Scheduler.
     */
    public function __construct() {

        // Setup the recurring actions
        add_action('init', array( $this, 'schedule_recurring_events' ));

        // Hook into the recurring actions
        add_action( 'wpd_schedule_facebook_api_call',                   'wpd_schedule_facebook_api_call_function' );
        add_action( 'wpd_schedule_google_api_gclid_check',              'wpd_schedule_google_api_gclid_check_cron' );
        add_action( 'wpd_schedule_order_utm_campaign_check',            'wpd_schedule_order_utm_campaign_check_google_cron' );
        add_action( 'wpd_schedule_order_utm_campaign_check',            'wpd_schedule_order_utm_campaign_check_meta_cron' );
        add_action( 'wpd_schedule_google_data_fetch',                   'wpd_schedule_google_data_fetch_cron' );
        add_action( 'wpd_schedule_emails',                              'wpd_schedule_emails_function' );
        add_action( 'wpd_schedule_webhook',                             'wpd_schedule_webhook_post' );
        add_action( 'wpd_schedule_license_check',                       'wpd_schedule_license_check_function' );
        add_action( 'wpd_schedule_log_cleanup',                         'wpd_schedule_log_cleanup_function' );
        add_action( 'wpd_schedule_analytics_db_cleanup',                'wpd_schedule_analytics_db_cleanup_function' );
        add_action( 'wpd_schedule_daily_task_runner_once_off',          'wpd_schedule_daily_task_runner_once_off_function' );
        add_action( 'wpd_schedule_database_upgrade',                    'wpd_schedule_database_upgrade_function' );
        add_action( 'wpd_schedule_product_analytics_collector',         'wpd_collect_product_statistics_cron' );
        add_action( 'wpd_schedule_customer_analytics_collector',        'wpd_collect_customer_statistics_cron' );
        add_action( 'wpd_schedule_order_calculation_cache_collector',   'wpd_fetch_and_store_last_n_uncached_orders_cron' );
        add_action( 'wpd_schedule_analytics_table_object_id_check',     'wpd_set_post_id_post_type_on_null_events_analytics_table' );
        
        // Migration check - use lazy loading to avoid class dependency issues
        if ( class_exists( 'WPD_Migration' ) ) {
            add_action( self::RECURRING_EVENT_MIGRATION_CHECK, array( WPD_Migration::get_instance(), 'run_pending_migrations' ) );
        }

        // All single action hooks will need to run off this
        add_action( self::SINGLE_EVENT_REBUILD_PRODUCT_CACHE,           'wpd_delete_all_product_cache');
        add_action( self::SINGLE_EVENT_TRACK_GOOGLE_ADS_ORDER_PROFIT_CONVERSION, 'wpd_google_ads_track_profit_conversion_from_order_id', 10, 1);
        add_action( self::SINGLE_EVENT_TRACK_GOOGLE_ADS_ADD_TO_CART_CONVERSION, 'wpd_google_ads_track_add_to_cart_conversion_from_landing_page', 10, 2);
        
    }

    /**
     * Schedules the background task if it hasn't been scheduled already.
     */
    public function schedule_recurring_events() {

        /**
         * Action hook to schedule recurring events.
         * 
         * @param WPD_Action_Scheduler $this The instance of the WPD_Action_Scheduler class.
         */
        do_action( 'wpd_schedule_recurring_events', $this );

        // Upgrade Database - Once a day, triggered immediately
        if ( ! as_next_scheduled_action( 'wpd_schedule_database_upgrade' ) ) {
            as_schedule_recurring_action( time(), DAY_IN_SECONDS, 'wpd_schedule_database_upgrade', array(), self::EVENT_GROUP_SLUG );
        }

        // Daily once off task runner @deprecated @since 5.0.0
        // if ( ! as_next_scheduled_action( 'wpd_schedule_daily_task_runner_once_off' ) ) {
        //     as_schedule_recurring_action( time() + 60, DAY_IN_SECONDS, 'wpd_schedule_daily_task_runner_once_off', array(), self::EVENT_GROUP_SLUG );
        // }

        // Check license status - Once a day, delayed by 4m for initial run
        if ( ! as_next_scheduled_action( 'wpd_schedule_license_check' ) && WPD_AI_PRO ) {
            as_schedule_recurring_action( time() + 240, DAY_IN_SECONDS, 'wpd_schedule_license_check', array(), self::EVENT_GROUP_SLUG );
        }

        // Orders Cache - Every 5 minutes, 250 per run, delay by 5m for initial run
        if ( ! as_next_scheduled_action( 'wpd_schedule_order_calculation_cache_collector' ) ) {
            as_schedule_recurring_action( time() + 600, 5 * MINUTE_IN_SECONDS, 'wpd_schedule_order_calculation_cache_collector', array(), self::EVENT_GROUP_SLUG);
        }

        // Clean up the Analytics DB - Once a day
        if ( ! as_next_scheduled_action( 'wpd_schedule_analytics_db_cleanup' ) ) {
            as_schedule_recurring_action( time() + 720, DAY_IN_SECONDS, 'wpd_schedule_analytics_db_cleanup', array(), self::EVENT_GROUP_SLUG );
        }

        // Schedule Emails - Once a day
        if ( ! as_next_scheduled_action( 'wpd_schedule_emails' ) ) {
            as_schedule_recurring_action( time() + 900, DAY_IN_SECONDS, 'wpd_schedule_emails', array(), self::EVENT_GROUP_SLUG );
        }
        
        // Schedule log cleanup - Once a day
        if ( ! as_next_scheduled_action( 'wpd_schedule_log_cleanup' ) ) {
            as_schedule_recurring_action( time() + 1200, DAY_IN_SECONDS, 'wpd_schedule_log_cleanup', array(), self::EVENT_GROUP_SLUG );
        }

        // Clean up Analytics DB to include
        if ( ! as_next_scheduled_action( 'wpd_schedule_analytics_table_object_id_check' ) ) {
            as_schedule_recurring_action( time() + 1500, DAY_IN_SECONDS, 'wpd_schedule_analytics_table_object_id_check', array(), self::EVENT_GROUP_SLUG );
        }

        // Product Analytics
        if ( ! as_next_scheduled_action( 'wpd_schedule_product_analytics_collector' ) ) {
            as_schedule_recurring_action( time() + 2400, HOUR_IN_SECONDS, 'wpd_schedule_product_analytics_collector', array(), self::EVENT_GROUP_SLUG );
        }

        // Customer Analytics
        if ( ! as_next_scheduled_action( 'wpd_schedule_customer_analytics_collector' ) ) {
            as_schedule_recurring_action( time() + 3000, HOUR_IN_SECONDS, 'wpd_schedule_customer_analytics_collector', array(), self::EVENT_GROUP_SLUG );
        }

        // Check order utm_campaign - Google & Meta
        if ( ! as_next_scheduled_action( 'wpd_schedule_order_utm_campaign_check' ) && WPD_AI_PRO ) {
            as_schedule_recurring_action( time() + 3600, HOUR_IN_SECONDS, 'wpd_schedule_order_utm_campaign_check', array(), self::EVENT_GROUP_SLUG );
        }

        // Facebook API call schedule
        if ( ! as_next_scheduled_action( 'wpd_schedule_facebook_api_call' ) && WPD_AI_PRO ) {

            $facebook_settings  = get_option( 'wpd_ai_facebook_integration' );
            $call_schedule      = $facebook_settings['facebook_api_call_schedule'] ?? '3-hrs'; // Default to 3 hours
            $access_token       = $facebook_settings['access_token'] ?? null;
            $interval           = 3 * HOUR_IN_SECONDS; // Default to 3 hours

            if ($call_schedule == 'daily') {
                $interval = DAY_IN_SECONDS;
            } elseif ($call_schedule == '12-hrs') {
                $interval = 12 * HOUR_IN_SECONDS;
            } elseif ($call_schedule == '6-hrs') {
                $interval = 6 * HOUR_IN_SECONDS;
            } elseif ($call_schedule == '3-hrs') {
                $interval = 3 * HOUR_IN_SECONDS;
            }

            // Only schedule if we have an access token and an interval is set
            if ($access_token && $interval > 0) {
                as_schedule_recurring_action( time() + 120, $interval, 'wpd_schedule_facebook_api_call', array(), self::EVENT_GROUP_SLUG );
            }

        }

        // Google API call schedule
        if ( ! as_next_scheduled_action( 'wpd_schedule_google_data_fetch' ) && WPD_AI_PRO ) {

            $google_api_settings    = get_option( 'wpd_ai_google_ads_api' );
            $call_schedule          = $google_api_settings['api_call_schedule'] ?? '3-hrs'; // Default to 3 hours
            $access_token           = get_option( 'wpd_ai_google_ads_api_refresh_token', null );
            $interval               = 3 * HOUR_IN_SECONDS; // Default to 3 hours

            if ($call_schedule == 'daily') {
                $interval = DAY_IN_SECONDS;
            } elseif ($call_schedule == '12-hrs') {
                $interval = 12 * HOUR_IN_SECONDS;
            } elseif ($call_schedule == '6-hrs') {
                $interval = 6 * HOUR_IN_SECONDS;
            } elseif ($call_schedule == '3-hrs') {
                $interval = 3 * HOUR_IN_SECONDS;
            }

            // Only schedule if we have an access token and an interval is set
            if ($access_token && $interval > 0) {
                as_schedule_recurring_action( time() + 120, $interval, 'wpd_schedule_google_data_fetch', array(), self::EVENT_GROUP_SLUG );
            }

        }

        // Check Google Ads order GCLIDs -> Only if access token exists
        if ( ! as_next_scheduled_action( 'wpd_schedule_google_api_gclid_check' ) && WPD_AI_PRO ) {
            $google_api_settings    = get_option( 'wpd_ai_google_ads_api' );
            $access_token           = $google_api_settings['refresh_token'] ?? null;
            if ( $access_token ) {
                as_schedule_recurring_action( time() + 240, HOUR_IN_SECONDS, 'wpd_schedule_google_api_gclid_check', array(), self::EVENT_GROUP_SLUG );
            }
        }

        // Check for webhook post - Runs hourly, but actually executes according to scheduled settings (Condition)
        if ( ! as_next_scheduled_action( 'wpd_schedule_webhook' ) ) {
            $webhook_settings = get_option( 'wpd_ai_webhooks' );
            $webhook_url = $webhook_settings['webhook_url'] ?? null;
            if ( ! empty($webhook_url) ) {
                as_schedule_recurring_action( time(), HOUR_IN_SECONDS, 'wpd_schedule_webhook', array(), self::EVENT_GROUP_SLUG );
            }
        }

        // Check for pending migrations - Once a day
        if ( ! as_next_scheduled_action( self::RECURRING_EVENT_MIGRATION_CHECK ) ) {
            as_schedule_recurring_action( time() + 1800, DAY_IN_SECONDS, self::RECURRING_EVENT_MIGRATION_CHECK, array(), self::EVENT_GROUP_SLUG );
        }

    }

    /**
     * Method to schedule a one-off event.
     * 
     * @param string $hook_name (Required) The unique hook name for this action, only accepts the static parms in this class
     * @param int $delay_in_seconds (Optional) The delay in seconds before the event runs. Default is 0 (run immediately).
     * 
     *  @return bool Returns true if it is scheduled in or false on failure
     */
    public function schedule_one_off_event( $hook_name, $delay_in_seconds = 0, $args = array()) {

        if (!as_next_scheduled_action( $hook_name )) {
            $result = as_schedule_single_action( time(), $hook_name, $args, self::EVENT_GROUP_SLUG );
            return ( $result ) ? true : false;
        }

        return true;
    }
}

// Initialize the class
new WPD_Action_Scheduler();