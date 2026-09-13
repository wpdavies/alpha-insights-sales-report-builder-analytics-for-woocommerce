<?php
/**
 *
 * Handle Scripts and Styles
 *
 * @package Alpha Insights
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 *
 *	Load admin css stylesheet
 *	Testing the dev branch
 *
 **/
add_action('admin_enqueue_scripts', 'wpdai_admin_enqueue');
function wpdai_admin_enqueue() {

	/**
	 *
	 *	Register Styles
	 *
	 */
	wp_register_style( 'wpd-alpha-insights-admin', plugins_url( 'assets/css/wpd-alpha-insights-admin.css', dirname(__FILE__)), array(), WPD_AI_VER );
	wp_register_style( 'wpd-alpha-insights-wordpress-admin', plugins_url( 'assets/css/wpd-alpha-insights-wordpress-admin.css', dirname(__FILE__)), array(), WPD_AI_VER );
	wp_register_style( 'wpd-core-style-override', plugins_url( 'assets/css/wpd-style-override-admin.css', dirname(__FILE__)), array(), WPD_AI_VER );
	wp_register_style( 'wpd-jquery-ui', plugins_url( 'assets/css/jquery-ui.css' , dirname(__FILE__)) );
	wp_register_style( 'wpd-fonts', 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');
	wp_register_style( 'wpd-easy-select', WPD_AI_URL_PATH . 'assets/css/js-easy-select-style.css' );
	wp_register_style( 'wpd-settings-about-us', WPD_AI_URL_PATH . 'assets/css/wpd-settings-about-us.css', array( 'wpd-alpha-insights-admin' ), WPD_AI_VER );
	wp_register_style( 'wpd-settings-responsive', WPD_AI_URL_PATH . 'assets/css/wpd-settings-responsive.css', array( 'wpd-alpha-insights-admin' ), WPD_AI_VER );

	/**
	 *
	 *	Register Scripts
	 *
	 */
	wp_register_script( 'wpd-alpha-insights-admin', WPD_AI_URL_PATH . 'assets/js/wpd-alpha-insights-admin.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-datepicker', 'jquery-ui-dialog' ), WPD_AI_VER );
	wp_register_script( 'wpd-alpha-insights-wordpress-admin', WPD_AI_URL_PATH . 'assets/js/wpd-alpha-insights-wordpress-admin.js', array( 'jquery' ), WPD_AI_VER, true );
	wp_register_script( 'wpd-submenu-scroll', WPD_AI_URL_PATH . 'assets/js/wpd-submenu-scroll.js', array( 'jquery' ), WPD_AI_VER, true );
	wp_register_script( 'wpd-mobile-nav', WPD_AI_URL_PATH . 'assets/js/wpd-mobile-nav.js', array( 'jquery' ), WPD_AI_VER, true );
	wp_register_script( 'wpd-easy-select', WPD_AI_URL_PATH . 'assets/js/js-easy-select.js', array( 'jquery' ), false, true ); // 2.9.xx
	wp_register_script( 'wpd-data-manager', WPD_AI_URL_PATH . 'assets/js/wpd-data-manager.js', array( 'jquery', 'wpd-alpha-insights-admin' ), WPD_AI_VER, true );
	wp_register_script( 'wpd-integrations-filter', WPD_AI_URL_PATH . 'assets/js/wpd-integrations-filter.js', array( 'jquery' ), WPD_AI_VER, true );

	/**
	 *
	 *	Add vars if I need them
	 *
	 */
	// Localize the script with new data
	$wpd_ai_vars = array(
	    'processing' 			=> wpdai_preloader( 40, true, true ),
	    'success' 				=> wpdai_success( 40, true, true ),
	    'failure' 				=> wpdai_failure( 40, true, true ),
		'ajax_url' 				=> admin_url('admin-ajax.php'),
		'site_creation_date' 	=> wpdai_get_site_creation_date(),
		'nonce' 				=> wp_create_nonce( WPD_AI_AJAX_NONCE_ACTION ),
		'strings' 				=> array(
			'processing' 		=> __( 'Processing...', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'working' 			=> __( 'We are working on it!', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'success' 			=> __( 'Your request has been successfully completed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'error' 			=> __( 'Your action could not be completed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'requestFailed' 	=> __( 'Request Failed', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'invalidKey' 		=> __( 'Hm, Something Is Not Quite Right', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'keyNotFound' 		=> __( 'We couldnt locate the custom order cost key.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'emailSuccess' 		=> __( 'Your email has been successfully sent.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'emailError' 		=> __( 'Your email was not sent.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'emailFailed' 		=> __( 'Email Failed', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'confirmDeleteAllLogs' => __( 'Delete all log files? This cannot be undone.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'logsDeletedEmpty' 	=> __( 'All log files have been deleted.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'logsHeaderEmpty' 	=> __( 'WP Davies Logs (0)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		)
	);
	wp_localize_script( 'wpd-alpha-insights-admin', 'wpdAlphaInsights', $wpd_ai_vars );
	
	// Localize WordPress admin script with minimal data needed for global features
	$wpd_ai_wordpress_admin_vars = array(
		'ajax_url' 	=> admin_url('admin-ajax.php'),
		'nonce' 	=> wp_create_nonce( WPD_AI_AJAX_NONCE_ACTION )
	);
	wp_localize_script( 'wpd-alpha-insights-wordpress-admin', 'wpdAlphaInsightsWordPressAdmin', $wpd_ai_wordpress_admin_vars );

	/**
	 *
	 *	Enqueue Styles
	 *
	 */
	// Only load my styles on my pages
	if ( is_wpdai_page() ) {
		wp_enqueue_style( 'wpd-alpha-insights-admin' );
	}

	// Needed on all pages
	wp_enqueue_style( 'wpd-alpha-insights-wordpress-admin' );
	wp_enqueue_style( 'wpd-jquery-ui' );
	wp_enqueue_style( 'wpd-fonts' );
	wp_enqueue_style( 'wpd-easy-select' );

	// Conditional check for override
	if ( get_option('wpd_ai_admin_style_override') == 1 ) {
		wp_enqueue_style( 'wpd-core-style-override' );
	}

	// About Us settings subpage
	if ( is_wpdai_page() && isset( $_GET['page'] ) && isset( $_GET['subpage'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === WPDAI_Admin_Menu::$settings_slug && sanitize_text_field( wp_unslash( $_GET['subpage'] ) ) === 'about-us' ) {
		wp_enqueue_style( 'wpd-settings-about-us' );
	}

	// Responsive layout for all settings subpages
	if ( is_wpdai_page() && isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === WPDAI_Admin_Menu::$settings_slug ) {
		wp_enqueue_style( 'wpd-settings-responsive' );
	}

	/**
	 *
	 *	Enqueue Scripts
	 *
	 */
	wp_enqueue_script( 'jquery-ui-datepicker' );
	wp_enqueue_script( 'jquery-ui-dialog' );
	
	// Always enqueue WordPress admin JS for global menu enhancements
	wp_enqueue_script( 'wpd-alpha-insights-wordpress-admin' );
	
	if ( is_wpdai_page() ) {
		wp_enqueue_script( 'wpd-alpha-insights-admin' );
		wp_enqueue_script( 'wpd-submenu-scroll' );
		wp_enqueue_script( 'wpd-mobile-nav' );
	}

	// Integrations filter (category tabs + search) on integration settings page
	if ( is_wpdai_page() && isset( $_GET['page'] ) && isset( $_GET['subpage'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === WPDAI_Admin_Menu::$settings_slug && sanitize_text_field( wp_unslash( $_GET['subpage'] ) ) === 'integrations' ) {
		wp_enqueue_script( 'wpd-integrations-filter' );
	}

	// Media library for Whitelabel Report Logo on general settings
	$settings_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	$settings_sub  = isset( $_GET['subpage'] ) ? sanitize_text_field( wp_unslash( $_GET['subpage'] ) ) : '';
	if ( is_wpdai_page() && $settings_page === WPDAI_Admin_Menu::$settings_slug && ( $settings_sub === 'general-settings' || $settings_sub === '' ) ) {
		wp_enqueue_media();
	}
	wp_enqueue_script( 'wpd-easy-select' );

	$prevent_notices = get_option( 'wpd_ai_prevent_wp_notices' );

	if ( $prevent_notices ) {
		// Enqueue the WordPress admin style first
		wp_enqueue_style( 'wpd-alpha-insights-wordpress-admin' );
		
		// Add inline style to hide notices
		$hide_notices_css = "
		/* Hide admin notices on my pages */
		.notice, .updated, .update-nag {
		    display: none !important;
		}
		.woocommerce-embed-page .woocommerce-store-alerts {
		    display: none;
		}
		.notice.wpd-notice, .plugin-update .notice {
		    display: block !important;
		}";
		
		wp_add_inline_style( 'wpd-alpha-insights-wordpress-admin', $hide_notices_css );
	}

	/**
	 *
	 *	Add inline style for order dashboard metabox logo
	 *
	 */
	$screen = get_current_screen();
	if ( $screen && class_exists( 'WooCommerce' ) ) {
		// Check if we're on the order edit screen (HPOS or traditional)
		$is_order_edit_screen = false;
		
		// Traditional WooCommerce orders - check post type
		if ( property_exists( $screen, 'post_type' ) && $screen->post_type === 'shop_order' ) {
			$is_order_edit_screen = true;
		}
		
		// HPOS (High-Performance Order Storage) - check screen ID
		if ( ! $is_order_edit_screen && function_exists( 'wc_get_page_screen_id' ) ) {
			$hpos_screen_id = wc_get_page_screen_id( 'shop-order' );
			if ( $hpos_screen_id && $screen->id === $hpos_screen_id ) {
				$is_order_edit_screen = true;
			}
		}
		
		// Fallback: check screen ID for traditional orders
		if ( ! $is_order_edit_screen && $screen->id === 'shop_order' ) {
			$is_order_edit_screen = true;
		}
		
		if ( $is_order_edit_screen ) {
			// Ensure the stylesheet is enqueued
			wp_enqueue_style( 'wpd-alpha-insights-wordpress-admin' );

			wp_register_script(
				'wpd-order-browsing-history',
				WPD_AI_URL_PATH . 'assets/js/wpd-order-browsing-history.js',
				array( 'jquery' ),
				WPD_AI_VER,
				true
			);
			wp_localize_script(
				'wpd-order-browsing-history',
				'wpdAiBrowsingHistory',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( WPD_AI_AJAX_NONCE_ACTION ),
					'i18n'     => array(
						'title'            => __( 'Browsing History', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
						'loading'          => __( 'Loading customer journey…', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
						'empty'            => __( 'No browsing history was found for this customer.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
						'error'            => __( 'Unable to load browsing history. Please try again.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
						'orderPlaced'      => __( 'Order Placed', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
						'landingPage'      => __( 'Landing page', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
						'referral'         => __( 'Referral', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
						'direct'           => __( 'Direct', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
						'pageViews'        => __( 'page views', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
						'noPageViews'      => __( 'No page views recorded for this session.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
						'close'            => __( 'Close', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
						'sessionCount'     => __( 'sessions', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
						'sessionStart'     => __( 'Session start', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
						'sessionEnd'       => __( 'Session end', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					),
				)
			);
			wp_enqueue_script( 'wpd-order-browsing-history' );
			
			// Logo URL
			$wpd_ai_logo = wpdai_get_logo_icon_url();
			
			// Add inline style for dashboard metabox logo
			$dashboard_logo_css = sprintf(
				"
				div#wpd-ai-dashboard-summary .postbox-header h2::before {
					content: '';
					position: absolute;
					background-image: url(%s);
					width: 30px;
					height: 30px;
					background-size: contain;
					background-repeat: no-repeat;
					left: 15px;
				}",
				esc_url( $wpd_ai_logo )
			);
			
			wp_add_inline_style( 'wpd-alpha-insights-wordpress-admin', $dashboard_logo_css );
		}

		/**
		 *
		 *	Add inline style for WooCommerce dashboard widget
		 *
		 */
		if ( $screen->id === 'dashboard' ) {
			// Ensure the stylesheet is enqueued
			wp_enqueue_style( 'wpd-alpha-insights-wordpress-admin' );
			
			// Icon URL
			$wpd_ai_icon = WPD_AI_URL_PATH . 'assets/img/Alpha-Insights-Icon-20x20.png';
			
			// Add inline style for WooCommerce dashboard widget
			$dashboard_widget_css = sprintf(
				"
				#woocommerce_dashboard_status li.wpd-status-widget-item {
					width: 50%% !important;
					clear: none !important;
					float: left !important;
				}
				#woocommerce_dashboard_status .wc_status_list li.gross-profit-this-month {
					border-right: 1px solid #ececec;
				}
				#woocommerce_dashboard_status .wc_status_list li.wpd-status-widget-item a::before {
					content: '';
					background-image: url(%s);
					background-repeat: no-repeat;
					width: 20px;
					background-size: cover;
					height: 20px;
				}",
				esc_url( $wpd_ai_icon )
			);
			
			wp_add_inline_style( 'wpd-alpha-insights-wordpress-admin', $dashboard_widget_css );
		}
	}

}