<?php
/**
 *
 * Currency Related Functions
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
 *	Currency Selector
 *
 */
function wpdai_get_woocommerce_currency_list() {

	$currencies = get_woocommerce_currencies();

	return $currencies;

}

/**
 *
 *	Currency list in selection option
 *
 */
function wpdai_woocommerce_currency_list_select( $selected = null ) {

	$currencies = wpdai_get_woocommerce_currency_list();
	$html = '';

	if ( ! is_string($selected) || empty($selected) ) {

		$woocommerce_currency = wpdai_get_store_currency();

		if ( isset($woocommerce_currency) && ! empty($woocommerce_currency) ) {

			$selected = $woocommerce_currency;

		}

	}

	foreach( $currencies as $key => $pair ) {

		if ( $selected == $key ) {

			$select = 'selected="selected"';

		} else {

			$select = null;

		}

		$html .= '<option value="' . $key . '" ' . $select . '>' . $key . '</option>';

	}

	return $html;

}

/**
 *
 *	Get base currency - Currency to display everything in
 *
 */
function wpdai_get_store_currency() {

	return get_option('woocommerce_currency');

}

/**
 * 
 * 	Returns string formatting for displaying of store's currency symbol &or code
 * 
 * 	@return string Formatted Currency string including symbol & code
 * 
 **/
function wpdai_store_currency_string() {

	return get_woocommerce_currency_symbol() . '' . wpdai_get_store_currency();

}

/**
 * 
 * 	Get Currency Conversion Rate
 * 
 * 	@param string $from Currency to convert from
 * 	@param string $from Currency to convert to
 *  @return float Rate on success
 * 	@return bool false on failure
 * 
 **/
function wpdai_get_currency_conversion_rate( $from, $to ) {

	$currency_conversion_table = wpdai_get_list_of_currency_conversion_rates();

	// If we cant find exchange rate just return the original value
	if ( ! array_key_exists($to,$currency_conversion_table) || ! array_key_exists($from,$currency_conversion_table) ) {
		return false;
	}

	$rate 	= wpdai_divide( $currency_conversion_table[$to], $currency_conversion_table[$from] );

	return $rate;

}

/**
 *
 *	Currency Conversion
 *
 * 	@param string $from Currency to convert from
 *  @param string $to Currency to convert to
 *  @param float $amount Amount to convert
 *  @param float $exchange_rate You can pass an exchange rate in, defaults to false means it wont be used
 *
 */
function wpdai_convert_currency( $from, $to, $amount, $exchange_rate = false ) {

	// Eg I have 35AUD, should become 24.87USD
	// AUD  = 1.41

	// Not a number - try and return it as a number
	if ( ! is_numeric($amount) ) {
		$amount = floatval( $amount );
	}

	// Return 0 if it's not a number basically
	if ( ! $amount ) {
		return 0;
	}

	$total = 0;	
	
	if ( is_numeric($exchange_rate) && $exchange_rate > 0 ) {

		$rate = $exchange_rate;

	} else {

		$currency_conversion_table = wpdai_get_list_of_currency_conversion_rates();
		// If we cant find exchange rate just return the original value
		if ( ! array_key_exists($to,$currency_conversion_table) || ! array_key_exists($from,$currency_conversion_table) ) {
			return $amount;
		}
		$rate 	= wpdai_divide( $currency_conversion_table[$to], $currency_conversion_table[$from] );

	}

	$total 	= $amount * $rate;

	// Return results.
	return $total;

}

/**
 *
 *	Get currency converions list
 *
 */
function wpdai_get_list_of_currency_conversion_rates() {

	// Deprecated since 3.2.1 -> will just use the OEX API that we feed on each release
	// $options = get_option( 'wpd_ai_currency_table' );

	$currency_conversion_table = wpdai_get_default_currency_conversion_rates();

	return $currency_conversion_table;

}

/** 
 *
 *	List of default conversion rates
 *
 */
