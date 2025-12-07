/**
 * Admin JavaScript
 *
 * Handles admin settings interface interactions
 *
 * @package HotelHub_Management_Tasks
 */

(function($) {
    'use strict';

    let itemIndex = 1000; // Start high to avoid conflicts

    /**
     * Initialize admin interface
     */
    function init() {
        initColorPickers();
        initRepeaterToggles();
        initAddButtons();
        initRemoveButtons();
        initSortable();
        initChecklistStartedValidation();
    }

    /**
     * Initialize WordPress color pickers
     */
    function initColorPickers() {
        if ($.fn.wpColorPicker) {
            $('.hhmgt-color-picker').wpColorPicker();

            // Re-initialize color pickers when new items are added
            $(document).on('hhmgt-item-added', function(e, $item) {
                $item.find('.hhmgt-color-picker').wpColorPicker();
            });
        }
    }

    /**
     * Initialize repeater item toggles
     */
    function initRepeaterToggles() {
        $(document).on('click', '.hhmgt-repeater-header', function(e) {
            if ($(e.target).closest('.hhmgt-repeater-toggle').length) {
                // Toggle button clicked
                const $item = $(this).closest('.hhmgt-repeater-item');
                const $content = $item.find('.hhmgt-repeater-content');
                const $toggle = $(this).find('.dashicons');

                $content.slideToggle(200, function() {
                    if ($content.is(':visible')) {
                        $toggle.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                    } else {
                        $toggle.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                    }
                });
            }
        });
    }

    /**
     * Initialize add buttons for repeaters
     */
    function initAddButtons() {
        // Add department
        $('#add-department').on('click', function() {
            addRepeaterItem('department');
        });

        // Add pattern
        $('#add-pattern').on('click', function() {
            addRepeaterItem('pattern');
        });

        // Add state
        $('#add-state').on('click', function() {
            addRepeaterItem('state');
        });
    }

    /**
     * Add a new repeater item
     */
    function addRepeaterItem(type) {
        const templateId = type + '-template';
        const listId = type + 's-list';

        const template = $('#' + templateId).html();
        if (!template) {
            console.error('Template not found: ' + templateId);
            return;
        }

        // Replace {{index}} placeholder with actual index
        const html = template.replace(/\{\{index\}\}/g, itemIndex);

        // Add to list
        const $item = $(html);
        $('#' + listId).append($item);

        // Open the new item
        $item.find('.hhmgt-repeater-content').show();
        $item.find('.hhmgt-repeater-toggle .dashicons')
            .removeClass('dashicons-arrow-down-alt2')
            .addClass('dashicons-arrow-up-alt2');

        // Trigger event for other scripts
        $(document).trigger('hhmgt-item-added', [$item]);

        // Increment index
        itemIndex++;

        // Scroll to new item
        $('html, body').animate({
            scrollTop: $item.offset().top - 100
        }, 300);
    }

    /**
     * Initialize remove buttons
     */
    function initRemoveButtons() {
        $(document).on('click', '.hhmgt-remove-item', function(e) {
            e.preventDefault();

            if (!confirm(hhmgtAdmin.strings.confirmDelete)) {
                return;
            }

            const $item = $(this).closest('.hhmgt-repeater-item');
            $item.slideUp(200, function() {
                $item.remove();
                updateSortOrders();
            });
        });
    }

    /**
     * Initialize sortable for repeater items
     */
    function initSortable() {
        if ($.fn.sortable) {
            $('.hhmgt-repeater-list').sortable({
                handle: '.hhmgt-drag-handle',
                axis: 'y',
                opacity: 0.7,
                cursor: 'move',
                placeholder: 'hhmgt-sortable-placeholder',
                start: function(e, ui) {
                    ui.placeholder.height(ui.item.height());
                },
                update: function() {
                    updateSortOrders();
                }
            });
        }
    }

    /**
     * Update sort order values
     */
    function updateSortOrders() {
        $('.hhmgt-repeater-list').each(function() {
            $(this).find('.hhmgt-repeater-item').each(function(index) {
                $(this).find('.hhmgt-sort-order').val(index);
            });
        });
    }

    /**
     * Show loading indicator
     */
    function showLoading() {
        const $submitButton = $('#submit');
        $submitButton.prop('disabled', true);
        $submitButton.after('<span class="spinner is-active" style="float: none; margin: 0 10px;"></span>');
    }

    /**
     * Hide loading indicator
     */
    function hideLoading() {
        const $submitButton = $('#submit');
        $submitButton.prop('disabled', false);
        $('.spinner').remove();
    }

    /**
     * Form submission handling
     */
    function initFormHandling() {
        $('.hhmgt-settings-form').on('submit', function() {
            showLoading();
        });
    }

    /**
     * Initialize checklist started checkbox validation
     * Only allow one state to have this option enabled
     */
    function initChecklistStartedValidation() {
        $(document).on('change', '.hhmgt-checklist-started-checkbox', function() {
            const $checkbox = $(this);

            if ($checkbox.is(':checked')) {
                // Uncheck all other checklist started checkboxes
                $('.hhmgt-checklist-started-checkbox').not($checkbox).prop('checked', false);
            }
        });

        // Also disable the checkbox if "Marks task as completed" is checked
        $(document).on('change', 'input[name*="[is_complete_state]"]', function() {
            const $completeCheckbox = $(this);
            const $container = $completeCheckbox.closest('.hhmgt-repeater-item');
            const $checklistStartedCheckbox = $container.find('.hhmgt-checklist-started-checkbox');

            if ($completeCheckbox.is(':checked')) {
                $checklistStartedCheckbox.prop('checked', false).prop('disabled', true);
            } else {
                $checklistStartedCheckbox.prop('disabled', false);
            }
        });
    }

    // Initialize when document is ready
    $(document).ready(function() {
        init();
        initFormHandling();
    });

})(jQuery);
