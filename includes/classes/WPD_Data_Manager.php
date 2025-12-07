<?php
/**
 *
 * Handles data management for the plugin
 *
 * @package Alpha Insights
 * @since 1.0.0
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

class WPD_Data_Manager {

    /**
     * Instance of this class
     *
     * @var WPD_Data_Manager
     */
    private static $instance = null;

    /**
     * Get the singleton instance of this class
     *
     * @return WPD_Data_Manager
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Prevent cloning of the instance
     *
     * @return void
     */
    private function __clone() {
        // Prevent cloning
    }
    
    /**
     * Prevent unserialization of the instance
     *
     * @return void
     */
    public function __wakeup() {
        // Prevent unserialization
    }
    
    /**
     * Cached HPOS table existence check
     *
     * @var bool|null
     */
    private static $hpos_table_exists = null;

    /**
     * 
     *  Initiate class
     * 
     **/
    private function __construct() {


    }

    /**
     * Check if HPOS orders meta table exists (cached)
     *
     * @return bool|string False if not exists, table name if exists
     */
    private function get_hpos_orders_meta_table() {
        if ( null !== self::$hpos_table_exists ) {
            return self::$hpos_table_exists;
        }

        if ( ! function_exists( 'wpd_is_hpos_enabled' ) || ! wpd_is_hpos_enabled() ) {
            self::$hpos_table_exists = false;
            return false;
        }

        global $wpdb;
        $orders_meta_table = $wpdb->prefix . 'wc_orders_meta';
        $table_exists = $wpdb->get_var( $wpdb->prepare( 
            "SHOW TABLES LIKE %s", 
            $orders_meta_table 
        ) );
        
        if ( $table_exists === $orders_meta_table ) {
            self::$hpos_table_exists = $orders_meta_table;
            return $orders_meta_table;
        }
        
        self::$hpos_table_exists = false;
        return false;
    }
    
    /**
     * Get all transient keys set by Alpha Insights
     *
     * @return array
     */
    public function get_all_transient_keys() {

        $static_values = array(
            'wpd_ai_activation_redirect',
            'wpd_report_filters_order_query_parameter_values',
            'wpd_report_filters_users',
            'wpd_report_filters_products',
            'wpd_report_filters_product_categories',
            'wpd_report_filters_product_tags',
            'wpd_report_filters_billing_countries',
            'wpd_report_filters_facebook_campaigns',
            'wpd_report_filters_google_campaigns',
            'wpd_report_filters_expense_categories',
            'wpd_report_filters_website_traffic_query_parameter_values',
            'wpd_report_filters_website_traffic_events',
            'wpd_fb_app_credentials',
            'wpd_google_app_credentials',
            'wpd_product_meta_keys',
            'wpd_all_order_ids',
            'wpd_all_order_ids_lock',
            '_wpd_updating_all_orders_cache'
        );

        // Dynamic values start with..
        $dynamic_value_keys_start_with = array(
            '_wpd_ip_requests_per_minute',
            '_wpd_ip_banned_event_tracking',
            'wpd_fb_auth_state',
            'wpd_google_auth_state',
            '_wpd_customer_first_order_id',
            '_wpd_product_statistics',
            '_wpd_user_analytics',
        );

        // Fetch transients from transients table
        global $wpdb;
        $transient_values = array();
        
        // Optimize: Combine multiple LIKE patterns into a single query with OR conditions
        if ( ! empty( $dynamic_value_keys_start_with ) ) {
            $like_conditions = array();
            $prepared_values = array();
            
            foreach ( $dynamic_value_keys_start_with as $pattern ) {
                $like_conditions[] = "option_name LIKE %s";
                $prepared_values[] = '%' . $wpdb->esc_like( $pattern ) . '%';
            }
            
            if ( ! empty( $like_conditions ) ) {
                // Build query with OR conditions - all values are properly escaped via prepare()
                // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                $query = "SELECT DISTINCT option_name FROM $wpdb->options WHERE " . implode( ' OR ', $like_conditions );
                $query = $wpdb->prepare( $query, $prepared_values );
                $results = $wpdb->get_col( $query );
                if ( ! empty( $results ) && is_array( $results ) ) {
                    $transient_values = $results;
                }
            }
        }
        
        $all_values = array_merge( $static_values, array_map( 'trim', $transient_values ) );
        return $all_values;

    }
    
    /**
     * Get all option keys set by Alpha Insights
     *
     * @return array
     */
    public function get_all_option_keys() {

        $static_values = array(
            // Plugin version and update tracking
            'wpd_ai_db_version',
            'wpd_ai_pending_db_update',
            'wpd_ai_cache_version',
            'wpd_ai_plugin_update_version',
            'wpd_ai_pending_rewrite_flush',
            'wpd_ai_pending_report_installation',
            'wpd_ai_onboarding_completed',
            
            // License and API
            'wpd_ai_api_key',
            'wpd_ai_license_status',
            'wpd_ai_license_details',
            
            // General settings
            'wpd_ai_currency_table',
            'wpd_ai_order_status',
            'wpd_ai_cache_build_batch_size',
            'wpd_ai_plugin_visibility',
            'wpd_ai_cost_defaults',
            'wpd_ai_admin_custom_columns',
            'wpd_ai_refunded_order_costs',
            'wpd_ai_admin_style_override',
            'wpd_ai_prevent_wp_notices',
            'wpd_ai_use_legacy_order_admin_metaboxes',
            'wpd_ai_user_interface_display_settings',
            
            // Cost settings
            'wpd_ai_payment_gateway_costs',
            'wpd_ai_custom_order_costs',
            'wpd_ai_custom_product_costs',
            
            // Integrations
            'wpd_ai_facebook_integration',
            'wpd_ai_google_ads_api',
            'wpd_ai_google_ads_profit_conversion_action_id',
            'wpd_ai_starshipit_api_key',
            'wpd_ai_starshipit_subscription_key',
            
            // Analytics and tracking
            'wpd_ai_analytics',
            'wpd_profit_tracking_oer_api_key',
            
            // Email settings
            'wpd_ai_email_settings',
            'wpd_ai_daily_emails_sent',
            'wpd_ai_weekly_emails_sent',
            'wpd_ai_monthly_emails_sent',
            
            // Webhooks
            'wpd_ai_webhooks',
            'wpd_ai_webhook_settings',
            
            // Cache status
            'wpd_ai_all_orders_cached',
        );

        // Dynamic values start with..
        $dynamic_value_keys_start_with = array(
            'wpd_dashboard_config_',
        );

        // Fetch dynamic options from database
        global $wpdb;
        $dynamic_options = array();
        
        foreach ( $dynamic_value_keys_start_with as $prefix ) {
            $query = $wpdb->prepare(
                "
                SELECT DISTINCT(option_name)
                FROM $wpdb->options
                WHERE option_name LIKE %s
                ",
                $wpdb->esc_like( $prefix ) . '%'
            );
            $results = $wpdb->get_col( $query );
            if ( ! empty( $results ) && is_array( $results ) ) {
                $dynamic_options = array_merge( $dynamic_options, $results );
            }
        }

        // Combine static and dynamic options
        $all_options = array_merge( $static_values, $dynamic_options );
        
        // Remove duplicates and return
        return array_unique( $all_options );

    }

    /**
     * Get all post meta keys set by Alpha Insights
     *
     * Includes meta keys for orders (HPOS aware), order items, products, expenses, and other post types.
     *
     * @return array
     */
    public function get_all_post_meta_keys() {

        $all_keys = array_merge(
            $this->get_all_product_meta_keys(),
            $this->get_all_order_meta_keys(),
            $this->get_all_expense_meta_keys(),
            $this->get_all_facebook_campaign_meta_keys(),
            $this->get_all_google_campaign_meta_keys()
        );

        return array_unique( $all_keys );

    }

    /**
     * Get all product meta keys set by Alpha Insights
     *
     * @return array
     */
    public function get_all_product_meta_keys() {

        $static_keys = array(
            '_wpd_ai_product_cost',
            '_wpd_ai_custom_product_costs',
            '_wpd_ai_product_data_store',
            '_wpd_ai_product_supplier',
        );

        // Query postmeta for any additional product meta keys we might have missed
        global $wpdb;
        $query = $wpdb->prepare(
            "
            SELECT DISTINCT(pm.meta_key)
            FROM $wpdb->postmeta pm
            INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
            WHERE p.post_type IN ('product', 'product_variation')
            AND (pm.meta_key LIKE %s OR pm.meta_key LIKE %s)
            ",
            $wpdb->esc_like( '_wpd_ai_' ) . '%',
            $wpdb->esc_like( '_wpd_' ) . '%'
        );
        $dynamic_keys = $wpdb->get_col( $query );

        return array_unique( array_merge( $static_keys, $dynamic_keys ? $dynamic_keys : array() ) );

    }

    /**
     * Get all order meta keys set by Alpha Insights
     *
     * Includes order meta and order item meta. HPOS aware.
     *
     * @return array
     */
    public function get_all_order_meta_keys() {

        $static_keys = array(
            // Order Meta Keys
            '_wpd_ai_total_shipping_cost',
            '_wpd_ai_total_payment_gateway_cost',
            '_wpd_ai_total_product_cost',
            '_wpd_ai_total_order_product_custom_cost',
            '_wpd_ai_landing_page',
            '_wpd_ai_referral_source',
            '_wpd_ai_session_id',
            '_wpd_ai_google_campaign_id',
            '_wpd_ai_meta_campaign_id',
            '_wpd_ai_google_api_campaign_id_check',
            '_wpd_ai_starshipit_api_cost',
            '_wpd_ai_starshipit_api_sync_attempt_count',
            '_wpd_ai_exchange_rate',
            
            // Order Item Meta Keys
            '_wpd_ai_product_cogs',
            '_wpd_ai_product_cogs_currency',
            '_wpd_ai_multi_cogs_total_cost',
            '_wpd_ai_multi_cogs_data',
            '_wpd_ai_multi_cogs_stock_withdrawn',
            '_wpd_ai_custom_product_costs',
        );

        global $wpdb;
        $dynamic_keys = array();

        // Query for dynamic custom order cost keys
        $query = $wpdb->prepare(
            "
            SELECT DISTINCT(meta_key)
            FROM $wpdb->postmeta
            WHERE meta_key LIKE %s
            ",
            $wpdb->esc_like( '_wpd_ai_custom_order_cost_' ) . '%'
        );
        $custom_order_costs = $wpdb->get_col( $query );
        if ( ! empty( $custom_order_costs ) && is_array( $custom_order_costs ) ) {
            $dynamic_keys = array_merge( $dynamic_keys, $custom_order_costs );
        }

        // Query wc_orders_meta table if HPOS is enabled
        if ( function_exists( 'wpd_is_hpos_enabled' ) && wpd_is_hpos_enabled() ) {
            $orders_meta_table = $wpdb->prefix . 'wc_orders_meta';
            
            $table_exists = $wpdb->get_var( $wpdb->prepare( 
                "SHOW TABLES LIKE %s", 
                $orders_meta_table 
            ) );
            
            if ( $table_exists === $orders_meta_table ) {
                // Get all Alpha Insights order meta keys from HPOS table
                $query = $wpdb->prepare(
                    "
                    SELECT DISTINCT(meta_key)
                    FROM $orders_meta_table
                    WHERE meta_key LIKE %s
                    ",
                    $wpdb->esc_like( '_wpd_ai_' ) . '%'
                );
                $hpos_keys = $wpdb->get_col( $query );
                if ( ! empty( $hpos_keys ) && is_array( $hpos_keys ) ) {
                    $dynamic_keys = array_merge( $dynamic_keys, $hpos_keys );
                }
            }
        }

        // Also query postmeta for order-related keys (for legacy installations)
        $query = $wpdb->prepare(
            "
            SELECT DISTINCT(pm.meta_key)
            FROM $wpdb->postmeta pm
            INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
            WHERE p.post_type = 'shop_order'
            AND (pm.meta_key LIKE %s OR pm.meta_key LIKE %s)
            ",
            $wpdb->esc_like( '_wpd_ai_' ) . '%',
            $wpdb->esc_like( '_wpd_' ) . '%'
        );
        $legacy_order_keys = $wpdb->get_col( $query );
        if ( ! empty( $legacy_order_keys ) && is_array( $legacy_order_keys ) ) {
            $dynamic_keys = array_merge( $dynamic_keys, $legacy_order_keys );
        }

        return array_unique( array_merge( $static_keys, $dynamic_keys ) );

    }

    /**
     * Get all expense meta keys set by Alpha Insights
     *
     * @return array
     */
    public function get_all_expense_meta_keys() {

        $static_keys = array(
            '_wpd_paid',
            '_wpd_amount_paid',
            '_wpd_amount_paid_currency',
            '_wpd_date_paid',
            '_wpd_expense_reference',
            '_wpd_recurring_expense_enabled',
            '_wpd_recurring_expense_frequency',
            '_wpd_recurring_expense_beginning_date',
            '_wpd_recurring_expense_end_date',
            '_wpd_expense_attachment',
        );

        // Query postmeta for any additional expense meta keys we might have missed
        global $wpdb;
        $query = $wpdb->prepare(
            "
            SELECT DISTINCT(pm.meta_key)
            FROM $wpdb->postmeta pm
            INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
            WHERE p.post_type = 'expense'
            AND pm.meta_key LIKE %s
            ",
            $wpdb->esc_like( '_wpd_' ) . '%'
        );
        $dynamic_keys = $wpdb->get_col( $query );

        return array_unique( array_merge( $static_keys, $dynamic_keys ? $dynamic_keys : array() ) );

    }

    /**
     * Get all Facebook campaign meta keys set by Alpha Insights
     *
     * @return array
     */
    public function get_all_facebook_campaign_meta_keys() {

        $static_keys = array(
            '_wpd_campaign_name',
            '_wpd_campaign_id',
            '_wpd_campaign_spend',
            '_wpd_campaign_impressions',
            '_wpd_campaign_clicks',
            '_wpd_campaign_outbound_clicks',
            '_wpd_campaign_leads',
            '_wpd_campaign_purchases',
            '_wpd_campaign_purchase_value',
            '_wpd_campaign_conversion_rate',
            '_wpd_campaign_roas',
            '_wpd_campaign_start',
            '_wpd_campaign_stop',
            '_wpd_totals_data',
            '_wpd_daily_data',
            '_wpd_campaign_currency',
            '_wpd_campaign_status',
            '_wpd_campaign_last_updated_unix',
            '_wpd_campaign_average_cpc',
            '_wpd_campaign_average_ctr',
            '_wpd_campaign_days_active',
            '_wpd_campaign_profit',
            '_wpd_campaign_total_days',
            '_wpd_campaign_conversion_value',
            '_wpd_campaign_conversions',
            '_wpd_campaign_ad_account_name',
            '_wpd_campaign_ad_account_id',
        );

        // Query postmeta for any additional Facebook campaign meta keys we might have missed
        global $wpdb;
        $query = $wpdb->prepare(
            "
            SELECT DISTINCT(pm.meta_key)
            FROM $wpdb->postmeta pm
            INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
            WHERE p.post_type = 'facebook_campaign'
            AND pm.meta_key LIKE %s
            ",
            $wpdb->esc_like( '_wpd_' ) . '%'
        );
        $dynamic_keys = $wpdb->get_col( $query );

        return array_unique( array_merge( $static_keys, $dynamic_keys ? $dynamic_keys : array() ) );

    }

    /**
     * Get all Google campaign meta keys set by Alpha Insights
     *
     * @return array
     */
    public function get_all_google_campaign_meta_keys() {

        $static_keys = array(
            '_wpd_campaign_name',
            '_wpd_campaign_id',
            '_wpd_campaign_spend',
            '_wpd_campaign_impressions',
            '_wpd_campaign_clicks',
            '_wpd_campaign_outbound_clicks',
            '_wpd_campaign_leads',
            '_wpd_campaign_purchases',
            '_wpd_campaign_purchase_value',
            '_wpd_campaign_conversion_rate',
            '_wpd_campaign_roas',
            '_wpd_campaign_start',
            '_wpd_campaign_stop',
            '_wpd_totals_data',
            '_wpd_daily_data',
            '_wpd_campaign_currency',
            '_wpd_campaign_status',
            '_wpd_campaign_last_updated_unix',
            '_wpd_campaign_average_cpc',
            '_wpd_campaign_average_ctr',
            '_wpd_campaign_days_active',
            '_wpd_campaign_profit',
            '_wpd_campaign_total_days',
            '_wpd_campaign_conversion_value',
            '_wpd_campaign_conversions',
            '_wpd_campaign_ad_account_name',
            '_wpd_campaign_ad_account_id',
        );

        // Query postmeta for any additional Google campaign meta keys we might have missed
        global $wpdb;
        $query = $wpdb->prepare(
            "
            SELECT DISTINCT(pm.meta_key)
            FROM $wpdb->postmeta pm
            INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
            WHERE p.post_type = 'google_ad_campaign'
            AND pm.meta_key LIKE %s
            ",
            $wpdb->esc_like( '_wpd_' ) . '%'
        );
        $dynamic_keys = $wpdb->get_col( $query );

        return array_unique( array_merge( $static_keys, $dynamic_keys ? $dynamic_keys : array() ) );

    }

    /**
     * Get all database table names set by Alpha Insights
     *
     * @return array
     */
    public function get_db_table_names() {

        if ( ! class_exists('WPD_Database_Interactor') ) {
            require_once( WPD_AI_PATH . 'includes/classes/WPD_Database_Interactor.php');
        }

        return ( new WPD_Database_Interactor() )->get_all_table_names();

    }

    /**
     * Delete all transients set by Alpha Insights
     *
     * @return int Number of transients deleted
     */
    public function delete_all_transients() {
        
        $transient_keys = $this->get_all_transient_keys();
        $deleted_count = 0;

        foreach ( $transient_keys as $key ) {
            if ( delete_transient( $key ) ) {
                $deleted_count++;
            }
            // Also delete the timeout transient
            delete_option( '_transient_timeout_' . $key );
        }

        return $deleted_count;
    }

    /**
     * Delete all options set by Alpha Insights
     *
     * @return int Number of options deleted
     */
    public function delete_all_options() {
        
        $option_keys = $this->get_all_option_keys();
        $deleted_count = 0;

        foreach ( $option_keys as $key ) {
            if ( delete_option( $key ) ) {
                $deleted_count++;
            }
        }

        return $deleted_count;
    }

    /**
     * Delete all post meta set by Alpha Insights
     *
     * @return int Number of meta entries deleted
     */
    public function delete_all_post_meta() {
        
        $meta_keys = $this->get_all_post_meta_keys();
        $deleted_count = 0;
        global $wpdb;

        foreach ( $meta_keys as $meta_key ) {
            // Delete from postmeta table
            $deleted = $wpdb->query( $wpdb->prepare(
                "DELETE FROM $wpdb->postmeta WHERE meta_key = %s",
                $meta_key
            ) );
            $deleted_count += $deleted;

            // Also delete from HPOS orders meta table if enabled
            if ( function_exists( 'wpd_is_hpos_enabled' ) && wpd_is_hpos_enabled() ) {
                $orders_meta_table = $wpdb->prefix . 'wc_orders_meta';
                $table_exists = $wpdb->get_var( $wpdb->prepare( 
                    "SHOW TABLES LIKE %s", 
                    $orders_meta_table 
                ) );
                
                if ( $table_exists === $orders_meta_table ) {
                    $deleted = $wpdb->query( $wpdb->prepare(
                        "DELETE FROM $orders_meta_table WHERE meta_key = %s",
                        $meta_key
                    ) );
                    $deleted_count += $deleted;
                }
            }
        }

        return $deleted_count;
    }

    /**
     * Delete all database tables set by Alpha Insights
     *
     * @return int Number of tables deleted
     */
    public function delete_all_database_tables() {

        $table_names = $this->get_db_table_names();
        $deleted_count = 0;

        foreach ( $table_names as $table_name ) {
            if ( $this->delete_database_table( $table_name ) ) {
                $deleted_count++;
            }
        }

        return $deleted_count;
    }

    /**
     * Delete a specific database table set by Alpha Insights
     *
     * @param string $table_name The table name to delete
     * @return bool True if deleted, false otherwise
     */
    public function delete_database_table( $table_name ) {
        
        if ( empty( $table_name ) || ! is_string( $table_name ) ) {
            return false;
        }

        global $wpdb;
        
        // Sanitize table name - only allow alphanumeric, underscores, and hyphens
        $table_name = preg_replace( '/[^a-zA-Z0-9_-]/', '', $table_name );
        
        // Ensure table name has the correct prefix
        if ( strpos( $table_name, $wpdb->prefix ) !== 0 ) {
            $table_name = $wpdb->prefix . $table_name;
        }

        // Check if table exists
        $table_exists = $wpdb->get_var( $wpdb->prepare( 
            "SHOW TABLES LIKE %s", 
            $table_name 
        ) );

        if ( $table_exists !== $table_name ) {
            return false;
        }

        // Delete the table - table name is already sanitized and validated
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->query( "DROP TABLE IF EXISTS `$table_name`" );
        
        return $result !== false;
    }

    /**
     * Truncate all database tables set by Alpha Insights
     * Removes all data from all tables
     *
     * @return int Number of tables truncated
     */
    public function truncate_all_database_tables() {
        
        $table_names = $this->get_db_table_names();
        $truncated_count = 0;

        foreach ( $table_names as $table_name ) {
            if ( $this->truncate_database_table( $table_name ) ) {
                $truncated_count++;
            }
        }

        return $truncated_count;
    }

    /**
     * Truncate a database table set by Alpha Insights
     * Removes all data from the table
     *
     * @param string $table_name The table name to truncate
     * @return bool True if truncated, false otherwise
     */
    public function truncate_database_table( $table_name ) {
        
        if ( empty( $table_name ) || ! is_string( $table_name ) ) {
            return false;
        }

        global $wpdb;
        
        // Sanitize table name
        $table_name = preg_replace( '/[^a-zA-Z0-9_-]/', '', $table_name );
        
        // Ensure table name has the correct prefix
        if ( strpos( $table_name, $wpdb->prefix ) !== 0 ) {
            $table_name = $wpdb->prefix . $table_name;
        }

        // Check if table exists
        $table_exists = $wpdb->get_var( $wpdb->prepare( 
            "SHOW TABLES LIKE %s", 
            $table_name 
        ) );

        if ( $table_exists !== $table_name ) {
            return false;
        }

        // Truncate the table - table name is already sanitized and validated
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->query( "TRUNCATE TABLE `$table_name`" );
        
        return $result !== false;
    }

    /**
     * Delete all expenses and meta data set by Alpha Insights
     *
     * @return int Number of expenses deleted
     */
    public function delete_all_expenses_and_meta_data() {
        
        $expense_ids = get_posts( array(
            'post_type'      => 'expense',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_status'    => 'any',
        ) );

        $deleted_count = 0;

        foreach ( $expense_ids as $expense_id ) {
            if ( wp_delete_post( $expense_id, true ) ) {
                $deleted_count++;
            }
        }

        return $deleted_count;
    }

    /**
     * Delete all Facebook campaigns and meta data set by Alpha Insights
     *
     * @return int Number of campaigns deleted
     */
    public function delete_all_facebook_campaigns_and_meta_data() {
        
        $campaign_ids = get_posts( array(
            'post_type'      => 'facebook_campaign',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_status'    => 'any',
        ) );

        $deleted_count = 0;

        foreach ( $campaign_ids as $campaign_id ) {
            if ( wp_delete_post( $campaign_id, true ) ) {
                $deleted_count++;
            }
        }

        return $deleted_count;
    }

    /**
     * Delete all Google campaigns and meta data set by Alpha Insights
     *
     * @return int Number of campaigns deleted
     */
    public function delete_all_google_campaigns_and_meta_data() {
        
        $campaign_ids = get_posts( array(
            'post_type'      => 'google_ad_campaign',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_status'    => 'any',
        ) );

        $deleted_count = 0;

        foreach ( $campaign_ids as $campaign_id ) {
            if ( wp_delete_post( $campaign_id, true ) ) {
                $deleted_count++;
            }
        }

        return $deleted_count;
    }

    /**
     * Delete all order line item data set by Alpha Insights
     *
     * @return int Number of meta entries deleted
     */
    public function delete_all_order_line_item_data() {

        $order_item_meta_keys = array(
            '_wpd_ai_product_cogs',
            '_wpd_ai_product_cogs_currency',
            '_wpd_ai_multi_cogs_total_cost',
            '_wpd_ai_multi_cogs_data',
            '_wpd_ai_multi_cogs_stock_withdrawn',
            '_wpd_ai_custom_product_costs',
        );

        global $wpdb;
        $item_meta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
        $deleted_count = 0;

        foreach ( $order_item_meta_keys as $meta_key ) {
            $deleted = $wpdb->query( $wpdb->prepare(
                "DELETE FROM $item_meta_table WHERE meta_key = %s",
                $meta_key
            ) );
            $deleted_count += $deleted;
        }

        return $deleted_count;
    }

    /**
     * Delete all order meta shipping costs set by Alpha Insights
     *
     * @return int Number of meta entries deleted
     */
    public function delete_all_order_meta_shipping_costs_overrides() {

        return $this->delete_order_meta_by_key( '_wpd_ai_total_shipping_cost' );
    }

    /**
     * Delete all order meta payment gateway costs set by Alpha Insights
     *
     * @return int Number of meta entries deleted
     */
    public function delete_all_order_meta_payment_gateway_costs_overrides() {
        
        return $this->delete_order_meta_by_key( '_wpd_ai_total_payment_gateway_cost' );
    }

    /**
     * Delete all order meta product costs set by Alpha Insights
     *
     * @return int Number of meta entries deleted
     */
    public function delete_all_order_meta_product_costs_overrides() {
        
        return $this->delete_order_meta_by_key( '_wpd_ai_total_product_cost' );
    }

    /**
     * Delete all order meta custom product costs set by Alpha Insights
     *
     * @return int Number of meta entries deleted
     */
    public function delete_all_order_meta_custom_product_costs() {
        
        return $this->delete_order_meta_by_key( '_wpd_ai_total_order_product_custom_cost' );
    }

    /**
     * Delete all order meta custom order costs set by Alpha Insights
     *
     * @return int Number of meta entries deleted
     */
    public function delete_all_order_meta_custom_order_costs() {
        
        global $wpdb;
        $deleted_count = 0;

        // Delete from postmeta
        $deleted = $wpdb->query( $wpdb->prepare(
            "DELETE FROM $wpdb->postmeta WHERE meta_key LIKE %s",
            $wpdb->esc_like( '_wpd_ai_custom_order_cost_' ) . '%'
        ) );
        $deleted_count += $deleted;

        // Delete from HPOS orders meta table if enabled
        if ( function_exists( 'wpd_is_hpos_enabled' ) && wpd_is_hpos_enabled() ) {
            $orders_meta_table = $wpdb->prefix . 'wc_orders_meta';
            $table_exists = $wpdb->get_var( $wpdb->prepare( 
                "SHOW TABLES LIKE %s", 
                $orders_meta_table 
            ) );
            
            if ( $table_exists === $orders_meta_table ) {
                $deleted = $wpdb->query( $wpdb->prepare(
                    "DELETE FROM $orders_meta_table WHERE meta_key LIKE %s",
                    $wpdb->esc_like( '_wpd_ai_custom_order_cost_' ) . '%'
                ) );
                $deleted_count += $deleted;
            }
        }

        return $deleted_count;
    }

    /**
     * Helper method to delete order meta by key (HPOS aware)
     *
     * @param string $meta_key The meta key to delete
     * @return int Number of meta entries deleted
     */
    public function delete_order_meta_by_key( $meta_key ) {
        
        if ( empty( $meta_key ) || ! is_string( $meta_key ) ) {
            return 0;
        }

        global $wpdb;
        $deleted_count = 0;

        // Delete from postmeta (for legacy installations)
        $deleted = $wpdb->query( $wpdb->prepare(
            "DELETE FROM $wpdb->postmeta WHERE meta_key = %s",
            $meta_key
        ) );
        $deleted_count += $deleted;

        // Delete from HPOS orders meta table if enabled
        if ( function_exists( 'wpd_is_hpos_enabled' ) && wpd_is_hpos_enabled() ) {
            $orders_meta_table = $wpdb->prefix . 'wc_orders_meta';
            $table_exists = $wpdb->get_var( $wpdb->prepare( 
                "SHOW TABLES LIKE %s", 
                $orders_meta_table 
            ) );
            
            if ( $table_exists === $orders_meta_table ) {
                $deleted = $wpdb->query( $wpdb->prepare(
                    "DELETE FROM $orders_meta_table WHERE meta_key = %s",
                    $meta_key
                ) );
                $deleted_count += $deleted;
            }
        }

        return $deleted_count;
    }

    /**
     * Get all scheduled Action Scheduler tasks set by Alpha Insights
     *
     * @return array Array of scheduled actions with their details
     */
    public function get_all_scheduled_tasks() {

        // Check if Action Scheduler is available
        if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
            return array();
        }

        global $wpdb;
        $scheduled_tasks = array();

        // Get all actions with our group slug
        $group_slug = 'WP Davies';
        
        // Query Action Scheduler tables directly for more comprehensive results
        $actions_table = $wpdb->prefix . 'actionscheduler_actions';
        $groups_table = $wpdb->prefix . 'actionscheduler_groups';
        
        // Check if tables exist
        $actions_table_exists = $wpdb->get_var( $wpdb->prepare( 
            "SHOW TABLES LIKE %s", 
            $actions_table 
        ) );
        
        if ( $actions_table_exists === $actions_table ) {
            // Get all actions for our group
            $query = $wpdb->prepare(
                "
                SELECT a.action_id, a.hook, a.status, a.scheduled_date_gmt, a.args, a.group_id
                FROM $actions_table a
                INNER JOIN $groups_table g ON a.group_id = g.group_id
                WHERE g.slug = %s
                AND a.status IN ('pending', 'in-progress')
                ",
                $group_slug
            );
            $group_actions = $wpdb->get_results( $query, ARRAY_A );
            
            if ( ! empty( $group_actions ) && is_array( $group_actions ) ) {
                $scheduled_tasks = array_merge( $scheduled_tasks, $group_actions );
            }
            
            // Also get actions with hooks starting with 'wpd_' (in case they're not in our group)
            $query = $wpdb->prepare(
                "
                SELECT a.action_id, a.hook, a.status, a.scheduled_date_gmt, a.args, a.group_id
                FROM $actions_table a
                WHERE a.hook LIKE %s
                AND a.status IN ('pending', 'in-progress')
                ",
                $wpdb->esc_like( 'wpd_' ) . '%'
            );
            $hook_actions = $wpdb->get_results( $query, ARRAY_A );
            
            if ( ! empty( $hook_actions ) && is_array( $hook_actions ) ) {
                // Merge and remove duplicates by action_id
                $existing_ids = array_column( $scheduled_tasks, 'action_id' );
                foreach ( $hook_actions as $action ) {
                    if ( ! in_array( $action['action_id'], $existing_ids, true ) ) {
                        $scheduled_tasks[] = $action;
                    }
                }
            }
        } else {
            // Fallback: Use Action Scheduler API if tables don't exist
            // Get all known hook names from the plugin
            $known_hooks = array(
                'wpd_schedule_database_upgrade',
                'wpd_schedule_license_check',
                'wpd_schedule_order_cache',
                'wpd_schedule_product_cache',
                'wpd_schedule_facebook_api_call',
                'wpd_schedule_google_data_fetch',
                'wpd_schedule_analytics_table_object_id_check',
                'wpd_schedule_webhook',
                'wpd_rebuild_order_cache',
                'wpd_rebuild_product_cache',
                'wpd_google_ads_track_order_profit_conversion',
                'wpd_google_ads_track_add_to_cart_conversion',
            );
            
            if ( function_exists( 'as_get_scheduled_actions' ) ) {
                foreach ( $known_hooks as $hook ) {
                    // phpcs:ignore -- Action Scheduler function loaded at runtime
                    $actions = as_get_scheduled_actions( array( 'hook' => $hook ) );
                    if ( ! empty( $actions ) ) {
                        foreach ( $actions as $action ) {
                            $schedule = $action->get_schedule();
                            $scheduled_date = '';
                            if ( $schedule && method_exists( $schedule, 'get_date' ) ) {
                                $date = $schedule->get_date();
                                if ( $date ) {
                                    $scheduled_date = $date->format( 'Y-m-d H:i:s' );
                                }
                            }
                            
                            $scheduled_tasks[] = array(
                                'action_id' => $action->get_id(),
                                'hook' => $hook,
                                'status' => $action->get_status(),
                                'scheduled_date_gmt' => $scheduled_date,
                                'args' => $action->get_args(),
                                'group_id' => $action->get_group(),
                            );
                        }
                    }
                }
            }
        }

        return $scheduled_tasks;
    }

    /**
     * Unschedule and delete all Action Scheduler tasks set by Alpha Insights
     *
     * @return int Number of tasks unscheduled/deleted
     */
    public function delete_all_scheduled_tasks() {

        // Check if Action Scheduler is available
        if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
            return 0;
        }

        $deleted_count = 0;
        $group_slug = 'WP Davies';

        // Get all scheduled tasks first
        $scheduled_tasks = $this->get_all_scheduled_tasks();

        // Get unique hook names
        $hook_names = array();
        if ( ! empty( $scheduled_tasks ) && is_array( $scheduled_tasks ) ) {
            $hook_names = array_unique( array_column( $scheduled_tasks, 'hook' ) );
            // Remove any empty or null values
            $hook_names = array_filter( $hook_names, function( $hook ) {
                return ! empty( $hook ) && is_string( $hook );
            } );
        }

        // Unschedule all actions by hook name
        foreach ( $hook_names as $hook ) {
            if ( ! empty( $hook ) ) {
                // phpcs:ignore -- Action Scheduler function loaded at runtime
                $unscheduled = as_unschedule_all_actions( $hook );
                if ( $unscheduled !== false ) {
                    $deleted_count += $unscheduled;
                }
            }
        }

        // Also unschedule by group if Action Scheduler supports it
        global $wpdb;
        $actions_table = $wpdb->prefix . 'actionscheduler_actions';
        $groups_table = $wpdb->prefix . 'actionscheduler_groups';
        
        $actions_table_exists = $wpdb->get_var( $wpdb->prepare( 
            "SHOW TABLES LIKE %s", 
            $actions_table 
        ) );
        
        if ( $actions_table_exists === $actions_table ) {
            // Get group ID
            $group_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT group_id FROM $groups_table WHERE slug = %s",
                $group_slug
            ) );
            
            if ( $group_id ) {
                // Get all action IDs for this group
                $action_ids = $wpdb->get_col( $wpdb->prepare(
                    "SELECT action_id FROM $actions_table WHERE group_id = %d AND status IN ('pending', 'in-progress')",
                    $group_id
                ) );
                
                // Delete each action
                foreach ( $action_ids as $action_id ) {
                    if ( function_exists( 'as_delete_action' ) ) {
                        // phpcs:ignore -- Action Scheduler function loaded at runtime
                        if ( as_delete_action( $action_id ) ) {
                            $deleted_count++;
                        }
                    }
                }
            }
        }

        return $deleted_count;
    }

    /**
     * Delete all data set by Alpha Insights
     *
     * @param array $entities_to_delete The entities to delete
     * @return void
     */
    public function delete_all_plugin_data( ) {

        $this->delete_all_transients();

        $this->delete_all_options();

        $this->delete_all_post_meta();

        $this->delete_all_expenses_and_meta_data();

        $this->delete_all_facebook_campaigns_and_meta_data();

        $this->delete_all_google_campaigns_and_meta_data();

        $this->delete_all_scheduled_tasks();

        $this->delete_all_database_tables();

    }

    /**
     * Delete all plugin data and deactivate the plugin
     *
     * This method will:
     * 1. Delete all transients, options, post meta, custom post types, and database tables
     * 2. Deactivate the plugin (both pro and free versions if present)
     *
     * @return array Result array with 'success' (bool) and 'message' (string)
     */
    public function delete_and_deactivate() {

        // Ensure we have access to WordPress plugin functions
        if ( ! function_exists( 'deactivate_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $result = array(
            'success' => true,
            'message' => '',
            'deleted_items' => array(),
        );

        try {
            // Delete all plugin data
            $result['deleted_items']['transients'] = $this->delete_all_transients();
            $result['deleted_items']['options'] = $this->delete_all_options();
            $result['deleted_items']['post_meta'] = $this->delete_all_post_meta();
            $result['deleted_items']['expenses'] = $this->delete_all_expenses_and_meta_data();
            $result['deleted_items']['facebook_campaigns'] = $this->delete_all_facebook_campaigns_and_meta_data();
            $result['deleted_items']['google_campaigns'] = $this->delete_all_google_campaigns_and_meta_data();
            $result['deleted_items']['scheduled_tasks'] = $this->delete_all_scheduled_tasks();
            $result['deleted_items']['database_tables'] = $this->delete_all_database_tables();

            // Determine plugin file paths
            $plugin_files = array();

            // Pro version - construct path from WPD_AI_PATH constant
            if ( defined( 'WPD_AI_PATH' ) ) {
                // WPD_AI_PATH is the plugin directory path, so we need to get the relative path
                $pro_plugin_file = 'wp-davies-alpha-insights/wpd-alpha-insights.php';
                if ( file_exists( WP_PLUGIN_DIR . '/' . $pro_plugin_file ) ) {
                    $plugin_files[] = $pro_plugin_file;
                }
            }

            // Free version (check if it exists)
            $free_plugin_file = 'alpha-insights-sales-report-builder-analytics-for-woocommerce/wpd-alpha-insights.php';
            if ( file_exists( WP_PLUGIN_DIR . '/' . $free_plugin_file ) ) {
                $plugin_files[] = $free_plugin_file;
            }

            // Deactivate plugins
            if ( ! empty( $plugin_files ) ) {
                deactivate_plugins( $plugin_files );
                $result['deactivated_plugins'] = $plugin_files;
                $result['message'] = sprintf(
                    __( 'All plugin data deleted and %d plugin(s) deactivated successfully.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                    count( $plugin_files )
                );
            } else {
                $result['message'] = __( 'All plugin data deleted successfully. No active plugins found to deactivate.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
            }

        } catch ( Exception $e ) {
            $result['success'] = false;
            $result['message'] = sprintf(
                __( 'Error during deletion/deactivation: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                $e->getMessage()
            );
        }

        return $result;
    }

    /**
     * Get count of transients
     *
     * @return int
     */
    private function get_transients_count() {
        $transient_keys = $this->get_all_transient_keys();
        if ( empty( $transient_keys ) ) {
            return 0;
        }
        
        // Optimize: Query database directly instead of looping through get_transient()
        // Transients are stored as options with _transient_ prefix (value entry, not timeout)
        global $wpdb;
        $transient_names = array();
        foreach ( $transient_keys as $key ) {
            $transient_names[] = '_transient_' . $key;
        }
        
        $placeholders = implode( ',', array_fill( 0, count( $transient_names ), '%s' ) );
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT option_name) FROM $wpdb->options WHERE option_name IN ($placeholders)",
            $transient_names
        );
        return (int) $wpdb->get_var( $query );
    }

    /**
     * Get transients details (list of all transient keys)
     *
     * @return array Array of transient details
     */
    private function get_transients_details() {
        $transient_keys = $this->get_all_transient_keys();
        if ( empty( $transient_keys ) ) {
            return array();
        }
        
        // Optimize: Query database directly instead of looping through get_transient()
        global $wpdb;
        $details = array();
        $transient_names = array();
        foreach ( $transient_keys as $key ) {
            $transient_names[] = '_transient_' . $key;
        }
        
        $placeholders = implode( ',', array_fill( 0, count( $transient_names ), '%s' ) );
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->prepare(
            "SELECT DISTINCT option_name FROM $wpdb->options WHERE option_name IN ($placeholders)",
            $transient_names
        );
        $existing_transients = $wpdb->get_col( $query );
        
        foreach ( $existing_transients as $transient_option_name ) {
            // Remove _transient_ prefix to get the actual key
            $key = str_replace( '_transient_', '', $transient_option_name );
            
            // Get friendly name from key
            $friendly_name = str_replace( 'wpd_', '', $key );
            $friendly_name = str_replace( '_', ' ', $friendly_name );
            $friendly_name = ucwords( $friendly_name );
            
            $details[] = array(
                'key' => $key,
                'friendly_name' => $friendly_name,
            );
        }
        
        return $details;
    }

    /**
     * Get count of options
     *
     * @return int
     */
    private function get_options_count() {
        $option_keys = $this->get_all_option_keys();
        if ( empty( $option_keys ) ) {
            return 0;
        }
        
        // Optimize: Query database directly instead of looping through get_option()
        global $wpdb;
        $placeholders = implode( ',', array_fill( 0, count( $option_keys ), '%s' ) );
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT option_name) FROM $wpdb->options WHERE option_name IN ($placeholders)",
            $option_keys
        );
        return (int) $wpdb->get_var( $query );
    }

    /**
     * Get options details (list of all option keys)
     *
     * @return array Array of option details
     */
    private function get_options_details() {
        $option_keys = $this->get_all_option_keys();
        if ( empty( $option_keys ) ) {
            return array();
        }
        
        // Optimize: Query database directly instead of looping through get_option()
        global $wpdb;
        $details = array();
        $placeholders = implode( ',', array_fill( 0, count( $option_keys ), '%s' ) );
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->prepare(
            "SELECT option_name FROM $wpdb->options WHERE option_name IN ($placeholders)",
            $option_keys
        );
        $existing_keys = $wpdb->get_col( $query );
        
        foreach ( $existing_keys as $key ) {
            // Get friendly name from key
            $friendly_name = str_replace( 'wpd_ai_', '', $key );
            $friendly_name = str_replace( 'wpd_', '', $friendly_name );
            $friendly_name = str_replace( '_', ' ', $friendly_name );
            $friendly_name = ucwords( $friendly_name );
            
            $details[] = array(
                'key' => $key,
                'friendly_name' => $friendly_name,
            );
        }
        
        return $details;
    }

    /**
     * Get count of post meta entries
     *
     * @return int
     */
    private function get_post_meta_count() {
        $meta_keys = $this->get_all_post_meta_keys();
        if ( empty( $meta_keys ) ) {
            return 0;
        }
        
        global $wpdb;
        $count = 0;
        
        // Optimize: Use single query with WHERE IN instead of looping
        $placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key IN ($placeholders)",
            $meta_keys
        );
        $count += (int) $wpdb->get_var( $query );
        
        // Count from HPOS orders meta table if enabled
        if ( function_exists( 'wpd_is_hpos_enabled' ) && wpd_is_hpos_enabled() ) {
            $orders_meta_table = $wpdb->prefix . 'wc_orders_meta';
            $table_exists = $wpdb->get_var( $wpdb->prepare( 
                "SHOW TABLES LIKE %s", 
                $orders_meta_table 
            ) );
            
            if ( $table_exists === $orders_meta_table ) {
                // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                $query = $wpdb->prepare(
                    "SELECT COUNT(*) FROM $orders_meta_table WHERE meta_key IN ($placeholders)",
                    $meta_keys
                );
                $count += (int) $wpdb->get_var( $query );
            }
        }
        
        return $count;
    }

    /**
     * Get count of expenses
     *
     * @return int
     */
    private function get_expenses_count() {
        $count = wp_count_posts( 'expense' );
        return (int) $count->publish + (int) $count->draft + (int) $count->trash;
    }

    /**
     * Get expenses details (posts and post meta counts)
     *
     * @return array
     */
    private function get_expenses_details() {
        global $wpdb;
        
        // Get post count
        $post_count = $this->get_expenses_count();
        
        // Get post meta count for expenses - Optimize: Single query instead of loop
        $meta_keys = $this->get_all_expense_meta_keys();
        $meta_count = 0;
        
        if ( ! empty( $meta_keys ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
            // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $query = $wpdb->prepare(
                "
                SELECT COUNT(*)
                FROM $wpdb->postmeta pm
                INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
                WHERE p.post_type = 'expense'
                AND pm.meta_key IN ($placeholders)
                ",
                $meta_keys
            );
            $meta_count = (int) $wpdb->get_var( $query );
        }
        
        return array(
            array(
                'label' => __( 'Posts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'count' => $post_count,
            ),
            array(
                'label' => __( 'Post Meta', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'count' => $meta_count,
            ),
        );
    }

    /**
     * Get count of Facebook campaigns
     *
     * @return int
     */
    private function get_facebook_campaigns_count() {
        $count = wp_count_posts( 'facebook_campaign' );
        return (int) $count->publish + (int) $count->draft + (int) $count->trash;
    }

    /**
     * Get Facebook campaigns details (posts and post meta counts)
     *
     * @return array
     */
    private function get_facebook_campaigns_details() {
        global $wpdb;
        
        // Get post count
        $post_count = $this->get_facebook_campaigns_count();
        
        // Get post meta count for Facebook campaigns - Optimize: Single query instead of loop
        $meta_keys = $this->get_all_facebook_campaign_meta_keys();
        $meta_count = 0;
        
        if ( ! empty( $meta_keys ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
            // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $query = $wpdb->prepare(
                "
                SELECT COUNT(*)
                FROM $wpdb->postmeta pm
                INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
                WHERE p.post_type = 'facebook_campaign'
                AND pm.meta_key IN ($placeholders)
                ",
                $meta_keys
            );
            $meta_count = (int) $wpdb->get_var( $query );
        }
        
        return array(
            array(
                'label' => __( 'Posts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'count' => $post_count,
            ),
            array(
                'label' => __( 'Post Meta', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'count' => $meta_count,
            ),
        );
    }

    /**
     * Get count of Google campaigns
     *
     * @return int
     */
    private function get_google_campaigns_count() {
        $count = wp_count_posts( 'google_ad_campaign' );
        return (int) $count->publish + (int) $count->draft + (int) $count->trash;
    }

    /**
     * Get Google campaigns details (posts and post meta counts)
     *
     * @return array
     */
    private function get_google_campaigns_details() {
        global $wpdb;
        
        // Get post count
        $post_count = $this->get_google_campaigns_count();
        
        // Get post meta count for Google campaigns - Optimize: Single query instead of loop
        $meta_keys = $this->get_all_google_campaign_meta_keys();
        $meta_count = 0;
        
        if ( ! empty( $meta_keys ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
            // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $query = $wpdb->prepare(
                "
                SELECT COUNT(*)
                FROM $wpdb->postmeta pm
                INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
                WHERE p.post_type = 'google_ad_campaign'
                AND pm.meta_key IN ($placeholders)
                ",
                $meta_keys
            );
            $meta_count = (int) $wpdb->get_var( $query );
        }
        
        return array(
            array(
                'label' => __( 'Posts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'count' => $post_count,
            ),
            array(
                'label' => __( 'Post Meta', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'count' => $meta_count,
            ),
        );
    }

    /**
     * Get orders meta keys with their counts
     *
     * @return array Array of meta keys with counts
     */
    private function get_orders_meta_keys_details() {
        $meta_keys = $this->get_all_order_meta_keys();
        if ( empty( $meta_keys ) ) {
            return array();
        }
        
        $details = array();
        global $wpdb;
        $counts_map = array();
        
        // Optimize: Get all counts in a single query using GROUP BY
        $placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
        
        // Count from postmeta (for legacy installations)
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->prepare(
            "
            SELECT pm.meta_key, COUNT(*) as count
            FROM $wpdb->postmeta pm
            INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
            WHERE p.post_type = 'shop_order'
            AND pm.meta_key IN ($placeholders)
            GROUP BY pm.meta_key
            ",
            $meta_keys
        );
        $results = $wpdb->get_results( $query, ARRAY_A );
        
        // Build counts map
        foreach ( $results as $row ) {
            $counts_map[ $row['meta_key'] ] = (int) $row['count'];
        }
        
        // Count from HPOS orders meta table if enabled
        if ( function_exists( 'wpd_is_hpos_enabled' ) && wpd_is_hpos_enabled() ) {
            $orders_meta_table = $wpdb->prefix . 'wc_orders_meta';
            $table_exists = $wpdb->get_var( $wpdb->prepare( 
                "SHOW TABLES LIKE %s", 
                $orders_meta_table 
            ) );
            
            if ( $table_exists === $orders_meta_table ) {
                // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                $query = $wpdb->prepare(
                    "SELECT meta_key, COUNT(*) as count FROM $orders_meta_table WHERE meta_key IN ($placeholders) GROUP BY meta_key",
                    $meta_keys
                );
                $hpos_results = $wpdb->get_results( $query, ARRAY_A );
                
                // Add HPOS counts to existing counts
                foreach ( $hpos_results as $row ) {
                    $key = $row['meta_key'];
                    if ( isset( $counts_map[ $key ] ) ) {
                        $counts_map[ $key ] += (int) $row['count'];
                    } else {
                        $counts_map[ $key ] = (int) $row['count'];
                    }
                }
            }
        }
        
        // Build details array
        foreach ( $meta_keys as $meta_key ) {
            $count = isset( $counts_map[ $meta_key ] ) ? $counts_map[ $meta_key ] : 0;
            
            if ( $count > 0 ) {
                // Get friendly name from meta key
                $friendly_name = str_replace( '_wpd_ai_', '', $meta_key );
                $friendly_name = str_replace( '_wpd_', '', $friendly_name );
                $friendly_name = str_replace( '_', ' ', $friendly_name );
                $friendly_name = ucwords( $friendly_name );
                
                $details[] = array(
                    'meta_key' => $meta_key,
                    'friendly_name' => $friendly_name,
                    'count' => $count,
                );
            }
        }
        
        return $details;
    }

    /**
     * Get total count of order meta entries
     *
     * @return int
     */
    private function get_orders_meta_count() {
        $details = $this->get_orders_meta_keys_details();
        $total = 0;
        foreach ( $details as $detail ) {
            $total += $detail['count'];
        }
        return $total;
    }

    /**
     * Get products meta keys with their counts
     *
     * @return array Array of meta keys with counts
     */
    private function get_products_meta_keys_details() {
        $meta_keys = $this->get_all_product_meta_keys();
        if ( empty( $meta_keys ) ) {
            return array();
        }
        
        $details = array();
        global $wpdb;
        
        // Optimize: Get all counts in a single query using GROUP BY
        $placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $query = $wpdb->prepare(
            "
            SELECT pm.meta_key, COUNT(*) as count
            FROM $wpdb->postmeta pm
            INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
            WHERE p.post_type IN ('product', 'product_variation')
            AND pm.meta_key IN ($placeholders)
            GROUP BY pm.meta_key
            ",
            $meta_keys
        );
        $results = $wpdb->get_results( $query, ARRAY_A );
        
        // Build details array
        foreach ( $results as $row ) {
            $meta_key = $row['meta_key'];
            $count = (int) $row['count'];
            
            if ( $count > 0 ) {
                // Get friendly name from meta key
                $friendly_name = str_replace( '_wpd_ai_', '', $meta_key );
                $friendly_name = str_replace( '_wpd_', '', $friendly_name );
                $friendly_name = str_replace( '_', ' ', $friendly_name );
                $friendly_name = ucwords( $friendly_name );
                
                $details[] = array(
                    'meta_key' => $meta_key,
                    'friendly_name' => $friendly_name,
                    'count' => $count,
                );
            }
        }
        
        return $details;
    }

    /**
     * Get total count of product meta entries
     *
     * @return int
     */
    private function get_products_meta_count() {
        $details = $this->get_products_meta_keys_details();
        $total = 0;
        foreach ( $details as $detail ) {
            $total += $detail['count'];
        }
        return $total;
    }

    /**
     * Get count of scheduled tasks
     *
     * @return int
     */
    private function get_scheduled_tasks_count() {
        $scheduled_tasks = $this->get_all_scheduled_tasks();
        return count( $scheduled_tasks );
    }

    /**
     * Get scheduled tasks details (list of all scheduled tasks)
     *
     * @return array Array of scheduled task details
     */
    private function get_scheduled_tasks_details() {
        $scheduled_tasks = $this->get_all_scheduled_tasks();
        $details = array();
        
        foreach ( $scheduled_tasks as $task ) {
            // Get friendly name from hook
            $friendly_name = isset( $task['hook'] ) ? $task['hook'] : '';
            $friendly_name = str_replace( 'wpd_', '', $friendly_name );
            $friendly_name = str_replace( '_', ' ', $friendly_name );
            $friendly_name = ucwords( $friendly_name );
            
            $details[] = array(
                'hook' => isset( $task['hook'] ) ? $task['hook'] : '',
                'friendly_name' => $friendly_name,
                'action_id' => isset( $task['action_id'] ) ? $task['action_id'] : '',
                'status' => isset( $task['status'] ) ? $task['status'] : '',
                'scheduled_date' => isset( $task['scheduled_date_gmt'] ) ? $task['scheduled_date_gmt'] : '',
            );
        }
        
        return $details;
    }

    /**
     * Get count of database tables that actually exist
     *
     * @return int
     */
    private function get_database_tables_count() {
        $table_names = $this->get_db_table_names();
        $existing_count = 0;
        global $wpdb;
        
        foreach ( $table_names as $table_name ) {
            $table_exists = $wpdb->get_var( $wpdb->prepare( 
                "SHOW TABLES LIKE %s", 
                $table_name 
            ) );
            
            if ( $table_exists === $table_name ) {
                $existing_count++;
            }
        }
        
        return $existing_count;
    }

    /**
     * Get database tables with their details (existence, record count, size)
     *
     * @return array Array of table details
     */
    private function get_database_tables_details() {
        $table_names = $this->get_db_table_names();
        $tables_details = array();
        global $wpdb;
        
        foreach ( $table_names as $table_name ) {
            // Check if table exists
            $table_exists = $wpdb->get_var( $wpdb->prepare( 
                "SHOW TABLES LIKE %s", 
                $table_name 
            ) );
            
            if ( $table_exists !== $table_name ) {
                continue; // Skip non-existent tables
            }
            
            // Get record count - table name is already validated and from our known list
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $record_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table_name`" );
            
            // Get table size in MB
            // Use DB_NAME constant instead of $wpdb->dbname for WordPress.org compliance
            $db_name = defined( 'DB_NAME' ) ? DB_NAME : $wpdb->dbname;
            $size_query = $wpdb->prepare(
                "SELECT 
                    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = %s 
                AND TABLE_NAME = %s",
                $db_name,
                $table_name
            );
            $size_mb = $wpdb->get_var( $size_query );
            $size_mb = $size_mb ? (float) $size_mb : 0;
            
            // Get friendly table name (without prefix)
            $friendly_name = str_replace( $wpdb->prefix, '', $table_name );
            $friendly_name = str_replace( '_', ' ', $friendly_name );
            $friendly_name = ucwords( $friendly_name );
            
            $tables_details[] = array(
                'table_name' => $table_name,
                'friendly_name' => $friendly_name,
                'record_count' => $record_count,
                'size_mb' => $size_mb,
            );
        }
        
        return $tables_details;
    }

    /**
     * Register AJAX actions
     *
     * @return void
     */
    public static function register_ajax_actions() {
        add_action( 'wp_ajax_wpd_get_data_management_counts', array( __CLASS__, 'get_data_management_counts_ajax_handler' ) );
        add_action( 'wp_ajax_wpd_delete_entity', array( __CLASS__, 'delete_entity_ajax_handler' ) );
        add_action( 'wp_ajax_wpd_delete_table', array( __CLASS__, 'delete_table_ajax_handler' ) );
        add_action( 'wp_ajax_wpd_truncate_table', array( __CLASS__, 'truncate_table_ajax_handler' ) );
        add_action( 'wp_ajax_wpd_truncate_all_tables', array( __CLASS__, 'truncate_all_tables_ajax_handler' ) );
        add_action( 'wp_ajax_wpd_delete_meta_key', array( __CLASS__, 'delete_meta_key_ajax_handler' ) );
        add_action( 'wp_ajax_wpd_delete_single_item', array( __CLASS__, 'delete_single_item_ajax_handler' ) );
        add_action( 'wp_ajax_wpd_delete_scheduled_task', array( __CLASS__, 'delete_scheduled_task_ajax_handler' ) );
        add_action( 'wp_ajax_wpd_delete_post_type_meta', array( __CLASS__, 'delete_post_type_meta_ajax_handler' ) );
    }

    /**
     * AJAX handler to get all data management counts and details
     *
     * @return void
     */
    public static function get_data_management_counts_ajax_handler() {
        // Verify AJAX request
        if ( ! function_exists( 'wpd_verify_ajax_request' ) ) {
            require_once( WPD_AI_PATH . 'includes/wpd-ajax.php' );
        }
        
        if ( ! wpd_verify_ajax_request() ) {
            return; // wpd_verify_ajax_request sends JSON error and dies
        }
        
        $data_manager = self::get_instance();
        
        // Get all entity data
        $data = array(
            'transients' => array(
                'count' => $data_manager->get_transients_count(),
                'details' => $data_manager->get_transients_details(),
            ),
            'options' => array(
                'count' => $data_manager->get_options_count(),
                'details' => $data_manager->get_options_details(),
            ),
            'orders' => array(
                'count' => $data_manager->get_orders_meta_count(),
                'details' => $data_manager->get_orders_meta_keys_details(),
            ),
            'products' => array(
                'count' => $data_manager->get_products_meta_count(),
                'details' => $data_manager->get_products_meta_keys_details(),
            ),
            'expenses' => array(
                'count' => $data_manager->get_expenses_count(),
                'details' => $data_manager->get_expenses_details(),
            ),
            'facebook_campaigns' => array(
                'count' => $data_manager->get_facebook_campaigns_count(),
                'details' => $data_manager->get_facebook_campaigns_details(),
            ),
            'google_campaigns' => array(
                'count' => $data_manager->get_google_campaigns_count(),
                'details' => $data_manager->get_google_campaigns_details(),
            ),
            'scheduled_tasks' => array(
                'count' => $data_manager->get_scheduled_tasks_count(),
                'details' => $data_manager->get_scheduled_tasks_details(),
            ),
            'database_tables' => array(
                'count' => $data_manager->get_database_tables_count(),
                'details' => $data_manager->get_database_tables_details(),
            ),
        );
        
        wp_send_json_success( $data );
    }

    /**
     * AJAX handler to delete an entity type
     *
     * @return void
     */
    public static function delete_entity_ajax_handler() {
        // Verify AJAX request
        if ( ! function_exists( 'wpd_verify_ajax_request' ) ) {
            require_once( WPD_AI_PATH . 'includes/wpd-ajax.php' );
        }
        
        if ( ! wpd_verify_ajax_request() ) {
            return;
        }
        
        if ( ! isset( $_POST['entity_type'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Entity type is required.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
            return;
        }
        
        $entity_type = sanitize_text_field( $_POST['entity_type'] );
        $data_manager = self::get_instance();
        $result = false;
        $message = '';
        
        switch ( $entity_type ) {
            case 'transients':
                $data_manager->delete_all_transients();
                $result = true;
                $message = __( 'All transients deleted successfully.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
                break;
            case 'options':
                $data_manager->delete_all_options();
                $result = true;
                $message = __( 'All options deleted successfully.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
                break;
            case 'orders':
                $data_manager->delete_all_order_line_item_data();
                $result = true;
                $message = __( 'All order meta data deleted successfully.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
                break;
            case 'products':
                // Delete product meta - need to get all keys and delete
                $meta_keys = $data_manager->get_all_product_meta_keys();
                foreach ( $meta_keys as $meta_key ) {
                    delete_post_meta_by_key( $meta_key );
                }
                $result = true;
                $message = __( 'All product meta data deleted successfully.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
                break;
            case 'expenses':
                $data_manager->delete_all_expenses_and_meta_data();
                $result = true;
                $message = __( 'All expenses deleted successfully.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
                break;
            case 'facebook_campaigns':
                $data_manager->delete_all_facebook_campaigns_and_meta_data();
                $result = true;
                $message = __( 'All Facebook campaigns deleted successfully.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
                break;
            case 'google_campaigns':
                $data_manager->delete_all_google_campaigns_and_meta_data();
                $result = true;
                $message = __( 'All Google campaigns deleted successfully.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
                break;
            case 'scheduled_tasks':
                $data_manager->delete_all_scheduled_tasks();
                $result = true;
                $message = __( 'All scheduled tasks deleted successfully.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
                break;
            case 'database_tables':
                $data_manager->delete_all_database_tables();
                $result = true;
                $message = __( 'All database tables deleted successfully.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
                break;
            default:
                wp_send_json_error( array( 'message' => __( 'Invalid entity type.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
                return;
        }
        
        if ( $result ) {
            wp_send_json_success( array( 'message' => $message ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to delete entity.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
        }
    }

    /**
     * AJAX handler to delete a database table
     *
     * @return void
     */
    public static function delete_table_ajax_handler() {
        // Verify AJAX request
        if ( ! function_exists( 'wpd_verify_ajax_request' ) ) {
            require_once( WPD_AI_PATH . 'includes/wpd-ajax.php' );
        }
        
        if ( ! wpd_verify_ajax_request() ) {
            return;
        }
        
        if ( ! isset( $_POST['table_name'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Table name is required.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
            return;
        }
        
        $table_name = sanitize_text_field( $_POST['table_name'] );
        $data_manager = self::get_instance();
        
        if ( $data_manager->delete_database_table( $table_name ) ) {
            wp_send_json_success( array( 'message' => __( 'Table deleted successfully.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to delete table.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
        }
    }

    /**
     * AJAX handler to truncate a database table
     *
     * @return void
     */
    public static function truncate_table_ajax_handler() {
        // Verify AJAX request
        if ( ! function_exists( 'wpd_verify_ajax_request' ) ) {
            require_once( WPD_AI_PATH . 'includes/wpd-ajax.php' );
        }
        
        if ( ! wpd_verify_ajax_request() ) {
            return;
        }
        
        if ( ! isset( $_POST['table_name'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Table name is required.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
            return;
        }
        
        $table_name = sanitize_text_field( $_POST['table_name'] );
        $data_manager = self::get_instance();
        
        if ( $data_manager->truncate_database_table( $table_name ) ) {
            wp_send_json_success( array( 'message' => __( 'Table cleared successfully.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to clear table.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
        }
    }

    /**
     * AJAX handler to truncate all database tables
     *
     * @return void
     */
    public static function truncate_all_tables_ajax_handler() {
        // Verify AJAX request
        if ( ! function_exists( 'wpd_verify_ajax_request' ) ) {
            require_once( WPD_AI_PATH . 'includes/wpd-ajax.php' );
        }
        
        if ( ! wpd_verify_ajax_request() ) {
            return;
        }
        
        $data_manager = self::get_instance();
        $truncated_count = $data_manager->truncate_all_database_tables();
        
        if ( $truncated_count > 0 ) {
            wp_send_json_success( array( 
                'message' => sprintf( __( '%d table(s) cleared successfully.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $truncated_count )
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to clear tables.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
        }
    }

    /**
     * AJAX handler to delete a meta key
     *
     * @return void
     */
    public static function delete_meta_key_ajax_handler() {
        // Verify AJAX request
        if ( ! function_exists( 'wpd_verify_ajax_request' ) ) {
            require_once( WPD_AI_PATH . 'includes/wpd-ajax.php' );
        }
        
        if ( ! wpd_verify_ajax_request() ) {
            return;
        }
        
        if ( ! isset( $_POST['entity_type'] ) || ! isset( $_POST['meta_key'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Entity type and meta key are required.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
            return;
        }
        
        $entity_type = sanitize_text_field( $_POST['entity_type'] );
        $meta_key = sanitize_text_field( $_POST['meta_key'] );
        $data_manager = self::get_instance();
        $result = false;
        
        if ( $entity_type === 'orders' ) {
            $result = $data_manager->delete_order_meta_by_key( $meta_key );
        } elseif ( $entity_type === 'products' ) {
            $result = delete_post_meta_by_key( $meta_key );
        }
        
        if ( $result ) {
            wp_send_json_success( array( 'message' => __( 'Meta key deleted successfully.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to delete meta key.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
        }
    }

    /**
     * AJAX handler to delete a single item (transient or option)
     *
     * @return void
     */
    public static function delete_single_item_ajax_handler() {
        // Verify AJAX request
        if ( ! function_exists( 'wpd_verify_ajax_request' ) ) {
            require_once( WPD_AI_PATH . 'includes/wpd-ajax.php' );
        }
        
        if ( ! wpd_verify_ajax_request() ) {
            return;
        }
        
        if ( ! isset( $_POST['entity_type'] ) || ! isset( $_POST['item_key'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Entity type and item key are required.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
            return;
        }
        
        $entity_type = sanitize_text_field( $_POST['entity_type'] );
        $item_key = sanitize_text_field( $_POST['item_key'] );
        $result = false;
        
        if ( $entity_type === 'transients' ) {
            $result = delete_transient( $item_key );
        } elseif ( $entity_type === 'options' ) {
            $result = delete_option( $item_key );
        }
        
        if ( $result ) {
            wp_send_json_success( array( 'message' => __( 'Item deleted successfully.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to delete item.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
        }
    }

    /**
     * AJAX handler to delete a scheduled task
     *
     * @return void
     */
    public static function delete_scheduled_task_ajax_handler() {
        // Verify AJAX request
        if ( ! function_exists( 'wpd_verify_ajax_request' ) ) {
            require_once( WPD_AI_PATH . 'includes/wpd-ajax.php' );
        }
        
        if ( ! wpd_verify_ajax_request() ) {
            return;
        }
        
        if ( ! isset( $_POST['action_id'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Action ID is required.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
            return;
        }
        
        $action_id = absint( $_POST['action_id'] );
        
        if ( function_exists( 'as_delete_action' ) ) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
            $result = as_delete_action( $action_id );
            if ( $result ) {
                wp_send_json_success( array( 'message' => __( 'Scheduled task deleted successfully.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
            } else {
                wp_send_json_error( array( 'message' => __( 'Failed to delete scheduled task.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
            }
        } else {
            wp_send_json_error( array( 'message' => __( 'Action Scheduler is not available.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
        }
    }

    /**
     * AJAX handler to delete post type meta (expenses, facebook, google)
     *
     * @return void
     */
    public static function delete_post_type_meta_ajax_handler() {
        // Verify AJAX request
        if ( ! function_exists( 'wpd_verify_ajax_request' ) ) {
            require_once( WPD_AI_PATH . 'includes/wpd-ajax.php' );
        }
        
        if ( ! wpd_verify_ajax_request() ) {
            return;
        }
        
        if ( ! isset( $_POST['entity_type'] ) || ! isset( $_POST['meta_type'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Entity type and meta type are required.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
            return;
        }
        
        $entity_type = sanitize_text_field( $_POST['entity_type'] );
        $meta_type = sanitize_text_field( $_POST['meta_type'] );
        $data_manager = self::get_instance();
        $result = false;
        
        if ( $entity_type === 'expenses' && $meta_type === 'posts' ) {
            $data_manager->delete_all_expenses_and_meta_data();
            $result = true;
        } elseif ( $entity_type === 'expenses' && $meta_type === 'post meta' ) {
            // Delete all expense meta
            $meta_keys = $data_manager->get_all_expense_meta_keys();
            foreach ( $meta_keys as $meta_key ) {
                delete_post_meta_by_key( $meta_key );
            }
            $result = true;
        } elseif ( $entity_type === 'facebook_campaigns' && $meta_type === 'posts' ) {
            $data_manager->delete_all_facebook_campaigns_and_meta_data();
            $result = true;
        } elseif ( $entity_type === 'facebook_campaigns' && $meta_type === 'post meta' ) {
            $meta_keys = $data_manager->get_all_facebook_campaign_meta_keys();
            foreach ( $meta_keys as $meta_key ) {
                delete_post_meta_by_key( $meta_key );
            }
            $result = true;
        } elseif ( $entity_type === 'google_campaigns' && $meta_type === 'posts' ) {
            $data_manager->delete_all_google_campaigns_and_meta_data();
            $result = true;
        } elseif ( $entity_type === 'google_campaigns' && $meta_type === 'post meta' ) {
            $meta_keys = $data_manager->get_all_google_campaign_meta_keys();
            foreach ( $meta_keys as $meta_key ) {
                delete_post_meta_by_key( $meta_key );
            }
            $result = true;
        }
        
        if ( $result ) {
            wp_send_json_success( array( 'message' => __( 'Data deleted successfully.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to delete data.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
        }
    }

    /**
     * Output HTML table for managing data entities
     *
     * @return void
     */
    public function render_data_management_table() {
        // Define entities structure without fetching counts (will be loaded via AJAX)
        $entities = array(
            array(
                'name' => __( 'Transients', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'description' => __( 'Cached data stored temporarily', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'entity_type' => 'transients',
                'has_sub_rows' => true,
            ),
            array(
                'name' => __( 'Options', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'description' => __( 'Plugin settings and configuration', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'entity_type' => 'options',
                'has_sub_rows' => true,
            ),
            array(
                'name' => __( 'Orders', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'description' => __( 'Order metadata entries', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'entity_type' => 'orders',
                'has_sub_rows' => true,
            ),
            array(
                'name' => __( 'Products', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'description' => __( 'Product metadata entries', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'entity_type' => 'products',
                'has_sub_rows' => true,
            ),
            array(
                'name' => __( 'Expenses', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'description' => __( 'Expense custom post type entries', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'entity_type' => 'expenses',
                'has_sub_rows' => true,
            ),
            array(
                'name' => __( 'Facebook Campaigns', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'description' => __( 'Facebook advertising campaign data', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'entity_type' => 'facebook_campaigns',
                'has_sub_rows' => true,
            ),
            array(
                'name' => __( 'Google Campaigns', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'description' => __( 'Google Ads campaign data', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'entity_type' => 'google_campaigns',
                'has_sub_rows' => true,
            ),
            array(
                'name' => __( 'Scheduled Tasks', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'description' => __( 'Action Scheduler background tasks', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'entity_type' => 'scheduled_tasks',
                'has_sub_rows' => true,
            ),
            array(
                'name' => __( 'Database Tables', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'description' => __( 'Custom database tables created by the plugin', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'entity_type' => 'database_tables',
                'has_sub_rows' => true,
            ),
        );
        ?>
        <div class="wpd-wrapper">
            <div class="wpd-section-heading"><?php _e( 'Data Management', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
            <div class="wpd-notice wpd-notice-warning" style="background-color: #fff3cd; border-left: 4px solid #ffb900; padding: 12px; margin: 15px 0;">
                <p style="margin: 0;">
                    <strong><?php _e( 'Important:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></strong> 
                    <?php _e( 'This section only relates to data generated by Alpha Insights. Please ensure you know what you\'re doing, have backups, and have a way to restore access before deleting data. If you are deleting order or product data, you may need to refresh your cache from the general settings page in order to update your reports.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
                </p>
            </div>
        </div>
        <div class="wpd-wrapper">
            <table class="wpd-table fixed widefat">
                <thead>
                    <tr>
                        <th style="width: 30%;"><?php _e( 'Entity Type', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
                        <th style="width: 40%;"><?php _e( 'Description', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
                        <th style="width: 15%;"><?php _e( 'Count', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
                        <th style="width: 15%;"><?php _e( 'Actions', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $entities as $entity ) : ?>
                        <tr class="wpd-entity-row<?php echo ( ! empty( $entity['has_sub_rows'] ) ) ? ' wpd-entity-row-expandable' : ''; ?>" data-entity-type="<?php echo esc_attr( $entity['entity_type'] ); ?>">
                            <td>
                                <?php if ( ! empty( $entity['has_sub_rows'] ) ) : ?>
                                    <span class="dashicons dashicons-arrow-down-alt2 wpd-toggle-icon" style="font-size: 16px; vertical-align: middle; margin-right: 5px;"></span>
                                <?php endif; ?>
                                <strong><?php echo esc_html( $entity['name'] ); ?></strong>
                            </td>
                            <td>
                                <span class="wpd-meta"><?php echo esc_html( $entity['description'] ); ?></span>
                            </td>
                            <td>
                                <span class="wpd-statistic wpd-count-<?php echo esc_attr( $entity['entity_type'] ); ?>" data-entity-type="<?php echo esc_attr( $entity['entity_type'] ); ?>">
                                    <span class="spinner is-active" style="float: none; margin: 0;"></span>
                                    <span class="wpd-loading-text" style="display: none;"><?php _e( 'Loading...', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></span>
                                </span>
                            </td>
                            <td>
                                <?php
                                // Get button text based on entity type
                                $button_texts = array(
                                    'transients' => __( 'Delete All Transients', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                                    'options' => __( 'Delete All Options', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                                    'orders' => __( 'Delete Order Metadata', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                                    'products' => __( 'Delete Product Metadata', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                                    'expenses' => __( 'Delete All Posts & Meta', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                                    'facebook_campaigns' => __( 'Delete All Posts & Meta', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                                    'google_campaigns' => __( 'Delete All Posts & Meta', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                                    'scheduled_tasks' => __( 'Delete All Tasks', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                                    'database_tables' => __( 'Delete All Tables', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                                );
                                $button_text = isset( $button_texts[ $entity['entity_type'] ] ) ? $button_texts[ $entity['entity_type'] ] : __( 'Delete', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
                                ?>
                                <button 
                                    type="button" 
                                    class="button button-secondary wpd-delete-entity" 
                                    data-entity-type="<?php echo esc_attr( $entity['entity_type'] ); ?>"
                                    data-entity-name="<?php echo esc_attr( $entity['name'] ); ?>"
                                    disabled
                                    onclick="event.stopPropagation();"
                                >
                                    <?php echo esc_html( $button_text ); ?>
                                </button>
                                <?php if ( $entity['entity_type'] === 'database_tables' ) : ?>
                                    <button 
                                        type="button" 
                                        class="button button-secondary wpd-clear-all-tables" 
                                        disabled
                                        onclick="event.stopPropagation();"
                                        style="margin-left: 5px;"
                                    >
                                        <?php _e( 'Clear All Tables', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ( ! empty( $entity['has_sub_rows'] ) ) : ?>
                            <tr class="wpd-sub-row-container wpd-sub-row-<?php echo esc_attr( $entity['entity_type'] ); ?>" style="display: none;" data-entity-type="<?php echo esc_attr( $entity['entity_type'] ); ?>">
                                <td colspan="4" style="padding: 10px; text-align: center;">
                                    <span class="spinner is-active"></span>
                                    <span class="wpd-loading-text"><?php _e( 'Loading details...', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></span>
                                </td>
                            </tr>
                            <!-- Sub-rows will be populated via AJAX -->
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <style type="text/css">
            .wpd-sub-row {
                background-color: #f9f9f9;
            }
            .wpd-sub-row:hover {
                background-color: #f0f0f0;
            }
            .wpd-entity-row-expandable {
                cursor: pointer;
            }
            .wpd-entity-row-expandable:hover {
                background-color: #f7f7f7;
            }
            .wpd-toggle-icon {
                color: #2271b1;
                transition: transform 0.2s;
            }
            .wpd-entity-row-expandable.expanded .wpd-toggle-icon {
                transform: rotate(180deg);
            }
            .wpd-entity-row-expandable .button {
                cursor: pointer;
            }
            .wpd-sub-row td {
                width: auto;
            }
            .wpd-sub-row td:first-child {
                width: 30%;
            }
            .wpd-sub-row td:nth-child(2) {
                width: 40%;
            }
            .wpd-sub-row td:nth-child(3) {
                width: 15%;
            }
            .wpd-sub-row td:nth-child(4) {
                width: 15%;
            }
        </style>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Load data via AJAX on page load
                var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
                var nonce = '<?php echo esc_js( wp_create_nonce( WPD_AI_AJAX_NONCE_ACTION ) ); ?>';
                var cachedData = null; // Cache the AJAX response
                
                // Fetch all data
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wpd_get_data_management_counts',
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            cachedData = response.data; // Cache the data
                            populateTableData(response.data);
                        } else {
                            showError('<?php echo esc_js( __( 'Failed to load data. Please refresh the page.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                        }
                    },
                    error: function() {
                        showError('<?php echo esc_js( __( 'Error loading data. Please refresh the page.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                    }
                });
                
                function populateTableData(data) {
                    // Update counts for each entity
                    $.each(data, function(entityType, entityData) {
                        var $countCell = $('.wpd-count-' + entityType);
                        var $deleteButton = $('.wpd-delete-entity[data-entity-type="' + entityType + '"]');
                        
                        // Update count
                        $countCell.html('<span class="wpd-statistic-value">' + formatNumber(entityData.count) + '</span>');
                        
                        // Enable/disable delete button
                        if (entityData.count > 0) {
                            $deleteButton.prop('disabled', false);
                        }
                        
                        // Enable/disable clear all tables button for database tables
                        if (entityType === 'database_tables') {
                            var $clearAllButton = $('.wpd-clear-all-tables');
                            if (entityData.count > 0) {
                                $clearAllButton.prop('disabled', false);
                            }
                        }
                    });
                }
                
                function formatNumber(num) {
                    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                }
                
                function showError(message) {
                    $('.wpd-statistic').each(function() {
                        $(this).html('<span style="color: #d63638;">' + message + '</span>');
                    });
                }
                
                // Toggle sub-rows
                $('.wpd-entity-row-expandable').on('click', function(e) {
                    // Don't toggle if clicking the delete button
                    if ($(e.target).closest('.button').length) {
                        return;
                    }
                    
                    var entityType = $(this).data('entity-type');
                    var $subRowContainer = $('.wpd-sub-row-container.wpd-sub-row-' + entityType);
                    var $row = $(this);
                    var $subRows = $subRowContainer.nextAll('.wpd-sub-row-' + entityType);
                    
                    if ($subRows.is(':visible')) {
                        $subRows.slideUp();
                        $row.removeClass('expanded');
                    } else {
                        // Load sub-row data if not already loaded
                        if ($subRows.length === 0) {
                            loadSubRowData(entityType, $subRowContainer);
                            // Wait a moment for rows to be inserted, then show them
                            setTimeout(function() {
                                $subRowContainer.nextAll('.wpd-sub-row-' + entityType).slideDown();
                                $row.addClass('expanded');
                            }, 50);
                        } else {
                            $subRows.slideDown();
                            $row.addClass('expanded');
                        }
                    }
                });
                
                function loadSubRowData(entityType, $container) {
                    // Use cached data if available, otherwise fetch
                    if (cachedData && cachedData[entityType]) {
                        renderSubRows(entityType, cachedData[entityType].details, $container);
                    } else {
                        // Fallback: fetch if cache not available
                        $.ajax({
                            url: ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'wpd_get_data_management_counts',
                                nonce: nonce
                            },
                            success: function(response) {
                                if (response.success && response.data) {
                                    cachedData = response.data; // Cache the data
                                    if (response.data[entityType]) {
                                        renderSubRows(entityType, response.data[entityType].details, $container);
                                    }
                                }
                            }
                        });
                    }
                }
                
                function renderSubRows(entityType, details, $container) {
                    // Remove any existing sub-rows for this entity type
                    $container.nextAll('.wpd-sub-row-' + entityType).remove();
                    
                    // Hide the loading container
                    $container.hide();
                    
                    if (!details || details.length === 0) {
                        var $emptyRow = $('<tr class="wpd-sub-row wpd-sub-row-' + entityType + '"><td colspan="4" style="padding: 10px; text-align: center; color: #666;"><?php echo esc_js( __( 'No items found.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?></td></tr>');
                        $container.after($emptyRow);
                        return;
                    }
                    
                    $.each(details, function(index, item) {
                        var $row = $('<tr class="wpd-sub-row wpd-sub-row-' + entityType + '"></tr>');
                        
                        if (entityType === 'database_tables') {
                            $row.html(
                                '<td style="padding-left: 40px;">' +
                                    '<span class="wpd-meta">' + escapeHtml(item.friendly_name) + '</span><br>' +
                                    '<code style="font-size: 11px; color: #666;">' + escapeHtml(item.table_name) + '</code>' +
                                '</td>' +
                                '<td>' +
                                    '<span class="wpd-meta"><?php echo esc_js( __( 'Records:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?> <strong>' + formatNumber(item.record_count) + '</strong></span><br>' +
                                    '<span class="wpd-meta"><?php echo esc_js( __( 'Size:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?> <strong>' + parseFloat(item.size_mb).toFixed(2) + ' MB</strong></span>' +
                                '</td>' +
                                '<td><span class="wpd-statistic">' + formatNumber(item.record_count) + '</span></td>' +
                                '<td>' +
                                    '<button type="button" class="button button-small wpd-truncate-table" ' +
                                    'data-table-name="' + escapeHtml(item.table_name) + '" ' +
                                    'data-table-friendly="' + escapeHtml(item.friendly_name) + '" ' +
                                    'style="margin-right: 5px;">' +
                                    '<?php echo esc_js( __( 'Clear', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>' +
                                    '</button>' +
                                    '<button type="button" class="button button-small button-link-delete wpd-delete-table" ' +
                                    'data-table-name="' + escapeHtml(item.table_name) + '" ' +
                                    'data-table-friendly="' + escapeHtml(item.friendly_name) + '">' +
                                    '<?php echo esc_js( __( 'Delete', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>' +
                                    '</button>' +
                                '</td>'
                            );
                        } else if (entityType === 'orders' || entityType === 'products') {
                            $row.html(
                                '<td style="padding-left: 40px;">' +
                                    '<strong>' + escapeHtml(item.friendly_name) + '</strong><br>' +
                                    '<code style="font-size: 11px; color: #666;">' + escapeHtml(item.meta_key) + '</code>' +
                                '</td>' +
                                '<td><span class="wpd-meta">' + escapeHtml($('.wpd-entity-row[data-entity-type="' + entityType + '"]').find('strong').text()) + ' <?php echo esc_js( __( 'meta key', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?></span></td>' +
                                '<td><span class="wpd-statistic">' + formatNumber(item.count) + '</span></td>' +
                                '<td>' +
                                    '<button type="button" class="button button-small button-link-delete wpd-delete-meta-key" ' +
                                    'data-entity-type="' + entityType + '" ' +
                                    'data-meta-key="' + escapeHtml(item.meta_key) + '" ' +
                                    'data-meta-friendly="' + escapeHtml(item.friendly_name) + '">' +
                                    '<?php echo esc_js( __( 'Delete', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>' +
                                    '</button>' +
                                '</td>'
                            );
                        } else if (entityType === 'transients' || entityType === 'options') {
                            $row.html(
                                '<td style="padding-left: 40px;">' +
                                    '<strong>' + escapeHtml(item.friendly_name) + '</strong><br>' +
                                    '<code style="font-size: 11px; color: #666;">' + escapeHtml(item.key) + '</code>' +
                                '</td>' +
                                '<td><span class="wpd-meta">' + escapeHtml($('.wpd-entity-row[data-entity-type="' + entityType + '"]').find('strong').text()) + ' <?php echo esc_js( __( 'entry', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?></span></td>' +
                                '<td><span class="wpd-statistic">1</span></td>' +
                                '<td>' +
                                    '<button type="button" class="button button-small button-link-delete wpd-delete-single-item" ' +
                                    'data-entity-type="' + entityType + '" ' +
                                    'data-item-key="' + escapeHtml(item.key) + '" ' +
                                    'data-item-friendly="' + escapeHtml(item.friendly_name) + '">' +
                                    '<?php echo esc_js( __( 'Delete', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>' +
                                    '</button>' +
                                '</td>'
                            );
                        } else if (entityType === 'scheduled_tasks') {
                            var scheduledDate = item.scheduled_date ? '<br><?php echo esc_js( __( 'Scheduled:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?> <strong>' + escapeHtml(item.scheduled_date) + '</strong>' : '';
                            $row.html(
                                '<td style="padding-left: 40px;">' +
                                    '<strong>' + escapeHtml(item.friendly_name) + '</strong><br>' +
                                    '<code style="font-size: 11px; color: #666;">' + escapeHtml(item.hook) + '</code>' +
                                '</td>' +
                                '<td>' +
                                    '<span class="wpd-meta"><?php echo esc_js( __( 'Status:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?> <strong>' + escapeHtml(item.status) + '</strong>' + scheduledDate + '</span>' +
                                '</td>' +
                                '<td><span class="wpd-statistic">1</span></td>' +
                                '<td>' +
                                    '<button type="button" class="button button-small button-link-delete wpd-delete-scheduled-task" ' +
                                    'data-action-id="' + escapeHtml(item.action_id) + '" ' +
                                    'data-hook="' + escapeHtml(item.hook) + '" ' +
                                    'data-hook-friendly="' + escapeHtml(item.friendly_name) + '">' +
                                    '<?php echo esc_js( __( 'Delete', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>' +
                                    '</button>' +
                                '</td>'
                            );
                        } else {
                            // Post type sub-rows (expenses, facebook_campaigns, google_campaigns)
                            $row.html(
                                '<td style="padding-left: 40px;"><strong>' + escapeHtml(item.label) + '</strong></td>' +
                                '<td><span class="wpd-meta">' + escapeHtml($('.wpd-entity-row[data-entity-type="' + entityType + '"]').find('strong').text()) + ' ' + escapeHtml(item.label.toLowerCase()) + '</span></td>' +
                                '<td><span class="wpd-statistic">' + formatNumber(item.count) + '</span></td>' +
                                '<td>' +
                                    '<button type="button" class="button button-small button-link-delete wpd-delete-post-type-meta" ' +
                                    'data-entity-type="' + entityType + '" ' +
                                    'data-meta-type="' + escapeHtml(item.label.toLowerCase()) + '">' +
                                    '<?php echo esc_js( __( 'Delete', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>' +
                                    '</button>' +
                                '</td>'
                            );
                        }
                        
                        // Insert the row after the container (which is the loading placeholder)
                        $container.after($row);
                    });
                }
                
                function escapeHtml(text) {
                    var map = {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    };
                    return text ? text.replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
                }
                
                // Handle delete entity button clicks
                $(document).on('click', '.wpd-delete-entity', function(e) {
                    e.stopPropagation();
                    var $button = $(this);
                    var entityType = $button.data('entity-type');
                    var entityName = $button.data('entity-name');
                    
                    if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete all', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?> ' + entityName + '? <?php echo esc_js( __( 'This action cannot be undone.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>')) {
                        return;
                    }
                    
                    var originalText = $button.text();
                    $button.prop('disabled', true).text('<?php echo esc_js( __( 'Deleting...', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                    
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wpd_delete_entity',
                            entity_type: entityType,
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Reload the page to refresh data
                                location.reload();
                            } else {
                                alert(response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Failed to delete.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                                $button.prop('disabled', false).text(originalText);
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js( __( 'Error occurred. Please try again.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                            $button.prop('disabled', false).text(originalText);
                        }
                    });
                });
                
                // Handle delete table button clicks
                $(document).on('click', '.wpd-delete-table', function(e) {
                    e.stopPropagation();
                    var $button = $(this);
                    var tableName = $button.data('table-name');
                    var tableFriendly = $button.data('table-friendly');
                    
                    if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete the table', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?> "' + tableFriendly + '"? <?php echo esc_js( __( 'This action cannot be undone.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>')) {
                        return;
                    }
                    
                    $button.prop('disabled', true).text('<?php echo esc_js( __( 'Deleting...', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                    
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wpd_delete_table',
                            table_name: tableName,
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Reload the page to refresh data
                                location.reload();
                            } else {
                                alert(response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Failed to delete table.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                                $button.prop('disabled', false).text('<?php echo esc_js( __( 'Delete', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js( __( 'Error occurred. Please try again.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                            $button.prop('disabled', false).text('<?php echo esc_js( __( 'Delete', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                        }
                    });
                });
                
                // Handle truncate table button clicks
                $(document).on('click', '.wpd-truncate-table', function(e) {
                    e.stopPropagation();
                    var $button = $(this);
                    var tableName = $button.data('table-name');
                    var tableFriendly = $button.data('table-friendly');
                    
                    if (!confirm('<?php echo esc_js( __( 'Are you sure you want to clear all data from the table', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?> "' + tableFriendly + '"? <?php echo esc_js( __( 'This action cannot be undone.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>')) {
                        return;
                    }
                    
                    $button.prop('disabled', true).text('<?php echo esc_js( __( 'Clearing...', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                    
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wpd_truncate_table',
                            table_name: tableName,
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Reload the page to refresh data
                                location.reload();
                            } else {
                                alert(response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Failed to clear table.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                                $button.prop('disabled', false).text('<?php echo esc_js( __( 'Clear', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js( __( 'Error occurred. Please try again.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                            $button.prop('disabled', false).text('<?php echo esc_js( __( 'Clear', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                        }
                    });
                });
                
                // Handle delete meta key button clicks
                $(document).on('click', '.wpd-delete-meta-key', function(e) {
                    e.stopPropagation();
                    var $button = $(this);
                    var entityType = $button.data('entity-type');
                    var metaKey = $button.data('meta-key');
                    var metaFriendly = $button.data('meta-friendly');
                    
                    if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete the meta key', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?> "' + metaFriendly + '"? <?php echo esc_js( __( 'This action cannot be undone.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>')) {
                        return;
                    }
                    
                    $button.prop('disabled', true).text('<?php echo esc_js( __( 'Deleting...', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                    
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wpd_delete_meta_key',
                            entity_type: entityType,
                            meta_key: metaKey,
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Reload the page to refresh data
                                location.reload();
                            } else {
                                alert(response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Failed to delete meta key.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                                $button.prop('disabled', false).text('<?php echo esc_js( __( 'Delete', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js( __( 'Error occurred. Please try again.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                            $button.prop('disabled', false).text('<?php echo esc_js( __( 'Delete', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                        }
                    });
                });
                
                // Handle delete single item button clicks
                $(document).on('click', '.wpd-delete-single-item', function(e) {
                    e.stopPropagation();
                    var $button = $(this);
                    var entityType = $button.data('entity-type');
                    var itemKey = $button.data('item-key');
                    var itemFriendly = $button.data('item-friendly');
                    
                    if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?> "' + itemFriendly + '"? <?php echo esc_js( __( 'This action cannot be undone.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>')) {
                        return;
                    }
                    
                    $button.prop('disabled', true).text('<?php echo esc_js( __( 'Deleting...', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                    
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wpd_delete_single_item',
                            entity_type: entityType,
                            item_key: itemKey,
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Reload the page to refresh data
                                location.reload();
                            } else {
                                alert(response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Failed to delete item.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                                $button.prop('disabled', false).text('<?php echo esc_js( __( 'Delete', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js( __( 'Error occurred. Please try again.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                            $button.prop('disabled', false).text('<?php echo esc_js( __( 'Delete', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                        }
                    });
                });
                
                // Handle delete scheduled task button clicks
                $(document).on('click', '.wpd-delete-scheduled-task', function(e) {
                    e.stopPropagation();
                    var $button = $(this);
                    var actionId = $button.data('action-id');
                    var hookFriendly = $button.data('hook-friendly');
                    
                    if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete the scheduled task', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?> "' + hookFriendly + '"? <?php echo esc_js( __( 'This action cannot be undone.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>')) {
                        return;
                    }
                    
                    $button.prop('disabled', true).text('<?php echo esc_js( __( 'Deleting...', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                    
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wpd_delete_scheduled_task',
                            action_id: actionId,
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Reload the page to refresh data
                                location.reload();
                            } else {
                                alert(response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Failed to delete scheduled task.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                                $button.prop('disabled', false).text('<?php echo esc_js( __( 'Delete', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js( __( 'Error occurred. Please try again.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                            $button.prop('disabled', false).text('<?php echo esc_js( __( 'Delete', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                        }
                    });
                });
                
                // Handle delete post type meta button clicks
                $(document).on('click', '.wpd-delete-post-type-meta', function(e) {
                    e.stopPropagation();
                    var $button = $(this);
                    var entityType = $button.data('entity-type');
                    var metaType = $button.data('meta-type');
                    
                    if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete this data?', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?> <?php echo esc_js( __( 'This action cannot be undone.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>')) {
                        return;
                    }
                    
                    $button.prop('disabled', true).text('<?php echo esc_js( __( 'Deleting...', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                    
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wpd_delete_post_type_meta',
                            entity_type: entityType,
                            meta_type: metaType,
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Reload the page to refresh data
                                location.reload();
                            } else {
                                alert(response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Failed to delete data.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                                $button.prop('disabled', false).text('<?php echo esc_js( __( 'Delete', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js( __( 'Error occurred. Please try again.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                            $button.prop('disabled', false).text('<?php echo esc_js( __( 'Delete', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                        }
                    });
                });
                
                // Handle clear all tables button clicks
                $(document).on('click', '.wpd-clear-all-tables', function(e) {
                    e.stopPropagation();
                    var $button = $(this);
                    
                    if (!confirm('<?php echo esc_js( __( 'Are you sure you want to clear all database tables?', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?> <?php echo esc_js( __( 'This will remove all data from all tables but keep the table structure. This action cannot be undone.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>')) {
                        return;
                    }
                    
                    $button.prop('disabled', true).text('<?php echo esc_js( __( 'Clearing...', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                    
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wpd_truncate_all_tables',
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Reload the page to refresh data
                                location.reload();
                            } else {
                                alert(response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Failed to clear tables.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                                $button.prop('disabled', false).text('<?php echo esc_js( __( 'Clear All Tables', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js( __( 'Error occurred. Please try again.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                            $button.prop('disabled', false).text('<?php echo esc_js( __( 'Clear All Tables', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                        }
                    });
                });
            });
        </script>
        <?php
    }

}