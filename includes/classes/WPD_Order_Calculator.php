<?php
/**
 * Class WPD_Order_Calculator
 *
 * Handles the calculation of costs, profits, and other metrics for WooCommerce orders.
 *
 * @package Alpha Insights
 * @version 1.0.0
 * @since 4.4.2
 * @author WPDavies
 * @link https://wpdavies.dev/
 */
defined('ABSPATH') || exit;

class WPD_Order_Calculator {

    /**
     * 
     *  The Order Object or order ID as passed in by the user
     * 
     *  @var bool
     * 
     **/
    private bool $force_recalculation = false;

    /**
     * 
     *  The Order Object
     * 
     *  @var WC_Order
     * 
     **/
    private $order;
    
    /**
     * 
     *  The Order ID
     * 
     *  @var int
     * 
     **/
    private int $order_id = 0;
    
    /**
     * 
     *  Default Cost Data
     * 
     *  @var array
     * 
     **/
    private $cost_defaults = array();

    /**
     * 
     *  Payment Gateway Costs
     * 
     *  @var array
     * 
     **/
    private $payment_gateway_cost_settings = array();
    
    /**
     * 
     *  The data results
     * 
     *  @var array
     * 
     **/
    private $results = array();
    
    /**
     * 
     *  Exchange rate for this order
     * 
     *  @var float
     * 
     **/
    private $exchange_rate = 1;
    
    /**
     * 
     *  Store Currency
     * 
     *  @var string
     * 
     **/
    private $store_currency;

    /**
     * 
     *  Order Currency
     * 
     *  @var string
     * 
     **/
    private $order_currency;

    /**
     * 
     *  Multi Currency Order
     * 
     *  @var bool|int
     *  
     **/
    private $multi_currency_order;

    /**
     * 
     *  Refund Order ID
     *  Used to store the original refund order id if a refund has been passed in
     * 
     *  @var int
     * 
     **/
    private $refund_order_id = 0;

    /**
     *
     *	Calculate the profit of an order by order_id or order object.
     *
     * 	Will return an associative array with detailed calculations for this order.
     * 	By default, will try fetch the values from our calculation cache, unless the $update_values parameter is set to true, which will refresh the cache.
     *	
     *	@param int|WC_order 	$order_id / $order			The order_id or order object
     *	@param bool 			$update_values 				(Default False) True to fore a recalculation and save the values to database
     *
     *  @since 1.0.0
     *  @version 4.4.20
     * 	@return array|bool Wil return an associative array for of the calculation values saved for this order or false on failure
     *
     **/
    public function __construct( $order_id_or_object = null, $force_recalculation = false) {
        
        // Capture order id or object where stored
        $this->initialize_parameters( $order_id_or_object, $force_recalculation );

        // Pefrm calculation method
        $this->calculate();

    }

    /**
     * 
     *  Confirm order_id from passed in parameter
     * 
     */
    private function initialize_parameters( $order_id_or_object, $force_recalculation ) {

        $this->force_recalculation = $force_recalculation;

        if (is_a($order_id_or_object, 'WC_Order')) {

            $this->order = $order_id_or_object;
            $this->order_id = $this->order->get_id();

        } elseif( is_numeric($order_id_or_object) && $order_id_or_object > 0 ) {

            $this->order_id = (int) $order_id_or_object;

        }

    }

    /**
     * 
     *  Main calculation method
     * 
     */
    private function calculate() {

        // Get the cached value if it exists
        if ( $this->force_recalculation === false ) {

            // Get cache from object if set, otherwise call the DB directly
            $order_calculations = wpd_get_order_calculation_cache( $this->order_id );

            // Fact check the data
            if ( is_array($order_calculations) && ! empty($order_calculations) ) {

                // Store the results
                $this->results = $order_calculations;

                // Return the results
                return $this->results;

            }

        }

        // Sets up order object if it doesn't exist & converts class types if required
        if ( $this->setup_order_object() === false ) return false;

        // Sets up default calculation data
        $this->setup_initial_data();

        // Deal with shipping costs
        $this->calculate_shipping_costs();

        // Deal with payment gateway costs
        $this->calculate_payment_gateway_fees();

        // Calculate tax data
        $this->calculate_tax_data();

        // Calculate coupon data
        $this->calculate_coupon_data();

        // Calculate Custom Order Costs
        $this->calculate_custom_order_costs();

        // Calculate subscription data
        $this->calculate_subscription_data();

        // Calculate product data
        $this->calculate_product_data();

        // Final Calculations
        $this->calculate_profit();

        /**
         *
         * 	Filters all the calculation values after the function is complete but before it has been returned or saved to meta
         *
         *  @param array $results The current order calculation object as an associate array
         *  @param WC_Order $order WooCommerce Order Object
         *
         * 	@return array $results
         *
         **/
        $this->results = apply_filters( 'wpd_ai_calculate_cost_profit_by_order', $this->results, $this->order );
        
        // Save calculations to data store
        $this->save_calculations();
        
        // Return Results
        return $this->results;

    }

    /**
     * 
     *  If we've not got an order object, lets set it up
     * 
     *  @return bool True if we've found an order object, false on failure
     * 
     **/
    private function setup_order_object() {

        // Setup the order object if it's not setup
        if ( ! is_a( $this->order, 'WC_Order' ) ) $this->order = wc_get_order( $this->order_id );

        // Convert Refund Order To It's Original Order
        if ( is_a( $this->order, 'WC_Order_Refund' ) ) {

            $this->refund_order_id 	    = $this->order_id;
            $this->order_id 			= $this->order->get_parent_id();
            $this->order 				= wc_get_order( $this->order_id );

        }

        // Return result
        return ( is_a($this->order, 'WC_Order') ) ? true : false;

    }

