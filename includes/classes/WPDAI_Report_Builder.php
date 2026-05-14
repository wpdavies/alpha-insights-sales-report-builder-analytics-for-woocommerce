<?php
/**
 *
 * React Dashboard Handler for Alpha Insights
 *
 * @package Alpha Insights
 * @since 4.7.0
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

class WPDAI_Report_Builder {

    /**
     *
     * The site creation date
     *
     */
    private string $site_creation_date;

    /**
     *
     * The cache build batch size
     *
     */
    private int $cache_build_batch_size = 50;

    /**
     * 
     * The dashboard slug
     * 
     **/
    private $dashboard_slug;

    /**
     * Whether to enqueue dashboard scripts when rendering (disabled for Live Share, which prints scripts separately).
     *
     * @var bool
     */
    private $enqueue_scripts_on_render = true;

    /**
     * 
     *  Mandatory report slugs, cannot be deleted
     * 
     **/
    public static $mandatory_report_slugs = array('orders', 'facebook', 'google-ads', 'expenses', 'profit-loss-statement', 'analytics-overview');

    /**
     * Constructor for WPDAI_Report_Builder class
     *
     * @since 4.7.0
     *
     * @param string|null $dashboard_slug Optional dashboard slug for specific report loading
     */
    public function __construct( $dashboard_slug = null ) {

        $this->site_creation_date = wpdai_get_site_creation_date( WPD_AI_PHP_ISO_DATE ); // Y-m-d
        $this->cache_build_batch_size = get_option( 'wpd_ai_cache_build_batch_size', 50 );
        $this->dashboard_slug = $dashboard_slug;

    }

    /**
     * Control script enqueuing when rendering the dashboard container (e.g. false for Live Share).
     *
     * @param bool $enqueue Whether to call enqueue_scripts from render_dashboard_from_config().
     * @return void
     */
    public function set_enqueue_scripts_on_render( $enqueue ) {
        $this->enqueue_scripts_on_render = (bool) $enqueue;
    }

    /**
     * Output the complete report dashboard
     *
     * @since 4.7.0
     *
     * @return void
     */
	public function output_report() {

		// Get dashboard ID from URL parameter
		$dashboard_id = null;
        $dashboard_config = null;
		
        // Prioritize loading by passed in parameter
        if ( ! empty( $this->dashboard_slug ) ) {

            $dashboard_id = $this->dashboard_slug;
            $dashboard_config = get_option('wpd_dashboard_config_' . $dashboard_id);

        }
        
        // Fallback to loading by URL parameter
        if ( empty( $dashboard_config ) && isset($_GET['subpage']) ) {

            $dashboard_id = sanitize_text_field($_GET['subpage']);
            $dashboard_config = get_option('wpd_dashboard_config_' . $dashboard_id);

        }

		// If no configuration found, pass null to trigger ReportSelector
		if (empty($dashboard_config)) {

			$dashboard_config = null;

            // Auto-install mandatory reports if they're missing
            if ( in_array($dashboard_id, self::$mandatory_report_slugs) ) {
                
                $install_result = $this->auto_install_mandatory_report($dashboard_id);
                
                if ( $install_result['success'] ) {
                    // Reload the config after successful installation
                    $dashboard_config = get_option('wpd_dashboard_config_' . $dashboard_id);
                    
                    // Ensure dashboard_id is set in the configuration
                    if ( $dashboard_config && !isset($dashboard_config['dashboard_id']) ) {
                        $dashboard_config['dashboard_id'] = $dashboard_id;
                    }
                    
                    // Ensure name field is set (for header display)
                    if ( $dashboard_config && !isset($dashboard_config['name']) ) {
                        $dashboard_config['name'] = ucfirst(str_replace('_', ' ', $dashboard_id));
                    }
                }
            }

		} else {
			// Ensure dashboard_id is set in the configuration
			if (!isset($dashboard_config['dashboard_id'])) {
				$dashboard_config['dashboard_id'] = $dashboard_id;
			}
			
			// Ensure name field is set (for header display)
			if (!isset($dashboard_config['name'])) {
				$dashboard_config['name'] = ucfirst(str_replace('_', ' ', $dashboard_id));
			}
		}

        /**
         * 
         * Action: wpd_ai_before_render_dashboard
         * 
         * Description: Fires before the React dashboard is rendered.
         * 
         * Parameters:
         * - $dashboard_id: The ID of the dashboard being rendered.
         * - $dashboard_config: The configuration array for the dashboard.
         * 
         */
        do_action( 'wpd_ai_before_render_dashboard', $dashboard_id, $dashboard_config );

		// Render the React dashboard
		$this->render_dashboard_from_config($dashboard_config);

	}

    /**
     * Render React dashboard with custom configuration
     *
     * @since 4.7.0
     *
     * @param array|null $config Dashboard configuration array or null for report selector
     * @return void
     */
    public function render_dashboard_from_config($config) {

        if ( $this->enqueue_scripts_on_render ) {
            $this->enqueue_scripts('wpd-react-dashboard');
        }

        // Make sure we've done our first install of default dashboard configs
        self::first_install_default_dashboard_configs();
        
        // If config is null or empty, don't set data-config attribute
        $config_attr = '';
        if ($config !== null && !empty($config)) {
            // Encode as JSON and escape for HTML attribute - use JSON_UNESCAPED_SLASHES to avoid escaping forward slashes
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is escaped via esc_attr and wp_json_encode.
            $config_attr = ' data-config="' . esc_attr( wp_json_encode( $config, JSON_UNESCAPED_SLASHES ) ) . '"';
        }

        // Output the React dashboard container - config_attr is already escaped, so don't escape again
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $config_attr is already escaped above.
        echo '<div id="wpd-react-dashboard"' . $config_attr . '></div>';
        echo '<!-- React Dashboard Container Created -->';
        
    }

    /**
     * First install default dashboard configs
     *
     * @since 4.7.0
     *
     * @return void
     */
    public static function first_install_default_dashboard_configs() {

        $initial_install = get_option( 'wpd_ai_initial_report_configs_install', 0 );
        if ( $initial_install == 0 ) {
            // Use override=false to skip any reports that might already exist
            self::import_all_default_reports( false );
            update_option( 'wpd_ai_initial_report_configs_install', 1, false );
        }

    }

    /**
     * Enqueue React dashboard scripts and styles
     *
     * @since 4.7.0
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_scripts($hook) {

        // Enqueue React dashboard integration script
        wp_enqueue_script(
            'wpd-react-dashboard-integration',
            WPD_AI_URL_PATH . 'assets/js/react-dashboard/dist/react-dashboard.js',
            array( 'jquery', 'wp-i18n' ),
            WPD_AI_VER,
            true
        );

        wp_set_script_translations( 'wpd-react-dashboard-integration', 'alpha-insights-sales-report-builder-analytics-for-woocommerce', WPD_AI_PATH . 'languages' );

        $localized_variables = array(
            'locale'                        => get_user_locale(),
            'ajax_url'                      => admin_url('admin-ajax.php'),
            'rest_url'                      => rest_url('alpha-insights/v1/'),
            'nonce'                         => wp_create_nonce( WPD_AI_AJAX_NONCE_ACTION ),
            'rest_nonce'                    => wp_create_nonce('wp_rest'),
            'react_dashboard_url'           => WPD_AI_URL_PATH . 'assets/js/react-dashboard',
            'currency_format_num_decimals' => wc_get_price_decimals(),
            'currency_format_symbol'       => html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8'),
            'currency_format_decimal_sep'  => wc_get_price_decimal_separator(),
            'currency_format_thousand_sep' => wc_get_price_thousand_separator(),
            'currency_format'              => get_woocommerce_price_format(),
            'cache_is_built'               => get_option( 'wpd_ai_all_orders_cached', 0 ),
            'cache_build_batch_size'       => $this->cache_build_batch_size,
            'site_creation_date'           => $this->site_creation_date,
            'site_name'                    => get_bloginfo( 'name' ),
            'filters_data_map_values'      => $this->get_filters_data_map_values(),
            'default_report_ids'           => wpdai_get_default_react_report_ids(),
            'logo_icon_url'                => esc_url( wpdai_get_logo_icon_url() ),
            'menu_slugs' => array(
                'sales_reports'         => WPDAI_Admin_Menu::$sales_report_slug,
                'website_traffic'       => WPDAI_Admin_Menu::$website_traffic_slug,
                'profit_loss_statement' => WPDAI_Admin_Menu::$profit_loss_statement_slug,
                'manage_expenses'       => WPDAI_Admin_Menu::$manage_expenses_slug,
                'expense_reports'       => WPDAI_Admin_Menu::$expense_reports_slug,
                'advertising'           => WPDAI_Admin_Menu::$advertising_slug,
                'cost_of_goods'         => WPDAI_Admin_Menu::$cost_of_goods_slug,
                'settings'              => WPDAI_Admin_Menu::$settings_slug,
                'about_help'            => WPDAI_Admin_Menu::$about_help_slug,
            ),
            'custom_data_source_mappings' => WPDAI_Custom_Data_Source_Registry::get_all_mappings(),
        );

        /**
         * 
         * Filter: wpd_ai_localized_report_builder_variables
         * 
         * Description: Filters the localized variables for the React dashboard.
         * 
         * Parameters:
         * - $localized_variables: The localized variables array.
         * 
         * Return: The filtered localized variables array.
         * 
         * 
         */
        $localized_variables = apply_filters( 'wpd_ai_localized_report_builder_variables', $localized_variables );

        // Localize script with WordPress data
        wp_localize_script('wpd-react-dashboard-integration', 'wpd_alpha_insights', $localized_variables);
    }

    /**
     * Register AJAX actions early in WordPress lifecycle
     *
     * @since 4.7.0
     *
     * @return void
     */
    public static function register_ajax_actions() {

        // AJAX handlers
        add_action('wp_ajax_wpd_get_live_dashboard_data', [__CLASS__, 'get_live_dashboard_data_ajax_handler']);
        add_action('wp_ajax_wpd_get_available_reports', [__CLASS__, 'get_available_reports_ajax_handler']);
        add_action('wp_ajax_wpd_create_report', [__CLASS__, 'create_report_ajax_handler']);
        add_action('wp_ajax_wpd_update_report', [__CLASS__, 'update_report_ajax_handler']);
        add_action('wp_ajax_wpd_delete_report', [__CLASS__, 'delete_report_ajax_handler']);
        add_action('wp_ajax_wpd_get_default_reports', [__CLASS__, 'get_default_reports_ajax_handler']);
        add_action('wp_ajax_wpd_import_default_report', [__CLASS__, 'import_default_report_ajax_handler']);
        add_action('wp_ajax_wpd_reset_default_report', [__CLASS__, 'reset_default_report_ajax_handler']);
        add_action('wp_ajax_wpd_save_report_config', [__CLASS__, 'save_report_config_ajax_handler']);
        add_action('wp_ajax_wpd_import_all_default_reports', [__CLASS__, 'import_all_default_reports_ajax_handler']);
        add_action('wp_ajax_wpd_import_json_report', [__CLASS__, 'import_json_report_ajax_handler']);
        add_action('wp_ajax_wpd_get_uncached_order_count', [__CLASS__, 'get_uncached_order_count_ajax_handler']);
        add_action('wp_ajax_wpd_build_order_cache_batch', [__CLASS__, 'build_order_cache_batch_ajax_handler']);
        add_action('wp_ajax_wpd_mark_cache_complete', [__CLASS__, 'mark_cache_complete_ajax_handler']);

    }

    /**
     * Static AJAX handler for importing all default reports
     *
     * @since 4.7.0
     *
     * @return void
     */
    public static function import_all_default_reports_ajax_handler() {
        // Check nonce for security
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, WPD_AI_AJAX_NONCE_ACTION ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        // Check capability
        if ( ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to perform this action.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        // Get override parameter, defaults to true for backward compatibility
        $override = isset($_POST['override']) ? filter_var($_POST['override'], FILTER_VALIDATE_BOOLEAN) : true;

        $instance = new self();
        $results = $instance->import_all_default_reports( $override );
        wp_send_json($results);
    }
    
    /**
     * Static AJAX handler for importing JSON report
     *
     * @since 4.8.0
     *
     * @return void
     */
    public static function import_json_report_ajax_handler() {
        // Check nonce for security
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, WPD_AI_AJAX_NONCE_ACTION ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        // Check capability
        if ( ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to perform this action.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        $report_data_json = sanitize_textarea_field($_POST['report_data'] ?? '');
        $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === '1';

        if (empty($report_data_json)) {
            wp_send_json_error( array(
                'message' => __( 'No report data provided.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        $report_data = json_decode(stripslashes($report_data_json), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error( array(
                'message' => __( 'Invalid JSON format.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        if (empty($report_data['dashboard_id'])) {
            wp_send_json_error( array(
                'message' => __( 'Report dashboard_id is required.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        $instance = new self();
        $results = $instance->import_json_report($report_data, $overwrite);
        wp_send_json($results);
    }
    
    /**
     * Static AJAX handler for importing default report
     *
     * @since 4.7.0
     *
     * @return void
     */
    public static function import_default_report_ajax_handler() {
        // Check nonce for security
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, WPD_AI_AJAX_NONCE_ACTION ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        // Check capability
        if ( ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to perform this action.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        $report_slug = sanitize_text_field($_POST['report_slug'] ?? '');

        $instance = new self();
        $results = $instance->import_default_report($report_slug);
        wp_send_json($results);
    }

    
    /**
     * Static AJAX handler for getting default reports
     *
     * @since 4.7.0
     *
     * @return void
     */
    public static function get_default_reports_ajax_handler() {
        // Check nonce for security (optional for read operations, but recommended)
        if ( ! empty($_POST['nonce']) ) {
            $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
            if ( ! wp_verify_nonce( $nonce, WPD_AI_AJAX_NONCE_ACTION ) ) {
                wp_send_json_error( array(
                    'message' => __( 'Security check failed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
                ) );
                return;
        }
        }

        // Check capability
        if ( ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to perform this action.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        $instance = new self();
        $results = $instance->get_default_reports();
        wp_send_json($results);
    }

    /**
     * Static AJAX handler for resetting default report
     *
     * @since 4.7.0
     *
     * @return void
     */
    public static function reset_default_report_ajax_handler() {
        // Check nonce for security
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, WPD_AI_AJAX_NONCE_ACTION ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        // Check capability
        if ( ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to perform this action.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        $report_slug = sanitize_text_field($_POST['report_slug'] ?? '');

        $instance = new self();
        $results = $instance->reset_default_report($report_slug);
        wp_send_json($results);
    }

    /**
     * Static AJAX handler for saving report config
     *
     * @since 4.7.0
     *
     * @return void
     */
    public static function save_report_config_ajax_handler() {
        // Verify nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, WPD_AI_AJAX_NONCE_ACTION ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
            return;
        }

        $report_slug = isset($_POST['report_slug']) ? sanitize_text_field( wp_unslash( $_POST['report_slug'] ) ) : '';
        
        // Sanitize and decode JSON config from POST data
        $config_raw = isset($_POST['config']) ? $_POST['config'] : '';
        $config_data = wpdai_sanitize_and_decode_json_config( $config_raw, true );
        
        // Handle error case
        if ( is_wp_error( $config_data ) ) {
            wp_send_json_error( array( 
                'message' => $config_data->get_error_message()
            ) );
            return;
        }
        
        // Ensure we have an array
        if ( ! is_array( $config_data ) ) {
            $config_data = array();
        }

        $instance = new self();
        $results = $instance->save_report_config($report_slug, $config_data);
        wp_send_json($results);
    }

    /**
     * Static AJAX handler for getting live dashboard data.
     *
     * Security: (1) Nonce verification – accepts either standard AJAX nonce (WPD_AI_AJAX_NONCE_ACTION)
     * or live-share nonce (wpd_live_share_nonce). (2) Authorization – logged-in requests require
     * capability via wpdai_is_user_authorized_to_use_alpha_insights();
     *
     * @since 4.7.0
     *
     * @return void
     */
    public static function get_live_dashboard_data_ajax_handler() {

        // 1. Nonce verification – require nonce and validate origin (standard or live share).
        if ( ! isset( $_POST['nonce'] ) || ! is_string( $_POST['nonce'] ) ) {
            self::log_error( 'WPDAI_Report_Builder: No nonce provided for live dashboard data.' );
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
        $regular_nonce_valid     = (bool) wp_verify_nonce( $nonce, WPD_AI_AJAX_NONCE_ACTION );
        
        $live_share_nonce_valid = (bool) wp_verify_nonce( $nonce, 'wpd_live_share_nonce' );

        if ( ! $live_share_nonce_valid && ! $regular_nonce_valid ) {
            self::log_error( 'WPDAI_Report_Builder: Nonce verification failed for live dashboard data.' );
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
            return;
        }

        // 2. Authorization: live-share path (nopriv) vs logged-in path.
        if ( $live_share_nonce_valid ) {
            // Live share: require token; no capability check (intended for public links).
            if ( ! isset( $_POST['live_share_auth'] ) || ! is_string( $_POST['live_share_auth'] ) || trim( $_POST['live_share_auth'] ) === '' ) {
                self::log_error( 'WPDAI_Report_Builder: Live share request missing live_share_auth.' );
                wp_send_json_error( array( 'message' => __( 'Live share authentication required.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
                return;
            }
            $live_share_auth = sanitize_text_field( wp_unslash( $_POST['live_share_auth'] ) );
            if ( ! self::validate_live_share_auth( $live_share_auth ) ) {
                self::log_error( 'WPDAI_Report_Builder: Invalid live_share_auth for live dashboard data.' );
                wp_send_json_error( array( 'message' => __( 'Invalid live share authentication.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
                return;
            }
        } else {
            // Logged-in: validate referer via check_ajax_referer, then capability.
            if ( ! check_ajax_referer( WPD_AI_AJAX_NONCE_ACTION, 'nonce', false ) ) {
                self::log_error( 'WPDAI_Report_Builder: AJAX referer/nonce check failed for live dashboard data.' );
                wp_send_json_error( array( 'message' => __( 'Security check failed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
                return;
            }
            if ( ! is_user_logged_in() || ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
                self::log_error( 'WPDAI_Report_Builder: User not authorized to access live dashboard data.' );
                wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
                return;
            }
        }

        try {
            // 3. Parse and sanitize config from POST (after nonce + auth).
            $config     = array();
            $config_raw = isset( $_POST['config'] ) && is_string( $_POST['config'] ) ? wp_unslash( $_POST['config'] ) : '';
            if ( $config_raw !== '' ) {
                $config = wpdai_sanitize_and_decode_json_config( $config_raw, false );
                
                // Handle error case
                if ( is_wp_error( $config ) ) {
                    self::log_error('WPDAI_Report_Builder: Invalid config format.' );
                    self::log_error('WPDAI_Report_Builder: JSON decode error: ' . $config->get_error_message() );
                    wp_send_json_error( array( 
                        'message' => sprintf(
                            /* translators: %s: JSON error message */
                            __( 'Failed to parse dashboard configuration: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                            esc_html( $config->get_error_message() )
                        )
                    ) );
                    return;
                }
                
                // Ensure we have an array
                if ( ! is_array( $config ) ) {
                    $config = array();
                }
            }

            // When using live share nonce, config must include valid live_share_links
            if ( $live_share_nonce_valid && ! self::config_has_valid_live_share_structure( $config ) ) {
                self::log_error( 'WPDAI_Report_Builder: Live share request missing required live_share_links config.' );
                wp_send_json_error( array(
                    'message' => __( 'Invalid live share configuration. The report must include live share links.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                ) );
                return;
            }
            
            $response = self::get_live_dashboard_data_from_config( $config );
            
            wp_send_json($response);
            
        } catch (Exception $e) {
            self::log_error('WPDAI_Report_Builder: Error fetching live data: ' . $e->getMessage());
            self::log_error('WPDAI_Report_Builder: Error stack trace: ' . $e->getTraceAsString());
            wp_send_json_error('Error fetching live data: ' . $e->getMessage());
        }

    }

    /**
     * Static AJAX handler for getting available reports
     *
     * @since 4.7.0
     *
     * @return void
     */
    public static function get_available_reports_ajax_handler() {
        // Check nonce for security (optional for read operations, but recommended)
        if ( ! empty($_POST['nonce']) ) {
            $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
            if ( ! wp_verify_nonce( $nonce, WPD_AI_AJAX_NONCE_ACTION ) ) {
                wp_send_json_error( array(
                    'message' => __( 'Security check failed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
                ) );
                return;
            }
        }

        // Check capability
        if ( ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to perform this action.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        $instance = new self();
        $results = $instance->get_available_reports();
        wp_send_json($results);
    }

    /**
     * Static AJAX handler for creating new reports
     *
     * @since 4.7.0
     *
     * @return void
     */
    public static function create_report_ajax_handler() {
        // Check nonce for security
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, WPD_AI_AJAX_NONCE_ACTION ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        // Check capability
        if ( ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to perform this action.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        // Validate required fields
        $required_fields = array('report_slug');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error( array(
                    'message' => sprintf(
                        /* translators: %s: Field name */
                        __('Missing required field: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                        esc_html($field)
                    )
                ) );
                return;
            }
        }

        $report_slug = sanitize_text_field($_POST['report_slug'] ?? '');
        
        // Build config data array from POST data
        $report_config = array(
            'name' => sanitize_text_field($_POST['report_name'] ?? ''),
            'menu_order' => intval($_POST['menu_order'] ?? 0),
            'report_category' => sanitize_text_field($_POST['report_category'] ?? 'sales_reports'),
            'version_number' => sanitize_text_field($_POST['version_number'] ?? '1.0'),
            'appear_in_menu' => isset($_POST['appear_in_menu']) ? (bool) $_POST['appear_in_menu'] : true,
            'icon' => sanitize_text_field($_POST['icon'] ?? 'bar_chart'),
            'color' => sanitize_text_field($_POST['color'] ?? 'blue')
        );
        
        // If a full config is provided (for duplicate mode), decode and merge it
        if (!empty($_POST['report_config'])) {
            // Sanitize and decode JSON config from POST data
            $full_config_raw = $_POST['report_config'];
            $full_config = wpdai_sanitize_and_decode_json_config( $full_config_raw, false );
            
            // Only merge if we got a valid array (ignore errors silently in this context)
            if ( ! is_wp_error( $full_config ) && is_array( $full_config ) ) {
                // Merge the full config with the basic fields, preferring the basic fields for metadata
                $report_config = array_merge($full_config, $report_config);
            }
        }

        $instance = new self();
        $results = $instance->create_report($report_slug, $report_config);
        wp_send_json($results);
    }

    /**
     * Static AJAX handler for updating reports
     *
     * @since 4.7.0
     *
     * @return void
     */
    public static function update_report_ajax_handler() {
        // Check nonce for security
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, WPD_AI_AJAX_NONCE_ACTION ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        // Check capability
        if ( ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to perform this action.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        // Validate required fields
        $required_fields = array('report_name', 'report_slug');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error( array(
                    'message' => sprintf(
                        /* translators: %s: Field name */
                        __('Missing required field: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                        esc_html($field)
                    )
                ) );
                return;
            }
        }

        $original_slug = sanitize_title($_POST['original_slug'] ?? '');
        
        // Build config data array from POST data
        $config_data = array(
            'dashboard_id' => sanitize_title($_POST['report_slug'] ?? ''),
            'name' => sanitize_text_field($_POST['report_name'] ?? ''),
            'menu_order' => intval($_POST['menu_order'] ?? 0),
            'report_category' => sanitize_text_field($_POST['report_category'] ?? 'sales_reports'),
            'version_number' => sanitize_text_field($_POST['version_number'] ?? '1.0'),
            'appear_in_menu' => isset($_POST['appear_in_menu']) ? (bool) $_POST['appear_in_menu'] : true
        );

        $instance = new self();
        $results = $instance->update_report($original_slug, $config_data);
        wp_send_json($results);
    }

    /**
     *
     * Static AJAX handler for deleting reports
     *
     */
    public static function delete_report_ajax_handler() {
        // Check nonce for security
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, WPD_AI_AJAX_NONCE_ACTION ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        // Check capability
        if ( ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to perform this action.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        // Validate required fields
        if (empty($_POST['report_id'])) {
            wp_send_json_error( array(
                'message' => __('Missing report ID.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce')
            ) );
            return;
        }

        $report_id = sanitize_text_field($_POST['report_id'] ?? '');

        $instance = new self();
        $results = $instance->delete_report($report_id);
        wp_send_json($results);
    }

    /**
     * 
     * Static AJAX handler for getting uncached order count
     * 
     */
    public static function get_uncached_order_count_ajax_handler() {
        // Check nonce for security
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, WPD_AI_AJAX_NONCE_ACTION ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        // Check capability
        if ( ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to perform this action.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        $instance = new self();
        $results = $instance->get_uncached_order_count();
        wp_send_json($results);
    }

    /**
     * 
     * Static AJAX handler for building order cache in batches
     * 
     */
    public static function build_order_cache_batch_ajax_handler() {
        // Check nonce for security
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, WPD_AI_AJAX_NONCE_ACTION ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        // Check capability
        if ( ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to perform this action.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        $batch_size = 500; // Default batch size
        if ( isset($_POST['batch_size']) && is_numeric($_POST['batch_size']) ) {
            $batch_size = absint($_POST['batch_size']);
        }

        $instance = new self();
        $results = $instance->build_order_cache_batch($batch_size);
        wp_send_json($results);
    }

    /**
     * 
     * Static AJAX handler for marking cache as complete
     * 
     */
    public static function mark_cache_complete_ajax_handler() {
        // Check nonce for security
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, WPD_AI_AJAX_NONCE_ACTION ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        // Check capability
        if ( ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to perform this action.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
            ) );
            return;
        }

        $instance = new self();
        $results = $instance->mark_cache_complete();
        wp_send_json($results);
    }

    /**
     * Creates a data map to populate dynamic filters
     *
     * @since 4.7.0
     *
     * @return array Data map containing filter values for orders, analytics, and traffic sources
     */
    public function get_filters_data_map_values() {

        $report_filters = new WPDAI_Report_Filters();

        // Cache filter values to avoid duplicate queries
        // These filters are used in multiple places, so we fetch them once
        $traffic_sources = $report_filters->get_filter_values_traffic_sources();

        // Potentially expensive queries that can be strings instead
        $products = $report_filters->get_filter_values_products();
        $order_query_parameters = $report_filters->get_filter_values_order_query_parameter_key_value_pairs();
        $website_traffic_query_parameters = $report_filters->get_filter_values_website_traffic_query_parameter_key_value_pairs();

        $filter_data_map = array(
            'orders' => array(
                'order_statuses' => array_merge( array( 'any' => 'Any' ), wc_get_order_statuses() ),
                'traffic_sources' => $traffic_sources,
                'query_parameters' => $order_query_parameters
            ),
            'products' => array(
                'products' => $products,
                'product_categories' => $report_filters->get_filter_values_product_categories(),
                'product_tags' => $report_filters->get_filter_values_product_tags()
            ),
            'customers' => array(
                'billing_countries' => $report_filters->get_filter_values_billing_countries(),
            ),
            'facebook_campaigns' => array(
                'campaigns' => $report_filters->get_filter_values_facebook_campaigns()
            ),
            'google_campaigns' => array(
                'campaigns' => $report_filters->get_filter_values_google_campaigns()
            ),
            'expenses' => array(
                'expense_categories' => $report_filters->get_filter_values_expense_categories()
            ),
            'website_traffic' => array(
                'traffic_sources' => $traffic_sources,
                'query_parameters' => $website_traffic_query_parameters,
                'session_contains_events' => $report_filters->get_filter_values_website_traffic_events(),
                'products' => $products
            )
        );

        return $filter_data_map;

    }


    /**
     * Core method for getting uncached order count
     *
     * @since 4.7.0
     *
     * @return array Response array containing the count of uncached orders
     */
    public function get_uncached_order_count() {
        $uncached_order_count = 0;
        $uncached_orders = wpdai_get_order_ids_without_calculation_cache();

        if ( is_array($uncached_orders) && ! empty($uncached_orders) ) {
            $uncached_order_count = count($uncached_orders);
        }

        $response = array(
            'success' => true,
            'data' => array(
                'uncached_order_count' => $uncached_order_count,
            ),
        );

        return $response;
    }


    /**
     * Core method for building order cache in batches
     *
     * @since 4.7.0
     *
     * @param int $batch_size Number of orders to process in each batch
     * @return array Response array with cached order count
     */
    public function build_order_cache_batch($batch_size = 50) {
        $cached_order_count = wpdai_build_order_cache_in_batch( $batch_size );
        
        $response = array(
            'success' => true,
            'data' => array(
                'cached_order_count' => $cached_order_count,
            ),
        );
        
        return $response;
    }


    /**
     * Core method for marking cache as complete
     *
     * @since 4.7.0
     *
     * @return array Response array indicating cache completion status
     */
    public function mark_cache_complete() {

        // Update the cache status to complete
        update_option( 'wpd_ai_all_orders_cached', 1 );

        $response = array(
            'success' => true,
            'message' => __( 'Cache status updated to complete.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
            'data' => array(
                'cache_is_built' => 1
            )
        );
        
        return $response;
    }

    /**
     * Get available dashboard reports from the database
     *
     * @since 4.7.0
     *
     * @return array Response array containing all available reports with metadata
     */
    public function get_available_reports() {
        
        $response = array();

        // Get all options that start with wpd_dashboard_config_ using direct DB query for autoload=false options
        global $wpdb;
        $dashboard_configs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                'wpd_dashboard_config_%'
            )
        );

        $reports = array();
        $raw_results = $dashboard_configs;

        if ( $dashboard_configs ) {
            foreach ( $dashboard_configs as $config ) {
                // Extract the dashboard ID from the option name
                $dashboard_id = str_replace( 'wpd_dashboard_config_', '', $config->option_name );
                
                // Parse the config data
                $config_data = maybe_unserialize( $config->option_value );
                
                if ( $config_data && is_array( $config_data ) ) {

                    // Get the last updated time from config
                    $last_updated = 'Unknown';
                    if (isset($config_data['updated_at'])) {
                        $last_updated = gmdate('Y-m-d H:i:s', $config_data['updated_at']);
                    }
                    
                    // Skip default reports that were auto-generated
                    $title = isset( $config_data['title'] ) ? $config_data['title'] : ucfirst( str_replace( '_', ' ', $dashboard_id ) );
                    
                    // Create a report object with full config
                    $report = array(
                        'dashboard_id' => $dashboard_id,
                        'name' => isset( $config_data['name'] ) ? $config_data['name'] : $title,
                        'last_updated' => $last_updated,
                        'report_category' => isset( $config_data['report_category'] ) ? $config_data['report_category'] : 'sales_reports',
                        'version_number' => isset( $config_data['version_number'] ) ? $config_data['version_number'] : '1.0',
                        'appear_in_menu' => isset( $config_data['appear_in_menu'] ) ? $config_data['appear_in_menu'] : true,
                        'icon' => isset( $config_data['icon'] ) ? $config_data['icon'] : 'bar_chart',
                        'color' => isset( $config_data['color'] ) ? $config_data['color'] : 'blue',
                        'menu_order' => isset( $config_data['menu_order'] ) ? $config_data['menu_order'] : 0,
                        'is_mandatory' => in_array( $dashboard_id, self::$mandatory_report_slugs ), // Flag if this report cannot be deleted
                        'live_share_links' => isset( $config_data['live_share_links'] ) ? $config_data['live_share_links'] : array(), // Add live share links to top level
                        'config' => $config_data // Include the full configuration
                    );
                        
                    $reports[] = $report;

                }
            }
        }

        // Sort reports by menu_order (lowest first)
        usort($reports, function($a, $b) {
            $menu_order_a = isset($a['config']['menu_order']) ? intval($a['config']['menu_order']) : 0;
            $menu_order_b = isset($b['config']['menu_order']) ? intval($b['config']['menu_order']) : 0;
            
            return $menu_order_a - $menu_order_b;
        });

        $response['success'] = true;
        $response['data'] = array(
            'reports' => $reports,
            'total' => count( $reports ),
            'debug' => array(
                'configs_found' => $dashboard_configs ? count($dashboard_configs) : 0,
                'query' => $wpdb->last_query,
            )
        );

        return $response;

    }

    /**
     * Core method for deleting reports
     *
     * @since 4.7.0
     *
     * @param string $report_id The dashboard ID of the report to delete
     * @return array Response array indicating success or failure
     */
    public function delete_report($report_id) {
        $response = array();

        // Check if this is a mandatory report that cannot be deleted
        if ( in_array( $report_id, self::$mandatory_report_slugs ) ) {
            $response['success'] = false;
            $response['message'] = __('This is a required Alpha Insights report and cannot be deleted. You can hide it from the menu by unchecking "Appear in Menu" in the report settings.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce');
            $response['error_code'] = 'mandatory_report';
            return $response;
        }

        // Check if report exists
        $existing_config = get_option('wpd_dashboard_config_' . $report_id);
        if ($existing_config === false) {
            $response['success'] = false;
            $response['message'] = __('Report not found.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce');
            return $response;
        }

        // Delete the report configuration
        $deleted = delete_option('wpd_dashboard_config_' . $report_id);
        
        // Also clean up the separate last updated option if it exists
        delete_option('wpd_dashboard_last_updated_' . $report_id);
        
        if ($deleted) {
            $response['success'] = true;
            $response['message'] = __('Report deleted successfully.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce');
        } else {
            $response['success'] = false;
            $response['message'] = __('Failed to delete report.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce');
        }

        return $response;
    }

    /**
     * Core method for creating reports
     *
     * @since 4.7.0
     *
     * @param string $report_slug The unique slug for the report
     * @param array $report_config Configuration array for the report (must include 'name')
     * @return array Response array indicating success or failure
     */
    public function create_report($report_slug, $report_config) {
        $response = array();

        // Validate input
        if (empty($report_slug) || empty($report_config)) {
            $response['success'] = false;
            $response['message'] = __('Report slug and config data are required.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce');
            return $response;
        }

        // Validate required fields
        if (empty($report_config['name'])) {
            $response['success'] = false;
            $response['message'] = __('Report name is required in config data.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce');
            return $response;
        }

        $report_slug = sanitize_title($report_slug);
        $report_name = sanitize_text_field($report_config['name']);
        $menu_order = isset($report_config['menu_order']) ? intval($report_config['menu_order']) : 0;
        $report_category = isset($report_config['report_category']) ? sanitize_text_field($report_config['report_category']) : 'sales_reports';
        $version_number = isset($report_config['version_number']) ? sanitize_text_field($report_config['version_number']) : '1.0';
        $appear_in_menu = isset($report_config['appear_in_menu']) ? (bool) $report_config['appear_in_menu'] : true;

        // Validate slug format
        if (!preg_match('/^[a-z0-9-]+$/', $report_slug)) {
            $response['success'] = false;
            $response['message'] = __('Report slug can only contain lowercase letters, numbers, and hyphens.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce');
            return $response;
        }

        // Validate version number format (major.minor with 1-2 decimal places)
        if (!preg_match('/^\d+\.\d{1,2}$/', $version_number)) {
            $response['success'] = false;
            $response['message'] = __('Version number must be in format major.minor (e.g., 1.0, 1.21, 2.05).', 'alpha-insights-sales-report-builder-analytics-for-woocommerce');
            return $response;
        }

        // Check if report slug already exists
        $existing_config = get_option('wpd_dashboard_config_' . $report_slug);
        if ($existing_config !== false) {
            $response['success'] = false;
            $response['message'] = __('A report with this slug already exists. Please choose a different slug.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce');
            return $response;
        }

        // Get icon and color from config with defaults
        $icon = isset($report_config['icon']) ? sanitize_text_field($report_config['icon']) : 'bar_chart';
        $color = isset($report_config['color']) ? sanitize_text_field($report_config['color']) : 'blue';

        // Create the report configuration
        // Start with the report_config if it has additional data (rows, filters, etc.)
        $config = is_array($report_config) ? $report_config : array();
        
        // Ensure required fields are set, overriding anything from report_config
        $config['dashboard_id'] = $report_slug;
        $config['name'] = $report_name;
        $config['menu_order'] = $menu_order;
        $config['report_category'] = $report_category;
        $config['version_number'] = $version_number;
        $config['appear_in_menu'] = $appear_in_menu;
        $config['icon'] = $icon;
        $config['color'] = $color;
        
        // Set defaults for optional fields if not present
        if (!isset($config['realtime_data'])) {
            $config['realtime_data'] = false;
        }
        if (!isset($config['filters'])) {
            $config['filters'] = array(
                'date_preset' => 'last_30_days',
                'comparison_date_selection' => 'previous_period'
            );
        }
        
        // Always update timestamps
        if (!isset($config['created_at'])) {
            $config['created_at'] = current_time('timestamp');
        }
        $config['updated_at'] = current_time('timestamp');

        // Save the configuration with autoload=false for better performance
        $option_name = 'wpd_dashboard_config_' . $report_slug;
        $saved = update_option($option_name, $config, false);
        
        if ($saved !== false) {
            $response['success'] = true;
            $response['message'] = __('Report created successfully!', 'alpha-insights-sales-report-builder-analytics-for-woocommerce');
            $response['data'] = array(
                'report_slug' => $report_slug,
                'report_name' => $report_name
            );
        } else {
            $response['success'] = false;
            $response['message'] = __('Failed to create report. Please try again.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce');
        }

        return $response;
    }


    /**
     * Core method for updating reports
     *
     * @since 4.7.0
     *
     * @param string $original_slug The original slug to identify the report to update
     * @param array $config_data Configuration array containing updated report data
     * @return array Response array indicating success or failure
     */
    public function update_report($original_slug, $config_data) {
        $response = array();

        // Validate input
        if (empty($original_slug) || empty($config_data)) {
            $response['success'] = false;
            $response['message'] = __('Original slug and config data are required.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce');
            return $response;
        }

        // Extract values from config data with defaults
        $report_slug = isset($config_data['dashboard_id']) ? $config_data['dashboard_id'] : $original_slug;
        $report_name = isset($config_data['name']) ? $config_data['name'] : '';
        $menu_order = isset($config_data['menu_order']) ? intval($config_data['menu_order']) : 0;
        $report_category = isset($config_data['report_category']) ? sanitize_text_field($config_data['report_category']) : 'sales_reports';
        $version_number = isset($config_data['version_number']) ? sanitize_text_field($config_data['version_number']) : '1.0';
        $appear_in_menu = isset($config_data['appear_in_menu']) ? (bool) $config_data['appear_in_menu'] : true;

        // Validate slug format
        if (!preg_match('/^[a-z0-9-]+$/', $report_slug)) {
            $response['success'] = false;
            $response['message'] = __('Report slug can only contain lowercase letters, numbers, and hyphens.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce');
            return $response;
        }

        // Check if original report exists
        $existing_config = get_option('wpd_dashboard_config_' . $original_slug);
        if ($existing_config === false) {
            $response['success'] = false;
            $response['message'] = __('Report not found.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce');
            return $response;
        }

        // If slug changed, check if new slug already exists
        if ($report_slug !== $original_slug) {
            $new_slug_exists = get_option('wpd_dashboard_config_' . $report_slug);
            if ($new_slug_exists !== false) {
                $response['success'] = false;
                $response['message'] = __('A report with this slug already exists. Please choose a different slug.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce');
                return $response;
            }
        }

        // Merge existing config with new data - preserve all existing config and only update specific fields
        $config = $existing_config; // Start with the complete existing configuration
        
        // Update only the fields that are provided in config_data
        $config['dashboard_id'] = $report_slug;
        if (!empty($report_name)) {
            $config['name'] = $report_name;
        }
        $config['menu_order'] = $menu_order;
        $config['report_category'] = $report_category;
        $config['version_number'] = $version_number;
        $config['appear_in_menu'] = $appear_in_menu;
        $config['updated_at'] = current_time('timestamp');
        
        // Ensure required fields exist
        if (!isset($config['filters'])) {
            $config['filters'] = array();
        }
        if (!isset($config['created_at'])) {
            $config['created_at'] = current_time('timestamp');
        }

        // If slug changed, delete old option and create new one
        if ($report_slug !== $original_slug) {
            delete_option('wpd_dashboard_config_' . $original_slug);
        }

        // Save the updated configuration with autoload=false for better performance
        $option_name = 'wpd_dashboard_config_' . $report_slug;
        $saved = update_option($option_name, $config, false);
        
        if ($saved !== false) {
            $response['success'] = true;
            $response['message'] = __('Report updated successfully!', 'alpha-insights-sales-report-builder-analytics-for-woocommerce');
            $response['data'] = array(
                'report_slug' => $report_slug,
                'report_name' => $config['name']
            );
        } else {
            $response['success'] = false;
            $response['message'] = __('Failed to update report. Please try again.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce');
        }

        return $response;
    }

    /**
     *
     * Get live dashboard data from config
     *
     */
    public static function get_live_dashboard_data_from_config( $config ) {

        try {

            // Check build mode from cookie (session-based, not report-specific)
            $build_mode_enabled = true; // Default to true
            if ( isset( $_COOKIE['wpd_build_mode'] ) ) {
                $build_mode_value = sanitize_text_field( $_COOKIE['wpd_build_mode'] );
                $build_mode_enabled = $build_mode_value === 'true';
            }
            
            // Override config with cookie value
            $config['build_mode_enabled'] = $build_mode_enabled;

            // Set up filters from config
            $filters = array();
            $start_time = microtime(true);
            $memory_start = memory_get_usage(true);
            $memory_used = 0;
            $comparison_data = array();
            $total_db_records = 0;
            $minimize_data = false;

            // Increase memory temporarily if low
            $server_memory_limit = wpdai_get_memory_limit();
            if ( $server_memory_limit < 512 ) @ini_set('memory_limit', '512M');
            
            // Sanitize the filters passed in
            if (isset($config['filters']) && is_array($config['filters'])) {

                // Use filters from config
                $filters = $config['filters'];

                // Safety check all time data
                if ( isset($filters['date_preset']) && $filters['date_preset'] === 'all_time' ) {

                    $remove_filters = array('comparison_date_selection', 'comparison_date_from', 'comparison_date_to');

                    foreach( $remove_filters as $remove_filter ) {
                        if ( isset($filters[$remove_filter]) ) unset($filters[$remove_filter]);
                    }

                }
                
                // Calculate actual dates from presets if they exist
                $filters = self::calculate_dates_from_presets($filters);
                
            } else {
                // No filters provided in config, use default date range
                $wp_timestamp = current_time('timestamp');
                $filters = array(
                    'date_from' => gmdate('Y-m-d', strtotime('-30 days', $wp_timestamp)), // 30 days ago
                    'date_to' => current_time('Y-m-d') // today
                );
            }

            // Decide what data is required from the report config
            $required_entities = self::get_required_entities( $config );
            $required_data = array_keys($required_entities);

            // Setup data table limits
            $data_table_limit = self::get_data_table_limits_from_config( $config );
            if ( is_array($data_table_limit) && ! empty($data_table_limit) ) {
                foreach( $data_table_limit as $entity => $limit ) {
                    $filters['data_table_limit'][$entity] = $limit;
                }
            }

            // Make sure we are fetching sales data, if any of the below keys are hit
            $sales_data_array_keys = array( 'orders', 'customers', 'products', 'coupons', 'refunds', 'taxes' );
            foreach( $sales_data_array_keys as $sales_data_array_key ) {
                if (in_array($sales_data_array_key, $required_data)) {
                    if ( ! in_array('orders', $required_data) ) $required_data[] = 'orders';
                    break;
                }
            }

            // Load the warehouse
            $data_warehouse = wpdai_data_warehouse( $filters );

            // Fetch all required entities via the warehouse's single entry point (delegates to registered data sources).
            $data_warehouse->fetch_data( $required_data );

            // Return data
            $report_data = $data_warehouse->get_data();
            $date_range_container = $data_warehouse->get_data_by_date_range_container();
            $data_warehouse_filter = $data_warehouse->get_filter();
            $total_db_records += $data_warehouse->get_total_db_records();
            $report_errors = $data_warehouse->get_errors();
            $execution_time_report_entities = $data_warehouse->get_execution_time();

            // Clear memory
            unset($data_warehouse);

            // Removes any unnecessary data from our data array - skip in build mode
            $report_data = self::minimize_data($report_data, $required_entities);
            $report_data = self::clean_data($report_data, $date_range_container);

            // Fetch comparison data if comparison dates are set
            $comparison_data = array();
            $comparison_data_warehouse_filter = array();
            if (isset($filters['comparison_date_from']) && isset($filters['comparison_date_to'])) {

                // Create comparison filters
                $comparison_filters = $filters;
                $comparison_filters['date_from'] = $filters['comparison_date_from'];
                $comparison_filters['date_to'] = $filters['comparison_date_to'];

                // Remove date preset filter in case, because it would call the warehouse incorrectly in comparison data
                if ( isset($comparison_filters['date_preset']) ) unset($comparison_filters['date_preset']);
                
                // Create new data warehouse instance for comparison period
                $comparison_warehouse = wpdai_data_warehouse( $comparison_filters );

                // Fetch all required entities via the warehouse's single entry point.
                $comparison_warehouse->fetch_data( $required_data );

                // Get comparison data
                $comparison_data = $comparison_warehouse->get_data();
                $comparison_date_range_container = $comparison_warehouse->get_data_by_date_range_container();
                $comparison_data_warehouse_filter = $comparison_warehouse->get_filter();
                $total_db_records += $comparison_warehouse->get_total_db_records();
                $execution_time_comparison_report_entities = $comparison_warehouse->get_execution_time();

                // Add comparison execution times if set (sum in full float; rounded to 2 dec in final response).
                foreach( $execution_time_report_entities as $execution_time_report_entity => $execution_time_report_entity_value ) {
                    if ( isset($execution_time_comparison_report_entities[$execution_time_report_entity])) {
                        $execution_time_report_entities[$execution_time_report_entity] += $execution_time_comparison_report_entities[$execution_time_report_entity];
                    }
                }

                // Clear memory
                unset($comparison_warehouse);

                // Minimize and clean comparison data - skip in build mode
                $comparison_data = self::minimize_data($comparison_data, $required_entities);
                $comparison_data = self::clean_data($comparison_data, $comparison_date_range_container);

            }

            // Calculate Performance (execution times rounded to 2 dec in final response).
            $finish_time                = microtime(true);
            $execution_time             = $finish_time - $start_time;
            $memory_end                 = memory_get_usage(true);
            $memory_used                = $memory_end - $memory_start;
            $memory_peak                = memory_get_peak_usage(true);
            $memory_start_mb            = round($memory_start / 1024 / 1024, 4);
            $memory_end_mb              = round($memory_end / 1024 / 1024, 4);
            $memory_used_mb             = round($memory_used / 1024 / 1024, 4);
            $memory_peak_mb             = round($memory_peak / 1024 / 1024, 4);
            $memory_limit               = ini_get('memory_limit');
            $memory_usage_percentage    = wpdai_calculate_percentage( $memory_peak, wpdai_get_memory_limit( true ) );

            // Restore the original memory limit
            if ( $server_memory_limit ) @ini_set( 'memory_limit', $server_memory_limit );

            // Return response
            $response = array(
                'success' => true,
                'data' => $report_data,
                'comparison_data' => $comparison_data,
                'config' => $config, // Include the config in the response
                'timestamp' => current_time('timestamp'),
                'filters_applied' => $filters,
                'data_warehouse_filter' => $data_warehouse_filter,
                'comparison_data_warehouse_filter' => $comparison_data_warehouse_filter,
                'formatted_data_keys' => array_keys($report_data),
                'required_entities' => $required_entities,
                'errors' => $report_errors,
                'performance' => array(
                    'execution_time' => round( (float) $execution_time, 2 ),
                    'execution_time_report_entities' => array_map( function ( $v ) {
                        return is_numeric( $v ) ? round( (float) $v, 2 ) : $v;
                    }, $execution_time_report_entities ),
                    'memory_start' => $memory_start_mb,
                    'memory_end' => $memory_end_mb,
                    'memory_used' => $memory_used_mb,
                    'memory_peak' => $memory_peak_mb,
                    'memory_limit' => $memory_limit,
                    'original_memory_limit' => $server_memory_limit,
                    'memory_usage_percentage' => $memory_usage_percentage,
                    'total_db_records' => $total_db_records                
                )
            );

            return $response;

        } catch (Exception $e) {
            // Log the error
            self::log_error('WPDAI_Report_Builder: Error in get_live_dashboard_data_from_config: ' . $e->getMessage());
            self::log_error('WPDAI_Report_Builder: Error stack trace: ' . $e->getTraceAsString());
            
            // Return error response
            return array(
                'success' => false,
                'error' => 'Failed to fetch dashboard data: ' . $e->getMessage(),
                'config' => $config,
                'timestamp' => current_time('timestamp'),
            );
        }

    }

    /**
     *
     *  Clean data, sets up empty containers if required
     *
     */
    private static function clean_data( $data, $date_range_container ) {

        foreach( $data as $entity_type => $data_types ) {

            foreach( $data_types as $data_type => $metrics ) {

                if ( $data_type === 'data_by_date' ) {

                    foreach( $metrics as $metric => $value ) {

                        // Setup empty date containers
                        if ( ! is_array($value) || empty($value) ) $data[$entity_type][$data_type][$metric] = $date_range_container;

                    }

                } else if ( $data_type === 'categorized_data' ) {

                    foreach( $metrics as $metric => $value ) {

                        // Setup empty date containers
                        if ( ! is_array($value) || empty($value) ) $data[$entity_type][$data_type][$metric] = array( 'no_data_found' => 0 );

                    }   

                }

            }

        }

        return $data;
    }

    /**
     * 
     *  Return only the required data to the payload (currently only cuts out main entities so that we can hydrate those in request)
     *  This enables us to easily build widgets from the same data entity, rather than constantly re-requesting data from the API
     *  Non build momde can use a more hardcore version, but we need to update it to check for dependant variables, etc etc.
     *
     **/
    private static function minimize_data( $data, $required_entities ) {

        // Not correct format, so we won't process the data.
        if ( ! is_array($required_entities) ) return $data;

        // If empty, return nothing so we trigger our loaders.
        if ( empty($required_entities) ) return array();

        // Empty array
        $cleaned_array = array();

        // Loop through requirements
        foreach( $required_entities as $entity_type => $values ) {

            if ( ! isset($cleaned_array[$entity_type]) ) $cleaned_array[$entity_type] = $data[$entity_type];

            // foreach( $values as $data_type => $metrics ) {

            //     foreach( $metrics as $metric ) {

            //         self::log( sprintf( '%s, %s, %s', $entity_type, $data_type, $metric ) );

            //         // Setup entity
            //         if ( ! isset($cleaned_array[$entity_type]) ) $cleaned_array[$entity_type] = array();
            //         if ( ! isset($cleaned_array[$entity_type][$data_type]) ) $cleaned_array[$entity_type][$data_type] = array();
    
            //         // Build new array element
            //         $cleaned_array[$entity_type][$data_type][$metric] = $data[$entity_type][$data_type][$metric];

            //     }

            // }

        }

        // In case we fail
        if ( empty($cleaned_array) || ! is_array($cleaned_array) ) return $data;

        // Return final array
        return $cleaned_array;

    }

    /**
     * Get formula variable paths for a calculated metric
     * This maps calculated metrics to their dependent variables
     * 
     * @param string $entity Entity name (e.g., 'orders', 'google_campaigns')
     * @param string $metric Metric key (e.g., 'campaign_cost_per_click_by_date')
     * @return array|false Array of variable paths or false if not a formula metric
     */
    private static function get_formula_variables( $entity, $metric ) {
        // Define formula-based metrics and their dependencies
        // Format: 'entity' => ['metric_key' => ['variable_path1', 'variable_path2']]
        $formula_map = array(
            'orders' => array(
                'average_order_value_by_date' => array(
                    'orders.data_by_date.revenue_by_date',
                    'orders.data_by_date.order_count_by_date'
                ),
                'average_order_margin_by_date' => array(
                    'orders.data_by_date.profit_by_date',
                    'orders.data_by_date.revenue_excluding_tax_by_date'
                ),
                'order_margin_percentage_by_date' => array(
                    'orders.data_by_date.profit_by_date',
                    'orders.data_by_date.revenue_excluding_tax_by_date'
                ),
                'average_order_item_count_by_date' => array(
                    'orders.data_by_date.product_quantity_by_date',
                    'orders.data_by_date.order_count_by_date'
                ),
                'order_costs_per_order_by_date' => array(
                    'orders.data_by_date.order_costs_by_date',
                    'orders.data_by_date.order_count_by_date'
                ),
                'total_revenue_all_time' => array(
                    'orders.totals.total_revenue'
                ),
                'total_order_margin_percentage' => array(
                    'orders.totals.total_profit',
                    'orders.totals.total_revenue_excluding_tax'
                )
            ),
            'customers' => array(
                'average_order_value_per_customer_by_date' => array(
                    'orders.data_by_date.revenue_by_date',
                    'customers.data_by_date.customer_count_by_date'
                ),
                'customer_lifetime_value' => array(
                    'customers.totals.total_revenue',
                    'customers.totals.total_customers'
                )
            ),
            'analytics' => array(
                'conversion_rate_by_date' => array(
                    'analytics.data_by_date.transactions_by_date',
                    'analytics.data_by_date.sessions_by_date'
                )
            ),
            'google_campaigns' => array(
                'campaign_cost_per_click_by_date' => array(
                    'google_campaigns.data_by_date.campaign_spend_by_date',
                    'google_campaigns.data_by_date.campaign_clicks_by_date'
                ),
                'campaign_roas_by_date' => array(
                    'orders.data_by_date.revenue_by_date',
                    'google_campaigns.data_by_date.campaign_spend_by_date'
                ),
                'campaign_actual_roas_by_date' => array(
                    'google_campaigns.data_by_date.campaign_order_revenue_by_date',
                    'google_campaigns.data_by_date.campaign_spend_by_date'
                ),
                'campaign_cost_per_conversion_by_date' => array(
                    'google_campaigns.data_by_date.campaign_spend_by_date',
                    'google_campaigns.data_by_date.campaign_conversions_by_date'
                ),
                'campaign_cost_per_new_customer_by_date' => array(
                    'google_campaigns.data_by_date.campaign_spend_by_date',
                    'google_campaigns.data_by_date.campaign_new_customer_count_by_date'
                ),
                'campaign_cost_per_order_placed_by_date' => array(
                    'google_campaigns.data_by_date.campaign_spend_by_date',
                    'google_campaigns.data_by_date.campaign_order_count_by_date'
                ),
                'campaign_ctr_by_date' => array(
                    'google_campaigns.data_by_date.campaign_clicks_by_date',
                    'google_campaigns.data_by_date.campaign_impressions_by_date'
                ),
                'campaign_conversion_rate_by_date' => array(
                    'google_campaigns.data_by_date.campaign_order_count_by_date',
                    'google_campaigns.data_by_date.campaign_clicks_by_date'
                )
            ),
            'facebook_campaigns' => array(
                'campaign_cost_per_click_by_date' => array(
                    'facebook_campaigns.data_by_date.campaign_spend_by_date',
                    'facebook_campaigns.data_by_date.campaign_clicks_by_date'
                ),
                'campaign_roas_by_date' => array(
                    'orders.data_by_date.revenue_by_date',
                    'facebook_campaigns.data_by_date.campaign_spend_by_date'
                ),
                'campaign_actual_roas_by_date' => array(
                    'facebook_campaigns.data_by_date.campaign_order_revenue_by_date',
                    'facebook_campaigns.data_by_date.campaign_spend_by_date'
                ),
                'campaign_cost_per_new_customer_by_date' => array(
                    'facebook_campaigns.data_by_date.campaign_spend_by_date',
                    'facebook_campaigns.data_by_date.campaign_new_customer_count_by_date'
                ),
                'campaign_cost_per_order_placed_by_date' => array(
                    'facebook_campaigns.data_by_date.campaign_spend_by_date',
                    'facebook_campaigns.data_by_date.campaign_order_count_by_date'
                ),
                'campaign_ctr_by_date' => array(
                    'facebook_campaigns.data_by_date.campaign_clicks_by_date',
                    'facebook_campaigns.data_by_date.campaign_impressions_by_date'
                ),
                'campaign_conversion_rate_by_date' => array(
                    'facebook_campaigns.data_by_date.campaign_order_count_by_date',
                    'facebook_campaigns.data_by_date.campaign_clicks_by_date'
                )
            ),
            'products' => array(
                'average_product_price_by_date' => array(
                    'products.data_by_date.product_revenue_by_date',
                    'products.data_by_date.product_quantity_by_date'
                ),
                'product_margin_percentage_by_date' => array(
                    'products.data_by_date.product_profit_by_date',
                    'products.data_by_date.product_revenue_excluding_tax_by_date'
                )
            ),
            'store_profit' => array(
                'store_profit_margin_by_date' => array(
                    'store_profit.data_by_date.store_profit_by_date',
                    'orders.data_by_date.revenue_excluding_tax_by_date'
                ),
                'store_roas_by_date' => array(
                    'orders.data_by_date.revenue_by_date',
                    'store_profit.data_by_date.total_ad_spend_by_date'
                )
            )
        );
        
        // Check if this entity and metric has formula dependencies
        if ( isset( $formula_map[ $entity ] ) && isset( $formula_map[ $entity ][ $metric ] ) ) {
            return $formula_map[ $entity ][ $metric ];
        }
        
        return false;
    }

    /**
     *
     * Get the data table limits from the config
     * Scans rows to see if there are any data tables with a limit set, this si found with the max_records field
     * Max records will be null to use default or if unset, 0 for unlimited, or a specific number if set
     * 
     * @param array $config The config array
     * @return array The data table limits -> Returns an array with the entity name as the key and the limit as the value
     *
     */
    private static function get_data_table_limits_from_config( $config ) {

        $data_table_limits = array();

        foreach( $config['rows'] as $row ) {

            foreach( $row['columns'] as $column_properties ) {

                $widgets = $column_properties['widgets'];

                if ( ! is_array($widgets) || empty($widgets) ) continue;

                foreach( $widgets as $widget ) {

                    // Don't proceed if not a data table or no config
                    if ( ! isset($widget['config']) || $widget['type'] !== 'data_table' ) continue;

                    // Widget's config
                    $widget = $widget['config'];
                    
                    // If max records is set, add it to the data table limits
                    if ( isset($widget['max_records']) ) {
                        $entity_info = self::parse_metric_path( $widget['metric'] );
                        if ( $entity_info && is_numeric($widget['max_records']) ) {
                            $data_table_limits[$entity_info['entity']] = (int) $widget['max_records'];
                        }
                    }
                }
            }
        }

        return $data_table_limits;

    }

    /**
     *
     * Optimize data fetch, only calls the data that is required by this report
     *
     */
    private static function get_required_entities( $config ) {
        
        // Check that the report config is valid
        if ( ! is_array( $config ) || empty( $config ) ) {
            return array();
        }
        
        $required_entities = array();
        
        // Check if widgets exist in config
        if ( ! isset( $config['rows'] ) || ! is_array( $config['rows'] ) ) {
            return $required_entities;
        }
        
        // Loop through each widget
        foreach ( $config['rows'] as $row ) {
            foreach( $row['columns'] as $column_properties ) {
                $widgets = $column_properties['widgets'] ?? array();
                if ( ! is_array($widgets) || empty($widgets) ) continue;
                foreach( $widgets as $widget ) {
                    if ( ! isset($widget['config']) ) continue;
                    $widget = $widget['config'];

                    // Handle single metric configuration
                    if ( isset( $widget['metric'] ) && ! empty( $widget['metric'] ) ) {
                        $entity_info = self::parse_metric_path( $widget['metric'] );
                        if ( $entity_info ) {
                            // Check if this is a formula-based metric
                            $formula_variables = self::get_formula_variables( $entity_info['entity'], $entity_info['metric'] );
                            
                            if ( $formula_variables !== false ) {
                                // This is a calculated metric - add its dependencies instead
                                foreach ( $formula_variables as $variable_path ) {
                                    $variable_entity_info = self::parse_metric_path( $variable_path );
                                    if ( $variable_entity_info ) {
                                        self::add_entity_to_required( $required_entities, $variable_entity_info );
                                    }
                                }
                            } else {
                                // Regular metric - add it directly
                                self::add_entity_to_required( $required_entities, $entity_info );
                            }
                        }
                    }
                    
                    // Handle multiple metrics configuration
                    if ( isset( $widget['metrics'] ) && is_array( $widget['metrics'] ) ) {
                        foreach ( $widget['metrics'] as $metric_config ) {
                            if ( isset( $metric_config['metric'] ) && ! empty( $metric_config['metric'] ) ) {
                                $entity_info = self::parse_metric_path( $metric_config['metric'] );
                                if ( $entity_info ) {
                                    // Check if this is a formula-based metric
                                    $formula_variables = self::get_formula_variables( $entity_info['entity'], $entity_info['metric'] );
                                    
                                    if ( $formula_variables !== false ) {
                                        // This is a calculated metric - add its dependencies instead
                                        foreach ( $formula_variables as $variable_path ) {
                                            $variable_entity_info = self::parse_metric_path( $variable_path );
                                            if ( $variable_entity_info ) {
                                                self::add_entity_to_required( $required_entities, $variable_entity_info );
                                            }
                                        }
                                    } else {
                                        // Regular metric - add it directly
                                        self::add_entity_to_required( $required_entities, $entity_info );
                                    }
                                }
                            }
                        }
                    }
                    
                }
            }
        }
        
        return $required_entities;
    }

    /**
     * Calculate actual dates from presets
     * 
     * @param array $filters The filters array that may contain presets
     * @return array The filters with calculated dates
     */
    private static function calculate_dates_from_presets( $filters ) {
        $calculated_filters = $filters;
        
        // Handle date preset
        if (isset($filters['date_preset']) && !empty($filters['date_preset'])) {
            $date_dates = self::get_dates_from_preset($filters['date_preset']);
            if ($date_dates) {
                $calculated_filters['date_from'] = $date_dates['from'];
                $calculated_filters['date_to'] = $date_dates['to'];
            }
        }
        
        // Handle comparison preset
        if (isset($filters['comparison_date_selection']) && !empty($filters['comparison_date_selection'])) {
            $comparison_dates = self::get_comparison_dates_from_preset($filters['comparison_date_selection'], $calculated_filters['date_from'], $calculated_filters['date_to']);
            if ($comparison_dates) {
                $calculated_filters['comparison_date_from'] = $comparison_dates['from'];
                $calculated_filters['comparison_date_to'] = $comparison_dates['to'];
            }
        }
        
        return $calculated_filters;
    }
    
    /**
     * Get dates from a preset
     * 
     * @param string $preset The preset name
     * @return array|false Array with 'from' and 'to' dates or false if invalid
     */
    private static function get_dates_from_preset( $preset ) {

        return wpdai_get_dates_from_preset( $preset );

    }
    
    /**
      * Get comparison dates from preset
      * 
      * @param string $preset The comparison preset name
      * @param string $date_from The main date from
      * @param string $date_to The main date to
      * @return array|false Array with 'from' and 'to' dates or false if invalid
      */
     private static function get_comparison_dates_from_preset( $preset, $date_from, $date_to ) {
         if (empty($date_from) || empty($date_to)) {
             return false;
         }
         
         $from_date = new DateTime($date_from, wp_timezone());
         $to_date = new DateTime($date_to, wp_timezone());
         
         switch ($preset) {
             case 'previous_period':
                 // Check if the main period is a month-based, week-based, or month-to-date preset
                 $is_month_based = self::is_month_based_period($date_from, $date_to);
                 $is_week_based = self::is_week_based_period($date_from, $date_to);
                 $is_month_to_date = self::is_month_to_date_period($date_from, $date_to);
                 
                 if ($is_month_based) {
                     // For month-based periods, get the same period in the previous month
                     $comparison_from = clone $from_date;
                     $comparison_from->modify('-1 month');
                     
                     $comparison_to = clone $comparison_from;
                     $comparison_to->modify('last day of this month');
                     
                     return array(
                         'from' => $comparison_from->format('Y-m-d'),
                         'to' => $comparison_to->format('Y-m-d')
                     );
                 } else if ($is_month_to_date) {
                     // For month-to-date periods, get the same period in the previous month
                     $comparison_from = clone $from_date;
                     $comparison_from->modify('-1 month');
                     
                     // Get the equivalent date in the previous month, but don't exceed the last day of that month
                     $target_day = $to_date->format('j'); // Day of month without leading zeros
                     $last_day_of_prev_month = $comparison_from->format('t'); // Number of days in the previous month
                     $comparison_day = min($target_day, $last_day_of_prev_month);
                     
                     $comparison_to = clone $comparison_from;
                     $comparison_to->setDate($comparison_from->format('Y'), $comparison_from->format('n'), $comparison_day);
                     
                     return array(
                         'from' => $comparison_from->format('Y-m-d'),
                         'to' => $comparison_to->format('Y-m-d')
                     );
                 } else if ($is_week_based) {
                     // For week-based periods, get the same Monday-Sunday period in the previous week
                     $comparison_from = clone $from_date;
                     $comparison_from->modify('-7 days');
                     
                     $comparison_to = clone $to_date;
                     $comparison_to->modify('-7 days');
                     
                     return array(
                         'from' => $comparison_from->format('Y-m-d'),
                         'to' => $comparison_to->format('Y-m-d')
                     );
                 } else {
                    // For day-based periods, use the same number of days
                    $date_range = $from_date->diff($to_date)->days + 1;
                    
                    // Special handling for single-day periods (like "Today")
                    if ($date_range === 1) {
                        // For single day, compare to the previous day
                        $comparison_from = clone $from_date;
                        $comparison_from->modify('-1 day');
                        $comparison_to = clone $from_date;
                        $comparison_to->modify('-1 day');
                        
                        return array(
                            'from' => $comparison_from->format('Y-m-d'),
                            'to' => $comparison_to->format('Y-m-d')
                        );
                    }
                    
                    $comparison_from = clone $from_date;
                    $comparison_from->modify("-{$date_range} days");
                    $comparison_to = clone $comparison_from;
                    $comparison_to->modify("+{$date_range} days -1 day");
                    
                    return array(
                        'from' => $comparison_from->format('Y-m-d'),
                        'to' => $comparison_to->format('Y-m-d')
                    );
                }
                 
             case 'previous_year':
                 // Same period last year
                 $comparison_from = clone $from_date;
                 $comparison_from->modify('-1 year');
                 $comparison_to = clone $to_date;
                 $comparison_to->modify('-1 year');
                 
                 return array(
                     'from' => $comparison_from->format('Y-m-d'),
                     'to' => $comparison_to->format('Y-m-d')
                 );
             default:
                 return false;
         }
     }
     
     /**
      * Check if a date range represents a month-based period
      * 
      * @param string $date_from The start date
      * @param string $date_to The end date
      * @return bool True if it's a month-based period
      */
     private static function is_month_based_period( $date_from, $date_to ) {
         $from_date = new DateTime($date_from, wp_timezone());
         $to_date = new DateTime($date_to, wp_timezone());
         
         // Check if it's from the first day to the last day of the same month
         $first_day_of_month = clone $from_date;
         $first_day_of_month->modify('first day of this month');
         
         $last_day_of_month = clone $from_date;
         $last_day_of_month->modify('last day of this month');
         
         return $from_date->format('Y-m-d') === $first_day_of_month->format('Y-m-d') &&
                $to_date->format('Y-m-d') === $last_day_of_month->format('Y-m-d');
     }

     /**
      * Check if a date range represents a week-based period (Monday to Sunday)
      * 
      * @param string $date_from The start date
      * @param string $date_to The end date
      * @return bool True if it's a week-based period
      */
     private static function is_week_based_period( $date_from, $date_to ) {
         $from_date = new DateTime($date_from, wp_timezone());
         $to_date = new DateTime($date_to, wp_timezone());
         
         $from_day = $from_date->format('w'); // 0 = Sunday, 1 = Monday, etc.
         $to_day = $to_date->format('w');
         $days_diff = $from_date->diff($to_date)->days;
         
         // Check if it's exactly 6 days (Monday to Sunday) and starts on Monday
         return $from_day == 1 && $to_day == 0 && $days_diff == 6;
     }

     /**
      * Check if a date range represents a month-to-date period (1st of month to current date)
      * 
      * @param string $date_from The start date
      * @param string $date_to The end date
      * @return bool True if it's a month-to-date period
      */
     private static function is_month_to_date_period( $date_from, $date_to ) {
         $from_date = new DateTime($date_from, wp_timezone());
         $to_date = new DateTime($date_to, wp_timezone());
         
         $from_day = $from_date->format('j'); // Day of month without leading zeros
         $from_month = $from_date->format('n'); // Month without leading zeros
         $from_year = $from_date->format('Y');
         $to_month = $to_date->format('n');
         $to_year = $to_date->format('Y');
         
         // Check if it starts on the 1st of the month and ends on a date in the same month
         // and the end date is not the last day of the month
         $is_first_day = $from_day == 1;
         $is_same_month = $from_month == $to_month && $from_year == $to_year;
         
         // Get the last day of the month
         $last_day_of_month = $to_date->format('t'); // Number of days in the month
         $is_not_last_day = $to_date->format('j') != $last_day_of_month;
         
         return $is_first_day && $is_same_month && $is_not_last_day;
     }

    /**
     *
     * Log a message
     *
     */
    public static function log( $log ) {
        wpdai_write_log( $log, 'report' );
    }

    /**
     *
     * Log an error
     *
     */
    public static function log_error( $log ) {
        wpdai_write_log( $log, 'report_error' );
    }

    /**
     * Parse a metric path to extract entity, data type, and metric information
     * 
     * @param string $metric_path The metric path (e.g., "orders.data_by_date.revenue_by_date")
     * @return array|false Array with entity, data_type, and metric keys, or false if invalid
     */
    private static function parse_metric_path( $metric_path ) {
        if ( empty( $metric_path ) ) {
            return false;
        }
        
        // Handle unified dot notation format: "entity.data_type.metric"
        if ( strpos( $metric_path, '.' ) !== false ) {
            $parts = explode( '.', $metric_path );
            if ( count( $parts ) >= 3 ) {
                return array(
                    'entity' => $parts[0],
                    'data_type' => $parts[1],
                    'metric' => $parts[2]
                );
            }
        }
        
        // Handle legacy format: "entity:metric"
        if ( strpos( $metric_path, ':' ) !== false ) {
            $parts = explode( ':', $metric_path );
            if ( count( $parts ) >= 2 ) {
                return array(
                    'entity' => $parts[0],
                    'data_type' => 'data_by_date', // Default for legacy format
                    'metric' => $parts[1]
                );
            }
        }
        
        // Handle simple metric name (fallback)
        return array(
            'entity' => 'orders', // Default entity
            'data_type' => 'data_by_date', // Default data type
            'metric' => $metric_path
        );
    }
    
    /**
     * Add entity information to the required entities array
     * 
     * @param array &$required_entities Reference to the required entities array
     * @param array $entity_info Array with entity, data_type, and metric information
     */
    private static function add_entity_to_required( &$required_entities, $entity_info ) {
        $entity = $entity_info['entity'];
        $data_type = $entity_info['data_type'];
        $metric = $entity_info['metric'];
        
        // Initialize entity if it doesn't exist
        if ( ! isset( $required_entities[ $entity ] ) ) {
            $required_entities[ $entity ] = array();
        }
        
        // Initialize data type if it doesn't exist
        if ( ! isset( $required_entities[ $entity ][ $data_type ] ) ) {
            $required_entities[ $entity ][ $data_type ] = array();
        }
        
        // Add metric if it's not already included
        if ( ! in_array( $metric, $required_entities[ $entity ][ $data_type ] ) ) {
            $required_entities[ $entity ][ $data_type ][] = $metric;
        }
    }

    /**
     * Core method for getting default reports
     *
     * @since 4.7.0
     *
     * @return array Response array containing all available default reports with installation status
     */
    public function get_default_reports() {
        $response = array();

        try {
            // Get the reports directory path
            $reports_dir = WPD_AI_PATH . 'includes/reports/';
            
            if ( ! is_dir( $reports_dir ) ) {
                throw new Exception( 'Reports directory not found' );
            }

            // Get all JSON files in the reports directory
            $json_files = glob( $reports_dir . '*.json' );
            
            if ( empty( $json_files ) ) {
                throw new Exception( 'No default reports found' );
            }

            // Get currently installed reports
            $installed_reports = $this->get_installed_report_slugs();

            $default_reports = array();

            foreach ( $json_files as $file_path ) {
                $filename = basename( $file_path );
                $slug = str_replace( array( 'dashboard-config-', '.json' ), '', $filename );
                
                // Read and parse the JSON file
                $json_content = file_get_contents( $file_path );
                if ( $json_content === false ) {
                    continue;
                }

                $report_data = json_decode( $json_content, true );
                if ( json_last_error() !== JSON_ERROR_NONE ) {
                    continue;
                }

                // Get the actual dashboard_id from the JSON file
                $actual_dashboard_id = isset( $report_data['dashboard_id'] ) ? $report_data['dashboard_id'] : $slug;
                
                // Check if this report is currently installed by looking at the actual report data
                $is_installed = false;
                if ( in_array( $actual_dashboard_id, $installed_reports ) ) {
                    $is_installed = true;
                }

                $default_reports[] = array(
                    'slug' => $actual_dashboard_id,
                    'name' => isset( $report_data['name'] ) ? $report_data['name'] : ucwords( str_replace( '-', ' ', $actual_dashboard_id ) ),
                    'category' => isset( $report_data['report_category'] ) ? $report_data['report_category'] : 'sales_reports',
                    'version' => isset( $report_data['version_number'] ) ? $report_data['version_number'] : '1.0',
                    'icon' => isset( $report_data['icon'] ) ? $report_data['icon'] : 'bar_chart',
                    'color' => isset( $report_data['color'] ) ? $report_data['color'] : 'blue',
                    'is_installed' => $is_installed,
                    'file_path' => $file_path
                );
            }

            $response['success'] = true;
            $response['data'] = $default_reports;

        } catch ( Exception $e ) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
        }

        return $response;
    }


    /**
     * Auto-install a mandatory report if it doesn't exist
     * This is called when a user tries to view a mandatory report that hasn't been installed
     *
     * @since 4.7.0
     *
     * @param string $report_slug The slug of the mandatory report to auto-install
     * @return array Response array indicating success or failure
     */
    public function auto_install_mandatory_report($report_slug) {
        $response = array();
        
        if ( empty( $report_slug ) ) {
            $response['success'] = false;
            $response['message'] = __( 'Report slug is required.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
            return $response;
        }

        // Verify this is actually a mandatory report
        if ( ! in_array( $report_slug, self::$mandatory_report_slugs ) ) {
            $response['success'] = false;
            $response['message'] = __( 'This report is not a mandatory report.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
            return $response;
        }

        try {
            // Find the report file by searching through all JSON files for matching dashboard_id
            $reports_dir = WPD_AI_PATH . 'includes/reports/';
            
            if ( ! is_dir( $reports_dir ) ) {
                throw new Exception( 'Reports directory not found' );
            }

            $json_files = glob( $reports_dir . '*.json' );
            $report_file = null;
            $report_data = null;

            foreach ( $json_files as $file_path ) {
                $json_content = file_get_contents( $file_path );
                if ( $json_content === false ) {
                    continue;
                }

                $data = json_decode( $json_content, true );
                if ( json_last_error() !== JSON_ERROR_NONE ) {
                    continue;
                }

                if ( isset( $data['dashboard_id'] ) && $data['dashboard_id'] === $report_slug ) {
                    $report_file = $file_path;
                    $report_data = $data;
                    break;
                }
            }

            if ( ! $report_file || ! $report_data ) {
                throw new Exception( 'Mandatory report configuration file not found: ' . $report_slug );
            }

            // Import the report configuration (overwrite if exists)
            $option_name = 'wpd_dashboard_config_' . $report_slug;
            $saved = update_option( $option_name, $report_data, false );

            if ( $saved !== false ) {
                $response['success'] = true;
                $response['message'] = sprintf( 
                    /* translators: %s: Report name or slug */
                    __( 'Mandatory report "%s" has been automatically installed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                    $report_data['name'] ?? $report_slug
                );
                $response['data'] = array(
                    'report_slug' => $report_slug,
                    'report_name' => $report_data['name'] ?? $report_slug,
                    'auto_installed' => true
                );
            } else {
                throw new Exception( 'Failed to save mandatory report configuration' );
            }

        } catch ( Exception $e ) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
        }

        return $response;
    }

    /**
     * Core method for importing default report
     *
     * @since 4.7.0
     *
     * @param string $report_slug The slug of the report to import
     * @return array Response array indicating success or failure
     */
    public function import_default_report($report_slug) {
        $response = array();
        
        if ( empty( $report_slug ) ) {
            $response['success'] = false;
            $response['message'] = __( 'Report slug is required.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
            return $response;
        }

        try {
            // Find the report file by searching through all JSON files for matching dashboard_id
            $reports_dir = WPD_AI_PATH . 'includes/reports/';
            
            if ( ! is_dir( $reports_dir ) ) {
                throw new Exception( 'Reports directory not found' );
            }

            $json_files = glob( $reports_dir . '*.json' );
            $report_file = null;
            $report_data = null;

            foreach ( $json_files as $file_path ) {
                $json_content = file_get_contents( $file_path );
                if ( $json_content === false ) {
                    continue;
                }

                $data = json_decode( $json_content, true );
                if ( json_last_error() !== JSON_ERROR_NONE ) {
                    continue;
                }

                if ( isset( $data['dashboard_id'] ) && $data['dashboard_id'] === $report_slug ) {
                    $report_file = $file_path;
                    $report_data = $data;
                    break;
                }
            }

            if ( ! $report_file || ! $report_data ) {
                throw new Exception( 'Report not found in default reports' );
            }

            // Check if report already exists
            $existing_config = get_option( 'wpd_dashboard_config_' . $report_slug );
            if ( $existing_config !== false ) {
                throw new Exception( 'Report already exists. Please delete the existing report first.' );
            }

            // Import the report configuration
            $option_name = 'wpd_dashboard_config_' . $report_slug;
            $saved = update_option( $option_name, $report_data, false );

            if ( $saved !== false ) {
                $response['success'] = true;
                $response['message'] = __( 'Report imported successfully!', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
                $response['data'] = array(
                    'report_slug' => $report_slug,
                    'report_name' => $report_data['name'] ?? $report_slug
                );
            } else {
                throw new Exception( 'Failed to save report configuration' );
            }

        } catch ( Exception $e ) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
        }

        return $response;
    }

    /**
     * Core method for resetting a default report
     *
     * @since 4.7.0
     *
     * @param string $report_slug The slug of the report to reset to default
     * @return array Response array indicating success or failure
     */
    public function reset_default_report($report_slug) {
        $response = array();

        if ( empty( $report_slug ) ) {
            $response['success'] = false;
            $response['message'] = __( 'Report slug is required.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
            return $response;
        }

        try {
            // Find the report file by searching through all JSON files for matching dashboard_id
            $reports_dir = WPD_AI_PATH . 'includes/reports/';
            $json_files = glob( $reports_dir . '*.json' );
            $report_file = null;
            
            foreach ( $json_files as $file_path ) {
                $json_content = file_get_contents( $file_path );
                if ( $json_content === false ) {
                    continue;
                }
                
                $report_data = json_decode( $json_content, true );
                if ( $report_data && isset( $report_data['dashboard_id'] ) && $report_data['dashboard_id'] === $report_slug ) {
                    $report_file = $file_path;
                    break;
                }
            }
            
            if ( ! $report_file || ! file_exists( $report_file ) ) {
                throw new Exception( 'Default report file not found for dashboard_id: ' . $report_slug );
            }

            // Read and parse the JSON file
            $json_content = file_get_contents( $report_file );
            if ( $json_content === false ) {
                throw new Exception( 'Could not read report file' );
            }

            $report_data = json_decode( $json_content, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                throw new Exception( 'Invalid JSON in report file' );
            }

            // Use the dashboard_id from the JSON config, not the filename slug
            $actual_dashboard_id = isset( $report_data['dashboard_id'] ) ? $report_data['dashboard_id'] : $report_slug;

            // Set timestamps and ensure report_category, version_number, appear_in_menu, icon, and color are set
            $report_data['created_at'] = current_time( 'timestamp' );
            $report_data['updated_at'] = current_time( 'timestamp' );
            if ( ! isset( $report_data['report_category'] ) ) {
                $report_data['report_category'] = 'sales_reports';
            }
            if ( ! isset( $report_data['version_number'] ) ) {
                $report_data['version_number'] = '1.0';
            }
            if ( ! isset( $report_data['appear_in_menu'] ) ) {
                $report_data['appear_in_menu'] = true;
            }
            if ( ! isset( $report_data['icon'] ) ) {
                $report_data['icon'] = 'bar_chart';
            }
            if ( ! isset( $report_data['color'] ) ) {
                $report_data['color'] = 'blue';
            }

            // Save the report using the actual dashboard_id from the config (this will overwrite any existing customizations) with autoload=false for better performance
            $option_name = 'wpd_dashboard_config_' . $actual_dashboard_id;
            $saved = update_option( $option_name, $report_data, false );
            
            if ( $saved !== false ) {
                $response['success'] = true;
                $response['message'] = __( 'Report reset to default successfully!', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
                $response['data'] = array(
                    'report_slug' => $actual_dashboard_id,
                    'report_name' => $report_data['name']
                );
            } else {
                throw new Exception( 'Failed to reset report' );
            }

        } catch ( Exception $e ) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
        }

        return $response;
    }


    /**
     * Core method for saving report config
     *
     * @since 4.7.0
     *
     * @param string $report_slug The unique slug identifying the report
     * @param array $config_data The configuration data to save
     * @return array Response array indicating success or failure
     */
    public function save_report_config($report_slug, $config_data) {
        $response = array();

        try {
            if ( empty( $report_slug ) || empty( $config_data ) ) {
                throw new Exception( 'Missing required parameters' );
            }

            // Save the configuration with autoload=false for better performance
            $option_name = 'wpd_dashboard_config_' . $report_slug;
            $existing_config = get_option( $option_name, null );
            
            // Check if the config is the same as existing
            if ( $existing_config === $config_data ) {
                // Config hasn't changed, but this is still a successful operation
                $response['success'] = true;
                $response['message'] = __( 'Report configuration is already up to date!', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
            } else {
                // Config has changed, save it
                $saved = update_option( $option_name, $config_data, false );
                
                if ( $saved !== false ) {
                    // update_option returns false only if the value is the same or if there's an error
                    // Since we already checked for same value above, this should be successful
                    $response['success'] = true;
                    $response['message'] = __( 'Report configuration saved successfully!', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
                } else {
                    throw new Exception( 'Failed to save report configuration' );
                }
            }

        } catch ( Exception $e ) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
        }

        return $response;
    }

    /**
     *
     * Helper method to get all installed reports with their data
     *
     */
    private function get_all_installed_reports() {
        global $wpdb;
        // Use direct DB query for autoload=false options
        $dashboard_configs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                'wpd_dashboard_config_%'
            )
        );

        $installed_reports = array();
        if ( $dashboard_configs ) {
            foreach ( $dashboard_configs as $config ) {
                // Parse the report data (handle both serialized and JSON data)
                $report_data = maybe_unserialize( $config->option_value );
                if ( $report_data && is_array( $report_data ) ) {
                    if ( isset( $report_data['dashboard_id'] ) ) {
                        // Include all reports that have a dashboard_id, regardless of option key
                        $installed_reports[] = $report_data;
                    }
                }
            }
        }

        return $installed_reports;
    }

    /**
     *
     * Helper method to get installed report slugs
     *
     */
    private function get_installed_report_slugs() {
        global $wpdb;
        // Use direct DB query for autoload=false options
        $dashboard_configs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                'wpd_dashboard_config_%'
            )
        );

        $slugs = array();
        if ( $dashboard_configs ) {
            foreach ( $dashboard_configs as $config ) {
                // Parse the report data to get the actual slug from the configuration
                $report_data = json_decode( $config->option_value, true );
                if ( $report_data && isset( $report_data['dashboard_id'] ) ) {
                    $actual_slug = $report_data['dashboard_id'];
                    // Include all dashboard_ids from installed reports
                    $slugs[] = $actual_slug;
                }
            }
        }

        return $slugs;
    }

    /**
     * Core method to import all available default reports
     *
     * Server-side version that returns array instead of JSON response.
     *
     * @since 4.7.0
     *
     * @param bool $override If true, overwrites existing reports. If false, skips already installed reports.
     * @return array Result array with success status, message, and details
     */
    public static function import_all_default_reports( $override = true ) {
        $response = array();

        try {
            // Get the reports directory path
            $reports_dir = WPD_AI_PATH . 'includes/reports/';
            
            if ( ! is_dir( $reports_dir ) ) {
                throw new Exception( 'Reports directory not found' );
            }

            // Get all JSON files in the reports directory
            $json_files = glob( $reports_dir . '*.json' );
            
            if ( empty( $json_files ) ) {
                throw new Exception( 'No default reports found' );
            }

            $imported_count = 0;
            $skipped_count = 0;
            $errors = array();
            $imported_reports = array();
            $skipped_reports = array();

            // Check for WC Subscriptions
            $wc_subscriptions = wpdai_is_wc_subscriptions_active();

            foreach ( $json_files as $file_path ) {
                try {
                    // Read and parse the JSON file
                    $json_content = file_get_contents( $file_path );
                    if ( $json_content === false ) {
                        $errors[] = 'Could not read file: ' . basename( $file_path );
                        continue;
                    }

                    $report_data = json_decode( $json_content, true );
                    if ( json_last_error() !== JSON_ERROR_NONE ) {
                        $errors[] = 'Invalid JSON in file: ' . basename( $file_path );
                        continue;
                    }

                    if ( ! isset( $report_data['dashboard_id'] ) ) {
                        $errors[] = 'Missing dashboard_id in file: ' . basename( $file_path );
                        continue;
                    }

                    $dashboard_id = sanitize_text_field( $report_data['dashboard_id'] );
                    
                    // Check if the report is a subscription report and WC Subscriptions is active
                    if ( ! $wc_subscriptions && strpos( $dashboard_id, 'subscription' ) !== false ) {
                        continue;
                    }

                    // Check if report already exists and override is false
                    if ( ! $override ) {
                        $existing_config = get_option( 'wpd_dashboard_config_' . $dashboard_id );
                        if ( $existing_config !== false ) {
                            // Report already exists, skip it
                            $skipped_count++;
                            $skipped_reports[] = $dashboard_id;
                            continue;
                        }
                    }

                    // Import the report using the existing import logic
                    self::import_single_default_report( $dashboard_id, $report_data );
                    $imported_count++;
                    $imported_reports[] = $dashboard_id;
                    
                } catch ( Exception $e ) {
                    $errors[] = 'Error importing ' . basename( $file_path ) . ': ' . $e->getMessage();
                }
            }

            if ( $imported_count > 0 || $skipped_count > 0 ) {
                $response['success'] = true;
                
                // Build message based on imported and skipped counts
                if ( $imported_count > 0 && $skipped_count > 0 ) {
                    $response['message'] = sprintf( 
                        /* translators: 1: Number of imported reports, 2: Number of skipped reports */
                        __( 'Successfully imported %1$d reports. %2$d reports were already installed and skipped.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 
                        $imported_count,
                        $skipped_count
                    );
                } elseif ( $imported_count > 0 ) {
                    $response['message'] = sprintf( 
                        /* translators: %d: Number of imported reports */
                        __( 'Successfully imported %d default reports.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 
                        $imported_count 
                    );
                } else {
                    $response['message'] = sprintf( 
                        /* translators: %d: Number of skipped reports */
                        __( 'All %d reports were already installed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 
                        $skipped_count 
                    );
                }
                
                $response['imported_count'] = $imported_count;
                $response['imported_reports'] = $imported_reports;
                $response['skipped_count'] = $skipped_count;
                $response['skipped_reports'] = $skipped_reports;
                
                if ( ! empty( $errors ) ) {
                    $response['warnings'] = $errors;
                }
            } else {
                $response['success'] = false;
                $response['message'] = __( 'No reports could be imported.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
                $response['imported_count'] = 0;
                $response['imported_reports'] = array();
                $response['skipped_count'] = 0;
                $response['skipped_reports'] = array();
                if ( ! empty( $errors ) ) {
                    $response['errors'] = $errors;
                }
            }

        } catch ( Exception $e ) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['imported_count'] = 0;
            $response['imported_reports'] = array();
        }

        return $response;
    }

    /**
     *
     * Helper method to import a single default report
     *
     */
    public static function import_single_default_report( $dashboard_id, $report_data ) {
        // Set timestamps and ensure required fields are set (same as individual import)
        $report_data['created_at'] = current_time( 'timestamp' );
        $report_data['updated_at'] = current_time( 'timestamp' );
        if ( ! isset( $report_data['report_category'] ) ) {
            $report_data['report_category'] = 'sales_reports';
        }
        if ( ! isset( $report_data['version_number'] ) ) {
            $report_data['version_number'] = '1.0';
        }
        if ( ! isset( $report_data['appear_in_menu'] ) ) {
            $report_data['appear_in_menu'] = true;
        }
        if ( ! isset( $report_data['realtime_data'] ) ) {
            $report_data['realtime_data'] = false;
        }

        // Use the same option name pattern as the individual import method with autoload=false for better performance
        $saved = update_option( 'wpd_dashboard_config_' . $dashboard_id, $report_data, false );
        
        if ( ! $saved ) {
            throw new Exception( 'Failed to save report: ' . esc_html( $dashboard_id ) );
        }
    }

    /**
     * Import a report from JSON data
     *
     * @since 4.8.0
     *
     * @param array $report_data The report data from JSON
     * @param bool $overwrite Whether to overwrite existing report
     * @return array Response array with success status and message
     */
    public function import_json_report( $report_data, $overwrite = false ) {
        $response = array();

        try {
            // Validate required fields
            if ( empty( $report_data['dashboard_id'] ) ) {
                throw new Exception( 'Report dashboard_id is required' );
            }

            $dashboard_id = sanitize_text_field( $report_data['dashboard_id'] );
            
            // Check if report already exists
            $existing_report = get_option( 'wpd_dashboard_config_' . $dashboard_id );
            
            if ( $existing_report && ! $overwrite ) {
                $response['success'] = false;
                $response['message'] = sprintf( 
                    /* translators: %s: Report dashboard ID/slug */
                    __( 'Report with slug "%s" already exists. Please enable overwrite to replace it.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 
                    $dashboard_id 
                );
                return $response;
            }

            // Dashboard ID is already set from the JSON, just ensure it's sanitized
            $report_data['dashboard_id'] = $dashboard_id;
            
            // Set timestamps
            if ( $existing_report && $overwrite ) {
                // Preserve created_at if it exists
                $report_data['created_at'] = $existing_report['created_at'] ?? current_time( 'timestamp' );
                $report_data['updated_at'] = current_time( 'timestamp' );
            } else {
                $report_data['created_at'] = current_time( 'timestamp' );
                $report_data['updated_at'] = current_time( 'timestamp' );
            }
            
            // Set default values for optional fields
            if ( ! isset( $report_data['report_category'] ) ) {
                $report_data['report_category'] = 'sales_reports';
            }
            if ( ! isset( $report_data['version_number'] ) ) {
                $report_data['version_number'] = '1.0';
            }
            if ( ! isset( $report_data['appear_in_menu'] ) ) {
                $report_data['appear_in_menu'] = true;
            }
            if ( ! isset( $report_data['realtime_data'] ) ) {
                $report_data['realtime_data'] = false;
            }

            // Save the report with autoload=false for better performance
            $saved = update_option( 'wpd_dashboard_config_' . $dashboard_id, $report_data, false );
            
            if ( ! $saved && ! $existing_report ) {
                throw new Exception( 'Failed to save report' );
            }

            $response['success'] = true;
            $response['message'] = $overwrite 
                ? __( 'Report successfully imported and existing report was overwritten.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
                : __( 'Report successfully imported.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
            $response['dashboard_id'] = $dashboard_id;
            if ( isset( $report_data['name'] ) ) {
                $response['report_name'] = $report_data['name'];
            }
            
        } catch ( Exception $e ) {
            $response['success'] = false;
            /* translators: %s: Error message */
            $response['message'] = sprintf( __( 'Error importing report: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $e->getMessage() ) );
        }

        return $response;
    }
    
    /**
     * Validate live share authentication
     */
    public static function validate_live_share_auth($live_share_auth) {
        // Parse the live share auth (format: report_slug:secret_key)
        $parts = explode(':', $live_share_auth);
        if (count($parts) !== 2) {
            return false;
        }
        
        $report_slug = $parts[0];
        $secret_key = $parts[1];
        
        // Get report configuration
        $report_config = get_option('wpd_dashboard_config_' . $report_slug);
        
        if (!$report_config) {
            return false;
        }
        
        // Check if secret key exists in live share links
        if (!isset($report_config['live_share_links'])) {
            return false;
        }
        
        foreach ($report_config['live_share_links'] as $link) {
            if ($link['secret_key'] === $secret_key) {
                // Check if link has expired
                if (!empty($link['expiry_date'])) {
                    // Create DateTime objects in WordPress timezone
                    $expiry_date = new DateTime($link['expiry_date'], new DateTimeZone(wp_timezone_string()));
                    $current_date = new DateTime('now', new DateTimeZone(wp_timezone_string()));
                    
                    if ($current_date > $expiry_date) {
                        return false; // Link expired
                    }
                }
                
                return true; // Valid live share link
            }
        }
        
        return false; // Secret key not found
    }

    /**
     * Check if config has the required live share structure.
     * Used when processing requests authenticated via the live share nonce.
     *
     * @param array $config Report configuration.
     * @return bool True if config has valid live_share_links structure.
     */
    public static function config_has_valid_live_share_structure( $config ) {
        $live_share_links = isset( $config['live_share_links'] ) ? $config['live_share_links'] : null;
        if ( ! is_array( $live_share_links ) || empty( $live_share_links ) ) {
            return false;
        }
        foreach ( $live_share_links as $link ) {
            if ( ! is_array( $link ) || empty( $link['id'] ) || empty( $link['secret_key'] ) ) {
                return false;
            }
        }
        return true;
    }

}