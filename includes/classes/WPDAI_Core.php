<?php
/**
 *
 * Core profit tracking functionality
 *
 * Add meta boxes to orders and products, save data, make calculations
 *
 * @package Alpha Insights
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

// Class init
class WPDAI_Core {

	/**
	 * 
	 *	Flag for checking whether a save is currently occurring 
	 *
	 **/
	private static $is_handling_save = false;

	public function __construct() {

		/**
		 *
		 *	Order inputs, data processing & admin display
		 *
		 */
		// Trigger calculation updates
		add_action( 'woocommerce_order_after_calculate_totals',			array( $this, 'save_update_order_details_recalculate' ), 10, 2 ); // For ajax recalculate button
		add_action( 'woocommerce_new_order',							array( $this, 'save_update_order_details' ), 10, 2 );
		add_action( 'woocommerce_update_order',							array( $this, 'save_update_order_details' ), 10, 2 ); 

		// Admin Order Columns
		add_filter( 'manage_edit-shop_order_columns', 					array( $this, 'register_admin_order_columns' ), 20 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', 		array( $this, 'register_admin_order_columns'), 10, 1 ); // HPOS
		add_action( 'manage_shop_order_posts_custom_column', 			array( $this, 'display_admin_order_column_data' ), 10, 2 );
		add_action( 'woocommerce_shop_order_list_table_custom_column', 	array( $this, 'display_admin_order_column_data'), 10, 2 ); // HPOS

		// Admin Meta Boxes
		add_action( 'add_meta_boxes', 									array( $this, 'register_order_admin_meta_boxes' ), 30 ); // Was priority 50

		// Admin order meta
		add_action( 'woocommerce_admin_order_item_headers', 			array( $this, 'add_cost_price_to_admin_order_meta_line_item_heading' ) );
		add_action( 'woocommerce_admin_order_item_values', 				array( $this, 'add_cost_price_to_admin_order_meta_line_item' ), 10, 3 );
		add_action( 'woocommerce_admin_order_totals_after_total', 		array( $this, 'show_profit_in_order_summary'), 100, 1 );
		add_filter( 'woocommerce_hidden_order_itemmeta', 				array( $this, 'hide_order_item_meta_in_admin' ), 10, 1 );
		add_action( 'woocommerce_after_order_itemmeta', 				array( $this, 'display_admin_order_item_meta' ), 10, 3 );

		/**
		 *
		 *	Product inputs, data processing & admin display
		 *
		 */
		// Product purchasing information tab
		add_filter( 'woocommerce_product_data_tabs', 					array( $this, 'register_product_cost_of_goods_tab') );
		add_action( 'woocommerce_product_data_panels', 					array( $this, 'output_product_cost_of_goods_tab_content' ) );

		// Save and process data inputs other than variations
		add_action( 'save_post_product',                            	array( $this, 'save_product_cog_data' ), PHP_INT_MAX, 2 );

		// Product admin columns
		add_filter( 'manage_edit-product_columns', 						array( $this, 'register_product_custom_columns'), 15 ); // $columns, $post_type
		add_action( 'manage_product_posts_custom_column', 				array( $this, 'print_product_custom_admin_columns' ), 10, 2 ); // $column_name, $post_id

		/**
		 *
		 *	User inputs, data processing & admin display
		 *
		 */
		// Admin Custom Columns
		add_filter( 'manage_users_columns', 							array( $this, 'register_user_custom_columns' ), 10, 1 ); // $columns
		// add_filter( 'manage_users_sortable_columns', 					array( $this, 'sortable_user_custom_columns' ), 10, 1 ); // $columns
		add_filter( 'manage_users_custom_column', 						array( $this, 'output_user_custom_columns' ), 10, 3 ); // $value, $column_name, $user_id

		// Save additional data to user meta when they register
		add_action( 'user_register', 									array( $this, 'update_user_meta_at_registration' ), 10, 2 );
		add_action( 'wp_login', 										array( $this, 'update_user_meta_at_login' ), 10, 2 );

		// Display account summary in user profile section in wp_admin
		add_action( 'edit_user_profile', 								array( $this, 'display_user_summary_in_wp_admin' ), 1, 1 );
		add_action( 'show_user_profile', 								array( $this, 'display_user_summary_in_wp_admin' ), 1, 1 );

	}

	/**
	 * 
	 * 	Adds additional data at time of registration
	 * 
	 * 	@param int $user_id User ID
	 * 	@param array $userdata The raw array of data passed to wp_insert_user()
	 * 
	 **/
	public function update_user_meta_at_registration( $user_id, $userdata ) {

		// Landing Page
		if ( ! empty( $_COOKIE['wpd_ai_landing_page'] ) ) {
			update_user_meta( $user_id, '_wpd_ai_landing_page', sanitize_text_field( $_COOKIE['wpd_ai_landing_page'] ) );
		}

		// Referral URL
		if ( ! empty( $_COOKIE['wpd_ai_referral_source'] ) ) {
			update_user_meta( $user_id, '_wpd_ai_referral_source', sanitize_text_field( $_COOKIE['wpd_ai_referral_source'] ) );
		}

		// Session ID
		if ( ! empty( $_COOKIE['wpd_ai_session_id'] ) ) {
			update_user_meta( $user_id, '_wpd_ai_session_id', sanitize_text_field( $_COOKIE['wpd_ai_session_id'] ) );
		}

		// Current URL
		update_user_meta( $user_id, '_wpd_ai_registration_url_current', wpdai_get_current_url_raw() );

		// Referral URL
		update_user_meta( $user_id, '_wpd_ai_registration_url_referral', wpdai_get_referral_url_raw() );

		// Last Login
		update_user_meta( $user_id, '_wpd_ai_last_login_unix', time() );

	}

	/**
	 * 
	 *	Update the last login date
	 * 
	 **/
	public function update_user_meta_at_login( $user_login, $user ) {

		update_user_meta( $user->ID, '_wpd_ai_last_login_unix', time() );

	}

	/**
	 * 
	 * 	Display user summary in WP Admin area
	 * 
	 **/
	public function display_user_summary_in_wp_admin( $user ) {

		// Safety Check
		if ( ! is_a($user, 'WP_User') ) return false;

		// Don't load HTML for non-authorized users
		if ( ! wpdai_is_user_authorized_to_view_alpha_insights() ) return false;

		// Cached data
		$user_data							= wpdai_fetch_customer_analytics_by_user_id( $user->ID );
		$registration_date 					= $user_data['registration_date_pretty'];
		$display_name 						= $user_data['display_name'];
		$registration_session_landing_page 	= $user_data['landing_page_url'];
		$registration_url 					= $user_data['registration_url'];
		$registration_url_referral 			= $user_data['registration_referral_url'];
		$last_login_date 		 			= $user_data['last_login_date_pretty'];
		$traffic_source 					= $user_data['referral_source'];
		$session_count 						= $user_data['total_session_count'];
		$order_count 						= $user_data['total_order_count'];
		$lifetime_value 					= $user_data['lifetime_value'];
		$average_order_value 				= $user_data['average_order_value'];
		$conversion_rate 					= $user_data['conversion_rate'];
		$last_updated 						= get_user_meta( $user->ID, '_wpd_ai_last_updated', true );
		$last_updated_date 					= ( ! empty($last_updated) && is_numeric($last_updated) ) ? get_date_from_gmt( gmdate( WPD_AI_PHP_ISO_DATETIME, $last_updated ), WPD_AI_PHP_PRETTY_DATETIME) : 'Not Set';
		
		// Logo Avatar
		$logo = '<span class="wpd-plugin-logo" style="vertical-align: middle; margin-right: 10px;"><img height="50" src="' . esc_url( wpdai_get_logo_icon_url() ) . '" class="alpha-insights-menu-logo"></span>';

		?>
		<table class="wpd-table widefat fixed" style="margin-top: 25px;">
			<thead>
				<tr>
					<th colspan="3"><?php echo wp_kses_post( $logo ); ?><span class="wpd-user-summary" style="font-size: 18px;">User Summary - <?php echo esc_html( $display_name ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<th colspan="1">Registration Data</th>
					<td colspan="2">
						<p><strong>Registration Date:</strong> <?php echo esc_html( $registration_date ); ?></p>
						<p><strong>Registration URL:</strong> <a href="<?php echo esc_url( $registration_url ); ?>" target="_blank"><?php echo esc_html( $registration_url ); ?></a></p>
						<p><strong>Registration Referral URL:</strong> <a href="<?php echo esc_url( $registration_url_referral ); ?>" target="_blank"><?php echo esc_html( $registration_url_referral ); ?></a></p>
						<p><strong>Landing Page URL:</strong> <a href="<?php echo esc_url( $registration_session_landing_page ); ?>" target="_blank"><?php echo esc_html( $registration_session_landing_page ); ?></a></p>
						<p><strong>Source:</strong> <?php echo esc_html( $traffic_source ); ?></p>
					</td>
				</tr>
				<tr>
					<th colspan="1">User Activity</th>
					<td colspan="2">
						<p><strong>Last Login Date:</strong> <?php echo esc_html( $last_login_date ); ?></p>
						<p><strong>Total Session Count:</strong> <?php echo absint( $session_count ); ?></p>
						<p><strong>Total Order Count:</strong> <?php echo absint( $order_count ); ?></p>
						<p><strong>Lifetime Value:</strong> <?php echo wp_kses_post( wc_price( $lifetime_value ) ); ?></p>
						<p><strong>Average Order Value:</strong> <?php echo wp_kses_post( wc_price( $average_order_value ) ); ?></p>
						<p><strong>Conversion Rate:</strong> <?php echo esc_html( $conversion_rate ); ?>%</p>
						<p><strong>Last Updated:</strong> <?php echo esc_html( $last_updated_date ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php

	}

	/**
	 *
	 * 	Save product COGS data directly to order item meta (Admin AJAX)
	 * 
	 */
	public function save_product_cogs_as_order_item_meta_admin_ajax( $item_id, $item ) {

		// Get product ID
		$product_id 		= $item->get_product_id();
		$variation_id 		= $item->get_variation_id();
		if ( is_numeric($variation_id) && $variation_id > 0 ) $product_id = $variation_id;

		$quantity 			= $item->get_quantity();
		$order 				= false;
		
		// Get cost price
		$cost_price = wpdai_get_cost_price_by_product_id( $product_id );

		// Save data to line item
		if ( is_numeric($cost_price) ) {

	   		$item->update_meta_data( '_wpd_ai_product_cogs', $cost_price );
	   		$item->save();

		}

	}

	/**
	 *
	 * 	Save product COGS data directly to order item meta
	 * 
	 */
	public function save_product_cogs_as_order_item_meta( $item, $cart_item_key, $values, $order ) {

		if ( ! empty( $values['variation_id'] ) && is_numeric($values['variation_id']) ) {
			$product_id = $values['variation_id'];
		} else {
			$product_id = $values['product_id'];
		}

		// Get cost price
		$cost_price 			= wpdai_get_cost_price_by_product_id( $product_id );
		$quantity 				= $item->get_quantity();

		// Save data to line item
		if ( is_numeric($cost_price) ) {

	   		$item->update_meta_data( '_wpd_ai_product_cogs', $cost_price );
		   	$item->save();

		}

	}

	/**
	 *
	 *	Hide admin order item meta
	 *
	 */
	function hide_order_item_meta_in_admin( $arr ) {

		$arr[] = '_wpd_ai_product_cogs';
		$arr[] = '_wpd_ai_product_cogs_currency';
		$arr[] = '_wpd_ai_multi_cogs_total_cost';
		$arr[] = '_wpd_ai_multi_cogs_data';
		$arr[] = '_wpd_ai_multi_cogs_stock_withdrawn';

	    return $arr;

	}

	/**
	 *
	 *	Display order item meta that we want to
	 *	Will only show for WC_Order_Item_Product
	 *
	 */
	function display_admin_order_item_meta( $item_id, $item, $product ) {

	    // Only "line" items and backend order pages
	    if( ! ( is_admin() && $item->is_type('line_item') ) )
	        return;

		// Line item products only
		if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
			return;
		}

		// Make sure id is available
		if ( ! is_a( $product, 'WC_Product' ) ) {
			return;
		}

		// Don't load HTML for non-authorized users
		if ( ! wpdai_is_user_authorized_to_view_alpha_insights() ) return false;

		// Display product COGS data
		$product_id 				= $product->get_id();
		$line_item_cogs 			= $item->get_meta( '_wpd_ai_product_cogs' );
		$default_line_item_cogs 	= wpdai_get_cost_price_by_product_id( $product_id );

		// COGS input
		$currency_string = wpdai_store_currency_string();
		woocommerce_wp_text_input( array(
			'id' => 'line-item-cogs[' . $item_id . ']',
			/* translators: %s: Currency code */
			'label' => sprintf( __( 'Cost Of Goods Per Unit (%s)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $currency_string ),
			'value' => $line_item_cogs,
			'placeholder' => esc_attr( wp_strip_all_tags( wc_price( $default_line_item_cogs ) ) ),
			'wrapper_class' => 'form-field-wide wpd-line-item-cogs',
			'data_type' => 'price',
			'style' => 'max-width: 150px; display: block;'
		) );

		// Custom product cogs
		$custom_product_costs = wpdai_get_custom_product_cost_options( $product_id );
		if ( is_array($custom_product_costs) && ! empty($custom_product_costs) ) {

			$custom_cost_meta = $item->get_meta( '_wpd_ai_custom_product_costs' );

			foreach( $custom_product_costs as $custom_cost_slug => $custom_cost_data ) {

				// Calculated value
				$default_calculated_value = (float) wpdai_calculate_custom_product_cost_by_line_item( $item, $custom_cost_data );

				// Saved Value
				$custom_cost_meta_value = ( isset( $custom_cost_meta[$custom_cost_slug] ) ) ? (float) $custom_cost_meta[$custom_cost_slug] : null;

				/**
				 * 
				 * 	Sets the default custom cost value for a custom product cost.
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
				$default_calculated_value = (float) apply_filters( 'wpd_ai_custom_product_cost_default_value', $default_calculated_value, $custom_cost_slug, $item->get_order(), $item );

				woocommerce_wp_text_input (
					array (

						'id'          => '_wpd_ai_custom_product_costs['.$item_id.']['.$custom_cost_slug.']',
						/* translators: 1: Custom cost label, 2: Currency code */
						'label' 	  => sprintf( __( '%1$s Per Unit (%2$s)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $custom_cost_data['label'] ), esc_html( wpdai_store_currency_string() ) ),
						'value'       => $custom_cost_meta_value,
						'data_type'   => 'price',
						'placeholder' => wp_strip_all_tags( wc_price( $default_calculated_value ) ),
						'wrapper_class' => 'form-field-wide wpd-line-item-cogs',
						'style' => 'max-width: 150px; display: block;'

					)
				);

			}

		}

	}

	/**
	 *
	 *	Show profit in order summary on edit order page
	 *	@todo need to make sure this factors in any manual override
	 *
	 */
	public function show_profit_in_order_summary( $order_id ) {

		// Don't load HTML for non-authorized users
		if ( ! wpdai_is_user_authorized_to_view_alpha_insights() ) return false;

	    // Here set your data and calculations
	    $order_data 				= wpdai_calculate_cost_profit_by_order( $order_id );
	    $calculation_string 		= 'Net Profit = Net Sales (Excl. Tax) - Total Order Costs';
	    $string_styles 				= 'font-size: 90%; opacity: .5; border-top: solid 1px #888; border-bottom: solid 1px #888; text-align: right;';

	    /**
	     *
	     *	Create profit calculation string
	     *
	     */
		?>
			<tr class="wpd-order-meta-summary">
				<th colspan="3">Profit Summary</th>
			</tr>
			<tr>
				<td colspan="3" style="<?php echo esc_attr( $string_styles ); ?>"><?php echo wp_kses_post( $calculation_string ); ?></td>
			</tr>
			<tr class="wpd-order-meta-summary">
				<td class="label">Net Sales<?php if ( $order_data['total_order_tax'] > 0 ) echo ' Excl. Tax'; ?></td>
				<td width="1%"></td>
				<td class="total"><?php echo wp_kses_post( wc_price( $order_data['total_order_revenue_excluding_tax'] ) ); ?></td>
			</tr>
			<?php if ( $order_data['total_order_tax'] > 0 ) : ?>
				<tr class="wpd-order-meta-summary">
					<td class="label">Sales Tax:</td>
					<td width="1%"></td>
					<td class="total"><?php echo wp_kses_post( wc_price( $order_data['total_order_tax'] ) ); ?></td>
				</tr>
			<?php endif; ?>
			<tr class="wpd-order-meta-summary">
	            <td class="label">Total Order Costs:</td>
	            <td width="1%"></td>
	            <td class="total"><?php echo wp_kses_post( wc_price( $order_data['total_order_cost'] ) ); ?></td>
	        </tr>
			<tr class="wpd-order-meta-summary">
	            <td class="label">Net Profit:</td>
	            <td width="1%"></td>
	            <td class="total"><?php echo wp_kses_post( wc_price( $order_data['total_order_profit'] ) ); ?></td>
	        </tr>
			<tr class="wpd-order-meta-summary">
	            <td class="label">Margin:</td>
	            <td width="1%"></td>
	            <td class="total amount"><?php echo esc_html( $order_data['total_order_margin'] ); ?>%</td>
	        </tr>
	    <?php

	}

	/**
	 *
	 *	Add heading for order line item data
	 *
	 */
	public function add_cost_price_to_admin_order_meta_line_item_heading( $order ){

		// Don't load HTML for non-authorized users
		if ( ! wpdai_is_user_authorized_to_view_alpha_insights() ) return false;

		?><th class="wpd_cogs" width="300px">Alpha Insights COGS</th><?php

	}

	/**
	 *
	 *	Add data to order line item
	 *	@link https://woocommerce.github.io/code-reference/classes/WC-Order-Item-Product.html
	 *	@todo rewrite this based off our order cache collector so they are in sync
	 *
	 */
	public function add_cost_price_to_admin_order_meta_line_item( $product, $item, $item_id = null ) {

		// Don't load HTML for non-authorized users
		if ( ! wpdai_is_user_authorized_to_view_alpha_insights() ) return false;

		// Only continue if product is object and item is of type product
		if ( is_a( $item, 'WC_Order_Item_Product' ) && is_a( $product, 'WC_Product' ) ) :

			// Load Vars
			$multi_currency = 0;
			$product_data 	= array();
			$order_data 	= wpdai_calculate_cost_profit_by_order( $item->get_order() );
			
			// Try get the data we need
			if ( is_array($order_data) && is_array($order_data['product_data']) ) {

				foreach( $order_data['product_data'] as $product_id => $product_sales_data ) {

					if ( $product_sales_data['item_id'] == $item->get_id() ) {
						$product_data = $product_sales_data;
						break;
					}

				}

				// Is Multi Currency Order?
				$multi_currency = (int) $order_data['multi_currency_order'];

			}

			// Only proceed if we have data
			if ( empty($product_data) ) {
				?>
					<td class="wpd_cogs" width="20%" data-sort-value="">
						<div class="view">
							<div class="wpd-line-item-summary">
							</div>
						</div>
					</td>
				<?php
				return false;
			}

			if ( $multi_currency ) {

				$order_currency = $order_data['order_currency'];
				$original_amount = wc_price( $item->get_total(), array('currency' => $order_currency) );
				$currency_conversion_string = sprintf( 
					/* translators: 1: Currency code, 2: Original amount */
					__( 'This order was paid for in %1$s. <br>The original amount is %2$s.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 
					$order_currency, 
					$original_amount 
				);

			}
			?>
			<td class="wpd_cogs" width="300px">
				<div class="view">
					<div class="wpd-line-item-summary">
						<table class="display_meta fixed wpd-line-item-summary">
							<tbody>
								<tr>
									<th><?php esc_html_e( 'Revenue Excl. Tax:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?><?php if ( $multi_currency ) echo esc_html( ' (' . wpdai_get_store_currency() . ')' ); ?></th>
									<td><?php echo wp_kses_post( wc_price( $product_data['product_revenue_excluding_tax'] ) ); ?></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Cost Of Goods:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
									<td><?php echo wp_kses_post( wc_price( $product_data['total_product_cogs'] ) ); ?></td>
								</tr>
								<?php if ( $product_data['total_custom_product_cost'] ) : ?>
									<tr>
										<th><?php esc_html_e( 'Custom Costs:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
										<td><?php echo wp_kses_post( wc_price( $product_data['total_custom_product_cost'] ) ); ?></td>
									</tr>
								<?php endif; ?>
								<tr>
									<th><?php esc_html_e( 'Profit:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
									<td><?php echo wp_kses_post( wc_price( $product_data['total_profit'] ) ); ?></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Margin:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
									<td><?php echo esc_html( $product_data['product_margin'] ); ?>%</td>
								</tr>
								<?php if ( $multi_currency) : ?>
									<tr><td colspan="2"><?php echo wp_kses_post( $currency_conversion_string ); ?></td></tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</td>
		<?php else : ?>
			<td class="wpd_cogs" width="20%" data-sort-value="">
				<div class="view">
					<div class="wpd-line-item-summary">
					</div>
				</div>
			</td>
		<?php endif;

	}

	/**
	 *
	 *	Add meta to admin order page
	 *
	 */
	public function register_order_admin_meta_boxes() {

		// Don't load HTML for non-authorized users
		if ( ! wpdai_is_user_authorized_to_view_alpha_insights() ) return false;

		// For compatability with WC HPOS
		// Fixed: Proper class reference in get() method
		$screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

		// Remove the order data meta box
		// remove_meta_box('woocommerce-order-data', $screen, 'normal');

		// Profit Summary
		add_meta_box ( 
			'wpd-ai-dashboard-summary', 
			__( 'Alpha Insights Dashboard', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			array( $this, 'order_admin_metabox_dashboard' ), 
			$screen, // Screen to display on
			'normal', // normal, side, advanced
			'high' // high, core, default, low
		);

	}

	/**
	 * 
	 * 	Summary Dashboard
	 * 
	 **/
	public function order_admin_metabox_dashboard( $post_or_order_object ) {

		// Don't load HTML for non-authorized users
		if ( ! wpdai_is_user_authorized_to_view_alpha_insights() ) return false;

		// Load the order
		$order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
		if ( ! is_a( $order, 'WC_Order' ) ) return false;

		// Defaults
		$order_count 				= 0;
		$lifetime_value				= 0;
		$average_order_value 		= 0;

		// Capture Vars
		$order_id 					= $order->get_id();
		$meta_total_shipping_cost 	= $order->get_meta( '_wpd_ai_total_shipping_cost' );
		$meta_payment_gateway_cost 	= $order->get_meta( '_wpd_ai_total_payment_gateway_cost' );
		$meta_total_product_cost  	= $order->get_meta( '_wpd_ai_total_product_cost' );
		$landing_page 				= $order->get_meta( '_wpd_ai_landing_page' );
		$referral 					= $order->get_meta( '_wpd_ai_referral_source' );

		// Custom Order Costs
		$custom_order_costs 		= wpdai_get_custom_order_cost_options();

		// Calculate Totals
		$order_data 				= wpdai_calculate_cost_profit_by_order( $order_id );
		$total_product_cost 		= $order_data[ 'total_product_cost_of_goods' ];
		$total_shipping_cost 		= $order_data[ 'total_shipping_cost' ];
		$payment_gateway_cost 		= $order_data[ 'payment_gateway_cost' ];
		$new_returning_customer 	= $order_data[ 'new_returning_customer' ];
		$total_order_revenue 		= $order_data[ 'total_order_revenue_excluding_tax' ];
		$total_order_tax  			= $order_data[ 'total_order_tax' ];

		// Other Calculations
		$query_params 				= wpdai_get_query_params( $landing_page );
		$traffic_source 			= wpdai_get_traffic_type( $referral, $query_params );

		// Haven't really loaded anything in yet
		if ( $order->get_status() == 'auto-draft'  ) {

			$session_count 			= 0;
			$order_count 			= 0;
			$lifetime_value 		= 0;
			$average_order_value 	= 0;
			$new_returning_customer = 'N/A';

		} else {

			// Force by billing email for now
			$ip_address 			= $order->get_customer_ip_address();
			$billing_email 			= $order->get_billing_email();
			$session_count 			= wpdai_get_session_count_by_ip_address( $ip_address );

			// Customer Details
			if ( is_string( $billing_email ) && ! empty( $billing_email ) ) {

				// Call the data warehouse for this customer
				$data_warehouse = new WPDAI_Data_Warehouse( 
					array(
						'date_preset' => 'all_time',
						'data_filters' => array(
							'orders' => array(
								'billing_email' => array( $billing_email )
							)
						)
					) 
				);

				// Fetch order data
				$data_warehouse->fetch_sales_data();
				$customer_order_data = $data_warehouse->get_data( 'orders', 'totals' );

				// Safety check the data warehouse
				if ( is_array( $customer_order_data ) && ! empty( $customer_order_data ) ) {

					$order_count = $customer_order_data['total_order_count'];
					$lifetime_value = $customer_order_data['total_order_revenue'];
					$average_order_value = $customer_order_data['average_order_revenue'];

				} else {

					$order_count 			= wpdai_customer_order_count_by_email_address($billing_email); // Review this
					$lifetime_value			= wpdai_customer_lifetime_value_by_email_address($billing_email);
					$average_order_value 	= wpdai_customer_average_order_value_by_email_address($billing_email);

				}

			}

		}
		
		// Calculate conversion rate
		$conversion_rate = ( $session_count == 0 || $order_count == 0 ) ? 'N/A' : wpdai_calculate_percentage( $order_count, $session_count ) . '%';?>
		<?php do_action('wpd_ai_order_dashboard_before_content', $order); ?>
		<div class="wpd-order-dashboard">
			<!-- Titles -->
			<div class="wpd-order-title">Order Overview</div>
			<div class="wpd-order-title">Order Costs</div>
			<div class="wpd-order-title">Session</div>
			<div class="wpd-order-title">Customer (By Billing Email)</div>
			<!-- Key Order Stats -->
			<div class="wpd-order-stats wpd-stats-grid">
				<div class="wpd-order-stat">
					<div class="wpd-order-stat-data wpd-statistic"><?php echo wp_kses_post( wc_price( $total_order_revenue ) ); ?></div>
					<div class="wpd-order-stat-label wpd-meta">Net Sales<?php if ( $total_order_tax > 0 ) echo esc_html( ' Excl. Tax' ); ?></div>
				</div>
				<div class="wpd-order-stat">
					<div class="wpd-order-stat-data wpd-statistic"><?php echo wp_kses_post( wc_price( $order_data['total_order_cost'] ) ); ?></div>
					<div class="wpd-order-stat-label wpd-meta">Total Order Costs</div>
				</div>
				<div class="wpd-order-stat">
					<div class="wpd-order-stat-data wpd-statistic"><?php echo wp_kses_post( wc_price( $order_data['total_order_profit'] ) ); ?></div>
					<div class="wpd-order-stat-label wpd-meta">Gross Profit</div>
				</div>
				<div class="wpd-order-stat">
					<div class="wpd-order-stat-data wpd-statistic"><?php echo esc_html( $order_data['total_order_margin'] ); ?>%</div>
					<div class="wpd-order-stat-label wpd-meta">Gross Profit Margin %</div>
				</div>
				<div class="wpd-order-stat">
					<div class="wpd-order-stat-data wpd-statistic"><?php echo absint( $order_data['total_qty_sold'] ); ?></div>
					<div class="wpd-order-stat-label wpd-meta">Total Qty Ordered</div>
				</div>
				<div class="wpd-order-stat">
					<div class="wpd-order-stat-data wpd-statistic"><?php echo esc_html( round( $order_data['total_order_discount_percent'], 2 ) ); ?>%</div>
					<div class="wpd-order-stat-label wpd-meta">Total Discount %</div>
				</div>
			</div>
			<!-- Cost Input Overrides -->
			<div class="wpd-order-cost-inputs">
				<div class="edit_address"><?php
					$currency_string = wpdai_store_currency_string();
					woocommerce_wp_text_input( array(
						'id' => 'total_product_cost',
						/* translators: %s: Currency code */
						'label' => sprintf( __( 'Total Product Cost Of Goods (%s)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $currency_string ) . ':',
						'value' => $meta_total_product_cost,
						'placeholder' => wp_strip_all_tags( wc_price( $total_product_cost ) ),
						'wrapper_class' => 'form-field-wide',
						'data_type' => 'price'
					) );

					// Custom Order Costs
					if ( is_array($order_data['product_data']) && ! empty($order_data['product_data'])  ) {

						foreach( $order_data['product_data'] as $product_id_slug => $product_data ) {

							// Check that there's at least one product in this order that has a custom order cost defined
							if ( ! empty( wpdai_get_custom_product_cost_options($product_data['product_id']) ) ) {

								// Get the meta value
								$meta_total_order_product_custom_cost = $order->get_meta( '_wpd_ai_total_order_product_custom_cost' );

								woocommerce_wp_text_input( array(
									'id' => '_wpd_ai_total_order_product_custom_cost',
									/* translators: %s: Currency code */
									'label' => sprintf( __( 'Total Product Custom Costs (%s)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), wpdai_store_currency_string() ) . ':',
									'value' => $meta_total_order_product_custom_cost,
									'placeholder' => wp_strip_all_tags( wc_price( $order_data['total_product_custom_costs'] ) ),
									'wrapper_class' => 'form-field-wide',
									'data_type' => 'price'
								) );

								break;

							}

						}

					}

					woocommerce_wp_text_input( array(
						'id' => 'total_shipping_cost',
						/* translators: %s: Currency code */
						'label' => sprintf( __( 'Total Shipping Cost (%s)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), wpdai_store_currency_string() ) . ':',
						'value' => $meta_total_shipping_cost,
						'placeholder' => wp_strip_all_tags( wc_price( $total_shipping_cost ) ),
						'wrapper_class' => 'form-field-wide',
						'data_type' => 'price'
					) );

					woocommerce_wp_text_input( array(
						'id' => 'payment_gateway_cost',
						/* translators: %s: Currency code */
						'label' => sprintf( __( 'Payment Gateway Cost (%s)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), wpdai_store_currency_string() ) . ':',
						'value' => $meta_payment_gateway_cost,
						'placeholder' => esc_attr( wp_strip_all_tags( wc_price( $payment_gateway_cost ) ) ),
						'wrapper_class' => 'form-field-wide',
						'data_type' => 'price'
					) );

					// Custom Order Costs
					foreach( $custom_order_costs as $cost_slug => $cost_data ) {

						$custom_order_cost_placeholder = ( isset( $order_data['custom_order_cost_data'][$cost_slug] ) ) ? $order_data['custom_order_cost_data'][$cost_slug] : 0;
						$custom_order_cost_value = $order->get_meta( '_wpd_ai_custom_order_cost_' . $cost_slug );

						// Check if the value has been filtered
						if ( isset($order_data['custom_order_cost_data']) && ! empty($order_data['custom_order_cost_data']) ) {
							if ( isset($order_data['custom_order_cost_data'][$cost_slug]) ) {
								$custom_order_cost_placeholder = $order_data['custom_order_cost_data'][$cost_slug];
							}
						}

						woocommerce_wp_text_input( array(
							'id' => '_wpd_ai_custom_order_cost_' . $cost_slug,
							/* translators: 1: Cost label, 2: Currency code */
							'label' => sprintf( __( '%1$s (%2$s)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $cost_data['label'] ), esc_html( wpdai_store_currency_string() ) ) . ':',
							'value' => $custom_order_cost_value, // Need to figure out
							'placeholder' => esc_attr( wp_strip_all_tags( wc_price( $custom_order_cost_placeholder ) ) ),
							'wrapper_class' => 'form-field-wide',
							'data_type' => 'price'
						) );
					}
					?>
				</div>
			</div>
			<!-- Attributions -->
			<div class="wpd-order-attribution">
				<table class="wpd-data-table">
					<tbody>
						<tr>
							<td><strong>Traffic Source</strong></td>
							<td><?php echo esc_html( $traffic_source ); ?></td>
						</tr>
						<tr>
							<td><strong>Referral Source</strong></td>
							<td><?php echo esc_html( $referral ); ?></td>
						</tr>
						<tr>
							<td><strong>Landing Page</strong></td>
							<td><?php echo esc_html( wpdai_strip_query_parameters_from_url( $landing_page ) ); ?></td>
						</tr>
						<tr>
							<td colspan="2">
								<strong>Query Parameters</strong>
								<?php if ( ! empty($query_params) && is_array($query_params) ) : ?>
									<div class="wpd-grid-2">
										<?php foreach( $query_params as $key => $value ) : ?>
												<span><?php echo esc_html( ucfirst( $key ) ); ?>:</span>
												<span><?php echo esc_html( ucfirst( $value ) ); ?></span>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</td>
						</tr>
						<?php do_action('wpd_ai_order_attribution_table_rows', $order); ?>
					</tbody>
				</table>
			</div>
			<div class="wpd-order-customer-activity wpd-stats-grid">
			<div class="wpd-order-stat">
					<div class="wpd-order-stat-data wpd-statistic"><?php echo esc_html( ucfirst($new_returning_customer) ); ?></div>
					<div class="wpd-order-stat-label wpd-meta">New vs Returning</div>
				</div>
				<div class="wpd-order-stat">
					<div class="wpd-order-stat-data wpd-statistic"><?php echo wp_kses_post( wc_price( $lifetime_value ) ); ?></div>
					<div class="wpd-order-stat-label wpd-meta">Lifetime Value</div>
				</div>
				<div class="wpd-order-stat">
					<div class="wpd-order-stat-data wpd-statistic"><?php echo absint( $order_count ); ?></div>
					<div class="wpd-order-stat-label wpd-meta">Total Order Count</div>
				</div>
				<div class="wpd-order-stat">
					<div class="wpd-order-stat-data wpd-statistic"><?php echo wp_kses_post( wc_price( $average_order_value ) ); ?></div>
					<div class="wpd-order-stat-label wpd-meta">AOV</div>
				</div>
				<div class="wpd-order-stat">
					<div class="wpd-order-stat-data wpd-statistic"><?php echo absint( $session_count ); ?></div>
					<div class="wpd-order-stat-label wpd-meta">Session Count</div>
				</div>
				<div class="wpd-order-stat">
					<div class="wpd-order-stat-data wpd-statistic"><?php echo esc_html( $conversion_rate ); ?></div>
					<div class="wpd-order-stat-label wpd-meta">Conversion Rate</div>
				</div>
			</div>
			<div class="wpd-grid-footer wpd-grid-full-span">
				<p style="float:left;">You can use the arrows in the top right corner to reposition this dashboard.<br>Don't want this dashboard? <a href="<?php echo esc_url( wpdai_admin_page_url( 'settings' ) ); ?>">Update your WordPress Admin Display Extensions settings</a> in Alpha Insights.</p>
				<p style="float:right;"><?php submit_button( __( 'Save & Recalculate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'primary wpd-ai-submit', 'submit', false ); ?></p>
			</div>
		</div>
		<?php

	}

	/**
	 *
	 *	Force refresh on order recalculations
	 *
	 */
	public function save_update_order_details_recalculate( $and_taxes, $order ) {

		if ( did_action( 'woocommerce_order_after_calculate_totals' ) >= 2 )
	    	return;

	    if ( is_admin() && ! defined( 'DOING_AJAX' ) )
	        return;

		if ( is_a( $order, 'WC_Order' ) ) {

			$order_id = $order->get_id();
			$this->save_update_order_details( $order_id, $order );

		}

	}

	/**
	 *
	 *	Save our order details
	 *
	 */
	public function save_update_order_details( $order_id, $order ) {

		// Prevent any recursions
		if (self::$is_handling_save) return;
		self::$is_handling_save = true;

		// Just in case
		if ( ! is_a($order, 'WC_Order') ) {
			$order = wc_get_order( $order_id );
		}

		if ( is_a($order, 'WC_Order') ) {

			// Current action for debugging
			$action = current_action();
			$url = wpdai_get_current_url_path_raw();

			// Safety check statuses that we don't want to process yet
			if ( $order->get_status() == 'auto-draft' || $order->get_status() == 'draft' || $order->get_status() == 'checkout-draft' ) {
				self::$is_handling_save = false;
				return false;
			}

			// Saves relevant _COOKIES and session data to the order
			$this->save_landing_page_to_order_meta( $order );

			// Process Totals - Note: These are from WooCommerce admin order edit page, WooCommerce handles security
			if ( isset($_POST[ 'total_product_cost' ]) ) {
				$total_product_cost = wc_format_decimal( sanitize_text_field( $_POST[ 'total_product_cost' ] ) );
				$order->update_meta_data( '_wpd_ai_total_product_cost', $total_product_cost );
			}
			if ( isset($_POST[ '_wpd_ai_total_order_product_custom_cost' ]) ) {
				$total_product_cost = wc_format_decimal( sanitize_text_field( $_POST[ '_wpd_ai_total_order_product_custom_cost' ] ) );
				$order->update_meta_data( '_wpd_ai_total_order_product_custom_cost', $total_product_cost );
			}
			if ( isset($_POST[ 'payment_gateway_cost' ]) ) {
				$total_payment_gateway_cost = wc_format_decimal( sanitize_text_field( $_POST[ 'payment_gateway_cost' ] ) );
				$order->update_meta_data( '_wpd_ai_total_payment_gateway_cost', $total_payment_gateway_cost );
			}
			if ( isset($_POST[ 'total_shipping_cost' ]) ) {
				$total_shipping_cost = wc_format_decimal( sanitize_text_field( $_POST[ 'total_shipping_cost' ] ) );
				$order->update_meta_data( '_wpd_ai_total_shipping_cost', $total_shipping_cost );
			}

			// Process custom order costs
			$custom_order_costs = wpdai_get_custom_order_cost_options();
			foreach( $custom_order_costs as $cost_slug => $cost_data ) {
				$custom_cost_meta_key = '_wpd_ai_custom_order_cost_' . sanitize_key( $cost_slug );
				if ( isset($_POST[$custom_cost_meta_key]) ) {
					$custom_cost_value = wc_format_decimal( sanitize_text_field( $_POST[$custom_cost_meta_key] ) );
					$order->update_meta_data( $custom_cost_meta_key, $custom_cost_value );
				}
			}

			// This is the AJAX recalculate button, let's transform the line item cogs in case the user has changed them
			if ( isset($_POST['action']) && sanitize_text_field( $_POST['action'] ) === 'woocommerce_calc_line_taxes') {

				// Search for Line Items
				if ( isset($_POST['items']) && ! empty($_POST['items']) ) {

					$undecoded_items = urldecode( sanitize_text_field( $_POST['items'] ) );
					wp_parse_str( $undecoded_items, $order_item_data );

					// Now let's check if we've got some line item COGS & store it in the post request if there isn't anything there already
					if ( isset($order_item_data['line-item-cogs']) && ! isset($_POST['line-item-cogs']) ) {
						$_POST['line-item-cogs'] = $order_item_data['line-item-cogs'];
					}

				}

			}
	
			// Process Line Item COGS
			if ( isset($_POST['line-item-cogs']) && ! empty($_POST['line-item-cogs']) && is_array($_POST['line-item-cogs']) ) {
	
				$line_item_cogs_data = array_map( 'sanitize_text_field', $_POST['line-item-cogs'] );
				foreach( $line_item_cogs_data as $item_id => $line_item_cogs ) {
	
					$line_item_cogs = wc_format_decimal( $line_item_cogs );
					$item_id = absint( $item_id );
	
					if ( $line_item_cogs == 0 || ! empty($line_item_cogs) ) {
						wc_update_order_item_meta( $item_id, '_wpd_ai_product_cogs', $line_item_cogs );
					} else {
						wc_update_order_item_meta( $item_id, '_wpd_ai_product_cogs', null );
					}
	
				}
	
			}

			// Process Custom Product Costs
			if ( isset($_POST['_wpd_ai_custom_product_costs']) && ! empty($_POST['_wpd_ai_custom_product_costs']) && is_array($_POST['_wpd_ai_custom_product_costs']) ) {

				foreach( $_POST['_wpd_ai_custom_product_costs'] as $item_id => $custom_cost_data ) {
				
					$item_id = absint( $item_id );
					
					// Sanitize data
					if ( is_array( $custom_cost_data ) ) {
						foreach( $custom_cost_data as $slug => $cost ) {

							$custom_cost = wc_format_decimal( sanitize_text_field( $cost ) );

							if ( ! empty($custom_cost ) || $custom_cost  == 0 ){
								$custom_cost_data[$slug] = $custom_cost;
							} else {
								$custom_cost_data[$slug] = null;
							}

						}
					}

					wc_update_order_item_meta( $item_id, '_wpd_ai_custom_product_costs', $custom_cost_data );

				}
	
			}

			// If campaign ID is set
			if ( isset($_POST['wpd_ai_order_google_campaign_id']) ) {

				$campaign_id_raw = sanitize_text_field( $_POST['wpd_ai_order_google_campaign_id'] );
				
				// Google - empty
				if ( empty( $campaign_id_raw ) ) {
					$order->delete_meta_data( '_wpd_ai_google_campaign_id' );
				}

				// Google - filled
				if ( is_numeric( $campaign_id_raw ) ) {
					$campaign_id = absint( $campaign_id_raw );
					$order->update_meta_data( '_wpd_ai_google_campaign_id', $campaign_id );
				}

			}

			// If campaign ID is set
			if ( isset($_POST['wpd_ai_order_meta_campaign_id']) ) {

				$campaign_id_raw = sanitize_text_field( $_POST['wpd_ai_order_meta_campaign_id'] );
				
				// Meta - empty
				if ( empty( $campaign_id_raw ) ) {
					$order->delete_meta_data( '_wpd_ai_meta_campaign_id' );
				}

				// Meta - filled
				if ( is_numeric( $campaign_id_raw ) ) {
					$campaign_id = absint( $campaign_id_raw );
					$order->update_meta_data( '_wpd_ai_meta_campaign_id', $campaign_id );
				}

			}

			// Save meta values
			$order->save_meta_data();
	
			// Calculate totals ... MUST force a resave otherwise all data will not be saved
			wpdai_calculate_cost_profit_by_order( $order_id, true );
			self::$is_handling_save = false;
			return true;

		}

	}

	/**
	 * Adds 'Profit' column header to 'Orders' page immediately after 'Total' column.
	 *
	 * @param string[] $columns
	 * @return string[] $new_columns
	 */
	public function register_admin_order_columns( $columns ) {

		// Don't load HTML for non-authorized users
		if ( ! wpdai_is_user_authorized_to_view_alpha_insights() ) return $columns;

		if ( isset($columns['order_total']) ) {
			unset( $columns['order_total'] );
		}

		if ( isset($columns['origin']) ) {
			unset( $columns['origin'] );
		}

	    $columns['order_profit'] 	= __( 'Profit', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
	    $columns['order_margin'] 	= __( 'Margin', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
	    $columns['order_total'] 	= __( 'Total', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); // Reorders order_total

		// Custom Columns
		$custom_admin_columns = wpdai_get_admin_custom_column_settings();

		if ( is_array($custom_admin_columns) && isset($custom_admin_columns['orders']) ) {

			$order_admin_columns = $custom_admin_columns['orders'];

			foreach( $order_admin_columns as $key => $label ) $columns[$key] = $label;
			
		}



	    return $columns;

	}

	/**
	 * Adds 'Profit' column content to 'Orders' page immediately after 'Total' column.
	 *
	 * @param string[] $column name of column being displayed
	 *	@todo revisit this with calculations function
	 */
	public function display_admin_order_column_data( $column, $post_id_or_order_object ) {

		if ( 'order_profit' == $column || 'order_margin' == $column || 'wpd_ai_new_vs_returning' == $column ) {

			// Get order ID
			if ( is_a($post_id_or_order_object, 'WC_Order') ) {
				$order_id = $post_id_or_order_object->get_id();
			} else {
				if ( is_numeric($post_id_or_order_object) && $post_id_or_order_object > 0 ) {
					$order_id = $post_id_or_order_object;
				} else {
					global $post;
					$order_id = $post->ID;
				}
			}

			// Data
	        $order_datas 			= wpdai_calculate_cost_profit_by_order( $order_id );
			$calculated_profit 		= $order_datas['total_order_profit'];
			$margin 				= $order_datas['total_order_margin'];
			$new_returning 			= $order_datas['new_returning_customer'];

		    if ( 'order_profit' == $column ) {

				if ( empty( $calculated_profit ) ) {

					echo wp_kses_post( wc_price( 0 ) );

				} else {

					echo wp_kses_post( wc_price( $calculated_profit ) );

				}

		    } elseif ( 'order_margin' == $column ) {
		    	
		    	echo esc_html( $margin ) . '%';

		    } elseif ( 'wpd_ai_new_vs_returning' == $column ) {
		    	
				$data_tip = ($new_returning == 'new') ? 'This is the first order placed by this email address' : 'This email address has placed an order prior to this date';
				echo '<mark class="wpd-new-vs-returning-customer order-status tips customer-' . esc_attr( strtolower( $new_returning ) ) . '" data-tip="' . esc_attr( $data_tip ) . '"><span>' . esc_html( ucfirst( $new_returning ) ) . ' Customer</span></mark>';
	
			}

		} 

		if ( $column == 'wpd_ai_traffic_source' ) {

			if ( is_a($post_id_or_order_object, 'WC_Order') ) {
				$order = $post_id_or_order_object; // HPOS hook
			} else {
				$order = wc_get_order( $post_id_or_order_object );
			}

			if ( ! is_a($order, 'WC_Order') ) return false;

			$order_data 				= wpdai_calculate_cost_profit_by_order( $order );
			$referral_source_url 		= $order_data['referral_source_url'];
			$landing_page_url_raw 		= $order_data['landing_page_url'];
			$traffic_type 			 	= $order_data['traffic_source'];

			echo '<mark class="wpd-order-acquisition-channel order-status tips ' . esc_attr( strtolower( $traffic_type ) ) . '" data-tip="' . esc_attr( $referral_source_url ) . '"><span>' . esc_html( $traffic_type ) . '</span></mark>';

	    } else if ( $column == 'wpd_ai_campaign' ) {

			if ( is_a($post_id_or_order_object, 'WC_Order') ) {
				$order = $post_id_or_order_object; // HPOS hook
			} else {
				$order = wc_get_order( $post_id_or_order_object );
			}

			if ( ! is_a($order, 'WC_Order') ) return false;

			$landing_page_url_raw 		= $order->get_meta( '_wpd_ai_landing_page' );
			$query_params 				= wpdai_get_query_params( $landing_page_url_raw );
			$query_params_string = '';

			if ( is_array($query_params) && ! empty($query_params) ) {
				foreach( $query_params as $key => $value ) {
					$query_params_string .= $key .'='. urlencode( $value ) .'<br>';
				}
			}

			if ( isset($query_params['utm_campaign']) ) {
				echo '<mark class="order-status tips" data-tip="' . esc_attr( $query_params_string ) . '"><span>' . esc_html( $query_params['utm_campaign'] ) . '</span></mark>';
			}

	    }

	}

	/**
	 *
	 *	Save additional data to the order meta as required, checks cookies and session data
	 *	Will not trigger the $order->save() function, this must be called after performing this action
	 *  This will not update the order meta if the meta already exists
	 *	
	 *	@param WC_Order $order The order object we are saving to
	 *	@return bool $updated True if an update has been made, false if no updates have been made
	 *
	 */
	public function save_landing_page_to_order_meta( $order ) {

		$updated = false;

		// Dont add landing page data if they are creating the order in the admin area
		if ( is_admin() ) return false;

		if ( is_a($order, 'WC_Order') ) {

			if ( ! empty( $_COOKIE['wpd_ai_landing_page'] ) ) {

				// Collect landing page
				$landing_page = sanitize_text_field( $_COOKIE['wpd_ai_landing_page'] );

				// Normal data
				$order->update_meta_data( '_wpd_ai_landing_page', $landing_page );
				$updated = true;

				// Now lets check the query params for additional data
				$query_params = wpdai_get_query_params( $landing_page );
				$meta_tracking_key = 'meta_cid';
				$google_tracking_key = 'google_cid';

				// If we have a meta_cid lets set the meta campaign id
				$meta_campaign_id = $order->get_meta( '_wpd_ai_meta_campaign_id' );
				if ( isset($query_params[$meta_tracking_key]) && is_numeric($query_params[$meta_tracking_key]) && empty($meta_campaign_id) ) $order->update_meta_data( '_wpd_ai_meta_campaign_id', trim( $query_params[$meta_tracking_key] ));

				// If we have a google_cid lets set the google campaign id
				$google_campaign_id = $order->get_meta( '_wpd_ai_google_campaign_id' );
				if ( isset($query_params[$google_tracking_key]) && is_numeric($query_params[$google_tracking_key]) && empty($google_campaign_id) ) $order->update_meta_data( '_wpd_ai_google_campaign_id', trim( $query_params[$google_tracking_key] ));

			}

			if ( ! empty( $_COOKIE['wpd_ai_referral_source'] ) ) {
				$referral_source = $order->get_meta( '_wpd_ai_referral_source' );
				if ( empty($referral_source) ) $order->update_meta_data( '_wpd_ai_referral_source', sanitize_text_field( $_COOKIE['wpd_ai_referral_source'] ) );
				$updated = true;
			}

			if ( ! empty( $_COOKIE['wpd_ai_session_id'] ) ) {
				$session_id = $order->get_meta( '_wpd_ai_session_id' );
				if ( empty($session_id) ) $order->update_meta_data( '_wpd_ai_session_id', sanitize_text_field( $_COOKIE['wpd_ai_session_id'] ) );
				$updated = true;
			}

		}

		return $updated;

	}

	/**
	 *
	 *	Add cost of goods input to product page - New Tab
	 *
	 */
	public function register_product_cost_of_goods_tab( $product_data_tabs ) {

		// Don't load HTML for non-authorized users
		if ( ! wpdai_is_user_authorized_to_view_alpha_insights() ) return $product_data_tabs;

		$product_id 			= get_the_ID();
		$product 				= wc_get_product();

		// Don't show product on grouped or bundled page
		if ( is_a($product, 'WC_Product') ) {
			if ( $product->is_type('bundle') || $product->is_type('grouped') ) {
				return $product_data_tabs;
			}
		}

		$product_data_tabs['wpd-ai-cost-of-goods'] = array(

			'label' 	=> __( 'Alpha Insights Cost Of Goods', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'target' 	=> 'wpd-ai-cost-of-goods',
			'priority' 	=> 10,
			'class' 	=> array( 'wpd-ai-cost-of-goods' ),

		);

		return $product_data_tabs;

	}

	/**
	 *
	 *	Content for product data tab
	 *
	 */
	public function output_product_cost_of_goods_tab_content() {

		// Don't load HTML for non-authorized users
		if ( ! wpdai_is_user_authorized_to_view_alpha_insights() ) return false;

		// Globals
		global $woocommerce, $post;

		// Vars
		$product_id 					= get_the_ID();
		$product 						= wc_get_product( $product_id );
		$cost_price 					= get_post_meta( $product_id, '_wpd_ai_product_cost', true );
		$is_variable 					= $product->is_type( 'variable' );
		$is_bundle 						= ( $product->is_type('bundle') || $product->is_type('woosb') ) ? true : false;
		$custom_product_costs 			= wpdai_get_custom_product_cost_options( $product_id );

		?>
		<div id="wpd-ai-cost-of-goods" class="panel woocommerce_options_panel">
			<div class="wpd-wrapper">
				<?php do_action('wpd_ai_product_cost_of_goods_tab_content_before_content', $product); ?>
				<?php if ( ! $is_bundle ) : ?>
					<table class="wpd-table fixed widefat">
						<thead>
							<tr>
								<th colspan="4">Product Purchasing Information - <?php echo esc_html( $product->get_name() ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td colspan="2">
									<label><?php echo esc_html( ( $is_variable ) ? 'Default Product Cost Of Goods (' . wpdai_store_currency_string() . ')' : 'Product Cost Of Goods (' . wpdai_store_currency_string() . ')' ); ?></label>
									<div class="wpd-meta">This is the cost of goods per unit for this product. 
										<?php if ($is_variable) : ?>This will be used as the default value for variations if you have not set the cost of goods for each variation below.<?php endif; ?>
									</div>
								</td>
								<td colspan="2">
									<?php
										$default_cost = wpdai_get_default_cost_price_by_product_id($product_id);
										$placeholder_text = $default_cost > 0 
											? 'Default: ' . wp_strip_all_tags(wc_price($default_cost))
											: 'No cost price set';
										
										woocommerce_wp_text_input (
											array (

												'id'          => '_wpd_ai_product_cost['.$product_id.']',
												'value'       => wc_format_localized_price( $cost_price ),
												'data_type'   => 'price',
												'description' => '',
												'label' 	  => '',
												'placeholder' => $placeholder_text,
												'wrapper_class' => 'form-field-wide',

											)
										);
									?>
								</td>
							</tr>
							<?php if ( ! $is_variable && is_array($custom_product_costs) && ! empty($custom_product_costs) ) : ?>
								<?php $product_specific_values = (array) get_post_meta( $product_id, '_wpd_ai_custom_product_costs', true ); ?>
								<?php foreach( $custom_product_costs as $custom_cost_slug => $custom_cost_data ) : ?>
									<?php
										$saved_static_fee_value = ( isset($product_specific_values[$custom_cost_slug]) && isset($product_specific_values[$custom_cost_slug]['static_fee']) && is_numeric($product_specific_values[$custom_cost_slug]['static_fee']) ) ? (float) $product_specific_values[$custom_cost_slug]['static_fee'] : null;
										$saved_percent_of_sell_price_value = ( isset($product_specific_values[$custom_cost_slug]) && isset($product_specific_values[$custom_cost_slug]['percent_of_sell_price']) && is_numeric($product_specific_values[$custom_cost_slug]['percent_of_sell_price']) ) ? (float) $product_specific_values[$custom_cost_slug]['percent_of_sell_price'] : null;
									?>
									<tr>
										<td colspan="2">
											<label><?php echo esc_html( ( ! is_null($custom_cost_data['label']) ) ? $custom_cost_data['label'] : $custom_cost_slug ); ?> (<?php echo esc_html( wpdai_store_currency_string() ); ?>)</label>
											<div class="wpd-meta"><?php echo esc_html( $custom_cost_data['description'] ); ?></div>
										</td>
										<td colspan="1">
											<?php
												woocommerce_wp_text_input (
													array (

														'id'          => '_wpd_ai_custom_product_costs['.$product_id.']['.$custom_cost_slug.'][percent_of_sell_price]',
														'value'       => $saved_percent_of_sell_price_value,
														'data_type'   => 'price',
														'description' => '',
														'label' 	  => '',
														'placeholder' => 'Current Settings: ' . (float) $custom_cost_data['percent_of_sell_price'] . '%',
														'wrapper_class' => 'form-field-wide',

													)
												);
											?>
											<label class="wpd-meta wpd-block-label">Percent Of Sell Price</label>
										</td>
										<td colspan="1">
											<?php
												woocommerce_wp_text_input (
													array (

														'id'          => '_wpd_ai_custom_product_costs['.$product_id.']['.$custom_cost_slug.'][static_fee]',
														'value'       => wc_format_localized_price( $saved_static_fee_value ),
														'data_type'   => 'price',
														'description' => '',
														'label' 	  => '',
														'placeholder' => esc_attr( 'Current Settings: ' . wp_strip_all_tags( wc_price( $custom_cost_data['static_fee'] ) ) ),
														'wrapper_class' => 'form-field-wide',

													)
												);
											?>
											<label class="wpd-meta wpd-block-label">+ Static Fee</label>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
							<?php if ( ! $is_variable ) : ?>
								<tr>
									<th>Current Price</th>
									<th>Total Cost</th>
									<th>Profit</th>
									<th>Margin</th>
								</tr>
								<tr>
									<?php
										$current_price  = (float) $product->get_price();
										$cost_price 	= (float) wpdai_get_cost_price_by_product_id( $product_id );
										$additional_cost_string = '';

										// Additional Cost Strings
										if ( ! empty($custom_product_costs) ) {
											$additional_costs = wpdai_get_additional_costs_by_product_id( $product_id );
											$cost_price += (float) $additional_costs['total'];
										}

										// Calculate Margin
										$profit = $current_price - $cost_price;
										$margin	= wpdai_calculate_margin($profit, $current_price);

									?>
									<td><?php echo wp_kses_post( wc_price( $current_price ) ); ?></td>
									<td><?php echo wp_kses_post( wc_price( $cost_price ) ); ?></td>
									<td><?php echo wp_kses_post( wc_price( $profit ) ); ?></td>
									<td><?php echo esc_html( $margin ); ?>%</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
					<?php if ( $is_variable ) : ?>
						<table class="wpd-table widefat fixed">
							<?php $variation_ids = $product->get_children(); ?>
							<?php if ( is_array($variation_ids) && ! empty($variation_ids) ) : ?>
								<thead>
									<tr>
										<th colspan="4">Product Variations</th>
									</tr>
									<tr>
										<td>Product</td>
										<td>Current Price</td>
										<td>Profit</td>
										<td>Cost of Goods (<?php echo esc_html( wpdai_store_currency_string() ); ?>)</td>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $variation_ids as $variation_id ) : ?>
										<?php $variation = wc_get_product( $variation_id ); ?>
										<tr>
											<td>
												<?php echo esc_html( $variation->get_name() ); ?>
												<div class="wpd-meta">
													ID: <?php echo absint( $variation_id ); ?><br>
													SKU: <?php echo esc_html( $variation->get_sku() ); ?>
												</div>
											</td>
											<td>
												<?php echo wp_kses_post( wc_price( $variation->get_price() ) ); ?>
											</td>
											<td>
												<?php $profit = (float) $variation->get_price() - (float) wpdai_get_cost_price_by_product_id($variation_id); ?>
												<?php echo wp_kses_post( wc_price( $profit ) ); ?>
											</td>
											<td>
												<?php
													$variation_cost_price_value = get_post_meta( $variation_id, '_wpd_ai_product_cost', true );
													woocommerce_wp_text_input (
														array (

															'id'          => '_wpd_ai_product_cost['.$variation_id.']',
															'value'       => wc_format_localized_price( $variation_cost_price_value ),
															'data_type'   => 'price',
															'description' => '',
															'label' 	  => '',
															'placeholder' => esc_attr( 'Default: ' . wp_strip_all_tags( wc_price( wpdai_get_default_cost_price_by_product_id( $variation_id ) ) ) ),
															'wrapper_class' => 'form-field-wide',

														)
													);
												?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							<?php endif; ?>
						</table>
					<?php endif; ?>
				<?php else : ?>
					<p>Product Cost Of Goods for Bundle Products are handled by their child products.</p>
				<?php endif; ?>
			</div>
		</div>
		<?php

	}

	/**
	 *
	 *	Save all of my posted product data
	 *
	 */
	public function save_product_cog_data( $product_id, $__post ) {

		// Track all updated product IDs for targeted cache deletion
		$updated_product_ids = [];

		if ( isset( $_POST['_wpd_ai_product_cost'] ) ) {

			if ( is_array($_POST['_wpd_ai_product_cost']) && ! empty($_POST['_wpd_ai_product_cost']) ) {

				foreach( $_POST['_wpd_ai_product_cost'] as $product_id => $product_cost ) {

					// Sanitize product ID before use
					$product_id = absint( $product_id );

					// Save numeric values -> must check via wc_format_decimal in case they're using commas for decimal
					if ( $product_cost && is_numeric( wc_format_decimal($product_cost) ) ) {

						$saved = update_post_meta( $product_id, '_wpd_ai_product_cost', wc_format_decimal( $product_cost ) );

						if ( $saved ) {
							$updated_product_ids[] = $product_id;
						}

					}

					// Delete empty values that aren't zero
					if ( empty($product_cost) && ! is_numeric($product_cost) ) {

						$saved = delete_post_meta( $product_id, '_wpd_ai_product_cost' );

						if ( $saved ) {
							$updated_product_ids[] = $product_id;
						}

					} 

				}

			}

		}

		// _wpd_ai_custom_product_costs[39][shipping_costs]
		if ( isset( $_POST['_wpd_ai_custom_product_costs'] ) ) {

			if ( is_array($_POST['_wpd_ai_custom_product_costs']) && ! empty($_POST['_wpd_ai_custom_product_costs']) ) {

				foreach( $_POST['_wpd_ai_custom_product_costs'] as $product_id => $custom_cost_data ) {

					$product_id = absint( $product_id );

					// Sanitize data
					if ( is_array( $custom_cost_data ) ) {
						foreach( $custom_cost_data as $slug => $cost_data ) {

							if ( is_array( $cost_data ) ) {
								$static_fee 			= wc_format_decimal( sanitize_text_field( $cost_data['static_fee'] ?? '' ) );
								$percent_of_sell_price 	= wc_format_decimal( sanitize_text_field( $cost_data['percent_of_sell_price'] ?? '' ) );

								$custom_cost_data[$slug] = array(
									'static_fee' => $static_fee,
									'percent_of_sell_price' => $percent_of_sell_price
								);
							}

						}
					}

					// Save to DB
					$saved = update_post_meta( $product_id, '_wpd_ai_custom_product_costs', $custom_cost_data );

					if ( $saved ) {
						$updated_product_ids[] = $product_id;
					}

				}

			}

		}

		// Update product data cache
		// @todo need to async this
		$update = wpdai_update_product_cache_by_product_id( $product_id );

		// Clear cache for orders containing the updated products
		if ( ! empty( $updated_product_ids ) && is_array($updated_product_ids) ) {
			wpdai_delete_order_cache_by_product_ids( array_unique( $updated_product_ids ) );
		}

	}

	/**
	 * 
	 * 	Register custom columns on the product-edit admin list
	 * 
	 **/
	public function register_product_custom_columns( $columns ) {

		// Don't load HTML for non-authorized users
		if ( ! wpdai_is_user_authorized_to_view_alpha_insights() ) return $columns;

		// Custom Columns
		$custom_admin_columns 	= wpdai_get_admin_custom_column_settings();

		// Make sure it's set
		if ( is_array($custom_admin_columns) && isset($custom_admin_columns['products']) ) {

			// Get product columns
			$order_admin_columns = $custom_admin_columns['products'];

			// Set the columns in admin
			foreach( $order_admin_columns as $key => $label ) $columns[$key] = $label;

		}

		// Return restructured columns
		return $columns;

	}

	/**
	 * 
	 * 	Display product margin on the product-edit admin list
	 * 
	 **/
	public function print_product_custom_admin_columns( $column, $post_id ) {

		if ( $column === 'wpd_ai_cost_of_goods' ) {

			// Product Object
			$product = wc_get_product( $post_id );

			// Safety check
			if ( ! is_a($product, 'WC_Product') ) return false;

			// Check for children
			$has_children = $product->has_child();
			if ( $has_children) {
				
				$cogs_range = wpdai_get_min_max_product_cogs_range( $product );
				if ( $cogs_range ) {

					$min_cost_price = $cogs_range['min'];
					$max_cost_price = $cogs_range['max'];

					if ( $min_cost_price == $max_cost_price ) {
						echo wp_kses_post( wc_price( $min_cost_price ) );
					} else {
						echo wp_kses_post( wc_price( $min_cost_price ) ) . ' – ' . wp_kses_post( wc_price( $max_cost_price ) );
					}

				} else {

					return false;

				}


			} else {

				// Simple product
				echo wp_kses_post( wc_price( wpdai_get_cost_price_by_product_id( $post_id ) ) );

			}

		} elseif ( $column === 'wpd_ai_margin' ) {

			// Product Object
			$product = wc_get_product( $post_id );

			// Safety check
			if ( ! is_a($product, 'WC_Product') ) return false;

			$has_children = $product->has_child();
			if ( $has_children) {

				$margin_range = wpdai_get_min_max_product_margin_range( $product );

				if ( $margin_range ) {

					$min_margin = $margin_range['min'];
					$max_margin = $margin_range['max'];

					if ( $min_margin == $max_margin ) {
						echo esc_html( $min_margin ) . '%';
					} else {
						echo esc_html( $min_margin ) . '% – ' . esc_html( $max_margin ) . '%';
					}

				} else {

					return false;

				}

			} else {

				$margin_percentage = wpdai_calculate_margin_by_product( $product );
				echo esc_html( $margin_percentage ) . '%';

			}

		} elseif( $column == 'wpd_ai_analytics_clicks' ) {

			// Fetch Data
			$target_key  		= 'product_cat_page_clicks';
			$product_analytics 	= wpdai_fetch_product_analytics_by_product_id( $post_id );
			
			// Output Data
			if ( is_array($product_analytics) && isset($product_analytics[$target_key]) ) {
				echo esc_attr( $product_analytics[$target_key] );
			}
			
	    } elseif( $column == 'wpd_ai_analytics_page_views' ) {

			// Fetch Data
			$target_key  		= 'product_page_views';
			$product_analytics 	= wpdai_fetch_product_analytics_by_product_id( $post_id );
			
			// Output Data
			if ( is_array($product_analytics) && isset($product_analytics[$target_key]) ) {
				echo esc_attr( $product_analytics[$target_key] );
			}

	    } elseif( $column == 'wpd_ai_analytics_add_to_carts' ) {

			// Fetch Data
			$target_key  		= 'product_add_to_carts';
			$product_analytics 	= wpdai_fetch_product_analytics_by_product_id( $post_id );
			
			// Output Data
			if ( is_array($product_analytics) && isset($product_analytics[$target_key]) ) {
				echo esc_attr( $product_analytics[$target_key] );
			}

	    } elseif( $column == 'wpd_ai_analytics_times_sold_tracked' ) {

			// Fetch Data
			$target_key  		= 'product_purchases';
			$product_analytics 	= wpdai_fetch_product_analytics_by_product_id( $post_id );
			
			// Output Data
			if ( is_array($product_analytics) && isset($product_analytics[$target_key]) ) {
				echo esc_attr( $product_analytics[$target_key] );
			}

	    } elseif( $column == 'wpd_ai_analytics_atc_conversion_rate' ) {

			// Fetch Data
			$target_key  		= 'page_view_to_atc_conversion_rate';
			$product_analytics 	= wpdai_fetch_product_analytics_by_product_id( $post_id );
			
			// Output Data
			if ( is_array($product_analytics) && isset($product_analytics[$target_key]) ) {
				echo esc_attr( $product_analytics[$target_key] . '%' );
			}

	    } elseif( $column == 'wpd_ai_analytics_purchase_conversion_rate' ) {

			// Fetch Data
			$target_key  		= 'page_view_to_atc_conversion_rate';
			$product_analytics 	= wpdai_fetch_product_analytics_by_product_id( $post_id );
			
			// Output Data
			if ( is_array($product_analytics) && isset($product_analytics[$target_key]) ) {
				echo esc_attr( $product_analytics[$target_key] . '%' );
			}

	    } elseif( $column == 'wpd_ai_analytics_total_qty_sold' ) {

			// Fetch Data
			$target_key  		= 'wc_total_qty_purchased';
			$product_analytics 	= wpdai_fetch_product_analytics_by_product_id( $post_id );
			
			// Output Data
			if ( is_array($product_analytics) && isset($product_analytics[$target_key]) ) {
				echo esc_attr( $product_analytics[$target_key] );
			}

	    } elseif ( $column == 'wpd_ai_analytics_avg_sell_price' ) {
	
			// Fetch Data
			$target_key  		= 'average_price_post_discount';
			$product_analytics 	= wpdai_fetch_product_analytics_by_product_id( $post_id );
			
			// Output Data
			if ( is_array($product_analytics) && isset($product_analytics[$target_key]) ) {
				echo wp_kses_post( wc_price( $product_analytics[$target_key] ) );
			}
	
		} elseif ( $column == 'wpd_ai_analytics_total_revenue' ) {
	
			// Fetch Data
			$target_key  		= 'total_revenue_post_discount';
			$product_analytics 	= wpdai_fetch_product_analytics_by_product_id( $post_id );
			
			// Output Data
			if ( is_array($product_analytics) && isset($product_analytics[$target_key]) ) {
				echo wp_kses_post( wc_price( $product_analytics[$target_key] ) );
			}
	
		} elseif ( $column == 'wpd_ai_analytics_total_profit' ) {
	
			// Fetch Data
			$target_key  		= 'total_profit';
			$product_analytics 	= wpdai_fetch_product_analytics_by_product_id( $post_id );
			
			// Output Data
			if ( is_array($product_analytics) && isset($product_analytics[$target_key]) ) {
				echo wp_kses_post( wc_price( $product_analytics[$target_key] ) );
			}
	
		}

	}

	/**
	 * 
	 * 	Register custom columns on the user table in the WordPress admin
	 * 
	 **/
	public function register_user_custom_columns( $columns ) {

		// Don't load HTML for non-authorized users
		if ( ! wpdai_is_user_authorized_to_view_alpha_insights() ) return $columns;

		// Change this to last
		if ( isset($columns['posts']) ) unset( $columns['posts'] );

		// Custom Columns
		$custom_admin_columns = wpdai_get_admin_custom_column_settings();

		if ( is_array($custom_admin_columns) && isset($custom_admin_columns['users']) ) {

			$order_admin_columns = $custom_admin_columns['users'];

			foreach( $order_admin_columns as $key => $label ) $columns[$key] = $label;

		}

		$columns['posts'] = __( 'Posts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); // Last

		return $columns;

	}

	/** 
	 * 
	 *	Output custom data on the user table in the WordPress admin 
	 * 
	 **/
	public function output_user_custom_columns( $value, $column_name, $user_id ) {

		if ( $column_name === 'wpd_ai_sessions' ) {

			// Fetch Data
			$target_key  		= 'total_session_count';
			$customer_analytics = wpdai_fetch_customer_analytics_by_user_id( $user_id);
			
			// Output Data
			if ( is_array($customer_analytics) && isset($customer_analytics[$target_key]) ) {
				return esc_attr( $customer_analytics[$target_key] );
			}
	

		} elseif ( $column_name === 'wpd_ai_orders' ) {

			// Fetch Data
			$target_key  		= 'total_order_count';
			$customer_analytics = wpdai_fetch_customer_analytics_by_user_id( $user_id);
			
			// Output Data
			if ( is_array($customer_analytics) && isset($customer_analytics[$target_key]) ) {
				return esc_attr( $customer_analytics[$target_key] );
			}

		} elseif ( $column_name === 'wpd_ai_ltv' ) {

			// Fetch Data
			$target_key  		= 'lifetime_value';
			$customer_analytics = wpdai_fetch_customer_analytics_by_user_id( $user_id);
			
			// Output Data
			if ( is_array($customer_analytics) && isset($customer_analytics[$target_key]) ) {
				return wc_price( $customer_analytics[$target_key] );
			}

		} elseif ( $column_name === 'wpd_ai_aov' ) {

			// Fetch Data
			$target_key  		= 'average_order_value';
			$customer_analytics = wpdai_fetch_customer_analytics_by_user_id( $user_id);
			
			// Output Data
			if ( is_array($customer_analytics) && isset($customer_analytics[$target_key]) ) {
				return wc_price( $customer_analytics[$target_key] );
			}

		} elseif ( $column_name === 'wpd_ai_conversion_rate' ) {

			// Fetch Data
			$target_key  		= 'conversion_rate';
			$customer_analytics = wpdai_fetch_customer_analytics_by_user_id( $user_id);

			$session_count = $customer_analytics['total_session_count'];

			// If they haven't got a session, print that
			if ( $session_count == 0 ) {

				return 'No Sessions Found';

			} else {

				// Output Data
				if ( is_array($customer_analytics) && isset($customer_analytics[$target_key]) ) {
					return $customer_analytics[$target_key] . '%';
				}

			}
			

		} elseif ( $column_name === 'wpd_ai_date_registered' ) {

			// Fetch Data
			$target_key  		= 'registration_date_pretty';
			$customer_analytics = wpdai_fetch_customer_analytics_by_user_id( $user_id);
			
			// Output Data
			if ( is_array($customer_analytics) && isset($customer_analytics[$target_key]) ) {
				return esc_attr( $customer_analytics[$target_key] );
			}

		}

	}

}

// Init
new WPDAI_Core();