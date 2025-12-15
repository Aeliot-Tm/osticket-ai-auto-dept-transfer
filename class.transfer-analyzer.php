<?php

require_once('class.api-client.php');

/**
 * Transfer Analyzer
 * Analyzes tickets and determines appropriate department based on keywords
 */
class AIAutoDeptTransferAnalyzer {

    private ?AIAutoDeptTransferAPIClient $apiClient = null;
    private AIAutoDeptTransferConfig $config;
    private string $notePoster = 'AI Department Detector';

    public function __construct(AIAutoDeptTransferConfig $config) {
        $this->config = $config;

        $api_key = $config->get('api_key');
        $model = $config->get('model');
        $api_url = $config->get('api_url');

        if ($api_key && $model && $api_url) {
            $temperature = $config->get('temperature');
            if ('' === trim((string)$temperature)) {
                $temperature = 0.3;
            }
            $this->apiClient = new AIAutoDeptTransferAPIClient(
                $api_url,
                $api_key,
                $model,
                $config->get('vision_model'),
                (int) $config->get('timeout', 30),
                (bool) $config->get('enable_logging', false),
                (float) $temperature
            );
        }
    }
    
    /**
     * Analyze ticket and determine if transfer is needed
     *
     * @return array Result with transfer recommendation
     */
    public function analyzeTicket(Ticket $ticket) {
        $analyzed_files = array();
        $ignored_files = array();
        try {
            // Get department rules
            $rules = $this->config->getDeptRules();
            if (empty($rules)) {
                return array(
                    'success' => false,
                    'error' => 'No department rules configured',
                    'analyzed_files' => $analyzed_files,
                    'ignored_files' => $ignored_files
                );
            }

            if (!$this->apiClient) {
                return array(
                    'success' => false,
                    'error' => 'API client not configured. Please check plugin settings (API Key, Model, API URL).',
                    'analyzed_files' => $analyzed_files,
                    'ignored_files' => $ignored_files
                );
            }
            
            // Extract all content from ticket
            $content_data = $this->extractTicketContent($ticket);
            $content = $content_data['content'];
            $analyzed_files = $content_data['analyzed_files'];
            $ignored_files = $content_data['ignored_files'];
            
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
                    'message' => 'No matching keywords found for any department',
                    'analyzed_files' => $analyzed_files,
                    'ignored_files' => $ignored_files
                );
            }
            
            // If single match, use it
            if (count($matches) == 1) {
                return array(
                    'success' => true,
                    'dept_id' => $matches[0]['dept_id'],
                    'dept_name' => $matches[0]['dept_name'],
                    'reason' => $matches[0]['reason'],
                    'confidence' => 'high',
                    'analyzed_files' => $analyzed_files,
                    'ignored_files' => $ignored_files
                );
            }
            
            // Multiple matches - use AI to select best one
            $selection = $this->apiClient->selectBestDepartment($content, $matches);
            
            if ($selection['success']) {
                $selection['confidence'] = 'medium';
            }
            
            $selection['analyzed_files'] = $analyzed_files;
            $selection['ignored_files'] = $ignored_files;
            
