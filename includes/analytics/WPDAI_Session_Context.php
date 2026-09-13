<?php
/**
 * Read-only session and attribution resolver for cache-safe analytics.
 * Never writes PHP cookies or WC/PHP session storage.
 *
 * @package Alpha Insights
 */
defined( 'ABSPATH' ) || exit;

/**
 * Class WPDAI_Session_Context
 */
class WPDAI_Session_Context {

	public string   $session_id = '';
	public string   $ip_address = '';
	public string   $landing_page = '';
	public string   $referral_url = '';
	public int      $user_id = 0;
	public string   $date_created_gmt = '';
	public string   $date_updated_gmt = '';
	public string   $device_category = '';
	public string   $operating_system = '';
	public string   $browser = '';
	public string   $device = '';
	public bool     $engaged_session = false;
	public array    $additional_data = array();
	public int      $is_bot = 0;
	public string   $page_href = '';
	public string   $raw_user_agent_string = '';
	public int      $object_id = 0;
	public string   $object_type = '';
	private bool    $is_new_session = false;

	/** @var array<string, mixed> */
	private array $payload = array();

	/** @var array<string, mixed>|null */
	private ?array $existing_session_row = null;

	/**
	 * @param array<string, mixed> $payload Optional event payload for context resolution.
	 */
	public function __construct( array $payload = array() ) {
		$this->payload = $payload;
		$this->setup_session_data();
	}

	/**
	 * Build session properties from request context (read-only).
	 *
	 * @return array<string, mixed>
	 */
	public function setup_session_data() {
		$user_agent = new WPDAI_User_Agent_Classification();
		$this->is_bot = $user_agent->isBot();

		if ( $this->is_bot ) {
			return get_object_vars( $this );
		}

		$this->session_id = $this->resolve_session_id();
		$this->hydrate_from_existing_session_row();

		if ( empty( $this->date_created_gmt ) ) {
			$this->date_created_gmt = current_time( 'mysql', true );
		}
		$this->date_updated_gmt = current_time( 'mysql', true );

		if ( ! empty( $this->payload['page_href'] ) ) {
			$this->page_href = esc_url_raw( $this->payload['page_href'] );
		} else {
			$this->page_href = esc_url_raw( home_url( add_query_arg( null, null ) ) );
		}

		if ( function_exists( 'wpdai_is_ajax_or_rest_url' ) && wpdai_is_ajax_or_rest_url( $this->page_href ) ) {
			$origin = function_exists( 'wpdai_get_event_origin_url' ) ? wpdai_get_event_origin_url() : '';
			if ( '' !== $origin ) {
				$this->page_href = $origin;
			}
		}

		$this->user_id               = get_current_user_id();
		$this->ip_address            = $this->resolve_ip_address();
		$this->referral_url          = $this->resolve_referral_url();
		$this->landing_page          = $this->resolve_landing_page();
		$this->device_category       = $user_agent->getDeviceCategory();
		$this->operating_system      = $user_agent->getOS();
		$this->browser               = $user_agent->getBrowser();
		$this->device                = $user_agent->getDeviceCategory();
		$this->raw_user_agent_string = $user_agent->getUserAgent() ? $user_agent->getUserAgent() : '';
		$this->additional_data       = array(
			'raw_user_agent_data' => $this->raw_user_agent_string,
		);
		$this->engaged_session       = $this->resolve_engaged_session();

		$context = get_object_vars( $this );
		$context = wpdai_apply_analytics_filter( 'wpd_ai_resolve_session_context', 'wpd_ai_v2_resolve_session_context', $context, $this->payload );

		if ( is_array( $context ) ) {
			foreach ( $context as $key => $value ) {
				if ( property_exists( $this, $key ) ) {
					$this->$key = $value;
				}
			}
		}

		return get_object_vars( $this );
	}

