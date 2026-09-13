<?php
/**
 * Bootstrap cache-safe analytics (the only frontend tracking loader).
 *
 * @package Alpha Insights
 */
defined( 'ABSPATH' ) || exit;

/**
 * Class WPDAI_Analytics_Loader
 */
class WPDAI_Analytics_Loader {

	/**
	 * Boot analytics.
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
		if ( ! wpdai_is_analytics_enabled() ) {
			return;
		}

		require_once WPD_AI_PATH . 'includes/analytics/WPDAI_Client_IP.php';
		require_once WPD_AI_PATH . 'includes/analytics/WPDAI_Session_Context.php';
		require_once WPD_AI_PATH . 'includes/analytics/WPDAI_Event_Tracking.php';
		require_once WPD_AI_PATH . 'includes/analytics/WPDAI_Analytics_Scripts.php';
		require_once WPD_AI_PATH . 'includes/analytics/WPDAI_Cache_Compatibility.php';

		WPDAI_Event_Tracking::get_instance();
		WPDAI_Analytics_Scripts::init();
		WPDAI_Cache_Compatibility::init();
	}
}

class_alias( 'WPDAI_Analytics_Loader', 'WPDAI_Analytics_V2_Loader' );

WPDAI_Analytics_Loader::init();
