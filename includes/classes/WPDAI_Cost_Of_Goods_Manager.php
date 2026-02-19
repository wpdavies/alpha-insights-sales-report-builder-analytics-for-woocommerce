<?php 
/**
 * Modern Cost of Goods Management
 * AJAX-powered interface for efficient product COGS management
 *
 * @package Alpha Insights
 * @version 4.9.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

class WPDAI_Cost_Of_Goods_Manager {

	/**
	 * Register AJAX actions
	 */
	public static function register_ajax_actions() {
		add_action('wp_ajax_wpd_get_cogs_products', [__CLASS__, 'ajax_get_products']);
		add_action('wp_ajax_wpd_update_product_cost', [__CLASS__, 'ajax_update_product_cost']);
		add_action('wp_ajax_wpd_export_cogs_csv', [__CLASS__, 'ajax_export_csv']);
		add_action('wp_ajax_wpd_import_product_cost', [__CLASS__, 'ajax_import_product_cost']);
		add_action('wp_ajax_wpd_get_migration_count', [__CLASS__, 'ajax_get_migration_count']);
		add_action('wp_ajax_wpd_migrate_cogs_data', [__CLASS__, 'ajax_migrate_cogs_data']);
		add_action('wp_ajax_wpd_get_available_meta_keys', [__CLASS__, 'ajax_get_available_meta_keys']);
	}

	/**
	 * Output the modern COGS management page
	 */
	public static function output() {
		// Enqueue scripts and styles
		self::enqueue_assets();
		
		// Render the page
		self::render_page();
	}

	/**
	 * Enqueue necessary scripts and styles
	 */
	private static function enqueue_assets() {
		wp_enqueue_style(
			'wpd-cogs-manager',
			WPD_AI_URL_PATH . 'assets/css/wpd-cost-of-goods-manager.css',
			[],
			WPD_AI_VER
		);

		wp_enqueue_script(
			'wpd-cogs-manager',
			WPD_AI_URL_PATH . 'assets/js/wpd-cost-of-goods-manager.js',
			['jquery'],
			WPD_AI_VER,
			true
		);

		// Get cost defaults
		$cost_defaults = get_option('wpd_ai_cost_defaults');
		$default_cost_percent = isset($cost_defaults['default_product_cost_percent']) ? $cost_defaults['default_product_cost_percent'] : 0;

		// Localize script with WooCommerce currency settings for international support
		wp_localize_script('wpd-cogs-manager', 'wpdCogsManager', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce(WPD_AI_AJAX_NONCE_ACTION),
			'currency_symbol' => get_woocommerce_currency_symbol(),
			'currency_code' => get_woocommerce_currency(),
			'price_decimals' => wc_get_price_decimals(),
			'price_decimal_sep' => wc_get_price_decimal_separator(),
			'price_thousand_sep' => wc_get_price_thousand_separator(),
			'default_cost_percent' => $default_cost_percent,
			'settings_url' => wpdai_admin_page_url('settings')
		]);
	}

	/**
	 * Render the modern COGS management page
	 */
	private static function render_page() {

		$categories = get_terms([
			'taxonomy' => 'product_cat',
			'hide_empty' => false
		]);

		if ( ! is_array( $categories ) ) $categories = [];
		
		$suppliers = get_terms([
			'taxonomy' => 'suppliers',
			'hide_empty' => false
		]);

		if ( ! is_array( $suppliers ) ) $suppliers = [];

		?>
        <div class="wrap">
            <div class="wpd-cogs-container" id="wpd-cogs-manager">
                <!-- Header -->
                <div class="wpd-cogs-header">
                    <div class="wpd-cogs-header-content">
                        <div>
                            <h1 class="wpd-cogs-title">Manage Cost of Goods</h1>
                            <p class="wpd-cogs-subtitle">Use this tool to quickly and easily update your product cost of goods. Use the help button for further information.</p>
                        </div>
                        <div class="wpd-cogs-header-buttons">
                            <button type="button" id="wpd-cogs-stats-btn" class="wpd-btn wpd-btn-secondary">
                                <span class="dashicons dashicons-chart-bar"></span> Stats
                            </button>
                            <button type="button" id="wpd-cogs-help-btn" class="wpd-btn wpd-btn-secondary">
                                <span class="dashicons dashicons-editor-help"></span> Help & About
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="wpd-cogs-stats">
                    <div class="wpd-cogs-stat-card">
                        <div class="wpd-cogs-stat-label">Total Products</div>
                        <div class="wpd-cogs-stat-value" id="wpd-cogs-total-products">-</div>
                    </div>
                    <div class="wpd-cogs-stat-card">
                        <div class="wpd-cogs-stat-label">With Cost Set</div>
                        <div class="wpd-cogs-stat-value" id="wpd-cogs-products-with-cost">-</div>
                    </div>
                    <div class="wpd-cogs-stat-card">
                        <div class="wpd-cogs-stat-label">Without Cost</div>
                        <div class="wpd-cogs-stat-value" id="wpd-cogs-products-without-cost">-</div>
                    </div>
                    <div class="wpd-cogs-stat-card">
                        <div class="wpd-cogs-stat-label">Avg Margin</div>
                        <div class="wpd-cogs-stat-value" id="wpd-cogs-avg-margin">-</div>
                    </div>
                    <div class="wpd-cogs-stat-card">
                        <div class="wpd-cogs-stat-label"><?php esc_html_e( 'Stock Value (RRP)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
                        <div class="wpd-cogs-stat-value" id="wpd-cogs-total-stock-value-rrp">-</div>
                    </div>
                    <div class="wpd-cogs-stat-card">
                        <div class="wpd-cogs-stat-label"><?php esc_html_e( 'Stock Value (Sell)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
                        <div class="wpd-cogs-stat-value" id="wpd-cogs-total-stock-value-sell">-</div>
                    </div>
                    <div class="wpd-cogs-stat-card">
                        <div class="wpd-cogs-stat-label"><?php esc_html_e( 'Stock Value (Cost)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
                        <div class="wpd-cogs-stat-value" id="wpd-cogs-total-stock-value-cost">-</div>
                    </div>
                </div>

				<!-- Toolbar -->
				<div class="wpd-cogs-toolbar">
                    <div class="wpd-cogs-toolbar-left">
                        <div class="wpd-cogs-filter-group">
                            <label class="wpd-cogs-filter-label">Per Page</label>
                            <select id="wpd-cogs-per-page">
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="200">200</option>
                            </select>
                        </div>
                        <div class="wpd-cogs-pagination-info" id="wpd-cogs-pagination-info">
                            Page <span id="wpd-cogs-current-page">1</span> of <span id="wpd-cogs-total-pages">1</span> (<span id="wpd-cogs-total-items">0</span> items)
                        </div>
                    </div>
                     <div class="wpd-cogs-toolbar-right">
                         <button type="button" id="wpd-cogs-migrate-from" class="wpd-btn wpd-btn-secondary">
                             <span class="dashicons dashicons-update"></span> Migrate From
                         </button>
                         <button type="button" id="wpd-cogs-import-csv" class="wpd-btn wpd-btn-secondary">
                             <span class="dashicons dashicons-upload"></span> Import CSV
                         </button>
                         <button type="button" id="wpd-cogs-export-csv" class="wpd-btn wpd-btn-secondary">
                             <span class="dashicons dashicons-download"></span> Export CSV
                         </button>
                     </div>
                </div>

                <!-- Filters -->
                <div class="wpd-cogs-filters">
                    <div class="wpd-cogs-filters-grid">
                        <div class="wpd-cogs-filter-group">
                            <label class="wpd-cogs-filter-label">Search Products</label>
                            <input type="text" id="wpd-cogs-search" placeholder="Search by name or SKU..." />
                        </div>

                        <div class="wpd-cogs-filter-group">
                            <label class="wpd-cogs-filter-label">Category</label>
                            <select class="wpd-cogs-filter" data-filter="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo esc_attr($category->term_id); ?>">
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="wpd-cogs-filter-group">
                            <label class="wpd-cogs-filter-label">Supplier</label>
                            <select class="wpd-cogs-filter" data-filter="supplier">
                                <option value="">All Suppliers</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo esc_attr($supplier->term_id); ?>">
                                        <?php echo esc_html($supplier->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="wpd-cogs-filter-group">
                            <label class="wpd-cogs-filter-label">Stock Status</label>
                            <select class="wpd-cogs-filter" data-filter="stock_status">
                                <option value="">All Stock</option>
                                <option value="instock">In Stock</option>
                                <option value="outofstock">Out of Stock</option>
                                <option value="onbackorder">On Backorder</option>
                            </select>
                        </div>

                        <div class="wpd-cogs-filter-group">
                            <label class="wpd-cogs-filter-label">Product Type</label>
                            <select class="wpd-cogs-filter" data-filter="product_type">
                                <option value="">All Types</option>
                                <option value="simple">Simple</option>
                                <option value="variable">Variable</option>
                                <option value="variation">Variation</option>
                            </select>
                        </div>

                        <div class="wpd-cogs-filter-group">
                            <label class="wpd-cogs-filter-label">Cost Status</label>
                            <select class="wpd-cogs-filter" data-filter="has_cost">
                                <option value="">All Products</option>
                                <option value="yes">Has Cost</option>
                                <option value="no">Missing Cost</option>
                            </select>
                        </div>

                         <div class="wpd-cogs-filter-group">
                             <label class="wpd-cogs-filter-label">&nbsp;</label>
                             <button type="button" id="wpd-cogs-clear-filters" class="button btn wpd-input">Clear</button>
                         </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="wpd-cogs-table-container">
                    <div class="wpd-cogs-table-wrapper">
                         <table class="wpd-cogs-table">
                             <thead>
                                 <tr>
                                     <th class="wpd-cogs-checkbox-col">
                                         <input type="checkbox" id="wpd-cogs-select-all" title="Select All">
                                     </th>
                                     <th class="wpd-cogs-sortable" data-column="name">Product</th>
                                     <th class="wpd-cogs-sortable" data-column="rrp">RRP</th>
                                     <th class="wpd-cogs-sortable" data-column="sell_price"><?php esc_html_e( 'Sell Price', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
                                     <th><?php esc_html_e( 'Cost of Goods', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
                                     <th class="wpd-cogs-sortable" data-column="margin">Margin %</th>
                                     <th class="wpd-cogs-sortable" data-column="profit">Profit</th>
                                     <th class="wpd-cogs-sortable" data-column="stock">Stock</th>
                                     <th>Actions</th>
                                 </tr>
                             </thead>
                             <tbody id="wpd-cogs-table-body">
                                 <tr><td colspan="9" class="wpd-cogs-loading"><?php esc_html_e( 'Loading products...', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></td></tr>
                             </tbody>
                         </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="wpd-cogs-pagination" id="wpd-cogs-pagination"></div>
            </div>
        </div>
		<?php
	}

	/**
	 * AJAX: Get products with pagination and filters
	 */
	public static function ajax_get_products() {
		check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => 'Insufficient permissions']);
		}

		$page = isset($_POST['page']) ? absint( wp_unslash( $_POST['page'] ) ) : 1;
		$per_page = isset($_POST['per_page']) ? absint( wp_unslash( $_POST['per_page'] ) ) : 25;
		$filters_raw = isset($_POST['filters']) ? wp_unslash( $_POST['filters'] ) : '[]';
		
		// Handle both array and JSON string inputs
		if ( is_array( $filters_raw ) ) {
			$filters = $filters_raw;
		} else {
			$filters = json_decode( $filters_raw, true );
			if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $filters ) ) {
				$filters = [];
			}
		}
		if ( function_exists( 'wpdai_sanitize_json_decoded_array' ) && ! empty( $filters ) ) {
			$filters = wpdai_sanitize_json_decoded_array( $filters );
		}
		$sort_by = isset($_POST['sort_by']) ? sanitize_text_field( wp_unslash( $_POST['sort_by'] ) ) : 'name';
		$sort_order = isset($_POST['sort_order']) ? sanitize_text_field( wp_unslash( $_POST['sort_order'] ) ) : 'asc';

		// Build WP_Query args
		$args = [
			'post_type' => ['product', 'product_variation'],
			'post_status' => 'publish',
			'posts_per_page' => $per_page,
			'paged' => $page,
			'fields' => 'ids' // Only get IDs for efficiency
		];

		// Apply search filter
		if (!empty($filters['search'])) {
			$args['s'] = sanitize_text_field($filters['search']);
		}

		// Apply category filter
		if (!empty($filters['category'])) {
			$args['tax_query'] = [[
				'taxonomy' => 'product_cat',
				'field' => 'term_id',
				'terms' => absint($filters['category'])
			]];
		}

		// Apply supplier filter
		if (!empty($filters['supplier'])) {
			if (!isset($args['tax_query'])) {
				$args['tax_query'] = [];
			}
			$args['tax_query'][] = [
				'taxonomy' => 'suppliers',
				'field' => 'term_id',
				'terms' => absint($filters['supplier'])
			];
			// If we have multiple tax queries, set relation
			if (count($args['tax_query']) > 1) {
				$args['tax_query']['relation'] = 'AND';
			}
		}

		// Apply product type filter
		if (!empty($filters['product_type'])) {
			$product_type = sanitize_text_field($filters['product_type']);
			$args['post_type'] = $product_type === 'variation' ? 'product_variation' : 'product';
			if ($product_type === 'variable' || $product_type === 'simple') {
				if (!isset($args['tax_query'])) {
					$args['tax_query'] = [];
				}
				$args['tax_query'][] = [
					'taxonomy' => 'product_type',
					'field' => 'slug',
					'terms' => $product_type
				];
				// If we have multiple tax queries, set relation
				if (count($args['tax_query']) > 1) {
					$args['tax_query']['relation'] = 'AND';
				}
			}
		}

		// Apply sorting
		switch ($sort_by) {
			case 'name':
				$args['orderby'] = 'title';
				$args['order'] = strtoupper($sort_order);
				break;
			case 'rrp':
			case 'sell_price':
			case 'cost':
			case 'margin':
			case 'profit':
			case 'stock':
				// These require post-query sorting
				$args['orderby'] = 'title';
				$args['order'] = 'ASC';
				break;
		}

		$query = new WP_Query($args);
		$product_ids = $query->posts;
		$total_found = $query->found_posts;

		// Process products for current page
		$products = [];

		foreach ($product_ids as $product_id) {
			$product = wc_get_product($product_id);
			if (!$product) continue;

			$product_type = $product->get_type();
			$is_variable_product = ($product_type === 'variable');
			
			$rrp = (float) $product->get_regular_price();
			$sell_price = (float) $product->get_price();

			// Get the actual meta value (if set by user) - using the correct meta key
			$meta_cost_raw = get_post_meta($product_id, '_wpd_ai_product_cost', true);
			
			// Check if meta is actually set (distinguish between 0 and not set)
			$has_meta = metadata_exists('post', $product_id, '_wpd_ai_product_cost');
			$meta_cost = $has_meta ? (is_numeric($meta_cost_raw) ? (float) $meta_cost_raw : null) : null;
			
			// Get the default/fallback cost using wpdai_get_default_cost_price_by_product_id
			$default_cost = wpdai_get_default_cost_price_by_product_id($product_id);
			
			// Use meta if set (even if 0), otherwise use default for calculations
			$cost = $meta_cost !== null ? $meta_cost : (float) $default_cost;
			
			$stock_quantity = (float) $product->get_stock_quantity();
			$stock_status = $product->get_stock_status();

			// Apply stock status filter (post-query)
			if (!empty($filters['stock_status']) && $stock_status !== $filters['stock_status']) {
				$total_found--; // Adjust count
				continue;
			}

			// Apply has_cost filter (post-query - check if custom meta cost is set)
			if (!empty($filters['has_cost'])) {
				$has_custom_cost = $has_meta; // Use metadata_exists result
				if ($filters['has_cost'] === 'yes' && !$has_custom_cost) {
					$total_found--; // Adjust count
					continue;
				}
				if ($filters['has_cost'] === 'no' && $has_custom_cost) {
					$total_found--; // Adjust count
					continue;
				}
			}

			// For variable products, set RRP, sell price, margin, and profit to null (N/A)
			// Cost can still be set, but calculations are not relevant for variable products
			if ( $is_variable_product ) {
				$rrp = null;
				$sell_price = null;
				$margin = null;
				$profit = null;
			} else {
				// Use sell price for margin and profit calculations (actual selling price)
				$margin = $sell_price > 0 ? ( ( $sell_price - $cost ) / $sell_price * 100 ) : 0;
				$profit = $sell_price - $cost;
			}

			$product_data = [
				'id' => $product_id,
				'name' => $product->get_name(),
				'sku' => $product->get_sku() ?: '',
				'type' => $product_type,
				'rrp' => $rrp,
				'sell_price' => $sell_price,
				'cost' => $cost,
				'meta_cost' => $meta_cost, // null if not set, 0 if set to 0, value if set
				'default_cost' => (float) $default_cost, // The default/fallback value
				'default_cost_formatted' => wp_strip_all_tags(wc_price($default_cost)), // Formatted for placeholder
				'margin' => $margin,
				'profit' => $profit,
				'stock_quantity' => $stock_quantity ?: 0,
				'stock_status' => $stock_status,
				'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
				'edit_url' => get_edit_post_link($product->is_type('variation') ? $product->get_parent_id() : $product_id)
			];

			$products[] = $product_data;
		}

		// Calculate stats from ALL products (not just current page)
		$stats = self::calculate_global_stats($filters);

		// Apply custom sorting if needed
		if (in_array($sort_by, ['rrp', 'sell_price', 'cost', 'margin', 'profit', 'stock'])) {
			usort($products, function($a, $b) use ($sort_by, $sort_order) {
				$val_a = $a[$sort_by];
				$val_b = $b[$sort_by];
				
				// Handle null values - treat them as lowest value (will appear last in asc, first in desc)
				if ($val_a === null && $val_b === null) {
					return 0;
				}
				if ($val_a === null) {
					return $sort_order === 'desc' ? -1 : 1; // Null appears last in asc, first in desc
				}
				if ($val_b === null) {
					return $sort_order === 'desc' ? 1 : -1; // Null appears last in asc, first in desc
				}
				
				$comparison = $val_a <=> $val_b;
				return $sort_order === 'desc' ? -$comparison : $comparison;
			});
		}

		// Calculate proper pagination
		$total_pages = max(1, ceil($total_found / $per_page));

		wp_send_json_success([
			'products' => $products,
			'total_items' => $total_found,
			'total_pages' => $total_pages,
			'stats' => $stats
		]);
	}

	/**
	 * Calculate global stats for all products (respects filters)
	 */
	private static function calculate_global_stats($filters = []) {
		// Build query args for ALL products (no pagination)
		$args = [
			'post_type' => ['product', 'product_variation'],
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids'
		];

		// Apply same filters as main query
		if (!empty($filters['search'])) {
			$args['s'] = sanitize_text_field($filters['search']);
		}

		if (!empty($filters['category'])) {
			$args['tax_query'] = [[
				'taxonomy' => 'product_cat',
				'field' => 'term_id',
				'terms' => absint($filters['category'])
			]];
		}

		if (!empty($filters['supplier'])) {
			if (!isset($args['tax_query'])) {
				$args['tax_query'] = [];
			}
			$args['tax_query'][] = [
				'taxonomy' => 'suppliers',
				'field' => 'term_id',
				'terms' => absint($filters['supplier'])
			];
			if (count($args['tax_query']) > 1) {
				$args['tax_query']['relation'] = 'AND';
			}
		}

		if (!empty($filters['product_type'])) {
			$product_type = sanitize_text_field($filters['product_type']);
			$args['post_type'] = $product_type === 'variation' ? 'product_variation' : 'product';
			if ($product_type === 'variable' || $product_type === 'simple') {
				if (!isset($args['tax_query'])) {
					$args['tax_query'] = [];
				}
				$args['tax_query'][] = [
					'taxonomy' => 'product_type',
					'field' => 'slug',
					'terms' => $product_type
				];
				if (count($args['tax_query']) > 1) {
					$args['tax_query']['relation'] = 'AND';
				}
			}
		}

		$query = new WP_Query($args);
		$all_product_ids = $query->posts;

		// Calculate stats
		$stats = [
			'total_products' => 0,
			'products_with_cost' => 0,
			'products_without_cost' => 0,
			'total_stock_value_rrp' => 0,
			'total_stock_value_sell' => 0,
			'total_stock_value_cost' => 0,
			'total_margin' => 0,
			'margin_count' => 0
		];

		foreach ($all_product_ids as $product_id) {
			$product = wc_get_product($product_id);
			if (!$product) continue;

			$product_type = $product->get_type();
			$is_variable_product = ($product_type === 'variable');

			// Check if meta is actually set (distinguish between 0 and not set)
			$has_meta = metadata_exists('post', $product_id, '_wpd_ai_product_cost');
			$meta_cost_raw = get_post_meta($product_id, '_wpd_ai_product_cost', true);
			$meta_cost = $has_meta && is_numeric($meta_cost_raw) ? (float) $meta_cost_raw : null;
			
			$default_cost = wpdai_get_default_cost_price_by_product_id($product_id);
			$cost = $meta_cost !== null ? $meta_cost : (float) $default_cost;
			$stock_status = $product->get_stock_status();
			$stock_quantity = (float) $product->get_stock_quantity();
			$rrp = $is_variable_product ? null : (float) $product->get_regular_price();
			$sell_price = $is_variable_product ? null : (float) $product->get_price();

			// Apply post-query filters for stats
			if (!empty($filters['stock_status']) && $stock_status !== $filters['stock_status']) {
				continue;
			}

			if (!empty($filters['has_cost'])) {
				$has_custom_cost = $has_meta;
				if ($filters['has_cost'] === 'yes' && !$has_custom_cost) continue;
				if ($filters['has_cost'] === 'no' && $has_custom_cost) continue;
			}

			// Count products (check if meta exists, not if value > 0)
			$stats['total_products']++;
			if ($has_meta) {
				$stats['products_with_cost']++;
			} else {
				$stats['products_without_cost']++;
			}

			// Stock values - exclude variable products from RRP/sell calculation
			if ($product->get_manage_stock() && $stock_quantity > 0) {
				if (!$is_variable_product && $rrp !== null) {
					$stats['total_stock_value_rrp'] += $rrp * $stock_quantity;
				}
				if (!$is_variable_product && $sell_price !== null) {
					$stats['total_stock_value_sell'] += $sell_price * $stock_quantity;
				}
				$stats['total_stock_value_cost'] += $cost * $stock_quantity;
			}

			// Margin calculation based on sell price - exclude variable products
			if (!$is_variable_product && $sell_price !== null && $sell_price > 0) {
				$margin = ( ( $sell_price - $cost ) / $sell_price * 100 );
				$stats['total_margin'] += $margin;
				$stats['margin_count']++;
			}
		}

		// Calculate average margin
		$stats['avg_margin'] = $stats['margin_count'] > 0 
			? $stats['total_margin'] / $stats['margin_count'] 
			: 0;

		return $stats;
	}

	/**
	 * AJAX: Update product cost
	 */
	public static function ajax_update_product_cost() {
		check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => 'Insufficient permissions']);
		}

		$product_id = isset($_POST['product_id']) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$cost = isset($_POST['cost']) ? sanitize_text_field( wp_unslash( $_POST['cost'] ) ) : '';

		if (!$product_id) {
			wp_send_json_error(['message' => 'Invalid product ID']);
		}

		// Handle empty vs 0 correctly
		// Empty string = delete meta (no custom cost set)
		// "0" or 0 = valid cost of zero
		if ($cost === '' || $cost === null) {
			// Delete meta - no custom cost set
			delete_post_meta($product_id, '_wpd_ai_product_cost');
			$cost = ''; // Return empty for response
		} else {
			// Use WooCommerce's wc_format_decimal to handle international formats
			// This allows "0" or "0.00" as valid costs
			if (is_numeric(wc_format_decimal($cost))) {
				$cost = wc_format_decimal($cost);
				update_post_meta($product_id, '_wpd_ai_product_cost', $cost);
			} else {
				wp_send_json_error(['message' => 'Invalid cost value']);
			}
		}

		// Clear cache for orders containing this product
		wpdai_delete_order_cache_by_product_ids( [$product_id] );

		// Get updated product data
		$product = wc_get_product($product_id);
		if ($product) {
			$sell_price = (float) $product->get_price();
			// Convert cost to float for calculations (empty string becomes 0)
			$cost_for_calc = $cost === '' ? 0 : (float) $cost;
			$margin = $sell_price > 0 ? ( ( $sell_price - $cost_for_calc ) / $sell_price * 100 ) : 0;
			$profit = $sell_price - $cost_for_calc;

			wp_send_json_success([
				'message' => 'Cost updated successfully',
				'cost' => $cost, // Keep as empty string if deleted
				'margin' => $margin,
				'profit' => $profit
			]);
		} else {
			wp_send_json_error(['message' => 'Product not found']);
		}
	}

	/**
	 * AJAX: Export products to CSV
	 */
	public static function ajax_export_csv() {
		check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_die('Insufficient permissions');
		}

		// Get all products (no pagination for export)
		$filters_raw = isset($_GET['filters']) ? sanitize_textarea_field( wp_unslash( $_GET['filters'] ) ) : '';
		$filters = ! empty( $filters_raw ) ? json_decode( $filters_raw, true ) : [];
		// Sanitize decoded JSON array according to WordPress standards
		if ( ! empty( $filters ) && is_array( $filters ) ) {
			$filters = wpdai_sanitize_json_decoded_array( $filters );
		}
		
		$args = [
			'post_type' => ['product', 'product_variation'],
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'fields' => 'ids'
		];

		if (!empty($filters['search'])) {
			$args['s'] = sanitize_text_field($filters['search']);
		}

		$query = new WP_Query($args);
		$product_ids = $query->posts;

		// Set headers for CSV download
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="cost-of-goods-' . gmdate('Y-m-d') . '.csv"');
		
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Direct output to browser for CSV download is acceptable.
		$output = fopen('php://output', 'w');
		
		// Headers
		fputcsv($output, [
			__( 'Product ID', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Name', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'SKU', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Type', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'RRP', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Sell Price', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Cost of Goods', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Margin %', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Profit', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Stock', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' )
		]);

		// Data rows
		foreach ($product_ids as $product_id) {
			$product = wc_get_product($product_id);
			if (!$product) continue;

			$rrp = (float) $product->get_regular_price();
			$sell_price = (float) $product->get_price();

			// Get meta cost (only if explicitly set)
			$meta_cost = get_post_meta($product_id, '_wpd_ai_product_cost', true);
			$custom_cost = $meta_cost !== '' && $meta_cost !== false ? (float) $meta_cost : null;

			// Get default cost
			$default_cost = (float) wpdai_get_default_cost_price_by_product_id($product_id);

			// Use custom if set, otherwise default for calculations
			$effective_cost = $custom_cost !== null ? $custom_cost : $default_cost;

			// Margin and profit based on sell price
			$margin = $sell_price > 0 ? ( ( $sell_price - $effective_cost ) / $sell_price * 100 ) : 0;
			$profit = $sell_price - $effective_cost;

			fputcsv($output, [
				$product_id,
				$product->get_name(),
				$product->get_sku(),
				$product->get_type(),
				$rrp,
				$sell_price,
				$custom_cost !== null ? $custom_cost : '', // Cost of goods (blank if not set)
				round($margin, 2),
				round($profit, 2),
				$product->get_stock_quantity() ?: 0
			]);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Direct output to browser for CSV download is acceptable.
		fclose($output);
		exit;
	}

	/**
	 * AJAX: Import single product cost from CSV
	 */
	public static function ajax_import_product_cost() {
		check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => 'Insufficient permissions']);
		}

		$identifier = isset($_POST['identifier']) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';
		$identifier_type = isset($_POST['identifier_type']) ? sanitize_text_field( wp_unslash( $_POST['identifier_type'] ) ) : 'sku';
		$cost = isset($_POST['cost']) ? sanitize_text_field( wp_unslash( $_POST['cost'] ) ) : '';

		if (!$identifier) {
			wp_send_json_error(['message' => 'Missing product identifier']);
		}

		// Find product by identifier
		$product_id = null;

		if ($identifier_type === 'id') {
			$product_id = absint($identifier);
			$product = wc_get_product($product_id);
			if (!$product) {
				wp_send_json_error(['message' => 'Product not found with ID: ' . $identifier]);
			}
		} else {
			// Find by SKU
			$product_id = wc_get_product_id_by_sku($identifier);
			if (!$product_id) {
				wp_send_json_error(['message' => 'Product not found with SKU: ' . $identifier]);
			}
		}

		// Handle empty vs 0 correctly
		if ($cost === '' || $cost === null) {
			// Delete meta - no custom cost set
			delete_post_meta($product_id, '_wpd_ai_product_cost');
			$cost = '';
		} else {
			// Use WooCommerce's wc_format_decimal to handle international formats
			if (is_numeric(wc_format_decimal($cost))) {
				$cost = wc_format_decimal($cost);
				update_post_meta($product_id, '_wpd_ai_product_cost', $cost);
			} else {
				wp_send_json_error(['message' => 'Invalid cost value: ' . $cost]);
			}
		}

		// Clear cache for orders containing this product
		wpdai_delete_order_cache_by_product_ids( [$product_id] );

		wp_send_json_success([
			'message' => 'Cost updated successfully',
			'product_id' => $product_id,
			'identifier' => $identifier,
			'cost' => $cost
		]);
	}

	/**
	 * Get list of popular COGS plugins and their meta keys
	 */
	private static function get_cogs_plugin_options() {
		return [
			'_wc_cog_cost' => 'WooCommerce Cost of Goods (SkyVerge)',
			'_cogs_total_value' => 'WooCommerce 10.0+ (Native COGS)',
			'_atum_purchase_price' => 'ATUM Inventory Management',
			'_alg_wc_cog_cost' => 'Cost of Goods for WooCommerce (Algoritmika)',
			'_yith_cog_cost' => 'YITH Cost of Goods',
			'_wc_cost_of_good' => 'WC Cost of Goods (Various)',
			'_purchase_price' => 'Purchase Price (Generic)',
		];
	}

	/**
	 * AJAX: Get count of products with source meta key
	 */
	public static function ajax_get_migration_count() {
		check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => 'Insufficient permissions']);
		}

		$source_meta_key = isset($_POST['source_meta_key']) ? sanitize_text_field( wp_unslash( $_POST['source_meta_key'] ) ) : '';

		if (!$source_meta_key) {
			wp_send_json_error(['message' => 'Source meta key is required']);
		}

		global $wpdb;

		// Count products with the source meta key
		$count = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(DISTINCT pm.post_id) 
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE pm.meta_key = %s 
			AND pm.meta_value != ''
			AND p.post_type IN ('product', 'product_variation')
			AND p.post_status = 'publish'",
			$source_meta_key
		));

		// Get sample products
		$sample_products = $wpdb->get_results($wpdb->prepare(
			"SELECT p.ID, p.post_title, pm.meta_value as cost
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE pm.meta_key = %s 
			AND pm.meta_value != ''
			AND p.post_type IN ('product', 'product_variation')
			AND p.post_status = 'publish'
			LIMIT 5",
			$source_meta_key
		), ARRAY_A);

		wp_send_json_success([
			'count' => absint($count),
			'source_meta_key' => $source_meta_key,
			'sample_products' => $sample_products
		]);
	}

	/**
	 * AJAX: Get available meta keys for custom migration
	 */
	public static function ajax_get_available_meta_keys() {
		check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => 'Insufficient permissions']);
		}

		global $wpdb;

		// Get all unique meta keys from product and product_variation posts
		// Exclude our own meta key and common WooCommerce meta keys that aren't cost-related
		$excluded_keys = [
			'_wpd_ai_product_cost',
			'_regular_price',
			'_sale_price',
			'_price',
			'_sku',
			'_weight',
			'_length',
			'_width',
			'_height',
			'_stock',
			'_stock_status',
			'_manage_stock',
			'_backorders',
			'_visibility',
			'_featured',
			'_virtual',
			'_downloadable',
			'_product_image_gallery',
			'_product_attributes',
			'_default_attributes',
			'_product_version',
			'_edit_lock',
			'_edit_last',
			'_wp_old_slug',
			'_wp_page_template',
			'_thumbnail_id',
			'_product_type',
			'_tax_status',
			'_tax_class',
			'_purchase_note',
			'_sold_individually',
			'_reviews_allowed',
			'_menu_order',
			'_variation_description',
			'_variation_menu_order',
			'_max_price_variation_id',
			'_min_price_variation_id',
			'_max_regular_price_variation_id',
			'_min_regular_price_variation_id',
			'_max_sale_price_variation_id',
			'_min_sale_price_variation_id',
		];

		// Build placeholder string for NOT IN clause
		$placeholders = implode(',', array_fill(0, count($excluded_keys), '%s'));

		// Get all unique meta keys from products/variations
		// We'll filter for numeric values in PHP for better compatibility
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The query is dynamically built with placeholders and then prepared.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are from trusted source.
		$meta_keys_raw = $wpdb->get_results($wpdb->prepare(
			"SELECT 
				pm.meta_key,
				pm.meta_value,
				pm.post_id
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.post_type IN ('product', 'product_variation')
			AND p.post_status = 'publish'
			AND pm.meta_key NOT IN ($placeholders)
			AND pm.meta_value != ''
			AND pm.meta_value IS NOT NULL
			ORDER BY pm.meta_key ASC",
			$excluded_keys
		), ARRAY_A);

		// Group by meta_key and count, filtering for numeric values
		$meta_keys_grouped = [];
		foreach ($meta_keys_raw as $row) {
			$meta_key = $row['meta_key'];
			$meta_value = $row['meta_value'];
			
			// Check if value is numeric (supports decimal and comma separators)
			$clean_value = str_replace(',', '.', $meta_value);
			if (is_numeric($clean_value) && floatval($clean_value) >= 0) {
				if (!isset($meta_keys_grouped[$meta_key])) {
					$meta_keys_grouped[$meta_key] = [
						'meta_key' => $meta_key,
						'count' => 0
					];
				}
				$meta_keys_grouped[$meta_key]['count']++;
			}
		}

		// Sort by count descending, then by meta_key
		usort($meta_keys_grouped, function($a, $b) {
			if ($a['count'] === $b['count']) {
				return strcmp($a['meta_key'], $b['meta_key']);
			}
			return $b['count'] - $a['count'];
		});

		// Limit to top 100
		$formatted_meta_keys = array_slice($meta_keys_grouped, 0, 100);

		wp_send_json_success([
			'meta_keys' => $formatted_meta_keys
		]);
	}

	/**
	 * AJAX: Migrate COGS data from another plugin
	 */
	public static function ajax_migrate_cogs_data() {
		check_ajax_referer(WPD_AI_AJAX_NONCE_ACTION, 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => 'Insufficient permissions']);
		}

		$source_meta_key = isset($_POST['source_meta_key']) ? sanitize_text_field( wp_unslash( $_POST['source_meta_key'] ) ) : '';
		$overwrite = isset($_POST['overwrite']) ? filter_var( wp_unslash( $_POST['overwrite'] ), FILTER_VALIDATE_BOOLEAN) : false;

		if (!$source_meta_key) {
			wp_send_json_error(['message' => 'Source meta key is required']);
		}

		// Prevent migrating from our own meta key
		if ($source_meta_key === '_wpd_ai_product_cost') {
			wp_send_json_error(['message' => 'Cannot migrate from the same meta key']);
		}

		global $wpdb;

		// Get all products with the source meta key
		$products_to_migrate = $wpdb->get_results($wpdb->prepare(
			"SELECT pm.post_id, pm.meta_value as cost
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE pm.meta_key = %s 
			AND pm.meta_value != ''
			AND p.post_type IN ('product', 'product_variation')
			AND p.post_status = 'publish'",
			$source_meta_key
		), ARRAY_A);

		$migrated_count = 0;
		$skipped_count = 0;
		$errors = [];
		$updated_product_ids = [];

		foreach ($products_to_migrate as $product_data) {
			$product_id = absint($product_data['post_id']);
			$cost_value = $product_data['cost'];

			// Check if product already has our cost set
			$existing_cost = get_post_meta($product_id, '_wpd_ai_product_cost', true);

			if (!$overwrite && $existing_cost !== '' && $existing_cost !== false) {
				$skipped_count++;
				continue;
			}

			// Format the cost value
			$formatted_cost = wc_format_decimal($cost_value);

			if (is_numeric($formatted_cost)) {
				$result = update_post_meta($product_id, '_wpd_ai_product_cost', $formatted_cost);
				if ($result !== false) {
					$migrated_count++;
					$updated_product_ids[] = $product_id;
				} else {
					$errors[] = "Failed to update product ID: {$product_id}";
				}
			} else {
				$errors[] = "Invalid cost value for product ID: {$product_id}";
			}
		}

		// Clear cache for orders containing the updated products
		if (!empty($updated_product_ids)) {
			wpdai_delete_order_cache_by_product_ids( $updated_product_ids );
		}

		wp_send_json_success([
			'message' => 'Migration completed successfully',
			'migrated_count' => $migrated_count,
			'skipped_count' => $skipped_count,
			'total_found' => count($products_to_migrate),
			'errors' => $errors
		]);
	}
}

