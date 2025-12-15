/**
 * Daily List Integration JavaScript
 *
 * Handles task modal opening and department toggle within Daily List context
 */
(function($) {
    'use strict';

    console.log('[HHMGT-DL] Daily List integration JS loaded');

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
        console.log('[HHMGT-DL] Task item clicked', this);
        e.preventDefault();
        e.stopPropagation();

        var instanceId = $(this).data('instance-id');
        console.log('[HHMGT-DL] Instance ID:', instanceId);

        if (!instanceId) {
            console.warn('No instance ID found on task item');
            return;
        }

        // Check if Tasks module's modal function is available
        console.log('[HHMGT-DL] hhmgtOpenTaskModal available:', typeof window.hhmgtOpenTaskModal === 'function');
        if (typeof window.hhmgtOpenTaskModal === 'function') {
            // Use Tasks module's modal function
            console.log('[HHMGT-DL] Calling hhmgtOpenTaskModal');
            window.hhmgtOpenTaskModal(instanceId);
        } else {
            // Try to open in our own modal
            console.log('[HHMGT-DL] Calling openTaskModalLocal');
            openTaskModalLocal(instanceId);
        }
    });

    /**
     * Open task modal locally (when Tasks module modal not available)
     */
    function openTaskModalLocal(instanceId) {
        console.log('[HHMGT-DL] openTaskModalLocal called with:', instanceId);
        var $modal = $('#hhmgt-task-modal');
        var $content = $modal.find('.hhmgt-modal-content');
        console.log('[HHMGT-DL] Modal found:', $modal.length > 0, 'Content found:', $content.length > 0);

        if (!$modal.length) {
            console.warn('[HHMGT-DL] Task modal container not found');
            // Fallback: redirect to Tasks module
            if (typeof hhmgtDLData !== 'undefined' && hhmgtDLData.tasks_url) {
                window.location.href = hhmgtDLData.tasks_url + '&task=' + instanceId;
            }
            return;
        }

        // Show modal with loading state
        $content.html('<div class="hhmgt-modal-loading"><span class="spinner"></span> ' +
            (hhmgtDLData.strings ? hhmgtDLData.strings.loading : 'Loading...') + '</div>');
        $modal.fadeIn(200);

        // Fetch task details via AJAX
        console.log('[HHMGT-DL] AJAX URL:', hhmgtDLData.ajax_url, 'Nonce:', hhmgtDLData.nonce);
        $.ajax({
            url: hhmgtDLData.ajax_url,
            type: 'POST',
            data: {
                action: 'hhmgt_get_task_detail',
                nonce: hhmgtDLData.nonce,
                instance_id: instanceId
            },
            success: function(response) {
                console.log('[HHMGT-DL] AJAX response:', response);
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
        $(this).closest('.hhmgt-modal').fadeOut(200);
    });

    // Close task modal on close button click
    $(document).on('click', '.hhmgt-modal-close', function() {
        $(this).closest('.hhmgt-modal').fadeOut(200);
    });

    // Close task modal on escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#hhmgt-task-modal').is(':visible')) {
            $('#hhmgt-task-modal').fadeOut(200);
        }
    });

})(jQuery);
