<?php
declare(strict_types=1);

namespace RoleUserManager;

/**
 * AJAX handler class
 */
class Ajax {
    
    /**
     * Initialize AJAX hooks
     */
    public static function init(): void {
        // Role management AJAX
        add_action('wp_ajax_rum_update_role_capabilities', [self::class, 'update_role_capabilities']);
        add_action('wp_ajax_rum_create_role', [self::class, 'create_role']);
        add_action('wp_ajax_rum_delete_role', [self::class, 'delete_role']);
        add_action('wp_ajax_rum_get_role_capabilities', [self::class, 'get_role_capabilities']);
        add_action('wp_ajax_rum_inherit_parent_capabilities', [self::class, 'inherit_parent_capabilities']);
        add_action('wp_ajax_rum_get_parent_options', [self::class, 'get_parent_options']);
        
        // Dashboard AJAX
        add_action('wp_ajax_rum_get_user_details', [self::class, 'get_user_details']);
        add_action('wp_ajax_rum_delete_user', [self::class, 'delete_user']);
        add_action('wp_ajax_rum_get_sites_for_program', [self::class, 'get_sites_for_program']);
        add_action('wp_ajax_rum_promote_user_direct', [self::class, 'promote_user_direct']);
        add_action('wp_ajax_rum_submit_promotion_request', [self::class, 'submit_promotion_request']);
        
        // Additional dashboard AJAX handlers
        add_action('wp_ajax_rum_get_user_ld_data', [self::class, 'get_user_ld_data']);
        add_action('wp_ajax_rum_remove_user_from_course', [self::class, 'remove_user_from_course']);
        add_action('wp_ajax_rum_bulk_user_action', [self::class, 'bulk_user_action']);
        add_action('wp_ajax_rum_export_users', [self::class, 'export_users']);
        add_action('wp_ajax_rum_export_user_descendants', [self::class, 'export_user_descendants']);
        
        // Workflow AJAX
        add_action('wp_ajax_rum_approve_promotion_request', [self::class, 'approve_promotion_request']);
        add_action('wp_ajax_rum_reject_promotion_request', [self::class, 'reject_promotion_request']);
        add_action('wp_ajax_rum_get_promotion_requests', [self::class, 'get_promotion_requests']);
    }
    
    /**
     * Send JSON response
     */
    private static function send_response(bool $success, $data = null, string $message = ''): void {
        wp_send_json([
            'success' => $success,
            'data' => $data,
            'message' => $message,
        ]);
    }
    
    /**
     * Send error response
     */
    private static function send_error(string $message, $data = null): void {
        self::send_response(false, $data, $message);
    }
    
    /**
     * Send success response
     */
    private static function send_success($data = null, string $message = ''): void {
        self::send_response(true, $data, $message);
    }
    
    /**
     * Verify nonce
     */
    private static function verify_nonce(string $nonce, string $action): bool {
        return Validator::validate_nonce($nonce, $action);
    }
    
    /**
     * Update role capabilities
     */
    public static function update_role_capabilities(): void {
        if (!current_user_can('manage_options')) {
            self::send_error('Insufficient permissions');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!self::verify_nonce($nonce, 'rum_nonce')) {
            self::send_error('Invalid nonce');
        }
        
        $role = Validator::sanitize_role($_POST['role'] ?? '');
        $capabilities = $_POST['capabilities'] ?? [];
        
        if (!Validator::validate_role($role)) {
            self::send_error('Invalid role');
        }
        
        $role_manager = \RoleUserManager::getInstance()->getComponent('role_manager');
        if ($role_manager) {
            $result = $role_manager->update_role_capabilities($role, $capabilities);
            if ($result) {
                Logger::log_capability_change($role, [], $capabilities);
                self::send_success(null, 'Role capabilities updated successfully');
            } else {
                self::send_error('Failed to update role capabilities');
            }
        } else {
            self::send_error('Role manager not available');
        }
    }
    
    /**
     * Create new role
     */
    public static function create_role(): void {
        if (!current_user_can('manage_options')) {
            self::send_error('Insufficient permissions');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!self::verify_nonce($nonce, 'rum_nonce')) {
            self::send_error('Invalid nonce');
        }
        
        $role_name = Validator::sanitize_role($_POST['role_name'] ?? '');
        $role_display_name = Validator::sanitize_text($_POST['role_display_name'] ?? '');
        $parent_role = Validator::sanitize_role($_POST['parent_role'] ?? '');
        
        if (!Validator::validate_role($role_name)) {
            self::send_error('Invalid role name');
        }
        
        $role_manager = \RoleUserManager::getInstance()->getComponent('role_manager');
        if ($role_manager) {
            $result = $role_manager->create_role($role_name, $role_display_name, $parent_role);
            if ($result) {
                Logger::log("New role created: {$role_name}");
                self::send_success(null, 'Role created successfully');
            } else {
                self::send_error('Failed to create role');
            }
        } else {
            self::send_error('Role manager not available');
        }
    }
    
