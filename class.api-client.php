<?php

/**
 * Handles file processing and AI-based department selection using OpenAI API
 */
class AIAutoDeptTransferAPIClient {
    
    private string $api_key;
    private string $api_url;
    private bool $enable_logging;
    private string $model;
    private float $temperature;
    private int $timeout;
    private ?string $vision_model;
    
    public function __construct(string $api_url, string $api_key, string $model, ?string $vision_model, int $timeout, bool $enable_logging, float $temperature) {
        $this->api_key = trim($api_key);
        $this->api_url = $api_url;
        $this->enable_logging = $enable_logging;
        $this->model = $model;
        $this->temperature = $temperature;
        $this->timeout = $timeout;
        $this->vision_model = $vision_model ? trim($vision_model) : null;
    }
    
    /**
     * Extract text from image using a vision-capable model
     * 
     * @param string $file_data Binary file data
     * @param string $mime_type MIME type of the image
     * @return array{success: bool, text?: string, error?: string, model?: string} Result with extracted text or error
     */
    public function extractTextFromImage(string $file_data, string $mime_type): array {
        // Convert to base64
        $base64_image = base64_encode($file_data);
        
        // Validate image type
        $supported_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array($mime_type, $supported_types)) {
            return array(
                'success' => false,
                'error' => 'Unsupported image type: ' . $mime_type
            );
        }

        // Prefer dedicated vision model, otherwise fall back to general model, and only then to default
        $vision_model = $this->vision_model ?: $this->model;
        if (!$vision_model) {
            $vision_model = 'gpt-4o';
            if ($this->enable_logging) {
                error_log("Auto Dept Transfer - Falling back to vision model gpt-4o (configured model: {$this->model})");
            }
        }
        
        $messages = array(
            array(
                'role' => 'system',
                'content' => 'You are an OCR assistant. Extract all text from the image and return it as plain text. If the image contains no readable text, return "No text found".'
            ),
            array(
                'role' => 'user',
                'content' => array(
                    array(
                        'type' => 'text',
                        'text' => 'Extract all text from this image:'
                    ),
                    array(
                        'type' => 'image_url',
                        'image_url' => array(
                            'url' => 'data:' . $mime_type . ';base64,' . $base64_image
                        )
                    )
                )
            )
        );
        
        $result = $this->makeRequest($messages, $vision_model, false);
        
        if (!$result['success']) {
            return $result;
        }
        
        $text = is_string($result['data']) ? $result['data'] : '';
        
        return array(
            'success' => true,
            'text' => $text,
            'model' => $vision_model
        );
    }
    
    /**
     * Select best department when multiple matches found
     * 
     * @param string $ticket_content Full ticket content
     * @param array $matched_depts Array of matched departments with reasons
     * @return array Result with selected department
     */
    public function selectBestDepartment($ticket_content, $matched_depts) {
        if (empty($matched_depts)) {
            return array(
                'success' => false,
                'error' => 'No departments provided'
            );
        }
        
        if (count($matched_depts) == 1) {
            return array(
                'success' => true,
                'dept_id' => $matched_depts[0]['dept_id'],
                'dept_name' => $matched_depts[0]['dept_name'],
                'reason' => $matched_depts[0]['reason']
            );
        }
        
        // Build prompt for AI selection
        $prompt = "Analyze the following ticket content and select the most appropriate department from the matched options.\n\n";
        $prompt .= "TICKET CONTENT:\n" . substr($ticket_content, 0, 2000) . "\n\n";
        $prompt .= "MATCHED DEPARTMENTS:\n";
        
        foreach ($matched_depts as $dept) {
            $prompt .= "Department ID: " . $dept['dept_id'] . "\n";
            $prompt .= "Department Name: " . $dept['dept_name'] . "\n";
            $prompt .= "Matched Keywords: " . $dept['reason'] . "\n\n";
        }
        
        $prompt .= "Return JSON with:\n";
        $prompt .= '{"best_dept_id": <ID>, "reasoning": "<why this department>"}';
        
        $messages = array(
            array(
                'role' => 'system',
                'content' => 'You are a ticket routing expert. Select the most appropriate department based on ticket content. Always respond with valid JSON.'
            ),
            array(
                'role' => 'user',
                'content' => $prompt
            )
        );
        
        $result = $this->makeRequest($messages, $this->model, true);
        
        if (!$result['success']) {
            return $result;
        }
        
        // Parse JSON response
        $analysis = json_decode($result['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'error' => 'Failed to parse AI response: ' . json_last_error_msg()
            );
        }
        
        if (!isset($analysis['best_dept_id'])) {
            return array(
                'success' => false,
                'error' => 'AI response missing department ID'
            );
        }
        
        // Find the selected department
        foreach ($matched_depts as $dept) {
            if ($dept['dept_id'] == $analysis['best_dept_id']) {
                return array(
                    'success' => true,
                    'dept_id' => $dept['dept_id'],
                    'dept_name' => $dept['dept_name'],
                    'reason' => $analysis['reasoning'] ?? $dept['reason']
                );
            }
        }
        
        return array(
            'success' => false,
            'error' => 'AI selected invalid department'
        );
    }
    
    /**
     * Make HTTP request to OpenAI API
     * 
     * @param array<int,array<string,string>> $messages Messages array for chat completion
     * @param string $model Model to use
     * @param bool $json_mode Enable JSON response mode
     *
     * @return array{success: bool, data?: string, error?: string } Result with data or error
     */
    private function makeRequest(array $messages, string $model, bool $json_mode): array {
        $data = array(
            'model' => $model,
            'messages' => $messages,
            'temperature' => $this->temperature
        );
        
        if ($json_mode) {
            $data['response_format'] = array('type' => 'json_object');
        }
        
        $json_data = json_encode($data);
        
        if ($this->enable_logging) {
            error_log("Auto Dept Transfer - API Request: " . $json_data);
        }
        
        $ch = curl_init($this->api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key
        );
        if ($json_mode) {
            $headers[] = 'Accept: application/json';
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            return array(
                'success' => false,
                'error' => 'CURL Error: ' . $curl_error
            );
        }
        
        if ($http_code !== 200) {
            $error_data = json_decode($response, true);
            $error_msg = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown error';
            return array(
                'success' => false,
                'error' => 'OpenAI API Error (' . $http_code . '): ' . $error_msg
            );
        }
        
        $result = json_decode($response, true);
        
        if ($this->enable_logging) {
            error_log("Auto Dept Transfer - API Response: " . $response);
        }
        
        $content = $result['choices'][0]['message']['content'] ?? null;
        
        // OpenAI can return either a string or an array of content parts (for vision)
        if (is_array($content)) {
            $parts = array();
            foreach ($content as $part) {
                if (is_array($part) && ($part['type'] ?? '') === 'text' && isset($part['text'])) {
                    $parts[] = $part['text'];
                }
            }
            $content = trim(implode("\n", $parts));
        }

        if (!is_string($content)) {
            return array(
                'success' => false,
                'error' => 'Invalid API response format'
            );
        }

        if ($content === '') {
            return array(
                'success' => false,
                'error' => 'API response contained no text content'
            );
        }
        
        return array(
            'success' => true,
            'data' => $content
        );
    }
}

