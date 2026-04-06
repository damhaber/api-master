<?php
/**
 * Delete API Key - AJAX Endpoint for Masal Panel
 * 
 * Provider'lar için API anahtarlarını siler
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

// Hata raporlamayı sessize al
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

class APIMaster_DeleteApiKey
{
    private $moduleDir;
    private $configDir;
    private $logDir;
    
    private $validProviders = [
        'openai', 'anthropic', 'groq', 'cohere', 'mistral', 'deepseek',
        'google', 'elevenlabs', 'stabilityai', 'replicate', 'pinecone',
        'weaviate', 'chromadb', 'twilio', 'sendgrid', 'mailchimp',
        'slack', 'discord', 'telegram', 'github', 'gitlab', 'bitbucket',
        'notion', 'hubspot', 'salesforce', 'zapier', 'make', 'pabbly',
        'zoom', 'google-calendar', 'google-drive', 'dropbox', 'aws-s3',
        'trello', 'jira', 'confluence', 'google-analytics', 'd-id', 'heygen'
    ];
    
    public function __construct()
    {
        $this->moduleDir = defined('API_MASTER_MODULE_DIR') 
            ? API_MASTER_MODULE_DIR 
            : dirname(__DIR__);
        
        $this->configDir = $this->moduleDir . '/config';
        $this->logDir = $this->moduleDir . '/logs';
    }
    
    public function execute()
    {
        try {
            $provider = isset($_POST['provider']) ? trim($_POST['provider']) : '';
            $confirm = isset($_POST['confirm']) ? (bool)$_POST['confirm'] : false;
            
            if (empty($provider)) {
                $this->sendResponse(false, 'Provider belirtilmedi');
                return;
            }
            
            if (!$confirm) {
                $this->sendResponse(false, 'Silme işlemi onaylanmadı');
                return;
            }
            
            if (!$this->isValidProvider($provider)) {
                $this->sendResponse(false, 'Geçersiz provider: ' . $provider);
                return;
            }
            
            $result = $this->deleteApiKey($provider);
            $this->logAction($provider, $result);
            $this->sendResponse($result['success'], $result['message'], $result['data'] ?? []);
            
        } catch (Exception $e) {
            $this->logError('Delete API key error: ' . $e->getMessage());
            $this->sendResponse(false, 'Bir hata oluştu: ' . $e->getMessage());
        }
    }
    
    private function isValidProvider($provider)
    {
        return in_array(strtolower($provider), $this->validProviders);
    }
    
    private function deleteApiKey($provider)
    {
        $provider = strtolower($provider);
        $configFile = $this->configDir . '/' . $provider . '.json';
        
        // Config dosyası kontrolü
        if (!file_exists($configFile)) {
            return [
                'success' => false,
                'message' => "{$provider} için yapılandırma dosyası bulunamadı"
            ];
        }
        
        // Mevcut config'i oku
        $content = file_get_contents($configFile);
        $config = json_decode($content, true);
        
        if (!is_array($config)) {
            $config = [];
        }
        
        // API key var mı kontrol et
        if (empty($config['api_key'])) {
            return [
                'success' => false,
                'message' => "{$provider} için kayıtlı API anahtarı bulunamadı"
            ];
        }
        
        // Silinecek anahtarın bilgilerini al (log için)
        $wasValid = $config['api_key_valid'] ?? false;
        $oldApiKey = $config['api_key'];
        
        // API key'i sil (boş string yap)
        $config['api_key'] = '';
        $config['api_key_valid'] = false;
        $config['api_key_updated'] = date('Y-m-d H:i:s');
        $config['api_key_deleted'] = true;
        
        // Dosyaya yaz
        $jsonContent = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (file_put_contents($configFile, $jsonContent, LOCK_EX) === false) {
            return [
                'success' => false,
                'message' => "Config dosyasına yazılamadı: {$provider}.json"
            ];
        }
        
        // Provider sınıfını yeniden yükle (eğer cache varsa temizle)
        $this->clearProviderCache($provider);
        
        return [
            'success' => true,
            'message' => "{$provider} API anahtarı başarıyla silindi",
            'data' => [
                'provider' => $provider,
                'deleted_at' => time(),
                'was_valid' => $wasValid,
                'config_file' => $provider . '.json'
            ]
        ];
    }
    
    private function clearProviderCache($provider)
    {
        // Cache dizini
        $cacheDir = $this->moduleDir . '/cache';
        
        if (!is_dir($cacheDir)) {
            return;
        }
        
        // Provider ile ilgili cache dosyalarını temizle
        $patterns = [
            $cacheDir . '/api_' . $provider . '_*.json',
            $cacheDir . '/provider_' . $provider . '_*.json',
            $cacheDir . '/config_' . $provider . '_*.json'
        ];
        
        foreach ($patterns as $pattern) {
            $files = glob($pattern);
            if (!empty($files)) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
        }
    }
    
    private function logAction($provider, $result)
    {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        
        $logFile = $this->logDir . '/api-master.log';
        
        if ($result['success']) {
            $validStatus = $result['data']['was_valid'] ? 'geçerli' : 'geçersiz';
            $logEntry = '[' . date('Y-m-d H:i:s') . "] [INFO] API anahtarı silindi - Provider: {$provider}, Durum: {$validStatus}\n";
        } else {
            $logEntry = '[' . date('Y-m-d H:i:s') . "] [ERROR] API anahtarı silme başarısız - Provider: {$provider}, Hata: {$result['message']}\n";
        }
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function logError($message)
    {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        
        $logFile = $this->logDir . '/error.log';
        $logEntry = '[' . date('Y-m-d H:i:s') . "] [ERROR] {$message}\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function sendResponse($success, $message, $data = [])
    {
        $response = [
            'success' => $success,
            'message' => $message
        ];
        
        if (!empty($data)) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Execute
$deleteApiKey = new APIMaster_DeleteApiKey();
$deleteApiKey->execute();