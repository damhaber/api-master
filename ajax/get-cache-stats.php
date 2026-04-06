<?php
/**
 * get-cache-stats.php - AJAX Endpoint for Masal Panel
 * Get cache statistics and usage information
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);

class APIMaster_GetCacheStats
{
    private $moduleDir;
    private $cacheDir;
    private $logDir;
    private $statsFile;
    
    private $defaultLimitMB = 100;
    private $validCacheTypes = ['api_response', 'embedding', 'search', 'learning', 'vector'];
    
    public function __construct()
    {
        $this->moduleDir = defined('API_MASTER_MODULE_DIR') 
            ? API_MASTER_MODULE_DIR 
            : dirname(__DIR__);
        
        $this->cacheDir = $this->moduleDir . '/cache';
        $this->logDir = $this->moduleDir . '/logs';
        $this->statsFile = $this->cacheDir . '/stats.json';
    }
    
    public function execute()
    {
        try {
            $stats = $this->getCacheStats();
            $this->sendResponse(true, 'Cache stats retrieved successfully', $stats);
            
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            $this->sendResponse(false, 'Error: ' . $e->getMessage());
        }
    }
    
    private function getCacheStats()
    {
        $stats = [
            'exists' => false,
            'file_count' => 0,
            'total_size' => 0,
            'total_size_human' => '0 B',
            'oldest_file' => null,
            'newest_file' => null,
            'last_cleaned' => null,
            'cache_types' => [],
            'avg_size' => 0,
            'avg_size_human' => '0 B',
            'limit_mb' => $this->defaultLimitMB,
            'usage_percent' => 0,
            'needs_cleanup' => false
        ];
        
        // Cache klasörü yoksa oluşturmayı öner
        if (!is_dir($this->cacheDir)) {
            $stats['message'] = 'Cache directory does not exist';
            return $stats;
        }
        
        $stats['exists'] = true;
        
        // İstatistik dosyasından önceki verileri oku (performans için)
        $cachedStats = $this->loadCachedStats();
        if ($cachedStats && (time() - ($cachedStats['generated_at'] ?? 0)) < 300) {
            // 5 dakikadan eski değilse cached verileri kullan
            return $cachedStats;
        }
        
        // Taze istatistikleri topla
        $stats = $this->collectFreshStats();
        
        // İstatistikleri cache'le
        $this->saveCachedStats($stats);
        
        return $stats;
    }
    
    private function collectFreshStats()
    {
        $stats = [
            'exists' => true,
            'file_count' => 0,
            'total_size' => 0,
            'total_size_human' => '0 B',
            'oldest_file' => null,
            'newest_file' => null,
            'last_cleaned' => null,
            'cache_types' => [],
            'avg_size' => 0,
            'avg_size_human' => '0 B',
            'limit_mb' => $this->defaultLimitMB,
            'usage_percent' => 0,
            'needs_cleanup' => false,
            'generated_at' => time()
        ];
        
        // Son temizlenme zamanını oku
        $stats['last_cleaned'] = $this->getLastCleanedTime();
        
        // Tüm JSON cache dosyalarını tara
        $files = @glob($this->cacheDir . '/*.json');
        
        if ($files === false || empty($files)) {
            return $stats;
        }
        
        $stats['file_count'] = count($files);
        
        $totalSize = 0;
        $oldestTime = time();
        $newestTime = 0;
        $cacheTypes = [];
        
        foreach ($files as $file) {
            // Dosya boyutu (hata kontrolü ile)
            $size = @filesize($file);
            if ($size === false) {
                continue;
            }
            $totalSize += $size;
            
            // Dosya değişiklik zamanı
            $mtime = @filemtime($file);
            if ($mtime === false) {
                continue;
            }
            
            if ($mtime < $oldestTime) {
                $oldestTime = $mtime;
            }
            if ($mtime > $newestTime) {
                $newestTime = $mtime;
            }
            
            // Cache tipini belirle
            $type = $this->getCacheType(basename($file));
            $cacheTypes[$type] = ($cacheTypes[$type] ?? 0) + 1;
        }
        
        $stats['total_size'] = $totalSize;
        $stats['total_size_human'] = $this->formatBytes($totalSize);
        $stats['oldest_file'] = ($oldestTime !== time()) ? $oldestTime : null;
        $stats['newest_file'] = ($newestTime > 0) ? $newestTime : null;
        $stats['cache_types'] = $cacheTypes;
        
        // Ortalama dosya boyutu
        if ($stats['file_count'] > 0) {
            $stats['avg_size'] = (int)round($totalSize / $stats['file_count']);
            $stats['avg_size_human'] = $this->formatBytes($stats['avg_size']);
        }
        
        // Limit ve doluluk oranı
        $stats['limit_mb'] = $this->getCacheLimit();
        $limitBytes = $stats['limit_mb'] * 1024 * 1024;
        $stats['usage_percent'] = $limitBytes > 0 ? round(($totalSize / $limitBytes) * 100, 2) : 0;
        $stats['needs_cleanup'] = $stats['usage_percent'] > 90;
        
        return $stats;
    }
    
    private function getCacheType($filename)
    {
        foreach ($this->validCacheTypes as $type) {
            if (strpos($filename, $type . '_') === 0 || 
                strpos($filename, str_replace('_', '', $type) . '_') === 0) {
                return $type;
            }
        }
        
        // Özel pattern'ler
        if (strpos($filename, 'api_') === 0) {
            return 'api_response';
        }
        if (strpos($filename, 'embedding_') === 0) {
            return 'embedding';
        }
        if (strpos($filename, 'search_') === 0) {
            return 'search';
        }
        if (strpos($filename, 'learning_') === 0) {
            return 'learning';
        }
        if (strpos($filename, 'vector_') === 0) {
            return 'vector';
        }
        
        return 'unknown';
    }
    
    private function getLastCleanedTime()
    {
        $statsFile = $this->cacheDir . '/metadata.json';
        
        if (file_exists($statsFile)) {
            $content = @file_get_contents($statsFile);
            if ($content !== false) {
                $metadata = json_decode($content, true);
                if (is_array($metadata) && isset($metadata['last_cleaned'])) {
                    return (int)$metadata['last_cleaned'];
                }
            }
        }
        
        return null;
    }
    
    private function getCacheLimit()
    {
        $configFile = $this->moduleDir . '/config/cache.json';
        
        if (file_exists($configFile)) {
            $content = @file_get_contents($configFile);
            if ($content !== false) {
                $config = json_decode($content, true);
                if (is_array($config) && isset($config['max_size_mb'])) {
                    return (int)$config['max_size_mb'];
                }
            }
        }
        
        return $this->defaultLimitMB;
    }
    
    private function loadCachedStats()
    {
        if (!file_exists($this->statsFile)) {
            return null;
        }
        
        $content = @file_get_contents($this->statsFile);
        if ($content === false) {
            return null;
        }
        
        $stats = json_decode($content, true);
        if (!is_array($stats)) {
            return null;
        }
        
        return $stats;
    }
    
    private function saveCachedStats($stats)
    {
        // Cache klasörü yoksa oluştur
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
        
        $stats['cached'] = true;
        $stats['cache_time'] = time();
        
        @file_put_contents(
            $this->statsFile,
            json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
    
    private function formatBytes($bytes, $precision = 2)
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen((string)$bytes) - 1) / 3);
        $factor = min($factor, count($units) - 1);
        
        $bytes /= pow(1024, $factor);
        
        return round($bytes, $precision) . ' ' . $units[$factor];
    }
    
    private function logError($message)
    {
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
        
        $logEntry = '[' . date('Y-m-d H:i:s') . '] [ERROR] [get-cache-stats] ' . $message . PHP_EOL;
        @file_put_contents($this->logDir . '/api-master.log', $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function sendResponse($success, $message, $data = [])
    {
        header('Content-Type: application/json');
        $response = [
            'success' => $success,
            'message' => $message,
            'timestamp' => time()
        ];
        
        if (!empty($data)) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$instance = new APIMaster_GetCacheStats();
$instance->execute();