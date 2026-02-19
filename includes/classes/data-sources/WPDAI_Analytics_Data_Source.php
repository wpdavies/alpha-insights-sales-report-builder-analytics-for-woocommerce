<?php
/**
 * Analytics Data Source
 *
 * Provides analytics entity data for the Alpha Insights data warehouse.
 *
 * @package Alpha Insights
 * @since 5.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * Analytics data source class
 *
 * @since 5.0.0
 */
class WPDAI_Analytics_Data_Source extends WPDAI_Custom_Data_Source_Base {

    /**
     * Entity names this data source provides
     *
     * @since 5.0.0
     * @var array<string>
     */
    protected $entity_names = array( 'analytics' );

    /**
     * Fetch analytics data
     *
     * Get filters via $data_warehouse->get_filter(). Use get_data_by_date_range_container() for date alignment.
     *
     * @since 5.0.0
     * @param WPDAI_Data_Warehouse $data_warehouse The data warehouse instance (required).
     * @return array Single-entity structure: totals, categorized_data, data_table, data_by_date, total_db_records.
     */
    public function fetch_data( WPDAI_Data_Warehouse $data_warehouse ) {

        $data_table_limit               = $data_warehouse->get_data_table_limit('analytics');
        $traffic_type_filter            = $data_warehouse->get_data_filter('website_traffic', 'traffic_source'); // Doesn't exist in DB

        // Settings - Show product variations
        $show_product_variations        = apply_filters( 'wpd_ai_show_product_variations', true );

        // Start prep
        $session_data_table = array();
        $session_summary_container = array(
            'page_views'                    => 0,
            'non_page_view_events'          => 0, // All non-page view events
            'category_page_views'           => 0,
            'product_clicks'                => 0,
            'product_page_views'            => 0,
            'account_created'               => 0,
            'add_to_carts'                  => 0,
            'add_to_cart_value'             => 0,
            'initiate_checkouts'            => 0,
            'transactions'                  => 0,
            'transaction_value'             => 0,
            'checkout_error_count'          => 0,
            'form_submits'                  => 0,
            'product_transaction_value'     => 0,
            'unique_products_purchased'     => 0, // Unique line items
            'total_products_purchased'      => 0, // Quantity of products purchased
        );
        $session_container = array_merge(
            $session_summary_container,
            array(
                'session_id' => '',
                'ip_address' => '',
                'user_id' => '',
                'session_start_in_local' => '',
                'session_end_in_local' => '',
                'session_duration' => 0,
                'engaged_session' => null,
                'landing_page' => '',
                'landing_page_path' => '',
                'landing_page_query_parameters' => array(),
                'landing_page_campaign' => '',
                'referral_url' => '',
                'referral_source' => '',
                'device_category' => '',
                'events' => array()
            )
        );
        $analytics_performance_container = array(
            'session_count'             => array(),
            'user_count'                => array(),
            'views'                     => 0,
            'page_views'                => 0,
            'form_submits'              => 0,
            'account_created'           => 0,
            'total_session_duration'    => 0,
            'average_session_duration'  => 0,
            'add_to_carts'              => 0,
            'add_to_cart_value'         => 0,
            'initiate_checkouts'        => 0,
            'transactions'              => 0,
            'revenue'                   => 0,
            'total_value'               => 0,
            'conversion_rate'           => 0.00,
            'page_views_per_session'    => 0,
            'channel_percent'           => 0,
        );
        $totals = array(
            'total_records'                         => 0,
            'sessions'                              => 0,
            'users'                                 => 0, // (count of unique IP's)
            'page_views'                            => 0,
            'form_submits'                          => 0,
            'sessions_with_form_submit'             => 0,
            'percent_of_sessions_with_form_submit'  => 0.00,
            'non_page_view_events'                  => 0, // All non-page view events
            'session_duration'                      => 0,
            'average_session_duration'              => 0,
            'category_page_views'                   => 0,
            'sessions_with_category_page_views'     => 0,
            'product_clicks'                        => 0,
            'product_page_views'                    => 0,
            'sessions_with_product_page_views'      => 0,
            'add_to_carts'                          => 0,
            'add_to_cart_value'                     => 0,
            'sessions_with_add_to_cart'             => 0,
            'account_created'                       => 0,   
            'initiate_checkouts'                    => 0,
            'sessions_with_initiate_checkout'       => 0,
            'transactions'                          => 0,
            'checkout_error_count'                  => 0,
            'sessions_with_transaction'             => 0,
            'transaction_value'                     => 0,
            'product_transaction_value'             => 0,
            'unique_products_purchased'             => 0, // Unique line items
            'total_products_purchased'              => 0, // Quantity of products purchased
            'sessions_per_day'                          => 0,
            'users_per_day'                             => 0,
            'page_views_per_session'                    => 0,
            'events_per_session'                        => 0,
            'percent_sessions_with_category_view'       => 0.00,
            'percent_sessions_with_product_page_view'   => 0.00,
            'percent_sessions_with_add_to_cart'         => 0.00,
            'percent_sessions_with_initiate_checkout'   => 0.00,
            'conversion_rate'                           => 0.00
        );

        $categorized_data = array(
            'event_summary'                         => array(),
            'campaign_summary'                      => array(),
            'acquisition_summary'                   => array(),
            'product_summary'                       => array(),
            'page_view_summary'                     => array(),
            'landing_page_summary'                  => array(),
            'referral_url_summary'                  => array(),
            'checkout_errors_summary'               => array(),
            'form_submits_by_id_summary'            => array(),
            'device_category_summary'               => array(),
            'conversion_funnel_summary'             => array(
                'sessions' => array(
                    'count' => 0,
                    'percent' => 0.00
                ),
                'category_page_view' => array(
                    'count' => 0,
                    'percent' => 0.00
                ),
                'product_page_views' => array(
                    'count' => 0,
                    'percent' => 0.00
                ),
                'add_to_carts' => array(
                    'count' => 0,
                    'percent' => 0.00
                ),
                'initiate_checkouts' => array(
                    'count' => 0,
                    'percent' => 0.00
                ),
                'transactions_complete' => array(
                    'count' => 0,
                    'percent' => 0.00
                ),
            )
        );

        $temp_counter = array(
            'session_id' => array(),
            'ip_address' => array(),
            'sessions_by_date' => array(),
            'ip_address_by_date' => array()
        );

        $data_by_date = array(

            'sessions_by_date' => $data_warehouse->get_data_by_date_range_container(), // Unique Per Day
            'users_by_date' => $data_warehouse->get_data_by_date_range_container(), // Unique Per Day
            'page_views_by_date' => $data_warehouse->get_data_by_date_range_container(),
            'form_submits_by_date' => $data_warehouse->get_data_by_date_range_container(),
            'events_by_date' => $data_warehouse->get_data_by_date_range_container(),
            'category_page_views_by_date' => $data_warehouse->get_data_by_date_range_container(),
            'product_clicks_by_date' => $data_warehouse->get_data_by_date_range_container(),
            'product_page_views_by_date' => $data_warehouse->get_data_by_date_range_container(),
            'add_to_carts_by_date' => $data_warehouse->get_data_by_date_range_container(),
            'conversion_rate_by_date' => $data_warehouse->get_data_by_date_range_container(),
            'transactions_by_date' => $data_warehouse->get_data_by_date_range_container(),
            'checkout_errors_by_date' => $data_warehouse->get_data_by_date_range_container(),
            'account_created_by_date' => $data_warehouse->get_data_by_date_range_container(),
            'acquisition_channels_by_date' => array( 'no_data_available' => $data_warehouse->get_data_by_date_range_container() ),
            'all_events_by_date' => array( 'no_data_available' => $data_warehouse->get_data_by_date_range_container() ), // Multi Dimensional

        );

        // Check for unique events per session -> store sessions ID and do unique count at the end
        $session_unique_array = array(
            'sessions_with_category_page_view' => array(),
            'sessions_with_product_page_view' => array(),
            'sessions_with_add_to_cart' => array(),
            'sessions_with_initiate_checkout' => array(),
            'sessions_with_transaction' => array(),
            'sessions_with_form_submit' => array(),
        );

        /**
         * 
         *  Query the analytics data from the DB with batching
         * 
         **/
        $limit = apply_filters( 'wpd_ai_analytics_data_fetch_batch_size', 10000 );
        $offset = 0;

        // First, get the total count of records to determine how many batches we need
        $total_count = $this->get_analytics_event_count( $data_warehouse );
        
        if ( $total_count === false ) {
            return false;
        }

        $total_batches = ceil( wpdai_divide($total_count, $limit) );
        $processed_records = 0;

        // Initialize session_data_map outside the loop so it persists across batches
        // This ensures session data from previous batches is preserved
        $session_data_map = array();
        
        while ( $offset < $total_count ) {

            $raw_analytics_data = array();
            // Don't reset session_data_map - merge new data into existing map
            $batch_session_data_map = array();
            $query_result = $this->query_analytics_data( $raw_analytics_data, $batch_session_data_map, $limit, $offset, $data_warehouse );
            
            // Ensure session_data_map is always an array (safety check)
            if ( ! is_array( $session_data_map ) ) {
                $session_data_map = array();
            }
            
            // Ensure batch_session_data_map is always an array (even if query failed)
            if ( ! is_array( $batch_session_data_map ) ) {
                $batch_session_data_map = array();
            }
            
            // Merge batch session data into persistent map (preserve existing, add new)
            // Only merge if we have data to merge
            if ( ! empty( $batch_session_data_map ) ) {
                $session_data_map = array_merge( $session_data_map, $batch_session_data_map );
            }

            /**
             *
             *  Perform all calculations
             *
             */
            foreach( $raw_analytics_data as $event ) {

                // Memory Check
                if ( wpdai_is_memory_usage_greater_than(90) ) {
                    $memory_limit = ini_get('memory_limit');
                    $data_warehouse->set_error(
                        sprintf(
                            /* translators: %s: PHP memory limit */
                            __( 'You\'ve exhausted your memory usage. Increase your PHP memory limit or reduce the date range. Your current PHP memory limit is %s.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                            $memory_limit
                        )
                    );
                    break;
                }

                // Event Data
                $session_id                 = $event['session_id'];
                $event_date_created_gmt     = $event['date_created_gmt'];
                $event_type                 = $event['event_type'] ?? null;
                $event_quantity             = $event['event_quantity'] ?? 1;
                $event_value                = $event['event_value'] ?? 0;
                $product_id                 = $event['product_id'] ?? 0;
                $variation_id               = $event['variation_id'] ?? 0;
                $page_href                  = $event['page_href'] ?? null;
                $object_type                = $event['object_type'] ?? null;
                $additional_data            = (isset($event['additional_data']) && ! empty($event['additional_data'])) ? json_decode( maybe_unserialize($event['additional_data']), true ) : null;

                // Session Data - get from session data map
                // Normalize session_id by trimming whitespace before lookup
                $normalized_session_id      = trim((string)$session_id);
                $session_data               = isset($session_data_map[$normalized_session_id]) ? $session_data_map[$normalized_session_id] : null;
                
                // If session data exists in map, use it; otherwise try to preserve existing data from session_data_table
                // This handles cases where historical sessions might not have session_data records
                $existing_session_data = isset($session_data_table[$session_id]) ? $session_data_table[$session_id] : null;
                
                $user_id                    = $session_data ? $session_data['user_id'] : ($existing_session_data && isset($existing_session_data['user_id']) && $existing_session_data['user_id'] !== '' ? $existing_session_data['user_id'] : null);
                $ip_address                 = $session_data ? $session_data['ip_address'] : ($existing_session_data && isset($existing_session_data['ip_address']) && $existing_session_data['ip_address'] !== '' ? $existing_session_data['ip_address'] : null);
                $landing_page               = $session_data ? $session_data['landing_page'] : ($existing_session_data && isset($existing_session_data['landing_page']) && !empty($existing_session_data['landing_page']) ? $existing_session_data['landing_page'] : null);
                $referral_url               = $session_data ? $session_data['referral_url'] : ($existing_session_data && isset($existing_session_data['referral_url']) && !empty($existing_session_data['referral_url']) ? $existing_session_data['referral_url'] : null);
                // Use GMT dates from session_data if available (more reliable than converting local times)
                $session_date_created_gmt   = $session_data ? $session_data['date_created_gmt'] : null;
                $session_date_updated_gmt   = $session_data ? $session_data['date_updated_gmt'] : null;
                $device_category            = $session_data ? $session_data['device_category'] : ($existing_session_data && isset($existing_session_data['device_category']) && $existing_session_data['device_category'] !== '' ? $existing_session_data['device_category'] : null);
                $engaged_session            = $session_data && isset($session_data['engaged_session']) ? (int) $session_data['engaged_session'] : null;

                // @todo this should likely be done on submission of data
                if ($event_type == 'add_to_cart') $event_value = $event_value * $event_quantity;

                // Variable cleaning
                $session_duration               = $data_warehouse->calculate_difference_in_seconds( $session_date_updated_gmt, $session_date_created_gmt );
                $event_timestamp_in_local       = $data_warehouse->get_date_from_gmt( $event_date_created_gmt ); // Replaced native get_date_from_gmt() with faster version
                $session_date_created_local     = $data_warehouse->get_date_from_gmt( $session_date_created_gmt ); // Replaced native get_date_from_gmt() with faster version
                $session_date_updated_local     = $data_warehouse->get_date_from_gmt( $session_date_updated_gmt ); // Replaced native get_date_from_gmt() with faster version
                $landing_page_url_components    = $data_warehouse->get_url_components( $landing_page );
                $landing_page_path              = $landing_page_url_components['path'];
                $landing_page_query_parameters  = $landing_page_url_components['query_parameters'];
                $session_traffic_source         = $data_warehouse->determine_traffic_source( $referral_url, $landing_page_query_parameters );
                $event_page_url_components      = $data_warehouse->get_url_components( $page_href );
                $event_page_path                = $event_page_url_components['path'];
                $event_formatted_date           = $data_warehouse->reformat_date_to_date_format($event_timestamp_in_local); // Formatted for date date
                (isset($landing_page_query_parameters['utm_campaign'])) ? $utm_campaign = $landing_page_query_parameters['utm_campaign'] : $utm_campaign = null;

                // Data Filtering
                $session_data_table_count = is_array($session_data_table) ? count($session_data_table) : 0;
    
                /**
                 *  Apply Traffic type filtering to sessions
                 *  This data does not currently exist in the DB so needs to be done here
                 **/
                if ( $traffic_type_filter && ! in_array($session_traffic_source, $traffic_type_filter) ) continue;
                
                // Setup session container
                if ( $session_data_table_count < $data_table_limit ) {

                    if ( ! isset($session_data_table[$session_id]) ) $session_data_table[$session_id] = $session_container;

                    // Store Session Meta (flattened structure)
                    // Only update fields if we have actual data (don't overwrite with nulls/empty strings)
                    $session_data_table[$session_id]['session_id'] = $session_id;
                    
                    // Only set these if we have actual values (preserve existing data if session_data is missing)
                    if ( $ip_address !== null && $ip_address !== '' ) {
                        $session_data_table[$session_id]['ip_address'] = $ip_address;
                    }
                    if ( $user_id !== null && $user_id !== '' ) {
                        $session_data_table[$session_id]['user_id'] = $user_id;
                    }
                    if ( $session_date_created_local !== null && $session_date_created_local !== '' ) {
                        $session_data_table[$session_id]['session_start_in_local'] = $session_date_created_local;
                    }
                    if ( $session_date_updated_local !== null && $session_date_updated_local !== '' ) {
                        $session_data_table[$session_id]['session_end_in_local'] = $session_date_updated_local;
                    }
                    if ( $session_duration !== null ) {
                        $session_data_table[$session_id]['session_duration'] = $session_duration;
                    }
                    if ( $landing_page !== null && $landing_page !== '' ) {
                        $session_data_table[$session_id]['landing_page'] = htmlspecialchars_decode( $landing_page );
                    }
                    if ( ! empty($landing_page_path) ) {
                        $session_data_table[$session_id]['landing_page_path'] = $landing_page_path;
                    }
                    if ( ! empty($landing_page_query_parameters) ) {
                        $session_data_table[$session_id]['landing_page_query_parameters'] = $landing_page_query_parameters;
                    }
                    if ( $utm_campaign !== null && $utm_campaign !== '' ) {
                        $session_data_table[$session_id]['landing_page_campaign'] = $utm_campaign;
                    }
                    if ( $referral_url !== null && $referral_url !== '' ) {
                        $session_data_table[$session_id]['referral_url'] = $referral_url;
                    }
                    if ( $session_traffic_source !== null && $session_traffic_source !== '' ) {
                        $session_data_table[$session_id]['referral_source'] = $session_traffic_source;
                    }
                    if ( $device_category !== null && $device_category !== '' ) {
                        $session_data_table[$session_id]['device_category'] = $device_category;
                    }
                    if ( $engaged_session !== null ) {
                        $session_data_table[$session_id]['engaged_session'] = $engaged_session;
                    }
                    $session_data_table[$session_id]['events'][] = $event;

                }

                /**
                 *
                 *  Calculate Totals
                 * 
                 */
                // Data Totals
                $totals['total_records']++;

                // Total Sessions
                if ( ! isset($temp_counter['session_id'][$session_id]) ) {
                    $temp_counter['session_id'][$session_id] = true;
                    $totals['sessions']++;
                    $totals['session_duration'] += $session_duration;
                }

                // Total Users
                if ( ! isset($temp_counter['ip_address'][$ip_address]) ) {
                    $temp_counter['ip_address'][$ip_address] = 1;
                    $totals['users']++;
                }

                // Sessions by date
                if ( ! isset($temp_counter['sessions_by_date'][$event_formatted_date][$session_id]) ) {

                    // Temp Counter for unique sessions by date
                    $temp_counter['sessions_by_date'][$event_formatted_date][$session_id] = 1;

                    // If this array key has not been setup, set it up
                    if ( ! isset($data_by_date['sessions_by_date'][$event_formatted_date]) ) $data_by_date['sessions_by_date'][$event_formatted_date] = 0;

                    // Increment the event count
                    $data_by_date['sessions_by_date'][$event_formatted_date]++;

                    // Acquisition channels by date -> Load the correct data defaults
                    if ( isset($data_by_date['acquisition_channels_by_date']['no_data_available']) ) $data_by_date['acquisition_channels_by_date'] = array(); // If we've still got the initial empty array, rebuild.
                    if ( ! isset($data_by_date['acquisition_channels_by_date'][$session_traffic_source]) ) $data_by_date['acquisition_channels_by_date'][$session_traffic_source] = $data_warehouse->get_data_by_date_range_container();
                    // Acquisitions channels by date -> fill in the data
                    if ( isset($data_by_date['acquisition_channels_by_date'][$session_traffic_source][$event_formatted_date]) ) $data_by_date['acquisition_channels_by_date'][$session_traffic_source][$event_formatted_date]++;

                }

                // Users by date
                if ( ! isset($temp_counter['ip_address_by_date'][$event_formatted_date][$ip_address]) ) {

                    // Temp counter for unique users per date
                    $temp_counter['ip_address_by_date'][$event_formatted_date][$ip_address] = 1;

                    // Setup user count by date if it hasn't been setup
                    if ( ! isset($data_by_date['users_by_date'][$event_formatted_date]) ) $data_by_date['users_by_date'][$event_formatted_date] = 0;

                    // Increment the users by date count
                    $data_by_date['users_by_date'][$event_formatted_date]++;

                }

                /**
                 * 
                 *  Setup Summary Data
                 * 
                 */
                // Setup Traffic Source (Acquisition) Data
                if ( ! isset($categorized_data['acquisition_summary'][$session_traffic_source]) ) $categorized_data['acquisition_summary'][$session_traffic_source] = $analytics_performance_container;
                $categorized_data['acquisition_summary'][$session_traffic_source]['session_count'][$session_id] = 1;
                $categorized_data['acquisition_summary'][$session_traffic_source]['user_count'][$ip_address] = 1;
                // Add session duration to this if it hasn't been already
                if ( ! isset($temp_counter['acquisition_summary_unique_session_counter'][$session_id]) ) {
                    $categorized_data['acquisition_summary'][$session_traffic_source]['total_session_duration'] += $session_duration;
                    $temp_counter['acquisition_summary_unique_session_counter'][$session_id] = 1;
                }

                // Setup Device Category Data
                if ( ! isset($categorized_data['device_category_summary'][$device_category]) ) $categorized_data['device_category_summary'][$device_category] = $analytics_performance_container;
                $categorized_data['device_category_summary'][$device_category]['session_count'][$session_id] = 1;
                $categorized_data['device_category_summary'][$device_category]['user_count'][$ip_address] = 1;
                // Add session duration to this if it hasn't been already
                if ( ! isset($temp_counter['device_category_summary_unique_session_counter'][$session_id]) ) {
                    $categorized_data['device_category_summary'][$device_category]['total_session_duration'] += $session_duration;
                    $temp_counter['device_category_summary_unique_session_counter'][$session_id] = 1;
                }

                // Setup UTM Campaign Data
                if ( ! is_null($utm_campaign) ) {
                    if ( ! isset($categorized_data['campaign_summary'][$utm_campaign]) ) $categorized_data['campaign_summary'][$utm_campaign] = $analytics_performance_container;
                    $categorized_data['campaign_summary'][$utm_campaign]['session_count'][$session_id] = 1;
                    $categorized_data['campaign_summary'][$utm_campaign]['user_count'][$ip_address] = 1;
                    // Add session duration to this if it hasn't been already
                    if ( ! isset($temp_counter['campaign_summary_unique_session_counter'][$session_id]) ) {
                        $categorized_data['campaign_summary'][$utm_campaign]['total_session_duration'] += $session_duration;
                        $temp_counter['campaign_summary_unique_session_counter'][$session_id] = 1;
                    }
                }

                // Setup Landing Page Data
                if ( ! empty($landing_page_path) ) {
                    if ( ! isset($categorized_data['landing_page_summary'][$landing_page_path]) ) $categorized_data['landing_page_summary'][$landing_page_path] = $analytics_performance_container;
                    $categorized_data['landing_page_summary'][$landing_page_path]['session_count'][$session_id] = 1;
                    $categorized_data['landing_page_summary'][$landing_page_path]['user_count'][$ip_address] = 1;
                    $categorized_data['landing_page_summary'][$landing_page_path]['views']++;
                    // Add session duration to this if it hasn't been already
                    if ( ! isset($temp_counter['landing_page_summary_unique_session_counter'][$session_id]) ) {
                        $categorized_data['landing_page_summary'][$landing_page_path]['total_session_duration'] += $session_duration;
                        $temp_counter['landing_page_summary_unique_session_counter'][$session_id] = 1;
                    }
                }
                
                // Setup Referral URL Data
                if ( ! empty($referral_url) ) {
                    if ( ! isset($categorized_data['referral_url_summary'][$referral_url]) ) $categorized_data['referral_url_summary'][$referral_url] = $analytics_performance_container;
                    $categorized_data['referral_url_summary'][$referral_url]['session_count'][$session_id] = 1;
                    $categorized_data['referral_url_summary'][$referral_url]['user_count'][$ip_address] = 1;
                    $categorized_data['referral_url_summary'][$referral_url]['views']++;
                    // Add session duration to this if it hasn't been already
                    if ( ! isset($temp_counter['referral_url_summary_unique_session_counter'][$session_id]) ) {
                        $categorized_data['referral_url_summary'][$referral_url]['total_session_duration'] += $session_duration;
                        $temp_counter['referral_url_summary_unique_session_counter'][$session_id] = 1;
                    }
                }

                /**
                 * 
                 *  Handle specific event types
                 * 
                 */
                // Page Views
                if ($event_type == 'page_view') {

                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['page_views']++;
                    $totals['page_views']++;

                    if ( ! isset($data_by_date['page_views_by_date'][$event_formatted_date]) ) $data_by_date['page_views_by_date'][$event_formatted_date] = 0;
                    $data_by_date['page_views_by_date'][$event_formatted_date]++;

                    // Handle total page view data
                    if ( ! isset($categorized_data['page_view_summary'][$event_page_path]) ) {
                        $categorized_data['page_view_summary'][$event_page_path] = $analytics_performance_container;
                    }
                    $categorized_data['page_view_summary'][$event_page_path]['session_count'][$session_id] = 1;
                    $categorized_data['page_view_summary'][$event_page_path]['user_count'][$ip_address] = 1;
                    $categorized_data['page_view_summary'][$event_page_path]['views']++;

                    // Enrich summary data
                    if ( ! empty($session_traffic_source) ) $categorized_data['acquisition_summary'][$session_traffic_source]['page_views']++;
                    if ( ! empty($device_category) ) $categorized_data['device_category_summary'][$device_category]['page_views']++;
                    if ( ! empty($landing_page_path) ) $categorized_data['landing_page_summary'][$landing_page_path]['page_views']++;
                    if ( ! empty($referral_url) ) $categorized_data['referral_url_summary'][$referral_url]['page_views']++;
                    if ( ! empty($utm_campaign) ) $categorized_data['campaign_summary'][$utm_campaign]['page_views']++;
                    if ( ! empty($session_traffic_source) ) $categorized_data['acquisition_summary'][$session_traffic_source]['views']++; // Legacy support
                    if ( ! empty($device_category) ) $categorized_data['device_category_summary'][$device_category]['views']++; // Legacy support
                    if ( ! empty($landing_page_path) ) $categorized_data['landing_page_summary'][$landing_page_path]['views']++; // Legacy support
                    if ( ! empty($referral_url) ) $categorized_data['referral_url_summary'][$referral_url]['views']++; // Legacy support
                    if ( ! empty($utm_campaign) ) $categorized_data['campaign_summary'][$utm_campaign]['views']++; // Legacy support

                }

                // Non Page View Events
                if ($event_type != 'page_view') {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['non_page_view_events']++;
                    $totals['non_page_view_events']++;
                    if ( ! isset($data_by_date['events_by_date'][$event_formatted_date]) ) $data_by_date['events_by_date'][$event_formatted_date] = 0;
                    $data_by_date['events_by_date'][$event_formatted_date]++;
                }

                // Product Click
                if ($event_type == 'product_click') {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['product_clicks']++;
                    $totals['product_clicks']++;
                    $data_by_date['product_clicks_by_date'][$event_formatted_date]++;
                }

                // Product Category Page Views
                if ($event_type == 'page_view' && $object_type == 'product_cat') {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['category_page_views']++;
                    $totals['category_page_views']++;
                    $data_by_date['category_page_views_by_date'][$event_formatted_date]++;
                    if ( ! isset($session_unique_array['sessions_with_category_page_view'][$session_id]) ) {
                        $session_unique_array['sessions_with_category_page_view'][$session_id] = true;
                        $totals['sessions_with_category_page_views']++;
                    }
                }

                // Product Page Views
                if ($event_type == 'page_view' && $object_type == 'product') {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['product_page_views']++;
                    $totals['product_page_views']++;
                    if ( ! isset($data_by_date['product_page_views_by_date'][$event_formatted_date]) ) $data_by_date['product_page_views_by_date'][$event_formatted_date] = 0;
                    $data_by_date['product_page_views_by_date'][$event_formatted_date]++;
                    if ( ! isset($session_unique_array['sessions_with_product_page_view'][$session_id]) ) {
                        $session_unique_array['sessions_with_product_page_view'][$session_id] = true;
                        $totals['sessions_with_product_page_views']++;
                    }
                }

                // Add to cart
                if ($event_type == 'add_to_cart') {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['add_to_carts']++;
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['add_to_cart_value'] += $event_value;
                    $totals['add_to_carts']++;
                    $totals['add_to_cart_value'] += $event_value;
                    if ( ! isset($data_by_date['add_to_carts_by_date'][$event_formatted_date]) ) $data_by_date['add_to_carts_by_date'][$event_formatted_date] = 0;
                    $data_by_date['add_to_carts_by_date'][$event_formatted_date]++;
                    if ( ! isset($session_unique_array['sessions_with_add_to_cart'][$session_id]) ) {
                        $session_unique_array['sessions_with_add_to_cart'][$session_id] = true;
                        $totals['sessions_with_add_to_cart']++;
                    }
                    // Add to carts
                    if ( ! empty($session_traffic_source) ) $categorized_data['acquisition_summary'][$session_traffic_source]['add_to_carts']++;
                    if ( ! empty($device_category) ) $categorized_data['device_category_summary'][$device_category]['add_to_carts']++;
                    if ( ! empty($landing_page_path) ) $categorized_data['landing_page_summary'][$landing_page_path]['add_to_carts']++;
                    if ( ! empty($referral_url) ) $categorized_data['referral_url_summary'][$referral_url]['add_to_carts']++;
                    if ( ! empty($utm_campaign) ) $categorized_data['campaign_summary'][$utm_campaign]['add_to_carts']++;
                    // Add to cart value
                    if ( ! empty($session_traffic_source) ) $categorized_data['acquisition_summary'][$session_traffic_source]['add_to_cart_value'] += $event_value;
                    if ( ! empty($device_category) ) $categorized_data['device_category_summary'][$device_category]['add_to_cart_value'] += $event_value;
                    if ( ! empty($landing_page_path) ) $categorized_data['landing_page_summary'][$landing_page_path]['add_to_cart_value'] += $event_value;
                    if ( ! empty($referral_url) ) $categorized_data['referral_url_summary'][$referral_url]['add_to_cart_value'] += $event_value;
                    if ( ! empty($utm_campaign) ) $categorized_data['campaign_summary'][$utm_campaign]['add_to_cart_value'] += $event_value;
                }

                // Initiate Checkout
                if ($event_type == 'init_checkout') {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['initiate_checkouts']++;
                    $totals['initiate_checkouts']++;
                    if ( ! isset($session_unique_array['sessions_with_initiate_checkout'][$session_id]) ) {
                        $session_unique_array['sessions_with_initiate_checkout'][$session_id] = true;
                        $totals['sessions_with_initiate_checkout']++;
                    }
                    // Initiate checkouts
                    if ( ! empty($session_traffic_source) ) $categorized_data['acquisition_summary'][$session_traffic_source]['initiate_checkouts']++;
                    if ( ! empty($device_category) ) $categorized_data['device_category_summary'][$device_category]['initiate_checkouts']++;
                    if ( ! empty($landing_page_path) ) $categorized_data['landing_page_summary'][$landing_page_path]['initiate_checkouts']++;
                    if ( ! empty($referral_url) ) $categorized_data['referral_url_summary'][$referral_url]['initiate_checkouts']++;
                    if ( ! empty($utm_campaign) ) $categorized_data['campaign_summary'][$utm_campaign]['initiate_checkouts']++;
                }

                // Purchase - Product Line Items
                if ($event_type == 'product_purchase') {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['unique_products_purchased']++;
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['total_products_purchased'] += $event_quantity;
                    $totals['unique_products_purchased']++;
                    $totals['total_products_purchased'] += $event_quantity;
                }

                // Transaction
                if ($event_type == 'transaction') {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['transactions']++;
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['transaction_value'] += $event_value;
                    $totals['transactions']++;
                    $totals['transaction_value'] += $event_value;
                    if ( ! isset($data_by_date['transactions_by_date'][$event_formatted_date]) ) $data_by_date['transactions_by_date'][$event_formatted_date] = 0;
                    $data_by_date['transactions_by_date'][$event_formatted_date]++;
                    if ( ! isset($session_unique_array['sessions_with_transaction'][$session_id]) ) {
                        $session_unique_array['sessions_with_transaction'][$session_id] = true;
                        $totals['sessions_with_transaction']++;
                    }
                    if (! empty($landing_page_path)) {
                        $categorized_data['landing_page_summary'][$landing_page_path]['transactions']++;
                        $categorized_data['landing_page_summary'][$landing_page_path]['revenue'] += $event_value;
                        $categorized_data['landing_page_summary'][$landing_page_path]['total_value'] += $event_value;
                    }
                    if (! empty($referral_url)) {
                        $categorized_data['referral_url_summary'][$referral_url]['transactions']++;
                        $categorized_data['referral_url_summary'][$referral_url]['revenue'] += $event_value;
                        $categorized_data['referral_url_summary'][$referral_url]['total_value'] += $event_value;
                    }
                    if ( ! empty($utm_campaign) ) {
                        $categorized_data['campaign_summary'][$utm_campaign]['transactions']++;
                        $categorized_data['campaign_summary'][$utm_campaign]['revenue'] += $event_value;
                        $categorized_data['campaign_summary'][$utm_campaign]['total_value'] += $event_value;
                    }
                    if ( ! empty($session_traffic_source) ) {
                        $categorized_data['acquisition_summary'][$session_traffic_source]['transactions']++;
                        $categorized_data['acquisition_summary'][$session_traffic_source]['revenue'] += $event_value;
                        $categorized_data['acquisition_summary'][$session_traffic_source]['total_value'] += $event_value;
                    }
                    if ( ! empty($device_category) ) {
                        $categorized_data['device_category_summary'][$device_category]['transactions']++;
                        $categorized_data['device_category_summary'][$device_category]['revenue'] += $event_value;
                        $categorized_data['device_category_summary'][$device_category]['total_value'] += $event_value;
                    }
                }

                // Checkout Erors
                if ( $event_type == 'checkout_error' ) {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['checkout_error_count']++;
                    $totals['checkout_error_count']++;
                    if ( ! isset($data_by_date['checkout_errors_by_date'][$event_formatted_date]) ) $data_by_date['checkout_errors_by_date'][$event_formatted_date] = 0;
                    if ( isset($data_by_date['checkout_errors_by_date'][$event_formatted_date]) ) $data_by_date['checkout_errors_by_date'][$event_formatted_date]++;

                    // Capture error message if available
                    if ( is_array($additional_data) && isset($additional_data['error_message']) && ! empty($additional_data['error_message']) ) {
                        $error_message = sanitize_text_field( $additional_data['error_message'] );
                        if ( ! isset($categorized_data['checkout_errors_summary'][$error_message]) ) {
                            $categorized_data['checkout_errors_summary'][$error_message] = 0;
                        }
                        $categorized_data['checkout_errors_summary'][$error_message]++;
                    }

                }

                // Form Submit
                if ( $event_type == 'form_submit' ) {

                    // ID
                    $form_id = 'unknown';
                    if ( is_array($additional_data) && isset($additional_data['form_id']) && ! empty($additional_data['form_id']) ) $form_id = sanitize_text_field( $additional_data['form_id'] );

                    // Add to datatable, if not at max
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['form_submits']++;

                    // Increment Totals
                    $totals['form_submits']++;

                    // Track Daily
                    if ( isset($data_by_date['form_submits_by_date'][$event_formatted_date]) ) $data_by_date['form_submits_by_date'][$event_formatted_date]++;
                
                    // Setup form submit by id summary
                    if ( ! isset($categorized_data['form_submits_by_id_summary'][$form_id]) ) {
                        $categorized_data['form_submits_by_id_summary'][$form_id] = array();
                        $categorized_data['form_submits_by_id_summary'][$form_id]['total_count'] = 0;
                        $categorized_data['form_submits_by_id_summary'][$form_id]['sessions_with_submission'] = 0;
                        $categorized_data['form_submits_by_id_summary'][$form_id]['conversion_rate'] = 0.00;
                    }
                    $categorized_data['form_submits_by_id_summary'][$form_id]['total_count']++;

                    // Unique session tracking
                    if ( ! isset($session_unique_array['sessions_with_form_submit'][$session_id]) ) {
                        $session_unique_array['sessions_with_form_submit'][$session_id] = true;
                        $totals['sessions_with_form_submit']++;
                        $categorized_data['form_submits_by_id_summary'][$form_id]['sessions_with_submission']++;
                    }

                    // Form Submits
                    if ( ! empty($session_traffic_source) ) $categorized_data['acquisition_summary'][$session_traffic_source]['form_submits']++;
                    if ( ! empty($device_category) ) $categorized_data['device_category_summary'][$device_category]['form_submits']++;
                    if ( ! empty($landing_page_path) ) $categorized_data['landing_page_summary'][$landing_page_path]['form_submits']++;
                    if ( ! empty($referral_url) ) $categorized_data['referral_url_summary'][$referral_url]['form_submits']++;
                    if ( ! empty($utm_campaign) ) $categorized_data['campaign_summary'][$utm_campaign]['form_submits']++;

                }

                // Account created
                if ( $event_type == 'account_created' ) {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['account_created']++;
                    $totals['account_created']++;
                    if ( ! isset($data_by_date['account_created_by_date'][$event_formatted_date]) ) $data_by_date['account_created_by_date'][$event_formatted_date] = 0;
                    $data_by_date['account_created_by_date'][$event_formatted_date]++;
                    // Account created
                    if ( ! empty($session_traffic_source) ) $categorized_data['acquisition_summary'][$session_traffic_source]['account_created']++;
                    if ( ! empty($device_category) ) $categorized_data['device_category_summary'][$device_category]['account_created']++;
                    if ( ! empty($landing_page_path) ) $categorized_data['landing_page_summary'][$landing_page_path]['account_created']++;
                    if ( ! empty($referral_url) ) $categorized_data['referral_url_summary'][$referral_url]['account_created']++;
                    if ( ! empty($utm_campaign) ) $categorized_data['campaign_summary'][$utm_campaign]['account_created']++;
                }

                /**
                 *
                 *  Log all events here
                 *
                 */
                // Setup container if not exists
                if ( ! isset($categorized_data['event_summary'][$event_type]) ) {
                    $categorized_data['event_summary'][$event_type] = array('total_count' => 0, 'user_count' => array(), 'session_count' => array(), 'total_value' => 0);
                }
                $categorized_data['event_summary'][$event_type]['total_count']++;
                $categorized_data['event_summary'][$event_type]['user_count'][$ip_address] = 1;
                $categorized_data['event_summary'][$event_type]['session_count'][$session_id] = 1;
                $categorized_data['event_summary'][$event_type]['total_value'] += $event_value;

                // All events by date
                if ( isset($data_by_date['all_events_by_date']['no_data_available']) ) $data_by_date['all_events_by_date'] = array(); // If we've still got the initial empty array, rebuild.
                if ( ! isset($data_by_date['all_events_by_date'][$event_type]) ) $data_by_date['all_events_by_date'][$event_type] = $data_warehouse->get_data_by_date_range_container();
                if ( isset($data_by_date['all_events_by_date'][$event_type][$event_formatted_date]) ) $data_by_date['all_events_by_date'][$event_type][$event_formatted_date]++;

                /**
                 * 
                 *  Product Performance
                 * 
                 */
                if ( $product_id > 0 ) {

                    if ( ! isset($categorized_data['product_summary'][$product_id]) ) {
                        $categorized_data['product_summary'][$product_id] = array(
                            'product_name'          => wp_strip_all_tags(html_entity_decode(get_the_title($product_id), ENT_QUOTES, 'UTF-8')),
                            'product_id'            => $product_id,
                            'variation_id'          => 0,
                            'user_count'            => array(),
                            'session_count'         => array(),
                            'product_clicks'        => 0, 
                            'product_page_views'    => 0, 
                            'add_to_cart'           => 0, 
                            'percent_of_sessions_with_add_to_cart'   => 0, 
                            'transactions'          => 0, 
                            'qty_purchased'         => 0, 
                            'total_value'           => 0
                        );
                    }
                    if (empty($categorized_data['product_summary'][$product_id]['product_name'])) $categorized_data['product_summary'][$product_id]['product_name'] = 'Unknown ID ' . $product_id;
                    $categorized_data['product_summary'][$product_id]['user_count'][$ip_address] = 1;
                    $categorized_data['product_summary'][$product_id]['session_count'][$session_id] = 1;
                    if ( $event_type == 'product_click' ) $categorized_data['product_summary'][$product_id]['product_clicks']++;
                    if ( $event_type == 'add_to_cart' ) $categorized_data['product_summary'][$product_id]['add_to_cart']++;
                    if ( $event_type == 'page_view' && $object_type == 'product' ) $categorized_data['product_summary'][$product_id]['product_page_views']++;
                    if ( $event_type == 'product_purchase' ) {
                        $categorized_data['product_summary'][$product_id]['transactions']++;
                        $categorized_data['product_summary'][$product_id]['total_value'] += $event_value;
                        $categorized_data['product_summary'][$product_id]['qty_purchased'] += $event_quantity;                
                    }

                    // Duplicate for variation ID
                    if ( $show_product_variations && $product_id > 0 && $variation_id > 0 ) {

                        if ( ! isset($categorized_data['product_summary'][$variation_id]) ) {
                            $categorized_data['product_summary'][$variation_id] = array(
                                'product_name'          => wp_strip_all_tags(html_entity_decode(get_the_title($variation_id), ENT_QUOTES, 'UTF-8')),
                                'product_id'            => $product_id,
                                'variation_id'          => $variation_id,
                                'user_count'            => array(),
                                'session_count'         => array(),
                                'product_clicks'        => 0, 
                                'product_page_views'    => 0, 
                                'add_to_cart'           => 0, 
                                'percent_of_sessions_with_add_to_cart'   => 0, 
                                'transactions'          => 0, 
                                'qty_purchased'         => 0, 
                                'total_value'           => 0
                            );
                        }

                        if (empty($categorized_data['product_summary'][$variation_id]['product_name'])) $categorized_data['product_summary'][$variation_id]['product_name'] = 'Unknown ID ' . $variation_id;
                        $categorized_data['product_summary'][$variation_id]['user_count'][$ip_address] = 1;
                        $categorized_data['product_summary'][$variation_id]['session_count'][$session_id] = 1;
                        if ( $event_type == 'product_click' ) $categorized_data['product_summary'][$variation_id]['product_clicks']++;
                        if ( $event_type == 'add_to_cart' ) $categorized_data['product_summary'][$variation_id]['add_to_cart']++;
                        if ( $event_type == 'page_view' && $object_type == 'product' ) $categorized_data['product_summary'][$variation_id]['product_page_views']++;
                        if ( $event_type == 'product_purchase' ) {
                            $categorized_data['product_summary'][$variation_id]['transactions']++;
                            $categorized_data['product_summary'][$variation_id]['total_value'] += $event_value;
                            $categorized_data['product_summary'][$variation_id]['qty_purchased'] += $event_quantity;                
                        }

                    }

                }

            }

            // Increment offset for next batch
            $offset += $limit;
            $processed_records += count($raw_analytics_data);
            
            // Clear batch data from memory (but preserve persistent session_data_map for next batch)
            unset($raw_analytics_data);
            unset($batch_session_data_map);
            // DO NOT unset $session_data_map - it needs to persist across batches!
            
            // Force garbage collection
            if ( function_exists('gc_collect_cycles') ) {
                gc_collect_cycles();
            }
                        
        }

        // Convert the blank performance container into all ints
        $analytics_performance_container['user_count'] = 0; // These begin as arrays
        $analytics_performance_container['session_count'] = 0; // These begin as arrays

        // Build the conversion rate chart
        foreach( $data_by_date['conversion_rate_by_date'] as $date_key => $value ) {

            $session_count  = ( isset($data_by_date['sessions_by_date']) ) ? (int) $data_by_date['sessions_by_date'][$date_key] : 0;
            $transactions   = ( isset($data_by_date['transactions_by_date']) ) ? (int) $data_by_date['transactions_by_date'][$date_key] : 0;
            $conversion_rate = wpdai_calculate_percentage( $transactions, $session_count );
            $data_by_date['conversion_rate_by_date'][$date_key] = $conversion_rate;

        }

        // Some cleaning - All Events
        if ( is_array($categorized_data['event_summary']) && ! empty($categorized_data['event_summary']) ) {
            foreach( $categorized_data['event_summary'] as $event_key => $event_data ) {
                $categorized_data['event_summary'][$event_key]['user_count'] = count($event_data['user_count']);
                $categorized_data['event_summary'][$event_key]['session_count'] = count($event_data['session_count']);
            }
        } else {
            $categorized_data['event_summary'] = array(
                'no_events_found' => array(
                    'total_count' => 0,
                    'user_count' => 0,
                    'session_count' => 0,
                    'total_value' => 0
                )
            );
        }
        // Some cleaning - Acquisition
        if ( is_array($categorized_data['acquisition_summary']) && ! empty($categorized_data['acquisition_summary']) ) {
            foreach( $categorized_data['acquisition_summary'] as $acquisition_channel => $acquisition_data ) {
                $categorized_data['acquisition_summary'][$acquisition_channel]['user_count'] = count($acquisition_data['user_count']);
                $categorized_data['acquisition_summary'][$acquisition_channel]['session_count'] = count($acquisition_data['session_count']);
                $categorized_data['acquisition_summary'][$acquisition_channel]['conversion_rate'] = wpdai_calculate_percentage( $categorized_data['acquisition_summary'][$acquisition_channel]['transactions'], $categorized_data['acquisition_summary'][$acquisition_channel]['session_count'], 2 );
                $categorized_data['acquisition_summary'][$acquisition_channel]['channel_percent'] = wpdai_calculate_percentage( $categorized_data['acquisition_summary'][$acquisition_channel]['session_count'], $totals['sessions'], 2 );
                $categorized_data['acquisition_summary'][$acquisition_channel]['average_session_duration'] = wpdai_divide( $categorized_data['acquisition_summary'][$acquisition_channel]['total_session_duration'], $categorized_data['acquisition_summary'][$acquisition_channel]['session_count'], 2 );
                $categorized_data['acquisition_summary'][$acquisition_channel]['page_views_per_session'] = wpdai_divide( $categorized_data['acquisition_summary'][$acquisition_channel]['page_views'], $categorized_data['acquisition_summary'][$acquisition_channel]['session_count'], 2 );
            }
        } else {
            $categorized_data['acquisition_summary']['no_acquisition_data_found'] = $analytics_performance_container;
        }
        // Some cleaning - Device Category
        if ( is_array($categorized_data['device_category_summary']) && ! empty($categorized_data['device_category_summary']) ) {
            foreach( $categorized_data['device_category_summary'] as $device_category => $device_data ) {
                $categorized_data['device_category_summary'][$device_category]['user_count'] = count($device_data['user_count']);
                $categorized_data['device_category_summary'][$device_category]['session_count'] = count($device_data['session_count']);
                $categorized_data['device_category_summary'][$device_category]['conversion_rate'] = wpdai_calculate_percentage( $categorized_data['device_category_summary'][$device_category]['transactions'], $categorized_data['device_category_summary'][$device_category]['session_count'], 2 );
                $categorized_data['device_category_summary'][$device_category]['channel_percent'] = wpdai_calculate_percentage( $categorized_data['device_category_summary'][$device_category]['session_count'], $totals['sessions'], 2 );
                $categorized_data['device_category_summary'][$device_category]['average_session_duration'] = wpdai_divide( $categorized_data['device_category_summary'][$device_category]['total_session_duration'], $categorized_data['device_category_summary'][$device_category]['session_count'], 2 );
                $categorized_data['device_category_summary'][$device_category]['page_views_per_session'] = wpdai_divide( $categorized_data['device_category_summary'][$device_category]['page_views'], $categorized_data['device_category_summary'][$device_category]['session_count'], 2 );
            }
        } else {
            $categorized_data['device_category_summary']['no_device_category_data_found'] = $analytics_performance_container;
        }
        // Some cleaning - UTM Campaigns
        if ( is_array($categorized_data['campaign_summary']) && ! empty($categorized_data['campaign_summary']) ) {
            foreach( $categorized_data['campaign_summary'] as $campaign_name => $campaign_data ) {
                $categorized_data['campaign_summary'][$campaign_name]['user_count'] = count($campaign_data['user_count']);
                $categorized_data['campaign_summary'][$campaign_name]['session_count'] = count($campaign_data['session_count']);
                $categorized_data['campaign_summary'][$campaign_name]['conversion_rate'] = wpdai_calculate_percentage( $categorized_data['campaign_summary'][$campaign_name]['transactions'], $categorized_data['campaign_summary'][$campaign_name]['session_count'], 2 );
                $categorized_data['campaign_summary'][$campaign_name]['channel_percent'] = wpdai_calculate_percentage( $categorized_data['campaign_summary'][$campaign_name]['session_count'], $totals['sessions'], 2 );
                $categorized_data['campaign_summary'][$campaign_name]['average_session_duration'] = wpdai_divide( $categorized_data['campaign_summary'][$campaign_name]['total_session_duration'], $categorized_data['campaign_summary'][$campaign_name]['session_count'], 2 );
                $categorized_data['campaign_summary'][$campaign_name]['page_views_per_session'] = wpdai_divide( $categorized_data['campaign_summary'][$campaign_name]['page_views'], $categorized_data['campaign_summary'][$campaign_name]['session_count'], 2 );
            }
        } else {
            $categorized_data['campaign_summary']['no_campaign_data_found'] = $analytics_performance_container;
        }
        // Some cleaning - Landing Page Data
        if ( is_array($categorized_data['landing_page_summary']) && ! empty($categorized_data['landing_page_summary']) ) {
            foreach($categorized_data['landing_page_summary'] as $page_view_href => $page_data) {
                $categorized_data['landing_page_summary'][$page_view_href]['user_count'] = count( $page_data['user_count'] );
                $categorized_data['landing_page_summary'][$page_view_href]['session_count'] = count( $page_data['session_count'] );
                $categorized_data['landing_page_summary'][$page_view_href]['conversion_rate'] = wpdai_calculate_percentage( $categorized_data['landing_page_summary'][$page_view_href]['transactions'], $categorized_data['landing_page_summary'][$page_view_href]['session_count'], 2 );
                $categorized_data['landing_page_summary'][$page_view_href]['channel_percent'] = wpdai_calculate_percentage( $categorized_data['landing_page_summary'][$page_view_href]['session_count'], $totals['sessions'], 2 );
                $categorized_data['landing_page_summary'][$page_view_href]['average_session_duration'] = wpdai_divide( $categorized_data['landing_page_summary'][$page_view_href]['total_session_duration'], $categorized_data['landing_page_summary'][$page_view_href]['session_count'], 2 );
                $categorized_data['landing_page_summary'][$page_view_href]['page_views_per_session'] = wpdai_divide( $categorized_data['landing_page_summary'][$page_view_href]['page_views'], $categorized_data['landing_page_summary'][$page_view_href]['session_count'], 2 );
            }
        } else {
            $categorized_data['landing_page_summary']['no_landing_page_data_found'] = $analytics_performance_container;
        }
        // Some cleaning - Referral URL Data
        if ( is_array($categorized_data['referral_url_summary']) && ! empty($categorized_data['referral_url_summary']) ) {
            foreach($categorized_data['referral_url_summary'] as $page_view_href => $page_data) {
                $categorized_data['referral_url_summary'][$page_view_href]['user_count'] = count( $page_data['user_count'] );
                $categorized_data['referral_url_summary'][$page_view_href]['session_count'] = count( $page_data['session_count'] );
                $categorized_data['referral_url_summary'][$page_view_href]['conversion_rate'] = wpdai_calculate_percentage( $categorized_data['referral_url_summary'][$page_view_href]['transactions'], $categorized_data['referral_url_summary'][$page_view_href]['session_count'], 2 );
                $categorized_data['referral_url_summary'][$page_view_href]['channel_percent'] = wpdai_calculate_percentage( $categorized_data['referral_url_summary'][$page_view_href]['session_count'], $totals['sessions'], 2 );
                $categorized_data['referral_url_summary'][$page_view_href]['average_session_duration'] = wpdai_divide( $categorized_data['referral_url_summary'][$page_view_href]['total_session_duration'], $categorized_data['referral_url_summary'][$page_view_href]['session_count'], 2 );
                $categorized_data['referral_url_summary'][$page_view_href]['page_views_per_session'] = wpdai_divide( $categorized_data['referral_url_summary'][$page_view_href]['page_views'], $categorized_data['referral_url_summary'][$page_view_href]['session_count'], 2 );
            }
        } else {
            $categorized_data['referral_url_summary']['no_referral_url_data_found'] = $analytics_performance_container;
        }
        // Some cleaning - Products
        if ( is_array($categorized_data['product_summary']) && ! empty($categorized_data['product_summary']) ) {
            foreach( $categorized_data['product_summary'] as $product_id => $product_data ) {

                // Copy the main product datas user count, session count, product clicks, product page views into the variration,
                // We will keep the add to cart, transactions, qty purchased and total value as they have been fetched.
                if ( $product_data['variation_id'] > 0 ) {
                    $parent_data = $categorized_data['product_summary'][$product_data['product_id']] ?? null;
                    if ( $parent_data ) {
                        $parent_user_count = ( is_array($parent_data['user_count']) ) ? count($parent_data['user_count']) : (int)$parent_data['user_count'];
                        $parent_session_count = ( is_array($parent_data['session_count']) ) ? count($parent_data['session_count']) : (int)$parent_data['session_count'];
                        $parent_product_clicks = ( is_array($parent_data['product_clicks']) ) ? count($parent_data['product_clicks']) : (int)$parent_data['product_clicks'];
                        $parent_product_page_views = ( is_array($parent_data['product_page_views']) ) ? count($parent_data['product_page_views']) : (int)$parent_data['product_page_views'];
                        $categorized_data['product_summary'][$product_id]['user_count'] = $parent_user_count;
                        $categorized_data['product_summary'][$product_id]['session_count'] = $parent_session_count;
                        $categorized_data['product_summary'][$product_id]['product_clicks'] = $parent_product_clicks;
                        $categorized_data['product_summary'][$product_id]['product_page_views'] = $parent_product_page_views;
                    }
                } else {
                    $categorized_data['product_summary'][$product_id]['user_count'] = count($product_data['user_count']);
                    $categorized_data['product_summary'][$product_id]['session_count'] = count($product_data['session_count']);
                }

                // Few more calculations
                $categorized_data['product_summary'][$product_id]['conversion_rate'] = wpdai_calculate_percentage( $product_data['transactions'],$categorized_data['product_summary'][$product_id]['session_count'], 2 );
                $categorized_data['product_summary'][$product_id]['percent_of_sessions_with_add_to_cart'] = wpdai_calculate_percentage( $product_data['add_to_cart'], $categorized_data['product_summary'][$product_id]['session_count'], 2 );

            }

            // Loop again to transform
            foreach( $categorized_data['product_summary'] as $product_id => $product_data ) {
                $categorized_data['product_summary'][$product_data['product_name']] = $categorized_data['product_summary'][$product_id];
                unset($categorized_data['product_summary'][$product_id]);
            }

        } else {
            $categorized_data['product_summary'] = array(
                'no_products_found' => array(
                    'user_count' => 0,
                    'session_count' => 0,
                    'product_clicks' => 0,
                    'product_page_views' => 0,
                    'add_to_cart' => 0,
                    'add_to_cart_per_session' => 0,
                    'transactions' => 0,
                    'qty_purchased' => 0,
                    'total_value' => 0
                    )
                );
        }
        // Some cleaning - Page View Data
        if ( is_array($categorized_data['page_view_summary']) && ! empty($categorized_data['page_view_summary']) ) {
            foreach($categorized_data['page_view_summary'] as $page_view_href => $page_data) {
                $categorized_data['page_view_summary'][$page_view_href]['session_count'] = count( $page_data['session_count'] );
                $categorized_data['page_view_summary'][$page_view_href]['user_count'] = count( $page_data['user_count'] );
            }
        } else {
            $categorized_data['page_view_summary'] = array(
                'no_page_views_found' => array(
                    'session_count' => 0,
                    'user_count'    => 0,
                    'views'         => 0,
                    'transactions'  => 0,
                    'revenue'       => 0
                )
            );
        }

        // Enrich the form submissions
        if ( is_array($categorized_data['form_submits_by_id_summary']) && ! empty($categorized_data['form_submits_by_id_summary']) ) {
            foreach( $categorized_data['form_submits_by_id_summary'] as $form_id => $form_data ) {
                $categorized_data['form_submits_by_id_summary'][$form_id]['conversion_rate'] = wpdai_calculate_percentage( $form_data['sessions_with_submission'], $totals['sessions'], 2 );
            }
        }

        // Do total calculations
        $number_of_days = $data_warehouse->get_n_days_range();
        $totals['average_session_duration'] = wpdai_divide( $totals['session_duration'], $totals['sessions'], 2 );
        $totals['sessions_per_day'] = wpdai_divide( $totals['sessions'], $number_of_days, 2 );
        $totals['users_per_day'] = wpdai_divide( $totals['users'], $number_of_days, 2 );
        $totals['page_views_per_session'] = wpdai_divide( $totals['page_views'], $totals['sessions'], 2 );
        $totals['events_per_session'] = wpdai_divide( $totals['non_page_view_events'], $totals['sessions'], 2 );
        $totals['percent_sessions_with_category_view'] = wpdai_calculate_percentage( $totals['sessions_with_category_page_views'], $totals['sessions'], 2 );
        $totals['percent_sessions_with_product_page_view'] = wpdai_calculate_percentage( $totals['sessions_with_product_page_views'], $totals['sessions'], 2 );
        $totals['percent_sessions_with_add_to_cart'] = wpdai_calculate_percentage( $totals['sessions_with_add_to_cart'], $totals['sessions'], 2 );
        $totals['percent_sessions_with_initiate_checkout'] = wpdai_calculate_percentage( $totals['sessions_with_initiate_checkout'], $totals['sessions'], 2 );
        $totals['percent_sessions_with_form_submit'] = wpdai_calculate_percentage( $totals['sessions_with_form_submit'], $totals['sessions'], 2 );
        $totals['conversion_rate'] = wpdai_calculate_percentage( $totals['transactions'], $totals['sessions'], 2 );

        // Conversion funnel summary
        $categorized_data['conversion_funnel_summary']['sessions']['count'] = $totals['sessions'];
        $categorized_data['conversion_funnel_summary']['sessions']['percent'] = 100.00;
        $categorized_data['conversion_funnel_summary']['category_page_view']['count'] = $totals['sessions_with_category_page_views'];
        $categorized_data['conversion_funnel_summary']['category_page_view']['percent'] = $totals['percent_sessions_with_category_view'];
        $categorized_data['conversion_funnel_summary']['product_page_views']['count'] = $totals['sessions_with_product_page_views'];
        $categorized_data['conversion_funnel_summary']['product_page_views']['percent'] = $totals['percent_sessions_with_product_page_view'];
        $categorized_data['conversion_funnel_summary']['add_to_carts']['count'] = $totals['sessions_with_add_to_cart'];
        $categorized_data['conversion_funnel_summary']['add_to_carts']['percent'] = $totals['percent_sessions_with_add_to_cart'];
        $categorized_data['conversion_funnel_summary']['initiate_checkouts']['count'] = $totals['sessions_with_initiate_checkout'];
        $categorized_data['conversion_funnel_summary']['initiate_checkouts']['percent'] = $totals['percent_sessions_with_initiate_checkout'];
        $categorized_data['conversion_funnel_summary']['transactions_complete']['count'] = $totals['sessions_with_transaction'];
        $categorized_data['conversion_funnel_summary']['transactions_complete']['percent'] = $totals['conversion_rate'];

        // Return single-entity structure for the warehouse to store (do not call set_data here).
        $analytics_data = array(
            'totals'            => $totals,
            'categorized_data'  => $categorized_data,
            'data_by_date'      => $data_by_date,
            'data_table'        => array(
                'sessions' => $session_data_table,
                'products' => $categorized_data['product_summary'],
            ),
            'total_db_records'  => $processed_records,
        );

        return apply_filters( 'wpd_alpha_insights_data_source_analytics', $analytics_data, $data_warehouse );
    }

        /**
     * Query analytics data with limit and offset for batching
     * 
     * @param array $raw_analytics_data Reference to store event data
     * @param array $session_data_map Reference to store session data map
     * @param int $limit Number of records to fetch
     * @param int $offset Starting offset
     * @return bool Success status
     */
    protected function query_analytics_data( &$raw_analytics_data, &$session_data_map, $limit, $offset, $data_warehouse ) {

        global $wpdb;
        $wpd_db                         = new WPDAI_Database_Interactor();
        $woo_events_table               = $wpd_db->events_table;
        $session_data_table             = $wpd_db->session_data_table;
        $where_clause                   = $this->get_analytics_where_clause( $data_warehouse );
        
        // Sanitize limit and offset as integers for safety
        $limit = absint( $limit );
        $offset = absint( $offset );

        // Fetch Events With Limit, Offset & Filters
        // Note: Table names are trusted (from WPDAI_Database_Interactor), where_clause uses $wpdb->prepare() internally
        $events_sql_query = 
            "SELECT 
            session_id,
            date_created_gmt,
            event_type, 
            event_quantity,
            event_value,
            product_id,
            variation_id,
            page_href, 
            object_type, 
            additional_data
            FROM $woo_events_table
            WHERE 1=1
            $where_clause
            ORDER BY date_created_gmt ASC
            LIMIT %d OFFSET %d";

        $events_sql_query = $wpdb->prepare( $events_sql_query, $limit, $offset );
        $raw_analytics_data = $wpdb->get_results( $events_sql_query, 'ARRAY_A' );

        if ( $wpdb->last_error ) {
            wpdai_write_log( 'Error capturing analytics data from DB, dumping the error and query.', 'db_error' );
            wpdai_write_log( $wpdb->last_error, 'db_error' );
            wpdai_write_log( $wpdb->last_query, 'db_error' );
            return false;
        }

        // Get distinct session IDs from the events data
        $session_ids = array();
        if ( is_array($raw_analytics_data) && !empty($raw_analytics_data) ) {
            $session_ids = array_unique(array_column($raw_analytics_data, 'session_id'));
            $session_ids = array_filter($session_ids); // Remove empty/null session IDs
            // Normalize session_ids: trim whitespace and ensure they're strings
            $session_ids = array_map(function($id) {
                return trim((string)$id);
            }, $session_ids);
            $session_ids = array_filter($session_ids); // Remove empty after trimming
        }

        // Fetch session data for the distinct session IDs
        // Don't reset if map already has data (preserve existing session data from previous batches)
        if ( ! is_array( $session_data_map ) ) {
            $session_data_map = array();
        }
        
        if ( !empty($session_ids) ) {

            // Chunk session_ids to avoid MySQL IN clause limits (max_allowed_packet, query size limits)
            // Large batches can cause queries to fail silently or exceed packet size
            // Reduced from 500 to 250 to handle long session IDs and avoid query size limits
            // Calculate safe chunk size based on average session ID length
            $avg_session_id_length = 0;
            if ( !empty($session_ids) ) {
                $total_length = array_sum(array_map('strlen', $session_ids));
                $avg_session_id_length = $total_length / count($session_ids);
            }
            
            // Adjust chunk size based on session ID length
            // Longer session IDs = smaller chunks to avoid max_allowed_packet limits
            // Estimate: each session ID in IN clause adds ~50-60 bytes (with quotes, commas, etc.)
            // Default max_allowed_packet is often 16MB, but we want to stay well under
            // For safety, limit query size to ~1MB per chunk (allowing for query overhead)
            $base_chunk_size = 250; // Reduced from 500
            if ( $avg_session_id_length > 50 ) {
                // Very long session IDs - use smaller chunks
                $base_chunk_size = 100;
            } elseif ( $avg_session_id_length > 40 ) {
                // Medium-long session IDs
                $base_chunk_size = 150;
            }
            
            $session_id_chunk_size = apply_filters( 'wpd_ai_session_data_chunk_size', $base_chunk_size );
            $session_id_chunks = array_chunk( $session_ids, $session_id_chunk_size );
            $all_session_data_results = array();
            $all_found_session_ids = array();
            $chunks_processed = 0;
            $chunks_failed = 0;
            $chunks_empty = 0;
            
            // Process each chunk separately
            foreach ( $session_id_chunks as $chunk_index => $session_ids_chunk ) {
                
                // Calculate estimated query size for this chunk
                $estimated_query_size = strlen($session_data_table) + 200; // Base query size
                foreach ( $session_ids_chunk as $sid ) {
                    $estimated_query_size += strlen($sid) + 10; // Each ID + quotes, commas, etc.
                }
                
                // Fetch session data for this chunk
                // Note: Session IDs are normalized (trimmed) in PHP before query and after retrieval
                // This handles potential whitespace differences between events and session_data tables
                $session_ids_placeholder = implode(',', array_fill(0, count($session_ids_chunk), '%s'));
                
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is from trusted source.
                $session_sql_query = $wpdb->prepare(
                    "SELECT 
                    session_id,
                    user_id, 
                    landing_page,
                    referral_url,
                    date_created_gmt,
                    date_updated_gmt,
                    device_category,
                    ip_address,
                    engaged_session
                    FROM $session_data_table 
                    WHERE session_id IN ($session_ids_placeholder)",
                    $session_ids_chunk
                );

                // Check if prepare() succeeded (it can fail silently)
                if ( $session_sql_query === false ) {
                    wpdai_write_log( 
                        sprintf( 
                            'ERROR: wpdb->prepare() failed for chunk %d/%d (%d session_ids, ~%d bytes). This may indicate query size limit exceeded.', 
                            $chunk_index + 1,
                            count($session_id_chunks),
                            count($session_ids_chunk),
                            $estimated_query_size
                        ), 
                        'db_error' 
                    );
                    $chunks_failed++;
                    continue;
                }

                $chunk_results = $wpdb->get_results( $session_sql_query, 'ARRAY_A' );

                // Log db error
                if ( $wpdb->last_error ) {
                    wpdai_write_log( 
                        sprintf( 
                            'ERROR: Database error capturing session data (chunk %d/%d, %d session_ids, ~%d bytes). Error: %s', 
                            $chunk_index + 1,
                            count($session_id_chunks),
                            count($session_ids_chunk),
                            $estimated_query_size,
                            $wpdb->last_error
                        ), 
                        'db_error' 
                    );
                    wpdai_write_log( 'Query: ' . substr($wpdb->last_query, 0, 500) . '...', 'db_error' );
                    $chunks_failed++;
                    // Continue with other chunks even if one fails
                    continue;
                }

                // Check if query returned results (even if empty, it should be an array)
                if ( ! is_array($chunk_results) ) {
                    wpdai_write_log( 
                        sprintf( 
                            'WARNING: Query returned non-array result for chunk %d/%d (%d session_ids). Result type: %s', 
                            $chunk_index + 1,
                            count($session_id_chunks),
                            count($session_ids_chunk),
                            gettype($chunk_results)
                        ), 
                        'db_error' 
                    );
                    $chunks_failed++;
                    continue;
                }

                // Track chunk processing
                if ( empty($chunk_results) ) {
                    $chunks_empty++;
                } else {
                    $chunks_processed++;
                }

                // Merge chunk results
                if ( !empty($chunk_results) ) {
                    $all_session_data_results = array_merge( $all_session_data_results, $chunk_results );
                    
                    // Track found session_ids from this chunk
                    foreach ( $chunk_results as $session_row ) {
                        $normalized_session_id = trim((string)$session_row['session_id']);
                        $all_found_session_ids[] = $normalized_session_id;
                    }
                }
            }
            
            // Log chunk processing summary
            if ( $chunks_failed > 0 || $chunks_empty > 0 ) {
                wpdai_write_log( 
                    sprintf( 
                        'Session data chunk processing summary: %d total chunks, %d processed successfully, %d returned empty, %d failed. Chunk size: %d, Avg session ID length: %.1f chars', 
                        count($session_id_chunks),
                        $chunks_processed,
                        $chunks_empty,
                        $chunks_failed,
                        $session_id_chunk_size,
                        $avg_session_id_length
                    ), 
                    'session_data_missing' 
                );
            }

            // Create a map of session_id => session_data for quick lookup
            // Normalize session_ids when creating the map (trim whitespace)
            if ( !empty($all_session_data_results) ) {
                foreach ( $all_session_data_results as $session_row ) {
                    // Normalize session_id by trimming
                    $normalized_session_id = trim((string)$session_row['session_id']);
                    $session_data_map[$normalized_session_id] = $session_row;
                }
                
                // Diagnostic logging: track missing session data
                $missing_session_ids = array_diff($session_ids, $all_found_session_ids);
                if ( !empty($missing_session_ids) ) {
                    $missing_count = count($missing_session_ids);
                    $total_count = count($session_ids);
                    $found_count = count($all_found_session_ids);
                    
                    wpdai_write_log( 
                        sprintf( 
                            'Session data lookup: Found %d/%d session records (%d chunks total: %d processed, %d empty, %d failed). Missing %d session_ids in batch (offset: %d). Sample missing IDs: %s', 
                            $found_count,
                            $total_count,
                            count($session_id_chunks),
                            $chunks_processed,
                            $chunks_empty,
                            $chunks_failed,
                            $missing_count,
                            $offset,
                            implode(', ', array_slice($missing_session_ids, 0, 5))
                        ), 
                        'session_data_missing'
                    );
                    
                    // Log sample of what we're looking for vs what we found (for debugging)
                    if ( $missing_count > 0 && $found_count > 0 ) {
                        $sample_missing = array_slice($missing_session_ids, 0, 3);
                        $sample_found = array_slice($all_found_session_ids, 0, 3);
                        wpdai_write_log( 
                            sprintf( 
                                'Sample missing session_ids: [%s] | Sample found session_ids: [%s]', 
                                implode(', ', $sample_missing),
                                implode(', ', $sample_found)
                            ), 
                            'session_data_missing'
                        );
                    }
                }
            } else {
                // No results found at all
                wpdai_write_log( 
                    sprintf( 
                        'Session data lookup: No session_data records found for %d session_ids in %d chunks (batch offset: %d). Sample session_ids: %s', 
                        count($session_ids),
                        count($session_id_chunks),
                        $offset,
                        implode(', ', array_slice($session_ids, 0, 5))
                    ), 
                    'session_data_missing'
                );
            }
        }

        return true;
    }

    /**
     * Get the where clause for the analytics data query
     * 
     * @param WPDAI_Data_Warehouse $data_warehouse The data warehouse instance (required).
     * @return string The where clause for the analytics data query
     */
    public function get_analytics_where_clause( WPDAI_Data_Warehouse $data_warehouse ) {

        global $wpdb;
        $wpd_db                         = new WPDAI_Database_Interactor();
        $woo_events_table               = $wpd_db->events_table;
        $session_data_table             = $wpd_db->session_data_table;
        $filters                        = $data_warehouse->get_filter();
        $analytics_settings             = wpdai_get_analytics_settings();
        $engaged_sessions_only          = ( isset($analytics_settings['only_track_engaged_sessions']) ) ? intval($analytics_settings['only_track_engaged_sessions']) : 0;
        $session_id_filter              = $data_warehouse->get_data_filter('website_traffic', 'session_id');
        $event_type_filter              = $data_warehouse->get_data_filter('website_traffic', 'event_type');
        $session_contains_event_filter  = $data_warehouse->get_data_filter('website_traffic', 'session_contains_event');
        $device_type_filter             = $data_warehouse->get_data_filter('website_traffic', 'device_type');
        $event_page_url_filter          = $data_warehouse->get_data_filter('website_traffic', 'page_href_contains');
        $referral_url_contains_filter   = $data_warehouse->get_data_filter('website_traffic', 'referral_url_contains');
        $user_id_filter                 = $data_warehouse->get_data_filter('website_traffic', 'user_id');
        $ip_address_filter              = $data_warehouse->get_data_filter('website_traffic', 'ip_address');
        $product_id_filter              = $data_warehouse->get_data_filter('website_traffic', 'product_id');
        $query_parameter_values_filter  = $data_warehouse->get_data_filter('website_traffic', 'query_parameter_values');
        $where_clause = '';

        // Check for all time filter
        if ( isset($filters['date_preset']) && $filters['date_preset'] === 'all_time' ) {
            if ( isset($filters['date_from']) ) unset($filters['date_from']);
            if ( isset($filters['date_to']) ) unset($filters['date_to']);
        }

        // Filter Events By Date
        if ( isset($filters['date_from']) && isset($filters['date_to']) ) {
            // Ensure dates are in Y-m-d H:i:s format for proper timezone handling
            // If only date is provided (Y-m-d), add time component
            $date_from_local = $filters['date_from'];
            $date_to_local = $filters['date_to'];
            
            // If date_from doesn't have time, assume start of day (00:00:00)
            if ( strlen( $date_from_local ) === 10 ) {
                $date_from_local .= ' 00:00:00';
            }
            
            // If date_to doesn't have time, use start of NEXT day (00:00:00) to capture full day
            // This ensures we get all events up to 23:59:59 of the specified day in local time
            if ( strlen( $date_to_local ) === 10 ) {
                // Parse the date and add 1 day in local timezone
                $date_to_datetime_local = new DateTime( $date_to_local . ' 00:00:00', wp_timezone() );
                $date_to_datetime_local->modify( '+1 day' );
                $date_to_local = $date_to_datetime_local->format( 'Y-m-d H:i:s' );
            }
            
            // Convert local times to GMT for querying (data is stored in GMT)
            $date_from_gmt = get_gmt_from_date( $date_from_local );
            $date_to_gmt = get_gmt_from_date( $date_to_local );

            // Use prepared statements for safety
            $where_clause .= $wpdb->prepare( ' AND date_created_gmt >= %s', $date_from_gmt );
            $where_clause .= $wpdb->prepare( ' AND date_created_gmt < %s', $date_to_gmt ); // Use < instead of <= since date_to is start of next day
        }

        // Filter Events By Event Type
        if ( $event_type_filter  ) {

            $escaped_values = array_map( fn($val) => $wpdb->prepare('%s', $val), $event_type_filter );
            $in_clause = implode( ',', $escaped_values );

            $where_clause .= ' AND event_type IN (' . $in_clause . ')';
        }

        // Filter Events By Product ID
        if ( $product_id_filter  ) {

            $escaped_values = array_map( fn($val) => $wpdb->prepare('%d', $val), $product_id_filter );
            $in_clause = implode( ',', $escaped_values );

            $where_clause .= ' AND product_id IN (' . $in_clause . ')';
        }

        // Filter Sessions By IP Address
        if ( $ip_address_filter  ) {
    
            $escaped_values = array_map( fn($val) => $wpdb->prepare('%s', $val), $ip_address_filter );
            $in_clause = implode( ',', $escaped_values );

            $where_clause .= " AND session_id IN (
                SELECT DISTINCT session_id
                FROM $session_data_table
                WHERE ip_address IN ($in_clause)
            )";
        }

        // Filter Sessions By User ID
        if ( $user_id_filter  ) {

            $escaped_values = array_map( fn($val) => $wpdb->prepare('%d', $val), $user_id_filter );
            $in_clause = implode( ',', $escaped_values );

            $where_clause .= " AND session_id IN (
                SELECT DISTINCT session_id
                FROM $session_data_table
                WHERE user_id IN ($in_clause)
            )";
        }
        
