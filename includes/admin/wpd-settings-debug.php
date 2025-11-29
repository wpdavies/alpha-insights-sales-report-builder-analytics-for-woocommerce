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
wpd_delete_large_logs();

/**
 * 
 *  Get all log files
 * 
 **/
$log_files = wpd_get_debug_log_data();

// Setup defaults
$order_id = '';

// Setup debug order ID 
if ( isset($_POST['wpd_ai_debug_order_id']) && ! empty($_POST['wpd_ai_debug_order_id']) ) {

    // Sanitize Order ID
    $order_id = abs( (int) $_POST['wpd_ai_debug_order_id'] );

    // Get order
    $order = wc_get_order( $order_id );

    // Get calculation meta
    $calculation = wpd_calculate_cost_profit_by_order( $order, true );

    // Fetch additional meta  for debugging
    $calculation['order_meta'] = $order->get_meta_data();

    // Output notices
    ( $calculation ) ? wpd_admin_notice( 'Order ID #' . $order_id . ' found. Outputting results.' ) : wpd_admin_notice( 'Could not find order ID #' . $order_id );

}

// Get all report class names 
if ( isset($_POST['wpd_ai_debug_data_warehouse_report']) && ! empty($_POST['wpd_ai_debug_data_warehouse_report']) ) {

    // Class Name
    $report_class_name = sanitize_text_field($_POST['wpd_ai_debug_data_warehouse_report']);

    if ( class_exists($report_class_name) ) {

        // Output notice
        wpd_admin_notice( sprintf( 'Outputting report data for "%s"', $report_class_name ));

        // Init class
        $report_object = new $report_class_name;

        // Safety Check
        if ( is_a($report_object, 'WPD_Report') ) {

            $report_data = $report_object->get_data();

        } else {

            $report_data = array( 'Couldnt load this report.' );

        }

    } else {

        wpd_admin_notice( sprintf( 'Couldnt find a report with class name "%s"', $report_class_name ));

    }

}

?>
<div class="wpd-wrapper">
	<div class="wpd-section-heading wpd-inline">
		<?php _e( 'General Settings', WPD_AI_TEXT_DOMAIN ); ?>
		<?php submit_button( __('Save Changes', WPD_AI_TEXT_DOMAIN), 'primary pull-right', 'submit', false); ?>
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
                <th colspan="2"><?php _e( 'Debug Order Calculations', WPD_AI_TEXT_DOMAIN ); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <label for="wpd_ai_general_settings"><?php _e( 'Debug order ID', WPD_AI_TEXT_DOMAIN ); ?></label>
                    <div class="wpd-meta"><?php _e( 'Use this tool to produce a full output of all the calculations for a specific order. This can assist with debugging.', WPD_AI_TEXT_DOMAIN ); ?></div>
                </td>
                <td>
                    <span style="display:inline-block">
                        <input class="wpd-input" type="number" name="wpd_ai_debug_order_id" value="<?php echo $order_id ?>" step="1" placeholder="5469">
                        <?php submit_button( __('Debug', WPD_AI_TEXT_DOMAIN), 'primary pull-right', 'submit', false); ?>
                    </span>
                </td>
            </tr>
            <?php if ( isset( $calculation ) ) : ?>
                <tr>
                    <td colspan="2">
                         <?php wpd_debug( $calculation, 'Order ' . $order_id . ' Data Dump' ); ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php if ( isset( $report_data ) ) : ?>
                <tr>
                    <td colspan="2">
                         <?php wpd_debug( $report_data, $report_class_name ); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<!-- All Logs -->
<?php if ( is_array($log_files) && ! empty($log_files) ) : ?>
    <style type="text/css">
        .wpd-debug-output td {
            padding: 0px;
        }
        .wpd-debug-log-wrapper {
            display: grid;
            grid-template-columns: 250px minmax(0, 1fr);
            align-items: stretch;
            column-gap: 15px;
            align-content: center;
        }
        .wpd-debug-log-option {
            border-bottom: solid 1px #eaeaea;
            cursor: pointer;
            flex: 1;
            align-content: center;
            padding: 15px;
        }
        .wpd-debug-log-option:hover {
            background-color: #f7f7f7;
        }
        .wpd-debug-log-option:hover, .wpd-debug-log-option.active {
            color: #03abee;
        }
        .wpd-debug-log-output {
            display: none;
        }
        .wpd-debug-log-output.active {
            display: block;
        }
        .wpd-debug-log-options {
            border-top: solid 1px #eaeaea;
            display: flex;
            flex-direction: column;
            flex-wrap: nowrap;
            justify-content: space-between;
            max-height: 500px;
            overflow-y: auto;
        }
        .wpd-debug-log-output .wpd-debug-container {
            margin: 0px;
            border: none;
        }
        .wpd-debug-log-output-container {
            align-content: center;
        }
    </style>
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
                                    <div class="wpd-debug-log-option<?php if ( $i == 0 ) echo ' active'; ?>" data-log="<?php echo sanitize_title($log['title']); ?>"><span class="wpd-log-title"><?php echo $log['title'] ?></span></div>
                                    <?php $i++; ?>
                                <?php endforeach; ?>
                            </div>
                            <div class="wpd-debug-log-output-container">
                                <?php $i = 0; ?>
                                <?php foreach($log_files as $log) : ?>
                                    <?php if ( ! is_array($log) ) continue; ?>
                                    <div class="wpd-debug-log-output<?php if ( $i == 0 ) echo ' active'; echo ' ' . sanitize_title($log['title']); ?>"><?php wpd_display_log( $log['file_name'], $log['title'] ); ?></div>
                                    <?php $i++; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </td>
                </tr>
            </thead>
        </table>
    </div>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.wpd-debug-log-option').click(function() {
                let targetLog = $(this).data('log');
                if ( targetLog ) {
                    $('.wpd-debug-log-option').removeClass('active');
                    $('.wpd-debug-log-output').hide();
                    $('.wpd-debug-log-output.' + targetLog).show();
                    $('.wpd-debug-log-option[data-log="'+ targetLog +'"]').addClass('active');
                }
            });
        });
    </script>
<?php endif; ?>