<?php
/**
 *
 * High level class that fetches all data, mainly used for reports but can be accessed directly
 * 
 * @package Alpha Insights
 * @version 2.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

class WPD_Data_Warehouse_React {

    /**
     *
     *  The filter used for filtering data
     * 
     *  ['date_format_display'] = Holds the date format for grouping. Accepts day, month, quarter & year, defaults to day.
     *  ['date_from] = Holds the start date to filter by
     *  ['date_to] = Holds the end date to filter by
     *  ['campaign_id] = Filters by Campaign ID for Google & Facebook Ads API
     *   
     */
    private array $filter = array();

    /**
     *
     *  Data by date containers
     *
     */
    private array $data_by_date_containers = array();

    /**
     * 
     *  Stores any errors in request, useful for debugging
     * 
     **/
    private array $errors = array();

    /**
     * 
     *  This store's currency as set by WooCommerce
     * 
     **/
    private string $store_currency = '';

    /**
     * 
     *  Product Data Cache
     * 
     *  Used in case we need to call the same data over and over again
     * 
     **/
    private array $product_cache = array();

    /**
     * 
     * 
     * Flag that states whether the memory has been exahusted for this report
     * 
     **/
    private bool $memory_exhausted = false;

    /**
     * 
     *  The limit for the data table
     *  Keeps memory load in check
     * 
     *  @var int
     * 
     **/
    private int $default_data_table_limit = 500;

    /**
     *
     *  All data that has been fetched will be stored in this array
     * 
     *  Associative array with keys for each data segment and a breakdown of totals, data & data_by_date
     *  Only store what we need so that we don't overdo the memory
     * 
     */
    private array $data = array(
        // Will call orders & expenses
        'store_profit' => array(
            'totals' => array(),
            'categorized_data' => array(),
            'data_table' => array(),
            'data_by_date' => array(),
            'total_db_records' => 0,
            'execution_time' => 0,
        ),
        'orders' => array(
            'totals' => array(),
            'categorized_data' => array(),
            'data_table' => array(),
            'data_by_date' => array(),
            'total_db_records' => 0,
            'execution_time' => 0,
        ),
        'customers' => array(
            'totals' => array(),
            'categorized_data' => array(),
            'data_table' => array(),
            'data_by_date' => array(),
            'total_db_records' => 0,
            'execution_time' => 0,
        ),
        'products' => array(
            'totals' => array(),
            'categorized_data' => array(),
            'data_table' => array(),
            'data_by_date' => array(),
            'total_db_records' => 0,
            'execution_time' => 0,
        ),
        'coupons' => array(
            'totals' => array(),
            'categorized_data' => array(),
            'data_table' => array(),
            'data_by_date' => array(),
            'total_db_records' => 0,
            'execution_time' => 0,
        ),
        'taxes' => array(
            'totals' => array(),
            'categorized_data' => array(),
            'data_table' => array(),
            'data_by_date' => array(),
            'total_db_records' => 0,
            'execution_time' => 0,
        ),
        'refunds' => array(
            'totals' => array(),
            'categorized_data' => array(),
            'data_table' => array(),
            'data_by_date' => array(),
            'total_db_records' => 0,
            'execution_time' => 0,
        ),
        'subscriptions' => array(
            'totals' => array(),
            'categorized_data' => array(),
            'data_table' => array(),
            'data_by_date' => array(),
            'total_db_records' => 0,
            'execution_time' => 0,
        ),
        'expenses' => array(
            'totals' => array(),
            'categorized_data' => array(),
            'data_table' => array(),
            'data_by_date' => array(),
            'total_db_records' => 0,
            'execution_time' => 0,
        ),
        'facebook_campaigns' => array(
            'totals' => array(),
            'categorized_data' => array(),
            'data_table' => array(),
            'data_by_date' => array(),
            'total_db_records' => 0,
            'execution_time' => 0,
        ),
        'google_campaigns' => array(
            'totals' => array(),
            'categorized_data' => array(),
            'data_table' => array(),
            'data_by_date' => array(),
            'total_db_records' => 0,
            'execution_time' => 0,
        ),
        'analytics' => array(
            'totals' => array(),
            'categorized_data' => array(),
            'data_table' => array(),
            'data_by_date' => array(),
            'total_db_records' => 0,
            'execution_time' => 0,
        ),
        'anonymous_queries' => array(), // If we make arbitrary SQL queries, they can go in here. data_key => data associative array structure.
        'total_db_records' => 0 // Stores all records fetched
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
            $this->set_error('Filter key' . $key . ' needs to be a string.');
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
        $max_date       = $this->get_selected_date_range('date_to');    // date in the past
        $min_date       = $this->get_selected_date_range('date_from');  // current date
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
            $this->set_error( 'Trying to set data, ' . $data_type . ' is not a string.' );
            return false;
        }

        // Data payload is incorrect
        if ( ! is_array($data) ) {
            $this->set_error( 'Trying to set data, ' . $data . ' is not an array.' );
            return false;
        }

        // Allowed keys to be set into array
        // $allowed_data_keys = array( 'totals', 'raw_data', 'data_table', 'data_by_date', 'total_db_records' );

        // Store the data
        foreach( $data as $key => $data ) {

            // Only store allowed keys
            // if ( ! in_array( $key, $allowed_data_keys ) && $data_type !== 'anonymous_queries' ) {
            //     $this->set_error( 'Trying to set data, ' . $key . ' is not an allowed key.' );
            //     continue;
            // }

            // Anonymous Queries must be set with an associative array
            if ( $data_type === 'anonymous_queries' && ! is_string($key) ) {
                $this->set_error( 'Trying to set an anonymous key without a string as the key, check that you\'ve passed an associative array.' );
                continue;
            }
            
            // Store the key
            $this->data[$data_type][$key] = $data;

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
    private function set_error( $error ) {

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

            $start = date($format, strtotime( $days_in_past, $wp_timestamp ) ); // this needs to be based on wp time as below

            if ( isset( $this->filter['date_from'] ) && ! empty($this->filter['date_from']) ) {
                $start = date( $format, strtotime($this->filter['date_from']) );
            }

            return $start;

        } elseif ( $result == 'date_to' ) {

            $end = current_time( $format ); 

            if ( isset($this->filter['date_to']) && ! empty($this->filter['date_to']) ) {
                $end = date( $format, strtotime($this->filter['date_to']));
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

            $dates[] = date($output_format, $current_date);
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
        return wpd_divide( $datediff, (60 * 60 * 24) );

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
     * 
     *  Returns the applied data filter for the chosen entity and key
     * 
     *  @return array|string|int|bool Returns the applied data filter for the chosen entity and key or false if not found
     * 
     **/
    public function get_data_filter( $entity, $key ) {

        $data_filter = $this->get_filter('data_filters', array());

        if ( isset($data_filter[$entity]) && isset($data_filter[$entity][$key]) && ! empty($data_filter[$entity][$key]) ) {

            $value = $data_filter[$entity][$key];

            // Handles strings, int, arrays and multi-dimensional arrays
            return $this->sanitize_recursive( $value );
        }

        // Return false
        return false;

    }

    /**
     * Recursively sanitize a value or array of values.
     *
     * @param mixed $value
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
            $this->store_currency = wpd_get_store_currency();
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

            if ( $data_table_limit[$entity] === 0 ) {

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

            $product_data_store 	    = wpd_product_data_collection( $product_id );
            $product_data_store_update 	= update_post_meta( $product_id, '_wpd_ai_product_data_store', $product_data_store );    

        }

        // Cache results in prop
        $this->product_cache[$product_id] = $product_data_store;

        // Return Results
        return $product_data_store;

    }

    /**
     * 
     *  Retrieves the HTML display badge for a product
     * 
     *  @param int $product_id The product ID
     *  @return string The HTML display badge
     * 
     **/
    private function get_product_html_display_badge( $product_id ) {

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
     *  Retrieves the Order ID's with a stored Meta Campaign ID
     * 
     **/
    public function get_order_ids_with_meta_campaign_ids() {

        $date_from = $this->get_date_from();
        $date_to = $this->get_date_to();
        $order_campaign_data = array();

        // Get main orders
        $order_ids_with_meta_campaign = wc_get_orders(
            array(
                'limit' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
                'return' => 'ids',
                'status' => wpd_paid_order_statuses(),
                'date_created' => $date_from . "..." . $date_to, //'2018-02-01...2018-02-28',
                'meta_key' => '_wpd_ai_meta_campaign_id',
                'meta_compare' => 'EXISTS'
            )
        );

        // Get anonymous orders - have fbclid but don't have a meta campaign set
        $order_ids_with_fbclid = wc_get_orders(
            array(
                'limit' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
                'return' => 'ids',
                'status' => wpd_paid_order_statuses(),
                'date_created' => $date_from . "..." . $date_to, //'2018-02-01...2018-02-28',
                'meta_key' => '_wpd_ai_landing_page',
                'meta_value' => 'fbclid',
                'meta_compare' => 'LIKE'
            )
        );

        // For debugging
        // $order_ids_with_fbclid = array();

        // Combine the two
        $order_ids = array_merge( $order_ids_with_meta_campaign, $order_ids_with_fbclid );

        // Load the desired calculation cache
        if ( is_array($order_ids) && ! empty($order_ids) ) {
            wpd_setup_order_calculations_in_object_cache( $order_ids );
        }

        // Loop through Order IDs to build useful array
        foreach( $order_ids as $order_id ) {

            // Collect data
            $order_data = wpd_calculate_cost_profit_by_order( $order_id );

            // Only deal with paid orders - Little buggy with custom order statuses, rely on filter above
            // if ( $order_data['is_paid'] != 1 ) continue; 

            // Fetch Campaign ID
            $campaign_id = wpd_get_order_meta_by_order_id( $order_id, '_wpd_ai_meta_campaign_id' );

            // Set a default value for non-found campaign ids
            if ( ! is_numeric($campaign_id) ) $campaign_id = 'unknown';

            // Build Array
            if ( ! isset($order_campaign_data[$campaign_id]) ) $order_campaign_data[$campaign_id] = array();
            $order_campaign_data[$campaign_id][$order_id] = $order_data;
            $order_campaign_data[$campaign_id][$order_id]['campaign_id'] = $campaign_id;
        }

        // Return IDS
        return $order_campaign_data;

    }

    /**
     * 
     *  Retrieves the Order ID's with a stored Google Campaign ID
     * 
     **/
    public function get_order_ids_with_google_campaign_ids() {

        $date_from = $this->get_date_from();
        $date_to = $this->get_date_to();
        $order_campaign_data = array();

        // Get orders
        $order_ids_with_google_campaign_id = wc_get_orders(
            array(
                'limit' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
                'return' => 'ids',
                'status' => wpd_paid_order_statuses(),
                'date_created' => $date_from . "..." . $date_to, //'2018-02-01...2018-02-28',
                'meta_key' => '_wpd_ai_google_campaign_id',
                'meta_compare' => 'EXISTS'
            )
        );

        // Get anonymous orders - have fbclid but don't have a meta campaign set
        $order_ids_with_gclid = wc_get_orders(
            array(
                'limit' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
                'return' => 'ids',
                'status' => wpd_paid_order_statuses(),
                'date_created' => $date_from . "..." . $date_to, //'2018-02-01...2018-02-28',
                'meta_key' => '_wpd_ai_landing_page',
                'meta_value' => 'gclid',
                'meta_compare' => 'LIKE'
            )
        );

        // Combine the two
        $order_ids = array_merge( $order_ids_with_google_campaign_id, $order_ids_with_gclid );

        // Load the desired calculation cache
        if ( is_array($order_ids) && ! empty($order_ids) ) {
            wpd_setup_order_calculations_in_object_cache( $order_ids );
        }

        // Loop through Order IDs to build useful array
        foreach( $order_ids as $order_id ) {

            // Collect data
            $order_data = wpd_calculate_cost_profit_by_order( $order_id );

            // Only deal with paid orders - Little buggy with custom order statuses, rely on filter above
            // if ( $order_data['is_paid'] != 1 ) continue; 

            // Fetch Campaign ID
            $campaign_id = wpd_get_order_meta_by_order_id( $order_id, '_wpd_ai_google_campaign_id' );

            // Set a default value for non-found campaign ids
            if ( ! is_numeric($campaign_id) ) $campaign_id = 'unknown';

            // Build Array
            if ( ! isset($order_campaign_data[$campaign_id]) ) $order_campaign_data[$campaign_id] = array();
            $order_campaign_data[$campaign_id][$order_id] = $order_data;
            $order_campaign_data[$campaign_id][$order_id]['campaign_id'] = $campaign_id;

        }

        // Return IDS
        return $order_campaign_data;

    }

    /**
     * 
     *  Fetches Subscriptions Data and organises it for use by get_data('subscriptions')
     *  Will also call fetch_orders with additional filters for orders, products & subscriptions
     *  When fetching from the orders, it will skip over any order that is not a renewal order
     * 
     *  This function will not run if WC_Subscriptions is not available
     * 
     **/
    public function fetch_subscriptions_data() {
        // Start execution timer
        $start_time = microtime(true);

        // Dont bother if they dont have subscriptions
        if ( ! wpd_is_wc_subscriptions_active() ){
            $this->set_error( 'WC_Subscriptions is not active.' );
            return $this->get_data('subscriptions');
        }

        // Global vars
        $data_table_limit       = $this->get_data_table_limit('subscriptions');
        $memory_limit           = ini_get('memory_limit');
        $date_from_timestamp   = strtotime( $this->get_date_from() );
        $date_to_timestamp     = strtotime( $this->get_date_to() );
        $date_format            = $this->get_filter( 'date_format_string' );

        // Setup Totals Defaults
        $totals = array(

            'total_subscriptions' 				        => 0,       // All time
            'total_renewals_in_period' 				    => 0,       // All time
            'total_subscriptions_active_today' 	        => 0,       // Today
            'average_active_subscription_days' 	        => 0,       // All time
            'total_active_subscription_days'            => 0,       // All time
            'total_subscriptions_created_in_period' 	=> 0,       // During Date Range
            'total_subscriptions_cancelled_in_period' 	=> 0,       // During Date Range
            'total_subscription_revenue_in_period'      => 0,       // During Date Range
            'total_subscription_profit_in_period'       => 0,       // During Date Range
            'average_subscription_revenue_in_period' 	=> 0,       // During Date Range
            'average_subscription_profit_in_period'     => 0,       // During Date Range
            'average_subscription_margin_in_period'     => 0,       // During Date Range
            'cancellation_rate_in_period' 	            => 0,       // During Date Range

        );

        $categorized_data = array(
            'subscription_status_count' 		        => array(),
            'scheduled_payments' 			            => array(
                'month'         => 0,
                'quarter'       => 0,
                'halfyear'      => 0,
                'year'          => 0
            ),
            'subscriptions_created_forecast'            => array(
                'this_period'   => 0,
                'daily'         => 0,
                '30_day'        => 0,
                '90_day'        => 0,
                '180_day'       => 0,
                '365_day'       => 0
            ),
            'expected_income' 					        => array(),
            'product_totals'	 				        => array(),
        );

        // Setup Subscription Array Defaults
        $data_table = array();

        // Setup default for total DB records reviewed
        $total_db_records = 0;

        // Setup Default Data By Date
        $data_by_date = array(

            'active_subscriptions_by_date' 					=> $this->get_data_by_date_range_container(),
			'total_subscription_signups_by_date' 			=> $this->get_data_by_date_range_container(),
			'total_subscription_cancellations_by_date' 		=> $this->get_data_by_date_range_container(),
			'net_subscription_movement_by_date' 			=> $this->get_data_by_date_range_container(),
			'subscription_growth_by_date' 					=> $this->get_data_by_date_range_container(),
			'expected_income_by_date' 						=> array() // Will be a custom range of +1 year

        );

        // Calculate dates for expected income over the next year
        $expected_income_array      = array();
        $expected_income_min_date   = current_time( 'timestamp' ); // min($date_keys);
        $expected_income_max_date   = strtotime( '+1 year', $expected_income_min_date ); // max($date_keys);

        // Next year array of dates
        $expected_income_date_range = $this->get_date_range_array( date( 'Y-m-d', $expected_income_min_date ), date( 'Y-m-d', $expected_income_max_date ), '+1 day', $date_format );
        
        // Create the shell array
        foreach( $expected_income_date_range as $date_array_val ) {
            $expected_income_array[$date_array_val] = 0;
        }
        
        // Store array
        $data_by_date['expected_income_by_date'] = $expected_income_array;

        // Default Subscription Payload
        $default_subscription_data = array(

            'subscription_id' 					=> null,
            'status' 							=> null,
            'status_nice_name' 					=> null,
            'date_created' 						=> null,
            'date_end'	 						=> null,
            'date_cancelled' 					=> null,
            'date_last_payment' 				=> null,
            'next_payment_date' 				=> null,
            'subscription_active_today' 		=> 0,
            'renewal_revenue'                   => 0,
            'total_revenue' 				    => 0,
            'total_cost'					    => 0,
            'total_profit' 						=> 0,
            'average_margin' 					=> 0,
            'related_orders_in_period' 			=> array(),
            'related_orders_in_period_count' 	=> 0,
            'active_subscription_days'			=> 0,
            'billing_period' 					=> null,
            'billing_interval' 					=> null,
            'scheduled_payments' 			    => array(
                'month'     => 0,
                'quarter'   => 0,
                'halfyear'  => 0,
                'year'      => 0
            )

        );

        // Setup args for subscriptions query
		$args = array(
		    'limit' 					=> -1,
		    'orderby' 					=> 'date',
		    'order' 					=> 'ASC',
		    'return' 					=> 'ids',
		    'type'   					=> 'shop_subscription',
		    'status' 					=>  array_keys( wcs_get_subscription_statuses() ),
		);

        // Filter by status
        if ( is_array($this->get_filter('subscription_status')) && ! empty($this->get_filter('subscription_status')) ) $args['status'] = $this->get_filter('subscription_status');

        // Call the subscriptions & store relevant values
		$subscriptions = wc_get_orders( $args );
        $total_db_records = count( $subscriptions );

        // If we have subscriptions, lets call the order data -> we'll use that later & make sure correct filters are set
        $currently_active_filter = $this->get_filter();
        
        // Create a new instance to fetch order data without affecting the current instance
        $order_data_instance = new self($currently_active_filter);
        $order_data_instance->update_filter('additional_order_data', array( 'orders' => true, 'subscriptions' => true, 'products' => true));
        $order_data_instance->fetch_sales_data();
        $order_data_tables = $order_data_instance->get_data( 'orders', 'data_table' );
        $order_data_table = $order_data_tables['orders'];
        $product_data_tables = $order_data_instance->get_data( 'products', 'data_table' );
        $product_data = $product_data_tables['products'];

        // Merge the calculated totals into our totals array
        if ( is_array( $order_data_instance->get_data( 'orders', 'totals' ) ) ) $totals = array_merge( $totals, $order_data_instance->get_data( 'orders', 'totals' ) );

        // Insert product totals data into our array
        if ( is_array( $product_data ) ) $categorized_data['product_totals'] = $product_data;

        /**
         * 
         *  Fill in sales data for each subscriptions based on renewal orders in this period
         * 
         **/
        if (! WPD_AI_PRO ) $order_data_table = array();
        foreach( $order_data_table as $order_id => $order_data ) {

            // Memory Check
			if ( wpd_is_memory_usage_greater_than(90) ) {
				$this->set_error(
					sprintf(
						/* translators: 1: Number of processed orders/subscriptions, 2: Total number of orders/subscriptions, 3: PHP memory limit */
						__( 'You\'ve exhausted your memory usage after %1$s out of %2$s orders. Increase your PHP memory limit or reduce the date range. Your current PHP memory limit is %3$s.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
						$subscription_id,
						$total_db_records,
						$memory_limit
					)
				);
				break;
			}

            $subscription_ids = $order_data['parent_subscription_ids'];

            // Check if we have parent subscription IDs
            if ( is_array($subscription_ids) && ! empty($subscription_ids) ) {

                // Loop through the parent subscription IDs for each renewal order
                foreach( $subscription_ids as $subscription_id ) {

                    // Setup default array for this subscription
                    if ( ! isset($data_table[$subscription_id]) ) $data_table[$subscription_id] = $default_subscription_data;

                    // Add sales data to the subscription
                    $data_table[$subscription_id]['total_revenue'] 				        += $order_data['total_order_revenue'];
                    $data_table[$subscription_id]['total_cost']					        += $order_data['total_order_cost'];
                    $data_table[$subscription_id]['total_profit'] 						+= $order_data['total_order_profit'];
                    $data_table[$subscription_id]['related_orders_in_period'][] 		= $order_data['order_id'];

                    // Add product data to our totals array
                    if ( isset( $order_data['product_data'] ) && is_array( $order_data['product_data'] ) ) {

                        // Loop through products in this order
                        foreach( $order_data['product_data'] as $product_id => $product_data ) {

                            // Check if this product is in our totals
                            if ( isset($categorized_data['product_totals'][$product_id]) ) {

                                // Setup defaults
                                if ( ! isset($categorized_data['product_totals'][$product_id]['subscription_ids']) ) $categorized_data['product_totals'][$product_id]['subscription_ids'] = array();
                                if ( ! isset($categorized_data['product_totals'][$product_id]['subscription_count']) ) $categorized_data['product_totals'][$product_id]['subscription_count'] = 0;
                                if ( ! isset($categorized_data['product_totals'][$product_id]['renewal_order_ids']) ) $categorized_data['product_totals'][$product_id]['renewal_order_ids'] = array();
                                if ( ! isset($categorized_data['product_totals'][$product_id]['renewal_order_count']) ) $categorized_data['product_totals'][$product_id]['renewal_order_count'] = 0;
                                
                                // Store Subscription IDs and count
                                if ( ! in_array( $subscription_id, $categorized_data['product_totals'][$product_id]['subscription_ids'] ) ) {
                                    $categorized_data['product_totals'][$product_id]['subscription_ids'][] = $subscription_id;
                                    $categorized_data['product_totals'][$product_id]['subscription_count']++;
                                }

                                // Store Renewal IDs and count
                                if ( ! in_array( $subscription_id, $categorized_data['product_totals'][$product_id]['renewal_order_ids'] ) ) {
                                    $categorized_data['product_totals'][$product_id]['renewal_order_ids'][] = $order_id;
                                    $categorized_data['product_totals'][$product_id]['renewal_order_count']++;
                                }

                            }

                        }

                    }

                }

                // Add renewal order to totals
                $totals['total_renewals_in_period']++;

            }
            
        }

		/**
		 *
		 *	Loop through Subscriptions
		 *
		 */
        if (! WPD_AI_PRO ) $subscriptions = array();
		foreach ( $subscriptions as $subscription_id ) {

            // Memory Check
			if ( wpd_is_memory_usage_greater_than(90) ) {
				$this->set_error(
					sprintf(
						/* translators: 1: Number of processed orders/subscriptions, 2: Total number of orders/subscriptions, 3: PHP memory limit */
						__( 'You\'ve exhausted your memory usage after %1$s out of %2$s orders. Increase your PHP memory limit or reduce the date range. Your current PHP memory limit is %3$s.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
						$subscription_id,
						$total_db_records,
						$memory_limit
					)
				);
				break;
			}

			// Get subscription object
            // @todo this is very slow, need to fix.
			$subscription = wcs_get_subscription( $subscription_id );

            // Safety Check
			if ( ! is_a( $subscription, 'WC_Subscription' ) ) continue;

            // Setup default array for this subscription
            if ( ! isset($data_table[$subscription_id]) ) $data_table[$subscription_id] = $default_subscription_data;

            // Setup vars that we can setup now in the organised data
            $data_table[$subscription_id]['subscription_id'] 			    = $subscription_id;
            $data_table[$subscription_id]['status'] 					    = $subscription->get_status();
            $data_table[$subscription_id]['status_nice_name'] 			    = '<span class="wpd-order-status wpd-status-' . $data_table[$subscription_id]['status'] . '">' . wcs_get_subscription_status_name( $data_table[$subscription_id]['status'] ) . '</span>';
            $data_table[$subscription_id]['date_created'] 				    = ( $subscription->get_date( 'start', 'site' ) ) ? strtotime($subscription->get_date( 'start', 'site' )) : 0;
            $data_table[$subscription_id]['date_end']	 				    = ( $subscription->get_date( 'end', 'site' ) ) ? strtotime($subscription->get_date( 'end', 'site' )) : 0;
            $data_table[$subscription_id]['date_cancelled'] 			    = ( $subscription->get_date( 'cancelled', 'site' ) ) ? strtotime($subscription->get_date( 'cancelled', 'site' )) : 0;
            $data_table[$subscription_id]['date_last_payment'] 				= ( $subscription->get_date( 'last_order_date_paid', 'site' ) ) ? strtotime($subscription->get_date( 'last_order_date_paid', 'site' )) : 0;
            $data_table[$subscription_id]['next_payment_date'] 				= ( $subscription->get_date( 'next_payment', 'site' ) ) ? strtotime($subscription->get_date( 'next_payment', 'site' )) : 0;
            $data_table[$subscription_id]['billing_interval'] 				= $subscription->get_billing_interval();
            $data_table[$subscription_id]['billing_period'] 				= $subscription->get_billing_period();
            $data_table[$subscription_id]['related_orders_in_period_count'] = count($data_table[$subscription_id]['related_orders_in_period']);
            $data_table[$subscription_id]['average_margin']					= wpd_calculate_margin( $data_table[$subscription_id]['total_profit'], $data_table[$subscription_id]['total_revenue'] );
            $data_table[$subscription_id]['subscription_active_today'] 		= ( $data_table[$subscription_id]['status'] === 'active' ) ? 1 : 0;
            $data_table[$subscription_id]['active_subscription_days']		= ( $data_table[$subscription_id]['date_end'] > 0 ) ? $this->days_between_dates( $data_table[$subscription_id]['date_created'], $data_table[$subscription_id]['date_end'] ) : $this->days_between_dates( $data_table[$subscription_id]['date_created'], current_time( 'timestamp' ) );
            $data_table[$subscription_id]['renewal_revenue']                = (float) $recurring_subscription_revenue = (float) $subscription->get_total();
            $data_table[$subscription_id]['billing_email']                  = $subscription->get_billing_email();

            // Active subscriptions count
            if ( $data_table[$subscription_id]['status'] === 'active' ) $totals['total_subscriptions_active_today']++;

            // Sign Ups in Period
            if ( $data_table[$subscription_id]['date_created'] > $date_from_timestamp && $data_table[$subscription_id]['date_created'] < $date_to_timestamp ) $totals['total_subscriptions_created_in_period']++;

            // Cancellations in period
			if ( $data_table[$subscription_id]['date_cancelled'] > $date_from_timestamp && $data_table[$subscription_id]['date_cancelled'] < $date_to_timestamp ) $totals['total_subscriptions_cancelled_in_period']++;
            
            // Total subscription active days
            $totals['total_active_subscription_days'] += $data_table[$subscription_id]['active_subscription_days'];

            // Total Revenue
            $totals['total_subscription_revenue_in_period'] += $data_table[$subscription_id]['total_revenue'];

            // Total Profit
            $totals['total_subscription_profit_in_period'] += $data_table[$subscription_id]['total_profit'];

            // Subscription Status Count
            if ( ! isset( $categorized_data['subscription_status_count'][$data_table[$subscription_id]['status']] ) ) $categorized_data['subscription_status_count'][$data_table[$subscription_id]['status']] = 0;
            $categorized_data['subscription_status_count'][$data_table[$subscription_id]['status']] ++;

            /**
			 *
			 *	Calculate all renewal dates within given date range (365 days)
			 * 	If there's a next payment date & this is expected to continue running
			 *
			 * 	Will setup the graph and a few key date milestones
			 *
			 */
			if ( $data_table[$subscription_id]['next_payment_date'] && ! in_array( $data_table[$subscription_id]['status'], array('cancelled', 'pending', 'on-hold') ) ) {

                // Setup a few key milestones for calculations
                $one_year_from_today 		= strtotime( '+1 year' );
				$thirty_days_from_today 	= strtotime( '+30 days' );
				$ninety_days_from_today 	= strtotime( '+90 days' );
				$half_year_from_today 		= strtotime( '+6 months' );

                // Store next payment as timestamp in var
				$next_payment_timestamp 	= $data_table[$subscription_id]['next_payment_date'];

				// Loop while less than date
				while ( $next_payment_timestamp < $one_year_from_today ) {

					// Just in case
					if ( $next_payment_timestamp > $one_year_from_today ) {
						break;
					}

					// If there's an end date & if it's past the next payment timestamp
					if ( $data_table[$subscription_id]['date_end'] > 0 && $next_payment_timestamp > $data_table[$subscription_id]['date_end']  ) {
						break;
					}

					// Setup Y-m-d format
					$next_payment_ymd = date( 'Y-m-d', $next_payment_timestamp );
                    
                    // Add to the chart
                    $formatted_date_key = $this->convert_date_string( $next_payment_ymd );
                    if (isset($data_by_date['expected_income_by_date'][$formatted_date_key])) $data_by_date['expected_income_by_date'][$formatted_date_key] += $recurring_subscription_revenue;

                    // Setup default for table of dates in Y-m-d
					if ( ! isset($categorized_data['expected_income'][$next_payment_ymd]) ) $categorized_data['expected_income'][$next_payment_ymd] = 0;

                    // Need to store this as a total
					$categorized_data['expected_income'][$next_payment_ymd] += $recurring_subscription_revenue;

					// Monthly Total
					if ( $next_payment_timestamp < $thirty_days_from_today ) {
						$data_table[$subscription_id]['scheduled_payments']['month'] += $recurring_subscription_revenue;
                        $categorized_data['scheduled_payments']['month'] += $recurring_subscription_revenue;
					}

					// Quarterly Total
					if ( $next_payment_timestamp < $ninety_days_from_today ) {
						$data_table[$subscription_id]['scheduled_payments']['quarter'] += $recurring_subscription_revenue;
                        $categorized_data['scheduled_payments']['quarter'] += $recurring_subscription_revenue;
					}

					// Half Yearly Total
					if ( $next_payment_timestamp < $half_year_from_today ) {
						$data_table[$subscription_id]['scheduled_payments']['halfyear'] += $recurring_subscription_revenue;
                        $categorized_data['scheduled_payments']['halfyear'] += $recurring_subscription_revenue;
					}

					// Yearly Total
					if ( $next_payment_timestamp < $one_year_from_today ) {
						$data_table[$subscription_id]['scheduled_payments']['year'] += $recurring_subscription_revenue;
                        $categorized_data['scheduled_payments']['year'] += $recurring_subscription_revenue;
					}

					// Calculate new date
					$next_payment_timestamp = wcs_add_time( 
						$data_table[$subscription_id]['billing_interval'], 
						$data_table[$subscription_id]['billing_period'], 
						$next_payment_timestamp 
					);

				}

			}

            /**
             * 
             *  Loop through all dates on every subscription to check if they're active on a given date
             * 
             *  @todo Very expensive, reconsider this.
             *  
             **/
            if ( is_array($data_by_date['active_subscriptions_by_date']) ) {

                foreach( $data_by_date['active_subscriptions_by_date'] as $date_key => $date_data ) {

                    // Force by day so that it lines up properly with conversions of dates
                    $date_created_day_timestamp = strtotime( $this->convert_date_string( date( 'Y-m-d', $data_table[$subscription_id]['date_created'] ) ) );
                    $date_cancelled_day_timestamp = ($data_table[$subscription_id]['date_cancelled']) ? strtotime( $this->convert_date_string( date( 'Y-m-d', $data_table[$subscription_id]['date_cancelled'] ) ) ) : 0;

                    // Active subscriptions by date
                    if ( wpd_is_subscription_active_on_date( $date_created_day_timestamp, $date_cancelled_day_timestamp, strtotime($date_key) ) ) {
                        $data_by_date['active_subscriptions_by_date'][$date_key]++;
                    }

                    // Total subscription growth
                    if ( strtotime($date_key) >= $date_created_day_timestamp ) {
                        $data_by_date['subscription_growth_by_date'][$date_key]++;
                    }

                }

            }

            // Date Data -> Subscriptions Created & Net Movement
			if ( is_numeric($data_table[$subscription_id]['date_created']) && $data_table[$subscription_id]['date_created'] > 0 ) {

                // Correct date key format
				$date_created_date_key = date( $date_format, $data_table[$subscription_id]['date_created'] );

                if ( isset($data_by_date['total_subscription_signups_by_date'][$date_created_date_key]) ) {

                    // Add to date created array
                    $data_by_date['total_subscription_signups_by_date'][$date_created_date_key]++;

                    // Sum the net movement array
                    $data_by_date['net_subscription_movement_by_date'][$date_created_date_key]++;

                }

			}

            // Date Data -> Subscriptions Cancelled & Net Movement
			if ( is_numeric($data_table[$subscription_id]['date_cancelled']) && $data_table[$subscription_id]['date_cancelled'] > 0 ) {

                // Correct date key format
				$date_created_date_key = date( $date_format, $data_table[$subscription_id]['date_cancelled'] );

                if ( isset($data_by_date['total_subscription_cancellations_by_date'][$date_created_date_key]) ) {

                    // Add to date created array
                    $data_by_date['total_subscription_cancellations_by_date'][$date_created_date_key]--;

                    // Sum the net movement array
                    $data_by_date['net_subscription_movement_by_date'][$date_created_date_key]--;

                }

			}

        }

        // Clean up categorized product totals
        $cleaned_product_totals = array();
        $product_data = $categorized_data['product_totals'];
        foreach( $categorized_data['product_totals'] as $product_id => $product_info ) {

            // Use product_name as key if it's not empty, otherwise use product_id
            $new_key = !empty($product_info['product_name']) ? $product_info['product_name'] : 'ID: ' . $product_id;
            $cleaned_product_totals[$new_key] = $product_info;
            
        }
        $categorized_data['product_totals'] = $cleaned_product_totals;

        // Calculate totals
        $totals['total_subscriptions']                                          = count( $subscriptions );
        $totals['average_active_subscription_days']                             = wpd_divide( $totals['total_active_subscription_days'], $totals['total_subscriptions'] );
        $totals['average_subscription_revenue_in_period']                       = wpd_divide( $totals['total_subscription_revenue_in_period'], $totals['total_subscriptions'] );
        $totals['average_subscription_profit_in_period']                        = wpd_divide( $totals['total_subscription_profit_in_period'], $totals['total_subscriptions'] );
        $totals['average_subscription_margin_in_period']                        = wpd_calculate_margin( $totals['total_subscription_profit_in_period'], $totals['total_subscription_revenue_in_period'] );
        $categorized_data['subscriptions_created_forecast']['this_period']      = $totals['total_subscriptions_created_in_period'];
        $categorized_data['subscriptions_created_forecast']['daily']            = wpd_divide( $totals['total_subscriptions_created_in_period'], $this->get_n_days_range(), 2 );
        $categorized_data['subscriptions_created_forecast']['30_day']           = round( $categorized_data['subscriptions_created_forecast']['daily'] * 30, 2);
        $categorized_data['subscriptions_created_forecast']['90_day']           = round( $categorized_data['subscriptions_created_forecast']['daily'] * 90, 2);
        $categorized_data['subscriptions_created_forecast']['180_day']          = round( $categorized_data['subscriptions_created_forecast']['daily'] * 180, 2);
        $categorized_data['subscriptions_created_forecast']['365_day']          = round( $categorized_data['subscriptions_created_forecast']['daily'] * 365, 2);
		$totals['cancellation_rate_in_period'] 	                                = wpd_calculate_percentage( $totals['total_subscriptions_cancelled_in_period'], $totals['total_subscriptions_created_in_period'] );

        // Create no data found array
        $data_by_date = $this->maybe_create_no_data_found_date_array( $data_by_date );

        // Calculate execution time
        $execution_time = microtime(true) - $start_time + $this->get_data('subscriptions', 'execution_time');

        // Configure return object
        $subscriptions_data = array(
            'totals'            => $totals,
            'categorized_data'  => $categorized_data,
            'data_table'        => array(
                'subscriptions' => $data_table,
                'subscription_products' => $product_data
            ),
            'data_by_date'      => $data_by_date,
            'total_db_records'  => $total_db_records,
            'execution_time'    => $execution_time
        );

        // Store the data into the prop
        $this->set_data( 'subscriptions', $subscriptions_data );

        // Return Results
        return $subscriptions_data;


    }

    /**
     * 
     *  Fetches Orders Data and organises it for use by get_data('orders')
     * 
     *  Available Filters: 
     * 
     *  date_from (will filter against the orders date_created)
     *  date_to (will filter against the orders date_created)
     *  additional_order_data (array) Accepts: products, acquisitions, customers, coupons, subscriptions, all -> will collect these additional data points if in filter
     *  
     **/
    public function fetch_sales_data() {

        // Start execution timer
        $start_time = microtime(true);

        // If we need to fetch additional data
        $additional_data                = ( $this->get_filter( 'additional_order_data' ) ) ? $this->get_filter( 'additional_order_data' ) : array();
        $orders_data_table_limit        = $this->get_data_table_limit('orders');
        $products_data_table_limit      = $this->get_data_table_limit('products');
        $customers_data_table_limit     = $this->get_data_table_limit('customers');

        // Filters
        $order_status_filter            = $this->get_data_filter('orders', 'order_status');
        $billing_email_filter           = $this->get_data_filter('orders', 'billing_email');
        $traffic_source_filter          = $this->get_data_filter('orders', 'traffic_source');
        $device_type_filter             = $this->get_data_filter('orders', 'device_type');
        $query_parameter_values_filter  = $this->get_data_filter('orders', 'query_parameter_values'); // New key-value pair format
        $order_ids_filter               = $this->get_data_filter('orders', 'order_ids');
        $product_ids_filter             = $this->get_data_filter('products', 'products');
        $product_category_filter        = $this->get_data_filter('products', 'product_category');
        $product_tag_filter             = $this->get_data_filter('products', 'product_tag');
        $billing_country_filter         = $this->get_data_filter('customers', 'billing_country');
        $user_id_filter                 = $this->get_data_filter('customers', 'user_id');
        $ip_address_filter              = $this->get_data_filter('customers', 'ip_address');
        $customer_billing_email_filter  = $this->get_data_filter('customers', 'billing_email');

        // Default container commonly used
        $default_order_summary = array(

            'distinct_count'        => array(), // For holding unique entities
            'total_revenue'         => 0,
            'total_cost'            => 0,
            'total_profit'          => 0,
            'total_order_count'     => 0,
            'margin_percentage'     => 0,
            'average_order_value'   => 0,
            'percent_of_revenue'    => 0,

        );

        // Setup default containers
        $totals = array(

            // Top Line Order Calculations
            'order_metrics' => array(
                'total_order_count' 				        => 0,
                'total_order_revenue_inc_tax_and_refunds'   => 0,
                'total_order_revenue' 				        => 0,
                'total_order_revenue_ex_tax'                => 0,
                'total_order_tax'                           => 0,
                'total_order_cost' 						    => 0,
                'total_order_profit' 				        => 0,
                'total_freight_recovery' 			        => 0,
                'total_freight_cost' 				        => 0,
                'total_payment_gateway_costs' 		        => 0,
                'total_tax_collected' 					    => 0,
                'total_custom_order_costs'                  => 0,
                'total_custom_product_costs'                => 0,
                'total_product_cost_of_goods'               => 0,
                'largest_order_revenue' 				    => 0,
                'largest_order_cost' 					    => 0,
                'largest_order_profit' 					    => 0,
                'cost_percentage_of_revenue'                => 0,
                'average_order_margin'					    => 0,
                'average_order_revenue' 				    => 0,
                'average_order_cost'				        => 0,
                'average_line_items_per_order'              => 0,
                'average_order_profit' 					    => 0,
                'daily_average_order_count'                 => 0,
                'daily_average_order_revenue'               => 0,
                'daily_average_order_cost'                  => 0,
                'daily_average_order_profit'                => 0,
            ),

            'refund_metrics' => array(
                'total_refund_amount' 				        => 0,
                'total_order_count_with_refund' 	        => 0,
                'total_order_count_with_full_refund' 	    => 0,
                'total_order_count_with_partial_refund'     => 0,
                'total_qty_refunded'                        => 0,
                'total_skus_refunded'                       => 0,
                'refund_percent_of_revenue'                 => 0,
                'refund_rate_percentage'                    => 0,
                'refunds_per_day'                           => 0,
            ),

            'product_metrics' => array(
                
                // Product Data   
                'total_product_revenue' 			        => 0,
                'total_product_revenue_excluding_tax'       => 0,
                'total_product_cost' 				        => 0,
                'total_qty_sold' 					        => 0,
                'total_skus_sold' 					        => 0,
                'total_product_revenue_at_rrp' 		        => 0,
                'total_product_discount_amount'             => 0,
                'average_product_discount_percent'          => 0,
                'total_product_profit'                      => 0,
                'total_product_profit_at_rrp'               => 0,
                'average_profit_per_product'                => 0,
                'average_product_margin'                    => 0,
                'average_product_margin_at_rrp'             => 0,
                'average_qty_sold_per_day'                  => 0,
                'average_products_sold_per_day'             => 0,
                'average_skus_sold_per_day'                 => 0,
                'total_product_refund_amount'               => 0,
                'total_product_line_items_sold'             => 0,
                'largest_product_count_sold_per_order'      => 0,
                'largest_quantity_sold_per_order'           => 0,
                'total_line_items_refunded'                 => 0,

            ),

            'customer_metrics' => array(
                'customer_count_by_email_address'           => 0,
                'registered_customer_count'                 => 0,
                'registered_customer_percentage'            => 0,
                'guest_customer_count'                      => 0,
                'guest_customer_percentage'                 => 0,
                'new_customer_count'                        => 0,
                'new_customer_percentage'                   => 0,
                'returning_customer_count'                  => 0,
                'returning_customer_percentage'             => 0,
                'average_customer_value_revenue'            => 0,
                'average_customer_value_profit'             => 0,
                'orders_per_customer'                       => 0,
                'customer_count_purchased_more_than_once'   => 0,
                'customer_country_count'                    => 0,
                'customer_state_count'                      => 0,
                'products_purchased_per_customer'           => 0,
                'quantity_purchased_per_customer'           => 0,
                'customers_with_refund'                     => 0,
                'refunds_per_customer'                      => 0,
                'customer_refund_rate'                      => 0
            ),

            'coupon_metrics' => array(
                'total_discount_amount'                                 => 0,
                'total_discount_amount_tax'                             => 0,
                'total_discount_amount_ex_tax'                          => 0,
                'total_revenue_with_coupons'                            => 0,
                'total_cost_with_coupons'                               => 0,
                'total_profit_with_coupons'                             => 0,
                'average_margin_with_coupons'                           => 0,
                'revenue_percent_with_coupons'                          => 0,
                'order_percent_with_coupons'                            => 0,
                'profit_percent_with_coupons'                           => 0,
                'orders_with_coupons'                                   => 0,
                'orders_without_coupons'                                => 0,
                'percent_of_orders_with_coupons'                        => 0,
                'percent_of_orders_without_coupons'                     => 0,
                'total_coupons_used'                                    => 0,
                'total_coupon_quantity_used'                            => 0,
                'unique_coupon_codes_used'                              => 0,
                'coupons_per_order'                                     => 0,
                'average_coupon_discount_per_discounted_order'          => 0,
                'average_coupon_discount_percent_per_discounted_order'  => 0,
                'total_order_revenue_before_coupons'                    => 0,
                'total_coupon_discount_amount' 	                        => 0,
                'average_coupon_discount_percent'                       => 0,
            ),

            'tax_metrics' => array(
                'total_revenue_where_tax_was_collected' => 0,
                'tax_as_percentage_of_revenue' => 0,
                'orders_with_tax' => 0,
            )

        );

        $categorized_data = array(

            'order_metrics'     => array(
                'order_status_data' => array(),
                'order_cost_breakdown' => array(),
                'custom_order_cost_data' => array(),
                'payment_gateway_data' => array(),
                'payment_gateway_order_count' => array(),
                'acquisition_traffic_type'           => array(),
                'acquisition_query_parameter_keys'   => array(),
                'acquisition_query_parameter_values' => array(),
                'acquisition_landing_page'           => array(),
                'acquisition_referral_source'        => array(),
                'acquisition_campaign_name'          => array(),
                'revenue_by_day_of_week'                   => $this->get_data_by_day_container(),
                'profit_by_day_of_week'                    => $this->get_data_by_day_container(),
                'revenue_by_hour_of_day'                   => $this->get_data_by_hour_container(),
                'profit_by_hour_of_day'                    => $this->get_data_by_hour_container(),
                'order_ids' => array(),
            ),
            'customer_metrics'  => array(
                'new_vs_returning_data'     => array(
                    'new_customer'       => $default_order_summary,
                    'returning_customer' => $default_order_summary,
                ),
                'guest_vs_registered_data'  => array(
                    'guest_customer'      => $default_order_summary,
                    'registered_customer' => $default_order_summary
                ),
                'country_location_data'     => array(),
                'state_location_data'      => array(),
                'device_browser_data'       => array(),
                'device_type_data'          => array(),
            ),
            'product_metrics'   => array(
                'product_type_data'  => array(),
                'product_cat_data'   => array(),
                'product_tag_data'   => array()
            ),
            'coupon_metrics'    => array(
                'orders_with_and_without_coupons' => array(
                    'orders_with_coupons' => $default_order_summary,
                    'orders_without_coupons' => $default_order_summary,
                ),
                'order_ids' => array()
            ),
            'refund_metrics'    => array(
                'order_ids' => array(),
            ),
            'tax_metrics'       => array(
                'tax_rate_summaries' => array()
            ),

        );

        // Data Tables -> Main entity tables
        $data_table = array(
            'order_metrics'     => array(),
            'customer_metrics'  => array(),
            'product_metrics'   => array(),
            'coupon_metrics'    => array(),
            'refund_metrics'    => array(),
            'tax_metrics'       => array(),
        );

        // Data By Date Containers
        $data_by_date = array(

            'order_metrics' => array(
                'order_count_by_date'                      => $this->get_data_by_date_range_container(),
                'revenue_by_date'                          => $this->get_data_by_date_range_container(),
                'revenue_excluding_tax_by_date'            => $this->get_data_by_date_range_container(),
                'profit_by_date'                           => $this->get_data_by_date_range_container(),
                'revenue_by_traffic_type_by_date'          => array( 'no_data_available' => $this->get_data_by_date_range_container() ),
                'average_order_value_by_date'              => $this->get_data_by_date_range_container(),
                'average_order_margin_by_date'             => $this->get_data_by_date_range_container(),
            ),
            'customer_metrics' => array(
                'unique_customer_orders_by_date'           => $this->get_data_by_date_range_container(),
                'new_customer_orders_by_date'              => $this->get_data_by_date_range_container(),
                'returning_customer_orders_by_date'        => $this->get_data_by_date_range_container(),
                'guest_customer_orders_by_date'            => $this->get_data_by_date_range_container(),
                'registered_customer_orders_by_date'       => $this->get_data_by_date_range_container(),
            ),
            'product_metrics' => array(
                'product_revenue_by_date'    => $this->get_data_by_date_range_container(),
                'quantity_sold_by_date' 		=> $this->get_data_by_date_range_container(),
            ),
            'coupon_metrics' => array(
                'orders_with_coupon_by_date'     => $this->get_data_by_date_range_container(),
                'coupon_discount_amount_by_date' => $this->get_data_by_date_range_container(),
            ),
            'tax_metrics' => array(
                'taxes_collected_by_date' => $this->get_data_by_date_range_container(),
                'tax_rates_collected_by_date' => array( 'no_data_available' => $this->get_data_by_date_range_container() ),
            ),
            'refund_metrics' => array(
                'amount_refunded_by_date'       => $this->get_data_by_date_range_container(),
                'quantity_refunded_by_date' 	=> $this->get_data_by_date_range_container(),
                'orders_refunded_by_date' 	=> $this->get_data_by_date_range_container(),
            ),

        );

        // Capture Meta Variables
        $date_from                                 = $this->get_date_from();
        $date_to                                   = $this->get_date_to();
        $n_days_period                              = $this->get_n_days_range();
        $date_format                                = $this->get_filter( 'date_format_string' );
        $custom_order_cost_options                  = wpd_get_custom_order_cost_options();
        $memory_limit                               = ini_get('memory_limit');

        // Default Array Variables
        $payment_gateway_array 			            = array();
        $refunded_product_ids                       = array();
        $unique_sku_array                           = array();
        $product_item_data                          = array();
        $product_type_data                          = array();
        $product_cat_data                           = array();
        $product_tag_data                           = array();
        $filtered_product_ids                       = array();
        $custom_order_cost_data                     = array();
        $payment_gateway_data                       = array();
        $unique_customer_daily_data_tracking        = array();
        $unique_counter                             = array(
            'unique_customers_by_email' => array()
        );

        // Default Variables
        $total_db_records                           = 0;
        $total_order_count 				            = 0;
    	$largest_order_revenue 			            = 0;
		$largest_order_cost 			            = 0;
		$largest_order_profit			            = 0;
		$total_shipping_charged 		            = 0;
		$total_shipping_cost 			            = 0;
		$total_product_cost 			            = 0;
		$total_product_discounts 		            = 0;
		$total_refunds 					            = 0;
		$total_payment_gateway_costs 	            = 0;
		$total_tax_collected 			            = 0;
		$total_coupon_discounts                     = 0;
		$total_product_revenue 			            = 0;
		$total_product_revenue_ex_tax               = 0;
		$total_product_revenue_at_rrp 	            = 0;
		$total_qty_sold 				            = 0;
		$total_revenue 					            = 0;
		$total_cost 					            = 0;
		$total_profit 					            = 0;
		$margin_sum 					            = 0;
        $total_order_revenue_ex_tax                 = 0;
        $total_order_revenue_before_coupons         = 0;
        $total_order_discounts                      = 0;
        $total_order_revenue_before_discounts       = 0;
        $orders_with_discount                       = 0;
        $total_order_revenue_inc_tax_and_refunds    = 0;
        $total_skus_sold                            = 0;
        $total_product_profit                       = 0;
        $total_product_profit_at_rrp                = 0;
        $average_profit_per_product                 = 0;
        $average_product_margin                     = 0;
        $average_product_margin_at_rrp              = 0;
        $average_qty_sold_per_day                   = 0;
        $average_products_sold_per_day              = 0;
        $average_skus_sold_per_day                  = 0;
        $total_product_refund_amount                = 0;
        $total_product_line_items_sold              = 0;
        $largest_product_count_sold_per_order       = 0;
        $largest_quantity_sold_per_order            = 0;
        $total_line_items_refunded                  = 0;
        $customer_count_purchase_more_than_once     = 0;
        $customer_country_count                     = 0;
        $customer_state_count                       = 0;
        $customers_with_refund_count                = 0;
        $total_custom_order_costs                   = 0;
        $total_custom_product_costs                 = 0;
        $total_product_cost_of_goods                = 0;

        // Build Query
		$args = array(
		    'limit' 			=> -1,
		    'orderby' 			=> 'date',
		    'order' 			=> 'DESC',
		    'date_created' 		=> $date_from . "..." . $date_to, //'2018-02-01...2018-02-28',
		    'type' 				=> array( 'shop_order' ),
		    'status' 			=> wpd_paid_order_statuses(),
		    'return' 			=> 'ids',
		);

        // All time filter
        if ( $this->get_filter('date_preset') === 'all_time' ) unset( $args['date_created'] );

        // Order Status Filter
        if ( $order_status_filter ) {
            $args['status'] = $order_status_filter;
            // Needs to be flat non-array
            if ( in_array( 'any', $order_status_filter ) ) $args['status'] = 'any';
        }

        // Search by billing email
        if ( $billing_email_filter ) $args['billing_email'] = $billing_email_filter;
        if ( $customer_billing_email_filter ) $args['billing_email'] = $customer_billing_email_filter;

        // Search by user ID
        if ( $user_id_filter ) $args['customer_id'] = $user_id_filter;

        // Search By Billing Countries
        if ( $billing_country_filter && is_array($billing_country_filter) ) $args['billing_country'] = $billing_country_filter;

        // Search By IP Address
        if ( $ip_address_filter ) {
            // Initialise meta_query if not set
            if ( ! isset( $args['meta_query'] ) ) {
                $args['meta_query'] = [];
            }
            $args['meta_query'][] = array(
                'key'     => '_customer_ip_address',
                'value'   => $ip_address_filter,
                'compare' => is_array($ip_address_filter) ? 'IN' : '='
            );
        } 

        // Search By Order IDs, will ignore filters if set
        if ( $order_ids_filter && is_array($order_ids_filter) ) {
            $args['include'] = $order_ids_filter;
        }

        /**
         *  @todo We should probably use our own get all orders, 
         *  as it fetches in batches in case someone has 100k+ orders
         **/
        // If we are passing an empty array of order_ids, let's assume we are not wanting any data
        $order_ids = ( isset($args['include']) ) ? $args['include'] : (array) wc_get_orders( $args );
        $total_db_records = count( $order_ids );
        // $categorized_data['order_metrics']['order_ids'] = $order_ids;

        // Run in batches
        $batch_size = 2500;
        $offset = 0;
        $total_batches = ceil( wpd_divide($total_db_records, $batch_size) );

        while( $offset < $total_batches ) {

            // Get the current batch of order IDs
            $current_order_ids_batch = array_slice( $order_ids, $offset * $batch_size, $batch_size );
            
            if ( empty( $current_order_ids_batch ) ) break; // No more orders to process

            // Load the desired calculation cache
            wpd_setup_order_calculations_in_object_cache( $current_order_ids_batch );
            
            // Loop through order ID's to build organised data and calculate totals
            foreach ( $current_order_ids_batch as $order_id ) {

                // Memory Check
                if ( wpd_is_memory_usage_greater_than(90) ) {
                    $this->set_error(
                        sprintf(
                            /* translators: 1: Number of processed orders, 2: Total number of orders, 3: PHP memory limit */
                            __( 'You\'ve exhausted your memory usage after %1$s out of %2$s orders. Increase your PHP memory limit or reduce the date range. Your current PHP memory limit is %3$s.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                            $total_order_count,
                            $total_db_records,
                            $memory_limit
                        )
                    );
                    break;
                }

                // Calculate order totals via cache
                $order_data = wpd_calculate_cost_profit_by_order( $order_id );

                // Safety Check
                if ( ! is_array($order_data) ) continue;

                // If were looking at subscriptions, only load subscription renewal orders
                if ( isset( $additional_data['subscriptions'] ) && $additional_data['subscriptions'] ) {
                    if ( $order_data['is_renewal_subscription_order'] != 1 ) continue;
                }

                // Load Acquisition Vars
                $landing_page_url_raw   = $order_data['landing_page_url'];
                $referral_source_url    = $order_data['referral_source_url'];
                $campaign_name          = $order_data['campaign_name'];
                $traffic_type 		    = $order_data['traffic_source'];
                $query_params 		    = wpd_get_query_params( $landing_page_url_raw );
                $landing_page           = wpd_strip_params_from_url( $landing_page_url_raw );

                // Transform if required
                if ( empty($traffic_type) ) $traffic_type = 'unknown';
                if ( empty($landing_page) ) $landing_page = 'unknown';
                if ( empty($referral_source_url) ) $referral_source_url = 'unknown';

                /**
                 * 
                 *  Apply filtering for items we can't filter out in the initial request
                 *  @todo ideally get these in the initial request
                 * 
                 **/
                // Traffic Source e.g. Direct, Organic, etc etc..
                if ( $traffic_source_filter && ! in_array( $traffic_type, $traffic_source_filter ) ) continue;
                
                // Device Type e.g. Desktop, Mobile, Tablet, etc etc..
                if ( $device_type_filter && ! in_array( strtolower($order_data['device_type']), $device_type_filter ) ) continue;
                
                // Query Parameter Key-Value Pairs (e.g., utm_campaign=Summer_Sale)
                if ( $query_parameter_values_filter && is_array($query_parameter_values_filter) ) {
                    $has_matching_query_param = false;
                    
                    if ( is_array($query_params) && ! empty($query_params) ) {
                        // Check each filter pair against the order's query parameters
                        foreach ( $query_parameter_values_filter as $filter_pair ) {
                            // Ensure filter pair has both key and value
                            if ( ! isset($filter_pair['key']) || ! isset($filter_pair['value']) ) continue;
                            
                            $filter_key = $filter_pair['key'];
                            $filter_value = $filter_pair['value'];
                            
                            // Check if this key exists in the order's query params and matches the value
                            if ( isset($query_params[$filter_key]) && $query_params[$filter_key] === $filter_value ) {
                                $has_matching_query_param = true;
                                break; // Found a match, no need to check further
                            }
                        }
                    }
                    
                    if ( ! $has_matching_query_param ) continue;
                }

                // Filter by product data if required
                if ( is_array($product_ids_filter) && ! empty($product_ids_filter) ) {
                    
                    // Collect an array of the order's product IDs
                    $order_product_ids = ( isset($order_data['product_data']) && is_array($order_data['product_data'] ) && ! empty($order_data['product_data'])) ? array_keys( $order_data['product_data'] ) : array();
                    
                    // Skip over this entire order, if we are filtering by product ID
                    if ( empty( array_intersect( $product_ids_filter, $order_product_ids ) ) ) continue;

                    // Reset values we need to override
                    $order_data['total_product_revenue_at_rrp']     = 0;
                    $order_data['total_product_revenue']            = 0;
                    $order_data['total_product_revenue_ex_tax']     = 0;
                    $order_data['total_product_discounts']          = 0;
                    $order_data['total_product_discount_percent']   = 0;
                    $order_data['total_product_profit']             = 0;
                    $order_data['total_skus_sold']                  = 0;
                    $order_data['total_product_cost']               = 0;

                    // Will need to adjust all order data values here
                    foreach( $order_data['product_data'] as $product_id => $product_data ) {

                        // Some filtering of non-target product IDs
                        if ( ! in_array( $product_id, $product_ids_filter ) ) {

                            // Remove from array
                            unset( $order_data['product_data'][$product_id] );

                            // Don't update any calculations
                            continue;
                            
                        }

                        // Calculate new values
                        $order_data['total_skus_sold']                  ++;
                        $order_data['total_product_revenue_at_rrp']     += $product_data['product_revenue_at_rrp'];
                        $order_data['total_product_revenue']            += $product_data['product_revenue'];
                        $order_data['total_product_revenue_ex_tax']     += $product_data['product_revenue_excluding_tax'];
                        $order_data['total_product_discounts']          += $product_data['product_discount_amount'];
                        $order_data['total_product_profit']             += $product_data['total_profit'];
                        $order_data['total_product_cost']               += $product_data['total_cost_of_goods'];

                    }

                    // Calculate order product discount
                    $order_data['total_product_discount_percent'] = wpd_calculate_percentage( $order_data['total_product_discounts'], $order_data['total_product_revenue_at_rrp'] );

                }

                // Filter by product category if required
                if ( $product_category_filter ) {

                    // Used to skip an entire order if required
                    $order_has_target_product_category_or_tag = false;

                    // Reset values we need to override
                    $order_data['total_product_revenue_at_rrp']     = 0;
                    $order_data['total_product_revenue']            = 0;
                    $order_data['total_product_revenue_ex_tax']     = 0;
                    $order_data['total_product_discounts']          = 0;
                    $order_data['total_product_discount_percent']   = 0;
                    $order_data['total_product_profit']             = 0;
                    $order_data['total_skus_sold']                  = 0;
                    $order_data['total_product_cost']               = 0;

                    if ( is_array($order_data['product_data']) && ! empty($order_data['product_data']) ) {

                        foreach( $order_data['product_data'] as $product_id => $product_data ) {

                            $product_data_store = $this->get_product_data_cache( $product_id );
                            $product_is_in_target_category = false;

                            if ( is_array($product_data_store['product_category']) && ! empty($product_data_store['product_category']) ) {

                                foreach( $product_data_store['product_category'] as $product_category_taxonomy ) {

                                    // Safety check
                                    if ( ! is_a( $product_category_taxonomy, 'WP_Term' ) ) continue;

                                    // Check if the product category is in the target category
                                    if ( in_array( $product_category_taxonomy->term_id, $product_category_filter ) ) {

                                        // We've hit a target product category
                                        $order_has_target_product_category_or_tag = true;
                                        $product_is_in_target_category = true;

                                        // Calculate new values
                                        $order_data['total_skus_sold']                  ++;
                                        $order_data['total_product_revenue_at_rrp']     += $product_data['product_revenue_at_rrp'];
                                        $order_data['total_product_revenue']            += $product_data['product_revenue'];
                                        $order_data['total_product_revenue_ex_tax']     += $product_data['product_revenue_excluding_tax'];
                                        $order_data['total_product_discounts']          += $product_data['product_discount_amount'];
                                        $order_data['total_product_profit']             += $product_data['total_profit'];
                                        $order_data['total_product_cost']               += $product_data['total_cost_of_goods'];

                                    }

                                }

                            }

                            // Remove the product data if not hit
                            if ( ! $product_is_in_target_category ) unset( $order_data['product_data'][$product_id] );

                        }

                    }

                    // If no targets were hit, skip the entire order
                    if ( ! $order_has_target_product_category_or_tag ) continue;

                    // Any recalculations required
                    $order_data['total_product_discount_percent'] = wpd_calculate_percentage( $order_data['total_product_discounts'], $order_data['total_product_revenue_at_rrp'] );

                }

                // Filter by product tag if required
                if ( $product_tag_filter ) {

                    // Used to skip an entire order if required
                    $order_has_target_product_tag = false;

                    // Reset values we need to override
                    $order_data['total_product_revenue_at_rrp']     = 0;
                    $order_data['total_product_revenue']            = 0;
                    $order_data['total_product_revenue_ex_tax']     = 0;
                    $order_data['total_product_discounts']          = 0;
                    $order_data['total_product_discount_percent']   = 0;
                    $order_data['total_product_profit']             = 0;
                    $order_data['total_skus_sold']                  = 0;
                    $order_data['total_product_cost']               = 0;

                    if ( is_array($order_data['product_data']) && ! empty($order_data['product_data']) ) {

                        foreach( $order_data['product_data'] as $product_id => $product_data ) {

                            $product_data_store = $this->get_product_data_cache( $product_id );
                            $product_is_in_target_tag = false;

                            if ( is_array($product_data_store['product_tags']) && ! empty($product_data_store['product_tags']) ) {

                                foreach( $product_data_store['product_tags'] as $product_tag_taxonomy ) {

                                    // Safety check
                                    if ( ! is_a( $product_tag_taxonomy, 'WP_Term' ) ) continue;

                                    // Check if the product tag is in the target tag
                                    if ( in_array( $product_tag_taxonomy->term_id, $product_tag_filter ) ) {

                                        // We've hit a target product tag
                                        $order_has_target_product_tag = true;
                                        $product_is_in_target_tag = true;

                                        // Calculate new values
                                        $order_data['total_skus_sold']                  ++;
                                        $order_data['total_product_revenue_at_rrp']     += $product_data['product_revenue_at_rrp'];
                                        $order_data['total_product_revenue']            += $product_data['product_revenue'];
                                        $order_data['total_product_revenue_ex_tax']     += $product_data['product_revenue_excluding_tax'];
                                        $order_data['total_product_discounts']          += $product_data['product_discount_amount'];
                                        $order_data['total_product_profit']             += $product_data['total_profit'];
                                        $order_data['total_product_cost']               += $product_data['total_cost_of_goods'];

                                    }

                                }

                            }

                            // Remove the product data if not hit
                            if ( ! $product_is_in_target_tag ) unset( $order_data['product_data'][$product_id] );

                        }

                    }

                    // If no targets were hit, skip the entire order
                    if ( ! $order_has_target_product_tag ) continue;

                    // Any recalculations required
                    $order_data['total_product_discount_percent'] = wpd_calculate_percentage( $order_data['total_product_discounts'], $order_data['total_product_revenue_at_rrp'] );

                }

                // Total Order Count
                $total_order_count++;

                $orders_data_table_count = is_array($data_table['order_metrics']) ? count($data_table['order_metrics']) : 0;
                if ( $orders_data_table_count < $orders_data_table_limit ) {
                    // Load the main payload into our organised array
                    $data_table['order_metrics'][$order_id] = $order_data;
                }

                // Store vars that are used in calculations
                $order_cost 			                    = $order_data['total_order_cost'];
                $order_revenue 			                    = $order_data['total_order_revenue'];
                $order_profit 			                    = $order_data['total_order_profit'];
                $order_margin 			                    = $order_data['total_order_margin'];
                $payment_gateway 		                    = ( $order_data['payment_gateway'] ) ? $order_data['payment_gateway'] : 'Unknown';
                $order_revenue_ex_tax                       = $order_data['total_order_revenue_excluding_tax'];

                // Make consecutive totals calculations 
                $total_revenue 					            += $order_revenue;
                $total_order_revenue_ex_tax                 += $order_revenue_ex_tax;
                $total_cost 					            += $order_cost;
                $total_profit 					            += $order_profit;
                $margin_sum 					            += $order_margin;
                $total_shipping_charged 		            += $order_data['total_shipping_charged'];
                $total_shipping_cost 			            += $order_data['total_shipping_cost'];
                $total_product_cost 			            += $order_data['total_product_cost'];
                $total_payment_gateway_costs 	            += $order_data['payment_gateway_cost'];
                $total_tax_collected 			            += $order_data['total_order_tax'];
                $total_qty_sold 				            += $order_data['total_qty_sold'];
                $total_product_revenue 			            += $order_data['total_product_revenue'];
                $total_product_revenue_ex_tax               += $order_data['total_product_revenue_ex_tax'];
                $total_order_revenue_inc_tax_and_refunds    += $order_data['total_order_revenue_inc_tax_and_refunds'];
                $total_product_cost_of_goods 				+= $order_data['total_product_cost_of_goods'];

                // Discounting Data
                $total_product_discounts 		            += (float) $order_data['total_product_discounts'];
                $total_product_revenue_at_rrp 	            += (float) $order_data['total_product_revenue_at_rrp'];
                $total_coupon_discounts                     += (float) $order_data['total_coupon_discounts'];
                $total_order_revenue_before_coupons         += (float) $order_data['total_order_revenue_before_coupons'];
                $total_order_discounts                      += (float) $order_data['total_order_discounts'];
                $total_order_revenue_before_discounts       += (float) $order_data['total_order_revenue_before_discounts'];

                // Accounts for any potential rounding issues
                if ( $order_data['total_order_discounts'] > 0.1 ) $orders_with_discount++;

                // Set highest values
                if ( $order_revenue > $largest_order_revenue ) $largest_order_revenue = $order_revenue;
                if ( $order_cost > $largest_order_cost ) $largest_order_cost = $order_cost;
                if ( $order_profit > $largest_order_profit ) $largest_order_profit = $order_profit;

                // Set payment gateway index & iterate counter
                if ( ! isset($payment_gateway_array[$payment_gateway]) ) $payment_gateway_array[$payment_gateway] = 0; 
                $payment_gateway_array[$payment_gateway]++;

                // Date Range Vars
                $date_created_unix  = $order_data['date_created'];
                $date_range_key     = date( $date_format, $date_created_unix );

                // Tax Data
                if ( $order_data['total_order_tax'] > 0 ) {
                    $totals['tax_metrics']['orders_with_tax']++;
                    $totals['tax_metrics']['total_revenue_where_tax_was_collected'] += $order_data['total_order_revenue'];
                    if( isset($data_by_date['tax_metrics']['taxes_collected_by_date'][$date_range_key]) ) $data_by_date['tax_metrics']['taxes_collected_by_date'][$date_range_key] += $order_data['total_order_tax'];
                }

                // Process Refund Calculations
                if ( $order_data['total_refund_amount'] > 0 ) {

                    // Total order refund count ++
                    $totals['refund_metrics']['total_order_count_with_refund']++;

                    // Full Refund
                    if ( $order_data['full_refund'] ) $totals['refund_metrics']['total_order_count_with_full_refund']++;

                    // Partial Refund
                    if ( $order_data['partial_refund'] ) $totals['refund_metrics']['total_order_count_with_partial_refund']++;

                    // Total Refund Quantity
                    $totals['refund_metrics']['total_qty_refunded'] += $order_data['total_refund_quantity'];

                    // Total Refund Amount
                    $total_refunds += $order_data['total_refund_amount'];

                    // Store these orders in the refunded orders table
                    $data_table['refund_metrics'][] = $order_data;

                    // Build an array of refunded product ID's
                    if ( is_array($order_data['refund_data']) && ! empty($order_data['refund_data']) ) {

                        $refunded_product_ids = array_unique( array_merge( $refunded_product_ids, array_keys( $order_data['refund_data'] ) ) );

                    }

                    // Refund data
                    if ( isset( $additional_data['refunds'] ) && $additional_data['refunds'] ) {
                        if( isset($data_by_date['refund_metrics']['quantity_refunded_by_date'][$date_range_key]) ) $data_by_date['refund_metrics']['quantity_refunded_by_date'][$date_range_key] += $order_data['total_refund_quantity'];
                        if( isset($data_by_date['refund_metrics']['amount_refunded_by_date'][$date_range_key]) ) $data_by_date['refund_metrics']['amount_refunded_by_date'][$date_range_key] += $order_data['total_refund_amount'];
                        if( isset($data_by_date['refund_metrics']['orders_refunded_by_date'][$date_range_key]) ) $data_by_date['refund_metrics']['orders_refunded_by_date'][$date_range_key] ++;
                    }

                }

                // Custom Order Costs
                if ( is_array($order_data['custom_order_cost_data']) && ! empty($order_data['custom_order_cost_data']) ) {

                    foreach( $order_data['custom_order_cost_data'] as $custom_order_cost_slug => $custom_order_cost_value ) {

                        // Only add if its a number
                        if ( is_numeric($custom_order_cost_value) && $custom_order_cost_value > 0 ) {

                            // Get the clean label
                            $custom_order_cost_label = ( isset($custom_order_cost_options[$custom_order_cost_slug]) ) ? $custom_order_cost_options[$custom_order_cost_slug]['label'] : $custom_order_cost_slug;

                            // Setup default variable
                            if ( ! isset($custom_order_cost_data[$custom_order_cost_label]) ) $custom_order_cost_data[$custom_order_cost_label] = 0;

                            // Add to total
                            $custom_order_cost_data[$custom_order_cost_label] += $custom_order_cost_value;

                            // Add to total
                            $total_custom_order_costs += $custom_order_cost_value;

                        }

                    }

                }

                // Total Custom Product Costs
                if ( is_array($order_data['custom_product_cost_data']) && ! empty($order_data['custom_product_cost_data']) ) {

                    foreach( $order_data['custom_product_cost_data'] as $custom_product_cost_slug => $custom_product_cost_data ) {

                        // Only add if its a number
                        if ( is_numeric($custom_product_cost_data['total_value']) && $custom_product_cost_data['total_value'] > 0 ) {

                            // Setup default variable
                            if ( ! isset($custom_order_cost_data[$custom_product_cost_data['label']]) ) $custom_order_cost_data[$custom_product_cost_data['label']] = 0;

                            // Add to total
                            $custom_order_cost_data[$custom_product_cost_data['label']] += $custom_product_cost_data['total_value'];

                            // Increment Total
                            $total_custom_product_costs += (float) $custom_product_cost_data['total_value'];

                        }

                    }

                }

                // Inside the order loop, after processing the order:
                $gateway_id = $payment_gateway;
                $gateway_title = $payment_gateway;
                
                // Initialize gateway data if not exists
                if (!isset($payment_gateway_data[$payment_gateway])) {
                    $payment_gateway_data[$gateway_id] = array(
                        'title' => $gateway_title,
                        'order_count' => 0,
                        'revenue' => 0,
                        'gateway_fees' => 0,
                        'percent_of_orders' => 0,
                        'percent_of_revenue' => 0,
                        'average_order_value' => 0
                    );
                }
                // Update gateway metrics
                $payment_gateway_data[$gateway_id]['order_count']++;
                $payment_gateway_data[$gateway_id]['revenue'] += $order_revenue;
                $payment_gateway_data[$gateway_id]['gateway_fees'] += $order_data['payment_gateway_cost'];

                // Order status data
                if ( ! isset($categorized_data['order_metrics']['order_status_data'][$order_data['order_status']]) ) $categorized_data['order_metrics']['order_status_data'][$order_data['order_status']] = $default_order_summary;
                $categorized_data['order_metrics']['order_status_data'][$order_data['order_status']]['total_revenue'] += $order_revenue;
                $categorized_data['order_metrics']['order_status_data'][$order_data['order_status']]['total_cost'] += $order_cost;
                $categorized_data['order_metrics']['order_status_data'][$order_data['order_status']]['total_profit'] += $order_profit;
                $categorized_data['order_metrics']['order_status_data'][$order_data['order_status']]['total_order_count']++;

                /**
                 * 
                 *  Additional Data For Orders Report
                 * 
                 **/
                if ( isset( $additional_data['orders'] ) && $additional_data['orders'] ) {

                    // Date Keys
                    $day_of_week_key    = date( 'D', $date_created_unix );
                    $hour_of_day_key    = date( 'ga', $date_created_unix );

                    // Add Date Range Values
                    if( isset($data_by_date['order_metrics']['order_count_by_date'][$date_range_key]) ) $data_by_date['order_metrics']['order_count_by_date'][$date_range_key]++;
                    if( isset($data_by_date['order_metrics']['revenue_by_date'][$date_range_key]) ) $data_by_date['order_metrics']['revenue_by_date'][$date_range_key] += $order_revenue;
                    if( isset($data_by_date['order_metrics']['revenue_excluding_tax_by_date'][$date_range_key]) ) $data_by_date['order_metrics']['revenue_excluding_tax_by_date'][$date_range_key] += $order_revenue_ex_tax;
                    if( isset($data_by_date['order_metrics']['profit_by_date'][$date_range_key]) ) $data_by_date['order_metrics']['profit_by_date'][$date_range_key] += $order_profit;
                    
                    if( isset($data_by_date['order_metrics']['average_order_value_by_date'][$date_range_key]) ) $data_by_date['order_metrics']['average_order_value_by_date'][$date_range_key] = wpd_divide( $data_by_date['order_metrics']['revenue_by_date'][$date_range_key], $data_by_date['order_metrics']['order_count_by_date'][$date_range_key], 2 );
                    if( isset($data_by_date['order_metrics']['average_order_margin_by_date'][$date_range_key]) ) $data_by_date['order_metrics']['average_order_margin_by_date'][$date_range_key] = wpd_calculate_margin( $data_by_date['order_metrics']['profit_by_date'][$date_range_key], $data_by_date['order_metrics']['revenue_excluding_tax_by_date'][$date_range_key] );

                    // Daily Data
                    if( isset($categorized_data['order_metrics']['revenue_by_day_of_week'][$day_of_week_key]) ) $categorized_data['order_metrics']['revenue_by_day_of_week'][$day_of_week_key] += $order_revenue;
                    if( isset($categorized_data['order_metrics']['profit_by_day_of_week'][$day_of_week_key]) ) $categorized_data['order_metrics']['profit_by_day_of_week'][$day_of_week_key] += $order_profit;
                    if( isset($categorized_data['order_metrics']['revenue_by_hour_of_day'][$hour_of_day_key]) ) $categorized_data['order_metrics']['revenue_by_hour_of_day'][$hour_of_day_key] += $order_revenue;
                    if( isset($categorized_data['order_metrics']['profit_by_hour_of_day'][$hour_of_day_key]) ) $categorized_data['order_metrics']['profit_by_hour_of_day'][$hour_of_day_key] += $order_profit;

                }

                /**
                 * 
                 * Additional Data For Products Report
                 *  
                 **/
                if ( isset( $additional_data['products'] ) && $additional_data['products'] ) {

                    $largest_product_count_sold_per_order = ( $largest_product_count_sold_per_order < count( array_keys( $order_data['product_data'] ) ) ) ? count( array_keys( $order_data['product_data'] ) ) : $largest_product_count_sold_per_order; // New

                    // Data By Date
                    if( isset($data_by_date['product_metrics']['product_revenue_by_date'][$date_range_key]) ) $data_by_date['product_metrics']['product_revenue_by_date'][$date_range_key] += $order_data['total_product_revenue'];
                    if( isset($data_by_date['product_metrics']['quantity_sold_by_date'][$date_range_key]) ) $data_by_date['product_metrics']['quantity_sold_by_date'][$date_range_key] += $order_data['total_qty_sold'];

                }

                /**
                 * 
                 *  Additional Data For Acquisitions Report
                 * 
                 **/
                if ( isset( $additional_data['orders'] ) && $additional_data['orders'] ) {
                    
                    // Default containers
                    if ( ! isset($categorized_data['order_metrics']['acquisition_traffic_type'][$traffic_type]) ) $categorized_data['order_metrics']['acquisition_traffic_type'][$traffic_type] = $default_order_summary;
                    if ( ! isset($categorized_data['order_metrics']['acquisition_landing_page'][$landing_page]) ) $categorized_data['order_metrics']['acquisition_landing_page'][$landing_page] = $default_order_summary;
                    if ( ! isset($categorized_data['order_metrics']['acquisition_referral_source'][$referral_source_url]) ) $categorized_data['order_metrics']['acquisition_referral_source'][$referral_source_url] = $default_order_summary;
                    if ( ! isset($categorized_data['order_metrics']['acquisition_campaign_name'][$campaign_name]) ) $categorized_data['order_metrics']['acquisition_campaign_name'][$campaign_name] = $default_order_summary;
                    // Traffic Type
                    $categorized_data['order_metrics']['acquisition_traffic_type'][$traffic_type]['total_revenue']         += $order_revenue;
                    $categorized_data['order_metrics']['acquisition_traffic_type'][$traffic_type]['total_cost']            += $order_cost;
                    $categorized_data['order_metrics']['acquisition_traffic_type'][$traffic_type]['total_profit']          += $order_profit;
                    $categorized_data['order_metrics']['acquisition_traffic_type'][$traffic_type]['total_order_count']++;

                    // Landing Page
                    $categorized_data['order_metrics']['acquisition_landing_page'][$landing_page]['total_revenue']         += $order_revenue;
                    $categorized_data['order_metrics']['acquisition_landing_page'][$landing_page]['total_cost']            += $order_cost;
                    $categorized_data['order_metrics']['acquisition_landing_page'][$landing_page]['total_profit']          += $order_profit;
                    $categorized_data['order_metrics']['acquisition_landing_page'][$landing_page]['total_order_count']++;

                    // Referral Source
                    $categorized_data['order_metrics']['acquisition_referral_source'][$referral_source_url]['total_revenue']         += $order_revenue;
                    $categorized_data['order_metrics']['acquisition_referral_source'][$referral_source_url]['total_cost']            += $order_cost;
                    $categorized_data['order_metrics']['acquisition_referral_source'][$referral_source_url]['total_profit']          += $order_profit;
                    $categorized_data['order_metrics']['acquisition_referral_source'][$referral_source_url]['total_order_count']++;

                    // Campaign Name
                    $categorized_data['order_metrics']['acquisition_campaign_name'][$campaign_name]['total_revenue']         += $order_revenue;
                    $categorized_data['order_metrics']['acquisition_campaign_name'][$campaign_name]['total_cost']            += $order_cost;
                    $categorized_data['order_metrics']['acquisition_campaign_name'][$campaign_name]['total_profit']          += $order_profit;
                    $categorized_data['order_metrics']['acquisition_campaign_name'][$campaign_name]['total_order_count']++;
                    
                    // Query Parameters
                    if ( is_array($query_params) && ! empty($query_params) ) {

                        // Loop through query param array
                        foreach( $query_params as $key => $value ) {

                            // Transform as required
                            $key = ( ! empty($key) ) ? $key : 'unset';
                            $value = ( ! empty($value) ) ? $value : 'unset';

                            // Defaults
                            if ( ! isset($categorized_data['order_metrics']['acquisition_query_parameter_keys'][$key]) ) $categorized_data['order_metrics']['acquisition_query_parameter_keys'][$key] = $default_order_summary;
                            if ( ! isset($categorized_data['order_metrics']['acquisition_query_parameter_values'][$value]) ) $categorized_data['order_metrics']['acquisition_query_parameter_values'][$value] = $default_order_summary;

                            // Keys
                            $categorized_data['order_metrics']['acquisition_query_parameter_keys'][$key]['total_revenue']         += $order_revenue;
                            $categorized_data['order_metrics']['acquisition_query_parameter_keys'][$key]['total_cost']            += $order_cost;
                            $categorized_data['order_metrics']['acquisition_query_parameter_keys'][$key]['total_profit']          += $order_profit;
                            $categorized_data['order_metrics']['acquisition_query_parameter_keys'][$key]['total_order_count']++;

                            // Values
                            $categorized_data['order_metrics']['acquisition_query_parameter_values'][$value]['total_revenue']         += $order_revenue;
                            $categorized_data['order_metrics']['acquisition_query_parameter_values'][$value]['total_cost']            += $order_cost;
                            $categorized_data['order_metrics']['acquisition_query_parameter_values'][$value]['total_profit']          += $order_profit;
                            $categorized_data['order_metrics']['acquisition_query_parameter_values'][$value]['total_order_count']++;

                        }

                    }

                    // Remove the no data available container
                    if ( isset($data_by_date['order_metrics']['revenue_by_traffic_type_by_date']['no_data_available']) ) $data_by_date['order_metrics']['revenue_by_traffic_type_by_date'] = array();

                    // Daily Data - Setup date container for traffic type & enter data
                    if ( ! isset($data_by_date['order_metrics']['revenue_by_traffic_type_by_date'][$traffic_type]) ) $data_by_date['order_metrics']['revenue_by_traffic_type_by_date'][$traffic_type] = $this->get_data_by_date_range_container();
                    if ( isset($data_by_date['order_metrics']['revenue_by_traffic_type_by_date'][$traffic_type][$date_range_key]) ) $data_by_date['order_metrics']['revenue_by_traffic_type_by_date'][$traffic_type][$date_range_key] += $order_revenue;

                }

                /**
                 * 
                 *  Additional Data For Customers Report
                 * 
                 **/
                if ( isset( $additional_data['customers'] ) && $additional_data['customers'] ) {

                    // Collect Vars
                    $user_id                = ( ! empty( $order_data['user_id'] ) ) ? $order_data['user_id'] : $order_data['user_id'];
                    $is_registered_user     = $order_data['is_registered_user']; // 1/0
                    $new_customer           = $order_data['new_returning_customer'];   // new / returning
                    $billing_first_name     = ( ! empty( $order_data['billing_first_name'] ) ) ? $order_data['billing_first_name'] : 'Unknown';
                    $billing_last_name      = ( ! empty( $order_data['billing_last_name'] ) ) ? $order_data['billing_last_name'] : 'Unknown';
                    $billing_email          = ( ! empty( $order_data['billing_email'] ) ) ?  $order_data['billing_email'] : 'Unknown';
                    $billing_phone          = ( ! empty( $order_data['billing_phone'] ) ) ?  $order_data['billing_phone'] : 'Unknown';
                    
                    // Store unique billing email addresses
                    $unique_counter['unique_customers_by_email'][$billing_email] = true;
                    
                    // Get clean country and state names (performance optimized)
                    $billing_country_code   = ( ! empty( $order_data['billing_country'] ) ) ? $order_data['billing_country'] : 'Unknown';
                    $billing_state_code     = ( ! empty( $order_data['billing_state'] ) ) ? $order_data['billing_state'] : 'Unknown';
                    
                    // Get clean country name
                    $billing_country        = ( isset( WC()->countries->countries[$billing_country_code] ) ) ? WC()->countries->countries[$billing_country_code] : $billing_country_code;
                    
                    // Get clean state name (only if country is valid)
                    $billing_state          = $billing_state_code;
                    if ( $billing_country_code !== 'Unknown' && isset( WC()->countries->countries[$billing_country_code] ) ) {
                        $state_names = (array) WC()->countries->get_states( $billing_country_code );
                        if ( isset( $state_names[$billing_state_code] ) ) {
                            $billing_state = $state_names[$billing_state_code];
                        }
                    }
                    
                    $billing_postcode       = ( ! empty( $order_data['billing_postcode'] ) ) ? $order_data['billing_postcode'] : 'Unknown';
                    $billing_company        = ( ! empty( $order_data['billing_company'] ) ) ? $order_data['billing_company'] : 'Unknown';
                    $device_type            = ( isset( $order_data['device_type'] ) ) ? $order_data['device_type'] : 'Unknown';
                    $device_browser         = ( isset( $order_data['device_browser'] ) ) ? $order_data['device_browser'] : 'Unknown';

                    // Track unique customers
                    if ( ! in_array($billing_email, $unique_customer_daily_data_tracking) ) {
                        $unique_customer_daily_data_tracking[] = $billing_email;
                        if ( isset($data_by_date['customer_metrics']['unique_customer_orders_by_date'][$date_range_key]) ) $data_by_date['customer_metrics']['unique_customer_orders_by_date'][$date_range_key]++;
                    }

                    // Billing Location -> Setup default country data
                    if ( ! isset($categorized_data['customer_metrics']['country_location_data'][$billing_country]) ) {

                        $categorized_data['customer_metrics']['country_location_data'][$billing_country] = $default_order_summary;
                        $categorized_data['customer_metrics']['country_location_data'][$billing_country]['customers'] = array();
                        $categorized_data['customer_metrics']['country_location_data'][$billing_country]['customer_count'] = 0;

                    }
                    // Billing Location -> Setup default state data
                    if ( ! isset($categorized_data['customer_metrics']['state_location_data'][$billing_state]) ) {
                        $categorized_data['customer_metrics']['state_location_data'][$billing_state] = $default_order_summary;
                        $categorized_data['customer_metrics']['state_location_data'][$billing_state]['customers'] = array();
                        $categorized_data['customer_metrics']['state_location_data'][$billing_state]['customer_count'] = 0;
                    } 
                    
                    // Billing Location -> Calculate Info (Country)
                    $categorized_data['customer_metrics']['country_location_data'][$billing_country]['total_revenue'] += $order_revenue;
                    $categorized_data['customer_metrics']['country_location_data'][$billing_country]['total_cost'] += $order_cost;
                    $categorized_data['customer_metrics']['country_location_data'][$billing_country]['total_profit'] += $order_profit;
                    $categorized_data['customer_metrics']['country_location_data'][$billing_country]['total_order_count']++;

                    // Billing Location -> Calculate Info (Country)
                    $categorized_data['customer_metrics']['state_location_data'][$billing_state]['total_revenue'] += $order_revenue;
                    $categorized_data['customer_metrics']['state_location_data'][$billing_state]['total_cost'] += $order_cost;
                    $categorized_data['customer_metrics']['state_location_data'][$billing_state]['total_profit'] += $order_profit;
                    $categorized_data['customer_metrics']['state_location_data'][$billing_state]['total_order_count']++;

                    // Customer count
                    if ( ! in_array($billing_email, $categorized_data['customer_metrics']['country_location_data'][$billing_country]['customers']) ) {
                        $categorized_data['customer_metrics']['country_location_data'][$billing_country]['customers'][] = $billing_email;
                        $categorized_data['customer_metrics']['country_location_data'][$billing_country]['customer_count']++;
                    }

                    // Customer count
                    if ( ! in_array($billing_email, $categorized_data['customer_metrics']['state_location_data'][$billing_state]['customers']) ) {
                        $categorized_data['customer_metrics']['state_location_data'][$billing_state]['customers'][] = $billing_email;
                        $categorized_data['customer_metrics']['state_location_data'][$billing_state]['customer_count']++;
                    }

                    // Device Data -> Browser
                    if ( ! isset($categorized_data['customer_metrics']['device_browser_data'][$device_browser]) ) $categorized_data['customer_metrics']['device_browser_data'][$device_browser] = $default_order_summary;
                    $categorized_data['customer_metrics']['device_browser_data'][$device_browser]['total_revenue'] += $order_revenue;
                    $categorized_data['customer_metrics']['device_browser_data'][$device_browser]['total_cost'] += $order_cost;
                    $categorized_data['customer_metrics']['device_browser_data'][$device_browser]['total_profit'] += $order_profit;
                    $categorized_data['customer_metrics']['device_browser_data'][$device_browser]['total_order_count']++;

                    // Device Data -> Device Type
                    if ( ! isset($categorized_data['customer_metrics']['device_type_data'][$device_type]) ) $categorized_data['customer_metrics']['device_type_data'][$device_type] = $default_order_summary;
                    $categorized_data['customer_metrics']['device_type_data'][$device_type]['total_revenue'] += $order_revenue;
                    $categorized_data['customer_metrics']['device_type_data'][$device_type]['total_cost'] += $order_cost;
                    $categorized_data['customer_metrics']['device_type_data'][$device_type]['total_profit'] += $order_profit;
                    $categorized_data['customer_metrics']['device_type_data'][$device_type]['total_order_count']++;

                    // New vs Returning
                    if ( $new_customer == 'new' ) {

                        // Summary Data
                        $categorized_data['customer_metrics']['new_vs_returning_data']['new_customer']['distinct_count'][] = $billing_email;
                        $categorized_data['customer_metrics']['new_vs_returning_data']['new_customer']['total_revenue'] += $order_revenue;
                        $categorized_data['customer_metrics']['new_vs_returning_data']['new_customer']['total_cost'] += $order_cost;
                        $categorized_data['customer_metrics']['new_vs_returning_data']['new_customer']['total_profit'] += $order_profit;
                        $categorized_data['customer_metrics']['new_vs_returning_data']['new_customer']['total_order_count']++;

                        // Daily data
                        if ( isset($data_by_date['customer_metrics']['new_customer_orders_by_date'][$date_range_key]) ) $data_by_date['customer_metrics']['new_customer_orders_by_date'][$date_range_key]++;
                    
                    } elseif ( $new_customer == 'returning' ) {

                        // Summary Data
                        $categorized_data['customer_metrics']['new_vs_returning_data']['returning_customer']['distinct_count'][] = $billing_email;
                        $categorized_data['customer_metrics']['new_vs_returning_data']['returning_customer']['total_revenue'] += $order_revenue;
                        $categorized_data['customer_metrics']['new_vs_returning_data']['returning_customer']['total_cost'] += $order_cost;
                        $categorized_data['customer_metrics']['new_vs_returning_data']['returning_customer']['total_profit'] += $order_profit;
                        $categorized_data['customer_metrics']['new_vs_returning_data']['returning_customer']['total_order_count']++;

                        // Daily Data
                        if ( isset($data_by_date['customer_metrics']['returning_customer_orders_by_date'][$date_range_key]) ) $data_by_date['customer_metrics']['returning_customer_orders_by_date'][$date_range_key]++;
                    
                    }

                    // Guest vs Registered
                    if ( $is_registered_user ) {

                        // Summary Data
                        $categorized_data['customer_metrics']['guest_vs_registered_data']['registered_customer']['distinct_count'][] = $billing_email;
                        $categorized_data['customer_metrics']['guest_vs_registered_data']['registered_customer']['total_revenue'] += $order_revenue;
                        $categorized_data['customer_metrics']['guest_vs_registered_data']['registered_customer']['total_cost'] += $order_cost;
                        $categorized_data['customer_metrics']['guest_vs_registered_data']['registered_customer']['total_profit'] += $order_profit;
                        $categorized_data['customer_metrics']['guest_vs_registered_data']['registered_customer']['total_order_count']++;

                        // Daily Data
                        if ( isset($data_by_date['customer_metrics']['registered_customer_orders_by_date'][$date_range_key]) ) $data_by_date['customer_metrics']['registered_customer_orders_by_date'][$date_range_key]++;

                    } else {

                        // Summary Data
                        $categorized_data['customer_metrics']['guest_vs_registered_data']['guest_customer']['distinct_count'][] = $billing_email;
                        $categorized_data['customer_metrics']['guest_vs_registered_data']['guest_customer']['total_revenue'] += $order_revenue;
                        $categorized_data['customer_metrics']['guest_vs_registered_data']['guest_customer']['total_cost'] += $order_cost;
                        $categorized_data['customer_metrics']['guest_vs_registered_data']['guest_customer']['total_profit'] += $order_profit;
                        $categorized_data['customer_metrics']['guest_vs_registered_data']['guest_customer']['total_order_count']++;

                        // Daily Data
                        if ( isset($data_by_date['customer_metrics']['guest_customer_orders_by_date'][$date_range_key]) ) $data_by_date['customer_metrics']['guest_customer_orders_by_date'][$date_range_key]++;

                    }

                    $customers_data_table_count = is_array($data_table['customer_metrics']) ? count($data_table['customer_metrics']) : 0;  
                    if ( $customers_data_table_count < $customers_data_table_limit ) {
                    
                        // Customer Data -> Setup Default Container
                        if ( ! isset($data_table['customer_metrics'][$billing_email]) ) {
                            $data_table['customer_metrics'][$billing_email] = array(
                                'user_id'               => $user_id,
                                'billing_email'         => $billing_email,
                                'billing_phone'         => $billing_phone,
                                'billing_first_name'    => $billing_first_name,
                                'billing_last_name'     => $billing_last_name,
                                'billing_country'       => $billing_country,
                                'billing_state'         => $billing_state,
                                'billing_postcode' 		=> $billing_postcode,
                                'billing_company' 		=> $billing_company,
                                'is_registered_user'    => $is_registered_user,
                                'total_revenue'         => 0,
                                'total_cost'            => 0,
                                'total_profit'          => 0,
                                'total_order_count'     => 0,
                                'margin_percentage'     => 0,
                                'average_order_value'   => 0,
                                'percent_of_revenue'    => 0,
                                'refund_count'          => 0,
                                'refund_value'          => 0,
                                'refund_rate'           => 0
                            );
                        }

                        // Make Calculations
                        $data_table['customer_metrics'][$billing_email]['total_revenue'] += $order_revenue;
                        $data_table['customer_metrics'][$billing_email]['total_cost'] += $order_cost;
                        $data_table['customer_metrics'][$billing_email]['total_profit'] += $order_profit;
                        $data_table['customer_metrics'][$billing_email]['total_order_count'] ++;
                        $data_table['customer_metrics'][$billing_email]['refund_count'] += ($order_data['total_refund_amount'] > 0) ? 1 : 0;
                        $data_table['customer_metrics'][$billing_email]['refund_value'] += $order_data['total_refund_amount'];

                    }

                }

                /**
                 * 
                 *  Additional Data for Coupons Report
                 * 
                 **/
                if ( isset( $additional_data['coupons'] ) && $additional_data['coupons'] ) {

                    // Coupon has been used
                    if ( is_array($order_data['coupons_used']) && ! empty($order_data['coupons_used']) ) {

                        $totals['coupon_metrics']['orders_with_coupons']++;
                        $totals['coupon_metrics']['total_revenue_with_coupons']  += $order_revenue;
                        $totals['coupon_metrics']['total_cost_with_coupons']     += $order_cost;
                        $totals['coupon_metrics']['total_profit_with_coupons']   += $order_profit;
                        $categorized_data['coupon_metrics']['order_ids'][] = $order_id;

                        // Categorized Data
                        $categorized_data['coupon_metrics']['orders_with_and_without_coupons']['orders_with_coupons']['total_revenue'] += $order_revenue;
                        $categorized_data['coupon_metrics']['orders_with_and_without_coupons']['orders_with_coupons']['total_cost'] += $order_cost;
                        $categorized_data['coupon_metrics']['orders_with_and_without_coupons']['orders_with_coupons']['total_profit'] += $order_profit;
                        $categorized_data['coupon_metrics']['orders_with_and_without_coupons']['orders_with_coupons']['total_order_count']++;

                        // Orders With Coupon By Date
                        if ( isset($data_by_date['coupon_metrics']['orders_with_coupon_by_date'][$date_range_key]) ) $data_by_date['coupon_metrics']['orders_with_coupon_by_date'][$date_range_key]++;

                        // Loop through order coupons
                        foreach( $order_data['coupons_used'] as $coupon_item_id => $coupon_data ) {

                            // Code
                            $coupon_code = $coupon_data['coupon_code'];

                            // Discount Amount By Date
                            if ( isset($data_by_date['coupon_metrics']['coupon_discount_amount_by_date'][$date_range_key]) ) $data_by_date['coupon_metrics']['coupon_discount_amount_by_date'][$date_range_key] += (float) $coupon_data['discount_amount'];

                            // Totals
                            $totals['coupon_metrics']['total_coupons_used']++;
                            $totals['coupon_metrics']['total_coupon_quantity_used']      += (int) $coupon_data['quantity_applied'];
                            $totals['coupon_metrics']['total_discount_amount']           += (float) $coupon_data['discount_amount'];
                            $totals['coupon_metrics']['total_discount_amount_tax']       += (float) $coupon_data['discount_amount_tax_only'];
                            $totals['coupon_metrics']['total_discount_amount_ex_tax']    += (float) $coupon_data['discount_amount_ex_tax'];

                            // Coupon not set yet
                            if ( ! isset($data_table['coupon_metrics'][$coupon_code]) ) {
                                $data_table['coupon_metrics'][$coupon_code] = array(

                                    'coupon_code'                   => $coupon_code,
                                    'coupon_name'                   => $coupon_data['coupon_name'],
                                    'discount_amount_ex_tax'        => 0,
                                    'discount_amount_tax_only'      => 0,
                                    'discount_amount'               => 0,
                                    'total_orders_applied'          => 0,
                                    'percent_of_orders_applied'     => 0, // Calculate Later
                                    'percent_of_orders_where_coupon_used' => 0, // Calculate Later
                                    'total_quantity_applied'        => 0,
                                    'total_customers_applied'       => 0, // Calculate later
                                    'total_revenue'                 => 0,
                                    'total_cost'                    => 0,
                                    'total_profit'                  => 0,
                                    'average_margin'                => 0, // Calculate Later
                                    'customers_by_email_address'    => array(), // Unique Later
                                    'order_ids'                     => array(), // Unique Later
                                    'coupon_id'                     => 'Unknown',
                                    'discount_type'                 => 'Unknown',
                                    'discount_type_amount'          => 'Unknown',
                                    'description'                   => 'Unknown',
                                    'total_usage_count'             => 'Unknown'

                                );

                            }

                            // Calculations
                            $data_table['coupon_metrics'][$coupon_code]['total_orders_applied']++;
                            $data_table['coupon_metrics'][$coupon_code]['discount_amount_ex_tax']     += (float) $coupon_data['discount_amount_ex_tax'];
                            $data_table['coupon_metrics'][$coupon_code]['discount_amount_tax_only']   += (float) $coupon_data['discount_amount_tax_only'];
                            $data_table['coupon_metrics'][$coupon_code]['discount_amount']            += (float) $coupon_data['discount_amount'];
                            $data_table['coupon_metrics'][$coupon_code]['total_quantity_applied']     += (int) $coupon_data['quantity_applied'];
                            $data_table['coupon_metrics'][$coupon_code]['total_revenue']              += $order_revenue;
                            $data_table['coupon_metrics'][$coupon_code]['total_cost']                 += $order_cost;
                            $data_table['coupon_metrics'][$coupon_code]['total_profit']               += $order_profit;

                            // Push data into array
                            $billing_email = ( ! empty( $order_data['billing_email'] ) ) ?  $order_data['billing_email'] : 'Unknown';
                            $data_table['coupon_metrics'][$coupon_code]['customers_by_email_address'][] = $billing_email;
                            $data_table['coupon_metrics'][$coupon_code]['order_ids'][] = $order_id;
                            
                        }

                    } else {

                        // Categorized Data
                        $categorized_data['coupon_metrics']['orders_with_and_without_coupons']['orders_without_coupons']['total_revenue'] += $order_revenue;
                        $categorized_data['coupon_metrics']['orders_with_and_without_coupons']['orders_without_coupons']['total_cost'] += $order_cost;
                        $categorized_data['coupon_metrics']['orders_with_and_without_coupons']['orders_without_coupons']['total_profit'] += $order_profit;
                        $categorized_data['coupon_metrics']['orders_with_and_without_coupons']['orders_without_coupons']['total_order_count']++;

                    }

                }

                /**
                 * 
                 *  Handle Product Specific Information
                 * 
                 **/
                if ( isset($order_data['product_data'] ) && is_array($order_data['product_data']) ) {

                    // Loop through each product in the order payload, product_id is variation id if set
                    foreach( $order_data['product_data'] as $product_id => $product_data ) {
                        
                        // Required for all reports
                        $unique_sku_array[] = $product_data['product_id'];
                        $total_product_line_items_sold++;

                        /**
                         * 
                         *  Additional Product Data
                         * 
                         **/
                        if ( isset( $additional_data['products'] ) && $additional_data['products'] ) {

                            // Get additional Product Data
                            $product_data_store = $this->get_product_data_cache( $product_id );

                            // Load up the product array if it hasn't been used yet
                            if ( ! isset($product_item_data[$product_id]) ) {

                                $product_item_data[$product_id] = array(

                                    'product_display'                       => $this->get_product_html_display_badge( $product_id ), // Html representation of product
                                    'product_id'                            => $product_id,
                                    'parent_id'                             => $product_data_store['parent_id'],
                                    'product_image'                         => '<img src="'.$product_data_store['product_image'].'" class="wpd-product-thumbnail">',
                                    'product_view_link'                     => $product_data_store['product_link'],
                                    'product_name'                          => $product_data_store['product_name'],
                                    'product_sku'                           => $product_data_store['product_sku'],
                                    'product_type'                          => $product_data_store['product_type'],
                                    'product_rrp'                           => $product_data_store['product_rrp'],
                                    'product_cost_price'                    => $product_data_store['product_cost_price'],
                                    'total_product_revenue_value_rrp'       => 0,
                                    'total_product_revenue'                 => 0,
                                    'total_product_revenue_excluding_tax'   => 0,
                                    'total_quantity_sold'                   => 0,
                                    'total_times_sold'                      => 0,
                                    'total_product_cost'                    => 0,
                                    'total_product_profit'                  => 0,
                                    'total_product_coupons_applied'         => 0,
                                    'total_product_discount_amount'         => 0,
                                    'total_quantity_refunded'               => 0,
                                    'total_times_refunded'                  => 0,
                                    'total_refund_amount'                   => 0,
                                    'refund_rate'                           => 0,
                                    'purchase_rate'                         => 0,
                                    'average_margin'                        => 0,
                                    'average_margin_sum'                    => 0,
                                    'average_product_discount_percent'      => 0,
                                    'average_product_discount_percent_sum'  => 0,
                                    'average_sell_price'                    => 0,
                                    'average_sell_price_sum'                => 0,
                                    'product_category'                      => ( $product_data_store['product_category'] ) ? $product_data_store['product_category'] : array(),
                                    'product_tags'                          => ( $product_data_store['product_tags'] ) ? $product_data_store['product_tags'] : array()

                                );

                            }

                            // Any vars used for calculations
                            $line_item_refund_boolean   = (is_numeric( $product_data['qty_refunded']) && $product_data['qty_refunded'] > 0) ? 1 : 0;
                            $product_profit_at_rrp      = $product_data['product_revenue_at_rrp'] - $product_data['total_cost_of_goods'];

                            // Make Product Calculations
                            $product_item_data[$product_id]['total_times_sold']++;
                            $product_item_data[$product_id]['total_times_refunded']                  += $line_item_refund_boolean;
                            $product_item_data[$product_id]['total_product_revenue_value_rrp']       += $product_data['product_revenue_at_rrp'];
                            $product_item_data[$product_id]['total_product_revenue']                 += $product_data['product_revenue'];
                            $product_item_data[$product_id]['total_quantity_sold']                   += $product_data['qty_sold'];
                            $product_item_data[$product_id]['total_product_cost']                    += $product_data['total_cost_of_goods'];
                            $product_item_data[$product_id]['total_product_profit']                  += $product_data['total_profit'];
                            $product_item_data[$product_id]['total_product_coupons_applied']         += $product_data['coupon_discount_amount'];
                            $product_item_data[$product_id]['total_product_discount_amount']         += $product_data['product_discount_amount'];
                            $product_item_data[$product_id]['total_quantity_refunded']               += $product_data['qty_refunded'];
                            $product_item_data[$product_id]['total_refund_amount']                   += $product_data['amount_refunded'];
                            $product_item_data[$product_id]['average_margin_sum']                    += $product_data['product_margin'];
                            $product_item_data[$product_id]['average_product_discount_percent_sum']  += $product_data['product_discount_percentage'];
                            $product_item_data[$product_id]['average_sell_price_sum']                += $product_data['product_revenue_per_unit'];
                            $product_item_data[$product_id]['total_product_revenue_excluding_tax']   += $product_data['product_revenue_excluding_tax'];

                            // Totals
                            $total_product_profit_at_rrp                += $product_profit_at_rrp;
                            $total_product_profit                       += $product_data['total_profit'];
                            $total_product_refund_amount                += $product_data['amount_refunded'];
                            $total_line_items_refunded                  += $line_item_refund_boolean;
                            
                            // Highest Count
                            if ( $product_data['qty_sold'] > $largest_quantity_sold_per_order ) $largest_quantity_sold_per_order = $product_data['qty_sold'];

                            // Product Type Defaults
                            if ( ! isset($product_type_data[$product_data_store['product_type']]) ) {

                                $product_type_data[$product_data_store['product_type']] = array(

                                    'total_revenue'       => 0,
                                    'total_profit'        => 0,
                                    'total_quantity_sold'  => 0,
                                    'unique_products_sold' => 0

                                );

                            }

                            // Product Type Calculations
                            $product_type_data[$product_data_store['product_type']]['unique_products_sold']++;
                            $product_type_data[$product_data_store['product_type']]['total_revenue']      += $product_data['product_revenue'];
                            $product_type_data[$product_data_store['product_type']]['total_profit']       += $product_data['total_profit'];
                            $product_type_data[$product_data_store['product_type']]['total_quantity_sold']     += $product_data['qty_sold'];

                            // Product Category Calculations
                            if ( is_array( $product_data_store['product_category'] ) && ! empty($product_data_store['product_category']) ) {

                                // Loop through product categories for this product
                                foreach( $product_data_store['product_category'] as $product_category_object ) {

                                    // Safety Check
                                    if ( ! is_a( $product_category_object, 'WP_Term' ) ) continue;

                                    // Product Category Defaults
                                    if ( ! isset($product_cat_data[$product_category_object->name]) ) {

                                        $product_cat_data[$product_category_object->name] = array(
                                            'total_revenue'         => 0,
                                            'total_profit'          => 0,
                                            'total_quantity_sold'   => 0,
                                            'unique_products_sold' => 0
                                        );

                                    }

                                    // Product Category Calculations
                                    $product_cat_data[$product_category_object->name]['unique_products_sold']++;
                                    $product_cat_data[$product_category_object->name]['total_revenue'] 	    += $product_data['product_revenue'];
                                    $product_cat_data[$product_category_object->name]['total_profit'] 			+= $product_data['total_profit'];
                                    $product_cat_data[$product_category_object->name]['total_quantity_sold'] 		+= $product_data['qty_sold'];

                                }

                            }

                            // Product Tag Calculations
                            if ( is_array( $product_data_store['product_tags'] ) && ! empty($product_data_store['product_tags']) ) {

                                // Loop through product tags for this product
                                foreach( $product_data_store['product_tags'] as $product_tag_object ) {

                                    // Safety Check
                                    if ( ! is_a( $product_tag_object, 'WP_Term' ) ) continue;

                                    // Product tags Defaults
                                    if ( ! isset($product_tag_data[$product_tag_object->name]) ) {

                                        $product_tag_data[$product_tag_object->name] = array(
                                            'total_revenue'       => 0,
                                            'total_profit'        => 0,
                                            'total_quantity_sold'      => 0,
                                            'unique_products_sold' => 0,
                                        );

                                    }

                                    // Product Tag Calculations
                                    $product_tag_data[$product_tag_object->name]['unique_products_sold']++;
                                    $product_tag_data[$product_tag_object->name]['total_revenue'] 			+= $product_data['product_revenue'];
                                    $product_tag_data[$product_tag_object->name]['total_profit'] 			+= $product_data['total_profit'];
                                    $product_tag_data[$product_tag_object->name]['total_quantity_sold'] 		+= $product_data['qty_sold'];

                                }

                            }

                        } // End Additional Product Data

                    } // End Product Line Item Loop

                } // End Product Data Availability Check

                // Inside the order loop, after processing the order data:
                if (isset($order_data['tax_data']) && is_array($order_data['tax_data'])) {
                    foreach ($order_data['tax_data'] as $rate_id => $tax_rate) {

                        // Initialize this tax rate in summaries if not exists for data table
                        if (!isset($data_table['tax_metrics'][$rate_id])) {
                            $data_table['tax_metrics'][$rate_id] = array(
                                'name' => $tax_rate['name'],
                                'rate' => $tax_rate['rate'],
                                'total_amount' => 0,
                                'order_count' => 0,
                                'average_per_order' => 0,
                                'percent_of_total_tax' => 0
                            );
                        }

                        // Different structure for our categorized data
                        if ( ! isset($categorized_data['tax_metrics']['tax_rate_summaries'][$tax_rate['name']]) ) {
                            $categorized_data['tax_metrics']['tax_rate_summaries'][$tax_rate['name']] = array(
                                'total_amount' => 0,
                                'order_count' => 0,
                                'average_per_order' => 0,
                                'percent_of_total_tax' => 0
                            );
                        }

                        // Remove the no data available container
                        if ( isset($data_by_date['tax_metrics']['tax_rates_collected_by_date']['no_data_available']) ) $data_by_date['tax_metrics']['tax_rates_collected_by_date'] = array();

                        // Setup multi-dimensional tax rate data
                        if ( ! isset($data_by_date['tax_metrics']['tax_rates_collected_by_date'][$tax_rate['name']]) ) {
                            $data_by_date['tax_metrics']['tax_rates_collected_by_date'][$tax_rate['name']] = $this->get_data_by_date_range_container();
                        }
                        $data_by_date['tax_metrics']['tax_rates_collected_by_date'][$tax_rate['name']][$date_range_key] += $tax_rate['amount'];

                        // Update the summaries
                        $categorized_data['tax_metrics']['tax_rate_summaries'][$tax_rate['name']]['total_amount'] += $tax_rate['amount'];
                        $categorized_data['tax_metrics']['tax_rate_summaries'][$tax_rate['name']]['order_count']++;
                        $data_table['tax_metrics'][$rate_id]['total_amount'] += $tax_rate['amount'];
                        $data_table['tax_metrics'][$rate_id]['order_count']++;
                        $totals['order_metrics']['total_tax_collected'] += $tax_rate['amount'];
                    }
                }

            } // End Order Loop
            
            // Delete the calculation cache
            wpd_delete_order_calculations_in_object_cache( $current_order_ids_batch );

            // Force garbage collection
            if ( function_exists('gc_collect_cycles') ) {
                gc_collect_cycles();
            }

            // Move to next batch
            $offset++;
            
        }

        // Any Figures we need for easy calculation
        $total_skus_sold = (is_array($unique_sku_array)) ? count( array_unique( $unique_sku_array ) ) : 0;

        // Additional Product Data Calculations
        if ( isset( $additional_data['products'] ) && $additional_data['products'] ) {

            $average_qty_sold_per_day                   = wpd_divide( $total_qty_sold, $n_days_period );
            $average_products_sold_per_day              = wpd_divide( $total_product_line_items_sold, $n_days_period );
            $average_skus_sold_per_day                  = wpd_divide( $total_skus_sold, $n_days_period );
            $average_profit_per_product                 = wpd_divide( $total_product_profit, $total_product_line_items_sold );
            $average_product_margin                     = wpd_calculate_margin( $total_product_profit, $total_product_revenue_ex_tax );
            $average_product_margin_at_rrp              = wpd_calculate_margin( $total_product_profit_at_rrp, $total_product_revenue_at_rrp );

            foreach( $product_item_data as $product_id => $product_data ) {
    
                // Calculations
                $product_item_data[$product_id]['purchase_rate']                    = wpd_divide( $product_data['total_times_sold'], $total_order_count ) * 100;
                $product_item_data[$product_id]['refund_rate']                      = wpd_divide( $product_data['total_times_refunded'], $product_data['total_times_sold'] ) * 100;
                $product_item_data[$product_id]['average_margin']                   = wpd_divide( $product_data['average_margin_sum'], $product_data['total_times_sold'] );
                $product_item_data[$product_id]['average_product_discount_percent'] = wpd_divide( $product_data['average_product_discount_percent_sum'], $product_data['total_times_sold'] );
                $product_item_data[$product_id]['average_sell_price']               = wpd_divide( $product_data['average_sell_price_sum'], $product_data['total_times_sold'] );
    
                // Get rid of trash
                unset( $product_item_data[$product_id]['average_margin_sum'] );
                unset( $product_item_data[$product_id]['average_product_discount_percent_sum'] );
                unset( $product_item_data[$product_id]['average_sell_price_sum'] );
    
            }

        }

        // Additional Acquisitions Calculations
        if ( isset( $additional_data['acquisitions'] ) && $additional_data['acquisitions'] ) {

            // Traffic Type
            foreach( $categorized_data['order_metrics']['acquisition_traffic_type'] as $data_key => $data ) {

                $categorized_data['order_metrics']['acquisition_traffic_type'][$data_key]['margin_percentage'] = wpd_calculate_margin( $data['total_profit'], $data['total_revenue'] );
                $categorized_data['order_metrics']['acquisition_traffic_type'][$data_key]['average_order_value'] = wpd_divide( $data['total_revenue'], $data['total_order_count'], 2 );
                $categorized_data['order_metrics']['acquisition_traffic_type'][$data_key]['percent_of_revenue'] = wpd_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );

            }

            // Query Parameter Keys
            foreach( $categorized_data['order_metrics']['acquisition_query_parameter_keys'] as $data_key => $data ) {

                $categorized_data['order_metrics']['acquisition_query_parameter_keys'][$data_key]['margin_percentage'] = wpd_calculate_margin( $data['total_profit'], $data['total_revenue'] );
                $categorized_data['order_metrics']['acquisition_query_parameter_keys'][$data_key]['average_order_value'] = wpd_divide( $data['total_revenue'], $data['total_order_count'], 2 );
                $categorized_data['order_metrics']['acquisition_query_parameter_keys'][$data_key]['percent_of_revenue'] = wpd_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );

            }

            // Query Parameter Values
            foreach( $categorized_data['order_metrics']['acquisition_query_parameter_values'] as $data_key => $data ) {

                $categorized_data['order_metrics']['acquisition_query_parameter_values'][$data_key]['margin_percentage'] = wpd_calculate_margin( $data['total_profit'], $data['total_revenue'] );
                $categorized_data['order_metrics']['acquisition_query_parameter_values'][$data_key]['average_order_value'] = wpd_divide( $data['total_revenue'], $data['total_order_count'], 2 );
                $categorized_data['order_metrics']['acquisition_query_parameter_values'][$data_key]['percent_of_revenue'] = wpd_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );

            }

            // Landing Page
            foreach( $categorized_data['order_metrics']['acquisition_landing_page'] as $data_key => $data ) {

                $categorized_data['order_metrics']['acquisition_landing_page'][$data_key]['margin_percentage'] = wpd_calculate_margin( $data['total_profit'], $data['total_revenue'] );
                $categorized_data['order_metrics']['acquisition_landing_page'][$data_key]['average_order_value'] = wpd_divide( $data['total_revenue'], $data['total_order_count'], 2 );
                $categorized_data['order_metrics']['acquisition_landing_page'][$data_key]['percent_of_revenue'] = wpd_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );

            }

            // Referral Source
            foreach( $categorized_data['order_metrics']['acquisition_referral_source'] as $data_key => $data ) {

                $categorized_data['order_metrics']['acquisition_referral_source'][$data_key]['margin_percentage'] = wpd_calculate_margin( $data['total_profit'], $data['total_revenue'] );
                $categorized_data['order_metrics']['acquisition_referral_source'][$data_key]['average_order_value'] = wpd_divide( $data['total_revenue'], $data['total_order_count'], 2 );
                $categorized_data['order_metrics']['acquisition_referral_source'][$data_key]['percent_of_revenue'] = wpd_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );

            }

            // Campaign Name
            foreach( $categorized_data['order_metrics']['acquisition_campaign_name'] as $data_key => $data ) {

                $categorized_data['order_metrics']['acquisition_campaign_name'][$data_key]['margin_percentage'] = wpd_calculate_margin( $data['total_profit'], $data['total_revenue'] );
                $categorized_data['order_metrics']['acquisition_campaign_name'][$data_key]['average_order_value'] = wpd_divide( $data['total_revenue'], $data['total_order_count'], 2 );
                $categorized_data['order_metrics']['acquisition_campaign_name'][$data_key]['percent_of_revenue'] = wpd_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );

            }

        }

        // Coupons
        if ( isset($additional_data['coupons']) && $additional_data['coupons'] ) {

            foreach( $categorized_data['coupon_metrics']['orders_with_and_without_coupons'] as $data_key => $data ) {

                $categorized_data['coupon_metrics']['orders_with_and_without_coupons'][$data_key]['margin_percentage'] = wpd_calculate_margin( $data['total_profit'], $data['total_revenue'] );
                $categorized_data['coupon_metrics']['orders_with_and_without_coupons'][$data_key]['average_order_value'] = wpd_divide( $data['total_revenue'], $data['total_order_count'], 2 );
                $categorized_data['coupon_metrics']['orders_with_and_without_coupons'][$data_key]['percent_of_revenue'] = wpd_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );
    
            }

        }

        // Additional Customers Calculations
        if ( isset( $additional_data['customers'] ) && $additional_data['customers'] ) {

            // New vs Returning
            foreach( $categorized_data['customer_metrics']['new_vs_returning_data'] as $data_key => $data ) {

                $categorized_data['customer_metrics']['new_vs_returning_data'][$data_key]['distinct_count'] = count( array_unique( $categorized_data['customer_metrics']['new_vs_returning_data'][$data_key]['distinct_count'] ) );
                $categorized_data['customer_metrics']['new_vs_returning_data'][$data_key]['margin_percentage'] = wpd_calculate_margin( $data['total_profit'], $data['total_revenue'] );
                $categorized_data['customer_metrics']['new_vs_returning_data'][$data_key]['average_order_value'] = wpd_divide( $data['total_revenue'], $data['total_order_count'], 2 );
                $categorized_data['customer_metrics']['new_vs_returning_data'][$data_key]['percent_of_revenue'] = wpd_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );

            }

            // Guest vs Registered
            foreach( $categorized_data['customer_metrics']['guest_vs_registered_data'] as $data_key => $data ) {

                $categorized_data['customer_metrics']['guest_vs_registered_data'][$data_key]['distinct_count'] = count( array_unique( $categorized_data['customer_metrics']['guest_vs_registered_data'][$data_key]['distinct_count'] ) );
                $categorized_data['customer_metrics']['guest_vs_registered_data'][$data_key]['margin_percentage'] = wpd_calculate_margin( $data['total_profit'], $data['total_revenue'] );
                $categorized_data['customer_metrics']['guest_vs_registered_data'][$data_key]['average_order_value'] = wpd_divide( $data['total_revenue'], $data['total_order_count'], 2 );
                $categorized_data['customer_metrics']['guest_vs_registered_data'][$data_key]['percent_of_revenue'] = wpd_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );

            }

            // Device Browser
            foreach( $categorized_data['customer_metrics']['device_browser_data'] as $data_key => $data ) {

                $categorized_data['customer_metrics']['device_browser_data'][$data_key]['margin_percentage'] = wpd_calculate_margin( $data['total_profit'], $data['total_revenue'] );
                $categorized_data['customer_metrics']['device_browser_data'][$data_key]['average_order_value'] = wpd_divide( $data['total_revenue'], $data['total_order_count'], 2 );
                $categorized_data['customer_metrics']['device_browser_data'][$data_key]['percent_of_revenue'] = wpd_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );
                if ( isset( $categorized_data['customer_metrics']['device_browser_data'][$data_key]['distinct_count'] ) ) unset( $categorized_data['customer_metrics']['device_browser_data'][$data_key]['distinct_count'] );

            }

            // Device Type
            foreach( $categorized_data['customer_metrics']['device_type_data'] as $data_key => $data ) {

                $categorized_data['customer_metrics']['device_type_data'][$data_key]['margin_percentage'] = wpd_calculate_margin( $data['total_profit'], $data['total_revenue'] );
                $categorized_data['customer_metrics']['device_type_data'][$data_key]['average_order_value'] = wpd_divide( $data['total_revenue'], $data['total_order_count'], 2 );
                $categorized_data['customer_metrics']['device_type_data'][$data_key]['percent_of_revenue'] = wpd_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );
                if ( isset( $categorized_data['customer_metrics']['device_type_data'][$data_key]['distinct_count'] ) ) unset( $categorized_data['customer_metrics']['device_type_data'][$data_key]['distinct_count'] );

            }

            // Location Data
            foreach( $categorized_data['customer_metrics']['country_location_data'] as $country_code => &$data ) {

                // Country name
                $customer_country_count++;

                // Country level data
                $data['margin_percentage'] = wpd_calculate_margin( $data['total_profit'], $data['total_revenue'] );
                $data['average_order_value'] = wpd_divide( $data['total_revenue'], $data['total_order_count'], 2 );
                $data['percent_of_revenue'] = wpd_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );
                
                // Cleaning
                if ( isset($data['distinct_count']) ) unset( $data['distinct_count'] );

            }

            // Location Data - State
            foreach( $categorized_data['customer_metrics']['state_location_data'] as $state_code => &$data ) {

                $data['margin_percentage'] = wpd_calculate_margin( $data['total_profit'], $data['total_revenue'] );
                $data['average_order_value'] = wpd_divide( $data['total_revenue'], $data['total_order_count'], 2 );
                $data['percent_of_revenue'] = wpd_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );
                if ( isset($data['distinct_count']) ) unset( $data['distinct_count'] );

            }

            // Customer Calculations
            foreach( $data_table['customer_metrics'] as $data_key => $data ) {

                $data_table['customer_metrics'][$data_key]['margin_percentage']   = wpd_calculate_margin( $data['total_profit'], $data['total_revenue'] );
                $data_table['customer_metrics'][$data_key]['average_order_value'] = wpd_divide( $data['total_revenue'], $data['total_order_count'], 2 );
                $data_table['customer_metrics'][$data_key]['percent_of_revenue']  = wpd_calculate_percentage( $data['total_revenue'], $total_revenue, 2 );
                $data_table['customer_metrics'][$data_key]['refund_rate']         = wpd_calculate_percentage( $data['refund_count'], $data['total_order_count'], 2 );

                if ( $data['total_order_count'] > 1 ) $customer_count_purchase_more_than_once++;
                if ( $data['refund_count'] > 0 ) $customers_with_refund_count++;

            }

            // For safe calculations
            $unique_customer_count              = (int) (is_array($unique_counter['unique_customers_by_email'])) ? count( $unique_counter['unique_customers_by_email'] ) : 0;
            $registered_customer_count          = (int) $categorized_data['customer_metrics']['guest_vs_registered_data']['registered_customer']['distinct_count'];
            $guest_customer_count               = (int) $categorized_data['customer_metrics']['guest_vs_registered_data']['guest_customer']['distinct_count'];
            $new_customer_count                 = (int) $categorized_data['customer_metrics']['new_vs_returning_data']['new_customer']['distinct_count'];
            $returning_customer_count           = (int) $categorized_data['customer_metrics']['new_vs_returning_data']['returning_customer']['distinct_count'];
            $average_customer_value_revenue     = (float) wpd_divide( $total_revenue, $unique_customer_count, 2 );
            $average_customer_value_profit      = (float) wpd_divide( $total_profit, $unique_customer_count, 2 );
            $orders_per_customer                = (float) wpd_divide( $totals['order_metrics']['total_order_count'], $unique_customer_count, 2 );
            $refunds_per_customer               = (float) wpd_divide( $totals['refund_metrics']['total_order_count_with_refund'], $unique_customer_count, 4 );
            $customer_refund_rate               = (float) wpd_calculate_percentage( $customers_with_refund_count, $unique_customer_count, 2 );
            $products_purchased_per_customer    = (float) wpd_divide( $total_product_line_items_sold, $unique_customer_count, 2 );
            $quantity_purchased_per_customer    = (float) wpd_divide( $total_qty_sold, $unique_customer_count, 2 );

            // Calculate some totals based on organised data
            $totals['customer_metrics']['customer_count_by_email_address']           = $unique_customer_count;
            $totals['customer_metrics']['registered_customer_count']                 = $registered_customer_count;
            $totals['customer_metrics']['registered_customer_percentage']            = wpd_calculate_percentage( $registered_customer_count, $unique_customer_count );
            $totals['customer_metrics']['guest_customer_count']                      = $guest_customer_count;
            $totals['customer_metrics']['guest_customer_percentage']                 = wpd_calculate_percentage( $guest_customer_count, $unique_customer_count );
            $totals['customer_metrics']['new_customer_count']                        = $new_customer_count;
            $totals['customer_metrics']['new_customer_percentage']                   = wpd_calculate_percentage( $new_customer_count, $unique_customer_count );
            $totals['customer_metrics']['returning_customer_count']                  = $returning_customer_count;
            $totals['customer_metrics']['returning_customer_percentage']             = wpd_calculate_percentage( $returning_customer_count, $unique_customer_count );
            $totals['customer_metrics']['average_customer_value_revenue']            = $average_customer_value_revenue;
            $totals['customer_metrics']['average_customer_value_profit']             = $average_customer_value_profit;
            $totals['customer_metrics']['orders_per_customer']                       = $orders_per_customer;
            $totals['customer_metrics']['customer_count_purchased_more_than_once']   = $customer_count_purchase_more_than_once;
            $totals['customer_metrics']['customer_country_count']                    = $customer_country_count;
            $totals['customer_metrics']['customer_state_count']                      = $customer_state_count;
            $totals['customer_metrics']['customers_with_refund']                     = $customers_with_refund_count;
            $totals['customer_metrics']['refunds_per_customer']                      = $refunds_per_customer;
            $totals['customer_metrics']['customer_refund_rate']                      = $customer_refund_rate;
            $totals['customer_metrics']['products_purchased_per_customer']           = $products_purchased_per_customer;
            $totals['customer_metrics']['quantity_purchased_per_customer']           = $quantity_purchased_per_customer;

        }

        // Additional Acquisitions Calculations
        if ( isset( $additional_data['coupons'] ) && $additional_data['coupons'] ) {

            foreach( $data_table['coupon_metrics'] as $data_key => $data ) {

                // Get additional data from WC Coupon Object
                $coupon_object = new WC_Coupon( $data['coupon_code'] );

                // Add Additional Meta
                if ( is_a($coupon_object, 'WC_Coupon') ) {

                    // Capture Data
                    $coupon_id              = $coupon_object->get_id();
                    $discount_type          = $coupon_object->get_discount_type();
                    $discount_type_amount   = $coupon_object->get_amount();
                    $description            = $coupon_object->get_description();
                    $usage_count            = $coupon_object->get_usage_count();

                    // Load Data In
                    $data_table['coupon_metrics'][$data_key]['coupon_id']               = $coupon_id;
                    $data_table['coupon_metrics'][$data_key]['discount_type']           = $discount_type;
                    $data_table['coupon_metrics'][$data_key]['discount_type_amount']    = $discount_type_amount;
                    $data_table['coupon_metrics'][$data_key]['description']             = $description;
                    $data_table['coupon_metrics'][$data_key]['total_usage_count']       = $usage_count;

                }

                // Coupon specific data
                $data_table['coupon_metrics'][$data_key]['percent_of_orders_applied']               = wpd_calculate_percentage($data['total_orders_applied'], $totals['order_metrics']['total_order_count'], 2);
                $data_table['coupon_metrics'][$data_key]['percent_of_orders_where_coupon_used']     = wpd_calculate_percentage($data['total_orders_applied'], $totals['coupon_metrics']['orders_with_coupons'], 2);
                $data_table['coupon_metrics'][$data_key]['average_margin']                          = wpd_calculate_margin($data['total_profit'], $data['total_revenue']);
                $data_table['coupon_metrics'][$data_key]['customers_by_email_address']              = (is_array($data_table['coupon_metrics'][$data_key]['customers_by_email_address'])) ?  array_unique($data_table['coupon_metrics'][$data_key]['customers_by_email_address']) : array();
                $data_table['coupon_metrics'][$data_key]['total_customers_applied']                 = count($data_table['coupon_metrics'][$data_key]['customers_by_email_address']);
                $data_table['coupon_metrics'][$data_key]['customers_by_email_address']              = (is_array($data_table['coupon_metrics'][$data_key]['customers_by_email_address'])) ?  array_unique($data_table['coupon_metrics'][$data_key]['customers_by_email_address']) : array();
                
                // Coupon totals
                $totals['coupon_metrics']['unique_coupon_codes_used']++;

            }

            // More totals
            $totals['coupon_metrics']['average_margin_with_coupons']                             = wpd_calculate_margin( $totals['coupon_metrics']['total_profit_with_coupons'], $totals['coupon_metrics']['total_revenue_with_coupons'] );
            $totals['coupon_metrics']['revenue_percent_with_coupons']                            = wpd_calculate_percentage( $totals['coupon_metrics']['total_revenue_with_coupons'], $total_revenue, 2 );
            $totals['coupon_metrics']['profit_percent_with_coupons']                             = wpd_calculate_percentage( $totals['coupon_metrics']['total_profit_with_coupons'], $total_profit, 2 );
            $totals['coupon_metrics']['order_percent_with_coupons']                              = wpd_calculate_percentage( $totals['coupon_metrics']['orders_with_coupons'], $totals['order_metrics']['total_order_count'], 2 );
            $totals['coupon_metrics']['coupons_per_order']                                       = wpd_divide( $totals['coupon_metrics']['orders_with_coupons'], $totals['order_metrics']['total_order_count'], 4 );
            $totals['coupon_metrics']['average_coupon_discount_per_discounted_order']            = wpd_divide( $totals['coupon_metrics']['total_discount_amount'], $totals['coupon_metrics']['orders_with_coupons'], 2 );
            $totals['coupon_metrics']['average_coupon_discount_percent_per_discounted_order']    = wpd_calculate_percentage( $totals['coupon_metrics']['total_discount_amount'], $totals['coupon_metrics']['total_discount_amount'] + $totals['coupon_metrics']['total_revenue_with_coupons'], 2 );
            $totals['coupon_metrics']['orders_without_coupons']                                  = (int) $totals['order_metrics']['total_order_count'] - (int) $totals['coupon_metrics']['orders_with_coupons'];
            $totals['coupon_metrics']['percent_of_orders_with_coupons']                          = wpd_calculate_percentage( $totals['coupon_metrics']['orders_with_coupons'], $totals['order_metrics']['total_order_count'] );
            $totals['coupon_metrics']['percent_of_orders_without_coupons']                       = wpd_calculate_percentage( $totals['coupon_metrics']['orders_without_coupons'], $totals['order_metrics']['total_order_count'] );

        }

        // After the order loop, calculate percentages
        if ( is_array($payment_gateway_data) && ! empty($payment_gateway_data) ) {
            foreach ($payment_gateway_data as $gateway_id => &$data) {
                $data['percent_of_orders'] = wpd_calculate_percentage($data['order_count'], $total_order_count);
                $data['percent_of_revenue'] = wpd_calculate_percentage($data['revenue'], $total_revenue);
                $data['average_order_value'] = wpd_divide($data['revenue'], $data['order_count']);
                unset($data['distinct_count']);
            }
        }

        // After the order loop, calculate percentages chris
        if ( is_array($categorized_data['order_metrics']['order_status_data']) && ! empty($categorized_data['order_metrics']['order_status_data']) ) {
            foreach ($categorized_data['order_metrics']['order_status_data'] as $order_status => &$data) {
                $data['percent_of_orders'] = wpd_calculate_percentage($data['total_order_count'], $total_order_count);
                $data['percent_of_revenue'] = wpd_calculate_percentage($data['total_revenue'], $total_revenue);
                $data['average_order_value'] = wpd_divide($data['total_revenue'], $data['total_order_count']);
                $data['margin_percentage'] = wpd_calculate_margin( $data['total_profit'], $data['total_revenue'] );
                unset($data['distinct_count']);
            }
        }

        // Calculate order costs (custom product costs are included in this)
        $categorized_data['order_metrics']['order_cost_breakdown'] = array( 'cost_of_goods' => $total_product_cost_of_goods, 'shipping_cost' => $total_shipping_cost, 'payment_gateway_fees' => $total_payment_gateway_costs );
        if ( is_array($custom_order_cost_data) && ! empty($custom_order_cost_data) ) {
            foreach($custom_order_cost_data as $custom_order_cost_label => $custom_order_cost_value) {
                $categorized_data['order_metrics']['order_cost_breakdown'][$custom_order_cost_label] = $custom_order_cost_value;
            }
        }
        // if ( is_array($custom_product_cost_data) && ! empty($custom_product_cost_data) ) {
        //     foreach($custom_product_cost_data as $custom_product_cost_label => $custom_product_cost_value) {
        //         $categorized_data['order_metrics']['order_cost_breakdown'][$custom_product_cost_label] = $custom_product_cost_value;
        //     }
        // }


        /**
         * 
         *  Calculate Totals
         * 
         **/
        $totals['order_metrics']['total_order_count']                        = $total_order_count;
        $totals['total_records'] 					                            = $totals['order_metrics']['total_order_count']; // Do we need?
        $totals['order_metrics']['total_order_revenue_inc_tax_and_refunds']  = $total_order_revenue_inc_tax_and_refunds;
        $totals['order_metrics']['total_order_revenue'] 					    = $total_revenue;
        $totals['order_metrics']['total_order_revenue_ex_tax']               = $total_order_revenue_ex_tax;
        $totals['order_metrics']['total_order_tax']                          = $total_tax_collected;
        $totals['order_metrics']['total_order_cost'] 				        = $total_cost;
        $totals['order_metrics']['total_order_profit'] 					    = $total_profit;
        $totals['order_metrics']['total_freight_recovery'] 			        = $total_shipping_charged;
        $totals['order_metrics']['total_freight_cost'] 				        = $total_shipping_cost;
        $totals['product_metrics']['total_product_cost'] 				        = $total_product_cost; // Includes custom costs
        $totals['order_metrics']['total_product_cost_of_goods'] 				= $total_product_cost_of_goods;
        $totals['order_metrics']['total_payment_gateway_costs'] 		        = $total_payment_gateway_costs;
        $totals['order_metrics']['total_tax_collected'] 					    = $total_tax_collected;

        // Custom Costs
        $totals['order_metrics']['total_custom_order_costs']                 = $total_custom_order_costs;

        $categorized_data['order_metrics']['custom_order_cost_data']                   = $custom_order_cost_data;
        $totals['order_metrics']['total_custom_product_costs']               = $total_custom_product_costs;

        // Payment Gateway Data
        $categorized_data['order_metrics']['payment_gateway_data']                     = $payment_gateway_data;

        // Product Data 
        $totals['product_metrics']['total_product_revenue'] 			        = $total_product_revenue;
        $totals['product_metrics']['total_product_revenue_excluding_tax']       = $total_product_revenue_ex_tax;
        $totals['product_metrics']['total_qty_sold'] 					        = $total_qty_sold;
        $totals['product_metrics']['total_skus_sold'] 					        = $total_skus_sold;
        $totals['product_metrics']['total_product_line_items_sold']            = $total_product_line_items_sold;

        // Discount Data    
        $totals['product_metrics']['total_product_revenue_at_rrp'] 		      = $total_product_revenue_at_rrp;
        $totals['product_metrics']['total_product_discount_amount'] 		  = $total_product_discounts;
        $totals['product_metrics']['average_product_discount_percent']        = wpd_calculate_percentage( $total_product_discounts, $total_product_revenue_at_rrp );
        $totals['coupon_metrics']['total_coupon_discount_amount'] 	          = $total_coupon_discounts;
        $totals['order_metrics']['total_order_revenue_before_coupons'] 	      = $total_order_revenue_before_coupons;
        $totals['coupon_metrics']['average_coupon_discount_percent']          = wpd_calculate_percentage( $total_coupon_discounts, $total_order_revenue_before_coupons );
        $totals['order_metrics']['total_order_discount_amount']               = $total_order_discounts;
        $totals['order_metrics']['total_order_revenue_before_discounts']      = $total_order_revenue_before_discounts;
        $totals['order_metrics']['average_order_discount_percent']            = wpd_calculate_percentage( $total_order_discounts, $total_order_revenue_before_discounts );
        $totals['order_metrics']['orders_with_discount']                      = $orders_with_discount;
        $totals['order_metrics']['discounted_order_percent']                  = wpd_calculate_percentage( $orders_with_discount, $total_order_count );

        // Calculations 
        $totals['order_metrics']['largest_order_revenue'] 				      = $largest_order_revenue;
        $totals['order_metrics']['largest_order_cost'] 					      = $largest_order_cost;
        $totals['order_metrics']['largest_order_profit'] 				      = $largest_order_profit;
        $totals['order_metrics']['average_order_margin']					  = wpd_calculate_percentage( $total_profit, $total_order_revenue_ex_tax, 2 );
        $totals['order_metrics']['average_order_revenue'] 				      = wpd_divide( $total_revenue, $total_order_count, 2 );
        $totals['order_metrics']['average_order_cost']					      = wpd_divide( $total_cost, $total_order_count, 2 );
        $totals['order_metrics']['average_order_profit'] 				      = wpd_divide( $total_profit, $total_order_count, 2 );
        $totals['order_metrics']['average_line_items_per_order']              = wpd_divide( $total_product_line_items_sold, $total_order_count, 2 );
        $totals['order_metrics']['daily_average_order_count']                 = wpd_divide( $total_order_count, $n_days_period );
        $totals['order_metrics']['daily_average_order_revenue']               = wpd_divide( $total_revenue, $n_days_period );
        $totals['order_metrics']['daily_average_order_cost']                  = wpd_divide( $total_cost, $n_days_period );
        $totals['order_metrics']['daily_average_order_profit']                = wpd_divide( $total_profit, $n_days_period );
        $totals['order_metrics']['cost_percentage_of_revenue']                = wpd_calculate_percentage( $total_cost, $total_revenue );

        // Refund data  
        $totals['refund_metrics']['total_refund_amount'] 					  = $total_refunds;
        $totals['refund_metrics']['refund_percent_of_revenue']                = wpd_calculate_percentage( $total_refunds, $total_revenue );
        $totals['refund_metrics']['refund_rate_percentage']                   = wpd_calculate_percentage( $totals['refund_metrics']['total_order_count_with_refund'], $totals['order_metrics']['total_order_count'] );
        $totals['refund_metrics']['refunds_per_day']                          = wpd_divide( $totals['refund_metrics']['total_order_count_with_refund'], $n_days_period );
        $totals['refund_metrics']['total_skus_refunded']                      = count( $refunded_product_ids );

        // Additional Data  
        $categorized_data['order_metrics']['payment_gateway_order_count']      = $payment_gateway_array;

        // Additional Product Data
        $totals['product_metrics']['total_product_profit']                     = $total_product_profit;
        $totals['product_metrics']['total_product_profit_at_rrp']              = $total_product_profit_at_rrp;
        $totals['product_metrics']['average_profit_per_product']               = $average_profit_per_product;
        $totals['product_metrics']['average_product_margin']                   = $average_product_margin;
        $totals['product_metrics']['average_product_margin_at_rrp']            = $average_product_margin_at_rrp;
        $totals['product_metrics']['average_qty_sold_per_day']                 = $average_qty_sold_per_day;
        $totals['product_metrics']['average_products_sold_per_day']            = $average_products_sold_per_day;
        $totals['product_metrics']['average_skus_sold_per_day']                = $average_skus_sold_per_day;
        $totals['product_metrics']['total_product_refund_amount']              = $total_product_refund_amount;
        $totals['product_metrics']['largest_product_count_sold_per_order']     = $largest_product_count_sold_per_order;
        $totals['product_metrics']['largest_quantity_sold_per_order']          = $largest_quantity_sold_per_order;
        $totals['product_metrics']['total_line_items_refunded']                = $total_line_items_refunded;

        // Additional Product Report Data
        $data_table['product_metrics']                                          = $product_item_data;
        $categorized_data['product_metrics']['product_type_data']               = $product_type_data;
        $categorized_data['product_metrics']['product_cat_data']                = $product_cat_data;
        $categorized_data['product_metrics']['product_tag_data']                = $product_tag_data;

        // After the order loop, calculate averages and percentages
        if ($totals['order_metrics']['total_tax_collected'] > 0) {
            foreach ($data_table['tax_metrics'] as $rate_id => &$summary) {
                $summary['average_per_order'] = wpd_divide($summary['total_amount'], $summary['order_count']);
                $summary['percent_of_total_tax'] = wpd_calculate_percentage(
                    $summary['total_amount'],
                    $totals['order_metrics']['total_tax_collected']
                );
            }
            foreach($categorized_data['tax_metrics']['tax_rate_summaries'] as $rate_id => &$summary) {
                $summary['average_per_order'] = wpd_divide($summary['total_amount'], $summary['order_count']);
                $summary['percent_of_total_tax'] = wpd_calculate_percentage(
                    $summary['total_amount'],
                    $totals['order_metrics']['total_tax_collected']
                );
            }
            // Override our other calculation
            $totals['tax_metrics']['tax_as_percentage_of_revenue'] = wpd_calculate_percentage( $totals['order_metrics']['total_tax_collected'], $totals['tax_metrics']['total_revenue_where_tax_was_collected'] );

        }

        // Create no data found array
        foreach( $data_by_date as $data_key => $data_values ) {
            $data_by_date[$data_key] = $this->maybe_create_no_data_found_date_array( $data_by_date[$data_key] );
        }

        // Calculate execution time
        $execution_time = microtime(true) - $start_time + $this->get_data('orders', 'execution_time');

        // Configure return object
        $this->set_data( 'orders', array(
            'totals'            => $totals['order_metrics'],
            'categorized_data'  => $categorized_data['order_metrics'],
            'data_by_date'      => $data_by_date['order_metrics'],
            'data_table'        => array(
                'orders' => $data_table['order_metrics']
            ),
            'total_db_records'  => $total_db_records,
            'execution_time'    => $execution_time
        ));

        // Configure return object
        $this->set_data( 'customers', array(
            'totals'            => $totals['customer_metrics'],
            'categorized_data'  => $categorized_data['customer_metrics'],
            'data_by_date'      => $data_by_date['customer_metrics'],
            'data_table'        => array(
                'customers' => $data_table['customer_metrics']
            ),
            'total_db_records'  => $total_db_records
        ));

        // Configure return object
        $this->set_data( 'products', array(
            'totals'            => $totals['product_metrics'],
            'categorized_data'  => $categorized_data['product_metrics'],
            'data_by_date'      => $data_by_date['product_metrics'],
            'data_table'        => array(
                'products' => $data_table['product_metrics']
            ),
            'total_db_records'  => $total_db_records
        ));

        // Configure return object
        $this->set_data( 'coupons', array(
            'totals'            => $totals['coupon_metrics'],
            'categorized_data'  => $categorized_data['coupon_metrics'],
            'data_by_date'      => $data_by_date['coupon_metrics'],
            'data_table'        => array(
                'coupons' => $data_table['coupon_metrics']
            ),
            'total_db_records'  => $total_db_records
        ));

        // Configure return object
        $this->set_data( 'refunds', array(
            'totals'            => $totals['refund_metrics'],
            'categorized_data'  => $categorized_data['refund_metrics'],
            'data_by_date'      => $data_by_date['refund_metrics'],
            'data_table'        => array(
                'refunds' => $data_table['refund_metrics']
            ),
            'total_db_records'  => $total_db_records
        ));

        // Configure return object
        $this->set_data( 'taxes', array(
            'totals'            => $totals['tax_metrics'],
            'categorized_data'  => $categorized_data['tax_metrics'],
            'data_by_date'      => $data_by_date['tax_metrics'],
            'data_table'        => array(
                'taxes' => $data_table['tax_metrics']
            ),
            'total_db_records'  => $total_db_records
        ));


        // Return success result
        return $this->get_data();

    }

    /**
     * 
     *  Fetches Store Revenue & Expenses and organises it for use by get_data('store_profit')
     * 
     *  Available Filters: 
     * 
     *      date_from (will filter against _wpd_date_paid)
     *      date_to (will filter against _wpd_date_paid)
     *  
     **/
    public function fetch_store_profit_data() {
        
        // Start execution timer
        $start_time = microtime(true);

        // Setup default containers
        $totals = array(			
			'total_store_profit'                    => 0,
            'average_store_margin'                  => 0, // Bottomline Store Margin
            'daily_average_store_profit'            => 0,
            'expense_percentage_of_order_profit'    => 0, // wpd_calculate_percentage( $total_other_expenses, $total_order_profit)
        );
        $categorized_data = array(
            'profit_loss_statement_data' => array(
                'gross_revenue_including_sales_tax_and_refunds' => 0,
                'sales_tax' => 0,
                'refunds' => 0,
                'net_revenue' => 0,
                'cost_of_goods_sold' => 0,
                'shipping_expenses' => 0,
                'payment_gateway_costs' => 0,
                'custom_order_cost_data' => array(),
                'total_cost_of_sales' => 0,
                'gross_profit' => 0,
                'gross_profit_percentage' => 0.00,
                'operating_expense_breakdown' => array(),
                'total_operating_expenses' => 0,
                'net_profit_before_income_tax' => 0,
                'net_profit_percentage' => 0.00
            ),
        );
        $data_table = array();
        $total_db_records = 0;
        $data_by_date = array(
            'store_profit_by_date' => $this->get_data_by_date_range_container(),
        );

        // Setup default vars
        $n_days_period                  = $this->get_n_days_range();
        $expense_data                   = $this->get_data('expenses');
        $orders_data                    = $this->get_data('orders');

        // Call data if it hasn't been
        if ( empty($expense_data['totals']) ) {
            $this->fetch_expense_data();
            $expense_data = $this->get_data('expenses');
        }

        // Call data if it hasnt been
        if ( empty($orders_data['totals']) ) {
            $this->fetch_sales_data();
            $orders_data = $this->get_data('orders');
        }

        // Setup store profit daily data
        foreach( $data_by_date['store_profit_by_date'] as $date_key => $data_array ) {

            $order_profit_by_date_key = $orders_data['data_by_date']['profit_by_date'][$date_key] ?? 0;
            $store_expenses_by_date_key = $expense_data['data_by_date']['amount_paid_by_date'][$date_key] ?? 0;
            $store_profit_by_date = $order_profit_by_date_key - $store_expenses_by_date_key;
            $data_by_date['store_profit_by_date'][$date_key] = $store_profit_by_date;

        }

        // Totals
        $totals['total_store_profit']                   = $orders_data['totals']['total_order_profit'] - $expense_data['totals']['total_amount_paid'];
        $totals['average_store_margin']                 = wpd_calculate_percentage( $totals['total_store_profit'], $orders_data['totals']['total_order_revenue_ex_tax'] );
        $totals['daily_average_store_profit']           = wpd_divide( $totals['total_store_profit'], $n_days_period );
        $totals['expense_percentage_of_order_profit']   = wpd_calculate_percentage( $expense_data['totals']['total_amount_paid'], $orders_data['totals']['total_order_profit'] );

        // Setup parent expense array
        $parent_expense_array = array();
        if ( is_array($expense_data['categorized_data']['parent_expense_type_categories']) && ! empty($expense_data['categorized_data']['parent_expense_type_categories']) ) {
            foreach( $expense_data['categorized_data']['parent_expense_type_categories'] as $parent_expense_slug => $parent_expense ) {
                $parent_expense_array[wpd_clean_string($parent_expense_slug)] = $parent_expense['total_amount_paid'];
            }
        }

        // Setup custom order cost array
        $custom_order_cost_array = array();
        if ( is_array($orders_data['categorized_data']['custom_order_cost_data']) && ! empty($orders_data['categorized_data']['custom_order_cost_data']) ) {
            foreach( $orders_data['categorized_data']['custom_order_cost_data'] as $custom_order_cost_slug => $custom_order_cost ) {
                $custom_order_cost_array[wpd_clean_string($custom_order_cost_slug)] = $custom_order_cost;
            }
        }

        // Refunds
        $refunds_data = $this->get_data( 'refunds', 'totals' );

        // Setup P&L Statement
        $categorized_data['profit_loss_statement_data']['gross_revenue_including_sales_tax_and_refunds'] = $orders_data['totals']['total_order_revenue_inc_tax_and_refunds'];
        $categorized_data['profit_loss_statement_data']['sales_tax'] = $orders_data['totals']['total_order_tax'];
        $categorized_data['profit_loss_statement_data']['refunds'] = $refunds_data['total_refund_amount'];
        $categorized_data['profit_loss_statement_data']['net_revenue'] = $orders_data['totals']['total_order_revenue_ex_tax'];
        $categorized_data['profit_loss_statement_data']['cost_of_goods_sold'] = $orders_data['totals']['total_product_cost_of_goods'];
        $categorized_data['profit_loss_statement_data']['shipping_expenses'] = $orders_data['totals']['total_freight_cost'];
        $categorized_data['profit_loss_statement_data']['payment_gateway_costs'] = $orders_data['totals']['total_payment_gateway_costs'];
        $categorized_data['profit_loss_statement_data']['custom_order_cost_data'] = $custom_order_cost_array;
        $categorized_data['profit_loss_statement_data']['total_cost_of_sales'] = $orders_data['totals']['total_order_cost'];
        $categorized_data['profit_loss_statement_data']['gross_profit'] = $orders_data['totals']['total_order_profit'];
        $categorized_data['profit_loss_statement_data']['gross_profit_percentage'] = $orders_data['totals']['average_order_margin'];
        $categorized_data['profit_loss_statement_data']['operating_expense_breakdown'] = $parent_expense_array;
        $categorized_data['profit_loss_statement_data']['total_operating_expenses'] = $expense_data['totals']['total_amount_paid'];
        $categorized_data['profit_loss_statement_data']['net_profit_before_income_tax'] = $totals['total_store_profit'];
        $categorized_data['profit_loss_statement_data']['net_profit_percentage'] = $totals['average_store_margin'];

        // Log execution time
        $execution_time = microtime(true) - $start_time + $this->get_data('store_profit', 'execution_time');

        // Configure return object
        $store_profit_data = array(
            'totals' => $totals,
            'categorized_data' => $categorized_data,
            'data_by_date' => $data_by_date,
            'data_table' => array(
                'store_profit' => $data_table
            ),
            'total_db_records' => $total_db_records,
            'execution_time' => $execution_time
        );

        $data_by_date = $this->maybe_create_no_data_found_date_array( $data_by_date );

        // Store the data into the prop
        $this->set_data( 'store_profit', $store_profit_data );

        // Return Results
        return $store_profit_data;

    }

    /**
     * 
     *  Fetches Expense Data and organises it for use by get_data('expenses')
     * 
     *  Available Filters: 
     * 
     *      date_from (will filter against _wpd_date_paid)
     *      date_to (will filter against _wpd_date_paid)
     *      expense_type Expects an expense_type taxonomy slug
     *  
     **/
    public function fetch_expense_data() {
        // Start execution timer
        $start_time = microtime(true);

        // Setup default containers
        $totals = array(			
			'total_amount_paid'  		    => 0,
			'total_amount_unpaid'  		    => 0,
            'total_amount'                  => 0,
            'total_expense_count'           => 0,
            'total_unpaid_expense_count'    => 0,
            'total_paid_expense_count'      => 0,
            'total_standard_expense_count'  => 0,
            'total_recurring_expense_count' => 0,
            'total_unique_recurring_expense_count' => 0,
			'average_expenses_per_day' 	    => 0,
            'daily_average_expense_count'   => 0,
            'daily_average_expense_amount'  => 0,

        );
        $categorized_data = array(
            'supplier_expense_data'             => array(),
            'parent_expense_type_categories'    => array(),
            'child_expense_type_categories' 	=> array(),
            'expense_post_ids'                  => array()
        );
        $data_table = array();
        $total_db_records = 0;
        $data_by_date = array(
            'amount_paid_by_date' => $this->get_data_by_date_range_container(),
            'amount_unpaid_by_date' => $this->get_data_by_date_range_container(),
        );

        // Setup default vars
        $filter                         = $this->get_filter();
        $date_from                     = $this->get_date_from();
        $date_to                       = $this->get_date_to();
        $store_currency                 = $this->get_store_currency();
        $n_days_period                  = $this->get_n_days_range();
        $date_format                    = $this->get_filter( 'date_format_string' );
        $expense_post_ids               = array();
        $total_amount_paid              = 0;
        $total_amount_unpaid            = 0;
        $total_amount                   = 0;
        $total_expense_count            = 0;
        $total_unpaid_expense_count     = 0;
        $total_paid_expense_count       = 0;
        $total_standard_expense_count   = 0;
        $total_recurring_expense_count  = 0;
        $total_unique_recurring_expense_count = 0;
        $parent_expense_by_type         = array();
        $child_expense_by_type          = array();

        // Default array payload
        $expense_data_array = array(
            'parent_id'             => 0, // 0 If is a parent
            'parent_expense_label'  => '',
            'label'                 => '',
            'slug'                  => '',
            'id'                    => 0,
            'total_amount'          => 0, // Unpaid or Paid
            'total_amount_paid'     => 0,
            'total_amount_unpaid'   => 0,
            'total_expense_count'   => 0,
            'unique_expenses'       => array() // For storing the related expenses
        );

        /**
         *
         *	Setup filter
         *
         */

         if ( $this->get_data_filter( 'expenses', 'expense_category' ) && is_array( $this->get_data_filter( 'expenses', 'expense_category' ) ) ) {

            $tax_args = array(
                array (
                    'taxonomy' 	=> 'expense_category',
                    'field' 	=> 'term_id',
                    'terms' 	=> $this->get_data_filter( 'expenses', 'expense_category' ),
                    'operator' => 'IN',
                )
            );

        } else {

            $tax_args = array();

        }

		$standard_expense_args = array(

			'fields' 			=> 'ids',
		    'post_type' 		=> 'expense',
		    'post_status' 		=> 'publish',
		    'posts_per_page' 	=> -1,
		    'meta_query' 		=> array(
		        array(
		            'key' 		=> '_wpd_date_paid',
		            'value' 	=> array($date_from, $date_to),
		            'compare' 	=> 'BETWEEN',
		            'type' 		=> 'DATE'
		        )
		    ),
		   	'tax_query' 		=> $tax_args,
		    'orderby' 			=> 'meta_value',
		    'meta_key' 			=> '_wpd_date_paid',
		    'order' 			=> 'DESC',
		);
		$recurring_expense_args = array(

			'fields' 			=> 'ids',
		    'post_type' 		=> 'expense',
		    'post_status' 		=> 'publish',
		    'posts_per_page' 	=> -1,
		    'meta_query' 		=> array(
		        array(
		            'key' 		=> '_wpd_recurring_expense_enabled',
		            'value' 	=> array( 1 ),
		            'compare' 	=> 'IN',
		        )
		    ),
		   	'tax_query' 		=> $tax_args,
		    'orderby' 			=> 'meta_value',
		    'meta_key' 			=> '_wpd_recurring_expense_beginning_date',
		    'order' 			=> 'DESC',
		);

		$standard_expense_ids 	= new WP_Query( $standard_expense_args );
		$recurring_expense_ids 	= new WP_Query( $recurring_expense_args );
		$expense_post_ids 		= array_unique( array_merge( $standard_expense_ids->posts, $recurring_expense_ids->posts ) );
		if ( empty($expense_post_ids) ) $expense_post_ids = array( 0 );

        if ( WPD_AI_PRO ) {
            $expense_posts = new WP_Query( 
                array(
                    'post__in' => $expense_post_ids,
                    'post_type' => 'expense',
                    'posts_per_page' 	=> -1,
                    'fields' => 'ids',
                ) 
            );
            $expense_post_ids = (array) $expense_posts->posts;
        } else {
            $expense_post_ids = array();
        }


        /**
         * 
         *  Loop through the expense Post IDs
         * 
         **/
    	foreach ( $expense_post_ids as $expense_post_id ) {

    		/**
			 *
			 *	Check memory usage
			 *	If memory use is higher than 90%, dont try and find anymore
			 *
			 */
			if ( wpd_is_memory_usage_greater_than(90) ) {

				$memory_limit = ini_get('memory_limit');
				$this->set_error(
					sprintf(
						/* translators: %s: PHP memory limit */
						__( 'You\'ve exhausted your memory usage. Increase your PHP memory limit or reduce the date range. Your current PHP memory limit is %s.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
						$memory_limit
					)
				);

				break; // Break the entire process if were hitting the memory limits

			}

            /**
             * 
             *  Collect Data
             * 
             **/
	    	$post_id 					= $expense_post_id;
            $is_expense_paid 			= ( get_post_meta( $post_id, '_wpd_paid', true ) === '' ) ? 1 : (int) get_post_meta( $post_id, '_wpd_paid', true ); // Default to true
	    	$wpd_amount_paid 			= (float) get_post_meta( $post_id, '_wpd_amount_paid', true );
			$wpd_amount_paid_currency 	= (string) get_post_meta( $post_id, '_wpd_amount_paid_currency', true );
			$wpd_date_paid 				= (string) get_post_meta( $post_id, '_wpd_date_paid', true );
			$wpd_expense_reference 		= (string) get_post_meta( $post_id, '_wpd_expense_reference', true );
            $recurring_expense_enabled  = get_post_meta( $post_id, '_wpd_recurring_expense_enabled', true );
			$expense_type 				= get_the_terms( $post_id, 'expense_category' );
			$suppliers 					= get_the_terms( $post_id, 'suppliers' );
			$converted_value 			= 0;
            $expense_type_names_string  = array();

            // Apply filters
            if ( $this->get_data_filter( 'expenses', 'paid_unpaid' ) && is_array( $this->get_data_filter( 'expenses', 'paid_unpaid' ) ) ) {

                $include_paid_expenses = in_array( 'paid', $this->get_data_filter( 'expenses', 'paid_unpaid' ) );
                $include_unpaid_expenses = in_array( 'unpaid', $this->get_data_filter( 'expenses', 'paid_unpaid' ) );

                // Skip this expense if it doesn't match the paid/unpaid filter
                if ( $is_expense_paid && !$include_paid_expenses ) {
                    continue;
                }
                if ( !$is_expense_paid && !$include_unpaid_expenses ) {
                    continue;
                }

            }

            // Apply filters
            if ( $this->get_data_filter( 'expenses', 'recurring_one_time' ) && is_array( $this->get_data_filter( 'expenses', 'recurring_one_time' ) ) ) {

                $include_recurring_expenses = in_array( 'recurring', $this->get_data_filter( 'expenses', 'recurring_one_time' ) );
                $include_one_time_expenses = in_array( 'one_time', $this->get_data_filter( 'expenses', 'recurring_one_time' ) );

                // Skip this expense if it doesn't match the recurring/one-time filter
                if ( $recurring_expense_enabled && !$include_recurring_expenses ) {
                    continue;
                }
                if ( !$recurring_expense_enabled && !$include_one_time_expenses ) {
                    continue;
                }

            }

            // All meta
            // $wpd_paid 						= get_post_meta( $post->ID, '_wpd_paid', true );
            // $wpd_amount_paid 				= get_post_meta( $post->ID, '_wpd_amount_paid', true );
            // $wpd_tax_amount 				    = get_post_meta( $post->ID, '_wpd_tax_amount', true );
            // $wpd_amount_paid_currency 		= get_post_meta( $post->ID, '_wpd_amount_paid_currency', true );
            // $wpd_date_paid 					= get_post_meta( $post->ID, '_wpd_date_paid', true );
            // $wpd_date_invoiced 				= get_post_meta( $post->ID, '_wpd_date_invoiced', true );
            // $wpd_expense_reference 			= get_post_meta( $post->ID, '_wpd_expense_reference', true );
            // $recurring_expense_enabled 		= get_post_meta( $post->ID, '_wpd_recurring_expense_enabled', true );
            // $recurring_expense_frequency 	= get_post_meta( $post->ID, '_wpd_recurring_expense_frequency', true );
            // $recurring_expense_date_started	= get_post_meta( $post->ID, '_wpd_recurring_expense_beginning_date', true );
            // $recurring_expense_date_ended 	= get_post_meta( $post->ID, '_wpd_recurring_expense_end_date', true );
            // $facebook_api_data 				= get_post_meta( $post->ID, '_wpd_fb_api_data', true );
            // $google_api_data 				= get_post_meta( $post->ID, '_wpd_google_api_campaign_data', true );
            // $google_api_account_data 		= get_post_meta( $post->ID, '_wpd_google_api_account_data', true );
            // $wpd_expense_attachments 		= get_post_meta( $post->ID, '_wpd_expense_attachments', true );
            
			if ( $recurring_expense_enabled ) {

				$recurring_expense_frequency 			= get_post_meta( $post_id, '_wpd_recurring_expense_frequency', true );
				$recurring_expense_date_started			= get_post_meta( $post_id, '_wpd_recurring_expense_beginning_date', true );
				$recurring_expense_date_end			    = get_post_meta( $post_id, '_wpd_recurring_expense_end_date', true );
				$recurring_expense_date_started_string 	= strtotime( $recurring_expense_date_started );
				$recurring_expense_date_end_string 	    = ( ! empty($recurring_expense_date_end) ) ? strtotime( $recurring_expense_date_end ) : null;
				$report_from_date_string 				= strtotime( $date_from );
				$report_to_date_string 					= strtotime( $date_to );

                $total_unique_recurring_expense_count++;

				// Start looping from recurring expense date, jumping by frequency on each loop
				while ( $recurring_expense_date_started_string <= $report_to_date_string ) {

				// Dont do calculations if its too old
				if ( $recurring_expense_date_started_string < $report_from_date_string ) {

					$new_timestamp = false;

					if ( $recurring_expense_frequency === 'daily' ) {

						$new_timestamp = strtotime( '+1 day', $recurring_expense_date_started_string );

					} elseif ( $recurring_expense_frequency === 'weekly' ) {

						$new_timestamp = strtotime( '+1 week', $recurring_expense_date_started_string );

					} elseif ( $recurring_expense_frequency === 'fortnightly' ) {

						$new_timestamp = strtotime( '+2 weeks', $recurring_expense_date_started_string );

					} elseif ( $recurring_expense_frequency === 'monthly' ) {

						$new_timestamp = strtotime( '+1 month', $recurring_expense_date_started_string );

					} elseif ( $recurring_expense_frequency === 'quarterly' ) {

						$new_timestamp = strtotime( '+3 months', $recurring_expense_date_started_string );

					} elseif ( $recurring_expense_frequency === 'yearly' || $recurring_expense_frequency === 'annually' ) {

						$new_timestamp = strtotime( '+1 year', $recurring_expense_date_started_string );

					} else {

						// Unknown frequency - default to monthly to prevent infinite loop
						$new_timestamp = strtotime( '+1 month', $recurring_expense_date_started_string );

					}

					// Validate timestamp advancement to prevent infinite loops
					if ( $new_timestamp === false || $new_timestamp <= $recurring_expense_date_started_string ) {
						wpd_write_log( "Recurring expense loop validation failed for expense ID: {$post_id}. Breaking loop to prevent infinite loop.", 'expense_error' );
						break;
					}

					$recurring_expense_date_started_string = $new_timestamp;
					continue;
				}

                    // Unique ID
                    $unique_id = $post_id . '-' . $wpd_date_paid;
                    $expense_type_names_string  = array();

                    // If we have an end date for the recurring expense, kill this process if we're hitting that mark
                    if ( ! is_null($recurring_expense_date_end_string) && $recurring_expense_date_started_string > $recurring_expense_date_end_string ) break;

                    // If this recurring expense exceeds the current date, kill the process
                    if ( $recurring_expense_date_started_string > current_time('timestamp') ) break;

					// Set date paid to recurring date
					$wpd_date_paid = date( 'Y-m-d', $recurring_expense_date_started_string );

                    // Currency Conversion
					if ( $wpd_amount_paid_currency != $store_currency ) {
						$converted_value = wpd_convert_currency( $wpd_amount_paid_currency, $store_currency, $wpd_amount_paid );
					} else {
						$converted_value = $wpd_amount_paid; 
					}

                    // Amounts
                    if ( $is_expense_paid ) {
                        $total_amount_paid += $converted_value;
                        $total_paid_expense_count++;
                    } else {
                        $total_amount_unpaid += $converted_value;
                        $total_unpaid_expense_count++;
                    }

                    $total_amount += $converted_value;
                    $total_expense_count ++;
                    $total_recurring_expense_count++;

                    if ( is_array($suppliers) && ! empty($suppliers) ) {

                        foreach( $suppliers as $supplier ) {

                            $supplier_id = $supplier->term_id;
                            $supplier_name = $supplier->name;
                            $supplier_slug = $supplier->slug;
                            $supplier_parent_id = $supplier->parent;
                            $supplier_names_string[] = $supplier_name;

                            if ( ! isset( $categorized_data['supplier_expense_data'][$supplier_slug]) ) $categorized_data['supplier_expense_data'][$supplier_slug] = $expense_data_array;

                            $categorized_data['supplier_expense_data'][$supplier_slug]['total_expense_count']++;
                            $categorized_data['supplier_expense_data'][$supplier_slug]['total_amount']             += $converted_value;
                            $categorized_data['supplier_expense_data'][$supplier_slug]['total_amount_paid']        += ( $is_expense_paid ) ? $converted_value : 0;
                            $categorized_data['supplier_expense_data'][$supplier_slug]['total_amount_unpaid']      += ( ! $is_expense_paid ) ? $converted_value : 0;
                            $categorized_data['supplier_expense_data'][$supplier_slug]['label']                    = $supplier_name;
                            $categorized_data['supplier_expense_data'][$supplier_slug]['slug']                     = $supplier_slug;
                            $categorized_data['supplier_expense_data'][$supplier_slug]['id']                       = $supplier_id;
                            $categorized_data['supplier_expense_data'][$supplier_slug]['parent_id']                = $supplier_parent_id;
                            $categorized_data['supplier_expense_data'][$supplier_slug]['unique_expenses'][]        = $unique_id;

                        }
                    }

					// Expense Type Calculations
					if ( is_array($expense_type) ) {

                        // Tax expenses
						foreach( $expense_type as $expense ) {

                            $expense_type_id = $expense->term_id;
                            $expense_type_name = $expense->name;
                            $expense_type_slug = $expense->slug;
                            $expense_type_parent_id = $expense->parent;
                            $expense_type_names_string[] = $expense_type_name;

                            // This is a parent expense
							if ( $expense_type_parent_id === 0 ) {

                                // Setup Default
                                if ( ! isset( $parent_expense_by_type[$expense_type_slug]) ) $parent_expense_by_type[$expense_type_slug] = $expense_data_array;

                                // Only build child expense data when its been set
                                $parent_expense_by_type[$expense_type_slug]['total_expense_count']++;
                                $parent_expense_by_type[$expense_type_slug]['total_amount']             += $converted_value;
                                $parent_expense_by_type[$expense_type_slug]['total_amount_paid']        += ( $is_expense_paid ) ? $converted_value : 0;
                                $parent_expense_by_type[$expense_type_slug]['total_amount_unpaid']      += ( ! $is_expense_paid ) ? $converted_value : 0;
                                $parent_expense_by_type[$expense_type_slug]['label']                    = $expense_type_name;
                                $parent_expense_by_type[$expense_type_slug]['slug']                     = $expense_type_slug;
                                $parent_expense_by_type[$expense_type_slug]['id']                       = $expense_type_id;
                                $parent_expense_by_type[$expense_type_slug]['parent_id']                = $expense_type_parent_id;
                                $parent_expense_by_type[$expense_type_slug]['unique_expenses'][]        = $unique_id;

							} else {

                                // Setup Default
                                if ( ! isset( $child_expense_by_type[$expense_type_slug]) ) $child_expense_by_type[$expense_type_slug] = $expense_data_array;

                                // Parent cat details
                                $parent_category 		    = get_term_by( 'id', $expense_type_parent_id, 'expense_category' );
                                $parent_expense_type_name 	= ( is_a($parent_category, 'WP_Term') ) ? $parent_category->name : 'Unknown';
                                $parent_expense_type_slug 	= ( is_a($parent_category, 'WP_Term') ) ? $parent_category->slug : null;
                                $parent_expense_type_id 	= ( is_a($parent_category, 'WP_Term') ) ? $parent_category->id : null;

                                // Only build child expense data when its been set
                                $child_expense_by_type[$expense_type_slug]['total_expense_count']++;
                                $child_expense_by_type[$expense_type_slug]['total_amount']             += $converted_value;
                                $child_expense_by_type[$expense_type_slug]['total_amount_paid']        += ( $is_expense_paid ) ? $converted_value : 0;
                                $child_expense_by_type[$expense_type_slug]['total_amount_unpaid']      += ( ! $is_expense_paid ) ? $converted_value : 0;
                                $child_expense_by_type[$expense_type_slug]['label']                    = $expense_type_name;
                                $child_expense_by_type[$expense_type_slug]['slug']                     = $expense_type_slug;
                                $child_expense_by_type[$expense_type_slug]['id']                       = $expense_type_id;
                                $child_expense_by_type[$expense_type_slug]['parent_id']                = $expense_type_parent_id;
                                $child_expense_by_type[$expense_type_slug]['parent_expense_label']     = $parent_expense_type_name;
                                $child_expense_by_type[$expense_type_slug]['unique_expenses'][]        = $unique_id;

                                // Try add the data into the parent if it's not been set
                                if ( ! is_null($parent_expense_type_slug) ) {

                                    // Check if the array exists yet & setup if required
                                    if ( ! isset($parent_expense_by_type[$parent_expense_type_slug]) ) $parent_expense_by_type[$parent_expense_type_slug] = $expense_data_array;

                                    // Check if this unique expense exists, and add it to the parent if not set
                                    if ( ! in_array( $unique_id, $parent_expense_by_type[$parent_expense_type_slug]['unique_expenses'] ) ) {

                                        $parent_expense_by_type[$parent_expense_type_slug]['total_expense_count']++;
                                        $parent_expense_by_type[$parent_expense_type_slug]['total_amount']             += $converted_value;
                                        $parent_expense_by_type[$parent_expense_type_slug]['total_amount_paid']        += ( $is_expense_paid ) ? $converted_value : 0;
                                        $parent_expense_by_type[$parent_expense_type_slug]['total_amount_unpaid']      += ( ! $is_expense_paid ) ? $converted_value : 0;
                                        $parent_expense_by_type[$parent_expense_type_slug]['label']                    = $parent_expense_type_name;
                                        $parent_expense_by_type[$parent_expense_type_slug]['slug']                     = $parent_expense_type_slug;
                                        $parent_expense_by_type[$parent_expense_type_slug]['id']                       = $parent_expense_type_id;
                                        $parent_expense_by_type[$parent_expense_type_slug]['parent_id']                = 0;
                                        $parent_expense_by_type[$parent_expense_type_slug]['unique_expenses'][]        = $unique_id;

                                    }

                                }

							}

						}

					}

                    // Date Calculations
                    $date_created_unix      = strtotime( $wpd_date_paid );
                    $date_range_key         = date( $date_format, $date_created_unix );

                    // Clean up expense type string
                    $expense_type_names_string = ( is_array($expense_type_names_string) && ! empty($expense_type_names_string) ) ? implode(', ', $expense_type_names_string ) : $expense_type_names_string = 'Unknown';

                    if( isset($data_by_date['amount_paid_by_date'][$date_range_key]) ) {
                        if ( $is_expense_paid ) {
                            $data_by_date['amount_paid_by_date'][$date_range_key] += $converted_value;
                        } else {
                            $data_by_date['amount_unpaid_by_date'][$date_range_key] += $converted_value;
                        }
                    }

                    // Store organised data
                    $data_table[$unique_id] = array(
                        'title' 				=> wp_strip_all_tags(html_entity_decode(get_the_title($post_id), ENT_QUOTES, 'UTF-8')),
                        'date_created'          => get_the_date('Y-m-d'),
                        'date_paid_unix' 		=> strtotime( $wpd_date_paid ),
                        'date_paid' 			=> $wpd_date_paid,
                        'reference' 			=> $wpd_expense_reference,
                        'amount_paid'			=> $wpd_amount_paid,
                        'amount_paid_currency' 	=> $wpd_amount_paid_currency,
                        'amount_paid_converted' => $converted_value,
                        'converted_to_currency' => $store_currency,
                        'expense_type' 			=> $expense_type_name,
                        'expense_type_string'   => $expense_type_names_string,
                        'recurring_expense'     => 1,
                        'recurring_frequency'   => $recurring_expense_frequency,
                        'post_id'				=> $post_id,
                        'reference_number'      => $wpd_expense_reference,
                        'is_paid'               => $is_expense_paid
                    );

                    // Setup next date to check with validation to prevent infinite loops
				$new_timestamp = false;

				if ( $recurring_expense_frequency === 'daily' ) {

					$new_timestamp = strtotime( '+1 day', $recurring_expense_date_started_string );

				} elseif ( $recurring_expense_frequency === 'weekly' ) {

					$new_timestamp = strtotime( '+1 week', $recurring_expense_date_started_string );

				} elseif ( $recurring_expense_frequency === 'fortnightly' ) {

					$new_timestamp = strtotime( '+2 weeks', $recurring_expense_date_started_string );

				} elseif ( $recurring_expense_frequency === 'monthly' ) {

					$new_timestamp = strtotime( '+1 month', $recurring_expense_date_started_string );

				} elseif ( $recurring_expense_frequency === 'quarterly' ) {

					$new_timestamp = strtotime( '+3 months', $recurring_expense_date_started_string );

				} elseif ( $recurring_expense_frequency === 'yearly' || $recurring_expense_frequency === 'annually' ) {

					$new_timestamp = strtotime( '+1 year', $recurring_expense_date_started_string );

				} else {

					// Unknown frequency - default to monthly to prevent infinite loop
					$new_timestamp = strtotime( '+1 month', $recurring_expense_date_started_string );

				}

				// Validate timestamp advancement to prevent infinite loops
				if ( $new_timestamp === false || $new_timestamp <= $recurring_expense_date_started_string ) {
					wpd_write_log( "Recurring expense loop validation failed for expense ID: {$post_id}. Breaking loop to prevent infinite loop.", 'expense_error' );
					break;
				}

				$recurring_expense_date_started_string = $new_timestamp;

			}

			} else {

				// Non recurring calculations
                $unique_id = $post_id;

                // Make conversions if required
				if ( $wpd_amount_paid_currency != $store_currency ) {

					$converted_value = wpd_convert_currency( $wpd_amount_paid_currency, $store_currency, $wpd_amount_paid );

				} else {

					$converted_value = $wpd_amount_paid;

				}

                // Amounts
                if ( $is_expense_paid ) {
                    $total_amount_paid += $converted_value;
                    $total_paid_expense_count++;
                } else {
                    $total_amount_unpaid += $converted_value;
                    $total_unpaid_expense_count++;
                }

                // Update totals
                $total_amount += $converted_value;
                $total_expense_count ++;
                $total_standard_expense_count++;

                // Supplier Calculations
                if ( is_array($suppliers) && ! empty($suppliers) ) {

                    foreach( $suppliers as $supplier ) {

                        $supplier_id = $supplier->term_id;
                        $supplier_name = $supplier->name;
                        $supplier_slug = $supplier->slug;
                        $supplier_parent_id = $supplier->parent;
                        $supplier_names_string[] = $supplier_name;

                        if ( ! isset( $categorized_data['supplier_expense_data'][$supplier_slug]) ) $categorized_data['supplier_expense_data'][$supplier_slug] = $expense_data_array;

                        $categorized_data['supplier_expense_data'][$supplier_slug]['total_expense_count']++;
                        $categorized_data['supplier_expense_data'][$supplier_slug]['total_amount']             += $converted_value;
                        $categorized_data['supplier_expense_data'][$supplier_slug]['total_amount_paid']        += ( $is_expense_paid ) ? $converted_value : 0;
                        $categorized_data['supplier_expense_data'][$supplier_slug]['total_amount_unpaid']      += ( ! $is_expense_paid ) ? $converted_value : 0;
                        $categorized_data['supplier_expense_data'][$supplier_slug]['label']                    = $supplier_name;
                        $categorized_data['supplier_expense_data'][$supplier_slug]['slug']                     = $supplier_slug;
                        $categorized_data['supplier_expense_data'][$supplier_slug]['id']                       = $supplier_id;
                        $categorized_data['supplier_expense_data'][$supplier_slug]['parent_id']                = $supplier_parent_id;
                        $categorized_data['supplier_expense_data'][$supplier_slug]['unique_expenses'][]        = $unique_id;

                    }
                }

                // Expense Type Calculations
                if ( is_array($expense_type) ) {

                    // Tax expenses
                    foreach( $expense_type as $expense ) {

                        $expense_type_id = $expense->term_id;
                        $expense_type_name = $expense->name;
                        $expense_type_slug = $expense->slug;
                        $expense_type_parent_id = $expense->parent;
                        $expense_type_names_string[] = $expense_type_name;

                        // This is a parent expense
                        if ( $expense_type_parent_id == 0 ) {

                            // Setup Default
                            if ( ! isset( $parent_expense_by_type[$expense_type_slug]) ) $parent_expense_by_type[$expense_type_slug] = $expense_data_array;

                            // Only build child expense data when its been set
                            $parent_expense_by_type[$expense_type_slug]['total_expense_count']++;
                            $parent_expense_by_type[$expense_type_slug]['total_amount']             += $converted_value;
                            $parent_expense_by_type[$expense_type_slug]['total_amount_paid']        += ( $is_expense_paid ) ? $converted_value : 0;
                            $parent_expense_by_type[$expense_type_slug]['total_amount_unpaid']      += ( ! $is_expense_paid ) ? $converted_value : 0;
                            $parent_expense_by_type[$expense_type_slug]['label']                    = $expense_type_name;
                            $parent_expense_by_type[$expense_type_slug]['slug']                     = $expense_type_slug;
                            $parent_expense_by_type[$expense_type_slug]['id']                       = $expense_type_id;
                            $parent_expense_by_type[$expense_type_slug]['parent_id']                = $expense_type_parent_id;
                            $parent_expense_by_type[$expense_type_slug]['unique_expenses'][]        = $unique_id;

                        } else {

                            // Setup Default
                            if ( ! isset( $child_expense_by_type[$expense_type_slug]) ) $child_expense_by_type[$expense_type_slug] = $expense_data_array;

                            // Parent cat details
                            $parent_category 		    = get_term_by( 'id', $expense_type_parent_id, 'expense_category' );
                            $parent_expense_type_name 	= ( is_a($parent_category, 'WP_Term') ) ? $parent_category->name : 'Unknown';
                            $parent_expense_type_slug 	= ( is_a($parent_category, 'WP_Term') ) ? $parent_category->slug : null;
                            $parent_expense_type_id 	= ( is_a($parent_category, 'WP_Term') ) ? $parent_category->id : null;

                            // Only build child expense data when its been set
                            $child_expense_by_type[$expense_type_slug]['total_expense_count']++;
                            $child_expense_by_type[$expense_type_slug]['total_amount']             += $converted_value;
                            $child_expense_by_type[$expense_type_slug]['total_amount_paid']        += ( $is_expense_paid ) ? $converted_value : 0;
                            $child_expense_by_type[$expense_type_slug]['total_amount_unpaid']      += ( ! $is_expense_paid ) ? $converted_value : 0;
                            $child_expense_by_type[$expense_type_slug]['label']                    = $expense_type_name;
                            $child_expense_by_type[$expense_type_slug]['slug']                     = $expense_type_slug;
                            $child_expense_by_type[$expense_type_slug]['id']                       = $expense_type_id;
                            $child_expense_by_type[$expense_type_slug]['parent_id']                = $expense_type_parent_id;
                            $child_expense_by_type[$expense_type_slug]['parent_expense_label']     = $parent_expense_type_name;
                            $child_expense_by_type[$expense_type_slug]['unique_expenses'][]        = $unique_id;

                            // Try add the data into the parent if it's not been set
                            if ( ! is_null($parent_expense_type_slug) ) {

                                // Check if the array exists yet & setup if required
                                if ( ! isset($parent_expense_by_type[$parent_expense_type_slug]) ) $parent_expense_by_type[$parent_expense_type_slug] = $expense_data_array;

                                // Check if this unique expense exists, and add it to the parent if not set
                                if ( ! in_array( $unique_id, $parent_expense_by_type[$parent_expense_type_slug]['unique_expenses'] ) ) {

                                    $parent_expense_by_type[$parent_expense_type_slug]['total_expense_count']++;
                                    $parent_expense_by_type[$parent_expense_type_slug]['total_amount']             += $converted_value;
                                    $parent_expense_by_type[$parent_expense_type_slug]['total_amount_paid']        += ( $is_expense_paid ) ? $converted_value : 0;
                                    $parent_expense_by_type[$parent_expense_type_slug]['total_amount_unpaid']      += ( ! $is_expense_paid ) ? $converted_value : 0;
                                    $parent_expense_by_type[$parent_expense_type_slug]['label']                    = $parent_expense_type_name;
                                    $parent_expense_by_type[$parent_expense_type_slug]['slug']                     = $parent_expense_type_slug;
                                    $parent_expense_by_type[$parent_expense_type_slug]['id']                       = $parent_expense_type_id;
                                    $parent_expense_by_type[$parent_expense_type_slug]['parent_id']                = 0;
                                    $parent_expense_by_type[$parent_expense_type_slug]['unique_expenses'][]        = $unique_id;

                                }

                            }

                        }

                    }

                }

                // Date Calculations
                $date_created_unix = strtotime( $wpd_date_paid );
                $date_range_key = date( $date_format, $date_created_unix );
                if( isset($data_by_date['amount_paid_by_date'][$date_range_key]) || isset($data_by_date['amount_unpaid_by_date'][$date_range_key]) ) {
                    if ( $is_expense_paid ) {
                        $data_by_date['amount_paid_by_date'][$date_range_key] += $converted_value;
                    } else {
                        $data_by_date['amount_unpaid_by_date'][$date_range_key] += $converted_value;
                    }
                }

                // Clean up expense type string
                $expense_type_names_string = ( is_array($expense_type_names_string) && ! empty($expense_type_names_string) ) ? implode(', ', $expense_type_names_string ) : $expense_type_names_string = 'Unknown';

                // Store organised data
                $data_table[$post_id] = array(
                    'title' 				=> wp_strip_all_tags(html_entity_decode(get_the_title($post_id), ENT_QUOTES, 'UTF-8')),
                    'date_created'          => get_the_date('Y-m-d'),
                    'date_paid_unix' 		=> strtotime( $wpd_date_paid ),
                    'date_paid' 			=> $wpd_date_paid,
                    'reference' 			=> $wpd_expense_reference,
                    'amount_paid'			=> $wpd_amount_paid,
                    'amount_paid_currency' 	=> $wpd_amount_paid_currency,
                    'amount_paid_converted' => $converted_value,
                    'converted_to_currency' => $store_currency,
                    'expense_type' 			=> $expense_type_name,
                    'expense_type_string'   => $expense_type_names_string,
                    'recurring_expense'     => 0,
                    'recurring_frequency'   => null,
                    'post_id'				=> $post_id,
                    'reference_number'      => $wpd_expense_reference,
                    'is_paid'               => $is_expense_paid
                );
                
			}

		} // End foreach

        // Sort arrays
        // Parent Expense Taxonomy
        if ( isset($parent_expense_by_type) && is_array($parent_expense_by_type) && ! empty($parent_expense_by_type) ) {
            // $parent_expense_by_type = wpd_sort_multi_level_array( $parent_expense_by_type, 'total' );
            foreach( $parent_expense_by_type as $key => &$value ) {
                unset($value['id']);
                unset($value['label']);
                unset($value['parent_expense_label']);
                unset($value['parent_id']);
                unset($value['slug']);
                unset($value['unique_expenses']);
                $parent_expense_by_type[$key]['percent_of_total_expenses'] = wpd_calculate_percentage( $value['total_amount'], $total_amount );
            }
        } else {
            $parent_expense_by_type = array();
        }
        // Child expense by taxonomy
        if ( isset($child_expense_by_type) && is_array($child_expense_by_type) && ! empty($child_expense_by_type) ) {
            // $child_expense_by_type = wpd_sort_multi_level_array( $child_expense_by_type, 'total' );
            foreach( $child_expense_by_type as $key => &$value ) {
                unset($value['id']);
                unset($value['label']);
                unset($value['parent_expense_label']);
                unset($value['parent_id']);
                unset($value['slug']);
                unset($value['unique_expenses']);
                $child_expense_by_type[$key]['percent_of_total_expenses'] = wpd_calculate_percentage( $value['total_amount'], $total_amount );
            }
        } else {
            $child_expense_by_type = array();
        }

        // Clean suppliers
        if ( is_array($categorized_data['supplier_expense_data']) && ! empty($categorized_data['supplier_expense_data']) ) {

            foreach( $categorized_data['supplier_expense_data'] as $key => &$value ) {
                unset($value['id']);
                unset($value['label']);
                unset($value['parent_id']);
                unset($value['slug']);
                unset($value['unique_expenses']);
                $value['percent_of_total_expenses'] = wpd_calculate_percentage( $value['total_amount'], $total_amount );
            }

        } else {
            $categorized_data['supplier_expense_data'] = array();
        }

        // Store totals
        $totals['total_amount_paid']  		            = $total_amount_paid;
        $totals['total_amount_unpaid']  		        = $total_amount_unpaid;
        $totals['total_amount']                         = $total_amount;
        $totals['average_expenses_per_day'] 	        = wpd_divide( $total_amount, $n_days_period );
        $totals['total_expense_count']                  = $total_expense_count;
        $totals['total_unpaid_expense_count']           = $total_unpaid_expense_count;
        $totals['total_paid_expense_count']             = $total_paid_expense_count;
        $totals['total_standard_expense_count']         = $total_standard_expense_count;
        $totals['total_recurring_expense_count']        = $total_recurring_expense_count;
        $totals['total_unique_recurring_expense_count'] = $total_unique_recurring_expense_count;
        $totals['daily_average_expense_count']          = wpd_divide( $total_expense_count, $n_days_period );
        $totals['daily_average_expense_amount']         = $totals['average_expenses_per_day']; // New key
        
        // Store categorized data
        $categorized_data['parent_expense_type_categories'] 			= $parent_expense_by_type;
        $categorized_data['child_expense_type_categories'] 			    = $child_expense_by_type;
        // $categorized_data['expense_post_ids']             = $expense_post_ids;
        
        $total_db_records                               = count( $expense_post_ids );

        $data_by_date = $this->maybe_create_no_data_found_date_array( $data_by_date );
        
        // Calculate execution time
        $execution_time = microtime(true) - $start_time + $this->get_data('expenses', 'execution_time');
        
        // Configure return object
        $expense_data = array(
            'totals' => $totals,
            'categorized_data' => $categorized_data,
            'data_by_date' => $data_by_date,
            'data_table' => array(
                'expenses' => $data_table
            ),
            'total_db_records' => $total_db_records,
            'execution_time' => $execution_time
        );

        // Store the data into the prop
        $this->set_data( 'expenses', $expense_data );

        // Return Results
        return $expense_data;

    }

    /**
     * 
     *  Fetches Facebook Campaign data and organises it for use
     * 
     *  Available Filters: 
     * 
     *      date_from (will filter against _wpd_campaign_start)
     *      date_to (will filter against _wpd_campaign_start)
     *      campaign_id
     *      campaign_date_override This will return data from within the campaign_id's date (requires campaign_id)
     *  
     **/
    public function fetch_facebook_campaign_data() {

        // Start execution timer
        $start_time = microtime(true);

    	$start 	                    = $this->get_date_from(); 	// date in the past
        $end 	                    = $this->get_date_to(); 	// current date
        $date_format                = $this->get_filter( 'date_format_string' );
        $unix_date_from             = strtotime( $start . ' 23:59:59' ); // Make sure it includes orders that day
        $unix_date_to               = strtotime( $end . ' 23:59:59' ); // Make sure it includes orders that day
        $store_currency             = wpd_get_store_currency();

        $default_product_sales_data = array(
            'product_name' => '',
            'product_sku' => '',
            'product_id' => '',
            'total_product_revenue' => 0,
            'total_product_revenue_excluding_tax' => 0,
            'total_product_cost' => 0,
            'total_product_profit' => 0,
            'total_qty_sold' => 0,
            'total_times_sold' => 0,
            'average_margin' => 0
        );

        // Variables to fill in and pass as data
        $totals = array(
            'campaigns_found' => 0,
            'campaign_spend' => 0,
            'campaign_spend_per_active_day' => 0,
            'campaign_order_count' => 0,
            'campaign_order_revenue' => 0,
            'campaign_order_costs' => 0,
            'campaign_order_profit' => 0,
            'campaign_order_margin' => 0,
            'campaign_order_conversion_rate' => 0,
            'campaign_cost_per_order' => 0,
            'campaign_total_profit' => 0,
            'campaign_average_order_value' => 0,
            'campaign_largest_order_value' => 0,
            'campaign_total_profit_per_day' => 0,
            'campaign_revenue_roas' => 0,
            'campaign_adjusted_roas' => 0,
            'campaign_adjusted_margin' => 0,
            'campaign_orders_per_click' => 0,
            'campaign_orders_per_active_campaign_day' => 0,
            'campaign_api_revenue' => 0,
            'campaign_api_profit' => 0,
            'campaign_api_roas' => 0,
            'campaign_api_conversions' => 0,
            'campaign_api_transactions' => 0,
            'campaign_api_conversion_rate' => 0,
            'campaign_total_days_active' => 0,
            'campaign_average_days_active' => 0,
            'campaign_impressions' => 0,
            'campaign_clicks' => 0,
            'campaign_average_cpc' => 0,
            'campaign_average_ctr' => 0,
            'campaign_add_to_carts' => 0,
            'ad_account_currency_converted' => 0,
            'new_customer_count' => 0,
            'cost_per_new_customer' => 0,
            'total_customer_count' => 0,
            'cost_per_customer' => 0,
            'all_campaigns_count' => 0,
        );
        $categorized_data = array(
            'customers_by_email_address' => array(),
            'filtered_campaigns' => array(),
            'all_campaigns' => array(),
            'order_ids' => array(),
            'order_data' => array(),
            'product_data' => array()
        );
        $data_table = array();
        $data_table_orders = array();
        $total_db_records = 0;
        $data_by_date = array(
            'campaign_spend_by_date' => $this->get_data_by_date_range_container(),
            'campaign_order_revenue_by_date' => $this->get_data_by_date_range_container(),
            'campaign_api_revenue_by_date' => $this->get_data_by_date_range_container(),
            'campaign_order_profit_by_date' => $this->get_data_by_date_range_container(),
            'campaign_api_profit_by_date' => $this->get_data_by_date_range_container(),
            'campaign_actual_profit_by_date' => $this->get_data_by_date_range_container(),
            'campaign_clicks_by_date' => $this->get_data_by_date_range_container(),
            'campaign_add_to_carts_by_date' => $this->get_data_by_date_range_container(),
            'campaign_order_count_by_date' => $this->get_data_by_date_range_container(),
            'campaign_new_customer_count_by_date' => $this->get_data_by_date_range_container(), // New
            'campaign_cost_per_new_customer_by_date' => $this->get_data_by_date_range_container(), // New
            'campaign_cost_per_order_placed_by_date' => $this->get_data_by_date_range_container(), // New
            'campaign_conversion_rate_by_date' => $this->get_data_by_date_range_container(), // New
            'campaign_api_transactions_by_date' => $this->get_data_by_date_range_container(),
            'campaign_actual_roas_by_date' => $this->get_data_by_date_range_container(),
            'campaign_api_roas_by_date' => $this->get_data_by_date_range_container(),
            'campaign_comparison_actual_profit_by_date' => array( 'no_data_available' => $this->get_data_by_date_range_container() ),
            'campaign_comparison_campaign_spend_by_date' => array( 'no_data_available' => $this->get_data_by_date_range_container() ),
        );

        $filtered_campaign_count        = 0;
        $fb_ad_spend 					= 0;
        $fb_ad_revenue 					= 0;
        $fb_ad_profit 					= 0;
        $fb_ad_transactions 			= 0;
        $fb_ad_atc 						= 0; 	
        $fb_ad_clicks 					= 0; 	
        $fb_total_days_active 			= 0;

        // It's a calculation
        $categorized_data['all_campaigns']        = $this->fetch_all_meta_campaign_ids();
        $totals['all_campaigns_count']            = ( is_array($categorized_data['all_campaigns']) ) ? count($categorized_data['all_campaigns']) : 0;
        $categorized_data['order_data']           = $this->get_order_ids_with_meta_campaign_ids();

        /**
         *
         *	Change this to just use the query above, filter by date found in meta
         *
         */
        // Run main query
		$facebook_campaign_args = array(

			'fields' 			=> 'ids',
		    'post_type' 		=> 'facebook_campaign',
		    'post_status' 		=> 'publish',
		    'posts_per_page' 	=> -1,
		    'orderby' 			=> 'meta_value',
		    'meta_key' 			=> '_wpd_campaign_start',
		    'order' 			=> 'DESC',

		);

		/**
         *
         *	Setup filters
         *
         */
        if ( $this->get_data_filter( 'facebook_campaigns', 'campaign' ) && is_array( $this->get_data_filter( 'facebook_campaigns', 'campaign' ) ) ) {

        	$facebook_campaign_args['meta_query'][] = array(
	            'key' 		=> '_wpd_campaign_id',
	            'value' 	=> $this->get_data_filter( 'facebook_campaigns', 'campaign' ),
	            'compare' => 'IN',
	        );

        }

        if ( WPD_AI_PRO ) {
            $facebook_query 		= new WP_Query( $facebook_campaign_args );
            $campaign_post_ids 		= $facebook_query->posts;
        } else {
            $campaign_post_ids = array();
        }

        $total_db_records       = count( $campaign_post_ids );

        // Insert 0 in case we have anonymous orders (forces a failure in our get_meta calls)
        $campaign_post_ids[] = 0;

        // Cycle through found campaigns
        foreach( $campaign_post_ids as $post_id ) {

        	// Store Variables
        	$campaign_id 		                        = ( ! empty( get_post_meta( $post_id, '_wpd_campaign_id', true ) ) ) ? (int) get_post_meta( $post_id, '_wpd_campaign_id', true ) : 'unknown';
        	$campaign_totals 	                        = get_post_meta( $post_id, '_wpd_totals_data', true );
        	$campaign_daily 	                        = get_post_meta( $post_id, '_wpd_daily_data', true );
        	$campaign_name 	                            = ( ! empty( get_post_meta( $post_id, '_wpd_campaign_name', true ) ) ) ? get_post_meta( $post_id, '_wpd_campaign_name', true ) : 'Unknown Campaign';
            $totals['ad_account_currency_converted']    = (is_array($campaign_totals) && isset($campaign_totals['currency_converted']) && $campaign_totals['currency_converted']) ? 1 : 0;

            /**
             *  2. Organise the array
             **/
            $campaign_status                = null;
            $campaign_start                 = $campaign_totals['campaign_start'] ?? null;
            $campaign_stop                  = $campaign_totals['campaign_stop'] ?? null;
            $campaign_currency              = $campaign_totals['account_currency'] ?? $store_currency;
            $campaign_ad_account_name       = $campaign_totals['account_name'] ?? null;
            $campaign_ad_account_id         = $campaign_totals['account_id'] ?? null;
            $campaign_last_updated_unix     = $campaign_totals['last_updated_unix'] ?? null;
            $is_campaign_spend_converted    = false;

            // If we've converted currency
            if ( $campaign_currency != $store_currency ) $is_campaign_spend_converted = true;

            // These need to be calculated based on daily's
            $campaign_days_active           = 0; // Iteration
            $campaign_spend                 = 0; // Iteration
            $campaign_spend_unconverted     = 0; // Iteration
            $campaign_impressions           = 0; // Iteration
            $campaign_clicks                = 0; // Iteration
            $campaign_conversions           = 0; // Iteration
            $campaign_conversion_value      = 0; // Iteration
            $campaign_add_to_carts          = 0; // Iteration
            $campaign_average_cpc           = 0; // Calculation
            $campaign_average_ctr           = 0; // Calculation
            $campaign_conversion_rate       = 0; // Calculation
            $campaign_roas                  = 0; // Calculation

            // Matching up with store data
            $campaign_order_revenue         = 0;
            $campaign_order_costs           = 0;
            $campaign_order_profit          = 0;
            $campaign_total_profit          = 0;
            $campaign_order_count           = 0;
            $campaign_new_customer_count    = 0;
            
            /**
             *  1. Iterate through daily data
             **/
            if ( is_array($campaign_daily) && ! empty($campaign_daily) )  {

                foreach( $campaign_daily as $fb_date => $fb_data ) {

                    $date_key = $this->convert_date_string( $fb_date, $date_format );
                    $campaign_daily_data_date_unix = strtotime( $date_key );

                    // Skip order if it's not within the date range we are looking at
                    if ( $campaign_daily_data_date_unix < $unix_date_from || $campaign_daily_data_date_unix > $unix_date_to ) {
                        continue;
                    }

                    // Vars
                    (float) $spend 									= $fb_data['spend'];
                    (float) $purchase_value 						= $fb_data['purchase_value'];
                    (float) $profit 								= $purchase_value - $spend;
                    (int) $clicks 									= $fb_data['outbound_clicks'];
                    (int) $add_to_carts 							= $fb_data['add_to_cart'];
                    (int) $purchases 								= $fb_data['purchases'];

                    // Remove the no data available container
                    if ( isset($data_by_date['campaign_comparison_actual_profit_by_date']['no_data_available']) ) $data_by_date['campaign_comparison_actual_profit_by_date'] = array();
                    if ( isset($data_by_date['campaign_comparison_campaign_spend_by_date']['no_data_available']) ) $data_by_date['campaign_comparison_campaign_spend_by_date'] = array();

                    // Setup campaign specific performance
                    if ( ! isset( $data_by_date['campaign_comparison_actual_profit_by_date'][$campaign_name]) ) $data_by_date['campaign_comparison_actual_profit_by_date'][$campaign_name] = $this->get_data_by_date_range_container();
                    if ( ! isset( $data_by_date['campaign_comparison_campaign_spend_by_date'][$campaign_name]) ) $data_by_date['campaign_comparison_campaign_spend_by_date'][$campaign_name] = $this->get_data_by_date_range_container();

                    // Cost
                    if ( isset($data_by_date['campaign_spend_by_date'][$date_key]) ) $data_by_date['campaign_spend_by_date'][$date_key] += $fb_data['spend'];

                    // Clicks
                    if ( isset($data_by_date['campaign_clicks_by_date'][$date_key]) ) $data_by_date['campaign_clicks_by_date'][$date_key] += $fb_data['outbound_clicks'];
   
                    // API Revenue
                    if ( isset($data_by_date['campaign_api_revenue_by_date'][$date_key]) ) $data_by_date['campaign_api_revenue_by_date'][$date_key] += $fb_data['purchase_value'];

                    // API Profit
                    if ( isset($data_by_date['campaign_api_profit_by_date'][$date_key]) ) $data_by_date['campaign_api_profit_by_date'][$date_key] += $profit;

                    // Add To Carts
                    if ( isset($data_by_date['campaign_add_to_carts_by_date'][$date_key]) ) $data_by_date['campaign_add_to_carts_by_date'][$date_key] += $fb_data['add_to_cart'];

                    // API Order Count
                    if ( isset($data_by_date['campaign_api_transactions_by_date'][$date_key]) ) $data_by_date['campaign_api_transactions_by_date'][$date_key] += $fb_data['purchases'];

                    // API ROAS
                    if ( isset($data_by_date['campaign_api_roas_by_date'][$date_key]) ) $data_by_date['campaign_api_roas_by_date'][$date_key] += $fb_data['roas'];

                    // Campaign Specific Ad Spend
                    if ( isset($data_by_date['campaign_comparison_campaign_spend_by_date'][$campaign_name][$date_key]) ) $data_by_date['campaign_comparison_campaign_spend_by_date'][$campaign_name][$date_key] += $fb_data['spend'];

                    // Calculations Stored For Totals
                    $fb_ad_spend 			+= $spend;
                    $fb_ad_revenue 			+= $purchase_value;
                    $fb_ad_profit 			+= $profit;
                    $fb_ad_clicks 			+= $clicks;
                    $fb_ad_atc 				+= $add_to_carts;
                    $fb_ad_transactions		+= $purchases;
                    $fb_total_days_active 	++;

                    $campaign_days_active++; // Iteration
                    $campaign_spend                 += $fb_data['spend']; // Iteration
                    $campaign_impressions           += $fb_data['impressions']; // Iteration
                    $campaign_clicks                += $fb_data['outbound_clicks']; // Iteration
                    $campaign_conversions           += $fb_data['purchases']; // Iteration
                    $campaign_conversion_value      += $fb_data['purchase_value']; // Iteration
                    $campaign_add_to_carts          += $fb_data['add_to_cart']; // Iteration
                    $campaign_spend_unconverted     += $fb_data['raw_spend'];

                }

            }
        
            /**
             *  2. Iterate through order data
             **/
            if ( isset($categorized_data['order_data'][$campaign_id]) && is_array($categorized_data['order_data'][$campaign_id]) ) {

                // Loop through order data from campaign
                foreach( $categorized_data['order_data'][$campaign_id] as $order_id => $order_data ) {

                    // Add our date key for the report
                    $order_date_unix = $order_data['date_created'];
                    $date_key = date( $date_format, $order_date_unix );

                    // Skip order if it's not within the date range we are looking at, should be all good we're looking at the right date range ?
                    if ( $order_date_unix < $unix_date_from || $order_date_unix > $unix_date_to ) {
                        continue;
                    }

                    // Add order ID to totals
                    if ( ! in_array($order_id, $categorized_data['order_ids']) ) $categorized_data['order_ids'][] = $order_id;

                    $campaign_order_count++;
                    $data_table_orders[$order_id] = $order_data;

                    // Build campaign specific totals
                    $campaign_order_revenue += $order_data['total_order_revenue'];
                    $campaign_order_costs += $order_data['total_order_cost'];
                    $campaign_order_profit += $order_data['total_order_profit'];

                    // New Customers Count
                    if ( $order_data['new_returning_customer'] == 'new' ) {
                        $campaign_new_customer_count++;
                        if ( isset($data_by_date['campaign_new_customer_count_by_date'][$date_key]) ) $data_by_date['campaign_new_customer_count_by_date'][$date_key]++;
                    } 

                    // Log all Customers
                    $categorized_data['customers_by_email_address'][] = $order_data['billing_email'];

                    // Setup default container for campaign comparisons
                    if (! isset($data_by_date['campaign_comparison_actual_profit_by_date'][$campaign_name]) ) {
                        $data_by_date['campaign_comparison_actual_profit_by_date'][$campaign_name] = $this->get_data_by_date_range_container();
                    }                     

                    // Add data to daily's
                    if ( isset($data_by_date['campaign_order_revenue_by_date'][$date_key]) ) $data_by_date['campaign_order_revenue_by_date'][$date_key] += $order_data['total_order_revenue'];
                    if ( isset($data_by_date['campaign_order_profit_by_date'][$date_key]) ) $data_by_date['campaign_order_profit_by_date'][$date_key] += $order_data['total_order_profit'];
                    if ( isset($data_by_date['campaign_order_count_by_date'][$date_key]) ) $data_by_date['campaign_order_count_by_date'][$date_key]++;
                    if ( isset($data_by_date['campaign_comparison_actual_profit_by_date'][$campaign_name][$date_key]) ) $data_by_date['campaign_comparison_actual_profit_by_date'][$campaign_name][$date_key] += $order_data['total_order_profit'];
                
                    // Product Sales Data
                    if (is_array($order_data['product_data']) && ! empty($order_data['product_data'])) {

                        foreach($order_data['product_data'] as $product_id => $product_data) {
                            
                            // Use product name as key
                            $product_name = ($product_data['product_name']) ? $product_data['product_name'] : 'Unknown Product';
                            
                            // Load Default Product Sales Data
                            if ( ! isset($categorized_data['product_data'][$product_name]) ) $categorized_data['product_data'][$product_name] = $default_product_sales_data;

                            // Add Product Data
                            $categorized_data['product_data'][$product_name]['product_name'] = $product_data['product_name'];
                            $categorized_data['product_data'][$product_name]['product_sku'] = $product_data['sku'];
                            $categorized_data['product_data'][$product_name]['product_id'] = $product_data['product_id'];
                            $categorized_data['product_data'][$product_name]['total_product_revenue'] += $product_data['product_revenue'];
                            $categorized_data['product_data'][$product_name]['total_product_revenue_excluding_tax'] += $product_data['product_revenue_excluding_tax'];
                            $categorized_data['product_data'][$product_name]['total_product_cost'] += $product_data['total_cost_of_goods'];
                            $categorized_data['product_data'][$product_name]['total_product_profit'] += $product_data['total_profit'];
                            $categorized_data['product_data'][$product_name]['total_qty_sold'] += $product_data['qty_sold'];
                            $categorized_data['product_data'][$product_name]['total_times_sold']++;
                            $categorized_data['product_data'][$product_name]['average_margin'] = wpd_calculate_margin( $categorized_data['product_data'][$product_name]['total_product_profit'], $categorized_data['product_data'][$product_name]['total_product_revenue_excluding_tax'] );

                        }

                    }

                }

            }

            // If we have no spend or revenue, assume it's not active
            if ( $campaign_spend === 0 && $campaign_order_revenue === 0 ) {
                continue;
            }

            // Campaign Totals - API Values
            $campaign_average_cpc           = wpd_divide( $campaign_spend, $campaign_clicks ); // Calculation
            $campaign_average_ctr           = wpd_calculate_percentage( $campaign_clicks, $campaign_impressions ); // Calculation
            $campaign_conversion_rate       = wpd_calculate_percentage( $campaign_conversions, $campaign_clicks ); // Calculation
            $campaign_roas                  = wpd_divide( $campaign_conversion_value, $campaign_spend ); // Calculation

            // Campaign Totals - Store Calculated Values
            $campaign_total_profit          = $campaign_order_profit - $campaign_spend;
            $campaign_order_conversion_rate = wpd_calculate_percentage( $campaign_order_count, $campaign_clicks );
            $campaign_order_revenue_roas    = wpd_divide( $campaign_order_revenue, $campaign_spend );
            $campaign_adjusted_roas         = wpd_divide( $campaign_total_profit, $campaign_spend );
            $campaign_adjusted_margin       = wpd_calculate_margin( $campaign_total_profit, $campaign_order_revenue );
            $campaign_cost_per_order        = wpd_divide( $campaign_order_count, $campaign_spend );
            $campaign_cost_per_new_customer = wpd_divide( $campaign_new_customer_count, $campaign_spend );

            // Iterate active campaigns
            $filtered_campaign_count++;

            // Setup Organised Arrays
            $data_table[$campaign_name] = array(
                'post_id' => $post_id,
                'campaign_last_updated_unix' => $campaign_last_updated_unix,
                'campaign_name' => $campaign_name,
                'campaign_id' => $campaign_id,
                'campaign_ad_account_name' => $campaign_ad_account_name,
                'campaign_ad_account_id' => $campaign_ad_account_id,
                'campaign_start' => $campaign_start,
                'campaign_stop' => $campaign_stop,
                'campaign_status' => $campaign_status,
                'campaign_days_active' => $campaign_days_active,
                'campaign_currency' => $campaign_currency,
                'campaign_converted' => $is_campaign_spend_converted,
                'campaign_spend_unconverted' => $campaign_spend_unconverted,
                'campaign_spend' => $campaign_spend,
                'campaign_impressions' => $campaign_impressions,
                'campaign_clicks' => $campaign_clicks,
                'campaign_add_to_carts' => $campaign_add_to_carts,
                'campaign_api_conversions' => $campaign_conversions,
                'campaign_api_conversion_value' => $campaign_conversion_value,
                'campaign_api_conversion_rate' => $campaign_conversion_rate,
                'campaign_api_roas' => $campaign_roas,
                'campaign_average_cpc' => $campaign_average_cpc,
                'campaign_average_ctr' => $campaign_average_ctr,
                'campaign_order_revenue' => $campaign_order_revenue,
                'campaign_order_costs' => $campaign_order_costs,
                'campaign_order_profit' => $campaign_order_profit,
                'campaign_order_count' => $campaign_order_count,
                'campaign_order_conversion_rate' => $campaign_order_conversion_rate,
                'campaign_cost_per_order' => $campaign_cost_per_order,
                'campaign_total_profit' => $campaign_total_profit,
                'campaign_revenue_roas' => $campaign_order_revenue_roas,
                'campaign_adjusted_roas' => $campaign_adjusted_roas,
                'campaign_adjusted_margin' => $campaign_adjusted_margin,
                'campaign_new_customer_count' => $campaign_new_customer_count,
                'campaing_cost_per_new_customer' => $campaign_cost_per_new_customer
            );

            $categorized_data['filtered_campaigns'][$campaign_id] = $campaign_name;

            /**
             *  4. Setup the totals
             **/
            // Add filtered campaigns to data
            $totals['campaign_spend'] += $campaign_spend;
            $totals['campaign_api_revenue'] += $campaign_conversion_value;
            $totals['campaign_api_profit'] += ( $campaign_conversion_value - $campaign_spend );
            $totals['campaign_api_conversions'] += $campaign_conversions;
            $totals['campaign_total_days_active'] += $campaign_days_active;
            $totals['campaign_impressions'] += $campaign_impressions;
            $totals['campaign_clicks'] += $campaign_clicks;
            $totals['campaign_average_cpc'] += $campaign_average_cpc;
            $totals['campaign_average_ctr'] += $campaign_clicks;
            $totals['campaign_order_revenue'] += $campaign_order_revenue;
            $totals['campaign_order_costs'] += $campaign_order_costs;
            $totals['campaign_order_profit'] += $campaign_order_profit;
            $totals['campaign_order_count'] += $campaign_order_count; // Happens earlier
            $totals['campaign_total_profit'] += $campaign_total_profit;
            $totals['new_customer_count'] += $campaign_new_customer_count;

        }

        // Do some calculated dailys
        foreach( $this->get_data_by_date_range_container() as $date_key => $empty_data ) {

            // Actual Profit By Date
            $data_by_date['campaign_actual_profit_by_date'][$date_key] = $data_by_date['campaign_order_profit_by_date'][$date_key] - $data_by_date['campaign_spend_by_date'][$date_key];
                       
            // Actual ROAS by date
            $data_by_date['campaign_actual_roas_by_date'][$date_key] = wpd_divide( $data_by_date['campaign_order_profit_by_date'][$date_key], $data_by_date['campaign_spend_by_date'][$date_key], 2 );

            // Cost Per New Customer
            $data_by_date['campaign_cost_per_new_customer_by_date'][$date_key] = wpd_divide( $data_by_date['campaign_spend_by_date'][$date_key], $data_by_date['campaign_new_customer_count_by_date'][$date_key], 2 );
            
            // Cost Per Order Placed
            $data_by_date['campaign_cost_per_order_placed_by_date'][$date_key] = wpd_divide( $data_by_date['campaign_spend_by_date'][$date_key], $data_by_date['campaign_order_count_by_date'][$date_key], 2 );

            // Conversion Rate
            $data_by_date['campaign_conversion_rate_by_date'][$date_key] = wpd_calculate_percentage( $data_by_date['campaign_order_count_by_date'][$date_key], $data_by_date['campaign_clicks_by_date'][$date_key], 2 );

            // Campaign Specific Array
            foreach( $data_by_date['campaign_comparison_actual_profit_by_date'] as $campaign_name => $campaign_date_data ) {

                if ( ! isset($data_by_date['campaign_comparison_campaign_spend_by_date'][$campaign_name]) ) continue;
                if ( isset($data_by_date['campaign_comparison_campaign_spend_by_date'][$campaign_name][$date_key]) ) $data_by_date['campaign_comparison_actual_profit_by_date'][$campaign_name][$date_key] -= $data_by_date['campaign_comparison_campaign_spend_by_date'][$campaign_name][$date_key];

            }

        }

        // Final calculations
        $totals['campaigns_found'] = $filtered_campaign_count;
        $totals['campaign_api_roas'] = wpd_divide( $totals['campaign_api_revenue'], $totals['campaign_spend'] );
        $totals['campaign_api_conversion_rate'] = wpd_calculate_percentage( $totals['campaign_api_conversions'], $totals['campaign_clicks'] );
        $totals['campaign_average_cpc'] = wpd_divide( $totals['campaign_spend'], $totals['campaign_clicks'] );
        $totals['campaign_average_ctr'] = wpd_calculate_percentage( $totals['campaign_clicks'], $totals['campaign_impressions'] );
        $totals['campaign_order_margin'] = wpd_calculate_margin( $totals['campaign_order_profit'], $totals['campaign_order_revenue'] );
        $totals['campaign_order_conversion_rate'] = wpd_calculate_percentage( $totals['campaign_order_count'], $totals['campaign_clicks'] );
        $totals['campaign_revenue_roas'] = wpd_divide( $totals['campaign_order_revenue'], $totals['campaign_spend'] );
        $totals['campaign_adjusted_roas'] = wpd_divide( $totals['campaign_total_profit'], $totals['campaign_spend'] );
        $totals['campaign_adjusted_margin'] = wpd_calculate_margin( $totals['campaign_total_profit'], $totals['campaign_order_revenue'] );
        $totals['campaign_cost_per_order'] = wpd_divide( $totals['campaign_spend'], $totals['campaign_order_count'] );
        $totals['campaign_average_days_active'] = wpd_divide( $totals['campaign_total_days_active'], $totals['campaigns_found'] );
        $totals['campaign_total_profit_per_day'] = wpd_divide( $totals['campaign_total_profit'], $totals['campaign_total_days_active'] );
        $totals['campaign_average_order_value'] = wpd_divide( $totals['campaign_order_revenue'], $totals['campaign_order_count'] );
        $totals['campaign_orders_per_active_campaign_day'] = wpd_divide( $totals['campaign_order_count'], $totals['campaign_total_days_active'] );
        $totals['campaign_orders_per_click'] = wpd_divide( $totals['campaign_order_count'], $totals['campaign_clicks'] );
        $totals['campaign_spend_per_active_day'] = wpd_divide( $totals['campaign_spend'], $totals['campaign_total_days_active'] );
        $categorized_data['customers_by_email_address'] = array_unique( $categorized_data['customers_by_email_address'] ); // New
        $totals['total_customer_count'] = count( $categorized_data['customers_by_email_address'] ); // New
        $totals['cost_per_new_customer'] = wpd_divide( $totals['campaign_spend'], $totals['new_customer_count'] ); // New
        $totals['cost_per_customer'] = wpd_divide( $totals['campaign_spend'], $totals['total_customer_count'] ); // New

        // Create no data found array
        $data_by_date = $this->maybe_create_no_data_found_date_array( $data_by_date );

        // Calculate execution time
        $execution_time = microtime(true) - $start_time + $this->get_data('facebook_campaigns', 'execution_time');

        // Configure return object
        $facebook_campaign_data = array(
            'totals'            => $totals,
            'categorized_data'  => $categorized_data,
            'data_by_date'      => $data_by_date,
            'data_table'        => array(
                'campaigns' => $data_table,
                'orders' => $data_table_orders,
                'products' => $categorized_data['product_data']
            ),
            'total_db_records'  => $total_db_records,
            'execution_time' => $execution_time
        );

        // Store the data into the prop
        $this->set_data( 'facebook_campaigns', $facebook_campaign_data );

        // Return Results
        return $facebook_campaign_data;

    }

    /**
     * 
     *  Fetches Google Campaign Data and organised it for use
     * 
     *  Available Filters: 
     * 
     *      date_from (will filter against _wpd_campaign_start)
     *      date_to (will filter against _wpd_campaign_start)
     *      campaign_id
     *      campaign_date_override This will return data from within the campaign_id's date (requires campaign_id)
     *  
     **/
    public function fetch_google_campaign_data() {

        // Start execution timer
        $start_time = microtime(true);

        $default_product_sales_data = array(
            'product_name' => '',
            'product_sku' => '',
            'product_id' => '',
            'total_product_revenue' => 0,
            'total_product_revenue_excluding_tax' => 0,
            'total_product_cost' => 0,
            'total_product_profit' => 0,
            'total_qty_sold' => 0,
            'total_times_sold' => 0,
            'average_margin' => 0
        );

        // Variables to fill in and pass as data
        $totals = array(
            'campaigns_found' => 0,
            'campaign_spend' => 0,
            'campaign_spend_per_active_day' => 0,
            'campaign_order_count' => 0,
            'campaign_order_revenue' => 0,
            'campaign_order_costs' => 0,
            'campaign_order_profit' => 0,
            'campaign_order_margin' => 0,
            'campaign_order_conversion_rate' => 0,
            'campaign_cost_per_order' => 0,
            'campaign_total_profit' => 0,
            'campaign_average_order_value' => 0,
            'campaign_largest_order_value' => 0,
            'campaign_total_profit_per_day' => 0,
            'campaign_revenue_roas' => 0,
            'campaign_adjusted_roas' => 0,
            'campaign_adjusted_margin' => 0,
            'campaign_orders_per_click' => 0,
            'campaign_orders_per_active_campaign_day' => 0,
            'campaign_api_revenue' => 0,
            'campaign_api_profit' => 0,
            'campaign_api_roas' => 0,
            'campaign_api_conversions' => 0,
            'campaign_api_conversion_rate' => 0,
            'campaign_total_days_active' => 0,
            'campaign_average_days_active' => 0,
            'campaign_impressions' => 0,
            'campaign_clicks' => 0,
            'campaign_average_cpc' => 0,
            'campaign_average_ctr' => 0,
            'new_customer_count' => 0,
            'cost_per_new_customer' => 0,
            'total_customer_count' => 0,
            'cost_per_customer' => 0,
            'all_campaigns_count' => 0,
        );
        $categorized_data = array(
            'customers_by_email_address' => array(),
            'filtered_campaigns' => array(),
            'all_campaigns' => array(),
            'order_ids' => array(),
            'order_data' => array(),
            'product_data' => array()
        );
        $data_table = array();
        $data_table_orders = array();
        $total_db_records = 0;
        $data_by_date = array(
            'campaign_spend_by_date' => $this->get_data_by_date_range_container(),
            'campaign_order_revenue_by_date' => $this->get_data_by_date_range_container(),
            'campaign_order_profit_by_date' => $this->get_data_by_date_range_container(),
            'campaign_order_count_by_date' => $this->get_data_by_date_range_container(),
            'campaign_new_customer_count_by_date' => $this->get_data_by_date_range_container(), // New
            'campaign_cost_per_new_customer_by_date' => $this->get_data_by_date_range_container(), // New
            'campaign_cost_per_order_placed_by_date' => $this->get_data_by_date_range_container(), // New
            'campaign_conversion_rate_by_date' => $this->get_data_by_date_range_container(), // New
            'campaign_clicks_by_date' => $this->get_data_by_date_range_container(), // Unique Per Day
            'campaign_actual_profit_by_date' => $this->get_data_by_date_range_container(), // Unique Per Day
            'campaign_roas_by_date' => $this->get_data_by_date_range_container(),
            'campaign_actual_roas_by_date' => $this->get_data_by_date_range_container(),
            'campaign_comparison_campaign_spend_by_date' => array( 'no_data_available' => $this->get_data_by_date_range_container() ),
            'campaign_comparison_actual_profit_by_date' => array( 'no_data_available' => $this->get_data_by_date_range_container() ),
        );

        // Variables we will use in calculations
        $filter = $this->get_filter();
        $date_from = $this->get_date_from();
        $date_to = $this->get_date_to();
        $unix_date_from = strtotime($date_from . ' 23:59:59' );
        $unix_date_to = strtotime($date_to . ' 23:59:59' );
        $search_by_campaign_id = false; // Null for now
        $date_format = $this->get_filter( 'date_format_string' );
        $store_currency = wpd_get_store_currency();

        // Let's get a list of all campaigns
        if ( WPD_AI_PRO ) {
            $all_campaigns = $this->fetch_all_google_campaign_ids();
            $orders_with_campaign_ids = $this->get_order_ids_with_google_campaign_ids();
        } else {
            $orders_with_campaign_ids = array();
            $all_campaigns = array();
        }

        // Store the campaigns in the categorized_data key
        $categorized_data['all_campaigns'] = $all_campaigns;
        $totals['all_campaigns_count'] = ( is_array($all_campaigns) ) ? count($all_campaigns) : 0;
        $categorized_data['order_data'] = $orders_with_campaign_ids;

        // Run main query
		$campaign_args = array(
			'fields' 			=> 'ids',
		    'post_type' 		=> 'google_ad_campaign',
		    'post_status' 		=> 'publish',
		    'posts_per_page' 	=> -1,
		    'orderby' 			=> 'meta_value',
		    'meta_key' 			=> '_wpd_campaign_start',
		    'order' 			=> 'DESC',
		);

		/**
         *
         *	Setup filter
         *
         */
        if ( $this->get_data_filter( 'google_campaigns', 'campaign' ) && is_array( $this->get_data_filter( 'google_campaigns', 'campaign' ) ) ) {

        	$campaign_args['meta_query'][] = array(
	            'key' 		=> '_wpd_campaign_id',
	            'value' 	=> $this->get_data_filter( 'google_campaigns', 'campaign' ),
	            'compare' => 'IN',
	        );

        }

        // Execute Query
        if ( WPD_AI_PRO ) {
            $query 		= new WP_Query( $campaign_args );
            $campaign_post_ids 	= $query->posts;
        } else {
            $campaign_post_ids = array();
        }

        // Insert 0 in case we have anonymous orders (forces a failure in our get_meta calls)
        $campaign_post_ids[] = 0;

        // Safety check the results
        if ( is_array($campaign_post_ids) && ! empty($campaign_post_ids) ) {

            // Get post count
            $total_db_records = count( $campaign_post_ids );
            
            // Gather Post ID meta
            foreach( $campaign_post_ids as $post_id ) {

                /**
                 *  1. Capture additional Meta and store that as our Raw Data
                 **/
                // Get meta
                $campaign_meta = array();
                $post_meta = get_post_meta( $post_id, '', true );

                // Clean the meta
                if ( is_array($post_meta) && ! empty($post_meta) ) {

                    foreach( $post_meta as $meta_key => $meta_value_array ) {

                        // Unserialize Array
                        if ( $meta_key === '_wpd_campaign_daily_data' ) {
                            $meta_value_array[0] = unserialize( $meta_value_array[0] );
                        }

                        // Store the value in a clean array
                        $campaign_meta[$meta_key] = $meta_value_array[0];

                    }

                }

                /**
                 *  2. Organise the array
                 **/
                $campaign_name                  = $campaign_meta['_wpd_campaign_name'] ?? 'Unknown Campaign';
                $campaign_id                    = $campaign_meta['_wpd_campaign_id'] ?? 'unknown';
                $campaign_status                = $campaign_meta['_wpd_campaign_status'] ?? null;
                $campaign_start                 = $campaign_meta['_wpd_campaign_start'] ?? null;
                $campaign_stop                  = $campaign_meta['_wpd_campaign_stop'] ?? null;
                $campaign_currency              = $campaign_meta['_wpd_campaign_currency'] ?? $store_currency;
                $campaign_ad_account_name       = $campaign_meta['_wpd_campaign_ad_account_name'] ?? null;
                $campaign_ad_account_id         = $campaign_meta['_wpd_campaign_ad_account_id'] ?? null;
                $campaign_last_updated_unix     = $campaign_meta['_wpd_campaign_last_updated_unix'] ?? null;
                $campaign_daily_data            = $campaign_meta['_wpd_campaign_daily_data'] ?? null;

                // These need to be calculated based on daily's
                $campaign_days_active           = 0; // Iteration
                $campaign_spend                 = 0; // Iteration
                $campaign_impressions           = 0; // Iteration
                $campaign_clicks                = 0; // Iteration
                $campaign_conversions           = 0; // Iteration
                $campaign_conversion_value      = 0; // Iteration
                $campaign_average_cpc           = 0; // Calculation
                $campaign_average_ctr           = 0; // Calculation
                $campaign_conversion_rate       = 0; // Calculation
                $campaign_roas                  = 0; // Calculation
                $is_campaign_spend_converted    = false;
                $campaign_spend_unconverted     = 0;

                // If we've converted currency
                if ( $campaign_currency != $store_currency ) $is_campaign_spend_converted = true; 

                // Matching up with store data
                $campaign_order_revenue = 0;
                $campaign_order_costs = 0;
                $campaign_order_profit = 0;
                $campaign_total_profit = 0;
                $campaign_order_count = 0;
                $campaign_new_customer_count = 0;

                /**
                 *  1. Iterate through daily data
                 **/
                if ( is_array($campaign_daily_data) && ! empty($campaign_daily_data)) {

                    foreach( $campaign_daily_data as $date_key => $data ) {

                        $date_key = $this->convert_date_string( $date_key, $date_format );
                        $campaign_daily_data_date_unix = strtotime( $date_key );

                        // Skip order if it's not within the date range we are looking at
                        if ( $campaign_daily_data_date_unix < $unix_date_from || $campaign_daily_data_date_unix > $unix_date_to ) {
                            continue;
                        }

                        // Remove the no data available container
                        if ( isset($data_by_date['campaign_comparison_actual_profit_by_date']['no_data_available']) ) $data_by_date['campaign_comparison_actual_profit_by_date'] = array();
                        if ( isset($data_by_date['campaign_comparison_campaign_spend_by_date']['no_data_available']) ) $data_by_date['campaign_comparison_campaign_spend_by_date'] = array();

                        // Setup campaign specific performance
                        if ( ! isset( $data_by_date['campaign_comparison_actual_profit_by_date'][$campaign_name]) ) $data_by_date['campaign_comparison_actual_profit_by_date'][$campaign_name] = $this->get_data_by_date_range_container();
                        if ( ! isset( $data_by_date['campaign_comparison_campaign_spend_by_date'][$campaign_name]) ) $data_by_date['campaign_comparison_campaign_spend_by_date'][$campaign_name] = $this->get_data_by_date_range_container();

                        // Cost
                        if ( isset($data_by_date['campaign_spend_by_date'][$date_key]) ) $data_by_date['campaign_spend_by_date'][$date_key] += $data['cost'];
    
                        // Clicks
                        if ( isset($data_by_date['campaign_clicks_by_date'][$date_key]) ) $data_by_date['campaign_clicks_by_date'][$date_key] += $data['clicks'];
        
                        // Campaign Specific Ad Spend
                        if ( isset($data_by_date['campaign_comparison_campaign_spend_by_date'][$campaign_name][$date_key]) ) $data_by_date['campaign_comparison_campaign_spend_by_date'][$campaign_name][$date_key] += $data['cost'];

                        $campaign_days_active++; // Iteration
                        $campaign_spend_unconverted     += $data['raw_cost'];
                        $campaign_spend                 += $data['cost']; // Iteration
                        $campaign_impressions           += $data['impressions']; // Iteration
                        $campaign_clicks                += $data['clicks']; // Iteration
                        $campaign_conversions           += $data['conversions']; // Iteration
                        $campaign_conversion_value      += $data['conversion_value']; // Iteration

                    }

                }

                /**
                 *  2. Iterate through order data
                 **/
                if ( isset($categorized_data['order_data'][$campaign_id]) && is_array($categorized_data['order_data'][$campaign_id]) ) {

                    // Loop through order data from campaign
                    foreach( $categorized_data['order_data'][$campaign_id] as $order_id => $order_data ) {

                        // Add our date key for the report
                        $order_date_unix = $order_data['date_created'];
                        $date_key = date( $date_format, $order_date_unix );

                        // Skip order if it's not within the date range we are looking at
                        if ( $order_date_unix < $unix_date_from || $order_date_unix > $unix_date_to ) {
                            continue;
                        }

                        // Add order ID to totals
                        if ( ! in_array($order_id, $categorized_data['order_ids']) ) $categorized_data['order_ids'][] = $order_id;

                        // Orders within period
                        $campaign_order_count++;
                        $data_table_orders[$order_id] = $order_data;

                        // Build campaign specific totals
                        $campaign_order_revenue += $order_data['total_order_revenue'];
                        $campaign_order_costs += $order_data['total_order_cost'];
                        $campaign_order_profit += $order_data['total_order_profit'];

                        // New Customers Count
                        if ( $order_data['new_returning_customer'] == 'new' ) {
                            $campaign_new_customer_count++;
                            if ( isset($data_by_date['campaign_new_customer_count_by_date'][$date_key]) ) $data_by_date['campaign_new_customer_count_by_date'][$date_key]++;
                        } 

                        // Log all Customers
                        $categorized_data['customers_by_email_address'][] = $order_data['billing_email'];

                        // Remove the no data available container
                        if ( isset($data_by_date['campaign_comparison_campaign_spend_by_date']['no_data_available']) ) $data_by_date['campaign_comparison_campaign_spend_by_date'] = array();

                        // Setup default container for campaign comparisons
                        if (! isset($data_by_date['campaign_comparison_actual_profit_by_date'][$campaign_name]) ) $data_by_date['campaign_comparison_actual_profit_by_date'][$campaign_name] = $this->get_data_by_date_range_container();

                        // Add data to daily's
                        if ( isset($data_by_date['campaign_order_revenue_by_date'][$date_key]) ) $data_by_date['campaign_order_revenue_by_date'][$date_key] += $order_data['total_order_revenue'];
                        if ( isset($data_by_date['campaign_order_profit_by_date'][$date_key]) ) $data_by_date['campaign_order_profit_by_date'][$date_key] += $order_data['total_order_profit'];
                        if ( isset($data_by_date['campaign_order_count_by_date'][$date_key]) ) $data_by_date['campaign_order_count_by_date'][$date_key]++;
                        if ( isset($data_by_date['campaign_comparison_actual_profit_by_date'][$campaign_name][$date_key]) ) $data_by_date['campaign_comparison_actual_profit_by_date'][$campaign_name][$date_key] += $order_data['total_order_profit'];
                        
                        // Product Sales Data
                        if (is_array($order_data['product_data']) && ! empty($order_data['product_data'])) {

                            foreach($order_data['product_data'] as $product_id => $product_data) {

                                // Use product name as key
                                $product_name = ($product_data['product_name']) ? $product_data['product_name'] : 'Unknown Product';
                                
                                // Load Default Product Sales Data
                                if ( ! isset($categorized_data['product_data'][$product_name]) ) $categorized_data['product_data'][$product_name] = $default_product_sales_data;

                                // Add Product Data
                                $categorized_data['product_data'][$product_name]['product_name'] = $product_data['product_name'];
                                $categorized_data['product_data'][$product_name]['product_sku'] = $product_data['sku'];
                                $categorized_data['product_data'][$product_name]['product_id'] = $product_data['product_id'];
                                $categorized_data['product_data'][$product_name]['total_product_revenue'] += $product_data['product_revenue'];
                                $categorized_data['product_data'][$product_name]['total_product_revenue_excluding_tax'] += $product_data['product_revenue_excluding_tax'];
                                $categorized_data['product_data'][$product_name]['total_product_cost'] += $product_data['total_cost_of_goods'];
                                $categorized_data['product_data'][$product_name]['total_product_profit'] += $product_data['total_profit'];
                                $categorized_data['product_data'][$product_name]['total_qty_sold'] += $product_data['qty_sold'];
                                $categorized_data['product_data'][$product_name]['total_times_sold']++;
                                $categorized_data['product_data'][$product_name]['average_margin'] = wpd_calculate_margin( $categorized_data['product_data'][$product_name]['total_product_profit'], $categorized_data['product_data'][$product_name]['total_product_revenue_excluding_tax'] );

                            }

                        }

                    }

                }

                // If we have no spend or revenue, assume it's not active
                if ( $campaign_spend === 0 && $campaign_order_revenue === 0 ) {
                    continue;
                }

                // Campaign Totals - API Values
                $campaign_average_cpc           = wpd_divide( $campaign_spend, $campaign_clicks ); // Calculation
                $campaign_average_ctr           = wpd_calculate_percentage( $campaign_clicks, $campaign_impressions ); // Calculation
                $campaign_conversion_rate       = wpd_calculate_percentage( $campaign_conversions, $campaign_clicks ); // Calculation
                $campaign_roas                  = wpd_divide( $campaign_conversion_value, $campaign_spend ); // Calculation

                // Campaign Totals - Store Calculated Values
                $campaign_total_profit          = $campaign_order_profit - $campaign_spend;
                $campaign_order_conversion_rate = wpd_calculate_percentage( $campaign_order_count, $campaign_clicks );
                $campaign_order_revenue_roas    = wpd_divide( $campaign_order_revenue, $campaign_spend );
                $campaign_adjusted_roas         = wpd_divide( $campaign_total_profit, $campaign_spend );
                $campaign_adjusted_margin       = wpd_calculate_margin( $campaign_total_profit, $campaign_order_revenue );
                $campaign_cost_per_order        = wpd_divide( $campaign_order_count, $campaign_spend );
                $campaign_cost_per_new_customer = wpd_divide( $campaign_new_customer_count, $campaign_spend );

                // Setup Organised Arrays
                $data_table[$campaign_name] = array(
                    'post_id' => $post_id,
                    'campaign_last_updated_unix' => $campaign_last_updated_unix,
                    'campaign_name' => $campaign_name,
                    'campaign_id' => $campaign_id,
                    'campaign_ad_account_name' => $campaign_ad_account_name,
                    'campaign_ad_account_id' => $campaign_ad_account_id,
                    'campaign_start' => $campaign_start,
                    'campaign_stop' => $campaign_stop,
                    'campaign_status' => $campaign_status,
                    'campaign_days_active' => $campaign_days_active,
                    'campaign_currency' => $campaign_currency,
                    'campaign_converted' => $is_campaign_spend_converted,
                    'campaign_spend_unconverted' => $campaign_spend_unconverted,
                    'campaign_spend' => $campaign_spend,
                    'campaign_impressions' => $campaign_impressions,
                    'campaign_clicks' => $campaign_clicks,
                    'campaign_api_conversions' => $campaign_conversions,
                    'campaign_api_conversion_value' => $campaign_conversion_value,
                    'campaign_api_conversion_rate' => $campaign_conversion_rate,
                    'campaign_api_roas' => $campaign_roas,
                    'campaign_average_cpc' => $campaign_average_cpc,
                    'campaign_average_ctr' => $campaign_average_ctr,
                    'campaign_order_revenue' => $campaign_order_revenue,
                    'campaign_order_costs' => $campaign_order_costs,
                    'campaign_order_profit' => $campaign_order_profit,
                    'campaign_order_count' => $campaign_order_count,
                    'campaign_order_conversion_rate' => $campaign_order_conversion_rate,
                    'campaign_cost_per_order' => $campaign_cost_per_order,
                    'campaign_total_profit' => $campaign_total_profit,
                    'campaign_revenue_roas' => $campaign_order_revenue_roas,
                    'campaign_adjusted_roas' => $campaign_adjusted_roas,
                    'campaign_adjusted_margin' => $campaign_adjusted_margin,
                    'campaign_new_customer_count' => $campaign_new_customer_count,
                    'campaing_cost_per_new_customer' => $campaign_cost_per_new_customer
                );

                /**
                 *  4. Setup the totals
                 **/
                // Add filtered campaigns to data
                $categorized_data['filtered_campaigns'][$campaign_id] = $campaign_name;
                $totals['campaigns_found'] = count( $data_table );
                $totals['campaign_spend'] += $campaign_spend;
                $totals['campaign_api_revenue'] += $campaign_conversion_value;
                $totals['campaign_api_profit'] += ( $campaign_conversion_value - $campaign_spend );
                $totals['campaign_api_conversions'] += $campaign_conversions;
                $totals['campaign_total_days_active'] += $campaign_days_active;
                $totals['campaign_impressions'] += $campaign_impressions;
                $totals['campaign_clicks'] += $campaign_clicks;
                $totals['campaign_average_cpc'] += $campaign_average_cpc;
                $totals['campaign_average_ctr'] += $campaign_clicks;
                $totals['campaign_order_revenue'] += $campaign_order_revenue;
                $totals['campaign_order_costs'] += $campaign_order_costs;
                $totals['campaign_order_profit'] += $campaign_order_profit;
                $totals['campaign_order_count'] += $campaign_order_count;
                $totals['campaign_total_profit'] += $campaign_total_profit;
                $totals['new_customer_count'] += $campaign_new_customer_count;

            }

        }

        // Do some calculated dailys
        foreach( $this->get_data_by_date_range_container() as $date_key => $empty_data ) {

            // Actual Profit By Date
            $data_by_date['campaign_actual_profit_by_date'][$date_key] = $data_by_date['campaign_order_profit_by_date'][$date_key] - $data_by_date['campaign_spend_by_date'][$date_key];
           
            // Campaign ROAS By Date
            $data_by_date['campaign_roas_by_date'][$date_key] = wpd_divide( $data_by_date['campaign_order_revenue_by_date'][$date_key], $data_by_date['campaign_spend_by_date'][$date_key], 2 );
            
            // Actual ROAS by date
            $data_by_date['campaign_actual_roas_by_date'][$date_key] = wpd_divide( $data_by_date['campaign_order_profit_by_date'][$date_key], $data_by_date['campaign_spend_by_date'][$date_key], 2 );

            // Cost Per New Customer
            $data_by_date['campaign_cost_per_new_customer_by_date'][$date_key] = wpd_divide( $data_by_date['campaign_spend_by_date'][$date_key], $data_by_date['campaign_new_customer_count_by_date'][$date_key], 2 );

            // Cost Per Order Placed
            $data_by_date['campaign_cost_per_order_placed_by_date'][$date_key] = wpd_divide( $data_by_date['campaign_spend_by_date'][$date_key], $data_by_date['campaign_order_count_by_date'][$date_key], 2 );

            // Conversion Rate
            $data_by_date['campaign_conversion_rate_by_date'][$date_key] = wpd_calculate_percentage( $data_by_date['campaign_order_count_by_date'][$date_key], $data_by_date['campaign_clicks_by_date'][$date_key], 2 );

            // Campaign Specific Array
            foreach( $data_by_date['campaign_comparison_actual_profit_by_date'] as $campaign_name => $campaign_date_data ) {

                if ( !isset($data_by_date['campaign_comparison_actual_profit_by_date'][$campaign_name][$date_key]) ) continue;
                if ( isset($data_by_date['campaign_comparison_campaign_spend_by_date'][$campaign_name][$date_key]) ) $data_by_date['campaign_comparison_actual_profit_by_date'][$campaign_name][$date_key] -= $data_by_date['campaign_comparison_campaign_spend_by_date'][$campaign_name][$date_key];

            }

        }

        // Final calculations
        $totals['campaign_api_roas'] = wpd_divide( $totals['campaign_api_revenue'], $totals['campaign_spend'] );
        $totals['campaign_api_conversion_rate'] = wpd_calculate_percentage( $totals['campaign_api_conversions'], $totals['campaign_clicks'] );
        $totals['campaign_average_cpc'] = wpd_divide( $totals['campaign_spend'], $totals['campaign_clicks'] );
        $totals['campaign_average_ctr'] = wpd_calculate_percentage( $totals['campaign_clicks'], $totals['campaign_impressions'] );
        $totals['campaign_order_margin'] = wpd_calculate_margin( $totals['campaign_order_profit'], $totals['campaign_order_revenue'] );
        $totals['campaign_order_conversion_rate'] = wpd_calculate_percentage( $totals['campaign_order_count'], $totals['campaign_clicks'] );
        $totals['campaign_revenue_roas'] = wpd_divide( $totals['campaign_order_revenue'], $totals['campaign_spend'] );
        $totals['campaign_adjusted_roas'] = wpd_divide( $totals['campaign_total_profit'], $totals['campaign_spend'] );
        $totals['campaign_adjusted_margin'] = wpd_calculate_margin( $totals['campaign_total_profit'], $totals['campaign_order_revenue'] );
        $totals['campaign_cost_per_order'] = wpd_divide( $totals['campaign_spend'], $totals['campaign_order_count'] );
        $totals['campaign_average_days_active'] = wpd_divide( $totals['campaign_total_days_active'], $totals['campaigns_found'] );
        $totals['campaign_total_profit_per_day'] = wpd_divide( $totals['campaign_total_profit'], $totals['campaign_total_days_active'] );
        $totals['campaign_average_order_value'] = wpd_divide( $totals['campaign_order_revenue'], $totals['campaign_order_count'] );
        $totals['campaign_orders_per_active_campaign_day'] = wpd_divide( $totals['campaign_order_count'], $totals['campaign_total_days_active'] );
        $totals['campaign_orders_per_click'] = wpd_divide( $totals['campaign_order_count'], $totals['campaign_clicks'] );
        $totals['campaign_spend_per_active_day'] = wpd_divide( $totals['campaign_spend'], $totals['campaign_total_days_active'] );
        $categorized_data['customers_by_email_address'] = array_unique( $categorized_data['customers_by_email_address'] ); // New
        $totals['total_customer_count'] = count( $categorized_data['customers_by_email_address'] ); // New
        $totals['cost_per_new_customer'] = wpd_divide( $totals['campaign_spend'], $totals['new_customer_count'] ); // New
        $totals['cost_per_customer'] = wpd_divide( $totals['campaign_spend'], $totals['total_customer_count'] ); // New

        // Create no data found array
        $data_by_date = $this->maybe_create_no_data_found_date_array( $data_by_date );

        // Calculate execution time
        $execution_time = microtime(true) - $start_time + $this->get_data('google_campaigns', 'execution_time');

        // Configure return object
        $google_campaign_data = array(
            'totals' => $totals,
            'categorized_data' => $categorized_data,
            'data_by_date' => $data_by_date,
            'data_table' => array(
                'campaigns' => $data_table,
                'orders' => $data_table_orders,
                'products' => $categorized_data['product_data']
            ),
            'total_db_records' => $total_db_records,
            'execution_time' => $execution_time
        );

        // Store the data into the prop
        $this->set_data( 'google_campaigns', $google_campaign_data );

        // Return Results
        return $google_campaign_data;

    }

    /**
     * 
     *  Fetches all Google Campaign ID's and returns an associative array with id => name structure
     * 
     *  @return array Associative array with the Campaign ID as the key and the campaign name as the value
     * 
     **/
    public function fetch_all_google_campaign_ids() {

        // Return result
        $all_campaigns = array();

        // Query Args
		$query_args = array(

			'fields' 			=> 'ids',
		    'post_type' 		=> 'google_ad_campaign',
		    'post_status' 		=> 'publish',
		    'posts_per_page' 	=> -1,
		    'orderby' 			=> 'meta_value',
		    'meta_key' 			=> '_wpd_campaign_start',
		    'order' 			=> 'DESC',
		);

        // Execute Query
		$query 		= new WP_Query( $query_args );
        $post_ids 	= $query->posts;

        // Loop through the found Post ID's
        foreach( $post_ids as $post_id ) {

            // Get the relevant meta data
        	$campaign_id 		= get_post_meta( $post_id, '_wpd_campaign_id', true );
        	$campaign_name 		= get_post_meta( $post_id, '_wpd_campaign_name', true );

            // Store the results
        	$all_campaigns[$campaign_id] = $campaign_name;

        }

        // Store this anon query
        $this->set_data( 'anonymous_queries', array( 'all_google_campaigns' => $all_campaigns ) );

        // Return results
        return $all_campaigns;

    }

    /**
     * 
     *  Fetches all Meta Campaign ID's and returns an associative array with id => name structure
     * 
     *  @return array Associative array with the Campaign ID as the key and the campaign name as the value
     * 
     **/
    public function fetch_all_meta_campaign_ids() {

        // Return result
        $all_campaigns = array();

        // Query Args
		$query_args = array(

			'fields' 			=> 'ids',
		    'post_type' 		=> 'facebook_campaign',
		    'post_status' 		=> 'publish',
		    'posts_per_page' 	=> -1,
		    'orderby' 			=> 'meta_value',
		    'meta_key' 			=> '_wpd_campaign_start',
		    'order' 			=> 'DESC',
		);

        // Execute Query
		$query 		= new WP_Query( $query_args );
        $post_ids 	= $query->posts;

        // Loop through the found Post ID's
        foreach( $post_ids as $post_id ) {

            // Get the relevant meta data
        	$campaign_id 		= get_post_meta( $post_id, '_wpd_campaign_id', true );
        	$campaign_name 		= get_post_meta( $post_id, '_wpd_campaign_name', true );

            // Store the results
        	$all_campaigns[$campaign_id] = $campaign_name;

        }

        // Store this anon query
        $this->set_data( 'anonymous_queries', array( 'all_facebook_campaigns' => $all_campaigns ) );

        // Return results
        return $all_campaigns;

    }

    /**
     * Fast replacement for get_date_from_gmt().
     * Converts a GMT date string to site local time with caching.
     *
     * @param string $date_gmt Date string in GMT (Y-m-d H:i:s).
     * @param string $format   Return format. Default 'Y-m-d H:i:s'.
     *
     * @return string Local time formatted string.
     */
    private function get_date_from_gmt( $date_gmt, $format = 'Y-m-d H:i:s' ) {

        static $gmt_date_cache = [];
        static $site_timezone = null;

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
     * Get the where clause for the analytics data query
     * 
     * @return string The where clause for the analytics data query
     */
    private function get_analytics_where_clause() {

        global $wpdb;
        $wpd_db                         = new WPD_Database_Interactor();
        $woo_events_table               = $wpd_db->events_table;
        $session_data_table             = $wpd_db->session_data_table;
        $filters                        = $this->get_filter();
        $session_id_filter              = $this->get_data_filter('website_traffic', 'session_id');
        $event_type_filter              = $this->get_data_filter('website_traffic', 'event_type');
        $session_contains_event_filter  = $this->get_data_filter('website_traffic', 'session_contains_event');
        $device_type_filter             = $this->get_data_filter('website_traffic', 'device_type');
        $event_page_url_filter          = $this->get_data_filter('website_traffic', 'page_href_contains');
        $referral_url_contains_filter   = $this->get_data_filter('website_traffic', 'referral_url_contains');
        $user_id_filter                 = $this->get_data_filter('website_traffic', 'user_id');
        $ip_address_filter              = $this->get_data_filter('website_traffic', 'ip_address');
        $product_id_filter              = $this->get_data_filter('website_traffic', 'product_id');
        $query_parameter_values_filter  = $this->get_data_filter('website_traffic', 'query_parameter_values');
        $where_clause = '';

        // Check for all time filter
        if ( isset($filters['date_preset']) && $filters['date_preset'] === 'all_time' ) {
            if ( isset($filters['date_from']) ) unset($filters['date_from']);
            if ( isset($filters['date_to']) ) unset($filters['date_to']);
        }

        // Filter Events By Date
        if ( isset($filters['date_from']) && isset($filters['date_to']) ) {
            // Ensure dates are in Y-m-d H:i:s format for proper timezone handling
            // If only date is provided (Y-m-d), add time component
            $date_from_local = $filters['date_from'];
            $date_to_local = $filters['date_to'];
            
            // If date_from doesn't have time, assume start of day (00:00:00)
            if ( strlen( $date_from_local ) === 10 ) {
                $date_from_local .= ' 00:00:00';
            }
            
            // If date_to doesn't have time, use start of NEXT day (00:00:00) to capture full day
            // This ensures we get all events up to 23:59:59 of the specified day in local time
            if ( strlen( $date_to_local ) === 10 ) {
                // Parse the date and add 1 day in local timezone
                $date_to_datetime_local = new DateTime( $date_to_local . ' 00:00:00', wp_timezone() );
                $date_to_datetime_local->modify( '+1 day' );
                $date_to_local = $date_to_datetime_local->format( 'Y-m-d H:i:s' );
            }
            
            // Convert local times to GMT for querying (data is stored in GMT)
            $date_from_gmt = get_gmt_from_date( $date_from_local );
            $date_to_gmt = get_gmt_from_date( $date_to_local );

            // Use prepared statements for safety
            $where_clause .= $wpdb->prepare( ' AND date_created_gmt >= %s', $date_from_gmt );
            $where_clause .= $wpdb->prepare( ' AND date_created_gmt < %s', $date_to_gmt ); // Use < instead of <= since date_to is start of next day
        }

        // Filter Events By Event Type
        if ( $event_type_filter  ) {

            $escaped_values = array_map( fn($val) => $wpdb->prepare('%s', $val), $event_type_filter );
            $in_clause = implode( ',', $escaped_values );

            $where_clause .= ' AND event_type IN (' . $in_clause . ')';
        }

        // Filter Events By Product ID
        if ( $product_id_filter  ) {

            $escaped_values = array_map( fn($val) => $wpdb->prepare('%d', $val), $product_id_filter );
            $in_clause = implode( ',', $escaped_values );

            $where_clause .= ' AND product_id IN (' . $in_clause . ')';
        }

        // Filter Sessions By IP Address
        if ( $ip_address_filter  ) {
    
            $escaped_values = array_map( fn($val) => $wpdb->prepare('%s', $val), $ip_address_filter );
            $in_clause = implode( ',', $escaped_values );

            $where_clause .= " AND session_id IN (
                SELECT DISTINCT session_id
                FROM $session_data_table
                WHERE ip_address IN ($in_clause)
            )";
        }

        // Filter Sessions By User ID
        if ( $user_id_filter  ) {

            $escaped_values = array_map( fn($val) => $wpdb->prepare('%d', $val), $user_id_filter );
            $in_clause = implode( ',', $escaped_values );

            $where_clause .= " AND session_id IN (
                SELECT DISTINCT session_id
                FROM $session_data_table
                WHERE user_id IN ($in_clause)
            )";
        }
        
        // Filter Sessions By Device Type
        if ( $device_type_filter ) {

            $escaped_values = array_map( fn($val) => $wpdb->prepare('%s', $val), $device_type_filter );
            $in_clause = implode( ',', $escaped_values );

            $where_clause .= " AND session_id IN (
                SELECT DISTINCT session_id
                FROM $session_data_table
                WHERE device_category IN ($in_clause)
            )";

        }

        // Filter Sessions By Session ID
        if ( $session_id_filter  ) {

            $escaped_values = array_map( fn($val) => $wpdb->prepare('%s', $val), $session_id_filter );
            $in_clause = implode( ',', $escaped_values );

            $where_clause .= ' AND session_id IN (' . $in_clause . ')';
        }

        // Filter Events By Query Parameter Key-Value Pairs
        if ( $query_parameter_values_filter && is_array($query_parameter_values_filter) ) {
            
            $like_conditions = [];
            
            foreach ( $query_parameter_values_filter as $filter_pair ) {
                // Ensure filter pair has both key and value
                if ( ! isset($filter_pair['key']) || ! isset($filter_pair['value']) ) continue;
                
                // Urlencode replaces spaces and +s with encoded version, to match what's in the DB
                $filter_key = urlencode($filter_pair['key']);
                $filter_value = urlencode($filter_pair['value']);
                
                // The landing_page field stores encoded URLs
                $search_pattern = $filter_key . '=' . $filter_value;
                
                // Add LIKE condition for this key-value pair
                $like_conditions[] = $wpdb->prepare( 
                    "landing_page LIKE %s", 
                    '%' . $wpdb->esc_like( $search_pattern ) . '%' 
                );
            }
            
            // If we have any conditions, add them to the WHERE clause with OR logic
            if ( ! empty( $like_conditions ) ) {
                $where_clause .= " AND session_id IN (
                    SELECT DISTINCT session_id
                    FROM $session_data_table
                    WHERE " . implode( ' OR ', $like_conditions ) . "
                )";
            }
        }

        // Filter Events By Event Page HREF Containing
        if ( $event_page_url_filter ) {

            $like_clauses = array_map( function( $value ) use ( $wpdb ) {
                // Escape and safely format each LIKE comparison
                return $wpdb->prepare( "page_href LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' );
            }, $event_page_url_filter );
        
            $where_clause .= ' AND (' . implode( ' OR ', $like_clauses ) . ')';
        }

        // Filter Sessions By Referral URL Containing
        if ( $referral_url_contains_filter ) {

            $like_clauses = array_map( function( $value ) use ( $wpdb ) {
                // Escape and safely format each LIKE comparison
                return $wpdb->prepare( "referral_url LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' );
            }, $referral_url_contains_filter );
        
            $where_clause .= " AND session_id IN (
                SELECT DISTINCT session_id
                FROM $session_data_table
                WHERE (" . implode( ' OR ', $like_clauses ) . ")
            )";
        }

        // Filter Events By Sessions That Contain Particular Events
        if ( $session_contains_event_filter ) {

            $subqueries = [];

            // --- Handle normal event types ---
            $normal_events = array_filter(
                $session_contains_event_filter,
                fn($event) => $event !== 'product_page_view'
            );

            if ( ! empty( $normal_events ) ) {
                // Escape each value properly
                $escaped_values = array_map( fn($val) => $wpdb->prepare('%s', $val), $normal_events );
                $in_clause = implode( ',', $escaped_values );

                $subqueries[] = "
                    SELECT DISTINCT session_id
                    FROM $woo_events_table
                    WHERE event_type IN ($in_clause)
                ";
            }

            // --- Handle the special case for product_page_view ---
            if ( in_array( 'product_page_view', $session_contains_event_filter, true ) ) {
                $subqueries[] = "
                    SELECT DISTINCT session_id
                    FROM $woo_events_table
                    WHERE event_type = 'page_view'
                    AND object_type = 'product'
                ";
            }

            // --- Combine the subqueries with UNION to merge all matching sessions ---
            if ( ! empty( $subqueries ) ) {
                $where_clause .= ' AND session_id IN (
                    ' . implode( ' UNION ', $subqueries ) . '
                )';
            }
        }

        // Return the where clause
        return $where_clause;

    }

    /**
     * Get the total count of analytics records for the current filters
     * 
     * @return int|false Total count of records or false on error
     */
    public function get_analytics_event_count() {

        global $wpdb;

        $wpd_db             = new WPD_Database_Interactor();
        $woo_events_table   = $wpd_db->events_table;
        $where_clause       = $this->get_analytics_where_clause();
        $count_sql_query    = "SELECT COUNT(*) FROM $woo_events_table AS events WHERE 1=1 $where_clause";
        $total_count        = (int) $wpdb->get_var( $count_sql_query );
        
        if ( $wpdb->last_error ) {
            wpd_write_log( 'Error getting analytics event count from DB, dumping the error and query.', 'db_error' );
            wpd_write_log( $wpdb->last_error, 'db_error' );
            wpd_write_log( $wpdb->last_query, 'db_error' );
            return false;
        }

        return $total_count;
    }

    /**
     * Get the total count of sessions for the current filters
     * 
     * Counts distinct sessions from the events table that match the filters.
     * This aligns with the event query logic - we search events first, then enrich with session data.
     * 
     * Uses get_analytics_where_clause() to ensure consistency with event filtering logic.
     * 
     * @return int|false Total count of sessions or false on error
     */
    public function get_analytics_session_count() {

        global $wpdb;

        $wpd_db             = new WPD_Database_Interactor();
        $woo_events_table   = $wpd_db->events_table;
        $where_clause       = $this->get_analytics_where_clause();
        $count_sql_query    = "SELECT COUNT(DISTINCT session_id) FROM $woo_events_table WHERE 1=1 $where_clause";
        $total_count        = (int) $wpdb->get_var( $count_sql_query );
        
        if ( $wpdb->last_error ) {
            wpd_write_log( 'Error getting analytics session count from DB, dumping the error and query.', 'db_error' );
            wpd_write_log( $wpdb->last_error, 'db_error' );
            wpd_write_log( $wpdb->last_query, 'db_error' );
            return false;
        }

        return $total_count;

    }

    
    /**
     * Query analytics data with limit and offset for batching
     * 
     * @param array $raw_analytics_data Reference to store event data
     * @param array $session_data_map Reference to store session data map
     * @param int $limit Number of records to fetch
     * @param int $offset Starting offset
     * @return bool Success status
     */
    private function query_analytics_data( &$raw_analytics_data, &$session_data_map, $limit, $offset ) {

        global $wpdb;
        $wpd_db                         = new WPD_Database_Interactor();
        $woo_events_table               = $wpd_db->events_table;
        $session_data_table             = $wpd_db->session_data_table;
        $where_clause                   = $this->get_analytics_where_clause();
        
        // Sanitize limit and offset as integers for safety
        $limit = absint( $limit );
        $offset = absint( $offset );

        // Fetch Events With Limit, Offset & Filters
        // Note: Table names are trusted (from WPD_Database_Interactor), where_clause uses $wpdb->prepare() internally
        $events_sql_query = 
            "SELECT 
            session_id,
            date_created_gmt,
            event_type, 
            event_quantity,
            event_value,
            product_id,
            variation_id,
            page_href, 
            object_type, 
            additional_data
            FROM $woo_events_table
            WHERE 1=1
            $where_clause
            ORDER BY date_created_gmt ASC
            LIMIT %d OFFSET %d";

        $events_sql_query = $wpdb->prepare( $events_sql_query, $limit, $offset );
        $raw_analytics_data = $wpdb->get_results( $events_sql_query, 'ARRAY_A' );

        if ( $wpdb->last_error ) {
            wpd_write_log( 'Error capturing analytics data from DB, dumping the error and query.', 'db_error' );
            wpd_write_log( $wpdb->last_error, 'db_error' );
            wpd_write_log( $wpdb->last_query, 'db_error' );
            return false;
        }

        // Get distinct session IDs from the events data
        $session_ids = array();
        if ( is_array($raw_analytics_data) && !empty($raw_analytics_data) ) {
            $session_ids = array_unique(array_column($raw_analytics_data, 'session_id'));
            $session_ids = array_filter($session_ids); // Remove empty/null session IDs
            // Normalize session_ids: trim whitespace and ensure they're strings
            $session_ids = array_map(function($id) {
                return trim((string)$id);
            }, $session_ids);
            $session_ids = array_filter($session_ids); // Remove empty after trimming
            $current_db_records = $this->get_data( 'analytics', 'total_db_records' );
            $this->set_data( 'analytics', array( 'total_db_records' => $current_db_records + count($raw_analytics_data) ) );
        }

        // Fetch session data for the distinct session IDs
        // Don't reset if map already has data (preserve existing session data from previous batches)
        if ( ! is_array( $session_data_map ) ) {
            $session_data_map = array();
        }
        
        if ( !empty($session_ids) ) {

            // Chunk session_ids to avoid MySQL IN clause limits (max_allowed_packet, query size limits)
            // Large batches can cause queries to fail silently or exceed packet size
            // Reduced from 500 to 250 to handle long session IDs and avoid query size limits
            // Calculate safe chunk size based on average session ID length
            $avg_session_id_length = 0;
            if ( !empty($session_ids) ) {
                $total_length = array_sum(array_map('strlen', $session_ids));
                $avg_session_id_length = $total_length / count($session_ids);
            }
            
            // Adjust chunk size based on session ID length
            // Longer session IDs = smaller chunks to avoid max_allowed_packet limits
            // Estimate: each session ID in IN clause adds ~50-60 bytes (with quotes, commas, etc.)
            // Default max_allowed_packet is often 16MB, but we want to stay well under
            // For safety, limit query size to ~1MB per chunk (allowing for query overhead)
            $base_chunk_size = 250; // Reduced from 500
            if ( $avg_session_id_length > 50 ) {
                // Very long session IDs - use smaller chunks
                $base_chunk_size = 100;
            } elseif ( $avg_session_id_length > 40 ) {
                // Medium-long session IDs
                $base_chunk_size = 150;
            }
            
            $session_id_chunk_size = apply_filters( 'wpd_ai_session_data_chunk_size', $base_chunk_size );
            $session_id_chunks = array_chunk( $session_ids, $session_id_chunk_size );
            $all_session_data_results = array();
            $all_found_session_ids = array();
            $chunks_processed = 0;
            $chunks_failed = 0;
            $chunks_empty = 0;
            
            // Process each chunk separately
            foreach ( $session_id_chunks as $chunk_index => $session_ids_chunk ) {
                
                // Calculate estimated query size for this chunk
                $estimated_query_size = strlen($session_data_table) + 200; // Base query size
                foreach ( $session_ids_chunk as $sid ) {
                    $estimated_query_size += strlen($sid) + 10; // Each ID + quotes, commas, etc.
                }
                
                // Fetch session data for this chunk
                // Note: Session IDs are normalized (trimmed) in PHP before query and after retrieval
                // This handles potential whitespace differences between events and session_data tables
                $session_ids_placeholder = implode(',', array_fill(0, count($session_ids_chunk), '%s'));
                
                $session_sql_query = $wpdb->prepare(
                    "SELECT 
                    session_id,
                    user_id, 
                    landing_page,
                    referral_url,
                    date_created_gmt,
                    date_updated_gmt,
                    device_category,
                    ip_address
                    FROM $session_data_table 
                    WHERE session_id IN ($session_ids_placeholder)",
                    $session_ids_chunk
                );

                // Check if prepare() succeeded (it can fail silently)
                if ( $session_sql_query === false ) {
                    wpd_write_log( 
                        sprintf( 
                            'ERROR: wpdb->prepare() failed for chunk %d/%d (%d session_ids, ~%d bytes). This may indicate query size limit exceeded.', 
                            $chunk_index + 1,
                            count($session_id_chunks),
                            count($session_ids_chunk),
                            $estimated_query_size
                        ), 
                        'db_error' 
                    );
                    $chunks_failed++;
                    continue;
                }

                $chunk_results = $wpdb->get_results( $session_sql_query, 'ARRAY_A' );

                // Log db error
                if ( $wpdb->last_error ) {
                    wpd_write_log( 
                        sprintf( 
                            'ERROR: Database error capturing session data (chunk %d/%d, %d session_ids, ~%d bytes). Error: %s', 
                            $chunk_index + 1,
                            count($session_id_chunks),
                            count($session_ids_chunk),
                            $estimated_query_size,
                            $wpdb->last_error
                        ), 
                        'db_error' 
                    );
                    wpd_write_log( 'Query: ' . substr($wpdb->last_query, 0, 500) . '...', 'db_error' );
                    $chunks_failed++;
                    // Continue with other chunks even if one fails
                    continue;
                }

                // Check if query returned results (even if empty, it should be an array)
                if ( ! is_array($chunk_results) ) {
                    wpd_write_log( 
                        sprintf( 
                            'WARNING: Query returned non-array result for chunk %d/%d (%d session_ids). Result type: %s', 
                            $chunk_index + 1,
                            count($session_id_chunks),
                            count($session_ids_chunk),
                            gettype($chunk_results)
                        ), 
                        'db_error' 
                    );
                    $chunks_failed++;
                    continue;
                }

                // Track chunk processing
                if ( empty($chunk_results) ) {
                    $chunks_empty++;
                } else {
                    $chunks_processed++;
                }

                // Merge chunk results
                if ( !empty($chunk_results) ) {
                    $all_session_data_results = array_merge( $all_session_data_results, $chunk_results );
                    
                    // Track found session_ids from this chunk
                    foreach ( $chunk_results as $session_row ) {
                        $normalized_session_id = trim((string)$session_row['session_id']);
                        $all_found_session_ids[] = $normalized_session_id;
                    }
                }
            }
            
            // Log chunk processing summary
            if ( $chunks_failed > 0 || $chunks_empty > 0 ) {
                wpd_write_log( 
                    sprintf( 
                        'Session data chunk processing summary: %d total chunks, %d processed successfully, %d returned empty, %d failed. Chunk size: %d, Avg session ID length: %.1f chars', 
                        count($session_id_chunks),
                        $chunks_processed,
                        $chunks_empty,
                        $chunks_failed,
                        $session_id_chunk_size,
                        $avg_session_id_length
                    ), 
                    'session_data_missing' 
                );
            }

            // Create a map of session_id => session_data for quick lookup
            // Normalize session_ids when creating the map (trim whitespace)
            if ( !empty($all_session_data_results) ) {
                foreach ( $all_session_data_results as $session_row ) {
                    // Normalize session_id by trimming
                    $normalized_session_id = trim((string)$session_row['session_id']);
                    $session_data_map[$normalized_session_id] = $session_row;
                }
                
                // Diagnostic logging: track missing session data
                $missing_session_ids = array_diff($session_ids, $all_found_session_ids);
                if ( !empty($missing_session_ids) ) {
                    $missing_count = count($missing_session_ids);
                    $total_count = count($session_ids);
                    $found_count = count($all_found_session_ids);
                    
                    wpd_write_log( 
                        sprintf( 
                            'Session data lookup: Found %d/%d session records (%d chunks total: %d processed, %d empty, %d failed). Missing %d session_ids in batch (offset: %d). Sample missing IDs: %s', 
                            $found_count,
                            $total_count,
                            count($session_id_chunks),
                            $chunks_processed,
                            $chunks_empty,
                            $chunks_failed,
                            $missing_count,
                            $offset,
                            implode(', ', array_slice($missing_session_ids, 0, 5))
                        ), 
                        'session_data_missing'
                    );
                    
                    // Log sample of what we're looking for vs what we found (for debugging)
                    if ( $missing_count > 0 && $found_count > 0 ) {
                        $sample_missing = array_slice($missing_session_ids, 0, 3);
                        $sample_found = array_slice($all_found_session_ids, 0, 3);
                        wpd_write_log( 
                            sprintf( 
                                'Sample missing session_ids: [%s] | Sample found session_ids: [%s]', 
                                implode(', ', $sample_missing),
                                implode(', ', $sample_found)
                            ), 
                            'session_data_missing'
                        );
                    }
                }
                
                $current_db_records = $this->get_data( 'analytics', 'total_db_records' );
                $this->set_data( 'analytics', array( 'total_db_records' => $current_db_records + count($all_session_data_results) ) );
            } else {
                // No results found at all
                wpd_write_log( 
                    sprintf( 
                        'Session data lookup: No session_data records found for %d session_ids in %d chunks (batch offset: %d). Sample session_ids: %s', 
                        count($session_ids),
                        count($session_id_chunks),
                        $offset,
                        implode(', ', array_slice($session_ids, 0, 5))
                    ), 
                    'session_data_missing'
                );
            }
        }

        return true;
    }

    /**
     *
     *  Fetches Analytics data from DB and stores it, raw data only no processing.
     *  @see get_gmt_from_date() for converting input date
     *  @link https://developer.wordpress.org/reference/functions/get_gmt_from_date/
     *
     */
    public function fetch_analytics_data() {

        // Start execution timer
        $start_time = microtime(true);

        $data_table_limit               = $this->get_data_table_limit('analytics');
        $traffic_type_filter            = $this->get_data_filter('website_traffic', 'traffic_source'); // Doesn't exist in DB

        // Settings - Show product variations
        $show_product_variations        = apply_filters( 'wpd_ai_show_product_variations', true );

        // Start prep
        $session_data_table = array();
        $session_summary_container = array(
            'page_views'                    => 0,
            'non_page_view_events'          => 0, // All non-page view events
            'category_page_views'           => 0,
            'product_clicks'                => 0,
            'product_page_views'            => 0,
            'account_created'               => 0,
            'add_to_carts'                  => 0,
            'add_to_cart_value'             => 0,
            'initiate_checkouts'            => 0,
            'transactions'                  => 0,
            'transaction_value'             => 0,
            'checkout_error_count'          => 0,
            'form_submits'                  => 0,
            'product_transaction_value'     => 0,
            'unique_products_purchased'     => 0, // Unique line items
            'total_products_purchased'      => 0, // Quantity of products purchased
        );
        $session_container = array_merge(
            $session_summary_container,
            array(
                'session_id' => '',
                'ip_address' => '',
                'user_id' => '',
                'session_start_in_local' => '',
                'session_end_in_local' => '',
                'session_duration' => 0,
                'landing_page' => '',
                'landing_page_path' => '',
                'landing_page_query_parameters' => array(),
                'landing_page_campaign' => '',
                'referral_url' => '',
                'referral_source' => '',
                'device_category' => '',
                'events' => array()
            )
        );
        $analytics_performance_container = array(
            'session_count'             => array(),
            'user_count'                => array(),
            'views'                     => 0,
            'page_views'                => 0,
            'form_submits'              => 0,
            'account_created'           => 0,
            'total_session_duration'    => 0,
            'average_session_duration'  => 0,
            'add_to_carts'              => 0,
            'add_to_cart_value'         => 0,
            'initiate_checkouts'        => 0,
            'transactions'              => 0,
            'revenue'                   => 0,
            'total_value'               => 0,
            'conversion_rate'           => 0.00,
            'page_views_per_session'    => 0,
            'channel_percent'           => 0,
        );
        $totals = array(
            'total_records'                         => 0,
            'sessions'                              => 0,
            'users'                                 => 0, // (count of unique IP's)
            'page_views'                            => 0,
            'form_submits'                          => 0,
            'sessions_with_form_submit'             => 0,
            'percent_of_sessions_with_form_submit'  => 0.00,
            'non_page_view_events'                  => 0, // All non-page view events
            'session_duration'                      => 0,
            'average_session_duration'              => 0,
            'category_page_views'                   => 0,
            'sessions_with_category_page_views'     => 0,
            'product_clicks'                        => 0,
            'product_page_views'                    => 0,
            'sessions_with_product_page_views'      => 0,
            'add_to_carts'                          => 0,
            'add_to_cart_value'                     => 0,
            'sessions_with_add_to_cart'             => 0,
            'account_created'                       => 0,   
            'initiate_checkouts'                    => 0,
            'sessions_with_initiate_checkout'       => 0,
            'transactions'                          => 0,
            'checkout_error_count'                  => 0,
            'sessions_with_transaction'             => 0,
            'transaction_value'                     => 0,
            'product_transaction_value'             => 0,
            'unique_products_purchased'             => 0, // Unique line items
            'total_products_purchased'              => 0, // Quantity of products purchased
            'sessions_per_day'                          => 0,
            'users_per_day'                             => 0,
            'page_views_per_session'                    => 0,
            'events_per_session'                        => 0,
            'percent_sessions_with_category_view'       => 0.00,
            'percent_sessions_with_product_page_view'   => 0.00,
            'percent_sessions_with_add_to_cart'         => 0.00,
            'percent_sessions_with_initiate_checkout'   => 0.00,
            'conversion_rate'                           => 0.00
        );

        $categorized_data = array(
            'event_summary'                         => array(),
            'campaign_summary'                      => array(),
            'acquisition_summary'                   => array(),
            'product_summary'                       => array(),
            'page_view_summary'                     => array(),
            'landing_page_summary'                  => array(),
            'referral_url_summary'                  => array(),
            'checkout_errors_summary'               => array(),
            'form_submits_by_id_summary'            => array(),
            'device_category_summary'               => array(),
            'conversion_funnel_summary'             => array(
                'sessions' => array(
                    'count' => 0,
                    'percent' => 0.00
                ),
                'category_page_view' => array(
                    'count' => 0,
                    'percent' => 0.00
                ),
                'product_page_views' => array(
                    'count' => 0,
                    'percent' => 0.00
                ),
                'add_to_carts' => array(
                    'count' => 0,
                    'percent' => 0.00
                ),
                'initiate_checkouts' => array(
                    'count' => 0,
                    'percent' => 0.00
                ),
                'transactions_complete' => array(
                    'count' => 0,
                    'percent' => 0.00
                ),
            )
        );

        $temp_counter = array(
            'session_id' => array(),
            'ip_address' => array(),
            'sessions_by_date' => array(),
            'ip_address_by_date' => array()
        );

        $data_by_date = array(

            'sessions_by_date' => $this->get_data_by_date_range_container(), // Unique Per Day
            'users_by_date' => $this->get_data_by_date_range_container(), // Unique Per Day
            'page_views_by_date' => $this->get_data_by_date_range_container(),
            'form_submits_by_date' => $this->get_data_by_date_range_container(),
            'events_by_date' => $this->get_data_by_date_range_container(),
            'category_page_views_by_date' => $this->get_data_by_date_range_container(),
            'product_clicks_by_date' => $this->get_data_by_date_range_container(),
            'product_page_views_by_date' => $this->get_data_by_date_range_container(),
            'add_to_carts_by_date' => $this->get_data_by_date_range_container(),
            'conversion_rate_by_date' => $this->get_data_by_date_range_container(),
            'transactions_by_date' => $this->get_data_by_date_range_container(),
            'checkout_errors_by_date' => $this->get_data_by_date_range_container(),
            'account_created_by_date' => $this->get_data_by_date_range_container(),
            'acquisition_channels_by_date' => array( 'no_data_available' => $this->get_data_by_date_range_container() ),
            'all_events_by_date' => array( 'no_data_available' => $this->get_data_by_date_range_container() ), // Multi Dimensional

        );

        // Check for unique events per session -> store sessions ID and do unique count at the end
        $session_unique_array = array(
            'sessions_with_category_page_view' => array(),
            'sessions_with_product_page_view' => array(),
            'sessions_with_add_to_cart' => array(),
            'sessions_with_initiate_checkout' => array(),
            'sessions_with_transaction' => array(),
            'sessions_with_form_submit' => array(),
        );

        /**
         * 
         *  Query the analytics data from the DB with batching
         * 
         **/
        $limit = apply_filters( 'wpd_ai_analytics_data_fetch_batch_size', 10000 );
        $offset = 0;

        // First, get the total count of records to determine how many batches we need
        $total_count = $this->get_analytics_event_count();
        
        if ( $total_count === false ) {
            return false;
        }

        $total_batches = ceil( wpd_divide($total_count, $limit) );
        $processed_records = 0;

        // Initialize session_data_map outside the loop so it persists across batches
        // This ensures session data from previous batches is preserved
        $session_data_map = array();
        
        while ( $offset < $total_count ) {

            $raw_analytics_data = array();
            // Don't reset session_data_map - merge new data into existing map
            $batch_session_data_map = array();
            $query_result = $this->query_analytics_data( $raw_analytics_data, $batch_session_data_map, $limit, $offset );
            
            // Ensure session_data_map is always an array (safety check)
            if ( ! is_array( $session_data_map ) ) {
                $session_data_map = array();
            }
            
            // Ensure batch_session_data_map is always an array (even if query failed)
            if ( ! is_array( $batch_session_data_map ) ) {
                $batch_session_data_map = array();
            }
            
            // Merge batch session data into persistent map (preserve existing, add new)
            // Only merge if we have data to merge
            if ( ! empty( $batch_session_data_map ) ) {
                $session_data_map = array_merge( $session_data_map, $batch_session_data_map );
            }

            /**
             *
             *  Perform all calculations
             *
             */
            foreach( $raw_analytics_data as $event ) {

                // Memory Check
                if ( wpd_is_memory_usage_greater_than(90) ) {
                    $memory_limit = ini_get('memory_limit');
                    $this->set_error(
                        sprintf(
                            /* translators: %s: PHP memory limit */
                            __( 'You\'ve exhausted your memory usage. Increase your PHP memory limit or reduce the date range. Your current PHP memory limit is %s.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                            $memory_limit
                        )
                    );
                    break;
                }

                // Event Data
                $session_id                 = $event['session_id'];
                $event_date_created_gmt     = $event['date_created_gmt'];
                $event_type                 = $event['event_type'] ?? null;
                $event_quantity             = $event['event_quantity'] ?? 1;
                $event_value                = $event['event_value'] ?? 0;
                $product_id                 = $event['product_id'] ?? 0;
                $variation_id               = $event['variation_id'] ?? 0;
                $page_href                  = $event['page_href'] ?? null;
                $object_type                = $event['object_type'] ?? null;
                $additional_data            = (isset($event['additional_data']) && ! empty($event['additional_data'])) ? json_decode( maybe_unserialize($event['additional_data']), true ) : null;

                // Session Data - get from session data map
                // Normalize session_id by trimming whitespace before lookup
                $normalized_session_id      = trim((string)$session_id);
                $session_data               = isset($session_data_map[$normalized_session_id]) ? $session_data_map[$normalized_session_id] : null;
                
                // If session data exists in map, use it; otherwise try to preserve existing data from session_data_table
                // This handles cases where historical sessions might not have session_data records
                $existing_session_data = isset($session_data_table[$session_id]) ? $session_data_table[$session_id] : null;
                
                $user_id                    = $session_data ? $session_data['user_id'] : ($existing_session_data && isset($existing_session_data['user_id']) && $existing_session_data['user_id'] !== '' ? $existing_session_data['user_id'] : null);
                $ip_address                 = $session_data ? $session_data['ip_address'] : ($existing_session_data && isset($existing_session_data['ip_address']) && $existing_session_data['ip_address'] !== '' ? $existing_session_data['ip_address'] : null);
                $landing_page               = $session_data ? $session_data['landing_page'] : ($existing_session_data && isset($existing_session_data['landing_page']) && !empty($existing_session_data['landing_page']) ? $existing_session_data['landing_page'] : null);
                $referral_url               = $session_data ? $session_data['referral_url'] : ($existing_session_data && isset($existing_session_data['referral_url']) && !empty($existing_session_data['referral_url']) ? $existing_session_data['referral_url'] : null);
                // Use GMT dates from session_data if available (more reliable than converting local times)
                $session_date_created_gmt   = $session_data ? $session_data['date_created_gmt'] : null;
                $session_date_updated_gmt   = $session_data ? $session_data['date_updated_gmt'] : null;
                $device_category            = $session_data ? $session_data['device_category'] : ($existing_session_data && isset($existing_session_data['device_category']) && $existing_session_data['device_category'] !== '' ? $existing_session_data['device_category'] : null);

                // @todo this should likely be done on submission of data
                if ($event_type == 'add_to_cart') $event_value = $event_value * $event_quantity;

                // Variable cleaning
                $session_duration               = $this->calculate_difference_in_seconds( $session_date_updated_gmt, $session_date_created_gmt );
                $event_timestamp_in_local       = $this->get_date_from_gmt( $event_date_created_gmt ); // Replaced native get_date_from_gmt() with faster version
                $session_date_created_local     = $this->get_date_from_gmt( $session_date_created_gmt ); // Replaced native get_date_from_gmt() with faster version
                $session_date_updated_local     = $this->get_date_from_gmt( $session_date_updated_gmt ); // Replaced native get_date_from_gmt() with faster version
                $landing_page_url_components    = $this->get_url_components( $landing_page );
                $landing_page_path              = $landing_page_url_components['path'];
                $landing_page_query_parameters  = $landing_page_url_components['query_parameters'];
                $session_traffic_source         = $this->determine_traffic_source( $referral_url, $landing_page_query_parameters );
                $event_page_url_components      = $this->get_url_components( $page_href );
                $event_page_path                = $event_page_url_components['path'];
                $event_formatted_date           = $this->reformat_date_to_date_format($event_timestamp_in_local); // Formatted for date date
                (isset($landing_page_query_parameters['utm_campaign'])) ? $utm_campaign = $landing_page_query_parameters['utm_campaign'] : $utm_campaign = null;

                // Data Filtering
                $session_data_table_count = is_array($session_data_table) ? count($session_data_table) : 0;
    
                /**
                 *  Apply Traffic type filtering to sessions
                 *  This data does not currently exist in the DB so needs to be done here
                 **/
                if ( $traffic_type_filter && ! in_array($session_traffic_source, $traffic_type_filter) ) continue;
                
                // Setup session container
                if ( $session_data_table_count < $data_table_limit ) {

                    if ( ! isset($session_data_table[$session_id]) ) $session_data_table[$session_id] = $session_container;

                    // Store Session Meta (flattened structure)
                    // Only update fields if we have actual data (don't overwrite with nulls/empty strings)
                    $session_data_table[$session_id]['session_id'] = $session_id;
                    
                    // Only set these if we have actual values (preserve existing data if session_data is missing)
                    if ( $ip_address !== null && $ip_address !== '' ) {
                        $session_data_table[$session_id]['ip_address'] = $ip_address;
                    }
                    if ( $user_id !== null && $user_id !== '' ) {
                        $session_data_table[$session_id]['user_id'] = $user_id;
                    }
                    if ( $session_date_created_local !== null && $session_date_created_local !== '' ) {
                        $session_data_table[$session_id]['session_start_in_local'] = $session_date_created_local;
                    }
                    if ( $session_date_updated_local !== null && $session_date_updated_local !== '' ) {
                        $session_data_table[$session_id]['session_end_in_local'] = $session_date_updated_local;
                    }
                    if ( $session_duration !== null ) {
                        $session_data_table[$session_id]['session_duration'] = $session_duration;
                    }
                    if ( $landing_page !== null && $landing_page !== '' ) {
                        $session_data_table[$session_id]['landing_page'] = htmlspecialchars_decode( $landing_page );
                    }
                    if ( ! empty($landing_page_path) ) {
                        $session_data_table[$session_id]['landing_page_path'] = $landing_page_path;
                    }
                    if ( ! empty($landing_page_query_parameters) ) {
                        $session_data_table[$session_id]['landing_page_query_parameters'] = $landing_page_query_parameters;
                    }
                    if ( $utm_campaign !== null && $utm_campaign !== '' ) {
                        $session_data_table[$session_id]['landing_page_campaign'] = $utm_campaign;
                    }
                    if ( $referral_url !== null && $referral_url !== '' ) {
                        $session_data_table[$session_id]['referral_url'] = $referral_url;
                    }
                    if ( $session_traffic_source !== null && $session_traffic_source !== '' ) {
                        $session_data_table[$session_id]['referral_source'] = $session_traffic_source;
                    }
                    if ( $device_category !== null && $device_category !== '' ) {
                        $session_data_table[$session_id]['device_category'] = $device_category;
                    }
                    $session_data_table[$session_id]['events'][] = $event;

                }

                /**
                 *
                 *  Calculate Totals
                 * 
                 */
                // Data Totals
                $totals['total_records']++;

                // Total Sessions
                if ( ! isset($temp_counter['session_id'][$session_id]) ) {
                    $temp_counter['session_id'][$session_id] = true;
                    $totals['sessions']++;
                    $totals['session_duration'] += $session_duration;
                }

                // Total Users
                if ( ! isset($temp_counter['ip_address'][$ip_address]) ) {
                    $temp_counter['ip_address'][$ip_address] = 1;
                    $totals['users']++;
                }

                // Sessions by date
                if ( ! isset($temp_counter['sessions_by_date'][$event_formatted_date][$session_id]) ) {

                    // Temp Counter for unique sessions by date
                    $temp_counter['sessions_by_date'][$event_formatted_date][$session_id] = 1;

                    // If this array key has not been setup, set it up
                    if ( ! isset($data_by_date['sessions_by_date'][$event_formatted_date]) ) $data_by_date['sessions_by_date'][$event_formatted_date] = 0;

                    // Increment the event count
                    $data_by_date['sessions_by_date'][$event_formatted_date]++;

                    // Acquisition channels by date -> Load the correct data defaults
                    if ( isset($data_by_date['acquisition_channels_by_date']['no_data_available']) ) $data_by_date['acquisition_channels_by_date'] = array(); // If we've still got the initial empty array, rebuild.
                    if ( ! isset($data_by_date['acquisition_channels_by_date'][$session_traffic_source]) ) $data_by_date['acquisition_channels_by_date'][$session_traffic_source] = $this->get_data_by_date_range_container();
                    // Acquisitions channels by date -> fill in the data
                    if ( isset($data_by_date['acquisition_channels_by_date'][$session_traffic_source][$event_formatted_date]) ) $data_by_date['acquisition_channels_by_date'][$session_traffic_source][$event_formatted_date]++;

                }

                // Users by date
                if ( ! isset($temp_counter['ip_address_by_date'][$event_formatted_date][$ip_address]) ) {

                    // Temp counter for unique users per date
                    $temp_counter['ip_address_by_date'][$event_formatted_date][$ip_address] = 1;

                    // Setup user count by date if it hasn't been setup
                    if ( ! isset($data_by_date['users_by_date'][$event_formatted_date]) ) $data_by_date['users_by_date'][$event_formatted_date] = 0;

                    // Increment the users by date count
                    $data_by_date['users_by_date'][$event_formatted_date]++;

                }

                /**
                 * 
                 *  Setup Summary Data
                 * 
                 */
                // Setup Traffic Source (Acquisition) Data
                if ( ! isset($categorized_data['acquisition_summary'][$session_traffic_source]) ) $categorized_data['acquisition_summary'][$session_traffic_source] = $analytics_performance_container;
                $categorized_data['acquisition_summary'][$session_traffic_source]['session_count'][$session_id] = 1;
                $categorized_data['acquisition_summary'][$session_traffic_source]['user_count'][$ip_address] = 1;
                // Add session duration to this if it hasn't been already
                if ( ! isset($temp_counter['acquisition_summary_unique_session_counter'][$session_id]) ) {
                    $categorized_data['acquisition_summary'][$session_traffic_source]['total_session_duration'] += $session_duration;
                    $temp_counter['acquisition_summary_unique_session_counter'][$session_id] = 1;
                }

                // Setup Device Category Data
                if ( ! isset($categorized_data['device_category_summary'][$device_category]) ) $categorized_data['device_category_summary'][$device_category] = $analytics_performance_container;
                $categorized_data['device_category_summary'][$device_category]['session_count'][$session_id] = 1;
                $categorized_data['device_category_summary'][$device_category]['user_count'][$ip_address] = 1;
                // Add session duration to this if it hasn't been already
                if ( ! isset($temp_counter['device_category_summary_unique_session_counter'][$session_id]) ) {
                    $categorized_data['device_category_summary'][$device_category]['total_session_duration'] += $session_duration;
                    $temp_counter['device_category_summary_unique_session_counter'][$session_id] = 1;
                }

                // Setup UTM Campaign Data
                if ( ! is_null($utm_campaign) ) {
                    if ( ! isset($categorized_data['campaign_summary'][$utm_campaign]) ) $categorized_data['campaign_summary'][$utm_campaign] = $analytics_performance_container;
                    $categorized_data['campaign_summary'][$utm_campaign]['session_count'][$session_id] = 1;
                    $categorized_data['campaign_summary'][$utm_campaign]['user_count'][$ip_address] = 1;
                    // Add session duration to this if it hasn't been already
                    if ( ! isset($temp_counter['campaign_summary_unique_session_counter'][$session_id]) ) {
                        $categorized_data['campaign_summary'][$utm_campaign]['total_session_duration'] += $session_duration;
                        $temp_counter['campaign_summary_unique_session_counter'][$session_id] = 1;
                    }
                }

                // Setup Landing Page Data
                if ( ! empty($landing_page_path) ) {
                    if ( ! isset($categorized_data['landing_page_summary'][$landing_page_path]) ) $categorized_data['landing_page_summary'][$landing_page_path] = $analytics_performance_container;
                    $categorized_data['landing_page_summary'][$landing_page_path]['session_count'][$session_id] = 1;
                    $categorized_data['landing_page_summary'][$landing_page_path]['user_count'][$ip_address] = 1;
                    $categorized_data['landing_page_summary'][$landing_page_path]['views']++;
                    // Add session duration to this if it hasn't been already
                    if ( ! isset($temp_counter['landing_page_summary_unique_session_counter'][$session_id]) ) {
                        $categorized_data['landing_page_summary'][$landing_page_path]['total_session_duration'] += $session_duration;
                        $temp_counter['landing_page_summary_unique_session_counter'][$session_id] = 1;
                    }
                }
                
                // Setup Referral URL Data
                if ( ! empty($referral_url) ) {
                    if ( ! isset($categorized_data['referral_url_summary'][$referral_url]) ) $categorized_data['referral_url_summary'][$referral_url] = $analytics_performance_container;
                    $categorized_data['referral_url_summary'][$referral_url]['session_count'][$session_id] = 1;
                    $categorized_data['referral_url_summary'][$referral_url]['user_count'][$ip_address] = 1;
                    $categorized_data['referral_url_summary'][$referral_url]['views']++;
                    // Add session duration to this if it hasn't been already
                    if ( ! isset($temp_counter['referral_url_summary_unique_session_counter'][$session_id]) ) {
                        $categorized_data['referral_url_summary'][$referral_url]['total_session_duration'] += $session_duration;
                        $temp_counter['referral_url_summary_unique_session_counter'][$session_id] = 1;
                    }
                }

                /**
                 * 
                 *  Handle specific event types
                 * 
                 */
                // Page Views
                if ($event_type == 'page_view') {

                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['page_views']++;
                    $totals['page_views']++;

                    if ( ! isset($data_by_date['page_views_by_date'][$event_formatted_date]) ) $data_by_date['page_views_by_date'][$event_formatted_date] = 0;
                    $data_by_date['page_views_by_date'][$event_formatted_date]++;

                    // Handle total page view data
                    if ( ! isset($categorized_data['page_view_summary'][$event_page_path]) ) {
                        $categorized_data['page_view_summary'][$event_page_path] = $analytics_performance_container;
                    }
                    $categorized_data['page_view_summary'][$event_page_path]['session_count'][$session_id] = 1;
                    $categorized_data['page_view_summary'][$event_page_path]['user_count'][$ip_address] = 1;
                    $categorized_data['page_view_summary'][$event_page_path]['views']++;

                    // Enrich summary data
                    if ( ! empty($session_traffic_source) ) $categorized_data['acquisition_summary'][$session_traffic_source]['page_views']++;
                    if ( ! empty($device_category) ) $categorized_data['device_category_summary'][$device_category]['page_views']++;
                    if ( ! empty($landing_page_path) ) $categorized_data['landing_page_summary'][$landing_page_path]['page_views']++;
                    if ( ! empty($referral_url) ) $categorized_data['referral_url_summary'][$referral_url]['page_views']++;
                    if ( ! empty($utm_campaign) ) $categorized_data['campaign_summary'][$utm_campaign]['page_views']++;
                    if ( ! empty($session_traffic_source) ) $categorized_data['acquisition_summary'][$session_traffic_source]['views']++; // Legacy support
                    if ( ! empty($device_category) ) $categorized_data['device_category_summary'][$device_category]['views']++; // Legacy support
                    if ( ! empty($landing_page_path) ) $categorized_data['landing_page_summary'][$landing_page_path]['views']++; // Legacy support
                    if ( ! empty($referral_url) ) $categorized_data['referral_url_summary'][$referral_url]['views']++; // Legacy support
                    if ( ! empty($utm_campaign) ) $categorized_data['campaign_summary'][$utm_campaign]['views']++; // Legacy support

                }

                // Non Page View Events
                if ($event_type != 'page_view') {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['non_page_view_events']++;
                    $totals['non_page_view_events']++;
                    if ( ! isset($data_by_date['events_by_date'][$event_formatted_date]) ) $data_by_date['events_by_date'][$event_formatted_date] = 0;
                    $data_by_date['events_by_date'][$event_formatted_date]++;
                }

                // Product Click
                if ($event_type == 'product_click') {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['product_clicks']++;
                    $totals['product_clicks']++;
                    $data_by_date['product_clicks_by_date'][$event_formatted_date]++;
                }

                // Product Category Page Views
                if ($event_type == 'page_view' && $object_type == 'product_cat') {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['category_page_views']++;
                    $totals['category_page_views']++;
                    $data_by_date['category_page_views_by_date'][$event_formatted_date]++;
                    if ( ! isset($session_unique_array['sessions_with_category_page_view'][$session_id]) ) {
                        $session_unique_array['sessions_with_category_page_view'][$session_id] = true;
                        $totals['sessions_with_category_page_views']++;
                    }
                }

                // Product Page Views
                if ($event_type == 'page_view' && $object_type == 'product') {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['product_page_views']++;
                    $totals['product_page_views']++;
                    if ( ! isset($data_by_date['product_page_views_by_date'][$event_formatted_date]) ) $data_by_date['product_page_views_by_date'][$event_formatted_date] = 0;
                    $data_by_date['product_page_views_by_date'][$event_formatted_date]++;
                    if ( ! isset($session_unique_array['sessions_with_product_page_view'][$session_id]) ) {
                        $session_unique_array['sessions_with_product_page_view'][$session_id] = true;
                        $totals['sessions_with_product_page_views']++;
                    }
                }

                // Add to cart
                if ($event_type == 'add_to_cart') {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['add_to_carts']++;
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['add_to_cart_value'] += $event_value;
                    $totals['add_to_carts']++;
                    $totals['add_to_cart_value'] += $event_value;
                    if ( ! isset($data_by_date['add_to_carts_by_date'][$event_formatted_date]) ) $data_by_date['add_to_carts_by_date'][$event_formatted_date] = 0;
                    $data_by_date['add_to_carts_by_date'][$event_formatted_date]++;
                    if ( ! isset($session_unique_array['sessions_with_add_to_cart'][$session_id]) ) {
                        $session_unique_array['sessions_with_add_to_cart'][$session_id] = true;
                        $totals['sessions_with_add_to_cart']++;
                    }
                    // Add to carts
                    if ( ! empty($session_traffic_source) ) $categorized_data['acquisition_summary'][$session_traffic_source]['add_to_carts']++;
                    if ( ! empty($device_category) ) $categorized_data['device_category_summary'][$device_category]['add_to_carts']++;
                    if ( ! empty($landing_page_path) ) $categorized_data['landing_page_summary'][$landing_page_path]['add_to_carts']++;
                    if ( ! empty($referral_url) ) $categorized_data['referral_url_summary'][$referral_url]['add_to_carts']++;
                    if ( ! empty($utm_campaign) ) $categorized_data['campaign_summary'][$utm_campaign]['add_to_carts']++;
                    // Add to cart value
                    if ( ! empty($session_traffic_source) ) $categorized_data['acquisition_summary'][$session_traffic_source]['add_to_cart_value'] += $event_value;
                    if ( ! empty($device_category) ) $categorized_data['device_category_summary'][$device_category]['add_to_cart_value'] += $event_value;
                    if ( ! empty($landing_page_path) ) $categorized_data['landing_page_summary'][$landing_page_path]['add_to_cart_value'] += $event_value;
                    if ( ! empty($referral_url) ) $categorized_data['referral_url_summary'][$referral_url]['add_to_cart_value'] += $event_value;
                    if ( ! empty($utm_campaign) ) $categorized_data['campaign_summary'][$utm_campaign]['add_to_cart_value'] += $event_value;
                }

                // Initiate Checkout
                if ($event_type == 'init_checkout') {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['initiate_checkouts']++;
                    $totals['initiate_checkouts']++;
                    if ( ! isset($session_unique_array['sessions_with_initiate_checkout'][$session_id]) ) {
                        $session_unique_array['sessions_with_initiate_checkout'][$session_id] = true;
                        $totals['sessions_with_initiate_checkout']++;
                    }
                    // Initiate checkouts
                    if ( ! empty($session_traffic_source) ) $categorized_data['acquisition_summary'][$session_traffic_source]['initiate_checkouts']++;
                    if ( ! empty($device_category) ) $categorized_data['device_category_summary'][$device_category]['initiate_checkouts']++;
                    if ( ! empty($landing_page_path) ) $categorized_data['landing_page_summary'][$landing_page_path]['initiate_checkouts']++;
                    if ( ! empty($referral_url) ) $categorized_data['referral_url_summary'][$referral_url]['initiate_checkouts']++;
                    if ( ! empty($utm_campaign) ) $categorized_data['campaign_summary'][$utm_campaign]['initiate_checkouts']++;
                }

                // Purchase - Product Line Items
                if ($event_type == 'product_purchase') {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['unique_products_purchased']++;
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['total_products_purchased'] += $event_quantity;
                    $totals['unique_products_purchased']++;
                    $totals['total_products_purchased'] += $event_quantity;
                }

                // Transaction
                if ($event_type == 'transaction') {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['transactions']++;
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['transaction_value'] += $event_value;
                    $totals['transactions']++;
                    $totals['transaction_value'] += $event_value;
                    if ( ! isset($data_by_date['transactions_by_date'][$event_formatted_date]) ) $data_by_date['transactions_by_date'][$event_formatted_date] = 0;
                    $data_by_date['transactions_by_date'][$event_formatted_date]++;
                    if ( ! isset($session_unique_array['sessions_with_transaction'][$session_id]) ) {
                        $session_unique_array['sessions_with_transaction'][$session_id] = true;
                        $totals['sessions_with_transaction']++;
                    }
                    if (! empty($landing_page_path)) {
                        $categorized_data['landing_page_summary'][$landing_page_path]['transactions']++;
                        $categorized_data['landing_page_summary'][$landing_page_path]['revenue'] += $event_value;
                        $categorized_data['landing_page_summary'][$landing_page_path]['total_value'] += $event_value;
                    }
                    if (! empty($referral_url)) {
                        $categorized_data['referral_url_summary'][$referral_url]['transactions']++;
                        $categorized_data['referral_url_summary'][$referral_url]['revenue'] += $event_value;
                        $categorized_data['referral_url_summary'][$referral_url]['total_value'] += $event_value;
                    }
                    if ( ! empty($utm_campaign) ) {
                        $categorized_data['campaign_summary'][$utm_campaign]['transactions']++;
                        $categorized_data['campaign_summary'][$utm_campaign]['revenue'] += $event_value;
                        $categorized_data['campaign_summary'][$utm_campaign]['total_value'] += $event_value;
                    }
                    if ( ! empty($session_traffic_source) ) {
                        $categorized_data['acquisition_summary'][$session_traffic_source]['transactions']++;
                        $categorized_data['acquisition_summary'][$session_traffic_source]['revenue'] += $event_value;
                        $categorized_data['acquisition_summary'][$session_traffic_source]['total_value'] += $event_value;
                    }
                    if ( ! empty($device_category) ) {
                        $categorized_data['device_category_summary'][$device_category]['transactions']++;
                        $categorized_data['device_category_summary'][$device_category]['revenue'] += $event_value;
                        $categorized_data['device_category_summary'][$device_category]['total_value'] += $event_value;
                    }
                }

                // Checkout Erors
                if ( $event_type == 'checkout_error' ) {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['checkout_error_count']++;
                    $totals['checkout_error_count']++;
                    if ( ! isset($data_by_date['checkout_errors_by_date'][$event_formatted_date]) ) $data_by_date['checkout_errors_by_date'][$event_formatted_date] = 0;
                    if ( isset($data_by_date['checkout_errors_by_date'][$event_formatted_date]) ) $data_by_date['checkout_errors_by_date'][$event_formatted_date]++;

                    // Capture error message if available
                    if ( is_array($additional_data) && isset($additional_data['error_message']) && ! empty($additional_data['error_message']) ) {
                        $error_message = sanitize_text_field( $additional_data['error_message'] );
                        if ( ! isset($categorized_data['checkout_errors_summary'][$error_message]) ) {
                            $categorized_data['checkout_errors_summary'][$error_message] = 0;
                        }
                        $categorized_data['checkout_errors_summary'][$error_message]++;
                    }

                }

                // Form Submit
                if ( $event_type == 'form_submit' ) {

                    // ID
                    $form_id = 'unknown';
                    if ( is_array($additional_data) && isset($additional_data['form_id']) && ! empty($additional_data['form_id']) ) $form_id = sanitize_text_field( $additional_data['form_id'] );

                    // Add to datatable, if not at max
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['form_submits']++;

                    // Increment Totals
                    $totals['form_submits']++;

                    // Track Daily
                    if ( isset($data_by_date['form_submits_by_date'][$event_formatted_date]) ) $data_by_date['form_submits_by_date'][$event_formatted_date]++;
                
                    // Setup form submit by id summary
                    if ( ! isset($categorized_data['form_submits_by_id_summary'][$form_id]) ) {
                        $categorized_data['form_submits_by_id_summary'][$form_id] = array();
                        $categorized_data['form_submits_by_id_summary'][$form_id]['total_count'] = 0;
                        $categorized_data['form_submits_by_id_summary'][$form_id]['sessions_with_submission'] = 0;
                        $categorized_data['form_submits_by_id_summary'][$form_id]['conversion_rate'] = 0.00;
                    }
                    $categorized_data['form_submits_by_id_summary'][$form_id]['total_count']++;

                    // Unique session tracking
                    if ( ! isset($session_unique_array['sessions_with_form_submit'][$session_id]) ) {
                        $session_unique_array['sessions_with_form_submit'][$session_id] = true;
                        $totals['sessions_with_form_submit']++;
                        $categorized_data['form_submits_by_id_summary'][$form_id]['sessions_with_submission']++;
                    }

                    // Form Submits
                    if ( ! empty($session_traffic_source) ) $categorized_data['acquisition_summary'][$session_traffic_source]['form_submits']++;
                    if ( ! empty($device_category) ) $categorized_data['device_category_summary'][$device_category]['form_submits']++;
                    if ( ! empty($landing_page_path) ) $categorized_data['landing_page_summary'][$landing_page_path]['form_submits']++;
                    if ( ! empty($referral_url) ) $categorized_data['referral_url_summary'][$referral_url]['form_submits']++;
                    if ( ! empty($utm_campaign) ) $categorized_data['campaign_summary'][$utm_campaign]['form_submits']++;

                }

                // Account created
                if ( $event_type == 'account_created' ) {
                    if ( $session_data_table_count < $data_table_limit ) $session_data_table[$session_id]['account_created']++;
                    $totals['account_created']++;
                    if ( ! isset($data_by_date['account_created_by_date'][$event_formatted_date]) ) $data_by_date['account_created_by_date'][$event_formatted_date] = 0;
                    $data_by_date['account_created_by_date'][$event_formatted_date]++;
                    // Account created
                    if ( ! empty($session_traffic_source) ) $categorized_data['acquisition_summary'][$session_traffic_source]['account_created']++;
                    if ( ! empty($device_category) ) $categorized_data['device_category_summary'][$device_category]['account_created']++;
                    if ( ! empty($landing_page_path) ) $categorized_data['landing_page_summary'][$landing_page_path]['account_created']++;
                    if ( ! empty($referral_url) ) $categorized_data['referral_url_summary'][$referral_url]['account_created']++;
                    if ( ! empty($utm_campaign) ) $categorized_data['campaign_summary'][$utm_campaign]['account_created']++;
                }

                /**
                 *
                 *  Log all events here
                 *
                 */
                // Setup container if not exists
                if ( ! isset($categorized_data['event_summary'][$event_type]) ) {
                    $categorized_data['event_summary'][$event_type] = array('total_count' => 0, 'user_count' => array(), 'session_count' => array(), 'total_value' => 0);
                }
                $categorized_data['event_summary'][$event_type]['total_count']++;
                $categorized_data['event_summary'][$event_type]['user_count'][$ip_address] = 1;
                $categorized_data['event_summary'][$event_type]['session_count'][$session_id] = 1;
                $categorized_data['event_summary'][$event_type]['total_value'] += $event_value;

                // All events by date
                if ( isset($data_by_date['all_events_by_date']['no_data_available']) ) $data_by_date['all_events_by_date'] = array(); // If we've still got the initial empty array, rebuild.
                if ( ! isset($data_by_date['all_events_by_date'][$event_type]) ) $data_by_date['all_events_by_date'][$event_type] = $this->get_data_by_date_range_container();
                if ( isset($data_by_date['all_events_by_date'][$event_type][$event_formatted_date]) ) $data_by_date['all_events_by_date'][$event_type][$event_formatted_date]++;

                /**
                 * 
                 *  Product Performance
                 * 
                 */
                if ( $product_id > 0 ) {

                    if ( ! isset($categorized_data['product_summary'][$product_id]) ) {
                        $categorized_data['product_summary'][$product_id] = array(
                            'product_name'          => wp_strip_all_tags(html_entity_decode(get_the_title($product_id), ENT_QUOTES, 'UTF-8')),
                            'product_id'            => $product_id,
                            'variation_id'          => 0,
                            'user_count'            => array(),
                            'session_count'         => array(),
                            'product_clicks'        => 0, 
                            'product_page_views'    => 0, 
                            'add_to_cart'           => 0, 
                            'percent_of_sessions_with_add_to_cart'   => 0, 
                            'transactions'          => 0, 
                            'qty_purchased'         => 0, 
                            'total_value'           => 0
                        );
                    }
                    if (empty($categorized_data['product_summary'][$product_id]['product_name'])) $categorized_data['product_summary'][$product_id]['product_name'] = 'Unknown ID ' . $product_id;
                    $categorized_data['product_summary'][$product_id]['user_count'][$ip_address] = 1;
                    $categorized_data['product_summary'][$product_id]['session_count'][$session_id] = 1;
                    if ( $event_type == 'product_click' ) $categorized_data['product_summary'][$product_id]['product_clicks']++;
                    if ( $event_type == 'add_to_cart' ) $categorized_data['product_summary'][$product_id]['add_to_cart']++;
                    if ( $event_type == 'page_view' && $object_type == 'product' ) $categorized_data['product_summary'][$product_id]['product_page_views']++;
                    if ( $event_type == 'product_purchase' ) {
                        $categorized_data['product_summary'][$product_id]['transactions']++;
                        $categorized_data['product_summary'][$product_id]['total_value'] += $event_value;
                        $categorized_data['product_summary'][$product_id]['qty_purchased'] += $event_quantity;                
                    }

                    // Duplicate for variation ID
                    if ( $show_product_variations && $product_id > 0 && $variation_id > 0 ) {

                        if ( ! isset($categorized_data['product_summary'][$variation_id]) ) {
                            $categorized_data['product_summary'][$variation_id] = array(
                                'product_name'          => wp_strip_all_tags(html_entity_decode(get_the_title($variation_id), ENT_QUOTES, 'UTF-8')),
                                'product_id'            => $product_id,
                                'variation_id'          => $variation_id,
                                'user_count'            => array(),
                                'session_count'         => array(),
                                'product_clicks'        => 0, 
                                'product_page_views'    => 0, 
                                'add_to_cart'           => 0, 
                                'percent_of_sessions_with_add_to_cart'   => 0, 
                                'transactions'          => 0, 
                                'qty_purchased'         => 0, 
                                'total_value'           => 0
                            );
                        }

                        if (empty($categorized_data['product_summary'][$variation_id]['product_name'])) $categorized_data['product_summary'][$variation_id]['product_name'] = 'Unknown ID ' . $variation_id;
                        $categorized_data['product_summary'][$variation_id]['user_count'][$ip_address] = 1;
                        $categorized_data['product_summary'][$variation_id]['session_count'][$session_id] = 1;
                        if ( $event_type == 'product_click' ) $categorized_data['product_summary'][$variation_id]['product_clicks']++;
                        if ( $event_type == 'add_to_cart' ) $categorized_data['product_summary'][$variation_id]['add_to_cart']++;
                        if ( $event_type == 'page_view' && $object_type == 'product' ) $categorized_data['product_summary'][$variation_id]['product_page_views']++;
                        if ( $event_type == 'product_purchase' ) {
                            $categorized_data['product_summary'][$variation_id]['transactions']++;
                            $categorized_data['product_summary'][$variation_id]['total_value'] += $event_value;
                            $categorized_data['product_summary'][$variation_id]['qty_purchased'] += $event_quantity;                
                        }

                    }

                }

            }

            // Increment offset for next batch
            $offset += $limit;
            $processed_records += count($raw_analytics_data);
            
            // Clear batch data from memory (but preserve persistent session_data_map for next batch)
            unset($raw_analytics_data);
            unset($batch_session_data_map);
            // DO NOT unset $session_data_map - it needs to persist across batches!
            
            // Force garbage collection
            if ( function_exists('gc_collect_cycles') ) {
                gc_collect_cycles();
            }
                        
        }

        // Build the conversion rate chart
        foreach( $data_by_date['conversion_rate_by_date'] as $date_key => $value ) {

            $session_count  = ( isset($data_by_date['sessions_by_date']) ) ? (int) $data_by_date['sessions_by_date'][$date_key] : 0;
            $transactions   = ( isset($data_by_date['transactions_by_date']) ) ? (int) $data_by_date['transactions_by_date'][$date_key] : 0;
            $conversion_rate = wpd_calculate_percentage( $transactions, $session_count );
            $data_by_date['conversion_rate_by_date'][$date_key] = $conversion_rate;

        }

        // Some cleaning - All Events
        if ( is_array($categorized_data['event_summary']) && ! empty($categorized_data['event_summary']) ) {
            foreach( $categorized_data['event_summary'] as $event_key => $event_data ) {
                $categorized_data['event_summary'][$event_key]['user_count'] = count($event_data['user_count']);
                $categorized_data['event_summary'][$event_key]['session_count'] = count($event_data['session_count']);
            }
        } else {
            $categorized_data['event_summary'] = array(
                'no_events_found' => array(
                    'total_count' => 0,
                    'user_count' => 0,
                    'session_count' => 0,
                    'total_value' => 0
                )
            );
        }
        // Some cleaning - Acquisition
        if ( is_array($categorized_data['acquisition_summary']) && ! empty($categorized_data['acquisition_summary']) ) {
            foreach( $categorized_data['acquisition_summary'] as $acquisition_channel => $acquisition_data ) {
                $categorized_data['acquisition_summary'][$acquisition_channel]['user_count'] = count($acquisition_data['user_count']);
                $categorized_data['acquisition_summary'][$acquisition_channel]['session_count'] = count($acquisition_data['session_count']);
                $categorized_data['acquisition_summary'][$acquisition_channel]['conversion_rate'] = wpd_calculate_percentage( $categorized_data['acquisition_summary'][$acquisition_channel]['transactions'], $categorized_data['acquisition_summary'][$acquisition_channel]['session_count'], 2 );
                $categorized_data['acquisition_summary'][$acquisition_channel]['channel_percent'] = wpd_calculate_percentage( $categorized_data['acquisition_summary'][$acquisition_channel]['session_count'], $totals['sessions'], 2 );
                $categorized_data['acquisition_summary'][$acquisition_channel]['average_session_duration'] = wpd_divide( $categorized_data['acquisition_summary'][$acquisition_channel]['total_session_duration'], $categorized_data['acquisition_summary'][$acquisition_channel]['session_count'], 2 );
                $categorized_data['acquisition_summary'][$acquisition_channel]['page_views_per_session'] = wpd_divide( $categorized_data['acquisition_summary'][$acquisition_channel]['page_views'], $categorized_data['acquisition_summary'][$acquisition_channel]['session_count'], 2 );
            }
        } else {
            $categorized_data['acquisition_summary']['no_acquisition_data_found'] = $analytics_performance_container;
        }
        // Some cleaning - Device Category
        if ( is_array($categorized_data['device_category_summary']) && ! empty($categorized_data['device_category_summary']) ) {
            foreach( $categorized_data['device_category_summary'] as $device_category => $device_data ) {
                $categorized_data['device_category_summary'][$device_category]['user_count'] = count($device_data['user_count']);
                $categorized_data['device_category_summary'][$device_category]['session_count'] = count($device_data['session_count']);
                $categorized_data['device_category_summary'][$device_category]['conversion_rate'] = wpd_calculate_percentage( $categorized_data['device_category_summary'][$device_category]['transactions'], $categorized_data['device_category_summary'][$device_category]['session_count'], 2 );
                $categorized_data['device_category_summary'][$device_category]['channel_percent'] = wpd_calculate_percentage( $categorized_data['device_category_summary'][$device_category]['session_count'], $totals['sessions'], 2 );
                $categorized_data['device_category_summary'][$device_category]['average_session_duration'] = wpd_divide( $categorized_data['device_category_summary'][$device_category]['total_session_duration'], $categorized_data['device_category_summary'][$device_category]['session_count'], 2 );
                $categorized_data['device_category_summary'][$device_category]['page_views_per_session'] = wpd_divide( $categorized_data['device_category_summary'][$device_category]['page_views'], $categorized_data['device_category_summary'][$device_category]['session_count'], 2 );
            }
        } else {
            $categorized_data['device_category_summary']['no_device_category_data_found'] = $analytics_performance_container;
        }
        // Some cleaning - UTM Campaigns
        if ( is_array($categorized_data['campaign_summary']) && ! empty($categorized_data['campaign_summary']) ) {
            foreach( $categorized_data['campaign_summary'] as $campaign_name => $campaign_data ) {
                $categorized_data['campaign_summary'][$campaign_name]['user_count'] = count($campaign_data['user_count']);
                $categorized_data['campaign_summary'][$campaign_name]['session_count'] = count($campaign_data['session_count']);
                $categorized_data['campaign_summary'][$campaign_name]['conversion_rate'] = wpd_calculate_percentage( $categorized_data['campaign_summary'][$campaign_name]['transactions'], $categorized_data['campaign_summary'][$campaign_name]['session_count'], 2 );
                $categorized_data['campaign_summary'][$campaign_name]['channel_percent'] = wpd_calculate_percentage( $categorized_data['campaign_summary'][$campaign_name]['session_count'], $totals['sessions'], 2 );
                $categorized_data['campaign_summary'][$campaign_name]['average_session_duration'] = wpd_divide( $categorized_data['campaign_summary'][$campaign_name]['total_session_duration'], $categorized_data['campaign_summary'][$campaign_name]['session_count'], 2 );
                $categorized_data['campaign_summary'][$campaign_name]['page_views_per_session'] = wpd_divide( $categorized_data['campaign_summary'][$campaign_name]['page_views'], $categorized_data['campaign_summary'][$campaign_name]['session_count'], 2 );
            }
        } else {
            $categorized_data['campaign_summary']['no_campaign_data_found'] = $analytics_performance_container;
        }
        // Some cleaning - Landing Page Data
        if ( is_array($categorized_data['landing_page_summary']) && ! empty($categorized_data['landing_page_summary']) ) {
            foreach($categorized_data['landing_page_summary'] as $page_view_href => $page_data) {
                $categorized_data['landing_page_summary'][$page_view_href]['user_count'] = count( $page_data['user_count'] );
                $categorized_data['landing_page_summary'][$page_view_href]['session_count'] = count( $page_data['session_count'] );
                $categorized_data['landing_page_summary'][$page_view_href]['conversion_rate'] = wpd_calculate_percentage( $categorized_data['landing_page_summary'][$page_view_href]['transactions'], $categorized_data['landing_page_summary'][$page_view_href]['session_count'], 2 );
                $categorized_data['landing_page_summary'][$page_view_href]['channel_percent'] = wpd_calculate_percentage( $categorized_data['landing_page_summary'][$page_view_href]['session_count'], $totals['sessions'], 2 );
                $categorized_data['landing_page_summary'][$page_view_href]['average_session_duration'] = wpd_divide( $categorized_data['landing_page_summary'][$page_view_href]['total_session_duration'], $categorized_data['landing_page_summary'][$page_view_href]['session_count'], 2 );
                $categorized_data['landing_page_summary'][$page_view_href]['page_views_per_session'] = wpd_divide( $categorized_data['landing_page_summary'][$page_view_href]['page_views'], $categorized_data['landing_page_summary'][$page_view_href]['session_count'], 2 );
            }
        } else {
            $categorized_data['landing_page_summary']['no_landing_page_data_found'] = $analytics_performance_container;
        }
        // Some cleaning - Referral URL Data
        if ( is_array($categorized_data['referral_url_summary']) && ! empty($categorized_data['referral_url_summary']) ) {
            foreach($categorized_data['referral_url_summary'] as $page_view_href => $page_data) {
                $categorized_data['referral_url_summary'][$page_view_href]['user_count'] = count( $page_data['user_count'] );
                $categorized_data['referral_url_summary'][$page_view_href]['session_count'] = count( $page_data['session_count'] );
                $categorized_data['referral_url_summary'][$page_view_href]['conversion_rate'] = wpd_calculate_percentage( $categorized_data['referral_url_summary'][$page_view_href]['transactions'], $categorized_data['referral_url_summary'][$page_view_href]['session_count'], 2 );
                $categorized_data['referral_url_summary'][$page_view_href]['channel_percent'] = wpd_calculate_percentage( $categorized_data['referral_url_summary'][$page_view_href]['session_count'], $totals['sessions'], 2 );
                $categorized_data['referral_url_summary'][$page_view_href]['average_session_duration'] = wpd_divide( $categorized_data['referral_url_summary'][$page_view_href]['total_session_duration'], $categorized_data['referral_url_summary'][$page_view_href]['session_count'], 2 );
                $categorized_data['referral_url_summary'][$page_view_href]['page_views_per_session'] = wpd_divide( $categorized_data['referral_url_summary'][$page_view_href]['page_views'], $categorized_data['referral_url_summary'][$page_view_href]['session_count'], 2 );
            }
        } else {
            $categorized_data['referral_url_summary']['no_referral_url_data_found'] = $analytics_performance_container;
        }
        // Some cleaning - Products
        if ( is_array($categorized_data['product_summary']) && ! empty($categorized_data['product_summary']) ) {
            foreach( $categorized_data['product_summary'] as $product_id => $product_data ) {

                // Copy the main product datas user count, session count, product clicks, product page views into the variration,
                // We will keep the add to cart, transactions, qty purchased and total value as they have been fetched.
                if ( $product_data['variation_id'] > 0 ) {
                    $parent_data = $categorized_data['product_summary'][$product_data['product_id']] ?? null;
                    if ( $parent_data ) {
                        $parent_user_count = ( is_array($parent_data['user_count']) ) ? count($parent_data['user_count']) : (int)$parent_data['user_count'];
                        $parent_session_count = ( is_array($parent_data['session_count']) ) ? count($parent_data['session_count']) : (int)$parent_data['session_count'];
                        $parent_product_clicks = ( is_array($parent_data['product_clicks']) ) ? count($parent_data['product_clicks']) : (int)$parent_data['product_clicks'];
                        $parent_product_page_views = ( is_array($parent_data['product_page_views']) ) ? count($parent_data['product_page_views']) : (int)$parent_data['product_page_views'];
                        $categorized_data['product_summary'][$product_id]['user_count'] = $parent_user_count;
                        $categorized_data['product_summary'][$product_id]['session_count'] = $parent_session_count;
                        $categorized_data['product_summary'][$product_id]['product_clicks'] = $parent_product_clicks;
                        $categorized_data['product_summary'][$product_id]['product_page_views'] = $parent_product_page_views;
                    }
                } else {
                    $categorized_data['product_summary'][$product_id]['user_count'] = count($product_data['user_count']);
                    $categorized_data['product_summary'][$product_id]['session_count'] = count($product_data['session_count']);
                }

                // Few more calculations
                $categorized_data['product_summary'][$product_id]['conversion_rate'] = wpd_calculate_percentage( $product_data['transactions'],$categorized_data['product_summary'][$product_id]['session_count'], 2 );
                $categorized_data['product_summary'][$product_id]['percent_of_sessions_with_add_to_cart'] = wpd_calculate_percentage( $product_data['add_to_cart'], $categorized_data['product_summary'][$product_id]['session_count'], 2 );

            }

            // Loop again to transform
            foreach( $categorized_data['product_summary'] as $product_id => $product_data ) {
                $categorized_data['product_summary'][$product_data['product_name']] = $categorized_data['product_summary'][$product_id];
                unset($categorized_data['product_summary'][$product_id]);
            }

        } else {
            $categorized_data['product_summary'] = array(
                'no_products_found' => array(
                    'user_count' => 0,
                    'session_count' => 0,
                    'product_clicks' => 0,
                    'product_page_views' => 0,
                    'add_to_cart' => 0,
                    'add_to_cart_per_session' => 0,
                    'transactions' => 0,
                    'qty_purchased' => 0,
                    'total_value' => 0
                    )
                );
        }
        // Some cleaning - Page View Data
        if ( is_array($categorized_data['page_view_summary']) && ! empty($categorized_data['page_view_summary']) ) {
            foreach($categorized_data['page_view_summary'] as $page_view_href => $page_data) {
                $categorized_data['page_view_summary'][$page_view_href]['session_count'] = count( $page_data['session_count'] );
                $categorized_data['page_view_summary'][$page_view_href]['user_count'] = count( $page_data['user_count'] );
            }
        } else {
            $categorized_data['page_view_summary'] = array(
                'no_page_views_found' => array(
                    'session_count' => 0,
                    'user_count'    => 0,
                    'views'         => 0,
                    'transactions'  => 0,
                    'revenue'       => 0
                )
            );
        }

        // Enrich the form submissions
        if ( is_array($categorized_data['form_submits_by_id_summary']) && ! empty($categorized_data['form_submits_by_id_summary']) ) {
            foreach( $categorized_data['form_submits_by_id_summary'] as $form_id => $form_data ) {
                $categorized_data['form_submits_by_id_summary'][$form_id]['conversion_rate'] = wpd_calculate_percentage( $form_data['sessions_with_submission'], $totals['sessions'], 2 );
            }
        }

        // Do total calculations
        $number_of_days = $this->data_by_date_containers['n_days_period'];
        $totals['average_session_duration'] = wpd_divide( $totals['session_duration'], $totals['sessions'], 2 );
        $totals['sessions_per_day'] = wpd_divide( $totals['sessions'], $number_of_days, 2 );
        $totals['users_per_day'] = wpd_divide( $totals['users'], $number_of_days, 2 );
        $totals['page_views_per_session'] = wpd_divide( $totals['page_views'], $totals['sessions'], 2 );
        $totals['events_per_session'] = wpd_divide( $totals['non_page_view_events'], $totals['sessions'], 2 );
        $totals['percent_sessions_with_category_view'] = wpd_calculate_percentage( $totals['sessions_with_category_page_views'], $totals['sessions'], 2 );
        $totals['percent_sessions_with_product_page_view'] = wpd_calculate_percentage( $totals['sessions_with_product_page_views'], $totals['sessions'], 2 );
        $totals['percent_sessions_with_add_to_cart'] = wpd_calculate_percentage( $totals['sessions_with_add_to_cart'], $totals['sessions'], 2 );
        $totals['percent_sessions_with_initiate_checkout'] = wpd_calculate_percentage( $totals['sessions_with_initiate_checkout'], $totals['sessions'], 2 );
        $totals['percent_sessions_with_form_submit'] = wpd_calculate_percentage( $totals['sessions_with_form_submit'], $totals['sessions'], 2 );
        $totals['conversion_rate'] = wpd_calculate_percentage( $totals['transactions'], $totals['sessions'], 2 );

        // Conversion funnel summary
        $categorized_data['conversion_funnel_summary']['sessions']['count'] = $totals['sessions'];
        $categorized_data['conversion_funnel_summary']['sessions']['percent'] = 100.00;
        $categorized_data['conversion_funnel_summary']['category_page_view']['count'] = $totals['sessions_with_category_page_views'];
        $categorized_data['conversion_funnel_summary']['category_page_view']['percent'] = $totals['percent_sessions_with_category_view'];
        $categorized_data['conversion_funnel_summary']['product_page_views']['count'] = $totals['sessions_with_product_page_views'];
        $categorized_data['conversion_funnel_summary']['product_page_views']['percent'] = $totals['percent_sessions_with_product_page_view'];
        $categorized_data['conversion_funnel_summary']['add_to_carts']['count'] = $totals['sessions_with_add_to_cart'];
        $categorized_data['conversion_funnel_summary']['add_to_carts']['percent'] = $totals['percent_sessions_with_add_to_cart'];
        $categorized_data['conversion_funnel_summary']['initiate_checkouts']['count'] = $totals['sessions_with_initiate_checkout'];
        $categorized_data['conversion_funnel_summary']['initiate_checkouts']['percent'] = $totals['percent_sessions_with_initiate_checkout'];
        $categorized_data['conversion_funnel_summary']['transactions_complete']['count'] = $totals['sessions_with_transaction'];
        $categorized_data['conversion_funnel_summary']['transactions_complete']['percent'] = $totals['conversion_rate'];

        // Calculate execution time
        $execution_time = microtime(true) - $start_time + $this->get_data('analytics', 'execution_time');

        // Configure return object
        $analytics_data = array(
            'totals' => $totals,
            'categorized_data' => $categorized_data,
            'data_by_date' => $data_by_date,
            'data_table' => array(
                'sessions' => $session_data_table,
                'products' => $categorized_data['product_summary']
            ),
            'execution_time' => $execution_time
        );

        // Store the data into the prop
        $this->set_data( 'analytics', $analytics_data );

        // Return results (will return all relevant props also stored by organised_analytics_data)
        return $this->get_data( 'analytics' );

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
        $converted_date = date( $format, $timestamp );

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

        // If we are doing minutes, this is a special case
        if ( $this->get_filter('date_format_display') == 'minute' ) {
            $timestamp = strtotime($date);
            $minutes_ago = floor((current_time('timestamp') - $timestamp) / 60);
            return (int) $minutes_ago;
        }

        $date_container_date_format = $this->get_filter('date_format_string');
        $formatted_date = date( $date_container_date_format, strtotime($date) );

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

        $traffic_type = new WPD_Traffic_Type( $referral_url, $query_parameters );
        return $traffic_type->determine_traffic_source();

    }

    /**
     * 
     *  Logs general messages and optionally errors to:
     *  wpd_google_ads_api_log.txt and wpd_google_ads_api_error_log.txt
     * 
     *  @param string|array|WP_Error $message the content to print to the log
     *  @param bool $error Set to true if you want to log this to the error log in addition to the general api log
     *  @return void
     * 
     **/
    private function log( $message, $error = false ) {

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
            wpd_write_log( 'Backtrace function: ' . $last_call, 'data_warehouse_error' );
            wpd_write_log( $message, 'data_warehouse_error' );
            
        }

        // Log the message
        wpd_write_log( $message, 'data_warehouse' );

        return $message;

    }

}