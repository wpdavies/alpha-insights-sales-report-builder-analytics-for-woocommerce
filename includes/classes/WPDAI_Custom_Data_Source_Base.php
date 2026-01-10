<?php
/**
 * Abstract Base Class for Custom Data Sources
 *
 * This abstract class provides the registration boilerplate for custom data sources.
 * Developers should extend this class and only need to set the entity_name property.
 *
 * @package Alpha Insights
 * @since 5.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * Abstract base class for custom data sources
 *
 * This class handles the registration boilerplate automatically. Developers
 * only need to extend this class and set the entity_name property.
 *
 * @since 5.0.0
 */
abstract class WPDAI_Custom_Data_Source_Base implements WPDAI_Custom_Data_Source_Interface {

    /**
     * The entity name for this data source
     *
     * Set this property in your child class. This is a unique identifier
     * (e.g., 'custom_inventory', 'third_party_analytics') that will be used
     * to reference this data source in widgets and reports.
     *
     * @since 5.0.0
     *
     * @var string
     */
    protected $entity_name;

    /**
     * Constructor
     *
     * Automatically registers this custom data source with Alpha Insights.
     * DO NOT override this constructor. Set the $entity_name property instead.
     *
     * @since 5.0.0
     */
    public function __construct() {
        add_filter( 'wpd_alpha_insights_register_data_sources', array( $this, 'register_data_source' ) );
    }

    /**
     * Register this data source
     *
     * This method is called automatically by WordPress filter system.
     * DO NOT override this method or call it directly.
     *
     * @since 5.0.0
     *
     * @param array $sources Array of registered data sources
     * @return array Updated array with this source added
     */
    public function register_data_source( $sources ) {
        $sources[] = $this;
        return $sources;
    }

    /**
     * Get the entity name for this data source
     *
     * Returns the entity name from the $entity_name property.
     * DO NOT override this method. Set the $entity_name property instead.
     *
     * @since 5.0.0
     *
     * @return string The entity name
     */
    public function get_entity_name() {
        return $this->entity_name;
    }

    /**
     * Fetch data for this custom data source
     *
     * This method must be implemented by child classes to fetch and return data
     * following the same structure as the data warehouse.
     *
     * @since 5.0.0
     *
     * @param array $filters Array of filters - see interface documentation for details
     * @param WPDAI_Data_Warehouse|null $data_warehouse Optional. The data warehouse instance.
     * @return array Data structure - see interface documentation for details
     */
    abstract public function fetch_data( $filters, $data_warehouse = null );

    /**
     * Get the data mapping configuration
     *
     * This method must be implemented by child classes to return a PHP array
     * structure that defines how React should display and format the data.
     *
     * @since 5.0.0
     *
     * @return array Data mapping structure - see interface documentation for details
     */
    abstract public function get_data_mapping();
}