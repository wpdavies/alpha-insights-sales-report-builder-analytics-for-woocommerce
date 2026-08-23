<?php
/**
 * Sticky hashing and assignment persistence.
 *
 * @package Alpha Insights
 * @since 5.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPDAI_Experiment_Assigner
 */
class WPDAI_Experiment_Assigner {

	/**
	 * Assign a visitor to an experiment.
	 *
	 * @param array  $experiment Experiment with variants.
	 * @param string $visitor_id Visitor ID.
	 * @param string $session_id Analytics session ID.
	 * @param bool   $log_events Whether to write analytics events.
	 * @return array { eligible, holdout, variant_key, variant, created }
	 */
	public static function assign( $experiment, $visitor_id, $session_id = '', $log_events = true ) {
		$result = array(
			'eligible'    => false,
			'holdout'     => false,
			'variant_key' => '',
			'variant'     => null,
			'created'     => false,
		);

		if ( empty( $experiment['id'] ) || '' === $visitor_id ) {
			return $result;
		}

		$existing = WPDAI_Experiment_Store::get_assignment( (int) $experiment['id'], $visitor_id );
		if ( $existing ) {
			$result['eligible']    = ! empty( $existing['eligible'] );
			$result['holdout']     = ! empty( $existing['holdout'] );
			$result['variant_key'] = (string) $existing['variant_key'];
			$result['variant']     = self::find_variant( $experiment, $result['variant_key'] );
			if ( empty( $result['variant'] ) && '' !== $result['variant_key'] && empty( $result['holdout'] ) ) {
				$result['variant']     = self::find_control_variant( $experiment );
				$result['variant_key'] = $result['variant'] ? $result['variant']['variant_key'] : $result['variant_key'];
			}

			$needs_exposure = $result['eligible'] && ! $result['holdout'] && empty( $existing['exposed_gmt'] );
			$needs_session  = '' !== $session_id && '' === (string) $existing['session_id'];

			if ( $needs_exposure || $needs_session ) {
				WPDAI_Experiment_Store::upsert_assignment(
					array(
						'experiment_id' => (int) $experiment['id'],
						'visitor_id'    => $visitor_id,
						'session_id'    => $session_id,
						'variant_key'   => $result['variant_key'],
						'eligible'      => $result['eligible'] ? 1 : 0,
						'holdout'       => $result['holdout'] ? 1 : 0,
						'exposed'       => $needs_exposure || ! empty( $existing['exposed_gmt'] ),
					)
				);
				if ( $needs_exposure && $log_events ) {
					self::track_event( 'experiment_exposure', $experiment, $result, $session_id, $visitor_id );
				}
			}
			return $result;
		}

		$bucket           = self::bucket( $visitor_id, (int) $experiment['id'] );
		$traffic_percent  = isset( $experiment['traffic_percent'] ) ? (int) $experiment['traffic_percent'] : 100;
		if ( $traffic_percent < 1 ) {
			$traffic_percent = 100;
		}
		if ( $traffic_percent > 100 ) {
			$traffic_percent = 100;
		}
		if ( ! wpdai_experiments_is_pro() ) {
			$traffic_percent = 100;
		}

		$result['eligible'] = true;

		if ( $bucket >= ( $traffic_percent * 100 ) ) {
			$result['holdout'] = true;
			WPDAI_Experiment_Store::upsert_assignment(
				array(
					'experiment_id' => (int) $experiment['id'],
					'visitor_id'    => $visitor_id,
					'session_id'    => $session_id,
					'variant_key'   => '',
					'eligible'      => 1,
					'holdout'       => 1,
					'exposed'       => false,
				)
			);
			$result['created'] = true;
			if ( $log_events ) {
				self::track_event( 'experiment_eligible', $experiment, $result, $session_id, $visitor_id );
			}
			return $result;
		}

		$variant_key = self::pick_variant_key( $experiment, $bucket, $traffic_percent );
		$variant     = self::find_variant( $experiment, $variant_key );

		$result['variant_key'] = $variant_key;
		$result['variant']     = $variant;
		$result['created']     = true;

		WPDAI_Experiment_Store::upsert_assignment(
			array(
				'experiment_id' => (int) $experiment['id'],
				'visitor_id'    => $visitor_id,
				'session_id'    => $session_id,
				'variant_key'   => $variant_key,
				'eligible'      => 1,
				'holdout'       => 0,
				'exposed'       => true,
			)
		);

		if ( $log_events ) {
			self::track_event( 'experiment_eligible', $experiment, $result, $session_id, $visitor_id );
			self::track_event( 'experiment_assignment', $experiment, $result, $session_id, $visitor_id );
			self::track_event( 'experiment_exposure', $experiment, $result, $session_id, $visitor_id );
		}

		self::stamp_session_additional_data( $session_id, $visitor_id, (int) $experiment['id'], $variant_key );

		return $result;
	}

	/**
	 * Deterministic 0–9999 bucket.
	 *
	 * @param string $visitor_id    Visitor ID.
	 * @param int    $experiment_id Experiment ID.
	 * @return int
	 */
	public static function bucket( $visitor_id, $experiment_id ) {
		$hash = sprintf( '%u', crc32( $visitor_id . ':' . (int) $experiment_id ) );
		return (int) ( $hash % 10000 );
	}

