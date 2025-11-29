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
	$license_action = '<a href="#" class="wpd-input button button-secondary wpd-license-action" id="wpd-deactivate-license">Deactivate License</a>';
} elseif( $license_status === 'inactive' ) {
	$license_action = '<a href="#" class="wpd-input button button-secondary wpd-license-action" id="wpd-activate-license">Activate License</a>';
} elseif( $license_status === 'expired' ) {
	$license_action = '<a href="https://wpdavies.dev/my-account/subscriptions/?utm_campaign=Expired+License&utm_source=Alpha+Insights+Plugin" class="wpd-input button button-primary wpd-license-action" target="_blank">Renew License</a>';
} else {
	$license_action = null;
}
?>
<?php if ( $license_status === 'expired' ) : ?>
	<div class="wpd-white-block">
		<div class="wpd-section-heading"><?php _e( 'Your License Has Expired', WPD_AI_TEXT_DOMAIN ); ?></div>
		<p><?php _e( 'Looks like your license has expired, to resume your profit reporting you will need to renew your license.', WPD_AI_TEXT_DOMAIN ); ?></p>
		<p><strong><?php _e( 'Use code "RENEWAL20" to take 20% off your next payment.', WPD_AI_TEXT_DOMAIN ); ?></strong></p>
		<p><a href="https://wpdavies.dev/my-account/subscriptions/?utm_campaign=Expired+License&utm_source=Alpha+Insights+Plugin" class="wpd-input button button-primary wpd-license-action" target="_blank"><?php _e( 'Click here to renew', WPD_AI_TEXT_DOMAIN ); ?></a></p>
	</div>
<?php endif; ?>
<div class="wpd-wrapper">
	<div class="wpd-section-heading wpd-inline">
		Alpha Insights <?php _e( 'License Manager', WPD_AI_TEXT_DOMAIN ); ?>
		<?php submit_button( __( 'Save Changes', WPD_AI_TEXT_DOMAIN ), 'wpd-input primary pull-right', 'submit', false ); ?>
		<?php echo '<a href="#" class="wpd-input button button-secondary wpd-license-action pull-right" style="margin-right: 10px;" id="wpd-refresh-license">Refresh License</a>'; ?>
	</div>
</div>
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<tr>
				<th><?php _e( 'Your License', WPD_AI_TEXT_DOMAIN ); ?></th>
				<td><?php _e( 'License Status:', WPD_AI_TEXT_DOMAIN ); ?> <strong class="wpd-license-status"><?php echo $license_status; ?></strong> <?php echo $license_action ?></td>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<label><?php _e( 'Your License Key', WPD_AI_TEXT_DOMAIN ); ?></label>
					<div class="wpd-meta"><?php _e( 'Enter the license key provided to you on purchase, this can be found in your order confirmation email or in your account area on', WPD_AI_TEXT_DOMAIN ); ?> <a href="https://wpdavies.dev/my-account/orders/?utm_campaign=Alpha+Insights+License&utm_source=Alpha+Insights+Plugin" target="_blank">WP Davies</a>.</div>
				</td>
				<td><input class="wpd-input" type="text" value="<?php echo $license_key ?>" placeholder="Your License Key" name="wpd_ai_api_key" style="width: 100%;"></td>
			</tr>
			<tr>
				<td><?php _e( 'License Status', WPD_AI_TEXT_DOMAIN ); ?></td>
				<td><?php echo ( isset( $license_details['license_status'] ) ) ? $license_details['license_status'] : null; ?></td>
			</tr>
			<tr>
				<td><?php _e( 'License Owner', WPD_AI_TEXT_DOMAIN ); ?></td>
				<td><?php echo ( isset( $license_details['owner_first_name'] ) ) ? $license_details['owner_first_name'] . ' ' . $license_details['owner_last_name'] : null;  ?></td>
			</tr>
			<tr>
				<td><?php _e( 'Max Number Of Uses', WPD_AI_TEXT_DOMAIN ); ?></td>
				<td><?php echo ( isset( $license_details['max_instance_number'] ) ) ? $license_details['max_instance_number'] : null;  ?></td>
			</tr>
			<tr>
				<td><?php _e( 'Number Of Uses Remaining', WPD_AI_TEXT_DOMAIN ); ?></td>
				<td><?php echo ( isset( $license_details['number_use_remaining'] ) ) ? $license_details['number_use_remaining'] : null;  ?></td>
			</tr>
			<tr>
				<td>
					<?php _e( 'Expiration Date', WPD_AI_TEXT_DOMAIN ); ?>
					<div class="wpd-meta">Your license expiry date is automatically updated as your subscription continues.<br>Each succesful payment during your subscription will push your expiry date out further.</div>
				</td>
				<td><?php echo (isset($license_details['expiration_date']) && ! empty($license_details['expiration_date'])) ? date( 'l jS F\, Y', strtotime($license_details['expiration_date']) ) : null; ?></td>
			</tr>
			<tr>
				<td>
					<?php _e( 'Last Updated', WPD_AI_TEXT_DOMAIN ); ?>
					<div class="wpd-meta">The last time your license data was checked and updated.</div>
				</td>
				<td><?php echo (isset($license_data['last_updated']) && ! empty($license_data['last_updated'])) ? date( 'l jS F\, Y \a\t g\:ia', strtotime($license_data['last_updated']) ) : null; ?></td>
			</tr>
			<tr>
				<td><?php _e( 'Manage Your License', WPD_AI_TEXT_DOMAIN ); ?><div class="wpd-meta"><?php _e( 'Visit your account area on WP Davies to manage your license.', WPD_AI_TEXT_DOMAIN ); ?></div></td>
				<td><a href="https://wpdavies.dev/my-account/?utm_campaign=Alpha+Insights+Manage+License&utm_source=Alpha+Insights+Plugin" target="_blank" class="wpd-input button button-secondary"><?php _e( 'Manage Your License', WPD_AI_TEXT_DOMAIN ); ?></a></td>
			</tr>
		</tbody>
	</table>
</div>
<div class="wpd-inline">
	<?php submit_button( __( 'Save Changes', WPD_AI_TEXT_DOMAIN ), 'wpd-input primary pull-right', 'submit', false); ?>
</div>
<?php wpd_javascript_ajax_action( '#wpd-deactivate-license', 'wpd_deactivate_license' ) ?>
<?php wpd_javascript_ajax_action( '#wpd-activate-license', 'wpd_activate_license' ) ?>
<?php wpd_javascript_ajax_action( '#wpd-refresh-license', 'wpd_refresh_license' ) ?>