# Hotel Hub Module - Management - Tasks

Comprehensive recurring task management system for Hotel Hub PWA with flexible scheduling, hierarchical locations, and multi-department support.

## Overview

This module provides a powerful task management system designed specifically for hotels with:
- **Flexible Recurring Patterns**: Both fixed-schedule and dynamic (from-completion) recurring options
- **Hierarchical Locations**: Unlimited depth location structure (e.g., Housekeeping → Room → 101 → Bedroom)
- **Multi-Department Support**: Custom departments with icons and colors
- **Photo Management**: Reference photos, note attachments, and completion photos
- **Task Checklists**: Reusable templates and per-task custom checklists
- **Lead Time Tracking**: Show tasks X days before they're due
- **Note Persistence**: Carry forward notes to future recurring task instances

## Features

### Core Functionality
- ✅ Per-location configuration (multi-hotel support)
- ✅ Custom departments with Material Symbols icons and color coding
- ✅ Flexible location hierarchy (unlimited depth)
- ✅ Recurring patterns: Fixed (scheduled) and Dynamic (from completion)
- ✅ Customizable task states with colors
- ✅ Lead time for advance task visibility
- ✅ Checklist templates for reuse
- ✅ Three photo types: Reference, Note, Completion
- ✅ Note carry-forward for recurring tasks
- ✅ Real-time sync via WordPress Heartbeat
- ✅ WP Cron scheduler for automatic task generation

### User Interface
- Frontend PWA module with filtering and grouping
- Task list view with Material Design cards
- Task detail modal with all information
- Completion flow with optional photo requirement
- Completion reminder with acknowledgment checkbox
- Searchable Material Symbols icon picker

## Installation

1. Upload to `/wp-content/plugins/hotelhubmodule-management-tasks/`
2. Activate through WordPress admin
3. Requires: Hotel Hub App plugin

## Database Schema

### Tables Created (8 total)
1. `wp_hhmgt_tasks` - Task definitions
2. `wp_hhmgt_task_instances` - Individual task occurrences
3. `wp_hhmgt_departments` - Custom departments
4. `wp_hhmgt_location_hierarchy` - Hierarchical locations (flexible depth)
5. `wp_hhmgt_recurring_patterns` - Pattern definitions
6. `wp_hhmgt_task_states` - Custom states with colors
7. `wp_hhmgt_checklist_templates` - Reusable checklists
8. `wp_hhmgt_task_notes` - Notes with persistence flags

## Architecture

### Core Classes
- `HHMGT_Core` - Module registration, permissions, assets (✅ CORRECT object-based registration)
- `HHMGT_Settings` - Per-location settings management
- `HHMGT_Display` - Frontend rendering
- `HHMGT_Ajax` - AJAX request handlers
- `HHMGT_Heartbeat` - Real-time synchronization
- `HHMGT_Scheduler` - Recurring task generation (WP Cron)

### Module Registration (CRITICAL)
```php
// ✅ CORRECT: Use object method
public function register_module($modules_manager) {
    $modules_manager->register_module($this);
}

// ✅ REQUIRED: Implement get_config()
public function get_config() {
    return array(
        'id' => 'management_tasks',
        'name' => __('Tasks', 'hhmgt'),
        'department' => 'management',
        // ...
    );
}

// ✅ REQUIRED: Implement render()
public function render($params = array()) {
    HHMGT_Display::instance()->render($params);
}
```

### Permissions Registration (CRITICAL)
```php
// ✅ CORRECT: Use object method
public function register_permissions($permissions_manager) {
    $permissions_manager->register_permission(
        'hhmgt_tasks_access',
        __('Access Tasks Module', 'hhmgt'),
        __('View and manage tasks', 'hhmgt'),
        'Management - Tasks'
    );
}
```

## Settings Structure

### Per-Location Settings
Settings are stored indexed by `location_id`:
```php
$settings = array(
    123 => array(  // location_id
        'enabled' => true,
        'departments' => array(...),
        'recurring_patterns' => array(...),
        'task_states' => array(...)
    ),
    456 => array(...) // another location
);
```

### Default Task States
Each location gets four default states:
1. **Pending** (grey) - Initial state
2. **Due** (amber) - On due date
3. **Overdue** (red) - Past due date
4. **Complete** (green) - Completed

## Recurring Logic

### Fixed Recurring
- Tasks generated on a fixed schedule (e.g., every 7 days from original date)
- Next task created regardless of completion status
- Runs via WP Cron hourly

### Dynamic Recurring
- Tasks generated from completion date
- If completed 1 day early, next task is 7 days from early completion
- Next instance created only after current is completed
- Notes with `carry_forward` flag are copied to new instance

### Lead Time
- Tasks become visible X days before due date
- Example: 7-day interval, 3-day lead time = shown 3 days before due

## Material Symbols Icons

Icon picker includes 80+ common icons categorized by:
- Tasks (assignment_turned_in, fact_check, schedule, etc.)
- Housekeeping (cleaning_services, dry_cleaning, soap, etc.)
- Hotel (bedtime, king_bed, hotel, door_front, etc.)
- Maintenance (plumbing, electrical_services, hvac, etc.)
- Safety (fire_extinguisher, emergency, security, etc.)
- And more...

Icons stored as text: `'assignment_turned_in'`, `'cleaning_services'`, etc.

## Text Domain
`hhmgt` - Used for all translatable strings

## Constants
- `HHMGT_VERSION` - Plugin version (1.0.0)
- `HHMGT_PLUGIN_DIR` - Plugin directory path
- `HHMGT_PLUGIN_URL` - Plugin URL
- `HHMGT_PLUGIN_BASENAME` - Plugin basename

## Development Status

### Phase 1: Foundation ✅ COMPLETE
- [x] Plugin scaffolding
- [x] Database schema (8 tables)
- [x] Core class with CORRECT registration
- [x] Settings class with multi-location structure
- [x] Display class
- [x] AJAX handlers
- [x] Heartbeat for real-time sync
- [x] Scheduler for recurring tasks
- [x] Material Symbols icon data

### Phase 2: Admin Interface (Next)
- [ ] Settings pages with tabbed interface
- [ ] Icon picker component
- [ ] Department management
- [ ] Location hierarchy builder
- [ ] Pattern definitions
- [ ] Task states customization
- [ ] Checklist template creator

### Phase 3: Frontend (Pending)
- [ ] CSS styling (Material Design)
- [ ] JavaScript for list view and modals
- [ ] Photo gallery component
- [ ] Completion flow

### Phase 4: Testing (Pending)
- [ ] Module registration verification
- [ ] Permissions integration testing
- [ ] Recurring logic testing
- [ ] Multi-location testing

## Code Reusability

All core structures designed for reuse in future Maintenance Manager module:
- Generic task/instance model
- Flexible location hierarchy
- Modular checklist system
- Reusable photo components
- Shared AJAX patterns

## WP Cron

**Hook**: `hhmgt_process_recurring_tasks`
**Schedule**: Hourly
**Actions**:
1. Generate new instances for fixed recurring tasks
2. Update overdue task statuses
3. Update due task statuses

## Notes

- Always use per-location settings (never global)
- Module registration uses OBJECT methods (not arrays)
- Permissions registration uses OBJECT methods (not arrays)
- Material Symbols font loaded from Google Fonts CDN
- All database operations use transactions where appropriate
- Heartbeat interval: 30 seconds

## Support

For issues and feature requests, please contact the Hotel Hub development team.

## License

GPL v2 or later

## Author

JTR

## Version

1.0.0
