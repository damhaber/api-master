<?php
/**
 * API Master - Log Rotator
 * 
 * @package APIMaster
 * @subpackage Logs
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class APIMaster_Log_Rotator
 * 
 * Log dosyalarını döndürür ve temizler
 */
class APIMaster_Log_Rotator {
    
    /**
     * @var string Log directory
     */
    private $logDir;
    
    /**
     * @var int Max file size (10MB default)
     */
    private $maxFileSize = 10485760;
    
    /**
     * @var int Max files to keep (30 default)
     */
    private $maxFiles = 30;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logDir = dirname(dirname(__FILE__)) . '/logs/';
        $this->loadConfig();
    }
    
    /**
     * Load configuration
     */
    private function loadConfig() {
        $configFile = dirname(dirname(__FILE__)) . '/config/logs.json';
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            $this->maxFileSize = $config['max_file_size'] ?? 10485760;
            $this->maxFiles = $config['max_files'] ?? 30;
        }
    }
    
    /**
     * Log dosyasını döndür
     * 
     * @param string $logFile
     * @return bool
     */
    public function rotate($logFile) {
        $filePath = $this->logDir . $logFile;
        
        if (!file_exists($filePath)) {
            return false;
        }
        
        if (filesize($filePath) < $this->maxFileSize) {
            return false;
        }
        
        // Mevcut logları kaydır
        for ($i = $this->maxFiles - 1; $i > 0; $i--) {
            $oldFile = $this->logDir . $logFile . '.' . $i . '.gz';
            $newFile = $this->logDir . $logFile . '.' . ($i + 1) . '.gz';
            
            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }
        
        // Mevcut log'u sıkıştır ve yedekle
        $backupFile = $this->logDir . $logFile . '.1';
        rename($filePath, $backupFile);
        
        // Sıkıştır
        $gzFile = $backupFile . '.gz';
        $fp = gzopen($gzFile, 'w9');
        gzwrite($fp, file_get_contents($backupFile));
        gzclose($fp);
        
        // Orijinal yedek dosyasını sil
        unlink($backupFile);
        
        // Yeni log dosyası oluştur
        touch($filePath);
        chmod($filePath, 0644);
        
        return true;
    }
    
    /**
     * Tüm log dosyalarını döndür
     * 
     * @return array
     */
    public function rotateAll() {
        $results = [];
        $logFiles = glob($this->logDir . '*.log');
        
        foreach ($logFiles as $file) {
            $filename = basename($file);
            $results[$filename] = $this->rotate($filename);
        }
        
        return $results;
    }
    
    /**
     * Eski logları temizle
     * 
     * @param int $days
     * @return int
     */
    public function cleanOldLogs($days = 30) {
        $deleted = 0;
        $cutoff = time() - ($days * 86400);
        
        $files = glob($this->logDir . '*.log*');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
    
    /**
     * Log dosyasını oku
     * 
     * @param string $logFile
     * @param int $lines
     * @param bool $reverse
     * @return array
     */
    public function readLog($logFile, $lines = 100, $reverse = true) {
        $filePath = $this->logDir . $logFile;
        
        if (!file_exists($filePath)) {
            return [];
        }
        
        // Gzip dosyası mı?
        if (pathinfo($filePath, PATHINFO_EXTENSION) === 'gz') {
            $content = gzfile($filePath);
        } else {
            $content = file($filePath);
        }
        
        if ($reverse) {
            $content = array_reverse($content);
        }
        
        return array_slice($content, 0, $lines);
    }
    
    /**
     * Log istatistiklerini getir
     * 
     * @return array
     */
    public function getStats() {
        $stats = [];
        $logFiles = glob($this->logDir . '*.log*');
        
        foreach ($logFiles as $file) {
            $filename = basename($file);
            $stats[$filename] = [
                'size' => filesize($file),
                'size_mb' => round(filesize($file) / 1024 / 1024, 2),
                'modified' => date('Y-m-d H:i:s', filemtime($file)),
                'is_compressed' => pathinfo($file, PATHINFO_EXTENSION) === 'gz'
            ];
        }
        
        return $stats;
    }
}