    /**
     * 
     *  Setup initial order data
     * 
     */
    private function setup_initial_data() {

        // Load Props
        $this->order_currency                       = $this->order->get_currency();
        $this->store_currency                       = wpd_get_store_currency();
        $this->multi_currency_order                 = ( $this->order_currency !== $this->store_currency ) ? 1 : 0;
        $this->cost_defaults                        = get_option( 'wpd_ai_cost_defaults' );
        $this->payment_gateway_cost_settings        = wpd_get_payment_gateway_cost_settings();
        $this->exchange_rate 			            = wpd_get_order_currency_conversion_rate( $this->order );

        // Basic Vars
        $total_order_revenue            = (float) $this->order->get_total(); // Does not include refunded amount
        $total_order_refund_amount      = (float) $this->order->get_total_refunded();
        $total_order_tax                = (float) $this->order->get_total_tax(); // Does not include refunded amount
        $total_order_tax_refunded       = ( is_a($this->order, 'WC_Subscription') ) ? 0 : abs( (float) $this->order->get_total_tax_refunded() ); // Used for tax exemption
        $total_shipping_charged         = (float) $this->order->get_shipping_total();
        $total_coupon_discounts         = (float) $this->order->get_discount_total() + (float) $this->order->get_discount_tax();

        // Convert currencies if multi currency
        if ( $this->multi_currency_order ) {

            $total_order_revenue        = $this->convert_currency( $total_order_revenue );
            $total_order_tax            = $this->convert_currency( $total_order_tax );
            $total_order_refund_amount  = $this->convert_currency( $total_order_refund_amount );
            $total_order_tax_refunded   = $this->convert_currency( $total_order_tax_refunded );
            $total_shipping_charged     = $this->convert_currency( $total_shipping_charged );
            $total_coupon_discounts     = $this->convert_currency( $total_coupon_discounts );

        }

        // Any requires calculations
        $total_order_revenue            = $total_order_revenue - $total_order_refund_amount;
        $total_order_tax                = $total_order_tax - $total_order_tax_refunded;
        $date_paid 						= ( is_a($this->order->get_date_paid(), 'WC_DateTime') ) ? $this->order->get_date_paid()->getOffsetTimestamp() : null;
        $date_created					= ( is_a($this->order->get_date_created(), 'WC_DateTime') ) ? $this->order->get_date_created()->getOffsetTimestamp() : null;
        $new_customer 					= ( wpd_customers_first_order( $this->order ) ) ? 'new' : 'returning';
        $registered_user                = ( is_numeric($this->order->get_user_id()) && $this->order->get_user_id() > 0 ) ? 1 : 0;
        $partial_refund                 = ( $total_order_refund_amount > 0 ) ? 1 : 0;
        $full_refund                    = 0;

        // Landing page / referral data
        $landing_page                   = ( ! empty($this->order->get_meta( '_wpd_ai_landing_page' )) ) ? $this->order->get_meta( '_wpd_ai_landing_page' ) : $this->order->get_meta('_wc_order_attribution_session_entry');
        $referrer_url                   = ( ! empty($this->order->get_meta( '_wpd_ai_referral_source' )) ) ? $this->order->get_meta( '_wpd_ai_referral_source' ) : $this->order->get_meta('_wc_order_attribution_referrer');
        $created_via                   = $this->order->get_created_via();
        
        // User Agent Data
        $user_agent                     = $this->order->get_customer_user_agent();
        $user_agent_data                = ( ! empty($user_agent) ) ? wpd_parse_user_agent( $user_agent ) : array();
        $device_type                    = ( isset( $user_agent_data['device_category'] ) ) ? $user_agent_data['device_category'] : 'Unknown';
        $device_browser                 = ( isset( $user_agent_data['browser'] ) ) ? $user_agent_data['browser'] : 'Unknown';

        // Traffic Source Data
        $query_params 				    = wpd_get_query_params( $landing_page );
		$traffic_source 			    = ( $created_via === 'admin' ) ? 'Admin' : wpd_get_traffic_type( $referrer_url, $query_params );
        $campaign_name 			        = '';
        $meta_campaign_id 				= $this->order->get_meta( '_wpd_ai_meta_campaign_id' );
        $google_campaign_id 			= $this->order->get_meta( '_wpd_ai_google_campaign_id' );

        if ( isset($meta_campaign_id) && ! empty($meta_campaign_id) ) {
            $campaign_name = wpd_get_facebook_campaign_name_by_id( $meta_campaign_id );
        } else if ( isset($google_campaign_id) && ! empty($google_campaign_id) ) {
            $campaign_name = wpd_get_google_campaign_name_by_id( $google_campaign_id );
        } else {
            $campaign_name = ( isset($query_params['utm_campaign']) && ! empty($query_params['utm_campaign']) ) ? $query_params['utm_campaign'] : '';
        }

        // If we have a fully refunded order
        if ( $total_order_refund_amount == ( $total_order_revenue + $total_order_refund_amount ) || $this->order->get_status() == 'refunded' ) {

            $full_refund            = 1;
            $partial_refund         = 0;

        }

        // Default Results
        $this->results = array(

            // ID
            'order_id' 								        => $this->order_id,
    
            // Main Calculations
            'total_order_revenue_inc_tax_and_refunds'       => $total_order_revenue + $total_order_refund_amount, // Original amount including taxes & refunds
            'total_order_revenue' 					        => $total_order_revenue, // Amount paid, including tax
            'total_order_revenue_excluding_tax' 	        => $total_order_revenue - $total_order_tax,
            'total_order_cost' 						        => 0, // Does not include tax
            'total_order_tax' 						        => $total_order_tax, // All tax paid on this order
            'total_order_profit'					        => 0, // Total order revenue, minus order costs & tax
            'total_order_margin' 					        => 0, // The bottomline profit compared to total revenue
            'total_shipping_charged' 				        => $total_shipping_charged, // Contributes to total order tax

            // Order Costs
            'total_shipping_cost' 					        => 0,
            'payment_gateway_cost' 					        => 0,
            'total_custom_order_costs' 				        => 0,
            'custom_order_cost_data' 				        => array(),
            'total_product_custom_costs' 			        => 0,
            'custom_product_cost_data' 				        => array(),

            // Product Data
            'total_product_revenue_at_rrp' 			        => 0,
            'total_product_revenue' 				        => 0,
            'total_product_revenue_ex_tax' 				    => 0,
            'total_qty_sold' 						        => 0,
            'total_product_profit' 					        => 0,
            'total_skus_sold' 						        => 0,
            'total_product_cost_of_goods' 					=> 0,
            'total_product_cost' 					        => 0,
            'product_data' 							        => array(),
    
            // Discount Data - Coupons
            'total_order_revenue_before_coupons' 	        => $total_coupon_discounts + $total_order_revenue,
            'total_coupon_discounts' 				        => $total_coupon_discounts, // Coupon Discount Amount
            'total_coupon_discount_percent' 		        => wpd_calculate_percentage( $total_coupon_discounts, ( $total_coupon_discounts + $total_order_revenue ) ), // Coupon Discount Percent
            'coupons_used' 							        => array(),

            // Discount Data - Product Discounts
            'total_order_revenue_before_product_discounts' 	=> 0,
            'total_product_discounts' 				        => 0, // Product Discount Amount
            'total_product_discount_percent' 		        => 0, // Product Discounts As Percentage

            // Discount Data - All
            'total_order_revenue_before_discounts' 	        => 0,
            'total_order_discounts' 				        => 0, // Coupons & Product Discounts
            'total_order_discount_percent' 			        => 0, // Coupons & Product Discounts
    
            // Order Meta
            'cache_version' 						        => WPD_AI_CACHE_UPDATE_REQUIRED_VER,
            'class_type' 							        => get_class( $this->order ),
            'created_via' 							        => $created_via,
            'order_type' 							        => $this->order->get_type(),
            'order_status' 							        => $this->order->get_status(),
            'date_paid' 							        => $date_paid,
            'date_created' 							        => $date_created,
            'is_paid' 								        => $this->order->is_paid(),
            'payment_gateway' 						        => $this->order->get_payment_method(),
            'landing_page_url' 						        => $landing_page,
            'referral_source_url' 					        => $referrer_url,
            'user_agent' 							        => $user_agent,
            'device_type' 							        => $device_type,
            'device_browser' 						        => $device_browser,
            'campaign_name'                                 => $campaign_name, // New
            'traffic_source'                                => $traffic_source, // New

            // Currency Data
            'order_currency' 						        => $this->order_currency,
            'store_currency'                                => $this->store_currency,
            'exchange_rate'                                 => $this->exchange_rate,
            'multi_currency_order'                          => $this->multi_currency_order,
    
            // Subscription Data
            'is_parent_subscription'				        => 0,
            'is_renewal_subscription_order' 		        => 0,
            'parent_subscription_ids' 				        => array(),
            'renewal_subscription_ids' 				        => array(),
    
            // Customer Data
            'is_registered_user' 					        => $registered_user,
            'new_returning_customer' 				        => $new_customer,
            'customer_id' 							        => $this->order->get_customer_id(),
            'user_id' 								        => $this->order->get_user_id(),
            'billing_email' 						        => $this->order->get_billing_email(),
            'billing_phone' 						        => $this->order->get_billing_phone(),
            'billing_first_name' 					        => $this->order->get_billing_first_name(),
            'billing_last_name' 					        => $this->order->get_billing_last_name(),
            'billing_country' 						        => $this->order->get_billing_country(),
            'billing_state' 						        => $this->order->get_billing_state(),
            'billing_postcode' 						        => $this->order->get_billing_postcode(),
            'billing_company' 						        => $this->order->get_billing_company(),
    
            // Refund Data
            'refund_order_id' 						        => $this->refund_order_id,
            'total_refund_amount' 					        => $total_order_refund_amount,
            'total_order_revenue_before_refunds' 	        => $total_order_revenue + $total_order_refund_amount,
            'total_refund_quantity' 				        => 0,
            'total_refund_sku_count'				        => 0,
            'full_refund' 							        => $full_refund,
            'partial_refund' 						        => $partial_refund,
            'refund_data' 							        => array(),
    
            // Tax Data
            'tax_data' 								        => array(),

            // Fee Data
            'fee_data'                                      => array() // To Do
    
        );

    }

