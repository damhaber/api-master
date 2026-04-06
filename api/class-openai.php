<?php
/**
 * OpenAI API Class for Masal Panel
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_OpenAI implements APIMaster_APIInterface
{
    private $apiKey;
    private $model;
    private $config;
    private $apiUrl = 'https://api.openai.com/v1';
    private $timeout = 30;
    private $maxTokens = 4096;
    private $temperature = 0.7;
    private $topP = 1.0;
    private $frequencyPenalty = 0;
    private $presencePenalty = 0;
    
    private $supportedModels = [
        'gpt-4' => 'GPT-4',
        'gpt-4-turbo-preview' => 'GPT-4 Turbo',
        'gpt-4-32k' => 'GPT-4 32K',
        'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
        'gpt-3.5-turbo-16k' => 'GPT-3.5 Turbo 16K',
        'gpt-3.5-turbo-instruct' => 'GPT-3.5 Turbo Instruct'
    ];
    
    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->model = $this->config['model'] ?? 'gpt-3.5-turbo';
        
        if (isset($this->config['timeout'])) {
            $this->timeout = $this->config['timeout'];
        }
        
        if (isset($this->config['max_tokens'])) {
            $this->maxTokens = $this->config['max_tokens'];
        }
        
        if (isset($this->config['temperature'])) {
            $this->temperature = $this->config['temperature'];
        }
    }
    
    private function loadConfig()
    {
        $configFile = dirname(__DIR__) . '/config/openai.json';
        
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
        
        $logFile = $logDir . '/openai-error.log';
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
            'Authorization: Bearer ' . $this->apiKey,
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
        
        if ($httpCode !== 200) {
            $errorMsg = isset($decoded['error']['message']) 
                ? $decoded['error']['message'] 
                : "HTTP Error: {$httpCode}";
            $this->logError($errorMsg, ['url' => $url, 'response' => $response]);
            return ['error' => $errorMsg];
        }
        
        return $decoded;
    }
    
    // Interface Metodları
    
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
            $this->logError('API key is empty');
            return ['error' => 'API key is not configured'];
        }
        
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        $method = $params['method'] ?? 'POST';
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
        if (empty($this->apiKey)) {
            $this->logError('API key is empty');
            return false;
        }
        
        if (!is_callable($callback)) {
            $this->logError('Callback is not callable');
            return false;
        }
        
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        
        $ch = curl_init();
        
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: text/event-stream'
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['stream' => true]),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_WRITEFUNCTION => function($ch, $chunk) use ($callback) {
                $lines = explode("\n", $chunk);
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    
                    if (empty($line) || strpos($line, 'data: ') !== 0) {
                        continue;
                    }
                    
                    $dataLine = substr($line, 6);
                    
                    if ($dataLine === '[DONE]') {
                        call_user_func($callback, ['done' => true]);
                        return strlen($chunk);
                    }
                    
                    $json = json_decode($dataLine, true);
                    
                    if ($json && isset($json['choices'][0]['delta']['content'])) {
                        call_user_func($callback, [
                            'chunk' => $json['choices'][0]['delta']['content'],
                            'raw' => $json
                        ]);
                    }
                }
                
                return strlen($chunk);
            }
        ]);
        
        curl_exec($ch);
        
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            $this->logError("Stream CURL Error: {$curlError}");
            return false;
        }
        
        return true;
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
            'chat' => true,
            'completion' => true,
            'embedding' => true,
            'streaming' => true,
            'vision' => strpos($this->model, 'gpt-4') !== false,
            'function_calling' => strpos($this->model, 'gpt-4') !== false || strpos($this->model, 'gpt-3.5-turbo') !== false,
            'max_tokens' => $this->maxTokens,
            'temperature' => true,
            'top_p' => true,
            'frequency_penalty' => true,
            'presence_penalty' => true
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
        $response = $this->curlRequest($this->apiUrl . '/models', 'GET');
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
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $messages = [];
        
        if (!empty($context['system'])) {
            $messages[] = [
                'role' => 'system',
                'content' => $context['system']
            ];
        }
        
        if (!empty($context['history']) && is_array($context['history'])) {
            $messages = array_merge($messages, $context['history']);
        }
        
        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];
        
        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $context['temperature'] ?? $this->temperature,
            'max_tokens' => $context['max_tokens'] ?? $this->maxTokens,
            'top_p' => $context['top_p'] ?? $this->topP,
            'frequency_penalty' => $context['frequency_penalty'] ?? $this->frequencyPenalty,
            'presence_penalty' => $context['presence_penalty'] ?? $this->presencePenalty
        ];
        
        $response = $this->curlRequest($this->apiUrl . '/chat/completions', 'POST', $data);
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'message' => $response['choices'][0]['message']['content'] ?? '',
            'role' => $response['choices'][0]['message']['role'] ?? 'assistant',
            'finish_reason' => $response['choices'][0]['finish_reason'] ?? '',
            'model' => $response['model'] ?? $this->model,
            'usage' => $response['usage'] ?? [],
            'created' => $response['created'] ?? time()
        ];
    }
    
    public function extractText($response)
    {
        if (is_array($response)) {
            if (isset($response['message'])) {
                return $response['message'];
            }
            
            if (isset($response['choices'][0]['message']['content'])) {
                return $response['choices'][0]['message']['content'];
            }
            
            if (isset($response['data']['choices'][0]['message']['content'])) {
                return $response['data']['choices'][0]['message']['content'];
            }
            
            if (isset($response['content'])) {
                return $response['content'];
            }
            
            return json_encode($response);
        }
        
        if (is_string($response)) {
            return $response;
        }
        
        return '';
    }
}