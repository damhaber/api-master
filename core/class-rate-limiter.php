<?php
if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_RateLimiter
{
    const TYPE_IP = 'ip';
    const TYPE_USER = 'user';
    const TYPE_API_KEY = 'api_key';
    const TYPE_ENDPOINT = 'endpoint';
    const TYPE_GLOBAL = 'global';
    
    private $defaultLimits = [
        self::TYPE_GLOBAL => ['limit' => 1000, 'window' => 3600],
        self::TYPE_IP => ['limit' => 100, 'window' => 60],
        self::TYPE_USER => ['limit' => 500, 'window' => 3600],
        self::TYPE_API_KEY => ['limit' => 1000, 'window' => 3600],
        self::TYPE_ENDPOINT => ['limit' => 100, 'window' => 60]
    ];
    
    private $customLimits = [];
    private $rateLimitDir;
    private $logger;
    private $settingsFile;

    public function __construct()
    {
        $moduleDir = dirname(__DIR__, 1);
        $this->rateLimitDir = $moduleDir . '/cache/rate-limits/';
        $this->settingsFile = $moduleDir . '/config/settings.json';
        
        $this->loadConfig();
        $this->initStorage();
    }

    private function loadConfig()
    {
        if (file_exists($this->settingsFile)) {
            $content = file_get_contents($this->settingsFile);
            $config = json_decode($content, true);
            
            if (is_array($config)) {
                if (isset($config['rate_limits']) && is_array($config['rate_limits'])) {
                    $this->customLimits = $config['rate_limits'];
                }
                
                if (isset($config['rate_limit_defaults']) && is_array($config['rate_limit_defaults'])) {
                    foreach ($config['rate_limit_defaults'] as $type => $limits) {
                        if (isset($this->defaultLimits[$type])) {
                            $this->defaultLimits[$type] = array_merge($this->defaultLimits[$type], $limits);
                        }
                    }
                }
            }
        }
        
        if (class_exists('APIMaster_Logger')) {
            $this->logger = new APIMaster_Logger();
        }
    }

    private function initStorage()
    {
        if (!is_dir($this->rateLimitDir)) {
            mkdir($this->rateLimitDir, 0755, true);
        }
    }

    private function getLimits($type, $identifier = null)
    {
        if ($identifier && isset($this->customLimits[$type][$identifier])) {
            return $this->customLimits[$type][$identifier];
        }
        
        return isset($this->defaultLimits[$type]) 
            ? $this->defaultLimits[$type] 
            : ['limit' => 100, 'window' => 60];
    }

    public function check($type, $identifier, $limit = null, $window = null)
    {
        $limits = $this->getLimits($type, $identifier);
        
        $limit = $limit !== null ? $limit : $limits['limit'];
        $window = $window !== null ? $window : $limits['window'];
        
        $key = $this->getKey($type, $identifier);
        $current = $this->getCurrent($key, $window);
        
        $allowed = $current['count'] < $limit;
        $remaining = max(0, $limit - $current['count']);
        $reset = $current['reset'];
        $retryAfter = max(0, $reset - time());
        
        if ($allowed) {
            $this->increment($key, $window);
        }
        
        if (!$allowed && $this->logger) {
            $this->logger->warning('Rate limit exceeded', [
                'type' => $type,
                'identifier' => $identifier,
                'limit' => $limit,
                'window' => $window,
                'current' => $current['count']
            ]);
        }
        
        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'reset' => $reset,
            'retry_after' => $retryAfter,
            'limit' => $limit,
            'window' => $window
        ];
    }

    private function getKey($type, $identifier)
    {
        return "rate_limit:{$type}:{$identifier}";
    }

    private function getCurrent($key, $window)
    {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return ['count' => 0, 'reset' => time() + $window];
        }
        
        $content = file_get_contents($file);
        if ($content === false) {
            return ['count' => 0, 'reset' => time() + $window];
        }
        
        $data = json_decode($content, true);
        
        if (!$data || !isset($data['count']) || !isset($data['expires'])) {
            return ['count' => 0, 'reset' => time() + $window];
        }
        
        if ($data['expires'] < time()) {
            unlink($file);
            return ['count' => 0, 'reset' => time() + $window];
        }
        
        return ['count' => $data['count'], 'reset' => $data['expires']];
    }

    private function increment($key, $window)
    {
        $file = $this->getFilePath($key);
        
        $current = $this->getCurrent($key, $window);
        $newCount = $current['count'] + 1;
        $expires = time() + $window;
        
        $data = [
            'count' => $newCount,
            'expires' => $expires
        ];
        
        return file_put_contents($file, json_encode($data), LOCK_EX) !== false;
    }

    private function getFilePath($key)
    {
        return $this->rateLimitDir . md5($key) . '.json';
    }

    public function reset($type, $identifier)
    {
        $key = $this->getKey($type, $identifier);
        $file = $this->getFilePath($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }

    public function resetAll()
    {
        $files = glob($this->rateLimitDir . '*.json');
        $success = true;
        
        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }
        
        return $success;
    }

    public function setCustomLimit($type, $identifier, $limit, $window)
    {
        if (!isset($this->customLimits[$type])) {
            $this->customLimits[$type] = [];
        }
        
        $this->customLimits[$type][$identifier] = [
            'limit' => $limit,
            'window' => $window
        ];
        
        return true;
    }

    public function removeCustomLimit($type, $identifier)
    {
        if (isset($this->customLimits[$type][$identifier])) {
            unset($this->customLimits[$type][$identifier]);
            return true;
        }
        
        return true;
    }

    public function getStats()
    {
        $stats = [
            'storage_driver' => 'file',
            'default_limits' => $this->defaultLimits,
            'custom_limits_count' => count($this->customLimits)
        ];
        
        $files = glob($this->rateLimitDir . '*.json');
        $stats['active_limits'] = count($files);
        
        $totalRequests = 0;
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data) && isset($data['count'])) {
                    $totalRequests += $data['count'];
                }
            }
        }
        $stats['total_requests'] = $totalRequests;
        
        return $stats;
    }

    public function getHeaders($result)
    {
        return [
            'X-RateLimit-Limit' => $result['limit'],
            'X-RateLimit-Remaining' => $result['remaining'],
            'X-RateLimit-Reset' => $result['reset'],
            'Retry-After' => $result['retry_after']
        ];
    }

    public function checkIp($ip, $limit = null, $window = null)
    {
        return $this->check(self::TYPE_IP, $ip, $limit, $window);
    }

    public function checkApiKey($apiKey, $limit = null, $window = null)
    {
        return $this->check(self::TYPE_API_KEY, $apiKey, $limit, $window);
    }

    public function checkEndpoint($endpoint, $limit = null, $window = null)
    {
        return $this->check(self::TYPE_ENDPOINT, $endpoint, $limit, $window);
    }

    public function checkGlobal($limit = null, $window = null)
    {
        return $this->check(self::TYPE_GLOBAL, 'global', $limit, $window);
    }
}