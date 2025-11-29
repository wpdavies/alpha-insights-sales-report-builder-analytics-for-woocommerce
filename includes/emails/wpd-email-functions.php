<?php
/**
 *
 * Functions For Email
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
 *	Send WPD Email
 *	@todo override subject with args
 *	@args from_date, to_date, subject
 *
 */
function wpd_email( $email, $preview = false, $args = array() ) {
	
	$mail_send 			= true;
	$options 			= get_option( 'wpd_ai_email_settings' );
	$response			= array( 'email' => $email );
	$admin_email 		= get_option( 'admin_email' );
	$mail_to 			= '';
	$mail_subject 		= '';
	$mail_message 		= '';
	$mail_headers 		= array( 'Content-Type: text/html; charset=UTF-8', 'From: Alpha Insights <' . $admin_email . '>' );
	$mail_attachments 	= array();
	$args['force_refresh'] = true;
	$site_name 			= get_bloginfo( 'name' );

	// Debug logging
	wpd_write_log('Starting email generation for type: ' . $email, 'email');
	wpd_write_log('Email args: ' . print_r($args, true), 'email');

	/**
	 *
	 *	Profit Report
	 *
	 */
	if ( $email === 'wpd_profit_report' ) {

		/**
		 *
		 *	Collect data
		 *
		 */
		try {
			$profit_report_settings 	= $options['profit-report'];
			$profit_reports 			= new WPD_Data_Warehouse_React($args);
			$profit_reports->fetch_store_profit_data();

			/**
			 *
			 *	Set variables
			 *
			 */
			ob_start();
			require_once( WPD_AI_PATH . 'includes/emails/wpd-email-template_profit_report.php' );
			$mail_message 	= ob_get_clean();
			$mail_to 		= $profit_report_settings['recipients'];
			( ! empty( $args['subject'] ) ) ? $mail_subject = $args['subject'] : $mail_subject = sprintf( __( '%s Profit Report', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $site_name );

			// Check if message is empty
			if (empty(trim($mail_message))) {
				wpd_write_log('WARNING - Profit report email message is empty!', 'email');
				$mail_message = '<p>No data available for the selected period. Please check your store data and settings.</p>';
			}

			wpd_write_log('Profit report email generated successfully. Message length: ' . strlen($mail_message), 'email');

		} catch (Exception $e) {
			wpd_write_log('ERROR generating profit report email: ' . $e->getMessage(), 'email');
			$mail_message = '<p>Error generating profit report. Please check your store configuration.</p>';
			$mail_to = $admin_email; // Fallback to admin
			$mail_subject = sprintf( __( '%s Profit Report - Error', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $site_name );
		}

	} 

	/**
	 *
	 *	Expense Report
	 *
	 */
	elseif ( $email === 'wpd_expense_report' ) {

		/**
		 *
		 *	Collect data
		 *
		 */
		try {
			$expense_report_settings 	= $options['expense-report'];
			$expense_reports 			= new WPD_Data_Warehouse_React( $args );
			$expense_reports->fetch_expense_data();
			$expense_report_totals 		= $expense_reports->get_data('expenses', 'totals');
			$expense_report_categorized = $expense_reports->get_data( 'expenses', 'categorized_data' );

			/**
			 *
			 *	Set variables
			 *
			 */
			ob_start();
			require_once( WPD_AI_PATH . 'includes/emails/wpd-email-template_expense_report.php' );
			$mail_message 	= ob_get_clean();
			$mail_to 		= $expense_report_settings['recipients'];
			( ! empty( $args['subject'] ) ) ? $mail_subject = $args['subject'] : $mail_subject 	= sprintf( __( '%s Expense Report', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $site_name );

			// Check if message is empty
			if (empty(trim($mail_message))) {
				wpd_write_log('WARNING - Expense report email message is empty!', 'email');
				$mail_message = '<p>No expense data available for the selected period.</p>';
			}

		} catch (Exception $e) {
			wpd_write_log('ERROR generating expense report email: ' . $e->getMessage(), 'email');
			$mail_message = '<p>Error generating expense report. Please check your store configuration.</p>';
			$mail_to = $admin_email;
			$mail_subject = sprintf( __( '%s Expense Report - Error', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $site_name );
		}

	}

	/**
	 *
	 *	Nothing found
	 *
	 */
	else {

		$mail_send = false;
		$response['email_sent'] = false;
		$response['message'] = __( 'Email doesn\'t exist.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		wpd_write_log('Unknown email type requested: ' . $email, 'email');

	}

	/**
	 *
	 *	Preview
	 *
	 */
	if ( $preview ) {

		echo '<div class="wpd-email-preview">' . $mail_message . '</div>';
		$response['action'] = 'Email Preview';
		$mail_send = false;

	} 

	// Fallback to admin email
	if ( empty( $mail_to ) ) {
		$mail_to = $admin_email;
		wpd_write_log('No recipients found, falling back to admin email: ' . $admin_email, 'email');
	}

	/**
	 *
	 *	If we've built the data we need, send an email.
	 *
	 */
	if ( $mail_send === true ) {

		$response['email_sent_attempt'] = true;
		$response['email_sent'] = wp_mail( $mail_to, $mail_subject, $mail_message, $mail_headers );

		if ($response['email_sent']) {
			wpd_write_log('Email sent successfully to: ' . $mail_to, 'email');
		} else {
			wpd_write_log('Failed to send email to: ' . $mail_to, 'email');
		}

	} else  {

		$response['email_sent_attempt'] = false;
		$response['email_sent'] = false;
		$response['message'] = __( 'couldn\'t send email', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

	}

	$response['recipients'] = $mail_to;
	$response['mail_subject'] = $mail_subject;
	// $response['mail_message'] = $mail_message;
	$response['mail_headers'] = $mail_headers;

	return $response;

}

add_action( 'wp_mail_failed', 'wpd_on_mail_error', 10, 1 );
function wpd_on_mail_error( $wp_error ) {

	wpd_write_log( print_r($wp_error, true), 'email' );
	
} 

/**
 *
 *	Include Header
 *
 */
function wpd_email_header( $heading, $subheading = null ) {

	$site_name = get_bloginfo( 'name' );

	if ( $subheading === null ) {

		$subheading = __( 'Created for ', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) . ' ' . $site_name;

	}

	require_once( WPD_AI_PATH . 'includes/emails/wpd-email-template_styles.php' );
	require_once( WPD_AI_PATH . 'includes/emails/wpd-email-template_header.php' );

}

/**
 *
 *	Include Header
 *
 */
function wpd_email_footer(  ) {

	require_once( WPD_AI_PATH . 'includes/emails/wpd-email-template_footer.php' );

}

/**
 *
 *	Display <tr>For the label / value </tr>
 *
 */
function wpd_table_row_report_data( $label, $value ) {

	?>
        <tr>
    		<td align="center "colspan="2" valign="top"  style="border-bottom: solid 2px #eaeaea;">
    			<h2 style="text-align:center;"><?php echo $value ?></h2>
    			<p style="text-align:center;"><?php echo $label; ?></p>
    		</td>
    	</tr>
	<?php

}

/**
 *
 *	Email spacer
 *
 */
function wpd_email_divider() {

	?>
		<table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnDividerBlock" style="min-width:100%;">
		    <tbody class="mcnDividerBlockOuter">
		        <tr>
		            <td class="mcnDividerBlockInner" style="min-width:100%; padding:18px;">
		                <table class="mcnDividerContent" border="0" cellpadding="0" cellspacing="0" width="100%" style="min-width:100%;">
		                    <tbody>
		                    	<tr>
		                        	<td>
		                            	<span></span>
		                        	</td>
		                    	</tr>
		                	</tbody>
		                </table>
		            </td>
		        </tr>
		    </tbody>
		</table>
	<?php

}

/**
 *
 *	Button
 *
 */
function wpd_email_button( $text, $url ) {

	?><table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnButtonBlock" style="min-width:100%;">
		    <tbody class="mcnButtonBlockOuter">
		        <tr>
		            <td style="padding-top:0; padding-right:18px; padding-bottom:18px; padding-left:18px;" valign="top" align="center" class="mcnButtonBlockInner">
		                <table border="0" cellpadding="0" cellspacing="0" class="mcnButtonContentContainer" style="border-collapse: separate !important;border-radius: 3px;background-color: #03AAED;">
		                    <tbody>
		                        <tr>
		                            <td align="center" valign="middle" class="mcnButtonContent" style="font-family: Helvetica; font-size: 18px; padding: 18px;">
		                                <a class="mcnButton " title="<?php echo $text; ?>" href="<?php echo $url; ?>" target="_blank" style="letter-spacing: -0.5px;line-height: 100%;text-align: center;text-decoration: none;color: #FFFFFF;"><?php echo $text; ?></a>
		                            </td>
		                        </tr>
		                    </tbody>
		                </table>
		            </td>
		        </tr>
		    </tbody>
		</table><?php

}

/**
 *
 *	Image container
 *
 */
function wpd_email_image( $image ) {

	?>
		<table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnImageBlock" style="min-width:100%;">
	   		<tbody class="mcnImageBlockOuter">
	            <tr>
	                <td valign="top" style="padding:9px" class="mcnImageBlockInner">
	                    <table align="left" width="100%" border="0" cellpadding="0" cellspacing="0" class="mcnImageContentContainer" style="min-width:100%;">
	                        <tbody>
	                        	<tr>
	                           		<td class="mcnImageContent" valign="top" style="padding-right: 9px; padding-left: 9px; padding-top: 0; padding-bottom: 0; text-align:center;">
	                                	<img align="center" alt="" src="<?php echo $image; ?>" width="564" style="max-width:750px; padding-bottom: 0; display: inline !important; vertical-align: bottom;" class="mcnImage">
		                            </td>
		                        </tr>
		                    </tbody>
		                </table>
	                </td>
	            </tr>
		    </tbody>
		</table>
	<?php

}