<?php
/**
 *
 * Formatting Related Functions
 *
 * @package Alpha Insights
 * @version 3.2.0
 * @since 3.2.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 *
 *	Helps to convert strange number formats into a float
 * @example 1.351.216,40 = 1351216.40
 * @example 1,351,216.40 = 1351216.40
 *
 */
function wpd_float( $val ){

    $val = str_replace( ",",".", $val );
    $val = preg_replace( '/\.(?=.*\.)/', '', $val );

    return (float) $val;

}

/**
 *
 * 	Cleans up a key like stirng and transforms it into a nice looking phrase
 * 
 *	Removes "-" and "_" from a key, replacing with a space and capitalized each first word.
 *
 */
function wpd_clean_string( $string ) {

	return ucwords( str_replace( '-', ' ', str_replace( '_', ' ', $string ) ) );

}

/**
 *
 *	Sanitize URL
 * 	Might consider using sanitize_text_field() instead
 *
 */
function wpd_sanitize_url( $url ) {

	return strip_tags( stripslashes( filter_var($url, FILTER_VALIDATE_URL) ) );

}


/**
 *
 *	Check that this is a number
 *
 */
if ( ! function_exists('wpd_numbers_only') ) {

	function wpd_numbers_only( $number ) {

		if ( is_numeric($number) ) {

			return $number;

		} else {

			return 0;

		}

	}

}

/**
 *
 *	Strip query params from URL
 *
 */
function wpd_strip_params_from_url( $url ) {

	// Bugger off useless data
	if ( empty( $url ) || ! is_string( $url ) ) {

		return '';

	}

	// Get the base url by stripping off everything after these string characters
	$url = strtok( $url, '#' );	
	$url = strtok( $url, '?' );	

	// Make sure we always have a trailing slash for consistency
	if ( substr( $url , -1 ) != '/' ) {

	    $url = $url . '/';

	}

	return $url;

}