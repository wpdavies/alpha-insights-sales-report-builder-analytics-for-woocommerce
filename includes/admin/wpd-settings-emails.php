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
		<?php _e( 'Email Settings', WPD_AI_TEXT_DOMAIN ) ?>
		<?php submit_button( __( 'Save Changes', WPD_AI_TEXT_DOMAIN ), 'primary pull-right wpd-input', 'submit', false); ?>
	</div>
</div>
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<th colspan="2"><?php _e( 'Appearance Settings', WPD_AI_TEXT_DOMAIN ) ?></th>
		</thead>
		<tbody>
			<tr>
			<th>
				<label><?php _e( 'Would you like to include our header and footer?', WPD_AI_TEXT_DOMAIN ) ?><div class="wpd-meta"><?php _e( 'Sometimes this helps with formatting if you\'ve got other html email templates already adding headers and footers.', WPD_AI_TEXT_DOMAIN ) ?></div></label>
			</th>
			<td>
				<?php wpd_checkbox( 'wpd-email[appearance][header]', isset($appearance_settings['header']) ? $appearance_settings['header'] : null, __( 'Header', WPD_AI_TEXT_DOMAIN) ); ?>
				<?php wpd_checkbox( 'wpd-email[appearance][footer]', isset($appearance_settings['footer']) ? $appearance_settings['footer'] : null, __( 'Footer', WPD_AI_TEXT_DOMAIN) ); ?>
			</td>
			</tr>
		</tbody>
	</table>
