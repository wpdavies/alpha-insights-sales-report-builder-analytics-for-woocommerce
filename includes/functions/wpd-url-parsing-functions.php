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
function wpdai_parse_query_params( $url ) {

    parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $query_params );

    return $query_params;

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
function wpdai_strip_query_parameters_from_url( $url ) {

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
function wpdai_get_query_params( $url ) {

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
 * Query params that identify paid / campaign attribution.
 *
 * @return array<int, string>
 */
function wpdai_get_attribution_tracking_params() {
	return array(
		'gclid',
		'gbraid',
		'wbraid',
		'dclid',
		'fbclid',
		'msclkid',
		'ttclid',
		'li_fat_id',
		'srsltid',
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
		'google_cid',
		'meta_cid',
		'gad_source',
		'gad_campaignid',
		'ref',
		'source',
		'referrer',
		'referer',
	);
}

/**
 * Whether a URL includes campaign or click-id query params.
 *
 * @param string $url URL to inspect.
 * @return bool
 */
function wpdai_url_has_tracking_params( $url ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return false;
	}

	$query_params = wpdai_get_query_params( $url );
	if ( empty( $query_params ) ) {
		return false;
	}

	foreach ( wpdai_get_attribution_tracking_params() as $param ) {
		if ( isset( $query_params[ $param ] ) && '' !== (string) $query_params[ $param ] ) {
			return true;
		}
	}

	return false;
}

/**
 * Decode and validate an attribution URL without using sanitize_text_field().
 *
 * sanitize_text_field() strips %XX sequences and can destroy encoded landing pages.
 *
 * @param string $url Raw URL.
 * @return string
 */
function wpdai_sanitize_attribution_url( $url ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return '';
	}

	$url = trim( wp_unslash( $url ) );
	if ( '' === $url ) {
		return '';
	}

	$decoded = rawurldecode( $url );
	if ( $decoded !== $url ) {
		$url    = $decoded;
		$double = rawurldecode( $url );
		if ( $double !== $url && filter_var( $double, FILTER_VALIDATE_URL ) ) {
			$url = $double;
		}
	}

	$url = htmlspecialchars_decode( $url );
	$url = filter_var( $url, FILTER_SANITIZE_URL );
	if ( ! is_string( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
		return '';
	}

	$lower = strtolower( $url );
	if ( str_contains( $lower, 'wp-admin' ) || str_contains( $lower, 'wp-login' ) || str_contains( $lower, 'admin-ajax' ) ) {
		return '';
	}

	if ( wpdai_is_checkout_like_url( $url ) && ! wpdai_url_has_tracking_params( $url ) ) {
		return '';
	}

	return esc_url_raw( $url );
}

/**
 * Whether a URL is cart, checkout, thank-you, or a WooCommerce AJAX endpoint.
 *
 * These should not become a session landing page unless they carry campaign tags.
 *
 * @param string $url URL to inspect.
 * @return bool
 */
function wpdai_is_checkout_like_url( $url ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return false;
	}

	$url   = htmlspecialchars_decode( $url );
	$path  = strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	$query = strtolower( (string) wp_parse_url( $url, PHP_URL_QUERY ) );

	if ( str_contains( $query, 'wc-ajax=' ) ) {
		return true;
	}

	if ( preg_match( '#/(checkout|cart)(/|$)#', $path ) ) {
		return true;
	}

	if ( str_contains( $path, 'order-received' ) || str_contains( $path, 'order-pay' ) ) {
		return true;
	}

	return false;
}

/**
 * Whether a URL is a WooCommerce AJAX, admin-ajax, or REST endpoint.
 *
 * @param string $url URL to inspect.
 * @return bool
 */
function wpdai_is_ajax_or_rest_url( $url ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return false;
	}

	$url   = htmlspecialchars_decode( $url );
	$path  = strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	$query = strtolower( (string) wp_parse_url( $url, PHP_URL_QUERY ) );

	if ( str_contains( $query, 'wc-ajax=' ) || str_contains( $query, 'rest_route=' ) ) {
		return true;
	}

	if ( str_contains( $path, 'admin-ajax.php' ) || str_contains( $path, '/wp-json/' ) ) {
		return true;
	}

	return false;
}

/**
 * Same-site browser referrer, including internal pages. AJAX/REST URLs are skipped.
 *
 * @return string
 */
