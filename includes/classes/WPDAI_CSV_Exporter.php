<?php
/**
 * WPD CSV Exporter Class
 * 
 * Handles comprehensive CSV export functionality for Alpha Insights reports
 * Transforms various data structures into CSV files and creates ZIP archives
 * 
 * @package WPD_Alpha_Insights
 * @since 4.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPDAI_CSV_Exporter {

    /**
     * Temporary directory for CSV file generation
     * @var string
     */
    private $tmp_dir;

    /**
     * Final ZIP storage directory
     * @var string
     */
    private $csv_dir;

    /**
     * Array to store generated CSV file paths
     * @var array
     */
    private $csv_files = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->tmp_dir = WPD_AI_UPLOADS_FOLDER_SYSTEM . 'tmp/';
        $this->csv_dir = WPD_AI_CSV_SYSTEM_PATH;
        
        // Ensure directories exist
        $this->ensure_directories();
    }

    /**
     * Ensure required directories exist
     */
    private function ensure_directories() {
        if ( ! file_exists( $this->tmp_dir ) ) {
            wp_mkdir_p( $this->tmp_dir );
        }
        if ( ! file_exists( $this->csv_dir ) ) {
            wp_mkdir_p( $this->csv_dir );
        }
    }

    /**
     * Main export method - processes all data and creates ZIP
     * 
     * @param array $all_data Complete data payload from report
     * @param string $report_name Report name for file naming
     * @return array Success status with ZIP file path and URL
     */
    public function export_all_data_to_zip( $all_data, $report_name = 'report' ) {
        try {
            // Clean up old temp files first
            $this->cleanup_old_temp_files();
            
            // Reset CSV files array
            $this->csv_files = [];
            
            // Create unique session ID for this export
            $session_id = uniqid( 'csv_export_' );
            
            // Process each entity in the data
            foreach ( $all_data as $entity => $entity_data ) {
                if ( empty( $entity_data ) || ! is_array( $entity_data ) ) {
                    continue; // Skip empty data
                }
                
                $this->process_entity( $entity, $entity_data, $session_id );
            }
            
            // If no CSV files were generated, return error
            if ( empty( $this->csv_files ) ) {
                return [
                    'success' => false,
                    'message' => 'No data available to export'
                ];
            }
            
            // Create ZIP file
            $zip_result = $this->create_zip_archive( $session_id, $report_name );
            
            // Cleanup temp files
            $this->cleanup_temp_files();
            
            return $zip_result;
            
        } catch ( Exception $e ) {
            // Cleanup on error
            $this->cleanup_temp_files();
            
            return [
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process a single entity and its data types
     * 
     * @param string $entity Entity name (e.g., 'orders', 'products')
     * @param array $entity_data Entity data containing various data types
     * @param string $session_id Unique session ID
     */
    private function process_entity( $entity, $entity_data, $session_id ) {
        foreach ( $entity_data as $data_type => $data ) {
            // Skip empty data and metadata fields
            if ( empty( $data ) || in_array( $data_type, ['total_db_records', 'execution_time'] ) ) {
                continue;
            }
            
            $filename = sanitize_file_name( $entity . '_' . $data_type );
            $filepath = $this->tmp_dir . $session_id . '_' . $filename . '.csv';
            
            // Route to appropriate handler based on data type
            switch ( $data_type ) {
                case 'data_by_date':
                    $this->export_data_by_date( $data, $filepath );
                    break;
                    
                case 'totals':
                    $this->export_totals( $data, $filepath );
                    break;
                    
                case 'data_tables':
                case 'data_table':
                    $this->export_data_table( $data, $filepath );
                    break;
                    
                case 'categorized_data':
                case 'by_category':
                    $this->export_categorized_data( $data, $filepath, $entity );
                    break;
                    
                default:
                    // Generic handler for unknown data types
                    $this->export_generic_data( $data, $filepath, $data_type );
                    break;
            }
        }
    }

    /**
     * Export data_by_date structure
     * Format: Dates in first column, metrics as headers, handle mixed multi-dimensional data
     * 
     * @param array|object $data Data indexed by date or nested by category then date
     * @param string $filepath File path to save CSV
     */
    private function export_data_by_date( $data, $filepath ) {
        if ( empty( $data ) ) return;
                
        $csv_rows = [];
        
        // Separate simple date-value pairs from multi-dimensional data
        $simple_date_data = [];
        $multi_dimensional_data = [];
        $date_value_fields = [];
        
        foreach ( $data as $key => $value ) {
            if ( $this->is_date_key( $key ) ) {
                // Simple date-value pair (key is a date)
                $simple_date_data[ $key ] = $value;
            } elseif ( is_array( $value ) && $this->is_multi_dimensional_date_data( $value ) ) {
                // Multi-dimensional: category -> date -> value
                $multi_dimensional_data[ $key ] = $value;
            } elseif ( is_array( $value ) && $this->is_simple_date_value_field( $value ) ) {
                // Simple date-value field: field_name -> {date: value, date: value, ...}
                $date_value_fields[ $key ] = $value;
            } else {
                // Simple key-value pair
                $simple_date_data[ $key ] = $value;
            }
        }
        
        // Collect all unique dates from all data types
        $all_dates = [];
        $all_columns = [];
        
        // Add dates from simple data
        foreach ( array_keys( $simple_date_data ) as $date ) {
            if ( $this->is_date_key( $date ) && ! in_array( $date, $all_dates ) ) {
                $all_dates[] = $date;
            }
        }
        
        // Add dates from date-value fields
        foreach ( $date_value_fields as $field_name => $date_values ) {
            foreach ( array_keys( $date_values ) as $date ) {
                if ( $this->is_date_key( $date ) && ! in_array( $date, $all_dates ) ) {
                    $all_dates[] = $date;
                }
            }
        }
        
        // Add dates from multi-dimensional data and create column headers
        foreach ( $multi_dimensional_data as $field_name => $category_data ) {
            foreach ( $category_data as $category => $date_values ) {
                $column_name = $this->format_column_header( $field_name ) . ' - ' . $category;
                $all_columns[] = $column_name;
                
                foreach ( array_keys( $date_values ) as $date ) {
                    if ( $this->is_date_key( $date ) && ! in_array( $date, $all_dates ) ) {
                        $all_dates[] = $date;
                    }
                }
            }
        }
        
        // Sort dates
        sort( $all_dates );
        
        // Build header row with proper column order
        $headers = ['Date'];
        $simple_columns = [];
        $date_value_columns = [];
        
        // Collect simple columns (non-date keys)
        foreach ( array_keys( $simple_date_data ) as $key ) {
            if ( ! $this->is_date_key( $key ) ) {
                $simple_columns[] = $this->format_column_header( $key );
            }
        }
        
        // Add date-value field columns
        foreach ( array_keys( $date_value_fields ) as $field_name ) {
            $date_value_columns[] = $this->format_column_header( $field_name );
        }
        
        $headers = array_merge( $headers, $simple_columns, $date_value_columns, $all_columns );
        $csv_rows[] = $headers;
        
        // Build data rows
        foreach ( $all_dates as $date ) {
            $row = [ $date ];
            
            // Add simple data values (non-date keys)
            foreach ( array_keys( $simple_date_data ) as $key ) {
                if ( ! $this->is_date_key( $key ) ) {
                    $row[] = $this->extract_value( $simple_date_data[ $key ] );
                }
            }
            
            // Add date-value field data for this specific date
            foreach ( $date_value_fields as $field_name => $date_values ) {
                $value = isset( $date_values[ $date ] ) ? $date_values[ $date ] : '';
                $row[] = $this->extract_value( $value );
            }
            
            // Add multi-dimensional data values
            foreach ( $multi_dimensional_data as $field_name => $category_data ) {
                foreach ( $category_data as $category => $date_values ) {
                    $value = isset( $date_values[ $date ] ) ? $date_values[ $date ] : '';
                    $row[] = $this->extract_value( $value );
                }
            }
            
            $csv_rows[] = $row;
        }
        
        $this->write_csv_file( $csv_rows, $filepath );
    }

    /**
     * Check if array contains multi-dimensional date data (category -> date -> value)
     * 
     * @param array $data Array to check
     * @return bool True if multi-dimensional date data
     */
    private function is_multi_dimensional_date_data( $data ) {
        if ( ! is_array( $data ) || empty( $data ) ) {
            return false;
        }
        
        // Check if values contain date-keyed arrays
        foreach ( $data as $value ) {
            if ( is_array( $value ) && ! empty( $value ) ) {
                $first_key = array_key_first( $value );
                if ( $this->is_date_key( $first_key ) ) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Check if array contains simple date-value data (date -> value)
     * 
     * @param array $data Array to check
     * @return bool True if simple date-value data
     */
    private function is_simple_date_value_field( $data ) {
        if ( ! is_array( $data ) || empty( $data ) ) {
            return false;
        }
        
        // Check if keys are dates and values are not arrays
        $date_count = 0;
        $total_count = count( $data );
        
        foreach ( $data as $key => $value ) {
            if ( $this->is_date_key( $key ) && ! is_array( $value ) ) {
                $date_count++;
            }
        }
        
        // If most keys are dates and values are not arrays, it's a simple date-value field
        return $date_count > 0 && ( $date_count / $total_count ) > 0.5;
    }

    /**
     * Format column header to be more readable
     * 
     * @param string $header Header string to format
     * @return string Formatted header
     */
    private function format_column_header( $header ) {
        // Replace underscores with spaces
        $header = str_replace( '_', ' ', $header );
        
        // Capitalize first letter of each word
        $header = ucwords( $header );
        
        return $header;
    }

    /**
     * Export totals structure
     * Format: Key-value pairs (key in column 1, value in column 2)
     * 
     * @param array $data Totals data
     * @param string $filepath File path to save CSV
     */
    private function export_totals( $data, $filepath ) {
        if ( empty( $data ) ) return;
        
        $csv_rows = [];
        $csv_rows[] = ['Metric', 'Value'];
        
        foreach ( $data as $key => $value ) {
            $formatted_key = str_replace( '_', ' ', $key );
            $formatted_key = ucwords( $formatted_key );
            
            $csv_rows[] = [
                $formatted_key,
                $this->extract_value( $value )
            ];
        }
        
        $this->write_csv_file( $csv_rows, $filepath );
    }

    /**
     * Export data_table structure
     * Format: Each row is a record, with defined columns as headers
     * Handles nested structure: data_table -> sub_table_name -> records
     * Data tables are always passed with nested arrays where keys are sub-table names
     * 
     * @param array $data Table data containing sub-tables
     * @param string $filepath File path to save CSV
     */
    private function export_data_table( $data, $filepath ) {
        if ( empty( $data ) || ! is_array( $data ) ) return;
                
        // Data tables always contain sub-tables (e.g., "orders", "customers")
        // Process each sub-table separately
        foreach ( $data as $sub_table_name => $sub_table_data ) {
            if ( empty( $sub_table_data ) || ! is_array( $sub_table_data ) ) continue;
            
            // Create a separate file for each sub-table
            $sub_filepath = str_replace( '.csv', '_' . sanitize_file_name( $sub_table_name ) . '.csv', $filepath );
            $this->export_single_data_table( $sub_table_data, $sub_filepath );
        }
    }

    /**
     * Export a single data table (without sub-tables)
     * 
     * @param array $data Table data
     * @param string $filepath File path to save CSV
     */
    private function export_single_data_table( $data, $filepath ) {
        if ( empty( $data ) || ! is_array( $data ) ) return;
        
        $csv_rows = [];
        
        // Check if this is an associative array where keys are identifiers
        $is_associative = false;
        $first_key = array_key_first( $data );
        if ( $first_key !== 0 && is_numeric( $first_key ) ) {
            $is_associative = true;
        }
        
        if ( $is_associative ) {
            // Data structure: {"1588": {"order_id": 1588, "revenue": 211.20}, "1587": {...}}
            // Each key (1588, 1587) should be a row, with the payload fields as columns
            
            // Get all unique keys from all records to create column headers
            $all_keys = [];
            foreach ( $data as $record ) {
                if ( is_array( $record ) ) {
                    foreach ( array_keys( $record ) as $key ) {
                        if ( ! in_array( $key, $all_keys ) ) {
                            $all_keys[] = $key;
                        }
                    }
                }
            }
            
            // Build header row - these are the column headers from the payload
            $headers = array_map( function( $key ) {
                return ucwords( str_replace( '_', ' ', $key ) );
            }, $all_keys );
            $csv_rows[] = $headers;
            
            // Build data rows - each identifier becomes a row
            foreach ( $data as $identifier => $record ) {
                if ( ! is_array( $record ) ) continue;
                
                $csv_row = [];
                foreach ( $all_keys as $key ) {
                    $value = isset( $record[ $key ] ) ? $record[ $key ] : '';
                    // Check if this is a date column with a timestamp (before JSON conversion)
                    if ( stripos( $key, 'date' ) !== false && is_numeric( $value ) && $value > 0 ) {
                        $value = gmdate( 'Y-m-d H:i:s', $value );
                    }
                    $csv_row[] = $this->extract_value( $value );
                }
                $csv_rows[] = $csv_row;
            }
            
        } else {
            // Data structure: [{"order_id": 1588, "revenue": 211.20}, {"order_id": 1587, ...}]
            // Each array element is a record
            
            // Get all unique keys from all records to create column headers
            $all_keys = [];
            foreach ( $data as $record ) {
                if ( is_array( $record ) ) {
                    foreach ( array_keys( $record ) as $key ) {
                        if ( ! in_array( $key, $all_keys ) ) {
                            $all_keys[] = $key;
                        }
                    }
                }
            }
            
            // Build header row - these are the column headers
            $headers = array_map( function( $key ) {
                return ucwords( str_replace( '_', ' ', $key ) );
            }, $all_keys );
            $csv_rows[] = $headers;
            
            // Build data rows - each record becomes a row
            foreach ( $data as $record ) {
                if ( ! is_array( $record ) ) continue;
                
                $csv_row = [];
                foreach ( $all_keys as $key ) {
                    $value = isset( $record[ $key ] ) ? $record[ $key ] : '';
                    // Check if this is a date column with a timestamp (before JSON conversion)
                    if ( stripos( $key, 'date' ) !== false && is_numeric( $value ) && $value > 0 ) {
                        $value = gmdate( 'Y-m-d H:i:s', $value );
                    }
                    $csv_row[] = $this->extract_value( $value );
                }
                $csv_rows[] = $csv_row;
            }
        }
        
        $this->write_csv_file( $csv_rows, $filepath );
    }

    /**
     * Export categorized data structure
     * Handles nested category structures with separate sections for each category
     * 
     * @param array $data Categorized data
     * @param string $filepath File path to save CSV
     * @param string $entity Entity name for context
     */
    private function export_categorized_data( $data, $filepath, $entity ) {
        if ( empty( $data ) ) return;
        
        $csv_rows = [];
        $first_category = true;
        
        foreach ( $data as $category_name => $category_data ) {
            if ( empty( $category_data ) || ! is_array( $category_data ) ) continue;
            
            // Add blank row before each category (except the first)
            if ( ! $first_category ) {
                $csv_rows[] = []; // Blank row
            }
            $first_category = false;
            
            // Add category header
            $csv_rows[] = [ $this->format_column_header( $category_name ) ];
            
            // Process category data
            $category_rows = $this->process_category_data( $category_data );
            
            if ( ! empty( $category_rows ) ) {
                $csv_rows = array_merge( $csv_rows, $category_rows );
            }
        }
        
        $this->write_csv_file( $csv_rows, $filepath );
    }

    /**
     * Process individual category data into CSV rows
     * 
     * @param array $category_data Category data to process
     * @return array Array of CSV rows
     */
    private function process_category_data( $category_data ) {
        $csv_rows = [];
        
        // Check if this is a simple key-value structure
        $is_simple_key_value = true;
        foreach ( $category_data as $key => $value ) {
            if ( is_array( $value ) || is_object( $value ) ) {
                $is_simple_key_value = false;
                break;
            }
        }
        
        if ( $is_simple_key_value ) {
            // Simple key-value pairs
            $csv_rows[] = [ 'Metric', 'Value' ];
            foreach ( $category_data as $key => $value ) {
                $formatted_key = $this->format_column_header( $key );
                $csv_rows[] = [ $formatted_key, $this->extract_value( $value ) ];
            }
        } else {
            // Complex data structure - flatten and create table
            $flattened = $this->flatten_categorized_data( $category_data );
            
            if ( empty( $flattened ) ) return $csv_rows;
            
            // Get all unique keys for headers
            $all_keys = [];
            foreach ( $flattened as $item ) {
                foreach ( array_keys( $item ) as $key ) {
                    if ( ! in_array( $key, $all_keys ) ) {
                        $all_keys[] = $key;
                    }
                }
            }
            
            // Build header row
            $headers = array_map( function( $key ) {
                return $this->format_column_header( $key );
            }, $all_keys );
            $csv_rows[] = $headers;
            
            // Build data rows
            foreach ( $flattened as $item ) {
                $csv_row = [];
                foreach ( $all_keys as $key ) {
                    $value = isset( $item[ $key ] ) ? $item[ $key ] : '';
                    $csv_row[] = $this->extract_value( $value );
                }
                $csv_rows[] = $csv_row;
            }
        }
        
        return $csv_rows;
    }

    /**
     * Export generic/unknown data structures
     * 
     * @param mixed $data Generic data
     * @param string $filepath File path to save CSV
     * @param string $data_type Data type name
     */
    private function export_generic_data( $data, $filepath, $data_type ) {
        if ( empty( $data ) ) return;
        
        $csv_rows = [];
        
        // If it's an array of arrays/objects
        if ( is_array( $data ) && isset( $data[0] ) && is_array( $data[0] ) ) {
            $all_keys = [];
            foreach ( $data as $item ) {
                foreach ( array_keys( $item ) as $key ) {
                    if ( ! in_array( $key, $all_keys ) ) {
                        $all_keys[] = $key;
                    }
                }
            }
            
            // Headers
            $headers = array_map( function( $key ) {
                return str_replace( '_', ' ', ucwords( $key ) );
            }, $all_keys );
            $csv_rows[] = $headers;
            
            // Data rows
            foreach ( $data as $item ) {
                $csv_row = [];
                foreach ( $all_keys as $key ) {
                    $csv_row[] = isset( $item[ $key ] ) ? $this->extract_value( $item[ $key ] ) : '';
                }
                $csv_rows[] = $csv_row;
            }
        } else {
            // Simple key-value structure
            $csv_rows[] = ['Key', 'Value'];
            foreach ( $data as $key => $value ) {
                $formatted_key = str_replace( '_', ' ', ucwords( $key ) );
                $csv_rows[] = [ $formatted_key, $this->extract_value( $value ) ];
            }
        }
        
        if ( ! empty( $csv_rows ) ) {
            $this->write_csv_file( $csv_rows, $filepath );
        }
    }

    /**
     * Flatten categorized data recursively
     * 
     * @param array $data Categorized data
     * @param string $prefix Category prefix for nested data
     * @return array Flattened array of records
     */
    private function flatten_categorized_data( $data, $prefix = '' ) {
        $flattened = [];
        
        foreach ( $data as $key => $value ) {
            $category_name = $prefix ? $prefix . ' > ' . $key : $key;
            
            if ( is_array( $value ) ) {
                // Check if this is a data record or another category level
                $is_data_record = $this->is_data_record( $value );
                
                if ( $is_data_record ) {
                    // This is a data record
                    $record = [ 'Category' => $category_name ];
                    foreach ( $value as $field_key => $field_value ) {
                        $record[ ucwords( str_replace( '_', ' ', $field_key ) ) ] = $this->extract_value( $field_value );
                    }
                    $flattened[] = $record;
                } else {
                    // Nested categories - recurse
                    $nested = $this->flatten_categorized_data( $value, $category_name );
                    $flattened = array_merge( $flattened, $nested );
                }
            } else {
                // Simple value
                $flattened[] = [
                    'Category' => $category_name,
                    'Value' => $this->extract_value( $value )
                ];
            }
        }
        
        return $flattened;
    }

    /**
     * Check if array is a data record vs nested categories
     * 
     * @param array $value Array to check
     * @return bool True if data record
     */
    private function is_data_record( $value ) {
        if ( ! is_array( $value ) || empty( $value ) ) {
            return false;
        }
        
        // If any value is not an array, it's likely a data record
        foreach ( $value as $v ) {
            if ( ! is_array( $v ) ) {
                return true;
            }
        }
        
        // If all values are arrays, check if they look like nested dates
        $first_key = array_key_first( $value );
        return $this->is_date_key( $first_key );
    }

    /**
     * Check if a key looks like a date
     * 
     * @param string $key Key to check
     * @return bool True if looks like a date
     */
    private function is_date_key( $key ) {
        return preg_match( '/^\d{4}-\d{2}-\d{2}/', $key );
    }

    /**
     * Extract value from complex data structures
     * Handles objects, arrays, and primitives
     * 
     * @param mixed $value Value to extract
     * @return string Extracted and formatted value
     */
    private function extract_value( $value ) {
        // Handle null/empty
        if ( $value === null || $value === '' ) {
            return '';
        }
        
        // Handle arrays
        if ( is_array( $value ) ) {
            // Empty arrays should display as blank
            if ( empty( $value ) ) {
                return '';
            }
            // Convert to JSON
            return json_encode( $value );
        }
        
        // Handle objects with value property
        if ( is_object( $value ) ) {
            if ( isset( $value->value ) ) {
                return $this->extract_value( $value->value );
            }
            if ( isset( $value->amount ) ) {
                return $this->extract_value( $value->amount );
            }
            if ( isset( $value->total ) ) {
                return $this->extract_value( $value->total );
            }
            // Convert object to JSON
            return json_encode( $value );
        }
        
        // Handle booleans
        if ( is_bool( $value ) ) {
            return $value ? 'Yes' : 'No';
        }
        
        // Handle numeric values
        if ( is_numeric( $value ) ) {
            return $value;
        }
        
        // Return as string
        return (string) $value;
    }

    /**
     * Write CSV file from array of rows
     * 
     * @param array $rows Array of rows (each row is an array of cells)
     * @param string $filepath File path to save CSV
     */
    private function write_csv_file( $rows, $filepath ) {
        if ( empty( $rows ) ) return;
        
        // Initialize WordPress filesystem
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        // Build CSV content
        $csv_content = '';
        
        // Write BOM for UTF-8
        $csv_content .= chr(0xEF).chr(0xBB).chr(0xBF);
        
        // Write each row using temporary stream to capture fputcsv output
        foreach ( $rows as $row ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Using php://temp stream for in-memory CSV generation is acceptable.
            $temp_handle = fopen( 'php://temp', 'r+' );
            if ( $temp_handle ) {
                fputcsv( $temp_handle, $row );
                rewind( $temp_handle );
                $csv_content .= stream_get_contents( $temp_handle );
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing temporary stream handle.
                fclose( $temp_handle );
            }
        }
        
        // Write file using WP_Filesystem
        if ( ! $wp_filesystem->put_contents( $filepath, $csv_content, FS_CHMOD_FILE ) ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging is necessary for debugging file write failures.
            error_log( 'WPD CSV Exporter: Failed to create file: ' . $filepath );
            return;
        }
        
        // Add to CSV files list
        $this->csv_files[] = $filepath;
    }

    /**
     * Create ZIP archive from all generated CSV files
     * 
     * @param string $session_id Unique session ID
     * @param string $report_name Report name
     * @return array Result with success status and file info
     */
    private function create_zip_archive( $session_id, $report_name ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return [
                'success' => false,
                'message' => 'ZipArchive class not available on this server'
            ];
        }
        
        $sanitized_name = sanitize_file_name( $report_name );
        $timestamp = gmdate( 'Y-m-d_H-i-s' );
        $zip_filename = $sanitized_name . '_export_' . $timestamp . '.zip';
        $zip_filepath = $this->csv_dir . $zip_filename;
        
        $zip = new ZipArchive();
        
        if ( $zip->open( $zip_filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== TRUE ) {
            return [
                'success' => false,
                'message' => 'Failed to create ZIP archive'
            ];
        }
        
        // Add each CSV file to the ZIP
        foreach ( $this->csv_files as $csv_file ) {
            $filename = basename( $csv_file );
            // Remove session ID prefix from filename in ZIP
            $filename = preg_replace( '/^' . preg_quote( $session_id, '/' ) . '_/', '', $filename );
            
            $zip->addFile( $csv_file, $filename );
        }
        
        $zip->close();
        
        // Generate download URL
        $upload_dir = wp_upload_dir();
        $zip_url = str_replace(
            $upload_dir['basedir'],
            $upload_dir['baseurl'],
            $zip_filepath
        );
        
        return [
            'success' => true,
            'message' => 'Export completed successfully',
            'zip_path' => $zip_filepath,
            'zip_url' => $zip_url,
            'filename' => $zip_filename,
            'file_count' => count( $this->csv_files )
        ];
    }

    /**
     * Cleanup temporary CSV files
     */
    private function cleanup_temp_files() {
        foreach ( $this->csv_files as $file ) {
            if ( file_exists( $file ) ) {
                wp_delete_file( $file );
            }
        }
        $this->csv_files = [];
    }

    /**
     * Cleanup old temporary files (older than 1 hour)
     */
    private function cleanup_old_temp_files() {
        $files = glob( $this->tmp_dir . 'csv_export_*' );
        
        if ( ! $files ) return;
        
        $one_hour_ago = time() - 3600;
        
        foreach ( $files as $file ) {
            if ( is_file( $file ) && filemtime( $file ) < $one_hour_ago ) {
                wp_delete_file( $file );
            }
        }
    }

    /**
     * Cleanup old ZIP files (older than 24 hours)
     */
    public function cleanup_old_zip_files() {
        $files = glob( $this->csv_dir . '*_export_*.zip' );
        
        if ( ! $files ) return;
        
        $one_day_ago = time() - 86400;
        
        foreach ( $files as $file ) {
            if ( is_file( $file ) && filemtime( $file ) < $one_day_ago ) {
                wp_delete_file( $file );
            }
        }
    }
}

