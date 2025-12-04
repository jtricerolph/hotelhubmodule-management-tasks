/**
 * Material Symbols Icon Picker Component
 *
 * Searchable icon picker for Material Symbols Outlined
 *
 * @package HotelHub_Management_Tasks
 */

(function($) {
    'use strict';

    let iconsData = [];
    let currentButton = null;
    let currentInput = null;
    let currentPreview = null;

    /**
     * Initialize icon picker
     */
    function initIconPicker() {
        // Load icons data
        loadIcons();

        // Handle icon picker button clicks
        $(document).on('click', '.hhmgt-icon-picker-button', function(e) {
            e.preventDefault();
            openIconPicker($(this));
        });

        // Close picker when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.hhmgt-icon-picker-modal, .hhmgt-icon-picker-button').length) {
                closeIconPicker();
            }
        });
    }

    /**
     * Load icons from JSON file or AJAX
     */
    function loadIcons() {
        if (iconsData.length > 0) {
            return; // Already loaded
        }

        // Try AJAX request first
        $.ajax({
            url: hhmgtAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'hhmgt_get_material_symbols',
                nonce: hhmgtAdmin.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    iconsData = response.data;
                } else {
                    // Fallback to common icons
                    iconsData = getCommonIcons();
                }
            },
            error: function() {
                // Fallback to common icons
                iconsData = getCommonIcons();
            }
        });
    }

    /**
     * Open icon picker modal
     */
    function openIconPicker($button) {
        currentButton = $button;
        currentInput = $button.siblings('.hhmgt-icon-value');
        currentPreview = $button.find('.hhmgt-icon-preview');

        const currentIcon = currentInput.val() || 'assignment_turned_in';

        // Create modal if it doesn't exist
        if ($('#hhmgt-icon-picker-modal').length === 0) {
            createIconPickerModal();
        }

        const $modal = $('#hhmgt-icon-picker-modal');
        const $search = $modal.find('.hhmgt-icon-search');
        const $category = $modal.find('.hhmgt-icon-category-filter');

        // Reset search and category
        $search.val('');
        $category.val('');

        // Render all icons
        renderIcons('', '');

        // Highlight current icon
        $modal.find('.hhmgt-icon-item').removeClass('selected');
        $modal.find(`.hhmgt-icon-item[data-icon="${currentIcon}"]`).addClass('selected');

        // Show modal
        $modal.fadeIn(200);
        $search.focus();
    }

    /**
     * Close icon picker modal
     */
    function closeIconPicker() {
        $('#hhmgt-icon-picker-modal').fadeOut(200);
    }

    /**
     * Create icon picker modal HTML
     */
    function createIconPickerModal() {
        const modalHTML = `
            <div id="hhmgt-icon-picker-modal" class="hhmgt-icon-picker-modal" style="display: none;">
                <div class="hhmgt-icon-picker-overlay"></div>
                <div class="hhmgt-icon-picker-content">
                    <div class="hhmgt-icon-picker-header">
                        <h3>Choose Icon</h3>
                        <button type="button" class="hhmgt-icon-picker-close">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>

                    <div class="hhmgt-icon-picker-filters">
                        <input type="text"
                               class="hhmgt-icon-search"
                               placeholder="Search icons..."
                               autocomplete="off">
                        <select class="hhmgt-icon-category-filter">
                            <option value="">All Categories</option>
                        </select>
                    </div>

                    <div class="hhmgt-icon-picker-grid" id="icon-picker-grid">
                        <!-- Icons rendered here -->
                    </div>

                    <div class="hhmgt-icon-picker-footer">
                        <button type="button" class="button button-secondary hhmgt-icon-picker-cancel">Cancel</button>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHTML);

        // Populate categories
        populateCategories();

        // Handle search
        $(document).on('input', '.hhmgt-icon-search', function() {
            const searchTerm = $(this).val();
            const category = $('.hhmgt-icon-category-filter').val();
            renderIcons(searchTerm, category);
        });

        // Handle category filter
        $(document).on('change', '.hhmgt-icon-category-filter', function() {
            const searchTerm = $('.hhmgt-icon-search').val();
            const category = $(this).val();
            renderIcons(searchTerm, category);
        });

        // Handle icon selection
        $(document).on('click', '.hhmgt-icon-item', function() {
            const iconName = $(this).data('icon');
            selectIcon(iconName);
        });

        // Handle close buttons
        $(document).on('click', '.hhmgt-icon-picker-close, .hhmgt-icon-picker-cancel', function(e) {
            e.preventDefault();
            closeIconPicker();
        });
    }

    /**
     * Populate category dropdown
     */
    function populateCategories() {
        const categories = [...new Set(iconsData.map(icon => icon.category))].sort();
        const $select = $('.hhmgt-icon-category-filter');

        categories.forEach(category => {
            $select.append(`<option value="${category}">${category}</option>`);
        });
    }

    /**
     * Render icons in grid
     */
    function renderIcons(searchTerm, category) {
        const $grid = $('#icon-picker-grid');
        $grid.empty();

        // Filter icons
        let filteredIcons = iconsData;

        if (searchTerm) {
            const search = searchTerm.toLowerCase();
            filteredIcons = filteredIcons.filter(icon =>
                icon.name.toLowerCase().includes(search) ||
                (icon.description && icon.description.toLowerCase().includes(search)) ||
                (icon.category && icon.category.toLowerCase().includes(search))
            );
        }

        if (category) {
            filteredIcons = filteredIcons.filter(icon => icon.category === category);
        }

        // Render icons
        if (filteredIcons.length === 0) {
            $grid.html('<p class="hhmgt-no-icons">No icons found</p>');
            return;
        }

        filteredIcons.forEach(icon => {
            const $item = $(`
                <div class="hhmgt-icon-item" data-icon="${icon.name}" title="${icon.description || icon.name}">
                    <span class="material-symbols-outlined">${icon.name}</span>
                    <span class="hhmgt-icon-name">${icon.name.replace(/_/g, ' ')}</span>
                </div>
            `);
            $grid.append($item);
        });
    }

    /**
     * Select an icon
     */
    function selectIcon(iconName) {
        if (currentInput && currentPreview) {
            // Update input value
            currentInput.val(iconName);

            // Update preview
            currentPreview.text(iconName);

            // Update header preview if in department item
            const $repeaterItem = currentButton.closest('.hhmgt-repeater-item');
            if ($repeaterItem.length) {
                $repeaterItem.find('.hhmgt-dept-icon-preview').text(iconName);
            }

            // Update name in header
            updateDepartmentHeaderName();
        }

        closeIconPicker();
    }

    /**
     * Update department header name when name field changes
     */
    function updateDepartmentHeaderName() {
        if (!currentButton) return;

        const $repeaterItem = currentButton.closest('.hhmgt-repeater-item');
        if ($repeaterItem.length) {
            const deptName = $repeaterItem.find('.hhmgt-dept-name').val() || 'Unnamed Department';
            $repeaterItem.find('.hhmgt-repeater-title strong').text(deptName);
        }
    }

    /**
     * Get common icons as fallback
     */
    function getCommonIcons() {
        return [
            {name: 'assignment_turned_in', category: 'Tasks', description: 'Completed assignment'},
            {name: 'fact_check', category: 'Tasks', description: 'Checklist'},
            {name: 'task_alt', category: 'Tasks', description: 'Task complete'},
            {name: 'check_circle', category: 'Tasks', description: 'Check mark'},
            {name: 'schedule', category: 'Tasks', description: 'Schedule'},
            {name: 'cleaning_services', category: 'Housekeeping', description: 'Cleaning services'},
            {name: 'dry_cleaning', category: 'Housekeeping', description: 'Dry cleaning'},
            {name: 'local_laundry_service', category: 'Housekeeping', description: 'Laundry'},
            {name: 'bedtime', category: 'Hotel', description: 'Bedtime'},
            {name: 'king_bed', category: 'Hotel', description: 'King bed'},
            {name: 'hotel', category: 'Hotel', description: 'Hotel'},
            {name: 'restaurant', category: 'Food & Beverage', description: 'Restaurant'},
            {name: 'coffee', category: 'Food & Beverage', description: 'Coffee'},
            {name: 'plumbing', category: 'Maintenance', description: 'Plumbing'},
            {name: 'electrical_services', category: 'Maintenance', description: 'Electrical'},
            {name: 'hvac', category: 'Maintenance', description: 'HVAC'},
            {name: 'build', category: 'Maintenance', description: 'Construction'},
            {name: 'pool', category: 'Amenities', description: 'Swimming pool'},
            {name: 'fitness_center', category: 'Amenities', description: 'Fitness center'},
            {name: 'wifi', category: 'Technology', description: 'WiFi'},
            {name: 'fire_extinguisher', category: 'Safety', description: 'Fire extinguisher'},
            {name: 'emergency', category: 'Safety', description: 'Emergency'},
            {name: 'security', category: 'Safety', description: 'Security'}
        ];
    }

    // Initialize when document is ready
    $(document).ready(function() {
        initIconPicker();

        // Update department header names when names change
        $(document).on('input', '.hhmgt-dept-name', function() {
            const $repeaterItem = $(this).closest('.hhmgt-repeater-item');
            const deptName = $(this).val() || 'Unnamed Department';
            $repeaterItem.find('.hhmgt-repeater-title strong').text(deptName);
        });
    });

})(jQuery);
