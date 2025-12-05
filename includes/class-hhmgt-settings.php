<?php
/**
 * Settings Management Class
 *
 * Handles per-location settings storage and retrieval
 *
 * @package HotelHub_Management_Tasks
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class HHMGT_Settings {
    const OPTION_NAME = 'hhmgt_location_settings';

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
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_hhmgt_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_hhmgt_get_material_symbols', array($this, 'ajax_get_material_symbols'));
        add_action('wp_ajax_hhmgt_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_hhmgt_get_template', array($this, 'ajax_get_template'));
        add_action('wp_ajax_hhmgt_delete_template', array($this, 'ajax_delete_template'));
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('hhmgt_settings', self::OPTION_NAME);
    }

    /**
     * Render settings page
     */
    public static function render() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'hhmgt'));
        }

        // Get locations from Hotel Hub App
        $locations = self::get_locations();

        // Get current settings
        $settings = get_option(self::OPTION_NAME, array());

        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

        // Get current location
        $current_location_id = isset($_GET['location_id']) ? intval($_GET['location_id']) : 0;

        // If no location selected, use first location
        if (!$current_location_id && !empty($locations)) {
            $current_location_id = $locations[0]['id'];
        }

        // Get location settings
        $location_settings = isset($settings[$current_location_id]) ? $settings[$current_location_id] : self::get_default_settings();

        // Auto-sync existing data to database if not already synced
        self::instance()->maybe_sync_existing_data($current_location_id, $location_settings);

        // Load template
        include HHMGT_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Auto-sync existing options data to database tables if needed
     */
    private function maybe_sync_existing_data($location_id, $location_settings) {
        global $wpdb;

        // Check if departments exist in options but not in database
        if (!empty($location_settings['departments'])) {
            $dept_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}hhmgt_departments WHERE location_id = %d",
                $location_id
            ));
            if ($dept_count == 0) {
                $this->sync_departments($location_id, $location_settings['departments']);
            }
        }

        // Check if patterns exist in options but not in database
        if (!empty($location_settings['recurring_patterns'])) {
            $pattern_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}hhmgt_recurring_patterns WHERE location_id = %d",
                $location_id
            ));
            if ($pattern_count == 0) {
                $this->sync_patterns($location_id, $location_settings['recurring_patterns']);
            }
        }

        // Check if states exist in options but not in database
        if (!empty($location_settings['task_states'])) {
            $state_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}hhmgt_task_states WHERE location_id = %d",
                $location_id
            ));
            if ($state_count == 0) {
                $this->sync_states($location_id, $location_settings['task_states']);
            }
        }
    }

    /**
     * Get default settings for a location
     */
    public static function get_default_settings() {
        return array(
            'enabled' => false,
            'departments' => array(),
            'recurring_patterns' => array(),
            'task_states' => array(
                array(
                    'state_name' => __('Pending', 'hhmgt'),
                    'state_slug' => 'pending',
                    'color_hex' => '#6b7280',
                    'is_default' => true,
                    'is_complete_state' => false,
                    'is_enabled' => true,
                    'sort_order' => 0
                ),
                array(
                    'state_name' => __('Due', 'hhmgt'),
                    'state_slug' => 'due',
                    'color_hex' => '#f59e0b',
                    'is_default' => true,
                    'is_complete_state' => false,
                    'is_enabled' => true,
                    'sort_order' => 1
                ),
                array(
                    'state_name' => __('Overdue', 'hhmgt'),
                    'state_slug' => 'overdue',
                    'color_hex' => '#ef4444',
                    'is_default' => true,
                    'is_complete_state' => false,
                    'is_enabled' => true,
                    'sort_order' => 2
                ),
                array(
                    'state_name' => __('Complete', 'hhmgt'),
                    'state_slug' => 'complete',
                    'color_hex' => '#10b981',
                    'is_default' => true,
                    'is_complete_state' => true,
                    'is_enabled' => true,
                    'sort_order' => 3
                )
            )
        );
    }

    /**
     * Save settings
     */
    public function save_settings() {
        // Check nonce
        if (!isset($_POST['hhmgt_settings_nonce']) ||
            !wp_verify_nonce($_POST['hhmgt_settings_nonce'], 'hhmgt_save_settings')) {
            wp_die(__('Security check failed', 'hhmgt'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'hhmgt'));
        }

        $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
        $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : 'general';

        if (!$location_id) {
            wp_die(__('Invalid location', 'hhmgt'));
        }

        // Get all settings
        $all_settings = get_option(self::OPTION_NAME, array());

        // Get current location settings or defaults
        $location_settings = isset($all_settings[$location_id]) ? $all_settings[$location_id] : self::get_default_settings();

        // Update based on tab
        switch ($tab) {
            case 'general':
                $location_settings['enabled'] = isset($_POST['enabled']) ? true : false;
                break;

            case 'departments':
                $location_settings['departments'] = $this->process_departments_data($_POST);
                break;

            case 'locations':
                // Location hierarchy is stored in database, not options
                $this->save_location_hierarchy($location_id, $_POST);
                break;

            case 'patterns':
                $location_settings['recurring_patterns'] = $this->process_patterns_data($_POST);
                break;

            case 'states':
                $location_settings['task_states'] = $this->process_states_data($_POST);
                break;

            case 'templates':
                // Templates are stored in database, not options - handled via AJAX
                break;
        }

        // Save updated settings
        $all_settings[$location_id] = $location_settings;
        update_option(self::OPTION_NAME, $all_settings);

        // Sync to database tables (for efficient querying)
        $this->sync_to_database($location_id, $location_settings, $tab);

        // Redirect back
        wp_redirect(add_query_arg(
            array(
                'page' => 'hhmgt-settings',
                'tab' => $tab,
                'location_id' => $location_id,
                'updated' => 'true'
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Sync settings to database tables
     *
     * @param int $location_id Location ID
     * @param array $settings Location settings
     * @param string $tab Current tab
     */
    private function sync_to_database($location_id, $settings, $tab) {
        global $wpdb;

        switch ($tab) {
            case 'departments':
                $this->sync_departments($location_id, $settings['departments'] ?? array());
                break;

            case 'patterns':
                $this->sync_patterns($location_id, $settings['recurring_patterns'] ?? array());
                break;

            case 'states':
                $this->sync_states($location_id, $settings['task_states'] ?? array());
                break;
        }
    }

    /**
     * Sync departments to database
     */
    private function sync_departments($location_id, $departments) {
        global $wpdb;
        $table = $wpdb->prefix . 'hhmgt_departments';

        // Delete existing departments for this location
        $wpdb->delete($table, array('location_id' => $location_id), array('%d'));

        // Insert new departments
        foreach ($departments as $dept) {
            $wpdb->insert(
                $table,
                array(
                    'location_id' => $location_id,
                    'dept_name' => $dept['dept_name'],
                    'dept_slug' => $dept['dept_slug'],
                    'icon_name' => $dept['icon_name'],
                    'color_hex' => $dept['color_hex'],
                    'description' => $dept['description'] ?? '',
                    'is_enabled' => $dept['is_enabled'] ? 1 : 0,
                    'sort_order' => $dept['sort_order'],
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s')
            );
        }
    }

    /**
     * Sync recurring patterns to database
     */
    private function sync_patterns($location_id, $patterns) {
        global $wpdb;
        $table = $wpdb->prefix . 'hhmgt_recurring_patterns';

        // Delete existing patterns for this location
        $wpdb->delete($table, array('location_id' => $location_id), array('%d'));

        // Insert new patterns
        foreach ($patterns as $pattern) {
            $wpdb->insert(
                $table,
                array(
                    'location_id' => $location_id,
                    'pattern_name' => $pattern['pattern_name'],
                    'interval_type' => $pattern['interval_type'],
                    'interval_days' => $pattern['interval_days'],
                    'lead_time_days' => $pattern['lead_time_days'],
                    'is_enabled' => $pattern['is_enabled'] ? 1 : 0,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%d', '%d', '%d', '%s')
            );
        }
    }

    /**
     * Sync task states to database
     */
    private function sync_states($location_id, $states) {
        global $wpdb;
        $table = $wpdb->prefix . 'hhmgt_task_states';

        // Delete existing states for this location
        $wpdb->delete($table, array('location_id' => $location_id), array('%d'));

        // Insert new states
        foreach ($states as $state) {
            $wpdb->insert(
                $table,
                array(
                    'location_id' => $location_id,
                    'state_name' => $state['state_name'],
                    'state_slug' => $state['state_slug'],
                    'color_hex' => $state['color_hex'],
                    'is_default' => isset($state['is_default']) && $state['is_default'] ? 1 : 0,
                    'is_complete_state' => isset($state['is_complete_state']) && $state['is_complete_state'] ? 1 : 0,
                    'is_enabled' => $state['is_enabled'] ? 1 : 0,
                    'sort_order' => $state['sort_order']
                ),
                array('%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d')
            );
        }
    }

    /**
     * Process departments data from POST
     */
    private function process_departments_data($post_data) {
        $departments = array();

        if (isset($post_data['departments']) && is_array($post_data['departments'])) {
            foreach ($post_data['departments'] as $dept_data) {
                $departments[] = array(
                    'dept_name' => sanitize_text_field($dept_data['dept_name']),
                    'dept_slug' => sanitize_title($dept_data['dept_name']),
                    'icon_name' => sanitize_text_field($dept_data['icon_name']),
                    'color_hex' => sanitize_hex_color($dept_data['color_hex']),
                    'description' => sanitize_textarea_field($dept_data['description'] ?? ''),
                    'is_enabled' => isset($dept_data['is_enabled']) ? true : false,
                    'sort_order' => intval($dept_data['sort_order'] ?? 0)
                );
            }
        }

        return $departments;
    }

    /**
     * Process recurring patterns data from POST
     */
    private function process_patterns_data($post_data) {
        $patterns = array();

        if (isset($post_data['patterns']) && is_array($post_data['patterns'])) {
            foreach ($post_data['patterns'] as $pattern_data) {
                $patterns[] = array(
                    'pattern_name' => sanitize_text_field($pattern_data['pattern_name']),
                    'interval_type' => in_array($pattern_data['interval_type'], array('fixed', 'dynamic')) ? $pattern_data['interval_type'] : 'dynamic',
                    'interval_days' => intval($pattern_data['interval_days']),
                    'lead_time_days' => intval($pattern_data['lead_time_days'] ?? 0),
                    'is_enabled' => isset($pattern_data['is_enabled']) ? true : false
                );
            }
        }

        return $patterns;
    }

    /**
     * Process task states data from POST
     */
    private function process_states_data($post_data) {
        $states = array();

        if (isset($post_data['states']) && is_array($post_data['states'])) {
            foreach ($post_data['states'] as $state_data) {
                $states[] = array(
                    'state_name' => sanitize_text_field($state_data['state_name']),
                    'state_slug' => sanitize_title($state_data['state_name']),
                    'color_hex' => sanitize_hex_color($state_data['color_hex']),
                    'is_default' => isset($state_data['is_default']) ? true : false,
                    'is_complete_state' => isset($state_data['is_complete_state']) ? true : false,
                    'is_enabled' => isset($state_data['is_enabled']) ? true : false,
                    'sort_order' => intval($state_data['sort_order'] ?? 0)
                );
            }
        }

        return $states;
    }

    /**
     * Save location hierarchy to database
     */
    private function save_location_hierarchy($location_id, $post_data) {
        global $wpdb;
        $table = $wpdb->prefix . 'hhmgt_location_hierarchy';

        // First pass: Create mapping of old IDs to new IDs
        $id_mapping = array();

        if (isset($post_data['locations']) && is_array($post_data['locations'])) {
            // Delete existing locations for this location
            $wpdb->delete($table, array('location_id' => $location_id), array('%d'));

            // First pass: Insert all locations and build ID mapping
            foreach ($post_data['locations'] as $loc_data) {
                // Skip empty locations
                if (empty($loc_data['location_name'])) {
                    continue;
                }

                $old_id = !empty($loc_data['id']) && $loc_data['id'] !== 'new' ? $loc_data['id'] : null;

                // Build full path
                $full_path = $loc_data['location_name'];
                if (!empty($loc_data['location_type'])) {
                    $full_path .= ' (' . $loc_data['location_type'] . ')';
                }

                // Insert with parent_id as null for now
                $wpdb->insert(
                    $table,
                    array(
                        'location_id' => $location_id,
                        'parent_id' => null, // Will update in second pass
                        'hierarchy_level' => intval($loc_data['hierarchy_level'] ?? 0),
                        'location_name' => sanitize_text_field($loc_data['location_name']),
                        'location_type' => sanitize_text_field($loc_data['location_type'] ?? ''),
                        'full_path' => $full_path,
                        'sort_order' => intval($loc_data['sort_order'] ?? 0),
                        'is_enabled' => isset($loc_data['is_enabled']) ? 1 : 0,
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s')
                );

                $new_id = $wpdb->insert_id;

                // Map old ID to new ID
                if ($old_id !== null) {
                    $id_mapping[$old_id] = $new_id;
                }

                // Also store the parent_id for second pass
                $id_mapping['_parent_' . $new_id] = !empty($loc_data['parent_id']) && $loc_data['parent_id'] !== 'new' ? $loc_data['parent_id'] : null;
            }

            // Second pass: Update parent_id relationships
            foreach ($id_mapping as $key => $value) {
                if (strpos($key, '_parent_') === 0) {
                    $new_id = intval(str_replace('_parent_', '', $key));
                    $old_parent_id = $value;

                    if ($old_parent_id && isset($id_mapping[$old_parent_id])) {
                        $new_parent_id = $id_mapping[$old_parent_id];

                        $wpdb->update(
                            $table,
                            array('parent_id' => $new_parent_id),
                            array('id' => $new_id),
                            array('%d'),
                            array('%d')
                        );
                    }
                }
            }
        }
    }

    /**
     * AJAX: Save checklist template
     */
    public function ajax_save_template() {
        check_ajax_referer('hhmgt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'hhmgt')));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'hhmgt_checklist_templates';

        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $template_name = sanitize_text_field($_POST['template_name']);
        $checklist_items = wp_unslash($_POST['checklist_items']); // Don't sanitize JSON - validate it instead
        $location_id = intval($_POST['location_id']);

        if (empty($template_name)) {
            wp_send_json_error(array('message' => __('Template name is required', 'hhmgt')));
        }

        // Validate that checklist_items is valid JSON
        $items_array = json_decode($checklist_items, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => __('Invalid checklist items format', 'hhmgt')));
        }

        $data = array(
            'location_id' => $location_id,
            'template_name' => $template_name,
            'checklist_items' => $checklist_items,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );

        if ($template_id) {
            // Update existing template
            unset($data['created_by']);
            unset($data['created_at']);
            $wpdb->update($table, $data, array('id' => $template_id), null, array('%d'));
        } else {
            // Create new template
            $wpdb->insert($table, $data);
        }

        wp_send_json_success(array('message' => __('Template saved successfully', 'hhmgt')));
    }

    /**
     * AJAX: Get template
     */
    public function ajax_get_template() {
        check_ajax_referer('hhmgt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'hhmgt')));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'hhmgt_checklist_templates';
        $template_id = intval($_POST['template_id']);

        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $template_id
        ));

        if (!$template) {
            wp_send_json_error(array('message' => __('Template not found', 'hhmgt')));
        }

        // Decode checklist_items so it's sent as an array, not a JSON string
        $template->checklist_items = json_decode($template->checklist_items, true);

        wp_send_json_success($template);
    }

    /**
     * AJAX: Delete template
     */
    public function ajax_delete_template() {
        check_ajax_referer('hhmgt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'hhmgt')));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'hhmgt_checklist_templates';
        $template_id = intval($_POST['template_id']);
        $location_id = intval($_POST['location_id']);

        $wpdb->delete($table, array('id' => $template_id, 'location_id' => $location_id), array('%d', '%d'));

        wp_send_json_success(array('message' => __('Template deleted successfully', 'hhmgt')));
    }

    /**
     * Get locations from Hotel Hub App
     */
    public static function get_locations() {
        if (!function_exists('hha')) {
            return array();
        }

        $hotels = hha()->hotels->get_active();

        $locations = array();
        foreach ($hotels as $hotel) {
            $locations[] = array(
                'id'   => $hotel->id,
                'name' => $hotel->name
            );
        }

        return $locations;
    }

    /**
     * Get settings for a specific location
     */
    public static function get_location_settings($location_id) {
        $all_settings = get_option(self::OPTION_NAME, array());

        if (isset($all_settings[$location_id])) {
            return $all_settings[$location_id];
        }

        // Return defaults
        return self::get_default_settings();
    }

    /**
     * AJAX: Get Material Symbols icon list
     */
    public function ajax_get_material_symbols() {
        check_ajax_referer('hhmgt_admin_nonce', 'nonce');

        // Load icons from data file
        $icons_file = HHMGT_PLUGIN_DIR . 'assets/data/material-symbols.json';

        if (file_exists($icons_file)) {
            $icons_json = file_get_contents($icons_file);
            wp_send_json_success(json_decode($icons_json, true));
        } else {
            // Return common icons as fallback
            wp_send_json_success($this->get_common_icons());
        }
    }

    /**
     * Get common Material Symbols icons
     */
    private function get_common_icons() {
        return array(
            'assignment_turned_in', 'fact_check', 'task_alt', 'check_circle', 'schedule',
            'cleaning_services', 'dry_cleaning', 'local_laundry_service', 'soap',
            'bedtime', 'king_bed', 'single_bed', 'bed', 'hotel',
            'restaurant', 'coffee', 'dining', 'kitchen',
            'plumbing', 'electrical_services', 'hvac', 'light', 'air',
            'pool', 'fitness_center', 'spa', 'hot_tub',
            'wifi', 'tv', 'phone', 'laptop',
            'fire_extinguisher', 'emergency', 'pest_control', 'security',
            'build', 'construction', 'handyman', 'home_repair_service',
            'description', 'folder', 'event', 'today',
            'person', 'group', 'badge', 'admin_panel_settings'
        );
    }
}
