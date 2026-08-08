<?php
/**
 * Frontend script registration for cache-safe analytics v2.
 *
 * @package Alpha Insights
 */
defined( 'ABSPATH' ) || exit;

/**
 * Class WPDAI_Analytics_V2_Scripts
 */
class WPDAI_Analytics_V2_Scripts {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ), 20 );
	}

	/**
	 * Enqueue v2 client + event tracking scripts.
	 *
	 * @return void
	 */
	public static function enqueue_scripts() {
		if ( ! wpdai_is_cache_safe_tracking_enabled() || ! wpdai_is_analytics_enabled() ) {
			return;
		}

		$tracking = WPDAI_Event_Tracking_V2::get_instance();

		if ( did_action( 'template_redirect' ) ) {
			$tracking->setup_object_type_id();
		}

		wp_register_script(
			'wpd-ai-client-v2',
			WPD_AI_URL_PATH . 'assets/js/analytics-v2/wpd-ai-client.js',
			array(),
			WPD_AI_VER,
			true
		);

		wp_register_script(
			'wpd-ai-event-tracking-v2',
			WPD_AI_URL_PATH . 'assets/js/analytics-v2/wpd-ai-event-tracking.js',
			array( 'jquery', 'wpd-ai-client-v2' ),
			WPD_AI_VER,
			true
		);

		$is_cart     = function_exists( 'is_cart' ) && is_cart();
		$is_checkout = function_exists( 'is_checkout' ) && is_checkout();

		$client_config = array(
			'session_timeout_seconds'     => wpdai_get_analytics_v2_session_timeout_seconds(),
			'attribution_timeout_seconds' => WPDAI_Session_Tracking::get_attribution_timeout_seconds(),
			'attribution_session_only'    => WPDAI_Session_Tracking::is_session_only_attribution() ? 1 : 0,
			'cookie_domain'               => WPDAI_Session_Tracking::get_cookie_domain(),
			'cookie_storage_mode'         => wpdai_get_analytics_v2_cookie_storage_mode(),
			'is_cart'                     => $is_cart,
			'is_checkout'                 => $is_checkout,
		);

		wp_localize_script( 'wpd-ai-client-v2', 'wpdAiClientConfig', $client_config );

		$event_config = array(
			'api_endpoint'                   => $tracking->api_url,
			'current_post_type'              => $tracking->object_type,
			'current_post_id'                => $tracking->object_id,
			'track_engaged_sessions'         => $tracking->only_track_engaged_sessions,
			'event_tracking_enabled'         => $tracking->event_tracking_enabled,
			'analytics_event_tracking_token' => wpdai_get_analytics_event_tracking_token(),
			'enbable_event_tracking_logging' => apply_filters( 'wpd_ai_event_tracking_enable_logging', false ) ? 1 : 0,
		);

		wp_localize_script( 'wpd-ai-event-tracking-v2', 'wpdAlphaInsightsEventTracking', $event_config );

		wp_enqueue_script( 'wpd-ai-client-v2' );
		wp_enqueue_script( 'wpd-ai-event-tracking-v2' );
	}
}
