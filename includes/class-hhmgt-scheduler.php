<?php
/**
 * Scheduler Class
 *
 * Handles recurring task generation and scheduling using WP Cron
 *
 * @package HotelHub_Management_Tasks
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class HHMGT_Scheduler {
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // WP Cron hook
        add_action('hhmgt_process_recurring_tasks', array($this, 'process_recurring_tasks'));

        // Hook for when a task is completed (for dynamic recurring)
        add_action('hhmgt_task_completed', array($this, 'handle_task_completion'), 10, 2);
    }

    /**
     * Process all recurring tasks
     *
     * Called by WP Cron hourly
     */
    public function process_recurring_tasks() {
        global $wpdb;

        $table_tasks = $wpdb->prefix . 'hhmgt_tasks';
        $table_patterns = $wpdb->prefix . 'hhmgt_recurring_patterns';

        // Get all active tasks with fixed recurring patterns
        $fixed_tasks = $wpdb->get_results(
            "SELECT t.*, p.interval_days, p.lead_time_days
            FROM {$table_tasks} t
            INNER JOIN {$table_patterns} p ON t.recurrence_pattern_id = p.id
            WHERE t.recurrence_type = 'fixed'
            AND t.is_active = 1
            AND p.is_enabled = 1"
        );

        foreach ($fixed_tasks as $task) {
            $this->process_fixed_recurring_task($task);
        }

        // Check for overdue tasks and update status
        $this->update_overdue_tasks();
        $this->update_due_tasks();
    }

    /**
     * Process a single fixed recurring task
     *
     * @param object $task Task object with pattern data
     */
    private function process_fixed_recurring_task($task) {
        global $wpdb;

        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';

        // Get last scheduled instance
        $last_instance = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_instances}
            WHERE task_id = %d
            ORDER BY scheduled_date DESC
            LIMIT 1",
            $task->id
        ));

        $next_date = null;

        if ($last_instance) {
            // Calculate next occurrence
            $last_date = new DateTime($last_instance->scheduled_date);
            $last_date->modify('+' . $task->interval_days . ' days');
            $next_date = $last_date->format('Y-m-d');
        } else {
            // First instance - schedule for today
            $next_date = date('Y-m-d');
        }

        // Only create if next date is today or in the past
        if ($next_date <= date('Y-m-d')) {
            $this->create_task_instance($task, $next_date);
        }
    }

    /**
     * Handle task completion (for dynamic recurring)
     *
     * @param int $instance_id Completed task instance ID
     * @param int $task_id Task ID
     */
    public function handle_task_completion($instance_id, $task_id) {
        global $wpdb;

        $table_tasks = $wpdb->prefix . 'hhmgt_tasks';
        $table_patterns = $wpdb->prefix . 'hhmgt_recurring_patterns';
        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';

        // Get task with pattern
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, p.interval_days, p.lead_time_days
            FROM {$table_tasks} t
            INNER JOIN {$table_patterns} p ON t.recurrence_pattern_id = p.id
            WHERE t.id = %d
            AND t.recurrence_type = 'dynamic'
            AND t.is_active = 1
            AND p.is_enabled = 1",
            $task_id
        ));

        if (!$task) {
            return; // Not a dynamic recurring task or not active
        }

        // Get the completed instance
        $completed_instance = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_instances}
            WHERE id = %d",
            $instance_id
        ));

        if (!$completed_instance || !$completed_instance->completed_at) {
            return;
        }

        // Calculate next occurrence from completion date
        $completion_date = new DateTime($completed_instance->completed_at);
        $completion_date->modify('+' . $task->interval_days . ' days');
        $next_date = $completion_date->format('Y-m-d');

        // Create next instance
        $new_instance_id = $this->create_task_instance($task, $next_date);

        // Copy forward notes with carry_forward flag
        if ($new_instance_id) {
            $this->copy_forward_notes($instance_id, $new_instance_id);
        }
    }

    /**
     * Create a new task instance
     *
     * @param object $task Task object with pattern data
     * @param string $scheduled_date Date to schedule (Y-m-d format)
     * @return int|false New instance ID or false on failure
     */
    private function create_task_instance($task, $scheduled_date) {
        global $wpdb;

        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';
        $table_states = $wpdb->prefix . 'hhmgt_task_states';

        // Check if instance already exists for this date
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_instances}
            WHERE task_id = %d AND scheduled_date = %s",
            $task->id, $scheduled_date
        ));

        if ($existing) {
            return false; // Already exists
        }

        // Get default pending status
        $pending_state = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_states}
            WHERE location_id = %d AND state_slug = 'pending' AND is_enabled = 1
            LIMIT 1",
            $task->location_id
        ));

        // Insert new instance
        $inserted = $wpdb->insert(
            $table_instances,
            array(
                'task_id' => $task->id,
                'location_id' => $task->location_id,
                'scheduled_date' => $scheduled_date,
                'due_date' => $scheduled_date,
                'status_id' => $pending_state ? $pending_state->id : null,
                'checklist_state' => json_encode(array()),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%d', '%s', '%s')
        );

        if ($inserted) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Copy notes with carry_forward flag to new instance
     *
     * @param int $old_instance_id Old task instance ID
     * @param int $new_instance_id New task instance ID
     */
    private function copy_forward_notes($old_instance_id, $new_instance_id) {
        global $wpdb;

        $table_notes = $wpdb->prefix . 'hhmgt_task_notes';

        // Get notes to carry forward
        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_notes}
            WHERE task_instance_id = %d AND carry_forward = 1",
            $old_instance_id
        ));

        foreach ($notes as $note) {
            $wpdb->insert(
                $table_notes,
                array(
                    'task_instance_id' => $new_instance_id,
                    'note_text' => $note->note_text,
                    'note_photos' => $note->note_photos,
                    'carry_forward' => 1, // Keep carry_forward flag
                    'created_by' => $note->created_by,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%d', '%d', '%s')
            );
        }
    }

    /**
     * Update tasks that are now overdue
     */
    private function update_overdue_tasks() {
        global $wpdb;

        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';
        $table_states = $wpdb->prefix . 'hhmgt_task_states';

        // Get overdue state for each location
        $locations = $wpdb->get_results(
            "SELECT DISTINCT location_id FROM {$table_instances}"
        );

        foreach ($locations as $location) {
            $overdue_state = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$table_states}
                WHERE location_id = %d AND state_slug = 'overdue' AND is_enabled = 1
                LIMIT 1",
                $location->location_id
            ));

            if ($overdue_state) {
                // Update instances that are overdue
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_instances} i
                    INNER JOIN {$table_states} s ON i.status_id = s.id
                    SET i.status_id = %d
                    WHERE i.location_id = %d
                    AND i.due_date < %s
                    AND (s.is_complete_state IS NULL OR s.is_complete_state = 0)",
                    $overdue_state->id, $location->location_id, date('Y-m-d')
                ));
            }
        }
    }

    /**
     * Update tasks that are now due
     */
    private function update_due_tasks() {
        global $wpdb;

        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';
        $table_states = $wpdb->prefix . 'hhmgt_task_states';

        // Get due state for each location
        $locations = $wpdb->get_results(
            "SELECT DISTINCT location_id FROM {$table_instances}"
        );

        foreach ($locations as $location) {
            $due_state = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$table_states}
                WHERE location_id = %d AND state_slug = 'due' AND is_enabled = 1
                LIMIT 1",
                $location->location_id
            ));

            $pending_state = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$table_states}
                WHERE location_id = %d AND state_slug = 'pending' AND is_enabled = 1
                LIMIT 1",
                $location->location_id
            ));

            if ($due_state && $pending_state) {
                // Update pending instances that are due today
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_instances}
                    SET status_id = %d
                    WHERE location_id = %d
                    AND status_id = %d
                    AND due_date = %s",
                    $due_state->id, $location->location_id, $pending_state->id, date('Y-m-d')
                ));
            }
        }
    }
}
