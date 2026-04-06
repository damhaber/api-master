<?php
if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_SaveApiKey
{
    private $configDir;
    private $configFile;
    private $logFile;
    private $validProviders;

    public function __construct()
    {
        $moduleDir = dirname(__DIR__, 2);
        $this->configDir = $moduleDir . '/config';
        $this->configFile = $this->configDir . '/api-keys.json';
        $this->logFile = $moduleDir . '/logs/api-master.log';
        
        $this->validProviders = ['openai', 'deepseek', 'gemini', 'claude', 'cohere', 'mistral', 'anthropic'];
        
        $this->ensureDirectories();
        $this->handleRequest();
    }

    private function ensureDirectories()
    {
        if (!is_dir($this->configDir)) {
            mkdir($this->configDir, 0755, true);
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
        $apiKey = isset($_POST['api_key']) ? trim($_POST['api_key']) : '';
        $action = isset($_POST['action_type']) ? trim($_POST['action_type']) : 'save';
        
        if (empty($provider)) {
            $this->jsonResponse(false, 'Provider belirtilmedi');
        }
        
        if (!in_array($provider, $this->validProviders)) {
            $this->jsonResponse(false, 'Geçersiz provider: ' . $provider);
        }
        
        $apiKeys = $this->loadApiKeys();
        
        if ($action === 'delete') {
            $this->deleteKey($provider, $apiKeys);
        } else {
            $this->saveKey($provider, $apiKey, $apiKeys);
        }
    }

    private function loadApiKeys()
    {
        if (!file_exists($this->configFile)) {
            return [];
        }
        
        $content = file_get_contents($this->configFile);
        $data = json_decode($content, true);
        
        return is_array($data) ? $data : [];
    }

    private function saveApiKeys($keys)
    {
        $content = json_encode($keys, JSON_PRETTY_PRINT);
        file_put_contents($this->configFile, $content);
    }

    private function deleteKey($provider, &$apiKeys)
    {
        if (!isset($apiKeys[$provider])) {
            $this->jsonResponse(false, 'Silinecek anahtar bulunamadı');
        }
        
        unset($apiKeys[$provider]);
        $this->saveApiKeys($apiKeys);
        $this->writeLog("{$provider} API anahtarı silindi");
        
        $this->jsonResponse(true, "{$provider} API anahtarı silindi", [
            'provider' => $provider,
            'action' => 'delete',
            'has_key' => false,
            'updated' => time()
        ]);
    }

    private function saveKey($provider, $apiKey, &$apiKeys)
    {
        if (empty($apiKey)) {
            $this->jsonResponse(false, 'API anahtarı boş olamaz');
        }
        
        $validation = $this->validateKeyFormat($provider, $apiKey);
        if (!$validation['valid']) {
            $this->jsonResponse(false, $validation['message']);
        }
        
        $testResult = $this->testKey($provider, $apiKey);
        $now = time();
        
        $existing = isset($apiKeys[$provider]) ? $apiKeys[$provider] : [];
        
        $apiKeys[$provider] = [
            'key' => $apiKey,
            'last_verified' => $now,
            'is_valid' => $testResult['valid'],
            'test_message' => $testResult['message'],
            'created' => isset($existing['created']) ? $existing['created'] : $now,
            'updated' => $now
        ];
        
        $this->saveApiKeys($apiKeys);
        
        $message = $testResult['valid'] 
            ? "{$provider} API anahtarı kaydedildi ve doğrulandı" 
            : "{$provider} API anahtarı kaydedildi ancak doğrulama başarısız";
        
        $this->writeLog("{$provider} API anahtarı kaydedildi (valid: " . ($testResult['valid'] ? 'true' : 'false') . ")");
        
        $this->jsonResponse(true, $message, [
            'provider' => $provider,
            'action' => 'save',
            'has_key' => true,
            'is_valid' => $testResult['valid'],
            'masked_key' => $this->maskKey($apiKey),
            'updated' => $now
        ]);
    }

    private function validateKeyFormat($provider, $apiKey)
    {
        $apiKey = trim($apiKey);
        
        if (empty($apiKey)) {
            return ['valid' => false, 'message' => 'API anahtarı boş olamaz'];
        }
        
        switch ($provider) {
            case 'openai':
                if (!preg_match('/^sk-[a-zA-Z0-9_-]+$/', $apiKey)) {
                    return ['valid' => false, 'message' => 'OpenAI API anahtarı "sk-" ile başlamalıdır'];
                }
                if (strlen($apiKey) < 20) {
                    return ['valid' => false, 'message' => 'OpenAI API anahtarı çok kısa'];
                }
                break;
                
            case 'deepseek':
                if (!preg_match('/^sk-[a-zA-Z0-9_-]+$/', $apiKey) && !preg_match('/^[a-zA-Z0-9_-]{20,}$/', $apiKey)) {
                    return ['valid' => false, 'message' => 'DeepSeek API anahtarı formatı geçersiz'];
                }
                break;
                
            case 'gemini':
                if (!preg_match('/^AIza[a-zA-Z0-9_-]+$/', $apiKey)) {
                    return ['valid' => false, 'message' => 'Gemini API anahtarı "AIza" ile başlamalıdır'];
                }
                break;
                
            case 'claude':
            case 'anthropic':
                if (!preg_match('/^sk-ant-[a-zA-Z0-9_-]+$/', $apiKey)) {
                    return ['valid' => false, 'message' => 'Claude API anahtarı "sk-ant-" ile başlamalıdır'];
                }
                break;
                
            default:
                if (strlen($apiKey) < 10) {
                    return ['valid' => false, 'message' => 'API anahtarı çok kısa (en az 10 karakter)'];
                }
                break;
        }
        
        return ['valid' => true, 'message' => 'Format geçerli'];
    }

    private function testKey($provider, $apiKey)
    {
        // Gerçek API testi için provider endpoint'lerine istek yapılabilir
        // Şimdilik format kontrolü yeterli
        return ['valid' => true, 'message' => 'API anahtarı formatı geçerli'];
    }

    private function maskKey($key)
    {
        if (empty($key)) {
            return '';
        }
        
        $length = strlen($key);
        
        if ($length <= 8) {
            return str_repeat('*', $length);
        }
        
        $prefix = substr($key, 0, 4);
        $suffix = substr($key, -4);
        $maskLength = $length - 8;
        
        return $prefix . str_repeat('*', $maskLength) . $suffix;
    }

    private function writeLog($message)
    {
        $logEntry = '[' . date('Y-m-d H:i:s') . "] [INFO] {$message}\n";
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
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

new APIMaster_SaveApiKey();
?>