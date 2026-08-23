<?php
/**
 * Bootstrap cache-safe analytics when enabled (default since 5.6.6).
 *
 * @package Alpha Insights
 */
defined( 'ABSPATH' ) || exit;

/**
 * Class WPDAI_Analytics_V2_Loader
 */
class WPDAI_Analytics_V2_Loader {

	/**
	 * Boot v2 module.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_boot' ), 5 );
	}

	/**
	 * Load cache-safe tracking when analytics are enabled.
	 *
	 * @return void
	 */
	public static function maybe_boot() {
		if ( ! wpdai_is_cache_safe_tracking_enabled() || ! wpdai_is_analytics_enabled() ) {
			return;
		}

		require_once WPD_AI_PATH . 'includes/analytics-v2/WPDAI_Client_IP.php';
		require_once WPD_AI_PATH . 'includes/analytics-v2/WPDAI_Session_Context.php';
		require_once WPD_AI_PATH . 'includes/analytics-v2/WPDAI_Event_Tracking_V2.php';
		require_once WPD_AI_PATH . 'includes/analytics-v2/WPDAI_Analytics_V2_Scripts.php';
		require_once WPD_AI_PATH . 'includes/analytics-v2/WPDAI_Cache_Compatibility.php';

		WPDAI_Event_Tracking_V2::get_instance();
		WPDAI_Analytics_V2_Scripts::init();
		WPDAI_Cache_Compatibility::init();
	}
}

WPDAI_Analytics_V2_Loader::init();
