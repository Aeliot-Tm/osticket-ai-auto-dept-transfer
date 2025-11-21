<?php

require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.signal.php');
require_once(INCLUDE_DIR . 'class.dispatcher.php');
require_once('class.ai-auto-dept-transfer-plugin.php');
require_once('class.transfer-analyzer.php');
require_once('config.php');

/**
 * @return AIAutoDeptTransferConfig
 */
function get_plugin_ai_auto_dept_transfer() {
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
        throw new DomainException('Plugin instance not found');
    }

    return $plugin;
}

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
        if (!class_exists('AIAutoDeptTransferAnalyzer')) {
            throw new Exception('Class TransferAnalyzer not found');
        }

        //Get active instance config
        $config = get_plugin_ai_auto_dept_transfer()->getConfig();

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
