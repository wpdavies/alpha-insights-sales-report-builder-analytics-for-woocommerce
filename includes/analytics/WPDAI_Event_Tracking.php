<?php
/**
 * Cache-safe WooCommerce event tracking.
 *
 * @package Alpha Insights
 */
defined( 'ABSPATH' ) || exit;

/**
 * Class WPDAI_Event_Tracking
 */
class WPDAI_Event_Tracking extends WPDAI_WooCommerce_Event_Tracking {

	/** @var self|null */
	private static $tracking_instance = null;

	/** @var array<string, mixed> */
	protected $session_payload = array();

	/** @var bool Whether the session row was written earlier in this request. */
	protected $session_persisted_this_request = false;

	/**
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$tracking_instance ) {
			self::$tracking_instance = new self();
		}
		return self::$tracking_instance;
	}

	/**
	 * Register hooks for cache-safe tracking.
	 */
	public function __construct() {
		if ( ! wpdai_is_analytics_enabled() ) {
			$this->event_tracking_enabled = 0;
		}

		$this->settings                    = wpdai_get_analytics_settings();
		$this->only_track_engaged_sessions = isset( $this->settings['only_track_engaged_sessions'] ) ? (int) $this->settings['only_track_engaged_sessions'] : 0;

		if ( 1 !== (int) $this->event_tracking_enabled ) {
			return;
		}

		$this->api_url = '/wp-json/' . $this->api_namespace . '/' . $this->api_endpoint;

		add_action( 'template_redirect', array( $this, 'setup_object_type_id' ), 1 );
		add_action( 'woocommerce_add_to_cart', array( $this, 'db_track_product_add_to_cart' ), 10, 6 );
		add_action( 'woocommerce_remove_cart_item', array( $this, 'db_track_product_remove_from_cart' ), 10, 2 );
		add_action( 'woocommerce_thankyou', array( $this, 'db_track_products_purchased_thankyou_page' ), 10, 1 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'db_track_products_purchased_on_order_status_change' ), 20, 4 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'db_track_failed_orders' ), 30, 4 );
		add_action( 'wp_login', array( $this, 'db_track_logins' ), 100 );
		add_action( 'woocommerce_customer_login', array( $this, 'db_track_logins' ), 100 );
		add_action( 'wp_logout', array( $this, 'db_track_logouts' ), 100, 1 );
		add_action( 'woocommerce_created_customer', array( $this, 'db_track_account_created' ), 100, 3 );
		add_action( 'woocommerce_before_shop_loop_item', array( $this, 'add_product_id_to_product_loop_item' ), 10 );
		add_filter( 'woocommerce_post_class', array( $this, 'add_class_to_loop_item' ), 10, 2 );
		add_action( 'rest_api_init', array( $this, 'register_wpd_ai_events_api' ) );
	}

	/**
	 * Frontend scripts are registered by WPDAI_Analytics_Scripts.
	 *
	 * @return array<string, mixed>
	 */
	public function register_event_tracking_script() {
		return array();
	}

	/**
	 * @return WPDAI_Session_Context
	 */
	public function get_set_session_instance() {
		if ( ! empty( $this->session_instance ) && is_object( $this->session_instance ) ) {
			return $this->session_instance;
		}
		$this->session_instance = new WPDAI_Session_Context( $this->session_payload );
		return $this->session_instance;
	}

	/**
	 * Whether this event can reuse the session already resolved in this request.
	 *
	 * @param array<string, mixed> $data Event payload.
	 * @return bool
	 */
	protected function can_reuse_session_for_event( $data ) {
		if ( empty( $this->session_instance ) || ! is_object( $this->session_instance ) ) {
			return false;
		}

		$incoming_session_id = '';
		if ( ! empty( $data['session_id'] ) && is_string( $data['session_id'] ) ) {
			$incoming_session_id = sanitize_text_field( $data['session_id'] );
		}

		$existing_session_id = isset( $this->session_instance->session_id ) ? (string) $this->session_instance->session_id : '';

		return ( '' === $incoming_session_id || $incoming_session_id === $existing_session_id );
	}

	/**
	 * Reuse the request session when the incoming event belongs to it.
	 *
	 * @param array<string, mixed> $data Event payload.
	 * @return WPDAI_Session_Context
	 */
	protected function resolve_session_instance_for_event( $data ) {
		$this->session_payload = is_array( $data ) ? $data : array();

		$incoming_session_id = '';
		if ( ! empty( $data['session_id'] ) && is_string( $data['session_id'] ) ) {
			$incoming_session_id = sanitize_text_field( $data['session_id'] );
		}

		if ( ! empty( $this->session_instance ) && is_object( $this->session_instance ) ) {
			$existing_session_id = isset( $this->session_instance->session_id ) ? (string) $this->session_instance->session_id : '';
			$can_reuse           = ( '' === $incoming_session_id || $incoming_session_id === $existing_session_id );

			if ( $can_reuse ) {
				if ( ! empty( $data['page_href'] ) && is_string( $data['page_href'] ) ) {
					$this->session_instance->page_href = esc_url_raw( $data['page_href'] );
				}
				return $this->session_instance;
			}
		}

		$this->session_instance                 = null;
		$this->session_persisted_this_request = false;

		return $this->get_set_session_instance();
	}

	/**
	 * Resolve client IP without constructing full session context.
	 *
	 * @return string
	 */
	protected function get_client_ip_for_rate_limit() {
		return WPDAI_Client_IP::resolve();
	}

	/**
	 * @param string $ip_address Client IP.
	 * @return bool
	 */
	protected function is_ip_banned( $ip_address ) {
		if ( empty( $ip_address ) ) {
			return false;
		}
		$transient_key = '_wpd_ip_banned_event_tracking' . $ip_address;
		return ( 1 === (int) get_transient( $transient_key ) );
	}

	/**
	 * @param string $ip_address Client IP.
	 * @return bool
	 */
	protected function is_rate_limit_exceeded( $ip_address ) {
		if ( empty( $ip_address ) ) {
			return false;
		}

		$maximum_requests_per_minute = 60;
		$transient_key               = '_wpd_ip_requests_per_minute_' . $ip_address;
		$requests_per_ip             = (int) get_transient( $transient_key );
		$requests_per_ip++;
		set_transient( $transient_key, $requests_per_ip, 60 );

		if ( $requests_per_ip > $maximum_requests_per_minute ) {
			set_transient( '_wpd_ip_banned_event_tracking' . $ip_address, 1, DAY_IN_SECONDS );
			return true;
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $data Event data.
	 * @return bool
	 */
	protected function should_block_request_by_data( $data ) {
		$block_request = false;

		if ( ! is_array( $data ) || empty( $data ) ) {
			$block_request = true;
		}
		if ( ! isset( $data['page_href'] ) || empty( $data['page_href'] ) ) {
			$block_request = true;
		}

		$domain_url = wp_parse_url( get_site_url(), PHP_URL_HOST );
		if ( ! empty( $data['page_href'] ) && ! str_contains( $data['page_href'], (string) $domain_url ) ) {
			$block_request = true;
		}

		return (bool) apply_filters( 'wpd_ai_event_tracking_block_request_by_data', $block_request, $data );
	}

	/**
	 * @param array<string, mixed> $data Event payload.
	 * @return array<string, mixed>
	 */
	public function insert_event( $data ) {
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return array(
				'success'       => true,
				'message'       => __( 'Event tracking skipped during CRON execution.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'code'          => 'cron_skip',
				'rows_inserted' => 0,
			);
		}

		if ( is_admin() ) {
			return array(
				'success'       => true,
				'message'       => __( 'Event tracking skipped in admin area.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'code'          => 'admin_skip',
				'rows_inserted' => 0,
			);
		}

		if ( ! $this->can_reuse_session_for_event( $data ) ) {
			$ip_address = $this->get_client_ip_for_rate_limit();

			if ( $this->is_ip_banned( $ip_address ) ) {
				return array(
					'success'       => false,
					'message'       => __( 'IP address is banned from event tracking.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'code'          => 'ip_banned',
					'rows_inserted' => 0,
				);
			}

			if ( $this->is_rate_limit_exceeded( $ip_address ) ) {
				return array(
					'success'       => false,
					'message'       => __( 'Rate limit exceeded. Too many requests.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'code'          => 'rate_limit_exceeded',
					'rows_inserted' => 0,
				);
			}
		}

		$session_instance = $this->resolve_session_instance_for_event( $data );

		if ( ! $this->track_user() || ! $this->event_tracking_enabled ) {
			return array(
				'success'       => true,
				'message'       => __( 'Event tracking is disabled for this user.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'code'          => 'tracking_disabled',
				'rows_inserted' => 0,
			);
		}

		if ( empty( $session_instance->landing_page ) ) {
			return array(
				'success'       => false,
				'message'       => __( 'Event tracking skipped: no landing page in session.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'code'          => 'no_landing_page',
				'rows_inserted' => 0,
			);
		}

		if ( $session_instance->is_bot ) {
			return array(
				'success'       => true,
				'message'       => __( 'Event tracking skipped: bot detected.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'code'          => 'bot_detected',
				'rows_inserted' => 0,
			);
		}

		if ( isset( $data['event_type'] ) && 'form_submit' === $data['event_type'] && isset( $data['additional_data']['form_element_class'] ) && str_contains( $data['additional_data']['form_element_class'], 'cart' ) ) {
			return array(
				'success'       => true,
				'message'       => __( 'Event tracking skipped: cart form submission tracked via other means.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'code'          => 'cart_form_skip',
				'rows_inserted' => 0,
			);
		}

		$db_interactor = WPDAI_Database_Interactor::instance();
		$table_name    = $db_interactor->events_table;

		$data['date_created_gmt'] = current_time( 'mysql', true );

		if ( ! isset( $data['session_id'] ) || empty( $data['session_id'] ) ) {
			$data['session_id'] = $session_instance->session_id;
		}
		if ( ! isset( $data['ip_address'] ) || empty( $data['ip_address'] ) ) {
			$data['ip_address'] = $session_instance->ip_address;
		}
		if ( ! isset( $data['user_id'] ) || empty( $data['user_id'] ) ) {
			$data['user_id'] = $session_instance->user_id;
		}
		if ( ! isset( $data['page_href'] ) || empty( $data['page_href'] ) ) {
			$data['page_href'] = $session_instance->page_href;
		}

		if ( ! empty( $data['page_href'] ) && function_exists( 'wpdai_is_ajax_or_rest_url' ) && wpdai_is_ajax_or_rest_url( $data['page_href'] ) ) {
			$origin = function_exists( 'wpdai_get_event_origin_url' )
				? wpdai_get_event_origin_url( '', isset( $data['product_id'] ) ? (int) $data['product_id'] : 0 )
				: '';
			if ( '' !== $origin ) {
				$data['page_href'] = $origin;
			}
		}
		if ( ! isset( $data['object_type'] ) || empty( $data['object_type'] ) ) {
			$data['object_type'] = $this->object_type;
		}
		if ( ! isset( $data['object_id'] ) || empty( $data['object_id'] ) ) {
			$data['object_id'] = $this->object_id;
		}
		if ( ! isset( $data['event_type'] ) || empty( $data['event_type'] ) ) {
			$data['event_type'] = $this->event_type;
		}
		if ( ! isset( $data['event_quantity'] ) ) {
			$data['event_quantity'] = $this->event_quantity;
		}
		if ( ! isset( $data['event_value'] ) || empty( $data['event_value'] ) ) {
			$data['event_value'] = $this->event_value;
		}
		if ( ! isset( $data['product_id'] ) || empty( $data['product_id'] ) ) {
			$data['product_id'] = $this->product_id;
		}
		if ( ! isset( $data['variation_id'] ) || empty( $data['variation_id'] ) ) {
			$data['variation_id'] = $this->variation_id;
		}

		$data['session_id']     = sanitize_text_field( $data['session_id'] );
		$data['ip_address']     = sanitize_text_field( $data['ip_address'] );
		$data['user_id']        = (int) $data['user_id'];
		$data['page_href']      = esc_url_raw( $data['page_href'] );
		$data['object_type']    = sanitize_text_field( $data['object_type'] );
		$data['object_id']      = (int) $data['object_id'];
		$data['event_type']     = sanitize_text_field( $data['event_type'] );
		$data['event_quantity'] = (int) $data['event_quantity'];
		$data['event_value']    = (float) $data['event_value'];
		$data['product_id']     = (int) $data['product_id'];
		$data['variation_id']   = (int) $data['variation_id'];

		if ( $this->should_block_request_by_data( $data ) ) {
			return array(
				'success'       => false,
				'message'       => __( 'Event tracking blocked: invalid or incomplete data.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'code'          => 'invalid_data',
				'rows_inserted' => 0,
			);
		}

		if ( ! $this->session_persisted_this_request ) {
			$session_instance->store_session_in_db();
			$this->session_persisted_this_request = true;
		}

		$data = apply_filters( 'wpd_ai_event_data_before_insertion', $data );
		$data = wpdai_prepare_event_row_for_db( $data );

		$rows_inserted = $db_interactor->add_row( $table_name, $data );

		if ( $rows_inserted > 0 ) {
			return array(
				'success'       => true,
				'message'       => sprintf(
					/* translators: %d: Number of rows inserted */
					_n( 'Event successfully tracked.', 'Events successfully tracked.', $rows_inserted, 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					$rows_inserted
				),
				'code'          => 'success',
				'rows_inserted' => $rows_inserted,
			);
		}

		return array(
			'success'       => false,
			'message'       => __( 'Failed to insert event into database.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'code'          => 'insert_failed',
			'rows_inserted' => 0,
		);
	}
}

class_alias( 'WPDAI_Event_Tracking', 'WPDAI_Event_Tracking_V2' );