    /**
     * 
     *  Calculate shipping costs for order
     * 
     **/
    private function calculate_shipping_costs() {

        // Default Options
        $shipping_cost_multiplier_order_revenue 	= (float) wpd_divide( $this->cost_defaults['default_shipping_cost_percent'], 100 );
        $shipping_cost_multiplier_shipping_charged 	= (float) wpd_divide( $this->cost_defaults['default_shipping_cost_percent_shipping_charged'], 100 );
        $shipping_cost_fee 							= (float) $this->cost_defaults['default_shipping_cost_fee'];

        // Calculate Default Value
        $order_revenue_multiplier                   = $this->results['total_order_revenue_inc_tax_and_refunds'] * $shipping_cost_multiplier_order_revenue; // Include tax and refunds, so the default works for refunded orders
        $shipping_charge_multiplier                 = $this->results['total_shipping_charged'] * $shipping_cost_multiplier_shipping_charged;
        $shipping_cost 					            = $order_revenue_multiplier + $shipping_charge_multiplier + $shipping_cost_fee;

        /**
         * 
         * 	Filters the default shipping cost of an order (this is an expense, not the amount charged to the customer)
         * 
         * 	@param float The current shipping cost assigned to this order
         *  @param WC_Order The order object
         * 
         * 	@return float The updated shipping cost
         * 
         **/
        $shipping_cost = (float) apply_filters( 'wpd_ai_order_shipping_cost_default_value', $shipping_cost, $this->order );

        // Use saved value if set
        $meta_shipping_cost = $this->order->get_meta( '_wpd_ai_total_shipping_cost' );
        if ( is_numeric($meta_shipping_cost) ) $shipping_cost = $meta_shipping_cost;

        // Store in main var
        $this->results['total_shipping_cost'] = (float) $shipping_cost;

    }

    /**
     * 
     *  Calculate payment gateway fees
     * 
     **/
    private function calculate_payment_gateway_fees() {

        // Get settings
        $current_payment_gateway = $this->results['payment_gateway'];
        $payment_gateway_cost_settings = $this->payment_gateway_cost_settings;
        $payment_gateway_cost = 0;
        $payment_gateway_fee_meta_keys = array( '_stripe_fee', '_paypal_fee', 'PayPal Transaction Fee', 'HitPay_fees', '_wcpay_transaction_fee' );
        $payment_gateway_fee_meta_keys = apply_filters( 'wpd_ai_payment_gateway_fee_meta_keys', $payment_gateway_fee_meta_keys );

        // Default Payment Gateway Cost
        $payment_gateway_cost_multiplier 	= (float) wpd_divide( $payment_gateway_cost_settings['default']['percent_of_sales'], 100 ); // As a percentage
        $payment_gateway_cost_fee 			= (float) $payment_gateway_cost_settings['default']['static_fee'];

        // If we've got a matching gateway cost in the settings, use it
        if ( isset($payment_gateway_cost_settings[$current_payment_gateway]) ) {
            $payment_gateway_cost_multiplier 	= (float) wpd_divide( $payment_gateway_cost_settings[$current_payment_gateway]['percent_of_sales'], 100 );
            $payment_gateway_cost_fee 			= (float) $payment_gateway_cost_settings[$current_payment_gateway]['static_fee'];
        }

        // Calculate cost, check against the revenue including tax and refunds in case we want to keep the cost the same for refunded orders
        $payment_gateway_cost = ( $this->results['total_order_revenue_inc_tax_and_refunds'] * $payment_gateway_cost_multiplier ) + $payment_gateway_cost_fee; // Include tax and refunds, so the default works for refunded orders

        // Check if we have pre-defined fee keys stored in meta
        foreach( $payment_gateway_fee_meta_keys as $meta_key ) {

            // Try find stored meta value
            $stored_fee = $this->order->get_meta( $meta_key );

            if ( is_numeric($stored_fee) ) {

                $payment_gateway_cost = (float) $stored_fee;
                break;

            }

        }
    
        // Assume no gateway fees if there's no income
        // if ( $this->results['total_order_revenue'] == 0 ) $payment_gateway_cost = 0;

        /**
         * 
         * 	Filters the default payment gateway cost of an order
         * 
         * 	@param float The current payment gateway cost assigned to this order
         *  @param WC_Order The order object
         * 
         * 	@return float The updated payment gateway cost
         * 
         **/
        $payment_gateway_cost = (float) apply_filters( 'wpd_ai_order_payment_gateway_cost_default_value', $payment_gateway_cost, $this->order );

        // Saved Value
        $meta_payment_gateway_cost = $this->order->get_meta( '_wpd_ai_total_payment_gateway_cost' );
        if ( is_numeric($meta_payment_gateway_cost) ) $payment_gateway_cost = (float) $meta_payment_gateway_cost;

        // Store in main var
        $this->results['payment_gateway_cost'] = (float) $payment_gateway_cost;

    }

