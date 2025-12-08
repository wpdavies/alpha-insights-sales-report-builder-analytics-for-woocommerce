<?php
/**
 *
 * Settings Page - Facebook Settings
 *
 * @package Alpha Insights
 * @version 3.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

	$facebook_settings = array();
	$api_setup_instructions = null;
	$facebook_ad_spend_category = 0;
	$facebook_settings = get_option( 'wpd_ai_facebook_integration', array() );
	$next_api_call = null;
	$data_count = array(
		'expenses' => 0,
		'campaigns' => 0
	);

	// Initialize new Facebook Auth handler
	$facebook_auth = null;
	if ( class_exists('WPD_Facebook_Auth') ) {
		$facebook_auth = new WPD_Facebook_Auth();
		$facebook_auth->enqueue_auth_assets();
	}

	if ( class_exists('WPD_Facebook_API') ) {
		$facebook_api = new WPD_Facebook_API( array('load_api' => true) );
		$api_setup_instructions 		= $facebook_api->wpd_get_configuration_instructions();
		$facebook_ad_spend_category 	= $facebook_api->facebook_expense_category_id;
		$data_count 					= $facebook_api->get_count_of_data();
		$next_api_call 					= wpd_next_fb_api_call();
	}

?>
<?php echo wp_kses_post( $api_setup_instructions ); ?>

<div class="wpd-wrapper">
	<div class="wpd-section-heading wpd-inline">
		<?php esc_html_e( 'Facebook API', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
		<?php submit_button( __('Save Changes', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), 'primary pull-right', 'submit', false); ?>
		<?php if( $facebook_settings['api_status'] == 'Healthy' ) : ?>
			<a href="#" class="wpd-input button button-secondary pull-right" id="wpd-refresh-facebook-api-data-top" style="margin-right: 5px;">Refresh All Campaign Data</a>
		<?php endif; ?>
		<a href="https://wpdavies.dev/documentation/alpha-insights/features/setting-up-the-woocommerce-meta-facebook-ads-api/?utm_campaign=Alpha+Insights+Documentation&utm_source=Alpha+Insights+Plugin" class="wpd-input button button-secondary pull-right" target="_blank" style="margin-right: 5px;">Documentation</a>
	</div>
</div>
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2"><?php esc_html_e( 'API Connection', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( $facebook_auth ) : ?>
			<tr>
				<td colspan="2">
					<label style="display: block; margin-bottom: 12px; font-weight: 600;"><?php esc_html_e( 'Connect To Facebook', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta" style="margin-bottom: 16px;"><?php esc_html_e( 'Connect your Facebook Ads account to track campaign performance and ad spend directly within Alpha Insights.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
					<?php $facebook_auth->render_auth_ui(); ?>
				</td>
			</tr>
			<?php endif; ?>
		</tbody>
	</table>
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2">
					<?php esc_html_e( 'Alpha Campaign Profit Tracking', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label>UTM Tracking Key / Values</label>
					<div class="wpd-meta">
						Add these values to the query parameters on the landing page of your Facebook Ads Campaign.
						<br>Our Alpha Campaign Profit Tracking system allows us to associate your orders with a particular Facebook Campaign & accurately report profitability.
						<br><a href="https://wpdavies.dev/documentation/alpha-insights/features/setting-up-the-woocommerce-meta-facebook-ads-api/?utm_campaign=Alpha+Insights+Documentation&utm_source=Alpha+Insights+Plugin">Click Here</a> to read the docs.
					</div>
				</td>
				<td>
					<input type="text" value="meta_cid={{campaign.id}}" style="width: 100%">
				</td>
			</tr>
		</tbody>
	</table>
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2"><?php esc_html_e( 'API Settings', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label><?php esc_html_e( 'API Call Schedule', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php esc_html_e( 'How often to query the Facebook API for your ad spend & campaign insights.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<select class="wpd-input" name="wpd_ai_facebook_integration[facebook_api_call_schedule]">
						<option value="daily" <?php echo esc_attr( wpd_selected_option( 'daily', $facebook_settings['facebook_api_call_schedule'] ) ); ?> ><?php esc_html_e( 'Daily', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
						<option value="12-hrs" <?php echo esc_attr( wpd_selected_option( '12-hrs', $facebook_settings['facebook_api_call_schedule'] ) ); ?> ><?php esc_html_e( 'Every 12 Hours', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
						<option value="6-hrs" <?php echo esc_attr( wpd_selected_option( '6-hrs', $facebook_settings['facebook_api_call_schedule'] ) ); ?> ><?php esc_html_e( 'Every 6 Hours', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
						<option value="3-hrs" <?php echo esc_attr( wpd_selected_option( '3-hrs', $facebook_settings['facebook_api_call_schedule'] ) ); ?> ><?php esc_html_e( 'Every 3 Hours', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
					</select>
				</td>
			</tr>
			<tr style="display:none;">
				<td>
					<label><?php esc_html_e( 'Collect Campaign Insights', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php esc_html_e( 'This will check your ad account for campaigns and build some basic reports based on those campaigns.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<select class="wpd-input" name="wpd_ai_facebook_integration[collect_campaign_insights]">
						<option value="true" <?php echo esc_attr( wpd_selected_option( 'true', $facebook_settings['collect_campaign_insights'] ) ); ?> ><?php esc_html_e( 'True', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
						<option value="false" <?php echo esc_attr( wpd_selected_option( 'false', $facebook_settings['collect_campaign_insights'] ) ); ?> ><?php esc_html_e( 'False', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
					</select>
				</td>
			</tr>
			<tr style="display:none;">
				<td>
					<label><?php _e( 'Collect Daily Ad Spend (Stored as an expense)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php _e( 'This will check your ad spend per day for your ad account and log it as an expense within Alpha Insights. This will run automatically as per your schedule.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<select class="wpd-input" name="wpd_ai_facebook_integration[collect_daily_ad_spend]">
						<option value="true" <?php echo wpd_selected_option( 'true', $facebook_settings['collect_daily_ad_spend'] ) ?> ><?php _e( 'True', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
						<option value="false" <?php echo wpd_selected_option( 'false', $facebook_settings['collect_daily_ad_spend'] ) ?> ><?php _e( 'False', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td>
					<label><?php esc_html_e( 'Ad Spend Expense Category', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php esc_html_e( 'Which expense category would you like us to save this in.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
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
									'selected'        => $facebook_ad_spend_category,
									'show_count'      => true,
									'hide_empty'      => false,
									'echo' 			  => true,
									'name'			  => 'wpd_ai_facebook_integration[facebook_expense_category_id]',
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
					<label><?php esc_html_e( 'Request Timeout', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php esc_html_e( 'HTTP Timeout Request in seconds. Extend this number if you are having timeout issues. Usually a response will be complete within 5 seconds (default).', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<input class="wpd-input" min="5" max="60" type="number" name="wpd_ai_facebook_integration[request_timeout]" value="<?php echo esc_attr( $facebook_settings['request_timeout'] ); ?>" step="1" placeholder="5">		
					<label for="wpd_ai_facebook_integration[request_timeout]" class="wpd-meta wpd-block-label"><?php esc_html_e( 'Number between 5-60', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>			
				</td>
			</tr>
			<tr>
				<td>
					<label><?php esc_html_e( 'Number Of Results To Collect Per Call', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php esc_html_e( 'How many results Facebook should return, they tell us if there are more results to grab which we automatically do. Only change this if you are getting timeout errors, it may help.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<input class="wpd-input" min="1" max="500" type="number" name="wpd_ai_facebook_integration[api_limit_per_page]" value="<?php echo esc_attr( $facebook_settings['api_limit_per_page'] ); ?>" step="1" placeholder="50">
					<label for="wpd_ai_facebook_integration[api_limit_per_page]" class="wpd-meta wpd-block-label"><?php esc_html_e( 'Default: 50', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>		
				</td>
			</tr>
		</tbody>
	</table>
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2"><?php esc_html_e( 'API Data', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<div class="">Daily Expenses Stored</div>
					<div class="">Campaigns Stored</div>
				</td>
				<td>
					<div class=""><?php echo esc_html( $data_count['expenses'] ); ?></div>
					<div class=""><?php echo esc_html( $data_count['campaigns'] ); ?></div>
				</td>
			</tr>
			<tr>
				<td>
					<div class="">Last Succesful Data Fetch</div>
				</td>
				<td>
					<div class=""><?php if ( function_exists('wpd_facebook_last_updated') ) echo esc_html( wpd_facebook_last_updated( $facebook_settings ) ); ?></div>
				</td>
			</tr>
			<tr>
				<td>
					<div class="">Next Scheduled Data Fetch</div>
				</td>
				<td>
					<div class=""><?php echo esc_html( ( $next_api_call ) ? wpd_calculate_time_difference( $next_api_call ) : 'N/A' ); ?></div>
				</td>
			</tr>
		</tbody>
	</table>
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2"><?php esc_html_e( 'API Tools', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label><?php esc_html_e( 'Refresh all API Data', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php esc_html_e( 'This function will create / update the Ad Spend & Campaign Insights for your ad account for all time.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<a href="#" class="wpd-input button button-secondary" id="wpd-refresh-facebook-api-data">Refresh All Campaign Data</a>
					<br><span class="wpd-meta">Last Updated <?php if ( function_exists('wpd_facebook_last_updated') ) echo esc_html( wpd_facebook_last_updated( $facebook_settings ) ); ?></span>
				</td>
			</tr>
			<tr>
				<td>
				<label><?php esc_html_e( 'API Status', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php esc_html_e( 'This will check your current API status and display the latest status message.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<a href="#" class="wpd-input button button-secondary" id="wpd-test-api-status">Check API Status</a>
				</td>
			</tr>	
			<tr>
				<td>
					<label><?php esc_html_e( 'Facebook Data Deletion', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php esc_html_e( 'These tools will delete the data that we have stored in your database from the Facebook API calls. This will not effect your Facebook ad account.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<a href="#" class="wpd-input button button-secondary" id="wpd-delete-all-expense-data">Delete All Expense Data</a>
					<a href="#" class="wpd-input button button-secondary" id="wpd-delete-all-campaign-data">Delete All Campaign Data</a>
				</td>
			</tr>
		</tbody>
	</table>
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2"><?php esc_html_e( 'Important Information', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td colspan="2">
					<ol>
						<li>The Facebook API Can only fetch back through 36 months of data.</li>
						<li>When we call the API, we always check for all data and create new data points or update existing points. If your API goes down, the next API call will fully refresh the data so there is never any data redundancy.</li>
						<li>Your Access token can only last a maximum of 60 days, after which you will need to reauthenticate using the Facebook button.</li>
						<li>You can only configure the API to check for data against one Facebook Ad account.</li>
						<li>Our Facebook Reports are built entirely on the results that are fetched from the Facebook API, so the reporting is as reliable as the information that your ad account has collected from the Facebook Pixel.</li>
						<li>If your ad account is in a different currency to your store currency, we will convert any monetary values from into your store's currency for reporting.</li>
						<li>The Facebook account that you login with must be an administrator of the Ad Account in order to gain access.</li>
						<li>This connection is read-only access, Alpha Insights does not have permission to make any changes to your Ad Account.</li>
					</ol>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<div class="wpd-inline">
	<?php submit_button( __('Save Changes', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), 'primary pull-right', 'submit', false); ?>
</div>
<?php wpd_javascript_ajax_action( '#wpd-delete-all-expense-data', 'wpd_delete_all_facebook_api_expense_data' ); ?>
<?php wpd_javascript_ajax_action( '#wpd-delete-all-campaign-data', 'wpd_delete_all_facebook_api_campaign_data' ); ?>
<?php wpd_javascript_ajax_action( '#wpd-refresh-facebook-api-data', 'wpd_refresh_all_facebook_api_data' ); ?>
<?php wpd_javascript_ajax_action( '#wpd-refresh-facebook-api-data-top', 'wpd_refresh_all_facebook_api_data' ); ?>
<?php wpd_javascript_ajax_action( '#wpd-test-api-status', 'wpd_test_facebook_api_status' ); ?>