    /**
     * Delete role
     */
    public static function delete_role(): void {
        if (!current_user_can('manage_options')) {
            self::send_error('Insufficient permissions');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!self::verify_nonce($nonce, 'rum_nonce')) {
            self::send_error('Invalid nonce');
        }
        
        $role = Validator::sanitize_role($_POST['role'] ?? '');
        
        if (!Validator::validate_role($role)) {
            self::send_error('Invalid role');
        }
        
        $role_manager = \RoleUserManager::getInstance()->getComponent('role_manager');
        if ($role_manager) {
            $result = $role_manager->delete_role($role);
            if ($result) {
                Logger::log("Role deleted: {$role}");
                self::send_success(null, 'Role deleted successfully');
            } else {
                self::send_error('Failed to delete role');
            }
        } else {
            self::send_error('Role manager not available');
        }
    }
    
    /**
     * Get role capabilities
     */
    public static function get_role_capabilities(): void {
        if (!current_user_can('manage_options')) {
            self::send_error('Insufficient permissions');
        }
        
        $role = Validator::sanitize_role($_POST['role'] ?? '');
        
        if (!Validator::validate_role($role)) {
            self::send_error('Invalid role');
        }
        
        $role_manager = \RoleUserManager::getInstance()->getComponent('role_manager');
        if ($role_manager) {
            $capabilities = $role_manager->get_role_capabilities($role);
            self::send_success($capabilities);
        } else {
            self::send_error('Role manager not available');
        }
    }
    
    /**
     * Inherit parent capabilities
     */
    public static function inherit_parent_capabilities(): void {
        if (!current_user_can('manage_options')) {
            self::send_error('Insufficient permissions');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!self::verify_nonce($nonce, 'rum_nonce')) {
            self::send_error('Invalid nonce');
        }
        
        $role = Validator::sanitize_role($_POST['role'] ?? '');
        
        if (!Validator::validate_role($role)) {
            self::send_error('Invalid role');
        }
        
        $role_manager = \RoleUserManager::getInstance()->getComponent('role_manager');
        if ($role_manager) {
            $result = $role_manager->inherit_parent_capabilities($role);
            if ($result) {
                Logger::log("Inherited parent capabilities for role: {$role}");
                self::send_success(null, 'Parent capabilities inherited successfully');
            } else {
                self::send_error('Failed to inherit parent capabilities');
            }
        } else {
            self::send_error('Role manager not available');
        }
    }
    
    /**
     * Get parent role options
     */
    public static function get_parent_options(): void {
        if (!current_user_can('manage_options')) {
            self::send_error('Insufficient permissions');
        }
        
        $role_manager = \RoleUserManager::getInstance()->getComponent('role_manager');
        if ($role_manager) {
            $roles = $role_manager->get_available_parent_roles();
            self::send_success($roles);
        } else {
            self::send_error('Role manager not available');
        }
    }
    
    /**
     * Get user details
     */
    public static function get_user_details(): void {
        if (!current_user_can('list_users')) {
            self::send_error('Insufficient permissions');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!self::verify_nonce($nonce, 'dashboard_nonce')) {
            self::send_error('Invalid nonce');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!Validator::validate_user_id($user_id)) {
            self::send_error('Invalid user ID');
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            self::send_error('User not found');
        }

        // Get current user's role for permission checking
        $current_user = wp_get_current_user();
        $current_user_roles = $current_user->roles;
        $user_role = $user->roles[0] ?? '';
        
        // Get basic user data
        $user_data = [
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'role' => $user_role,
            'role_display' => self::get_role_display_name($user_role),
            'program' => get_user_meta($user->ID, 'program', true),
            'site' => get_user_meta($user->ID, 'site', true),
            'registration_date' => $user->user_registered,
            'parent_user_id' => get_user_meta($user->ID, 'parent_user_id', true),
        ];

        // Get parent user details
        if ($user_data['parent_user_id']) {
            $parent_user = get_user_by('id', $user_data['parent_user_id']);
            $user_data['parent_name'] = $parent_user ? $parent_user->display_name : 'Unknown';
        }

        // Get LearnDash data if available
        if (function_exists('learndash_user_get_enrolled_courses')) {
            $enrolled_courses = learndash_user_get_enrolled_courses($user->ID);
            $completed_courses = [];
            $certificates = [];
            
            foreach ($enrolled_courses as $course_id) {
                if (learndash_course_completed($user->ID, $course_id)) {
                    $completed_courses[] = $course_id;
                }
            }
            
            // Get certificates
            if (function_exists('learndash_get_user_certificates')) {
                $certificates = learndash_get_user_certificates($user->ID);
            }
            
            $user_data['training'] = [
                'courses_enrolled' => count($enrolled_courses),
                'courses_completed' => count($completed_courses),
                'certificates_earned' => count($certificates),
                'completion_rate' => count($enrolled_courses) > 0 ? round((count($completed_courses) / count($enrolled_courses)) * 100, 1) : 0
            ];
        }

        // Get descendants if user is program leader or site supervisor
        $descendants = [];
        if (in_array($user_role, ['program-leader', 'site-supervisor'])) {
            $descendants = self::get_user_descendants($user->ID);
        }
        $user_data['descendants'] = $descendants;

        // Check if current user can export (admin, program leader, or data viewer)
        $can_export = in_array('administrator', $current_user_roles) || 
                     in_array('program-leader', $current_user_roles) || 
                     in_array('data-viewer', $current_user_roles);
        $user_data['can_export'] = $can_export;

        // Generate HTML content for the modal
        $html = self::generate_user_details_html($user_data);
        
        self::send_success(['user_data' => $user_data, 'html' => $html]);
    }

