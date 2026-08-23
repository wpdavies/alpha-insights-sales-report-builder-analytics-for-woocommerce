<?php
/**
 * Front-end experiment runtime: visitor ID, assignment, CSS/JS/PHP apply.
 *
 * @package Alpha Insights
 * @since 5.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPDAI_Experiment_Runtime
 */
class WPDAI_Experiment_Runtime {

	/**
	 * Visitor ID for this request.
	 *
	 * @var string
	 */
	protected static $visitor_id = '';

	/**
	 * Cookie assignment map experiment_id => variant_key.
	 *
	 * @var array|null
	 */
	protected static $assignment_map = null;

	/**
	 * Applied variant payloads for this request.
	 *
	 * @var array
	 */
	protected static $applied = array();

	/**
	 * Whether assignment already ran.
	 *
	 * @var bool
	 */
	protected static $did_assign = false;

	/**
	 * Whether this request matched a running experiment page rule.
	 *
	 * @var bool
	 */
	protected static $matched_page = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp', array( __CLASS__, 'maybe_assign' ), 0 );
		add_action( 'wp_head', array( __CLASS__, 'print_css' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'print_js' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'print_session_bridge' ), 5 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'stamp_order_meta' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'stamp_order_meta' ), 20, 1 );
	}

	/**
	 * Skip assignment on non-storefront requests.
	 *
	 * @return bool
	 */
	protected static function should_skip() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return true;
		}
		if ( did_action( 'parse_query' ) && ( is_feed() || is_robots() || is_trackback() ) ) {
			return true;
		}
		if ( ! function_exists( 'wpdai_is_analytics_enabled' ) || ! wpdai_is_analytics_enabled() ) {
			return true;
		}
		return false;
	}

	/**
	 * Assign and apply on `wp` (conditionals are available).
	 *
	 * @return void
	 */
	public static function maybe_assign() {
		if ( self::$did_assign || self::should_skip() ) {
			return;
		}
		self::$did_assign = true;

		$running = WPDAI_Experiment_Store::get_running();
		if ( empty( $running ) ) {
			return;
		}

		$page_matches = array();
		foreach ( $running as $experiment ) {
			if ( WPDAI_Experiment_Eligibility::matches_page( $experiment ) ) {
				$page_matches[]     = $experiment;
				self::$matched_page = true;
			}
		}

		if ( empty( $page_matches ) ) {
			return;
		}

		WPDAI_Experiment_Cache::bypass_current_request();

		$preview    = self::get_preview();
		$visitor_id = self::ensure_visitor_id();
		$session_id = self::get_session_id();
		$map        = self::get_assignment_map();
		self::$assignment_map = $map;

		foreach ( $page_matches as $experiment ) {
			$variant_key = '';
			$variant     = null;
			$holdout     = false;
			$is_preview  = ( $preview && isset( $preview['slug'] ) && $preview['slug'] === $experiment['slug'] );
			$log_events  = ! $is_preview;

			if ( $is_preview ) {
				$variant_key = $preview['variant_key'];
				$variant     = WPDAI_Experiment_Assigner::find_variant( $experiment, $variant_key );
				if ( empty( $variant ) ) {
					$variant     = WPDAI_Experiment_Assigner::find_control_variant( $experiment );
					$variant_key = $variant ? $variant['variant_key'] : 'control';
				}
			} else {
				if ( ! WPDAI_Experiment_Eligibility::is_eligible( $experiment, $visitor_id ) ) {
					continue;
				}
				$assigned    = WPDAI_Experiment_Assigner::assign( $experiment, $visitor_id, $session_id, $log_events );
				$holdout     = ! empty( $assigned['holdout'] );
				$variant_key = $assigned['variant_key'];
				$variant     = $assigned['variant'];
				if ( empty( $variant ) && ! $holdout && '' !== $variant_key ) {
					$variant = WPDAI_Experiment_Assigner::find_control_variant( $experiment );
				}
			}

			if ( $holdout || empty( $variant_key ) || empty( $variant ) ) {
				continue;
			}

			$map[ (string) $experiment['id'] ] = $variant_key;
			self::$assignment_map              = $map;
			self::$applied[] = array(
				'experiment' => $experiment,
				'variant'    => $variant,
			);

			if ( wpdai_experiments_is_pro() && ! empty( $variant['php'] ) ) {
				do_action( 'wpd_ai_experiment_run_php_snippet', $variant['php'], $experiment, $variant );
			}
		}

		self::$assignment_map = $map;
		self::write_assignment_cookie( $map );
	}

	/**
	 * Whether the current request matches a running experiment page rule.
	 *
	 * @return bool
	 */
	public static function request_matches_running_experiment() {
		if ( self::$did_assign ) {
			return self::$matched_page;
		}

		foreach ( WPDAI_Experiment_Store::get_running() as $experiment ) {
			if ( WPDAI_Experiment_Eligibility::matches_page( $experiment ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Assignment map from cookie / this request.
	 *
	 * @return array
	 */
	public static function get_assignment_map() {
		if ( is_array( self::$assignment_map ) ) {
			return self::$assignment_map;
		}

		$map = array();
		if ( ! empty( $_COOKIE[ WPD_AI_EXPS_COOKIE ] ) ) {
			$raw = wp_unslash( $_COOKIE[ WPD_AI_EXPS_COOKIE ] );
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $experiment_id => $variant_key ) {
					$map[ (string) absint( $experiment_id ) ] = sanitize_key( $variant_key );
				}
			}
		}

		self::$assignment_map = $map;
		return $map;
	}

	/**
	 * Assigned variant key by experiment slug.
	 *
	 * @param string $slug Experiment slug.
	 * @return string|null
	 */
	public static function get_assigned_variant_key( $slug ) {
		if ( ! self::$did_assign && did_action( 'wp' ) ) {
			self::maybe_assign();
		}

		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return null;
		}

		foreach ( self::$applied as $applied ) {
			if ( isset( $applied['experiment']['slug'] ) && $applied['experiment']['slug'] === $slug ) {
				return isset( $applied['variant']['variant_key'] ) ? $applied['variant']['variant_key'] : null;
			}
		}

		$map = self::get_assignment_map();
		if ( empty( $map ) ) {
			return null;
		}

		$experiment = WPDAI_Experiment_Store::get_by_slug( $slug );
		if ( ! $experiment ) {
			return null;
		}

		$id = (string) $experiment['id'];
		return isset( $map[ $id ] ) && '' !== $map[ $id ] ? $map[ $id ] : null;
	}

	/**
	 * Visitor ID for this request.
	 *
	 * @return string
	 */
	public static function get_visitor_id() {
		return self::$visitor_id ? self::$visitor_id : self::read_visitor_id();
	}

	/**
	 * Print combined CSS for applied variants.
	 *
	 * @return void
	 */
	public static function print_css() {
		$css = '';
		foreach ( self::$applied as $applied ) {
			if ( ! empty( $applied['variant']['css'] ) ) {
				$css .= "\n" . $applied['variant']['css'];
			}
		}
		if ( '' === $css ) {
			return;
		}
		echo '<style id="wpd-ai-experiment-css">' . wp_strip_all_tags( $css ) . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Print combined JS for applied variants.
	 *
	 * @return void
	 */
	public static function print_js() {
		$js = '';
		foreach ( self::$applied as $applied ) {
			if ( ! empty( $applied['variant']['js'] ) ) {
				$js .= "\n" . $applied['variant']['js'];
			}
		}
		if ( '' === $js ) {
			return;
		}

		$js = str_replace( array( '</script', '</SCRIPT' ), array( '<\/script', '<\/SCRIPT' ), $js );
		echo '<script id="wpd-ai-experiment-js">' . $js . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin-owned experiment JS.
	}

	/**
	 * Copy the analytics v2 localStorage session ID onto a cookie on uncached experiment pages
	 * so later PHP assignment/order stamps can join conversions.
	 *
	 * @return void
	 */
	public static function print_session_bridge() {
		if ( empty( self::$applied ) && ! self::$matched_page ) {
			return;
		}
		?>
		<script id="wpd-ai-experiment-session-bridge">
		(function () {
			try {
				if (document.cookie.indexOf('wpd_ai_session_id=') !== -1) {
					return;
				}
				var sid = window.localStorage ? window.localStorage.getItem('wpd_ai_v2_session_id') : null;
				if (!sid) {
					return;
				}
				document.cookie = 'wpd_ai_session_id=' + encodeURIComponent(sid) + '; path=/; max-age=2592000; SameSite=Lax';
			} catch (e) {}
		})();
		</script>
		<?php
	}

	/**
	 * Stamp experiment meta onto a new order.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public static function stamp_order_meta( $order ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$vid = self::get_visitor_id();
		if ( $vid && ! $order->get_meta( '_wpd_ai_visitor_id' ) ) {
			$order->update_meta_data( '_wpd_ai_visitor_id', $vid );
		}

		$map = self::get_assignment_map();
		if ( ! empty( $map ) && ! $order->get_meta( '_wpd_ai_experiments' ) ) {
			$order->update_meta_data( '_wpd_ai_experiments', wp_json_encode( $map ) );
		}
	}

	/**
	 * Stamp by order ID.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function stamp_order_meta_by_id( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		self::stamp_order_meta( $order );
		$order->save();
	}

	/**
	 * Authorized QA preview from query args.
	 *
	 * @return array|null
	 */
	protected static function get_preview() {
		if ( empty( $_GET['wpd_ai_preview_exp'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return null;
		}
		if ( ! function_exists( 'wpdai_is_user_authorized_to_use_alpha_insights' ) || ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
			return null;
		}

		return array(
			'slug'        => sanitize_title( wp_unslash( $_GET['wpd_ai_preview_exp'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'variant_key' => isset( $_GET['wpd_ai_preview_var'] ) ? sanitize_key( wp_unslash( $_GET['wpd_ai_preview_var'] ) ) : 'control', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
	}

	/**
	 * Ensure visitor ID cookie exists.
	 *
	 * @return string
	 */
	protected static function ensure_visitor_id() {
		$visitor_id = self::read_visitor_id();
		if ( '' === $visitor_id ) {
			$visitor_id = 'wpdvid_' . wp_generate_password( 16, false, false );
		}
		self::$visitor_id = $visitor_id;
		wpdai_set_experiment_cookie( WPD_AI_VID_COOKIE, $visitor_id, time() + YEAR_IN_SECONDS );
		return $visitor_id;
	}

	/**
	 * Read visitor ID from cookie.
	 *
	 * @return string
	 */
	protected static function read_visitor_id() {
		if ( ! empty( $_COOKIE[ WPD_AI_VID_COOKIE ] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE[ WPD_AI_VID_COOKIE ] ) );
		}
		return '';
	}

	/**
	 * Analytics session ID if already present.
	 *
	 * @return string
	 */
	protected static function get_session_id() {
		if ( ! empty( $_COOKIE['wpd_ai_session_id'] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE['wpd_ai_session_id'] ) );
		}
		return '';
	}

	/**
	 * Persist compact assignment cookie.
	 *
	 * @param array $map Assignment map.
	 * @return void
	 */
	protected static function write_assignment_cookie( $map ) {
		$clean = array();
		foreach ( $map as $experiment_id => $variant_key ) {
			if ( '' === $variant_key ) {
				continue;
			}
			$clean[ (string) absint( $experiment_id ) ] = sanitize_key( $variant_key );
		}
		wpdai_set_experiment_cookie( WPD_AI_EXPS_COOKIE, wp_json_encode( $clean ), time() + YEAR_IN_SECONDS );
	}
}
