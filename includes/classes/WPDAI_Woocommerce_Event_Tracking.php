<?php
/**
 *
 * Track Product & Category Activity
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
 * 	Product Analytics, a segment of WooCommerce Event Tracking
 *	WooCommerce Event Tracking
 *
 */
class WPDAI_WooCommerce_Event_Tracking {

	/**
	 *
	 *	Required Params for DB Insert
	 *
	 */
	public string 	$page_href 				= '';
	public string 	$object_type 			= '';
	public int 		$object_id 				= 0;
	public string 	$event_type 			= '';
	public int 		$event_quantity 		= 1;
	public float 	$event_value 			= 0;
	public int 		$product_id 			= 0;
	public int 		$variation_id 			= 0;
	public string 	$date_created_gmt 		= '';
	public string   $api_namespace 			= 'alpha-insights/v1';
	public string   $api_endpoint 			= 'woocommerce-events';
	public string   $api_url 				= '';
	public int 		$only_track_engaged_sessions = 0;
	public int 		$event_tracking_enabled = 1;
	public array 	$settings 				= array();
    public int      $track_user 			= 1;
	private static 	$instance 				= null; // Singleton pattern so that we can call the insert_event without reinitializing the whole thing
	public ?object 	$session_instance 		= null; // WPDAI_Session_Tracking instance
	public bool 	$enable_logging 		= false;
	
	/**
	 *
	 *	Init all hooks
	 *
	 */
	public function __construct() {

		if ( ! wpdai_is_analytics_enabled() ) {
			$this->event_tracking_enabled = 0;
		}

		// Load script -> always add tracking script to get around cache issues
		add_action( 'wp_enqueue_scripts', array($this, 'register_event_tracking_script') );

		// Setup props
		$this->settings = get_option( 'wpd_ai_analytics', array()); // Default return empty array
		$this->only_track_engaged_sessions = get_option( 'wpd_ai_analytics_only_track_engaged_sessionss', 0 );

		// If we are going to track events, setup the hooks
		if ( $this->event_tracking_enabled == 1 ) {

			// Set API URL for calls
			$this->api_url = '/wp-json/' . $this->api_namespace . '/' . $this->api_endpoint;

			// Setup object info, after things are setup though
			add_action( 'template_redirect', array( $this, 'setup_object_type_id' ), 1 );

			// Track add to cart via server
			add_action( 'woocommerce_add_to_cart', array( $this, 'db_track_product_add_to_cart' ), 10, 6 );

			// Track products purchased via server - using payment complete hook for reliability
			add_action( 'woocommerce_thankyou', array($this, 'db_track_products_purchased_thankyou_page'), 10, 1 ); // Track orders using two methods, in case one fails
			add_action( 'woocommerce_order_status_changed', array( $this, 'db_track_products_purchased_on_order_status_change' ), 20, 4 ); // Track orders using order status change hook for reliability
			add_action( 'woocommerce_order_status_changed', array( $this, 'db_track_failed_orders' ), 30, 4 );

			// Track logins via server -> Wordpress API Only
			add_action( 'wp_login', array($this, 'db_track_logins'), 100 ); // wp api
			add_action( 'woocommerce_customer_login', 'db_track_logins', 100 ); // wc api
			add_action( 'wp_logout', array($this, 'db_track_logouts'), 100, 1 ); // both api

			// Track account creation via server
			add_action( 'woocommerce_created_customer', array($this, 'db_track_account_created'), 100, 3 ); // wc api

			// Used for tracking clicks
			add_action( 'woocommerce_before_shop_loop_item', array($this, 'add_product_id_to_product_loop_item'), 10 );

			// Used for tracking clicks -> not used for now but may be helpful
			add_filter( 'woocommerce_post_class', array($this, 'add_class_to_loop_item'), 10, 2 );

			// WooCommerce Events API
			add_action( 'rest_api_init', array( $this, 'register_wpd_ai_events_api' ) );

		}

	}

	/**
	 * 
	 * 	Singleton pattern - used for calling one instance of the insert_event() without creating a new instance of the object
	 * 	e.g. WPDAI_WooCommerce_Event_Tracking::get_instance()->insert_event($event_data);
	 * 
	 **/
	public static function get_instance() {

        if ( self::$instance === null ) {
            self::$instance = new self();
        }

        return self::$instance;

    }

