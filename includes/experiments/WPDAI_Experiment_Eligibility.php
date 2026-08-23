<?php
/**
 * Experiment page and audience eligibility.
 *
 * @package Alpha Insights
 * @since 5.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPDAI_Experiment_Eligibility
 */
class WPDAI_Experiment_Eligibility {

	/**
	 * Whether the current request matches page targeting.
	 *
	 * @param array $experiment Experiment row.
	 * @return bool
	 */
	public static function matches_page( $experiment ) {
		$pages = wpdai_experiment_effective_page_rules( isset( $experiment['targeting'] ) ? $experiment['targeting'] : array() );

		if ( empty( $pages ) ) {
			return true;
		}

		$path    = wpdai_get_current_request_path();
		$is_pro  = wpdai_experiments_is_pro();
		$allowed = $is_pro ? array_keys( wpdai_get_experiment_page_target_types() ) : wpdai_get_experiment_free_page_target_types();
		$use_conditionals = (bool) did_action( 'wp' );

		foreach ( $pages as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$type  = isset( $rule['type'] ) ? sanitize_key( $rule['type'] ) : '';
			$value = isset( $rule['value'] ) ? (string) $rule['value'] : '';
			if ( ! in_array( $type, $allowed, true ) ) {
				continue;
			}
			if ( $use_conditionals ? self::rule_matches( $type, $value, $path ) : self::rule_matches_path_only( $type, $value, $path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the visitor is a valid potential (page + audience + analytics exclusions).
	 *
	 * @param array  $experiment Experiment row.
	 * @param string $visitor_id Visitor ID.
	 * @return bool
	 */
	public static function is_eligible( $experiment, $visitor_id ) {
		if ( empty( $experiment ) || 'running' !== ( $experiment['status'] ?? '' ) ) {
			return false;
		}

		if ( ! function_exists( 'wpdai_is_analytics_enabled' ) || ! wpdai_is_analytics_enabled() ) {
			return false;
		}

		if ( ! self::matches_page( $experiment ) ) {
			return false;
		}

		if ( self::is_excluded_role() ) {
			return false;
		}

		if ( class_exists( 'WPDAI_User_Agent_Classification' ) ) {
			$ua = new WPDAI_User_Agent_Classification();
			if ( 'BOT' === $ua->getDeviceCategory() ) {
				return false;
			}
		}

		if ( wpdai_experiments_is_pro() && ! self::matches_audience( $experiment, $visitor_id ) ) {
			return false;
		}

		if ( self::already_in_another_experiment( $experiment, $visitor_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Analytics exclude_roles setting.
	 *
	 * @return bool
	 */
	protected static function is_excluded_role() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$settings = function_exists( 'wpdai_get_analytics_settings' ) ? wpdai_get_analytics_settings() : array();
		if ( empty( $settings['exclude_roles'] ) || ! is_array( $settings['exclude_roles'] ) ) {
			return false;
		}

		$user = wp_get_current_user();
		foreach ( $settings['exclude_roles'] as $excluded_role ) {
			$excluded_role = str_replace( 'exclude_', '', $excluded_role );
			if ( in_array( $excluded_role, (array) $user->roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Audience targeting (Pro).
	 *
	 * @param array  $experiment Experiment row.
	 * @param string $visitor_id Visitor ID.
	 * @return bool
	 */
	protected static function matches_audience( $experiment, $visitor_id ) {
		$audience = isset( $experiment['targeting']['audience'] ) && is_array( $experiment['targeting']['audience'] ) ? $experiment['targeting']['audience'] : array();

		$logged_in = isset( $audience['logged_in'] ) ? $audience['logged_in'] : 'any';
		if ( 'yes' === $logged_in && ! is_user_logged_in() ) {
			return false;
		}
		if ( 'no' === $logged_in && is_user_logged_in() ) {
			return false;
		}

		$roles = isset( $audience['roles'] ) && is_array( $audience['roles'] ) ? $audience['roles'] : array();
		if ( ! empty( $roles ) ) {
			if ( ! is_user_logged_in() ) {
				return false;
			}
			$user       = wp_get_current_user();
			$has_role   = false;
			foreach ( $roles as $role ) {
				if ( in_array( $role, (array) $user->roles, true ) ) {
					$has_role = true;
					break;
				}
			}
			if ( ! $has_role ) {
				return false;
			}
		}

		$devices = isset( $audience['devices'] ) && is_array( $audience['devices'] ) ? $audience['devices'] : array();
		if ( ! empty( $devices ) && class_exists( 'WPDAI_User_Agent_Classification' ) ) {
			$ua       = new WPDAI_User_Agent_Classification();
			$category = $ua->getDeviceCategory();
			if ( ! in_array( $category, $devices, true ) ) {
				return false;
			}
		}

		$sources = isset( $audience['traffic_sources'] ) && is_array( $audience['traffic_sources'] ) ? $audience['traffic_sources'] : array();
		if ( ! empty( $sources ) ) {
			$detected = self::detect_traffic_source();
			if ( ! in_array( $detected, $sources, true ) ) {
				return false;
			}
		}

		$utm = isset( $audience['utm'] ) && is_array( $audience['utm'] ) ? $audience['utm'] : array();
		if ( ! empty( $utm ) ) {
			foreach ( $utm as $key => $expected ) {
				if ( '' === (string) $expected ) {
					continue;
				}
				$param = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( $param !== (string) $expected ) {
					return false;
				}
			}
		}

		$query_params = isset( $audience['query_params'] ) && is_array( $audience['query_params'] ) ? $audience['query_params'] : array();
		if ( ! empty( $query_params ) ) {
			foreach ( $query_params as $key => $expected ) {
				if ( '' === (string) $expected ) {
					continue;
				}
				$param = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( $param !== (string) $expected ) {
					return false;
				}
			}
		}

		$visitor_type = isset( $audience['visitor_type'] ) ? $audience['visitor_type'] : 'any';
		if ( 'new' === $visitor_type || 'returning' === $visitor_type ) {
			$is_returning = self::is_returning_visitor( $visitor_id );
			if ( 'new' === $visitor_type && $is_returning ) {
				return false;
			}
			if ( 'returning' === $visitor_type && ! $is_returning ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Detect traffic source from current request / analytics cookies.
	 *
	 * @return string
	 */
	protected static function detect_traffic_source() {
		$referral = '';
		if ( ! empty( $_COOKIE['wpd_ai_referral_source'] ) ) {
			$referral = esc_url_raw( wp_unslash( $_COOKIE['wpd_ai_referral_source'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$referral = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}

		$query = array();
		if ( ! empty( $_COOKIE['wpd_ai_landing_page'] ) ) {
			$landing = esc_url_raw( wp_unslash( $_COOKIE['wpd_ai_landing_page'] ) );
			$parsed  = wp_parse_url( $landing );
			if ( ! empty( $parsed['query'] ) ) {
				parse_str( $parsed['query'], $query );
			}
		} else {
			$query = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( class_exists( 'WPDAI_Traffic_Type_Detection' ) ) {
			$detector = new WPDAI_Traffic_Type_Detection( $referral, $query );
			return (string) $detector->determine_traffic_source();
		}

		return 'Unknown';
	}

	/**
	 * Returning = already has an assignment for any experiment, or visitor cookie pre-existed this request.
	 *
	 * @param string $visitor_id Visitor ID.
	 * @return bool
	 */
	protected static function is_returning_visitor( $visitor_id ) {
		if ( '' === $visitor_id || ! WPDAI_Experiment_Store::tables_exist() ) {
			return false;
		}

		global $wpdb;
		$table = WPDAI_Experiment_Store::assignments_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefix-based and trusted.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE visitor_id = %s LIMIT 1",
				sanitize_text_field( $visitor_id )
			)
		);

		return ! empty( $found );
	}

	/**
	 * Visitors may only be in one running experiment at a time.
	 *
	 * @param array  $experiment Experiment row.
	 * @param string $visitor_id Visitor ID.
	 * @return bool True if this visitor should skip this experiment.
	 */
	protected static function already_in_another_experiment( $experiment, $visitor_id ) {
		$current_id = isset( $experiment['id'] ) ? (int) $experiment['id'] : 0;
		$map        = class_exists( 'WPDAI_Experiment_Runtime' ) ? WPDAI_Experiment_Runtime::get_assignment_map() : array();

		foreach ( WPDAI_Experiment_Store::get_running() as $other ) {
			$other_id = (int) $other['id'];
			if ( $other_id === $current_id ) {
				continue;
			}
			if ( isset( $map[ (string) $other_id ] ) ) {
				return true;
			}
			if ( '' !== $visitor_id && WPDAI_Experiment_Store::get_assignment( $other_id, $visitor_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Evaluate a single page rule.
	 *
	 * @param string $type  Rule type.
	 * @param string $value Rule value.
	 * @param string $path  Current path.
	 * @return bool
	 */
	protected static function rule_matches( $type, $value, $path ) {
		$value_path = '/' !== $value ? untrailingslashit( $value ) : $value;

		switch ( $type ) {
			case 'path_equals':
				$compare = '/' !== $value_path ? $value_path : '/';
				if ( '' !== $compare && '/' !== substr( $compare, 0, 1 ) ) {
					$compare = '/' . $compare;
				}
				return $path === $compare;

			case 'path_contains':
				return '' !== $value && false !== strpos( $path, $value );

			case 'path_starts_with':
				$prefix = $value_path;
				if ( '' !== $prefix && '/' !== substr( $prefix, 0, 1 ) ) {
					$prefix = '/' . $prefix;
				}
				return '' !== $prefix && 0 === strpos( $path, $prefix );

			case 'wc_cart':
				return function_exists( 'is_cart' ) && is_cart();

			case 'wc_checkout':
				return function_exists( 'is_checkout' ) && is_checkout();

			case 'wc_product':
				return function_exists( 'is_product' ) && is_product();

			case 'wc_shop':
				return function_exists( 'is_shop' ) && is_shop();

			case 'post_type':
				return '' !== $value && is_singular( $value );

			case 'regex':
				if ( '' === $value || strlen( $value ) > 200 ) {
					return false;
				}
				$set = @preg_match( '#' . str_replace( '#', '\#', $value ) . '#', $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return 1 === $set;
		}

		return false;
	}

	/**
	 * Path-only rule match for early cache hooks (query conditionals are not ready).
	 *
	 * @param string $type  Rule type.
	 * @param string $value Rule value.
	 * @param string $path  Current path.
	 * @return bool
	 */
	public static function rule_matches_path_only( $type, $value, $path ) {
		switch ( $type ) {
			case 'path_equals':
			case 'path_contains':
			case 'path_starts_with':
			case 'regex':
				return self::rule_matches( $type, $value, $path );

			case 'wc_cart':
			case 'wc_checkout':
			case 'wc_shop':
				$known = self::woocommerce_path_for_type( $type );
				return '' !== $known && $path === $known;

			case 'wc_product':
			case 'post_type':
				// Unknown concrete URL until query parse — treat as a match so cache is skipped.
				return true;
		}

		return false;
	}

	/**
	 * Known WooCommerce path for a targeting type.
	 *
	 * @param string $type Rule type.
	 * @return string
	 */
	protected static function woocommerce_path_for_type( $type ) {
		$url = '';
		if ( 'wc_cart' === $type && function_exists( 'wc_get_cart_url' ) ) {
			$url = wc_get_cart_url();
		} elseif ( 'wc_checkout' === $type && function_exists( 'wc_get_checkout_url' ) ) {
			$url = wc_get_checkout_url();
		} elseif ( 'wc_shop' === $type && function_exists( 'wc_get_page_permalink' ) ) {
			$url = wc_get_page_permalink( 'shop' );
		}

		if ( '' === $url || ! is_string( $url ) ) {
			return '';
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( '' === $path ) {
			return '';
		}
		return '/' !== $path ? untrailingslashit( $path ) : $path;
	}
}
