<?php
/**
 * HeyGen API Class for Masal Panel
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_HeyGen implements APIMaster_APIInterface
{
    private $apiKey;
    private $model;
    private $config;
    private $apiUrl = 'https://api.heygen.com/v1';
    private $timeout = 180;
    
    private $supportedModels = [
        'avatar_talk' => 'Avatar Talk',
        'video_generation' => 'Video Generation',
        'instant_avatar' => 'Instant Avatar'
    ];
    
    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->model = $this->config['model'] ?? 'avatar_talk';
        
        if (isset($this->config['timeout'])) {
            $this->timeout = $this->config['timeout'];
        }
    }
    
    private function loadConfig()
    {
        $configFile = dirname(__DIR__) . '/config/heygen.json';
        
        if (file_exists($configFile)) {
            $content = file_get_contents($configFile);
            return json_decode($content, true) ?: [];
        }
        
        return [];
    }
    
    private function logError($message, $context = [])
    {
        $logDir = dirname(__DIR__) . '/logs';
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/heygen-error.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        
        file_put_contents(
            $logFile,
            "[{$timestamp}] {$message}{$contextStr}" . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
    
    private function curlRequest($url, $method = 'GET', $data = null, $headers = [])
    {
        $ch = curl_init();
        
        $defaultHeaders = [
            'X-Api-Key: ' . $this->apiKey,
            'Content-Type: application/json'
        ];
        
        $allHeaders = array_merge($defaultHeaders, $headers);
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'GET' && $data !== null) {
            $url .= '?' . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        curl_close($ch);
        
        if ($curlError) {
            $this->logError("CURL Error: {$curlError}", ['url' => $url]);
            return ['error' => "CURL Error: {$curlError}"];
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            $errorMsg = isset($decoded['message']) 
                ? $decoded['message'] 
                : (isset($decoded['error']['message']) 
                    ? $decoded['error']['message'] 
                    : "HTTP Error: {$httpCode}");
            $this->logError($errorMsg, ['url' => $url, 'response' => $response]);
            return ['error' => $errorMsg];
        }
        
        return $decoded;
    }
    
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        return true;
    }
    
    public function setModel($model)
    {
        if (isset($this->supportedModels[$model])) {
            $this->model = $model;
            return true;
        }
        return false;
    }
    
    public function getModel()
    {
        return $this->model;
    }
    
    public function complete($endpoint, $params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        $method = $params['method'] ?? 'GET';
        unset($params['method']);
        
        $response = $this->curlRequest($url, $method, !empty($params) ? $params : null);
        
        if (isset($response['error'])) {
            return $response;
        }
        
        return [
            'success' => true,
            'data' => $response,
            'endpoint' => $endpoint
        ];
    }
    
    public function stream($endpoint, $callback)
    {
        $this->logError('Streaming not supported for HeyGen');
        return false;
    }
    
    public function getModels()
    {
        $models = [];
        
        foreach ($this->supportedModels as $id => $name) {
            $models[] = [
                'id' => $id,
                'name' => $name,
                'enabled' => true
            ];
        }
        
        return $models;
    }
    
    public function getCapabilities()
    {
        return [
            'talking_avatar' => true,
            'text_to_speech' => true,
            'video_generation' => true,
            'instant_avatar' => true,
            'streaming' => false,
            'languages' => ['en', 'tr', 'es', 'fr', 'de', 'it', 'pt', 'ja', 'ko', 'zh'],
            'voices' => true,
            'avatars' => true
        ];
    }
    
    public function checkHealth()
    {
        if (empty($this->apiKey)) {
            return [
                'status' => 'error',
                'message' => 'API key not configured'
            ];
        }
        
        $startTime = microtime(true);
        $response = $this->curlRequest($this->apiUrl . '/avatars', 'GET');
        $responseTime = round((microtime(true) - $startTime) * 1000);
        
        if (isset($response['error'])) {
            return [
                'status' => 'error',
                'message' => $response['error'],
                'response_time_ms' => $responseTime
            ];
        }
        
        return [
            'status' => 'healthy',
            'message' => 'API is reachable',
            'response_time_ms' => $responseTime
        ];
    }
    
    public function chat($message, $context = [])
    {
        return $this->createVideo($message, $context);
    }
    
    public function createVideo($text, $options = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $data = [
            'text' => $text,
            'avatar_id' => $options['avatar_id'] ?? 'default',
            'voice_id' => $options['voice_id'] ?? 'en-US-JennyNeural',
            'voice_speed' => $options['voice_speed'] ?? 1.0,
            'background' => $options['background'] ?? '#FFFFFF'
        ];
        
        if (isset($options['avatar_style'])) {
            $data['avatar_style'] = $options['avatar_style'];
        }
        
        if (isset($options['resolution'])) {
            $data['resolution'] = $options['resolution'];
        }
        
        $response = $this->curlRequest($this->apiUrl . '/video/generate', 'POST', $data);
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'video_id' => $response['data']['video_id'] ?? '',
            'status' => $response['data']['status'] ?? 'processing',
            'video_url' => $response['data']['video_url'] ?? '',
            'duration' => $response['data']['duration'] ?? 0
        ];
    }
    
    public function getVideoStatus($videoId)
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest($this->apiUrl . '/video/status/' . $videoId, 'GET');
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'status' => $response['data']['status'] ?? 'unknown',
            'video_url' => $response['data']['video_url'] ?? '',
            'error' => $response['data']['error'] ?? null
        ];
    }
    
    public function getAvatars()
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest($this->apiUrl . '/avatars', 'GET');
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'avatars' => $response['data']['avatars'] ?? []
        ];
    }
    
    public function getVoices()
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest($this->apiUrl . '/voices', 'GET');
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'voices' => $response['data']['voices'] ?? []
        ];
    }
    
    public function extractText($response)
    {
        if (is_array($response)) {
            if (isset($response['video_url'])) {
                return 'Video generated: ' . $response['video_url'];
            }
            
            if (isset($response['video_id'])) {
                return 'Video created with ID: ' . $response['video_id'];
            }
            
            if (isset($response['message'])) {
                return $response['message'];
            }
            
            return json_encode($response);
        }
        
        if (is_string($response)) {
            return $response;
        }
        
        return '';
    }
}