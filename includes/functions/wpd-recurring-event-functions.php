<?php
/**
 *
 * Recurring functions that are run by WPD_Action_Scheduler and functions that will trigger these one off events
 *
 * @package Alpha Insights
 * @version 4.4.0
 * @since 4.4.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * 
 *  Will fetch for the most recent N orders that don't have a cache and build that cache
 * 
 *  @return bool|int Will return false on failure, true if cache is complete and order count representing number of updates
 * 
 **/
function wpd_fetch_and_store_last_n_uncached_orders_cron() {

    // Start timer
	$start = microtime( true );
    $memory_usage = 0;

    wpd_write_log( 'Executing wpd_schedule_order_calculation_cache_collector', 'cron' );
    wpd_write_log( 'Executing wpd_schedule_order_calculation_cache_collector', 'cache' );

    // Check if cache is complete
    $order_cache_complete = get_option( 'wpd_ai_all_orders_cached', 0 );
    if ( $order_cache_complete == 1 ) {

        wpd_write_log( 'Order cache has already been determined as complete, not continuing.', 'cron' );
        wpd_write_log( 'Completed wpd_schedule_order_calculation_cache_collector.', 'cron' );
        wpd_write_log( 'Order cache has already been determined as complete, not continuing.', 'cache' );
        wpd_write_log( 'Completed wpd_schedule_order_calculation_cache_collector.', 'cache' );

        return true; 

    }

    // Get last n orders
    $max_count = 250;

    wpd_write_log( 'Searching database for the latest ' . $max_count . ' orders that dont have the cache set.', 'cron' );
    wpd_write_log( 'Searching database for the latest ' . $max_count . ' orders that dont have the cache set.', 'cache' );

    $last_n_orders_without_cache = wpd_get_order_ids_without_calculation_cache( $max_count );

    // Safety Check
    if ( ! is_array($last_n_orders_without_cache) ) {

        wpd_write_log( 'Couldnt produce an array when searching for orders without cache, execution stopping.', 'cron' );
        wpd_write_log( 'Completed wpd_schedule_order_calculation_cache_collector.', 'cron' );
        wpd_write_log( 'Couldnt produce an array when searching for orders without cache, execution stopping.', 'cache' );
        wpd_write_log( 'Completed wpd_schedule_order_calculation_cache_collector.', 'cache' );

        return false; 
    }

    wpd_write_log( 'Found ' . count( $last_n_orders_without_cache ) . ' orders with max search count of ' . $max_count, 'cron' );
    wpd_write_log( 'Found ' . count( $last_n_orders_without_cache ) . ' orders with max search count of ' . $max_count, 'cache' );

    // If we've got some, update the calculations
    if ( count( $last_n_orders_without_cache ) > 0 ) {

        wpd_write_log( 'Looping throgh ' . count( $last_n_orders_without_cache ) . ' orders to update the cache.', 'cron' );
        wpd_write_log( 'Looping throgh ' . count( $last_n_orders_without_cache ) . ' orders to update the cache.', 'cache' );

        foreach( $last_n_orders_without_cache as $order_id ) {

            // Log the memory usage
            if ( wpd_get_peak_memory_usage() > $memory_usage ) $memory_usage = wpd_get_peak_memory_usage();

            wpd_calculate_cost_profit_by_order( $order_id, true );

        }

        $finish             = microtime( true );
        $total_time_elapsed = round( $finish - $start, 2 );

        wpd_write_log( 'Completed wpd_schedule_order_calculation_cache_collector. Process took ' . $total_time_elapsed . ' seconds and ' . $memory_usage . ' memory usage.', 'cron' );
        wpd_write_log( 'Completed wpd_schedule_order_calculation_cache_collector. Process took ' . $total_time_elapsed . ' seconds and ' . $memory_usage . ' memory usage.', 'cache' );

        return count( $last_n_orders_without_cache );

    } else {

        wpd_write_log( 'Count of orders was 0, so we must be finished processing the order cache, updating the database to complete.', 'cron' );
        wpd_write_log( 'Completed wpd_schedule_order_calculation_cache_collector.', 'cron' );
        wpd_write_log( 'Count of orders was 0, so we must be finished processing the order cache, updating the database to complete.', 'cache' );
        wpd_write_log( 'Completed wpd_schedule_order_calculation_cache_collector.', 'cache' );

        $finish             = microtime( true );
        $total_time_elapsed = round( $finish - $start, 2 );

        // Log the memory usage
        if ( wpd_get_peak_memory_usage() > $memory_usage ) $memory_usage = wpd_get_peak_memory_usage();

        // Set order cache as complete
        $order_cache_complete = update_option( 'wpd_ai_all_orders_cached', 1 );

        // Delete the transient if it happens to exist
        delete_transient('_wpd_updating_all_orders_cache');

        // Return success
        return true;

    }

}

/**
 * 
 *  Hourly cron schedule to update customer analytics in batches
 * 
 *  Will fetch info like total sales etc etc
 * 
 **/
