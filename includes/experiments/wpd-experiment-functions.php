<?php
/**
 * Shared helpers for Alpha Insights A/B experiments.
 *
 * @package Alpha Insights
 * @since 5.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cookie names for experiment identity and assignment.
 */
if ( ! defined( 'WPD_AI_VID_COOKIE' ) ) {
	define( 'WPD_AI_VID_COOKIE', 'wpd_ai_vid' );
}
if ( ! defined( 'WPD_AI_EXPS_COOKIE' ) ) {
	define( 'WPD_AI_EXPS_COOKIE', 'wpd_ai_exps' );
}

/**
 * Whether the current build is Pro.
 *
 * @return bool
 */
function wpdai_experiments_is_pro() {
	return defined( 'WPD_AI_PRO' ) && WPD_AI_PRO;
}

/**
 * Whether the current visitor is in a given experiment variant.
 *
 * @param string $experiment_slug Experiment slug.
 * @param string $variant_key     Variant key (e.g. control, treatment).
 * @return bool
 */
function wpdai_in_experiment( $experiment_slug, $variant_key ) {
	$current = wpdai_get_experiment_variant( $experiment_slug );
	if ( null === $current ) {
		return false;
	}
	return (string) $current === (string) $variant_key;
}

/**
 * Get the assigned variant key for an experiment, or null if unassigned/holdout.
 *
 * @param string $experiment_slug Experiment slug.
 * @return string|null
 */
function wpdai_get_experiment_variant( $experiment_slug ) {
	if ( ! class_exists( 'WPDAI_Experiment_Runtime' ) ) {
		return null;
	}
	return WPDAI_Experiment_Runtime::get_assigned_variant_key( $experiment_slug );
}

/**
 * Default experiment settings.
 *
 * @return array
 */
function wpdai_get_experiment_default_settings() {
	return array(
		'mde_percent' => 5,
	);
}

/**
 * Default experiment targeting.
 *
 * @return array
 */
function wpdai_get_experiment_default_targeting() {
	return array(
		'pages'    => array(),
		'audience' => array(
			'traffic_sources' => array(),
			'devices'         => array(),
			'roles'           => array(),
			'logged_in'       => 'any',
			'utm'             => array(),
			'query_params'    => array(),
			'visitor_type'    => 'any',
		),
	);
}

/**
 * Default experiment goals.
 *
 * @return array
 */
function wpdai_get_experiment_default_goals() {
	return array(
		'primary'   => array(
			'type'       => 'transaction',
			'event_type' => '',
			'path'       => '',
		),
		'secondary' => array(),
	);
}

/**
 * Conversion objectives available without a Pro license.
 *
 * @return array
 */
function wpdai_get_experiment_free_conversion_objectives() {
	return array( 'transaction' );
}

/**
 * Whether a conversion objective or saved primary goal is Pro-only.
 *
 * @param string $goal_type Goal or objective key.
 * @return bool
 */
function wpdai_experiment_goal_is_pro( $goal_type ) {
	return ! in_array( sanitize_key( (string) $goal_type ), wpdai_get_experiment_free_conversion_objectives(), true );
}

/**
 * Conversion objectives available on the experiments list table.
 *
 * @return array
 */
