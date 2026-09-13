<?php
/**
 * Frontend script registration for cache-safe analytics.
 *
 * @package Alpha Insights
 */
defined( 'ABSPATH' ) || exit;

/**
 * Class WPDAI_Analytics_Scripts
 */
class WPDAI_Analytics_Scripts {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ), 20 );
		add_filter( 'cfw_blocked_script_handles', array( __CLASS__, 'allow_checkout_assets' ), 20 );
	}

	/**
	 * Keep analytics scripts when CheckoutWC strips theme/plugin handles.
	 *
	 * @param string[] $handles Blocked handles.
	 * @return string[]
	 */
	public static function allow_checkout_assets( $handles ) {
		if ( ! is_array( $handles ) ) {
			return $handles;
		}

		return array_values(
			array_diff(
				$handles,
				array(
					'wpd-ai-client',
					'wpd-ai-event-tracking',
					'wpd-ai-client-v2',
					'wpd-ai-event-tracking-v2',
				)
			)
		);
	}

	/**
	 * Enqueue analytics client + event tracking scripts.
	 *
	 * @return void
	 */
	public static function enqueue_scripts() {
		if ( ! wpdai_is_analytics_enabled() ) {
			return;
		}

		$tracking = WPDAI_Event_Tracking::get_instance();

		if ( did_action( 'template_redirect' ) ) {
			$tracking->setup_object_type_id();
		}

		$client_src  = WPD_AI_URL_PATH . 'assets/js/analytics/wpd-ai-client.js';
		$tracking_src = WPD_AI_URL_PATH . 'assets/js/analytics/wpd-ai-event-tracking.js';

		wp_register_script(
			'wpd-ai-client',
			$client_src,
			array(),
			WPD_AI_VER,
			true
		);

		wp_register_script(
			'wpd-ai-event-tracking',
			$tracking_src,
			array( 'jquery', 'wpd-ai-client' ),
			WPD_AI_VER,
			true
		);

		// Retired handles: empty src, depend on the current scripts so old dequeue/enqueue still resolves.
		wp_register_script( 'wpd-ai-client-v2', false, array( 'wpd-ai-client' ), WPD_AI_VER, true );
		wp_register_script( 'wpd-ai-event-tracking-v2', false, array( 'wpd-ai-event-tracking' ), WPD_AI_VER, true );

		$is_cart     = function_exists( 'is_cart' ) && is_cart();
		$is_checkout = function_exists( 'is_checkout' ) && is_checkout();

		$client_config = array(
			'session_timeout_seconds'     => wpdai_get_analytics_session_timeout_seconds(),
			'attribution_timeout_seconds' => WPDAI_Session_Tracking::get_attribution_timeout_seconds(),
			'attribution_session_only'    => WPDAI_Session_Tracking::is_session_only_attribution() ? 1 : 0,
			'cookie_domain'               => WPDAI_Session_Tracking::get_cookie_domain(),
			'cookie_storage_mode'         => wpdai_get_analytics_cookie_storage_mode(),
			'override_attribution_on_new_utm' => wpdai_should_override_attribution_on_new_utm() ? 1 : 0,
			'is_cart'                     => $is_cart,
			'is_checkout'                 => $is_checkout,
		);

		wp_localize_script( 'wpd-ai-client', 'wpdAiClientConfig', $client_config );

		$event_config = array(
			'api_endpoint'                   => $tracking->api_url,
			'current_post_type'              => $tracking->object_type,
			'current_post_id'                => $tracking->object_id,
			'track_engaged_sessions'         => $tracking->only_track_engaged_sessions,
			'event_tracking_enabled'         => $tracking->event_tracking_enabled,
			'analytics_event_tracking_token' => wpdai_get_analytics_event_tracking_token(),
			'enbable_event_tracking_logging' => apply_filters( 'wpd_ai_event_tracking_enable_logging', false ) ? 1 : 0,
		);

		wp_localize_script( 'wpd-ai-event-tracking', 'wpdAlphaInsightsEventTracking', $event_config );

		wp_enqueue_script( 'wpd-ai-client' );
		wp_enqueue_script( 'wpd-ai-event-tracking' );
	}
}

class_alias( 'WPDAI_Analytics_Scripts', 'WPDAI_Analytics_V2_Scripts' );
