<?php
/**
 *
 * Report Filters Handler for Alpha Insights
 * Responsible for fetching and storing filter data in transients
 *
 * @package Alpha Insights
 * @since 4.8.0
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

class WPDAI_Report_Filters {

    /**
     *
     * The transient duration in seconds
     *
     */
    private int $transient_duration_in_seconds = 21600; // 6 hours

    /**
     * 
     *  Whether or not to use transients
     * 
     **/
    private bool $is_transient_enabled = true;

    /**
     * 
     *  Batch size for processing large datasets
     *  Used across all methods that fetch large amounts of data
     * 
     *  Default is 2500 for safe operation with 256MB PHP memory limit
     *  Can be increased to 5000-10000 for servers with 512MB+ memory
     * 
     **/
    private int $batch_size = 2500;

    /**
     * 
     *  Instance-level cache to prevent duplicate queries within the same request
     *  Even if transients are disabled, this prevents re-running expensive queries
     * 
     **/
    private array $instance_cache = array();

    /**
     *
     * Constructor
     *
     */
    public function __construct() {

        // Allow filtering of the report filters class settings
        $this->is_transient_enabled = apply_filters( 'wpd_ai_report_filters_is_transient_enabled', $this->is_transient_enabled );
        $this->batch_size = apply_filters( 'wpd_ai_report_filters_batch_size', $this->batch_size );

    }

    /**
     * 
     * 	List of available traffic sources
     * 
     * 	@return array $array An associative array of all traffic sources.
     * 	Array structure is array[$traffic_source] = $traffic_source.
     * 
     **/
    public function get_filter_values_traffic_sources() {

        // Check instance cache first
        if ( isset( $this->instance_cache['traffic_sources'] ) ) {
            return $this->instance_cache['traffic_sources'];
        }

        $traffic_types = WPDAI_Traffic_Type_Detection::available_traffic_types();
        $traffic_types_array = array();
        foreach( $traffic_types as $traffic_type => $traffic_type_name ) {
            $traffic_types_array[$traffic_type_name] = $traffic_type_name;
        }

        // Store in instance cache
        $this->instance_cache['traffic_sources'] = $traffic_types_array;

        return $traffic_types_array;

    }

    /**
     * 
     * 	List of available query parameter values for orders
     *  Optimized for large stores using batched processing
     * 
     * 	@return array $array An associative array of all query parameter values.
     * 	Array structure is array[$query_parameter_value_raw] = $query_parameter_value.
     * 
     **/
    public function get_filter_values_order_query_parameter_key_value_pairs() {

        // Check instance cache first
        if ( isset( $this->instance_cache['order_query_params'] ) ) {
            return $this->instance_cache['order_query_params'];
        }

        // Get results
        $results = get_transient( 'WPDAI_Report_Filters_order_query_parameter_values' );

        if ( $results && $this->is_transient_enabled ) {
            $this->instance_cache['order_query_params'] = $results;
            return $results;
        }

        global $wpdb;

        // Detect HPOS (custom order tables)
        $is_hpos_enabled = wpdai_is_hpos_enabled();
    
        $meta_key = '_wpd_ai_landing_page';
        $parsed_values = array();
        
        // Batch configuration
        $max_batches = 1000; // Safety limit
        $batch_count = 0;
        $offset = 0;
        $has_more = true;
    
        while ( $has_more && $batch_count < $max_batches ) {
            
            if ( $is_hpos_enabled ) {
        
                // HPOS mode — order meta table
                $order_meta_table = $wpdb->prefix . 'wc_orders_meta';
        
                $query = $wpdb->prepare("
                    SELECT meta_value
                    FROM {$order_meta_table}
                    WHERE meta_key = %s
                    AND meta_value LIKE %s
                    LIMIT %d OFFSET %d
                ", $meta_key, '%' . $wpdb->esc_like( '?' ) . '%', $this->batch_size, $offset);
        
                $results = $wpdb->get_col( $query );
        
            } else {
        
                // Legacy posts/postmeta system
                $query = $wpdb->prepare("
                    SELECT pm.meta_value
                    FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                    WHERE pm.meta_key = %s
                    AND pm.meta_value LIKE %s
                    AND p.post_type = 'shop_order'
                    LIMIT %d OFFSET %d
                ", $meta_key, '%' . $wpdb->esc_like( '?' ) . '%', $this->batch_size, $offset);
        
                $results = $wpdb->get_col( $query );
            }
            
            if ( empty( $results ) || ! is_array( $results ) ) {
                $has_more = false;
                break;
            }
        
            // Process this batch
            foreach ( $results as $url ) {
        
                // Extract the query string
                $params = wpdai_get_query_params( $url );

                if ( empty( $params ) ) continue;

                // Loop through params
                foreach ( $params as $key => $value ) {
                    // Handle arrays (when query parameter appears multiple times)
                    $values_to_process = is_array( $value ) ? $value : array( $value );
                    
                    foreach ( $values_to_process as $single_value ) {
                        // Skip if not a string (nested arrays or other types)
                        if ( ! is_string( $single_value ) ) {
                            continue;
                        }
                        
                        $raw = $single_value;
                        $clean = sanitize_text_field( $single_value );
                        if ( wpdai_is_valid_reporting_utm_key_value_pair( $key, $single_value ) ) {
                            if ( ! isset( $parsed_values[ $key ] ) ) $parsed_values[ $key ] = array();
                            $parsed_values[ $key ][$clean] = true;
                        }
                    }
                }
            }
            
            // Check if we got fewer results than batch size (last batch)
            if ( count( $results ) < $this->batch_size ) {
                $has_more = false;
            }
            
            $offset += $this->batch_size;
            $batch_count++;
        }
    
        if ( empty( $parsed_values ) ) {
            return array();
        }

        // Clean up the array
        if ( is_array($parsed_values) && ! empty($parsed_values) ) {
            foreach( $parsed_values as $key => $value ) {
                $parsed_values[ $key ] = array_keys( $value );
            }
        }
    
        // Optionally sort alphabetically
        ksort( $parsed_values );
    
        // Store transient
        if ( ! empty($parsed_values) ) set_transient( 'WPDAI_Report_Filters_order_query_parameter_values', $parsed_values, $this->transient_duration_in_seconds );

        // Store in instance cache
        $this->instance_cache['order_query_params'] = $parsed_values;

        return $parsed_values;

    }

    /**
     * 
     *  List of users in an associative array
     *  Optimized for very large stores using direct SQL with batching
     *  Causes problems for massive user database
     * 
     *  @return array Structure: [user_id] => "First Last (ID)"
     * 
     */
    public function get_filter_values_users() {

        // Check instance cache first
        if ( isset( $this->instance_cache['users'] ) ) {
            return $this->instance_cache['users'];
        }

        // Attempt transient
        $results = get_transient( 'WPDAI_Report_Filters_users' );

        if ( $results && $this->is_transient_enabled ) {
            $this->instance_cache['users'] = $results;
            return $results;
        }

        global $wpdb;
        $results = array();

        // Whether to sort results (can be disabled for very large datasets via filter)
        $should_sort = apply_filters( 'wpd_ai_report_filters_users_should_sort', false );

        // Use cursor-based pagination (ID > last_id) instead of OFFSET for better performance
        // OFFSET becomes slow on large datasets as it has to scan through all previous rows
        $last_id = 0;
        $has_more = true;
        $batch_count = 0;
        $max_batches = 1000; // Safety limit: 1000 batches × batch_size = large max

        while ( $has_more && $batch_count < $max_batches ) {
            // Cursor-based pagination: much faster than OFFSET for large datasets
            // Uses primary key index efficiently
            $query = $wpdb->prepare(
                "SELECT ID, display_name, user_login 
                 FROM {$wpdb->users} 
                 WHERE ID > %d
                 ORDER BY ID ASC 
                 LIMIT %d",
                $last_id,
                $this->batch_size
            );

            $users = $wpdb->get_results( $query, ARRAY_A );

            if ( empty( $users ) || ! is_array( $users ) ) {
                $has_more = false;
                break;
            }

            // Process batch
            foreach ( $users as $user ) {
                $user_id = isset( $user['ID'] ) ? (int) $user['ID'] : 0;
                $display_name = isset( $user['display_name'] ) ? sanitize_text_field( $user['display_name'] ) : '';
                $user_login = isset( $user['user_login'] ) ? sanitize_text_field( $user['user_login'] ) : '';

                // Skip invalid users
                if ( empty( $user_id ) ) {
                    continue;
                }

                // Update cursor for next iteration
                $last_id = $user_id;

                // Build label: "Display Name (ID)"
                // If display_name is empty, fallback to user_login or "User {ID}"
                if ( empty( $display_name ) ) {
                    $display_name = ! empty( $user_login ) ? $user_login : sprintf( 'User %d', $user_id );
                }

                $results[ $user_id ] = $display_name . ' (' . $user_id . ')';
            }

            // Check if we got fewer results than batch size (last batch)
            if ( count( $users ) < $this->batch_size ) {
                $has_more = false;
            }

            $batch_count++;
        }

        // Sort by display name for better UX (can be disabled for very large datasets)
        // Sorting 250k+ items can take 2-5 seconds, so make it optional
        if ( ! empty( $results ) && $should_sort ) {
            asort( $results, SORT_NATURAL | SORT_FLAG_CASE );
        }

        // Cache
        if ( ! empty( $results ) ) {
            set_transient( 'WPDAI_Report_Filters_users', $results, $this->transient_duration_in_seconds );
        }

        // Store in instance cache
        $this->instance_cache['users'] = $results;

        return $results;
    }

    /**
     * 
     * 	List of products, including variations, in an associative array
     *  Optimized for large stores using batched processing
     * 	
     * 	@return array $array An associative array of all products within this database.
     * 	Array structure is array[$product_id] = $product_label
     * 
     **/
    public function get_filter_values_products() {

        // Check instance cache first
        if ( isset( $this->instance_cache['products'] ) ) {
            return $this->instance_cache['products'];
        }

        // Get results
        $results = get_transient( 'WPDAI_Report_Filters_products' );

        if ( $results && $this->is_transient_enabled ) {
            $this->instance_cache['products'] = $results;
            return $results;
        }

        global $wpdb;

        // Default response
        $results = array();

        // Safety limit
        $max_batches = 1000; // 1000 batches × batch_size = large max
        $batch_count = 0;
        $offset = 0;
        $has_more = true;

        while ( $has_more && $batch_count < $max_batches ) {
            
            // Use direct SQL for better performance with large datasets
            $query = $wpdb->prepare(
                "SELECT ID, post_title 
                 FROM {$wpdb->posts} 
                 WHERE post_type IN ('product', 'product_variation')
                 AND post_status = 'publish'
                 ORDER BY ID ASC 
                 LIMIT %d OFFSET %d",
                $this->batch_size,
                $offset
            );

            $products = $wpdb->get_results( $query, ARRAY_A );

            if ( empty( $products ) || ! is_array( $products ) ) {
                $has_more = false;
                break;
            }

            // Get all product IDs for this batch to fetch SKUs efficiently
            $product_ids = array_column( $products, 'ID' );
            
            // Fetch all SKUs for this batch in one query
            if ( ! empty( $product_ids ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );
                $sku_query = $wpdb->prepare(
                    "SELECT post_id, meta_value 
                     FROM {$wpdb->postmeta} 
                     WHERE meta_key = '_sku' 
                     AND post_id IN ($placeholders)",
                    ...$product_ids
                );
                
                $skus_raw = $wpdb->get_results( $sku_query, ARRAY_A );
                
                // Create SKU lookup array
                $skus = array();
                foreach ( $skus_raw as $sku_row ) {
                    $skus[ $sku_row['post_id'] ] = $sku_row['meta_value'];
                }
            }

            // Process batch
            foreach ( $products as $product ) {
                $product_id = isset( $product['ID'] ) ? (int) $product['ID'] : 0;
                
                if ( empty( $product_id ) ) {
                    continue;
                }

                // Get title
                $product_title = isset( $product['post_title'] ) ? html_entity_decode( $product['post_title'] ) : 'Unknown';
                if ( empty( $product_title ) ) {
                    $product_title = 'Unknown';
                }

                // Get SKU from our batch lookup
                $product_sku = isset( $skus[ $product_id ] ) ? $skus[ $product_id ] : 'N/A';
                if ( empty( $product_sku ) ) {
                    $product_sku = 'N/A';
                }

                // Load the resulting array
                $results[ $product_id ] = $product_title . ' (' . $product_sku . ')';
            }

            // Check if we got fewer results than batch size (last batch)
            if ( count( $products ) < $this->batch_size ) {
                $has_more = false;
            }

            $offset += $this->batch_size;
            $batch_count++;
        }

        // Store transient
        if ( ! empty($results) ) set_transient( 'WPDAI_Report_Filters_products', $results, $this->transient_duration_in_seconds );

        // Store in instance cache
        $this->instance_cache['products'] = $results;

        // Return Results
        return $results;

    }

    /**
     * 
     * 	List of available product categories
     * 
     * 	@return array $array An associative array of all product categories.
     * 	Array structure is array[$term_id] = $term_name.
     * 
     **/
    public function get_filter_values_product_categories() {

        // Get results
        $results = get_transient( 'WPDAI_Report_Filters_product_categories' );

        if ( $results && $this->is_transient_enabled ) {
            return $results;
        }

        $categories = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
        ]);
        
        $category_array = [];
        
        if ( ! is_wp_error( $categories ) ) {
            foreach ( $categories as $cat ) {
                $category_array[$cat->term_id] = $cat->name;
            }
        }

        // Store transient
        if ( ! empty($category_array) ) set_transient( 'WPDAI_Report_Filters_product_categories', $category_array, $this->transient_duration_in_seconds );

        // Return Results
        return $category_array;

    }

    /**
     * 
     * 	List of available product tags
     * 
     * 	@return array $array An associative array of all product tags.
     * 	Array structure is array[$term_id] = $term_name.
     * 
     **/
    public function get_filter_values_product_tags() {

        // Get results
        $results = get_transient( 'WPDAI_Report_Filters_product_tags' );

        if ( $results && $this->is_transient_enabled ) {
            return $results;
        }

        $tags = get_terms([
            'taxonomy'   => 'product_tag',
            'hide_empty' => true,
        ]);
        
        $tag_array = [];
        
        if ( ! is_wp_error( $tags ) ) {
            foreach ( $tags as $tag ) {
                $tag_array[$tag->term_id] = $tag->name;
            }
        }

        // Store transient
        if ( ! empty($tag_array) ) set_transient( 'WPDAI_Report_Filters_product_tags', $tag_array, $this->transient_duration_in_seconds );

        // Return Results
        return $tag_array;

    }

    /**
     * 
     * 	List of available billing countries
     * 
     * 	@return array $array An associative array of all billing countries.
     * 	Array structure is array[$billing_country] = $billing_country.
     * 
     **/
    public function get_filter_values_billing_countries() {

        // Get results
        $results = get_transient( 'WPDAI_Report_Filters_billing_countries' );

        if ( $results && $this->is_transient_enabled ) {
            return $results;
        }

        $results = WC()->countries->get_countries();

        // Store transient
        if ( empty($results) ) set_transient( 'WPDAI_Report_Filters_billing_countries', $results, $this->transient_duration_in_seconds );

        // Return Results
        return WC()->countries->get_countries();

    }

    /**
     * 
     * 	List of available facebook campaigns
     * 
     * 	@return array $array An associative array of all facebook campaigns.
     * 	Array structure is array[$facebook_campaign_id] = $facebook_campaign_name.
     * 
     **/
    public function get_filter_values_facebook_campaigns() {

        // Get results
        $results = get_transient( 'WPDAI_Report_Filters_facebook_campaigns' );

        if ( $results && $this->is_transient_enabled ) {
            return $results;
        }

        $results = (function_exists('wpdai_get_all_meta_campaigns')) ? wpdai_get_all_meta_campaigns() : array();

        // Store transient
        if ( ! empty($results) ) set_transient( 'WPDAI_Report_Filters_facebook_campaigns', $results, $this->transient_duration_in_seconds );

        // Return Results
        return $results;

    }

    /**
     * 
     * 	List of available google campaigns
     * 
     * 	@return array $array An associative array of all google campaigns.
     * 	Array structure is array[$google_campaign_id] = $google_campaign_name.
     * 
     **/
    public function get_filter_values_google_campaigns() {

        // Get results
        $results = get_transient( 'WPDAI_Report_Filters_google_campaigns' );

        if ( $results && $this->is_transient_enabled ) {
            return $results;
        }

        $results = (function_exists('wpdai_get_all_google_campaigns')) ? wpdai_get_all_google_campaigns() : array();

        // Store transient
        if ( ! empty($results) ) set_transient( 'WPDAI_Report_Filters_google_campaigns', $results, $this->transient_duration_in_seconds );

        // Return Results
        return $results;

    }

    /**
     * 
     * 	List of available expense categories
     * 
     * 	@return array $array An associative array of all expense categories.
     * 	Array structure is array[$expense_category_id] = $expense_category_name.
     * 
     **/
    public function get_filter_values_expense_categories() {

        // Get results
        $results = get_transient( 'WPDAI_Report_Filters_expense_categories' );

        if ( $results && $this->is_transient_enabled ) {
            return $results;
        }

        $categories = get_terms([
            'taxonomy'   => 'expense_category',
            'hide_empty' => true,
        ]);
        
        $category_array = [];
        
        if ( ! is_wp_error( $categories ) ) {
            foreach ( $categories as $category ) {
                $category_array[$category->term_id] = $category->name;
            }
        }

        // Store transient
        if ( ! empty($category_array) ) set_transient( 'WPDAI_Report_Filters_expense_categories', $category_array, $this->transient_duration_in_seconds );

        // Return Results
        return $category_array;

    }

    /**
     * 
     * 	List of available query parameter values from website traffic
     *  Optimized for large stores using batched processing
     * 
     * 	@return array $array An associative array of all query parameter values.
     * 	Array structure is array[$query_parameter_value_slug] = $query_parameter_value_name.
     * 
     **/
    public function get_filter_values_website_traffic_query_parameter_key_value_pairs() {

        // Check instance cache first
        if ( isset( $this->instance_cache['traffic_query_params'] ) ) {
            return $this->instance_cache['traffic_query_params'];
        }

        // Get results
        $results = get_transient( 'WPDAI_Report_Filters_website_traffic_query_parameter_values' );

        if ( $results && $this->is_transient_enabled ) {
            $this->instance_cache['traffic_query_params'] = $results;
            return $results;
        }

        global $wpdb;

        $wpd_db = new WPDAI_Database_Interactor();
        $session_data_table = $wpd_db->session_data_table;

        // Validate table name for security
        $session_data_table = esc_sql( $session_data_table );
        
        $parsed_values = array();
        
        // Batch configuration
        $max_batches = 1000; // Safety limit
        $batch_count = 0;
        $offset = 0;
        $has_more = true;

        while ( $has_more && $batch_count < $max_batches ) {
            
            // Fetch session data in batches
            $session_sql_query = $wpdb->prepare(
                "SELECT DISTINCT landing_page
                 FROM {$session_data_table}
                 WHERE landing_page LIKE %s
                 LIMIT %d OFFSET %d",
                '%' . $wpdb->esc_like( '?' ) . '%',
                $this->batch_size,
                $offset
            );

            $results = $wpdb->get_col( $session_sql_query );

            if ( $wpdb->last_error ) {
                wpdai_write_log( 'Error capturing session data from DB, dumping the error and query.', 'db_error' );
                wpdai_write_log( $wpdb->last_error, 'db_error' );
                wpdai_write_log( $wpdb->last_query, 'db_error' );
                return false;
            }

            if ( empty( $results ) || ! is_array( $results ) ) {
                $has_more = false;
                break;
            }
        
            // Process this batch
            foreach ( $results as $url ) {
        
                // Extract the query string
                $params = wpdai_get_query_params( $url );

                if ( empty( $params ) ) continue;
        
                // Loop through params
                foreach ( $params as $key => $value ) {
                    // Handle arrays (when query parameter appears multiple times)
                    $values_to_process = is_array( $value ) ? $value : array( $value );
                    
                    foreach ( $values_to_process as $single_value ) {
                        // Skip if not a string (nested arrays or other types)
                        if ( ! is_string( $single_value ) ) {
                            continue;
                        }
                        
                        $raw = $single_value;
                        $clean = sanitize_text_field( $single_value );
                        if ( wpdai_is_valid_reporting_utm_key_value_pair( $key, $single_value ) ) {
                            if ( ! isset( $parsed_values[ $key ] ) ) $parsed_values[ $key ] = array();
                            $parsed_values[ $key ][$clean] = true;
                        }
                    }
                }
            }
            
            // Check if we got fewer results than batch size (last batch)
            if ( count( $results ) < $this->batch_size ) {
                $has_more = false;
            }
            
            $offset += $this->batch_size;
            $batch_count++;
        }
    
        if ( empty( $parsed_values ) ) {
            return array();
        }

        // Clean up the array
        if ( is_array($parsed_values) && ! empty($parsed_values) ) {
            foreach( $parsed_values as $key => $value ) {
                $parsed_values[ $key ] = array_keys( $value );
            }
        }
    
        // Optionally sort alphabetically
        ksort( $parsed_values );

        // Store transient
        if ( ! empty($parsed_values) ) set_transient( 'WPDAI_Report_Filters_website_traffic_query_parameter_values', $parsed_values, $this->transient_duration_in_seconds );

        // Store in instance cache
        $this->instance_cache['traffic_query_params'] = $parsed_values;

        // Return Results
        return $parsed_values;

    }

    /**
     * 
     * 	List of available session events from website traffic
     * 
     * 	@return array $array An associative array of all session events.
     * 	Array structure is array[$session_event_slug] = $session_event_name.
     * 
     **/
    public function get_filter_values_website_traffic_events() {

        // Get results
        $results = get_transient( 'WPDAI_Report_Filters_website_traffic_events' );

        if ( $results && $this->is_transient_enabled ) {
            return $results;
        }

        global $wpdb;

        // Collect Vars
        $wpd_db = new WPDAI_Database_Interactor();
        // WordPress's $wpdb->prepare() doesn't support %i placeholder, so we validate and use direct concatenation
        $events_table = esc_sql( $wpd_db->events_table );
        $sql_query = "SELECT DISTINCT event_type FROM {$events_table}";

        // Fetch Results
        $results = $wpdb->get_col( $sql_query );

        // DB Error
        if ( $wpdb->last_error ) {

            wpdai_write_log( 'Error capturing analytics data from DB, dumping the error and query.', 'db_error' );
            wpdai_write_log( $wpdb->last_error, 'db_error' );
            wpdai_write_log( $wpdb->last_query, 'db_error' );

            // Return empty array
            return array();

        }

        if ( empty($results) ) {

            // Return empty array
            return array();

        }

        // Doesnt work
        $results = array_filter( $results );

        // Manually place an option for standard events
        array_push( $results, 'product_page_view', 'form_submit', 'init_checkout', 'checkout_error', 'log_in', 'log_out', 'page_view', 'product_purchase', 'transaction', 'product_click', 'viewed_cart_page', 'viewed_checkout_page', 'add_to_cart' );

        // Sort alphabetically -> ignoring cases
        usort( $results, 'strnatcasecmp' );

        // Return Results
        $key_values = array();
        foreach( $results as $result ) $key_values[$result] = wpdai_clean_string( $result );

        // Store transient
        if ( ! empty($key_values) ) set_transient( 'WPDAI_Report_Filters_website_traffic_events', $key_values, $this->transient_duration_in_seconds );

        // Return results
        return $key_values;

    }

}
