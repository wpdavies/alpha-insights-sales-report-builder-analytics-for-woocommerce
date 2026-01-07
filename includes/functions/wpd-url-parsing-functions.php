<?php
/**
 *
 * URL Parsing Related Functions
 * Typically used to parse URLs and get the query params or clean URLs
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
 *	Parse URL to check for Query params
 *	
 *	@return array key|value pair of query params
 *
 */
if ( ! function_exists( 'wpd_parse_query_params' ) ) {

	function wpd_parse_query_params( $url ) {

		parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $query_params );

		return $query_params;

	}

}

/**
 * 
 * 	Strips URL's & fragments from a url and returns everything else (the main domain)
 * 	e.g. https://wpdavies.dev
 * 
 * 	@param string $url The url to clean
 * 	@return string Returns the cleaned string
 * 
 **/
function wpd_strip_query_parameters_from_url( $url ) {

	$parsed_url = wp_parse_url( $url );
    
    // Reconstruct the URL without query parameters
    $clean_url = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
    $clean_url .= isset($parsed_url['host']) ? $parsed_url['host'] : '';
    $clean_url .= isset($parsed_url['path']) ? $parsed_url['path'] : '';
    
    return $clean_url;

}

/**
 *
 *	Collect URL Query Params
 *
 */
function wpd_get_query_params( $url ) {

	$query_params = array();

	if ( empty($url) ) {

		return $query_params;

	} else {
		
        $url = htmlspecialchars_decode( $url );
		$parsed_url = wp_parse_url( $url, PHP_URL_QUERY );
		if ( empty($parsed_url) ) {
			return $query_params;
		}
		parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $query_params );

	}

	return $query_params;

}

/**
 * 
 *	Returns the current URL path from $_SERVER unaltered by WP -> does not include domain
 *
 * 	Very useful for debugging when you need an unfiltered current URL
 *  to get an understanding of where the code is being executed.
 * 		
 * 	@return string $url Current URL path and all query params at that time of code execution or "/" if nothing found
 * 
 **/
function wpd_get_current_url_path_raw() {

	if ( isset($_SERVER['REQUEST_URI']) ) {

		return sanitize_text_field( $_SERVER['REQUEST_URI'] );

	} else {

		return '/';

	}

}

/**
 * 
 *	Returns the current URL including domain name from $_SERVER unaltered by WP -> does not include domain
 *
 * 	Very useful for debugging when you need an unfiltered current URL
 *  to get an understanding of where the code is being executed.
 * 		
 * 	@return string $url Current URL path and all query params at that time of code execution or "/" if nothing found
 * 
 **/
function wpd_get_current_url_raw() {

	$https_value = isset($_SERVER['HTTPS']) ? sanitize_text_field($_SERVER['HTTPS']) : '';
	$scheme = ( ! empty($https_value) && $https_value === 'on' ? "https" : "http");
	$host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field($_SERVER['HTTP_HOST']) : '';
	$uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
	$actual_link = $scheme . "://" . $host . $uri;

	return $actual_link;

}

/**
 * 
 *  Gets the referral URL, preferring WordPress-native wp_get_referer()
 * 
 *  Will return null if the referral URL is our own domain
 * 
 *  @return string|null The referral URL
 * 
 */
function wpd_get_referral_url_raw() {

    // Prefer WordPress-native referer
    $referral_url = wp_get_referer();

    // Fallback explicitly to HTTP_REFERER if needed
    if ( ! $referral_url && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
        $referral_url = $_SERVER['HTTP_REFERER'];
    }

    if ( empty( $referral_url ) ) {
        return null;
    }

    // Ensure it's clean
    $referral_url = esc_url_raw( $referral_url );

    // Parse domains
    $referring_domain = wp_parse_url( $referral_url, PHP_URL_HOST );
    $site_host        = wp_parse_url( site_url(), PHP_URL_HOST );

    // Normalize www
    $referring_domain = preg_replace( '/^www\./', '', $referring_domain );
    $site_host        = preg_replace( '/^www\./', '', $site_host );

    // Ignore internal referrals
    if ( $referring_domain === $site_host ) {
        return null;
    }

    return $referral_url;
}