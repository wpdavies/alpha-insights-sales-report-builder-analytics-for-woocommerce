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

// Delete any log files over 10mb
wpdai_delete_large_logs();

/**
 * 
 *  Get all log files
 * 
 **/
$log_files = wpdai_get_debug_log_data();

// Setup defaults
$order_id = '';

// Setup debug order ID 
if ( isset($_POST['wpd_ai_debug_order_id']) && ! empty($_POST['wpd_ai_debug_order_id']) ) {

    // Sanitize Order ID
    $order_id = abs( (int) $_POST['wpd_ai_debug_order_id'] );

    // Get order
    $order = wc_get_order( $order_id );

    // Get calculation meta
    $calculation = wpdai_calculate_cost_profit_by_order( $order, true );

    // Fetch additional meta  for debugging
    $calculation['order_meta'] = $order->get_meta_data();

    // Output notices
    ( $calculation ) ? wpdai_admin_notice( 'Order ID #' . $order_id . ' found. Outputting results.' ) : wpdai_admin_notice( 'Could not find order ID #' . $order_id );

}

?>
<div class="wpd-wrapper">
	<div class="wpd-section-heading wpd-inline">
		<?php esc_html_e( 'General Settings', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
		<?php submit_button( __('Save Changes', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), 'primary pull-right', 'submit', false); ?>
	</div>
</div>
<div class="wpd-wrapper">
	<div class="wpd-section-heading">Debugging</div>
</div>
<!-- Debug Order ID -->
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
                    <label for="wpd_ai_general_settings"><?php esc_html_e( 'Debug order ID', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></label>
                    <div class="wpd-meta"><?php esc_html_e( 'Use this tool to produce a full output of all the calculations for a specific order. This can assist with debugging.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
                </td>
                <td>
                    <span style="display:inline-block">
                        <input class="wpd-input" type="number" name="wpd_ai_debug_order_id" value="<?php echo esc_attr( $order_id ); ?>" step="1" placeholder="5469">
                        <?php submit_button( __('Debug', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), 'primary pull-right', 'submit', false); ?>
                    </span>
                </td>
            </tr>
            <?php if ( isset( $calculation ) ) : ?>
                <tr>
                    <td colspan="2">
                         <?php wpdai_debug( $calculation, 'Order ' . $order_id . ' Data Dump' ); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<!-- All Logs -->
<?php if ( is_array($log_files) && ! empty($log_files) ) : ?>
    <div class="wpd-wrapper">
        <table class="wpd-table widefat wpd-debug-output">
            <thead>
                <tr>
                    <th>WP Davies Logs (<?php echo count( $log_files ); ?>)</th>
                </tr>
                <tr>
                    <td>
                        <div class="wpd-debug-log-wrapper">
                            <div class="wpd-debug-log-options">
                                <?php $i = 0; ?>
                                <?php foreach($log_files as $log) : ?>
                                    <?php if ( ! is_array($log) ) continue; ?>
                                    <div class="wpd-debug-log-option<?php if ( $i == 0 ) echo ' active'; ?>" data-log="<?php echo esc_attr( sanitize_title($log['title']) ); ?>"><span class="wpd-log-title"><?php echo esc_html( $log['title'] ); ?></span></div>
                                    <?php $i++; ?>
                                <?php endforeach; ?>
                            </div>
                            <div class="wpd-debug-log-output-container">
                                <?php $i = 0; ?>
                                <?php foreach($log_files as $log) : ?>
                                    <?php if ( ! is_array($log) ) continue; ?>
                                    <div class="wpd-debug-log-output<?php if ( $i == 0 ) echo ' active'; echo ' ' . esc_attr( sanitize_title($log['title']) ); ?>"><?php wpdai_display_log( $log['file_name'], $log['title'] ); ?></div>
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
<!-- Data Management Table -->
<?php
$data_manager = WPDAI_Data_Manager::get_instance();
$data_manager->render_data_management_table();
?>
<!-- Migrations Table -->
<?php
$migration = WPDAI_Migration::get_instance();
$migration->render_migrations_table();
?>