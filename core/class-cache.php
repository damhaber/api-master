<?php
if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_Cache
{
    private $driver = 'file';
    private $defaultTtl = 3600;
    private $prefix = 'am_cache_';
    private $memoryCache = [];
    private $cacheDir;
    private $settingsFile;

    public function __construct()
    {
        $moduleDir = dirname(__DIR__, 1);
        $this->cacheDir = $moduleDir . '/cache/';
        $this->settingsFile = $moduleDir . '/config/settings.json';
        
        $this->loadConfig();
        $this->initDriver();
    }

    private function loadConfig()
    {
        if (file_exists($this->settingsFile)) {
            $content = file_get_contents($this->settingsFile);
            $config = json_decode($content, true);
            
            if (is_array($config)) {
                $this->defaultTtl = isset($config['cache_ttl']) ? intval($config['cache_ttl']) : 3600;
            }
        }
    }

    private function initDriver()
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    private function getKey($key)
    {
        return $this->prefix . md5($key);
    }

    private function getFilePath($key)
    {
        return $this->cacheDir . $key . '.cache';
    }

    public function set($key, $data, $ttl = null)
    {
        $key = $this->getKey($key);
        $ttl = $ttl ?: $this->defaultTtl;
        
        $this->memoryCache[$key] = [
            'data' => $data,
            'expires' => time() + $ttl
        ];
        
        return $this->setFile($key, $data, $ttl);
    }

    private function setFile($key, $data, $ttl)
    {
        $file = $this->getFilePath($key);
        
        $cacheData = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        $content = serialize($cacheData);
        $result = file_put_contents($file, $content, LOCK_EX);
        
        return $result !== false;
    }

    public function get($key)
    {
        $key = $this->getKey($key);
        
        if (isset($this->memoryCache[$key])) {
            $item = $this->memoryCache[$key];
            if ($item['expires'] > time()) {
                return $item['data'];
            }
            unset($this->memoryCache[$key]);
        }
        
        return $this->getFile($key);
    }

    private function getFile($key)
    {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }
        
        $cacheData = unserialize($content);
        
        if (!$cacheData || !isset($cacheData['expires'])) {
            return null;
        }
        
        if ($cacheData['expires'] < time()) {
            $this->delete($key);
            return null;
        }
        
        $this->memoryCache[$key] = $cacheData;
        
        return $cacheData['data'];
    }

    public function delete($key)
    {
        $key = $this->getKey($key);
        unset($this->memoryCache[$key]);
        
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }

    public function clearAll()
    {
        $this->memoryCache = [];
        return $this->clearFileCache();
    }

    private function clearFileCache()
    {
        $files = glob($this->cacheDir . '*.cache');
        $success = true;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if (!unlink($file)) {
                    $success = false;
                }
            }
        }
        
        return $success;
    }

    public function has($key)
    {
        return $this->get($key) !== null;
    }

    public function getStats()
    {
        $stats = [
            'driver' => $this->driver,
            'default_ttl' => $this->defaultTtl,
            'cache_dir' => $this->cacheDir,
            'size' => $this->getFileCacheSize(),
            'count' => $this->getFileCacheCount()
        ];
        
        return $stats;
    }

    private function getFileCacheSize()
    {
        $size = 0;
        $files = glob($this->cacheDir . '*.cache');
        
        foreach ($files as $file) {
            $size += filesize($file);
        }
        
        return $this->formatSize($size);
    }

    private function getFileCacheCount()
    {
        $files = glob($this->cacheDir . '*.cache');
        return count($files);
    }

    private function formatSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function cleanExpired()
    {
        $files = glob($this->cacheDir . '*.cache');
        $cleaned = 0;
        $now = time();
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }
            
            $cacheData = unserialize($content);
            
            if ($cacheData && isset($cacheData['expires'])) {
                if ($cacheData['expires'] < $now) {
                    unlink($file);
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }

    public function remember($key, callable $callback, $ttl = null)
    {
        $cached = $this->get($key);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $data = $callback();
        $this->set($key, $data, $ttl);
        
        return $data;
    }

    public function increment($key, $amount = 1)
    {
        $value = $this->get($key);
        
        if ($value === null) {
            $value = 0;
        }
        
        if (!is_numeric($value)) {
            return false;
        }
        
        $newValue = $value + $amount;
        $this->set($key, $newValue);
        
        return $newValue;
    }

    public function decrement($key, $amount = 1)
    {
        $value = $this->get($key);
        
        if ($value === null) {
            $value = 0;
        }
        
        if (!is_numeric($value)) {
            return false;
        }
        
        $newValue = $value - $amount;
        $this->set($key, $newValue);
        
        return $newValue;
    }
}