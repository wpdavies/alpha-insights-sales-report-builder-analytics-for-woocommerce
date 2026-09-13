<?php
/**
 * Experiments report data source.
 *
 * @package Alpha Insights
 * @since 5.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPDAI_Experiments_Data_Source
 */
class WPDAI_Experiments_Data_Source extends WPDAI_Custom_Data_Source_Base {

	/**
	 * Entity names.
	 *
	 * @var array
	 */
	protected $entity_names = array( 'experiments' );

	/**
	 * Fetch experiment stats.
	 *
	 * @param WPDAI_Data_Warehouse $data_warehouse Warehouse.
	 * @return array
	 */
	public function fetch_data( WPDAI_Data_Warehouse $data_warehouse ) {
		global $wpdb;

		$date_from = $data_warehouse->get_date_from( 'Y-m-d' );
		$date_to   = $data_warehouse->get_date_to( 'Y-m-d' );
		$from_gmt  = get_gmt_from_date( $date_from . ' 00:00:00' );
		$to_gmt    = get_gmt_from_date( $date_to . ' 23:59:59' );

		$container = $data_warehouse->get_data_by_date_range_container();
		if ( ! is_array( $container ) ) {
			$container = array();
		}

		if ( ! class_exists( 'WPDAI_Experiment_Store' ) || ! WPDAI_Experiment_Store::tables_exist() ) {
			return $this->empty_payload( $container );
		}

		$assign_table  = WPDAI_Experiment_Store::assignments_table();
		$exp_table     = WPDAI_Experiment_Store::experiments_table();
		$var_table     = WPDAI_Experiment_Store::variants_table();
		$db            = new WPDAI_Database_Interactor();
		$events_table   = $db->events_table;
		$session_table  = $db->session_data_table;
		$experiment_ids = $this->get_filtered_experiment_ids( $data_warehouse );

		$sql = "SELECT a.*, e.name AS experiment_name, e.slug AS experiment_slug, e.goals AS experiment_goals, v.name AS variant_name, v.is_control
				FROM {$assign_table} a
				INNER JOIN {$exp_table} e ON e.id = a.experiment_id
				LEFT JOIN {$var_table} v ON v.experiment_id = a.experiment_id AND v.variant_key = a.variant_key
				WHERE a.assigned_gmt >= %s AND a.assigned_gmt <= %s";
		$args = array( $from_gmt, $to_gmt );
		if ( ! empty( $experiment_ids ) ) {
			$sql   .= ' AND a.experiment_id IN (' . implode( ',', array_fill( 0, count( $experiment_ids ), '%d' ) ) . ')';
			$args   = array_merge( $args, $experiment_ids );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are prefix-based and trusted; placeholders counted from arrays.
		$assignments = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );

		if ( ! is_array( $assignments ) ) {
			$assignments = array();
		}

		$session_ids = array();
		$visitor_ids = array();
		foreach ( $assignments as $row ) {
			if ( ! empty( $row['session_id'] ) ) {
				$session_ids[] = $row['session_id'];
			}
			if ( ! empty( $row['visitor_id'] ) ) {
				$visitor_ids[] = $row['visitor_id'];
			}
		}
		$session_ids = array_values( array_unique( $session_ids ) );
		$visitor_ids = array_values( array_unique( $visitor_ids ) );

		$session_analytics      = array();
		$conversions_by_session = array();
		$session_durations      = array();
		if ( ! empty( $session_ids ) ) {
			$event_rows             = $this->get_events_for_sessions( $events_table, $session_ids, array(), $from_gmt, $to_gmt );
			$indexed                = $this->index_session_events( $event_rows );
			$session_analytics      = $indexed['analytics'];
			$conversions_by_session = $indexed['conversions'];
			$session_durations      = $this->get_session_durations( $session_table, $session_ids );
		}

		$purchase_by_visitor = array();
		if ( ! empty( $visitor_ids ) ) {
			$purchase_by_visitor = $this->get_purchases_by_visitor( $visitor_ids );
		}

		$profit_by_session = array();
		if ( wpdai_experiments_is_pro() && ! empty( $session_ids ) ) {
			$profit_by_session = $this->get_profit_by_session( $session_ids );
		}

		$analytics_zero = $this->empty_analytics_counters();
		$totals         = array_merge(
			array(
				'eligible'               => 0,
				'assigned'               => 0,
				'exposed'                => 0,
				'holdout'                => 0,
				'conversions'            => 0,
				'conversion_rate'        => 0,
				'revenue'                => 0,
				'revenue_per_exposure'   => 0,
				'aov'                    => 0,
				'profit'                 => 0,
				'average_profit'         => 0,
				'sessions_with_duration' => 0,
			),
			$analytics_zero
		);

		$variant_rows            = array();
		$daily_arms              = array();
		$seen_purchase_visitors  = array();
		$data_by_date     = array(
			'exposures_by_date'                   => $container,
			'conversions_by_date'                 => $container,
			'exposures_by_experiment_by_date'     => array(),
			'lift_by_variant_by_date'             => array(),
			'conversion_rate_by_variant_by_date'  => array(),
		);

		foreach ( $assignments as $row ) {
			$eligible = ! empty( $row['eligible'] );
			$holdout  = ! empty( $row['holdout'] );
			$exposed  = ! empty( $row['exposed_gmt'] );
			$key      = (int) $row['experiment_id'] . ':' . ( $row['variant_key'] ? $row['variant_key'] : 'holdout' );

			if ( ! isset( $variant_rows[ $key ] ) ) {
				$variant_rows[ $key ] = array_merge(
					array(
						'experiment_id'           => (int) $row['experiment_id'],
						'experiment_name'         => $row['experiment_name'],
						'experiment_slug'         => $row['experiment_slug'],
						'variant_key'             => $row['variant_key'],
						'variant_name'            => $row['variant_name'] ? $row['variant_name'] : __( 'Holdout', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
						'is_control'              => ! empty( $row['is_control'] ) ? 1 : 0,
						'eligible'                => 0,
						'assigned'                => 0,
						'exposures'               => 0,
						'holdout'                 => 0,
						'conversions'             => 0,
						'conversion_rate'         => 0,
						'revenue'                 => 0,
						'revenue_per_exposure'    => 0,
						'aov'                     => 0,
						'profit'                  => 0,
						'lift'                    => 0,
						'_seen_sessions'          => array(),
						'_sessions_with_duration' => 0,
					),
					$analytics_zero
				);
			}

			if ( $eligible ) {
				++$totals['eligible'];
				++$variant_rows[ $key ]['eligible'];
			}
			if ( $holdout ) {
				++$totals['holdout'];
				++$variant_rows[ $key ]['holdout'];
			} elseif ( $eligible ) {
				++$totals['assigned'];
				++$variant_rows[ $key ]['assigned'];
			}
			if ( $exposed && ! $holdout ) {
				++$totals['exposed'];
				++$variant_rows[ $key ]['exposures'];
			}

			$goal_type = 'transaction';
			$goals     = json_decode( $row['experiment_goals'], true );
			if ( is_array( $goals ) && ! empty( $goals['primary']['type'] ) ) {
				$goal_type = $goals['primary']['type'];
			}
			if ( ! wpdai_experiments_is_pro() ) {
				$goal_type = 'transaction';
			}
			// Value goals still convert on a purchase; lift is calculated from revenue / AOV.
			if ( in_array( $goal_type, array( 'purchase_value', 'revenue_per_exposure', 'aov' ), true ) ) {
				$goal_type = 'transaction';
			}

			$converted = false;
			$revenue   = 0;
			if ( ! empty( $row['session_id'] ) && isset( $conversions_by_session[ $row['session_id'] ] ) ) {
				$session_events = $conversions_by_session[ $row['session_id'] ];
				$event_key      = 'transaction';
				if ( 'add_to_cart' === $goal_type ) {
					$event_key = 'add_to_cart';
				} elseif ( 'page_view' === $goal_type ) {
					$event_key = 'page_view';
				} elseif ( 'event' === $goal_type && is_array( $goals ) && ! empty( $goals['primary']['event_type'] ) ) {
					$event_key = $goals['primary']['event_type'];
				}
				if ( isset( $session_events[ $event_key ] ) && $exposed && ! $holdout ) {
					$converted = true;
					$revenue   = (float) $session_events[ $event_key ]['revenue'];
				}
			}

			if ( ! $converted && 'transaction' === $goal_type && $exposed && ! $holdout && ! empty( $row['visitor_id'] ) && isset( $purchase_by_visitor[ $row['visitor_id'] ] ) ) {
				$converted = true;
				$revenue   = (float) $purchase_by_visitor[ $row['visitor_id'] ]['revenue'];
			}

			$local_date = '';
			if ( ! empty( $row['assigned_gmt'] ) ) {
				$local_date = $data_warehouse->reformat_date_to_date_format( get_date_from_gmt( $row['assigned_gmt'] ) );
			}

			$exp_label = ! empty( $row['experiment_name'] ) ? $row['experiment_name'] : __( 'Untitled experiment', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );

			if ( $converted ) {
				++$totals['conversions'];
				++$variant_rows[ $key ]['conversions'];
				$totals['revenue']                 += $revenue;
				$variant_rows[ $key ]['revenue']   += $revenue;
				if ( $local_date && isset( $data_by_date['conversions_by_date'][ $local_date ] ) ) {
					$data_by_date['conversions_by_date'][ $local_date ]++;
				}
				$this->increment_daily_arm_metric( $daily_arms, $row, $container, $local_date, 'conversions' );
			}

			if ( $exposed && ! $holdout && ! empty( $row['session_id'] ) ) {
				$sid = $row['session_id'];
				if ( ! isset( $variant_rows[ $key ]['_seen_sessions'][ $sid ] ) ) {
					$variant_rows[ $key ]['_seen_sessions'][ $sid ] = 1;
					++$totals['sessions'];
					$metrics = isset( $session_analytics[ $sid ] ) ? $session_analytics[ $sid ] : $this->empty_analytics_counters();
					if ( $local_date ) {
						$daily_metrics = $metrics;
						$visitor_id    = ! empty( $row['visitor_id'] ) ? $row['visitor_id'] : '';
						$purchase_key  = $key . ':' . $visitor_id;
						if (
							(int) $daily_metrics['transactions'] < 1
							&& '' !== $visitor_id
							&& isset( $purchase_by_visitor[ $visitor_id ] )
							&& empty( $seen_purchase_visitors[ $purchase_key ] )
						) {
							$seen_purchase_visitors[ $purchase_key ] = 1;
							$daily_metrics['transactions']           = 1;
							$daily_metrics['transaction_value']     += (float) $purchase_by_visitor[ $visitor_id ]['revenue'];
						}
						$this->increment_daily_arm_metric( $daily_arms, $row, $container, $local_date, 'add_to_carts', (int) $daily_metrics['add_to_carts'] );
						$this->increment_daily_arm_metric( $daily_arms, $row, $container, $local_date, 'initiate_checkouts', (int) $daily_metrics['initiate_checkouts'] );
						$this->increment_daily_arm_metric( $daily_arms, $row, $container, $local_date, 'transactions', (int) $daily_metrics['transactions'] );
						$this->increment_daily_arm_metric( $daily_arms, $row, $container, $local_date, 'revenue', (float) $daily_metrics['transaction_value'] );
					}
					foreach ( $this->session_metric_sum_keys() as $metric_key ) {
						$val = isset( $metrics[ $metric_key ] ) ? $metrics[ $metric_key ] : 0;
						$variant_rows[ $key ][ $metric_key ] += $val;
						$totals[ $metric_key ]               += $val;
					}
					if ( isset( $session_durations[ $sid ] ) ) {
						$dur = max( 0, (int) $session_durations[ $sid ] );
						$variant_rows[ $key ]['total_session_duration'] += $dur;
						$totals['total_session_duration']               += $dur;
						++$variant_rows[ $key ]['_sessions_with_duration'];
						++$totals['sessions_with_duration'];
					}
				}
			}

			if ( $exposed && ! $holdout && $local_date && isset( $data_by_date['exposures_by_date'][ $local_date ] ) ) {
				$data_by_date['exposures_by_date'][ $local_date ]++;
				$this->increment_category_date_series( $data_by_date['exposures_by_experiment_by_date'], $exp_label, $local_date, $container );
				$this->increment_daily_arm_metric( $daily_arms, $row, $container, $local_date, 'exposures' );
			}

			if ( $exposed && ! $holdout && ! empty( $row['session_id'] ) && isset( $profit_by_session[ $row['session_id'] ] ) ) {
				$variant_rows[ $key ]['profit'] += $profit_by_session[ $row['session_id'] ];
				$totals['profit']               += $profit_by_session[ $row['session_id'] ];
			} elseif ( $exposed && ! $holdout && ! empty( $row['visitor_id'] ) && isset( $purchase_by_visitor[ $row['visitor_id'] ]['profit'] ) ) {
				$variant_rows[ $key ]['profit'] += $purchase_by_visitor[ $row['visitor_id'] ]['profit'];
				$totals['profit']               += $purchase_by_visitor[ $row['visitor_id'] ]['profit'];
			}
		}

		$data_by_date['lift_by_variant_by_date']            = $this->build_daily_lift_series( $daily_arms, $container );
		$data_by_date['conversion_rate_by_variant_by_date'] = $this->build_daily_conversion_rate_series( $daily_arms, $container );
		$progress = $data_warehouse->get_filter( 'experiments_progress' )
			? $this->build_progress_payload( $daily_arms, $container )
			: array();

		$totals['conversion_rate']          = wpdai_calculate_percentage( $totals['conversions'], $totals['exposed'] );
		$totals['aov']                      = $totals['conversions'] > 0 ? round( $totals['revenue'] / $totals['conversions'], 2 ) : 0;
		$totals['revenue_per_exposure']     = wpdai_divide( $totals['revenue'], $totals['exposed'], 2 );
		$totals['average_profit']           = $totals['conversions'] > 0 ? round( $totals['profit'] / $totals['conversions'], 2 ) : 0;
		$totals['page_views_per_session']   = wpdai_divide( $totals['page_views'], $totals['sessions'], 2 );
		$totals['average_session_duration'] = wpdai_divide( $totals['total_session_duration'], $totals['sessions_with_duration'], 2 );
		$this->apply_funnel_rates( $totals, $totals['exposed'] );

		$control_rates = array();
		foreach ( $variant_rows as $row ) {
			if ( ! empty( $row['is_control'] ) ) {
				$control_rates[ (int) $row['experiment_id'] ] = $row['exposures'] > 0 ? ( $row['conversions'] / $row['exposures'] ) : 0;
			}
		}

		foreach ( $variant_rows as $key => $row ) {
			$session_count = isset( $row['_seen_sessions'] ) && is_array( $row['_seen_sessions'] ) ? count( $row['_seen_sessions'] ) : 0;
			$duration_n    = isset( $row['_sessions_with_duration'] ) ? (int) $row['_sessions_with_duration'] : 0;
			$cr            = $row['exposures'] > 0 ? wpdai_calculate_percentage( $row['conversions'], $row['exposures'] ) : 0;
			$variant_rows[ $key ]['sessions']                 = $session_count;
			$variant_rows[ $key ]['conversion_rate']          = $cr;
			$variant_rows[ $key ]['aov']                      = $row['conversions'] > 0 ? round( $row['revenue'] / $row['conversions'], 2 ) : 0;
			$variant_rows[ $key ]['revenue_per_exposure']     = wpdai_divide( $row['revenue'], $row['exposures'], 2 );
			$variant_rows[ $key ]['page_views_per_session']   = wpdai_divide( $row['page_views'], $session_count, 2 );
			$variant_rows[ $key ]['average_session_duration'] = wpdai_divide( $row['total_session_duration'], $duration_n, 2 );
			$this->apply_funnel_rates( $variant_rows[ $key ], $row['exposures'] );
			$eid = (int) $row['experiment_id'];
			if ( empty( $row['is_control'] ) && isset( $control_rates[ $eid ] ) && $control_rates[ $eid ] > 0 ) {
				$variant_cr = $row['exposures'] > 0 ? ( $row['conversions'] / $row['exposures'] ) : 0;
				$variant_rows[ $key ]['lift'] = round( ( ( $variant_cr - $control_rates[ $eid ] ) / $control_rates[ $eid ] ) * 100, 2 );
			}
			unset( $variant_rows[ $key ]['_seen_sessions'], $variant_rows[ $key ]['_sessions_with_duration'], $variant_rows[ $key ]['page_views'] );
		}

		$variant_rows = apply_filters( 'wpd_ai_experiments_variant_stats', array_values( $variant_rows ), $data_warehouse );

		$categorized = array();
		foreach ( $variant_rows as $row ) {
			$label                 = trim( ( $row['experiment_name'] ?? '' ) . ' — ' . ( $row['variant_name'] ?? '' ) );
			$categorized[ $label ] = $row;
		}

		$is_pro = wpdai_experiments_is_pro();
		if ( ! $is_pro ) {
			unset( $totals['revenue'], $totals['revenue_per_exposure'], $totals['aov'], $totals['profit'], $totals['average_profit'] );
			foreach ( $variant_rows as $i => $row ) {
				unset( $variant_rows[ $i ]['revenue'], $variant_rows[ $i ]['revenue_per_exposure'], $variant_rows[ $i ]['aov'], $variant_rows[ $i ]['profit'] );
			}
		}
		unset( $totals['sessions_with_duration'], $totals['page_views'] );

		$data_table = array(
			'variants' => $variant_rows,
		);
		if ( ! empty( $progress ) ) {
			$data_table['progress'] = $progress;
		}

		$payload = array(
			'totals'           => $totals,
			'categorized_data' => array(
				'variants' => $categorized,
			),
			'data_by_date'     => $data_by_date,
			'data_table'       => $data_table,
			'total_db_records' => count( $assignments ),
		);
		if ( ! empty( $progress ) ) {
			$payload['progress'] = $progress;
		}

		return $payload;
	}

	/**
	 * Empty report payload when tables are missing.
	 *
	 * @param array $container Date container.
	 * @return array
	 */
	protected function empty_payload( $container ) {
		return array(
			'totals'           => array_merge(
				array(
					'eligible'        => 0,
					'assigned'        => 0,
					'exposed'         => 0,
					'holdout'         => 0,
					'conversions'     => 0,
					'conversion_rate' => 0,
				),
				$this->empty_analytics_counters()
			),
			'categorized_data' => array(
				'variants' => array(),
			),
			'data_by_date'     => array(
				'exposures_by_date'                  => $container,
				'conversions_by_date'                => $container,
				'exposures_by_experiment_by_date'    => array(),
				'lift_by_variant_by_date'            => array(),
				'conversion_rate_by_variant_by_date' => array(),
			),
			'data_table'       => array(
				'variants' => array(),
			),
			'total_db_records' => 0,
		);
	}

	/**
	 * Selected experiment IDs from the report filter.
	 *
	 * @param WPDAI_Data_Warehouse $data_warehouse Warehouse.
	 * @return array
	 */
	protected function get_filtered_experiment_ids( WPDAI_Data_Warehouse $data_warehouse ) {
		$raw = $data_warehouse->get_data_filter( 'experiments', 'experiment' );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$ids = array();
		foreach ( $raw as $value ) {
			$id = absint( $value );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Increment a multi-series date bucket keyed by category (e.g. experiment name).
	 *
	 * @param array  $series    Series array, passed by reference.
	 * @param string $category  Series key.
	 * @param string $date      Local formatted date.
	 * @param array  $container Empty date container to clone for a new series.
	 * @param int    $amount    Increment amount.
	 * @return void
	 */
	protected function increment_category_date_series( &$series, $category, $date, $container, $amount = 1 ) {
		if ( '' === $category || '' === $date || ! is_array( $container ) ) {
			return;
		}
		if ( ! isset( $series[ $category ] ) || ! is_array( $series[ $category ] ) ) {
			$series[ $category ] = $container;
		}
		if ( isset( $series[ $category ][ $date ] ) ) {
			$series[ $category ][ $date ] += (int) $amount;
		}
	}

	/**
	 * Increment daily exposures or conversions for one experiment arm.
	 *
	 * @param array  $daily_arms Daily arm buckets, passed by reference.
	 * @param array  $row        Assignment row.
	 * @param array  $container  Empty date container.
	 * @param string $date       Local formatted date.
	 * @param string $metric     exposures or conversions.
	 * @return void
	 */
	protected function increment_daily_arm_metric( &$daily_arms, $row, $container, $date, $metric, $amount = 1 ) {
		$allowed = array( 'exposures', 'conversions', 'add_to_carts', 'initiate_checkouts', 'transactions', 'revenue' );
		if ( '' === $date || ! in_array( $metric, $allowed, true ) ) {
			return;
		}

		$amount = (float) $amount;
		if ( $amount <= 0 ) {
			return;
		}

		$eid  = isset( $row['experiment_id'] ) ? (int) $row['experiment_id'] : 0;
		$vkey = ! empty( $row['variant_key'] ) ? $row['variant_key'] : 'holdout';
		if ( $eid < 1 || 'holdout' === $vkey ) {
			return;
		}

		if ( ! isset( $daily_arms[ $eid ][ $vkey ] ) ) {
			$exp_name = ! empty( $row['experiment_name'] ) ? $row['experiment_name'] : __( 'Untitled experiment', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
			$var_name = ! empty( $row['variant_name'] ) ? $row['variant_name'] : __( 'Holdout', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' );
			$daily_arms[ $eid ][ $vkey ] = array(
				'experiment_name'    => $exp_name,
				'variant_name'       => $var_name,
				'is_control'         => ! empty( $row['is_control'] ),
				'exposures'          => $container,
				'conversions'        => $container,
				'add_to_carts'       => $container,
				'initiate_checkouts' => $container,
				'transactions'       => $container,
				'revenue'            => $container,
			);
		}

		if ( isset( $daily_arms[ $eid ][ $vkey ][ $metric ][ $date ] ) ) {
			$daily_arms[ $eid ][ $vkey ][ $metric ][ $date ] += $amount;
		}
	}

	/**
	 * Compact daily funnel series for the A/B test list progress chart.
	 *
	 * @param array $daily_arms Per-experiment arm date buckets.
	 * @param array $container  Empty date container.
	 * @return array
	 */
	protected function build_progress_payload( $daily_arms, $container ) {
		$progress = array();
		if ( ! is_array( $daily_arms ) || ! is_array( $container ) || empty( $container ) ) {
			return $progress;
		}

		$dates = array_keys( $container );
		foreach ( $daily_arms as $eid => $arms ) {
			$eid = (int) $eid;
			if ( $eid < 1 || ! is_array( $arms ) ) {
				continue;
			}

			$first = null;
			$last  = null;
			foreach ( $dates as $date ) {
				$has_traffic = false;
				foreach ( $arms as $arm ) {
					if ( ! empty( $arm['exposures'][ $date ] ) ) {
						$has_traffic = true;
						break;
					}
				}
				if ( ! $has_traffic ) {
					continue;
				}
				if ( null === $first ) {
					$first = $date;
				}
				$last = $date;
			}
			if ( null === $first || null === $last ) {
				continue;
			}

			$slice    = array();
			$in_range = false;
			foreach ( $dates as $date ) {
				if ( $date === $first ) {
					$in_range = true;
				}
				if ( $in_range ) {
					$slice[] = $date;
				}
				if ( $date === $last ) {
					break;
				}
			}
			if ( empty( $slice ) ) {
				continue;
			}

			$progress[ $eid ] = array(
				'dates' => $slice,
				'arms'  => array(),
			);

			foreach ( $arms as $vkey => $arm ) {
				if ( ! is_array( $arm ) || 'holdout' === $vkey ) {
					continue;
				}
				$point = array(
					'key'                => (string) $vkey,
					'name'               => ! empty( $arm['variant_name'] ) ? $arm['variant_name'] : (string) $vkey,
					'is_control'         => ! empty( $arm['is_control'] ),
					'exposures'          => array(),
					'add_to_carts'       => array(),
					'initiate_checkouts' => array(),
					'transactions'       => array(),
					'revenue'            => array(),
				);
				foreach ( $slice as $date ) {
					$point['exposures'][]          = isset( $arm['exposures'][ $date ] ) ? (int) $arm['exposures'][ $date ] : 0;
					$point['add_to_carts'][]       = isset( $arm['add_to_carts'][ $date ] ) ? (int) $arm['add_to_carts'][ $date ] : 0;
					$point['initiate_checkouts'][] = isset( $arm['initiate_checkouts'][ $date ] ) ? (int) $arm['initiate_checkouts'][ $date ] : 0;
					$point['transactions'][]       = isset( $arm['transactions'][ $date ] ) ? (int) $arm['transactions'][ $date ] : 0;
					$point['revenue'][]            = isset( $arm['revenue'][ $date ] ) ? round( (float) $arm['revenue'][ $date ], 2 ) : 0;
				}
				$progress[ $eid ]['arms'][] = $point;
			}

			if ( empty( $progress[ $eid ]['arms'] ) ) {
				unset( $progress[ $eid ] );
			}
		}

		return $progress;
	}

	/**
	 * Daily lift vs control for non-control arms.
	 *
	 * Control stays at the baseline (0%) so it is omitted. Days without a
	 * control conversion rate cannot compute lift and stay 0.
	 *
	 * @param array $daily_arms Per-experiment arm date buckets.
	 * @param array $container  Empty date container.
	 * @return array
	 */
	protected function build_daily_lift_series( $daily_arms, $container ) {
		$series    = array();
		$multi_exp = is_array( $daily_arms ) && count( $daily_arms ) > 1;

		if ( ! is_array( $daily_arms ) || ! is_array( $container ) ) {
			return $series;
		}

		foreach ( $daily_arms as $arms ) {
			if ( ! is_array( $arms ) ) {
				continue;
			}

			$control = null;
			foreach ( $arms as $arm ) {
				if ( ! empty( $arm['is_control'] ) ) {
					$control = $arm;
					break;
				}
			}
			if ( ! $control ) {
				continue;
			}

			foreach ( $arms as $arm ) {
				if ( empty( $arm ) || ! empty( $arm['is_control'] ) ) {
					continue;
				}

				$var_name = isset( $arm['variant_name'] ) ? $arm['variant_name'] : '';
				$exp_name = isset( $arm['experiment_name'] ) ? $arm['experiment_name'] : '';
				$label    = $multi_exp ? trim( $exp_name . ' — ' . $var_name ) : $var_name;
				if ( '' === $label ) {
					continue;
				}

				$points = $container;
				foreach ( $container as $date => $_unused ) {
					$control_exp  = isset( $control['exposures'][ $date ] ) ? (int) $control['exposures'][ $date ] : 0;
					$control_conv = isset( $control['conversions'][ $date ] ) ? (int) $control['conversions'][ $date ] : 0;
					$arm_exp      = isset( $arm['exposures'][ $date ] ) ? (int) $arm['exposures'][ $date ] : 0;
					$arm_conv     = isset( $arm['conversions'][ $date ] ) ? (int) $arm['conversions'][ $date ] : 0;
					$control_cr   = $control_exp > 0 ? ( $control_conv / $control_exp ) : 0;
					$arm_cr       = $arm_exp > 0 ? ( $arm_conv / $arm_exp ) : 0;
					if ( $control_cr > 0 ) {
						$points[ $date ] = round( ( ( $arm_cr - $control_cr ) / $control_cr ) * 100, 2 );
					}
				}

				$series[ $label ] = $points;
			}
		}

		return $series;
	}

	/**
	 * Daily conversion rate for every arm, including control.
	 *
	 * @param array $daily_arms Per-experiment arm date buckets.
	 * @param array $container  Empty date container.
	 * @return array
	 */
	protected function build_daily_conversion_rate_series( $daily_arms, $container ) {
		$series    = array();
		$multi_exp = is_array( $daily_arms ) && count( $daily_arms ) > 1;

		if ( ! is_array( $daily_arms ) || ! is_array( $container ) ) {
			return $series;
		}

		foreach ( $daily_arms as $arms ) {
			if ( ! is_array( $arms ) ) {
				continue;
			}

			foreach ( $arms as $arm ) {
				if ( empty( $arm ) ) {
					continue;
				}

				$var_name = isset( $arm['variant_name'] ) ? $arm['variant_name'] : '';
				$exp_name = isset( $arm['experiment_name'] ) ? $arm['experiment_name'] : '';
				$label    = $multi_exp ? trim( $exp_name . ' — ' . $var_name ) : $var_name;
				if ( '' === $label ) {
					continue;
				}

				$points = $container;
				foreach ( $container as $date => $_unused ) {
					$exposures   = isset( $arm['exposures'][ $date ] ) ? (int) $arm['exposures'][ $date ] : 0;
					$conversions = isset( $arm['conversions'][ $date ] ) ? (int) $arm['conversions'][ $date ] : 0;
					if ( $exposures > 0 ) {
						$points[ $date ] = round( ( $conversions / $exposures ) * 100, 2 );
					}
				}

				$series[ $label ] = $points;
			}
		}

		return $series;
	}

	/**
	 * Funnel rates as percentages of exposures.
	 *
	 * @param array $row       Totals or variant row, passed by reference.
	 * @param int   $exposures Exposure count.
	 * @return void
	 */
	protected function apply_funnel_rates( &$row, $exposures ) {
		$exposures = (int) $exposures;
		$row['add_to_cart_rate']        = wpdai_calculate_percentage( isset( $row['add_to_carts'] ) ? $row['add_to_carts'] : 0, $exposures );
		$row['initiate_checkout_rate']  = wpdai_calculate_percentage( isset( $row['initiate_checkouts'] ) ? $row['initiate_checkouts'] : 0, $exposures );
		$row['checkout_rate']           = wpdai_calculate_percentage( isset( $row['transactions'] ) ? $row['transactions'] : 0, $exposures );
	}

	/**
	 * Event aggregates for session IDs, chunked to stay under max_allowed_packet.
	 *
	 * @param string $events_table Events table.
	 * @param array  $session_ids  Session IDs.
	 * @param array  $event_types  Event types.
	 * @param string $from_gmt     Start GMT.
	 * @param string $to_gmt       End GMT.
	 * @return array
	 */
	protected function get_events_for_sessions( $events_table, $session_ids, $event_types, $from_gmt, $to_gmt ) {
		global $wpdb;
		$rows        = array();
		$event_types = array_values( array_filter( array_map( 'sanitize_text_field', (array) $event_types ) ) );
		if ( empty( $session_ids ) ) {
			return $rows;
		}

		$filter_types = ! empty( $event_types );
		$type_sql     = '';
		if ( $filter_types ) {
			$type_sql = 'AND event_type IN (' . implode( ',', array_fill( 0, count( $event_types ), '%s' ) ) . ')';
		}

		foreach ( array_chunk( $session_ids, 200 ) as $chunk ) {
			$session_placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			$args                 = array( $from_gmt, $to_gmt );
			if ( $filter_types ) {
				$args = array_merge( $args, $event_types );
			}
			$args = array_merge( $args, $chunk );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders counted from arrays.
			$chunk_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT session_id, event_type, object_type,
						SUM(CASE WHEN event_type = 'add_to_cart' THEN event_value * GREATEST(event_quantity, 1) ELSE event_value END) AS revenue,
						COUNT(*) AS event_count
					FROM {$events_table}
					WHERE date_created_gmt >= %s AND date_created_gmt <= %s
					{$type_sql}
					AND session_id IN ({$session_placeholders})
					GROUP BY session_id, event_type, object_type",
					$args
				),
				ARRAY_A
			);
			if ( is_array( $chunk_rows ) ) {
				$rows = array_merge( $rows, $chunk_rows );
			}
		}

		return $rows;
	}

	/**
	 * Zeroed session analytics counters.
	 *
	 * @return array
	 */
	protected function empty_analytics_counters() {
		return array(
			'sessions'                 => 0,
			'page_views'               => 0,
			'page_views_per_session'   => 0,
			'product_page_views'       => 0,
			'add_to_carts'             => 0,
			'add_to_cart_value'        => 0,
			'add_to_cart_rate'         => 0,
			'initiate_checkouts'       => 0,
			'initiate_checkout_rate'   => 0,
			'transactions'             => 0,
			'checkout_rate'            => 0,
			'transaction_value'        => 0,
			'total_session_duration'   => 0,
			'average_session_duration' => 0,
		);
	}

	/**
	 * Metric keys summed from per-session event aggregates.
	 *
	 * @return array
	 */
	protected function session_metric_sum_keys() {
		return array(
			'page_views',
			'product_page_views',
			'add_to_carts',
			'add_to_cart_value',
			'initiate_checkouts',
			'transactions',
			'transaction_value',
		);
	}

	/**
	 * Fold grouped event rows into per-session analytics and conversion maps.
	 *
	 * @param array $event_rows Grouped event rows.
	 * @return array
	 */
	protected function index_session_events( $event_rows ) {
		$analytics    = array();
		$conversions  = array();
		if ( ! is_array( $event_rows ) ) {
			return array(
				'analytics'   => $analytics,
				'conversions' => $conversions,
			);
		}

		foreach ( $event_rows as $event_row ) {
			$sid    = isset( $event_row['session_id'] ) ? $event_row['session_id'] : '';
			$type   = isset( $event_row['event_type'] ) ? $event_row['event_type'] : '';
			$object = isset( $event_row['object_type'] ) ? $event_row['object_type'] : '';
			$count  = isset( $event_row['event_count'] ) ? (int) $event_row['event_count'] : 0;
			$value  = isset( $event_row['revenue'] ) ? (float) $event_row['revenue'] : 0;
			if ( '' === $sid || '' === $type ) {
				continue;
			}

			if ( ! isset( $analytics[ $sid ] ) ) {
				$analytics[ $sid ] = $this->empty_analytics_counters();
			}
			if ( ! isset( $conversions[ $sid ][ $type ] ) ) {
				$conversions[ $sid ][ $type ] = array(
					'revenue'     => 0,
					'event_count' => 0,
				);
			}
			$conversions[ $sid ][ $type ]['revenue']     += $value;
			$conversions[ $sid ][ $type ]['event_count'] += $count;

			if ( 'page_view' === $type ) {
				$analytics[ $sid ]['page_views'] += $count;
				if ( 'product' === $object ) {
					$analytics[ $sid ]['product_page_views'] += $count;
				}
			}

			if ( 'add_to_cart' === $type ) {
				$analytics[ $sid ]['add_to_carts']      += $count;
				$analytics[ $sid ]['add_to_cart_value'] += $value;
			} elseif ( 'init_checkout' === $type ) {
				$analytics[ $sid ]['initiate_checkouts'] += $count;
			} elseif ( 'transaction' === $type ) {
				$analytics[ $sid ]['transactions']      += $count;
				$analytics[ $sid ]['transaction_value'] += $value;
			}
		}

		return array(
			'analytics'   => $analytics,
			'conversions' => $conversions,
		);
	}

	/**
	 * Session durations in seconds, keyed by session ID.
	 *
	 * @param string $session_table Session data table.
	 * @param array  $session_ids   Session IDs.
	 * @return array
	 */
	protected function get_session_durations( $session_table, $session_ids ) {
		global $wpdb;
		$map = array();
		if ( empty( $session_ids ) || empty( $session_table ) ) {
			return $map;
		}

		foreach ( array_chunk( $session_ids, 200 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefix-based and trusted.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT session_id, TIMESTAMPDIFF(SECOND, date_created_gmt, date_updated_gmt) AS duration
					FROM {$session_table}
					WHERE session_id IN ({$placeholders})",
					$chunk
				),
				ARRAY_A
			);
			if ( ! is_array( $rows ) ) {
				continue;
			}
			foreach ( $rows as $row ) {
				if ( empty( $row['session_id'] ) ) {
					continue;
				}
				$map[ $row['session_id'] ] = max( 0, (int) $row['duration'] );
			}
		}

		return $map;
	}

	/**
	 * Purchase totals keyed by experiment visitor ID (order meta fallback).
	 *
	 * @param array $visitor_ids Visitor IDs.
	 * @return array
	 */
	protected function get_purchases_by_visitor( $visitor_ids ) {
		global $wpdb;
		$map = array();
		if ( empty( $visitor_ids ) || ! function_exists( 'wc_get_order' ) ) {
			return $map;
		}

		foreach ( array_chunk( $visitor_ids, 200 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			if ( function_exists( 'wpdai_is_hpos_enabled' ) && wpdai_is_hpos_enabled() ) {
				$meta_table = $wpdb->prefix . 'wc_orders_meta';
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is trusted; placeholders counted.
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT order_id, meta_value AS visitor_id FROM {$meta_table} WHERE meta_key = %s AND meta_value IN ({$placeholders})",
						array_merge( array( '_wpd_ai_visitor_id' ), $chunk )
					),
					ARRAY_A
				);
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders counted.
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT post_id AS order_id, meta_value AS visitor_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value IN ({$placeholders})",
						array_merge( array( '_wpd_ai_visitor_id' ), $chunk )
					),
					ARRAY_A
				);
			}

			if ( ! is_array( $rows ) ) {
				continue;
			}

			foreach ( $rows as $row ) {
				$order = wc_get_order( (int) $row['order_id'] );
				if ( ! $order || ! is_callable( array( $order, 'is_paid' ) ) || ! $order->is_paid() ) {
					continue;
				}
				$vid = $row['visitor_id'];
				if ( ! isset( $map[ $vid ] ) ) {
					$map[ $vid ] = array(
						'revenue' => 0,
						'profit'  => 0,
					);
				}
				$map[ $vid ]['revenue'] += (float) $order->get_total();
				if ( function_exists( 'wpdai_calculate_cost_profit_by_order' ) ) {
					$calc = wpdai_calculate_cost_profit_by_order( (int) $row['order_id'] );
					if ( is_array( $calc ) && isset( $calc['total_order_profit'] ) ) {
						$map[ $vid ]['profit'] += (float) $calc['total_order_profit'];
					}
				}
			}
		}

