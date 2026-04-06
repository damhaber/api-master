<?php
/**
 * Salesforce API Class for Masal Panel
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Salesforce implements APIMaster_APIInterface
{
    private $apiKey;
    private $model;
    private $config;
    private $apiUrl = '';
    private $instanceUrl = '';
    private $accessToken = '';
    private $timeout = 30;
    
    private $supportedModels = [
        'accounts' => 'Accounts',
        'contacts' => 'Contacts',
        'leads' => 'Leads',
        'opportunities' => 'Opportunities',
        'cases' => 'Cases'
    ];
    
    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->apiUrl = $this->config['api_url'] ?? 'https://login.salesforce.com';
        $this->model = $this->config['model'] ?? 'accounts';
        
        if (isset($this->config['timeout'])) {
            $this->timeout = $this->config['timeout'];
        }
        
        if (isset($this->config['access_token'])) {
            $this->accessToken = $this->config['access_token'];
        }
        
        if (isset($this->config['instance_url'])) {
            $this->instanceUrl = $this->config['instance_url'];
        }
    }
    
    private function loadConfig()
    {
        $configFile = dirname(__DIR__) . '/config/salesforce.json';
        
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
        
        $logFile = $logDir . '/salesforce-error.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        
        file_put_contents(
            $logFile,
            "[{$timestamp}] {$message}{$contextStr}" . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
    
    private function authenticate()
    {
        if (!empty($this->accessToken) && !empty($this->instanceUrl)) {
            return true;
        }
        
        if (empty($this->config['client_id']) || empty($this->config['client_secret'])) {
            $this->logError('Salesforce credentials missing');
            return false;
        }
        
        $ch = curl_init();
        
        $data = [
            'grant_type' => 'password',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'username' => $this->config['username'] ?? '',
            'password' => $this->config['password'] ?? ''
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl . '/services/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $this->logError('Salesforce authentication failed', ['http_code' => $httpCode, 'response' => $response]);
            return false;
        }
        
        $decoded = json_decode($response, true);
        
        if (isset($decoded['access_token'])) {
            $this->accessToken = $decoded['access_token'];
            $this->instanceUrl = $decoded['instance_url'];
            return true;
        }
        
        return false;
    }
    
    private function curlRequest($url, $method = 'GET', $data = null, $headers = [])
    {
        if (!$this->authenticate() && empty($this->accessToken)) {
            return ['error' => 'Salesforce authentication failed'];
        }
        
        $ch = curl_init();
        
        $defaultHeaders = [
            'Authorization: Bearer ' . $this->accessToken,
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
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
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
            $errorMsg = isset($decoded[0]['message']) 
                ? $decoded[0]['message']
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
        if (empty($this->instanceUrl)) {
            return ['error' => 'Salesforce not authenticated'];
        }
        
        $url = $this->instanceUrl . '/' . ltrim($endpoint, '/');
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
        $this->logError('Streaming not supported for Salesforce');
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
            'soql' => true,
            'sosl' => true,
            'crud' => true,
            'bulk_operations' => true,
            'reports' => true,
            'dml' => true
        ];
    }
    
    public function checkHealth()
    {
        if (empty($this->instanceUrl) && empty($this->accessToken)) {
            if (!$this->authenticate()) {
                return [
                    'status' => 'error',
                    'message' => 'Authentication failed'
                ];
            }
        }
        
        $startTime = microtime(true);
        $response = $this->curlRequest($this->instanceUrl . '/services/data/v58.0/limits', 'GET');
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
        return $this->query($message, $context);
    }
    
    public function query($soql, $params = [])
    {
        if (empty($this->instanceUrl)) {
            return ['error' => 'Salesforce not authenticated'];
        }
        
        $response = $this->curlRequest(
            $this->instanceUrl . '/services/data/v58.0/query',
            'GET',
            ['q' => $soql]
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'records' => $response['records'] ?? [],
            'total_size' => $response['totalSize'] ?? 0,
            'done' => $response['done'] ?? true,
            'query' => $soql
        ];
    }
    
    public function search($sosl)
    {
        if (empty($this->instanceUrl)) {
            return ['error' => 'Salesforce not authenticated'];
        }
        
        $response = $this->curlRequest(
            $this->instanceUrl . '/services/data/v58.0/search',
            'GET',
            ['q' => $sosl]
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'records' => $response['searchRecords'] ?? $response,
            'count' => count($response['searchRecords'] ?? $response)
        ];
    }
    
    public function createRecord($objectType, $fields)
    {
        if (empty($this->instanceUrl)) {
            return ['error' => 'Salesforce not authenticated'];
        }
        
        $response = $this->curlRequest(
            $this->instanceUrl . '/services/data/v58.0/sobjects/' . urlencode($objectType),
            'POST',
            $fields
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'id' => $response['id'] ?? '',
            'object_type' => $objectType
        ];
    }
    
    public function getRecord($objectType, $recordId, $fields = [])
    {
        if (empty($this->instanceUrl)) {
            return ['error' => 'Salesforce not authenticated'];
        }
        
        $url = $this->instanceUrl . '/services/data/v58.0/sobjects/' . urlencode($objectType) . '/' . urlencode($recordId);
        
        if (!empty($fields)) {
            $url .= '?fields=' . implode(',', $fields);
        }
        
        $response = $this->curlRequest($url, 'GET');
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'record' => $response,
            'id' => $recordId,
            'object_type' => $objectType
        ];
    }
    
    public function updateRecord($objectType, $recordId, $fields)
    {
        if (empty($this->instanceUrl)) {
            return ['error' => 'Salesforce not authenticated'];
        }
        
        $response = $this->curlRequest(
            $this->instanceUrl . '/services/data/v58.0/sobjects/' . urlencode($objectType) . '/' . urlencode($recordId),
            'PATCH',
            $fields
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'id' => $recordId,
            'object_type' => $objectType,
            'message' => 'Record updated successfully'
        ];
    }
    
    public function deleteRecord($objectType, $recordId)
    {
        if (empty($this->instanceUrl)) {
            return ['error' => 'Salesforce not authenticated'];
        }
        
        $response = $this->curlRequest(
            $this->instanceUrl . '/services/data/v58.0/sobjects/' . urlencode($objectType) . '/' . urlencode($recordId),
            'DELETE'
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'id' => $recordId,
            'object_type' => $objectType,
            'message' => 'Record deleted successfully'
        ];
    }
    
    public function describeObject($objectType)
    {
        if (empty($this->instanceUrl)) {
            return ['error' => 'Salesforce not authenticated'];
        }
        
        $response = $this->curlRequest(
            $this->instanceUrl . '/services/data/v58.0/sobjects/' . urlencode($objectType) . '/describe',
            'GET'
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'fields' => $response['fields'] ?? [],
            'label' => $response['label'] ?? '',
            'name' => $response['name'] ?? ''
        ];
    }
    
    public function extractText($response)
    {
        if (is_array($response)) {
            if (isset($response['records'])) {
                return 'Found ' . $response['total_size'] . ' records from query';
            }
            
            if (isset($response['id']) && isset($response['object_type'])) {
                return $response['object_type'] . ' record created/updated with ID: ' . $response['id'];
            }
            
            if (isset($response['record'])) {
                return 'Record retrieved from ' . ($response['object_type'] ?? 'unknown');
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