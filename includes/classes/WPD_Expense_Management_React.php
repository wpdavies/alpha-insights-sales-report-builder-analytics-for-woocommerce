<?php
/**
 *
 * React Expense Management Handler for Alpha Insights
 *
 * @package Alpha Insights
 * @since 5.0.0
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

class WPD_Expense_Management_React {

    /**
     * Constructor for WPD_Expense_Management_React class
     *
     * @since 5.0.0
     */
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Output the complete expense management dashboard
     *
     * @since 5.0.0
     *
     * @return void
     */
	public function output_expense_management() {
		// Render the React expense management app
		$this->render_expense_management();
	}

    /**
     * Render React expense management app
     *
     * @since 5.0.0
     *
     * @return void
     */
    public function render_expense_management() {
        // Ensure scripts are enqueued
        $this->enqueue_scripts('wpd-expense-management');
        
        // Output the React container
        echo '<div id="wpd-alpha-expense-management"></div>';
        echo '<!-- React Expense Management Container Created -->';
    }

    /**
     * Enqueue React expense management scripts and styles
     *
     * @since 5.0.0
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_scripts($hook) {
        // Enqueue React expense management script
        wp_enqueue_script(
            'wpd-react-expense-management',
            WPD_AI_URL_PATH . 'assets/js/react-expense-management/dist/expense-management.js',
            array('jquery'),
            WPD_AI_VER,
            true
        );

        // Localize script with WordPress data
        wp_localize_script('wpd-react-expense-management', 'wpd_expense_management', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('alpha-insights/v1/'),
            'nonce' => wp_create_nonce(WPD_AI_AJAX_NONCE_ACTION),
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'currency_format_num_decimals' => wc_get_price_decimals(),
            'currency_format_symbol'       => html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8'),
            'currency_format_decimal_sep'  => wc_get_price_decimal_separator(),
            'currency_format_thousand_sep' => wc_get_price_thousand_separator(),
            'currency_format'              => get_woocommerce_price_format(),
            'site_name'                    => get_bloginfo( 'name' ),
            'default_currency'             => get_woocommerce_currency(),
            'currency_list'                => wpd_get_woocommerce_currency_list(),
            'current_date'                 => current_time('Y-m-d'),
            'is_pro'                       => WPD_AI_PRO && wpd_get_license_key(),
            'license_key'                  => wpd_get_license_key(),
        ));
    }

    /**
     * Register AJAX actions early in WordPress lifecycle
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function register_ajax_actions() {
        add_action('wp_ajax_wpd_get_expense_overview_data', [__CLASS__, 'get_expense_overview_data_ajax_handler']);
        add_action('wp_ajax_wpd_get_recent_expenses', [__CLASS__, 'get_recent_expenses_ajax_handler']);
        add_action('wp_ajax_wpd_get_recurring_expenses', [__CLASS__, 'get_recurring_expenses_ajax_handler']);
        add_action('wp_ajax_wpd_get_expense_taxonomies', [__CLASS__, 'get_expense_taxonomies_ajax_handler']);
        add_action('wp_ajax_wpd_create_expense', [__CLASS__, 'create_expense_ajax_handler']);
        add_action('wp_ajax_wpd_bulk_create_expenses', [__CLASS__, 'bulk_create_expenses_ajax_handler']);
        add_action('wp_ajax_wpd_update_expense', [__CLASS__, 'update_expense_ajax_handler']);
        add_action('wp_ajax_wpd_delete_expense', [__CLASS__, 'delete_expense_ajax_handler']);
        add_action('wp_ajax_wpd_create_expense_category', [__CLASS__, 'create_expense_category_ajax_handler']);
        add_action('wp_ajax_wpd_create_supplier', [__CLASS__, 'create_supplier_ajax_handler']);
        add_action('wp_ajax_wpd_get_all_expenses', [__CLASS__, 'get_all_expenses_ajax_handler']);
        add_action('wp_ajax_wpd_bulk_delete_expenses', [__CLASS__, 'bulk_delete_expenses_ajax_handler']);
        add_action('wp_ajax_wpd_bulk_update_expenses', [__CLASS__, 'bulk_update_expenses_ajax_handler']);
        add_action('wp_ajax_wpd_update_taxonomy', [__CLASS__, 'update_taxonomy_ajax_handler']);
        add_action('wp_ajax_wpd_delete_taxonomy', [__CLASS__, 'delete_taxonomy_ajax_handler']);
        add_action('wp_ajax_wpd_bulk_delete_taxonomies', [__CLASS__, 'bulk_delete_taxonomies_ajax_handler']);
        add_action('wp_ajax_wpd_get_expense_by_id', [__CLASS__, 'get_expense_by_id_ajax_handler']);
        add_action('wp_ajax_wpd_upload_attachment', [__CLASS__, 'upload_attachment_ajax_handler']);
        add_action('wp_ajax_wpd_export_all_expenses', [__CLASS__, 'export_all_expenses_ajax_handler']);
        add_action('wp_ajax_wpd_export_filtered_expenses', [__CLASS__, 'export_filtered_expenses_ajax_handler']);
        add_action('wp_ajax_wpd_bulk_assign_taxonomy', [__CLASS__, 'bulk_assign_taxonomy_ajax_handler']);
    }

    /**
     * Get expense overview data (stats with comparison) AND expenses list
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function get_expense_overview_data_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            // Get date range from request (default: last 30 days)
            $days_ago = isset($_POST['days_ago']) ? intval($_POST['days_ago']) : 30;
            
            $to_date = current_time('Y-m-d');
            // For "last N days" we need -(N-1) to include today (e.g., last 30 days = today + 29 previous)
            $from_date = gmdate('Y-m-d', strtotime('-' . ($days_ago - 1) . ' days'));
            
            // Comparison period
            $comparison_to_date = gmdate('Y-m-d', strtotime('-' . $days_ago . ' days'));
            $comparison_from_date = gmdate('Y-m-d', strtotime('-' . (($days_ago * 2) - 1) . ' days'));

            // Get current period data
            $filter_current = array(
                'date_from' => $from_date,
                'date_to' => $to_date,
                'date_format_display' => 'day',
                'data_filters' => array(
                    'expenses' => array(
                        'paid_unpaid' => array('paid')
                    )
                )
            );
            
            $data_warehouse_current = new WPD_Data_Warehouse_React($filter_current);
            $data_warehouse_current->fetch_store_profit_data();
            
            $expense_data_current = $data_warehouse_current->get_data('expenses');
            $order_data_current = $data_warehouse_current->get_data('orders');
            $store_profit_data_current = $data_warehouse_current->get_data('store_profit');

            // Get comparison period data
            $filter_comparison = array(
                'date_from' => $comparison_from_date,
                'date_to' => $comparison_to_date,
                'date_format_display' => 'day',
                'data_filters' => array(
                    'expenses' => array(
                        'paid_unpaid' => array('paid')
                    )
                )
            );
            
            $data_warehouse_comparison = new WPD_Data_Warehouse_React($filter_comparison);
            $data_warehouse_comparison->fetch_store_profit_data();
            
            $expense_data_comparison = $data_warehouse_comparison->get_data('expenses');
            $order_data_comparison = $data_warehouse_comparison->get_data('orders');
            $store_profit_data_comparison = $data_warehouse_comparison->get_data('store_profit');

            // Calculate stats for paid expenses
            $total_expenses_current = $expense_data_current['totals']['total_amount_paid'] ?? 0;
            $total_expenses_comparison = $expense_data_comparison['totals']['total_amount_paid'] ?? 0;
                        
            $total_revenue_current = $order_data_current['totals']['total_order_revenue_ex_tax'] ?? 0;
            $total_revenue_comparison = $order_data_comparison['totals']['total_order_revenue_ex_tax'] ?? 0;
            
            $net_profit_current = $store_profit_data_current['totals']['total_store_profit'] ?? 0;
            $net_profit_comparison = $store_profit_data_comparison['totals']['total_store_profit'] ?? 0;

            if ( ! WPD_AI_PRO ) {
                $total_expenses_current = 0;
                $total_expenses_comparison = 0;
                $total_revenue_current = 0;
                $total_revenue_comparison = 0;
                $net_profit_current = 0;
                $net_profit_comparison = 0;
            }
            
            // Calculate percentage changes
            $expenses_change = self::calculate_percentage_change($total_expenses_comparison, $total_expenses_current);
            $revenue_change = self::calculate_percentage_change($total_revenue_comparison, $total_revenue_current);
            $profit_change = self::calculate_percentage_change($net_profit_comparison, $net_profit_current);

            // Get PAID expenses from the date range (from data warehouse)
            $paid_expenses_table = $expense_data_current['data_table']['expenses'] ?? array();
            
            // Add edit links to paid expenses
            foreach ($paid_expenses_table as &$expense) {
                $expense['edit_link'] = get_edit_post_link($expense['post_id']);
            }
            
            // Get UNPAID expenses (all time - separate query)
            $unpaid_filter = array(
                'date_from' => '2000-01-01',
                'date_to' => current_time('Y-m-d'),
                'date_format_display' => 'day',
                'data_filters' => array(
                    'expenses' => array(
                        'paid_unpaid' => array('unpaid')
                    )
                )
            );
            
            $data_warehouse_unpaid = new WPD_Data_Warehouse_React($unpaid_filter);
            $data_warehouse_unpaid->fetch_expense_data();
            
            $unpaid_expense_data = $data_warehouse_unpaid->get_data('expenses');
            $unpaid_expenses_table = $unpaid_expense_data['data_table']['expenses'] ?? array();

            $unpaid_total_current = $unpaid_expense_data['totals']['total_amount_unpaid'] ?? 0;
            $unpaid_change = 0; // No comparison for unpaid expenses
            
            // Add edit links to paid expenses
            foreach ($paid_expenses_table as &$expense) {
                $expense['edit_link'] = get_edit_post_link($expense['post_id']);
            }

            // Sort expenses by date (most recent first)
            usort($paid_expenses_table, function($a, $b) {
                $date_a = isset($a['date_paid_unix']) ? $a['date_paid_unix'] : 0;
                $date_b = isset($b['date_paid_unix']) ? $b['date_paid_unix'] : 0;
                return $date_b - $date_a; // DESC order
            });

            usort($unpaid_expenses_table, function($a, $b) {
                $date_a = isset($a['date_paid_unix']) ? $a['date_paid_unix'] : 0;
                $date_b = isset($b['date_paid_unix']) ? $b['date_paid_unix'] : 0;
                return $date_b - $date_a; // DESC order
            });

            wp_send_json_success(array(
                'metrics' => array(
                    'total_expenses' => array(
                        'current' => $total_expenses_current,
                        'previous' => $total_expenses_comparison,
                        'change_percent' => $expenses_change,
                    ),
                    'unpaid_expenses' => array(
                        'current' => $unpaid_total_current,
                        'previous' => $unpaid_total_current,
                        'change_percent' => $unpaid_change,
                    ),
                    'total_revenue' => array(
                        'current' => $total_revenue_current,
                        'previous' => $total_revenue_comparison,
                        'change_percent' => $revenue_change,
                    ),
                    'net_profit' => array(
                        'current' => $net_profit_current,
                        'previous' => $net_profit_comparison,
                        'change_percent' => $profit_change,
                    ),
                ),
                'expenses' => array(
                    'paid' => array_values($paid_expenses_table),
                    'unpaid' => array_values($unpaid_expenses_table),
                ),
                'period_days' => $days_ago,
                'date_range' => array(
                    'from' => $from_date,
                    'to' => $to_date,
                ),
                'comparison_date_range' => array(
                    'from' => $comparison_from_date,
                    'to' => $comparison_to_date,
                ),
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to fetch expense overview data: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Get recent expenses
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function get_recent_expenses_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            $days_ago = isset($_POST['days_ago']) ? intval($_POST['days_ago']) : 30;
            $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 100;
            $status = isset($_POST['status']) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'paid';
            
            // Set up filter based on status
            if ($status === 'unpaid') {
                // For unpaid, query all time (no date limit)
                // Use a very old start date to get all expenses
                $filter = array(
                    'date_from' => '2000-01-01',
                    'date_to' => current_time('Y-m-d'),
                    'date_format_display' => 'day',
                );
            } else {
                // For paid expenses, use date range
                $to_date = current_time('Y-m-d');
                // For "last N days" we need -(N-1) to include today
                $from_date = gmdate('Y-m-d', strtotime('-' . ($days_ago - 1) . ' days'));
                
                $filter = array(
                    'date_from' => $from_date,
                    'date_to' => $to_date,
                    'date_format_display' => 'day',
                );
            }
            
            // Use data warehouse to fetch expense data
            $data_warehouse = new WPD_Data_Warehouse_React($filter);
            $data_warehouse->fetch_expense_data();
            
            $expense_data = $data_warehouse->get_data('expenses');
            $data_table = $expense_data['data_table']['expenses'] ?? array();
            
            // Filter by paid status
            $filtered_expenses = array();
            foreach ($data_table as $expense) {
                $is_paid = isset($expense['is_paid']) ? $expense['is_paid'] : true; // Default to paid
                
                if ($status === 'unpaid' && !$is_paid) {
                    $filtered_expenses[] = $expense;
                } elseif ($status === 'paid' && $is_paid) {
                    $filtered_expenses[] = $expense;
                }
            }
            
            // Limit results
            $filtered_expenses = array_slice($filtered_expenses, 0, $limit);
            
            // Add edit links
            foreach ($filtered_expenses as &$expense) {
                $expense['edit_link'] = get_edit_post_link($expense['post_id']);
            }

            wp_send_json_success(array(
                'expenses' => array_values($filtered_expenses),
                'total_count' => count($filtered_expenses),
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to fetch recent expenses: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Get recurring expenses
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function get_recurring_expenses_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            // Query for active recurring expenses
            $args = array(
                'post_type' => 'expense',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'meta_value',
                'meta_key' => '_wpd_recurring_expense_beginning_date',
                'order' => 'DESC',
                'meta_query' => array(
                    array(
                        'key' => '_wpd_recurring_expense_enabled',
                        'value' => '1',
                        'compare' => '=',
                    ),
                ),
            );
            
            // For non-pro users, force an empty result
            if ( ! WPD_AI_PRO ) {
                $args['post__in'] = array(0); // no post has ID 0
            }

            $recurring_expenses = new WP_Query($args);
            $expenses_list = array();
            $store_currency = get_woocommerce_currency();


            
            while ($recurring_expenses->have_posts()) {
                $recurring_expenses->the_post();
                $post_id = get_the_ID();
                
                $amount = floatval(get_post_meta($post_id, '_wpd_amount_paid', true));
                $currency = get_post_meta($post_id, '_wpd_amount_paid_currency', true);
                $frequency = get_post_meta($post_id, '_wpd_recurring_expense_frequency', true);
                $start_date = get_post_meta($post_id, '_wpd_recurring_expense_beginning_date', true);
                $end_date = get_post_meta($post_id, '_wpd_recurring_expense_end_date', true);
                
                // Currency conversion
                if ($currency && $currency !== $store_currency) {
                    $converted_amount = wpd_convert_currency($currency, $store_currency, $amount);
                } else {
                    $converted_amount = $amount;
                }

                // Get total amount paid to date using our new static function
                $total_data = self::get_recurring_expense_total($post_id, null, null, $store_currency);
                
                $expenses_list[] = array(
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'amount' => $amount,
                    'amount_converted' => $converted_amount,
                    'currency' => $currency,
                    'converted_to_currency' => $store_currency,
                    'frequency' => $frequency,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'reference' => get_post_meta($post_id, '_wpd_expense_reference', true),
                    'edit_link' => get_edit_post_link($post_id),
                    'total_paid_to_date' => $total_data['total'],
                    'occurrence_count' => $total_data['count'],
                );
            }
            
            wp_reset_postdata();

            wp_send_json_success(array(
                'recurring_expenses' => $expenses_list,
                'total_count' => count($expenses_list),
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to fetch recurring expenses: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Get expense taxonomies (categories and suppliers)
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function get_expense_taxonomies_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            // Get expense categories
            $expense_categories = array();
            if ( WPD_AI_PRO ) {
                $expense_categories = get_terms(array(
                    'taxonomy' => 'expense_category',
                    'hide_empty' => false,
                ));
            }
            
            $categories_list = array();
            foreach ($expense_categories as $term) {
                $categories_list[] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'count' => $term->count,
                    'parent' => $term->parent,
                );
            }

            // Get suppliers
            $suppliers = array();
            if ( WPD_AI_PRO ) {
                $suppliers = get_terms(array(
                    'taxonomy' => 'suppliers',
                    'hide_empty' => false,
                ));
            }
            
            $suppliers_list = array();
            foreach ($suppliers as $term) {
                $suppliers_list[] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'count' => $term->count,
                );
            }

            wp_send_json_success(array(
                'expense_categories' => $categories_list,
                'suppliers' => $suppliers_list,
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to fetch taxonomies: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Create a new expense
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function create_expense_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            $title = isset($_POST['title']) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
            $amount = isset($_POST['amount']) ? floatval( wp_unslash( $_POST['amount'] ) ) : 0;
            $currency = isset($_POST['currency']) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : get_woocommerce_currency();
            $date_paid = isset($_POST['date']) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : (isset($_POST['date_paid']) ? sanitize_text_field( wp_unslash( $_POST['date_paid'] ) ) : current_time('Y-m-d'));
            $reference = isset($_POST['reference']) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';
            $attachment_id = isset($_POST['attachment_id']) ? intval( wp_unslash( $_POST['attachment_id'] ) ) : 0;
            $expense_category = isset($_POST['expense_category']) ? intval( wp_unslash( $_POST['expense_category'] ) ) : 0;
            $supplier = isset($_POST['supplier']) ? intval( wp_unslash( $_POST['supplier'] ) ) : 0;
            $paid = isset($_POST['paid']) ? filter_var( wp_unslash( $_POST['paid'] ), FILTER_VALIDATE_BOOLEAN) : true;
            $recurring = isset($_POST['recurring']) ? filter_var( wp_unslash( $_POST['recurring'] ), FILTER_VALIDATE_BOOLEAN) : false;
            $recurring_frequency = isset($_POST['recurring_frequency']) ? sanitize_text_field( wp_unslash( $_POST['recurring_frequency'] ) ) : '';
            $recurring_start_date = isset($_POST['recurring_start_date']) ? sanitize_text_field( wp_unslash( $_POST['recurring_start_date'] ) ) : '';
            $recurring_end_date = isset($_POST['recurring_end_date']) ? sanitize_text_field( wp_unslash( $_POST['recurring_end_date'] ) ) : '';

            if ( ! WPD_AI_PRO ) {
                wp_send_json_error(array('message' => 'This feature is only available in the Pro version.'));
                return;
            }

            if (empty($title) || $amount <= 0) {
                wp_send_json_error(array('message' => 'Title and amount are required.'));
                return;
            }

            // Create expense post
            $post_id = wp_insert_post(array(
                'post_title' => $title,
                'post_type' => 'expense',
                'post_status' => 'publish',
            ));

            if (is_wp_error($post_id)) {
                wp_send_json_error(array('message' => 'Failed to create expense.'));
                return;
            }

            // Save meta data
            update_post_meta($post_id, '_wpd_paid', $paid ? '1' : '0');
            update_post_meta($post_id, '_wpd_amount_paid', $amount);
            update_post_meta($post_id, '_wpd_amount_paid_currency', $currency);
            update_post_meta($post_id, '_wpd_date_paid', $date_paid);
            update_post_meta($post_id, '_wpd_expense_reference', $reference);

            // Recurring expense fields
            if ($recurring) {
                update_post_meta($post_id, '_wpd_recurring_expense_enabled', '1');
                update_post_meta($post_id, '_wpd_recurring_expense_frequency', $recurring_frequency);
                update_post_meta($post_id, '_wpd_recurring_expense_beginning_date', $recurring_start_date ?: $date_paid);
                if (!empty($recurring_end_date)) {
                    update_post_meta($post_id, '_wpd_recurring_expense_end_date', $recurring_end_date);
                }
            } else {
                update_post_meta($post_id, '_wpd_recurring_expense_enabled', '0');
                delete_post_meta($post_id, '_wpd_recurring_expense_frequency');
                delete_post_meta($post_id, '_wpd_recurring_expense_beginning_date');
                delete_post_meta($post_id, '_wpd_recurring_expense_end_date');
            }

            // Attachment
            if ($attachment_id > 0) {
                update_post_meta($post_id, '_wpd_expense_attachment', $attachment_id);
            }

            // Set taxonomies
            if ($expense_category > 0) {
                wp_set_post_terms($post_id, array($expense_category), 'expense_category');
            }
            if ($supplier > 0) {
                wp_set_post_terms($post_id, array($supplier), 'suppliers');
            }

            wp_send_json_success(array(
                'message' => 'Expense created successfully.',
                'expense_id' => $post_id,
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to create expense: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Bulk create expenses
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function bulk_create_expenses_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            // Decode JSON if it's a string (sent from FormData)
            $expenses_raw = isset($_POST['expenses']) ? wp_unslash( $_POST['expenses'] ) : array();
            if ( is_string( $expenses_raw ) ) {
                // Sanitize the JSON string before decoding
                $expenses_raw = sanitize_textarea_field( $expenses_raw );
                $expenses = json_decode( $expenses_raw, true );
                // Sanitize decoded JSON array according to WordPress standards
                if ( is_array( $expenses ) ) {
                    $expenses = wpd_sanitize_json_decoded_array( $expenses );
                }
            } else {
                $expenses = $expenses_raw;
            }
            
            if ( ! WPD_AI_PRO ) {
                wp_send_json_error(array('message' => 'This feature is only available in the Pro version.'));
                return;
            }

            if (empty($expenses) || !is_array($expenses)) {
                wp_send_json_error(array('message' => 'No expenses provided.'));
                return;
            }

            $created_count = 0;
            $errors = array();

            foreach ($expenses as $expense) {
                $title = isset($expense['title']) ? sanitize_text_field($expense['title']) : '';
                $amount = isset($expense['amount']) ? floatval($expense['amount']) : 0;
                $currency = isset($expense['currency']) ? sanitize_text_field($expense['currency']) : get_woocommerce_currency();
                $date_paid_raw = isset($expense['date_paid']) ? sanitize_text_field($expense['date_paid']) : current_time('Y-m-d');
                $reference = isset($expense['reference']) ? sanitize_text_field($expense['reference']) : '';
                $expense_category_input = isset($expense['expense_category']) ? sanitize_text_field($expense['expense_category']) : '';
                $supplier_input = isset($expense['supplier']) ? sanitize_text_field($expense['supplier']) : '';

                // Validate and format date
                $date_paid = $date_paid_raw;
                if (!empty($date_paid_raw)) {
                    // Try to parse the date
                    $timestamp = strtotime($date_paid_raw);
                    if ($timestamp !== false) {
                        // Convert to Y-m-d format
                        $date_paid = gmdate('Y-m-d', $timestamp);
                    } else {
                        // If date can't be parsed, use today's date
                        $date_paid = current_time('Y-m-d');
                        $errors[] = "Warning for '{$title}': Invalid date format '{$date_paid_raw}' - used today's date instead";
                    }
                } else {
                    $date_paid = current_time('Y-m-d');
                }

                if (empty($title) || $amount <= 0) {
                    $errors[] = "Skipped expense: {$title} (invalid data)";
                    continue;
                }

                // Create expense post
                $post_id = wp_insert_post(array(
                    'post_title' => $title,
                    'post_type' => 'expense',
                    'post_status' => 'publish',
                ));

                if (is_wp_error($post_id)) {
                    $errors[] = "Failed to create expense: {$title}";
                    continue;
                }

                // Save meta data
                update_post_meta($post_id, '_wpd_paid', '1');
                update_post_meta($post_id, '_wpd_amount_paid', $amount);
                update_post_meta($post_id, '_wpd_amount_paid_currency', $currency);
                update_post_meta($post_id, '_wpd_date_paid', $date_paid);
                update_post_meta($post_id, '_wpd_expense_reference', $reference);

                // Handle category - match by name or ID, create if doesn't exist
                if (!empty($expense_category_input)) {
                    // Try as ID first (if numeric)
                    if (is_numeric($expense_category_input)) {
                        $category_id = intval($expense_category_input);
                    } else {
                        // Try to find by name
                        $term = get_term_by('name', $expense_category_input, 'expense_category');
                        
                        // Create if doesn't exist
                        if (!$term) {
                            $new_term = wp_insert_term($expense_category_input, 'expense_category');
                            if (!is_wp_error($new_term)) {
                                $category_id = $new_term['term_id'];
                            } else {
                                $category_id = 0;
                            }
                        } else {
                            $category_id = $term->term_id;
                        }
                    }
                    
                    if ($category_id > 0) {
                        wp_set_post_terms($post_id, array($category_id), 'expense_category');
                    }
                }

                // Handle supplier - match by name or ID, create if doesn't exist
                if (!empty($supplier_input)) {
                    // Try as ID first (if numeric)
                    if (is_numeric($supplier_input)) {
                        $supplier_id = intval($supplier_input);
                    } else {
                        // Try to find by name
                        $term = get_term_by('name', $supplier_input, 'suppliers');
                        
                        // Create if doesn't exist
                        if (!$term) {
                            $new_term = wp_insert_term($supplier_input, 'suppliers');
                            if (!is_wp_error($new_term)) {
                                $supplier_id = $new_term['term_id'];
                            } else {
                                $supplier_id = 0;
                            }
                        } else {
                            $supplier_id = $term->term_id;
                        }
                    }
                    
                    if ($supplier_id > 0) {
                        wp_set_post_terms($post_id, array($supplier_id), 'suppliers');
                    }
                }

                $created_count++;
            }

            wp_send_json_success(array(
                'message' => "Successfully created {$created_count} expenses.",
                'created_count' => $created_count,
                'errors' => $errors,
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to bulk create expenses: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Update an existing expense
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function update_expense_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            $expense_id = isset($_POST['expense_id']) ? intval($_POST['expense_id']) : 0;
            
            if ( ! WPD_AI_PRO ) {
                wp_send_json_error(array('message' => 'This feature is only available in the Pro version.'));
                return;
            }

            if ($expense_id <= 0) {
                wp_send_json_error(array('message' => 'Invalid expense ID.'));
                return;
            }

            $title = isset($_POST['title']) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
            $amount = isset($_POST['amount']) ? floatval( wp_unslash( $_POST['amount'] ) ) : 0;
            
            // Update post
            wp_update_post(array(
                'ID' => $expense_id,
                'post_title' => $title,
            ));

            // Update meta data
            if (isset($_POST['amount'])) {
                update_post_meta($expense_id, '_wpd_amount_paid', $amount);
            }
            if (isset($_POST['currency'])) {
                update_post_meta($expense_id, '_wpd_amount_paid_currency', sanitize_text_field( wp_unslash( $_POST['currency'] ) ));
            }
            if (isset($_POST['date']) || isset($_POST['date_paid'])) {
                $date = isset($_POST['date']) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : sanitize_text_field( wp_unslash( $_POST['date_paid'] ) );
                update_post_meta($expense_id, '_wpd_date_paid', $date);
            }
            if (isset($_POST['reference'])) {
                update_post_meta($expense_id, '_wpd_expense_reference', sanitize_text_field( wp_unslash( $_POST['reference'] ) ));
            }
            if (isset($_POST['attachment_id'])) {
                $attachment_id = intval( wp_unslash( $_POST['attachment_id'] ) );
                if ($attachment_id > 0) {
                    update_post_meta($expense_id, '_wpd_expense_attachment', $attachment_id);
                } else {
                    delete_post_meta($expense_id, '_wpd_expense_attachment');
                }
            }
            if (isset($_POST['paid'])) {
                $paid = filter_var( wp_unslash( $_POST['paid'] ), FILTER_VALIDATE_BOOLEAN);
                update_post_meta($expense_id, '_wpd_paid', $paid ? '1' : '0');
            }

            // Update taxonomies
            if (isset($_POST['expense_category'])) {
                $expense_category = intval( wp_unslash( $_POST['expense_category'] ) );
                if ($expense_category > 0) {
                    wp_set_post_terms($expense_id, array($expense_category), 'expense_category');
                } else {
                    wp_set_post_terms($expense_id, array(), 'expense_category');
                }
            }
            if (isset($_POST['supplier'])) {
                $supplier = intval( wp_unslash( $_POST['supplier'] ) );
                if ($supplier > 0) {
                    wp_set_post_terms($expense_id, array($supplier), 'suppliers');
                } else {
                    wp_set_post_terms($expense_id, array(), 'suppliers');
                }
            }

            // Update recurring expense fields
            if (isset($_POST['recurring'])) {
                $recurring = filter_var( wp_unslash( $_POST['recurring'] ), FILTER_VALIDATE_BOOLEAN);
                if ($recurring) {
                    update_post_meta($expense_id, '_wpd_recurring_expense_enabled', '1');
                    if (isset($_POST['recurring_frequency'])) {
                        update_post_meta($expense_id, '_wpd_recurring_expense_frequency', sanitize_text_field( wp_unslash( $_POST['recurring_frequency'] ) ));
                    }
                    if (isset($_POST['recurring_start_date'])) {
                        update_post_meta($expense_id, '_wpd_recurring_expense_beginning_date', sanitize_text_field( wp_unslash( $_POST['recurring_start_date'] ) ));
                    }
                    if (isset($_POST['recurring_end_date'])) {
                        $recurring_end_date = sanitize_text_field( wp_unslash( $_POST['recurring_end_date'] ) );
                        if (!empty($recurring_end_date)) {
                            update_post_meta($expense_id, '_wpd_recurring_expense_end_date', $recurring_end_date);
                        } else {
                            delete_post_meta($expense_id, '_wpd_recurring_expense_end_date');
                        }
                    }
                } else {
                    update_post_meta($expense_id, '_wpd_recurring_expense_enabled', '0');
                    delete_post_meta($expense_id, '_wpd_recurring_expense_frequency');
                    delete_post_meta($expense_id, '_wpd_recurring_expense_beginning_date');
                    delete_post_meta($expense_id, '_wpd_recurring_expense_end_date');
                }
            }

            wp_send_json_success(array(
                'message' => 'Expense updated successfully.',
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to update expense: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Delete an expense
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function delete_expense_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            $expense_id = isset($_POST['expense_id']) ? intval($_POST['expense_id']) : 0;
            
            if ( ! WPD_AI_PRO ) {
                wp_send_json_error(array('message' => 'This feature is only available in the Pro version.'));
                return;
            }

            if ($expense_id <= 0) {
                wp_send_json_error(array('message' => 'Invalid expense ID.'));
                return;
            }

            $result = wp_delete_post($expense_id, true);

            if ($result) {
                wp_send_json_success(array(
                    'message' => 'Expense deleted successfully.',
                ));
            } else {
                wp_send_json_error(array('message' => 'Failed to delete expense.'));
            }

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to delete expense: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Create a new expense category
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function create_expense_category_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            $name = isset($_POST['name']) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
            $parent = isset($_POST['parent']) ? intval($_POST['parent']) : 0;
            
            if ( ! WPD_AI_PRO ) {
                wp_send_json_error(array('message' => 'This feature is only available in the Pro version.'));
                return;
            }

            if (empty($name)) {
                wp_send_json_error(array('message' => 'Category name is required.'));
                return;
            }

            $result = wp_insert_term($name, 'expense_category', array(
                'parent' => $parent,
            ));

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
                return;
            }

            wp_send_json_success(array(
                'message' => 'Expense category created successfully.',
                'term_id' => $result['term_id'],
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to create expense category: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Create a new supplier
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function create_supplier_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            $name = isset($_POST['name']) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
            
            if ( ! WPD_AI_PRO ) {
                wp_send_json_error(array('message' => 'This feature is only available in the Pro version.'));
                return;
            }

            if (empty($name)) {
                wp_send_json_error(array('message' => 'Supplier name is required.'));
                return;
            }

            $result = wp_insert_term($name, 'suppliers');

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
                return;
            }

            wp_send_json_success(array(
                'message' => 'Supplier created successfully.',
                'term_id' => $result['term_id'],
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to create supplier: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Calculate percentage change between two values
     *
     * @since 5.0.0
     *
     * @param float $old_value
     * @param float $new_value
     * @return float
     */
    private static function calculate_percentage_change($old_value, $new_value) {
        if ($old_value == 0) {
            return $new_value > 0 ? 100 : 0;
        }
        return (($new_value - $old_value) / $old_value) * 100;
    }

    /**
     * Log error messages
     *
     * @since 5.0.0
     *
     * @param string $message
     * @return void
     */
    private static function log_error($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($message);
        }
    }

    /**
     * Get all expenses with filters
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function get_all_expenses_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            $filters = array(
                'search' => isset($_POST['search']) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
                'status' => isset($_POST['status']) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'all',
                'category' => isset($_POST['category']) ? intval( wp_unslash( $_POST['category'] ) ) : '',
                'supplier' => isset($_POST['supplier']) ? intval( wp_unslash( $_POST['supplier'] ) ) : '',
                'dateFrom' => isset($_POST['dateFrom']) ? sanitize_text_field( wp_unslash( $_POST['dateFrom'] ) ) : '',
                'dateTo' => isset($_POST['dateTo']) ? sanitize_text_field( wp_unslash( $_POST['dateTo'] ) ) : '',
                'isPaid' => isset($_POST['isPaid']) ? sanitize_text_field( wp_unslash( $_POST['isPaid'] ) ) : 'all',
                'isRecurring' => isset($_POST['isRecurring']) ? sanitize_text_field( wp_unslash( $_POST['isRecurring'] ) ) : 'all',
                'hasAttachment' => isset($_POST['hasAttachment']) ? sanitize_text_field( wp_unslash( $_POST['hasAttachment'] ) ) : 'all',
            );

            // Pagination
            $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
            $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 50;
            $offset = ($page - 1) * $per_page;

            // Build WP_Query args
            $args = array(
                'post_type' => 'expense',
                'post_status' => 'publish',
                'posts_per_page' => $per_page,
                'offset' => $offset,
                'orderby' => 'meta_value',
                'meta_key' => '_wpd_date_paid',
                'order' => 'DESC',
            );

            if ( ! WPD_AI_PRO ) {
                $args['post__in'] = array(0); // no post has ID 0
            }

            // Add meta query for filters
            $meta_query = array('relation' => 'AND');
            
            // Paid/Unpaid filter
            if ($filters['isPaid'] === 'yes') {
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_wpd_paid',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key' => '_wpd_paid',
                        'value' => '0',
                        'compare' => '!=',
                    ),
                );
            } elseif ($filters['isPaid'] === 'no') {
                $meta_query[] = array(
                    'key' => '_wpd_paid',
                    'value' => '0',
                    'compare' => '=',
                );
            }

            // Recurring filter
            if ($filters['isRecurring'] === 'yes') {
                $meta_query[] = array(
                    'key' => '_wpd_recurring_expense_enabled',
                    'value' => '1',
                    'compare' => '=',
                );
            } elseif ($filters['isRecurring'] === 'no') {
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_wpd_recurring_expense_enabled',
                        'compare' => 'NOT EXISTS',
                    ),
                    array(
                        'key' => '_wpd_recurring_expense_enabled',
                        'value' => '1',
                        'compare' => '!=',
                    ),
                );
            }

            // Has Attachment filter
            if ($filters['hasAttachment'] === 'yes') {
                $meta_query[] = array(
                    'key' => '_wpd_expense_attachment',
                    'compare' => 'EXISTS',
                );
            } elseif ($filters['hasAttachment'] === 'no') {
                $meta_query[] = array(
                    'key' => '_wpd_expense_attachment',
                    'compare' => 'NOT EXISTS',
                );
            }

            // Date filters
            if ($filters['dateFrom'] || $filters['dateTo']) {
                $date_query = array();
                if ($filters['dateFrom']) {
                    $date_query['after'] = $filters['dateFrom'];
                }
                if ($filters['dateTo']) {
                    $date_query['before'] = $filters['dateTo'];
                }
                $args['date_query'] = array($date_query);
            }

            if (count($meta_query) > 1) {
                $args['meta_query'] = $meta_query;
            }

            // Tax query for category and supplier
            $tax_query = array('relation' => 'AND');
            if ($filters['category']) {
                $tax_query[] = array(
                    'taxonomy' => 'expense_category',
                    'field' => 'term_id',
                    'terms' => $filters['category'],
                );
            }
            if ($filters['supplier']) {
                $tax_query[] = array(
                    'taxonomy' => 'suppliers',
                    'field' => 'term_id',
                    'terms' => $filters['supplier'],
                );
            }
            if (count($tax_query) > 1) {
                $args['tax_query'] = $tax_query;
            }

            // Search filter
            if ($filters['search']) {
                $args['s'] = $filters['search'];
            }

            // Get total count (without pagination)
            $count_args = $args;
            $count_args['posts_per_page'] = -1;
            $count_args['fields'] = 'ids';
            unset($count_args['offset']);
            $count_query = new WP_Query($count_args);
            $total_count = $count_query->found_posts;
            wp_reset_postdata();

            // Get paginated results
            $query = new WP_Query($args);
            $expenses_list = array();
            $store_currency = get_woocommerce_currency();

            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $amount = floatval(get_post_meta($post_id, '_wpd_amount_paid', true));
                $currency = get_post_meta($post_id, '_wpd_amount_paid_currency', true);
                $is_paid = get_post_meta($post_id, '_wpd_paid', true);
                
                // Default to paid if meta not set
                if ($is_paid === '') {
                    $is_paid = '1';
                }

                // Currency conversion
                if ($currency && $currency !== $store_currency) {
                    $converted_amount = wpd_convert_currency($currency, $store_currency, $amount);
                } else {
                    $converted_amount = $amount;
                }

                // Get category terms
                $categories = wp_get_post_terms($post_id, 'expense_category');
                $category_name = !empty($categories) ? $categories[0]->name : '';

                // Get supplier terms
                $suppliers = wp_get_post_terms($post_id, 'suppliers');
                $supplier_name = !empty($suppliers) ? $suppliers[0]->name : '';

                    // Check if recurring
                    $is_recurring = get_post_meta($post_id, '_wpd_recurring_expense_enabled', true) === '1';
                    $recurring_frequency = $is_recurring ? get_post_meta($post_id, '_wpd_recurring_expense_frequency', true) : '';

                    // Get attachment
                    $attachment_id = get_post_meta($post_id, '_wpd_expense_attachment', true);
                    $attachment_url = '';
                    $attachment_type = '';
                    if ($attachment_id) {
                        $attachment_url = wp_get_attachment_url($attachment_id);
                        $attachment_mime = get_post_mime_type($attachment_id);
                        $attachment_type = $attachment_mime ? $attachment_mime : 'unknown';
                    }

                    $expenses_list[] = array(
                        'post_id' => $post_id,
                        'title' => get_the_title(),
                        'date_paid' => get_post_meta($post_id, '_wpd_date_paid', true),
                        'amount_paid' => $amount,
                        'amount_paid_converted' => $converted_amount,
                        'amount_paid_currency' => $currency,
                        'converted_to_currency' => $store_currency,
                        'paid' => $is_paid === '1',
                        'expense_type' => $category_name,
                        'supplier' => $supplier_name,
                        'is_recurring' => $is_recurring,
                        'recurring_frequency' => $recurring_frequency,
                        'reference' => get_post_meta($post_id, '_wpd_expense_reference', true),
                        'attachment_url' => $attachment_url,
                        'attachment_type' => $attachment_type,
                        'edit_link' => get_edit_post_link($post_id),
                    );
                }

            wp_reset_postdata();

            if ( ! WPD_AI_PRO ) {
                $expenses_list = array();
                $total_count = 0;
                $page = 1;
                $per_page = 50;
                $total_pages = 1;
            }

            wp_send_json_success(array(
                'expenses' => $expenses_list,
                'total_count' => $total_count,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => ceil($total_count / $per_page),
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to fetch expenses: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Bulk delete expenses
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function bulk_delete_expenses_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            $expense_ids_raw = isset($_POST['expense_ids']) ? sanitize_textarea_field( wp_unslash( $_POST['expense_ids'] ) ) : '';
            $expense_ids = ! empty( $expense_ids_raw ) ? json_decode( $expense_ids_raw, true ) : array();
            // Sanitize decoded JSON array - ensure all IDs are integers
            if ( is_array( $expense_ids ) ) {
                $expense_ids = array_map( 'absint', wpd_sanitize_json_decoded_array( $expense_ids ) );
            } else {
                $expense_ids = array();
            }

            if ( ! WPD_AI_PRO ) {
                wp_send_json_error(array('message' => 'This feature is only available in the Pro version.'));
                return;
            }

            if (empty($expense_ids) || !is_array($expense_ids)) {
                throw new Exception('No expense IDs provided');
            }

            $deleted_count = 0;
            foreach ($expense_ids as $expense_id) {
                $expense_id = absint( $expense_id );
                if ($expense_id && get_post_type($expense_id) === 'expense') {
                    if (wp_delete_post($expense_id, true)) {
                        $deleted_count++;
                    }
                }
            }

            wp_send_json_success(array(
                'message' => sprintf('%d expense(s) deleted successfully', $deleted_count),
                'deleted_count' => $deleted_count,
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to delete expenses: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Bulk update expenses
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function bulk_update_expenses_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            $expense_ids_raw = isset($_POST['expense_ids']) ? sanitize_textarea_field( wp_unslash( $_POST['expense_ids'] ) ) : '';
            $expense_ids = ! empty( $expense_ids_raw ) ? json_decode( $expense_ids_raw, true ) : array();
            // Sanitize decoded JSON array - ensure all IDs are integers
            if ( is_array( $expense_ids ) ) {
                $expense_ids = array_map( 'absint', wpd_sanitize_json_decoded_array( $expense_ids ) );
            } else {
                $expense_ids = array();
            }
            
            if ( ! WPD_AI_PRO ) {
                wp_send_json_error(array('message' => 'This feature is only available in the Pro version.'));
                return;
            }

            if (empty($expense_ids) || !is_array($expense_ids)) {
                throw new Exception('No expense IDs provided');
            }

            $update_data = array();
            
            // Check which fields to update
            if (isset($_POST['paid'])) {
                $update_data['paid'] = filter_var( wp_unslash( $_POST['paid'] ), FILTER_VALIDATE_BOOLEAN);
            }

            if (empty($update_data)) {
                throw new Exception('No update data provided');
            }

            $updated_count = 0;
            foreach ($expense_ids as $expense_id) {
                $expense_id = absint( $expense_id );
                if ($expense_id && get_post_type($expense_id) === 'expense') {
                    if (isset($update_data['paid'])) {
                        update_post_meta($expense_id, '_wpd_paid', $update_data['paid'] ? '1' : '0');
                        $updated_count++;
                    }
                }
            }

            wp_send_json_success(array(
                'message' => sprintf('%d expense(s) updated successfully', $updated_count),
                'updated_count' => $updated_count,
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to update expenses: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Update a taxonomy term
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function update_taxonomy_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) ) : '';
            $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
            $name = isset($_POST['name']) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
            $parent = isset($_POST['parent']) ? intval($_POST['parent']) : 0;

            if ( ! WPD_AI_PRO ) {
                wp_send_json_error(array('message' => 'This feature is only available in the Pro version.'));
                return;
            }

            if (empty($taxonomy) || empty($term_id) || empty($name)) {
                throw new Exception('Missing required parameters');
            }

            // Validate taxonomy
            if (!in_array($taxonomy, array('expense_category', 'suppliers'))) {
                throw new Exception('Invalid taxonomy');
            }

            // Update the term
            $args = array('name' => $name);
            if ($taxonomy === 'expense_category' && $parent > 0) {
                $args['parent'] = $parent;
            }

            $result = wp_update_term($term_id, $taxonomy, $args);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            wp_send_json_success(array(
                'message' => 'Taxonomy updated successfully',
                'term_id' => $term_id,
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to update taxonomy: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Delete a taxonomy term
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function delete_taxonomy_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) ) : '';
            $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;

            if ( ! WPD_AI_PRO ) {
                wp_send_json_error(array('message' => 'This feature is only available in the Pro version.'));
                return;
            }

            if (empty($taxonomy) || empty($term_id)) {
                throw new Exception('Missing required parameters');
            }

            // Validate taxonomy
            if (!in_array($taxonomy, array('expense_category', 'suppliers'))) {
                throw new Exception('Invalid taxonomy');
            }

            // Delete the term
            $result = wp_delete_term($term_id, $taxonomy);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            if (!$result) {
                throw new Exception('Failed to delete term. It may still be in use.');
            }

            wp_send_json_success(array(
                'message' => 'Taxonomy deleted successfully',
                'term_id' => $term_id,
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to delete taxonomy: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Bulk delete taxonomy terms
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function bulk_delete_taxonomies_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) ) : '';
            $term_ids_raw = isset($_POST['term_ids']) ? sanitize_textarea_field( wp_unslash( $_POST['term_ids'] ) ) : '';
            $term_ids = ! empty( $term_ids_raw ) ? json_decode( $term_ids_raw, true ) : array();
            // Sanitize decoded JSON array - ensure all term IDs are integers
            if ( is_array( $term_ids ) ) {
                $term_ids = array_map( 'absint', wpd_sanitize_json_decoded_array( $term_ids ) );
            } else {
                $term_ids = array();
            }

            if ( ! WPD_AI_PRO ) {
                wp_send_json_error(array('message' => 'This feature is only available in the Pro version.'));
                return;
            }

            if (empty($taxonomy) || empty($term_ids) || !is_array($term_ids)) {
                throw new Exception('Missing required parameters');
            }

            // Validate taxonomy
            if (!in_array($taxonomy, array('expense_category', 'suppliers'))) {
                throw new Exception('Invalid taxonomy');
            }

            $deleted_count = 0;
            $errors = array();

            foreach ($term_ids as $term_id) {
                $term_id = intval($term_id);
                if ($term_id) {
                    $result = wp_delete_term($term_id, $taxonomy);
                    if (is_wp_error($result)) {
                        $errors[] = $result->get_error_message();
                    } elseif ($result) {
                        $deleted_count++;
                    }
                }
            }

            wp_send_json_success(array(
                'message' => sprintf('%d term(s) deleted successfully', $deleted_count),
                'deleted_count' => $deleted_count,
                'errors' => $errors,
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to bulk delete taxonomies: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Get expense by ID
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function get_expense_by_id_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            $expense_id = isset($_POST['expense_id']) ? intval($_POST['expense_id']) : 0;

            if ( ! WPD_AI_PRO ) {
                wp_send_json_error(array('message' => 'This feature is only available in the Pro version.'));
                return;
            }

            if (empty($expense_id)) {
                throw new Exception('Expense ID is required');
            }

            $post = get_post($expense_id);
            
            if (!$post || $post->post_type !== 'expense') {
                throw new Exception('Expense not found');
            }

            // Get all meta data
            $amount = get_post_meta($expense_id, '_wpd_amount_paid', true);
            $currency = get_post_meta($expense_id, '_wpd_amount_paid_currency', true);
            $date_paid = get_post_meta($expense_id, '_wpd_date_paid', true);
            $reference = get_post_meta($expense_id, '_wpd_expense_reference', true);
            $attachment_id = get_post_meta($expense_id, '_wpd_expense_attachment', true);
            $paid_meta = get_post_meta($expense_id, '_wpd_paid', true);
            $paid = ($paid_meta === '' || $paid_meta === '1'); // Default to paid if not set
            $recurring = get_post_meta($expense_id, '_wpd_recurring_expense_enabled', true) === '1';
            $recurring_frequency = get_post_meta($expense_id, '_wpd_recurring_expense_frequency', true);
            $recurring_start_date = get_post_meta($expense_id, '_wpd_recurring_expense_beginning_date', true);
            $recurring_end_date = get_post_meta($expense_id, '_wpd_recurring_expense_end_date', true);

            // Get attachment URL if exists
            $attachment_url = '';
            if ($attachment_id) {
                $attachment_url = wp_get_attachment_url($attachment_id);
            }

            // Get taxonomies
            $categories = wp_get_post_terms($expense_id, 'expense_category');
            $category_id = !empty($categories) ? $categories[0]->term_id : '';

            $suppliers = wp_get_post_terms($expense_id, 'suppliers');
            $supplier_id = !empty($suppliers) ? $suppliers[0]->term_id : '';

            wp_send_json_success(array(
                'id' => $expense_id,
                'title' => $post->post_title,
                'amount' => $amount,
                'currency' => $currency,
                'date' => $date_paid,
                'reference' => $reference,
                'attachment_id' => $attachment_id,
                'attachment_url' => $attachment_url,
                'expense_category' => $category_id,
                'supplier' => $supplier_id,
                'paid' => $paid,
                'recurring' => $recurring,
                'recurring_frequency' => $recurring_frequency,
                'recurring_start_date' => $recurring_start_date,
                'recurring_end_date' => $recurring_end_date,
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to fetch expense: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Upload attachment for expense
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function upload_attachment_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            if (empty($_FILES['file'])) {
                throw new Exception('No file uploaded');
            }

            if ( ! WPD_AI_PRO ) {
                wp_send_json_error(array('message' => 'This feature is only available in the Pro version.'));
                return;
            }

            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File uploads are validated and sanitized by wp_handle_upload().
            $file = $_FILES['file'];

            // Validate file size (10MB max)
            if ($file['size'] > 10 * 1024 * 1024) {
                throw new Exception('File size must be less than 10MB');
            }

            // Validate file type
            $allowed_types = array(
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            );

            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception('File type not allowed. Please upload an image, PDF, or DOC file.');
            }

            // Upload file using WordPress
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $attachment_id = media_handle_upload('file', 0);

            if (is_wp_error($attachment_id)) {
                throw new Exception($attachment_id->get_error_message());
            }

            $attachment_url = wp_get_attachment_url($attachment_id);

            wp_send_json_success(array(
                'attachment_id' => $attachment_id,
                'url' => $attachment_url,
                'message' => 'File uploaded successfully',
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to upload file: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Export all expenses as CSV
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function export_all_expenses_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            if ( ! WPD_AI_PRO ) {
                wp_send_json_error(array('message' => 'This feature is only available in the Pro version.'));
                exit;
            }

            // Query all expenses
            $args = array(
                'post_type' => 'expense',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'meta_value',
                'meta_key' => '_wpd_date_paid',
                'order' => 'DESC',
            );

            $query = new WP_Query($args);
            $store_currency = get_woocommerce_currency();

            // Set headers for CSV download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=expenses-export-' . gmdate('Y-m-d') . '.csv');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Create output stream
            $output = fopen('php://output', 'w');

            // Write CSV headers
            fputcsv($output, array(
                'Title',
                'Amount',
                'Currency',
                'Converted Amount',
                'Store Currency',
                'Date',
                'Category',
                'Supplier',
                'Reference',
                'Status',
                'Recurring',
                'Recurring Frequency',
            ));

            // Write expense rows
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $amount = floatval(get_post_meta($post_id, '_wpd_amount_paid', true));
                $currency = get_post_meta($post_id, '_wpd_amount_paid_currency', true);
                $date_paid = get_post_meta($post_id, '_wpd_date_paid', true);
                $reference = get_post_meta($post_id, '_wpd_expense_reference', true);
                $is_paid = get_post_meta($post_id, '_wpd_paid', true);
                $is_recurring = get_post_meta($post_id, '_wpd_recurring_expense_enabled', true) === '1';
                $recurring_frequency = get_post_meta($post_id, '_wpd_recurring_expense_frequency', true);

                // Default to paid if meta not set
                if ($is_paid === '') {
                    $is_paid = '1';
                }

                // Currency conversion
                if ($currency && $currency !== $store_currency) {
                    $converted_amount = wpd_convert_currency($currency, $store_currency, $amount);
                } else {
                    $converted_amount = $amount;
                }

                // Get category
                $categories = wp_get_post_terms($post_id, 'expense_category');
                $category_name = !empty($categories) ? $categories[0]->name : '';

                // Get supplier
                $suppliers = wp_get_post_terms($post_id, 'suppliers');
                $supplier_name = !empty($suppliers) ? $suppliers[0]->name : '';

                // Write row
                fputcsv($output, array(
                    get_the_title(),
                    number_format($amount, 2, '.', ''),
                    $currency,
                    number_format($converted_amount, 2, '.', ''),
                    $store_currency,
                    $date_paid,
                    $category_name,
                    $supplier_name,
                    $reference,
                    $is_paid === '1' ? 'Paid' : 'Unpaid',
                    $is_recurring ? 'Yes' : 'No',
                    $recurring_frequency,
                ));
            }

            wp_reset_postdata();
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct output to browser for CSV download is acceptable.
            fclose($output);
            exit;

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to export expenses: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Export filtered expenses to CSV
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function export_filtered_expenses_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {

            if ( ! WPD_AI_PRO ) {
                wp_send_json_error(array('message' => 'This feature is only available in the Pro version.'));
                exit;
            }

            // Get filters from request
            $date_from = isset($_POST['date_from']) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
            $date_to = isset($_POST['date_to']) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';
            $category = isset($_POST['category']) ? intval( wp_unslash( $_POST['category'] ) ) : 0;
            $supplier = isset($_POST['supplier']) ? intval( wp_unslash( $_POST['supplier'] ) ) : 0;
            $search = isset($_POST['search']) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
            $is_recurring = isset($_POST['is_recurring']) ? sanitize_text_field( wp_unslash( $_POST['is_recurring'] ) ) : 'all';
            $is_paid = isset($_POST['is_paid']) ? sanitize_text_field( wp_unslash( $_POST['is_paid'] ) ) : 'all';
            $has_attachment = isset($_POST['has_attachment']) ? sanitize_text_field( wp_unslash( $_POST['has_attachment'] ) ) : 'all';

            // Build query args
            $args = array(
                'post_type' => 'expense',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'meta_value',
                'meta_key' => '_wpd_date_paid',
                'order' => 'DESC',
            );

            $meta_query = array('relation' => 'AND');
            $tax_query = array('relation' => 'AND');

            // Date filters
            if (!empty($date_from) && !empty($date_to)) {
                $meta_query[] = array(
                    'key' => '_wpd_date_paid',
                    'value' => array($date_from, $date_to),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                );
            } elseif (!empty($date_from)) {
                $meta_query[] = array(
                    'key' => '_wpd_date_paid',
                    'value' => $date_from,
                    'compare' => '>=',
                    'type' => 'DATE'
                );
            } elseif (!empty($date_to)) {
                $meta_query[] = array(
                    'key' => '_wpd_date_paid',
                    'value' => $date_to,
                    'compare' => '<=',
                    'type' => 'DATE'
                );
            }

            // Category filter
            if ($category > 0) {
                $tax_query[] = array(
                    'taxonomy' => 'expense_category',
                    'field' => 'term_id',
                    'terms' => $category,
                );
            }

            // Supplier filter
            if ($supplier > 0) {
                $tax_query[] = array(
                    'taxonomy' => 'suppliers',
                    'field' => 'term_id',
                    'terms' => $supplier,
                );
            }

            // Recurring filter
            if ($is_recurring === 'yes') {
                $meta_query[] = array(
                    'key' => '_wpd_recurring_expense_enabled',
                    'value' => '1',
                    'compare' => '='
                );
            } elseif ($is_recurring === 'no') {
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_wpd_recurring_expense_enabled',
                        'value' => '1',
                        'compare' => '!='
                    ),
                    array(
                        'key' => '_wpd_recurring_expense_enabled',
                        'compare' => 'NOT EXISTS'
                    )
                );
            }

            // Paid filter
            if ($is_paid === 'yes') {
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_wpd_paid',
                        'value' => '1',
                        'compare' => '='
                    ),
                    array(
                        'key' => '_wpd_paid',
                        'compare' => 'NOT EXISTS'
                    )
                );
            } elseif ($is_paid === 'no') {
                $meta_query[] = array(
                    'key' => '_wpd_paid',
                    'value' => '0',
                    'compare' => '='
                );
            }

            // Attachment filter
            if ($has_attachment === 'yes') {
                $meta_query[] = array(
                    'key' => '_wpd_expense_attachment',
                    'value' => '',
                    'compare' => '!='
                );
            } elseif ($has_attachment === 'no') {
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_wpd_expense_attachment',
                        'value' => '',
                        'compare' => '='
                    ),
                    array(
                        'key' => '_wpd_expense_attachment',
                        'compare' => 'NOT EXISTS'
                    )
                );
            }

            // Add meta query if not empty
            if (count($meta_query) > 1) {
                $args['meta_query'] = $meta_query;
            }

            // Add tax query if not empty
            if (count($tax_query) > 1) {
                $args['tax_query'] = $tax_query;
            }

            // Search filter
            if (!empty($search)) {
                $args['s'] = $search;
            }

            $query = new WP_Query($args);
            $store_currency = get_woocommerce_currency();

            // Set headers for CSV download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=filtered-expenses-export-' . gmdate('Y-m-d') . '.csv');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Create output stream
            $output = fopen('php://output', 'w');

            // Write CSV headers
            fputcsv($output, array(
                'Title',
                'Amount',
                'Currency',
                'Converted Amount',
                'Store Currency',
                'Date',
                'Category',
                'Supplier',
                'Reference',
                'Status',
                'Recurring',
                'Recurring Frequency',
            ));

            // Write expense rows
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $amount = floatval(get_post_meta($post_id, '_wpd_amount_paid', true));
                $currency = get_post_meta($post_id, '_wpd_amount_paid_currency', true);
                $date_paid = get_post_meta($post_id, '_wpd_date_paid', true);
                $reference = get_post_meta($post_id, '_wpd_expense_reference', true);
                $is_paid_meta = get_post_meta($post_id, '_wpd_paid', true);
                $is_recurring = get_post_meta($post_id, '_wpd_recurring_expense_enabled', true) === '1';
                $recurring_frequency = get_post_meta($post_id, '_wpd_recurring_expense_frequency', true);

                // Default to paid if meta not set
                if ($is_paid_meta === '') {
                    $is_paid_meta = '1';
                }

                // Currency conversion
                if ($currency && $currency !== $store_currency) {
                    $converted_amount = wpd_convert_currency($currency, $store_currency, $amount);
                } else {
                    $converted_amount = $amount;
                }

                // Get category
                $categories = wp_get_post_terms($post_id, 'expense_category');
                $category_name = !empty($categories) ? $categories[0]->name : '';

                // Get supplier
                $suppliers = wp_get_post_terms($post_id, 'suppliers');
                $supplier_name = !empty($suppliers) ? $suppliers[0]->name : '';

                // Write row
                fputcsv($output, array(
                    get_the_title(),
                    number_format($amount, 2, '.', ''),
                    $currency,
                    number_format($converted_amount, 2, '.', ''),
                    $store_currency,
                    $date_paid,
                    $category_name,
                    $supplier_name,
                    $reference,
                    $is_paid_meta === '1' ? 'Paid' : 'Unpaid',
                    $is_recurring ? 'Yes' : 'No',
                    $recurring_frequency,
                ));
            }

            wp_reset_postdata();
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct output to browser for CSV download is acceptable.
            fclose($output);
            exit;

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to export filtered expenses: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Bulk assign taxonomy to expenses
     *
     * @since 5.0.0
     *
     * @return void
     */
    public static function bulk_assign_taxonomy_ajax_handler() {
        check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

        try {
            $expense_ids_raw = isset($_POST['expense_ids']) ? sanitize_textarea_field( wp_unslash( $_POST['expense_ids'] ) ) : '';
            $expense_ids = ! empty( $expense_ids_raw ) ? json_decode( $expense_ids_raw, true ) : array();
            // Sanitize decoded JSON array - ensure all IDs are integers
            if ( is_array( $expense_ids ) ) {
                $expense_ids = array_map( 'absint', wpd_sanitize_json_decoded_array( $expense_ids ) );
            } else {
                $expense_ids = array();
            }
            $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) ) : '';
            $term_id = isset($_POST['term_id']) ? absint( $_POST['term_id'] ) : 0;

            if ( ! WPD_AI_PRO ) {
                wp_send_json_error(array('message' => 'This feature is only available in the Pro version.'));
                exit;
            }

            if (empty($expense_ids) || !is_array($expense_ids)) {
                throw new Exception('No expenses selected');
            }

            if (empty($taxonomy) || !in_array($taxonomy, array('expense_category', 'suppliers'))) {
                throw new Exception('Invalid taxonomy');
            }

            if (empty($term_id)) {
                throw new Exception('No term selected');
            }

            // Verify term exists
            $term = get_term($term_id, $taxonomy);
            if (is_wp_error($term) || !$term) {
                throw new Exception('Invalid term ID');
            }

            $success_count = 0;
            $errors = array();

            foreach ($expense_ids as $expense_id) {
                $expense_id = intval($expense_id);
                
                // Verify post exists and is an expense
                $post = get_post($expense_id);
                if (!$post || $post->post_type !== 'expense') {
                    $errors[] = "Expense ID {$expense_id} not found";
                    continue;
                }

                // Assign the taxonomy term (replaces existing terms for this taxonomy)
                $result = wp_set_post_terms($expense_id, array($term_id), $taxonomy, false);
                
                if (is_wp_error($result)) {
                    $errors[] = "Failed to assign to expense ID {$expense_id}: " . $result->get_error_message();
                } else {
                    $success_count++;
                }
            }

            wp_send_json_success(array(
                'message' => sprintf(
                    'Successfully assigned %s to %d expense(s)',
                    $taxonomy === 'expense_category' ? 'category' : 'supplier',
                    $success_count
                ),
                'success_count' => $success_count,
                'errors' => $errors,
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to assign taxonomy: ' . $e->getMessage(),
            ));
        }
    }

    /**
     * Generate all occurrences of a recurring expense within a date range
     * 
     * @since 5.0.0
     * 
     * @param int $expense_id The expense post ID
     * @param string $from_date Optional. Start date (Y-m-d format). Defaults to expense start date.
     * @param string $to_date Optional. End date (Y-m-d format). Defaults to current date or expense end date.
     * @param string $store_currency Optional. Currency to convert to. Defaults to site currency.
     * 
     * @return array Array of expense occurrences with structure matching data warehouse format
     */
    public static function generate_recurring_expense_occurrences($expense_id, $from_date = null, $to_date = null, $store_currency = null) {
        // Validate expense exists and is recurring
        $post = get_post($expense_id);
        if (!$post || $post->post_type !== 'expense') {
            return array();
        }

        $recurring_enabled = get_post_meta($expense_id, '_wpd_recurring_expense_enabled', true);
        if ($recurring_enabled !== '1') {
            return array();
        }

        // Get store currency if not provided
        if (empty($store_currency)) {
            $store_currency = get_woocommerce_currency();
        }

        // Get expense meta data
        $amount_paid = (float) get_post_meta($expense_id, '_wpd_amount_paid', true);
        $amount_paid_currency = (string) get_post_meta($expense_id, '_wpd_amount_paid_currency', true);
        $expense_reference = (string) get_post_meta($expense_id, '_wpd_expense_reference', true);
        $is_expense_paid = (get_post_meta($expense_id, '_wpd_paid', true) === '' || get_post_meta($expense_id, '_wpd_paid', true) === '1') ? 1 : 0;
        $recurring_frequency = get_post_meta($expense_id, '_wpd_recurring_expense_frequency', true);
        $recurring_start_date = get_post_meta($expense_id, '_wpd_recurring_expense_beginning_date', true);
        $recurring_end_date = get_post_meta($expense_id, '_wpd_recurring_expense_end_date', true);

        // Get expense categories
        $expense_categories = wp_get_post_terms($expense_id, 'expense_category');
        $expense_type_name = !empty($expense_categories) && !is_wp_error($expense_categories) ? $expense_categories[0]->name : '';

        // Set date boundaries
        // From date: Use provided from_date, or expense start date
        if (empty($from_date)) {
            $from_date = $recurring_start_date;
        }

        // To date: Use provided to_date, or min(current_date, expense_end_date)
        if (empty($to_date)) {
            $current_date = current_time('Y-m-d');
            if (!empty($recurring_end_date)) {
                $to_date = min($current_date, $recurring_end_date);
            } else {
                $to_date = $current_date;
            }
        } else {
            // If to_date is provided but there's an expense end date, use the earlier one
            if (!empty($recurring_end_date)) {
                $to_date = min($to_date, $recurring_end_date);
            }
        }

        // Convert dates to timestamps
        $recurring_start_timestamp = strtotime($recurring_start_date);
        $recurring_end_timestamp = !empty($recurring_end_date) ? strtotime($recurring_end_date) : null;
        $from_timestamp = strtotime($from_date);
        $to_timestamp = strtotime($to_date);
        $current_timestamp = current_time('timestamp');

        // Start from the later of: expense start date or filter from date
        $loop_timestamp = max($recurring_start_timestamp, $from_timestamp);

        $occurrences = array();

        // Generate occurrences
        while ($loop_timestamp <= $to_timestamp) {
            // Stop if we've exceeded the expense end date
            if (!is_null($recurring_end_timestamp) && $loop_timestamp > $recurring_end_timestamp) {
                break;
            }

            // Stop if we've exceeded the current date
            if ($loop_timestamp > $current_timestamp) {
                break;
            }

            // Only include if within the specified range
            if ($loop_timestamp >= $from_timestamp && $loop_timestamp <= $to_timestamp) {
                $occurrence_date = gmdate('Y-m-d', $loop_timestamp);
                $unique_id = $expense_id . '-' . $occurrence_date;

                // Currency conversion
                if ($amount_paid_currency != $store_currency) {
                    $converted_value = wpd_convert_currency($amount_paid_currency, $store_currency, $amount_paid);
                } else {
                    $converted_value = $amount_paid;
                }

                // Build occurrence data
                $occurrences[$unique_id] = array(
                    'unique_id'              => $unique_id,
                    'title'                  => wp_strip_all_tags(html_entity_decode(get_the_title($expense_id), ENT_QUOTES, 'UTF-8')),
                    'date_created'           => get_the_date('Y-m-d', $expense_id),
                    'date_paid_unix'         => $loop_timestamp,
                    'date_paid'              => $occurrence_date,
                    'reference'              => $expense_reference,
                    'amount_paid'            => $amount_paid,
                    'amount_paid_currency'   => $amount_paid_currency,
                    'amount_paid_converted'  => $converted_value,
                    'converted_to_currency'  => $store_currency,
                    'expense_type'           => $expense_type_name,
                    'recurring_expense'      => 1,
                    'recurring_frequency'    => $recurring_frequency,
                    'post_id'                => $expense_id,
                    'reference_number'       => $expense_reference,
                    'is_paid'                => $is_expense_paid,
                );
            }

            // Increment based on frequency with validation to prevent infinite loops
            $new_timestamp = false;
            
            if ($recurring_frequency === 'daily') {
                $new_timestamp = strtotime('+1 day', $loop_timestamp);
            } elseif ($recurring_frequency === 'weekly') {
                $new_timestamp = strtotime('+1 week', $loop_timestamp);
            } elseif ($recurring_frequency === 'fortnightly') {
                $new_timestamp = strtotime('+2 weeks', $loop_timestamp);
            } elseif ($recurring_frequency === 'monthly') {
                $new_timestamp = strtotime('+1 month', $loop_timestamp);
            } elseif ($recurring_frequency === 'quarterly') {
                $new_timestamp = strtotime('+3 months', $loop_timestamp);
            } elseif ($recurring_frequency === 'yearly' || $recurring_frequency === 'annually') {
                $new_timestamp = strtotime('+1 year', $loop_timestamp);
            } else {
                // Unknown frequency - default to monthly to prevent infinite loop
                $new_timestamp = strtotime('+1 month', $loop_timestamp);
            }

            // Validate timestamp advancement to prevent infinite loops
            if ($new_timestamp === false || $new_timestamp <= $loop_timestamp) {
                // strtotime() failed or timestamp didn't advance - break to prevent infinite loop
                wpd_write_log("Recurring expense loop validation failed for expense ID: {$expense_id}. Timestamp did not advance.", 'expense_error');
                break;
            }

            $loop_timestamp = $new_timestamp;
        }

        return $occurrences;
    }

    /**
     * Get total amount for a recurring expense within a date range
     * 
     * @since 5.0.0
     * 
     * @param int $expense_id The expense post ID
     * @param string $from_date Optional. Start date (Y-m-d format)
     * @param string $to_date Optional. End date (Y-m-d format)
     * @param string $store_currency Optional. Currency to convert to
     * 
     * @return array Array with 'total', 'count', 'occurrences'
     */
    public static function get_recurring_expense_total($expense_id, $from_date = null, $to_date = null, $store_currency = null) {
        $occurrences = self::generate_recurring_expense_occurrences($expense_id, $from_date, $to_date, $store_currency);
        
        $total = 0;
        foreach ($occurrences as $occurrence) {
            $total += $occurrence['amount_paid_converted'];
        }

        return array(
            'total' => $total,
            'count' => count($occurrences),
            'occurrences' => $occurrences,
        );
    }
}

