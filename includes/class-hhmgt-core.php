<?php
/**
 * Core Module Class
 *
 * Handles module registration, permissions, and asset loading
 *
 * @package HotelHub_Management_Tasks
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class HHMGT_Core {
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
        $this->init_components();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // ✅ CORRECT: Register with Hotel Hub App (ACTION, using OBJECT method)
        add_action('hha_register_modules', array($this, 'register_module'));

        // ✅ CORRECT: Register permissions (ACTION, using OBJECT method)
        add_action('wfa_register_permissions', array($this, 'register_permissions'));

        // Admin menu
        add_action('admin_menu', array($this, 'register_admin_menu'));

        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Initialize components
     */
    private function init_components() {
        HHMGT_Settings::instance();
        HHMGT_Display::instance();
        HHMGT_Ajax::instance();
        HHMGT_Heartbeat::instance();
        HHMGT_Scheduler::instance();
        HHMGT_Tasks_Admin::instance();
    }

    /**
     * ✅ CORRECT: Register module with Hotel Hub App
     *
     * CRITICAL: Use $modules_manager->register_module($this)
     * NOT: $modules[$key] = array(...)
     *
     * @param HHA_Modules $modules_manager Hotel Hub modules manager object
     */
    public function register_module($modules_manager) {
        $modules_manager->register_module($this);
    }

    /**
     * ✅ REQUIRED: Get module configuration
     *
     * Called by HHA_Modules during registration
     *
     * @return array Module configuration
     */
    public function get_config() {
        return array(
            'id'             => 'management_tasks',
            'name'           => __('Tasks', 'hhmgt'),
            'description'    => __('Recurring task management with flexible scheduling', 'hhmgt'),
            'department'     => 'management',
            'icon'           => 'assignment_turned_in',
            'color'          => '#8b5cf6',
            'order'          => 10,
            'permissions'    => array(
                'hhmgt_tasks_access'
            ),
            'requires_hotel' => true,
            'settings_pages' => array(
                array(
                    'slug'       => 'hhmgt-settings',
                    'title'      => __('Tasks Settings', 'hhmgt'),
                    'menu_title' => __('Tasks', 'hhmgt'),
                    'callback'   => array('HHMGT_Settings', 'render')
                )
            )
        );
    }

    /**
     * ✅ REQUIRED: Render module content
     *
     * Called by HHA_Modules when module is displayed
     *
     * @param array $params Optional parameters from Hotel Hub App
     */
    public function render($params = array()) {
        HHMGT_Display::instance()->render($params);
    }

    /**
     * ✅ CORRECT: Register permissions with Workforce Authentication
     *
     * CRITICAL: Use $permissions_manager->register_permission(...)
     * NOT: $permissions[$key] = array(...)
     *
     * @param WFA_Permissions $permissions_manager Workforce Auth permissions manager object
     */
    public function register_permissions($permissions_manager) {
        // Main access permission
        $permissions_manager->register_permission(
            'hhmgt_tasks_access',
            __('Access Tasks Module', 'hhmgt'),
            __('View and manage tasks in the Tasks module', 'hhmgt'),
            'Management - Tasks'
        );

        // Future: Dynamic department permissions can be registered here
        // Example: Loop through departments and register {dept_slug}_tasks_access
    }

    /**
     * Register admin menu items
     */
    public function register_admin_menu() {
        // Main tasks menu
        add_menu_page(
            __('Tasks', 'hhmgt'),
            __('Tasks', 'hhmgt'),
            'manage_options',
            'hhmgt-tasks',
            array('HHMGT_Tasks_Admin', 'render_list'),
            'dashicons-clipboard',
            30
        );

        // Tasks list (same as main menu)
        add_submenu_page(
            'hhmgt-tasks',
            __('All Tasks', 'hhmgt'),
            __('All Tasks', 'hhmgt'),
            'manage_options',
            'hhmgt-tasks',
            array('HHMGT_Tasks_Admin', 'render_list')
        );

        // Create/Edit task (hidden from menu)
        add_submenu_page(
            null, // Hidden
            __('Edit Task', 'hhmgt'),
            __('Edit Task', 'hhmgt'),
            'manage_options',
            'hhmgt-edit-task',
            array('HHMGT_Tasks_Admin', 'render_edit')
        );

        // Settings submenu
        add_submenu_page(
            'hhmgt-tasks',
            __('Settings', 'hhmgt'),
            __('Settings', 'hhmgt'),
            'manage_options',
            'hhmgt-settings',
            array('HHMGT_Settings', 'render')
        );
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        // Only on Hotel Hub pages
        if (!is_page() && !is_singular()) {
            return;
        }

        // Check user permissions
        if (!$this->user_can_access()) {
            return;
        }

        // Material Symbols font (standard across modules)
        wp_enqueue_style(
            'material-symbols-outlined',
            'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200',
            array(),
            null
        );

        // CSS
        wp_enqueue_style(
            'hhmgt-styles',
            HHMGT_PLUGIN_URL . 'assets/css/hhmgt.css',
            array(),
            HHMGT_VERSION
        );

        wp_enqueue_style(
            'hhmgt-modal',
            HHMGT_PLUGIN_URL . 'assets/css/modal.css',
            array(),
            HHMGT_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'hhmgt-script',
            HHMGT_PLUGIN_URL . 'assets/js/hhmgt.js',
            array('jquery', 'heartbeat'),
            HHMGT_VERSION,
            true
        );

        wp_enqueue_script(
            'hhmgt-photo-gallery',
            HHMGT_PLUGIN_URL . 'assets/js/photo-gallery.js',
            array('jquery'),
            HHMGT_VERSION,
            true
        );

        // Localize script
        wp_localize_script('hhmgt-script', 'hhmgtData', array(
            'ajax_url'    => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('hhmgt_ajax_nonce'),
            'user_id'     => get_current_user_id(),
            'location_id' => hha_get_current_location(),
            'strings'     => array(
                'error'                => __('An error occurred', 'hhmgt'),
                'success'              => __('Success', 'hhmgt'),
                'loading'              => __('Loading...', 'hhmgt'),
                'confirmComplete'      => __('Are you sure you want to mark this task as complete?', 'hhmgt'),
                'photoRequired'        => __('Completion photo is required for this task', 'hhmgt'),
                'acknowledgmentRequired' => __('Please acknowledge the completion reminder', 'hhmgt')
            )
        ));
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only on plugin settings pages
        if (strpos($hook, 'hhmgt') === false) {
            return;
        }

        // Material Symbols font
        wp_enqueue_style(
            'material-symbols-outlined',
            'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200',
            array(),
            null
        );

        // WordPress color picker
        wp_enqueue_style('wp-color-picker');

        // WordPress media library (for photo uploads)
        wp_enqueue_media();

        // Admin CSS
        wp_enqueue_style(
            'hhmgt-admin',
            HHMGT_PLUGIN_URL . 'assets/css/admin.css',
            array('wp-color-picker'),
            HHMGT_VERSION
        );

        // Modal CSS (for future tasks modal)
        wp_enqueue_style(
            'hhmgt-modal',
            HHMGT_PLUGIN_URL . 'assets/css/modal.css',
            array(),
            HHMGT_VERSION
        );

        // Admin JavaScript
        wp_enqueue_script(
            'hhmgt-admin',
            HHMGT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker'),
            HHMGT_VERSION,
            true
        );

        wp_enqueue_script(
            'hhmgt-icon-picker',
            HHMGT_PLUGIN_URL . 'assets/js/icon-picker.js',
            array('jquery'),
            HHMGT_VERSION,
            true
        );

        // Localize admin script
        wp_localize_script('hhmgt-admin', 'hhmgtAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('hhmgt_admin_nonce'),
            'strings'  => array(
                'confirmDelete' => __('Are you sure you want to delete this?', 'hhmgt'),
                'error'         => __('An error occurred', 'hhmgt'),
                'success'       => __('Saved successfully', 'hhmgt')
            )
        ));
    }

    /**
     * Check if current user can access module
     *
     * @return bool
     */
    private function user_can_access() {
        if (function_exists('wfa_user_can')) {
            return wfa_user_can('hhmgt_tasks_access');
        }
        return current_user_can('edit_posts'); // Fallback
    }
}
