<?php
/**
 * Location Hierarchy Settings Tab
 *
 * @package HotelHub_Management_Tasks
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="hhmgt-settings-section">
    <h3><?php esc_html_e('Location Hierarchy', 'hhmgt'); ?></h3>
    <p class="description">
        <?php esc_html_e('Define hierarchical locations for task assignment (e.g., Housekeeping > Room > 101 > Bedroom). This feature will be completed in Phase 3.', 'hhmgt'); ?>
    </p>

    <div class="notice notice-info inline">
        <p><?php esc_html_e('Location hierarchy builder coming in Phase 3. For now, locations can be created via the database or future admin interface.', 'hhmgt'); ?></p>
    </div>
</div>
