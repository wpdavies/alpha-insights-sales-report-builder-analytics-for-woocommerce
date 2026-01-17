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
function wpdai_prepare_csv_data( $data, $target_fields ) {

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


/**
 *
 *	create csv
 *
 */
function wpdai_create_csv_file( $file_name, $data ) {

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

/**
 *
 *	Convert CSV to array
 *
 */
function wpdai_csv_to_array( $file ) {

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