    /**
     * 
     *  Calculates tax data for use in reporting
     * 
     **/
    private function calculate_tax_data() {

        $tax_breakdown  = array();
        $tax_items      = $this->order->get_items('tax');
    
        if ( ! empty($tax_items) && is_array($tax_items) ) {

            foreach ($tax_items as $tax_item) {

                // Ensure we have a WC_Order_Item_Tax object
                if ( ! ( $tax_item instanceof WC_Order_Item_Tax ) ) continue; 
    
                // Get rate id
                $rate_id = $tax_item->get_rate_id();

                // Not relevant if no rate id set
                if ( empty($rate_id) ) continue;
    
                // Get initial tax amounts
                $tax_total      = (float) $tax_item->get_tax_total();
                $shipping_tax   = (float) $tax_item->get_shipping_tax_total();
    
                // Get refunded amounts
                $refunded_tax = 0;

                // If it's a fully refunded order, assume 100% refund on taxes
                if ( $this->results['full_refund'] ) {

                    $refunded_tax = $tax_total + $shipping_tax;

                } else {

                    // Calculate tax refunds on partial refunds
                    foreach ($this->order->get_refunds() as $refund) {

                        foreach ($refund->get_items('tax') as $refund_tax_item) {
    
                            if ( $refund_tax_item->get_rate_id() === $rate_id ) {
    
                                $refunded_tax += abs((float)$refund_tax_item->get_tax_total());
                                $refunded_tax += abs((float)$refund_tax_item->get_shipping_tax_total());
    
                            }
    
                        }
    
                    }

                }

                // Convert currencies if multi currency
                if ( $this->multi_currency_order ) {

                    $tax_total      = $this->convert_currency( $tax_total );
                    $shipping_tax   = $this->convert_currency( $shipping_tax );
                    $refunded_tax   = $this->convert_currency( $refunded_tax );

                } 
                
                // Calculate final tax amount after refunds
                $tax_amount = ($tax_total + $shipping_tax) - $refunded_tax;
                
                // Skip if no tax was collected after refunds
                // if ( $tax_amount <= 0 ) continue;
                
                // Convert if multicurrency
                if ( $this->multi_currency_order ) $tax_amount = $this->convert_currency( $tax_amount );
                
                // Get rate details
                $rate_details   = WC_Tax::_get_tax_rate($rate_id);
                $rate_name      = ! empty($rate_details['tax_rate_name']) ? $rate_details['tax_rate_name'] : 'Tax';
                $rate_percent   = ! empty($rate_details['tax_rate']) ? (float)$rate_details['tax_rate'] : 0;
                
                $tax_breakdown[$rate_id] = array(

                    'name'              => $rate_name,
                    'rate'              => $rate_percent,
                    'amount'            => $tax_amount,
                    'original_amount'   => $tax_total + $shipping_tax,
                    'refunded_amount'   => $refunded_tax

                );

            }

        }

        // Store results
        $this->results['tax_data'] = $tax_breakdown;

    }

    /**
     * 
     *  Calculates Coupon Data
     * 
     * 	WC_Order_Item_Coupon
	 * 	@see https://woocommerce.github.io/code-reference/classes/WC-Order-Item-Coupon.html
     * 
     **/
    private function calculate_coupon_data() {

        $coupon_data = array();
        $coupon_items = $this->order->get_coupons();

        // Count number of orders with coupons
        if ( is_array($coupon_items) && count($coupon_items) > 0 ) {
    
            // Loop through coupons
            foreach( $coupon_items as $coupon ) {
    
                if ( ! is_a($coupon, 'WC_Order_Item_Coupon') ) continue;
    
                $coupon_item_id         = $coupon->get_id();
                $coupon_discount_amount = (float) $coupon->get_discount();
                $coupon_discount_tax    = (float) $coupon->get_discount_tax();

                // Multi Currency Order
                if ( $this->multi_currency_order ) {
                    $coupon_discount_amount = $this->convert_currency( $coupon_discount_amount );
                    $coupon_discount_tax    = $this->convert_currency( $coupon_discount_tax );
                }
    
                $coupon_data[$coupon_item_id]['coupon_code'] 				= $coupon->get_code();
                $coupon_data[$coupon_item_id]['coupon_name'] 				= $coupon->get_name();
                $coupon_data[$coupon_item_id]['discount_amount_ex_tax'] 	= (float) $coupon->get_discount();
                $coupon_data[$coupon_item_id]['discount_amount_tax_only'] 	= (float) $coupon->get_discount_tax();
                $coupon_data[$coupon_item_id]['discount_amount'] 			= $coupon_data[$coupon_item_id]['discount_amount_ex_tax'] + $coupon_data[$coupon_item_id]['discount_amount_tax_only'];
                $coupon_data[$coupon_item_id]['quantity_applied'] 			= $coupon->get_quantity();
    
            }
    
        }

        // Store results
        $this->results['coupons_used'] = $coupon_data;

    }

    /**
     * 
     *  Deals with custom order costs
     * 
     **/
    private function calculate_custom_order_costs() {

        // Load options
        $total_custom_order_costs   = 0;
        $custom_order_cost_array    = array();
        $custom_order_costs         = wpd_get_custom_order_cost_options();

        foreach( $custom_order_costs as $cost_slug => $cost_data ) {

            // Default calculation
            $default_cost_percent_of_order  = (float) $cost_data['percent_of_order_value'];
            $default_static_fee             = (float) $cost_data['static_fee'];
            $custom_cost_value              = ( $this->results['total_order_revenue'] * wpd_divide( $default_cost_percent_of_order, 100 ) ) + $default_static_fee;
    
            /**
             * 
             * 	Allow filtering of the custom order cost value, takes preference over the default settings but is overriden by the stored meta value.
             * 
             * 	@param float The current custom order cost value assigned to this order
             * 	@param string The unique slug for this custom order cost
             *  @param WC_Order The order object
             * 
             * 	@return float The updated payment gateway cost
             * 
             **/
            $custom_cost_value = (float) apply_filters( 'wpd_ai_custom_order_cost_default_value', $custom_cost_value, $cost_slug, $this->order );

            // Check stored meta, this will override all others
            $custom_cost_meta_key = '_wpd_ai_custom_order_cost_' . $cost_slug;
            $custom_cost_meta_value = $this->order->get_meta( $custom_cost_meta_key );
            if ( is_numeric($custom_cost_meta_value) ) $custom_cost_value = (float) $custom_cost_meta_value;
            
            // Make additions
            $total_custom_order_costs += $custom_cost_value;
            $custom_order_cost_array[$cost_slug] = $custom_cost_value;
    
        }

        // Store results
        $this->results['total_custom_order_costs']  = $total_custom_order_costs;
        $this->results['custom_order_cost_data']    = $custom_order_cost_array;

    }

