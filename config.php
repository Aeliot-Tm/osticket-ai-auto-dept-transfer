<?php

require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.forms.php');
require_once(__DIR__ . '/class.api-client.php');

/**
 * Custom field for Model selection that switches between dropdown and textbox
 */
class AIAutoDeptTransferModelField extends TextboxField {
    static $widget = AIAutoDeptTransferModelWidget::class;
}

class AIAutoDeptTransferModelWidget extends Widget {
    function render($options=array()) {
        $name = $this->name;
        $value = $this->value;
        $config = $this->field->getConfiguration();

        // OpenAI models list
        $models = AIAutoDeptTransferModelCapabilities::MODELS;
        ?>
        <input type="hidden" name="<?php echo $name; ?>" id="model_value" value="<?php echo Format::htmlchars($value); ?>" />

        <!-- Dropdown for OpenAI -->
        <select id="model_select" class="model-select-dropdown" style="width: 350px;">
            <?php foreach ($models as $model_id => $model_name): ?>
                <option value="<?php echo $model_id; ?>" <?php if ($value === $model_id) echo 'selected="selected"'; ?> <?php if (AIAutoDeptTransferModelCapabilities::responsesOnly($model_id)) echo 'disabled="disabled"'; ?>>
                    <?php echo Format::htmlchars($model_name); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Textbox for Custom -->
        <input type="text" id="model_text" class="model-text-input"
               value="<?php echo Format::htmlchars($value); ?>"
               placeholder="Enter model name (e.g., gpt-6-luna)"
               style="width: 350px; padding: 5px;" />

        <script type="text/javascript"><?php readfile(__DIR__ . '/js/config-api-provider.js'); ?></script>
        <?php
    }
}

class AIAutoDeptTransferVisionModelField extends TextboxField {
    static $widget = AIAutoDeptTransferVisionModelWidget::class;
}

class AIAutoDeptTransferVisionModelWidget extends Widget {
    function render($options=array()) {
        $name = $this->name;
        $value = $this->value;
        $input_id = 'vision_model_text_' . uniqid();
        $datalist_id = $input_id . '_datalist';
        ?>
        <input type="text"
               id="<?php echo $input_id; ?>"
               name="<?php echo $name; ?>"
               class="vision-model-text-input"
               list="<?php echo $datalist_id; ?>"
               value="<?php echo Format::htmlchars($value); ?>"
               placeholder="Enter vision model (e.g., gpt-6-luna)"
               style="width: 350px; padding: 5px;" />
        <datalist id="<?php echo $datalist_id; ?>"></datalist>
        <script type="text/javascript"><?php readfile(__DIR__ . '/js/config-vision-model-autocomplete.js'); ?></script>
        <script type="text/javascript">
            (function() {
                if (window.AIADTVisionModelAutocomplete && typeof window.AIADTVisionModelAutocomplete.setup === 'function') {
                    window.AIADTVisionModelAutocomplete.setup('<?php echo $input_id; ?>', '<?php echo $datalist_id; ?>');
                }
            })();
        </script>
        <?php
    }
}

class AIAutoDeptTransferRulesField extends TextareaField {
    static $widget = 'AIAutoDeptTransferRulesWidget';
}