    /**
     * Get role display name
     */
    private static function get_role_display_name(string $role): string {
        $role_names = [
            'administrator' => __('Administrator', 'role-user-manager'),
            'data-viewer' => __('Data Viewer', 'role-user-manager'),
            'program-leader' => __('Program Leader', 'role-user-manager'),
            'site-supervisor' => __('Site Supervisor', 'role-user-manager'),
            'frontline-staff' => __('Frontline Staff', 'role-user-manager'),
        ];
        
        return $role_names[$role] ?? ucfirst(str_replace(['-', '_'], ' ', $role));
    }

    /**
     * Get user descendants
     */
    private static function get_user_descendants(int $parent_id): array {
        $all_users = get_users(['fields' => ['ID', 'display_name', 'user_email', 'user_registered']]);
        return self::get_descendant_user_ids_with_details($parent_id, $all_users);
    }

    /**
     * Get descendant user IDs with details
     */
    private static function get_descendant_user_ids_with_details(int $parent_id, array $all_users, int $depth = 0): array {
        if ($depth > 10) { // Prevent infinite loops
            return [];
        }

        $descendants = [];
        foreach ($all_users as $user) {
            $user_parent = get_user_meta($user->ID, 'parent_user_id', true);
            if ($user_parent && intval($user_parent) === intval($parent_id)) {
                $user_role = get_user_meta($user->ID, 'wp_capabilities', true);
                $user_role = is_array($user_role) ? array_keys($user_role)[0] : '';
                
                $descendant = [
                    'id' => $user->ID,
                    'display_name' => $user->display_name,
                    'email' => $user->user_email,
                    'role' => $user_role,
                    'role_display' => self::get_role_display_name($user_role),
                    'program' => get_user_meta($user->ID, 'program', true),
                    'site' => get_user_meta($user->ID, 'site', true),
                    'registration_date' => $user->user_registered,
                    'depth' => $depth + 1,
                    'children' => self::get_descendant_user_ids_with_details($user->ID, $all_users, $depth + 1)
                ];
                
                // Get training data if LearnDash is available
                if (function_exists('learndash_user_get_enrolled_courses')) {
                    $enrolled = learndash_user_get_enrolled_courses($user->ID);
                    $completed = 0;
                    foreach ($enrolled as $course_id) {
                        if (learndash_course_completed($user->ID, $course_id)) {
                            $completed++;
                        }
                    }
                    $descendant['training'] = [
                        'courses_enrolled' => count($enrolled),
                        'courses_completed' => $completed,
                        'completion_rate' => count($enrolled) > 0 ? round(($completed / count($enrolled)) * 100, 1) : 0
                    ];
                }
                
                $descendants[] = $descendant;
            }
        }
        return $descendants;
    }

