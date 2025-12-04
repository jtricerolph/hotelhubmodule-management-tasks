<?php
/**
 * Bulk Update Utility Class
 *
 * Handles bulk operations on task instances
 *
 * @package HotelHub_Management_Tasks
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class HHMGT_Bulk_Update {

    /**
     * Update all future (non-completed) instances for a task
     *
     * Use this when changing task settings like:
     * - Recurring pattern (7 days → 14 days)
     * - Lead time
     * - Checklist items
     * - Description
     * - Reference photos
     *
     * @param int $task_id Task ID
     * @param array $updates Array of fields to update
     * @return int|false Number of instances updated, or false on error
     */
    public static function update_future_instances($task_id, $updates = array()) {
        global $wpdb;

        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';
        $table_states = $wpdb->prefix . 'hhmgt_task_states';
        $table_tasks = $wpdb->prefix . 'hhmgt_tasks';

        // Get the task
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_tasks} WHERE id = %d",
            $task_id
        ));

        if (!$task) {
            return false;
        }

        // Build update SQL
        $update_data = array();
        $update_format = array();

        // Checklist items (update from task)
        if (isset($updates['checklist_items']) || isset($task->checklist_items)) {
            $checklist_items = $updates['checklist_items'] ?? $task->checklist_items;
            // Reset checklist state for future instances
            $update_data['checklist_state'] = json_encode(array());
            $update_format[] = '%s';
        }

        if (empty($update_data)) {
            return false; // Nothing to update
        }

        // Get future non-completed instances
        $future_instances = $wpdb->get_results($wpdb->prepare(
            "SELECT i.id
            FROM {$table_instances} i
            LEFT JOIN {$table_states} s ON i.status_id = s.id
            WHERE i.task_id = %d
            AND i.due_date >= %s
            AND (s.is_complete_state IS NULL OR s.is_complete_state = 0)",
            $task_id,
            date('Y-m-d')
        ));

        if (empty($future_instances)) {
            return 0; // No future instances
        }

        // Update each instance
        $updated_count = 0;
        foreach ($future_instances as $instance) {
            $result = $wpdb->update(
                $table_instances,
                $update_data,
                array('id' => $instance->id),
                $update_format,
                array('%d')
            );

            if ($result !== false) {
                $updated_count++;
            }
        }

        return $updated_count;
    }

    /**
     * Clear all future instances for a task
     *
     * Use this when you want to completely regenerate future instances
     * (e.g., when changing from fixed to dynamic recurring)
     *
     * @param int $task_id Task ID
     * @return int|false Number of instances deleted, or false on error
     */
    public static function clear_future_instances($task_id) {
        global $wpdb;

        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';
        $table_states = $wpdb->prefix . 'hhmgt_task_states';

        // Delete future non-completed instances
        $result = $wpdb->query($wpdb->prepare(
            "DELETE i FROM {$table_instances} i
            LEFT JOIN {$table_states} s ON i.status_id = s.id
            WHERE i.task_id = %d
            AND i.due_date >= %s
            AND (s.is_complete_state IS NULL OR s.is_complete_state = 0)",
            $task_id,
            date('Y-m-d')
        ));

        return $result;
    }

    /**
     * Reschedule future instances when pattern interval changes
     *
     * Example: Change from 7 days to 14 days
     * Recalculates all future due dates based on new interval
     *
     * @param int $task_id Task ID
     * @param int $new_interval_days New interval in days
     * @return int|false Number of instances rescheduled, or false on error
     */
    public static function reschedule_future_instances($task_id, $new_interval_days) {
        global $wpdb;

        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';
        $table_states = $wpdb->prefix . 'hhmgt_task_states';

        // Get all future non-completed instances ordered by due date
        $instances = $wpdb->get_results($wpdb->prepare(
            "SELECT i.id, i.scheduled_date, i.due_date
            FROM {$table_instances} i
            LEFT JOIN {$table_states} s ON i.status_id = s.id
            WHERE i.task_id = %d
            AND i.due_date >= %s
            AND (s.is_complete_state IS NULL OR s.is_complete_state = 0)
            ORDER BY i.due_date ASC",
            $task_id,
            date('Y-m-d')
        ));

        if (empty($instances)) {
            return 0;
        }

        // Get most recent completed instance to use as reference point
        $last_completed = $wpdb->get_row($wpdb->prepare(
            "SELECT i.*
            FROM {$table_instances} i
            INNER JOIN {$table_states} s ON i.status_id = s.id
            WHERE i.task_id = %d
            AND s.is_complete_state = 1
            AND i.completed_at IS NOT NULL
            ORDER BY i.completed_at DESC
            LIMIT 1",
            $task_id
        ));

        $reference_date = $last_completed ? $last_completed->completed_at : date('Y-m-d');

        // Reschedule each instance
        $updated_count = 0;
        $current_date = new DateTime($reference_date);

        foreach ($instances as $index => $instance) {
            // Calculate new due date
            $current_date->modify('+' . $new_interval_days . ' days');
            $new_due_date = $current_date->format('Y-m-d');

            // Update instance
            $result = $wpdb->update(
                $table_instances,
                array(
                    'due_date' => $new_due_date,
                    'scheduled_date' => $new_due_date
                ),
                array('id' => $instance->id),
                array('%s', '%s'),
                array('%d')
            );

            if ($result !== false) {
                $updated_count++;
            }
        }

        return $updated_count;
    }

    /**
     * Delete all instances for a task (when deleting task)
     *
     * @param int $task_id Task ID
     * @return int|false Number of instances deleted, or false on error
     */
    public static function delete_all_instances($task_id) {
        global $wpdb;

        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';

        $result = $wpdb->delete(
            $table_instances,
            array('task_id' => $task_id),
            array('%d')
        );

        return $result;
    }

    /**
     * Get count of future instances for a task
     *
     * @param int $task_id Task ID
     * @return int Number of future non-completed instances
     */
    public static function count_future_instances($task_id) {
        global $wpdb;

        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';
        $table_states = $wpdb->prefix . 'hhmgt_task_states';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
            FROM {$table_instances} i
            LEFT JOIN {$table_states} s ON i.status_id = s.id
            WHERE i.task_id = %d
            AND i.due_date >= %s
            AND (s.is_complete_state IS NULL OR s.is_complete_state = 0)",
            $task_id,
            date('Y-m-d')
        ));

        return intval($count);
    }

    /**
     * Update task and optionally update all future instances
     *
     * This is the main method to call when editing a task
     *
     * @param int $task_id Task ID
     * @param array $task_data Task fields to update
     * @param bool $update_future_instances Whether to update future instances
     * @return array Result with counts
     */
    public static function update_task_and_instances($task_id, $task_data, $update_future_instances = false) {
        global $wpdb;

        $table_tasks = $wpdb->prefix . 'hhmgt_tasks';

        // Update task
        $task_updated = $wpdb->update(
            $table_tasks,
            $task_data,
            array('id' => $task_id),
            null, // Let WordPress figure out formats
            array('%d')
        );

        $result = array(
            'task_updated' => $task_updated !== false,
            'instances_updated' => 0
        );

        // Update future instances if requested
        if ($update_future_instances) {
            $instances_updated = self::update_future_instances($task_id, $task_data);
            $result['instances_updated'] = $instances_updated;
        }

        return $result;
    }
}
