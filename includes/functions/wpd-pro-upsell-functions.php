<?php
/**
 * Pro upsell modal (server-side) – template loader and render function
 *
 * @package Alpha Insights
 * @since 5.4.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 */
defined( 'ABSPATH' ) || exit;

/**
 * Default title for the pro upsell modal.
 *
 * @return string
 */
function wpdai_pro_upsell_modal_default_title() {
	return __( 'This Is A Pro Feature', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
}

/**
 * Default description for the pro upsell modal (value of upgrading: expense management, ads, filtering, export, integrations, realtime).
 *
 * @return string
 */
function wpdai_pro_upsell_modal_default_description() {
	return __( 'Upgrade to Alpha Insights Pro and move beyond basic revenue reporting. Automatically track every expense, connect your ad platforms, segment performance with advanced filtering, export professional PDF and CSV reports, and see the real profit behind every order.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
}

/**
 * Default CTA URL for the pro upsell modal (Upgrade to Pro link).
 *
 * @return string Escaped URL for pricing page with default UTM params.
 */
function wpdai_pro_upsell_modal_default_cta_url() {
	return wpdai_wpdavies_url( '/plugins/alpha-insights/pricing/', 'Alpha Insights Pro Upsell Modal', 'plugin' );
}

/**
 * Render the server-side pro upgrade modal (title, description, and CTA URL overridable; table fixed).
 *
 * Use this wherever you need the shared "this is a Pro feature" block with comparison table and Upgrade CTA.
 * React-based pro upsell UI is unchanged.
 *
 * @param array $args {
 *     Optional. Overrides for the modal.
 *
 *     @type string $title       Heading text. Default: "This Is A Pro Feature".
 *     @type string $description Intro paragraph. Default: value-focused copy (expense management, ads, export, etc.).
 *     @type string $cta_url     Upgrade to Pro link URL (e.g. with custom UTM params). Default: pricing URL with generic UTM.
 * }
 */
function wpdai_render_pro_upsell_modal( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'title'       => wpdai_pro_upsell_modal_default_title(),
		'description' => wpdai_pro_upsell_modal_default_description(),
		'cta_url'     => wpdai_pro_upsell_modal_default_cta_url(),
	) );
	$title       = is_string( $args['title'] ) ? $args['title'] : wpdai_pro_upsell_modal_default_title();
	$description = is_string( $args['description'] ) ? $args['description'] : wpdai_pro_upsell_modal_default_description();
	$cta_url     = is_string( $args['cta_url'] ) && $args['cta_url'] !== '' ? $args['cta_url'] : wpdai_pro_upsell_modal_default_cta_url();

	$upgrade_url = $cta_url;

	$template_path = WPD_AI_PATH . 'templates/wpd-pro-upsell-modal.php';
	if ( ! is_readable( $template_path ) ) {
		return;
	}
	include $template_path;
}

/**
 * Render the pro upgrade modal in a popup overlay (for use in admin_footer).
 * Call this only on the free version when the menu with .wpd-trigger-upgrade-modal is shown.
 * Includes inline styles and script to open on .wpd-trigger-upgrade-modal click and close on overlay/close/escape.
 */
function wpdai_render_pro_upsell_modal_popup() {
	$overlay_id = 'wpd-upgrade-modal-overlay';
	$close_btn_aria = __( 'Close', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
	?>
	<div id="<?php echo esc_attr( $overlay_id ); ?>" class="wpd-upgrade-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="wpd-upgrade-modal-title" aria-hidden="true" style="display: none; position: fixed; inset: 0; z-index: 100000; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; padding: 20px; box-sizing: border-box;">
		<div class="wpd-upgrade-modal-box" style="position: relative; overflow: auto; padding: 0; background: #ffffff; border-radius: 12px; box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15); max-width: 640px; width: 90%; max-height: 90vh;">
			<button type="button" class="wpd-upgrade-modal-close" aria-label="<?php echo esc_attr( $close_btn_aria ); ?>" style="position: absolute; top: 12px; right: 12px; z-index: 1; width: 36px; height: 36px; padding: 0; border: none; background: #f0f0f0; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #50575e; font-size: 20px; line-height: 1;">&times;</button>
			<div class="wpd-upgrade-modal-inner" style="padding: 24px 24px 24px 24px;">
				<?php wpdai_render_pro_upsell_modal(); ?>
			</div>
		</div>
	</div>
	<style>
		.wpd-upgrade-modal-overlay.is-visible { display: flex !important; }
		.wpd-upgrade-modal-overlay .wpd-wrapper { margin: 0; max-width: 100%; }
	</style>
	<script>
		(function() {
			var overlay = document.getElementById('<?php echo esc_js( $overlay_id ); ?>');
			if (!overlay) return;
			function show() { overlay.classList.add('is-visible'); overlay.setAttribute('aria-hidden', 'false'); }
			function hide() { overlay.classList.remove('is-visible'); overlay.setAttribute('aria-hidden', 'true'); }
			document.addEventListener('click', function(e) {
				if (e.target && e.target.closest && e.target.closest('.wpd-trigger-upgrade-modal')) {
					e.preventDefault();
					show();
				}
				if (e.target === overlay || (e.target.closest && e.target.closest('.wpd-upgrade-modal-close'))) {
					e.preventDefault();
					hide();
				}
			});
			document.addEventListener('keydown', function(e) {
				if (e.key === 'Escape' && overlay.classList.contains('is-visible')) hide();
			});
		})();
	</script>
	<?php
}
