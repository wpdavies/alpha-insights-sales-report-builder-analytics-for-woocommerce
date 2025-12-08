<?php
/**
 *
 * Settings Page - GEneral Settings
 *
 * @package Alpha Insights
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

// Load vars
$webhook_data 				= get_option( 'wpd_ai_webhook_settings' );
$json_output 				= wpd_webhook_data_request( null, null, false );
$starshipit_api_key 		= get_option( 'wpd_ai_starshipit_api_key' );
$starshipit_subscription_key = get_option( 'wpd_ai_starshipit_subscription_key' );

?>
<div class="wpd-wrapper">
	<div class="wpd-section-heading wpd-inline">
		<?php esc_html_e( 'Integration Settings', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
		<?php submit_button( __('Save Changes', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), 'primary pull-right', 'submit', false); ?>
	</div>
</div>
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2">
					<?php esc_html_e( 'Integrations', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
					<?php if ( ! WPD_AI_PRO ) : ?>
						<span class="wpd-ai-pro-setting-label"><?php esc_html_e( '(Pro Feature - Upgrade to unlock)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></span>
					<?php endif; ?>
				</th>
			</tr>
		</thead>
		<tbody>
			<tr <?php if ( ! WPD_AI_PRO ) echo 'class="wpd-ai-pro-setting"'; ?>>
				<td>
					<label><?php esc_html_e( 'StarShipIt', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php esc_html_e( 'This is where we will sync your shipping costs with your orders.<br>Once you\'ve entered a value for both the API key and subscription key, you will need to save your settings to activate the integration. A daily schedule will run that will sync 50 orders at a time, fetching the shipping cost and automatically saving it to the order.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<input class="wpd-input" style="width: 100%;" type="text" name="wpd_ai_starshipit_api_key" value="<?php echo esc_attr( $starshipit_api_key ); ?>" placeholder="StarShipIt API Key">
					<label for="wpd_ai_starshipit_api_key" class="wpd-meta wpd-block-label"><?php esc_html_e( 'The API key for your StarShipIt account', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<input class="wpd-input" style="width: 100%;" type="text" name="wpd_ai_starshipit_subscription_key" value="<?php echo esc_attr( $starshipit_subscription_key ); ?>" placeholder="StarShipIt Subscription Key">
					<label for="wpd_ai_starshipit_subscription_key" class="wpd-meta wpd-block-label"><?php esc_html_e( 'The subscription key for your StarShipIt account', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
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
					<?php esc_html_e( 'Webhooks', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
					<a href="https://wpdavies.dev/documentation/alpha-insights/features/setting-up-a-webhook-integrating-with-third-party-apps/?utm_campaign=Alpha+Insights+Documentation&utm_source=Alpha+Insights+Plugin" class="wpd-input button button-secondary pull-right" target="_blank" style="margin-right: 5px;">Documentation</a>
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label><?php esc_html_e( 'Webhook URL', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php esc_html_e( 'This is where we will post your webhook data.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<input class="wpd-input" style="width: 100%;" type="text" name="wpd_ai_webhook_settings[webhook_url]" value="<?php echo esc_attr( $webhook_data['webhook_url'] ); ?>" placeholder="Webhook Endpoint URL">
					<label for="wpd_ai_webhook_settings[webhook_url]" class="wpd-meta wpd-block-label"><?php esc_html_e( 'The URL where we will send your data', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
				</td>
			</tr>
			<tr>
				<td>
					<label><?php esc_html_e( 'Schedule Webhook Export', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php esc_html_e( 'We will broadcast your webhook data according to this schedule.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
					<div class="wpd-meta"><?php esc_html_e( 'Daily schedule will export your previous day\'s data once per day.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
					<div class="wpd-meta"><?php esc_html_e( 'Weekly schedule will export your previous weeks\'s data once per week.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
					<div class="wpd-meta"><?php esc_html_e( 'Monthly schedule will export your previous month\'s data once per month.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<select class="wpd-input" name="wpd_ai_webhook_settings[webhook_schedule]">
						<option value="none" <?php echo esc_attr( wpd_selected_option( 'none', $webhook_data['webhook_schedule'] ) ); ?> ><?php esc_html_e( 'Dont Schedule Export', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
						<option value="daily" <?php echo esc_attr( wpd_selected_option( 'daily', $webhook_data['webhook_schedule'] ) ); ?> ><?php esc_html_e( 'Daily', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
						<option value="weekly" <?php echo esc_attr( wpd_selected_option( 'weekly', $webhook_data['webhook_schedule'] ) ); ?> ><?php esc_html_e( 'Weekly', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
						<option value="monthly" <?php echo esc_attr( wpd_selected_option( 'monthly', $webhook_data['webhook_schedule'] ) ); ?> ><?php esc_html_e( 'Monthly', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
					</select>
					<input type="hidden" name="wpd_ai_webhook_settings[webhook_schedule_last_run]" value="<?php echo esc_attr( $webhook_data['webhook_schedule_last_run'] ); ?>">
					<div class="wpd-meta">Your webhook will run at roughly 1am at the start of your period.</div>
					<?php if ( ! empty($webhook_data['webhook_schedule_last_run']) ) : ?>
						<p><?php printf( esc_html__( 'Your last successful webhook ran on %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $webhook_data['webhook_schedule_last_run'] ) ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<td>
					<label><?php esc_html_e( 'Send Webhook Now', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php esc_html_e( 'We will immediately broadcast your test data once to your URL endpoint. Make sure you have saved your endpoint url.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
				</td>
				<td>
					<a href="#" id="test-webhook" class="wpd-input button secondary-button">Test Webhook</a>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<label>Webhook Test Data Output</label>
					<div class="wpd-meta">
						The output data below is for example purposes so you can see how the JSON object is being configured.<br>Your scheduled data will output according to the schedule you have saved.<br>
					</div>
					<span class="wpd-data-output">
						<pre style="background-color: #f7f7f7; padding: 25px;"><?php echo esc_html( json_encode($json_output, JSON_PRETTY_PRINT) ); ?></pre>
					</span>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<div class="wpd-inline">
	<?php submit_button( __('Save Changes', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), 'primary pull-right', 'submit', false); ?>
</div>
<?php wpd_javascript_ajax_action( '#test-webhook', 'wpd_webhook_export_manual' ); ?>
