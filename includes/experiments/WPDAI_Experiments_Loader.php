<?php
/**
 * Bootstrap the experiments module.
 *
 * @package Alpha Insights
 * @since 5.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPDAI_Experiments_Loader
 */
class WPDAI_Experiments_Loader {

	/**
	 * Boot experiments.
	 *
	 * @return void
	 */
	public static function init() {
		require_once WPD_AI_PATH . 'includes/experiments/wpd-experiment-functions.php';
		require_once WPD_AI_PATH . 'includes/experiments/WPDAI_Experiment_Store.php';
		require_once WPD_AI_PATH . 'includes/experiments/WPDAI_Experiment_Eligibility.php';
		require_once WPD_AI_PATH . 'includes/experiments/WPDAI_Experiment_Assigner.php';
		require_once WPD_AI_PATH . 'includes/experiments/WPDAI_Experiment_Cache.php';
		require_once WPD_AI_PATH . 'includes/experiments/WPDAI_Experiment_Runtime.php';
		require_once WPD_AI_PATH . 'includes/experiments/WPDAI_Experiments_Admin.php';

		WPDAI_Experiment_Cache::init();
		WPDAI_Experiment_Runtime::init();
		WPDAI_Experiments_Admin::init();

		add_filter( 'wpd_ai_child_page_register', array( __CLASS__, 'register_child_pages' ) );
		add_filter( 'wpd_alpha_insights_menu_items', array( __CLASS__, 'filter_menu_items' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_install_default_report' ) );
	}

	/**
	 * Install the experiments default report if it is missing.
	 *
	 * @return void
	 */
	public static function maybe_install_default_report() {
		if ( ! class_exists( 'WPDAI_Report_Builder' ) ) {
			return;
		}
		$file = WPD_AI_PATH . 'includes/reports/dashboard-config-experiments.json';
		if ( ! file_exists( $file ) ) {
			return;
		}
		$json = file_get_contents( $file );
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || empty( $data['dashboard_id'] ) ) {
			return;
		}

		$existing    = get_option( 'wpd_dashboard_config_experiments' );
		$new_version = isset( $data['version_number'] ) ? (string) $data['version_number'] : '1.0';
		$old_version = ( is_array( $existing ) && ! empty( $existing['version_number'] ) ) ? (string) $existing['version_number'] : '0';
		if ( false !== $existing && version_compare( $new_version, $old_version, '<=' ) ) {
			return;
		}

		try {
			WPDAI_Report_Builder::import_single_default_report( $data['dashboard_id'], $data );
		} catch ( Exception $e ) {
			if ( function_exists( 'wpdai_write_log' ) ) {
				wpdai_write_log( 'Could not install experiments default report: ' . $e->getMessage(), 'experiments' );
			}
		}
	}

	/**
	 * Register the Experiments admin page.
	 *
	 * @param array $child_pages Pages.
	 * @return array
	 */
	public static function register_child_pages( $child_pages ) {
		$child_pages[] = array(
			'parent_slug'   => WPDAI_Admin_Menu::$top_level_menu_slug,
			'page_title'    => __( 'Experiment (Beta)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'menu_title'    => __( 'Experiments', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'menu_slug'     => WPDAI_Admin_Menu::$experiments_slug,
			'page_callback' => 'wpdai_experiments_page',
			'menu_position' => 25,
		);
		return $child_pages;
	}

	/**
	 * Third-level Experiments menu.
	 *
	 * @param array $menu_items Menu items.
	 * @return array
	 */
	public static function filter_menu_items( $menu_items ) {
		$base = admin_url( 'admin.php' ) . '?page=' . WPDAI_Admin_Menu::$experiments_slug;

		$menu_items[ WPDAI_Admin_Menu::$experiments_slug ] = array(
			'title'              => __( 'Experiments', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'url'                => $base,
			'icon'               => null,
			'additional_classes' => array(),
			'menu_order'         => 25,
			'children'           => array(
				'all_experiments'  => array(
					'title'              => __( 'All Experiments', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'url'                => $base,
					'page'               => WPDAI_Admin_Menu::$experiments_slug,
					'subpage'            => '',
					'icon'               => null,
					'additional_classes' => array(),
				),
				'add_experiment'   => array(
					'title'              => __( 'Add New', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'url'                => $base . '&subpage=edit',
					'page'               => WPDAI_Admin_Menu::$experiments_slug,
					'subpage'            => 'edit',
					'icon'               => null,
					'additional_classes' => array(),
				),
				'experiment_results' => array(
					'title'              => __( 'Report', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'url'                => admin_url( 'admin.php' ) . '?page=' . WPDAI_Admin_Menu::$website_traffic_slug . '&subpage=experiments',
					'page'               => WPDAI_Admin_Menu::$website_traffic_slug,
					'subpage'            => 'experiments',
					'icon'               => null,
					'additional_classes' => array(),
				),
			),
		);

		return $menu_items;
	}
}
