<?php
/**
 * API Master Core Class
 * BU BİR WORDPRESS MODÜLÜDÜR, PLUGIN DEĞİLDİR
 */

if (!defined('ABSPATH')) {
    // Normal PHP çalışması
}

class APIMaster_Core
{
    private $logger;
    private $cache;
    private $database;
    private $moduleDir;
    private $configDir;
    private $settingsFile;
    private $version = '3.0.0';

    public function __construct()
    {
        $this->moduleDir = API_MASTER_MODULE_DIR;
        $this->configDir = API_MASTER_CONFIG_DIR;
        $this->settingsFile = $this->configDir . '/settings.json';
        
        // Logger'ı dene, yoksa oluştur
        if (class_exists('APIMaster_Logger')) {
            $this->logger = new APIMaster_Logger();
        }
        
        // Cache'i dene, yoksa oluştur
        if (class_exists('APIMaster_Cache')) {
            $this->cache = new APIMaster_Cache();
        }
        
        // Database'i dene
        if (class_exists('APIMaster_Database')) {
            $this->database = new APIMaster_Database();
        }
        
        $this->init();
    }

    private function init()
    {
        $this->ensureDirectories();
        $this->logInfo('Core initialized', ['version' => $this->version]);
    }
    
    private function ensureDirectories()
    {
        $dirs = [API_MASTER_DATA_DIR, API_MASTER_LOG_DIR, API_MASTER_CACHE_DIR];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    public function getSettings()
    {
        if (!file_exists($this->settingsFile)) {
            return $this->getDefaultSettings();
        }
        
        $content = file_get_contents($this->settingsFile);
        $settings = json_decode($content, true);
        
        return is_array($settings) ? $settings : $this->getDefaultSettings();
    }
    
    private function getDefaultSettings()
    {
        return [
            'version' => $this->version,
            'debug_mode' => false,
            'log_level' => 'info',
            'cache_enabled' => true,
            'cache_ttl' => 3600,
            'rate_limit_enabled' => true,
            'rate_limit_per_minute' => 60,
            'learning_enabled' => true,
            'default_provider' => 'openai',
            'default_model' => 'gpt-3.5-turbo',
            'updated_at' => time()
        ];
    }
    
    public function saveSettings($settings)
    {
        $settings['updated_at'] = time();
        $content = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (!is_dir($this->configDir)) {
            mkdir($this->configDir, 0755, true);
        }
        
        return file_put_contents($this->settingsFile, $content, LOCK_EX) !== false;
    }

    public function systemStatus()
    {
        return [
            'php_version' => PHP_VERSION,
            'php_version_ok' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'curl_enabled' => function_exists('curl_version'),
            'json_enabled' => function_exists('json_decode'),
            'openssl_enabled' => extension_loaded('openssl'),
            'cache_writable' => is_writable(API_MASTER_CACHE_DIR),
            'logs_writable' => is_writable(API_MASTER_LOG_DIR),
            'config_writable' => is_writable(API_MASTER_CONFIG_DIR),
            'data_writable' => is_writable(API_MASTER_DATA_DIR),
            'module_dir' => API_MASTER_MODULE_DIR
        ];
    }

    public function healthCheck()
    {
        $status = [
            'status' => 'healthy',
            'version' => $this->version,
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => []
        ];
        
        $status['checks']['php_version'] = [
            'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'pass' : 'fail',
            'value' => PHP_VERSION
        ];
        
        $status['checks']['cache_dir'] = [
            'status' => is_writable(API_MASTER_CACHE_DIR) ? 'pass' : 'fail',
            'value' => API_MASTER_CACHE_DIR
        ];
        
        $status['checks']['logs_dir'] = [
            'status' => is_writable(API_MASTER_LOG_DIR) ? 'pass' : 'fail',
            'value' => API_MASTER_LOG_DIR
        ];
        
        foreach ($status['checks'] as $check) {
            if ($check['status'] === 'fail') {
                $status['status'] = 'unhealthy';
                break;
            }
        }
        
        return $status;
    }
    
    private function logInfo($message, $context = [])
    {
        if ($this->logger) {
            $this->logger->info($message, $context);
        }
    }
}