<?php
/**
 *
 * Core functions for Alpha Insights
 *
 * @package Alpha Insights
 * @since 1.0.0
 * @version 5.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 *
 *	HTML container for small processing notifications	
 *
 */
add_action( 'admin_footer', 'wpdai_admin_notification_pop' );
function wpdai_admin_notification_pop() {

	if ( ! is_wpdai_page() ) return; ?>
	<div class="wpd-notification-pop" id="wpd-notification-pop">
		<div class="wpd-exit-notification-pop"><span class="dashicons dashicons-no-alt"></span></div>
		<table>
			<tbody>
				<tr>
					<td class="wpd-notification-pop-icon"><?php wpdai_preloader( 40 ); ?></td>
					<td>
						<div class="wpd-notification-pop-title"><?php esc_html_e( 'Processing', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>...</div>
						<div class="wpd-meta wpd-notification-pop-subtitle"><?php esc_html_e( 'We are working on it!', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></div>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	<?php

}

/**
 * Add Documentation Modal HTML
 */
add_action('admin_footer', 'wpdai_documentation_modal_html');
function wpdai_documentation_modal_html() {
	
	if ( ! is_wpdai_page() ) {
		return;
	}
	
	$logo_icon_url = WPD_AI_URL_PATH . 'assets/img/Alpha-Insights-Icon-Large.png';
	
	?>
	<div id="wpd-docs-modal-overlay" class="wpd-docs-modal-overlay">
		<div class="wpd-docs-modal">
		<!-- Left Side - Brand Panel -->
		<div class="wpd-docs-modal-brand">
			<!-- Support Card with Branding -->
			<div class="wpd-docs-support-card">
				<!-- Branding Section -->
				<div class="wpd-docs-brand-logo-container">
					<div class="wpd-docs-brand-logo-row">
						<img src="<?php echo esc_url($logo_icon_url); ?>" alt="Alpha Insights Icon" class="wpd-docs-brand-logo-icon" />
						<div class="wpd-docs-brand-logo-text">
							<div class="wpd-docs-brand-title"><?php esc_html_e('Alpha Insights', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></div>
							<div class="wpd-docs-brand-subtitle"><?php esc_html_e('Intelligent Profit Reports', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></div>
						</div>
					</div>
				</div>
				
				<?php $is_pro = defined('WPD_AI_PRO') && WPD_AI_PRO; ?>
				<?php if ( $is_pro ) : ?>
				<!-- Separator -->
				<div class="wpd-docs-support-separator"></div>
				
				<!-- Support Options -->
				<div class="wpd-docs-support-items">
					<div class="wpd-docs-support-item">
						<div class="wpd-docs-support-icon">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
								<polyline points="14 2 14 8 20 8"></polyline>
								<line x1="16" y1="13" x2="8" y2="13"></line>
								<line x1="16" y1="17" x2="8" y2="17"></line>
								<polyline points="10 9 9 9 8 9"></polyline>
							</svg>
						</div>
						<div class="wpd-docs-support-content">
							<a href="https://wpdavies.dev/my-account/my-tickets/" target="_blank" class="wpd-docs-support-link">
								<h4 class="wpd-docs-support-title"><?php esc_html_e('Open A Support Ticket', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></h4>
								<p class="wpd-docs-support-text"><?php esc_html_e('Get personalized help from our support team', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
							</a>
						</div>
					</div>
					
					<div class="wpd-docs-support-item">
						<div class="wpd-docs-support-icon">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
								<polyline points="22,6 12,13 2,6"></polyline>
							</svg>
						</div>
						<div class="wpd-docs-support-content">
							<a href="mailto:support@wpdavies.dev" class="wpd-docs-support-link">
								<h4 class="wpd-docs-support-title"><?php esc_html_e('Email Us', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></h4>
								<p class="wpd-docs-support-text">support@wpdavies.dev</p>
							</a>
						</div>
					</div>
				</div>
				<?php else : ?>
				<!-- Separator -->
				<div class="wpd-docs-support-separator"></div>
				
				<!-- Upgrade CTA for Free Version -->
				<div class="wpd-docs-upgrade-cta">
					<div class="wpd-docs-upgrade-icon">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"></path>
						</svg>
					</div>
					<div class="wpd-docs-upgrade-content">
						<h4 class="wpd-docs-upgrade-title"><?php esc_html_e('Upgrade to Pro', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></h4>
						<p class="wpd-docs-upgrade-text"><?php esc_html_e('Unlock advanced features, priority support, and more', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
						<a href="https://wpdavies.dev/plugins/alpha-insights/pricing/?utm_campaign=Alpha+Insights+Help+Modal&utm_source=Alpha+Insights+Plugin" target="_blank" class="wpd-docs-upgrade-button">
							<?php esc_html_e('Upgrade Now', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
								<polyline points="15 3 21 3 21 9"></polyline>
								<line x1="10" y1="14" x2="21" y2="3"></line>
							</svg>
						</a>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</div>
			
			<!-- Right Side - Documentation Content -->
			<div class="wpd-docs-modal-content">
				<!-- Header -->
				<div class="wpd-docs-modal-header">
					<h2 class="wpd-docs-modal-title"><?php esc_html_e('Documentation', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></h2>
					<button type="button" class="wpd-docs-modal-close" aria-label="<?php esc_attr_e('Close', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>">
						&times;
					</button>
				</div>
				
				<!-- Search Bar -->
				<div class="wpd-docs-search-container">
					<input 
						type="text" 
						class="wpd-docs-search-input" 
						placeholder="<?php esc_attr_e('Search documentation...', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?>"
						id="wpd-docs-search"
					/>
				</div>
				
				<!-- Main Content Area -->
				<div class="wpd-docs-modal-body">
					<!-- Sidebar Navigation -->
					<div class="wpd-docs-sidebar">
						<nav class="wpd-docs-sidebar-nav" id="wpd-docs-nav">
							<!-- Navigation will be populated via JavaScript -->
						</nav>
					</div>
					
					<!-- Content Viewer -->
					<div class="wpd-docs-viewer">
						<div class="wpd-docs-loading">
							<div class="wpd-docs-loading-spinner"></div>
							<p><?php esc_html_e('Loading documentation...', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'); ?></p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 *
 *	Order hooks correctly for admin notice
 *	You must add your hooks before the do_action
 *
 */
add_action( 'admin_notices', 'wpdai_setup_notice_hook' );
function wpdai_setup_notice_hook() {

	add_action( 'wpd_before_content', 'wpdai_output_notices' );

}

/**
 *
 *	Calculate the profit of an order by order_id or order object.
 *
 * 	Will return an associative array with detailed calculations for this order.
 * 	By default, will try fetch the values from our calculation cache, unless the $update_values parameter is set to true, which will refresh the cache.
 *	
 *	@param int|WC_order 	$order_id / $order			The order_id or order object
 *	@param bool 			$update_values 				(Default False) True to fore a recalculation and save the values to database
 *
 *  @since 1.0.0
 *  @version 4.4.20
 * 	@return array|bool Wil return an associative array for of the calculation values saved for this order or false on failure
 *
 */
function wpdai_calculate_cost_profit_by_order( $order_id_or_object = null, $update_values = false ) {

	// Prepare Object
	$order_calculator = new WPD_Order_Calculator( $order_id_or_object, $update_values );

	// Return Results
	return $order_calculator->get_results();

}

/**
 *
 *	Get product cost price by product ID
 *  This function will fetch the product cost price from the database, if not found it will apply some default logic
 *  Bundles will be set to 0, as the cost price will be handled by the children that make this bundle
 *  
 *  Calculation Cost Price Hierarchy: 
 *  1. Object Cache for this product ID (_wpd_ai_product_cost_price) -> Means this function has already ran in this request, so we can return the value immediately
 *  2. Post Meta for this product ID (_wpd_ai_product_cost)
 *  3. WC Native COGS meta: _cogs_total_value -> WooCommerce 10.0+
 *  4. If a variation, will check parent product for cost price (if a variation): _wpd_ai_product_cost & _cogs_total_value
 *  5. Default Cost Price (General Settings) e.g. 30% of RRP
 * 
 *  Can be filtered using (float) apply_filters( 'wpd_ai_cost_price_per_unit', $cost_price_per_unit, $product_id );
 * 
 *  @param int $product_id The product ID
 *  @return float|false The cost price as a float or false if no cost price is found
 *  @since 1.0.0
 *  @version 5.0.0
 *
 */
function wpdai_get_cost_price_by_product_id( $product_id ) {

	// Safety Check
	if ( ! is_numeric($product_id) ) return false;

	/**
	 *  Check the object cache for a value
	 *  This function has already ran in this request, so we can return the value immediately
	 */ 
	$result = wp_cache_get( $product_id, '_wpd_ai_product_cost_price' );
	if ( is_numeric( $result ) ) return (float) $result;

	/**
	 *  If not found, check the post meta for a value
	 *  This is our primary cost price
	 */
	$cost_price_per_unit = get_post_meta( $product_id, '_wpd_ai_product_cost', true );
	if ( is_numeric( $cost_price_per_unit ) ) $cost_price_per_unit = wpdai_float( $cost_price_per_unit );

	/**
	 *	If cost price doesnt exist, use our default checking function
	 * 	Default cost price will check the following hierarchy
	 * 	1. WC Native COGS meta: _cogs_total_value
	 * 	2. Parent Variable Product meta (if a variation): _wpd_ai_product_cost & _cogs_total_value
	 * 	3. Default Cost Price (General Settings)
	 */
	if ( ! is_numeric($cost_price_per_unit)  ) {
		$cost_price_per_unit = wpdai_get_default_cost_price_by_product_id( $product_id ); // This will check WC and then our general settings
	}

	/**
	 *	Remove cost price from WPC Bundles Parent (WPClever)
	 * 	Costs will be handled by the children that make this bundle
	 */
	if ( function_exists( 'woosb_init' ) ) {
		$product = wc_get_product( $product_id );
		if ( is_callable(array($product, 'get_type')) && $product->get_type() === 'woosb' ) {
			$cost_price_per_unit = 0;
		}
	}

	/**
	 *	Remove cost price from WC Bundle parent (WooCommerce)
	 * 	Costs will be handled by the children that make this bundle
	 */
	if ( class_exists('WC_Bundles') ) {
		$product = wc_get_product( $product_id );
		if ( is_callable(array($product, 'get_type')) && $product->get_type() === 'bundle' ) {
			$cost_price_per_unit = 0;
		}
	}

	/**
	 *  Allow filtering the cost price
	 *  This is useful for customizing the cost price for a product
	 */
	$cost_price_per_unit = (float) apply_filters( 'wpd_ai_cost_price_per_unit', $cost_price_per_unit, $product_id );

	// Save to cache
	wp_cache_set( $product_id, $cost_price_per_unit, '_wpd_ai_product_cost_price' );

	// Return the result
	return (float) $cost_price_per_unit;

}


/**
 * 
 * 	Gets the usable default cost price by product / variation
 *  Will check the following hierarchy:
 * 	1. WC Native COGS meta: _cogs_total_value
 * 	2. Parent Variable Product meta: _wpd_ai_product_cost & _cogs_total_value
 * 	3. Default Cost Price (General Settings) e.g. 30% of RRP
 *  
 *  @param int $product_id The product ID
 *  @return float The default cost price, will revert to 0 by default
 *  @since 1.0.0
 *  @version 5.0.0
 *
 **/
function wpdai_get_default_cost_price_by_product_id( $product_id ) {

	$cost_price_per_unit = 0;
	$support_native_cogs = apply_filters( 'wpd_ai_cost_price_support_woocommerce_native_cogs', true );

	/**
	 *  Check the WC native cost of goods (WooCommerce Cost of Goods)
	 *  This is the preferred method of getting the cost price
	 */
	$wc_native_cost_price_per_unit = get_post_meta( $product_id, '_cogs_total_value', true ); // WooCommerce 10.0+ (Native COGS)
	if ( is_numeric($wc_native_cost_price_per_unit) && $support_native_cogs ) {
		$cost_price_per_unit = wpdai_float( $wc_native_cost_price_per_unit );
		return $cost_price_per_unit;
	}

	/**
	 *	If variation doesnt have a cost price set, lets check the parent
	 *  This is the fallback method of getting the cost price
	 */
	if ( get_post_type( $product_id ) == 'product_variation' ) {
		// Get parent product ID
		$parent_id 			 = wp_get_post_parent_id( $product_id );
		// Get parent product cost price
		$cost_price_per_unit = get_post_meta( $parent_id, '_wpd_ai_product_cost', true );
		if ( is_numeric( $cost_price_per_unit ) ) {
			$cost_price_per_unit = wpdai_float( $cost_price_per_unit );
			return $cost_price_per_unit;
		}
		// Also check the WC native cost of goods (WooCommerce Cost of Goods)
		$wc_native_cost_price_per_unit = get_post_meta( $parent_id, '_cogs_total_value', true ); // WooCommerce 10.0+ (Native COGS)
		if ( is_numeric( $wc_native_cost_price_per_unit ) && $support_native_cogs ) {
			$cost_price_per_unit = wpdai_float( $wc_native_cost_price_per_unit );
			return $cost_price_per_unit;
		}
	}

	/**
	 *  If cost price is still empty, use our default setting
	 *  This is the fallback method of getting the cost price
	 */
	$cost_price_per_unit = wpdai_get_default_cost_price_from_general_settings( $product_id );
	if ( is_numeric( $cost_price_per_unit ) ) {
		return $cost_price_per_unit;
	}

	// If cost price is still empty, return 0
	return 0;

}


/**
 *
 *	Get default cost price by product ID only using the default fallback values set in general settings, e.g. 30% of RRP
 *  Will return 0 if no default cost price is set in general settings or if the product price is not set
 * 
 *  @param int $product_id The product ID
 *  @return float The default cost price
 *  @since 1.0.0
 *  @version 5.0.0
 *
 */
function wpdai_get_default_cost_price_from_general_settings( $product_id ) {

    $default_cost_prices = get_option( 'wpd_ai_cost_defaults', [] );
    $default_percent = isset( $default_cost_prices['default_product_cost_percent'] ) ? (float) $default_cost_prices['default_product_cost_percent'] : 0;

    if ( $default_percent <= 0 ) {
        return 0;
    }

    // Try to get the regular price from post meta
    $price = get_post_meta( $product_id, '_regular_price', true );

    if ( ! $price ) {
        // If not found, load the WC_Product object
        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            return 0;
        }

        // Determine price based on product type
        if ( $product instanceof WC_Product_Variable ) {
            $price = (float) $product->get_variation_regular_price( 'max', true );
        } else {
            $price = (float) $product->get_regular_price();
        }

        // If still no price, return 0
        if ( ! $price ) {
            return 0;
        }
    }

    // Calculate default cost
    return $price * ( $default_percent / 100 );
}

/**
 *
 *	Checks if this is an Alpha Insights page
 *  Will return true if the current page is an Alpha Insights page, false otherwise
 * 
 *  @return bool True if the current page is an Alpha Insights page, false otherwise
 *  @since 1.0.0
 *  @version 5.0.0
 *
 */
function is_wpdai_page() {

	// No public pages
	if ( ! is_admin() ) return false;

	$screen 			= get_current_screen();
	
	// Safety check: get_current_screen() can return null
	if ( ! is_object($screen) ) return false;
	
	$page 				= ( isset($_GET['page']) ) ? sanitize_text_field( $_GET['page'] ) : null;
	$post_type 			= ( isset($_GET['post_type']) ) ? sanitize_text_field( $_GET['post_type'] ) : null;
	$taxonomy 			= ( isset($_GET['taxonomy']) ) ? sanitize_text_field( $_GET['taxonomy'] ) : null;
	$screen_post_type 	= ( property_exists($screen, 'post_type') ) ? $screen->post_type : null;
	$bool 				= false;

	if ( 
			( isset($screen->parent_base) && $screen->parent_base == 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) 
			|| $page == 'alpha-insights-sales-report-builder-analytics-for-woocommerce' 
			|| $page == 'wpd-expense-reports' 
			|| $post_type == 'expense'
			|| $post_type == 'facebook_campaign'
			|| $post_type == 'google_ad_campaign'				
			|| $screen_post_type == 'expense'
			|| $screen_post_type == 'facebook_campaign'
			|| $screen_post_type == 'google_ad_campaign'
			|| $taxonomy == 'expense_category'
			|| $taxonomy == 'suppliers'
			|| $page == WPD_Admin_Menu::$sales_report_slug
			|| $page == WPD_Admin_Menu::$website_traffic_slug
			|| $page == WPD_Admin_Menu::$profit_loss_statement_slug
			|| $page == WPD_Admin_Menu::$manage_expenses_slug
			|| $page == WPD_Admin_Menu::$advertising_slug
			|| $page == WPD_Admin_Menu::$cost_of_goods_slug
			|| $page == WPD_Admin_Menu::$settings_slug
			|| $page == WPD_Admin_Menu::$about_help_slug
			|| $page == WPD_Admin_Menu::$getting_started_slug
		) {

		$bool = true;

	}

	return apply_filters( 'wpd_ai_is_wpd_page', $bool, $screen, $page, $post_type, $taxonomy, $screen_post_type );

}

/**
 *
 *	Get Admin Menu Item URL
 *
 */
function wpdai_admin_page_url( $target ) {

	if ( $target === 'inventory-management' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$cost_of_goods_slug;

	} elseif( $target === 'settings' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$settings_slug;

	} elseif( $target === 'settings-emails' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$settings_slug . '&subpage=email';

	} elseif( $target === 'settings-emails-preview-profit-report' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$settings_slug . '&subpage=email&email_preview=profit-report';

	} elseif( $target === 'settings-emails-preview-expense-report' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$settings_slug . '&subpage=email&email_preview=expense-report';

	} elseif( $target === 'settings-emails-preview-inventory-report' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$settings_slug . '&subpage=email&email_preview=inventory-report';

	} elseif( $target === 'settings' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$settings_slug;

	} elseif( $target === 'settings-bulk-import' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$cost_of_goods_slug;

	} elseif( $target === 'settings-product-cogs' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$cost_of_goods_slug;

	} elseif( $target === 'settings-license' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$settings_slug . '&subpage=license';

	} elseif( $target === 'reports' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$sales_report_slug;

	} elseif( $target === 'reports-orders' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$sales_report_slug;

	} elseif( $target === 'reports-products' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$sales_report_slug . '&subpage=products';

	} elseif( $target === 'reports-customers' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$sales_report_slug . '&subpage=customers';

	} elseif( $target === 'reports-expenses' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$expense_reports_slug;

	} elseif( $target === 'pl-statement' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$profit_loss_statement_slug;

	} elseif( $target === 'facebook-report' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$sales_report_slug . '&subpage=facebook';

	} elseif( $target === 'google-report' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$sales_report_slug . '&subpage=google-ads';

	} elseif( $target === 'add-expense-type' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$manage_expenses_slug . '&subpage=manage-expense-taxonomies';

	} elseif( $target === 'manage-suppliers' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$manage_expenses_slug . '&subpage=manage-expense-taxonomies';

	} elseif( $target === 'cost-of-goods-manager' ) {

		return admin_url( 'admin.php') . '?page=' . WPD_Admin_Menu::$cost_of_goods_slug;

	} else {

		return '#';

	}

}

/**
 *
 *	Get traffic source type
 *
 */
function wpdai_get_traffic_type( $url, $query_params = array() ) {

	$traffic_class = new WPD_Traffic_Type( $url, $query_params );
	$traffic_source_type = $traffic_class->determine_traffic_source();

	return $traffic_source_type;

}

/**
 *
 *	Margin calculation
 *
 */
function wpdai_calculate_percentage( $original, $total, $round = 2 ) {

	// Definitely going to be 0 if any value is 0
	if ( ! $original || ! $total ) {

		return 0;

	}

	if ( ! $round ) {

		$result = ($original / $total) * 100;

	} else {

		$result = round( ($original / $total) * 100, $round);

	}

	// Converted two negatives into a negative
	if ( $original < 0 && $total < 0  ) {
		$result = $result * -1;
	}

	if ( $result < 0 ) {

		$result = 0;

	}

	return $result;

}

/**
 *
 *	Divide calculation (prevents NaN)
 *
 */
function wpdai_divide( $n1, $n2, $round = false ) {

	if ( ! $n1 || ! $n2 ) {

		return 0;

	} else {

		if ( $round && is_numeric($round) ) {

			return round( $n1 / $n2, $round );

		} else {

			if ( $n1 == 0 || $n2 == 0 ) {

				return 0;

			}

			return $n1 / $n2;

		}

	}

}

/**
 *
 *	Calculate margin
 *
 */
function wpdai_calculate_margin( $num_profit, $num_total, $negative_margin = true ) {

	$result = 0;
	$result = (float) round( wpdai_divide($num_profit, $num_total) * 100, 2 );

	if ( $negative_margin === false && $result < 0 ) {

		$result = 0;

	}

	return $result;

}

/**
 *
 *	Sort multi level associative array
 *	
 *	@param $array (array) The array
 *	@param $key (string) 'Key to sort by'
 *
 */
function wpdai_sort_multi_level_array( $array, $key, $desc = true ) {

	if ( ! is_array($array) ) {

		return $array;
	
	}

	( $desc === true ) ? $order = SORT_DESC : $order = SORT_ASC;
	
	try {

		array_multisort( array_column( $array, $key ), $order, $array );

	} catch( Error $e ) {

		return $array;

	}

	return $array;

}

/**
 *
 *	Parse User Agent
 *
 */
function wpdai_parse_user_agent( $user_agent ) {

	$ua_parser 	= new WPD_User_Agent( $user_agent );
	if ( ! is_object($ua_parser) ) return false;
	$results 	= $ua_parser->getInfo();
	return $results;

}

/**
 *
 *	Check if this option is selected and return html
 *
 */
function wpdai_selected_option( $current_option, $current_value ) {

	if ( $current_option == $current_value ) {

		return 'selected="selected"';

	} else {

		return '';

	}

}

/**
 *
 *	Preloaer
 *
 */
function wpdai_preloader( $width = 30, $visible = true, $return = false ) {

	$style = 'width: ' . $width . 'px;';
	$style .= 'height: ' . $width . 'px;';
	
	if ( ! $visible ) {

		$style .= 'display:none;';

	}

	$result = '<div class="wpd-preloader" style="' . $style . '"><img src="' . WPD_AI_URL_PATH . '/assets/img/wpd-preloader.svg"></div>';

	if ( $return ) {
		return $result;
	} else {
		echo wp_kses_post( $result );
	}

}

/**
 *
 *	Success
 *
 */
function wpdai_success( $width = 30, $visible =  true, $return = false ) {

	$style = 'width: ' . $width . 'px;';
	$style .= 'height: ' . $width . 'px;';

	if ( ! $visible ) {

		$style .= 'display:none;';

	}

	$result = '<div class="wpd-success" style="' . $style . '"><span class="dashicons dashicons-yes" style="line-height: ' . $width . 'px; font-size: ' . $width / 2 . 'px;"></span></div>';

	if ( $return ) {
		return $result;
	} else {
		echo wp_kses_post( $result );
	}

}

/**
 *
 *	Failure
 *
 */
function wpdai_failure( $width = 30, $visible =  true, $return = false  ) {

	$style = 'width: ' . $width . 'px;';
	$style .= 'height: ' . $width . 'px;';

	if ( ! $visible ) {

		$style .= 'display:none;';

	}

	$result = '<div class="wpd-failure" style="' . $style . '"><span class="dashicons dashicons-no" style="line-height: ' . $width . 'px; font-size: ' . $width / 2 . 'px;"></span></div>';

	if ( $return ) {
		return $result;
	} else {
		echo wp_kses_post( $result );
	}

}

/**
 *
 *	Return an array of paid order status keys as defined by the user
 *	Defaults to wc-completed & wc-processing, wc-refunded is forced to deal with order reporting
 *
 *	@return array $statuses An array of status keys to be used in profit calculations
 *
 */
function wpdai_paid_order_statuses() {

	// User settings
	$status = get_option( 'wpd_ai_order_status' );
	
	// Defaults
	if ( ! $status || empty($status) || ! is_array($status) ) {
		
		$status = array( 'wc-completed', 'wc-processing', 'wc-refunded' );
		
	}
	
	// Force the refund status into the array for order calculations
	if ( ! in_array('wc-refunded', $status) ) {

		array_push( $status, 'wc-refunded' );

	}
	
	return $status;

}


/**
 *
 *	Function to register a WP Davies Notice
 *
 */
function wpdai_notice( $string ) {

	$_POST['wpd-notice'][] = $string;

}


/**
 *
 *	Loop & Output notices
 *
 */
function wpdai_output_notices() {

	if ( isset( $_POST['wpd-notice'] ) && ! empty( $_POST['wpd-notice'] ) ) {

		if ( is_array($_POST['wpd-notice']) ) {

			foreach( $_POST['wpd-notice'] as $message ) {

				wpdai_admin_notice( sanitize_text_field( $message ) );

			}

		} else {

			wpdai_admin_notice( sanitize_text_field( $_POST['wpd-notice'] ) );

		}

	}

}

/**
 *
 *	Output admin notice as function
 *	To be hooked onto @hook wpd_before_content
 *
 *	@param string The message to display
 *	@param string Message type -> success(blue), error(red), warning(amber)
 *
 */
function wpdai_admin_notice( $string, $status = 'success' ) {

	echo '<div class="wpd-notice notice notice-' . esc_attr( $status ) . ' is-dismissible"><p>' . esc_html( $string ) . '</p></div>';

}

/**
 *
 *	Checkbox
 *
 */
function wpdai_checkbox( $name, $value = null, $label = null ) {

	if ( $value == true || $value == 1 ) {
		$checked = 'checked="checked"';
	} else {
		$checked = null;
	}

	?>
		<div class="wpd-checkbox-container">
			<label for="<?php echo esc_attr( $name ); ?>" class="wpd-checkbox-label">
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" id="<?php echo esc_attr( $name ); ?>" class="wpd-input wpd-checkbox" <?php echo esc_attr( $checked ); ?>>
				<span class="checkbox-custom rectangular"></span>
			</label>
			<span class="wpd-checkbox-text"><?php echo esc_html( $label ); ?></span>
		</div>
	<?php

}

/**
 *
 *	Write log
 *
 */
function wpdai_write_log( $data, $log = 'default' ) {

	if ( $log === 'webhook' ) {

		$filepath 	= WPD_AI_UPLOADS_FOLDER_SYSTEM . 'log/wpd_webhook_log.txt';

	} elseif ( $log === 'email' ) {

		$filepath 	= WPD_AI_UPLOADS_FOLDER_SYSTEM . 'log/wpdai_email_log.txt';

	} elseif ( $log === 'default' ) {

		$filepath 	= WPD_AI_UPLOADS_FOLDER_SYSTEM . 'log/wpd_general_log.txt';

	} elseif ( $log === 'facebook' ) {

		$filepath 	= WPD_AI_UPLOADS_FOLDER_SYSTEM . 'log/wpd_facebook_log.txt';

	} else {

		$filepath 	= WPD_AI_UPLOADS_FOLDER_SYSTEM . 'log/wpd_' . $log . '_log.txt';

	}

	$time_stamp = current_time('Y-m-d h:i:sa') . ': ';

	if ( is_array( $data ) || is_object( $data ) ) {

		file_put_contents( $filepath, $time_stamp . print_r( $data, true ), FILE_APPEND );

	} else {

		file_put_contents( $filepath, $time_stamp . trim( $data ) . PHP_EOL, FILE_APPEND );

	}

}

/**
 *
 *	Status circle
 *	@return string HTML status circle
 * 	@var $status = success, error, neutral
 */
function wpdai_status_circle($status = "neutral") {

	$color = '#eaeaea'; // grey

	if ( $status == 'success' ) {
		$color = '#6ae56a'; // green
	}

	if ( $status == 'error' ) {
		$color = 'red'; // red
	}

	$html = ' <span class="wpd-status-circle" style="width: 10px; height: 10px; border-radius: 100px; background-color: ' . $color . '; display:inline-block;"></span>';

	return $html;
}

/**
 *
 *	Collect list of available roles
 *
 */
function wpdai_get_available_store_roles( $return_keys = true ) {

	global $wp_roles;

	$roles = $wp_roles->roles;

	if ( $return_keys ) {
		return array_keys($roles);
	}

	return $roles;

}

/**
 * 
 *	Get Product COGS range for products with children
 *	@param int|WC_Product $product Accepts a WC_Product object or the product/post_id
 *	@return false|array Returns false if we couldnt find a product | return range with ['min'] and ['max']
 *	in array format regardles of whether it's a simple product or a variable/grouped product.
 * 
 **/
function wpdai_get_min_max_product_cogs_range( $product ) {

	// If product_id has been passed lets get an object
	if ( is_numeric($product) ) {
		$product = wc_get_product( $product );
	}

	// Safety check
	if ( ! is_a($product, 'WC_Product') ) return false;

	// If it has children, return a range
	if ( $product->has_child() ) {

		$variation_ids = $product->get_children();

		if ( ! empty($variation_ids) && is_array($variation_ids) ) {
			
			$cost_price = array();

			// Variations
			foreach( $variation_ids as $product_id ) {
				$cost_price[] = wpdai_get_cost_price_by_product_id( $product_id );
			}
	
			$min_cost_price = min( $cost_price );
			$max_cost_price = max( $cost_price );
	
			return array(
				'min' => $min_cost_price,
				'max' => $max_cost_price
			);
	
		} else {

			$cost_price = wpdai_get_cost_price_by_product_id( $product->get_id() );

			return array(
				'min' => $cost_price,
				'max' => $cost_price
			);

		}

	} else {

		// Not a variable product
		return false;

	}

}

/**
 * 
 *	Get Product Margin range for products with children
 *	@param int|WC_Product $product Accepts a WC_Product object or the product/post_id
 *	@return false|array Returns false if we couldnt find a product | return range with ['min'] and ['max']
 *	in array format regardles of whether it's a simple product or a variable/grouped product.
 * 
 **/
function wpdai_get_min_max_product_margin_range( $product ) {

	// If product_id has been passed lets get an object
	if ( is_numeric($product) && $product > 0  ) {
		$product = wc_get_product( $product );
	}

	// Safety check
	if ( ! is_a($product, 'WC_Product') ) return false;

	// If it has children, return a range
	if ( $product->has_child() ) {

		$variation_ids = $product->get_children();

		if ( ! empty($variation_ids) && is_array($variation_ids) ) {
			
			$margin_array = array();
			$cost_price = array();
			$rrp_price = array();

			// Variations
			foreach( $variation_ids as $product_id ) {

				$cost_price = wpdai_get_cost_price_by_product_id( $product_id );
				
				// Reload variation product
				$product = wc_get_product( $product_id );
				if ( ! is_a($product, 'WC_Product') ) return false; // Cant continue if we cant find a product?

				$rrp_price = (float) $product->get_regular_price();

				// Create array of margin calculations
				$profit = $rrp_price - $cost_price;
				$margin = wpdai_calculate_margin( $profit, $rrp_price );
				$margin_array[] = $margin;

			}
	
			$min_margin = min( $margin_array );
			$max_margin = max( $margin_array );
			
			return array(
				'min' => $min_margin,
				'max' => $max_margin
			);

		} else {

			$margin = wpdai_calculate_margin_by_product( $product );

			return array(
				'min' => $margin,
				'max' => $margin
			);

		}

	} else {

		// Not a variable product
		return false;

	}

}

/**
 * 
 *	Calculate product margin as a % returned as a float by product ID or object. 
 *	Will only do calculation on products that have no children, otherwise will return false.
 *
 *	@param int|WC_Product $product Accepts a WC_Product object or the product/post_id
 * 	@return float Returns the margin percentage as a float or false if we couldnt find a 
 * 	product object or the product has a child
 * 
 **/
function wpdai_calculate_margin_by_product( $product ) {

	// If product_id has been passed lets get an object
	if ( is_numeric($product) && $product > 0 ) {
		$product = wc_get_product( $product );
	}

	// Safety check
	if ( ! is_a($product, 'WC_Product') ) return false;

	if ( $product->has_child() ) {

		return false;

	} else {

		$rrp 				= (float) $product->get_regular_price();
		$cogs 				= (float) wpdai_get_cost_price_by_product_id( $product->get_id() );
		$profit 			= $rrp - $cogs;
		$margin_percentage 	= wpdai_calculate_margin( $profit, $rrp );
	
		return (float) $margin_percentage;

	}

}

/**
 * 
 * 	Checks for truthy / falsey statements and returns a bool
 * 	Mostly checks for string or integer representations of a bool
 * 
 * 	@param string|int|bool Variable to check
 * 	@param bool Default return if we can't work it out
 * 
 **/
function wpdai_convert_to_bool( $bool_check, bool $default ) {

	// Already boolean, return that
	if ( is_bool($bool_check) ) {
		return $bool_check;
	}

	// Check for truthy's
	if ( $bool_check === 'true' || $bool_check === 1 || $bool_check === '1' ) {
		return true;
	}

	// Check for falseys
	if ( $bool_check === 'false' || $bool_check === 0 || $bool_check === '0' ) {
		return false;
	}

	// Couldnt make an accurate guess, return the default
	return $default;

}

/**
 * 
 * 	Returns an associative array of countries as provided by WooCommerce
 * 
 * 	@return array List of countries with the country code as the key & the country name as the value
 * 
 **/
function wpdai_get_list_of_available_countries() {

	global $woocommerce;
	$countries_obj  = new WC_Countries();
	$countries   	= $countries_obj->__get('countries');

	return $countries;

}

/**
 * 
 * 	Gets data for an attachment by ID
 * 	Will return an associative array including thumbnail_url, file_url, file_name & meta_data containing size information
 * 
 * 	@return array|false Associative array or false if a non-numeric value was passed into this function
 * 
 **/
function wpdai_get_attachment_data_by_id( $attachment_id ) {

	// Safety Check
	if ( ! is_numeric($attachment_id) ) {
		return false;
	}

	// Double check thumbnail
	$thumbnail_url = wp_get_attachment_image_url( $attachment_id );
	if ( ! $thumbnail_url ) {
		$thumbnail_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail', true );
	}
	
	// Collect results
	$results = array(
		'attachment_id' => $attachment_id,
		'thumbnail_url' => $thumbnail_url,
		'file_url' => wp_get_attachment_url($attachment_id),
		'file_name' => basename( get_attached_file($attachment_id) ),
		'meta_data' => wp_get_attachment_metadata( $attachment_id )
	);

	// Return
	return $results;

}

/**
 * 
 * 	Sends an API call to the woocommerce API endpoint
 * 	@todo create a direct DB call, no need to run through API.
 * 
 *  @var $data['session_id']        PHP Session ID                      	Default  '' ##Session data - calculated automatically
 *  @var $data['ip_address']        IP Address                          	Default  0 	##Session data - calculated automatically
 *  @var $data['date_created_gmt']  Date Event Created In GMT Time      	Default: current_time('mysql') ##Only set if not current time
 *  @var $data['user_id']           User ID                             	Default  0
 *  @var $data['page_href']         Current Page Url                    	Default ''
 *  @var $data['object_type']       Custom Post Type Name               	Default ''
 *  @var $data['object_id']         Wordpress Object ID                 	Default 0
 *  @var $data['event_type']        category_page_click | product_page_view | add_to_cart | purchase | refund | add_to_wishlist | anything else...
 *  @var $data['event_quantity']    Event Quantity                      	Default 1
 *  @var $data['event_value']       Event Value                         	Default 0.00
 *  @var $data['product_id']        Product ID                          	Default  0
 *  @var $data['variation_id']      Product ID                          	Default  0
 *  @var $data['additional_data']   Array Any additional data, stored in JSON 	Default: NULL
 * 
 **/
function wpdai_send_woocommerce_event( $data ) {

	// Call event tracking class
	$result = WPD_WooCommerce_Events::get_instance()->insert_event( $data );

	// return results
	return $result;

}


/**
 * 
 * 	Returns a list of available payment gateways with id, title, description and enabled status
 *  Only returns gateways that are enabled
 * 
 * 	@return array List of payment gateways with id, title, description and enabled status
 * 
 **/
function wpdai_get_available_payment_gateways() {

	// Get payment gateways
	$payment_gateways = WC()->payment_gateways->payment_gateways();

	// Collect results
	$gateways_data = array(
		'default' => array(
			'id' => 'default',
			'title' => 'Default',
			'description' => 'Default payment gateway cost',
			'enabled' => true,
		),
	);

	// Loop through payment gateways
	if ( ! empty($payment_gateways) && is_array($payment_gateways) ) {

		foreach ( $payment_gateways as $gateway_id => $gateway ) {

			// Safety check we are actually using a WC_Payment_Gateway object
			if ( ! is_a($gateway, 'WC_Payment_Gateway') ) {
				continue;
			}

			if ( ! $gateway->enabled || $gateway->enabled === 'no' ) {
				continue;
			}

			$gateways_data[$gateway->id] = array(
				'id'    => $gateway->id,
				'title' => $gateway->get_method_title(),
				'description' => $gateway->get_method_description(),
				'enabled' => $gateway->enabled,
			);
		}
	}

	return $gateways_data;

}


/**
 * 
 * 	Checks if the current user is authorized to view Alpha Insights
 * 
 * 	@return bool
 * 
 * 	@since 5.0.0
 * 	@version 5.0.0
 * 	@author WPDavies
 * 	@link https://wpdavies.dev/
 * 
 **/
function wpdai_is_user_authorized_to_view_alpha_insights() {
	
	// Get authorized roles from settings (should return an array of role slugs)
	$authorized_roles = (array) wpdai_get_authorized_user_roles_settings();
	
	// Get current user
	$current_user = wp_get_current_user();
	
	// Ensure we have a valid user
	if ( ! $current_user || empty( $current_user->roles ) ) {
		return false;
	}

	// Check if at least one of the user's roles is authorized
	foreach ( $current_user->roles as $role ) {
		if ( in_array( $role, $authorized_roles, true ) ) {
			return true;
		}
	}

	// Default to false
	return false;

}