<?php
/**
 *
 * Settings Page - Google Ads Settings
 *
 * @package Alpha Insights
 * @version 3.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 * 
 * Settings stored in array wpd_ai_google_ads_api
 * 
 */
defined( 'ABSPATH' ) || exit;

// Defaults
$api_settings 	= array();
$data_count 	= array();
$is_configured 	= false;

// Initialize new Google Ads Auth handler
$google_auth = null;
if ( class_exists('WPD_Google_Ads_Auth') ) {
	$google_auth = new WPD_Google_Ads_Auth();
	$google_auth->enqueue_auth_assets();
}

if ( class_exists('WPD_Google_Ads_API') ) {

	// API Initialization
	$google_ads_api = new WPD_Google_Ads_API();

	// Get settings for display
	$api_settings 		= $google_ads_api->get_settings(); // Change to function
	$data_count 		= $google_ads_api->get_post_type_count(); // Change to function
	$is_configured 		= $google_ads_api->is_configured(); // Change to function
	$all_campaigns 		= wpd_get_all_google_campaign_post_ids();
	$google_ads_api->output_admin_notices(); // Change to function

}

// Stored Vars
$refresh_token 				= ( isset( $api_settings['refresh_token'] ) ) ? $api_settings['refresh_token'] : null;
$customer_accounts 			= ( isset( $api_settings['customer_accounts'] ) ) ? $api_settings['customer_accounts'] : array();
$ad_account_id 				= ( isset( $api_settings['ad_account_id'] ) ) ? $api_settings['ad_account_id'] : null;
$api_call_schedule 			= ( isset( $api_settings['api_call_schedule'] ) ) ? $api_settings['api_call_schedule'] : null;
$collect_campaign_insights 	= ( isset( $api_settings['collect_campaign_insights'] ) ) ? $api_settings['collect_campaign_insights'] : false;
$collect_daily_ad_spend 	= ( isset( $api_settings['collect_daily_ad_spend'] ) ) ? $api_settings['collect_daily_ad_spend'] : false;
$expense_category_id 		= ( isset( $api_settings['expense_category_id'] ) ) ? $api_settings['expense_category_id'] : 0;
$expense_count 				= ( isset( $data_count['expenses'] ) ) ? $data_count['expenses'] : 0;
$campaign_count 			= ( isset( $data_count['campaigns'] ) ) ? $data_count['campaigns'] : 0;
$last_data_fetch 			= ( isset( $api_settings['last_data_fetch'] ) ) ? $api_settings['last_data_fetch'] : null;
$next_data_fetch 			= ( isset( $api_settings['next_data_fetch'] ) ) ? $api_settings['next_data_fetch'] : null;
$account_age_years 			= ( isset($api_settings['account_age_years']) ) ? $api_settings['account_age_years'] : 0;

// Conversion Action Settings
$profit_conversion_action_id = get_option( 'wpd_ai_google_ads_profit_conversion_action_id', null );
$profit_conversion_action_details = get_option( 'wpd_ai_google_ads_profit_conversion_action_details', null );

// Add To Cart Conversion Action Settings
$add_to_cart_conversion_action_id = get_option( 'wpd_ai_google_ads_add_to_cart_conversion_action_id', null );
$add_to_cart_conversion_action_details = get_option( 'wpd_ai_google_ads_add_to_cart_conversion_action_details', null );

