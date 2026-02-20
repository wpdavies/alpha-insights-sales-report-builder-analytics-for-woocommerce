<?php
/**
 *
 * Plugin Name:         Alpha Insights - Sales Report Builder & Analytics For WooCommerce
 * Plugin URI:          https://wpdavies.dev/plugins/alpha-insights/
 * Description:         Track your store's profit & loss, cost of goods, expenses & website traffic. Build custom WooCommerce reports using our advanced drag & drop report builder. <a href="https://wpdavies.dev/plugins/alpha-insights/pricing/?utm_source=wordpress&utm_medium=plugin_description&utm_campaign=alpha_insights_free_upgrade&utm_content=upgrade_to_pro_link" target="_blank">Upgrade to Pro</a> for additional features.
 * Author:              WP Davies
 * Author URI:          https://wpdavies.dev/
 *
 * Version:             	1.1.0
 * Requires at least:   	5.0
 * Tested up to:        	6.9
 * Requires PHP: 			7.4
 * Requires Plugins: 		woocommerce
 * WC requires at least: 	3.0
 * WC tested up to: 		10.5
 *
 * License:             GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Text Domain:         alpha-insights-sales-report-builder-analytics-for-woocommerce
 * Domain Path: 		/languages
 *
 * Alpha Insights
 * Copyright (C) 2026, WP Davies, support@wpdavies.dev
 *
  * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category            Plugin
 * @copyright           Copyright WP Davies © 2026
 * @author              WP Davies
 * @package             Alpha Insights
 * @textdomain 			alpha-insights-pro
 *
 */
defined( 'ABSPATH' ) || exit;

class WPD_Alpha_Insights_Free_Plugin {

	/**
	 * Singleton instance
	 *
	 * @var WPD_Alpha_Insights_Free_Plugin
	 */
	private static $instance = null;

	/**
	 * Stores bool for whether or not we've passed the version check
	 *
	 * @var bool
	 */
	public $version_check = true;

	/**
	 * Stores the cached compatibility check results to avoid running checks multiple times
	 *
	 * @var array|null
	 */
	private $compatibility_check_result = null;

