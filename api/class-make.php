<?php
/**
 * Make (Integromat) API Class for Masal Panel
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Make implements APIMaster_APIInterface
{
    private $apiKey;
    private $model;
    private $config;
    private $apiUrl = 'https://api.make.com';
    private $apiVersion = 'v1';
    private $timeout = 30;
    
    private $supportedModels = [
        'webhook' => 'Webhook Receiver',
        'scenario' => 'Scenario Control',
        'data_store' => 'Data Store',
        'connection' => 'Connection Management'
    ];
    
    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->model = $this->config['model'] ?? 'webhook';
        
        if (isset($this->config['api_url'])) {
            $this->apiUrl = $this->config['api_url'];
        }
        
        if (isset($this->config['timeout'])) {
            $this->timeout = $this->config['timeout'];
        }
    }
    
    private function loadConfig()
    {
        $configFile = dirname(__DIR__) . '/config/make.json';
        
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
        
        $logFile = $logDir . '/make-error.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        
        file_put_contents(
            $logFile,
            "[{$timestamp}] {$message}{$contextStr}" . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
    
    private function getApiUrl($endpoint)
    {
        return rtrim($this->apiUrl, '/') . '/' . $this->apiVersion . '/' . ltrim($endpoint, '/');
    }
    
    private function curlRequest($url, $method = 'GET', $data = null, $headers = [])
    {
        $ch = curl_init();
        
        $defaultHeaders = [
            'Content-Type: application/json'
        ];
        
        if (!empty($this->apiKey)) {
            $defaultHeaders[] = 'Authorization: Token ' . $this->apiKey;
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
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
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
        
        if ($httpCode < 200 || $httpCode >= 300) {
            $errorMsg = isset($decoded['error']['message']) 
                ? $decoded['error']['message']
                : (isset($decoded['message']) 
                    ? $decoded['message']
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
        
        $url = $this->getApiUrl($endpoint);
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
        $this->logError('Streaming not supported for Make');
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
            'webhooks' => true,
            'scenario_execution' => true,
            'data_stores' => true,
            'connections' => true,
            'scheduling' => true
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
        $response = $this->curlRequest($this->getApiUrl('/scenarios'), 'GET');
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
        return $this->sendToWebhook($message, $context);
    }
    
    public function sendToWebhook($webhookUrl, $data = [])
    {
        if (empty($webhookUrl)) {
            return ['error' => 'Webhook URL is required'];
        }
        
        $payload = is_string($data) ? ['message' => $data] : $data;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $webhookUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            $this->logError("Webhook CURL Error: {$curlError}", ['url' => $webhookUrl]);
            return ['error' => "CURL Error: {$curlError}"];
        }
        
        $decoded = json_decode($response, true);
        
        return [
            'success' => $httpCode === 200 || $httpCode === 201,
            'message' => 'Webhook sent to Make',
            'response' => $decoded,
            'webhook_url' => $webhookUrl,
            'status_code' => $httpCode
        ];
    }
    
    public function triggerWebhook($hookId, $data = [])
    {
        $webhookUrl = "https://hook.make.com/{$hookId}";
        
        return $this->sendToWebhook($webhookUrl, $data);
    }
    
    public function getScenarios($params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest($this->getApiUrl('/scenarios'), 'GET', $params);
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'scenarios' => $response['scenarios'] ?? $response,
            'count' => count($response['scenarios'] ?? $response)
        ];
    }
    
    public function getScenario($scenarioId)
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest($this->getApiUrl('/scenarios/' . urlencode($scenarioId)), 'GET');
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'scenario' => $response
        ];
    }
    
    public function runScenario($scenarioId, $data = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest(
            $this->getApiUrl('/scenarios/' . urlencode($scenarioId) . '/run'),
            'POST',
            $data
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'execution_id' => $response['id'] ?? $response['execution_id'] ?? '',
            'status' => $response['status'] ?? 'started',
            'scenario_id' => $scenarioId
        ];
    }
    
    public function getScenarioRuns($scenarioId, $params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest(
            $this->getApiUrl('/scenarios/' . urlencode($scenarioId) . '/runs'),
            'GET',
            $params
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'runs' => $response['runs'] ?? $response,
            'count' => count($response['runs'] ?? $response)
        ];
    }
    
    public function getDataStores($params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest($this->getApiUrl('/data-stores'), 'GET', $params);
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'data_stores' => $response['data_stores'] ?? $response,
            'count' => count($response['data_stores'] ?? $response)
        ];
    }
    
    public function getDataStoreRecords($dataStoreId, $params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest(
            $this->getApiUrl('/data-stores/' . urlencode($dataStoreId) . '/records'),
            'GET',
            $params
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'records' => $response['records'] ?? $response,
            'count' => count($response['records'] ?? $response)
        ];
    }
    
    public function addDataStoreRecord($dataStoreId, $data)
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest(
            $this->getApiUrl('/data-stores/' . urlencode($dataStoreId) . '/records'),
            'POST',
            $data
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'record_id' => $response['id'] ?? $response['record_id'] ?? '',
            'data_store_id' => $dataStoreId
        ];
    }
    
    public function extractText($response)
    {
        if (is_array($response)) {
            if (isset($response['webhook_url'])) {
                return 'Webhook sent to: ' . $response['webhook_url'];
            }
            
            if (isset($response['scenarios'])) {
                return 'Found ' . $response['count'] . ' scenarios';
            }
            
            if (isset($response['execution_id'])) {
                return 'Scenario execution started: ' . $response['execution_id'];
            }
            
            if (isset($response['data_stores'])) {
                return 'Found ' . $response['count'] . ' data stores';
            }
            
            if (isset($response['record_id'])) {
                return 'Record added to data store: ' . $response['record_id'];
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