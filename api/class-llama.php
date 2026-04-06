<?php
/**
 * API Master Module - Meta Llama API
 * Llama 3.1, 3.2, 3.3 modelleri - Replicate, Together AI, DeepInfra, Fireworks, Groq provider'ları
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Llama implements APIMaster_APIInterface {
    
    /**
     * Provider options
     */
    const PROVIDER_REPLICATE = 'replicate';
    const PROVIDER_TOGETHER = 'together';
    const PROVIDER_DEEPINFRA = 'deepinfra';
    const PROVIDER_FIREWORKS = 'fireworks';
    const PROVIDER_GROQ = 'groq';
    
    /**
     * API URLs for different providers
     */
    private $provider_urls = [
        self::PROVIDER_REPLICATE => 'https://api.replicate.com/v1',
        self::PROVIDER_TOGETHER => 'https://api.together.xyz/v1',
        self::PROVIDER_DEEPINFRA => 'https://api.deepinfra.com/v1/openai',
        self::PROVIDER_FIREWORKS => 'https://api.fireworks.ai/inference/v1',
        self::PROVIDER_GROQ => 'https://api.groq.com/openai/v1'
    ];
    
    /**
     * Model mappings for different providers
     */
    private $model_mappings = [
        'llama-3.1-8b' => [
            self::PROVIDER_REPLICATE => 'meta/meta-llama-3.1-8b-instruct',
            self::PROVIDER_TOGETHER => 'meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo',
            self::PROVIDER_DEEPINFRA => 'meta-llama/Meta-Llama-3.1-8B-Instruct',
            self::PROVIDER_FIREWORKS => 'accounts/fireworks/models/llama-v3p1-8b-instruct',
            self::PROVIDER_GROQ => 'llama-3.1-8b-instant'
        ],
        'llama-3.1-70b' => [
            self::PROVIDER_REPLICATE => 'meta/meta-llama-3.1-70b-instruct',
            self::PROVIDER_TOGETHER => 'meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo',
            self::PROVIDER_DEEPINFRA => 'meta-llama/Meta-Llama-3.1-70B-Instruct',
            self::PROVIDER_FIREWORKS => 'accounts/fireworks/models/llama-v3p1-70b-instruct',
            self::PROVIDER_GROQ => 'llama-3.1-70b-versatile'
        ],
        'llama-3.1-405b' => [
            self::PROVIDER_REPLICATE => 'meta/meta-llama-3.1-405b-instruct',
            self::PROVIDER_TOGETHER => 'meta-llama/Meta-Llama-3.1-405B-Instruct-Turbo',
            self::PROVIDER_DEEPINFRA => 'meta-llama/Meta-Llama-3.1-405B-Instruct',
            self::PROVIDER_FIREWORKS => 'accounts/fireworks/models/llama-v3p1-405b-instruct'
        ],
        'llama-3.2-1b' => [
            self::PROVIDER_REPLICATE => 'meta/meta-llama-3.2-1b-instruct',
            self::PROVIDER_TOGETHER => 'meta-llama/Llama-3.2-1B-Instruct-Turbo',
            self::PROVIDER_GROQ => 'llama-3.2-1b-preview'
        ],
        'llama-3.2-3b' => [
            self::PROVIDER_REPLICATE => 'meta/meta-llama-3.2-3b-instruct',
            self::PROVIDER_TOGETHER => 'meta-llama/Llama-3.2-3B-Instruct-Turbo',
            self::PROVIDER_GROQ => 'llama-3.2-3b-preview'
        ],
        'llama-3.2-11b-vision' => [
            self::PROVIDER_REPLICATE => 'meta/meta-llama-3.2-11b-vision-instruct',
            self::PROVIDER_TOGETHER => 'meta-llama/Llama-3.2-11B-Vision-Instruct-Turbo',
            self::PROVIDER_FIREWORKS => 'accounts/fireworks/models/llama-v3p2-11b-vision-instruct'
        ],
        'llama-3.2-90b-vision' => [
            self::PROVIDER_REPLICATE => 'meta/meta-llama-3.2-90b-vision-instruct',
            self::PROVIDER_TOGETHER => 'meta-llama/Llama-3.2-90B-Vision-Instruct-Turbo'
        ],
        'llama-3.3-70b' => [
            self::PROVIDER_REPLICATE => 'meta/meta-llama-3.3-70b-instruct',
            self::PROVIDER_TOGETHER => 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
            self::PROVIDER_DEEPINFRA => 'meta-llama/Llama-3.3-70B-Instruct',
            self::PROVIDER_FIREWORKS => 'accounts/fireworks/models/llama-v3p3-70b-instruct',
            self::PROVIDER_GROQ => 'llama-3.3-70b-versatile'
        ]
    ];
    
    /**
     * Current provider
     *
     * @var string
     */
    private $provider = self::PROVIDER_REPLICATE;
    
    /**
     * API key for current provider
     *
     * @var string
     */
    private $api_key;
    
    /**
     * API keys for different providers
     *
     * @var array
     */
    private $provider_keys = [];
    
    /**
     * Request timeout in seconds
     *
     * @var int
     */
    private $timeout = 120;
    
    /**
     * Yapılandırma
     *
     * @var array
     */
    private $config = [];
    
    /**
     * Constructor
     *
     * @param array $config Yapılandırma ayarları
     */
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'provider' => self::PROVIDER_REPLICATE,
            'api_key' => '',
            'timeout' => 120
        ], $config);
        
        $this->provider = $this->config['provider'];
        $this->api_key = $this->config['api_key'];
        $this->provider_keys[$this->provider] = $this->api_key;
        $this->timeout = $this->config['timeout'];
    }
    
    /**
     * Set provider
     *
     * @param string $provider Provider name
     * @param string|null $api_key Optional API key for this provider
     * @return self
     */
    public function setProvider($provider, $api_key = null) {
        if (isset($this->provider_urls[$provider])) {
            $this->provider = $provider;
            if ($api_key) {
                $this->provider_keys[$provider] = $api_key;
                $this->api_key = $api_key;
            } elseif (isset($this->provider_keys[$provider])) {
                $this->api_key = $this->provider_keys[$provider];
            }
        }
        return $this;
    }
    
    /**
     * Set API key for current provider
     *
     * @param string $api_key
     * @return self
     */
    public function setApiKey($api_key) {
        $this->api_key = $api_key;
        $this->provider_keys[$this->provider] = $api_key;
        return $this;
    }
    
    /**
     * Send a chat completion request
     *
     * @param array $params Request parameters
     * @return array|false Response or false on error
     */
    public function sendMessage($params) {
        if (empty($this->api_key)) {
            return false;
        }
        
        if (empty($params['model']) || empty($params['messages'])) {
            return false;
        }
        
        // Map model to provider-specific identifier
        $model = $params['model'];
        $provider_model = $this->mapModelToProvider($model);
        
        if (!$provider_model) {
            return false;
        }
        
        // Prepare request based on provider
        $request = $this->prepareRequest($provider_model, $params);
        
        // Make API request
        $response = $this->makeRequest($request);
        
        if ($response === false) {
            return false;
        }
        
        return $this->normalizeResponse($response);
    }
    
    /**
     * Map model name to provider-specific identifier
     *
     * @param string $model Model name
     * @return string|false
     */
    private function mapModelToProvider($model) {
        if (isset($this->model_mappings[$model][$this->provider])) {
            return $this->model_mappings[$model][$this->provider];
        }
        
        foreach ($this->model_mappings as $key => $mapping) {
            if (strpos($model, $key) === 0 && isset($mapping[$this->provider])) {
                return $mapping[$this->provider];
            }
        }
        
        return false;
    }
    
    /**
     * Prepare request based on provider
     *
     * @param string $model Provider-specific model identifier
     * @param array $params Validated parameters
     * @return array
     */
    private function prepareRequest($model, $params) {
        $request = [
            'stream' => $params['stream'] ?? false
        ];
        
        switch ($this->provider) {
            case self::PROVIDER_REPLICATE:
                $request = array_merge($request, [
                    'version' => $model,
                    'input' => [
                        'messages' => $params['messages'],
                        'max_tokens' => $params['max_tokens'] ?? 1024,
                        'temperature' => $params['temperature'] ?? 0.7,
                        'top_p' => $params['top_p'] ?? 0.9,
                        'top_k' => $params['top_k'] ?? 50,
                        'frequency_penalty' => $params['frequency_penalty'] ?? 0,
                        'presence_penalty' => $params['presence_penalty'] ?? 0
                    ]
                ]);
                break;
                
            case self::PROVIDER_TOGETHER:
            case self::PROVIDER_DEEPINFRA:
            case self::PROVIDER_FIREWORKS:
            case self::PROVIDER_GROQ:
                $request = array_merge($request, [
                    'model' => $model,
                    'messages' => $params['messages'],
                    'max_tokens' => $params['max_tokens'] ?? 1024,
                    'temperature' => $params['temperature'] ?? 0.7,
                    'top_p' => $params['top_p'] ?? 0.9,
                    'top_k' => $params['top_k'] ?? 50,
                    'frequency_penalty' => $params['frequency_penalty'] ?? 0,
                    'presence_penalty' => $params['presence_penalty'] ?? 0
                ]);
                
                if (isset($params['stop'])) {
                    $request['stop'] = $params['stop'];
                }
                if (isset($params['seed'])) {
                    $request['seed'] = $params['seed'];
                }
                break;
        }
        
        return $request;
    }
    
    /**
     * Make HTTP request to provider API
     *
     * @param array $request Request data
     * @return array|false
     */
    private function makeRequest($request) {
        $endpoint = $this->getEndpoint();
        $headers = $this->getHeaders();
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            return false;
        }
        
        $decoded = json_decode($response, true);
        
        if ($http_code !== 200 && $http_code !== 201) {
            return false;
        }
        
        return $decoded;
    }
    
    /**
     * Get API endpoint for current provider
     *
     * @return string
     */
    private function getEndpoint() {
        $base_url = $this->provider_urls[$this->provider];
        
        switch ($this->provider) {
            case self::PROVIDER_REPLICATE:
                return $base_url . '/predictions';
            case self::PROVIDER_TOGETHER:
            case self::PROVIDER_DEEPINFRA:
            case self::PROVIDER_FIREWORKS:
            case self::PROVIDER_GROQ:
                return $base_url . '/chat/completions';
            default:
                return $base_url;
        }
    }
    
    /**
     * Get headers for current provider
     *
     * @return array
     */
    private function getHeaders() {
        $headers = ['Content-Type: application/json'];
        
        switch ($this->provider) {
            case self::PROVIDER_REPLICATE:
                $headers[] = 'Authorization: Token ' . $this->api_key;
                break;
            case self::PROVIDER_TOGETHER:
            case self::PROVIDER_DEEPINFRA:
            case self::PROVIDER_FIREWORKS:
            case self::PROVIDER_GROQ:
                $headers[] = 'Authorization: Bearer ' . $this->api_key;
                break;
        }
        
        return $headers;
    }
    
    /**
     * Normalize response from different providers
     *
     * @param array $response Raw API response
     * @return array
     */
    private function normalizeResponse($response) {
        switch ($this->provider) {
            case self::PROVIDER_REPLICATE:
                if (isset($response['output']['choices'][0]['message'])) {
                    return [
                        'choices' => $response['output']['choices'],
                        'usage' => $response['output']['usage'] ?? []
                    ];
                }
                if (isset($response['output'])) {
                    return [
                        'choices' => [[
                            'message' => ['content' => $response['output']],
                            'finish_reason' => 'stop'
                        ]]
                    ];
                }
                break;
                
            case self::PROVIDER_TOGETHER:
            case self::PROVIDER_DEEPINFRA:
            case self::PROVIDER_FIREWORKS:
            case self::PROVIDER_GROQ:
                return $response;
        }
        
        return $response;
    }
    
    /**
     * Get available models
     *
     * @return array
     */
    public function getModels() {
        return [
            'llama-3.3-70b' => [
                'name' => 'Llama 3.3 70B',
                'description' => 'Latest Llama 3.3 model with improved performance',
                'context_length' => 128000,
                'max_tokens' => 8192,
                'supports_streaming' => true,
                'supports_tools' => true,
                'providers' => array_keys($this->model_mappings['llama-3.3-70b'])
            ],
            'llama-3.1-405b' => [
                'name' => 'Llama 3.1 405B',
                'description' => 'Largest Llama model with frontier-level capabilities',
                'context_length' => 128000,
                'max_tokens' => 8192,
                'supports_streaming' => true,
                'supports_tools' => true,
                'providers' => array_keys($this->model_mappings['llama-3.1-405b'])
            ],
            'llama-3.1-70b' => [
                'name' => 'Llama 3.1 70B',
                'description' => 'High-performance model for complex tasks',
                'context_length' => 128000,
                'max_tokens' => 8192,
                'supports_streaming' => true,
                'supports_tools' => true,
                'providers' => array_keys($this->model_mappings['llama-3.1-70b'])
            ],
            'llama-3.1-8b' => [
                'name' => 'Llama 3.1 8B',
                'description' => 'Fast and efficient model for everyday tasks',
                'context_length' => 128000,
                'max_tokens' => 8192,
                'supports_streaming' => true,
                'supports_tools' => true,
                'providers' => array_keys($this->model_mappings['llama-3.1-8b'])
            ],
            'llama-3.2-90b-vision' => [
                'name' => 'Llama 3.2 90B Vision',
                'description' => 'Vision-language model for image understanding',
                'context_length' => 128000,
                'max_tokens' => 8192,
                'supports_streaming' => true,
                'supports_vision' => true,
                'providers' => array_keys($this->model_mappings['llama-3.2-90b-vision'])
            ],
            'llama-3.2-11b-vision' => [
                'name' => 'Llama 3.2 11B Vision',
                'description' => 'Lightweight vision-language model',
                'context_length' => 128000,
                'max_tokens' => 8192,
                'supports_streaming' => true,
                'supports_vision' => true,
                'providers' => array_keys($this->model_mappings['llama-3.2-11b-vision'])
            ],
            'llama-3.2-3b' => [
                'name' => 'Llama 3.2 3B',
                'description' => 'Ultra-lightweight text model',
                'context_length' => 128000,
                'max_tokens' => 8192,
                'supports_streaming' => true,
                'providers' => array_keys($this->model_mappings['llama-3.2-3b'])
            ],
            'llama-3.2-1b' => [
                'name' => 'Llama 3.2 1B',
                'description' => 'Smallest Llama model for edge deployment',
                'context_length' => 128000,
                'max_tokens' => 8192,
                'supports_streaming' => true,
                'providers' => array_keys($this->model_mappings['llama-3.2-1b'])
            ]
        ];
    }
    
    /**
     * Get available providers
     *
     * @return array
     */
    public function getProviders() {
        return [
            self::PROVIDER_REPLICATE => [
                'name' => 'Replicate',
                'website' => 'https://replicate.com',
                'requires_api_key' => true,
                'free_tier' => false
            ],
            self::PROVIDER_TOGETHER => [
                'name' => 'Together AI',
                'website' => 'https://together.ai',
                'requires_api_key' => true,
                'free_tier' => true
            ],
            self::PROVIDER_DEEPINFRA => [
                'name' => 'DeepInfra',
                'website' => 'https://deepinfra.com',
                'requires_api_key' => true,
                'free_tier' => true
            ],
            self::PROVIDER_FIREWORKS => [
                'name' => 'Fireworks AI',
                'website' => 'https://fireworks.ai',
                'requires_api_key' => true,
                'free_tier' => true
            ],
            self::PROVIDER_GROQ => [
                'name' => 'Groq',
                'website' => 'https://groq.com',
                'requires_api_key' => true,
                'free_tier' => true,
                'specialty' => 'Ultra-fast LPU inference'
            ]
        ];
    }
    
    /**
     * Set request timeout
     *
     * @param int $seconds Timeout in seconds
     * @return self
     */
    public function setTimeout($seconds) {
        $this->timeout = (int) $seconds;
        $this->config['timeout'] = $this->timeout;
        return $this;
    }
    
    /**
     * APIInterface: complete metodu
     */
    public function complete($prompt, $options = []) {
        $model = $options['model'] ?? 'llama-3.1-8b';
        $messages = [['role' => 'user', 'content' => $prompt]];
        
        return $this->sendMessage(array_merge($options, [
            'model' => $model,
            'messages' => $messages
        ]));
    }
    
    /**
     * APIInterface: stream metodu
     */
    public function stream($prompt, $callback, $options = []) {
        $result = $this->complete($prompt, array_merge($options, ['stream' => true]));
        if (is_callable($callback) && $result !== false) {
            $callback($result);
        }
        return $result;
    }
    
    /**
     * APIInterface: getCapabilities metodu
     */
    public function getCapabilities() {
        return [
            'chat',
            'completion',
            'streaming',
            'tools',
            'vision' => $this->provider !== self::PROVIDER_GROQ
        ];
    }
    
    /**
     * APIInterface: checkHealth metodu
     */
    public function checkHealth() {
        if (empty($this->api_key)) {
            return false;
        }
        
        $response = $this->makeRequest([
            'model' => $this->mapModelToProvider('llama-3.1-8b'),
            'messages' => [['role' => 'user', 'content' => 'Test']],
            'max_tokens' => 1,
            'stream' => false
        ]);
        
        return $response !== false;
    }
    
    /**
     * APIInterface: setModel metodu
     */
    public function setModel($model) {
        // Model seçimi request anında yapılır
        return true;
    }
    
    /**
     * APIInterface: getModel metodu
     */
    public function getModel() {
        return 'llama-3.3-70b';
    }
    
    /**
     * APIInterface: chat metodu
     */
    public function chat($messages, $options = []) {
        $model = $options['model'] ?? 'llama-3.1-8b';
        
        return $this->sendMessage(array_merge($options, [
            'model' => $model,
            'messages' => $messages
        ]));
    }
    
    /**
     * Extract text from response
     *
     * @param array $response API response
     * @return string
     */
    public function extractText($response) {
        if (isset($response['choices'][0]['message']['content'])) {
            return $response['choices'][0]['message']['content'];
        }
        
        if (isset($response['choices'][0]['delta']['content'])) {
            return $response['choices'][0]['delta']['content'];
        }
        
        if (isset($response['output'])) {
            return is_string($response['output']) ? $response['output'] : json_encode($response['output']);
        }
        
        return '';
    }
    
    /**
     * Extract usage statistics from response
     *
     * @param array $response API response
     * @return array
     */
    public function extractUsage($response) {
        return isset($response['usage']) ? $response['usage'] : [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0
        ];
    }
}