	/**
	 * Get singleton instance
	 *
	 * @return WPD_Alpha_Insights_Free_Plugin
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;

	}

	/**
	 * Constructor (private for singleton)
	 */
	private function __construct() {

		// Setup Definitions -> Must be first
		$this->define_constants();

		// Check for conflicts with free version (must be early)
		$this->check_for_conflicting_plugins();

		// Ensure log directory exists early
		$this->ensure_log_directory();

		// Register shutdown function to catch fatal/critical errors
		register_shutdown_function( array( $this, 'log_fatal_errors' ) );

		// Add links to plugin page
		add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'alpha_insights_plugin_action_links' ) );

		// Declare HPOS compatibility
		add_action( 'before_woocommerce_init', array( $this, 'declare_wc_hpos_compatability' ) );

		// Fires when plugin is installed (outside compatibility check - just schedules tasks)
		register_activation_hook( __FILE__, array( $this, 'plugin_installed' ) );

		// Fires when plugin is updated (outside compatibility check - just schedules tasks)
		add_action( 'upgrader_process_complete', array( $this, 'plugin_updated' ), 10, 2 );

		// Checks for compatibility with versions (run once and cache result)
		$this->compatibility_check_result = $this->check_compatability_and_return_notices();

		// If we are all good, load the plugin
		if ( $this->is_plugin_compatible() ) {
			// Load the plugin
			add_action( 'plugins_loaded', array( $this, 'initialize_plugin' ), 20 );
			// $this->initialize_plugin();
		}

		// Output plugin init admin notices (hook only fires in admin area)
		add_action( 'admin_notices', array( $this, 'output_alpha_insights_init_admin_notices' ) );

		// Process any pending post-installation/update tasks
		add_action( 'admin_init', array( $this, 'process_pending_plugin_tasks' ), 10 );

	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization
	 */
	public function __wakeup() {
		throw new Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Setup definitions
	 */
	public function define_constants() {

		// Changing this in the free version will trigger a fatal error.
		// If you are trying to break the locks, just use discount code "WPDAVIESDEV30" when purchasing the pro version for 30% off.
		// It's our gift to you from developer to developer.
		if ( ! defined('WPD_AI_PRO') ) define( 'WPD_AI_PRO', false );

		// Alpha Insights Meta
		if ( ! defined('WPD_AI_VER') ) define( 'WPD_AI_VER', '1.1.0' );
		if ( ! defined('WPD_AI_CACHE_VERSION') ) define( 'WPD_AI_CACHE_VERSION', '5.4.9' ); // Follows along pro versioning
		if ( ! defined('WPD_AI_CACHE_UPDATE_REQUIRED_VER') ) define( 'WPD_AI_CACHE_UPDATE_REQUIRED_VER', '4.7.10' ); // version this up as cache deletes are required
		if ( ! defined('WPD_AI_DB_VERSION') ) define( 'WPD_AI_DB_VERSION', '5.2.1' );
		if ( ! defined('WPD_AI_PRODUCT_ID') ) define( 'WPD_AI_PRODUCT_ID', 8330 );
		
		// Security Constants
		if ( ! defined('WPD_AI_AJAX_NONCE_ACTION') ) define( 'WPD_AI_AJAX_NONCE_ACTION', 'wpd_alpha_insights_nonce' );

		// Date Formats
		if ( ! defined('WPD_AI_PHP_PRETTY_DATE') ) define( 'WPD_AI_PHP_PRETTY_DATE', 'F jS, Y' );
		if ( ! defined('WPD_AI_PHP_PRETTY_DATETIME') ) define( 'WPD_AI_PHP_PRETTY_DATETIME', 'F jS, Y \a\t g:ia' );
		if ( ! defined('WPD_AI_PHP_ISO_DATE') ) define( 'WPD_AI_PHP_ISO_DATE', 'Y-m-d' );
		if ( ! defined('WPD_AI_PHP_ISO_DATETIME') ) define( 'WPD_AI_PHP_ISO_DATETIME', 'Y-m-d H:i:s' );

		// Main Path Constants
		if ( ! defined('WPD_AI_PATH') ) define( 'WPD_AI_PATH', plugin_dir_path( __FILE__ ) ); // Server Path
		if ( ! defined('WPD_AI_URL_PATH') ) define( 'WPD_AI_URL_PATH', plugin_dir_url( __FILE__ ) ); // Public URL Path

		// Make sure function is available at this stage
		if ( ! function_exists( 'wp_upload_dir' ) ) require_once ABSPATH . WPINC . '/functions.php';

		// Get upload directories
		$upload_directory = wp_upload_dir();

		// Content paths
		if ( ! defined('WPD_AI_UPLOADS_FOLDER_SYSTEM') ) define( 'WPD_AI_UPLOADS_FOLDER_SYSTEM', trailingslashit( $upload_directory['basedir'] ) . 'alpha-insights/' ); // System Path
		if ( ! defined('WPD_AI_UPLOADS_FOLDER') ) define( 'WPD_AI_UPLOADS_FOLDER', trailingslashit( $upload_directory['baseurl'] ) . 'alpha-insights/' ); // Public URL Path
		if ( ! defined('WPD_AI_CSV_SYSTEM_PATH') ) define( 'WPD_AI_CSV_SYSTEM_PATH', trailingslashit( $upload_directory['basedir'] ) . 'alpha-insights/exports/csv_files/' ); // System Path
		if ( ! defined('WPD_AI_CSV_PATH') ) define( 'WPD_AI_CSV_PATH', trailingslashit( $upload_directory['baseurl'] ) . 'alpha-insights/exports/csv_files/' ); // Public URL Path

		// Minimum Versions
		if ( ! defined('WPD_AI_MIN_PHP_VER') ) define( 'WPD_AI_MIN_PHP_VER', '7.4.0' );
		if ( ! defined('WPD_AI_MIN_WP_VER') ) define( 'WPD_AI_MIN_WP_VER', '5.0.0' );
		if ( ! defined('WPD_AI_MIN_WC_VER') ) define( 'WPD_AI_MIN_WC_VER', '3.0.0' );

		// APIs
		if ( ! defined('WPD_AI_FACEBOOK_API_VER') ) define( 'WPD_AI_FACEBOOK_API_VER', 'v24.0' );
		if ( ! defined('WPD_AI_GOOGLE_ADS_API_VER') ) define( 'WPD_AI_GOOGLE_ADS_API_VER', 'v20' );

	}

	/**
	 * Check for conflicting plugins (pro version)
	 * Deactivates free version if pro is active
	 */
	private function check_for_conflicting_plugins() {
		
		// Need to include plugin functions
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		
		// Get free plugin file path
		$pro_plugin_file = 'wp-davies-alpha-insights/wpd-alpha-insights.php';
		
		// Check if free version is active
		if ( is_plugin_active( $pro_plugin_file ) || class_exists( 'WPD_Alpha_Insights_Plugin' ) ) {
			
			// Deactivate free version
			deactivate_plugins( plugin_basename( __FILE__ ) );
			
			// Set transient for admin notice
			set_transient( 'wpd_ai_free_deactivated_by_pro', true, 30 );
			
		}
		
	}

	/**
	 * Check for compatibility with this WP install
	 *
	 * Checks all compatibility requirements and collects all notices instead of returning early.
	 * This allows multiple issues to be reported at once.
	 *
	 * @since 1.0.0
	 * @return array Array with 'notices' (array of messages) and 'version_check' (bool)
	 */
	public function check_compatability_and_return_notices() {

		// Ensure constants are defined before checking
		if ( ! defined( 'WPD_AI_MIN_PHP_VER' ) || ! defined( 'WPD_AI_MIN_WP_VER' ) || ! defined( 'WPD_AI_MIN_WC_VER' ) ) {
			// Constants not yet defined, return safe defaults
			return array(
				'notices' => array(),
				'version_check' => true
			);
		}

		// WP Version
		global $wp_version;

		// Message array - collect ALL issues, don't return early
		$notices = array();
		$is_compatible = true;

		// Check PHP version
		if ( version_compare( PHP_VERSION, WPD_AI_MIN_PHP_VER, '<' ) ) {
			$is_compatible = false;
			$notices[] = sprintf(
				/* translators: 1: Minimum required PHP version, 2: Current PHP version */
				__( 'Alpha Insights requires at least PHP %1$s to run correctly. You are currently using PHP %2$s. Please upgrade your PHP version to use this plugin.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				esc_html( WPD_AI_MIN_PHP_VER ),
				esc_html( PHP_VERSION )
			);
		}

		// Check WordPress version
		if ( version_compare( $wp_version, WPD_AI_MIN_WP_VER, '<' ) ) {
			$is_compatible = false;
			$notices[] = sprintf(
			/* translators: 1: Minimum required WordPress version, 2: Current WordPress version */
				__( 'Alpha Insights requires at least WordPress version %1$s to run. You are currently using version %2$s. Please upgrade WordPress to use this plugin.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				esc_html( WPD_AI_MIN_WP_VER ),
				esc_html( $wp_version )
			);
		}

		// Check if WooCommerce is active - use multiple methods for reliability
		$woocommerce_active = false;
		$woocommerce_version = null;
		
		// Method 1: Check if WooCommerce class exists (most reliable when WooCommerce is loaded)
		if ( class_exists( 'WooCommerce' ) ) {
			$woocommerce_active = true;
			// WC_VERSION should be defined if class exists, but check anyway
			if ( defined( 'WC_VERSION' ) ) {
				$woocommerce_version = WC_VERSION;
			}
		}
		
		// Method 2: If class doesn't exist yet, check if plugin is active (early check)
		if ( ! $woocommerce_active ) {
			// Include plugin.php if needed
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}
			
			// Check if WooCommerce plugin is active
			if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
				$woocommerce_active = true;
				// Try to get version from plugin data if constant isn't defined yet
				if ( ! defined( 'WC_VERSION' ) && function_exists( 'get_plugin_data' ) ) {
					$plugin_file = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
					if ( file_exists( $plugin_file ) ) {
						$plugin_data = get_plugin_data( $plugin_file, false, false );
						if ( isset( $plugin_data['Version'] ) ) {
							$woocommerce_version = $plugin_data['Version'];
						}
					}
				} elseif ( defined( 'WC_VERSION' ) ) {
					$woocommerce_version = WC_VERSION;
				}
			}
		} elseif ( defined( 'WC_VERSION' ) ) {
			$woocommerce_version = WC_VERSION;
		}
		
		// If WooCommerce is not active, show error
		if ( ! $woocommerce_active ) {
			$is_compatible = false;
			$notices[] = __( 'Alpha Insights requires WooCommerce to be installed and activated. Please install and activate WooCommerce to use this plugin.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		} elseif ( ! empty( $woocommerce_version ) && version_compare( $woocommerce_version, WPD_AI_MIN_WC_VER, '<' ) ) {
			// Check WooCommerce version (only if we have a version to check)
			$is_compatible = false;
			$notices[] = sprintf(
			/* translators: 1: Minimum required WooCommerce version, 2: Current WooCommerce version */
				__( 'Alpha Insights requires at least WooCommerce version %1$s to run. You are currently using version %2$s. Please upgrade WooCommerce to use this plugin.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				esc_html( WPD_AI_MIN_WC_VER ),
				esc_html( $woocommerce_version )
			);
		}

		// Update version check property
		$this->version_check = $is_compatible;

		// Check DB Version - Schedule deferred update instead of immediate
		if ( defined( 'WPD_AI_DB_VERSION' ) ) {
			$installed_db_version = get_option( 'wpd_ai_db_version', null );
				if ( is_string( $installed_db_version ) && version_compare( $installed_db_version, WPD_AI_DB_VERSION, '<' ) ) {
				// Schedule DB update to run when classes are loaded
				update_option( 'wpd_ai_pending_db_update', true );
				$this->log( 'Database version check detected outdated schema, scheduled deferred update.' );
			}
		}

		// Return all collected notices
		return array(
			'notices' => $notices,
			'version_check' => $is_compatible
		);

	}

	/**
	 * Output error notice if we don't have dependencies
	 * 
	 * Safely outputs compatibility notices only in admin area and not during AJAX requests.
	 * Uses cached compatibility check results to avoid running checks multiple times.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function output_plugin_dependency_notices() {

		// Only output in admin area
		if ( ! is_admin() ) {
			return;
		}

		// Don't output during AJAX requests
		if ( wp_doing_ajax() ) {
			return;
		}

		// Don't output during autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Use cached compatibility check results if available, otherwise run check
		if ( null === $this->compatibility_check_result ) {
			$this->compatibility_check_result = $this->check_compatability_and_return_notices();
		}

		$version_check = $this->compatibility_check_result;
		
		// Validate notices array
		if ( ! isset( $version_check['notices'] ) || ! is_array( $version_check['notices'] ) ) {
			return;
		}

		$notice_messages = $version_check['notices'];

		// Output notices if any exist
		if ( ! empty( $notice_messages ) ) {
			foreach ( $notice_messages as $message ) {
				// Ensure message is a string and not empty
				if ( is_string( $message ) && ! empty( trim( $message ) ) ) {
					printf(
						'<div class="wpd-notice notice notice-error is-dismissible"><p>%s</p></div>',
						wp_kses_post( $message )
					);
				}
			}
		}

	}

	/**
	 * Declares compatibility with WC extensions, E.g. HPOS
	 */
	public function declare_wc_hpos_compatability() {

		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}

	}
	
	/**
	 * Fires when plugin is installed
	 */
	public function plugin_installed() {
		
		// Need to include plugin functions
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		
		// Check for and deactivate free version on activation
		$pro_plugin_file = 'wp-davies-alpha-insights/wpd-alpha-insights.php';
		if ( is_plugin_active( $pro_plugin_file ) || class_exists( 'WPD_Alpha_Insights_Plugin' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			set_transient( 'wpd_ai_free_deactivated_by_pro', true, 30 );
		}
		
		// Logging
		$this->log( 'Alpha Insights installation method has been triggered.' );
		
		// Set latest version
		update_option( 'wpd_ai_cache_version', WPD_AI_CACHE_VERSION );
		update_option( 'wpd_ai_plugin_update_version', WPD_AI_VER );
		
		// Schedule database creation for next admin load
		update_option( 'wpd_ai_pending_db_update', true );
		$this->log( 'Database table creation scheduled for next admin page load.' );

		// Schedule rewrite rules flush for next admin load
		update_option( 'wpd_ai_pending_rewrite_flush', true );
		$this->log( 'Rewrite rules flush scheduled for next admin page load.' );

		// Schedule default report installation for next admin load
		update_option( 'wpd_ai_pending_report_installation', true );
		$this->log( 'Default reports installation scheduled for next admin page load.' );

		// Schedule getting started redirect (only on first activation)
		if ( ! get_option( 'wpd_ai_onboarding_completed' ) ) {
			set_transient( 'wpd_ai_activation_redirect', true, 30 );
			$this->log( 'Getting started redirect scheduled for next admin page load.' );
		}

		$this->log( 'Plugin installation method has completed successfully.' );

	}

	/**
	 * Method that fires when plugin is updated
	 *
	 * @param object $upgrader_object
	 * @param array  $options
	 */
	public function plugin_updated( $upgrader_object, $options ) {

		// Security: Check if options keys exist
		if ( ! isset( $options['action'], $options['type'] ) ) {
			return;
		}

		$current_plugin_path_name = plugin_basename( __FILE__ );

		if ( $options['action'] === 'update' && $options['type'] === 'plugin' ) {

			if ( isset($options['plugins']) && is_array($options['plugins']) ) {

				foreach( $options['plugins'] as $each_plugin ) {

					if ( $each_plugin === $current_plugin_path_name ) {

						$current_plugin_version = get_option( 'wpd_ai_plugin_update_version', null );

						// Plugin upgrade
						$this->log( 'Upgrading Alpha Insights from version ' . $current_plugin_version . ' to version ' . WPD_AI_VER . '.' );

						// Set currently installed version at end of plugin update
						update_option( 'wpd_ai_plugin_update_version', WPD_AI_VER );

						// Schedule database update for next admin load
						update_option( 'wpd_ai_pending_db_update', true );
						$this->log( 'Database upgrade scheduled for next admin page load.' );

						// Schedule rewrite rules flush for next admin load
						update_option( 'wpd_ai_pending_rewrite_flush', true );
						$this->log( 'Rewrite rules flush scheduled for next admin page load.' );

					// Schedule default report installation for next admin load
					update_option( 'wpd_ai_pending_report_installation', true );
					$this->log( 'Default reports installation scheduled for next admin page load.' );

					// Schedule migration runner to check for pending migrations
					if ( class_exists( 'WPDAI_Action_Scheduler' ) ) {
						$action_scheduler = new WPDAI_Action_Scheduler();
						$action_scheduler->schedule_one_off_event( WPDAI_Action_Scheduler::SINGLE_EVENT_MIGRATION_RUNNER, 0 );
						$this->log( 'Migration runner scheduled via action scheduler.' );
					} else {
						update_option( 'wpd_ai_pending_migration_runner', true );
						$this->log( 'Migration runner scheduled for next admin page load.' );
					}

					$this->log( 'Plugin update scheduling complete.' );

					}
	
				}

			}

		}

	}

	/**
	 * Process all pending plugin tasks after installation or update
	 * Runs on admin_init to ensure all classes and dependencies are loaded
	 * Tasks are executed in order: DB -> Rewrite Rules -> Reports
	 */
	public function process_pending_plugin_tasks() {

		// Task 1: Database table creation/updates (must run first)
		if ( get_option( 'wpd_ai_pending_db_update' ) ) {

			$this->log( 'Processing pending database table updates.' );
			$this->create_tables_and_data();
			delete_option( 'wpd_ai_pending_db_update' );
			$this->log( 'Database tables updated successfully.' );

		}

		// Task 2: Rewrite rules flush (requires WPDAI_Live_Share_Handler class)
		if ( get_option( 'wpd_ai_pending_rewrite_flush' ) ) {

			// Verify the class exists (all dependencies loaded)
			if ( class_exists( 'WPDAI_Live_Share_Handler' ) ) {

				$this->log( 'Flushing pending rewrite rules for live share URLs.' );
				WPDAI_Live_Share_Handler::flush_rewrite_rules();
				delete_option( 'wpd_ai_pending_rewrite_flush' );
				$this->log( 'Rewrite rules flushed successfully for live share URLs.' );

			} else {

				$this->log( 'WPDAI_Live_Share_Handler class not found during deferred flush, will retry on next admin load.' );

			}

		}

		// Task 3: Default reports installation (requires WPDAI_Report_Builder class and DB tables)
		if ( get_option( 'wpd_ai_pending_report_installation' ) ) {

			// Verify the class exists (all dependencies loaded)
			if ( class_exists( 'WPDAI_Report_Builder' ) ) {

				$this->log( 'Installing pending default reports.' );
				WPDAI_Report_Builder::import_all_default_reports( false );
				delete_option( 'wpd_ai_pending_report_installation' );
				$this->log( 'Default reports installed successfully.' );

			} else {

				$this->log( 'WPDAI_Report_Builder class not found during deferred installation, will retry on next admin load.' );

			}

		}

		// Task 4: Migration runner (requires WPDAI_Action_Scheduler class)
		if ( get_option( 'wpd_ai_pending_migration_runner' ) ) {

			// Verify the class exists (all dependencies loaded)
			if ( class_exists( 'WPDAI_Action_Scheduler' ) ) {

				$this->log( 'Scheduling pending migration runner.' );
				$action_scheduler = new WPDAI_Action_Scheduler();
				$action_scheduler->schedule_one_off_event( WPDAI_Action_Scheduler::SINGLE_EVENT_MIGRATION_RUNNER, 0 );
				delete_option( 'wpd_ai_pending_migration_runner' );
				$this->log( 'Migration runner scheduled successfully.' );

			} else {

				$this->log( 'WPDAI_Action_Scheduler class not found during deferred migration scheduling, will retry on next admin load.' );

			}

		}

		// Task 4: Redirect to getting started page (after all other tasks complete)
		if ( get_transient( 'wpd_ai_activation_redirect' ) ) {

			// Delete the transient
			delete_transient( 'wpd_ai_activation_redirect' );

			// Get current page to prevent redirect loops
			$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

			// Only redirect if:
			// 1. Not in AJAX request
			// 2. Not in bulk activation
			// 3. User has capability to manage options
			// 4. Onboarding not completed
			// 5. Not already on getting started page (prevent loop)
			// 6. Not on reports pages (prevent redirect loop when user finishes wizard)
			if ( 
				! wp_doing_ajax() 
				&& ! isset( $_GET['activate-multi'] ) 
				&& current_user_can( 'manage_options' ) 
				&& ! get_option( 'wpd_ai_onboarding_completed' )
				&& $current_page !== WPDAI_Admin_Menu::$getting_started_slug
				&& $current_page !== WPDAI_Admin_Menu::$sales_report_slug
			) {

				$this->log( 'Redirecting to getting started page.' );
				
				// Perform redirect
				wp_safe_redirect( admin_url( 'admin.php?page=' . WPDAI_Admin_Menu::$getting_started_slug ) );
				exit;

			}

		}

	}

	/**
	 * Ensure log directory exists before any logging
	 */
	private function ensure_log_directory() {

		$log_directory = WPD_AI_UPLOADS_FOLDER_SYSTEM . 'log';
		
		if ( ! is_dir( $log_directory ) ) {
			wp_mkdir_p( $log_directory );
		}

	}

	/**
	 * Log fatal and critical errors that contain 'alpha-insights'
	 * 
	 * This shutdown function catches fatal errors, parse errors, core errors,
	 * compile errors, and recoverable errors that are related to the plugin.
	 */
	public function log_fatal_errors() {

		// Get the last error
		$error = error_get_last();

		// Only process if there's an error
		if ( empty( $error ) || ! is_array( $error ) ) {
			return;
		}

		// Define fatal/critical error types
		$fatal_error_types = array(
			E_ERROR,              // Fatal run-time errors
			E_PARSE,              // Compile-time parse errors
			E_CORE_ERROR,         // Fatal errors during PHP's initial startup
			E_COMPILE_ERROR,      // Fatal compile-time errors
			E_RECOVERABLE_ERROR,  // Catchable fatal errors
		);

		// Only log fatal/critical errors
		if ( ! in_array( $error['type'], $fatal_error_types, true ) ) {
			return;
		}

		// Check if error is related to alpha-insights
		$error_message = isset( $error['message'] ) ? strtolower( $error['message'] ) : '';
		$error_file    = isset( $error['file'] ) ? strtolower( $error['file'] ) : '';
		$search_string = 'alpha-insights';

		// Check if error message or file path contains 'alpha-insights'
		if ( 
			false === strpos( $error_message, $search_string ) && 
			false === strpos( $error_file, $search_string ) 
		) {
			return;
		}

		// Build error log entry
		$error_type_name = $this->get_error_type_name( $error['type'] );
		$log_entry = sprintf(
			"FATAL ERROR [%s]: %s\nFile: %s\nLine: %s\n",
			$error_type_name,
			$error['message'],
			$error['file'],
			$error['line']
		);

		// Log the error using wpdai_write_log if available, otherwise use the private log method
		if ( function_exists( 'wpdai_write_log' ) ) {
			wpdai_write_log( $log_entry, 'fatal_error' );
		} else {
			$this->log( $log_entry, 'fatal_error' );
		}

	}

	/**
	 * Get human-readable error type name
	 * 
	 * @param int $error_type The error type constant
	 * @return string Human-readable error type name
	 */
	private function get_error_type_name( $error_type ) {

		$error_types = array(
			E_ERROR             => 'E_ERROR',
			E_PARSE             => 'E_PARSE',
			E_CORE_ERROR        => 'E_CORE_ERROR',
			E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
			E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
		);

		return isset( $error_types[ $error_type ] ) ? $error_types[ $error_type ] : 'UNKNOWN';

	}

	/**
	 * Log events before files have been included
	 *
	 * @param mixed  $data
	 * @param string $log
	 */
	private function log( $data, $log = 'plugin_loader' ) {

		$log_directory = WPD_AI_UPLOADS_FOLDER_SYSTEM . 'log';
		
		// Ensure directory exists
		if ( ! is_dir( $log_directory ) ) {
			wp_mkdir_p( $log_directory );
		}

		$filepath = $log_directory . '/wpd_' . $log . '_log.txt';
		$time_stamp = current_time('Y-m-d h:i:sa') . ': ';

		if ( is_array( $data ) || is_object( $data ) ) {

			file_put_contents( $filepath, $time_stamp . print_r( $data, true ), FILE_APPEND );

		} else {

			file_put_contents( $filepath, $time_stamp . trim( $data ) . PHP_EOL, FILE_APPEND );

		}

	}

	/**
	 * Loads up all the files
	 */
	public function initialize_plugin() {

		$this->include_plugin_files();	
		$this->create_uploads_folders();

	}

	/**
	 * Setup all our files
	 */
	public function include_plugin_files() {

		// Functions
		require_once( WPD_AI_PATH . 'includes/wpd-functions.php');
		require_once( WPD_AI_PATH . 'includes/functions/wpd-csv-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-hpos-compatability-functions.php');
		require_once( WPD_AI_PATH . 'includes/functions/wpd-currency-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-url-parsing-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-cache-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-formatting-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-data-fetch-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-date-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-debugging-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-webhook-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-one-off-events-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-recurring-event-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-report-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-settings-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-deprecated.php');
		require_once( WPD_AI_PATH . 'includes/functions/wpd-subscription-functions.php' );
		require_once( WPD_AI_PATH . 'includes/emails/wpd-email-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-custom-cost-functions.php' );
		
		// Admin
		require_once( WPD_AI_PATH . 'includes/admin/wpd-admin-page-content.php');
		require_once( WPD_AI_PATH . 'includes/admin/wpd-settings.php');

		// Framework
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Core.php');
		require_once( WPD_AI_PATH . 'includes/wpd-scripts-styles.php' );
		require_once( WPD_AI_PATH . 'includes/wpd-ajax.php' );

		// Additional Classes -> No Dependencies
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Admin_Menu.php');		
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Order_Calculator.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Database_Interactor.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Migration.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Action_Scheduler.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_User_Agent_Classification.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Traffic_Type_Detection.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_CSV_Exporter.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Session_Tracking.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Task_Runner.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Reporting_API.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Getting_Started.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Data_Manager.php');

		// Additional Classes - With Dependencies
		require_once( WPD_AI_PATH . 'includes/classes/interfaces/WPDAI_Custom_Data_Source_Interface.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Custom_Data_Source_Base.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Custom_Data_Source_Registry.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Data_Warehouse.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Report_Builder.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Report_Filters.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Woocommerce_Event_Tracking.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Cost_Of_Goods_Manager.php');

		// Integrations framework
		require_once( WPD_AI_PATH . 'includes/integrations/WPDAI_Integration_Base.php' );
		require_once( WPD_AI_PATH . 'includes/integrations/WPDAI_Integrations_Manager.php' );

		// Integrations providers
		require_once( WPD_AI_PATH . 'includes/integrations/providers/wpdavies/webhooks/WPDAI_Webhook_Provider.php');

		// Register Relevant Actions
		WPDAI_Report_Builder::register_ajax_actions();
		WPDAI_Cost_Of_Goods_Manager::register_ajax_actions();
		WPDAI_Reporting_API::register_routes();
		WPDAI_Data_Manager::register_ajax_actions();

		// Load the appropriate loader based on the plugin version
		if ( file_exists( WPD_AI_PATH . 'includes/classes/pro/WPDAI_Pro_Loader.php' ) ) {
			require_once( WPD_AI_PATH . 'includes/classes/pro/WPDAI_Pro_Loader.php');
		} else {
			require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Free_Loader.php');
		}

		// Data sources: load all and register (each file self-registers when required).
		require_once( WPD_AI_PATH . 'includes/classes/data-sources/WPDAI_Sales_Data_Source.php' );
		require_once( WPD_AI_PATH . 'includes/classes/data-sources/WPDAI_Store_Profit_Data_Source.php' );
		require_once( WPD_AI_PATH . 'includes/classes/data-sources/WPDAI_Analytics_Data_Source.php' );
		require_once( WPD_AI_PATH . 'includes/classes/data-sources/WPDAI_Refunds_Internal_Data_Source.php' );

	}

	/**
	 * Create directory for uploads
	 */
	private function create_uploads_folders() {

		$alpha_insights_directory = WPD_AI_UPLOADS_FOLDER_SYSTEM;
		if ( ! is_dir($alpha_insights_directory) ) {
			wp_mkdir_p( $alpha_insights_directory );
		}

		$exports_directory = WPD_AI_UPLOADS_FOLDER_SYSTEM . 'exports';
		if ( ! is_dir($exports_directory) ) {
			wp_mkdir_p( $exports_directory );
		}

		$csv_directory = WPD_AI_UPLOADS_FOLDER_SYSTEM . 'exports/csv_files';
		if ( ! is_dir($csv_directory) ) {
			wp_mkdir_p( $csv_directory );
		}
	
		$pdf_directory = WPD_AI_UPLOADS_FOLDER_SYSTEM . 'exports/pdf_files';
		if ( ! is_dir($pdf_directory) ) {
			wp_mkdir_p( $pdf_directory );
		}

		$tmp_directory = WPD_AI_UPLOADS_FOLDER_SYSTEM . 'tmp';
		if ( ! is_dir($tmp_directory) ) {
			wp_mkdir_p( $tmp_directory );
		}

		$log_directory = WPD_AI_UPLOADS_FOLDER_SYSTEM . 'log';
		if ( ! is_dir($log_directory) ) {
			wp_mkdir_p( $log_directory );
		}

	}

	/**
	 * Responsible for loading any initialization admin notices
	 */
	public function output_alpha_insights_init_admin_notices() {

		// If there are dependency issues with Alpha Insights
		$this->output_plugin_dependency_notices();

		// Check if plugin cache is required
		$this->clear_cache_if_required();

		// Output plugin activity notices
		$this->output_plugin_activity_notices();
		
		// Show notice if free version was deactivated
		if ( get_transient( 'wpd_ai_free_deactivated_by_pro' ) ) {
			delete_transient( 'wpd_ai_free_deactivated_by_pro' );
			?>
			<div class="notice notice-warning is-dismissible">
				<p><strong><?php esc_html_e( 'Alpha Insights:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></strong> <?php esc_html_e( 'The free version has been automatically deactivated because Alpha Insights Pro is active. Please deactivate the pro version first if you wish to use the free version.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></p>
			</div>
			<?php
		}

	}

	/**
	 * If we need to tell the user anything is happening, do it here
	 */
	public function output_plugin_activity_notices() {

		// If the cache is being refreshed
		if ( get_transient('_wpd_updating_all_orders_cache') === 1 ) {
			
			// Output notice - check if function exists
			if ( function_exists( 'wpdai_admin_notice' ) ) {
				wpdai_admin_notice( __( 'Your Alpha Insights Order Cache is currently being updated, depending on how many orders you have this may take a few minutes.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) );
			}

		}

		// Output any additional plugin activity notices
		do_action( 'wpd_ai_output_plugin_activity_notices' );

	}

	/**
	 * Detects if they need a cache delete
	 */
	private function clear_cache_if_required() {

		// Check if cache reset is required
		$cache_reset_required = $this->check_if_plugin_needs_cache_cleared();
		
		if ( $cache_reset_required ) {

			$cache_version = (string) get_option( 'wpd_ai_cache_version', '' );
			
			$this->log( 'This version of Alpha Insights requires a cache update, attempting to clear cache.' );
			$this->log( 'Cache version ' . $cache_version . ' is currently installed, latest cache refresh version is ' . WPD_AI_CACHE_UPDATE_REQUIRED_VER . '. Performing update.' );

			$this->clear_cache();

			$this->log( 'Alpha Insights cache clear has been scheduled.' );

		}

	}

	/**
	 * Defines which plugin versions need the cache cleared
	 *
	 * @return bool
	 */
	private function check_if_plugin_needs_cache_cleared() {

		// Get latest cache version
		$cache_version = (string) get_option( 'wpd_ai_cache_version', '' );

		if ( empty( $cache_version ) ) {
			return true;
		}

		return version_compare( $cache_version, WPD_AI_CACHE_UPDATE_REQUIRED_VER, '<' );

	}

	/**
	 * Clear cache on plugin upgrade
	 */
	private function clear_cache() {

		// Check if function exists before calling
		if ( function_exists( 'wpdai_delete_all_data_caches' ) ) {
			wpdai_delete_all_data_caches();
		}

		// Set latest version since this has been cleared
		$this->log( 'Storing your cache version in the DB.' );
		update_option( 'wpd_ai_cache_version', WPD_AI_CACHE_VERSION );

	}

	/**
	 * Create tables and data
	 *
	 * @return array
	 */
	private function create_tables_and_data() {

		$response = array();

		$this->log( 'Verifying the database structure, updating if required.' );

		if ( ! class_exists('WPDAI_Database_Interactor') ) {
			require_once( WPD_AI_PATH . 'includes/classes/WPDAI_Database_Interactor.php');
		}

		$db_interactor = new WPDAI_Database_Interactor();
		$this->log( 'DB interactor has been initialized, going to attempt to update the DB to the latest version.' );

		if ( is_object( $db_interactor ) && method_exists( $db_interactor, 'create_update_tables_columns' ) ) {

			$db_upgrade_response = $db_interactor->create_update_tables_columns();
			$this->log( 'Executing DB upgrader.' );

			if ( $db_upgrade_response ) {

				$response['success']	= true;
				$response['message']	= __( 'DB Upgrade completed successfully, you can check the Alpha Insights logs for more details if required.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
				$this->log( 'DB Upgrade completed successfully, you can check the Alpha Insights logs for more details if required.' );

			} else {

				$response['success']	= false;
				$response['message']	= __( 'Error occurred during DB upgrade, please check the Alpha Insights logs for more details.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
				$this->log( 'Error occurred during DB upgrade, please check the Alpha Insights logs for more details.' );

			}

		} else {

			$response['success']	= false;
			$response['message']	= __( 'We couldn\'t complete this action unfortunately, feel free to shoot us an email and we\'ll help you resolve this.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
			$this->log( 'Could not complete the DB upgrade, couldn\'t locate the DB interactor object, please contact support or re-trigger the DB upgrade in general settings.' );

		}

		$this->log( 'DB upgrade complete.' );

		return $response;

	}

	/**
	 * Returns plugin compatibility
	 *
	 * @return bool
	 */
	private function is_plugin_compatible() {

		return $this->version_check;

	}

	/**
	 * Extra links on plugin page
	 *
	 * @since 1.4.1
	 * @param array $links
	 * @return array
	 */
	public function alpha_insights_plugin_action_links( $links ) {

		$new_links = array();

		// Settings
		$link = admin_url( 'admin.php') . '?page=wpd-settings';
		$new_links[] = '<a href="' . esc_url( $link ) . '">' . esc_html__( 'Settings', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) . '</a>';
		
		// Docs
		$new_links[] = '<a href="' . esc_url( 'https://wpdavies.dev/docs/alpha-insights/' ) . '" target="_blank">' . esc_html__( 'Docs', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) . '</a>';	

		// Upgrade to Pro
		if ( ! WPD_AI_PRO ) {

			// Build upgrade URL with query parameters for tracking
			$upgrade_url = add_query_arg(
				array(
					'utm_source' => 'wordpress',
					'utm_medium' => 'plugin_action_links',
					'utm_campaign' => 'alpha_insights_free_upgrade',
					'utm_content' => 'upgrade_to_pro_link'
				),
				'https://wpdavies.dev/plugins/alpha-insights/pricing/'
			);
			
			// Add the "Upgrade to Pro" link at the beginning
			$new_links[] = '<a href="' . esc_url($upgrade_url) . '" target="_blank" style="color: #2271b1; font-weight: 600;">' . esc_html__('Upgrade to Pro', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') . '</a>';
			
		}

		$links = array_merge( $new_links, $links );

		return $links;

	}

}

// Initialize the singleton instance
WPD_Alpha_Insights_Free_Plugin::get_instance();