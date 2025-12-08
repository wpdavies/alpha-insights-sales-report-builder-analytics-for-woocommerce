<?php
/**
 *
 * Debugging Functions
 *
 * @package Alpha Insights
 * @version 2.2.0
 * @since 2.2.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 *
 *	Cleanly display print_r and optionally var_dump debug container with array or string
 *	
 *	@param array|string $data The data to display
 *	@param string $title The title to show in the container
 *	@param bool $var_dump Whether or not to var_dump, true to show additional details
 *
 */
function wpd_debug( $data, $title = false, $var_dump = false, $file = false ) {

	// Setup the heading
	$heading = ( $title !== false ) ? 'Debug Container - ' . $title : 'Debug Container';

	// Any changes from using a file
	if ( $file ) {

		// Get Data
		$file_data = wpd_get_debug_log_data( $file );

		// Delete log file
		$additional_elements = '<span class="wpd-delete-log" data-file="' . $file . '">Delete Log</span>';

		// Download log file
		if ( isset($file_data['file_url']) ) $additional_elements .= '<span class="wpd-download-log"><a href="' . $file_data['file_url'] . '" target="_blank" download>Download Log</a></span>';

		// Add file size to title
		if ( isset($file_data['file_size']) ) $heading .= ' (' . $file_data['file_size'] . ')'; 

	} 

	// Output
	ob_start(); ?>
	<div class="wpd-debug-container">
		<!-- Additional Elements -->
		<?php if ( isset( $additional_elements ) ) echo wp_kses_post( $additional_elements ); ?>
		<!-- Heading -->
		<h2><?php echo esc_html( $heading ); ?></h2>
		<!-- Debug Output -->
		<pre><?php ( $var_dump === true ) ? var_dump( $data ) : print_r( $data ); ?> </pre>
	</div>
	<?php 
	
	// Capture the HTML
	$html_output = ob_get_clean();

	// Output the HTML
	echo wp_kses_post( $html_output );

}

/**
 * 
 *	Returns Debug Log Directory including trailing slash 
 *	
 * 	@return string Full server log directory
 * 
 **/
function wpd_debug_log_directory() {

	$directory = WPD_AI_UPLOADS_FOLDER_SYSTEM . 'log/';
	$directory = str_replace( '\\', '/', $directory );
	
	return $directory;

}

/**
 * 
 *	Returns Debug Log URL including trailing slash 
 *	
 * 	@return string Server log URL for public access
 * 
 **/
function wpd_debug_log_url() {

	$directory = WPD_AI_UPLOADS_FOLDER . 'log/';
	$directory = str_replace( '\\', '/', $directory );
	
	return $directory;
}

/**
 * 
 *  Returns the size of a file optionally in human readable format or bytes
 * 
 *  @param string $file, the full server location of a file
 *  @param bool $human_readable_size, if set to true will return file size in KB, MB, GB, if set to false will return size in bytes
 * 
 **/
function wpd_get_file_size( $file, $human_readable = true ) {

	// No file found
	if ( ! file_exists($file) ) {
		return false;
	}

	$size = filesize($file);

	if ( $human_readable !== true ) {
		return (int) $size;
	}

    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $formattedSize = $size;

    for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
        $formattedSize = round($size, 2);
    }

    return $formattedSize . ' ' . $units[$i];

}

/**
 * 
 * 	Returns an array of data for the WP Davies Debug Logs
 * 
 * 	If no file is specificed, will return an array of all debug logs in the directory
 * 	Each array key contains the Title, the File Name, File Location & File Size
 * 
 * 	@param string|bool Expects the full file name location of the target file, otherwise if not set will search all available logs
 * 	@param bool $human_readable_size, if set to true will return file size in KB, MB, GB, if set to false will return size in bytes
 * 	@return array An associative array of log(s)
 * 
 **/
