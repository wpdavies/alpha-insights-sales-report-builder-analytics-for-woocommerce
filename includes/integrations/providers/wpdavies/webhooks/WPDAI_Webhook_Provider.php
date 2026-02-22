<?php
/**
 * StarShipIt Integration
 * Manages StarShipIt integration
 * 
 * @package Alpha Insights
 * @version 1.0.0
 * @since 5.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 */

defined( 'ABSPATH' ) || exit;

class WPDAI_Webhook_Provider {

    /**
	 * Webhook settings option name
	 *
	 * @var string
	 */
	private $webhook_settings_option_name = 'wpd_ai_webhook_settings';

    /**
     * Webhook settings
     *
     * @var array
     */
    private $webhook_settings = array();

    /**
     * Singleton instance
     *
     * @var WPDAI_Webhook_Provider
     */
    private static $instance = null;

    /**
     * Recurring event hook
     *
     * @var string
     */
    public static $recurring_event_hook = 'wpd_ai_webhook_export';

    /**
     * Log files
     *
     * @var string
     */
    public static $log_file = 'webhooks';
    
    /**
     * Log error file
     *
     * @var string
     */
    public static $log_error_file = 'webhooks_error';

    /**
     * Get the singleton instance of this class
     *
     * @return WPDAI_Webhook_Provider
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log a message
     *
     * Uses the enable_logging setting from webhook options. Errors are always
     * logged to webhooks_error. The wpd_ai_webhooks_enable_logging filter can override.
     *
     * @param string $message The message to log
     * @param bool   $error   Whether the message is an error
     */
    public function log( $message, $error = false ) {

        $settings     = get_option( $this->webhook_settings_option_name, array() );
        $option_value = isset( $settings['enable_logging'] ) ? $settings['enable_logging'] : '0';
        $enable_log   = apply_filters( 'wpd_ai_webhooks_enable_logging', ! empty( $option_value ) );

        if ( $enable_log ) {
            wpdai_write_log( $message, self::$log_file );
        }

        if ( $error ) {
            wpdai_write_log( $message, self::$log_error_file );
        }

    }

    /**
     * Constructor
     */
    public function __construct() {

        $this->webhook_settings = get_option( $this->webhook_settings_option_name, array() );

        // Check if the webhook is configured
        if ( $this->is_configured() ) {
            $this->setup_integration();
        }

    }

    /**
     * Register AJAX actions
     */
    public static function register_ajax_actions() {
        add_action('wp_ajax_wpd_webhook_export_manual', array( self::get_instance(), 'ajax_manual_webhook_export' ) );

    }

    /**
     * Manually export the webhook data
     */
    public function ajax_manual_webhook_export() {
        // Verify security
        if ( ! wpdai_verify_ajax_request() ) {
            return;
        }
        
        $response = wpdai_webhook_post_data();
        wp_send_json( $response );
    }

    /**
     * Check if the webhook is configured
     *
     * @return bool
     */
    public function is_configured() {

        // Settings need to be an array and not empty
        if ( ! is_array( $this->webhook_settings ) || empty( $this->webhook_settings ) ) {
            return false;
        }

        // Need a webhook URL
        if ( empty( $this->webhook_settings['webhook_url'] ) ) {
            return false;
        }

        // Finally, the schedule needs to be set
        if ( ! isset( $this->webhook_settings['webhook_schedule'] ) || ! in_array( $this->webhook_settings['webhook_schedule'], array( 'daily', 'weekly', 'monthly' ) ) ) {
            return false;
        }

        // Passed all checks, webhook is configured
        return true;

    }

    /**
     * Setup the webhook integration
     */
    public function setup_integration() {

        // Register the action scheduler
        add_action( 'wpd_schedule_recurring_events', array( $this, 'schedule_webhook_export' ), 10, 1 );

        // Execute the callback
        add_action( self::$recurring_event_hook, array( $this, 'execute_webhook_export' ) );

    }

