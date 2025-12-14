<?php
/**
 * All Tasks Page (Task Instances View)
 *
 * @package HotelHub_Management_Tasks
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap hhmgt-admin hhmgt-instances-page">
    <h1 class="wp-heading-inline"><?php _e('All Tasks', 'hhmgt'); ?></h1>

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
                'page' => 'hhmgt-tasks',
                'location_id' => $location['id']
            ), admin_url('admin.php'));
            ?>
            <a href="<?php echo esc_url($tab_url); ?>" class="<?php echo esc_attr($tab_class); ?>">
                <?php echo esc_html($location['name']); ?>
            </a>
        <?php endforeach; ?>
    </h2>

    <!-- Filters -->
    <form method="get" class="hhmgt-instances-filters" style="margin: 20px 0; background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">
        <input type="hidden" name="page" value="hhmgt-tasks">
        <input type="hidden" name="location_id" value="<?php echo esc_attr($current_location_id); ?>">

        <!-- Row 1: Basic filters -->
        <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; margin-bottom: 15px;">
            <!-- Department Filter -->
            <div class="hhmgt-filter-group">
                <label for="filter-department" style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Department', 'hhmgt'); ?></label>
                <select name="department[]" id="filter-department" multiple style="min-width: 120px; height: 60px;">
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo esc_attr($dept->id); ?>"
                            <?php echo in_array($dept->id, $filters['department']) ? 'selected' : ''; ?>>
                            <?php echo esc_html($dept->dept_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="hhmgt-filter-group">
                <label for="filter-status" style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Status', 'hhmgt'); ?></label>
                <select name="status[]" id="filter-status" multiple style="min-width: 120px; height: 60px;">
                    <?php foreach ($states as $state): ?>
                        <option value="<?php echo esc_attr($state->id); ?>"
                            <?php echo in_array($state->id, $filters['status']) ? 'selected' : ''; ?>>
                            <?php echo esc_html($state->state_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Search -->
            <div class="hhmgt-filter-group">
                <label for="filter-search" style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Search', 'hhmgt'); ?></label>
                <input type="text" name="search" id="filter-search" value="<?php echo esc_attr($filters['search']); ?>"
                       placeholder="<?php esc_attr_e('Task name...', 'hhmgt'); ?>" style="width: 150px;">
            </div>

            <!-- Include Completed -->
            <div class="hhmgt-filter-group" style="padding-bottom: 5px;">
                <label style="display: flex; align-items: center; gap: 5px;">
                    <input type="checkbox" name="include_completed" value="1"
                        <?php checked($filters['include_completed']); ?>>
                    <?php _e('Include Completed', 'hhmgt'); ?>
                </label>
            </div>

            <!-- Per Page -->
            <div class="hhmgt-filter-group">
                <label for="filter-per-page" style="display: block; margin-bottom: 5px; font-weight: 500;"><?php _e('Per Page', 'hhmgt'); ?></label>
                <select name="per_page" id="filter-per-page">
                    <option value="25" <?php selected($per_page, 25); ?>>25</option>
                    <option value="50" <?php selected($per_page, 50); ?>>50</option>
                    <option value="100" <?php selected($per_page, 100); ?>>100</option>
                    <option value="999999" <?php selected($per_page, 999999); ?>><?php _e('All', 'hhmgt'); ?></option>
                </select>
            </div>
        </div>

        <!-- Row 2: Date range filters -->
        <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; padding-top: 15px; border-top: 1px solid #e5e7eb;">
            <!-- Scheduled Date Range -->
            <div class="hhmgt-filter-group hhmgt-date-filter">
                <label style="display: flex; align-items: center; gap: 5px; margin-bottom: 5px;">
                    <input type="checkbox" name="filter_scheduled" value="1" <?php checked($filters['filter_scheduled']); ?>>
                    <span style="font-weight: 500;"><?php _e('Scheduled', 'hhmgt'); ?></span>
                </label>
                <div style="display: flex; gap: 5px; align-items: center;">
                    <input type="date" name="scheduled_from" value="<?php echo esc_attr($filters['scheduled_from']); ?>" style="width: 130px;">
                    <span>-</span>
                    <input type="date" name="scheduled_to" value="<?php echo esc_attr($filters['scheduled_to']); ?>" style="width: 130px;">
                </div>
            </div>

            <!-- Due Date Range -->
            <div class="hhmgt-filter-group hhmgt-date-filter">
                <label style="display: flex; align-items: center; gap: 5px; margin-bottom: 5px;">
                    <input type="checkbox" name="filter_due" value="1" <?php checked($filters['filter_due']); ?>>
                    <span style="font-weight: 500;"><?php _e('Due', 'hhmgt'); ?></span>
                </label>
                <div style="display: flex; gap: 5px; align-items: center;">
                    <input type="date" name="due_from" value="<?php echo esc_attr($filters['due_from']); ?>" style="width: 130px;">
                    <span>-</span>
                    <input type="date" name="due_to" value="<?php echo esc_attr($filters['due_to']); ?>" style="width: 130px;">
                </div>
            </div>

            <!-- Completed Date Range -->
            <div class="hhmgt-filter-group hhmgt-date-filter">
                <label style="display: flex; align-items: center; gap: 5px; margin-bottom: 5px;">
                    <input type="checkbox" name="filter_completed" value="1" <?php checked($filters['filter_completed']); ?>>
                    <span style="font-weight: 500;"><?php _e('Completed', 'hhmgt'); ?></span>
                </label>
                <div style="display: flex; gap: 5px; align-items: center;">
                    <input type="date" name="completed_from" value="<?php echo esc_attr($filters['completed_from']); ?>" style="width: 130px;">
                    <span>-</span>
                    <input type="date" name="completed_to" value="<?php echo esc_attr($filters['completed_to']); ?>" style="width: 130px;">
                </div>
            </div>

            <!-- Submit -->
            <div class="hhmgt-filter-group">
                <button type="submit" class="button button-primary"><?php _e('Filter', 'hhmgt'); ?></button>
                <a href="<?php echo esc_url(add_query_arg(array('page' => 'hhmgt-tasks', 'location_id' => $current_location_id), admin_url('admin.php'))); ?>"
                   class="button"><?php _e('Reset', 'hhmgt'); ?></a>
            </div>
        </div>
    </form>

    <!-- Results Summary -->
    <div style="margin-bottom: 10px;">
        <span class="displaying-num">
            <?php printf(_n('%s task', '%s tasks', $total_instances, 'hhmgt'), number_format_i18n($total_instances)); ?>
        </span>
    </div>

    <!-- Tasks Table -->
    <table class="wp-list-table widefat fixed striped hhmgt-instances-table">
        <thead>
            <tr>
                <th scope="col" style="width: 28%;"><?php _e('Task Name', 'hhmgt'); ?></th>
                <th scope="col" style="width: 18%;"><?php _e('Location', 'hhmgt'); ?></th>
                <th scope="col" style="width: 5%;"><?php _e('Dept', 'hhmgt'); ?></th>
                <th scope="col" style="width: 9%;"><?php _e('Scheduled', 'hhmgt'); ?></th>
                <th scope="col" style="width: 9%;"><?php _e('Due', 'hhmgt'); ?></th>
                <th scope="col" style="width: 10%;"><?php _e('Status', 'hhmgt'); ?></th>
                <th scope="col" style="width: 12%;"><?php _e('Completed', 'hhmgt'); ?></th>
                <th scope="col" style="width: 9%;"><?php _e('Actions', 'hhmgt'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($instances)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 20px;">
                        <?php _e('No tasks found matching your criteria.', 'hhmgt'); ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($instances as $instance): ?>
                    <tr data-instance-id="<?php echo esc_attr($instance->id); ?>">
                        <td>
                            <strong><?php echo esc_html($instance->task_name); ?></strong>
                        </td>
                        <td>
                            <?php if ($instance->location_name): ?>
                                <?php echo esc_html($instance->location_level); ?>: <?php echo esc_html($instance->location_name); ?>
                            <?php else: ?>
                                <span style="color: #999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($instance->dept_name): ?>
                                <span class="hhmgt-dept-icon" style="background-color: <?php echo esc_attr($instance->dept_color); ?>; color: #fff; padding: 4px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center;" title="<?php echo esc_attr($instance->dept_name); ?>">
                                    <span class="material-symbols-outlined" style="font-size: 18px;"><?php echo esc_html($instance->dept_icon ?: 'assignment'); ?></span>
                                </span>
                            <?php else: ?>
                                <span style="color: #999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html(date_i18n('d/m/y', strtotime($instance->scheduled_date))); ?>
                        </td>
                        <td>
                            <?php
                            $due_date = strtotime($instance->due_date);
                            $today = strtotime(current_time('Y-m-d'));
                            $is_overdue = $due_date < $today && !$instance->is_complete_state;
                            ?>
                            <span style="<?php echo $is_overdue ? 'color: #dc3232; font-weight: 600;' : ''; ?>">
                                <?php echo esc_html(date_i18n('d/m/y', $due_date)); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($instance->state_name): ?>
                                <span class="hhmgt-status-badge" style="background-color: <?php echo esc_attr($instance->color_hex); ?>; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 12px;">
                                    <?php echo esc_html($instance->state_name); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($instance->completed_at): ?>
                                <?php echo esc_html(date_i18n('d/m/y', strtotime($instance->completed_at))); ?>
                                <?php if ($instance->completed_by_name): ?>
                                    <br><span style="color: #666; font-size: 11px;"><?php echo esc_html($instance->completed_by_name); ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #999;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="row-actions visible" style="display: flex; gap: 5px;">
                                <button type="button" class="button button-small hhmgt-view-instance" data-instance-id="<?php echo esc_attr($instance->id); ?>" title="<?php esc_attr_e('View', 'hhmgt'); ?>">
                                    <span class="dashicons dashicons-visibility" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
                                </button>
                                <?php if (!$instance->is_complete_state): ?>
                                    <button type="button" class="button button-small hhmgt-delete-instance" data-instance-id="<?php echo esc_attr($instance->id); ?>" title="<?php esc_attr_e('Delete', 'hhmgt'); ?>">
                                        <span class="dashicons dashicons-trash" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; color: #dc3232;"></span>
                                    </button>
                                <?php endif; ?>
                                <a href="<?php echo esc_url(add_query_arg(array('page' => 'hhmgt-edit-task', 'task_id' => $instance->task_id, 'location_id' => $current_location_id), admin_url('admin.php'))); ?>"
                                   class="button button-small" title="<?php esc_attr_e('Configure Task', 'hhmgt'); ?>">
                                    <span class="dashicons dashicons-admin-generic" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(_n('%s task', '%s tasks', $total_instances, 'hhmgt'), number_format_i18n($total_instances)); ?>
                </span>
                <span class="pagination-links">
                    <?php
                    $pagination_base = add_query_arg(array(
                        'page' => 'hhmgt-tasks',
                        'location_id' => $current_location_id,
                        'department' => $filters['department'],
                        'status' => $filters['status'],
                        'search' => $filters['search'],
                        'include_completed' => $filters['include_completed'] ? '1' : '',
                        'include_future' => $filters['include_future'] ? '1' : '',
                        'per_page' => $per_page,
                    ), admin_url('admin.php'));

                    // First page
                    if ($paged > 1): ?>
                        <a class="first-page button" href="<?php echo esc_url(add_query_arg('paged', 1, $pagination_base)); ?>">
                            <span class="screen-reader-text"><?php _e('First page', 'hhmgt'); ?></span>
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                        <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', $paged - 1, $pagination_base)); ?>">
                            <span class="screen-reader-text"><?php _e('Previous page', 'hhmgt'); ?></span>
                            <span aria-hidden="true">&lsaquo;</span>
                        </a>
                    <?php else: ?>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
                    <?php endif; ?>

                    <span class="paging-input">
                        <span class="tablenav-paging-text">
                            <?php echo $paged; ?> <?php _e('of', 'hhmgt'); ?>
                            <span class="total-pages"><?php echo $total_pages; ?></span>
                        </span>
                    </span>

                    <?php if ($paged < $total_pages): ?>
                        <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', $paged + 1, $pagination_base)); ?>">
                            <span class="screen-reader-text"><?php _e('Next page', 'hhmgt'); ?></span>
                            <span aria-hidden="true">&rsaquo;</span>
                        </a>
                        <a class="last-page button" href="<?php echo esc_url(add_query_arg('paged', $total_pages, $pagination_base)); ?>">
                            <span class="screen-reader-text"><?php _e('Last page', 'hhmgt'); ?></span>
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    <?php else: ?>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
                        <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Task Detail Modal (reused from frontend) -->
<div id="hhmgt-admin-task-modal" class="hhmgt-modal" style="display: none;">
    <div class="hhmgt-modal-overlay"></div>
    <div class="hhmgt-modal-content">
        <!-- Content loaded dynamically -->
    </div>
</div>

<!-- Status Selection Modal -->
<div id="hhmgt-admin-status-modal" class="hhmgt-modal hhmgt-modal-small" style="display: none;">
    <div class="hhmgt-modal-overlay"></div>
    <div class="hhmgt-modal-content">
        <!-- Content loaded dynamically -->
    </div>
</div>

<!-- Lightbox -->
<div id="hhmgt-lightbox" class="hhmgt-lightbox">
    <div class="hhmgt-lightbox-content">
        <button type="button" class="hhmgt-lightbox-close">
            <span class="dashicons dashicons-no-alt" style="font-size: 32px; width: 32px; height: 32px;"></span>
        </button>
        <button type="button" class="hhmgt-lightbox-nav prev">
            <span class="dashicons dashicons-arrow-left-alt2" style="font-size: 32px; width: 32px; height: 32px;"></span>
        </button>
        <img src="" alt="Photo" class="hhmgt-lightbox-image">
        <button type="button" class="hhmgt-lightbox-nav next">
            <span class="dashicons dashicons-arrow-right-alt2" style="font-size: 32px; width: 32px; height: 32px;"></span>
        </button>
        <div class="hhmgt-lightbox-counter"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Store current state
    var adminTaskState = {
        currentInstance: null,
        currentTask: null,
        states: []
    };

    // View instance button click
    $('.hhmgt-view-instance').on('click', function() {
        var instanceId = $(this).data('instance-id');
        openAdminTaskModal(instanceId);
    });

    // Delete instance button click
    $('.hhmgt-delete-instance').on('click', function() {
        var instanceId = $(this).data('instance-id');
        var $row = $(this).closest('tr');

        if (!confirm('<?php echo esc_js(__('Are you sure you want to delete this task instance?', 'hhmgt')); ?>')) {
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'hhmgt_admin_delete_instance',
                nonce: '<?php echo wp_create_nonce('hhmgt_admin_nonce'); ?>',
                instance_id: instanceId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data || '<?php echo esc_js(__('Failed to delete task', 'hhmgt')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('An error occurred', 'hhmgt')); ?>');
            }
        });
    });

    // Open task modal
    function openAdminTaskModal(instanceId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'hhmgt_get_task_detail',
                nonce: '<?php echo wp_create_nonce('hhmgt_ajax_nonce'); ?>',
                instance_id: instanceId
            },
            success: function(response) {
                if (response.success) {
                    adminTaskState.currentInstance = response.data.instance;
                    adminTaskState.currentTask = response.data;
                    adminTaskState.states = response.data.states || [];
                    renderAdminTaskModal(response.data);
                    $('#hhmgt-admin-task-modal').fadeIn(200);
                } else {
                    alert(response.data || '<?php echo esc_js(__('Failed to load task details', 'hhmgt')); ?>');
                }
            }
        });
    }

    // Render modal content
    function renderAdminTaskModal(data) {
        var instance = data.instance;
        var notes = data.notes || [];
        var states = data.states || [];

        // Find current status
        var currentStatus = states.find(function(s) { return s.id == instance.status_id; });
        var statusBadge = currentStatus ?
            '<span class="hhmgt-status-badge" style="background-color: ' + currentStatus.color_hex + '; color: #fff; padding: 4px 12px; border-radius: 12px; font-size: 12px;">' + escapeHtml(currentStatus.state_name) + '</span>' : '';

        var html = '<div class="hhmgt-modal-header">' +
            '<div class="hhmgt-modal-header-content">' +
            '<h2 style="margin: 0 0 8px 0;">' + escapeHtml(instance.task_name) + '</h2>' +
            statusBadge +
            '</div>' +
            '<button type="button" class="hhmgt-modal-close">' +
            '<span class="dashicons dashicons-no-alt"></span>' +
            '</button>' +
            '</div>' +
            '<div class="hhmgt-modal-body" style="padding: 20px;">';

        // Description
        if (instance.description) {
            html += '<div style="margin-bottom: 20px;">' +
                '<h4 style="margin: 0 0 8px 0;"><?php echo esc_js(__('Description', 'hhmgt')); ?></h4>' +
                '<p style="margin: 0; color: #666;">' + escapeHtml(instance.description) + '</p>' +
                '</div>';
        }

        // Task info
        html += '<div style="margin-bottom: 20px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">' +
            '<div><strong><?php echo esc_js(__('Scheduled:', 'hhmgt')); ?></strong> ' + instance.scheduled_date + '</div>' +
            '<div><strong><?php echo esc_js(__('Due:', 'hhmgt')); ?></strong> ' + instance.due_date + '</div>' +
            '</div>';

        // Checklist
        if (instance.checklist_items && instance.checklist_items.length > 0) {
            html += '<div style="margin-bottom: 20px;">' +
                '<h4 style="margin: 0 0 8px 0;"><?php echo esc_js(__('Checklist', 'hhmgt')); ?></h4>' +
                '<div style="background: #f9f9f9; padding: 10px; border-radius: 4px;">';
            instance.checklist_items.forEach(function(item, index) {
                var checked = instance.checklist_state && instance.checklist_state[index] === true;
                html += '<div style="margin-bottom: 5px;">' +
                    '<span style="color: ' + (checked ? '#46b450' : '#999') + ';">' +
                    (checked ? '&#10003;' : '&#9675;') + '</span> ' +
                    escapeHtml(item) +
                    '</div>';
            });
            html += '</div></div>';
        }

        // Completion photos
        if (instance.completion_photos && instance.completion_photos.length > 0) {
            html += '<div style="margin-bottom: 20px;">' +
                '<h4 style="margin: 0 0 8px 0;"><?php echo esc_js(__('Completion Photos', 'hhmgt')); ?></h4>' +
                '<div style="display: flex; flex-wrap: wrap; gap: 8px;">';
            instance.completion_photos.forEach(function(photo) {
                html += '<div class="hhmgt-completion-photo-thumb" data-full-url="' + photo.full_url + '" style="width: 80px; height: 80px; border-radius: 4px; overflow: hidden; cursor: pointer;">' +
                    '<img src="' + photo.thumb_url + '" style="width: 100%; height: 100%; object-fit: cover;">' +
                    '</div>';
            });
            html += '</div></div>';
        }

        // Notes
        if (notes.length > 0) {
            html += '<div style="margin-bottom: 20px;">' +
                '<h4 style="margin: 0 0 8px 0;"><?php echo esc_js(__('Notes', 'hhmgt')); ?></h4>';
            notes.forEach(function(note) {
                html += '<div style="background: #f9f9f9; padding: 10px; border-radius: 4px; margin-bottom: 8px;">' +
                    '<p style="margin: 0;">' + escapeHtml(note.note_text) + '</p>' +
                    '<small style="color: #999;">' + note.created_at + '</small>' +
                    '</div>';
            });
            html += '</div>';
        }

        // Completion info
        if (instance.completed_at) {
            html += '<div style="margin-bottom: 20px; padding: 10px; background: #e7f5ea; border-radius: 4px;">' +
                '<strong><?php echo esc_js(__('Completed:', 'hhmgt')); ?></strong> ' + instance.completed_at;
            if (instance.completed_by_name) {
                html += ' <?php echo esc_js(__('by', 'hhmgt')); ?> ' + escapeHtml(instance.completed_by_name);
            }
            html += '</div>';
        }

        html += '</div>';

        $('#hhmgt-admin-task-modal .hhmgt-modal-content').html(html);
    }

    // Close modal
    $(document).on('click', '.hhmgt-modal-overlay, .hhmgt-modal-close', function(e) {
        if ($(e.target).hasClass('hhmgt-modal-overlay') || $(e.target).closest('.hhmgt-modal-close').length) {
            $(this).closest('.hhmgt-modal').fadeOut(200);
        }
    });

    // Lightbox
    var lightboxPhotos = [];
    var lightboxIndex = 0;

    $(document).on('click', '.hhmgt-completion-photo-thumb', function() {
        lightboxPhotos = [];
        $('.hhmgt-completion-photo-thumb').each(function() {
            lightboxPhotos.push($(this).data('full-url'));
        });
        lightboxIndex = $('.hhmgt-completion-photo-thumb').index(this);
        updateLightbox();
        $('#hhmgt-lightbox').addClass('active');
    });

    $(document).on('click', '.hhmgt-lightbox-close, #hhmgt-lightbox', function(e) {
        if (e.target === this || $(e.target).closest('.hhmgt-lightbox-close').length) {
            $('#hhmgt-lightbox').removeClass('active');
        }
    });

    $(document).on('click', '.hhmgt-lightbox-content', function(e) {
        e.stopPropagation();
    });

    $(document).on('click', '.hhmgt-lightbox-nav.prev', function(e) {
        e.stopPropagation();
        lightboxIndex = (lightboxIndex - 1 + lightboxPhotos.length) % lightboxPhotos.length;
        updateLightbox();
    });

    $(document).on('click', '.hhmgt-lightbox-nav.next', function(e) {
        e.stopPropagation();
        lightboxIndex = (lightboxIndex + 1) % lightboxPhotos.length;
        updateLightbox();
    });

    function updateLightbox() {
        $('#hhmgt-lightbox .hhmgt-lightbox-image').attr('src', lightboxPhotos[lightboxIndex]);
        $('#hhmgt-lightbox .hhmgt-lightbox-counter').text((lightboxIndex + 1) + ' / ' + lightboxPhotos.length);
        if (lightboxPhotos.length <= 1) {
            $('.hhmgt-lightbox-nav, .hhmgt-lightbox-counter').hide();
        } else {
            $('.hhmgt-lightbox-nav, .hhmgt-lightbox-counter').show();
        }
    }

    // Helper function
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

<style>
.hhmgt-instances-page .hhmgt-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hhmgt-instances-page .hhmgt-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
}

.hhmgt-instances-page .hhmgt-modal-content {
    position: relative;
    background: #fff;
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}

.hhmgt-instances-page .hhmgt-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.hhmgt-instances-page .hhmgt-modal-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
    color: #666;
}

.hhmgt-instances-page .hhmgt-modal-close:hover {
    color: #000;
}

/* Lightbox */
.hhmgt-lightbox {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.9);
    z-index: 100001;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s, visibility 0.3s;
}

.hhmgt-lightbox.active {
    opacity: 1;
    visibility: visible;
}

.hhmgt-lightbox-content {
    position: relative;
    max-width: 90vw;
    max-height: 90vh;
}

.hhmgt-lightbox-image {
    max-width: 90vw;
    max-height: 85vh;
    object-fit: contain;
}

.hhmgt-lightbox-close {
    position: absolute;
    top: -40px;
    right: 0;
    background: transparent;
    border: none;
    color: #fff;
    cursor: pointer;
}

.hhmgt-lightbox-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: #fff;
    cursor: pointer;
    padding: 10px;
    border-radius: 4px;
}

.hhmgt-lightbox-nav.prev { left: -50px; }
.hhmgt-lightbox-nav.next { right: -50px; }

.hhmgt-lightbox-counter {
    position: absolute;
    bottom: -30px;
    left: 50%;
    transform: translateX(-50%);
    color: #fff;
    font-size: 14px;
}
</style>
