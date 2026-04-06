<?php
if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_Logger
{
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';
    
    private $levelWeights = [
        self::EMERGENCY => 800,
        self::ALERT => 700,
        self::CRITICAL => 600,
        self::ERROR => 500,
        self::WARNING => 400,
        self::NOTICE => 300,
        self::INFO => 200,
        self::DEBUG => 100
    ];
    
    private $logFile;
    private $minLevel;
    private $logDir;
    private $settingsFile;

    public function __construct()
    {
        $moduleDir = dirname(__DIR__, 1);
        $this->logDir = $moduleDir . '/logs/';
        $this->logFile = $this->logDir . 'api-master.log';
        $this->settingsFile = $moduleDir . '/config/settings.json';
        
        $this->loadConfig();
        $this->init();
    }

    private function loadConfig()
    {
        $this->minLevel = self::INFO;
        
        if (file_exists($this->settingsFile)) {
            $content = file_get_contents($this->settingsFile);
            $config = json_decode($content, true);
            
            if (is_array($config) && isset($config['log_level'])) {
                $level = $config['log_level'];
                if (isset($this->levelWeights[$level])) {
                    $this->minLevel = $level;
                }
            }
        }
    }

    private function init()
    {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
            chmod($this->logFile, 0644);
        }
        
        set_error_handler([$this, 'errorHandler']);
        register_shutdown_function([$this, 'shutdownHandler']);
    }

    private function shouldLog($level)
    {
        $currentWeight = isset($this->levelWeights[$level]) ? $this->levelWeights[$level] : 200;
        $minWeight = isset($this->levelWeights[$this->minLevel]) ? $this->levelWeights[$this->minLevel] : 200;
        
        return $currentWeight >= $minWeight;
    }

    public function log($level, $message, $context = [])
    {
        if (!$this->shouldLog($level)) {
            return false;
        }
        
        $logEntry = $this->formatLogEntry($level, $message, $context);
        
        return $this->writeToFile($logEntry);
    }

    public function emergency($message, $context = []) { $this->log(self::EMERGENCY, $message, $context); }
    public function alert($message, $context = []) { $this->log(self::ALERT, $message, $context); }
    public function critical($message, $context = []) { $this->log(self::CRITICAL, $message, $context); }
    public function error($message, $context = []) { $this->log(self::ERROR, $message, $context); }
    public function warning($message, $context = []) { $this->log(self::WARNING, $message, $context); }
    public function notice($message, $context = []) { $this->log(self::NOTICE, $message, $context); }
    public function info($message, $context = []) { $this->log(self::INFO, $message, $context); }
    public function debug($message, $context = []) { $this->log(self::DEBUG, $message, $context); }

    public function logApiCall($provider, $endpoint, $request, $response, $duration, $success = true)
    {
        $level = $success ? self::INFO : self::ERROR;
        
        $context = [
            'provider' => $provider,
            'endpoint' => $endpoint,
            'duration' => $duration,
            'request_size' => strlen(json_encode($request)),
            'response_size' => strlen(json_encode($response))
        ];
        
        $message = sprintf(
            'API Call: %s/%s - %s (%.2f ms)',
            $provider,
            $endpoint,
            $success ? 'SUCCESS' : 'FAILED',
            $duration
        );
        
        $this->log($level, $message, $context);
        
        if ($this->shouldLog(self::DEBUG)) {
            $this->debug('API Call Details', [
                'request' => $request,
                'response' => $response
            ]);
        }
    }

    private function formatLogEntry($level, $message, $context)
    {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $this->getIp();
        
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        
        return sprintf(
            "[%s] [%s] [IP:%s] %s%s\n",
            $timestamp,
            strtoupper($level),
            $ip,
            $message,
            $contextStr
        );
    }

    private function writeToFile($logEntry)
    {
        if (!is_writable($this->logFile)) {
            return false;
        }
        
        $result = file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        return $result !== false;
    }

    public function getLogs($limit = 100, $level = null)
    {
        $logs = [];
        
        if (!file_exists($this->logFile)) {
            return $logs;
        }
        
        $lines = file($this->logFile);
        $lines = array_reverse($lines);
        
        $count = 0;
        foreach ($lines as $line) {
            if ($count >= $limit) {
                break;
            }
            
            if ($level) {
                $upperLevel = strtoupper($level);
                if (stripos($line, "[{$level}]") === false && stripos($line, "[{$upperLevel}]") === false) {
                    continue;
                }
            }
            
            $logs[] = $this->parseLogLine($line);
            $count++;
        }
        
        return $logs;
    }

    private function parseLogLine($line)
    {
        $log = [];
        
        if (preg_match('/\[(.*?)\]/', $line, $matches)) {
            $log['timestamp'] = $matches[1];
            $line = str_replace($matches[0], '', $line);
        }
        
        if (preg_match('/\[(.*?)\]/', $line, $matches)) {
            $log['level'] = $matches[1];
            $line = str_replace($matches[0], '', $line);
        }
        
        if (preg_match('/\[IP:(.*?)\]/', $line, $matches)) {
            $log['ip'] = $matches[1];
            $line = str_replace($matches[0], '', $line);
        }
        
        $line = trim($line);
        if (preg_match('/^(.*?)(\s+\{.*\})?$/', $line, $matches)) {
            $log['message'] = $matches[1];
            if (isset($matches[2])) {
                $context = json_decode($matches[2], true);
                if (is_array($context)) {
                    $log['context'] = $context;
                }
            }
        }
        
        return $log;
    }

    public function clearLogs()
    {
        if (file_exists($this->logFile)) {
            return file_put_contents($this->logFile, '', LOCK_EX) !== false;
        }
        
        return false;
    }

    public function getStats()
    {
        $stats = [
            'total_lines' => 0,
            'by_level' => [],
            'size' => 0
        ];
        
        if (file_exists($this->logFile)) {
            $stats['size'] = filesize($this->logFile);
            $lines = file($this->logFile);
            $stats['total_lines'] = count($lines);
            
            foreach ($lines as $line) {
                if (preg_match('/\[(ERROR|WARNING|NOTICE|INFO|DEBUG|CRITICAL|ALERT|EMERGENCY)\]/', $line, $matches)) {
                    $level = strtolower($matches[1]);
                    if (!isset($stats['by_level'][$level])) {
                        $stats['by_level'][$level] = 0;
                    }
                    $stats['by_level'][$level]++;
                }
            }
        }
        
        return $stats;
    }

    public function errorHandler($errno, $errstr, $errfile, $errline)
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $level = $this->getErrorLevel($errno);
        $message = sprintf('%s in %s on line %d', $errstr, $errfile, $errline);
        
        $this->log($level, $message, [
            'errno' => $errno,
            'errfile' => $errfile,
            'errline' => $errline
        ]);
        
        return true;
    }

    public function shutdownHandler()
    {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->critical('Fatal error: ' . $error['message'], [
                'file' => $error['file'],
                'line' => $error['line']
            ]);
        }
    }

    private function getErrorLevel($errno)
    {
        switch ($errno) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return self::ERROR;
            case E_WARNING:
            case E_USER_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
                return self::WARNING;
            default:
                return self::NOTICE;
        }
    }

    private function getIp()
    {
        $headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}