<?php
/**
 * get-logs.php - AJAX Endpoint for Masal Panel
 * Get system logs with filtering and pagination
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);

class APIMaster_GetLogs
{
    private $moduleDir;
    private $logDir;
    
    private $validLogTypes = ['api-master', 'error', 'api-tests', 'all'];
    private $validLevels = ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'];
    private $maxLimit = 5000;
    private $defaultLimit = 500;
    
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
            $format = isset($_GET['format']) ? $this->sanitizeFormat($_GET['format']) : 'json';
            $logType = isset($_GET['type']) ? $this->sanitizeLogType($_GET['type']) : 'api-master';
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : $this->defaultLimit;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $level = isset($_GET['level']) ? $this->sanitizeLevel($_GET['level']) : null;
            $search = isset($_GET['search']) ? trim($_GET['search']) : null;
            $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
            $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;
            
            // Limit kontrolü
            if ($limit > $this->maxLimit) {
                $limit = $this->maxLimit;
            }
            if ($limit < 1) {
                $limit = 1;
            }
            
            // Logları topla
            $allLogs = $this->collectLogs($logType);
            
            // Filtrele
            $filteredLogs = $this->filterLogs($allLogs, $level, $search, $dateFrom, $dateTo);
            
            // Toplam satır sayısı
            $totalLines = count($filteredLogs);
            
            // Pagination
            $paginatedLogs = array_slice($filteredLogs, $offset, $limit);
            
            // Logları parse et
            $logsData = [];
            foreach ($paginatedLogs as $line) {
                $parsed = $this->parseLogLine($line);
                if ($parsed !== null) {
                    $logsData[] = $parsed;
                }
            }
            
            // Format'a göre yanıt ver
            if ($format === 'text') {
                $this->sendTextResponse($logsData);
            } else {
                $this->sendJsonResponse($logsData, $totalLines, $limit, $offset, $logType, [
                    'level' => $level,
                    'search' => $search,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo
                ]);
            }
            
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            $this->sendErrorResponse($e->getMessage());
        }
    }
    
    private function collectLogs($logType)
    {
        if ($logType === 'all') {
            $allLogs = [];
            $logFiles = ['api-master.log', 'error.log', 'api-tests.log'];
            
            foreach ($logFiles as $file) {
                $filePath = $this->logDir . '/' . $file;
                $logs = $this->readLogFile($filePath);
                
                foreach ($logs as $line) {
                    $allLogs[] = [
                        'line' => $line,
                        'source' => basename($file, '.log'),
                        'timestamp' => $this->extractTimestamp($line)
                    ];
                }
            }
            
            // Zamana göre sırala (en yeni önce)
            usort($allLogs, function($a, $b) {
                return strcmp($b['timestamp'], $a['timestamp']);
            });
            
            // Sadece satır içeriğini döndür
            return array_map(function($item) {
                return $item['line'];
            }, $allLogs);
        }
        
        // Tek dosya
        $logFile = $this->logDir . '/' . $logType . '.log';
        return $this->readLogFile($logFile);
    }
    
    private function readLogFile($filePath)
    {
        if (!file_exists($filePath)) {
            return [];
        }
        
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return [];
        }
        
        // Satırlara ayır
        $lines = explode("\n", $content);
        
        // Boş satırları temizle
        $lines = array_filter($lines, function($line) {
            return trim($line) !== '';
        });
        
        return array_values($lines);
    }
    
    private function extractTimestamp($line)
    {
        // JSON formatı: {"timestamp":"2024-01-01 12:00:00",...}
        if (strpos($line, '"timestamp"') !== false) {
            if (preg_match('/"timestamp":"([^"]+)"/', $line, $matches)) {
                return $matches[1];
            }
        }
        
        // Text formatı: [2024-01-01 12:00:00] veya [2024-01-01 12:00:00] [LEVEL]
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            return $matches[1];
        }
        
        return '0000-00-00 00:00:00';
    }
    
    private function parseLogLine($line)
    {
        // JSON formatı
        if (strpos($line, '{') === 0) {
            $data = json_decode($line, true);
            if ($data && isset($data['timestamp'])) {
                return [
                    'timestamp' => $data['timestamp'],
                    'level' => isset($data['level']) ? strtoupper($data['level']) : 'INFO',
                    'message' => $data['message'] ?? $line,
                    'source' => $data['source'] ?? null,
                    'data' => $data['data'] ?? null,
                    'raw' => $line
                ];
            }
        }
        
        // Format: [LEVEL] [timestamp] - message
        if (preg_match('/^\[([A-Z]+)\]\s+\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+-\s+(.*)$/', $line, $matches)) {
            return [
                'timestamp' => $matches[2],
                'level' => $matches[1],
                'message' => $matches[3],
                'source' => null,
                'data' => null,
                'raw' => $line
            ];
        }
        
        // Format: [timestamp] [LEVEL] - message
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+\[([A-Z]+)\]\s+-\s+(.*)$/', $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'level' => $matches[2],
                'message' => $matches[3],
                'source' => null,
                'data' => null,
                'raw' => $line
            ];
        }
        
        // Format: [timestamp] - message
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+-\s+(.*)$/', $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'level' => 'INFO',
                'message' => $matches[2],
                'source' => null,
                'data' => null,
                'raw' => $line
            ];
        }
        
        // Parse edilemeyen satır
        return [
            'timestamp' => $this->extractTimestamp($line),
            'level' => 'INFO',
            'message' => $line,
            'source' => null,
            'data' => null,
            'raw' => $line
        ];
    }
    
    private function filterLogs($logs, $level, $search, $dateFrom, $dateTo)
    {
        if (empty($logs)) {
            return [];
        }
        
        $filtered = [];
        
        foreach ($logs as $line) {
            $parsed = $this->parseLogLine($line);
            
            // Seviye filtresi
            if ($level !== null && strtoupper($parsed['level']) !== strtoupper($level)) {
                continue;
            }
            
            // Arama filtresi (case-insensitive)
            if ($search !== null && $search !== '' && stripos($line, $search) === false) {
                continue;
            }
            
            // Tarih filtresi (başlangıç)
            if ($dateFrom !== null && $dateFrom !== '' && $parsed['timestamp'] < $dateFrom) {
                continue;
            }
            
            // Tarih filtresi (bitiş)
            if ($dateTo !== null && $dateTo !== '') {
                $endDate = $dateTo . ' 23:59:59';
                if ($parsed['timestamp'] > $endDate) {
                    continue;
                }
            }
            
            $filtered[] = $line;
        }
        
        return $filtered;
    }
    
    private function sanitizeFormat($format)
    {
        $format = strtolower(trim($format));
        return in_array($format, ['json', 'text']) ? $format : 'json';
    }
    
    private function sanitizeLogType($type)
    {
        // Sadece alfanumeric ve tire
        $type = preg_replace('/[^a-zA-Z0-9\-]/', '', strtolower(trim($type)));
        
        if (!in_array($type, $this->validLogTypes)) {
            return 'api-master';
        }
        
        return $type;
    }
    
    private function sanitizeLevel($level)
    {
        $level = strtoupper(trim($level));
        
        if (!in_array($level, $this->validLevels)) {
            return null;
        }
        
        return $level;
    }
    
    private function sendTextResponse($logs)
    {
        header('Content-Type: text/plain; charset=utf-8');
        
        if (empty($logs)) {
            echo '[' . date('Y-m-d H:i:s') . '] [INFO] - No log records found.' . PHP_EOL;
        } else {
            foreach ($logs as $log) {
                echo $log['raw'] . PHP_EOL;
            }
        }
        
        exit;
    }
    
    private function sendJsonResponse($logs, $total, $limit, $offset, $logType, $filters)
    {
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => true,
            'data' => [
                'logs' => $logs,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total,
                'log_type' => $logType,
                'filters' => $filters
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        exit;
    }
    
    private function sendErrorResponse($message)
    {
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        exit;
    }
    
    private function logError($message)
    {
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
        
        $logEntry = '[' . date('Y-m-d H:i:s') . '] [ERROR] [get-logs] ' . $message . PHP_EOL;
        @file_put_contents($this->logDir . '/api-master.log', $logEntry, FILE_APPEND | LOCK_EX);
    }
}

$instance = new APIMaster_GetLogs();
$instance->execute();