<?php
/**
 * Registry for Custom Data Sources
 *
 * Manages registration and retrieval of custom data sources that extend
 * Alpha Insights reporting capabilities.
 *
 * @package Alpha Insights
 * @since 5.0.0
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

class WPD_Custom_Data_Source_Registry {

    /**
     * Registered data sources
     *
     * @var array<string, WPD_Custom_Data_Source_Interface>
     */
    private static array $data_sources = array();

    /**
     * Whether the registry has been initialized
     *
     * @var bool
     */
    private static bool $initialized = false;

    /**
     * Initialize the registry
     *
     * Collects all registered data sources via WordPress filter
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function init() {
        if ( self::$initialized ) {
            return;
        }

        /**
         * Filter to register custom data sources
         *
         * @since 5.0.0
         *
         * @param array $data_sources Array of data source instances
         */
        $registered_sources = apply_filters( 'wpd_alpha_insights_register_data_sources', array() );

        foreach ( $registered_sources as $source ) {
            if ( $source instanceof WPD_Custom_Data_Source_Interface ) {
                $entity_name = $source->get_entity_name();
                
                // Validate entity name
                if ( empty( $entity_name ) || ! is_string( $entity_name ) ) {
                    continue;
                }

                // Check for conflicts with built-in entities
                $built_in_entities = array(
                    'orders', 'customers', 'products', 'coupons', 'taxes', 'refunds',
                    'subscriptions', 'expenses', 'store_profit', 'facebook_campaigns',
                    'google_campaigns', 'analytics', 'anonymous_queries'
                );

                if ( in_array( $entity_name, $built_in_entities, true ) ) {
                    continue;
                }

                self::$data_sources[ $entity_name ] = $source;
            }
        }

        self::$initialized = true;
    }

    /**
     * Get all registered data sources
     *
     * @since 5.0.0
     *
     * @return array<string, WPD_Custom_Data_Source_Interface>
     */
    public static function get_all() {
        self::init();
        return self::$data_sources;
    }

    /**
     * Get a specific data source by entity name
     *
     * @since 5.0.0
     *
     * @param string $entity_name The entity name
     * @return WPD_Custom_Data_Source_Interface|null
     */
    public static function get( $entity_name ) {
        self::init();
        return self::$data_sources[ $entity_name ] ?? null;
    }

    /**
     * Check if a data source is registered
     *
     * @since 5.0.0
     *
     * @param string $entity_name The entity name
     * @return bool
     */
    public static function has( $entity_name ) {
        self::init();
        return isset( self::$data_sources[ $entity_name ] );
    }

    /**
     * Get all entity names
     *
     * @since 5.0.0
     *
     * @return array<string>
     */
    public static function get_entity_names() {
        self::init();
        return array_keys( self::$data_sources );
    }

    /**
     * Get all data mappings for React
     *
     * Collects mappings from all registered data sources and formats
     * them for passing to React via localized variables.
     *
     * @since 5.0.0
     *
     * @return array Array of mappings keyed by entity name
     */
    public static function get_all_mappings() {
        self::init();
        $mappings = array();

        foreach ( self::$data_sources as $entity_name => $source ) {
            try {
                $mapping = $source->get_data_mapping();
                if ( is_array( $mapping ) && ! empty( $mapping ) ) {
                    $mappings[ $entity_name ] = $mapping;
                }
            } catch ( Exception $e ) {
                // Log error but don't break the process
                if ( function_exists( 'wpdai_write_log' ) ) {
                    wpdai_write_log( sprintf(
                        /* translators: %1$s: entity name, %2$s: error message */
                        __( 'Error getting mapping for data source %1$s: %2$s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                        esc_html( $entity_name ),
                        esc_html( $e->getMessage() )
                    ), 'custom_data_source_registry' );
                }
            }
        }

        return $mappings;
    }
}




