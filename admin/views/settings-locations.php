<?php
/**
 * Location Hierarchy Settings Tab
 *
 * @package HotelHub_Management_Tasks
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get existing location hierarchy
global $wpdb;
$table = $wpdb->prefix . 'hhmgt_location_hierarchy';
$locations = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table} WHERE location_id = %d ORDER BY hierarchy_level ASC, sort_order ASC",
    $current_location_id
));

// Build hierarchical structure
function build_hierarchy($locations, $parent_id = null, $level = 0) {
    $result = array();
    foreach ($locations as $location) {
        if ($location->parent_id == $parent_id) {
            $location->level = $level;
            $result[] = $location;
            $children = build_hierarchy($locations, $location->id, $level + 1);
            $result = array_merge($result, $children);
        }
    }
    return $result;
}

$hierarchical_locations = build_hierarchy($locations);
?>

<div class="hhmgt-settings-section">
    <h2><?php _e('Location Hierarchy', 'hhmgt'); ?></h2>
    <p class="description">
        <?php _e('Build a hierarchical structure for your hotel locations (e.g., Housekeeping → Room 101 → Bedroom). This allows you to assign tasks to specific areas.', 'hhmgt'); ?>
    </p>

    <div class="hhmgt-hierarchy-builder">
        <div class="hhmgt-hierarchy-toolbar">
            <button type="button" class="button button-primary" id="add-root-location">
                <span class="material-symbols-outlined">add</span>
                <?php _e('Add Root Location', 'hhmgt'); ?>
            </button>
            <div class="hhmgt-hierarchy-info">
                <span class="material-symbols-outlined">info</span>
                <span><?php _e('Drag items to reorder. Click + to add child locations.', 'hhmgt'); ?></span>
            </div>
        </div>

        <div id="location-hierarchy-list" class="hhmgt-hierarchy-list">
            <?php if (empty($hierarchical_locations)): ?>
                <div class="hhmgt-empty-state">
                    <span class="material-symbols-outlined">location_on</span>
                    <p><?php _e('No locations defined yet. Add a root location to get started.', 'hhmgt'); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($hierarchical_locations as $index => $location): ?>
                    <div class="hhmgt-hierarchy-item"
                         data-id="<?php echo esc_attr($location->id); ?>"
                         data-parent-id="<?php echo esc_attr($location->parent_id ?? ''); ?>"
                         data-level="<?php echo esc_attr($location->level); ?>"
                         style="padding-left: <?php echo ($location->level * 30 + 10); ?>px;">

                        <span class="material-symbols-outlined hhmgt-drag-handle">drag_indicator</span>

                        <div class="hhmgt-hierarchy-content">
                            <input type="hidden" name="locations[<?php echo $index; ?>][id]" value="<?php echo esc_attr($location->id); ?>">
                            <input type="hidden" name="locations[<?php echo $index; ?>][parent_id]" value="<?php echo esc_attr($location->parent_id ?? ''); ?>">
                            <input type="hidden" name="locations[<?php echo $index; ?>][hierarchy_level]" value="<?php echo esc_attr($location->level); ?>">
                            <input type="hidden" name="locations[<?php echo $index; ?>][sort_order]" value="<?php echo $index; ?>">

                            <div class="hhmgt-hierarchy-field">
                                <input type="text"
                                       name="locations[<?php echo $index; ?>][location_name]"
                                       value="<?php echo esc_attr($location->location_name); ?>"
                                       placeholder="<?php esc_attr_e('Location name (e.g., Room 101)', 'hhmgt'); ?>"
                                       required>
                            </div>

                            <div class="hhmgt-hierarchy-field">
                                <input type="text"
                                       name="locations[<?php echo $index; ?>][location_type]"
                                       value="<?php echo esc_attr($location->location_type ?? ''); ?>"
                                       placeholder="<?php esc_attr_e('Type (e.g., Bedroom)', 'hhmgt'); ?>">
                            </div>

                            <label class="hhmgt-hierarchy-enabled">
                                <input type="checkbox"
                                       name="locations[<?php echo $index; ?>][is_enabled]"
                                       value="1"
                                       <?php checked($location->is_enabled, 1); ?>>
                                <span><?php _e('Enabled', 'hhmgt'); ?></span>
                            </label>
                        </div>

                        <div class="hhmgt-hierarchy-actions">
                            <button type="button" class="button button-small hhmgt-add-child" title="<?php esc_attr_e('Add Child', 'hhmgt'); ?>">
                                <span class="material-symbols-outlined">add</span>
                            </button>
                            <button type="button" class="button button-small hhmgt-remove-location" title="<?php esc_attr_e('Remove', 'hhmgt'); ?>">
                                <span class="material-symbols-outlined">delete</span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Template for new locations -->
        <template id="location-item-template">
            <div class="hhmgt-hierarchy-item" data-id="new" data-parent-id="" data-level="0">
                <span class="material-symbols-outlined hhmgt-drag-handle">drag_indicator</span>

                <div class="hhmgt-hierarchy-content">
                    <input type="hidden" name="locations[INDEX][id]" value="new">
                    <input type="hidden" name="locations[INDEX][parent_id]" value="">
                    <input type="hidden" name="locations[INDEX][hierarchy_level]" value="0">
                    <input type="hidden" name="locations[INDEX][sort_order]" value="0">

                    <div class="hhmgt-hierarchy-field">
                        <input type="text"
                               name="locations[INDEX][location_name]"
                               placeholder="<?php esc_attr_e('Location name (e.g., Room 101)', 'hhmgt'); ?>"
                               required>
                    </div>

                    <div class="hhmgt-hierarchy-field">
                        <input type="text"
                               name="locations[INDEX][location_type]"
                               placeholder="<?php esc_attr_e('Type (e.g., Bedroom)', 'hhmgt'); ?>">
                    </div>

                    <label class="hhmgt-hierarchy-enabled">
                        <input type="checkbox" name="locations[INDEX][is_enabled]" value="1" checked>
                        <span><?php _e('Enabled', 'hhmgt'); ?></span>
                    </label>
                </div>

                <div class="hhmgt-hierarchy-actions">
                    <button type="button" class="button button-small hhmgt-add-child" title="<?php esc_attr_e('Add Child', 'hhmgt'); ?>">
                        <span class="material-symbols-outlined">add</span>
                    </button>
                    <button type="button" class="button button-small hhmgt-remove-location" title="<?php esc_attr_e('Remove', 'hhmgt'); ?>">
                        <span class="material-symbols-outlined">delete</span>
                    </button>
                </div>
            </div>
        </template>
    </div>
</div>

<style>
.hhmgt-hierarchy-builder {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.hhmgt-hierarchy-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e5e7eb;
}

.hhmgt-hierarchy-info {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #6b7280;
    font-size: 14px;
}

.hhmgt-hierarchy-info .material-symbols-outlined {
    color: #3b82f6;
}

.hhmgt-hierarchy-list {
    min-height: 200px;
}

.hhmgt-hierarchy-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 10px;
    margin-bottom: 8px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    transition: all 0.2s;
}

.hhmgt-hierarchy-item:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
}

.hhmgt-hierarchy-item.dragging {
    opacity: 0.5;
}

.hhmgt-drag-handle {
    cursor: move;
    color: #9ca3af;
    flex-shrink: 0;
}

.hhmgt-hierarchy-content {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.hhmgt-hierarchy-field {
    flex: 1;
}

.hhmgt-hierarchy-field input[type="text"] {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
}

.hhmgt-hierarchy-enabled {
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    margin: 0;
}

.hhmgt-hierarchy-enabled input[type="checkbox"] {
    margin: 0;
}

.hhmgt-hierarchy-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

.hhmgt-hierarchy-actions .button-small {
    padding: 4px 8px !important;
    min-width: auto !important;
}

.hhmgt-hierarchy-actions .material-symbols-outlined {
    font-size: 18px;
}

.hhmgt-empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}

.hhmgt-empty-state .material-symbols-outlined {
    font-size: 64px;
    display: block;
    margin-bottom: 15px;
}

.hhmgt-empty-state p {
    margin: 0;
    font-size: 15px;
}
</style>

<script>
jQuery(document).ready(function($) {
    let locationIndex = $('.hhmgt-hierarchy-item').length;

    // Add root location
    $('#add-root-location').on('click', function() {
        addLocation(null, 0);
    });

    // Add child location
    $(document).on('click', '.hhmgt-add-child', function() {
        const $parent = $(this).closest('.hhmgt-hierarchy-item');
        const parentId = $parent.data('id');
        const parentLevel = parseInt($parent.data('level'));
        addLocation(parentId, parentLevel + 1, $parent);
    });

    // Remove location
    $(document).on('click', '.hhmgt-remove-location', function() {
        if (confirm('<?php esc_attr_e('Are you sure you want to remove this location? Any tasks assigned to it will need to be reassigned.', 'hhmgt'); ?>')) {
            const $item = $(this).closest('.hhmgt-hierarchy-item');
            const itemId = $item.data('id');

            // Remove this item and all its children
            removeLocationAndChildren(itemId);
        }
    });

    function addLocation(parentId, level, $insertAfter) {
        const template = $('#location-item-template').html();
        const $newItem = $(template.replace(/INDEX/g, locationIndex));

        $newItem.attr('data-level', level);
        $newItem.attr('data-parent-id', parentId || '');
        $newItem.find('input[name*="[parent_id]"]').val(parentId || '');
        $newItem.find('input[name*="[hierarchy_level]"]').val(level);
        $newItem.css('padding-left', (level * 30 + 10) + 'px');

        // Remove empty state if exists
        $('.hhmgt-empty-state').remove();

        if ($insertAfter) {
            // Insert after parent or parent's last child
            const $lastChild = findLastChild($insertAfter);
            $lastChild.after($newItem);
        } else {
            // Add to end
            $('#location-hierarchy-list').append($newItem);
        }

        locationIndex++;
        $newItem.find('input[type="text"]').first().focus();
        updateSortOrders();
    }

    function findLastChild($parent) {
        const parentId = $parent.data('id');
        let $lastChild = $parent;

        $parent.nextAll('.hhmgt-hierarchy-item').each(function() {
            const $this = $(this);
            if ($this.data('parent-id') == parentId || hasAncestor($this, parentId)) {
                $lastChild = $this;
            } else {
                return false; // Break
            }
        });

        return $lastChild;
    }

    function hasAncestor($item, ancestorId) {
        let parentId = $item.data('parent-id');
        while (parentId) {
            if (parentId == ancestorId) {
                return true;
            }
            const $parent = $(`.hhmgt-hierarchy-item[data-id="${parentId}"]`);
            if (!$parent.length) break;
            parentId = $parent.data('parent-id');
        }
        return false;
    }

    function removeLocationAndChildren(itemId) {
        const $item = $(`.hhmgt-hierarchy-item[data-id="${itemId}"]`);
        $item.remove();

        // Remove all children
        $(`.hhmgt-hierarchy-item[data-parent-id="${itemId}"]`).each(function() {
            removeLocationAndChildren($(this).data('id'));
        });

        updateSortOrders();

        // Show empty state if no items
        if ($('.hhmgt-hierarchy-item').length === 0) {
            $('#location-hierarchy-list').html(`
                <div class="hhmgt-empty-state">
                    <span class="material-symbols-outlined">location_on</span>
                    <p><?php esc_attr_e('No locations defined yet. Add a root location to get started.', 'hhmgt'); ?></p>
                </div>
            `);
        }
    }

    function updateSortOrders() {
        $('.hhmgt-hierarchy-item').each(function(index) {
            $(this).find('input[name*="[sort_order]"]').val(index);
        });
    }

    // Simple drag and drop (basic implementation)
    let draggedItem = null;

    $(document).on('mousedown', '.hhmgt-drag-handle', function(e) {
        draggedItem = $(this).closest('.hhmgt-hierarchy-item');
        draggedItem.addClass('dragging');
    });

    $(document).on('mouseup', function() {
        if (draggedItem) {
            draggedItem.removeClass('dragging');
            draggedItem = null;
            updateSortOrders();
        }
    });

    $(document).on('mouseenter', '.hhmgt-hierarchy-item', function() {
        if (draggedItem && draggedItem[0] !== this) {
            const draggedLevel = parseInt(draggedItem.data('level'));
            const targetLevel = parseInt($(this).data('level'));

            // Only allow reordering within same level
            if (draggedLevel === targetLevel) {
                $(this).before(draggedItem);
            }
        }
    });
});
</script>
