<?php
/**
 * API Master - API Key Manager
 * 
 * @package APIMaster
 * @subpackage Includes
 * @since 1.0.0
 * 
 * IMPORTANT: No WordPress dependencies! Pure JSON-based API key management.
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Key_Manager {
    
    /**
     * @var string API keys storage file
     */
    private $storage_file;
    
    /**
     * @var array In-memory cache
     */
    private $keys = [];
    
    /**
     * @var string Encryption key
     */
    private $encryption_key;
    
    /**
     * @var array Rate limit defaults
     */
    private $rate_limits = [
        'default' => ['limit' => 1000, 'window' => 3600],
        'free' => ['limit' => 100, 'window' => 3600],
        'basic' => ['limit' => 1000, 'window' => 3600],
        'pro' => ['limit' => 10000, 'window' => 3600],
        'enterprise' => ['limit' => 100000, 'window' => 3600]
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->storage_file = dirname(dirname(__FILE__)) . '/data/api-keys.json';
        $this->encryption_key = $this->getOrCreateEncryptionKey();
        $this->loadKeys();
    }
    
    /**
     * Get or create encryption key
     * 
     * @return string
     */
    private function getOrCreateEncryptionKey(): string {
        $key_file = dirname(dirname(__FILE__)) . '/config/master.key';
        
        if (file_exists($key_file)) {
            return trim(file_get_contents($key_file));
        }
        
        // Generate new key
        $key = bin2hex(random_bytes(32));
        $key_dir = dirname($key_file);
        
        if (!is_dir($key_dir)) {
            mkdir($key_dir, 0700, true);
        }
        
        file_put_contents($key_file, $key);
        chmod($key_file, 0600);
        
        return $key;
    }
    
    /**
     * Load API keys from storage
     */
    private function loadKeys(): void {
        if (file_exists($this->storage_file)) {
            $content = file_get_contents($this->storage_file);
            $data = json_decode($content, true);
            $this->keys = $data['keys'] ?? [];
        } else {
            $this->keys = [];
            $this->saveKeys();
        }
    }
    
    /**
     * Save API keys to storage
     */
    private function saveKeys(): void {
        $dir = dirname($this->storage_file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $data = [
            'keys' => $this->keys,
            'updated_at' => date('Y-m-d H:i:s'),
            'total_keys' => count($this->keys)
        ];
        
        file_put_contents($this->storage_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * Encrypt API key value
     * 
     * @param string $value
     * @return string
     */
    private function encrypt(string $value): string {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, 'aes-256-cbc', hex2bin($this->encryption_key), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt API key value
     * 
     * @param string $encrypted
     * @return string
     */
    private function decrypt(string $encrypted): string {
        $data = base64_decode($encrypted);
        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        return openssl_decrypt($ciphertext, 'aes-256-cbc', hex2bin($this->encryption_key), OPENSSL_RAW_DATA, $iv);
    }
    
    /**
     * Generate new API key
     * 
     * @param string $name
     * @param string $provider
     * @param string $plan
     * @param array $options
     * @return array|false
     */
    public function createKey(string $name, string $provider, string $plan = 'basic', array $options = []) {
        // Generate random API key
        $key_value = 'am_' . bin2hex(random_bytes(24));
        
        $key_data = [
            'id' => uniqid('key_', true),
            'name' => $name,
            'provider' => $provider,
            'key_value' => $this->encrypt($key_value),
            'key_preview' => substr($key_value, 0, 10) . '...',
            'plan' => $plan,
            'status' => 'active',
            'rate_limit' => $options['rate_limit'] ?? $this->rate_limits[$plan]['limit'],
            'rate_window' => $options['rate_window'] ?? $this->rate_limits[$plan]['window'],
            'permissions' => $options['permissions'] ?? ['read', 'write'],
            'allowed_ips' => $options['allowed_ips'] ?? [],
            'allowed_endpoints' => $options['allowed_endpoints'] ?? [],
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => $options['expires_at'] ?? date('Y-m-d H:i:s', strtotime('+1 year')),
            'last_used' => null,
            'usage_count' => 0,
            'metadata' => $options['metadata'] ?? []
        ];
        
        $this->keys[] = $key_data;
        $this->saveKeys();
        
        return [
            'key' => $key_value,
            'data' => $key_data
        ];
    }
    
    /**
     * Validate API key
     * 
     * @param string $key_value
     * @param string $endpoint
     * @param string $ip
     * @return array|false
     */
    public function validateKey(string $key_value, string $endpoint = '', string $ip = '') {
        foreach ($this->keys as &$key) {
            $decrypted = $this->decrypt($key['key_value']);
            
            if ($decrypted === $key_value) {
                // Check status
                if ($key['status'] !== 'active') {
                    return ['valid' => false, 'reason' => 'Key is ' . $key['status']];
                }
                
                // Check expiration
                if (strtotime($key['expires_at']) < time()) {
                    return ['valid' => false, 'reason' => 'Key has expired'];
                }
                
                // Check IP whitelist
                if (!empty($key['allowed_ips']) && $ip) {
                    if (!$this->ipInList($ip, $key['allowed_ips'])) {
                        return ['valid' => false, 'reason' => 'IP not allowed'];
                    }
                }
                
                // Check endpoint permissions
                if (!empty($key['allowed_endpoints']) && $endpoint) {
                    if (!$this->endpointAllowed($endpoint, $key['allowed_endpoints'])) {
                        return ['valid' => false, 'reason' => 'Endpoint not allowed'];
                    }
                }
                
                // Update usage stats
                $key['last_used'] = date('Y-m-d H:i:s');
                $key['usage_count']++;
                $this->saveKeys();
                
                return [
                    'valid' => true,
                    'key_id' => $key['id'],
                    'name' => $key['name'],
                    'provider' => $key['provider'],
                    'plan' => $key['plan'],
                    'rate_limit' => $key['rate_limit'],
                    'rate_window' => $key['rate_window'],
                    'permissions' => $key['permissions']
                ];
            }
        }
        
        return ['valid' => false, 'reason' => 'Invalid API key'];
    }
    
    /**
     * Get all API keys
     * 
     * @param bool $include_values
     * @return array
     */
    public function getAllKeys(bool $include_values = false): array {
        $keys = [];
        
        foreach ($this->keys as $key) {
            $key_data = $key;
            unset($key_data['key_value']);
            
            if ($include_values) {
                $key_data['key_value'] = $this->decrypt($key['key_value']);
            }
            
            $keys[] = $key_data;
        }
        
        return $keys;
    }
    
    /**
     * Get key by ID
     * 
     * @param string $id
     * @param bool $include_value
     * @return array|null
     */
    public function getKeyById(string $id, bool $include_value = false): ?array {
        foreach ($this->keys as $key) {
            if ($key['id'] === $id) {
                $key_data = $key;
                unset($key_data['key_value']);
                
                if ($include_value) {
                    $key_data['key_value'] = $this->decrypt($key['key_value']);
                }
                
                return $key_data;
            }
        }
        
        return null;
    }
    
    /**
     * Update API key
     * 
     * @param string $id
     * @param array $updates
     * @return bool
     */
    public function updateKey(string $id, array $updates): bool {
        foreach ($this->keys as &$key) {
            if ($key['id'] === $id) {
                $allowed_fields = ['name', 'status', 'plan', 'rate_limit', 'rate_window', 'permissions', 'allowed_ips', 'allowed_endpoints', 'expires_at', 'metadata'];
                
                foreach ($updates as $field => $value) {
                    if (in_array($field, $allowed_fields)) {
                        $key[$field] = $value;
                    }
                }
                
                $key['updated_at'] = date('Y-m-d H:i:s');
                $this->saveKeys();
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Regenerate API key (new value)
     * 
     * @param string $id
     * @return string|false New key value
     */
    public function regenerateKey(string $id) {
        foreach ($this->keys as &$key) {
            if ($key['id'] === $id) {
                $new_value = 'am_' . bin2hex(random_bytes(24));
                $key['key_value'] = $this->encrypt($new_value);
                $key['key_preview'] = substr($new_value, 0, 10) . '...';
                $key['regenerated_at'] = date('Y-m-d H:i:s');
                $key['regeneration_count'] = ($key['regeneration_count'] ?? 0) + 1;
                $this->saveKeys();
                return $new_value;
            }
        }
        
        return false;
    }
    
    /**
     * Delete API key
     * 
     * @param string $id
     * @return bool
     */
    public function deleteKey(string $id): bool {
        foreach ($this->keys as $index => $key) {
            if ($key['id'] === $id) {
                array_splice($this->keys, $index, 1);
                $this->saveKeys();
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Revoke API key (set status to revoked)
     * 
     * @param string $id
     * @return bool
     */
    public function revokeKey(string $id): bool {
        return $this->updateKey($id, ['status' => 'revoked']);
    }
    
    /**
     * Activate API key
     * 
     * @param string $id
     * @return bool
     */
    public function activateKey(string $id): bool {
        return $this->updateKey($id, ['status' => 'active']);
    }
    
    /**
     * Suspend API key
     * 
     * @param string $id
     * @return bool
     */
    public function suspendKey(string $id): bool {
        return $this->updateKey($id, ['status' => 'suspended']);
    }
    
    /**
     * Get key usage statistics
     * 
     * @param string $id
     * @return array
     */
    public function getKeyStats(string $id): array {
        $key = $this->getKeyById($id);
        
        if (!$key) {
            return [];
        }
        
        // Get usage from logs
        $logs_file = dirname(dirname(__FILE__)) . '/data/logs.json';
        $logs = [];
        
        if (file_exists($logs_file)) {
            $all_logs = json_decode(file_get_contents($logs_file), true);
            
            foreach ($all_logs as $log) {
                if (isset($log['api_key_id']) && $log['api_key_id'] === $id) {
                    $logs[] = $log;
                }
            }
        }
        
        // Calculate stats
        $total_calls = count($logs);
        $successful = count(array_filter($logs, function($log) {
            return isset($log['status_code']) && $log['status_code'] >= 200 && $log['status_code'] < 300;
        }));
        
        $avg_response_time = $total_calls > 0 ? array_sum(array_column($logs, 'response_time')) / $total_calls : 0;
        
        // Group by date
        $by_date = [];
        foreach ($logs as $log) {
            $date = substr($log['timestamp'] ?? $log['created_at'] ?? '', 0, 10);
            if (!isset($by_date[$date])) {
                $by_date[$date] = 0;
            }
            $by_date[$date]++;
        }
        
        return [
            'key_id' => $id,
            'name' => $key['name'],
            'total_calls' => $total_calls,
            'successful_calls' => $successful,
            'failed_calls' => $total_calls - $successful,
            'success_rate' => $total_calls > 0 ? round(($successful / $total_calls) * 100, 2) : 0,
            'avg_response_time' => round($avg_response_time, 2),
            'last_used' => $key['last_used'],
            'usage_by_date' => $by_date,
            'recent_logs' => array_slice($logs, 0, 10)
        ];
    }
    
    /**
     * Check rate limit for key
     * 
     * @param string $key_id
     * @return array
     */
    public function checkRateLimit(string $key_id): array {
        $key = $this->getKeyById($key_id);
        
        if (!$key) {
            return ['allowed' => false, 'reason' => 'Key not found'];
        }
        
        $rate_file = dirname(dirname(__FILE__)) . '/cache/rate-limits/key_' . md5($key_id) . '.json';
        $now = time();
        $window = $key['rate_window'];
        $limit = $key['rate_limit'];
        
        $data = ['requests' => []];
        if (file_exists($rate_file)) {
            $data = json_decode(file_get_contents($rate_file), true);
            $data['requests'] = array_filter($data['requests'], function($timestamp) use ($now, $window) {
                return $timestamp > ($now - $window);
            });
        }
        
        $current = count($data['requests']);
        $allowed = $current < $limit;
        
        if ($allowed) {
            $data['requests'][] = $now;
            file_put_contents($rate_file, json_encode($data));
        }
        
        return [
            'allowed' => $allowed,
            'limit' => $limit,
            'window' => $window,
            'current' => $current,
            'remaining' => max(0, $limit - ($allowed ? $current + 1 : $current)),
            'reset_in' => $allowed ? 0 : ($window - ($now - min($data['requests'])))
        ];
    }
    
    /**
     * Check if IP is in allowed list
     * 
     * @param string $ip
     * @param array $allowed_list
     * @return bool
     */
    private function ipInList(string $ip, array $allowed_list): bool {
        foreach ($allowed_list as $allowed) {
            if (strpos($allowed, '/') !== false) {
                // CIDR notation
                if ($this->ipInCidr($ip, $allowed)) {
                    return true;
                }
            } elseif ($ip === $allowed) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if IP is in CIDR range
     * 
     * @param string $ip
     * @param string $cidr
     * @return bool
     */
    private function ipInCidr(string $ip, string $cidr): bool {
        list($subnet, $mask) = explode('/', $cidr);
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip_long = ip2long($ip);
            $subnet_long = ip2long($subnet);
            $mask_long = ~((1 << (32 - $mask)) - 1);
            
            return ($ip_long & $mask_long) == ($subnet_long & $mask_long);
        }
        
        return false;
    }
    
    /**
     * Check if endpoint is allowed
     * 
     * @param string $endpoint
     * @param array $allowed_endpoints
     * @return bool
     */
    private function endpointAllowed(string $endpoint, array $allowed_endpoints): bool {
        foreach ($allowed_endpoints as $allowed) {
            if (strpos($allowed, '*') !== false) {
                $pattern = '/^' . str_replace('*', '.*', preg_quote($allowed, '/')) . '$/';
                if (preg_match($pattern, $endpoint)) {
                    return true;
                }
            } elseif ($endpoint === $allowed) {
                return true;
            }
        }
        
        return empty($allowed_endpoints);
    }
    
    /**
     * Get statistics summary
     * 
     * @return array
     */
    public function getStats(): array {
        $total = count($this->keys);
        $active = count(array_filter($this->keys, function($k) {
            return $k['status'] === 'active' && strtotime($k['expires_at']) > time();
        }));
        $revoked = count(array_filter($this->keys, function($k) {
            return $k['status'] === 'revoked';
        }));
        $suspended = count(array_filter($this->keys, function($k) {
            return $k['status'] === 'suspended';
        }));
        $expired = count(array_filter($this->keys, function($k) {
            return strtotime($k['expires_at']) < time() && $k['status'] === 'active';
        }));
        
        $total_usage = array_sum(array_column($this->keys, 'usage_count'));
        
        return [
            'total_keys' => $total,
            'active_keys' => $active,
            'revoked_keys' => $revoked,
            'suspended_keys' => $suspended,
            'expired_keys' => $expired,
            'total_usage' => $total_usage,
            'by_plan' => $this->countByPlan(),
            'by_provider' => $this->countByProvider()
        ];
    }
    
    /**
     * Count keys by plan
     * 
     * @return array
     */
    private function countByPlan(): array {
        $counts = [];
        foreach ($this->keys as $key) {
            $plan = $key['plan'];
            $counts[$plan] = ($counts[$plan] ?? 0) + 1;
        }
        return $counts;
    }
    
    /**
     * Count keys by provider
     * 
     * @return array
     */
    private function countByProvider(): array {
        $counts = [];
        foreach ($this->keys as $key) {
            $provider = $key['provider'];
            $counts[$provider] = ($counts[$provider] ?? 0) + 1;
        }
        return $counts;
    }
    
    /**
     * Clean expired keys (set status to expired)
     * 
     * @return int Number of keys expired
     */
    public function cleanExpiredKeys(): int {
        $expired = 0;
        $now = time();
        
        foreach ($this->keys as &$key) {
            if ($key['status'] === 'active' && strtotime($key['expires_at']) < $now) {
                $key['status'] = 'expired';
                $expired++;
            }
        }
        
        if ($expired > 0) {
            $this->saveKeys();
        }
        
        return $expired;
    }
}