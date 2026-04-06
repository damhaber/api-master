<?php
/**
 * Pinecone API Class for Masal Panel
 * 
 * Vector database, embedding management, similarity search, index management
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Direct access prevention
}

class APIMaster_API_Pinecone implements APIMaster_APIInterface
{
    /**
     * API Key
     * @var string
     */
    private $apiKey;
    
    /**
     * Environment
     * @var string
     */
    private $environment;
    
    /**
     * Index name
     * @var string
     */
    private $indexName;
    
    /**
     * Project ID
     * @var string
     */
    private $projectId;
    
    /**
     * Model (index name)
     * @var string
     */
    private $model;
    
    /**
     * Config array
     * @var array
     */
    private $config;
    
    /**
     * API base URL (Control Plane)
     * @var string
     */
    private $apiUrl = 'https://api.pinecone.io';
    
    /**
     * Index host URL (Data Plane)
     * @var string|null
     */
    private $indexHost;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->environment = $this->config['environment'] ?? '';
        $this->indexName = $this->config['index_name'] ?? '';
        $this->projectId = $this->config['project_id'] ?? '';
        $this->model = $this->indexName;
        
        $this->initIndexHost();
    }
    
    /**
     * Load configuration from JSON file
     * 
     * @return array Configuration data
     */
    private function loadConfig()
    {
        $configFile = __DIR__ . '/config/pinecone.json';
        
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
        $logFile = $logDir . '/pinecone-error.log';
        
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
     * Initialize index host URL
     */
    private function initIndexHost()
    {
        if ($this->indexName && $this->environment && $this->projectId) {
            $this->indexHost = "https://{$this->indexName}-{$this->projectId}.svc.{$this->environment}.pinecone.io";
        }
    }
    
    /**
     * Get Control Plane headers
     * 
     * @return array Headers
     */
    private function getControlHeaders()
    {
        return [
            'Api-Key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
    }
    
    /**
     * Get Data Plane headers
     * 
     * @return array Headers
     */
    private function getDataHeaders()
    {
        return [
            'Api-Key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
    }
    
    /**
     * Make curl request to Pinecone API
     * 
     * @param string $url Full URL
     * @param string $method HTTP method
     * @param array|string|null $data Request data
     * @param array $headers Headers
     * @return array|true|false Response data (true for 204 No Content)
     */
    private function curlRequest($url, $method = 'GET', $data = null, $headers = [])
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
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
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
            $errorMsg = isset($decoded['message']) ? $decoded['message'] : 
                       (isset($decoded['error']) ? $decoded['error'] : 'HTTP ' . $httpCode);
            $this->logError('API Error', ['status' => $httpCode, 'error' => $errorMsg, 'url' => $url]);
            return false;
        }
        
        return $decoded;
    }
    
    /**
     * Control Plane API request
     * 
     * @param string $endpoint Endpoint
     * @param string $method HTTP method
     * @param array $data Request data
     * @return array|true|false Response
     */
    private function controlRequest($endpoint, $method = 'GET', $data = [])
    {
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        return $this->curlRequest($url, $method, $data, $this->getControlHeaders());
    }
    
    /**
     * Data Plane API request (Index operations)
     * 
     * @param string $endpoint Endpoint
     * @param string $method HTTP method
     * @param array $data Request data
     * @return array|true|false Response
     */
    private function dataRequest($endpoint, $method = 'POST', $data = [])
    {
        if (!$this->indexHost) {
            $this->logError('Index host not configured');
            return false;
        }
        
        $url = $this->indexHost . '/' . ltrim($endpoint, '/');
        
        // For GET requests with query string in endpoint
        if ($method === 'GET' && strpos($endpoint, '?') !== false) {
            return $this->curlRequest($url, 'GET', null, $this->getDataHeaders());
        }
        
        return $this->curlRequest($url, $method, $data, $this->getDataHeaders());
    }
    
    /**
     * Set API key
     * 
     * @param string $apiKey Pinecone API key
     * @return void
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }
    
    /**
     * Set model (index name)
     * 
     * @param string $model Index name
     * @return void
     */
    public function setModel($model)
    {
        $this->model = $model;
        $this->indexName = $model;
        $this->initIndexHost();
    }
    
    /**
     * Get current model
     * 
     * @return string Current index name
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
        if (strpos($endpoint, 'control/') === 0) {
            return $this->controlRequest(substr($endpoint, 8), 'GET', $params);
        }
        return $this->dataRequest($endpoint, 'GET', $params);
    }
    
    /**
     * Stream (not supported by Pinecone)
     * 
     * @param string $endpoint API endpoint
     * @param callable $callback Callback function
     * @return void
     */
    public function stream($endpoint, $callback)
    {
        $this->logError('Stream method not supported by Pinecone API');
    }
    
    /**
     * Get available models (indexes)
     * 
     * @return array|false List of indexes
     */
    public function getModels()
    {
        $response = $this->listIndexes();
        if ($response && isset($response['indexes'])) {
            return $response['indexes'];
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
            'indexes' => ['create', 'read', 'update', 'delete', 'scale'],
            'vectors' => ['upsert', 'fetch', 'delete', 'query', 'update'],
            'namespaces' => ['list', 'delete'],
            'metadata' => ['filter', 'update'],
            'batch_operations' => ['upsert']
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
     * Chat (not supported by Pinecone)
     * 
     * @param string $message Message content
     * @param array $context Context parameters
     * @return array|false Response
     */
    public function chat($message, $context = [])
    {
        $this->logError('Chat method not supported by Pinecone API. Use queryVectors() for similarity search.');
        return false;
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
        
        if (isset($response['matches'])) {
            $texts = [];
            foreach ($response['matches'] as $match) {
                if (isset($match['id'])) {
                    $texts[] = $match['id'];
                }
                if (isset($match['metadata']['text'])) {
                    $texts[] = $match['metadata']['text'];
                }
            }
            return implode(', ', $texts);
        }
        
        if (isset($response['vectors'])) {
            return implode(', ', array_keys($response['vectors']));
        }
        
        if (isset($response['count'])) {
            return 'Vector count: ' . $response['count'];
        }
        
        return json_encode($response);
    }
    
    // ========== PINECONE SPECIFIC METHODS ==========
    
    /**
     * Create index
     * 
     * @param string $name Index name
     * @param int $dimension Vector dimension
     * @param string $metric Metric (cosine, euclidean, dotproduct)
     * @param string $podType Pod type
     * @return bool Success
     */
    public function createIndex($name, $dimension, $metric = 'cosine', $podType = 'p1.x1')
    {
        $data = [
            'name' => $name,
            'dimension' => $dimension,
            'metric' => $metric,
            'spec' => [
                'pod' => [
                    'environment' => $this->environment,
                    'pod_type' => $podType,
                ]
            ]
        ];
        
        $response = $this->controlRequest('indexes', 'POST', $data);
        return ($response !== false);
    }
    
    /**
     * List indexes
     * 
     * @return array|false Index list
     */
    public function listIndexes()
    {
        return $this->controlRequest('indexes', 'GET');
    }
    
    /**
     * Describe index
     * 
     * @param string|null $name Index name
     * @return array|false Index details
     */
    public function describeIndex($name = null)
    {
        $indexName = $name ?? $this->indexName;
        return $this->controlRequest('indexes/' . $indexName, 'GET');
    }
    
    /**
     * Delete index
     * 
     * @param string|null $name Index name
     * @return bool Success
     */
    public function deleteIndex($name = null)
    {
        $indexName = $name ?? $this->indexName;
        $response = $this->controlRequest('indexes/' . $indexName, 'DELETE');
        return ($response !== false);
    }
    
    /**
     * Scale index
     * 
     * @param string $name Index name
     * @param int|null $replicas Number of replicas
     * @param string|null $podType Pod type
     * @return bool Success
     */
    public function scaleIndex($name, $replicas = null, $podType = null)
    {
        $data = ['spec' => ['pod' => []]];
        
        if ($replicas !== null) {
            $data['spec']['pod']['replicas'] = $replicas;
        }
        
        if ($podType !== null) {
            $data['spec']['pod']['pod_type'] = $podType;
        }
        
        $response = $this->controlRequest('indexes/' . $name . '/configure', 'PATCH', $data);
        return ($response !== false);
    }
    
    /**
     * Upsert vectors
     * 
     * @param array $vectors Vector list (id, values, metadata)
     * @param string|null $namespace Namespace (optional)
     * @return array|false Upsert result
     */
    public function upsertVectors($vectors, $namespace = null)
    {
        $data = ['vectors' => $vectors];
        
        if ($namespace) {
            $data['namespace'] = $namespace;
        }
        
        return $this->dataRequest('vectors/upsert', 'POST', $data);
    }
    
    /**
     * Upsert single vector
     * 
     * @param string $id Vector ID
     * @param array $values Vector values
     * @param array $metadata Metadata (optional)
     * @param string|null $namespace Namespace (optional)
     * @return array|false Upsert result
     */
    public function upsertVector($id, $values, $metadata = [], $namespace = null)
    {
        $vector = ['id' => $id, 'values' => $values];
        
        if (!empty($metadata)) {
            $vector['metadata'] = $metadata;
        }
        
        return $this->upsertVectors([$vector], $namespace);
    }
    
    /**
     * Query vectors (similarity search)
     * 
     * @param array $queryVectors Query vectors
     * @param int $topK Number of results
     * @param string|null $namespace Namespace (optional)
     * @param array $filter Metadata filter
     * @param bool $includeMetadata Include metadata
     * @param bool $includeValues Include values
     * @return array|false Query results
     */
    public function queryVectors($queryVectors, $topK = 10, $namespace = null, $filter = [], $includeMetadata = true, $includeValues = false)
    {
        $data = [
            'vector' => $queryVectors,
            'topK' => $topK,
            'includeMetadata' => $includeMetadata,
            'includeValues' => $includeValues,
        ];
        
        if ($namespace) {
            $data['namespace'] = $namespace;
        }
        
        if (!empty($filter)) {
            $data['filter'] = $filter;
        }
        
        return $this->dataRequest('query', 'POST', $data);
    }
    
    /**
     * Query by vector ID
     * 
     * @param string $vectorId Vector ID
     * @param int $topK Number of results
     * @param string|null $namespace Namespace (optional)
     * @param array $filter Metadata filter
     * @return array|false Query results
     */
    public function queryById($vectorId, $topK = 10, $namespace = null, $filter = [])
    {
        $data = [
            'id' => $vectorId,
            'topK' => $topK,
            'includeMetadata' => true,
            'includeValues' => false,
        ];
        
        if ($namespace) {
            $data['namespace'] = $namespace;
        }
        
        if (!empty($filter)) {
            $data['filter'] = $filter;
        }
        
        return $this->dataRequest('query', 'POST', $data);
    }
    
    /**
     * Fetch vectors by IDs
     * 
     * @param array $ids Vector IDs
     * @param string|null $namespace Namespace (optional)
     * @return array|false Vectors
     */
    public function fetchVectors($ids, $namespace = null)
    {
        $params = ['ids' => implode(',', $ids)];
        
        if ($namespace) {
            $params['namespace'] = $namespace;
        }
        
        $url = 'vectors/fetch?' . http_build_query($params);
        return $this->dataRequest($url, 'GET', []);
    }
    
    /**
     * Delete vectors
     * 
     * @param array $ids Vector IDs to delete
     * @param string|null $namespace Namespace (optional)
     * @param bool $deleteAll Delete all vectors
     * @return bool Success
     */
    public function deleteVectors($ids = [], $namespace = null, $deleteAll = false)
    {
        $data = [];
        
        if ($deleteAll) {
            $data['deleteAll'] = true;
        } elseif (!empty($ids)) {
            $data['ids'] = $ids;
        } else {
            $this->logError('No vectors specified for deletion');
            return false;
        }
        
        if ($namespace) {
            $data['namespace'] = $namespace;
        }
        
        $response = $this->dataRequest('vectors/delete', 'POST', $data);
        return ($response !== false);
    }
    
    /**
     * Get vector count in namespace
     * 
     * @param string|null $namespace Namespace (optional)
     * @return int|false Vector count
     */
    public function getVectorCount($namespace = null)
    {
        $params = [];
        if ($namespace) {
            $params['namespace'] = $namespace;
        }
        
        $url = 'vectors/count?' . http_build_query($params);
        $response = $this->dataRequest($url, 'GET', []);
        
        if ($response && isset($response['count'])) {
            return $response['count'];
        }
        
        return false;
    }
    
    /**
     * Update vector metadata
     * 
     * @param string $id Vector ID
     * @param array $metadata New metadata
     * @param string|null $namespace Namespace (optional)
     * @return bool Success
     */
    public function updateMetadata($id, $metadata, $namespace = null)
    {
        $data = [
            'id' => $id,
            'metadata' => $metadata,
        ];
        
        if ($namespace) {
            $data['namespace'] = $namespace;
        }
        
        $response = $this->dataRequest('vectors/update', 'POST', $data);
        return ($response !== false);
    }
    
    /**
     * Get index statistics
     * 
     * @param string|null $namespace Namespace (optional)
     * @return array|false Index stats
     */
    public function getIndexStats($namespace = null)
    {
        $params = [];
        if ($namespace) {
            $params['namespace'] = $namespace;
        }
        
        $url = 'describe_index_stats?' . http_build_query($params);
        return $this->dataRequest($url, 'GET', []);
    }
    
    /**
     * Batch upsert vectors
     * 
     * @param array $vectors Vector list
     * @param int $batchSize Batch size
     * @param string|null $namespace Namespace (optional)
     * @return array Batch results
     */
    public function batchUpsert($vectors, $batchSize = 100, $namespace = null)
    {
        $results = [
            'total' => count($vectors),
            'successful' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        $batches = array_chunk($vectors, $batchSize);
        
        foreach ($batches as $index => $batch) {
            $response = $this->upsertVectors($batch, $namespace);
            
            if ($response && isset($response['upsertedCount'])) {
                $results['successful'] += $response['upsertedCount'];
            } else {
                $results['failed'] += count($batch);
                $results['errors'][] = "Batch " . ($index + 1) . " failed";
            }
        }
        
        return $results;
    }
    
    /**
     * Query with metadata filter only
     * 
     * @param array $filter Metadata filter
     * @param int $topK Number of results
     * @param string|null $namespace Namespace (optional)
     * @return array|false Filtered results
     */
    public function queryWithFilter($filter, $topK = 50, $namespace = null)
    {
        // For metadata-only queries, use a zero vector
        $stats = $this->getIndexStats($namespace);
        if ($stats && isset($stats['dimension'])) {
            $randomVector = array_fill(0, $stats['dimension'], 0);
        } else {
            $randomVector = array_fill(0, 1536, 0);
        }
        
        return $this->queryVectors($randomVector, $topK, $namespace, $filter, true, false);
    }
    
    /**
     * List namespaces
     * 
     * @return array|false Namespace list
     */
    public function listNamespaces()
    {
        $stats = $this->getIndexStats();
        
        if ($stats && isset($stats['namespaces'])) {
            return array_keys($stats['namespaces']);
        }
        
        return [];
    }
    
    /**
     * Delete namespace
     * 
     * @param string $namespace Namespace to delete
     * @return bool Success
     */
    public function deleteNamespace($namespace)
    {
        return $this->deleteVectors([], $namespace, true);
    }
    
    /**
     * Test connection
     * 
     * @return bool Connection successful
     */
    public function testConnection()
    {
        $response = $this->listIndexes();
        return ($response !== false);
    }
}