<?php
/**
 * Abstract Base Class for Custom Data Sources
 *
 * This abstract class provides the registration boilerplate for custom data sources.
 * Developers should extend this class and set the entity_names property (array).
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
 * only need to extend this class and set the entity_names property (array).
 * Single entity: array( 'my_entity' ). Multi-entity: array( 'orders', 'products', 'customers' ).
 *
 * @since 5.0.0
 */
abstract class WPDAI_Custom_Data_Source_Base implements WPDAI_Custom_Data_Source_Interface {

    /**
     * Entity names this data source provides
     *
     * Set this property in your child class. One-to-one: array( 'my_entity' ).
     * One-to-many: array( 'orders', 'products', 'customers' ). Used for
     * registration and as the source of truth for what this source returns.
     *
     * @since 5.0.0
     *
     * @var array<string>
     */
    protected $entity_names = array();

    /**
     * Constructor
     *
     * Automatically registers this custom data source with Alpha Insights.
     * DO NOT override this constructor. Set the $entity_names property instead.
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
     * Get the entity name for this data source (primary/first entity)
     *
     * Returns the first entity from the $entity_names property.
     * DO NOT override this method. Set the $entity_names property instead.
     *
     * @since 5.0.0
     *
     * @return string The entity name
     */
    public function get_entity_name() {
        $names = $this->get_entity_names();
        return isset( $names[0] ) ? $names[0] : '';
    }

    /**
     * Get all entity names this data source provides
     *
     * Returns the $entity_names property. Single entity = one entry; multi-entity = multiple.
     *
     * @since 5.0.0
     *
     * @return array<string> List of entity names this source can provide
     */
    public function get_entity_names() {
        return is_array( $this->entity_names ) ? $this->entity_names : array();
    }

    /**
     * Fetch data for this custom data source
     *
     * Get filters from $data_warehouse->get_filter(). Return format should match get_entity_names().
     *
     * @since 5.0.0
     *
     * @param WPDAI_Data_Warehouse $data_warehouse The data warehouse instance (required).
     * @return array Data structure - see interface documentation for details
     */
    abstract public function fetch_data( WPDAI_Data_Warehouse $data_warehouse );

    /**
     * Get the data mapping configuration for React (optional)
     *
     * Default: returns empty array (no custom mapping). Override to provide
     * mapping for the frontend. Single-entity: return one mapping structure;
     * multi-entity: return array keyed by entity name.
     *
     * @since 5.0.0
     *
     * @return array Data mapping structure, or empty array for no mapping
     */
    public function get_data_mapping() {
        return array();
    }
}