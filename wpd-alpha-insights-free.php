<?php
/**
 *
 * Plugin Name:         Alpha Insights - Sales Report Builder & Analytics For WooCommerce
 * Plugin URI:          https://wpdavies.dev/plugins/alpha-insights/
 * Description:         The world's most powerful drag & drop WooCommerce reporting plugin.
 * Author:              WP Davies
 * Author URI:          https://wpdavies.dev/
 *
 * Version:             	1.0.0
 * Requires at least:   	5.0.0
 * Tested up to:        	6.8.2
 * Requires PHP: 			7.4.0
 * Requires Plugins: 		woocommerce
 * WC requires at least: 	3.0.0
 * WC tested up to: 		10.3.5
 *
 * License:             GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Text Domain:         alpha-insights-pro
 * Domain Path: 		/languages
 *
 * Alpha Insights
 * Copyright (C) 2025, WP Davies, support@wpdavies.dev
 *
  * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category            Plugin
 * @copyright           Copyright WP Davies © 2025
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

		// Add links to plugin page
		add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'alpha_insights_plugin_action_links' ) );

		// Declare HPOS compatibility
		add_action( 'before_woocommerce_init', array( $this, 'declare_wc_hpos_compatability' ) );

		// Fires when plugin is installed (outside compatibility check - just schedules tasks)
		register_activation_hook( __FILE__, array( $this, 'plugin_installed' ) );

		// Fires when plugin is updated (outside compatibility check - just schedules tasks)
		add_action( 'upgrader_process_complete', array( $this, 'plugin_updated' ), 10, 2 );

		// Checks for compatibility with versions
		$this->check_compatability_and_return_notices();

		// If we are all good, load the plugin
		if ( $this->is_plugin_compatible() ) {

			// Load the plugin
			add_action( 'plugins_loaded', array( $this, 'initialize_plugin' ), 20 );

		}

		// Output plugin init admin notices
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
		if ( ! defined('WPD_AI_VER') ) define( 'WPD_AI_VER', '1.0.0' );
		if ( ! defined('WPD_AI_CACHE_UPDATE_REQUIRED_VER') ) define( 'WPD_AI_CACHE_UPDATE_REQUIRED_VER', '1.0.0' ); // version this up as cache deletes are required
		if ( ! defined('WPD_AI_DB_VERSION') ) define( 'WPD_AI_DB_VERSION', '1.0.0' );
		if ( ! defined('WPD_AI_PRODUCT_ID') ) define( 'WPD_AI_PRODUCT_ID', 8330 );
		
		// Security Constants
		if ( ! defined('WPD_AI_AJAX_NONCE_ACTION') ) define( 'WPD_AI_AJAX_NONCE_ACTION', 'wpd_alpha_insights_nonce' );
		if ( ! defined('WPD_AI_TEXT_DOMAIN') ) define( 'WPD_AI_TEXT_DOMAIN', 'wpd-alpha-insights' );

		// Date Formats
		if ( ! defined('WPD_AI_PHP_PRETTY_DATE') ) define( 'WPD_AI_PHP_PRETTY_DATE', 'F jS, Y' );
		if ( ! defined('WPD_AI_PHP_PRETTY_DATETIME') ) define( 'WPD_AI_PHP_PRETTY_DATETIME', 'F jS, Y \a\t g:ia' );
		if ( ! defined('WPD_AI_PHP_ISO_DATE') ) define( 'WPD_AI_PHP_ISO_DATE', 'Y-m-d' );
		if ( ! defined('WPD_AI_PHP_ISO_DATETIME') ) define( 'WPD_AI_PHP_ISO_DATETIME', 'Y-m-d H:i:s' );

		// Main Path Constants
		if ( ! defined('WPD_AI_PATH') ) define( 'WPD_AI_PATH', plugin_dir_path( __FILE__ ) ); // Server Path
		if ( ! defined('WPD_AI_URL_PATH') ) define( 'WPD_AI_URL_PATH', plugin_dir_url( __FILE__ ) ); // Public URL Path

		// Additional Paths - with safe wp_upload_dir() access
		if ( ! defined('WPD_AI_UPLOADS_FOLDER_SYSTEM') ) {
			$upload_dir = wp_upload_dir();
			if ( $upload_dir && is_array( $upload_dir ) && isset( $upload_dir['basedir'] ) ) {
				define( 'WPD_AI_UPLOADS_FOLDER_SYSTEM', trailingslashit( $upload_dir['basedir'] ) . 'alpha-insights/' );
			} else {
				// Fallback to wp-content/uploads if wp_upload_dir() fails
				define( 'WPD_AI_UPLOADS_FOLDER_SYSTEM', trailingslashit( WP_CONTENT_DIR ) . 'uploads/alpha-insights/' );
			}
		}
		
		if ( ! defined('WPD_AI_UPLOADS_FOLDER') ) define( 'WPD_AI_UPLOADS_FOLDER', $this->get_wp_uploads_folder() . 'alpha-insights/' );
		if ( ! defined('WPD_AI_CSV_PATH') ) define( 'WPD_AI_CSV_PATH', WPD_AI_UPLOADS_FOLDER . 'exports/csv_files/' );
		if ( ! defined('WPD_AI_CSV_SYSTEM_PATH') ) define( 'WPD_AI_CSV_SYSTEM_PATH', WPD_AI_UPLOADS_FOLDER_SYSTEM . 'exports/csv_files/' );

		// Minimum Versions
		if ( ! defined('WPD_AI_MIN_PHP_VER') ) define( 'WPD_AI_MIN_PHP_VER', '7.4.0' );
		if ( ! defined('WPD_AI_MIN_WP_VER') ) define( 'WPD_AI_MIN_WP_VER', '5.0.0' );
		if ( ! defined('WPD_AI_MIN_WC_VER') ) define( 'WPD_AI_MIN_WC_VER', '3.0.0' );

		// APIs
		if ( ! defined('WPD_AI_FACEBOOK_API_VER') ) define( 'WPD_AI_FACEBOOK_API_VER', 'v23.0' );
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
		$pro_plugin_file = 'wp-davies-alpha-insights/wpd-alpha-insights-free.php';
		
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
	 * @return array
	 */
	public function check_compatability_and_return_notices() {

		// WP Version
		global $wp_version;

		// Message array
		$message = array();

		// Default, empty
		$results = array(
			'notices' 		=> $message,
			'version_check' => $this->version_check
		);

		// Is PHP version correct
		if ( version_compare( PHP_VERSION, WPD_AI_MIN_PHP_VER, '<' ) ) {
			
			// Set version check to fail
			$this->version_check = false;

			// Fail Message
			$message[] = sprintf(
				__( 'Alpha Insights has not been fully activated as it requires at least PHP %s to run correctly. Please upgrade your PHP version to use this plugin.', WPD_AI_TEXT_DOMAIN ),
				WPD_AI_MIN_PHP_VER
			);

			// Return Results
			return array(
				'notices' => $message,
				'version_check' => $this->version_check
			);

		}

		// Is WP Version Correct
		if ( version_compare( $wp_version, WPD_AI_MIN_WP_VER, '<' ) ) {
			
			// Set version check to fail
			$this->version_check = false;

			// Fail message
			$message[] = sprintf(
				__( 'Alpha Insights requires at least WordPress version %1$s to run. Please upgrade WordPress to use this plugin. You are currently using version %2$s', WPD_AI_TEXT_DOMAIN ),
				WPD_AI_MIN_WP_VER,
				$wp_version
			);

			// Return Results
			return array(
				'notices' => $message,
				'version_check' => $this->version_check
			);

		}

		// Is WC active
		if ( ! defined('WC_VERSION') ) {

			// Set version check to fail
			$this->version_check = false;

			// Fail Message
			$message[] = __( 'WooCommerce must be installed and activated in order to use Alpha Insights.', WPD_AI_TEXT_DOMAIN );

			// Return Results
			return array(
				'notices' => $message,
				'version_check' => $this->version_check
			);

		}

		// Is WC version correct
		if ( version_compare( WC_VERSION, WPD_AI_MIN_WC_VER, '<' ) ) {

			// Set version check to fail
			$this->version_check = false;

			// Fail Message
			$message[] = sprintf(
				__( 'Alpha Insights requires at least WooCommerce version %1$s to run. Please upgrade WooCommerce to use this plugin. You are currently using version %2$s', WPD_AI_TEXT_DOMAIN ),
				WPD_AI_MIN_WC_VER,
				WC_VERSION
			);

			// Return Results
			return array(
				'notices' => $message,
				'version_check' => $this->version_check
			);

		}

		// Check DB Version - Schedule deferred update instead of immediate
		$installed_db_version = get_option( 'wpd_ai_db_version', null );
		if ( is_string($installed_db_version) && version_compare( $installed_db_version, WPD_AI_DB_VERSION, '<' ) ) {
			// Schedule DB update to run when classes are loaded
			update_option( 'wpd_ai_pending_db_update', true );
			$this->log( 'Database version check detected outdated schema, scheduled deferred update.' );
		}

		// Returns empty default
		return $results;

	}

	/**
	 * Output error notice if we don't have dependencies
	 */
	public function output_plugin_dependency_notices() {

		$version_check = $this->check_compatability_and_return_notices();
		$notice_messages = $version_check['notices'];

		if ( is_array($notice_messages) && ! empty($notice_messages) ) {

			foreach ( $notice_messages as $message ) {

				echo '<div class="wpd-notice notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';

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
		$pro_plugin_file = 'wp-davies-alpha-insights/wpd-alpha-insights-free.php';
		if ( is_plugin_active( $pro_plugin_file ) || class_exists( 'WPD_Alpha_Insights_Plugin' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			set_transient( 'wpd_ai_free_deactivated_by_pro', true, 30 );
		}
		
		// Logging
		$this->log( 'Alpha Insights installation method has been triggered.' );
		
		// Set latest version
		update_option( 'wpd_ai_cache_version', WPD_AI_VER );
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

		// Task 2: Rewrite rules flush (requires WPD_Live_Share_Handler class)
		if ( get_option( 'wpd_ai_pending_rewrite_flush' ) ) {

			// Verify the class exists (all dependencies loaded)
			if ( class_exists( 'WPD_Live_Share_Handler' ) ) {

				$this->log( 'Flushing pending rewrite rules for live share URLs.' );
				WPD_Live_Share_Handler::flush_rewrite_rules();
				delete_option( 'wpd_ai_pending_rewrite_flush' );
				$this->log( 'Rewrite rules flushed successfully for live share URLs.' );

			} else {

				$this->log( 'WPD_Live_Share_Handler class not found during deferred flush, will retry on next admin load.' );

			}

		}

		// Task 3: Default reports installation (requires WPD_React_Report class and DB tables)
		if ( get_option( 'wpd_ai_pending_report_installation' ) ) {

			// Verify the class exists (all dependencies loaded)
			if ( class_exists( 'WPD_React_Report' ) ) {

				$this->log( 'Installing pending default reports.' );
				WPD_React_Report::import_all_default_reports( false );
				delete_option( 'wpd_ai_pending_report_installation' );
				$this->log( 'Default reports installed successfully.' );

			} else {

				$this->log( 'WPD_React_Report class not found during deferred installation, will retry on next admin load.' );

			}

		}

		// Task 4: Redirect to getting started page (after all other tasks complete)
		if ( get_transient( 'wpd_ai_activation_redirect' ) ) {

			// Delete the transient
			delete_transient( 'wpd_ai_activation_redirect' );

			// Only redirect if:
			// 1. Not in AJAX request
			// 2. Not in bulk activation
			// 3. User has capability to manage options
			// 4. Onboarding not completed
			if ( 
				! wp_doing_ajax() 
				&& ! isset( $_GET['activate-multi'] ) 
				&& current_user_can( 'manage_options' ) 
				&& ! get_option( 'wpd_ai_onboarding_completed' )
			) {

				$this->log( 'Redirecting to getting started page.' );
				
				// Perform redirect
				wp_safe_redirect( admin_url( 'admin.php?page=' . WPD_Admin_Menu::$getting_started_slug ) );
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
		$this->load_textdomain();

		// Pro version updating
		if ( WPD_AI_PRO ) $this->check_for_updates();

	}

	/**
	 * Setup all our files
	 */
	public function include_plugin_files() {

		// Functions
		require_once( WPD_AI_PATH . 'includes/wpd-functions.php');
		require_once( WPD_AI_PATH . 'includes/functions/wpd-hpos-compatability-functions.php');
		require_once( WPD_AI_PATH . 'includes/functions/wpd-currency-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-url-parsing-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-csv-pdf-functions.php' );
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
		require_once( WPD_AI_PATH . 'includes/emails/wpd-email-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-subscription-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-google-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-facebook-functions.php' );
		require_once( WPD_AI_PATH . 'includes/functions/wpd-custom-cost-functions.php' );
		
		// Admin
		require_once( WPD_AI_PATH . 'includes/admin/wpd-admin-page-content.php');
		require_once( WPD_AI_PATH . 'includes/admin/wpd-settings.php');

		// Framework
		require_once( WPD_AI_PATH . 'includes/classes/WPD_Alpha_Insights_Core.php');
		require_once( WPD_AI_PATH . 'includes/wpd-scripts-styles.php' );
		require_once( WPD_AI_PATH . 'includes/wpd-ajax.php' );

		// Register Custom Post Types
		if ( WPD_AI_PRO ) {
			require_once( WPD_AI_PATH . 'includes/custom-post-types/expense-custom-post-type.php');
			require_once( WPD_AI_PATH . 'includes/custom-post-types/facebook-campaigns-custom-post-type.php');
			require_once( WPD_AI_PATH . 'includes/custom-post-types/google-ad-campaigns-custom-post-type.php');
		}

		// Additional Classes -> No Dependencies
		require_once( WPD_AI_PATH . 'includes/classes/WPD_Action_Scheduler.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPD_Order_Calculator.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPD_Database_Interactor.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPD_User_Agent.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPD_Traffic_Type.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPD_CSV_Exporter.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPD_Session_Tracking.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPD_Task_Runner.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPD_Admin_Menu.php');		
		require_once( WPD_AI_PATH . 'includes/classes/WPD_Report_API.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPD_Getting_Started.php');

		// Additional Classes - With Dependencies
		require_once( WPD_AI_PATH . 'includes/classes/WPD_Data_Warehouse_React.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPD_React_Report.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPD_Report_Filters.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPD_WooCommerce_Events.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPD_Cost_Of_Goods_Manager.php');
		require_once( WPD_AI_PATH . 'includes/classes/WPD_Expense_Management_React.php');

		// Pro API classes
		if ( WPD_AI_PRO ) {
			require_once( WPD_AI_PATH . 'includes/classes/pro/WPD_Live_Share_Handler.php');
			require_once( WPD_AI_PATH . 'includes/classes/pro/WPD_Authenticator.php');
			require_once( WPD_AI_PATH . 'includes/classes/pro/WPD_Facebook_API.php');
			require_once( WPD_AI_PATH . 'includes/classes/pro/WPD_Facebook_Auth.php');
			require_once( WPD_AI_PATH . 'includes/classes/pro/WPD_Google_Ads_API.php');
			require_once( WPD_AI_PATH . 'includes/classes/pro/WPD_Google_Ads_Auth.php');

			// Load AJAX actions
			new WPD_Facebook_Auth(); // Initialize Facebook Auth (registers AJAX handlers)
			new WPD_Google_Ads_Auth(); // Initialize Google Ads Auth (registers AJAX handlers)
		} else {
			require_once( WPD_AI_PATH . 'includes/classes/WPD_Alpha_Insights_Notices.php');
		}

		// Register Relevant Actions
		WPD_React_Report::register_ajax_actions();
		WPD_Cost_Of_Goods_Manager::register_ajax_actions();
		WPD_Report_API::register_routes();
		WPD_Expense_Management_React::register_ajax_actions();

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
	 * Fetch updates
	 */
	public function check_for_updates() {

		if ( get_option( 'wpd_ai_api_key', false ) ) {

			// Just in case plugin isn't loaded
			if ( function_exists('wpd_fetch_for_updates') && WPD_AI_PRO ) {
				wpd_fetch_for_updates();
			}

		}

	}

	/**
	 * Load translator
	 */
	public function load_textdomain() {

		load_plugin_textdomain( WPD_AI_TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)).'/languages/' );

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
				<p><strong><?php esc_html_e( 'Alpha Insights:', WPD_AI_TEXT_DOMAIN ); ?></strong> <?php esc_html_e( 'The free version has been automatically deactivated because Alpha Insights Pro is active. Please deactivate the pro version first if you wish to use the free version.', WPD_AI_TEXT_DOMAIN ); ?></p>
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
			if ( function_exists( 'wpd_admin_notice' ) ) {
				wpd_admin_notice( __( 'Your Alpha Insights Order Cache is currently being updated, depending on how many orders you have this may take a few minutes.', WPD_AI_TEXT_DOMAIN ) );
			}

		}

		// License failed
		if ( isset( $_GET['wpd-notice'] ) && sanitize_text_field( $_GET['wpd-notice'] ) == 'invalid-license' ) {

			// Output notice - check if function exists
			if ( function_exists( 'wpd_admin_notice' ) ) {
				wpd_admin_notice( __( 'Your Alpha Insights license has expired or is invalid. Please update your license to continue using the plugin.', WPD_AI_TEXT_DOMAIN ) );
			}

		}

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
		if ( function_exists( 'wpd_delete_all_data_caches' ) ) {
			wpd_delete_all_data_caches();
		}

		// Set latest version since this has been cleared
		$this->log( 'Storing your cache version in the DB.' );
		update_option( 'wpd_ai_cache_version', WPD_AI_VER );

	}

	/**
	 * Create tables and data
	 *
	 * @return array
	 */
	private function create_tables_and_data() {

		$response = array();

		$this->log( 'Verifying the database structure, updating if required.' );

		if ( ! class_exists('WPD_Database_Interactor') ) {
			require_once( WPD_AI_PATH . 'includes/classes/WPD_Database_Interactor.php');
		}

		$db_interactor = new WPD_Database_Interactor();
		$this->log( 'DB interactor has been initialized, going to attempt to update the DB to the latest version.' );

		if ( is_object( $db_interactor ) && method_exists( $db_interactor, 'create_update_tables_columns' ) ) {

			$db_upgrade_response = $db_interactor->create_update_tables_columns();
			$this->log( 'Executing DB upgrader.' );

			if ( $db_upgrade_response ) {

				$response['success']	= true;
				$response['message']	= __( 'DB Upgrade completed successfully, you can check the Alpha Insights logs for more details if required.', WPD_AI_TEXT_DOMAIN );
				$this->log( 'DB Upgrade completed successfully, you can check the Alpha Insights logs for more details if required.' );

			} else {

				$response['success']	= false;
				$response['message']	= __( 'Error occurred during DB upgrade, please check the Alpha Insights logs for more details.', WPD_AI_TEXT_DOMAIN );
				$this->log( 'Error occurred during DB upgrade, please check the Alpha Insights logs for more details.' );

			}

		} else {

			$response['success']	= false;
			$response['message']	= __( 'We couldn\'t complete this action unfortunately, feel free to shoot us an email and we\'ll help you resolve this.', WPD_AI_TEXT_DOMAIN );
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
	 * Fix wp_upload_dir() not using https
	 *
	 * @return string
	 */
	private function get_wp_uploads_folder() {

		$upload_dir = wp_upload_dir();
		
		// Check if wp_upload_dir() returned valid data
		if ( ! $upload_dir || ! is_array( $upload_dir ) || ! isset( $upload_dir['baseurl'] ) ) {
			// Fallback to content_url/uploads
			$url = trailingslashit( content_url( 'uploads' ) );
		} else {
			$url = trailingslashit( $upload_dir['baseurl'] );
		}
		
		// Ensure HTTPS if SSL is enabled
		if ( is_ssl() ) {
			$url = str_replace( 'http://', 'https://', $url );
		}
		
		return $url;
	
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

		// Check if function exists before using
		if ( function_exists( 'wpd_admin_page_url' ) ) {
			$new_links[] = '<a href="' . esc_url( wpd_admin_page_url('settings') ) . '">' . esc_html__( 'Settings', WPD_AI_TEXT_DOMAIN ) . '</a>';
		}
		
		$new_links[] = '<a href="' . esc_url( 'https://wpdavies.dev/docs/alpha-insights/' ) . '" target="_blank">' . esc_html__( 'Docs', WPD_AI_TEXT_DOMAIN ) . '</a>';	

		if ( ! WPD_AI_PRO ) {

			// Build upgrade URL with query parameters for tracking
			$upgrade_url = add_query_arg(
				array(
					'utm_source' => 'wordpress',
					'utm_medium' => 'plugin_action_links',
					'utm_campaign' => 'alpha_insights_free_upgrade',
					'utm_content' => 'upgrade_to_pro_link'
				),
				'https://wpdavies.dev/plugins/alpha-insights/'
			);
			
			// Add the "Upgrade to Pro" link at the beginning
			$new_links[] = '<a href="' . esc_url($upgrade_url) . '" target="_blank" style="color: #2271b1; font-weight: 600;">' . esc_html__('Upgrade to Pro', WPD_AI_TEXT_DOMAIN) . '</a>';
			
		}

		$links = array_merge( $new_links, $links );

		return $links;

	}

}

// Initialize the singleton instance
WPD_Alpha_Insights_Free_Plugin::get_instance();
