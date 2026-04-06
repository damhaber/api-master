<?php
/**
 * API Master - Cache Manager
 * 
 * @package APIMaster
 * @subpackage Cache
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class APIMaster_Cache_Manager
 * 
 * Önbellek yönetimi (WordPress transients yok, dosya tabanlı)
 */
class APIMaster_Cache_Manager {
    
    /**
     * @var string Cache directory
     */
    private $cacheDir;
    
    /**
     * @var int Default TTL (seconds)
     */
    private $defaultTtl = 3600;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->cacheDir = dirname(dirname(__FILE__)) . '/cache/';
        $this->ensureCacheDirectories();
    }
    
    /**
     * Cache dizinlerini oluştur
     */
    private function ensureCacheDirectories() {
        $dirs = [
            $this->cacheDir,
            $this->cacheDir . 'rate-limits/',
            $this->cacheDir . 'api-responses/',
            $this->cacheDir . 'embeddings/'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * Cache'e veri kaydet
     * 
     * @param string $key
     * @param mixed $data
     * @param int|null $ttl
     * @return bool
     */
    public function set($key, $data, $ttl = null) {
        $ttl = $ttl ?? $this->defaultTtl;
        $cacheFile = $this->getCacheFile($key);
        
        $cacheData = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time(),
            'key' => $key
        ];
        
        return file_put_contents($cacheFile, json_encode($cacheData)) !== false;
    }
    
    /**
     * Cache'ten veri al
     * 
     * @param string $key
     * @return mixed|null
     */
    public function get($key) {
        $cacheFile = $this->getCacheFile($key);
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        
        if (!$cacheData || $cacheData['expires'] < time()) {
            $this->delete($key);
            return null;
        }
        
        return $cacheData['data'];
    }
    
    /**
     * Cache'ten sil
     * 
     * @param string $key
     * @return bool
     */
    public function delete($key) {
        $cacheFile = $this->getCacheFile($key);
        
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        
        return true;
    }
    
    /**
     * Cache var mı?
     * 
     * @param string $key
     * @return bool
     */
    public function has($key) {
        $cacheFile = $this->getCacheFile($key);
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        
        if (!$cacheData || $cacheData['expires'] < time()) {
            $this->delete($key);
            return false;
        }
        
        return true;
    }
    
    /**
     * Cache dosya yolunu getir
     * 
     * @param string $key
     * @return string
     */
    private function getCacheFile($key) {
        return $this->cacheDir . md5($key) . '.cache';
    }
    
    /**
     * Süresi dolmuş cache'leri temizle
     * 
     * @return int
     */
    public function cleanExpired() {
        $cleaned = 0;
        $files = glob($this->cacheDir . '*.cache');
        
        foreach ($files as $file) {
            $cacheData = json_decode(file_get_contents($file), true);
            if (!$cacheData || $cacheData['expires'] < time()) {
                if (unlink($file)) {
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Tüm cache'i temizle
     * 
     * @return int
     */
    public function clearAll() {
        $cleaned = 0;
        $files = glob($this->cacheDir . '*.cache');
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Cache istatistikleri
     * 
     * @return array
     */
    public function getStats() {
        $files = glob($this->cacheDir . '*.cache');
        $totalSize = 0;
        $valid = 0;
        $expired = 0;
        
        foreach ($files as $file) {
            $size = filesize($file);
            $totalSize += $size;
            
            $cacheData = json_decode(file_get_contents($file), true);
            if ($cacheData && $cacheData['expires'] >= time()) {
                $valid++;
            } else {
                $expired++;
            }
        }
        
        return [
            'total_files' => count($files),
            'valid_cache' => $valid,
            'expired_cache' => $expired,
            'total_size' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2)
        ];
    }
}