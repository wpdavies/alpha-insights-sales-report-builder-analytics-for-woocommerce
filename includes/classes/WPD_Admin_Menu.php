<?php
/**
 * WPD_Admin_Menu Class
 * 
 * Handles the registration and output of the Alpha Insights admin menu
 * 
 * @package Alpha Insights
 * @since 1.0.0
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 */
defined( 'ABSPATH' ) || exit;

class WPD_Admin_Menu {

    // Alpha Insights root menu
    public static $top_level_menu_slug          = 'wpd-sales-reports';

    // Alpha Insights submenu items
    public static $sales_report_slug            = 'wpd-sales-reports';
    public static $website_traffic_slug         = 'wpd-website-traffic-reports';
    public static $profit_loss_statement_slug   = 'wpd-profit-loss-statement';
    public static $manage_expenses_slug         = 'wpd-expense-management';
    public static $expense_reports_slug         = 'wpd-expense-reports';
    public static $advertising_slug             = 'wpd-advertising';
    public static $cost_of_goods_slug           = 'wpd-cost-of-goods';
    public static $settings_slug                = 'wpd-settings';
    public static $about_help_slug              = 'wpd-about-help';
    public static $getting_started_slug         = 'wpd-getting-started';

    /**
     * Cache for menu structure to prevent excessive regeneration
     * 
     * @since 4.8.0
     */
    private $menu_cache = null;

    /**
     * Constructor
     * 
     * @since 4.8.0
     */
    public function __construct() {

        // Register Menu and Pages
        add_action( 'admin_menu', array( $this, 'register_admin_menu_and_pages' ) );

        // Output Menu
        add_action( 'admin_notices', array( $this, 'output_alpha_insights_menu' ) );

        // Enqueue admin scripts for third-level menu (late priority to ensure script is enqueued first)
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_menu_scripts' ), 99 );

        // Register AJAX handler for third-level menu
        add_action( 'wp_ajax_wpd_get_all_third_level_menus', array( $this, 'get_all_third_level_menus_ajax' ) );

    }

