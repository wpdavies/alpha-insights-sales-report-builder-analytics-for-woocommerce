<?php
/**
 * Cache-safe analytics v2 helper functions.
 *
 * @package Alpha Insights
 */
defined( 'ABSPATH' ) || exit;

/**
 * Whether cache-safe tracking beta is enabled.
 *
 * @return bool
 */
function wpdai_is_cache_safe_tracking_enabled() {
	$enabled = false;

	if ( function_exists( 'wpdai_get_analytics_settings' ) ) {
		$settings = wpdai_get_analytics_settings();
		$enabled  = ! empty( $settings['enable_cache_safe_tracking_beta'] );
	}

	return (bool) apply_filters( 'wpd_ai_cache_safe_tracking_enabled', $enabled );
}

/**
 * Cookie storage mode for v2 analytics.
 *
 * @return string checkout_only|immediate
 */
function wpdai_get_analytics_v2_cookie_storage_mode() {
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
 * Session inactivity timeout in seconds for v2 client.
 *
 * @return int
 */
function wpdai_get_analytics_v2_session_timeout_seconds() {
	return (int) apply_filters( 'wpd_session_timeout_seconds', 30 * MINUTE_IN_SECONDS );
}

/**
 * Return the active event tracking instance (legacy or v2).
 *
 * @return WPDAI_WooCommerce_Event_Tracking|WPDAI_Event_Tracking_V2
 */
function wpdai_get_event_tracking_instance() {
	if ( wpdai_is_cache_safe_tracking_enabled() && class_exists( 'WPDAI_Event_Tracking_V2' ) ) {
		return WPDAI_Event_Tracking_V2::get_instance();
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
