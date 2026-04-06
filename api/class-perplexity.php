<?php
/**
 * API Master Module - Perplexity API
 * Perplexity AI Sonar models with online search and citations
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Perplexity implements APIMaster_APIInterface {
    
    /**
     * API base URL
     */
    private $api_url = 'https://api.perplexity.ai';
    
    /**
     * API key
     */
    private $api_key;
    
    /**
     * Current model
     */
    private $model = 'sonar-large-online';
    
    /**
     * Request timeout
     */
    private $timeout = 60;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct(array $config = []) {
        $this->api_key = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'sonar-large-online';
        $this->timeout = $config['timeout'] ?? 60;
    }
    
    /**
     * Set API key
     * 
     * @param string $api_key
     * @return self
     */
    public function setApiKey($api_key) {
        $this->api_key = $api_key;
        return $this;
    }
    
    /**
     * Set model
     * 
     * @param string $model
     * @return self
     */
    public function setModel($model) {
        $this->model = $model;
        return $this;
    }
    
    /**
     * Get current model
     * 
     * @return string
     */
    public function getModel() {
        return $this->model;
    }
    
    /**
     * Complete a prompt (non-streaming)
     * 
     * @param string $prompt User prompt
     * @param array $options Additional options
     * @return array|false Response or false on error
     */
    public function complete($prompt, $options = []) {
        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];
        
        return $this->chat($messages, $options);
    }
    
    /**
     * Stream completion
     * 
     * @param string $prompt User prompt
     * @param callable $callback Callback function for each chunk
     * @param array $options Additional options
     * @return bool Success status
     */
    public function stream($prompt, $callback, $options = []) {
        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];
        
        $options['stream'] = true;
        
        return $this->chat($messages, $options, $callback);
    }
    
    /**
     * Chat completion
     * 
     * @param array $messages Chat messages
     * @param array $options Additional options
     * @param callable|null $callback Optional callback for streaming
     * @return array|bool Response or false on error
     */
    public function chat($messages, $options = [], $callback = null) {
        if (empty($this->api_key)) {
            return false;
        }
        
        $request = array_merge([
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 2048,
            'temperature' => $options['temperature'] ?? 0.7,
            'top_p' => $options['top_p'] ?? 0.9,
            'top_k' => $options['top_k'] ?? 0,
            'stream' => $options['stream'] ?? false,
            'return_images' => $options['return_images'] ?? false,
            'return_related_questions' => $options['return_related_questions'] ?? false
        ], $options);
        
        // Check for search domain filter
        if (!empty($options['search_domain_filter'])) {
            $request['search_domain_filter'] = $options['search_domain_filter'];
        }
        
        // Check for recency filter
        if (!empty($options['search_recency_filter'])) {
            $request['search_recency_filter'] = $options['search_recency_filter'];
        }
        
        if ($request['stream'] && is_callable($callback)) {
            return $this->make_stream_request($request, $callback);
        }
        
        return $this->make_request('/chat/completions', $request);
    }
    
    /**
     * Get available models
     * 
     * @return array
     */
    public function getModels() {
        return [
            'sonar-large-online' => [
                'name' => 'Sonar Large Online',
                'max_tokens' => 4096,
                'context_length' => 200000,
                'supports_streaming' => true
            ],
            'sonar-small-online' => [
                'name' => 'Sonar Small Online',
                'max_tokens' => 4096,
                'context_length' => 200000,
                'supports_streaming' => true
            ],
            'sonar-large-chat' => [
                'name' => 'Sonar Large Chat',
                'max_tokens' => 4096,
                'context_length' => 200000,
                'supports_streaming' => true
            ],
            'sonar-small-chat' => [
                'name' => 'Sonar Small Chat',
                'max_tokens' => 4096,
                'context_length' => 200000,
                'supports_streaming' => true
            ],
            'llama-3.1-sonar-large-128k-online' => [
                'name' => 'Llama 3.1 Sonar Large 128K Online',
                'max_tokens' => 4096,
                'context_length' => 128000,
                'supports_streaming' => true
            ],
            'llama-3.1-sonar-small-128k-online' => [
                'name' => 'Llama 3.1 Sonar Small 128K Online',
                'max_tokens' => 4096,
                'context_length' => 128000,
                'supports_streaming' => true
            ]
        ];
    }
    
    /**
     * Get API capabilities
     * 
     * @return array
     */
    public function getCapabilities() {
        return [
            'chat' => true,
            'streaming' => true,
            'citations' => true,
            'online_search' => true,
            'images' => true,
            'related_questions' => true,
            'max_tokens' => 4096,
            'context_length' => 200000
        ];
    }
    
    /**
     * Check API health
     * 
     * @return bool
     */
    public function checkHealth() {
        if (empty($this->api_key)) {
            return false;
        }
        
        $response = $this->make_request('/chat/completions', [
            'model' => 'sonar-small-chat',
            'messages' => [
                ['role' => 'user', 'content' => 'ping']
            ],
            'max_tokens' => 1
        ]);
        
        return $response !== false;
    }
    
    /**
     * Make HTTP request to Perplexity API
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array|false
     */
    private function make_request($endpoint, $data) {
        $url = $this->api_url . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
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
        
        if ($http_code !== 200) {
            return false;
        }
        
        return $decoded;
    }
    
    /**
     * Make streaming request
     * 
     * @param array $data Request data
     * @param callable $callback Callback function
     * @return bool
     */
    private function make_stream_request($data, $callback) {
        $url = $this->api_url . '/chat/completions';
        
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($callback) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, 'data: ') === 0) {
                    $json = substr($line, 6);
                    if ($json === '[DONE]') {
                        call_user_func($callback, ['done' => true]);
                        return strlen($data);
                    }
                    $event = json_decode($json, true);
                    if ($event) {
                        call_user_func($callback, $event);
                    }
                }
            }
            return strlen($data);
        });
        
        curl_exec($ch);
        
        if (curl_error($ch)) {
            curl_close($ch);
            return false;
        }
        
        curl_close($ch);
        return true;
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
        
        return '';
    }
    
    /**
     * Extract citations from response
     * 
     * @param array $response API response
     * @return array
     */
    public function extractCitations($response) {
        if (isset($response['citations'])) {
            return $response['citations'];
        }
        
        if (isset($response['choices'][0]['message']['citations'])) {
            return $response['choices'][0]['message']['citations'];
        }
        
        return [];
    }
}