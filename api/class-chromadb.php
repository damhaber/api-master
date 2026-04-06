<?php
/**
 * ChromaDB API Class for Masal Panel
 * 
 * Vector database, collection management, embedding storage, similarity search
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Direct access prevention
}

class APIMaster_API_ChromaDB implements APIMaster_APIInterface
{
    /**
     * ChromaDB host
     * @var string
     */
    private $host;
    
    /**
     * ChromaDB port
     * @var int
     */
    private $port;
    
    /**
     * Default collection name
     * @var string
     */
    private $defaultCollection;
    
    /**
     * Model (collection name)
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
        $this->host = $this->config['host'] ?? 'localhost';
        $this->port = $this->config['port'] ?? 8000;
        $this->defaultCollection = $this->config['default_collection'] ?? 'documents';
        $this->model = $this->defaultCollection;
        
        $this->apiUrl = "http://{$this->host}:{$this->port}/api/v1";
    }
    
    /**
     * Load configuration from JSON file
     * 
     * @return array Configuration data
     */
    private function loadConfig()
    {
        $configFile = __DIR__ . '/config/chromadb.json';
        
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
        $logFile = $logDir . '/chromadb-error.log';
        
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
        return [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
    }
    
    /**
     * Make curl request to ChromaDB API
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local development
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());
        
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
            $errorMsg = isset($decoded['error']) ? $decoded['error'] : 
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
     * @return array|true|false Response
     */
    private function request($endpoint, $method = 'GET', $data = [])
    {
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        return $this->curlRequest($url, $method, $data);
    }
    
    /**
     * Generate random ID
     * 
     * @return string Random ID
     */
    private function generateId()
    {
        return 'id_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }
    
    /**
     * Set API key (not used for ChromaDB, kept for interface)
     * 
     * @param string $apiKey API key
     * @return void
     */
    public function setApiKey($apiKey)
    {
        // ChromaDB doesn't use API key by default
        // This method exists for interface compatibility
    }
    
    /**
     * Set model (collection name)
     * 
     * @param string $model Collection name
     * @return void
     */
    public function setModel($model)
    {
        $this->model = $model;
        $this->defaultCollection = $model;
    }
    
    /**
     * Get current model
     * 
     * @return string Current collection name
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
     * Stream (not supported by ChromaDB)
     * 
     * @param string $endpoint API endpoint
     * @param callable $callback Callback function
     * @return void
     */
    public function stream($endpoint, $callback)
    {
        $this->logError('Stream method not supported by ChromaDB API');
    }
    
    /**
     * Get available models (collections)
     * 
     * @return array|false List of collections
     */
    public function getModels()
    {
        $collections = $this->listCollections();
        if ($collections) {
            return $collections;
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
            'collections' => ['create', 'read', 'update', 'delete', 'list'],
            'embeddings' => ['add', 'get', 'delete', 'query', 'count'],
            'metadata_filtering' => ['where', 'where_document'],
            'batch_operations' => ['add']
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
     * Chat (not supported by ChromaDB)
     * 
     * @param string $message Message content
     * @param array $context Context parameters
     * @return array|false Response
     */
    public function chat($message, $context = [])
    {
        // Use queryText for chat-like functionality
        $nResults = $context['n_results'] ?? 5;
        $collection = $context['collection'] ?? $this->defaultCollection;
        return $this->queryText($message, $nResults, [], $collection);
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
        
        if (isset($response['documents'])) {
            $texts = [];
            foreach ($response['documents'] as $docs) {
                if (is_array($docs)) {
                    $texts = array_merge($texts, $docs);
                } else {
                    $texts[] = $docs;
                }
            }
            return implode(', ', $texts);
        }
        
        if (isset($response['ids'])) {
            return implode(', ', $response['ids']);
        }
        
        if (isset($response['count'])) {
            return 'Count: ' . $response['count'];
        }
        
        return json_encode($response);
    }
    
    // ========== CHROMADB SPECIFIC METHODS ==========
    
    /**
     * Heartbeat check
     * 
     * @return array|false Heartbeat response
     */
    public function heartbeat()
    {
        return $this->request('heartbeat', 'GET');
    }
    
    /**
     * Create collection
     * 
     * @param string $name Collection name
     * @param array $metadata Metadata
     * @return array|false Collection data
     */
    public function createCollection($name, $metadata = [])
    {
        $data = ['name' => $name];
        
        if (!empty($metadata)) {
            $data['metadata'] = $metadata;
        }
        
        return $this->request('collections', 'POST', $data);
    }
    
    /**
     * List collections
     * 
     * @return array|false Collections list
     */
    public function listCollections()
    {
        return $this->request('collections', 'GET');
    }
    
    /**
     * Get collection details
     * 
     * @param string|null $name Collection name
     * @return array|false Collection details
     */
    public function getCollection($name = null)
    {
        $collectionName = $name ?? $this->defaultCollection;
        return $this->request('collections/' . urlencode($collectionName), 'GET');
    }
    
    /**
     * Delete collection
     * 
     * @param string|null $name Collection name
     * @return bool Success
     */
    public function deleteCollection($name = null)
    {
        $collectionName = $name ?? $this->defaultCollection;
        $response = $this->request('collections/' . urlencode($collectionName), 'DELETE');
        return ($response !== false);
    }
    
    /**
     * Update collection metadata
     * 
     * @param array $metadata New metadata
     * @param string|null $name Collection name
     * @return bool Success
     */
    public function updateCollection($metadata, $name = null)
    {
        $collectionName = $name ?? $this->defaultCollection;
        $data = ['metadata' => $metadata];
        $response = $this->request('collections/' . urlencode($collectionName), 'PUT', $data);
        return ($response !== false);
    }
    
    /**
     * Add embeddings
     * 
     * @param array $embeddings Embedding list
     * @param array $metadatas Metadata list
     * @param array $documents Document list
     * @param array $ids ID list
     * @param string|null $collectionName Collection name
     * @return bool Success
     */
    public function addEmbeddings($embeddings, $metadatas = [], $documents = [], $ids = [], $collectionName = null)
    {
        $collection = $collectionName ?? $this->defaultCollection;
        
        $data = ['embeddings' => $embeddings];
        
        if (!empty($metadatas)) {
            $data['metadatas'] = $metadatas;
        }
        
        if (!empty($documents)) {
            $data['documents'] = $documents;
        }
        
        if (!empty($ids)) {
            $data['ids'] = $ids;
        } else {
            $data['ids'] = array_map(function() {
                return $this->generateId();
            }, $embeddings);
        }
        
        $response = $this->request('collections/' . urlencode($collection) . '/add', 'POST', $data);
        return ($response !== false);
    }
    
    /**
     * Add single embedding
     * 
     * @param array $embedding Embedding vector
     * @param string $document Document text
     * @param array $metadata Metadata
     * @param string|null $id ID
     * @param string|null $collectionName Collection name
     * @return bool Success
     */
    public function addEmbedding($embedding, $document = '', $metadata = [], $id = null, $collectionName = null)
    {
        $id = $id ?? $this->generateId();
        
        return $this->addEmbeddings(
            [$embedding],
            [$metadata],
            [$document],
            [$id],
            $collectionName
        );
    }
    
    /**
     * Query embeddings (similarity search)
     * 
     * @param array $queryEmbeddings Query embeddings
     * @param int $nResults Number of results
     * @param array $where Metadata filter
     * @param array $whereDocument Document filter
     * @param string|null $collectionName Collection name
     * @return array|false Query results
     */
    public function queryEmbeddings($queryEmbeddings, $nResults = 10, $where = [], $whereDocument = [], $collectionName = null)
    {
        $collection = $collectionName ?? $this->defaultCollection;
        
        $data = [
            'query_embeddings' => $queryEmbeddings,
            'n_results' => $nResults,
        ];
        
        if (!empty($where)) {
            $data['where'] = $where;
        }
        
        if (!empty($whereDocument)) {
            $data['where_document'] = $whereDocument;
        }
        
        return $this->request('collections/' . urlencode($collection) . '/query', 'POST', $data);
    }
    
    /**
     * Query with text (automatic embedding)
     * 
     * @param string $queryText Query text
     * @param int $nResults Number of results
     * @param array $where Metadata filter
     * @param string|null $collectionName Collection name
     * @return array|false Query results
     */
    public function queryText($queryText, $nResults = 10, $where = [], $collectionName = null)
    {
        $collection = $collectionName ?? $this->defaultCollection;
        
        $data = [
            'query_texts' => [$queryText],
            'n_results' => $nResults,
        ];
        
        if (!empty($where)) {
            $data['where'] = $where;
        }
        
        return $this->request('collections/' . urlencode($collection) . '/query', 'POST', $data);
    }
    
    /**
     * Get embeddings
     * 
     * @param array $ids ID list
     * @param array $where Metadata filter
     * @param string|null $collectionName Collection name
     * @return array|false Embedding data
     */
    public function getEmbeddings($ids = [], $where = [], $collectionName = null)
    {
        $collection = $collectionName ?? $this->defaultCollection;
        
        $data = [];
        
        if (!empty($ids)) {
            $data['ids'] = $ids;
        }
        
        if (!empty($where)) {
            $data['where'] = $where;
        }
        
        return $this->request('collections/' . urlencode($collection) . '/get', 'POST', $data);
    }
    
    /**
     * Delete embeddings
     * 
     * @param array $ids IDs to delete
     * @param array $where Metadata filter
     * @param string|null $collectionName Collection name
     * @return bool Success
     */
    public function deleteEmbeddings($ids = [], $where = [], $collectionName = null)
    {
        $collection = $collectionName ?? $this->defaultCollection;
        
        $data = [];
        
        if (!empty($ids)) {
            $data['ids'] = $ids;
        }
        
        if (!empty($where)) {
            $data['where'] = $where;
        }
        
        if (empty($data)) {
            $this->logError('No criteria specified for deletion');
            return false;
        }
        
        $response = $this->request('collections/' . urlencode($collection) . '/delete', 'POST', $data);
        return ($response !== false);
    }
    
    /**
     * Count embeddings in collection
     * 
     * @param string|null $collectionName Collection name
     * @return int|false Embedding count
     */
    public function countEmbeddings($collectionName = null)
    {
        $collection = $collectionName ?? $this->defaultCollection;
        
        $response = $this->request('collections/' . urlencode($collection) . '/count', 'GET');
        
        if ($response && isset($response['count'])) {
            return $response['count'];
        }
        
        return false;
    }
    
    /**
     * Batch add embeddings
     * 
     * @param array $items Item list (each: embedding, document, metadata)
     * @param int $batchSize Batch size
     * @param string|null $collectionName Collection name
     * @return array Batch results
     */
    public function batchAddEmbeddings($items, $batchSize = 100, $collectionName = null)
    {
        $results = [
            'total' => count($items),
            'successful' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        $batches = array_chunk($items, $batchSize);
        
        foreach ($batches as $index => $batch) {
            $embeddings = [];
            $metadatas = [];
            $documents = [];
            $ids = [];
            
            foreach ($batch as $item) {
                $embeddings[] = $item['embedding'];
                $metadatas[] = $item['metadata'] ?? [];
                $documents[] = $item['document'] ?? '';
                $ids[] = $item['id'] ?? $this->generateId();
            }
            
            $success = $this->addEmbeddings($embeddings, $metadatas, $documents, $ids, $collectionName);
            
            if ($success) {
                $results['successful'] += count($batch);
            } else {
                $results['failed'] += count($batch);
                $results['errors'][] = "Batch " . ($index + 1) . " failed";
            }
        }
        
        return $results;
    }
    
    /**
     * Rename collection
     * 
     * @param string $newName New name
     * @param string|null $oldName Old name
     * @return bool Success
     */
    public function renameCollection($newName, $oldName = null)
    {
        $old = $oldName ?? $this->defaultCollection;
        
        // Get old collection
        $collection = $this->getCollection($old);
        
        if (!$collection) {
            $this->logError('Collection not found for rename', ['name' => $old]);
            return false;
        }
        
        // Create new collection
        $metadata = $collection['metadata'] ?? [];
        $new = $this->createCollection($newName, $metadata);
        
        if (!$new) {
            $this->logError('Failed to create new collection for rename');
            return false;
        }
        
        // Get all embeddings from old collection
        $embeddingsData = $this->getEmbeddings([], [], $old);
        
        if ($embeddingsData && isset($embeddingsData['ids']) && !empty($embeddingsData['ids'])) {
            $success = $this->addEmbeddings(
                $embeddingsData['embeddings'],
                $embeddingsData['metadatas'],
                $embeddingsData['documents'],
                $embeddingsData['ids'],
                $newName
            );
            
            if ($success) {
                $this->deleteCollection($old);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Query by metadata filter only
     * 
     * @param array $whereMetadata Metadata filter
     * @param int $nResults Number of results
     * @param string|null $collectionName Collection name
     * @return array|false Filtered results
     */
    public function queryByMetadata($whereMetadata, $nResults = 50, $collectionName = null)
    {
        // Use random embedding for metadata-only query
        $randomEmbedding = array_fill(0, 384, 0.1);
        
        return $this->queryEmbeddings([$randomEmbedding], $nResults, $whereMetadata, [], $collectionName);
    }
    
    /**
     * Get collection statistics
     * 
     * @param string|null $collectionName Collection name
     * @return array Statistics
     */
    public function getCollectionStats($collectionName = null)
    {
        $collection = $collectionName ?? $this->defaultCollection;
        
        $count = $this->countEmbeddings($collection);
        
        return [
            'name' => $collection,
            'count' => $count !== false ? $count : 0,
            'exists' => ($count !== false)
        ];
    }
    
    /**
     * Get all collections statistics
     * 
     * @return array Statistics list
     */
    public function getAllStats()
    {
        $collections = $this->listCollections();
        $stats = [];
        
        if ($collections) {
            foreach ($collections as $collection) {
                $stats[] = $this->getCollectionStats($collection['name']);
            }
        }
        
        return $stats;
    }
    
    /**
     * Clear collection (delete all embeddings)
     * 
     * @param string|null $collectionName Collection name
     * @return bool Success
     */
    public function clearCollection($collectionName = null)
    {
        $collection = $collectionName ?? $this->defaultCollection;
        
        // Get all IDs
        $embeddings = $this->getEmbeddings([], [], $collection);
        
        if ($embeddings && isset($embeddings['ids']) && !empty($embeddings['ids'])) {
            return $this->deleteEmbeddings($embeddings['ids'], [], $collection);
        }
        
        return true;
    }
    
    /**
     * Test connection
     * 
     * @return bool Connection successful
     */
    public function testConnection()
    {
        $response = $this->heartbeat();
        return ($response !== false);
    }
}