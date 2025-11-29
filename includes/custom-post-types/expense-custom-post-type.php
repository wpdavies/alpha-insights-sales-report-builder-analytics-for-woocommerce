<?php
/**
 *
 * Expense Tracking Custom Post Type
 *
 * @package Alpha Insights
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 * 
 * 	@post_meta
 * 
 * 	$wpd_paid 						= get_post_meta( $post->ID, '_wpd_paid', true ); | Saved as 1 or 0. 1 for paid 0 for unpaid. Default to 1 in all calculations
 *	$wpd_amount_paid 				= get_post_meta( $post->ID, '_wpd_amount_paid', true );
 * 	$wpd_tax_amount 				= get_post_meta( $post->ID, '_wpd_tax_amount', true );
 *	$wpd_amount_paid_currency 		= get_post_meta( $post->ID, '_wpd_amount_paid_currency', true );
 *	$wpd_date_paid 					= get_post_meta( $post->ID, '_wpd_date_paid', true );
 *	$wpd_date_invoiced 				= get_post_meta( $post->ID, '_wpd_date_invoiced', true );
 *	$wpd_expense_reference 			= get_post_meta( $post->ID, '_wpd_expense_reference', true );
 *	$recurring_expense_enabled 		= get_post_meta( $post->ID, '_wpd_recurring_expense_enabled', true );
 *	$recurring_expense_frequency 	= get_post_meta( $post->ID, '_wpd_recurring_expense_frequency', true );
 *	$recurring_expense_date_started	= get_post_meta( $post->ID, '_wpd_recurring_expense_beginning_date', true );
 *
 */
defined( 'ABSPATH' ) || exit;

// Main Class
class WPD_Expense_Tracking_CPT {

	public function __construct() {

		// Initialise custom post type
		add_action( 'init', array( $this, 'register_expense_cpt' ) );

		// Initialise custom post type taxonomy
		add_action( 'init', array( $this, 'expense_taxonomies' ), 0 );

		// Meta boxes
		add_action( 'admin_init', array( $this, 'register_expense_meta_boxes' ) );

		// Save info
		add_action( 'save_post', array( $this, 'save_expense_data') );

		// Modify expense post columns
		add_filter( 'manage_expense_posts_columns', array($this, 'register_expense_column') );
		add_action( 'manage_expense_posts_custom_column' , array($this, 'expense_column_data'), 10, 2 );

		// Sort columns
		add_action( 'pre_get_posts', array( $this, 'sort_expense_columns' ) );
		add_filter( 'manage_edit-expense_sortable_columns', array($this, 'set_expense_sortable_columns' ) );

		// Prevent selected checkbox showing on top for custom post type
		add_filter( 'wp_terms_checklist_args', array($this, 'wp_terms_checklist_args') );

		// Add new filters to custom tax
		add_action( 'restrict_manage_posts', array($this,'filter_post_type_by_taxonomy')); // Show filter
		add_filter( 'parse_query', array($this,'convert_id_to_term_in_query')); // Parse the query

		// Add new fields to the suppliers taxonomy
		add_action( 'suppliers_edit_form_fields', array( $this, 'supplier_taxonomy_custom_fields' ), 10, 1 );
		add_action( 'suppliers_add_form_fields', array( $this, 'supplier_taxonomy_custom_fields' ), 10, 1 );

		// Save the custom fields in the suppliers taxonomy
		add_action( 'edited_suppliers', array( $this, 'save_supplier_data' ), 10, 3);
		add_action( 'created_suppliers', array( $this, 'save_supplier_data' ), 10, 3);

	}

	/**
	 * Display a custom taxonomy dropdown in admin
	 */
	function filter_post_type_by_taxonomy() {

		global $typenow;

		$post_type = 'expense'; // change to your post type
		$taxonomy  = 'expense_category'; // change to your taxonomy

		if ($typenow == $post_type) {

			$selected      = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';
			$info_taxonomy = get_taxonomy($taxonomy);

			wp_dropdown_categories(array(
				'show_option_all' => sprintf( __( 'Show all %s', 'wpd-alpha-insights' ), $info_taxonomy->label ),
				'taxonomy'        => $taxonomy,
				'name'            => $taxonomy,
				'orderby'         => 'name',
				'selected'        => $selected,
				'show_count'      => true,
				'hide_empty'      => true,
				'hierarchical' 	  => true,
			));

		};

	}

	/**
	 * Filter posts by taxonomy in admin
	 */
	function convert_id_to_term_in_query($query) {

		global $pagenow;

		$post_type = 'expense'; // change to your post type
		$taxonomy  = 'expense_category'; // change to your taxonomy
		$q_vars    = &$query->query_vars;

		if ( $pagenow == 'edit.php' && isset($q_vars['post_type']) && $q_vars['post_type'] == $post_type && isset($q_vars[$taxonomy]) && is_numeric($q_vars[$taxonomy]) && $q_vars[$taxonomy] != 0 ) {

			$term = get_term_by('id', $q_vars[$taxonomy], $taxonomy);
			$q_vars[$taxonomy] = $term->slug;

		}

	}

	/** 
	 *
	 *	Register meta box
	 *
	 */
	public function register_expense_meta_boxes() {

		// add_meta_box( $id, $title, $callback, $page, $context, $priority );
		add_meta_box( "expense_meta", "Expense Information", array( $this, "expense_meta" ), "expense", "normal", "low" ); // <- Main info

	}
 
