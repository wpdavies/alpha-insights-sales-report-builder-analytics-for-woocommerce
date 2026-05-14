<?php
/**
 * Data warehouse: orchestrates fetching and storage for all report entities.
 *
 * All entities are provided by registered data sources (see wpd_alpha_insights_register_data_sources).
 * Use fetch_data( array( 'orders', 'expenses', 'store_profit', ... ) ) as the single entry point;
 * the warehouse delegates to the appropriate data source and stores results in $this->data.
 * Deduplication is handled via fetched_custom_sources (each source is only run once per warehouse instance).
 *
 * @package Alpha Insights
 * @since 5.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 */
defined( 'ABSPATH' ) || exit;

class WPDAI_Data_Warehouse {

    /**
     * Report filter options. Passed into the constructor and merged with defaults.
     * Use get_filter( $key ) or get_filter() to read; update_filter( $key, $value ) to set.
     *
     * @var array<string, mixed> Known keys (others may be set by report config or data sources):
     *   - cache (bool): Whether to use cache. Default true (set in set_filter).
     *   - date_preset (string): Preset name; when set, overrides date_from/date_to via wpdai_get_dates_from_preset().
     *     One of: today, yesterday, this_week, this_month, last_month, month_to_date, this_year, last_year,
     *     last_7_days, last_30_days, last_90_days, ytd, all_time.
     *   - date_from (string): Start date (Y-m-d). Set from preset if date_preset is provided.
     *   - date_to (string): End date (Y-m-d). Set from preset if date_preset is provided.
     *   - date_format_display (string): Grouping for date axes. One of: day, month, quarter, year, minute. Default 'day'.
     *   - minutes_ago (int): When date_format_display is 'minute', number of minutes (1–60). Default 30.
     *   - date_format_string (string): Internal; set by set_data_by_date_containers() (e.g. 'Y-m-d', 'M Y').
     *   - data_filters (array): Entity-level filters for get_data_filter( $entity, $key ). Shape: [ entity => [ key => value ] ].
     *   - data_table_limit (array): Per-entity row limit for data_table. Shape: [ entity => int|false ]. 0 = unlimited, false = none.
     *   - comparison_date_selection (string): Preset for comparison period (e.g. previous_period).
     *   - comparison_date_from (string): Comparison start date (Y-m-d).
     *   - comparison_date_to (string): Comparison end date (Y-m-d).
     */
    protected array $filter = array();

    /**
     *
     *  Data by date containers
     *
     */
    protected array $data_by_date_containers = array();

    /**
     * 
     *  Stores any errors in request, useful for debugging
     * 
     **/
    protected array $errors = array();

    /**
     * 
     *  This store's currency as set by WooCommerce
     * 
     **/
    protected string $store_currency = '';

    /**
     * 
     *  Product Data Cache
     * 
     *  Used in case we need to call the same data over and over again
     * 
     **/
    protected array $product_cache = array();

    /**
     * 
     * 
     * Flag that states whether the memory has been exahusted for this report
     * 
     **/
    protected bool $memory_exhausted = false;

    /**
     * 
     *  The limit for the data table
     *  Keeps memory load in check
     * 
     *  @var int
     * 
     **/
    protected int $default_data_table_limit = 500;

    /**
     * Tracks which custom data sources (by spl_object_id) have already been fetched this request.
     * Prevents refetch when the same source provides multiple entities (e.g. Sales provides orders, products, etc.).
     *
     * @since 5.0.0
     * @var array<int, true>
     */
    public array $fetched_custom_sources = array();

    /**
     * Fetched data keyed by entity name (and special keys).
     *
     * Entity slots (orders, expenses, store_profit, etc.) are created on demand when
     * a data source fetches data via fetch_data() / fetch_custom_data_source_internal().
     * Do not prefill entity keys here; the registry is the source of truth for entities.
     *
     * Special keys always present:
     * - total_db_records (int): aggregate record count, updated by set_total_db_records().
     * - anonymous_queries (array): arbitrary query results keyed by query name.
     */
    protected array $data = array(
        'total_db_records' => 0,
        'anonymous_queries' => array(),
    );

    /** 
     *   Initialize the constructor for the data warehouse. Will load the filters & date containers on initialization
     * 
     *   @param array $filter The filter to be used on the data, expected to be passed as an array at this point
     *   @param array $data_by_date_containers An array of dates with empty values to use for reports
     *
     **/
    public function __construct( $filter = array() ) {

        // Configure Filter
        $this->set_filter( $filter );

        // Setup data by date
        $this->set_data_by_date_containers();

    }

    /**
     *
     *  Setup props, loads filter and date containers in if they are set
     * 
     * @param array $filter
     * @param array $data_by_date_containers
     * @return null
     *
     */
    private function set_filter( $filter ) {

        // Defaults
        $this->filter['cache'] = true;

        // Load passed filter
        $this->filter = array_merge( $this->filter, $filter );

        // Handle date presets
        $this->handle_date_presets();

    }

    /**
     * 
     *  Handle date presets, if found, this will override the date_from and date_to filter values
     * 
     */
    private function handle_date_presets() {

        if ( ! isset($this->filter['date_preset']) ) return;

        // Get dates from preset, returns an array with 'from' and 'to' dates, or false if not valid
        $date_preset_dates = wpdai_get_dates_from_preset( $this->filter['date_preset'] );

        // If we have dates, update the filter
        if ( $date_preset_dates ) {
            $this->filter['date_from'] = $date_preset_dates['from'];
            $this->filter['date_to'] = $date_preset_dates['to'];
        }

    }

