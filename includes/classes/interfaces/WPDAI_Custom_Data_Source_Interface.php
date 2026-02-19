<?php
/**
 * Interface for Custom Data Sources
 *
 * Allows developers to extend Alpha Insights with custom data sources
 * that integrate seamlessly with the reporting system.
 *
 * RECOMMENDED: Use WPDAI_Custom_Data_Source_Base instead of implementing
 * this interface directly. The base class handles registration boilerplate
 * and provides optional defaults for get_data_mapping() and get_entity_names().
 *
 * @package Alpha Insights
 * @since 5.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * Interface for custom data sources
 *
 * Defines the contract that custom data sources must implement to integrate
 * with Alpha Insights reporting system. Supports both one-to-one (single entity)
 * and one-to-many (multiple entities from one source) patterns.
 *
 * IMPLEMENTATION NOTE: Consider extending WPDAI_Custom_Data_Source_Base
 * instead of implementing this interface directly. The base class handles
 * automatic registration and provides default implementations for
 * get_data_mapping() (returns empty array) and uses the $entity_names
 * property for get_entity_name() / get_entity_names().
 *
 * @since 5.0.0
 */
interface WPDAI_Custom_Data_Source_Interface {

    /**
     * Get the entity name for this data source
     *
     * This should be a unique identifier (e.g., 'custom_inventory', 'third_party_analytics')
     * that will be used to reference this data source in widgets and reports.
     *
     * For single-entity sources, this is the primary entity name. For multi-entity
     * sources, this is typically the "primary" or first entity name. Use
     * get_entity_names() to expose all entities this source provides.
     *
     * The entity name is used as the key in data structures and must be unique.
     * It should be lowercase, use underscores instead of spaces, and be descriptive.
     *
     * @since 5.0.0
     *
     * @return string The entity name (e.g., 'example_custom_data')
     */
    public function get_entity_name();

    /**
     * Get all entity names this data source provides
     *
     * Use this for one-to-many data sources that fill multiple entity slots
     * (e.g. orders, products, customers from one fetch). For one-to-one
     * sources, return an array with a single entity name (same as get_entity_name()).
     *
     * @since 5.0.0
     *
     * @return array<string> List of entity names this source can provide
     */
    public function get_entity_names();

    /**
     * Fetch data for this custom data source
     *
     * The data warehouse is required. Get filters via $data_warehouse->get_filter() (or get_filter( 'date_from' ), etc.).
     * You should use get_data_by_date_range_container() from the warehouse for data_by_date alignment.
     *
     * Return format: one entity = single-entity structure (totals, data_by_date, etc.);
     * multiple entities = array keyed by entity name, each value a single-entity structure.
     *
     * Passing a non-WPDAI_Data_Warehouse instance triggers a fatal error (type enforcement).
     *
     * @since 5.0.0
     *
     * @param WPDAI_Data_Warehouse $data_warehouse The data warehouse instance. Required.
     *   Use for: get_filter(), get_data_by_date_range_container(), get_date_from(), get_date_to(),
     *   get_data(), get_store_currency(), get_data_table_limit(), etc.
     *
     * @return array Single-entity structure OR multi-entity keyed by entity names.
     *   Keys: totals, categorized_data, data_table, data_by_date, total_db_records (execution_time/memory_usage added by warehouse).
     */
    public function fetch_data( WPDAI_Data_Warehouse $data_warehouse );

    /**
     * Get the data mapping configuration for React (optional)
     *
     * Return a PHP array that defines how React should display and format
     * your data. Return an empty array to indicate no custom mapping (e.g.
     * for built-in entities that use core/localized mappings).
     *
     * When provided, the structure must match the JavaScript mapping files.
     * The registry keys this by entity name when passing to the frontend.
     *
     * For single-entity: return one mapping structure (keys: 'totals', 'data_by_date', etc.).
     * For multi-entity: return array keyed by entity name, each value a mapping structure.
     *
     * @since 5.0.0
     *
     * @return array Empty array for no mapping, or mapping structure (single-entity) or
     *               array of entity_name => mapping (multi-entity). See interface docblock for structure.
     */
    public function get_data_mapping();
}