	/**
	 * Map a bucket onto variant weights among the allocated traffic.
	 *
	 * @param array $experiment      Experiment.
	 * @param int   $bucket          0-9999.
	 * @param int   $traffic_percent 1-100.
	 * @return string
	 */
	protected static function pick_variant_key( $experiment, $bucket, $traffic_percent ) {
		$variants = isset( $experiment['variants'] ) && is_array( $experiment['variants'] ) ? $experiment['variants'] : array();
		if ( empty( $variants ) ) {
			return 'control';
		}

		$allocated = $traffic_percent * 100;
		$in_test   = $bucket; // 0 .. allocated-1
		$total_weight = 0;
		foreach ( $variants as $variant ) {
			$total_weight += isset( $variant['weight'] ) ? absint( $variant['weight'] ) : 0;
		}
		if ( $total_weight < 1 ) {
			$total_weight = count( $variants );
			foreach ( $variants as $i => $variant ) {
				$variants[ $i ]['weight'] = 1;
			}
		}

		$cursor = 0;
		foreach ( $variants as $variant ) {
			$weight    = isset( $variant['weight'] ) ? absint( $variant['weight'] ) : 0;
			$span      = (int) floor( ( $weight / $total_weight ) * $allocated );
			$cursor   += $span;
			if ( $in_test < $cursor ) {
				return isset( $variant['variant_key'] ) ? $variant['variant_key'] : 'control';
			}
		}

		$last = end( $variants );
		return isset( $last['variant_key'] ) ? $last['variant_key'] : 'control';
	}

	/**
	 * Find a variant by key.
	 *
	 * @param array  $experiment  Experiment.
	 * @param string $variant_key Variant key.
	 * @return array|null
	 */
	public static function find_variant( $experiment, $variant_key ) {
		if ( '' === $variant_key || empty( $experiment['variants'] ) ) {
			return null;
		}
		foreach ( $experiment['variants'] as $variant ) {
			if ( isset( $variant['variant_key'] ) && $variant['variant_key'] === $variant_key ) {
				return $variant;
			}
		}
		return null;
	}

	/**
	 * Control variant, or the first variant if none is marked control.
	 *
	 * @param array $experiment Experiment.
	 * @return array|null
	 */
	public static function find_control_variant( $experiment ) {
		if ( empty( $experiment['variants'] ) || ! is_array( $experiment['variants'] ) ) {
			return null;
		}
		foreach ( $experiment['variants'] as $variant ) {
			if ( ! empty( $variant['is_control'] ) || ( isset( $variant['variant_key'] ) && 'control' === $variant['variant_key'] ) ) {
				return $variant;
			}
		}
		$first = reset( $experiment['variants'] );
		return is_array( $first ) ? $first : null;
	}

	/**
	 * Log an experiment funnel event.
	 *
	 * @param string $event_type Event type.
	 * @param array  $experiment Experiment.
	 * @param array  $result     Assign result.
	 * @param string $session_id Session ID.
	 * @param string $visitor_id Visitor ID.
	 * @return void
	 */
	protected static function track_event( $event_type, $experiment, $result, $session_id, $visitor_id = '' ) {
		if ( ! function_exists( 'wpdai_track_custom_event' ) ) {
			return;
		}

		wpdai_track_custom_event(
			$event_type,
			array(
				'session_id'      => $session_id,
				'object_id'       => (int) $experiment['id'],
				'object_type'     => 'wpd_ai_experiment',
				'additional_data' => array(
					'experiment_id'   => (int) $experiment['id'],
					'experiment_slug' => isset( $experiment['slug'] ) ? $experiment['slug'] : '',
					'variant_key'     => $result['variant_key'],
					'holdout'         => ! empty( $result['holdout'] ) ? 1 : 0,
					'visitor_id'      => $visitor_id,
				),
			)
		);
	}

	/**
	 * Merge experiment map onto the analytics session row when it exists.
	 *
	 * @param string $session_id    Session ID.
	 * @param string $visitor_id    Visitor ID.
	 * @param int    $experiment_id Experiment ID.
	 * @param string $variant_key   Variant key.
	 * @return void
	 */
	protected static function stamp_session_additional_data( $session_id, $visitor_id, $experiment_id, $variant_key ) {
		if ( '' === $session_id ) {
			return;
		}

		global $wpdb;
		$interactor = new WPDAI_Database_Interactor();
		$table      = $interactor->session_data_table;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is from Database Interactor.
		$raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT additional_data FROM {$table} WHERE session_id = %s LIMIT 1",
				$session_id
			)
		);
		if ( null === $raw ) {
			return;
		}

		$extra = json_decode( (string) $raw, true );
		if ( ! is_array( $extra ) ) {
			$extra = array();
		}

		if ( ! isset( $extra['experiments'] ) || ! is_array( $extra['experiments'] ) ) {
			$extra['experiments'] = array();
		}
		$extra['visitor_id']                        = $visitor_id;
		$extra['experiments'][ (string) $experiment_id ] = $variant_key;

		$wpdb->update(
			$table,
			array( 'additional_data' => wp_json_encode( $extra ) ),
			array( 'session_id' => $session_id ),
			array( '%s' ),
			array( '%s' )
		);
	}
}
