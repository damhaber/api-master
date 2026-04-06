<?php
/**
 * GitLab API Class for Masal Panel
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_GitLab implements APIMaster_APIInterface
{
    private $apiKey;
    private $model;
    private $config;
    private $apiUrl = 'https://gitlab.com/api/v4';
    private $timeout = 30;
    
    private $supportedModels = [
        'projects' => 'Projects Management',
        'repositories' => 'Repositories',
        'issues' => 'Issues',
        'merge_requests' => 'Merge Requests',
        'pipelines' => 'CI/CD Pipelines'
    ];
    
    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->model = $this->config['model'] ?? 'projects';
        
        if (isset($this->config['api_url'])) {
            $this->apiUrl = $this->config['api_url'];
        }
        
        if (isset($this->config['timeout'])) {
            $this->timeout = $this->config['timeout'];
        }
    }
    
    private function loadConfig()
    {
        $configFile = dirname(__DIR__) . '/config/gitlab.json';
        
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
        
        $logFile = $logDir . '/gitlab-error.log';
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
            'PRIVATE-TOKEN: ' . $this->apiKey,
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
            $errorMsg = isset($decoded['message']) 
                ? (is_array($decoded['message']) ? json_encode($decoded['message']) : $decoded['message'])
                : "HTTP Error: {$httpCode}";
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
        $this->logError('Streaming not supported for GitLab');
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
            'projects' => true,
            'repositories' => true,
            'issues' => true,
            'merge_requests' => true,
            'pipelines' => true,
            'webhooks' => true,
            'deployments' => true
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
        $response = $this->curlRequest($this->apiUrl . '/user', 'GET');
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
            'response_time_ms' => $responseTime,
            'user' => $response['username'] ?? 'unknown'
        ];
    }
    
    public function chat($message, $context = [])
    {
        return $this->searchProjects($message, $context);
    }
    
    public function getProjects($params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest($this->apiUrl . '/projects', 'GET', $params);
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'projects' => $response,
            'count' => count($response)
        ];
    }
    
    public function getProject($projectId)
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest($this->apiUrl . '/projects/' . urlencode($projectId), 'GET');
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'project' => $response
        ];
    }
    
    public function createIssue($projectId, $title, $description = '', $options = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $data = [
            'title' => $title,
            'description' => $description
        ];
        
        if (isset($options['labels'])) {
            $data['labels'] = $options['labels'];
        }
        
        if (isset($options['assignee_id'])) {
            $data['assignee_id'] = $options['assignee_id'];
        }
        
        if (isset($options['milestone_id'])) {
            $data['milestone_id'] = $options['milestone_id'];
        }
        
        $response = $this->curlRequest(
            $this->apiUrl . '/projects/' . urlencode($projectId) . '/issues',
            'POST',
            $data
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'issue' => $response,
            'issue_id' => $response['iid'] ?? $response['id'] ?? ''
        ];
    }
    
    public function getIssues($projectId, $params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest(
            $this->apiUrl . '/projects/' . urlencode($projectId) . '/issues',
            'GET',
            $params
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'issues' => $response,
            'count' => count($response)
        ];
    }
    
    public function createMergeRequest($projectId, $title, $sourceBranch, $targetBranch, $description = '')
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $data = [
            'title' => $title,
            'source_branch' => $sourceBranch,
            'target_branch' => $targetBranch,
            'description' => $description
        ];
        
        $response = $this->curlRequest(
            $this->apiUrl . '/projects/' . urlencode($projectId) . '/merge_requests',
            'POST',
            $data
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'merge_request' => $response,
            'mr_id' => $response['iid'] ?? $response['id'] ?? ''
        ];
    }
    
    public function getPipelines($projectId, $params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest(
            $this->apiUrl . '/projects/' . urlencode($projectId) . '/pipelines',
            'GET',
            $params
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'pipelines' => $response,
            'count' => count($response)
        ];
    }
    
    public function getCommits($projectId, $params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest(
            $this->apiUrl . '/projects/' . urlencode($projectId) . '/repository/commits',
            'GET',
            $params
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'commits' => $response,
            'count' => count($response)
        ];
    }
    
    public function searchProjects($search, $params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $params['search'] = $search;
        
        $response = $this->curlRequest($this->apiUrl . '/projects', 'GET', $params);
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'results' => $response,
            'count' => count($response),
            'query' => $search
        ];
    }
    
    public function extractText($response)
    {
        if (is_array($response)) {
            if (isset($response['projects'])) {
                return 'Found ' . $response['count'] . ' projects';
            }
            
            if (isset($response['issues'])) {
                return 'Found ' . $response['count'] . ' issues';
            }
            
            if (isset($response['merge_request'])) {
                return 'Merge request created: ' . ($response['mr_id'] ?? '');
            }
            
            if (isset($response['commits'])) {
                return 'Found ' . $response['count'] . ' commits';
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