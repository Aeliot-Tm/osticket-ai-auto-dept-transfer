<?php

require_once('class.api-client.php');

/**
 * Transfer Analyzer
 * Analyzes tickets and determines appropriate department based on keywords
 */
class AIAutoDeptTransferAnalyzer {

    private ?AIAutoDeptTransferAPIClient $apiClient = null;
    private AIAutoDeptTransferConfig $config;

    public function __construct(AIAutoDeptTransferConfig $config) {
        $this->config = $config;

        $api_key = $config->get('api_key');
        $model = $config->get('model');
        $api_url = $config->get('api_url');

        if ($api_key && $model && $api_url) {
            $this->apiClient = new AIAutoDeptTransferAPIClient(
                $api_key,
                $model,
                $api_url,
                (int) $config->get('timeout', 30),
                (bool) $config->get('enable_logging', false),
            );
        }
    }
    
    /**
     * Analyze ticket and determine if transfer is needed
     * 
     * @param int $ticket_id Ticket ID
     * @return array Result with transfer recommendation
     */
    public function analyzeTicket($ticket_id) {
        try {
            $ticket = Ticket::lookup($ticket_id);
            
            if (!$ticket) {
                return array(
                    'success' => false,
                    'error' => 'Ticket not found'
                );
            }
            
            // Get department rules
            $rules = $this->config->getDeptRules();
            if (empty($rules)) {
                return array(
                    'success' => false,
                    'error' => 'No department rules configured'
                );
            }

            if (!$this->apiClient) {
                return array(
                    'success' => false,
                    'error' => 'API client not configured. Please check plugin settings (API Key, Model, API URL).'
                );
            }
            
            // Extract all content from ticket
            $content = $this->extractTicketContent($ticket);
            
            if ($this->config->get('enable_logging')) {
                error_log("Auto Dept Transfer - Analyzing ticket #" . $ticket->getNumber());
                error_log("Auto Dept Transfer - Content length: " . strlen($content));
            }
            
            // Find matching departments
            $matches = $this->findMatchingDepartments($content, $rules);
            
            if (empty($matches)) {
                return array(
                    'success' => false,
                    'no_match' => true,
                    'message' => 'No matching keywords found for any department'
                );
            }
            
            // If single match, use it
            if (count($matches) == 1) {
                return array(
                    'success' => true,
                    'dept_id' => $matches[0]['dept_id'],
                    'dept_name' => $matches[0]['dept_name'],
                    'reason' => $matches[0]['reason'],
                    'confidence' => 'high'
                );
            }
            
            // Multiple matches - use AI to select best one
            $selection = $this->apiClient->selectBestDepartment($content, $matches);
            
            if ($selection['success']) {
                $selection['confidence'] = 'medium';
            }
            
            return $selection;
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Extract all content from ticket including subject, body, and attachments
     * 
     * @param Ticket $ticket
     * @return string Combined content
     */
    private function extractTicketContent($ticket) {
        $content = array();
        
        // Add subject
        $subject = $ticket->getSubject();
        if ($subject) {
            $content[] = 'Subject: ' . $subject;
        }
        
        // Get thread entries
        $thread = $ticket->getThread();
        if ($thread) {
            $entries = $thread->getEntries();
            
            foreach ($entries as $entry) {
                // Add message body
                $body = $entry->getBody();
                if ($body) {
                    $text = strip_tags($body->getClean());
                    if (!empty(trim($text))) {
                        $content[] = $text;
                    }
                }
                
                // Process attachments
                if ($entry->has_attachments && isset($entry->attachments)) {
                    foreach ($entry->attachments as $attachment) {
                        $file_text = $this->processAttachment($attachment);
                        if ($file_text) {
                            $content[] = 'File content: ' . $file_text;
                        }
                    }
                }
            }
        }
        
        return implode("\n\n", $content);
    }
    
    /**
     * Extract text from attachment file
     * 
     * @param object $attachment Attachment object
     * @return string|null Extracted text or null
     */
    private function processAttachment($attachment) {
        try {
            $file = $attachment->getFile();
            if (!$file) {
                return null;
            }
            
            $filename = $attachment->getFilename();
            $size = $file->getSize();
            $mime_type = $file->getType();
            
            // Check file size limit
            $max_size = intval($this->config->get('max_file_size')) * 1024 * 1024; // Convert MB to bytes
            if ($size > $max_size) {
                if ($this->config->get('enable_logging')) {
                    error_log("Auto Dept Transfer - File too large: $filename ($size bytes)");
                }
                return null;
            }
            
            if ($this->config->get('enable_logging')) {
                error_log("Auto Dept Transfer - Processing file: $filename (type: $mime_type, size: $size)");
            }
            
            // Handle images with Vision API
            if (preg_match('/^image\/(jpeg|jpg|png|gif|webp)$/i', $mime_type)) {
                return $this->extractTextFromImage($file);
            }
            
            // Handle PDF files
            if ($mime_type == 'application/pdf') {
                return $this->extractTextFromPDF($file);
            }
            
            // Handle Word documents
            if (preg_match('/word|officedocument\.wordprocessing/i', $mime_type)) {
                return $this->extractTextFromWord($file);
            }
            
            if ($this->config->get('enable_logging')) {
                error_log("Auto Dept Transfer - Unsupported file type: $mime_type");
            }
            
            return null;
            
        } catch (Exception $e) {
            if ($this->config->get('enable_logging')) {
                error_log("Auto Dept Transfer - Error processing attachment: " . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Extract text from image using AI
     */
    private function extractTextFromImage($file) {
        $file_data = $file->getData();
        $mime_type = $file->getType();
        
        if ($this->config->get('enable_logging')) {
            error_log("Auto Dept Transfer - Processing image file: " . $file->getName() . " (mime: " . $mime_type . ", size: " . strlen($file_data) . " bytes)");
        }
        
        $result = $this->apiClient->extractTextFromImage($file_data, $mime_type);
        
        if ($result['success']) {
            if ($this->config->get('enable_logging')) {
                error_log("Auto Dept Transfer - Successfully extracted " . strlen($result['text']) . " bytes from image using Vision API");
            }
            return $result['text'];
        }
        
        if ($this->config->get('enable_logging')) {
            error_log("Auto Dept Transfer - Image extraction failed: " . ($result['error'] ?? 'Unknown error'));
        }
        
        return null;
    }
    
    /**
     * Extract text from PDF
     */
    private function extractTextFromPDF($file) {
        if (!function_exists('shell_exec')) {
            if ($this->config->get('enable_logging')) {
                error_log("Auto Dept Transfer - shell_exec not available for PDF extraction");
            }
            return null;
        }
        
        if ($this->config->get('enable_logging')) {
            error_log("Auto Dept Transfer - Processing PDF file: " . $file->getName() . " (mime: " . $file->getMimeType() . ")");
        }
        
        $tmpfile = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($tmpfile, $file->getData());
        
        // Use pdftotext to extract text from PDF
        $output = shell_exec("pdftotext '$tmpfile' - 2>/dev/null");
        
        unlink($tmpfile);
        
        if ($output && !empty(trim($output))) {
            if ($this->config->get('enable_logging')) {
                error_log("Auto Dept Transfer - Successfully extracted " . strlen($output) . " bytes from PDF document");
            }
            return $output;
        }
        
        if ($this->config->get('enable_logging')) {
            error_log("Auto Dept Transfer - Failed to extract text from PDF (empty output from pdftotext)");
        }
        
        return null;
    }
    
    /**
     * Extract text from Word document (.doc and .docx)
     */
    private function extractTextFromWord($file) {
        if (!function_exists('shell_exec')) {
            if ($this->config->get('enable_logging')) {
                error_log("Auto Dept Transfer - shell_exec not available");
            }
            return null;
        }
        
        $tmpfile = tempnam(sys_get_temp_dir(), 'doc_');
        file_put_contents($tmpfile, $file->getData());
        
        $output = '';
        $mime_type = $file->getType();
        
        // Get filename to check extension as fallback
        $filename = method_exists($file, 'getName') ? $file->getName() : '';
        if (!$filename && isset($file->filename)) {
            $filename = $file->filename;
        }
        
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if ($this->config->get('enable_logging')) {
            error_log("Auto Dept Transfer - Processing Word file: $filename (mime: $mime_type, ext: $extension)");
        }
        
        // Determine if it's .doc or .docx based on MIME type and extension
        $is_doc_legacy = (
            stripos($mime_type, 'msword') !== false && 
            stripos($mime_type, 'officedocument') === false
        ) || $extension === 'doc';
        
        $is_docx = (
            stripos($mime_type, 'wordprocessingml') !== false || 
            stripos($mime_type, 'officedocument') !== false
        ) || $extension === 'docx';
        
        // Try antiword for legacy .doc files
        if ($is_doc_legacy) {
            if ($this->config->get('enable_logging')) {
                error_log("Auto Dept Transfer - Detected legacy .doc file, trying antiword");
            }
            
            $output = shell_exec("antiword '$tmpfile' 2>/dev/null");
            
            if (!empty(trim($output))) {
                if ($this->config->get('enable_logging')) {
                    error_log("Auto Dept Transfer - Successfully extracted " . strlen($output) . " bytes using antiword");
                }
            } else {
                // Try catdoc as alternative
                if ($this->config->get('enable_logging')) {
                    error_log("Auto Dept Transfer - antiword failed, trying catdoc");
                }
                $output = shell_exec("catdoc '$tmpfile' 2>/dev/null");
                
                if (!empty(trim($output))) {
                    if ($this->config->get('enable_logging')) {
                        error_log("Auto Dept Transfer - Successfully extracted " . strlen($output) . " bytes using catdoc");
                    }
                } else {
                    if ($this->config->get('enable_logging')) {
                        error_log("Auto Dept Transfer - Both antiword and catdoc failed, trying .docx format as fallback");
                    }
                    // Mark as potential .docx for fallback attempt
                    $is_docx = true;
                }
            }
        }
        
        // Try extracting .docx (Office Open XML) if:
        // 1. Detected as .docx format, OR
        // 2. .doc extraction failed (fallback), OR  
        // 3. Format couldn't be determined
        if (empty(trim($output)) && ($is_docx || !$is_doc_legacy)) {
            if ($this->config->get('enable_logging')) {
                error_log("Auto Dept Transfer - Trying to extract as .docx using unzip");
            }
            
            // Create temp directory for extraction
            $tmpdir = sys_get_temp_dir() . '/docx_' . uniqid();
            mkdir($tmpdir);
            
            // Extract document.xml
            shell_exec("unzip -q -d '$tmpdir' '$tmpfile' word/document.xml 2>/dev/null");
            
            $xml_file = $tmpdir . '/word/document.xml';
            if (file_exists($xml_file)) {
                $xml_content = file_get_contents($xml_file);
                
                // Remove XML tags and extract text
                // This regex preserves text content while removing tags
                $text = preg_replace('/<[^>]+>/', ' ', $xml_content);
                
                // Clean up whitespace
                $text = preg_replace('/\s+/', ' ', $text);
                $output = trim($text);
                
                if (!empty($output)) {
                    if ($this->config->get('enable_logging')) {
                        error_log("Auto Dept Transfer - Successfully extracted " . strlen($output) . " bytes from .docx");
                    }
                }
            } else {
                if ($this->config->get('enable_logging')) {
                    error_log("Auto Dept Transfer - Failed to extract document.xml from .docx");
                }
            }
            
            // Clean up temp directory
            shell_exec("rm -rf '$tmpdir'");
        }
        
        unlink($tmpfile);
        
        if ($output && !empty(trim($output))) {
            return $output;
        }
        
        if ($this->config->get('enable_logging')) {
            error_log("Auto Dept Transfer - Failed to extract text from Word document (mime: $mime_type, ext: $extension)");
        }
        
        return null;
    }
    
    /**
     * Find departments matching keywords in content
     * 
     * @param string $content Ticket content
     * @param array $rules Department rules
     * @return array Matched departments with reasons
     */
    private function findMatchingDepartments($content, $rules) {
        $content_lower = mb_strtolower($content, 'UTF-8');
        $matches = array();
        
        foreach ($rules as $rule) {
            if (!isset($rule['dept_id']) || !isset($rule['keywords'])) {
                continue;
            }
            
            $dept_id = intval($rule['dept_id']);
            $keywords = $rule['keywords'];
            
            // Parse keywords (comma or semicolon separated)
            $keyword_list = preg_split('/[,;]/', $keywords);
            $keyword_list = array_map('trim', $keyword_list);
            $keyword_list = array_filter($keyword_list); // Remove empty values
            $found_keywords = array();
            
            foreach ($keyword_list as $keyword) {
                if (empty($keyword)) {
                    continue;
                }
                
                $keyword_lower = mb_strtolower($keyword, 'UTF-8');
                
                // Check if keyword exists in content (case-insensitive, word boundary aware)
                if (mb_stripos($content_lower, $keyword_lower) !== false) {
                    $found_keywords[] = $keyword;
                }
            }
            
            // If any keywords matched, add to results
            if (!empty($found_keywords)) {
                // Get department name
                $dept = Dept::lookup($dept_id);
                if ($dept && $dept->isActive()) {
                    $matches[] = array(
                        'dept_id' => $dept_id,
                        'dept_name' => $dept->getName(),
                        'reason' => 'Found keywords: ' . implode(', ', $found_keywords),
                        'keyword_count' => count($found_keywords)
                    );
                }
            }
        }
        
        // Sort by keyword count (descending)
        usort($matches, function($a, $b) {
            return $b['keyword_count'] - $a['keyword_count'];
        });
        
        return $matches;
    }
    
    /**
     * Transfer ticket to new department and log note
     * 
     * @param Ticket $ticket
     * @param int $dept_id Target department ID
     * @param string $reason Reason for transfer
     * @return bool Success status
     */
    public function transferTicket($ticket, $dept_id, $reason) {
        try {
            $current_dept_id = $ticket->getDeptId();
            
            // Don't transfer if already in target department
            if ($current_dept_id == $dept_id) {
                if ($this->config->get('enable_logging')) {
                    error_log("Auto Dept Transfer - Ticket already in department $dept_id");
                }
                return false;
            }
            
            // Get department names
            $current_dept = $ticket->getDept();
            $target_dept = Dept::lookup($dept_id);
            
            if (!$target_dept || !$target_dept->isActive()) {
                if ($this->config->get('enable_logging')) {
                    error_log("Auto Dept Transfer - Target department not found or inactive: $dept_id");
                }
                return false;
            }
            
            // Perform transfer
            $success = $ticket->setDeptId($dept_id);
            
            if ($success) {
                // Log internal note
                $note_title = 'Auto Department Transfer';
                $note_body = sprintf(
                    'Ticket automatically transferred from "%s" to "%s".<br><br>Reason: %s',
                    $current_dept->getName(),
                    $target_dept->getName(),
                    htmlspecialchars($reason)
                );
                
                $ticket->logNote($note_title, $note_body, 'SYSTEM', false);
                
                if ($this->config->get('enable_logging')) {
                    error_log("Auto Dept Transfer - Successfully transferred ticket #{$ticket->getNumber()} to {$target_dept->getName()}");
                }
            }
            
            return $success;
            
        } catch (Exception $e) {
            if ($this->config->get('enable_logging')) {
                error_log("Auto Dept Transfer - Transfer failed: " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Log a note when transfer cannot be performed
     * 
     * @param Ticket $ticket
     * @param string $reason Reason why transfer failed
     */
    public function logTransferFailure($ticket, $reason) {
        try {
            $note_title = 'Auto Department Transfer - Not Performed';
            $note_body = 'Automatic department transfer was not performed.<br><br>Reason: ' . htmlspecialchars($reason);
            
            $ticket->logNote($note_title, $note_body, 'SYSTEM', false);
            
        } catch (Exception $e) {
            if ($this->config->get('enable_logging')) {
                error_log("Auto Dept Transfer - Failed to log note: " . $e->getMessage());
            }
        }
    }
}

