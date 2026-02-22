<?php
/**
 * Sales Data Source
 *
 * Provides orders, customers, products, coupons, taxes, and refunds in a single fetch.
 * One-to-many: one data source fills multiple entity slots.
 *
 * @package Alpha Insights
 * @since 5.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * Sales data source class (multi-entity)
 *
 * Returns data for: orders, customers, products, coupons, taxes, refunds.
 * Use get_data_by_date_range_container() for date alignment on each entity's data_by_date.
 *
 * @since 5.0.0
 */
class WPDAI_Sales_Data_Source extends WPDAI_Custom_Data_Source_Base {

    /**
     * Entity names this data source provides (sales group)
     *
     * @since 5.0.0
     * @var array<string>
     */
    protected $entity_names = array(
        'orders',
        'customers',
        'products',
        'coupons',
        'taxes',
        'refunds',
    );

    /**
     * Fetch sales data (all sales entities in one call)
     *
     * Return multi-entity structure keyed by entity name. Get filters via $data_warehouse->get_filter().
     *
     * @since 5.0.0
     * @param WPDAI_Data_Warehouse $data_warehouse The data warehouse instance (required).
     * @return array Multi-entity: 'orders' => array(...), 'customers' => array(...), etc.
     */
    public function fetch_data( WPDAI_Data_Warehouse $data_warehouse ) {

        // If we need to fetch additional data
        $additional_data                = ( $data_warehouse->get_filter( 'additional_order_data' ) ) ? $data_warehouse->get_filter( 'additional_order_data' ) : array();
        $orders_data_table_limit        = $data_warehouse->get_data_table_limit('orders');
        $products_data_table_limit      = $data_warehouse->get_data_table_limit('products');
        $customers_data_table_limit     = $data_warehouse->get_data_table_limit('customers');

        // Filters
        $order_status_filter            = $data_warehouse->get_data_filter('orders', 'order_status');
        $billing_email_filter           = $data_warehouse->get_data_filter('orders', 'billing_email');
        $traffic_source_filter          = $data_warehouse->get_data_filter('orders', 'traffic_source');
        $device_type_filter             = $data_warehouse->get_data_filter('orders', 'device_type');
        $query_parameter_values_filter  = $data_warehouse->get_data_filter('orders', 'query_parameter_values'); // New key-value pair format
        $order_ids_filter               = $data_warehouse->get_data_filter('orders', 'order_ids');
        $product_ids_filter             = $data_warehouse->get_data_filter('products', 'products');
        $product_category_filter        = $data_warehouse->get_data_filter('products', 'product_category');
        $product_tag_filter             = $data_warehouse->get_data_filter('products', 'product_tag');
        $billing_country_filter         = $data_warehouse->get_data_filter('customers', 'billing_country');
        $user_id_filter                 = $data_warehouse->get_data_filter('customers', 'user_id');
        $ip_address_filter              = $data_warehouse->get_data_filter('customers', 'ip_address');
        $customer_billing_email_filter  = $data_warehouse->get_data_filter('customers', 'billing_email');

        // Default container commonly used
        $default_order_summary = array(

            'distinct_count'        => array(), // For holding unique entities
            'total_revenue'         => 0,
            'total_cost'            => 0,
            'total_profit'          => 0,
            'total_order_count'     => 0,
            'margin_percentage'     => 0,
            'average_order_value'   => 0,
            'percent_of_revenue'    => 0,

        );

        // Setup default containers
        $totals = array(

            // Top Line Order Calculations
            'order_metrics' => array(
                'total_order_count' 				        => 0,
                'total_order_revenue_inc_tax_and_refunds'   => 0,
                'total_order_revenue' 				        => 0,
                'total_order_revenue_ex_tax'                => 0,
                'total_order_tax'                           => 0,
                'total_order_cost' 						    => 0,
                'total_order_profit' 				        => 0,
                'total_freight_recovery' 			        => 0,
                'total_freight_cost' 				        => 0,
                'total_payment_gateway_costs' 		        => 0,
                'total_tax_collected' 					    => 0,
                'total_custom_order_costs'                  => 0,
                'total_custom_product_costs'                => 0,
                'total_product_cost_of_goods'               => 0,
                'largest_order_revenue' 				    => 0,
                'largest_order_cost' 					    => 0,
                'largest_order_profit' 					    => 0,
                'cost_percentage_of_revenue'                => 0,
                'average_order_margin'					    => 0,
                'average_order_revenue' 				    => 0,
                'average_order_cost'				        => 0,
                'average_line_items_per_order'              => 0,
                'average_order_profit' 					    => 0,
                'daily_average_order_count'                 => 0,
                'daily_average_order_revenue'               => 0,
                'daily_average_order_cost'                  => 0,
                'daily_average_order_profit'                => 0,
            ),

            'refund_metrics' => array(
                // Populated by refunds_internal
            ),

            'product_metrics' => array(
                
                // Product Data   
                'total_product_revenue' 			        => 0,
                'total_product_revenue_excluding_tax'       => 0,
                'total_product_cost' 				        => 0,
                'total_qty_sold' 					        => 0,
                'total_skus_sold' 					        => 0,
                'total_product_revenue_at_rrp' 		        => 0,
                'total_product_discount_amount'             => 0,
                'average_product_discount_percent'          => 0,
                'total_product_profit'                      => 0,
                'total_product_profit_at_rrp'               => 0,
                'average_profit_per_product'                => 0,
                'average_product_margin'                    => 0,
                'average_product_margin_at_rrp'             => 0,
                'average_qty_sold_per_day'                  => 0,
                'average_products_sold_per_day'             => 0,
                'average_skus_sold_per_day'                 => 0,
                'total_product_refund_amount'               => 0,
                'total_product_line_items_sold'             => 0,
                'largest_product_count_sold_per_order'      => 0,
                'largest_quantity_sold_per_order'           => 0,
                'total_line_items_refunded'                 => 0,

            ),

            'customer_metrics' => array(
                'customer_count_by_email_address'           => 0,
                'registered_customer_count'                 => 0,
                'registered_customer_percentage'            => 0,
                'guest_customer_count'                      => 0,
                'guest_customer_percentage'                 => 0,
                'new_customer_count'                        => 0,
                'new_customer_percentage'                   => 0,
                'returning_customer_count'                  => 0,
                'returning_customer_percentage'             => 0,
                'average_customer_value_revenue'            => 0,
                'average_customer_value_profit'             => 0,
                'orders_per_customer'                       => 0,
                'customer_count_purchased_more_than_once'   => 0,
                'customer_country_count'                    => 0,
                'customer_state_count'                      => 0,
                'products_purchased_per_customer'           => 0,
                'quantity_purchased_per_customer'           => 0,
                'customers_with_refund'                     => 0,
                'refunds_per_customer'                      => 0,
                'customer_refund_rate'                      => 0
            ),

            'coupon_metrics' => array(
                'total_discount_amount'                                 => 0,
                'total_discount_amount_tax'                             => 0,
                'total_discount_amount_ex_tax'                          => 0,
                'total_revenue_with_coupons'                            => 0,
                'total_cost_with_coupons'                               => 0,
                'total_profit_with_coupons'                             => 0,
                'average_margin_with_coupons'                           => 0,
                'revenue_percent_with_coupons'                          => 0,
                'order_percent_with_coupons'                            => 0,
                'profit_percent_with_coupons'                           => 0,
                'orders_with_coupons'                                   => 0,
                'orders_without_coupons'                                => 0,
                'percent_of_orders_with_coupons'                        => 0,
                'percent_of_orders_without_coupons'                     => 0,
                'total_coupons_used'                                    => 0,
                'total_coupon_quantity_used'                            => 0,
                'unique_coupon_codes_used'                              => 0,
                'coupons_per_order'                                     => 0,
                'average_coupon_discount_per_discounted_order'          => 0,
                'average_coupon_discount_percent_per_discounted_order'  => 0,
                'total_order_revenue_before_coupons'                    => 0,
                'total_coupon_discount_amount' 	                        => 0,
                'average_coupon_discount_percent'                       => 0,
            ),

            'tax_metrics' => array(
                'total_revenue_where_tax_was_collected' => 0,
                'tax_as_percentage_of_revenue' => 0,
                'orders_with_tax' => 0,
            )

        );

        $categorized_data = array(

            'order_metrics'     => array(
                'order_status_data' => array(),
                'order_cost_breakdown' => array(),
                'custom_order_cost_data' => array(),
                'payment_gateway_data' => array(),
                'payment_gateway_order_count' => array(),
                'acquisition_traffic_type'           => array(),
                'acquisition_query_parameter_keys'   => array(),
                'acquisition_query_parameter_values' => array(),
                'acquisition_landing_page'           => array(),
                'acquisition_referral_source'        => array(),
                'acquisition_campaign_name'          => array(),
                'revenue_by_day_of_week'                   => $data_warehouse->get_data_by_day_container(),
                'profit_by_day_of_week'                    => $data_warehouse->get_data_by_day_container(),
                'revenue_by_hour_of_day'                   => $data_warehouse->get_data_by_hour_container(),
                'profit_by_hour_of_day'                    => $data_warehouse->get_data_by_hour_container(),
                'order_ids' => array(),
            ),
            'customer_metrics'  => array(
                'new_vs_returning_data'     => array(
                    'new_customer'       => $default_order_summary,
                    'returning_customer' => $default_order_summary,
                ),
                'guest_vs_registered_data'  => array(
                    'guest_customer'      => $default_order_summary,
                    'registered_customer' => $default_order_summary
                ),
                'country_location_data'     => array(),
                'state_location_data'      => array(),
                'device_browser_data'       => array(),
                'device_type_data'          => array(),
            ),
            'product_metrics'   => array(
                'product_type_data'  => array(),
                'product_cat_data'   => array(),
                'product_tag_data'   => array()
            ),
            'coupon_metrics'    => array(
                'orders_with_and_without_coupons' => array(
                    'orders_with_coupons' => $default_order_summary,
                    'orders_without_coupons' => $default_order_summary,
                ),
                'order_ids' => array()
            ),
            'refund_metrics'    => array(
                // Populated by refunds_internal
            ),
            'tax_metrics'       => array(
                'tax_rate_summaries' => array()
            ),

        );

        // Data Tables -> Main entity tables
        $data_table = array(
            'order_metrics'     => array(),
            'customer_metrics'  => array(),
            'product_metrics'   => array(),
            'coupon_metrics'    => array(),
            'refund_metrics'    => array(
                // Populated by refunds_internal
            ),
            'tax_metrics'       => array(),
        );

        // Data By Date Containers
        $data_by_date = array(

            'order_metrics' => array(
                'gross_revenue_inc_tax_by_date'            => $data_warehouse->get_data_by_date_range_container(), // Gross (pre refunds) revenue including tax
                'revenue_by_date'                          => $data_warehouse->get_data_by_date_range_container(), // Net (post refunds) revenue inc tax
                'revenue_excluding_tax_by_date'            => $data_warehouse->get_data_by_date_range_container(), // Net (post refunds) revenue excluding tax
                'order_count_by_date'                      => $data_warehouse->get_data_by_date_range_container(),
                'profit_by_date'                           => $data_warehouse->get_data_by_date_range_container(),
                'revenue_by_traffic_type_by_date'          => array( 'no_data_available' => $data_warehouse->get_data_by_date_range_container() ),
                'average_order_value_by_date'              => $data_warehouse->get_data_by_date_range_container(),
                'average_order_margin_by_date'             => $data_warehouse->get_data_by_date_range_container(),
            ),
            'customer_metrics' => array(
                'unique_customer_orders_by_date'           => $data_warehouse->get_data_by_date_range_container(),
                'new_customer_orders_by_date'              => $data_warehouse->get_data_by_date_range_container(),
                'returning_customer_orders_by_date'        => $data_warehouse->get_data_by_date_range_container(),
                'guest_customer_orders_by_date'            => $data_warehouse->get_data_by_date_range_container(),
                'registered_customer_orders_by_date'       => $data_warehouse->get_data_by_date_range_container(),
            ),
            'product_metrics' => array(
                'product_revenue_by_date'    => $data_warehouse->get_data_by_date_range_container(),
                'quantity_sold_by_date' 		=> $data_warehouse->get_data_by_date_range_container(),
            ),
            'coupon_metrics' => array(
                'orders_with_coupon_by_date'     => $data_warehouse->get_data_by_date_range_container(),
                'coupon_discount_amount_by_date' => $data_warehouse->get_data_by_date_range_container(),
            ),
            'tax_metrics' => array(
                'taxes_collected_by_date' => $data_warehouse->get_data_by_date_range_container(),
                'tax_rates_collected_by_date' => array( 'no_data_available' => $data_warehouse->get_data_by_date_range_container() ),
            ),
            'refund_metrics' => array(
                // Populated by refunds_internal
            ),

        );

        // Fetch refunds data from external source and integrate into our data above
        $data_warehouse->fetch_data( array( 'refunds_internal' ) );
        $totals['refund_metrics'] = $data_warehouse->get_data( 'refunds_internal', 'totals' );
        $categorized_data['refund_metrics'] = $data_warehouse->get_data( 'refunds_internal', 'categorized_data' );
        $data_by_date['refund_metrics'] = $data_warehouse->get_data( 'refunds_internal', 'data_by_date' );
        $data_table['refund_metrics'] = $data_warehouse->get_data( 'refunds_internal', 'data_table' );
        $refund_total_db_records = $data_warehouse->get_data( 'refunds_internal', 'total_db_records' );

        // Capture Meta Variables
        $date_from                                 = $data_warehouse->get_date_from();
        $date_to                                   = $data_warehouse->get_date_to();
        $n_days_period                              = $data_warehouse->get_n_days_range();
        $date_format                                = $data_warehouse->get_filter( 'date_format_string' );
        $custom_order_cost_options                  = wpdai_get_custom_order_cost_options();
        $memory_limit                               = ini_get('memory_limit');

        // Default Array Variables
        $payment_gateway_array 			            = array();
        $refunded_product_ids                       = array();
        $unique_sku_array                           = array();
        $product_item_data                          = array();
        $product_type_data                          = array();
        $product_cat_data                           = array();
        $product_tag_data                           = array();
        $filtered_product_ids                       = array();
        $custom_order_cost_data                     = array();
        $payment_gateway_data                       = array();
        $unique_customer_daily_data_tracking        = array();
        $unique_counter                             = array(
            'unique_customers_by_email' => array()
        );

        // Default Variables
        $total_db_records                           = 0;
        $total_order_count 				            = 0;
        $largest_order_revenue 			            = 0;
        $largest_order_cost 			            = 0;
        $largest_order_profit			            = 0;
        $total_shipping_charged 		            = 0;
        $total_shipping_cost 			            = 0;
        $total_product_cost 			            = 0;
        $total_product_discounts 		            = 0;
        $total_refunds 					            = 0;
        $total_payment_gateway_costs 	            = 0;
        $total_tax_collected 			            = 0;
        $total_tax_owed                             = 0;
        $total_coupon_discounts                     = 0;
        $total_product_revenue 			            = 0;
        $total_product_revenue_ex_tax               = 0;
        $total_product_revenue_at_rrp 	            = 0;
        $total_qty_sold 				            = 0;
        $total_revenue 					            = 0;
        $total_cost 					            = 0;
        $total_profit 					            = 0;
        $margin_sum 					            = 0;
        $total_order_revenue_ex_tax                 = 0;
        $total_order_revenue_before_coupons         = 0;
        $total_order_discounts                      = 0;
        $total_order_revenue_before_discounts       = 0;
        $orders_with_discount                       = 0;
        $total_order_revenue_inc_tax_and_refunds    = 0;
        $total_skus_sold                            = 0;
        $total_product_profit                       = 0;
        $total_product_profit_at_rrp                = 0;
        $average_profit_per_product                 = 0;
        $average_product_margin                     = 0;
        $average_product_margin_at_rrp              = 0;
        $average_qty_sold_per_day                   = 0;
        $average_products_sold_per_day              = 0;
        $average_skus_sold_per_day                  = 0;
        $total_product_refund_amount                = 0;
        $total_product_line_items_sold              = 0;
        $largest_product_count_sold_per_order       = 0;
        $largest_quantity_sold_per_order            = 0;
        $total_line_items_refunded                  = 0;
        $customer_count_purchase_more_than_once     = 0;
        $customer_country_count                     = 0;
        $customer_state_count                       = 0;
        $customers_with_refund_count                = 0;
        $total_custom_order_costs                   = 0;
        $total_custom_product_costs                 = 0;
        $total_product_cost_of_goods                = 0;

        // Build Query
        $args = array(
            'limit' 			=> -1,
            'orderby' 			=> 'date',
            'order' 			=> 'DESC',
            'date_created' 		=> $date_from . "..." . $date_to, //'2018-02-01...2018-02-28',
            'type' 				=> array( 'shop_order' ),
            'status' 			=> wpdai_paid_order_statuses(),
            'return' 			=> 'ids',
        );

        // All time filter
        if ( $data_warehouse->get_filter('date_preset') === 'all_time' ) unset( $args['date_created'] );

        // Order Status Filter
        if ( $order_status_filter ) {
            $args['status'] = $order_status_filter;
            // Needs to be flat non-array
            if ( in_array( 'any', $order_status_filter ) ) $args['status'] = 'any';
        }

        // Search by billing email
        if ( $billing_email_filter ) $args['billing_email'] = $billing_email_filter;
        if ( $customer_billing_email_filter ) $args['billing_email'] = $customer_billing_email_filter;

        // Search by user ID
        if ( $user_id_filter ) $args['customer_id'] = $user_id_filter;

        // Search By Billing Countries
        if ( $billing_country_filter && is_array($billing_country_filter) ) $args['billing_country'] = $billing_country_filter;

        // Search By IP Address
        if ( $ip_address_filter ) {
            // Initialise meta_query if not set
            if ( ! isset( $args['meta_query'] ) ) {
                $args['meta_query'] = [];
            }
            $args['meta_query'][] = array(
                'key'     => '_customer_ip_address',
                'value'   => $ip_address_filter,
                'compare' => is_array($ip_address_filter) ? 'IN' : '='
            );
        } 

        // Search By Order IDs, will ignore filters if set
        if ( $order_ids_filter && is_array($order_ids_filter) ) {
            $args['include'] = $order_ids_filter;
        }

        /**
         *  @todo We should probably use our own get all orders, 
         *  as it fetches in batches in case someone has 100k+ orders
         **/
        // If we are passing an empty array of order_ids, let's assume we are not wanting any data
        $order_ids = ( isset($args['include']) ) ? $args['include'] : (array) wc_get_orders( $args );
        $total_db_records = count( $order_ids );
        // $categorized_data['order_metrics']['order_ids'] = $order_ids;

        // Run in batches
        $batch_size = 2500;
        $offset = 0;
        $total_batches = ceil( wpdai_divide($total_db_records, $batch_size) );

        while( $offset < $total_batches ) {

            // Get the current batch of order IDs
            $current_order_ids_batch = array_slice( $order_ids, $offset * $batch_size, $batch_size );
            
            if ( empty( $current_order_ids_batch ) ) break; // No more orders to process

            // Load the desired calculation cache
            wpdai_setup_order_calculations_in_object_cache( $current_order_ids_batch );
            
            // Loop through order ID's to build organised data and calculate totals
            foreach ( $current_order_ids_batch as $order_id ) {

                // Memory Check
                if ( wpdai_is_memory_usage_greater_than(90) ) {
                    $data_warehouse->set_error(
                        sprintf(
                            /* translators: 1: Number of processed orders, 2: Total number of orders, 3: PHP memory limit */
                            __( 'You\'ve exhausted your memory usage after %1$s out of %2$s orders. Increase your PHP memory limit or reduce the date range. Your current PHP memory limit is %3$s.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                            $total_order_count,
                            $total_db_records,
                            $memory_limit
                        )
                    );
                    break;
                }

                // Calculate order totals via cache
                $order_data = wpdai_calculate_cost_profit_by_order( $order_id );
                $order_data_with_refunds_calculated = $order_data;

                // Convert to non-refunded order, for now
                if ( isset($order_data['total_refund_amount']) && $order_data['total_refund_amount'] > 0 ) {
                    $order_data = wpdai_calculate_cost_profit_by_order( $order_id, true, true );
                }

                // Safety Check
                if ( ! is_array($order_data) ) continue;

                // If were looking at subscriptions, only load subscription renewal orders
                if ( isset( $additional_data['subscriptions'] ) && $additional_data['subscriptions'] ) {
                    if ( $order_data['is_renewal_subscription_order'] != 1 ) continue;
                }

                // Load Acquisition Vars
                $landing_page_url_raw   = $order_data['landing_page_url'];
                $referral_source_url    = $order_data['referral_source_url'];
                $campaign_name          = $order_data['campaign_name'];
                $traffic_type 		    = $order_data['traffic_source'];
                $query_params 		    = wpdai_get_query_params( $landing_page_url_raw );
                $landing_page           = wpdai_strip_params_from_url( $landing_page_url_raw );

                // Transform if required
                if ( empty($traffic_type) ) $traffic_type = 'unknown';
                if ( empty($landing_page) ) $landing_page = 'unknown';
                if ( empty($referral_source_url) ) $referral_source_url = 'unknown';

                /**
                 * 
                 *  Apply filtering for items we can't filter out in the initial request
                 *  @todo ideally get these in the initial request
                 * 
                 **/
                // Traffic Source e.g. Direct, Organic, etc etc..
                if ( $traffic_source_filter && ! in_array( $traffic_type, $traffic_source_filter ) ) continue;
                
                // Device Type e.g. Desktop, Mobile, Tablet, etc etc..
                if ( $device_type_filter && ! in_array( strtolower($order_data['device_type']), $device_type_filter ) ) continue;
                
                // Query Parameter Key-Value Pairs (e.g., utm_campaign=Summer_Sale)
                if ( $query_parameter_values_filter && is_array($query_parameter_values_filter) ) {
                    $has_matching_query_param = false;
                    
                    if ( is_array($query_params) && ! empty($query_params) ) {
                        // Check each filter pair against the order's query parameters
                        foreach ( $query_parameter_values_filter as $filter_pair ) {
                            // Ensure filter pair has both key and value
                            if ( ! isset($filter_pair['key']) || ! isset($filter_pair['value']) ) continue;
                            
                            $filter_key = $filter_pair['key'];
                            $filter_value = $filter_pair['value'];
                            
                            // Check if this key exists in the order's query params and matches the value
                            if ( isset($query_params[$filter_key]) && $query_params[$filter_key] === $filter_value ) {
                                $has_matching_query_param = true;
                                break; // Found a match, no need to check further
                            }
                        }
                    }
                    
                    if ( ! $has_matching_query_param ) continue;
                }

                // Filter by product data if required
                if ( is_array($product_ids_filter) && ! empty($product_ids_filter) ) {
                    
                    // Collect an array of the order's product IDs
                    $order_product_ids = ( isset($order_data['product_data']) && is_array($order_data['product_data'] ) && ! empty($order_data['product_data'])) ? array_keys( $order_data['product_data'] ) : array();
                    
                    // Skip over this entire order, if we are filtering by product ID
                    if ( empty( array_intersect( $product_ids_filter, $order_product_ids ) ) ) continue;

                    // Reset values we need to override
                    $order_data['total_product_revenue_at_rrp']     = 0;
                    $order_data['total_product_revenue']            = 0;
                    $order_data['total_product_revenue_ex_tax']     = 0;
                    $order_data['total_product_discounts']          = 0;
                    $order_data['total_product_discount_percent']   = 0;
                    $order_data['total_product_profit']             = 0;
                    $order_data['total_skus_sold']                  = 0;
                    $order_data['total_product_cost']               = 0;

                    // Will need to adjust all order data values here
                    foreach( $order_data['product_data'] as $product_id => $product_data ) {

                        // Some filtering of non-target product IDs
                        if ( ! in_array( $product_id, $product_ids_filter ) ) {

                            // Remove from array
                            unset( $order_data['product_data'][$product_id] );

                            // Don't update any calculations
                            continue;
                            
                        }

                        // Calculate new values
                        $order_data['total_skus_sold']                  ++;
                        $order_data['total_product_revenue_at_rrp']     += $product_data['product_revenue_at_rrp'];
                        $order_data['total_product_revenue']            += $product_data['product_revenue'];
                        $order_data['total_product_revenue_ex_tax']     += $product_data['product_revenue_excluding_tax'];
                        $order_data['total_product_discounts']          += $product_data['product_discount_amount'];
                        $order_data['total_product_profit']             += $product_data['total_profit'];
                        $order_data['total_product_cost']               += $product_data['total_cost_of_goods'];

                    }

                    // Calculate order product discount
                    $order_data['total_product_discount_percent'] = wpdai_calculate_percentage( $order_data['total_product_discounts'], $order_data['total_product_revenue_at_rrp'] );

                }

                // Filter by product category if required
                if ( $product_category_filter ) {

                    // Used to skip an entire order if required
                    $order_has_target_product_category_or_tag = false;

                    // Reset values we need to override
                    $order_data['total_product_revenue_at_rrp']     = 0;
                    $order_data['total_product_revenue']            = 0;
                    $order_data['total_product_revenue_ex_tax']     = 0;
                    $order_data['total_product_discounts']          = 0;
                    $order_data['total_product_discount_percent']   = 0;
                    $order_data['total_product_profit']             = 0;
                    $order_data['total_skus_sold']                  = 0;
                    $order_data['total_product_cost']               = 0;

                    if ( is_array($order_data['product_data']) && ! empty($order_data['product_data']) ) {

                        foreach( $order_data['product_data'] as $product_id => $product_data ) {

                            $product_data_store = $data_warehouse->get_product_data_cache( $product_id );
                            $product_is_in_target_category = false;

                            if ( is_array($product_data_store['product_category']) && ! empty($product_data_store['product_category']) ) {

                                foreach( $product_data_store['product_category'] as $product_category_taxonomy ) {

                                    // Safety check
                                    if ( ! is_a( $product_category_taxonomy, 'WP_Term' ) ) continue;

                                    // Check if the product category is in the target category
                                    if ( in_array( $product_category_taxonomy->term_id, $product_category_filter ) ) {

                                        // We've hit a target product category
                                        $order_has_target_product_category_or_tag = true;
                                        $product_is_in_target_category = true;

                                        // Calculate new values
                                        $order_data['total_skus_sold']                  ++;
                                        $order_data['total_product_revenue_at_rrp']     += $product_data['product_revenue_at_rrp'];
                                        $order_data['total_product_revenue']            += $product_data['product_revenue'];
                                        $order_data['total_product_revenue_ex_tax']     += $product_data['product_revenue_excluding_tax'];
                                        $order_data['total_product_discounts']          += $product_data['product_discount_amount'];
                                        $order_data['total_product_profit']             += $product_data['total_profit'];
                                        $order_data['total_product_cost']               += $product_data['total_cost_of_goods'];

                                    }

                                }

                            }

                            // Remove the product data if not hit
                            if ( ! $product_is_in_target_category ) unset( $order_data['product_data'][$product_id] );

                        }

                    }

                    // If no targets were hit, skip the entire order
                    if ( ! $order_has_target_product_category_or_tag ) continue;

                    // Any recalculations required
                    $order_data['total_product_discount_percent'] = wpdai_calculate_percentage( $order_data['total_product_discounts'], $order_data['total_product_revenue_at_rrp'] );

                }

                // Filter by product tag if required
                if ( $product_tag_filter ) {

                    // Used to skip an entire order if required
                    $order_has_target_product_tag = false;

                    // Reset values we need to override
                    $order_data['total_product_revenue_at_rrp']     = 0;
                    $order_data['total_product_revenue']            = 0;
                    $order_data['total_product_revenue_ex_tax']     = 0;
                    $order_data['total_product_discounts']          = 0;
                    $order_data['total_product_discount_percent']   = 0;
                    $order_data['total_product_profit']             = 0;
                    $order_data['total_skus_sold']                  = 0;
                    $order_data['total_product_cost']               = 0;

                    if ( is_array($order_data['product_data']) && ! empty($order_data['product_data']) ) {

                        foreach( $order_data['product_data'] as $product_id => $product_data ) {

                            $product_data_store = $data_warehouse->get_product_data_cache( $product_id );
                            $product_is_in_target_tag = false;

                            if ( is_array($product_data_store['product_tags']) && ! empty($product_data_store['product_tags']) ) {

                                foreach( $product_data_store['product_tags'] as $product_tag_taxonomy ) {

                                    // Safety check
                                    if ( ! is_a( $product_tag_taxonomy, 'WP_Term' ) ) continue;

                                    // Check if the product tag is in the target tag
                                    if ( in_array( $product_tag_taxonomy->term_id, $product_tag_filter ) ) {

                                        // We've hit a target product tag
                                        $order_has_target_product_tag = true;
                                        $product_is_in_target_tag = true;

                                        // Calculate new values
                                        $order_data['total_skus_sold']                  ++;
                                        $order_data['total_product_revenue_at_rrp']     += $product_data['product_revenue_at_rrp'];
                                        $order_data['total_product_revenue']            += $product_data['product_revenue'];
                                        $order_data['total_product_revenue_ex_tax']     += $product_data['product_revenue_excluding_tax'];
                                        $order_data['total_product_discounts']          += $product_data['product_discount_amount'];
                                        $order_data['total_product_profit']             += $product_data['total_profit'];
                                        $order_data['total_product_cost']               += $product_data['total_cost_of_goods'];

                                    }

                                }

                            }

                            // Remove the product data if not hit
                            if ( ! $product_is_in_target_tag ) unset( $order_data['product_data'][$product_id] );

                        }

                    }

                    // If no targets were hit, skip the entire order
                    if ( ! $order_has_target_product_tag ) continue;

                    // Any recalculations required
                    $order_data['total_product_discount_percent'] = wpdai_calculate_percentage( $order_data['total_product_discounts'], $order_data['total_product_revenue_at_rrp'] );

                }

                // Total Order Count
                $total_order_count++;

                $orders_data_table_count = is_array($data_table['order_metrics']) ? count($data_table['order_metrics']) : 0;
                if ( $orders_data_table_count < $orders_data_table_limit ) {
                    // Load the main payload into our organised array
                    $data_table['order_metrics'][$order_id] = $order_data_with_refunds_calculated;
                }

                // Store vars that are used in calculations
                $order_revenue 			                    = $order_data['total_order_revenue'];
                $order_revenue_ex_tax                       = $order_data['total_order_revenue_excluding_tax'];
                $order_cost 			                    = $order_data['total_order_cost'];
                $order_profit 			                    = $order_data['total_order_profit'];
                $order_margin 			                    = $order_data['total_order_margin'];
                $payment_gateway 		                    = ( $order_data['payment_gateway'] ) ? $order_data['payment_gateway'] : 'Unknown';

                // Make consecutive totals calculations 
                $total_revenue 					            += $order_revenue;
                $total_order_revenue_ex_tax                 += $order_revenue_ex_tax;
                $total_cost 					            += $order_cost;
                $total_profit 					            += $order_profit;
                $margin_sum 					            += $order_margin;
                $total_shipping_charged 		            += $order_data['total_shipping_charged'];
                $total_shipping_cost 			            += $order_data['total_shipping_cost'];
                $total_product_cost 			            += $order_data['total_product_cost'];
                $total_payment_gateway_costs 	            += $order_data['payment_gateway_cost'];
                $total_tax_owed 				            += $order_data['total_order_tax'];
                $total_tax_collected 			            += $order_data['total_order_tax'];
                $total_qty_sold 				            += $order_data['total_qty_sold'];
                $total_product_revenue 			            += $order_data['total_product_revenue'];
                $total_product_revenue_ex_tax               += $order_data['total_product_revenue_ex_tax'];
                $total_order_revenue_inc_tax_and_refunds    += $order_data['total_order_revenue_inc_tax_and_refunds'];
                $total_product_cost_of_goods 				+= $order_data['total_product_cost_of_goods'];

                // Discounting Data
                $total_product_discounts 		            += (float) $order_data['total_product_discounts'];
                $total_product_revenue_at_rrp 	            += (float) $order_data['total_product_revenue_at_rrp'];
                $total_coupon_discounts                     += (float) $order_data['total_coupon_discounts'];
                $total_order_revenue_before_coupons         += (float) $order_data['total_order_revenue_before_coupons'];
                $total_order_discounts                      += (float) $order_data['total_order_discounts'];
                $total_order_revenue_before_discounts       += (float) $order_data['total_order_revenue_before_discounts'];

                // Accounts for any potential rounding issues
                if ( $order_data['total_order_discounts'] > 0.1 ) $orders_with_discount++;

                // Set highest values
                if ( $order_revenue > $largest_order_revenue ) $largest_order_revenue = $order_revenue;
                if ( $order_cost > $largest_order_cost ) $largest_order_cost = $order_cost;
                if ( $order_profit > $largest_order_profit ) $largest_order_profit = $order_profit;

                // Set payment gateway index & iterate counter
                if ( ! isset($payment_gateway_array[$payment_gateway]) ) $payment_gateway_array[$payment_gateway] = 0; 
                $payment_gateway_array[$payment_gateway]++;

                // Date Range Vars
                $date_created_unix  = $order_data['date_created'];
                $date_range_key     = gmdate( $date_format, $date_created_unix );

                // Tax Data
                if ( $order_data['total_order_tax'] > 0 ) {
                    $totals['tax_metrics']['orders_with_tax']++;
                    $totals['tax_metrics']['total_revenue_where_tax_was_collected'] += $order_data['total_order_revenue'];
                    if( isset($data_by_date['tax_metrics']['taxes_collected_by_date'][$date_range_key]) ) $data_by_date['tax_metrics']['taxes_collected_by_date'][$date_range_key] += $order_data['total_order_tax'];
                }

                // Custom Order Costs
                if ( is_array($order_data['custom_order_cost_data']) && ! empty($order_data['custom_order_cost_data']) ) {

                    foreach( $order_data['custom_order_cost_data'] as $custom_order_cost_slug => $custom_order_cost_value ) {

                        // Only add if its a number
                        if ( is_numeric($custom_order_cost_value) && $custom_order_cost_value > 0 ) {

                            // Get the clean label
                            $custom_order_cost_label = ( isset($custom_order_cost_options[$custom_order_cost_slug]) ) ? $custom_order_cost_options[$custom_order_cost_slug]['label'] : $custom_order_cost_slug;

                            // Setup default variable
                            if ( ! isset($custom_order_cost_data[$custom_order_cost_label]) ) $custom_order_cost_data[$custom_order_cost_label] = 0;

                            // Add to total
                            $custom_order_cost_data[$custom_order_cost_label] += $custom_order_cost_value;

                            // Add to total
                            $total_custom_order_costs += $custom_order_cost_value;

                        }

                    }

                }

                // Total Custom Product Costs
                if ( is_array($order_data['custom_product_cost_data']) && ! empty($order_data['custom_product_cost_data']) ) {

                    foreach( $order_data['custom_product_cost_data'] as $custom_product_cost_slug => $custom_product_cost_data ) {

                        // Only add if its a number
                        if ( is_numeric($custom_product_cost_data['total_value']) && $custom_product_cost_data['total_value'] > 0 ) {

                            // Setup default variable
                            if ( ! isset($custom_order_cost_data[$custom_product_cost_data['label']]) ) $custom_order_cost_data[$custom_product_cost_data['label']] = 0;

                            // Add to total
                            $custom_order_cost_data[$custom_product_cost_data['label']] += $custom_product_cost_data['total_value'];

                            // Increment Total
                            $total_custom_product_costs += (float) $custom_product_cost_data['total_value'];

                        }

                    }

                }

                // Inside the order loop, after processing the order:
                $gateway_id = $payment_gateway;
                $gateway_title = $payment_gateway;
                
                // Initialize gateway data if not exists
                if (!isset($payment_gateway_data[$payment_gateway])) {
                    $payment_gateway_data[$gateway_id] = array(
                        'title' => $gateway_title,
                        'order_count' => 0,
                        'revenue' => 0,
                        'gateway_fees' => 0,
                        'percent_of_orders' => 0,
                        'percent_of_revenue' => 0,
                        'average_order_value' => 0
                    );
                }
                // Update gateway metrics
                $payment_gateway_data[$gateway_id]['order_count']++;
                $payment_gateway_data[$gateway_id]['revenue'] += $order_revenue;
                $payment_gateway_data[$gateway_id]['gateway_fees'] += $order_data['payment_gateway_cost'];

                // Order status data
                if ( ! isset($categorized_data['order_metrics']['order_status_data'][$order_data['order_status']]) ) $categorized_data['order_metrics']['order_status_data'][$order_data['order_status']] = $default_order_summary;
                $categorized_data['order_metrics']['order_status_data'][$order_data['order_status']]['total_revenue'] += $order_revenue;
                $categorized_data['order_metrics']['order_status_data'][$order_data['order_status']]['total_cost'] += $order_cost;
                $categorized_data['order_metrics']['order_status_data'][$order_data['order_status']]['total_profit'] += $order_profit;
                $categorized_data['order_metrics']['order_status_data'][$order_data['order_status']]['total_order_count']++;

                /**
                 * 
                 *  Additional Data For Orders Report
                 * 
                 **/
                // Date Keys
                $day_of_week_key    = gmdate( 'D', $date_created_unix );
                $hour_of_day_key    = gmdate( 'ga', $date_created_unix );

                // Add Date Range Values
                if( isset($data_by_date['order_metrics']['order_count_by_date'][$date_range_key]) ) $data_by_date['order_metrics']['order_count_by_date'][$date_range_key]++;

                // Pre refunded sales, we just dont subtract refunds from this figure
                if ( isset($data_by_date['order_metrics']['gross_revenue_inc_tax_by_date'][$date_range_key]) ) $data_by_date['order_metrics']['gross_revenue_inc_tax_by_date'][$date_range_key] += $order_revenue;
                // Pre-Refund Sales, then subtract refunds so the dates are correct
                if( isset($data_by_date['order_metrics']['revenue_by_date'][$date_range_key]) ) $data_by_date['order_metrics']['revenue_by_date'][$date_range_key] += $order_revenue;
                if( isset($data_by_date['order_metrics']['revenue_excluding_tax_by_date'][$date_range_key]) ) $data_by_date['order_metrics']['revenue_excluding_tax_by_date'][$date_range_key] += $order_revenue_ex_tax;
                if( isset($data_by_date['order_metrics']['profit_by_date'][$date_range_key]) ) $data_by_date['order_metrics']['profit_by_date'][$date_range_key] += $order_profit;
                
                if( isset($data_by_date['order_metrics']['average_order_value_by_date'][$date_range_key]) ) $data_by_date['order_metrics']['average_order_value_by_date'][$date_range_key] = wpdai_divide( $data_by_date['order_metrics']['revenue_by_date'][$date_range_key], $data_by_date['order_metrics']['order_count_by_date'][$date_range_key], 2 );
                if( isset($data_by_date['order_metrics']['average_order_margin_by_date'][$date_range_key]) ) $data_by_date['order_metrics']['average_order_margin_by_date'][$date_range_key] = wpdai_calculate_margin( $data_by_date['order_metrics']['profit_by_date'][$date_range_key], $data_by_date['order_metrics']['revenue_excluding_tax_by_date'][$date_range_key] );

                // Daily Data
                if( isset($categorized_data['order_metrics']['revenue_by_day_of_week'][$day_of_week_key]) ) $categorized_data['order_metrics']['revenue_by_day_of_week'][$day_of_week_key] += $order_revenue;
                if( isset($categorized_data['order_metrics']['profit_by_day_of_week'][$day_of_week_key]) ) $categorized_data['order_metrics']['profit_by_day_of_week'][$day_of_week_key] += $order_profit;
                if( isset($categorized_data['order_metrics']['revenue_by_hour_of_day'][$hour_of_day_key]) ) $categorized_data['order_metrics']['revenue_by_hour_of_day'][$hour_of_day_key] += $order_revenue;
                if( isset($categorized_data['order_metrics']['profit_by_hour_of_day'][$hour_of_day_key]) ) $categorized_data['order_metrics']['profit_by_hour_of_day'][$hour_of_day_key] += $order_profit;


                /**
                 * 
                 * Additional Data For Products Report
                 *  
                 **/
                $largest_product_count_sold_per_order = ( $largest_product_count_sold_per_order < count( array_keys( $order_data['product_data'] ) ) ) ? count( array_keys( $order_data['product_data'] ) ) : $largest_product_count_sold_per_order; // New

                // Data By Date
                if( isset($data_by_date['product_metrics']['product_revenue_by_date'][$date_range_key]) ) $data_by_date['product_metrics']['product_revenue_by_date'][$date_range_key] += $order_data['total_product_revenue'];
                if( isset($data_by_date['product_metrics']['quantity_sold_by_date'][$date_range_key]) ) $data_by_date['product_metrics']['quantity_sold_by_date'][$date_range_key] += $order_data['total_qty_sold'];


                /**
                 * 
                 *  Additional Data For Acquisitions Report
                 * 
                 **/
                // Default containers
                if ( ! isset($categorized_data['order_metrics']['acquisition_traffic_type'][$traffic_type]) ) $categorized_data['order_metrics']['acquisition_traffic_type'][$traffic_type] = $default_order_summary;
                if ( ! isset($categorized_data['order_metrics']['acquisition_landing_page'][$landing_page]) ) $categorized_data['order_metrics']['acquisition_landing_page'][$landing_page] = $default_order_summary;
                if ( ! isset($categorized_data['order_metrics']['acquisition_referral_source'][$referral_source_url]) ) $categorized_data['order_metrics']['acquisition_referral_source'][$referral_source_url] = $default_order_summary;
                if ( ! isset($categorized_data['order_metrics']['acquisition_campaign_name'][$campaign_name]) ) $categorized_data['order_metrics']['acquisition_campaign_name'][$campaign_name] = $default_order_summary;
                // Traffic Type
                $categorized_data['order_metrics']['acquisition_traffic_type'][$traffic_type]['total_revenue']         += $order_revenue;
                $categorized_data['order_metrics']['acquisition_traffic_type'][$traffic_type]['total_cost']            += $order_cost;
                $categorized_data['order_metrics']['acquisition_traffic_type'][$traffic_type]['total_profit']          += $order_profit;
                $categorized_data['order_metrics']['acquisition_traffic_type'][$traffic_type]['total_order_count']++;

                // Landing Page
                $categorized_data['order_metrics']['acquisition_landing_page'][$landing_page]['total_revenue']         += $order_revenue;
                $categorized_data['order_metrics']['acquisition_landing_page'][$landing_page]['total_cost']            += $order_cost;
                $categorized_data['order_metrics']['acquisition_landing_page'][$landing_page]['total_profit']          += $order_profit;
                $categorized_data['order_metrics']['acquisition_landing_page'][$landing_page]['total_order_count']++;

                // Referral Source
                $categorized_data['order_metrics']['acquisition_referral_source'][$referral_source_url]['total_revenue']         += $order_revenue;
                $categorized_data['order_metrics']['acquisition_referral_source'][$referral_source_url]['total_cost']            += $order_cost;
                $categorized_data['order_metrics']['acquisition_referral_source'][$referral_source_url]['total_profit']          += $order_profit;
                $categorized_data['order_metrics']['acquisition_referral_source'][$referral_source_url]['total_order_count']++;

                // Campaign Name
                $categorized_data['order_metrics']['acquisition_campaign_name'][$campaign_name]['total_revenue']         += $order_revenue;
                $categorized_data['order_metrics']['acquisition_campaign_name'][$campaign_name]['total_cost']            += $order_cost;
                $categorized_data['order_metrics']['acquisition_campaign_name'][$campaign_name]['total_profit']          += $order_profit;
                $categorized_data['order_metrics']['acquisition_campaign_name'][$campaign_name]['total_order_count']++;
                
                // Query Parameters
                if ( is_array($query_params) && ! empty($query_params) ) {

                    // Loop through query param array
                    foreach( $query_params as $key => $value ) {

                        // Transform as required
                        $key = ( ! empty($key) ) ? $key : 'unset';
                        $value = ( ! empty($value) ) ? $value : 'unset';

                        // Defaults
                        if ( ! isset($categorized_data['order_metrics']['acquisition_query_parameter_keys'][$key]) ) $categorized_data['order_metrics']['acquisition_query_parameter_keys'][$key] = $default_order_summary;
                        if ( ! isset($categorized_data['order_metrics']['acquisition_query_parameter_values'][$value]) ) $categorized_data['order_metrics']['acquisition_query_parameter_values'][$value] = $default_order_summary;

                        // Keys
                        $categorized_data['order_metrics']['acquisition_query_parameter_keys'][$key]['total_revenue']         += $order_revenue;
                        $categorized_data['order_metrics']['acquisition_query_parameter_keys'][$key]['total_cost']            += $order_cost;
                        $categorized_data['order_metrics']['acquisition_query_parameter_keys'][$key]['total_profit']          += $order_profit;
                        $categorized_data['order_metrics']['acquisition_query_parameter_keys'][$key]['total_order_count']++;

                        // Values
                        $categorized_data['order_metrics']['acquisition_query_parameter_values'][$value]['total_revenue']         += $order_revenue;
                        $categorized_data['order_metrics']['acquisition_query_parameter_values'][$value]['total_cost']            += $order_cost;
                        $categorized_data['order_metrics']['acquisition_query_parameter_values'][$value]['total_profit']          += $order_profit;
                        $categorized_data['order_metrics']['acquisition_query_parameter_values'][$value]['total_order_count']++;

                    }

                }

                // Remove the no data available container
                if ( isset($data_by_date['order_metrics']['revenue_by_traffic_type_by_date']['no_data_available']) ) $data_by_date['order_metrics']['revenue_by_traffic_type_by_date'] = array();

                // Daily Data - Setup date container for traffic type & enter data
                if ( ! isset($data_by_date['order_metrics']['revenue_by_traffic_type_by_date'][$traffic_type]) ) $data_by_date['order_metrics']['revenue_by_traffic_type_by_date'][$traffic_type] = $data_warehouse->get_data_by_date_range_container();
                if ( isset($data_by_date['order_metrics']['revenue_by_traffic_type_by_date'][$traffic_type][$date_range_key]) ) $data_by_date['order_metrics']['revenue_by_traffic_type_by_date'][$traffic_type][$date_range_key] += $order_revenue;


                /**
                 * 
                 *  Additional Data For Customers Report
                 * 
                 **/
                // Collect Vars
                $user_id                = ( ! empty( $order_data['user_id'] ) ) ? $order_data['user_id'] : $order_data['user_id'];
                $is_registered_user     = $order_data['is_registered_user']; // 1/0
                $new_customer           = $order_data['new_returning_customer'];   // new / returning
                $billing_first_name     = ( ! empty( $order_data['billing_first_name'] ) ) ? $order_data['billing_first_name'] : 'Unknown';
                $billing_last_name      = ( ! empty( $order_data['billing_last_name'] ) ) ? $order_data['billing_last_name'] : 'Unknown';
                $billing_email          = ( ! empty( $order_data['billing_email'] ) ) ?  $order_data['billing_email'] : 'Unknown';
                $billing_phone          = ( ! empty( $order_data['billing_phone'] ) ) ?  $order_data['billing_phone'] : 'Unknown';
                
                // Store unique billing email addresses
                $unique_counter['unique_customers_by_email'][$billing_email] = true;
                
                // Get clean country and state names (performance optimized)
                $billing_country_code   = ( ! empty( $order_data['billing_country'] ) ) ? $order_data['billing_country'] : 'Unknown';
                $billing_state_code     = ( ! empty( $order_data['billing_state'] ) ) ? $order_data['billing_state'] : 'Unknown';
                
                // Get clean country name
                $billing_country        = ( isset( WC()->countries->countries[$billing_country_code] ) ) ? WC()->countries->countries[$billing_country_code] : $billing_country_code;
                
                // Get clean state name (only if country is valid)
                $billing_state          = $billing_state_code;
                if ( $billing_country_code !== 'Unknown' && isset( WC()->countries->countries[$billing_country_code] ) ) {
                    $state_names = (array) WC()->countries->get_states( $billing_country_code );
                    if ( isset( $state_names[$billing_state_code] ) ) {
                        $billing_state = $state_names[$billing_state_code];
                    }
                }
                
                $billing_postcode       = ( ! empty( $order_data['billing_postcode'] ) ) ? $order_data['billing_postcode'] : 'Unknown';
                $billing_company        = ( ! empty( $order_data['billing_company'] ) ) ? $order_data['billing_company'] : 'Unknown';
                $device_type            = ( isset( $order_data['device_type'] ) ) ? $order_data['device_type'] : 'Unknown';
                $device_browser         = ( isset( $order_data['device_browser'] ) ) ? $order_data['device_browser'] : 'Unknown';

                // Track unique customers
                if ( ! in_array($billing_email, $unique_customer_daily_data_tracking) ) {
                    $unique_customer_daily_data_tracking[] = $billing_email;
                    if ( isset($data_by_date['customer_metrics']['unique_customer_orders_by_date'][$date_range_key]) ) $data_by_date['customer_metrics']['unique_customer_orders_by_date'][$date_range_key]++;
                }

                // Billing Location -> Setup default country data
                if ( ! isset($categorized_data['customer_metrics']['country_location_data'][$billing_country]) ) {

                    $categorized_data['customer_metrics']['country_location_data'][$billing_country] = $default_order_summary;
                    $categorized_data['customer_metrics']['country_location_data'][$billing_country]['customers'] = array();
                    $categorized_data['customer_metrics']['country_location_data'][$billing_country]['customer_count'] = 0;

                }
                // Billing Location -> Setup default state data
                if ( ! isset($categorized_data['customer_metrics']['state_location_data'][$billing_state]) ) {
                    $categorized_data['customer_metrics']['state_location_data'][$billing_state] = $default_order_summary;
                    $categorized_data['customer_metrics']['state_location_data'][$billing_state]['customers'] = array();
                    $categorized_data['customer_metrics']['state_location_data'][$billing_state]['customer_count'] = 0;
                } 
                
                // Billing Location -> Calculate Info (Country)
                $categorized_data['customer_metrics']['country_location_data'][$billing_country]['total_revenue'] += $order_revenue;
                $categorized_data['customer_metrics']['country_location_data'][$billing_country]['total_cost'] += $order_cost;
                $categorized_data['customer_metrics']['country_location_data'][$billing_country]['total_profit'] += $order_profit;
                $categorized_data['customer_metrics']['country_location_data'][$billing_country]['total_order_count']++;

                // Billing Location -> Calculate Info (Country)
                $categorized_data['customer_metrics']['state_location_data'][$billing_state]['total_revenue'] += $order_revenue;
                $categorized_data['customer_metrics']['state_location_data'][$billing_state]['total_cost'] += $order_cost;
                $categorized_data['customer_metrics']['state_location_data'][$billing_state]['total_profit'] += $order_profit;
                $categorized_data['customer_metrics']['state_location_data'][$billing_state]['total_order_count']++;

                // Customer count
                if ( ! in_array($billing_email, $categorized_data['customer_metrics']['country_location_data'][$billing_country]['customers']) ) {
                    $categorized_data['customer_metrics']['country_location_data'][$billing_country]['customers'][] = $billing_email;
                    $categorized_data['customer_metrics']['country_location_data'][$billing_country]['customer_count']++;
                }

                // Customer count
                if ( ! in_array($billing_email, $categorized_data['customer_metrics']['state_location_data'][$billing_state]['customers']) ) {
                    $categorized_data['customer_metrics']['state_location_data'][$billing_state]['customers'][] = $billing_email;
                    $categorized_data['customer_metrics']['state_location_data'][$billing_state]['customer_count']++;
                }

                // Device Data -> Browser
                if ( ! isset($categorized_data['customer_metrics']['device_browser_data'][$device_browser]) ) $categorized_data['customer_metrics']['device_browser_data'][$device_browser] = $default_order_summary;
                $categorized_data['customer_metrics']['device_browser_data'][$device_browser]['total_revenue'] += $order_revenue;
                $categorized_data['customer_metrics']['device_browser_data'][$device_browser]['total_cost'] += $order_cost;
                $categorized_data['customer_metrics']['device_browser_data'][$device_browser]['total_profit'] += $order_profit;
                $categorized_data['customer_metrics']['device_browser_data'][$device_browser]['total_order_count']++;

                // Device Data -> Device Type
                if ( ! isset($categorized_data['customer_metrics']['device_type_data'][$device_type]) ) $categorized_data['customer_metrics']['device_type_data'][$device_type] = $default_order_summary;
                $categorized_data['customer_metrics']['device_type_data'][$device_type]['total_revenue'] += $order_revenue;
                $categorized_data['customer_metrics']['device_type_data'][$device_type]['total_cost'] += $order_cost;
                $categorized_data['customer_metrics']['device_type_data'][$device_type]['total_profit'] += $order_profit;
                $categorized_data['customer_metrics']['device_type_data'][$device_type]['total_order_count']++;

                // New vs Returning
                if ( $new_customer == 'new' ) {

                    // Summary Data
                    $categorized_data['customer_metrics']['new_vs_returning_data']['new_customer']['distinct_count'][] = $billing_email;
                    $categorized_data['customer_metrics']['new_vs_returning_data']['new_customer']['total_revenue'] += $order_revenue;
                    $categorized_data['customer_metrics']['new_vs_returning_data']['new_customer']['total_cost'] += $order_cost;
                    $categorized_data['customer_metrics']['new_vs_returning_data']['new_customer']['total_profit'] += $order_profit;
                    $categorized_data['customer_metrics']['new_vs_returning_data']['new_customer']['total_order_count']++;

                    // Daily data
                    if ( isset($data_by_date['customer_metrics']['new_customer_orders_by_date'][$date_range_key]) ) $data_by_date['customer_metrics']['new_customer_orders_by_date'][$date_range_key]++;
                
                } elseif ( $new_customer == 'returning' ) {

                    // Summary Data
                    $categorized_data['customer_metrics']['new_vs_returning_data']['returning_customer']['distinct_count'][] = $billing_email;
                    $categorized_data['customer_metrics']['new_vs_returning_data']['returning_customer']['total_revenue'] += $order_revenue;
                    $categorized_data['customer_metrics']['new_vs_returning_data']['returning_customer']['total_cost'] += $order_cost;
                    $categorized_data['customer_metrics']['new_vs_returning_data']['returning_customer']['total_profit'] += $order_profit;
                    $categorized_data['customer_metrics']['new_vs_returning_data']['returning_customer']['total_order_count']++;

                    // Daily Data
                    if ( isset($data_by_date['customer_metrics']['returning_customer_orders_by_date'][$date_range_key]) ) $data_by_date['customer_metrics']['returning_customer_orders_by_date'][$date_range_key]++;
                
                }

                // Guest vs Registered
                if ( $is_registered_user ) {

                    // Summary Data
                    $categorized_data['customer_metrics']['guest_vs_registered_data']['registered_customer']['distinct_count'][] = $billing_email;
                    $categorized_data['customer_metrics']['guest_vs_registered_data']['registered_customer']['total_revenue'] += $order_revenue;
                    $categorized_data['customer_metrics']['guest_vs_registered_data']['registered_customer']['total_cost'] += $order_cost;
                    $categorized_data['customer_metrics']['guest_vs_registered_data']['registered_customer']['total_profit'] += $order_profit;
                    $categorized_data['customer_metrics']['guest_vs_registered_data']['registered_customer']['total_order_count']++;

                    // Daily Data
                    if ( isset($data_by_date['customer_metrics']['registered_customer_orders_by_date'][$date_range_key]) ) $data_by_date['customer_metrics']['registered_customer_orders_by_date'][$date_range_key]++;

                } else {

                    // Summary Data
                    $categorized_data['customer_metrics']['guest_vs_registered_data']['guest_customer']['distinct_count'][] = $billing_email;
                    $categorized_data['customer_metrics']['guest_vs_registered_data']['guest_customer']['total_revenue'] += $order_revenue;
                    $categorized_data['customer_metrics']['guest_vs_registered_data']['guest_customer']['total_cost'] += $order_cost;
                    $categorized_data['customer_metrics']['guest_vs_registered_data']['guest_customer']['total_profit'] += $order_profit;
                    $categorized_data['customer_metrics']['guest_vs_registered_data']['guest_customer']['total_order_count']++;

                    // Daily Data
                    if ( isset($data_by_date['customer_metrics']['guest_customer_orders_by_date'][$date_range_key]) ) $data_by_date['customer_metrics']['guest_customer_orders_by_date'][$date_range_key]++;

                }

                $customers_data_table_count = is_array($data_table['customer_metrics']) ? count($data_table['customer_metrics']) : 0;  
                if ( $customers_data_table_count < $customers_data_table_limit ) {
                
                    // Customer Data -> Setup Default Container
                    if ( ! isset($data_table['customer_metrics'][$billing_email]) ) {
                        $data_table['customer_metrics'][$billing_email] = array(
                            'user_id'               => $user_id,
                            'billing_email'         => $billing_email,
                            'billing_phone'         => $billing_phone,
                            'billing_first_name'    => $billing_first_name,
                            'billing_last_name'     => $billing_last_name,
                            'billing_country'       => $billing_country,
                            'billing_state'         => $billing_state,
                            'billing_postcode' 		=> $billing_postcode,
                            'billing_company' 		=> $billing_company,
                            'is_registered_user'    => $is_registered_user,
                            'total_revenue'         => 0,
                            'total_cost'            => 0,
                            'total_profit'          => 0,
                            'total_order_count'     => 0,
                            'margin_percentage'     => 0,
                            'average_order_value'   => 0,
                            'percent_of_revenue'    => 0,
                            'refund_count'          => 0,
                            'refund_value'          => 0,
                            'refund_rate'           => 0
                        );
                    }

                    // Make Calculations
                    $data_table['customer_metrics'][$billing_email]['total_revenue'] += $order_revenue;
                    $data_table['customer_metrics'][$billing_email]['total_cost'] += $order_cost;
                    $data_table['customer_metrics'][$billing_email]['total_profit'] += $order_profit;
                    $data_table['customer_metrics'][$billing_email]['total_order_count'] ++;
                    // refund_count, refund_value, refund_rate populated from refunds_internal in Customer Calculations below

                }

                /**
                 * 
                 *  Additional Data for Coupons Report
                 * 
                 **/
                // Coupon has been used
                if ( is_array($order_data['coupons_used']) && ! empty($order_data['coupons_used']) ) {

                    $totals['coupon_metrics']['orders_with_coupons']++;
                    $totals['coupon_metrics']['total_revenue_with_coupons']  += $order_revenue;
                    $totals['coupon_metrics']['total_cost_with_coupons']     += $order_cost;
                    $totals['coupon_metrics']['total_profit_with_coupons']   += $order_profit;
                    $categorized_data['coupon_metrics']['order_ids'][] = $order_id;

                    // Categorized Data
                    $categorized_data['coupon_metrics']['orders_with_and_without_coupons']['orders_with_coupons']['total_revenue'] += $order_revenue;
                    $categorized_data['coupon_metrics']['orders_with_and_without_coupons']['orders_with_coupons']['total_cost'] += $order_cost;
                    $categorized_data['coupon_metrics']['orders_with_and_without_coupons']['orders_with_coupons']['total_profit'] += $order_profit;
                    $categorized_data['coupon_metrics']['orders_with_and_without_coupons']['orders_with_coupons']['total_order_count']++;

                    // Orders With Coupon By Date
                    if ( isset($data_by_date['coupon_metrics']['orders_with_coupon_by_date'][$date_range_key]) ) $data_by_date['coupon_metrics']['orders_with_coupon_by_date'][$date_range_key]++;

                    // Loop through order coupons
                    foreach( $order_data['coupons_used'] as $coupon_item_id => $coupon_data ) {

                        // Code
                        $coupon_code = $coupon_data['coupon_code'];

                        // Discount Amount By Date
                        if ( isset($data_by_date['coupon_metrics']['coupon_discount_amount_by_date'][$date_range_key]) ) $data_by_date['coupon_metrics']['coupon_discount_amount_by_date'][$date_range_key] += (float) $coupon_data['discount_amount'];

                        // Totals
                        $totals['coupon_metrics']['total_coupons_used']++;
                        $totals['coupon_metrics']['total_coupon_quantity_used']      += (int) $coupon_data['quantity_applied'];
                        $totals['coupon_metrics']['total_discount_amount']           += (float) $coupon_data['discount_amount'];
                        $totals['coupon_metrics']['total_discount_amount_tax']       += (float) $coupon_data['discount_amount_tax_only'];
                        $totals['coupon_metrics']['total_discount_amount_ex_tax']    += (float) $coupon_data['discount_amount_ex_tax'];

                        // Coupon not set yet
                        if ( ! isset($data_table['coupon_metrics'][$coupon_code]) ) {
                            $data_table['coupon_metrics'][$coupon_code] = array(

                                'coupon_code'                   => $coupon_code,
                                'coupon_name'                   => $coupon_data['coupon_name'],
                                'discount_amount_ex_tax'        => 0,
                                'discount_amount_tax_only'      => 0,
                                'discount_amount'               => 0,
                                'total_orders_applied'          => 0,
                                'percent_of_orders_applied'     => 0, // Calculate Later
                                'percent_of_orders_where_coupon_used' => 0, // Calculate Later
                                'total_quantity_applied'        => 0,
                                'total_customers_applied'       => 0, // Calculate later
                                'total_revenue'                 => 0,
                                'total_cost'                    => 0,
                                'total_profit'                  => 0,
                                'average_margin'                => 0, // Calculate Later
                                'customers_by_email_address'    => array(), // Unique Later
                                'order_ids'                     => array(), // Unique Later
                                'coupon_id'                     => 'Unknown',
                                'discount_type'                 => 'Unknown',
                                'discount_type_amount'          => 'Unknown',
                                'description'                   => 'Unknown',
                                'total_usage_count'             => 'Unknown'

                            );

                        }

                        // Calculations
                        $data_table['coupon_metrics'][$coupon_code]['total_orders_applied']++;
                        $data_table['coupon_metrics'][$coupon_code]['discount_amount_ex_tax']     += (float) $coupon_data['discount_amount_ex_tax'];
                        $data_table['coupon_metrics'][$coupon_code]['discount_amount_tax_only']   += (float) $coupon_data['discount_amount_tax_only'];
                        $data_table['coupon_metrics'][$coupon_code]['discount_amount']            += (float) $coupon_data['discount_amount'];
                        $data_table['coupon_metrics'][$coupon_code]['total_quantity_applied']     += (int) $coupon_data['quantity_applied'];
                        $data_table['coupon_metrics'][$coupon_code]['total_revenue']              += $order_revenue;
                        $data_table['coupon_metrics'][$coupon_code]['total_cost']                 += $order_cost;
                        $data_table['coupon_metrics'][$coupon_code]['total_profit']               += $order_profit;

                        // Push data into array
                        $billing_email = ( ! empty( $order_data['billing_email'] ) ) ?  $order_data['billing_email'] : 'Unknown';
                        $data_table['coupon_metrics'][$coupon_code]['customers_by_email_address'][] = $billing_email;
                        $data_table['coupon_metrics'][$coupon_code]['order_ids'][] = $order_id;
                        
                    }

                } else {

                    // Categorized Data
                    $categorized_data['coupon_metrics']['orders_with_and_without_coupons']['orders_without_coupons']['total_revenue'] += $order_revenue;
                    $categorized_data['coupon_metrics']['orders_with_and_without_coupons']['orders_without_coupons']['total_cost'] += $order_cost;
                    $categorized_data['coupon_metrics']['orders_with_and_without_coupons']['orders_without_coupons']['total_profit'] += $order_profit;
                    $categorized_data['coupon_metrics']['orders_with_and_without_coupons']['orders_without_coupons']['total_order_count']++;

                }

                /**
                 * 
                 *  Handle Product Specific Information
                 * 
                 **/
                if ( isset($order_data['product_data'] ) && is_array($order_data['product_data']) ) {

                    // Loop through each product in the order payload, product_id is variation id if set
                    foreach( $order_data['product_data'] as $product_id => $product_data ) {
                        
                        // Required for all reports
                        $unique_sku_array[] = $product_data['product_id'];
                        $total_product_line_items_sold++;

                        /**
                         * 
                         *  Additional Product Data
                         * 
                         **/
                        // Get additional Product Data
                        $product_data_store = $data_warehouse->get_product_data_cache( $product_id );

                        // Load up the product array if it hasn't been used yet
                        if ( ! isset($product_item_data[$product_id]) ) {

                            $product_item_data[$product_id] = array(

                                'product_display'                       => $data_warehouse->get_product_html_display_badge( $product_id ), // Html representation of product
                                'product_id'                            => $product_id,
                                'parent_id'                             => $product_data_store['parent_id'],
                                'product_image'                         => '<img src="'.$product_data_store['product_image'].'" class="wpd-product-thumbnail">',
                                'product_view_link'                     => $product_data_store['product_link'],
                                'product_name'                          => $product_data_store['product_name'],
                                'product_sku'                           => $product_data_store['product_sku'],
                                'product_type'                          => $product_data_store['product_type'],
                                'product_rrp'                           => $product_data_store['product_rrp'],
                                'product_cost_price'                    => $product_data_store['product_cost_price'],
                                'total_product_revenue_value_rrp'       => 0,
                                'total_product_revenue'                 => 0,
                                'total_product_revenue_excluding_tax'   => 0,
                                'total_quantity_sold'                   => 0,
                                'total_times_sold'                      => 0,
                                'total_product_cost'                    => 0,
                                'total_product_profit'                  => 0,
                                'total_product_coupons_applied'         => 0,
                                'total_product_discount_amount'         => 0,
                                'total_quantity_refunded'               => 0,
                                'total_times_refunded'                  => 0,
                                'total_refund_amount'                   => 0,
                                'refund_rate'                           => 0,
                                'purchase_rate'                         => 0,
                                'average_margin'                        => 0,
                                'average_margin_sum'                    => 0,
                                'average_product_discount_percent'      => 0,
                                'average_product_discount_percent_sum'  => 0,
                                'average_sell_price'                    => 0,
                                'average_sell_price_sum'                => 0,
                                'product_category'                      => ( $product_data_store['product_category'] ) ? $product_data_store['product_category'] : array(),
                                'product_tags'                          => ( $product_data_store['product_tags'] ) ? $product_data_store['product_tags'] : array()

                            );

                        }

                        // Any vars used for calculations (refund fields populated from refunds_internal below)
                        $product_profit_at_rrp      = $product_data['product_revenue_at_rrp'] - $product_data['total_cost_of_goods'];

                        // Make Product Calculations
                        $product_item_data[$product_id]['total_times_sold']++;
                        $product_item_data[$product_id]['total_product_revenue_value_rrp']       += $product_data['product_revenue_at_rrp'];
                        $product_item_data[$product_id]['total_product_revenue']                 += $product_data['product_revenue'];
                        $product_item_data[$product_id]['total_quantity_sold']                   += $product_data['qty_sold'];
                        $product_item_data[$product_id]['total_product_cost']                    += $product_data['total_cost_of_goods'];
                        $product_item_data[$product_id]['total_product_profit']                  += $product_data['total_profit'];
                        $product_item_data[$product_id]['total_product_coupons_applied']         += $product_data['coupon_discount_amount'];
                        $product_item_data[$product_id]['total_product_discount_amount']         += $product_data['product_discount_amount'];
                        $product_item_data[$product_id]['average_margin_sum']                    += $product_data['product_margin'];
                        $product_item_data[$product_id]['average_product_discount_percent_sum']  += $product_data['product_discount_percentage'];
                        $product_item_data[$product_id]['average_sell_price_sum']                += $product_data['product_revenue_per_unit'];
                        $product_item_data[$product_id]['total_product_revenue_excluding_tax']   += $product_data['product_revenue_excluding_tax'];

                        // Totals (refund totals updated when merging refund_metrics into product_item_data below)
                        $total_product_profit_at_rrp                += $product_profit_at_rrp;
                        $total_product_profit                       += $product_data['total_profit'];
                        
                        // Highest Count
                        if ( $product_data['qty_sold'] > $largest_quantity_sold_per_order ) $largest_quantity_sold_per_order = $product_data['qty_sold'];

                        // Product Type Defaults
                        if ( ! isset($product_type_data[$product_data_store['product_type']]) ) {

                            $product_type_data[$product_data_store['product_type']] = array(

                                'total_revenue'       => 0,
                                'total_profit'        => 0,
                                'total_quantity_sold'  => 0,
                                'unique_products_sold' => 0

                            );

                        }

                        // Product Type Calculations
                        $product_type_data[$product_data_store['product_type']]['unique_products_sold']++;
                        $product_type_data[$product_data_store['product_type']]['total_revenue']      += $product_data['product_revenue'];
                        $product_type_data[$product_data_store['product_type']]['total_profit']       += $product_data['total_profit'];
                        $product_type_data[$product_data_store['product_type']]['total_quantity_sold']     += $product_data['qty_sold'];

                        // Product Category Calculations
                        if ( is_array( $product_data_store['product_category'] ) && ! empty($product_data_store['product_category']) ) {

                            // Loop through product categories for this product
                            foreach( $product_data_store['product_category'] as $product_category_object ) {

                                // Safety Check
                                if ( ! is_a( $product_category_object, 'WP_Term' ) ) continue;

                                // Product Category Defaults
                                if ( ! isset($product_cat_data[$product_category_object->name]) ) {

                                    $product_cat_data[$product_category_object->name] = array(
                                        'total_revenue'         => 0,
                                        'total_profit'          => 0,
                                        'total_quantity_sold'   => 0,
                                        'unique_products_sold' => 0
                                    );

                                }

                                // Product Category Calculations
                                $product_cat_data[$product_category_object->name]['unique_products_sold']++;
                                $product_cat_data[$product_category_object->name]['total_revenue'] 	    += $product_data['product_revenue'];
                                $product_cat_data[$product_category_object->name]['total_profit'] 			+= $product_data['total_profit'];
                                $product_cat_data[$product_category_object->name]['total_quantity_sold'] 		+= $product_data['qty_sold'];

                            }

                        }

                        // Product Tag Calculations
                        if ( is_array( $product_data_store['product_tags'] ) && ! empty($product_data_store['product_tags']) ) {

                            // Loop through product tags for this product
                            foreach( $product_data_store['product_tags'] as $product_tag_object ) {

                                // Safety Check
                                if ( ! is_a( $product_tag_object, 'WP_Term' ) ) continue;

                                // Product tags Defaults
                                if ( ! isset($product_tag_data[$product_tag_object->name]) ) {

                                    $product_tag_data[$product_tag_object->name] = array(
                                        'total_revenue'       => 0,
                                        'total_profit'        => 0,
                                        'total_quantity_sold'      => 0,
                                        'unique_products_sold' => 0,
                                    );

                                }

                                // Product Tag Calculations
                                $product_tag_data[$product_tag_object->name]['unique_products_sold']++;
                                $product_tag_data[$product_tag_object->name]['total_revenue'] 			+= $product_data['product_revenue'];
                                $product_tag_data[$product_tag_object->name]['total_profit'] 			+= $product_data['total_profit'];
                                $product_tag_data[$product_tag_object->name]['total_quantity_sold'] 		+= $product_data['qty_sold'];

                            }

                        }

                    } // End Product Line Item Loop

                } // End Product Data Availability Check

                // Inside the order loop, after processing the order data:
                // Must make sure we use the calculations after refunds for this, in case we've subtracted some tax data
                if (isset($order_data_with_refunds_calculated['tax_data']) && is_array($order_data_with_refunds_calculated['tax_data'])) {
                    foreach ($order_data_with_refunds_calculated['tax_data'] as $rate_id => $tax_rate) {

                        // Initialize this tax rate in summaries if not exists for data table
                        if (!isset($data_table['tax_metrics'][$rate_id])) {
                            $data_table['tax_metrics'][$rate_id] = array(
                                'name' => $tax_rate['name'],
                                'rate' => $tax_rate['rate'],
                                'total_amount' => 0,
                                'order_count' => 0,
                                'average_per_order' => 0,
                                'percent_of_total_tax' => 0
                            );
                        }

                        // Different structure for our categorized data
                        if ( ! isset($categorized_data['tax_metrics']['tax_rate_summaries'][$tax_rate['name']]) ) {
                            $categorized_data['tax_metrics']['tax_rate_summaries'][$tax_rate['name']] = array(
                                'total_amount' => 0,
                                'order_count' => 0,
                                'average_per_order' => 0,
                                'percent_of_total_tax' => 0
                            );
                        }

                        // Remove the no data available container
                        if ( isset($data_by_date['tax_metrics']['tax_rates_collected_by_date']['no_data_available']) ) $data_by_date['tax_metrics']['tax_rates_collected_by_date'] = array();

                        // Setup multi-dimensional tax rate data
                        if ( ! isset($data_by_date['tax_metrics']['tax_rates_collected_by_date'][$tax_rate['name']]) ) {
                            $data_by_date['tax_metrics']['tax_rates_collected_by_date'][$tax_rate['name']] = $data_warehouse->get_data_by_date_range_container();
                        }
                        $data_by_date['tax_metrics']['tax_rates_collected_by_date'][$tax_rate['name']][$date_range_key] += $tax_rate['amount'];

                        // Update the summaries
                        $categorized_data['tax_metrics']['tax_rate_summaries'][$tax_rate['name']]['total_amount'] += $tax_rate['amount'];
                        $categorized_data['tax_metrics']['tax_rate_summaries'][$tax_rate['name']]['order_count']++;
                        $data_table['tax_metrics'][$rate_id]['total_amount'] += $tax_rate['amount'];
                        $data_table['tax_metrics'][$rate_id]['order_count']++;

                    }
                }

            } // End Order Loop
            
            // Delete the calculation cache
            wpdai_delete_order_calculations_in_object_cache( $current_order_ids_batch );

            // Force garbage collection
            if ( function_exists('gc_collect_cycles') ) {
                gc_collect_cycles();
            }

            // Move to next batch
            $offset++;
            
        }

        // Any Figures we need for easy calculation
        $total_skus_sold = (is_array($unique_sku_array)) ? count( array_unique( $unique_sku_array ) ) : 0;

        // Additional Product Data Calculations
        $average_qty_sold_per_day                   = wpdai_divide( $total_qty_sold, $n_days_period );
        $average_products_sold_per_day              = wpdai_divide( $total_product_line_items_sold, $n_days_period );
        $average_skus_sold_per_day                  = wpdai_divide( $total_skus_sold, $n_days_period );
        $average_profit_per_product                 = wpdai_divide( $total_product_profit, $total_product_line_items_sold );
        $average_product_margin                     = wpdai_calculate_margin( $total_product_profit, $total_product_revenue_ex_tax );
        $average_product_margin_at_rrp              = wpdai_calculate_margin( $total_product_profit_at_rrp, $total_product_revenue_at_rrp );

        // Build lookup of refund data by active_product_id (refunds_internal keys are "Name (id)" or "item_X")
        $refund_by_product_id = array();
        $refund_product_data = isset( $categorized_data['refund_metrics']['product_refund_data'] ) && is_array( $categorized_data['refund_metrics']['product_refund_data'] )
            ? $categorized_data['refund_metrics']['product_refund_data']
            : array();
        foreach ( $refund_product_data as $refund_key => $refund_entry ) {
            if ( ! is_array( $refund_entry ) ) {
                continue;
            }
            $active_id = isset( $refund_entry['active_product_id'] ) ? (int) $refund_entry['active_product_id'] : 0;
            if ( $active_id > 0 ) {
                $refund_by_product_id[ $active_id ] = $refund_entry;
            }
        }

        foreach( $product_item_data as $product_id => $product_data ) {

            // Populate refund fields from refunds_internal (matched by product_id / active_product_id)
            $total_quantity_refunded = isset( $product_data['total_quantity_refunded'] ) ? (float) $product_data['total_quantity_refunded'] : 0;
            $total_times_refunded    = isset( $product_data['total_times_refunded'] ) ? (int) $product_data['total_times_refunded'] : 0;
            $total_refund_amount     = isset( $product_data['total_refund_amount'] ) ? (float) $product_data['total_refund_amount'] : 0;
            if ( isset( $refund_by_product_id[ $product_id ] ) ) {
                $ref = $refund_by_product_id[ $product_id ];
                $total_quantity_refunded = isset( $ref['total_quantity_refunded'] ) ? (float) $ref['total_quantity_refunded'] : 0;
                $total_times_refunded    = isset( $ref['refund_count'] ) ? (int) $ref['refund_count'] : 0;
                $total_refund_amount     = isset( $ref['total_refund_amount'] ) ? (float) $ref['total_refund_amount'] : 0;
            }
            $product_item_data[ $product_id ]['total_quantity_refunded'] = $total_quantity_refunded;
            $product_item_data[ $product_id ]['total_times_refunded']    = $total_times_refunded;
            $product_item_data[ $product_id ]['total_refund_amount']     = $total_refund_amount;
            $product_item_data[ $product_id ]['refund_rate']             = wpdai_divide( $total_times_refunded, $product_data['total_times_sold'] ) * 100;

            // Calculations
            $product_item_data[$product_id]['purchase_rate']                    = wpdai_divide( $product_data['total_times_sold'], $total_order_count ) * 100;
            $product_item_data[$product_id]['average_margin']                   = wpdai_divide( $product_data['average_margin_sum'], $product_data['total_times_sold'] );
            $product_item_data[$product_id]['average_product_discount_percent'] = wpdai_divide( $product_data['average_product_discount_percent_sum'], $product_data['total_times_sold'] );
            $product_item_data[$product_id]['average_sell_price']               = wpdai_divide( $product_data['average_sell_price_sum'], $product_data['total_times_sold'] );

            // Get rid of trash
            unset( $product_item_data[$product_id]['average_margin_sum'] );
            unset( $product_item_data[$product_id]['average_product_discount_percent_sum'] );
            unset( $product_item_data[$product_id]['average_sell_price_sum'] );

        }

        // Recompute product refund totals from merged data (for totals.product_metrics)
        $total_product_refund_amount = 0;
        $total_line_items_refunded   = 0;
        foreach ( $product_item_data as $row ) {
            $total_product_refund_amount += isset( $row['total_refund_amount'] ) ? (float) $row['total_refund_amount'] : 0;
            $total_line_items_refunded   += isset( $row['total_times_refunded'] ) ? (int) $row['total_times_refunded'] : 0;
        }
        $totals['product_metrics']['total_product_refund_amount'] = $total_product_refund_amount;
        $totals['product_metrics']['total_line_items_refunded']  = $total_line_items_refunded;

        // Handle some refund adjustments
        // Pick up the orders that we've marked as refunded in this timeline, so we can make the correct GP adjustments
        // Chris working here
        $original_order_ids_with_refund_adjustments_already_calculated = array();
        foreach( $data_table['refund_metrics']['refunds'] as $refund_data ) {

            $date_key = gmdate( $date_format, $refund_data['refund_date'] );
            $original_order_id = $refund_data['parent_order_id'];

            // Skip orders we've already looked at
            if ( in_array( $original_order_id, $original_order_ids_with_refund_adjustments_already_calculated ) ) {
                continue;
            }

            // Fetch the old & new order data
            $order_calculations_before_refund_applied = wpdai_calculate_cost_profit_by_order( $original_order_id );
            $order_calculations_after_refund_applied = wpdai_calculate_cost_profit_by_order( $original_order_id, true, true );

            // Must have valid data
            if ( ! is_array($order_calculations_before_refund_applied) || ! is_array($order_calculations_after_refund_applied) ) {
                continue;
            }

            // Calculate any variances we need to adjust for with our refunded orders
	        $gross_profit_difference = $order_calculations_before_refund_applied['total_order_profit'] - $order_calculations_after_refund_applied['total_order_profit'];
            $cost_difference = $order_calculations_before_refund_applied['total_order_cost'] - $order_calculations_after_refund_applied['total_order_cost'];
            $tax_difference = $order_calculations_before_refund_applied['total_order_tax'] - $order_calculations_after_refund_applied['total_order_tax'];

            // Adjust the GP when a refund is applied
            if( is_numeric($gross_profit_difference) && isset($data_by_date['order_metrics']['profit_by_date'][$date_key]) ) {
                $data_by_date['order_metrics']['profit_by_date'][$date_key] += $gross_profit_difference;
            }

            // Adjust the total values according to our data coming back in for refund adjustments
            $total_profit += $gross_profit_difference;
            $total_cost += $cost_difference;
            $total_tax_owed += $tax_difference;

            // Add the order id into our array so we don't double calculate, timing may be loose, but calculations will be accurate
            $original_order_ids_with_refund_adjustments_already_calculated[] = $original_order_id;

        }

        foreach( $data_warehouse->get_data_by_date_range_container() as $date_range_key => $array_values ) { // Chris

            $refund_amount = ( isset($data_by_date['refund_metrics']['amount_refunded_by_date'][$date_range_key]) ) ? $data_by_date['refund_metrics']['amount_refunded_by_date'][$date_range_key] : 0;
            $refund_amount_ex_tax = ( isset($data_by_date['refund_metrics']['amount_refunded_ex_tax_by_date'][$date_range_key]) ) ? $data_by_date['refund_metrics']['amount_refunded_ex_tax_by_date'][$date_range_key] : 0;
            
            // Adjust the revenue by date
            if( isset($data_by_date['order_metrics']['revenue_by_date'][$date_range_key]) ) $data_by_date['order_metrics']['revenue_by_date'][$date_range_key] -= $refund_amount;
            if( isset($data_by_date['order_metrics']['revenue_excluding_tax_by_date'][$date_range_key]) ) $data_by_date['order_metrics']['revenue_excluding_tax_by_date'][$date_range_key] -= $refund_amount_ex_tax;

        }

        // Additional Acquisitions Calculations
        // Traffic Type
        foreach( $categorized_data['order_metrics']['acquisition_traffic_type'] as $data_key => $data ) {

            $categorized_data['order_metrics']['acquisition_traffic_type'][$data_key]['margin_percentage'] = wpdai_calculate_margin( $data['total_profit'], $data['total_revenue'] );
            $categorized_data['order_metrics']['acquisition_traffic_type'][$data_key]['average_order_value'] = wpdai_divide( $data['total_revenue'], $data['total_order_count'], 2 );
            $categorized_data['order_metrics']['acquisition_traffic_type'][$data_key]['percent_of_revenue'] = wpdai_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );

        }

        // Query Parameter Keys
        foreach( $categorized_data['order_metrics']['acquisition_query_parameter_keys'] as $data_key => $data ) {

            $categorized_data['order_metrics']['acquisition_query_parameter_keys'][$data_key]['margin_percentage'] = wpdai_calculate_margin( $data['total_profit'], $data['total_revenue'] );
            $categorized_data['order_metrics']['acquisition_query_parameter_keys'][$data_key]['average_order_value'] = wpdai_divide( $data['total_revenue'], $data['total_order_count'], 2 );
            $categorized_data['order_metrics']['acquisition_query_parameter_keys'][$data_key]['percent_of_revenue'] = wpdai_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );

        }

