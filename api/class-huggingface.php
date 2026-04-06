<?php
/**
 * API Master Module - Hugging Face API
 * Inference API, Transformers, 200k+ model desteği
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_HuggingFace implements APIMaster_APIInterface {
    
    /**
     * API base URL
     *
     * @var string
     */
    private $api_url = 'https://api-inference.huggingface.co/models';
    
    /**
     * API key
     *
     * @var string
     */
    private $api_key;
    
    /**
     * Request timeout in seconds
     *
     * @var int
     */
    private $timeout = 60;
    
    /**
     * Use inference endpoint
     *
     * @var bool
     */
    private $use_inference_endpoint = true;
    
    /**
     * Custom inference endpoint URL
     *
     * @var string|null
     */
    private $custom_endpoint = null;
    
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
            'api_key' => '',
            'timeout' => 60,
            'max_retries' => 3
        ], $config);
        
        $this->api_key = $this->config['api_key'];
        $this->timeout = $this->config['timeout'];
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
     * Send a chat completion request (text generation)
     *
     * @param array $params Request parameters
     * @return array|false Response or false on error
     */
    public function sendMessage($params) {
        if (empty($this->api_key)) {
            return false;
        }
        
        if (empty($params['model']) || empty($params['inputs'])) {
            return false;
        }
        
        $model = $params['model'];
        $stream = $params['stream'] ?? false;
        
        $request = [
            'inputs' => $params['inputs']
        ];
        
        if (isset($params['parameters'])) {
            $request['parameters'] = $params['parameters'];
        }
        
        if (isset($params['options'])) {
            $request['options'] = $params['options'];
        }
        
        return $this->makeRequest($model, $request, $stream);
    }
    
    /**
     * Make HTTP request to Hugging Face API
     *
     * @param string $model Model name
     * @param array $data Request data
     * @param bool $stream Whether to stream
     * @return array|bool
     */
    private function makeRequest($model, $data, $stream = false) {
        if ($this->custom_endpoint) {
            $url = $this->custom_endpoint;
        } elseif ($this->use_inference_endpoint) {
            $url = $this->api_url . '/' . $model;
        } else {
            $url = 'https://api-inference.huggingface.co/pipeline/' . $model;
        }
        
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, !$stream);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $stream ? 0 : $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        if ($stream) {
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
                echo $data;
                flush();
                return strlen($data);
            });
            curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                return false;
            }
            
            return $http_code === 200;
        }
        
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
     * Text generation
     *
     * @param string $model Model name
     * @param string $prompt Input prompt
     * @param array $params Generation parameters
     * @return array|false
     */
    public function generate($model, $prompt, $params = []) {
        $request = [
            'inputs' => $prompt,
            'parameters' => array_merge([
                'max_new_tokens' => 100,
                'temperature' => 0.7,
                'top_p' => 0.9,
                'do_sample' => true
            ], $params)
        ];
        
        return $this->makeRequest($model, $request);
    }
    
    /**
     * Text classification
     *
     * @param string $model Model name
     * @param string|array $texts Text(s) to classify
     * @return array|false
     */
    public function classify($model, $texts) {
        $inputs = is_array($texts) ? $texts : [$texts];
        $request = ['inputs' => $inputs];
        
        return $this->makeRequest($model, $request);
    }
    
    /**
     * Text embeddings
     *
     * @param string $model Model name
     * @param string|array $texts Text(s) to embed
     * @return array|false
     */
    public function embed($model, $texts) {
        $inputs = is_array($texts) ? $texts : [$texts];
        $request = ['inputs' => $inputs];
        
        $response = $this->makeRequest($model, $request);
        
        if ($response === false) {
            return false;
        }
        
        if (isset($response[0]) && is_array($response[0]) && isset($response[0]['embedding'])) {
            return array_column($response, 'embedding');
        }
        
        return $response;
    }
    
    /**
     * Image generation
     *
     * @param string $model Model name
     * @param string $prompt Image generation prompt
     * @param array $params Generation parameters
     * @return string|false Base64 encoded image or false
     */
    public function generateImage($model, $prompt, $params = []) {
        $request = [
            'inputs' => $prompt,
            'parameters' => $params
        ];
        
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        ];
        
        $url = $this->api_url . '/' . $model;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            return false;
        }
        
        return 'data:' . $content_type . ';base64,' . base64_encode($response);
    }
    
    /**
     * Automatic speech recognition (ASR)
     *
     * @param string $model Model name
     * @param string $audio_path Path to audio file
     * @return array|false
     */
    public function transcribe($model, $audio_path) {
        if (!file_exists($audio_path)) {
            return false;
        }
        
        $audio_data = file_get_contents($audio_path);
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: ' . mime_content_type($audio_path)
        ];
        
        $url = $this->api_url . '/' . $model;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $audio_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            return false;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Zero-shot classification
     *
     * @param string $model Model name
     * @param string $text Text to classify
     * @param array $candidate_labels Possible labels
     * @return array|false
     */
    public function zeroShotClassify($model, $text, $candidate_labels) {
        $request = [
            'inputs' => $text,
            'parameters' => [
                'candidate_labels' => $candidate_labels
            ]
        ];
        
        return $this->makeRequest($model, $request);
    }
    
    /**
     * Question answering
     *
     * @param string $model Model name
     * @param string $question Question
     * @param string $context Context for answering
     * @return array|false
     */
    public function questionAnswering($model, $question, $context) {
        $request = [
            'inputs' => [
                'question' => $question,
                'context' => $context
            ]
        ];
        
        return $this->makeRequest($model, $request);
    }
    
    /**
     * Summarization
     *
     * @param string $model Model name
     * @param string $text Text to summarize
     * @param array $params Summarization parameters
     * @return array|false
     */
    public function summarize($model, $text, $params = []) {
        $request = [
            'inputs' => $text,
            'parameters' => array_merge([
                'max_length' => 150,
                'min_length' => 30,
                'do_sample' => false
            ], $params)
        ];
        
        return $this->makeRequest($model, $request);
    }
    
    /**
     * Translation
     *
     * @param string $model Model name
     * @param string $text Text to translate
     * @return array|false
     */
    public function translate($model, $text) {
        $request = ['inputs' => $text];
        
        return $this->makeRequest($model, $request);
    }
    
    /**
     * Get available models
     *
     * @return array
     */
    public function getModels() {
        return $this->getPopularModels();
    }
    
    /**
     * Get popular models
     *
     * @return array
     */
    private function getPopularModels() {
        return [
            // Text Generation
            [
                'id' => 'mistralai/Mistral-7B-Instruct-v0.2',
                'name' => 'Mistral 7B Instruct',
                'task' => 'text-generation',
                'description' => 'High-performance instruction-tuned model',
                'max_tokens' => 8192
            ],
            [
                'id' => 'meta-llama/Llama-2-7b-chat-hf',
                'name' => 'Llama 2 7B Chat',
                'task' => 'text-generation',
                'description' => 'Meta Llama 2 conversational model',
                'max_tokens' => 4096
            ],
            [
                'id' => 'google/gemma-2b-it',
                'name' => 'Gemma 2B IT',
                'task' => 'text-generation',
                'description' => 'Google Gemma instruction-tuned',
                'max_tokens' => 8192
            ],
            // Text Embeddings
            [
                'id' => 'sentence-transformers/all-MiniLM-L6-v2',
                'name' => 'MiniLM L6 v2',
                'task' => 'feature-extraction',
                'description' => 'Fast and efficient embeddings',
                'dimensions' => 384
            ],
            [
                'id' => 'intfloat/e5-large-v2',
                'name' => 'E5 Large v2',
                'task' => 'feature-extraction',
                'description' => 'High-quality embeddings for search',
                'dimensions' => 1024
            ],
            // Image Generation
            [
                'id' => 'stabilityai/stable-diffusion-2-1',
                'name' => 'Stable Diffusion 2.1',
                'task' => 'text-to-image',
                'description' => 'High-quality image generation'
            ],
            [
                'id' => 'black-forest-labs/FLUX.1-dev',
                'name' => 'FLUX.1 Dev',
                'task' => 'text-to-image',
                'description' => 'Advanced image generation model'
            ],
            // Classification
            [
                'id' => 'cardiffnlp/twitter-roberta-base-sentiment-latest',
                'name' => 'Twitter RoBERTa Sentiment',
                'task' => 'text-classification',
                'description' => 'Sentiment analysis for social media'
            ],
            // Speech Recognition
            [
                'id' => 'openai/whisper-large-v3',
                'name' => 'Whisper Large v3',
                'task' => 'automatic-speech-recognition',
                'description' => 'High-accuracy speech recognition'
            ],
            // Translation
            [
                'id' => 'facebook/nllb-200-distilled-600M',
                'name' => 'NLLB-200',
                'task' => 'translation',
                'description' => '200-language translation model'
            ]
        ];
    }
    
    /**
     * Extract text from response
     *
     * @param array $response API response
     * @return string
     */
    public function extractText($response) {
        if (is_array($response) && isset($response[0]['generated_text'])) {
            return $response[0]['generated_text'];
        }
        
        if (is_array($response) && isset($response['generated_text'])) {
            return $response['generated_text'];
        }
        
        if (is_array($response) && isset($response[0])) {
            return json_encode($response[0]);
        }
        
        if (is_string($response)) {
            return $response;
        }
        
        return json_encode($response);
    }
    
    /**
     * Validate API key
     *
     * @return bool
     */
    public function validateApiKey() {
        if (empty($this->api_key)) {
            return false;
        }
        
        $response = $this->makeRequest('meta-llama/Llama-2-7b-chat-hf', [
            'inputs' => 'Test',
            'parameters' => ['max_new_tokens' => 1]
        ]);
        
        return $response !== false;
    }
    
    /**
     * Set custom inference endpoint
     *
     * @param string $endpoint Custom endpoint URL
     * @return self
     */
    public function setCustomEndpoint($endpoint) {
        $this->custom_endpoint = $endpoint;
        return $this;
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
     * Get API name
     *
     * @return string
     */
    public function getName() {
        return 'Hugging Face (200k+ Models)';
    }
    
    /**
     * Get available tasks
     *
     * @return array
     */
    public function getAvailableTasks() {
        return [
            'text-generation',
            'text-classification',
            'token-classification',
            'question-answering',
            'summarization',
            'translation',
            'feature-extraction',
            'text-to-image',
            'image-classification',
            'automatic-speech-recognition',
            'text-to-speech'
        ];
    }
    
    /**
     * APIInterface: complete metodu
     */
    public function complete($prompt, $options = []) {
        $model = $options['model'] ?? 'mistralai/Mistral-7B-Instruct-v0.2';
        return $this->generate($model, $prompt, $options);
    }
    
    /**
     * APIInterface: stream metodu
     */
    public function stream($prompt, $callback, $options = []) {
        $model = $options['model'] ?? 'mistralai/Mistral-7B-Instruct-v0.2';
        $result = $this->generate($model, $prompt, $options);
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
            'text_generation',
            'text_classification',
            'embeddings',
            'image_generation',
            'speech_recognition',
            'question_answering',
            'summarization',
            'translation'
        ];
    }
    
    /**
     * APIInterface: checkHealth metodu
     */
    public function checkHealth() {
        return $this->validateApiKey();
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
        return 'huggingface_inference_api';
    }
    
    /**
     * APIInterface: chat metodu
     */
    public function chat($messages, $options = []) {
        $last_message = end($messages);
        $prompt = $last_message['content'] ?? '';
        $model = $options['model'] ?? 'mistralai/Mistral-7B-Instruct-v0.2';
        
        // Format messages for instruction-tuned model
        $formatted_prompt = $this->formatChatMessages($messages);
        
        return $this->generate($model, $formatted_prompt, $options);
    }
    
    /**
     * Format chat messages for model
     *
     * @param array $messages Chat messages
     * @return string Formatted prompt
     */
    private function formatChatMessages($messages) {
        $formatted = "";
        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $content = $message['content'] ?? '';
            
            if ($role === 'system') {
                $formatted .= "System: {$content}\n\n";
            } elseif ($role === 'user') {
                $formatted .= "User: {$content}\n";
            } elseif ($role === 'assistant') {
                $formatted .= "Assistant: {$content}\n";
            }
        }
        $formatted .= "Assistant: ";
        
        return $formatted;
    }
}