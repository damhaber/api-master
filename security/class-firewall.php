<?php
/**
 * API Master - Firewall
 * 
 * @package APIMaster
 * @subpackage Security
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class APIMaster_Firewall
 * 
 * Güvenlik duvarı ve saldırı önleme
 */
class APIMaster_Firewall {
    
    /**
     * @var array Blacklist'teki IP'ler
     */
    private $blacklistedIps = [];
    
    /**
     * @var array Whitelist'teki IP'ler
     */
    private $whitelistedIps = [];
    
    /**
     * @var array Şüpheli pattern'ler
     */
    private $suspiciousPatterns = [
        '/union.*select/i',
        '/select.*from/i',
        '/insert.*into/i',
        '/delete.*from/i',
        '/drop.*table/i',
        '/exec\(/i',
        '/system\(/i',
        '/eval\(/i',
        '/base64_decode/i',
        '/<script/i',
        '/javascript:/i',
        '/onload=/i',
        '/onerror=/i'
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->loadBlacklist();
        $this->loadWhitelist();
    }
    
    /**
     * Blacklist'i yükle
     */
    private function loadBlacklist() {
        $file = dirname(dirname(__FILE__)) . '/config/blacklist.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            $this->blacklistedIps = $data['ips'] ?? [];
        }
    }
    
    /**
     * Whitelist'i yükle
     */
    private function loadWhitelist() {
        $file = dirname(dirname(__FILE__)) . '/config/whitelist.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            $this->whitelistedIps = $data['ips'] ?? [];
        }
    }
    
    /**
     * IP adresini kontrol et
     * 
     * @param string|null $ip
     * @return bool
     */
    public function checkIp($ip = null) {
        $ip = $ip ?? $this->getClientIp();
        
        // Whitelist kontrolü
        if (in_array($ip, $this->whitelistedIps)) {
            return true;
        }
        
        // Blacklist kontrolü
        if (in_array($ip, $this->blacklistedIps)) {
            return false;
        }
        
        // CIDR formatındaki blacklist'leri kontrol et
        foreach ($this->blacklistedIps as $blacklisted) {
            if (strpos($blacklisted, '/') !== false) {
                if ($this->ipInCidr($ip, $blacklisted)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * İstek içeriğini kontrol et
     * 
     * @param string $content
     * @return bool
     */
    public function checkContent($content) {
        foreach ($this->suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $this->logSuspiciousActivity($content, $pattern);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Headers'ları kontrol et
     * 
     * @param array $headers
     * @return bool
     */
    public function checkHeaders($headers) {
        // User-Agent kontrolü
        if (isset($headers['User-Agent'])) {
            $ua = $headers['User-Agent'];
            
            // Boş User-Agent
            if (empty(trim($ua))) {
                return false;
            }
            
            // Şüpheli User-Agent
            $suspiciousUa = ['sqlmap', 'nikto', 'nmap', 'masscan', 'zgrab'];
            foreach ($suspiciousUa as $sua) {
                if (stripos($ua, $sua) !== false) {
                    $this->logSuspiciousActivity($ua, 'suspicious_user_agent');
                    return false;
                }
            }
        }
        
        // Referer kontrolü (opsiyonel)
        if (isset($headers['Referer'])) {
            $referer = $headers['Referer'];
            if (strlen($referer) > 2000) {
                return false; // Çok uzun referer
            }
        }
        
        return true;
    }
    
    /**
     * Rate limit kontrolü
     * 
     * @param string $identifier
     * @param int $limit
     * @param int $window
     * @return bool
     */
    public function checkRateLimit($identifier, $limit = 60, $window = 60) {
        $cacheDir = dirname(dirname(__FILE__)) . '/cache/rate-limits/';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $file = $cacheDir . md5($identifier) . '.json';
        $now = time();
        
        $data = ['requests' => []];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            // Eski kayıtları temizle
            $data['requests'] = array_filter($data['requests'], function($timestamp) use ($now, $window) {
                return $timestamp > ($now - $window);
            });
        }
        
        if (count($data['requests']) >= $limit) {
            return false;
        }
        
        $data['requests'][] = $now;
        file_put_contents($file, json_encode($data));
        
        return true;
    }
    
    /**
     * IP'yi blacklist'e ekle
     * 
     * @param string $ip
     * @param string $reason
     * @return bool
     */
    public function addToBlacklist($ip, $reason = '') {
        if (!in_array($ip, $this->blacklistedIps)) {
            $this->blacklistedIps[] = $ip;
            $this->saveBlacklist();
            
            // Log'la
            $this->logSecurityEvent('ip_blacklisted', [
                'ip' => $ip,
                'reason' => $reason
            ]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * IP'yi whitelist'e ekle
     * 
     * @param string $ip
     * @return bool
     */
    public function addToWhitelist($ip) {
        if (!in_array($ip, $this->whitelistedIps)) {
            $this->whitelistedIps[] = $ip;
            $this->saveWhitelist();
            return true;
        }
        
        return false;
    }
    
    /**
     * Blacklist'i kaydet
     */
    private function saveBlacklist() {
        $file = dirname(dirname(__FILE__)) . '/config/blacklist.json';
        $data = ['ips' => $this->blacklistedIps, 'updated_at' => date('Y-m-d H:i:s')];
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    /**
     * Whitelist'i kaydet
     */
    private function saveWhitelist() {
        $file = dirname(dirname(__FILE__)) . '/config/whitelist.json';
        $data = ['ips' => $this->whitelistedIps, 'updated_at' => date('Y-m-d H:i:s')];
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    /**
     * Şüpheli aktiviteyi logla
     * 
     * @param string $content
     * @param string $pattern
     */
    private function logSuspiciousActivity($content, $pattern) {
        $log = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $this->getClientIp(),
            'pattern' => $pattern,
            'content' => substr($content, 0, 500),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $logFile = dirname(dirname(__FILE__)) . '/logs/suspicious.log';
        file_put_contents($logFile, json_encode($log) . PHP_EOL, FILE_APPEND);
    }
    
    /**
     * Güvenlik olayını logla
     * 
     * @param string $event
     * @param array $data
     */
    private function logSecurityEvent($event, $data) {
        $log = array_merge([
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $this->getClientIp()
        ], $data);
        
        $logFile = dirname(dirname(__FILE__)) . '/logs/security.log';
        file_put_contents($logFile, json_encode($log) . PHP_EOL, FILE_APPEND);
    }
    
    /**
     * İstemci IP'sini al
     * 
     * @return string
     */
    private function getClientIp() {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (isset($_SERVER[$header])) {
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
     * IP'nin CIDR içinde olup olmadığını kontrol et
     * 
     * @param string $ip
     * @param string $cidr
     * @return bool
     */
    private function ipInCidr($ip, $cidr) {
        list($subnet, $mask) = explode('/', $cidr);
        
        if ((filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) &&
            (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = ~((1 << (32 - $mask)) - 1);
            
            return ($ipLong & $maskLong) == ($subnetLong & $maskLong);
        }
        
        return false;
    }
    
    /**
     * Firewall istatistikleri
     * 
     * @return array
     */
    public function getStats() {
        return [
            'blacklisted_ips' => count($this->blacklistedIps),
            'whitelisted_ips' => count($this->whitelistedIps),
            'suspicious_patterns' => count($this->suspiciousPatterns)
        ];
    }
}