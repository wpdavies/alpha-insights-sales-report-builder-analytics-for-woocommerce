<?php
/**
 *
 * Email Template - Profit Report
 *
 * @package Alpha Insights
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 *	@var $profit_reports class object
 *	@var $total_order_data
 *	@var $profit_report_settings
 */

// Debug logging
wpd_write_log('Starting profit report email template generation', 'email');

// Ensure we have the required data with fallbacks
$from_date 				= $profit_reports->get_date_from( WPD_AI_PHP_PRETTY_DATE );
$to_date 				= $profit_reports->get_date_to( WPD_AI_PHP_PRETTY_DATE );
$date_time_stamp 		= current_time( 'Y-m-d-h-i-s' );

// Get totals with fallbacks
$total_order_data 			= $profit_reports->get_data( 'orders', 'totals' );
$total_expense_data 		= $profit_reports->get_data( 'expenses', 'totals' );
$total_store_profit_data 	= $profit_reports->get_data( 'store_profit', 'totals' );

// Ensure arrays exist and have fallback values
if (!is_array($total_order_data)) {
    wpd_write_log('total_order_data is not an array, using fallback', 'email');
    $total_order_data = array(
        'total_order_revenue' => 0,
        'total_order_cost' => 0,
        'total_order_profit' => 0,
        'total_order_count' => 0,
        'average_order_revenue' => 0,
        'average_order_profit' => 0,
        'total_skus_sold' => 0,
        'total_product_discount_amount' => 0,
        'total_refund_amount' => 0
    );
}

if (!is_array($total_expense_data)) {
    wpd_write_log('total_expense_data is not an array, using fallback', 'email');
    $total_expense_data = array('total_amount' => 0);
}

if (!is_array($total_store_profit_data)) {
    wpd_write_log('total_store_profit_data is not an array, using fallback', 'email');
    $total_store_profit_data = array('total_store_profit' => 0);
}

// Ensure required keys exist with fallbacks
$total_order_data = array_merge(array(
    'total_order_revenue' => 0,
    'total_order_cost' => 0,
    'total_order_profit' => 0,
    'total_order_count' => 0,
    'average_order_revenue' => 0,
    'average_order_profit' => 0,
    'total_skus_sold' => 0,
    'total_product_discount_amount' => 0,
    'total_refund_amount' => 0
), $total_order_data);

$total_expense_data = array_merge(array('total_amount' => 0), $total_expense_data);
$total_store_profit_data = array_merge(array('total_store_profit' => 0), $total_store_profit_data);

wpd_write_log('Profit report data prepared - Orders: ' . $total_order_data['total_order_count'] . ', Revenue: ' . $total_order_data['total_order_revenue'], 'email');

?>
<?php 
/* translators: %s: Plugin or site name */
wpd_email_header( sprintf( __( '%s Profit Report', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'Alpha Insights' ) ); ?>
<table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnCodeBlock">
    <tbody class="mcnTextBlockOuter">
        <tr>
            <td valign="top" class="mcnTextBlockInner">
				<div class="mcnTextContent">
					<?php
					?>
					<p style="color: #03aaed;font-size: 19px; text-align: center;"><?php
						printf(
							/* translators: 1: Start date, 2: End date */
							esc_html__( 'Showing results from %1$s to %2$s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
							esc_html( $from_date ),
							esc_html( $to_date )
						);
					?>.</p>
				    <table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" id="bodyTable">
				        <tbody>
				        	<tr>
					            <td align="center" valign="top">
					                <table border="0" cellpadding="20" cellspacing="0" width="100%" id="emailContainer">
					                    <tbody>
					                    	<?php if ( $profit_report_settings['details']['order_revenue'] ) : ?>
							                    <?php wpd_table_row_report_data( __( 'Net Sales (Incl. Tax)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), wc_price($total_order_data['total_order_revenue']) ); ?>
						                	<?php endif; ?>
						               		<?php if ( $profit_report_settings['details']['order_cost'] ) : ?>
							                    <?php wpd_table_row_report_data( __( 'Total Order Costs', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), wc_price($total_order_data['total_order_cost']) ); ?>
						                	<?php endif; ?>
						               		<?php if ( $profit_report_settings['details']['order_profit'] ) : ?>
							                    <?php wpd_table_row_report_data( __( 'Gross Profit', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), wc_price($total_order_data['total_order_profit']) ); ?>
						                	<?php endif; ?>
						               		<?php if ( $profit_report_settings['details']['order_count'] ) : ?>
							                    <?php wpd_table_row_report_data( __( 'Number Of Orders', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), $total_order_data['total_order_count'] ); ?>
						                	<?php endif; ?>
						               		<?php if ( $profit_report_settings['details']['average_order_value'] ) : ?>
							                    <?php wpd_table_row_report_data( __( 'Average Order Value', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), wc_price($total_order_data['average_order_revenue']) ); ?>
						                	<?php endif; ?>
						               		<?php if ( $profit_report_settings['details']['average_profit_per_order'] ) : ?>
							                    <?php wpd_table_row_report_data( __( 'Average Gross Profit Per Order', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), wc_price($total_order_data['average_order_profit']) ); ?>
						                	<?php endif; ?>
						               		<?php if ( $profit_report_settings['details']['total_products_sold'] ) : ?>
							                    <?php wpd_table_row_report_data( __( 'Total Products Sold', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), $total_order_data['total_skus_sold'] ); ?>
						                	<?php endif; ?>
						               		<?php if ( $profit_report_settings['details']['total_product_discounts'] ) : ?>
							                    <?php wpd_table_row_report_data( __( 'Total Product Discounts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), wc_price($total_order_data['total_product_discount_amount']) ); ?>
						                	<?php endif; ?>
						               		<?php if ( $profit_report_settings['details']['total_refunds'] ) : ?>
							                    <?php wpd_table_row_report_data( __( 'Order Refunds', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), wc_price($total_order_data['total_refund_amount']) ); ?>
						                	<?php endif; ?>
						               		<?php if ( $profit_report_settings['details']['additional_expenses'] ) : ?>
							                    <?php wpd_table_row_report_data( __( 'Additional Expenses', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), wc_price($total_expense_data['total_amount']) ); ?>
						                	<?php endif; ?>
						               		<?php if ( $profit_report_settings['details']['net_profit'] ) : ?>
							                    <?php wpd_table_row_report_data( __( 'Total Net Profit', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'), wc_price($total_store_profit_data['total_store_profit']) ); ?>
						                	<?php endif; ?>
					                	</tbody>
					            	</table>
					            </td>
					        </tr>
				    	</tbody>
					</table>
				</div>
            </td>
        </tr>
    </tbody>
</table>
<?php wpd_email_divider(); ?>
<?php wpd_email_footer(); ?>