<?php
/**
 * API Master - Cache Manager
 * 
 * @package APIMaster
 * @subpackage Includes
 * @since 1.0.0
 * 
 * IMPORTANT: No WordPress dependencies! Pure file-based cache management.
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_Cache_Manager {
    
    /**
     * @var string Cache directory
     */
    private $cache_dir;
    
    /**
     * @var int Default TTL in seconds
     */
    private $default_ttl = 3600;
    
    /**
     * @var array Cache statistics
     */
    private $stats = [];
    
    /**
     * @var array In-memory cache for hot items
     */
    private $memory_cache = [];
    
    /**
     * @var int Max memory cache items
     */
    private $max_memory_items = 100;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->cache_dir = dirname(dirname(__FILE__)) . '/cache/';
        $this->ensureCacheDirectory();
        $this->loadConfig();
    }
    
    /**
     * Ensure cache directory exists
     */
    private function ensureCacheDirectory(): void {
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
        
        // Subdirectories
        $subdirs = ['api-responses', 'embeddings', 'rate-limits', 'temp'];
        foreach ($subdirs as $subdir) {
            $path = $this->cache_dir . $subdir;
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
            $this->default_ttl = $config['cache_ttl'] ?? 3600;
            $this->max_memory_items = $config['max_memory_cache'] ?? 100;
        }
    }
    
    /**
     * Generate cache key
     * 
     * @param string $key
     * @param string $prefix
     * @return string
     */
    private function getCacheKey(string $key, string $prefix = ''): string {
        $full_key = $prefix . md5($key);
        return $full_key;
    }
    
    /**
     * Get cache file path
     * 
     * @param string $key
     * @param string $group
     * @return string
     */
    private function getCacheFile(string $key, string $group = 'default'): string {
        $group_dir = $this->cache_dir . $group . '/';
        
        if (!is_dir($group_dir)) {
            mkdir($group_dir, 0755, true);
        }
        
        return $group_dir . md5($key) . '.cache';
    }
    
    /**
     * Set cache item
     * 
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @param string $group
     * @return bool
     */
    public function set(string $key, $value, ?int $ttl = null, string $group = 'default'): bool {
        $ttl = $ttl ?? $this->default_ttl;
        $cache_key = $this->getCacheKey($key);
        
        $data = [
            'value' => $value,
            'expires_at' => time() + $ttl,
            'created_at' => time(),
            'key' => $key,
            'group' => $group
        ];
        
        // Save to memory cache for hot items
        if (count($this->memory_cache) >= $this->max_memory_items) {
            array_shift($this->memory_cache);
        }
        $this->memory_cache[$cache_key] = $data;
        
        // Save to file
        $file = $this->getCacheFile($key, $group);
        $result = file_put_contents($file, serialize($data));
        
        // Update stats
        $this->updateStats('set', 1);
        
        return $result !== false;
    }
    
    /**
     * Get cache item
     * 
     * @param string $key
     * @param string $group
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, string $group = 'default', $default = null) {
        $cache_key = $this->getCacheKey($key);
        
        // Check memory cache first
        if (isset($this->memory_cache[$cache_key])) {
            $data = $this->memory_cache[$cache_key];
            if ($data['expires_at'] > time()) {
                $this->updateStats('hit', 1);
                return $data['value'];
            }
            unset($this->memory_cache[$cache_key]);
        }
        
        // Check file cache
        $file = $this->getCacheFile($key, $group);
        
        if (!file_exists($file)) {
            $this->updateStats('miss', 1);
            return $default;
        }
        
        $data = unserialize(file_get_contents($file));
        
        if (!$data || $data['expires_at'] < time()) {
            $this->delete($key, $group);
            $this->updateStats('miss', 1);
            return $default;
        }
        
        // Add to memory cache
        if (count($this->memory_cache) < $this->max_memory_items) {
            $this->memory_cache[$cache_key] = $data;
        }
        
        $this->updateStats('hit', 1);
        
        return $data['value'];
    }
    
    /**
     * Check if cache exists and not expired
     * 
     * @param string $key
     * @param string $group
     * @return bool
     */
    public function has(string $key, string $group = 'default'): bool {
        $value = $this->get($key, $group, '__NULL__');
        return $value !== '__NULL__';
    }
    
    /**
     * Delete cache item
     * 
     * @param string $key
     * @param string $group
     * @return bool
     */
    public function delete(string $key, string $group = 'default'): bool {
        $cache_key = $this->getCacheKey($key);
        
        // Remove from memory
        unset($this->memory_cache[$cache_key]);
        
        // Remove from file
        $file = $this->getCacheFile($key, $group);
        
        if (file_exists($file)) {
            $this->updateStats('delete', 1);
            return unlink($file);
        }
        
        return true;
    }
    
    /**
     * Clear entire cache or specific group
     * 
     * @param string|null $group
     * @return int Number of deleted files
     */
    public function clear(?string $group = null): int {
        $deleted = 0;
        
        if ($group) {
            // Clear specific group
            $group_dir = $this->cache_dir . $group . '/';
            if (is_dir($group_dir)) {
                $files = glob($group_dir . '*.cache');
                foreach ($files as $file) {
                    if (unlink($file)) {
                        $deleted++;
                    }
                }
            }
        } else {
            // Clear all cache
            $dirs = glob($this->cache_dir . '*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $files = glob($dir . '/*.cache');
                foreach ($files as $file) {
                    if (unlink($file)) {
                        $deleted++;
                    }
                }
            }
            
            // Also clear root cache files
            $files = glob($this->cache_dir . '*.cache');
            foreach ($files as $file) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        // Clear memory cache
        $this->memory_cache = [];
        
        $this->updateStats('clear', $deleted);
        
        return $deleted;
    }
    
    /**
     * Remember pattern (get or set)
     * 
     * @param string $key
     * @param callable $callback
     * @param int|null $ttl
     * @param string $group
     * @return mixed
     */
    public function remember(string $key, callable $callback, ?int $ttl = null, string $group = 'default') {
        $value = $this->get($key, $group);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl, $group);
        
        return $value;
    }
    
    /**
     * Increment cache value
     * 
     * @param string $key
     * @param int $step
     * @param string $group
     * @return int|false
     */
    public function increment(string $key, int $step = 1, string $group = 'default') {
        $value = $this->get($key, $group);
        
        if ($value === null) {
            $value = 0;
        }
        
        if (!is_numeric($value)) {
            return false;
        }
        
        $new_value = $value + $step;
        $this->set($key, $new_value, null, $group);
        
        return $new_value;
    }
    
    /**
     * Decrement cache value
     * 
     * @param string $key
     * @param int $step
     * @param string $group
     * @return int|false
     */
    public function decrement(string $key, int $step = 1, string $group = 'default') {
        $value = $this->get($key, $group);
        
        if ($value === null) {
            $value = 0;
        }
        
        if (!is_numeric($value)) {
            return false;
        }
        
        $new_value = $value - $step;
        $this->set($key, $new_value, null, $group);
        
        return $new_value;
    }
    
    /**
     * Get multiple cache items
     * 
     * @param array $keys
     * @param string $group
     * @return array
     */
    public function getMultiple(array $keys, string $group = 'default'): array {
        $results = [];
        
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $group);
        }
        
        return $results;
    }
    
    /**
     * Set multiple cache items
     * 
     * @param array $items
     * @param int|null $ttl
     * @param string $group
     * @return bool
     */
    public function setMultiple(array $items, ?int $ttl = null, string $group = 'default'): bool {
        $success = true;
        
        foreach ($items as $key => $value) {
            if (!$this->set($key, $value, $ttl, $group)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Delete multiple cache items
     * 
     * @param array $keys
     * @param string $group
     * @return bool
     */
    public function deleteMultiple(array $keys, string $group = 'default'): bool {
        $success = true;
        
        foreach ($keys as $key) {
            if (!$this->delete($key, $group)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Get cache statistics
     * 
     * @return array
     */
    public function getStats(): array {
        // Calculate cache size
        $total_size = 0;
        $total_files = 0;
        
        $dirs = glob($this->cache_dir . '*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $files = glob($dir . '/*.cache');
            foreach ($files as $file) {
                $total_size += filesize($file);
                $total_files++;
            }
        }
        
        $root_files = glob($this->cache_dir . '*.cache');
        foreach ($root_files as $file) {
            $total_size += filesize($file);
            $total_files++;
        }
        
        $hit_rate = $this->stats['total'] > 0 
            ? round(($this->stats['hits'] / $this->stats['total']) * 100, 2) 
            : 0;
        
        return [
            'total_files' => $total_files,
            'total_size' => $this->formatSize($total_size),
            'total_size_bytes' => $total_size,
            'hits' => $this->stats['hits'] ?? 0,
            'misses' => $this->stats['misses'] ?? 0,
            'sets' => $this->stats['sets'] ?? 0,
            'deletes' => $this->stats['deletes'] ?? 0,
            'clears' => $this->stats['clears'] ?? 0,
            'total_operations' => $this->stats['total'] ?? 0,
            'hit_rate' => $hit_rate,
            'memory_cache_items' => count($this->memory_cache),
            'default_ttl' => $this->default_ttl,
            'cache_dir' => $this->cache_dir
        ];
    }
    
    /**
     * Update cache statistics
     * 
     * @param string $type
     * @param int $count
     */
    private function updateStats(string $type, int $count = 1): void {
        if (!isset($this->stats[$type . 's'])) {
            $this->stats[$type . 's'] = 0;
        }
        
        $this->stats[$type . 's'] += $count;
        $this->stats['total'] = ($this->stats['total'] ?? 0) + $count;
        
        // Save stats periodically (every 100 operations)
        if ($this->stats['total'] % 100 === 0) {
            $this->saveStats();
        }
    }
    
    /**
     * Save statistics to file
     */
    private function saveStats(): void {
        $stats_file = $this->cache_dir . 'cache-stats.json';
        file_put_contents($stats_file, json_encode($this->stats, JSON_PRETTY_PRINT));
    }
    
    /**
     * Load statistics from file
     */
    private function loadStats(): void {
        $stats_file = $this->cache_dir . 'cache-stats.json';
        
        if (file_exists($stats_file)) {
            $stats = json_decode(file_get_contents($stats_file), true);
            if (is_array($stats)) {
                $this->stats = $stats;
            }
        }
    }
    
    /**
     * Clean expired cache items
     * 
     * @return int Number of cleaned items
     */
    public function cleanExpired(): int {
        $cleaned = 0;
        $now = time();
        
        $dirs = glob($this->cache_dir . '*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $files = glob($dir . '/*.cache');
            foreach ($files as $file) {
                $data = unserialize(file_get_contents($file));
                if ($data && $data['expires_at'] < $now) {
                    if (unlink($file)) {
                        $cleaned++;
                    }
                }
            }
        }
        
        // Also check root cache files
        $files = glob($this->cache_dir . '*.cache');
        foreach ($files as $file) {
            $data = unserialize(file_get_contents($file));
            if ($data && $data['expires_at'] < $now) {
                if (unlink($file)) {
                    $cleaned++;
                }
            }
        }
        
        $this->updateStats('clean', $cleaned);
        
        return $cleaned;
    }
    
    /**
     * Warm up cache with common queries
     * 
     * @param array $items
     * @return int
     */
    public function warmup(array $items): int {
        $warmed = 0;
        
        foreach ($items as $item) {
            if (isset($item['key']) && isset($item['value'])) {
                $ttl = $item['ttl'] ?? $this->default_ttl;
                $group = $item['group'] ?? 'default';
                
                if ($this->set($item['key'], $item['value'], $ttl, $group)) {
                    $warmed++;
                }
            }
        }
        
        return $warmed;
    }
    
    /**
     * Format size in bytes to human readable
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
     * Get cache keys by pattern
     * 
     * @param string $pattern
     * @param string $group
     * @return array
     */
    public function getKeys(string $pattern = '*', string $group = 'default'): array {
        $keys = [];
        $group_dir = $this->cache_dir . $group . '/';
        
        if (!is_dir($group_dir)) {
            return [];
        }
        
        $files = glob($group_dir . $pattern . '.cache');
        
        foreach ($files as $file) {
            $data = unserialize(file_get_contents($file));
            if ($data && isset($data['key'])) {
                $keys[] = $data['key'];
            }
        }
        
        return $keys;
    }
    
    /**
     * Tag-based cache operations
     */
    private $tags = [];
    
    /**
     * Get cache with tag
     * 
     * @param string $key
     * @param string $tag
     * @param mixed $default
     * @return mixed
     */
    public function getTagged(string $key, string $tag, $default = null) {
        $value = $this->get($key, 'tagged_' . $tag);
        return $value !== null ? $value : $default;
    }
    
    /**
     * Set cache with tag
     * 
     * @param string $key
     * @param mixed $value
     * @param string $tag
     * @param int|null $ttl
     * @return bool
     */
    public function setTagged(string $key, $value, string $tag, ?int $ttl = null): bool {
        return $this->set($key, $value, $ttl, 'tagged_' . $tag);
    }
    
    /**
     * Clear all cache by tag
     * 
     * @param string $tag
     * @return int
     */
    public function clearTag(string $tag): int {
        return $this->clear('tagged_' . $tag);
    }
}