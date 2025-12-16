<?php
/**
 * Daily List Integration Class
 *
 * Integrates Management Tasks module with the Housekeeping Daily List module
 * to show task counts on room cards and task details in room modals.
 *
 * @package HotelHub_Management_Tasks
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Daily List Integration class
 */
class HHMGT_DailyList_Integration {

    /**
     * Singleton instance
     */
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
    public function __construct() {
        // Only hook if Daily List module is active
        if (!class_exists('HHDL_Display')) {
            return;
        }

        // Hook into Daily List modal sections
        add_action('hhdl_modal_sections', array($this, 'render_tasks_section'), 10, 5);

        // Hook into room data loading to add task counts
        add_filter('hhdl_room_data', array($this, 'add_task_counts'), 10, 3);

        // Conditionally enqueue assets on Daily List pages
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));

        // Add modal container to footer
        add_action('wp_footer', array($this, 'render_task_modal_container'));

        // AJAX endpoint to refresh tasks section
        add_action('wp_ajax_hhmgt_refresh_room_tasks', array($this, 'ajax_refresh_room_tasks'));
    }

    /**
     * AJAX handler to refresh tasks section for a room
     * Returns HTML for the tasks section
     */
    public function ajax_refresh_room_tasks() {
        check_ajax_referer('hhmgt_ajax_nonce', 'nonce');

        $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
        $room_number = isset($_POST['room_number']) ? sanitize_text_field($_POST['room_number']) : '';
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : date('Y-m-d');

        if (!$location_id || !$room_number) {
            wp_send_json_error(array('message' => __('Invalid parameters', 'hhmgt')));
        }

        // Get settings
        $dl_settings = class_exists('HHDL_Settings')
            ? HHDL_Settings::get_location_settings($location_id)
            : array();

        $enabled = isset($dl_settings['task_departments_enabled']) ? $dl_settings['task_departments_enabled'] : true;
        if (!$enabled) {
            wp_send_json_success(array('html' => '', 'count' => 0));
        }

        $default_dept_ids = isset($dl_settings['task_departments_default']) ? $dl_settings['task_departments_default'] : array();

        // Get tasks
        $default_tasks = self::get_tasks_for_room($location_id, $room_number, $date, $default_dept_ids, false);
        $all_tasks = self::get_tasks_for_room($location_id, $room_number, $date, null, true);

        // Calculate counts
        $total_count = count($all_tasks);
        $overdue_count = 0;
        $due_today_count = 0;
        foreach ($all_tasks as $task) {
            if ($task->urgency === 'overdue') $overdue_count++;
            elseif ($task->urgency === 'due') $due_today_count++;
        }

        // Generate HTML
        ob_start();
        if (!empty($all_tasks)) {
            $other_tasks_count = count($all_tasks) - count($default_tasks);
            ?>
            <section class="hhmgt-tasks-section"
                 data-room-number="<?php echo esc_attr($room_number); ?>"
                 data-location-id="<?php echo esc_attr($location_id); ?>"
                 data-date="<?php echo esc_attr($date); ?>">

                <div class="hhmgt-tasks-header">
                    <h3>
                        <span class="material-symbols-outlined">checklist_rtl</span>
                        <?php _e('Recurring Tasks', 'hhmgt'); ?>
                    </h3>
                    <?php if ($other_tasks_count > 0): ?>
                        <label class="hhmgt-show-all-toggle">
                            <input type="checkbox" class="hhmgt-show-all-depts">
                            <span><?php printf(__('Show all departments (+%d)', 'hhmgt'), $other_tasks_count); ?></span>
                        </label>
                    <?php endif; ?>
                </div>

                <div class="hhmgt-task-list hhmgt-tasks-default">
                    <?php foreach ($default_tasks as $task): ?>
                        <?php $this->render_task_item($task); ?>
                    <?php endforeach; ?>
                    <?php if (empty($default_tasks) && $other_tasks_count > 0): ?>
                        <p class="hhmgt-no-default-tasks">
                            <?php _e('No tasks from default departments. Toggle "Show all" to see other tasks.', 'hhmgt'); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($other_tasks_count > 0): ?>
                <div class="hhmgt-task-list hhmgt-tasks-other" style="display: none;">
                    <?php
                    $default_instance_ids = array_map(function($t) { return $t->instance_id; }, $default_tasks);
                    foreach ($all_tasks as $task):
                        if (!in_array($task->instance_id, $default_instance_ids)):
                            $this->render_task_item($task);
                        endif;
                    endforeach;
                    ?>
                </div>
                <?php endif; ?>
            </section>
            <?php
        }
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
            'count' => array(
                'total' => $total_count,
                'overdue' => $overdue_count,
                'due_today' => $due_today_count
            )
        ));
    }

    /**
     * Check if current page is a Daily List page
     *
     * @return bool
     */
    private function is_dailylist_page() {
        // Check if we're in the Hotel Hub app context with Daily List module
        if (function_exists('hha_get_current_module')) {
            $module = hha_get_current_module();
            return $module === 'daily_list';
        }

        // Fallback: check if HHDL_Display class is rendering
        global $wp_query;
        if (isset($wp_query->query_vars['hha_module']) && $wp_query->query_vars['hha_module'] === 'daily_list') {
            return true;
        }

        return false;
    }

    /**
     * Get tasks for a specific room by location_name match
     *
     * @param int $location_id Hotel/workforce location ID
     * @param string $room_number Room number/name to match
     * @param string $date Date in Y-m-d format
     * @param array|null $department_ids Array of department IDs to filter by, or null for all
     * @param bool $include_all If true, ignores department_ids filter
     * @return array Array of task objects
     */
    public static function get_tasks_for_room($location_id, $room_number, $date, $department_ids = null, $include_all = false) {
        global $wpdb;

        if (empty($room_number)) {
            return array();
        }

        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';
        $table_tasks = $wpdb->prefix . 'hhmgt_tasks';
        $table_locations = $wpdb->prefix . 'hhmgt_location_hierarchy';
        $table_states = $wpdb->prefix . 'hhmgt_task_states';
        $table_depts = $wpdb->prefix . 'hhmgt_departments';

        // Check if tables exist
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_instances'") !== $table_instances) {
            return array();
        }

        $where = array(
            "lh.location_id = %d",
            "lh.location_name = %s",
            "t.is_active = 1",
            "(s.is_complete_state IS NULL OR s.is_complete_state = 0)",
            "i.due_date <= %s"  // Due today or overdue
        );
        $params = array($location_id, $room_number, $date);

        // Department filtering (unless include_all)
        if (!$include_all && !empty($department_ids)) {
            $placeholders = implode(',', array_fill(0, count($department_ids), '%d'));
            $where[] = "t.department_id IN ($placeholders)";
            $params = array_merge($params, $department_ids);
        }

        $sql = "SELECT
                    i.id AS instance_id,
                    i.due_date,
                    i.status_id,
                    t.id AS task_id,
                    t.task_name,
                    t.department_id,
                    d.dept_name,
                    d.color_hex AS dept_color,
                    d.icon_name AS dept_icon,
                    s.state_name,
                    s.state_slug,
                    s.color_hex AS status_color,
                    CASE
                        WHEN i.due_date < %s THEN 'overdue'
                        WHEN i.due_date = %s THEN 'due'
                        ELSE 'pending'
                    END AS urgency
                FROM {$table_instances} i
                INNER JOIN {$table_tasks} t ON i.task_id = t.id
                INNER JOIN {$table_locations} lh ON i.location_hierarchy_id = lh.id
                LEFT JOIN {$table_states} s ON i.status_id = s.id
                LEFT JOIN {$table_depts} d ON t.department_id = d.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY i.due_date ASC, t.task_name ASC";

        // Add date params for CASE statement at the beginning
        array_unshift($params, $date, $date);

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Get task counts for multiple rooms (batch query)
     *
     * @param int $location_id Hotel/workforce location ID
     * @param array $room_numbers Array of room numbers to query
     * @param string $date Date in Y-m-d format
     * @param array|null $department_ids Array of department IDs to filter by
     * @return array Associative array keyed by room_number with counts
     */
    public static function get_task_counts_batch($location_id, $room_numbers, $date, $department_ids = null) {
        global $wpdb;

        if (empty($room_numbers)) {
            return array();
        }

        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';
        $table_tasks = $wpdb->prefix . 'hhmgt_tasks';
        $table_locations = $wpdb->prefix . 'hhmgt_location_hierarchy';
        $table_states = $wpdb->prefix . 'hhmgt_task_states';

        // Check if tables exist
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_instances'") !== $table_instances) {
            return array();
        }

        $room_placeholders = implode(',', array_fill(0, count($room_numbers), '%s'));

        // Build params array - date params for CASE first, then location_id, room_numbers, date for WHERE
        $params = array($date, $date, $location_id);
        $params = array_merge($params, $room_numbers);
        $params[] = $date;

        $dept_filter = '';
        if (!empty($department_ids)) {
            $dept_placeholders = implode(',', array_fill(0, count($department_ids), '%d'));
            $dept_filter = "AND t.department_id IN ($dept_placeholders)";
            $params = array_merge($params, $department_ids);
        }

        $sql = "SELECT
                    lh.location_name AS room_number,
                    SUM(CASE WHEN i.due_date < %s THEN 1 ELSE 0 END) AS overdue,
                    SUM(CASE WHEN i.due_date = %s THEN 1 ELSE 0 END) AS due_today,
                    COUNT(*) AS total
                FROM {$table_instances} i
                INNER JOIN {$table_tasks} t ON i.task_id = t.id
                INNER JOIN {$table_locations} lh ON i.location_hierarchy_id = lh.id
                LEFT JOIN {$table_states} s ON i.status_id = s.id
                WHERE lh.location_id = %d
                  AND lh.location_name IN ($room_placeholders)
                  AND t.is_active = 1
                  AND (s.is_complete_state IS NULL OR s.is_complete_state = 0)
                  AND i.due_date <= %s
                  {$dept_filter}
                GROUP BY lh.location_name";

        $results = $wpdb->get_results($wpdb->prepare($sql, $params));

        // Key by room_number for easy lookup
        $counts = array();
        if ($results) {
            foreach ($results as $row) {
                $counts[$row->room_number] = array(
                    'overdue' => (int)$row->overdue,
                    'due_today' => (int)$row->due_today,
                    'total' => (int)$row->total
                );
            }
        }

        return $counts;
    }

    /**
     * Add task counts to room data via filter hook
     *
     * @param array $rooms Array of room data
     * @param int $location_id Location ID
     * @param string $date Date in Y-m-d format
     * @return array Modified room data with task counts
     */
    public function add_task_counts($rooms, $location_id, $date) {
        if (empty($rooms) || !is_array($rooms)) {
            return $rooms;
        }

        // Get settings from Daily List
        $dl_settings = class_exists('HHDL_Settings')
            ? HHDL_Settings::get_location_settings($location_id)
            : array();

        // Check if integration is enabled
        $enabled = isset($dl_settings['task_departments_enabled']) ? $dl_settings['task_departments_enabled'] : true;
        if (!$enabled) {
            return $rooms;
        }

        // Get default department IDs
        $department_ids = isset($dl_settings['task_departments_default']) ? $dl_settings['task_departments_default'] : array();

        // Collect all room numbers for batch query
        $room_numbers = array();
        foreach ($rooms as $room) {
            if (isset($room['room_number']) && !empty($room['room_number'])) {
                $room_numbers[] = $room['room_number'];
            }
        }

        if (empty($room_numbers)) {
            return $rooms;
        }

        // Get task counts for all rooms in one query
        $task_counts = self::get_task_counts_batch($location_id, $room_numbers, $date, $department_ids);

        // Add task counts to each room
        foreach ($rooms as &$room) {
            $room_number = isset($room['room_number']) ? $room['room_number'] : '';
            if (isset($task_counts[$room_number])) {
                $room['module_tasks'] = $task_counts[$room_number];
            } else {
                $room['module_tasks'] = array(
                    'overdue' => 0,
                    'due_today' => 0,
                    'total' => 0
                );
            }
        }

        return $rooms;
    }

    /**
     * Render tasks section in room modal
     *
     * @param int $location_id Location ID
     * @param string $room_id Room/site ID
     * @param string $date Date in Y-m-d format
     * @param array $room_details Full room details array
     * @param array $booking_data Booking data
     */
    public function render_tasks_section($location_id, $room_id, $date, $room_details, $booking_data) {
        $room_number = isset($room_details['room_number']) ? $room_details['room_number'] : '';

        if (empty($room_number)) {
            return;
        }

        // Get department settings from Daily List
        $dl_settings = class_exists('HHDL_Settings')
            ? HHDL_Settings::get_location_settings($location_id)
            : array();

        $enabled = isset($dl_settings['task_departments_enabled']) ? $dl_settings['task_departments_enabled'] : true;
        if (!$enabled) {
            return;
        }

        $default_dept_ids = isset($dl_settings['task_departments_default']) ? $dl_settings['task_departments_default'] : array();

        // Get default department tasks
        $default_tasks = self::get_tasks_for_room($location_id, $room_number, $date, $default_dept_ids, false);

        // Get ALL tasks (for "show all" toggle)
        $all_tasks = self::get_tasks_for_room($location_id, $room_number, $date, null, true);

        // Calculate "other" tasks count
        $other_tasks_count = count($all_tasks) - count($default_tasks);

        if (empty($all_tasks)) {
            return; // Don't render section if no tasks at all
        }
        ?>
        <section class="hhmgt-tasks-section"
             data-room-number="<?php echo esc_attr($room_number); ?>"
             data-location-id="<?php echo esc_attr($location_id); ?>"
             data-date="<?php echo esc_attr($date); ?>">

            <div class="hhmgt-tasks-header">
                <h3>
                    <span class="material-symbols-outlined">checklist_rtl</span>
                    <?php _e('Recurring Tasks', 'hhmgt'); ?>
                </h3>
                <?php if ($other_tasks_count > 0): ?>
                    <label class="hhmgt-show-all-toggle">
                        <input type="checkbox" class="hhmgt-show-all-depts">
                        <span><?php printf(__('Show all departments (+%d)', 'hhmgt'), $other_tasks_count); ?></span>
                    </label>
                <?php endif; ?>
            </div>

            <!-- Default department tasks (always visible) -->
            <div class="hhmgt-task-list hhmgt-tasks-default">
                <?php if (!empty($default_tasks)): ?>
                    <?php foreach ($default_tasks as $task): ?>
                        <?php $this->render_task_item($task); ?>
                    <?php endforeach; ?>
                <?php elseif ($other_tasks_count > 0): ?>
                    <p class="hhmgt-no-default-tasks">
                        <?php _e('No tasks from default departments. Toggle "Show all" to see other tasks.', 'hhmgt'); ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Other department tasks (hidden by default) -->
            <?php if ($other_tasks_count > 0): ?>
            <div class="hhmgt-task-list hhmgt-tasks-other" style="display: none;">
                <?php
                // Filter to only "other" tasks
                $default_instance_ids = array_map(function($t) { return $t->instance_id; }, $default_tasks);
                foreach ($all_tasks as $task):
                    if (!in_array($task->instance_id, $default_instance_ids)):
                        $this->render_task_item($task);
                    endif;
                endforeach;
                ?>
            </div>
            <?php endif; ?>
        </section>
        <?php
    }

    /**
     * Render a single task item
     *
     * @param object $task Task object from query
     */
    private function render_task_item($task) {
        $urgency_class = 'hhmgt-urgency-' . $task->urgency;

        if ($task->urgency === 'overdue') {
            $due_display = sprintf(__('Overdue: %s', 'hhmgt'), date_i18n('j M', strtotime($task->due_date)));
        } elseif ($task->urgency === 'due') {
            $due_display = __('Due today', 'hhmgt');
        } else {
            $due_display = date_i18n('j M', strtotime($task->due_date));
        }

        $dept_color = !empty($task->dept_color) ? $task->dept_color : '#6b7280';
        $status_color = !empty($task->status_color) ? $task->status_color : '#6b7280';
        ?>
        <div class="hhmgt-task-item <?php echo esc_attr($urgency_class); ?>"
             data-instance-id="<?php echo esc_attr($task->instance_id); ?>">
            <span class="hhmgt-task-dept" style="color: <?php echo esc_attr($dept_color); ?>;">
                <?php echo esc_html($task->dept_name); ?>
            </span>
            <span class="hhmgt-task-name"><?php echo esc_html($task->task_name); ?></span>
            <span class="hhmgt-task-status" style="background: <?php echo esc_attr($status_color); ?>;">
                <?php echo esc_html($task->state_name); ?>
            </span>
            <span class="hhmgt-task-due"><?php echo esc_html($due_display); ?></span>
        </div>
        <?php
    }

    /**
     * Conditionally enqueue assets on Daily List pages
     */
    public function maybe_enqueue_assets() {
        // Always enqueue on frontend if Daily List is loaded
        // The actual check happens in the hooks themselves

        // Enqueue main Tasks module JS (contains openTaskModal)
        wp_enqueue_script(
            'hhmgt-main',
            HHMGT_PLUGIN_URL . 'assets/js/hhmgt.js',
            array('jquery'),
            HHMGT_VERSION,
            true
        );

        // Enqueue Daily List integration JS (handles click delegation and toggle)
        wp_enqueue_script(
            'hhmgt-dailylist',
            HHMGT_PLUGIN_URL . 'assets/js/hhmgt-dailylist.js',
            array('jquery', 'hhmgt-main'),
            HHMGT_VERSION,
            true
        );

        // Enqueue modal CSS (main Tasks module modal styles)
        wp_enqueue_style(
            'hhmgt-modal',
            HHMGT_PLUGIN_URL . 'assets/css/modal.css',
            array(),
            HHMGT_VERSION
        );

        // Enqueue integration CSS (task items in Daily List context)
        wp_enqueue_style(
            'hhmgt-dailylist',
            HHMGT_PLUGIN_URL . 'assets/css/hhmgt-dailylist.css',
            array('hhmgt-modal'),
            HHMGT_VERSION
        );

        // Localize hhmgtData for main Tasks module JS
        wp_localize_script('hhmgt-main', 'hhmgtData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hhmgt_ajax_nonce'),
            'strings' => array(
                'error' => __('An error occurred. Please try again.', 'hhmgt'),
                'loading' => __('Loading...', 'hhmgt'),
                'confirm_delete' => __('Are you sure you want to delete this?', 'hhmgt')
            )
        ));

        // Also localize tasks_url for Daily List specific needs
        wp_localize_script('hhmgt-dailylist', 'hhmgtDLData', array(
            'tasks_url' => admin_url('admin.php?page=hhmgt-instances')
        ));
    }

    /**
     * Render task modal containers for cross-module use
     * Uses same structure as main Tasks module (class-hhmgt-display.php)
     */
    public function render_task_modal_container() {
        ?>
        <!-- Task detail modal -->
        <div id="task-modal" class="hhmgt-modal" style="display: none;">
            <div class="hhmgt-modal-overlay"></div>
            <div class="hhmgt-modal-content">
                <!-- Modal content loaded dynamically by hhmgt.js openTaskModal -->
            </div>
        </div>

        <!-- Completion confirmation modal (reminder + photo upload) -->
        <div id="completion-modal" class="hhmgt-modal hhmgt-modal-small" style="display: none;">
            <div class="hhmgt-modal-overlay"></div>
            <div class="hhmgt-modal-content">
                <!-- Completion form loaded dynamically -->
            </div>
        </div>

        <!-- Status selection modal -->
        <div id="status-selection-modal" class="hhmgt-modal hhmgt-modal-small" style="display: none;">
            <div class="hhmgt-modal-overlay"></div>
            <div class="hhmgt-modal-content">
                <!-- Status selection loaded dynamically -->
            </div>
        </div>

        <!-- Lightbox for viewing photos -->
        <div id="hhmgt-lightbox" class="hhmgt-lightbox">
            <div class="hhmgt-lightbox-content">
                <button type="button" class="hhmgt-lightbox-close">
                    <span class="material-symbols-outlined">close</span>
                </button>
                <button type="button" class="hhmgt-lightbox-nav prev">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
                <img src="" alt="Photo" class="hhmgt-lightbox-image">
                <button type="button" class="hhmgt-lightbox-nav next">
                    <span class="material-symbols-outlined">chevron_right</span>
                </button>
                <div class="hhmgt-lightbox-counter"></div>
            </div>
        </div>
        <?php
    }
}
