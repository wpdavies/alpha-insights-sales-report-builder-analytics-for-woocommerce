<?php
/**
 * Debug Settings — Sessions tab helpers.
 *
 * @package Alpha Insights
 * @since 5.10.1
 * @author WPDavies
 */
defined( 'ABSPATH' ) || exit;

/**
 * Sessions shown per page on the debug Sessions tab.
 *
 * @return int
 */
function wpdai_get_debug_sessions_per_page() {
	$per_page = (int) apply_filters( 'wpd_ai_debug_sessions_per_page', 100 );
	return max( 1, min( 500, $per_page ) );
}

/**
 * Maximum sessions included in a debug CSV export.
 *
 * @return int
 */
function wpdai_get_debug_sessions_export_limit() {
	$limit = (int) apply_filters( 'wpd_ai_debug_sessions_export_limit', 25000 );
	return max( 1, min( 100000, $limit ) );
}

/**
 * Traffic source options for the debug Sessions filter.
 *
 * @return array<string, string> Slug => label.
 */
function wpdai_get_debug_session_source_options() {
	if ( class_exists( 'WPDAI_Traffic_Type_Detection' ) ) {
		$types = WPDAI_Traffic_Type_Detection::available_traffic_types();
		if ( is_array( $types ) && ! empty( $types ) ) {
			return $types;
		}
	}

	return array(
		'direct' => 'Direct',
	);
}

/**
 * Sanitized query args for the debug Sessions tab.
 *
 * @return array<string, mixed>
 */
function wpdai_get_debug_session_request_args() {
	$defaults = function_exists( 'wpdai_get_dates_from_preset' )
		? wpdai_get_dates_from_preset( 'last_30_days' )
		: array(
			'from' => gmdate( 'Y-m-d', strtotime( '-29 days' ) ),
			'to'   => gmdate( 'Y-m-d' ),
		);

	$date_from = isset( $_GET['session_from'] ) ? sanitize_text_field( wp_unslash( $_GET['session_from'] ) ) : '';
	$date_to   = isset( $_GET['session_to'] ) ? sanitize_text_field( wp_unslash( $_GET['session_to'] ) ) : '';
	$source    = isset( $_GET['session_source'] ) ? sanitize_key( wp_unslash( $_GET['session_source'] ) ) : '';
	$paged     = isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1;

	if ( ! $date_from || ! wpdai_validate_date_format( $date_from ) ) {
		$date_from = isset( $defaults['from'] ) ? $defaults['from'] : '';
	}

	if ( ! $date_to || ! wpdai_validate_date_format( $date_to ) ) {
		$date_to = isset( $defaults['to'] ) ? $defaults['to'] : '';
	}

	if ( $date_from && $date_to && $date_from > $date_to ) {
		$swap      = $date_from;
		$date_from = $date_to;
		$date_to   = $swap;
	}

	$source_options = wpdai_get_debug_session_source_options();
	if ( '' !== $source && ! array_key_exists( $source, $source_options ) ) {
		$source = '';
	}

	return array(
		'date_from' => $date_from,
		'date_to'   => $date_to,
		'source'    => $source,
		'paged'     => max( 1, $paged ),
		'per_page'  => wpdai_get_debug_sessions_per_page(),
	);
}

/**
 * Convert a local date range to GMT bounds for session queries.
 *
 * @param string $date_from Local Y-m-d start.
 * @param string $date_to   Local Y-m-d end (inclusive).
 * @return array{from:string,to:string}|false
 */
function wpdai_get_debug_session_gmt_bounds( $date_from, $date_to ) {
	if ( ! wpdai_validate_date_format( $date_from ) || ! wpdai_validate_date_format( $date_to ) ) {
		return false;
	}

	$from_local = $date_from . ' 00:00:00';

	try {
		$to_local = new DateTime( $date_to . ' 00:00:00', wp_timezone() );
		$to_local->modify( '+1 day' );
	} catch ( Exception $e ) {
		return false;
	}

	return array(
		'from' => get_gmt_from_date( $from_local ),
		'to'   => get_gmt_from_date( $to_local->format( 'Y-m-d H:i:s' ) ),
	);
}

/**
 * Determine the report traffic source for a session row.
 *
 * @param string $landing_page Landing URL.
 * @param string $referral_url Referral URL.
 * @param string $user_agent   Optional visitor user agent.
 * @return string Display label (e.g. Direct).
 */
function wpdai_get_debug_session_traffic_source( $landing_page, $referral_url, $user_agent = '' ) {
	$query_params = function_exists( 'wpdai_get_query_params' ) ? wpdai_get_query_params( $landing_page ) : array();
	$source       = function_exists( 'wpdai_get_traffic_type' ) ? wpdai_get_traffic_type( $referral_url, $query_params, $user_agent ) : '';

	return is_string( $source ) && '' !== $source ? $source : 'Unknown';
}

/**
 * Pull the stored user agent from session additional_data.
 *
 * @param mixed $additional_data JSON string or array.
 * @return string
 */
