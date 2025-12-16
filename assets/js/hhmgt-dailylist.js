/**
 * Daily List Integration JavaScript
 *
 * Handles task modal opening and department toggle within Daily List context
 */
(function($) {
    'use strict';

    // Handle "Show all departments" toggle
    $(document).on('change', '.hhmgt-show-all-depts', function() {
        var $section = $(this).closest('.hhmgt-tasks-section');
        var $otherTasks = $section.find('.hhmgt-tasks-other');

        if ($(this).is(':checked')) {
            $otherTasks.slideDown(200);
        } else {
            $otherTasks.slideUp(200);
        }
    });

    // Delegate click on task items within Daily List modal
    $(document).on('click', '.hhmgt-task-item', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var instanceId = $(this).data('instance-id');
        if (!instanceId) {
            return;
        }

        // Check if Tasks module's modal element exists on this page
        // The main Tasks module uses #task-modal, which only exists on the Tasks page
        // On Daily List, we need to use our own #hhmgt-task-modal
        var tasksModuleModalExists = $('#task-modal').length > 0;

        if (tasksModuleModalExists && typeof window.hhmgtOpenTaskModal === 'function' && typeof window.hhmgtData !== 'undefined') {
            // Use Tasks module's modal function (only when on Tasks module page)
            window.hhmgtOpenTaskModal(instanceId);
        } else {
            // Use our own modal (on Daily List page)
            openTaskModalLocal(instanceId);
        }
    });

    /**
     * Open task modal locally (when Tasks module modal not available)
     */
    function openTaskModalLocal(instanceId) {
        var $modal = $('#hhmgt-task-modal');
        var $content = $modal.find('.hhmgt-modal-content');

        if (!$modal.length) {
            // Fallback: redirect to Tasks module
            if (typeof hhmgtDLData !== 'undefined' && hhmgtDLData.tasks_url) {
                window.location.href = hhmgtDLData.tasks_url + '&task=' + instanceId;
            }
            return;
        }

        // Show modal with loading state
        $content.html('<div class="hhmgt-modal-loading"><span class="spinner"></span> ' +
            (hhmgtDLData.strings ? hhmgtDLData.strings.loading : 'Loading...') + '</div>');

        // Force display with !important to override PWA body CSS reset
        $modal.addClass('active');
        $modal[0].style.setProperty('display', 'flex', 'important');
        $modal[0].style.setProperty('opacity', '1', 'important');
        $modal[0].style.setProperty('pointer-events', 'auto', 'important');

        // Fetch task details via AJAX
        $.ajax({
            url: hhmgtDLData.ajax_url,
            type: 'POST',
            data: {
                action: 'hhmgt_get_task_detail',
                nonce: hhmgtDLData.nonce,
                instance_id: instanceId
            },
            success: function(response) {
                if (response.success && response.data) {
                    renderTaskDetail(response.data, $content);
                } else {
                    $content.html('<div class="hhmgt-error">' +
                        (response.data || hhmgtDLData.strings.error) + '</div>');
                }
            },
            error: function() {
                $content.html('<div class="hhmgt-error">' +
                    (hhmgtDLData.strings ? hhmgtDLData.strings.error : 'Error loading task') + '</div>');
            }
        });
    }

    /**
     * Close task modal
     */
    function closeTaskModal() {
        var $modal = $('#hhmgt-task-modal');
        if ($modal.length) {
            $modal.removeClass('active');
            $modal[0].style.setProperty('opacity', '0', 'important');
            $modal[0].style.setProperty('pointer-events', 'none', 'important');
            // After transition, hide completely
            setTimeout(function() {
                $modal[0].style.setProperty('display', 'none', 'important');
            }, 200);
        }
    }

    /**
     * Render task detail in modal
     */
    function renderTaskDetail(data, $container) {
        var instance = data.instance;
        var notes = data.notes || [];

        var html = '<div class="hhmgt-task-detail">';

        // Task header
        html += '<div class="hhmgt-detail-header">';
        html += '<h3>' + escapeHtml(instance.task_name) + '</h3>';
        if (instance.location_path) {
            html += '<p class="hhmgt-detail-location">' + escapeHtml(instance.location_path) + '</p>';
        }
        html += '</div>';

        // Status and dates
        html += '<div class="hhmgt-detail-info">';
        html += '<div class="hhmgt-detail-row">';
        html += '<span class="hhmgt-detail-label">Status:</span>';
        html += '<span class="hhmgt-detail-value">' + escapeHtml(instance.state_name || 'Unknown') + '</span>';
        html += '</div>';
        html += '<div class="hhmgt-detail-row">';
        html += '<span class="hhmgt-detail-label">Due Date:</span>';
        html += '<span class="hhmgt-detail-value">' + escapeHtml(instance.due_date) + '</span>';
        html += '</div>';
        if (instance.dept_name) {
            html += '<div class="hhmgt-detail-row">';
            html += '<span class="hhmgt-detail-label">Department:</span>';
            html += '<span class="hhmgt-detail-value">' + escapeHtml(instance.dept_name) + '</span>';
            html += '</div>';
        }
        html += '</div>';

        // Description
        if (instance.description) {
            html += '<div class="hhmgt-detail-description">';
            html += '<h4>Description</h4>';
            html += '<p>' + escapeHtml(instance.description) + '</p>';
            html += '</div>';
        }

        // Notes
        if (notes.length > 0) {
            html += '<div class="hhmgt-detail-notes">';
            html += '<h4>Notes (' + notes.length + ')</h4>';
            notes.forEach(function(note) {
                html += '<div class="hhmgt-note-item">';
                html += '<p>' + escapeHtml(note.note_text) + '</p>';
                html += '<span class="hhmgt-note-meta">' + escapeHtml(note.created_at) + '</span>';
                html += '</div>';
            });
            html += '</div>';
        }

        // Action link to Tasks module
        if (typeof hhmgtDLData !== 'undefined' && hhmgtDLData.tasks_url) {
            html += '<div class="hhmgt-detail-actions">';
            html += '<a href="' + hhmgtDLData.tasks_url + '&task=' + instance.id + '" class="hhmgt-btn hhmgt-btn-primary">';
            html += 'Open in Tasks Module';
            html += '</a>';
            html += '</div>';
        }

        html += '</div>';

        $container.html(html);
    }

    /**
     * Escape HTML entities
     */
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Close task modal on overlay click
    $(document).on('click', '.hhmgt-modal-overlay', function() {
        closeTaskModal();
    });

    // Close task modal on close button click
    $(document).on('click', '.hhmgt-modal-close', function() {
        closeTaskModal();
    });

    // Close task modal on escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#hhmgt-task-modal').hasClass('active')) {
            closeTaskModal();
        }
    });

})(jQuery);