function wpd_collect_customer_statistics_cron() {

    wpd_write_log( 'Executing wpd_schedule_customer_analytics_collector', 'cron' );

    // Start timer
	$start = microtime( true );
    $memory_usage = 0;

    // List of user Id's
    $user_ids =  wpd_query_user_ids_for_analytics_collector( 25 ); // Process 25 users at a time
    
    // Log start
	wpd_write_log( 'Executing Customer Analytics cache builder on ' . count( $user_ids ) . ' users', 'cache' );
	wpd_write_log( 'Executing Customer Analytics cache builder on ' . count( $user_ids ) . ' users', 'cron' );

    // Safety check the data
    if ( is_array($user_ids) && ! empty($user_ids) ) {

        $user_count = count( $user_ids );
        $i = 1;

        // Entry logging
        wpd_write_log( 'We have an array of User IDs, ' . $user_count . ' users found.', 'cron' );
        wpd_write_log( 'Looping through User IDs to fetch customer analytics.', 'cron' );

        // Loop through users
        foreach( $user_ids as $user_id ) {

            // False = force refresh data collection and update transients and meta
            $user_analytics = wpd_fetch_customer_analytics_by_user_id( $user_id, false );

            // Log iteration
            wpd_write_log( '(' . $i . '/' . $user_count . ') Calculating analytics for user id: ' . $user_id . '.', 'cron' );

            if ( is_array($user_analytics) && ! empty( $user_analytics ) ) {

                wpd_write_log( 'Update succesful.', 'cron' );
    
            }

            // Log the memory usage
            if ( wpd_get_peak_memory_usage() > $memory_usage ) $memory_usage = wpd_get_peak_memory_usage();

            // Iterator
            $i++;

        }

    } else {

        wpd_write_log( 'We couldnt find a good data collection, the format wasnt an array or it was empty. Couldnt find user IDs? Check debug.log.', 'cron' );

    }

    $finish             = microtime( true );
	$total_time_elapsed = round( $finish - $start, 2 );

    wpd_write_log( 'Completed wpd_schedule_customer_analytics_collector. Process took ' . $total_time_elapsed . ' seconds and ' . $memory_usage . ' memory usage.', 'cron' );
    wpd_write_log( 'Completed wpd_schedule_customer_analytics_collector. Process took ' . $total_time_elapsed . ' seconds and ' . $memory_usage . ' memory usage.', 'cache' );

}

/**
 * 
 *  Hourly schedule to update product analytics
 * 
 *  Will fetch info like total sales etc etc
 * 
 **/
function wpd_collect_product_statistics_cron() {

    // Start timer
	$start          = microtime( true );
    $memory_usage   = 0;

    wpd_write_log( 'Executing wpd_schedule_product_analytics_collector', 'cron' );
    wpd_write_log( 'This function will store sales data and reference the analytics database to check for website activity and store in the DB.', 'cron' );
    wpd_write_log( 'Collecting product IDs', 'cron' );
    
    // List of product Id's
    $product_ids = wpd_query_product_ids_for_analytics_collector( 25 ); // Collect 25 products

    // Make sure the data is correct
    if ( is_array($product_ids) && ! empty($product_ids) ) {

        $product_count = count( $product_ids );
        $i = 1;

        // Entry logging
        wpd_write_log( 'We have an array of product IDs, ' . $product_count . ' products found.', 'cron' );
        wpd_write_log( 'Looping through product IDs to fetch product analytics.', 'cron' );

        foreach( $product_ids as $product_id ) {

            // False = force refresh data collection and update transients and meta
            $product_analytics = wpd_fetch_product_analytics_by_product_id( $product_id, false );

            // Log iteration
            wpd_write_log( '(' . $i . '/' . $product_count . ') Calculating analytics for product id: ' . $product_id . '.', 'cron' );

            if ( is_array($product_analytics) && ! empty( $product_analytics ) ) {

                wpd_write_log( 'Update succesful.', 'cron' );
    
            }

            // Log the memory usage
            if ( wpd_get_peak_memory_usage() > $memory_usage ) $memory_usage = wpd_get_peak_memory_usage();

            // Iterator
            $i++;
    
        }

    } else {

        wpd_write_log( 'We couldnt find a good data collection, the format wasnt an array or it was empty. Couldnt find product IDs? Check debug.log.', 'cron' );
        
    }

    $finish             = microtime( true );
	$total_time_elapsed = round( $finish - $start, 2 );

    wpd_write_log( 'Completed wpd_schedule_product_analytics_collector. Process took ' . $total_time_elapsed . ' seconds and ' . $memory_usage . ' memory usage.', 'cron' );
    wpd_write_log( 'Completed wpd_schedule_product_analytics_collector. Process took ' . $total_time_elapsed . ' seconds and ' . $memory_usage . ' memory usage.', 'cache' );

}

/**
 * 
 *  Check DB upgrade once daily 
 * 
 **/
function wpd_schedule_database_upgrade_function() {

    wpd_write_log( 'Executing wpd_schedule_database_upgrade', 'cron' );

    if ( ! class_exists('WPD_Database_Interactor') ) {
        require_once( WPD_AI_PATH . 'includes/classes/WPD_Database_Interactor.php');
    }

    $db_interactor = new WPD_Database_Interactor();

    if ( is_object( $db_interactor ) && method_exists( $db_interactor, 'create_update_tables_columns' ) ) {

        $db_interactor->create_update_tables_columns();

    }

    wpd_write_log( 'Completed wpd_schedule_database_upgrade', 'cron' );

}

/**
 * 
 *  Daily task runner
 * 
 *  Runs a task once day that just checks for tasks that have not been run before
 * 
 *  This is for once off tasks, usually minor migration related things
 * 
 **/
function wpd_schedule_daily_task_runner_once_off_function() {

    // Run the task runner class
    $task_runner = new WPD_Task_Runner();

    // Run incomplete tasks
    $task_runner->run_incomplete_tasks();

}

/**
 * 
 *  Gets rid of bot traffic, crawlers, and other dodgy looking data 
 * 
 **/
function wpd_schedule_analytics_db_cleanup_function() {

    $days = 2;
    
    wpd_write_log( 'Executing wpd_schedule_analytics_db_cleanup for the past ' . $days . ' days', 'cron' );

    // Clean up data from the last N days
    wpd_cleanup_analytics_data( $days );

    wpd_write_log( 'wpd_schedule_analytics_db_cleanup', 'cron' );


}

