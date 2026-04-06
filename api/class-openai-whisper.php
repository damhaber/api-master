<?php
/**
 * OpenAI Whisper API Class for Masal Panel
 * 
 * Speech-to-text, audio transcription and translation
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Direct access prevention
}

class APIMaster_API_OpenAIWhisper implements APIMaster_APIInterface
{
    /**
     * API Key
     * @var string
     */
    private $apiKey;
    
    /**
     * Model (whisper-1)
     * @var string
     */
    private $model;
    
    /**
     * Config array
     * @var array
     */
    private $config;
    
    /**
     * API base URL
     * @var string
     */
    private $apiUrl = 'https://api.openai.com/v1/audio';
    
    /**
     * Default model
     * @var string
     */
    private $defaultModel = 'whisper-1';
    
    /**
     * Supported models
     * @var array
     */
    private $supportedModels = ['whisper-1'];
    
    /**
     * Supported audio formats
     * @var array
     */
    private $supportedFormats = ['mp3', 'mp4', 'mpeg', 'mpga', 'm4a', 'wav', 'webm'];
    
    /**
     * Supported response formats
     * @var array
     */
    private $responseFormats = ['json', 'text', 'srt', 'verbose_json', 'vtt'];
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->model = $this->config['model'] ?? $this->defaultModel;
    }
    
    /**
     * Load configuration from JSON file
     * 
     * @return array Configuration data
     */
    private function loadConfig()
    {
        $configFile = __DIR__ . '/config/openai-whisper.json';
        
        if (file_exists($configFile)) {
            $content = file_get_contents($configFile);
            return json_decode($content, true) ?: [];
        }
        
        return [];
    }
    
    /**
     * Log error to file
     * 
     * @param string $message Error message
     * @param array $context Additional context
     */
    private function logError($message, $context = [])
    {
        $logDir = __DIR__ . '/logs';
        $logFile = $logDir . '/openai-whisper-error.log';
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logEntry = date('Y-m-d H:i:s') . ' - ' . $message;
        if (!empty($context)) {
            $logEntry .= ' - ' . json_encode($context);
        }
        $logEntry .= PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Generate random boundary for multipart form data
     * 
     * @return string Boundary string
     */
    private function generateBoundary()
    {
        return '----' . md5(uniqid() . microtime());
    }
    
    /**
     * Make curl request to OpenAI Whisper API
     * 
     * @param string $url Full URL
     * @param string $method HTTP method
     * @param array|string $data Request data
     * @param array $headers Additional headers
     * @return array|string|false Response (array for JSON, string for text/srt/vtt)
     */
    private function curlRequest($url, $method = 'POST', $data = null, $headers = [])
    {
        $ch = curl_init();
        
        $method = strtoupper($method);
        
        // Default headers
        $defaultHeaders = [
            'Authorization: Bearer ' . $this->apiKey,
        ];
        
        $allHeaders = array_merge($defaultHeaders, $headers);
        
        // Set common options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Audio files need more time
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        
        // Set method specific options
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                }
                break;
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        curl_close($ch);
        
        if ($curlError) {
            $this->logError('CURL Error', ['error' => $curlError, 'url' => $url]);
            return false;
        }
        
        if ($httpCode >= 400) {
            $decoded = json_decode($response, true);
            $errorMsg = isset($decoded['error']['message']) ? $decoded['error']['message'] : 'HTTP ' . $httpCode;
            $this->logError('API Error', ['status' => $httpCode, 'error' => $errorMsg, 'url' => $url]);
            return false;
        }
        
        // Check if response is JSON
        $decoded = json_decode($response, true);
        if ($decoded !== null) {
            return $decoded;
        }
        
        // Return raw response for text/srt/vtt formats
        return $response;
    }
    
    /**
     * Validate file
     * 
     * @param string $filePath File path
     * @return bool|string False or error message
     */
    private function validateFile($filePath)
    {
        if (!file_exists($filePath)) {
            return 'File not found: ' . $filePath;
        }
        
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->supportedFormats)) {
            return 'Unsupported file format. Supported: ' . implode(', ', $this->supportedFormats);
        }
        
        $fileSize = filesize($filePath);
        $maxSize = 25 * 1024 * 1024; // 25MB
        if ($fileSize > $maxSize) {
            return 'File too large. Maximum size: 25MB';
        }
        
        return true;
    }
    
    /**
     * Prepare multipart form data for file upload
     * 
     * @param string $filePath File path
     * @param array $options Request options
     * @return array [body, contentType]
     */
    private function prepareMultipartData($filePath, $options = [])
    {
        $boundary = $this->generateBoundary();
        $body = '';
        
        // Add file
        $filename = basename($filePath);
        $mimeType = mime_content_type($filePath) ?: 'audio/mpeg';
        $fileContent = file_get_contents($filePath);
        
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: {$mimeType}\r\n\r\n";
        $body .= $fileContent . "\r\n";
        
        // Add model
        $model = $options['model'] ?? $this->model;
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
        $body .= $model . "\r\n";
        
        // Add response_format
        if (isset($options['response_format'])) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"response_format\"\r\n\r\n";
            $body .= $options['response_format'] . "\r\n";
        }
        
        // Add language (optional)
        if (isset($options['language'])) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"language\"\r\n\r\n";
            $body .= $options['language'] . "\r\n";
        }
        
        // Add prompt (optional)
        if (isset($options['prompt'])) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"prompt\"\r\n\r\n";
            $body .= $options['prompt'] . "\r\n";
        }
        
        // Add temperature (optional)
        if (isset($options['temperature'])) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"temperature\"\r\n\r\n";
            $body .= (string)$options['temperature'] . "\r\n";
        }
        
        // Close boundary
        $body .= "--{$boundary}--\r\n";
        
        $contentType = 'multipart/form-data; boundary=' . $boundary;
        
        return [$body, $contentType];
    }
    
    /**
     * Set API key
     * 
     * @param string $apiKey OpenAI API key
     * @return void
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }
    
    /**
     * Set model
     * 
     * @param string $model Model name
     * @return void
     */
    public function setModel($model)
    {
        if (in_array($model, $this->supportedModels)) {
            $this->model = $model;
        } else {
            $this->logError('Unsupported model', ['model' => $model]);
        }
    }
    
    /**
     * Get current model
     * 
     * @return string Current model
     */
    public function getModel()
    {
        return $this->model;
    }
    
    /**
     * Complete request (generic method)
     * 
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @return array|false Response
     */
    public function complete($endpoint, $params = [])
    {
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        
        if ($endpoint === 'transcriptions' || $endpoint === 'translations') {
            if (!isset($params['file'])) {
                $this->logError('File parameter required for transcriptions/translations');
                return false;
            }
            
            $validation = $this->validateFile($params['file']);
            if ($validation !== true) {
                $this->logError($validation);
                return false;
            }
            
            list($body, $contentType) = $this->prepareMultipartData($params['file'], $params);
            
            return $this->curlRequest($url, 'POST', $body, ['Content-Type: ' . $contentType]);
        }
        
        return false;
    }
    
    /**
     * Stream (not supported by Whisper API)
     * 
     * @param string $endpoint API endpoint
     * @param callable $callback Callback function
     * @return void
     */
    public function stream($endpoint, $callback)
    {
        $this->logError('Stream method not supported by OpenAI Whisper API');
    }
    
    /**
     * Get available models
     * 
     * @return array List of supported models
     */
    public function getModels()
    {
        return $this->supportedModels;
    }
    
    /**
     * Get API capabilities
     * 
     * @return array Capabilities list
     */
    public function getCapabilities()
    {
        return [
            'transcription' => ['create', 'read'],
            'translation' => ['create'],
            'supported_formats' => $this->supportedFormats,
            'response_formats' => $this->responseFormats,
            'max_file_size_mb' => 25,
            'languages' => [
                'auto' => 'Automatic detection',
                'af' => 'Afrikaans', 'ar' => 'Arabic', 'hy' => 'Armenian', 'az' => 'Azerbaijani',
                'be' => 'Belarusian', 'bs' => 'Bosnian', 'bg' => 'Bulgarian', 'ca' => 'Catalan',
                'zh' => 'Chinese', 'hr' => 'Croatian', 'cs' => 'Czech', 'da' => 'Danish',
                'nl' => 'Dutch', 'en' => 'English', 'et' => 'Estonian', 'fi' => 'Finnish',
                'fr' => 'French', 'gl' => 'Galician', 'de' => 'German', 'el' => 'Greek',
                'he' => 'Hebrew', 'hi' => 'Hindi', 'hu' => 'Hungarian', 'is' => 'Icelandic',
                'id' => 'Indonesian', 'it' => 'Italian', 'ja' => 'Japanese', 'kn' => 'Kannada',
                'kk' => 'Kazakh', 'ko' => 'Korean', 'lv' => 'Latvian', 'lt' => 'Lithuanian',
                'mk' => 'Macedonian', 'ms' => 'Malay', 'mr' => 'Marathi', 'mi' => 'Maori',
                'ne' => 'Nepali', 'no' => 'Norwegian', 'fa' => 'Persian', 'pl' => 'Polish',
                'pt' => 'Portuguese', 'ro' => 'Romanian', 'ru' => 'Russian', 'sr' => 'Serbian',
                'sk' => 'Slovak', 'sl' => 'Slovenian', 'es' => 'Spanish', 'sw' => 'Swahili',
                'sv' => 'Swedish', 'tl' => 'Tagalog', 'ta' => 'Tamil', 'th' => 'Thai',
                'tr' => 'Turkish', 'uk' => 'Ukrainian', 'ur' => 'Urdu', 'vi' => 'Vietnamese',
                'cy' => 'Welsh'
            ]
        ];
    }
    
    /**
     * Check API health
     * 
     * @return bool Connection successful
     */
    public function checkHealth()
    {
        // Simple test by listing models (if we had that endpoint)
        // For now, just check if API key is set
        return !empty($this->apiKey);
    }
    
    /**
     * Chat (not supported by Whisper API)
     * 
     * @param string $message Message content
     * @param array $context Context parameters
     * @return array|false Response
     */
    public function chat($message, $context = [])
    {
        $this->logError('Chat method not supported by OpenAI Whisper API. Use transcribe() or translate() instead.');
        return false;
    }
    
    /**
     * Extract text from response
     * 
     * @param array|string $response API response
     * @return string Extracted text
     */
    public function extractText($response)
    {
        if (is_string($response)) {
            return $response;
        }
        
        if (is_array($response)) {
            if (isset($response['text'])) {
                return $response['text'];
            }
            if (isset($response['transcription'])) {
                return $response['transcription'];
            }
            return json_encode($response);
        }
        
        return '';
    }
    
    // ========== OPENAI WHISPER SPECIFIC METHODS ==========
    
    /**
     * Transcribe audio to text
     * 
     * @param string $filePath Path to audio file
     * @param array $options Transcription options
     * @return array|string|false Transcription result
     */
    public function transcribe($filePath, $options = [])
    {
        $validation = $this->validateFile($filePath);
        if ($validation !== true) {
            $this->logError($validation);
            return false;
        }
        
        $defaultOptions = [
            'model' => $this->model,
            'response_format' => 'json',
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        list($body, $contentType) = $this->prepareMultipartData($filePath, $options);
        
        $url = $this->apiUrl . '/transcriptions';
        $response = $this->curlRequest($url, 'POST', $body, ['Content-Type: ' . $contentType]);
        
        if ($response && isset($response['text'])) {
            $this->logError('Transcription successful', ['file' => basename($filePath)]);
        }
        
        return $response;
    }
    
    /**
     * Translate audio to English text
     * 
     * @param string $filePath Path to audio file
     * @param array $options Translation options
     * @return array|string|false Translation result
     */
    public function translate($filePath, $options = [])
    {
        $validation = $this->validateFile($filePath);
        if ($validation !== true) {
            $this->logError($validation);
            return false;
        }
        
        $defaultOptions = [
            'model' => $this->model,
            'response_format' => 'json',
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        list($body, $contentType) = $this->prepareMultipartData($filePath, $options);
        
        $url = $this->apiUrl . '/translations';
        $response = $this->curlRequest($url, 'POST', $body, ['Content-Type: ' . $contentType]);
        
        if ($response && isset($response['text'])) {
            $this->logError('Translation successful', ['file' => basename($filePath)]);
        }
        
        return $response;
    }
    
    /**
     * Transcribe audio with verbose JSON output
     * 
     * @param string $filePath Path to audio file
     * @param array $options Transcription options
     * @return array|false Detailed transcription with timestamps
     */
    public function transcribeVerbose($filePath, $options = [])
    {
        $options['response_format'] = 'verbose_json';
        return $this->transcribe($filePath, $options);
    }
    
    /**
     * Transcribe audio and return as SRT subtitle format
     * 
     * @param string $filePath Path to audio file
     * @param array $options Transcription options
     * @return string|false SRT formatted text
     */
    public function transcribeAsSRT($filePath, $options = [])
    {
        $options['response_format'] = 'srt';
        return $this->transcribe($filePath, $options);
    }
    
    /**
     * Transcribe audio and return as VTT subtitle format
     * 
     * @param string $filePath Path to audio file
     * @param array $options Transcription options
     * @return string|false VTT formatted text
     */
    public function transcribeAsVTT($filePath, $options = [])
    {
        $options['response_format'] = 'vtt';
        return $this->transcribe($filePath, $options);
    }
    
    /**
     * Transcribe audio with prompt guidance
     * 
     * @param string $filePath Path to audio file
     * @param string $prompt Prompt to guide transcription
     * @param array $options Additional options
     * @return array|string|false Transcription result
     */
    public function transcribeWithPrompt($filePath, $prompt, $options = [])
    {
        $options['prompt'] = $prompt;
        return $this->transcribe($filePath, $options);
    }
    
    /**
     * Transcribe audio with specific language
     * 
     * @param string $filePath Path to audio file
     * @param string $language Language code (e.g., 'tr', 'en', 'fr')
     * @param array $options Additional options
     * @return array|string|false Transcription result
     */
    public function transcribeWithLanguage($filePath, $language, $options = [])
    {
        $options['language'] = $language;
        return $this->transcribe($filePath, $options);
    }
    
    /**
     * Transcribe audio with temperature control
     * 
     * @param string $filePath Path to audio file
     * @param float $temperature Temperature (0-1)
     * @param array $options Additional options
     * @return array|string|false Transcription result
     */
    public function transcribeWithTemperature($filePath, $temperature, $options = [])
    {
        $options['temperature'] = max(0, min(1, $temperature));
        return $this->transcribe($filePath, $options);
    }
    
    /**
     * Get supported audio formats
     * 
     * @return array List of supported formats
     */
    public function getSupportedFormats()
    {
        return $this->supportedFormats;
    }
    
    /**
     * Get supported response formats
     * 
     * @return array List of response formats
     */
    public function getResponseFormats()
    {
        return $this->responseFormats;
    }
    
    /**
     * Get supported languages
     * 
     * @return array List of supported language codes
     */
    public function getSupportedLanguages()
    {
        $capabilities = $this->getCapabilities();
        return $capabilities['languages'];
    }
}