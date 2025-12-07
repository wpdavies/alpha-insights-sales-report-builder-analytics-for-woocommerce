<?php
/**
 *
 * Expense Report Email Template
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
 *	@var $profit_report_data
 *	@var $profit_report_totals
 *	@var $profit_report_settings
 */
$from_date 						= $expense_reports->get_date_from(WPD_AI_PHP_PRETTY_DATE); 	// date in the past
$to_date						= $expense_reports->get_date_to(WPD_AI_PHP_PRETTY_DATE); 	// current date
?>
<?php wpd_email_header( sprintf( __( '%s Expense Report', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'Alpha Insights' ) ); ?>
<table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnCodeBlock">
    <tbody class="mcnTextBlockOuter">
        <tr>
            <td valign="top" class="mcnTextBlockInner">
				<div class="mcnTextContent">
					<p style="color: #03aaed;font-size: 19px; text-align: center;"><?php printf( __( 'Showing results from %s to %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $from_date, $to_date ); ?>.</p>
				    <table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" id="bodyTable">
				        <tbody>
				        	<tr>
					            <td align="center" valign="top">
					                <table border="0" cellpadding="20" cellspacing="0" width="100%" id="emailContainer">
					                    <tbody>
					                    	<?php if ( $expense_report_settings['details']['total_expenses_paid'] ) : ?>
							                    <?php wpd_table_row_report_data( __( 'Total Expenses Paid', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), wc_price($expense_report_totals['total_amount_paid']) ); ?>
						                	<?php endif; ?>
						               		<?php if ( $expense_report_settings['details']['total_no_expenses'] ) : ?>
							                    <?php wpd_table_row_report_data( __( 'Total No. Expenses', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $expense_report_totals['total_expense_count'] ); ?>
						                	<?php endif; ?>
						               		<?php if ( $expense_report_settings['details']['average_expenses_per_day'] ) : ?>
							                    <?php wpd_table_row_report_data( __( 'Average Expenses Per Day', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), wc_price($expense_report_totals['average_expenses_per_day']) ); ?>
						                	<?php endif; ?>
						               		<?php if ( $expense_report_settings['details']['parent_expenses'] ) : ?>
						               			<?php foreach( $expense_report_categorized['parent_expense_type_categories'] as $key => $value ) : ?>
							                    	<?php wpd_table_row_report_data( sprintf( __( 'Spent On %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), wpd_clean_string($key) ), wc_price( $value['total_amount'] ) ); ?>
							                	<?php endforeach; ?>
						                	<?php endif; ?>
						               		<?php if ( $expense_report_settings['details']['child_expenses'] ) : ?>
						               			<?php foreach( $expense_report_categorized['child_expense_type_categories'] as $key => $value ) : ?>
							                    	<?php wpd_table_row_report_data( sprintf( __( 'Spent On %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), wpd_clean_string( $key ) ), wc_price( $value['total_amount'] ) ); ?>
							                    <?php endforeach; ?>
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