</div>
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<th colspan="2" style="<?php echo $header_styles; ?>"><?php _e( 'Email #1 - Profit Report', WPD_AI_TEXT_DOMAIN ) ?></th>
		</thead>
		<tbody>
			<tr>
			<th>
				<label><?php _e( 'Comma Seperated List Of Recipient', WPD_AI_TEXT_DOMAIN ) ?></label>
			</th>
			<td>
				<input type="text" name="wpd-email[profit-report][recipients]" class="wpd-input full-width" value="<?php echo isset($profit_report_settings['recipients']) ? $profit_report_settings['recipients'] : ''; ?>" placeholder="<?php echo $admin_email ?>">
			</td>
			</tr>
			<tr>
				<th>
					<label><?php _e( 'How Often This Email Should Be Sent?', WPD_AI_TEXT_DOMAIN ) ?></label>
				</th>
				<td>
					<?php wpd_checkbox( 'wpd-email[profit-report][frequency][daily]', $profit_report_settings['frequency']['daily'], __( 'Daily', WPD_AI_TEXT_DOMAIN) ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][frequency][weekly]', $profit_report_settings['frequency']['weekly'], __( 'Weekly', WPD_AI_TEXT_DOMAIN) ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][frequency][monthly]', $profit_report_settings['frequency']['monthly'], __( 'Monthly', WPD_AI_TEXT_DOMAIN) ); ?>
				</td>
			</tr>
			<tr>
			<th><?php _e( 'What would you like to include?', WPD_AI_TEXT_DOMAIN ) ?></th>
			<td>
				<?php wpd_checkbox( 'wpd-email[profit-report][details][order_revenue]', isset($profit_report_settings['details']['order_revenue']) ? $profit_report_settings['details']['order_revenue'] : null, __( 'Net Sales (Incl. Tax)', WPD_AI_TEXT_DOMAIN) ); ?>
				<?php wpd_checkbox( 'wpd-email[profit-report][details][order_cost]', isset($profit_report_settings['details']['order_cost']) ? $profit_report_settings['details']['order_cost'] : null, __( 'Total Order Costs', WPD_AI_TEXT_DOMAIN) ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][details][order_profit]', isset($profit_report_settings['details']['order_profit']) ? $profit_report_settings['details']['order_profit'] : null, __( 'Gross Profit', WPD_AI_TEXT_DOMAIN) ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][details][order_count]', isset($profit_report_settings['details']['order_count']) ? $profit_report_settings['details']['order_count'] : null, __( 'Order Count', WPD_AI_TEXT_DOMAIN) ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][details][average_order_value]', isset($profit_report_settings['details']['average_order_value']) ? $profit_report_settings['details']['average_order_value'] : null, __( 'Average Order Value', WPD_AI_TEXT_DOMAIN) ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][details][average_profit_per_order]', isset($profit_report_settings['details']['average_profit_per_order']) ? $profit_report_settings['details']['average_profit_per_order'] : null, __( 'Average Gross Profit Per Order', WPD_AI_TEXT_DOMAIN) ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][details][total_products_sold]', isset($profit_report_settings['details']['total_products_sold']) ? $profit_report_settings['details']['total_products_sold'] : null, __( 'Total Products Sold', WPD_AI_TEXT_DOMAIN) ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][details][total_product_discounts]', isset($profit_report_settings['details']['total_product_discounts']) ? $profit_report_settings['details']['total_product_discounts'] : null, __( 'Total Product Discounts', WPD_AI_TEXT_DOMAIN) ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][details][total_refunds]', isset($profit_report_settings['details']['total_refunds']) ? $profit_report_settings['details']['total_refunds'] : null, __( 'Total Refunds', WPD_AI_TEXT_DOMAIN) ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][details][additional_expenses]', isset($profit_report_settings['details']['additional_expenses']) ? $profit_report_settings['details']['additional_expenses'] : null, __( 'Additional Expenses', WPD_AI_TEXT_DOMAIN) ); ?>
					<?php wpd_checkbox( 'wpd-email[profit-report][details][net_profit]', isset($profit_report_settings['details']['net_profit']) ? $profit_report_settings['details']['net_profit'] : null, __( 'Net Profit', WPD_AI_TEXT_DOMAIN) ); ?>
				</td>
			</tr>
			<tr>
				<td colspan="2" style="background-color: #fbfbfb;">
					<a href="<?php echo wpd_admin_page_url( 'settings-emails-preview-profit-report' ); ?>" class="wpd-input button secondary-button pull-right" target="_blank"><?php _e( 'Preview Email', WPD_AI_TEXT_DOMAIN ) ?></a>
					<a href="#" class="wpd-input button secondary-button pull-right" id="send-email-profit-report"><?php _e( 'Send Test Email', WPD_AI_TEXT_DOMAIN ) ?></a>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<div class="wpd-wrapper">
	<table class="wpd-table fixed widefat">
		<thead>
			<th colspan="2" style="<?php echo $header_styles; ?>"><?php _e( 'Email #2 - Expense Report', WPD_AI_TEXT_DOMAIN ) ?></th>
		</thead>
		<tbody>
			<tr>
			<th>
				<label><?php _e( 'Comma Seperated List Of Recipient', WPD_AI_TEXT_DOMAIN ) ?></label>
			</th>
			<td>
				<input type="text" name="wpd-email[expense-report][recipients]" class="wpd-input full-width" value="<?php echo isset($expense_report_settings['recipients']) ? $expense_report_settings['recipients'] : ''; ?>" placeholder="<?php echo $admin_email ?>">
			</td>
			</tr>
			<tr>
				<th>
					<label><?php _e( 'How Often This Email Should Be Sent?', WPD_AI_TEXT_DOMAIN ) ?></label>
				</th>
				<td>
					<?php wpd_checkbox( 'wpd-email[expense-report][frequency][daily]', $expense_report_settings['frequency']['daily'], __( 'Daily', WPD_AI_TEXT_DOMAIN) ); ?>
					<?php wpd_checkbox( 'wpd-email[expense-report][frequency][weekly]', $expense_report_settings['frequency']['weekly'], __( 'Weekly', WPD_AI_TEXT_DOMAIN) ); ?>
					<?php wpd_checkbox( 'wpd-email[expense-report][frequency][monthly]', $expense_report_settings['frequency']['monthly'], __( 'Monthly', WPD_AI_TEXT_DOMAIN) ); ?>
				</td>
			</tr>
			<tr>
			<th><?php _e( 'What would you like to include?', WPD_AI_TEXT_DOMAIN ) ?></th>
			<td>
				<?php wpd_checkbox( 'wpd-email[expense-report][details][total_expenses_paid]', isset($expense_report_settings['details']['total_expenses_paid']) ? $expense_report_settings['details']['total_expenses_paid'] : null, __( 'Total Expenses Paid', WPD_AI_TEXT_DOMAIN) ); ?>
				<?php wpd_checkbox( 'wpd-email[expense-report][details][total_no_expenses]', isset($expense_report_settings['details']['total_no_expenses']) ? $expense_report_settings['details']['total_no_expenses'] : null, __( 'Total No. Expenses', WPD_AI_TEXT_DOMAIN) ); ?>
				<?php wpd_checkbox( 'wpd-email[expense-report][details][average_expenses_per_day]', isset($expense_report_settings['details']['average_expenses_per_day']) ? $expense_report_settings['details']['average_expenses_per_day'] : null, __( 'Average Expenses Per Day', WPD_AI_TEXT_DOMAIN) ); ?>
				<?php wpd_checkbox( 'wpd-email[expense-report][details][parent_expenses]', isset($expense_report_settings['details']['parent_expenses']) ? $expense_report_settings['details']['parent_expenses'] : null, __( 'All Parent Category Expenses', WPD_AI_TEXT_DOMAIN) ); ?>
				<?php wpd_checkbox( 'wpd-email[expense-report][details][child_expenses]', isset($expense_report_settings['details']['child_expenses']) ? $expense_report_settings['details']['child_expenses'] : null, __( 'All Child Category Expenses', WPD_AI_TEXT_DOMAIN) ); ?>
			</td>
			</tr>
			<tr>
				<td colspan="2" style="background-color: #fbfbfb;">
					<a href="<?php echo wpd_admin_page_url( 'settings-emails-preview-expense-report' ); ?>" class="wpd-input button secondary-button pull-right" target="_blank"><?php _e( 'Preview Email', WPD_AI_TEXT_DOMAIN ) ?></a>
					<a href="#" class="wpd-input button secondary-button pull-right" id="send-email-expense-report"><?php _e( 'Send Test Email', WPD_AI_TEXT_DOMAIN ) ?></a>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<div class="wpd-inline"><?php submit_button( __( 'Save Changes', WPD_AI_TEXT_DOMAIN ), 'primary pull-right wpd-input', 'submit', false); ?></div>
<?php wpd_javascript_email_ajax( '#send-email-profit-report', 'wpd_profit_report' ); ?>
<?php wpd_javascript_email_ajax( '#send-email-expense-report', 'wpd_expense_report' ); ?>