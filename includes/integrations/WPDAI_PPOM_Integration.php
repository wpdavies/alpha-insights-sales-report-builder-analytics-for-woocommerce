<?php
/**
 * PPOM for WooCommerce integration – cost tracking for Alpha Insights
 *
 * When PPOM is active, adds a "Cost" field to PPOM field settings (stored in meta)
 * and supplies PPOM add-on cost to Alpha Insights order calculations.
 *
 * @package Alpha Insights
 * @since 5.4.14
 */
defined( 'ABSPATH' ) || exit;

/**
 * Class WPDAI_PPOM_Integration
 */
class WPDAI_PPOM_Integration {

	const CUSTOM_COST_SLUG = 'wpd_ai_ppom_addon_cost';

	/**
	 * Bootstrap: register hooks only when PPOM is present.
	 */
	public static function init() {
		if ( ! self::is_ppom_active() ) {
			return;
		}
		self::add_ppom_cost_setting_filters();
		self::add_alpha_insights_cost_filters();
	}

	/**
	 * Check if PPOM for WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function is_ppom_active() {
		if ( defined( 'PPOM_PATH' ) && class_exists( 'PPOM_Meta' ) ) {
			return true;
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$slug = 'woocommerce-product-addon/woocommerce-product-addon.php';
		return is_plugin_active( $slug );
	}

	/**
	 * Add filters to inject "Cost" into PPOM field settings for every type.
	 * Discovers types from PPOM's input files (and filter) without calling PPOM(),
	 * so our filters are registered before PPOM ever builds its settings.
	 */
	private static function add_ppom_cost_setting_filters() {
		$types = self::get_ppom_input_types();
		foreach ( $types as $type ) {
			if ( is_string( $type ) && $type !== '' ) {
				add_filter( 'poom_' . $type . '_input_setting', array( __CLASS__, 'inject_cost_setting' ), 10, 2 );
			}
		}
	}

	/**
	 * Get list of PPOM input types without calling PPOM() (which would build settings before our filters exist).
	 *
	 * @return string[]
	 */
	private static function get_ppom_input_types() {
		$types = array();
		if ( defined( 'PPOM_PATH' ) && is_dir( PPOM_PATH . '/classes/inputs' ) ) {
			$files = (array) glob( PPOM_PATH . '/classes/inputs/input.*.php' );
			foreach ( $files as $file ) {
				$base = basename( $file, '.php' );
				if ( strpos( $base, 'input.' ) === 0 ) {
					$types[] = substr( $base, 6 );
				}
			}
		}
		$fallback = array( 'text', 'textarea', 'select', 'radio', 'checkbox', 'email', 'date', 'number', 'hidden' );
		$types    = array_unique( array_merge( $types, $fallback ) );
		return apply_filters( 'wpd_ai_ppom_cost_input_types', $types );
	}

