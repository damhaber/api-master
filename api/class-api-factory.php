<?php
/**
 * API Master Module - API Factory
 * API provider'larını oluşturmak için factory sınıfı
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_APIFactory {
    
    /**
     * @var array Provider instance'ları
     */
    private static $instances = [];
    
    /**
     * @var array Provider konfigürasyonları
     */
    private static $configs = [];
    
    /**
     * @var array Config ayarları (dosya tabanlı)
     */
    private static $settings = [];
    
    /**
     * Desteklenen provider'lar (config/providers.php'den yüklenecek)
     */
    private static $supportedProviders = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        // Config dosyasını yükle
        $this->loadConfig();
        
        // Provider listesini yükle
        $this->loadProviders();
    }
    
    /**
     * Config dosyasını yükle
     */
    private function loadConfig(): void {
        $config_file = API_MASTER_MODULE_DIR . 'config/config.php';
        if (file_exists($config_file)) {
            $config = include $config_file;
            if (is_array($config)) {
                self::$settings = $config;
            }
        }
    }
    
    /**
     * Provider listesini yükle (config/providers.php tabanlı)
     */
    private function loadProviders(): void {
        $providers_file = API_MASTER_MODULE_DIR . 'config/providers.php';
        if (file_exists($providers_file)) {
            $providers = include $providers_file;
            if (is_array($providers)) {
                self::$supportedProviders = $providers;
            }
        }
        
        // Fallback: Eğer providers.php yoksa varsayılan listeyi kullan
        if (empty(self::$supportedProviders)) {
            self::$supportedProviders = $this->getDefaultProviders();
        }
    }
    
    /**
     * Varsayılan provider listesini getir
     * 
     * @return array
     */
    private function getDefaultProviders(): array {
        return [
            'openai' => ['name' => 'OpenAI', 'class' => 'OpenAI', 'enabled' => true],
            'deepseek' => ['name' => 'DeepSeek', 'class' => 'DeepSeek', 'enabled' => true],
            'gemini' => ['name' => 'Gemini', 'class' => 'Gemini', 'enabled' => true],
            'claude' => ['name' => 'Claude', 'class' => 'Claude', 'enabled' => true],
            'groq' => ['name' => 'Groq', 'class' => 'Groq', 'enabled' => true],
            'cohere' => ['name' => 'Cohere', 'class' => 'Cohere', 'enabled' => true],
            'mistral' => ['name' => 'Mistral', 'class' => 'Mistral', 'enabled' => true],
            'ollama' => ['name' => 'Ollama', 'class' => 'Ollama', 'enabled' => true],
            'perplexity' => ['name' => 'Perplexity', 'class' => 'Perplexity', 'enabled' => true],
            'huggingface' => ['name' => 'HuggingFace', 'class' => 'HuggingFace', 'enabled' => true],
            'llama' => ['name' => 'Llama', 'class' => 'Llama', 'enabled' => true],
            'stable-diffusion' => ['name' => 'StableDiffusion', 'class' => 'StableDiffusion', 'enabled' => true],
            'elevenlabs' => ['name' => 'ElevenLabs', 'class' => 'ElevenLabs', 'enabled' => true],
            'wikipedia' => ['name' => 'Wikipedia', 'class' => 'Wikipedia', 'enabled' => true],
            'duckduckgo' => ['name' => 'DuckDuckGo', 'class' => 'DuckDuckGo', 'enabled' => true],
            'google-search' => ['name' => 'GoogleSearch', 'class' => 'GoogleSearch', 'enabled' => true],
            'bing' => ['name' => 'Bing', 'class' => 'Bing', 'enabled' => true],
            'youtube' => ['name' => 'YouTube', 'class' => 'YouTube', 'enabled' => true],
            'spotify' => ['name' => 'Spotify', 'class' => 'Spotify', 'enabled' => true],
            'twitter' => ['name' => 'Twitter', 'class' => 'Twitter', 'enabled' => true],
            'reddit' => ['name' => 'Reddit', 'class' => 'Reddit', 'enabled' => true],
            'github' => ['name' => 'GitHub', 'class' => 'GitHub', 'enabled' => true],
            'newsapi' => ['name' => 'NewsAPI', 'class' => 'NewsAPI', 'enabled' => true],
            'weather' => ['name' => 'Weather', 'class' => 'Weather', 'enabled' => true],
            'translate' => ['name' => 'Translate', 'class' => 'Translate', 'enabled' => true],
            'paypal' => ['name' => 'PayPal', 'class' => 'PayPal', 'enabled' => true],
            'stripe' => ['name' => 'Stripe', 'class' => 'Stripe', 'enabled' => true],
            'telegram' => ['name' => 'Telegram', 'class' => 'Telegram', 'enabled' => true],
            'slack' => ['name' => 'Slack', 'class' => 'Slack', 'enabled' => true],
            'discord' => ['name' => 'Discord', 'class' => 'Discord', 'enabled' => true],
            'twilio' => ['name' => 'Twilio', 'class' => 'Twilio', 'enabled' => true],
            'sendgrid' => ['name' => 'SendGrid', 'class' => 'SendGrid', 'enabled' => true],
            'mailchimp' => ['name' => 'Mailchimp', 'class' => 'Mailchimp', 'enabled' => true],
            'notion' => ['name' => 'Notion', 'class' => 'Notion', 'enabled' => true],
            'google-drive' => ['name' => 'GoogleDrive', 'class' => 'GoogleDrive', 'enabled' => true],
            'dropbox' => ['name' => 'Dropbox', 'class' => 'Dropbox', 'enabled' => true],
            'aws-s3' => ['name' => 'AWSS3', 'class' => 'AWSS3', 'enabled' => true],
            'zoom' => ['name' => 'Zoom', 'class' => 'Zoom', 'enabled' => true],
            'google-calendar' => ['name' => 'GoogleCalendar', 'class' => 'GoogleCalendar', 'enabled' => true],
            'trello' => ['name' => 'Trello', 'class' => 'Trello', 'enabled' => true],
            'jira' => ['name' => 'Jira', 'class' => 'Jira', 'enabled' => true],
            'confluence' => ['name' => 'Confluence', 'class' => 'Confluence', 'enabled' => true],
            'google-analytics' => ['name' => 'GoogleAnalytics', 'class' => 'GoogleAnalytics', 'enabled' => true],
            'pinecone' => ['name' => 'Pinecone', 'class' => 'Pinecone', 'enabled' => true],
            'weaviate' => ['name' => 'Weaviate', 'class' => 'Weaviate', 'enabled' => true],
            'chromadb' => ['name' => 'ChromaDB', 'class' => 'ChromaDB', 'enabled' => true],
            'replicate' => ['name' => 'Replicate', 'class' => 'Replicate', 'enabled' => true],
            'yahoo-finance' => ['name' => 'YahooFinance', 'class' => 'YahooFinance', 'enabled' => true]
        ];
    }
    
    /**
     * API provider instance'ı oluştur
     * 
     * @param string $provider Provider adı
     * @param array $config Konfigürasyon
     * @return object APIMaster_APIInterface implementasyonu
     * @throws InvalidArgumentException
     */
    public static function create(string $provider, array $config = []) {
        $provider = strtolower($provider);
        
        // Provider destekleniyor mu?
        if (!isset(self::$supportedProviders[$provider])) {
            throw new InvalidArgumentException("Desteklenmeyen API provider: {$provider}");
        }
        
        // Provider aktif mi?
        $providerConfig = self::$supportedProviders[$provider];
        if (isset($providerConfig['enabled']) && $providerConfig['enabled'] === false) {
            throw new RuntimeException("API provider devre dışı: {$provider}");
        }
        
        // Sınıf adını oluştur
        $className = self::getClassName($provider);
        $fullClassName = "APIMaster_API_{$className}";
        
        // Sınıf mevcut mu?
        if (!class_exists($fullClassName)) {
            // Sınıf dosyasını dahil etmeyi dene
            $class_file = API_MASTER_MODULE_DIR . "api/class-{$className}.php";
            if (file_exists($class_file)) {
                require_once $class_file;
            }
            
            if (!class_exists($fullClassName)) {
                throw new RuntimeException("API provider sınıfı bulunamadı: {$fullClassName}");
            }
        }
        
        // Instance oluştur
        try {
            $instance = new $fullClassName($config);
            
            // Instance'ı kaydet
            self::$instances[$provider] = $instance;
            self::$configs[$provider] = $config;
            
            return $instance;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Provider sınıf adını getir
     * 
     * @param string $provider
     * @return string
     */
    private static function getClassName(string $provider): string {
        // Snake case'den Pascal case'e çevir
        $parts = explode('-', $provider);
        $parts = array_map('ucfirst', $parts);
        
        return implode('', $parts);
    }
    
    /**
     * Provider instance'ını getir
     * 
     * @param string $provider
     * @return object|null
     */
    public static function getInstance(string $provider) {
        return self::$instances[$provider] ?? null;
    }
    
    /**
     * Tüm provider'ları getir
     * 
     * @return array
     */
    public static function getAllProviders(): array {
        $providers = [];
        
        foreach (self::$supportedProviders as $key => $info) {
            $className = self::getClassName($key);
            $fullClassName = "APIMaster_API_{$className}";
            
            $providers[$key] = [
                'name' => $info['name'] ?? ucfirst($key),
                'key' => $key,
                'available' => class_exists($fullClassName),
                'enabled' => $info['enabled'] ?? true,
                'config' => self::$configs[$key] ?? null,
                'instance' => isset(self::$instances[$key])
            ];
        }
        
        return $providers;
    }
    
    /**
     * Aktif provider'ları getir
     * 
     * @return array
     */
    public static function getActiveProviders(): array {
        $providers = [];
        
        foreach (self::$supportedProviders as $key => $info) {
            // Provider devre dışıysa atla
            if (isset($info['enabled']) && $info['enabled'] === false) {
                continue;
            }
            
            $instance = self::getInstance($key);
            
            if ($instance) {
                $providerData = [
                    'name' => $info['name'] ?? ucfirst($key),
                    'key' => $key
                ];
                
                // Varsa ek metodları çağır
                if (method_exists($instance, 'getModel')) {
                    $providerData['model'] = $instance->getModel();
                }
                
                if (method_exists($instance, 'getCapabilities')) {
                    $providerData['capabilities'] = $instance->getCapabilities();
                }
                
                $providers[$key] = $providerData;
            }
        }
        
        return $providers;
    }
    
    /**
     * Provider konfigürasyonunu ayarla
     * 
     * @param string $provider
     * @param array $config
     */
    public static function setConfig(string $provider, array $config): void {
        self::$configs[$provider] = $config;
        
        // Mevcut instance varsa güncelle
        if (isset(self::$instances[$provider])) {
            $instance = self::$instances[$provider];
            
            if (isset($config['api_key']) && method_exists($instance, 'setApiKey')) {
                $instance->setApiKey($config['api_key']);
            }
            
            if (isset($config['model']) && method_exists($instance, 'setModel')) {
                $instance->setModel($config['model']);
            }
            
            if (isset($config['timeout']) && method_exists($instance, 'setTimeout')) {
                $instance->setTimeout($config['timeout']);
            }
            
            if (isset($config['max_tokens']) && method_exists($instance, 'setMaxTokens')) {
                $instance->setMaxTokens($config['max_tokens']);
            }
            
            if (isset($config['temperature']) && method_exists($instance, 'setTemperature')) {
                $instance->setTemperature($config['temperature']);
            }
        }
    }
    
    /**
     * Provider konfigürasyonunu getir
     * 
     * @param string $provider
     * @return array|null
     */
    public static function getConfig(string $provider): ?array {
        return self::$configs[$provider] ?? null;
    }
    
    /**
     * Provider instance'ını sil
     * 
     * @param string $provider
     */
    public static function destroy(string $provider): void {
        unset(self::$instances[$provider]);
        unset(self::$configs[$provider]);
    }
    
    /**
     * Tüm provider'ları temizle
     */
    public static function destroyAll(): void {
        self::$instances = [];
        self::$configs = [];
    }
    
    /**
     * Provider'ların sağlık durumunu kontrol et
     * 
     * @param array $providers Kontrol edilecek provider'lar (boşsa tümü)
     * @return array Sağlık durumları
     */
    public static function healthCheck(array $providers = []): array {
        $results = [];
        
        if (empty($providers)) {
            $providers = array_keys(self::$supportedProviders);
        }
        
        foreach ($providers as $provider) {
            try {
                $instance = self::getInstance($provider);
                
                if (!$instance) {
                    // Instance yoksa oluşturmayı dene
                    $instance = self::create($provider);
                }
                
                $healthStatus = true;
                if (method_exists($instance, 'checkHealth')) {
                    $healthStatus = $instance->checkHealth();
                }
                
                $results[$provider] = [
                    'status' => $healthStatus,
                    'message' => $healthStatus ? 'OK' : 'Failed',
                    'timestamp' => time()
                ];
                
            } catch (Exception $e) {
                $results[$provider] = [
                    'status' => false,
                    'message' => $e->getMessage(),
                    'timestamp' => time()
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * En uygun provider'ı seç
     * 
     * @param string $intent İstek tipi/intent
     * @param array $requirements Gereksinimler
     * @return string|null Provider adı
     */
    public static function selectBestProvider(string $intent, array $requirements = []): ?string {
        $activeProviders = self::getActiveProviders();
        
        if (empty($activeProviders)) {
            return null;
        }
        
        // Skorlama sistemi
        $scores = [];
        
        foreach ($activeProviders as $provider => $info) {
            $score = 0;
            $capabilities = $info['capabilities'] ?? [];
            
            // Intent bazlı skorlama
            switch ($intent) {
                case 'chat':
                    if (in_array('chat', $capabilities)) $score += 10;
                    if ($provider === 'openai') $score += 5;
                    if ($provider === 'claude') $score += 4;
                    if ($provider === 'deepseek') $score += 3;
                    break;
                    
                case 'image':
                    if (in_array('image', $capabilities)) $score += 10;
                    if ($provider === 'openai') $score += 5;
                    if ($provider === 'stable-diffusion') $score += 8;
                    if ($provider === 'replicate') $score += 6;
                    break;
                    
                case 'embedding':
                    if (in_array('embedding', $capabilities)) $score += 10;
                    break;
                    
                case 'search':
                    if (in_array('search', $capabilities)) $score += 10;
                    if ($provider === 'google-search') $score += 5;
                    if ($provider === 'bing') $score += 4;
                    break;
                    
                case 'audio':
                    if (in_array('audio', $capabilities)) $score += 10;
                    if ($provider === 'elevenlabs') $score += 8;
                    break;
            }
            
            // Gereksinim bazlı skorlama
            if (isset($requirements['language']) && isset($capabilities['languages'])) {
                if (in_array($requirements['language'], $capabilities['languages'])) {
                    $score += 3;
                }
            }
            
            if (isset($requirements['max_tokens'])) {
                if (($capabilities['max_tokens'] ?? 0) >= $requirements['max_tokens']) {
                    $score += 5;
                }
            }
            
            $scores[$provider] = $score;
        }
        
        // En yüksek skorlu provider'ı seç
        if (!empty($scores)) {
            arsort($scores);
            return key($scores);
        }
        
        return null;
    }
    
    /**
     * Provider'ı yeniden yükle (config/providers.php'yi tekrar oku)
     */
    public function reloadProviders(): void {
        $this->loadProviders();
    }
}