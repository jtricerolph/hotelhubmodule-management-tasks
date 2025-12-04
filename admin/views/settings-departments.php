<?php
/**
 * Departments Settings Tab
 *
 * @package HotelHub_Management_Tasks
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$departments = isset($location_settings['departments']) ? $location_settings['departments'] : array();
?>

<div class="hhmgt-settings-section">
    <h3><?php esc_html_e('Departments', 'hhmgt'); ?></h3>
    <p class="description">
        <?php esc_html_e('Define custom departments for organizing tasks. Each department can have its own icon and color.', 'hhmgt'); ?>
    </p>

    <div id="departments-list" class="hhmgt-repeater-list">
        <?php if (!empty($departments)): ?>
            <?php foreach ($departments as $index => $dept): ?>
                <div class="hhmgt-repeater-item" data-index="<?php echo esc_attr($index); ?>">
                    <div class="hhmgt-repeater-header">
                        <span class="hhmgt-drag-handle">
                            <span class="dashicons dashicons-menu"></span>
                        </span>
                        <span class="hhmgt-repeater-title">
                            <span class="material-symbols-outlined hhmgt-dept-icon-preview"
                                  style="color: <?php echo esc_attr($dept['color_hex'] ?? '#8b5cf6'); ?>;">
                                <?php echo esc_html($dept['icon_name'] ?? 'assignment_turned_in'); ?>
                            </span>
                            <strong><?php echo esc_html($dept['dept_name'] ?? __('Unnamed Department', 'hhmgt')); ?></strong>
                        </span>
                        <button type="button" class="button hhmgt-repeater-toggle">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                    </div>

                    <div class="hhmgt-repeater-content" style="display: none;">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Department Name', 'hhmgt'); ?></label>
                                </th>
                                <td>
                                    <input type="text"
                                           name="departments[<?php echo esc_attr($index); ?>][dept_name]"
                                           value="<?php echo esc_attr($dept['dept_name'] ?? ''); ?>"
                                           class="regular-text hhmgt-dept-name"
                                           required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Icon', 'hhmgt'); ?></label>
                                </th>
                                <td>
                                    <div class="hhmgt-icon-picker-wrapper">
                                        <button type="button" class="button hhmgt-icon-picker-button">
                                            <span class="material-symbols-outlined hhmgt-icon-preview">
                                                <?php echo esc_html($dept['icon_name'] ?? 'assignment_turned_in'); ?>
                                            </span>
                                            <span><?php esc_html_e('Choose Icon', 'hhmgt'); ?></span>
                                        </button>
                                        <input type="hidden"
                                               name="departments[<?php echo esc_attr($index); ?>][icon_name]"
                                               value="<?php echo esc_attr($dept['icon_name'] ?? 'assignment_turned_in'); ?>"
                                               class="hhmgt-icon-value">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Color', 'hhmgt'); ?></label>
                                </th>
                                <td>
                                    <input type="text"
                                           name="departments[<?php echo esc_attr($index); ?>][color_hex]"
                                           value="<?php echo esc_attr($dept['color_hex'] ?? '#8b5cf6'); ?>"
                                           class="hhmgt-color-picker">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Description', 'hhmgt'); ?></label>
                                </th>
                                <td>
                                    <textarea name="departments[<?php echo esc_attr($index); ?>][description]"
                                              rows="3"
                                              class="large-text"><?php echo esc_textarea($dept['description'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Enabled', 'hhmgt'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                               name="departments[<?php echo esc_attr($index); ?>][is_enabled]"
                                               value="1"
                                               <?php checked($dept['is_enabled'] ?? true, true); ?>>
                                        <?php esc_html_e('Enable this department', 'hhmgt'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <input type="hidden"
                               name="departments[<?php echo esc_attr($index); ?>][sort_order]"
                               value="<?php echo esc_attr($index); ?>"
                               class="hhmgt-sort-order">

                        <div class="hhmgt-repeater-actions">
                            <button type="button" class="button button-secondary hhmgt-remove-item">
                                <span class="dashicons dashicons-trash"></span>
                                <?php esc_html_e('Remove Department', 'hhmgt'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <button type="button" id="add-department" class="button button-secondary">
        <span class="dashicons dashicons-plus-alt"></span>
        <?php esc_html_e('Add Department', 'hhmgt'); ?>
    </button>
</div>

<!-- Department Template (hidden) -->
<script type="text/template" id="department-template">
    <div class="hhmgt-repeater-item" data-index="{{index}}">
        <div class="hhmgt-repeater-header">
            <span class="hhmgt-drag-handle">
                <span class="dashicons dashicons-menu"></span>
            </span>
            <span class="hhmgt-repeater-title">
                <span class="material-symbols-outlined hhmgt-dept-icon-preview" style="color: #8b5cf6;">
                    assignment_turned_in
                </span>
                <strong><?php esc_html_e('New Department', 'hhmgt'); ?></strong>
            </span>
            <button type="button" class="button hhmgt-repeater-toggle">
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </button>
        </div>

        <div class="hhmgt-repeater-content">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Department Name', 'hhmgt'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               name="departments[{{index}}][dept_name]"
                               value=""
                               class="regular-text hhmgt-dept-name"
                               required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Icon', 'hhmgt'); ?></label>
                    </th>
                    <td>
                        <div class="hhmgt-icon-picker-wrapper">
                            <button type="button" class="button hhmgt-icon-picker-button">
                                <span class="material-symbols-outlined hhmgt-icon-preview">
                                    assignment_turned_in
                                </span>
                                <span><?php esc_html_e('Choose Icon', 'hhmgt'); ?></span>
                            </button>
                            <input type="hidden"
                                   name="departments[{{index}}][icon_name]"
                                   value="assignment_turned_in"
                                   class="hhmgt-icon-value">
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Color', 'hhmgt'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               name="departments[{{index}}][color_hex]"
                               value="#8b5cf6"
                               class="hhmgt-color-picker">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Description', 'hhmgt'); ?></label>
                    </th>
                    <td>
                        <textarea name="departments[{{index}}][description]"
                                  rows="3"
                                  class="large-text"></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Enabled', 'hhmgt'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="departments[{{index}}][is_enabled]"
                                   value="1"
                                   checked>
                            <?php esc_html_e('Enable this department', 'hhmgt'); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <input type="hidden"
                   name="departments[{{index}}][sort_order]"
                   value="{{index}}"
                   class="hhmgt-sort-order">

            <div class="hhmgt-repeater-actions">
                <button type="button" class="button button-secondary hhmgt-remove-item">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Remove Department', 'hhmgt'); ?>
                </button>
            </div>
        </div>
    </div>
</script>

<style>
.hhmgt-repeater-list {
    margin: 15px 0;
}

.hhmgt-repeater-item {
    background: #fff;
    border: 1px solid #c3c4c7;
    margin-bottom: 10px;
    border-radius: 4px;
}

.hhmgt-repeater-header {
    display: flex;
    align-items: center;
    padding: 12px;
    background: #f6f7f7;
    border-bottom: 1px solid #c3c4c7;
    cursor: pointer;
}

.hhmgt-drag-handle {
    margin-right: 10px;
    color: #8c8f94;
    cursor: move;
}

.hhmgt-repeater-title {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
}

.hhmgt-dept-icon-preview {
    font-size: 20px;
}

.hhmgt-repeater-toggle {
    padding: 4px 8px;
    min-height: auto;
    line-height: 1;
}

.hhmgt-repeater-content {
    padding: 20px;
}

.hhmgt-repeater-actions {
    padding-top: 15px;
    border-top: 1px solid #e5e7eb;
    margin-top: 15px;
}

.hhmgt-icon-picker-wrapper {
    position: relative;
}

.hhmgt-icon-picker-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.hhmgt-icon-picker-button .hhmgt-icon-preview {
    font-size: 20px;
}
</style>
