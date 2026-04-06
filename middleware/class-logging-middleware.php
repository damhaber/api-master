<?php
/**
 * API Master - Logging Middleware
 * 
 * @package APIMaster
 * @subpackage Middleware
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class APIMaster_Logging_Middleware
 * 
 * İstek/yanıt loglama middleware'i
 */
class APIMaster_Logging_Middleware {
    
    /**
     * @var APIMaster_Security_Audit Audit instance
     */
    private $audit;
    
    /**
     * @var array Request start times
     */
    private $startTimes = [];
    
    /**
     * @var string Current request ID
     */
    private $requestId;
    
    /**
     * Constructor
     */
    public function __construct() {
        if (class_exists('APIMaster_Security_Audit')) {
            $this->audit = new APIMaster_Security_Audit();
        }
        $this->requestId = $this->generateRequestId();
    }
    
    /**
     * Generate unique request ID
     * 
     * @return string
     */
    private function generateRequestId() {
        return uniqid('req_', true);
    }
    
    /**
     * Log request başlangıcı
     * 
     * @param string $endpoint
     * @param array $data
     * @param array $headers
     * @return string Request ID
     */
    public function logRequest($endpoint, $data = [], $headers = []) {
        $this->startTimes[$this->requestId] = microtime(true);
        
        $logData = [
            'request_id' => $this->requestId,
            'endpoint' => $endpoint,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'ip' => $this->getClientIp(),
            'user_agent' => $headers['User-Agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'data_size' => strlen(json_encode($data)),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Hassas verileri maskele
        $maskedData = $this->maskSensitiveData($data);
        $logData['data'] = $maskedData;
        
        if ($this->audit) {
            $this->audit->info('API Request: ' . $endpoint, $logData);
        }
        
        // Request log dosyasına yaz
        $this->writeRequestLog($logData);
        
        return $this->requestId;
    }
    
    /**
     * Log response
     * 
     * @param string $requestId
     * @param mixed $response
     * @param int $statusCode
     * @param array $additionalData
     */
    public function logResponse($requestId, $response, $statusCode, $additionalData = []) {
        $duration = isset($this->startTimes[$requestId]) 
            ? (microtime(true) - $this->startTimes[$requestId]) * 1000 
            : 0;
        
        $logData = array_merge([
            'request_id' => $requestId,
            'status_code' => $statusCode,
            'duration_ms' => round($duration, 2),
            'response_size' => strlen(is_string($response) ? $response : json_encode($response)),
            'timestamp' => date('Y-m-d H:i:s')
        ], $additionalData);
        
        // Response data'sını kısalt (çok uzunsa)
        if (is_array($response) || is_object($response)) {
            $responseForLog = $this->truncateResponse($response);
            $logData['response_preview'] = $responseForLog;
        }
        
        $logLevel = $statusCode >= 400 ? 'error' : 'info';
        
        if ($this->audit) {
            $this->audit->$logLevel('API Response: ' . $statusCode, $logData);
            
            // Performance log
            if ($duration > 1000) { // 1 saniyeden uzun süren istekler
                $this->audit->performance('slow_request', $duration, [
                    'request_id' => $requestId,
                    'status_code' => $statusCode
                ]);
            }
        }
        
        // Response log dosyasına yaz
        $this->writeResponseLog($logData);
        
        // Clean up
        unset($this->startTimes[$requestId]);
    }
    
    /**
     * Log error
     * 
     * @param string $requestId
     * @param string $error
     * @param array $context
     */
    public function logError($requestId, $error, $context = []) {
        $logData = array_merge([
            'request_id' => $requestId,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s')
        ], $context);
        
        if ($this->audit) {
            $this->audit->error('API Error: ' . $error, $logData);
        }
        
        $this->writeErrorLog($logData);
    }
    
    /**
     * Hassas verileri maskele
     * 
     * @param array $data
     * @return array
     */
    private function maskSensitiveData($data) {
        $sensitiveFields = ['password', 'api_key', 'secret', 'token', 'authorization', 'credit_card'];
        
        array_walk_recursive($data, function(&$value, $key) use ($sensitiveFields) {
            foreach ($sensitiveFields as $field) {
                if (stripos($key, $field) !== false) {
                    $value = '***MASKED***';
                    break;
                }
            }
        });
        
        return $data;
    }
    
    /**
     * Response'u kısalt
     * 
     * @param mixed $response
     * @param int $maxLength
     * @return mixed
     */
    private function truncateResponse($response, $maxLength = 500) {
        $json = json_encode($response);
        if (strlen($json) > $maxLength) {
            return substr($json, 0, $maxLength) . '... (truncated)';
        }
        return $response;
    }
    
    /**
     * Request log dosyasına yaz
     * 
     * @param array $logData
     */
    private function writeRequestLog($logData) {
        $logDir = dirname(dirname(__FILE__)) . '/logs/requests/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . 'requests-' . date('Y-m-d') . '.log';
        file_put_contents($logFile, json_encode($logData) . PHP_EOL, FILE_APPEND);
    }
    
    /**
     * Response log dosyasına yaz
     * 
     * @param array $logData
     */
    private function writeResponseLog($logData) {
        $logDir = dirname(dirname(__FILE__)) . '/logs/responses/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . 'responses-' . date('Y-m-d') . '.log';
        file_put_contents($logFile, json_encode($logData) . PHP_EOL, FILE_APPEND);
    }
    
    /**
     * Error log dosyasına yaz
     * 
     * @param array $logData
     */
    private function writeErrorLog($logData) {
        $logDir = dirname(dirname(__FILE__)) . '/logs/errors/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . 'errors-' . date('Y-m-d') . '.log';
        file_put_contents($logFile, json_encode($logData) . PHP_EOL, FILE_APPEND);
    }
    
    /**
     * İstemci IP'sini al
     * 
     * @return string
     */
    private function getClientIp() {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (isset($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * İstatistikleri getir
     * 
     * @param string $date
     * @return array
     */
    public function getStats($date = null) {
        $date = $date ?? date('Y-m-d');
        $logDir = dirname(dirname(__FILE__)) . '/logs/requests/';
        $logFile = $logDir . 'requests-' . $date . '.log';
        
        if (!file_exists($logFile)) {
            return ['total' => 0, 'by_endpoint' => [], 'by_status' => []];
        }
        
        $stats = [
            'total' => 0,
            'by_endpoint' => [],
            'by_status' => [],
            'avg_duration' => 0,
            'total_duration' => 0
        ];
        
        $lines = file($logFile);
        $durations = [];
        
        foreach ($lines as $line) {
            $log = json_decode(trim($line), true);
            if (!$log) continue;
            
            $stats['total']++;
            
            $endpoint = $log['endpoint'] ?? 'unknown';
            if (!isset($stats['by_endpoint'][$endpoint])) {
                $stats['by_endpoint'][$endpoint] = 0;
            }
            $stats['by_endpoint'][$endpoint]++;
            
            if (isset($log['duration_ms'])) {
                $durations[] = $log['duration_ms'];
            }
        }
        
        if (!empty($durations)) {
            $stats['avg_duration'] = round(array_sum($durations) / count($durations), 2);
            $stats['total_duration'] = round(array_sum($durations), 2);
        }
        
        return $stats;
    }
    
    /**
     * Log dosyalarını temizle
     * 
     * @param int $days
     * @return int
     */
    public function cleanOldLogs($days = 30) {
        $deleted = 0;
        $cutoff = strtotime("-$days days");
        
        $logDirs = ['requests', 'responses', 'errors'];
        
        foreach ($logDirs as $dir) {
            $path = dirname(dirname(__FILE__)) . '/logs/' . $dir . '/';
            if (!is_dir($path)) continue;
            
            $files = glob($path . '*.log');
            foreach ($files as $file) {
                if (filemtime($file) < $cutoff) {
                    if (unlink($file)) {
                        $deleted++;
                    }
                }
            }
        }
        
        return $deleted;
    }
}