        // Filter Sessions By Device Type
        if ( $device_type_filter ) {

            $escaped_values = array_map( fn($val) => $wpdb->prepare('%s', $val), $device_type_filter );
            $in_clause = implode( ',', $escaped_values );

            $where_clause .= " AND session_id IN (
                SELECT DISTINCT session_id
                FROM $session_data_table
                WHERE device_category IN ($in_clause)
            )";

        }

        // Filter Sessions By Session ID
        if ( $session_id_filter  ) {

            $escaped_values = array_map( fn($val) => $wpdb->prepare('%s', $val), $session_id_filter );
            $in_clause = implode( ',', $escaped_values );

            $where_clause .= ' AND session_id IN (' . $in_clause . ')';
        }

        // Filter Events By Query Parameter Key-Value Pairs
        if ( $query_parameter_values_filter && is_array($query_parameter_values_filter) ) {
            
            $like_conditions = [];
            
            foreach ( $query_parameter_values_filter as $filter_pair ) {
                // Ensure filter pair has both key and value
                if ( ! isset($filter_pair['key']) || ! isset($filter_pair['value']) ) continue;
                
                // Urlencode replaces spaces and +s with encoded version, to match what's in the DB
                $filter_key = urlencode($filter_pair['key']);
                $filter_value = urlencode($filter_pair['value']);
                
                // The landing_page field stores encoded URLs
                $search_pattern = $filter_key . '=' . $filter_value;
                
                // Add LIKE condition for this key-value pair
                $like_conditions[] = $wpdb->prepare( 
                    "landing_page LIKE %s", 
                    '%' . $wpdb->esc_like( $search_pattern ) . '%' 
                );
            }
            
            // If we have any conditions, add them to the WHERE clause with OR logic
            if ( ! empty( $like_conditions ) ) {
                $where_clause .= " AND session_id IN (
                    SELECT DISTINCT session_id
                    FROM $session_data_table
                    WHERE " . implode( ' OR ', $like_conditions ) . "
                )";
            }
        }

        // Filter Events By Event Page HREF Containing
        if ( $event_page_url_filter ) {

            $like_clauses = array_map( function( $value ) use ( $wpdb ) {
                // Escape and safely format each LIKE comparison
                return $wpdb->prepare( "page_href LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' );
            }, $event_page_url_filter );
        
            $where_clause .= ' AND (' . implode( ' OR ', $like_clauses ) . ')';
        }

        // Filter Sessions By Referral URL Containing
        if ( $referral_url_contains_filter ) {

            $like_clauses = array_map( function( $value ) use ( $wpdb ) {
                // Escape and safely format each LIKE comparison
                return $wpdb->prepare( "referral_url LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' );
            }, $referral_url_contains_filter );
        
            $where_clause .= " AND session_id IN (
                SELECT DISTINCT session_id
                FROM $session_data_table
                WHERE (" . implode( ' OR ', $like_clauses ) . ")
            )";
        }

        // Filter Events By Sessions That Contain Particular Events
        if ( $session_contains_event_filter ) {

            $subqueries = [];

            // --- Handle normal event types ---
            $normal_events = array_filter(
                $session_contains_event_filter,
                fn($event) => $event !== 'product_page_view'
            );

            if ( ! empty( $normal_events ) ) {
                // Escape each value properly
                $escaped_values = array_map( fn($val) => $wpdb->prepare('%s', $val), $normal_events );
                $in_clause = implode( ',', $escaped_values );

                $subqueries[] = "
                    SELECT DISTINCT session_id
                    FROM $woo_events_table
                    WHERE event_type IN ($in_clause)
                ";
            }

            // --- Handle the special case for product_page_view ---
            if ( in_array( 'product_page_view', $session_contains_event_filter, true ) ) {
                $subqueries[] = "
                    SELECT DISTINCT session_id
                    FROM $woo_events_table
                    WHERE event_type = 'page_view'
                    AND object_type = 'product'
                ";
            }

            // --- Combine the subqueries with UNION to merge all matching sessions ---
            if ( ! empty( $subqueries ) ) {
                $where_clause .= ' AND session_id IN (
                    ' . implode( ' UNION ', $subqueries ) . '
                )';
            }
        }

        // Filter Out Unengaged Sessions
        if ( $engaged_sessions_only ) {
            $where_clause .= " AND session_id IN (
                SELECT DISTINCT session_id
                FROM $session_data_table
                WHERE engaged_session = 1
                OR date_created_gmt != date_updated_gmt
            )";
        }

        // Return the where clause
        return $where_clause;

    }

    /**
     * Get the total count of analytics records for the current warehouse filters.
     *
     * Used by fetch_data() for batching and by the warehouse for public API.
     *
     * @since 5.0.0
     * @param WPDAI_Data_Warehouse $data_warehouse The data warehouse instance (filters, etc.).
     * @return int|false Total count of records or false on error.
     */
    public function get_analytics_event_count( WPDAI_Data_Warehouse $data_warehouse ) {
        global $wpdb;

        $wpd_db           = new WPDAI_Database_Interactor();
        $woo_events_table = $wpd_db->events_table;
        $where_clause     = $this->get_analytics_where_clause( $data_warehouse );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from trusted source.
        $count_sql_query  = "SELECT COUNT(*) FROM $woo_events_table AS events WHERE 1=1 $where_clause";
        $total_count      = (int) $wpdb->get_var( $count_sql_query );

        if ( $wpdb->last_error ) {
            wpdai_write_log( __( 'Error getting analytics event count from DB, dumping the error and query.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'db_error' );
            wpdai_write_log( $wpdb->last_error, 'db_error' );
            wpdai_write_log( $wpdb->last_query, 'db_error' );
            return false;
        }

        return $total_count;
    }

    /**
     * Get the total count of sessions for the current warehouse filters.
     *
     * Counts distinct sessions from the events table that match the filters.
     *
     * @since 5.0.0
     * @param WPDAI_Data_Warehouse $data_warehouse The data warehouse instance (filters, etc.).
     * @return int|false Total count of sessions or false on error.
     */
    public function get_analytics_session_count( WPDAI_Data_Warehouse $data_warehouse ) {
        global $wpdb;

        $wpd_db           = new WPDAI_Database_Interactor();
        $woo_events_table = $wpd_db->events_table;
        $where_clause     = $this->get_analytics_where_clause( $data_warehouse );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from trusted source.
        $count_sql_query  = "SELECT COUNT(DISTINCT session_id) FROM $woo_events_table WHERE 1=1 $where_clause";
        $total_count      = (int) $wpdb->get_var( $count_sql_query );

        if ( $wpdb->last_error ) {
            wpdai_write_log( __( 'Error getting analytics session count from DB, dumping the error and query.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'db_error' );
            wpdai_write_log( $wpdb->last_error, 'db_error' );
            wpdai_write_log( $wpdb->last_query, 'db_error' );
            return false;
        }

        return $total_count;
    }

}

// Self-register when file is loaded.
new WPDAI_Analytics_Data_Source();
