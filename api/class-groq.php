<?php
/**
 * API Master Module - Groq Integration
 * LPU (Language Processing Unit) ile ultra hızlı inference
 */

if (!defined('ABSPATH')) {
    exit; // ABSPATH kontrolü KALACAK
}

// Interface kontrolü
if (!interface_exists('API_Interface')) {
    require_once API_MASTER_MODULE_DIR . 'api/interface-api.php';
}

class APIMaster_Groq implements API_Interface {
    
    /**
     * @var string API anahtarı
     */
    private $api_key;
    
    /**
     * @var string Model adı
     */
    private $model = 'mixtral-8x7b-32768';
    
    /**
     * @var string API endpoint
     */
    private $endpoint = 'https://api.groq.com/openai/v1';
    
    /**
     * @var int Zaman aşımı (saniye)
     */
    private $timeout = 30;
    
    /**
     * @var int Maksimum token
     */
    private $max_tokens = 4096;
    
    /**
     * @var float Sıcaklık değeri
     */
    private $temperature = 0.7;
    
    /**
     * @var float Top p değeri
     */
    private $top_p = 1.0;
    
    /**
     * @var int Frequency penalty
     */
    private $frequency_penalty = 0;
    
    /**
     * @var int Presence penalty
     */
    private $presence_penalty = 0;
    
    /**
     * @var bool Aktif/pasif durumu
     */
    private $enabled = true;
    
    /**
     * @var array Provider konfigürasyonu
     */
    private $config = [];
    
    /**
     * @var array Desteklenen modeller
     */
    private $supported_models = [
        'mixtral-8x7b-32768' => 'Mixtral 8x7B',
        'llama3-70b-8192' => 'Llama 3 70B',
        'llama3-8b-8192' => 'Llama 3 8B',
        'gemma2-9b-it' => 'Gemma 2 9B'
    ];
    
    /**
     * @var array Model özellikleri
     */
    private $model_features = [
        'mixtral-8x7b-32768' => ['max_tokens' => 8192, 'context_length' => 32768, 'functions' => true],
        'llama3-70b-8192' => ['max_tokens' => 8192, 'context_length' => 8192, 'functions' => true],
        'llama3-8b-8192' => ['max_tokens' => 8192, 'context_length' => 8192, 'functions' => true],
        'gemma2-9b-it' => ['max_tokens' => 8192, 'context_length' => 8192, 'functions' => false]
    ];
    
