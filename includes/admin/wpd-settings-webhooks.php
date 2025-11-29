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

?>
<div class="wpd-wrapper">
	<div class="wpd-section-heading wpd-inline">
		<?php _e( 'Webhook Settings', 'wpd-alpha-insights' ); ?>
		<?php submit_button( __('Save Changes', 'wpd-alpha-insights'), 'primary pull-right', 'submit', false); ?>
		<a href="https://wpdavies.dev/documentation/alpha-insights/features/setting-up-a-webhook-integrating-with-third-party-apps/?utm_campaign=Alpha+Insights+Documentation&utm_source=Alpha+Insights+Plugin" class="wpd-input button button-secondary pull-right" target="_blank" style="margin-right: 5px;">Documentation</a>
	</div>
</div>
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2"><?php _e( 'Webhook Export', 'wpd-alpha-insights' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label><?php _e( 'Webhook URL', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'This is where we will post your webhook data.', 'wpd-alpha-insights' ); ?></div>
				</td>
				<td>
					<input class="wpd-input" style="width: 100%;" type="text" name="wpd_ai_webhook_settings[webhook_url]" value="<?php echo $webhook_data['webhook_url'] ?>" placeholder="Webhook Endpoint URL">
					<label for="wpd_ai_webhook_settings[webhook_url]" class="wpd-meta wpd-block-label"><?php _e( 'The URL where we will send your data', 'wpd-alpha-insights' ); ?></label>
				</td>
			</tr>
			<tr>
				<td>
					<label><?php _e( 'Schedule Webhook Export', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'We will broadcast your webhook data according to this schedule.', 'wpd-alpha-insights' ); ?></div>
					<div class="wpd-meta"><?php _e( 'Daily schedule will export your previous day\'s data once per day.', 'wpd-alpha-insights' ); ?></div>
					<div class="wpd-meta"><?php _e( 'Weekly schedule will export your previous weeks\'s data once per week.', 'wpd-alpha-insights' ); ?></div>
					<div class="wpd-meta"><?php _e( 'Monthly schedule will export your previous month\'s data once per month.', 'wpd-alpha-insights' ); ?></div>
				</td>
				<td>
					<select class="wpd-input" name="wpd_ai_webhook_settings[webhook_schedule]">
						<option value="none" <?php echo wpd_selected_option( 'none', $webhook_data['webhook_schedule'] ) ?> ><?php _e( 'Dont Schedule Export', 'wpd-alpha-insights' ); ?></option>
						<option value="daily" <?php echo wpd_selected_option( 'daily', $webhook_data['webhook_schedule'] ) ?> ><?php _e( 'Daily', 'wpd-alpha-insights' ); ?></option>
						<option value="weekly" <?php echo wpd_selected_option( 'weekly', $webhook_data['webhook_schedule'] ) ?> ><?php _e( 'Weekly', 'wpd-alpha-insights' ); ?></option>
						<option value="monthly" <?php echo wpd_selected_option( 'monthly', $webhook_data['webhook_schedule'] ) ?> ><?php _e( 'Monthly', 'wpd-alpha-insights' ); ?></option>
					</select>
					<input type="hidden" name="wpd_ai_webhook_settings[webhook_schedule_last_run]" value="<?php echo $webhook_data['webhook_schedule_last_run'] ?>">
					<div class="wpd-meta">Your webhook will run at roughly 1am at the start of your period.</div>
					<?php if ( ! empty($webhook_data['webhook_schedule_last_run']) ) : ?>
						<p><?php printf( 'Your last successful webhook ran on %s', $webhook_data['webhook_schedule_last_run'] ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th colspan="2">
					<?php _e( 'Webhook Testing', 'wpd-alpha-insights' ); ?>
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label><?php _e( 'Send Webhook Now', 'wpd-alpha-insights' ); ?></label>
					<div class="wpd-meta"><?php _e( 'We will immediately broadcast your test data once to your URL endpoint. Make sure you have saved your endpoint url.', 'wpd-alpha-insights' ); ?></div>
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
						<pre style="background-color: #f7f7f7; padding: 25px;"><?php echo json_encode($json_output, JSON_PRETTY_PRINT); ?></pre>
					</span>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<div class="wpd-inline">
	<?php submit_button( __('Save Changes', 'wpd-alpha-insights'), 'primary pull-right', 'submit', false); ?>
</div>
<?php wpd_javascript_ajax_action( '#test-webhook', 'wpd_webhook_export_manual' ); ?>
