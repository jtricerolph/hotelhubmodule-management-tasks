<?php
/**
 * Admin Settings Page Template
 *
 * Multi-location tabbed interface for module settings
 *
 * @package HotelHub_Management_Tasks
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap hhmgt-settings-wrap">
    <h1><?php esc_html_e('Tasks Module Settings', 'hhmgt'); ?></h1>

    <?php if (isset($_GET['updated']) && $_GET['updated'] === 'true'): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Settings saved successfully.', 'hhmgt'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (empty($locations)): ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e('No locations found. Please ensure Hotel Hub App is properly configured.', 'hhmgt'); ?></p>
        </div>
        <?php return; ?>
    <?php endif; ?>

    <!-- Location Selector Tabs -->
    <h2 class="nav-tab-wrapper hhmgt-location-tabs">
        <?php foreach ($locations as $location): ?>
            <?php
            $is_active = ($location['id'] == $current_location_id);
            $tab_class = 'nav-tab' . ($is_active ? ' nav-tab-active' : '');
            $tab_url = add_query_arg(array(
                'page' => 'hhmgt-settings',
                'location_id' => $location['id'],
                'tab' => $current_tab
            ), admin_url('admin.php'));
            ?>
            <a href="<?php echo esc_url($tab_url); ?>" class="<?php echo esc_attr($tab_class); ?>">
                <?php echo esc_html($location['name']); ?>
            </a>
        <?php endforeach; ?>
    </h2>

    <!-- Settings Section Tabs -->
    <h2 class="nav-tab-wrapper hhmgt-settings-tabs">
        <?php
        $tabs = array(
            'general' => __('General', 'hhmgt'),
            'departments' => __('Departments', 'hhmgt'),
            'locations' => __('Locations', 'hhmgt'),
            'patterns' => __('Recurring Patterns', 'hhmgt'),
            'states' => __('Task States', 'hhmgt'),
            'templates' => __('Checklist Templates', 'hhmgt')
        );

        foreach ($tabs as $tab_key => $tab_label):
            $is_active = ($tab_key === $current_tab);
            $tab_class = 'nav-tab' . ($is_active ? ' nav-tab-active' : '');
            $tab_url = add_query_arg(array(
                'page' => 'hhmgt-settings',
                'location_id' => $current_location_id,
                'tab' => $tab_key
            ), admin_url('admin.php'));
        ?>
            <a href="<?php echo esc_url($tab_url); ?>" class="<?php echo esc_attr($tab_class); ?>">
                <?php echo esc_html($tab_label); ?>
            </a>
        <?php endforeach; ?>
    </h2>

    <!-- Settings Form -->
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="hhmgt-settings-form">
        <input type="hidden" name="action" value="hhmgt_save_settings">
        <input type="hidden" name="location_id" value="<?php echo esc_attr($current_location_id); ?>">
        <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>">
        <?php wp_nonce_field('hhmgt_save_settings', 'hhmgt_settings_nonce'); ?>

        <div class="hhmgt-settings-content">
            <?php
            // Load tab-specific template
            $tab_file = HHMGT_PLUGIN_DIR . 'admin/views/settings-' . $current_tab . '.php';
            if (file_exists($tab_file)) {
                include $tab_file;
            } else {
                echo '<p>' . esc_html__('Tab template not found.', 'hhmgt') . '</p>';
            }
            ?>
        </div>

        <?php submit_button(__('Save Settings', 'hhmgt')); ?>
    </form>
</div>

<style>
/* Quick inline styles - will move to admin.css */
.hhmgt-settings-wrap {
    margin-top: 20px;
}

.hhmgt-location-tabs,
.hhmgt-settings-tabs {
    margin-bottom: 0;
}

.hhmgt-settings-tabs {
    border-top: 1px solid #c3c4c7;
    padding-top: 0;
}

.hhmgt-settings-content {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-top: none;
    padding: 20px;
    margin-bottom: 20px;
}

.hhmgt-settings-section {
    margin-bottom: 30px;
}

.hhmgt-settings-section h3 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #e5e7eb;
}
</style>
