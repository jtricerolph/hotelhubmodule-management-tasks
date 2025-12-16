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
     * Only creates FIRST instance if none exist (bootstrap).
     * Subsequent instances are created by:
     * - Overdue trigger (update_overdue_tasks)
     * - Completion trigger (handle_task_completion)
     *
     * @param object $task Task object with pattern data
     */
    private function process_fixed_recurring_task($task) {
        global $wpdb;

        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';

        // Check if any instance exists for this task
        $has_instance = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_instances}
            WHERE task_id = %d
            LIMIT 1",
            $task->id
        ));

        // Only create first instance if none exist (bootstrap)
        if (!$has_instance) {
            $this->create_task_instance($task, date('Y-m-d'));
        }
    }

    /**
     * Handle task completion (for both fixed and dynamic recurring)
     *
     * - Fixed: next due = scheduled date + interval (maintains calendar schedule)
     * - Dynamic: next due = completion date + interval (adapts to actual completion)
     *
     * @param int $instance_id Completed task instance ID
     * @param int $task_id Task ID
     */
    public function handle_task_completion($instance_id, $task_id) {
        global $wpdb;

        $table_tasks = $wpdb->prefix . 'hhmgt_tasks';
        $table_patterns = $wpdb->prefix . 'hhmgt_recurring_patterns';
        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';

        // Get task with pattern - now includes BOTH dynamic AND fixed
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, p.interval_days, p.lead_time_days
            FROM {$table_tasks} t
            INNER JOIN {$table_patterns} p ON t.recurrence_pattern_id = p.id
            WHERE t.id = %d
            AND t.recurrence_type IN ('dynamic', 'fixed')
            AND t.is_active = 1
            AND p.is_enabled = 1",
            $task_id
        ));

        if (!$task) {
            return; // Not a recurring task or not active
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

        // Calculate next occurrence based on recurrence type
        if ($task->recurrence_type === 'dynamic') {
            // Dynamic: next due = completion date + interval
            $base_date = new DateTime($completed_instance->completed_at);
        } else {
            // Fixed: next due = scheduled date + interval
            $base_date = new DateTime($completed_instance->scheduled_date);
        }

        $base_date->modify('+' . $task->interval_days . ' days');
        $next_date = $base_date->format('Y-m-d');

        // Create next instance
        $new_instance_id = $this->create_task_instance($task, $next_date);

        // Copy forward notes with carry_forward flag
        if ($new_instance_id) {
            $this->copy_forward_notes($instance_id, $new_instance_id);
        }
    }

    /**
     * Create a new task instance (or multiple if multi-location)
     *
     * @param object $task Task object with pattern data
     * @param string $scheduled_date Date to schedule (Y-m-d format)
     * @return int|array New instance ID(s) or false on failure
     */
    private function create_task_instance($task, $scheduled_date) {
        global $wpdb;

        // Check if this is a multi-location task
        if ($task->applies_to_multiple_locations) {
            return $this->create_multi_location_instances($task, $scheduled_date);
        }

        // Single location task - create one instance
        return $this->create_single_instance($task, $scheduled_date, null);
    }

    /**
     * Create instances for multi-location task
     *
     * @param object $task Task object
     * @param string $scheduled_date Scheduled date
     * @return array Array of created instance IDs
     */
    private function create_multi_location_instances($task, $scheduled_date) {
        global $wpdb;

        $table_task_locations = $wpdb->prefix . 'hhmgt_task_locations';

        // Get all assigned locations
        $assigned_locations = $wpdb->get_results($wpdb->prepare(
            "SELECT location_hierarchy_id FROM {$table_task_locations}
            WHERE task_id = %d",
            $task->id
        ));

        if (empty($assigned_locations)) {
            return array(); // No locations assigned
        }

        $instance_ids = array();

        // Create instance for each location
        foreach ($assigned_locations as $loc) {
            $instance_id = $this->create_single_instance($task, $scheduled_date, $loc->location_hierarchy_id);
            if ($instance_id) {
                $instance_ids[] = $instance_id;
            }
        }

        return $instance_ids;
    }

    /**
     * Create a single task instance
     *
     * @param object $task Task object
     * @param string $scheduled_date Scheduled date
     * @param int|null $location_hierarchy_id Specific location (for multi-location tasks)
     * @return int|false Instance ID or false
     */
    private function create_single_instance($task, $scheduled_date, $location_hierarchy_id = null) {
        global $wpdb;

        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';
        $table_states = $wpdb->prefix . 'hhmgt_task_states';

        // Check if instance already exists for this task/location/date
        $where = "task_id = %d AND scheduled_date = %s";
        $params = array($task->id, $scheduled_date);

        if ($location_hierarchy_id) {
            $where .= " AND location_hierarchy_id = %d";
            $params[] = $location_hierarchy_id;
        } else {
            $where .= " AND location_hierarchy_id IS NULL";
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_instances} WHERE {$where}",
            $params
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
                'location_hierarchy_id' => $location_hierarchy_id,
                'scheduled_date' => $scheduled_date,
                'due_date' => $scheduled_date,
                'status_id' => $pending_state ? $pending_state->id : null,
                'checklist_state' => json_encode(array()),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s')
        );

        if ($inserted) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Schedule instances for a specific task
     *
     * Manually triggers instance generation for a recurring task.
     * Creates only ONE instance - subsequent instances are created by:
     * - Overdue trigger (for fixed recurring)
     * - Completion trigger (for both fixed and dynamic)
     *
     * @param int $task_id Task ID
     * @return int Number of instances created
     */
    public function schedule_task_instances($task_id) {
        global $wpdb;

        $table_tasks = $wpdb->prefix . 'hhmgt_tasks';
        $table_patterns = $wpdb->prefix . 'hhmgt_recurring_patterns';
        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';

        // Get task with pattern
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, p.interval_days, p.lead_time_days, p.interval_type
            FROM {$table_tasks} t
            INNER JOIN {$table_patterns} p ON t.recurrence_pattern_id = p.id
            WHERE t.id = %d
            AND t.recurrence_type != 'none'
            AND t.is_active = 1
            AND p.is_enabled = 1",
            $task_id
        ));

        if (!$task) {
            return 0; // Not a recurring task or not active
        }

        // Check if there's already an active (non-completed) instance
        $active_instance = $wpdb->get_var($wpdb->prepare(
            "SELECT i.id FROM {$table_instances} i
            LEFT JOIN {$wpdb->prefix}hhmgt_task_states s ON i.status_id = s.id
            WHERE i.task_id = %d
            AND (s.is_complete_state IS NULL OR s.is_complete_state = 0)",
            $task_id
        ));

        if ($active_instance) {
            return 0; // Already has an active instance
        }

        // Create single instance for today
        $next_date = date('Y-m-d');
        $result = $this->create_task_instance($task, $next_date);

        if (is_array($result)) {
            return count($result);
        }
        return $result ? 1 : 0;
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
     * Trigger next instance for fixed recurring task when current becomes overdue
     *
     * @param int $task_id Task ID
     * @param string $current_scheduled_date Current instance scheduled date
     */
    private function trigger_next_instance_for_fixed($task_id, $current_scheduled_date) {
        global $wpdb;

        $table_tasks = $wpdb->prefix . 'hhmgt_tasks';
        $table_patterns = $wpdb->prefix . 'hhmgt_recurring_patterns';

        // Get task with pattern - only for fixed recurring
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, p.interval_days, p.lead_time_days
            FROM {$table_tasks} t
            INNER JOIN {$table_patterns} p ON t.recurrence_pattern_id = p.id
            WHERE t.id = %d
            AND t.recurrence_type = 'fixed'
            AND t.is_active = 1
            AND p.is_enabled = 1",
            $task_id
        ));

        if (!$task) {
            return; // Not a fixed recurring task or not active
        }

        // Calculate next occurrence from the SCHEDULED date (not today)
        $scheduled_date = new DateTime($current_scheduled_date);
        $scheduled_date->modify('+' . $task->interval_days . ' days');
        $next_date = $scheduled_date->format('Y-m-d');

        // Create next instance
        $this->create_task_instance($task, $next_date);
    }

    /**
     * Update tasks that are now overdue
     *
     * Also triggers next instance creation for fixed recurring tasks
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
                // Get instances that are about to become overdue (for triggering next)
                $newly_overdue = $wpdb->get_results($wpdb->prepare(
                    "SELECT i.id, i.task_id, i.scheduled_date
                    FROM {$table_instances} i
                    INNER JOIN {$table_states} s ON i.status_id = s.id
                    WHERE i.location_id = %d
                    AND i.due_date < %s
                    AND (s.is_complete_state IS NULL OR s.is_complete_state = 0)
                    AND s.state_slug != 'overdue'",
                    $location->location_id, date('Y-m-d')
                ));

                // Update instances to overdue status
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_instances} i
                    INNER JOIN {$table_states} s ON i.status_id = s.id
                    SET i.status_id = %d
                    WHERE i.location_id = %d
                    AND i.due_date < %s
                    AND (s.is_complete_state IS NULL OR s.is_complete_state = 0)",
                    $overdue_state->id, $location->location_id, date('Y-m-d')
                ));

                // Trigger next instance for fixed recurring tasks that just became overdue
                foreach ($newly_overdue as $instance) {
                    $this->trigger_next_instance_for_fixed($instance->task_id, $instance->scheduled_date);
                }
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
