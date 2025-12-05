<?php
/**
 * Temporary Debug Script - Delete after use
 *
 * Access via: /wp-content/plugins/hotelhubmodule-management-tasks/debug-settings.php
 */

// Load WordPress
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo "<h1>HHMGT Settings Diagnostic</h1>";

// Get all settings from options table
$all_settings = get_option('hhmgt_location_settings', array());

echo "<h2>Settings in Options Table</h2>";
echo "<pre>";
print_r($all_settings);
echo "</pre>";

// Get locations from Hotel Hub App
if (function_exists('hha')) {
    $hotels = hha()->hotels->get_active();
    echo "<h2>Available Locations from Hotel Hub App</h2>";
    echo "<pre>";
    foreach ($hotels as $hotel) {
        echo "ID: {$hotel->id}, Name: {$hotel->name}\n";
    }
    echo "</pre>";
}

// Check database tables
global $wpdb;

echo "<h2>Departments in Database</h2>";
$departments = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}hhmgt_departments ORDER BY location_id, sort_order");
echo "<pre>";
print_r($departments);
echo "</pre>";

echo "<h2>Recurring Patterns in Database</h2>";
$patterns = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}hhmgt_recurring_patterns ORDER BY location_id");
echo "<pre>";
print_r($patterns);
echo "</pre>";

echo "<h2>Location Hierarchy in Database</h2>";
$hierarchy = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}hhmgt_location_hierarchy ORDER BY location_id, hierarchy_level, sort_order");
echo "<pre>";
print_r($hierarchy);
echo "</pre>";

echo "<h2>Task States in Database</h2>";
$states = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}hhmgt_task_states ORDER BY location_id, sort_order");
echo "<pre>";
print_r($states);
echo "</pre>";
