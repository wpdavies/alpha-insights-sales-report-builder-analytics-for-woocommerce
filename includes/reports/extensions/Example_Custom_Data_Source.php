<?php
/**
 * Example Custom Data Source
 *
 * This is an example implementation of a custom data source for Alpha Insights.
 * It demonstrates how to create a custom data source with static data for testing.
 *
 * QUICK START:
 * 1. Extend WPD_Alpha_Insights_Data_Source_Base (handles registration automatically)
 * 2. Set the $entity_name property (the ONLY property you need to set)
 * 3. Implement fetch_data() method to return your data
 * 4. Implement get_data_mapping() method to define how React displays your data
 * 5. Instantiate the class (new Your_Class_Name()) to register it
 *
 * The base class handles all registration boilerplate - you just focus on your data!
 *
 * @package Alpha Insights
 * @since 5.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

// The base class and interface are already loaded by the main plugin file
// No need to include them here

/**
 * Example Custom Data Source Class
 *
 * This class demonstrates how to create a custom data source that integrates
 * with Alpha Insights reporting system.
 *
 * IMPORTANT: This class extends WPDAI_Custom_Data_Source_Base, which handles
 * all registration boilerplate automatically. You only need to:
 * 1. Set the $entity_name property (below) - THIS IS THE ONLY REQUIRED PROPERTY
 * 2. Implement fetch_data() method
 * 3. Implement get_data_mapping() method
 *
 * The base class automatically handles:
 * - Constructor registration (no need to define __construct)
 * - register_data_source() filter hook
 * - get_entity_name() method (returns $entity_name property)
 *
 * @since 5.0.0
 */
class WPD_AI_Example_Custom_Data_Source extends WPDAI_Custom_Data_Source_Base {

    /**
     * Entity name for this data source
     *
     * THIS IS THE ONLY PROPERTY YOU NEED TO SET!
     *
     * Set this to your unique entity identifier. This will be used as the key
     * in data structures and must be unique across all data sources.
     *
     * Requirements:
     * - Must be unique (not used by built-in entities or other custom sources)
     * - Should be lowercase
     * - Use underscores instead of spaces
     * - Should be descriptive (e.g., 'inventory_tracking', 'custom_analytics')
     *
     * @since 5.0.0
     *
     * @var string
     */
    protected $entity_name = 'example_custom_data';

    /**
     * Fetch data for this custom data source
     * 
     * Fetch data from your database, external API, or other data source.
     * 
     * This example uses static data for demonstration purposes.
     * In a real implementation, you would fetch data from your database,
     * external API, or other data source.
     *
     * @since 5.0.0
     *
     * @param array $filters Array of filters
     * @param WPDAI_Data_Warehouse|null $data_warehouse Optional. The data warehouse instance.
     * @return array Data structure
     */
    public function fetch_data( $filters, $data_warehouse = null ) {
        
        // NOTE: Execution time and memory usage are automatically tracked by the data warehouse.
        // You do NOT need to manually track these metrics. They will be automatically added
        // to your data structure when it's stored.

        // Extract date range from filters (for reference)
        $date_from = isset( $filters['date_from'] ) ? $filters['date_from'] : date( 'Y-m-d', strtotime( '-30 days' ) );
        $date_to = isset( $filters['date_to'] ) ? $filters['date_to'] : date( 'Y-m-d' );

        // Get the date range container from the data warehouse
        // This ensures dates match exactly with other data sources
        if ( ! $data_warehouse || ! method_exists( $data_warehouse, 'get_data_by_date_range_container' ) ) {
            // Data warehouse is required for proper date alignment
            return array(
                'totals' => array(),
                'categorized_data' => array(),
                'data_table' => array(),
                'data_by_date' => array(),
                'total_db_records' => 0,
                // execution_time and memory_usage are automatically added - don't include them
            );
        }

        $date_range_container = $data_warehouse->get_data_by_date_range_container();
        $date_range = array_keys( $date_range_container );
        
        // You can also access other data warehouse methods:
        // - $data_warehouse->get_data_by_date_containers() - All date containers
        // - $data_warehouse->get_filter() - Get current filters
        // - $data_warehouse->get_data( 'orders' ) - Access other entities' data
        // - $data_warehouse->get_data_by_date_range_container() - Date range container

        // Example totals data
        $totals = array(
            'total_custom_metric' => 1250.50,
            'total_custom_count' => 42,
            'average_custom_value' => 29.77,
            'custom_percentage' => 15.5,
        );

        // Example categorized data
        $categorized_data = array(
            'custom_categories' => array(
                'category_a' => array(
                    'total_custom_metric' => 500.25,
                    'total_custom_count' => 15,
                ),
                'category_b' => array(
                    'total_custom_metric' => 450.75,
                    'total_custom_count' => 18,
                ),
                'category_c' => array(
                    'total_custom_metric' => 299.50,
                    'total_custom_count' => 9,
                )
            )
        );

        // Example data table
        $data_table = array(
            'example_items' => array(
                array(
                    'id' => 1,
                    'name' => 'Example Item 1',
                    'value' => 125.50,
                    'category' => 'category_a',
                    'date' => $date_from,
                ),
                array(
                    'id' => 2,
                    'name' => 'Example Item 2',
                    'value' => 250.75,
                    'category' => 'category_b',
                    'date' => $date_from,
                ),
                array(
                    'id' => 3,
                    'name' => 'Example Item 3',
                    'value' => 99.25,
                    'category' => 'category_c',
                    'date' => $date_to,
                ),
            ),
        );

        // Example data by date (time series)
        // Initialize using the date range container to ensure all dates are included
        $data_by_date = array(
            'total_custom_metric_by_date' => $date_range_container, // Initialize with date container
            'total_custom_count_by_date' => $date_range_container,  // Initialize with date container
        );
        
        // Populate with example data for each date
        foreach ( $date_range as $date_key ) {
            $data_by_date['total_custom_metric_by_date'][ $date_key ] = rand( 50, 200 );
            $data_by_date['total_custom_count_by_date'][ $date_key ] = rand( 1, 10 );
        }

        // Return data in the required format, these array keys are the supported keys for the data warehouse
        return array(
            'totals' => $totals,
            'categorized_data' => $categorized_data,
            'data_table' => $data_table,
            'data_by_date' => $data_by_date,
            'total_db_records' => 3, // Example: number of records processed, useful for debugging performance
        );
    }