function wpdai_get_default_currency_conversion_rates() {

    // Check the cache
	$result = wp_cache_get( '_currency_conversion_array', '_wpd_ai_data' );

	// Return the results in the cache
	if ( $result !== false ) {
		
		// Format into array
		$result = maybe_unserialize($result);

		// Of not correct, something has gone wrong.
		if ( ! is_array($result) ) return false;

		// Return format
		return $result;

	}

	// Check the currency conversion is in the right format
	$file = WPD_AI_PATH . 'assets/other/default-currency-exchange-rates.csv';

	if ( ! file_exists($file) ) {
		wpdai_write_log( 'Could not find the currency exchange rate file: ' . $file, 'errors' );
	}

	$exchange_rates = wpdai_csv_to_array( $file );

	if ( ! is_array($exchange_rates) || empty($exchange_rates) ) {
		return array();
	}

	$return_array = array();

	foreach( $exchange_rates as $exchange_rate_array ) {

		$currency_code = $exchange_rate_array['currency_code'];
		$exchange_rate = $exchange_rate_array['exchange_rate_against_USD'];

		$return_array[$currency_code] = $exchange_rate;

	}

    wp_cache_set( '_currency_conversion_array', $return_array, '_wpd_ai_data' );

	return $return_array;

}

/**
 *
 *
 *	Returns the symbol for a currency
 *
 */
function wpdai_get_woocommerce_currency_symbol( $currency = '' ) { 

    if ( ! $currency ) { 
        $currency = wpdai_get_store_currency(); 
    } 

    $symbols = apply_filters( 'woocommerce_currency_symbols', array( 
        'AED' => 'د.إ',  
        'AFN' => '؋',  
        'ALL' => 'L',  
        'AMD' => 'AMD',  
        'ANG' => 'ƒ',  
        'AOA' => 'Kz',  
        'ARS' => '$',  
        'AUD' => '$',  
        'AWG' => 'ƒ',  
        'AZN' => 'AZN',  
        'BAM' => 'KM',  
        'BBD' => '$',  
        'BDT' => '৳ ',  
        'BGN' => 'лв.',  
        'BHD' => '.د.ب',  
        'BIF' => 'Fr',  
        'BMD' => '$',  
        'BND' => '$',  
        'BOB' => 'Bs.',  
        'BRL' => 'R$',  
        'BSD' => '$',  
        'BTC' => '฿',  
        'BTN' => 'Nu.',  
        'BWP' => 'P',  
        'BYR' => 'Br',  
        'BZD' => '$',  
        'CAD' => '$',  
        'CDF' => 'Fr',  
        'CHF' => 'CHF',  
        'CLP' => '$',  
        'CNY' => '¥',  
        'COP' => '$',  
        'CRC' => '₡',  
        'CUC' => '$',  
        'CUP' => '$',  
        'CVE' => '$',  
        'CZK' => 'Kč',  
        'DJF' => 'Fr',  
        'DKK' => 'DKK',  
        'DOP' => 'RD$',  
        'DZD' => 'د.ج',  
        'EGP' => 'EGP',  
        'ERN' => 'Nfk',  
        'ETB' => 'Br',  
        'EUR' => '€',  
        'FJD' => '$',  
        'FKP' => '£',  
        'GBP' => '£',  
        'GEL' => 'ლ',  
        'GGP' => '£',  
        'GHS' => '₵',  
        'GIP' => '£',  
        'GMD' => 'D',  
        'GNF' => 'Fr',  
        'GTQ' => 'Q',  
        'GYD' => '$',  
        'HKD' => '$',  
        'HNL' => 'L',  
        'HRK' => 'Kn',  
        'HTG' => 'G',  
        'HUF' => 'Ft',  
        'IDR' => 'Rp',  
        'ILS' => '₪',  
        'IMP' => '£',  
        'INR' => '₹',  
        'IQD' => 'ع.د',  
        'IRR' => '﷼',  
        'IRT' => 'تومان',  
        'ISK' => 'kr.',  
        'JEP' => '£',  
        'JMD' => '$',  
        'JOD' => 'د.ا',  
        'JPY' => '¥',  
        'KES' => 'KSh',  
        'KGS' => 'сом',  
        'KHR' => '៛',  
        'KMF' => 'Fr',  
        'KPW' => '₩',  
        'KRW' => '₩',  
        'KWD' => 'د.ك',  
        'KYD' => '$',  
        'KZT' => 'KZT',  
        'LAK' => '₭',  
        'LBP' => 'ل.ل',  
        'LKR' => 'රු',  
        'LRD' => '$',  
        'LSL' => 'L',  
        'LYD' => 'ل.د',  
        'MAD' => 'د.م.',  
        'MDL' => 'MDL',  
        'MGA' => 'Ar',  
        'MKD' => 'ден',  
        'MMK' => 'Ks',  
        'MNT' => '₮',  
        'MOP' => 'P',  
        'MRO' => 'UM',  
        'MUR' => '₨',  
        'MVR' => '.ރ',  
        'MWK' => 'MK',  
        'MXN' => '$',  
        'MYR' => 'RM',  
        'MZN' => 'MT',  
        'NAD' => '$',  
        'NGN' => '₦',  
        'NIO' => 'C$',  
        'NOK' => 'kr',  
        'NPR' => '₨',  
        'NZD' => '$',  
        'OMR' => 'ر.ع.',  
        'PAB' => 'B/.',  
        'PEN' => 'S/.',  
        'PGK' => 'K',  
        'PHP' => '₱',  
        'PKR' => '₨',  
        'PLN' => 'zł',  
        'PRB' => 'р.',  
        'PYG' => '₲',  
        'QAR' => 'ر.ق',  
        'RMB' => '¥',  
        'RON' => 'lei',  
        'RSD' => 'дин.',  
        'RUB' => '₽',  
        'RWF' => 'Fr',  
        'SAR' => 'ر.س',  
        'SBD' => '$',  
        'SCR' => '₨',  
        'SDG' => 'ج.س.',  
        'SEK' => 'kr',  
        'SGD' => '$',  
        'SHP' => '£',  
        'SLL' => 'Le',  
        'SOS' => 'Sh',  
        'SRD' => '$',  
        'SSP' => '£',  
        'STD' => 'Db',  
        'SYP' => 'ل.س',  
        'SZL' => 'L',  
        'THB' => '฿',  
        'TJS' => 'ЅМ',  
        'TMT' => 'm',  
        'TND' => 'د.ت',  
        'TOP' => 'T$',  
        'TRY' => '₺',  
        'TTD' => '$',  
        'TWD' => 'NT$',  
        'TZS' => 'Sh',  
        'UAH' => '₴',  
        'UGX' => 'UGX',  
        'USD' => '$',  
        'UYU' => '$',  
        'UZS' => 'UZS',  
        'VEF' => 'Bs F',  
        'VND' => '₫',  
        'VUV' => 'Vt',  
        'WST' => 'T',  
        'XAF' => 'Fr',  
        'XCD' => '$',  
        'XOF' => 'Fr',  
        'XPF' => 'Fr',  
        'YER' => '﷼',  
        'ZAR' => 'R',  
        'ZMW' => 'ZK',  
 ) ); 

    $currency_symbol = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : ''; 

    return apply_filters( 'wpd_woocommerce_currency_symbol', $currency_symbol, $currency ); 
} 