 	/**
 	 *
 	 *	Prevent checklist item showing at the top
 	 *
 	 */
	public function wp_terms_checklist_args( $args ) {

		if ( $args['taxonomy'] == 'expense_category' ) {

			$args['checked_ontop'] = false;

		}

		return $args;
		
	}

	/** 
	 *
	 *	Markup for the meta info in the middle of the page
	 *
	 */
	public function expense_meta() {

		// Sets up wp.media() JS for file uploads
		wp_enqueue_media();

		global $post;

		$wpd_paid 						= get_post_meta( $post->ID, '_wpd_paid', true );
		$wpd_amount_paid 				= get_post_meta( $post->ID, '_wpd_amount_paid', true );
		$wpd_tax_amount 				= get_post_meta( $post->ID, '_wpd_tax_amount', true );
		$wpd_amount_paid_currency 		= get_post_meta( $post->ID, '_wpd_amount_paid_currency', true );
		$wpd_date_paid 					= get_post_meta( $post->ID, '_wpd_date_paid', true );
		$wpd_date_invoiced 				= get_post_meta( $post->ID, '_wpd_date_invoiced', true );
		$wpd_expense_reference 			= get_post_meta( $post->ID, '_wpd_expense_reference', true );
		$recurring_expense_enabled 		= get_post_meta( $post->ID, '_wpd_recurring_expense_enabled', true );
		$recurring_expense_frequency 	= get_post_meta( $post->ID, '_wpd_recurring_expense_frequency', true );
		$recurring_expense_date_started	= get_post_meta( $post->ID, '_wpd_recurring_expense_beginning_date', true );
		$recurring_expense_date_ended 	= get_post_meta( $post->ID, '_wpd_recurring_expense_end_date', true );
		$facebook_api_data 				= get_post_meta( $post->ID, '_wpd_fb_api_data', true );
		$google_api_data 				= get_post_meta( $post->ID, '_wpd_google_api_campaign_data', true );
		$google_api_account_data 		= get_post_meta( $post->ID, '_wpd_google_api_account_data', true );
		$wpd_expense_attachments 		= get_post_meta( $post->ID, '_wpd_expense_attachments', true );
		$attachments 					= null;

		// Process Attachment Data
		if ( ! empty($wpd_expense_attachments) ) {
			$attachment_ids = explode(',', $wpd_expense_attachments);
			if ( is_array($attachment_ids) && ! empty($attachment_ids) ) {
				foreach( $attachment_ids as $attachment_id ) {
					$attachments[$attachment_id] = wpd_get_attachment_data_by_id( $attachment_id );
				}
			}
		}
		
		// Recurring expenses display
		if ( $recurring_expense_enabled ) {
			echo '<style type="text/css">.recurring-expense-hide {display:none;}</style>';
		} else {
			echo '<style type="text/css">.recurring-expense-show {display:none;}</style>';
		}
		?>
		<style type="text/css">
			div#expense_meta .inside {
				padding: 0px !important;
			}
			div#expense_meta .inside .wpd-wrapper,
			div#expense_meta .inside .wpd-wrapper table {
				margin: 0px;
				border: none;
			}
			tr.form-field.required label::after {
				content: '*';
				color: red;
				margin-left: 3px;
			}
		</style>
		<div class="wpd-wrapper">
			<table class="wpd-table widefat fixed">
				<tbody>
					<tr class="form-field required">
						<th>
							<label for="_wpd_paid"><?php _e( 'Has this expense been paid?', 'wpd-alpha-insights' ) ?></label>
							<div class="wpd-meta"><?php _e( 'Will default to true, set to unpaid if outstanding invoice.', 'wpd-alpha-insights' ) ?></p></div>
						</th>
						<td>
							<select class="wpd-input wpd-expense-paid" name="_wpd_paid" id="_wpd_paid">
								<option value="1" <?php echo wpd_selected_option( '1', $wpd_paid ) ?> ><?php _e( 'Paid', 'wpd-alpha-insights' ); ?></option>
								<option value="0" <?php echo wpd_selected_option( '0', $wpd_paid) ?> ><?php _e( 'Unpaid', 'wpd-alpha-insights' ); ?></option>
							</select>
						</td>
					</tr>
					<tr class="form-field required">
						<th>
							<label for="_wpd_amount_paid"><?php _e( 'Total Expense Amount', 'wpd-alpha-insights' ) ?></label>
							<div class="wpd-meta"><?php _e( 'Enter a number and 2 decimals only, don\'t worry about dollar formatting.', 'wpd-alpha-insights' ) ?></p></div>
						</th>
						<td>
							<input type="number" class="wpd-expense-total-amount" value="<?php echo $wpd_amount_paid ?>" placeholder="45.36" name="_wpd_amount_paid" step=".01">
						</td>
					</tr>
					<tr class="form-field" style="display:none;">
						<th>
							<label for="_wpd_tax_amount"><?php _e( 'Taxes (If applicable)', 'wpd-alpha-insights' ) ?></label>
							<div class="wpd-meta"><?php _e( 'Enter a number and 2 decimals only, don\'t worry about dollar formatting.', 'wpd-alpha-insights' ) ?></p></div>
						</th>
						<td>
							<input type="number" class="wpd-expense-tax-amount" value="<?php echo $wpd_tax_amount ?>" placeholder="2.46" name="_wpd_tax_amount" step=".01">
						</td>
					</tr>
					<tr class="form-field required">
						<th><label for="_wpd_amount_paid_currency"><?php _e( 'Amount Paid Currency', 'wpd-alpha-insights' ) ?></label></th>
						<td style="vertical-align: top;">
							<select name="_wpd_amount_paid_currency" class="wpd-expense-currency">
								<?php echo wpd_woocommerce_currency_list_select( $wpd_amount_paid_currency ); ?>
							</select>
						</td>
					</tr>
					<tr class="form-field recurring-expense-hide required">
						<th>
							<label for="_wpd_date_paid"><?php _e( 'Date', 'wpd-alpha-insights' ) ?></label>
							<div class="wpd-meta"><?php _e( 'Select the date for the amount of the expense.', 'wpd-alpha-insights' ) ?></div>
						</th>
						<td style="display: flex;">
							<?php echo wpd_date_picker( $wpd_date_paid , '_wpd_date_paid', 'wpd-expense-date') ?>
						</td>
					</tr>