function wpdai_get_debug_session_user_agent( $additional_data ) {
	if ( class_exists( 'WPDAI_Traffic_Type_Detection' ) ) {
		return WPDAI_Traffic_Type_Detection::user_agent_from_additional_data( $additional_data );
	}

	return '';
}

/**
 * Decode event additional_data for debug display.
 *
 * @param mixed $additional_data JSON string or array.
 * @return array<string, mixed>
 */
function wpdai_get_debug_event_additional_data( $additional_data ) {
	if ( is_string( $additional_data ) && '' !== $additional_data ) {
		$decoded = json_decode( $additional_data, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	return is_array( $additional_data ) ? $additional_data : array();
}

/**
 * Fetch paginated debug sessions and their events.
 *
 * @param array<string, mixed> $args Request args from wpdai_get_debug_session_request_args().
 * @return array<string, mixed>
 */
function wpdai_get_debug_sessions( $args ) {
	$empty = array(
		'sessions'    => array(),
		'total'       => 0,
		'page'        => 1,
		'per_page'    => wpdai_get_debug_sessions_per_page(),
		'total_pages' => 0,
	);

	$query = wpdai_query_debug_session_raw_rows( $args );
	if ( empty( $query['ok'] ) ) {
		return $empty;
	}

	$source_label = isset( $query['source_label'] ) ? (string) $query['source_label'] : '';
	$sessions     = wpdai_format_debug_sessions( $query['rows'], $source_label );
	$session_ids  = array();
	foreach ( $sessions as $session ) {
		if ( ! empty( $session['session_id'] ) ) {
			$session_ids[] = $session['session_id'];
		}
	}

	$events_by_session = wpdai_get_debug_session_events( $query['events_table'], $session_ids );

	foreach ( $sessions as &$session ) {
		$session_id             = $session['session_id'];
		$session['events']      = isset( $events_by_session[ $session_id ] ) ? $events_by_session[ $session_id ] : array();
		$session['event_count'] = count( $session['events'] );
	}
	unset( $session );

	$per_page    = (int) $query['per_page'];
	$total       = (int) $query['total'];
	$page        = (int) $query['page'];
	$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 0;

	return array(
		'sessions'    => $sessions,
		'total'       => $total,
		'page'        => $page,
		'per_page'    => $per_page,
		'total_pages' => $total_pages,
	);
}

/**
 * Query raw session rows for the debug table or CSV export.
 *
 * @param array<string, mixed> $args Request args. Set export=true to ignore pagination.
 * @return array<string, mixed>
 */
function wpdai_query_debug_session_raw_rows( $args ) {
	$failed = array(
		'ok'           => false,
		'rows'         => array(),
		'total'        => 0,
		'page'         => 1,
		'per_page'     => wpdai_get_debug_sessions_per_page(),
		'source_label' => '',
		'events_table' => '',
	);

	global $wpdb;

	if ( ! class_exists( 'WPDAI_Database_Interactor' ) ) {
		return $failed;
	}

	$db_interactor = new WPDAI_Database_Interactor();
	$session_table = $db_interactor->session_data_table;
	$events_table  = $db_interactor->events_table;
	$managed       = $db_interactor->get_managed_tables();

	if ( ! in_array( $session_table, $managed, true ) || ! in_array( $events_table, $managed, true ) ) {
		return $failed;
	}

	$bounds = wpdai_get_debug_session_gmt_bounds( $args['date_from'], $args['date_to'] );
	if ( ! $bounds ) {
		return $failed;
	}

	$export         = ! empty( $args['export'] );
	$per_page       = $export ? wpdai_get_debug_sessions_export_limit() : (int) $args['per_page'];
	$page           = $export ? 1 : (int) $args['paged'];
	$source_slug    = isset( $args['source'] ) ? (string) $args['source'] : '';
	$source_options = wpdai_get_debug_session_source_options();
	$source_label   = ( '' !== $source_slug && isset( $source_options[ $source_slug ] ) ) ? $source_options[ $source_slug ] : '';
	$session_columns = 'session_id, ip_address, user_id, landing_page, referral_url, date_created_gmt, date_updated_gmt, device_category, operating_system, browser, engaged_session, additional_data';

	if ( ! $export && '' === $source_label ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated via Database Interactor whitelist.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$session_table} WHERE date_created_gmt >= %s AND date_created_gmt < %s",
				$bounds['from'],
				$bounds['to']
			)
		);

		$offset = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated via Database Interactor whitelist.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$session_columns}
				FROM {$session_table}
				WHERE date_created_gmt >= %s AND date_created_gmt < %s
				ORDER BY date_created_gmt DESC
				LIMIT %d OFFSET %d",
				$bounds['from'],
				$bounds['to'],
				$per_page,
				$offset
			),
			ARRAY_A
		);
	} else {
		$batch_size = (int) apply_filters( 'wpd_ai_debug_sessions_batch_size', 500 );
		$batch_size = max( 100, min( 1000, $batch_size ) );
		$db_offset  = 0;
		$skip       = $export ? 0 : ( ( $page - 1 ) * $per_page );
		$total      = 0;
		$rows       = array();

		while ( true ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated via Database Interactor whitelist.
			$batch = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT {$session_columns}
					FROM {$session_table}
					WHERE date_created_gmt >= %s AND date_created_gmt < %s
					ORDER BY date_created_gmt DESC
					LIMIT %d OFFSET %d",
					$bounds['from'],
					$bounds['to'],
					$batch_size,
					$db_offset
				),
				ARRAY_A
			);

			if ( ! is_array( $batch ) || empty( $batch ) ) {
				break;
			}

			foreach ( $batch as $row ) {
				$computed = wpdai_get_debug_session_traffic_source(
					isset( $row['landing_page'] ) ? (string) $row['landing_page'] : '',
					isset( $row['referral_url'] ) ? (string) $row['referral_url'] : '',
					wpdai_get_debug_session_user_agent( $row['additional_data'] ?? '' )
				);

				if ( '' !== $source_label && $computed !== $source_label ) {
					continue;
				}

				if ( $total >= $skip && count( $rows ) < $per_page ) {
					$row['traffic_source'] = $computed;
					$rows[]                = $row;
				}

				$total++;

				if ( $export && count( $rows ) >= $per_page ) {
					break 2;
				}
			}

			$db_offset += $batch_size;
		}
	}

	if ( ! is_array( $rows ) ) {
		$rows = array();
	}

	return array(
		'ok'           => true,
		'rows'         => $rows,
		'total'        => $total,
		'page'         => $page,
		'per_page'     => $export ? count( $rows ) : $per_page,
		'source_label' => $source_label,
		'events_table' => $events_table,
	);
}

