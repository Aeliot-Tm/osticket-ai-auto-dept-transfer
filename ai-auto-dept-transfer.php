<?php

require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.signal.php');
require_once(INCLUDE_DIR . 'class.dispatcher.php');
require_once('config.php');

// --- ГЛОБАЛЬНЫЕ ФУНКЦИИ-ОБРАБОТЧИКИ ---

function ai_auto_dept_transfer_handle_analyze() {
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
            Http::response(200, json_encode(array(
                'success' => false,
                'error' => 'FATAL ERROR: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']
            )), 'application/json');
        }
    });

    global $thisstaff;
    
    if (!$thisstaff) {
        Http::response(403, 'Access Denied');
        return;
    }
    
    $ticket_id = $_POST['ticket_id'] ?? $_GET['ticket_id'] ?? null;
    
    if (!$ticket_id) {
        Http::response(200, json_encode(array('success' => false, 'error' => 'Ticket ID required')), 'application/json');
        return;
    }
    
    try {
        // Get plugin instance
        $plugin = null;
        $installed_plugins = PluginManager::allInstalled();
        
        foreach ($installed_plugins as $path => $info) {
            if (is_object($info)) {
                $manifest = isset($info->info) ? $info->info : array();
                if (isset($manifest['id']) && $manifest['id'] == 'osticket:ai-auto-dept-transfer') {
                    $plugin = $info;
                    break;
                }
            } elseif (is_array($info)) {
                if (isset($info['id']) && $info['id'] == 'osticket:ai-auto-dept-transfer') {
                    $plugin = PluginManager::getInstance($path);
                    break;
                }
            }
        }
        
        if (!$plugin) {
            $plugin = PluginManager::getInstance('plugins/ai-auto-dept-transfer');
        }
        
        if (!$plugin || !is_a($plugin, 'Plugin')) {
            throw new Exception('Plugin instance not found');
        }
        
        // Get active instance config
        $config = null;
        $instances = $plugin->getInstances();
        foreach ($instances as $instance) {
            if ($instance->isEnabled()) {
                $config = $instance->getConfig();
                break;
            }
        }
        
        if (!$config) {
            $config = $plugin->getConfig();
        }
        
        if (!class_exists('Ticket')) {
            require_once(INCLUDE_DIR . 'class.ticket.php');
        }
        
        // Load analyzer class
        if (!class_exists('AIAutoDeptTransferAnalyzer')) {
            require_once(dirname(__FILE__) . '/class.transfer-analyzer.php');
        }
        
        if (!class_exists('AIAutoDeptTransferAnalyzer')) {
            throw new Exception('Class TransferAnalyzer not found');
        }
        
        // Analyze ticket
        $analyzer = new AIAutoDeptTransferAnalyzer($config);
        $result = $analyzer->analyzeTicket($ticket_id);
        
        // If successful analysis, perform transfer
        if ($result['success']) {
            $ticket = Ticket::lookup($ticket_id);
            if ($ticket) {
                $transferred = $analyzer->transferTicket(
                    $ticket,
                    $result['dept_id'],
                    $result['reason']
                );
                
                if ($transferred) {
                    $result['transferred'] = true;
                    $result['message'] = 'Ticket successfully transferred to ' . $result['dept_name'];
                } else {
                    $result['transferred'] = false;
                    $result['message'] = 'Analysis successful but transfer not needed (already in target department)';
                }
            }
        } elseif (isset($result['no_match'])) {
            // Log note about no match
            $ticket = Ticket::lookup($ticket_id);
            if ($ticket) {
                $analyzer->logTransferFailure($ticket, $result['message']);
            }
        }
        
        Http::response(200, json_encode($result), 'application/json');
        
    } catch (Throwable $e) {
        Http::response(200, json_encode(array(
            'success' => false, 
            'error' => 'EXCEPTION: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        )), 'application/json');
    }
}

// --- КЛАСС ПЛАГИНА ---

class AIAutoDeptTransferPlugin extends Plugin {
    var $config_class = 'AIAutoDeptTransferConfig';
    
    function bootstrap() {
        // Register signal handlers
        Signal::connect('ticket.created', array($this, 'onTicketCreated'));
        Signal::connect('object.view', array($this, 'onObjectView'));
        Signal::connect('ajax.scp', array($this, 'registerAjax'));
        
        // Load config UI on admin pages
        $this->onAdminPage();
    }
    
    /**
     * Handle ticket.created signal for automatic processing
     */
    function onTicketCreated($ticket) {
        $config = $this->getConfig();
        
        // Check if auto-transfer is enabled
        if (!$config->get('auto_transfer')) {
            if ($config->get('enable_logging')) {
                error_log("Auto Dept Transfer - Auto-transfer disabled, skipping ticket #" . $ticket->getNumber());
            }
            return;
        }
        
        try {
            if ($config->get('enable_logging')) {
                error_log("Auto Dept Transfer - Processing new ticket #" . $ticket->getNumber());
            }
            
            // Load analyzer class
            if (!class_exists('AIAutoDeptTransferAnalyzer')) {
                require_once(dirname(__FILE__) . '/class.transfer-analyzer.php');
            }
            
            // Analyze ticket
            $analyzer = new AIAutoDeptTransferAnalyzer($config);
            $result = $analyzer->analyzeTicket($ticket->getId());
            
            if ($result['success']) {
                // Transfer ticket
                $transferred = $analyzer->transferTicket(
                    $ticket,
                    $result['dept_id'],
                    $result['reason']
                );
                
                if ($config->get('enable_logging')) {
                    if ($transferred) {
                        error_log("Auto Dept Transfer - Transferred ticket #" . $ticket->getNumber() . " to dept " . $result['dept_name']);
                    } else {
                        error_log("Auto Dept Transfer - Transfer not needed for ticket #" . $ticket->getNumber());
                    }
                }
            } elseif (isset($result['no_match'])) {
                // Log note that no match was found
                $analyzer->logTransferFailure($ticket, $result['message']);
                
                if ($config->get('enable_logging')) {
                    error_log("Auto Dept Transfer - No match found for ticket #" . $ticket->getNumber());
                }
            } else {
                // Log error
                if ($config->get('enable_logging')) {
                    error_log("Auto Dept Transfer - Error analyzing ticket #" . $ticket->getNumber() . ": " . ($result['error'] ?? 'Unknown error'));
                }
                
                $analyzer->logTransferFailure($ticket, $result['error'] ?? 'Analysis failed');
            }
            
        } catch (Exception $e) {
            if ($config->get('enable_logging')) {
                error_log("Auto Dept Transfer - Exception processing ticket: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Register AJAX endpoints
     */
    function registerAjax($dispatcher, $data=null) {
        $dispatcher->append(
            url_post('^/ai-auto-dept-transfer/analyze', 'ai_auto_dept_transfer_handle_analyze')
        );
    }
    
    /**
     * Inject assets when viewing a ticket
     */
    function onObjectView($object, $type=null) {
        if ($object && is_a($object, 'Ticket')) {
            $this->loadAssets($object);
        }
    }
    
    /**
     * Inject config UI script on plugin configuration page
     */
    function onAdminPage() {
        // Check if we're on plugin config page
        if (isset($_GET['id']) && strpos($_SERVER['REQUEST_URI'], 'plugins.php') !== false) {
            $this->loadConfigAssets();
        }
    }
    
    /**
     * Load CSS and JavaScript assets for ticket view
     */
    function loadAssets($object) {
        $config = $this->getConfig();
        $path = dirname(__FILE__);
        
        // Load CSS
        echo '<style type="text/css">';
        @readfile($path . '/css/auto-dept-transfer.css');
        echo '</style>';
        
        // Pass config to JavaScript
        echo '<script type="text/javascript">
            var AUTO_DEPT_TRANSFER_CONFIG = {
                ajax_url: "ajax.php/ai-auto-dept-transfer",
                ticket_id: ' . $object->getId() . ',
                enable_logging: ' . ($config->get('enable_logging') ? 'true' : 'false') . '
            };
        </script>';
        
        // Load JavaScript
        echo '<script type="text/javascript">';
        @readfile($path . '/js/auto-dept-transfer.js');
        echo '</script>';
    }
    
    /**
     * Load config UI assets
     */
    function loadConfigAssets() {
        $path = dirname(__FILE__);
        
        echo '<script type="text/javascript">';
        @readfile($path . '/js/config-ui.js');
        echo '</script>';
    }
}

