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

        // Debug: Log empty data for troubleshooting
        if (empty($departments)) {
            error_log("HHMGT Debug: No departments found for location ID {$current_location_id}");
        }
        if (empty($patterns)) {
            error_log("HHMGT Debug: No patterns found for location ID {$current_location_id}");
        }
        if (empty($location_hierarchy)) {
            error_log("HHMGT Debug: No location hierarchy found for location ID {$current_location_id}");
        }

        // Get checklist templates
        $templates = self::get_checklist_templates($current_location_id);

        // Load template
        include HHMGT_PLUGIN_DIR . 'admin/views/task-edit.php';
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
     * Get location hierarchy for a location
     */
    private static function get_location_hierarchy($location_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hhmgt_location_hierarchy
            WHERE location_id = %d AND is_enabled = 1
            ORDER BY hierarchy_level ASC, sort_order ASC",
            $location_id
        ));
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
            error_log("HHMGT Debug: No settings found in options for location ID {$location_id}");
            return;
        }

        error_log("HHMGT Debug: Found settings in options for location ID {$location_id}");

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
                error_log("HHMGT Debug: Departments in database: {$dept_count}, in options: " . count($location_settings['departments']));

                if ($dept_count == 0) {
                    $method = $reflection->getMethod('sync_departments');
                    $method->setAccessible(true);
                    $method->invoke($settings_instance, $location_id, $location_settings['departments']);
                    error_log("HHMGT Debug: Synced departments to database");
                }
            }

            // Check and sync patterns
            if (!empty($location_settings['recurring_patterns'])) {
                $pattern_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}hhmgt_recurring_patterns WHERE location_id = %d",
                    $location_id
                ));
                error_log("HHMGT Debug: Patterns in database: {$pattern_count}, in options: " . count($location_settings['recurring_patterns']));

                if ($pattern_count == 0) {
                    $method = $reflection->getMethod('sync_patterns');
                    $method->setAccessible(true);
                    $method->invoke($settings_instance, $location_id, $location_settings['recurring_patterns']);
                    error_log("HHMGT Debug: Synced patterns to database");
                }
            }

            // Check and sync states
            if (!empty($location_settings['task_states'])) {
                $state_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}hhmgt_task_states WHERE location_id = %d",
                    $location_id
                ));
                error_log("HHMGT Debug: States in database: {$state_count}, in options: " . count($location_settings['task_states']));

                if ($state_count == 0) {
                    $method = $reflection->getMethod('sync_states');
                    $method->setAccessible(true);
                    $method->invoke($settings_instance, $location_id, $location_settings['task_states']);
                    error_log("HHMGT Debug: Synced states to database");
                }
            }
        } catch (Exception $e) {
            error_log("HHMGT Error in auto-sync: " . $e->getMessage());
        }
    }
}
