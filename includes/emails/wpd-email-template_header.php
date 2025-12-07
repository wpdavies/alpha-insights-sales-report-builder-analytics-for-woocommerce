<?php
/**
 *
 * HTML template for email headings
 *
 * @package Alpha Insights
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;
$options = get_option( 'wpd_ai_email_settings' );
?>
<body>
    <center>
    	<?php if ( $options['appearance']['header'] == 1 ) : ?>
			<table align="center" border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" id="bodyTable">
				<tr>
					<td align="center" valign="top" id="bodyCell">
						<table border="0" cellpadding="0" cellspacing="0" width="100%">
							<tr>
								<td align="center" valign="top" id="templateHeader" data-template-container>
									<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" class="templateContainer">
										<tr>
											<td valign="top" class="headerContainer">
												<table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnTextBlock" style="min-width:100%;">
													<tbody class="mcnTextBlockOuter">
														<tr>
															<td valign="top" class="mcnTextBlockInner" style="padding-top:9px;">
																<table align="left" border="0" cellpadding="0" cellspacing="0" style="max-width:100%; min-width:100%;" width="100%" class="mcnTextContentContainer">
																	<tbody>
																		<tr>
																			<td valign="top" class="mcnTextContent" style="padding-top:0; padding-right:18px; padding-bottom:9px; padding-left:18px;">
																				<h1 style="text-align: center;"><span style="color:#FFFFFF"><?php echo $heading ?></span></h1>
																			</td>
																		</tr>
																	</tbody>
																</table>
															</td>
														</tr>
													</tbody>
												</table>
												<table border="0" cellpadding="0" cellspacing="0" width="100%" class="mcnTextBlock" style="min-width:100%;">
													<tbody class="mcnTextBlockOuter">
														<tr>
															<td valign="top" class="mcnTextBlockInner" style="padding-top:9px;">
																<table align="left" border="0" cellpadding="0" cellspacing="0" style="max-width:100%; min-width:100%;" width="100%" class="mcnTextContentContainer">
																	<tbody>
																		<tr>
																			<td valign="top" class="mcnTextContent" style="padding-top:0; padding-right:18px; padding-bottom:9px; padding-left:18px;">
																				<div style="text-align: center;">
																					<font color="#ffffff"><?php echo $subheading; ?></font>
																				</div>
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
								<td align="center" valign="top" id="templateBody" data-template-container>
									<table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" class="templateContainer">
										<tr>
											<td valign="top" class="bodyContainer">
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
											<?php endif; ?>