<?php
/**
 *
 * Settings Page - General Settings
 *
 * @package Alpha Insights
 * @version 5.0.0
 * @since 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

$cost_defaults 								= get_option( 'wpd_ai_cost_defaults' );
$order_status 								= get_option( 'wpd_ai_order_status' );
$admin_style_override 						= get_option( 'wpd_ai_admin_style_override', 0 );
$prevent_notices 							= get_option( 'wpd_ai_prevent_wp_notices', 0 );
$admin_custom_column_settings 				= wpd_get_admin_custom_column_settings();
$wpd_ai_use_legacy_order_admin_metaboxes	= get_option( 'wpd_ai_use_legacy_order_admin_metaboxes', 0 );
$custom_order_cost_options 					= (function_exists('wpd_get_custom_order_cost_options')) ? wpd_get_custom_order_cost_options() : array();
$custom_product_cost_options 				= (function_exists('wpd_get_custom_product_cost_options')) ? wpd_get_custom_product_cost_options() : array();
$analytics_settings							= get_option( 'wpd_ai_analytics');
$enable_woocommerce_analytics 				= (isset($analytics_settings['enable_woocommerce_analytics'])) ? (int) $analytics_settings['enable_woocommerce_analytics'] : 1;
$allowed_roles 								= wpd_get_authorized_user_roles_settings();
$refunded_order_costs 						= get_option( 'wpd_ai_refunded_order_costs' );
$payment_gateway_cost_settings				= wpd_get_payment_gateway_cost_settings();
$available_payment_gateways					= wpd_get_available_payment_gateways();

?>
<div class="wpd-wrapper">
	<div class="wpd-section-heading wpd-inline">
		<?php _e( 'General Settings', 'wpd-alpha-insights' ); ?>
		<?php submit_button( __('Save Changes', 'wpd-alpha-insights'), 'primary pull-right', 'submit', false); ?>
	</div>
</div>
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2"><?php _e( 'Initial Configuration - Default Costs', 'wpd-alpha-insights' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label for="wpd_ai_payment_gateway_costs"><?php _e( 'Payment Gateway Costs', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'This will define the costs associated with each of your payment gateways. Your orders will start with this cost, but you can override it on the order admin page. If we detect that your payment plugin has returned the actual fee we will use that instead.', 'wpd-alpha-insights' ); ?></div>
				</td>
				<td>
					<table class="wpd-table fixed" width="100%">
						<thead>
							<tr>
								<td>Payment Gateway</td>
								<td>Percent Of Order Value</td>
								<td>Static Fee</td>
							</tr>
						</thead>
						<tbody>
							<?php if ( is_array($available_payment_gateways) && ! empty($available_payment_gateways) ) : ?>
								<?php foreach( $available_payment_gateways as $payment_gateway_id => $payment_gateway_data ) : ?>
									<tr>
										<td><?php echo esc_html( $payment_gateway_data['title'] ); ?></td>
										<td>
											<input class="wpd-input" type="number" name="wpd_ai_payment_gateway_costs[<?php echo esc_attr( $payment_gateway_id ); ?>][percent_of_sales]" value="<?php echo esc_attr( (float) ( $payment_gateway_cost_settings[$payment_gateway_id]['percent_of_sales'] ?? 0 ) ); ?>" step="0.01" placeholder="Percent Of Order Value">
										</td>
										<td>
											<input class="wpd-input" type="number" name="wpd_ai_payment_gateway_costs[<?php echo esc_attr( $payment_gateway_id ); ?>][static_fee]" value="<?php echo esc_attr( (float) ( $payment_gateway_cost_settings[$payment_gateway_id]['static_fee'] ?? 0 ) ); ?>" step="0.01" placeholder="Static Fee">
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</td>
			</tr>
			<tr>
				<td>
					<label><?php _e( 'Default Product Cost Price', 'wpd-alpha-insights' ); ?> (%)</label>
					<div class="wpd-meta"><?php _e( 'This will be a fallback setting for products in which you haven\'t entered a cost price. This is calculated as a percentage of the given product\'s retail price. Use the configure COGS Per Product to manage costs per product (recommended).', 'wpd-alpha-insights' ); ?></div>
					<div class="wpd-meta">Our cost price hierarchy works as follows:</div>
					<div class="wpd-meta">1. Value saved for a product in the Alpha Insights Cost of Goods Manager (recommended)</div>
					<div class="wpd-meta">2. Fall back to WooCommerce Native COGS if set -> WooCommerce 10.0+</div>
					<div class="wpd-meta">3. Fall back to Parent Variable Product meta if a variation and value found in parent product</div>
					<div class="wpd-meta">4. Fall back to Default Cost Price (This Setting) e.g. 30% of RRP (if no other value is found)</div>
				</td>
				<td>
					<table class="wpd-table fixed" width="100%">
						<thead>
							<tr>
								<td>Percent Of RRP</td>
								<td colspan="2">Actions</td>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><input class="wpd-input" type="number" name="wpd_ai_cost_defaults[default_product_cost_percent]" value="<?php echo $cost_defaults['default_product_cost_percent'] ?>" step="0.01" placeholder="Percent of RRP"></td>
								<td colspan="2"><a href="<?php echo wpd_admin_page_url('cost-of-goods-manager') ?>" target="_blank" class="button btn wpd-input"><?php _e( 'Configure COGS Per Product', 'wpd-alpha-insights' ) ?></a></td>
							</tr>
						</tbody>
					</table>
				</td>
			</tr>
			<tr>
				<td>
					<label for="wpd_ai_general_settings"><?php _e( 'Default Shipping Cost', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'This will be a fallback setting for the shipping fees you pay to your carrier. Your orders will start with this cost, but you can override it as the fee is finalised.', 'wpd-alpha-insights' ); ?></div>
				</td>
				<td>
					<table class="wpd-table fixed" width="100%">
						<thead>
							<tr>
								<td>Percent Of Order Value</td>
								<td>Percent Of Shipping Charged</td>
								<td>Static Fee</td>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><input class="wpd-input" type="number" name="wpd_ai_cost_defaults[default_shipping_cost_percent]" value="<?php echo $cost_defaults['default_shipping_cost_percent'] ?>" step="0.01" placeholder="Percent Of Order Value"></td>
								<td><input class="wpd-input" type="number" name="wpd_ai_cost_defaults[default_shipping_cost_percent_shipping_charged]" value="<?php echo $cost_defaults['default_shipping_cost_percent_shipping_charged'] ?>" step="0.01" placeholder="Percent Of Shipping Charged"></td>
								<td><input class="wpd-input" type="number" name="wpd_ai_cost_defaults[default_shipping_cost_fee]" value="<?php echo $cost_defaults['default_shipping_cost_fee'] ?>" step="0.01" placeholder="Static Fee"></td>
							</tr>
						</tbody>
					</table>

				</td>
			</tr>
		</tbody>
	</table>
</div>
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2"><?php _e( 'Report Settings', 'wpd-alpha-insights' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label><?php _e( 'Paid Order Status For Reporting', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'These are the order statuses that we will look at when reviewing your profitability.<br>These statuses are the ones that are considered paid for and will be used in your report calculations.<br>Refund status is required.', 'wpd-alpha-insights' ); ?></div>
				</td>
				<td>
					<select class="wpd-input wpd-combo-select" name="wpd_ai_order_status[]" value="" multiple="multiple">
						<?php 
							$chosen_status 	= wpd_paid_order_statuses();
							$order_status 	= wc_get_order_statuses();
							foreach( $order_status as $key => $value ) {
								$selected = '';
								if ( in_array( $key, $chosen_status ) ) {
									$selected = 'selected="selected"';
								}
								echo '<option value="' . esc_attr( $key ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $value ) . '</option>';
							}

						?>
					</select>
				</td>
			</tr>
			<tr>
				<td>
					<label><?php _e( 'Cache Build Batch Size', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'This will define the batch size for the cache build process. This is the number of orders that will be processed at a time.<br>Lowering this value if you are having errors in the cache building process, but this may increase processing time.', 'wpd-alpha-insights' ); ?></div>
				</td>
				<td>
					<input class="wpd-input" type="number" name="wpd_ai_cache_build_batch_size" value="<?php echo get_option( 'wpd_ai_cache_build_batch_size', 250 ) ?>" step="1" placeholder="Batch Size" min="1" max="10000">
				</td>
			</tr>
		</tbody>
	</table>
</div>
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2"><?php _e( 'Profit Calculation Settings', 'wpd-alpha-insights' ) ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label><?php _e( 'Costs to include when an order is fully refunded', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'By default, all costs are set to 0 when an order is fully refunded.<br>You can use these settings to adjust which costs are included in your profit calculation when an order is fully refunded.<br>*Partially refunded orders have exemption calculations, per item refunded..', 'wpd-alpha-insights' ); ?></div>
				</td>
				<td>
					<!-- Ensure a value is always set -->
					<input type="hidden" name="wpd-refunded-order-costs[__none]" value="0">
					<?php wpd_checkbox( 'wpd-refunded-order-costs[total_product_cost_of_goods]', $refunded_order_costs['total_product_cost_of_goods'], __( 'Product Cost Of Goods', 'wpd-alpha-insights') ); ?>
					<?php wpd_checkbox( 'wpd-refunded-order-costs[total_product_custom_costs]', $refunded_order_costs['total_product_custom_costs'], __( 'Product Custom Costs', 'wpd-alpha-insights') ); ?>
					<?php wpd_checkbox( 'wpd-refunded-order-costs[total_shipping_cost]', $refunded_order_costs['total_shipping_cost'], __( 'Shipping Costs', 'wpd-alpha-insights') ); ?>
					<?php wpd_checkbox( 'wpd-refunded-order-costs[payment_gateway_cost]', $refunded_order_costs['payment_gateway_cost'], __( 'Payment Gateway Fees', 'wpd-alpha-insights') ); ?>
					<?php wpd_checkbox( 'wpd-refunded-order-costs[total_custom_order_costs]', $refunded_order_costs['total_custom_order_costs'], __( 'Custom Order Costs', 'wpd-alpha-insights') ); ?>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<!-- Custom Order Costs -->
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2"><?php _e( 'Custom Order & Product Costs', 'wpd-alpha-insights' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label for="wpd_ai_custom_order_cost"><?php _e( 'Create Custom Order Costs', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta">
						<?php _e( 
							'You can use this setting to create additional order costs with default values for each order.
							<br>Every new cost field you add here will show up on the order edit page in the admin area.<br>
							You can override the default cost value for each order. <a href="https://wpdavies.dev/documentation/alpha-insights/features/setting-up-custom-order-costs-for-woocommerce/?utm_campaign=Alpha+Insights+Documentation&utm_source=Alpha+Insights+Plugin" target="_blank">Click Here</a> for documentation.', 'wpd-alpha-insights' ); ?></div>
				</td>
				<td>
					<table class="wpd-table fixed" width="100%">
						<thead>
							<tr>
								<td colspan="2">Name</td>
								<td colspan="2">% Of Order Value</td>
								<td colspan="2">Static Fee</td>
								<td>Action</td>
							</tr>
						</thead>
						<tbody>
							<?php if ( is_array($custom_order_cost_options) && ! empty($custom_order_cost_options) ) : ?>
								<?php foreach( $custom_order_cost_options as $slug => $data ) : ?>
									<tr class="wpd-custom-order-cost-row">
										<td colspan="2">
											<input class="wpd-input" type="text" name="wpd_ai_custom_order_cost[<?php echo esc_attr( $slug ); ?>][label]" placeholder="E.g. Commission" value="<?php echo esc_attr( $data['label'] ); ?>">
											<label for="wpd_ai_custom_order_cost[<?php echo esc_attr( $slug ); ?>][label]" class="wpd-meta wpd-block-label">Unique Key: <?php echo esc_html( $slug ); ?></label>
										</td>
										<td colspan="2">
											<input class="wpd-input" type="number" name="wpd_ai_custom_order_cost[<?php echo esc_attr( $slug ); ?>][percent_of_order_value]" placeholder="0" value="<?php echo esc_attr( $data['percent_of_order_value'] ); ?>" step="0.01" >
											<label for="wpd_ai_custom_order_cost[<?php echo esc_attr( $slug ); ?>][percent_of_order_value]" class="wpd-meta wpd-block-label">Percent Of Order Value</label>
										</td>
										<td colspan="2">
											<input class="wpd-input" type="number" name="wpd_ai_custom_order_cost[<?php echo esc_attr( $slug ); ?>][static_fee]" placeholder="0" value="<?php echo esc_attr( $data['static_fee'] ); ?>" step="0.01" >
											<label for="wpd_ai_custom_order_cost[<?php echo esc_attr( $slug ); ?>][static_fee]" class="wpd-meta wpd-block-label">+ Static Fee</label>
										</td>
										<td><div class="wpd-delete wpd-delete-custom-order-cost" style="color: red; cursor: pointer; text-decoration: underline; margin-bottom: 25px;">Delete</div></td>
										<input type="hidden" name="wpd_ai_custom_order_cost[<?php echo esc_attr( $slug ); ?>][slug]" value="<?php echo esc_attr( $slug ); ?>">
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
							<tr class="wpd-custom-order-cost-row-new">
								<td colspan="2">
									<input class="wpd-input" type="text" name="wpd_ai_custom_order_cost[new][label]" placeholder="E.g. Commission" value="">
									<label for="wpd_ai_custom_order_cost[new][label]" class="wpd-meta wpd-block-label">Unique Key: N/A</label>
								</td>
								<td colspan="2">
									<input class="wpd-input" type="number" name="wpd_ai_custom_order_cost[new][percent_of_order_value]" placeholder="0" value="" step="0.01" >
									<label for="wpd_ai_custom_order_cost[new][percent_of_order_value]" class="wpd-meta wpd-block-label">Percent Of Order Value</label>
								</td>
								<td colspan="2">
									<input class="wpd-input" type="number" name="wpd_ai_custom_order_cost[new][static_fee]" placeholder="0" value="" step="0.01" >
									<label for="wpd_ai_custom_order_cost[new][static_fee]" class="wpd-meta wpd-block-label">+ Static Fee</label>
								</td>
								<td></td>
							</tr>
						</tbody>
					</table>
				</td>
			</tr>
			<tr>
				<td>
					<label for="wpd_ai_custom_product_cost"><?php _e( 'Create Custom Product Costs', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta">
						<?php _e( 
							'You can use this setting to create additional product costs with default values for each product.
							<br>Every new cost field you add here will show up on the product edit page and in the order admin area.<br>
							You can override the default cost value for each product & each order. <a href="https://wpdavies.dev/documentation/alpha-insights/features/setting-up-custom-product-costs-for-woocommerce/?utm_campaign=Alpha+Insights+Documentation&utm_source=Alpha+Insights+Plugin" target="_blank">Click Here</a> for documentation.', 'wpd-alpha-insights' ); ?></div>
				</td>
				<td>
					<table class="wpd-table fixed" width="100%">
						<thead>
							<tr>
								<td colspan="2">Name</td>
								<td colspan="2">% Of Sell Price</td>
								<td colspan="2">Static Fee</td>
								<td>Action</td>
							</tr>
						</thead>
						<tbody>
							<?php if ( is_array($custom_product_cost_options) && ! empty($custom_product_cost_options) ) : ?>
								<?php foreach( $custom_product_cost_options as $slug => $data ) : ?>
									<tr class="wpd-custom-order-cost-row">
										<td colspan="2">
											<input class="wpd-input" type="text" name="wpd_ai_custom_product_cost[<?php echo esc_attr( $slug ); ?>][label]" placeholder="E.g. Packing Fees" value="<?php echo esc_attr( $data['label'] ); ?>">
											<label for="wpd_ai_custom_product_cost[<?php echo esc_attr( $slug ); ?>][label]" class="wpd-meta wpd-block-label">Unique Key: <?php echo esc_html( $slug ); ?></label>
										</td>
										<td colspan="2">
											<input class="wpd-input" type="number" name="wpd_ai_custom_product_cost[<?php echo esc_attr( $slug ); ?>][percent_of_sell_price]" placeholder="0" value="<?php echo esc_attr( $data['percent_of_sell_price'] ); ?>" step="0.01" >
											<label for="wpd_ai_custom_product_cost[<?php echo esc_attr( $slug ); ?>][percent_of_sell_price]" class="wpd-meta wpd-block-label">Percent Of Sell Price</label>
										</td>
										<td colspan="2">
											<input class="wpd-input" type="number" name="wpd_ai_custom_product_cost[<?php echo esc_attr( $slug ); ?>][static_fee]" placeholder="0" value="<?php echo esc_attr( $data['static_fee'] ); ?>" step="0.01" >
											<label for="wpd_ai_custom_product_cost[<?php echo esc_attr( $slug ); ?>][static_fee]" class="wpd-meta wpd-block-label">+ Static Fee</label>
										</td>
										<td><div class="wpd-delete wpd-delete-custom-order-cost" style="color: red; cursor: pointer; text-decoration: underline; margin-bottom: 25px;">Delete</div></td>
										<input type="hidden" name="wpd_ai_custom_product_cost[<?php echo esc_attr( $slug ); ?>][slug]" value="<?php echo esc_attr( $slug ); ?>">
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
							<tr class="wpd-custom-order-cost-row-new">
								<td colspan="2">
									<input class="wpd-input" type="text" name="wpd_ai_custom_product_cost[new][label]" placeholder="E.g. Packing Fees" value="">
									<label for="wpd_ai_custom_product_cost[new][label]" class="wpd-meta wpd-block-label">Unique Key: N/A</label>
								</td>
								<td colspan="2">
									<input class="wpd-input" type="number" name="wpd_ai_custom_product_cost[new][percent_of_sell_price]" placeholder="0" value="" step="0.01" >
									<label for="wpd_ai_custom_product_cost[new][percent_of_sell_price]" class="wpd-meta wpd-block-label">Percent Of Sell Price</label>
								</td>
								<td colspan="2">
									<input class="wpd-input" type="number" name="wpd_ai_custom_product_cost[new][static_fee]" placeholder="0" value="" step="0.01" >
									<label for="wpd_ai_custom_product_cost[new][static_fee]" class="wpd-meta wpd-block-label">+ Static Fee</label>
								</td>
								<td></td>
							</tr>
						</tbody>
					</table>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2"><?php _e( 'Alpha Analytics & Event Tracking', 'wpd-alpha-insights' ); ?><div class="wpd-meta">Full WooCommerce Analytics suite for event tracking & session data</div></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label><?php _e( 'Enable Woocommerce Event Tracking', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'This will enable product analytics which will add additional tracking to monitor things like product clicks, add to carts and purchases - utilising this will add a small additional load to your server.', 'wpd-alpha-insights' ); ?></div>
				</td>
				<td>
					<select class="wpd-input" name="wpd_ai_analytics[enable_woocommerce_analytics]">
						<option value="1" <?php echo wpd_selected_option( '1', $enable_woocommerce_analytics ) ?> ><?php _e( 'True', 'wpd-alpha-insights' ); ?></option>
						<option value="0" <?php echo wpd_selected_option( '0', $enable_woocommerce_analytics ) ?> ><?php _e( 'False', 'wpd-alpha-insights' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td>
					<label><?php _e( 'Exclude These Roles From Tracking', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'This will prevent these user roles from being tracked on your website.' ); ?></div>
				</td>
				<td>
					<select class="wpd-input wpd-combo-select" name="wpd_ai_analytics[exclude_roles][]" value="" multiple="multiple" placeholder="Select Role Type(s) To Exclude">
						<?php 
							$analytics_excluded_roles 	= (isset($analytics_settings['exclude_roles']) && ! empty($analytics_settings['exclude_roles'])) ? $analytics_settings['exclude_roles'] : array();
							$analytics_all_roles 		= wpd_get_available_store_roles();
							foreach( $analytics_all_roles as $analytics_role ) {
								$analytics_selected = '';
								$analytics_role = 'exclude_' . $analytics_role; // Prevent collisions with other settings?
								if ( in_array( $analytics_role, $analytics_excluded_roles ) ) {
									$analytics_selected = 'selected="selected"';
								}
								echo '<option value="' . esc_attr( $analytics_role ) . '" ' . esc_attr( $analytics_selected ) . '>' . esc_html( $analytics_role ) . '</option>';
							}
						?>
					</select>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2">
					<?php _e( 'WordPress Admin Display Extensions', 'wpd-alpha-insights' ); ?>
					<div class="wpd-meta">These checkboxes will display / hide any extensions to the standard WP Admin columns.</div>
					<?php $admin_custom_column_defaults = wpd_get_admin_custom_column_defaults(); ?>
					<!-- Hidden input allows for saving empty values across the board due to empty multi-select not passing into _POST -->
					<input type="hidden" name="wpd_ai_admin_custom_columns[]" value="" />
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label><?php _e( 'Product Admin Columns', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'Select which columns you would like to display in the Product Admin List section in your WP Dashboard.', 'wpd-alpha-insights' ); ?></div>
				</td>
				<td>
					<select class="wpd-input wpd-combo-select" name="wpd_ai_admin_custom_columns[products][]" value="" multiple="multiple" placeholder="Select Columns">
						<?php 
							$chosen_columns 	= ( isset($admin_custom_column_settings['products']) ) ? $admin_custom_column_settings['products'] : array();
							$product_columns 	= $admin_custom_column_defaults['products'];
							foreach( $product_columns as $key => $value ) {
								$selected = '';
								if ( array_key_exists( $key, $chosen_columns) ) {
									$selected = 'selected="selected"';
								}
								echo '<option value="' . esc_attr( $key ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $value ) . '</option>';
							}

						?>
					</select>
				</td>			
			</tr>
			<tr>
				<td>
					<label><?php _e( 'Order Admin Columns', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'Select which columns you would like to display in the Order Admin List section in your WP Dashboard.', 'wpd-alpha-insights' ); ?></div>
				</td>
				<td>
					<select class="wpd-input wpd-combo-select" name="wpd_ai_admin_custom_columns[orders][]" value="" multiple="multiple" placeholder="Select Columns">
						<?php 
							$chosen_columns = (isset($admin_custom_column_settings['orders'])) ? $admin_custom_column_settings['orders'] : array();
							$order_columns 	= $admin_custom_column_defaults['orders'];
							foreach( $order_columns as $key => $value ) {
								$selected = '';
								if ( array_key_exists( $key, $chosen_columns) ) {
									$selected = 'selected="selected"';
								}
								echo '<option value="' . esc_attr( $key ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $value ) . '</option>';
							}

						?>
					</select>
				</td>			
			</tr>
			<tr>
				<td>
					<label><?php _e( 'User Admin Columns', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'Select which columns you would like to display in the Users List section in your WP Dashboard.', 'wpd-alpha-insights' ); ?></div>
				</td>
				<td>
					<select class="wpd-input wpd-combo-select" name="wpd_ai_admin_custom_columns[users][]" value="" multiple="multiple" placeholder="Select Columns">
						<?php 
							$chosen_columns 	= (isset($admin_custom_column_settings['users'])) ? $admin_custom_column_settings['users'] : array();
							$user_columns 		= $admin_custom_column_defaults['users'];
							foreach( $user_columns as $key => $value ) {
								$selected = '';
								if ( array_key_exists( $key, $chosen_columns) ) {
									$selected = 'selected="selected"';
								}
								echo '<option value="' . esc_attr( $key ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $value ) . '</option>';
							}

						?>
					</select>
				</td>			
			</tr>
		</tbody>
	</table>
</div>
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2"><?php _e( 'Other Settings', 'wpd-alpha-insights' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label><?php _e( 'Limit Plugin Visibility', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'Choose which user roles can view this plugin. Those who are denied access will not see any part of the plugin.<br>Administrators will always have access.', 'wpd-alpha-insights' ); ?></div>
				</td>
				<td>
					<select class="wpd-input wpd-combo-select" name="wpd_ai_plugin_visibility[]" multiple="multiple" placeholder="Select Role Type(s) To Include">
						<?php

						    $all_roles = wpd_get_available_store_roles();

							foreach( $all_roles as $role ) {

								$selected = '';

								if ( in_array( $role, $allowed_roles ) ) {
									$selected = 'selected="selected"';
								}

								if ( $role === 'administrator' ) {
									$selected = 'selected="selected"';
								}

								echo '<option value="' . esc_attr( $role ) . '" ' . $selected . '>' . esc_html( $role ) . '</option>';
							}

						?>
					</select>
				</td>
			</tr>
			<tr>
				<td>
					<label><?php _e( 'Load Modern WP Admin Skin', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'Load our custom stylesheet which will override core admin appearance settings to help modernize your admin.', 'wpd-alpha-insights' ); ?></div>
				</td>
				<td>
					<select class="wpd-input" name="wpd_ai_admin_style_override">
						<option value="0" <?php echo wpd_selected_option( '0', $admin_style_override ) ?> ><?php _e( 'False', 'wpd-alpha-insights' ); ?></option>
						<option value="1" <?php echo wpd_selected_option( '1', $admin_style_override ) ?> ><?php _e( 'True', 'wpd-alpha-insights' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td>
					<label><?php _e( 'Prevent annoying WordPress notices', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'This will prevent the annoying update notices, license notices and whatever else rubbish people like to clutter your screen with.', 'wpd-alpha-insights' ); ?></div>
				</td>
				<td>
					<select class="wpd-input" name="wpd_ai_prevent_wp_notices">
						<option value="0" <?php echo wpd_selected_option( '0', $prevent_notices ) ?> ><?php _e( 'False', 'wpd-alpha-insights' ); ?></option>
						<option value="1" <?php echo wpd_selected_option( '1', $prevent_notices ) ?> ><?php _e( 'True', 'wpd-alpha-insights' ); ?></option>
					</select>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2"><?php _e( 'Debugging, Tools & Cache', 'wpd-alpha-insights' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label><?php _e( 'Delete All Report Caches', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'We store order and product data in a cache in order to run your reports faster.<br>This function will delete all caches to force the most recent data in your reports.<br>This is non-destructive and recommended for displaying the latest data.', 'wpd-alpha-insights' ); ?></div>
				</td>
				<td>
					<a class="button btn wpd-input" id="wpd-delete-cache"><?php _e( 'Delete Cache', 'wpd-alpha-insights' ) ?></a>
				</td>
			</tr>
			<tr>
				<td>
					<label><?php _e( 'Delete All Order Calculation Overrides', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'In your order admin page you are able to override the default calculations by entering values manually.<br>This tool will remove all of these overrides. It will not effect your WooCommerce order data in any way.<br><span style="color: red;">This tool will permanetly delete order overrides.</span>', 'wpd-alpha-insights' ); ?></div>
				</td>
				<td>
					<a class="button btn wpd-input" id="wpd-reset-order-meta"><?php _e( 'Delete All Order Calculation Overrides', 'wpd-alpha-insights' ) ?></a>
				</td>
			</tr>
			<tr>
				<td>
					<label><?php _e( 'Delete All Order Line Item COGS Overrides', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'If you have overridden the Cost of Goods for a product at the line item level on an order, this is saved and used for calculations.<br>This tool will remove all of these overrides. It will not effect your WooCommerce order data in any way.<br><span style="color: red;">This tool will permanetly delete line item COGS overrides.</span>', 'wpd-alpha-insights' ); ?></div>
				</td>
				<td>
					<a class="button btn wpd-input" id="wpd-delete-order-line-item-cogs"><?php _e( 'Delete All Order Line Item COGS', 'wpd-alpha-insights' ) ?></a>
				</td>
			</tr>
			<tr>
				<td>
					<label><?php _e( 'Upgrade Database', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'Run this function to manually update your database to the latest version.', 'wpd-alpha-insights' ); ?></div>
					<div class="wpd-meta"><?php _e( 'Installed Version', 'wpd-alpha-insights' ); ?>: <?php echo get_option('wpd_ai_db_version'); ?></div>
					<div class="wpd-meta"><?php _e( 'Required Version', 'wpd-alpha-insights' ); ?>: <?php echo WPD_AI_DB_VERSION ?></div>
				</td>
				<td>
					<a class="button btn wpd-input" id="wpd-update_db_manually"><?php _e( 'Update Database', 'wpd-alpha-insights' ) ?></a>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<div class="wpd-inline">
	<?php submit_button( __('Save Changes', 'wpd-alpha-insights'), 'primary pull-right', 'submit', false); ?>
</div>
<?php wpd_javascript_ajax_action( '#wpd-delete-cache', 'wpd_delete_all_cache' ); ?>
<?php wpd_javascript_ajax_action( '#wpd-reset-order-meta', 'wpd_reset_order_meta' ); ?>
<?php wpd_javascript_ajax_action( '#wpd-delete-order-line-item-cogs', 'wpd_delete_order_line_item_cogs' ); ?>
<?php wpd_javascript_ajax_action( '#wpd-update_db_manually', 'wpd-update_db_manually' ); ?>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		$('.wpd-delete-custom-order-cost').click(function() {
			let targetRow = $(this).closest('tr').remove();
		});
	});
</script>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		jQuery('.wpd-data-point').click(function(e) {

			// Prevent anything else
			e.preventDefault();

			// Show pop notification
			wpdPopNotification( 'loading', '<?php _e( 'Processing...', 'wpd-alpha-insights') ?>', '<?php _e( 'We are working on it!', 'wpd-alpha-insights') ?>' );

			// Get value
			let customOrderCostName = jQuery(this).data('val');

			// Some data cleaning
			if ( customOrderCostName.length < 4 ) {
				wpdPopNotification( 'fail', 'Hm, Something Is Not Quite Right', 'We couldnt locate the custom order cost key.');
				return false;
			}

			// Pass in the data
			let data = {
				'action': 'wpd_delete_custom_order_cost',
				'url'   : window.location.href,
				'value' : customOrderCostName,
				'nonce' : (typeof wpdAlphaInsights !== 'undefined' && wpdAlphaInsights.nonce) ? wpdAlphaInsights.nonce : ''
			};
			var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
			$.post(ajaxurl, data)
			.done(function( response ) {
				var parsedResponse = wpdHandleAjaxResponse(
					response,
					'<?php echo esc_js( __( 'Your request has been successfully completed.', 'wpd-alpha-insights') ); ?>',
					'<?php echo esc_js( __( 'Your action could not be completed.', 'wpd-alpha-insights') ); ?>'
				);
				if (parsedResponse && parsedResponse.success) {
					window.postMessage(parsedResponse, "*"); // jQuery(window).on("message", function(e) {});
				}
			})
			.fail(function( jqXHR, textStatus, errorThrown ) {
				var errorMessage = '<?php echo esc_js( __( 'Your action could not be completed.', 'wpd-alpha-insights') ); ?>';
				if (jqXHR.responseText) {
					try {
						var errorResponse = JSON.parse(jqXHR.responseText);
						errorMessage = wpdExtractResponseMessage(errorResponse, errorMessage);
					} catch(e) {
						// If we can't parse the error, use default message
					}
				}
				wpdPopNotification( 'fail', '<?php echo esc_js( __( 'Request Failed', 'wpd-alpha-insights') ); ?>', errorMessage );
			});
		});
	});
</script>