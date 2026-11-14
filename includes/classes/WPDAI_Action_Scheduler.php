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

class WPDAI_Action_Scheduler {

    public const EVENT_GROUP_SLUG                   = 'WP Davies';
    public const SINGLE_EVENT_REBUILD_PRODUCT_CACHE = 'wpd_rebuild_product_cache';
    public const SINGLE_EVENT_MIGRATION_RUNNER      = 'wpd_ai_migration_runner';
    public const RECURRING_EVENT_MIGRATION_CHECK    = 'wpd_schedule_migration_check';

    /** Product statistics refresh: 4 times a day. Not time-critical. */
    public const PRODUCT_ANALYTICS_INTERVAL         = 6 * 3600;

    /**
     * Class constructor: hooks into WooCommerce's Action Scheduler.
     */
    public function __construct() {

        // Setup the recurring actions
        add_action('init', array( $this, 'schedule_recurring_events' ));

        // Free actions
        add_action( 'wpd_schedule_emails',                              'wpdai_schedule_emails_function' );
        add_action( 'wpd_schedule_log_cleanup',                         'wpdai_schedule_log_cleanup_function' );
        add_action( 'wpd_schedule_analytics_db_cleanup',                'wpdai_schedule_analytics_db_cleanup_function' );
        add_action( 'wpd_schedule_database_upgrade',                    'wpdai_schedule_database_upgrade_function' );
        add_action( 'wpd_schedule_product_analytics_collector',         'wpdai_collect_product_statistics_cron' );
        add_action( 'wpd_schedule_customer_analytics_collector',        'wpdai_collect_customer_statistics_cron' );
        add_action( 'wpd_schedule_order_calculation_cache_collector',   'wpdai_fetch_and_store_last_n_uncached_orders_cron' );
        add_action( 'wpd_schedule_analytics_table_object_id_check',     'wpdai_set_post_id_post_type_on_null_events_analytics_table' );
        
        // Migration check - use lazy loading to avoid class dependency issues
        if ( class_exists( 'WPDAI_Migration' ) ) {
            add_action( self::RECURRING_EVENT_MIGRATION_CHECK, array( WPDAI_Migration::get_instance(), 'run_pending_migrations' ) );
        }

        // All single action hooks will need to run off this
        add_action( self::SINGLE_EVENT_REBUILD_PRODUCT_CACHE,           'wpdai_delete_all_product_cache');
        
    }

    /**
     * Schedules the background task if it hasn't been scheduled already.
     */
    public function schedule_recurring_events() {

        // One-off: clear duplicate recurring actions left by the old non-unique scheduling.
        $this->remove_duplicate_recurring_actions();

        // Drop pending actions whose interval we've since changed, so they're re-added below.
        $this->reschedule_changed_intervals();

        /**
         * Action hook to schedule recurring events.
         * 
         * @param WPDAI_Action_Scheduler $this The instance of the WPDAI_Action_Scheduler class.
         */
        do_action( 'wpd_schedule_recurring_events', $this );

        // Upgrade Database - Once a day, triggered immediately
        if ( ! as_next_scheduled_action( 'wpd_schedule_database_upgrade' ) ) {
            as_schedule_recurring_action( time(), DAY_IN_SECONDS, 'wpd_schedule_database_upgrade', array(), self::EVENT_GROUP_SLUG, true );
        }

        // Orders Cache - Every 5 minutes, 250 per run, delay by 5m for initial run
        if ( ! as_next_scheduled_action( 'wpd_schedule_order_calculation_cache_collector' ) ) {
            as_schedule_recurring_action( time() + 600, 5 * MINUTE_IN_SECONDS, 'wpd_schedule_order_calculation_cache_collector', array(), self::EVENT_GROUP_SLUG, true );
        }

        // Clean up the Analytics DB - Once a day
        if ( ! as_next_scheduled_action( 'wpd_schedule_analytics_db_cleanup' ) ) {
            as_schedule_recurring_action( time() + 720, DAY_IN_SECONDS, 'wpd_schedule_analytics_db_cleanup', array(), self::EVENT_GROUP_SLUG, true );
        }

        // Schedule Emails - Once a day
        if ( ! as_next_scheduled_action( 'wpd_schedule_emails' ) ) {
            as_schedule_recurring_action( time() + 900, DAY_IN_SECONDS, 'wpd_schedule_emails', array(), self::EVENT_GROUP_SLUG, true );
        }
        
        // Schedule log cleanup - Once a day
        if ( ! as_next_scheduled_action( 'wpd_schedule_log_cleanup' ) ) {
            as_schedule_recurring_action( time() + 1200, DAY_IN_SECONDS, 'wpd_schedule_log_cleanup', array(), self::EVENT_GROUP_SLUG, true );
        }

        // Clean up Analytics DB to include
        if ( ! as_next_scheduled_action( 'wpd_schedule_analytics_table_object_id_check' ) ) {
            as_schedule_recurring_action( time() + 1500, DAY_IN_SECONDS, 'wpd_schedule_analytics_table_object_id_check', array(), self::EVENT_GROUP_SLUG, true );
        }

        // Product Analytics - 4 times a day
        if ( ! as_next_scheduled_action( 'wpd_schedule_product_analytics_collector' ) ) {
            as_schedule_recurring_action( time() + 2400, self::PRODUCT_ANALYTICS_INTERVAL, 'wpd_schedule_product_analytics_collector', array(), self::EVENT_GROUP_SLUG, true );
        }

        // Customer Analytics
        if ( ! as_next_scheduled_action( 'wpd_schedule_customer_analytics_collector' ) ) {
            as_schedule_recurring_action( time() + 3000, HOUR_IN_SECONDS, 'wpd_schedule_customer_analytics_collector', array(), self::EVENT_GROUP_SLUG, true );
        }

        // Check for pending migrations - Once a day
        if ( ! as_next_scheduled_action( self::RECURRING_EVENT_MIGRATION_CHECK ) ) {
            as_schedule_recurring_action( time() + 1800, DAY_IN_SECONDS, self::RECURRING_EVENT_MIGRATION_CHECK, array(), self::EVENT_GROUP_SLUG, true );
        }

    }