    /**
     * 
     *  Calculate subscription data
     * 
     **/
    private function calculate_subscription_data() {

        if ( wpd_is_wc_subscriptions_active() ) {

            // Defaults
            $is_subscription_parent_order   = 0;
            $is_subscription_renewal_order  = 0;
            $renewal_subscription_ids       = array();
            $parent_subscription_ids        = array();

            // Get any subscriptions associated with this order
            $parent_subscription_objects = wcs_get_subscriptions_for_order( $this->order, array( 'order_type' => 'any' ) );
    
            // Loop through available parent subscription ids
            if ( is_array($parent_subscription_objects) ) {
    
                foreach( $parent_subscription_objects as $parent_subscription ) {
    
                    // Safety Check
                    if ( ! is_a($parent_subscription, 'WC_Subscription') ) continue;
    
                    // Collect subscription IDs
                    $parent_subscription_ids[] = $parent_subscription->get_id();
                    
                    // Force recalculate parent subscriptions while we're at it
                    // wpd_calculate_cost_profit_by_order( $parent_subscription->get_id(), true );
    
                }
    
            }
    
            // Check if this is a renewal order based in it having parent subscriptions
            $is_subscription_renewal_order = (is_array($parent_subscription_ids) && count($parent_subscription_ids) > 0) ? 1 : 0;
    
            // Deal with an actual subscription parent object
            if ( is_a($this->order,'WC_Subscription') ) {
    
                $is_subscription_parent_order = 1;
                $renewal_subscription_ids = $this->order->get_related_orders();
    
            }

            // Save Results
            $this->results['is_parent_subscription']        = $is_subscription_parent_order;
            $this->results['is_renewal_subscription_order'] = $is_subscription_renewal_order;
            $this->results['parent_subscription_ids']       = $parent_subscription_ids;
            $this->results['renewal_subscription_ids']      = $renewal_subscription_ids;
    
        }

    }

