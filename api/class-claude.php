<?php
/**
 * API Master Module - Anthropic Claude Integration
 * Claude AI API entegrasyonu
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişim engellendi
}

/**
 * Anthropic Claude Provider Sınıfı
 * API_Interface implementasyonu
 */
class APIMaster_Claude implements API_Interface {
    
    /**
     * @var string API anahtarı
     */
    private $api_key;
    
    /**
     * @var string Model adı
     */
    private $model = 'claude-3-sonnet-20240229';
    
    /**
     * @var string API endpoint
     */
    private $endpoint = 'https://api.anthropic.com/v1';
    
    /**
     * @var string API versiyonu
     */
    private $api_version = '2023-06-01';
    
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
     * @var int Top k değeri
     */
    private $top_k = 40;
    
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
        'claude-3-opus-20240229' => 'Claude 3 Opus',
        'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
        'claude-3-haiku-20240307' => 'Claude 3 Haiku',
        'claude-2.1' => 'Claude 2.1'
    ];
    
    /**
     * @var array Model özellikleri
     */
    private $model_features = [
        'claude-3-opus-20240229' => ['max_tokens' => 4096, 'vision' => true, 'context_length' => 200000],
        'claude-3-sonnet-20240229' => ['max_tokens' => 4096, 'vision' => true, 'context_length' => 200000],
        'claude-3-haiku-20240307' => ['max_tokens' => 4096, 'vision' => true, 'context_length' => 200000],
        'claude-2.1' => ['max_tokens' => 4096, 'vision' => false, 'context_length' => 200000]
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
            // Basit bir test isteği gönder
            $response = $this->make_request('/messages', [
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 1,
                'messages' => [
                    ['role' => 'user', 'content' => 'Hi']
                ]
            ]);
            
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
        $max_tokens = $options['max_tokens'] ?? $this->max_tokens;
        $top_p = $options['top_p'] ?? $this->top_p;
        $top_k = $options['top_k'] ?? $this->top_k;
        
        // System mesajını ayır
        $system = $this->extract_system_message($messages);
        $chat_messages = $this->filter_chat_messages($messages);
        
        $data = [
            'model' => $model,
            'messages' => $this->format_messages_for_claude($chat_messages),
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
            'top_p' => $top_p,
            'top_k' => $top_k,
            'stream' => false
        ];
        
        // System mesajı varsa ekle
        if ($system) {
            $data['system'] = $system;
        }
        
        // Stop sequences varsa ekle
        if (isset($options['stop_sequences']) && is_array($options['stop_sequences'])) {
            $data['stop_sequences'] = $options['stop_sequences'];
        }
        
        $response = $this->make_request('/messages', $data);
        
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
        $top_k = $options['top_k'] ?? $this->top_k;
        
        $system = $this->extract_system_message($messages);
        $chat_messages = $this->filter_chat_messages($messages);
        
        $data = [
            'model' => $model,
            'messages' => $this->format_messages_for_claude($chat_messages),
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
            'top_p' => $top_p,
            'top_k' => $top_k,
            'stream' => true
        ];
        
        if ($system) {
            $data['system'] = $system;
        }
        
        return $this->make_stream_request('/messages', $data, $callback);
    }
    
    /**
     * {@inheritdoc}
     */
    public function create_embedding($text, $options = []) {
        // Claude embedding desteklemiyor
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function get_models() {
        $models = [];
        
        foreach ($this->supported_models as $key => $name) {
            $features = $this->model_features[$key] ?? [
                'max_tokens' => 4096, 
                'vision' => false, 
                'context_length' => 200000
            ];
            
            $models[] = [
                'id' => $key,
                'name' => $name,
                'max_tokens' => $features['max_tokens'],
                'context_length' => $features['context_length'],
                'vision' => $features['vision'],
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
        return $this->config['name'] ?? 'Anthropic Claude';
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
        // Yaklaşık token hesabı (Claude için 1 token ≈ 3.5 karakter)
        return (int)ceil(mb_strlen($text) / 3.5);
    }
    
    /**
     * {@inheritdoc}
     */
    public function get_stats() {
        $features = $this->model_features[$this->model] ?? [
            'max_tokens' => 4096, 
            'vision' => false, 
            'context_length' => 200000
        ];
        
        return [
            'provider' => 'claude',
            'model' => $this->model,
            'enabled' => $this->enabled,
            'has_api_key' => !empty($this->api_key),
            'max_tokens' => $features['max_tokens'],
            'context_length' => $features['context_length'],
            'supports_vision' => $features['vision'],
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
     * @return array|false
     */
    private function make_request($endpoint, $data) {
        if (empty($this->api_key)) {
            return false;
        }
        
        $url = $this->endpoint . $endpoint;
        
        $headers = [
            'x-api-key: ' . $this->api_key,
            'anthropic-version: ' . $this->api_version,
            'content-type: application/json'
        ];
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
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
            'x-api-key: ' . $this->api_key,
            'anthropic-version: ' . $this->api_version,
            'content-type: application/json'
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
                        
                        if ($json && isset($json['type'])) {
                            if ($json['type'] === 'content_block_delta' && isset($json['delta']['text'])) {
                                call_user_func($callback, [
                                    'chunk' => $json['delta']['text'],
                                    'raw' => $json
                                ]);
                            }
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
     * Chat mesajlarını filtrele (system hariç)
     * 
     * @param array $messages
     * @return array
     */
    private function filter_chat_messages($messages) {
        $filtered = [];
        
        foreach ($messages as $message) {
            if (is_array($message) && isset($message['role']) && $message['role'] !== 'system') {
                $filtered[] = $message;
            } elseif (is_string($message)) {
                $filtered[] = ['role' => 'user', 'content' => $message];
            }
        }
        
        return $filtered;
    }
    
    /**
     * Mesajları Claude formatına dönüştür
     * 
     * @param array $messages
     * @return array
     */
    private function format_messages_for_claude($messages) {
        $formatted = [];
        
        foreach ($messages as $message) {
            $role = $message['role'];
            
            // Claude rol dönüşümü
            if ($role === 'assistant') {
                $role = 'assistant';
            } elseif ($role === 'user') {
                $role = 'user';
            } else {
                continue;
            }
            
            $formatted[] = [
                'role' => $role,
                'content' => $message['content']
            ];
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
        if (!isset($response['content'])) {
            return false;
        }
        
        $text = '';
        foreach ($response['content'] as $content) {
            if ($content['type'] === 'text') {
                $text .= $content['text'];
            }
        }
        
        return [
            'success' => true,
            'message' => $text,
            'role' => 'assistant',
            'finish_reason' => $response['stop_reason'] ?? '',
            'model' => $response['model'] ?? $this->model,
            'usage' => [
                'prompt_tokens' => $response['usage']['input_tokens'] ?? 0,
                'completion_tokens' => $response['usage']['output_tokens'] ?? 0,
                'total_tokens' => ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0)
            ]
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