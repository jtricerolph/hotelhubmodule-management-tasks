<?php
/**
 * Heartbeat Class
 *
 * Handles real-time synchronization using WordPress Heartbeat API
 *
 * @package HotelHub_Management_Tasks
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class HHMGT_Heartbeat {
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
        add_filter('heartbeat_settings', array($this, 'heartbeat_settings'));
        add_filter('heartbeat_received', array($this, 'heartbeat_received'), 10, 3);
    }

    /**
     * Modify heartbeat settings
     *
     * @param array $settings Heartbeat settings
     * @return array Modified settings
     */
    public function heartbeat_settings($settings) {
        $settings['interval'] = 30; // 30 seconds
        return $settings;
    }

    /**
     * Process heartbeat request
     *
     * @param array $response Response data
     * @param array $data Request data
     * @param string $screen_id Current screen ID
     * @return array Modified response
     */
    public function heartbeat_received($response, $data, $screen_id) {
        if (!isset($data['hhmgt_monitor'])) {
            return $response;
        }

        $monitor_data = $data['hhmgt_monitor'];
        $location_id = isset($monitor_data['location_id']) ? intval($monitor_data['location_id']) : 0;
        $last_check = isset($monitor_data['last_check']) ? sanitize_text_field($monitor_data['last_check']) : '';

        if (!$location_id || !$last_check) {
            return $response;
        }

        // Get recent updates
        global $wpdb;
        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';
        $table_tasks = $wpdb->prefix . 'hhmgt_tasks';
        $table_notes = $wpdb->prefix . 'hhmgt_task_notes';

        // Get updated task instances
        $updated_instances = $wpdb->get_results($wpdb->prepare(
            "SELECT i.id, i.task_id, i.status_id, i.completed_at, i.checklist_state
            FROM {$table_instances} i
            INNER JOIN {$table_tasks} t ON i.task_id = t.id
            WHERE i.location_id = %d
            AND t.is_active = 1
            AND (i.created_at >= %s OR i.completed_at >= %s)
            ORDER BY i.due_date ASC",
            $location_id, $last_check, $last_check
        ));

        // Get new notes
        $new_notes = $wpdb->get_results($wpdb->prepare(
            "SELECT n.id, n.task_instance_id, n.note_text, n.created_by, n.created_at
            FROM {$table_notes} n
            INNER JOIN {$table_instances} i ON n.task_instance_id = i.id
            WHERE i.location_id = %d
            AND n.created_at >= %s
            ORDER BY n.created_at DESC",
            $location_id, $last_check
        ));

        if (!empty($updated_instances) || !empty($new_notes)) {
            $response['hhmgt_updates'] = array(
                'instances' => $updated_instances,
                'notes' => $new_notes,
                'timestamp' => current_time('mysql')
            );
        }

        return $response;
    }
}