/**
 * Normalize session rows for the debug table.
 *
 * @param array<int, array<string, mixed>> $rows         Raw session rows.
 * @param string                           $source_label Precomputed source label when already filtered.
 * @return array<int, array<string, mixed>>
 */
function wpdai_format_debug_sessions( $rows, $source_label = '' ) {
	$formatted = array();

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$landing_page = isset( $row['landing_page'] ) ? (string) $row['landing_page'] : '';
		$referral_url = isset( $row['referral_url'] ) ? (string) $row['referral_url'] : '';
		$query_params = function_exists( 'wpdai_get_query_params' ) ? wpdai_get_query_params( $landing_page ) : array();
		$source       = ! empty( $row['traffic_source'] )
			? (string) $row['traffic_source']
			: wpdai_get_debug_session_traffic_source(
				$landing_page,
				$referral_url,
				wpdai_get_debug_session_user_agent( $row['additional_data'] ?? '' )
			);

		if ( '' !== $source_label && $source !== $source_label ) {
			continue;
		}

		$source_class = class_exists( 'WPDAI_Traffic_Type_Detection' )
			? WPDAI_Traffic_Type_Detection::traffic_source_css_class( $source )
			: sanitize_title( $source );

		$formatted[] = array(
			'session_id'        => isset( $row['session_id'] ) ? (string) $row['session_id'] : '',
			'ip_address'        => isset( $row['ip_address'] ) ? (string) $row['ip_address'] : '',
			'user_id'           => isset( $row['user_id'] ) ? (int) $row['user_id'] : 0,
			'landing_page'      => $landing_page,
			'landing_page_path' => function_exists( 'wpdai_get_browsing_history_url_path' ) ? wpdai_get_browsing_history_url_path( $landing_page ) : $landing_page,
			'query_params'      => is_array( $query_params ) ? $query_params : array(),
			'referral_url'      => $referral_url,
			'user_agent'        => wpdai_get_debug_session_user_agent( $row['additional_data'] ?? '' ),
			'device_category'   => isset( $row['device_category'] ) ? (string) $row['device_category'] : '',
			'browser'           => isset( $row['browser'] ) ? (string) $row['browser'] : '',
			'operating_system'  => isset( $row['operating_system'] ) ? (string) $row['operating_system'] : '',
			'started_at'        => wpdai_format_browsing_history_datetime( $row['date_created_gmt'] ?? '' ),
			'ended_at'          => wpdai_format_browsing_history_datetime( $row['date_updated_gmt'] ?? '' ),
			'duration'          => wpdai_format_browsing_history_duration( $row['date_created_gmt'] ?? '', $row['date_updated_gmt'] ?? '' ),
			'traffic_source'    => $source,
			'traffic_class'     => $source_class,
			'engaged'           => ! empty( $row['engaged_session'] ),
		);
	}

	return $formatted;
}

/**
 * Load events for a set of session IDs.
 *
 * @param string             $events_table Validated events table name.
 * @param array<int, string> $session_ids  Session IDs.
 * @return array<string, array<int, array<string, mixed>>>
 */
