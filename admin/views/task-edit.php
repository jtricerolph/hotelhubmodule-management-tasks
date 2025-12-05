<?php
/**
 * Task Create/Edit Page
 *
 * @package HotelHub_Management_Tasks
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($task);
$page_title = $is_edit ? __('Edit Task', 'hhmgt') : __('Add New Task', 'hhmgt');
?>

<div class="wrap hhmgt-admin">
    <h1><?php echo esc_html($page_title); ?></h1>

    <?php if (isset($_GET['future_updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php printf(__('%d future task instances updated successfully.', 'hhmgt'), intval($_GET['future_updated'])); ?></p>
        </div>
    <?php endif; ?>

    <?php if (empty($locations)): ?>
        <div class="notice notice-warning">
            <p><?php _e('No locations found. Please ensure Hotel Hub App is properly configured.', 'hhmgt'); ?></p>
        </div>
        <?php return; ?>
    <?php endif; ?>

    <!-- Location Tabs -->
    <h2 class="nav-tab-wrapper hhmgt-location-tabs">
        <?php foreach ($locations as $location): ?>
            <?php
            $is_active = ($location['id'] == $current_location_id);
            $tab_class = 'nav-tab' . ($is_active ? ' nav-tab-active' : '');
            $tab_url = add_query_arg(array(
                'page' => 'hhmgt-edit-task',
                'location_id' => $location['id']
            ), admin_url('admin.php'));
            // Preserve task_id if editing
            if ($is_edit) {
                $tab_url = add_query_arg('task_id', $task->id, $tab_url);
            }
            ?>
            <a href="<?php echo esc_url($tab_url); ?>" class="<?php echo esc_attr($tab_class); ?>">
                <?php echo esc_html($location['name']); ?>
            </a>
        <?php endforeach; ?>
    </h2>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="task-form" style="border-top: 1px solid #c3c4c7; padding-top: 20px; margin-top: 0;">
        <?php wp_nonce_field('hhmgt_save_task', 'hhmgt_task_nonce'); ?>
        <input type="hidden" name="action" value="hhmgt_save_task">
        <input type="hidden" name="task_id" value="<?php echo esc_attr($task->id ?? ''); ?>">
        <input type="hidden" name="location_id" value="<?php echo esc_attr($current_location_id); ?>">

        <div class="hhmgt-form-grid">
            <!-- Left Column: Main Settings -->
            <div class="hhmgt-form-column">
                <div class="hhmgt-card">
                    <h2><?php _e('Basic Information', 'hhmgt'); ?></h2>

                    <div class="hhmgt-form-group">
                        <label for="task_name"><?php _e('Task Name', 'hhmgt'); ?> <span class="required">*</span></label>
                        <input type="text"
                               id="task_name"
                               name="task_name"
                               class="regular-text"
                               value="<?php echo esc_attr($task->task_name ?? ''); ?>"
                               required>
                    </div>

                    <div class="hhmgt-form-group">
                        <label for="description"><?php _e('Description', 'hhmgt'); ?></label>
                        <textarea id="description"
                                  name="description"
                                  rows="4"
                                  class="large-text"><?php echo esc_textarea($task->description ?? ''); ?></textarea>
                    </div>

                    <div class="hhmgt-form-group">
                        <label for="department_id"><?php _e('Department', 'hhmgt'); ?></label>
                        <select id="department_id" name="department_id">
                            <option value=""><?php _e('-- Select Department --', 'hhmgt'); ?></option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo esc_attr($dept->id); ?>"
                                        <?php selected($task->department_id ?? '', $dept->id); ?>>
                                    <?php echo esc_html($dept->dept_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="hhmgt-form-group">
                        <label>
                            <input type="checkbox"
                                   name="is_active"
                                   value="1"
                                   <?php checked($task->is_active ?? true, 1); ?>>
                            <?php _e('Active', 'hhmgt'); ?>
                        </label>
                        <p class="description"><?php _e('Inactive tasks will not generate new instances.', 'hhmgt'); ?></p>
                    </div>
                </div>

                <!-- Recurrence Settings -->
                <div class="hhmgt-card">
                    <h2><?php _e('Recurrence Settings', 'hhmgt'); ?></h2>

                    <div class="hhmgt-form-group">
                        <label for="is_recurring"><?php _e('Task Type', 'hhmgt'); ?></label>
                        <select id="is_recurring" name="is_recurring">
                            <option value="0" <?php selected(empty($task->recurrence_pattern_id), true); ?>>
                                <?php _e('One-Time Task', 'hhmgt'); ?>
                            </option>
                            <option value="1" <?php selected(!empty($task->recurrence_pattern_id), true); ?>>
                                <?php _e('Recurring Task', 'hhmgt'); ?>
                            </option>
                        </select>
                    </div>

                    <div class="hhmgt-form-group" id="recurrence_pattern_group" style="display: none;">
                        <label for="recurrence_pattern_id"><?php _e('Recurring Pattern', 'hhmgt'); ?> <span class="required">*</span></label>
                        <select id="recurrence_pattern_id" name="recurrence_pattern_id">
                            <option value=""><?php _e('-- Select Pattern --', 'hhmgt'); ?></option>
                            <?php foreach ($patterns as $pattern): ?>
                                <option value="<?php echo esc_attr($pattern->id); ?>"
                                        data-interval="<?php echo esc_attr($pattern->interval_days); ?>"
                                        data-type="<?php echo esc_attr($pattern->interval_type); ?>"
                                        <?php selected($task->recurrence_pattern_id ?? '', $pattern->id); ?>>
                                    <?php echo esc_html($pattern->pattern_name); ?>
                                    - <?php printf(_n('%d day', '%d days', $pattern->interval_days, 'hhmgt'), $pattern->interval_days); ?>
                                    (<?php echo $pattern->interval_type === 'fixed' ? __('Fixed', 'hhmgt') : __('Dynamic', 'hhmgt'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php _e('Fixed: Scheduled on calendar (e.g., every 7 days). Dynamic: Scheduled from completion.', 'hhmgt'); ?>
                        </p>
                    </div>
                    <input type="hidden" name="recurrence_type" id="recurrence_type" value="<?php echo esc_attr($task->recurrence_type ?? 'none'); ?>">

                    <?php if ($is_edit && ($task->recurrence_type === 'fixed' || $task->recurrence_type === 'dynamic')): ?>
                        <div class="hhmgt-future-tasks-notice">
                            <span class="material-symbols-outlined">info</span>
                            <div>
                                <strong><?php _e('Future Task Instances', 'hhmgt'); ?></strong>
                                <p>
                                    <?php
                                    $future_count = HHMGT_Bulk_Update::count_future_instances($task->id);
                                    printf(
                                        _n('This task has %d future instance scheduled.', 'This task has %d future instances scheduled.', $future_count, 'hhmgt'),
                                        $future_count
                                    );
                                    ?>
                                </p>
                                <?php if ($future_count == 0): ?>
                                    <p class="description" style="margin-bottom: 10px;">
                                        <?php _e('No instances scheduled yet. The scheduler runs hourly, or you can schedule manually:', 'hhmgt'); ?>
                                    </p>
                                <?php endif; ?>
                                <button type="button" class="button" id="schedule-now-btn">
                                    <span class="material-symbols-outlined" style="font-size: 18px; vertical-align: middle;">play_arrow</span>
                                    <?php _e('Schedule Now', 'hhmgt'); ?>
                                </button>
                                <?php if ($future_count > 0): ?>
                                    <button type="button" class="button" id="manage-future-tasks-btn">
                                        <?php _e('Manage Future Instances', 'hhmgt'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Location Assignment -->
                <div class="hhmgt-card">
                    <h2><?php _e('Location Assignment', 'hhmgt'); ?></h2>

                    <div class="hhmgt-form-group">
                        <label>
                            <input type="checkbox"
                                   name="applies_to_multiple_locations"
                                   id="applies_to_multiple_locations"
                                   value="1"
                                   <?php checked($task->applies_to_multiple_locations ?? false, 1); ?>>
                            <?php _e('Apply to Multiple Locations', 'hhmgt'); ?>
                        </label>
                        <p class="description"><?php _e('Check this to assign the same task to multiple rooms/areas. Great for housekeeping tasks.', 'hhmgt'); ?></p>
                    </div>

                    <div id="location_hierarchy_group" style="display: none;">
                        <label><?php _e('Select Locations', 'hhmgt'); ?></label>
                        <div class="hhmgt-location-selector">
                            <?php if (empty($location_hierarchy)): ?>
                                <p style="color: #9ca3af;">
                                    <?php _e('No location hierarchy defined. Go to Settings to create room/area structure.', 'hhmgt'); ?>
                                </p>
                            <?php else: ?>
                                <div class="hhmgt-location-list">
                                    <?php foreach ($location_hierarchy as $location): ?>
                                        <label class="hhmgt-location-item" style="padding-left: <?php echo ($location->hierarchy_level * 20); ?>px;">
                                            <input type="checkbox"
                                                   name="location_hierarchy_ids[]"
                                                   value="<?php echo esc_attr($location->id); ?>"
                                                   <?php checked(in_array($location->id, $task_locations ?? array())); ?>>
                                            <span><?php echo esc_html($location->location_name); ?></span>
                                            <?php if ($location->location_type): ?>
                                                <small>(<?php echo esc_html($location->location_type); ?>)</small>
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div style="margin-top: 10px;">
                                    <button type="button" class="button" id="select-all-locations"><?php _e('Select All', 'hhmgt'); ?></button>
                                    <button type="button" class="button" id="deselect-all-locations"><?php _e('Deselect All', 'hhmgt'); ?></button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Checklist & Photos -->
            <div class="hhmgt-form-column">
                <!-- Checklist Items -->
                <div class="hhmgt-card">
                    <h2><?php _e('Checklist Items', 'hhmgt'); ?></h2>

                    <div class="hhmgt-form-group">
                        <label><?php _e('Add checklist items for this task', 'hhmgt'); ?></label>

                        <?php if (!empty($templates)): ?>
                            <div style="margin-bottom: 15px;">
                                <label for="load-template"><?php _e('Or load from template:', 'hhmgt'); ?></label>
                                <select id="load-template" style="width: auto; margin-right: 10px;">
                                    <option value=""><?php _e('-- Select Template --', 'hhmgt'); ?></option>
                                    <?php foreach ($templates as $template): ?>
                                        <?php
                                        // Decode JSON from database and re-encode for JavaScript
                                        $items = json_decode($template->checklist_items, true);
                                        $items_json = !empty($items) ? json_encode($items) : '[]';
                                        ?>
                                        <option value="<?php echo esc_attr($template->id); ?>" data-items='<?php echo esc_attr($items_json); ?>'>
                                            <?php echo esc_html($template->template_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="button" id="load-template-btn">
                                    <span class="material-symbols-outlined">download</span>
                                    <?php _e('Load Template', 'hhmgt'); ?>
                                </button>
                            </div>
                        <?php endif; ?>

                        <div id="checklist-items">
                            <?php
                            $checklist_items = !empty($task->checklist_items) ? json_decode($task->checklist_items, true) : array();
                            if (!empty($checklist_items)):
                                foreach ($checklist_items as $index => $item):
                            ?>
                                <div class="hhmgt-checklist-item">
                                    <span class="material-symbols-outlined hhmgt-drag-handle">drag_indicator</span>
                                    <input type="text"
                                           name="checklist_items[]"
                                           value="<?php echo esc_attr($item); ?>"
                                           placeholder="<?php esc_attr_e('Checklist item...', 'hhmgt'); ?>">
                                    <button type="button" class="button hhmgt-remove-item">
                                        <span class="material-symbols-outlined">close</span>
                                    </button>
                                </div>
                            <?php
                                endforeach;
                            endif;
                            ?>
                        </div>
                        <button type="button" class="button" id="add-checklist-item">
                            <span class="material-symbols-outlined">add</span>
                            <?php _e('Add Item', 'hhmgt'); ?>
                        </button>
                    </div>
                </div>

                <!-- Reference Photos -->
                <div class="hhmgt-card">
                    <h2><?php _e('Reference Photos', 'hhmgt'); ?></h2>
                    <p class="description"><?php _e('Upload reference photos showing how the task should be completed.', 'hhmgt'); ?></p>

                    <div class="hhmgt-photo-upload">
                        <button type="button" class="button" id="upload-reference-photos">
                            <span class="material-symbols-outlined">add_photo_alternate</span>
                            <?php _e('Upload Photos', 'hhmgt'); ?>
                        </button>
                        <div id="reference-photos-preview" class="hhmgt-photo-gallery">
                            <?php
                            // TODO: Display existing reference photos
                            ?>
                        </div>
                        <input type="hidden" name="reference_photos" id="reference_photos" value="<?php echo esc_attr($task->reference_photos ?? ''); ?>">
                    </div>
                </div>

                <!-- Completion Settings -->
                <div class="hhmgt-card">
                    <h2><?php _e('Completion Settings', 'hhmgt'); ?></h2>

                    <div class="hhmgt-form-group">
                        <label>
                            <input type="checkbox"
                                   name="require_completion_photo"
                                   value="1"
                                   <?php checked($task->require_completion_photo ?? false, 1); ?>>
                            <?php _e('Require Completion Photo', 'hhmgt'); ?>
                        </label>
                        <p class="description"><?php _e('User must upload a photo when marking task as complete.', 'hhmgt'); ?></p>
                    </div>

                    <div class="hhmgt-form-group">
                        <label for="completion_reminder_text"><?php _e('Completion Reminder', 'hhmgt'); ?></label>
                        <textarea id="completion_reminder_text"
                                  name="completion_reminder_text"
                                  rows="3"
                                  class="large-text"
                                  placeholder="<?php esc_attr_e('e.g., Did you check all safety protocols?', 'hhmgt'); ?>"><?php echo esc_textarea($task->completion_reminder_text ?? ''); ?></textarea>
                        <p class="description"><?php _e('Text shown when user completes the task (with acknowledgment checkbox).', 'hhmgt'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Actions -->
        <div class="hhmgt-submit-actions">
            <?php if ($is_edit): ?>
                <div style="flex: 1;">
                    <label style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" name="update_future_instances" value="1">
                        <span><?php _e('Update all future (non-completed) task instances with new settings', 'hhmgt'); ?></span>
                    </label>
                </div>
            <?php endif; ?>
            <div>
                <a href="<?php echo esc_url(add_query_arg(array('page' => 'hhmgt-tasks', 'location_id' => $current_location_id), admin_url('admin.php'))); ?>" class="button">
                    <?php _e('Cancel', 'hhmgt'); ?>
                </a>
                <button type="submit" class="button button-primary">
                    <?php echo $is_edit ? __('Update Task', 'hhmgt') : __('Create Task', 'hhmgt'); ?>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Future Tasks Management Modal -->
<?php if ($is_edit && ($task->recurrence_type === 'fixed' || $task->recurrence_type === 'dynamic')): ?>
<div id="future-tasks-modal" class="hhmgt-modal" style="display: none;">
    <div class="hhmgt-modal-overlay"></div>
    <div class="hhmgt-modal-content" style="max-width: 500px;">
        <div class="hhmgt-modal-header">
            <h3><?php _e('Manage Future Task Instances', 'hhmgt'); ?></h3>
            <button type="button" class="hhmgt-modal-close">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="hhmgt-modal-body">
            <p><?php _e('Choose an action to apply to all future (non-completed) task instances:', 'hhmgt'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="future-tasks-form">
                <?php wp_nonce_field('hhmgt_update_future_' . $task->id, '_wpnonce'); ?>
                <input type="hidden" name="action" value="hhmgt_update_future_tasks">
                <input type="hidden" name="task_id" value="<?php echo esc_attr($task->id); ?>">
                <input type="hidden" name="location_id" value="<?php echo esc_attr($current_location_id); ?>">

                <div class="hhmgt-form-group">
                    <label>
                        <input type="radio" name="action_type" value="update" checked>
                        <strong><?php _e('Update Settings', 'hhmgt'); ?></strong>
                        <p class="description"><?php _e('Apply current checklist items and settings to future instances.', 'hhmgt'); ?></p>
                    </label>
                </div>

                <div class="hhmgt-form-group">
                    <label>
                        <input type="radio" name="action_type" value="reschedule">
                        <strong><?php _e('Reschedule with New Interval', 'hhmgt'); ?></strong>
                        <p class="description"><?php _e('Recalculate all future due dates with a new interval.', 'hhmgt'); ?></p>
                    </label>
                    <input type="number"
                           name="new_interval_days"
                           min="1"
                           placeholder="<?php esc_attr_e('Enter new interval (days)', 'hhmgt'); ?>"
                           style="margin-left: 24px; margin-top: 8px;">
                </div>

                <div class="hhmgt-form-group">
                    <label>
                        <input type="radio" name="action_type" value="clear">
                        <strong style="color: #dc2626;"><?php _e('Clear All Future Instances', 'hhmgt'); ?></strong>
                        <p class="description"><?php _e('Delete all future instances. They will be regenerated by the scheduler.', 'hhmgt'); ?></p>
                    </label>
                </div>

                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" class="button hhmgt-modal-close"><?php _e('Cancel', 'hhmgt'); ?></button>
                    <button type="submit" class="button button-primary"><?php _e('Apply Changes', 'hhmgt'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.hhmgt-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin: 20px 0;
}

@media (max-width: 1200px) {
    .hhmgt-form-grid {
        grid-template-columns: 1fr;
    }
}

.hhmgt-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.hhmgt-card h2 {
    margin: 0 0 20px;
    font-size: 18px;
    font-weight: 600;
    color: #111827;
}

.hhmgt-form-group {
    margin-bottom: 20px;
}

.hhmgt-form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #374151;
}

.hhmgt-form-group .description {
    margin: 5px 0 0;
    font-size: 13px;
    color: #6b7280;
}

.hhmgt-form-group input[type="text"],
.hhmgt-form-group textarea,
.hhmgt-form-group select {
    width: 100%;
}

.required {
    color: #dc2626;
}

.hhmgt-checklist-item {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}

.hhmgt-checklist-item input[type="text"] {
    flex: 1;
}

.hhmgt-drag-handle {
    cursor: move;
    color: #9ca3af;
}

.hhmgt-remove-item {
    padding: 4px 8px !important;
    min-width: auto !important;
}

.hhmgt-location-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    padding: 10px;
    background: #f9fafb;
}

.hhmgt-location-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    cursor: pointer;
}

.hhmgt-location-item:hover {
    background: #fff;
}

.hhmgt-location-item input[type="checkbox"] {
    margin: 0;
}

.hhmgt-location-item small {
    color: #6b7280;
    margin-left: 4px;
}

.hhmgt-submit-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 20px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    position: sticky;
    bottom: 20px;
}

.hhmgt-future-tasks-notice {
    display: flex;
    gap: 12px;
    padding: 15px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    margin-top: 15px;
}

.hhmgt-future-tasks-notice .material-symbols-outlined {
    color: #3b82f6;
    font-size: 24px;
}

.hhmgt-future-tasks-notice p {
    margin: 5px 0 10px;
    font-size: 14px;
}

.hhmgt-photo-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 10px;
    margin-top: 15px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Show/hide recurrence pattern based on is_recurring
    $('#is_recurring').on('change', function() {
        const isRecurring = $(this).val() === '1';
        $('#recurrence_pattern_group').toggle(isRecurring);
        if (!isRecurring) {
            $('#recurrence_type').val('none');
        }
    }).trigger('change');

    // Update recurrence_type based on selected pattern
    $('#recurrence_pattern_id').on('change', function() {
        const $selected = $(this).find(':selected');
        const intervalType = $selected.data('type');
        if (intervalType) {
            $('#recurrence_type').val(intervalType);
        }
    });

    // Show/hide location hierarchy based on multi-location checkbox
    $('#applies_to_multiple_locations').on('change', function() {
        $('#location_hierarchy_group').toggle($(this).is(':checked'));
    }).trigger('change');

    // Add checklist item
    $('#add-checklist-item').on('click', function() {
        const $item = $(`
            <div class="hhmgt-checklist-item">
                <span class="material-symbols-outlined hhmgt-drag-handle">drag_indicator</span>
                <input type="text" name="checklist_items[]" placeholder="<?php esc_attr_e('Checklist item...', 'hhmgt'); ?>">
                <button type="button" class="button hhmgt-remove-item">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        `);
        $('#checklist-items').append($item);
        $item.find('input').focus();
    });

    // Remove checklist item
    $(document).on('click', '.hhmgt-remove-item', function() {
        $(this).closest('.hhmgt-checklist-item').remove();
    });

    // Load checklist template
    $('#load-template-btn').on('click', function() {
        const $selected = $('#load-template').find(':selected');
        const itemsJson = $selected.data('items');

        if (!itemsJson) {
            alert('<?php esc_attr_e('Please select a template first.', 'hhmgt'); ?>');
            return;
        }

        if (!confirm('<?php esc_attr_e('This will replace your current checklist items. Continue?', 'hhmgt'); ?>')) {
            return;
        }

        try {
            // Clear existing items
            $('#checklist-items').empty();

            // Parse and add template items
            const items = JSON.parse(itemsJson);

            if (!Array.isArray(items) || items.length === 0) {
                alert('<?php esc_attr_e('Template has no items.', 'hhmgt'); ?>');
                return;
            }

            items.forEach(function(item) {
                const $item = $(`
                    <div class="hhmgt-checklist-item">
                        <span class="material-symbols-outlined hhmgt-drag-handle">drag_indicator</span>
                        <input type="text" name="checklist_items[]" value="${item}" placeholder="<?php esc_attr_e('Checklist item...', 'hhmgt'); ?>">
                        <button type="button" class="button hhmgt-remove-item">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                `);
                $('#checklist-items').append($item);
            });

            // Reset template selector
            $('#load-template').val('');
        } catch (e) {
            console.error('Error loading template:', e);
            alert('<?php esc_attr_e('Error loading template. Please try again.', 'hhmgt'); ?>');
        }
    });

    // Select/deselect all locations
    $('#select-all-locations').on('click', function() {
        $('input[name="location_hierarchy_ids[]"]').prop('checked', true);
    });

    $('#deselect-all-locations').on('click', function() {
        $('input[name="location_hierarchy_ids[]"]').prop('checked', false);
    });

    // Schedule Now button
    $('#schedule-now-btn').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.html();

        if (!confirm('<?php esc_attr_e('This will generate future task instances based on the recurring pattern. Continue?', 'hhmgt'); ?>')) {
            return;
        }

        $btn.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0;"></span> <?php esc_attr_e('Scheduling...', 'hhmgt'); ?>');

        $.ajax({
            url: hhmgtAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'hhmgt_schedule_task_now',
                nonce: hhmgtAdmin.nonce,
                task_id: <?php echo intval($task->id ?? 0); ?>
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message || '<?php esc_attr_e('Task scheduled successfully!', 'hhmgt'); ?>');
                    location.reload();
                } else {
                    alert(response.data.message || '<?php esc_attr_e('Error scheduling task.', 'hhmgt'); ?>');
                    $btn.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                alert('<?php esc_attr_e('Error scheduling task. Please try again.', 'hhmgt'); ?>');
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Manage future tasks modal
    $('#manage-future-tasks-btn').on('click', function() {
        $('#future-tasks-modal').fadeIn(200);
    });

    $('.hhmgt-modal-close, .hhmgt-modal-overlay').on('click', function(e) {
        if ($(e.target).hasClass('hhmgt-modal-overlay') || $(e.target).closest('.hhmgt-modal-close').length) {
            $('#future-tasks-modal').fadeOut(200);
        }
    });

    // Validate future tasks form
    $('#future-tasks-form').on('submit', function(e) {
        const actionType = $('input[name="action_type"]:checked').val();

        if (actionType === 'reschedule') {
            const interval = $('input[name="new_interval_days"]').val();
            if (!interval || interval < 1) {
                e.preventDefault();
                alert('<?php esc_attr_e('Please enter a valid interval (minimum 1 day).', 'hhmgt'); ?>');
                return false;
            }
        }

        if (actionType === 'clear') {
            if (!confirm('<?php esc_attr_e('Are you sure you want to delete all future task instances? This cannot be undone.', 'hhmgt'); ?>')) {
                e.preventDefault();
                return false;
            }
        }
    });

    // Upload reference photos (WordPress Media Library)
    $('#upload-reference-photos').on('click', function(e) {
        e.preventDefault();

        const mediaUploader = wp.media({
            title: '<?php esc_attr_e('Select Reference Photos', 'hhmgt'); ?>',
            button: {
                text: '<?php esc_attr_e('Use these photos', 'hhmgt'); ?>'
            },
            multiple: true,
            library: {
                type: 'image'
            }
        });

        mediaUploader.on('select', function() {
            const attachments = mediaUploader.state().get('selection').toJSON();
            const attachmentIds = attachments.map(a => a.id);

            $('#reference_photos').val(JSON.stringify(attachmentIds));

            // Display previews
            const $preview = $('#reference-photos-preview');
            $preview.empty();

            attachments.forEach(function(attachment) {
                $preview.append(`
                    <div class="hhmgt-photo-item">
                        <img src="${attachment.sizes.thumbnail.url}" alt="">
                    </div>
                `);
            });
        });

        mediaUploader.open();
    });
});
</script>
