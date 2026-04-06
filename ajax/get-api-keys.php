<?php
/**
 * get-api-keys.php - AJAX Endpoint for Masal Panel
 * Get stored API keys for providers (masked)
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);

class APIMaster_GetApiKeys
{
    private $moduleDir;
    private $configDir;
    private $logDir;
    
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
            // Tüm provider config dosyalarını bul
            $providers = $this->getProvidersFromConfig();
            
            // Provider bazlı anahtarları topla
            $keys = [];
            $totalKeys = 0;
            $validKeys = 0;
            
            foreach ($providers as $provider) {
                $keyData = $this->getProviderKeyData($provider);
                
                if ($keyData['has_key']) {
                    $totalKeys++;
                }
                if ($keyData['is_valid']) {
                    $validKeys++;
                }
                
                $keys[$provider] = $keyData;
            }
            
            // İsteğe bağlı: Sadece belirli provider'lar
            if (isset($_GET['provider']) && !empty($_GET['provider'])) {
                $requestedProvider = $this->sanitizeProvider($_GET['provider']);
                if (isset($keys[$requestedProvider])) {
                    $keys = [$requestedProvider => $keys[$requestedProvider]];
                } else {
                    throw new Exception('Provider not found: ' . $requestedProvider);
                }
            }
            
            $this->sendResponse(true, 'API keys retrieved successfully', [
                'keys' => $keys,
                'total_keys' => $totalKeys,
                'valid_keys' => $validKeys,
                'total_providers' => count($providers),
                'last_updated' => time()
            ]);
            
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            $this->sendResponse(false, 'Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Config klasöründen tüm provider'ları bul
     */
    private function getProvidersFromConfig()
    {
        $providers = [];
        
        if (!is_dir($this->configDir)) {
            return $providers;
        }
        
        // Tüm JSON dosyalarını tara
        $configFiles = glob($this->configDir . '/*.json');
        
        foreach ($configFiles as $file) {
            $provider = basename($file, '.json');
            
            // Ana config dosyasını atla
            if ($provider === 'api-master') {
                continue;
            }
            
            // Config dosyasını oku ve active mi kontrol et
            $content = @file_get_contents($file);
            if ($content !== false) {
                $config = json_decode($content, true);
                if (is_array($config)) {
                    // Sadece aktif provider'lar? (opsiyonel)
                    $includeInactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] === 'true';
                    if ($includeInactive || ($config['active'] ?? false)) {
                        $providers[] = $provider;
                    }
                }
            }
        }
        
        // Varsayılan olarak sırala
        sort($providers);
        
        return $providers;
    }
    
    /**
     * Provider'ın API key bilgilerini al
     */
    private function getProviderKeyData($provider)
    {
        $keyData = [
            'provider' => $provider,
            'has_key' => false,
            'masked_key' => '',
            'last_verified' => null,
            'is_valid' => false,
            'created' => null,
            'updated' => null
        ];
        
        // Config dosyasını oku
        $configFile = $this->configDir . '/' . $provider . '.json';
        
        if (!file_exists($configFile)) {
            return $keyData;
        }
        
        $content = @file_get_contents($configFile);
        if ($content === false) {
            return $keyData;
        }
        
        $config = json_decode($content, true);
        if (!is_array($config)) {
            return $keyData;
        }
        
        // API key'i al
        $apiKey = $config['api_key'] ?? '';
        
        if (!empty($apiKey)) {
            $keyData['has_key'] = true;
            $keyData['masked_key'] = $this->maskApiKey($apiKey);
            $keyData['is_valid'] = $config['valid'] ?? false;
            $keyData['last_verified'] = $config['last_verified'] ?? null;
            $keyData['created'] = $config['created'] ?? null;
            $keyData['updated'] = $config['updated'] ?? null;
        }
        
        return $keyData;
    }
    
    /**
     * API anahtarını maskele
     * Göster: İlk 4 + *** + Son 4
     */
    private function maskApiKey($key)
    {
        if (empty($key)) {
            return '';
        }
        
        $length = strlen($key);
        
        // Çok kısa anahtarlar
        if ($length <= 8) {
            return str_repeat('*', $length);
        }
        
        // İlk 4 ve son 4 karakteri göster
        $prefix = substr($key, 0, 4);
        $suffix = substr($key, -4);
        $maskLength = $length - 8;
        
        return $prefix . str_repeat('*', $maskLength) . $suffix;
    }
    
    /**
     * Provider adını temizle
     */
    private function sanitizeProvider($provider)
    {
        // Sadece alfanumeric ve tire
        $provider = preg_replace('/[^a-zA-Z0-9\-_]/', '', $provider);
        return strtolower(trim($provider));
    }
    
    /**
     * Hata logla
     */
    private function logError($message)
    {
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
        
        $logEntry = '[' . date('Y-m-d H:i:s') . '] [ERROR] [get-api-keys] ' . $message . PHP_EOL;
        @file_put_contents($this->logDir . '/api-master.log', $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * JSON yanıt gönder
     */
    private function sendResponse($success, $message, $data = [])
    {
        header('Content-Type: application/json');
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

$instance = new APIMaster_GetApiKeys();
$instance->execute();