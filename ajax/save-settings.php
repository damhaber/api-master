<?php
if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_SaveSettings
{
    private $moduleDir;
    private $configDir;
    private $settingsFile;
    private $logDir;
    private $cacheDir;

    public function __construct()
    {
        $this->moduleDir = dirname(__DIR__, 2);
        $this->configDir = $this->moduleDir . '/config';
        $this->settingsFile = $this->configDir . '/settings.json';
        $this->logDir = $this->moduleDir . '/logs';
        $this->cacheDir = $this->moduleDir . '/cache';
        
        $this->ensureDirectories();
        $this->handleRequest();
    }

    private function ensureDirectories()
    {
        $dirs = [$this->configDir, $this->logDir, $this->cacheDir];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    private function handleRequest()
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(false, 'Sadece POST istekleri kabul edilir.');
        }
        
        $settings = $this->validateAndPrepareSettings();
        $this->saveSettings($settings);
    }

    private function validateAndPrepareSettings()
    {
        $validLogLevels = ['debug', 'info', 'warning', 'error'];
        $logLevel = isset($_POST['log_level']) ? $_POST['log_level'] : 'info';
        
        if (!in_array($logLevel, $validLogLevels)) {
            $logLevel = 'info';
        }
        
        $defaultProvider = isset($_POST['default_provider']) ? $this->sanitizeProvider($_POST['default_provider']) : 'openai';
        
        return [
            'default_timeout' => isset($_POST['default_timeout']) ? intval($_POST['default_timeout']) : 30,
            'max_retries' => isset($_POST['max_retries']) ? intval($_POST['max_retries']) : 3,
            'cache_ttl' => isset($_POST['cache_ttl']) ? intval($_POST['cache_ttl']) : 60,
            'enable_learning' => isset($_POST['enable_learning']) ? true : false,
            'enable_vector' => isset($_POST['enable_vector']) ? true : false,
            'log_level' => $logLevel,
            'default_provider' => $defaultProvider,
            'updated_at' => time()
        ];
    }

    private function sanitizeProvider($provider)
    {
        $validProviders = ['openai', 'deepseek', 'gemini', 'claude', 'cohere', 'mistral', 'anthropic'];
        $provider = strtolower(trim($provider));
        
        if (in_array($provider, $validProviders)) {
            return $provider;
        }
        
        return 'openai';
    }

    private function saveSettings($settings)
    {
        $existingSettings = $this->loadSettings();
        $mergedSettings = array_merge($existingSettings, $settings);
        
        $content = json_encode($mergedSettings, JSON_PRETTY_PRINT);
        $result = file_put_contents($this->settingsFile, $content, LOCK_EX);
        
        if ($result === false) {
            $this->writeLog('Ayarlar kaydedilemedi: ' . $this->settingsFile, 'error');
            $this->jsonResponse(false, 'Ayarlar kaydedilemedi. Config dizini yazılabilir olmalıdır.');
        }
        
        $this->writeLog('Ayarlar kaydedildi: ' . implode(', ', array_keys($settings)));
        $this->clearSettingsCache();
        
        $this->jsonResponse(true, 'Ayarlar başarıyla kaydedildi', [
            'saved' => array_keys($settings),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    private function loadSettings()
    {
        if (!file_exists($this->settingsFile)) {
            return [];
        }
        
        $content = file_get_contents($this->settingsFile);
        $data = json_decode($content, true);
        
        return is_array($data) ? $data : [];
    }

    private function clearSettingsCache()
    {
        $cacheFile = $this->cacheDir . '/settings.cache';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    private function writeLog($message, $level = 'info')
    {
        $logFile = $this->logDir . '/api-master.log';
        $logEntry = '[' . date('Y-m-d H:i:s') . "] [{$level}] {$message}\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function jsonResponse($success, $message, $data = [])
    {
        $response = ['success' => $success, 'message' => $message];
        if (!empty($data)) {
            $response['data'] = $data;
        }
        echo json_encode($response);
        exit;
    }
}

new APIMaster_SaveSettings();
?>