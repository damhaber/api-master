<?php
/**
 * Pabbly API Class for Masal Panel
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Pabbly implements APIMaster_APIInterface
{
    private $apiKey;
    private $model;
    private $config;
    private $apiUrl = 'https://connect.pabbly.com';
    private $timeout = 30;
    
    private $supportedModels = [
        'webhook' => 'Webhook Trigger',
        'workflow' => 'Workflow Management',
        'email' => 'Email Marketing',
        'subscription' => 'Subscription'
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
        $configFile = dirname(__DIR__) . '/config/pabbly.json';
        
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
        
        $logFile = $logDir . '/pabbly-error.log';
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
            'Content-Type: application/json'
        ];
        
        if (!empty($this->apiKey)) {
            $defaultHeaders[] = 'Authorization: Bearer ' . $this->apiKey;
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
            $errorMsg = isset($decoded['error']) 
                ? (is_array($decoded['error']) ? json_encode($decoded['error']) : $decoded['error'])
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
        
        $url = rtrim($this->apiUrl, '/') . '/' . ltrim($endpoint, '/');
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
        $this->logError('Streaming not supported for Pabbly');
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
            'workflows' => true,
            'email_marketing' => true,
            'subscription_management' => true,
            'form_builder' => true
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
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl . '/api/workflows',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->apiKey],
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $responseTime = round((microtime(true) - $startTime) * 1000);
        
        if ($httpCode === 401) {
            return [
                'status' => 'error',
                'message' => 'Invalid API key',
                'response_time_ms' => $responseTime
            ];
        }
        
        if ($httpCode === 200 || $httpCode === 403) {
            return [
                'status' => 'healthy',
                'message' => 'API is reachable',
                'response_time_ms' => $responseTime
            ];
        }
        
        return [
            'status' => 'warning',
            'message' => 'Pabbly API webhook mode available',
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
            'message' => 'Webhook sent to Pabbly Connect',
            'response' => $decoded,
            'webhook_url' => $webhookUrl,
            'status_code' => $httpCode
        ];
    }
    
    public function triggerWorkflow($workflowId, $data = [])
    {
        $webhookUrl = "https://connect.pabbly.com/workflow/{$workflowId}";
        
        return $this->sendToWebhook($webhookUrl, $data);
    }
    
    public function getWorkflows($params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest($this->apiUrl . '/api/workflows', 'GET', $params);
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'workflows' => $response['workflows'] ?? $response,
            'count' => count($response['workflows'] ?? $response)
        ];
    }
    
    public function getWorkflow($workflowId)
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest($this->apiUrl . '/api/workflows/' . urlencode($workflowId), 'GET');
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'workflow' => $response
        ];
    }
    
    public function createWorkflow($name, $steps = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $data = [
            'name' => $name,
            'steps' => $steps
        ];
        
        $response = $this->curlRequest($this->apiUrl . '/api/workflows', 'POST', $data);
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'workflow_id' => $response['id'] ?? $response['workflow_id'] ?? '',
            'name' => $name
        ];
    }
    
    public function getEmailLists($params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest($this->apiUrl . '/api/email/lists', 'GET', $params);
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'lists' => $response['lists'] ?? $response,
            'count' => count($response['lists'] ?? $response)
        ];
    }
    
    public function addSubscriber($listId, $email, $data = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $payload = array_merge([
            'email' => $email,
            'list_id' => $listId
        ], $data);
        
        $response = $this->curlRequest($this->apiUrl . '/api/email/subscribe', 'POST', $payload);
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'subscriber_id' => $response['id'] ?? $response['subscriber_id'] ?? '',
            'email' => $email,
            'list_id' => $listId
        ];
    }
    
    public function sendEmail($to, $subject, $body, $options = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $data = array_merge([
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'is_html' => $options['is_html'] ?? true
        ], $options);
        
        $response = $this->curlRequest($this->apiUrl . '/api/email/send', 'POST', $data);
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'message_id' => $response['id'] ?? $response['message_id'] ?? '',
            'to' => $to,
            'subject' => $subject
        ];
    }
    
    public function extractText($response)
    {
        if (is_array($response)) {
            if (isset($response['webhook_url'])) {
                return 'Webhook sent to: ' . $response['webhook_url'];
            }
            
            if (isset($response['workflows'])) {
                return 'Found ' . $response['count'] . ' workflows';
            }
            
            if (isset($response['workflow_id'])) {
                return 'Workflow created: ' . $response['workflow_id'];
            }
            
            if (isset($response['lists'])) {
                return 'Found ' . $response['count'] . ' email lists';
            }
            
            if (isset($response['subscriber_id'])) {
                return 'Subscriber added: ' . $response['email'];
            }
            
            if (isset($response['message_id'])) {
                return 'Email sent to: ' . $response['to'];
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