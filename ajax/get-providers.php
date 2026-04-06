<?php
/**
 * get-providers.php - AJAX Endpoint for Masal Panel
 * Get API providers list and their status from JSON configs
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);

class APIMaster_GetProviders
{
    private $moduleDir;
    private $configDir;
    private $logDir;
    
    // Provider metadata (görüntüleme için)
    private $providerMeta = [
        'openai' => [
            'name' => 'OpenAI',
            'description' => 'GPT-4, GPT-3.5 Turbo ve diğer OpenAI modelleri',
            'website' => 'https://openai.com',
            'icon' => 'openai.svg',
            'category' => 'llm'
        ],
        'anthropic' => [
            'name' => 'Anthropic Claude',
            'description' => 'Claude 3 Opus, Sonnet ve Haiku modelleri',
            'website' => 'https://anthropic.com',
            'icon' => 'claude.svg',
            'category' => 'llm'
        ],
        'deepseek' => [
            'name' => 'DeepSeek',
            'description' => 'DeepSeek Coder ve DeepSeek Chat modelleri',
            'website' => 'https://deepseek.com',
            'icon' => 'deepseek.svg',
            'category' => 'llm'
        ],
        'gemini' => [
            'name' => 'Google Gemini',
            'description' => 'Gemini Pro, Gemini 1.5 Pro ve Flash modelleri',
            'website' => 'https://deepmind.google/technologies/gemini/',
            'icon' => 'gemini.svg',
            'category' => 'llm'
        ],
        'cohere' => [
            'name' => 'Cohere',
            'description' => 'Command, Command-Light ve Command-R modelleri',
            'website' => 'https://cohere.com',
            'icon' => 'cohere.svg',
            'category' => 'llm'
        ],
        'mistral' => [
            'name' => 'Mistral AI',
            'description' => 'Mistral Tiny, Small, Medium ve Large modelleri',
            'website' => 'https://mistral.ai',
            'icon' => 'mistral.svg',
            'category' => 'llm'
        ],
        'stabilityai' => [
            'name' => 'Stability AI',
            'description' => 'Stable Diffusion görüntü oluşturma modelleri',
            'website' => 'https://stability.ai',
            'icon' => 'stability.svg',
            'category' => 'image'
        ],
        'd-id' => [
            'name' => 'D-ID',
            'description' => 'AI yüz ve video oluşturma',
            'website' => 'https://d-id.com',
            'icon' => 'did.svg',
            'category' => 'video'
        ],
        'heygen' => [
            'name' => 'HeyGen',
            'description' => 'AI avatar ve video oluşturma',
            'website' => 'https://heygen.com',
            'icon' => 'heygen.svg',
            'category' => 'video'
        ],
        'gitlab' => [
            'name' => 'GitLab',
            'description' => 'GitLab API entegrasyonu',
            'website' => 'https://gitlab.com',
            'icon' => 'gitlab.svg',
            'category' => 'devops'
        ],
        'bitbucket' => [
            'name' => 'Bitbucket',
            'description' => 'Bitbucket API entegrasyonu',
            'website' => 'https://bitbucket.org',
            'icon' => 'bitbucket.svg',
            'category' => 'devops'
        ],
        'hubspot' => [
            'name' => 'HubSpot',
            'description' => 'HubSpot CRM entegrasyonu',
            'website' => 'https://hubspot.com',
            'icon' => 'hubspot.svg',
            'category' => 'crm'
        ],
        'salesforce' => [
            'name' => 'Salesforce',
            'description' => 'Salesforce CRM entegrasyonu',
            'website' => 'https://salesforce.com',
            'icon' => 'salesforce.svg',
            'category' => 'crm'
        ],
        'zapier' => [
            'name' => 'Zapier',
            'description' => 'Zapier webhook entegrasyonu',
            'website' => 'https://zapier.com',
            'icon' => 'zapier.svg',
            'category' => 'automation'
        ],
        'make' => [
            'name' => 'Make',
            'description' => 'Make (Integromat) entegrasyonu',
            'website' => 'https://make.com',
            'icon' => 'make.svg',
            'category' => 'automation'
        ],
        'pabbly' => [
            'name' => 'Pabbly',
            'description' => 'Pabbly Connect entegrasyonu',
            'website' => 'https://pabbly.com',
            'icon' => 'pabbly.svg',
            'category' => 'automation'
        ]
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
            $category = isset($_GET['category']) ? $this->sanitizeCategory($_GET['category']) : null;
            $includeInactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] === 'true';
            $onlyActive = isset($_GET['only_active']) && $_GET['only_active'] === 'true';
            
            $providers = $this->getProvidersFromConfig();
            
            // Kategori filtresi
            if ($category !== null) {
                $providers = array_filter($providers, function($provider) use ($category) {
                    return ($provider['category'] ?? 'other') === $category;
                });
            }
            
            // Aktiflik filtresi
            if ($onlyActive) {
                $providers = array_filter($providers, function($provider) {
                    return $provider['active'] === true;
                });
            }
            
            // İnaktif provider'ları dahil etme
            if (!$includeInactive) {
                $providers = array_filter($providers, function($provider) {
                    return $provider['active'] === true || $provider['has_config'] === true;
                });
            }
            
            // İstatistikler
            $activeCount = 0;
            $totalModels = 0;
            $categories = [];
            
            foreach ($providers as $key => $provider) {
                if ($provider['active']) {
                    $activeCount++;
                }
                $totalModels += count($provider['models'] ?? []);
                
                $cat = $provider['category'] ?? 'other';
                if (!isset($categories[$cat])) {
                    $categories[$cat] = 0;
                }
                $categories[$cat]++;
            }
            
            // Anahtarları yeniden indeksle
            $providers = array_values($providers);
            
            $this->sendResponse(true, 'Providers retrieved successfully', [
                'providers' => $providers,
                'total' => count($providers),
                'active_count' => $activeCount,
                'total_models' => $totalModels,
                'categories' => $categories,
                'last_updated' => time()
            ]);
            
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            $this->sendResponse(false, 'Error: ' . $e->getMessage());
        }
    }
    
    private function getProvidersFromConfig()
    {
        $providers = [];
        
        if (!is_dir($this->configDir)) {
            return $providers;
        }
        
        // Tüm JSON config dosyalarını tara
        $configFiles = glob($this->configDir . '/*.json');
        
        foreach ($configFiles as $file) {
            $provider = basename($file, '.json');
            
            // Ana config dosyasını atla
            if ($provider === 'api-master' || $provider === 'cache' || $provider === 'learning') {
                continue;
            }
            
            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }
            
            $config = json_decode($content, true);
            if (!is_array($config)) {
                continue;
            }
            
            // Provider metadata'sını al
            $meta = $this->providerMeta[$provider] ?? [
                'name' => ucfirst($provider),
                'description' => $provider . ' API entegrasyonu',
                'website' => null,
                'icon' => $provider . '.svg',
                'category' => 'other'
            ];
            
            // Modelleri al (config'den veya varsayılan)
            $models = $config['models'] ?? $this->getDefaultModels($provider);
            
            // Provider verisini oluştur
            $providers[] = [
                'id' => $provider,
                'name' => $meta['name'],
                'description' => $meta['description'],
                'website' => $meta['website'],
                'icon' => $meta['icon'],
                'category' => $meta['category'],
                'models' => $models,
                'default_model' => $config['default_model'] ?? ($models[0] ?? null),
                'active' => $config['active'] ?? false,
                'has_config' => true,
                'has_api_key' => !empty($config['api_key']),
                'api_key_valid' => $config['valid'] ?? false,
                'last_verified' => $config['last_verified'] ?? null,
                'created' => $config['created'] ?? null,
                'updated' => $config['updated'] ?? null,
                'requires_api_key' => $config['requires_api_key'] ?? true
            ];
        }
        
        // ID'ye göre sırala
        usort($providers, function($a, $b) {
            return strcmp($a['id'], $b['id']);
        });
        
        return $providers;
    }
    
    private function getDefaultModels($provider)
    {
        $defaultModels = [
            'openai' => ['gpt-4', 'gpt-4-turbo', 'gpt-3.5-turbo', 'gpt-4o'],
            'anthropic' => ['claude-3-opus', 'claude-3-sonnet', 'claude-3-haiku'],
            'deepseek' => ['deepseek-chat', 'deepseek-coder'],
            'gemini' => ['gemini-pro', 'gemini-1.5-pro', 'gemini-1.5-flash'],
            'cohere' => ['command', 'command-light', 'command-r'],
            'mistral' => ['mistral-tiny', 'mistral-small', 'mistral-medium', 'mistral-large'],
            'stabilityai' => ['stable-diffusion-xl', 'stable-diffusion-3'],
            'd-id' => ['d-id'],
            'heygen' => ['heygen'],
            'gitlab' => ['gitlab'],
            'bitbucket' => ['bitbucket'],
            'hubspot' => ['hubspot'],
            'salesforce' => ['salesforce'],
            'zapier' => ['zapier'],
            'make' => ['make'],
            'pabbly' => ['pabbly']
        ];
        
        return $defaultModels[$provider] ?? ['default'];
    }
    
    private function sanitizeCategory($category)
    {
        $validCategories = ['llm', 'image', 'video', 'devops', 'crm', 'automation', 'other'];
        $category = strtolower(trim($category));
        
        return in_array($category, $validCategories) ? $category : null;
    }
    
    private function logError($message)
    {
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
        
        $logEntry = '[' . date('Y-m-d H:i:s') . '] [ERROR] [get-providers] ' . $message . PHP_EOL;
        @file_put_contents($this->logDir . '/api-master.log', $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function sendResponse($success, $message, $data = [])
    {
        header('Content-Type: application/json');
        $response = [
            'success' => $success,
            'message' => $message,
            'timestamp' => time()
        ];
        
        if (!empty($data)) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$instance = new APIMaster_GetProviders();
$instance->execute();