    /**
     * Get the data mapping configuration
     *
     * This method returns a PHP array that defines how React should display and format
     * your custom data. The structure must match the JavaScript mapping files exactly.
     *
     * IMPORTANT: The registry automatically keys this by your entity name (from get_entity_name()),
     * so you return the entity data directly - NOT wrapped in another entity key.
     *
     * The returned array will be JSON encoded and passed to React via localized variables.
     * React will merge these mappings into:
     * - TOTALS_DATA_MAPPING (for totals metrics)
     * - DATA_BY_DATE_MAP (for time series metrics)
     * - CATEGORIZED_DATA_MAP (for categorized/pie chart data)
     * - DATA_TABLE_METRICS (for data table widgets)
     *
     * REQUIRED KEYS (all optional, but include what you use):
     * - 'totals': Maps metrics in your fetch_data() 'totals' array
     * - 'data_by_date': Maps metrics in your fetch_data() 'data_by_date' array
     * - 'categorized_data': Maps categories in your fetch_data() 'categorized_data' array
     * - 'data_table': Maps tables in your fetch_data() 'data_table' array
     *
     * METRIC KEY NAMING:
     * - The keys you use here (e.g., 'total_custom_metric') MUST match the keys
     *   in your fetch_data() return array exactly.
     * - For data_by_date, the metric keys should match what you use in the
     *   data_by_date array structure.
     *
     * FORMAT TYPES:
     * - 'currency': For monetary values (will be formatted with currency symbol)
     * - 'number': For numeric values
     * - 'integer': For whole numbers
     * - 'percentage': For percentage values (0-100)
     * - 'text': For text/string values
     * - 'date': For date values
     *
     * @since 5.0.0
     *
     * @return array Data mapping structure. Must include keys that match your fetch_data() return structure.
     *               Structure: array(
     *                   'totals' => array(...),           // Maps to TOTALS_DATA_MAPPING[entity]
     *                   'data_by_date' => array(...),    // Maps to DATA_BY_DATE_MAP[entity]
     *                   'categorized_data' => array(...), // Maps to CATEGORIZED_DATA_MAP[entity]
     *                   'data_table' => array(...),      // Maps to DATA_TABLE_METRICS[entity]
     *               )
     */
    public function get_data_mapping() {
        return array(
            /**
             * TOTALS MAPPING
             * 
             * Maps the metrics in your fetch_data() 'totals' array.
             * Structure: { label, icon, totals: { metric_key: { label, type, format, description } } }
             * 
             * This will be merged into TOTALS_DATA_MAPPING[entity_name] in React.
             * Each metric_key must match a key in your fetch_data() 'totals' array.
             */
            'totals' => array(
                // Display label for this data source (shown in metric selectors)
                'label' => 'Example Custom Data',
                // Icon name (Material Icons) for this data source
                'icon' => 'analytics',
                // Map each metric from your fetch_data() 'totals' array
                'totals' => array(
                    // Metric key MUST match the key in your fetch_data() 'totals' array
                    'total_custom_metric' => array(
                        'label' => 'Total Custom Metric',        // Display name in UI
                        'type' => 'currency',                    // Data type: currency, number, integer, percentage, text, date
                        'format' => 'currency',                  // Format for display: currency, integer, decimal, percentage
                        'description' => 'Total value of custom metric', // Tooltip/help text
                    ),
                    'total_custom_count' => array(
                        'label' => 'Total Custom Count',
                        'type' => 'number',
                        'format' => 'integer',
                        'description' => 'Total count of custom items',
                    ),
                    'average_custom_value' => array(
                        'label' => 'Average Custom Value',
                        'type' => 'currency',
                        'format' => 'currency',
                        'description' => 'Average value per item',
                    ),
                    'custom_percentage' => array(
                        'label' => 'Custom Percentage',
                        'type' => 'percentage',
                        'format' => 'percentage',
                        'description' => 'Custom percentage value',
                    ),
                ),
            ),

            /**
             * DATA BY DATE MAPPING (Time Series)
             * 
             * Maps the metrics in your fetch_data() 'data_by_date' array.
             * Structure: { metric_key: { label, type, format, description, chart_calculation } }
             * 
             * This will be merged into DATA_BY_DATE_MAP[entity_name] in React.
             * Each metric_key must match a key in your fetch_data() 'data_by_date' array.
             * 
             * NOTE: This is a flat structure - metrics are directly under the entity, not nested.
             */
            'data_by_date' => array(
                // Metric key MUST match the key in your fetch_data() 'data_by_date' array
                'total_custom_metric_by_date' => array(
                    'label' => 'Custom Metric Over Time',        // Display name in chart legends
                    'type' => 'currency',                        // Data type
                    'format' => 'currency',                      // Format for display
                    'description' => 'Custom metric value over time', // Tooltip/help text
                    'chart_calculation' => 'sum',                // How to aggregate: 'sum', 'average', 'max'
                ),
                'total_custom_count_by_date' => array(
                    'label' => 'Custom Count Over Time',
                    'type' => 'integer',
                    'format' => 'integer',
                    'description' => 'Custom count over time',
                    'chart_calculation' => 'sum',                // 'sum' for counts, 'average' for averages
                ),
            ),

            /**
             * CATEGORIZED DATA MAPPING (Pie/Doughnut Charts typically, but can be used in multiple widget types such as bar charts, line charts, tables etc.)
             * 
             * Maps the categories in your fetch_data() 'categorized_data' array.
             * Structure: { metric_key: { label, description, color, icon, metric_fields: [...] } }
             * 
             * This will be merged into CATEGORIZED_DATA_MAP[entity_name] in React.
             * 
             * The metric_key (e.g., 'custom_categories') is used in widget configs like:
             * "example_custom_data.categorized_data.custom_categories"
             * 
             * The metric_fields define which metrics from each category can be displayed.
             * Each field's 'value' must match a key in your categorized_data structure.
             */
            'categorized_data' => array(
                // This key is used in widget configs: entity.categorized_data.custom_categories
                'custom_categories' => array(
                    'label' => 'Custom Data by Category',         // Display name in widget selectors
                    'description' => 'Custom data broken down by categories (A, B, C)', // Help text
                    'color' => '#138fdd',                         // Default color for charts
                    'icon' => 'category',                         // Icon for this categorized metric
                    // Define which metrics are available for each category
                    // Each 'value' must match a key in your categorized_data[category] arrays
                    'metric_fields' => array(
                        array(
                            'label' => 'Total Custom Metric',     // Display name
                            'value' => 'total_custom_metric',      // MUST match key in categorized_data[category]
                            'type' => 'currency',                  // Data type
                        ),
                        array(
                            'label' => 'Total Custom Count',
                            'value' => 'total_custom_count',      // MUST match key in categorized_data[category]
                            'type' => 'number',
                        ),
                    ),
                ),
            ),

            /**
             * DATA TABLE MAPPING
             * 
             * Maps the tables in your fetch_data() 'data_table' array.
             * Structure: { table_key: { label, description, icon, columns: {...} } }
             * 
             * This will be merged into DATA_TABLE_METRICS[entity_name] in React.
             * The table_key must match a key in your fetch_data() 'data_table' array.
             * 
             * Columns define the structure of your table data for display in data table widgets.
             */
            'data_table' => array(
                // Table key MUST match the key in your fetch_data() 'data_table' array
                'example_items' => array(
                    'label' => 'Example Items',                   // Display name in table selector
                    'description' => 'Table of example custom items', // Help text
                    'icon' => 'table_chart',                      // Icon for this table
                    // Define columns that exist in your table data
                    // Each column key should match a field in your table rows in your data_table array
                    'columns' => array(
                        'id' => array(
                            'label' => 'ID',                      // Column header
                            'type' => 'number',                   // Data type
                            'format' => 'integer',                // Display format
                        ),
                        'name' => array(
                            'label' => 'Name',
                            'type' => 'text',
                        ),
                        'value' => array(
                            'label' => 'Value',
                            'type' => 'currency',
                            'format' => 'currency',
                        ),
                        'category' => array(
                            'label' => 'Category',
                            'type' => 'text',
                        ),
                        'date' => array(
                            'label' => 'Date',
                            'type' => 'date',
                        ),
                    ),
                ),
            ),
        );
    }
}

/**
 * Initialize the example custom data source
 *
 * This will automatically register the data source when the class is instantiated.
 * Remove or comment out this line if you don't want to use this example.
 *
 * @since 5.0.0
 */
new WPD_AI_Example_Custom_Data_Source();