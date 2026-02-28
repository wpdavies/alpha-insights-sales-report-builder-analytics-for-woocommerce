<?php
/**
 * Pro upgrade modal template (server-side)
 *
 * Title and description are passed in; comparison table and CTA are fixed.
 * Used on Integrations and other "this is a Pro feature" screens.
 *
 * @package Alpha Insights
 * @since 5.4.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 */
defined( 'ABSPATH' ) || exit;

if ( empty( $title ) || ! is_string( $title ) ) {
	$title = __( 'This Is A Pro Feature', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
}
if ( empty( $description ) || ! is_string( $description ) ) {
	$description = __( 'Upgrade to Pro for expense management, Google & Meta ads integrations, advanced filtering and segmentation, export to PDF and CSV, third-party integrations, and realtime analytics—so you get a complete picture of your store\'s performance.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
}
// $upgrade_url is set by wpdai_render_pro_upsell_modal() (default or via cta_url param).
$icon_tick  = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4caf50" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22,4 12,14.01 9,11.01"></polyline></svg>';
$icon_cross = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#e0e0e0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
$allowed_icon_svg = array(
	'svg'     => array( 'width' => true, 'height' => true, 'viewbox' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'aria-hidden' => true ),
	'path'    => array( 'd' => true ),
	'polyline' => array( 'points' => true ),
	'line'    => array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true ),
);
?>
<div class="wpd-wrapper wpd-integration-settings-wrapper wpd-integration-pro-upsell wpd-pro-upsell-modal-style wpd-pro-upsell-modal-font">
	<div class="wpd-integration-pro-upsell-content wpd-pro-upsell-modal-content">
		<div class="wpd-pro-upsell-modal-icon">
			<div class="wpd-pro-upsell-icon-wrapper">
				<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
					<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
				</svg>
			</div>
		</div>
		<h2 id="wpd-upgrade-modal-title" class="wpd-integration-pro-upsell-title wpd-pro-upsell-modal-title"><?php echo esc_html( $title ); ?></h2>
		<p class="wpd-integration-pro-upsell-intro wpd-pro-upsell-modal-description"><?php echo esc_html( $description ); ?></p>
		<div class="wpd-integration-pro-upsell-comparison wpd-pro-upsell-comparison">
			<table class="wpd-pro-comparison-table">
				<thead>
					<tr>
						<th class="wpd-comparison-feature-col"><?php esc_html_e( 'Feature', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
						<th class="wpd-comparison-free-col"><?php esc_html_e( 'Free', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
						<th class="wpd-comparison-pro-col"><?php esc_html_e( 'Pro', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td class="wpd-comparison-feature-name"><?php esc_html_e( 'Drag and Drop Report Builder', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
						<td class="wpd-comparison-icon wpd-comparison-pro-icon"><?php echo wp_kses( $icon_tick, $allowed_icon_svg ); ?></td>
						<td class="wpd-comparison-icon wpd-comparison-pro-icon"><?php echo wp_kses( $icon_tick, $allowed_icon_svg ); ?></td>
					</tr>
					<tr>
						<td class="wpd-comparison-feature-name"><?php esc_html_e( 'Sales & Website Traffic Reporting', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
						<td class="wpd-comparison-icon wpd-comparison-pro-icon"><?php echo wp_kses( $icon_tick, $allowed_icon_svg ); ?></td>
						<td class="wpd-comparison-icon wpd-comparison-pro-icon"><?php echo wp_kses( $icon_tick, $allowed_icon_svg ); ?></td>
					</tr>
					<tr>
						<td class="wpd-comparison-feature-name"><?php esc_html_e( 'Create Unlimited Custom Reports', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
						<td class="wpd-comparison-icon wpd-comparison-pro-icon"><?php echo wp_kses( $icon_tick, $allowed_icon_svg ); ?></td>
						<td class="wpd-comparison-icon wpd-comparison-pro-icon"><?php echo wp_kses( $icon_tick, $allowed_icon_svg ); ?></td>
					</tr>
					<tr>
						<td class="wpd-comparison-feature-name"><?php esc_html_e( 'Enhanced Profit Reporting', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
						<td class="wpd-comparison-icon"><?php echo wp_kses( $icon_cross, $allowed_icon_svg ); ?></td>
						<td class="wpd-comparison-icon wpd-comparison-pro-icon"><?php echo wp_kses( $icon_tick, $allowed_icon_svg ); ?></td>
					</tr>
					<tr>
						<td class="wpd-comparison-feature-name"><?php esc_html_e( 'Integrate Your Ad Accounts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
						<td class="wpd-comparison-icon"><?php echo wp_kses( $icon_cross, $allowed_icon_svg ); ?></td>
						<td class="wpd-comparison-icon wpd-comparison-pro-icon"><?php echo wp_kses( $icon_tick, $allowed_icon_svg ); ?></td>
					</tr>
					<tr>
						<td class="wpd-comparison-feature-name"><?php esc_html_e( 'Third party integrations', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
						<td class="wpd-comparison-icon"><?php echo wp_kses( $icon_cross, $allowed_icon_svg ); ?></td>
						<td class="wpd-comparison-icon wpd-comparison-pro-icon"><?php echo wp_kses( $icon_tick, $allowed_icon_svg ); ?></td>
					</tr>
					<tr>
						<td class="wpd-comparison-feature-name"><?php esc_html_e( 'Expense Management System', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
						<td class="wpd-comparison-icon"><?php echo wp_kses( $icon_cross, $allowed_icon_svg ); ?></td>
						<td class="wpd-comparison-icon wpd-comparison-pro-icon"><?php echo wp_kses( $icon_tick, $allowed_icon_svg ); ?></td>
					</tr>
					<tr>
						<td class="wpd-comparison-feature-name"><?php esc_html_e( 'Realtime Data', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
						<td class="wpd-comparison-icon"><?php echo wp_kses( $icon_cross, $allowed_icon_svg ); ?></td>
						<td class="wpd-comparison-icon wpd-comparison-pro-icon"><?php echo wp_kses( $icon_tick, $allowed_icon_svg ); ?></td>
					</tr>
					<tr>
						<td class="wpd-comparison-feature-name"><?php esc_html_e( 'Advanced Widgets & Visualisations', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
						<td class="wpd-comparison-icon"><?php echo wp_kses( $icon_cross, $allowed_icon_svg ); ?></td>
						<td class="wpd-comparison-icon wpd-comparison-pro-icon"><?php echo wp_kses( $icon_tick, $allowed_icon_svg ); ?></td>
					</tr>
					<tr>
						<td class="wpd-comparison-feature-name"><?php esc_html_e( 'Advanced Custom Filters', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
						<td class="wpd-comparison-icon"><?php echo wp_kses( $icon_cross, $allowed_icon_svg ); ?></td>
						<td class="wpd-comparison-icon wpd-comparison-pro-icon"><?php echo wp_kses( $icon_tick, $allowed_icon_svg ); ?></td>
					</tr>
					<tr>
						<td class="wpd-comparison-feature-name"><?php esc_html_e( 'Share via CSV, PDF, & Live Share Link', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
						<td class="wpd-comparison-icon"><?php echo wp_kses( $icon_cross, $allowed_icon_svg ); ?></td>
						<td class="wpd-comparison-icon wpd-comparison-pro-icon"><?php echo wp_kses( $icon_tick, $allowed_icon_svg ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<div class="wpd-integration-pro-upsell-cta wpd-pro-upsell-modal-actions">
			<a href="<?php echo esc_url( $upgrade_url ); ?>" class="wpd-pro-upsell-cta-button" target="_blank" rel="noopener noreferrer">
				<span class="wpd-pro-upsell-cta-icon" aria-hidden="true">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
				</span>
				<span class="wpd-pro-upsell-cta-text"><?php esc_html_e( 'Upgrade to Pro', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></span>
			</a>
		</div>
	</div>
</div>