<!-- 					<tr class="form-field recurring-expense-hide <?php if ( $wpd_paid == 0 ) echo 'required' ?>">
						<th>
							<label for="_wpd_date_invoiced"><?php _e( 'Date Invoiced', 'wpd-alpha-insights' ) ?></label>
							<div class="wpd-meta"><?php _e( 'Select the date this invoice was generated.', 'wpd-alpha-insights' ) ?></div>
						</th>
						<td>
							<?php echo wpd_date_picker( $wpd_date_invoiced, '_wpd_date_invoiced', 'wpd-invoice-date' ) ?>
						</td>
					</tr> -->
					<tr class="form-field">
						<th>
							<label for="_wpd_recurring_expense_enabled"><?php _e( 'Recurring Expense', 'wpd-alpha-insights' ) ?></label>
							<div class="wpd-meta"><?php _e( 'Enables or disables recurring expense feature for this expense.', 'wpd-alpha-insights' ) ?></p></div>
						</th>
						<td>
							<select class="wpd-input" name="_wpd_recurring_expense_enabled" id="_wpd_recurring_expense_enabled">
								<option value="0" <?php echo wpd_selected_option( '0', $recurring_expense_enabled ) ?> ><?php _e( 'False', 'wpd-alpha-insights' ); ?></option>
								<option value="1" <?php echo wpd_selected_option( '1', $recurring_expense_enabled ) ?> ><?php _e( 'True', 'wpd-alpha-insights' ); ?></option>
							</select>
						</td>
					</tr>
					<tr class="form-field recurring-expense-show required">
						<th>
							<label for="_wpd_recurring_expense_frequency"><?php _e( 'Recurring Expense Frequency', 'wpd-alpha-insights' ) ?></label>
							<div class="wpd-meta"><?php _e( 'This expense will be calculated according to your chosen frequency starting from the Recurring Expense Beginning Date.', 'wpd-alpha-insights' ) ?></p></div>
						</th>
						<td>
							<select class="wpd-input" name="_wpd_recurring_expense_frequency">
								<option value="daily" <?php echo wpd_selected_option( 'daily', $recurring_expense_frequency ) ?> ><?php _e( 'Daily', 'wpd-alpha-insights' ); ?></option>
								<option value="weekly" <?php echo wpd_selected_option( 'weekly', $recurring_expense_frequency ) ?> ><?php _e( 'Weekly', 'wpd-alpha-insights' ); ?></option>
								<option value="fortnightly" <?php echo wpd_selected_option( 'fortnightly', $recurring_expense_frequency ) ?> ><?php _e( 'Fortnightly', 'wpd-alpha-insights' ); ?></option>
								<option value="monthly" <?php echo wpd_selected_option( 'monthly', $recurring_expense_frequency ) ?> ><?php _e( 'Monthly', 'wpd-alpha-insights' ); ?></option>
								<option value="yearly" <?php echo wpd_selected_option( 'yearly', $recurring_expense_frequency ) ?> ><?php _e( 'Annually', 'wpd-alpha-insights' ); ?></option>
							</select>
						</td>
					</tr>
					<tr class="form-field recurring-expense-show required">
						<th>
							<label for="_wpd_recurring_expense_beginning_date"><?php _e( 'Recurring Expense Beginning Date', 'wpd-alpha-insights' ) ?></label>
							<div class="wpd-meta"><?php _e( 'This is the first date from which your recurring expense will start to appear.', 'wpd-alpha-insights' ) ?></div>
						</th>
						<td style="display:flex;">
							<?php echo wpd_date_picker( $recurring_expense_date_started, '_wpd_recurring_expense_beginning_date' ) ?>
						</td>
					</tr>
					<tr class="form-field recurring-expense-show">
						<th>
							<label for="_wpd_recurring_expense_end_date"><?php _e( 'Recurring Expense End Date', 'wpd-alpha-insights' ) ?></label>
							<div class="wpd-meta"><?php _e( 'This is the last date from which your recurring expense will occur.<br>If left empty, it will continue to calculate against the current date.', 'wpd-alpha-insights' ) ?></div>
						</th>
						<td style="display: flex;">
							<?php echo wpd_date_picker( $recurring_expense_date_ended, '_wpd_recurring_expense_end_date', 'future_date' ) ?>
						</td>
					</tr>
					<tr class="form-field">
						<th>
							<label for="_wpd_expense_reference"><?php _e( 'Invoice/Reference Number (if applicable)', 'wpd-alpha-insights' ) ?></label>
							<div class="wpd-meta"><?php _e( 'Any relevant reference number information if required', 'wpd-alpha-insights' ) ?>.</div>
						</th>
						<td>
							<input type="text" value="<?php echo $wpd_expense_reference ?>" name="_wpd_expense_reference">
						</td>
					</tr>
					<tr class="form-field">
						<th>
							<label for="_wpd_expense_attachments"><?php _e( 'Attachments (Evidence)', 'wpd-alpha-insights' ) ?></label>
							<div class="wpd-meta"><?php _e( 'Attach relevant evidence, multiple attachments is acceptable', 'wpd-alpha-insights' ) ?>.<br>You can click on a thumbnail to view the attachment.</div>
						</th>
						<td>
							<div class="wpd-attachment-media" data-attachment-ids="<?php echo $wpd_expense_attachments; ?>">
								<?php if (is_array($attachments)) : ?>
									<?php foreach( $attachments as $attachment ) : ?>
										<a href="<?php echo $attachment['file_url']; ?>" target="_blank" class="wpd-attachment-preview" style="background-image: url( <?php echo $attachment['thumbnail_url']; ?> );">
											<div class="wpd-remove-media" data-attachment-id="<?php echo $attachment['attachment_id']; ?>"><span class="dashicons dashicons-no-alt"></span></div>
											<div class="wpd-media-file-name"><?php echo $attachment['file_name']; ?></div>
										</a>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
							<input type="hidden" value="<?php echo $wpd_expense_attachments ?>" name="_wpd_expense_attachments" class="wpd-media-upload">
							<button type="button" class="button button-primary wpd-media-upload-button">Manage Attachments</button>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php if( is_array($facebook_api_data) && ! empty($facebook_api_data) ) : ?>
			<!-- Facebook API Data -->
			<div class="wpd-wrapper">
				<table class="wpd-table widefat fixed">
					<thead>
						<tr>
							<th colspan="2"><?php _e( 'Facebook API Data', 'wpd-alpha-insights' ) ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>Account Name</td>
							<td><?php echo $facebook_api_data['account_name'] ?></td>
						</tr>
						<tr>
							<td>Account ID</td>
							<td><?php echo $facebook_api_data['account_id'] ?></td>
						</tr>
						<tr>
							<td>Last Updated</td>
							<td><?php echo ($facebook_api_data['last_updated_unix'] ) ? date('F j, Y h:i:s', $facebook_api_data['last_updated_unix']) : 'N/A'; ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
		<?php if (is_array( $google_api_account_data ) && ! empty( $google_api_account_data )) : ?>
			<!-- Google Ads API Data -->
			<div class="wpd-wrapper">
				<table class="wpd-table widefat fixed">
					<thead>
						<tr>
							<th colspan="2"><?php _e( 'Google API Data', 'wpd-alpha-insights' ) ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach( $google_api_account_data as $key => $value ) : ?>
							<tr>
								<th><?php echo wpd_clean_string($key); ?></th>
								<td><?php echo $value; ?></th>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
		<?php if( is_array($google_api_data) && ! empty($google_api_data) ) : ?>
			<?php foreach( $google_api_data as $campaigns_analytics_on_day ) : ?>
				<div class="wpd-wrapper">
					<table class="wpd-table widefat fixed">
						<thead>
							<tr>
								<th colspan="2"><?php echo $campaigns_analytics_on_day['campaign_name'] ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<th>Campaign Status</th>
								<td><?php echo $campaigns_analytics_on_day['campaign_status'] ?></td>
							</tr>
							<tr>
								<th>Campaign ID</th>
								<td><?php echo $campaigns_analytics_on_day['campaign_id'] ?></td>
							</tr>
							<tr>
								<th>Campaign Spend<div class="wpd-meta">On This Day</div></th>
								<td><?php echo wc_price( $campaigns_analytics_on_day['cost'] ) ?> (<?php echo $campaigns_analytics_on_day['reporting_currency']; ?>)</td>
							</tr>
						</tbody>
					</table>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
		<script type="text/javascript">
			document.getElementById('_wpd_recurring_expense_enabled').addEventListener('change', function() {
			  if ( this.value == 1 ) {

			  	// If we are using recurring expense
			  	var all = document.getElementsByClassName('recurring-expense-hide');
				for (var i = 0; i < all.length; i++) {
				  all[i].style.display = 'none';
				}
				var all = document.getElementsByClassName('recurring-expense-show');
				for (var i = 0; i < all.length; i++) {
				  all[i].style.display = 'table-row';
				}

			  } else {

			  	// Not recurring expense
			  	var all = document.getElementsByClassName('recurring-expense-hide');
				for (var i = 0; i < all.length; i++) {
				  all[i].style.display = 'table-row';
				}
				var all = document.getElementsByClassName('recurring-expense-show');
				for (var i = 0; i < all.length; i++) {
				  all[i].style.display = 'none';
				}

			  }

			});

			jQuery(document).ready(function($) {

				$(document).on('click', '.wpd-media-upload-button', function(e) {

					// Prevent Default
					e.preventDefault();

					// If the media frame already exists, reopen it.
					if ( file_frame ) {
						file_frame.open();
						return;
					}

					// Get the button to find the correct input
					var button = $(this);
					var input_field = button.siblings( '.wpd-media-upload' );

					
					// Get already selected items and load them into the frame
					var currentSelections = input_field.val() + "";
					if ( currentSelections.length === 0 ) {
						currentSelections = [];
					} else {
						currentSelections = currentSelections.split( "," );
					}

					// Init frame
					var file_frame = wp.media({
						title: 'Select or Upload PDFs or Images',
						button: {
							text: 'Select Evidence'
						},
						multiple: 'add'
					});

					// if you have IDs of previously selected files you can set them checked
					file_frame.on('open', function() {

						let selection = file_frame.state().get('selection');

						if ( currentSelections.length !== 0 ) {

							let ids = currentSelections; // array of IDs of previously selected files. You're gonna build it dynamically

							ids.forEach(function(id) {
								let attachment = wp.media.attachment(id);
								selection.add(attachment ? [attachment] : []);
							}); // would be probably a good idea to check if it is indeed a non-empty array

						}

					});

					// On selection
					file_frame.on( 'select', function() {
						
						// Get selections
						var target_ids = [];
						var selection = file_frame.state().get('selection');

						// Clear current previews
						$('.wpd-attachment-media').empty();

						// Map them to build ID array
						selection.map( function( attachment ) {

							// Store the attachment IDS in one var
							attachment = attachment.toJSON();
							target_ids.push( attachment.id );

							// Rebuild the previews
							let url = attachment.url;
							let attachment_id = attachment.id;
							let attachmentIcon = attachment.icon;
							let thumbnail = '';

							// In case there's no thumbnail for this
							if ( attachment.hasOwnProperty('sizes') ) {
								thumbnail = attachment.sizes.thumbnail.url;
							} else {
								thumbnail = attachmentIcon;
							}

							// HTML string
							let removeHtml = '<div class="wpd-remove-media" data-attachment-id="' + attachment_id + '"><span class="dashicons dashicons-no-alt"></span></div>';
							let fileName = 	'<div class="wpd-media-file-name">' + attachment.filename + '</div>';
							let htmlOutput = '<a href="' + url + '" target="_blank" class="wpd-attachment-preview" style="background-image: url( ' + thumbnail + ' );">' + removeHtml + fileName + '</a>';
							$('.wpd-attachment-media').append(htmlOutput);

						});

						// Turn array into comma seperated string
						target_ids = target_ids.join(',');

						// Update Input value
						input_field.val(target_ids);

						// Set the data vals in the wrapper
						$('.wpd-attachment-media').attr('data-attachment-ids', target_ids);

					});

					file_frame.open();

				});

				$(document).on( 'click', '.wpd-remove-media', function(e) {

					// Prevent Default
					e.preventDefault();

					// Get the data id
					let attachment_id = $(this).data('attachment-id');

					// Remove the parent HTML
					$(this).parent('.wpd-attachment-preview').remove();

					// Update the input value
					var currentSelections = $('.wpd-media-upload').val() + "";

					// If there's nothing left?
					if ( currentSelections.length === 0 ) {

						currentSelections = [];
						target_ids = '';

					} else {

						currentSelections = currentSelections.split( "," );

						// Remove this array value
						var updatedSelection = currentSelections.filter(function(elem){
							return elem != attachment_id; 
						});

						// Rebuild our string
						target_ids = updatedSelection.join(',');

					}


					// Update Input value
					$('.wpd-media-upload').val(target_ids);

				});

			});
		</script>
		<?php

	}

	/** 
	 *
	 *	Process data on save
	 *	
	 */
	public function save_expense_data() {

		global $post;

		if ( isset($_POST["_wpd_paid"]) ) {
			$paid = wpd_numbers_only( $_POST["_wpd_paid"] );
			update_post_meta( $post->ID, "_wpd_paid", $paid );
		}
		if ( isset($_POST["_wpd_amount_paid"]) ) {
			$amount_paid = wc_format_decimal( $_POST["_wpd_amount_paid"] );
			update_post_meta( $post->ID, "_wpd_amount_paid", $amount_paid );
		}
		if ( isset($_POST["_wpd_date_paid"]) ) {
			$date_paid = sanitize_text_field( $_POST["_wpd_date_paid"] );
			update_post_meta( $post->ID, "_wpd_date_paid", $date_paid );
		}
		if ( isset($_POST["_wpd_date_invoiced"]) ) {
			$date_invoiced = sanitize_text_field( $_POST["_wpd_date_invoiced"] );
			update_post_meta( $post->ID, "_wpd_date_invoiced", $date_invoiced );
		}
		if ( isset($_POST["_wpd_expense_reference"]) ) {
			$expense_reference = sanitize_text_field( $_POST["_wpd_expense_reference"] );
			update_post_meta( $post->ID, "_wpd_expense_reference", $expense_reference );
		}
		if ( isset($_POST["_wpd_amount_paid_currency"]) ) {
			$amount_paid_currency = sanitize_text_field( $_POST["_wpd_amount_paid_currency"] );
			update_post_meta( $post->ID, "_wpd_amount_paid_currency", $amount_paid_currency );
		}
		// Recurring Expenses
		if ( isset($_POST["_wpd_recurring_expense_enabled"]) ) {
			$amount_paid_currency = sanitize_text_field( $_POST["_wpd_recurring_expense_enabled"] );
			update_post_meta( $post->ID, "_wpd_recurring_expense_enabled", $amount_paid_currency );
		}
		if ( isset($_POST["_wpd_recurring_expense_frequency"]) ) {
			$amount_paid_currency = sanitize_text_field( $_POST["_wpd_recurring_expense_frequency"] );
			update_post_meta( $post->ID, "_wpd_recurring_expense_frequency", $amount_paid_currency );
		}
		if ( isset($_POST["_wpd_recurring_expense_beginning_date"]) ) {
			$amount_paid_currency = sanitize_text_field( $_POST["_wpd_recurring_expense_beginning_date"] );
			update_post_meta( $post->ID, "_wpd_recurring_expense_beginning_date", $amount_paid_currency );
		}
		if ( isset($_POST["_wpd_recurring_expense_end_date"]) ) {
			$amount_paid_currency = sanitize_text_field( $_POST["_wpd_recurring_expense_end_date"] );
			update_post_meta( $post->ID, "_wpd_recurring_expense_end_date", $amount_paid_currency );
		}
		if ( isset($_POST["_wpd_expense_attachments"]) ) {
			$attachments = sanitize_text_field( $_POST["_wpd_expense_attachments"] );
			update_post_meta( $post->ID, "_wpd_expense_attachments", $attachments );
		}
		
	}

	/**
	 * 
	 * 	Outputs custom fields for supplier info
	 * 
	 **/
	public function supplier_taxonomy_custom_fields( $term_or_taxonomy ) {

		$term_id 				= ( property_exists($term_or_taxonomy, 'term_id') ) ? $term_or_taxonomy->term_id : 0;
		$supplier_tax_number 	= get_term_meta( $term_id, '_wpd_supplier_tax_number', true );
		$supplier_country_code 	= get_term_meta( $term_id, '_wpd_supplier_country', true );
		$empty_country 			= array( '' => 'Select a Country' );
		$available_countries 	= wpd_get_list_of_available_countries();
		$country_options 		= array_merge( $empty_country, $available_countries );

		?>
		<style type="text/css">
			table.form-table p.description {
				display: none;
			}
			tr.form-field.term-description-wrap {
				display:none;
			}
		</style>
		<tr class="form-field term-country-wrap">
			<th scope="row"><label for="description">Registered Tax Number</label></th>
			<td><input type="text" name="_wpd_supplier_tax_number" value="<?php echo $supplier_tax_number; ?>" id="supplier-tax-number" ></td>
		</tr>
		<tr class="form-field term-country-wrap">
			<th scope="row"><label for="description">Supplier Country</label></th>
			<td>
				<?php 
					woocommerce_form_field(
						'_wpd_supplier_country',
						array(
							'type' => 'select',
							'class' => array('wpd-supplier-country'),
							'placeholder' => 'Available Countries',
							'options' => $country_options
						),
						$supplier_country_code
					);
				?>
			</td>
		</tr>
		<?php

	}

	/**
	 * 	
	 * Handles posted data for the Supplier taxonomy
	 * 
	 **/
	public function save_supplier_data( $term_id, $term_taxonomy_id, $args ) {

		// Supplier Country
		if ( isset($_POST['_wpd_supplier_tax_number'] ) ) {
			$supplier_tax_number = sanitize_text_field( $_POST['_wpd_supplier_tax_number'] );
			update_term_meta( $term_id, '_wpd_supplier_tax_number', $supplier_tax_number );
		}

		// Supplier Country
		if ( isset($_POST['_wpd_supplier_country'] ) ) {
			$supplier_country = sanitize_text_field( $_POST['_wpd_supplier_country'] );
			update_term_meta( $term_id, '_wpd_supplier_country', $supplier_country );
		}

	}

	/**
	 * Registers post types needed by the plugin.
	 *
	 * @since  0.1.0
	 * @access public
	 * @return void
	 */
	public function register_expense_cpt() {

		/* Set up the arguments for the post type. */
		$args = array(

			/*
			 * A short description of what your post type is. As far as I know, this isn't used anywhere 
			 * in core WordPress.  However, themes may choose to display this on post type archives. 
			 */
			'description'         => __( 'Expense tracking for WP Davies Alpha Insights.', 'wpd-alpha-insights' ), // string

			/**
			 * Whether the post type should be used publicly via the admin or by front-end users.  This 
			 * argument is sort of a catchall for many of the following arguments.  I would focus more 
			 * on adjusting them to your liking than this argument.
			 *	@since  
			 */
			'public'              => false, // bool (default is FALSE)

			/*
			 * Whether queries can be performed on the front end as part of parse_request(). 
			 */
			'publicly_queryable'  => false, // bool (defaults to 'public'). <- stops it showing on frontend

			/*
			 * Whether to exclude posts with this post type from front end search results.
			 */
			'exclude_from_search' => true, // bool (defaults to FALSE - the default of 'internal')

			/*
			 * Whether individual post type items are available for selection in navigation menus. 
			 */
			'show_in_nav_menus'   => false, // bool (defaults to 'public')

			/*
			 * Whether to generate a default UI for managing this post type in the admin. You'll have 
			 * more control over what's shown in the admin with the other arguments.  To build your 
			 * own UI, set this to FALSE.
			 */
			'show_ui'             => true, // bool (defaults to 'public')

			/*
			 * Whether to show post type in the admin menu. 'show_ui' must be true for this to work. 
			 */
			'show_in_menu'        => false, // bool (defaults to 'show_ui')

			/*
			 * Whether to make this post type available in the WordPress admin bar. The admin bar adds 
			 * a link to add a new post type item.
			 */
			'show_in_admin_bar'   => true, // bool (defaults to 'show_in_menu')

			/*
			 * The position in the menu order the post type should appear. 'show_in_menu' must be true 
			 * for this to work.
			 */
			'menu_position'       => 6, // int (defaults to 25 - below comments)

			/*
			 * The URI to the icon to use for the admin menu item. There is no header icon argument, so 
			 * you'll need to use CSS to add one.
			 */
			'menu_icon'           => WPD_AI_URL_PATH . 'assets/img/Alpha-Insights-Icon-20x20.png', // string (defaults to use the post icon)

			/*
			 * Whether the posts of this post type can be exported via the WordPress import/export plugin 
			 * or a similar plugin. 
			 */
			'can_export'          => true, // bool (defaults to TRUE)

			/*
			 * Whether to delete posts of this type when deleting a user who has written posts. 
			 */
			'delete_with_user'    => false, // bool (defaults to TRUE if the post type supports 'author')

			/*
			 * Whether this post type should allow hierarchical (parent/child/grandchild/etc.) posts. 
			 */
			'hierarchical'        => false, // bool (defaults to FALSE)

			/* 
			 * Whether the post type has an index/archive/root page like the "page for posts" for regular 
			 * posts. If set to TRUE, the post type name will be used for the archive slug.  You can also 
			 * set this to a string to control the exact name of the archive slug.
			 */
			'has_archive'         => false, // bool|string (defaults to FALSE)

			/*
			 * Sets the query_var key for this post type. If set to TRUE, the post type name will be used. 
			 * You can also set this to a custom string to control the exact key.
			 */
			'query_var'           => true, // bool|string (defaults to TRUE - post type name)

			/*
			 * (array) (optional) An array of registered taxonomies like category or post_tag that will be used with this post type.
			 * This can be used in lieu of calling register_taxonomy_for_object_type() directly. Custom taxonomies still need to be registered with register_taxonomy() .
			 */
			'taxonomies'           => array('expense_category', 'suppliers'), // (array) (optional) - by slug

			/* 
			 * How the URL structure should be handled with this post type.  You can set this to an 
			 * array of specific arguments or true|false.  If set to FALSE, it will prevent rewrite 
			 * rules from being created.
			 */
			'rewrite' => array(

				/* The slug to use for individual posts of this type. */
				'slug'       => 'expense', // string (defaults to the post type name)

				/* Whether to show the $wp_rewrite->front slug in the permalink. */
				'with_front' => false, // bool (defaults to TRUE)

				/* Whether to allow single post pagination via the <!--nextpage--> quicktag. */
				'pages'      => true, // bool (defaults to TRUE)

				/* Whether to create pretty permalinks for feeds. */
				'feeds'      => true, // bool (defaults to the 'has_archive' argument)

				/* Assign an endpoint mask to this permalink. */
				'ep_mask'    => EP_PERMALINK, // const (defaults to EP_PERMALINK)
			),

			'supports' => array(

				'title',
				'author',

			),

			'labels' => array(

				'name'               => __( 'Expenses',                   'wpd-alpha-insights' ),
				'singular_name'      => __( 'Expense',                    'wpd-alpha-insights' ),
				'menu_name'          => __( 'Expenses',                   'wpd-alpha-insights' ),
				'name_admin_bar'     => __( 'Expenses',                   'wpd-alpha-insights' ),
				'add_new'            => __( 'Add New',                    'wpd-alpha-insights' ),
				'add_new_item'       => __( 'Add New Expense',            'wpd-alpha-insights' ),
				'edit_item'          => __( 'Edit Expense',               'wpd-alpha-insights' ),
				'new_item'           => __( 'New Expense',                'wpd-alpha-insights' ),
				'view_item'          => __( 'View Expense',               'wpd-alpha-insights' ),
				'search_items'       => __( 'Search Expenses',            'wpd-alpha-insights' ),
				'not_found'          => __( 'No Expenses found',          'wpd-alpha-insights' ),
				'not_found_in_trash' => __( 'No Expenses found in trash', 'wpd-alpha-insights' ),
				'all_items'          => __( 'All Expenses',               'wpd-alpha-insights' ),

				/* Labels for hierarchical post types only. */
				'parent_item'        => __( 'Parent Expense',             'wpd-alpha-insights' ),
				'parent_item_colon'  => __( 'Parent Expense:',            'wpd-alpha-insights' ),

				/* Custom archive label.  Must filter 'post_type_archive_title' to use. */
				'archive_title'      => __( 'Expenses',                   'wpd-alpha-insights' ),

			)

		);

		/* Register the post type. */
		register_post_type (

			'expense', // Post type name. Max of 20 characters. Uppercase and spaces not allowed.
			$args      // Arguments for post type.

		);

	}

	/**
	 *
	 *	Taxonomies for expense
	 *
	 */
	public function expense_taxonomies() {

	  $expense_type_labels = array(

	    'name'              => _x( 'Expense Category', 'taxonomy general name' ),
	    'singular_name'     => _x( 'Expense Category', 'taxonomy singular name' ),
	    'search_items'      => __( 'Search Expense Categories', 'wpd-alpha-insights' ),
	    'all_items'         => __( 'All Expense Categories', 'wpd-alpha-insights' ),
	    'parent_item'       => __( 'Parent Expense Categories', 'wpd-alpha-insights' ),
	    'parent_item_colon' => __( 'Parent Expense Category:', 'wpd-alpha-insights' ),
	    'edit_item'         => __( 'Edit Expense Category', 'wpd-alpha-insights' ), 
	    'update_item'       => __( 'Update Expense Category', 'wpd-alpha-insights' ),
	    'add_new_item'      => __( 'Add New Expense Category', 'wpd-alpha-insights' ),
	    'new_item_name'     => __( 'New Expense Category', 'wpd-alpha-insights' ),
	    'menu_name'         => __( 'Expense Categories', 'wpd-alpha-insights' ),
	    'not_found' 		=> __( 'No Expense Categories Found', 'wpd-alpha-insights' ),

	  );

	  $expense_type_args = array(

	    'labels' 				=> $expense_type_labels,
	    'public' 				=> true,
	    'publicly_queryable' 	=> false,
	    'show_ui' 				=> true,
	    'show_in_menu' 			=> true,
	    'show_admin_column' 	=> true,
	    'hierarchical' 			=> true,

	  );

	  register_taxonomy( 'expense_category', array( 'expense' ), $expense_type_args );

	  $supplier_labels = array(

	    'name'              	=> _x( 'Suppliers', 'taxonomy general name' ),
	    'singular_name'     	=> _x( 'Supplier', 'taxonomy singular name' ),
	    'search_items'      	=> __( 'Search Suppliers', 'wpd-alpha-insights' ),
	    'popular_items'     	=> __( 'Popular Suppliers', 'wpd-alpha-insights' ),
	    'parent_item'     		=> __( 'Parent Supplier', 'wpd-alpha-insights' ),
	    'parent_item_colon' 	=> __( 'Parent Suppliers:', 'wpd-alpha-insights' ),
	    'all_items'         	=> __( 'All Suppliers', 'wpd-alpha-insights' ),
	    'edit_item'         	=> __( 'Edit Supplier', 'wpd-alpha-insights' ), 
	    'view_item'         	=> __( 'View Supplier', 'wpd-alpha-insights' ), 
	    'update_item'       	=> __( 'Update Supplier', 'wpd-alpha-insights' ),
	    'add_new_item'      	=> __( 'Add New Supplier', 'wpd-alpha-insights' ),
	    'new_item_name'     	=> __( 'New Supplier', 'wpd-alpha-insights' ),
	    'menu_name'         	=> __( 'Suppliers', 'wpd-alpha-insights' ),
	    'not_found' 			=> __( 'No Suppliers Found', 'wpd-alpha-insights' ),
	    'filter_by_item' 		=> __( 'Filter By Supplier', 'wpd-alpha-insights' ),
	    'items_list_navigation' => __( 'Supplier List Navigation', 'wpd-alpha-insights' ),
	    'items_list' 			=> __( 'Suppliers List', 'wpd-alpha-insights' ),
	    'back_to_items' 		=> __( 'Go Back To Suppliers', 'wpd-alpha-insights' ),
	    'item_link' 			=> __( 'Supplier Link', 'wpd-alpha-insights' ),
	    'item_link_description' => __( 'A link to a supplier', 'wpd-alpha-insights' ),

	  );

	  $supplier_args = array(

	    'labels' 				=> $supplier_labels,
	    'public' 				=> true,
	    'publicly_queryable' 	=> false,
	    'show_ui' 				=> true,
	    'show_in_menu' 			=> true,
	    'show_admin_column' 	=> true,
	    'hierarchical' 			=> true,

	  );
	  
	  register_taxonomy( 'suppliers', array( 'expense', 'product' ), $supplier_args );

	}

	/**
	 *
	 *	Set sort order
	 *
	 */
	function sort_expense_columns( $query ) {

		if ( $query->is_main_query() ) {

			// wpd_debug( $query );

		}

		// Only apply these settings to this page
		$post_type = ( isset($query->query['post_type']) ) ? $query->query['post_type'] : '';

		if ( is_admin() && $query->is_main_query() && $post_type === 'expense' ) {
	
			// Set the default
			if ( empty( $query->query_vars['orderby'] ) ) {

				$query->set( 'order', 'desc' );
				$query->set( 'orderby', 'meta_value' );
				$query->set( 'meta_key', '_wpd_date_paid' );
				$query->set( 'meta_type', 'DATE' );

			} elseif ( 'date_paid' === $query->get( 'orderby') ) {

				$query->set( 'orderby', 'meta_value' );
				$query->set( 'meta_key', '_wpd_date_paid' );
				$query->set( 'meta_type', 'DATE' );

			}
			
		} else {

			// This is for other posts, do nothing.

		}


	}

	/** 
	 *
	 *	Set sortable columns
	 *
	 */
	function set_expense_sortable_columns( $columns ) {

		$columns['date_paid'] = 'date_paid';

		return $columns;

	}

	/**
	 *
	 *	Add the custom columns to the book post type:
	 *
	 */
	public function register_expense_column($columns) {

	    $columns['amount_paid'] 	= __( 'Amount Paid', 'wpd-alpha-insights' );
	    $columns['currency_paid'] 	= __( 'Currency', 'wpd-alpha-insights' );
	    $columns['reference_no'] 	= __( 'Reference ID', 'wpd-alpha-insights' );
	    $columns['date_paid'] 		= __( 'Date Paid', 'wpd-alpha-insights' );
	    $columns['date'] 			= __( 'Date Created', 'wpd-alpha-insights' );

	    if ( isset($columns['author']) ) {

	    	unset($columns['author']);

	    }

	    return $columns;

	}

	/**
	 *
	 *	Add the data to the custom columns for the book post type:
	 *
	 */
	public function expense_column_data( $column, $post_id ) {

	    switch ( $column ) {

	        case 'amount_paid' :

	        	$wpd_amount_paid = get_post_meta( $post_id, '_wpd_amount_paid', true );
	        	echo $wpd_amount_paid;
	            break;

	        case 'currency_paid' :

	        	$wpd_amount_paid_currency = get_post_meta( $post_id, '_wpd_amount_paid_currency', true );
	            echo $wpd_amount_paid_currency; 
	            break;

	        case 'date_paid' :

	        	$wpd_date_paid 	= get_post_meta($post_id, '_wpd_date_paid', true );
	        	$wpd_date_paid 	= date( 'F j, Y', strtotime($wpd_date_paid) );
	            echo $wpd_date_paid; 
	            break;

	        case 'reference_no' :

	        	$wpd_expense_reference = get_post_meta( $post_id, '_wpd_expense_reference', true );
	            echo $wpd_expense_reference; 
	            break;

	    }

	}

}

/**
 *
 *	Initialize
 *
 */
new WPD_Expense_Tracking_CPT();