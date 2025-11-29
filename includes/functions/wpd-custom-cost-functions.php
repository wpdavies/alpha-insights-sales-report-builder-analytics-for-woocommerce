<?php
/**
 *
 * Custom cost related functions for orders & products
 *
 * @package Alpha Insights
 * @version 4.5.0
 * @since 4.5.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * 
 * 	Checks for custom order costs & formats appropriately
 * 
 * 	@return array
 * 
 **/
function wpd_get_custom_order_cost_options() {

	// Gets saved values
	$return_array 		= array();
	$saved_cost_options = get_option( 'wpd_ai_custom_order_costs', array() );

	// Return nothing if they haven't saved anything
	if ( ! is_array($saved_cost_options) || empty( $saved_cost_options ) ) return array();

	// Loop through options
	foreach( $saved_cost_options as $slug => $data ) {

		// Init the array
		if ( ! isset($return_array) ) $return_array[$slug] = array();

		// Backwards compatability
		if ( is_string($data) ) $return_array[$slug]['label'] = $data;

		// Description, label, placeholder, static_fee, percent_of_order_value
		$return_array[$slug]['label'] 					= (isset($data['label']) && ! empty($data['label'])) ? $data['label'] : $slug;
		$return_array[$slug]['description'] 			= (isset($data['description']) && ! empty($data['description'])) ? $data['description'] : '';
		$return_array[$slug]['placeholder'] 			= (isset($data['placeholder']) && ! empty($data['placeholder'])) ? $data['placeholder'] : '';
		$return_array[$slug]['static_fee'] 				= (isset($data['static_fee']) && is_numeric($data['static_fee'])) ? $data['static_fee'] : null;
		$return_array[$slug]['percent_of_order_value'] 	= (isset($data['percent_of_order_value']) && is_numeric($data['percent_of_order_value'])) ? $data['percent_of_order_value'] : null;

	}

	// Return array
	return $return_array;

}

/**
 * 
 * 	Retrieves all custom product costs by product ID
 * 	Expects associate array in format unique_slug => array(label, description) in key value pairs.
 * 
 * 	@return array
 * 
 **/
function wpd_get_custom_product_cost_options( int $product_id = 0 ) {

	$cached_results = wp_cache_get( $product_id, '_wpd_ai_custom_product_costs' );

	if ( $cached_results !== false ) {

		$cached_results = maybe_unserialize($cached_results);

		if ( is_array($cached_results) && ! empty($cached_results) ) return $cached_results;

	}

    // Gets saved values
	$custom_product_costs = array();
	$saved_cost_options = get_option( 'wpd_ai_custom_product_costs', array() );

    if ( is_array($saved_cost_options) && ! empty( $saved_cost_options ) ) {

        // Loop through options
        foreach( $saved_cost_options as $slug => $data ) {

            // Init the array
            if ( ! isset($return_array) ) $return_array[$slug] = array();

            // Backwards compatability
            if ( is_string($data) ) $return_array[$slug]['label'] = $data;

            // Description, label, placeholder, static_fee, percent_of_order_value
            $custom_product_costs[$slug]['label'] 					= (isset($data['label']) && ! empty($data['label'])) ? $data['label'] : $slug;
            $custom_product_costs[$slug]['description'] 			= (isset($data['description']) && ! empty($data['description'])) ? $data['description'] : '';
            $custom_product_costs[$slug]['placeholder'] 			= (isset($data['placeholder']) && ! empty($data['placeholder'])) ? $data['placeholder'] : 0;
            $custom_product_costs[$slug]['static_fee'] 				= (isset($data['static_fee']) && is_numeric($data['static_fee'])) ? (float) $data['static_fee'] : null;
            $custom_product_costs[$slug]['percent_of_sell_price'] 	= (isset($data['percent_of_sell_price']) && is_numeric($data['percent_of_sell_price'])) ? (float) $data['percent_of_sell_price'] : null;

        }

    }

    /**
	 * 
	 * 	Generate a list of custom product cost options for use by Alpha Insights
	 *  Expects an associative array in format array( unique_slug => array(key => 'Value') ) in key value pairs.
	 * 	The available keys are: label, description, placeholder, static_fee, percent_of_sell_price
	 *
	 * 	@param array Empty array to begin with
	 * 	@param int $product_id The product ID of a particular product
	 * 
	 * 	@return array The array of custom product costs that are registered by this filter
	 * 
	 **/
	$custom_product_costs = apply_filters( 'wpd_ai_custom_product_cost_options', $custom_product_costs, $product_id );

	// Product specific meta
	$product_specific_values = (is_numeric($product_id) && $product_id > 0) ? (array) get_post_meta( $product_id, '_wpd_ai_custom_product_costs', true ) : array();

	// Loop through product specific stuff
	if ( is_array($product_specific_values) && ! empty($product_specific_values) ) {

		foreach( $product_specific_values as $slug => $product_meta ) {

			// Dont put it in if it's not in our settings
			if ( ! isset($custom_product_costs[$slug]) ) continue;

			// Store product specific values if they exist
			if ( isset($product_meta['static_fee']) && is_numeric($product_meta['static_fee']) ) $custom_product_costs[$slug]['static_fee'] = (float) $product_meta['static_fee'];
			if ( isset($product_meta['percent_of_sell_price']) && is_numeric($product_meta['percent_of_sell_price']) ) $custom_product_costs[$slug]['percent_of_sell_price'] = (float) $product_meta['percent_of_sell_price'];

		}

	}

	// If we have custom costs, do a quick check on this product to see if we've got saved values and also sanitize data
	if ( is_array($custom_product_costs) && ! empty($custom_product_costs) ) {

		foreach( $custom_product_costs as $slug => $data ) {

            // Make sure we don't have empty arrays
			if ( ! array_filter($data) ) unset($custom_product_costs[$slug]); continue;

            // Sanitize our data
            $custom_product_costs[$slug]['label'] 					= (isset($data['label']) && ! empty($data['label'])) ? $data['label'] : $slug;
            $custom_product_costs[$slug]['description'] 			= (isset($data['description']) && ! empty($data['description'])) ? $data['description'] : '';
            $custom_product_costs[$slug]['placeholder'] 			= (isset($data['placeholder']) && ! empty($data['placeholder'])) ? $data['placeholder'] : 0;
            $custom_product_costs[$slug]['static_fee'] 				= (isset($data['static_fee']) && is_numeric($data['static_fee'])) ? (float) $data['static_fee'] : null;
            $custom_product_costs[$slug]['percent_of_sell_price'] 	= (isset($data['percent_of_sell_price']) && is_numeric($data['percent_of_sell_price'])) ? (float) $data['percent_of_sell_price'] : null;

		}

        // Store in cache
	    wp_cache_set( $product_id, $custom_product_costs, '_wpd_ai_custom_product_costs' );

	}

	// Return Results
	return $custom_product_costs;
	
}

