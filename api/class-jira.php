<?php
/**
 * Jira API Class for Masal Panel
 * 
 * Project management, issue tracking, sprint management, agile boards
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Direct access prevention
}

class APIMaster_API_Jira implements APIMaster_APIInterface
{
    /**
     * Jira domain
     * @var string
     */
    private $domain;
    
    /**
     * Email
     * @var string
     */
    private $email;
    
    /**
     * API Token
     * @var string
     */
    private $apiToken;
    
    /**
     * Project key
     * @var string
     */
    private $projectKey;
    
    /**
     * Model (project key)
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
    private $apiUrl;
    
    /**
     * Agile API base URL
     * @var string
     */
    private $agileUrl;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->domain = $this->config['domain'] ?? '';
        $this->email = $this->config['email'] ?? '';
        $this->apiToken = $this->config['api_token'] ?? '';
        $this->projectKey = $this->config['project_key'] ?? '';
        $this->model = $this->projectKey;
        
        if ($this->domain) {
            $this->apiUrl = "https://{$this->domain}.atlassian.net/rest/api/3";
            $this->agileUrl = "https://{$this->domain}.atlassian.net/rest/agile/1.0";
        }
    }
    
    /**
     * Load configuration from JSON file
     * 
     * @return array Configuration data
     */
    private function loadConfig()
    {
        $configFile = __DIR__ . '/config/jira.json';
        
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
        $logFile = $logDir . '/jira-error.log';
        
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
     * Get authentication header
     * 
     * @return array Headers
     */
    private function getAuthHeader()
    {
        $auth = base64_encode($this->email . ':' . $this->apiToken);
        return [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
    }
    
    /**
     * Generate random string for boundary
     * 
     * @return string Random string
     */
    private function generateBoundary()
    {
        return '----' . md5(uniqid() . microtime());
    }
    
    /**
     * Make curl request to Jira API
     * 
     * @param string $url Full URL
     * @param string $method HTTP method
     * @param array|string $data Request data
     * @param array $headers Additional headers
     * @return array|false Response data
     */
    private function curlRequest($url, $method = 'GET', $data = null, $headers = [])
    {
        $ch = curl_init();
        
        $method = strtoupper($method);
        
        // Merge with default headers
        $defaultHeaders = $this->getAuthHeader();
        $allHeaders = array_merge($defaultHeaders, $headers);
        
        // Set common options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
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
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
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
        
        // For 204 No Content
        if ($httpCode === 204) {
            return true;
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMsg = isset($decoded['errorMessages'][0]) ? $decoded['errorMessages'][0] : 
                       (isset($decoded['message']) ? $decoded['message'] : 'HTTP ' . $httpCode);
            $this->logError('API Error', ['status' => $httpCode, 'error' => $errorMsg, 'url' => $url]);
            return false;
        }
        
        return $decoded;
    }
    
    /**
     * API request wrapper
     * 
     * @param string $endpoint Endpoint
     * @param string $method HTTP method
     * @param array $data Request data
     * @param array $params URL parameters
     * @return array|false Response
     */
    private function request($endpoint, $method = 'GET', $data = [], $params = [])
    {
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $body = null;
        if (!empty($data) && ($method === 'POST' || $method === 'PUT')) {
            $body = json_encode($data);
        }
        
        return $this->curlRequest($url, $method, $body);
    }
    
    /**
     * Request to Agile API
     * 
     * @param string $endpoint Endpoint
     * @param string $method HTTP method
     * @param array $data Request data
     * @param array $params URL parameters
     * @return array|false Response
     */
    private function agileRequest($endpoint, $method = 'GET', $data = [], $params = [])
    {
        $url = $this->agileUrl . '/' . ltrim($endpoint, '/');
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $body = null;
        if (!empty($data) && ($method === 'POST' || $method === 'PUT')) {
            $body = json_encode($data);
        }
        
        return $this->curlRequest($url, $method, $body);
    }
    
    /**
     * Set API key (email + token combination)
     * 
     * @param string $apiKey API key (format: email:token)
     * @return void
     */
    public function setApiKey($apiKey)
    {
        $parts = explode(':', $apiKey, 2);
        if (count($parts) === 2) {
            $this->email = $parts[0];
            $this->apiToken = $parts[1];
        } else {
            $this->apiToken = $apiKey;
        }
    }
    
    /**
     * Set model (project key)
     * 
     * @param string $model Project key
     * @return void
     */
    public function setModel($model)
    {
        $this->model = $model;
        $this->projectKey = $model;
    }
    
    /**
     * Get current model
     * 
     * @return string Current project key
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
        return $this->request($endpoint, 'GET', [], $params);
    }
    
    /**
     * Stream (not supported by Jira)
     * 
     * @param string $endpoint API endpoint
     * @param callable $callback Callback function
     * @return void
     */
    public function stream($endpoint, $callback)
    {
        $this->logError('Stream method not supported by Jira API');
    }
    
    /**
     * Get available models (projects)
     * 
     * @return array|false List of projects
     */
    public function getModels()
    {
        return $this->getProjects();
    }
    
    /**
     * Get API capabilities
     * 
     * @return array Capabilities list
     */
    public function getCapabilities()
    {
        return [
            'issues' => ['create', 'read', 'update', 'delete', 'transition', 'search'],
            'comments' => ['create', 'read'],
            'attachments' => ['create', 'read'],
            'sprints' => ['create', 'read', 'update'],
            'boards' => ['read'],
            'projects' => ['read'],
            'worklogs' => ['create', 'read'],
            'webhooks' => ['create', 'read'],
            'users' => ['read']
        ];
    }
    
    /**
     * Check API health
     * 
     * @return bool Connection successful
     */
    public function checkHealth()
    {
        return $this->testConnection();
    }
    
    /**
     * Chat (not supported by Jira, use addComment instead)
     * 
     * @param string $message Message content
     * @param array $context Context parameters
     * @return array|false Response
     */
    public function chat($message, $context = [])
    {
        $issueKey = $context['issue_key'] ?? null;
        if (!$issueKey) {
            $this->logError('Chat method requires issue_key in context');
            return false;
        }
        
        return $this->addComment($issueKey, $message);
    }
    
    /**
     * Extract text from response
     * 
     * @param array $response API response
     * @return string Extracted text
     */
    public function extractText($response)
    {
        if (!is_array($response)) {
            return '';
        }
        
        if (isset($response['summary'])) {
            return $response['summary'];
        }
        
        if (isset($response['description'])) {
            if (is_array($response['description'])) {
                // Extract text from Atlassian Document Format
                return $this->extractTextFromADF($response['description']);
            }
            return $response['description'];
        }
        
        if (isset($response['key']) && isset($response['fields']['summary'])) {
            return $response['fields']['summary'];
        }
        
        return json_encode($response);
    }
    
    /**
     * Extract text from Atlassian Document Format
     * 
     * @param array $adf ADF content
     * @return string Extracted text
     */
    private function extractTextFromADF($adf)
    {
        $text = '';
        
        if (isset($adf['content'])) {
            foreach ($adf['content'] as $block) {
                if (isset($block['content'])) {
                    foreach ($block['content'] as $item) {
                        if (isset($item['text'])) {
                            $text .= $item['text'] . ' ';
                        }
                    }
                }
            }
        }
        
        return trim($text);
    }
    
    // ========== JIRA SPECIFIC METHODS ==========
    
    /**
     * Create issue
     * 
     * @param array $issueData Issue data
     * @return array|false Issue data
     */
    public function createIssue($issueData)
    {
        $requiredFields = ['summary', 'issue_type'];
        
        foreach ($requiredFields as $field) {
            if (!isset($issueData[$field])) {
                $this->logError('Missing required issue field', ['field' => $field]);
                return false;
            }
        }
        
        $data = [
            'fields' => [
                'project' => [
                    'key' => $issueData['project_key'] ?? $this->projectKey
                ],
                'summary' => $issueData['summary'],
                'description' => [
                    'type' => 'doc',
                    'version' => 1,
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => $issueData['description'] ?? ''
                                ]
                            ]
                        ]
                    ]
                ],
                'issuetype' => [
                    'name' => $issueData['issue_type']
                ],
                'priority' => [
                    'name' => $issueData['priority'] ?? 'Medium'
                ],
                'labels' => $issueData['labels'] ?? [],
            ]
        ];
        
        if (isset($issueData['assignee'])) {
            $data['fields']['assignee'] = ['name' => $issueData['assignee']];
        }
        
        if (isset($issueData['due_date'])) {
            $data['fields']['duedate'] = $issueData['due_date'];
        }
        
        if (isset($issueData['components'])) {
            $data['fields']['components'] = array_map(function($comp) {
                return ['name' => $comp];
            }, $issueData['components']);
        }
        
        if (isset($issueData['custom_fields'])) {
            foreach ($issueData['custom_fields'] as $fieldId => $value) {
                $data['fields'][$fieldId] = $value;
            }
        }
        
        return $this->request('issue', 'POST', $data);
    }
    
    /**
     * Get issue
     * 
     * @param string $issueKey Issue key
     * @param array $fields Fields to fetch
     * @return array|false Issue data
     */
    public function getIssue($issueKey, $fields = [])
    {
        $params = [];
        if (!empty($fields)) {
            $params['fields'] = implode(',', $fields);
        }
        
        return $this->request("issue/{$issueKey}", 'GET', [], $params);
    }
    
    /**
     * Update issue
     * 
     * @param string $issueKey Issue key
     * @param array $updateData Update data
     * @return bool Success
     */
    public function updateIssue($issueKey, $updateData)
    {
        $data = ['fields' => []];
        
        $fieldMappings = [
            'summary' => 'summary',
            'priority' => 'priority',
            'assignee' => 'assignee',
            'due_date' => 'duedate',
            'labels' => 'labels'
        ];
        
        foreach ($fieldMappings as $key => $field) {
            if (isset($updateData[$key])) {
                if ($field === 'priority' || $field === 'assignee') {
                    $data['fields'][$field] = ['name' => $updateData[$key]];
                } elseif ($field === 'duedate') {
                    $data['fields'][$field] = $updateData[$key];
                } elseif ($field === 'labels') {
                    $data['fields'][$field] = $updateData[$key];
                } else {
                    $data['fields'][$field] = $updateData[$key];
                }
            }
        }
        
        if (isset($updateData['description'])) {
            $data['fields']['description'] = [
                'type' => 'doc',
                'version' => 1,
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $updateData['description']
                            ]
                        ]
                    ]
                ]
            ];
        }
        
        $response = $this->request("issue/{$issueKey}", 'PUT', $data);
        return ($response !== false);
    }
    
    /**
     * Delete issue
     * 
     * @param string $issueKey Issue key
     * @param bool $deleteSubtasks Delete subtasks too
     * @return bool Success
     */
    public function deleteIssue($issueKey, $deleteSubtasks = false)
    {
        $params = [];
        if ($deleteSubtasks) {
            $params['deleteSubtasks'] = 'true';
        }
        
        $url = $this->apiUrl . "/issue/{$issueKey}";
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $response = $this->curlRequest($url, 'DELETE');
        return ($response !== false);
    }
    
    /**
     * Search issues
     * 
     * @param string $jql JQL query
     * @param array $options Search options
     * @return array|false Search results
     */
    public function searchIssues($jql, $options = [])
    {
        $data = [
            'jql' => $jql,
            'startAt' => $options['start_at'] ?? 0,
            'maxResults' => $options['max_results'] ?? 50,
            'fields' => $options['fields'] ?? ['summary', 'status', 'assignee', 'priority'],
        ];
        
        if (isset($options['expand'])) {
            $data['expand'] = $options['expand'];
        }
        
        return $this->request('search', 'POST', $data);
    }
    
    /**
     * Add comment to issue
     * 
     * @param string $issueKey Issue key
     * @param string $comment Comment text
     * @param array $visibility Visibility settings
     * @return array|false Comment data
     */
    public function addComment($issueKey, $comment, $visibility = [])
    {
        $data = [
            'body' => [
                'type' => 'doc',
                'version' => 1,
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $comment
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        if (!empty($visibility)) {
            $data['visibility'] = $visibility;
        }
        
        return $this->request("issue/{$issueKey}/comment", 'POST', $data);
    }
    
    /**
     * Get issue comments
     * 
     * @param string $issueKey Issue key
     * @return array|false Comments
     */
    public function getComments($issueKey)
    {
        return $this->request("issue/{$issueKey}/comment", 'GET');
    }
    
    /**
     * Transition issue (change status)
     * 
     * @param string $issueKey Issue key
     * @param string $transitionId Transition ID
     * @param array $fields Fields to update
     * @return bool Success
     */
    public function transitionIssue($issueKey, $transitionId, $fields = [])
    {
        $data = [
            'transition' => ['id' => $transitionId]
        ];
        
        if (!empty($fields)) {
            $data['fields'] = $fields;
        }
        
        $response = $this->request("issue/{$issueKey}/transitions", 'POST', $data);
        return ($response !== false);
    }
    
    /**
     * Get available transitions for issue
     * 
     * @param string $issueKey Issue key
     * @return array|false Transitions
     */
    public function getTransitions($issueKey)
    {
        return $this->request("issue/{$issueKey}/transitions", 'GET');
    }
    
    /**
     * Add attachment to issue
     * 
     * @param string $issueKey Issue key
     * @param string $filePath File path
     * @param string|null $filename File name
     * @return array|false Attachment data
     */
    public function addAttachment($issueKey, $filePath, $filename = null)
    {
        if (!file_exists($filePath)) {
            $this->logError('File not found', ['path' => $filePath]);
            return false;
        }
        
        $filename = $filename ?? basename($filePath);
        $url = $this->apiUrl . "/issue/{$issueKey}/attachments";
        
        $boundary = $this->generateBoundary();
        $fileContent = file_get_contents($filePath);
        $mimeType = mime_content_type($filePath);
        
        $body = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: {$mimeType}\r\n\r\n";
        $body .= $fileContent . "\r\n";
        $body .= "--{$boundary}--\r\n";
        
        $headers = [
            'Content-Type: multipart/form-data; boundary=' . $boundary,
            'X-Atlassian-Token: no-check'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($this->getAuthHeader(), $headers));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            $this->logError('Attachment upload failed', ['status' => $httpCode, 'issue_key' => $issueKey]);
            return false;
        }
        
        $decoded = json_decode($response, true);
        
        if ($decoded && isset($decoded[0]['id'])) {
            return $decoded[0];
        }
        
        return false;
    }
    
    /**
     * Create sprint
     * 
     * @param string $boardId Board ID
     * @param string $name Sprint name
     * @param array $options Sprint options
     * @return array|false Sprint data
     */
    public function createSprint($boardId, $name, $options = [])
    {
        $data = [
            'name' => $name,
            'boardId' => (int)$boardId,
            'startDate' => $options['start_date'] ?? null,
            'endDate' => $options['end_date'] ?? null,
            'goal' => $options['goal'] ?? '',
        ];
        
        $data = array_filter($data);
        
        return $this->agileRequest('sprint', 'POST', $data);
    }
    
    /**
     * Add issues to sprint
     * 
     * @param string $sprintId Sprint ID
     * @param array $issueKeys Issue keys
     * @return bool Success
     */
    public function addIssuesToSprint($sprintId, $issueKeys)
    {
        $data = ['issues' => $issueKeys];
        $response = $this->agileRequest("sprint/{$sprintId}/issue", 'POST', $data);
        return ($response !== false);
    }
    
    /**
     * Get boards
     * 
     * @param array $params Filter parameters
     * @return array|false Boards
     */
    public function getBoards($params = [])
    {
        return $this->agileRequest('board', 'GET', [], $params);
    }
    
    /**
     * Get sprints for board
     * 
     * @param string $boardId Board ID
     * @param string|null $state Sprint state (active, closed, future)
     * @return array|false Sprints
     */
    public function getSprints($boardId, $state = null)
    {
        $params = [];
        if ($state) {
            $params['state'] = $state;
        }
        
        return $this->agileRequest("board/{$boardId}/sprint", 'GET', [], $params);
    }
    
    /**
     * Get projects
     * 
     * @param array $params Filter parameters
     * @return array|false Projects
     */
    public function getProjects($params = [])
    {
        return $this->request('project', 'GET', [], $params);
    }
    
    /**
     * Get issue types
     * 
     * @return array|false Issue types
     */
    public function getIssueTypes()
    {
        return $this->request('issuetype', 'GET');
    }
    
    /**
     * Get fields
     * 
     * @return array|false Fields
     */
    public function getFields()
    {
        return $this->request('field', 'GET');
    }
    
    /**
     * Get users
     * 
     * @param string $query Search query
     * @return array|false Users
     */
    public function getUsers($query = '')
    {
        $params = [];
        if ($query) {
            $params['query'] = $query;
        }
        
        return $this->request('user/search', 'GET', [], $params);
    }
    
    /**
     * Create webhook
     * 
     * @param string $url Webhook URL
     * @param array $events Events
     * @param array $filters Filters
     * @return array|false Webhook data
     */
    public function createWebhook($url, $events = ['jira:issue_created', 'jira:issue_updated'], $filters = [])
    {
        $data = [
            'name' => 'API Master Webhook',
            'url' => $url,
            'events' => $events,
            'filters' => $filters,
        ];
        
        return $this->request('webhook', 'POST', $data);
    }
    
    /**
     * Log work on issue
     * 
     * @param string $issueKey Issue key
     * @param string $timeSpent Time spent (e.g., "1h 30m")
     * @param string $comment Comment
     * @return bool Success
     */
    public function logWork($issueKey, $timeSpent, $comment = '')
    {
        $data = [
            'timeSpent' => $timeSpent,
            'comment' => [
                'type' => 'doc',
                'version' => 1,
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $comment
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $response = $this->request("issue/{$issueKey}/worklog", 'POST', $data);
        return ($response !== false && isset($response['id']));
    }
    
    /**
     * Test connection
     * 
     * @return bool Connection successful
     */
    public function testConnection()
    {
        $response = $this->request('myself', 'GET');
        return ($response !== false && isset($response['accountId']));
    }
}