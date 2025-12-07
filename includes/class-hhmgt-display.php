<?php
/**
 * Display Class
 *
 * Handles frontend rendering of the tasks module
 *
 * @package HotelHub_Management_Tasks
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class HHMGT_Display {
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
        // No hooks needed for now
    }

    /**
     * Main render method called by Core
     *
     * @param array $params Parameters from Hotel Hub App
     */
    public function render($params = array()) {
        // Check permissions
        if (!$this->user_can_access()) {
            echo '<div class="hha-error">' . esc_html__('You do not have permission to access this module.', 'hhmgt') . '</div>';
            return;
        }

        // Get current location
        $location_id = hha_get_current_location();

        if (!$location_id) {
            echo '<div class="hha-error">' . esc_html__('No location selected.', 'hhmgt') . '</div>';
            return;
        }

        // Get location settings
        $settings = HHMGT_Settings::get_location_settings($location_id);

        if (!$settings['enabled']) {
            echo '<div class="hha-info">' . esc_html__('Tasks module is not enabled for this location.', 'hhmgt') . '</div>';
            return;
        }

        // Render the module UI
        $this->render_tasks_interface($location_id, $settings);
    }

    /**
     * Render tasks interface
     *
     * @param int $location_id Current location ID
     * @param array $settings Location settings
     */
    private function render_tasks_interface($location_id, $settings) {
        ?>
        <div class="hhmgt-container" data-location="<?php echo esc_attr($location_id); ?>">
            <!-- Header -->
            <div class="hhmgt-header">
                <h1><?php esc_html_e('Tasks', 'hhmgt'); ?></h1>
            </div>

            <!-- Filters -->
            <div class="hhmgt-filters-wrapper">
                <div class="hhmgt-filters-header" id="filters-toggle">
                    <span class="material-symbols-outlined">filter_list</span>
                    <span><?php esc_html_e('Filters & Grouping', 'hhmgt'); ?></span>
                    <span class="material-symbols-outlined hhmgt-toggle-icon">expand_more</span>
                </div>
                <div class="hhmgt-filters-content" id="filters-content">
                    <div class="hhmgt-filter-row">
                        <!-- Department filter -->
                        <div class="hhmgt-filter-group">
                            <label><?php esc_html_e('Department', 'hhmgt'); ?></label>
                            <div class="hhmgt-multiselect" data-filter="department">
                                <button type="button" class="hhmgt-multiselect-button">
                                    <span class="hhmgt-multiselect-label"><?php esc_html_e('All Departments', 'hhmgt'); ?></span>
                                    <span class="material-symbols-outlined">expand_more</span>
                                </button>
                                <div class="hhmgt-multiselect-dropdown">
                                    <div class="hhmgt-multiselect-actions">
                                        <button type="button" class="hhmgt-multiselect-action" data-action="select-all"><?php esc_html_e('Select All', 'hhmgt'); ?></button>
                                        <button type="button" class="hhmgt-multiselect-action" data-action="clear-all"><?php esc_html_e('Clear All', 'hhmgt'); ?></button>
                                    </div>
                                    <div class="hhmgt-multiselect-options">
                                        <?php if (!empty($settings['departments'])): ?>
                                            <?php foreach ($settings['departments'] as $dept): ?>
                                                <?php if ($dept['is_enabled']): ?>
                                                    <label class="hhmgt-multiselect-option">
                                                        <input type="checkbox" value="<?php echo esc_attr($dept['dept_slug']); ?>">
                                                        <span><?php echo esc_html($dept['dept_name']); ?></span>
                                                    </label>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status filter -->
                        <div class="hhmgt-filter-group">
                            <label><?php esc_html_e('Status', 'hhmgt'); ?></label>
                            <div class="hhmgt-multiselect" data-filter="status">
                                <button type="button" class="hhmgt-multiselect-button">
                                    <span class="hhmgt-multiselect-label"><?php esc_html_e('All Statuses', 'hhmgt'); ?></span>
                                    <span class="material-symbols-outlined">expand_more</span>
                                </button>
                                <div class="hhmgt-multiselect-dropdown">
                                    <div class="hhmgt-multiselect-actions">
                                                        <button type="button" class="hhmgt-multiselect-action" data-action="select-all"><?php esc_html_e('Select All', 'hhmgt'); ?></button>
                                        <button type="button" class="hhmgt-multiselect-action" data-action="clear-all"><?php esc_html_e('Clear All', 'hhmgt'); ?></button>
                                    </div>
                                    <div class="hhmgt-multiselect-options">
                                        <?php if (!empty($settings['task_states'])): ?>
                                            <?php foreach ($settings['task_states'] as $state): ?>
                                                <?php if ($state['is_enabled']): ?>
                                                    <label class="hhmgt-multiselect-option">
                                                        <input type="checkbox" value="<?php echo esc_attr($state['state_slug']); ?>">
                                                        <span><?php echo esc_html($state['state_name']); ?></span>
                                                    </label>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Location Type filter -->
                        <div class="hhmgt-filter-group">
                            <label><?php esc_html_e('Location Type', 'hhmgt'); ?></label>
                            <div class="hhmgt-multiselect" data-filter="location_type">
                                <button type="button" class="hhmgt-multiselect-button">
                                    <span class="hhmgt-multiselect-label"><?php esc_html_e('All Types', 'hhmgt'); ?></span>
                                    <span class="material-symbols-outlined">expand_more</span>
                                </button>
                                <div class="hhmgt-multiselect-dropdown">
                                    <div class="hhmgt-multiselect-actions">
                                        <button type="button" class="hhmgt-multiselect-action" data-action="select-all"><?php esc_html_e('Select All', 'hhmgt'); ?></button>
                                        <button type="button" class="hhmgt-multiselect-action" data-action="clear-all"><?php esc_html_e('Clear All', 'hhmgt'); ?></button>
                                    </div>
                                    <div class="hhmgt-multiselect-options">
                                        <!-- Populated dynamically via AJAX -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Location filter -->
                        <div class="hhmgt-filter-group">
                            <label><?php esc_html_e('Location', 'hhmgt'); ?></label>
                            <div class="hhmgt-multiselect" data-filter="location">
                                <button type="button" class="hhmgt-multiselect-button">
                                    <span class="hhmgt-multiselect-label"><?php esc_html_e('All Locations', 'hhmgt'); ?></span>
                                    <span class="material-symbols-outlined">expand_more</span>
                                </button>
                                <div class="hhmgt-multiselect-dropdown">
                                    <div class="hhmgt-multiselect-actions">
                                        <button type="button" class="hhmgt-multiselect-action" data-action="select-all"><?php esc_html_e('Select All', 'hhmgt'); ?></button>
                                        <button type="button" class="hhmgt-multiselect-action" data-action="clear-all"><?php esc_html_e('Clear All', 'hhmgt'); ?></button>
                                    </div>
                                    <div class="hhmgt-multiselect-options">
                                        <!-- Populated dynamically via AJAX based on type -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="hhmgt-filter-row">
                        <!-- Include future tasks toggle -->
                        <div class="hhmgt-filter-group">
                            <label class="hhmgt-checkbox-label">
                                <input type="checkbox" id="filter-include-future" class="hhmgt-filter-checkbox" checked>
                                <span><?php esc_html_e('Include Future Tasks', 'hhmgt'); ?></span>
                            </label>
                        </div>

                        <!-- Show completed toggle -->
                        <div class="hhmgt-filter-group">
                            <label class="hhmgt-checkbox-label">
                                <input type="checkbox" id="filter-show-completed" class="hhmgt-filter-checkbox">
                                <span><?php esc_html_e('Show Completed', 'hhmgt'); ?></span>
                            </label>
                        </div>

                        <!-- Group by -->
                        <div class="hhmgt-filter-group">
                            <label for="filter-group-by"><?php esc_html_e('Group By', 'hhmgt'); ?></label>
                            <select id="filter-group-by" class="hhmgt-filter-select">
                                <option value=""><?php esc_html_e('None', 'hhmgt'); ?></option>
                                <option value="location"><?php esc_html_e('Location', 'hhmgt'); ?></option>
                                <option value="department"><?php esc_html_e('Department', 'hhmgt'); ?></option>
                                <option value="status"><?php esc_html_e('Status', 'hhmgt'); ?></option>
                            </select>
                        </div>

                        <!-- Search -->
                        <div class="hhmgt-filter-group hhmgt-filter-search">
                            <label for="filter-search"><?php esc_html_e('Search', 'hhmgt'); ?></label>
                            <input type="text" id="filter-search" class="hhmgt-filter-input"
                                   placeholder="<?php esc_attr_e('Search tasks...', 'hhmgt'); ?>">
                        </div>

                        <!-- Apply filters button -->
                        <div class="hhmgt-filter-group">
                            <button id="apply-filters" class="hhmgt-btn hhmgt-btn-primary">
                                <span class="material-symbols-outlined">filter_list</span>
                                <?php esc_html_e('Apply Filters', 'hhmgt'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tasks list container -->
            <div class="hhmgt-tasks-list" id="tasks-list">
                <div class="hhmgt-loading">
                    <span class="material-symbols-outlined hhmgt-loading-icon">sync</span>
                    <p><?php esc_html_e('Loading tasks...', 'hhmgt'); ?></p>
                </div>
            </div>

            <!-- Task detail modal -->
            <div id="task-modal" class="hhmgt-modal" style="display: none;">
                <div class="hhmgt-modal-overlay"></div>
                <div class="hhmgt-modal-content">
                    <!-- Modal content loaded dynamically -->
                </div>
            </div>

            <!-- Completion confirmation modal -->
            <div id="completion-modal" class="hhmgt-modal hhmgt-modal-small" style="display: none;">
                <div class="hhmgt-modal-overlay"></div>
                <div class="hhmgt-modal-content">
                    <!-- Completion form loaded dynamically -->
                </div>
            </div>
        </div>
        <?php
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
        return current_user_can('edit_posts'); // Fallback
    }
}
