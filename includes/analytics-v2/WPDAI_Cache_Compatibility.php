<?php
/**
 * Cache plugin compatibility for cache-safe analytics v2.
 *
 * @package Alpha Insights
 */
defined( 'ABSPATH' ) || exit;

/**
 * Class WPDAI_Cache_Compatibility
 */
class WPDAI_Cache_Compatibility {

	/**
	 * Register cache compatibility hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! wpdai_is_cache_safe_tracking_enabled() ) {
			return;
		}

		add_filter( 'rocket_cache_reject_cookies', array( __CLASS__, 'filter_rocket_cache_reject_cookies' ), 20 );
		add_filter( 'litespeed_vary_cookies', array( __CLASS__, 'filter_litespeed_vary_cookies' ), 20 );
	}

	/**
	 * Cookies that should not bust page cache in checkout_only mode.
	 *
	 * @return array
	 */
	public static function get_ignore_cookies() {
		$cookies = array(
			'wpd_ai_session_id',
			'wpd_ai_landing_page',
			'wpd_ai_referral_source',
			'wpd_ai_engaged_session',
		);

		return (array) apply_filters( 'wpd_ai_v2_cache_ignore_cookies', $cookies );
	}

	/**
	 * WP Rocket: do not reject cache when only analytics cookies are present (checkout_only).
	 *
	 * @param array $cookies Rejected cookies.
	 * @return array
	 */
	public static function filter_rocket_cache_reject_cookies( $cookies ) {
		if ( 'checkout_only' !== wpdai_get_analytics_v2_cookie_storage_mode() ) {
			return $cookies;
		}

		if ( ! is_array( $cookies ) ) {
			return $cookies;
		}

		return array_values( array_diff( $cookies, self::get_ignore_cookies() ) );
	}

	/**
	 * LiteSpeed: exclude analytics cookies from vary list in checkout_only mode.
	 *
	 * @param array $cookies Vary cookies.
	 * @return array
	 */
	public static function filter_litespeed_vary_cookies( $cookies ) {
		if ( 'checkout_only' !== wpdai_get_analytics_v2_cookie_storage_mode() ) {
			return $cookies;
		}

		if ( ! is_array( $cookies ) ) {
			return $cookies;
		}

		return array_values( array_diff( $cookies, self::get_ignore_cookies() ) );
	}
}
