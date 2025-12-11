<?php
/**
 *
 * Settings Page - Email
 *
 * @package Alpha Insights
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 *
 *	Email Settings
 *
 */
$header_styles 				= 'background: rgb(3, 170, 237);color: white;border-top-right-radius: 7px;border-top-left-radius: 7px;';
$admin_email 				= get_option( 'admin_email' );
$email_settings 			= get_option( 'wpd_ai_email_settings' );
$appearance_settings 		= isset($email_settings['appearance']) ? $email_settings['appearance'] : array();
$profit_report_settings 	= isset($email_settings['profit-report']) ? (array) $email_settings['profit-report'] : array();
$expense_report_settings 	= isset($email_settings['expense-report']) ? (array) $email_settings['expense-report'] : array();
$inventory_report_settings 	= isset($email_settings['inventory-report']) ? (array) $email_settings['inventory-report'] : array();

if ( ! isset($profit_report_settings['frequency']['daily']) ) $profit_report_settings['frequency']['daily'] = null;
if ( ! isset($profit_report_settings['frequency']['weekly']) ) $profit_report_settings['frequency']['weekly'] = null;
if ( ! isset($profit_report_settings['frequency']['monthly']) ) $profit_report_settings['frequency']['monthly'] = null;
if ( ! isset($expense_report_settings['frequency']['daily']) ) $expense_report_settings['frequency']['daily'] = null;
if ( ! isset($expense_report_settings['frequency']['weekly']) ) $expense_report_settings['frequency']['weekly'] = null;
if ( ! isset($expense_report_settings['frequency']['monthly']) ) $expense_report_settings['frequency']['monthly'] = null;
if ( ! isset($inventory_report_settings['frequency']['daily']) ) $inventory_report_settings['frequency']['daily'] = null;
if ( ! isset($inventory_report_settings['frequency']['weekly']) ) $inventory_report_settings['frequency']['weekly'] = null;
if ( ! isset($inventory_report_settings['frequency']['monthly']) ) $inventory_report_settings['frequency']['monthly'] = null;

