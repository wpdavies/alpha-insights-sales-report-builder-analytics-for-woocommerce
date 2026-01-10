<?php
/**
 *
 * Load admin page content
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
 * Admin Activity
 *
 */
function wpdai_profit_reports_page_content() {

	$react_report_loader = new WPD_React_Report(); // Will default to subpage if no slug passed in
	$react_report_loader->output_report();

}

/**
 *
 *	Analytics Dashboard -> This is being called from the actual sub menu page
 * 	@class WPD_Analytics_Report_Dashboard
 *
 */
function wpdai_analytics_dashboard() {

	$react_report_loader = new WPD_React_Report(); // Will default to subpage if no slug passed in
	$react_report_loader->output_report();
	return null;

}

/**
 *
 * Admin Activity
 *
 */
function wpdai_expense_reports_page() { 

	$react_report_loader = new WPD_React_Report('expenses'); // Will default to subpage if no slug passed in
	$react_report_loader->output_report();
	return null;

}
/**
 *
 * Admin Activity
 *
 */
function wpdai_advertising_reports_page() { 

	// Get the option to use React reports

	$subpage = ( isset($_GET['subpage']) ) ? sanitize_text_field( $_GET['subpage'] ) : null;
	if ( ! empty( $subpage ) && $subpage == 'facebook' ) {

		$react_report_loader = new WPD_React_Report('facebook'); // Will default to subpage if no slug passed in
		$react_report_loader->output_report();

	} elseif ( ! empty( $subpage ) && $subpage == 'google-ads' ) {

		$react_report_loader = new WPD_React_Report('google-ads'); // Will default to subpage if no slug passed in
		$react_report_loader->output_report();

	} else {

		$react_report_loader = new WPD_React_Report(); // Will default to subpage if no slug passed in
		$react_report_loader->output_report();

	}

}

/**
 *
 *	Inventory Management page
 *
 */
function wpdai_cost_of_goods_manager_page() {

	// Use modern Cost of Goods Manager
	WPD_Cost_Of_Goods_Manager::output();

}

/**
 *
 *	P&L statement page
 *
 */
function wpdai_pl_statement_page() {

	$react_report_loader = new WPD_React_Report('profit-loss-statement');
	$react_report_loader->output_report();

}

/**
 *
 *	Expense Management page
 *
 */
function wpdai_expense_management_page() {

	?>
	<div class="wrap">
		<?php

			// Load React Expense Management app
			$expense_management = new WPD_Expense_Management_React();
			$expense_management->output_expense_management();

		?>
	</div>
	<?php

}

/**
 *
 *	Settings page
 *
 */
function wpdai_settings_page() { 

	(isset($_GET['subpage'])) ? $subpage = sanitize_text_field( $_GET['subpage'] ) : $subpage = null;
	(isset($_GET['wpd-action'])) ? $wpd_action = sanitize_text_field( $_GET['wpd-action'] ) : $wpd_action = null;

	?>
  
	<div class="wrap">
		<?php do_action( 'wpd_before_heading' ); ?>
		<h3>Settings</h3>
		<?php do_action( 'wpd_before_content' ); ?>
		<div class="wpd-white-block">
			<form method="post" action="" id="wpd-ai-settings" class="<?php echo esc_attr( $subpage ); ?>-form">
				<?php

					// Add nonce field for settings form security
					wp_nonce_field( 'wpd_alpha_insights_settings', 'wpd_alpha_insights_settings_nonce' );

					// Output the content for the selected page
					wpdai_output_settings_page_content( $subpage, $wpd_action );

				?>

			</form>
		</div>
	</div>
	<?php

}

/**
 *
 *	Getting Started page
 *
 */
function wpdai_getting_started_page() {

	// Use Pro class if available, otherwise fall back to base class
	if ( class_exists( 'WPD_Getting_Started_Pro' ) ) {
		WPD_Getting_Started_Pro::render_getting_started_page();
	} else {
		WPD_Getting_Started::render_getting_started_page();
	}
	
}