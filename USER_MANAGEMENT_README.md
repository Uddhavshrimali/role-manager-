# User Management Admin Page

## Overview

The User Management admin page provides a comprehensive interface for managing users' roles, parent relationships, programs, and sites. It includes filtering capabilities and export functionality to help administrators efficiently manage their user base.

## Features

### 🔍 Advanced Filtering
- **Role Filter**: Filter users by their assigned WordPress roles
- **Program Filter**: Filter users by their assigned program
- **Site Filter**: Filter users by their assigned sites
- **Parent User Filter**: Filter users by their parent user or show users with no parent

### 📊 User Data Display
- **User Information**: Display name, email, username
- **Role Information**: Current WordPress roles
- **Program Assignment**: Assigned program (if any)
- **Site Assignment**: Assigned sites (if any)
- **Parent Relationship**: Parent user (if any)
- **Actions**: Edit and View user links

### 📤 Export Functionality
- **CSV Export**: Export filtered user data to CSV format
- **Filtered Export**: Export only the users matching current filters
- **Comprehensive Data**: Includes all user metadata in export

### 📱 Responsive Design
- **Mobile Friendly**: Optimized for all screen sizes
- **Accessibility**: Keyboard navigation and screen reader support
- **Modern UI**: Clean, professional interface following WordPress design patterns

## Installation

The User Management admin page is automatically installed when the Role User Manager plugin is activated. No additional configuration is required.

## Usage

### Accessing the Page

1. Navigate to **Users** → **User Management** in the WordPress admin menu
2. Ensure you have the `edit_users` capability to access this page

### Filtering Users

1. **Select Filters**: Use the dropdown menus to select specific criteria
   - **Role**: Choose from available WordPress roles
   - **Program**: Select from available programs
   - **Site**: Choose from available sites
   - **Parent User**: Select a specific parent user or "No Parent"

2. **Apply Filters**: Click the "Apply Filters" button to filter the user list

3. **Clear Filters**: Click "Clear Filters" to reset all filters

### Exporting Users

1. **Set Filters** (optional): Apply any desired filters to limit the export
2. **Export**: Click the "Export Users" button
3. **Download**: The CSV file will automatically download with the current filter settings

### CSV Export Format

The exported CSV file includes the following columns:
- User ID
- Username
- Email
- Display Name
- Roles
- Program
- Sites
- Parent User ID
- Parent User Name
- Registration Date
- Last Login

## Technical Details

### File Structure

```
wp-content/plugins/role-user-manager/
├── includes/classes/UserManagement.php    # Main class file
├── assets/js/user-management.js           # JavaScript functionality
├── assets/css/user-management.css         # Styling
└── role-user-manager.php                 # Plugin initialization
```

### Dependencies

- **WordPress**: 5.6+
- **PHP**: 7.4+
- **jQuery**: Included with WordPress
- **jQuery UI**: For enhanced form controls

### Hooks and Actions

#### Admin Menu
- `admin_menu`: Adds the User Management submenu under Users

#### Scripts and Styles
- `admin_enqueue_scripts`: Enqueues required JavaScript and CSS files

#### AJAX Handlers
- `wp_ajax_rum_get_users_for_parent`: Gets users for parent dropdown
- `wp_ajax_rum_get_sites_for_program_user_management`: Gets sites for a program
- `wp_ajax_rum_get_all_sites`: Gets all available sites
- `wp_ajax_rum_export_users`: Handles CSV export

### Database Queries

The page uses WordPress's built-in user query functions:
- `get_users()`: Retrieves user data
- `get_user_meta()`: Gets user metadata (program, sites, parent)
- `get_option('dash_program_site_map')`: Retrieves program-site mappings

## Customization

### Adding Custom Filters

To add custom filters, modify the `UserManagement` class:

```php
// Add to the render_admin_page() method
<div class="rum-filter-group">
    <label for="filter_custom">Custom Filter:</label>
    <select name="filter_custom" id="filter_custom">
        <option value="">All Options</option>
        <!-- Add your options here -->
    </select>
</div>

// Add to the get_filtered_users() method
$filter_custom = sanitize_text_field($_GET['filter_custom'] ?? '');
// Add your filtering logic here
```

### Customizing Export Data

To modify the exported data, edit the `ajax_export_users()` method:

```php
// Add custom columns
fputcsv($output, [
    'User ID',
    'Username',
    'Email',
    'Custom Field', // Add your custom field
    // ... other columns
]);

// Add custom data
fputcsv($output, [
    $user->ID,
    $user->user_login,
    $user->user_email,
    get_user_meta($user->ID, 'custom_field', true), // Add your data
    // ... other data
]);
```

### Styling Customization

Modify `assets/css/user-management.css` to customize the appearance:

```css
/* Custom filter styling */
.rum-filter-group.custom-filter {
    background: #f0f0f1;
    padding: 15px;
    border-radius: 6px;
}

/* Custom table column styling */
.rum-users-table .custom-column {
    background: #f9f9f9;
    font-weight: bold;
}
```

## Troubleshooting

### Common Issues

1. **Page Not Loading**
   - Check if you have the `edit_users` capability
   - Verify the plugin is properly activated
   - Check browser console for JavaScript errors

2. **Filters Not Working**
   - Ensure the `dash_program_site_map` option exists
   - Check if user metadata is properly set
   - Verify AJAX nonce is valid

3. **Export Fails**
   - Check file permissions
   - Ensure sufficient memory for large exports
   - Verify CSV headers are properly formatted

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Performance Optimization

For large user bases:
- Consider implementing pagination (already included)
- Add database indexes on frequently queried meta fields
- Use transients for caching filter options

## Security Considerations

- **Nonce Verification**: All AJAX requests include nonce verification
- **Capability Checks**: Page access restricted to users with `edit_users` capability
- **Data Sanitization**: All user input is properly sanitized
- **SQL Injection Protection**: Uses WordPress's built-in query functions

## Browser Support

- **Chrome**: 80+
- **Firefox**: 75+
- **Safari**: 13+
- **Edge**: 80+
- **Internet Explorer**: Not supported

## Support

For issues or questions:
1. Check the WordPress error log
2. Review browser console for JavaScript errors
3. Verify plugin compatibility with your WordPress version
4. Contact plugin support with detailed error information

## Changelog

### Version 1.0.0
- Initial release
- Basic filtering functionality
- CSV export capability
- Responsive design
- Accessibility features
