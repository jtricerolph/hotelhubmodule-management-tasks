/**
 * Daily List Integration JavaScript
 *
 * Handles task click delegation and department toggle within Daily List context.
 * Modal functionality is handled by the main Tasks module (hhmgt.js).
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

        // Use the main Tasks module's modal function (exposed globally)
        if (typeof window.hhmgtOpenTaskModal === 'function') {
            window.hhmgtOpenTaskModal(instanceId);
        } else {
            // Fallback: redirect to Tasks module if modal function not available
            if (typeof hhmgtDLData !== 'undefined' && hhmgtDLData.tasks_url) {
                window.location.href = hhmgtDLData.tasks_url + '&task=' + instanceId;
            }
        }
    });

})(jQuery);
