<?php
/**
 *
 * Migration class responsible for database migrations
 * Handles data migrations that need to run after plugin updates
 *
 * @package Alpha Insights
 * @version 5.2.0
 * @since 5.2.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

class WPD_Migration {

    /**
     * Instance of this class
     *
     * @var WPD_Migration
     */
    private static $instance = null;

    /**
     * Option name for tracking completed migrations
     */
    const MIGRATION_COMPLETED_OPTION = 'wpd_ai_migrations_completed';

    /**
     * Get the singleton instance of this class
     *
     * @return WPD_Migration
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
     * Class constructor (private for singleton)
     */
    private function __construct() {
        // Hook into the migration runner action
        add_action( 'wpd_migration_runner', array( $this, 'run_pending_migrations' ) );
        
        // Hook into individual migration actions
        add_action( 'wpd_migration_build_engaged_sessions', array( $this, 'build_engaged_sessions' ) );
        
        // Register AJAX actions
        add_action( 'wp_ajax_wpd_run_migration', array( $this, 'run_migration_ajax_handler' ) );
    }

    /**
     * Get all available migrations
     * 
     * @return array Array of available migrations
     */
    public function get_available_migrations() {
        return array(
            'build_engaged_sessions' => array(
                'hook' => 'wpd_migration_build_engaged_sessions',
                'version' => '5.2.1',
                'description' => __( 'Build engaged sessions flag for existing sessions', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
                'name' => __( 'Build Engaged Sessions', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ),
            ),
            // Add more migrations here as needed
        );
    }

    /**
     * Get migration status information
     * 
     * @param string $migration_key The migration key
     * @return array Status information including completed status and completion time
     */
    public function get_migration_status( $migration_key ) {
        $completed_migrations = get_option( self::MIGRATION_COMPLETED_OPTION, array() );
        if ( ! is_array( $completed_migrations ) ) {
            $completed_migrations = array();
        }

        $is_completed = in_array( $migration_key, $completed_migrations, true );
        
        // Get completion time from option (we'll store this when marking as completed)
        $completion_times = get_option( 'wpd_ai_migrations_completion_times', array() );
        if ( ! is_array( $completion_times ) ) {
            $completion_times = array();
        }

        $completion_time = isset( $completion_times[ $migration_key ] ) ? $completion_times[ $migration_key ] : null;

        return array(
            'completed' => $is_completed,
            'completion_time' => $completion_time,
            'completion_time_formatted' => $completion_time ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $completion_time ) : null,
        );
    }

    /**
     * Migration runner - checks for pending migrations and schedules them
     * 
     * This method determines which migrations need to run based on what's been completed
     * and schedules the appropriate single events via Action Scheduler
     * 
     * @return bool True on success, false on failure
     */
    public function run_pending_migrations() {

        wpd_write_log( 'Starting migration runner to check for pending migrations', 'migration' );

        // Get list of completed migrations
        $completed_migrations = get_option( self::MIGRATION_COMPLETED_OPTION, array() );
        if ( ! is_array( $completed_migrations ) ) {
            $completed_migrations = array();
        }

        // Get all available migrations
        $available_migrations = $this->get_available_migrations();

        // Check which migrations need to run
        $pending_migrations = array();
        foreach ( $available_migrations as $migration_key => $migration_data ) {
            if ( ! in_array( $migration_key, $completed_migrations, true ) ) {
                $pending_migrations[ $migration_key ] = $migration_data;
            }
        }

        // If no pending migrations, log and return
        if ( empty( $pending_migrations ) ) {
            wpd_write_log( 'No pending migrations found. All migrations are up to date.', 'migration' );
            return true;
        }

        /* translators: %d: Number of pending migrations */
        wpd_write_log( sprintf( __( 'Found %d pending migration(s) to schedule.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), count( $pending_migrations ) ), 'migration' );

        // Schedule each pending migration
        if ( ! class_exists( 'WPD_Action_Scheduler' ) ) {
            wpd_write_log( 'WPD_Action_Scheduler class not found. Cannot schedule migrations.', 'migration_error' );
            return false;
        }

        $action_scheduler = new WPD_Action_Scheduler();
        $scheduled_count = 0;

        foreach ( $pending_migrations as $migration_key => $migration_data ) {
            $hook_name = $migration_data['hook'];
            
            // Check if this migration is already scheduled
            if ( ! as_next_scheduled_action( $hook_name ) ) {
                $result = $action_scheduler->schedule_one_off_event( $hook_name, 0 );
                if ( $result ) {
                    $scheduled_count++;
                    /* translators: 1: Migration key, 2: Migration description */
                    wpd_write_log( sprintf( __( 'Scheduled migration: %1$s (%2$s)', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $migration_key, $migration_data['description'] ), 'migration' );
                } else {
                    /* translators: %s: Migration key */
                    wpd_write_log( sprintf( __( 'Failed to schedule migration: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $migration_key ), 'migration_error' );
                }
            } else {
                /* translators: %s: Migration key */
                wpd_write_log( sprintf( __( 'Migration already scheduled: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $migration_key ), 'migration' );
            }
        }

        /* translators: %d: Number of scheduled migrations */
        wpd_write_log( sprintf( __( 'Migration runner completed. Scheduled %d migration(s).', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $scheduled_count ), 'migration' );

        return true;

    }

    /**
     * Mark a migration as completed
     * 
     * @param string $migration_key The key of the migration to mark as completed
     * @return bool True on success, false on failure
     */
    private function mark_migration_completed( $migration_key ) {

        $completed_migrations = get_option( self::MIGRATION_COMPLETED_OPTION, array() );
        if ( ! is_array( $completed_migrations ) ) {
            $completed_migrations = array();
        }

        if ( ! in_array( $migration_key, $completed_migrations, true ) ) {
            $completed_migrations[] = $migration_key;
            update_option( self::MIGRATION_COMPLETED_OPTION, $completed_migrations );
            
            // Store completion time
            $completion_times = get_option( 'wpd_ai_migrations_completion_times', array() );
            if ( ! is_array( $completion_times ) ) {
                $completion_times = array();
            }
            $completion_times[ $migration_key ] = current_time( 'timestamp', true );
            update_option( 'wpd_ai_migrations_completion_times', $completion_times );
            
            /* translators: %s: Migration key */
            wpd_write_log( sprintf( __( 'Marked migration as completed: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $migration_key ), 'migration' );
        }

        return true;

    }

    /**
     * Migration: Build engaged sessions
     * 
     * Updates engaged_session to true (1) for sessions where 
     * date_created_gmt != date_updated_gmt, indicating the session was updated
     * Processes in batches of 2500 to handle large sites
     * 
     * @return bool True on success, false on failure
     */
    public function build_engaged_sessions() {

        wpd_write_log( 'Starting migration: build_engaged_sessions', 'migration' );

        global $wpdb;

        // Get the database interactor instance
        $db_interactor = new WPD_Database_Interactor();
        $session_data_table = $db_interactor->session_data_table;

        // Check if the column exists
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is from trusted source.
        $column_exists = $wpdb->get_var( $wpdb->prepare( 
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE table_name = %s AND column_name = 'engaged_session'", 
            $session_data_table 
        ) );

        if ( ! $column_exists ) {
            wpd_write_log( 'Column engaged_session does not exist. Migration cannot proceed.', 'migration_error' );
            return false;
        }

        // Batch size for processing
        $batch_size = 2500;
        $total_updated = 0;
        $batch_number = 0;

        // Process in batches until no more sessions need updating
        while ( true ) {
            $batch_number++;

            // Get session IDs that need updating (limit to batch size)
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is from trusted source.
            $session_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT session_id 
                FROM $session_data_table 
                WHERE date_created_gmt != date_updated_gmt 
                AND (engaged_session IS NULL OR engaged_session = 0)
                LIMIT %d",
                $batch_size
            ) );

            // If no more sessions to update, we're done
            if ( empty( $session_ids ) ) {
                break;
            }

            // Prepare session IDs for IN clause
            $session_ids_placeholder = implode( ',', array_fill( 0, count( $session_ids ), '%s' ) );

            // Update this batch of sessions
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is from trusted source.
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholders are properly prepared.
            $sql = $wpdb->prepare(
                "UPDATE $session_data_table 
                SET engaged_session = 1 
                WHERE session_id IN ($session_ids_placeholder)",
                ...$session_ids
            );

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above.
            $updated_rows = $wpdb->query( $sql );

            // Check for errors
            if ( $wpdb->last_error ) {
                $error = $wpdb->last_error;
                $query = $wpdb->last_query;
                /* translators: %d: Batch number */
                wpd_write_log( sprintf( __( 'Error occurred during build_engaged_sessions migration at batch %d', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $batch_number ), 'migration_error' );
                wpd_write_log( $error, 'migration_error' );
                wpd_write_log( $query, 'migration_error' );
                return false;
            }

            $total_updated += $updated_rows;

            /* translators: 1: Batch number, 2: Number of updated sessions in this batch, 3: Total updated sessions */
            wpd_write_log( sprintf( __( 'Migration build_engaged_sessions batch %1$d completed. Updated %2$d sessions (Total: %3$d).', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $batch_number, $updated_rows, $total_updated ), 'migration' );

            // If we got fewer rows than the batch size, we've processed all remaining sessions
            if ( count( $session_ids ) < $batch_size ) {
                break;
            }
        }

        /* translators: %d: Total number of updated sessions */
        wpd_write_log( sprintf( __( 'Migration build_engaged_sessions completed. Updated %d sessions in %d batches.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $total_updated, $batch_number ), 'migration' );

        // Mark this migration as completed
        $this->mark_migration_completed( 'build_engaged_sessions' );

        return true;

    }

    /**
     * Render migrations management table
     * 
     * @return void
     */
    public function render_migrations_table() {
        $available_migrations = $this->get_available_migrations();
        ?>
        <div class="wpd-wrapper">
            <div class="wpd-section-heading"><?php esc_html_e( 'Migrations', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></div>
            <div class="wpd-notice wpd-notice-info" style="background-color: #e7f5fe; border-left: 4px solid #2271b1; padding: 12px; margin: 15px 0;">
                <p style="margin: 0;">
                    <strong><?php esc_html_e( 'Info:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></strong> 
                    <?php esc_html_e( 'Migrations are data updates that run automatically after plugin updates. You can manually trigger them here if needed.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
                </p>
            </div>
        </div>
        <div class="wpd-wrapper">
            <table class="wpd-table fixed widefat">
                <thead>
                    <tr>
                        <th style="width: 25%;"><?php esc_html_e( 'Migration', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
                        <th style="width: 40%;"><?php esc_html_e( 'Description', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
                        <th style="width: 20%;"><?php esc_html_e( 'Status', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
                        <th style="width: 15%;"><?php esc_html_e( 'Actions', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $available_migrations as $migration_key => $migration_data ) : 
                        $status = $this->get_migration_status( $migration_key );
                        ?>
                        <tr class="wpd-migration-row" data-migration-key="<?php echo esc_attr( $migration_key ); ?>">
                            <td>
                                <strong><?php echo esc_html( $migration_data['name'] ); ?></strong>
                                <br>
                                <span class="wpd-meta" style="font-size: 11px; color: #666;">
                                    <?php echo esc_html( sprintf( __( 'Version: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $migration_data['version'] ) ); ?>
                                </span>
                            </td>
                            <td>
                                <span class="wpd-meta"><?php echo esc_html( $migration_data['description'] ); ?></span>
                            </td>
                            <td>
                                <?php if ( $status['completed'] ) : ?>
                                    <span class="wpd-meta" style="color: #00a32a;">
                                        <span class="dashicons dashicons-yes-alt" style="font-size: 16px; vertical-align: middle;"></span>
                                        <?php esc_html_e( 'Completed', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
                                    </span>
                                    <?php if ( $status['completion_time_formatted'] ) : ?>
                                        <br>
                                        <span class="wpd-meta" style="font-size: 11px; color: #666;">
                                            <?php echo esc_html( sprintf( __( 'Ran: %s', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), $status['completion_time_formatted'] ) ); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="wpd-meta" style="color: #d63638;">
                                        <span class="dashicons dashicons-clock" style="font-size: 16px; vertical-align: middle;"></span>
                                        <?php esc_html_e( 'Pending', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button 
                                    type="button" 
                                    class="button button-secondary wpd-run-migration" 
                                    data-migration-key="<?php echo esc_attr( $migration_key ); ?>"
                                    data-migration-name="<?php echo esc_attr( $migration_data['name'] ); ?>"
                                >
                                    <?php esc_html_e( 'Run Migration', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Helper function to escape HTML
            function escapeHtml(text) {
                var map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text ? text.replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
            }
            
            $('.wpd-run-migration').on('click', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var migrationKey = $button.data('migration-key');
                var migrationName = $button.data('migration-name');
                var $row = $button.closest('tr');
                
                // Disable button and show loading
                $button.prop('disabled', true).text('<?php echo esc_js( __( 'Running...', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>');
                
                // Make AJAX request
                $.ajax({
                    url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
                    type: 'POST',
                    data: {
                        action: 'wpd_run_migration',
                        migration_key: migrationKey,
                        nonce: '<?php echo esc_js( wp_create_nonce( WPD_AI_AJAX_NONCE_ACTION ) ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            var completionTime = response.data && response.data.completion_time ? response.data.completion_time : '';
                            var statusHtml = '<span class="wpd-meta" style="color: #00a32a;">' +
                                '<span class="dashicons dashicons-yes-alt" style="font-size: 16px; vertical-align: middle;"></span> ' +
                                '<?php echo esc_js( __( 'Completed', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>' +
                                '</span>';
                            if (completionTime) {
                                statusHtml += '<br><span class="wpd-meta" style="font-size: 11px; color: #666;">' +
                                    '<?php echo esc_js( __( 'Ran:', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?> ' + escapeHtml(completionTime) +
                                    '</span>';
                            }
                            $row.find('td:nth-child(3)').html(statusHtml);
                            $button.text('<?php echo esc_js( __( 'Run Migration', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>').prop('disabled', false);
                            
                            // Show admin notice
                            if (response.data && response.data.message) {
                                $('<div class="notice notice-success is-dismissible"><p>' + escapeHtml(response.data.message) + '</p></div>')
                                    .insertAfter('.wpd-wrapper:first')
                                    .delay(5000)
                                    .fadeOut(function() { $(this).remove(); });
                            }
                        } else {
                            // Show error
                            $button.text('<?php echo esc_js( __( 'Run Migration', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>').prop('disabled', false);
                            var errorMsg = response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Migration failed. Please check logs.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>';
                            $('<div class="notice notice-error is-dismissible"><p>' + escapeHtml(errorMsg) + '</p></div>')
                                .insertAfter('.wpd-wrapper:first')
                                .delay(5000)
                                .fadeOut(function() { $(this).remove(); });
                        }
                    },
                    error: function() {
                        $button.text('<?php echo esc_js( __( 'Run Migration', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?>').prop('disabled', false);
                        $('<div class="notice notice-error is-dismissible"><p><?php echo esc_js( __( 'Error running migration. Please try again.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ); ?></p></div>')
                            .insertAfter('.wpd-wrapper:first')
                            .delay(5000)
                            .fadeOut(function() { $(this).remove(); });
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler to run a migration manually
     * 
     * @return void
     */
    public function run_migration_ajax_handler() {
        // Verify AJAX request
        if ( ! function_exists( 'wpd_verify_ajax_request' ) ) {
            require_once( WPD_AI_PATH . 'includes/wpd-ajax.php' );
        }
        
        if ( ! wpd_verify_ajax_request() ) {
            return; // wpd_verify_ajax_request sends JSON error and dies
        }

        // Get migration key
        $migration_key = isset( $_POST['migration_key'] ) ? sanitize_text_field( $_POST['migration_key'] ) : '';
        
        if ( empty( $migration_key ) ) {
            wp_send_json_error( array( 'message' => __( 'Migration key is required.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
            return;
        }

        // Get available migrations
        $available_migrations = $this->get_available_migrations();
        
        if ( ! isset( $available_migrations[ $migration_key ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid migration key.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
            return;
        }

        // Get the migration method name
        $migration_data = $available_migrations[ $migration_key ];
        $method_name = $migration_key;
        
        // Check if method exists
        if ( ! method_exists( $this, $method_name ) ) {
            wp_send_json_error( array( 'message' => __( 'Migration method not found.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ) ) );
            return;
        }

        // Run the migration
        $result = call_user_func( array( $this, $method_name ) );
        
        if ( $result ) {
            // Get updated status after migration
            $status = $this->get_migration_status( $migration_key );
            /* translators: %s: Migration name */
            wp_send_json_success( array( 
                'message' => sprintf( __( 'Migration "%s" completed successfully.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $migration_data['name'] ) ),
                'completion_time' => $status['completion_time_formatted'] ? esc_html( $status['completion_time_formatted'] ) : ''
            ) );
        } else {
            /* translators: %s: Migration name */
            wp_send_json_error( array( 
                'message' => sprintf( __( 'Migration "%s" failed. Please check the logs for details.', 'alpha-insights-sales-report-builder-analytics-for-woocommerce' ), esc_html( $migration_data['name'] ) )
            ) );
        }
    }

}

// Initialize the class
WPD_Migration::get_instance();

