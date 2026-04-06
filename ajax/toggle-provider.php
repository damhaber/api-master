<?php
if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_ToggleProvider
{
    private $moduleDir;
    private $providersFile;
    private $logFile;
    private $validProviders;

    public function __construct()
    {
        $this->moduleDir = dirname(__DIR__, 2);
        $this->providersFile = $this->moduleDir . '/config/providers.json';
        $this->logFile = $this->moduleDir . '/logs/api-master.log';
        $this->validProviders = ['openai', 'deepseek', 'gemini', 'claude', 'cohere', 'mistral', 'anthropic'];
        
        $this->ensureDirectories();
        $this->handleRequest();
    }

    private function ensureDirectories()
    {
        $configDir = dirname($this->providersFile);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    private function handleRequest()
    {
        header('Content-Type: application/json');
        
        $provider = isset($_POST['provider']) ? trim($_POST['provider']) : '';
        $active = isset($_POST['active']) ? (bool)$_POST['active'] : false;
        
        if (empty($provider)) {
            $this->jsonResponse(false, 'Provider belirtilmedi');
        }
        
        if (!in_array($provider, $this->validProviders)) {
            $this->jsonResponse(false, 'Geçersiz provider: ' . $provider);
        }
        
        $providersConfig = $this->loadProvidersConfig();
        
        if (!isset($providersConfig[$provider])) {
            $providersConfig[$provider] = [];
        }
        
        $providersConfig[$provider]['active'] = $active;
        $providersConfig[$provider]['updated'] = time();
        
        $this->saveProvidersConfig($providersConfig);
        
        $status = $active ? 'aktif edildi' : 'pasif edildi';
        $this->writeLog("Provider {$provider} {$status}");
        
        $this->jsonResponse(true, "Provider {$provider} {$status}", [
            'provider' => $provider,
            'active' => $active,
            'updated' => time()
        ]);
    }

    private function loadProvidersConfig()
    {
        if (!file_exists($this->providersFile)) {
            return [];
        }
        
        $content = file_get_contents($this->providersFile);
        $data = json_decode($content, true);
        
        return is_array($data) ? $data : [];
    }

    private function saveProvidersConfig($config)
    {
        $content = json_encode($config, JSON_PRETTY_PRINT);
        file_put_contents($this->providersFile, $content, LOCK_EX);
    }

    private function writeLog($message)
    {
        $logEntry = '[' . date('Y-m-d H:i:s') . "] [INFO] {$message}\n";
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function jsonResponse($success, $message, $data = [])
    {
        $response = ['success' => $success, 'message' => $message];
        if (!empty($data)) {
            $response['data'] = $data;
        }
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

new APIMaster_ToggleProvider();
?>