    /**
     * 
     *  Calculations for product data
     * 
     **/
    private function calculate_product_data() {

        // Global Defaults
        $total_product_revenue 					= 0;
        $total_product_revenue_ex_tax 			= 0;
        $total_qty_sold 						= 0;
        $total_skus_sold                        = 0;
        $total_product_custom_costs             = 0;
        $total_quantity_refunded 			    = 0;
        $total_product_cost 	                = 0;
        $total_product_revenue_at_rrp 	        = 0;
        $total_product_discounts                = 0;
        $total_product_profit                   = 0;
        $refunded_sku_count                     = 0;
        $cost_price_refund_exemption            = 0;
        $total_product_cogs                     = 0;
        $product_data                           = array();
        $custom_product_cost_data               = array();
        $refunded_skus                          = array();
        $refund_data                            = array();

        if ( count( $this->order->get_items() ) > 0 ) {

            foreach ( $this->order->get_items() as $item_id => $item ) {

                // Safety Check
                if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) continue;

                // Default Line Item Vars
                $qty_refunded                               = 0;
                $total_line_item_custom_product_cost        = 0;
                $line_item_custom_product_cost_data         = array();

                // Item Object Vars
                $order_item_cogs                            = $item->get_meta( '_wpd_ai_product_cogs' ); // Dont type case so we can check for empty
                $product_id 								= (int) $item->get_product_id();
                $variation_id 								= (int) $item->get_variation_id();
                $quantity 									= (float) $item->get_quantity();
                $line_item_total_before_discounts_ex_tax    = round( $item->get_subtotal(), 2); // Does not Include Discounts
                $line_item_total_after_discounts_ex_tax 	= round( $item->get_total(), 2); // Includes Discounts
                $line_item_tax_before_discounts 			= round( $item->get_subtotal_tax(), 2);
                $line_item_tax_after_discounts 				= round( $item->get_total_tax(), 2);
                $active_product_id                          = ( is_numeric($variation_id) && $variation_id > 0 ) ? (int) $variation_id : (int) $product_id;
                $cost_price_per_unit                        = ( is_numeric($order_item_cogs) ) ? (float) $order_item_cogs : (float) wpd_get_cost_price_by_product_id( $active_product_id );
                $product_object                             = wc_get_product( $active_product_id );

                // Convert prices if multi
                if ( $this->multi_currency_order ) {

                    $line_item_total_before_discounts_ex_tax    = $this->convert_currency( $line_item_total_before_discounts_ex_tax );
                    $line_item_total_after_discounts_ex_tax     = $this->convert_currency( $line_item_total_after_discounts_ex_tax );
                    $line_item_tax_before_discounts             = $this->convert_currency( $line_item_tax_before_discounts );
                    $line_item_tax_after_discounts              = $this->convert_currency( $line_item_tax_after_discounts );
    
                }

                // Custom product costs
                $this->calculate_custom_product_costs(
                    $active_product_id, 
                    $item,
                    $quantity,
                    $line_item_custom_product_cost_data, 
                    $total_line_item_custom_product_cost,
                    $total_product_custom_costs,
                    $custom_product_cost_data
                );

                // Line Item Totals
                $custom_product_costs_per_unit              = wpd_divide( $total_line_item_custom_product_cost, $quantity );
                $line_item_total_before_discounts_inc_tax 	= round( $line_item_total_before_discounts_ex_tax + $line_item_tax_before_discounts, 2);
                $line_item_total_after_discounts_inc_tax 	= round( $line_item_total_after_discounts_ex_tax + $line_item_tax_after_discounts, 2);
                $total_cost_per_unit                        = $cost_price_per_unit + $custom_product_costs_per_unit;
                $total_line_item_cost_price                 = $total_cost_per_unit * $quantity;
                $total_line_item_product_cogs               = $cost_price_per_unit * $quantity;

                // Per Unit
                $sell_price_per_unit_including_tax 			= wpd_divide( $line_item_total_after_discounts_inc_tax, $quantity );
                $sell_price_per_unit_excluding_tax 			= wpd_divide( $line_item_total_after_discounts_ex_tax, $quantity );
                $tax_per_unit                               = $sell_price_per_unit_including_tax - $sell_price_per_unit_excluding_tax;
                $line_item_tax                              = $tax_per_unit * $quantity;
        
                // Order Totals
                $total_product_revenue 					    += $line_item_total_after_discounts_inc_tax;
                $total_product_revenue_ex_tax 			    += $line_item_total_after_discounts_ex_tax;
                $total_qty_sold 						    += $quantity;
                $total_skus_sold++;

                // This needs to happen before we adjust for refunds
                // Coupon discounts must be the difference between the amount paid and the pre-discount amount
                $product_coupon_discount_amount = $line_item_total_before_discounts_inc_tax - $line_item_total_after_discounts_inc_tax;
                if ( $product_coupon_discount_amount < 0.01 ) $product_coupon_discount_amount = 0; 
                $product_coupon_discount_percentage = wpd_calculate_percentage( $product_coupon_discount_amount, $line_item_total_before_discounts_inc_tax );
        
                // Refund adjustments
                if ( $this->results['total_refund_amount'] > 0 ) {
    
                    // If the entire order has been refunded, assume all qty's have been refunded
                    $qty_refunded = ( $this->results['full_refund'] ) ? $quantity : abs( $this->order->get_qty_refunded_for_item( $item_id ) );
                    $quantity -= $qty_refunded;

                    // Setup product specific calculations
                    if ( is_numeric( $qty_refunded ) && $qty_refunded > 0 ) {

                        // Mark this as partial refund, can be adjusted later to full if required
                        $refunded_skus[] 					= $active_product_id;
                        $refund_data[$active_product_id]    = array();

                        // Adjust costs for this partial refund
                        $cost_price_refund_exemption 		= $qty_refunded * $cost_price_per_unit;
                        $total_line_item_product_cogs       -= $cost_price_refund_exemption;
                        $total_line_item_cost_price         -= $cost_price_refund_exemption;
                        $total_quantity_refunded 			+= $qty_refunded;
                        $custom_product_costs_per_unit      = wpd_divide( $total_line_item_custom_product_cost, $quantity );
                        $total_cost_per_unit                = $cost_price_per_unit + $custom_product_costs_per_unit;

                        // Adjust actual tax amount
                        $line_item_tax                      = $tax_per_unit * $quantity;

                        // Adjust revenues and costs for this refund
                        $line_item_total_after_discounts_inc_tax -= ( $sell_price_per_unit_including_tax * $qty_refunded);
                        $line_item_total_after_discounts_ex_tax -= ( $sell_price_per_unit_excluding_tax * $qty_refunded);
    
                        // Store refund data
                        $refund_data[$active_product_id]['product_id']                  = $active_product_id;
                        $refund_data[$active_product_id]['qty_refunded']                = $qty_refunded;
                        $refund_data[$active_product_id]['cost_price_refund_exemption'] = $cost_price_refund_exemption;
                        $refund_data[$active_product_id]['product_amount_refunded']     = $sell_price_per_unit_including_tax * $qty_refunded;

                    }
    
                }
    
                // Further calculations after refunds
                $total_product_cost 	+= $total_line_item_cost_price;
                $total_product_cogs     += $total_line_item_product_cogs;
                $line_item_profit 		= $line_item_total_after_discounts_ex_tax - $total_line_item_cost_price;
                $profit_per_product 	= $sell_price_per_unit_excluding_tax - $total_cost_per_unit;
                $total_product_profit 	+= $line_item_profit;
    
                // Store data
                if ( is_a( $product_object, 'WC_Product' ) ) {
    
                    $rrp_price = (float) $product_object->get_regular_price();
                    if ( $rrp_price == 0 ) $rrp_price = $sell_price_per_unit_including_tax;
        
                    // Product revenue at rrp
                    $product_revenue_at_rrp 		= $rrp_price * $quantity;
                    $total_product_revenue_at_rrp 	+= $product_revenue_at_rrp;
    
                    // Reduce rounding issues that come from combining tax and ex tax amount
                    $product_discount_amount 		= $product_revenue_at_rrp - $line_item_total_before_discounts_inc_tax;
                    if ( $product_discount_amount < 0.01 ) $product_discount_amount = 0; 
                    $product_discount_percentage = wpd_calculate_percentage( $product_discount_amount, $product_revenue_at_rrp );
                    $total_product_discounts += $product_discount_amount;
    
                    // Total discounts
                    $total_product_discount_amount = $product_discount_amount + $product_coupon_discount_amount;
                    $total_product_discount_percentage = wpd_calculate_percentage( $total_product_discount_amount, $product_revenue_at_rrp );

                    // If this line item has been fully refunded, 0 out some vars
                    if ( $qty_refunded == $item->get_quantity() ) {
    
                        $product_revenue_at_rrp = 0;
                        $line_item_total_after_discounts_inc_tax = 0;
                        $total_line_item_cost_price = 0;
                        $line_item_profit = 0;
                        $profit_per_product = 0;
                        $total_line_item_cost_price = 0;
                        $product_discount_amount = 0;
                        $product_discount_percentage = 0;
                        $product_coupon_discount_amount = 0;
                        $product_coupon_discount_percentage = 0;
                        $total_product_discount_amount = 0;
                        $total_product_discount_percentage = 0;
                        $line_item_total_after_discounts_ex_tax = 0;
                        $line_item_tax_after_discounts = 0;
                        $cost_price_per_unit = 0;

                    }
    
                    // Store product data
                    $product_data[$active_product_id] = array(
    
                        'product_name' 								=> $product_object->get_title(),
                        'sku' 										=> $product_object->get_sku(),
                        'product_type' 								=> $product_object->get_type(),
                        'item_id' 									=> $item_id,
                        'product_id' 								=> $active_product_id,
                        'variation_id' 								=> $variation_id,
                        'parent_product_id' 						=> $product_id,
                        'qty_sold' 									=> $quantity,
    
                        'product_revenue_at_rrp' 					=> $product_revenue_at_rrp,
                        'rrp_per_unit' 								=> $rrp_price,
    
                        'product_revenue' 							=> $line_item_total_after_discounts_inc_tax,
                        'product_revenue_per_unit' 					=> $sell_price_per_unit_including_tax,

                        'product_revenue_excluding_tax' 			=> $line_item_total_after_discounts_ex_tax,
                        'product_revenue_per_unit_excluding_tax' 	=> $sell_price_per_unit_excluding_tax,
                        'product_tax' 								=> $line_item_tax, // $line_item_tax_after_discounts,
    
                        'total_cost_of_goods' 						=> $total_line_item_cost_price,
                        'total_product_cogs'                        => $total_line_item_product_cogs,
                        'total_custom_product_cost' 				=> $total_line_item_custom_product_cost,
                        'total_cost_per_unit'                       => $total_cost_per_unit,
                        'cost_of_goods_per_unit' 					=> $cost_price_per_unit,
    
                        'total_profit' 								=> $line_item_profit,
                        'profit_per_unit' 							=> $profit_per_product,
                        'product_margin'							=> wpd_calculate_margin( $line_item_profit, $line_item_total_after_discounts_ex_tax ),
    
                        'product_discount_amount' 					=> $product_discount_amount, // Only Considers Sale Price
                        'product_discount_percentage' 				=> $product_discount_percentage, // Only Considers Sale Price
    
                        'coupon_discount_amount' 					=> $product_coupon_discount_amount, // Only Considers Coupon Discounts
                        'coupon_discount_percentage' 				=> $product_coupon_discount_percentage, // Only Considers Coupon Discounts
    
                        'total_discount_amount' 					=> $total_product_discount_amount, // Combines Coupons & Sale Price
                        'total_discount_percentage' 				=> $total_product_discount_percentage, // Combines Coupons & Sale Price
    
                        'qty_refunded' 								=> $qty_refunded,
                        'amount_refunded' 							=> $sell_price_per_unit_including_tax * $qty_refunded,

                        'custom_product_cost_data' 					=> $line_item_custom_product_cost_data
    
                    );
    
                }
    
            }
    
        }

        // Override by meta
        $meta_total_product_cost = $this->order->get_meta( '_wpd_ai_total_product_cost' );
        if ( is_numeric($meta_total_product_cost) ) {

            $total_product_cogs = (float) $meta_total_product_cost;
            $total_product_cost = $total_product_cogs + $total_product_custom_costs;
            
        }