function wpdai_get_debug_session_events( $events_table, $session_ids ) {
	global $wpdb;

	$events_by_session = array();
	$session_ids       = array_values( array_unique( array_filter( array_map( 'strval', $session_ids ) ) ) );

	if ( empty( $session_ids ) ) {
		return $events_by_session;
	}

	$placeholders = implode( ',', array_fill( 0, count( $session_ids ), '%s' ) );

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name is validated; placeholders are generated from counted arrays.
	$event_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT session_id, page_href, event_type, object_type, object_id, product_id, variation_id, event_value, event_quantity, date_created_gmt, additional_data
			FROM {$events_table}
			WHERE session_id IN ({$placeholders})
			ORDER BY date_created_gmt ASC, ID ASC",
			$session_ids
		),
		ARRAY_A
	);

	if ( ! is_array( $event_rows ) ) {
		return $events_by_session;
	}

	foreach ( $event_rows as $event_row ) {
		$session_id = isset( $event_row['session_id'] ) ? (string) $event_row['session_id'] : '';
		if ( '' === $session_id ) {
			continue;
		}

		$page_href = isset( $event_row['page_href'] ) ? (string) $event_row['page_href'] : '';

		$events_by_session[ $session_id ][] = array(
			'time'            => wpdai_format_browsing_history_datetime( $event_row['date_created_gmt'] ?? '' ),
			'type'            => sanitize_key( $event_row['event_type'] ?? '' ),
			'type_label'      => wpdai_get_browsing_history_event_label( $event_row['event_type'] ?? '' ),
			'url'             => $page_href,
			'path'            => function_exists( 'wpdai_get_browsing_history_url_path' ) ? wpdai_get_browsing_history_url_path( $page_href ) : $page_href,
			'object_type'     => sanitize_key( $event_row['object_type'] ?? '' ),
			'object_id'       => isset( $event_row['object_id'] ) ? (int) $event_row['object_id'] : 0,
			'product_id'      => isset( $event_row['product_id'] ) ? (int) $event_row['product_id'] : 0,
			'variation_id'    => isset( $event_row['variation_id'] ) ? (int) $event_row['variation_id'] : 0,
			'event_value'     => isset( $event_row['event_value'] ) ? (string) $event_row['event_value'] : '',
			'event_quantity'  => isset( $event_row['event_quantity'] ) ? (int) $event_row['event_quantity'] : 0,
			'additional_data' => wpdai_get_debug_event_additional_data( $event_row['additional_data'] ?? '' ),
		);
	}

	return $events_by_session;
}

/**
 * Build a Sessions tab URL that keeps the current filters.
 *
 * @param array<string, mixed> $args    Current request args.
 * @param array<string, mixed> $replace Extra query args to merge.
 * @return string
 */
function wpdai_get_debug_sessions_url( $args, $replace = array() ) {
	$query = array(
		'page'           => WPDAI_Admin_Menu::$settings_slug,
		'subpage'        => 'debug',
		'tab'            => 'sessions',
		'session_from'   => isset( $args['date_from'] ) ? $args['date_from'] : '',
		'session_to'     => isset( $args['date_to'] ) ? $args['date_to'] : '',
		'session_source' => isset( $args['source'] ) ? $args['source'] : '',
		'paged'          => isset( $args['paged'] ) ? (int) $args['paged'] : 1,
	);

	$query = array_merge( $query, $replace );

	if ( empty( $query['session_source'] ) ) {
		unset( $query['session_source'] );
	}

	if ( isset( $query['paged'] ) && (int) $query['paged'] < 2 ) {
		unset( $query['paged'] );
	}

	return add_query_arg( $query, admin_url( 'admin.php' ) );
}

/**
 * Render the debug Sessions tab.
 *
 * @return void
 */
