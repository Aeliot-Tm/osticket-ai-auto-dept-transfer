/**
 * Auto Department Transfer - Client-side JavaScript
 */

(function($) {
    'use strict';
    
    // Configuration from server
    var config = window.AI_AUTO_DEPT_TRANSFER_CONFIG || {};
    
    /**
     * Show notification message
     */
    function showNotification(message, type) {
        type = type || 'info'; // info, success, error, warning
        
        var alertClass = 'alert-' + type;
        if (type === 'error') alertClass = 'alert-danger';
        
        var $alert = $('<div>')
            .addClass('alert ' + alertClass + ' ai-auto-dept-transfer-alert')
            .html('<strong>' + (type === 'error' ? 'Error: ' : '') + '</strong> ' + message)
            .hide();
        
        // Remove any existing alerts
        $('.ai-auto-dept-transfer-alert').remove();
        
        // Insert at top of page
        $('#content').prepend($alert);
        $alert.slideDown(300);
        
        // Auto-hide after 5 seconds for success messages
        if (type === 'success') {
            setTimeout(function() {
                $alert.slideUp(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }
    
    /**
     * Analyze ticket and transfer if needed
     */
    function analyzeTicket() {
        var $menuItem = $('.ai-auto-dept-transfer-menu-item');
        var originalHtml = $menuItem.html();
        
        // Disable menu item and show loading state
        $menuItem.addClass('disabled')
            .css('pointer-events', 'none')
            .html('<i class="icon-refresh icon-spin"></i> Analyzing...');
        
        $.ajax({
            url: config.ajax_url + '/analyze',
            type: 'POST',
            data: {
                ticket_id: config.ticket_id
            },
            dataType: 'json',
            success: function(response) {
                if (config.enable_logging) {
                    console.log('Auto Dept Transfer - Response:', response);
                }
                
                if (response.success) {
                    showNotification(
                        'Ticket successfully transferred to <strong>' + response.dept_name + '</strong>. ' +
                        'Reason: ' + response.reason,
                        'success'
                    );
                } else {
                    showNotification(
                        response.message || response.error || 'Cannot transfer (unknown reason).',
                        'info'
                    );
                }

                // Reload page after short delay to show updated department
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            },
            error: function(xhr, status, error) {
                if (config.enable_logging) {
                    console.error('Auto Dept Transfer - AJAX Error:', status, error);
                }
                showNotification(
                    'Request failed: ' + (error || 'Network error'),
                    'error'
                );
            },
            complete: function() {
                // Re-enable menu item
                $menuItem.removeClass('disabled')
                    .css('pointer-events', '')
                    .html(originalHtml);
            }
        });
    }
    
    /**
     * Check if current staff member's department is allowed to see the button
     */
    function isButtonAllowed() {
        return config.is_manual_button_allowed;
    }
    
    /**
     * Initialize plugin UI
     */
    function init() {
        // Wait for DOM to be ready
        $(document).ready(function() {
            // Check if button should be shown for this staff member's department
            if (!isButtonAllowed()) {
                if (config.enable_logging) {
                    console.log('Auto Dept Transfer - Button not allowed');
                }
                return;
            }
            
            // Find the "More" dropdown menu
            var $dropdown = $('#action-dropdown-more ul');
            
            if (!$dropdown.length) {
                if (config.enable_logging) {
                    console.log('Auto Dept Transfer - More dropdown not found');
                }
                return;
            }
            
            // Create menu item in the same format as other items in the dropdown
            var $menuItem = $('<li>')
                .html('<a href="#" class="ai-auto-dept-transfer-menu-item"><i class="icon-exchange"></i> Auto Transfer Department</a>');
            
            // Add click handler to the link
            $menuItem.find('a').on('click', function(e) {
                e.preventDefault();
                if (confirm('Analyze this ticket and transfer to appropriate department?')) {
                    analyzeTicket();
                }
            });
            
            // Insert as first item in the dropdown
            $dropdown.prepend($menuItem);
            
            if (config.enable_logging) {
                console.log('Auto Dept Transfer - UI initialized in More dropdown');
            }
        });
    }
    
    // Initialize
    init();
    
})(jQuery);

