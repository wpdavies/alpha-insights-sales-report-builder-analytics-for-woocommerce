<?php
/**
 *
 * Google API Functions
 * 
 * @package Alpha Insights
 * @version 3.2.1
 * @since 3.2.1
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * 
 * 	Get Google Campaign Name by ID
 * 
 * 	@param int $campaign_id The campaign ID
 * 	@return string The campaign name, or the campaign ID if no name is found
 * 
 **/
function wpd_get_google_campaign_name_by_id( $campaign_id ) {

	$query = new WP_Query(
		array(
			'post_type' => 'google_ad_campaign',
			'meta_key' => '_wpd_campaign_id',
			'meta_value' => $campaign_id,
			'meta_compare' => '='
		)
	);

	$post_ids =  wp_list_pluck( $query->posts, 'ID' );
	
	if ( isset($post_ids[0]) ) {

		$post_id = $post_ids[0];
		$campaign_name = get_post_meta( $post_id, '_wpd_campaign_name', true );

		return $campaign_name;

	}

	return $campaign_id;

}

/**
 * 
 * 	Gets Google Expense Category ID by saved DB value
 * 
 **/
function wpd_get_google_expense_category_id() {

	$category_id 	= 0;
	$options 		= get_option( 'wpd_ai_google_ads_api' );

	if ( is_array($options) && ! empty($options) ) {

		if ( isset($options['expense_category_id']) && is_numeric($options['expense_category_id']) ) {
			$category_id = (int) $options['expense_category_id'];
		}

	}

	return $category_id;

}


/**
 * 
 * 	Retrieves all stored Google Campaigns
 * 
 * 	@return array Associative array with campaign_id & campaign_name
 * 
 **/
function wpd_get_all_google_campaigns() {

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

	// Return results
	return $all_campaigns;

}

/**
 * 
 * 	Returns all google campaign post ID's
 * 
 **/
function wpd_get_all_google_campaign_post_ids() {

	// Query Args
	$query_args = array(

		'fields' 			=> 'ids',
		'post_type' 		=> 'google_ad_campaign',
		'post_status' 		=> 'publish',
		'posts_per_page' 	=> -1,
	);

	// Execute Query
	$query 		= new WP_Query( $query_args );
	$post_ids 	= $query->posts;

	return $post_ids;

}

/**
 * 
 * 	Returns an associative array in utm_campaign => campaign_id format
 * 
 * 	@return array
 * 
 **/
function wpd_get_all_utm_campaign_google_campaigns() {

	$results = array();

	// Query Args
	$query_args = array(

		'fields' 			=> 'ids',
		'post_type' 		=> 'google_ad_campaign',
		'post_status' 		=> 'publish',
		'posts_per_page' 	=> -1,
		'meta_query' 		=> array(
			array(
				'key' => '_wpd_utm_campaign_value',
				'compare' => 'NOT IN',
				'value' => array('')
			)
		)
	);

	// Execute Query
	$query 		= new WP_Query( $query_args );
	$post_ids 	= $query->posts;

	// Build finished array
	foreach( $post_ids as $post_id ) {

		$campaign_id = get_post_meta( $post_id, '_wpd_campaign_id', true );
		$utm_campaign = get_post_meta( $post_id, '_wpd_utm_campaign_value', true );
		$results[$utm_campaign] = $campaign_id;

	}

	return $results;

}

/**
 * 
 * 	Checks for last succesful data fetch from Google API
 * 
 **/
function wpd_get_last_google_api_data_fetch() {

	// Get store settings
	$store_settings = get_option( 'wpd_ai_google_ads_api', false );

	// Results are no good
	if ( ! isset($store_settings['last_data_fetch_unix']) || empty($store_settings['last_data_fetch_unix']) ) {
		return false;
	}

	// Return Human Readable Result
	return wpd_calculate_time_difference( $store_settings['last_data_fetch_unix'] ) . ' ago';

}