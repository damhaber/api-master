<?php
/**
 * API Master Module - Notion API
 * Database, page, block yönetimi ve query işlemleri
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Notion implements APIMaster_APIInterface {
    
    /**
     * API Base URL
     * @var string
     */
    private $apiUrl = 'https://api.notion.com/v1/';
    
    /**
     * API Version
     * @var string
     */
    private $apiVersion = '2022-06-28';
    
    /**
     * Integration Token (API Key)
     * @var string|null
     */
    private $integrationToken = null;
    
    /**
     * API Key (alias for integration token)
     * @var string|null
     */
    private $apiKey = null;
    
    /**
     * Current model (for interface compatibility)
     * @var string|null
     */
    private $model = null;
    
    /**
     * Timeout in seconds
     * @var int
     */
    private $timeout = 30;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct(array $config = []) {
        if (isset($config['integration_token'])) {
            $this->integrationToken = $config['integration_token'];
            $this->apiKey = $config['integration_token'];
        }
        
        if (isset($config['api_key'])) {
            $this->apiKey = $config['api_key'];
            $this->integrationToken = $config['api_key'];
        }
        
        if (isset($config['api_version'])) {
            $this->apiVersion = $config['api_version'];
        }
        
        if (isset($config['timeout'])) {
            $this->timeout = (int) $config['timeout'];
        }
    }
    
    /**
     * Set API Key (Integration Token)
     * 
     * @param string $apiKey
     * @return self
     */
    public function setApiKey($apiKey) {
        $this->apiKey = $apiKey;
        $this->integrationToken = $apiKey;
        return $this;
    }
    
    /**
     * Set Model (for interface compatibility)
     * 
     * @param string $model
     * @return self
     */
    public function setModel($model) {
        $this->model = $model;
        return $this;
    }
    
    /**
     * Get Current Model
     * 
     * @return string|null
     */
    public function getModel() {
        return $this->model;
    }
    
    /**
     * Complete method - Create a page
     * 
     * @param string $prompt Page title or content
     * @param array $options Options (parent_id, parent_type, properties)
     * @return array Page creation result
     */
    public function complete($prompt, $options = []) {
        if (!$this->integrationToken) {
            return ['error' => 'Integration token is required'];
        }
        
        $parentId = $options['parent_id'] ?? null;
        if (!$parentId) {
            return ['error' => 'parent_id is required'];
        }
        
        $parentType = $options['parent_type'] ?? 'database';
        
        // Create title property
        $properties = $options['properties'] ?? [];
        if (empty($properties) && $parentType === 'database') {
            $properties = [
                'title' => [
                    'title' => [
                        ['text' => ['content' => $prompt]]
                    ]
                ]
            ];
        }
        
        return $this->createPage($parentId, $parentType, $properties);
    }
    
    /**
     * Stream method (not supported)
     * 
     * @param string $prompt Page title
     * @param callable $callback Callback function
     * @param array $options Options
     * @return void
     */
    public function stream($prompt, $callback, $options = []) {
        $result = $this->complete($prompt, $options);
        if (is_callable($callback)) {
            $callback(json_encode($result));
        }
    }
    
    /**
     * Get Available Models (Features)
     * 
     * @return array
     */
    public function getModels() {
        return [
            'database' => 'Database Operations',
            'page' => 'Page Operations',
            'block' => 'Block Operations',
            'query' => 'Query Database',
            'search' => 'Search Content'
        ];
    }
    
    /**
     * Get Provider Capabilities
     * 
     * @return array
     */
    public function getCapabilities() {
        return [
            'streaming' => false,
            'chat' => false,
            'completion' => true,
            'models' => true,
            'max_tokens' => null,
            'functions' => false,
            'vision' => false,
            'supported_features' => [
                'databases',
                'pages',
                'blocks',
                'query_database',
                'search',
                'rich_text',
                'mentions',
                'files',
                'embeds',
                'equations',
                'tables'
            ]
        ];
    }
    
    /**
     * Check API Health
     * 
     * @return array
     */
    public function checkHealth() {
        if (!$this->integrationToken) {
            return ['status' => 'error', 'message' => 'Integration token not configured'];
        }
        
        $result = $this->search('', ['property' => 'object', 'value' => 'page'], [], 1);
        
        if (isset($result['error'])) {
            return ['status' => 'error', 'message' => $result['error']];
        }
        
        if (isset($result['results'])) {
            return [
                'status' => 'healthy',
                'message' => 'API is working'
            ];
        }
        
        return ['status' => 'error', 'message' => 'Invalid response from API'];
    }
    
    /**
     * Chat method (not supported)
     * 
     * @param array $messages Messages array
     * @param array $options Options
     * @param callable|null $callback Callback for streaming
     * @return array
     */
    public function chat($messages, $options = [], $callback = null) {
        return [
            'error' => 'Chat method is not supported by Notion API',
            'supported_methods' => ['complete', 'createPage', 'queryDatabase', 'search']
        ];
    }
    
    /**
     * Extract Text from Response
     * 
     * @param array $response API response
     * @return string
     */
    public function extractText($response) {
        if (isset($response['error'])) {
            return $response['error'];
        }
        
        if (isset($response['id']) && isset($response['object'])) {
            return sprintf(
                "Notion %s created: %s",
                $response['object'],
                $response['id']
            );
        }
        
        if (isset($response['results'])) {
            return sprintf(
                "Found %d results",
                count($response['results'])
            );
        }
        
        return json_encode($response);
    }
    
    /**
     * Get Auth Headers
     * 
     * @return array
     */
    private function getHeaders() {
        return [
            'Authorization: Bearer ' . $this->integrationToken,
            'Content-Type: application/json',
            'Notion-Version: ' . $this->apiVersion
        ];
    }
    
    /**
     * Make API Request
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method
     * @return array Response
     */
    private function request($endpoint, $data = [], $method = 'GET') {
        if (!$this->integrationToken) {
            return ['error' => 'Integration token is empty'];
        }
        
        $url = $this->apiUrl . $endpoint;
        
        $ch = curl_init();
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['error' => $error, 'code' => $httpCode];
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return $result;
        }
        
        return [
            'error' => $result['message'] ?? 'Request failed',
            'code' => $httpCode,
            'details' => $result
        ];
    }
    
    /**
     * Get Database
     * 
     * @param string $databaseId Database ID
     * @return array Response
     */
    public function getDatabase($databaseId) {
        return $this->request("databases/{$databaseId}", [], 'GET');
    }
    
    /**
     * Query Database
     * 
     * @param string $databaseId Database ID
     * @param array $filter Filter conditions
     * @param array $sorts Sort conditions
     * @param int $pageSize Page size (max 100)
     * @param string $startCursor Start cursor for pagination
     * @return array Response
     */
    public function queryDatabase($databaseId, $filter = [], $sorts = [], $pageSize = 100, $startCursor = '') {
        $data = ['page_size' => min($pageSize, 100)];
        
        if (!empty($filter)) {
            $data['filter'] = $filter;
        }
        
        if (!empty($sorts)) {
            $data['sorts'] = $sorts;
        }
        
        if ($startCursor) {
            $data['start_cursor'] = $startCursor;
        }
        
        return $this->request("databases/{$databaseId}/query", $data, 'POST');
    }
    
    /**
     * Create Page
     * 
     * @param string $parentId Parent ID (database ID or page ID)
     * @param string $parentType Parent type (database or page)
     * @param array $properties Page properties
     * @param array $children Page children blocks
     * @return array Response
     */
    public function createPage($parentId, $parentType = 'database', $properties = [], $children = []) {
        $data = [];
        
        if ($parentType === 'database') {
            $data['parent'] = ['database_id' => $parentId];
        } else {
            $data['parent'] = ['page_id' => $parentId];
        }
        
        $data['properties'] = $properties;
        
        if (!empty($children)) {
            $data['children'] = $children;
        }
        
        return $this->request("pages", $data, 'POST');
    }
    
    /**
     * Update Page
     * 
     * @param string $pageId Page ID
     * @param array $properties Page properties
     * @param bool|null $archived Archive status
     * @return array Response
     */
    public function updatePage($pageId, $properties = [], $archived = null) {
        $data = [];
        
        if (!empty($properties)) {
            $data['properties'] = $properties;
        }
        
        if ($archived !== null) {
            $data['archived'] = $archived;
        }
        
        return $this->request("pages/{$pageId}", $data, 'PATCH');
    }
    
    /**
     * Get Page
     * 
     * @param string $pageId Page ID
     * @return array Response
     */
    public function getPage($pageId) {
        return $this->request("pages/{$pageId}", [], 'GET');
    }
    
    /**
     * Get Block Children
     * 
     * @param string $blockId Block ID
     * @param int $pageSize Page size
     * @param string $startCursor Start cursor
     * @return array Response
     */
    public function getBlockChildren($blockId, $pageSize = 100, $startCursor = '') {
        $params = ['page_size' => min($pageSize, 100)];
        
        if ($startCursor) {
            $params['start_cursor'] = $startCursor;
        }
        
        return $this->request("blocks/{$blockId}/children", $params, 'GET');
    }
    
    /**
     * Append Block Children
     * 
     * @param string $blockId Block ID
     * @param array $children Block children to append
     * @return array Response
     */
    public function appendBlockChildren($blockId, $children) {
        return $this->request("blocks/{$blockId}/children", ['children' => $children], 'PATCH');
    }
    
    /**
     * Search Pages/Databases
     * 
     * @param string $query Search query
     * @param array $filter Search filter
     * @param array $sort Sort options
     * @param int $pageSize Page size
     * @param string $startCursor Start cursor
     * @return array Response
     */
    public function search($query = '', $filter = [], $sort = [], $pageSize = 20, $startCursor = '') {
        $data = ['page_size' => min($pageSize, 100)];
        
        if ($query) {
            $data['query'] = $query;
        }
        
        if (!empty($filter)) {
            $data['filter'] = $filter;
        }
        
        if (!empty($sort)) {
            $data['sort'] = $sort;
        }
        
        if ($startCursor) {
            $data['start_cursor'] = $startCursor;
        }
        
        return $this->request("search", $data, 'POST');
    }
    
    /**
     * Create Text Block
     * 
     * @param string $text Text content
     * @param array $annotations Text annotations
     * @return array Block
     */
    public function createTextBlock($text, $annotations = []) {
        $richText = [
            'type' => 'text',
            'text' => ['content' => $text]
        ];
        
        if (!empty($annotations)) {
            $richText['annotations'] = $annotations;
        }
        
        return [
            'type' => 'paragraph',
            'paragraph' => [
                'rich_text' => [$richText]
            ]
        ];
    }
    
    /**
     * Create Heading Block
     * 
     * @param string $text Heading text
     * @param int $level Heading level (1, 2, or 3)
     * @return array Block
     */
    public function createHeadingBlock($text, $level = 1) {
        $level = min(max($level, 1), 3);
        $headingType = "heading_{$level}";
        
        return [
            'type' => $headingType,
            $headingType => [
                'rich_text' => [
                    [
                        'type' => 'text',
                        'text' => ['content' => $text]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Create Bulleted List Item
     * 
     * @param string $text List item text
     * @return array Block
     */
    public function createBulletListItem($text) {
        return [
            'type' => 'bulleted_list_item',
            'bulleted_list_item' => [
                'rich_text' => [
                    [
                        'type' => 'text',
                        'text' => ['content' => $text]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Create Numbered List Item
     * 
     * @param string $text List item text
     * @return array Block
     */
    public function createNumberedListItem($text) {
        return [
            'type' => 'numbered_list_item',
            'numbered_list_item' => [
                'rich_text' => [
                    [
                        'type' => 'text',
                        'text' => ['content' => $text]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Create To-Do Block
     * 
     * @param string $text To-do text
     * @param bool $checked Checked status
     * @return array Block
     */
    public function createTodoBlock($text, $checked = false) {
        return [
            'type' => 'to_do',
            'to_do' => [
                'rich_text' => [
                    [
                        'type' => 'text',
                        'text' => ['content' => $text]
                    ]
                ],
                'checked' => $checked
            ]
        ];
    }
    
    /**
     * Create Divider Block
     * 
     * @return array Block
     */
    public function createDividerBlock() {
        return [
            'type' => 'divider',
            'divider' => []
        ];
    }
    
    /**
     * Create Code Block
     * 
     * @param string $code Code content
     * @param string $language Programming language
     * @return array Block
     */
    public function createCodeBlock($code, $language = 'plain text') {
        return [
            'type' => 'code',
            'code' => [
                'rich_text' => [
                    [
                        'type' => 'text',
                        'text' => ['content' => $code]
                    ]
                ],
                'language' => $language
            ]
        ];
    }
    
    /**
     * Create Callout Block
     * 
     * @param string $text Callout text
     * @param string $emoji Emoji for callout
     * @return array Block
     */
    public function createCalloutBlock($text, $emoji = '💡') {
        return [
            'type' => 'callout',
            'callout' => [
                'rich_text' => [
                    [
                        'type' => 'text',
                        'text' => ['content' => $text]
                    ]
                ],
                'icon' => [
                    'type' => 'emoji',
                    'emoji' => $emoji
                ]
            ]
        ];
    }
    
    /**
     * Create Filter
     * 
     * @param string $property Property name
     * @param string $condition Filter condition
     * @param mixed $value Filter value
     * @return array Filter
     */
    public function createFilter($property, $condition, $value) {
        return [
            'property' => $property,
            $condition => $value
        ];
    }
    
    /**
     * Create Compound Filter (AND/OR)
     * 
     * @param string $operator 'and' or 'or'
     * @param array $filters Array of filters
     * @return array Compound filter
     */
    public function createCompoundFilter($operator, $filters) {
        return [
            $operator => $filters
        ];
    }
    
    /**
     * Create Sort
     * 
     * @param string $property Property name
     * @param string $direction 'ascending' or 'descending'
     * @return array Sort
     */
    public function createSort($property, $direction = 'ascending') {
        return [
            'property' => $property,
            'direction' => $direction
        ];
    }
}