class AIAutoDeptTransferRulesWidget extends TextareaWidget {
    function render($options=array()) {
        // Render the hidden textarea
        $config = $this->field->getConfiguration();
        ?>
        <script type="text/javascript"><?php readfile(__DIR__ . '/js/config-dept-rules.js'); ?></script>
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
            'default_department' => new ChoiceField(array(
                'label' => __('Default Department'),
                'required' => false,
                'default' => '',
                'choices' => (function() {
                    $choices = array('' => '-- None --');
                    if (class_exists('Dept')) {
                        $depts = Dept::getDepartments(array('activeonly' => true), true, false);
                        if ($depts) {
                            foreach ($depts as $id => $name) {
                                $choices[$id] = $name;
                            }
                        }
                    }
                    return $choices;
                })(),
                'hint' => __('Department to use when no matching keywords are found in ticket content. Leave empty to skip transfer if no rules match.')
            )),
            'auto_transfer' => new BooleanField(array(
                'label' => __('Auto-transfer on ticket creation'),
                'default' => true,
                'configuration' => array(
                        'desc' => __('Automatically analyze and transfer new tickets')
                )
            )),
            'api_provider' => new ChoiceField(array(
                'label' => __('API Provider'),
                'default' => 'openai',
                'choices' => array(
                    'openai' => 'Open AI',
                    'custom' => 'Custom'
                ),
                'hint' => __('Choose API provider type')
            )),
            'api_key' => new TextboxField(array(
                'label' => __('API Key'),
                'required' => true,
                'configuration' => array(
                    'size' => 60,
                    'length' => 500,
                    'placeholder' => 'sk-...'
                ),
                'hint' => __('Your API key. Get it for example from https://platform.openai.com/api-keys')
            )),
            'api_url' => new TextboxField(array(
                'label' => __('API URL'),
                'required' => false,
                'configuration' => array(
                    'size' => 60,
                    'length' => 500,
                    'placeholder' => 'https://api.example.com/v1/chat/completions'
                ),
                'hint' => __('Custom API endpoint URL (compatible with OpenAI)')
            )),
            'model' => new AIAutoDeptTransferModelField(array(
                'label' => __('Model Name'),
                'default' => 'gpt-6-luna',
                'required' => true,
                'hint' => __('Select or enter the model name to use for analysis')
            )),
            'reasoning_effort' => new ChoiceField(array(
                'label' => __('Reasoning Effort'),
                'default' => 'none',
                'configuration' => array(
                    'classes' => 'reasoning-effort-select',
                    'data' => array(
                        'reasoning-rules' => json_encode(AIAutoDeptTransferModelCapabilities::REASONING_RULES),
                        'unavailable-hint' => __('Reasoning configuration is not available for the selected models.')
                    )
                ),
                'choices' => array(
                    'none' => __('None'),
                    'minimal' => __('Minimal'),
                    'low' => __('Low'),
                    'medium' => __('Medium'),
                    'high' => __('High'),
                    'xhigh' => __('Extra High'),
                    'max' => __('Maximum')
                ),
                'hint' => __('Reasoning level for analysis and image text extraction with supported models. Higher levels may increase response time and cost.')
            )),
            'vision_model' => new AIAutoDeptTransferVisionModelField(array(
                'label' => __('Vision Model'),
                'default' => 'gpt-6-luna',
                'required' => false,
                'hint' => __('Optional: Vision-capable model for image text extraction (e.g., gpt-6-luna). Autocomplete suggests common OpenAI vision models, but any model name is allowed.')
            )),
            'temperature' => new TextboxField(array(
                'label' => __('Temperature'),
                'default' => '0.3',
                'required' => false,
                'configuration' => array(
                    'size' => 10,
                    'length' => 4
                ),
                'hint' => __('Advanced: Controls response randomness (0.0-2.0). Default: 0.3. For supported reasoning models, used only when Reasoning Effort is None.')
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
            'allowed_depts' => new AIAutoDeptTransferDepartmentMultiselectField(array(
                'label' => __('Departments with Manual Transfer Button'),
                'required' => false,
                'default' => [],
                'configuration' => array(
                        'multiselect' => true
                ),
                'hint' => __('Select departments that can see the manual transfer button. Leave empty to show for all departments (including future ones).')
            )),
            'show_files_info' => new BooleanField(array(
                'label' => __('Show processed files info'),
                'default' => false,
                'configuration' => array(
                    'desc' => __('Add analyzed file contents anr/or names of ignored to the transfer decision message (debug mode)')
                )
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

    function pre_save(&$config, &$errors) {

        $result = true;
        if ('openai' === $config['api_provider']) {
            // For OpenAI provider, set default API URL
            $config['api_url'] = 'https://api.openai.com/v1/chat/completions';
        }

        // Validate API URL
        if (empty($config['api_url'])) {
            $errors['api_url'] = __('API URL is required for Custom provider');
            $result = false;
        }

        if (isset($config['temperature'])) {
            $config['temperature'] = (float) $config['temperature'];
            if (0.0 > $config['temperature'] || $config['temperature'] > 2.0) {
                $errors['temperature'] = __('Value is out of range');
                $result = false;
            }
        }

        $efforts = AIAutoDeptTransferModelCapabilities::commonReasoningEfforts(
            (string) ($config['model'] ?? ''),
            (string) ($config['vision_model'] ?? '')
        );
        $config['reasoning_effort'] = $config['reasoning_effort'] ?? ($efforts[0] ?? 'none');
        if (!in_array($config['reasoning_effort'], array('none', 'minimal', 'low', 'medium', 'high', 'xhigh', 'max'), true)) {
            $errors['reasoning_effort'] = __('Invalid reasoning effort');
            $result = false;
        } elseif ($efforts && !in_array($config['reasoning_effort'], $efforts, true)) {
            $errors['reasoning_effort'] = __('This reasoning effort is not supported by the selected models.');
            $result = false;
        }

        return $result;
    }
}
