<?php
/**
 * API Master Module - Translate API (Google Cloud Translation)
 * Metin çeviri ve dil tespiti sağlayıcısı
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Translate implements APIMaster_APIInterface {
    
    /**
     * API Base URL
     * @var string
     */
    private $baseUrl = 'https://translation.googleapis.com/language/translate/v2';
    
    /**
     * API Key
     * @var string|null
     */
    private $apiKey = null;
    
    /**
     * Current model (for interface compatibility)
     * @var string|null
     */
    private $model = null;
    
    /**
     * Default source language
     * @var string
     */
    private $defaultSource = 'auto';
    
    /**
     * Default target language
     * @var string
     */
    private $defaultTarget = 'tr';
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct(array $config = []) {
        if (isset($config['api_key'])) {
            $this->setApiKey($config['api_key']);
        }
        
        if (isset($config['default_source'])) {
            $this->defaultSource = $config['default_source'];
        }
        
        if (isset($config['default_target'])) {
            $this->defaultTarget = $config['default_target'];
        }
    }
    
    /**
     * Set API Key
     * 
     * @param string $apiKey
     * @return self
     */
    public function setApiKey($apiKey) {
        $this->apiKey = $apiKey;
        return $this;
    }
    
    /**
     * Set Model (for interface compatibility)
     * 
     * @param string $model
     * @return self
     */
    public function setModel($model) {
        $this->model = $model;
        return $this;
    }
    
    /**
     * Get Current Model
     * 
     * @return string|null
     */
    public function getModel() {
        return $this->model;
    }
    
    /**
     * Complete method - Translate text
     * 
     * @param string $prompt Text to translate
     * @param array $options Options (source, target, format)
     * @return array Translation result
     */
    public function complete($prompt, $options = []) {
        if (!$this->apiKey) {
            return ['error' => 'API key is required'];
        }
        
        return $this->translate($prompt, $options);
    }
    
    /**
     * Stream method (not supported by translate API)
     * 
     * @param string $prompt Text to translate
     * @param callable $callback Callback function
     * @param array $options Options
     * @return void
     */
    public function stream($prompt, $callback, $options = []) {
        $result = $this->complete($prompt, $options);
        if (is_callable($callback)) {
            $callback(json_encode($result));
        }
    }
    
    /**
     * Get Available Models
     * 
     * @return array
     */
    public function getModels() {
        return [
            'nmt' => 'Neural Machine Translation',
            'base' => 'Base Translation Model'
        ];
    }
    
    /**
     * Get Provider Capabilities
     * 
     * @return array
     */
    public function getCapabilities() {
        return [
            'streaming' => false,
            'chat' => false,
            'completion' => true,
            'models' => true,
            'max_tokens' => null,
            'functions' => false,
            'vision' => false,
            'supported_features' => [
                'text_translation',
                'language_detection',
                'batch_translation',
                'html_translation'
            ]
        ];
    }
    
    /**
     * Check API Health
     * 
     * @return array
     */
    public function checkHealth() {
        if (!$this->apiKey) {
            return ['status' => 'error', 'message' => 'API key not configured'];
        }
        
        // Test with a simple translation
        $result = $this->translate('Hello world', ['target' => 'tr']);
        
        if (isset($result['error'])) {
            return ['status' => 'error', 'message' => $result['error']];
        }
        
        if (isset($result['success']) && $result['success'] === true) {
            return ['status' => 'healthy', 'message' => 'API is working'];
        }
        
        return ['status' => 'error', 'message' => 'API returned unexpected response'];
    }
    
    /**
     * Chat method (not supported)
     * 
     * @param array $messages Messages array
     * @param array $options Options
     * @param callable|null $callback Callback for streaming
     * @return array
     */
    public function chat($messages, $options = [], $callback = null) {
        return [
            'error' => 'Chat method is not supported by Translate API',
            'supported_methods' => ['complete', 'translate', 'detectLanguage']
        ];
    }
    
    /**
     * Extract Text from Response
     * 
     * @param array $response Translation response
     * @return string
     */
    public function extractText($response) {
        if (isset($response['error'])) {
            return $response['error'];
        }
        
        if (isset($response['translated_text'])) {
            return $response['translated_text'];
        }
        
        if (isset($response['translations'][0]['translated_text'])) {
            return $response['translations'][0]['translated_text'];
        }
        
        return json_encode($response);
    }
    
    /**
     * Translate Text
     * 
     * @param string|array $text Text or array of texts to translate
     * @param array $options Options (source, target, format, model)
     * @return array Translation result
     */
    public function translate($text, $options = []) {
        $source = $options['source'] ?? $this->defaultSource;
        $target = $options['target'] ?? $this->defaultTarget;
        $format = $options['format'] ?? 'text';
        $model = $options['model'] ?? null;
        
        $params = [
            'q' => $text,
            'source' => $source,
            'target' => $target,
            'format' => $format,
            'key' => $this->apiKey
        ];
        
        if ($model && $model !== 'auto') {
            $params['model'] = $model;
        }
        
        $result = $this->makeRequest($params);
        
        if (isset($result['error'])) {
            return $result;
        }
        
        return $this->parseTranslateResponse($result, $text, $source, $target);
    }
    
    /**
     * Detect Language
     * 
     * @param string|array $text Text to detect
     * @return array Detection result
     */
    public function detectLanguage($text) {
        $isMultiple = is_array($text);
        
        $params = [
            'q' => $text,
            'key' => $this->apiKey
        ];
        
        $url = $this->baseUrl . '/detect?' . http_build_query($params);
        $result = $this->makeHttpRequest($url);
        
        if (isset($result['error'])) {
            return $result;
        }
        
        if (isset($result['data']['detections'])) {
            $detections = [];
            foreach ($result['data']['detections'] as $index => $detectionGroup) {
                $best = $detectionGroup[0];
                $detections[] = [
                    'language' => $best['language'],
                    'confidence' => $best['confidence'],
                    'is_reliable' => $best['isReliable'] ?? false,
                    'text' => $isMultiple ? $text[$index] : $text
                ];
            }
            
            return [
                'success' => true,
                'detections' => $detections,
                'detected_language' => $detections[0]['language'],
                'confidence' => $detections[0]['confidence']
            ];
        }
        
        return ['error' => 'Invalid response from API'];
    }
    
    /**
     * Get Supported Languages
     * 
     * @param string|null $displayLanguage Language to display names in
     * @return array Languages list
     */
    public function getSupportedLanguages($displayLanguage = null) {
        $target = $displayLanguage ?? $this->defaultTarget;
        
        $params = [
            'target' => $target,
            'key' => $this->apiKey
        ];
        
        $url = $this->baseUrl . '/languages?' . http_build_query($params);
        $result = $this->makeHttpRequest($url);
        
        if (isset($result['error'])) {
            return $result;
        }
        
        if (isset($result['data']['languages'])) {
            $languages = [];
            foreach ($result['data']['languages'] as $lang) {
                $languages[] = [
                    'code' => $lang['language'],
                    'name' => $lang['name'] ?? $this->getLanguageName($lang['language'])
                ];
            }
            
            return [
                'success' => true,
                'languages' => $languages
            ];
        }
        
        return ['error' => 'Invalid response from API'];
    }
    
    /**
     * Batch Translate Multiple Texts
     * 
     * @param array $texts Array of texts to translate
     * @param array $options Options
     * @return array Translation results
     */
    public function batchTranslate($texts, $options = []) {
        return $this->translate($texts, $options);
    }
    
    /**
     * Translate HTML Content
     * 
     * @param string $html HTML content
     * @param array $options Options
     * @return array Translated HTML
     */
    public function translateHtml($html, $options = []) {
        $options['format'] = 'html';
        return $this->translate($html, $options);
    }
    
    /**
     * Smart Translate - Auto-detect source language
     * 
     * @param string $text Text to translate
     * @param string $targetLanguage Target language
     * @return array Translation result
     */
    public function smartTranslate($text, $targetLanguage = null) {
        $target = $targetLanguage ?? $this->defaultTarget;
        
        // Detect source language
        $detection = $this->detectLanguage($text);
        
        if (isset($detection['error'])) {
            return $detection;
        }
        
        $sourceLang = $detection['detected_language'];
        
        // No translation needed if same language
        if ($sourceLang === $target) {
            return [
                'success' => true,
                'translated_text' => $text,
                'detected_source_language' => $sourceLang,
                'target_language' => $target,
                'note' => 'Source and target languages are the same'
            ];
        }
        
        // Translate
        return $this->translate($text, [
            'source' => $sourceLang,
            'target' => $target
        ]);
    }
    
    /**
     * Parse Translation Response
     * 
     * @param array $response API response
     * @param string|array $originalText Original text
     * @param string $source Source language
     * @param string $target Target language
     * @return array Parsed response
     */
    private function parseTranslateResponse($response, $originalText, $source, $target) {
        $isMultiple = is_array($originalText);
        
        if (!isset($response['data']['translations'])) {
            return ['error' => 'Invalid response from API'];
        }
        
        $translations = [];
        foreach ($response['data']['translations'] as $index => $translation) {
            $translations[] = [
                'translated_text' => $translation['translatedText'],
                'detected_source_language' => $translation['detectedSourceLanguage'] ?? null,
                'original_text' => $isMultiple ? $originalText[$index] : $originalText,
                'source_language' => $source,
                'target_language' => $target
            ];
        }
        
        $result = [
            'success' => true,
            'translations' => $translations
        ];
        
        if (!$isMultiple && count($translations) === 1) {
            $result['translated_text'] = $translations[0]['translated_text'];
            $result['detected_source_language'] = $translations[0]['detected_source_language'];
        }
        
        return $result;
    }
    
    /**
     * Make API Request (POST)
     * 
     * @param array $params Request parameters
     * @return array Response data
     */
    private function makeRequest($params) {
        $url = $this->baseUrl . '?key=' . $this->apiKey;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['error' => $error, 'code' => $httpCode];
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode === 200 && isset($data['data'])) {
            return $data;
        }
        
        return [
            'error' => $data['error']['message'] ?? 'Unknown error',
            'code' => $httpCode,
            'status' => $data['error']['status'] ?? null
        ];
    }
    
    /**
     * Make HTTP GET Request
     * 
     * @param string $url Request URL
     * @return array Response data
     */
    private function makeHttpRequest($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['error' => $error, 'code' => $httpCode];
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode === 200 && isset($data['data'])) {
            return $data;
        }
        
        return [
            'error' => $data['error']['message'] ?? 'Unknown error',
            'code' => $httpCode
        ];
    }
    
    /**
     * Get Language Name by Code
     * 
     * @param string $code Language code
     * @return string Language name
     */
    private function getLanguageName($code) {
        $languages = [
            'tr' => 'Türkçe',
            'en' => 'English',
            'de' => 'German',
            'fr' => 'French',
            'es' => 'Spanish',
            'it' => 'Italian',
            'ru' => 'Russian',
            'ar' => 'Arabic',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'pl' => 'Polish',
            'sv' => 'Swedish',
            'da' => 'Danish',
            'no' => 'Norwegian',
            'fi' => 'Finnish',
            'el' => 'Greek',
            'cs' => 'Czech',
            'hu' => 'Hungarian',
            'ro' => 'Romanian',
            'uk' => 'Ukrainian',
            'he' => 'Hebrew',
            'hi' => 'Hindi',
            'th' => 'Thai',
            'vi' => 'Vietnamese',
            'id' => 'Indonesian',
            'ms' => 'Malay'
        ];
        
        return $languages[$code] ?? strtoupper($code);
    }
}