    /**
     * 
     *  Updates the filter with the key value pair
     * 
     *  @return bool Returns true on success or false on failure
     * 
     **/
    public function update_filter( $key, $value ) {

        // Need a string as a key
        if ( ! is_string($key) ) {
            $this->set_error( sprintf(
                /* translators: %s: filter key value */
                __( 'Filter key %s needs to be a string.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                esc_html( (string) $key )
            ) );
            return false;
        }

        // Update filter
        $this->filter[$key] = $value;

        // Return success
        return true;

    }

    /**
     *
     *  Setup the date shells
     *
     */
    private function set_data_by_date_containers() {

        // Fetch details
        $min_date       = $this->get_selected_date_range('date_from');  // Date in the past, or older date
        $max_date       = $this->get_selected_date_range('date_to');    // Current Date, or later date
        $n_days_period  = $this->get_n_days_range();

        // Set Default Display Format
        if ( ! isset($this->filter['date_format_display']) ) $this->filter['date_format_display'] = 'day';

        /**
         *
         *  Construct date data
         *
         */
        if ( $this->filter['date_format_display'] == 'day' ) {

            $date_range = $this->get_date_range_array($min_date, $max_date, '+1 day', 'Y-m-d' );
            $date_format = 'Y-m-d';

        } elseif ( $this->filter['date_format_display'] == 'month' ) {

            $date_format = 'M Y';
            $date_range = $this->get_date_range_array($min_date, $max_date, '+1 day', $date_format );

        } elseif ( $this->filter['date_format_display'] == 'quarter' ) {

            $date_format = 'M Y';
            $date_range = $this->get_date_range_array($min_date, $max_date, '+3 months', $date_format ); // Changed step

        } elseif ( $this->filter['date_format_display'] == 'year' ) {

            $date_format = 'Y';
            $date_range = $this->get_date_range_array($min_date, $max_date, '+1 year', $date_format );

        } elseif( $this->filter['date_format_display'] == 'minute' ) {

            $minutes_ago = ( ! isset($this->filter['minutes_ago']) ) ? 30 : (int) $this->filter['minutes_ago'];
            if ( $minutes_ago <= 0 || $minutes_ago > 60 ) {
                $minutes_ago = 30;
            }
            $i = 0;
            $date_range = array();
            // Tie this to the minuts ago variable so I dont have to write it
            while ($i <= $minutes_ago) {
                $date_range[] = $i;
                $i++;
            }
            $date_format = 'Y-m-d H:i:s';

        } else {

            $date_format = 'Y-m-d';
            $date_range = $this->get_date_range_array($min_date, $max_date, '+1 day', $date_format );

        }

        $calculations_by_day = array(
            'Mon' => 0,
            'Tue' => 0,
            'Wed' => 0,
            'Thu' => 0,
            'Fri' => 0,
            'Sat' => 0,
            'Sun' => 0,
        );

        $calculations_by_time = array(
            '12am' => 0,
            '1am' => 0,
            '2am' => 0,
            '3am' => 0,
            '4am' => 0,
            '5am' => 0,
            '6am' => 0,
            '7am' => 0,
            '8am' => 0,
            '9am' => 0,
            '10am' => 0,
            '11am' => 0,
            '12pm' => 0,
            '1pm' => 0,
            '2pm' => 0,
            '3pm' => 0,
            '4pm' => 0,
            '5pm' => 0,
            '6pm' => 0,
            '7pm' => 0,
            '8pm' => 0,
            '9pm' => 0,
            '10pm' => 0,
            '11pm' => 0,
        );

        $date_formatting_container = array();
        foreach ( $date_range as $date_array_val ) {

            $date_formatting_container[$date_array_val] = 0;

        }

        // Return data
        $data_by_date_containers = array(

            'n_days_period' => $n_days_period,
            'date_format' => $date_format,
            'date_from' => $min_date,
            'date_to' => $max_date,
            'date_range_container' => $date_formatting_container,
            'calculations_by_day' => $calculations_by_day,
            'calculations_by_time' => $calculations_by_time

        );


        // Add the date format string to the filter so we can easily grab it later
        $this->update_filter( 'date_format_string', $date_format );

        // Set data by date containers
        $this->data_by_date_containers = $data_by_date_containers;

        // Return data by date containers
        return $data_by_date_containers;

    }

    /**
     * 
     *  Stores data in the data prop
     *  
     *  @param string $data_type The relevant data type, i.e. expense, orders, etc etc (see init $data prop for available types)
     * 
     **/
    public function set_data( $data_type, $data ) {

        // Data type passed is incorrect
        if ( ! is_string($data_type) ) {
            $this->set_error( sprintf(
                /* translators: %s: data type value passed */
                __( 'Trying to set data, %s is not a string.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                esc_html( (string) $data_type )
            ) );
            return false;
        }

        // Data payload is incorrect
        if ( ! is_array($data) ) {
            $this->set_error( __( 'Trying to set data: payload is not an array.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) );
            return false;
        }

        // Store the data
        foreach ( $data as $key => $value ) {

            // Anonymous Queries must be set with an associative array
            if ( $data_type === 'anonymous_queries' && ! is_string($key) ) {
                $this->set_error( __( 'Trying to set an anonymous key without a string as the key, check that you have passed an associative array.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) );
                continue;
            }

            $this->data[ $data_type ][ $key ] = $value;
        }

        // Update the total record count
        $this->set_total_db_records();

    }

    /**
     * 
     *  Checks all stored db records and sets the data property accordingly
     * 
     **/
    public function set_total_db_records() {

		// Get the array keys
		$array_keys = array_keys( $this->get_data() );

		// Set the default count
		$count = 0;

		// Loop through the keys
		foreach( $array_keys as $data_type ) {
			if ( isset($this->data[$data_type]['total_db_records']) ) {
				$count += $this->data[$data_type]['total_db_records'];
			}
		}

        // Update our data
        $this->data['total_db_records'] = $count;

        // Return result
		return $count;

	}

    /**
     * 
     *  Sets an error in the class
     * 
     **/
    public function set_error( $error ) {

        // This method does most of the error handling
        $this->log( $error, true );

    }

    /**
     * 
     *  Returns data by date containers
     * 
     **/
    public function get_data_by_date_containers() {

        // Set the containers if not set yet
        if ( empty($this->data_by_date_containers) ) {
            $this->set_data_by_date_containers();
        }

        return $this->data_by_date_containers;

    }

    /**
     * 
     *  Gets the start date we are using for this request
     * 
     **/
    public function get_date_from( $format = 'Y-m-d' ) {

        return $this->get_selected_date_range('date_from', $format);

    }

    /**
     * 
     *  Gets the start date we are using for this request
     * 
     **/
    public function get_date_to( $format = 'Y-m-d' ) {

        return $this->get_selected_date_range('date_to', $format);

    }

    /**
     *
     *  Returns selected date range
     *  Accepts date_from or date_to
     * 
     *  @todo Get rid of this and move it into get_date_from() and get_date_to()
     *
     */
    public function get_selected_date_range( $result = 'date_from', $format = 'Y-m-d' ) {

        $days_in_past   = (string) '-' . $this->get_n_days_range() . ' days';
        $wp_timestamp   = current_time( 'timestamp' );

        if ( $result == 'date_from' ) {

            $start = gmdate($format, strtotime( $days_in_past, $wp_timestamp ) ); // this needs to be based on wp time as below

            if ( isset( $this->filter['date_from'] ) && ! empty($this->filter['date_from']) ) {
                $start = gmdate( $format, strtotime($this->filter['date_from']) );
            }

            return $start;

        } elseif ( $result == 'date_to' ) {

            $end = current_time( $format ); 

            if ( isset($this->filter['date_to']) && ! empty($this->filter['date_to']) ) {
                $end = gmdate( $format, strtotime($this->filter['date_to']));
            }

            return $end;

        }

    }

    /**
     *
     *  @return array $dates Returns array of dates within defined range
     *
     */
    public function get_date_range_array( $first, $last, $step = '+1 day', $output_format = 'Y-m-d' ) {

        $dates              = array();
        $current_date       = strtotime($first);
        $date_to           = strtotime($last);

        while( $current_date <= $date_to ) {

            $dates[] = gmdate($output_format, $current_date);
            $current_date = strtotime($step, $current_date);

        }

        return array_values( array_unique( $dates ) );

    }

    /**
     *
     *  Check if we are limiting range by X days
     *
     */
    public function get_n_days_range() {

        // Defaults
        $days = 30;

        if ( isset( $this->filter['date_from'] ) && isset( $this->filter['date_to'] ) ) {

            $start  = new DateTime( $this->filter['date_from'] );
            $end    = new DateTime( $this->filter['date_to'] );

            // Only execute this if we're sure these are now objects
            if ( is_a($start, 'DateTime') && is_a($end, 'DateTime') ) {

                $days = $end->diff( $start )->format('%a') + 1;
                return $days;

            }

        }

        return $days;

    }

    /**
	 *
	 *	Count of days between two dates
     *
	 * 	@param int $date_from Unix timestamp start date
	 * 	@param int $date_to Unix timestamp end date
	 *
	 */
	public function days_between_dates( $date_from, $date_to ) {

        // Difference in seconds
		$datediff = $date_to - $date_from;

        // Difference in days
        return wpdai_divide( $datediff, (60 * 60 * 24) );

	}

    /**
     * 
     *  Returns the currently set filter
     * 
     *  @return array|string|int Returns all active filters on this instance of the class, or a particular key if set
     * 
     **/
    public function get_filter( $key = null, $default = null ) {

        // Check if they've passed a key in
        if ( ! is_null($key) ) {

            // Return their key if found
            if ( isset($this->filter[$key]) ) {
                return $this->filter[$key];
            }

            // Otherwise false, we couldn't find it. Return the default
            return $default;

        }

        // Return all filters
        return $this->filter;

    }

    /**
     * Returns the applied data filter for the chosen entity and key.
     *
     * Booleans are returned as-is (so false is a valid value). Product-picker filters (`products.products`,
     * `website_traffic.product_id`) accept arrays or scalars and resolve tokens to WooCommerce IDs. Tokens that resolve to nothing
     * contribute a sentinel (-1) once alongside real IDs (-1 matches no real product in intersections / SQL IN). Other
     * scalars use sanitize_text_field; other arrays are sanitized recursively. Numbers are returned unchanged unless they belong
     * to those product-picker keys.
     *
     * @param string $entity  Entity key under data_filters.
     * @param string $key     Filter key.
     * @param mixed  $default Optional. When the key is absent (or stored value is null), return this. If the argument is omitted, missing keys return false (legacy behaviour).
     * @return array|string|int|float|bool|mixed
     */
    public function get_data_filter( $entity, $key, $default = null ) {

        $data_filters   = $this->get_filter( 'data_filters', array() );
        $default_passed = func_num_args() >= 3;

        if ( ! isset( $data_filters[ $entity ] ) || ! array_key_exists( $key, $data_filters[ $entity ] ) ) {
            return $default_passed ? $default : false;
        }

        $value = $data_filters[ $entity ][ $key ];

        if ( is_bool( $value ) ) {
            return $value;
        }

        if ( null === $value ) {
            return $default_passed ? $default : false;
        }

        if ( $this->is_expandable_product_filter_entity_key( $entity, $key ) ) {
            /** @var list<mixed> $normalized_tokens */
            $normalized_tokens = array();

            if ( is_array( $value ) ) {
                $normalized_tokens = $this->sanitize_recursive( $value );
            } elseif ( is_int( $value ) || is_float( $value ) ) {
                $as_float = (float) $value;
                // Whole-number scalars behave like typed product IDs before title/SKU substring resolution.
                if ( floor( $as_float ) === $as_float ) {
                    $normalized_tokens = array( (string) (int) $value );
                } else {
                    $normalized_tokens = array( sanitize_text_field( (string) $value ) );
                }
            } elseif ( is_string( $value ) ) {
                $normalized_tokens = array( sanitize_text_field( $value ) );
            }

            return $this->expand_product_filter_values_to_product_ids( $normalized_tokens );
        }

        if ( is_int( $value ) || is_float( $value ) ) {
            return $value;
        }

        if ( is_array( $value ) ) {
            return $this->sanitize_recursive( $value );
        }

        return $this->sanitize_recursive( $value );
    }

    /**
     * Product-picker entity keys resolved to WooCommerce product/variation IDs in get_data_filter().
     *
     * @param string $entity Entity under data_filters.
     * @param string $key    Filter field key.
     * @return bool
     */
    private function is_expandable_product_filter_entity_key( $entity, $key ) {

        foreach ( array( array( 'products', 'products' ), array( 'website_traffic', 'product_id' ) ) as $pair ) {
            if ( $entity === $pair[0] && $key === $pair[1] ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Turn mixed filter entries into unique published product/variation IDs (as strings).
     *
     * If a token resolves to zero IDs, a single sentinel (-1 default) is appended after successful IDs; intersections and
     * SQL IN lists still match real IDs, while -1 matches nothing.
     *
     * @param array<mixed> $values Sanitized tokens; associative rows may expose a scalar `value` key.
     * @return list<string>
     */
    private function expand_product_filter_values_to_product_ids( array $values ) {

        /** Default -1 matches no WooCommerce post ID when used in queries. Override via {@see 'wpd_ai_product_filter_unresolved_sentinel_id'}. */
        $sentinel = (string) (int) apply_filters( 'wpd_ai_product_filter_unresolved_sentinel_id', -1 );

        $result               = array();
        $already_seen         = array();
        $attempted_token      = false;
        $append_sentinel_miss = false;

        foreach ( $values as $raw ) {

            if ( is_array( $raw ) ) {
                if ( isset( $raw['value'] ) && ! is_array( $raw['value'] ) ) {
                    $raw = $raw['value'];
                } else {
                    continue;
                }
            }

            $token = sanitize_text_field( (string) $raw );
            if ( '' === $token ) {
                continue;
            }

            $attempted_token = true;

            $resolved_ids = array();

            if ( ctype_digit( $token ) ) {
                $maybe_id = (int) $token;
                if ( $maybe_id > 0 && $this->published_product_post_exists( $maybe_id ) ) {
                    $resolved_ids[] = $maybe_id;
                }
            }

            if ( empty( $resolved_ids ) ) {
                $resolved_ids = $this->find_product_ids_by_string_search( $token );
            }

            if ( empty( $resolved_ids ) ) {
                $append_sentinel_miss = true;
                continue;
            }

            foreach ( $resolved_ids as $pid ) {
                $pid = (int) $pid;
                if ( $pid <= 0 || isset( $already_seen[ $pid ] ) ) {
                    continue;
                }
                $already_seen[ $pid ] = true;
                // String IDs align with array keys from order product_data and JS filter payloads.
                $result[] = (string) $pid;
            }
        }

        if ( ! $attempted_token ) {
            return array();
        }

        if ( $append_sentinel_miss ) {
            $result[] = $sentinel;
        }

        return $result;
    }

    /**
     * Whether a post is a published product or product variation.
     *
     * @param int $product_id Post ID.
     * @return bool
     */
    private function published_product_post_exists( $product_id ) {

        $post = get_post( (int) $product_id );
        if ( ! $post || 'publish' !== $post->post_status ) {
            return false;
        }

        return in_array( $post->post_type, array( 'product', 'product_variation' ), true );
    }

    /**
     * Find product or variation IDs where the title or SKU contains the search string.
     *
     * @param string $search_value User input (already sanitized).
     * @return int[] Unique post IDs, may be empty.
     */
    private function find_product_ids_by_string_search( $search_value ) {

        $search_value = sanitize_text_field( $search_value );
        if ( '' === $search_value ) {
            return array();
        }

        global $wpdb;

        $limit_per_leg = (int) apply_filters( 'wpd_ai_find_product_ids_by_string_search_per_query_limit', 100 );
        if ( $limit_per_leg < 1 ) {
            $limit_per_leg = 100;
        }

        $like = '%' . $wpdb->esc_like( $search_value ) . '%';

        $title_sql = $wpdb->prepare(
            "SELECT DISTINCT ID FROM {$wpdb->posts} WHERE post_type IN ('product', 'product_variation') AND post_status = %s AND post_title LIKE %s LIMIT %d",
            'publish',
            $like,
            $limit_per_leg
        );

        $sku_sql = $wpdb->prepare(
            "SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} AS pm INNER JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value LIKE %s AND p.post_type IN ('product', 'product_variation') AND p.post_status = %s LIMIT %d",
            '_sku',
            $like,
            'publish',
            $limit_per_leg
        );

        /** @var list<int|string|false>|null */
        $from_title = $wpdb->get_col( $title_sql );
        /** @var list<int|string|false>|null */
        $from_sku   = $wpdb->get_col( $sku_sql );

        /** @var int[] */
        $ids = array();
        foreach ( array_merge( (array) $from_title, (array) $from_sku ) as $maybe_id ) {
            $maybe_id = (int) $maybe_id;
            if ( $maybe_id > 0 ) {
                $ids[] = $maybe_id;
            }
        }

        $ids = array_values( array_unique( $ids ) );

        /** @var int */
        $max_total = (int) apply_filters( 'wpd_ai_find_product_ids_by_string_search_total_limit', 250 );
        if ( count( $ids ) > $max_total ) {
            $ids = array_slice( $ids, 0, $max_total );
        }

        return array_map(
            'intval',
            apply_filters( 'wpd_ai_find_product_ids_by_string_search_results', $ids, $search_value )
        );
    }

    /**
     * Recursively sanitize a value or array of values.
     *
     * @param mixed $value Input.
     * @return mixed
     */
    private function sanitize_recursive( $value ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $k => $v ) {
                $value[ $k ] = $this->sanitize_recursive( $v );
            }
            return $value;
        }

        return sanitize_text_field( $value );
    }

    /**
     * 
     *  Data Return Function
     * 
     *  If no params are set, it will return all data
     *  If only the data_type is set, it will return all found data for that data_type
     *  If the data_type and data_key are set and found, will return that payload
     *  
     *  @param string $data_type The data type, i.e. analytics, orders etc..
     *  @param string $data_key The data_key to fetch for, will return that specific key's data if found with the data_type. Accepts totals, data_table, data_by_date & total_db_records
     * 
     *  @return array|bool Returns the requested data or false if we couldn't resolve the request
     * 
     **/
    public function get_data( $data_type = false, $data_key = false ) {

        // Insufficient parameters passed, return all data
        if ( $data_type === false && $data_key === false ) {
            return $this->data;
        }

        // Return all data for the data type if found and no data_key is set
        if ( isset( $this->data[$data_type] ) && $data_key === false ) {
            return $this->data[$data_type];
        }

        // Try and return their target key if found
        if ( isset( $this->data[$data_type] ) && isset($this->data[$data_type][$data_key]) ) {
            return $this->data[$data_type][$data_key];
        }

        // Incorrect query
        return false;

    }

    /**
     * 
     *  Gets the execution time for the data warehouse
     * 
     *  @return array The execution time for each data type and the total execution time
     * 
     **/
    public function get_execution_time() {

        $total_execution_time = 0;
        $execution_time_array = array();

        foreach( $this->data as $data_type => $data ) {

            if ( isset($data['execution_time']) && is_numeric($data['execution_time']) && $data['execution_time'] > 0 ) {

                $execution_time_array[$data_type] = $data['execution_time'];
                $total_execution_time += $data['execution_time'];

            }

        }

        $response = array_merge( $execution_time_array, array( 'total' => $total_execution_time ) );

        return $response;

    }

    /**
     * 
     *  Gets all set errors
     * 
     **/
    public function get_errors() {

        return $this->errors;

    }

    /**
     * 
     *  Gets the total record count
     * 
     **/
    public function get_total_db_records() {

        // Found it
        if ( isset($this->data['total_db_records']) ) {
            return $this->data['total_db_records'];
        }

        // Doesnt exist?
        return 0;

    }

    /**
     * 
     *  Returns the flattened date range container (empty) to use in the javascript graphs
     * 
     *  @return array An associative array with the date as the key and the value directly
     * 
     **/
    public function get_data_by_date_range_container() {

        // @todo Do some checks on this
        return $this->data_by_date_containers['date_range_container'];

    }

    /**
     * 
     *  Returns a simple data array with the day of the week as the key and the total as an int.
     *  Date format in accordance with date('D', $date_string_unix );
     * 
     *  @return array Associative array
     * 
     **/
    public function get_data_by_day_container() {

        $data_by_day = array(
			'Mon' => 0,
			'Tue' => 0,
			'Wed' => 0,
			'Thu' => 0,
			'Fri' => 0,
			'Sat' => 0,
			'Sun' => 0,
		);

        $this->data_by_date_containers['data_by_day_container'] = $data_by_day;

        return $data_by_day;

    }

    /**
     * 
     *  Returns a simple data array with the hour of the day as the key and the total as an int
     * 
     *  Date format in accordance with date( 'ga', $date_string_unix )
     * 
     *  @return array Associative array
     * 
     **/
    public function get_data_by_hour_container() {

        $data_by_hour = array(
			'12am' => 0,
			'1am' => 0,
			'2am' => 0,
			'3am' => 0,
			'4am' => 0,
			'5am' => 0,
			'6am' => 0,
			'7am' => 0,
			'8am' => 0,
			'9am' => 0,
			'10am' => 0,
			'11am' => 0,
			'12pm' => 0,
			'1pm' => 0,
			'2pm' => 0,
			'3pm' => 0,
			'4pm' => 0,
			'5pm' => 0,
			'6pm' => 0,
			'7pm' => 0,
			'8pm' => 0,
			'9pm' => 0,
			'10pm' => 0,
			'11pm' => 0,
		);

        $this->data_by_date_containers['data_by_hour_container'] = $data_by_hour;

        return $data_by_hour;

    }

    /**
     * 
     *  Returns store currency
     * 
     *  @return string The currency code
     * 
     **/
    public function get_store_currency() {

        // Only load it if this has been called
        if ( empty($this->store_currency) ) {
            $this->store_currency = wpdai_get_store_currency();
        }

        // Safety Check
        if ( empty($this->store_currency) || ! is_string($this->store_currency) ) {
            $this->set_error('Couldn\'t determine a store currency.');
            return false;
        }

        // Return results
        return $this->store_currency;

    }

    /**
     * 
     *  Returns the limit for the data table
     *  Looks for filters set in data_table_limit by entity.
     *  0 = Unlimited
     *  false = Return nothing
     *  numeric = Return the limit
     *  If no entity is found, will return the default set in the properties
     * 
     *  @param string $entity The entity to get the limit for
     *  @return int The limit for the data table
     * 
     **/
    public function get_data_table_limit( $entity = null ) {

        $limit = $this->default_data_table_limit;

        // If no entity is set, return the default limit
        if ( ! $entity ) {
            return $limit;
        }

        $data_table_limit = $this->get_filter( 'data_table_limit' );

        if ( is_array($data_table_limit) && isset($data_table_limit[$entity]) ) {

            if ( $data_table_limit[$entity] === 0 || $data_table_limit[$entity] === -1 || $data_table_limit[$entity] === '0' || $data_table_limit[$entity] === '-1' ) {

                return PHP_INT_MAX;

            } elseif ( $data_table_limit[$entity] === false ) {

                return 0;

            } elseif ( is_numeric($data_table_limit[$entity]) ) {

                return (int) $data_table_limit[$entity];

            }

        }

        // If the entity is set, return the limit for the entity
        return $limit;

    }

    /**
     * 
     *  Retrieves Product Data Cache from DB or sets it if not set
     * 
     *  Will also cache the results incase it needs to get called again in the same query
     * 
     *  @param int Product ID
     *  @return array The product data cache including name, sku, tags, categories etc..
     * 
     **/
    public function get_product_data_cache( $product_id ) {

        // Try and call this from the prop cache if available
        if ( isset($this->product_cache[$product_id]) && is_array($this->product_cache[$product_id]) && ! empty($this->product_cache[$product_id]) ) {

            return $this->product_cache[$product_id];

        }

        // Try and get cached version from DB
        $product_data_store = get_post_meta( $product_id, '_wpd_ai_product_data_store', true );

        // Run and store a product collection if we couldnt find a data store
        if ( ! is_array($product_data_store) || empty($product_data_store) ) {

            $product_data_store 	    = wpdai_product_data_collection( $product_id );
            $product_data_store_update 	= update_post_meta( $product_id, '_wpd_ai_product_data_store', $product_data_store );    

        }

        // Cache results in prop
        $this->product_cache[$product_id] = $product_data_store;

        // Return Results
        return $product_data_store;

    }

    /**
     *
     *  Creates an empty no data found array
     *
     *  @param array $data_by_date The data by date array containing all data_by_date keys
     *  @return array The data by date array with the no data found array added
     * 
     * 
     **/
    public function maybe_create_no_data_found_date_array( $data_by_date ) {

        if ( is_array($data_by_date) && ! empty($data_by_date) ) {

            foreach( $data_by_date as $data_key => $date_data ) {

                if ( ! is_array($date_data) || empty($date_data) ) $data_by_date[$data_key]['no_data_found'] = $this->get_data_by_date_range_container();

            }

        }

        return $data_by_date;

    } 

    /**
     * 
     *  Converts a date from one format of string to another
     * 
     **/
    public function convert_date_string( $date, $format = 'date_format_string' ) {

        // Need a string in this
        if ( ! is_string($date) ) {
            $this->set_error( 'Date passed into convert_date_string is not of type string.' );
            return false;
        }

        // If we are using the date_format_string
        if ( $format === 'date_format_string' ) {
            $format = $this->get_filter('date_format_string');
        }

        // Convert to timestamp
        $timestamp = strtotime( $date );

        // Convert date
        $converted_date = gmdate( $format, $timestamp );

        // Return result
        return $converted_date;

    }

    /**
     *
     *  Returns date in format for the date container
     *  @param string $date The date to reformat
     *  @return string The reformatted date
     *
     */
    public function reformat_date_to_date_format( $date ) {
        
        if ( ! is_string($date) ) {
            $this->set_error( 'Date passed into reformat_date_to_date_format is not of type string.' );
            return false;
        }

        if ( '' === trim( $date ) ) {
            return '';
        }

        // If we are doing minutes, this is a special case
        if ( $this->get_filter('date_format_display') == 'minute' ) {
            $timestamp = strtotime($date);
            $minutes_ago = floor((current_time('timestamp') - $timestamp) / 60);
            return (int) $minutes_ago;
        }

        $date_container_date_format = $this->get_filter('date_format_string');
        $formatted_date = gmdate( $date_container_date_format, strtotime($date) );

        return $formatted_date;

    }

    /**
     *
     *  Calculates difference in seconds between two dates - used for session duration
     *
     */
    public function calculate_difference_in_seconds( $recent_date, $old_date ) {

        if ( is_null($recent_date) || is_null($old_date) ) {
            return 0;
        }

        $recent_date_string = strtotime($recent_date);
        $old_date_string    = strtotime($old_date);
        (int) $difference_in_seconds = $recent_date_string - $old_date_string;

        return $difference_in_seconds;

    }

    /**
     *
     *  Checks for Query Parameters and returns as associated array if found
     *
     */
    public function get_url_components( $url ) {

        $result = array(
            'url' => $url,
            'path' => null,
            'query_parameters' => array()
        );

        if ( is_null($url) ) return $result;

        // Prevents issues with 038;
        $url = htmlspecialchars_decode( $url );
        $result['url'] = $url;

        $parsed_url = wp_parse_url( $url );

        if ( isset($parsed_url['path']) && ! empty($parsed_url['path']) ) {
            $result['path'] = $parsed_url['path'];
        }

        // Only collect query params
        $query_parameters = wp_parse_url( $url, PHP_URL_QUERY );

        if ( ! empty($query_parameters) ) {

            $result['decoded_qp'] = $query_parameters;
            parse_str( $query_parameters, $result['query_parameters'] );

        }

        return $result;

    }

    /**
     *
     *  Calculate traffic source type
     *
     */
    public function determine_traffic_source( $referral_url, $query_parameters = null ) {

        $traffic_type = new WPDAI_Traffic_Type_Detection( $referral_url, $query_parameters );
        return $traffic_type->determine_traffic_source();

    }

    /**
     * 
     *  Retrieves the HTML display badge for a product
     * 
     *  @param int $product_id The product ID
     *  @return string The HTML display badge
     * 
     **/
    public function get_product_html_display_badge( $product_id ) {

        $product_data = $this->get_product_data_cache( $product_id );

        if ( ! is_array($product_data) || empty($product_data) ) {
            return '';
        }

        ob_start(); ?>
        <div class="wpd-product-display">
            <div class="wpd-product-image-wrapper"><img src="<?php echo esc_url( $product_data['product_image'] ); ?>" class="wpd-product-thumbnail"></div>
            <div class="wpd-product-info-wrapper">
                <a href="<?php echo esc_url( $product_data['product_link'] ); ?>" target="_blank"><?php echo esc_html( $product_data['product_name'] ); ?></a>
                <span class="wpd-product-sku wpd-subtext"><?php echo esc_html( $product_data['product_sku'] ); ?></span>
                <?php if ( isset($product_data['variation_attributes']) && ! empty($product_data['variation_attributes']) && is_array($product_data['variation_attributes']) ) : ?>
                    <div class="wpd-product-variation-attributes">
                        <?php foreach ( $product_data['variation_attributes'] as $attribute => $value ) : ?>
                            <?php if ( empty($attribute) || empty($value) ) : continue; endif; ?>
                            <span class="wpd-product-variation-attribute"><?php echo esc_html( $attribute . ': ' . $value ); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php return ob_get_clean();

    }

    /**
     * 
     *  Logs general messages and optionally errors to:
     *  WPDAI_Google_Ads_API_log.txt and WPDAI_Google_Ads_API_error_log.txt
     * 
     *  @param string|array|WP_Error $message the content to print to the log
     *  @param bool $error Set to true if you want to log this to the error log in addition to the general api log
     *  @return void
     * 
     **/
    protected function log( $message, $error = false ) {

        // Setup backtrace
        $backtrace = debug_backtrace();
        $last_call = $backtrace[1]['function'];
        if ( $last_call == 'set_error' ) $last_call = $backtrace[2]['function'];

        if ( $error ) {

            // WP Error
            if ( is_a( $message, 'WP_Error' ) ) {
                $error_message = (string) $message->get_error_message();
            }

            // Array
            if ( is_array($message) || is_object($message) ) {
                $error_message = (string) json_encode( $message );
            }

            // Confirm string
            if ( is_string($message) ) {
                $error_message = (string) $message;
            }

            // Save the errors to instance
            if ( $error_message ) {
                $this->errors[] = $error_message;
            }

            // Log errors
            wpdai_write_log( 'Backtrace function: ' . $last_call, 'data_warehouse_error' );
            wpdai_write_log( $message, 'data_warehouse_error' );
            
        }

        // Log the message
        wpdai_write_log( $message, 'data_warehouse' );

        return $message;

    }

    /**
     * Fast replacement for get_date_from_gmt().
     * Converts a GMT date string to site local time with caching.
     *
     * @param string|null $date_gmt Date string in GMT (Y-m-d H:i:s), or null/empty when unknown.
     * @param string        $format   Return format. Default 'Y-m-d H:i:s'.
     *
     * @return string Local time formatted string, or empty string if input was not a non-empty string.
     */
    public function get_date_from_gmt( $date_gmt, $format = 'Y-m-d H:i:s' ) {

        static $gmt_date_cache = [];
        static $site_timezone = null;

        if ( ! is_string( $date_gmt ) || '' === trim( $date_gmt ) ) {
            return '';
        }

        if ( isset( $gmt_date_cache[ $date_gmt ][ $format ] ) ) {
            return $gmt_date_cache[ $date_gmt ][ $format ];
        }

        if ( $site_timezone === null ) {
            // wp_timezone() is cached internally after the first call
            $site_timezone = wp_timezone();
        }

        $datetime = new DateTime( $date_gmt, new DateTimeZone( 'UTC' ) );
        $datetime->setTimezone( $site_timezone );

        $local = $datetime->format( $format );

        // Cache the result
        $gmt_date_cache[ $date_gmt ][ $format ] = $local;

        return $local;

    }

    /**
     * Get the total count of analytics records for the current filters.
     *
     * Delegates to the registered Analytics data source when present.
     *
     * @since 5.0.0
     * @return int|false Total count of records or false on error.
     */
    public function get_analytics_event_count() {
        $source = WPDAI_Custom_Data_Source_Registry::get( 'analytics' );
        if ( ! $source || ! is_callable( array( $source, 'get_analytics_event_count' ) ) ) {
            return false;
        }
        return call_user_func( array( $source, 'get_analytics_event_count' ), $this );
    }

    /**
     * Get the total count of sessions for the current filters.
     *
     * Delegates to the registered Analytics data source when present.
     *
     * @since 5.0.0
     * @return int|false Total count of sessions or false on error.
     */
    public function get_analytics_session_count() {
        $source = WPDAI_Custom_Data_Source_Registry::get( 'analytics' );
        if ( ! $source || ! is_callable( array( $source, 'get_analytics_session_count' ) ) ) {
            return false;
        }
        return call_user_func( array( $source, 'get_analytics_session_count' ), $this );
    }

    /**
     * Single entry point to fetch data for requested entities.
     *
     * All entities are served by registered data sources (loaded and registered in the main
     * plugin loader). Call fetch_data( array( 'orders', 'products', 'expenses', 'store_profit' ) );
     * data is stored in $this->data['entity_name'] and returned keyed by entity name. Each
     * source is only fetched once per warehouse instance.
     *
     * Methods that need dependency data (e.g. store_profit) should call
     * $this->fetch_data( array( 'orders', 'expenses' ) ) so fetching is deduplicated.
     *
     * @since 5.0.0
     * @param array $entity_names List of entity names to fetch (e.g. array( 'orders', 'products', 'expenses' )).
     * @return array<string, array> Data for the requested entities, keyed by entity name.
     *         Each value is the entity's data structure (totals, data_by_date, etc.).
     *         Only includes entities that were requested and are available in $this->data.
     */
    public function fetch_data( array $entity_names ) {
        $entity_names = array_unique( array_filter( array_map( 'strval', $entity_names ) ) );
        $result = array();

        if ( empty( $entity_names ) ) {
            return $result;
        }

        $custom_entities = array();
        foreach ( $entity_names as $entity_name ) {
            if ( WPDAI_Custom_Data_Source_Registry::has( $entity_name ) ) {
                $custom_entities[] = $entity_name;
            }
        }

        // One call per unique registered source (no-op if already fetched).
        $sources_by_id = array();
        foreach ( $custom_entities as $entity_name ) {
            $source = WPDAI_Custom_Data_Source_Registry::get( $entity_name );
            if ( $source ) {
                $sources_by_id[ spl_object_id( $source ) ] = $source;
            }
        }
        foreach ( $sources_by_id as $source ) {
            $this->fetch_custom_data_source_internal( $source );
        }

        // Return requested entities keyed by entity name (from $this->data).
        foreach ( $entity_names as $entity_name ) {
            if ( isset( $this->data[ $entity_name ] ) && is_array( $this->data[ $entity_name ] ) ) {
                $result[ $entity_name ] = $this->data[ $entity_name ];
            }
        }

        return $result;
    }

    /**
     * Run a single custom data source fetch and store all returned entities.
     * Used so that one source (e.g. orders + products) is only called once.
     *
     * @since 5.0.0
     * @param WPDAI_Custom_Data_Source_Interface $data_source Registered data source.
     * @return bool True on success, false on failure.
     */
    private function fetch_custom_data_source_internal( $data_source ) {
        $source_id = spl_object_id( $data_source );
        if ( ! empty( $this->fetched_custom_sources[ $source_id ] ) ) {
            return true;
        }

        $entity_names = $data_source->get_entity_names();
        if ( empty( $entity_names ) || ! is_array( $entity_names ) ) {
            return false;
        }

        foreach ( $entity_names as $entity_name ) {
            if ( ! isset( $this->data[ $entity_name ] ) ) {
                $this->data[ $entity_name ] = array(
                    'totals' => array(),
                    'categorized_data' => array(),
                    'data_table' => array(),
                    'data_by_date' => array(),
                    'total_db_records' => 0,
                    'execution_time' => 0,
                    'memory_usage' => 0,
                );
            }
        }

        $start_time = microtime( true );
        $memory_start = memory_get_usage( true );

        try {
            $custom_data = $data_source->fetch_data( $this );

            if ( ! is_array( $custom_data ) ) {
                $primary = $data_source->get_entity_name();
                $this->set_error( sprintf(
                    /* translators: %s: entity name */
                    __( 'Custom data source "%s" did not return a valid array.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                    esc_html( $primary )
                ) );
                return false;
            }

            $execution_time = microtime( true ) - $start_time;
            $memory_usage = memory_get_usage( true ) - $memory_start;

            $is_single_entity = array_key_exists( 'totals', $custom_data );

            if ( $is_single_entity ) {
                $primary = $data_source->get_entity_name();
                $formatted = array(
                    'totals' => $custom_data['totals'] ?? array(),
                    'categorized_data' => $custom_data['categorized_data'] ?? array(),
                    'data_table' => $custom_data['data_table'] ?? array(),
                    'data_by_date' => $custom_data['data_by_date'] ?? array(),
                    'total_db_records' => isset( $custom_data['total_db_records'] ) ? absint( $custom_data['total_db_records'] ) : 0,
                    'execution_time' => $execution_time,
                    'memory_usage' => $memory_usage,
                );
                $this->set_data( $primary, $formatted );
                $this->fetched_custom_sources[ $source_id ] = true;
                return true;
            }

            foreach ( $custom_data as $ent => $entity_data ) {
                if ( ! is_array( $entity_data ) ) {
                    continue;
                }
                if ( ! isset( $this->data[ $ent ] ) ) {
                    $this->data[ $ent ] = array(
                        'totals' => array(),
                        'categorized_data' => array(),
                        'data_table' => array(),
                        'data_by_date' => array(),
                        'total_db_records' => 0,
                        'execution_time' => 0,
                        'memory_usage' => 0,
                    );
                }
                $formatted = array(
                    'totals' => $entity_data['totals'] ?? array(),
                    'categorized_data' => $entity_data['categorized_data'] ?? array(),
                    'data_table' => $entity_data['data_table'] ?? array(),
                    'data_by_date' => $entity_data['data_by_date'] ?? array(),
                    'total_db_records' => isset( $entity_data['total_db_records'] ) ? absint( $entity_data['total_db_records'] ) : 0,
                    'execution_time' => $execution_time,
                    'memory_usage' => $memory_usage,
                );
                $this->set_data( $ent, $formatted );
            }
            $this->fetched_custom_sources[ $source_id ] = true;
            return true;

        } catch ( Exception $e ) {
            $primary = $data_source->get_entity_name();
            $this->set_error( sprintf(
                /* translators: %1$s: entity name, %2$s: error message */
                __( 'Error fetching data from custom data source "%1$s": %2$s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                esc_html( $primary ),
                esc_html( $e->getMessage() )
            ) );
            return false;
        }
    }

    /**
     * Fetches data from a single custom data source (backward-compatible wrapper).
     *
     * Delegates to fetch_data( array( $entity_name ) ) so all fetching uses the
     * same path and deduplication. For multiple entities use fetch_data( array( 'entity1', 'entity2' ) ).
     *
     * @param string $entity_name The entity name of the custom data source
     * @return array|false The fetched data or false on failure
     */
    public function fetch_custom_data_source( $entity_name ) {

        if ( empty( $entity_name ) || ! is_string( $entity_name ) ) {
            $this->set_error( __( 'Custom data source entity name must be a non-empty string.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) );
            return false;
        }

        if ( ! WPDAI_Custom_Data_Source_Registry::has( $entity_name ) ) {
            $this->set_error( sprintf(
                /* translators: %s: entity name */
                __( 'Custom data source "%s" is not registered.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                esc_html( $entity_name )
            ) );
            return false;
        }

        $this->fetch_data( array( $entity_name ) );
        $data = $this->get_data( $entity_name );

        return is_array( $data ) ? $data : false;
    }

}