/**
 * 
 * 	Analytics Database Cleanup
 * 	
 * 	This function will scan the two analytics tables and remove anything that looks like spam, bots, crawlers or incomplete data.
 *  Will default to the last 30 days of data and can be overriden, typically used in the daily cron schedule to keep the DB clean
 * 
 * 	@param int $days_ago Number of days back to fetch through the database
 *  @return mixed Will return bool TRUE on succesful execution, otherwise will return a string with DB error if an error was triggered in execution
 *  @author WP Davies - Christopher Davies
 *  @since 2.0.50
 *  
 **/
function wpd_cleanup_analytics_data( $days_ago = 30 ) {

	global $wpdb;

	// Collect Vars
	$wpd_db = new WPD_Database_Interactor();
	$woo_events_table = $wpd_db->events_table;
	$session_data_table = $wpd_db->session_data_table;

	if ( ! $days_ago ) $days_ago = 30;

	$days_ago_string = '-' . strval($days_ago) . ' days';

	wpd_write_log( 'Cleaning up analytics data in the last ' . $days_ago_string, 'db_cleanup' );

	// Number of days to check
	$start_date = date("Y-m-d H:i:s", strtotime($days_ago_string));

	// Prepare query using wpdb->prepare() for security
	$sql_query = $wpdb->prepare(
		"SELECT * FROM $session_data_table AS session_data WHERE date_created_gmt >= %s",
		$start_date
	);

	// Fetch Results
	$results = $wpdb->get_results( $sql_query, 'ARRAY_A' );

	// Sessions deleted
	$sessions_deleted = 0;

	if ( $wpdb->last_error  ) {

		wpd_write_log( 'Error capturing analytics data from DB, dumping the error and query.', 'db_error' );
		wpd_write_log( $wpdb->last_error, 'db_error' );
		wpd_write_log( $wpdb->last_query, 'db_error' );

		return $wpdb->last_error;

	}

	// Do some manual cleaning on bots, crawlers, and incomplete sessions
	if ( is_array($results) && ! empty($results) ) {

		wpd_write_log( 'Found ' . count( $results ) . ' sessions that we will check for bots, crawlers and incomplete data. ', 'db_cleanup' );

		foreach( $results as $row ) {

			$session_id = $row['session_id']; // Unique ID for deleting rows

			// Make sure we've got a session ID
			if ( empty($session_id) || ! is_string($session_id) ) {
				continue;
			}

			// Remove Bots & Crawlers if they got through
			if ( isset( $row['additional_data'] ) ) {

				// If the additional data we are checking contains the user agent data
				$additional_data = json_decode( $row['additional_data'], true );
				if ( isset($additional_data['raw_user_agent_data']) && ! empty($additional_data['raw_user_agent_data']) ) {

					$user_agent = strtolower( $additional_data['raw_user_agent_data'] );

					// Remove bots
					if ( strpos( $user_agent, 'bot') !== false ) {

						// Delete the results
						$wpdb->delete( $woo_events_table, array( 'session_id' => $session_id ) );
						$wpdb->delete( $session_data_table, array( 'session_id' => $session_id ) );
						$sessions_deleted++;
						continue;

					}

					// Remove crawlers
					if ( strpos( $user_agent, 'crawler') !== false ) {

						// Delete the results
						$wpdb->delete( $woo_events_table, array( 'session_id' => $session_id ) );
						$wpdb->delete( $session_data_table, array( 'session_id' => $session_id ) );
						$sessions_deleted++;
						continue;

					}

				}
			}

			// Remove all events without landing pages
			if ( array_key_exists('landing_page', $row) && empty($row['landing_page']) ) {
				$wpdb->delete( $woo_events_table, array( 'session_id' => $session_id ) );
				$wpdb->delete( $session_data_table, array( 'session_id' => $session_id ) );	
				$sessions_deleted++;
				continue;		
			}
		}
	} else {

		wpd_write_log( 'Didnt find any data to cleanup, check the db_error log to see if there was an issue capturing data. ', 'db_cleanup' );

	}

	// Logging sessions deleted
	if ( $sessions_deleted > 0 ) {
		wpd_write_log( $sessions_deleted . ' Sessions were deleted from your database.', 'db_cleanup' );
	} else {
		wpd_write_log( 'No sessions were deleted from your database, everything looks clean.', 'db_cleanup' );
	}

	wpd_write_log( 'Now we\'ll just remove any sessions or events that don\'t have corresponding data in the other table.', 'db_cleanup' );

	// Events stored that have no session data
	$sql_query = "SELECT session_id
	FROM $woo_events_table
	WHERE session_id NOT IN
		(SELECT session_id 
		FROM $session_data_table)
	AND date_created_gmt >= '$start_date' GROUP BY session_id";

	// Execute Query
	$results = $wpdb->get_results( $sql_query, 'ARRAY_A' );
	if ( $wpdb->last_error  ) {

		wpd_write_log( 'Error capturing analytics data from DB, dumping the error and query.', 'db_error' );
		wpd_write_log( $wpdb->last_error, 'db_error' );
		wpd_write_log( $wpdb->last_query, 'db_error' );

		return $wpdb->last_error;

	}
	if ( is_array($results) && ! empty($results) ) {
		foreach( $results as $array_key => $row ) {
			$session_id = $row['session_id']; // Unique ID for deleting rows
			if ( ! empty($session_id) && is_string($session_id) ) {
				$wpdb->delete( $woo_events_table, array( 'session_id' => $session_id ) );
				$wpdb->delete( $session_data_table, array( 'session_id' => $session_id ) );	
			}
		}
	}

	// Session stored that has no event data
	$sql_query = "SELECT session_id
	FROM $session_data_table
	WHERE session_id NOT IN
		(SELECT session_id 
		FROM $woo_events_table)
	AND date_created_gmt >= '$start_date' GROUP BY session_id";

	// Execute Query
	$results = $wpdb->get_results( $sql_query, 'ARRAY_A' );
	if ( $wpdb->last_error  ) {

		wpd_write_log( 'Error capturing analytics data from DB, dumping the error and query.', 'db_error' );
		wpd_write_log( $wpdb->last_error, 'db_error' );
		wpd_write_log( $wpdb->last_query, 'db_error' );

		return $wpdb->last_error;

	}
	if ( is_array($results) && ! empty($results) ) {
		foreach( $results as $array_key => $row ) {
			$session_id = $row['session_id']; // Unique ID for deleting rows
			if ( ! empty($session_id) && is_string($session_id) ) {
				$wpdb->delete( $woo_events_table, array( 'session_id' => $session_id ) );
				$wpdb->delete( $session_data_table, array( 'session_id' => $session_id ) );	
			}
		}
	}

	wpd_write_log( 'Cleanup complete, your analytics database has now been cleaned.', 'db_cleanup' );

	return true;

}

