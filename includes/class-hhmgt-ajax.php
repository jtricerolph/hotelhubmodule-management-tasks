<?php
/**
 * AJAX Handler Class
 *
 * Handles all AJAX requests for the tasks module
 *
 * @package HotelHub_Management_Tasks
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class HHMGT_Ajax {
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
        // Register AJAX handlers
        add_action('wp_ajax_hhmgt_get_tasks', array($this, 'get_tasks'));
        add_action('wp_ajax_hhmgt_get_task_detail', array($this, 'get_task_detail'));
        add_action('wp_ajax_hhmgt_update_task_status', array($this, 'update_task_status'));
        add_action('wp_ajax_hhmgt_update_checklist', array($this, 'update_checklist'));
        add_action('wp_ajax_hhmgt_add_note', array($this, 'add_note'));
        add_action('wp_ajax_hhmgt_complete_task', array($this, 'complete_task'));
        add_action('wp_ajax_hhmgt_get_location_types', array($this, 'get_location_types'));
        add_action('wp_ajax_hhmgt_get_locations', array($this, 'get_locations'));
    }

    /**
     * Get tasks list (AJAX handler)
     */
    public function get_tasks() {
        // Verify nonce
        check_ajax_referer('hhmgt_ajax_nonce', 'nonce');

        // Check permissions
        if (!$this->user_can_access()) {
            wp_send_json_error(array('message' => __('Permission denied', 'hhmgt')));
        }

        // Get parameters
        $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
        $include_future = isset($_POST['include_future']) ? filter_var($_POST['include_future'], FILTER_VALIDATE_BOOLEAN) : true;

        // Handle multi-select filters (can be arrays or single values)
        $departments = isset($_POST['department']) ? (array)$_POST['department'] : array();
        $departments = array_filter(array_map('sanitize_text_field', $departments));

        $statuses = isset($_POST['status']) ? (array)$_POST['status'] : array();
        $statuses = array_filter(array_map('sanitize_text_field', $statuses));

        $location_types = isset($_POST['location_type']) ? (array)$_POST['location_type'] : array();
        $location_types = array_filter(array_map('sanitize_text_field', $location_types));

        $location_filters = isset($_POST['location']) ? (array)$_POST['location'] : array();
        $location_filters = array_filter(array_map('intval', $location_filters));

        $show_completed = isset($_POST['show_completed']) ? filter_var($_POST['show_completed'], FILTER_VALIDATE_BOOLEAN) : false;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $group_by = isset($_POST['group_by']) ? sanitize_text_field($_POST['group_by']) : '';

        // Calculate date range: always include past (for overdue tasks)
        $date_from = date('Y-m-d', strtotime('-90 days')); // Include overdue tasks up to 90 days back
        $date_to = $include_future ? date('Y-m-d', strtotime('+30 days')) : date('Y-m-d');

        if (!$location_id) {
            wp_send_json_error(array('message' => __('Invalid location', 'hhmgt')));
        }

        // Fetch task instances from database
        global $wpdb;
        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';
        $table_tasks = $wpdb->prefix . 'hhmgt_tasks';
        $table_states = $wpdb->prefix . 'hhmgt_task_states';
        $table_departments = $wpdb->prefix . 'hhmgt_departments';
        $table_locations = $wpdb->prefix . 'hhmgt_location_hierarchy';

        // Build query
        $where_clauses = array(
            "i.location_id = %d",
            "i.due_date >= %s",
            "i.due_date <= %s",
            "t.is_active = 1"
        );
        $where_values = array($location_id, $date_from, $date_to);

        // Filter by department (multi-select)
        if (!empty($departments)) {
            $placeholders = implode(', ', array_fill(0, count($departments), '%s'));
            $where_clauses[] = "d.dept_slug IN ($placeholders)";
            $where_values = array_merge($where_values, $departments);
        }

        // Filter by status (multi-select)
        if (!empty($statuses)) {
            $placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
            $where_clauses[] = "s.state_slug IN ($placeholders)";
            $where_values = array_merge($where_values, $statuses);
        }

        // Filter by location type (multi-select)
        if (!empty($location_types)) {
            $placeholders = implode(', ', array_fill(0, count($location_types), '%s'));
            $where_clauses[] = "l.location_type IN ($placeholders)";
            $where_values = array_merge($where_values, $location_types);
        }

        // Filter by specific location (multi-select - use instance's location_hierarchy_id)
        if (!empty($location_filters)) {
            $placeholders = implode(', ', array_fill(0, count($location_filters), '%d'));
            $where_clauses[] = "i.location_hierarchy_id IN ($placeholders)";
            $where_values = array_merge($where_values, $location_filters);
        }

        // Filter completed
        if (!$show_completed) {
            $where_clauses[] = "(s.is_complete_state IS NULL OR s.is_complete_state = 0)";
        }

        // Search
        if ($search) {
            $where_clauses[] = "(t.task_name LIKE %s OR t.description LIKE %s)";
            $where_values[] = '%' . $wpdb->esc_like($search) . '%';
            $where_values[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $where_sql = implode(' AND ', $where_clauses);

        $sql = "SELECT
                    i.id AS instance_id,
                    i.scheduled_date,
                    i.due_date,
                    i.completed_at,
                    t.id AS task_id,
                    t.task_name,
                    t.description,
                    t.recurrence_type,
                    t.require_completion_photo,
                    d.dept_name,
                    d.icon_name AS dept_icon,
                    d.color_hex AS dept_color,
                    l.full_path AS location_path,
                    l.location_name,
                    s.state_name,
                    s.color_hex AS status_color,
                    s.is_complete_state
                FROM {$table_instances} i
                INNER JOIN {$table_tasks} t ON i.task_id = t.id
                LEFT JOIN {$table_departments} d ON t.department_id = d.id
                LEFT JOIN {$table_locations} l ON i.location_hierarchy_id = l.id
                LEFT JOIN {$table_states} s ON i.status_id = s.id
                WHERE {$where_sql}
                ORDER BY i.due_date ASC, t.task_name ASC";

        $results = $wpdb->get_results($wpdb->prepare($sql, $where_values));

        // Debug logging
        error_log("[HHMGT] Task query - Location: $location_id, Date: $date_from to $date_to, Results: " . count($results));
        error_log("[HHMGT] Filters applied - Depts: [" . implode(', ', $departments) . "], Statuses: [" . implode(', ', $statuses) . "], LocTypes: [" . implode(', ', $location_types) . "], LocFilters: [" . implode(', ', $location_filters) . "], Search: '$search', IncludeFuture: " . ($include_future ? 'yes' : 'no') . ", ShowCompleted: " . ($show_completed ? 'yes' : 'no'));

        if ($wpdb->last_error) {
            error_log("[HHMGT] SQL Error: " . $wpdb->last_error);
        }

        // Log the actual SQL query for debugging
        $debug_sql = $wpdb->prepare($sql, $where_values);
        error_log("[HHMGT] SQL Query: " . $debug_sql);

        // Check if task instances exist at all
        $total_instances = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_instances} WHERE location_id = %d",
            $location_id
        ));
        error_log("[HHMGT] Total task instances for location $location_id: $total_instances");

        // Check instances in date range
        $instances_in_range = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_instances}
            WHERE location_id = %d AND due_date >= %s AND due_date <= %s",
            $location_id, $date_from, $date_to
        ));
        error_log("[HHMGT] Instances in date range ($date_from to $date_to): $instances_in_range");

        // Group results if requested
        $tasks_data = $this->group_tasks($results, $group_by);

        wp_send_json_success(array(
            'tasks' => $tasks_data,
            'count' => count($results),
            'group_by' => $group_by
        ));
    }

    /**
     * Group tasks by specified field
     */
    private function group_tasks($tasks, $group_by) {
        if (!$group_by) {
            return array('items' => $tasks);
        }

        $grouped = array();

        foreach ($tasks as $task) {
            $group_key = '';

            switch ($group_by) {
                case 'location':
                    $group_key = $task->location_path ?? __('No Location', 'hhmgt');
                    break;
                case 'department':
                    $group_key = $task->dept_name ?? __('No Department', 'hhmgt');
                    break;
                case 'status':
                    $group_key = $task->state_name ?? __('Pending', 'hhmgt');
                    break;
            }

            if (!isset($grouped[$group_key])) {
                $grouped[$group_key] = array();
            }

            $grouped[$group_key][] = $task;
        }

        return array('groups' => $grouped);
    }

    /**
     * Get task detail (AJAX handler)
     */
    public function get_task_detail() {
        check_ajax_referer('hhmgt_ajax_nonce', 'nonce');

        if (!$this->user_can_access()) {
            wp_send_json_error(array('message' => __('Permission denied', 'hhmgt')));
        }

        $instance_id = isset($_POST['instance_id']) ? intval($_POST['instance_id']) : 0;

        if (!$instance_id) {
            wp_send_json_error(array('message' => __('Invalid task instance', 'hhmgt')));
        }

        global $wpdb;
        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';
        $table_tasks = $wpdb->prefix . 'hhmgt_tasks';
        $table_notes = $wpdb->prefix . 'hhmgt_task_notes';

        // Get task instance with task details
        $instance = $wpdb->get_row($wpdb->prepare(
            "SELECT i.*, t.*
            FROM {$table_instances} i
            INNER JOIN {$table_tasks} t ON i.task_id = t.id
            WHERE i.id = %d",
            $instance_id
        ));

        if (!$instance) {
            wp_send_json_error(array('message' => __('Task not found', 'hhmgt')));
        }

        // Get notes
        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, u.display_name AS author_name
            FROM {$table_notes} n
            LEFT JOIN {$wpdb->users} u ON n.created_by = u.ID
            WHERE n.task_instance_id = %d
            ORDER BY n.created_at DESC",
            $instance_id
        ));

        // Decode JSON fields
        $instance->reference_photos = json_decode($instance->reference_photos, true) ?: array();
        $instance->completion_photos = json_decode($instance->completion_photos, true) ?: array();
        $instance->checklist_items = json_decode($instance->checklist_items, true) ?: array();
        $instance->checklist_state = json_decode($instance->checklist_state, true) ?: array();

        // Process notes
        foreach ($notes as &$note) {
            $note->note_photos = json_decode($note->note_photos, true) ?: array();
        }

        wp_send_json_success(array(
            'instance' => $instance,
            'notes' => $notes
        ));
    }

    /**
     * Update task status (AJAX handler)
     */
    public function update_task_status() {
        global $wpdb;

        check_ajax_referer('hhmgt_ajax_nonce', 'nonce');

        if (!$this->user_can_access()) {
            wp_send_json_error(array('message' => __('Permission denied', 'hhmgt')));
        }

        $instance_id = isset($_POST['instance_id']) ? intval($_POST['instance_id']) : 0;
        $status_id = isset($_POST['status_id']) ? intval($_POST['status_id']) : 0;

        if (!$instance_id || !$status_id) {
            wp_send_json_error(array('message' => __('Invalid parameters', 'hhmgt')));
        }

        // Update status
        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';

        $updated = $wpdb->update(
            $table_instances,
            array('status_id' => $status_id),
            array('id' => $instance_id),
            array('%d'),
            array('%d')
        );

        if ($updated === false) {
            wp_send_json_error(array('message' => __('Failed to update status', 'hhmgt')));
        }

        wp_send_json_success(array(
            'message' => __('Status updated successfully', 'hhmgt')
        ));
    }

    /**
     * Update checklist (AJAX handler)
     */
    public function update_checklist() {
        global $wpdb;

        check_ajax_referer('hhmgt_ajax_nonce', 'nonce');

        if (!$this->user_can_access()) {
            wp_send_json_error(array('message' => __('Permission denied', 'hhmgt')));
        }

        $instance_id = isset($_POST['instance_id']) ? intval($_POST['instance_id']) : 0;
        $checklist_state = isset($_POST['checklist_state']) ? $_POST['checklist_state'] : array();

        if (!$instance_id) {
            wp_send_json_error(array('message' => __('Invalid task instance', 'hhmgt')));
        }

        // Convert string booleans to actual booleans
        $normalized_state = array();
        foreach ($checklist_state as $index => $value) {
            // Handle both boolean and string values ("true"/"false")
            $normalized_state[$index] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        // Debug logging
        error_log("[HHMGT] Updating checklist for instance $instance_id: " . json_encode($normalized_state));

        // Update checklist state
        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';

        $updated = $wpdb->update(
            $table_instances,
            array('checklist_state' => json_encode($normalized_state)),
            array('id' => $instance_id),
            array('%s'),
            array('%d')
        );

        if ($updated === false) {
            wp_send_json_error(array('message' => __('Failed to update checklist', 'hhmgt')));
        }

        wp_send_json_success(array(
            'message' => __('Checklist updated', 'hhmgt'),
            'checklist_state' => $normalized_state
        ));
    }

    /**
     * Add note (AJAX handler)
     */
    public function add_note() {
        global $wpdb;

        check_ajax_referer('hhmgt_ajax_nonce', 'nonce');

        if (!$this->user_can_access()) {
            wp_send_json_error(array('message' => __('Permission denied', 'hhmgt')));
        }

        $instance_id = isset($_POST['instance_id']) ? intval($_POST['instance_id']) : 0;
        $note_text = isset($_POST['note_text']) ? sanitize_textarea_field($_POST['note_text']) : '';
        $carry_forward = isset($_POST['carry_forward']) ? filter_var($_POST['carry_forward'], FILTER_VALIDATE_BOOLEAN) : true;
        $note_photos = isset($_POST['note_photos']) ? array_map('intval', $_POST['note_photos']) : array();

        if (!$instance_id || !$note_text) {
            wp_send_json_error(array('message' => __('Invalid parameters', 'hhmgt')));
        }

        // Insert note
        $table_notes = $wpdb->prefix . 'hhmgt_task_notes';

        $inserted = $wpdb->insert(
            $table_notes,
            array(
                'task_instance_id' => $instance_id,
                'note_text' => $note_text,
                'note_photos' => json_encode($note_photos),
                'carry_forward' => $carry_forward ? 1 : 0,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%d', '%s')
        );

        if ($inserted === false) {
            wp_send_json_error(array('message' => __('Failed to add note', 'hhmgt')));
        }

        wp_send_json_success(array(
            'message' => __('Note added successfully', 'hhmgt'),
            'note_id' => $wpdb->insert_id
        ));
    }

    /**
     * Complete task (AJAX handler)
     */
    public function complete_task() {
        global $wpdb;

        check_ajax_referer('hhmgt_ajax_nonce', 'nonce');

        if (!$this->user_can_access()) {
            wp_send_json_error(array('message' => __('Permission denied', 'hhmgt')));
        }

        $instance_id = isset($_POST['instance_id']) ? intval($_POST['instance_id']) : 0;
        $completion_photos = isset($_POST['completion_photos']) ? array_map('intval', $_POST['completion_photos']) : array();

        if (!$instance_id) {
            wp_send_json_error(array('message' => __('Invalid task instance', 'hhmgt')));
        }

        // Get task instance
        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';
        $instance = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_instances} WHERE id = %d",
            $instance_id
        ));

        if (!$instance) {
            wp_send_json_error(array('message' => __('Task not found', 'hhmgt')));
        }

        // Get complete status ID
        $table_states = $wpdb->prefix . 'hhmgt_task_states';
        $complete_state = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_states}
            WHERE location_id = %d AND is_complete_state = 1 AND is_enabled = 1
            LIMIT 1",
            $instance->location_id
        ));

        if (!$complete_state) {
            wp_send_json_error(array('message' => __('No complete status found', 'hhmgt')));
        }

        // Update instance
        $updated = $wpdb->update(
            $table_instances,
            array(
                'status_id' => $complete_state->id,
                'completed_by' => get_current_user_id(),
                'completed_at' => current_time('mysql'),
                'completion_photos' => json_encode($completion_photos)
            ),
            array('id' => $instance_id),
            array('%d', '%d', '%s', '%s'),
            array('%d')
        );

        if ($updated === false) {
            wp_send_json_error(array('message' => __('Failed to complete task', 'hhmgt')));
        }

        // Trigger scheduler for dynamic recurring tasks
        do_action('hhmgt_task_completed', $instance_id, $instance->task_id);

        wp_send_json_success(array(
            'message' => __('Task completed successfully', 'hhmgt')
        ));
    }

    /**
     * Get location types (AJAX handler)
     */
    public function get_location_types() {
        global $wpdb;

        check_ajax_referer('hhmgt_ajax_nonce', 'nonce');

        $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;

        if (!$location_id) {
            wp_send_json_error(array('message' => __('Invalid location', 'hhmgt')));
        }

        $table_locations = $wpdb->prefix . 'hhmgt_location_hierarchy';

        // Get location types that are not empty
        $types = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT location_type
            FROM {$table_locations}
            WHERE location_id = %d
            AND location_type IS NOT NULL
            AND location_type != ''
            AND is_enabled = 1
            ORDER BY location_type ASC",
            $location_id
        ));

        // If no types found, check if there are ANY locations in hierarchy
        if (empty($types)) {
            $total_locations = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_locations} WHERE location_id = %d AND is_enabled = 1",
                $location_id
            ));

            // Log for debugging
            error_log("[HHMGT] No location types found for location $location_id. Total locations: $total_locations");

            // If locations exist but have no type, return a generic type
            if ($total_locations > 0) {
                $types = array('General');
            }
        }

        wp_send_json_success(array('types' => $types));
    }

    /**
     * Get locations (AJAX handler)
     */
    public function get_locations() {
        global $wpdb;

        check_ajax_referer('hhmgt_ajax_nonce', 'nonce');

        $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;

        // Handle multi-select location types (can be array or single value)
        $location_types = isset($_POST['location_type']) ? (array)$_POST['location_type'] : array();
        $location_types = array_filter(array_map('sanitize_text_field', $location_types));

        if (!$location_id) {
            wp_send_json_error(array('message' => __('Invalid location', 'hhmgt')));
        }

        $table_locations = $wpdb->prefix . 'hhmgt_location_hierarchy';

        $where_clauses = array("location_id = %d", "is_enabled = 1");
        $where_values = array($location_id);

        // Filter by location types if specified (excluding "General")
        $filtered_types = array_filter($location_types, function($type) {
            return $type !== 'General' && $type !== '';
        });

        if (!empty($filtered_types)) {
            $placeholders = implode(', ', array_fill(0, count($filtered_types), '%s'));
            $where_clauses[] = "location_type IN ($placeholders)";
            $where_values = array_merge($where_values, $filtered_types);
        }

        $where_sql = implode(' AND ', $where_clauses);

        $locations = $wpdb->get_results($wpdb->prepare(
            "SELECT id, location_name, full_path, location_type
            FROM {$table_locations}
            WHERE {$where_sql}
            ORDER BY full_path ASC",
            $where_values
        ));

        error_log("[HHMGT] Loaded " . count($locations) . " locations for location_id=$location_id, types=[" . implode(', ', $location_types) . "]");

        wp_send_json_success(array('locations' => $locations));
    }

    /**
     * Check if user can access module
     *
     * @return bool
     */
    private function user_can_access() {
        if (function_exists('wfa_user_can')) {
            return wfa_user_can('hhmgt_tasks_access');
        }
        return current_user_can('edit_posts');
    }
}
