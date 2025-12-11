<?php
/**
 *
 * Settings Page - License
 *
 * @package Alpha Insights
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

if ( class_exists('WPD_Authenticator') ) {
	$authenticator 		= new WPD_Authenticator();
	$license_data 		= $authenticator->license_details();
	$license_key 		= $license_data['license_key'];
	$license_status 	= $license_data['license_status'];
	$license_details 	= $license_data['license_details'];
}


if ( $license_status === 'active' ) {
	$license_action = '<a href="#" class="wpd-input button button-secondary wpd-license-action" id="wpd-deactivate-license" data-wpd-ajax-action="wpd_deactivate_license">Deactivate License</a>';
} elseif( $license_status === 'inactive' ) {
	$license_action = '<a href="#" class="wpd-input button button-secondary wpd-license-action" id="wpd-activate-license" data-wpd-ajax-action="wpd_activate_license">Activate License</a>';
} elseif( $license_status === 'expired' ) {
	$license_action = '<a href="https://wpdavies.dev/my-account/subscriptions/?utm_campaign=Expired+License&utm_source=Alpha+Insights+Plugin" class="wpd-input button button-primary wpd-license-action" target="_blank">Renew License</a>';
} else {
	$license_action = null;
}
?>
<?php if ( $license_status === 'expired' ) : ?>
	<div class="wpd-white-block">
		<div class="wpd-section-heading"><?php esc_html_e( 'Your License Has Expired', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
		<p><?php esc_html_e( 'Looks like your license has expired, to resume your profit reporting you will need to renew your license.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></p>
		<p><strong><?php esc_html_e( 'Use code "RENEWAL20" to take 20% off your next payment.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></strong></p>
		<p><a href="https://wpdavies.dev/my-account/subscriptions/?utm_campaign=Expired+License&utm_source=Alpha+Insights+Plugin" class="wpd-input button button-primary wpd-license-action" target="_blank"><?php esc_html_e( 'Click here to renew', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a></p>
	</div>
<?php endif; ?>
<div class="wpd-wrapper">
	<div class="wpd-section-heading wpd-inline">
		Alpha Insights <?php esc_html_e( 'License Manager', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
		<?php submit_button( __( 'Save Changes', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'wpd-input primary pull-right', 'submit', false ); ?>
		<?php echo '<a href="#" class="wpd-input button button-secondary wpd-license-action pull-right" style="margin-right: 10px;" id="wpd-refresh-license" data-wpd-ajax-action="wpd_refresh_license">Refresh License</a>'; ?>
	</div>
</div>
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Your License', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
				<td><?php esc_html_e( 'License Status:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?> <strong class="wpd-license-status"><?php echo esc_html( $license_status ); ?></strong> <?php echo wp_kses_post( $license_action ); ?></td>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label><?php esc_html_e( 'Your License Key', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
					<div class="wpd-meta"><?php echo wp_kses_post( __( 'Enter the license key provided to you on purchase, this can be found in your order confirmation email or in your account area on', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?> <a href="https://wpdavies.dev/my-account/orders/?utm_campaign=Alpha+Insights+License&utm_source=Alpha+Insights+Plugin" target="_blank"><?php esc_html_e( 'WP Davies', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a>.</div>
				</td>
				<td><input class="wpd-input" type="text" value="<?php echo esc_attr( $license_key ); ?>" placeholder="Your License Key" name="wpd_ai_api_key" style="width: 100%;"></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'License Status', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
				<td><?php echo esc_html( ( isset( $license_details['license_status'] ) ) ? $license_details['license_status'] : '' ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'License Owner', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
				<td><?php echo esc_html( ( isset( $license_details['owner_first_name'] ) ) ? $license_details['owner_first_name'] . ' ' . $license_details['owner_last_name'] : '' ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Max Number Of Uses', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
				<td><?php echo esc_html( ( isset( $license_details['max_instance_number'] ) ) ? $license_details['max_instance_number'] : '' ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Number Of Uses Remaining', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
				<td><?php echo esc_html( ( isset( $license_details['number_use_remaining'] ) ) ? $license_details['number_use_remaining'] : '' ); ?></td>
			</tr>
			<tr>
				<td>
					<?php esc_html_e( 'Expiration Date', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
					<div class="wpd-meta">Your license expiry date is automatically updated as your subscription continues.<br>Each succesful payment during your subscription will push your expiry date out further.</div>
				</td>
				<td><?php echo esc_html( (isset($license_details['expiration_date']) && ! empty($license_details['expiration_date'])) ? gmdate( 'l jS F\, Y', strtotime($license_details['expiration_date']) ) : '' ); ?></td>
			</tr>
			<tr>
				<td>
					<?php esc_html_e( 'Last Updated', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
					<div class="wpd-meta">The last time your license data was checked and updated.</div>
				</td>
				<td><?php echo esc_html( (isset($license_data['last_updated']) && ! empty($license_data['last_updated'])) ? gmdate( 'l jS F\, Y \a\t g\:ia', strtotime($license_data['last_updated']) ) : '' ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Manage Your License', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?><div class="wpd-meta"><?php esc_html_e( 'Visit your account area on WP Davies to manage your license.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div></td>
				<td><a href="https://wpdavies.dev/my-account/?utm_campaign=Alpha+Insights+Manage+License&utm_source=Alpha+Insights+Plugin" target="_blank" class="wpd-input button button-secondary"><?php esc_html_e( 'Manage Your License', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a></td>
			</tr>
		</tbody>
	</table>
</div>
<div class="wpd-inline">
	<?php submit_button( __( 'Save Changes', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'wpd-input primary pull-right', 'submit', false); ?>
</div>