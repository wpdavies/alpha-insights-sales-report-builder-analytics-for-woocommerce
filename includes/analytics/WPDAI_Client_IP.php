<?php
/**
 * Visitor IP resolver for cache-safe analytics.
 *
 * WooCommerce's geolocation helper prefers X-Real-IP / X-Forwarded-For and
 * never reads CF-Connecting-IP. Behind Cloudflare or cloudflared that stores
 * a Cloudflare hop for every visitor. This resolver prefers Cloudflare's
 * original-client headers when the request actually arrived via a trusted proxy.
 * CF-Connecting-IP is kept even when it falls inside Cloudflare ranges (WARP).
 *
 * @package Alpha Insights
 */
defined( 'ABSPATH' ) || exit;

/**
 * Class WPDAI_Client_IP
 */
class WPDAI_Client_IP {

	/**
	 * Published Cloudflare anycast ranges (IPv4 + IPv6).
	 *
	 * @see https://www.cloudflare.com/ips/
	 * @var array<int, string>
	 */
	protected static $cloudflare_ip_ranges = array(
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);

	/**
	 * Resolve the originating client IP for the current request.
	 *
	 * @return string
	 */
	public static function resolve() {
		$remote_addr = self::get_server_ip( 'REMOTE_ADDR' );
		$trust_proxy = self::should_trust_proxy_headers( $remote_addr );

		$candidates = array();

		if ( $trust_proxy ) {
			$cf_connecting = self::get_server_ip( 'HTTP_CF_CONNECTING_IP' );
			if ( '' === $cf_connecting ) {
				$cf_connecting = self::get_server_ip( 'REDIRECT_HTTP_CF_CONNECTING_IP' );
			}

			$true_client = self::get_server_ip( 'HTTP_TRUE_CLIENT_IP' );
			$x_real      = self::get_server_ip( 'HTTP_X_REAL_IP' );
			$xff_client  = self::first_public_non_cloudflare_ip( self::get_forwarded_for_ips() );

			if ( self::is_valid_ip( $cf_connecting ) ) {
				$candidates[] = $cf_connecting;
			}
			if ( self::is_valid_ip( $true_client ) ) {
				$candidates[] = $true_client;
			}
			if ( self::is_usable_client_ip( $xff_client ) ) {
				$candidates[] = $xff_client;
			}
			if ( self::is_usable_client_ip( $x_real ) ) {
				$candidates[] = $x_real;
			}
		}

		if ( class_exists( 'WC_Geolocation' ) ) {
			$wc_ip = self::sanitize_ip( (string) WC_Geolocation::get_ip_address() );
			if ( $trust_proxy && self::is_usable_client_ip( $wc_ip ) ) {
				$candidates[] = $wc_ip;
			} elseif ( ! $trust_proxy && self::is_valid_ip( $wc_ip ) && ! self::is_valid_ip( $remote_addr ) ) {
				$candidates[] = $wc_ip;
			}
		}

		if ( self::is_valid_ip( $remote_addr ) ) {
			$candidates[] = $remote_addr;
		}

		$ip = '';
		foreach ( $candidates as $candidate ) {
			if ( self::is_valid_ip( $candidate ) ) {
				$ip = $candidate;
				break;
			}
		}

		/**
		 * Filter the resolved visitor IP for cache-safe analytics.
		 *
		 * @param string $ip          Resolved IP address.
		 * @param string $remote_addr REMOTE_ADDR for this request.
		 * @param bool   $trust_proxy Whether proxy headers were trusted.
		 */
		return (string) wpdai_apply_analytics_filter( 'wpd_ai_client_ip', 'wpd_ai_v2_client_ip', $ip, $remote_addr, $trust_proxy );
	}

	/**
	 * Whether forwarded / Cloudflare headers should be trusted for this request.
	 *
	 * @param string $remote_addr Connecting IP (REMOTE_ADDR).
	 * @return bool
	 */
	protected static function should_trust_proxy_headers( $remote_addr ) {
		$trust = false;

		if ( self::is_valid_ip( $remote_addr ) && ( ! self::is_public_ip( $remote_addr ) || self::is_cloudflare_ip( $remote_addr ) ) ) {
			$trust = true;
		}

		/**
		 * Filter whether analytics should trust Cloudflare / forwarded IP headers.
		 *
		 * @param bool   $trust       Whether to trust proxy headers.
		 * @param string $remote_addr Connecting IP (REMOTE_ADDR).
		 */
		return (bool) wpdai_apply_analytics_filter( 'wpd_ai_trust_proxy_headers', 'wpd_ai_v2_trust_proxy_headers', $trust, $remote_addr );
	}

