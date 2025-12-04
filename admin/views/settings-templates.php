<?php
/**
 * Checklist Templates Settings Tab
 *
 * @package HotelHub_Management_Tasks
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get existing templates
global $wpdb;
$table = $wpdb->prefix . 'hhmgt_checklist_templates';
$templates = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table} WHERE location_id = %d ORDER BY template_name ASC",
    $current_location_id
));
?>

<div class="hhmgt-settings-section">
    <h2><?php _e('Checklist Templates', 'hhmgt'); ?></h2>
    <p class="description">
        <?php _e('Create reusable checklist templates that can be applied to tasks. Great for standardizing common inspection or cleaning procedures.', 'hhmgt'); ?>
    </p>

    <div class="hhmgt-templates-manager">
        <div class="hhmgt-templates-toolbar">
            <button type="button" class="button button-primary" id="add-template-btn">
                <span class="material-symbols-outlined">add</span>
                <?php _e('Create Template', 'hhmgt'); ?>
            </button>
        </div>

        <div class="hhmgt-templates-list">
            <?php if (empty($templates)): ?>
                <div class="hhmgt-empty-state">
                    <span class="material-symbols-outlined">fact_check</span>
                    <p><?php _e('No templates created yet. Create your first template to get started.', 'hhmgt'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 30%;"><?php _e('Template Name', 'hhmgt'); ?></th>
                            <th style="width: 50%;"><?php _e('Checklist Items', 'hhmgt'); ?></th>
                            <th style="width: 20%;"><?php _e('Actions', 'hhmgt'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $template): ?>
                            <?php
                            $items = json_decode($template->checklist_items, true);
                            $item_count = is_array($items) ? count($items) : 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($template->template_name); ?></strong>
                                </td>
                                <td>
                                    <?php if ($item_count > 0): ?>
                                        <details>
                                            <summary><?php printf(_n('%d item', '%d items', $item_count, 'hhmgt'), $item_count); ?></summary>
                                            <ul style="margin: 10px 0 0 20px;">
                                                <?php foreach (array_slice($items, 0, 5) as $item): ?>
                                                    <li><?php echo esc_html($item); ?></li>
                                                <?php endforeach; ?>
                                                <?php if ($item_count > 5): ?>
                                                    <li><em><?php printf(__('+ %d more...', 'hhmgt'), $item_count - 5); ?></em></li>
                                                <?php endif; ?>
                                            </ul>
                                        </details>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;"><?php _e('No items', 'hhmgt'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-small edit-template-btn" data-id="<?php echo esc_attr($template->id); ?>">
                                        <?php _e('Edit', 'hhmgt'); ?>
                                    </button>
                                    <button type="button" class="button button-small delete-template-btn" data-id="<?php echo esc_attr($template->id); ?>" style="color: #dc2626;">
                                        <?php _e('Delete', 'hhmgt'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Template Editor Modal -->
<div id="template-editor-modal" class="hhmgt-modal" style="display: none;">
    <div class="hhmgt-modal-overlay"></div>
    <div class="hhmgt-modal-content" style="max-width: 700px;">
        <div class="hhmgt-modal-header">
            <h3 id="template-modal-title"><?php _e('Create Checklist Template', 'hhmgt'); ?></h3>
            <button type="button" class="hhmgt-modal-close">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="hhmgt-modal-body">
            <form id="template-form" onsubmit="return false;">
                <input type="hidden" name="template_id" id="template_id">
                <input type="hidden" name="location_id" value="<?php echo esc_attr($current_location_id); ?>">

                <div class="hhmgt-form-group">
                    <label for="template_name"><?php _e('Template Name', 'hhmgt'); ?> <span class="required">*</span></label>
                    <input type="text"
                           id="template_name"
                           name="template_name"
                           class="regular-text"
                           placeholder="<?php esc_attr_e('e.g., Standard Room Cleaning', 'hhmgt'); ?>"
                           required>
                </div>

                <div class="hhmgt-form-group">
                    <label><?php _e('Checklist Items', 'hhmgt'); ?></label>
                    <div id="template-checklist-items">
                        <!-- Items will be added here -->
                    </div>
                    <button type="button" class="button" id="add-template-item">
                        <span class="material-symbols-outlined">add</span>
                        <?php _e('Add Item', 'hhmgt'); ?>
                    </button>
                </div>

                <div class="hhmgt-modal-footer">
                    <button type="button" class="button hhmgt-modal-close"><?php _e('Cancel', 'hhmgt'); ?></button>
                    <button type="button" class="button button-primary" id="save-template-btn"><?php _e('Save Template', 'hhmgt'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.hhmgt-templates-manager {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.hhmgt-templates-toolbar {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e5e7eb;
}

.hhmgt-templates-list details {
    cursor: pointer;
}

.hhmgt-templates-list summary {
    color: #3b82f6;
    font-size: 14px;
}

.hhmgt-templates-list ul {
    font-size: 14px;
    color: #374151;
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

.required {
    color: #dc2626;
}

.hhmgt-modal-footer {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
    text-align: right;
}

#template-checklist-items {
    margin-bottom: 15px;
}

.hhmgt-template-item {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}

.hhmgt-template-item input[type="text"] {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
}

.hhmgt-template-item .hhmgt-drag-handle {
    cursor: move;
    color: #9ca3af;
}

.hhmgt-template-item button {
    padding: 4px 8px !important;
    min-width: auto !important;
}

.hhmgt-template-item .material-symbols-outlined {
    font-size: 18px;
}
</style>

<script>
jQuery(document).ready(function($) {
    let currentTemplateId = null;

    // Open create template modal
    $('#add-template-btn').on('click', function() {
        openTemplateModal();
    });

    // Edit template
    $('.edit-template-btn').on('click', function() {
        const templateId = $(this).data('id');
        loadTemplate(templateId);
    });

    // Delete template
    $('.delete-template-btn').on('click', function() {
        if (!confirm('<?php esc_attr_e('Are you sure you want to delete this template?', 'hhmgt'); ?>')) {
            return;
        }

        const templateId = $(this).data('id');

        $.ajax({
            url: hhmgtAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'hhmgt_delete_template',
                nonce: hhmgtAdmin.nonce,
                template_id: templateId,
                location_id: <?php echo $current_location_id; ?>
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || hhmgtAdmin.strings.error);
                }
            }
        });
    });

    // Close modal
    $('.hhmgt-modal-close, .hhmgt-modal-overlay').on('click', function(e) {
        if ($(e.target).hasClass('hhmgt-modal-overlay') || $(e.target).closest('.hhmgt-modal-close').length) {
            closeTemplateModal();
        }
    });

    // Add template item
    $(document).on('click', '#add-template-item', function() {
        addTemplateItem('');
    });

    // Remove template item
    $(document).on('click', '.remove-template-item', function() {
        $(this).closest('.hhmgt-template-item').remove();
    });

    // Save template button click
    $('#save-template-btn').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        // Validate template name
        const templateName = $('#template_name').val().trim();
        if (!templateName) {
            alert('<?php esc_attr_e('Please enter a template name.', 'hhmgt'); ?>');
            $('#template_name').focus();
            return;
        }

        const items = [];
        $('#template-checklist-items input[type="text"]').each(function() {
            const val = $(this).val().trim();
            if (val) {
                items.push(val);
            }
        });

        $.ajax({
            url: hhmgtAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'hhmgt_save_template',
                nonce: hhmgtAdmin.nonce,
                template_id: $('#template_id').val(),
                template_name: templateName,
                checklist_items: JSON.stringify(items),
                location_id: <?php echo $current_location_id; ?>
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || hhmgtAdmin.strings.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('<?php esc_attr_e('Error saving template. Please try again.', 'hhmgt'); ?>');
            }
        });
    });

    function openTemplateModal(templateId = null) {
        currentTemplateId = templateId;
        $('#template_id').val(templateId || '');
        $('#template_name').val('');
        $('#template-checklist-items').empty();

        if (templateId) {
            $('#template-modal-title').text('<?php esc_attr_e('Edit Checklist Template', 'hhmgt'); ?>');
        } else {
            $('#template-modal-title').text('<?php esc_attr_e('Create Checklist Template', 'hhmgt'); ?>');
            // Add one empty item for new templates
            addTemplateItem('');
        }

        $('#template-editor-modal').fadeIn(200);
    }

    function closeTemplateModal() {
        $('#template-editor-modal').fadeOut(200);
        currentTemplateId = null;
    }

    function loadTemplate(templateId) {
        $.ajax({
            url: hhmgtAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'hhmgt_get_template',
                nonce: hhmgtAdmin.nonce,
                template_id: templateId
            },
            success: function(response) {
                if (response.success) {
                    const template = response.data;
                    openTemplateModal(templateId);
                    $('#template_name').val(template.template_name);

                    const items = JSON.parse(template.checklist_items);
                    items.forEach(function(item) {
                        addTemplateItem(item);
                    });
                }
            }
        });
    }

    function addTemplateItem(value) {
        const $item = $(`
            <div class="hhmgt-template-item">
                <span class="material-symbols-outlined hhmgt-drag-handle">drag_indicator</span>
                <input type="text" value="${value}" placeholder="<?php esc_attr_e('Checklist item...', 'hhmgt'); ?>">
                <button type="button" class="button remove-template-item">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        `);
        $('#template-checklist-items').append($item);
        if (!value) {
            $item.find('input').focus();
        }
    }
});
</script>
