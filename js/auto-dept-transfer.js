/**
 * Auto Department Transfer - Client-side JavaScript
 */

(function($) {
    'use strict';
    
    // Configuration from server
    var config = window.AUTO_DEPT_TRANSFER_CONFIG || {};
    
    /**
     * Show notification message
     */
    function showNotification(message, type) {
        type = type || 'info'; // info, success, error, warning
        
        var alertClass = 'alert-' + type;
        if (type === 'error') alertClass = 'alert-danger';
        
        var $alert = $('<div>')
            .addClass('alert ' + alertClass + ' auto-dept-transfer-alert')
            .html('<strong>' + (type === 'error' ? 'Error: ' : '') + '</strong> ' + message)
            .hide();
        
        // Remove any existing alerts
        $('.auto-dept-transfer-alert').remove();
        
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
        var $button = $('.auto-dept-transfer-btn');
        var originalHtml = $button.html();
        
        // Disable button and show loading state
        $button.prop('disabled', true).html('<i class="icon-refresh icon-spin"></i> Analyzing...');
        
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
                    if (response.transferred) {
                        showNotification(
                            'Ticket successfully transferred to <strong>' + response.dept_name + '</strong>. ' +
                            'Reason: ' + response.reason,
                            'success'
                        );
                        
                        // Reload page after short delay to show updated department
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        showNotification(
                            response.message || 'Transfer not needed - ticket already in the correct department.',
                            'info'
                        );
                    }
                } else if (response.no_match) {
                    showNotification(
                        'No matching department found. ' + (response.message || ''),
                        'warning'
                    );
                } else {
                    showNotification(
                        'Analysis failed: ' + (response.error || 'Unknown error'),
                        'error'
                    );
                }
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
                // Re-enable button
                $button.prop('disabled', false).html(originalHtml);
            }
        });
    }
    
    /**
     * Initialize plugin UI
     */
    function init() {
        // Wait for DOM to be ready
        $(document).ready(function() {
            // Find ticket actions area
            var $ticketActions = $('#ticket-actions, .ticket-actions, .actions');
            
            // If not found, try alternative locations
            if (!$ticketActions.length) {
                $ticketActions = $('.pull-left.flush-left').first();
            }
            
            // Create button
            var $button = $('<button>')
                .addClass('action-button auto-dept-transfer-btn')
                .attr('type', 'button')
                .html('<i class="icon-exchange"></i> Auto Transfer Department')
                .on('click', function(e) {
                    e.preventDefault();
                    if (confirm('Analyze this ticket and transfer to appropriate department?')) {
                        analyzeTicket();
                    }
                });
            
            // Try to insert button in ticket actions
            if ($ticketActions.length) {
                $ticketActions.append($button);
            } else {
                // Fallback: try to find ticket header or any action area
                var $header = $('h2:contains("Ticket")').first();
                if ($header.length) {
                    $header.after($('<div>').addClass('auto-dept-transfer-container').append($button));
                }
            }
            
            if (config.enable_logging) {
                console.log('Auto Dept Transfer - UI initialized');
            }
        });
    }
    
    // Initialize
    init();
    
})(jQuery);