    /**
     * Constructor
     * 
     * @param array $config Provider konfigürasyonu
     * @param string|null $api_key API anahtarı
     */
    public function __construct($config, $api_key = null) {
        $this->config = $config;
        
        if ($api_key) {
            $this->set_api_key($api_key);
        }
        
        if (isset($config['default_model'])) {
            $this->model = $config['default_model'];
        }
        
        if (isset($config['timeout'])) {
            $this->timeout = $config['timeout'];
        }
        
        if (isset($config['enabled'])) {
            $this->enabled = $config['enabled'];
        }
        
        // Model özelliklerine göre max_tokens ayarla
        if (isset($this->model_features[$this->model])) {
            $this->max_tokens = $this->model_features[$this->model]['max_tokens'];
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function set_api_key($api_key) {
        $this->api_key = $api_key;
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function validate_api_key() {
        if (empty($this->api_key)) {
            return ['valid' => false, 'message' => 'API anahtarı boş'];
        }
        
        try {
            $response = $this->make_request('/models', [], 'GET');
            
            if (isset($response['data'])) {
                return ['valid' => true, 'message' => 'API anahtarı geçerli'];
            }
            
            return ['valid' => false, 'message' => 'API anahtarı geçersiz'];
            
        } catch (Exception $e) {
            return ['valid' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function chat_completion($messages, $options = []) {
        if (!$this->enabled) {
            return false;
        }
        
        $this->validate_request($messages, 'chat');
        
        $model = $options['model'] ?? $this->model;
        $temperature = $options['temperature'] ?? $this->temperature;
        $max_tokens = $options['max_tokens'] ?? $this->max_tokens;
        $top_p = $options['top_p'] ?? $this->top_p;
        
        $data = [
            'model' => $model,
            'messages' => $this->format_messages($messages),
            'temperature' => $temperature,
            'max_tokens' => $max_tokens,
            'top_p' => $top_p,
            'frequency_penalty' => $options['frequency_penalty'] ?? $this->frequency_penalty,
            'presence_penalty' => $options['presence_penalty'] ?? $this->presence_penalty,
            'stream' => false
        ];
        
        if (isset($options['stop'])) {
            $data['stop'] = $options['stop'];
        }
        
        if (isset($options['seed'])) {
            $data['seed'] = $options['seed'];
        }
        
        $response = $this->make_request('/chat/completions', $data);
        
        if ($response === false) {
            return false;
        }
        
        return $this->format_chat_response($response);
    }
    
    /**
     * {@inheritdoc}
     */
    public function stream_chat_completion($messages, $options = [], $callback = null) {
        if (!$this->enabled) {
            return false;
        }
        
        if (!is_callable($callback)) {
            return false;
        }
        
        $this->validate_request($messages, 'chat');
        
        $model = $options['model'] ?? $this->model;
        $temperature = $options['temperature'] ?? $this->temperature;
        $max_tokens = $options['max_tokens'] ?? $this->max_tokens;
        $top_p = $options['top_p'] ?? $this->top_p;
        
        $data = [
            'model' => $model,
            'messages' => $this->format_messages($messages),
            'temperature' => $temperature,
            'max_tokens' => $max_tokens,
            'top_p' => $top_p,
            'frequency_penalty' => $options['frequency_penalty'] ?? $this->frequency_penalty,
            'presence_penalty' => $options['presence_penalty'] ?? $this->presence_penalty,
            'stream' => true
        ];
        
        return $this->make_stream_request('/chat/completions', $data, $callback);
    }
    
    /**
     * {@inheritdoc}
     */
    public function create_embedding($text, $options = []) {
        // Groq embedding desteklemiyor
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function get_models() {
        $models = [];
        
        foreach ($this->supported_models as $key => $name) {
            $features = $this->model_features[$key] ?? [
                'max_tokens' => 8192,
                'context_length' => 8192,
                'functions' => false
            ];
            
            $models[] = [
                'id' => $key,
                'name' => $name,
                'max_tokens' => $features['max_tokens'],
                'context_length' => $features['context_length'],
                'functions' => $features['functions'],
                'enabled' => true
            ];
        }
        
        return $models;
    }
    
    /**
     * {@inheritdoc}
     */
    public function get_default_model() {
        return $this->model;
    }
    
    /**
     * {@inheritdoc}
     */
    public function get_provider_name() {
        return $this->config['name'] ?? 'Groq';
    }
    
    /**
     * {@inheritdoc}
     */
    public function is_enabled() {
        return $this->enabled;
    }
    
    /**
     * {@inheritdoc}
     */
    public function set_enabled($enabled) {
        $this->enabled = (bool)$enabled;
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function check_rate_limit() {
        return ['available' => true, 'reset_in' => 0];
    }
    
    /**
     * {@inheritdoc}
     */
    public function count_tokens($text, $model = null) {
        // Basit token hesaplama
        return (int)ceil(mb_strlen($text) / 4);
    }
    
    /**
     * {@inheritdoc}
     */
    public function get_stats() {
        $features = $this->model_features[$this->model] ?? [
            'max_tokens' => 8192,
            'context_length' => 8192,
            'functions' => false
        ];
        
        return [
            'provider' => 'groq',
            'model' => $this->model,
            'enabled' => $this->enabled,
            'has_api_key' => !empty($this->api_key),
            'max_tokens' => $features['max_tokens'],
            'context_length' => $features['context_length'],
            'supports_functions' => $features['functions'],
            'supported_models' => array_keys($this->supported_models)
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function test_connection() {
        $start_time = microtime(true);
        
        try {
            $result = $this->validate_api_key();
            $response_time = microtime(true) - $start_time;
            
            return [
                'success' => $result['valid'],
                'message' => $result['message'],
                'response_time' => round($response_time, 3)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'response_time' => 0
            ];
        }
    }
    
    /**
     * API isteği yap
     * 
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @return array|false
     */
    private function make_request($endpoint, $data = [], $method = 'POST') {
        if (empty($this->api_key)) {
            return false;
        }
        
        $url = $this->endpoint . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            return false;
        }
        
        $decoded = json_decode($response, true);
        
        if ($http_code !== 200) {
            return false;
        }
        
        return $decoded;
    }
    
    /**
     * Stream isteği yap
     * 
     * @param string $endpoint
     * @param array $data
     * @param callable $callback
     * @return bool
     */
    private function make_stream_request($endpoint, $data, $callback) {
        if (empty($this->api_key)) {
            return false;
        }
        
        $url = $this->endpoint . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_WRITEFUNCTION => function($ch, $chunk) use ($callback) {
                $lines = explode("\n", $chunk);
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    
                    if (empty($line)) {
                        continue;
                    }
                    
                    if (strpos($line, 'data: ') === 0) {
                        $json_str = substr($line, 6);
                        
                        if ($json_str === '[DONE]') {
                            call_user_func($callback, ['done' => true]);
                            return strlen($chunk);
                        }
                        
                        $json = json_decode($json_str, true);
                        
                        if ($json && isset($json['choices'][0]['delta']['content'])) {
                            call_user_func($callback, [
                                'chunk' => $json['choices'][0]['delta']['content'],
                                'raw' => $json
                            ]);
                        }
                    }
                }
                
                return strlen($chunk);
            }
        ]);
        
        curl_exec($ch);
        
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Mesajları formatla
     * 
     * @param array $messages
     * @return array
     */
    private function format_messages($messages) {
        $formatted = [];
        
        foreach ($messages as $message) {
            if (is_string($message)) {
                $formatted[] = [
                    'role' => 'user',
                    'content' => $message
                ];
            } else {
                $formatted[] = $message;
            }
        }
        
        return $formatted;
    }
    
    /**
     * Chat yanıtını formatla
     * 
     * @param array $response
     * @return array|false
     */
    private function format_chat_response($response) {
        if (!isset($response['choices'][0])) {
            return false;
        }
        
        $choice = $response['choices'][0];
        
        return [
            'success' => true,
            'message' => $choice['message']['content'] ?? '',
            'role' => $choice['message']['role'] ?? 'assistant',
            'finish_reason' => $choice['finish_reason'] ?? '',
            'model' => $response['model'] ?? $this->model,
            'usage' => $response['usage'] ?? []
        ];
    }
    
    /**
     * İsteği doğrula
     * 
     * @param array $data
     * @param string $type
     * @return bool
     * @throws Exception
     */
    private function validate_request($data, $type) {
        if ($type === 'chat') {
            if (empty($data)) {
                throw new Exception('Mesajlar boş olamaz');
            }
            
            foreach ($data as $message) {
                if (is_array($message)) {
                    if (!isset($message['role']) || !isset($message['content'])) {
                        throw new Exception('Her mesaj role ve content içermelidir');
                    }
                }
            }
        }
        
        return true;
    }
}