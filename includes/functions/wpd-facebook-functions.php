<?php
/**
 *
 * Facebook Functions
 * 
 * @deprecated -> Move these functions into the WPD_Facebook_API class
 * @todo -> Move these functions into the WPD_Facebook_API class
 *
 * @package Alpha Insights
 * @version 2.2.0
 * @since 2.2.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 *
 *	Get Facebook Token Status
 * 	@return string Not Set, Not Activated, Expired, All Good
 *
 */
function wpd_get_fb_token_status( $facebook_settings ) {

	if ( isset($facebook_settings['access_token']) && ! empty($facebook_settings['access_token']) ) {

		// Token Hasn't Been Activated
		if ( !isset($facebook_settings['access_token_validated']) || empty($facebook_settings['access_token_validated']) ) {
			return 'Not Activated <span style="width: 10px; height: 10px; border-radius: 100px; background-color: #eaeaea; display:inline-block;"></span>';
		}

		if ( isset($facebook_settings['access_token_expiry_date_unix']) && ! empty($facebook_settings['access_token_expiry_date_unix']) ) {
			if ( $facebook_settings['access_token_expiry_date_unix'] < current_time('timestamp') ) {
				return 'Expired <span style="width: 10px; height: 10px; border-radius: 100px; background-color: red; display:inline-block;"></span>';
			}
		}

		return 'Active <span style="width: 10px; height: 10px; border-radius: 100px; background-color: #6ae56a; display:inline-block;"></span>';

	} else {

		return 'Not Set <span style="width: 10px; height: 10px; border-radius: 100px; background-color: #eaeaea; display:inline-block;"></span>';

	}

}

/**
 *
 *	Gets the current Facebook API status
 *
 */
function wpd_ai_get_fb_api_status( $facebook_settings ) {

	$api_status = ($facebook_settings['api_status'] ) ? $facebook_settings['api_status'] : 'N/A';

	if ( $api_status == "Healthy" ) {

		$api_status_circle = wpd_status_circle("success");

	} else {

		$api_status_circle = wpd_status_circle();

	}

	return $api_status . $api_status_circle;

}

/**
 * 
 *  Calculates when the next Facebook API call will be made in the schedule
 * 
 **/
function wpd_next_fb_api_call() {

	// Get the next scheduled action timestamp (in GMT/UTC)
	$timestamp = as_next_scheduled_action('wpd_schedule_facebook_api_call');

	if ( $timestamp ) {
		// Convert to store’s local time
		return strtotime( get_date_from_gmt( date( 'Y-m-d H:i:s', $timestamp ) ) );
	}

	return false;
}

/**
 * 
 *  Makes an estimation of how profitable your Facebook campaigns are
 *  This is based on your average store margin vs the reported income by Facebook
 * 
 **/
function wpd_fb_estimated_profit( $ad_revenue, $ad_spend, $margin ) {

	$ad_profit = $ad_revenue - $ad_spend;

	if ( $ad_revenue == 0 ) {

		// No revenue, no adjustments
		$fb_estimated_profit = $ad_profit;

	} else {

		// There was revenue, calculate cost of goods & remove from the profit
		$fb_estimated_profit = $ad_profit - ( $ad_revenue * (1 - ($margin / 100)) ); // Ad Profit - Cogs (cogs = ad revenue * margin)

	}

	return $fb_estimated_profit;

}

/**
 *
 *	Last time Facebook data was updated
 *	@return string time in microseconds
 *
 */
function wpd_facebook_last_updated( $facebook_settings = null ) {

	// Load settings
	if ($facebook_settings === null) {

		$facebook_settings = get_option( 'wpd_ai_facebook_integration' );

	}

	if ( isset($facebook_settings['last_data_fetch_unix']) && ! empty($facebook_settings['last_data_fetch_unix']) ) {

		$microseconds_ago = (int) current_time('timestamp') - (int) $facebook_settings['last_data_fetch_unix'];

		if ( $microseconds_ago === 0 ) {

			return '0 minutes ago';

		} elseif ( $microseconds_ago > 0 && $microseconds_ago < 3600 ) {

			$minutes_ago = round( $microseconds_ago / 60, 0 );
			return $minutes_ago . ' minutes ago';

		} else {

			$hours_ago = round( $microseconds_ago / 60 / 60, 0 );
			return $hours_ago . ' hours ago';

		}

	} else {

		return false;

	}

}

/**
 * 
 * 	Retrieves all stored Meta Campaigns
 * 
 * 	@return array Associative array with campaign_id & campaign_name
 * 
 **/
function wpd_get_all_meta_campaigns() {

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

	// Return results
	return $all_campaigns;

}


/**
 * 
 * 	Get Facebook Campaign Name by ID
 * 
 * 	@param int $campaign_id The campaign ID
 * 	@return string The campaign name, or the campaign ID if no name is found
 * 
 **/
function wpd_get_facebook_campaign_name_by_id( $campaign_id ) {

	$query = new WP_Query(
		array(
			'post_type' => 'facebook_campaign',
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
 * 	Gets the Facebook Expense Category ID by saved DB value
 * 
 **/
function wpd_get_facebook_expense_category_id() {

	$category_id 	= 0;
	$options 		= get_option( 'wpd_ai_facebook_integration' );

	if ( is_array($options) && ! empty($options) ) {
		if ( isset($options['facebook_expense_category_id']) && is_numeric($options['facebook_expense_category_id']) ) {
			$category_id = (int) $options['facebook_expense_category_id'];
		} 
	}

	return $category_id;
	
}