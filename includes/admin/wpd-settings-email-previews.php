<?php
/**
 *
 * Settings - Email Previews
 *
 * @package Alpha Insights
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

if ( isset($_GET['email_preview']) && $_GET['email_preview'] === 'profit-report' ) {

	?>
	<div class="wpd-wrapper">
		<div class="pull-left wpd-section-heading"><?php _e( 'Profit Report Email', WPD_AI_TEXT_DOMAIN ) ?></div>
		<div class="pull-right">
			<a href="<?php echo wpd_admin_page_url( 'settings-emails' ); ?>" class="wpd-input button button-secondary"><?php _e( 'Return To Settings', WPD_AI_TEXT_DOMAIN ) ?></a>
			<a href="#" id="send-email-profit-report" class="wpd-input button button-primary"><?php _e( 'Send Email', WPD_AI_TEXT_DOMAIN ) ?></a>
		</div>
		<div class="wpd-inline">
			<span class="wpd-filter-wrapper"><?php _e( 'Profit Report', WPD_AI_TEXT_DOMAIN ) ?></span>
			<a href="<?php echo wpd_admin_page_url( 'settings-emails-preview-expense-report' ); ?>" class="wpd-filter-wrapper"><?php _e( 'Expense Report', WPD_AI_TEXT_DOMAIN ) ?></a>
		</div>
	</div>
	<?php 
	wpd_email( 'wpd_profit_report', true );
	wpd_javascript_email_ajax( '#send-email-profit-report', 'wpd_profit_report' );

} elseif( isset($_GET['email_preview']) && $_GET['email_preview'] === 'expense-report' ) {

	?>
	<div class="wpd-wrapper">
		<div class="wpd-section-heading pull-left"><?php _e( 'Expense Report Email', WPD_AI_TEXT_DOMAIN ) ?></div>
		<div class="pull-right">
			<a href="<?php echo wpd_admin_page_url( 'settings-emails' ); ?>" class="wpd-input button button-secondary"><?php _e( 'Return To Settings', WPD_AI_TEXT_DOMAIN ) ?></a>
			<a href="#" id="send-email-expense-report" class="wpd-input button button-primary"><?php _e( 'Send Email', WPD_AI_TEXT_DOMAIN ) ?></a>
		</div>
		<div class="wpd-inline">
			<a href="<?php echo wpd_admin_page_url( 'settings-emails-preview-profit-report' ); ?>" class="wpd-filter-wrapper"><?php _e( 'Profit Report', WPD_AI_TEXT_DOMAIN ) ?></a>
			<span class="wpd-filter-wrapper"><?php _e( 'Expense Report', WPD_AI_TEXT_DOMAIN ) ?></span>
		</div>
	</div>

	<?php
	wpd_email( 'wpd_expense_report', true );
	wpd_javascript_email_ajax( '#send-email-expense-report', 'wpd_expense_report' );

} else {

	?>	
	<div class="wpd-wrapper">
		<div class="wpd-section-heading"><?php _e( 'Sorry, we couldn\'t find this email preview', WPD_AI_TEXT_DOMAIN ) ?></div>
		<a href="<?php echo wpd_admin_page_url( 'settings-emails' ); ?>" class="wpd-input button button-secondary pull-right"><?php _e( 'Return To Settings', WPD_AI_TEXT_DOMAIN ) ?></a>
	</div>
	<?php

}

?>