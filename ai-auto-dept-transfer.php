<?php

require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.signal.php');
require_once(INCLUDE_DIR . 'class.dispatcher.php');
require_once('class.ai-auto-dept-transfer-plugin.php');
require_once('class.transfer-analyzer.php');
require_once('config.php');

/**
 * @return AIAutoDeptTransferPlugin
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
        Http::response(400, json_encode(array('success' => false, 'error' => 'Ticket ID required')), 'application/json');
        return;
    }
    
    try {
        $ticket = Ticket::lookup($ticket_id);
        if (!$ticket) {
            Http::response(400, json_encode(array(
                'success' => false,
                'error' => 'Ticket not found'
            )), 'application/json');
        }

        if (!class_exists('AIAutoDeptTransferAnalyzer')) {
            throw new Exception('Class TransferAnalyzer not found');
        }

        //Get active instance config
        $result = get_plugin_ai_auto_dept_transfer()->tryTransferTicket($ticket);
        
        Http::response(200, json_encode(array_diff_key($result, array_flip(['analyzed_files', 'ignored_files']))), 'application/json');
        
    } catch (Throwable $e) {
        Http::response(200, json_encode(array(
            'success' => false, 
            'error' => 'EXCEPTION: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        )), 'application/json');
    }
}
