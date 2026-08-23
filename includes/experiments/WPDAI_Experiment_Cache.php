<?php
/**
 * Page-cache exclusions for running experiments.
 *
 * Analytics cookies stay cache-ignored. Only URLs targeted by a running
 * experiment miss cache, and only for the life of the test.
 *
 * @package Alpha Insights
 * @since 5.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPDAI_Experiment_Cache
 */
class WPDAI_Experiment_Cache {

	/**
	 * Register cache-plugin hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'rocket_cache_reject_uri', array( __CLASS__, 'filter_rocket_cache_reject_uri' ), 20 );
		add_action( 'litespeed_init', array( __CLASS__, 'maybe_set_litespeed_nocache' ) );
	}

	/**
	 * Mark the current response uncacheable.
	 *
	 * @return void
	 */
	public static function bypass_current_request() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		if ( function_exists( 'litespeed_control_set_nocache' ) ) {
			litespeed_control_set_nocache( 'alpha-insights-experiment' );
		}

		do_action( 'litespeed_control_set_nocache', 'alpha-insights-experiment' );
		do_action( 'wpd_ai_experiment_bypass_cache' );
	}

	/**
	 * WP Rocket: extra reject URIs while tests run.
	 *
	 * @param array $uris Existing URIs.
	 * @return array
	 */
	public static function filter_rocket_cache_reject_uri( $uris ) {
		if ( ! is_array( $uris ) ) {
			$uris = array();
		}

		foreach ( self::get_reject_patterns() as $pattern ) {
			if ( ! in_array( $pattern, $uris, true ) ) {
				$uris[] = $pattern;
			}
		}

		return $uris;
	}

	/**
	 * LiteSpeed request-time nocache if this URL is targeted.
	 *
	 * @return void
	 */
	public static function maybe_set_litespeed_nocache() {
		if ( is_admin() ) {
			return;
		}
		if ( WPDAI_Experiment_Runtime::request_matches_running_experiment() ) {
			self::bypass_current_request();
		}
	}

	/**
	 * Regex/path patterns suitable for cache plugins.
	 *
	 * @return array
	 */
	public static function get_reject_patterns() {
		$patterns = array();

		foreach ( WPDAI_Experiment_Store::get_running() as $experiment ) {
			$pages = wpdai_experiment_effective_page_rules( isset( $experiment['targeting'] ) ? $experiment['targeting'] : array() );
			if ( empty( $pages ) ) {
				$patterns[] = '/.*';
				continue;
			}

			foreach ( $pages as $rule ) {
				$type  = isset( $rule['type'] ) ? $rule['type'] : '';
				$value = isset( $rule['value'] ) ? (string) $rule['value'] : '';
				$pattern = self::rule_to_pattern( $type, $value );
				if ( $pattern ) {
					$patterns[] = $pattern;
				}
			}
		}

		return array_values( array_unique( $patterns ) );
	}

	/**
	 * Convert a targeting rule to a cache-exclusion pattern.
	 *
	 * @param string $type  Rule type.
	 * @param string $value Rule value.
	 * @return string|null
	 */
	protected static function rule_to_pattern( $type, $value ) {
		switch ( $type ) {
			case 'path_equals':
				$path = '/' !== $value ? untrailingslashit( $value ) : '/';
				if ( '' !== $path && '/' !== substr( $path, 0, 1 ) ) {
					$path = '/' . $path;
				}
				return $path;

			case 'path_contains':
				return '' !== $value ? '.*' . preg_quote( $value, '/' ) . '.*' : null;

			case 'path_starts_with':
				$prefix = $value;
				if ( '' !== $prefix && '/' !== substr( $prefix, 0, 1 ) ) {
					$prefix = '/' . $prefix;
				}
				return '' !== $prefix ? preg_quote( $prefix, '/' ) . '.*' : null;

			case 'wc_cart':
				if ( function_exists( 'wc_get_cart_url' ) ) {
					$path = wp_parse_url( wc_get_cart_url(), PHP_URL_PATH );
					return $path ? untrailingslashit( $path ) : '/cart';
				}
				return '/cart';

			case 'wc_checkout':
				if ( function_exists( 'wc_get_checkout_url' ) ) {
					$path = wp_parse_url( wc_get_checkout_url(), PHP_URL_PATH );
					return $path ? untrailingslashit( $path ) : '/checkout';
				}
				return '/checkout';

			case 'wc_shop':
				if ( function_exists( 'wc_get_page_permalink' ) ) {
					$path = wp_parse_url( wc_get_page_permalink( 'shop' ), PHP_URL_PATH );
					return $path ? untrailingslashit( $path ) : '/shop';
				}
				return '/shop';

			case 'wc_product':
			case 'post_type':
			case 'regex':
				return '/.*';
		}

		return null;
	}

	/**
	 * After an experiment starts or stops, purge targeted URLs.
	 *
	 * @param array  $experiment      Experiment.
	 * @param string $previous_status Previous status.
	 * @return void
	 */
	public static function on_status_change( $experiment, $previous_status ) {
		self::purge_experiment_urls( $experiment );

		if ( function_exists( 'rocket_clean_domain' ) && wpdai_experiment_targeting_is_broad( $experiment['targeting'] ?? array() ) ) {
			rocket_clean_domain();
		}

		if ( class_exists( 'LiteSpeed\Purge' ) ) {
			do_action( 'litespeed_purge_all', 'alpha-insights-experiment' );
		}
	}

	/**
	 * Purge known targeted URLs.
	 *
	 * @param array $experiment Experiment.
	 * @return void
	 */
	public static function purge_experiment_urls( $experiment ) {
		$urls  = array();
		$pages = wpdai_experiment_effective_page_rules( isset( $experiment['targeting'] ) ? $experiment['targeting'] : array() );

		foreach ( $pages as $rule ) {
			$type  = isset( $rule['type'] ) ? $rule['type'] : '';
			$value = isset( $rule['value'] ) ? (string) $rule['value'] : '';
			$url   = self::rule_to_url( $type, $value );
			if ( $url ) {
				$urls[] = $url;
			}
		}

		$urls = array_unique( $urls );

		foreach ( $urls as $url ) {
			if ( function_exists( 'rocket_clean_files' ) ) {
				rocket_clean_files( $url );
			}
			do_action( 'litespeed_purge_url', $url );
		}
	}

	/**
	 * Concrete URL for a targeting rule, when possible.
	 *
	 * @param string $type  Rule type.
	 * @param string $value Rule value.
	 * @return string|null
	 */
	protected static function rule_to_url( $type, $value ) {
		switch ( $type ) {
			case 'path_equals':
			case 'path_starts_with':
				$path = $value;
				if ( '' !== $path && '/' !== substr( $path, 0, 1 ) ) {
					$path = '/' . $path;
				}
				return '' !== $path ? home_url( $path ) : home_url( '/' );

			case 'wc_cart':
				return function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );

			case 'wc_checkout':
				return function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );

			case 'wc_shop':
				return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
		}

		return null;
	}
}