/**
 * 
 *  Deletes any log files that are larger than 10mb - daily
 * 
 **/
function wpd_schedule_log_cleanup_function() {

    // Log init
    wpd_write_log( 'Executing wpd_schedule_log_cleanup', 'cron' );

    // Cleanup all logs larger than 10mb
    $deletion_count = wpd_delete_large_logs( 10 );

    // Log count
    wpd_write_log( $deletion_count . ' Log files were deleted.', 'cron' );

    // Log completion
    wpd_write_log( 'Completed wpd_schedule_log_cleanup', 'cron' );

}

/**
 *
 *  Check and refresh license status once a week
 *
 */
function wpd_schedule_license_check_function() {

    wpd_write_log( 'Executing wpd_schedule_license_check', 'cron' );

    $authenticator = new WPD_Authenticator();
    $license_update = $authenticator->update_license_status();

	if ( $license_update ) {

        wpd_write_log( 'License check returned the following results: ' . $license_update, 'cron' );
        wpd_write_log( 'License check returned the following results: ' . $license_update, 'license' );

	} else {

        // Notify WP Davies if there's an issue with the license check
        $license_key = get_option( 'wpd_ai_api_key' );
        $license_status = get_option( 'wpd_ai_license_status' );
        $message = 'Failed to check license status for website: ' . get_site_url() . '.';
        $message .= ' License Key: ' . $license_key . '.';
        $message .= ' License Status: ' . $license_status . '.';

        wpd_write_log( 'License check failed, dumping current results.', 'cron' );
        wpd_write_log( $message, 'cron' );

        wpd_write_log( 'License check failed. ', 'license' );

        // Notify admin
        wp_mail('support@wpdavies.dev', 'Failed License Check', $message);

    }

    wpd_write_log( 'Completed wpd_schedule_license_check', 'cron' );

}

/**
 *
 *  Fetch new exchange rates
 *
 */
function wpd_schedule_webhook_post() {

    wpd_write_log( 'Executing wpd_schedule_webhook', 'cron' );

    /**
     *
     *  Only take this seriously if we're at the 1st hour
     *
     */
    $site_time_hour     = (int) wpd_site_date_time( 'H' );
    $site_day_of_week   = wpd_site_date_time( 'D' );
    $site_day_of_month  = (int) wpd_site_date_time( 'j' );

    // Webhook export
    $webhook_settings   = get_option( 'wpd_ai_webhook_settings' );

    // Schedule
    if ( isset($webhook_settings['webhook_schedule']) ) {
        $webhook_schedule   = $webhook_settings['webhook_schedule'];
    }
    // URL
    if ( isset( $webhook_settings['webhook_url'] ) ) {

        $webhook_url        = $webhook_settings['webhook_url'];

    } else {

        wpd_write_log( 'Not sending webhook post as there is no URL set.', 'cron' );

        return false; // No URL to post to

    }

    /**
     *
     *  Fix critical error
     *  Make convert_to_screen() available for WP_List class
     *
     */
    if ( ! function_exists('convert_to_screen') ) {
        require_once( ABSPATH . 'wp-admin/includes/admin.php' );
    }

    /**
     *
     *  Daily Webhook
     *
     */
    if ( $site_time_hour === 1 ) {

        $daily_from_date    = wpd_site_date_time( WPD_AI_PHP_ISO_DATE, 'yesterday' );
        $daily_to_date      = wpd_site_date_time( WPD_AI_PHP_ISO_DATE, 'yesterday' );

        // Send webhook request
        if ( $webhook_schedule === 'daily' ) {

            // Make webhook request
            wpd_write_log( 'Executing daily webhook post', 'webhook' );
            wpd_write_log( 'Executing daily webhook post', 'cron' );

            $webhook_response = wpd_webhook_post_data( $daily_from_date, $daily_to_date );

            wpd_write_log( $webhook_response, 'cron' );

        }

    } 
    /**
     *
     *  Weekly Webhook
     *
     */
    if ( $site_time_hour === 1 && $site_day_of_week === 'Mon' ) {

        $weekly_from_date   = wpd_site_date_time( WPD_AI_PHP_ISO_DATE, 'Monday last week' );
        $weekly_to_date     = wpd_site_date_time( WPD_AI_PHP_ISO_DATE, 'Sunday last week' );

        // Send webhook request
        if ( $webhook_schedule === 'weekly' ) {

            // Make webhook request
            wpd_write_log( 'Executing weekly webhook post', 'webhook' );
            wpd_write_log( 'Executing weekly webhook post', 'cron' );

            $webhook_response = wpd_webhook_post_data( $weekly_from_date, $weekly_to_date );

            wpd_write_log( $webhook_response, 'cron' );

        }


    } 

    /**
     *
     *  Monthly Webhook
     *
     */
    if ( $site_time_hour === 1 && $site_day_of_month === 1 ) {

        $monthly_from_date  = wpd_site_date_time( WPD_AI_PHP_ISO_DATE, 'first day of last month' );
        $monthly_to_date    = wpd_site_date_time( WPD_AI_PHP_ISO_DATE, 'last day of last month' );

        // Send webhook request
        if ( $webhook_schedule === 'monthly' ) {

            // Make webhook request
            wpd_write_log( 'Executing monthly webhook post', 'webhook' );
            wpd_write_log( 'Executing monthly webhook post', 'cron' );

            $webhook_response = wpd_webhook_post_data( $monthly_from_date, $monthly_to_date );

            wpd_write_log( $webhook_response, 'cron' );

        }

    }

    wpd_write_log( 'Complete wpd_schedule_webhook.', 'cron' );

}

