<?php
/**
 * API Master - Log Rotator
 * 
 * @package APIMaster
 * @subpackage Includes
 * @since 1.0.0
 * 
 * IMPORTANT: No WordPress dependencies! Pure file-based log rotation.
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_Log_Rotator {
    
    /**
     * @var string Log directory
     */
    private $log_dir;
    
    /**
     * @var int Maximum log file size in bytes (10MB default)
     */
    private $max_file_size = 10485760;
    
    /**
     * @var int Maximum number of rotated files to keep
     */
    private $max_rotated_files = 30;
    
    /**
     * @var array Log levels
     */
    private $log_levels = [
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7
    ];
    
    /**
     * @var int Current log level threshold
     */
    private $log_level = 6; // info
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->log_dir = dirname(dirname(__FILE__)) . '/logs/';
        $this->ensureLogDirectory();
        $this->loadConfig();
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory(): void {
        if (!is_dir($this->log_dir)) {
            mkdir($this->log_dir, 0755, true);
        }
        
        // Subdirectories for different log types
        $subdirs = ['api', 'errors', 'security', 'performance', 'cron'];
        foreach ($subdirs as $subdir) {
            $path = $this->log_dir . $subdir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }
    
    /**
     * Load configuration
     */
    private function loadConfig(): void {
        $config_file = dirname(dirname(__FILE__)) . '/config/settings.json';
        
        if (file_exists($config_file)) {
            $config = json_decode(file_get_contents($config_file), true);
            $this->max_file_size = $config['max_log_size'] ?? 10485760;
            $this->max_rotated_files = $config['max_rotated_logs'] ?? 30;
            
            $level = $config['log_level'] ?? 'info';
            $this->log_level = $this->log_levels[$level] ?? 6;
        }
    }
    
    /**
     * Write log entry
     * 
     * @param string $level
     * @param string $message
     * @param array $context
     * @param string $type
     * @return bool
     */
    public function write(string $level, string $message, array $context = [], string $type = 'api'): bool {
        // Check log level
        $level_value = $this->log_levels[$level] ?? 6;
        if ($level_value > $this->log_level) {
            return false;
        }
        
        $log_file = $this->getLogFile($type);
        
        // Check if rotation needed
        if ($this->needsRotation($log_file)) {
            $this->rotate($log_file, $type);
        }
        
        $entry = $this->formatLogEntry($level, $message, $context);
        
        return file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX) !== false;
    }
    
    /**
     * Get log file path
     * 
     * @param string $type
     * @return string
     */
    private function getLogFile(string $type): string {
        $date = date('Y-m-d');
        $subdir = $this->getSubdirByType($type);
        return $this->log_dir . $subdir . '/' . $type . '-' . $date . '.log';
    }
    
    /**
     * Get subdirectory by log type
     * 
     * @param string $type
     * @return string
     */
    private function getSubdirByType(string $type): string {
        $type_map = [
            'api' => 'api',
            'error' => 'errors',
            'security' => 'security',
            'performance' => 'performance',
            'cron' => 'cron',
            'default' => 'api'
        ];
        
        return $type_map[$type] ?? $type_map['default'];
    }
    
    /**
     * Check if log file needs rotation
     * 
     * @param string $log_file
     * @return bool
     */
    private function needsRotation(string $log_file): bool {
        if (!file_exists($log_file)) {
            return false;
        }
        
        return filesize($log_file) >= $this->max_file_size;
    }
    
    /**
     * Rotate log file
     * 
     * @param string $log_file
     * @param string $type
     * @return bool
     */
    private function rotate(string $log_file, string $type): bool {
        if (!file_exists($log_file)) {
            return false;
        }
        
        // Generate rotation filename with timestamp
        $timestamp = date('Y-m-d_H-i-s');
        $rotated_file = $this->log_dir . $this->getSubdirByType($type) . '/' . $type . '-' . $timestamp . '.log';
        
        // Rotate the file
        if (!rename($log_file, $rotated_file)) {
            return false;
        }
        
        // Compress rotated file
        $this->compressLog($rotated_file);
        
        // Clean old rotated files
        $this->cleanOldRotated($type);
        
        return true;
    }
    
    /**
     * Compress log file
     * 
     * @param string $file
     * @return bool
     */
    private function compressLog(string $file): bool {
        if (!file_exists($file) || !function_exists('gzopen')) {
            return false;
        }
        
        $gz_file = $file . '.gz';
        $fp = gzopen($gz_file, 'w9');
        
        if (!$fp) {
            return false;
        }
        
        $content = file_get_contents($file);
        gzwrite($fp, $content);
        gzclose($fp);
        
        // Remove original file after compression
        unlink($file);
        
        return true;
    }
    
    /**
     * Clean old rotated log files
     * 
     * @param string $type
     * @return int Number of deleted files
     */
    private function cleanOldRotated(string $type): int {
        $deleted = 0;
        $pattern = $this->log_dir . $this->getSubdirByType($type) . '/' . $type . '-*.log*';
        $files = glob($pattern);
        
        // Sort by modification time (oldest first)
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        // Keep only max_rotated_files
        $to_delete = count($files) - $this->max_rotated_files;
        
        if ($to_delete > 0) {
            $delete_files = array_slice($files, 0, $to_delete);
            foreach ($delete_files as $file) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
    
    /**
     * Format log entry
     * 
     * @param string $level
     * @param string $message
     * @param array $context
     * @return string
     */
    private function formatLogEntry(string $level, string $message, array $context = []): string {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $this->getClientIp();
        $pid = getmypid();
        $request_id = $this->getRequestId();
        
        $context_str = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        
        return sprintf(
            "[%s] [%s] [PID:%d] [IP:%s] [REQ:%s] %s%s\n",
            $timestamp,
            strtoupper($level),
            $pid,
            $ip,
            $request_id,
            $message,
            $context_str
        );
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    private function getClientIp(): string {
        $headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
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
     * Get or generate request ID
     * 
     * @return string
     */
    private function getRequestId(): string {
        if (defined('API_MASTER_REQUEST_ID')) {
            return API_MASTER_REQUEST_ID;
        }
        
        return substr(uniqid(), -8);
    }
    
    /**
     * Read log file
     * 
     * @param string $type
     * @param string $date
     * @param int $limit
     * @param string|null $level
     * @return array
     */
    public function read(string $type, string $date = null, int $limit = 100, ?string $level = null): array {
        $date = $date ?? date('Y-m-d');
        $log_file = $this->log_dir . $this->getSubdirByType($type) . '/' . $type . '-' . $date . '.log';
        
        if (!file_exists($log_file)) {
            return [];
        }
        
        $lines = file($log_file);
        $lines = array_reverse($lines); // Newest first
        $logs = [];
        
        foreach ($lines as $line) {
            if (count($logs) >= $limit) {
                break;
            }
            
            if ($level && stripos($line, "[{$level}]") === false && stripos($line, '[' . strtoupper($level) . ']') === false) {
                continue;
            }
            
            $logs[] = $this->parseLogLine($line);
        }
        
        return $logs;
    }
    
    /**
     * Parse log line
     * 
     * @param string $line
     * @return array
     */
    private function parseLogLine(string $line): array {
        $log = [];
        
        // Extract timestamp
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            $log['timestamp'] = $matches[1];
            $line = str_replace($matches[0], '', $line);
        }
        
        // Extract level
        if (preg_match('/\[([A-Z]+)\]/', $line, $matches)) {
            $log['level'] = strtolower($matches[1]);
            $line = str_replace($matches[0], '', $line);
        }
        
        // Extract PID
        if (preg_match('/\[PID:(\d+)\]/', $line, $matches)) {
            $log['pid'] = (int)$matches[1];
            $line = str_replace($matches[0], '', $line);
        }
        
        // Extract IP
        if (preg_match('/\[IP:([0-9.]+)\]/', $line, $matches)) {
            $log['ip'] = $matches[1];
            $line = str_replace($matches[0], '', $line);
        }
        
        // Extract Request ID
        if (preg_match('/\[REQ:([a-z0-9]+)\]/', $line, $matches)) {
            $log['request_id'] = $matches[1];
            $line = str_replace($matches[0], '', $line);
        }
        
        // Extract message and context
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
    
    /**
     * Search logs
     * 
     * @param string $type
     * @param string $search
     * @param string $start_date
     * @param string $end_date
     * @param int $limit
     * @return array
     */
    public function search(string $type, string $search, string $start_date = null, string $end_date = null, int $limit = 100): array {
        $results = [];
        $start_date = $start_date ?? date('Y-m-d', strtotime('-7 days'));
        $end_date = $end_date ?? date('Y-m-d');
        
        $current = strtotime($start_date);
        $end = strtotime($end_date);
        
        while ($current <= $end) {
            $date = date('Y-m-d', $current);
            $logs = $this->read($type, $date, 1000);
            
            foreach ($logs as $log) {
                if (count($results) >= $limit) {
                    break 2;
                }
                
                if (stripos($log['message'] ?? '', $search) !== false ||
                    stripos(json_encode($log['context'] ?? ''), $search) !== false) {
                    $results[] = $log;
                }
            }
            
            $current = strtotime('+1 day', $current);
        }
        
        return $results;
    }
    
    /**
     * Get log statistics
     * 
     * @param string $type
     * @param string $date
     * @return array
     */
    public function getStats(string $type, string $date = null): array {
        $date = $date ?? date('Y-m-d');
        $log_file = $this->log_dir . $this->getSubdirByType($type) . '/' . $type . '-' . $date . '.log';
        
        $stats = [
            'type' => $type,
            'date' => $date,
            'exists' => file_exists($log_file),
            'size' => 0,
            'size_human' => '0 B',
            'line_count' => 0,
            'by_level' => []
        ];
        
        if (file_exists($log_file)) {
            $stats['size'] = filesize($log_file);
            $stats['size_human'] = $this->formatSize($stats['size']);
            
            $lines = file($log_file);
            $stats['line_count'] = count($lines);
            
            foreach ($lines as $line) {
                if (preg_match('/\[([A-Z]+)\]/', $line, $matches)) {
                    $level = strtolower($matches[1]);
                    $stats['by_level'][$level] = ($stats['by_level'][$level] ?? 0) + 1;
                }
            }
        }
        
        // Also check rotated files
        $pattern = $this->log_dir . $this->getSubdirByType($type) . '/' . $type . '-*.log*';
        $rotated = glob($pattern);
        $stats['rotated_files'] = count($rotated);
        $stats['total_size'] = $stats['size'];
        
        foreach ($rotated as $file) {
            $stats['total_size'] += filesize($file);
        }
        $stats['total_size_human'] = $this->formatSize($stats['total_size']);
        
        return $stats;
    }
    
    /**
     * Get all available log dates
     * 
     * @param string $type
     * @return array
     */
    public function getAvailableDates(string $type): array {
        $dates = [];
        $pattern = $this->log_dir . $this->getSubdirByType($type) . '/' . $type . '-*.log';
        $files = glob($pattern);
        
        foreach ($files as $file) {
            if (preg_match('/' . $type . '-(\d{4}-\d{2}-\d{2})\.log/', $file, $matches)) {
                $dates[] = $matches[1];
            }
        }
        
        sort($dates);
        
        return $dates;
    }
    
    /**
     * Delete log file
     * 
     * @param string $type
     * @param string $date
     * @return bool
     */
    public function delete(string $type, string $date): bool {
        $log_file = $this->log_dir . $this->getSubdirByType($type) . '/' . $type . '-' . $date . '.log';
        
        if (file_exists($log_file)) {
            return unlink($log_file);
        }
        
        return false;
    }
    
    /**
     * Clean all logs older than specified days
     * 
     * @param int $days
     * @return int Number of deleted files
     */
    public function cleanOld(int $days = 30): int {
        $deleted = 0;
        $cutoff = strtotime("-$days days");
        
        $types = ['api', 'errors', 'security', 'performance', 'cron'];
        
        foreach ($types as $type) {
            $pattern = $this->log_dir . $this->getSubdirByType($type) . '/' . $type . '-*.log*';
            $files = glob($pattern);
            
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
    
    /**
     * Format size in bytes
     * 
     * @param int $bytes
     * @return string
     */
    private function formatSize(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Convenience methods for different log levels
     */
    
    public function emergency(string $message, array $context = [], string $type = 'api'): bool {
        return $this->write('emergency', $message, $context, $type);
    }
    
    public function alert(string $message, array $context = [], string $type = 'api'): bool {
        return $this->write('alert', $message, $context, $type);
    }
    
    public function critical(string $message, array $context = [], string $type = 'api'): bool {
        return $this->write('critical', $message, $context, $type);
    }
    
    public function error(string $message, array $context = [], string $type = 'api'): bool {
        return $this->write('error', $message, $context, $type);
    }
    
    public function warning(string $message, array $context = [], string $type = 'api'): bool {
        return $this->write('warning', $message, $context, $type);
    }
    
    public function notice(string $message, array $context = [], string $type = 'api'): bool {
        return $this->write('notice', $message, $context, $type);
    }
    
    public function info(string $message, array $context = [], string $type = 'api'): bool {
        return $this->write('info', $message, $context, $type);
    }
    
    public function debug(string $message, array $context = [], string $type = 'api'): bool {
        return $this->write('debug', $message, $context, $type);
    }
}