function wpd_get_debug_log_data( $file = false, $human_readable_size = true ) {

	// Return array
	$available_log_data = array();

	// If we've targeted a file, lets use that as our array of searches
	if ( $file ) {

		if ( file_exists($file) ) {

			$file_name = str_replace( wpd_debug_log_directory(), '', $file );
			$available_logs = array($file_name);

		} else {

			return $available_log_data;

		}

	} else {

		// Otherwise check for all Logs
		$available_logs = scandir( wpd_debug_log_directory() );

	}

	// Now lets collect data
	if ( is_array($available_logs) && ! empty($available_logs) ) {

		foreach( $available_logs as $index => $file_name ) {

			// Skip non text files
			if ( strpos($file_name, '.txt') === false ) continue;

			// Title
			$title = wpd_clean_string( str_replace( 'wpd_', '', str_replace( '.txt', '', $file_name) ) );
			$file_location = wpd_debug_log_directory() . $file_name;
			$file_size = ($human_readable_size) ? wpd_get_file_size($file_location) : wpd_get_file_size($file_location, false);
			$file_url = wpd_debug_log_url() . $file_name;

			// Array Push
			$log = array(
				'title' => $title,
				'file_name' => $file_name,
				'file_location' => $file_location,
				'file_url' => $file_url,
				'file_size' => $file_size
			);

			// Push onto array
			$available_log_data[] = $log;

		}

	}

	// If we've only got one result, make this an associative array
	// if ( is_array($available_log_data) && count($available_log_data) === 1 ) {
	// 	$available_log_data = $available_log_data[0];
	// }

	// Return results
	return $available_log_data;

}

/**
 * 	
 * 	Displays an error log
 * 	Includes the ability to delete the error log if you want
 * 	Will do nothing if we couldn't find the error log
 * 
 * 	@param string The full log name, not include .txt, excluding the directory. Required.
 * 	@param string $title The title of the log if you would like, otherwise it will remove non alphanumeric characters of the log
 * 	@return output Will echo the error log
 * 
 **/
function wpd_display_log( $log, $title = false ) {

	// Clean naming and try find file
	$log = str_replace( '.txt', '', $log );
	$file = WPD_AI_UPLOADS_FOLDER_SYSTEM . 'log/' . $log . '.txt';
	$file = str_replace( '\\', '/', $file );

	// Get Contents
	$file_contents = (file_exists($file)) ? file_get_contents( $file ) : 'Log is empty.';

	// Create Title
	$log_title = ( $title ) ? $title : wpd_clean_string( $log );

	// Output Container
	wpd_debug( $file_contents, $log_title, false, $file );

}

/**
 *
 *	Check memory loop 
 *	@return $peak_memory_usage if true, otherwise returns false
 *
 */
function wpd_is_memory_usage_greater_than( $percent = 90 ) {

    $peak_memory_usage 	= memory_get_peak_usage( true );
    $wp_memory_limit 	= wpd_get_memory_limit( true );
    $memory_usage 		= wpd_divide( $peak_memory_usage, $wp_memory_limit ) * 100;

    if ( $memory_usage > $percent ) {

        return $peak_memory_usage;

    } else {

        return false;

    }

}

/**
 * 
 * 	Gets the store memory limit and returns it in MB
 * 
 * 	@param bool Return in bytes, defaults to false. Will return MB if left on false, or bytes on true.
 * 
 */
function wpd_get_memory_limit( $return_bytes = false ) {
	
    $val 	= trim( ini_get('memory_limit') );
    $last 	= strtolower($val[strlen($val)-1]);
    $val 	= substr($val, 0, -1);

    switch($last) {
        // The 'G' modifier is available since PHP 5.1.0
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }

    if ( $return_bytes !== true ) $val = round( $val / 1024 / 1024, 2 );

    return $val;
}

/**
 * 
 * 	Returns max execution time for a the PHP script on this server in seconds
 * 
 **/
function wpd_get_php_max_execution_time() {

	return (int) ini_get( 'max_execution_time' );

}

/**
 * 
 *  Gets peak memory usage for script and returns it in MB
 * 
 **/
function wpd_get_peak_memory_usage() {

	return round( memory_get_peak_usage() / 1024 / 1024, 2);

}

/**
 * 
 * 	Debugging in Query Montior
 * 
 **/
function wpd_qm_debug( $debug ) {
	
	do_action('qm/debug', $debug);

}

/**
 * 
 * 	Deletes all log files larger in size then N megabytes
 * 
 * 	@return int Count of log files that were deleted
 * 
 **/
function wpd_delete_large_logs( $max_size_in_mb = 10 ) {

	// Get all logs
	$log_files = wpd_get_debug_log_data(false, false);

	// Iterate on each deletion
	$delete_count = 0;

	// Check data
	if ( is_array($log_files) && ! empty($log_files) ) {

		foreach( $log_files as $log_file ) {

			if ( ! is_array($log_file) ) continue;

			$log_file_location = $log_file['file_location'];
			$log_file_size_bytes = (int) $log_file['file_size'];
			$log_file_size_mb = $log_file_size_bytes / 1024 / 1024;

			// If larger than target MB
			if ( $log_file_size_mb > $max_size_in_mb ) {

				// Delete file
				if ( file_exists( $log_file_location ) ) {

					wp_delete_file( $log_file_location );
					$delete_count++;

				}

			}

		}

	}

	// Return total deleted count
	return $delete_count;

}