function wpdai_render_debug_sessions_table() {
	$args     = wpdai_get_debug_session_request_args();
	$results  = wpdai_get_debug_sessions( $args );
	$sessions = $results['sessions'];
	$sources  = wpdai_get_debug_session_source_options();
	$from     = ( (int) $results['page'] - 1 ) * (int) $results['per_page'] + 1;
	$to       = min( (int) $results['total'], (int) $results['page'] * (int) $results['per_page'] );

	if ( $results['total'] < 1 ) {
		$from = 0;
		$to   = 0;
	}

	$reset_url = add_query_arg(
		array(
			'page'    => WPDAI_Admin_Menu::$settings_slug,
			'subpage' => 'debug',
			'tab'     => 'sessions',
		),
		admin_url( 'admin.php' )
	);
	?>
	<div class="wpd-debug-sessions" data-sessions-base-url="<?php echo esc_url( wpdai_get_debug_sessions_url( $args, array( 'paged' => 1 ) ) ); ?>">
		<div class="wpd-wrapper">
			<div class="wpd-section-heading"><?php esc_html_e( 'Sessions', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
			<p class="wpd-meta"><?php esc_html_e( 'Review raw session rows and the traffic source Alpha Insights would assign from the landing page, referral URL, and Facebook or Instagram in-app user agents. Expand a session to inspect its events.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></p>
			<div class="wpd-debug-sessions-filters">
				<label class="wpd-debug-sessions-filter">
					<span><?php esc_html_e( 'From', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></span>
					<input type="text" class="wpd-input wpd-jquery-datepicker" id="wpd-debug-session-from" value="<?php echo esc_attr( $args['date_from'] ); ?>" autocomplete="off">
				</label>
				<label class="wpd-debug-sessions-filter">
					<span><?php esc_html_e( 'To', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></span>
					<input type="text" class="wpd-input wpd-jquery-datepicker" id="wpd-debug-session-to" value="<?php echo esc_attr( $args['date_to'] ); ?>" autocomplete="off">
				</label>
				<label class="wpd-debug-sessions-filter">
					<span><?php esc_html_e( 'Session source', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></span>
					<select id="wpd-debug-session-source" class="wpd-input">
						<option value=""><?php esc_html_e( 'All sources', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></option>
						<?php foreach ( $sources as $source_slug => $source_label ) : ?>
							<option value="<?php echo esc_attr( $source_slug ); ?>" <?php selected( $args['source'], $source_slug ); ?>><?php echo esc_html( $source_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<div class="wpd-debug-sessions-filter-actions">
					<button type="button" class="button button-primary wpd-debug-sessions-apply"><?php esc_html_e( 'Apply filters', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></button>
					<a class="button button-secondary" href="<?php echo esc_url( $reset_url ); ?>"><?php esc_html_e( 'Reset', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></a>
				</div>
			</div>
		</div>
		<div class="wpd-wrapper">
			<div class="wpd-debug-sessions-summary">
				<span>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: first result number, 2: last result number, 3: total sessions */
							__( 'Showing %1$s–%2$s of %3$s sessions', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
							number_format_i18n( $from ),
							number_format_i18n( $to ),
							number_format_i18n( $results['total'] )
						)
					);
					?>
				</span>
				<button type="button" class="button button-secondary wpd-debug-sessions-export">
					<?php esc_html_e( 'Export to CSV', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
				</button>
			</div>
			<div id="wpd-debug-sessions-export-modal" class="wpd-debug-sessions-modal" hidden>
				<div class="wpd-debug-sessions-modal-overlay" data-wpd-sessions-export-close="1"></div>
				<div class="wpd-debug-sessions-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="wpd-debug-sessions-export-title">
					<button type="button" class="wpd-debug-sessions-modal-close" data-wpd-sessions-export-close="1" aria-label="<?php esc_attr_e( 'Close', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>">&times;</button>
					<h2 id="wpd-debug-sessions-export-title"><?php esc_html_e( 'Export to CSV', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></h2>
					<p class="wpd-meta"><?php esc_html_e( 'Download every session that matches the current filters, not just this page. Choose a flat table:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></p>
					<div class="wpd-debug-sessions-export-actions">
						<a class="button button-primary" href="<?php echo esc_url( wpdai_get_debug_sessions_export_url( $args, 'sessions' ) ); ?>">
							<?php esc_html_e( 'Download session data', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
						</a>
						<a class="button button-secondary" href="<?php echo esc_url( wpdai_get_debug_sessions_export_url( $args, 'events' ) ); ?>">
							<?php esc_html_e( 'Download event data', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
						</a>
					</div>
				</div>
			</div>
			<?php if ( empty( $sessions ) ) : ?>
				<p class="wpd-meta"><?php esc_html_e( 'No sessions matched these filters.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></p>
			<?php else : ?>
				<div class="wpd-debug-sessions-table-wrap">
					<table class="wpd-table widefat wpd-debug-sessions-table">
						<thead>
							<tr>
								<th class="wpd-debug-sessions-col-toggle"><span class="screen-reader-text"><?php esc_html_e( 'Expand', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></span></th>
								<th><?php esc_html_e( 'Session ID', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Started', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'IP address', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Landing page', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'User agent', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Source', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $sessions as $index => $session ) : ?>
								<?php
								$row_id      = 'wpd-debug-session-' . $index;
								$session_id  = $session['session_id'];
								$short_id    = strlen( $session_id ) > 12 ? substr( $session_id, 0, 12 ) . '…' : $session_id;
								$user_agent  = $session['user_agent'];
								$short_ua    = strlen( $user_agent ) > 72 ? substr( $user_agent, 0, 72 ) . '…' : $user_agent;
								?>
								<tr class="wpd-debug-session-row" data-session-row="<?php echo esc_attr( $row_id ); ?>">
									<td>
										<button type="button" class="button-link wpd-debug-session-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $row_id ); ?>">
											<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
											<span class="screen-reader-text"><?php esc_html_e( 'Show session events', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></span>
										</button>
									</td>
									<td>
										<code class="wpd-debug-session-id" title="<?php echo esc_attr( $session_id ); ?>"><?php echo esc_html( $short_id ); ?></code>
										<?php if ( $session['event_count'] > 0 ) : ?>
											<div class="wpd-meta">
												<?php
												echo esc_html(
													sprintf(
														/* translators: %d: Number of events */
														_n( '%d event', '%d events', $session['event_count'], 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
														$session['event_count']
													)
												);
												?>
											</div>
										<?php endif; ?>
									</td>
									<td>
										<?php echo esc_html( $session['started_at'] ); ?>
										<?php if ( $session['duration'] ) : ?>
											<div class="wpd-meta"><?php echo esc_html( $session['duration'] ); ?></div>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $session['ip_address'] ); ?></td>
									<td>
										<div class="wpd-debug-session-landing" title="<?php echo esc_attr( $session['landing_page'] ); ?>">
											<?php echo esc_html( $session['landing_page_path'] ? $session['landing_page_path'] : $session['landing_page'] ); ?>
										</div>
										<?php if ( ! empty( $session['query_params'] ) ) : ?>
											<dl class="wpd-debug-session-params">
												<?php foreach ( $session['query_params'] as $param_key => $param_value ) : ?>
													<?php
													if ( is_array( $param_value ) ) {
														$param_value = wp_json_encode( $param_value );
													}
													?>
													<div>
														<dt><?php echo esc_html( (string) $param_key ); ?></dt>
														<dd><?php echo esc_html( (string) $param_value ); ?></dd>
													</div>
												<?php endforeach; ?>
											</dl>
										<?php endif; ?>
									</td>
									<td>
										<span title="<?php echo esc_attr( $user_agent ); ?>"><?php echo esc_html( $short_ua ); ?></span>
									</td>
									<td>
										<mark class="wpd-order-acquisition-channel order-status <?php echo esc_attr( $session['traffic_class'] ); ?>">
											<span><?php echo esc_html( $session['traffic_source'] ); ?></span>
										</mark>
									</td>
								</tr>
								<tr id="<?php echo esc_attr( $row_id ); ?>" class="wpd-debug-session-events-row" hidden>
									<td colspan="7">
										<?php wpdai_render_debug_session_events_panel( $session ); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php wpdai_render_debug_sessions_pagination( $args, $results ); ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Render the fold-out debug panel for one session.
 *
 * @param array<string, mixed> $session Formatted session.
 * @return void
 */
function wpdai_render_debug_session_events_panel( $session ) {
	$events = isset( $session['events'] ) && is_array( $session['events'] ) ? $session['events'] : array();
	?>
	<div class="wpd-debug-session-panel">
		<div class="wpd-debug-session-meta">
			<div>
				<strong><?php esc_html_e( 'Session ID', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></strong>
				<code><?php echo esc_html( $session['session_id'] ); ?></code>
			</div>
			<div>
				<strong><?php esc_html_e( 'Landing page', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></strong>
				<span><?php echo esc_html( $session['landing_page'] ? $session['landing_page'] : '—' ); ?></span>
			</div>
			<div>
				<strong><?php esc_html_e( 'Referral URL', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></strong>
				<span><?php echo esc_html( $session['referral_url'] ? $session['referral_url'] : __( 'None', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?></span>
			</div>
			<div>
				<strong><?php esc_html_e( 'User agent', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></strong>
				<span><?php echo esc_html( $session['user_agent'] ? $session['user_agent'] : '—' ); ?></span>
			</div>
			<div>
				<strong><?php esc_html_e( 'Device', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></strong>
				<span>
					<?php
					$device_bits = array_filter(
						array(
							$session['device_category'],
							$session['browser'],
							$session['operating_system'],
						)
					);
					echo esc_html( $device_bits ? implode( ' · ', $device_bits ) : '—' );
					?>
				</span>
			</div>
			<div>
				<strong><?php esc_html_e( 'Session window', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></strong>
				<span>
					<?php
					echo esc_html(
						trim(
							$session['started_at']
							. ( $session['ended_at'] ? ' – ' . $session['ended_at'] : '' )
							. ( $session['duration'] ? ' (' . $session['duration'] . ')' : '' )
						)
					);
					?>
				</span>
			</div>
		</div>
		<?php if ( empty( $events ) ) : ?>
			<p class="wpd-meta"><?php esc_html_e( 'No events were stored for this session.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></p>
		<?php else : ?>
			<table class="wpd-table widefat wpd-debug-session-events">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Event', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Page', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Object', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Value', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Additional data', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $events as $event ) : ?>
						<?php
						$object_bits = array();
						if ( ! empty( $event['object_type'] ) ) {
							$object_bits[] = $event['object_type'];
						}
						if ( ! empty( $event['object_id'] ) ) {
							$object_bits[] = '#' . $event['object_id'];
						}
						if ( ! empty( $event['product_id'] ) ) {
							$object_bits[] = 'product #' . $event['product_id'];
						}
						if ( ! empty( $event['variation_id'] ) ) {
							$object_bits[] = 'variation #' . $event['variation_id'];
						}
						$value_bits = array();
						if ( '' !== $event['event_value'] && null !== $event['event_value'] ) {
							$value_bits[] = $event['event_value'];
						}
						if ( ! empty( $event['event_quantity'] ) && 1 !== (int) $event['event_quantity'] ) {
							$value_bits[] = 'x' . (int) $event['event_quantity'];
						}
						?>
						<tr>
							<td><?php echo esc_html( $event['time'] ); ?></td>
							<td><?php echo esc_html( $event['type_label'] ); ?></td>
							<td title="<?php echo esc_attr( $event['url'] ); ?>"><?php echo esc_html( $event['path'] ? $event['path'] : $event['url'] ); ?></td>
							<td><?php echo esc_html( $object_bits ? implode( ' ', $object_bits ) : '—' ); ?></td>
							<td><?php echo esc_html( $value_bits ? implode( ' ', $value_bits ) : '—' ); ?></td>
							<td>
								<?php if ( ! empty( $event['additional_data'] ) ) : ?>
									<pre class="wpd-debug-session-event-json"><?php echo esc_html( wp_json_encode( $event['additional_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render pagination for the debug Sessions tab.
 *
 * @param array<string, mixed> $args    Current request args.
 * @param array<string, mixed> $results Query results.
 * @return void
 */
function wpdai_render_debug_sessions_pagination( $args, $results ) {
	$total_pages = (int) $results['total_pages'];
	$page        = (int) $results['page'];

	if ( $total_pages < 2 ) {
		return;
	}

	$links = array();
	$start = max( 1, $page - 2 );
	$end   = min( $total_pages, $page + 2 );

	if ( $page > 1 ) {
		$links[] = array(
			'url'      => wpdai_get_debug_sessions_url( $args, array( 'paged' => $page - 1 ) ),
			'label'    => __( 'Previous', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'current'  => false,
		);
	}

	if ( $start > 1 ) {
		$links[] = array(
			'url'     => wpdai_get_debug_sessions_url( $args, array( 'paged' => 1 ) ),
			'label'   => '1',
			'current' => false,
		);
		if ( $start > 2 ) {
			$links[] = array(
				'url'     => '',
				'label'   => '…',
				'current' => false,
			);
		}
	}

	for ( $i = $start; $i <= $end; $i++ ) {
		$links[] = array(
			'url'     => wpdai_get_debug_sessions_url( $args, array( 'paged' => $i ) ),
			'label'   => (string) $i,
			'current' => ( $i === $page ),
		);
	}

	if ( $end < $total_pages ) {
		if ( $end < $total_pages - 1 ) {
			$links[] = array(
				'url'     => '',
				'label'   => '…',
				'current' => false,
			);
		}
		$links[] = array(
			'url'     => wpdai_get_debug_sessions_url( $args, array( 'paged' => $total_pages ) ),
			'label'   => (string) $total_pages,
			'current' => false,
		);
	}

	if ( $page < $total_pages ) {
		$links[] = array(
			'url'     => wpdai_get_debug_sessions_url( $args, array( 'paged' => $page + 1 ) ),
			'label'   => __( 'Next', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			'current' => false,
		);
	}
	?>
	<nav class="wpd-debug-sessions-pagination" aria-label="<?php esc_attr_e( 'Sessions pagination', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>">
		<?php foreach ( $links as $link ) : ?>
			<?php if ( '' === $link['url'] ) : ?>
				<span class="wpd-debug-sessions-page-ellipsis"><?php echo esc_html( $link['label'] ); ?></span>
			<?php elseif ( $link['current'] ) : ?>
				<span class="button button-primary" aria-current="page"><?php echo esc_html( $link['label'] ); ?></span>
			<?php else : ?>
				<a class="button button-secondary" href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a>
			<?php endif; ?>
		<?php endforeach; ?>
	</nav>
	<?php
}

/**
 * Build a nonce-protected CSV export URL for the current filters.
 *
 * @param array<string, mixed> $args        Current request args.
 * @param string               $export_type sessions|events.
 * @return string
 */
function wpdai_get_debug_sessions_export_url( $args, $export_type ) {
	$query = array(
		'action'         => 'wpd_export_debug_sessions_csv',
		'export_type'    => ( 'events' === $export_type ) ? 'events' : 'sessions',
		'session_from'   => isset( $args['date_from'] ) ? $args['date_from'] : '',
		'session_to'     => isset( $args['date_to'] ) ? $args['date_to'] : '',
		'session_source' => isset( $args['source'] ) ? $args['source'] : '',
	);

	if ( '' === $query['session_source'] ) {
		unset( $query['session_source'] );
	}

	return wp_nonce_url(
		add_query_arg( $query, admin_url( 'admin-post.php' ) ),
		'wpd_export_debug_sessions_csv'
	);
}

/**
 * Flatten query params for a CSV cell.
 *
 * @param array<string, mixed> $params Query parameters.
 * @return string
 */
function wpdai_debug_session_params_to_string( $params ) {
	if ( ! is_array( $params ) || empty( $params ) ) {
		return '';
	}

	return (string) http_build_query( $params, '', '&' );
}

/**
 * Handle a debug sessions CSV download.
 *
 * @return void
 */
function wpdai_export_debug_sessions_csv() {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpd_export_debug_sessions_csv' ) ) {
		wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) );
	}

	if ( ! function_exists( 'wpdai_is_user_authorized_to_use_alpha_insights' ) || ! wpdai_is_user_authorized_to_use_alpha_insights() ) {
		wp_die( esc_html__( 'You do not have permission to perform this action.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) );
	}

	$export_type = isset( $_GET['export_type'] ) ? sanitize_key( wp_unslash( $_GET['export_type'] ) ) : 'sessions';
	if ( 'events' !== $export_type ) {
		$export_type = 'sessions';
	}

	$args           = wpdai_get_debug_session_request_args();
	$args['export'] = true;

	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 0 );
	}

	$filename = ( 'events' === $export_type )
		? 'alpha-insights-session-events-' . gmdate( 'Y-m-d' ) . '.csv'
		: 'alpha-insights-sessions-' . gmdate( 'Y-m-d' ) . '.csv';

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

	echo "\xEF\xBB\xBF";

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Direct output to browser for CSV download.
	$output = fopen( 'php://output', 'w' );
	if ( ! $output ) {
		wp_die( esc_html__( 'Could not start the CSV download.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) );
	}

	if ( 'events' === $export_type ) {
		wpdai_write_debug_events_csv( $output, $args );
	} else {
		wpdai_write_debug_sessions_csv( $output, $args );
	}

	fclose( $output );
	exit;
}
add_action( 'admin_post_wpd_export_debug_sessions_csv', 'wpdai_export_debug_sessions_csv' );

/**
 * Write the flat sessions CSV.
 *
 * @param resource             $output Output stream.
 * @param array<string, mixed> $args   Export args.
 * @return void
 */
function wpdai_write_debug_sessions_csv( $output, $args ) {
	fputcsv(
		$output,
		array(
			__( 'Session ID', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Started', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Ended', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Duration', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'IP address', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'User ID', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Landing page', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Landing page path', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Query parameters', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Referral URL', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Source', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'User agent', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Device', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Browser', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Operating system', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Engaged', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		)
	);

	$query    = wpdai_query_debug_session_raw_rows( $args );
	$sessions = ! empty( $query['ok'] ) ? wpdai_format_debug_sessions( $query['rows'], $query['source_label'] ) : array();

	foreach ( $sessions as $session ) {
		fputcsv(
			$output,
			array(
				$session['session_id'],
				$session['started_at'],
				$session['ended_at'],
				$session['duration'],
				$session['ip_address'],
				$session['user_id'],
				$session['landing_page'],
				$session['landing_page_path'],
				wpdai_debug_session_params_to_string( $session['query_params'] ),
				$session['referral_url'],
				$session['traffic_source'],
				$session['user_agent'],
				$session['device_category'],
				$session['browser'],
				$session['operating_system'],
				! empty( $session['engaged'] ) ? __( 'Yes', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) : __( 'No', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			)
		);
	}
}

/**
 * Write the flat events CSV for all filtered sessions.
 *
 * @param resource             $output Output stream.
 * @param array<string, mixed> $args   Export args.
 * @return void
 */
function wpdai_write_debug_events_csv( $output, $args ) {
	fputcsv(
		$output,
		array(
			__( 'Session ID', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Session source', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Session IP', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Time', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Event', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Event type', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Page', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Page path', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Object type', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Object ID', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Product ID', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Variation ID', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Value', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Quantity', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
			__( 'Additional data', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
		)
	);

	$query = wpdai_query_debug_session_raw_rows( $args );
	if ( empty( $query['ok'] ) || empty( $query['rows'] ) ) {
		return;
	}

	$sessions    = wpdai_format_debug_sessions( $query['rows'], $query['source_label'] );
	$session_map = array();
	$session_ids = array();

	foreach ( $sessions as $session ) {
		if ( empty( $session['session_id'] ) ) {
			continue;
		}
		$session_ids[] = $session['session_id'];
		$session_map[ $session['session_id'] ] = array(
			'source'     => $session['traffic_source'],
			'ip_address' => $session['ip_address'],
		);
	}

	$chunk_size = 200;
	foreach ( array_chunk( $session_ids, $chunk_size ) as $chunk ) {
		$events_by_session = wpdai_get_debug_session_events( $query['events_table'], $chunk );

		foreach ( $chunk as $session_id ) {
			$events      = isset( $events_by_session[ $session_id ] ) ? $events_by_session[ $session_id ] : array();
			$session_meta = isset( $session_map[ $session_id ] ) ? $session_map[ $session_id ] : array(
				'source'     => '',
				'ip_address' => '',
			);

			foreach ( $events as $event ) {
				$additional = '';
				if ( ! empty( $event['additional_data'] ) ) {
					$additional = wp_json_encode( $event['additional_data'], JSON_UNESCAPED_SLASHES );
				}

				fputcsv(
					$output,
					array(
						$session_id,
						$session_meta['source'],
						$session_meta['ip_address'],
						$event['time'],
						$event['type_label'],
						$event['type'],
						$event['url'],
						$event['path'],
						$event['object_type'],
						$event['object_id'] ? $event['object_id'] : '',
						$event['product_id'] ? $event['product_id'] : '',
						$event['variation_id'] ? $event['variation_id'] : '',
						$event['event_value'],
						$event['event_quantity'],
						$additional,
					)
				);
			}
		}
	}
}