function wpdai_get_experiment_conversion_objectives() {
	return array(
		'transaction'          => array(
			'label'      => wpdai_get_experiment_goal_label( 'transaction' ),
			'rate_label' => wpdai_get_experiment_goal_label( 'transaction' ),
			'kind'       => 'count',
			'pro'        => false,
		),
		'add_to_cart'          => array(
			'label'      => wpdai_get_experiment_goal_label( 'add_to_cart' ),
			'rate_label' => wpdai_get_experiment_goal_label( 'add_to_cart' ),
			'kind'       => 'count',
			'pro'        => true,
		),
		'initiate_checkout'    => array(
			'label'      => wpdai_get_experiment_goal_label( 'initiate_checkout' ),
			'rate_label' => wpdai_get_experiment_goal_label( 'initiate_checkout' ),
			'kind'       => 'count',
			'pro'        => true,
		),
		'revenue_per_exposure' => array(
			'label'      => wpdai_get_experiment_goal_label( 'revenue_per_exposure' ),
			'rate_label' => __( 'Revenue / Exposure', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'kind'       => 'currency',
			'pro'        => true,
		),
		'aov'                  => array(
			'label'      => wpdai_get_experiment_goal_label( 'aov' ),
			'rate_label' => wpdai_get_experiment_goal_label( 'aov' ),
			'kind'       => 'currency',
			'pro'        => true,
		),
	);
}

/**
 * Human label for a stored primary goal type.
 *
 * @param string $goal_type Goal type key.
 * @return string
 */
function wpdai_get_experiment_goal_label( $goal_type ) {
	$goal_type = sanitize_key( (string) $goal_type );
	$labels    = array(
		'transaction'          => __( 'Purchase rate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'add_to_cart'          => __( 'Add to cart rate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'initiate_checkout'    => __( 'Checkout init rate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'purchase_value'       => __( 'Revenue', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'revenue_per_exposure' => __( 'Revenue', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'aov'                  => __( 'AOV', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'page_view'            => __( 'Page view', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'event'                => __( 'Custom event', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
	);
	if ( isset( $labels[ $goal_type ] ) ) {
		return $labels[ $goal_type ];
	}
	return $labels['transaction'];
}

/**
 * Description of what a stored primary goal measures.
 *
 * @param string $goal_type Goal type key.
 * @return string
 */
function wpdai_get_experiment_goal_description( $goal_type ) {
	$goal_type    = sanitize_key( (string) $goal_type );
	$descriptions = array(
		'transaction'          => __( 'The share of visitors who saw this variant and then placed an order.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'add_to_cart'          => __( 'The share of visitors who saw this variant and then added a product to the cart.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'initiate_checkout'    => __( 'The share of visitors who saw this variant and then started checkout (viewed the checkout page).', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'purchase_value'       => __( 'Revenue from visitors who saw this variant, divided by how many people saw it. Fair when traffic is split unevenly.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'revenue_per_exposure' => __( 'Revenue from visitors who saw this variant, divided by how many people saw it. Fair when traffic is split unevenly.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'aov'                  => __( 'Average order value among visitors who saw this variant and purchased. This does not count visitors who did not buy.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'page_view'            => __( 'Counts a conversion when a visitor who saw this variant views the page path you set below.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'event'                => __( 'Counts a conversion when tracking records the custom event you set below, for a visitor who saw this variant.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
	);
	if ( isset( $descriptions[ $goal_type ] ) ) {
		return $descriptions[ $goal_type ];
	}
	return $descriptions['transaction'];
}

/**
 * Map a stored primary goal to a list-table conversion objective.
 *
 * @param string $goal_type Stored primary goal type.
 * @return string
 */
function wpdai_map_experiment_goal_to_objective( $goal_type ) {
	$goal_type  = sanitize_key( (string) $goal_type );
	$objectives = wpdai_get_experiment_conversion_objectives();
	if ( 'purchase_value' === $goal_type ) {
		return 'revenue_per_exposure';
	}
	if ( isset( $objectives[ $goal_type ] ) ) {
		return $goal_type;
	}
	return 'transaction';
}

/**
 * Allowed experiment statuses.
 *
 * @return array
 */
function wpdai_get_experiment_statuses() {
	return array( 'draft', 'running', 'paused', 'completed' );
}

/**
 * Allowed page targeting types. Pro-only types are still listed for storage;
 * the admin UI and eligibility layer gate them.
 *
 * @return array
 */
function wpdai_get_experiment_page_target_types() {
	return array(
		'path_equals'      => __( 'Path equals', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'path_contains'    => __( 'Path contains', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'path_starts_with' => __( 'Path starts with', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'wc_cart'          => __( 'WooCommerce cart', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'wc_checkout'      => __( 'WooCommerce checkout', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'wc_product'       => __( 'WooCommerce product', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'wc_shop'          => __( 'WooCommerce shop', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'post_type'        => __( 'Post type', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'regex'            => __( 'URL regex', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
	);
}

/**
 * Page target types available in the free version.
 *
 * @return array
 */
function wpdai_get_experiment_free_page_target_types() {
	return array( 'path_equals', 'path_contains', 'wc_cart', 'wc_checkout', 'wc_product' );
}

/**
 * Allowed traffic source labels for audience targeting.
 *
 * @return array
 */
function wpdai_get_experiment_traffic_sources() {
	return array(
		'Organic',
		'Google Ads',
		'Microsoft Ads',
		'Email',
		'Social',
		'Direct',
		'App',
		'Referral',
		'AI Chat',
		'QR Code',
		'Unknown',
	);
}

/**
 * Allowed device labels for audience targeting.
 *
 * @return array
 */
function wpdai_get_experiment_devices() {
	return array( 'Desktop', 'Mobile', 'Tablet' );
}

/**
 * Values to mark selected in the editor. Empty stored list means “all”.
 *
 * @param array $saved Saved values.
 * @param array $all   Full option list.
 * @return array
 */
function wpdai_experiment_selected_filter_values( $saved, $all ) {
	$saved = is_array( $saved ) ? array_values( $saved ) : array();
	$all   = is_array( $all ) ? array_values( $all ) : array();
	if ( empty( $saved ) ) {
		return $all;
	}
	return array_values( array_intersect( $saved, $all ) );
}

/**
 * Persist a subset of filter values. All or none selected means “any”.
 *
 * @param array $posted Posted values.
 * @param array $all    Full option list.
 * @return array
 */
function wpdai_experiment_normalize_filter_values( $posted, $all ) {
	$all    = is_array( $all ) ? array_values( $all ) : array();
	$posted = is_array( $posted ) ? array_values( array_intersect( array_map( 'sanitize_text_field', $posted ), $all ) ) : array();
	sort( $posted );
	$sorted_all = $all;
	sort( $sorted_all );
	if ( empty( $posted ) || $posted === $sorted_all ) {
		return array();
	}
	return $posted;
}

/**
 * Cookie options shared with analytics cookies.
 *
 * @param int $expiry Unix timestamp.
 * @return array
 */
function wpdai_get_experiment_cookie_options( $expiry ) {
	$domain = class_exists( 'WPDAI_Session_Tracking' ) ? WPDAI_Session_Tracking::get_cookie_domain() : '';

	return array(
		'expires'  => (int) $expiry,
		'path'     => '/',
		'domain'   => $domain,
		'secure'   => is_ssl(),
		'httponly' => false,
		'samesite' => 'Lax',
	);
}

/**
 * Set an experiment-related cookie.
 *
 * @param string $name   Cookie name.
 * @param string $value  Cookie value.
 * @param int    $expiry Unix timestamp.
 * @return void
 */
function wpdai_set_experiment_cookie( $name, $value, $expiry ) {
	$options = wpdai_get_experiment_cookie_options( $expiry );

	if ( ! headers_sent() ) {
		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie( $name, $value, $options );
		} else {
			setcookie( $name, $value, $expiry, $options['path'], $options['domain'], $options['secure'], $options['httponly'] );
		}
	}

	$_COOKIE[ $name ] = $value;
}

/**
 * Current request path (no query string), leading slash, trailing slash stripped except root.
 *
 * @return string
 */
function wpdai_get_current_request_path() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
	$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
	if ( '' === $path ) {
		$path = '/';
	}
	if ( '/' !== $path ) {
		$path = untrailingslashit( $path );
	}
	return $path;
}

/**
 * Whether a page-targeting rule has no usable match value.
 *
 * Empty path/regex/post-type values are ignored. WooCommerce page types
 * match without a value.
 *
 * @param array $rule Targeting rule.
 * @return bool
 */
function wpdai_experiment_page_rule_is_empty( $rule ) {
	if ( ! is_array( $rule ) ) {
		return true;
	}

	$type  = isset( $rule['type'] ) ? sanitize_key( $rule['type'] ) : '';
	$value = isset( $rule['value'] ) ? trim( (string) $rule['value'] ) : '';

	if ( in_array( $type, array( 'wc_cart', 'wc_checkout', 'wc_product', 'wc_shop' ), true ) ) {
		return false;
	}

	return '' === $value;
}

/**
 * Page rules that can actually match a request.
 *
 * Empty path values are skipped. An empty result means "entire site".
 *
 * @param array $targeting Targeting array.
 * @return array
 */
function wpdai_experiment_effective_page_rules( $targeting ) {
	$pages = isset( $targeting['pages'] ) && is_array( $targeting['pages'] ) ? $targeting['pages'] : array();
	$valid = array();

	foreach ( $pages as $rule ) {
		if ( wpdai_experiment_page_rule_is_empty( $rule ) ) {
			continue;
		}
		$valid[] = $rule;
	}

	return $valid;
}

/**
 * Whether targeting is "broad" (site-wide cache bypass risk).
 *
 * @param array $targeting Targeting array.
 * @return bool
 */
function wpdai_experiment_targeting_is_broad( $targeting ) {
	$pages = wpdai_experiment_effective_page_rules( is_array( $targeting ) ? $targeting : array() );
	if ( empty( $pages ) ) {
		return true;
	}
	$broad_types = array( 'wc_product', 'wc_shop', 'post_type', 'regex' );
	foreach ( $pages as $rule ) {
		$type = isset( $rule['type'] ) ? $rule['type'] : '';
		if ( in_array( $type, $broad_types, true ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Validate Pro PHP snippet syntax without executing it.
 *
 * @param string $php Snippet source.
 * @return string|null Error message, or null if valid/empty.
 */
function wpdai_experiment_php_syntax_error( $php ) {
	$php = is_string( $php ) ? trim( $php ) : '';
	if ( '' === $php ) {
		return null;
	}

	$php = preg_replace( '/^\s*<\?(php)?/i', '', $php );
	$php = preg_replace( '/\?>\s*$/', '', $php );

	if ( ! defined( 'TOKEN_PARSE' ) || ! function_exists( 'token_get_all' ) ) {
		return null;
	}

	try {
		token_get_all( '<?php ' . $php, TOKEN_PARSE );
	} catch ( ParseError $e ) {
		return $e->getMessage();
	}

	return null;
}

/**
 * Two-proportion z-test and a plain-language verdict for an A/B arm vs control.
 *
 * Statuses:
 * - needs_data: sample is too small for a reliable z-test
 * - significant: p <= 0.05 (95% confidence)
 * - directional: 0.05 < p <= 0.20 (leaning, not proven)
 * - inconclusive: p > 0.20
 *
 * @param int $control_successes    Control conversions.
 * @param int $control_n            Control exposures.
 * @param int $treatment_successes  Treatment conversions.
 * @param int $treatment_n          Treatment exposures.
 * @return array<string, mixed>
 */
function wpdai_experiment_significance_result( $control_successes, $control_n, $treatment_successes, $treatment_n ) {
	$c1 = max( 0, (int) $control_successes );
	$n1 = max( 0, (int) $control_n );
	$c2 = max( 0, (int) $treatment_successes );
	$n2 = max( 0, (int) $treatment_n );

	$p1   = $n1 > 0 ? $c1 / $n1 : 0.0;
	$p2   = $n2 > 0 ? $c2 / $n2 : 0.0;
	$lift = ( $n1 > 0 && $p1 > 0 ) ? ( ( $p2 - $p1 ) / $p1 ) * 100 : null;
	$ci   = wpdai_experiment_wilson_interval( $c2, $n2 );

	$result = array(
		'status'     => 'needs_data',
		'label'      => __( 'Needs more data', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'summary'    => __( 'Not enough visitors yet to test this result.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'p_value'    => null,
		'z'          => null,
		'confidence' => null,
		'lift'       => $lift,
		'rate'       => $p2,
		'rate_low'   => $ci['low'],
		'rate_high'  => $ci['high'],
		'direction'  => ( null !== $lift && $lift > 0 ) ? 'up' : ( ( null !== $lift && $lift < 0 ) ? 'down' : 'flat' ),
		'ready'      => false,
	);

	$pooled     = ( $n1 + $n2 ) > 0 ? ( $c1 + $c2 ) / ( $n1 + $n2 ) : 0.0;
	$expected_1 = $n1 * $pooled;
	$expected_2 = $n2 * $pooled;
	$ready      = ( $n1 >= 100 && $n2 >= 100 && $expected_1 >= 5 && $expected_2 >= 5 && ( $n1 * ( 1 - $pooled ) ) >= 5 && ( $n2 * ( 1 - $pooled ) ) >= 5 );
	$result['ready'] = $ready;

	if ( $n1 < 1 || $n2 < 1 ) {
		return $result;
	}

	$z = wpdai_experiment_two_proportion_z( $c1, $n1, $c2, $n2 );
	if ( null === $z ) {
		$result['summary'] = __( 'The conversion rates are too similar, or the sample is too small, to calculate a p-value.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		return $result;
	}

	$p_value              = wpdai_experiment_two_tailed_p_from_z( $z );
	$result['z']          = $z;
	$result['p_value']    = $p_value;
	$result['confidence'] = round( ( 1 - $p_value ) * 100, 1 );

	if ( ! $ready ) {
		$result['summary'] = sprintf(
			/* translators: 1: control visitors, 2: treatment visitors */
			__( 'Need about 100 visitors in each arm, with enough conversions, before this result is reliable. Control has %1$s, treatment has %2$s.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			number_format_i18n( $n1 ),
			number_format_i18n( $n2 )
		);
		return $result;
	}

	if ( $p_value <= 0.05 ) {
		$result['status']  = 'significant';
		$result['label']   = __( 'Significant', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		$result['summary'] = ( 'up' === $result['direction'] )
			? __( 'This variant is statistically ahead of control at 95% confidence.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
			: __( 'This variant is statistically behind control at 95% confidence.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		return $result;
	}

	if ( $p_value <= 0.20 ) {
		$result['status']  = 'directional';
		$result['label']   = __( 'Directional', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		$result['summary'] = __( 'The result is leaning this way, but it is not yet significant at 95% confidence.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
		return $result;
	}

	$result['status']  = 'inconclusive';
	$result['label']   = __( 'Inconclusive', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
	$result['summary'] = __( 'No reliable difference versus control yet. Keep the test running.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
	return $result;
}

/**
 * Wilson 95% confidence interval for a proportion.
 *
 * @param int   $successes Successes.
 * @param int   $n         Sample size.
 * @param float $z         Z critical value.
 * @return array{low:float,high:float}
 */
function wpdai_experiment_wilson_interval( $successes, $n, $z = 1.96 ) {
	$successes = max( 0, (int) $successes );
	$n         = max( 0, (int) $n );
	if ( $n < 1 ) {
		return array(
			'low'  => 0.0,
			'high' => 0.0,
		);
	}

	$p      = $successes / $n;
	$z2     = $z * $z;
	$denom  = 1 + ( $z2 / $n );
	$center = ( $p + ( $z2 / ( 2 * $n ) ) ) / $denom;
	$margin = ( $z * sqrt( ( ( $p * ( 1 - $p ) ) / $n ) + ( $z2 / ( 4 * $n * $n ) ) ) ) / $denom;

	return array(
		'low'  => max( 0, $center - $margin ),
		'high' => min( 1, $center + $margin ),
	);
}

/**
 * Two-proportion z statistic (treatment minus control).
 *
 * @param int $c1 Control successes.
 * @param int $n1 Control sample.
 * @param int $c2 Treatment successes.
 * @param int $n2 Treatment sample.
 * @return float|null
 */
function wpdai_experiment_two_proportion_z( $c1, $n1, $c2, $n2 ) {
	$n1 = (int) $n1;
	$n2 = (int) $n2;
	if ( $n1 < 1 || $n2 < 1 ) {
		return null;
	}

	$p  = ( (int) $c1 + (int) $c2 ) / ( $n1 + $n2 );
	$se = sqrt( $p * ( 1 - $p ) * ( ( 1 / $n1 ) + ( 1 / $n2 ) ) );
	if ( $se <= 0 ) {
		return null;
	}

	return ( ( (int) $c2 / $n2 ) - ( (int) $c1 / $n1 ) ) / $se;
}

/**
 * Two-tailed p-value from a z score.
 *
 * @param float $z Z score.
 * @return float
 */
function wpdai_experiment_two_tailed_p_from_z( $z ) {
	$p = 2 * ( 1 - wpdai_experiment_normal_cdf( abs( (float) $z ) ) );
	return round( min( 1, max( 0, $p ) ), 4 );
}

/**
 * Standard normal CDF.
 *
 * @param float $x Value.
 * @return float
 */
function wpdai_experiment_normal_cdf( $x ) {
	return 0.5 * ( 1 + wpdai_experiment_erf( $x / sqrt( 2 ) ) );
}

/**
 * Error function approximation.
 *
 * @param float $x Value.
 * @return float
 */
function wpdai_experiment_erf( $x ) {
	$sign = $x < 0 ? -1 : 1;
	$x    = abs( (float) $x );
	$a1   = 0.254829592;
	$a2   = -0.284496736;
	$a3   = 1.421413741;
	$a4   = -1.453152027;
	$a5   = 1.061405429;
	$p    = 0.3275911;
	$t    = 1 / ( 1 + $p * $x );
	$y    = 1 - ( ( ( ( ( $a5 * $t + $a4 ) * $t ) + $a3 ) * $t + $a2 ) * $t + $a1 ) * $t * exp( -$x * $x );
	return $sign * $y;
}