	/**
	 * @return bool
	 */
	public function resolve_engaged_session() {
		if ( isset( $_COOKIE['wpd_ai_engaged_session'] ) && ! empty( $_COOKIE['wpd_ai_engaged_session'] ) ) {
			$cookie = sanitize_text_field( wp_unslash( $_COOKIE['wpd_ai_engaged_session'] ) );
			return ( '1' === $cookie );
		}

		if ( is_array( $this->existing_session_row ) && ! empty( $this->existing_session_row['engaged_session'] ) ) {
			return ( 1 === (int) $this->existing_session_row['engaged_session'] );
		}

		return false;
	}

	/**
	 * Load persisted session attribution when cookies are unavailable (checkout_only mode).
	 *
	 * @return void
	 */
	private function hydrate_from_existing_session_row() {
		if ( empty( $this->session_id ) ) {
			return;
		}

		global $wpdb;

		$db_interactor = WPDAI_Database_Interactor::instance();
		$table_name    = $db_interactor->session_data_table;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated via Database Interactor whitelist.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT landing_page, referral_url, date_created_gmt, date_updated_gmt, engaged_session, user_id FROM {$table_name} WHERE session_id = %s LIMIT 1",
				$this->session_id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) || empty( $row ) ) {
			$this->existing_session_row = null;
			return;
		}

		$this->existing_session_row = $row;

		if ( ! empty( $row['date_created_gmt'] ) ) {
			$this->date_created_gmt = (string) $row['date_created_gmt'];
		}
	}

	/**
	 * @return string
	 */
	public function resolve_session_id() {
		$session_id = '';

		if ( ! empty( $this->payload['session_id'] ) ) {
			$session_id = sanitize_text_field( (string) $this->payload['session_id'] );
		} elseif ( isset( $_COOKIE['wpd_ai_session_id'] ) && ! empty( $_COOKIE['wpd_ai_session_id'] ) ) {
			$session_id = sanitize_text_field( wp_unslash( $_COOKIE['wpd_ai_session_id'] ) );
		} elseif ( $this->should_reuse_recent_visitor_session() ) {
			$session_id = $this->find_recent_session_id_for_visitor();
		}

		if ( empty( $session_id ) ) {
			$session_id           = $this->generate_unique_session_id();
			$this->is_new_session = true;
		}

		$this->session_id = $session_id;
		return $session_id;
	}

	/**
	 * Checkout, cart, and cart-ajax requests should reuse an in-progress session
	 * instead of minting a new session_id when cookies have not been written yet.
	 *
	 * @return bool
	 */
	private function should_reuse_recent_visitor_session() {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}

		$wc_ajax = isset( $_GET['wc-ajax'] ) ? sanitize_key( wp_unslash( $_GET['wc-ajax'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$reuse_ajax = array(
			'update_order_review',
			'checkout',
			'apply_coupon',
			'remove_coupon',
			'update_shipping_method',
			'get_refreshed_fragments',
			'add_to_cart',
			'reycore_ajax_add_to_cart',
			'remove_from_cart',
		);

		if ( '' !== $wc_ajax && in_array( $wc_ajax, $reuse_ajax, true ) ) {
			return true;
		}

		if ( ! empty( $this->payload['page_href'] ) && function_exists( 'wpdai_is_checkout_like_url' ) && wpdai_is_checkout_like_url( $this->payload['page_href'] ) ) {
			return true;
		}

		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		if ( '' !== $referer && function_exists( 'wpdai_is_checkout_like_url' ) && wpdai_is_checkout_like_url( $referer ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Find a still-active session for this IP + user agent.
	 *
	 * @return string
	 */
	private function find_recent_session_id_for_visitor() {
		$ip_address = WPDAI_Client_IP::resolve();
		if ( '' === $ip_address ) {
			return '';
		}

		$timeout = function_exists( 'wpdai_get_analytics_session_timeout_seconds' )
			? wpdai_get_analytics_session_timeout_seconds()
			: ( 30 * MINUTE_IN_SECONDS );
		$since   = gmdate( 'Y-m-d H:i:s', time() - max( 60, (int) $timeout ) );
		$ua      = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		global $wpdb;

		$db_interactor = WPDAI_Database_Interactor::instance();
		$table_name    = $db_interactor->session_data_table;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated via Database Interactor whitelist.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT session_id, additional_data
				FROM {$table_name}
				WHERE ip_address = %s
				AND date_updated_gmt >= %s
				ORDER BY date_updated_gmt DESC
				LIMIT 10",
				$ip_address,
				$since
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return '';
		}

		$ua_matches = array();
		foreach ( $rows as $row ) {
			$session_id = isset( $row['session_id'] ) ? sanitize_text_field( (string) $row['session_id'] ) : '';
			if ( '' === $session_id ) {
				continue;
			}

			$stored_ua = '';
			if ( class_exists( 'WPDAI_Traffic_Type_Detection' ) ) {
				$stored_ua = WPDAI_Traffic_Type_Detection::user_agent_from_additional_data( isset( $row['additional_data'] ) ? $row['additional_data'] : '' );
			}

			if ( '' !== $ua && '' !== $stored_ua && $stored_ua === $ua ) {
				$ua_matches[] = $session_id;
			}
		}

		if ( ! empty( $ua_matches ) ) {
			return $ua_matches[0];
		}

		if ( 1 === count( $rows ) ) {
			return sanitize_text_field( (string) $rows[0]['session_id'] );
		}

		return '';
	}

	/**
	 * @return string
	 */
	public function generate_unique_session_id() {
		$user_agent   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$random_token = md5( $user_agent . time() );
		return sanitize_text_field( 'wpd' . $random_token . time() );
	}

	/**
	 * @return string
	 */
	public function resolve_ip_address() {
		$this->ip_address = WPDAI_Client_IP::resolve();
		return $this->ip_address;
	}

	/**
	 * @return string
	 */
	public function resolve_landing_page() {
		$existing = '';
		if ( is_array( $this->existing_session_row ) && ! empty( $this->existing_session_row['landing_page'] ) ) {
			$existing = $this->sanitize_landing_page_url( (string) $this->existing_session_row['landing_page'] );
		}

		$candidates = array();
		if ( isset( $this->payload['landing_page'] ) && is_string( $this->payload['landing_page'] ) ) {
			$candidates[] = $this->payload['landing_page'];
		}
		if ( isset( $_COOKIE['wpd_ai_landing_page'] ) && ! empty( $_COOKIE['wpd_ai_landing_page'] ) ) {
			$candidates[] = wp_unslash( $_COOKIE['wpd_ai_landing_page'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via wpdai_sanitize_attribution_url().
		}
		if ( ! empty( $this->payload['page_href'] ) && is_string( $this->payload['page_href'] ) ) {
			$candidates[] = $this->payload['page_href'];
		}

		$incoming = function_exists( 'wpdai_select_attribution_url' )
			? wpdai_select_attribution_url( $candidates )
			: $this->sanitize_landing_page_url( isset( $candidates[0] ) ? (string) $candidates[0] : '' );

		if ( function_exists( 'wpdai_choose_first_touch_landing_page' ) ) {
			$landing_page = wpdai_choose_first_touch_landing_page( $existing, $incoming );
		} else {
			$landing_page = '' !== $existing ? $existing : $incoming;
		}

		$this->landing_page = $landing_page;
		return $landing_page;
	}

	/**
	 * @param string $landing_page Raw landing page value.
	 * @return string
	 */
	private function sanitize_landing_page_url( $landing_page ) {
		if ( function_exists( 'wpdai_sanitize_attribution_url' ) ) {
			return wpdai_sanitize_attribution_url( $landing_page );
		}

		if ( empty( $landing_page ) ) {
			return '';
		}

		$decoded = rawurldecode( $landing_page );
		if ( $decoded !== $landing_page ) {
			$landing_page = $decoded;
			$double       = rawurldecode( $landing_page );
			if ( $double !== $landing_page && filter_var( $double, FILTER_VALIDATE_URL ) ) {
				$landing_page = $double;
			}
		}

		$landing_page = filter_var( $landing_page, FILTER_SANITIZE_URL );
		if ( ! filter_var( $landing_page, FILTER_VALIDATE_URL ) ) {
			return '';
		}

		$lower = strtolower( $landing_page );
		if ( str_contains( $lower, 'wp-admin' ) || str_contains( $lower, 'wp-login' ) || str_contains( $lower, 'admin-ajax' ) ) {
			return '';
		}

		return $landing_page;
	}

	/**
	 * @return string
	 */
	public function resolve_referral_url() {
		$existing = '';
		if ( is_array( $this->existing_session_row ) && ! empty( $this->existing_session_row['referral_url'] ) ) {
			$existing = $this->normalize_referral_value( (string) $this->existing_session_row['referral_url'] );
		}

		$referral_url = '';

		if ( array_key_exists( 'referral_source', $this->payload ) ) {
			$referral_url = $this->normalize_referral_value( (string) $this->payload['referral_source'] );
		} elseif ( isset( $_COOKIE['wpd_ai_referral_source'] ) ) {
			$cookie_val = wp_unslash( $_COOKIE['wpd_ai_referral_source'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Normalized via normalize_referral_value().
			if ( '' !== $cookie_val ) {
				$referral_url = $this->normalize_referral_value( $cookie_val );
			}
		} elseif ( ! $this->should_skip_wc_session_lookup() && ( $wc_ref = $this->get_referral_url_from_wc_order_attribution() ) ) {
			$referral_url = $wc_ref;
		} elseif ( function_exists( 'wpdai_get_referral_url_raw' ) ) {
			$http_referrer = wpdai_get_referral_url_raw();
			if ( ! empty( $http_referrer ) ) {
				$referral_url = esc_url_raw( $http_referrer );
			}
		} elseif ( $header_ref = $this->get_referral_url_from_http_headers() ) {
			$referral_url = $header_ref;
		}

		if ( function_exists( 'wpdai_choose_first_touch_referral_url' ) ) {
			$referral_url = wpdai_choose_first_touch_referral_url( $existing, $referral_url );
		} elseif ( '' !== $existing ) {
			$referral_url = $existing;
		}

		$this->referral_url = $referral_url;
		return $referral_url;
	}

	/**
	 * @param string $referral_url Raw referral value.
	 * @return string
	 */
	private function normalize_referral_value( $referral_url ) {
		if ( '' === $referral_url ) {
			return '';
		}

		$decoded = rawurldecode( $referral_url );
		if ( $decoded !== $referral_url ) {
			$referral_url = $decoded;
			$double       = rawurldecode( $referral_url );
			if ( $double !== $referral_url && filter_var( $double, FILTER_VALIDATE_URL ) ) {
				$referral_url = $double;
			}
		}

		$validated = $this->validate_external_referral_url( $referral_url );
		return $validated ? $validated : '';
	}

	/**
	 * @return string|false
	 */
	private function get_referral_url_from_wc_order_attribution() {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
			return false;
		}

		$wc_session = WC()->session;
		if ( ! $wc_session || ! is_a( $wc_session, 'WC_Session' ) ) {
			return false;
		}

		$wc_referrer = $wc_session->get( 'wc_order_attribution_referrer' );
		if ( empty( $wc_referrer ) ) {
			return false;
		}

		return $this->validate_external_referral_url( $wc_referrer );
	}

	/**
	 * @return string|false
	 */
	private function get_referral_url_from_http_headers() {
		$referrer_headers = array(
			'HTTP_X_FORWARDED_REFERER',
			'HTTP_X_REFERER',
			'HTTP_REFERER_ORIGINAL',
			'HTTP_X_ORIGINAL_REFERER',
		);

		foreach ( $referrer_headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$potential = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				$validated = $this->validate_external_referral_url( $potential );
				if ( $validated ) {
					return $validated;
				}
			}
		}

		return false;
	}

	/**
	 * Intentionally unused. Own-site URLs with tracking params stripped
	 * were being stored as the referrer and classified as Direct.
	 *
	 * @return false
	 */
	private function get_referral_url_from_url_parameters() {
		return false;
	}

	/**
	 * @param string $referral_url Referral URL.
	 * @return string|false
	 */
	private function validate_external_referral_url( $referral_url ) {
		if ( empty( $referral_url ) ) {
			return false;
		}

		$referral_url = filter_var( $referral_url, FILTER_SANITIZE_URL );
		if ( ! filter_var( $referral_url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		if ( ! $this->is_external_referral_url( $referral_url ) ) {
			return false;
		}

		return esc_url_raw( $referral_url );
	}

	/**
	 * @param string $referral_url Referral URL.
	 * @return bool
	 */
	private function is_external_referral_url( $referral_url ) {
		$site_host = wp_parse_url( site_url(), PHP_URL_HOST );
		if ( empty( $site_host ) ) {
			return false;
		}

		$referring_domain = wp_parse_url( $referral_url, PHP_URL_HOST );
		if ( empty( $referring_domain ) ) {
			return false;
		}

		$referring_domain     = strtolower( preg_replace( '/^www\./', '', $referring_domain ) );
		$site_host_normalized = strtolower( preg_replace( '/^www\./', '', $site_host ) );

		if ( $referring_domain === $site_host_normalized ) {
			return false;
		}

		if ( str_contains( $referring_domain, '.' . $site_host_normalized ) ) {
			return false;
		}

		if ( str_contains( $site_host_normalized, '.' . $referring_domain ) ) {
			return false;
		}

		return true;
	}

	/**
	 * WooCommerce session bootstrap is expensive on REST inserts and is unused
	 * when the browser already sent referral cookies or payload fields.
	 *
	 * @return bool
	 */
	private function should_skip_wc_session_lookup() {
		return ( defined( 'REST_REQUEST' ) && REST_REQUEST );
	}

	/**
	 * Whether the existing session row still needs a write.
	 *
	 * @param array<string, mixed> $data Prepared session row.
	 * @return bool
	 */
	private function session_row_needs_write( $data ) {
		if ( ! is_array( $this->existing_session_row ) || empty( $this->existing_session_row ) ) {
			return true;
		}

		$existing_landing  = isset( $this->existing_session_row['landing_page'] ) ? (string) $this->existing_session_row['landing_page'] : '';
		$existing_referral = isset( $this->existing_session_row['referral_url'] ) ? (string) $this->existing_session_row['referral_url'] : '';
		$existing_engaged  = isset( $this->existing_session_row['engaged_session'] ) ? (int) $this->existing_session_row['engaged_session'] : 0;
		$existing_user_id  = isset( $this->existing_session_row['user_id'] ) ? (int) $this->existing_session_row['user_id'] : 0;

		if ( (string) $data['landing_page'] !== $existing_landing ) {
			return true;
		}
		if ( (string) $data['referral_url'] !== $existing_referral ) {
			return true;
		}
		if ( (int) $data['engaged_session'] !== $existing_engaged ) {
			return true;
		}
		if ( (int) $data['user_id'] > 0 && $existing_user_id !== (int) $data['user_id'] ) {
			return true;
		}

		$last_updated = 0;
		if ( ! empty( $this->existing_session_row['date_updated_gmt'] ) ) {
			$last_updated = (int) strtotime( $this->existing_session_row['date_updated_gmt'] . ' UTC' );
		}

		$heartbeat = (int) apply_filters( 'wpd_ai_session_heartbeat_seconds', 15 );
		$heartbeat = max( 1, $heartbeat );

		return ( $last_updated <= 0 || ( time() - $last_updated ) >= $heartbeat );
	}

	/**
	 * Persist session row (no cookie side effects).
	 *
	 * @return bool
	 */
	public function store_session_in_db() {
		if ( empty( $this->session_id ) ) {
			return false;
		}

		global $wpdb;

		$db_interactor = WPDAI_Database_Interactor::instance();
		$table_name    = $db_interactor->session_data_table;

		$data = array(
			'session_id'       => sanitize_text_field( $this->session_id ),
			'ip_address'       => sanitize_text_field( $this->ip_address ),
			'landing_page'     => sanitize_url( $this->landing_page ),
			'referral_url'     => sanitize_url( $this->referral_url ),
			'user_id'          => (int) $this->user_id,
			'date_created_gmt' => $this->date_created_gmt,
			'date_updated_gmt' => $this->date_updated_gmt,
			'device_category'  => sanitize_text_field( $this->device_category ),
			'operating_system' => sanitize_text_field( $this->operating_system ),
			'browser'          => sanitize_text_field( $this->browser ),
			'device'           => sanitize_text_field( $this->device ),
			'additional_data'  => wp_json_encode( $this->additional_data ),
			'engaged_session'  => (int) $this->engaged_session,
		);

		$session_exists = is_array( $this->existing_session_row ) && ! empty( $this->existing_session_row );

		if ( $session_exists ) {
			$existing_landing  = isset( $this->existing_session_row['landing_page'] ) ? (string) $this->existing_session_row['landing_page'] : '';
			$existing_referral = isset( $this->existing_session_row['referral_url'] ) ? (string) $this->existing_session_row['referral_url'] : '';

			if ( function_exists( 'wpdai_choose_first_touch_landing_page' ) ) {
				$data['landing_page'] = wpdai_choose_first_touch_landing_page( $existing_landing, $data['landing_page'] );
			} elseif ( '' !== $existing_landing ) {
				$data['landing_page'] = sanitize_url( $existing_landing );
			}

			if ( function_exists( 'wpdai_choose_first_touch_referral_url' ) ) {
				$data['referral_url'] = wpdai_choose_first_touch_referral_url( $existing_referral, $data['referral_url'] );
			} elseif ( '' !== $existing_referral ) {
				$data['referral_url'] = sanitize_url( $existing_referral );
			}

			$this->landing_page = $data['landing_page'];
			$this->referral_url = $data['referral_url'];

			if ( ! $this->session_row_needs_write( $data ) ) {
				return true;
			}

			$this->existing_session_row['landing_page']     = $data['landing_page'];
			$this->existing_session_row['referral_url']     = $data['referral_url'];
			$this->existing_session_row['engaged_session']  = $data['engaged_session'];
			$this->existing_session_row['user_id']          = $data['user_id'];
			$this->existing_session_row['date_updated_gmt'] = $data['date_updated_gmt'];

			$update_user = '';
			if ( $data['user_id'] > 0 ) {
				$update_user = 'user_id = ' . (int) $data['user_id'] . ',';
			}

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $table_name
					SET date_updated_gmt = %s,
					landing_page = %s,
					engaged_session = %d,
					$update_user
					referral_url = %s
					WHERE session_id = %s",
					$data['date_updated_gmt'],
					$data['landing_page'],
					$data['engaged_session'],
					$data['referral_url'],
					$data['session_id']
				)
			);
		} else {
			$db_interactor->add_row( $table_name, $data );
			$this->existing_session_row = array(
				'landing_page'     => $data['landing_page'],
				'referral_url'     => $data['referral_url'],
				'date_created_gmt' => $data['date_created_gmt'],
				'date_updated_gmt' => $data['date_updated_gmt'],
				'engaged_session'  => $data['engaged_session'],
				'user_id'          => $data['user_id'],
			);
		}

		return true;
	}
}
