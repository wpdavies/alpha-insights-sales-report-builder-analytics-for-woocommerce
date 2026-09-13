<?php
/**
 * Cache-safe analytics helper functions.
 *
 * @package Alpha Insights
 */
defined( 'ABSPATH' ) || exit;

/**
 * Apply a canonical analytics filter, then the retired v2 hook name.
 *
 * @param string $hook        Current filter name.
 * @param string $legacy_hook Previous v2 filter name.
 * @param mixed  $value       Filtered value.
 * @return mixed
 */
function wpdai_apply_analytics_filter( $hook, $legacy_hook, $value ) {
	$args = array_slice( func_get_args(), 2 );
	$value = apply_filters( $hook, ...$args );
	$args[0] = $value;
	return apply_filters( $legacy_hook, ...$args );
}

/**
 * Whether cache-safe event tracking is enabled.
 *
 * Always on. The former legacy opt-out setting is ignored.
 *
 * @return bool
 */
function wpdai_is_cache_safe_tracking_enabled() {
	return (bool) apply_filters( 'wpd_ai_cache_safe_tracking_enabled', true );
}

/**
 * Whether deprecated legacy PHP-cookie event tracking is active.
 *
 * Always false. Cache-safe analytics is the only loader.
 *
 * @return bool
 */
function wpdai_is_legacy_event_tracking_enabled() {
	return false;
}

/**
 * Cookie storage mode for cache-safe analytics.
 *
 * @return string checkout_only|immediate
 */
function wpdai_get_analytics_cookie_storage_mode() {
	$mode = 'checkout_only';

	if ( function_exists( 'wpdai_get_analytics_settings' ) ) {
		$settings = wpdai_get_analytics_settings();
		if ( ! empty( $settings['cookie_storage_mode'] ) && in_array( $settings['cookie_storage_mode'], array( 'checkout_only', 'immediate' ), true ) ) {
			$mode = $settings['cookie_storage_mode'];
		}
	}

	return (string) apply_filters( 'wpd_ai_cookie_storage_mode', $mode );
}

/**
 * Whether a new session with UTM or click IDs should replace stored attribution.
 *
 * @return bool
 */
function wpdai_should_override_attribution_on_new_utm() {
	$enabled = true;

	if ( function_exists( 'wpdai_get_analytics_settings' ) ) {
		$settings = wpdai_get_analytics_settings();
		$enabled  = ! empty( $settings['override_attribution_on_new_utm'] );
	}

	return (bool) apply_filters( 'wpd_ai_override_attribution_on_new_utm', $enabled );
}

/**
 * Session inactivity timeout in seconds for the analytics client.
 *
 * @return int
 */
function wpdai_get_analytics_session_timeout_seconds() {
	return (int) apply_filters( 'wpd_session_timeout_seconds', 30 * MINUTE_IN_SECONDS );
}

/**
 * Return the active event tracking instance.
 *
 * @return WPDAI_Event_Tracking|WPDAI_WooCommerce_Event_Tracking
 */
function wpdai_get_event_tracking_instance() {
	if ( class_exists( 'WPDAI_Event_Tracking' ) ) {
		return WPDAI_Event_Tracking::get_instance();
	}

	return WPDAI_WooCommerce_Event_Tracking::get_instance();
}

/**
 * Allowed columns for the WooCommerce events table.
 *
 * @return array<int, string>
 */
function wpdai_get_event_table_columns() {
	$columns = array(
		'session_id',
		'ip_address',
		'user_id',
		'page_href',
		'object_type',
		'object_id',
		'event_type',
		'event_quantity',
		'event_value',
		'product_id',
		'variation_id',
		'date_created_gmt',
		'additional_data',
	);

	return (array) apply_filters( 'wpd_ai_event_table_columns', $columns );
}

/**
 * Strip non-table fields and normalize values before DB insert.
 *
 * @param array<string, mixed> $data Event row data.
 * @return array<string, mixed>
 */
function wpdai_prepare_event_row_for_db( $data ) {
	if ( ! is_array( $data ) ) {
		return array();
	}

	unset( $data['nonce'], $data['event-tracking-token'], $data['landing_page'], $data['referral_source'] );

	if ( ! isset( $data['additional_data'] ) || ( is_array( $data['additional_data'] ) && empty( $data['additional_data'] ) ) ) {
		$data['additional_data'] = null;
	} elseif ( is_array( $data['additional_data'] ) ) {
		$data['additional_data'] = wp_json_encode( $data['additional_data'] );
	}

	return array_intersect_key( $data, array_flip( wpdai_get_event_table_columns() ) );
}

/**
 * @deprecated 5.10.0 Use wpdai_get_analytics_cookie_storage_mode().
 *
 * @return string
 */
function wpdai_get_analytics_v2_cookie_storage_mode() {
	return wpdai_get_analytics_cookie_storage_mode();
}

/**
 * @deprecated 5.10.0 Use wpdai_get_analytics_session_timeout_seconds().
 *
 * @return int
 */
function wpdai_get_analytics_v2_session_timeout_seconds() {
	return wpdai_get_analytics_session_timeout_seconds();
}
