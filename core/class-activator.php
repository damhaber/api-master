<?php
if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_Activator
{
    private $moduleDir;
    private $configDir;
    private $dataDir;
    private $logDir;
    private $cacheDir;
    private $settingsFile;
    private $logFile;
    private $config;

    public function __construct()
    {
        $this->moduleDir = dirname(__DIR__, 1);
        $this->configDir = $this->moduleDir . '/config';
        $this->dataDir = $this->moduleDir . '/data';
        $this->logDir = $this->moduleDir . '/logs';
        $this->cacheDir = $this->moduleDir . '/cache';
        $this->settingsFile = $this->configDir . '/settings.json';
        $this->logFile = $this->logDir . '/api-master.log';
        
        $this->loadConfig();
    }

    private function loadConfig()
    {
        if (file_exists($this->settingsFile)) {
            $content = file_get_contents($this->settingsFile);
            $this->config = json_decode($content, true);
        }
        
        if (!is_array($this->config)) {
            $this->config = [];
        }
    }

    public function activate()
    {
        $this->writeLog('API Master modülü aktifleştiriliyor...');
        
        $this->createDirectories();
        $this->createDefaultSettings();
        $this->createDefaultProviders();
        $this->clearCache();
        $this->afterActivation();
        
        $this->writeLog('API Master modülü başarıyla aktifleştirildi');
        
        return true;
    }

    private function createDirectories()
    {
        $directories = [
            $this->logDir,
            $this->cacheDir,
            $this->dataDir,
            $this->configDir,
            $this->dataDir . '/stats',
            $this->dataDir . '/learning',
            $this->moduleDir . '/temp',
            $this->moduleDir . '/backups'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                $this->addSecurityFiles($dir);
                $this->writeLog("Dizin oluşturuldu: {$dir}");
            }
        }
        
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
            chmod($this->logFile, 0644);
        }
    }

    private function addSecurityFiles($directory)
    {
        $htaccess = $directory . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Order Deny,Allow\nDeny from all\n");
        }
        
        $index = $directory . '/index.html';
        if (!file_exists($index)) {
            file_put_contents($index, "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Access Denied</h1></body></html>");
        }
    }

    private function createDefaultSettings()
    {
        $defaultSettings = [
            'version' => '1.0.0',
            'debug_mode' => false,
            'log_level' => 'info',
            'cache_enabled' => true,
            'cache_ttl' => 3600,
            'rate_limit_enabled' => true,
            'rate_limit_per_minute' => 60,
            'rate_limit_per_hour' => 1000,
            'learning_enabled' => true,
            'auto_select_provider' => true,
            'fallback_enabled' => true,
            'max_retries' => 3,
            'timeout' => 30,
            'default_provider' => 'openai',
            'default_model' => 'gpt-3.5-turbo',
            'max_tokens' => 4096,
            'temperature' => 0.7,
            'system_prompt' => 'Sen yardımcı bir yapay zeka asistanısın.',
            'vector_memory_enabled' => false,
            'performance_tracking' => true,
            'analytics_enabled' => true,
            'updated_at' => time()
        ];
        
        $existingSettings = [];
        if (file_exists($this->settingsFile)) {
            $content = file_get_contents($this->settingsFile);
            $existingSettings = json_decode($content, true);
            if (!is_array($existingSettings)) {
                $existingSettings = [];
            }
        }
        
        $settings = array_merge($defaultSettings, $existingSettings);
        
        file_put_contents($this->settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        
        $this->writeLog('Varsayılan ayarlar oluşturuldu');
    }

    private function createDefaultProviders()
    {
        $providersFile = $this->configDir . '/providers.json';
        
        $defaultProviders = [
            'openai' => [
                'name' => 'OpenAI',
                'active' => true,
                'description' => 'GPT-4, GPT-3.5 Turbo ve DALL-E modelleri',
                'website' => 'https://openai.com',
                'models' => ['gpt-4', 'gpt-3.5-turbo', 'dall-e-3'],
                'supports' => ['chat', 'completion', 'image']
            ],
            'deepseek' => [
                'name' => 'DeepSeek',
                'active' => true,
                'description' => 'DeepSeek AI modelleri',
                'website' => 'https://deepseek.com',
                'models' => ['deepseek-chat', 'deepseek-coder'],
                'supports' => ['chat', 'completion']
            ],
            'gemini' => [
                'name' => 'Google Gemini',
                'active' => true,
                'description' => 'Google\'ın yapay zeka modelleri',
                'website' => 'https://deepmind.google/technologies/gemini/',
                'models' => ['gemini-pro', 'gemini-pro-vision'],
                'supports' => ['chat', 'completion', 'vision']
            ],
            'claude' => [
                'name' => 'Anthropic Claude',
                'active' => false,
                'description' => 'Claude AI asistan',
                'website' => 'https://anthropic.com',
                'models' => ['claude-3-opus', 'claude-3-sonnet', 'claude-3-haiku'],
                'supports' => ['chat', 'completion']
            ],
            'mistral' => [
                'name' => 'Mistral AI',
                'active' => false,
                'description' => 'Mistral AI modelleri',
                'website' => 'https://mistral.ai',
                'models' => ['mistral-tiny', 'mistral-small', 'mistral-medium'],
                'supports' => ['chat', 'completion']
            ],
            'cohere' => [
                'name' => 'Cohere',
                'active' => false,
                'description' => 'Cohere AI modelleri',
                'website' => 'https://cohere.com',
                'models' => ['command', 'generate'],
                'supports' => ['chat', 'completion']
            ]
        ];
        
        $existingProviders = [];
        if (file_exists($providersFile)) {
            $content = file_get_contents($providersFile);
            $existingProviders = json_decode($content, true);
            if (!is_array($existingProviders)) {
                $existingProviders = [];
            }
        }
        
        $providers = array_merge($defaultProviders, $existingProviders);
        
        file_put_contents($providersFile, json_encode($providers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        
        $this->writeLog('Varsayılan provider\'lar oluşturuldu');
    }

    private function clearCache()
    {
        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && basename($file) !== '.htaccess' && basename($file) !== 'index.html') {
                    unlink($file);
                }
            }
        }
        
        $this->writeLog('Önbellek temizlendi');
    }

    private function afterActivation()
    {
        $this->scheduleCronJobs();
        $this->registerWebhooks();
        
        $activationTime = time();
        file_put_contents($this->dataDir . '/activation-time.txt', $activationTime, LOCK_EX);
    }

    private function scheduleCronJobs()
    {
        $cronJobs = [
            'cleanup_logs' => '0 0 * * *',
            'clear_old_cache' => '0 */6 * * *',
            'update_metrics' => '*/5 * * * *'
        ];
        
        $cronFile = $this->configDir . '/cron-jobs.json';
        file_put_contents($cronFile, json_encode($cronJobs, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function registerWebhooks()
    {
        $webhooks = [
            [
                'name' => 'chat_webhook',
                'endpoint' => '/webhook/chat',
                'method' => 'POST',
                'active' => true
            ],
            [
                'name' => 'api_status',
                'endpoint' => '/webhook/status',
                'method' => 'GET',
                'active' => true
            ]
        ];
        
        $webhookFile = $this->configDir . '/webhooks.json';
        file_put_contents($webhookFile, json_encode($webhooks, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function writeLog($message)
    {
        $logEntry = '[' . date('Y-m-d H:i:s') . "] [INFO] {$message}\n";
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public static function isActivated()
    {
        $moduleDir = dirname(__DIR__, 1);
        $activationFile = $moduleDir . '/data/activation-time.txt';
        
        if (!file_exists($activationFile)) {
            return false;
        }
        
        $activationTime = (int)file_get_contents($activationFile);
        return $activationTime > 0;
    }

    public function getActivationInfo()
    {
        return [
            'activated' => self::isActivated(),
            'version' => $this->config['version'] ?? '1.0.0',
            'php_version' => PHP_VERSION,
            'directories' => $this->checkDirectories()
        ];
    }

    private function checkDirectories()
    {
        $directories = [
            'logs' => $this->logDir,
            'cache' => $this->cacheDir,
            'data' => $this->dataDir,
            'config' => $this->configDir
        ];
        
        $status = [];
        foreach ($directories as $name => $path) {
            $status[$name] = [
                'exists' => is_dir($path),
                'writable' => is_writable($path)
            ];
        }
        
        return $status;
    }
}