    /**
     * Unschedule pending recurring actions whose interval no longer matches the code.
     *
     * Scheduling is unique per hook, so an action already pending at the old
     * interval would otherwise keep it forever. Only does Action Scheduler
     * queries when the interval map differs from the one last applied. To
     * change another task's interval later, add its hook here.
     *
     * @return void
     */
    private function reschedule_changed_intervals() {

        $intervals = array(
            'wpd_schedule_product_analytics_collector' => (int) self::PRODUCT_ANALYTICS_INTERVAL,
        );

        $option_name = 'wpd_ai_recurring_action_intervals';

        if ( get_option( $option_name ) === $intervals ) {
            return;
        }

        if ( ! function_exists( 'as_get_scheduled_actions' ) || ! function_exists( 'as_unschedule_all_actions' ) || ! class_exists( 'ActionScheduler_Store' ) ) {
            return;
        }

        update_option( $option_name, $intervals, true );

        foreach ( $intervals as $hook => $interval ) {

            $actions = as_get_scheduled_actions(
                array(
                    'hook'     => $hook,
                    'group'    => self::EVENT_GROUP_SLUG,
                    'status'   => ActionScheduler_Store::STATUS_PENDING,
                    'per_page' => -1,
                )
            );

            if ( ! is_array( $actions ) ) {
                continue;
            }

            foreach ( $actions as $action ) {

                $schedule = is_object( $action ) && method_exists( $action, 'get_schedule' ) ? $action->get_schedule() : null;

                if ( ! $schedule || ! $schedule->is_recurring() || ! method_exists( $schedule, 'get_recurrence' ) ) {
                    continue;
                }

                if ( (int) $schedule->get_recurrence() !== $interval ) {
                    // Cancels every pending copy of this hook; it's re-added at the new interval below.
                    as_unschedule_all_actions( $hook );
                    wpdai_write_log( 'Rescheduling ' . $hook . ' to run every ' . $interval . ' seconds.', 'cron' );
                    break;
                }
            }
        }

    }

    /**
     * Cancel duplicate pending recurring actions in our group, once.
     *
     * Before 5.14.1 the init check-then-schedule wasn't atomic, so concurrent
     * requests could each add a copy of a recurring action. Every copy recurs,
     * so an hourly task could end up running many times a minute. Keeps the
     * earliest pending action per hook + args and cancels the rest.
     *
     * @return void
     */
    private function remove_duplicate_recurring_actions() {

        $option_name = 'wpd_ai_recurring_actions_deduped';

        if ( get_option( $option_name ) ) {
            return;
        }

        if ( ! function_exists( 'as_get_scheduled_actions' ) || ! class_exists( 'ActionScheduler' ) || ! class_exists( 'ActionScheduler_Store' ) ) {
            return;
        }

        // Set first so concurrent requests don't all run the cleanup.
        update_option( $option_name, WPD_AI_VER, true );

        $actions = as_get_scheduled_actions(
            array(
                'group'    => self::EVENT_GROUP_SLUG,
                'status'   => ActionScheduler_Store::STATUS_PENDING,
                'per_page' => -1,
                'orderby'  => 'date',
                'order'    => 'ASC',
            )
        );

        if ( ! is_array( $actions ) || empty( $actions ) ) {
            return;
        }

        $seen      = array();
        $cancelled = 0;
        $store     = ActionScheduler::store();

        foreach ( $actions as $action_id => $action ) {

            if ( ! is_object( $action ) || ! method_exists( $action, 'get_schedule' ) || ! $action->get_schedule()->is_recurring() ) {
                continue;
            }

            $key = $action->get_hook() . '|' . md5( wp_json_encode( $action->get_args() ) );

            if ( ! isset( $seen[ $key ] ) ) {
                $seen[ $key ] = true;
                continue;
            }

            try {
                $store->cancel_action( $action_id );
                $cancelled++;
            } catch ( Exception $e ) {
                wpdai_write_log( 'Could not cancel duplicate scheduled action ' . $action_id . ': ' . $e->getMessage(), 'cron' );
            }
        }

        if ( $cancelled > 0 ) {
            wpdai_write_log( 'Cancelled ' . $cancelled . ' duplicate recurring scheduled actions.', 'cron' );
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
new WPDAI_Action_Scheduler();