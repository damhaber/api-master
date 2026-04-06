<?php
/**
 * API Master - Rate Limiter
 * 
 * @package APIMaster
 * @subpackage Security
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class APIMaster_Rate_Limiter
 * 
 * Rate limiting yönetimi
 */
class APIMaster_Rate_Limiter {
    
    /**
     * @var string Rate limit storage directory
     */
    private $storageDir;
    
    /**
     * @var array Default limits
     */
    private $defaultLimits = [
        'default' => ['limit' => 60, 'window' => 60],
        'auth' => ['limit' => 10, 'window' => 60],
        'api' => ['limit' => 100, 'window' => 60],
        'admin' => ['limit' => 200, 'window' => 60]
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->storageDir = dirname(dirname(__FILE__)) . '/cache/rate-limits/';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }
    
    /**
     * Rate limit kontrolü
     * 
     * @param string $key Benzersiz anahtar (IP, user_id, vs.)
     * @param string $type Limit tipi
     * @param int|null $customLimit Özel limit
     * @param int|null $customWindow Özel pencere (saniye)
     * @return array ['allowed' => bool, 'remaining' => int, 'reset' => int]
     */
    public function check($key, $type = 'default', $customLimit = null, $customWindow = null) {
        $limits = $this->getLimits($type, $customLimit, $customWindow);
        $limit = $limits['limit'];
        $window = $limits['window'];
        
        $cacheKey = md5($key . '_' . $type);
        $data = $this->getData($cacheKey);
        $now = time();
        
        // Eski kayıtları temizle
        $data['requests'] = array_filter($data['requests'], function($timestamp) use ($now, $window) {
            return $timestamp > ($now - $window);
        });
        
        $currentCount = count($data['requests']);
        $allowed = $currentCount < $limit;
        
        if ($allowed) {
            $data['requests'][] = $now;
            $data['last_check'] = $now;
        }
        
        $this->saveData($cacheKey, $data);
        
        // Reset zamanını hesapla
        $oldestRequest = !empty($data['requests']) ? min($data['requests']) : $now;
        $reset = $oldestRequest + $window;
        
        return [
            'allowed' => $allowed,
            'limit' => $limit,
            'remaining' => max(0, $limit - ($allowed ? $currentCount + 1 : $currentCount)),
            'reset' => $reset,
            'retry_after' => $allowed ? 0 : ($reset - $now)
        ];
    }
    
    /**
     * Limit ayarlarını getir
     * 
     * @param string $type
     * @param int|null $customLimit
     * @param int|null $customWindow
     * @return array
     */
    private function getLimits($type, $customLimit = null, $customWindow = null) {
        if ($customLimit !== null && $customWindow !== null) {
            return ['limit' => $customLimit, 'window' => $customWindow];
        }
        
        if (isset($this->defaultLimits[$type])) {
            return $this->defaultLimits[$type];
        }
        
        return $this->defaultLimits['default'];
    }
    
    /**
     * Rate limit verisini getir
     * 
     * @param string $key
     * @return array
     */
    private function getData($key) {
        $file = $this->storageDir . $key . '.json';
        
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['requests'])) {
                return $data;
            }
        }
        
        return ['requests' => [], 'last_check' => time()];
    }
    
    /**
     * Rate limit verisini kaydet
     * 
     * @param string $key
     * @param array $data
     */
    private function saveData($key, $data) {
        $file = $this->storageDir . $key . '.json';
        file_put_contents($file, json_encode($data));
    }
    
    /**
     * Rate limit sıfırla
     * 
     * @param string $key
     * @param string $type
     * @return bool
     */
    public function reset($key, $type = 'default') {
        $cacheKey = md5($key . '_' . $type);
        $file = $this->storageDir . $cacheKey . '.json';
        
        if (file_exists($file)) {
            unlink($file);
            return true;
        }
        
        return false;
    }
    
    /**
     * Kalan istek sayısını getir
     * 
     * @param string $key
     * @param string $type
     * @param int|null $customLimit
     * @param int|null $customWindow
     * @return int
     */
    public function getRemaining($key, $type = 'default', $customLimit = null, $customWindow = null) {
        $limits = $this->getLimits($type, $customLimit, $customWindow);
        $limit = $limits['limit'];
        $window = $limits['window'];
        
        $cacheKey = md5($key . '_' . $type);
        $data = $this->getData($cacheKey);
        $now = time();
        
        $data['requests'] = array_filter($data['requests'], function($timestamp) use ($now, $window) {
            return $timestamp > ($now - $window);
        });
        
        return max(0, $limit - count($data['requests']));
    }
    
    /**
     * Özel limit tanımla
     * 
     * @param string $type
     * @param int $limit
     * @param int $window
     */
    public function defineLimit($type, $limit, $window) {
        $this->defaultLimits[$type] = ['limit' => $limit, 'window' => $window];
    }
    
    /**
     * Tüm limitleri getir
     * 
     * @return array
     */
    public function getLimitsList() {
        return $this->defaultLimits;
    }
    
    /**
     * Rate limit istatistikleri
     * 
     * @param string $key
     * @param string $type
     * @return array
     */
    public function getStats($key, $type = 'default') {
        $limits = $this->getLimits($type);
        $cacheKey = md5($key . '_' . $type);
        $data = $this->getData($cacheKey);
        $now = time();
        $window = $limits['window'];
        
        $data['requests'] = array_filter($data['requests'], function($timestamp) use ($now, $window) {
            return $timestamp > ($now - $window);
        });
        
        return [
            'type' => $type,
            'limit' => $limits['limit'],
            'window' => $limits['window'],
            'current_usage' => count($data['requests']),
            'remaining' => max(0, $limits['limit'] - count($data['requests'])),
            'last_check' => $data['last_check'] ?? null
        ];
    }
    
    /**
     * Tüm rate limit verilerini temizle
     * 
     * @return int
     */
    public function clearAll() {
        $count = 0;
        $files = glob($this->storageDir . '*.json');
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $count++;
            }
        }
        
        return $count;
    }
}