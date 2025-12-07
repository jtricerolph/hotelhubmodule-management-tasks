/**
 * Frontend Module JavaScript
 *
 * Handles task list, filters, modals, and interactions
 *
 * @package HotelHub_Management_Tasks
 */

(function($) {
    'use strict';

    // State management
    let currentState = {
        location_id: null,
        filters: {
            include_future: true,
            department: [], // Multi-select
            status: [], // Multi-select
            location_type: [], // Multi-select
            location: [], // Multi-select
            show_completed: false,
            search: '',
            group_by: ''
        },
        tasks: [],
        currentTask: null
    };

    // localStorage persistence key
    const STORAGE_KEY_PREFIX = 'hhmgt_filters_';
    const DEBUG = true; // Enable console logging

    /**
     * Debug log helper
     */
    function debugLog(message, data) {
        if (DEBUG) {
            console.log('[HHMGT] ' + message, data || '');
        }
    }

    /**
     * Initialize module
     */
    function initModule() {
        debugLog('Initializing module...');

        // Get location from container
        const $container = $('.hhmgt-container');
        if ($container.length) {
            currentState.location_id = $container.data('location');
        }

        debugLog('Location ID:', currentState.location_id);

        if (!currentState.location_id) {
            showError('No location selected');
            return;
        }

        // Initialize components
        initFilters();
        loadSavedFilters(); // Load from localStorage
        initEventHandlers();
        initHeartbeat();

        // Load initial data
        loadLocationTypes();
        loadTasks();
    }

    /**
     * Initialize filters
     */
    function initFilters() {
        // Filters toggle
        $('#filters-toggle').on('click', function() {
            const $content = $('#filters-content');
            const $icon = $('.hhmgt-toggle-icon');

            $content.slideToggle(200, function() {
                if ($content.is(':visible')) {
                    $icon.text('expand_less');
                } else {
                    $icon.text('expand_more');
                }
            });
        });

        // Initialize custom multi-select
        initMultiSelect();
    }

    /**
     * Initialize custom multi-select dropdowns
     */
    function initMultiSelect() {
        // Toggle dropdown on button click
        $(document).on('click', '.hhmgt-multiselect-button', function(e) {
            e.stopPropagation();
            const $multiselect = $(this).closest('.hhmgt-multiselect');
            const wasOpen = $multiselect.hasClass('open');

            // Close all other dropdowns
            $('.hhmgt-multiselect').removeClass('open');

            // Toggle this dropdown
            if (!wasOpen) {
                $multiselect.addClass('open');
            }
        });

        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.hhmgt-multiselect').length) {
                $('.hhmgt-multiselect').removeClass('open');
            }
        });

        // Prevent dropdown from closing when clicking inside
        $(document).on('click', '.hhmgt-multiselect-dropdown', function(e) {
            e.stopPropagation();
        });

        // Handle checkbox change
        $(document).on('change', '.hhmgt-multiselect-option input[type="checkbox"]', function() {
            const $multiselect = $(this).closest('.hhmgt-multiselect');
            updateMultiSelectLabel($multiselect);

            // Trigger location load when location type changes
            if ($multiselect.data('filter') === 'location_type') {
                loadLocations(getMultiSelectValues($multiselect));
            }
        });

        // Handle Select All
        $(document).on('click', '.hhmgt-multiselect-action[data-action="select-all"]', function(e) {
            e.preventDefault();
            const $multiselect = $(this).closest('.hhmgt-multiselect');
            $multiselect.find('.hhmgt-multiselect-option input[type="checkbox"]').prop('checked', true);
            updateMultiSelectLabel($multiselect);

            // Trigger location load when location type changes
            if ($multiselect.data('filter') === 'location_type') {
                loadLocations(getMultiSelectValues($multiselect));
            }
        });

        // Handle Clear All
        $(document).on('click', '.hhmgt-multiselect-action[data-action="clear-all"]', function(e) {
            e.preventDefault();
            const $multiselect = $(this).closest('.hhmgt-multiselect');
            $multiselect.find('.hhmgt-multiselect-option input[type="checkbox"]').prop('checked', false);
            updateMultiSelectLabel($multiselect);

            // Trigger location load when location type changes
            if ($multiselect.data('filter') === 'location_type') {
                loadLocations(getMultiSelectValues($multiselect));
            }
        });
    }

    /**
     * Update multi-select label to show selected count
     */
    function updateMultiSelectLabel($multiselect) {
        const filterName = $multiselect.data('filter');
        const $label = $multiselect.find('.hhmgt-multiselect-label');
        const checked = $multiselect.find('.hhmgt-multiselect-option input[type="checkbox"]:checked');
        const total = $multiselect.find('.hhmgt-multiselect-option input[type="checkbox"]').length;

        let labelText = '';

        if (checked.length === 0) {
            switch(filterName) {
                case 'department': labelText = 'All Departments'; break;
                case 'status': labelText = 'All Statuses'; break;
                case 'location_type': labelText = 'All Types'; break;
                case 'location': labelText = 'All Locations'; break;
            }
        } else if (checked.length === total) {
            switch(filterName) {
                case 'department': labelText = 'All Departments'; break;
                case 'status': labelText = 'All Statuses'; break;
                case 'location_type': labelText = 'All Types'; break;
                case 'location': labelText = 'All Locations'; break;
            }
        } else if (checked.length === 1) {
            labelText = checked.first().next('span').text();
        } else {
            labelText = `${checked.length} selected`;
        }

        $label.text(labelText);
    }

    /**
     * Get selected values from multi-select
     */
    function getMultiSelectValues($multiselect) {
        const values = [];
        $multiselect.find('.hhmgt-multiselect-option input[type="checkbox"]:checked').each(function() {
            values.push($(this).val());
        });
        return values;
    }

    /**
     * Set selected values for multi-select
     */
    function setMultiSelectValues($multiselect, values) {
        if (!values || values.length === 0) {
            $multiselect.find('.hhmgt-multiselect-option input[type="checkbox"]').prop('checked', false);
        } else {
            $multiselect.find('.hhmgt-multiselect-option input[type="checkbox"]').each(function() {
                $(this).prop('checked', values.includes($(this).val()));
            });
        }
        updateMultiSelectLabel($multiselect);
    }

    /**
     * Load saved filters from localStorage
     */
    function loadSavedFilters() {
        const storageKey = STORAGE_KEY_PREFIX + currentState.location_id;
        const saved = localStorage.getItem(storageKey);

        debugLog('Loading saved filters from localStorage...', storageKey);

        if (saved) {
            try {
                const filters = JSON.parse(saved);
                debugLog('Saved filters found:', filters);

                // Restore filters (handle both array and single values for backwards compatibility)
                if (filters.department) {
                    const values = Array.isArray(filters.department) ? filters.department : [filters.department];
                    currentState.filters.department = values;
                    setMultiSelectValues($('.hhmgt-multiselect[data-filter="department"]'), values);
                }

                if (filters.status) {
                    const values = Array.isArray(filters.status) ? filters.status : [filters.status];
                    currentState.filters.status = values;
                    setMultiSelectValues($('.hhmgt-multiselect[data-filter="status"]'), values);
                }

                if (filters.location_type) {
                    const values = Array.isArray(filters.location_type) ? filters.location_type : [filters.location_type];
                    currentState.filters.location_type = values;
                    // Will be set after location types are loaded
                }

                if (filters.location) {
                    const values = Array.isArray(filters.location) ? filters.location : [filters.location];
                    currentState.filters.location = values;
                    // Will be set after locations are loaded
                }

                if (filters.group_by) {
                    currentState.filters.group_by = filters.group_by;
                    $('#filter-group-by').val(filters.group_by);
                }

                if (filters.include_future !== undefined) {
                    currentState.filters.include_future = filters.include_future;
                    $('#filter-include-future').prop('checked', filters.include_future);
                }

                if (filters.show_completed !== undefined) {
                    currentState.filters.show_completed = filters.show_completed;
                    $('#filter-show-completed').prop('checked', filters.show_completed);
                }

                debugLog('Filters restored from localStorage');
            } catch (e) {
                console.error('[HHMGT] Failed to load saved filters:', e);
            }
        } else {
            debugLog('No saved filters found');
        }
    }

    /**
     * Save filters to localStorage
     */
    function saveFilters() {
        const storageKey = STORAGE_KEY_PREFIX + currentState.location_id;

        // Only save specific filters
        const filtersToSave = {
            department: currentState.filters.department,
            status: currentState.filters.status,
            location_type: currentState.filters.location_type,
            location: currentState.filters.location,
            group_by: currentState.filters.group_by,
            include_future: currentState.filters.include_future,
            show_completed: currentState.filters.show_completed
        };

        try {
            localStorage.setItem(storageKey, JSON.stringify(filtersToSave));
            debugLog('Filters saved to localStorage:', filtersToSave);
        } catch (e) {
            console.error('[HHMGT] Failed to save filters:', e);
        }
    }

    /**
     * Initialize event handlers
     */
    function initEventHandlers() {
        // Apply filters
        $('#apply-filters').on('click', applyFilters);

        // Filter on Enter key
        $('.hhmgt-filter-input, .hhmgt-filter-select').on('keypress', function(e) {
            if (e.which === 13) {
                applyFilters();
            }
        });

        // Location type change - load locations
        $('#filter-location-type').on('change', function() {
            const locationType = $(this).val();
            loadLocations(locationType);
        });

        // Task card click
        $(document).on('click', '.hhmgt-task-card', function() {
            const instanceId = $(this).data('instance-id');
            openTaskModal(instanceId);
        });

        // Close modals
        $(document).on('click', '.hhmgt-modal-overlay, .hhmgt-modal-close', function(e) {
            if ($(e.target).hasClass('hhmgt-modal-overlay') || $(e.target).closest('.hhmgt-modal-close').length) {
                closeModals();
            }
        });

        // Update status button click
        $(document).on('click', '#update-status-btn', function() {
            openStatusSelectionModal();
        });

        // Complete task button click
        $(document).on('click', '#complete-task-btn', function() {
            openCompletionModal();
        });

        // Status option selection
        $(document).on('click', '.hhmgt-status-option', function() {
            const statusId = $(this).data('status-id');
            updateTaskStatus(statusId);
        });

        // Checklist change
        $(document).on('change', '.hhmgt-checklist-checkbox', function() {
            updateChecklist();
        });

        // Add note
        $(document).on('click', '#add-note-btn', function() {
            addNote();
        });

        // Toggle note carry-forward
        $(document).on('change', '.hhmgt-note-carry-forward-checkbox', function() {
            const noteId = $(this).data('note-id');
            const carryForward = $(this).is(':checked');
            updateNoteCarryForward(noteId, carryForward);
        });

        // Complete task
        $(document).on('click', '#confirm-complete-btn', function() {
            completeTask();
        });

        // Cancel completion
        $(document).on('click', '#cancel-complete-btn', function() {
            closeCompletionModal();
        });
    }

    /**
     * Apply filters and reload tasks
     */
    function applyFilters() {
        currentState.filters = {
            include_future: $('#filter-include-future').is(':checked'),
            department: getMultiSelectValues($('.hhmgt-multiselect[data-filter="department"]')),
            status: getMultiSelectValues($('.hhmgt-multiselect[data-filter="status"]')),
            location_type: getMultiSelectValues($('.hhmgt-multiselect[data-filter="location_type"]')),
            location: getMultiSelectValues($('.hhmgt-multiselect[data-filter="location"]')),
            show_completed: $('#filter-show-completed').is(':checked'),
            search: $('#filter-search').val(),
            group_by: $('#filter-group-by').val()
        };

        debugLog('Applying filters:', currentState.filters);

        // Save to localStorage
        saveFilters();

        loadTasks();
    }

    /**
     * Load location types
     */
    function loadLocationTypes() {
        debugLog('Loading location types...');

        $.ajax({
            url: hhmgtData.ajax_url,
            type: 'POST',
            data: {
                action: 'hhmgt_get_location_types',
                nonce: hhmgtData.nonce,
                location_id: currentState.location_id
            },
            success: function(response) {
                debugLog('Location types response:', response);

                if (response.success && response.data.types) {
                    const $multiselect = $('.hhmgt-multiselect[data-filter="location_type"]');
                    const $options = $multiselect.find('.hhmgt-multiselect-options');

                    if (response.data.types.length === 0) {
                        debugLog('Warning: No location types found');
                        $options.html('<p style="padding: 8px; color: #dc2626; font-size: 12px;">No location types configured</p>');
                    } else {
                        // Clear existing options
                        $options.empty();

                        // Add checkboxes for each type
                        response.data.types.forEach(function(type) {
                            $options.append(`
                                <label class="hhmgt-multiselect-option">
                                    <input type="checkbox" value="${type}">
                                    <span>${type}</span>
                                </label>
                            `);
                        });
                        debugLog('Location types loaded:', response.data.types.length);

                        // Restore saved location type after options are added
                        if (currentState.filters.location_type && currentState.filters.location_type.length > 0) {
                            setMultiSelectValues($multiselect, currentState.filters.location_type);
                            loadLocations(currentState.filters.location_type);
                        }
                    }
                } else {
                    console.error('[HHMGT] Failed to load location types:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('[HHMGT] Location types AJAX error:', {xhr, status, error});
                showError('Failed to load location types');
            }
        });
    }

    /**
     * Load locations by type
     */
    function loadLocations(locationTypes) {
        const $multiselect = $('.hhmgt-multiselect[data-filter="location"]');
        const $options = $multiselect.find('.hhmgt-multiselect-options');

        // Convert to array if single value (for backwards compatibility)
        const types = Array.isArray(locationTypes) ? locationTypes : (locationTypes ? [locationTypes] : []);

        if (types.length === 0) {
            debugLog('No location type selected, showing all locations');
            // Clear options and show message
            $options.html('<p style="padding: 8px; color: #6b7280; font-size: 12px;">Select location type(s) first</p>');
            return;
        }

        debugLog('Loading locations for types:', types);

        $.ajax({
            url: hhmgtData.ajax_url,
            type: 'POST',
            data: {
                action: 'hhmgt_get_locations',
                nonce: hhmgtData.nonce,
                location_id: currentState.location_id,
                location_type: types // Send array to backend
            },
            success: function(response) {
                debugLog('Locations response:', response);

                if (response.success && response.data.locations) {
                    if (response.data.locations.length === 0) {
                        debugLog('Warning: No locations found for types:', types);
                        $options.html('<p style="padding: 8px; color: #dc2626; font-size: 12px;">No locations found for selected types</p>');
                    } else {
                        // Clear existing options
                        $options.empty();

                        // Add checkboxes for each location
                        response.data.locations.forEach(function(loc) {
                            $options.append(`
                                <label class="hhmgt-multiselect-option">
                                    <input type="checkbox" value="${loc.id}">
                                    <span>${loc.full_path || loc.location_name}</span>
                                </label>
                            `);
                        });
                        debugLog('Locations loaded:', response.data.locations.length);

                        // Restore saved locations after options are added
                        if (currentState.filters.location && currentState.filters.location.length > 0) {
                            setMultiSelectValues($multiselect, currentState.filters.location);
                        }
                    }
                } else {
                    console.error('[HHMGT] Failed to load locations:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('[HHMGT] Locations AJAX error:', {xhr, status, error});
                showError('Failed to load locations');
            }
        });
    }

    /**
     * Load tasks
     */
    function loadTasks() {
        const $list = $('#tasks-list');
        $list.html('<div class="hhmgt-loading"><span class="material-symbols-outlined hhmgt-loading-icon">sync</span><p>' + hhmgtData.strings.loading + '</p></div>');

        debugLog('Loading tasks with filters:', currentState.filters);

        // Set timeout for loading
        const loadTimeout = setTimeout(function() {
            console.error('[HHMGT] Task loading timeout - no response after 10 seconds');
            $list.html(`
                <div class="hhmgt-empty">
                    <span class="material-symbols-outlined hhmgt-empty-icon" style="color: #dc2626;">error</span>
                    <p class="hhmgt-empty-text">Loading timeout. Please check your connection and try again.</p>
                    <button type="button" class="hhmgt-btn hhmgt-btn-primary" onclick="location.reload()">Reload Page</button>
                </div>
            `);
        }, 10000); // 10 second timeout

        $.ajax({
            url: hhmgtData.ajax_url,
            type: 'POST',
            data: {
                action: 'hhmgt_get_tasks',
                nonce: hhmgtData.nonce,
                location_id: currentState.location_id,
                ...currentState.filters
            },
            success: function(response) {
                clearTimeout(loadTimeout);
                debugLog('Tasks response:', response);

                if (response.success) {
                    currentState.tasks = response.data.tasks;
                    renderTasks(response.data);
                    debugLog('Tasks rendered successfully');
                } else {
                    console.error('[HHMGT] Task load failed:', response);
                    $list.html(`
                        <div class="hhmgt-empty">
                            <span class="material-symbols-outlined hhmgt-empty-icon" style="color: #dc2626;">error</span>
                            <p class="hhmgt-empty-text">${response.data || 'Failed to load tasks'}</p>
                        </div>
                    `);
                    showError(response.data || 'Failed to load tasks');
                }
            },
            error: function(xhr, status, error) {
                clearTimeout(loadTimeout);
                console.error('[HHMGT] Tasks AJAX error:', {xhr, status, error});

                $list.html(`
                    <div class="hhmgt-empty">
                        <span class="material-symbols-outlined hhmgt-empty-icon" style="color: #dc2626;">error</span>
                        <p class="hhmgt-empty-text">Error loading tasks. Please try again.</p>
                        <button type="button" class="hhmgt-btn hhmgt-btn-primary" onclick="location.reload()">Reload Page</button>
                    </div>
                `);
                showError(hhmgtData.strings.error || 'Error loading tasks');
            }
        });
    }

    /**
     * Render tasks list
     */
    function renderTasks(data) {
        const $list = $('#tasks-list');
        $list.empty();

        if (data.group_by && data.tasks.groups) {
            // Render grouped
            renderGroupedTasks(data.tasks.groups, $list);
        } else if (data.tasks.items && data.tasks.items.length > 0) {
            // Render flat list
            data.tasks.items.forEach(function(task) {
                $list.append(renderTaskCard(task));
            });
        } else {
            // Empty state
            $list.html(`
                <div class="hhmgt-empty">
                    <span class="material-symbols-outlined hhmgt-empty-icon">assignment</span>
                    <p class="hhmgt-empty-text">No tasks found</p>
                </div>
            `);
        }
    }

    /**
     * Render grouped tasks
     */
    function renderGroupedTasks(groups, $container) {
        Object.keys(groups).forEach(function(groupKey) {
            const tasks = groups[groupKey];

            const $group = $(`
                <div class="hhmgt-task-group">
                    <h3 class="hhmgt-task-group-header">${groupKey} (${tasks.length})</h3>
                </div>
            `);

            tasks.forEach(function(task) {
                $group.append(renderTaskCard(task));
            });

            $container.append($group);
        });
    }

    /**
     * Render individual task card
     */
    function renderTaskCard(task) {
        const description = task.description ? `<p class="hhmgt-task-description">${escapeHtml(task.description.substring(0, 150))}${task.description.length > 150 ? '...' : ''}</p>` : '';

        const statusBadge = task.state_name ? `<span class="hhmgt-task-status-badge" style="background-color: ${task.status_color || '#6b7280'};">${task.state_name}</span>` : '';

        return `
            <div class="hhmgt-task-card" data-instance-id="${task.instance_id}">
                <div class="hhmgt-task-card-header">
                    ${task.dept_icon ? `<span class="material-symbols-outlined hhmgt-task-icon" style="color: ${task.dept_color || '#8b5cf6'};">${task.dept_icon}</span>` : ''}
                    <div class="hhmgt-task-info">
                        <h3 class="hhmgt-task-title">${escapeHtml(task.task_name)}</h3>
                        <div class="hhmgt-task-meta">
                            ${task.location_path ? `
                                <span class="hhmgt-task-meta-item">
                                    <span class="material-symbols-outlined">place</span>
                                    ${escapeHtml(task.location_path)}
                                </span>
                            ` : ''}
                            ${task.due_date ? `
                                <span class="hhmgt-task-meta-item">
                                    <span class="material-symbols-outlined">schedule</span>
                                    ${formatDate(task.due_date)}
                                </span>
                            ` : ''}
                            ${statusBadge}
                        </div>
                        ${description}
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Open task detail modal
     */
    function openTaskModal(instanceId) {
        $.ajax({
            url: hhmgtData.ajax_url,
            type: 'POST',
            data: {
                action: 'hhmgt_get_task_detail',
                nonce: hhmgtData.nonce,
                instance_id: instanceId
            },
            success: function(response) {
                if (response.success) {
                    currentState.currentTask = response.data;
                    renderTaskModal(response.data);
                    $('#task-modal').fadeIn(200);
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                showError(hhmgtData.strings.error);
            }
        });
    }

    /**
     * Render task modal content
     */
    function renderTaskModal(data) {
        const instance = data.instance;
        const notes = data.notes || [];
        const states = data.states || [];

        // Store states in currentState for action buttons
        if (currentState.currentTask) {
            currentState.currentTask.states = states;
        }

        const $modal = $('#task-modal .hhmgt-modal-content');

        // Build compact status badge HTML for header
        let statusBadgeHTML = '';
        if (states.length > 0) {
            // Find current status
            const currentStatus = states.find(state => state.id == instance.status_id);
            if (currentStatus) {
                statusBadgeHTML = `<div class="hhmgt-header-status-badge" style="background-color: ${currentStatus.color_hex};">${escapeHtml(currentStatus.state_name)}</div>`;
            }
        }

        // Build checklist HTML
        let checklistHTML = '';
        if (instance.checklist_items && instance.checklist_items.length > 0) {
            checklistHTML = '<div class="hhmgt-modal-section"><h4 class="hhmgt-modal-section-title">Checklist</h4><div class="hhmgt-checklist">';

            instance.checklist_items.forEach(function(item, index) {
                // Explicitly check for true boolean value (not truthy)
                const isChecked = instance.checklist_state && instance.checklist_state[index] === true ? 'checked' : '';
                debugLog(`Checklist item ${index}: state=${instance.checklist_state ? instance.checklist_state[index] : 'undefined'}, isChecked=${isChecked ? 'yes' : 'no'}`);
                checklistHTML += `
                    <div class="hhmgt-checklist-item">
                        <input type="checkbox" class="hhmgt-checklist-checkbox" data-index="${index}" ${isChecked} id="check-${index}">
                        <label for="check-${index}" class="hhmgt-checklist-label">${escapeHtml(item)}</label>
                    </div>
                `;
            });

            checklistHTML += '</div></div>';
        }

        // Build completion reminder HTML
        let reminderHTML = '';
        if (instance.completion_reminder_text) {
            reminderHTML = `
                <div class="hhmgt-modal-section">
                    <div class="hhmgt-completion-reminder">
                        <div class="hhmgt-completion-reminder-icon">
                            <span class="material-symbols-outlined">warning</span>
                            <span>Important Reminder</span>
                        </div>
                        <div class="hhmgt-completion-reminder-text">${escapeHtml(instance.completion_reminder_text)}</div>
                    </div>
                </div>
            `;
        }

        // Build action buttons HTML
        let actionButtonsHTML = '';
        if (states.length > 0) {
            // Check if current status is a complete state
            const currentStatus = states.find(state => state.id == instance.status_id);
            const isCurrentlyComplete = currentStatus && (currentStatus.is_complete_state == 1 || currentStatus.is_complete_state === true);

            actionButtonsHTML = `
                <div class="hhmgt-modal-section">
                    <div class="hhmgt-action-buttons">
                        <button type="button" id="update-status-btn" class="hhmgt-btn hhmgt-btn-secondary" ${isCurrentlyComplete ? 'disabled' : ''}>
                            <span class="material-symbols-outlined">edit</span>
                            Update Status
                        </button>
                        <button type="button" id="complete-task-btn" class="hhmgt-btn hhmgt-btn-primary" ${isCurrentlyComplete ? 'disabled' : ''}>
                            <span class="material-symbols-outlined">check_circle</span>
                            Complete Task
                        </button>
                    </div>
                </div>
            `;
        }

        // Build notes HTML
        let notesHTML = '<div class="hhmgt-modal-section"><h4 class="hhmgt-modal-section-title">Notes</h4><div class="hhmgt-notes-list">';

        if (notes.length > 0) {
            notes.forEach(function(note) {
                const checked = note.carry_forward ? 'checked' : '';

                notesHTML += `
                    <div class="hhmgt-note-item" data-note-id="${note.id}">
                        <div class="hhmgt-note-header">
                            <span class="hhmgt-note-author">${escapeHtml(note.author_name || 'Unknown')}</span>
                            <span class="hhmgt-note-date">${formatDateTime(note.created_at)}</span>
                        </div>
                        <div class="hhmgt-note-text">${escapeHtml(note.note_text)}</div>
                        <div class="hhmgt-note-footer">
                            <label class="hhmgt-note-carry-forward-toggle">
                                <input type="checkbox" class="hhmgt-note-carry-forward-checkbox" data-note-id="${note.id}" ${checked}>
                                <span class="material-symbols-outlined">repeat</span>
                                <span>Carry forward to next task</span>
                            </label>
                        </div>
                    </div>
                `;
            });
        } else {
            notesHTML += '<p style="color: #6b7280;">No notes yet</p>';
        }

        notesHTML += '</div></div>';

        // Add note form
        const addNoteHTML = `
            <div class="hhmgt-modal-section">
                <h4 class="hhmgt-modal-section-title">Add Note</h4>
                <div class="hhmgt-add-note-form">
                    <textarea id="note-text" class="hhmgt-note-textarea" placeholder="Enter note..."></textarea>
                    <div class="hhmgt-note-options">
                        <button type="button" id="add-note-btn" class="hhmgt-btn hhmgt-btn-primary">
                            <span class="material-symbols-outlined">add</span>
                            Add Note
                        </button>
                    </div>
                </div>
            </div>
        `;

        const modalHTML = `
            <div class="hhmgt-modal-header">
                <div class="hhmgt-modal-header-content">
                    <h2 class="hhmgt-modal-title">${escapeHtml(instance.task_name)} - ${escapeHtml(instance.location_path || 'Location')}</h2>
                    ${statusBadgeHTML}
                </div>
                <button type="button" class="hhmgt-modal-close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="hhmgt-modal-body">
                ${instance.description ? `
                    <div class="hhmgt-modal-section">
                        <h4 class="hhmgt-modal-section-title">Description</h4>
                        <p class="hhmgt-modal-description">${escapeHtml(instance.description)}</p>
                    </div>
                ` : ''}
                ${checklistHTML}
                ${reminderHTML}
                ${notesHTML}
                ${addNoteHTML}
                ${actionButtonsHTML}
            </div>
        `;

        $modal.html(modalHTML);
    }

    /**
     * Update task status
     */
    function updateTaskStatus(statusId) {
        if (!currentState.currentTask) return;

        const instanceId = currentState.currentTask.instance.id;

        $.ajax({
            url: hhmgtData.ajax_url,
            type: 'POST',
            data: {
                action: 'hhmgt_update_task_status',
                nonce: hhmgtData.nonce,
                instance_id: instanceId,
                status_id: statusId
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.data.message, 'success');

                    // Close status selection modal
                    $('#status-selection-modal').fadeOut(200);

                    // Reload task detail to reflect new status
                    openTaskModal(instanceId);

                    // Reload tasks list in background
                    loadTasks();
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                showError(hhmgtData.strings.error);
            }
        });
    }

    /**
     * Update checklist
     */
    function updateChecklist() {
        if (!currentState.currentTask) return;

        const checklistState = {};
        $('.hhmgt-checklist-checkbox').each(function() {
            const index = $(this).data('index');
            checklistState[index] = $(this).is(':checked');
        });

        debugLog('Updating checklist state:', checklistState);

        $.ajax({
            url: hhmgtData.ajax_url,
            type: 'POST',
            data: {
                action: 'hhmgt_update_checklist',
                nonce: hhmgtData.nonce,
                instance_id: currentState.currentTask.instance.id,
                checklist_state: checklistState
            },
            success: function(response) {
                if (response.success) {
                    debugLog('Checklist updated successfully:', response.data.checklist_state);

                    // If status was auto-updated, reload the task modal
                    if (response.data.status_updated) {
                        showToast('Status updated to: ' + response.data.new_status_name, 'success');
                        // Reload task modal to reflect new status
                        openTaskModal(currentState.currentTask.instance.id);
                        // Reload tasks list in background
                        loadTasks();
                    }
                } else {
                    console.error('[HHMGT] Checklist update failed:', response);
                    showError(response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('[HHMGT] Checklist update AJAX error:', {xhr, status, error});
            }
        });
    }

    /**
     * Add note
     */
    function addNote() {
        if (!currentState.currentTask) return;

        const noteText = $('#note-text').val().trim();
        if (!noteText) {
            showError('Please enter a note');
            return;
        }

        // Default carry forward to true (user can toggle it after adding)
        const carryForward = true;

        $.ajax({
            url: hhmgtData.ajax_url,
            type: 'POST',
            data: {
                action: 'hhmgt_add_note',
                nonce: hhmgtData.nonce,
                instance_id: currentState.currentTask.instance.id,
                note_text: noteText,
                carry_forward: carryForward,
                note_photos: []
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                    $('#note-text').val('');
                    // Reload task modal
                    openTaskModal(currentState.currentTask.instance.id);
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                showError(hhmgtData.strings.error);
            }
        });
    }

    /**
     * Update note carry-forward status
     */
    function updateNoteCarryForward(noteId, carryForward) {
        $.ajax({
            url: hhmgtData.ajax_url,
            type: 'POST',
            data: {
                action: 'hhmgt_update_note_carry_forward',
                nonce: hhmgtData.nonce,
                note_id: noteId,
                carry_forward: carryForward
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                } else {
                    showError(response.data);
                    // Revert checkbox on error
                    $(`.hhmgt-note-carry-forward-checkbox[data-note-id="${noteId}"]`).prop('checked', !carryForward);
                }
            },
            error: function() {
                showError(hhmgtData.strings.error);
                // Revert checkbox on error
                $(`.hhmgt-note-carry-forward-checkbox[data-note-id="${noteId}"]`).prop('checked', !carryForward);
            }
        });
    }

    /**
     * Open status selection modal
     */
    function openStatusSelectionModal() {
        if (!currentState.currentTask || !currentState.currentTask.states) {
            showError('No states available');
            return;
        }

        const instance = currentState.currentTask.instance;
        const states = currentState.currentTask.states;

        // Filter out complete states
        const nonCompleteStates = states.filter(state =>
            !(state.is_complete_state == 1 || state.is_complete_state === true)
        );

        if (nonCompleteStates.length === 0) {
            showError('No status options available');
            return;
        }

        // Build modal content
        let statusOptionsHTML = '<div class="hhmgt-status-selection-grid">';
        nonCompleteStates.forEach(function(state) {
            statusOptionsHTML += `
                <button type="button"
                        class="hhmgt-status-option"
                        data-status-id="${state.id}"
                        style="background-color: ${state.color_hex};">
                    ${escapeHtml(state.state_name)}
                </button>
            `;
        });
        statusOptionsHTML += '</div>';

        const modalHTML = `
            <div class="hhmgt-modal-header">
                <h3 class="hhmgt-modal-title">Update Task Status</h3>
                <button type="button" class="hhmgt-modal-close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="hhmgt-modal-body">
                <p style="margin-bottom: 15px; color: #6b7280;">Select a new status for this task:</p>
                ${statusOptionsHTML}
            </div>
        `;

        $('#status-selection-modal .hhmgt-modal-content').html(modalHTML);
        $('#status-selection-modal').fadeIn(200);
    }

    /**
     * Open completion modal
     */
    function openCompletionModal() {
        if (!currentState.currentTask) return;

        const instance = currentState.currentTask.instance;

        // Simple confirmation for now
        if (confirm('Mark this task as complete?')) {
            completeTask();
        }
    }

    /**
     * Close completion modal
     */
    function closeCompletionModal() {
        $('#completion-modal').fadeOut(200);
    }

    /**
     * Complete task
     */
    function completeTask() {
        if (!currentState.currentTask) return;

        $.ajax({
            url: hhmgtData.ajax_url,
            type: 'POST',
            data: {
                action: 'hhmgt_complete_task',
                nonce: hhmgtData.nonce,
                instance_id: currentState.currentTask.instance.id,
                completion_photos: []
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                    closeModals();
                    loadTasks();
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                showError(hhmgtData.strings.error);
            }
        });
    }

    /**
     * Close all modals
     */
    function closeModals() {
        $('.hhmgt-modal').fadeOut(200);
        currentState.currentTask = null;
    }

    /**
     * Initialize heartbeat
     */
    function initHeartbeat() {
        // Send data with heartbeat
        $(document).on('heartbeat-send', function(event, data) {
            if (currentState.location_id) {
                data.hhmgt_monitor = {
                    location_id: currentState.location_id,
                    last_check: new Date().toISOString()
                };
            }
        });

        // Receive heartbeat response
        $(document).on('heartbeat-tick', function(event, data) {
            if (data.hhmgt_updates) {
                // Reload tasks if updates detected
                if (data.hhmgt_updates.instances && data.hhmgt_updates.instances.length > 0) {
                    loadTasks();
                }
            }
        });
    }

    /**
     * Show toast notification
     */
    function showToast(message, type = 'success') {
        const $toast = $(`<div class="hhmgt-toast hhmgt-toast-${type}">${escapeHtml(message)}</div>`);
        $('body').append($toast);

        setTimeout(() => $toast.addClass('show'), 10);

        setTimeout(() => {
            $toast.removeClass('show');
            setTimeout(() => $toast.remove(), 300);
        }, 3000);
    }

    /**
     * Show error message
     */
    function showError(message) {
        showToast(message, 'error');
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Format date
     */
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString();
    }

    /**
     * Format date and time
     */
    function formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString();
    }

    /**
     * Initialize module when Hotel Hub App loads it
     */
    function tryInit() {
        if ($('.hhmgt-container').length) {
            debugLog('Container found, initializing...');
            initModule();
        } else {
            debugLog('Container not found, waiting for module load...');
        }
    }

    // Listen for Hotel Hub App module load event
    $(document).on('hha-module-loaded', function(event, moduleId) {
        debugLog('Module load event received:', moduleId);
        if (moduleId === 'management_tasks') {
            // Small delay to ensure DOM is ready
            setTimeout(tryInit, 50);
        }
    });

    // Also try on document ready (for direct page loads)
    $(document).ready(tryInit);

})(jQuery);