        // If we've overriden at the order level
        $meta_total_order_product_custom_cost = $this->order->get_meta( '_wpd_ai_total_order_product_custom_cost' );
        if ( is_numeric($meta_total_order_product_custom_cost) ) {

            $total_product_custom_costs = (float) $meta_total_order_product_custom_cost;
            $total_product_cost = $total_product_cogs + $total_product_custom_costs;

        } 

        // Few calculation variables
        $total_product_profit 		                                    = $total_product_revenue_ex_tax - $total_product_cost;
        $refunded_sku_count 		                                    = ( is_array($refunded_skus) ) ? count($refunded_skus) : 0;
        $total_product_discount_percent                                 = wpd_calculate_percentage( $total_product_discounts, $total_product_revenue_at_rrp );
        $order_revenue_before_product_discounts                         = $total_product_discounts + $this->results['total_order_revenue'];

        // Update Results
        $this->results['total_product_revenue']                         = $total_product_revenue;
        $this->results['total_order_revenue_before_product_discounts']  = $order_revenue_before_product_discounts;
        $this->results['total_product_revenue_at_rrp']                  = $total_product_revenue_at_rrp;
        $this->results['total_product_revenue_ex_tax']                  = $total_product_revenue_ex_tax;
        $this->results['total_qty_sold']                                = $total_qty_sold;
        $this->results['total_skus_sold']                               = $total_skus_sold;
        $this->results['total_refund_quantity']                         = $total_quantity_refunded;
        $this->results['total_refund_sku_count']                        = $refunded_sku_count;
        $this->results['total_product_cost']                            = $total_product_cost; // COGS and product custom costs
        $this->results['total_product_cost_of_goods']                   = $total_product_cogs;
        $this->results['total_product_custom_costs']                    = $total_product_custom_costs;
        $this->results['custom_product_cost_data']                      = $custom_product_cost_data;
        $this->results['total_product_discounts']                       = $total_product_discounts;
        $this->results['total_product_discount_percent']                = $total_product_discount_percent;
        $this->results['total_product_profit']                          = $total_product_profit;
        $this->results['product_data']                                  = $product_data;
        $this->results['refund_data']                                   = $refund_data;

    }

    /**
     * 
     *  Does calculations for custom product costs & updates the variables passed in
     * 
     **/
    private function calculate_custom_product_costs( $active_product_id, $item, $quantity, &$line_item_custom_product_cost_data, &$total_line_item_custom_product_cost, &$total_product_custom_costs, &$custom_product_cost_data) {

        // Custom Line Item Product Costs
        $custom_product_cost_defaults = wpd_get_custom_product_cost_options( $active_product_id );

        if ( is_array($custom_product_cost_defaults) && ! empty($custom_product_cost_defaults) ) {

            // Collect Vars
            $qty_refunded = ( $this->results['full_refund'] ) ? $quantity : abs( $this->order->get_qty_refunded_for_item( $item->get_id() ) );
            $quantity = $quantity - $qty_refunded;
            
            // Check meta storage
            $line_item_custom_product_cost_meta = $item->get_meta( '_wpd_ai_custom_product_costs' );

            // Defined by filter
            foreach( $custom_product_cost_defaults as $custom_cost_slug => $custom_cost_data ) {
                
                // Default custom product cost value for filtering
				$default_custom_product_cost_value = (float) wpd_calculate_custom_product_cost_by_line_item( $item, $custom_cost_data );

                /**
                 * 
                 * 	Sets the default custom cost value for a custom product cost for a line item. This value is the per unit cost.
                 * 	This will be overriden by the meta field input
                 * 
                 * 	@param float $default_custom_product_cost_value The default custom product cost if set by your product configuration
                 *  @param string $custom_cost_slug The custom product cost slug that we are currently looking at
                 * 	@param WC_Order $order The WC Order for this line item
                 * 	@param WC_Order_Item_Product $item The line item for this product
                 * 
                 * 	@return float $default_custom_product_cost_value The filtered custom product cost value (must return a floating integer)
                 * 
                 **/
                $default_custom_product_cost_value = (float) apply_filters( 'wpd_ai_custom_product_cost_default_value', $default_custom_product_cost_value, $custom_cost_slug, $this->order, $item );
                
                // Either use default or 
                $line_item_custom_product_cost_per_unit = ( isset($line_item_custom_product_cost_meta[$custom_cost_slug]) && is_numeric($line_item_custom_product_cost_meta[$custom_cost_slug]) ) ? (float) $line_item_custom_product_cost_meta[$custom_cost_slug] : $default_custom_product_cost_value;
                $line_item_custom_product_cost_total = $line_item_custom_product_cost_per_unit * $quantity;

                // Setup default
                $line_item_custom_product_cost_data[$custom_cost_slug] = array(

                    'unit_cost' => $line_item_custom_product_cost_per_unit,
                    'total' 	=> $line_item_custom_product_cost_total

                );

                // Add to total for line item
                $total_line_item_custom_product_cost += $line_item_custom_product_cost_total;

                // Add to total for order
                $total_product_custom_costs += $line_item_custom_product_cost_total;

                // Add to total data payload
                if ( ! isset($custom_product_cost_data[$custom_cost_slug]) ) {

                    $custom_product_cost_data[$custom_cost_slug] = array(
                        'label' => $custom_cost_data['label'],
                        'unit_cost' => $line_item_custom_product_cost_per_unit,
                        'total_value' => 0,
                        'total_quantity' => 0
                    );

                }

                // Iterate Totals
                $custom_product_cost_data[$custom_cost_slug]['total_value'] += $line_item_custom_product_cost_total;
                $custom_product_cost_data[$custom_cost_slug]['total_quantity'] += $quantity;

            }

        }

    }

    /**
     * 
     *  Final Calculations
     * 
     **/
    private function calculate_profit() {

        // Adjust for fully refunded orders
        if ( $this->results['full_refund'] == 1 ) $this->adjust_costs_for_fully_refunded_order();

        // Calculate Total Costs // total_product_cost includes custom product costs 
        $cost_elements = array( 'total_product_cost_of_goods', 'total_product_custom_costs', 'total_shipping_cost', 'payment_gateway_cost', 'total_custom_order_costs' );
        foreach( $cost_elements as $cost_key ) {
            $this->results['total_order_cost'] += $this->results[$cost_key];
        }

        // Profit Calculations
        $this->results['total_order_profit'] = $this->results['total_order_revenue'] - $this->results['total_order_cost'] - $this->results['total_order_tax'];
                
        // Order Discounts
        $this->results['total_order_discounts'] = $this->results['total_product_discounts'] + $this->results['total_coupon_discounts'];
        $this->results['total_order_revenue_before_discounts'] = $this->results['total_order_revenue'] + $this->results['total_order_discounts'];
        $this->results['total_order_discount_percent'] = wpd_calculate_percentage( $this->results['total_order_discounts'], $this->results['total_order_revenue_before_discounts'] );

        // Calculate the margin on the tax-exclusive revenue
        $this->results['total_order_margin'] = wpd_calculate_margin( $this->results['total_order_profit'], $this->results['total_order_revenue_excluding_tax'], true );

    }

    /**
     * 
     *  Adjusts the costs for a fully refunded order based on user settings
     * 
     *  This method handles the conditional zeroing of cost elements when an order
     *  is fully refunded. Revenue and tax-related fields are always zeroed,
     *  while other cost fields (product COGS, custom product costs, shipping,
     *  payment gateway, custom order costs) are conditionally zeroed based on
     *  the user's 'wpd_ai_refunded_order_costs' settings.
     * 
     *  @since 4.7.0
     *  @return void
     * 
     **/
    private function adjust_costs_for_fully_refunded_order() {
        
        // Get refunded order costs settings
        $refunded_order_costs = wpd_get_refunded_order_costs_settings();

        // Target keys to null if fully refunded
        $array_keys_to_adjust_to_zero = array(

            // 'total_order_revenue_inc_tax_and_refunds', // Keep this to view original amount
            'total_order_revenue',
            'total_order_revenue_excluding_tax',
            'total_order_tax',

            // 'total_order_cost', // This will be calculated with our adjustment
            // 'total_order_profit', // This will be calculated with our adjustment
            // 'total_order_margin', // This will be calculated with our adjustment

            'total_product_cost', // The user settings will determine if this is included (COGS and product custom costs)
            'total_product_cost_of_goods', // The user settings will determine if this is included (COGS)
            'total_product_custom_costs', // The user settings will determine if this is included
            'total_shipping_cost', // The user settings will determine if this is included
            'payment_gateway_cost', // The user settings will determine if this is included
            'total_custom_order_costs', // The user settings will determine if this is included

            'total_shipping_charged',
            'total_product_revenue_at_rrp',
            'total_product_revenue',
            'total_product_profit',
            'total_product_revenue_ex_tax',
            // 'total_qty_sold',
            'total_order_revenue_before_coupons',
            'total_coupon_discounts',
            'total_coupon_discount_percent',
            'total_order_revenue_before_product_discounts',
            'total_product_discounts',
            'total_product_discount_percent',
            'total_order_revenue_before_discounts',
            'total_order_discounts',
            'total_order_discount_percent'
            
        );

        // Track which costs should be zeroed based on user settings
        $costs_to_zero = array();
        foreach( $array_keys_to_adjust_to_zero as $key ) {
            $costs_to_zero[$key] = true;
        }

        // Check user settings - if set to 1, they want to include the cost (don't zero it)
        if ( isset($refunded_order_costs['total_product_cost_of_goods']) && $refunded_order_costs['total_product_cost_of_goods'] == 1 ) {
            unset($costs_to_zero['total_product_cost_of_goods']);

            // Need to recalculate the product cost, because it's otherwise removed due to qty 0
            $refund_data = $this->results['refund_data'];
            if ( is_array($refund_data) && ! empty($refund_data) ) {
                foreach( $refund_data as $refund_item ) {
                    $this->results['total_product_cost_of_goods'] += $refund_item['cost_price_refund_exemption'];
                }
            }
        }
        if ( isset($refunded_order_costs['total_product_custom_costs']) && $refunded_order_costs['total_product_custom_costs'] == 1 ) {
            unset($costs_to_zero['total_product_custom_costs']);

            // Need to recalculate the product cost, because it's otherwise removed due to qty 0
            $product_data = $this->results['product_data'];
            foreach( $product_data as $product_data_item_id => $product_data_item_data ) {
                if ( isset($product_data_item_data['custom_product_cost_data']) && is_array($product_data_item_data['custom_product_cost_data']) && ! empty($product_data_item_data['custom_product_cost_data']) ) {
                    foreach( $product_data_item_data['custom_product_cost_data'] as $custom_product_cost_item_slug => $custom_product_cost_item_data ) {
                        $this->results['total_product_custom_costs'] += $custom_product_cost_item_data['unit_cost'] * $product_data_item_data['qty_refunded'];
                    }
                }
            }
        }
        if ( isset($refunded_order_costs['total_shipping_cost']) && $refunded_order_costs['total_shipping_cost'] == 1 ) {
            unset($costs_to_zero['total_shipping_cost']);
        }
        if ( isset($refunded_order_costs['payment_gateway_cost']) && $refunded_order_costs['payment_gateway_cost'] == 1 ) {
            unset($costs_to_zero['payment_gateway_cost']);
        }
        if ( isset($refunded_order_costs['total_custom_order_costs']) && $refunded_order_costs['total_custom_order_costs'] == 1 ) {
            unset($costs_to_zero['total_custom_order_costs']);
        }

        // Set all tracked costs to 0
        foreach( $costs_to_zero as $array_key => $should_zero ) {
            if ( $should_zero ) $this->results[$array_key] = 0;
        }

        // Clean up total product cost
        $this->results['total_product_cost'] = $this->results['total_product_cost_of_goods'] + $this->results['total_product_custom_costs'];

        // Adjust custom order costs to zero if the main cost type is being zeroed
        if ( is_array($this->results['custom_order_cost_data']) && ! empty($this->results['custom_order_cost_data']) && isset($costs_to_zero['total_custom_order_costs']) ) {
            foreach( $this->results['custom_order_cost_data'] as $slug => $value ) {
                $this->results['custom_order_cost_data'][$slug] = 0;
            }
        }

        // Adjust custom product costs to zero if the main cost type is being zeroed
        if ( is_array($this->results['custom_product_cost_data']) && ! empty($this->results['custom_product_cost_data']) && isset($costs_to_zero['total_product_custom_costs']) ) {
            foreach( $this->results['custom_product_cost_data'] as $slug => $value ) {
                $this->results['custom_product_cost_data'][$slug]['total_quantity'] = 0;
                $this->results['custom_product_cost_data'][$slug]['total_value'] = 0;
            }
        }

        // Update a few values
        $this->results['full_refund'] = 1;
        $this->results['partial_refund'] = 0;

    }

    /**
     * 
     *  Save calculations to cache
     * 
     */
    private function save_calculations() {

        // Convert back to originally passed order id, if requested
        $order_id = ( $this->refund_order_id > 0 ) ? $this->refund_order_id : $this->order_id;

        // Save Value
        $update = wpd_set_order_calculations_cache( $order_id, $this->results );

        // Log an error
        if ( $update === false ) wpd_write_log( 'Unable to update the order cache for Order ID: #' . $order_id, 'order_update' );

        // Log complete
        wpd_write_log( 'Saving calculations to custom calculation table for Order ID: #' . $order_id, 'order_update' );

    }

    /**
     * 
     *  Converts currency using stored order & store currency
     * 
     **/
    private function convert_currency( $amount ) {

        $converted_amount = (float) wpd_convert_currency($this->order_currency, $this->store_currency, (float) $amount, $this->exchange_rate );

        return $converted_amount;

    }

    /**
     * 
     *  The main method for returning the results of the calculation
     * 
     *  @return bool|array Returns false on failure (if an order cant be found), otherwise returns an associative array of the results
     * 
     */
    public function get_results() {

        // Return results if set
        if ( is_array($this->results) && ! empty($this->results) ) return $this->results;

        // Otherwise we failed
        return false;

    }

}