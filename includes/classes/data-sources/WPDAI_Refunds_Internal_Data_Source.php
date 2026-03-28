<?php
/**
 * Internal Refunds Data Source
 *
 * Provides internal refunds entity data for the Alpha Insights data warehouse.
 * The final public version of this data source is handled in Sales Data Source.
 * This is so that we can introduce refunds alongside order data for sales calculations with correct timings.
 *
 * @package Alpha Insights
 * @since 5.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * Refunds data source class
 *
 * @since 5.0.0
 */
class WPDAI_Refunds_Internal_Data_Source extends WPDAI_Custom_Data_Source_Base {

    /**
     * Entity names this data source provides
     *
     * @since 5.0.0
     * @var array<string>
     */
    protected $entity_names = array( 'refunds_internal' );

    /**
     * Fetch refunds data
     *
     * Get filters via $data_warehouse->get_filter(). Use get_data_by_date_range_container() for date alignment.
     *
     * @since 5.0.0
     * @param WPDAI_Data_Warehouse $data_warehouse The data warehouse instance (required).
     * @return array Single-entity structure: totals, categorized_data, data_table, data_by_date, total_db_records.
     */
    public function fetch_data( WPDAI_Data_Warehouse $data_warehouse ) {

        // Setup default containers
        $totals = array(
            'total_refund_amount' 				        => 0,
            'total_refund_amount_ex_tax'                => 0,
            'total_refund_tax'                          => 0,
            'total_refund_shipping'                     => 0,
            'total_refund_shipping_tax'                 => 0,
            'total_refund_count'                        => 0,
            'total_order_count_with_refund' 	        => 0,
            'total_order_count_with_full_refund' 	    => 0,
            'total_order_count_with_partial_refund'     => 0,
            'total_qty_refunded'                        => 0,
            'total_skus_refunded'                       => 0,
            'total_product_refund_amount'               => 0,
            'total_line_items_refunded'                 => 0,
            'total_customers_with_refund'               => 0,
            'refund_percent_of_revenue'                 => 0,
            'refund_rate_percentage'                    => 0,
            'refunds_per_day'                           => 0,
            'refunds_per_customer'                      => 0,
            'customer_refund_rate'                      => 0
        );

        $categorized_data = array(
            'refunded_order_ids' => array(), // Refund IDs
            'parent_order_ids' => array(), // Associated Order IDs (parent orders)
            'product_refund_data' => array(), // Product-level refund data keyed by product_id
            'customer_refund_data' => array(), // Customer-level refund data keyed by billing_email
        );

        $data_table = array();

        $data_by_date = array(
            'amount_refunded_by_date'           => $data_warehouse->get_data_by_date_range_container(),
            'amount_refunded_ex_tax_by_date'   => $data_warehouse->get_data_by_date_range_container(),
            'quantity_refunded_by_date' 	    => $data_warehouse->get_data_by_date_range_container(),
            'orders_refunded_by_date' 	        => $data_warehouse->get_data_by_date_range_container(),
            'product_refund_amount_by_date'     => $data_warehouse->get_data_by_date_range_container(), // Product refund amounts by date
            'product_refund_quantity_by_date'   => $data_warehouse->get_data_by_date_range_container(), // Product refund quantities by date
        );

        // Setup default vars
        $date_from   = $data_warehouse->get_date_from();
        $date_to     = $data_warehouse->get_date_to();
        $date_format = $data_warehouse->get_filter( 'date_format_string' );
        // Fallback to default date format if not set
        if ( empty( $date_format ) || ! is_string( $date_format ) ) {
            $date_format = 'Y-m-d';
        }
        $n_days_period = $data_warehouse->get_n_days_range();
        $memory_limit = ini_get('memory_limit');
        if ( empty( $memory_limit ) ) {
            $memory_limit = 'Unknown';
        }

        // Store currency for conversion (align with WPDAI_Order_Calculator).
        $store_currency = wpdai_get_store_currency();

        // Filters (aligned with Sales data source fetch_data)
        $billing_email_filter           = $data_warehouse->get_data_filter( 'orders', 'billing_email' );
        $order_ids_filter               = $data_warehouse->get_data_filter( 'orders', 'order_ids' );
        $billing_country_filter         = $data_warehouse->get_data_filter( 'customers', 'billing_country' );
        $user_id_filter                 = $data_warehouse->get_data_filter( 'customers', 'user_id' );
        $ip_address_filter              = $data_warehouse->get_data_filter( 'customers', 'ip_address' );
        $customer_billing_email_filter  = $data_warehouse->get_data_filter( 'customers', 'billing_email' );

        // Match order status filter of parent refund IDs to ensure we match our refund data to the queried sales data
        $parent_order_status_filter = wpdai_paid_order_statuses();
        $order_status_filter        = $data_warehouse->get_data_filter('orders', 'order_status');
        if ( $order_status_filter ) $parent_order_status_filter = $order_status_filter;
        // Remove the wc prefix for checking $order->get_status later
        if ( is_array( $parent_order_status_filter ) ) {
            $parent_order_status_filter = array_map( function( $status ) {
                return str_replace( 'wc-', '', $status );
            }, $parent_order_status_filter );
        }

        // Initialize refund tracking variables
        $total_refund_amount = 0;
        $total_refund_amount_ex_tax = 0;
        $total_refund_tax = 0;
        $total_refund_shipping = 0;
        $total_refund_shipping_tax = 0;
        $refunded_product_ids = array();
        $refunded_order_ids = array();
        // Per-date tracking so orders_refunded_by_date counts unique orders per date (not refund events).
        $orders_refunded_counted_by_date = array();

        // Build query for refund orders
        $args = array(
            'limit'         => -1,
            'orderby'      => 'date',
            'order'        => 'DESC',
            'date_created' => $date_from . '...' . $date_to,
            'type'         => array( 'shop_order_refund' ),
            'return'       => 'ids',
        );

        // All time filter
        if ( $data_warehouse->get_filter( 'date_preset' ) === 'all_time' ) {
            unset( $args['date_created'] );
        }

        // Search by billing email
        if ( $billing_email_filter ) {
            $args['billing_email'] = $billing_email_filter;
        }
        if ( $customer_billing_email_filter ) {
            $args['billing_email'] = $customer_billing_email_filter;
        }

        // Search by user ID
        if ( $user_id_filter ) {
            $args['customer_id'] = $user_id_filter;
        }

        // Search by billing countries
        if ( $billing_country_filter && is_array( $billing_country_filter ) ) {
            $args['billing_country'] = $billing_country_filter;
        }

        // Search by IP address
        if ( $ip_address_filter ) {
            if ( ! isset( $args['meta_query'] ) ) {
                $args['meta_query'] = array();
            }
            $args['meta_query'][] = array(
                'key'     => '_customer_ip_address',
                'value'   => $ip_address_filter,
                'compare' => is_array( $ip_address_filter ) ? 'IN' : '=',
            );
        }

        // Search by order IDs: restrict to refunds whose parent order is in the list.
        // Use post_parent__in (WP_Query accepts array); 'parent' maps to post_parent (single int) and does not work for multiple IDs.
        if ( $order_ids_filter && is_array( $order_ids_filter ) ) {
            $args['post_parent__in'] = array_map( 'absint', $order_ids_filter );
        }

        // When customer/order filters are set, refund orders may not store billing/customer meta;
        // restrict to refunds whose parent order matches the same filters (aligned with Sales).
        $has_order_filter = $billing_email_filter || $customer_billing_email_filter || $user_id_filter
            || ( $billing_country_filter && is_array( $billing_country_filter ) ) || $ip_address_filter;
        if ( $has_order_filter && empty( $args['post_parent__in'] ) ) {
            $order_args = array(
                'limit'        => -1,
                'return'       => 'ids',
                'type'         => array( 'shop_order' ),
                'date_created' => isset( $args['date_created'] ) ? $args['date_created'] : '',
            );
            if ( $billing_email_filter ) {
                $order_args['billing_email'] = $billing_email_filter;
            }
            if ( $customer_billing_email_filter ) {
                $order_args['billing_email'] = $customer_billing_email_filter;
            }
            if ( $user_id_filter ) {
                $order_args['customer_id'] = $user_id_filter;
            }
            if ( $billing_country_filter && is_array( $billing_country_filter ) ) {
                $order_args['billing_country'] = $billing_country_filter;
            }
            if ( $ip_address_filter ) {
                $order_args['meta_query'] = array( array(
                    'key'     => '_customer_ip_address',
                    'value'   => $ip_address_filter,
                    'compare' => is_array( $ip_address_filter ) ? 'IN' : '=',
                ) );
            }
            $matching_order_ids = (array) wc_get_orders( $order_args );
            if ( ! empty( $matching_order_ids ) ) {
                $args['post_parent__in'] = array_map( 'absint', $matching_order_ids );
                // Refund orders often don't store billing/customer meta; remove these so the
                // refund query only uses parent (filtering is already done via matching order IDs).
                unset( $args['billing_email'], $args['customer_id'], $args['billing_country'], $args['meta_query'] );
            } else {
                // No orders match; no refunds can match
                $refund_ids = array();
            }
        } elseif ( ! empty( $args['post_parent__in'] ) ) {
            // We have parent from order_ids_filter; same reason to drop order/customer args.
            unset( $args['billing_email'], $args['customer_id'], $args['billing_country'], $args['meta_query'] );
        }

        // Ignore refunds: still run a valid query that returns no rows so response shape stays normalized.
        if ( filter_var( $data_warehouse->get_data_filter( 'orders', 'ignore_refunds', false ), FILTER_VALIDATE_BOOLEAN ) ) {
            
            return array(
                'totals'            => $totals,
                'categorized_data'  => $categorized_data,
                'data_by_date'      => $data_by_date,
                'data_table'        => array(
                    'refunds'       => $data_table
                ),
                'total_db_records'  => 0,
            );
            
        }

        // Query refund IDs (unless we already set empty due to no matching orders)
        if ( ! isset( $refund_ids ) ) {
            $refund_ids = (array) wc_get_orders( $args );
        }
        
        if ( empty( $refund_ids ) ) {
            // No refunds found, set empty data and return
            $refunds_data = array(
                'totals'            => $totals,
                'categorized_data'  => $categorized_data,
                'data_by_date'      => $data_by_date,
                'data_table'        => array(
                    'refunds'       => $data_table
                ),
                'total_db_records'  => 0,
            );
            return apply_filters( 'wpd_alpha_insights_data_source_refunds', $refunds_data, $data_warehouse );
        }

        $total_db_records = count( $refund_ids );

        // Process refunds in batches to manage memory
        $batch_size = 2500;
        $total_batches = ( $batch_size > 0 ) ? ceil( wpdai_divide( $total_db_records, $batch_size ) ) : 1;

        for ( $batch = 0; $batch < $total_batches; $batch++ ) {

            // Get current batch of refund IDs
            $current_refund_ids_batch = array_slice( $refund_ids, $batch * $batch_size, $batch_size );

            if ( empty( $current_refund_ids_batch ) ) {
                break; // No more refunds to process
            }

            // Process each refund in the batch
            foreach ( $current_refund_ids_batch as $refund_id ) {

                // Memory check
                if ( wpdai_is_memory_usage_greater_than( 90 ) ) {
                    $data_warehouse->set_error(
                        sprintf(
                            /* translators: 1: Number of processed refunds, 2: Total number of refunds, 3: PHP memory limit */
                            __( 'Memory exhausted after processing %1$s out of %2$s refunds. Increase your PHP memory limit or reduce the date range. Your current PHP memory limit is %3$s.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                            ( $batch * $batch_size ) + count( $current_refund_ids_batch ),
                            $total_db_records,
                            $memory_limit
                        )
                    );
                    break 2; // Break out of both loops
                }

                // Get refund object
                $refund = wc_get_order( $refund_id );

                // Safety check
                if ( ! is_a( $refund, 'WC_Order_Refund' ) ) {
                    continue;
                }

                $refund_ids_to_skip = apply_filters( 'wpd_alpha_insights_refund_ids_to_skip', array(), $refund, $data_warehouse );
                if ( in_array( $refund_id, $refund_ids_to_skip ) ) {
                    continue;
                }

                // Get parent order ID and load parent order (used for skip check and later for tax fallback).
                $parent_order_id = $refund->get_parent_id();
                $parent_order = null;
                if ( $parent_order_id > 0 ) {
                    $parent_order = wc_get_order( $parent_order_id );
                    if ( ! is_a( $parent_order, 'WC_Order' ) ) {
                        $parent_order = null;
                    }
                }

                // Filter refunds to only be for relevant orders according to our query
                if ( is_a( $parent_order, 'WC_Order' ) ) {
                    $parent_order_status = $parent_order->get_status();
                    if ( ! in_array( 'any', $parent_order_status_filter ) && ! in_array( $parent_order_status, $parent_order_status_filter ) ) {
                        continue;
                    }
                }

                // Optionally skip refunds without a valid parent (broken/orphaned data).
                $skip_refunds_without_parent = apply_filters( 'wpd_alpha_insights_skip_refunds_without_parent', true, $refund, $data_warehouse );
                if ( $skip_refunds_without_parent && ! $parent_order ) {
                    continue;
                }

                // Get refund date (this is the date the refund was created, not the order date)
                $refund_date_created = $refund->get_date_created();
                
                if ( ! is_a( $refund_date_created, 'WC_DateTime' ) ) {
                    continue; // Skip if no valid date
                }

                // Safely get timestamp
                $refund_date_unix = 0;
                if ( method_exists( $refund_date_created, 'getOffsetTimestamp' ) ) {
                    $refund_date_unix = $refund_date_created->getOffsetTimestamp();
                } elseif ( method_exists( $refund_date_created, 'getTimestamp' ) ) {
                    $refund_date_unix = $refund_date_created->getTimestamp();
                }
                
                // Validate timestamp
                if ( $refund_date_unix <= 0 ) {
                    continue; // Skip if invalid timestamp
                }
                
                $date_range_key = gmdate( $date_format, $refund_date_unix );
                // Fallback if gmdate fails or returns invalid value
                if ( $date_range_key === false || empty( $date_range_key ) || ! is_string( $date_range_key ) ) {
                    $date_range_key = gmdate( 'Y-m-d', $refund_date_unix );
                    // Final fallback
                    if ( $date_range_key === false || empty( $date_range_key ) ) {
                        continue; // Skip this refund if we can't get a valid date key
                    }
                }

                // Get refund amount (negative value, we'll use absolute)
                $refund_amount = 0;
                if ( method_exists( $refund, 'get_amount' ) ) {
                    $refund_amount = abs( (float) $refund->get_amount() );
                }

                // Refund currency (for conversion; aligns with WPDAI_Order_Calculator).
                $refund_currency = '';
                if ( method_exists( $refund, 'get_currency' ) ) {
                    $refund_currency = $refund->get_currency();
                }

                // Get refund tax and shipping data (must be before we use these variables).
                // Use cart_tax + shipping_tax for total refund tax: WooCommerce only persists
                // _order_tax (cart_tax) and _order_shipping_tax for refunds; total_tax is not
                // stored and may be 0 when loading from DB in some code paths. Cart tax
                // includes line items + fees; shipping_tax is shipping only.
                $refund_tax_total = 0;
                $refund_shipping_total = 0;
                $refund_shipping_tax = 0;
                if ( method_exists( $refund, 'get_cart_tax' ) && method_exists( $refund, 'get_shipping_tax' ) ) {
                    $refund_tax_total = abs( (float) $refund->get_cart_tax() ) + abs( (float) $refund->get_shipping_tax() );
                } elseif ( method_exists( $refund, 'get_total_tax' ) ) {
                    $refund_tax_total = abs( (float) $refund->get_total_tax() );
                }
                if ( method_exists( $refund, 'get_shipping_total' ) ) {
                    $refund_shipping_total = abs( (float) $refund->get_shipping_total() );
                }
                if ( method_exists( $refund, 'get_shipping_tax' ) ) {
                    $refund_shipping_tax = abs( (float) $refund->get_shipping_tax() );
                }

                // Currency conversion (align with WPDAI_Order_Calculator): convert to store currency when refund is in another currency.
                // Refund-level amounts are converted after the closure so proportional fallback uses same-currency amounts.
                $multi_currency_refund = false;
                $exchange_rate = 1.0;
                if ( $parent_order && $refund_currency !== '' && $store_currency !== '' && $refund_currency !== $store_currency ) {
                    $multi_currency_refund = true;
                    $rate_result = wpdai_get_order_currency_conversion_rate( $parent_order );
                    $exchange_rate = isset( $rate_result['exchange_rate'] ) && is_numeric( $rate_result['exchange_rate'] ) ? (float) $rate_result['exchange_rate'] : 1.0;
                }

                // Get refund reason and other metadata (with safe method calls)
                $refund_reason = '';
                $refunded_by_user_id = 0;
                $refunded_payment = false;

                if ( method_exists( $refund, 'get_reason' ) ) {
                    $refund_reason = $refund->get_reason();
                }
                if ( method_exists( $refund, 'get_refunded_by' ) ) {
                    $refunded_by_user_id = (int) $refund->get_refunded_by();
                }
                if ( method_exists( $refund, 'get_refunded_payment' ) ) {
                    $refunded_payment = $refund->get_refunded_payment();
                }

                // Get refund quantity and product IDs from refund items
                $refund_quantity = 0;
                $refund_item_product_ids = array();
                // Get all items, then filter for product items only
                $refund_items_all = array();
                if ( method_exists( $refund, 'get_items' ) ) {
                    $refund_items_all = $refund->get_items();
                }
                $refund_items = array();
                if ( is_array( $refund_items_all ) && ! empty( $refund_items_all ) ) {
                    foreach ( $refund_items_all as $item ) {
                        if ( is_a( $item, 'WC_Order_Item_Product' ) ) {
                            $refund_items[] = $item;
                        }
                    }
                }
                $refund_item_details = array(); // Store detailed refund item data

                // Detect fully refunded order before processing items (so we can assume all line items refunded when no refund line items exist).
                $is_fully_refunded_order = false;
                if ( is_a( $parent_order, 'WC_Order' ) ) {
                    $parent_total = 0;
                    $parent_total_refunded = 0;
                    if ( method_exists( $parent_order, 'get_total' ) ) {
                        $parent_total = (float) $parent_order->get_total();
                    }
                    if ( method_exists( $parent_order, 'get_total_refunded' ) ) {
                        $parent_total_refunded = (float) $parent_order->get_total_refunded();
                    }
                    if ( $parent_total > 0 && $parent_total_refunded >= $parent_total ) {
                        $is_fully_refunded_order = true;
                    }
                }

                // Helper function to process a refund item (inline for better performance and clarity)
                $process_refund_item = function( $item, $is_from_parent_order = false ) use ( &$refund_quantity, &$refund_item_product_ids, &$refunded_product_ids, &$refund_item_details, &$categorized_data, &$data_by_date, $date_range_key, $parent_order_id, $refund_id, $refund_amount, &$parent_order, $multi_currency_refund, $refund_currency, $store_currency, $exchange_rate, $is_fully_refunded_order ) {
                    if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
                        return;
                    }

                // Get item quantity (for refund items, quantity is negative, so use absolute)
                $item_qty = 0;
                if ( method_exists( $item, 'get_quantity' ) ) {
                    $item_qty = abs( (float) $item->get_quantity() );
                }
                if ( $item_qty <= 0 ) {
                    return; // Skip items with zero quantity
                }
                    
                    // Get product ID (variation ID if exists, otherwise product ID)
                    $product_id = 0;
                    $variation_id = 0;
                    if ( method_exists( $item, 'get_product_id' ) ) {
                        $product_id = (int) $item->get_product_id();
                    }
                    if ( method_exists( $item, 'get_variation_id' ) ) {
                        $variation_id = (int) $item->get_variation_id();
                    }
                    $active_product_id = ( $variation_id > 0 ) ? $variation_id : $product_id;
                    
                    // Get product name from line item (works even if product is deleted)
                    $product_name = '';
                    if ( method_exists( $item, 'get_name' ) ) {
                        $product_name = $item->get_name();
                    }
                    if ( empty( $product_name ) || ! is_string( $product_name ) ) {
                        $product_name = __( 'Unknown Product', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
                    }
                    
                    // Get SKU from line item meta (fallback if product doesn't exist)
                    $product_sku = '';
                    $product_type = '';
                    if ( $active_product_id > 0 ) {
                        $product_object = wc_get_product( $active_product_id );
                        if ( is_a( $product_object, 'WC_Product' ) ) {
                            if ( method_exists( $product_object, 'get_sku' ) ) {
                                $product_sku = $product_object->get_sku();
                            }
                            if ( method_exists( $product_object, 'get_type' ) ) {
                                $product_type = $product_object->get_type();
                            }
                        }
                    }
                    
                    // If SKU not found from product, try to get from item meta
                    if ( empty( $product_sku ) && method_exists( $item, 'get_meta' ) ) {
                        $item_sku = $item->get_meta( '_sku' );
                        if ( ! empty( $item_sku ) && is_string( $item_sku ) ) {
                            $product_sku = $item_sku;
                        }
                    }
                    
                    // Use active_product_id as key, or item_id if product_id is 0 (for deleted products)
                    $item_id = 0;
                    if ( method_exists( $item, 'get_id' ) ) {
                        $item_id = (int) $item->get_id();
                    }
                    $product_refund_key = ( $active_product_id > 0 ) ? $product_name . ' (' . $active_product_id . ') ' : 'item_' . $item_id;

                    // Get detailed refund item data (with safe method calls)
                    $item_total = 0;
                    $item_tax = 0;
                    $item_subtotal = 0;
                    $item_subtotal_tax = 0;
                    
                    if ( method_exists( $item, 'get_total' ) ) {
                        $item_total = abs( (float) $item->get_total() );
                    }
                    if ( method_exists( $item, 'get_total_tax' ) ) {
                        $item_tax = abs( (float) $item->get_total_tax() );
                    }
                    $item_total_inc_tax = $item_total + $item_tax; // Line total including tax
                    
                    if ( method_exists( $item, 'get_subtotal' ) ) {
                        $item_subtotal = abs( (float) $item->get_subtotal() );
                    }
                    if ( method_exists( $item, 'get_subtotal_tax' ) ) {
                        $item_subtotal_tax = abs( (float) $item->get_subtotal_tax() );
                    }
                    $item_subtotal_inc_tax = $item_subtotal + $item_subtotal_tax; // Subtotal including tax

                    // If this is from parent order (fallback): for fully refunded orders use full line item amounts;
                    // for partial refunds without line items use proportional amounts.
                    if ( $is_from_parent_order && is_a( $parent_order, 'WC_Order' ) && ! $is_fully_refunded_order ) {
                        $order_total = 0;
                        if ( method_exists( $parent_order, 'get_total' ) ) {
                            $order_total = (float) $parent_order->get_total();
                        }
                        // Only calculate if order total is valid and refund amount is valid
                        if ( $order_total > 0 && $refund_amount > 0 ) {
                            $refund_ratio = $refund_amount / $order_total;
                            // Calculate proportional amounts
                            $item_total_inc_tax_original = $item_total + $item_tax;
                            $item_total_inc_tax = $item_total_inc_tax_original * $refund_ratio;
                            $item_tax_original = $item_tax;
                            $item_tax = $item_tax_original * $refund_ratio;
                            $item_total = $item_total_inc_tax - $item_tax;
                            $item_subtotal_original = $item_subtotal;
                            $item_subtotal_tax_original = $item_subtotal_tax;
                            $item_subtotal = $item_subtotal_original * $refund_ratio;
                            $item_subtotal_tax = $item_subtotal_tax_original * $refund_ratio;
                            $item_subtotal_inc_tax = $item_subtotal + $item_subtotal_tax;
                        }
                    }
                    // When $is_fully_refunded_order and $is_from_parent_order, we keep full item amounts (all line items treated as refunded).

                    // Convert item amounts to store currency when refund is multi-currency (align with WPDAI_Order_Calculator).
                    if ( $multi_currency_refund && $exchange_rate > 0 ) {
                        $item_total         = (float) wpdai_convert_currency( $refund_currency, $store_currency, $item_total, $exchange_rate );
                        $item_tax           = (float) wpdai_convert_currency( $refund_currency, $store_currency, $item_tax, $exchange_rate );
                        $item_subtotal      = (float) wpdai_convert_currency( $refund_currency, $store_currency, $item_subtotal, $exchange_rate );
                        $item_subtotal_tax  = (float) wpdai_convert_currency( $refund_currency, $store_currency, $item_subtotal_tax, $exchange_rate );
                        $item_total_inc_tax = $item_total + $item_tax;
                        $item_subtotal_inc_tax = $item_subtotal + $item_subtotal_tax;
                    }

                    // Calculate per unit values
                    $refund_amount_per_unit = ( $item_qty > 0 ) ? wpdai_divide( $item_total_inc_tax, $item_qty ) : 0;
                    $refund_amount_per_unit_ex_tax = ( $item_qty > 0 ) ? wpdai_divide( $item_total, $item_qty ) : 0;
                    $refund_tax_per_unit = ( $item_qty > 0 ) ? wpdai_divide( $item_tax, $item_qty ) : 0;

                    // Track product IDs (only if valid)
                    if ( $active_product_id > 0 ) {
                        $refund_item_product_ids[] = $active_product_id;
                        $refunded_product_ids[] = $active_product_id;
                    }
                    $refund_quantity += $item_qty;

                    // Store refund item details
                    $refund_item_details[] = array(
                        'product_id' => $active_product_id,
                        'parent_product_id' => $product_id,
                        'variation_id' => $variation_id,
                        'product_name' => $product_name,
                        'product_sku' => $product_sku,
                        'product_type' => $product_type,
                        'quantity_refunded' => $item_qty,
                        'refund_amount' => $item_total_inc_tax,
                        'refund_amount_ex_tax' => $item_total,
                        'refund_tax' => $item_tax,
                        'refund_amount_per_unit' => $refund_amount_per_unit,
                        'refund_amount_per_unit_ex_tax' => $refund_amount_per_unit_ex_tax,
                        'refund_tax_per_unit' => $refund_tax_per_unit,
                        'subtotal_refunded' => $item_subtotal_inc_tax,
                        'subtotal_refunded_ex_tax' => $item_subtotal,
                        'subtotal_tax_refunded' => $item_subtotal_tax,
                        'item_id' => $item_id,
                    );

                    // Track product refund data in categorized_data (keyed by product_id or item_id)
                    if ( ! isset( $categorized_data['product_refund_data'][$product_refund_key] ) ) {
                        $categorized_data['product_refund_data'][$product_refund_key] = array(
                            'product_id' => $product_id,
                            'variation_id' => $variation_id,
                            'active_product_id' => $active_product_id, // Variation ID if exists, otherwise product ID, or 0 if deleted
                            'product_name' => $product_name,
                            'product_sku' => $product_sku,
                            'product_type' => $product_type,
                            'total_quantity_refunded' => 0,
                            'total_refund_amount' => 0,
                            'total_refund_amount_ex_tax' => 0,
                            'total_refund_tax' => 0,
                            'total_subtotal_refunded' => 0,
                            'total_subtotal_refunded_ex_tax' => 0,
                            'total_subtotal_tax_refunded' => 0,
                            'refund_count' => 0, // Number of times this product was refunded
                            'order_ids' => array(), // Order IDs where this product was refunded
                            'refund_ids' => array(), // Refund IDs for this product
                        );
                    }

                    // Accumulate product refund totals
                    $categorized_data['product_refund_data'][$product_refund_key]['total_quantity_refunded'] += $item_qty;
                    $categorized_data['product_refund_data'][$product_refund_key]['total_refund_amount'] += $item_total_inc_tax;
                    $categorized_data['product_refund_data'][$product_refund_key]['total_refund_amount_ex_tax'] += $item_total;
                    $categorized_data['product_refund_data'][$product_refund_key]['total_refund_tax'] += $item_tax;
                    $categorized_data['product_refund_data'][$product_refund_key]['total_subtotal_refunded'] += $item_subtotal_inc_tax;
                    $categorized_data['product_refund_data'][$product_refund_key]['total_subtotal_refunded_ex_tax'] += $item_subtotal;
                    $categorized_data['product_refund_data'][$product_refund_key]['total_subtotal_tax_refunded'] += $item_subtotal_tax;
                    $categorized_data['product_refund_data'][$product_refund_key]['refund_count']++;
                    
                    // Track order and refund IDs
                    if ( $parent_order_id > 0 && ! in_array( $parent_order_id, $categorized_data['product_refund_data'][$product_refund_key]['order_ids'] ) ) {
                        $categorized_data['product_refund_data'][$product_refund_key]['order_ids'][] = $parent_order_id;
                    }
                    if ( ! in_array( $refund_id, $categorized_data['product_refund_data'][$product_refund_key]['refund_ids'] ) ) {
                        $categorized_data['product_refund_data'][$product_refund_key]['refund_ids'][] = $refund_id;
                    }

                    // Populate product refund data by date
                    if ( isset( $data_by_date['product_refund_amount_by_date'][$date_range_key] ) ) {
                        $data_by_date['product_refund_amount_by_date'][$date_range_key] += $item_total_inc_tax;
                    }
                    if ( isset( $data_by_date['product_refund_quantity_by_date'][$date_range_key] ) ) {
                        $data_by_date['product_refund_quantity_by_date'][$date_range_key] += $item_qty;
                    }
                };

                // Process refund items if they exist
                if ( is_array( $refund_items ) && ! empty( $refund_items ) ) {
                    foreach ( $refund_items as $refund_item ) {
                        $process_refund_item( $refund_item, false );
                    }
                } else {
                    // If no refund items, fall back to parent order items (for full refunds without line items).
                    // When order is fully refunded, assume all line items and shipping are refunded.
                    if ( is_a( $parent_order, 'WC_Order' ) && method_exists( $parent_order, 'get_items' ) ) {
                        if ( $is_fully_refunded_order ) {
                            // Capture shipping from parent so refund totals reflect full refund.
                            if ( $refund_shipping_total <= 0 && method_exists( $parent_order, 'get_shipping_total' ) ) {
                                $refund_shipping_total = abs( (float) $parent_order->get_shipping_total() );
                            }
                            if ( $refund_shipping_tax <= 0 && method_exists( $parent_order, 'get_shipping_tax' ) ) {
                                $refund_shipping_tax = abs( (float) $parent_order->get_shipping_tax() );
                            }
                        }
                        $parent_order_items_all = $parent_order->get_items();
                        if ( is_array( $parent_order_items_all ) && ! empty( $parent_order_items_all ) ) {
                            foreach ( $parent_order_items_all as $order_item ) {
                                if ( is_a( $order_item, 'WC_Order_Item_Product' ) ) {
                                    $process_refund_item( $order_item, true );
                                }
                            }
                        }
                    }
                }

                // Convert refund-level amounts to store currency (after closure so proportional fallback used same-currency values).
                if ( $multi_currency_refund && $exchange_rate > 0 ) {
                    $refund_amount          = (float) wpdai_convert_currency( $refund_currency, $store_currency, $refund_amount, $exchange_rate );
                    $refund_tax_total       = (float) wpdai_convert_currency( $refund_currency, $store_currency, $refund_tax_total, $exchange_rate );
                    $refund_shipping_total  = (float) wpdai_convert_currency( $refund_currency, $store_currency, $refund_shipping_total, $exchange_rate );
                    $refund_shipping_tax    = (float) wpdai_convert_currency( $refund_currency, $store_currency, $refund_shipping_tax, $exchange_rate );
                }

                // Final adjustment: for fully refunded orders only, if the refund object has no tax data but the
                // parent order had tax, estimate refund tax from the order's tax ratio (total tax / gross total).
                // For partial refunds we do not assume tax; only use WC-reported tax.
                $refund_total_tax_so_far = $refund_tax_total + $refund_shipping_tax;
                if ( $is_fully_refunded_order && $refund_total_tax_so_far <= 0 && $refund_amount > 0 && is_a( $parent_order, 'WC_Order' ) ) {
                    $order_gross = (float) $parent_order->get_total();
                    $order_tax   = (float) $parent_order->get_total_tax();
                    if ( $order_gross > 0 && $order_tax > 0 ) {
                        $order_tax_ratio = wpdai_divide( $order_tax, $order_gross );
                        $estimated_refund_tax_total = $refund_amount * $order_tax_ratio;
                        $order_cart_tax    = (float) $parent_order->get_cart_tax();
                        $order_ship_tax    = (float) $parent_order->get_shipping_tax();
                        $order_tax_sum     = $order_cart_tax + $order_ship_tax;
                        if ( $order_tax_sum > 0 ) {
                            $refund_tax_total    = $estimated_refund_tax_total * wpdai_divide( $order_cart_tax, $order_tax_sum );
                            $refund_shipping_tax = $estimated_refund_tax_total * wpdai_divide( $order_ship_tax, $order_tax_sum );
                        } else {
                            $refund_tax_total = $estimated_refund_tax_total;
                        }
                    }
                }

                // Check if this is a full or partial refund by comparing to parent order; get original amount paid.
                $is_full_refund = false;
                $is_partial_refund = false;
                $original_amount_paid = 0.0;

                if ( is_a( $parent_order, 'WC_Order' ) ) {
                    $parent_total = 0;
                    $parent_total_refunded = 0;

                    if ( method_exists( $parent_order, 'get_total' ) ) {
                        $parent_total = (float) $parent_order->get_total();
                    }
                    if ( method_exists( $parent_order, 'get_total_refunded' ) ) {
                        $parent_total_refunded = (float) $parent_order->get_total_refunded();
                    }

                    // Original amount paid = parent order total (positive value).
                    $original_amount_paid = $parent_total;

                    // Check if this refund makes it a full refund (only if we have valid totals)
                    if ( $parent_total > 0 && $parent_total_refunded >= $parent_total ) {
                        $is_full_refund = true;
                    } elseif ( $parent_total > 0 ) {
                        $is_partial_refund = true;
                    }

                    // Track unique order IDs with refunds
                    if ( ! in_array( $parent_order_id, $refunded_order_ids ) ) {
                        $refunded_order_ids[] = $parent_order_id;
                        $totals['total_order_count_with_refund']++;

                        if ( $is_full_refund ) {
                            $totals['total_order_count_with_full_refund']++;
                        } else {
                            $totals['total_order_count_with_partial_refund']++;
                        }
                    }
                }

                // Refund amount ex tax: when WC reported tax use (refund_amount - refund_tax_total);
                // when there is no tax data, use the full refund amount so we always have a value.
                $refund_has_tax_data = ( $refund_tax_total + $refund_shipping_tax ) > 0;
                $refund_amount_ex_tax_for_totals = $refund_has_tax_data ? ( $refund_amount - $refund_tax_total ) : $refund_amount;

                $total_refund_amount += $refund_amount;
                $total_refund_amount_ex_tax += $refund_amount_ex_tax_for_totals;
                $total_refund_tax += $refund_tax_total;
                $total_refund_shipping += $refund_shipping_total;
                $total_refund_shipping_tax += $refund_shipping_tax;
                $totals['total_qty_refunded'] += $refund_quantity;

                // Store refund data in data_table
                $refund_data_entry = array(
                    'refund_id' => $refund_id,
                    'parent_order_id' => $parent_order_id,
                    'original_amount_paid' => $original_amount_paid,
                    'refund_amount' => $refund_amount,
                    'refund_amount_ex_tax' => $refund_amount_ex_tax_for_totals,
                    'refund_tax' => $refund_tax_total,
                    'refund_quantity' => $refund_quantity,
                    'refund_date' => $refund_date_unix,
                    'refund_date_formatted' => ( $refund_date_unix > 0 ) ? ( gmdate( 'Y-m-d H:i:s', $refund_date_unix ) ?: '' ) : '',
                    'is_full_refund' => $is_full_refund,
                    'is_partial_refund' => $is_partial_refund,
                    'refund_reason' => $refund_reason,
                    'refunded_by_user_id' => $refunded_by_user_id,
                    'refunded_payment' => $refunded_payment,
                    'refund_currency' => $refund_currency,
                    'refund_shipping_total' => $refund_shipping_total,
                    'refund_shipping_tax' => $refund_shipping_tax,
                    'refunded_product_ids' => is_array( $refund_item_product_ids ) ? array_unique( $refund_item_product_ids ) : array(),
                    'refund_items' => $refund_item_details, // Detailed refund item data
                );
                
                $data_table[] = $refund_data_entry;

                // Store refund IDs and parent order IDs in categorized_data
                if ( $refund_id > 0 && ! in_array( $refund_id, $categorized_data['refunded_order_ids'] ) ) {
                    $categorized_data['refunded_order_ids'][] = $refund_id;
                }
                if ( $parent_order_id > 0 && ! in_array( $parent_order_id, $categorized_data['parent_order_ids'] ) ) {
                    $categorized_data['parent_order_ids'][] = $parent_order_id;
                }

                // Track customer refund data (get customer info from parent order)
                if ( is_a( $parent_order, 'WC_Order' ) && $refund_amount > 0 ) {
                    $customer_billing_email = '';
                    $customer_id = 0;
                    $customer_user_id = 0;
                    
                    // Get customer billing email
                    if ( method_exists( $parent_order, 'get_billing_email' ) ) {
                        $customer_billing_email = $parent_order->get_billing_email();
                    }
                    
                    // Get customer ID
                    if ( method_exists( $parent_order, 'get_customer_id' ) ) {
                        $customer_id = (int) $parent_order->get_customer_id();
                    }
                    
                    // Get user ID
                    if ( method_exists( $parent_order, 'get_user_id' ) ) {
                        $customer_user_id = (int) $parent_order->get_user_id();
                    }
                    
                    // Use billing email as key (fallback to customer_id if email is empty)
                    $customer_key = ! empty( $customer_billing_email ) ? $customer_billing_email : ( $customer_id > 0 ? 'customer_' . $customer_id : 'unknown' );
                    
                    // Initialize customer refund data if not exists
                    if ( ! isset( $categorized_data['customer_refund_data'][$customer_key] ) ) {
                        $categorized_data['customer_refund_data'][$customer_key] = array(
                            'billing_email' => $customer_billing_email,
                            'customer_id' => $customer_id,
                            'user_id' => $customer_user_id,
                            'refund_count' => 0,
                            'refund_value' => 0,
                            'refund_value_ex_tax' => 0,
                            'refund_tax' => 0,
                            'refund_quantity' => 0,
                            'refund_ids' => array(),
                            'parent_order_ids' => array(),
                        );
                    }
                    
                    // Accumulate customer refund totals
                    $categorized_data['customer_refund_data'][$customer_key]['refund_count']++;
                    $categorized_data['customer_refund_data'][$customer_key]['refund_value'] += $refund_amount;
                    $categorized_data['customer_refund_data'][$customer_key]['refund_value_ex_tax'] += $refund_amount_ex_tax_for_totals;
                    $categorized_data['customer_refund_data'][$customer_key]['refund_tax'] += $refund_tax_total;
                    $categorized_data['customer_refund_data'][$customer_key]['refund_quantity'] += $refund_quantity;
                    
                    // Track refund IDs and parent order IDs for this customer
                    if ( $refund_id > 0 && ! in_array( $refund_id, $categorized_data['customer_refund_data'][$customer_key]['refund_ids'] ) ) {
                        $categorized_data['customer_refund_data'][$customer_key]['refund_ids'][] = $refund_id;
                    }
                    if ( $parent_order_id > 0 && ! in_array( $parent_order_id, $categorized_data['customer_refund_data'][$customer_key]['parent_order_ids'] ) ) {
                        $categorized_data['customer_refund_data'][$customer_key]['parent_order_ids'][] = $parent_order_id;
                    }
                }

                // Populate data_by_date containers
                // Only populate if the date key exists in the container (respects date range)
                if ( isset( $data_by_date['amount_refunded_by_date'][$date_range_key] ) ) {
                    $data_by_date['amount_refunded_by_date'][$date_range_key] += $refund_amount;
                }
                if ( isset( $data_by_date['amount_refunded_ex_tax_by_date'][$date_range_key] ) ) {
                    $data_by_date['amount_refunded_ex_tax_by_date'][$date_range_key] += $refund_amount_ex_tax_for_totals;
                }

                if ( isset( $data_by_date['quantity_refunded_by_date'][$date_range_key] ) ) {
                    $data_by_date['quantity_refunded_by_date'][$date_range_key] += $refund_quantity;
                }

                // Count unique orders per date (same semantics as total_order_count_with_refund).
                if ( isset( $data_by_date['orders_refunded_by_date'][$date_range_key] ) && $parent_order_id > 0 ) {
                    if ( ! isset( $orders_refunded_counted_by_date[ $date_range_key ] ) ) {
                        $orders_refunded_counted_by_date[ $date_range_key ] = array();
                    }
                    if ( ! in_array( $parent_order_id, $orders_refunded_counted_by_date[ $date_range_key ], true ) ) {
                        $orders_refunded_counted_by_date[ $date_range_key ][] = $parent_order_id;
                        $data_by_date['orders_refunded_by_date'][ $date_range_key ]++;
                    }
                }

            }

        }

        // Calculate final refund metrics
        $totals['total_refund_count'] = count( $data_table );
        $totals['total_refund_amount'] = $total_refund_amount;
        $totals['total_refund_amount_ex_tax'] = $total_refund_amount_ex_tax;
        $totals['total_refund_tax'] = $total_refund_tax;
        $totals['total_refund_shipping'] = $total_refund_shipping;
        $totals['total_refund_shipping_tax'] = $total_refund_shipping_tax;
        $totals['total_skus_refunded'] = is_array( $refunded_product_ids ) ? count( array_unique( $refunded_product_ids ) ) : 0;
        
        // Calculate product refund totals from categorized_data
        $total_product_refund_amount = 0;
        $total_line_items_refunded = 0;
        if ( isset( $categorized_data['product_refund_data'] ) && is_array( $categorized_data['product_refund_data'] ) && ! empty( $categorized_data['product_refund_data'] ) ) {
            foreach ( $categorized_data['product_refund_data'] as $product_id => $product_refund_data ) {
                // Safe array access
                if ( ! is_array( $product_refund_data ) ) {
                    continue;
                }
                
                $product_total_refund = isset( $product_refund_data['total_refund_amount'] ) ? (float) $product_refund_data['total_refund_amount'] : 0;
                $product_refund_count = isset( $product_refund_data['refund_count'] ) ? (int) $product_refund_data['refund_count'] : 0;
                
                $total_product_refund_amount += $product_total_refund;
                $total_line_items_refunded += $product_refund_count;
                
                // Calculate averages and percentages for each product
                $product_qty_refunded = isset( $product_refund_data['total_quantity_refunded'] ) ? (float) $product_refund_data['total_quantity_refunded'] : 0;
                if ( $product_qty_refunded > 0 ) {
                    $product_total_ex_tax = isset( $product_refund_data['total_refund_amount_ex_tax'] ) ? (float) $product_refund_data['total_refund_amount_ex_tax'] : 0;
                    $categorized_data['product_refund_data'][$product_id]['average_refund_amount_per_unit'] = wpdai_divide( $product_total_refund, $product_qty_refunded, 2 );
                    $categorized_data['product_refund_data'][$product_id]['average_refund_amount_per_unit_ex_tax'] = wpdai_divide( $product_total_ex_tax, $product_qty_refunded, 2 );
                } else {
                    $categorized_data['product_refund_data'][$product_id]['average_refund_amount_per_unit'] = 0;
                    $categorized_data['product_refund_data'][$product_id]['average_refund_amount_per_unit_ex_tax'] = 0;
                }
                
                // Calculate percentage of total refunds
                if ( $total_refund_amount > 0 ) {
                    $categorized_data['product_refund_data'][$product_id]['percent_of_total_refunds'] = wpdai_calculate_percentage( $product_total_refund, $total_refund_amount );
                } else {
                    $categorized_data['product_refund_data'][$product_id]['percent_of_total_refunds'] = 0;
                }
            }
        }
        
        // Add product refund totals to main totals
        $totals['total_product_refund_amount'] = $total_product_refund_amount;
        $totals['total_line_items_refunded'] = $total_line_items_refunded;
        
        // Calculate customer refund totals and metrics from categorized_data
        $total_customers_with_refund = 0;
        if ( isset( $categorized_data['customer_refund_data'] ) && is_array( $categorized_data['customer_refund_data'] ) && ! empty( $categorized_data['customer_refund_data'] ) ) {
            foreach ( $categorized_data['customer_refund_data'] as $customer_key => $customer_refund_data ) {
                // Safe array access
                if ( ! is_array( $customer_refund_data ) ) {
                    continue;
                }
                
                $customer_refund_value = isset( $customer_refund_data['refund_value'] ) ? (float) $customer_refund_data['refund_value'] : 0;
                $customer_refund_count = isset( $customer_refund_data['refund_count'] ) ? (int) $customer_refund_data['refund_count'] : 0;
                
                // Count unique customers with refunds
                if ( $customer_refund_count > 0 ) {
                    $total_customers_with_refund++;
                }
                
                // Calculate averages and percentages for each customer
                if ( $customer_refund_count > 0 ) {
                    $customer_refund_value_ex_tax = isset( $customer_refund_data['refund_value_ex_tax'] ) ? (float) $customer_refund_data['refund_value_ex_tax'] : 0;
                    $categorized_data['customer_refund_data'][$customer_key]['average_refund_amount'] = wpdai_divide( $customer_refund_value, $customer_refund_count, 2 );
                    $categorized_data['customer_refund_data'][$customer_key]['average_refund_amount_ex_tax'] = wpdai_divide( $customer_refund_value_ex_tax, $customer_refund_count, 2 );
                } else {
                    $categorized_data['customer_refund_data'][$customer_key]['average_refund_amount'] = 0;
                    $categorized_data['customer_refund_data'][$customer_key]['average_refund_amount_ex_tax'] = 0;
                }
                
                // Calculate percentage of total refunds
                if ( $total_refund_amount > 0 ) {
                    $categorized_data['customer_refund_data'][$customer_key]['percent_of_total_refunds'] = wpdai_calculate_percentage( $customer_refund_value, $total_refund_amount );
                } else {
                    $categorized_data['customer_refund_data'][$customer_key]['percent_of_total_refunds'] = 0;
                }
            }
        }
        
        // Add customer refund totals to main totals
        $totals['total_customers_with_refund'] = $total_customers_with_refund;
        
        // Calculate daily averages
        if ( $n_days_period > 0 ) {
            $totals['refunds_per_day'] = wpdai_divide( $totals['total_order_count_with_refund'], $n_days_period );
        }

        // Create no data found array for date containers
        $data_by_date = $data_warehouse->maybe_create_no_data_found_date_array( $data_by_date );

        // Return single-entity structure for the warehouse to store (do not call set_data here).
        $refunds_data = array(
            'totals'            => $totals,
            'categorized_data'  => $categorized_data,
            'data_by_date'      => $data_by_date,
            'data_table'        => array(
                'refunds' => $data_table
            ),
            'total_db_records'  => $total_db_records,
        );

        return apply_filters( 'wpd_alpha_insights_data_source_refunds', $refunds_data, $data_warehouse );

    }

}

// Self-register when file is loaded.
new WPDAI_Refunds_Internal_Data_Source();
