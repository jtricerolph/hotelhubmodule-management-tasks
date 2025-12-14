<?php
/**
 * Task Administration Class
 *
 * Handles admin UI for task management (list, create, edit, delete)
 *
 * @package HotelHub_Management_Tasks
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class HHMGT_Tasks_Admin {
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
        add_action('admin_post_hhmgt_save_task', array($this, 'save_task'));
        add_action('admin_post_hhmgt_delete_task', array($this, 'delete_task'));
        add_action('admin_post_hhmgt_update_future_tasks', array($this, 'update_future_tasks'));
        add_action('wp_ajax_hhmgt_get_task', array($this, 'ajax_get_task'));
        add_action('wp_ajax_hhmgt_schedule_task_now', array($this, 'ajax_schedule_task_now'));
        add_action('wp_ajax_hhmgt_admin_delete_instance', array($this, 'ajax_delete_instance'));
    }

    /**
     * Render tasks list page
     */
    public static function render_list() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'hhmgt'));
        }

        // Get current location
        $current_location_id = isset($_GET['location_id']) ? intval($_GET['location_id']) : 0;

        // Get locations
        $locations = HHMGT_Settings::get_locations();

        // If no location selected, use first location
        if (!$current_location_id && !empty($locations)) {
            $current_location_id = $locations[0]['id'];
        }

        // Get tasks for this location
        $tasks = self::get_tasks($current_location_id);

        // Get departments for filter
        $departments = self::get_departments($current_location_id);

        // Load template
        include HHMGT_PLUGIN_DIR . 'admin/views/tasks-list.php';
    }

    /**
     * Render create/edit task page
     */
    public static function render_edit() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'hhmgt'));
        }

        // Get task ID if editing
        $task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;

        // Get current location
        $current_location_id = isset($_GET['location_id']) ? intval($_GET['location_id']) : 0;

        // Get locations
        $locations = HHMGT_Settings::get_locations();

        // If no location selected, use first location
        if (!$current_location_id && !empty($locations)) {
            $current_location_id = $locations[0]['id'];
        }

        // Get task data if editing
        $task = null;
        $task_locations = array();
        if ($task_id) {
            $task = self::get_task($task_id);
            if (!$task || $task->location_id != $current_location_id) {
                wp_die(__('Invalid task', 'hhmgt'));
            }

            // Get assigned locations if multi-location task
            if ($task->applies_to_multiple_locations) {
                $task_locations = self::get_task_locations($task_id);
            }
        }

        // Auto-sync settings if needed (in case user configured before bug fix)
        self::maybe_auto_sync_settings($current_location_id);

        // Get departments
        $departments = self::get_departments($current_location_id);

        // Get recurring patterns
        $patterns = self::get_patterns($current_location_id);

        // Get location hierarchy
        $location_hierarchy = self::get_location_hierarchy($current_location_id);

        // Get checklist templates
        $templates = self::get_checklist_templates($current_location_id);

        // Load template
        include HHMGT_PLUGIN_DIR . 'admin/views/task-edit.php';
    }

    /**
     * Render task instances page (All Tasks view)
     */
    public static function render_instances() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'hhmgt'));
        }

        // Get current location
        $current_location_id = isset($_GET['location_id']) ? intval($_GET['location_id']) : 0;

        // Get locations
        $locations = HHMGT_Settings::get_locations();

        // If no location selected, use first location
        if (!$current_location_id && !empty($locations)) {
            $current_location_id = $locations[0]['id'];
        }

        // Get filter values
        $filters = array(
            'department' => isset($_GET['department']) ? array_map('intval', (array)$_GET['department']) : array(),
            'status' => isset($_GET['status']) ? array_map('intval', (array)$_GET['status']) : array(),
            'search' => isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '',
            'include_completed' => isset($_GET['include_completed']) && $_GET['include_completed'] === '1',
            // Date range filters
            'filter_scheduled' => isset($_GET['filter_scheduled']) && $_GET['filter_scheduled'] === '1',
            'scheduled_from' => isset($_GET['scheduled_from']) ? sanitize_text_field($_GET['scheduled_from']) : '',
            'scheduled_to' => isset($_GET['scheduled_to']) ? sanitize_text_field($_GET['scheduled_to']) : '',
            'filter_due' => isset($_GET['filter_due']) && $_GET['filter_due'] === '1',
            'due_from' => isset($_GET['due_from']) ? sanitize_text_field($_GET['due_from']) : '',
            'due_to' => isset($_GET['due_to']) ? sanitize_text_field($_GET['due_to']) : '',
            'filter_completed' => isset($_GET['filter_completed']) && $_GET['filter_completed'] === '1',
            'completed_from' => isset($_GET['completed_from']) ? sanitize_text_field($_GET['completed_from']) : '',
            'completed_to' => isset($_GET['completed_to']) ? sanitize_text_field($_GET['completed_to']) : '',
        );

        // Pagination
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 50;
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

        // Get task instances
        $result = self::get_task_instances($current_location_id, $filters, $per_page, $paged);
        $instances = $result['instances'];
        $total_instances = $result['total'];
        $total_pages = ceil($total_instances / $per_page);
        // Get departments for filter
        $departments = self::get_departments($current_location_id);

        // Get states for filter
        $states = self::get_task_states($current_location_id);

        // Load template
        include HHMGT_PLUGIN_DIR . 'admin/views/task-instances.php';
    }

    /**
     * Get task instances with filters
     *
     * @param int $location_id Location ID
     * @param array $filters Filter values
     * @param int $per_page Items per page
     * @param int $paged Current page
     * @return array Array with 'instances' and 'total'
     */
    private static function get_task_instances($location_id, $filters = array(), $per_page = 50, $paged = 1) {
        global $wpdb;

        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';
        $table_tasks = $wpdb->prefix . 'hhmgt_tasks';
        $table_states = $wpdb->prefix . 'hhmgt_task_states';
        $table_departments = $wpdb->prefix . 'hhmgt_departments';
        $table_locations = $wpdb->prefix . 'hhmgt_location_hierarchy';

        // Base query
        $select = "SELECT i.*,
            t.task_name, t.description AS task_description, t.recurrence_type,
            s.state_name, s.color_hex, s.is_complete_state,
            d.dept_name, d.icon_name AS dept_icon, d.color_hex AS dept_color,
            lh.full_path AS location_path,
            u.display_name AS completed_by_name";

        $from = " FROM {$table_instances} i
            LEFT JOIN {$table_tasks} t ON i.task_id = t.id
            LEFT JOIN {$table_states} s ON i.status_id = s.id
            LEFT JOIN {$table_departments} d ON t.department_id = d.id
            LEFT JOIN {$table_locations} lh ON i.location_hierarchy_id = lh.id
            LEFT JOIN {$wpdb->users} u ON i.completed_by = u.ID";

        $where = " WHERE i.location_id = %d";
        $params = array($location_id);

        // Apply filters
        if (!empty($filters['department'])) {
            $placeholders = implode(',', array_fill(0, count($filters['department']), '%d'));
            $where .= " AND t.department_id IN ({$placeholders})";
            $params = array_merge($params, $filters['department']);
        }

        if (!empty($filters['status'])) {
            $placeholders = implode(',', array_fill(0, count($filters['status']), '%d'));
            $where .= " AND i.status_id IN ({$placeholders})";
            $params = array_merge($params, $filters['status']);
        }

        if (!empty($filters['search'])) {
            $where .= " AND t.task_name LIKE %s";
            $params[] = '%' . $wpdb->esc_like($filters['search']) . '%';
        }

        // Include completed filter - by default exclude completed tasks
        if (empty($filters['include_completed'])) {
            $where .= " AND (s.is_complete_state IS NULL OR s.is_complete_state = 0)";
        }

        // Scheduled date range filter
        if (!empty($filters['filter_scheduled'])) {
            if (!empty($filters['scheduled_from'])) {
                $where .= " AND i.scheduled_date >= %s";
                $params[] = $filters['scheduled_from'];
            }
            if (!empty($filters['scheduled_to'])) {
                $where .= " AND i.scheduled_date <= %s";
                $params[] = $filters['scheduled_to'];
            }
        }

        // Due date range filter
        if (!empty($filters['filter_due'])) {
            if (!empty($filters['due_from'])) {
                $where .= " AND i.due_date >= %s";
                $params[] = $filters['due_from'];
            }
            if (!empty($filters['due_to'])) {
                $where .= " AND i.due_date <= %s";
                $params[] = $filters['due_to'];
            }
        }

        // Completed date range filter
        if (!empty($filters['filter_completed'])) {
            if (!empty($filters['completed_from'])) {
                $where .= " AND DATE(i.completed_at) >= %s";
                $params[] = $filters['completed_from'];
            }
            if (!empty($filters['completed_to'])) {
                $where .= " AND DATE(i.completed_at) <= %s";
                $params[] = $filters['completed_to'];
            }
        }

        // Order by due date, then scheduled date
        $order = " ORDER BY i.due_date ASC, i.scheduled_date ASC";

        // Get total count (use copy of params for count query)
        $count_params = $params;
        $count_query = "SELECT COUNT(*) " . $from . $where;
        $total = $wpdb->get_var($wpdb->prepare($count_query, $count_params));

        // Add pagination to main query params
        $offset = ($paged - 1) * $per_page;
        $limit = " LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        // Get instances
        $query = $select . $from . $where . $order . $limit;
        $instances = $wpdb->get_results($wpdb->prepare($query, $params));

        return array(
            'instances' => $instances ? $instances : array(),
            'total' => intval($total)
        );
    }

    /**
     * Get task states for a location
     *
     * @param int $location_id Location ID
     * @return array Array of states
     */
    private static function get_task_states($location_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'hhmgt_task_states';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, state_name, color_hex, is_complete_state
            FROM {$table}
            WHERE location_id = %d AND is_enabled = 1
            ORDER BY sort_order ASC",
            $location_id
        ));
    }

    /**
     * Save task (create or update)
     */
    public function save_task() {
        // Check nonce
        if (!isset($_POST['hhmgt_task_nonce']) ||
            !wp_verify_nonce($_POST['hhmgt_task_nonce'], 'hhmgt_save_task')) {
            wp_die(__('Security check failed', 'hhmgt'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'hhmgt'));
        }

        global $wpdb;

        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;

        if (!$location_id) {
            wp_die(__('Invalid location', 'hhmgt'));
        }

        // Prepare task data
        $task_data = array(
            'location_id' => $location_id,
            'task_name' => sanitize_text_field($_POST['task_name']),
            'description' => wp_kses_post($_POST['description'] ?? ''),
            'recurrence_type' => in_array($_POST['recurrence_type'], array('none', 'fixed', 'dynamic')) ? $_POST['recurrence_type'] : 'none',
            'recurrence_pattern_id' => isset($_POST['recurrence_pattern_id']) ? intval($_POST['recurrence_pattern_id']) : null,
            'department_id' => isset($_POST['department_id']) ? intval($_POST['department_id']) : null,
            'checklist_items' => isset($_POST['checklist_items']) ? json_encode($_POST['checklist_items']) : null,
            'reference_photos' => isset($_POST['reference_photos']) ? json_encode($_POST['reference_photos']) : null,
            'require_completion_photo' => isset($_POST['require_completion_photo']) ? 1 : 0,
            'completion_reminder_text' => sanitize_textarea_field($_POST['completion_reminder_text'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'applies_to_multiple_locations' => isset($_POST['applies_to_multiple_locations']) ? 1 : 0,
        );

        $update_future_instances = isset($_POST['update_future_instances']) ? true : false;

        if ($task_id) {
            // Update existing task
            $task_data['updated_at'] = current_time('mysql');

            if ($update_future_instances) {
                // Use bulk update utility
                $result = HHMGT_Bulk_Update::update_task_and_instances($task_id, $task_data, true);
            } else {
                // Just update task
                $wpdb->update(
                    $wpdb->prefix . 'hhmgt_tasks',
                    $task_data,
                    array('id' => $task_id),
                    null,
                    array('%d')
                );
            }

            $redirect_task_id = $task_id;
        } else {
            // Create new task
            $task_data['created_by'] = get_current_user_id();
            $task_data['created_at'] = current_time('mysql');

            $wpdb->insert(
                $wpdb->prefix . 'hhmgt_tasks',
                $task_data
            );

            $redirect_task_id = $wpdb->insert_id;
        }

        // Update task locations if multi-location
        if ($task_data['applies_to_multiple_locations']) {
            $this->update_task_locations($redirect_task_id, $_POST['location_hierarchy_ids'] ?? array());
        } else {
            // Clear any existing location assignments
            $wpdb->delete(
                $wpdb->prefix . 'hhmgt_task_locations',
                array('task_id' => $redirect_task_id),
                array('%d')
            );
        }

        // Redirect back
        wp_redirect(add_query_arg(
            array(
                'page' => 'hhmgt-tasks',
                'location_id' => $location_id,
                'updated' => 'true'
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Update task location assignments
     */
    private function update_task_locations($task_id, $location_hierarchy_ids) {
        global $wpdb;
        $table = $wpdb->prefix . 'hhmgt_task_locations';

        // Delete existing assignments
        $wpdb->delete($table, array('task_id' => $task_id), array('%d'));

        // Insert new assignments
        if (is_array($location_hierarchy_ids) && !empty($location_hierarchy_ids)) {
            foreach ($location_hierarchy_ids as $location_hierarchy_id) {
                $wpdb->insert(
                    $table,
                    array(
                        'task_id' => $task_id,
                        'location_hierarchy_id' => intval($location_hierarchy_id),
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%d', '%s')
                );
            }
        }
    }

    /**
     * Delete task
     */
    public function delete_task() {
        // Check nonce
        if (!isset($_GET['_wpnonce']) ||
            !wp_verify_nonce($_GET['_wpnonce'], 'hhmgt_delete_task_' . $_GET['task_id'])) {
            wp_die(__('Security check failed', 'hhmgt'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'hhmgt'));
        }

        $task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;
        $location_id = isset($_GET['location_id']) ? intval($_GET['location_id']) : 0;

        if ($task_id) {
            // Delete all instances
            HHMGT_Bulk_Update::delete_all_instances($task_id);

            // Delete task locations
            global $wpdb;
            $wpdb->delete(
                $wpdb->prefix . 'hhmgt_task_locations',
                array('task_id' => $task_id),
                array('%d')
            );

            // Delete task
            $wpdb->delete(
                $wpdb->prefix . 'hhmgt_tasks',
                array('id' => $task_id),
                array('%d')
            );
        }

        // Redirect back
        wp_redirect(add_query_arg(
            array(
                'page' => 'hhmgt-tasks',
                'location_id' => $location_id,
                'deleted' => 'true'
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Update future task instances
     */
    public function update_future_tasks() {
        // Check nonce
        if (!isset($_POST['_wpnonce']) ||
            !wp_verify_nonce($_POST['_wpnonce'], 'hhmgt_update_future_' . $_POST['task_id'])) {
            wp_die(__('Security check failed', 'hhmgt'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'hhmgt'));
        }

        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
        $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';

        if (!$task_id) {
            wp_die(__('Invalid task', 'hhmgt'));
        }

        $count = 0;
        switch ($action_type) {
            case 'update':
                $count = HHMGT_Bulk_Update::update_future_instances($task_id);
                break;

            case 'reschedule':
                $new_interval = isset($_POST['new_interval_days']) ? intval($_POST['new_interval_days']) : 0;
                if ($new_interval > 0) {
                    $count = HHMGT_Bulk_Update::reschedule_future_instances($task_id, $new_interval);
                }
                break;

            case 'clear':
                $count = HHMGT_Bulk_Update::clear_future_instances($task_id);
                break;
        }

        // Redirect back
        wp_redirect(add_query_arg(
            array(
                'page' => 'hhmgt-edit-task',
                'task_id' => $task_id,
                'location_id' => $location_id,
                'future_updated' => $count
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * AJAX: Get task data
     */
    public function ajax_get_task() {
        check_ajax_referer('hhmgt_admin_nonce', 'nonce');

        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;

        if (!$task_id) {
            wp_send_json_error(array('message' => 'Invalid task ID'));
        }

        $task = self::get_task($task_id);

        if (!$task) {
            wp_send_json_error(array('message' => 'Task not found'));
        }

        // Get assigned locations if multi-location
        $task_locations = array();
        if ($task->applies_to_multiple_locations) {
            $task_locations = self::get_task_locations($task_id);
        }

        wp_send_json_success(array(
            'task' => $task,
            'locations' => $task_locations
        ));
    }

    /**
     * Get tasks for a location
     */
    private static function get_tasks($location_id) {
        global $wpdb;

        $table_tasks = $wpdb->prefix . 'hhmgt_tasks';
        $table_departments = $wpdb->prefix . 'hhmgt_departments';
        $table_patterns = $wpdb->prefix . 'hhmgt_recurring_patterns';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.*,
                d.dept_name,
                d.icon_name as dept_icon,
                d.color_hex as dept_color,
                p.pattern_name,
                p.interval_days
            FROM {$table_tasks} t
            LEFT JOIN {$table_departments} d ON t.department_id = d.id
            LEFT JOIN {$table_patterns} p ON t.recurrence_pattern_id = p.id
            WHERE t.location_id = %d
            ORDER BY t.is_active DESC, t.task_name ASC",
            $location_id
        ));
    }

    /**
     * Get single task
     */
    private static function get_task($task_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hhmgt_tasks WHERE id = %d",
            $task_id
        ));
    }

    /**
     * Get task location assignments
     */
    private static function get_task_locations($task_id) {
        global $wpdb;

        return $wpdb->get_col($wpdb->prepare(
            "SELECT location_hierarchy_id FROM {$wpdb->prefix}hhmgt_task_locations WHERE task_id = %d",
            $task_id
        ));
    }

    /**
     * Get departments for a location
     */
    private static function get_departments($location_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hhmgt_departments
            WHERE location_id = %d AND is_enabled = 1
            ORDER BY sort_order ASC",
            $location_id
        ));
    }

    /**
     * Get recurring patterns for a location
     */
    private static function get_patterns($location_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hhmgt_recurring_patterns
            WHERE location_id = %d AND is_enabled = 1
            ORDER BY interval_days ASC",
            $location_id
        ));
    }

    /**
     * Get location hierarchy for a location (properly ordered for display)
     */
    private static function get_location_hierarchy($location_id) {
        global $wpdb;

        // Get all locations ordered by sort_order
        $locations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hhmgt_location_hierarchy
            WHERE location_id = %d AND is_enabled = 1
            ORDER BY sort_order ASC",
            $location_id
        ));

        // Build hierarchical structure and flatten for proper display order
        return self::flatten_hierarchy($locations);
    }

    /**
     * Recursively flatten hierarchy for proper display order
     *
     * @param array $locations All locations
     * @param int|null $parent_id Parent ID to filter by
     * @param int $level Current hierarchy level
     * @return array Flattened locations in proper order
     */
    private static function flatten_hierarchy($locations, $parent_id = null, $level = 0) {
        $result = array();
        foreach ($locations as $loc) {
            // Match parent_id (handle both null and 0 as root)
            $loc_parent = $loc->parent_id ? intval($loc->parent_id) : null;
            if ($loc_parent === $parent_id) {
                $loc->hierarchy_level = $level;
                $loc->has_children = self::location_has_children($locations, $loc->id);
                $result[] = $loc;
                // Recursively add children immediately after parent
                $children = self::flatten_hierarchy($locations, intval($loc->id), $level + 1);
                $result = array_merge($result, $children);
            }
        }
        return $result;
    }

    /**
     * Check if a location has children
     *
     * @param array $locations All locations
     * @param int $parent_id Parent ID to check
     * @return bool
     */
    private static function location_has_children($locations, $parent_id) {
        foreach ($locations as $loc) {
            if ($loc->parent_id && intval($loc->parent_id) === intval($parent_id)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get checklist templates for a location
     */
    private static function get_checklist_templates($location_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hhmgt_checklist_templates
            WHERE location_id = %d
            ORDER BY template_name ASC",
            $location_id
        ));
    }

    /**
     * Auto-sync settings from options to database if needed
     */
    private static function maybe_auto_sync_settings($location_id) {
        $settings = get_option(HHMGT_Settings::OPTION_NAME, array());
        $location_settings = $settings[$location_id] ?? array();

        if (empty($location_settings)) {
            return;
        }

        $settings_instance = HHMGT_Settings::instance();
        global $wpdb;

        try {
            $reflection = new ReflectionClass($settings_instance);

            // Check and sync departments
            if (!empty($location_settings['departments'])) {
                $dept_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}hhmgt_departments WHERE location_id = %d",
                    $location_id
                ));

                if ($dept_count == 0) {
                    $method = $reflection->getMethod('sync_departments');
                    $method->setAccessible(true);
                    $method->invoke($settings_instance, $location_id, $location_settings['departments']);
                }
            }

            // Check and sync patterns
            if (!empty($location_settings['recurring_patterns'])) {
                $pattern_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}hhmgt_recurring_patterns WHERE location_id = %d",
                    $location_id
                ));

                if ($pattern_count == 0) {
                    $method = $reflection->getMethod('sync_patterns');
                    $method->setAccessible(true);
                    $method->invoke($settings_instance, $location_id, $location_settings['recurring_patterns']);
                }
            }

            // Check and sync states
            if (!empty($location_settings['task_states'])) {
                $state_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}hhmgt_task_states WHERE location_id = %d",
                    $location_id
                ));

                if ($state_count == 0) {
                    $method = $reflection->getMethod('sync_states');
                    $method->setAccessible(true);
                    $method->invoke($settings_instance, $location_id, $location_settings['task_states']);
                }
            }
        } catch (Exception $e) {
            // Silently fail - sync will happen on next settings save
        }
    }

    /**
     * AJAX: Schedule task instances now
     */
    public function ajax_schedule_task_now() {
        check_ajax_referer('hhmgt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'hhmgt')));
        }

        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;

        if (!$task_id) {
            wp_send_json_error(array('message' => __('Invalid task ID', 'hhmgt')));
        }

        // Get the task
        $task = self::get_task($task_id);

        if (!$task || $task->recurrence_type === 'none') {
            wp_send_json_error(array('message' => __('Task is not a recurring task', 'hhmgt')));
        }

        // Call the scheduler to process this specific task
        $scheduler = HHMGT_Scheduler::instance();
        $count = $scheduler->schedule_task_instances($task_id);

        if ($count > 0) {
            wp_send_json_success(array(
                'message' => sprintf(
                    _n('Created %d task instance.', 'Created %d task instances.', $count, 'hhmgt'),
                    $count
                )
            ));
        } else {
            wp_send_json_success(array(
                'message' => __('No new instances created. Instances may already exist or task settings need review.', 'hhmgt')
            ));
        }
    }

    /**
     * AJAX: Delete a task instance
     */
    public function ajax_delete_instance() {
        check_ajax_referer('hhmgt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'hhmgt')));
        }

        $instance_id = isset($_POST['instance_id']) ? intval($_POST['instance_id']) : 0;

        if (!$instance_id) {
            wp_send_json_error(array('message' => __('Invalid instance ID', 'hhmgt')));
        }

        global $wpdb;
        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';
        $table_notes = $wpdb->prefix . 'hhmgt_task_notes';

        // Check if instance exists
        $instance = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_instances} WHERE id = %d",
            $instance_id
        ));

        if (!$instance) {
            wp_send_json_error(array('message' => __('Task instance not found', 'hhmgt')));
        }

        // Delete related notes first
        $wpdb->delete($table_notes, array('task_instance_id' => $instance_id), array('%d'));

        // Delete the instance
        $deleted = $wpdb->delete($table_instances, array('id' => $instance_id), array('%d'));

        if ($deleted) {
            wp_send_json_success(array('message' => __('Task instance deleted successfully', 'hhmgt')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete task instance', 'hhmgt')));
        }
    }
}
