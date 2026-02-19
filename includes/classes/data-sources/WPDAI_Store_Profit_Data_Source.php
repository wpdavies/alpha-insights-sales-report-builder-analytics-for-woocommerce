<?php
/**
 * Store Profit Data Source
 *
 * Provides store_profit entity data for the Alpha Insights data warehouse.
 * Computed from orders + expenses (and refunds when available).
 *
 * @package Alpha Insights
 * @since 5.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

/**
 * Store profit data source class
 *
 * Fetches orders and expenses via the warehouse, then computes store profit
 * and profit/loss statement data. Use fetch_data() as the single entry point.
 *
 * @since 5.0.0
 */
class WPDAI_Store_Profit_Data_Source extends WPDAI_Custom_Data_Source_Base {

	/**
	 * Entity names this data source provides
	 *
	 * @since 5.0.0
	 * @var array<string>
	 */
	protected $entity_names = array( 'store_profit' );

	/**
	 * Fetch store profit data
	 *
	 * Depends on orders and expenses (and optionally refunds). Gets them via
	 * $data_warehouse->fetch_data() then computes profit and P&L statement.
	 *
	 * @since 5.0.0
	 * @param WPDAI_Data_Warehouse $data_warehouse The data warehouse instance (required).
	 * @return array Single-entity structure: totals, categorized_data, data_table, data_by_date, total_db_records.
	 */
	public function fetch_data( WPDAI_Data_Warehouse $data_warehouse ) {

		$deps = $data_warehouse->fetch_data( array( 'orders', 'expenses', 'refunds' ) );
		$orders_data  = isset( $deps['orders'] ) ? $deps['orders'] : $data_warehouse->get_data( 'orders' );
		$expense_data  = isset( $deps['expenses'] ) ? $deps['expenses'] : $data_warehouse->get_data( 'expenses' );
		$refunds_totals = isset( $deps['refunds']['totals'] ) ? $deps['refunds']['totals'] : $data_warehouse->get_data( 'refunds', 'totals' );
		$total_refund_amount = is_array( $refunds_totals ) && isset( $refunds_totals['total_refund_amount'] ) ? $refunds_totals['total_refund_amount'] : 0;

		$totals = array(
			'total_store_profit'                  => 0,
			'average_store_margin'                => 0,
			'daily_average_store_profit'          => 0,
			'expense_percentage_of_order_profit'  => 0,
		);
		$categorized_data = array(
			'profit_loss_statement_data' => array(
				'gross_revenue_including_sales_tax_and_refunds' => 0,
				'sales_tax' => 0,
				'refunds' => 0,
				'net_revenue' => 0,
				'cost_of_goods_sold' => 0,
				'shipping_expenses' => 0,
				'payment_gateway_costs' => 0,
				'custom_order_cost_data' => array(),
				'total_cost_of_sales' => 0,
				'gross_profit' => 0,
				'gross_profit_percentage' => 0.00,
				'operating_expense_breakdown' => array(),
				'total_operating_expenses' => 0,
				'net_profit_before_income_tax' => 0,
				'net_profit_percentage' => 0.00,
			),
		);
		$data_table      = array();
		$total_db_records = 0;
		$data_by_date    = array(
			'store_profit_by_date' => $data_warehouse->get_data_by_date_range_container(),
		);

		$n_days_period = $data_warehouse->get_n_days_range();

		// Daily store profit from order profit and expenses by date.
		foreach ( $data_by_date['store_profit_by_date'] as $date_key => $data_array ) {
			$order_profit_by_date_key  = isset( $orders_data['data_by_date']['profit_by_date'][ $date_key ] ) ? $orders_data['data_by_date']['profit_by_date'][ $date_key ] : 0;
			$store_expenses_by_date_key = isset( $expense_data['data_by_date']['amount_paid_by_date'][ $date_key ] ) ? $expense_data['data_by_date']['amount_paid_by_date'][ $date_key ] : 0;
			$data_by_date['store_profit_by_date'][ $date_key ] = $order_profit_by_date_key - $store_expenses_by_date_key;
		}

		$totals['total_store_profit']                 = ( isset( $orders_data['totals']['total_order_profit'] ) ? $orders_data['totals']['total_order_profit'] : 0 ) - ( isset( $expense_data['totals']['total_amount_paid'] ) ? $expense_data['totals']['total_amount_paid'] : 0 );
		$totals['average_store_margin']               = wpdai_calculate_percentage( $totals['total_store_profit'], isset( $orders_data['totals']['total_order_revenue_ex_tax'] ) ? $orders_data['totals']['total_order_revenue_ex_tax'] : 0 );
		$totals['daily_average_store_profit']         = wpdai_divide( $totals['total_store_profit'], $n_days_period );
		$totals['expense_percentage_of_order_profit'] = wpdai_calculate_percentage( isset( $expense_data['totals']['total_amount_paid'] ) ? $expense_data['totals']['total_amount_paid'] : 0, isset( $orders_data['totals']['total_order_profit'] ) ? $orders_data['totals']['total_order_profit'] : 0 );

		$parent_expense_array = array();
		if ( ! empty( $expense_data['categorized_data']['parent_expense_type_categories'] ) && is_array( $expense_data['categorized_data']['parent_expense_type_categories'] ) ) {
			foreach ( $expense_data['categorized_data']['parent_expense_type_categories'] as $parent_expense_slug => $parent_expense ) {
				$parent_expense_array[ wpdai_clean_string( $parent_expense_slug ) ] = isset( $parent_expense['total_amount_paid'] ) ? $parent_expense['total_amount_paid'] : 0;
			}
		}

		$custom_order_cost_array = array();
		if ( ! empty( $orders_data['categorized_data']['custom_order_cost_data'] ) && is_array( $orders_data['categorized_data']['custom_order_cost_data'] ) ) {
			foreach ( $orders_data['categorized_data']['custom_order_cost_data'] as $custom_order_cost_slug => $custom_order_cost ) {
				$custom_order_cost_array[ wpdai_clean_string( $custom_order_cost_slug ) ] = $custom_order_cost;
			}
		}

		$categorized_data['profit_loss_statement_data']['gross_revenue_including_sales_tax_and_refunds'] = isset( $orders_data['totals']['total_order_revenue_inc_tax_and_refunds'] ) ? $orders_data['totals']['total_order_revenue_inc_tax_and_refunds'] : 0;
		$categorized_data['profit_loss_statement_data']['sales_tax']                                   = isset( $orders_data['totals']['total_order_tax'] ) ? $orders_data['totals']['total_order_tax'] : 0;
		$categorized_data['profit_loss_statement_data']['refunds']                                     = $total_refund_amount;
		$categorized_data['profit_loss_statement_data']['net_revenue']                                 = isset( $orders_data['totals']['total_order_revenue_ex_tax'] ) ? $orders_data['totals']['total_order_revenue_ex_tax'] : 0;
		$categorized_data['profit_loss_statement_data']['cost_of_goods_sold']                           = isset( $orders_data['totals']['total_product_cost_of_goods'] ) ? $orders_data['totals']['total_product_cost_of_goods'] : 0;
		$categorized_data['profit_loss_statement_data']['shipping_expenses']                            = isset( $orders_data['totals']['total_freight_cost'] ) ? $orders_data['totals']['total_freight_cost'] : 0;
		$categorized_data['profit_loss_statement_data']['payment_gateway_costs']                         = isset( $orders_data['totals']['total_payment_gateway_costs'] ) ? $orders_data['totals']['total_payment_gateway_costs'] : 0;
		$categorized_data['profit_loss_statement_data']['custom_order_cost_data']                      = $custom_order_cost_array;
		$categorized_data['profit_loss_statement_data']['total_cost_of_sales']                          = isset( $orders_data['totals']['total_order_cost'] ) ? $orders_data['totals']['total_order_cost'] : 0;
		$categorized_data['profit_loss_statement_data']['gross_profit']                                  = isset( $orders_data['totals']['total_order_profit'] ) ? $orders_data['totals']['total_order_profit'] : 0;
		$categorized_data['profit_loss_statement_data']['gross_profit_percentage']                      = isset( $orders_data['totals']['average_order_margin'] ) ? $orders_data['totals']['average_order_margin'] : 0.00;
		$categorized_data['profit_loss_statement_data']['operating_expense_breakdown']                  = $parent_expense_array;
		$categorized_data['profit_loss_statement_data']['total_operating_expenses']                     = isset( $expense_data['totals']['total_amount_paid'] ) ? $expense_data['totals']['total_amount_paid'] : 0;
		$categorized_data['profit_loss_statement_data']['net_profit_before_income_tax']                = $totals['total_store_profit'];
		$categorized_data['profit_loss_statement_data']['net_profit_percentage']                         = $totals['average_store_margin'];

		$data_by_date = $data_warehouse->maybe_create_no_data_found_date_array( $data_by_date );

		// Return single-entity structure for the warehouse to store (do not call set_data here).
		$store_profit_data = array(
			'totals'            => $totals,
			'categorized_data'  => $categorized_data,
			'data_table'        => array( 'store_profit' => $data_table ),
			'data_by_date'      => $data_by_date,
			'total_db_records'  => $total_db_records,
		);

		return apply_filters( 'wpd_alpha_insights_data_source_store_profit', $store_profit_data, $data_warehouse );
	}
}

// Self-register when file is loaded.
new WPDAI_Store_Profit_Data_Source();
