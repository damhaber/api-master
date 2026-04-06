<?php
/**
 * Confluence API Class for Masal Panel
 * 
 * Wiki pages, spaces, articles, labels, comments
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Direct access prevention
}

class APIMaster_API_Confluence implements APIMaster_APIInterface
{
    /**
     * Confluence domain
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
     * Space key
     * @var string
     */
    private $spaceKey;
    
    /**
     * Model (space key)
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
     * Constructor
     */
    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->domain = $this->config['domain'] ?? '';
        $this->email = $this->config['email'] ?? '';
        $this->apiToken = $this->config['api_token'] ?? '';
        $this->spaceKey = $this->config['space_key'] ?? '';
        $this->model = $this->spaceKey;
        
        if ($this->domain) {
            $this->apiUrl = "https://{$this->domain}.atlassian.net/wiki/rest/api";
        }
    }
    
    /**
     * Load configuration from JSON file
     * 
     * @return array Configuration data
     */
    private function loadConfig()
    {
        $configFile = __DIR__ . '/config/confluence.json';
        
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
        $logFile = $logDir . '/confluence-error.log';
        
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
     * Make curl request to Confluence API
     * 
     * @param string $url Full URL
     * @param string $method HTTP method
     * @param array|string $data Request data
     * @param array $headers Additional headers
     * @return array|false|true Response data (true for 204 No Content)
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
            $errorMsg = isset($decoded['message']) ? $decoded['message'] : 
                       (isset($decoded['error']) ? $decoded['error'] : 'HTTP ' . $httpCode);
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
     * Set model (space key)
     * 
     * @param string $model Space key
     * @return void
     */
    public function setModel($model)
    {
        $this->model = $model;
        $this->spaceKey = $model;
    }
    
    /**
     * Get current model
     * 
     * @return string Current space key
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
     * Stream (not supported by Confluence)
     * 
     * @param string $endpoint API endpoint
     * @param callable $callback Callback function
     * @return void
     */
    public function stream($endpoint, $callback)
    {
        $this->logError('Stream method not supported by Confluence API');
    }
    
    /**
     * Get available models (spaces)
     * 
     * @return array|false List of spaces
     */
    public function getModels()
    {
        return $this->getSpaces();
    }
    
    /**
     * Get API capabilities
     * 
     * @return array Capabilities list
     */
    public function getCapabilities()
    {
        return [
            'pages' => ['create', 'read', 'update', 'delete', 'versioning', 'restore'],
            'comments' => ['create', 'read'],
            'attachments' => ['create', 'read'],
            'spaces' => ['create', 'read'],
            'labels' => ['create', 'read', 'delete'],
            'templates' => ['create', 'read'],
            'search' => ['read'],
            'permissions' => ['read'],
            'watchers' => ['create']
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
     * Chat (not supported by Confluence, use addComment instead)
     * 
     * @param string $message Message content
     * @param array $context Context parameters
     * @return array|false Response
     */
    public function chat($message, $context = [])
    {
        $pageId = $context['page_id'] ?? null;
        if (!$pageId) {
            $this->logError('Chat method requires page_id in context');
            return false;
        }
        
        return $this->addComment($pageId, $message);
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
        
        if (isset($response['title'])) {
            return $response['title'];
        }
        
        if (isset($response['body']['storage']['value'])) {
            // Strip HTML tags from storage format
            return strip_tags($response['body']['storage']['value']);
        }
        
        if (isset($response['results']) && is_array($response['results'])) {
            $texts = [];
            foreach ($response['results'] as $result) {
                if (isset($result['title'])) {
                    $texts[] = $result['title'];
                }
            }
            return implode(', ', $texts);
        }
        
        return json_encode($response);
    }
    
    // ========== CONFLUENCE SPECIFIC METHODS ==========
    
    /**
     * Create page
     * 
     * @param string $title Page title
     * @param string $content Page content (HTML or Storage format)
     * @param array $options Page options
     * @return array|false Page data
     */
    public function createPage($title, $content, $options = [])
    {
        $data = [
            'type' => 'page',
            'title' => $title,
            'space' => [
                'key' => $options['space_key'] ?? $this->spaceKey
            ],
            'body' => [
                'storage' => [
                    'value' => $content,
                    'representation' => 'storage'
                ]
            ],
            'version' => [
                'number' => 1
            ]
        ];
        
        if (isset($options['parent_id'])) {
            $data['ancestors'] = [['id' => $options['parent_id']]];
        }
        
        if (isset($options['labels'])) {
            $data['metadata'] = [
                'labels' => [
                    'results' => array_map(function($label) {
                        return ['name' => $label];
                    }, $options['labels'])
                ]
            ];
        }
        
        return $this->request('content', 'POST', $data);
    }
    
    /**
     * Get page
     * 
     * @param string $pageId Page ID
     * @param array $expand Fields to expand
     * @return array|false Page data
     */
    public function getPage($pageId, $expand = ['body.storage', 'version', 'space', 'metadata'])
    {
        $params = ['expand' => implode(',', $expand)];
        return $this->request("content/{$pageId}", 'GET', [], $params);
    }
    
    /**
     * Update page
     * 
     * @param string $pageId Page ID
     * @param string $title New title
     * @param string $content New content
     * @param int $version Current version number
     * @return array|false Updated page
     */
    public function updatePage($pageId, $title, $content, $version)
    {
        $data = [
            'id' => $pageId,
            'type' => 'page',
            'title' => $title,
            'body' => [
                'storage' => [
                    'value' => $content,
                    'representation' => 'storage'
                ]
            ],
            'version' => [
                'number' => $version + 1
            ]
        ];
        
        return $this->request("content/{$pageId}", 'PUT', $data);
    }
    
    /**
     * Delete page
     * 
     * @param string $pageId Page ID
     * @return bool Success
     */
    public function deletePage($pageId)
    {
        $response = $this->request("content/{$pageId}", 'DELETE');
        return ($response !== false);
    }
    
    /**
     * Get space pages
     * 
     * @param string|null $spaceKey Space key
     * @param array $params Listing parameters
     * @return array|false Pages
     */
    public function getSpacePages($spaceKey = null, $params = [])
    {
        $space = $spaceKey ?? $this->spaceKey;
        
        $defaultParams = [
            'space' => $space,
            'type' => 'page',
            'limit' => 25,
            'expand' => 'version,space,metadata'
        ];
        
        $params = array_merge($defaultParams, $params);
        
        return $this->request('content', 'GET', [], $params);
    }
    
    /**
     * Add comment to page
     * 
     * @param string $pageId Page ID
     * @param string $comment Comment text
     * @return array|false Comment data
     */
    public function addComment($pageId, $comment)
    {
        $data = [
            'type' => 'comment',
            'container' => [
                'type' => 'page',
                'id' => $pageId
            ],
            'body' => [
                'storage' => [
                    'value' => $comment,
                    'representation' => 'storage'
                ]
            ]
        ];
        
        return $this->request('content', 'POST', $data);
    }
    
    /**
     * Get page comments
     * 
     * @param string $pageId Page ID
     * @param array $params Listing parameters
     * @return array|false Comments
     */
    public function getComments($pageId, $params = [])
    {
        $defaultParams = ['expand' => 'body.storage,version'];
        $params = array_merge($defaultParams, $params);
        
        return $this->request("content/{$pageId}/child/comment", 'GET', [], $params);
    }
    
    /**
     * Add labels to content
     * 
     * @param string $contentId Content ID
     * @param array $labels Label list
     * @return array|false Labels data
     */
    public function addLabels($contentId, $labels)
    {
        $data = array_map(function($label) {
            return ['name' => $label];
        }, $labels);
        
        return $this->request("content/{$contentId}/label", 'POST', $data);
    }
    
    /**
     * Get labels
     * 
     * @param string $contentId Content ID
     * @return array|false Labels
     */
    public function getLabels($contentId)
    {
        return $this->request("content/{$contentId}/label", 'GET');
    }
    
    /**
     * Remove label
     * 
     * @param string $contentId Content ID
     * @param string $labelName Label name
     * @return bool Success
     */
    public function removeLabel($contentId, $labelName)
    {
        $response = $this->request("content/{$contentId}/label/{$labelName}", 'DELETE');
        return ($response !== false);
    }
    
    /**
     * Search content
     * 
     * @param string $cql CQL query
     * @param array $options Search options
     * @return array|false Search results
     */
    public function search($cql, $options = [])
    {
        $params = [
            'cql' => $cql,
            'limit' => $options['limit'] ?? 25,
            'start' => $options['start'] ?? 0,
        ];
        
        if (isset($options['expand'])) {
            $params['expand'] = $options['expand'];
        }
        
        return $this->request('search', 'GET', [], $params);
    }
    
    /**
     * Create space
     * 
     * @param string $key Space key
     * @param string $name Space name
     * @param string $description Space description
     * @return array|false Space data
     */
    public function createSpace($key, $name, $description = '')
    {
        $data = [
            'key' => $key,
            'name' => $name,
            'description' => [
                'plain' => [
                    'value' => $description,
                    'representation' => 'plain'
                ]
            ]
        ];
        
        return $this->request('space', 'POST', $data);
    }
    
    /**
     * Get spaces
     * 
     * @param array $params Listing parameters
     * @return array|false Spaces
     */
    public function getSpaces($params = [])
    {
        return $this->request('space', 'GET', [], $params);
    }
    
    /**
     * Add attachment to page
     * 
     * @param string $pageId Page ID
     * @param string $filePath File path
     * @param string $comment Comment
     * @return array|false Attachment data
     */
    public function addAttachment($pageId, $filePath, $comment = '')
    {
        if (!file_exists($filePath)) {
            $this->logError('File not found', ['path' => $filePath]);
            return false;
        }
        
        $url = $this->apiUrl . "/content/{$pageId}/child/attachment";
        $boundary = $this->generateBoundary();
        $filename = basename($filePath);
        $fileContent = file_get_contents($filePath);
        $mimeType = mime_content_type($filePath);
        
        $body = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: {$mimeType}\r\n\r\n";
        $body .= $fileContent . "\r\n";
        
        if ($comment) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"comment\"\r\n\r\n";
            $body .= $comment . "\r\n";
        }
        
        $body .= "--{$boundary}--\r\n";
        
        $headers = ['Content-Type: multipart/form-data; boundary=' . $boundary];
        
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
            $this->logError('Attachment upload failed', ['status' => $httpCode, 'page_id' => $pageId]);
            return false;
        }
        
        $decoded = json_decode($response, true);
        
        if ($decoded && isset($decoded['results'][0]['id'])) {
            return $decoded['results'][0];
        }
        
        return false;
    }
    
    /**
     * Get attachments
     * 
     * @param string $pageId Page ID
     * @return array|false Attachments
     */
    public function getAttachments($pageId)
    {
        return $this->request("content/{$pageId}/child/attachment", 'GET');
    }
    
    /**
     * Get page history
     * 
     * @param string $pageId Page ID
     * @param int $limit Limit
     * @return array|false Versions
     */
    public function getPageHistory($pageId, $limit = 25)
    {
        $params = ['expand' => 'version', 'limit' => $limit];
        return $this->request("content/{$pageId}/version", 'GET', [], $params);
    }
    
    /**
     * Restore page to specific version
     * 
     * @param string $pageId Page ID
     * @param int $versionNumber Version number
     * @return array|false Restored page
     */
    public function restorePageVersion($pageId, $versionNumber)
    {
        $versionData = $this->request("content/{$pageId}/version/{$versionNumber}", 'GET');
        
        if (!$versionData) {
            $this->logError('Version not found', ['page_id' => $pageId, 'version' => $versionNumber]);
            return false;
        }
        
        $restoreData = [
            'id' => $pageId,
            'type' => 'page',
            'title' => $versionData['title'],
            'body' => $versionData['body'],
            'version' => [
                'number' => $versionData['version']['number'] + 1,
                'message' => 'Restored from version ' . $versionNumber
            ]
        ];
        
        return $this->request("content/{$pageId}", 'PUT', $restoreData);
    }
    
    /**
     * Create template
     * 
     * @param string $name Template name
     * @param string $content Template content
     * @param string|null $spaceKey Space key
     * @return array|false Template data
     */
    public function createTemplate($name, $content, $spaceKey = null)
    {
        $space = $spaceKey ?? $this->spaceKey;
        
        $data = [
            'name' => $name,
            'templateType' => 'page',
            'body' => [
                'storage' => [
                    'value' => $content,
                    'representation' => 'storage'
                ]
            ],
            'space' => ['key' => $space]
        ];
        
        return $this->request('template', 'POST', $data);
    }
    
    /**
     * Create page from template
     * 
     * @param string $templateId Template ID
     * @param string $title Page title
     * @param string|null $spaceKey Space key
     * @return array|false Page data
     */
    public function createPageFromTemplate($templateId, $title, $spaceKey = null)
    {
        $space = $spaceKey ?? $this->spaceKey;
        
        $data = [
            'templateId' => $templateId,
            'spaceKey' => $space,
            'title' => $title
        ];
        
        return $this->request('content/blueprint/instance', 'POST', $data);
    }
    
    /**
     * Get page permissions
     * 
     * @param string $pageId Page ID
     * @return array|false Permissions
     */
    public function getPagePermissions($pageId)
    {
        return $this->request("content/{$pageId}/permission", 'GET');
    }
    
    /**
     * Add watcher
     * 
     * @param string $contentId Content ID
     * @param string $userId User ID
     * @return bool Success
     */
    public function addWatcher($contentId, $userId)
    {
        $data = ['userId' => $userId];
        $response = $this->request("content/{$contentId}/watcher", 'POST', $data);
        return ($response !== false);
    }
    
    /**
     * Test connection
     * 
     * @return bool Connection successful
     */
    public function testConnection()
    {
        $response = $this->request('space', 'GET', [], ['limit' => 1]);
        return ($response !== false);
    }
}