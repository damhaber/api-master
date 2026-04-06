<?php
/**
 * export-logs.php - AJAX Endpoint for Masal Panel
 * Export system logs as CSV
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);

class APIMaster_ExportLogs
{
    private $moduleDir;
    private $logDir;
    
    public function __construct()
    {
        $this->moduleDir = defined('API_MASTER_MODULE_DIR') 
            ? API_MASTER_MODULE_DIR 
            : dirname(__DIR__);
        
        $this->logDir = $this->moduleDir . '/logs';
    }
    
    public function execute()
    {
        try {
            $type = isset($_GET['type']) ? $_GET['type'] : 'api-master';
            $format = isset($_GET['format']) ? $_GET['format'] : 'csv';
            
            $logFile = $this->logDir . '/' . $type . '.log';
            
            if (!file_exists($logFile)) {
                $this->sendError('Log file not found: ' . $type);
            }
            
            if ($format === 'csv') {
                $this->exportCSV($logFile, $type);
            } elseif ($format === 'json') {
                $this->exportJSON($logFile, $type);
            } else {
                $this->sendError('Unsupported format: ' . $format);
            }
            
        } catch (Exception $e) {
            $this->sendError($e->getMessage());
        }
    }
    
    private function exportCSV($logFile, $type)
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $type . '-logs-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV başlığı
        fputcsv($output, ['Timestamp', 'Level', 'Message', 'Context', 'File', 'Line']);
        
        $lines = file($logFile);
        $lastLines = array_slice($lines, -10000); // Son 10,000 satır
        
        foreach ($lastLines as $line) {
            $parsed = $this->parseLogLine($line);
            fputcsv($output, $parsed);
        }
        
        fclose($output);
        exit;
    }
    
    private function exportJSON($logFile, $type)
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $type . '-logs-' . date('Y-m-d') . '.json"');
        
        $lines = file($logFile);
        $lastLines = array_slice($lines, -10000);
        
        $logs = [];
        foreach ($lastLines as $line) {
            $parsed = $this->parseLogLine($line);
            if ($parsed['timestamp'] !== '') {
                $logs[] = $parsed;
            }
        }
        
        echo json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    private function parseLogLine($line)
    {
        // Format: [2024-01-01 12:00:00] [ERROR] Message {"context":""} in file.php:123
        if (preg_match('/\[(.*?)\] \[(.*?)\] (.*?)(?:\s+(.*?))?(?:\s+in\s+(.*?):(\d+))?$/', trim($line), $matches)) {
            return [
                'timestamp' => $matches[1],
                'level' => $matches[2],
                'message' => trim($matches[3]),
                'context' => isset($matches[4]) ? $matches[4] : '',
                'file' => isset($matches[5]) ? $matches[5] : '',
                'line' => isset($matches[6]) ? $matches[6] : ''
            ];
        }
        
        // Fallback for simple format
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => 'INFO',
            'message' => trim($line),
            'context' => '',
            'file' => '',
            'line' => ''
        ];
    }
    
    private function sendError($message)
    {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$instance = new APIMaster_ExportLogs();
$instance->execute();