/**
 * 
 *  Performs default calculation for custom product cost per line item
 * 
 *  @param WC_Order_Item_Product $item The item object
 *  @param array $custom_cost_data This needs to be the data for one particular custom product cost option.
 * 
 *  @return float The calculated value.
 * 
 **/
function wpd_calculate_custom_product_cost_by_line_item( $item, $custom_cost_data ) {

    // Safety Check
    if ( ! is_a($item, 'WC_Order_Item_Product') || ! is_array($custom_cost_data) ) return 0;

    $static_fee                 = (float) $custom_cost_data['static_fee'];
    $percent_of_sell_price      = (float) $custom_cost_data['percent_of_sell_price'];
    $product_revenue            = (float) $item->get_total() + (float) $item->get_total_tax();
    $product_revenue_per_unit   = wpd_divide( $product_revenue, (float) $item->get_quantity() );

    // Make calculation
    $result = ( $product_revenue_per_unit * wpd_divide( $percent_of_sell_price, 100 ) ) + $static_fee;

    // Return result
    return (float) $result;

}

/**
 * 
 * 	Fetches the total additional costs by product ID and returns an associate array with the values
 * 
 * 	@return array Associative array containing all costs, and a total key for the total amount in the store's currency
 * 
 **/
function wpd_get_additional_costs_by_product_id( int $product_id ) {

	$return_values              = array('total' => 0, 'string' => '');
	$custom_product_cost_values = wpd_get_custom_product_cost_options( $product_id );
    $product                    = wc_get_product( $product_id );
    $price                      = ( is_a($product, 'WC_Product') ) ? (float) $product->get_price() : 0;

	if ( is_array($custom_product_cost_values) && ! empty($custom_product_cost_values) ) {

		foreach( $custom_product_cost_values as $slug => $custom_cost_data ) {

            $static_fee                 = (float) $custom_cost_data['static_fee'];
            $percent_of_sell_price      = (float) $custom_cost_data['percent_of_sell_price'];
            $cost_value                 = ( $price * wpd_divide( $percent_of_sell_price, 100 ) ) + $static_fee;

            $return_values[$slug] = (float) $cost_value;
			$return_values['total'] += (float) $cost_value;
			$return_values['string'] .= '<span class="wpd-additional-cost-item" style="display:block;">' . $slug . ': ' . wc_price($cost_value) . '</span>';

		}

	}

	return $return_values;

}