            return $selection;
            
        } catch (Exception $e) {
            if ($this->config->get('enable_logging')) {
                error_log("Auto Dept Transfer - Exception: " . $e->getMessage());
            }
            return array(
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'analyzed_files' => $analyzed_files,
                'ignored_files' => $ignored_files
            );
        }
    }
    
    /**
     * Extract all content from ticket including subject, body, and attachments
     * 
     * @param Ticket $ticket
     * @return array Array with 'content' (string), 'analyzed_files' (array), and 'ignored_files' (array)
     */
    private function extractTicketContent($ticket) {
        $content = array();
        $analyzed_files = array();
        $ignored_files = array();
        
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
                        $file_info = $this->processAttachment($attachment);
                        if ($file_info['content'] ?? false) {
                            $content[] = 'File content: ' . $file_info['content'];
                        }
                        if ($file_info['ignored']) {
                            $ignored_files[] = $file_info;
                        } else {
                            $analyzed_files[] = $file_info;
                        }
                    }
                }
            }
        }
        
        return array(
            'content' => implode("\n\n", $content),
            'analyzed_files' => $analyzed_files,
            'ignored_files' => $ignored_files
        );
    }
    
    /**
     * Extract text from attachment file
     * 
     * @param object $attachment Attachment object
     * @return array|null Array with 'filename', 'content' (if successful), 'ignored' and 'reason' (if ignored) keys, or null
     */
    private function processAttachment($attachment) {
        try {
            $file = $attachment->getFile();
            if (!$file) {
                return array(
                    'ignored' => true,
                    'filename' => $attachment->getFilename() ?? 'Unknown',
                    'reason' => 'File could not be accessed or processed'
                );
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
                return array(
                    'filename' => $filename,
                    'ignored' => true,
                    'reason' => sprintf('File size (%s) exceeds maximum allowed size (%s MB)', 
                        $this->formatFileSize($size), 
                        $this->config->get('max_file_size'))
                );
            }
            
            if ($this->config->get('enable_logging')) {
                error_log("Auto Dept Transfer - Processing file: $filename (type: $mime_type, size: $size)");
            }
            
            $file_text = null;
            $extraction_error = null;

            // Handle images with Vision API
            if (preg_match('/^image\/(jpeg|jpg|png|gif|webp)$/i', $mime_type)) {
                $vision_result = $this->extractTextFromImage($file);
                if (!$vision_result['success']) {
                    $reason = 'Image text extraction failed';
                    if (!empty($vision_result['error'])) {
                        $reason .= ': ' . $vision_result['error'];
                    }
                    return array(
                        'filename' => $filename,
                        'ignored' => true,
                        'reason' => $reason
                    );
                }

                $file_text = (string) ($vision_result['text'] ?? '');

                // If the Vision API explicitly reports no text, keep that reason
                if ('' !== $file_text && strcasecmp(trim($file_text), 'No text found') === 0) {
                    return array(
                        'filename' => $filename,
                        'ignored' => true,
                        'reason' => 'Vision API reported no readable text in the image'
                    );
                }
            }
            // Handle PDF files
            elseif ($mime_type == 'application/pdf') {
                $file_text = $this->extractTextFromPDF($file);
                $extraction_error = 'Unable to extract text from PDF';
            }
            // Handle Word documents
            elseif (preg_match('/word|officedocument\.wordprocessing/i', $mime_type)) {
                $file_text = $this->extractTextFromWord($file);
                $extraction_error = 'Unable to extract text from Word document';
            }
            else {
                if ($this->config->get('enable_logging')) {
                    error_log("Auto Dept Transfer - Unsupported file type: $mime_type");
                }
                return array(
                    'filename' => $filename,
                    'ignored' => true,
                    'reason' => 'Unsupported file type: ' . $mime_type
                );
            }
            
            if (null !== $file_text) {
                $file_text = trim((string) $file_text);
            }
            
            if (!$file_text) {
                return array(
                    'filename' => $filename,
                    'ignored' => true,
                    'reason' => $extraction_error ?: 'No text content returned after extraction'
                );
            }
            
            return array(
                'filename' => $filename,
                'ignored' => false,
                'content' => $file_text
            );
            
        } catch (Exception $e) {
            if ($this->config->get('enable_logging')) {
                error_log("Auto Dept Transfer - Error processing attachment: " . $e->getMessage());
            }
            
            return array(
                'filename' => $attachment->getFilename() ?? 'Unknown',
                'ignored' => true,
                'reason' => 'Error processing file: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Format file size in human-readable format
     *
     * @param int $bytes File size in bytes
     * @return string Formatted size
     */
    private function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
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
            return array(
                'success' => true,
                'text' => $result['text'] ?? '',
                'model' => $result['model'] ?? null
            );
        }
        
        if ($this->config->get('enable_logging')) {
            error_log("Auto Dept Transfer - Image extraction failed: " . ($result['error'] ?? 'Unknown error'));
        }
        
        return array(
            'success' => false,
            'error' => $result['error'] ?? 'Unknown error'
        );
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
     * @param array $analyzed_files Array of analyzed files with 'filename' and 'content' keys
     * @param array $ignored_files Array of ignored files with 'filename' and 'reason' keys
     * @return array{success: bool, message: string } Success status
     */
    public function transferTicket($ticket, $dept_id, $reason, $analyzed_files = array(), $ignored_files = array()) {
        try {
            $current_dept_id = $ticket->getDeptId();
            
            // Don't transfer if already in target department
            if ($current_dept_id == $dept_id) {
                if ($this->config->get('enable_logging')) {
                    error_log("Auto Dept Transfer - Ticket already in department $dept_id");
                }
                
                return ['success' => false, 'message' => 'Ticket already in target department'];
            }
            
            // Get department names
            $current_dept = $ticket->getDept();
            $target_dept = Dept::lookup($dept_id);
            
            if (!$target_dept || !$target_dept->isActive()) {
                if ($this->config->get('enable_logging')) {
                    error_log("Auto Dept Transfer - Target department not found or inactive: $dept_id");
                }
                
                return ['success' => false, 'message' => "Target department ($dept_id) not found or inactive"];
            }
            
            // Perform transfer
            $success = $ticket->setDeptId($dept_id);
            if (!$success){
                return ['success' => false, 'message' => 'Ticket transfer failed'];
            }
            
            // Log internal note
            $note_title = 'Auto Department Transfer';
            $note_body = sprintf(
                'Ticket automatically transferred from "%s" to "%s".<br><br>Reason: %s',
                $current_dept->getName(),
                $target_dept->getName(),
                htmlspecialchars($reason)
            );
            
            // Add file contents if option is enabled
            $note_body .= $this->getAnalyzedFilesNote($analyzed_files);
            
            // Add ignored files list (always show if there are ignored files)
            $note_body .= $this->getIgnoredFilesNote($ignored_files);
            
            $ticket->logNote($note_title, $note_body,  $this->notePoster, false);
            
            return ['success' => true, 'message' => "Successfully transferred ticket #{$ticket->getNumber()} to {$target_dept->getName()}"];
            
        } catch (Exception $e) {
            if ($this->config->get('enable_logging')) {
                error_log("Auto Dept Transfer - Transfer failed: " . $e->getMessage());
            }
            
            return ['success' => false, 'message' => 'Internal exception happened. See log for details.'];
        }
    }
    
    /**
     * Log a note when transfer cannot be performed
     * 
     * @param Ticket $ticket
     * @param string $reason Reason why transfer failed
     */
    public function logTransferFailure($ticket, $reason, $analyzed_files, $ignored_files) {
        try {
            $note_title = 'Auto Department Transfer - Not Performed';
            $note_body = 'Automatic department transfer was not performed.<br><br>Reason: ' . htmlspecialchars($reason);
            
            // Add file contents if option is enabled
            $note_body .= $this->getAnalyzedFilesNote($analyzed_files);
            
            // Add ignored files list (always show if there are ignored files)
            $note_body .= $this->getIgnoredFilesNote($ignored_files);
            
            $ticket->logNote($note_title, $note_body, $this->notePoster, false);
            
        } catch (Exception $e) {
            if ($this->config->get('enable_logging')) {
                error_log("Auto Dept Transfer - Failed to log note: " . $e->getMessage());
            }
        }
    }
    
    /**
     * @param array<array<string,mixed>> $analyzed_files
     * @return string
     */
    private function getAnalyzedFilesNote($analyzed_files)
    {
        $note_body = '';
        if ($this->config->get('show_files_info') && $analyzed_files) {
            $note_body .= '<br><br>';
            
            foreach ($analyzed_files as $file_info) {
                $note_body .= '<hr>';
                $note_body .= 'Text from file \'' . htmlspecialchars($file_info['filename']) . '\'<br>';
                $note_body .= '<pre>' . htmlspecialchars($file_info['content']) . '</pre>';
            }
        }
        
        return $note_body;
    }
    
    /**
     * @param array<array<string,mixed>> $ignored_files
     * @return string
     */
    private function getIgnoredFilesNote($ignored_files)
    {
        $note_body = '';
        if ($this->config->get('show_files_info') && $ignored_files) {
            $note_body .= '<br><br>';
            $note_body .= '<hr>';
            $note_body .= '<strong>Ignored files:</strong><br>';
            $note_body .= '<ul>';
            foreach ($ignored_files as $file_info) {
                $note_body .= '<li>' . htmlspecialchars($file_info['filename']) . ' - ' . htmlspecialchars($file_info['reason']) . '</li>';
            }
            $note_body .= '</ul>';
        }
        
        return $note_body;
    }
}