    /**
     * Generate HTML content for user details modal
     */
    private static function generate_user_details_html(array $user_data): string {
        ob_start();
        ?>
        <div class="user-details-modal-content">
            <!-- Navigation Tabs -->
            <div class="modal-tabs">
                <button class="tab-button active" data-tab="details"><?php _e('User Details', 'role-user-manager'); ?></button>
                <?php if (!empty($user_data['descendants'])): ?>
                    <button class="tab-button" data-tab="descendants"><?php _e('Team Members', 'role-user-manager'); ?> (<?php echo count($user_data['descendants']); ?>)</button>
                <?php endif; ?>
                <?php if ($user_data['can_export'] && !empty($user_data['descendants'])): ?>
                    <button class="tab-button" data-tab="export"><?php _e('Export', 'role-user-manager'); ?></button>
                <?php endif; ?>
            </div>

            <!-- User Details Tab -->
            <div class="tab-content active" id="details-tab">
                <div class="user-info-grid">
                    <div class="info-section">
                        <h4><?php _e('Basic Information', 'role-user-manager'); ?></h4>
                        <div class="info-row">
                            <span class="label"><?php _e('Name:', 'role-user-manager'); ?></span>
                            <span class="value"><?php echo esc_html($user_data['display_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label"><?php _e('Email:', 'role-user-manager'); ?></span>
                            <span class="value"><?php echo esc_html($user_data['email']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label"><?php _e('Username:', 'role-user-manager'); ?></span>
                            <span class="value"><?php echo esc_html($user_data['username']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label"><?php _e('Role:', 'role-user-manager'); ?></span>
                            <span class="value role-badge role-<?php echo esc_attr($user_data['role']); ?>">
                                <?php echo esc_html($user_data['role_display']); ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="label"><?php _e('Registration Date:', 'role-user-manager'); ?></span>
                            <span class="value"><?php echo esc_html(date('M j, Y', strtotime($user_data['registration_date']))); ?></span>
                        </div>
                    </div>

                    <div class="info-section">
                        <h4><?php _e('Assignment', 'role-user-manager'); ?></h4>
                        <div class="info-row">
                            <span class="label"><?php _e('Program:', 'role-user-manager'); ?></span>
                            <span class="value"><?php echo esc_html($user_data['program'] ?: 'Not assigned'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label"><?php _e('Site:', 'role-user-manager'); ?></span>
                            <span class="value"><?php echo esc_html($user_data['site'] ?: 'Not assigned'); ?></span>
                        </div>
                        <?php if (!empty($user_data['parent_name'])): ?>
                        <div class="info-row">
                            <span class="label"><?php _e('Reports To:', 'role-user-manager'); ?></span>
                            <span class="value"><?php echo esc_html($user_data['parent_name']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (isset($user_data['training'])): ?>
                    <div class="info-section">
                        <h4><?php _e('Training Progress', 'role-user-manager'); ?></h4>
                        <div class="training-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo esc_html($user_data['training']['courses_enrolled']); ?></span>
                                <span class="stat-label"><?php _e('Enrolled', 'role-user-manager'); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo esc_html($user_data['training']['courses_completed']); ?></span>
                                <span class="stat-label"><?php _e('Completed', 'role-user-manager'); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo esc_html($user_data['training']['certificates_earned']); ?></span>
                                <span class="stat-label"><?php _e('Certificates', 'role-user-manager'); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo esc_html($user_data['training']['completion_rate']); ?>%</span>
                                <span class="stat-label"><?php _e('Completion', 'role-user-manager'); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Descendants Tab -->
            <?php if (!empty($user_data['descendants'])): ?>
            <div class="tab-content" id="descendants-tab">
                <div class="descendants-section">
                    <h4><?php _e('Team Members', 'role-user-manager'); ?></h4>
                    <div class="descendants-list">
                        <?php echo self::render_descendants_tree($user_data['descendants']); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Export Tab -->
            <?php if ($user_data['can_export'] && !empty($user_data['descendants'])): ?>
            <div class="tab-content" id="export-tab">
                <div class="export-section">
                    <h4><?php _e('Export Team Data', 'role-user-manager'); ?></h4>
                    <p><?php _e('Export data for this user and all their team members.', 'role-user-manager'); ?></p>
                    
                    <div class="export-options">
                        <label>
                            <input type="checkbox" name="include_basic" checked> 
                            <?php _e('Basic Information (Name, Email, Role)', 'role-user-manager'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="include_assignment" checked> 
                            <?php _e('Assignment (Program, Site)', 'role-user-manager'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="include_training" checked> 
                            <?php _e('Training Progress', 'role-user-manager'); ?>
                        </label>
                    </div>
                    
                    <div class="export-actions">
                        <button type="button" class="button button-primary" onclick="exportUserData(<?php echo $user_data['id']; ?>)">
                            <?php _e('Export as CSV', 'role-user-manager'); ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Tab switching functionality
            $('.tab-button').on('click', function() {
                var tabName = $(this).data('tab');
                
                // Update active tab button
                $('.tab-button').removeClass('active');
                $(this).addClass('active');
                
                // Update active tab content
                $('.tab-content').removeClass('active');
                $('#' + tabName + '-tab').addClass('active');
            });
        });

        function exportUserData(userId) {
            var options = {
                include_basic: $('input[name="include_basic"]').is(':checked'),
                include_assignment: $('input[name="include_assignment"]').is(':checked'),
                include_training: $('input[name="include_training"]').is(':checked')
            };
            
            // Create form and submit
            var form = $('<form>', {
                method: 'POST',
                action: ajaxurl
            });
            
            form.append($('<input>', { type: 'hidden', name: 'action', value: 'rum_export_user_descendants' }));
            form.append($('<input>', { type: 'hidden', name: 'user_id', value: userId }));
            form.append($('<input>', { type: 'hidden', name: 'nonce', value: '<?php echo wp_create_nonce('export_user_descendants'); ?>' }));
            form.append($('<input>', { type: 'hidden', name: 'options', value: JSON.stringify(options) }));
            
            $('body').append(form);
            form.submit();
            form.remove();
        }
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render descendants tree
     */
    private static function render_descendants_tree(array $descendants, int $level = 0): string {
        $html = '<div class="descendants-tree-level level-' . $level . '">';
        
        foreach ($descendants as $descendant) {
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
            $html .= '<div class="descendant-item">';
            $html .= '<div class="descendant-info">';
            $html .= $indent . '<strong>' . esc_html($descendant['display_name']) . '</strong>';
            $html .= ' <span class="role-badge role-' . esc_attr($descendant['role']) . '">' . esc_html($descendant['role_display']) . '</span>';
            $html .= '<br>' . $indent . '<small>' . esc_html($descendant['email']) . '</small>';
            if ($descendant['program']) {
                $html .= '<br>' . $indent . '<small>Program: ' . esc_html($descendant['program']) . '</small>';
            }
            if ($descendant['site']) {
                $html .= '<br>' . $indent . '<small>Site: ' . esc_html($descendant['site']) . '</small>';
            }
            if (isset($descendant['training'])) {
                $html .= '<br>' . $indent . '<small>Training: ' . $descendant['training']['courses_completed'] . '/' . $descendant['training']['courses_enrolled'] . ' (' . $descendant['training']['completion_rate'] . '%)</small>';
            }
            $html .= '</div>';
            
            // Render children recursively
            if (!empty($descendant['children'])) {
                $html .= self::render_descendants_tree($descendant['children'], $level + 1);
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Delete user
     */
    public static function delete_user(): void {
        if (!current_user_can('delete_users')) {
            self::send_error('Insufficient permissions');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!self::verify_nonce($nonce, 'dashboard_nonce')) {
            self::send_error('Invalid nonce');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!Validator::validate_user_id($user_id)) {
            self::send_error('Invalid user ID');
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            self::send_error('User not found');
        }
        
        $result = wp_delete_user($user_id);
        if ($result) {
            Logger::log("User deleted: {$user->user_login} (ID: {$user_id})");
            self::send_success(null, 'User deleted successfully');
        } else {
            self::send_error('Failed to delete user');
        }
    }
    
    /**
     * Get sites for program
     */
    public static function get_sites_for_program(): void {
        $nonce = $_POST['nonce'] ?? '';
        if (!self::verify_nonce($nonce, 'dashboard_nonce')) {
            self::send_error('Invalid nonce');
        }
        
        $program = Validator::sanitize_text($_POST['program'] ?? '');
        
        if (empty($program)) {
            self::send_error('Program is required');
        }
        
        global $wpdb;
        $sites = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT site FROM {$wpdb->prefix}rum_program_sites WHERE program = %s ORDER BY site",
            $program
        ));
        
        self::send_success($sites);
    }
    
    /**
     * Get user LearnDash data (for modal)
     */
    public static function get_user_ld_data(): void {
        if (!is_user_logged_in()) {
            self::send_error('User not logged in');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!self::verify_nonce($nonce, 'dashboard_nonce')) {
            self::send_error('Invalid nonce');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        if (!$user_id) {
            self::send_error('No user ID provided');
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            self::send_error('User not found');
        }
        
        // Get user metadata
        $parent_id = get_user_meta($user_id, 'parent_user_id', true);
        $program = get_user_meta($user_id, 'program', true);
        $site = get_user_meta($user_id, 'site', true);
        $parent_name = $parent_id ? get_user_by('id', $parent_id)->display_name : 'None';
        
        // Start building HTML
        $html = '<div class="user-edit-info">';
        $html .= '<h6>' . __('User Information', 'role-user-manager') . '</h6>';
        $html .= '<div class="row">';
        $html .= '<div class="col-md-6">';
        $html .= '<p><strong>' . __('Name:', 'role-user-manager') . '</strong> ' . esc_html($user->display_name) . '</p>';
        $html .= '<p><strong>' . __('Email:', 'role-user-manager') . '</strong> ' . esc_html($user->user_email) . '</p>';
        $html .= '<p><strong>' . __('Username:', 'role-user-manager') . '</strong> ' . esc_html($user->user_login) . '</p>';
        $html .= '</div>';
        $html .= '<div class="col-md-6">';
        $html .= '<p><strong>' . __('Role:', 'role-user-manager') . '</strong> ' . esc_html(implode(', ', $user->roles)) . '</p>';
        $html .= '<p><strong>' . __('Parent:', 'role-user-manager') . '</strong> ' . esc_html($parent_name) . '</p>';
        $html .= '<p><strong>' . __('Program:', 'role-user-manager') . '</strong> ' . esc_html($program ?: 'None') . '</p>';
        $html .= '<p><strong>' . __('Site:', 'role-user-manager') . '</strong> ' . esc_html($site ?: 'None') . '</p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div><hr>';
        
        // Get LearnDash stats if available
        $dashboard = \RoleUserManager::getInstance()->getComponent('dashboard');
        if ($dashboard) {
            $stats = $dashboard->get_user_stats($user_id);
            
            $html .= '<h6>' . __('Learning Statistics', 'role-user-manager') . '</h6>';
            $html .= '<div class="row">';
            $html .= '<div class="col-md-3"><strong>' . __('Courses Enrolled:', 'role-user-manager') . '</strong> ' . esc_html($stats['courses_enrolled']) . '</div>';
            $html .= '<div class="col-md-3"><strong>' . __('Courses Completed:', 'role-user-manager') . '</strong> ' . esc_html($stats['courses_completed']) . '</div>';
            $html .= '<div class="col-md-3"><strong>' . __('Assignments:', 'role-user-manager') . '</strong> ' . esc_html($stats['assignments_submitted']) . '</div>';
            $html .= '<div class="col-md-3"><strong>' . __('Certificates:', 'role-user-manager') . '</strong> ' . esc_html($stats['certificates_earned']) . '</div>';
            $html .= '</div>';
        }
        
        self::send_success(['html' => $html]);
    }
    
    /**
     * Remove user from course
     */
    public static function remove_user_from_course(): void {
        if (!current_user_can('edit_users')) {
            self::send_error('Insufficient permissions');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!self::verify_nonce($nonce, 'dashboard_nonce')) {
            self::send_error('Invalid nonce');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $course_id = intval($_POST['course_id'] ?? 0);
        
        if (!$user_id || !$course_id) {
            self::send_error('Missing user or course ID');
        }
        
        // Check if LearnDash function exists
        if (!function_exists('ld_update_course_access')) {
            self::send_error('LearnDash function not available');
        }
        
        // Unenroll user from course
        $result = ld_update_course_access($user_id, $course_id, $remove = true);
        
        if ($result) {
            self::send_success(null, 'User removed from course');
        } else {
            self::send_error('Failed to remove user from course');
        }
    }
    
    /**
     * Bulk user action
     */
    public static function bulk_user_action(): void {
        if (!current_user_can('edit_users')) {
            self::send_error('Insufficient permissions');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!self::verify_nonce($nonce, 'dashboard_nonce')) {
            self::send_error('Invalid nonce');
        }
        
        $user_ids = isset($_POST['users']) && is_array($_POST['users']) ? array_map('intval', $_POST['users']) : [];
        $action = Validator::sanitize_text($_POST['bulk_action'] ?? '');
        $role = Validator::sanitize_text($_POST['bulk_role'] ?? '');
        
        if (empty($user_ids) || !$action) {
            self::send_error('No users or action specified');
        }
        
        $success = 0;
        $fail = 0;
        
        foreach ($user_ids as $uid) {
            if ($action === 'remove') {
                require_once ABSPATH . 'wp-admin/includes/user.php';
                $result = wp_delete_user($uid);
                if ($result) {
                    $success++;
                } else {
                    $fail++;
                }
            } elseif ($action === 'assign_role' && $role) {
                $u = get_userdata($uid);
                if ($u) {
                    $u->set_role($role);
                    $success++;
                } else {
                    $fail++;
                }
            }
        }
        
        if ($success > 0) {
            self::send_success(null, "Bulk action completed: {$success} success, {$fail} failed");
        } else {
            self::send_error('No users updated');
        }
    }
    
    /**
     * Export users
     */
    public static function export_users(): void {
        if (!current_user_can('edit_users') || current_user_can('data_viewer_export')) {
            self::send_error('Insufficient permissions');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!self::verify_nonce($nonce, 'dashboard_nonce')) {
            self::send_error('Invalid nonce');
        }
        
        // Get filter parameters
        $filter_program = Validator::sanitize_text($_POST['filter_program'] ?? '');
        $filter_site = Validator::sanitize_text($_POST['filter_site'] ?? '');
        $filter_training_status = Validator::sanitize_text($_POST['filter_training_status'] ?? '');
        $filter_date_start = Validator::sanitize_text($_POST['filter_date_start'] ?? '');
        $filter_date_end = Validator::sanitize_text($_POST['filter_date_end'] ?? '');
        
        // Get all users
        $all_users = get_users(['orderby' => 'display_name', 'order' => 'ASC', 'fields' => 'all']);
        
        // Apply filters (simplified version)
        $filtered_users = array_filter($all_users, function ($user) use ($filter_program, $filter_site, $filter_training_status, $filter_date_start, $filter_date_end) {
            // Filter by program
            if (!empty($filter_program)) {
                $user_program = get_user_meta($user->ID, 'program', true);
                if ($user_program !== $filter_program) {
                    return false;
                }
            }
            
            // Filter by site
            if (!empty($filter_site)) {
                $user_site = get_user_meta($user->ID, 'site', true);
                if ($user_site !== $filter_site) {
                    return false;
                }
            }
            
            return true;
        });
        
        // Prepare CSV data
        $csv_data = [];
        $csv_data[] = [
            'Name',
            'Email',
            'Role',
            'Program',
            'Site',
            'Registration Date'
        ];
        
        foreach ($filtered_users as $user) {
            $program = get_user_meta($user->ID, 'program', true);
            $site = get_user_meta($user->ID, 'site', true);
            
            $csv_data[] = [
                $user->display_name,
                $user->user_email,
                implode(', ', $user->roles),
                $program ?: '—',
                $site ?: '—',
                $user->user_registered
            ];
        }
        
        // Generate CSV content
        $csv_content = '';
        foreach ($csv_data as $row) {
            $csv_content .= '"' . implode('","', array_map(function ($field) {
                return str_replace('"', '""', strval($field));
            }, $row)) . '"' . "\n";
        }
        
        $filename = 'users_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        self::send_success([
            'csv_content' => $csv_content,
            'filename' => $filename,
            'count' => count($filtered_users)
        ]);
    }
    
    /**
     * Promote user directly
     */
    public static function promote_user_direct(): void {
        if (!current_user_can('edit_users')) {
            self::send_error('Insufficient permissions');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!self::verify_nonce($nonce, 'arc_dashboard_nonce')) {
            self::send_error('Invalid nonce');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $requested_role = Validator::sanitize_role($_POST['requested_role'] ?? '');
        
        if (!Validator::validate_user_id($user_id)) {
            self::send_error('Invalid user ID');
        }
        
        if (!Validator::validate_role($requested_role)) {
            self::send_error('Invalid role');
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            self::send_error('User not found');
        }
        
        $old_role = $user->roles[0] ?? '';
        $user->set_role($requested_role);
        
        // Set the promoter as the parent of the promoted user
        $promoter_id = get_current_user_id();
        update_user_meta($user_id, 'parent_user_id', $promoter_id);
        error_log("Set parent user to promoter: {$promoter_id} for user: {$user_id}");
        
        Logger::log_role_change($user_id, $old_role, $requested_role);
        self::send_success(null, 'User promoted successfully');
    }
    
    /**
     * Submit promotion request
     */
    public static function submit_promotion_request(): void {
        if (!is_user_logged_in()) {
            self::send_error('User not logged in');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!self::verify_nonce($nonce, 'arc_dashboard_nonce')) {
            self::send_error('Invalid nonce');
        }
        
        $requester_id = get_current_user_id();
        $user_id = intval($_POST['user_id'] ?? 0);
        $requested_role = Validator::sanitize_role($_POST['requested_role'] ?? '');
        $reason = Validator::sanitize_text($_POST['reason'] ?? '');
        
        // Get current role from user data
        $user = get_user_by('id', $user_id);
        if (!$user) {
            self::send_error('User not found');
        }
        $current_role = $user->roles[0] ?? '';
        
        $errors = Validator::validate_promotion_request($requester_id, $user_id, $current_role, $requested_role, $reason);
        if (!empty($errors)) {
            self::send_error(implode(', ', $errors));
        }
        
        $workflow = \RoleUserManager::getInstance()->getComponent('workflow');
        if ($workflow) {
            $request_id = $workflow->create_promotion_request($requester_id, $user_id, $current_role, $requested_role, $reason);
            if ($request_id) {
                Logger::log_promotion_request($requester_id, $user_id, $current_role, $requested_role, $reason);
                self::send_success(['request_id' => $request_id], 'Promotion request submitted successfully');
            } else {
                self::send_error('Failed to submit promotion request');
            }
        } else {
            self::send_error('Workflow manager not available');
        }
    }
    
    /**
     * Approve promotion request
     */
    public static function approve_promotion_request(): void {
        if (!current_user_can('edit_users')) {
            self::send_error('Insufficient permissions');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!self::verify_nonce($nonce, 'workflow_nonce')) {
            self::send_error('Invalid nonce');
        }
        
        $request_id = intval($_POST['request_id'] ?? 0);
        $admin_notes = Validator::sanitize_text($_POST['admin_notes'] ?? '');
        
        if ($request_id <= 0) {
            self::send_error('Invalid request ID');
        }
        
        $workflow = \RoleUserManager::getInstance()->getComponent('workflow');
        if ($workflow) {
            $result = $workflow->approve_request($request_id, $admin_notes);
            if ($result) {
                Logger::log("Promotion request approved: {$request_id}");
                self::send_success(null, 'Promotion request approved successfully');
            } else {
                self::send_error('Failed to approve promotion request');
            }
        } else {
            self::send_error('Workflow manager not available');
        }
    }
    
    /**
     * Reject promotion request
     */
    public static function reject_promotion_request(): void {
        if (!current_user_can('edit_users')) {
            self::send_error('Insufficient permissions');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!self::verify_nonce($nonce, 'workflow_nonce')) {
            self::send_error('Invalid nonce');
        }
        
        $request_id = intval($_POST['request_id'] ?? 0);
        $admin_notes = Validator::sanitize_text($_POST['admin_notes'] ?? '');
        
        if ($request_id <= 0) {
            self::send_error('Invalid request ID');
        }
        
        $workflow = \RoleUserManager::getInstance()->getComponent('workflow');
        if ($workflow) {
            $result = $workflow->reject_request($request_id, $admin_notes);
            if ($result) {
                Logger::log("Promotion request rejected: {$request_id}");
                self::send_success(null, 'Promotion request rejected successfully');
            } else {
                self::send_error('Failed to reject promotion request');
            }
        } else {
            self::send_error('Workflow manager not available');
        }
    }
    
    /**
     * Get promotion requests
     */
    public static function get_promotion_requests(): void {
        if (!current_user_can('edit_users')) {
            self::send_error('Insufficient permissions');
        }
        
        $workflow = \RoleUserManager::getInstance()->getComponent('workflow');
        if ($workflow) {
            $requests = $workflow->get_promotion_requests();
            self::send_success($requests);
        } else {
            self::send_error('Workflow manager not available');
        }
    }
    
    /**
     * Get users for parent dropdown
     */
    public static function get_users_for_parent(): void {
        if (!current_user_can('edit_users')) {
            self::send_error('Insufficient permissions');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!self::verify_nonce($nonce, 'rum_user_management_nonce')) {
            self::send_error('Invalid nonce');
        }
        
        $users = get_users([
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => ['ID', 'display_name', 'user_email']
        ]);
        
        self::send_success(['users' => $users]);
    }
    
    /**
     * Get sites for a specific program (for user management)
     */
    public static function get_sites_for_program_user_management(): void {
        if (!current_user_can('edit_users')) {
            self::send_error('Insufficient permissions');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!self::verify_nonce($nonce, 'rum_user_management_nonce')) {
            self::send_error('Invalid nonce');
        }
        
        $program = sanitize_text_field($_POST['program'] ?? '');
        if (empty($program)) {
            self::send_error('Program is required');
        }
        
        $program_site_map = get_option('dash_program_site_map', []);
        $sites = $program_site_map[$program] ?? [];
        
        self::send_success(['sites' => $sites]);
    }
    
    /**
     * Get all available sites
     */
    public static function get_all_sites(): void {
        if (!current_user_can('edit_users')) {
            self::send_error('Insufficient permissions');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!self::verify_nonce($nonce, 'rum_user_management_nonce')) {
            self::send_error('Invalid nonce');
        }
        
        $program_site_map = get_option('dash_program_site_map', []);
        $sites = [];
        
        foreach ($program_site_map as $program_sites) {
            if (is_array($program_sites)) {
                $sites = array_merge($sites, $program_sites);
            }
        }
        
        $sites = array_unique($sites);
        sort($sites);
        
        self::send_success(['sites' => $sites]);
    }

    /**
     * Export user descendants data as CSV
     */
    public static function export_user_descendants(): void {
        // Check permissions
        $current_user = wp_get_current_user();
        $current_user_roles = $current_user->roles;
        
        $can_export = in_array('administrator', $current_user_roles) || 
                     in_array('program-leader', $current_user_roles) || 
                     in_array('data-viewer', $current_user_roles);
                     
        if (!$can_export) {
            self::send_error('Insufficient permissions to export data');
        }
        
        // Verify nonce
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'export_user_descendants')) {
            self::send_error('Invalid nonce');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $options = json_decode($_POST['options'] ?? '{}', true);
        
        if (!$user_id) {
            self::send_error('Invalid user ID');
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            self::send_error('User not found');
        }
        
        // Get user and descendants data
        $descendants = self::get_user_descendants($user_id);
        $all_users = array_merge([
            [
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'role' => $user->roles[0] ?? '',
                'role_display' => self::get_role_display_name($user->roles[0] ?? ''),
                'program' => get_user_meta($user->ID, 'program', true),
                'site' => get_user_meta($user->ID, 'site', true),
                'registration_date' => $user->user_registered,
                'depth' => 0,
                'training' => self::get_user_training_data($user->ID)
            ]
        ], self::flatten_descendants($descendants));
        
        // Generate CSV content
        $csv_content = self::generate_csv_content($all_users, $options);
        
        // Set headers for CSV download
        $filename = 'team_export_' . $user->display_name . '_' . date('Y-m-d_H-i-s') . '.csv';
        $filename = sanitize_file_name($filename);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $csv_content;
        exit;
    }

    /**
     * Get user training data
     */
    private static function get_user_training_data(int $user_id): array {
        if (!function_exists('learndash_user_get_enrolled_courses')) {
            return [
                'courses_enrolled' => 0,
                'courses_completed' => 0,
                'certificates_earned' => 0,
                'completion_rate' => 0
            ];
        }
        
        $enrolled_courses = learndash_user_get_enrolled_courses($user_id);
        $completed_courses = [];
        
        foreach ($enrolled_courses as $course_id) {
            if (learndash_course_completed($user_id, $course_id)) {
                $completed_courses[] = $course_id;
            }
        }
        
        $certificates = [];
        if (function_exists('learndash_get_user_certificates')) {
            $certificates = learndash_get_user_certificates($user_id);
        }
        
        return [
            'courses_enrolled' => count($enrolled_courses),
            'courses_completed' => count($completed_courses),
            'certificates_earned' => count($certificates),
            'completion_rate' => count($enrolled_courses) > 0 ? round((count($completed_courses) / count($enrolled_courses)) * 100, 1) : 0
        ];
    }

    /**
     * Flatten descendants tree into a flat array
     */
    private static function flatten_descendants(array $descendants, int $depth = 1): array {
        $flattened = [];
        
        foreach ($descendants as $descendant) {
            $descendant['depth'] = $depth;
            if (!isset($descendant['training'])) {
                $descendant['training'] = self::get_user_training_data($descendant['id']);
            }
            
            $children = $descendant['children'] ?? [];
            unset($descendant['children']);
            
            $flattened[] = $descendant;
            
            if (!empty($children)) {
                $flattened = array_merge($flattened, self::flatten_descendants($children, $depth + 1));
            }
        }
        
        return $flattened;
    }

    /**
     * Generate CSV content
     */
    private static function generate_csv_content(array $users, array $options): string {
        $csv_lines = [];
        
        // Build header row
        $headers = ['Hierarchy Level'];
        
        if ($options['include_basic'] ?? true) {
            $headers = array_merge($headers, ['Name', 'Email', 'Username', 'Role', 'Registration Date']);
        }
        
        if ($options['include_assignment'] ?? true) {
            $headers = array_merge($headers, ['Program', 'Site']);
        }
        
        if ($options['include_training'] ?? true) {
            $headers = array_merge($headers, ['Courses Enrolled', 'Courses Completed', 'Certificates Earned', 'Completion Rate %']);
        }
        
        $csv_lines[] = '"' . implode('","', $headers) . '"';
        
        // Build data rows
        foreach ($users as $user) {
            $row = [];
            
            // Hierarchy level
            $level_indicator = str_repeat('  ', $user['depth']) . ($user['depth'] > 0 ? '└─ ' : '');
            $row[] = $level_indicator . ($user['depth'] == 0 ? 'Team Leader' : 'Team Member');
            
            if ($options['include_basic'] ?? true) {
                $row[] = $user['display_name'];
                $row[] = $user['email'];
                $row[] = get_user_by('id', $user['id'])->user_login ?? '';
                $row[] = $user['role_display'];
                $row[] = date('M j, Y', strtotime($user['registration_date']));
            }
            
            if ($options['include_assignment'] ?? true) {
                $row[] = $user['program'] ?: 'Not assigned';
                $row[] = $user['site'] ?: 'Not assigned';
            }
            
            if ($options['include_training'] ?? true) {
                $row[] = $user['training']['courses_enrolled'];
                $row[] = $user['training']['courses_completed'];
                $row[] = $user['training']['certificates_earned'];
                $row[] = $user['training']['completion_rate'];
            }
            
            // Escape and quote each field
            $escaped_row = array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row);
            
            $csv_lines[] = implode(',', $escaped_row);
        }
        
        return implode("\n", $csv_lines);
    }
} 