        // Query Parameter Values
        foreach( $categorized_data['order_metrics']['acquisition_query_parameter_values'] as $data_key => $data ) {

            $categorized_data['order_metrics']['acquisition_query_parameter_values'][$data_key]['margin_percentage'] = wpdai_calculate_margin( $data['total_profit'], $data['total_revenue'] );
            $categorized_data['order_metrics']['acquisition_query_parameter_values'][$data_key]['average_order_value'] = wpdai_divide( $data['total_revenue'], $data['total_order_count'], 2 );
            $categorized_data['order_metrics']['acquisition_query_parameter_values'][$data_key]['percent_of_revenue'] = wpdai_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );

        }

        // Landing Page
        foreach( $categorized_data['order_metrics']['acquisition_landing_page'] as $data_key => $data ) {

            $categorized_data['order_metrics']['acquisition_landing_page'][$data_key]['margin_percentage'] = wpdai_calculate_margin( $data['total_profit'], $data['total_revenue'] );
            $categorized_data['order_metrics']['acquisition_landing_page'][$data_key]['average_order_value'] = wpdai_divide( $data['total_revenue'], $data['total_order_count'], 2 );
            $categorized_data['order_metrics']['acquisition_landing_page'][$data_key]['percent_of_revenue'] = wpdai_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );

        }

        // Referral Source
        foreach( $categorized_data['order_metrics']['acquisition_referral_source'] as $data_key => $data ) {

            $categorized_data['order_metrics']['acquisition_referral_source'][$data_key]['margin_percentage'] = wpdai_calculate_margin( $data['total_profit'], $data['total_revenue'] );
            $categorized_data['order_metrics']['acquisition_referral_source'][$data_key]['average_order_value'] = wpdai_divide( $data['total_revenue'], $data['total_order_count'], 2 );
            $categorized_data['order_metrics']['acquisition_referral_source'][$data_key]['percent_of_revenue'] = wpdai_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );

        }

        // Campaign Name
        foreach( $categorized_data['order_metrics']['acquisition_campaign_name'] as $data_key => $data ) {

            $categorized_data['order_metrics']['acquisition_campaign_name'][$data_key]['margin_percentage'] = wpdai_calculate_margin( $data['total_profit'], $data['total_revenue'] );
            $categorized_data['order_metrics']['acquisition_campaign_name'][$data_key]['average_order_value'] = wpdai_divide( $data['total_revenue'], $data['total_order_count'], 2 );
            $categorized_data['order_metrics']['acquisition_campaign_name'][$data_key]['percent_of_revenue'] = wpdai_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );

        }

        // Coupons
        foreach( $categorized_data['coupon_metrics']['orders_with_and_without_coupons'] as $data_key => $data ) {

            $categorized_data['coupon_metrics']['orders_with_and_without_coupons'][$data_key]['margin_percentage'] = wpdai_calculate_margin( $data['total_profit'], $data['total_revenue'] );
            $categorized_data['coupon_metrics']['orders_with_and_without_coupons'][$data_key]['average_order_value'] = wpdai_divide( $data['total_revenue'], $data['total_order_count'], 2 );
            $categorized_data['coupon_metrics']['orders_with_and_without_coupons'][$data_key]['percent_of_revenue'] = wpdai_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );

        }

        // Additional Customers Calculations
        // New vs Returning
        foreach( $categorized_data['customer_metrics']['new_vs_returning_data'] as $data_key => $data ) {

            $categorized_data['customer_metrics']['new_vs_returning_data'][$data_key]['distinct_count'] = count( array_unique( $categorized_data['customer_metrics']['new_vs_returning_data'][$data_key]['distinct_count'] ) );
            $categorized_data['customer_metrics']['new_vs_returning_data'][$data_key]['margin_percentage'] = wpdai_calculate_margin( $data['total_profit'], $data['total_revenue'] );
            $categorized_data['customer_metrics']['new_vs_returning_data'][$data_key]['average_order_value'] = wpdai_divide( $data['total_revenue'], $data['total_order_count'], 2 );
            $categorized_data['customer_metrics']['new_vs_returning_data'][$data_key]['percent_of_revenue'] = wpdai_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );

        }

        // Guest vs Registered
        foreach( $categorized_data['customer_metrics']['guest_vs_registered_data'] as $data_key => $data ) {

            $categorized_data['customer_metrics']['guest_vs_registered_data'][$data_key]['distinct_count'] = count( array_unique( $categorized_data['customer_metrics']['guest_vs_registered_data'][$data_key]['distinct_count'] ) );
            $categorized_data['customer_metrics']['guest_vs_registered_data'][$data_key]['margin_percentage'] = wpdai_calculate_margin( $data['total_profit'], $data['total_revenue'] );
            $categorized_data['customer_metrics']['guest_vs_registered_data'][$data_key]['average_order_value'] = wpdai_divide( $data['total_revenue'], $data['total_order_count'], 2 );
            $categorized_data['customer_metrics']['guest_vs_registered_data'][$data_key]['percent_of_revenue'] = wpdai_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );

        }

        // Device Browser
        foreach( $categorized_data['customer_metrics']['device_browser_data'] as $data_key => $data ) {

            $categorized_data['customer_metrics']['device_browser_data'][$data_key]['margin_percentage'] = wpdai_calculate_margin( $data['total_profit'], $data['total_revenue'] );
            $categorized_data['customer_metrics']['device_browser_data'][$data_key]['average_order_value'] = wpdai_divide( $data['total_revenue'], $data['total_order_count'], 2 );
            $categorized_data['customer_metrics']['device_browser_data'][$data_key]['percent_of_revenue'] = wpdai_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );
            if ( isset( $categorized_data['customer_metrics']['device_browser_data'][$data_key]['distinct_count'] ) ) unset( $categorized_data['customer_metrics']['device_browser_data'][$data_key]['distinct_count'] );

        }

        // Device Type
        foreach( $categorized_data['customer_metrics']['device_type_data'] as $data_key => $data ) {

            $categorized_data['customer_metrics']['device_type_data'][$data_key]['margin_percentage'] = wpdai_calculate_margin( $data['total_profit'], $data['total_revenue'] );
            $categorized_data['customer_metrics']['device_type_data'][$data_key]['average_order_value'] = wpdai_divide( $data['total_revenue'], $data['total_order_count'], 2 );
            $categorized_data['customer_metrics']['device_type_data'][$data_key]['percent_of_revenue'] = wpdai_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );
            if ( isset( $categorized_data['customer_metrics']['device_type_data'][$data_key]['distinct_count'] ) ) unset( $categorized_data['customer_metrics']['device_type_data'][$data_key]['distinct_count'] );

        }

        // Location Data
        foreach( $categorized_data['customer_metrics']['country_location_data'] as $country_code => &$data ) {

            // Country name
            $customer_country_count++;

            // Country level data
            $data['margin_percentage'] = wpdai_calculate_margin( $data['total_profit'], $data['total_revenue'] );
            $data['average_order_value'] = wpdai_divide( $data['total_revenue'], $data['total_order_count'], 2 );
            $data['percent_of_revenue'] = wpdai_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );
            
            // Cleaning
            if ( isset($data['distinct_count']) ) unset( $data['distinct_count'] );

        }

        // Location Data - State
        foreach( $categorized_data['customer_metrics']['state_location_data'] as $state_code => &$data ) {

            $data['margin_percentage'] = wpdai_calculate_margin( $data['total_profit'], $data['total_revenue'] );
            $data['average_order_value'] = wpdai_divide( $data['total_revenue'], $data['total_order_count'], 2 );
            $data['percent_of_revenue'] = wpdai_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );
            if ( isset($data['distinct_count']) ) unset( $data['distinct_count'] );

        }

        // Customer Calculations (refund_count, refund_value from refunds_internal; refund_rate derived)
        $refund_customer_data = isset( $categorized_data['refund_metrics']['customer_refund_data'] ) && is_array( $categorized_data['refund_metrics']['customer_refund_data'] )
            ? $categorized_data['refund_metrics']['customer_refund_data']
            : array();

        foreach( $data_table['customer_metrics'] as $data_key => $data ) {

            // Populate refund fields from refunds_internal customer_refund_data (keyed by billing_email or customer_N)
            $refund_count  = 0;
            $refund_value  = 0.0;
            if ( isset( $refund_customer_data[ $data_key ]['refund_count'] ) ) {
                $refund_count = (int) $refund_customer_data[ $data_key ]['refund_count'];
            }
            if ( isset( $refund_customer_data[ $data_key ]['refund_value'] ) ) {
                $refund_value = (float) $refund_customer_data[ $data_key ]['refund_value'];
            }
            if ( $refund_count === 0 && $refund_value === 0.0 && ! empty( $data['user_id'] ) && (int) $data['user_id'] > 0 ) {
                $customer_key = 'customer_' . (int) $data['user_id'];
                if ( isset( $refund_customer_data[ $customer_key ]['refund_count'] ) ) {
                    $refund_count = (int) $refund_customer_data[ $customer_key ]['refund_count'];
                }
                if ( isset( $refund_customer_data[ $customer_key ]['refund_value'] ) ) {
                    $refund_value = (float) $refund_customer_data[ $customer_key ]['refund_value'];
                }
            }
            $data_table['customer_metrics'][ $data_key ]['refund_count'] = $refund_count;
            $data_table['customer_metrics'][ $data_key ]['refund_value'] = $refund_value;
            $data_table['customer_metrics'][ $data_key ]['refund_rate']  = wpdai_calculate_percentage( $refund_count, $data['total_order_count'], 2 );

            $data_table['customer_metrics'][$data_key]['margin_percentage']   = wpdai_calculate_margin( $data['total_profit'], $data['total_revenue'] );
            $data_table['customer_metrics'][$data_key]['average_order_value'] = wpdai_divide( $data['total_revenue'], $data['total_order_count'], 2 );
            $data_table['customer_metrics'][$data_key]['percent_of_revenue']  = wpdai_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );

            if ( $data['total_order_count'] > 1 ) $customer_count_purchase_more_than_once++;
            if ( $refund_count > 0 ) $customers_with_refund_count++;

        }

        // For safe calculations
        $unique_customer_count              = (int) (is_array($unique_counter['unique_customers_by_email'])) ? count( $unique_counter['unique_customers_by_email'] ) : 0;
        $registered_customer_count          = (int) $categorized_data['customer_metrics']['guest_vs_registered_data']['registered_customer']['distinct_count'];
        $guest_customer_count               = (int) $categorized_data['customer_metrics']['guest_vs_registered_data']['guest_customer']['distinct_count'];
        $new_customer_count                 = (int) $categorized_data['customer_metrics']['new_vs_returning_data']['new_customer']['distinct_count'];
        $returning_customer_count           = (int) $categorized_data['customer_metrics']['new_vs_returning_data']['returning_customer']['distinct_count'];
        $average_customer_value_revenue     = (float) wpdai_divide( $total_revenue, $unique_customer_count, 2 );
        $average_customer_value_profit      = (float) wpdai_divide( $total_profit, $unique_customer_count, 2 );
        $orders_per_customer                = (float) wpdai_divide( $totals['order_metrics']['total_order_count'], $unique_customer_count, 2 );
        $products_purchased_per_customer    = (float) wpdai_divide( $total_product_line_items_sold, $unique_customer_count, 2 );
        $quantity_purchased_per_customer    = (float) wpdai_divide( $total_qty_sold, $unique_customer_count, 2 );

        // Calculate some totals based on organised data
        $totals['customer_metrics']['customer_count_by_email_address']           = $unique_customer_count;
        $totals['customer_metrics']['registered_customer_count']                 = $registered_customer_count;
        $totals['customer_metrics']['registered_customer_percentage']            = wpdai_calculate_percentage( $registered_customer_count, $unique_customer_count );
        $totals['customer_metrics']['guest_customer_count']                      = $guest_customer_count;
        $totals['customer_metrics']['guest_customer_percentage']                 = wpdai_calculate_percentage( $guest_customer_count, $unique_customer_count );
        $totals['customer_metrics']['new_customer_count']                        = $new_customer_count;
        $totals['customer_metrics']['new_customer_percentage']                   = wpdai_calculate_percentage( $new_customer_count, $unique_customer_count );
        $totals['customer_metrics']['returning_customer_count']                  = $returning_customer_count;
        $totals['customer_metrics']['returning_customer_percentage']             = wpdai_calculate_percentage( $returning_customer_count, $unique_customer_count );
        $totals['customer_metrics']['average_customer_value_revenue']            = $average_customer_value_revenue;
        $totals['customer_metrics']['average_customer_value_profit']             = $average_customer_value_profit;
        $totals['customer_metrics']['orders_per_customer']                       = $orders_per_customer;
        $totals['customer_metrics']['customer_count_purchased_more_than_once']   = $customer_count_purchase_more_than_once;
        $totals['customer_metrics']['customer_country_count']                    = $customer_country_count;
        $totals['customer_metrics']['customer_state_count']                      = $customer_state_count;
        $totals['customer_metrics']['products_purchased_per_customer']           = $products_purchased_per_customer;
        $totals['customer_metrics']['quantity_purchased_per_customer']           = $quantity_purchased_per_customer;
        
        // Customer related refund data
        $totals['refund_metrics']['refunds_per_customer']                        = (float) wpdai_divide( $totals['refund_metrics']['total_order_count_with_refund'], $unique_customer_count, 4 ); // Already in refunds, can be removed here & in data mapping
        $totals['refund_metrics']['customer_refund_rate']                        = (float) wpdai_calculate_percentage( $totals['refund_metrics']['total_customers_with_refund'], $unique_customer_count, 2 );; // Already in refunds, can be removed here & in data mapping

        // Additional Acquisitions Calculations
        foreach( $data_table['coupon_metrics'] as $data_key => $data ) {

            // Get additional data from WC Coupon Object
            $coupon_object = new WC_Coupon( $data['coupon_code'] );

            // Add Additional Meta
            if ( is_a($coupon_object, 'WC_Coupon') ) {

                // Capture Data
                $coupon_id              = $coupon_object->get_id();
                $discount_type          = $coupon_object->get_discount_type();
                $discount_type_amount   = $coupon_object->get_amount();
                $description            = $coupon_object->get_description();
                $usage_count            = $coupon_object->get_usage_count();

                // Load Data In
                $data_table['coupon_metrics'][$data_key]['coupon_id']               = $coupon_id;
                $data_table['coupon_metrics'][$data_key]['discount_type']           = $discount_type;
                $data_table['coupon_metrics'][$data_key]['discount_type_amount']    = $discount_type_amount;
                $data_table['coupon_metrics'][$data_key]['description']             = $description;
                $data_table['coupon_metrics'][$data_key]['total_usage_count']       = $usage_count;

            }

            // Coupon specific data
            $data_table['coupon_metrics'][$data_key]['percent_of_orders_applied']               = wpdai_calculate_percentage($data['total_orders_applied'], $totals['order_metrics']['total_order_count'], 2);
            $data_table['coupon_metrics'][$data_key]['percent_of_orders_where_coupon_used']     = wpdai_calculate_percentage($data['total_orders_applied'], $totals['coupon_metrics']['orders_with_coupons'], 2);
            $data_table['coupon_metrics'][$data_key]['average_margin']                          = wpdai_calculate_margin($data['total_profit'], $data['total_revenue']);
            $data_table['coupon_metrics'][$data_key]['customers_by_email_address']              = (is_array($data_table['coupon_metrics'][$data_key]['customers_by_email_address'])) ?  array_unique($data_table['coupon_metrics'][$data_key]['customers_by_email_address']) : array();
            $data_table['coupon_metrics'][$data_key]['total_customers_applied']                 = count($data_table['coupon_metrics'][$data_key]['customers_by_email_address']);
            $data_table['coupon_metrics'][$data_key]['customers_by_email_address']              = (is_array($data_table['coupon_metrics'][$data_key]['customers_by_email_address'])) ?  array_unique($data_table['coupon_metrics'][$data_key]['customers_by_email_address']) : array();
            
            // Coupon totals
            $totals['coupon_metrics']['unique_coupon_codes_used']++;

        }

        // More totals
        $totals['coupon_metrics']['average_margin_with_coupons']                             = wpdai_calculate_margin( $totals['coupon_metrics']['total_profit_with_coupons'], $totals['coupon_metrics']['total_revenue_with_coupons'] );
        $totals['coupon_metrics']['revenue_percent_with_coupons']                            = wpdai_calculate_percentage( $totals['coupon_metrics']['total_revenue_with_coupons'], $total_revenue, 2 );
        $totals['coupon_metrics']['profit_percent_with_coupons']                             = wpdai_calculate_percentage( $totals['coupon_metrics']['total_profit_with_coupons'], $total_profit, 2 );
        $totals['coupon_metrics']['order_percent_with_coupons']                              = wpdai_calculate_percentage( $totals['coupon_metrics']['orders_with_coupons'], $totals['order_metrics']['total_order_count'], 2 );
        $totals['coupon_metrics']['coupons_per_order']                                       = wpdai_divide( $totals['coupon_metrics']['orders_with_coupons'], $totals['order_metrics']['total_order_count'], 4 );
        $totals['coupon_metrics']['average_coupon_discount_per_discounted_order']            = wpdai_divide( $totals['coupon_metrics']['total_discount_amount'], $totals['coupon_metrics']['orders_with_coupons'], 2 );
        $totals['coupon_metrics']['average_coupon_discount_percent_per_discounted_order']    = wpdai_calculate_percentage( $totals['coupon_metrics']['total_discount_amount'], $totals['coupon_metrics']['total_discount_amount'] + $totals['coupon_metrics']['total_revenue_with_coupons'], 2 );
        $totals['coupon_metrics']['orders_without_coupons']                                  = (int) $totals['order_metrics']['total_order_count'] - (int) $totals['coupon_metrics']['orders_with_coupons'];
        $totals['coupon_metrics']['percent_of_orders_with_coupons']                          = wpdai_calculate_percentage( $totals['coupon_metrics']['orders_with_coupons'], $totals['order_metrics']['total_order_count'] );
        $totals['coupon_metrics']['percent_of_orders_without_coupons']                       = wpdai_calculate_percentage( $totals['coupon_metrics']['orders_without_coupons'], $totals['order_metrics']['total_order_count'] );


        // After the order loop, calculate percentages
        if ( is_array($payment_gateway_data) && ! empty($payment_gateway_data) ) {
            foreach ($payment_gateway_data as $gateway_id => &$data) {
                $data['percent_of_orders'] = wpdai_calculate_percentage($data['order_count'], $total_order_count);
                $data['percent_of_revenue'] = wpdai_calculate_percentage($data['revenue'], $total_revenue);
                $data['average_order_value'] = wpdai_divide($data['revenue'], $data['order_count']);
                unset($data['distinct_count']);
            }
        }

        // After the order loop, calculate percentages chris
        if ( is_array($categorized_data['order_metrics']['order_status_data']) && ! empty($categorized_data['order_metrics']['order_status_data']) ) {
            foreach ($categorized_data['order_metrics']['order_status_data'] as $order_status => &$data) {
                $data['percent_of_orders'] = wpdai_calculate_percentage($data['total_order_count'], $total_order_count);
                $data['percent_of_revenue'] = wpdai_calculate_percentage($data['total_revenue'], $total_revenue);
                $data['average_order_value'] = wpdai_divide($data['total_revenue'], $data['total_order_count']);
                $data['margin_percentage'] = wpdai_calculate_margin( $data['total_profit'], $data['total_revenue'] );
                unset($data['distinct_count']);
            }
        }

        // Calculate order costs (custom product costs are included in this)
        $categorized_data['order_metrics']['order_cost_breakdown'] = array( 'cost_of_goods' => $total_product_cost_of_goods, 'shipping_cost' => $total_shipping_cost, 'payment_gateway_fees' => $total_payment_gateway_costs );
        if ( is_array($custom_order_cost_data) && ! empty($custom_order_cost_data) ) {
            foreach($custom_order_cost_data as $custom_order_cost_label => $custom_order_cost_value) {
                $categorized_data['order_metrics']['order_cost_breakdown'][$custom_order_cost_label] = $custom_order_cost_value;
            }
        }
        // if ( is_array($custom_product_cost_data) && ! empty($custom_product_cost_data) ) {
        //     foreach($custom_product_cost_data as $custom_product_cost_label => $custom_product_cost_value) {
        //         $categorized_data['order_metrics']['order_cost_breakdown'][$custom_product_cost_label] = $custom_product_cost_value;
        //     }
        // }

        // Make refund adjustments
        $total_revenue = $total_revenue - $totals['refund_metrics']['total_refund_amount'];
        $total_order_revenue_ex_tax = $total_order_revenue_ex_tax - $totals['refund_metrics']['total_refund_amount_ex_tax'];

        /**
         * 
         *  Calculate Totals
         * 
         **/
        $totals['order_metrics']['total_order_count']                         = $total_order_count;
        $totals['total_records'] 					                          = $totals['order_metrics']['total_order_count']; // Do we need?
        $totals['order_metrics']['total_order_revenue_inc_tax_and_refunds']   = $total_order_revenue_inc_tax_and_refunds;
        $totals['order_metrics']['total_order_revenue'] 					  = $total_revenue;
        $totals['order_metrics']['total_order_revenue_ex_tax']                = $total_order_revenue_ex_tax;
        $totals['order_metrics']['total_order_tax']                           = $total_tax_owed; // Taxes collected, with refunded taxes subtracted
        $totals['order_metrics']['total_tax_collected'] 					  = $total_tax_collected; // Total tax collected on sales
        $totals['order_metrics']['total_order_cost'] 				          = $total_cost;
        $totals['order_metrics']['total_order_profit'] 					      = $total_profit;
        $totals['order_metrics']['total_freight_recovery'] 			          = $total_shipping_charged;
        $totals['order_metrics']['total_freight_cost'] 				          = $total_shipping_cost;
        $totals['product_metrics']['total_product_cost'] 				      = $total_product_cost; // Includes custom costs
        $totals['order_metrics']['total_product_cost_of_goods'] 		      = $total_product_cost_of_goods;
        $totals['order_metrics']['total_payment_gateway_costs'] 		      = $total_payment_gateway_costs;

        // Custom Costs
        $totals['order_metrics']['total_custom_order_costs']                  = $total_custom_order_costs;

        $categorized_data['order_metrics']['custom_order_cost_data']          = $custom_order_cost_data;
        $totals['order_metrics']['total_custom_product_costs']                = $total_custom_product_costs;

        // Payment Gateway Data
        $categorized_data['order_metrics']['payment_gateway_data']            = $payment_gateway_data;

        // Product Data 
        $totals['product_metrics']['total_product_revenue'] 			      = $total_product_revenue;
        $totals['product_metrics']['total_product_revenue_excluding_tax']     = $total_product_revenue_ex_tax;
        $totals['product_metrics']['total_qty_sold'] 					      = $total_qty_sold;
        $totals['product_metrics']['total_skus_sold'] 					      = $total_skus_sold;
        $totals['product_metrics']['total_product_line_items_sold']           = $total_product_line_items_sold;

        // Discount Data    
        $totals['product_metrics']['total_product_revenue_at_rrp'] 		      = $total_product_revenue_at_rrp;
        $totals['product_metrics']['total_product_discount_amount'] 		  = $total_product_discounts;
        $totals['product_metrics']['average_product_discount_percent']        = wpdai_calculate_percentage( $total_product_discounts, $total_product_revenue_at_rrp );
        $totals['coupon_metrics']['total_coupon_discount_amount'] 	          = $total_coupon_discounts;
        $totals['order_metrics']['total_order_revenue_before_coupons'] 	      = $total_order_revenue_before_coupons;
        $totals['coupon_metrics']['average_coupon_discount_percent']          = wpdai_calculate_percentage( $total_coupon_discounts, $total_order_revenue_before_coupons );
        $totals['order_metrics']['total_order_discount_amount']               = $total_order_discounts;
        $totals['order_metrics']['total_order_revenue_before_discounts']      = $total_order_revenue_before_discounts;
        $totals['order_metrics']['average_order_discount_percent']            = wpdai_calculate_percentage( $total_order_discounts, $total_order_revenue_before_discounts );
        $totals['order_metrics']['orders_with_discount']                      = $orders_with_discount;
        $totals['order_metrics']['discounted_order_percent']                  = wpdai_calculate_percentage( $orders_with_discount, $total_order_count );

        // Calculations 
        $totals['order_metrics']['largest_order_revenue'] 				      = $largest_order_revenue;
        $totals['order_metrics']['largest_order_cost'] 					      = $largest_order_cost;
        $totals['order_metrics']['largest_order_profit'] 				      = $largest_order_profit;
        $totals['order_metrics']['average_order_margin']					  = wpdai_calculate_percentage( $total_profit, $total_order_revenue_ex_tax, 2 );
        $totals['order_metrics']['average_order_revenue'] 				      = wpdai_divide( $total_revenue, $total_order_count, 2 );
        $totals['order_metrics']['average_order_cost']					      = wpdai_divide( $total_cost, $total_order_count, 2 );
        $totals['order_metrics']['average_order_profit'] 				      = wpdai_divide( $total_profit, $total_order_count, 2 );
        $totals['order_metrics']['average_line_items_per_order']              = wpdai_divide( $total_product_line_items_sold, $total_order_count, 2 );
        $totals['order_metrics']['daily_average_order_count']                 = wpdai_divide( $total_order_count, $n_days_period );
        $totals['order_metrics']['daily_average_order_revenue']               = wpdai_divide( $total_revenue, $n_days_period );
        $totals['order_metrics']['daily_average_order_cost']                  = wpdai_divide( $total_cost, $n_days_period );
        $totals['order_metrics']['daily_average_order_profit']                = wpdai_divide( $total_profit, $n_days_period );
        $totals['order_metrics']['cost_percentage_of_revenue']                = wpdai_calculate_percentage( $total_cost, $total_revenue );

        // Refund data  
        $totals['refund_metrics']['refund_percent_of_revenue']                = wpdai_calculate_percentage( $totals['refund_metrics']['total_refund_amount'], $total_order_revenue_inc_tax_and_refunds );
        $totals['refund_metrics']['refund_rate_percentage']                   = wpdai_calculate_percentage( $totals['refund_metrics']['total_order_count_with_refund'], $totals['order_metrics']['total_order_count'] );

        // Additional Data  
        $categorized_data['order_metrics']['payment_gateway_order_count']     = $payment_gateway_array;

        // Additional Product Data
        $totals['product_metrics']['total_product_profit']                     = $total_product_profit;
        $totals['product_metrics']['total_product_profit_at_rrp']              = $total_product_profit_at_rrp;
        $totals['product_metrics']['average_profit_per_product']               = $average_profit_per_product;
        $totals['product_metrics']['average_product_margin']                   = $average_product_margin;
        $totals['product_metrics']['average_product_margin_at_rrp']            = $average_product_margin_at_rrp;
        $totals['product_metrics']['average_qty_sold_per_day']                 = $average_qty_sold_per_day;
        $totals['product_metrics']['average_products_sold_per_day']            = $average_products_sold_per_day;
        $totals['product_metrics']['average_skus_sold_per_day']                = $average_skus_sold_per_day;
        $totals['product_metrics']['largest_product_count_sold_per_order']     = $largest_product_count_sold_per_order;
        $totals['product_metrics']['largest_quantity_sold_per_order']          = $largest_quantity_sold_per_order;

        // Additional Product Report Data
        $data_table['product_metrics']                                         = $product_item_data;
        $categorized_data['product_metrics']['product_type_data']              = $product_type_data;
        $categorized_data['product_metrics']['product_cat_data']               = $product_cat_data;
        $categorized_data['product_metrics']['product_tag_data']               = $product_tag_data;

        // After the order loop, calculate averages and percentages
        if ($totals['order_metrics']['total_tax_collected'] > 0) {
            foreach ($data_table['tax_metrics'] as $rate_id => &$summary) {
                $summary['average_per_order'] = wpdai_divide($summary['total_amount'], $summary['order_count']);
                $summary['percent_of_total_tax'] = wpdai_calculate_percentage(
                    $summary['total_amount'],
                    $totals['order_metrics']['total_tax_collected']
                );
            }
            foreach($categorized_data['tax_metrics']['tax_rate_summaries'] as $rate_id => &$summary) {
                $summary['average_per_order'] = wpdai_divide($summary['total_amount'], $summary['order_count']);
                $summary['percent_of_total_tax'] = wpdai_calculate_percentage(
                    $summary['total_amount'],
                    $totals['order_metrics']['total_tax_collected']
                );
            }
            // Override our other calculation
            $totals['tax_metrics']['tax_as_percentage_of_revenue'] = wpdai_calculate_percentage( $totals['order_metrics']['total_tax_collected'], $totals['tax_metrics']['total_revenue_where_tax_was_collected'] );

        }

        // Create no data found array
        foreach ( $data_by_date as $data_key => $data_values ) {
            $data_by_date[ $data_key ] = $data_warehouse->maybe_create_no_data_found_date_array( $data_by_date[ $data_key ] );
        }

        // Return multi-entity structure for the warehouse to store (do not call set_data here).
        $sales_data = array(
            'orders'    => array(
                'totals'            => $totals['order_metrics'],
                'categorized_data'  => $categorized_data['order_metrics'],
                'data_by_date'      => $data_by_date['order_metrics'],
                'data_table'        => array( 'orders' => $data_table['order_metrics'] ),
                'total_db_records'  => $total_db_records,
            ),
            'customers' => array(
                'totals'            => $totals['customer_metrics'],
                'categorized_data'  => $categorized_data['customer_metrics'],
                'data_by_date'      => $data_by_date['customer_metrics'],
                'data_table'        => array( 'customers' => $data_table['customer_metrics'] ),
                'total_db_records'  => $total_db_records,
            ),
            'products'  => array(
                'totals'            => $totals['product_metrics'],
                'categorized_data'  => $categorized_data['product_metrics'],
                'data_by_date'      => $data_by_date['product_metrics'],
                'data_table'        => array( 'products' => $data_table['product_metrics'] ),
                'total_db_records'  => $total_db_records,
            ),
            'coupons'   => array(
                'totals'            => $totals['coupon_metrics'],
                'categorized_data'  => $categorized_data['coupon_metrics'],
                'data_by_date'      => $data_by_date['coupon_metrics'],
                'data_table'        => array( 'coupons' => $data_table['coupon_metrics'] ),
                'total_db_records'  => $total_db_records,
            ),
            'refunds'   => array(
                'totals'            => $totals['refund_metrics'],
                'categorized_data'  => $categorized_data['refund_metrics'],
                'data_by_date'      => $data_by_date['refund_metrics'],
                'data_table'        => $data_table['refund_metrics'],
                'total_db_records'  => $refund_total_db_records,
            ),
            'taxes'     => array(
                'totals'            => $totals['tax_metrics'],
                'categorized_data'  => $categorized_data['tax_metrics'],
                'data_by_date'      => $data_by_date['tax_metrics'],
                'data_table'        => array( 'taxes' => $data_table['tax_metrics'] ),
                'total_db_records'  => $total_db_records,
            ),
        );

        return apply_filters( 'wpd_alpha_insights_data_source_sales', $sales_data, $data_warehouse );
    }

}

// Self-register when file is loaded.
new WPDAI_Sales_Data_Source();


// add_action( 'admin_init', function() {
//     $refund_id = 13265; // the refund ID you want gone
//     if ( wc_get_order( $refund_id ) instanceof WC_Order_Refund ) {
//         wp_delete_post( $refund_id, true ); // true = force delete, skip trash
//     }
// });