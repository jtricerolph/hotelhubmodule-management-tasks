<?php
/**
 * Plugin Name: Hotel Hub Module - Management - Tasks
 * Description: Comprehensive recurring task management system with flexible scheduling, hierarchical locations, and multi-department support
 * Version: 1.0.0
 * Author: JTR
 * License: GPL v2 or later
 * Text Domain: hhmgt
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: hotel-hub-app
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HHMGT_VERSION', '1.0.0');
define('HHMGT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HHMGT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HHMGT_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class - Singleton Pattern
 */
class HotelHub_Management_Tasks {
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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once HHMGT_PLUGIN_DIR . 'includes/class-hhmgt-core.php';
        require_once HHMGT_PLUGIN_DIR . 'includes/class-hhmgt-settings.php';
        require_once HHMGT_PLUGIN_DIR . 'includes/class-hhmgt-display.php';
        require_once HHMGT_PLUGIN_DIR . 'includes/class-hhmgt-ajax.php';
        require_once HHMGT_PLUGIN_DIR . 'includes/class-hhmgt-heartbeat.php';
        require_once HHMGT_PLUGIN_DIR . 'includes/class-hhmgt-scheduler.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Check dependencies
        if (!$this->check_dependencies()) {
            return;
        }

        // Initialize core
        HHMGT_Core::instance();
    }

    /**
     * Check required dependencies
     */
    private function check_dependencies() {
        $required_plugins = array(
            'hotel-hub-app/hotel-hub-app.php' => 'Hotel Hub App'
        );

        $missing_plugins = array();

        foreach ($required_plugins as $plugin_file => $plugin_name) {
            if (!is_plugin_active($plugin_file)) {
                $missing_plugins[] = $plugin_name;
            }
        }

        if (!empty($missing_plugins)) {
            add_action('admin_notices', function() use ($missing_plugins) {
                ?>
                <div class="notice notice-error">
                    <p><strong>Hotel Hub Module - Management - Tasks</strong> requires the following plugins:</p>
                    <ul>
                        <?php foreach ($missing_plugins as $plugin): ?>
                            <li><?php echo esc_html($plugin); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php
            });

            return false;
        }

        return true;
    }

    /**
     * Plugin activation
     */
    public function activate() {
        $this->create_tables();
        $this->set_default_options();

        // Schedule cron job
        if (!wp_next_scheduled('hhmgt_process_recurring_tasks')) {
            wp_schedule_event(time(), 'hourly', 'hhmgt_process_recurring_tasks');
        }

        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Tasks table - Main task definitions
        $table_tasks = $wpdb->prefix . 'hhmgt_tasks';
        $sql_tasks = "CREATE TABLE {$table_tasks} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            location_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Hotel/workforce location ID',
            task_name VARCHAR(255) NOT NULL,
            description TEXT,
            recurrence_type ENUM('none', 'fixed', 'dynamic') DEFAULT 'none',
            recurrence_pattern_id BIGINT(20) UNSIGNED DEFAULT NULL,
            department_id BIGINT(20) UNSIGNED DEFAULT NULL,
            location_hierarchy_id BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'Specific location within hotel',
            checklist_template_id BIGINT(20) UNSIGNED DEFAULT NULL,
            checklist_items TEXT COMMENT 'JSON array of checklist items',
            reference_photos TEXT COMMENT 'JSON array of photo attachment IDs',
            require_completion_photo BOOLEAN DEFAULT FALSE,
            completion_reminder_text TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY location_idx (location_id),
            KEY department_idx (department_id),
            KEY location_hierarchy_idx (location_hierarchy_id),
            KEY active_idx (is_active)
        ) $charset_collate;";

        // Task instances table - Individual occurrences
        $table_instances = $wpdb->prefix . 'hhmgt_task_instances';
        $sql_instances = "CREATE TABLE {$table_instances} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id BIGINT(20) UNSIGNED NOT NULL,
            location_id BIGINT(20) UNSIGNED NOT NULL,
            scheduled_date DATE NOT NULL COMMENT 'When task was scheduled',
            due_date DATE NOT NULL COMMENT 'When task is due',
            status_id BIGINT(20) UNSIGNED DEFAULT NULL,
            checklist_state TEXT COMMENT 'JSON array of checkbox states',
            completed_by BIGINT(20) UNSIGNED DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            completion_photos TEXT COMMENT 'JSON array of photo attachment IDs',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY task_date_idx (task_id, due_date),
            KEY location_date_idx (location_id, due_date),
            KEY status_idx (status_id),
            KEY due_date_idx (due_date),
            UNIQUE KEY unique_task_date (task_id, scheduled_date)
        ) $charset_collate;";

        // Departments table
        $table_departments = $wpdb->prefix . 'hhmgt_departments';
        $sql_departments = "CREATE TABLE {$table_departments} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            location_id BIGINT(20) UNSIGNED NOT NULL,
            dept_name VARCHAR(255) NOT NULL,
            dept_slug VARCHAR(255) NOT NULL,
            icon_name VARCHAR(100) DEFAULT 'assignment_turned_in' COMMENT 'Material Symbol icon name',
            color_hex VARCHAR(7) DEFAULT '#8b5cf6',
            description TEXT,
            is_enabled BOOLEAN DEFAULT TRUE,
            sort_order INT(11) DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY location_slug_idx (location_id, dept_slug),
            KEY enabled_idx (is_enabled)
        ) $charset_collate;";

        // Location hierarchy table - Flexible depth structure
        $table_locations = $wpdb->prefix . 'hhmgt_location_hierarchy';
        $sql_locations = "CREATE TABLE {$table_locations} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            location_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Hotel/workforce location ID',
            parent_id BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'Parent location for hierarchy',
            hierarchy_level INT(11) DEFAULT 0 COMMENT 'Depth level: 0=root, 1=child, etc',
            location_name VARCHAR(255) NOT NULL,
            location_type VARCHAR(100) DEFAULT NULL COMMENT 'e.g., Bedroom, Bathroom, Storeroom',
            full_path VARCHAR(500) DEFAULT NULL COMMENT 'Full hierarchical path for display',
            sort_order INT(11) DEFAULT 0,
            is_enabled BOOLEAN DEFAULT TRUE,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY location_parent_idx (location_id, parent_id),
            KEY hierarchy_level_idx (hierarchy_level),
            KEY enabled_idx (is_enabled)
        ) $charset_collate;";

        // Recurring patterns table
        $table_patterns = $wpdb->prefix . 'hhmgt_recurring_patterns';
        $sql_patterns = "CREATE TABLE {$table_patterns} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            location_id BIGINT(20) UNSIGNED NOT NULL,
            pattern_name VARCHAR(255) NOT NULL,
            interval_type ENUM('fixed', 'dynamic') DEFAULT 'dynamic' COMMENT 'Fixed=scheduled date, Dynamic=from completion',
            interval_days INT(11) NOT NULL COMMENT 'Number of days between occurrences',
            lead_time_days INT(11) DEFAULT 0 COMMENT 'Days before due date to show task',
            is_enabled BOOLEAN DEFAULT TRUE,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY location_enabled_idx (location_id, is_enabled)
        ) $charset_collate;";

        // Task states table
        $table_states = $wpdb->prefix . 'hhmgt_task_states';
        $sql_states = "CREATE TABLE {$table_states} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            location_id BIGINT(20) UNSIGNED NOT NULL,
            state_name VARCHAR(100) NOT NULL,
            state_slug VARCHAR(100) NOT NULL,
            color_hex VARCHAR(7) DEFAULT '#6b7280',
            is_default BOOLEAN DEFAULT FALSE COMMENT 'Default states: Pending, Due, Overdue, Complete',
            is_complete_state BOOLEAN DEFAULT FALSE COMMENT 'Marks task as completed',
            sort_order INT(11) DEFAULT 0,
            is_enabled BOOLEAN DEFAULT TRUE,
            PRIMARY KEY (id),
            KEY location_slug_idx (location_id, state_slug),
            KEY enabled_idx (is_enabled)
        ) $charset_collate;";

        // Checklist templates table
        $table_templates = $wpdb->prefix . 'hhmgt_checklist_templates';
        $sql_templates = "CREATE TABLE {$table_templates} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            location_id BIGINT(20) UNSIGNED NOT NULL,
            template_name VARCHAR(255) NOT NULL,
            checklist_items TEXT NOT NULL COMMENT 'JSON array of checklist item objects',
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY location_idx (location_id)
        ) $charset_collate;";

        // Task notes table
        $table_notes = $wpdb->prefix . 'hhmgt_task_notes';
        $sql_notes = "CREATE TABLE {$table_notes} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            task_instance_id BIGINT(20) UNSIGNED NOT NULL,
            note_text TEXT NOT NULL,
            note_photos TEXT COMMENT 'JSON array of photo attachment IDs',
            carry_forward BOOLEAN DEFAULT TRUE COMMENT 'Copy to next recurring instance',
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY instance_idx (task_instance_id),
            KEY carry_forward_idx (carry_forward)
        ) $charset_collate;";

        // Create tables
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_tasks);
        dbDelta($sql_instances);
        dbDelta($sql_departments);
        dbDelta($sql_locations);
        dbDelta($sql_patterns);
        dbDelta($sql_states);
        dbDelta($sql_templates);
        dbDelta($sql_notes);

        // Update DB version
        update_option('hhmgt_db_version', HHMGT_VERSION);
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        // Initialize empty settings array
        if (!get_option('hhmgt_location_settings')) {
            update_option('hhmgt_location_settings', array());
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled cron
        $timestamp = wp_next_scheduled('hhmgt_process_recurring_tasks');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'hhmgt_process_recurring_tasks');
        }

        flush_rewrite_rules();
    }
}

/**
 * Initialize plugin
 */
function hhmgt_init() {
    return HotelHub_Management_Tasks::instance();
}

hhmgt_init();