	/**
	 * Public IP that is not a Cloudflare hop. Used for X-Forwarded-For / X-Real-IP
	 * chain walking. Do not use this for CF-Connecting-IP: WARP visitors legitimately
	 * have Cloudflare-ranged client IPs.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	protected static function is_usable_client_ip( $ip ) {
		return self::is_public_ip( $ip ) && ! self::is_cloudflare_ip( $ip );
	}

	/**
	 * @param string $key $_SERVER key.
	 * @return string
	 */
	protected static function get_server_ip( $key ) {
		if ( empty( $_SERVER[ $key ] ) || ! is_string( $_SERVER[ $key ] ) ) {
			return '';
		}

		return self::sanitize_ip( wp_unslash( $_SERVER[ $key ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * @return array<int, string>
	 */
	protected static function get_forwarded_for_ips() {
		if ( empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) || ! is_string( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			return array();
		}

		$raw   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		$ips   = array();
		$parts = explode( ',', $raw );

		foreach ( $parts as $part ) {
			$ip = self::sanitize_ip( $part );
			if ( '' !== $ip ) {
				$ips[] = $ip;
			}
		}

		return $ips;
	}

	/**
	 * @param array<int, string> $ips Forwarded IP list.
	 * @return string
	 */
	protected static function first_public_non_cloudflare_ip( $ips ) {
		if ( ! is_array( $ips ) ) {
			return '';
		}

		foreach ( $ips as $ip ) {
			if ( self::is_usable_client_ip( $ip ) ) {
				return $ip;
			}
		}

		return '';
	}

	/**
	 * @param string $value Raw IP header value.
	 * @return string
	 */
	protected static function sanitize_ip( $value ) {
		$value = trim( sanitize_text_field( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}

		if ( false !== strpos( $value, ',' ) ) {
			$parts = explode( ',', $value );
			$value = trim( (string) $parts[0] );
		}

		if ( preg_match( '/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $value, $matches ) ) {
			$value = $matches[1];
		} elseif ( preg_match( '/^\[([^\]]+)\](?::\d+)?$/', $value, $matches ) ) {
			$value = $matches[1];
		}

		$validated = filter_var( $value, FILTER_VALIDATE_IP );
		return false !== $validated ? $validated : '';
	}

	/**
	 * @param string $ip IP address.
	 * @return bool
	 */
	protected static function is_valid_ip( $ip ) {
		return '' !== $ip && false !== filter_var( $ip, FILTER_VALIDATE_IP );
	}

	/**
	 * @param string $ip IP address.
	 * @return bool
	 */
	protected static function is_public_ip( $ip ) {
		return false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * @param string $ip IP address.
	 * @return bool
	 */
	protected static function is_cloudflare_ip( $ip ) {
		if ( ! self::is_valid_ip( $ip ) ) {
			return false;
		}

		$ranges = (array) wpdai_apply_analytics_filter( 'wpd_ai_cloudflare_ip_ranges', 'wpd_ai_v2_cloudflare_ip_ranges', self::$cloudflare_ip_ranges );

		foreach ( $ranges as $cidr ) {
			if ( is_string( $cidr ) && self::ip_in_cidr( $ip, $cidr ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $ip   IP address.
	 * @param string $cidr CIDR range.
	 * @return bool
	 */
	protected static function ip_in_cidr( $ip, $cidr ) {
		if ( false === strpos( $cidr, '/' ) ) {
			return $ip === $cidr;
		}

		$parts = explode( '/', $cidr, 2 );
		if ( 2 !== count( $parts ) ) {
			return false;
		}

		$subnet = $parts[0];
		$bits   = (int) $parts[1];

		$ip_bin     = inet_pton( $ip );
		$subnet_bin = inet_pton( $subnet );

		if ( false === $ip_bin || false === $subnet_bin || strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
			return false;
		}

		$max_bits = 8 * strlen( $ip_bin );
		if ( $bits < 0 || $bits > $max_bits ) {
			return false;
		}

		$full_bytes     = (int) floor( $bits / 8 );
		$remaining_bits = $bits % 8;

		if ( $full_bytes > 0 && substr( $ip_bin, 0, $full_bytes ) !== substr( $subnet_bin, 0, $full_bytes ) ) {
			return false;
		}

		if ( 0 === $remaining_bits ) {
			return true;
		}

		$mask = chr( ( 0xFF << ( 8 - $remaining_bits ) ) & 0xFF );
		return ( $ip_bin[ $full_bytes ] & $mask ) === ( $subnet_bin[ $full_bytes ] & $mask );
	}
}
