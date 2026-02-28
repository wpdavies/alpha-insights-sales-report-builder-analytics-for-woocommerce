<?php
/**
 *
 * Footer Template For Emails
 *
 * @package Alpha Insights
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 *	@todo create setting to show/hide footer and set in if statement
 */
$options = get_option( 'wpd_ai_email_settings' );
// Close header tags
?>
											<?php if ( $options['appearance']['footer'] == 1 ) : ?>
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
											</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" valign="top" id="templateFooter" data-template-container>
                                    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" class="templateContainer">
                                        <tr>
                                            <td valign="top" class="footerContainer">
                                            	<table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnTextBlock" style="min-width:100%;">
												    <tbody class="mcnTextBlockOuter">
												        <tr>
												            <td valign="top" class="mcnTextBlockInner" style="padding-top:9px;">
												                <table align="left" border="0" cellpadding="0" cellspacing="0" style="max-width:100%; min-width:100%;" width="100%" class="mcnTextContentContainer">
												                    <tbody>
												                    	<tr>
												                        	<td valign="top" class="mcnTextContent" style="padding-top:0; padding-right:18px; padding-bottom:9px; padding-left:18px;">Want to change how you receive these emails?<br>Click here to <a href="<?php echo esc_url( wpdai_admin_page_url('settings-emails') ); ?>">update your preferences</a>.<br><br>This report is powered by <a href="<?php echo esc_url( wpdai_wpdavies_url( '/plugins/alpha-insights/', __( 'Alpha Insights Email Report', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'email' ) ); ?>" target="_blank" rel="noopener noreferrer">Alpha Insights</a> by <a href="<?php echo esc_url( wpdai_wpdavies_url( '/', __( 'Alpha Insights Email Report', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'email' ) ); ?>" target="_blank" rel="noopener noreferrer">WP Davies</a></td>
												                    	</tr>
												                	</tbody>
												            	</table>
												            </td>
												        </tr>
												    </tbody>
												</table>
											</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
    	<?php endif; ?>
    </center>
</body>