function wpdai_get_same_site_referer_url() {
	$candidates = array();

	if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
		$candidates[] = wp_unslash( $_SERVER['HTTP_REFERER'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via esc_url_raw() below.
	}

	if ( function_exists( 'wp_get_raw_referer' ) ) {
		$raw = wp_get_raw_referer();
		if ( is_string( $raw ) && '' !== $raw ) {
			$candidates[] = $raw;
		}
	}

	$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
	$site_host = strtolower( (string) preg_replace( '/^www\./', '', (string) $site_host ) );

	foreach ( $candidates as $candidate ) {
		$url = esc_url_raw( $candidate );
		if ( '' === $url || wpdai_is_ajax_or_rest_url( $url ) ) {
			continue;
		}

		$host = strtolower( (string) preg_replace( '/^www\./', '', (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
		if ( '' !== $host && $host === $site_host ) {
			return $url;
		}
	}

	return '';
}

/**
 * Page the visitor was on when an AJAX/REST event fired.
 *
 * @param string $fallback   Current request URL, used only if it is not AJAX/REST.
 * @param int    $product_id Optional product permalink fallback.
 * @return string
 */
function wpdai_get_event_origin_url( $fallback = '', $product_id = 0 ) {
	$referer = wpdai_get_same_site_referer_url();
	if ( '' !== $referer ) {
		return $referer;
	}

	if ( is_string( $fallback ) && '' !== $fallback && ! wpdai_is_ajax_or_rest_url( $fallback ) ) {
		return esc_url_raw( $fallback );
	}

	$product_id = (int) $product_id;
	if ( $product_id > 0 && function_exists( 'get_permalink' ) ) {
		$permalink = get_permalink( $product_id );
		if ( is_string( $permalink ) && '' !== $permalink ) {
			return esc_url_raw( $permalink );
		}
	}

	return '';
}

/**
 * First non-empty URL, preferring one that still has tracking params.
 *
 * @param array<int, string> $urls Candidate URLs.
 * @return string
 */
function wpdai_select_attribution_url( $urls ) {
	$first = '';

	if ( ! is_array( $urls ) ) {
		return '';
	}

	foreach ( $urls as $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			continue;
		}

		$sanitized = wpdai_sanitize_attribution_url( $url );
		if ( '' === $sanitized ) {
			continue;
		}

		if ( '' === $first ) {
			$first = $sanitized;
		}

		if ( wpdai_url_has_tracking_params( $sanitized ) ) {
			return $sanitized;
		}
	}

	return $first;
}

/**
 * Keep the first landing page. Upgrade only from untagged → tagged.
 *
 * @param string $existing Already stored landing page.
 * @param string $incoming Newly resolved landing page.
 * @return string
 */
function wpdai_choose_first_touch_landing_page( $existing, $incoming ) {
	$existing = is_string( $existing ) ? wpdai_sanitize_attribution_url( $existing ) : '';
	$incoming = is_string( $incoming ) ? wpdai_sanitize_attribution_url( $incoming ) : '';

	if ( '' === $existing ) {
		return $incoming;
	}

	if ( '' === $incoming ) {
		return $existing;
	}

	if ( wpdai_url_has_tracking_params( $existing ) ) {
		return $existing;
	}

	if ( wpdai_url_has_tracking_params( $incoming ) ) {
		return $incoming;
	}

	return $existing;
}

/**
 * Keep the first external referral. Never replace a stored referrer with empty.
 *
 * @param string $existing Already stored referral URL.
 * @param string $incoming Newly resolved referral URL.
 * @return string
 */
function wpdai_choose_first_touch_referral_url( $existing, $incoming ) {
	$existing = is_string( $existing ) ? trim( $existing ) : '';
	$incoming = is_string( $incoming ) ? trim( $incoming ) : '';

	if ( '' !== $existing ) {
		return $existing;
	}

	return $incoming;
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
function wpdai_get_current_url_path_raw() {

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
function wpdai_get_current_url_raw() {

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
function wpdai_get_referral_url_raw() {

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

/**
 * Whether a URL is the WooCommerce order-received (thank you) page.
 *
 * @param string $url Page URL.
 * @return bool
 */
function wpdai_is_order_received_url( $url ) {
	if ( ! is_string( $url ) || '' === trim( $url ) ) {
		return false;
	}

	$url      = htmlspecialchars_decode( $url );
	$endpoint = 'order-received';

	if ( function_exists( 'wc_get_page_id' ) ) {
		$endpoint = (string) get_option( 'woocommerce_checkout_order_received_endpoint', 'order-received' );
	}

	$endpoint = sanitize_title( $endpoint );
	if ( '' === $endpoint ) {
		$endpoint = 'order-received';
	}

	$query = wpdai_parse_query_params( $url );
	if ( isset( $query[ $endpoint ] ) && '' !== (string) $query[ $endpoint ] ) {
		return true;
	}

	$path    = (string) wp_parse_url( $url, PHP_URL_PATH );
	$pattern = '#/' . preg_quote( $endpoint, '#' ) . '(?:/|$)#';

	return (bool) preg_match( $pattern, $path );
}