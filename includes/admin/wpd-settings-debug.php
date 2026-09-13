<?php
/**
 *
 * Debugging Page
 *
 * @package Alpha Insights
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

$debug_tabs = array(
	'general'          => __( 'General', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
	'sessions'         => __( 'Sessions', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
	'data-management'  => __( 'Data Management', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
	'migrations'       => __( 'Migrations', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
);

$requested_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
$active_tab    = array_key_exists( $requested_tab, $debug_tabs ) ? $requested_tab : 'general';

$debug_page_url = add_query_arg(
	array(
		'page'    => WPDAI_Admin_Menu::$settings_slug,
		'subpage' => 'debug',
	),
	admin_url( 'admin.php' )
);

$order_id     = '';
$calculation  = null;
$log_files    = array();

if ( 'general' === $active_tab ) {

	wpdai_delete_large_logs();
	$log_files = wpdai_get_debug_log_data();

	if (
		isset( $_POST['wpd_ai_debug_order_id'] )
		&& ! empty( $_POST['wpd_ai_debug_order_id'] )
		&& isset( $_POST['wpd_alpha_insights_settings_nonce'] )
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpd_alpha_insights_settings_nonce'] ) ), 'wpd_alpha_insights_settings' )
	) {

		$order_id = absint( wp_unslash( $_POST['wpd_ai_debug_order_id'] ) );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( $order ) {
			$calculation               = wpdai_calculate_cost_profit_by_order( $order, true );
			$calculation['order_meta'] = $order->get_meta_data();
			wpdai_admin_notice( 'Order ID #' . $order_id . ' found. Outputting results.' );
		} else {
			wpdai_admin_notice( 'Could not find order ID #' . $order_id );
		}

	}

}

?>
<div class="wpd-wrapper">
	<div class="wpd-section-heading"><?php esc_html_e( 'Debug Settings', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
	<nav class="nav-tab-wrapper wpd-nav-tab-wrapper wpd-debug-tab-nav" aria-label="<?php esc_attr_e( 'Debug settings sections', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>">
		<?php foreach ( $debug_tabs as $tab_key => $tab_label ) : ?>
			<?php
			$tab_url   = add_query_arg( 'tab', $tab_key, $debug_page_url );
			$tab_class = 'wpd-nav-tab nav-tab';
			if ( $active_tab === $tab_key ) {
				$tab_class .= ' nav-tab-active';
			}
			?>
			<a class="<?php echo esc_attr( $tab_class ); ?>" href="<?php echo esc_url( $tab_url ); ?>"><?php echo esc_html( $tab_label ); ?></a>
		<?php endforeach; ?>
	</nav>
</div>

<?php if ( 'general' === $active_tab ) : ?>
	<div class="wpd-wrapper">
		<table class="wpd-table fixed widefat">
			<thead>
				<tr>
					<th colspan="2"><?php esc_html_e( 'Debug Order Calculations', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>
						<label for="wpd_ai_debug_order_id"><?php esc_html_e( 'Debug order ID', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
						<div class="wpd-meta"><?php esc_html_e( 'Use this tool to produce a full output of all the calculations for a specific order. This can assist with debugging.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
					</td>
					<td>
						<span style="display:inline-block">
							<input class="wpd-input" type="number" id="wpd_ai_debug_order_id" name="wpd_ai_debug_order_id" value="<?php echo esc_attr( $order_id ); ?>" step="1" placeholder="5469">
							<?php submit_button( __( 'Debug', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'primary pull-right', 'submit', false ); ?>
						</span>
					</td>
				</tr>
				<?php if ( ! empty( $calculation ) ) : ?>
					<tr>
						<td colspan="2">
							<?php wpdai_debug( $calculation, 'Order ' . $order_id . ' Data Dump' ); ?>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php if ( is_array( $log_files ) && ! empty( $log_files ) ) : ?>
		<div class="wpd-wrapper">
			<table class="wpd-table widefat wpd-debug-output">
				<thead>
					<tr>
						<th>
							<div class="wpd-debug-logs-header">
								<span class="wpd-debug-logs-title"><?php
								echo esc_html(
									sprintf(
										/* translators: %d: Number of log files */
										__( 'WP Davies Logs (%d)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
										count( $log_files )
									)
								);
								?></span>
								<button type="button" class="button button-secondary wpd-delete-all-logs">
									<?php esc_html_e( 'Delete All Logs', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
								</button>
							</div>
						</th>
					</tr>
					<tr>
						<td>
							<div class="wpd-debug-log-wrapper">
								<div class="wpd-debug-log-options">
									<?php $i = 0; ?>
									<?php foreach ( $log_files as $log ) : ?>
										<?php if ( ! is_array( $log ) ) { continue; } ?>
										<div class="wpd-debug-log-option<?php echo ( 0 === $i ) ? ' active' : ''; ?>" data-log="<?php echo esc_attr( sanitize_title( $log['title'] ) ); ?>"><span class="wpd-log-title"><?php echo esc_html( $log['title'] ); ?></span></div>
										<?php $i++; ?>
									<?php endforeach; ?>
								</div>
								<div class="wpd-debug-log-output-container">
									<?php $i = 0; ?>
									<?php foreach ( $log_files as $log ) : ?>
										<?php if ( ! is_array( $log ) ) { continue; } ?>
										<div class="wpd-debug-log-output<?php echo ( 0 === $i ) ? ' active' : ''; echo ' ' . esc_attr( sanitize_title( $log['title'] ) ); ?>"><?php wpdai_display_log( $log['file_name'], $log['title'] ); ?></div>
										<?php $i++; ?>
									<?php endforeach; ?>
								</div>
							</div>
						</td>
					</tr>
				</thead>
			</table>
		</div>
	<?php endif; ?>
<?php elseif ( 'sessions' === $active_tab ) : ?>
	<?php wpdai_render_debug_sessions_table(); ?>
<?php elseif ( 'data-management' === $active_tab ) : ?>
	<?php
	$data_manager = WPDAI_Data_Manager::get_instance();
	$data_manager->render_data_management_table();
	?>
<?php elseif ( 'migrations' === $active_tab ) : ?>
	<?php
	$migration = WPDAI_Migration::get_instance();
	$migration->render_migrations_table();
	?>
<?php endif; ?>
