<?php
/**
 * Recurring Patterns Settings Tab
 *
 * @package HotelHub_Management_Tasks
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$patterns = isset($location_settings['recurring_patterns']) ? $location_settings['recurring_patterns'] : array();
?>

<div class="hhmgt-settings-section">
    <h3><?php esc_html_e('Recurring Patterns', 'hhmgt'); ?></h3>
    <p class="description">
        <?php esc_html_e('Define recurring patterns for tasks. Fixed patterns generate on schedule, while dynamic patterns generate from completion date.', 'hhmgt'); ?>
    </p>

    <div id="patterns-list" class="hhmgt-repeater-list">
        <?php if (!empty($patterns)): ?>
            <?php foreach ($patterns as $index => $pattern): ?>
                <div class="hhmgt-repeater-item" data-index="<?php echo esc_attr($index); ?>">
                    <div class="hhmgt-repeater-header">
                        <span class="hhmgt-drag-handle">
                            <span class="dashicons dashicons-menu"></span>
                        </span>
                        <span class="hhmgt-repeater-title">
                            <strong><?php echo esc_html($pattern['pattern_name'] ?? __('Unnamed Pattern', 'hhmgt')); ?></strong>
                            <span class="hhmgt-pattern-meta">
                                (<?php echo esc_html(ucfirst($pattern['interval_type'] ?? 'dynamic')); ?> -
                                <?php echo esc_html($pattern['interval_days'] ?? 7); ?> <?php esc_html_e('days', 'hhmgt'); ?>)
                            </span>
                        </span>
                        <button type="button" class="button hhmgt-repeater-toggle">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                    </div>

                    <div class="hhmgt-repeater-content" style="display: none;">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Pattern Name', 'hhmgt'); ?></label>
                                </th>
                                <td>
                                    <input type="text"
                                           name="patterns[<?php echo esc_attr($index); ?>][pattern_name]"
                                           value="<?php echo esc_attr($pattern['pattern_name'] ?? ''); ?>"
                                           class="regular-text"
                                           required>
                                    <p class="description">
                                        <?php esc_html_e('e.g., "Weekly Deep Clean", "Monthly Inspection"', 'hhmgt'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Interval Type', 'hhmgt'); ?></label>
                                </th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="radio"
                                                   name="patterns[<?php echo esc_attr($index); ?>][interval_type]"
                                                   value="fixed"
                                                   <?php checked($pattern['interval_type'] ?? 'dynamic', 'fixed'); ?>>
                                            <strong><?php esc_html_e('Fixed', 'hhmgt'); ?></strong> -
                                            <?php esc_html_e('Generate on schedule (e.g., every 7 days from original date)', 'hhmgt'); ?>
                                        </label>
                                        <br>
                                        <label>
                                            <input type="radio"
                                                   name="patterns[<?php echo esc_attr($index); ?>][interval_type]"
                                                   value="dynamic"
                                                   <?php checked($pattern['interval_type'] ?? 'dynamic', 'dynamic'); ?>>
                                            <strong><?php esc_html_e('Dynamic', 'hhmgt'); ?></strong> -
                                            <?php esc_html_e('Generate from completion (e.g., 7 days after task is done)', 'hhmgt'); ?>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Interval (Days)', 'hhmgt'); ?></label>
                                </th>
                                <td>
                                    <input type="number"
                                           name="patterns[<?php echo esc_attr($index); ?>][interval_days]"
                                           value="<?php echo esc_attr($pattern['interval_days'] ?? 7); ?>"
                                           min="1"
                                           max="365"
                                           class="small-text"
                                           required>
                                    <?php esc_html_e('days', 'hhmgt'); ?>
                                    <p class="description">
                                        <?php esc_html_e('Number of days between task occurrences', 'hhmgt'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Lead Time (Days)', 'hhmgt'); ?></label>
                                </th>
                                <td>
                                    <input type="number"
                                           name="patterns[<?php echo esc_attr($index); ?>][lead_time_days]"
                                           value="<?php echo esc_attr($pattern['lead_time_days'] ?? 0); ?>"
                                           min="0"
                                           max="90"
                                           class="small-text">
                                    <?php esc_html_e('days', 'hhmgt'); ?>
                                    <p class="description">
                                        <?php esc_html_e('Show task this many days before due date (for planning/scheduling)', 'hhmgt'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Enabled', 'hhmgt'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                               name="patterns[<?php echo esc_attr($index); ?>][is_enabled]"
                                               value="1"
                                               <?php checked($pattern['is_enabled'] ?? true, true); ?>>
                                        <?php esc_html_e('Enable this pattern', 'hhmgt'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <div class="hhmgt-repeater-actions">
                            <button type="button" class="button button-secondary hhmgt-remove-item">
                                <span class="dashicons dashicons-trash"></span>
                                <?php esc_html_e('Remove Pattern', 'hhmgt'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <button type="button" id="add-pattern" class="button button-secondary">
        <span class="dashicons dashicons-plus-alt"></span>
        <?php esc_html_e('Add Pattern', 'hhmgt'); ?>
    </button>
</div>

<!-- Pattern Template -->
<script type="text/template" id="pattern-template">
    <div class="hhmgt-repeater-item" data-index="{{index}}">
        <div class="hhmgt-repeater-header">
            <span class="hhmgt-drag-handle"><span class="dashicons dashicons-menu"></span></span>
            <span class="hhmgt-repeater-title">
                <strong><?php esc_html_e('New Pattern', 'hhmgt'); ?></strong>
            </span>
            <button type="button" class="button hhmgt-repeater-toggle">
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </button>
        </div>
        <div class="hhmgt-repeater-content">
            <table class="form-table">
                <tr>
                    <th scope="row"><label><?php esc_html_e('Pattern Name', 'hhmgt'); ?></label></th>
                    <td>
                        <input type="text" name="patterns[{{index}}][pattern_name]" value="" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label><?php esc_html_e('Interval Type', 'hhmgt'); ?></label></th>
                    <td>
                        <label>
                            <input type="radio" name="patterns[{{index}}][interval_type]" value="fixed">
                            <strong><?php esc_html_e('Fixed', 'hhmgt'); ?></strong>
                        </label><br>
                        <label>
                            <input type="radio" name="patterns[{{index}}][interval_type]" value="dynamic" checked>
                            <strong><?php esc_html_e('Dynamic', 'hhmgt'); ?></strong>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label><?php esc_html_e('Interval (Days)', 'hhmgt'); ?></label></th>
                    <td>
                        <input type="number" name="patterns[{{index}}][interval_days]" value="7" min="1" max="365" class="small-text" required> <?php esc_html_e('days', 'hhmgt'); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label><?php esc_html_e('Lead Time (Days)', 'hhmgt'); ?></label></th>
                    <td>
                        <input type="number" name="patterns[{{index}}][lead_time_days]" value="0" min="0" max="90" class="small-text"> <?php esc_html_e('days', 'hhmgt'); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label><?php esc_html_e('Enabled', 'hhmgt'); ?></label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="patterns[{{index}}][is_enabled]" value="1" checked>
                            <?php esc_html_e('Enable this pattern', 'hhmgt'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <div class="hhmgt-repeater-actions">
                <button type="button" class="button button-secondary hhmgt-remove-item">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Remove Pattern', 'hhmgt'); ?>
                </button>
            </div>
        </div>
    </div>
</script>

<style>
.hhmgt-pattern-meta {
    color: #646970;
    font-size: 13px;
    font-weight: normal;
    margin-left: 8px;
}
</style>