?>
<div class="wpd-wrapper">
	<div class="wpd-section-heading wpd-inline">
		<?php _e( 'Google Ads API', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
		<?php submit_button( __('Save Changes', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), 'primary pull-right', 'submit', false); ?>
		<?php if( $is_configured ) : ?>
			<a href="#" class="wpd-input button button-secondary pull-right" id="wpd-refresh-google-api-data-top" style="margin-right: 5px;">Refresh All Campaign Data</a>
		<?php endif; ?>
		<a href="https://wpdavies.dev/documentation/alpha-insights/features/setting-up-the-woocommerce-google-ads-api/?utm_campaign=Alpha+Insights+Documentation&utm_source=Alpha+Insights+Plugin" class="wpd-input button button-secondary pull-right" target="_blank" style="margin-right: 5px;">Documentation</a>
		</div>
</div>
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2"><?php _e( 'API Connection', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( $google_auth ) : ?>
			<tr>
				<td colspan="2">
					<label style="display: block; margin-bottom: 12px; font-weight: 600;"><?php _e( 'Connect To Google Ads', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta" style="margin-bottom: 16px;"><?php _e( 'Connect your Google Ads account to track campaign performance and ad spend directly within Alpha Insights.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
					<?php $google_auth->render_auth_ui(); ?>
				</td>
			</tr>
			<?php endif; ?>
		</tbody>
	</table>
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2">
					<?php _e( 'Alpha Campaign Profit Tracking', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label>UTM Tracking Key / Values</label>
					<div class="wpd-meta">
						Add these values to the query parameters on the landing page of your Google Ads Campaign.
						<br>Our Alpha Campaign Profit Tracking system allows us to associate your orders with a particular Google Campaign & accurately report profitability.
						<a href="https://wpdavies.dev/documentation/alpha-insights/features/setting-up-the-woocommerce-google-ads-api/?utm_campaign=Alpha+Insights+Documentation&utm_source=Alpha+Insights+Plugin">Click Here</a> to read the docs.
					</div>
				</td>
				<td>
					<input type="text" value="google_cid={campaignid}" style="width: 100%">
				</td>
			</tr>
		</tbody>
	</table>
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2"><?php _e( 'API Settings', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label><?php _e( 'API Call Schedule', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php _e( 'How often to query the Google API for your ad spend & campaign insights. This regular schedule will always check through the past 30 days of data and create or update accordingly. To update all time data use the Refresh All Data button.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<select class="wpd-input" name="wpd_ai_google_ads_api[api_call_schedule]">
						<option value="daily" <?php echo wpd_selected_option( 'daily', $api_call_schedule ) ?> ><?php _e( 'Daily', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
						<option value="12-hrs" <?php echo wpd_selected_option( '12-hrs', $api_call_schedule ) ?> ><?php _e( 'Every 12 Hours', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
						<option value="6-hrs" <?php echo wpd_selected_option( '6-hrs', $api_call_schedule ) ?> ><?php _e( 'Every 6 Hours', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
						<option value="3-hrs" <?php echo wpd_selected_option( '3-hrs', $api_call_schedule ) ?> ><?php _e( 'Every 3 Hours', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
					</select>
				</td>
			</tr>
			<tr style="display:none;">
				<td>
					<label><?php _e( 'Collect Campaign Insights', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php _e( 'This will check your ad account for campaigns and build some basic reports based on those campaigns.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<select class="wpd-input" name="wpd_ai_google_ads_api[collect_campaign_insights]">
						<option value="true" <?php echo wpd_selected_option( 'true', $collect_campaign_insights ) ?> ><?php _e( 'True', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
						<option value="false" <?php echo wpd_selected_option( 'false', $collect_campaign_insights ) ?> ><?php _e( 'False', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
					</select>
				</td>
			</tr>
			<tr style="display:none;">
				<td>
					<label><?php _e( 'Collect Daily Ad Spend (Stored as an expense)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php _e( 'This will check your ad spend per day for your ad account and log it as an expense within Alpha Insights. This will run automatically as per your schedule.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<select class="wpd-input" name="wpd_ai_google_ads_api[collect_daily_ad_spend]">
						<option value="true" <?php echo wpd_selected_option( 'true', $collect_daily_ad_spend ) ?> ><?php _e( 'True', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
						<option value="false" <?php echo wpd_selected_option( 'false', $collect_daily_ad_spend ) ?> ><?php _e( 'False', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td>
					<label><?php _e( 'Ad Spend Expense Category', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php _e( 'Which expense category would you like us to save this in.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<?php
						if ( taxonomy_exists('expense_category') ) {
							wp_dropdown_categories(
								array(
									'show_option_all' => false,
									'taxonomy'        => 'expense_category',
									'name'            => 'expense_category',
									'orderby'         => 'name',
									'selected'        => $expense_category_id,
									'show_count'      => true,
									'hide_empty'      => false,
									'echo' 			  => true,
									'name'			  => 'wpd_ai_google_ads_api[expense_category_id]',
									'hierarchical' 	  => true,
								)
							);
						}
					?>
					<a href="<?php echo( wpd_admin_page_url('add-expense-type') ) ?>" class="wpd-input button button-secondary">Add New Category</a>
				</td>
			</tr>
			<tr>
				<td>
					<label><?php _e( 'Ad Account Age (Years)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php _e( 'When we do an all time search, we\'ll check back this many years to fetch and store data. Defaults to 10 years.<br>You will want this number larger than your account age, but large queries may time out.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<input class="wpd-input" min="1" max="25" type="number" name="wpd_ai_google_ads_api[account_age_years]" value="<?php echo $account_age_years ?>" step="1" placeholder="10">
					<label for="wpd_ai_google_ads_api[account_age_years]" class="wpd-meta wpd-block-label"><?php _e( 'Default: 10', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>		
				</td>
			</tr>
		</tbody>
	</table>
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2"><?php _e( 'Conversion Action for Tracking Order Profit Value & Add To Carts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label><?php _e( 'Conversion Action for Tracking Order Profit Value', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta">
						<?php if ( ! empty($profit_conversion_action_details) ) : ?>
							<?php _e( 'A conversion action has been created and configured for tracking order profit value. Every time an order is detected with a GCLID, the profit value will be sent to this conversion action. You can view this conversion action in your Google Ads account under Goals > Summary and then clicking View All Conversions.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
						<?php else: ?>
							<?php _e( 'Create a conversion action that will be used to pass the profit value of an order back to Google Ads. Every time an order is detected with a GCLID, the profit value will be sent to this conversion action. You can view this conversion action in your Google Ads account under Goals > Summary and then clicking View All Conversions.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
						<?php endif; ?>
					</div>
				</td>
				<td>
					<?php if ( ! empty($profit_conversion_action_details) ) : ?>
						<div style="margin-bottom: 15px;">
							<strong>Current Conversion Action: </strong>
							<span><?php echo esc_html($profit_conversion_action_details['name']) ?> (ID: <?php echo esc_html($profit_conversion_action_details['id']) ?>) <?php echo wpd_status_circle("success") ?></span>
						</div>
						<div style="margin-bottom: 15px;">
							<a href="#ajax" class="wpd-input button button-secondary" id="wpd-delete-conversion-action">Delete Conversion Action</a>
						</div>
						<div class="wpd-meta">Conversion action is configured and currently sending the order profit value to Google Ads</div>
					<?php else: ?>
						<div style="margin-bottom: 15px;">
							<strong>Status: </strong>
							<span>No conversion action configured <?php echo wpd_status_circle("error") ?></span>
						</div>
						<?php if ( $is_configured ) : ?>
							<a href="#ajax" class="wpd-input button button-primary" id="wpd-create-conversion-action">Create Conversion Action</a>
						<?php endif; ?>
						<div class="wpd-meta">Click "Create Conversion Action" to set up profit tracking. This will create a new conversion action in your Google Ads account.</div>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td>
					<label><?php _e( 'Conversion Action for Add To Cart', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta">
						<?php if ( ! empty($add_to_cart_conversion_action_details) ) : ?>
							<?php _e( 'A conversion action has been created and configured for tracking Add To Cart events. Every time an Add To Cart event is detected with a GCLID, the event will be sent to this conversion action. You can view this conversion action in your Google Ads account under Goals > Summary and then clicking View All Conversions.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
						<?php else: ?>
							<?php _e( 'Create a conversion action that will be used to track Add To Cart events. Every time an Add To Cart event is detected with a GCLID, the event will be sent to this conversion action. You can view this conversion action in your Google Ads account under Goals > Summary and then clicking View All Conversions.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
						<?php endif; ?>
					</div>
				</td>
				<td>
					<?php if ( ! empty($add_to_cart_conversion_action_details) ) : ?>
						<div style="margin-bottom: 15px;">
							<strong>Current Conversion Action: </strong>
							<span><?php echo esc_html($add_to_cart_conversion_action_details['name']) ?> (ID: <?php echo esc_html($add_to_cart_conversion_action_details['id']) ?>) <?php echo wpd_status_circle("success") ?></span>
						</div>
						<div style="margin-bottom: 15px;">
							<a href="#ajax" class="wpd-input button button-secondary" id="wpd-delete-add-to-cart-conversion-action">Delete Conversion Action</a>
						</div>
						<div class="wpd-meta">Conversion action is configured and currently sending the Add To Cart event to Google Ads</div>
					<?php else: ?>
						<div style="margin-bottom: 15px;">
							<strong>Status: </strong>
							<span>No conversion action configured <?php echo wpd_status_circle("error") ?></span>
						</div>
						<?php if ( $is_configured ) : ?>
							<a href="#ajax" class="wpd-input button button-primary" id="wpd-create-add-to-cart-conversion-action">Create Add To Cart Conversion Action</a>
						<?php endif; ?>
						<div class="wpd-meta">Click "Create Add To Cart Conversion Action" to set up Add To Cart tracking. This will create a new conversion action in your Google Ads account.</div>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2"><?php _e( 'API Data', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<div class="">Daily Expenses Stored</div>
					<div class="">Campaigns Stored</div>
				</td>
				<td>
					<div class=""><?php echo $expense_count ?></div>
					<div class=""><?php echo $campaign_count ?></div>
				</td>
			</tr>
			<tr>
				<td>
					<div class="">Last Succesful Data Fetch</div>
				</td>
				<td>
					<div class=""><?php echo $last_data_fetch; ?></div>
				</td>
			</tr>
			<tr>
				<td>
					<div class="">Next Scheduled Data Fetch</div>
				</td>
				<td>
					<div class=""><?php echo $next_data_fetch ?></div>
				</td>
			</tr>
		</tbody>
	</table>
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2"><?php _e( 'API Tools', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label><?php _e( 'Refresh all API Data', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php _e( 'This function will create / update the Ad Spend & Campaign Insights for your ad account for the number of years set on this settings page. It will not delete anything, just update it using the latest data from the API. Use this sparingly, it is a large request for both the Google API and your website. It is only really required to fetch all time data once you\'ve established a connection.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<a href="#refresh" class="wpd-input button button-secondary" id="wpd-refresh-google-api-data">Refresh All Campaign Data</a>
					<br><span class="wpd-meta"><?php echo $last_data_fetch ?></span>
				</td>
			</tr>
			<tr>
				<td>
				<label><?php _e( 'API Status', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php _e( 'This will check your current API status and display the latest status message.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<a href="#" class="wpd-input button button-secondary" id="wpd-test-api-status">Check API Status</a>
				</td>
			</tr>	
			<tr>
				<td>
					<label><?php _e( 'Data Deletion', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php _e( 'These tools will delete the data that we have stored in your database from the Google Ads API calls. This will not effect your Google Ad account or any stored expenses or campaigns that were not created by the API.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<a href="#" class="wpd-input button button-secondary" id="wpd-delete-all-expense-data">Delete All Expense Data</a>
					<a href="#" class="wpd-input button button-secondary" id="wpd-delete-all-campaign-data">Delete All Campaign Data</a>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<div class="wpd-wrapper">
	<?php submit_button( __('Save Changes', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), 'primary pull-right', 'submit', false); ?>
</div>
<?php wpd_javascript_ajax_action( '#wpd-test-api-status', 'wpd_test_google_ads_api_status' ); ?>
<?php wpd_javascript_ajax_action( '#wpd-delete-all-expense-data', 'wpd_delete_all_google_api_expense_data' ); ?>
<?php wpd_javascript_ajax_action( '#wpd-delete-all-campaign-data', 'wpd_delete_all_google_api_campaign_data' ); ?>
<?php wpd_javascript_ajax_action( '#wpd-refresh-google-api-data-top', 'wpd_refresh_all_google_data' ); ?>
<?php wpd_javascript_ajax_action( '#wpd-refresh-google-api-data', 'wpd_refresh_all_google_data' ); ?>
<?php wpd_javascript_ajax_action( '#wpd-create-conversion-action', 'wpd_create_google_conversion_action' ); ?>
<?php wpd_javascript_ajax_action( '#wpd-delete-conversion-action', 'wpd_delete_google_conversion_action' ); ?>
<?php wpd_javascript_ajax_action( '#wpd-create-add-to-cart-conversion-action', 'wpd_create_google_add_to_cart_conversion_action' ); ?>
<?php wpd_javascript_ajax_action( '#wpd-delete-add-to-cart-conversion-action', 'wpd_delete_google_add_to_cart_conversion_action' ); ?>
<?php wpd_javascript_ajax_action( '#store_utm_campaigns', 'wpd_scan_utm_campaigns_via_order' ); ?>
<?php wpd_javascript_ajax_action( '#check-gclid-storage', 'wpd_scan_order_gclids' ); ?>

<!-- We only get one go at capturing a refresh token, if this has not been succesful then they will need to go here: https://myaccount.google.com/connections?continue=https%3A%2F%2Fmyaccount.google.com%2Fsecurity%3Fhl%3Den&hl=en and remove the authentication -->