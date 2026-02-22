<?php
/**
 * WPDAI_Reviews – review prompt logic and display
 *
 * Asks for reviews at the right time (e.g. after viewing reports multiple times).
 * Free: WordPress.org plugin reviews URL.
 * Pro: Trustpilot URL.
 *
 * @package Alpha Insights
 * @since 5.4.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 */
defined( 'ABSPATH' ) || exit;

class WPDAI_Reviews {

	const OPTION_KEY         = 'wpd_ai_review_state';
	const MIN_REPORT_VIEWS   = 7;
	const MIN_DAYS_ACTIVE    = 5;
	const AJAX_ACTION        = 'wpd_ai_review_dismiss';
	const PREVIEW_QUERY_PARAM = 'wpd_ai_preview_review';

	/**
	 * Report page slugs that count toward the "viewed report" threshold.
	 *
	 * @var string[]
	 */
	private static $report_page_slugs = array(
		'wpd-sales-reports',
		'wpd-website-traffic-reports',
		'wpd-profit-loss-statement',
		'wpd-expense-reports',
		'wpd-advertising',
		'wpd-expense-management',
	);

	/**
	 * Single instance.
	 *
	 * @var WPDAI_Reviews|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return WPDAI_Reviews
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'current_screen', array( $this, 'maybe_increment_report_views' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_review_toast_styles' ), 10 );
		add_action( 'admin_footer', array( $this, 'maybe_render_review_toast' ), 20 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_dismiss' ) );
	}

	/**
	 * Get the review URL for the current plugin version (free vs pro).
	 *
	 * @return string Escaped URL for the appropriate review destination.
	 */
	public static function get_review_url() {
		if ( defined( 'WPD_AI_PRO' ) && WPD_AI_PRO ) {
			return 'https://www.trustpilot.com/review/wpdavies.dev';
		}
		return 'https://wordpress.org/support/plugin/alpha-insights-sales-report-builder-analytics-for-woocommerce/reviews/#new-post';
	}

	/**
	 * Whether the current admin screen is a "report" page (views count toward prompt).
	 *
	 * @return bool
	 */
	public static function is_report_page() {
		if ( ! is_admin() ) {
			return false;
		}
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		return in_array( $page, self::$report_page_slugs, true );
	}

	/**
	 * Get stored review state.
	 *
	 * @return array{ report_views: int, dismissed_at: int, first_view_at: int }
	 */
	public static function get_state() {
		$defaults = array(
			'report_views'   => 0,
			'dismissed_at'  => 0,
			'first_view_at' => 0,
		);
		$state = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		return wp_parse_args( $state, $defaults );
	}

	/**
	 * Update stored review state.
	 *
	 * @param array $state State array (partial merge with existing).
	 * @return bool
	 */
	public static function save_state( $state ) {
		$current = self::get_state();
		$merged  = array_merge( $current, $state );
		return update_option( self::OPTION_KEY, $merged, true );
	}

	/**
	 * Get current report view count.
	 *
	 * @return int
	 */
	public static function get_report_view_count() {
		$state = self::get_state();
		return max( 0, (int) $state['report_views'] );
	}

	/**
	 * Increment report view count (and set first_view_at if not set).
	 */
	public function maybe_increment_report_views() {
		if ( ! self::is_report_page() ) {
			return;
		}
		if ( ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
			return;
		}
		$state = self::get_state();
		$now   = time();
		if ( empty( $state['first_view_at'] ) ) {
			$state['first_view_at'] = $now;
		}
		$state['report_views'] = self::get_report_view_count() + 1;
		self::save_state( $state );
	}

