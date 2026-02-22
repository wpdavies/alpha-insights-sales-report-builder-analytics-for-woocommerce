<?php
/**
 * Settings subpage: About Us
 *
 * Story about Alpha Insights and a call to action to leave a review.
 *
 * @package Alpha Insights
 * @since 5.4.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 */
defined( 'ABSPATH' ) || exit;

$review_url  = WPDAI_Reviews::get_review_url();
$is_pro      = defined( 'WPD_AI_PRO' ) && WPD_AI_PRO;
$cta_text    = $is_pro
	? __( 'Leave a Review on Trustpilot', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
	: __( 'Leave a review on WordPress.org', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
$founder_img = WPD_AI_URL_PATH . 'assets/img/chris-founder.png';

?>
<div class="wpd-wrapper wpd-about-page">
	<h2 class="wpd-about-hero"><?php esc_html_e( 'About Alpha Insights', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></h2>

	<div class="wpd-about-intro">
		<p>
			<?php
			echo esc_html__(
				'Alpha Insights began as a practical solution to a real problem. I couldn\'t clearly see accurate profit and cost data for the stores I was helping — so I built a system that could.',
				'alpha-insights-sales-report-builder-analytics-for-woocommerce'
			);
			?>
		</p>
		<p>
			<?php
			echo esc_html__(
				'What started as an internal tool evolved into a product used by store owners around the world who want more than surface-level analytics. Today, Alpha Insights helps businesses see their true margins, understand their numbers, and make decisions with confidence.',
				'alpha-insights-sales-report-builder-analytics-for-woocommerce'
			);
			?>
		</p>
	</div>

	<p class="wpd-about-byline"><?php esc_html_e( 'Chris Davies, Founder', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></p>

	<div class="wpd-about-founder">
		<img src="<?php echo esc_url( $founder_img ); ?>" alt="<?php esc_attr_e( 'Chris Davies, Founder', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>">
		<div>
			<p>
				<?php
				echo esc_html__(
					'I\'m Chris, founder of WP Davies and Alpha Insights. For over a decade, I\'ve worked alongside online stores — building conversion-focused websites and helping owners grow sustainably through better data and smarter decisions.',
					'alpha-insights-sales-report-builder-analytics-for-woocommerce'
				);
				?>
			</p>
			<p style="margin-top: 1rem;">
				<?php
				echo esc_html__(
					'Alpha Insights remains a founder-led product, built and supported with care. The goal is simple: give store owners clarity around profit so they can run stronger, healthier businesses.',
					'alpha-insights-sales-report-builder-analytics-for-woocommerce'
				);
				?>
			</p>
		</div>
	</div>
	<div class="wpd-about-intro">
	<p>
		<?php
		echo esc_html__(
			'If Alpha Insights has played a small role in improving your understanding of your business, a review would be genuinely appreciated. Independent products grow through trust, and your feedback helps others make informed decisions.',
			'alpha-insights-sales-report-builder-analytics-for-woocommerce'
		);
		?>
	</p>
	</div>
	<div class="wpd-about-cta-wrap">
		<a href="<?php echo esc_url( $review_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary button-large">
			<?php echo esc_html( $cta_text ); ?>
		</a>
	</div>

	<p class="wpd-about-signoff">
		<strong><?php esc_html_e( 'Chris Davies', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></strong>
		<?php esc_html_e( 'Founder, Alpha Insights', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
	</p>
</div>
