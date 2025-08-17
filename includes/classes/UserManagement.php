<?php
declare(strict_types=1);

namespace RoleUserManager;

/**
 * User Management Admin Page Class
 */
class UserManagement {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_rum_export_users', [$this, 'ajax_export_users']);
        add_action('wp_ajax_rum_get_filtered_users', [$this, 'ajax_get_filtered_users']);
        add_action('wp_ajax_rum_update_user_data', [$this, 'ajax_update_user_data']);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu(): void {
        add_submenu_page(
            'users.php',
            __('User Management', 'role-user-manager'),
            __('User Management', 'role-user-manager'),
            'edit_users',
            'user-management',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts(string $hook): void {
        if ($hook !== 'users_page_user-management') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        
        // Enqueue custom CSS
        wp_enqueue_style(
            'rum-user-management',
            RUM_PLUGIN_URL . 'assets/css/user-management.css',
            [],
            '1.0.0'
        );
        
        wp_enqueue_script(
            'rum-user-management',
            RUM_PLUGIN_URL . 'assets/js/user-management.js',
            ['jquery', 'jquery-ui-datepicker'],
            '1.0.0',
            true
        );
        
        wp_localize_script('rum-user-management', 'rum_user_management', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rum_user_management_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'role-user-manager'),
                'no_users' => __('No users found.', 'role-user-manager'),
                'export_success' => __('Export completed successfully.', 'role-user-manager'),
                'export_error' => __('Export failed. Please try again.', 'role-user-manager'),
            ]
        ]);
    }
    
    /**
     * Admin page
     */
    public function admin_page(): void {
        if (!current_user_can('edit_users')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'role-user-manager'));
        }
        
        $this->render_admin_page();
    }
    
    /**
     * Render admin page
     */
    private function render_admin_page(): void {
        $programs = $this->get_programs();
        $sites = $this->get_all_sites();
        $roles = $this->get_available_roles();
        
        ?>
        <div class="wrap">
            <h1><?php _e('User Management', 'role-user-manager'); ?></h1>
            
            <!-- Filters Section -->
            <div class="rum-filters-section">
                <h2><?php _e('Filter Users', 'role-user-manager'); ?></h2>
                <form id="rum-user-filters" method="post">
                    <div class="rum-filter-row">
                        <div class="rum-filter-group">
                            <label for="filter_role"><?php _e('Role:', 'role-user-manager'); ?></label>
                            <select name="filter_role" id="filter_role">
                                <option value=""><?php _e('All Roles', 'role-user-manager'); ?></option>
                                <?php foreach ($roles as $role_key => $role_name): ?>
                                    <option value="<?php echo esc_attr($role_key); ?>"><?php echo esc_html($role_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="rum-filter-group">
                            <label for="filter_program"><?php _e('Program:', 'role-user-manager'); ?></label>
                            <select name="filter_program" id="filter_program">
                                <option value=""><?php _e('All Programs', 'role-user-manager'); ?></option>
                                <?php foreach ($programs as $program): ?>
                                    <option value="<?php echo esc_attr($program); ?>"><?php echo esc_html($program); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="rum-filter-group">
                            <label for="filter_site"><?php _e('Site:', 'role-user-manager'); ?></label>
                            <select name="filter_site" id="filter_site">
                                <option value=""><?php _e('All Sites', 'role-user-manager'); ?></option>
                                <?php foreach ($sites as $site): ?>
                                    <option value="<?php echo esc_attr($site); ?>"><?php echo esc_html($site); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        

                    </div>
                    
                    <div class="rum-filter-actions">
                        <button type="submit" class="button button-primary"><?php _e('Apply Filters', 'role-user-manager'); ?></button>
                        <button type="button" id="clear-filters" class="button"><?php _e('Clear Filters', 'role-user-manager'); ?></button>
                        <button type="button" id="export-users" class="button button-secondary"><?php _e('Export Users', 'role-user-manager'); ?></button>
                    </div>
                </form>
            </div>
            
            <!-- Users Table -->
            <div class="rum-users-section">
                <h2><?php _e('Users', 'role-user-manager'); ?></h2>
                <div id="rum-users-table-container">
                    <?php $this->render_users_table(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render users table
     */
    private function render_users_table(): void {
        $users = $this->get_filtered_users();
        $total_users = count($users);
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        $paginated_users = array_slice($users, $offset, $per_page);
        
        if (empty($paginated_users)) {
            echo '<p>' . __('No users found.', 'role-user-manager') . '</p>';
            return;
        }
        
        ?>
        <table class="rum-users-table">
            <thead>
                <tr>
                    <th><?php _e('User', 'role-user-manager'); ?></th>
                    <th><?php _e('Role', 'role-user-manager'); ?></th>
                    <th><?php _e('Program', 'role-user-manager'); ?></th>
                    <th><?php _e('Sites', 'role-user-manager'); ?></th>
                    <th><?php _e('Parent User', 'role-user-manager'); ?></th>
                    <th><?php _e('Actions', 'role-user-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($paginated_users as $user): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($user->display_name); ?></strong><br>
                            <small><?php echo esc_html($user->user_email); ?></small>
                        </td>
                        <td class="rum-editable-cell" data-field="role" data-user-id="<?php echo $user->ID; ?>">
                            <div class="rum-display-value">
                                <?php 
                                $primary_role = reset($user->roles);
                                echo esc_html(ucfirst($primary_role));
                                ?>
                                <button type="button" class="rum-edit-cell-btn button button-small" data-user-id="<?php echo $user->ID; ?>"><?php _e('Edit', 'role-user-manager'); ?></button>
                            </div>
                            <div class="rum-edit-form" style="display: none;">
                                <select class="rum-edit-select" data-original-value="<?php echo esc_attr(reset($user->roles)); ?>">
                                    <?php 
                                    $roles = $this->get_available_roles();
                                    foreach ($roles as $role_key => $role_name): 
                                        $selected = (reset($user->roles) === $role_key) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo esc_attr($role_key); ?>" <?php echo $selected; ?>><?php echo esc_html($role_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="rum-edit-actions">
                                    <button type="button" class="rum-save-btn button button-small button-primary"><?php _e('Save', 'role-user-manager'); ?></button>
                                    <button type="button" class="rum-cancel-btn button button-small"><?php _e('Cancel', 'role-user-manager'); ?></button>
                                </div>
                            </div>
                        </td>
                        <td class="rum-editable-cell" data-field="program" data-user-id="<?php echo $user->ID; ?>">
                            <div class="rum-display-value">
                                <?php 
                                $program = get_user_meta($user->ID, 'programme', true);
                                echo $program ? esc_html($program) : '<em>' . __('Not set', 'role-user-manager') . '</em>';
                                ?>
                                <button type="button" class="rum-edit-cell-btn button button-small" data-user-id="<?php echo $user->ID; ?>"><?php _e('Edit', 'role-user-manager'); ?></button>
                            </div>
                            <div class="rum-edit-form" style="display: none;">
                                <select class="rum-edit-select" data-original-value="<?php echo esc_attr($program); ?>">
                                    <option value=""><?php _e('-- Select Program --', 'role-user-manager'); ?></option>
                                    <?php 
                                    $programs = $this->get_programs();
                                    foreach ($programs as $prog): 
                                        $selected = ($prog === $program) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo esc_attr($prog); ?>" <?php echo $selected; ?>><?php echo esc_html($prog); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="rum-edit-actions">
                                    <button type="button" class="rum-save-btn button button-small button-primary"><?php _e('Save', 'role-user-manager'); ?></button>
                                    <button type="button" class="rum-cancel-btn button button-small"><?php _e('Cancel', 'role-user-manager'); ?></button>
                                </div>
                            </div>
                        </td>
                        <td class="rum-editable-cell" data-field="sites" data-user-id="<?php echo $user->ID; ?>">
                            <div class="rum-display-value">
                                <?php 
                                $sites = get_user_meta($user->ID, 'sites', true);
                                if (is_array($sites) && !empty($sites)) {
                                    echo esc_html(implode(', ', $sites));
                                } else {
                                    echo '<em>' . __('Not set', 'role-user-manager') . '</em>';
                                }
                                ?>
                                <button type="button" class="rum-edit-cell-btn button button-small" data-user-id="<?php echo $user->ID; ?>"><?php _e('Edit', 'role-user-manager'); ?></button>
                            </div>
                            <div class="rum-edit-form" style="display: none;">
                                <select class="rum-edit-select" multiple data-original-value="<?php echo esc_attr(is_array($sites) ? implode(',', $sites) : ''); ?>">
                                    <?php 
                                    $all_sites = $this->get_all_sites();
                                    foreach ($all_sites as $site): 
                                        $selected = (is_array($sites) && in_array($site, $sites)) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo esc_attr($site); ?>" <?php echo $selected; ?>><?php echo esc_html($site); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="rum-edit-actions">
                                    <button type="button" class="rum-save-btn button button-small button-primary"><?php _e('Save', 'role-user-manager'); ?></button>
                                    <button type="button" class="rum-cancel-btn button button-small"><?php _e('Cancel', 'role-user-manager'); ?></button>
                                </div>
                            </div>
                        </td>
                        <td class="rum-editable-cell" data-field="parent" data-user-id="<?php echo $user->ID; ?>">
                            <div class="rum-display-value">
                                <?php 
                                $parent_id = get_user_meta($user->ID, 'parent_user_id', true);
                                if ($parent_id) {
                                    $parent_user = get_user_by('ID', $parent_id);
                                    echo $parent_user ? esc_html($parent_user->display_name) : __('Unknown', 'role-user-manager');
                                } else {
                                    echo '<em>' . __('No parent', 'role-user-manager') . '</em>';
                                }
                                ?>
                                <button type="button" class="rum-edit-cell-btn button button-small" data-user-id="<?php echo $user->ID; ?>"><?php _e('Edit', 'role-user-manager'); ?></button>
                            </div>
                            <div class="rum-edit-form" style="display: none;">
                                <select class="rum-edit-select" data-original-value="<?php echo esc_attr($parent_id); ?>">
                                    <option value=""><?php _e('-- No Parent --', 'role-user-manager'); ?></option>
                                    <?php 
                                    $all_users = get_users(['orderby' => 'display_name', 'order' => 'ASC', 'fields' => ['ID', 'display_name']]);
                                    foreach ($all_users as $u): 
                                        if ($u->ID != $user->ID): // Prevent self-selection
                                            $selected = ($u->ID == $parent_id) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo esc_attr($u->ID); ?>" <?php echo $selected; ?>><?php echo esc_html($u->display_name); ?></option>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </select>
                                <div class="rum-edit-actions">
                                    <button type="button" class="rum-save-btn button button-small button-primary"><?php _e('Save', 'role-user-manager'); ?></button>
                                    <button type="button" class="rum-cancel-btn button button-small"><?php _e('Cancel', 'role-user-manager'); ?></button>
                                </div>
                            </div>
                        </td>
                        <td class="rum-user-actions">
                            <button type="button" class="rum-edit-cell-btn button" data-user-id="<?php echo $user->ID; ?>"><?php _e('Edit', 'role-user-manager'); ?></button>
                            <a href="<?php echo admin_url('user-edit.php?user_id=' . $user->ID); ?>" class="button"><?php _e('View', 'role-user-manager'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php $this->render_pagination($total_users, $per_page, $current_page); ?>
        <?php
    }
    
    /**
     * Render pagination
     */
    private function render_pagination(int $total_users, int $per_page, int $current_page): void {
        $total_pages = ceil($total_users / $per_page);
        
        if ($total_pages <= 1) {
            return;
        }
        
        echo '<div class="rum-pagination">';
        
        if ($current_page > 1) {
            echo '<a href="' . add_query_arg('paged', $current_page - 1) . '" class="page-numbers">&laquo; ' . __('Previous', 'role-user-manager') . '</a>';
        }
        
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i == $current_page) {
                echo '<span class="page-numbers current">' . $i . '</span>';
            } else {
                echo '<a href="' . add_query_arg('paged', $i) . '" class="page-numbers">' . $i . '</a>';
            }
        }
        
        if ($current_page < $total_pages) {
            echo '<a href="' . add_query_arg('paged', $current_page + 1) . '" class="page-numbers">' . __('Next', 'role-user-manager') . ' &raquo;</a>';
        }
        
        echo '</div>';
    }
    
    /**
     * Get filtered users
     */
    private function get_filtered_users(): array {
        $args = [
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => 'all'
        ];
        
        // Apply filters
        $filter_role = sanitize_text_field($_GET['filter_role'] ?? '');
        $filter_program = sanitize_text_field($_GET['filter_program'] ?? '');
        $filter_site = sanitize_text_field($_GET['filter_site'] ?? '');
        
        $users = get_users($args);
        
        return array_filter($users, function($user) use ($filter_role, $filter_program, $filter_site) {
            // Filter by role
            if (!empty($filter_role) && !in_array($filter_role, $user->roles)) {
                return false;
            }
            
            // Filter by program
            if (!empty($filter_program)) {
                $user_program = get_user_meta($user->ID, 'programme', true);
                if ($user_program !== $filter_program) {
                    return false;
                }
            }
            
            // Filter by site
            if (!empty($filter_site)) {
                $user_sites = get_user_meta($user->ID, 'sites', true);
                if (!is_array($user_sites) || !in_array($filter_site, $user_sites)) {
                    return false;
                }
            }
            
            return true;
        });
    }
    
    /**
     * Get available programs
     */
    private function get_programs(): array {
        $program_site_map = get_option('dash_program_site_map', []);
        return array_keys($program_site_map);
    }
    
    /**
     * Get all sites
     */
    private function get_all_sites(): array {
        $program_site_map = get_option('dash_program_site_map', []);
        $sites = [];
        foreach ($program_site_map as $program_sites) {
            if (is_array($program_sites)) {
                $sites = array_merge($sites, $program_sites);
            }
        }
        return array_unique($sites);
    }
    
    /**
     * Get available roles
     */
    private function get_available_roles(): array {
        global $wp_roles;
        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }
        
        $roles = [];
        foreach ($wp_roles->roles as $role_key => $role_data) {
            $roles[$role_key] = $role_data['name'];
        }
        
        return $roles;
    }
    
    /**
     * AJAX handler for getting filtered users
     */
    public function ajax_get_filtered_users(): void {
        check_ajax_referer('rum_user_management_nonce', 'nonce');
        
        if (!current_user_can('edit_users')) {
            wp_die(__('Insufficient permissions.', 'role-user-manager'));
        }
        
        $users = $this->get_filtered_users();
        $total_users = count($users);
        
        wp_send_json_success([
            'users' => $users,
            'total' => $total_users
        ]);
    }
    
    /**
     * AJAX handler for exporting users
     */
    public function ajax_export_users(): void {
        check_ajax_referer('rum_user_management_nonce', 'nonce');
        
        if (!current_user_can('edit_users')) {
            wp_die(__('Insufficient permissions.', 'role-user-manager'));
        }
        
        $users = $this->get_filtered_users();
        
        if (empty($users)) {
            wp_send_json_error(__('No users to export.', 'role-user-manager'));
        }
        
        $filename = 'users-export-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'User ID',
            'Username',
            'Email',
            'Display Name',
            'Roles',
            'Program',
            'Sites',
            'Parent User ID',
            'Parent User Name',
            'Registration Date',
            'Last Login'
        ]);
        
        // CSV data
        foreach ($users as $user) {
            $program = get_user_meta($user->ID, 'programme', true);
            $sites = get_user_meta($user->ID, 'sites', true);
            $parent_id = get_user_meta($user->ID, 'parent_user_id', true);
            $parent_user = $parent_id ? get_user_by('ID', $parent_id) : null;
            
            fputcsv($output, [
                $user->ID,
                $user->user_login,
                $user->user_email,
                $user->display_name,
                implode(', ', $user->roles),
                $program ?: '',
                is_array($sites) ? implode(', ', $sites) : '',
                $parent_id ?: '',
                $parent_user ? $parent_user->display_name : '',
                $user->user_registered,
                get_user_meta($user->ID, 'last_login', true) ?: ''
            ]);
        }
        
        fclose($output);
        exit;
    }

    /**
     * AJAX handler for updating user data
     */
    public function ajax_update_user_data(): void {
        try {
            if (!current_user_can('edit_users')) {
                wp_send_json_error('Insufficient permissions');
            }

            $nonce = $_POST['nonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'rum_user_management_nonce')) {
                wp_send_json_error('Security check failed');
            }

            $user_id = intval($_POST['user_id'] ?? 0);
            $field = sanitize_text_field($_POST['field'] ?? '');
            $value = $_POST['value'] ?? '';

            if (!$user_id || !$field) {
                wp_send_json_error('Invalid parameters');
            }

            $user = get_user_by('ID', $user_id);
            if (!$user) {
                wp_send_json_error('User not found');
            }

        $result = false;
        $message = '';

        switch ($field) {
            case 'role':
                // Update user role - handle more safely
                try {
                    // Check if the role exists
                    $wp_roles = new WP_Roles();
                    if (!isset($wp_roles->roles[$value])) {
                        $result = false;
                        $message = 'Invalid role specified';
                        break;
                    }
                    
                    // Remove all existing roles first
                    $user->set_role('');
                    // Then set the new role
                    $user->set_role($value);
                    
                    $result = true;
                    $message = 'User role updated successfully';
                } catch (Exception $e) {
                    $result = false;
                    $message = 'Failed to update user role: ' . $e->getMessage();
                }
                break;

            case 'program':
                // Update program meta
                $result = update_user_meta($user_id, 'programme', $value);
                $message = $result ? 'Program updated successfully' : 'Failed to update program';
                break;

            case 'sites':
                // Update sites meta
                if (is_string($value)) {
                    $value = array_filter(array_map('trim', explode(',', $value)));
                }
                $result = update_user_meta($user_id, 'sites', $value);
                $message = $result ? 'Sites updated successfully' : 'Failed to update sites';
                break;

            case 'parent':
                // Update parent user meta
                $parent_id = empty($value) ? '' : intval($value);
                $result = update_user_meta($user_id, 'parent_user_id', $parent_id);
                $message = $result ? 'Parent user updated successfully' : 'Failed to update parent user';
                break;

            default:
                wp_send_json_error('Invalid field');
        }

        if ($result) {
            // Get updated display value
            $display_value = $this->get_field_display_value($user_id, $field);
            wp_send_json_success([
                'message' => $message,
                'display_value' => $display_value
            ]);
        } else {
            wp_send_json_error($message);
        }
        } catch (Exception $e) {
            error_log('User Management AJAX Error: ' . $e->getMessage());
            error_log('User Management AJAX Error Stack: ' . $e->getTraceAsString());
            wp_send_json_error('An error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Get display value for a field
     */
    private function get_field_display_value(int $user_id, string $field): string {
        switch ($field) {
            case 'role':
                $user = get_user_by('ID', $user_id);
                $primary_role = reset($user->roles);
                return ucfirst($primary_role);

            case 'program':
                $program = get_user_meta($user_id, 'programme', true);
                return $program ?: '<em>' . __('Not set', 'role-user-manager') . '</em>';

            case 'sites':
                $sites = get_user_meta($user_id, 'sites', true);
                if (is_array($sites) && !empty($sites)) {
                    return implode(', ', $sites);
                }
                return '<em>' . __('Not set', 'role-user-manager') . '</em>';

            case 'parent':
                $parent_id = get_user_meta($user_id, 'parent_user_id', true);
                if ($parent_id) {
                    $parent_user = get_user_by('ID', $parent_id);
                    return $parent_user ? $parent_user->display_name : __('Unknown', 'role-user-manager');
                }
                return '<em>' . __('No parent', 'role-user-manager') . '</em>';

            default:
                return '';
        }
    }
}
