<?php
/**
 * Experiments admin list and editor.
 *
 * @package Alpha Insights
 * @since 5.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPDAI_Experiments_Admin
 */
class WPDAI_Experiments_Admin {

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_analytics_notice' ) );
	}

	/**
	 * Enqueue code editors on the experiments screen.
	 *
	 * @param string $hook Current hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( WPDAI_Admin_Menu::$experiments_slug !== $page ) {
			return;
		}

		$css_settings = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
		$js_settings  = wp_enqueue_code_editor( array( 'type' => 'text/javascript' ) );
		$php_settings = wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php' ) );
		wp_enqueue_script( 'wp-theme-plugin-editor' );
		wp_enqueue_style( 'wp-codemirror' );
		wp_enqueue_style(
			'wpd-ai-experiments-admin',
			WPD_AI_URL_PATH . 'assets/css/experiments/wpd-ai-experiments-admin.css',
			array( 'wpd-alpha-insights-admin' ),
			WPD_AI_VER
		);

		wp_enqueue_script(
			'wpd-ai-experiments-admin',
			WPD_AI_URL_PATH . 'assets/js/experiments/wpd-ai-experiments-admin.js',
			array( 'jquery', 'wp-theme-plugin-editor', 'wp-url' ),
			WPD_AI_VER,
			true
		);

		$current_id = isset( $_GET['experiment_id'] ) ? absint( $_GET['experiment_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		wp_localize_script(
			'wpd-ai-experiments-admin',
			'wpdAiExperimentsAdmin',
			array(
				'isPro'              => wpdai_experiments_is_pro() ? 1 : 0,
				'freeRunningBlocked' => ( ! wpdai_experiments_is_pro() && WPDAI_Experiment_Store::count_running( $current_id ) >= 1 ) ? 1 : 0,
				'cssSettings'        => $css_settings,
				'jsSettings'         => $js_settings,
				'phpSettings'        => $php_settings,
				'i18n'               => array(
					'variant'           => __( 'Variant', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'remove'            => __( 'Remove', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'freeRunningLimit'  => self::free_running_limit_message(),
				),
			)
		);
	}

	/**
	 * Notice when analytics is disabled.
	 *
	 * @return void
	 */
	public static function maybe_analytics_notice() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( WPDAI_Admin_Menu::$experiments_slug !== $page ) {
			return;
		}
		if ( function_exists( 'wpdai_is_analytics_enabled' ) && wpdai_is_analytics_enabled() ) {
			return;
		}
		echo '<div class="wpd-notice notice notice-warning"><p>' . esc_html__( 'Experiments require Alpha Insights analytics to be enabled. Turn on website traffic tracking in General Settings before running a test.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) . '</p></div>';
	}

	/**
	 * Handle save / status / delete before output.
	 *
	 * @return void
	 */
	public static function handle_actions() {
		if ( ! is_admin() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( WPDAI_Admin_Menu::$experiments_slug !== $page ) {
			return;
		}
		if ( ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
			return;
		}

		if ( isset( $_POST['wpd_ai_experiment_save'] ) ) {
			check_admin_referer( 'wpd_ai_experiment_save', 'wpd_ai_experiment_nonce' );
			$result = self::save_from_request();
			if ( is_wp_error( $result ) ) {
				self::queue_notice( $result->get_error_code(), $result->get_error_message(), 'error' );
				$id = isset( $_POST['experiment']['id'] ) ? absint( $_POST['experiment']['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$redirect = self::edit_url( $id );
				wp_safe_redirect( $redirect );
				exit;
			}
			self::queue_notice( 'saved', __( 'Experiment saved.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'updated' );
			wp_safe_redirect( self::edit_url( absint( $result ) ) );
			exit;
		}

		$action = isset( $_GET['wpd-action'] ) ? sanitize_key( wp_unslash( $_GET['wpd-action'] ) ) : '';
		$id     = isset( $_GET['experiment_id'] ) ? absint( $_GET['experiment_id'] ) : 0;

		if ( $id && in_array( $action, array( 'run', 'pause', 'complete', 'delete' ), true ) ) {
			check_admin_referer( 'wpd_ai_experiment_status_' . $id );
			if ( 'delete' === $action ) {
				WPDAI_Experiment_Store::delete( $id );
				self::queue_notice( 'deleted', __( 'Experiment deleted.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'updated' );
			} else {
				$map    = array(
					'run'      => 'running',
					'pause'    => 'paused',
					'complete' => 'completed',
				);
				$result = self::change_status( $id, $map[ $action ] );
				if ( is_wp_error( $result ) ) {
					self::queue_notice( $result->get_error_code(), $result->get_error_message(), 'error' );
				} else {
					self::queue_notice( 'status', __( 'Experiment updated.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'updated' );
				}
			}
			wp_safe_redirect( admin_url( 'admin.php?page=' . WPDAI_Admin_Menu::$experiments_slug ) );
			exit;
		}
	}

	/**
	 * Render the experiments page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) );
		}

		if ( self::is_edit_screen() ) {
			self::render_edit();
			return;
		}
		self::render_list();
	}

	/**
	 * List screen.
	 *
	 * @return void
	 */
	protected static function render_list() {
		$experiments      = WPDAI_Experiment_Store::get_all( true );
		$summaries        = self::get_list_summaries( $experiments );
		$edit_url         = self::edit_url();
		$results_url      = self::results_url();
		$free_run_blocked = ! wpdai_experiments_is_pro() && WPDAI_Experiment_Store::count_running() >= 1;
		?>
		<div class="wrap">
			<?php do_action( 'wpd_before_heading' ); ?>
			<h3><?php esc_html_e( 'Experiment (Beta)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></h3>
			<?php do_action( 'wpd_before_content' ); ?>
			<?php
			$printed_codes = self::print_notices();
			if ( $free_run_blocked && ! in_array( 'free_limit', $printed_codes, true ) ) {
				wpdai_admin_notice( self::free_running_limit_message(), 'warning' );
			}
			?>
			<div class="wpd-white-block">
				<div class="wpd-wrapper">
					<div class="wpd-section-heading wpd-inline">
						<?php esc_html_e( 'A/B Tests', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
						<a href="<?php echo esc_url( $results_url ); ?>" class="button pull-right" style="margin-left:8px;"><?php esc_html_e( 'View results', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a>
						<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-primary pull-right"><?php esc_html_e( 'Add New', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a>
					</div>
					<p class="wpd-exp-list-intro">
						<?php esc_html_e( 'Experiments split visitors between a control and one or more treatments so you can measure which version converts better. Each visitor is assigned on the first matching page view and then sees that variant’s CSS, JS, or PHP until you pause or complete the test.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
					</p>
				</div>
				<div class="wpd-exp-list">
					<?php if ( empty( $experiments ) ) : ?>
						<p class="wpd-exp-list-empty"><?php esc_html_e( 'No experiments yet. Create one to split traffic between a control and a treatment.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></p>
					<?php endif; ?>
					<?php foreach ( $experiments as $experiment ) : ?>
						<?php
						$eid     = (int) $experiment['id'];
						$summary = isset( $summaries[ $eid ] ) ? $summaries[ $eid ] : array();
						self::render_list_experiment( $experiment, $summary, $results_url, $free_run_blocked );
						?>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Per-experiment stats from the experiments data warehouse.
	 *
	 * @param array $experiments Hydrated experiments with variants.
	 * @return array
	 */
	protected static function get_list_summaries( $experiments ) {
		$summaries = array();
		if ( ! is_array( $experiments ) ) {
			return $summaries;
		}

		foreach ( $experiments as $experiment ) {
			$eid = isset( $experiment['id'] ) ? (int) $experiment['id'] : 0;
			if ( $eid < 1 ) {
				continue;
			}
			$summaries[ $eid ] = array(
				'arms'        => array(),
				'winner_key'  => '',
				'winner_name' => '',
				'winner_lift' => null,
				'has_data'    => false,
			);
			$variants = isset( $experiment['variants'] ) && is_array( $experiment['variants'] ) ? $experiment['variants'] : array();
			foreach ( $variants as $variant ) {
				$vkey = isset( $variant['variant_key'] ) ? $variant['variant_key'] : '';
				if ( '' === $vkey ) {
					continue;
				}
				$summaries[ $eid ]['arms'][ $vkey ] = array(
					'name'               => ! empty( $variant['name'] ) ? $variant['name'] : $vkey,
					'is_control'         => ! empty( $variant['is_control'] ),
					'exposures'          => 0,
					'add_to_carts'       => 0,
					'initiate_checkouts' => 0,
					'conversions'        => 0,
					'rate'               => 0,
					'lift'               => null,
				);
			}
		}

		if ( empty( $summaries ) || ! function_exists( 'wpdai_data_warehouse' ) ) {
			return $summaries;
		}

		$warehouse = wpdai_data_warehouse(
			array(
				'date_preset'         => 'all_time',
				'date_format_display' => 'year',
			)
		);
		$warehouse->fetch_data( array( 'experiments' ) );
		$tables = $warehouse->get_data( 'experiments', 'data_table' );
		$rows   = ( is_array( $tables ) && isset( $tables['variants'] ) && is_array( $tables['variants'] ) ) ? $tables['variants'] : array();

		foreach ( $rows as $row ) {
			$eid  = isset( $row['experiment_id'] ) ? (int) $row['experiment_id'] : 0;
			$vkey = isset( $row['variant_key'] ) ? $row['variant_key'] : '';
			if ( $eid < 1 || '' === $vkey || ! isset( $summaries[ $eid ] ) ) {
				continue;
			}
			if ( ! isset( $summaries[ $eid ]['arms'][ $vkey ] ) ) {
				$summaries[ $eid ]['arms'][ $vkey ] = array(
					'name'               => ! empty( $row['variant_name'] ) ? $row['variant_name'] : $vkey,
					'is_control'         => ! empty( $row['is_control'] ),
					'exposures'          => 0,
					'add_to_carts'       => 0,
					'initiate_checkouts' => 0,
					'conversions'        => 0,
					'rate'               => 0,
					'lift'               => null,
				);
			}

			$arm = $summaries[ $eid ]['arms'][ $vkey ];
			$arm['exposures']          = isset( $row['exposures'] ) ? (int) $row['exposures'] : 0;
			$arm['add_to_carts']       = isset( $row['add_to_carts'] ) ? (int) $row['add_to_carts'] : 0;
			$arm['initiate_checkouts'] = isset( $row['initiate_checkouts'] ) ? (int) $row['initiate_checkouts'] : 0;
			$arm['conversions']        = isset( $row['conversions'] ) ? (int) $row['conversions'] : 0;
			$arm['rate']               = isset( $row['conversion_rate'] ) ? (float) $row['conversion_rate'] : 0;
			if ( empty( $arm['is_control'] ) && isset( $row['lift'] ) ) {
				$arm['lift'] = (float) $row['lift'];
			}
			$summaries[ $eid ]['arms'][ $vkey ] = $arm;
			if ( $arm['exposures'] > 0 ) {
				$summaries[ $eid ]['has_data'] = true;
			}
		}

		foreach ( $summaries as $eid => $summary ) {
			$best_key  = '';
			$best_rate = -1;
			foreach ( $summary['arms'] as $vkey => $arm ) {
				if ( $arm['exposures'] > 0 && $arm['rate'] > $best_rate ) {
					$best_rate = $arm['rate'];
					$best_key  = $vkey;
				}
			}
			if ( '' !== $best_key ) {
				$summaries[ $eid ]['winner_key']  = $best_key;
				$summaries[ $eid ]['winner_name'] = $summaries[ $eid ]['arms'][ $best_key ]['name'];
				$summaries[ $eid ]['winner_lift'] = $summaries[ $eid ]['arms'][ $best_key ]['lift'];
			}
		}

		return $summaries;
	}

	/**
	 * Whether a snippet field has code.
	 *
	 * @param mixed $value CSS, JS, or PHP string.
	 * @return bool
	 */
	protected static function snippet_has_code( $value ) {
		return is_string( $value ) && '' !== trim( $value );
	}

	/**
	 * CSS / JS / PHP usage flags for one variant.
	 *
	 * @param array $variant Variant row.
	 * @return array
	 */
	protected static function variant_code_flags( $variant ) {
		if ( ! is_array( $variant ) ) {
			$variant = array();
		}
		return array(
			'css' => self::snippet_has_code( isset( $variant['css'] ) ? $variant['css'] : '' ),
			'js'  => self::snippet_has_code( isset( $variant['js'] ) ? $variant['js'] : '' ),
			'php' => self::snippet_has_code( isset( $variant['php'] ) ? $variant['php'] : '' ),
		);
	}

	/**
	 * CSS / JS / PHP usage flags for an experiment (any variant).
	 *
	 * @param array $experiment Experiment with variants.
	 * @return array
	 */
	protected static function experiment_code_flags( $experiment ) {
		$flags = array(
			'css' => false,
			'js'  => false,
			'php' => false,
		);
		$variants = isset( $experiment['variants'] ) && is_array( $experiment['variants'] ) ? $experiment['variants'] : array();
		foreach ( $variants as $variant ) {
			$variant_flags = self::variant_code_flags( $variant );
			$flags['css']  = $flags['css'] || $variant_flags['css'];
			$flags['js']   = $flags['js'] || $variant_flags['js'];
			$flags['php']  = $flags['php'] || $variant_flags['php'];
		}
		return $flags;
	}

	/**
	 * Print CSS / JS / PHP badges for active snippet types.
	 *
	 * @param array $flags Keys css, js, php.
	 * @return void
	 */
	protected static function render_code_badges( $flags ) {
		$labels = array(
			'css' => 'CSS',
			'js'  => 'JS',
			'php' => 'PHP',
		);
		foreach ( $labels as $key => $label ) {
			if ( empty( $flags[ $key ] ) ) {
				continue;
			}
			printf(
				'<span class="wpd-exp-code-badge wpd-exp-code-badge--%s">%s</span>',
				esc_attr( $key ),
				esc_html( $label )
			);
		}
	}

	/**
	 * One experiment as its own table: header plus per-arm stats.
	 *
	 * @param array  $experiment        Experiment.
	 * @param array  $summary           Summary.
	 * @param string $results_url       Results report URL.
	 * @param bool   $free_run_blocked  Whether Free already has a running experiment.
	 * @return void
	 */
	protected static function render_list_experiment( $experiment, $summary, $results_url, $free_run_blocked = false ) {
		$eid    = isset( $experiment['id'] ) ? (int) $experiment['id'] : 0;
		$status = isset( $experiment['status'] ) ? $experiment['status'] : 'draft';
		$arms   = isset( $summary['arms'] ) && is_array( $summary['arms'] ) ? $summary['arms'] : array();
		$code_flags = self::experiment_code_flags( $experiment );
		$variant_code = array();
		$variants     = isset( $experiment['variants'] ) && is_array( $experiment['variants'] ) ? $experiment['variants'] : array();
		foreach ( $variants as $variant ) {
			$vkey = isset( $variant['variant_key'] ) ? $variant['variant_key'] : '';
			if ( '' !== $vkey ) {
				$variant_code[ $vkey ] = self::variant_code_flags( $variant );
			}
		}
		$status_url = function( $action ) use ( $eid ) {
			return wp_nonce_url(
				admin_url( 'admin.php?page=' . WPDAI_Admin_Menu::$experiments_slug . '&wpd-action=' . $action . '&experiment_id=' . $eid ),
				'wpd_ai_experiment_status_' . $eid
			);
		};
		?>
		<table class="wpd-table widefat wpd-exp-list-table">
			<thead>
				<tr class="wpd-exp-list-heading">
					<th colspan="7">
						<div class="wpd-exp-list-heading-inner">
							<div class="wpd-exp-list-identity">
								<a href="<?php echo esc_url( self::edit_url( $eid ) ); ?>">
									<strong><?php echo esc_html( $experiment['name'] ); ?></strong>
								</a>
								<div class="wpd-meta"><?php echo esc_html( $experiment['slug'] ); ?></div>
							</div>
							<div class="wpd-exp-list-meta">
								<span class="wpd-exp-status wpd-exp-status--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span>
								<span class="wpd-exp-traffic"><?php echo esc_html( (int) $experiment['traffic_percent'] ); ?>%</span>
								<span class="wpd-exp-code-badges"><?php self::render_code_badges( $code_flags ); ?></span>
							</div>
							<div class="wpd-exp-row-actions">
								<a class="button button-small" href="<?php echo esc_url( self::edit_url( $eid ) ); ?>"><?php esc_html_e( 'Edit', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a>
								<a class="button button-small" href="<?php echo esc_url( $results_url ); ?>"><?php esc_html_e( 'Results', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a>
								<?php if ( 'running' !== $status ) : ?>
									<?php if ( $free_run_blocked ) : ?>
										<span class="button button-small disabled" title="<?php echo esc_attr( self::free_running_limit_message() ); ?>"><?php esc_html_e( 'Start', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></span>
									<?php else : ?>
										<a class="button button-small" href="<?php echo esc_url( $status_url( 'run' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Start this test? Targeted URLs will miss page cache until you pause or complete it.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');"><?php esc_html_e( 'Start', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a>
									<?php endif; ?>
								<?php endif; ?>
								<?php if ( 'running' === $status ) : ?>
									<a class="button button-small" href="<?php echo esc_url( $status_url( 'pause' ) ); ?>"><?php esc_html_e( 'Pause', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a>
								<?php endif; ?>
								<?php if ( 'completed' !== $status ) : ?>
									<a class="button button-small" href="<?php echo esc_url( $status_url( 'complete' ) ); ?>"><?php esc_html_e( 'Complete', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a>
								<?php endif; ?>
								<a class="button button-small" href="<?php echo esc_url( $status_url( 'delete' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this experiment and its assignment history?', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');"><?php esc_html_e( 'Delete', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a>
							</div>
						</div>
					</th>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Variant', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Exposures', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Add to carts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Initiate checkouts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Conversions', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Conversion rate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Lift vs control', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $arms ) ) : ?>
					<tr>
						<td colspan="7"><?php esc_html_e( 'No variants configured.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
					</tr>
				<?php endif; ?>
				<?php foreach ( $arms as $vkey => $arm ) : ?>
					<?php $is_winner = ( isset( $summary['winner_key'] ) && $vkey === $summary['winner_key'] && ! empty( $summary['has_data'] ) ); ?>
					<tr<?php echo $is_winner ? ' class="is-winner"' : ''; ?>>
						<td>
							<?php echo esc_html( $arm['name'] ); ?>
							<?php if ( ! empty( $arm['is_control'] ) ) : ?>
								<span class="wpd-meta"><?php esc_html_e( 'Control', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></span>
							<?php endif; ?>
							<?php if ( $is_winner ) : ?>
								<span class="wpd-exp-arm-leading"><?php esc_html_e( 'Leading', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></span>
							<?php endif; ?>
							<?php
							$arm_flags = isset( $variant_code[ $vkey ] ) ? $variant_code[ $vkey ] : array();
							if ( ! empty( $arm_flags['css'] ) || ! empty( $arm_flags['js'] ) || ! empty( $arm_flags['php'] ) ) :
								?>
								<span class="wpd-exp-code-badges wpd-exp-code-badges--arm"><?php self::render_code_badges( $arm_flags ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( number_format_i18n( (int) $arm['exposures'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( isset( $arm['add_to_carts'] ) ? (int) $arm['add_to_carts'] : 0 ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( isset( $arm['initiate_checkouts'] ) ? (int) $arm['initiate_checkouts'] : 0 ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $arm['conversions'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (float) $arm['rate'], 1 ) ); ?>%</td>
						<td>
							<?php
							if ( null !== $arm['lift'] ) {
								echo esc_html( ( $arm['lift'] > 0 ? '+' : '' ) . number_format_i18n( (float) $arm['lift'], 1 ) . '%' );
							} else {
								echo '&mdash;';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Edit screen.
	 *
	 * @return void
	 */
	protected static function render_edit() {
		$id         = isset( $_GET['experiment_id'] ) ? absint( $_GET['experiment_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$experiment = $id ? WPDAI_Experiment_Store::get( $id ) : self::blank_experiment();
		if ( $id && ! $experiment ) {
			self::queue_notice( 'not_found', __( 'That experiment was not found.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'error' );
			self::render_list();
			return;
		}
		$is_pro     = wpdai_experiments_is_pro();
		$types      = wpdai_get_experiment_page_target_types();
		$free_types = wpdai_get_experiment_free_page_target_types();

		$pages    = isset( $experiment['targeting']['pages'] ) && is_array( $experiment['targeting']['pages'] ) ? $experiment['targeting']['pages'] : array();
		$audience = isset( $experiment['targeting']['audience'] ) && is_array( $experiment['targeting']['audience'] ) ? wp_parse_args( $experiment['targeting']['audience'], wpdai_get_experiment_default_targeting()['audience'] ) : wpdai_get_experiment_default_targeting()['audience'];
		$goals    = isset( $experiment['goals'] ) && is_array( $experiment['goals'] ) ? wp_parse_args( $experiment['goals'], wpdai_get_experiment_default_goals() ) : wpdai_get_experiment_default_goals();
		$goals['primary'] = wp_parse_args(
			isset( $goals['primary'] ) && is_array( $goals['primary'] ) ? $goals['primary'] : array(),
			wpdai_get_experiment_default_goals()['primary']
		);
		$variants = ! empty( $experiment['variants'] ) ? $experiment['variants'] : array(
			array(
				'id'          => 0,
				'variant_key' => 'control',
				'name'        => __( 'Control', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'weight'      => 50,
				'is_control'  => 1,
				'css'         => '',
				'js'          => '',
				'php'         => '',
			),
			array(
				'id'          => 0,
				'variant_key' => 'treatment',
				'name'        => __( 'Treatment', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'weight'      => 50,
				'is_control'  => 0,
				'css'         => '',
				'js'          => '',
				'php'         => '',
			),
		);

		if ( empty( $pages ) ) {
			$pages = array(
				array(
					'type'  => 'path_contains',
					'value' => '',
				),
			);
		}

		$preview_base = home_url( '/' );
		foreach ( wpdai_experiment_effective_page_rules( $experiment['targeting'] ) as $rule ) {
			$type  = isset( $rule['type'] ) ? $rule['type'] : '';
			$value = isset( $rule['value'] ) ? (string) $rule['value'] : '';
			if ( in_array( $type, array( 'path_equals', 'path_starts_with', 'path_contains' ), true ) && '' !== $value ) {
				$preview_base = home_url( '/' === substr( $value, 0, 1 ) ? $value : '/' . $value );
				break;
			}
		}
		?>
		<div class="wrap">
			<?php do_action( 'wpd_before_heading' ); ?>
			<h3><?php echo $id ? esc_html__( 'Edit Experiment', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) : esc_html__( 'Add Experiment', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></h3>
			<?php do_action( 'wpd_before_content' ); ?>
			<?php self::print_notices(); ?>
			<div class="wpd-white-block">
				<form method="post" action="" id="wpd-exp-experiment-form">
					<?php wp_nonce_field( 'wpd_ai_experiment_save', 'wpd_ai_experiment_nonce' ); ?>
					<input type="hidden" name="wpd_ai_experiment_save" value="1">
					<input type="hidden" name="experiment[id]" value="<?php echo esc_attr( (string) $id ); ?>">

					<div class="wpd-notice notice notice-info">
						<p><?php esc_html_e( 'While this test is running, targeted URLs miss full-page cache so the correct variant can render on first paint. Other URLs stay cached. Pause or complete the test to restore cache on those URLs.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></p>
					</div>
					<?php if ( wpdai_experiment_targeting_is_broad( $experiment['targeting'] ) ) : ?>
						<div class="wpd-notice notice notice-warning">
							<p><?php esc_html_e( 'This targeting is broad (entire site, all products, or a post type). Starting the test will bypass page cache for a large part of the store until you pause or complete it.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></p>
						</div>
					<?php endif; ?>
					<?php if ( ! $is_pro && WPDAI_Experiment_Store::count_running( $id ) >= 1 && 'running' !== $experiment['status'] ) : ?>
						<div class="wpd-notice notice notice-warning">
							<p>
								<?php echo esc_html( self::free_running_limit_message() ); ?>
								<a href="#" class="wpd-trigger-upgrade-modal"><?php esc_html_e( 'Upgrade to Pro', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a>
							</p>
						</div>
					<?php endif; ?>

					<p class="wpd-exp-save-actions">
						<?php submit_button( __( 'Save Experiment', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'primary', 'submit', false ); ?>
					</p>
					<table class="wpd-table widefat">
						<tbody>
							<tr>
								<td>
									<label for="wpd-exp-name"><?php esc_html_e( 'Name', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
								</td>
								<td>
									<input id="wpd-exp-name" class="wpd-input regular-text" type="text" name="experiment[name]" value="<?php echo esc_attr( $experiment['name'] ); ?>" required>
								</td>
							</tr>
							<tr>
								<td>
									<label for="wpd-exp-slug"><?php esc_html_e( 'Slug', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
									<div class="wpd-meta"><?php esc_html_e( 'Used in preview links and wpdai_in_experiment().', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
								</td>
								<td>
									<?php
									$slug_value  = isset( $experiment['slug'] ) ? (string) $experiment['slug'] : '';
									$slug_autogen = '' === $slug_value ? '1' : '0';
									?>
									<div id="wpd-exp-slug-box" data-autogen="<?php echo esc_attr( $slug_autogen ); ?>">
										<span id="wpd-exp-slug-view">
											<code id="wpd-exp-slug-display"><?php echo $slug_value ? esc_html( $slug_value ) : '&mdash;'; ?></code>
											<button type="button" class="button button-small" id="wpd-exp-slug-edit"><?php esc_html_e( 'Edit', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></button>
										</span>
										<span id="wpd-exp-slug-editor" hidden>
											<input id="wpd-exp-slug" class="wpd-input regular-text" type="text" name="experiment[slug]" value="<?php echo esc_attr( $slug_value ); ?>" autocomplete="off">
											<button type="button" class="button button-small" id="wpd-exp-slug-ok"><?php esc_html_e( 'OK', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></button>
											<button type="button" class="button-link" id="wpd-exp-slug-cancel"><?php esc_html_e( 'Cancel', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></button>
										</span>
									</div>
								</td>
							</tr>
							<tr>
								<td>
									<label><?php esc_html_e( 'Hypothesis', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
								</td>
								<td>
									<textarea class="wpd-input large-text" name="experiment[hypothesis]" rows="3"><?php echo esc_textarea( $experiment['hypothesis'] ); ?></textarea>
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Primary goal', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
								<td>
									<?php $goal_type = isset( $goals['primary']['type'] ) ? (string) $goals['primary']['type'] : 'transaction'; ?>
									<select id="wpd-exp-primary-goal-type" name="experiment[goals][primary][type]" <?php disabled( ! $is_pro ); ?>>
										<option value="transaction" <?php selected( $goal_type, 'transaction' ); ?>><?php esc_html_e( 'Purchase', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
										<option value="add_to_cart" <?php selected( $goal_type, 'add_to_cart' ); ?>><?php esc_html_e( 'Add to cart', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
										<option value="page_view" <?php selected( $goal_type, 'page_view' ); ?>><?php esc_html_e( 'Page view', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
										<option value="event" <?php selected( $goal_type, 'event' ); ?>><?php esc_html_e( 'Custom event', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
									</select>
									<?php if ( ! $is_pro ) : ?>
										<p class="wpd-meta"><a href="#" class="wpd-trigger-upgrade-modal"><?php esc_html_e( 'Free tests use Purchase as the primary goal.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a></p>
									<?php endif; ?>
								</td>
							</tr>
							<tr class="wpd-exp-goal-extra<?php echo 'event' === $goal_type ? '' : ' hidden'; ?>" data-goal-types="event">
								<td><?php esc_html_e( 'Custom event type', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
								<td>
									<input class="wpd-input regular-text" type="text" name="experiment[goals][primary][event_type]" value="<?php echo esc_attr( $goals['primary']['event_type'] ?? '' ); ?>" placeholder="form_submit" <?php disabled( ! $is_pro ); ?>>
									<p class="wpd-meta"><?php esc_html_e( 'The event name recorded by tracking, e.g. form_submit.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></p>
								</td>
							</tr>
							<tr class="wpd-exp-goal-extra<?php echo 'page_view' === $goal_type ? '' : ' hidden'; ?>" data-goal-types="page_view">
								<td><?php esc_html_e( 'Page path', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
								<td>
									<input class="wpd-input regular-text" type="text" name="experiment[goals][primary][path]" value="<?php echo esc_attr( $goals['primary']['path'] ?? '' ); ?>" placeholder="/thank-you/" <?php disabled( ! $is_pro ); ?>>
									<p class="wpd-meta"><?php esc_html_e( 'Count a conversion when this path is viewed, e.g. /thank-you/.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></p>
								</td>
							</tr>
							<tr>
								<td>
									<label><?php esc_html_e( 'Status', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
								</td>
								<td>
									<select name="experiment[status]">
										<?php foreach ( wpdai_get_experiment_statuses() as $status ) : ?>
											<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $experiment['status'], $status ); ?>><?php echo esc_html( ucfirst( $status ) ); ?></option>
										<?php endforeach; ?>
									</select>
									<?php if ( ! $is_pro ) : ?>
										<p class="wpd-meta"><?php echo esc_html( self::free_running_limit_message() ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<td>
									<label><?php esc_html_e( 'Traffic in test', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
									<div class="wpd-meta"><?php esc_html_e( 'Percent of eligible visitors assigned to an arm. The rest are holdout.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
								</td>
								<td>
									<input class="wpd-input" type="number" min="1" max="100" name="experiment[traffic_percent]" value="<?php echo esc_attr( (string) $experiment['traffic_percent'] ); ?>" <?php disabled( ! $is_pro ); ?>>
									<?php if ( ! $is_pro ) : ?>
										<p class="wpd-meta"><?php esc_html_e( 'Holdout traffic allocation is a Pro feature. Free tests include 100% of eligible visitors.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
										<a href="#" class="wpd-trigger-upgrade-modal"><?php esc_html_e( 'Upgrade to Pro', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a></p>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<td>
									<label><?php esc_html_e( 'Schedule', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
								</td>
								<td>
									<input type="datetime-local" name="experiment[start_gmt]" value="<?php echo esc_attr( self::datetime_local( $experiment['start_gmt'] ) ); ?>" <?php disabled( ! $is_pro ); ?>>
									—
									<input type="datetime-local" name="experiment[end_gmt]" value="<?php echo esc_attr( self::datetime_local( $experiment['end_gmt'] ) ); ?>" <?php disabled( ! $is_pro ); ?>>
									<?php if ( ! $is_pro ) : ?>
										<p class="wpd-meta"><a href="#" class="wpd-trigger-upgrade-modal"><?php esc_html_e( 'Start/end dates are available in Pro.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a></p>
									<?php endif; ?>
								</td>
							</tr>
						</tbody>
					</table>

					<h4><?php esc_html_e( 'Filters', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></h4>
					<p class="wpd-meta"><?php esc_html_e( 'Only matching visitors enter the test. Leave a filter empty to include everyone for that rule. Empty URL values are ignored; if every URL rule is empty the test includes the entire site and those pages miss cache until you pause it.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></p>
					<?php if ( ! $is_pro ) : ?>
						<p class="wpd-meta"><a href="#" class="wpd-trigger-upgrade-modal"><?php esc_html_e( 'Audience filters (traffic source, device, logged in, visitor type) are Pro features. Free tests include all eligible page traffic minus analytics excluded roles.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a></p>
					<?php endif; ?>
					<table class="wpd-table widefat">
						<tbody>
							<tr>
								<td>
									<label><?php esc_html_e( 'Pages', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
									<div class="wpd-meta"><?php esc_html_e( 'Cart, checkout, and product matches do not need a value.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
								</td>
								<td>
									<table class="wpd-table widefat" id="wpd-exp-page-rules" style="margin:0;">
										<thead>
											<tr>
												<th><?php esc_html_e( 'Match', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
												<th><?php esc_html_e( 'Value', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
												<th></th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ( $pages as $i => $rule ) : ?>
												<tr>
													<td>
														<select name="experiment[targeting][pages][<?php echo esc_attr( (string) $i ); ?>][type]">
															<?php foreach ( $types as $type => $label ) : ?>
																<?php $locked = ! $is_pro && ! in_array( $type, $free_types, true ); ?>
																<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $rule['type'] ?? '', $type ); ?> <?php disabled( $locked ); ?>><?php echo esc_html( $label ); ?><?php echo $locked ? ' (' . esc_html__( 'Pro', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) . ')' : ''; ?></option>
															<?php endforeach; ?>
														</select>
													</td>
													<td><input class="wpd-input regular-text" type="text" name="experiment[targeting][pages][<?php echo esc_attr( (string) $i ); ?>][value]" value="<?php echo esc_attr( $rule['value'] ?? '' ); ?>" placeholder="/pricing/"></td>
													<td><button type="button" class="button wpd-exp-remove-row"><?php esc_html_e( 'Remove', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></button></td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
									<p style="margin:8px 0 0;"><button type="button" class="button" id="wpd-exp-add-page-rule"><?php esc_html_e( 'Add URL rule', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></button></p>
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Traffic sources', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
								<td>
									<?php
									self::render_audience_multiselect(
										'experiment[targeting][audience][traffic_sources][]',
										wpdai_get_experiment_traffic_sources(),
										isset( $audience['traffic_sources'] ) ? $audience['traffic_sources'] : array(),
										__( 'Select traffic sources', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
										__( 'All traffic sources', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
										$is_pro
									);
									?>
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Devices', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
								<td>
									<?php
									self::render_audience_multiselect(
										'experiment[targeting][audience][devices][]',
										wpdai_get_experiment_devices(),
										isset( $audience['devices'] ) ? $audience['devices'] : array(),
										__( 'Select devices', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
										__( 'All devices', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
										$is_pro
									);
									?>
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Logged in', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
								<td>
									<select name="experiment[targeting][audience][logged_in]" <?php disabled( ! $is_pro ); ?>>
										<option value="any" <?php selected( $audience['logged_in'], 'any' ); ?>><?php esc_html_e( 'Any', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
										<option value="yes" <?php selected( $audience['logged_in'], 'yes' ); ?>><?php esc_html_e( 'Logged in', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
										<option value="no" <?php selected( $audience['logged_in'], 'no' ); ?>><?php esc_html_e( 'Guests', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Visitor type', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td>
								<td>
									<select name="experiment[targeting][audience][visitor_type]" <?php disabled( ! $is_pro ); ?>>
										<option value="any" <?php selected( $audience['visitor_type'], 'any' ); ?>><?php esc_html_e( 'Any', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
										<option value="new" <?php selected( $audience['visitor_type'], 'new' ); ?>><?php esc_html_e( 'New', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
										<option value="returning" <?php selected( $audience['visitor_type'], 'returning' ); ?>><?php esc_html_e( 'Returning', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
									</select>
								</td>
							</tr>
						</tbody>
					</table>

					<h4><?php esc_html_e( 'Variants', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></h4>
					<div id="wpd-exp-variants">
						<?php foreach ( $variants as $index => $variant ) : ?>
							<?php self::render_variant_fields( $index, $variant, $is_pro ); ?>
						<?php endforeach; ?>
					</div>
					<p>
						<button type="button" class="button" id="wpd-exp-add-variant" <?php disabled( ! $is_pro ); ?>><?php esc_html_e( 'Add variant', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></button>
						<?php if ( ! $is_pro ) : ?>
							<a href="#" class="wpd-trigger-upgrade-modal"><?php esc_html_e( 'A/B/n tests are a Pro feature.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a>
						<?php endif; ?>
					</p>

					<?php if ( $id && ! empty( $experiment['slug'] ) ) : ?>
						<p class="wpd-meta">
							<?php esc_html_e( 'Preview as an authorized user (does not record experiment events):', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
						</p>
						<ul class="wpd-meta">
							<?php foreach ( $variants as $variant ) : ?>
								<?php
								$preview_url = add_query_arg(
									array(
										'wpd_ai_preview_exp' => $experiment['slug'],
										'wpd_ai_preview_var' => $variant['variant_key'] ?? 'control',
									),
									$preview_base
								);
								?>
								<li>
									<a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener noreferrer">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: variant name */
												__( 'Preview %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
												$variant['name'] ?? ( $variant['variant_key'] ?? '' )
											)
										);
										?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<p class="wpd-exp-save-actions wpd-exp-save-actions--bottom">
						<span class="wpd-exp-save-secondary">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . WPDAI_Admin_Menu::$experiments_slug ) ); ?>" class="button"><?php esc_html_e( 'Back to list', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a>
							<a href="<?php echo esc_url( self::results_url() ); ?>" class="button"><?php esc_html_e( 'View results', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a>
						</span>
						<?php submit_button( __( 'Save Experiment', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'primary', 'submit', false ); ?>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Variant editor block.
	 *
	 * @param int   $index   Index.
	 * @param array $variant Variant.
	 * @param bool  $is_pro  Pro flag.
	 * @return void
	 */
	protected static function render_variant_fields( $index, $variant, $is_pro ) {
		$index_attr = esc_attr( (string) $index );
		$has_css    = '' !== trim( (string) ( $variant['css'] ?? '' ) );
		$has_js     = '' !== trim( (string) ( $variant['js'] ?? '' ) );
		$has_php    = '' !== trim( (string) ( $variant['php'] ?? '' ) );
		$active_tab = 'css';
		if ( $has_js && ! $has_css ) {
			$active_tab = 'js';
		} elseif ( $has_php && ! $has_css && ! $has_js ) {
			$active_tab = 'php';
		}
		?>
		<div class="wpd-exp-variant" data-index="<?php echo $index_attr; ?>">
			<input type="hidden" name="experiment[variants][<?php echo $index_attr; ?>][id]" value="<?php echo esc_attr( (string) ( $variant['id'] ?? 0 ) ); ?>">
			<div class="wpd-exp-variant-meta">
				<label><?php esc_html_e( 'Name', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
					<input class="wpd-input" type="text" name="experiment[variants][<?php echo $index_attr; ?>][name]" value="<?php echo esc_attr( $variant['name'] ?? '' ); ?>">
				</label>
				<label><?php esc_html_e( 'Key', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
					<input class="wpd-input" type="text" name="experiment[variants][<?php echo $index_attr; ?>][variant_key]" value="<?php echo esc_attr( $variant['variant_key'] ?? '' ); ?>">
				</label>
				<label><?php esc_html_e( 'Weight', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
					<input class="wpd-input wpd-exp-variant-weight" type="number" min="0" max="100" name="experiment[variants][<?php echo $index_attr; ?>][weight]" value="<?php echo esc_attr( (string) ( $variant['weight'] ?? 50 ) ); ?>">
				</label>
				<label class="wpd-exp-variant-control">
					<input class="wpd-exp-is-control" type="checkbox" name="experiment[variants][<?php echo $index_attr; ?>][is_control]" value="1" <?php checked( ! empty( $variant['is_control'] ) ); ?>>
					<?php esc_html_e( 'Control', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
				</label>
			</div>
			<div class="wpd-exp-variant-code">
				<div class="wpd-exp-code-tabs" role="tablist">
					<button type="button" class="wpd-exp-code-tab<?php echo 'css' === $active_tab ? ' is-active' : ''; ?><?php echo $has_css ? ' has-code' : ''; ?>" data-code-tab="css" role="tab" aria-selected="<?php echo 'css' === $active_tab ? 'true' : 'false'; ?>"><?php esc_html_e( 'CSS', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></button>
					<button type="button" class="wpd-exp-code-tab<?php echo 'js' === $active_tab ? ' is-active' : ''; ?><?php echo $has_js ? ' has-code' : ''; ?>" data-code-tab="js" role="tab" aria-selected="<?php echo 'js' === $active_tab ? 'true' : 'false'; ?>"><?php esc_html_e( 'JavaScript', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></button>
					<button type="button" class="wpd-exp-code-tab<?php echo 'php' === $active_tab ? ' is-active' : ''; ?><?php echo $has_php ? ' has-code' : ''; ?>" data-code-tab="php" role="tab" aria-selected="<?php echo 'php' === $active_tab ? 'true' : 'false'; ?>"><?php esc_html_e( 'PHP snippet', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></button>
				</div>
				<div class="wpd-exp-code-panel<?php echo 'css' === $active_tab ? '' : ' hidden'; ?>" data-code-panel="css" role="tabpanel">
					<textarea class="wpd-exp-code wpd-exp-css large-text" rows="8" name="experiment[variants][<?php echo $index_attr; ?>][css]"><?php echo esc_textarea( $variant['css'] ?? '' ); ?></textarea>
				</div>
				<div class="wpd-exp-code-panel<?php echo 'js' === $active_tab ? '' : ' hidden'; ?>" data-code-panel="js" role="tabpanel">
					<textarea class="wpd-exp-code wpd-exp-js large-text" rows="8" name="experiment[variants][<?php echo $index_attr; ?>][js]"><?php echo esc_textarea( $variant['js'] ?? '' ); ?></textarea>
				</div>
				<div class="wpd-exp-code-panel<?php echo 'php' === $active_tab ? '' : ' hidden'; ?>" data-code-panel="php" role="tabpanel">
					<textarea class="wpd-exp-code wpd-exp-php large-text" rows="8" name="experiment[variants][<?php echo $index_attr; ?>][php]" <?php disabled( ! $is_pro ); ?>><?php echo esc_textarea( $variant['php'] ?? '' ); ?></textarea>
					<?php if ( ! $is_pro ) : ?>
						<p class="wpd-meta"><a href="#" class="wpd-trigger-upgrade-modal"><?php esc_html_e( 'PHP snippets are a Pro feature. Theme developers can still use wpdai_in_experiment() in both versions.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a></p>
					<?php else : ?>
						<p class="wpd-meta"><?php esc_html_e( 'Runs after WordPress has parsed the request (the wp hook), so template tags like is_product() work. Syntax is checked on save; runtime errors are logged and skipped.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Blank experiment for the add screen.
	 *
	 * @return array
	 */
	protected static function blank_experiment() {
		return array(
			'id'              => 0,
			'name'            => '',
			'slug'            => '',
			'status'          => 'draft',
			'hypothesis'      => '',
			'traffic_percent' => 100,
			'start_gmt'       => '',
			'end_gmt'         => '',
			'targeting'       => wpdai_get_experiment_default_targeting(),
			'goals'           => wpdai_get_experiment_default_goals(),
			'settings'        => wpdai_get_experiment_default_settings(),
			'variants'        => array(),
		);
	}

	/**
	 * Convert GMT mysql datetime to datetime-local value.
	 *
	 * @param string $gmt GMT datetime.
	 * @return string
	 */
	protected static function datetime_local( $gmt ) {
		if ( empty( $gmt ) || '0000-00-00 00:00:00' === $gmt ) {
			return '';
		}
		$local = get_date_from_gmt( $gmt, 'Y-m-d H:i:s' );
		if ( ! $local ) {
			return '';
		}
		return str_replace( ' ', 'T', substr( $local, 0, 16 ) );
	}

	/**
	 * Persist the editor form with Free limits applied.
	 *
	 * @return int|WP_Error
	 */
	protected static function save_from_request() {
		$raw = isset( $_POST['experiment'] ) && is_array( $_POST['experiment'] ) ? wp_unslash( $_POST['experiment'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$pages = array();
		if ( isset( $raw['targeting']['pages'] ) && is_array( $raw['targeting']['pages'] ) ) {
			foreach ( $raw['targeting']['pages'] as $rule ) {
				if ( ! is_array( $rule ) ) {
					continue;
				}
				$pages[] = array(
					'type'  => sanitize_key( $rule['type'] ?? 'path_contains' ),
					'value' => sanitize_text_field( $rule['value'] ?? '' ),
				);
			}
		}

		$audience = wpdai_get_experiment_default_targeting()['audience'];
		if ( isset( $raw['targeting']['audience'] ) && is_array( $raw['targeting']['audience'] ) ) {
			$audience['traffic_sources'] = wpdai_experiment_normalize_filter_values(
				isset( $raw['targeting']['audience']['traffic_sources'] ) ? (array) $raw['targeting']['audience']['traffic_sources'] : array(),
				wpdai_get_experiment_traffic_sources()
			);
			$audience['devices'] = wpdai_experiment_normalize_filter_values(
				isset( $raw['targeting']['audience']['devices'] ) ? (array) $raw['targeting']['audience']['devices'] : array(),
				wpdai_get_experiment_devices()
			);
			$audience['logged_in']       = sanitize_key( $raw['targeting']['audience']['logged_in'] ?? 'any' );
			$audience['visitor_type']    = sanitize_key( $raw['targeting']['audience']['visitor_type'] ?? 'any' );
		}

		$variants = array();
		if ( isset( $raw['variants'] ) && is_array( $raw['variants'] ) ) {
			foreach ( $raw['variants'] as $variant ) {
				if ( ! is_array( $variant ) ) {
					continue;
				}
				$variants[] = array(
					'id'          => absint( $variant['id'] ?? 0 ),
					'variant_key' => sanitize_key( $variant['variant_key'] ?? '' ),
					'name'        => sanitize_text_field( $variant['name'] ?? '' ),
					'weight'      => absint( $variant['weight'] ?? 50 ),
					'is_control'  => ! empty( $variant['is_control'] ),
					'css'         => (string) ( $variant['css'] ?? '' ),
					'js'          => (string) ( $variant['js'] ?? '' ),
					'php'         => (string) ( $variant['php'] ?? '' ),
				);
			}
		}

		$data = array(
			'id'              => absint( $raw['id'] ?? 0 ),
			'name'            => sanitize_text_field( $raw['name'] ?? '' ),
			'slug'            => sanitize_title( $raw['slug'] ?? '' ),
			'status'          => sanitize_key( $raw['status'] ?? 'draft' ),
			'hypothesis'      => sanitize_textarea_field( $raw['hypothesis'] ?? '' ),
			'traffic_percent' => absint( $raw['traffic_percent'] ?? 100 ),
			'start_gmt'       => self::datetime_local_to_gmt( sanitize_text_field( $raw['start_gmt'] ?? '' ) ),
			'end_gmt'         => self::datetime_local_to_gmt( sanitize_text_field( $raw['end_gmt'] ?? '' ) ),
			'targeting'       => array(
				'pages'    => $pages,
				'audience' => $audience,
			),
			'goals'           => array(
				'primary'   => array(
					'type'       => sanitize_key( $raw['goals']['primary']['type'] ?? 'transaction' ),
					'event_type' => sanitize_text_field( $raw['goals']['primary']['event_type'] ?? '' ),
					'path'       => sanitize_text_field( $raw['goals']['primary']['path'] ?? '' ),
				),
				'secondary' => array(),
			),
			'settings'        => array(
				'mde_percent' => 5,
			),
			'variants'        => $variants,
		);

		$data = self::apply_free_limits( $data );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( 'running' === $data['status'] ) {
			if ( ! function_exists( 'wpdai_is_analytics_enabled' ) || ! wpdai_is_analytics_enabled() ) {
				return new WP_Error( 'analytics_disabled', __( 'Turn on website traffic tracking in General Settings before starting a test.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) );
			}
		}

		if ( wpdai_experiments_is_pro() ) {
			foreach ( $data['variants'] as $variant ) {
				$syntax = wpdai_experiment_php_syntax_error( $variant['php'] ?? '' );
				if ( $syntax ) {
					return new WP_Error(
						'php_syntax',
						sprintf(
							/* translators: 1: variant name, 2: parse error */
							__( 'PHP snippet in “%1$s” has a syntax error: %2$s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
							$variant['name'] ? $variant['name'] : $variant['variant_key'],
							$syntax
						)
					);
				}
			}
		}

		return WPDAI_Experiment_Store::save( $data );
	}

	/**
	 * Enforce Free feature limits on save and status changes.
	 *
	 * @param array $data Experiment data.
	 * @return array|WP_Error
	 */
	public static function apply_free_limits( $data ) {
		if ( wpdai_experiments_is_pro() ) {
			return $data;
		}

		$data['traffic_percent'] = 100;
		$data['start_gmt']       = '';
		$data['end_gmt']         = '';
		$data['goals']['primary'] = array(
			'type'       => 'transaction',
			'event_type' => '',
			'path'       => '',
		);
		$data['targeting']['audience'] = wpdai_get_experiment_default_targeting()['audience'];
		if ( ! isset( $data['variants'] ) || ! is_array( $data['variants'] ) ) {
			$data['variants'] = array();
		}

		$allowed_types = wpdai_get_experiment_free_page_target_types();
		if ( ! empty( $data['targeting']['pages'] ) ) {
			foreach ( $data['targeting']['pages'] as $i => $rule ) {
				if ( ! in_array( $rule['type'], $allowed_types, true ) ) {
					$data['targeting']['pages'][ $i ]['type'] = 'path_contains';
				}
			}
		}

		if ( count( $data['variants'] ) > 2 ) {
			$data['variants'] = array_slice( $data['variants'], 0, 2 );
		}
		foreach ( $data['variants'] as $i => $variant ) {
			$data['variants'][ $i ]['php'] = '';
		}

		if ( 'running' === $data['status'] && WPDAI_Experiment_Store::count_running( absint( $data['id'] ?? 0 ) ) >= 1 ) {
			return new WP_Error( 'free_limit', self::free_running_limit_message() );
		}

		return $data;
	}

	/**
	 * Change status with Free running-experiment limit.
	 *
	 * @param int    $id     Experiment ID.
	 * @param string $status Status.
	 * @return int|WP_Error
	 */
	protected static function change_status( $id, $status ) {
		$experiment = WPDAI_Experiment_Store::get( $id );
		if ( ! $experiment ) {
			return new WP_Error( 'not_found', __( 'Experiment not found.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) );
		}

		if ( 'running' === $status ) {
			if ( ! function_exists( 'wpdai_is_analytics_enabled' ) || ! wpdai_is_analytics_enabled() ) {
				return new WP_Error( 'analytics_disabled', __( 'Turn on website traffic tracking in General Settings before starting a test.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) );
			}
			if ( ! wpdai_experiments_is_pro() && WPDAI_Experiment_Store::count_running( $id ) >= 1 ) {
				return new WP_Error( 'free_limit', self::free_running_limit_message() );
			}
		}

		$experiment['status'] = $status;
		return WPDAI_Experiment_Store::save( $experiment );
	}

	/**
	 * Editor URL (list "Add New" and row Edit links).
	 *
	 * Uses `subpage=edit` so the shared submenu highlighter can tell this
	 * screen apart from All Experiments.
	 *
	 * @param int $id Optional experiment ID.
	 * @return string
	 */
	protected static function edit_url( $id = 0 ) {
		$args = array(
			'page'    => WPDAI_Admin_Menu::$experiments_slug,
			'subpage' => 'edit',
		);
		$id = absint( $id );
		if ( $id ) {
			$args['experiment_id'] = $id;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Whether the current request is the experiment editor.
	 *
	 * @return bool
	 */
	protected static function is_edit_screen() {
		$subpage = isset( $_GET['subpage'] ) ? sanitize_key( wp_unslash( $_GET['subpage'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action  = isset( $_GET['wpd-action'] ) ? sanitize_key( wp_unslash( $_GET['wpd-action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return ( 'edit' === $subpage || 'edit' === $action );
	}

	/**
	 * Results report URL.
	 *
	 * @return string
	 */
	protected static function results_url() {
		return admin_url( 'admin.php?page=' . WPDAI_Admin_Menu::$website_traffic_slug . '&subpage=experiments' );
	}

	/**
	 * Persist an admin notice across redirect.
	 *
	 * @param string $code    Notice code.
	 * @param string $message Message.
	 * @param string $type    updated|error.
	 * @return void
	 */
	protected static function queue_notice( $code, $message, $type = 'updated' ) {
		$key     = 'wpd_ai_exp_notices_' . get_current_user_id();
		$notices = get_transient( $key );
		if ( ! is_array( $notices ) ) {
			$notices = array();
		}
		$notices[] = array(
			'code'    => sanitize_key( $code ),
			'message' => (string) $message,
			'type'    => ( 'error' === $type ) ? 'error' : 'updated',
		);
		set_transient( $key, $notices, MINUTE_IN_SECONDS );
	}

	/**
	 * Print queued notices.
	 *
	 * @return string[] Notice codes that were output.
	 */
	protected static function print_notices() {
		$key     = 'wpd_ai_exp_notices_' . get_current_user_id();
		$notices = get_transient( $key );
		delete_transient( $key );
		$codes   = array();
		if ( ! is_array( $notices ) ) {
			return $codes;
		}

		foreach ( $notices as $notice ) {
			$message = isset( $notice['message'] ) ? (string) $notice['message'] : '';
			if ( '' === $message ) {
				continue;
			}
			$type = isset( $notice['type'] ) ? $notice['type'] : 'updated';
			if ( 'error' === $type ) {
				$status = 'error';
			} elseif ( 'warning' === $type ) {
				$status = 'warning';
			} else {
				$status = 'success';
			}
			wpdai_admin_notice( $message, $status );
			if ( ! empty( $notice['code'] ) ) {
				$codes[] = (string) $notice['code'];
			}
		}

		return $codes;
	}

	/**
	 * Free-plan copy for the one-running-experiment limit.
	 *
	 * @return string
	 */
	protected static function free_running_limit_message() {
		return __( 'The free version allows one running experiment. Pause or complete the current test, or upgrade to Pro.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
	}

	/**
	 * Audience multi-select, or a locked Free placeholder (easySelect ignores disabled).
	 *
	 * @param string $name        Input name.
	 * @param array  $all         Allowed values.
	 * @param array  $selected    Saved values.
	 * @param string $placeholder Combo placeholder.
	 * @param string $locked_label Free locked label.
	 * @param bool   $is_pro      Whether this is the Pro build.
	 * @return void
	 */
	protected static function render_audience_multiselect( $name, $all, $selected, $placeholder, $locked_label, $is_pro ) {
		if ( ! $is_pro ) {
			echo '<p class="wpd-exp-locked-filter">' . esc_html( $locked_label ) . '</p>';
			return;
		}

		$selected_values = wpdai_experiment_selected_filter_values( $selected, $all );
		?>
		<select class="wpd-input wpd-combo-select" name="<?php echo esc_attr( $name ); ?>" multiple="multiple" placeholder="<?php echo esc_attr( $placeholder ); ?>">
			<?php foreach ( $all as $value ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( in_array( $value, $selected_values, true ) ); ?>><?php echo esc_html( $value ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Convert a datetime-local value (site timezone) to GMT MySQL datetime.
	 *
	 * @param string $value Local datetime-local string.
	 * @return string
	 */
	protected static function datetime_local_to_gmt( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return '';
		}
		$value = str_replace( 'T', ' ', $value );
		if ( 16 === strlen( $value ) ) {
			$value .= ':00';
		}
		$gmt = get_gmt_from_date( $value );
		return is_string( $gmt ) ? $gmt : '';
	}
}
