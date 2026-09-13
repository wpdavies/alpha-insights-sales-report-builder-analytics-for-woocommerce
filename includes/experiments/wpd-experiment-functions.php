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
			'label'      => __( 'Purchases', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'rate_label' => __( 'Purchase rate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'kind'       => 'count',
			'pro'        => false,
		),
		'add_to_cart'          => array(
			'label'      => __( 'Add to carts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'rate_label' => __( 'Add to cart rate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'kind'       => 'count',
			'pro'        => true,
		),
		'initiate_checkout'    => array(
			'label'      => __( 'Initiate checkouts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'rate_label' => __( 'Checkout rate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'kind'       => 'count',
			'pro'        => true,
		),
		'purchase_value'       => array(
			'label'      => __( 'Purchase value', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'rate_label' => __( 'Revenue / visitor', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'kind'       => 'currency',
			'pro'        => true,
		),
		'revenue_per_exposure' => array(
			'label'      => __( 'Revenue per exposure', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'rate_label' => __( 'Rev / exposure', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'kind'       => 'currency',
			'pro'        => true,
		),
		'aov'                  => array(
			'label'      => __( 'Average order value', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'rate_label' => __( 'AOV', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
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
		'transaction'          => __( 'Purchases', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'add_to_cart'          => __( 'Add to carts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'initiate_checkout'    => __( 'Initiate checkouts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'purchase_value'       => __( 'Purchase value', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'revenue_per_exposure' => __( 'Revenue per exposure', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'aov'                  => __( 'Average order value', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'page_view'            => __( 'Page view', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		'event'                => __( 'Custom event', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
	);
	if ( isset( $labels[ $goal_type ] ) ) {
		return $labels[ $goal_type ];
	}
	return $labels['transaction'];
}

/**
 * Map a stored primary goal to a list-table conversion objective.
 *
 * @param string $goal_type Stored primary goal type.
 * @return string
 */
function wpdai_map_experiment_goal_to_objective( $goal_type ) {
	$goal_type   = sanitize_key( (string) $goal_type );
	$objectives  = wpdai_get_experiment_conversion_objectives();
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
