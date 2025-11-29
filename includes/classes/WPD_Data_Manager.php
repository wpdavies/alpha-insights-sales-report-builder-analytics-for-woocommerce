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
     * 
     *  Initiate class
     * 
     **/
    private function __construct() {


    }
    
    /**
     * Get all transient keys set by Alpha Insights
     *
     * @return array
     */
    public function get_transient_keys() {

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
        $query = $wpdb->prepare(
            "
            SELECT DISTINCT(option_name)
            FROM $wpdb->options
            WHERE option_name LIKE %s
            ",
            '%' . implode( '%', $dynamic_value_keys_start_with ) . '%'
        );
        $transient_values = $wpdb->get_col( $query );
        $all_values = array_merge( $static_values, array_map( 'trim', $transient_values ) );
        return $all_values;

    }
    
    /**
     * Get all option keys set by Alpha Insights
     *
     * @return array
     */
    public function get_option_keys() {



    }

    /**
     * Get all post meta keys set by Alpha Insights
     *
     * @return array
     */
    public function get_post_meta_keys() {



    }

    /**
     * Get all database table names set by Alpha Insights
     *
     * @return array
     */
    public function get_db_table_names() {



    }

    /**
     * Delete all transients set by Alpha Insights
     *
     * @return void
     */
    public function delete_all_transients() {
        
    }

    /**
     * Delete all options set by Alpha Insights
     *
     * @return void
     */
    public function delete_all_options() {
        
    }

    /**
     * Delete all post meta set by Alpha Insights
     *
     * @return void
     */
    public function delete_all_post_meta() {
        
    }

    /**
     * Delete all database tables set by Alpha Insights
     *
     * @return void
     */
    public function delete_all_database_tables() {

    }

    /**
     * Delete all database tables set by Alpha Insights
     *
     * @return void
     */
    public function delete_database_table( $table_name ) {
        
    }

    /**
     * Truncate all database tables set by Alpha Insights
     * Removes all data from the table
     *
     * @return void
     */
    public function truncate_database_table( $table_name ) {
        
    }

    /**
     * Delete all data set by Alpha Insights
     *
     * @param array $entities_to_delete The entities to delete
     * @return void
     */
    public function delete_all_data( ) {

        $this->delete_all_transients();

        $this->delete_all_options();

        $this->delete_all_post_meta();

        $this->delete_all_database_tables();

    }

}