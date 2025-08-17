jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize user management functionality
    initUserManagement();
    initInlineEditing();
    
    function initUserManagement() {
        // Handle filter form submission
        $('#rum-user-filters').on('submit', function(e) {
            e.preventDefault();
            applyFilters();
        });
        
        // Handle clear filters button
        $('#clear-filters').on('click', function() {
            clearFilters();
        });
        
        // Handle export users button
        $('#export-users').on('click', function() {
            exportUsers();
        });
        
        // Handle filter changes for real-time updates (optional)
        $('#filter_role, #filter_program, #filter_site').on('change', function() {
            // Uncomment the line below if you want real-time filtering
            // applyFilters();
        });
    }
    
    /**
     * Apply filters and update the users table
     */
    function applyFilters() {
        const filters = {
            filter_role: $('#filter_role').val(),
            filter_program: $('#filter_program').val(),
            filter_site: $('#filter_site').val()
        };
        
        // Update URL with filter parameters
        updateURL(filters);
        
        // Reload the page to show filtered results
        location.reload();
    }
    
    /**
     * Clear all filters
     */
    function clearFilters() {
        $('#filter_role').val('');
        $('#filter_program').val('');
        $('#filter_site').val('');
        
        // Clear URL parameters
        const url = new URL(window.location);
        url.search = '';
        window.history.pushState({}, '', url);
        
        // Reload the page
        location.reload();
    }
    
    /**
     * Export users to CSV
     */
    function exportUsers() {
        const filters = {
            filter_role: $('#filter_role').val(),
            filter_program: $('#filter_program').val(),
            filter_site: $('#filter_site').val()
        };
        
        // Build export URL with filters
        let exportUrl = rum_user_management.ajax_url + '?action=rum_export_users&nonce=' + rum_user_management.nonce;
        
        // Add filter parameters
        Object.keys(filters).forEach(key => {
            if (filters[key]) {
                exportUrl += '&' + key + '=' + encodeURIComponent(filters[key]);
            }
        });
        
        // Create temporary form to submit export request
        const form = $('<form>', {
            method: 'POST',
            action: exportUrl,
            target: '_blank'
        });
        
        // Add filter data as hidden fields
        Object.keys(filters).forEach(key => {
            if (filters[key]) {
                form.append($('<input>', {
                    type: 'hidden',
                    name: key,
                    value: filters[key]
                }));
            }
        });
        
        // Submit form
        $('body').append(form);
        form.submit();
        form.remove();
        
        // Show success message
        showNotification(rum_user_management.strings.export_success, 'success');
    }
    
    /**
     * Update URL with filter parameters
     */
    function updateURL(filters) {
        const url = new URL(window.location);
        
        // Clear existing filter parameters
        Object.keys(filters).forEach(key => {
            url.searchParams.delete(key);
        });
        
        // Add new filter parameters
        Object.keys(filters).forEach(key => {
            if (filters[key]) {
                url.searchParams.set(key, filters[key]);
            }
        });
        
        // Update URL without reloading
        window.history.pushState({}, '', url);
    }
    

    
    /**
     * Show notification message
     */
    function showNotification(message, type = 'info') {
        // Create notification element
        const notification = $('<div>', {
            class: 'notice notice-' + type + ' is-dismissible',
            html: '<p>' + message + '</p>'
        });
        
        // Add to page
        $('.wrap h1').after(notification);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Handle dismiss button
        notification.find('.notice-dismiss').on('click', function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    /**
     * Handle AJAX errors
     */
    function handleAjaxError(xhr, status, error) {
        console.error('AJAX Error:', status, error);
        showNotification('An error occurred. Please try again.', 'error');
    }
    
    /**
     * Show loading state
     */
    function showLoading() {
        $('#rum-users-table-container').html('<div class="rum-loading">' + rum_user_management.strings.loading + '</div>');
    }
    
    /**
     * Hide loading state
     */
    function hideLoading() {
        $('.rum-loading').remove();
    }
    
    /**
     * Update users table with new data
     */
    function updateUsersTable(users) {
        if (!users || users.length === 0) {
            $('#rum-users-table-container').html('<p>' + rum_user_management.strings.no_users + '</p>');
            return;
        }
        
        // Build table HTML
        let tableHtml = '<table class="rum-users-table">';
        tableHtml += '<thead><tr>';
        tableHtml += '<th>User</th>';
        tableHtml += '<th>Role</th>';
        tableHtml += '<th>Program</th>';
        tableHtml += '<th>Sites</th>';
        tableHtml += '<th>Parent User</th>';
        tableHtml += '<th>Actions</th>';
        tableHtml += '</tr></thead><tbody>';
        
        users.forEach(user => {
            tableHtml += '<tr>';
            tableHtml += '<td><strong>' + user.display_name + '</strong><br><small>' + user.user_email + '</small></td>';
            tableHtml += '<td>' + user.roles.join(', ') + '</td>';
            tableHtml += '<td>' + (user.program || 'Not set') + '</td>';
            tableHtml += '<td>' + (user.sites && user.sites.length ? user.sites.join(', ') : 'Not set') + '</td>';
            tableHtml += '<td>' + (user.parent_name || 'No parent') + '</td>';
            tableHtml += '<td class="rum-user-actions">';
            tableHtml += '<a href="' + ajaxurl + '?page=user-edit&user_id=' + user.ID + '" class="button">Edit</a> ';
            tableHtml += '<a href="' + ajaxurl + '?page=user-management&action=view&user_id=' + user.ID + '" class="button">View</a>';
            tableHtml += '</td>';
            tableHtml += '</tr>';
        });
        
        tableHtml += '</tbody></table>';
        
        $('#rum-users-table-container').html(tableHtml);
    }
    
    /**
     * Handle pagination clicks
     */
    $(document).on('click', '.rum-pagination .page-numbers', function(e) {
        e.preventDefault();
        
        const page = $(this).text();
        if (page === 'Previous' || page === 'Next') {
            // Handle previous/next navigation
            const currentPage = parseInt($('.rum-pagination .current').text());
            const newPage = page === 'Previous' ? currentPage - 1 : currentPage + 1;
            
            if (newPage > 0) {
                updateURL({ paged: newPage });
                location.reload();
            }
        } else {
            // Handle specific page number
            updateURL({ paged: page });
            location.reload();
        }
    });
    
    /**
     * Handle bulk actions
     */
    $(document).on('change', '#bulk-action-selector-top, #bulk-action-selector-bottom', function() {
        const action = $(this).val();
        const bulkActionButton = $(this).closest('.tablenav').find('.button');
        
        if (action && action !== '-1') {
            bulkActionButton.prop('disabled', false);
        } else {
            bulkActionButton.prop('disabled', true);
        }
    });
    
    /**
     * Handle bulk action submission
     */
    $(document).on('click', '.button-primary', function(e) {
        const form = $(this).closest('form');
        const bulkAction = form.find('#bulk-action-selector-top, #bulk-action-selector-bottom').val();
        
        if (bulkAction && bulkAction !== '-1') {
            const selectedUsers = form.find('input[name="users[]"]:checked');
            
            if (selectedUsers.length === 0) {
                e.preventDefault();
                showNotification('Please select users to perform this action.', 'warning');
                return false;
            }
        }
    });
    
    /**
     * Confirm destructive actions
     */
    $(document).on('click', '.rum-user-actions .button[href*="delete"]', function(e) {
        if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            e.preventDefault();
            return false;
        }
    });
    
    /**
     * Handle dynamic site filtering based on program selection
     */
    $('#filter_program').on('change', function() {
        const selectedProgram = $(this).val();
        const siteSelect = $('#filter_site');
        
        if (selectedProgram) {
            // Filter sites based on selected program
                            $.ajax({
                    url: rum_user_management.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'rum_get_sites_for_program_user_management',
                        program: selectedProgram,
                        nonce: rum_user_management.nonce
                    },
                success: function(response) {
                    if (response.success && response.data.sites) {
                        siteSelect.empty();
                        siteSelect.append('<option value="">All Sites</option>');
                        
                        response.data.sites.forEach(site => {
                            siteSelect.append('<option value="' + site + '">' + site + '</option>');
                        });
                    }
                },
                error: function() {
                    console.log('Failed to load sites for program');
                }
            });
        } else {
            // Reset to all sites
            siteSelect.empty();
            siteSelect.append('<option value="">All Sites</option>');
            
            // Reload all sites
            $.ajax({
                url: rum_user_management.ajax_url,
                type: 'POST',
                data: {
                    action: 'rum_get_all_sites',
                    nonce: rum_user_management.nonce
                },
                success: function(response) {
                    if (response.success && response.data.sites) {
                        response.data.sites.forEach(site => {
                            siteSelect.append('<option value="' + site + '">' + site + '</option>');
                        });
                    }
                }
            });
        }
    });
    
    /**
     * Initialize tooltips for better UX
     */
    function initTooltips() {
        // Simple tooltip implementation without jQuery UI dependency
        $('[title]').each(function() {
            const $element = $(this);
            const title = $element.attr('title');
            
            if (title) {
                $element.removeAttr('title');
                
                $element.on('mouseenter', function() {
                    const tooltip = $('<div class="rum-tooltip">' + title + '</div>');
                    $('body').append(tooltip);
                    
                    const offset = $element.offset();
                    tooltip.css({
                        position: 'absolute',
                        top: offset.top - tooltip.outerHeight() - 10,
                        left: offset.left + ($element.outerWidth() / 2) - (tooltip.outerWidth() / 2),
                        zIndex: 10000
                    });
                });
                
                $element.on('mouseleave', function() {
                    $('.rum-tooltip').remove();
                });
            }
        });
    }
    
    // Initialize tooltips after page load
    setTimeout(initTooltips, 1000);
    
    /**
     * Handle keyboard shortcuts
     */
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + F to focus on search
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            $('#filter_role').focus();
        }
        
        // Ctrl/Cmd + E to export
        if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
            e.preventDefault();
            exportUsers();
        }
        
        // Escape to clear filters
        if (e.key === 'Escape') {
            clearFilters();
        }
    });
    
    /**
     * Add keyboard navigation for better accessibility
     */
    $('.rum-users-table').on('keydown', 'tr', function(e) {
        const currentRow = $(this);
        const allRows = currentRow.closest('table').find('tbody tr');
        const currentIndex = allRows.index(currentRow);
        
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                if (currentIndex < allRows.length - 1) {
                    allRows.eq(currentIndex + 1).focus();
                }
                break;
                
            case 'ArrowUp':
                e.preventDefault();
                if (currentIndex > 0) {
                    allRows.eq(currentIndex - 1).focus();
                }
                break;
                
            case 'Enter':
            case ' ':
                e.preventDefault();
                currentRow.find('.rum-user-actions .button').first().click();
                break;
        }
    });
    
    // Make table rows focusable for keyboard navigation
    $('.rum-users-table tbody tr').attr('tabindex', '0');
    
    /**
     * Add loading states to buttons
     */
    $(document).on('click', 'button', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Processing...');
        
        // Re-enable after 3 seconds (fallback)
        setTimeout(function() {
            button.prop('disabled', false).text(originalText);
        }, 3000);
    });
    
    /**
     * Handle form validation
     */
    function validateFilters() {
        const errors = [];
        
        // Check if date range is valid (if implemented)
        const dateStart = $('#filter_date_start').val();
        const dateEnd = $('#filter_date_end').val();
        
        if (dateStart && dateEnd && new Date(dateStart) > new Date(dateEnd)) {
            errors.push('Start date cannot be after end date');
        }
        
        if (errors.length > 0) {
            errors.forEach(error => showNotification(error, 'error'));
            return false;
        }
        
        return true;
    }
    
    // Add validation to form submission
    $('#rum-user-filters').on('submit', function(e) {
        if (!validateFilters()) {
            e.preventDefault();
            return false;
        }
    });

    /**
     * Show notification message
     */
    function showNotification(message, type = 'info') {
        const notification = $(`
            <div class="rum-notification rum-notification-${type}">
                <span>${message}</span>
                <button class="rum-notification-close">&times;</button>
            </div>
        `);
        
        $('body').append(notification);
        notification.slideDown(300);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            notification.slideUp(300, function() {
                notification.remove();
            });
        }, 5000);
        
        // Close button functionality
        notification.find('.rum-notification-close').on('click', function() {
            notification.slideUp(300, function() {
                notification.remove();
            });
        });
    }

    /**
     * Initialize inline editing functionality
     */
    function initInlineEditing() {
        console.log('Initializing inline editing...');
        
        // Handle edit button clicks
        $(document).on('click', '.rum-edit-cell-btn', function() {
            console.log('Edit button clicked');
            const $button = $(this);
            const $cell = $button.closest('.rum-editable-cell');
            const $displayValue = $cell.find('.rum-display-value');
            const $editForm = $cell.find('.rum-edit-form');
            
            console.log('Cell:', $cell);
            console.log('Display value:', $displayValue);
            console.log('Edit form:', $editForm);
            
            // Show edit form for this specific cell only
            $displayValue.hide();
            $editForm.show();
        });

        // Handle save button clicks
        $(document).on('click', '.rum-save-btn', function() {
            console.log('Save button clicked');
            const $button = $(this);
            const $cell = $button.closest('.rum-editable-cell');
            const userId = $cell.data('user-id');
            const field = $cell.data('field');
            const $select = $cell.find('.rum-edit-select');
            const $displayValue = $cell.find('.rum-display-value');
            const $editForm = $cell.find('.rum-edit-form');
            
            console.log('Saving field:', field, 'for user:', userId, 'with value:', $select.val());
            
            let value;
            if (field === 'sites' && $select.attr('multiple')) {
                // Handle multiple select for sites
                value = $select.val() || [];
            } else {
                value = $select.val();
            }

            // Show loading state
            $button.prop('disabled', true).text('Saving...');

            // Send AJAX request
            $.ajax({
                url: rum_user_management.ajax_url,
                type: 'POST',
                data: {
                    action: 'rum_update_user_data',
                    nonce: rum_user_management.nonce,
                    user_id: userId,
                    field: field,
                    value: value
                },
                success: function(response) {
                    console.log('AJAX response:', response);
                    if (response.success) {
                        // Update display value
                        $displayValue.html(response.data.display_value);
                        showNotification(response.data.message, 'success');
                        
                        // Hide edit form and show display value
                        $editForm.hide();
                        $displayValue.show();
                    } else {
                        showNotification(response.data || 'Update failed', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error details:');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Response Text:', xhr.responseText);
                    console.error('Status Code:', xhr.status);
                    
                    let errorMessage = 'Error updating user data. Please try again.';
                    
                    // Try to parse error response
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.data) {
                            errorMessage = errorResponse.data;
                        }
                    } catch (e) {
                        console.log('Could not parse error response');
                    }
                    
                    showNotification(errorMessage, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Save');
                }
            });
        });

        // Handle cancel button clicks
        $(document).on('click', '.rum-cancel-btn', function() {
            console.log('Cancel button clicked');
            const $cell = $(this).closest('.rum-editable-cell');
            const $displayValue = $cell.find('.rum-display-value');
            const $editForm = $cell.find('.rum-edit-form');
            const $select = $cell.find('.rum-edit-select');
            
            // Reset to original value
            const originalValue = $select.data('original-value');
            if (originalValue) {
                if ($select.attr('multiple')) {
                    // Handle multiple select
                    const values = originalValue.split(',').filter(v => v.trim());
                    $select.val(values);
                } else {
                    $select.val(originalValue);
                }
            }
            
            // Hide edit form and show display value
            $editForm.hide();
            $displayValue.show();
        });

        // Handle escape key to cancel editing
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.rum-edit-form').each(function() {
                    const $cell = $(this).closest('.rum-editable-cell');
                    const $displayValue = $cell.find('.rum-display-value');
                    const $editForm = $(this);
                    
                    $editForm.hide();
                    $displayValue.show();
                });
            }
        });
        
        console.log('Inline editing initialized');
    }
});