	/**
	 * Whether the prompt is being shown in preview mode (query param, admin only).
	 *
	 * @return bool
	 */
	public static function is_preview_mode() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return isset( $_GET[ self::PREVIEW_QUERY_PARAM ] ) && '1' === sanitize_text_field( wp_unslash( $_GET[ self::PREVIEW_QUERY_PARAM ] ) );
	}

	/**
	 * Whether the current request is for an Alpha Insights admin page (by page param).
	 * Use this when get_current_screen() may not be set yet (e.g. early in admin_notices).
	 *
	 * @return bool
	 */
	public static function is_wpdai_page_by_param() {
		if ( ! is_admin() ) {
			return false;
		}
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$wpdai_pages = array_merge(
			self::$report_page_slugs,
			array(
				'wpd-settings',
				'wpd-about-help',
				'wpd-getting-started',
			)
		);
		return in_array( $page, $wpdai_pages, true );
	}

	/**
	 * Whether we should show the review prompt.
	 *
	 * Displays on report pages only (Sales Reports, Website Traffic, etc.) after the user
	 * has viewed reports at least MIN_REPORT_VIEWS times and at least MIN_DAYS_ACTIVE days
	 * since first report view. Dismissing hides it permanently.
	 *
	 * Preview: add ?wpd_ai_preview_review=1 to any Alpha Insights admin page URL (when
	 * logged in as an admin) to force the prompt to show for testing.
	 *
	 * @return bool
	 */
	public static function should_show_prompt() {
		if ( ! is_admin() ) {
			return false;
		}
		// Preview first: show when query param is present and user can manage options, on any Alpha Insights page (by GET param, so it works before get_current_screen() is set).
		if ( self::is_preview_mode() && self::is_wpdai_page_by_param() ) {
			return true;
		}
		if ( ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
			return false;
		}
		$state = self::get_state();
		if ( ! empty( $state['dismissed_at'] ) ) {
			return false;
		}
		$min_views = (int) apply_filters( 'wpd_ai_review_min_report_views', self::MIN_REPORT_VIEWS );
		$views     = self::get_report_view_count();
		if ( $views < $min_views ) {
			return false;
		}
		$min_days = (int) apply_filters( 'wpd_ai_review_min_days_active', self::MIN_DAYS_ACTIVE );
		$first    = ! empty( $state['first_view_at'] ) ? (int) $state['first_view_at'] : time();
		$days     = ( time() - $first ) / DAY_IN_SECONDS;
		if ( $days < $min_days ) {
			return false;
		}
		// Only show on report pages so it's contextual.
		if ( ! self::is_report_page() ) {
			return false;
		}
		return true;
	}

	/**
	 * Mark the review prompt as dismissed.
	 */
	public static function mark_dismissed() {
		self::save_state( array( 'dismissed_at' => time() ) );
	}

	/**
	 * Enqueue review toast CSS via wp_add_inline_style (WordPress.org appropriate).
	 * Only runs when the prompt will be shown.
	 */
	public function maybe_enqueue_review_toast_styles() {
		if ( ! self::should_show_prompt() ) {
			return;
		}
		$toast_id = 'wpd-ai-review-toast';
		$css      = sprintf(
			'#%1$s {
				position: fixed;
				bottom: 20px;
				left: 20px;
				max-width: 380px;
				padding: 14px 16px 14px 18px;
				background: #fff;
				border-left: 4px solid #138fdd;
				box-shadow: 0 4px 20px rgba(0,0,0,0.15);
				border-radius: 6px;
				z-index: 100000;
				font-size: 13px;
				line-height: 1.45;
				animation: wpd-ai-review-toast-in 0.3s ease-out;
			}
			#%1$s .wpd-ai-review-toast-close {
				position: absolute;
				top: 8px;
				right: 8px;
				width: 28px;
				height: 28px;
				padding: 0;
				border: none;
				background: transparent;
				color: #666;
				cursor: pointer;
				border-radius: 4px;
				display: flex;
				align-items: center;
				justify-content: center;
				font-size: 18px;
				line-height: 1;
			}
			#%1$s .wpd-ai-review-toast-close:hover {
				background: #f0f0f0;
				color: #333;
			}
			#%1$s .wpd-ai-review-toast-title { font-weight: 600; margin: 0 24px 6px 0; font-size: 14px; color: #1d2327; }
			#%1$s .wpd-ai-review-toast-text { margin: 0 24px 0 0; }
			#%1$s .wpd-ai-review-toast-signoff { margin: 8px 24px 12px 0; font-size: 12px; color: #50575e; }
			#%1$s .wpd-ai-review-toast-actions { margin-top: 10px; }
			#%1$s .wpd-ai-review-toast-actions .button { margin-right: 8px; }
			#%1$s .wpd-ai-review-toast-preview-label { font-size: 12px; opacity: 0.85; }
			#%1$s.wpd-ai-review-toast-hide {
				animation: wpd-ai-review-toast-out 0.25s ease-out forwards;
			}
			@keyframes wpd-ai-review-toast-in {
				from { opacity: 0; transform: translateY(10px); }
				to { opacity: 1; transform: translateY(0); }
			}
			@keyframes wpd-ai-review-toast-out {
				to { opacity: 0; transform: translateY(10px); }
			}',
			esc_attr( $toast_id )
		);
		wp_register_style( 'wpd-ai-review-toast', false, array(), null );
		wp_enqueue_style( 'wpd-ai-review-toast' );
		wp_add_inline_style( 'wpd-ai-review-toast', $css );
	}

	/**
	 * Output the review prompt as a toast notification at the bottom left.
	 *
	 * Renders in admin_footer so it is not affected by page-specific HTML. Only shows
	 * when should_show_prompt() is true (report pages, or any Alpha Insights page with
	 * preview param).
	 */
	public function maybe_render_review_toast() {
		if ( ! self::should_show_prompt() ) {
			return;
		}
		$url        = self::get_review_url();
		$is_pro     = defined( 'WPD_AI_PRO' ) && WPD_AI_PRO;
		$is_preview = self::is_preview_mode();
		$title = __( 'Enjoying Alpha Insights?', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		if ( $is_pro ) {
			$message = __( 'If you have a minute, a quick review on Trustpilot helps indie developers like us grow far more than you realize :)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
			$cta     = __( 'Leave a review on Trustpilot', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		} else {
			$message = __( 'If you have a minute, a quick review on WordPress.org helps indie developers like us grow far more than you realize :)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
			$cta     = __( 'Leave a review on WordPress.org', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		}
		$signoff = __( 'Chris Davies, Founder', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		$dismiss_url = wp_nonce_url(
			add_query_arg( array( 'action' => self::AJAX_ACTION ), admin_url( 'admin-ajax.php' ) ),
			self::AJAX_ACTION
		);
		$toast_id = 'wpd-ai-review-toast';
		?>
		<div id="<?php echo esc_attr( $toast_id ); ?>" class="wpd-ai-review-toast" role="status" data-wpd-ai-review-dismiss-url="<?php echo esc_attr( $dismiss_url ); ?>" data-wpd-ai-review-preview="<?php echo $is_preview ? '1' : '0'; ?>">
			<button type="button" class="wpd-ai-review-toast-close" aria-label="<?php esc_attr_e( 'Dismiss', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>">&times;</button>
			<div class="wpd-ai-review-toast-title"><?php echo esc_html( $title ); ?></div>
			<div class="wpd-ai-review-toast-text"><?php echo esc_html( $message ); ?></div>
			<div class="wpd-ai-review-toast-signoff"><?php echo esc_html( $signoff ); ?></div>
			<div class="wpd-ai-review-toast-actions">
				<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary button-small"><?php echo esc_html( $cta ); ?></a>
				<?php if ( $is_preview ) : ?>
					<em class="wpd-ai-review-toast-preview-label"><?php esc_html_e( 'Preview', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></em>
				<?php endif; ?>
			</div>
		</div>
		<script>
		(function() {
			var el = document.getElementById('<?php echo esc_js( $toast_id ); ?>');
			if (!el) return;
			var dismissUrl = el.getAttribute('data-wpd-ai-review-dismiss-url');
			var isPreview = el.getAttribute('data-wpd-ai-review-preview') === '1';
			function hide() {
				el.classList.add('wpd-ai-review-toast-hide');
				setTimeout(function() { el.style.display = 'none'; }, 260);
			}
			el.querySelector('.wpd-ai-review-toast-close').addEventListener('click', function() {
				if (!isPreview && dismissUrl) { window.fetch(dismissUrl).catch(function() {}); }
				hide();
			});
		})();
		</script>
		<?php
	}

	/**
	 * AJAX handler: dismiss the review prompt.
	 */
	public function ajax_dismiss() {
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), self::AJAX_ACTION ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ), 400 );
		}
		if ( ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ), 403 );
		}
		self::mark_dismissed();
		wp_send_json_success();
	}
}

WPDAI_Reviews::get_instance();
