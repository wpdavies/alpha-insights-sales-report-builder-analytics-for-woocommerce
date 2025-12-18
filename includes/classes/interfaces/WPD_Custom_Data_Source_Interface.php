<?php
/**
 * Interface for Custom Data Sources
 *
 * Allows developers to extend Alpha Insights with custom data sources
 * that integrate seamlessly with the reporting system.
 *
 * RECOMMENDED: Use WPD_Custom_Data_Source_Base instead of implementing
 * this interface directly. The base class handles registration boilerplate automatically.
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
 * with Alpha Insights reporting system.
 *
 * IMPLEMENTATION NOTE: Consider extending WPD_Custom_Data_Source_Base
 * instead of implementing this interface directly. The base class handles
 * automatic registration and you only need to set the entity_name property.
 *
 * @since 5.0.0
 */
interface WPD_Custom_Data_Source_Interface {

    /**
     * Get the entity name for this data source
     *
     * This should be a unique identifier (e.g., 'custom_inventory', 'third_party_analytics')
     * that will be used to reference this data source in widgets and reports.
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
     * Fetch data for this custom data source
     *
     * This method should fetch and return data following the same structure
     * as the data warehouse. All keys are optional, but should match the format
     * exactly if provided.
     *
     * The data warehouse instance is passed to allow access to helper methods
     * like date range containers, filters, and other entity data. You should
     * ALWAYS use get_data_by_date_range_container() from the data warehouse
     * to initialize data_by_date metrics for proper date alignment.
     *
     * @since 5.0.0
     *
     * @param array $filters Array of filters passed from the report configuration:
     *                      - 'date_from' (string): Start date in Y-m-d format (e.g., '2024-01-01')
     *                      - 'date_to' (string): End date in Y-m-d format (e.g., '2024-12-31')
     *                      - 'date_format_display' (string): Date grouping format - 'day', 'month', 'quarter', or 'year'
     *                      - Additional custom filters may be present based on widget configuration
     * @param WPD_Data_Warehouse_React|null $data_warehouse The data warehouse instance.
     *                                                      Provides access to helper methods:
     *                                                      - get_data_by_date_range_container() - Returns empty date array for initialization
     *                                                      - get_data_by_date_containers() - Returns full date containers with metadata
     *                                                      - get_date_from($format) - Get start date in specified format
     *                                                      - get_date_to($format) - Get end date in specified format
     *                                                      - get_filter($key, $default) - Get a specific filter value
     *                                                      - get_data($entity, $key) - Access data from other entities (orders, customers, etc.)
     *                                                      - get_store_currency() - Get WooCommerce store currency code
     *                                                      - get_data_table_limit($entity) - Get data table limit for pagination
     *                                                      - And more - see WPD_Data_Warehouse_React class for full API
     *
     * @return array Data structure matching the warehouse format. All keys are optional:
     *               - 'totals' (array): Aggregated totals/metrics keyed by metric name.
     *                                   Example: array( 'total_custom_metric' => 1234.56, 'total_count' => 42 )
     *               - 'categorized_data' (array): Data grouped by categories for pie/bar charts.
     *                                             Structure: array( 'category_key' => array( 'label' => 'Category Name', 'total_custom_metric' => 100, ... ) )
     *               - 'data_table' (array): Table data for data table widgets.
     *                                      Structure: array( 'table_key' => array( array( 'column1' => 'value1', ... ), ... ) )
     *               - 'data_by_date' (array): Time series data keyed by metric name, then date.
     *                                        MUST initialize with $data_warehouse->get_data_by_date_range_container().
     *                                        Structure: array( 'metric_key' => array( '2024-01-01' => 100, '2024-01-02' => 200, ... ) )
     *               - 'total_db_records' (int): Total number of database records processed (for performance tracking)
     *               - 'execution_time' (float): **AUTOMATICALLY TRACKED** - Do NOT include this in your return array.
     *                                          Execution time is automatically measured and added by the data warehouse.
     *               - 'memory_usage' (int): **AUTOMATICALLY TRACKED** - Do NOT include this in your return array.
     *                                      Memory usage is automatically measured and added by the data warehouse (in bytes).
     *
     * @example
     * <code>
     * public function fetch_data( $filters, $data_warehouse = null ) {
     *     // NOTE: execution_time and memory_usage are automatically tracked
     *     
     *     if ( ! $data_warehouse ) {
     *         return array( 'totals' => array() );
     *     }
     *     
     *     // Get date range container for proper date alignment
     *     $date_range_container = $data_warehouse->get_data_by_date_range_container();
     *     
     *     // Fetch your data here...
     *     
     *     return array(
     *         'totals' => array( 'my_metric' => 100 ),
     *         'data_by_date' => array( 'my_metric_by_date' => $date_range_container ), // Initialize with container
     *         'total_db_records' => 50,
     *         // execution_time and memory_usage are automatically added - don't include them
     *     );
     * }
     * </code>
     */
    public function fetch_data( $filters, $data_warehouse = null );

