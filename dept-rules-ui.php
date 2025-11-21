<?php
/**
 * Department Rules UI - Embedded in config form
 */
?>
<style>
#dept_rules_json { display: none !important; }
#dept_rules_json + br { display: none !important; }
.dept-rules-table {
    width: 100%;
    border-collapse: collapse;
    margin: 10px 0;
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
.dept-rules-table input[type="text"] {
    width: 100%;
    padding: 5px;
    border: 1px solid #ccc;
    border-radius: 3px;
}
.dept-rules-table textarea {
    width: 100%;
    padding: 5px;
    resize: vertical;
    min-height: 60px;
    border: 1px solid #ccc;
    border-radius: 3px;
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

<div class="dept-rules-container">
    <table class="dept-rules-table">
        <thead>
            <tr>
                <th style="width: 35%;">Department</th>
                <th style="width: 55%;">Keywords (comma-separated)</th>
                <th style="width: 10%;">Action</th>
            </tr>
        </thead>
        <tbody id="dept-rules-tbody">
            <tr class="dept-rules-empty-row">
                <td colspan="3" class="dept-rules-empty">
                    No rules configured. Click "Add Rule" to create one.
                </td>
            </tr>
        </tbody>
    </table>
    <button type="button" class="dept-rules-add" onclick="deptRulesAddRule()">Add Rule</button>
</div>

<script>
(function() {
    // Find the hidden JSON field
    var jsonField = document.getElementById('dept_rules_json');
    var tbody = document.getElementById('dept-rules-tbody');
    
    if (!jsonField || !tbody) {
        console.error('Auto Dept Transfer: Required elements not found');
        return;
    }
    
    // Load existing rules
    function loadRules() {
        try {
            var rules = JSON.parse(jsonField.value || '[]');
            tbody.innerHTML = '';
            
            if (rules.length === 0) {
                showEmptyMessage();
            } else {
                rules.forEach(function(rule) {
                    addRuleRow(rule.dept_id, rule.keywords);
                });
            }
        } catch(e) {
            console.error('Auto Dept Transfer: Error parsing rules', e);
            showEmptyMessage();
        }
    }
    
    // Get departments data from PHP
    var departments = <?php
        require_once(INCLUDE_DIR . 'class.dept.php');
        $depts = Dept::getDepartments(array('activeonly' => true), true, false);
        $dept_list = array();
        if ($depts) {
            foreach ($depts as $id => $name) {
                $dept_list[] = array('id' => $id, 'name' => $name);
            }
        }
        echo json_encode($dept_list);
    ?>;
    
    function addRuleRow(deptId, keywords) {
        var emptyRow = tbody.querySelector('.dept-rules-empty-row');
        if (emptyRow) {
            emptyRow.remove();
        }
        
        var tr = document.createElement('tr');
        tr.className = 'dept-rule-row';
        
        // Build department dropdown
        var selectHTML = '<select class="dept-id-select" style="width: 100%; padding: 5px;">';
        selectHTML += '<option value="">-- Select Department --</option>';
        departments.forEach(function(dept) {
            var selected = (dept.id == deptId) ? ' selected' : '';
            selectHTML += '<option value="' + dept.id + '"' + selected + '>' + dept.name + '</option>';
        });
        selectHTML += '</select>';
        
        tr.innerHTML = 
            '<td>' + selectHTML + '</td>' +
            '<td><textarea class="keywords-input" rows="2" ' +
            'placeholder="e.g., password, login, authentication">' + (keywords || '') + '</textarea></td>' +
            '<td style="text-align: center;"><button type="button" class="dept-rules-remove">Remove</button></td>';
        
        // Добавляем обработчики
        var deptSelect = tr.querySelector('.dept-id-select');
        var keywordsInput = tr.querySelector('.keywords-input');
        var removeBtn = tr.querySelector('.dept-rules-remove');
        
        if (deptSelect) {
            deptSelect.addEventListener('change', updateJSON);
        }
        if (keywordsInput) {
            keywordsInput.addEventListener('change', updateJSON);
            keywordsInput.addEventListener('blur', updateJSON);
        }
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                tr.remove();
                updateJSON();
                showEmptyMessage();
            });
        }
        
        tbody.appendChild(tr);
    }
    
    function showEmptyMessage() {
        if (tbody.querySelectorAll('.dept-rule-row').length === 0) {
            tbody.innerHTML = '<tr class="dept-rules-empty-row"><td colspan="3" class="dept-rules-empty">' +
                'No rules configured. Click "Add Rule" to create one.</td></tr>';
        }
    }
    
    function updateJSON() {
        var rules = [];
        tbody.querySelectorAll('.dept-rule-row').forEach(function(row) {
            var deptSelect = row.querySelector('.dept-id-select');
            var keywordsInput = row.querySelector('.keywords-input');
            
            if (deptSelect && keywordsInput) {
                var deptId = deptSelect.value.trim();
                var keywords = keywordsInput.value.trim();
                
                if (deptId && keywords) {
                    rules.push({
                        dept_id: parseInt(deptId) || 0,
                        keywords: keywords
                    });
                }
            }
        });
        
        jsonField.value = JSON.stringify(rules);
        console.log('Auto Dept Transfer: Updated JSON:', jsonField.value);
    }
    
    window.deptRulesAddRule = function() {
        addRuleRow('', '');
        updateJSON();
    };
    
    
    window.deptRulesUpdateJSON = updateJSON;
    
    // Update JSON before form submission
    var form = jsonField.closest('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            console.log('Auto Dept Transfer: Form submitting, updating JSON...');
            updateJSON();
        });
    }
    
    // Initial load
    loadRules();
})();
</script>

