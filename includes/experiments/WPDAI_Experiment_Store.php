<?php
/**
 * CRUD for A/B experiments, variants, and assignments.
 *
 * @package Alpha Insights
 * @since 5.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPDAI_Experiment_Store
 */
class WPDAI_Experiment_Store {

	/**
	 * Config version option — bumped when running experiments change.
	 *
	 * @var string
	 */
	const CONFIG_VERSION_OPTION = 'wpd_ai_experiments_config_version';

	/**
	 * Experiments table name.
	 *
	 * @return string
	 */
	public static function experiments_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpd_ai_experiments';
	}

	/**
	 * Variants table name.
	 *
	 * @return string
	 */
	public static function variants_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpd_ai_experiment_variants';
	}

	/**
	 * Assignments table name.
	 *
	 * @return string
	 */
	public static function assignments_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpd_ai_experiment_assignments';
	}

	/**
	 * Whether experiment tables exist (static-cached per request).
	 *
	 * @return bool
	 */
	public static function tables_exist() {
		static $exists = null;
		if ( null !== $exists ) {
			return $exists;
		}

		global $wpdb;
		$table  = self::experiments_table();
		$found  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		$exists = ( $found === $table );
		return $exists;
	}

	/**
	 * Bump the public config version.
	 *
	 * @return void
	 */
	public static function bump_config_version() {
		update_option( self::CONFIG_VERSION_OPTION, (string) time(), false );
	}

	/**
	 * Get a running-experiments cache key.
	 *
	 * @return string
	 */
	public static function running_cache_key() {
		return 'wpd_ai_running_experiments';
	}

	/**
	 * Clear runtime caches.
	 *
	 * @return void
	 */
	public static function flush_runtime_cache() {
		wp_cache_delete( self::running_cache_key(), 'alpha_insights' );
		self::bump_config_version();
	}

	/**
	 * Decode a JSON column to an array.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	protected static function decode_json_array( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}
		$decoded = json_decode( $value, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Normalize a DB experiment row.
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function hydrate_experiment( $row ) {
		if ( ! is_array( $row ) ) {
			return array();
		}

		$row['id']              = isset( $row['id'] ) ? (int) $row['id'] : 0;
		$row['traffic_percent'] = isset( $row['traffic_percent'] ) ? (int) $row['traffic_percent'] : 100;
		$row['targeting']       = wp_parse_args( self::decode_json_array( $row['targeting'] ?? '' ), wpdai_get_experiment_default_targeting() );
		$row['goals']           = wp_parse_args( self::decode_json_array( $row['goals'] ?? '' ), wpdai_get_experiment_default_goals() );
		$row['settings']        = wp_parse_args( self::decode_json_array( $row['settings'] ?? '' ), wpdai_get_experiment_default_settings() );
		$row['variants']        = isset( $row['variants'] ) && is_array( $row['variants'] ) ? $row['variants'] : array();

		return $row;
	}

	/**
	 * Get one experiment by ID, with variants.
	 *
	 * @param int $id Experiment ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( $id < 1 || ! self::tables_exist() ) {
			return null;
		}

		$table = self::experiments_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefix-based and trusted.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}

		$experiment             = self::hydrate_experiment( $row );
		$experiment['variants'] = self::get_variants( $id );
		return $experiment;
	}

	/**
	 * Get one experiment by slug.
	 *
	 * @param string $slug Slug.
	 * @return array|null
	 */
	public static function get_by_slug( $slug ) {
		global $wpdb;
		$slug = sanitize_title( $slug );
		if ( '' === $slug || ! self::tables_exist() ) {
			return null;
		}

		$table = self::experiments_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefix-based and trusted.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}

		$experiment             = self::hydrate_experiment( $row );
		$experiment['variants'] = self::get_variants( (int) $experiment['id'] );
		return $experiment;
	}

	/**
	 * List experiments (no variants unless requested).
	 *
	 * @param bool $with_variants Include variants.
	 * @return array
	 */
	public static function get_all( $with_variants = false ) {
		global $wpdb;
		if ( ! self::tables_exist() ) {
			return array();
		}
		$table = self::experiments_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefix-based and trusted.
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$experiments = array();
		foreach ( $rows as $row ) {
			$experiment = self::hydrate_experiment( $row );
			if ( $with_variants ) {
				$experiment['variants'] = self::get_variants( (int) $experiment['id'] );
			}
			$experiments[] = $experiment;
		}

		return $experiments;
	}

	/**
	 * Running experiments with variants (cached per request + object cache).
	 *
	 * @return array
	 */
	public static function get_running() {
		$cached = wp_cache_get( self::running_cache_key(), 'alpha_insights' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		if ( ! self::tables_exist() ) {
			return array();
		}
		$table = self::experiments_table();
		$now   = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefix-based and trusted.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s AND ( start_gmt IS NULL OR start_gmt = %s OR start_gmt <= %s ) AND ( end_gmt IS NULL OR end_gmt = %s OR end_gmt >= %s ) ORDER BY id ASC",
				'running',
				'0000-00-00 00:00:00',
				$now,
				'0000-00-00 00:00:00',
				$now
			),
			ARRAY_A
		);

		$experiments = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$experiment             = self::hydrate_experiment( $row );
				$experiment['variants'] = self::get_variants( (int) $experiment['id'] );
				$experiments[]          = $experiment;
			}
		}

		wp_cache_set( self::running_cache_key(), $experiments, 'alpha_insights', MINUTE_IN_SECONDS );
		return $experiments;
	}

	/**
	 * Count currently running experiments.
	 *
	 * @param int $exclude_id Optional ID to exclude.
	 * @return int
	 */
	public static function count_running( $exclude_id = 0 ) {
		$count = 0;
		foreach ( self::get_running() as $experiment ) {
			if ( $exclude_id && (int) $experiment['id'] === (int) $exclude_id ) {
				continue;
			}
			++$count;
		}
		return $count;
	}

	/**
	 * Variants for an experiment.
	 *
	 * @param int $experiment_id Experiment ID.
	 * @return array
	 */
	public static function get_variants( $experiment_id ) {
		global $wpdb;
		$experiment_id = absint( $experiment_id );
		if ( $experiment_id < 1 || ! self::tables_exist() ) {
			return array();
		}

		$table = self::variants_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefix-based and trusted.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE experiment_id = %d ORDER BY is_control DESC, id ASC",
				$experiment_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Insert or update an experiment and its variants.
	 *
	 * @param array $data Experiment data.
	 * @return int|WP_Error Experiment ID.
	 */
	public static function save( $data ) {
		global $wpdb;

		if ( ! self::tables_exist() ) {
			return new WP_Error( 'db_error', __( 'Experiment tables are not installed yet. Deactivate and reactivate Alpha Insights, or wait for the database upgrade to finish.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) );
		}

		$id     = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$name   = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		$slug   = isset( $data['slug'] ) ? sanitize_title( $data['slug'] ) : '';
		$status = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'draft';

		if ( '' === $name ) {
			return new WP_Error( 'missing_name', __( 'Experiment name is required.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) );
		}

		if ( '' === $slug ) {
			$slug = sanitize_title( $name );
		}
		if ( '' === $slug ) {
			$slug = 'experiment-' . time();
		}

		$slug = self::ensure_unique_slug( $slug, $id );

		if ( ! in_array( $status, wpdai_get_experiment_statuses(), true ) ) {
			$status = 'draft';
		}

		$traffic_percent = isset( $data['traffic_percent'] ) ? absint( $data['traffic_percent'] ) : 100;
		if ( $traffic_percent < 1 ) {
			$traffic_percent = 1;
		}
		if ( $traffic_percent > 100 ) {
			$traffic_percent = 100;
		}

		$now        = current_time( 'mysql', true );
		$table      = self::experiments_table();
		$row        = array(
			'name'            => $name,
			'slug'            => $slug,
			'status'          => $status,
			'hypothesis'      => isset( $data['hypothesis'] ) ? sanitize_textarea_field( $data['hypothesis'] ) : '',
			'traffic_percent' => $traffic_percent,
			'start_gmt'       => self::sanitize_datetime( $data['start_gmt'] ?? '' ),
			'end_gmt'         => self::sanitize_datetime( $data['end_gmt'] ?? '' ),
			'targeting'       => wp_json_encode( isset( $data['targeting'] ) && is_array( $data['targeting'] ) ? $data['targeting'] : wpdai_get_experiment_default_targeting() ),
			'goals'           => wp_json_encode( isset( $data['goals'] ) && is_array( $data['goals'] ) ? $data['goals'] : wpdai_get_experiment_default_goals() ),
			'settings'        => wp_json_encode( isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : wpdai_get_experiment_default_settings() ),
			'updated_gmt'     => $now,
		);
		$formats    = array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' );

		$previous_status = '';
		if ( $id > 0 ) {
			$existing = self::get( $id );
			if ( ! $existing ) {
				return new WP_Error( 'not_found', __( 'Experiment not found.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) );
			}
			$previous_status = $existing['status'];
			$wpdb->update( $table, $row, array( 'id' => $id ), $formats, array( '%d' ) );
		} else {
			$row['created_gmt'] = $now;
			$formats[]          = '%s';
			$wpdb->insert( $table, $row, $formats );
			$id = (int) $wpdb->insert_id;
		}

		if ( $id < 1 || $wpdb->last_error ) {
			return new WP_Error( 'db_error', $wpdb->last_error ? $wpdb->last_error : __( 'Could not save the experiment.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) );
		}

		$variants = isset( $data['variants'] ) && is_array( $data['variants'] ) ? $data['variants'] : array();
		self::save_variants( $id, $variants );
		self::flush_runtime_cache();

		if ( $previous_status !== $status && class_exists( 'WPDAI_Experiment_Cache' ) ) {
			WPDAI_Experiment_Cache::on_status_change( self::get( $id ), $previous_status );
		}

		return $id;
	}

	/**
	 * Replace variants for an experiment.
	 *
	 * @param int   $experiment_id Experiment ID.
	 * @param array $variants      Variant rows from the form.
	 * @return void
	 */
	protected static function save_variants( $experiment_id, $variants ) {
		global $wpdb;

		$experiment_id = absint( $experiment_id );
		$table         = self::variants_table();
		$keep_ids      = array();
		$used_keys     = array();
		$now           = current_time( 'mysql', true );
		$saw_control   = false;

		foreach ( $variants as $index => $variant ) {
			if ( ! is_array( $variant ) ) {
				continue;
			}

			$variant_id  = isset( $variant['id'] ) ? absint( $variant['id'] ) : 0;
			$variant_key = isset( $variant['variant_key'] ) ? sanitize_key( $variant['variant_key'] ) : '';
			if ( '' === $variant_key ) {
				$variant_key = ( 0 === (int) $index ) ? 'control' : 'treatment';
			}
			if ( isset( $used_keys[ $variant_key ] ) ) {
				$variant_key = $variant_key . '_' . ( (int) $index + 1 );
			}
			$used_keys[ $variant_key ] = true;

			$is_control = ! empty( $variant['is_control'] ) || 'control' === $variant_key;
			if ( $saw_control ) {
				$is_control = false;
				if ( 'control' === $variant_key ) {
					$variant_key = 'treatment';
				}
			}
			if ( $is_control ) {
				$saw_control = true;
				$variant_key = 'control';
			}

			$weight = isset( $variant['weight'] ) ? absint( $variant['weight'] ) : 50;
			if ( $weight < 0 ) {
				$weight = 0;
			}
			if ( $weight > 100 ) {
				$weight = 100;
			}

			$row = array(
				'experiment_id' => $experiment_id,
				'variant_key'   => $variant_key,
				'name'          => isset( $variant['name'] ) ? sanitize_text_field( $variant['name'] ) : ucfirst( $variant_key ),
				'weight'        => $weight,
				'is_control'    => $is_control ? 1 : 0,
				'css'           => isset( $variant['css'] ) ? (string) $variant['css'] : '',
				'js'            => isset( $variant['js'] ) ? (string) $variant['js'] : '',
				'php'           => isset( $variant['php'] ) ? (string) $variant['php'] : '',
			);

			if ( $variant_id > 0 ) {
				$wpdb->update( $table, $row, array( 'id' => $variant_id, 'experiment_id' => $experiment_id ) );
				$keep_ids[] = $variant_id;
			} else {
				$row['created_gmt'] = $now;
				$wpdb->insert( $table, $row );
				if ( $wpdb->insert_id ) {
					$keep_ids[] = (int) $wpdb->insert_id;
				}
			}
		}

		if ( empty( $keep_ids ) ) {
			self::create_default_variants( $experiment_id );
			return;
		}

		$ids_placeholder = implode( ',', array_map( 'absint', $keep_ids ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IDs are absint-cast.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE experiment_id = %d AND id NOT IN ({$ids_placeholder})", $experiment_id ) );
	}

	/**
	 * Default A/B variants.
	 *
	 * @param int $experiment_id Experiment ID.
	 * @return void
	 */
	public static function create_default_variants( $experiment_id ) {
		global $wpdb;
		$table = self::variants_table();
		$now   = current_time( 'mysql', true );

		$wpdb->insert(
			$table,
			array(
				'experiment_id' => absint( $experiment_id ),
				'variant_key'   => 'control',
				'name'          => __( 'Control', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'weight'        => 50,
				'is_control'    => 1,
				'css'           => '',
				'js'            => '',
				'php'           => '',
				'created_gmt'   => $now,
			)
		);
		$wpdb->insert(
			$table,
			array(
				'experiment_id' => absint( $experiment_id ),
				'variant_key'   => 'treatment',
				'name'          => __( 'Treatment', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
				'weight'        => 50,
				'is_control'    => 0,
				'css'           => '',
				'js'            => '',
				'php'           => '',
				'created_gmt'   => $now,
			)
		);
	}

	/**
	 * Delete an experiment and its variants/assignments.
	 *
	 * @param int $id Experiment ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( $id < 1 || ! self::tables_exist() ) {
			return false;
		}

		$existing = self::get( $id );
		$wpdb->delete( self::assignments_table(), array( 'experiment_id' => $id ), array( '%d' ) );
		$wpdb->delete( self::variants_table(), array( 'experiment_id' => $id ), array( '%d' ) );
		$deleted = (bool) $wpdb->delete( self::experiments_table(), array( 'id' => $id ), array( '%d' ) );
		self::flush_runtime_cache();

		if ( $deleted && $existing && class_exists( 'WPDAI_Experiment_Cache' ) ) {
			WPDAI_Experiment_Cache::on_status_change( $existing, $existing['status'] );
		}

		return $deleted;
	}

	/**
	 * Set status.
	 *
	 * @param int    $id     Experiment ID.
	 * @param string $status New status.
	 * @return int|WP_Error
	 */
	public static function set_status( $id, $status ) {
		$experiment = self::get( $id );
		if ( ! $experiment ) {
			return new WP_Error( 'not_found', __( 'Experiment not found.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) );
		}
		$experiment['status'] = $status;
		return self::save( $experiment );
	}

	/**
	 * Get an assignment row.
	 *
	 * @param int    $experiment_id Experiment ID.
	 * @param string $visitor_id    Visitor ID.
	 * @return array|null
	 */
	public static function get_assignment( $experiment_id, $visitor_id ) {
		global $wpdb;
		if ( ! self::tables_exist() ) {
			return null;
		}
		$table = self::assignments_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefix-based and trusted.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE experiment_id = %d AND visitor_id = %s",
				absint( $experiment_id ),
				sanitize_text_field( $visitor_id )
			),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	/**
	 * Insert or update an assignment.
	 *
	 * @param array $data Assignment data.
	 * @return int
	 */
	public static function upsert_assignment( $data ) {
		global $wpdb;
		if ( ! self::tables_exist() ) {
			return 0;
		}
		$table = self::assignments_table();
		$now   = current_time( 'mysql', true );

		$experiment_id = absint( $data['experiment_id'] ?? 0 );
		$visitor_id    = sanitize_text_field( $data['visitor_id'] ?? '' );
		if ( $experiment_id < 1 || '' === $visitor_id ) {
			return 0;
		}

		$existing = self::get_assignment( $experiment_id, $visitor_id );
		$row      = array(
			'experiment_id' => $experiment_id,
			'variant_key'   => sanitize_key( $data['variant_key'] ?? '' ),
			'visitor_id'    => $visitor_id,
			'session_id'    => sanitize_text_field( $data['session_id'] ?? '' ),
			'eligible'      => ! empty( $data['eligible'] ) ? 1 : 0,
			'holdout'       => ! empty( $data['holdout'] ) ? 1 : 0,
		);

		if ( $existing ) {
			if ( empty( $existing['exposed_gmt'] ) && ! empty( $data['exposed'] ) ) {
				$row['exposed_gmt'] = $now;
			}
			if ( '' === (string) $existing['session_id'] && '' !== (string) $row['session_id'] ) {
				$row['session_id'] = $row['session_id'];
			} elseif ( '' !== (string) $existing['session_id'] && '' === (string) $row['session_id'] ) {
				unset( $row['session_id'] );
			}
			$wpdb->update(
				$table,
				$row,
				array(
					'experiment_id' => $experiment_id,
					'visitor_id'    => $visitor_id,
				)
			);
			return (int) $existing['id'];
		}

		$row['assigned_gmt'] = $now;
		$row['exposed_gmt']  = ! empty( $data['exposed'] ) ? $now : null;
		$wpdb->insert( $table, $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Unique slug helper.
	 *
	 * @param string $slug Slug.
	 * @param int    $id   Current ID.
	 * @return string
	 */
	protected static function ensure_unique_slug( $slug, $id ) {
		global $wpdb;
		if ( ! self::tables_exist() ) {
			return $slug;
		}
		$table    = self::experiments_table();
		$original = $slug;
		$i        = 2;

		while ( true ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefix-based and trusted.
			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE slug = %s AND id != %d LIMIT 1",
					$slug,
					absint( $id )
				)
			);
			if ( ! $found ) {
				return $slug;
			}
			$slug = $original . '-' . $i;
			++$i;
		}
	}

	/**
	 * Sanitize a GMT datetime string.
	 *
	 * @param string $value Datetime.
	 * @return string|null
	 */
	protected static function sanitize_datetime( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return null;
		}
		$timestamp = strtotime( $value );
		if ( ! $timestamp ) {
			return null;
		}
		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
