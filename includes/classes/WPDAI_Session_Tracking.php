<?php
/**
 *
 * Session Tracking Parent Container
 *
 * @package Alpha Insights
 * @version 2.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 *
 * 	Sets up a session, collects all needed variables and stores the session in the DB
 *	To be used by any tracking classes that are measuring user activity so that session data is being stored correctly
 *
 */
class WPDAI_Session_Tracking {

    /**
     *
     *  Required Params for DB Insert
     *
     */
    public string   $session_id = '';
    public string   $ip_address = '';
    public string   $landing_page = '';
    public string   $referral_url = '';
    public int      $user_id = 0;
    public string   $date_created_gmt = '';
    public string   $date_updated_gmt = '';
    public string   $device_category = '';
    public string   $operating_system = '';
    public string   $browser = '';
    public string   $device = '';
    public array    $additional_data = array();
    public int      $is_bot = 0;
    public string   $page_href = '';
    public string   $raw_user_agent_string = '';
    public int      $object_id = 0;
    public string   $object_type = '';
    private bool    $is_new_session = false; // Track if this is a newly created session (first page load)
    private bool    $enable_logging = false;

    /**
     *
     *  Initilization
     * 
     */
    public function __construct() {

        // Check if we should log referral URL tracking
        $this->enable_logging = apply_filters( 'WPDAI_Session_Tracking_enable_logging', false );

        // Setup all props
        $this->setup_session_data();

    }