    /**
     * Schedule the webhook export to run daily at 1am site-local time
     *
     * Ensures a full day of data is available for the previous period.
     * The callback handles daily/weekly/monthly logic and date ranges.
     */
    public function schedule_webhook_export( $action_scheduler ) {

        
        if ( ! as_next_scheduled_action( self::$recurring_event_hook ) ) {
            $first_run = function_exists( 'wpdai_next_1am_local_timestamp' ) ? wpdai_next_1am_local_timestamp() : strtotime( 'tomorrow 01:00:00' );
            as_schedule_recurring_action( $first_run, DAY_IN_SECONDS, self::$recurring_event_hook, array(), $action_scheduler::EVENT_GROUP_SLUG );
        }

    }

    /**
     * Execute the webhook export
     *
     * Scheduled to run daily at 1am local time. Determines whether to send based on
     * schedule type: daily (every run), weekly (Mondays only), monthly (1st only).
     * Date ranges are computed for the previous period (yesterday, last week, last month).
     */
    public function execute_webhook_export() {

        $this->log( 'Executing ' . self::$recurring_event_hook . ' event.' );

        // Reload settings in case they changed since schedule was created.
        $webhook_settings = get_option( $this->webhook_settings_option_name, array() );
        $webhook_schedule = isset( $webhook_settings['webhook_schedule'] ) ? $webhook_settings['webhook_schedule'] : null;
        $webhook_url      = isset( $webhook_settings['webhook_url'] ) ? $webhook_settings['webhook_url'] : null;

        if ( ! $webhook_url ) {
            $this->log( 'Not sending webhook post as there is no URL set.' );
            return;
        }

        if ( ! $webhook_schedule || ! in_array( $webhook_schedule, array( 'daily', 'weekly', 'monthly' ), true ) ) {
            $this->log( 'Not sending webhook post as there is no valid schedule set.' );
            return;
        }

        $site_day_of_week  = wpdai_site_date_time( 'D' );
        $site_day_of_month = (int) wpdai_site_date_time( 'j' );

        // Daily: run every time (we are scheduled for 1am each day).
        // Weekly: run only on Monday.
        // Monthly: run only on the 1st.
        $should_run = false;
        if ( 'daily' === $webhook_schedule ) {
            $should_run = true;
        } elseif ( 'weekly' === $webhook_schedule && 'Mon' === $site_day_of_week ) {
            $should_run = true;
        } elseif ( 'monthly' === $webhook_schedule && 1 === $site_day_of_month ) {
            $should_run = true;
        }

        if ( ! $should_run ) {
            $this->log( 'Skipping webhook post: schedule ' . $webhook_schedule . ' does not run today.' );
            return;
        }

        if ( ! function_exists( 'convert_to_screen' ) ) {
            require_once ABSPATH . 'wp-admin/includes/admin.php';
        }

        $from_date = null;
        $to_date   = null;

        if ( 'daily' === $webhook_schedule ) {
            $from_date = wpdai_site_date_time( WPD_AI_PHP_ISO_DATE, 'yesterday' );
            $to_date   = wpdai_site_date_time( WPD_AI_PHP_ISO_DATE, 'yesterday' );
        } elseif ( 'weekly' === $webhook_schedule ) {
            $from_date = wpdai_site_date_time( WPD_AI_PHP_ISO_DATE, 'Monday last week' );
            $to_date   = wpdai_site_date_time( WPD_AI_PHP_ISO_DATE, 'Sunday last week' );
        } elseif ( 'monthly' === $webhook_schedule ) {
            $from_date = wpdai_site_date_time( WPD_AI_PHP_ISO_DATE, 'first day of last month' );
            $to_date   = wpdai_site_date_time( WPD_AI_PHP_ISO_DATE, 'last day of last month' );
        }

        $this->log( 'Executing ' . $webhook_schedule . ' webhook post for ' . $from_date . ' to ' . $to_date . '.' );
        $webhook_response = wpdai_webhook_post_data( $from_date, $to_date );
        $this->log( $webhook_response );

        $this->log( 'Complete ' . self::$recurring_event_hook . ' event.' );

    }

}

// Initialize plugins, we are already in plugins_loaded
WPDAI_Webhook_Provider::get_instance();
WPDAI_Webhook_Provider::register_ajax_actions();