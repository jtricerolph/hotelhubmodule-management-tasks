/**
 * Daily List Integration JavaScript
 *
 * Handles task click delegation and department toggle within Daily List context.
 * Modal functionality is handled by the main Tasks module (hhmgt.js).
 */
(function($) {
    'use strict';

    // Track the current room context for refreshing
    var currentRoomContext = null;

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

        // Store room context for later refresh
        var $section = $(this).closest('.hhmgt-tasks-section');
        if ($section.length) {
            currentRoomContext = {
                roomNumber: $section.data('room-number'),
                locationId: $section.data('location-id'),
                date: $section.data('date'),
                $section: $section
            };
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

    // Listen for task modified events from main Tasks module
    $(document).on('hhmgt:task-modified', function(e, data) {
        console.log('[HHMGT-DL] Task modified event received:', data);

        if (!currentRoomContext) {
            console.log('[HHMGT-DL] No room context available, skipping refresh');
            return;
        }

        // Refresh the tasks section in the room modal
        refreshTasksSection();
    });

    /**
     * Refresh the tasks section in the current room modal
     */
    function refreshTasksSection() {
        if (!currentRoomContext) return;

        var ctx = currentRoomContext;
        console.log('[HHMGT-DL] Refreshing tasks section for room:', ctx.roomNumber);

        $.ajax({
            url: hhmgtData.ajax_url,
            type: 'POST',
            data: {
                action: 'hhmgt_refresh_room_tasks',
                nonce: hhmgtData.nonce,
                location_id: ctx.locationId,
                room_number: ctx.roomNumber,
                date: ctx.date
            },
            success: function(response) {
                if (response.success) {
                    // Replace the tasks section HTML
                    if (ctx.$section && ctx.$section.length) {
                        if (response.data.html) {
                            ctx.$section.replaceWith(response.data.html);
                            // Update reference to new section
                            currentRoomContext.$section = $('.hhmgt-tasks-section[data-room-number="' + ctx.roomNumber + '"]');
                        } else {
                            // No tasks left, remove the section
                            ctx.$section.remove();
                            currentRoomContext.$section = null;
                        }
                    }

                    // Update room card task count badge
                    updateRoomCardTaskCount(ctx.roomNumber, response.data.count);
                } else {
                    console.error('[HHMGT-DL] Failed to refresh tasks:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('[HHMGT-DL] AJAX error refreshing tasks:', error);
            }
        });
    }

    /**
     * Update the task count badge on the room card
     */
    function updateRoomCardTaskCount(roomNumber, counts) {
        if (!counts) return;

        // Find the room card by room number
        var $card = $('.hhdl-room-card[data-room-number="' + roomNumber + '"]');
        if (!$card.length) {
            console.log('[HHMGT-DL] Room card not found for:', roomNumber);
            return;
        }

        // Find the tasks stat block (4th block with checklist icon)
        var $taskBlock = $card.find('.hhdl-stat-block').filter(function() {
            return $(this).find('.material-symbols-outlined').text().trim() === 'checklist_rtl';
        });

        if (!$taskBlock.length) {
            console.log('[HHMGT-DL] Task stat block not found in card');
            return;
        }

        var $content = $taskBlock.find('.hhdl-stat-content');
        var $badge = $taskBlock.find('.hhdl-task-count-badge');

        // Update count badge
        if (counts.total > 0) {
            if ($badge.length) {
                $badge.text(counts.total);
            } else {
                $taskBlock.find('.hhdl-task-count').append('<span class="hhdl-task-count-badge">' + counts.total + '</span>');
            }
        } else {
            $badge.remove();
        }

        // Update status class
        $content.removeClass('hhdl-tasks-overdue hhdl-tasks-due hhdl-tasks-pending hhdl-tasks-none');

        if (counts.total === 0) {
            $content.addClass('hhdl-tasks-none');
            $content.attr('title', 'No tasks');
        } else if (counts.overdue > 0) {
            $content.addClass('hhdl-tasks-overdue');
            $content.attr('title', counts.overdue + ' overdue tasks');
        } else if (counts.due_today > 0) {
            $content.addClass('hhdl-tasks-due');
            $content.attr('title', counts.due_today + ' tasks due today');
        } else {
            $content.addClass('hhdl-tasks-pending');
            $content.attr('title', counts.total + ' pending tasks');
        }

        console.log('[HHMGT-DL] Updated room card task count:', roomNumber, counts);
    }

})(jQuery);