    /**
     * Get the data mapping configuration
     *
     * This method returns a PHP array structure that defines how React should display
     * and format your custom data. The structure must match the JavaScript mapping files
     * (totalsDataMapping.js, dataByDateMapping.js, categorizedDataMapping.js, dataTableMapping.js)
     * exactly.
     *
     * The returned array will be JSON encoded and passed to React via localized variables
     * under the key 'custom_data_source_mappings'. React automatically merges these mappings
     * into the main mapping objects.
     *
     * IMPORTANT: The registry automatically keys this by your entity name (from get_entity_name()),
     * so you return the mapping data directly - NOT wrapped in another entity key.
     *
     * @since 5.0.0
     *
     * @return array Data mapping structure. All top-level keys are optional, but include what you use:
     *               - 'totals' (array): Mapping for totals metrics. Structure:
     *                                  array(
     *                                      'label' => 'Display Name',        // Label shown in selectors
     *                                      'icon' => 'analytics',            // Material Icons name
     *                                      'totals' => array(               // Metric definitions
     *                                          'metric_key' => array(       // MUST match fetch_data() 'totals' keys
     *                                              'label' => 'Metric Label',
     *                                              'type' => 'currency',     // currency, number, integer, percentage, text, date
     *                                              'format' => 'currency',   // currency, integer, decimal, percentage
     *                                              'description' => 'Help text',
     *                                          ),
     *                                      ),
     *                                  )
     *               - 'data_by_date' (array): Mapping for time series metrics. Structure:
     *                                       array(
     *                                          'metric_key' => array(      // MUST match fetch_data() 'data_by_date' keys
     *                                              'label' => 'Metric Over Time',
     *                                              'type' => 'currency',
     *                                              'format' => 'currency',
     *                                              'description' => 'Time series description',
     *                                              'chart_calculation' => 'sum', // sum, average, count (optional)
     *                                          ),
     *                                      )
     *               - 'categorized_data' (array): Mapping for categorized metrics (pie/bar charts). Structure:
     *                                           array(
     *                                              'category_key' => array( // Category identifier
     *                                                  'label' => 'Category Display Name',
     *                                                  'description' => 'Category description',
     *                                                  'color' => '#138fdd',         // Hex color for charts
     *                                                  'icon' => 'category',          // Material Icons name
     *                                                  'metric_fields' => array(      // Metrics to display
     *                                                      array(
     *                                                          'label' => 'Metric Label',
     *                                                          'value' => 'metric_key', // Key in categorized_data array
     *                                                          'type' => 'currency',
     *                                                      ),
     *                                                  ),
     *                                              ),
     *                                          )
     *               - 'data_table' (array): Mapping for data table widgets. Structure:
     *                                     array(
     *                                         'table_key' => array(        // Table identifier
     *                                             'label' => 'Table Name',
     *                                             'description' => 'Table description',
     *                                             'icon' => 'table_chart', // Material Icons name
     *                                             'columns' => array(      // Column definitions
     *                                                 'column_key' => array(
     *                                                     'label' => 'Column Header',
     *                                                     'type' => 'number',         // Data type
     *                                                     'format' => 'integer',      // Display format
     *                                                 ),
     *                                             ),
     *                                         ),
     *                                     )
     *
     * METRIC KEY REQUIREMENTS:
     * - All metric keys in this mapping MUST exactly match the keys used in your fetch_data() return array
     * - For 'totals': Keys must match keys in fetch_data() 'totals' array
     * - For 'data_by_date': Keys must match keys in fetch_data() 'data_by_date' array
     * - For 'categorized_data': Category keys should match keys in fetch_data() 'categorized_data' array
     * - For 'data_table': Table keys should match keys in fetch_data() 'data_table' array
     *
     * FORMAT TYPES:
     * - 'type' accepts: 'currency', 'number', 'integer', 'percentage', 'text', 'date'
     * - 'format' accepts: 'currency', 'integer', 'decimal', 'percentage'
     *
     * @example See Example_Custom_Data_Source class in includes/reports/extensions/ for complete examples
     */
    public function get_data_mapping();
}

