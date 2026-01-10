<?php
/**
 *
 * WPD Task Runner
 * 
 * Responsible for running once off tasks as required.
 * Will search this class for any methods that begin with run_task_ and run once.
 * 
 * @package Alpha Insights
 * @version 3.4.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;

class WPD_Task_Runner {

    /**
     * 
     *  Stores the tasks that have already been ran
     * 
     **/
    private array $task_runner_activity = array();

    /**
     * 
     *  The task method name that a method should begin with to be consider a task that needs to be run
     * 
     **/
    private string $task_method_string_start = 'run_task_';

    /**
     * 
     *  Returns the string for wp_options db table key to store complete tasks
     * 
     **/
    private string $task_runner_option_name = 'wpd_ai_task_runner';

    /** 
     *
     *   Constructor
     * 
     **/
    public function __construct() {

        // Silence is golden
        // delete_option( $this->task_runner_option_name );

    }

    /**
     * 
     *  Get list of tasks that have already been completed at least once. Method => Count format.
     * 
     *  @return array An array of tasks that have already ran
     * 
     **/
    public function get_list_of_complete_tasks( $return_count = false ) {

        return (array) array_keys( get_option( $this->task_runner_option_name, array() ) );

    }

    /**
     * 
     *  Gets list of methods that should be run as tasks
     * 
     *  This pends on a method beginning with run_task_
     * 
     *  @return array|bool Returns an array of task method names on success, false on failure
     * 
     **/
    private function get_list_of_tasks_to_complete() {

        // Gets list of class methods
        $class_methods = get_class_methods( $this );

        // Incomplete Tasks
        $tasks_to_complete = array();

        // Safety Check
        if ( is_array($class_methods) ) {

            // Loop through available methods
            foreach( $class_methods as $class_method ) {

                // Only store the method that starts with target id
                if ( $this->string_starts_with( $class_method, $this->task_method_string_start ) ) $tasks_to_complete[] = $class_method;

            }

            // Returns list of tasks to complete
            return $tasks_to_complete;

        }

        // Must have failed
        return false;

    }

    /**
     * 
     *  Get list of tasks that are defined as needing to be executed.
     * 
     *  This will run any method within this class that begin with run_task_
     * 
     *  @return array|bool An array of task method titles that need to run. Method => Count format. False on failure
     * 
     **/
    public function get_list_of_incomplete_tasks() {

        // The return array of incomplete tasks
        $incomplete_tasks = array();

        // Tasks to run, and tasks that have already beeen complete
        $tasks_to_complete = $this->get_list_of_tasks_to_complete();
        $complete_tasks = $this->get_list_of_complete_tasks();

        // Store this as an array key
        if ( is_array($complete_tasks) ) {

            // Remove the complete tasks from the tasks to complete
            $incomplete_tasks = array_diff( $tasks_to_complete, $complete_tasks );

            // Return the results
            return $incomplete_tasks;

        }

    }

    /**
     * 
     *  Main method for running tasks
     *  Will search this class for any methods that begin with run_task_ and run once
     * 
     *  @param bool $run_all Set this to true to run all tasks, regardless if they've been ran or not
     * 
     **/
    public function run_incomplete_tasks( $run_all = false ) {

        // Log init of task runner
        $this->log( 'Task runner has begun' );

        // Get list of tasks that need to run / not run
        $all_tasks = $this->get_list_of_tasks_to_complete();
        $incomplete_tasks = $this->get_list_of_incomplete_tasks();

        // Run all tasks if set
        if ( $run_all ) $incomplete_tasks = $all_tasks;

        // Run through tasks
        if ( is_array($incomplete_tasks) ) {

            // All tasks are complete
            if ( count($incomplete_tasks) == 0 ) {

                // Log task complete
                $this->log( 'All tasks are complete, execution complete.' );

                // Return true
                return true;

            }

            // Mark count of tasks complete
            $this->log( sprintf( '%s of %s tasks are incomplete, running tasks', count( $incomplete_tasks ), count( $all_tasks ) ) );

            // Loop through methods that need to complete
            foreach( $incomplete_tasks as $incomplete_task ) {

                // Call the method
                $result = call_user_func( array($this, $incomplete_task) );

            }

            // Return success
            $this->log( 'Task Runner Complete.' );

            // Mark complete
            return true;

        }
        
        // Log Error
        $this->log( 'Could not run the Task Runner, something is wrong with the incomplete tasks format.' );

        // Return false
        return false;

    }

    /**
     * 
     *  Log all occurences within this class in the task_runner log
     * 
     **/
    private function log( $data ) {

        wpdai_write_log( $data, 'task_runner' );

    }

    /**
     * 
     *  Marks a task as complete based on the method name as the key
     * 
     *  Will also log the number of times this task has been executed
     * 
     *  @return int The results of update_option();
     * 
     **/
    private function complete_task( $method_name ) {

        // Get current options
        $complete_tasks = (array) get_option( $this->task_runner_option_name, array() );

        // If this method has been run before, iterate the count
        if ( isset($complete_tasks[$method_name]) ) {
            
            // Current run count
            $current_count = $complete_tasks[$method_name];

            // New count
            $current_count++;

            // Update the var with new count
            $complete_tasks[$method_name] = $current_count;

            // Save the new value
            return update_option( $this->task_runner_option_name, $complete_tasks );

        }

        // Otherwise set a new count
        $complete_tasks[$method_name] = 1;

        // Save new value
        return update_option( $this->task_runner_option_name, $complete_tasks );

    }

    /**
     * 
     *  Checks if a string starts with a defined value
     * 
     *  @return bool Returns true if yes, false if no
     * 
     **/
    private function string_starts_with( $string, $starts_with ) {

        // Length of check
        $len = strlen( $starts_with ); 

        // Calculation
        return (substr($string, 0, $len) === $starts_with ); 

    }

}