    /**
     *
     *  Log class initialization with context information
     * 
     *  @return void
     *
     */
    private function log_initialization() {


        // Get current hook/action
        $current_hook = '';
        if ( function_exists( 'current_filter' ) ) {
            $current_hook = current_filter();
        }
        if ( empty( $current_hook ) && function_exists( 'current_action' ) ) {
            $current_hook = current_action();
        }
        if ( empty( $current_hook ) ) {
            $current_hook = 'unknown';
        }

        // Determine request context
        $context = array();
        
        if ( is_admin() ) {
            $context[] = 'admin';
        }
        if ( defined( 'DOING_AJAX' ) && constant( 'DOING_AJAX' ) ) {
            $context[] = 'ajax';
        }
        if ( defined( 'REST_REQUEST' ) && constant( 'REST_REQUEST' ) ) {
            $context[] = 'rest_api';
        }
        if ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) ) {
            $context[] = 'wp_cli';
        }
        if ( defined( 'DOING_CRON' ) && constant( 'DOING_CRON' ) ) {
            $context[] = 'cron';
        }
        if ( empty( $context ) ) {
            $context[] = 'frontend';
        }

        // Get backtrace to see where it's being called from
        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 5 );
        $caller_info = '';
        
        if ( ! empty( $backtrace ) && isset( $backtrace[1] ) ) {
            $caller = $backtrace[1];
            $caller_file = isset( $caller['file'] ) ? basename( $caller['file'] ) : 'unknown';
            $caller_line = isset( $caller['line'] ) ? $caller['line'] : 'unknown';
            $caller_function = isset( $caller['function'] ) ? $caller['function'] : 'unknown';
            $caller_class = isset( $caller['class'] ) ? $caller['class'] : '';
            
            if ( ! empty( $caller_class ) ) {
                $caller_info = sprintf( '%s::%s() in %s:%d', $caller_class, $caller_function, $caller_file, $caller_line );
            } else {
                $caller_info = sprintf( '%s() in %s:%d', $caller_function, $caller_file, $caller_line );
            }
        }

        // Build log message
        $log_message = sprintf(
            __( 'WPDAI_Session_Tracking initialized. Hook: %s, Context: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
            $current_hook,
            implode( ', ', $context )
        );

        if ( ! empty( $caller_info ) ) {
            $log_message .= sprintf( __( ', Called from: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $caller_info );
        }

        // Log the initialization
        $this->log( $log_message );

    }

    /**
     *
     *  Log a message
     * 
     *  @param string $message The log message
     *  @param string $log_type The log type/category (default: 'session_tracking')
     *
     */
    private function log( $message, $error = false ) {

        if ( function_exists( 'wpdai_write_log' ) ) {

            if ( $this->enable_logging ) {
                wpdai_write_log( $message, 'session_tracking' );
            }

            if ( $error ) {
                wpdai_write_log( $message, 'session_tracking_error' );
            }
        }

    }

    /**
     *
     *  Sets up properties for the additional tracking parameters
     *
     */
    public function setup_session_data() {

        $this->log_initialization();

        // Collect User Agent Data
        $user_agent = new WPDAI_User_Agent_Classification();

        // First, check for bots
        // if ( ! empty($this->is_bot) ) $this->is_bot = $user_agent->isBot();
        $this->is_bot = $user_agent->isBot();

        // Dont process bots
        if ( $this->is_bot ) return get_object_vars( $this );

        // Session ID
        $this->session_id = $this->get_set_session_id();

        // Timestamp
        if ( empty($this->date_created_gmt) ) $this->date_created_gmt = current_time( 'mysql', true ); // SQL Timestamp in GMT

        // Timestamp
        $this->date_updated_gmt = current_time( 'mysql', true ); // SQL Timestamp in GMT

        // Page Href
        $this->page_href = esc_url_raw( home_url( add_query_arg( NULL, NULL ) ) );

        // User ID
        $this->user_id = get_current_user_id();

        // User IP
        $this->ip_address = $this->get_set_ip_address();

        // Referral URL
        $this->referral_url = $this->get_set_referral_url();

        // Landing Page
        $this->landing_page = $this->get_set_landing_page();

        // Device Category
        $this->device_category = $user_agent->getDeviceCategory();

        // Operating System
        $this->operating_system = $user_agent->getOS();

        // Browser
        $this->browser = $user_agent->getBrowser();

        // Device
        $this->device = $user_agent->getDeviceCategory();

        // User Agent Data
        $this->raw_user_agent_string = ( $user_agent->getUserAgent() ) ? $user_agent->getUserAgent() : '';

        // Additional_data
        $this->additional_data = array( 
            'raw_user_agent_data' => $this->raw_user_agent_string 
        );

        return get_object_vars( $this );

    }

    /**
     *
     *  Collects props, can be helpful with debugging.
     *
     */
    private function return_class_props() {

        $params = get_object_vars( $this );
        return $params;

    }

    /**
     * 
     * Generate a unique session_id from database
     * 
     **/
    public function generate_unique_session_id() {

        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        $random_token = md5($user_agent . time());
        return sanitize_text_field( 'wpd' . $random_token . time() );

    }

    /**
     *
     *  Get the User's IP address
     *  Supports Cloudflare and other proxy/CDN services
     *
     */
    public function get_set_ip_address() {

        // Use WC If available
        if ( class_exists('WC_Geolocation') ) {

            $ip = WC_Geolocation::get_ip_address();
            $this->ip_address = (string) $ip;
            return $ip;

        }

        // Redundant
        $ip = '';

        // Priority 1: Cloudflare (most reliable for CF users)
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            $ip = sanitize_text_field( $_SERVER['HTTP_CF_CONNECTING_IP'] );
        }
        // Priority 2: Other proxy headers
        elseif ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( $_SERVER['HTTP_CLIENT_IP'] );
        }
        // Priority 3: X-Forwarded-For (may contain multiple IPs, take first)
        elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $forwarded_ips = sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] );
            // X-Forwarded-For can contain multiple IPs: "client, proxy1, proxy2"
            // Extract the first (original client) IP
            $ip_list = explode( ',', $forwarded_ips );
            $ip = trim( $ip_list[0] );
        }
        // Priority 4: X-Real-IP (some proxies use this)
        elseif ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
            $ip = sanitize_text_field( $_SERVER['HTTP_X_REAL_IP'] );
        }
        // Priority 5: Direct connection (fallback)
        elseif ( isset($_SERVER['REMOTE_ADDR']) && ! empty($_SERVER['REMOTE_ADDR']) ) {
            $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] );
        }

        // Validate IP address (basic check)
        if ( ! empty( $ip ) ) {
            // Filter out invalid IPs
            $filtered_ip = filter_var( $ip, FILTER_VALIDATE_IP );
            if ( $filtered_ip !== false ) {
                $ip = $filtered_ip;
            } else {
                // If it's a private IP but we have nothing else, use it anyway
                // (for local development or internal networks)
                $ip = filter_var( $ip, FILTER_VALIDATE_IP );
                if ( $ip === false ) {
                    $ip = '';
                }
            }
        }

        $this->ip_address = (string) $ip;

        return $ip;

    }

    /**
     *
     *  If not setup, setup a session ID in PHP
     * 
     *  Session will expire and reset after 10 minutes of inactivity
     *  If the user has use WC sessions enabled, we will use WC sessions to store the session ID
     *  @note WC sessions are more persistent, typically lasting 48 hours
     * 
     *  Fallback order:
     *  1. Cookie (primary)
     *  2. PHP $_SESSION (fallback for Cloudflare/WP Engine cookie issues)
     *  3. WooCommerce session (if available)
     *  4. Generate new session ID
     *
     */
    public function get_set_session_id() {

        // 30 Minute expiry after dormant activity
        $seconds_from_now = time() + 60 * 30; // 30 minutes

        $session_id = '';

        // Priority 1: Try to get from cookie (primary method)
        if ( isset($_COOKIE['wpd_ai_session_id']) && ! empty($_COOKIE['wpd_ai_session_id']) ) {
            $session_id = sanitize_text_field( $_COOKIE['wpd_ai_session_id'] );
        }
        // Priority 2: Fallback to PHP $_SESSION (for Cloudflare/WP Engine cookie issues)
        elseif ( $this->get_session_id_from_php_session() ) {
            $session_id = $this->get_session_id_from_php_session();
        }
        // Priority 3: Try WooCommerce session if available
        elseif ( $this->get_session_id_from_wc_session() ) {
            $session_id = $this->get_session_id_from_wc_session();
        }

        // If no session ID found, generate a new one
        if ( empty($session_id) ) {
            $session_id = $this->generate_unique_session_id();
            $this->is_new_session = true; // Mark as new session (first page load)
        } else {
            $this->is_new_session = false; // Existing session (subsequent page load)
        }

        // Always store in all available storage methods (cookie is primary, sessions are backups)
        // Store in PHP session as backup (for Cloudflare/WP Engine compatibility)
        $this->store_session_id_in_php_session( $session_id );

        // Store in WooCommerce session if available
        $this->store_session_id_in_wc_session( $session_id );

        // Always attempt to set/refresh cookie (primary storage method)
        // This ensures cookie is set even if session ID was retrieved from PHP/WC session
        if ( ! headers_sent() ) {
            $this->set_session_cookie( $session_id, $seconds_from_now );
        }

        // Return results
        $this->session_id = $session_id;
        return $session_id;

    }

    /**
     * 
     * Get session ID from PHP $_SESSION (fallback for cookie issues)
     * Respects the 30-minute inactivity threshold
     * 
     * @return string|false Session ID if found and not expired, false otherwise
     * 
     */
    private function get_session_id_from_php_session() {

        // Only use PHP sessions if not in admin, not in CRON, and sessions are available
        if ( is_admin() || ( defined('DOING_CRON') && DOING_CRON ) ) {
            return false;
        }

        // Check if sessions are enabled and not disabled
        if ( session_status() === PHP_SESSION_DISABLED ) {
            return false;
        }

        // Get session ID and timestamp from $_SESSION
        if ( session_status() === PHP_SESSION_ACTIVE ) {
            
            // Check if session ID exists
            if ( ! isset($_SESSION['wpd_ai_session_id']) || empty($_SESSION['wpd_ai_session_id']) ) {
                return false;
            }

            // Check if timestamp exists (for backwards compatibility, if no timestamp, assume valid)
            $session_timestamp = isset($_SESSION['wpd_ai_session_timestamp']) ? (int) $_SESSION['wpd_ai_session_timestamp'] : time();
            
            // Check if session has expired (30 minutes of inactivity)
            $session_timeout = apply_filters( 'wpd_session_timeout_seconds', 30 * 60 ); // 30 minutes default
            $time_since_activity = time() - $session_timestamp;
            
            if ( $time_since_activity > $session_timeout ) {
                // Session expired, clear it and related data
                unset($_SESSION['wpd_ai_session_id']);
                unset($_SESSION['wpd_ai_session_timestamp']);
                unset($_SESSION['wpd_ai_landing_page']);
                unset($_SESSION['wpd_ai_referral_url']);
                return false;
            }

            // Session is still valid, return the session ID
            return sanitize_text_field( $_SESSION['wpd_ai_session_id'] );
        }

        return false;

    }

    /**
     * 
     * Store session ID in PHP $_SESSION as backup
     * Also stores timestamp to track inactivity
     * 
     * @param string $session_id The session ID to store
     * 
     */
    private function store_session_id_in_php_session( $session_id ) {

        // Only use PHP sessions if not in admin, not in CRON, and sessions are available
        if ( is_admin() || ( defined('DOING_CRON') && DOING_CRON ) ) {
            return;
        }

        // Check if sessions are enabled and not disabled
        if ( session_status() === PHP_SESSION_DISABLED ) {
            return;
        }

        // Store session ID and current timestamp in $_SESSION
        if ( session_status() === PHP_SESSION_ACTIVE ) {
            $_SESSION['wpd_ai_session_id'] = sanitize_text_field( $session_id );
            $_SESSION['wpd_ai_session_timestamp'] = time(); // Track last activity time
        }

    }

    /**
     * 
     * Get session ID from WooCommerce session (if available)
     * Respects the 30-minute inactivity threshold
     * 
     * @return string|false Session ID if found and not expired, false otherwise
     * 
     */
    private function get_session_id_from_wc_session() {

        // Check if WooCommerce is active and session handler is available
        if ( ! class_exists('WooCommerce') || ! function_exists('WC') ) {
            return false;
        }

        $wc_session = WC()->session;
        if ( ! $wc_session || ! is_a( $wc_session, 'WC_Session' ) ) {
            return false;
        }

        // Get session ID from WooCommerce session
        $wc_session_id = $wc_session->get( 'wpd_ai_session_id' );
        if ( empty($wc_session_id) ) {
            return false;
        }

        // Get timestamp (for backwards compatibility, if no timestamp, assume valid)
        $session_timestamp = $wc_session->get( 'wpd_ai_session_timestamp' );
        if ( empty($session_timestamp) ) {
            // No timestamp means old data, but we'll allow it for backwards compatibility
            // and update the timestamp on next save
            return sanitize_text_field( $wc_session_id );
        }

        // Check if session has expired (30 minutes of inactivity)
        $session_timeout = apply_filters( 'wpd_session_timeout_seconds', 30 * 60 ); // 30 minutes default
        $time_since_activity = time() - (int) $session_timestamp;
        
        if ( $time_since_activity > $session_timeout ) {
            // Session expired, clear it and related data
            $wc_session->set( 'wpd_ai_session_id', '' );
            $wc_session->set( 'wpd_ai_session_timestamp', '' );
            $wc_session->set( 'wpd_ai_landing_page', '' );
            $wc_session->set( 'wpd_ai_referral_url', '' );
            return false;
        }

        // Session is still valid, return the session ID
        return sanitize_text_field( $wc_session_id );

    }

    /**
     * 
     * Store session ID in WooCommerce session (if available)
     * Also stores timestamp to track inactivity
     * 
     * @param string $session_id The session ID to store
     * 
     */
    private function store_session_id_in_wc_session( $session_id ) {

        // Check if WooCommerce is active and session handler is available
        if ( ! class_exists('WooCommerce') || ! function_exists('WC') ) {
            return;
        }

        $wc_session = WC()->session;
        if ( ! $wc_session || ! is_a( $wc_session, 'WC_Session' ) ) {
            return;
        }

        // Store session ID and current timestamp in WooCommerce session
        $wc_session->set( 'wpd_ai_session_id', sanitize_text_field( $session_id ) );
        $wc_session->set( 'wpd_ai_session_timestamp', time() ); // Track last activity time

    }

    /**
     * 
     * Get referral URL from PHP $_SESSION backup
     * 
     * @return string|false Referral URL if found, false otherwise
     * 
     */
    private function get_referral_url_from_php_session() {

        // Only use PHP sessions if not in admin, not in CRON, and sessions are available
        if ( is_admin() || ( defined('DOING_CRON') && DOING_CRON ) ) {
            return false;
        }

        // Check if sessions are enabled and not disabled
        if ( session_status() === PHP_SESSION_DISABLED ) {
            return false;
        }

        // Get referral URL from $_SESSION
        if ( session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['wpd_ai_referral_url']) && ! empty($_SESSION['wpd_ai_referral_url']) ) {
            return esc_url_raw( $_SESSION['wpd_ai_referral_url'] );
        }

        return false;

    }

    /**
     * 
     * Store referral URL in PHP $_SESSION as backup
     * 
     * @param string $referral_url The referral URL to store
     * 
     */
    private function store_referral_url_in_php_session( $referral_url ) {

        // Only use PHP sessions if not in admin, not in CRON, and sessions are available
        if ( is_admin() || ( defined('DOING_CRON') && DOING_CRON ) ) {
            return;
        }

        // Check if sessions are enabled and not disabled
        if ( session_status() === PHP_SESSION_DISABLED ) {
            return;
        }

        // Store referral URL in $_SESSION (only if not already set, to preserve original)
        if ( session_status() === PHP_SESSION_ACTIVE ) {
            // Only set if not already exists (preserve original on first load)
            if ( ! isset($_SESSION['wpd_ai_referral_url']) || empty($_SESSION['wpd_ai_referral_url']) ) {
                $_SESSION['wpd_ai_referral_url'] = esc_url_raw( $referral_url );
            }
        }

    }

    /**
     * 
     * Get referral URL from WooCommerce native order attribution (WC 8.5+)
     * This is more reliable than HTTP_REFERER as WooCommerce captures it early in the request lifecycle
     * 
     * @return string|false Referral URL if found, false otherwise
     * 
     */
    private function get_referral_url_from_wc_order_attribution() {

        // Check if WooCommerce is active and session handler is available
        if ( ! class_exists('WooCommerce') || ! function_exists('WC') ) {
            return false;
        }

        $wc_session = WC()->session;
        if ( ! $wc_session || ! is_a( $wc_session, 'WC_Session' ) ) {
            return false;
        }

        // WooCommerce 8.5+ stores order attribution data in session
        // Check for native WooCommerce order attribution referrer (most reliable)
        $wc_referrer = $wc_session->get( 'wc_order_attribution_referrer' );
        if ( ! empty($wc_referrer) ) {
            // Validate URL format and ensure it's external domain (combined validation)
            $validated_url = $this->validate_external_referral_url( $wc_referrer );
            if ( $validated_url ) {
                return $validated_url;
            }
        }

        return false;

    }

    /**
     * 
     * Get referral URL from WooCommerce session backup (our own stored value)
     * 
     * @return string|false Referral URL if found, false otherwise
     * 
     */
    private function get_referral_url_from_wc_session() {

        // Check if WooCommerce is active and session handler is available
        if ( ! class_exists('WooCommerce') || ! function_exists('WC') ) {
            return false;
        }

        $wc_session = WC()->session;
        if ( ! $wc_session || ! is_a( $wc_session, 'WC_Session' ) ) {
            return false;
        }

        // Get referral URL from WooCommerce session (our own stored value)
        $wc_referral = $wc_session->get( 'wpd_ai_referral_url' );
        if ( ! empty($wc_referral) ) {
            return esc_url_raw( $wc_referral );
        }

        return false;

    }

    /**
     * 
     * Store referral URL in WooCommerce session backup
     * 
     * @param string $referral_url The referral URL to store
     * 
     */
    private function store_referral_url_in_wc_session( $referral_url ) {

        // Check if WooCommerce is active and session handler is available
        if ( ! class_exists('WooCommerce') || ! function_exists('WC') ) {
            return;
        }

        $wc_session = WC()->session;
        if ( ! $wc_session || ! is_a( $wc_session, 'WC_Session' ) ) {
            return;
        }

        // Store referral URL in WooCommerce session (only if not already set, to preserve original)
        $existing = $wc_session->get( 'wpd_ai_referral_url' );
        if ( empty($existing) ) {
            $wc_session->set( 'wpd_ai_referral_url', esc_url_raw( $referral_url ) );
        }

    }

    /**
     * 
     * Get landing page from PHP $_SESSION backup
     * 
     * @return string|false Landing page URL if found, false otherwise
     * 
     */
    private function get_landing_page_from_php_session() {

        // Only use PHP sessions if not in admin, not in CRON, and sessions are available
        if ( is_admin() || ( defined('DOING_CRON') && DOING_CRON ) ) {
            return false;
        }

        // Check if sessions are enabled and not disabled
        if ( session_status() === PHP_SESSION_DISABLED ) {
            return false;
        }

        // Get landing page from $_SESSION
        if ( session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['wpd_ai_landing_page']) && ! empty($_SESSION['wpd_ai_landing_page']) ) {
            return esc_url_raw( $_SESSION['wpd_ai_landing_page'] );
        }

        return false;

    }

    /**
     * 
     * Store landing page in PHP $_SESSION as backup
     * 
     * @param string $landing_page The landing page URL to store
     * 
     */
    private function store_landing_page_in_php_session( $landing_page ) {

        // Only use PHP sessions if not in admin, not in CRON, and sessions are available
        if ( is_admin() || ( defined('DOING_CRON') && DOING_CRON ) ) {
            return;
        }

        // Check if sessions are enabled and not disabled
        if ( session_status() === PHP_SESSION_DISABLED ) {
            return;
        }

        // Store landing page in $_SESSION (only if not already set, to preserve original)
        if ( session_status() === PHP_SESSION_ACTIVE ) {
            // Only set if not already exists (preserve original on first load)
            if ( ! isset($_SESSION['wpd_ai_landing_page']) || empty($_SESSION['wpd_ai_landing_page']) ) {
                $_SESSION['wpd_ai_landing_page'] = esc_url_raw( $landing_page );
            }
        }

    }

    /**
     * 
     * Get landing page from WooCommerce session backup
     * 
     * @return string|false Landing page URL if found, false otherwise
     * 
     */
    private function get_landing_page_from_wc_session() {

        // Check if WooCommerce is active and session handler is available
        if ( ! class_exists('WooCommerce') || ! function_exists('WC') ) {
            return false;
        }

        $wc_session = WC()->session;
        if ( ! $wc_session || ! is_a( $wc_session, 'WC_Session' ) ) {
            return false;
        }

        // Get landing page from WooCommerce session
        $wc_landing = $wc_session->get( 'wpd_ai_landing_page' );
        if ( ! empty($wc_landing) ) {
            return esc_url_raw( $wc_landing );
        }

        return false;

    }

    /**
     * 
     * Store landing page in WooCommerce session backup
     * 
     * @param string $landing_page The landing page URL to store
     * 
     */
    private function store_landing_page_in_wc_session( $landing_page ) {

        // Check if WooCommerce is active and session handler is available
        if ( ! class_exists('WooCommerce') || ! function_exists('WC') ) {
            return;
        }

        $wc_session = WC()->session;
        if ( ! $wc_session || ! is_a( $wc_session, 'WC_Session' ) ) {
            return;
        }

        // Store landing page in WooCommerce session (only if not already set, to preserve original)
        $existing = $wc_session->get( 'wpd_ai_landing_page' );
        if ( empty($existing) ) {
            $wc_session->set( 'wpd_ai_landing_page', esc_url_raw( $landing_page ) );
        }

    }

    /**
     * 
     * Set session cookie with improved settings for Cloudflare/WP Engine compatibility
     * 
     * @param string $session_id The session ID to set
     * @param int $expiry The expiry timestamp
     * 
     */
    private function set_session_cookie( $session_id, $expiry ) {

        // Determine if we're on HTTPS
        $https_value = isset($_SERVER['HTTPS']) ? sanitize_text_field($_SERVER['HTTPS']) : '';
        $forwarded_proto = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? sanitize_text_field($_SERVER['HTTP_X_FORWARDED_PROTO']) : '';
        $cf_visitor = isset($_SERVER['HTTP_CF_VISITOR']) ? sanitize_text_field($_SERVER['HTTP_CF_VISITOR']) : '';
        $is_https = ( ! empty($https_value) && $https_value !== 'off' ) || 
                    ( ! empty($forwarded_proto) && $forwarded_proto === 'https' ) ||
                    ( ! empty($cf_visitor) && strpos($cf_visitor, '"scheme":"https"') !== false );

        // Cookie options for better Cloudflare/WP Engine compatibility
        $cookie_options = array(
            'expires' => $expiry,
            'path' => '/',
            'domain' => '', // Empty = current domain only (better for subdomains)
            'secure' => $is_https, // Secure flag for HTTPS
            'httponly' => false, // Allow JavaScript access (needed for AJAX)
            'samesite' => 'Lax' // Lax is more permissive than Strict, works better with Cloudflare
        );

        // PHP 7.3+ supports array syntax for setcookie
        if ( PHP_VERSION_ID >= 70300 ) {
            setcookie( 'wpd_ai_session_id', $session_id, $cookie_options );
        } else {
            // Fallback for older PHP versions
            setcookie( 
                'wpd_ai_session_id', 
                $session_id, 
                $expiry, 
                $cookie_options['path'], 
                $cookie_options['domain'], 
                $cookie_options['secure'], 
                $cookie_options['httponly'] 
            );
        }

    }

    /**
     *
     *  Get referral URL from cookie (set by JavaScript on frontend) or HTTP_REFERER fallback
     *  Must be done after session_id is calculated
     *  Only captures external referrers (not same domain) to avoid capturing admin/login pages
     * 
     *  Fallback order:
     *  1. Cookie (primary - set by JavaScript on frontend)
     *  2. WooCommerce native order attribution (WC 8.5+) - most reliable server-side source
     *  3. PHP $_SESSION (backup for Cloudflare/WP Engine cookie issues)
     *  4. WooCommerce session (our own stored value, if available)
     *  5. Additional HTTP headers (X-Forwarded-Referer, etc.)
     *  6. HTTP_REFERER (fallback for first page load before JavaScript sets cookie)
     *  7. URL query parameters (UTM, tracking IDs - construct referral URL from current URL)
     *
     */
    public function get_set_referral_url() {

        // Default
        $referral_url = '';

        // Priority 1: Try to get from cookie (set by JavaScript on frontend)
        if ( isset($_COOKIE['wpd_ai_referral_source']) && ! empty($_COOKIE['wpd_ai_referral_source']) ) {

            $this->log( '[Session ID: ' . $this->session_id . '] Priority 1: Checking cookie (wpd_ai_referral_source) - Cookie value: ' . $_COOKIE['wpd_ai_referral_source'] );

            // Get raw cookie value and sanitize immediately
            $referral_url = sanitize_text_field( wp_unslash( $_COOKIE['wpd_ai_referral_source'] ) );

            // Try decoding if it's URL-encoded (may be double-encoded)
            $decoded = rawurldecode($referral_url);
            
            // If still encoded, decode again (handle double-encoding)
            if ( $decoded !== $referral_url ) {
                $referral_url = $decoded;
                // Try one more decode in case of double-encoding
                $double_decoded = rawurldecode($referral_url);
                if ( $double_decoded !== $referral_url && filter_var($double_decoded, FILTER_VALIDATE_URL) ) {
                    $referral_url = $double_decoded;
                }
            }

            // Validate URL format and ensure it's external domain (combined validation)
            $validated_url = $this->validate_external_referral_url( $referral_url );
            if ( $validated_url ) {
                $referral_url = $validated_url;
                $this->log( '[Session ID: ' . $this->session_id . '] Priority 1: Cookie method SUCCESS - Referral URL: ' . $referral_url );
            } else {
                $referral_url = '';
            }
        }
        // Priority 2: WooCommerce native order attribution (WC 8.5+) - most reliable server-side source
        // This is captured early by WooCommerce and is more reliable than HTTP_REFERER
        elseif ( $this->get_referral_url_from_wc_order_attribution() ) {
            $this->log( '[Session ID: ' . $this->session_id . '] Priority 2: WooCommerce order attribution method SUCCESS' );
            $referral_url = $this->get_referral_url_from_wc_order_attribution();
        }
        // Priority 3: Fallback to PHP $_SESSION (for Cloudflare/WP Engine cookie issues)
        elseif ( $this->get_referral_url_from_php_session() ) {
            $this->log( '[Session ID: ' . $this->session_id . '] Priority 3: PHP $_SESSION method SUCCESS' );
            $referral_url = $this->get_referral_url_from_php_session();
        }
        // Priority 4: Try WooCommerce session (our own stored value) if available
        elseif ( $this->get_referral_url_from_wc_session() ) {
            $this->log( '[Session ID: ' . $this->session_id . '] Priority 4: WooCommerce session method SUCCESS' );
            $referral_url = $this->get_referral_url_from_wc_session();
        }
        // Priority 5: Fallback to HTTP_REFERER if cookie/sessions don't exist (only external domains)
        // This captures referrers on first page load before JavaScript sets the cookie
        elseif ( function_exists('wpdai_get_referral_url_raw') ) {
            $http_referrer = wpdai_get_referral_url_raw();
            if ( ! empty($http_referrer) ) {
                $this->log( '[Session ID: ' . $this->session_id . '] Priority 5: HTTP_REFERER method SUCCESS - Referral URL: ' . $http_referrer );
                $referral_url = esc_url_raw( $http_referrer );
            } else {
                $this->log( '[Session ID: ' . $this->session_id . '] Priority 5: HTTP_REFERER method - No referrer found' );
            }
        }
        // Priority 6: Check additional HTTP headers (some proxies/CDNs set custom referrer headers)
        elseif ( $this->get_referral_url_from_http_headers() ) {
            $this->log( '[Session ID: ' . $this->session_id . '] Priority 6: HTTP headers method SUCCESS' );
            $referral_url = $this->get_referral_url_from_http_headers();
        }
        // Priority 7: Check URL query parameters for referral indicators (UTM, tracking IDs, etc.)
        // If we have tracking parameters but no referrer, construct a referral URL from the current URL
        elseif ( $this->get_referral_url_from_url_parameters() ) {
            $this->log( '[Session ID: ' . $this->session_id . '] Priority 7: URL query parameters method SUCCESS' );
            $referral_url = $this->get_referral_url_from_url_parameters();
        }

        // Log if no referral URL was found
        if ( empty( $referral_url ) ) {
            $this->log( '[Session ID: ' . $this->session_id . '] No referral URL found after checking all 7 priority methods' );
        }

        // If we have a referral URL, store it in all available storage methods for future requests
        if ( ! empty($referral_url) ) {
            // Store in PHP session as backup (for Cloudflare/WP Engine compatibility)
            $this->store_referral_url_in_php_session( $referral_url );

            // Store in WooCommerce session if available
            $this->store_referral_url_in_wc_session( $referral_url );

            // Always attempt to set/refresh cookie (primary storage method)
            // This ensures cookie is set even if referral URL was retrieved from PHP/WC session
            if ( ! headers_sent() ) {
                // Use same cookie settings as session cookie for consistency
                $this->set_referral_cookie( $referral_url );
            }
        }

        // Set the prop
        $this->referral_url = $referral_url;

        return $referral_url;
        
    }

    /**
     * 
     * Set referral cookie with improved settings for Cloudflare/WP Engine compatibility
     * Uses same cookie settings as session cookie for consistency
     * 
     * @param string $referral_url The referral URL to set
     * 
     */
    private function set_referral_cookie( $referral_url ) {

        // Determine if we're on HTTPS
        $https_value = isset($_SERVER['HTTPS']) ? sanitize_text_field($_SERVER['HTTPS']) : '';
        $forwarded_proto = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? sanitize_text_field($_SERVER['HTTP_X_FORWARDED_PROTO']) : '';
        $cf_visitor = isset($_SERVER['HTTP_CF_VISITOR']) ? sanitize_text_field($_SERVER['HTTP_CF_VISITOR']) : '';
        $is_https = ( ! empty($https_value) && $https_value !== 'off' ) || 
                    ( ! empty($forwarded_proto) && $forwarded_proto === 'https' ) ||
                    ( ! empty($cf_visitor) && strpos($cf_visitor, '"scheme":"https"') !== false );

        // Cookie options for better Cloudflare/WP Engine compatibility
        // No expiry (session cookie) - will persist until browser closes
        $cookie_options = array(
            'expires' => 0,
            'path' => '/',
            'domain' => '', // Empty = current domain only (better for subdomains)
            'secure' => $is_https, // Secure flag for HTTPS
            'httponly' => false, // Allow JavaScript access (needed for frontend updates)
            'samesite' => 'Lax' // Lax is more permissive than Strict, works better with Cloudflare
        );

        // PHP 7.3+ supports array syntax for setcookie
        if ( PHP_VERSION_ID >= 70300 ) {
            setcookie( 'wpd_ai_referral_source', esc_url_raw( $referral_url ), $cookie_options );
        } else {
            // Fallback for older PHP versions
            setcookie( 
                'wpd_ai_referral_source', 
                esc_url_raw( $referral_url ), 
                $cookie_options['expires'], 
                $cookie_options['path'], 
                $cookie_options['domain'], 
                $cookie_options['secure'], 
                $cookie_options['httponly'] 
            );
        }

    }

    /**
     * 
     * Check additional HTTP headers for referral URL
     * Some proxies, CDNs, or load balancers set custom referrer headers
     * 
     * @return string|false Referral URL if found, false otherwise
     * 
     */
    private function get_referral_url_from_http_headers() {

        $referral_url = '';

        // Check various HTTP headers that might contain referrer information
        $referrer_headers = array(
            'HTTP_X_FORWARDED_REFERER',
            'HTTP_X_REFERER',
            'HTTP_REFERER_ORIGINAL',
            'HTTP_X_ORIGINAL_REFERER',
        );

        foreach ( $referrer_headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $potential_referrer = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
                
                // Validate URL format and ensure it's external domain (combined validation)
                $validated_url = $this->validate_external_referral_url( $potential_referrer );
                if ( $validated_url ) {
                    $referral_url = $validated_url;
                    break;
                }
            }
        }

        return ! empty( $referral_url ) ? $referral_url : false;

    }

    /**
     * 
     * Get referral URL from URL query parameters
     * If we have tracking parameters (UTM, gclid, fbclid, etc.) but no referrer,
     * construct a referral URL from the current URL to preserve attribution
     * 
     * @return string|false Referral URL if found, false otherwise
     * 
     */
    private function get_referral_url_from_url_parameters() {

        // Only check if we're on the frontend and have a current URL
        if ( is_admin() || ( defined('DOING_CRON') && DOING_CRON ) || ( defined('DOING_AJAX') && DOING_AJAX ) ) {
            return false;
        }

        // Get current URL
        $current_url = home_url( add_query_arg( NULL, NULL ) );
        if ( empty( $current_url ) ) {
            return false;
        }

        // Parse query parameters
        $query_params = wp_parse_url( $current_url, PHP_URL_QUERY );
        if ( empty( $query_params ) ) {
            return false;
        }

        parse_str( $query_params, $params );

        // Check for tracking parameters that indicate a referral source
        $tracking_params = array(
            'gclid',           // Google Ads
            'fbclid',          // Facebook
            'msclkid',         // Microsoft Ads
            'ttclid',          // TikTok
            'li_fat_id',       // LinkedIn
            'utm_source',      // UTM tracking
            'utm_medium',      // UTM tracking
            'utm_campaign',    // UTM tracking
            'utm_term',        // UTM tracking
            'utm_content',     // UTM tracking
            'ref',             // Generic referral
            'source',          // Generic source
            'referrer',        // Generic referrer
            'referer',         // Common misspelling
        );

        $has_tracking_param = false;
        foreach ( $tracking_params as $param ) {
            if ( isset( $params[ $param ] ) && ! empty( $params[ $param ] ) ) {
                $has_tracking_param = true;
                break;
            }
        }

        // If we have tracking parameters, use the current URL as referral URL
        // This preserves attribution even when referrer header is missing
        if ( $has_tracking_param ) {
            // Remove tracking parameters to get a cleaner referral URL
            $clean_url = remove_query_arg( array_keys( $tracking_params ), $current_url );
            
            // Validate it's a proper URL
            if ( filter_var( $clean_url, FILTER_VALIDATE_URL ) ) {
                return esc_url_raw( $clean_url );
            }
        }

        return false;

    }

    /**
     * 
     * Validate and sanitize a referral URL - ensures it's a valid URL and from an external domain
     * Combines URL validation and external domain checking into a single method
     * 
     * @param string $referral_url The referral URL to validate
     * @return string|false Validated and sanitized URL if valid and external, false otherwise
     * 
     */
    private function validate_external_referral_url( $referral_url ) {

        if ( empty( $referral_url ) ) {
            $this->log( '[Session ID: ' . $this->session_id . '] Combined validation: Referral URL is empty' );
            return false;
        }

        // First, validate it's a proper URL format
        $referral_url = filter_var( $referral_url, FILTER_SANITIZE_URL );
        if ( ! filter_var( $referral_url, FILTER_VALIDATE_URL ) ) {
            $this->log( '[Session ID: ' . $this->session_id . '] Combined validation: URL validation failed: ' . $referral_url );
            return false;
        }

        // Then, check if it's external (not our own domain)
        if ( ! $this->is_external_referral_url( $referral_url ) ) {
            return false;
        }

        // Return sanitized URL
        return esc_url_raw( $referral_url );

    }

    /**
     * 
     * Validate if a referral URL is external (not our own domain)
     * Handles subdomains, IP addresses, and various edge cases
     * 
     * @param string $referral_url The referral URL to validate
     * @return bool True if external, false if internal
     * 
     */
    private function is_external_referral_url( $referral_url ) {

        if ( empty( $referral_url ) ) {
            $this->log( '[Session ID: ' . $this->session_id . '] Domain comparison: Referral URL is empty' );
            return false;
        }

        // Get site host for comparison
        $site_host = wp_parse_url( site_url(), PHP_URL_HOST );
        if ( empty( $site_host ) ) {
            $this->log( '[Session ID: ' . $this->session_id . '] Domain comparison: Site host is empty' );
            return false;
        }

        // Parse the referral URL
        $referring_domain = wp_parse_url( $referral_url, PHP_URL_HOST );
        if ( empty( $referring_domain ) ) {
            $this->log( '[Session ID: ' . $this->session_id . '] Domain comparison: Could not parse referring domain from URL: ' . $referral_url );
            return false;
        }

        // Normalize domains (remove www, convert to lowercase)
        $referring_domain = strtolower( preg_replace( '/^www\./', '', $referring_domain ) );
        $site_host_normalized = strtolower( preg_replace( '/^www\./', '', $site_host ) );

        // Direct match
        if ( $referring_domain === $site_host_normalized ) {
            $this->log( '[Session ID: ' . $this->session_id . '] Domain comparison: INTERNAL - Direct match. Referring: ' . $referring_domain . ' === Site: ' . $site_host_normalized );
            return false;
        }

        // Check if referral domain is a subdomain of site domain
        // e.g., shop.example.com should match example.com
        if ( strpos( $referring_domain, '.' . $site_host_normalized ) !== false ) {
            $this->log( '[Session ID: ' . $this->session_id . '] Domain comparison: INTERNAL - Referral is subdomain of site. Referring: ' . $referring_domain . ' (subdomain of ' . $site_host_normalized . ')' );
            return false;
        }

        // Check if site domain is a subdomain of referral domain
        // e.g., example.com should match shop.example.com
        if ( strpos( $site_host_normalized, '.' . $referring_domain ) !== false ) {
            $this->log( '[Session ID: ' . $this->session_id . '] Domain comparison: INTERNAL - Site is subdomain of referral. Site: ' . $site_host_normalized . ' (subdomain of ' . $referring_domain . ')' );
            return false;
        }

        // Check for IP addresses - if both are IPs, compare directly
        if ( filter_var( $referring_domain, FILTER_VALIDATE_IP ) && filter_var( $site_host_normalized, FILTER_VALIDATE_IP ) ) {
            $is_external = $referring_domain !== $site_host_normalized;
            $result = $is_external ? 'EXTERNAL' : 'INTERNAL';
            $this->log( '[Session ID: ' . $this->session_id . '] Domain comparison: ' . $result . ' - IP comparison. Referring: ' . $referring_domain . ' vs Site: ' . $site_host_normalized );
            return $is_external;
        }

        // Check for localhost/local domains
        $local_domains = array( 'localhost', '127.0.0.1', '::1', '0.0.0.0' );
        if ( in_array( $referring_domain, $local_domains, true ) || in_array( $site_host_normalized, $local_domains, true ) ) {
            // Allow localhost to localhost (development scenario)
            if ( $referring_domain === $site_host_normalized ) {
                $this->log( '[Session ID: ' . $this->session_id . '] Domain comparison: INTERNAL - Localhost match. Referring: ' . $referring_domain . ' === Site: ' . $site_host_normalized );
                return false;
            }
        }

        // If we get here, it's an external referral
        $this->log( '[Session ID: ' . $this->session_id . '] Domain comparison: EXTERNAL - Referral URL accepted. Referring: ' . $referring_domain . ' vs Site: ' . $site_host_normalized );
        return true;

    }
        
    /**
     *
     *  Get landing page URL from cookie only (set by JavaScript on frontend)
     *  Must be done after session_id is calculated
     *  Landing page is cookie-only to prevent persistence across sessions and avoid capturing admin/login pages
     *
     */
    public function get_set_landing_page() {

        // Default
        $landing_page = '';

        // Only get from cookie (set by JavaScript on frontend)
        // No PHP fallback to avoid capturing admin/login pages
        if ( isset($_COOKIE['wpd_ai_landing_page']) && ! empty($_COOKIE['wpd_ai_landing_page']) ) {

            // Get raw cookie value and sanitize immediately
            $landing_page = sanitize_text_field( $_COOKIE['wpd_ai_landing_page'] );

            // Try decoding if URL-encoded (may be double-encoded)
            $decoded = rawurldecode($landing_page);
            
            // If still encoded, decode again (handle double-encoding)
            if ( $decoded !== $landing_page ) {
                $landing_page = $decoded;
                // Try one more decode in case of double-encoding
                $double_decoded = rawurldecode($landing_page);
                if ( $double_decoded !== $landing_page && filter_var($double_decoded, FILTER_VALIDATE_URL) ) {
                    $landing_page = $double_decoded;
                }
            }

            // Sanitize URL (internal use)
            $landing_page = filter_var($landing_page, FILTER_SANITIZE_URL);
            
            // Validate it's a proper URL
            if ( ! filter_var($landing_page, FILTER_VALIDATE_URL) ) {
                $landing_page = '';
            }
            
            // Filter out admin/login pages - if it contains wp-admin, wp-login, or admin-ajax, ignore it
            if ( ! empty($landing_page) ) {
                $landing_page_lower = strtolower($landing_page);
                if ( strpos($landing_page_lower, 'wp-admin') !== false || 
                     strpos($landing_page_lower, 'wp-login') !== false || 
                     strpos($landing_page_lower, 'admin-ajax') !== false ) {
                    $landing_page = '';
                }
            }
        }

        // Set the class property
        $this->landing_page = $landing_page;

        return $landing_page;
    }

    /**
     *
     *  Create / update session once per main query
     *  @hook template_redirect
     *
     */
    public function store_session_in_db_hook() {

		// Don't bother in CRON
        if (defined('DOING_CRON') && DOING_CRON) {
            return false;
        }

        // Dont bother in AJAX
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return false;
        }

		// Dont store any data from admin
		if ( is_admin() ) {
			return false;
		}

        // Only check session in main query
        global $wp_query;
        if ( is_admin() || ! $wp_query->is_main_query() ) {
            return false;
        }

        // Otherwise, lets store the session in the DB
        $result = $this->store_session_in_db();

    }

    /**
     *
     *  Stores session in DB, shouldnt be called if we are inheriting this method.
     * 
     *  Always update the first landing page, this can be updated later ???
     *  Always update the dated_updated_gmt, this can be updated later
     * 
     *  @todo need to do more checks to prevent this running when it shouldnt
     *  @todo need to sanitize all variables that are going into the DB
     *  @see $wpdb->update() for better update method https://developer.wordpress.org/reference/classes/wpdb/
     *
     */
    public function store_session_in_db() {

        // Dont proceed if we dont have a Session ID
        if ( empty($this->session_id) ) {
            return false;
        }

        global $wpdb;

        // Capture and set defaults
        $result             = true;
        $db_interactor      = new WPDAI_Database_Interactor();
        $table_name         = $db_interactor->session_data_table;
        $data               = array();

        // Setup our Data Array
        $data['session_id']         = $this->session_id;
        $data['ip_address']         = $this->ip_address;
        $data['landing_page']       = $this->landing_page;
        $data['referral_url']       = $this->referral_url;
        $data['user_id']            = $this->user_id;
        $data['date_created_gmt']   = $this->date_created_gmt;
        $data['date_updated_gmt']   = $this->date_updated_gmt;
        $data['device_category']    = $this->device_category;
        $data['operating_system']   = $this->operating_system;
        $data['browser']            = $this->browser;
        $data['device']             = $this->device;
        $data['additional_data']    = json_encode( $this->additional_data );

        // Sanitize
        $data['session_id']         = sanitize_text_field($data['session_id']);
		$data['ip_address']         = sanitize_text_field($data['ip_address']);
		$data['landing_page']       = sanitize_url($data['landing_page']);
		$data['referral_url']       = sanitize_url($data['referral_url']);
		$data['user_id']            = (int) $data['user_id'] ;
        $data['device_category']    = sanitize_text_field($this->device_category);
        $data['operating_system']   = sanitize_text_field($this->operating_system);
        $data['browser']            = sanitize_text_field($this->browser);
        $data['device']             = sanitize_text_field($this->device);

        /**
         *
         *  Check if this session already exists, create it or update it
         *
         */
        $value_exists = $db_interactor->does_value_exist( $table_name, 'session_id', $data['session_id'] );

        if ( $value_exists ) {

            $update_user = false;
            if ( $data['user_id'] > 0 ) {
                $update_user = 'user_id = ' . $data['user_id'] . ',';
            }
            // Update date
            $rows_updated = $wpdb->query(
                $wpdb->prepare( 
                    "UPDATE $table_name 
                    SET date_updated_gmt = %s,
                    landing_page = %s,
                    $update_user
                    referral_url = %s 
                    WHERE session_id = %s",
                    $data['date_updated_gmt'], 
                    $data['landing_page'], 
                    $data['referral_url'], 
                    $data['session_id']
                )
            );

        } else {

            // Insert new session into DB
            $rows_inserted = $db_interactor->add_row( $table_name, $data );

        }

        return $result;

    }

    /**
     *
     *  Update engaged_session flag for the current session
     *  Called when user interacts with the site (click/scroll) to mark session as engaged
     * 
     *  @return bool True on success, false on failure
     *
     */
    public function update_engaged_session() {

        // Get session ID
        $session_id = $this->get_set_session_id();
        
        if ( empty( $session_id ) ) {
            $this->log( 'Cannot update engaged session: session ID is empty.', true );
            return false;
        }

        global $wpdb;

        $db_interactor = new WPDAI_Database_Interactor();
        $table_name = $db_interactor->session_data_table;

        // Check if session exists
        $session_exists = $db_interactor->does_value_exist( $table_name, 'session_id', $session_id );

        if ( ! $session_exists ) {
            $this->log( 'Cannot update engaged session: session does not exist in database.', true );
            return false;
        }

        // Update engaged_session to 1 (true) for this session
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is from trusted source.
        $updated_rows = $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table_name 
                SET engaged_session = 1 
                WHERE session_id = %s 
                AND (engaged_session IS NULL OR engaged_session = 0)",
                $session_id
            )
        );

        // Check for errors
        if ( $wpdb->last_error ) {
            $error = $wpdb->last_error;
            $query = $wpdb->last_query;
            $this->log( 'Error occurred updating engaged session', true );
            $this->log( $error, true );
            $this->log( $query, true );
            return false;
        }

        if ( $updated_rows > 0 ) {
            return true;
        }

        return false;

    }

}