		return $map;
	}

	/**
	 * Profit by analytics session ID via order meta.
	 *
	 * @param array $session_ids Session IDs.
	 * @return array
	 */
	protected function get_profit_by_session( $session_ids ) {
		global $wpdb;
		$map = array();
		if ( empty( $session_ids ) ) {
			return $map;
		}

		foreach ( array_chunk( $session_ids, 200 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );

			if ( function_exists( 'wpdai_is_hpos_enabled' ) && wpdai_is_hpos_enabled() ) {
				$meta_table = $wpdb->prefix . 'wc_orders_meta';
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is trusted; placeholders counted.
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT order_id, meta_value AS session_id FROM {$meta_table} WHERE meta_key = %s AND meta_value IN ({$placeholders})",
						array_merge( array( '_wpd_ai_session_id' ), $chunk )
					),
					ARRAY_A
				);
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders counted.
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT post_id AS order_id, meta_value AS session_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value IN ({$placeholders})",
						array_merge( array( '_wpd_ai_session_id' ), $chunk )
					),
					ARRAY_A
				);
			}

			if ( ! is_array( $rows ) ) {
				continue;
			}

			foreach ( $rows as $row ) {
				$calc = wpdai_calculate_cost_profit_by_order( (int) $row['order_id'] );
				if ( is_array( $calc ) && isset( $calc['total_order_profit'] ) ) {
					$sid = $row['session_id'];
					if ( ! isset( $map[ $sid ] ) ) {
						$map[ $sid ] = 0;
					}
					$map[ $sid ] += (float) $calc['total_order_profit'];
				}
			}
		}

		return $map;
	}

	/**
	 * React mapping.
	 *
	 * @return array
	 */
	public function get_data_mapping() {
		$is_pro = wpdai_experiments_is_pro();

		$totals = array(
			'label'  => __( 'A/B Tests', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'icon'   => 'analytics',
			'totals' => array(
				'eligible'        => array(
					'label'       => __( 'Experiment - Eligible visitors', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'        => 'number',
					'format'      => 'integer',
					'description' => __( 'Visitors who matched targeting before assignment.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				),
				'assigned'        => array(
					'label'       => __( 'Experiment - Assigned', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'        => 'number',
					'format'      => 'integer',
					'description' => __( 'Eligible visitors assigned to a variant (excludes holdout).', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				),
				'exposed'         => array(
					'label'       => __( 'Experiment - Exposures', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'        => 'number',
					'format'      => 'integer',
					'description' => __( 'Assigned visitors who actually saw the variant.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				),
				'holdout'         => array(
					'label'       => __( 'Experiment - Holdout', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'        => 'number',
					'format'      => 'integer',
					'description' => __( 'Eligible visitors withheld from the experiment.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				),
				'conversions'     => array(
					'label'       => __( 'Experiment - Conversions', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'        => 'number',
					'format'      => 'integer',
					'description' => __( 'Exposed visitors who completed the primary goal.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				),
				'conversion_rate' => array(
					'label'       => __( 'Experiment - Conversion rate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'        => 'percentage',
					'format'      => 'percentage',
					'description' => __( 'Conversions divided by exposures.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				),
				'sessions' => array(
					'label'       => __( 'Experiment - Sessions', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'        => 'number',
					'format'      => 'integer',
					'description' => __( 'Unique sessions linked to an exposed assignment.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				),
				'average_session_duration' => array(
					'label'       => __( 'Experiment - Avg. session duration', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'        => 'number',
					'format'      => 'duration',
					'description' => __( 'Average duration of exposed experiment sessions.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				),
				'page_views_per_session' => array(
					'label'       => __( 'Experiment - Page views / session', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'        => 'number',
					'format'      => 'decimal',
					'description' => __( 'Average page views per exposed session.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				),
				'product_page_views' => array(
					'label'       => __( 'Experiment - Product page views', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'        => 'number',
					'format'      => 'integer',
					'description' => __( 'Product page views in exposed sessions.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				),
				'add_to_carts' => array(
					'label'       => __( 'Experiment - Add to carts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'        => 'number',
					'format'      => 'integer',
					'description' => __( 'Add to cart events in exposed sessions.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				),
				'add_to_cart_value' => array(
					'label'       => __( 'Experiment - Add to cart value', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'        => 'currency',
					'format'      => 'currency',
					'description' => __( 'Value of add to cart events in exposed sessions.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				),
				'add_to_cart_rate' => array(
					'label'       => __( 'Experiment - Add to cart rate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'        => 'percentage',
					'format'      => 'percentage',
					'description' => __( 'Add to carts divided by exposures.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				),
				'initiate_checkouts' => array(
					'label'       => __( 'Experiment - Initiate checkouts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'        => 'number',
					'format'      => 'integer',
					'description' => __( 'Checkout starts in exposed sessions.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				),
				'initiate_checkout_rate' => array(
					'label'       => __( 'Experiment - Initiate checkout rate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'        => 'percentage',
					'format'      => 'percentage',
					'description' => __( 'Initiate checkouts divided by exposures.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				),
				'transactions' => array(
					'label'       => __( 'Experiment - Transactions', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'        => 'number',
					'format'      => 'integer',
					'description' => __( 'Purchase events in exposed sessions.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				),
				'checkout_rate' => array(
					'label'       => __( 'Experiment - Checkout rate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'        => 'percentage',
					'format'      => 'percentage',
					'description' => __( 'Transactions divided by exposures.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				),
				'transaction_value' => array(
					'label'       => __( 'Experiment - Transaction value', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'        => 'currency',
					'format'      => 'currency',
					'description' => __( 'Purchase value in exposed sessions.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				),
			),
		);

		if ( $is_pro ) {
			$totals['totals']['revenue'] = array(
				'label'       => __( 'Experiment - Revenue', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'        => 'currency',
				'format'      => 'currency',
				'description' => __( 'Attributed order revenue from exposed converting visitors.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'pro'         => true,
			);
			$totals['totals']['revenue_per_exposure'] = array(
				'label'       => __( 'Experiment - Revenue per exposure', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'        => 'currency',
				'format'      => 'currency',
				'description' => __( 'Attributed revenue divided by exposures, so variants with more traffic are not favoured.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'pro'         => true,
			);
			$totals['totals']['aov'] = array(
				'label'       => __( 'Experiment - AOV', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'        => 'currency',
				'format'      => 'currency',
				'description' => __( 'Average order value of converting exposed visitors.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'pro'         => true,
			);
			$totals['totals']['profit'] = array(
				'label'       => __( 'Experiment - Profit', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'        => 'currency',
				'format'      => 'currency',
				'description' => __( 'Attributed profit from exposed converting visitors.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'pro'         => true,
			);
		}

		$columns = array(
			'experiment_name' => array(
				'label' => __( 'A/B Test', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'  => 'text',
			),
			'variant_name'    => array(
				'label' => __( 'Variant', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'  => 'text',
			),
			'is_control' => array(
				'label'  => __( 'Control', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'number',
				'format' => 'integer',
			),
			'exposures'       => array(
				'label'  => __( 'Experiment - Exposures', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'number',
				'format' => 'integer',
			),
			'sessions' => array(
				'label'  => __( 'Experiment - Sessions', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'number',
				'format' => 'integer',
			),
			'average_session_duration' => array(
				'label'  => __( 'Experiment - Avg. duration', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'number',
				'format' => 'duration',
			),
			'page_views_per_session' => array(
				'label'  => __( 'Experiment - Page views / session', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'number',
				'format' => 'decimal',
			),
			'product_page_views' => array(
				'label'  => __( 'Experiment - Product page views', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'number',
				'format' => 'integer',
			),
			'add_to_carts' => array(
				'label'  => __( 'Experiment - Add to carts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'number',
				'format' => 'integer',
			),
			'add_to_cart_value' => array(
				'label'  => __( 'Experiment - Add to cart value', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'currency',
				'format' => 'currency',
			),
			'add_to_cart_rate' => array(
				'label'  => __( 'Experiment - Add to cart rate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'percentage',
				'format' => 'percentage',
			),
			'initiate_checkouts' => array(
				'label'  => __( 'Experiment - Initiate checkouts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'number',
				'format' => 'integer',
			),
			'initiate_checkout_rate' => array(
				'label'  => __( 'Experiment - Initiate checkout rate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'percentage',
				'format' => 'percentage',
			),
			'transactions' => array(
				'label'  => __( 'Experiment - Transactions', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'number',
				'format' => 'integer',
			),
			'checkout_rate' => array(
				'label'  => __( 'Experiment - Checkout rate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'percentage',
				'format' => 'percentage',
			),
			'transaction_value' => array(
				'label'  => __( 'Experiment - Transaction value', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'currency',
				'format' => 'currency',
			),
			'conversions'     => array(
				'label'  => __( 'Experiment - Conversions', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'number',
				'format' => 'integer',
			),
			'conversion_rate' => array(
				'label'  => __( 'Experiment - Conversion rate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'percentage',
				'format' => 'percentage',
			),
			'lift'            => array(
				'label'  => __( 'Experiment - Lift vs control (%)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'number',
				'format' => 'decimal',
			),
		);

		if ( $is_pro ) {
			$columns['revenue'] = array(
				'label'  => __( 'Experiment - Revenue', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'currency',
				'format' => 'currency',
				'pro'    => true,
			);
			$columns['revenue_per_exposure'] = array(
				'label'       => __( 'Experiment - Revenue per exposure', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'        => 'currency',
				'format'      => 'currency',
				'description' => __( 'Attributed revenue divided by exposures, so variants with more traffic are not favoured.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'pro'         => true,
			);
			$columns['aov'] = array(
				'label'  => __( 'Experiment - AOV', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'currency',
				'format' => 'currency',
				'pro'    => true,
			);
			$columns['profit'] = array(
				'label'  => __( 'Experiment - Profit', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'currency',
				'format' => 'currency',
				'pro'    => true,
			);
			$columns['conversion_rate_low'] = array(
				'label'  => __( 'Experiment - Conversion rate low', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'percentage',
				'format' => 'percentage',
				'pro'    => true,
			);
			$columns['conversion_rate_high'] = array(
				'label'  => __( 'Experiment - Conversion rate high', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'percentage',
				'format' => 'percentage',
				'pro'    => true,
			);
			$columns['p_value'] = array(
				'label'  => __( 'Experiment - P-value', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'number',
				'format' => 'decimal',
				'pro'    => true,
			);
			$columns['significant'] = array(
				'label'  => __( 'Experiment - Significant', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'number',
				'format' => 'integer',
				'pro'    => true,
			);
			$columns['ready'] = array(
				'label'  => __( 'Experiment - Sample ready', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'type'   => 'number',
				'format' => 'integer',
				'pro'    => true,
			);
		}

		$categorized_fields = array(
			array(
				'label' => __( 'Experiment - Exposures', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'value' => 'exposures',
				'type'  => 'number',
			),
			array(
				'label' => __( 'Experiment - Conversions', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'value' => 'conversions',
				'type'  => 'number',
			),
			array(
				'label' => __( 'Experiment - Conversion rate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'value' => 'conversion_rate',
				'type'  => 'percentage',
			),
			array(
				'label' => __( 'Experiment - Lift vs control', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'value' => 'lift',
				'type'  => 'number',
			),
			array(
				'label' => __( 'Experiment - Add to carts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'value' => 'add_to_carts',
				'type'  => 'number',
			),
			array(
				'label' => __( 'Experiment - Add to cart rate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'value' => 'add_to_cart_rate',
				'type'  => 'percentage',
			),
			array(
				'label' => __( 'Experiment - Initiate checkouts', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'value' => 'initiate_checkouts',
				'type'  => 'number',
			),
			array(
				'label' => __( 'Experiment - Initiate checkout rate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'value' => 'initiate_checkout_rate',
				'type'  => 'percentage',
			),
			array(
				'label' => __( 'Experiment - Transactions', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'value' => 'transactions',
				'type'  => 'number',
			),
			array(
				'label' => __( 'Experiment - Checkout rate', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'value' => 'checkout_rate',
				'type'  => 'percentage',
			),
		);

		if ( $is_pro ) {
			$categorized_fields[] = array(
				'label' => __( 'Experiment - Revenue', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'value' => 'revenue',
				'type'  => 'currency',
			);
		}

		return array(
			'totals'           => $totals,
			'data_by_date'     => array(
				'exposures_by_date'   => array(
					'label'             => __( 'Experiment - Exposures over time', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'description'       => __( 'Visitors who saw a variant, counted by assignment date.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'              => 'integer',
					'format'            => 'integer',
					'chart_calculation' => 'sum',
				),
				'conversions_by_date' => array(
					'label'             => __( 'Experiment - Conversions over time', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'description'       => __( 'Primary-goal conversions, counted by assignment date.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'              => 'integer',
					'format'            => 'integer',
					'chart_calculation' => 'sum',
				),
				'exposures_by_experiment_by_date' => array(
					'label'             => __( 'Experiment - Hits by experiment', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'description'       => __( 'Exposures over time, one series per experiment.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'              => 'integer',
					'format'            => 'integer',
					'chart_calculation' => 'sum',
					'multi_dimensional' => true,
				),
				'lift_by_variant_by_date' => array(
					'label'             => __( 'Experiment - Daily lift vs control', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'description'       => __( 'Relative conversion-rate lift versus the control arm, one series per variant (control omitted because it is the 0% baseline).', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'              => 'percentage',
					'format'            => 'percentage',
					'chart_calculation' => 'average',
					'multi_dimensional' => true,
				),
				'conversion_rate_by_variant_by_date' => array(
					'label'             => __( 'Experiment - Conversion rate by variant', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'description'       => __( 'Daily conversion rate for each variant, including the control arm.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'type'              => 'percentage',
					'format'            => 'percentage',
					'chart_calculation' => 'average',
					'multi_dimensional' => true,
				),
			),
			'categorized_data' => array(
				'variants' => array(
					'label'         => __( 'Experiment - Results by variant', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'description'   => __( 'Results by experiment variant.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'icon'          => 'analytics',
					'type'          => 'count',
					'chart_type'    => 'pie',
					'metric_fields' => $categorized_fields,
				),
			),
			'data_table'       => array(
				'variants' => array(
					'label'       => __( 'Experiment - Results by variant', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'description' => __( 'Per-arm exposures, conversions, lift, and funnel rates.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
					'icon'        => 'table_chart',
					'columns'     => $columns,
				),
			),
		);
	}
}

new WPDAI_Experiments_Data_Source();
