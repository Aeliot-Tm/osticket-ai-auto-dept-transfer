<?php

require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.forms.php');

/**
 * Custom field for department rules with dynamic table UI
 */
class DeptRulesField extends FormField {
    static $widget = 'DeptRulesWidget';
    
    function to_php($value) {
        // Convert from database format to PHP array
        if (is_array($value))
            return $value;
        if (is_string($value) && !empty($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded))
                return $decoded;
        }
        return array();
    }
    
    function to_database($value) {
        // Convert from PHP to database format
        if (is_string($value)) {
            // Already JSON, just validate
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $value; // Return as-is if valid JSON
            }
        }
        if (is_array($value)) {
            return json_encode($value);
        }
        return '[]';
    }
    
    function toString($value) {
        if (is_array($value))
            return json_encode($value);
        return is_string($value) ? $value : '[]';
    }
    
    function getClean($validate = true) {
        // This is called when form is submitted
        $value = $this->value;
        
        // If it's already a string (from POST), decode it
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        
        return $this->to_php($value);
    }
    
    function getValue() {
        // Return the value for display in widget
        return $this->value;
    }
    
    function display($value) {
        // For display purposes
        if (is_array($value)) {
            return json_encode($value);
        }
        return is_string($value) ? $value : '[]';
    }
}

class DeptRulesWidget extends Widget {
    function render($options=array()) {
        require_once(dirname(__FILE__) . '/dept-rules-ui.php');
        
        // Get the value
        $value = $this->value;
        
        // Debug - what do we have?
        error_log("Auto Dept Transfer - Widget render START");
        error_log("Auto Dept Transfer - Widget->value type: " . gettype($value));
        error_log("Auto Dept Transfer - Widget->value content: " . print_r($value, true));
        
        // If value is empty, try to get from field
        if (empty($value) && $this->field) {
            error_log("Auto Dept Transfer - Trying to get value from field");
            $fieldValue = $this->field->getValue();
            error_log("Auto Dept Transfer - Field->getValue() returned: " . print_r($fieldValue, true));
            if (!empty($fieldValue)) {
                $value = $fieldValue;
            }
        }
        
        // Also try field->value directly
        if (empty($value) && $this->field && isset($this->field->value)) {
            error_log("Auto Dept Transfer - Trying field->value directly");
            error_log("Auto Dept Transfer - Field->value: " . print_r($this->field->value, true));
            $value = $this->field->value;
        }
        
        error_log("Auto Dept Transfer - Final value for render: " . print_r($value, true));
        
        echo renderDeptRulesUI($this->name, $value);
    }
}

class AIAutoDeptTransferConfig extends PluginConfig {
    
    function getOptions() {
        return array(
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
            'enable_logging' => new BooleanField(array(
                'label' => __('Enable Debug Logging'),
                'default' => false,
                'configuration' => array(
                    'desc' => __('Log processing details and AI requests for debugging')
                )
            )),
            'auto_transfer' => new BooleanField(array(
                'label' => __('Auto-transfer on ticket creation'),
                'default' => true,
                'configuration' => array(
                    'desc' => __('Automatically analyze and transfer new tickets')
                )
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
            'dept_rules' => new DeptRulesField(array(
                'label' => __('Department Transfer Rules'),
                'required' => false,
                'default' => '[]',
                'hint' => __('Configure department transfer rules by selecting departments and specifying keywords. When a ticket contains these keywords, it will be transferred to the corresponding department.')
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
        
        // The get() method already uses to_php() so rules should be array
        if (!is_array($rules)) {
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (!is_array($rules)) {
                return array();
            }
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
    
    /**
     * Hook for debugging - called before save
     */
    function pre_save(&$config, &$errors) {
        error_log("Auto Dept Transfer - Saving config with dept_rules: " . print_r($config['dept_rules'] ?? 'NOT SET', true));
        return parent::pre_save($config, $errors);
    }
}