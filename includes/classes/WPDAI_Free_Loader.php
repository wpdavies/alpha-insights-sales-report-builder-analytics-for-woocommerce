<?php
/**
 *
 * Handles appropriate notices for the free version
 *
 * @package Alpha Insights
 * @since 1.0.0
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

class WPDAI_Free_Loader {

    /**
     * Instance of this class
     *
     * @var WPDAI_Free_Loader
     */
    private static $instance = null;

    /**
     * Get the singleton instance of this class
     *
     * @return WPDAI_Free_Loader
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Prevent cloning of the instance
     *
     * @return void
     */
    private function __clone() {
        // Prevent cloning
    }

    /**
     * Prevent unserialization of the instance
     *
     * @return void
     */
    public function __wakeup() {
        // Prevent unserialization
    }

    /**
     * 
     *  Initiate class
     * 
     **/
    private function __construct() {

        add_filter('wpd_alpha_insights_menu_items', array($this, 'filter_wpd_alpha_insights_menu_items'));

    }

    /**
     * Filter the Alpha Insights menu items
     * 
     * @param array $menu_items The menu items
     * @return array The filtered menu items
     */
    public function filter_wpd_alpha_insights_menu_items($menu_items) {
        
        // Class added to display the pro menu item in a different style
        $pro_menu_item_class = 'wpd-pro-menu-item';

        // Remove the license manager menu item
        if (isset($menu_items['wpd-settings']['children']['license_manager'])) unset($menu_items['wpd-settings']['children']['license_manager']);
        if (isset($menu_items['wpd-settings']['children']['facebook_settings'])) $menu_items['wpd-settings']['children']['facebook_settings']['additional_classes'][] = $pro_menu_item_class;
        if (isset($menu_items['wpd-settings']['children']['google_ads_settings'])) $menu_items['wpd-settings']['children']['google_ads_settings']['additional_classes'][] = $pro_menu_item_class;

        // Pro icons for expense management
        if ( isset($menu_items['wpd-expense-management']) ) {
            $menu_items['wpd-expense-management']['additional_classes'][] = $pro_menu_item_class;
            $children = $menu_items['wpd-expense-management']['children'];
            foreach ( $children as $child_key => $child ) {
                $children[$child_key]['additional_classes'][] = $pro_menu_item_class;
            }
            $menu_items['wpd-expense-management']['children'] = $children;
        }
        // Pro icons for advertising features
        if ( isset($menu_items['wpd-advertising']) ) {
            $menu_items['wpd-advertising']['additional_classes'][] = $pro_menu_item_class;
            $children = $menu_items['wpd-advertising']['children'];
            foreach ( $children as $child_key => $child ) {
                $children[$child_key]['additional_classes'][] = $pro_menu_item_class;
            }
            $menu_items['wpd-advertising']['children'] = $children;
        }

        // Add new menu item for Upgrade to Pro
        $menu_items['wpd-upgrade-to-pro'] = array(
            'title' => __('Upgrade to Pro', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
            'url' => wpdai_wpdavies_url( '/plugins/alpha-insights/pricing/', 'Alpha Insights Upgrade to Pro Menu Item' ),
            'target' => '_blank',
            'icon' => 'dashicons-star-filled',
            'position' => 100,
            'additional_classes' => array('wpd-trigger-upgrade-modal'),
        );

        // Return filtered results
        return $menu_items;
    }

}

// Initialize the class
WPDAI_Free_Loader::get_instance();