    /**
     * Register the wordpress admin menu and pages
     * 
     * @since 4.8.0
     */
    public function register_admin_menu_and_pages() {

        // Check if user is authorized to view Alpha Insights
        $capability = wpd_is_user_authorized_to_view_alpha_insights() ? 'read' : 'do_not_allow';
    
        // Top level menu item - Defaults to Sales Reports
        add_menu_page(
            __( 'Sales Reports', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),						// Page Title
            __( 'Alpha Insights', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 					// Menu Title
            $capability, 													// Capability
            self::$top_level_menu_slug, 									// Menu Slug
            'wpd_profit_reports_page_content',								// Callback (page content)
            WPD_AI_URL_PATH . 'assets/img/Alpha-Insights-Icon-20x20.png', 	// Icon URL
            5																// Position (defaults to 5)
        );

        // Child pages
        $child_pages = array(

            // Submenu item - Sales Reports
            array(
                'parent_slug' => self::$top_level_menu_slug,
                'page_title' => __( 'Sales Reports', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                'menu_title' => __( 'Sales Reports', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                'menu_slug' => self::$sales_report_slug,
                'page_callback' => 'wpd_profit_reports_page_content',
                'menu_position' => 10,
            ),

            // Submenu item - Website Traffic
            array(
                'parent_slug' => self::$top_level_menu_slug,
                'page_title' => __( 'Website Traffic', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                'menu_title' => __( 'Website Traffic', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                'menu_slug' => self::$website_traffic_slug,
                'page_callback' => 'wpd_analytics_dashboard',
                'menu_position' => 20,
            ),
        
            // Submenu item - Profit & Loss Statement
            array(
                'parent_slug' => self::$top_level_menu_slug,
                'page_title' => __( 'P&L Statement', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                'menu_title' => __( 'P&L Statement', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                'menu_slug' => self::$profit_loss_statement_slug,
                'page_callback' => 'wpd_pl_statement_page',
                'menu_position' => 30,
            ),
        
            // Submenu item - Cost Of Goods Manager
            array(
                'parent_slug' => self::$top_level_menu_slug,
                'page_title' => __( 'Cost Of Goods', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                'menu_title' => __( 'Cost Of Goods', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                'menu_slug' => self::$cost_of_goods_slug,
                'page_callback' => 'wpd_cost_of_goods_manager_page',
                'menu_position' => 70,
            ),
        
            // Submenu item - Settings
            array(
                'parent_slug' => self::$top_level_menu_slug,
                'page_title' => __( 'Settings', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                'menu_title' => __( 'Settings', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                'menu_slug' => self::$settings_slug,
                'page_callback' => 'wpd_settings_page',
                'menu_position' => 80,
            ),

            // Submenu item - Getting Started
            array(
                'parent_slug' => self::$top_level_menu_slug,
                'page_title' => __( 'Getting Started', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                'menu_title' => __( 'Getting Started', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                'menu_slug' => self::$getting_started_slug,
                'page_callback' => 'wpd_getting_started_page',
                'menu_position' => 90,
            )


        );

        // Children menu items
        $child_pages = apply_filters( 'wpd_ai_child_page_register', $child_pages );

        // Sort child pages by menu_position (lowest first)
        usort( $child_pages, function( $a, $b ) {
            $menu_position_a = isset( $a['menu_position'] ) ? intval( $a['menu_position'] ) : 999;
            $menu_position_b = isset( $b['menu_position'] ) ? intval( $b['menu_position'] ) : 999;
            
            return $menu_position_a - $menu_position_b;
        } );

        // Load all child pages
        foreach( $child_pages as $child_page ) {

            $required_fields = array('page_title', 'menu_title', 'menu_slug', 'page_callback');
            foreach( $required_fields as $field ) {
                if ( ! isset($child_page[$field]) ) {
                    continue;
                }
            }

            add_submenu_page(
                $child_page['parent_slug'] ?? self::$top_level_menu_slug,
                $child_page['page_title'],
                $child_page['menu_title'],
                $child_page['capability'] ?? $capability,
                $child_page['menu_slug'],
                $child_page['page_callback'],
                $child_page['menu_position'] ?? 0,
            );
        }

    }

    /**
     * Enqueue admin menu scripts and localize data
     * 
     * @since 4.8.0
     * 
     * @return void
     */
    public function enqueue_admin_menu_scripts() {

        // Only enqueue on admin pages
        if ( ! is_admin() ) {
            return;
        }

        // Localize script with menu configuration
        wp_localize_script( 'wpd-alpha-insights-wordpress-admin', 'wpdAlphaInsightsMenu', array(
            'topLevelMenuSlug' => self::$top_level_menu_slug,
            'topLevelMenuId'   => 'toplevel_page_' . self::$top_level_menu_slug,
        ));

    }

    /**
     * Output the alpha insights menu
     * @hook admin_notices
     * @return void
     * 
     * @since 4.8.0
     */
    public function output_alpha_insights_menu() {

        // Only on WPD pages
        if ( ! is_wpd_page() ) return false; 

        // Get the alpha insights menu
        $alpha_insights_menu = $this->register_alpha_insights_menu();
        $active_parent_menu_item = $this->get_active_parent_menu_item();
        $active_submenu_item = $this->get_active_submenu_item( $active_parent_menu_item );
        ob_start(); ?>

        <div class="wpd-nav-wrapper">
            <div class="wrap">
                <!-- Branding container -->
                <h3 class="nav-tab-wrapper wpd-nav-tab-wrapper" id="wpd-ai-menu">
                    <span class="wpd-plugin-logo">
                        <img height="50" src="<?php echo esc_url( WPD_AI_URL_PATH ); ?>assets/img/Alpha-Insights-Icon-Large.png" class="alpha-insights-menu-logo">
                        <span class="product-subtitle">Alpha Insights</span>
                    </span>
                    <!-- Menu items container -->
                    <?php foreach( $alpha_insights_menu as $key => $item ) : ?>
                        <span class="wpd-ai-menu-item-container">
                            <!-- Actual Menu Item -->
                            <a class="wpd-nav-tab nav-tab <?php echo esc_attr( implode(' ', $item['additional_classes']) ); ?> <?php echo esc_attr( ( $active_parent_menu_item == $key ) ? 'nav-tab-active' : '' ); ?>" href="<?php echo esc_url( $item['url'] ); ?>"<?php echo ( isset($item['target']) ) ? ' target="' . esc_attr( $item['target'] ) . '"' : ''; ?>><?php echo esc_html( $item['title'] ); ?></a>
                            <!-- Dropdown Menu -->
                            <?php if ( isset($item['children']) && is_array($item['children']) && count($item['children']) > 1 ) : ?>
                                <ul class="wpd-ai-dropdown-submenu">
                                    <?php foreach( $item['children'] as $key => $child ) : ?>
                                        <li class="wpd-drop-down-menu-item <?php echo esc_attr( implode(' ', $child['additional_classes']) ); ?>">
                                            <a href="<?php echo esc_url( $child['url'] ); ?>"><?php echo esc_html( $child['title'] ); ?></a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </h3>
                <!-- Submenu container -->
                <div class="wpd-sub-menu-container">
                    <!-- Gradient fade overlays -->
                    <div class="wpd-sub-menu-fade-left"></div>
                    <div class="wpd-sub-menu-fade-right"></div>
                    <!-- Scroll control buttons -->
                    <button class="wpd-sub-menu-scroll-btn wpd-sub-menu-scroll-left" type="button" aria-label="Scroll left">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15,18 9,12 15,6"></polyline>
                        </svg>
                    </button>
                    <button class="wpd-sub-menu-scroll-btn wpd-sub-menu-scroll-right" type="button" aria-label="Scroll right">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9,18 15,12 9,6"></polyline>
                        </svg>
                    </button>
                    <ul class="wpd-sub-menu">
                        <?php if ( $active_parent_menu_item && isset($alpha_insights_menu[$active_parent_menu_item]['children']) ) : ?>
                            <?php foreach( $alpha_insights_menu[$active_parent_menu_item]['children'] as $key => $item ) : ?>
                                <li class="wpd-sub-menu-li">
                                    <a class="wpd-sub-menu-item <?php echo esc_attr( ( $active_submenu_item == $key ) ? 'nav-tab-active' : '' ); ?> <?php echo esc_attr( implode(' ', $item['additional_classes']) ); ?>" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                    <!-- Custom scrollbar -->
                    <div class="wpd-sub-menu-scrollbar">
                        <div class="wpd-sub-menu-scrollbar-thumb"></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Empty H2 container so that admin notices are pushed here -->
        <div class="wrap"><h2></h2></div>
        <?php $menu_html = ob_get_clean();

        // Filter the menu HTML
        $menu_html = apply_filters( 'wpd_alpha_insights_menu_html', $menu_html );

        // Echo the menu HTML
        echo wp_kses_post( $menu_html );

    }

    /**
     * Register the alpha insights menu
     * 
     * @since 4.8.0
     */
    public function register_alpha_insights_menu() {

        // Return cached menu if available (prevents excessive regeneration)
        if ( $this->menu_cache !== null ) {
            return $this->menu_cache;
        }

        // Empty Payload
        $alpha_insights_menu = array(

            // Sales Reports
            self::$sales_report_slug => array(
                'title' => __( 'Sales Reports', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                'url'   => admin_url( 'admin.php') . '?page=' . self::$sales_report_slug,
                'icon'  => null,
                'additional_classes' => array(),
                'menu_order' => 10,
                'children' => array(
                    'manage_sales_reports' => array(
                        'title' => __( 'Manage Reports', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                        'url'   => admin_url( 'admin.php') . '?page=' . self::$sales_report_slug . '&subpage=manage-reports',
                        'icon'  => null,
                        'additional_classes' => array(),
                    ),
                )
            ),

            // Website Traffic
            self::$website_traffic_slug => array(
                'title' => __( 'Website Traffic', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                'url'   => admin_url( 'admin.php') . '?page=' . self::$website_traffic_slug,
                'icon'  => null,
                'additional_classes' => array(),
                'menu_order' => 20,
                'children' => array(
                    'manage_website_traffic' => array(
                        'title' => __( 'Manage Reports', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                        'url'   => admin_url( 'admin.php') . '?page=' . self::$website_traffic_slug . '&subpage=manage-reports',
                        'icon'  => null,
                        'additional_classes' => array(),
                    ),
                )
            ),

            // Profit & Loss Statement
            self::$profit_loss_statement_slug => array(
                'title' => __( 'P&L Statement', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                'url'   => admin_url( 'admin.php') . '?page=' . self::$profit_loss_statement_slug,
                'icon'  => null,
                'additional_classes' => array(),
                'menu_order' => 30,
                'children' => array(
                    'profit_loss_statement_report' => array(
                        'title' => __( 'Profit & Loss Statement', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                        'url'   => admin_url( 'admin.php') . '?page=' . self::$profit_loss_statement_slug,
                        'icon'  => null,
                        'additional_classes' => array(),
                    ),
                )
            ),

            // Cost Of Goods Manager
            self::$cost_of_goods_slug => array(
                'title' => __( 'Cost Of Goods', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                'url'   => admin_url( 'admin.php') . '?page=' . self::$cost_of_goods_slug,
                'icon'  => null,
                'additional_classes' => array(),
                'menu_order' => 60,
                'children' => array(
                    'cost_of_goods_manager' => array(
                        'title' => __( 'Cost Of Goods Manager', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                        'url'   => admin_url( 'admin.php') . '?page=' . self::$cost_of_goods_slug . '&subpage=cost-of-goods-manager',
                        'icon'  => null,
                        'additional_classes' => array(),
                    ),
                )
            ),

            // Settings
            self::$settings_slug => array(
                'title' => __( 'Settings', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                'url'   => admin_url( 'admin.php') . '?page=' . self::$settings_slug,
                'icon'  => null,
                'additional_classes' => array(),
                'menu_order' => 70,
                'children' => array(
                    'general_settings' => array(
                        'title' => __( 'General Settings', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                        'url'   => admin_url( 'admin.php') . '?page=' . self::$settings_slug . '&subpage=general-settings',
                        'icon'  => null,
                        'additional_classes' => array(),
                        'menu_order' => 10,
                    ),
                    'email_settings' => array(
                        'title' => __( 'Email Settings', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                        'url'   => admin_url( 'admin.php') . '?page=' . self::$settings_slug . '&subpage=email',
                        'icon'  => null,
                        'additional_classes' => array(),
                        'menu_order' => 20,
                    ),
                    'integration_settings' => array(
                        'title' => __( 'Integration Settings', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                        'url'   => admin_url( 'admin.php') . '?page=' . self::$settings_slug . '&subpage=integration',
                        'icon'  => null,
                        'additional_classes' => array(),
                        'menu_order' => 30,
                    ),
                    'debug_settings' => array(
                        'title' => __( 'Debug Settings', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                        'url'   => admin_url( 'admin.php') . '?page=' . self::$settings_slug . '&subpage=debug',
                        'icon'  => null,
                        'additional_classes' => array(),
                        'menu_order' => 50,
                    ),
                )
            ),

            // About/Help
            self::$about_help_slug => array(
                'title' => __( 'Help', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                'url'   => admin_url( 'admin.php') . '?page=' . self::$settings_slug,
                'icon'  => null,
                'additional_classes' => array('additional-items'),
                'menu_order' => 80,
                'children' => array()
            )
        );

        // Build dynamic menu items
        $react_reports = wpd_get_installed_react_reports();

        // Add each report to relevant category
        foreach( $react_reports as $report ) {

			// Skip reports without a dashboard ID
			if ( ! isset($report['dashboard_id']) ) continue;

			// Skip reports that we don't want to show in the menu
			if ( isset($report['appear_in_menu']) && ! $report['appear_in_menu'] ) continue;

            // If it's a pro report, and this is not the pro version
            $pro_report = ( isset($report['pro_report']) && $report['pro_report'] ) ? true : false;
            if ( $pro_report && ! WPD_AI_PRO ) continue;

			// If it's the analytics category, add it to our analytics menu
			if ( isset($report['report_category']) && $report['report_category'] == 'website_traffic' ) {

				$alpha_insights_menu[self::$website_traffic_slug]['children'][$report['dashboard_id']] = array(
					'title' 	=> $report['name'],
					'url' 		=> admin_url( 'admin.php') . '?page=' . self::$website_traffic_slug . '&subpage=' . sanitize_text_field($report['dashboard_id']),
                    'icon'  => null,
                    'additional_classes' => array(),
				);

			// If it's not the analytics category, add it to our profit reports menu
			} else {

				// Add report to results
				$alpha_insights_menu[self::$sales_report_slug]['children'][$report['dashboard_id']] = array(
					'title' 	=> $report['name'],
					'url' 		=> admin_url( 'admin.php') . '?page=' . self::$sales_report_slug . '&subpage=' . sanitize_text_field($report['dashboard_id']),
                    'icon'  => null,
                    'additional_classes' => array(),
				);

			}

		}

        // Apply filters to allow customization
        $alpha_insights_menu = apply_filters( 'wpd_alpha_insights_menu_items', $alpha_insights_menu );

        // Sort menu items by menu_order (lowest first)
        uasort( $alpha_insights_menu, function( $a, $b ) {
            $menu_order_a = isset( $a['menu_order'] ) ? intval( $a['menu_order'] ) : 999;
            $menu_order_b = isset( $b['menu_order'] ) ? intval( $b['menu_order'] ) : 999;
            
            return $menu_order_a - $menu_order_b;
        } );

        // Sort children items by menu_order if they have it
        foreach ( $alpha_insights_menu as $key => $menu_item ) {
            if ( isset( $menu_item['children'] ) && is_array( $menu_item['children'] ) && ! empty( $menu_item['children'] ) ) {
                uasort( $alpha_insights_menu[$key]['children'], function( $a, $b ) {
                    $menu_order_a = isset( $a['menu_order'] ) ? intval( $a['menu_order'] ) : 999;
                    $menu_order_b = isset( $b['menu_order'] ) ? intval( $b['menu_order'] ) : 999;
                    
                    return $menu_order_a - $menu_order_b;
                } );
            }
        }

        // Cache the menu to prevent regeneration on subsequent calls
        $this->menu_cache = $alpha_insights_menu;

        // Return the filtered and sorted alpha insights menu
        return $alpha_insights_menu;

    }

    /**
     * Get the active parent menu item
     * 
     * @since 4.8.0
     */
    private function get_active_parent_menu_item() {

        // Currently only supports the page parameter, all our main menu items are in the page parameter
        $menu_item_key = ( ! empty($_GET['page']) ) ? sanitize_text_field( $_GET['page'] ) : null;

        // Overrides
        if ( $menu_item_key == self::$expense_reports_slug ) {
            $menu_item_key = self::$manage_expenses_slug;
        }

        // Check if the menu item key is a valid menu item key
        if ( ! in_array($menu_item_key, array_keys($this->register_alpha_insights_menu())) ) {
            return null;
        }

        // Filter result
        $menu_item_key = apply_filters( 'wpd_alpha_insights_active_parent_menu_item', $menu_item_key );

        // Return Result
        return $menu_item_key;
    }

    /**
     * Get the active submenu item
     * 
     * @since 4.8.0
     * 
     * @param string $parent_menu_item_key The parent menu item key
     * @return string|null The active submenu item key or null if none found
     */
    private function get_active_submenu_item( $parent_menu_item_key ) {

        // If no parent menu item, return null
        if ( ! $parent_menu_item_key ) {
            return null;
        }

        // Get the full menu structure
        $menu = $this->register_alpha_insights_menu();

        // If parent has no children, return null
        if ( ! isset($menu[$parent_menu_item_key]['children']) || empty($menu[$parent_menu_item_key]['children']) ) {
            return null;
        }

        $children = $menu[$parent_menu_item_key]['children'];
        $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        $current_subpage = isset($_GET['subpage']) ? sanitize_text_field($_GET['subpage']) : '';

        // Method 1: Direct subpage parameter match (most common and reliable)
        if ( ! empty($current_subpage) && isset($children[$current_subpage]) ) {
            return $current_subpage;
        }

        // Method 2: Check if any child's key matches the subpage parameter
        // This handles cases where the key might be different from expected
        if ( ! empty($current_subpage) ) {
            foreach ( $children as $key => $child ) {
                if ( $key === $current_subpage ) {
                    return $key;
                }
            }
        }

        // Method 3: Match current page URL against child URLs
        // This handles cases like wpd-expense-reports that should map to the 'report' submenu
        $current_url = admin_url('admin.php') . '?page=' . $current_page;
        if ( ! empty($current_subpage) ) {
            $current_url .= '&subpage=' . $current_subpage;
        }

        foreach ( $children as $key => $child ) {
            if ( isset($child['url']) ) {
                // Parse both URLs to compare them properly
                $child_url_parts = wp_parse_url($child['url']);
                $current_url_parts = wp_parse_url($current_url);

                // Compare query strings
                if ( isset($child_url_parts['query']) && isset($current_url_parts['query']) ) {
                    parse_str($child_url_parts['query'], $child_params);
                    parse_str($current_url_parts['query'], $current_params);

                    // Check if page parameters match
                    if ( isset($child_params['page']) && isset($current_params['page']) 
                         && $child_params['page'] === $current_params['page'] ) {
                        
                        // If both have subpage and they match, we found it
                        if ( isset($child_params['subpage']) && isset($current_params['subpage']) 
                             && $child_params['subpage'] === $current_params['subpage'] ) {
                            return $key;
                        }
                        
                        // If child URL has no subpage but pages match exactly, we found it
                        if ( ! isset($child_params['subpage']) && ! isset($current_params['subpage']) ) {
                            return $key;
                        }
                    }
                }
            }
        }

        // Method 4: Default to first child if on parent page without subpage
        // This provides a sensible default when landing on the parent page
        if ( empty($current_subpage) && $current_page === $parent_menu_item_key ) {
            $first_child = array_key_first($children);
            return $first_child;
        }

        // Filter result to allow customization
        $submenu_item_key = apply_filters( 'wpd_alpha_insights_active_submenu_item', null, $parent_menu_item_key, $children );

        return $submenu_item_key;

    }

    /**
     * Get all third level menus data
     * Returns menu items that have more than 1 child (for dropdown functionality)
     * 
     * @since 4.8.0
     * 
     * @return array Array of third level menus keyed by parent slug
     */
    public function get_all_third_level_menus_data() {

        $menu = $this->register_alpha_insights_menu();
        $third_level_menus = array();

        foreach ( $menu as $parent_slug => $parent_item ) {
            
            // Skip if no children or only 1 child
            if ( ! isset($parent_item['children']) || ! is_array($parent_item['children']) || count($parent_item['children']) <= 1 ) {
                continue;
            }

            // Format children for response
            $formatted_children = array();
            foreach ( $parent_item['children'] as $child_key => $child_item ) {
                
                // Validate item structure
                if ( ! isset($child_item['title']) || ! isset($child_item['url']) ) {
                    continue;
                }

                $formatted_children[] = array(
                    'key'   => sanitize_text_field($child_key),
                    'title' => sanitize_text_field($child_item['title']),
                    'url'   => esc_url($child_item['url'])
                );
            }

            // Only add if we have valid formatted children
            if ( ! empty($formatted_children) ) {
                $third_level_menus[$parent_slug] = array(
                    'parent_title' => sanitize_text_field($parent_item['title']),
                    'parent_url'   => esc_url($parent_item['url']),
                    'children'     => $formatted_children
                );
            }
        }

        return $third_level_menus;

    }

    /**
     * AJAX handler to get all third-level menu items
     * Returns all menu items that have children in a single request
     * 
     * @since 4.8.0
     * 
     * @return void (outputs JSON)
     */
    public function get_all_third_level_menus_ajax() {

        // Verify nonce
        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), WPD_AI_AJAX_NONCE_ACTION ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
            return;
        }

        // ✅ Add authorization check
        if ( ! wpd_is_user_authorized_to_view_alpha_insights() ) {
            wp_send_json_error( array( 'message' => 'Unauthorized access' ) );
            return;
        }

        // Get all third level menus
        $third_level_menus = $this->get_all_third_level_menus_data();

        // Return the data
        if ( empty($third_level_menus) ) {
            wp_send_json_success( array( 'menus' => array() ) );
        } else {
            wp_send_json_success( array( 'menus' => $third_level_menus ) );
        }

    }

}

// Init
new WPD_Admin_Menu();