function wpd_schedule_emails_function() {

	/**
	 *
	 *	Only take this seriously if we're at the 8th hour
	 *
	 */
	$site_time_hour     = (int) wpd_site_date_time( 'H' );
	$site_day_of_week 	= wpd_site_date_time( 'D' );
	$site_day_of_month 	= (int) wpd_site_date_time( 'j' );
	$email_settings 	= get_option( 'wpd_ai_email_settings' );

    // Check if we've sent emails
    $daily_emails_sent      = get_option( 'wpd_ai_daily_emails_sent' ); // Check from date
    $weekly_emails_sent     = get_option( 'wpd_ai_weekly_emails_sent' ); // Check from date
    $monthly_emails_sent    = get_option( 'wpd_ai_monthly_emails_sent' ); // Check from date

    /**
     *
     *  Fix critical error
     *	Make convert_to_screen() available for WP_List class
     *
     */
    if ( ! function_exists('convert_to_screen') ) {
        require_once( ABSPATH . 'wp-admin/includes/admin.php' );
    }
    if ( ! class_exists( 'WP_List_Table' ) ) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
    }

    /**
     *
     *	Daily Emails
     *
     */
	$daily_from_date 	= wpd_site_date_time( WPD_AI_PHP_ISO_DATE, 'yesterday' );
	$daily_to_date 		= wpd_site_date_time( WPD_AI_PHP_ISO_DATE, 'yesterday' );

    // Only send once
    if ( $daily_emails_sent != $daily_from_date ) {

    	// Send profit report
    	if ( isset( $email_settings['profit-report']['frequency']['daily'] ) && $email_settings['profit-report']['frequency']['daily'] ) {

    		wpd_email( 'wpd_profit_report', false, array(

    			'from_date' => $daily_from_date,
    			'to_date' 	=> $daily_to_date,
    			/* translators: %s: Plugin or site name */
    			'subject' 	=> sprintf( __( 'Your Daily Profit Report From %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'Alpha Insights' ),

    			)

    		);

    	}

    	// Send inventory report
    	// if ( $email_settings['inventory-report']['frequency']['daily'] ) {

    	// 	wpd_email( 'wpd_inventory_report', false, array(

    	// 		'from_date' => $daily_from_date,
    	// 		'to_date' 	=> $daily_to_date,
    	// 		'subject' 	=> sprintf( __( 'Your Daily Inventory Report From %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'Alpha Insights' ),

    	// 		) 
    	// 	);

    	// }

    	// Send expense report
    	if ( isset( $email_settings['expense-report']['frequency']['daily'] ) && $email_settings['expense-report']['frequency']['daily'] ) {

    		wpd_email( 'wpd_expense_report', false, array(

    			'from_date' => $daily_from_date,
    			'to_date' 	=> $daily_to_date,
    			/* translators: %s: Plugin or site name */
    			'subject' 	=> sprintf( __( 'Your Daily Expense Report From %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'Alpha Insights' ),

    			) 
    		);

    	}

        // Save the day weve sent
        update_option( 'wpd_ai_daily_emails_sent', $daily_from_date );

    }

    /**
     *
     *	Weekly Email
     *
     */
    if ( $site_day_of_week === 'Mon' ) {

    	$weekly_from_date 	= wpd_site_date_time( WPD_AI_PHP_ISO_DATE, 'Monday last week' );
		$weekly_to_date 	= wpd_site_date_time( WPD_AI_PHP_ISO_DATE, 'Sunday last week' );

        // Only send once
        if (  $weekly_emails_sent != $weekly_from_date ) {

        	// Send profit report
        	if ( isset( $email_settings['profit-report']['frequency']['weekly'] ) && $email_settings['profit-report']['frequency']['weekly'] ) {

        		wpd_email( 'wpd_profit_report', false, array(

        			'from_date' => $weekly_from_date,
        			'to_date' 	=> $weekly_to_date,
        			/* translators: %s: Plugin or site name */
        			'subject' 	=> sprintf( __( 'Your Weekly Profit Report From %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'Alpha Insights' ),

        			) 
        		);

        	}

        	// Send inventory report
        	// if ( $email_settings['inventory-report']['frequency']['weekly'] ) {

        	// 	wpd_email( 'wpd_inventory_report', false, array(

        	// 		'from_date' => $weekly_from_date,
        	// 		'to_date' 	=> $weekly_to_date,
        	// 		'subject' 	=> sprintf( __( 'Your Weekly Inventory Report From %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'Alpha Insights' ),

        	// 		) 
        	// 	);

        	// }

        	// Send expense report
        	if ( isset( $email_settings['expense-report']['frequency']['weekly'] ) && $email_settings['expense-report']['frequency']['weekly'] ) {

        		wpd_email( 'wpd_expense_report', false, array(

        			'from_date' => $weekly_from_date,
        			'to_date' 	=> $weekly_to_date,
        			/* translators: %s: Plugin or site name */
        			'subject' 	=> sprintf( __( 'Your Weekly Expense Report From %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'Alpha Insights' ),

        			) 
        		);

        	}

            // Save the day weve sent
            update_option( 'wpd_ai_weekly_emails_sent', $weekly_from_date );

        }

    } 

    /**
     *
     *	Monthly Email
     *
     */
    if ( $site_day_of_month === 1 ) {

		$monthly_from_date 	= wpd_site_date_time( WPD_AI_PHP_ISO_DATE, 'first day of last month' );
		$monthly_to_date 	= wpd_site_date_time( WPD_AI_PHP_ISO_DATE, 'last day of last month' );

        // Only send once
        if ( $monthly_emails_sent != $monthly_from_date ) {

        	// Send profit report
        	if ( isset( $email_settings['profit-report']['frequency']['monthly'] ) && $email_settings['profit-report']['frequency']['monthly'] ) {

        		wpd_email( 'wpd_profit_report', false, array(

        			'from_date' => $monthly_from_date,
        			'to_date' 	=> $monthly_to_date,
        			/* translators: %s: Plugin or site name */
        			'subject' 	=> sprintf( __( 'Your Monthly Profit Report From %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'Alpha Insights' ),

        			) 
        		);

        	}

        	// Send inventory report
        	// if ( $email_settings['inventory-report']['frequency']['monthly'] ) {

        	// 	wpd_email( 'wpd_inventory_report', false, array(

        	// 		'from_date' => $monthly_from_date,
        	// 		'to_date' 	=> $monthly_to_date,
        	// 		'subject' 	=> sprintf( __( 'Your Monthly Inventory Report From %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'Alpha Insights' ),

        	// 		) 
        	// 	);

        	// }

        	// Send expense report
        	if ( isset( $email_settings['expense-report']['frequency']['monthly'] ) && $email_settings['expense-report']['frequency']['monthly'] ) {

        		wpd_email( 'wpd_expense_report', false, array(

        			'from_date' => $monthly_from_date,
        			'to_date' 	=> $monthly_to_date,
        			/* translators: %s: Plugin or site name */
        			'subject' 	=> sprintf( __( 'Your Monthly Expense Report From %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), 'Alpha Insights' ),

        			) 
        		);

        	}

            // Save the day weve sent
            update_option( 'wpd_ai_monthly_emails_sent', $monthly_from_date );

        }

    }

}

/**
 * 
 *  Fetch the Google Ads API data in the configured interval
 * 
 **/
function wpd_schedule_google_data_fetch_cron() {

    if ( ! WPD_AI_PRO ) return false;

    // Init the API
    $google_api = new WPD_Google_Ads_API();

    // Validate refresh token using auth class
    if ( class_exists('WPD_Google_Ads_Auth') ) {
        $google_auth = new WPD_Google_Ads_Auth();
        $google_auth->validate_refresh_token();
    }

    // Log the init
    $google_api->log('[Cron Schedule] Executing scheduled call to google API');
    wpd_write_log( 'Executing wpd_schedule_google_data_fetch', 'cron' );

    // Dont proceed if it's not configured
    if ( ! $google_api->is_configured() ) {
        wpd_write_log( 'API not configured, not going to run the API call', 'cron' );
        wpd_write_log( 'Completed wpd_schedule_google_data_fetch', 'cron' );
        $google_api->log( '[Cron Schedule] API not configured, not going to run the API call.');
        return false;
    }

    // Others
    $start = microtime(true);

    // Take action
    wpd_write_log( 'Updating campaign and expense data', 'cron' );
    $google_api->log( '[Cron Schedule] Updating campaign and expense data.');
    $google_api->create_update_campaign_and_expense_data();

    // Execution time
    $time_elapsed_secs = round( microtime(true) - $start, 2 );

    // Log duration
    wpd_write_log( 'Google API scheduled data fetch completed in ' . $time_elapsed_secs . ' seconds.', 'cron' );
    wpd_write_log( 'Completed wpd_schedule_google_data_fetch', 'cron' );
    $google_api->log( '[Cron Schedule] Google API scheduled data fetch completed in ' . $time_elapsed_secs . ' seconds.' );

    // No need to return anything really
    return true;

}

/**
 * 
 *  Hourly check for utm_campaigns for orders
 * 
 **/
function wpd_schedule_order_utm_campaign_check_meta_cron() {

    if ( ! WPD_AI_PRO ) return false;

    // Set number of days to check
    $days = 30;

    // Init the API
    $facebook_api = new WPD_Facebook_API(array( 'load_api' => false ));

    // Log the init
    wpd_write_log( 'Executing wpd_schedule_order_utm_campaign_check.', 'cron' );
    wpd_write_log( 'Executing scheduled call to check orders for utm_campaigns.', 'cron' );
    $facebook_api->log('[Cron Schedule] Executing scheduled call to check orders for utm_campaigns');

    // Others
    $start = microtime(true);

    // Check orders for a GCLID and store the campaign ID
    wpd_write_log( '[Cron Schedule] Checking stored utm_campaigns for the last ' . $days . ' days.', 'cron' );
    $facebook_api->log( '[Cron Schedule] Checking stored utm_campaigns for the last ' . $days . ' days.');
    $result = $facebook_api->set_order_campaign_id_via_query_param( $days );

    // Execution time
    $time_elapsed_secs = round( microtime(true) - $start, 2 );

    // Log duration
    if ( $result === false ) {
        wpd_write_log( 'Facebook API scheduled utm_campaign check completed in ' . $time_elapsed_secs . ' seconds. No utm_campaigns have been defined so we didnt process any orders.', 'cron' );
        $facebook_api->log( '[Cron Schedule] Facebook API scheduled utm_campaign check completed in ' . $time_elapsed_secs . ' seconds. No utm_campaigns have been defined so we didnt process any orders.' );
    } else {
        wpd_write_log( '[Cron Schedule] Facebook API scheduled utm_campaign check completed in ' . $time_elapsed_secs . ' seconds. ' . $result . ' orders were updated.', 'cron' );
        $facebook_api->log( '[Cron Schedule] Facebook API scheduled utm_campaign check completed in ' . $time_elapsed_secs . ' seconds. ' . $result . ' orders were updated' );
    }

    wpd_write_log( 'Completed wpd_schedule_order_utm_campaign_check.', 'cron' );

    // No need to return anything really
    return true;

}

/**
 * 
 *  Hourly check for utm_campaigns for orders
 * 
 **/
function wpd_schedule_order_utm_campaign_check_google_cron() {

    if ( ! WPD_AI_PRO ) return false;

    // Set number of days to check
    $days = 30;

    // Init the API
    $google_api = new WPD_Google_Ads_API(array( 'load_api' => false ));

    // Log the init
    wpd_write_log( 'Executing wpd_schedule_order_utm_campaign_check.', 'cron' );
    wpd_write_log( 'Executing scheduled call to check orders for utm_campaigns.', 'cron' );
    $google_api->log('[Cron Schedule] Executing scheduled call to check orders for utm_campaigns');

    // Others
    $start = microtime(true);

    // Check orders for a GCLID and store the campaign ID
    wpd_write_log( '[Cron Schedule] Checking stored utm_campaigns for the last ' . $days . ' days.', 'cron' );
    $google_api->log( '[Cron Schedule] Checking stored utm_campaigns for the last ' . $days . ' days.');
    $result = $google_api->set_order_campaign_id_via_query_param( $days );

    // Execution time
    $time_elapsed_secs = round( microtime(true) - $start, 2 );

    // Log duration
    if ( $result === false ) {
        wpd_write_log( 'Google API scheduled utm_campaign check completed in ' . $time_elapsed_secs . ' seconds. No utm_campaigns have been defined so we didnt process any orders.', 'cron' );
        $google_api->log( '[Cron Schedule] Google API scheduled utm_campaign check completed in ' . $time_elapsed_secs . ' seconds. No utm_campaigns have been defined so we didnt process any orders.' );
    } else {
        wpd_write_log( '[Cron Schedule] Google API scheduled utm_campaign check completed in ' . $time_elapsed_secs . ' seconds. ' . $result . ' orders were updated.', 'cron' );
        $google_api->log( '[Cron Schedule] Google API scheduled utm_campaign check completed in ' . $time_elapsed_secs . ' seconds. ' . $result . ' orders were updated' );
    }

    wpd_write_log( 'Completed wpd_schedule_order_utm_campaign_check.', 'cron' );

    // No need to return anything really
    return true;

}

/**
 * 
 *  Hourly check for GCLIDs from orders
 * 
 **/
function wpd_schedule_google_api_gclid_check_cron() {

    if ( ! WPD_AI_PRO ) return false;

    wpd_write_log( 'Executing wpd_schedule_google_api_gclid_check.', 'cron' );

    // Init the API
    $google_api = new WPD_Google_Ads_API();

    // Log the init
    wpd_write_log( 'Executing scheduled call to google API for order GCLIDs.', 'cron' );
    $google_api->log('[Cron Schedule] Executing scheduled call to google API for order GCLIDs');

    // Dont proceed if it's not configured
    if ( ! $google_api->is_configured() ) {
        wpd_write_log( 'API not configured, not going to run the API call.', 'cron' );
        $google_api->log( '[Cron Schedule] API not configured, not going to run the API call.');
        return false;
    }

    // Others
    $start = microtime(true);

    // Check orders for a GCLID and store the campaign ID
    wpd_write_log( 'Checking stored GCLID\'s for the last 30 days.', 'cron' );
    $google_api->log( '[Cron Schedule] Checking stored GCLID\'s for the last 30 days.');
    $result = $google_api->set_order_campaign_id_via_api_last_x_days( 30 );

    // Execution time
    $time_elapsed_secs = round( microtime(true) - $start, 2 );

    // Log duration
    wpd_write_log( 'Google API scheduled GCLID check completed in ' . $time_elapsed_secs . ' seconds.', 'cron' );
    $google_api->log( '[Cron Schedule] Google API scheduled GCLID check completed in ' . $time_elapsed_secs . ' seconds.' );

    $orders_checked = $result['order_count'];
    $updates = $result['updates'];
    $errors = $result['errors'];
    $gclids_found = $result['gclids_found'];

    wpd_write_log( sprintf( '%s Orders were checked, we found %s GCLIDs, associated %s orders to campaigns and there were %s API errors.', $orders_checked, $gclids_found, $updates, $errors ), 'cron' );
    $google_api->log( sprintf( '%s Orders were checked, we found %s GCLIDs, associated %s orders to campaigns and there were %s API errors.', $orders_checked, $gclids_found, $updates, $errors ) );
    
    wpd_write_log( 'Completed wpd_schedule_google_api_gclid_check.', 'cron' );

    // No need to return anything really
    return true;

}

/**
 *
 *  Create daily Facebook Ad Spend Data
 *
 */
function wpd_schedule_facebook_api_call_function() {

    if ( ! WPD_AI_PRO ) return false;

    wpd_write_log( 'Executing wpd_schedule_facebook_api_call.', 'cron' );
    wpd_write_log( '[Cron Schedule] Executing scheduled call to Facebook API', 'facebook' );

    $facebook_api = new WPD_Facebook_API( array('load_api' => true) );

    if ( ! $facebook_api->is_configured() ) {
        wpd_write_log( 'API not configured, not going to run the API call.', 'cron' );
        wpd_write_log( '[Cron Schedule] API not configured, not going to run the API call.', 'facebook' );
        return false;
    }

    $settings = $facebook_api->facebook_settings;
    $start    = microtime(true);
    $facebook_api->test_api();
    $settings['last_api_test_unix'] = current_time('timestamp');

    // Also try to auto-refresh token using new auth class
    if ( class_exists('WPD_Facebook_Auth') ) {
        $facebook_auth = new WPD_Facebook_Auth();
        $facebook_auth->auto_refresh_token();
    }

    // Stored values to return
    $number_of_campaigns_found      = 0;
    $number_of_campaigns_created    = 0;
    $number_of_campaigns_updated    = 0;
    $number_of_fb_expenses_created  = 0;
    $number_of_fb_expenses_updated  = 0;

    // Daily Ad Spend
    if ( $settings['collect_daily_ad_spend'] == "true" ) {

        wpd_write_log( 'Collecting daily ad spend data.', 'cron' );
        wpd_write_log( '[Cron Schedule] Collecting daily ad spend data.', 'facebook' );
        $daily_ad_spend_response = $facebook_api->create_daily_ad_spend_data();
        $settings['last_data_fetch_unix']   = current_time('timestamp');

        $number_of_fb_expenses_created = $daily_ad_spend_response['created_expenses'];
        $number_of_fb_expenses_updated = $daily_ad_spend_response['updated_expenses'];

    } else {

        wpd_write_log( 'Not collecting daily ad spend as you\'ve set this to false.', 'cron' );
        wpd_write_log( '[Cron Schedule] Not collecting daily ad spend as you\'ve set this to false.', 'facebook' );

    }

    // Campaign Insights
    if ( $settings['collect_campaign_insights'] == "true" ) {

        wpd_write_log( 'Collecting Facebook campaign data.', 'cron' );
        wpd_write_log( '[Cron Schedule] Collecting Facebook campaign data.', 'facebook' );
        $campaign_response = $facebook_api->create_update_facebook_campaigns();

        wpd_write_log( 'Collecting Daily Facebook campaign data.', 'cron' );
        wpd_write_log( '[Cron Schedule] Collecting Daily Facebook campaign data.', 'facebook' );
        $daily_campaign_response = $facebook_api->store_daily_campaign_data();  
        $settings['last_data_fetch_unix']   = current_time('timestamp');

        $number_of_campaigns_found      = $campaign_response['campaigns'];
        $number_of_campaigns_created    = $campaign_response['created_campaigns'];
        $number_of_campaigns_updated    = $campaign_response['updated_campaigns'];

    } else {

        wpd_write_log( 'Not collecting campaign settings as you\'ve set this to false.', 'cron' );
        wpd_write_log( '[Cron Schedule] Not collecting campaign settings as you\'ve set this to false.', 'facebook' );

    }

    // Execution time
    $time_elapsed_secs = round( microtime(true) - $start, 2 );

    // Update last updated time
    $update_facebook_settings = update_option( 'wpd_ai_facebook_integration', $settings );

    wpd_write_log( 'Facebook API call completed in ' . $time_elapsed_secs . ' seconds.', 'cron' );
    wpd_write_log( '[Cron Schedule] Facebook API call completed in ' . $time_elapsed_secs . ' seconds.', 'facebook' );

    return array(
        'time_elapsed'      => $time_elapsed_secs,
        'expenses_created'  => $number_of_fb_expenses_created,
        'expenses_updated'  => $number_of_fb_expenses_updated,
        'campaigns_found'   => $number_of_campaigns_found,
        'campaigns_created' => $number_of_campaigns_created,
        'campaigns_updated' => $number_of_campaigns_updated
    );

}

/**
 * 
 * 	Enriches DB table for page_view events that don't have an object ID or type
 * 
 * 	@return bool|int Returns false on failure, or count of updates on success.
 * 
 **/
function wpd_set_post_id_post_type_on_null_events_analytics_table() {

	// Load vars
	global $wpdb;
	$wpd_db 			= new WPD_Database_Interactor();
	$woo_events_table 	= $wpd_db->events_table;
	$count_of_updates 	= 0;
	$limit 				= 5000; // Roughly 10s
	
	// Prepare query
	$sql_query = 
		"SELECT ID, page_href, object_type, object_id
		FROM {$woo_events_table}
		WHERE 1=1
		AND object_id = 0
		AND event_type = 'page_view'
		AND object_type = ''
		ORDER BY date_created_gmt DESC
		LIMIT $limit";

	// Fetch Results
	$results = $wpdb->get_results( $sql_query, 'ARRAY_A' );

	// DB Error
	if ( $wpdb->last_error  ) {
		wpd_write_log( 'Error updating the post ID\'s for null values in the analytics table.', 'db_error' );
		wpd_write_log( $wpdb->last_error, 'db_error' );
		wpd_write_log( $wpdb->last_query, 'db_error' );
		return false;
	}

	// If we've got results
	if ( is_array($results) && ! empty($results) ) {
		
		foreach( $results as $page_view ) {

			// Fetch the data
			$row_id 	= $page_view['ID'];
			$page_href 	= sanitize_url( $page_view['page_href'] );
			$post_id 	= url_to_postid($page_href);

			// We've found the object
			if ( $post_id ) {

				// Get the post type
				$post_type = get_post_type($post_id);

				// Update table row
				$update = $wpdb->update( 
					$woo_events_table, 
					array( 'object_id' => $post_id, 'object_type' => $post_type ),
					array( 'ID' => $row_id ),
				);

				// Iterate success counter
				if ( $update ) $count_of_updates++;

			}

		}

	}

	// Return count of updates
	return $count_of_updates;

}