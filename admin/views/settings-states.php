<?php
/**
 * Task States Settings Tab
 *
 * @package HotelHub_Management_Tasks
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$states = isset($location_settings['task_states']) ? $location_settings['task_states'] : HHMGT_Settings::get_default_settings()['task_states'];
?>

<div class="hhmgt-settings-section">
    <h3><?php esc_html_e('Task States', 'hhmgt'); ?></h3>
    <p class="description">
        <?php esc_html_e('Customize task status states and colors. Default states (Pending, Due, Overdue, Complete) are recommended but can be customized.', 'hhmgt'); ?>
    </p>

    <div id="states-list" class="hhmgt-repeater-list">
        <?php foreach ($states as $index => $state): ?>
            <div class="hhmgt-repeater-item" data-index="<?php echo esc_attr($index); ?>">
                <div class="hhmgt-repeater-header">
                    <span class="hhmgt-drag-handle">
                        <span class="dashicons dashicons-menu"></span>
                    </span>
                    <span class="hhmgt-repeater-title">
                        <span class="hhmgt-state-badge"
                              style="background-color: <?php echo esc_attr($state['color_hex'] ?? '#6b7280'); ?>;">
                            <?php echo esc_html($state['state_name'] ?? __('Unnamed State', 'hhmgt')); ?>
                        </span>
                        <?php if ($state['is_default'] ?? false): ?>
                            <span class="hhmgt-default-badge"><?php esc_html_e('Default', 'hhmgt'); ?></span>
                        <?php endif; ?>
                        <?php if ($state['is_complete_state'] ?? false): ?>
                            <span class="hhmgt-complete-badge"><?php esc_html_e('Complete', 'hhmgt'); ?></span>
                        <?php endif; ?>
                    </span>
                    <button type="button" class="button hhmgt-repeater-toggle">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                </div>

                <div class="hhmgt-repeater-content" style="display: none;">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('State Name', 'hhmgt'); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       name="states[<?php echo esc_attr($index); ?>][state_name]"
                                       value="<?php echo esc_attr($state['state_name'] ?? ''); ?>"
                                       class="regular-text"
                                       required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Color', 'hhmgt'); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       name="states[<?php echo esc_attr($index); ?>][color_hex]"
                                       value="<?php echo esc_attr($state['color_hex'] ?? '#6b7280'); ?>"
                                       class="hhmgt-color-picker">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Properties', 'hhmgt'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox"
                                               name="states[<?php echo esc_attr($index); ?>][is_default]"
                                               value="1"
                                               <?php checked($state['is_default'] ?? false, true); ?>
                                               <?php disabled($state['is_default'] ?? false, true); ?>>
                                        <?php esc_html_e('Default state (cannot be changed)', 'hhmgt'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox"
                                               name="states[<?php echo esc_attr($index); ?>][is_complete_state]"
                                               value="1"
                                               <?php checked($state['is_complete_state'] ?? false, true); ?>>
                                        <?php esc_html_e('Marks task as completed', 'hhmgt'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox"
                                               name="states[<?php echo esc_attr($index); ?>][is_enabled]"
                                               value="1"
                                               <?php checked($state['is_enabled'] ?? true, true); ?>>
                                        <?php esc_html_e('Show in frontend', 'hhmgt'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox"
                                               name="states[<?php echo esc_attr($index); ?>][checklist_started_state]"
                                               value="1"
                                               class="hhmgt-checklist-started-checkbox"
                                               <?php checked($state['checklist_started_state'] ?? false, true); ?>
                                               <?php disabled($state['is_complete_state'] ?? false, true); ?>>
                                        <?php esc_html_e('Auto-update to this state when checklist started', 'hhmgt'); ?>
                                        <span class="description" style="display: block; margin-top: 5px;">
                                            <?php esc_html_e('Only one state can have this option enabled. Cannot be used with completed states.', 'hhmgt'); ?>
                                        </span>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>

                    <input type="hidden"
                           name="states[<?php echo esc_attr($index); ?>][sort_order]"
                           value="<?php echo esc_attr($state['sort_order'] ?? $index); ?>"
                           class="hhmgt-sort-order">

                    <?php if (!($state['is_default'] ?? false)): ?>
                        <div class="hhmgt-repeater-actions">
                            <button type="button" class="button button-secondary hhmgt-remove-item">
                                <span class="dashicons dashicons-trash"></span>
                                <?php esc_html_e('Remove State', 'hhmgt'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <button type="button" id="add-state" class="button button-secondary">
        <span class="dashicons dashicons-plus-alt"></span>
        <?php esc_html_e('Add Custom State', 'hhmgt'); ?>
    </button>

    <p class="description" style="margin-top: 15px;">
        <strong><?php esc_html_e('Note:', 'hhmgt'); ?></strong>
        <?php esc_html_e('Default states (Pending, Due, Overdue, Complete) are automatically managed by the system and cannot be removed.', 'hhmgt'); ?>
    </p>
</div>

<!-- State Template -->
<script type="text/template" id="state-template">
    <div class="hhmgt-repeater-item" data-index="{{index}}">
        <div class="hhmgt-repeater-header">
            <span class="hhmgt-drag-handle"><span class="dashicons dashicons-menu"></span></span>
            <span class="hhmgt-repeater-title">
                <span class="hhmgt-state-badge" style="background-color: #6b7280;">
                    <?php esc_html_e('New State', 'hhmgt'); ?>
                </span>
            </span>
            <button type="button" class="button hhmgt-repeater-toggle">
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </button>
        </div>
        <div class="hhmgt-repeater-content">
            <table class="form-table">
                <tr>
                    <th scope="row"><label><?php esc_html_e('State Name', 'hhmgt'); ?></label></th>
                    <td>
                        <input type="text" name="states[{{index}}][state_name]" value="" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label><?php esc_html_e('Color', 'hhmgt'); ?></label></th>
                    <td>
                        <input type="text" name="states[{{index}}][color_hex]" value="#6b7280" class="hhmgt-color-picker">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label><?php esc_html_e('Properties', 'hhmgt'); ?></label></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="states[{{index}}][is_complete_state]" value="1">
                                <?php esc_html_e('Marks task as completed', 'hhmgt'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="states[{{index}}][is_enabled]" value="1" checked>
                                <?php esc_html_e('Show in frontend', 'hhmgt'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="states[{{index}}][checklist_started_state]" value="1" class="hhmgt-checklist-started-checkbox">
                                <?php esc_html_e('Auto-update to this state when checklist started', 'hhmgt'); ?>
                                <span class="description" style="display: block; margin-top: 5px;">
                                    <?php esc_html_e('Only one state can have this option enabled. Cannot be used with completed states.', 'hhmgt'); ?>
                                </span>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="states[{{index}}][sort_order]" value="{{index}}" class="hhmgt-sort-order">
            <div class="hhmgt-repeater-actions">
                <button type="button" class="button button-secondary hhmgt-remove-item">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Remove State', 'hhmgt'); ?>
                </button>
            </div>
        </div>
    </div>
</script>

<style>
.hhmgt-state-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    color: #fff;
    font-size: 13px;
    font-weight: 500;
}

.hhmgt-default-badge,
.hhmgt-complete-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    background: #e5e7eb;
    color: #374151;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
    margin-left: 8px;
}

.hhmgt-complete-badge {
    background: #d1fae5;
    color: #065f46;
}
</style>
