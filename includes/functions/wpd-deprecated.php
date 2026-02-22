<?php
/**
 *
 * Deprecated & Compatability functions for compatibility with the old Alpha Insights and phasing out old functions
 *
 * @package Alpha Insights
 * @since 1.0.0
 * @version 5.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * 
 * 	Debugging function
 * 
 * 	@param mixed $data The data to debug
 * 	@param string $title The title of the debug
 * 	@param bool $var_dump Whether to use var_dump or print_r
 * 	@param string $file The file to debug
 * 
 * 	@return void
 * 
 */
if ( ! function_exists( 'wpd_debug' ) ) {

    function wpd_debug( $data, $title = false, $var_dump = false, $file = false ) {

        // Deprecated notice
        _deprecated_function( 'wpd_debug', '5.0.0', 'wpdai_debug' );

        return wpdai_debug( $data, $title, $var_dump, $file );
    
    }

}

/**
 * 
 * 	Debugging in Query Montior function
 * 
 * 	@param mixed $debug The debug to write to the query monitor
 * 
 * 	@return void
 */
if ( ! function_exists( 'wpd_qm_debug' ) ) {

    function wpd_qm_debug( $debug ) {

        // Deprecated notice
        _deprecated_function( 'wpd_qm_debug', '5.0.0', 'wpdai_qm_debug' );

        return wpdai_qm_debug( $debug );

    }

}

/**
 * 
 * 	Write log function
 * 
 * 	@param mixed $data The data to write to the log
 * 	@param string $log The log to write to
 * 
 * 	@return void
 */
if ( ! function_exists( 'wpd_write_log' ) ) {

    function wpd_write_log( $data, $log = 'default' ) {

        // Deprecated notice
        _deprecated_function( 'wpd_write_log', '5.0.0', 'wpdai_write_log' );

        return wpdai_write_log( $data, $log );

    }

}

/**
 * 
 * 	Divide function
 * 
 * 	@param int $n1 The first number
 * 	@param int $n2 The second number
 * 	@param int $round The number of decimal places to round to
 * 
 * 	@return int The result of the division
 */
if ( ! function_exists( 'wpd_divide' ) ) {

    function wpd_divide( $n1, $n2, $round = false ) {

        // Deprecated notice
        _deprecated_function( 'wpd_divide', '5.0.0', 'wpdai_divide' );

        return wpdai_divide( $n1, $n2, $round );

    }

}

/**
 * 
 * 	Calculate percentage function
 * 
 * 	@param int $original The original number
 * 	@param int $total The total number
 * 	@param int $round The number of decimal places to round to
 * 
 * 	@return int The result of the percentage calculation
 */
if ( ! function_exists( 'wpd_calculate_percentage' ) ) {

    function wpd_calculate_percentage( $original, $total, $round = 2 ) {
    
        // Deprecated notice
        _deprecated_function( 'wpd_calculate_percentage', '5.0.0', 'wpdai_calculate_percentage' );

        return wpdai_calculate_percentage( $original, $total, $round );

    }

}

/**
 * 
 * 	Calculate cost profit by order function
 * 
 * 	@param int|WC_order $order_id_or_object The order ID or order object
 * 	@param bool $update_values Whether to update the values
 * 
 * 	@return array The result of the cost profit calculation
 */ 
if ( ! function_exists( 'wpd_calculate_cost_profit_by_order' ) ) {

    function wpd_calculate_cost_profit_by_order( $order_id_or_object = null, $update_values = false ) {
    
        // Deprecated notice
        _deprecated_function( 'wpd_calculate_cost_profit_by_order', '5.0.0', 'wpdai_calculate_cost_profit_by_order' );

        return wpdai_calculate_cost_profit_by_order( $order_id_or_object, $update_values );

    }

}

/**
 * Sends event data to the event tracking system (direct DB insert).
 *
 * @deprecated Use wpdai_track_custom_event( $event_type, $args ) for new code.
 * @param array $data Event payload. Required key: event_type. Other keys defaulted from session/context.
 * @return array Result from insert_event: 'success', 'message', 'code', 'rows_inserted'.
 */
if ( ! function_exists( 'wpdai_send_woocommerce_event' ) ) {

    function wpdai_send_woocommerce_event( $data ) {

        _deprecated_function( 'wpdai_send_woocommerce_event', '5.0.0', 'wpdai_track_custom_event() or WPDAI_WooCommerce_Event_Tracking::get_instance()->insert_event()' );

        return WPDAI_WooCommerce_Event_Tracking::get_instance()->insert_event( $data );

    }

}