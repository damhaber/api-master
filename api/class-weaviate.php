<?php
/**
 * Weaviate API Class for Masal Panel
 * 
 * Vector database, schema management, object storage, GraphQL queries
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Direct access prevention
}

class APIMaster_API_Weaviate implements APIMaster_APIInterface
{
    /**
     * Cluster URL
     * @var string
     */
    private $clusterUrl;
    
    /**
     * API Key
     * @var string
     */
    private $apiKey;
    
    /**
     * Default schema class
     * @var string
     */
    private $defaultClass;
    
    /**
     * Model (default class name)
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
        $this->clusterUrl = $this->config['cluster_url'] ?? '';
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->defaultClass = $this->config['default_class'] ?? 'Document';
        $this->model = $this->defaultClass;
        
        $this->apiUrl = rtrim($this->clusterUrl, '/') . '/v1';
    }
    
    /**
     * Load configuration from JSON file
     * 
     * @return array Configuration data
     */
    private function loadConfig()
    {
        $configFile = __DIR__ . '/config/weaviate.json';
        
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
        $logFile = $logDir . '/weaviate-error.log';
        
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
     * Get request headers
     * 
     * @return array Headers
     */
    private function getHeaders()
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        if ($this->apiKey) {
            $headers['Authorization: Bearer ' . $this->apiKey] = '';
        }
        
        return array_keys($headers);
    }
    
    /**
     * Get header values as associative array for curl
     * 
     * @return array Headers for curl
     */
    private function getHeaderArray()
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        if ($this->apiKey) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }
        
        return $headers;
    }
    
    /**
     * Make curl request to Weaviate API
     * 
     * @param string $url Full URL
     * @param string $method HTTP method
     * @param array|string|null $data Request data
     * @return array|true|false Response data (true for 204 No Content)
     */
    private function curlRequest($url, $method = 'GET', $data = null)
    {
        $ch = curl_init();
        
        $method = strtoupper($method);
        
        // Build URL with query parameters for GET
        if ($method === 'GET' && is_array($data) && !empty($data)) {
            $url .= '?' . http_build_query($data);
            $data = null;
        }
        
        // Set common options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaderArray());
        
        // Set method specific options
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
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
            $errorMsg = isset($decoded['error'][0]['message']) ? $decoded['error'][0]['message'] : 
                       (isset($decoded['error']) ? $decoded['error'] : 'HTTP ' . $httpCode);
            $this->logError('API Error', ['status' => $httpCode, 'error' => $errorMsg, 'url' => $url]);
            return false;
        }
        
        return $decoded;
    }
    
    /**
     * GraphQL query
     * 
     * @param string $query GraphQL query
     * @param array $variables Variables
     * @return array|false Query result
     */
    private function graphQL($query, $variables = [])
    {
        $url = $this->apiUrl . '/graphql';
        
        $data = ['query' => $query];
        
        if (!empty($variables)) {
            $data['variables'] = $variables;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaderArray());
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            $this->logError('GraphQL CURL Error', ['error' => $curlError]);
            return false;
        }
        
        $decoded = json_decode($response, true);
        
        if (isset($decoded['errors'])) {
            $this->logError('GraphQL errors', ['errors' => $decoded['errors']]);
            return false;
        }
        
        return $decoded;
    }
    
    /**
     * REST API request
     * 
     * @param string $endpoint Endpoint
     * @param string $method HTTP method
     * @param array $data Request data
     * @return array|true|false Response
     */
    private function request($endpoint, $method = 'GET', $data = [])
    {
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        return $this->curlRequest($url, $method, $data);
    }
    
    /**
     * Build GraphQL fields string
     * 
     * @param array $additionalFields Additional fields
     * @return string Fields string
     */
    private function buildGraphQLFields($additionalFields = [])
    {
        if (!empty($additionalFields)) {
            return '_additional { ' . implode(' ', $additionalFields) . ' }';
        }
        return '';
    }
    
    /**
     * Set API key
     * 
     * @param string $apiKey Weaviate API key
     * @return void
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }
    
    /**
     * Set model (class name)
     * 
     * @param string $model Class name
     * @return void
     */
    public function setModel($model)
    {
        $this->model = $model;
        $this->defaultClass = $model;
    }
    
    /**
     * Get current model
     * 
     * @return string Current class name
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
        return $this->request($endpoint, 'GET', $params);
    }
    
    /**
     * Stream (not supported by Weaviate)
     * 
     * @param string $endpoint API endpoint
     * @param callable $callback Callback function
     * @return void
     */
    public function stream($endpoint, $callback)
    {
        $this->logError('Stream method not supported by Weaviate API');
    }
    
    /**
     * Get available models (classes)
     * 
     * @return array|false List of classes
     */
    public function getModels()
    {
        $schema = $this->getSchema();
        if ($schema && isset($schema['classes'])) {
            return $schema['classes'];
        }
        return false;
    }
    
    /**
     * Get API capabilities
     * 
     * @return array Capabilities list
     */
    public function getCapabilities()
    {
        return [
            'schema' => ['create', 'read', 'delete'],
            'objects' => ['create', 'read', 'update', 'delete', 'list', 'batch'],
            'vector_search' => ['near_vector', 'near_text', 'hybrid'],
            'filter_search' => ['where'],
            'aggregations' => ['aggregate'],
            'references' => ['create'],
            'backup' => ['create', 'read']
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
     * Chat (not supported by Weaviate)
     * 
     * @param string $message Message content
     * @param array $context Context parameters
     * @return array|false Response
     */
    public function chat($message, $context = [])
    {
        // Use nearText for chat-like functionality
        $limit = $context['limit'] ?? 5;
        $class = $context['class'] ?? $this->defaultClass;
        return $this->nearText($message, $class, $limit);
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
        
        if (isset($response[0]['properties'])) {
            $texts = [];
            foreach ($response as $item) {
                if (isset($item['properties'])) {
                    $texts[] = json_encode($item['properties']);
                }
            }
            return implode(', ', $texts);
        }
        
        if (isset($response['properties'])) {
            return json_encode($response['properties']);
        }
        
        if (isset($response['objects'])) {
            return $this->extractText($response['objects']);
        }
        
        return json_encode($response);
    }
    
    // ========== WEAVIATE SPECIFIC METHODS ==========
    
    /**
     * Create schema class
     * 
     * @param string $className Class name
     * @param array $properties Property list
     * @param string $vectorizer Vectorizer (e.g., 'text2vec-openai')
     * @return bool Success
     */
    public function createClass($className, $properties, $vectorizer = 'text2vec-openai')
    {
        $data = [
            'class' => $className,
            'properties' => $properties,
            'vectorizer' => $vectorizer,
        ];
        
        $response = $this->request('schema', 'POST', $data);
        return ($response !== false);
    }
    
    /**
     * Get schema
     * 
     * @return array|false Schema data
     */
    public function getSchema()
    {
        return $this->request('schema', 'GET');
    }
    
    /**
     * Get class details
     * 
     * @param string|null $className Class name
     * @return array|false Class details
     */
    public function getClass($className = null)
    {
        $class = $className ?? $this->defaultClass;
        return $this->request('schema/' . $class, 'GET');
    }
    
    /**
     * Delete class
     * 
     * @param string|null $className Class name
     * @return bool Success
     */
    public function deleteClass($className = null)
    {
        $class = $className ?? $this->defaultClass;
        $response = $this->request('schema/' . $class, 'DELETE');
        return ($response !== false);
    }
    
    /**
     * Create object
     * 
     * @param array $properties Property values
     * @param string|null $className Class name
     * @param string|null $id Object ID (optional)
     * @param array|null $vector Vector (optional)
     * @return array|false Created object
     */
    public function createObject($properties, $className = null, $id = null, $vector = null)
    {
        $class = $className ?? $this->defaultClass;
        
        $data = ['class' => $class, 'properties' => $properties];
        
        if ($id) {
            $data['id'] = $id;
        }
        
        if ($vector) {
            $data['vector'] = $vector;
        }
        
        return $this->request('objects', 'POST', $data);
    }
    
    /**
     * Get object
     * 
     * @param string $id Object ID
     * @param string|null $className Class name
     * @param array $include Include fields
     * @return array|false Object data
     */
    public function getObject($id, $className = null, $include = [])
    {
        $class = $className ?? $this->defaultClass;
        $params = [];
        
        if (!empty($include)) {
            $params['include'] = implode(',', $include);
        }
        
        return $this->request('objects/' . $class . '/' . $id, 'GET', $params);
    }
    
    /**
     * Update object
     * 
     * @param string $id Object ID
     * @param array $properties New property values
     * @param string|null $className Class name
     * @return bool Success
     */
    public function updateObject($id, $properties, $className = null)
    {
        $class = $className ?? $this->defaultClass;
        
        $data = [
            'class' => $class,
            'properties' => $properties,
        ];
        
        $response = $this->request('objects/' . $class . '/' . $id, 'PUT', $data);
        return ($response !== false);
    }
    
    /**
     * Delete object
     * 
     * @param string $id Object ID
     * @param string|null $className Class name
     * @return bool Success
     */
    public function deleteObject($id, $className = null)
    {
        $class = $className ?? $this->defaultClass;
        $response = $this->request('objects/' . $class . '/' . $id, 'DELETE');
        return ($response !== false);
    }
    
    /**
     * List objects
     * 
     * @param string|null $className Class name
     * @param int $limit Limit
     * @param string|null $after After cursor
     * @return array|false Objects list
     */
    public function listObjects($className = null, $limit = 100, $after = null)
    {
        $class = $className ?? $this->defaultClass;
        
        $params = [
            'class' => $class,
            'limit' => $limit,
        ];
        
        if ($after) {
            $params['after'] = $after;
        }
        
        return $this->request('objects', 'GET', $params);
    }
    
    /**
     * Near vector search
     * 
     * @param array $vector Vector values
     * @param string|null $className Class name
     * @param int $limit Limit
     * @param array $additionalFields Additional fields
     * @param float|null $distance Distance threshold
     * @return array|false Search results
     */
    public function nearVector($vector, $className = null, $limit = 10, $additionalFields = ['distance'], $distance = null)
    {
        $class = $className ?? $this->defaultClass;
        $fields = $this->buildGraphQLFields($additionalFields);
        
        $nearVector = ['vector' => $vector];
        if ($distance !== null) {
            $nearVector['distance'] = $distance;
        }
        
        $query = '{
            Get {
                ' . $class . '(limit: ' . $limit . ', nearVector: ' . json_encode($nearVector) . ') {
                    ' . $fields . '
                }
            }
        }';
        
        $result = $this->graphQL($query);
        
        if ($result && isset($result['data']['Get'][$class])) {
            return $result['data']['Get'][$class];
        }
        
        return false;
    }
    
    /**
     * Near text search
     * 
     * @param string $text Search text
     * @param string|null $className Class name
     * @param int $limit Limit
     * @param array $additionalFields Additional fields
     * @return array|false Search results
     */
    public function nearText($text, $className = null, $limit = 10, $additionalFields = ['distance'])
    {
        $class = $className ?? $this->defaultClass;
        $fields = $this->buildGraphQLFields($additionalFields);
        
        $nearText = ['concepts' => [$text]];
        
        $query = '{
            Get {
                ' . $class . '(limit: ' . $limit . ', nearText: ' . json_encode($nearText) . ') {
                    ' . $fields . '
                }
            }
        }';
        
        $result = $this->graphQL($query);
        
        if ($result && isset($result['data']['Get'][$class])) {
            return $result['data']['Get'][$class];
        }
        
        return false;
    }
    
    /**
     * Hybrid search (Vector + Keyword)
     * 
     * @param string $query Search query
     * @param string|null $className Class name
     * @param int $limit Limit
     * @param float $alpha Vector vs keyword weight (0-1)
     * @param array $additionalFields Additional fields
     * @return array|false Search results
     */
    public function hybridSearch($query, $className = null, $limit = 10, $alpha = 0.5, $additionalFields = ['score'])
    {
        $class = $className ?? $this->defaultClass;
        $fields = $this->buildGraphQLFields($additionalFields);
        
        $hybrid = [
            'query' => $query,
            'alpha' => $alpha,
        ];
        
        $queryGql = '{
            Get {
                ' . $class . '(limit: ' . $limit . ', hybrid: ' . json_encode($hybrid) . ') {
                    ' . $fields . '
                }
            }
        }';
        
        $result = $this->graphQL($queryGql);
        
        if ($result && isset($result['data']['Get'][$class])) {
            return $result['data']['Get'][$class];
        }
        
        return false;
    }
    
    /**
     * Where filter search
     * 
     * @param array $whereFilter Where filter
     * @param string|null $className Class name
     * @param int $limit Limit
     * @param array $additionalFields Additional fields
     * @return array|false Search results
     */
    public function whereFilter($whereFilter, $className = null, $limit = 10, $additionalFields = [])
    {
        $class = $className ?? $this->defaultClass;
        $fields = $this->buildGraphQLFields($additionalFields);
        
        $query = '{
            Get {
                ' . $class . '(limit: ' . $limit . ', where: ' . json_encode($whereFilter) . ') {
                    ' . $fields . '
                }
            }
        }';
        
        $result = $this->graphQL($query);
        
        if ($result && isset($result['data']['Get'][$class])) {
            return $result['data']['Get'][$class];
        }
        
        return false;
    }
    
    /**
     * Aggregate query
     * 
     * @param string|null $className Class name
     * @param string|null $groupBy Group by field
     * @param array $metaAggregates Meta aggregates
     * @return array|false Aggregate results
     */
    public function aggregate($className = null, $groupBy = null, $metaAggregates = [])
    {
        $class = $className ?? $this->defaultClass;
        
        $aggregateFields = [];
        foreach ($metaAggregates as $field => $aggregates) {
            $aggregateFields[] = $field . ' { ' . implode(' ', $aggregates) . ' }';
        }
        
        $groupByClause = '';
        if ($groupBy) {
            $groupByClause = 'groupBy: ["' . $groupBy . '"]';
        }
        
        $query = '{
            Aggregate {
                ' . $class . '(' . $groupByClause . ') {
                    ' . implode("\n", $aggregateFields) . '
                }
            }
        }';
        
        $result = $this->graphQL($query);
        
        if ($result && isset($result['data']['Aggregate'][$class])) {
            return $result['data']['Aggregate'][$class];
        }
        
        return false;
    }
    
    /**
     * Batch create objects
     * 
     * @param array $objects Object list
     * @param string|null $className Class name
     * @return array|false Batch results
     */
    public function batchCreate($objects, $className = null)
    {
        $class = $className ?? $this->defaultClass;
        
        $batchObjects = [];
        foreach ($objects as $obj) {
            $batchObjects[] = [
                'class' => $class,
                'properties' => $obj,
            ];
        }
        
        $data = ['objects' => $batchObjects];
        
        return $this->request('batch/objects', 'POST', $data);
    }
    
    /**
     * Add reference (cross-reference)
     * 
     * @param string $fromId Source object ID
     * @param string $fromClass Source class
     * @param string $property Property name
     * @param string $toId Target object ID
     * @param string $toClass Target class
     * @return bool Success
     */
    public function addReference($fromId, $fromClass, $property, $toId, $toClass)
    {
        $beacon = 'weaviate://localhost/' . $toClass . '/' . $toId;
        
        $data = [
            'property' => $property,
            'href' => $beacon,
        ];
        
        $response = $this->request('objects/' . $fromClass . '/' . $fromId . '/references/' . $property, 'POST', $data);
        return ($response !== false);
    }
    
    /**
     * Create backup
     * 
     * @param string $backupId Backup ID
     * @param array $includeClasses Include classes
     * @return array|false Backup status
     */
    public function createBackup($backupId, $includeClasses = [])
    {
        $data = ['id' => $backupId];
        
        if (!empty($includeClasses)) {
            $data['include'] = $includeClasses;
        }
        
        return $this->request('backups/s3', 'POST', $data);
    }
    
    /**
     * Get backup status
     * 
     * @param string $backupId Backup ID
     * @return array|false Backup status
     */
    public function getBackupStatus($backupId)
    {
        return $this->request('backups/s3/' . $backupId, 'GET');
    }
    
    /**
     * Get meta information
     * 
     * @return array|false Meta info
     */
    public function getMeta()
    {
        return $this->request('meta', 'GET');
    }
    
    /**
     * Get node status
     * 
     * @return array|false Node status
     */
    public function getNodeStatus()
    {
        return $this->request('nodes', 'GET');
    }
    
    /**
     * Test connection
     * 
     * @return bool Connection successful
     */
    public function testConnection()
    {
        $response = $this->getMeta();
        return ($response !== false);
    }
}