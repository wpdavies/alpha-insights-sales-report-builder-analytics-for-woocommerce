<?php
/**
 * REST API Handler for Alpha Insights Reports
 *
 * @package Alpha Insights
 * @since 4.7.0
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

class WPD_Report_API {

    /**
     * Register REST API routes
     */
    public static function register_routes() {
        add_action('rest_api_init', function () {
            register_rest_route('alpha-insights/v1', '/dashboard-data', array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'get_dashboard_data'),
                'permission_callback' => array(__CLASS__, 'check_permissions'),
                'args' => array(
                    'config' => array(
                        'required' => false,
                        'type' => 'object',
                        'default' => array()
                    ),
                    'filters' => array(
                        'required' => false,
                        'type' => 'object',
                        'default' => array()
                    )
                )
            ));

            register_rest_route('alpha-insights/v1', '/realtime-data', array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'get_realtime_data'),
                'permission_callback' => array(__CLASS__, 'check_permissions'),
                'args' => array()
            ));
        });
    }

    /**
     * Check if user has permission to access the API
     */
    public static function check_permissions() {
        // Check if user is logged in and has appropriate capabilities
        if (!current_user_can('manage_woocommerce')) {
            return new WP_Error(
                'rest_forbidden',
                __('Sorry, you are not allowed to access this endpoint.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce'),
                array('status' => 403)
            );
        }

        return true;
    }

    /**
     * Get dashboard data via REST API
     */
    public static function get_dashboard_data($request) {
        try {
            // Get parameters from request -> live reports
            $config = $request->get_param('config');

            // Use the existing method from WPD_React_Report to get data
            $response = WPD_React_Report::get_live_dashboard_data_from_config($config);

            // Return REST API response
            if ($response['success']) {
                return new WP_REST_Response($response, 200);
            } else {
                return new WP_Error(
                    'data_fetch_error',
                    $response['error'] ?? 'Failed to fetch dashboard data',
                    array('status' => 500)
                );
            }

        } catch (Exception $e) {
            // Log the error
            WPD_React_Report::log_error('WPD_Report_API: Error in get_dashboard_data: ' . $e->getMessage());
            
            return new WP_Error(
                'server_error',
                'An error occurred while fetching dashboard data',
                array('status' => 500)
            );
        }
    }

    /**
     * Get realtime dashboard data via REST API
     */
    public static function get_realtime_data($request) {
        try {
            // Use the existing method from WPD_React_Report to get realtime data
            $response = WPD_React_Report::get_realtime_dashboard_data();

            // Return REST API response
            if ($response['success']) {
                return new WP_REST_Response($response, 200);
            } else {
                return new WP_Error(
                    'realtime_data_fetch_error',
                    $response['error'] ?? 'Failed to fetch realtime data',
                    array('status' => 500)
                );
            }

        } catch (Exception $e) {
            // Log the error
            WPD_React_Report::log_error('WPD_Report_API: Error in get_realtime_data: ' . $e->getMessage());
            
            return new WP_Error(
                'server_error',
                'An error occurred while fetching realtime data',
                array('status' => 500)
            );
        }
    }

    /**
     * Get the REST API base URL
     */
    public static function get_api_base_url() {
        return rest_url('alpha-insights/v1/');
    }

    /**
     * Get authentication headers for REST API requests
     */
    public static function get_auth_headers() {
        return array(
            'X-WP-Nonce' => wp_create_nonce('wp_rest'),
            'Content-Type' => 'application/json'
        );
    }
}
