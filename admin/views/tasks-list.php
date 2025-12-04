<?php
/**
 * Tasks List Page
 *
 * @package HotelHub_Management_Tasks
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap hhmgt-admin">
    <h1 class="wp-heading-inline"><?php _e('Tasks', 'hhmgt'); ?></h1>
    <a href="<?php echo esc_url(add_query_arg(array('page' => 'hhmgt-edit-task', 'location_id' => $current_location_id), admin_url('admin.php'))); ?>" class="page-title-action">
        <?php _e('Add New Task', 'hhmgt'); ?>
    </a>

    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Task saved successfully.', 'hhmgt'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Task deleted successfully.', 'hhmgt'); ?></p>
        </div>
    <?php endif; ?>

    <hr class="wp-header-end">

    <!-- Location Selector -->
    <?php if (count($locations) > 1): ?>
        <div class="hhmgt-location-selector">
            <label for="location-select"><?php _e('Location:', 'hhmgt'); ?></label>
            <select id="location-select" onchange="window.location.href = this.value">
                <?php foreach ($locations as $location): ?>
                    <option value="<?php echo esc_url(add_query_arg('location_id', $location['id'])); ?>" <?php selected($current_location_id, $location['id']); ?>>
                        <?php echo esc_html($location['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="hhmgt-filters" style="margin: 20px 0;">
        <div class="hhmgt-filter-group">
            <label><?php _e('Department:', 'hhmgt'); ?></label>
            <select id="filter-department">
                <option value=""><?php _e('All Departments', 'hhmgt'); ?></option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo esc_attr($dept->id); ?>">
                        <?php echo esc_html($dept->dept_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="hhmgt-filter-group">
            <label><?php _e('Status:', 'hhmgt'); ?></label>
            <select id="filter-status">
                <option value=""><?php _e('All', 'hhmgt'); ?></option>
                <option value="active"><?php _e('Active', 'hhmgt'); ?></option>
                <option value="inactive"><?php _e('Inactive', 'hhmgt'); ?></option>
            </select>
        </div>

        <div class="hhmgt-filter-group">
            <label><?php _e('Type:', 'hhmgt'); ?></label>
            <select id="filter-type">
                <option value=""><?php _e('All', 'hhmgt'); ?></option>
                <option value="none"><?php _e('One-Time', 'hhmgt'); ?></option>
                <option value="fixed"><?php _e('Fixed Recurring', 'hhmgt'); ?></option>
                <option value="dynamic"><?php _e('Dynamic Recurring', 'hhmgt'); ?></option>
            </select>
        </div>

        <div class="hhmgt-filter-group">
            <input type="search" id="filter-search" placeholder="<?php esc_attr_e('Search tasks...', 'hhmgt'); ?>">
        </div>
    </div>

    <!-- Tasks Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 40%;"><?php _e('Task Name', 'hhmgt'); ?></th>
                <th style="width: 15%;"><?php _e('Department', 'hhmgt'); ?></th>
                <th style="width: 15%;"><?php _e('Recurrence', 'hhmgt'); ?></th>
                <th style="width: 15%;"><?php _e('Locations', 'hhmgt'); ?></th>
                <th style="width: 10%;"><?php _e('Status', 'hhmgt'); ?></th>
                <th style="width: 5%;"></th>
            </tr>
        </thead>
        <tbody id="tasks-list">
            <?php if (empty($tasks)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px;">
                        <span class="material-symbols-outlined" style="font-size: 48px; color: #9ca3af; display: block; margin-bottom: 10px;">assignment_turned_in</span>
                        <p style="color: #6b7280; margin: 0;"><?php _e('No tasks found. Create your first task to get started.', 'hhmgt'); ?></p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                    <tr class="task-row"
                        data-task-id="<?php echo esc_attr($task->id); ?>"
                        data-department-id="<?php echo esc_attr($task->department_id ?? ''); ?>"
                        data-status="<?php echo $task->is_active ? 'active' : 'inactive'; ?>"
                        data-type="<?php echo esc_attr($task->recurrence_type); ?>"
                        data-name="<?php echo esc_attr(strtolower($task->task_name)); ?>">

                        <td>
                            <strong>
                                <a href="<?php echo esc_url(add_query_arg(array('page' => 'hhmgt-edit-task', 'task_id' => $task->id, 'location_id' => $current_location_id), admin_url('admin.php'))); ?>">
                                    <?php echo esc_html($task->task_name); ?>
                                </a>
                            </strong>
                            <?php if (!empty($task->description)): ?>
                                <p style="margin: 5px 0 0; color: #6b7280; font-size: 13px;">
                                    <?php echo esc_html(wp_trim_words($task->description, 15)); ?>
                                </p>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($task->dept_name): ?>
                                <span class="hhmgt-badge" style="background-color: <?php echo esc_attr($task->dept_color); ?>;">
                                    <span class="material-symbols-outlined"><?php echo esc_html($task->dept_icon); ?></span>
                                    <?php echo esc_html($task->dept_name); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #9ca3af;">—</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($task->recurrence_type === 'none'): ?>
                                <span style="color: #6b7280;"><?php _e('One-Time', 'hhmgt'); ?></span>
                            <?php else: ?>
                                <span style="color: #059669;">
                                    <strong><?php echo esc_html($task->pattern_name); ?></strong>
                                    <br>
                                    <small style="color: #6b7280;">
                                        <?php
                                        printf(
                                            _n('%d day', '%d days', $task->interval_days, 'hhmgt'),
                                            $task->interval_days
                                        );
                                        echo ' (' . ucfirst($task->recurrence_type) . ')';
                                        ?>
                                    </small>
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($task->applies_to_multiple_locations): ?>
                                <?php
                                global $wpdb;
                                $count = $wpdb->get_var($wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$wpdb->prefix}hhmgt_task_locations WHERE task_id = %d",
                                    $task->id
                                ));
                                ?>
                                <span class="hhmgt-badge" style="background-color: #3b82f6;">
                                    <span class="material-symbols-outlined">location_on</span>
                                    <?php printf(_n('%d location', '%d locations', $count, 'hhmgt'), $count); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #6b7280;"><?php _e('Single Location', 'hhmgt'); ?></span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($task->is_active): ?>
                                <span class="hhmgt-status-badge hhmgt-status-active"><?php _e('Active', 'hhmgt'); ?></span>
                            <?php else: ?>
                                <span class="hhmgt-status-badge hhmgt-status-inactive"><?php _e('Inactive', 'hhmgt'); ?></span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="row-actions">
                                <a href="<?php echo esc_url(add_query_arg(array('page' => 'hhmgt-edit-task', 'task_id' => $task->id, 'location_id' => $current_location_id), admin_url('admin.php'))); ?>">
                                    <?php _e('Edit', 'hhmgt'); ?>
                                </a>
                                |
                                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'hhmgt_delete_task', 'task_id' => $task->id, 'location_id' => $current_location_id), admin_url('admin-post.php')), 'hhmgt_delete_task_' . $task->id)); ?>"
                                   onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this task? This will also delete all associated task instances.', 'hhmgt'); ?>');"
                                   style="color: #dc2626;">
                                    <?php _e('Delete', 'hhmgt'); ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.hhmgt-location-selector {
    margin: 20px 0;
    padding: 15px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
}

.hhmgt-location-selector label {
    font-weight: 600;
    margin-right: 10px;
}

.hhmgt-location-selector select {
    min-width: 200px;
}

.hhmgt-filters {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    padding: 15px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
}

.hhmgt-filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.hhmgt-filter-group label {
    font-size: 13px;
    font-weight: 500;
    color: #374151;
}

.hhmgt-filter-group select,
.hhmgt-filter-group input {
    min-width: 180px;
}

.hhmgt-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    color: white;
}

.hhmgt-badge .material-symbols-outlined {
    font-size: 16px;
}

.hhmgt-status-badge {
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.hhmgt-status-active {
    background-color: #d1fae5;
    color: #065f46;
}

.hhmgt-status-inactive {
    background-color: #f3f4f6;
    color: #6b7280;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Filter tasks
    function filterTasks() {
        const departmentId = $('#filter-department').val();
        const status = $('#filter-status').val();
        const type = $('#filter-type').val();
        const search = $('#filter-search').val().toLowerCase();

        $('.task-row').each(function() {
            const $row = $(this);
            let show = true;

            if (departmentId && $row.data('department-id') != departmentId) {
                show = false;
            }

            if (status && $row.data('status') !== status) {
                show = false;
            }

            if (type && $row.data('type') !== type) {
                show = false;
            }

            if (search && !$row.data('name').includes(search)) {
                show = false;
            }

            $row.toggle(show);
        });
    }

    $('#filter-department, #filter-status, #filter-type').on('change', filterTasks);
    $('#filter-search').on('input', filterTasks);
});
</script>
