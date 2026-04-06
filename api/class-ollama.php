<?php
/**
 * API Master Module - Ollama Integration
 * Local LLM çalıştırma, Llama, Mistral, Gemma, Phi-3, Vision modelleri
 */

if (!defined('ABSPATH')) {
    exit; // ABSPATH kontrolü KALACAK
}

// Interface kontrolü
if (!interface_exists('API_Interface')) {
    require_once API_MASTER_MODULE_DIR . 'api/interface-api.php';
}

class APIMaster_Ollama implements API_Interface {
    
    /**
     * @var string API URL (default local)
     */
    private $api_url = 'http://localhost:11434';
    
    /**
     * @var string Model adı
     */
    private $model = 'llama3.2';
    
    /**
     * @var string|null API key (optional, for remote instances)
     */
    private $api_key = null;
    
    /**
     * @var int Zaman aşımı (saniye)
     */
    private $timeout = 120;
    
    /**
     * @var int Maksimum token
     */
    private $max_tokens = 4096;
    
    /**
     * @var float Sıcaklık değeri
     */
    private $temperature = 0.7;
    
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
        'llama3.2' => 'Llama 3.2',
        'llama3.1' => 'Llama 3.1',
        'mistral' => 'Mistral',
        'mixtral' => 'Mixtral',
        'gemma2' => 'Gemma 2',
        'phi3' => 'Phi-3',
        'qwen2.5' => 'Qwen 2.5',
        'deepseek-coder' => 'DeepSeek Coder',
        'llava' => 'LLaVA',
        'nomic-embed-text' => 'Nomic Embed Text'
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
        
