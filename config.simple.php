<?php

require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.forms.php');

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
            'dept_rules' => new TextareaField(array(
                'label' => __('Department Transfer Rules'),
                'required' => false,
                'default' => '[]',
                'configuration' => array(
                    'rows' => 1,
                    'cols' => 80,
                    'html' => false,
                    'classes' => 'dept-rules-json-field'
                ),
                'hint' => __('Department transfer rules will appear as a table below. The JSON data is auto-managed.')
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

