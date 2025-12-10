<?php

require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.forms.php');

class AIAutoDeptTransferRulesField extends TextareaField {
    static $widget = 'AIAutoDeptTransferRulesWidget';
}

class AIAutoDeptTransferRulesWidget extends TextareaWidget {
    function render($options=array()) {
        // Render the hidden textarea
        $config = $this->field->getConfiguration();
        ?>
        <script type="text/javascript"><?php readfile(__DIR__ . '/js/config.js'); ?></script>
        <textarea style="display:none !important;" name="<?php echo $this->name; ?>" 
                  id="dept_rules_json"><?php echo Format::htmlchars($this->value); ?></textarea>
        <?php
        // Include the UI
        include(dirname(__FILE__) . '/dept-rules-ui.php');
    }
}

/**
 * Custom field for department selection that stores only IDs
 * Names are loaded dynamically on render
 */
class AIAutoDeptTransferDepartmentMultiselectField extends TextboxField {
    static $widget = 'AIAutoDeptTransferDepartmentMultiselectWidget';
}

class AIAutoDeptTransferDepartmentMultiselectWidget extends Widget {
    
    function render($options=array()) {
        // Parse current value (JSON array of IDs)
        $selected = array();
        if ($this->value) {
            $selected = is_array($this->value) ? $this->value : json_decode($this->value, true);
        }
        
        // Get all departments
        $depts = array();
        if (class_exists('Dept')) {
            $depts = Dept::getDepartments();
        }
        
        $name = $this->name;
        $id = substr(str_replace(array('[', ']'), '_', $name), 0, -1);
        ?>
        <input type="hidden" name="<?php echo $name; ?>" id="<?php echo $id; ?>_hidden" value="<?php echo Format::htmlchars($this->value); ?>" />
        <select id="<?php echo $id; ?>_select" multiple="multiple" data-placeholder="<?php echo __('Select departments...'); ?>" style="width: 350px;">
            <?php foreach ($depts as $dept_id => $dept_name): ?>
                <option value="<?php echo $dept_id; ?>" <?php if (in_array($dept_id, $selected)) echo 'selected="selected"'; ?>>
                    <?php echo Format::htmlchars($dept_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <script type="text/javascript">
        $(function() {
            var $select = $('#<?php echo $id; ?>_select');
            var $hidden = $('#<?php echo $id; ?>_hidden');
            
            $select.select2({
                minimumResultsForSearch: 10,
                width: '350px'
            });
            
            $select.on('change', function() {
                var selected = $(this).val() || [];
                var ids = selected.map(function(v) { return parseInt(v); });
                $hidden.val(JSON.stringify(ids));
            });
        });
        </script>
        <?php
    }
}

class AIAutoDeptTransferConfig extends PluginConfig {

    function get($key, $default = null)
    {
        $value = parent::get($key, $default);
        if ('allowed_depts' === $key) {
            if (!$value){
                $value = '[]';
            }
            if (is_string($value)) {
                $value = rtrim($value);
                if (!$value || 'null' === strtolower($value)){
                    $value = '[]';
                }
                $value = json_decode($value, true);
                if (!\is_array($value)) {
                    $value = [];
                }
            }

            $value = array_map('intval', $value) ?: null;
        }

        return $value;
    }

    function getOptions() {
        return array(
            'dept_rules' => new AIAutoDeptTransferRulesField(array(
                'label' => __('Department Transfer Rules'),
                'required' => false,
                'default' => '[]',
                'hint' => __('Configure department transfer rules. Use the table below to add departments and keywords.')
            )),
            'max_file_size' => new TextboxField(array(
                'label' => __('Max File Size (MB)'),
                'default' => '10',
                'required' => true,
                'validator' => 'number',
                'configuration' => array(
                        'size' => 10,
                        'length' => 3
                ),
                'hint' => __('Maximum file size to process for text extraction')
            )),
            'api_key' => new TextboxField(array(
                'label' => __('OpenAI API Key'),
                'required' => true,
                'configuration' => array(
                    'size' => 60,
                    'length' => 500,
                    'placeholder' => 'sk-...'
                ),
                'hint' => __('Your OpenAI API key. Get it from https://platform.openai.com/api-keys')
            )),
            'model' => new ChoiceField(array(
                'label' => __('OpenAI Model'),
                'default' => 'gpt-4o-mini',
                'choices' => array(
                    'gpt-4o' => 'GPT-4o (Most capable, expensive)',
                    'gpt-4o-mini' => 'GPT-4o Mini (Fast and affordable)',
                    'gpt-4-turbo' => 'GPT-4 Turbo',
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Cheapest)'
                ),
                'hint' => __('Choose the model to use for analysis')
            )),
            'timeout' => new TextboxField(array(
                'label' => __('API Timeout (seconds)'),
                'default' => '30',
                'required' => true,
                'validator' => 'number',
                'configuration' => array(
                    'size' => 10,
                    'length' => 3
                ),
                'hint' => __('Maximum time to wait for OpenAI response')
            )),
            'auto_transfer' => new BooleanField(array(
                'label' => __('Auto-transfer on ticket creation'),
                'default' => true,
                'configuration' => array(
                        'desc' => __('Automatically analyze and transfer new tickets')
                )
            )),
            'allowed_depts' => new AIAutoDeptTransferDepartmentMultiselectField(array(
                'label' => __('Departments with Manual Transfer Button'),
                'required' => false,
                'default' => [],
                'configuration' => array(
                        'multiselect' => true
                ),
                'hint' => __('Select departments that can see the manual transfer button. Leave empty to show for all departments (including future ones).')
            )),
            'enable_logging' => new BooleanField(array(
                'label' => __('Enable Debug Logging'),
                'default' => false,
                'configuration' => array(
                    'desc' => __('Log processing details and AI requests for debugging')
                )
            ))
        );
    }
    
    function getFormOptions() {
        return array(
            'title' => __('Auto Department Transfer Configuration'),
            'instructions' => __('Configure automatic ticket department transfers based on keywords in subject, content, and attachments.')
        );
    }
    
    /**
     * Get parsed department rules
     * @return array
     */
    function getDeptRules() {
        $rules = $this->get('dept_rules');
        
        if (is_string($rules)) {
            $rules = json_decode($rules, true);
        }
        
        if (!is_array($rules)) {
            return array();
        }
        
        // Filter out empty rules
        $filtered = array();
        foreach ($rules as $rule) {
            if (isset($rule['dept_id']) && !empty($rule['dept_id']) && 
                isset($rule['keywords']) && !empty(trim($rule['keywords']))) {
                $filtered[] = $rule;
            }
        }
        
        return $filtered;
    }
}