	/**
	 * Inject the 'cost' setting into PPOM input meta (after 'price' when present).
	 *
	 * @param array $input_meta Existing settings for the input type.
	 * @param object $input_obj The PPOM input object (unused).
	 * @return array
	 */
	public static function inject_cost_setting( $input_meta, $input_obj = null ) {
		if ( ! is_array( $input_meta ) ) {
			return $input_meta;
		}
		$cost_setting = array(
			'cost' => array(
				'type'        => 'text',
				'title'       => __( 'Cost (for profit tracking)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'desc'        => __( 'Your cost for this add-on. Used by Alpha Insights for COGS and profit reports.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'col_classes' => array( 'col-md-3', 'col-sm-12' ),
			),
		);
		// Insert after 'price' if present, otherwise at end.
		if ( isset( $input_meta['price'] ) ) {
			$pos   = array_search( 'price', array_keys( $input_meta ), true );
			$keys  = array_keys( $input_meta );
			$vals  = array_values( $input_meta );
			$after = $pos + 1;
			$input_meta = array_merge(
				array_slice( $input_meta, 0, $after, true ),
				$cost_setting,
				array_slice( $input_meta, $after, null, true )
			);
		} else {
			$input_meta = array_merge( $input_meta, $cost_setting );
		}
		return $input_meta;
	}

	/**
	 * Register Alpha Insights filters for PPOM add-on cost.
	 */
	private static function add_alpha_insights_cost_filters() {
		add_filter( 'wpd_ai_custom_product_cost_options', array( __CLASS__, 'register_ppom_custom_cost_option' ), 10, 2 );
		add_filter( 'wpd_ai_custom_product_cost_default_value', array( __CLASS__, 'get_ppom_addon_cost_for_line_item' ), 10, 4 );
	}

	/**
	 * Add PPOM add-on cost as a custom product cost option (so it's included in calculations).
	 *
	 * @param array $options Existing custom product cost options.
	 * @param int   $product_id Product ID.
	 * @return array
	 */
	public static function register_ppom_custom_cost_option( $options, $product_id ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$options[ self::CUSTOM_COST_SLUG ] = array(
			'label'               => __( 'PPOM add-on cost', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'description'         => __( 'Cost of PPOM options selected for this product (from field Cost setting).', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'placeholder'         => 0,
			'static_fee'          => null,
			'percent_of_sell_price' => null,
		);
		return $options;
	}

	/**
	 * Compute PPOM add-on cost for a line item (per unit) for Alpha Insights.
	 *
	 * @param float                  $default_value Default value (ignored for our slug).
	 * @param string                 $custom_cost_slug Custom product cost slug.
	 * @param WC_Order               $order Order.
	 * @param WC_Order_Item_Product  $item Line item.
	 * @return float Cost per unit.
	 */
	public static function get_ppom_addon_cost_for_line_item( $default_value, $custom_cost_slug, $order, $item ) {
		if ( $custom_cost_slug !== self::CUSTOM_COST_SLUG || ! is_a( $item, 'WC_Order_Item_Product' ) ) {
			return $default_value;
		}
		$product_id = (int) $item->get_product_id();
		$variation_id = (int) $item->get_variation_id();
		$active_product_id = ( $variation_id > 0 ) ? $variation_id : $product_id;
		$ppom_fields = $item->get_meta( '_ppom_fields' );
		if ( empty( $ppom_fields ) || ! is_array( $ppom_fields ) || empty( $ppom_fields['fields'] ) ) {
			return 0.0;
		}
		if ( ! class_exists( 'PPOM_Meta' ) ) {
			return 0.0;
		}
		$ppom = new PPOM_Meta( $product_id );
		if ( empty( $ppom->fields ) || ! is_array( $ppom->fields ) ) {
			return 0.0;
		}
		$posted = $ppom_fields['fields'];
		$total_cost = 0.0;
		foreach ( $ppom->fields as $field ) {
			$data_name = isset( $field['data_name'] ) ? $field['data_name'] : '';
			if ( $data_name === '' ) {
				continue;
			}
			$data_name = sanitize_key( $data_name );
			if ( ! isset( $posted[ $data_name ] ) ) {
				continue;
			}
			$value = $posted[ $data_name ];
			if ( $value === '' || $value === null ) {
				continue;
			}
			// Simple fields (text, textarea, number, email, date, etc.) with a 'cost' setting.
			if ( empty( $field['options'] ) || ! is_array( $field['options'] ) ) {
				$cost = isset( $field['cost'] ) ? $field['cost'] : '';
				if ( $cost !== '' ) {
					$cost = floatval( $cost );
					if ( $cost > 0 ) {
						$total_cost += $cost;
					}
				}
				continue;
			}
			// Option-based fields (select, radio, checkbox): use per-option 'cost' when set.
			$value_arr = is_array( $value ) ? $value : array( $value );
			foreach ( $value_arr as $selected ) {
				foreach ( $field['options'] as $opt ) {
					$opt_id     = isset( $opt['id'] ) ? $opt['id'] : '';
					$opt_option = isset( $opt['option'] ) ? $opt['option'] : '';
					if ( (string) $selected !== (string) $opt_id && (string) $selected !== (string) $opt_option ) {
						continue;
					}
					if ( isset( $opt['cost'] ) && $opt['cost'] !== '' ) {
						$total_cost += floatval( $opt['cost'] );
					}
					break;
				}
			}
		}
		return (float) $total_cost;
	}
}

// Run when file is loaded (plugins_loaded) so filters are in place before PPOM builds settings.
WPDAI_PPOM_Integration::init();
