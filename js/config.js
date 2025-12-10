/**
 * Auto Department Transfer - Config UI Enhancement
 * Transforms JSON textarea into dynamic table
 */

(function($) {
    'use strict';
    
    // Wait for document ready
    $(document).ready(function() {
        // Find the dept_rules textarea
        var $textarea = $('textarea[name*="dept_rules"]');
        
        if (!$textarea.length) {
            console.log('Auto Dept Transfer: dept_rules field not found');
            return;
        }
        
        console.log('Auto Dept Transfer: Initializing config UI');
        
        // Hide the textarea
        $textarea.hide();
        
        // Get departments list via AJAX
        $.ajax({
            url: 'ajax.php/departments',
            type: 'GET',
            dataType: 'json',
            success: function(departments) {
                initDeptRulesTable($textarea, departments);
            },
            error: function() {
                // Fallback - create table without departments list
                console.log('Auto Dept Transfer: Could not load departments, using fallback');
                initDeptRulesTable($textarea, {});
            }
        });
    });
    
    function initDeptRulesTable($textarea, departments) {
        // Parse current value
        var rules = [];
        try {
            var value = $textarea.val().trim();
            if (value) {
                rules = JSON.parse(value);
            }
        } catch(e) {
            console.error('Auto Dept Transfer: Error parsing JSON', e);
            rules = [];
        }
        
        // Create table container
        var $container = $('<div class="dept-rules-table-container"></div>');
        
        // Add styles
        var styles = `
        <style>
        .dept-rules-table-container {
            margin: 10px 0;
        }
        .dept-rules-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        .dept-rules-table th,
        .dept-rules-table td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .dept-rules-table th {
            background: #f5f5f5;
            font-weight: 600;
        }
        .dept-rules-table input[type="text"],
        .dept-rules-table textarea {
            width: 100%;
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        .dept-rules-table textarea {
            min-height: 60px;
            resize: vertical;
        }
        .dept-rules-remove {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 3px;
        }
        .dept-rules-remove:hover {
            background: #c82333;
        }
        .dept-rules-add {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            cursor: pointer;
            border-radius: 3px;
            margin-top: 10px;
        }
        .dept-rules-add:hover {
            background: #218838;
        }
        .dept-rules-empty {
            padding: 20px;
            text-align: center;
            color: #666;
            font-style: italic;
        }
        </style>
        `;
        
        $container.append(styles);
        
        // Create table
        var $table = $('<table class="dept-rules-table"></table>');
        $table.html(`
            <thead>
                <tr>
                    <th style="width: 35%;">Department ID</th>
                    <th style="width: 55%;">Keywords (comma-separated)</th>
                    <th style="width: 10%;">Action</th>
                </tr>
            </thead>
            <tbody></tbody>
        `);
        
        var $tbody = $table.find('tbody');
        
        // Add existing rules
        if (rules.length > 0) {
            rules.forEach(function(rule) {
                addRuleRow($tbody, rule.dept_id, rule.keywords);
            });
        } else {
            showEmptyMessage($tbody);
        }
        
        $container.append($table);
        
        // Add button
        var $addBtn = $('<button type="button" class="dept-rules-add">Add Rule</button>');
        $addBtn.on('click', function(e) {
            e.preventDefault();
            removeEmptyMessage($tbody);
            addRuleRow($tbody, '', '');
            updateJSON();
        });
        $container.append($addBtn);
        
        // Insert after textarea
        $textarea.after($container);
        
        // Helper functions
        function addRuleRow($tbody, deptId, keywords) {
            var $row = $('<tr class="dept-rule-row"></tr>');
            $row.html(`
                <td>
                    <input type="text" class="dept-id-input" value="${deptId || ''}" 
                           placeholder="Enter department ID (e.g., 1, 2, 3)">
                </td>
                <td>
                    <textarea class="keywords-input" rows="2" 
                              placeholder="e.g., password, login, authentication">${keywords || ''}</textarea>
                </td>
                <td style="text-align: center;">
                    <button type="button" class="dept-rules-remove">Remove</button>
                </td>
            `);
            
            // Bind events
            $row.find('.dept-id-input, .keywords-input').on('change keyup', updateJSON);
            $row.find('.dept-rules-remove').on('click', function(e) {
                e.preventDefault();
                $row.remove();
                updateJSON();
                if ($tbody.find('.dept-rule-row').length === 0) {
                    showEmptyMessage($tbody);
                }
            });
            
            $tbody.append($row);
        }
        
        function showEmptyMessage($tbody) {
            if ($tbody.find('.dept-rules-empty-row').length === 0) {
                $tbody.html('<tr class="dept-rules-empty-row"><td colspan="3" class="dept-rules-empty">No rules configured. Click "Add Rule" to create one.</td></tr>');
            }
        }
        
        function removeEmptyMessage($tbody) {
            $tbody.find('.dept-rules-empty-row').remove();
        }
        
        function updateJSON() {
            var rules = [];
            $tbody.find('.dept-rule-row').each(function() {
                var $row = $(this);
                var deptId = $row.find('.dept-id-input').val().trim();
                var keywords = $row.find('.keywords-input').val().trim();
                
                if (deptId && keywords) {
                    rules.push({
                        dept_id: parseInt(deptId) || 0,
                        keywords: keywords
                    });
                }
            });
            
            $textarea.val(JSON.stringify(rules));
            console.log('Auto Dept Transfer: Updated rules', rules);
        }
        
        // Initial update
        updateJSON();
    }
    
})(jQuery);

