<?php
/**
 * Webhooks Integration
 *
 * @package Alpha Insights
 * @version 5.2.0
 * @since 5.2.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 */
defined( 'ABSPATH' ) || exit;

require_once WPD_AI_PATH . 'includes/integrations/WPDAI_Integration_Base.php';

class WPDAI_Integration_Register_Webhooks extends WPDAI_Integration_Base {

	/**
	 * Option name for webhook settings
	 *
	 * @var string
	 */
	private $option_name = 'wpd_ai_webhook_settings';

	/**
	 * {@inheritdoc}
	 */
	public function get_slug() {
		return 'webhooks';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label() {
		return __( 'Webhooks', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description() {
		return __( 'Send data to your webhook endpoint', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_pro() {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_enabled() {

		if ( ! class_exists( 'WPDAI_Webhook_Provider' ) ) {
			return false;
		}

		return WPDAI_Webhook_Provider::get_instance()->is_configured();
	}

	/**
	 * {@inheritdoc}
	 */
	public function render_settings() {
		$webhook_data = get_option( $this->option_name, array() );
		$next_webhook_sync = wpdai_next_scheduled_event_date_html( WPDAI_Webhook_Provider::$recurring_event_hook );
		if ( ! is_array( $webhook_data ) ) {
			$webhook_data = array();
		}
		$json_output = function_exists( 'wpdai_webhook_data_request' ) ? wpdai_webhook_data_request( null, null, false ) : array();
		?>
		<table class="wpd-table fixed widefat">
			<thead>
				<tr>
					<th colspan="2">
						<?php esc_html_e( 'Webhooks', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
						<a href="https://wpdavies.dev/documentation/alpha-insights/integrations/webhooks/webhook-setup/?utm_campaign=Alpha+Insights+Documentation&utm_source=Alpha+Insights+Plugin" class="wpd-input button button-secondary pull-right" target="_blank" style="margin-right: 5px;"><?php esc_html_e( 'Documentation', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a>
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
						<input class="wpd-input" style="width: 100%;" type="text" name="wpd_ai_webhook_settings[webhook_url]" value="<?php echo esc_attr( isset( $webhook_data['webhook_url'] ) ? $webhook_data['webhook_url'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Webhook Endpoint URL', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>">
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
							<?php
							$current_schedule = isset( $webhook_data['webhook_schedule'] ) ? $webhook_data['webhook_schedule'] : 'none';
							?>
							<option value="none" <?php echo esc_attr( wpdai_selected_option( 'none', $current_schedule ) ); ?>><?php esc_html_e( 'Don\'t Schedule Export', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
							<option value="daily" <?php echo esc_attr( wpdai_selected_option( 'daily', $current_schedule ) ); ?>><?php esc_html_e( 'Daily', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
							<option value="weekly" <?php echo esc_attr( wpdai_selected_option( 'weekly', $current_schedule ) ); ?>><?php esc_html_e( 'Weekly', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
							<option value="monthly" <?php echo esc_attr( wpdai_selected_option( 'monthly', $current_schedule ) ); ?>><?php esc_html_e( 'Monthly', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
						</select>
						<input type="hidden" name="wpd_ai_webhook_settings[webhook_schedule_last_run]" value="<?php echo esc_attr( isset( $webhook_data['webhook_schedule_last_run'] ) ? $webhook_data['webhook_schedule_last_run'] : '' ); ?>">
						<div class="wpd-meta"><?php esc_html_e( 'Your webhook will run at roughly 1am at the start of your period.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
						<?php if ( ! empty( $webhook_data['webhook_schedule_last_run'] ) ) : ?>
							<p><?php
							/* translators: %s: Date and time when the webhook last ran */
							printf( esc_html__( 'Your last successful webhook ran on %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $webhook_data['webhook_schedule_last_run'] ) ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<?php
				if ( ! empty( $next_webhook_sync ) ) :
					?>
					<tr>
						<td>
							<label><?php esc_html_e( 'Next Scheduled Sync', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
							<div class="wpd-meta"><?php esc_html_e( 'The next time the webhook export will run.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
						</td>
						<td>
							<?php echo wp_kses_post( $next_webhook_sync ); ?>
						</td>
					</tr>
				<?php endif; ?>
				<tr>
					<td>
						<label><?php esc_html_e( 'Enable Logging', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
						<div class="wpd-meta"><?php esc_html_e( 'Log webhook requests and responses for debugging.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
					</td>
					<td>
						<?php wpdai_checkbox( 'wpd_ai_webhook_settings[enable_logging]', isset( $webhook_data['enable_logging'] ) ? $webhook_data['enable_logging'] : null, __( 'Enable webhook logging', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>
					</td>
				</tr>
				<tr>
					<td>
						<label><?php esc_html_e( 'Send Webhook Now', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
						<div class="wpd-meta"><?php esc_html_e( 'We will immediately broadcast your test data once to your URL endpoint. Make sure you have saved your endpoint url.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
					</td>
					<td>
						<a href="#" id="test-webhook" class="wpd-input button secondary-button" data-wpd-ajax-action="wpd_webhook_export_manual"><?php esc_html_e( 'Test Webhook', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a>
					</td>
				</tr>
				<tr>
					<td colspan="2">
						<label><?php esc_html_e( 'Webhook Test Data Output', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
						<div class="wpd-meta">
							<?php esc_html_e( 'The output data below is for example purposes so you can see how the JSON object is being configured. Your scheduled data will output according to the schedule you have saved.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
						</div>
						<span class="wpd-data-output">
							<?php wpdai_debug( esc_html( wp_json_encode( $json_output, JSON_PRETTY_PRINT ) ), 'Webhook Test Data Output' ); ?>
						</span>
					</td>
				</tr>
				<tr>
					<td colspan="2">
						<?php wpdai_display_log( 'wpd_webhooks_log' ); ?>
						<?php wpdai_display_log( 'wpd_webhooks_error_log' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * {@inheritdoc}
	 */
	public function save_settings( $saved ) {
		if ( isset( $_POST['wpd_ai_webhook_settings'] ) && is_array( $_POST['wpd_ai_webhook_settings'] ) ) {
			$webhook_data = map_deep( wp_unslash( $_POST['wpd_ai_webhook_settings'] ), 'sanitize_text_field' );
			$webhook_data['enable_logging'] = ! empty( $webhook_data['enable_logging'] ) ? '1' : '0';
			$saved['Webhook Settings'] = update_option( $this->option_name, $webhook_data );
			if ( $saved['Webhook Settings'] ) {
				if ( function_exists( 'as_unschedule_all_actions' ) ) {
					as_unschedule_all_actions( WPDAI_Webhook_Provider::$recurring_event_hook );
				}
			}
		}
		return $saved;
	}
}