	/**
	 *
	 *	Initialize callback for rest API - JS Event Tracking
	 *
	 */
	public function register_wpd_ai_events_api() {

		register_rest_route(
			$this->api_namespace, 	// Namespace
			$this->api_endpoint, 	// Endpoint
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'process_events_api_data' ),
				'permission_callback' => array( $this, 'validate_event_tracking_token' )
			)
		);

	}

	/**
	 * 
	 * 	Validate the event tracking token
	 *  Token is passed in the request as 'event-tracking-token' to prevent CSRF attacks
	 *  This is a public endpoint, so we need to validate the token to prevent CSRF attacks
	 * 
	 * 	@param WP_REST_Request $request The request object
	 * 	@return bool|WP_Error True if the token is valid, WP_Error if the token is invalid
	 * 
	 */
	public function validate_event_tracking_token( $request ) {
		$token = $request->get_param( 'event-tracking-token' );
		$expected_token = wpdai_get_analytics_event_tracking_token();
		
		// Reject if token is not provided, is empty, or doesn't match
		if ( empty( $token ) || empty( $expected_token ) || $token !== $expected_token ) {
			return new \WP_Error( 'invalid_token', __( 'Invalid token.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 *
	 *	Manually authenticate user from cookies if not already authenticated
	 *  REST API doesn't automatically load user sessions from cookies
	 *
	 */
	public function rest_api_cookie_authentication() {
		if ( ! is_user_logged_in() ) {
			// Use wp_validate_auth_cookie with false to auto-read from $_COOKIE
			$user_id = wp_validate_auth_cookie( false, 'logged_in' );
			if ( $user_id ) {
				wp_set_current_user( $user_id );
			}
		}
	}

	/**
	 *
	 *	Process data passed to us by the Rest API, this is used for JS event tracking
	 *
	 */
	public function process_events_api_data( WP_REST_Request $request ) {


		// Vars
		$payload 		= $request->get_params();
		$referer 		= $request->get_header( 'referer' );

		// Check for referer mismatch, users can optionally prevent bad referral requests
		if ( $referer ) {

			$referring_host = wp_parse_url( $referer, PHP_URL_HOST );
			$site_host      = wp_parse_url( home_url(), PHP_URL_HOST );
		
			if ( $referring_host && $referring_host !== $site_host ) {
		
				$this->log(
					sprintf(
						'Event tracking referer mismatch: %s vs %s',
						$referring_host,
						$site_host
					)
				);
		
				if ( apply_filters( 'wpd_ai_event_tracking_prevent_foreign_referrals', false ) ) {
					return new WP_REST_Response(
						array(
							'success' => false,
							'message' => __( 'Forbidden request.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
							'code'    => 'invalid_referer',
							'data'    => array(),
						),
						403
					);
				}
			}
		}

		// Event Type Not Set
		if ( ! isset($payload['event_type']) || empty($payload['event_type']) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Event type is a required parameter.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'code' => 'missing_event_type',
					'data' => array()
				),
				400
			);
		}

		// At this stage, let's make sure the user object is available during our request as this is important for $this->track_user()
		$this->rest_api_cookie_authentication();

		// Succesful Call, insert into db
		if ( isset($payload['event_type']) && ! empty($payload['event_type']) ) {

			// Original event
			$insert_result = $this->insert_event( $payload );
			
			// Handle structured response
			if ( is_array( $insert_result ) && isset( $insert_result['success'] ) ) {

				$response['success'] = $insert_result['success'];
				$response['message'] = $insert_result['message'];
				$response['code'] = $insert_result['code'];
				$response['data'] = $payload;
				
				// Track additional events for page_view
				if ( $payload['event_type'] == 'page_view' && is_numeric($payload['object_id']) && $insert_result['success'] ) {

					if ( intval($payload['object_id']) == wc_get_page_id('checkout') ) {

						$payload['event_type'] = 'init_checkout';
						$checkout_result = $this->insert_event( $payload );
						
						if ( is_array( $checkout_result ) && isset( $checkout_result['success'] ) && $checkout_result['success'] ) {
							$response['message'] = __( 'Page view and checkout initiation successfully tracked.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
							$response['code'] = 'page_view_and_checkout_tracked';
						} else {
							$response['message'] = __( 'Page view tracked, but checkout initiation failed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
						}
						$response['data'] = $payload;

					} else if ( intval($payload['object_id']) == wc_get_page_id('cart') ) {

						$payload['event_type'] = 'viewed_cart_page';
						$cart_result = $this->insert_event( $payload );
						
						if ( is_array( $cart_result ) && isset( $cart_result['success'] ) && $cart_result['success'] ) {
							$response['message'] = __( 'Page view and cart page view successfully tracked.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
							$response['code'] = 'page_view_and_cart_tracked';
						} else {
							$response['message'] = __( 'Page view tracked, but cart page view failed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
						}
						$response['data'] = $payload;

					}
				}
				
				// Set appropriate HTTP status code
				$status_code = $insert_result['success'] ? 200 : 400;
				
			} else {
				// Fallback for backward compatibility (shouldn't happen with new code)
				$response = array(
					'success' => false,
					'message' => __( 'Unexpected response format from event insertion.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'code' => 'unexpected_response',
					'data' => $payload
				);
				$status_code = 500;
			}
			
			return new \WP_REST_Response(
				$response,
				$status_code
			);

		}

		// If we get here, something went wrong
		return new \WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Invalid request: Unable to process event tracking request.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'code' => 'invalid_request',
				'data' => array()
			),
			400
		);

	}

	/**
	 *
	 *	Get or set session instance from WPDAI_Session_Tracking
	 *
	 */
	public function get_set_session_instance() {
		if ( ! empty($this->session_instance) && is_object($this->session_instance) ) {
			return $this->session_instance;
		}
		$this->session_instance = new WPDAI_Session_Tracking();
		return $this->session_instance;
	}

	/**
	 *
	 *	Insert event into Database
	 * 
	 * 	@var $data
	 *  @see /includes/helpers/database_interactor
	 * 
     *  @var $data['session_id']        PHP Session ID                      	Default  ''
     *  @var $data['ip_address']        IP Address                          	Default  0
     *  @var $data['user_id']           User ID                             	Default  0
     *  @var $data['page_href']         Current Page Url                    	Default ''
     *  @var $data['object_type']       Custom Post Type Name               	Default ''
     *  @var $data['object_id']         Wordpress Object ID                 	Default 0
     *  @var $data['event_type']        category_page_click | product_page_view | add_to_cart | purchase | refund | add_to_wishlist | anything else...
     *  @var $data['event_quantity']    Event Quantity                      	Default 1
     *  @var $data['event_value']       Event Value                         	Default 0.00
     *  @var $data['product_id']        Product ID                          	Default  0
     *  @var $data['variation_id']      Product ID                          	Default  0
     *  @var $data['date_created_gmt']  Date Event Created In GMT Time      	Default: current_time('mysql')
     *  @var $data['additional_data']   Array Any additional data, stored in JSON 	Default: NULL
	 * 
	 *  @return array {
	 *      @type bool   $success       Whether the event was successfully inserted
	 *      @type string $message       Human-readable message (translatable)
	 *      @type string $code         Programmatic code for the result
	 *      @type int    $rows_inserted Number of rows inserted (for backward compatibility)
	 *  }
	 *
	 */
	public function insert_event( $data ) {

		// Setup session data if not set
		$session_instance = $this->get_set_session_instance();

		// If we're not tracking, dont enter data
		if ( ! $this->track_user() || ! $this->event_tracking_enabled ) {
			return array(
				'success' => true,
				'message' => __( 'Event tracking is disabled for this user.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'code' => 'tracking_disabled',
				'rows_inserted' => 0
			);
		}

		// Dont proceed if theyve been ip banned
		if ( $this->is_ip_banned() ) {
			return array(
				'success' => false,
				'message' => __( 'IP address is banned from event tracking.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'code' => 'ip_banned',
				'rows_inserted' => 0
			);
		}

		// If it's a CRON event, shouldn't be added to DB
        if (defined('DOING_CRON') && DOING_CRON) {
            return array(
				'success' => true,
				'message' => __( 'Event tracking skipped during CRON execution.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'code' => 'cron_skip',
				'rows_inserted' => 0
			);
        }

        // Dont bother in AJAX
        // if (defined('DOING_AJAX') && DOING_AJAX) {
        //     return array(
		// 		'success' => false,
		// 		'message' => __( 'Event tracking skipped during AJAX request.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		// 		'code' => 'ajax_skip',
		// 		'rows_inserted' => 0
		// 	);
        // }

		// Dont store any data from admin
		if ( is_admin() ) {
			return array(
				'success' => true,
				'message' => __( 'Event tracking skipped in admin area.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'code' => 'admin_skip',
				'rows_inserted' => 0
			);
		}

		// If there's no landing page in the session, probably not a real person.. causes issue in api calls
		if ( empty( $session_instance->landing_page ) ) {
			return array(
				'success' => false,
				'message' => __( 'Event tracking skipped: no landing page in session.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'code' => 'no_landing_page',
				'rows_inserted' => 0
			);
		}

		// Bot
		if ( $session_instance->is_bot ) {
			return array(
				'success' => true,
				'message' => __( 'Event tracking skipped: bot detected.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'code' => 'bot_detected',
				'rows_inserted' => 0
			);
		}

		// Block form submissions that contain cart classes, we'll track these through other means
		if ( $data['event_type'] == 'form_submit' && isset($data['additional_data']['form_element_class']) && strpos($data['additional_data']['form_element_class'], 'cart') !== false ) {
			return array(
				'success' => true,
				'message' => __( 'Event tracking skipped: cart form submission tracked via other means.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'code' => 'cart_form_skip',
				'rows_inserted' => 0
			);
		}

		$db_interactor 			= new WPDAI_Database_Interactor();
		$table_name 			= $db_interactor->events_table;

		// Cant be overriden
		$data['date_created_gmt'] 	= current_time( 'mysql', true ); // True = GMT

		// Defaults to session instance values, if not set or empty
		if ( ! isset($data['session_id']) || empty($data['session_id']) ) $data['session_id'] = $session_instance->session_id;
		if ( ! isset($data['ip_address']) || empty($data['ip_address']) ) $data['ip_address'] = $session_instance->ip_address;
		if ( ! isset($data['user_id']) || empty($data['user_id']) ) $data['user_id'] = $session_instance->user_id;
		if ( ! isset($data['page_href']) || empty($data['page_href']) ) $data['page_href'] = $session_instance->page_href;

		// We setup the object type and id during the hooks on this class
		if ( ! isset($data['object_type']) || empty($data['object_type']) ) $data['object_type'] = $this->object_type;
		if ( ! isset($data['object_id']) || empty($data['object_id']) ) $data['object_id'] = $this->object_id;

		// Event specific defaults? 
		if ( ! isset($data['event_type']) || empty($data['event_type']) ) $data['event_type'] = $this->event_type;
		if ( ! isset($data['event_quantity']) ) $data['event_quantity'] = $this->event_quantity;
		if ( ! isset($data['event_value']) || empty($data['event_value']) ) $data['event_value'] = $this->event_value;
		if ( ! isset($data['product_id']) || empty($data['product_id']) ) $data['product_id'] = $this->product_id;
		if ( ! isset($data['variation_id']) || empty($data['variation_id']) ) $data['variation_id'] = $this->variation_id;
		if ( ! isset($data['additional_data']) ) $data['additional_data'] = null;

		// Sanitize
		if (isset($data['additional_data']) && ! empty($data['additional_data']) && is_array($data['additional_data']) ) $data['additional_data'] = json_encode($data['additional_data']);
		if (isset($data['session_id'])) $data['session_id'] = sanitize_text_field($data['session_id']);
		if (isset($data['ip_address'])) $data['ip_address'] = sanitize_text_field($data['ip_address']);
		if (isset($data['user_id'])) $data['user_id'] = (int) $data['user_id'] ;
		if (isset($data['page_href'])) $data['page_href'] = esc_url_raw( $data['page_href'] );
		if (isset($data['object_type'])) $data['object_type'] = sanitize_text_field($data['object_type']);
		if (isset($data['object_id'])) $data['object_id'] = (int) $data['object_id'];
		if (isset($data['event_type'])) $data['event_type'] = sanitize_text_field($data['event_type']);
		if (isset($data['event_quantity'])) $data['event_quantity'] = (int) $data['event_quantity'];
		if (isset($data['event_value'])) $data['event_value'] = (float) $data['event_value'];
		if (isset($data['product_id'])) $data['product_id'] = (int) $data['product_id'];
		if (isset($data['variation_id'])) $data['variation_id'] = (int) $data['variation_id'];

		// Remove nonce if set
		if ( isset($data['nonce']) ) unset($data['nonce']);
		if ( isset($data['event-tracking-token']) ) unset($data['event-tracking-token']);

		// Check rate limiting (max 60 requests per minute)
		if ( $this->is_rate_limit_exceeded() ) {
			return array(
				'success' => false,
				'message' => __( 'Rate limit exceeded. Too many requests.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'code' => 'rate_limit_exceeded',
				'rows_inserted' => 0
			);
		}

		// Finally check over the data before inserting into DB
		if ( $this->block_request_by_data( $data ) ) {
			return array(
				'success' => false,
				'message' => __( 'Event tracking blocked: invalid or incomplete data.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'code' => 'invalid_data',
				'rows_inserted' => 0
			);
		}

		// Store / Update session in DB
		$session_instance->store_session_in_db();

		// Filter data before inserting into DB
		$data = apply_filters( 'wpd_ai_event_data_before_insertion', $data );

		// Insert data
		$rows_inserted = $db_interactor->add_row( $table_name, $data );
		
		// Return structured response
		if ( $rows_inserted > 0 ) {
			return array(
				'success' => true,
				'message' => sprintf(
					/* translators: %d: Number of rows inserted */
					_n( 'Event successfully tracked.', 'Events successfully tracked.', $rows_inserted, 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					$rows_inserted
				),
				'code' => 'success',
				'rows_inserted' => $rows_inserted
			);
		} else {
			return array(
				'success' => false,
				'message' => __( 'Failed to insert event into database.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'code' => 'insert_failed',
				'rows_inserted' => 0
			);
		}

	}

	/**
	 * 
	 * 	Checks for incomplete or dodgy requests, by data array
	 * 
	 * 	@var $data The data array to check
	 * 	@return bool True if we should block the request, false otherwise
	 * 
	 **/
	private function block_request_by_data( $data ) {

		// Setup var to allow filtering
		$block_request = false;

		// Empty / unstructured data array
		if ( ! is_array($data) || empty($data) ) {
			$block_request = true;
		}

		// Empty Page Href
		if ( ! isset($data['page_href']) || empty($data['page_href']) ) {
			$block_request = true;
		}

		// Page Href is not from the same domain
		$domain_url = wp_parse_url( get_site_url(), PHP_URL_HOST );
		if ( ! str_contains($data['page_href'], $domain_url) ) {
			$block_request = true;
		}

		// Filter
		$block_request = apply_filters( 'wpd_ai_event_tracking_block_request_by_data', $block_request, $data );

		// Return true if we should block the request
		return $block_request;

	}

	/**
	 * 
	 * 	Rate limiting check, maximum of 60 requests per minute
	 * 
	 *  Will result in an IP Ban for 24 hours
	 * 
	 * 	@return bool True if we've banned the IP and they've exceed the rate limit, otherwise false
	 * 
	 **/
	private function is_rate_limit_exceeded() {

		$session_instance = $this->get_set_session_instance();

		$maximum_requests_per_minute = 60;
		$transient_key = '_wpd_ip_requests_per_minute_' . $session_instance->ip_address;

		// Get requests per minute
		$requests_per_ip = (int) get_transient( $transient_key );
		$requests_per_ip++;

		// Update requests per minute
		$set_transient_rate = set_transient($transient_key, $requests_per_ip, 60);

		if ( $requests_per_ip > $maximum_requests_per_minute ) {
			$this->ban_ip_from_event_tracking();
			return true;
		}

		return false;

	}

	/**
	 * 
	 * 	Apply a ban to this ip_address for 24 hours
	 * 	$transient_key = '_wpd_ip_banned_event_tracking' . $this->ip_address;
	 * 
	 * 	@return bool True on succesful ban, false if failure (will fail if theres no ip_address)
	 * 
	 **/
	private function ban_ip_from_event_tracking() {

		$session_instance = $this->get_set_session_instance();

		// Ban for 24 hours
		$ban_duration = 60 * 60 * 24;
		$transient_key = '_wpd_ip_banned_event_tracking' . $session_instance->ip_address;

		if ( ! empty($session_instance->ip_address) ) {

			// Update the transient
			$update_transient = set_transient( $transient_key, 1, $ban_duration ); // Set to 1 = true

			return true;

		} else {

			return false;

		}

	}

	/**
	 * 
	 * 	Check if IP Address is banned for 24 hours
	 * 
	 **/
	private function is_ip_banned() {

		$session_instance = $this->get_set_session_instance();
		$transient_key 	= '_wpd_ip_banned_event_tracking' . $session_instance->ip_address;
		$ip_banned 		= (int) get_transient( $transient_key );

		if ( $ip_banned == 1 ) {

			return true;

		} else {

			return false;

		}

	}

	/**
     *
     *  Track user - calls the user settings
     *
     */
    public function track_user() {

		$session_instance = $this->get_set_session_instance();

    	// Default yes
    	$track_user = 1;

		// Dont track bots
		if ( $session_instance->is_bot ) {
			return 0;
		}

    	// Are we wanting to exclude any roles?
    	if ( isset($this->settings['exclude_roles']) && ! empty($this->settings['exclude_roles']) && is_array($this->settings['exclude_roles']) ) {

    		// Are they logged in?
    		if ( is_user_logged_in() ) {

	    		$user = wp_get_current_user();
	    		foreach( $this->settings['exclude_roles'] as $excluded_role ) {

	    			// Remove our added string in db
	    			$excluded_role = str_replace('exclude_', '', $excluded_role);

	    			// If one of the chosen exclusion roles are found in this user's list of roles, dont track
					if ( in_array( $excluded_role, (array) $user->roles ) ) {
					    //The user has this role
					    $track_user = 0;
					    break;
					}

	    		}

    		}

    	}

        return $track_user;

    }

	/**
     *
     *  Try to setup the object ID and post type, WP not great at it all the time
     * 
     */
    public function setup_object_type_id() {

        global $wp_query;

        // Main query - fire once
        if ( $wp_query->is_main_query() ) {

        	$queried_object = get_queried_object();
            $object_id = (int) $wp_query->get_queried_object_id();

            // Fallback for Object ID - Usually home page
            if ( ! $object_id && isset($wp_query->queried_object->ID) && ! empty($wp_query->queried_object->ID) ) {
                $object_id = (int) $wp_query->queried_object->ID;
            }

            $this->object_id = $object_id;

            // Set object type
            if ( isset($wp_query->queried_object->post_type) && ! empty($wp_query->queried_object->post_type) ) {

                $object_type = (string) $wp_query->queried_object->post_type;

            } elseif ( isset($wp_query->queried_object->taxonomy) && ! empty($wp_query->queried_object->taxonomy) ) {

                $object_type = (string) $wp_query->queried_object->taxonomy;

            } elseif ( is_a($queried_object, 'WP_Post_Type') && $queried_object->name == 'product'  ) {

            	// Shop archive doesnt bring up much data - cant get page id from WP_QUERY?
				$this->object_id = (int) wc_get_page_id( 'shop' );
                $object_type = 'shop';

            } elseif ( function_exists('is_front_page') && is_front_page() ) {

				$front_page_id = (int) get_option( 'page_on_front' );

				if ( $front_page_id ) {
					$this->object_id   = $front_page_id;
					$object_type = 'page';
				} else {
					// Latest posts homepage
					$this->object_id   = 0;
					$object_type = 'page';
				}

			} else {

            	$object_type = '';

            }

            $this->object_type = $object_type;

        }

    }

	/**
	 *
	 *	Track user logins
	 *  @see https://developer.wordpress.org/reference/hooks/authenticate/
	 *  @filter 'wp_login'
	 *
	 */
	public function db_track_logins( $login ) {

		$user = get_user_by('login',$login);
    	$user_id = (int) $user->ID;
		$data = array('event_type' => 'log_in', 'user_id' => $user_id, 'object_type' => 'form');
		$this->insert_event($data);

	}

	/**
	 *
	 *	Track user logins
	 *  @see https://developer.wordpress.org/reference/hooks/authenticate/
	 *  @filter 'wp_login'
	 *
	 */
	public function db_track_logouts( $user_id ) {

		$data = array('event_type' => 'log_out', 'object_type' => 'form', 'user_id' => $user_id);
		$this->insert_event($data);

	}

	/**
	 *
	 *	Track account creation
	 *  @hook woocommerce_created_customer
	 *  @param int $customer_id New customer (user) ID
	 *  @param array $new_customer_data Array of customer (user) data
	 *  @param string $password_generated The generated password for the account
	 *
	 */
	public function db_track_account_created( $customer_id, $new_customer_data, $password_generated ) {

		$data = array(
			'event_type' => 'account_created',
			'object_type' => 'form',
			'user_id' => (int) $customer_id,
			'additional_data' => array(
				'email' => isset($new_customer_data['user_email']) ? $new_customer_data['user_email'] : '',
				'username' => isset($new_customer_data['user_login']) ? $new_customer_data['user_login'] : '',
				'password_generated' => $password_generated ? 'yes' : 'no',
				'source' => isset($new_customer_data['source']) ? $new_customer_data['source'] : 'unknown'
			)
		);
		$this->insert_event($data);

	}

	/**
	 *
	 *	Tracks product purchases when the thankyou page is loaded
	 *  @hook woocommerce_thankyou
	 *
	 */
	public function db_track_products_purchased_thankyou_page( $order_id ) {

		// Prevent duplicate tracking - check meta and database
		if ( $this->has_order_been_tracked( $order_id ) ) {
			return false;
		}

		// Try to acquire lock to prevent race conditions
		if ( ! $this->acquire_tracking_lock( $order_id ) ) {
			// Another process is already tracking this order
			return false;
		}

		// Double-check database after acquiring lock (another process may have just inserted)
		if ( $this->has_order_been_tracked( $order_id ) ) {
			$this->release_tracking_lock( $order_id );
			return false;
		}

		$order = wc_get_order( $order_id );

		if ( ! is_a( $order, 'WC_Order' ) ) {
			$this->release_tracking_lock( $order_id );
			return false;
		}

		// Only track orders that are actually paid - disabled for now
		// if ( ! $order->is_paid() ) {
		// 	$this->release_tracking_lock( $order_id );
		// 	return false;
		// }

		// Track the overall transaction
		$transaction_data = array(
			'object_type' 		=> 'shop_order',
			'object_id' 			=> $order_id,
			'event_type' 		=> 'transaction',
			'event_value' 		=> $order->get_total(),
			'user_id' 			=> $order->get_customer_id(),
			'page_href' 		=> $this->get_order_source_url( $order ),
			'additional_data' 	=> array(
				'order_status' => $order->get_status(),
				'payment_method' => $order->get_payment_method(),
				'order_date' => $order->get_date_created()->format('Y-m-d H:i:s'),
				'customer_email' => $order->get_billing_email()
			)
		);

		$event_inserted = $this->insert_event( $transaction_data );

		// Mark this order as tracked to prevent duplicates
		// Check new structured response format
		if ( is_array( $event_inserted ) && isset( $event_inserted['success'] ) && $event_inserted['success'] === true && $event_inserted['rows_inserted'] === 1 ) {
			$this->mark_order_as_tracked( $order_id );
		}
		
		// Release the lock
		$this->release_tracking_lock( $order_id );

		// Send off google conversion event
		if ( function_exists('wpdai_schedule_once_off_cron_event_google_ads_profit_conversion_action_from_order_id') ) {
			wpdai_schedule_once_off_cron_event_google_ads_profit_conversion_action_from_order_id( 0, array( 'order_id' => $order_id ) );
		}

		// Track individual product purchases
		foreach( $order->get_items() as $item_id => $item ) {

			// Skip if not a product
			if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
				continue;
			}

			$product_id 	= $item->get_product_id();
			$quantity 		= $item->get_quantity();
			$value 			= (float) $item->get_total() + (float) $item->get_total_tax();
			$variation_id 	= $item->get_variation_id();

			$product_data = array(
				'object_type' 		=> 'shop_order',
				'object_id' 			=> $order_id,
				'event_type' 		=> 'product_purchase',
				'event_quantity' 	=> $quantity,
				'event_value' 		=> $value,
				'product_id' 		=> $product_id,
				'variation_id' 		=> $variation_id,
				'user_id' 			=> $order->get_customer_id(),
				'page_href' 		=> $this->get_order_source_url( $order ),
				'additional_data' 	=> array(
					'order_status' => $order->get_status(),
					'product_name' => $item->get_name(),
					'product_sku' => $item->get_product()->get_sku(),
					'line_total' => $item->get_total(),
					'line_tax' => $item->get_total_tax()
				)
			);

			$this->insert_event( $product_data );
		}

		return true;
	}

	/**
	 *
	 *	Tracks product purchases when the order status changes to a paid status
	 *  @hook woocommerce_order_status_changed
	 *
	 */
	public function db_track_products_purchased_on_order_status_change( $order_id, $old_status, $new_status, $order ) {

		$session_instance = $this->get_set_session_instance();

		// Prevent duplicate tracking - check meta and database
		if ( $this->has_order_been_tracked( $order_id ) ) {
			return false;
		}

		// Try to acquire lock to prevent race conditions
		if ( ! $this->acquire_tracking_lock( $order_id ) ) {
			// Another process is already tracking this order
			return false;
		}

		// Double-check database after acquiring lock (another process may have just inserted)
		if ( $this->has_order_been_tracked( $order_id ) ) {
			$this->release_tracking_lock( $order_id );
			return false;
		}

		if ( ! is_a( $order, 'WC_Order' ) ) {
			$this->release_tracking_lock( $order_id );
			return false;
		}

		// Ignore orders that were not created by a real customer checkout
		$created_via = $order->get_created_via();
		$ignored_sources = array(
			'admin',     // manually created in wp-admin
			'rest-api',  // created by external systems
			'import',    // imports / CSV tools
			'subscription', // renewal orders from Subscriptions plugin
			'wc-admin',  // programmatically created inside WC Admin
		);

		// If created via any ignored method, skip tracking
		if ( in_array( $created_via, $ignored_sources, true ) ) {
			$this->release_tracking_lock( $order_id );
			return false;
		}

		// Only orders that are actually paid
		if ( ! $order->is_paid() ) {
			$this->release_tracking_lock( $order_id );
			return false;
		}

		// Track the overall transaction
		$transaction_data = array(
			'object_type'     => 'shop_order',
			'object_id'       => $order_id,
			'event_type'      => 'transaction',
			'event_value'     => $order->get_total(),
			'user_id'         => $order->get_customer_id(),
			'page_href'       => wc_get_checkout_url(),
			'additional_data' => array(
				'order_status'   => $order->get_status(),
				'payment_method' => $order->get_payment_method(),
				'order_date'     => $order->get_date_created()->format( 'Y-m-d H:i:s' ),
				'customer_email' => $order->get_billing_email(),
			),
		);

		// Set session id manually if it exists in the order
		$session_id = $order->get_meta( '_wpd_ai_session_id' );
		if ( $session_id && empty($session_instance->session_id) ) {
			$session_instance->session_id = $session_id;
		}

		// Set landing page manually if it exists in the order
		$landing_page = $order->get_meta( '_wpd_ai_landing_page' );
		if ( $landing_page && empty($session_instance->landing_page) ) {
			$session_instance->landing_page = $landing_page;
		}

		$event_inserted = $this->insert_event( $transaction_data );

		// Mark this order as tracked to prevent duplicates
		// Check new structured response format
		if ( is_array( $event_inserted ) && isset( $event_inserted['success'] ) && $event_inserted['success'] === true && $event_inserted['rows_inserted'] === 1 ) {
			$this->mark_order_as_tracked( $order_id );
		}
		
		// Release the lock
		$this->release_tracking_lock( $order_id );

		// Send off google conversion event
		if ( function_exists('wpdai_schedule_once_off_cron_event_google_ads_profit_conversion_action_from_order_id') ) {
			wpdai_schedule_once_off_cron_event_google_ads_profit_conversion_action_from_order_id( 0, array( 'order_id' => $order_id ) );
		}

		// Track individual product purchases
		foreach ( $order->get_items() as $item_id => $item ) {

			if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) continue;

			$product_id   = $item->get_product_id();
			$quantity     = $item->get_quantity();
			$value        = (float) $item->get_total() + (float) $item->get_total_tax();
			$variation_id = $item->get_variation_id();

			$product_data = array(
				'object_type'     => 'shop_order',
				'object_id'       => $order_id,
				'event_type'      => 'product_purchase',
				'event_quantity'  => $quantity,
				'event_value'     => $value,
				'product_id'      => $product_id,
				'variation_id'    => $variation_id,
				'user_id'         => $order->get_customer_id(),
				'page_href'       => wc_get_checkout_url(),
				'additional_data' => array(
					'order_status' => $order->get_status(),
					'product_name' => $item->get_name(),
					'product_sku'  => $item->get_product() ? $item->get_product()->get_sku() : '',
					'line_total'   => $item->get_total(),
					'line_tax'     => $item->get_total_tax(),
				),
			);

			$this->insert_event( $product_data );
		}

		return true;
	}

	/**
	 *
	 *	Tracks product purchases when the order status changes to processing or completed
	 *  @hook woocommerce_order_status_changed
	 *
	 */
	public function db_track_failed_orders( $order_id, $old_status, $new_status, $order ) {

		// Not orders
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return false;
		}

		// Not in admin space
		if ( is_admin() ) {
			return false;
		}

		// Only track frontend checkout orders
		if ( ! in_array( $order->get_created_via(), array( 'checkout', 'store-api' ), true ) ) {
			return false;
		}

		// Only track orders that have reached a "failed" status
		if ( ! in_array( $new_status, array( 'failed', 'cancelled', 'on-hold' ), true ) ) {
			return false;
		}

		// Track the overall transaction
		$transaction_data = array(
			'object_type'     => 'shop_order',
			'object_id'       => $order_id,
			'event_type'      => 'transaction_' . $new_status,
			'event_value'     => $order->get_total(),
			'user_id'         => $order->get_customer_id(),
			'page_href'       => $this->get_order_source_url( $order ),
			'additional_data' => array(
				'order_status'   => $new_status,
				'payment_method' => $order->get_payment_method(),
				'order_date'     => $order->get_date_created()->format( 'Y-m-d H:i:s' ),
				'customer_email' => $order->get_billing_email(),
			),
		);

		// Set session id manually if it exists in the order
		$session_id = $order->get_meta( '_wpd_ai_session_id' );
		if ( $session_id ) $transaction_data['session_id'] = $session_id;

		$this->insert_event( $transaction_data );

		return true;

	}

	/**
	 *
	 *	Check if an order has already been tracked to prevent duplicates
	 *  Checks both the database for existing transaction events and order meta
	 *  Database check is the source of truth to prevent race conditions
	 *
	 */
	private function has_order_been_tracked( $order_id ) {

		// First check order meta (fast check)
		$tracked = get_post_meta( $order_id, '_wpd_analytics_tracked', true );
		if ( !empty($tracked) ) {
			return true;
		}

		// Also check database directly for existing transaction event (source of truth)
		// This prevents duplicates even if meta wasn't set, and handles race conditions
		// Using EXISTS with LIMIT 1 is much faster than COUNT(*) - stops at first match
		global $wpdb;
		$db_interactor = new WPDAI_Database_Interactor();
		$events_table = $db_interactor->events_table;

		// Optimized query: Use object_id first (most selective), then event_type (indexed), then object_type
		// LIMIT 1 stops scanning once a match is found - much faster than COUNT(*)
		$existing_event = $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM $events_table 
			WHERE object_id = %d 
			AND event_type = 'transaction' 
			AND object_type = 'shop_order' 
			LIMIT 1",
			$order_id
		) );

		if ( $existing_event === '1' ) {
			// Found existing transaction, mark in meta for future fast checks
			update_post_meta( $order_id, '_wpd_analytics_tracked', current_time( 'mysql' ) );
			return true;
		}

		return false;
	}

	/**
	 *
	 *	Acquire a lock for tracking an order (prevents race conditions)
	 *  Uses atomic operation - only adds if key doesn't exist
	 *
	 */
	private function acquire_tracking_lock( $order_id ) {

		$lock_key = '_wpd_analytics_tracking_lock_' . $order_id;
		// add_option only succeeds if the key doesn't exist (atomic operation)
		return add_option( $lock_key, current_time( 'mysql' ), '', 'no' );
	}

	/**
	 *
	 *	Release the tracking lock for an order
	 *
	 */
	private function release_tracking_lock( $order_id ) {

		$lock_key = '_wpd_analytics_tracking_lock_' . $order_id;
		delete_option( $lock_key );
	}

	/**
	 *
	 *	Mark an order as tracked to prevent duplicate tracking
	 *  Sets order meta as permanent record
	 *
	 */
	private function mark_order_as_tracked( $order_id ) {

		// Set order meta as permanent record
		update_post_meta( $order_id, '_wpd_analytics_tracked', current_time( 'mysql' ) );
	}

	/**
	 *
	 *	Get the source URL for an order (where the order was placed from)
	 *
	 */
	private function get_order_source_url( $order ) {

		// Try to get from order meta first
		$source_url = $order->get_meta( '_wpd_order_source_url' );
		if ( $source_url ) {
			return $source_url;
		}

		// Fallback to referer or site URL
		$referer = wp_get_referer();
		if ( $referer ) {
			return $referer;
		}

		return get_site_url();
	}

	/**
	 * 
	 *	Register the event tracking script 
	 * 
	 **/
	public function register_event_tracking_script() {

		$this->enable_logging = apply_filters( 'wpd_ai_event_tracking_enable_logging', $this->enable_logging );

		// Setup JS Tracking - see wpd-alpha-insights-event-tracking.js
		wp_register_script( 'wpd-alpha-insights-event-tracking', WPD_AI_URL_PATH . 'assets/js/wpd-alpha-insights-event-tracking.js', array( 'jquery' ), WPD_AI_VER, true );

		$wpd_ai_event_tracking_params = array(
			'api_endpoint' => $this->api_url,
			'current_post_type' => $this->object_type,
			'current_post_id' => $this->object_id,
			'track_engaged_sessions' => $this->only_track_engaged_sessions,
			'event_tracking_enabled' => $this->event_tracking_enabled,
			'analytics_event_tracking_token' => wpdai_get_analytics_event_tracking_token(), // This is used to protect the API from CSRF attacks
			'enbable_event_tracking_logging' => $this->enable_logging,
			'ajax_url' => admin_url('admin-ajax.php'),
		);

		// Server vars to pass onto frontend
		wp_localize_script( 'wpd-alpha-insights-event-tracking', 'wpdAlphaInsightsEventTracking', $wpd_ai_event_tracking_params );
		wp_enqueue_script( 'wpd-alpha-insights-event-tracking' );

		return $wpd_ai_event_tracking_params;

	}

	/**
	 *
	 *	Add class to product loop item to track clicks
	 *  @see wpd-alpha-insights-event-tracking.js	 
	 * 
	 */
	public function add_class_to_loop_item( $classes, $product ) {

		if ( method_exists($product, 'get_id') ) {

			$product_id_class = 'wpd-ai-product-id-' . $product->get_id();
	        $classes = array_merge([$product_id_class], $classes);

		}

		return $classes;

	}

	/**
	 *
	 *	Add an element to product loop item so that we can detect product ID on click
	 *  @see wpd-alpha-insights-event-tracking.js
	 *
	 */
	public function add_product_id_to_product_loop_item() {

		$id = get_the_ID();
		echo '<span class="wpd-ai-event-tracking-product-id" style="display:none;" data-product-id="' . esc_attr( $id ) . '"></span>';

	}

	/**
	 *
	 *	Tracks Product, Shop, Category page views.
	 * 	@hook woocommerce_add_to_cart
	 *
	 */
	public function db_track_product_add_to_cart( $cart_id, $product_id, $request_quantity, $variation_id, $variation, $cart_item_data ) {

		$session_instance = $this->get_set_session_instance();

		// Defaults
		$data = array();

		// Preference the referer in case we are using an AJAX call or it's been triggered from somewhere else
		$referral_url = wpdai_get_referral_url_raw();
		if ( isset($referral_url) && ! empty($referral_url) ) {
			$data['page_href'] = $referral_url;
		}

		$data['object_type'] 		= 'product';
		$data['event_type'] 		= 'add_to_cart';
		$data['event_quantity'] 	= $request_quantity;

		// Set Product ID
		if ( isset( $product_id ) && ! empty( $product_id ) ) {

			$data['product_id'] = $product_id;
			$data['object_id'] = $product_id;
			$product = wc_get_product($product_id);
			$data['event_value'] = (float) $product->get_price();

		}

		// Set variation ID
		if ( isset( $variation_id ) && ! empty( $variation_id ) ) {

			$data['variation_id'] = $variation_id;
			$variation = wc_get_product($variation_id);
			$data['event_value'] = (float) $variation->get_price();

		}

		if ( ! $variation_id && ! $product_id ) {
			return false;
		}

		// For debugging
		$data['additional_data'] = array( 'cart_id' => $cart_id );

		// Send off google conversion event
		$conversion_value = (float) $data['event_value'] * (int) $data['event_quantity'];
		if ( function_exists('wpdai_schedule_once_off_cron_event_google_ads_add_to_cart_conversion_action_from_gclid') ) {
			wpdai_schedule_once_off_cron_event_google_ads_add_to_cart_conversion_action_from_gclid( 0, array( 'landing_page' => $session_instance->landing_page, 'conversion_value' => $conversion_value ) );
		}

		// Add to DB
		$insert = $this->insert_event($data);
		return $insert;
		
	}

	/**
	 *
	 *	Check if we are doing an automatic process
	 *
	 */
	public function doing_cron_ajax() {

		// Dont bother in AJAX
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return true;
        }

        // Dont bother in CRON
        if (defined('DOING_CRON') && DOING_CRON) {
            return true;
        }

        return false;

	}

	/**
	 * 
	 * 	Log a message - if an error, always log & dont log to the normal route
	 *  Verbose logging can be enabled via the filter 'wpd_ai_event_tracking_enable_logging'
	 * 
	 * 	@param string $message The message to log
	 * 	@param bool $error Whether the log is an error
	 *  
	 * 	@return void
	 * 
	 */
	private function log( $message, $error = false ) {

		// If an error, always log & dont log to the normal route
		if ( $error ) {
			wpdai_write_log( $message, 'event-tracking-error' );
			return;
		}

		$this->enable_logging = apply_filters( 'wpd_ai_event_tracking_enable_logging', $this->enable_logging );

		if ( $this->enable_logging ) {
			wpdai_write_log( $message, 'event-tracking' );
		}

	}

}

// Init
$WPDAI_WooCommerce_Event_Tracking = new WPDAI_WooCommerce_Event_Tracking();