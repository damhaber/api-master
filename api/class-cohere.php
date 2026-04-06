<?php
/**
 * API Master Module - Cohere AI Integration
 * Cohere Command R models, embeddings ve RAG desteği
 */

if (!defined('ABSPATH')) {
    exit; // ABSPATH kontrolü KALACAK
}

// Interface kontrolü
if (!interface_exists('API_Interface')) {
    require_once API_MASTER_MODULE_DIR . 'api/interface-api.php';
}

class APIMaster_Cohere implements API_Interface {
    
    /**
     * @var string API anahtarı
     */
    private $api_key;
    
    /**
     * @var string Model adı
     */
    private $model = 'command-r-plus';
    
    /**
     * @var string API endpoint
     */
    private $endpoint = 'https://api.cohere.ai/v1';
    
    /**
     * @var string API versiyonu
     */
    private $api_version = '2024-07-01';
    
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
        'command-r-plus' => 'Command R+',
        'command-r' => 'Command R',
        'command' => 'Command',
        'embed-english-v3.0' => 'Embed English v3',
        'embed-multilingual-v3.0' => 'Embed Multilingual v3'
    ];
    
    /**
     * @var array Model özellikleri
     */
    private $model_features = [
        'command-r-plus' => ['max_tokens' => 4096, 'context_length' => 128000, 'embedding' => false, 'rag' => true],
        'command-r' => ['max_tokens' => 4096, 'context_length' => 128000, 'embedding' => false, 'rag' => true],
        'command' => ['max_tokens' => 4096, 'context_length' => 4096, 'embedding' => false, 'rag' => false],
        'embed-english-v3.0' => ['max_tokens' => 512, 'context_length' => 512, 'embedding' => true, 'rag' => false],
        'embed-multilingual-v3.0' => ['max_tokens' => 512, 'context_length' => 512, 'embedding' => true, 'rag' => false]
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
            
            if (isset($response['models'])) {
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
        
        // Embedding model ise chat yapılamaz
        if ($this->is_embedding_model($model)) {
            return false;
        }
        
        $temperature = $options['temperature'] ?? $this->temperature;
        $max_tokens = $options['max_tokens'] ?? $this->max_tokens;
        
        // Cohere chat API farklı formatta
        // Son mesajı al
        $last_message = end($messages);
        $chat_history = $this->format_chat_history(array_slice($messages, 0, -1));
        
        $data = [
            'message' => is_string($last_message) ? $last_message : $last_message['content'],
            'model' => $model,
            'temperature' => $temperature,
            'max_tokens' => $max_tokens,
            'stream' => false
        ];
        
        if (!empty($chat_history)) {
            $data['chat_history'] = $chat_history;
        }
        
        if (isset($options['top_p'])) {
            $data['p'] = $options['top_p'];
        }
        
        if (isset($options['top_k'])) {
            $data['k'] = $options['top_k'];
        }
        
        if (isset($options['documents'])) {
            $data['documents'] = $options['documents'];
        }
        
        if (isset($options['connectors'])) {
            $data['connectors'] = $options['connectors'];
        }
        
        $response = $this->make_request('/chat', $data);
        
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
        
        if ($this->is_embedding_model($model)) {
            return false;
        }
        
        $temperature = $options['temperature'] ?? $this->temperature;
        $max_tokens = $options['max_tokens'] ?? $this->max_tokens;
        
        $last_message = end($messages);
        $chat_history = $this->format_chat_history(array_slice($messages, 0, -1));
        
        $data = [
            'message' => is_string($last_message) ? $last_message : $last_message['content'],
            'model' => $model,
            'temperature' => $temperature,
            'max_tokens' => $max_tokens,
            'stream' => true
        ];
        
        if (!empty($chat_history)) {
            $data['chat_history'] = $chat_history;
        }
        
        return $this->make_stream_request('/chat', $data, $callback);
    }
    
    /**
     * {@inheritdoc}
     */
    public function create_embedding($text, $options = []) {
        $model = $options['model'] ?? 'embed-english-v3.0';
        
        if (!$this->is_embedding_model($model)) {
            return false;
        }
        
        $input_type = $options['input_type'] ?? 'search_document';
        
        $data = [
            'texts' => is_array($text) ? $text : [$text],
            'model' => $model,
            'input_type' => $input_type,
            'truncate' => 'END'
        ];
        
        $response = $this->make_request('/embed', $data);
        
        if ($response === false) {
            return false;
        }
        
        return [
            'success' => true,
            'embedding' => $response['embeddings'][0] ?? [],
            'model' => $response['model'] ?? $model,
            'input_type' => $input_type
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function get_models() {
        $models = [];
        
        foreach ($this->supported_models as $key => $name) {
            $features = $this->model_features[$key] ?? [
                'max_tokens' => 4096,
                'context_length' => 4096,
                'embedding' => false,
                'rag' => false
            ];
            
            $models[] = [
                'id' => $key,
                'name' => $name,
                'max_tokens' => $features['max_tokens'],
                'context_length' => $features['context_length'],
                'embedding' => $features['embedding'],
                'rag' => $features['rag'],
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
        return $this->config['name'] ?? 'Cohere';
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
        // Cohere için yaklaşık token hesaplama
        return (int)ceil(mb_strlen($text) / 4);
    }
    
    /**
     * {@inheritdoc}
     */
    public function get_stats() {
        $features = $this->model_features[$this->model] ?? [
            'max_tokens' => 4096,
            'context_length' => 4096,
            'embedding' => false,
            'rag' => false
        ];
        
        return [
            'provider' => 'cohere',
            'model' => $this->model,
            'enabled' => $this->enabled,
            'has_api_key' => !empty($this->api_key),
            'max_tokens' => $features['max_tokens'],
            'context_length' => $features['context_length'],
            'is_embedding_model' => $features['embedding'],
            'supports_rag' => $features['rag'],
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
            'Content-Type: application/json',
            'Cohere-Version: ' . $this->api_version
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
            'Content-Type: application/json',
            'Cohere-Version: ' . $this->api_version
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
                    
                    // Stream formatı: data: {...} veya direkt JSON
                    if (strpos($line, 'data: ') === 0) {
                        $json_str = substr($line, 6);
                        if ($json_str === '[DONE]') {
                            call_user_func($callback, ['done' => true]);
                            return strlen($chunk);
                        }
                        $json = json_decode($json_str, true);
                    } else {
                        $json = json_decode($line, true);
                    }
                    
                    if ($json && isset($json['text'])) {
                        call_user_func($callback, [
                            'chunk' => $json['text'],
                            'raw' => $json
                        ]);
                    } elseif ($json && isset($json['event']) && $json['event'] === 'text-generation') {
                        call_user_func($callback, [
                            'chunk' => $json['text'] ?? '',
                            'raw' => $json
                        ]);
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
     * Chat history formatla
     * 
     * @param array $messages
     * @return array
     */
    private function format_chat_history($messages) {
        $history = [];
        
        foreach ($messages as $message) {
            if (is_array($message)) {
                $role = $message['role'];
                $content = $message['content'];
                
                if ($role === 'user') {
                    $history[] = ['role' => 'USER', 'message' => $content];
                } elseif ($role === 'assistant') {
                    $history[] = ['role' => 'CHATBOT', 'message' => $content];
                }
            }
        }
        
        return $history;
    }
    
    /**
     * Chat yanıtını formatla
     * 
     * @param array $response
     * @return array|false
     */
    private function format_chat_response($response) {
        $text = '';
        
        if (isset($response['text'])) {
            $text = $response['text'];
        } elseif (isset($response['message']['content'][0]['text'])) {
            $text = $response['message']['content'][0]['text'];
        }
        
        return [
            'success' => true,
            'message' => $text,
            'role' => 'assistant',
            'finish_reason' => $response['finish_reason'] ?? 'complete',
            'model' => $response['model'] ?? $this->model,
            'citations' => $response['citations'] ?? [],
            'search_queries' => $response['search_queries'] ?? [],
            'usage' => $response['meta']['billed_units'] ?? [
                'input_tokens' => 0,
                'output_tokens' => 0
            ]
        ];
    }
    
    /**
     * Embedding model mi kontrol et
     * 
     * @param string $model
     * @return bool
     */
    private function is_embedding_model($model) {
        return strpos($model, 'embed') !== false;
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