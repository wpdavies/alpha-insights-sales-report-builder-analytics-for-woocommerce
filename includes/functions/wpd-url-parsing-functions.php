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
 * 	Gets the referral url provided from $_SERVER["HTTP_REFERER"]
 * 
 * 	Will return null if the referral url is our own domain
 * 
 * 	@return string|null The HTTP Referer, if set
 * 
 **/
function wpd_get_referral_url_raw() {

    // Return HTTP Referer if found
    if ( isset($_SERVER["HTTP_REFERER"]) && ! empty($_SERVER["HTTP_REFERER"]) ) {

        // Capture raw referral URL and sanitize immediately
        $referral_url = sanitize_text_field( $_SERVER["HTTP_REFERER"] );

        // Basic sanitization — ensures it's a valid URL
        $referral_url = filter_var($referral_url, FILTER_SANITIZE_URL);

        // Try get domain from referral URL
        $referring_domain = wp_parse_url($referral_url, PHP_URL_HOST);

        // Get current site host
        $site_host = wp_parse_url(site_url(), PHP_URL_HOST);

        // Check if referral URL is our own domain, and return null if so
        if ( $referring_domain === $site_host ) {
            return null;
        }

        return esc_url_raw($referral_url);
    }

    // Else return null
    return null;
	
}