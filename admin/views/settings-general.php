<?php
/**
 * General Settings Tab
 *
 * @package HotelHub_Management_Tasks
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$is_enabled = isset($location_settings['enabled']) ? (bool)$location_settings['enabled'] : false;
?>

<div class="hhmgt-settings-section">
    <h3><?php esc_html_e('Module Status', 'hhmgt'); ?></h3>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="enabled"><?php esc_html_e('Enable Tasks Module', 'hhmgt'); ?></label>
            </th>
            <td>
                <label class="hhmgt-toggle-switch">
                    <input type="checkbox"
                           id="enabled"
                           name="enabled"
                           value="1"
                           <?php checked($is_enabled, true); ?>>
                    <span class="hhmgt-toggle-slider"></span>
                </label>
                <p class="description">
                    <?php esc_html_e('Enable or disable the Tasks module for this location. When disabled, the module will not appear in the frontend app.', 'hhmgt'); ?>
                </p>
            </td>
        </tr>
    </table>
</div>

<div class="hhmgt-settings-section">
    <h3><?php esc_html_e('Information', 'hhmgt'); ?></h3>

    <table class="form-table">
        <tr>
            <th scope="row"><?php esc_html_e('Database Tables', 'hhmgt'); ?></th>
            <td>
                <ul>
                    <li><?php esc_html_e('Tasks: Main task definitions', 'hhmgt'); ?></li>
                    <li><?php esc_html_e('Task Instances: Individual occurrences', 'hhmgt'); ?></li>
                    <li><?php esc_html_e('Departments: Custom department configuration', 'hhmgt'); ?></li>
                    <li><?php esc_html_e('Location Hierarchy: Flexible location structure', 'hhmgt'); ?></li>
                    <li><?php esc_html_e('Recurring Patterns: Pattern definitions', 'hhmgt'); ?></li>
                    <li><?php esc_html_e('Task States: Custom status configuration', 'hhmgt'); ?></li>
                    <li><?php esc_html_e('Checklist Templates: Reusable checklists', 'hhmgt'); ?></li>
                    <li><?php esc_html_e('Task Notes: Notes with carry-forward', 'hhmgt'); ?></li>
                </ul>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('WP Cron Schedule', 'hhmgt'); ?></th>
            <td>
                <?php
                $next_scheduled = wp_next_scheduled('hhmgt_process_recurring_tasks');
                if ($next_scheduled) {
                    echo '<p>' . sprintf(
                        esc_html__('Next run: %s', 'hhmgt'),
                        '<strong>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_scheduled)) . '</strong>'
                    ) . '</p>';
                } else {
                    echo '<p class="description">' . esc_html__('Not scheduled', 'hhmgt') . '</p>';
                }
                ?>
            </td>
        </tr>
    </table>
</div>

<style>
.hhmgt-toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.hhmgt-toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.hhmgt-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.hhmgt-toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .hhmgt-toggle-slider {
    background-color: #10b981;
}

input:checked + .hhmgt-toggle-slider:before {
    transform: translateX(26px);
}
</style>
