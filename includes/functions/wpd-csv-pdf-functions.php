<?php
/**
 *
 * CSV Related Functions
 *
 * @package Alpha Insights
 * @version 5.0.0
 * @since 5.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 *
 *	Prepare CSV Data
 *
 */
if ( ! function_exists( 'wpd_prepare_csv_data' ) ) {

	function wpd_prepare_csv_data( $data, $target_fields ) {

		$row_number = 0;
		$csv_results = array();

		/**
		 *
		 *	Store Header Rows
		 *
		 */
		foreach( $target_fields as $key => $value ) {

			$csv_results[$row_number][] = $value;

		}

		/**
		 *
		 *	Store CSV Rows
		 *
		 */
		foreach( $data as $data_row ) {

			$row_number++;

			foreach( $target_fields as $key => $value ) {

				$csv_results[$row_number][] = $data_row[$key];

			}

		}

		return $csv_results;

	}

}

/**
 *
 *	create csv
 *
 */
if ( ! function_exists( 'wpd_create_csv_file' ) ) {

	function wpd_create_csv_file( $file_name, $data ) {

		$start 						= microtime( true );
		$response 					= array();
		$system_path 				= WPD_AI_CSV_SYSTEM_PATH;
	    $output 					= fopen( $system_path . $file_name, "w");
	    $i 							= 0;
	    $success 					= true;
		$response['file_type'] 		= 'CSV';
		$error_message 				= array();

	    if ( ! $output ) {

			$error_message['file-creation-failure'] = __( 'Failed to create the CSV file, check to make sure folder permissions are okay.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
			$success = false;

	    }

	    /**
	     *
	     *	Set headers
	     *
	     */
	    // header('Content-type: text/csv');
	    // header("Content-Encoding: UTF-8");
	    // header('Content-Disposition: attachment; filename="' . $file_name . '"');
	    // header('Pragma: no-cache');
	    // header('Expires: 0');

	    /**
	     *
	     *	Fill in rows with data
	     *
	     */
	    foreach( $data as $row ) {

		   	$write_csv = fputcsv( $output, $row );  //output the user info line to the csv file

		     if ( ! $write_csv ) {

		    	$error_message['write-failure'] = __( 'Failed to write CSV, check to make sure folder permissions are okay.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
				$success = false;

		    } else {

		    	$i++;

		    }

	    }

	    /**
	     *
	     *	Send fail if 0 or 1 rows are written
	     *
	     */
	    if ( $i === 0 || $i === 1 ) {

	    	$error_message['data-failure'] = __( 'We couldn\'t find any data for the given range. Please check your filter and try again.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
			$success = false;

	    }

	 	/**
	 	 *
	 	 *	Close output
	 	 *
	 	 */
	    fclose( $output ); 

	    /**
	     *
	     *	Store results
	     *
	     */
	    $download_link 				= WPD_AI_CSV_PATH . $file_name;  //make a link to the file so the user can download.
		$finish 					= microtime( true );
		$execution_time 			= $finish - $start;
		$response = array(

			'execution_time' => $execution_time,
			'download_link'  => $download_link,
			'error_messages' => $error_message,
			'rows_found' 	 => $i,
			'success' 		 => $success,

		);

		return $response;

	}

}

/**
 *
 *	Convert CSV to array
 *
 */
function wpd_csv_to_array( $file ) {

	$row = 0;
	$data_return = array();

	// Open the file
	if ( ($handle = fopen($file, "r")) !== FALSE ) {

		// This will loop through an array of row data
	  while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

		// Count of rows
	    $count_of_rows = count( $data );

		// Store the header row
	    if ( $row === 0 ) $header_row = $data;

		// Loop through the data in each row
	    for ( $data_index = 0; $data_index < $count_of_rows; $data_index++ ) {

			// Skip header row
	    	if ($row === 0) continue;

			// If an object has more data points than headers, our data is bad and we'll skip it, can't be useful used anyway
			if ( ! isset($header_row[$data_index]) ) continue;

			// Setup undefined vars
			if ( ! isset( $data_return[$row] ) ) $data_return[$row] = array();
			if ( ! isset( $data_return[$row][$header_row[$data_index]] ) ) $data_return[$row][$header_row[$data_index]] = null;

			// Store data
	    	$data_return[$row][$header_row[$data_index]] = $data[$data_index];

	    }

	    $row++;

	  }

	  fclose( $handle );

	}

	return $data_return;

}


/**
 * Generate PDF from report slug using headless browser
 * 
 * This function creates a temporary live link for the specified report, generates a PDF via the 
 * wpdavies.dev API endpoint using puppeteer, saves it to the server, and then cleans up the 
 * temporary live link. The file name is automatically generated from the report name.
 * 
 * @param string $report_slug The slug of the report to generate PDF for
 * 
 * @return array Response array with success status, file paths, and any error messages
 *               Keys include: success, download_link, server_file, file_name, file_size, 
 *               file_type, error_messages, live_link_url
 * 
 * @since 4.7.0
 */
if ( ! function_exists( 'wpd_generate_pdf_from_report_slug' ) ) {

	function wpd_generate_pdf_from_report_slug( $report_slug ) {

		$response = array();
		$error_message = false;
		$temp_link_id = null;
		$success = false;
		$license_key = get_option( 'wpd_ai_api_key', null);
		$api_endpoint = 'https://wpdavies.dev/wp-json/wp-davies/v1/alpha-insights/generate/pdf';

		try {

			// Validate report slug
			if ( empty( $report_slug ) ) {
				throw new Exception( __('Report slug is required', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') );
			}

			// Validate license key
			if ( empty( $license_key ) ) {
				throw new Exception( __('You must have a valid license key to use this feature', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') );
			}

			// Get report configuration to extract the report name
			$report_config = get_option( 'wpd_dashboard_config_' . $report_slug );
			
			if ( ! $report_config ) {
				throw new Exception( __('Report not found', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') );
			}

			// Extract report name and generate file name
			$report_name = isset( $report_config['name'] ) ? $report_config['name'] : 'Alpha Insights Report';
			$date_time_stamp = current_time( 'Y-m-d-H-i-s' );
			
			// Sanitize the report name for use in filename
			$sanitized_report_name = sanitize_title( $report_name );
			$file_name = $sanitized_report_name . '-' . $date_time_stamp;

			// Create temporary live link
			$react_report = new WPD_React_Report( $report_slug );
			$temp_link_name = 'PDF Generation Link - ' . current_time('Y-m-d H:i:s');
			
			// Set expiry to 1 hour from now
			$expiry_datetime = new DateTime('now', new DateTimeZone(wp_timezone_string()));
			$expiry_datetime->modify('+1 hour');
			$expiry_date = $expiry_datetime->format('Y-m-d H:i:s');
			
			// Create the link
			$link_creation_result = $react_report->create_live_share_link(
				$report_slug,
				$temp_link_name,
				$expiry_date,
				null, // no password
				false // password not required
			);

			// Check if link creation was successful
			if ( ! isset( $link_creation_result['success'] ) || ! $link_creation_result['success'] ) {
				throw new Exception( 
					isset( $link_creation_result['message'] ) 
						? $link_creation_result['message'] 
						: __('Failed to create temporary live link', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') 
				);
			}

			// Extract link data
			$temp_link_data = $link_creation_result['data']['link'];
			$temp_link_id = $temp_link_data['id'];
			$secret_key = $temp_link_data['secret_key'];

			// Build the live link URL
			$site_url = get_site_url();
			$live_link_url = $site_url . '/alpha-insights/reports/' . $report_slug . '/?secret_key=' . $secret_key;

			// Build API request URL with query parameters
			$api_url = add_query_arg(
				array(
					'license_key' => $license_key,
					'url' => urlencode( $live_link_url ),
					'file_name' => urlencode( $file_name )
				),
				$api_endpoint
			);

			// Make the API request to generate PDF
			$api_response = wp_remote_post( $api_url, array(
				'timeout' => 60, // 2 minutes timeout for PDF generation
				'headers' => array(
					'Content-Type' => 'application/json'
				),
			));

			// Check for WP_Error
			if ( is_wp_error( $api_response ) ) {
				throw new Exception( 
					__('API request failed: ', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') . $api_response->get_error_message() 
				);
			}

			// Get response code and body
			$response_code = wp_remote_retrieve_response_code( $api_response );
			$response_body = wp_remote_retrieve_body( $api_response );

			// Check for non-200 response
			if ( $response_code !== 200 ) {
				$error_data = json_decode( $response_body, true );
				$error_msg = isset( $error_data['error_message'] ) 
					? $error_data['error_message'] 
					: __('PDF generation API returned error', 'alpha-insights-sales-report-builder-analytics-for-woocommerce');
				throw new Exception( $error_msg );
			}

			// Decode the JSON response
			$pdf_data = json_decode( $response_body, true );

			// Validate response structure
			if ( ! isset( $pdf_data['success'] ) || ! $pdf_data['success'] ) {
				throw new Exception( 
					isset( $pdf_data['error_message'] ) 
						? $pdf_data['error_message'] 
						: __('PDF generation failed', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') 
				);
			}

			if ( empty( $pdf_data['pdf_base64'] ) ) {
				throw new Exception( __('PDF data is missing from API response', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') );
			}

			// Decode the base64 PDF
			$pdf_binary = base64_decode( $pdf_data['pdf_base64'] );

			if ( $pdf_binary === false ) {
				throw new Exception( __('Failed to decode PDF data', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') );
			}

			// Prepare file paths
			$server_directory = WPD_AI_UPLOADS_FOLDER_SYSTEM . 'exports/pdf_files';
			$public_directory = WPD_AI_UPLOADS_FOLDER . 'exports/pdf_files';
			
			// Sanitize and ensure the file name from API has .pdf extension
			$final_file_name = isset( $pdf_data['file_name'] ) ? sanitize_file_name( $pdf_data['file_name'] ) : sanitize_file_name( $file_name . '.pdf' );
			if ( substr( $final_file_name, -4 ) !== '.pdf' ) {
				$final_file_name .= '.pdf';
			}
			
			// Additional security: remove any path components
			$final_file_name = basename( $final_file_name );

			$server_pdf_file = $server_directory . '/' . $final_file_name;
			$public_pdf_file = $public_directory . '/' . $final_file_name;

			// Ensure directory exists
			if ( ! file_exists( $server_directory ) ) {
				wp_mkdir_p( $server_directory );
			}

			// Save the PDF to the server
			$bytes_written = file_put_contents( $server_pdf_file, $pdf_binary );

			if ( $bytes_written === false ) {
				throw new Exception( __('Failed to save PDF file to server', 'alpha-insights-sales-report-builder-analytics-for-woocommerce') );
			}

			// Set proper file permissions
			chmod( $server_pdf_file, 0644 );

			$success = true;

			// Build success response
			$response = array(
				'download_link'  => $public_pdf_file,
				'server_file'    => $server_pdf_file,
				'error_messages' => false,
				'file_name'      => $final_file_name,
				'success'        => true,
				'file_type'      => 'PDF',
				'file_size'      => filesize( $server_pdf_file ),
				'live_link_url'  => $live_link_url, // for debugging
			);

		} catch ( Exception $e ) {

			$error_message = $e->getMessage();
			$success = false;

			$response = array(
				'download_link'  => null,
				'server_file'    => null,
				'error_messages' => $error_message,
				'file_name'      => null,
				'success'        => false,
				'file_type'      => 'PDF',
			);

		} finally {

			// Always clean up the temporary live link
			if ( ! empty( $temp_link_id ) ) {
				try {
					$react_report = new WPD_React_Report( $report_slug );
					$react_report->delete_live_share_link( $temp_link_id );
				} catch ( Exception $cleanup_error ) {
					// Log cleanup error but don't fail the whole operation
					if ( $success ) {
						// If PDF generation succeeded, just add a warning
						$response['cleanup_warning'] = __('Temporary live link could not be deleted', 'alpha-insights-sales-report-builder-analytics-for-woocommerce');
					}
				}
			}

		}

		return $response;

	}

}
