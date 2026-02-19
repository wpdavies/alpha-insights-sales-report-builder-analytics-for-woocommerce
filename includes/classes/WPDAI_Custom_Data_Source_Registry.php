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

class WPDAI_Custom_Data_Source_Registry {

    /**
     * Registered data sources
     *
     * @var array<string, WPDAI_Custom_Data_Source_Interface>
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

        $reserved_entities = self::get_reserved_entity_names();

        foreach ( $registered_sources as $source ) {
            if ( $source instanceof WPDAI_Custom_Data_Source_Interface ) {
                $entity_names = $source->get_entity_names();

                if ( ! is_array( $entity_names ) || empty( $entity_names ) ) {
                    continue;
                }

                foreach ( $entity_names as $entity_name ) {
                    if ( empty( $entity_name ) || ! is_string( $entity_name ) ) {
                        continue;
                    }

                    if ( in_array( $entity_name, $reserved_entities, true ) ) {
                        continue;
                    }

                    self::$data_sources[ $entity_name ] = $source;
                }
            }
        }

        self::$initialized = true;
    }

    /**
     * Get reserved entity names that custom sources cannot register
     *
     * Protection is disabled for now (empty default). All data sources register
     * the same way; reserve list can be reintroduced later if needed.
     *
     * @since 5.0.0
     *
     * @return array<string> Entity names that are reserved (not registerable by custom sources)
     */
    public static function get_reserved_entity_names() {
        $default = array();

        /**
         * Filter reserved entity names for the data source registry
         *
         * @since 5.0.0
         * @param array $default Default reserved entity names
         */
        return apply_filters( 'wpd_alpha_insights_reserved_entity_names', $default );
    }

    /**
     * Get all registered data sources
     *
     * @since 5.0.0
     *
     * @return array<string, WPDAI_Custom_Data_Source_Interface>
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
     * @return WPDAI_Custom_Data_Source_Interface|null
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
     * Collects mappings from all registered data sources that provide
     * non-empty get_data_mapping(). Sources returning empty array are skipped
     * (e.g. built-in entities using core mappings). Supports single-entity
     * and multi-entity mapping structures.
     *
     * @since 5.0.0
     *
     * @return array Array of mappings keyed by entity name
     */
    public static function get_all_mappings() {
        self::init();
        $mappings = array();
        $seen_sources = array();

        foreach ( self::$data_sources as $entity_name => $source ) {
            $source_id = spl_object_id( $source );
            if ( isset( $seen_sources[ $source_id ] ) ) {
                continue;
            }
            $seen_sources[ $source_id ] = true;

            try {
                $mapping = $source->get_data_mapping();
                if ( ! is_array( $mapping ) || empty( $mapping ) ) {
                    continue;
                }

                // Single-entity mapping: has 'totals' (or similar) at top level.
                if ( isset( $mapping['totals'] ) || isset( $mapping['data_by_date'] ) || isset( $mapping['data_table'] ) || isset( $mapping['categorized_data'] ) ) {
                    foreach ( $source->get_entity_names() as $en ) {
                        $mappings[ $en ] = $mapping;
                    }
                    continue;
                }

                // Multi-entity mapping: keyed by entity name.
                foreach ( $mapping as $en => $map ) {
                    if ( is_string( $en ) && is_array( $map ) ) {
                        $mappings[ $en ] = $map;
                    }
                }
            } catch ( Exception $e ) {
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




