<?php
/**
 * Bitbucket API Class for Masal Panel
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Bitbucket implements APIMaster_APIInterface
{
    private $apiKey;
    private $apiSecret;
    private $model;
    private $config;
    private $apiUrl = 'https://api.bitbucket.org/2.0';
    private $timeout = 30;
    
    private $supportedModels = [
        'repositories' => 'Repositories',
        'issues' => 'Issues (Jira integration)',
        'pull_requests' => 'Pull Requests',
        'pipelines' => 'Pipelines',
        'workspaces' => 'Workspaces'
    ];
    
    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->apiSecret = $this->config['api_secret'] ?? '';
        $this->model = $this->config['model'] ?? 'repositories';
        
        if (isset($this->config['api_url'])) {
            $this->apiUrl = $this->config['api_url'];
        }
        
        if (isset($this->config['timeout'])) {
            $this->timeout = $this->config['timeout'];
        }
    }
    
    private function loadConfig()
    {
        $configFile = dirname(__DIR__) . '/config/bitbucket.json';
        
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
        
        $logFile = $logDir . '/bitbucket-error.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        
        file_put_contents(
            $logFile,
            "[{$timestamp}] {$message}{$contextStr}" . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
    
    private function getAuthHeader()
    {
        if (!empty($this->apiKey) && !empty($this->apiSecret)) {
            return 'Basic ' . base64_encode($this->apiKey . ':' . $this->apiSecret);
        } elseif (!empty($this->apiKey)) {
            return 'Bearer ' . $this->apiKey;
        }
        
        return '';
    }
    
    private function curlRequest($url, $method = 'GET', $data = null, $headers = [])
    {
        $ch = curl_init();
        
        $authHeader = $this->getAuthHeader();
        
        $defaultHeaders = [
            'Content-Type: application/json'
        ];
        
        if ($authHeader) {
            $defaultHeaders[] = $authHeader;
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
    
    public function setApiSecret($apiSecret)
    {
        $this->apiSecret = $apiSecret;
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
        $this->logError('Streaming not supported for Bitbucket');
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
            'repositories' => true,
            'pull_requests' => true,
            'pipelines' => true,
            'issues' => true,
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
        return $this->searchRepositories($message, $context);
    }
    
    public function getWorkspaces()
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest($this->apiUrl . '/workspaces', 'GET');
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'workspaces' => $response['values'] ?? [],
            'count' => $response['pagelen'] ?? 0
        ];
    }
    
    public function getRepositories($workspace, $params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest(
            $this->apiUrl . '/repositories/' . urlencode($workspace),
            'GET',
            $params
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'repositories' => $response['values'] ?? [],
            'count' => count($response['values'] ?? [])
        ];
    }
    
    public function getRepository($workspace, $repoSlug)
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest(
            $this->apiUrl . '/repositories/' . urlencode($workspace) . '/' . urlencode($repoSlug),
            'GET'
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'repository' => $response
        ];
    }
    
    public function createPullRequest($workspace, $repoSlug, $title, $sourceBranch, $destinationBranch, $description = '')
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $data = [
            'title' => $title,
            'description' => $description,
            'source' => [
                'branch' => [
                    'name' => $sourceBranch
                ],
                'repository' => [
                    'full_name' => $workspace . '/' . $repoSlug
                ]
            ],
            'destination' => [
                'branch' => [
                    'name' => $destinationBranch
                ]
            ]
        ];
        
        $response = $this->curlRequest(
            $this->apiUrl . '/repositories/' . urlencode($workspace) . '/' . urlencode($repoSlug) . '/pullrequests',
            'POST',
            $data
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'pull_request' => $response,
            'pr_id' => $response['id'] ?? ''
        ];
    }
    
    public function getPullRequests($workspace, $repoSlug, $params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest(
            $this->apiUrl . '/repositories/' . urlencode($workspace) . '/' . urlencode($repoSlug) . '/pullrequests',
            'GET',
            $params
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'pull_requests' => $response['values'] ?? [],
            'count' => count($response['values'] ?? [])
        ];
    }
    
    public function getCommits($workspace, $repoSlug, $params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest(
            $this->apiUrl . '/repositories/' . urlencode($workspace) . '/' . urlencode($repoSlug) . '/commits',
            'GET',
            $params
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'commits' => $response['values'] ?? [],
            'count' => count($response['values'] ?? [])
        ];
    }
    
    public function getBranches($workspace, $repoSlug, $params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest(
            $this->apiUrl . '/repositories/' . urlencode($workspace) . '/' . urlencode($repoSlug) . '/refs/branches',
            'GET',
            $params
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'branches' => $response['values'] ?? [],
            'count' => count($response['values'] ?? [])
        ];
    }
    
    public function searchRepositories($search, $params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $params['q'] = $search;
        
        $response = $this->curlRequest($this->apiUrl . '/repositories', 'GET', $params);
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'results' => $response['values'] ?? [],
            'count' => count($response['values'] ?? []),
            'query' => $search
        ];
    }
    
    public function extractText($response)
    {
        if (is_array($response)) {
            if (isset($response['repositories'])) {
                return 'Found ' . $response['count'] . ' repositories';
            }
            
            if (isset($response['pull_requests'])) {
                return 'Found ' . $response['count'] . ' pull requests';
            }
            
            if (isset($response['pull_request'])) {
                return 'Pull request created: ' . ($response['pr_id'] ?? '');
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