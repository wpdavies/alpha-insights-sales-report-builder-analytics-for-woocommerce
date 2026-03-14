<?php
/**
 * Native support plugins – list of plugins Alpha Insights supports without configuration.
 *
 * Shown on the Integrations list view. Each item can have thumbnail, link, author, docs URL, etc.
 *
 * @package Alpha Insights
 * @since 5.4.14
 */
defined( 'ABSPATH' ) || exit;

/**
 * Class WPDAI_Native_Support_Plugins
 */
class WPDAI_Native_Support_Plugins {

	/**
	 * Default list of natively supported plugins.
	 * Each item: slug, label, description, logo_url (or thumbnail_id), link, author, author_url, docs_url.
	 *
	 * @return array[]
	 */
	public static function get_items() {
		$items = array(
			array(
				'slug'        => 'woocommerce-subscriptions',
				'label'       => __( 'WooCommerce Subscriptions', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'description' => __( 'Subscription and renewal order tracking, LTV and subscription reports.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'logo_url'    => 'https://woocommerce.com/wp-content/uploads/2012/09/Woo_Subscriptions_icon-marketplace-160x160-2.png',
				'thumbnail_id' => 0,
				'link'        => 'https://woocommerce.com/products/woocommerce-subscriptions/',
				'author'      => __( 'WooCommerce', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'author_url'  => 'https://woocommerce.com/',
				'docs_url'    => '',
			),
			array(
				'slug'        => 'woocommerce-product-bundles',
				'label'       => __( 'WooCommerce Product Bundles', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'description' => __( 'Bundle parent cost is derived from child products; no configuration needed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'logo_url'    => 'https://woocommerce.com/wp-content/uploads/2012/07/Product_Bundles_icon-marketplace-160x160-2.png',
				'thumbnail_id' => 0,
				'link'        => 'https://woocommerce.com/products/product-bundles/',
				'author'      => __( 'WooCommerce', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'author_url'  => 'https://woocommerce.com/',
				'docs_url'    => '',
			),
			array(
				'slug'        => 'wpc-product-bundles',
				'label'       => __( 'WPC Product Bundles', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'description' => __( 'WPClever bundle products; parent cost derived from children.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'logo_url'    => 'https://ps.w.org/woo-product-bundle/assets/icon-128x128.png',
				'thumbnail_id' => 0,
				'link'        => 'https://wordpress.org/plugins/woo-product-bundle/',
				'author'      => __( 'WPClever', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'author_url'  => 'https://wpclever.net/',
				'docs_url'    => '',
			),
			array(
				'slug'        => 'woocommerce-grouped-products',
				'label'       => __( 'WooCommerce Grouped Products', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'description' => __( 'Native grouped product type; cost of goods handled per child product.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'logo_url'    => WPD_AI_URL_PATH . 'assets/img/integrations/woocommerce-logo.jpg',
				'thumbnail_id' => 0,
				'link'        => 'https://woocommerce.com/document/group-bundle-products-woocommerce/',
				'author'      => __( 'WooCommerce', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'author_url'  => 'https://woocommerce.com/',
				'docs_url'    => '',
			),
			array(
				'slug'        => 'ppom-for-woocommerce',
				'label'       => __( 'PPOM for WooCommerce', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'description' => __( 'Product add-ons; add a Cost field per PPOM field for accurate COGS and profit.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'logo_url'    => 'https://ps.w.org/woocommerce-product-addon/assets/icon-128x128.gif?rev=3186763',
				'thumbnail_id' => 0,
				'link'        => 'https://wordpress.org/plugins/woocommerce-product-addon/',
				'author'      => __( 'Themeisle', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'author_url'  => 'https://themeisle.com/',
				'docs_url'    => '',
			),
		);

		foreach ( $items as $i => $item ) {
			$items[ $i ] = wp_parse_args(
				$item,
				array(
					'slug'         => '',
					'label'        => '',
					'description'  => '',
					'logo_url'     => '',
					'thumbnail_id' => 0,
					'link'         => '',
					'author'       => '',
					'author_url'   => '',
					'docs_url'     => '',
				)
			);
		}

		return apply_filters( 'wpd_ai_native_support_plugins', $items );
	}

	/**
	 * Get logo/thumbnail URL for an item (thumbnail_id takes precedence over logo_url).
	 *
	 * @param array $item Item from get_items().
	 * @return string URL or empty string.
	 */
	public static function get_item_logo_url( $item ) {
		$thumbnail_id = isset( $item['thumbnail_id'] ) ? absint( $item['thumbnail_id'] ) : 0;
		if ( $thumbnail_id > 0 ) {
			$url = wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' );
			if ( $url ) {
				return $url;
			}
		}
		return isset( $item['logo_url'] ) && is_string( $item['logo_url'] ) ? esc_url_raw( $item['logo_url'] ) : '';
	}

	/**
	 * Output the native support section (white box + heading + list).
	 *
	 * @return void
	 */
	public static function output_section() {
		$items = self::get_items();
		if ( empty( $items ) ) {
			return;
		}
		?>
		<div class="wpd-wrapper wpd-native-support-plugins-wrapper">
			<h3 class="wpd-native-support-plugins-title"><?php esc_html_e( 'Plugins with native support', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></h3>
			<p class="wpd-native-support-plugins-intro"><?php esc_html_e( 'These plugins work with Alpha Insights automatically. No configuration required.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></p>
			<div class="wpd-native-support-plugins-grid">
				<?php
				foreach ( $items as $item ) {
					self::output_item( $item );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Output a single native-support plugin item (card-style).
	 *
	 * @param array $item Item from get_items().
	 * @return void
	 */
	private static function output_item( $item ) {
		$slug        = isset( $item['slug'] ) ? sanitize_key( $item['slug'] ) : '';
		$label       = isset( $item['label'] ) ? $item['label'] : '';
		$description = isset( $item['description'] ) ? $item['description'] : '';
		$link        = isset( $item['link'] ) ? $item['link'] : '';
		$author      = isset( $item['author'] ) ? $item['author'] : '';
		$author_url  = isset( $item['author_url'] ) ? $item['author_url'] : '';
		$docs_url    = isset( $item['docs_url'] ) ? $item['docs_url'] : '';
		$logo_url    = self::get_item_logo_url( $item );

		if ( $label === '' ) {
			return;
		}
		?>
		<div class="wpd-native-support-plugin-card" data-slug="<?php echo esc_attr( $slug ); ?>">
			<div class="wpd-native-support-plugin-card-inner">
				<?php if ( $logo_url !== '' ) : ?>
					<div class="wpd-native-support-plugin-card-logo">
						<?php if ( $link !== '' ) : ?>
							<a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer" class="wpd-native-support-plugin-card-logo-link">
								<img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="wpd-native-support-plugin-card-logo-img">
							</a>
						<?php else : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="wpd-native-support-plugin-card-logo-img">
						<?php endif; ?>
					</div>
				<?php else : ?>
					<div class="wpd-native-support-plugin-card-logo wpd-native-support-plugin-card-logo-placeholder">
						<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
					</div>
				<?php endif; ?>
				<div class="wpd-native-support-plugin-card-body">
					<?php if ( $label !== '' ) : ?>
						<h4 class="wpd-native-support-plugin-card-label">
							<?php if ( $link !== '' ) : ?>
								<a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $label ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $label ); ?>
							<?php endif; ?>
						</h4>
					<?php endif; ?>
					<?php if ( $description !== '' ) : ?>
						<p class="wpd-native-support-plugin-card-description"><?php echo esc_html( $description ); ?></p>
					<?php endif; ?>
					<?php if ( $author !== '' ) : ?>
						<p class="wpd-native-support-plugin-card-author">
							<?php esc_html_e( 'By', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
							<?php if ( $author_url !== '' ) : ?>
								<a href="<?php echo esc_url( $author_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $author ); ?></a>
							<?php else : ?>
								<span><?php echo esc_html( $author ); ?></span>
							<?php endif; ?>
						</p>
					<?php endif; ?>
					<?php if ( $docs_url !== '' ) : ?>
						<p class="wpd-native-support-plugin-card-docs">
							<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer" class="wpd-native-support-plugin-card-docs-link">
								<?php esc_html_e( 'Documentation', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
								<span class="dashicons dashicons-external" aria-hidden="true"></span>
							</a>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