/**
 * 
 * 	Gets the currency conversion rate to be used on this order
 *  @param WC_Order $order
 * 	@return array $array['exchange_rate', 'rate_key_used']
 * 
 **/
function wpdai_get_order_currency_conversion_rate( $order ) {

	$result = array(

		'exchange_rate' => 1,
		'rate_key_used' => null

	);

	$exchange_rate = false;
	$order_currency = $order->get_currency();
	$store_currency = wpdai_get_store_currency();

	// Lets see if they have used a particular currency exchange rate
    // @todo save the exchange rate at time of transaction as meta
	$currency_exchange_rate_keys = array( '_wcpbc_base_exchange_rate', '_wpd_ai_exchange_rate' );

	foreach( $currency_exchange_rate_keys as $rate_key ) {

		$exchange_rate_meta = $order->get_meta( $rate_key );

		if ( ! empty($exchange_rate_meta) && is_numeric($exchange_rate_meta) && $exchange_rate_meta > 0 ) {

			$exchange_rate = $exchange_rate_meta;
			$result['exchange_rate'] = $exchange_rate;
			$result['rate_key_used'] = $rate_key;
			break;

		}

	}

	// If we didn't find one
	if ( ! $exchange_rate ) {
		$exchange_rate = wpdai_get_currency_conversion_rate( $order_currency, $store_currency );
		$result['exchange_rate'] = $exchange_rate;
		$result['rate_key_used'] = 'wpd_currency_conversion_list';
	}

	return $result;

}