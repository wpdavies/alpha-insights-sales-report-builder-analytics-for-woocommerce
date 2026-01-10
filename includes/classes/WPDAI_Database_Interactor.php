<?php
/**
 *
 * Class that interacts with custom Database tables made by WP Davies
 * 
 * Sets up the database to be formatted correctly and also used for adding and modifying our custom tables with data. 
 * Should this also fetch data? Yes. Full CRUD so that we can manage all names in one place.
 * 
 * This class could be called before Alpha Insights is loaded, any dependencies must be loaded, i.e. wpd_functions.
 * 
 * @see https://deliciousbrains.com/managing-custom-tables-wordpress/
 *
 * @package Alpha Insights
 * @version 4.1.4
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

// In case class is loaded before main plugin
require_once( WPD_AI_PATH . 'includes/wpd-functions.php');

class WPDAI_Database_Interactor {

    public $plugin_db_version          = '';
    public $installed_db_version       = '';
    public $product_impressions_table  = '';
    public $session_data_table         = '';
    public $events_table               = '';
    public $order_calculations_table   = '';
    public $table_definitions          = array();

    /** 
     *
     *   Init
     * 
     **/
    public function __construct() {

        $this->define_props();

    }

    /**
     *
     *  Setup stored props
     *
     */
    public function define_props() {

        global $wpdb;

        $this->plugin_db_version            = WPD_AI_DB_VERSION;
        $this->installed_db_version         = get_option( 'wpd_ai_db_version' );
        $this->session_data_table           = $wpdb->prefix . 'wpd_ai_session_data';
        $this->events_table                 = $wpdb->prefix . 'wpd_ai_woocommerce_events';
        $this->product_impressions_table    = $wpdb->prefix . 'wpd_ai_product_impressions'; // Deprecated
        $this->order_calculations_table     = $wpdb->prefix . 'wpd_ai_order_calculations';

    }

    /**
     *
     *  Check if a value exists within a Table, Column, Value
     * 
     *  @param string $table Table Name (including prefix)
     *  @param string $column Column Name
     *  @param mixed $value Raw Value to search for
     *  @param string $value_type Optional. Variable Type placeholder (%s, %d, %f). If not provided, will be auto-detected.
     *  @return bool True if value exists, false otherwise
     */
    public function does_value_exist( $table, $column, $value, $value_type = null ) {

        global $wpdb;

        $result = false;

        // Validate table name - must be one of our defined tables (whitelist validation)
        if ( empty( $table ) ) {
            wpdai_write_log( __( 'Cannot check value existence: table name is empty.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'db_error' );
            return false;
        }

        // Must be set to a defined table
        if ( ! in_array( $table, array_values( get_object_vars( $this ) ), true ) ) {
            wpdai_write_log( sprintf( __( 'Cannot check value existence: table name %s does not match a defined table within the WPD Database Interactor.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $table ) ), 'db_error' );
            return false;
        }

        // Validate and sanitize column name
        if ( empty( $column ) || ! is_string( $column ) ) {
            wpdai_write_log( __( 'Cannot check value existence: column name is empty or invalid.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'db_error' );
            return false;
        }

        // Sanitize column name - only allow valid identifier characters
        $column = sanitize_key( $column );
        if ( empty( $column ) ) {
            wpdai_write_log( __( 'Cannot check value existence: column name contains invalid characters.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'db_error' );
            return false;
        }

        // Determine placeholder format if not provided
        if ( is_null( $value_type ) ) {
            $value_type = $this->determine_placeholder_format( $value );
        } else {
            // Validate value_type placeholder - must be one of the allowed types
            $allowed_placeholders = array( '%s', '%d', '%f' );
            if ( ! in_array( $value_type, $allowed_placeholders, true ) ) {
                wpdai_write_log( sprintf( __( 'Cannot check value existence: invalid placeholder type %s. Must be one of: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $value_type ), implode( ', ', $allowed_placeholders ) ), 'db_error' );
                return false;
            }
        }

        // Table and column names are already validated against whitelist above
        // For WordPress.org compliance: identifiers are validated, not escaped with esc_sql()
        // Table name is validated against whitelist (line 86), column name is sanitized (line 98)
        // Since identifiers are validated, we can safely use them directly in the query
        // Build query with proper placeholder based on value type (table/column names are validated)
        // Use explicit placeholder types as required by WordPress.org standards
        switch ( $value_type ) {
            case '%d':
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and column names are validated against whitelist.
                $sql_query = $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = %d", absint( $value ) );
                break;
            case '%f':
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and column names are validated against whitelist.
                $sql_query = $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = %f", floatval( $value ) );
                break;
            case '%s':
            default:
                // Sanitize string value for database query (prepare handles escaping, but we sanitize for safety)
                $value = sanitize_text_field( $value );
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and column names are validated against whitelist.
                $sql_query = $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = %s", $value );
                break;
        }
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above.
        $count = $wpdb->get_var( $sql_query );

        // Check for database errors
        if ( $wpdb->last_error ) {
            wpdai_write_log( sprintf( __( 'Database error while checking value existence in table %s, column %s: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $table ), esc_html( $column ), esc_html( $wpdb->last_error ) ), 'db_error' );
            return false;
        }

        if ( $count > 0 ) {
            $result = true;
        }

        return $result;

    }

    /**
     *
     *  Add New Row of data to a specified table
     *  [IMPORTANT!] Data must be sanitized and organised before being passed into this function, no checks are being done on security.
     * 
     *  @param string $table_name The name of the table, including the prefix
     *  @param array $data The data to be passed into the table
     * 
     *  @return int|false Will return the number of rows added on success or false on failure
     *
     */
    public function add_row( $table_name, $data = array() ) {

        // Table required
        if ( empty( $table_name ) ) {
            
            wpdai_write_log( 'Coudln\'t add row to table as the table name does not exist.', 'db_error' );
            return false;

        }

        // Must be set to a defined table
        if ( ! in_array( $table_name, array_values( get_object_vars( $this ) ) ) ) {

            wpdai_write_log( 'Coudln\'t add data to table as the table name does not match a defined table within the WPD Database Interactor.', 'db_error' );
            return false;

        }

        // Need to pass data in
        if ( empty($data) ) {

            wpdai_write_log( 'Coudln\'t add data to ' . $table_name . ' as no data was passed.', 'db_error' );
            return false;

        }

        global $wpdb;

        // Insert into DB
        $result = $wpdb->insert( $table_name, $data );

        // Something went wrong.
        if ( $wpdb->last_error ) {

            $result = $wpdb->last_error;
            wpdai_write_log( 'Error occured adding data to ' . $table_name, 'db_error' );
            wpdai_write_log( $result, 'db_error' );
            return false;

        }

        // Returns Int|false. The number of rows inserted, or false on error.
        return $result;

    }

    /**
     * 
     *  Will either insert a row or update it if already set (if where is set)
     * 
     **/
    public function insert_update_row( $table_name, $data, $where = null, $data_format = null, $where_format = null ) {

        // Load the DB interface
        global $wpdb;

        // Table required
        if ( empty( $table_name ) ) {
    
            wpdai_write_log( 'Coudln\'t add row to table as the table name does not exist.', 'db_error' );
            return false;

        }

        // Must be set to a defined table
        if ( ! in_array( $table_name, array_values( get_object_vars( $this ) ) ) ) {

            wpdai_write_log( 'Coudln\'t add data to table as the table name does not match a defined table within the WPD Database Interactor.', 'db_error' );
            return false;

        }

        // Need to pass data in
        if ( empty($data) ) {

            wpdai_write_log( 'Coudln\'t add data to ' . $table_name . ' as no data was passed.', 'db_error' );
            return false;

        }

        // Determine Where Format
        if ( is_array($where) && is_null($where_format) ) {

            // Setup array
            $where_format = array();

            // Loop through where conditions
            foreach( $where as $key => $value ) {

                // Determine target placeholder format
                $placeholder_format = $this->determine_placeholder_format($value);

                // Set Where Format
                $where_format[] = $placeholder_format;

            }

        }

        // Determine Data Format
        if ( is_array($data) && is_null($data_format) ) {

            // Setup array
            $data_format = array();

            // Loop through where conditions
            foreach( $data as $key => $value ) {

                // Determine target placeholder format
                $placeholder_format = $this->determine_placeholder_format($value);

                // Set Where Format
                $data_format[] = $placeholder_format;

            }

        }

        // Insert the row if it's null
        if ( is_null($where) ) {

            // Insert Row
            $inserted_rows = $wpdb->insert( $table_name, $data, $data_format  );

            // If error is set
            if ( $wpdb->last_error ) {
                
                $result = $wpdb->last_error;
                wpdai_write_log( 'Error occured inserting table rows in the ' . $table_name . ' table.', 'db_error' );
                wpdai_write_log( $result, 'db_error' );
                return false;
                
            }
            
            // Return the updates rows count
            return $inserted_rows;

        }

        // Update the row
        $updated_rows = $wpdb->replace( $table_name, $data, $data_format );

        if ( $wpdb->last_error ) {

            $result = $wpdb->last_error;
            wpdai_write_log( 'Error occured updating table rows in the ' . $table_name . ' table.', 'db_error' );
            wpdai_write_log( $result, 'db_error' );
            return false;

        }

        // Return the updates rows count
        return $updated_rows;

    }

    /**
     * 
     *  Determine's placeholder format based on the input
     * 
     **/
    private function determine_placeholder_format( $value ) {

        // If it looks like a number
        if ( is_numeric($value) ) {

            // Is int
            if ( is_int($value) ) return '%d';

            // Is some sort of decimal
            if ( is_float($value) ) return '%f';

            // Default to float
            return '%f';

        }

        // Otherwise, must be a string
        return '%s';

    }

    /**
     * 
     *  Convert an array into an in statement format
     *  array(1, 2, 3) => "1, 2, 3"
     * 
     */
    public function convert_array_to_in_statement_int( $array ) {

        return implode( ", ", $array );

    }

    /**
     *
     *  Create tables and data
     *
     */
    public function create_update_tables_columns() {

        global $wpdb;

        // Tables
        $events_table               = $this->events_table;
        $session_data_table         = $this->session_data_table;
        $impressions_table          = $this->product_impressions_table;
        $order_calculations_table   = $this->order_calculations_table;

        // Settings
        $charset_collate            = $wpdb->get_charset_collate();

        wpdai_write_log( 'Updating Alpha Insights Database to the latest version.', 'db_upgrade' );

        // Only install if its the latest version
        // if ( version_compare( $this->plugin_db_version, $this->installed_db_version, "<=" )  ) {
        //     wpdai_write_log( 'You have currently got the latest version ('.$this->installed_db_version.') installed, no need to continue.', 'db_upgrade' );
        //     return true;
        // }

        /**
         *
         *  Create Events Analytics Table
         *  @since WPD AI Version 1.22.0
         *  @since DB Version 1.22.0
         *
         *  @var $data['session_id']        PHP Session ID                      Default  ''
         *  @var $data['ip_address']        IP Address                          Default  0
         *  @var $data['user_id']           User ID                             Default  0
         *  @var $data['page_href']         Current Page Url                    Default ''
         *  @var $data['object_type']       Custom Post Type Name               Default ''
         *  @var $data['object_id']         Wordpress Object ID                 Default 0
         *  @var $data['event_type']        impression | category_page_click | product_page_view | add_to_cart | purchase | refund | add_to_wishlist | anything else...
         *  @var $data['event_quantity']    Event Quantity                      Default 1
         *  @var $data['event_value']       Event Value                         Default 0.00
         *  @var $data['product_id']        Product ID                          Default  0
         *  @var $data['variation_id']      Product ID                          Default  0
         *  @var $data['date_created_gmt']  Date Event Created In GMT Time      Default: current_time('mysql')
         *  @var $data['date_created_gmt']  Any additional data, stored in JSON
         * 
         *  @see // Product /includes/integrations/product-analytics-api.php
         *
         */
        // Validate table name against whitelist
        if ( ! in_array( $events_table, array( $this->events_table, $this->session_data_table, $this->order_calculations_table ), true ) ) {
            wpdai_write_log( sprintf( __( 'Invalid table name for table existence check: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $events_table ) ), 'db_error' );
            return false;
        }
        
        // Use prepare() for table name in SHOW TABLES query (WordPress.org compliance)
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is validated against whitelist and prepared.
        $table_check = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $events_table ) );
        if ( $table_check != $events_table ) {

            $sql = "CREATE TABLE $events_table (
                    ID BIGINT(20) NOT NULL AUTO_INCREMENT,
                    `session_id` VARCHAR(255) DEFAULT '0' NOT NULL,
                    `ip_address` VARCHAR(255) DEFAULT '0' NOT NULL,
                    `user_id` BIGINT(20) DEFAULT '0' NOT NULL,
                    `page_href` TEXT DEFAULT '' NOT NULL,
                    `object_type` VARCHAR(255) DEFAULT '' NOT NULL,
                    `object_id` BIGINT(20) DEFAULT '0' NOT NULL,
                    `event_type` VARCHAR(255) NOT NULL,
                    `event_quantity` BIGINT(20) DEFAULT '1' NOT NULL,
                    `event_value` DECIMAL(19,4) DEFAULT '0' NOT NULL,
                    `product_id` BIGINT(20) DEFAULT '0' NOT NULL,
                    `variation_id` BIGINT(20) DEFAULT '0' NOT NULL,
                    `date_created_gmt` DATETIME NOT NULL,
                    `additional_data` longtext,
                    PRIMARY KEY  (ID)
            ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL statement passed to dbDelta.
            dbDelta($sql);

            // Something went wrong.
            if ( $wpdb->last_error ) {
                $error = $wpdb->last_error;
                $query = $wpdb->last_query;
                wpdai_write_log( 'Error occured creating table: ' . $events_table, 'db_error' );
                wpdai_write_log( $error, 'db_error' );
                wpdai_write_log( $query, 'db_error' );
                return false;
            }

            wpdai_write_log( 'New table created: ' . $events_table . '.', 'db_upgrade' );

        } else {

            wpdai_write_log( 'Table already exists, no need to create: ' . $events_table . '.', 'db_upgrade' );

        }

        /**
         *
         *  Create Session Data Table
         *  @since WPD AI Version 1.22.0
         *  @since DB Version 1.22.0
         *
         *  @var $data['session_id']        PHP Session ID                      Default  ''
         *  @var $data['ip_address']        IP Address                          Default  0
         *  @var $data['landing_page']      User ID                             Default  0
         *  @var $data['referral_url']      User ID                             Default  0
         *  @var $data['user_id']           User ID                             Default  0
         *  @var $data['date_created_gmt']  Date Event Created In GMT Time      Default: current_time('mysql')
         *  @var $data['date_updated_gmt']  Date Event Created In GMT Time      Default: current_time('mysql')
         *  @var $data['device_category']   Custom Post Type Name               Default ''
         *  @var $data['operating_system']  Custom Post Type Name               Default ''
         *  @var $data['browser']           Custom Post Type Name               Default ''
         *  @var $data['device']            Custom Post Type Name               Default ''
         *  @var $data['additional_data']   Custom Post Type Name               Default ''
         * 
         *  @see // Session Data -> /includes/integrations/session-tracking-api.php
         *
         */
        // Validate table name against whitelist
        if ( ! in_array( $session_data_table, array( $this->events_table, $this->session_data_table, $this->order_calculations_table ), true ) ) {
            wpdai_write_log( sprintf( __( 'Invalid table name for table existence check: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $session_data_table ) ), 'db_error' );
            return false;
        }
        
        // Use prepare() for table name in SHOW TABLES query (WordPress.org compliance)
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is validated against whitelist and prepared.
        $table_check = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $session_data_table ) );
        if ( $table_check != $session_data_table ) {

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL statement cannot use prepared statements for table names.
            $sql = "CREATE TABLE $session_data_table (
                    ID BIGINT(20) NOT NULL AUTO_INCREMENT,
                    `session_id` VARCHAR(255) NOT NULL,
                    `ip_address` VARCHAR(255),
                    `landing_page` TEXT,
                    `referral_url` TEXT,
                    `user_id` BIGINT(20) DEFAULT '0' NOT NULL,
                    `date_created_gmt` DATETIME NOT NULL,
                    `date_updated_gmt` DATETIME NOT NULL,
                    `device_category` VARCHAR(255),
                    `operating_system` VARCHAR(255),
                    `browser` VARCHAR(255),
                    `device` VARCHAR(255),
                    `additional_data` longtext,
                    `engaged_session` TINYINT(1) DEFAULT NULL,
                    PRIMARY KEY  (ID)
            ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL statement passed to dbDelta.
            dbDelta($sql);

            // Something went wrong.
            if ( $wpdb->last_error ) {
                $error = $wpdb->last_error;
                $query = $wpdb->last_query;
                wpdai_write_log( 'Error occured creating table: ' . $session_data_table, 'db_error' );
                wpdai_write_log( $error, 'db_error' );
                wpdai_write_log( $query, 'db_error' );
                return false;
            }

            wpdai_write_log( 'New table created: ' . $session_data_table . '.', 'db_upgrade' );

        } else {

            wpdai_write_log( 'Table already exists, no need to create: ' . $session_data_table . '.', 'db_upgrade' );

        }

        /**
         *
         *  Create Order Calculations Table for storing order cache
         * 
         *  @since WPD AI Version 3.3.6
         *  @since DB Version 3.3.6
         *
         *  @var $data['order_id']          The Order ID                      Default  ''
         *  @var $data['ip_address']        IP Address                          Default  0
         *  @var $data['landing_page']      User ID                             Default  0
         * 
         *  @see // Session Data -> /includes/integrations/session-tracking-api.php
         *
         */
        // Validate table name against whitelist
        if ( ! in_array( $order_calculations_table, array( $this->events_table, $this->session_data_table, $this->order_calculations_table ), true ) ) {
            wpdai_write_log( sprintf( __( 'Invalid table name for table existence check: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $order_calculations_table ) ), 'db_error' );
            return false;
        }
        
        // Use prepare() for table name in SHOW TABLES query (WordPress.org compliance)
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is validated against whitelist and prepared.
        $table_check = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $order_calculations_table ) );
        if ( $table_check != $order_calculations_table ) {

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL statement cannot use prepared statements for table names.
            $sql = "CREATE TABLE $order_calculations_table (
                    `order_id` BIGINT(20) NOT NULL,
                    `order_calculation` longtext NOT NULL,
                    `calculation_last_updated_gmt` DATETIME NOT NULL,
                    PRIMARY KEY (order_id)
            ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL statement passed to dbDelta.
            dbDelta($sql);

            // Something went wrong.
            if ( $wpdb->last_error ) {
                $error = $wpdb->last_error;
                $query = $wpdb->last_query;
                wpdai_write_log( 'Error occured creating table: ' . $order_calculations_table, 'db_error' );
                wpdai_write_log( $error, 'db_error' );
                wpdai_write_log( $query, 'db_error' );
                return false;
            }

            wpdai_write_log( 'New table created: ' . $order_calculations_table . '.', 'db_upgrade' );

        } else {

            wpdai_write_log( 'Table already exists, no need to create: ' . $order_calculations_table . '.', 'db_upgrade' );

        }

        // Setup indexes
        // Single-column indexes for common filters
        $this->add_new_index( $events_table, 'date_created_gmt' );
        $this->add_new_index( $events_table, 'event_type' );
        $this->add_new_index( $events_table, 'session_id' );
        $this->add_new_index( $events_table, 'object_id' ); // Added for faster order tracking lookups
        $this->add_new_index( $events_table, 'product_id' ); // Added for product filtering in analytics queries
        
        // Composite index for most common query pattern: date range + event_type + ORDER BY date
        // This optimizes: WHERE date_created_gmt >= X AND event_type IN (...) ORDER BY date_created_gmt
        // More efficient than using separate indexes
        $this->add_composite_index( $events_table, 'idx_date_event', array( 'date_created_gmt', 'event_type' ) );
        
        // Session data table indexes
        $this->add_new_index( $session_data_table, 'session_id' ); // Used in WHERE session_id IN (...)
        $this->add_new_index( $session_data_table, 'date_created_gmt' ); // Used for date range queries
        $this->add_new_index( $session_data_table, 'ip_address' ); // Used in WHERE ip_address IN (...) subqueries
        $this->add_new_index( $session_data_table, 'user_id' ); // Used in WHERE user_id IN (...) subqueries
        $this->add_new_index( $session_data_table, 'device_category' ); // Used in WHERE device_category IN (...) subqueries
        
        $this->add_new_index( $order_calculations_table, 'order_id' );

        // Make any changes to column types
        $this->change_column_type( $session_data_table, 'landing_page', "TEXT" );
        $this->change_column_type( $session_data_table, 'referral_url', "TEXT" );
        $this->change_column_type( $events_table, 'page_href', "TEXT DEFAULT '' NOT NULL" );

        // Add new columns where required
        $this->create_new_column( $session_data_table, 'engaged_session', 'TINYINT(1) DEFAULT NULL' );
        
        // Finally, return response.
        wpdai_write_log( 'Completed Alpha Insights upgrade to Database version ' . $this->plugin_db_version, 'db_upgrade' );

        // Set new db version
        update_option( "wpd_ai_db_version", $this->plugin_db_version );
        
        // Return true response
        return true;

    }

    /**
     * 
     *  Changes a column type
     * 
     **/
    public function change_column_type( $table, $column, $format ) {

        // Fetch global
        global $wpdb;

        // Validate table name against whitelist
        if ( ! in_array( $table, array( $this->events_table, $this->session_data_table, $this->order_calculations_table ), true ) ) {
            wpdai_write_log( sprintf( __( 'Invalid table name for column type change: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $table ) ), 'db_error' );
            return false;
        }

        // Validate and sanitize column name
        $column_original = $column;
        $column = sanitize_key( $column );
        if ( empty( $column ) || $column !== $column_original ) {
            wpdai_write_log( sprintf( __( 'Invalid column name for column type change: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $column_original ) ), 'db_error' );
            return false;
        }

        // Validate format parameter - only allow safe SQL column definition patterns
        $format = preg_replace( '/[^A-Za-z0-9\s\(\)\-\_\.\'\",=]/', '', $format );
        if ( empty( $format ) ) {
            wpdai_write_log( sprintf( __( 'Invalid format for column type change: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $format ) ), 'db_error' );
            return false;
        }

        // Log beginning
        wpdai_write_log( sprintf( __( 'Updating column %s in table %s to the following format: %s.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $column ), esc_html( $table ), esc_html( $format ) ), 'db_upgrade' );

        // For DDL statements, table and column names are validated above
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL statement. Table and column names validated against whitelist.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and column names are validated above.
        $sql_query = "ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$format};";

        // Execute
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL statement passed to query.
        $update_column_format = $wpdb->query( $sql_query );

        // Something went wrong.
        if ( $wpdb->last_error ) {

            $error = $wpdb->last_error;
            $query = $wpdb->last_query;

            wpdai_write_log( 'Error occured updating column ' . $column . ' in ' . $table, 'db_error' );
            wpdai_write_log( $error, 'db_error' );
            wpdai_write_log( $query, 'db_error' );

            return false;

        }

        if ( $update_column_format ) {

            wpdai_write_log( sprintf( __( 'Successfully updated %s in %s to %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $column ), esc_html( $table ), esc_html( $format ) ), 'db_upgrade' );
            return true;

        } else {

            wpdai_write_log( sprintf( __( '%s was not updated, may already be correct.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $column ) ), 'db_upgrade' );
            return true;

        }
    }

    /**
     * 
     *  Add an index to a column if it does not already exist
     * 
     *  @param string $table the name of the table, including the prefix
     *  @param string $column the name of the column
     * 
     *  @return bool true on success, false if there was an issue
     * 
     **/
    public function add_new_index( $table, $column ) {

        wpdai_write_log( 'Adding new index "' . $column . '" to '. $table . '.', 'db_upgrade' );

        global $wpdb;

        // Validate table name against whitelist
        if ( ! in_array( $table, array( $this->events_table, $this->session_data_table, $this->order_calculations_table ), true ) ) {
            wpdai_write_log( sprintf( __( 'Invalid table name for index check: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $table ) ), 'db_error' );
            return false;
        }

        // Validate column name - sanitize to ensure only valid identifier characters
        $column = sanitize_key( $column );
        if ( empty( $column ) ) {
            wpdai_write_log( sprintf( __( 'Invalid column name for index check: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $column ) ), 'db_error' );
            return false;
        }

        // Use prepare() for column name in WHERE clause for WordPress.org compliance
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated against whitelist.
        $sql_query = $wpdb->prepare( "SHOW INDEXES FROM `{$table}` WHERE column_name = %s", $column );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above.
        $index_check = $wpdb->get_results( $sql_query );

        // Something went wrong.
        if ( $wpdb->last_error ) {
            $error = $wpdb->last_error;
            $query = $wpdb->last_query;
            wpdai_write_log( sprintf( __( 'Error occurred checking index %s on table %s: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $column ), esc_html( $table ), esc_html( $error ) ), 'db_error' );
            wpdai_write_log( $query, 'db_error' );
            return false;
        }

        // Index doesn't exist, create the index
        // Check if index_check is empty (no results) rather than checking count
        if ( empty( $index_check ) || ! is_array( $index_check ) ) {

            // For DDL statements, table and column names are validated above
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL statement. Table and column names validated against whitelist.
            $sql_query = $wpdb->prepare( "CREATE INDEX `%s` ON `{$table}` (`%s`)", $column, $column );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL statement passed to query.
            $index_update = $wpdb->query( $sql_query );

            // Something went wrong.
            if ( $wpdb->last_error ) {
                $error = $wpdb->last_error;
                $query = $wpdb->last_query;
                wpdai_write_log( 'Error occured adding index ' . $column . ' to ' . $table, 'db_error' );
                wpdai_write_log( $error, 'db_error' );
                wpdai_write_log( $query, 'db_error' );
                return false;
            }

            wpdai_write_log( 'Added new index "' . $column . '" to '. $table . '.', 'db_upgrade' );

        } else {

            wpdai_write_log( 'Index "' . $column . '" in '. $table . ' already exists.', 'db_upgrade' );

        }

        return true;

    }

    /**
     * 
     *  Add a composite index (multiple columns) if it does not already exist
     * 
     *  @param string $table the name of the table, including the prefix
     *  @param string $index_name the name of the composite index
     *  @param array $columns array of column names for the composite index
     * 
     *  @return bool true on success, false if there was an issue
     * 
     **/
    public function add_composite_index( $table, $index_name, $columns ) {

        if ( ! is_array( $columns ) || empty( $columns ) ) {
            wpdai_write_log( 'Error: add_composite_index requires an array of column names.', 'db_error' );
            return false;
        }

        wpdai_write_log( 'Adding composite index "' . $index_name . '" on columns (' . implode( ', ', $columns ) . ') to ' . $table . '.', 'db_upgrade' );

        global $wpdb;

        // Check if index already exists
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is from trusted source.
        $sql_query = $wpdb->prepare( "SHOW INDEXES FROM $table WHERE Key_name = %s", $index_name );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above.
        $index_check = $wpdb->get_results( $sql_query );

        // Something went wrong.
        if ( $wpdb->last_error ) {
            $error = $wpdb->last_error;
            $query = $wpdb->last_query;
            wpdai_write_log( 'Error checking for composite index ' . $index_name . ' on ' . $table, 'db_error' );
            wpdai_write_log( $error, 'db_error' );
            wpdai_write_log( $query, 'db_error' );
            return false;
        }

        // Index doesn't exist, create it
        if ( empty( $index_check ) ) {

            // Validate and sanitize column names - WordPress.org prefers validation over esc_sql()
            $validated_columns = array();
            foreach ( $columns as $col ) {
                $sanitized_col = sanitize_key( $col );
                if ( ! empty( $sanitized_col ) ) {
                    $validated_columns[] = '`' . str_replace( array( '`', ';' ), '', $sanitized_col ) . '`';
                }
            }
            
            if ( empty( $validated_columns ) ) {
                wpdai_write_log( __( 'Error: No valid column names provided for composite index.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'db_error' );
                return false;
            }
            
            $columns_sql = implode( ', ', $validated_columns );

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL statement cannot use prepared statements for table/index names.
            $sql_query = "CREATE INDEX `$index_name` ON $table ($columns_sql)";
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL statement passed to query.
            $index_update = $wpdb->query( $sql_query );

            // Something went wrong.
            if ( $wpdb->last_error ) {
                $error = $wpdb->last_error;
                $query = $wpdb->last_query;
                wpdai_write_log( 'Error occured adding composite index ' . $index_name . ' to ' . $table, 'db_error' );
                wpdai_write_log( $error, 'db_error' );
                wpdai_write_log( $query, 'db_error' );
                return false;
            }

            wpdai_write_log( 'Added composite index "' . $index_name . '" on columns (' . implode( ', ', $columns ) . ') to ' . $table . '.', 'db_upgrade' );

        } else {

            wpdai_write_log( 'Composite index "' . $index_name . '" in ' . $table . ' already exists.', 'db_upgrade' );

        }

        return true;

    }

    /**
     *
     *  Check if a column exists within a table and adds it if not
     * 
     *  @param string $table_name The name of the table, including the prefix
     *  @param string $column_name The name of the column to add
     *  @param string $settings The column definition (e.g., 'TINYINT(1) DEFAULT NULL')
     * 
     *  @return bool true on success, false on failure
     *
     */
    public function create_new_column( $table_name, $column_name, $settings ) {

        wpdai_write_log( sprintf( __( 'Checking if column "%s" exists in table %s.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $column_name ), esc_html( $table_name ) ), 'db_upgrade' );

        global $wpdb;

        // Validate table name against whitelist
        if ( ! in_array( $table_name, array( $this->events_table, $this->session_data_table, $this->order_calculations_table ), true ) ) {
            wpdai_write_log( sprintf( __( 'Invalid table name for column creation: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $table_name ) ), 'db_error' );
            return false;
        }

        // Validate and sanitize column name
        $column_name_original = $column_name;
        $column_name = sanitize_key( $column_name );
        if ( empty( $column_name ) || $column_name !== $column_name_original ) {
            wpdai_write_log( sprintf( __( 'Invalid column name for column creation: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $column_name_original ) ), 'db_error' );
            return false;
        }

        // Validate settings parameter - only allow safe SQL column definition patterns
        // Remove any potentially dangerous characters but allow valid SQL column definitions
        $settings = preg_replace( '/[^A-Za-z0-9\s\(\)\-\_\.\'\",=]/', '', $settings );
        if ( empty( $settings ) ) {
            wpdai_write_log( sprintf( __( 'Invalid column settings for column creation: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $settings ) ), 'db_error' );
            return false;
        }

        // Check if column exists
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated against whitelist.
        $query = $wpdb->prepare( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = %s AND column_name = %s", $table_name, $column_name );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above.
        $row = $wpdb->get_results( $query );

        if ( empty( $row ) ) {

            wpdai_write_log( sprintf( __( 'Adding new column "%s" to %s with settings: %s.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $column_name ), esc_html( $table_name ), esc_html( $settings ) ), 'db_upgrade' );

            // For DDL statements, table and column names are validated above
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL statement. Table and column names validated against whitelist.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and column names are validated above.
            $wpdb->query( "ALTER TABLE `{$table_name}` ADD `{$column_name}` {$settings}" );

            // Something went wrong.
            if ( $wpdb->last_error ) {
                $error = $wpdb->last_error;
                $query = $wpdb->last_query;
                wpdai_write_log( 'Error occured creating new column ' . $column_name . ' in ' . $table_name, 'db_error' );
                wpdai_write_log( $error, 'db_error' );
                wpdai_write_log( $query, 'db_error' );
                return false;
            }

            wpdai_write_log( 'Successfully added new column "' . $column_name . '" to table ' . $table_name . '.', 'db_upgrade' );

        } else {

            wpdai_write_log( 'Column "' . $column_name . '" already exists in table ' . $table_name . '.', 'db_upgrade' );

        }

        return true;

    }

    /**
     * 
     *  Get all table names set by Alpha Insights
     * 
     *  @return array
     * 
     **/
    public function get_all_table_names() {
        return array( $this->session_data_table, $this->events_table, $this->product_impressions_table, $this->order_calculations_table );
    }

}