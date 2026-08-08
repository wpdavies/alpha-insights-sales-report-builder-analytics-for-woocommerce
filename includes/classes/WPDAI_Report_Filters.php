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
     * @deprecated 5.7.4 Query parameter suggestions load via AJAX (`search_query_parameter_suggestions`).
     * @return array<string, array<int, string>>
     */
    public function get_filter_values_order_query_parameter_key_value_pairs() {
        return array();
    }

    /**
     * Cap query-parameter key suggestion count and values per key for filter pickers (orders + website traffic).
     *
     * @since 5.0.0
     *
     * @param array<string, mixed> $parsed_values Key => list of distinct value strings.
     * @return array<string, array<int, string>>
     */
    private function limit_query_parameter_picker_options( array $parsed_values ) {

        /** @var int */
        $max_keys = (int) apply_filters( 'wpd_ai_report_filters_query_parameter_picker_max_keys', 20 );
        /** @var int */
        $max_vals = (int) apply_filters( 'wpd_ai_report_filters_query_parameter_picker_max_values_per_key', 50 );
        if ( $max_keys < 1 ) {
            $max_keys = 20;
        }
        if ( $max_vals < 1 ) {
            $max_vals = 50;
        }

        ksort( $parsed_values );
        if ( count( $parsed_values ) > $max_keys ) {
            $parsed_values = array_slice( $parsed_values, 0, $max_keys, true );
        }

        foreach ( $parsed_values as $key => $values ) {
            if ( ! is_array( $values ) ) {
                unset( $parsed_values[ $key ] );
                continue;
            }
            $values = array_values( array_unique( array_map( 'strval', $values ) ) );
            sort( $values, SORT_STRING );
            if ( count( $values ) > $max_vals ) {
                $values = array_slice( $values, 0, $max_vals );
            }
            $parsed_values[ $key ] = $values;
        }

        return $parsed_values;
    }

    /**
     * Search query parameter keys or values for report filter autosuggest (AJAX only).
     *
     * @since 5.7.4
     *
     * @param string $source    Data source: orders or website_traffic.
     * @param string $search    Case-insensitive substring match.
     * @param string $param_key When set, returns values for this key only.
     * @return array{keys: string[], values: string[]}
     */
    public function search_query_parameter_suggestions( $source, $search = '', $param_key = '' ) {

        $allowed_sources = array( 'orders', 'website_traffic' );
        if ( ! in_array( $source, $allowed_sources, true ) ) {
            return array(
                'keys'   => array(),
                'values' => array(),
            );
        }

        $search     = sanitize_text_field( (string) $search );
        $param_key  = sanitize_text_field( (string) $param_key );
        $search_lc  = strtolower( $search );

        $cache_key = 'wpd_ai_qp_search_' . md5( $source . '|' . $search . '|' . $param_key );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached && $this->is_transient_enabled && is_array( $cached ) ) {
            return $cached;
        }

        /** @var int */
        $max_batches = (int) apply_filters( 'wpd_ai_report_filters_query_parameter_search_max_batches', 25 );
        /** @var int */
        $max_keys = (int) apply_filters( 'wpd_ai_report_filters_query_parameter_picker_max_keys', 20 );
        /** @var int */
        $max_vals = (int) apply_filters( 'wpd_ai_report_filters_query_parameter_picker_max_values_per_key', 50 );

        if ( $max_batches < 1 ) {
            $max_batches = 25;
        }
        if ( $max_keys < 1 ) {
            $max_keys = 20;
        }
        if ( $max_vals < 1 ) {
            $max_vals = 50;
        }

        $matched_keys   = array();
        $matched_values = array();
        $offset         = 0;
        $batch_count    = 0;
        $has_more       = true;
        $url_like       = $this->build_query_param_search_url_like( $search, $param_key );
        $value_mode     = ( '' !== $param_key );

        while ( $has_more && $batch_count < $max_batches ) {
            $urls = $this->fetch_landing_page_urls_batch( $source, $offset, $url_like );

            if ( empty( $urls ) || ! is_array( $urls ) ) {
                break;
            }

            foreach ( $urls as $url ) {
                $params = wpdai_get_query_params( $url );

                if ( empty( $params ) || ! is_array( $params ) ) {
                    continue;
                }

                if ( $value_mode ) {
                    if ( ! isset( $params[ $param_key ] ) ) {
                        continue;
                    }

                    $raw_values = is_array( $params[ $param_key ] ) ? $params[ $param_key ] : array( $params[ $param_key ] );

                    foreach ( $raw_values as $single_value ) {
                        if ( ! is_string( $single_value ) ) {
                            continue;
                        }

                        $clean = sanitize_text_field( $single_value );
                        if ( '' === $clean ) {
                            continue;
                        }

                        if ( '' !== $search_lc && false === stripos( $clean, $search ) ) {
                            continue;
                        }

                        $matched_values[ $clean ] = true;

                        if ( count( $matched_values ) >= $max_vals ) {
                            break 3;
                        }
                    }
                } else {
                    foreach ( $params as $key => $value ) {
                        $key_clean = sanitize_text_field( (string) $key );
                        if ( '' === $key_clean ) {
                            continue;
                        }

                        if ( '' !== $search_lc && false === stripos( $key_clean, $search ) ) {
                            continue;
                        }

                        $matched_keys[ $key_clean ] = true;

                        if ( count( $matched_keys ) >= $max_keys ) {
                            break 3;
                        }
                    }
                }
            }

            if ( count( $urls ) < $this->batch_size ) {
                $has_more = false;
            }

            $offset += $this->batch_size;
            $batch_count++;
        }

        $results = array(
            'keys'   => array(),
            'values' => array(),
        );

        if ( $value_mode ) {
            $values = array_keys( $matched_values );
            sort( $values, SORT_STRING );
            $results['values'] = $values;
        } else {
            $keys = array_keys( $matched_keys );
            sort( $keys, SORT_STRING );
            $results['keys'] = $keys;
        }

        if ( $this->is_transient_enabled ) {
            set_transient( $cache_key, $results, 900 );
        }

        return $results;
    }

    /**
     * Build a SQL LIKE pattern to narrow landing-page URLs for query-param search.
     *
     * @param string $search    Search substring.
     * @param string $param_key Parameter key when searching values.
     * @return string
     */
    private function build_query_param_search_url_like( $search, $param_key ) {

        global $wpdb;

        $fragments = array( '?' );

        if ( '' !== $param_key ) {
            $fragments[] = $param_key . '=';
        }

        if ( '' !== $search ) {
            $fragments[] = $search;
        }

        $escaped = array_map(
            static function ( $fragment ) use ( $wpdb ) {
                return $wpdb->esc_like( (string) $fragment );
            },
            $fragments
        );

        return '%' . implode( '%', $escaped ) . '%';
    }

    /**
     * Fetch a batch of landing page URLs for query parameter scanning.
     *
     * @param string $source Data source: orders or website_traffic.
     * @param int    $offset Batch offset.
     * @param string $url_like SQL LIKE pattern.
     * @return string[]
     */
    private function fetch_landing_page_urls_batch( $source, $offset, $url_like ) {

        global $wpdb;

        if ( 'orders' === $source ) {
            $meta_key         = '_wpd_ai_landing_page';
            $is_hpos_enabled  = wpdai_is_hpos_enabled();

            if ( $is_hpos_enabled ) {
                $order_meta_table = $wpdb->prefix . 'wc_orders_meta';

                return $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT meta_value
                        FROM {$order_meta_table}
                        WHERE meta_key = %s
                        AND meta_value LIKE %s
                        LIMIT %d OFFSET %d",
                        $meta_key,
                        $url_like,
                        $this->batch_size,
                        $offset
                    )
                );
            }

            return $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT pm.meta_value
                    FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                    WHERE pm.meta_key = %s
                    AND pm.meta_value LIKE %s
                    AND p.post_type = 'shop_order'
                    LIMIT %d OFFSET %d",
                    $meta_key,
                    $url_like,
                    $this->batch_size,
                    $offset
                )
            );
        }

        $wpd_db             = new WPDAI_Database_Interactor();
        $session_data_table = $wpd_db->session_data_table;
        $valid_tables       = $wpd_db->get_all_table_names();

        if ( ! in_array( $session_data_table, $valid_tables, true ) ) {
            wpdai_write_log(
                sprintf(
                    /* translators: %s: database table name */
                    __( 'Invalid table name for query: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                    esc_html( $session_data_table )
                ),
                'db_error'
            );
            return array();
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated against whitelist.
        $session_sql_query = $wpdb->prepare(
            "SELECT DISTINCT landing_page
             FROM `{$session_data_table}`
             WHERE landing_page LIKE %s
             LIMIT %d OFFSET %d",
            $url_like,
            absint( $this->batch_size ),
            absint( $offset )
        );

        $results = $wpdb->get_col( $session_sql_query );

        if ( $wpdb->last_error ) {
            wpdai_write_log( 'Error capturing session data from DB for query parameter search.', 'db_error' );
            wpdai_write_log( $wpdb->last_error, 'db_error' );
            wpdai_write_log( $wpdb->last_query, 'db_error' );
            return array();
        }

        return is_array( $results ) ? $results : array();
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
        $results = get_transient( 'wpd_ai_report_filters_users' );

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
            set_transient( 'wpd_ai_report_filters_users', $results, $this->transient_duration_in_seconds );
        }

        // Store in instance cache
        $this->instance_cache['users'] = $results;

        return $results;
    }

    /**
     * Product picker options for report/dashboard filters (not the full catalog).
     *
     * Returns up to N published products and variations, most recently modified first,
     * so the React picker stays small on large catalogs. Users can still filter by any
     * product ID typed in the UI. N defaults to 100; override with filter
     * `wpd_ai_report_filters_products_picker_limit`.
     *
     * Array structure is array[ $product_id ] = $product_label.
     *
     * Optional: `wpd_ai_report_filters_short_circuit_products_picker` (default false) — when true, returns `array()` without reading cache or DB.
     *
     * @since 4.8.0
     * @return array<int|string,string> Product ID => picker label (title + SKU).
     */
    public function get_filter_values_products() {

        if ( apply_filters( 'wpd_ai_report_filters_short_circuit_products_picker', true ) ) {
            return array();
        }

        // Check instance cache first.
        if ( isset( $this->instance_cache['products'] ) ) {
            return $this->instance_cache['products'];
        }

        // Transient key versioned so we do not serve old "full catalog" payloads.
        $results = get_transient( 'wpd_ai_report_filters_products_picker' );

        if ( $results && $this->is_transient_enabled ) {
            $this->instance_cache['products'] = $results;
            return $results;
        }

        global $wpdb;

        $results      = array();
        $picker_limit = (int) apply_filters( 'wpd_ai_report_filters_products_picker_limit', 100 );
        if ( $picker_limit < 1 ) {
            $picker_limit = 100;
        }

        $query = $wpdb->prepare(
            "SELECT ID, post_title 
			FROM {$wpdb->posts} 
			WHERE post_type IN ('product', 'product_variation')
			AND post_status = 'publish'
			ORDER BY post_modified DESC 
			LIMIT %d",
            $picker_limit
        );

        $products = $wpdb->get_results( $query, ARRAY_A );

        if ( empty( $products ) || ! is_array( $products ) ) {
            $this->instance_cache['products'] = $results;
            return $results;
        }

        $product_ids = array_column( $products, 'ID' );
        $skus        = array();

        if ( ! empty( $product_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );
            $sku_query    = $wpdb->prepare(
                "SELECT post_id, meta_value 
				FROM {$wpdb->postmeta} 
				WHERE meta_key = '_sku' 
				AND post_id IN ($placeholders)",
                ...$product_ids
            );

            $skus_raw = $wpdb->get_results( $sku_query, ARRAY_A );
            foreach ( $skus_raw as $sku_row ) {
                $skus[ (int) $sku_row['post_id'] ] = $sku_row['meta_value'];
            }
        }

        foreach ( $products as $product ) {
            $product_id = isset( $product['ID'] ) ? (int) $product['ID'] : 0;

            if ( empty( $product_id ) ) {
                continue;
            }

            $product_title = isset( $product['post_title'] ) ? html_entity_decode( $product['post_title'], ENT_QUOTES, 'UTF-8' ) : 'Unknown';
            if ( empty( $product_title ) ) {
                $product_title = 'Unknown';
            }

            $product_sku = isset( $skus[ $product_id ] ) ? $skus[ $product_id ] : null;
            if ( ! empty( $product_sku ) ) {
                $product_sku = sanitize_text_field( $product_sku );
                $product_title = $product_title . ' (' . $product_sku . ')';
            }

            $results[ $product_id ] = $product_title;
        }

        if ( ! empty( $results ) && $this->is_transient_enabled ) {
            set_transient( 'wpd_ai_report_filters_products_picker', $results, $this->transient_duration_in_seconds );
        }

        $this->instance_cache['products'] = $results;

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
        $results = get_transient( 'wpd_ai_report_filters_product_categories' );

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
        if ( ! empty($category_array) ) set_transient( 'wpd_ai_report_filters_product_categories', $category_array, $this->transient_duration_in_seconds );

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
        $results = get_transient( 'wpd_ai_report_filters_product_tags' );

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
        if ( ! empty($tag_array) ) set_transient( 'wpd_ai_report_filters_product_tags', $tag_array, $this->transient_duration_in_seconds );

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
        $results = get_transient( 'wpd_ai_report_filters_billing_countries' );

        if ( $results && $this->is_transient_enabled ) {
            return $results;
        }

        $results = WC()->countries->get_countries();

        // Store transient
        if ( empty($results) ) set_transient( 'wpd_ai_report_filters_billing_countries', $results, $this->transient_duration_in_seconds );

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
        $results = get_transient( 'wpd_ai_report_filters_facebook_campaigns' );

        if ( $results && $this->is_transient_enabled ) {
            return $results;
        }

        $results = (function_exists('wpdai_get_all_meta_campaigns')) ? wpdai_get_all_meta_campaigns() : array();

        // Store transient
        if ( ! empty($results) ) set_transient( 'wpd_ai_report_filters_facebook_campaigns', $results, $this->transient_duration_in_seconds );

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
        $results = get_transient( 'wpd_ai_report_filters_google_campaigns' );

        if ( $results && $this->is_transient_enabled ) {
            return $results;
        }

        $results = (function_exists('wpdai_get_all_google_campaigns')) ? wpdai_get_all_google_campaigns() : array();

        // Store transient
        if ( ! empty($results) ) set_transient( 'wpd_ai_report_filters_google_campaigns', $results, $this->transient_duration_in_seconds );

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
        $results = get_transient( 'wpd_ai_report_filters_expense_categories' );

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
        if ( ! empty($category_array) ) set_transient( 'wpd_ai_report_filters_expense_categories', $category_array, $this->transient_duration_in_seconds );

        // Return Results
        return $category_array;

    }

    /**
     * @deprecated 5.7.4 Query parameter suggestions load via AJAX (`search_query_parameter_suggestions`).
     * @return array<string, array<int, string>>
     */
    public function get_filter_values_website_traffic_query_parameter_key_value_pairs() {
        return array();
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
        $results = get_transient( 'wpd_ai_report_filters_website_traffic_events' );

        if ( $results && $this->is_transient_enabled ) {
            return $results;
        }

        global $wpdb;

        // Collect Vars
        $wpd_db = new WPDAI_Database_Interactor();
        $events_table = $wpd_db->events_table;

        // Validate table name against whitelist (WordPress.org compliance - prefer validation over esc_sql)
        $valid_tables = $wpd_db->get_all_table_names();
        if ( ! in_array( $events_table, $valid_tables, true ) ) {
            wpdai_write_log( sprintf( __( 'Invalid table name for query: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $events_table ) ), 'db_error' );
            return array();
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated against whitelist.
        $sql_query = "SELECT DISTINCT event_type FROM `{$events_table}`";

        // Fetch Results
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is validated above.
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
        if ( ! empty($key_values) ) set_transient( 'wpd_ai_report_filters_website_traffic_events', $key_values, $this->transient_duration_in_seconds );

        // Return results
        return $key_values;

    }

}
