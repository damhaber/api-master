<?php
/**
 * StabilityAI API Class for Masal Panel
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_StabilityAI implements APIMaster_APIInterface
{
    private $apiKey;
    private $model;
    private $config;
    private $apiUrl = 'https://api.stability.ai/v1';
    private $timeout = 120;
    private $imageSize = '1024x1024';
    
    private $supportedModels = [
        'stable-diffusion-xl-1024-v1-0' => 'SD XL 1.0',
        'stable-diffusion-xl-1024-v0-9' => 'SD XL 0.9',
        'stable-diffusion-512-v2-1' => 'SD 2.1 (512)',
        'stable-diffusion-768-v2-1' => 'SD 2.1 (768)',
        'stable-diffusion-v1-6' => 'SD 1.6',
        'core' => 'Core',
        'sd3' => 'Stable Diffusion 3'
    ];
    
    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->model = $this->config['model'] ?? 'stable-diffusion-xl-1024-v1-0';
        
        if (isset($this->config['timeout'])) {
            $this->timeout = $this->config['timeout'];
        }
        
        if (isset($this->config['image_size'])) {
            $this->imageSize = $this->config['image_size'];
        }
    }
    
    private function loadConfig()
    {
        $configFile = dirname(__DIR__) . '/config/stabilityai.json';
        
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
        
        $logFile = $logDir . '/stabilityai-error.log';
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
            'Authorization: Bearer ' . $this->apiKey
        ];
        
        $isMultipart = ($method === 'POST' && isset($headers['Content-Type']) && $headers['Content-Type'] === 'multipart/form-data');
        
        if (!$isMultipart) {
            $defaultHeaders[] = 'Content-Type: application/json';
        }
        
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
                if ($isMultipart) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
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
        
        if ($httpCode !== 200) {
            $decoded = json_decode($response, true);
            $errorMsg = isset($decoded['message']) 
                ? $decoded['message'] 
                : "HTTP Error: {$httpCode}";
            $this->logError($errorMsg, ['url' => $url, 'response' => $response]);
            return ['error' => $errorMsg];
        }
        
        return $response;
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
        $isMultipart = isset($params['multipart']) && $params['multipart'] === true;
        unset($params['method'], $params['multipart']);
        
        $data = !empty($params) ? $params : null;
        
        $headers = [];
        if ($isMultipart) {
            $headers['Content-Type'] = 'multipart/form-data';
        }
        
        $response = $this->curlRequest($url, $method, $data, $headers);
        
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
        $this->logError('Streaming not supported for StabilityAI');
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
            'text_to_image' => true,
            'image_to_image' => true,
            'image_upscale' => true,
            'inpainting' => true,
            'outpainting' => true,
            'streaming' => false,
            'max_size' => $this->imageSize,
            'formats' => ['png', 'jpeg', 'webp']
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
        $response = $this->curlRequest($this->apiUrl . '/user/account', 'GET');
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
        return $this->generateImage($message, $context);
    }
    
    public function generateImage($prompt, $options = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $data = [
            'text_prompts' => [
                [
                    'text' => $prompt,
                    'weight' => $options['weight'] ?? 1.0
                ]
            ],
            'cfg_scale' => $options['cfg_scale'] ?? 7,
            'height' => $options['height'] ?? 1024,
            'width' => $options['width'] ?? 1024,
            'samples' => $options['samples'] ?? 1,
            'steps' => $options['steps'] ?? 30
        ];
        
        if (isset($options['negative_prompt'])) {
            $data['text_prompts'][] = [
                'text' => $options['negative_prompt'],
                'weight' => -1.0
            ];
        }
        
        if (isset($options['seed'])) {
            $data['seed'] = $options['seed'];
        }
        
        if (isset($options['style_preset'])) {
            $data['style_preset'] = $options['style_preset'];
        }
        
        $response = $this->curlRequest(
            $this->apiUrl . '/generation/' . $this->model . '/text-to-image',
            'POST',
            $data
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        $decoded = json_decode($response, true);
        
        $images = [];
        if (isset($decoded['artifacts'])) {
            foreach ($decoded['artifacts'] as $artifact) {
                $images[] = [
                    'base64' => $artifact['base64'],
                    'finish_reason' => $artifact['finishReason'] ?? 'SUCCESS',
                    'seed' => $artifact['seed'] ?? 0
                ];
            }
        }
        
        return [
            'success' => true,
            'images' => $images,
            'prompt' => $prompt,
            'model' => $this->model
        ];
    }
    
    public function extractText($response)
    {
        if (is_array($response)) {
            if (isset($response['images'])) {
                return 'Generated ' . count($response['images']) . ' images';
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