?>
<div class="wpd-wrapper">
	<div class="wpd-section-heading wpd-inline">
		<?php esc_html_e( 'Email Settings', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ?>
		<?php submit_button( __( 'Save Changes', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'primary pull-right wpd-input', 'submit', false); ?>
	</div>
</div>
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<th colspan="2"><?php esc_html_e( 'Appearance Settings', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ?></th>
		</thead>
		<tbody>
			<tr>
			<th>
				<label><?php esc_html_e( 'Would you like to include our header and footer?', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ?><div class="wpd-meta"><?php esc_html_e( 'Sometimes this helps with formatting if you\'ve got other html email templates already adding headers and footers.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ?></div></label>
			</th>
			<td>
				<?php wpd_checkbox( 'wpd-email[appearance][header]', isset($appearance_settings['header']) ? $appearance_settings['header'] : null, __( 'Header', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
				<?php wpd_checkbox( 'wpd-email[appearance][footer]', isset($appearance_settings['footer']) ? $appearance_settings['footer'] : null, __( 'Footer', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
			</td>
			</tr>
		</tbody>
	</table>
</div>
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<th colspan="2" style="<?php echo esc_attr( $header_styles ); ?>"><?php esc_html_e( 'Email #1 - Profit Report', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ?></th>
		</thead>
		<tbody>
			<tr>
			<th>
				<label><?php esc_html_e( 'Comma Seperated List Of Recipient', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ?></label>
			</th>
			<td>
				<input type="text" name="wpd-email[profit-report][recipients]" class="wpd-input full-width" value="<?php echo esc_attr( isset($profit_report_settings['recipients']) ? $profit_report_settings['recipients'] : '' ); ?>" placeholder="<?php echo esc_attr( $admin_email ); ?>">
			</td>
			</tr>
			<tr>
				<th>
					<label><?php esc_html_e( 'How Often This Email Should Be Sent?', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ?></label>
				</th>
				<td>
					<?php wpd_checkbox( 'wpd-email[profit-report][frequency][daily]', $profit_report_settings['frequency']['daily'], __( 'Daily', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][frequency][weekly]', $profit_report_settings['frequency']['weekly'], __( 'Weekly', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][frequency][monthly]', $profit_report_settings['frequency']['monthly'], __( 'Monthly', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
				</td>
			</tr>
			<tr>
			<th><?php esc_html_e( 'What would you like to include?', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ?></th>
			<td>
				<?php wpd_checkbox( 'wpd-email[profit-report][details][order_revenue]', isset($profit_report_settings['details']['order_revenue']) ? $profit_report_settings['details']['order_revenue'] : null, __( 'Net Sales (Incl. Tax)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
				<?php wpd_checkbox( 'wpd-email[profit-report][details][order_cost]', isset($profit_report_settings['details']['order_cost']) ? $profit_report_settings['details']['order_cost'] : null, __( 'Total Order Costs', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][details][order_profit]', isset($profit_report_settings['details']['order_profit']) ? $profit_report_settings['details']['order_profit'] : null, __( 'Gross Profit', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][details][order_count]', isset($profit_report_settings['details']['order_count']) ? $profit_report_settings['details']['order_count'] : null, __( 'Order Count', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][details][average_order_value]', isset($profit_report_settings['details']['average_order_value']) ? $profit_report_settings['details']['average_order_value'] : null, __( 'Average Order Value', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][details][average_profit_per_order]', isset($profit_report_settings['details']['average_profit_per_order']) ? $profit_report_settings['details']['average_profit_per_order'] : null, __( 'Average Gross Profit Per Order', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][details][total_products_sold]', isset($profit_report_settings['details']['total_products_sold']) ? $profit_report_settings['details']['total_products_sold'] : null, __( 'Total Products Sold', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][details][total_product_discounts]', isset($profit_report_settings['details']['total_product_discounts']) ? $profit_report_settings['details']['total_product_discounts'] : null, __( 'Total Product Discounts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][details][total_refunds]', isset($profit_report_settings['details']['total_refunds']) ? $profit_report_settings['details']['total_refunds'] : null, __( 'Total Refunds', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][details][additional_expenses]', isset($profit_report_settings['details']['additional_expenses']) ? $profit_report_settings['details']['additional_expenses'] : null, __( 'Additional Expenses', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][details][net_profit]', isset($profit_report_settings['details']['net_profit']) ? $profit_report_settings['details']['net_profit'] : null, __( 'Net Profit', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
				</td>
			</tr>
			<tr>
				<td colspan="2" style="background-color: #fbfbfb;">
					<a href="<?php echo esc_url( wpd_admin_page_url( 'settings-emails-preview-profit-report' ) ); ?>" class="wpd-input button secondary-button pull-right" target="_blank"><?php esc_html_e( 'Preview Email', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ?></a>
					<a href="#" class="wpd-input button secondary-button pull-right" id="send-email-profit-report" data-wpd-email-ajax="wpd_profit_report"><?php esc_html_e( 'Send Test Email', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ?></a>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<th colspan="2" style="<?php echo esc_attr( $header_styles ); ?>"><?php esc_html_e( 'Email #2 - Expense Report', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ?></th>
		</thead>
		<tbody>
			<tr>
			<th>
				<label><?php esc_html_e( 'Comma Seperated List Of Recipient', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ?></label>
			</th>
			<td>
				<input type="text" name="wpd-email[expense-report][recipients]" class="wpd-input full-width" value="<?php echo esc_attr( isset($expense_report_settings['recipients']) ? $expense_report_settings['recipients'] : '' ); ?>" placeholder="<?php echo esc_attr( $admin_email ); ?>">
			</td>
			</tr>
			<tr>
				<th>
					<label><?php esc_html_e( 'How Often This Email Should Be Sent?', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ?></label>
				</th>
				<td>
					<?php wpd_checkbox( 'wpd-email[expense-report][frequency][daily]', $expense_report_settings['frequency']['daily'], __( 'Daily', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
					<?php wpd_checkbox( 'wpd-email[expense-report][frequency][weekly]', $expense_report_settings['frequency']['weekly'], __( 'Weekly', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
					<?php wpd_checkbox( 'wpd-email[expense-report][frequency][monthly]', $expense_report_settings['frequency']['monthly'], __( 'Monthly', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
				</td>
			</tr>
			<tr>
			<th><?php esc_html_e( 'What would you like to include?', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ?></th>
			<td>
				<?php wpd_checkbox( 'wpd-email[expense-report][details][total_expenses_paid]', isset($expense_report_settings['details']['total_expenses_paid']) ? $expense_report_settings['details']['total_expenses_paid'] : null, __( 'Total Expenses Paid', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
				<?php wpd_checkbox( 'wpd-email[expense-report][details][total_no_expenses]', isset($expense_report_settings['details']['total_no_expenses']) ? $expense_report_settings['details']['total_no_expenses'] : null, __( 'Total No. Expenses', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
				<?php wpd_checkbox( 'wpd-email[expense-report][details][average_expenses_per_day]', isset($expense_report_settings['details']['average_expenses_per_day']) ? $expense_report_settings['details']['average_expenses_per_day'] : null, __( 'Average Expenses Per Day', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
				<?php wpd_checkbox( 'wpd-email[expense-report][details][parent_expenses]', isset($expense_report_settings['details']['parent_expenses']) ? $expense_report_settings['details']['parent_expenses'] : null, __( 'All Parent Category Expenses', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
				<?php wpd_checkbox( 'wpd-email[expense-report][details][child_expenses]', isset($expense_report_settings['details']['child_expenses']) ? $expense_report_settings['details']['child_expenses'] : null, __( 'All Child Category Expenses', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') ); ?>
			</td>
			</tr>
			<tr>
				<td colspan="2" style="background-color: #fbfbfb;">
					<a href="<?php echo esc_url( wpd_admin_page_url( 'settings-emails-preview-expense-report' ) ); ?>" class="wpd-input button secondary-button pull-right" target="_blank"><?php esc_html_e( 'Preview Email', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ?></a>
					<a href="#" class="wpd-input button secondary-button pull-right" id="send-email-expense-report" data-wpd-email-ajax="wpd_expense_report"><?php esc_html_e( 'Send Test Email', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ?></a>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<div class="wpd-inline"><?php submit_button( __( 'Save Changes', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'primary pull-right wpd-input', 'submit', false); ?></div>