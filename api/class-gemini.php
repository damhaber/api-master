<?php
/**
 * API Master Module - Google Gemini Integration
 * Google Gemini AI API entegrasyonu
 */

if (!defined('ABSPATH')) {
    exit; // Doğrudan erişim engellendi
}

/**
 * Google Gemini Provider Sınıfı
 * API_Interface implementasyonu
 */
class APIMaster_Gemini implements API_Interface {
    
    /**
     * @var string API anahtarı
     */
    private $api_key;
    
    /**
     * @var string Model adı
     */
    private $model = 'gemini-pro';
    
    /**
     * @var string API endpoint
     */
    private $endpoint = 'https://generativelanguage.googleapis.com/v1';
    
    /**
     * @var int Zaman aşımı (saniye)
     */
    private $timeout = 30;
    
    /**
     * @var int Maksimum token
     */
    private $max_tokens = 2048;
    
    /**
     * @var float Sıcaklık değeri
     */
    private $temperature = 0.7;
    
    /**
     * @var float Top p değeri
     */
    private $top_p = 0.95;
    
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
        'gemini-pro' => 'Gemini Pro',
        'gemini-pro-vision' => 'Gemini Pro Vision',
        'gemini-1.5-pro' => 'Gemini 1.5 Pro',
        'gemini-1.5-flash' => 'Gemini 1.5 Flash'
    ];
    
    /**
     * @var array Model özellikleri
     */
    private $model_features = [
        'gemini-pro' => ['max_tokens' => 2048, 'vision' => false],
        'gemini-pro-vision' => ['max_tokens' => 2048, 'vision' => true],
        'gemini-1.5-pro' => ['max_tokens' => 8192, 'vision' => true],
        'gemini-1.5-flash' => ['max_tokens' => 8192, 'vision' => true]
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
        $temperature = $options['temperature'] ?? $this->temperature;
        $max_tokens = $options['max_tokens'] ?? $this->max_tokens;
        
        // Gemini formatına dönüştür
        $contents = $this->format_messages_for_gemini($messages);
        
        $data = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $temperature,
                'topP' => $options['top_p'] ?? $this->top_p,
                'topK' => $options['top_k'] ?? $this->top_k,
                'maxOutputTokens' => $max_tokens,
                'candidateCount' => $options['candidate_count'] ?? 1
            ]
        ];
        
        // System instruction (Gemini 1.5 ve üzeri)
        $system_instruction = $this->extract_system_instruction($messages);
        if ($this->supports_system_instruction($model) && $system_instruction) {
            $data['systemInstruction'] = [
                'parts' => [['text' => $system_instruction]]
            ];
        }
        
        $response = $this->make_request('/models/' . $model . ':generateContent', $data);
        
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
        
        $contents = $this->format_messages_for_gemini($messages);
        
        $data = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $temperature,
                'topP' => $options['top_p'] ?? $this->top_p,
                'topK' => $options['top_k'] ?? $this->top_k,
                'maxOutputTokens' => $max_tokens,
                'candidateCount' => $options['candidate_count'] ?? 1
            ]
        ];
        
        return $this->make_stream_request('/models/' . $model . ':streamGenerateContent', $data, $callback);
    }
    
    /**
     * {@inheritdoc}
     */
    public function create_embedding($text, $options = []) {
        $model = $options['model'] ?? 'embedding-001';
        $endpoint = '/models/' . $model . ':embedContent';
        
        $data = [
            'model' => 'models/' . $model,
            'content' => [
                'parts' => [['text' => $text]]
            ]
        ];
        
        try {
            $response = $this->make_request($endpoint, $data);
            
            return [
                'success' => true,
                'embedding' => $response['embedding']['values'] ?? [],
                'model' => $model
            ];
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function get_models() {
        $models = [];
        
        foreach ($this->supported_models as $key => $name) {
            $features = $this->model_features[$key] ?? ['max_tokens' => 2048, 'vision' => false];
            
            $models[] = [
                'id' => $key,
                'name' => $name,
                'max_tokens' => $features['max_tokens'],
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
        return $this->config['name'] ?? 'Google Gemini';
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
        $model_name = $model ?? $this->model;
        
        try {
            $data = [
                'model' => 'models/' . $model_name,
                'contents' => [
                    'parts' => [['text' => $text]]
                ]
            ];
            
            $response = $this->make_request('/models/' . $model_name . ':countTokens', $data);
            return $response['totalTokens'] ?? 0;
            
        } catch (Exception $e) {
            // Yaklaşık hesaplama (1 token ≈ 4 karakter)
            return (int)ceil(mb_strlen($text) / 4);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function get_stats() {
        $features = $this->model_features[$this->model] ?? ['max_tokens' => 2048, 'vision' => false];
        
        return [
            'provider' => 'gemini',
            'model' => $this->model,
            'enabled' => $this->enabled,
            'has_api_key' => !empty($this->api_key),
            'max_tokens' => $features['max_tokens'],
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
     * @param string $method
     * @return array
     * @throws Exception
     */
    private function make_request($endpoint, $data = [], $method = 'POST') {
        if (empty($this->api_key)) {
            throw new Exception('Gemini API anahtarı ayarlanmamış');
        }
        
        $url = $this->endpoint . $endpoint . '?key=' . $this->api_key;
        
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json'
        ];
        
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
            $url .= '&' . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Hatası: {$error}");
        }
        
        return $this->parse_response($response, $http_code);
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
        
        $url = $this->endpoint . $endpoint . '?key=' . $this->api_key;
        
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => $this->timeout,
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
                    
                    $json = json_decode($line, true);
                    
                    if ($json && isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                        call_user_func($callback, [
                            'chunk' => $json['candidates'][0]['content']['parts'][0]['text'],
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
     * Yanıtı parse et
     * 
     * @param string $response
     * @param int $status_code
     * @return array
     * @throws Exception
     */
    private function parse_response($response, $status_code) {
        $data = json_decode($response, true);
        
        if ($status_code !== 200) {
            $error = isset($data['error']) ? $data['error'] : ['message' => 'Bilinmeyen hata'];
            $error_message = $error['message'] ?? json_encode($error);
            throw new Exception($error_message, $status_code);
        }
        
        return $data;
    }
    
    /**
     * Mesajları Gemini formatına dönüştür
     * 
     * @param array $messages
     * @return array
     */
    private function format_messages_for_gemini($messages) {
        $contents = [];
        
        foreach ($messages as $message) {
            if (is_string($message)) {
                $contents[] = [
                    'role' => 'user',
                    'parts' => [['text' => $message]]
                ];
            } elseif ($message['role'] !== 'system') {
                $role = $message['role'] === 'user' ? 'user' : 'model';
                
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $message['content']]]
                ];
            }
        }
        
        return $contents;
    }
    
    /**
     * System instruction'u çıkar
     * 
     * @param array $messages
     * @return string|null
     */
    private function extract_system_instruction($messages) {
        foreach ($messages as $message) {
            if (is_array($message) && isset($message['role']) && $message['role'] === 'system') {
                return $message['content'];
            }
        }
        
        return null;
    }
    
    /**
     * System instruction desteği var mı?
     * 
     * @param string $model
     * @return bool
     */
    private function supports_system_instruction($model) {
        return strpos($model, '1.5') !== false;
    }
    
    /**
     * Chat yanıtını formatla
     * 
     * @param array $response
     * @return array|false
     */
    private function format_chat_response($response) {
        if (!isset($response['candidates'][0])) {
            return false;
        }
        
        $candidate = $response['candidates'][0];
        $content = $candidate['content'] ?? [];
        $parts = $content['parts'] ?? [];
        
        $text = '';
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
        }
        
        return [
            'success' => true,
            'message' => $text,
            'role' => 'assistant',
            'finish_reason' => $candidate['finishReason'] ?? '',
            'safety_ratings' => $candidate['safetyRatings'] ?? [],
            'model' => $this->model,
            'usage' => $response['usageMetadata'] ?? []
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