        if (isset($config['api_url'])) {
            $this->api_url = rtrim($config['api_url'], '/');
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
        // Ollama için API key zorunlu değil (local)
        if ($this->api_url === 'http://localhost:11434') {
            return ['valid' => true, 'message' => 'Local Ollama çalışıyor'];
        }
        
        if (empty($this->api_key)) {
            return ['valid' => false, 'message' => 'Remote Ollama için API anahtarı gerekli'];
        }
        
        // Remote bağlantı testi
        try {
            $response = $this->make_request('/api/tags', [], 'GET');
            if ($response !== false) {
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
        
        $data = [
            'model' => $model,
            'messages' => $this->format_messages($messages),
            'stream' => false,
            'keep_alive' => $options['keep_alive'] ?? '5m'
        ];
        
        // Options (Ollama specific)
        $ollama_options = [];
        if (isset($options['max_tokens'])) {
            $ollama_options['num_predict'] = $options['max_tokens'];
        }
        if ($temperature !== null) {
            $ollama_options['temperature'] = $temperature;
        }
        if (isset($options['top_p'])) {
            $ollama_options['top_p'] = $options['top_p'];
        }
        if (isset($options['top_k'])) {
            $ollama_options['top_k'] = $options['top_k'];
        }
        if (isset($options['repeat_penalty'])) {
            $ollama_options['repeat_penalty'] = $options['repeat_penalty'];
        }
        
        if (!empty($ollama_options)) {
            $data['options'] = $ollama_options;
        }
        
        // System message
        $system = $this->extract_system_message($messages);
        if ($system) {
            $data['system'] = $system;
        }
        
        // Format (JSON mode)
        if (isset($options['format']) && $options['format'] === 'json') {
            $data['format'] = 'json';
        }
        
        $response = $this->make_request('/api/chat', $data);
        
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
        
        $data = [
            'model' => $model,
            'messages' => $this->format_messages($messages),
            'stream' => true,
            'keep_alive' => $options['keep_alive'] ?? '5m'
        ];
        
        $ollama_options = [];
        if (isset($options['max_tokens'])) {
            $ollama_options['num_predict'] = $options['max_tokens'];
        }
        if ($temperature !== null) {
            $ollama_options['temperature'] = $temperature;
        }
        if (!empty($ollama_options)) {
            $data['options'] = $ollama_options;
        }
        
        $system = $this->extract_system_message($messages);
        if ($system) {
            $data['system'] = $system;
        }
        
        return $this->make_stream_request('/api/chat', $data, $callback);
    }
    
    /**
     * {@inheritdoc}
     */
    public function create_embedding($text, $options = []) {
        $model = $options['model'] ?? 'nomic-embed-text';
        
        $data = [
            'model' => $model,
            'input' => is_array($text) ? $text : [$text]
        ];
        
        $response = $this->make_request('/api/embed', $data);
        
        if ($response === false) {
            return false;
        }
        
        return [
            'success' => true,
            'embedding' => $response['embeddings'][0] ?? [],
            'model' => $response['model'] ?? $model
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function get_models() {
        $models = [];
        
        // Try to get actual models from Ollama
        $actual_models = $this->list_models();
        
        if ($actual_models !== false) {
            foreach ($actual_models as $name => $info) {
                $models[] = [
                    'id' => $name,
                    'name' => $info['name'],
                    'size' => $info['size'] ?? 0,
                    'modified_at' => $info['modified_at'] ?? '',
                    'enabled' => true
                ];
            }
        } else {
            // Fallback to default models
            foreach ($this->supported_models as $key => $name) {
                $models[] = [
                    'id' => $key,
                    'name' => $name,
                    'enabled' => true
                ];
            }
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
        return $this->config['name'] ?? 'Ollama';
    }
    
    /**
     * {@inheritdoc}
     */
    public function is_enabled() {
        return $this->enabled && $this->is_running();
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
        // Local Ollama için rate limit yok
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
        return [
            'provider' => 'ollama',
            'model' => $this->model,
            'enabled' => $this->enabled,
            'api_url' => $this->api_url,
            'is_running' => $this->is_running(),
            'supported_models' => array_keys($this->supported_models)
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function test_connection() {
        $start_time = microtime(true);
        
        try {
            $is_running = $this->is_running();
            $response_time = microtime(true) - $start_time;
            
            if ($is_running) {
                return [
                    'success' => true,
                    'message' => 'Ollama bağlantısı başarılı',
                    'response_time' => round($response_time, 3)
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Ollama çalışmıyor veya bağlantı kurulamadı',
                    'response_time' => round($response_time, 3)
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'response_time' => 0
            ];
        }
    }
    
    /**
     * List available models from Ollama
     *
     * @return array|false
     */
    public function list_models() {
        $response = $this->make_request('/api/tags', [], 'GET');
        
        if ($response === false || !isset($response['models'])) {
            return false;
        }
        
        $models = [];
        foreach ($response['models'] as $model) {
            $models[$model['name']] = [
                'name' => $model['name'],
                'modified_at' => $model['modified_at'],
                'size' => $model['size'],
                'digest' => $model['digest'],
                'details' => $model['details'] ?? []
            ];
        }
        
        return $models;
    }
    
    /**
     * Check if Ollama is running
     *
     * @return bool
     */
    public function is_running() {
        $ch = curl_init($this->api_url . '/api/tags');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $http_code === 200;
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
        $url = $this->api_url . $endpoint;
        
        $headers = ['Content-Type: application/json'];
        if ($this->api_key) {
            $headers[] = 'Authorization: Bearer ' . $this->api_key;
        }
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false, // Local development
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
        
        if ($http_code !== 200) {
            return false;
        }
        
        return json_decode($response, true);
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
        $url = $this->api_url . $endpoint;
        
        $headers = ['Content-Type: application/json'];
        if ($this->api_key) {
            $headers[] = 'Authorization: Bearer ' . $this->api_key;
        }
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_WRITEFUNCTION => function($ch, $chunk) use ($callback) {
                $lines = explode("\n", $chunk);
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    
                    if (empty($line)) {
                        continue;
                    }
                    
                    $json = json_decode($line, true);
                    
                    if ($json) {
                        if (isset($json['message']['content'])) {
                            call_user_func($callback, [
                                'chunk' => $json['message']['content'],
                                'raw' => $json
                            ]);
                        } elseif (isset($json['response'])) {
                            call_user_func($callback, [
                                'chunk' => $json['response'],
                                'raw' => $json
                            ]);
                        }
                        
                        if (isset($json['done']) && $json['done'] === true) {
                            call_user_func($callback, ['done' => true]);
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
            } elseif ($message['role'] !== 'system') {
                $formatted[] = $message;
            }
        }
        
        return $formatted;
    }
    
    /**
     * System mesajını çıkar
     * 
     * @param array $messages
     * @return string|null
     */
    private function extract_system_message($messages) {
        foreach ($messages as $message) {
            if (is_array($message) && isset($message['role']) && $message['role'] === 'system') {
                return $message['content'];
            }
        }
        
        return null;
    }
    
    /**
     * Chat yanıtını formatla
     * 
     * @param array $response
     * @return array|false
     */
    private function format_chat_response($response) {
        if (!isset($response['message'])) {
            return false;
        }
        
        return [
            'success' => true,
            'message' => $response['message']['content'] ?? '',
            'role' => $response['message']['role'] ?? 'assistant',
            'model' => $response['model'] ?? $this->model,
            'usage' => [
                'prompt_tokens' => $response['prompt_eval_count'] ?? 0,
                'completion_tokens' => $response['eval_count'] ?? 0,
                'total_tokens' => ($response['prompt_eval_count'] ?? 0) + ($response['eval_count'] ?? 0),
                'total_duration' => $response['total_duration'] ?? 0
            ],
            'created_at' => $response['created_at'] ?? date('c')
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