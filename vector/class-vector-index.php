<?php
/**
 * Vector Index Manager - Unified Interface for Vector Search
 * 
 * @package APIMaster
 * @subpackage Vector
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_VectorIndex {
    
    /**
     * @var APIMaster_VectorStore $vector_store
     */
    private $vector_store;
    
    /**
     * @var APIMaster_SimilaritySearch $similarity_search
     */
    private $similarity_search;
    
    /**
     * @var APIMaster_HNSWIndex $hnsw_index
     */
    private $hnsw_index;
    
    /**
     * @var array $config Configuration
     */
    private $config;
    
    /**
     * @var array $indices Available indices
     */
    private $indices = [];
    
    /**
     * @var string $active_index Current active index type
     */
    private $active_index = 'hnsw';
    
    /**
     * Constructor
     * 
     * @param array $config Optional configuration
     */
    public function __construct($config = []) {
        $this->loadConfig($config);
        $this->initializeComponents();
        $this->loadIndices();
    }
    
    /**
     * Load configuration
     * 
     * @param array $config
     */
    private function loadConfig($config = []) {
        $default_config = [
            'default_index' => 'hnsw',
            'indices' => [
                'hnsw' => [
                    'enabled' => true,
                    'max_levels' => 10,
                    'ef_construction' => 200,
                    'max_connections' => 16,
                    'ef_search' => 50
                ],
                'flat' => [
                    'enabled' => true,
                    'use_cache' => true
                ],
                'ivf' => [
                    'enabled' => false,
                    'nlist' => 100,
                    'nprobe' => 10
                ]
            ],
            'auto_index' => true,
            'sync_on_write' => true,
            'index_file' => dirname(dirname(__FILE__)) . '/data/vector-index.json',
            'statistics_enabled' => true
        ];
        
        $this->config = array_merge($default_config, $config);
        $this->active_index = $this->config['default_index'];
    }
    
    /**
     * Initialize components
     */
    private function initializeComponents() {
        $this->vector_store = new APIMaster_VectorStore();
        $this->similarity_search = new APIMaster_SimilaritySearch();
        
        // Initialize HNSW index
        $hnsw_config = $this->config['indices']['hnsw'];
        $hnsw_config['index_file'] = str_replace('.json', '-hnsw.json', $this->config['index_file']);
        $this->hnsw_index = new APIMaster_HNSWIndex($hnsw_config);
    }
    
    /**
     * Load all indices
     */
    private function loadIndices() {
        $this->indices = [
            'hnsw' => [
                'name' => 'HNSW Index',
                'description' => 'Hierarchical Navigable Small World - Fast approximate search',
                'enabled' => $this->config['indices']['hnsw']['enabled'],
                'instance' => $this->hnsw_index
            ],
            'flat' => [
                'name' => 'Flat Index',
                'description' => 'Brute force exact search',
                'enabled' => $this->config['indices']['flat']['enabled'],
                'instance' => null
            ]
        ];
    }
    
    /**
     * Index a vector
     * 
     * @param string $id
     * @param array $vector
     * @param array $metadata
     * @return bool
     */
    public function indexVector($id, $vector, $metadata = []) {
        // Store in vector store
        $stored = $this->vector_store->add($id, $vector, $metadata);
        
        if (!$stored) {
            return false;
        }
        
        // Index in HNSW if enabled
        if ($this->config['indices']['hnsw']['enabled']) {
            $this->hnsw_index->addElement($id, $vector, $metadata);
        }
        
        return true;
    }
    
    /**
     * Search for similar vectors
     * 
     * @param array $query_vector
     * @param int $k Number of results
     * @param array $options Search options
     * @return array
     */
    public function search($query_vector, $k = 10, $options = []) {
        $index_type = $options['index'] ?? $this->active_index;
        $metric = $options['metric'] ?? 'cosine';
        $filters = $options['filters'] ?? [];
        
        // Apply filters if any
        if (!empty($filters)) {
            return $this->searchWithFilters($query_vector, $filters, $k, $options);
        }
        
        switch ($index_type) {
            case 'hnsw':
                if (!$this->config['indices']['hnsw']['enabled']) {
                    return $this->flatSearch($query_vector, $k, $metric);
                }
                $results = $this->hnswIndexSearch($query_vector, $k, $options);
                break;
                
            case 'flat':
            default:
                $results = $this->flatSearch($query_vector, $k, $metric);
                break;
        }
        
        // Enrich results with metadata
        return $this->enrichResults($results);
    }
    
    /**
     * Search using HNSW index
     * 
     * @param array $query_vector
     * @param int $k
     * @param array $options
     * @return array
     */
    private function hnswIndexSearch($query_vector, $k, $options = []) {
        $ef_search = $options['ef_search'] ?? $this->config['indices']['hnsw']['ef_search'];
        
        if ($this->hnsw_index->isEmpty()) {
            // Fallback to flat search if index is empty
            return $this->flatSearch($query_vector, $k, $options['metric'] ?? 'cosine');
        }
        
        return $this->hnsw_index->search($query_vector, $k, $ef_search);
    }
    
    /**
     * Flat (brute force) search
     * 
     * @param array $query_vector
     * @param int $k
     * @param string $metric
     * @return array
     */
    private function flatSearch($query_vector, $k, $metric = 'cosine') {
        $all_vectors = $this->vector_store->getAll();
        
        if (empty($all_vectors)) {
            return [];
        }
        
        $results = [];
        foreach ($all_vectors as $id => $data) {
            $similarity = $this->similarity_search->calculateSimilarity(
                $query_vector,
                $data['vector'],
                $metric
            );
            
            $results[] = [
                'id' => $id,
                'similarity' => $similarity,
                'metric' => $metric,
                'metadata' => $data['metadata'] ?? []
            ];
        }
        
        // Sort by similarity (descending)
        usort($results, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        return array_slice($results, 0, $k);
    }
    
    /**
     * Search with metadata filters
     * 
     * @param array $query_vector
     * @param array $filters
     * @param int $k
     * @param array $options
     * @return array
     */
    private function searchWithFilters($query_vector, $filters, $k, $options = []) {
        // Get filtered vectors
        $filtered_vectors = $this->vector_store->getByMetadata($filters);
        
        if (empty($filtered_vectors)) {
            return [];
        }
        
        $metric = $options['metric'] ?? 'cosine';
        $results = [];
        
        foreach ($filtered_vectors as $id => $data) {
            $similarity = $this->similarity_search->calculateSimilarity(
                $query_vector,
                $data['vector'],
                $metric
            );
            
            $results[] = [
                'id' => $id,
                'similarity' => $similarity,
                'metric' => $metric,
                'metadata' => $data['metadata'] ?? []
            ];
        }
        
        // Sort and limit
        usort($results, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        return array_slice($results, 0, $k);
    }
    
    /**
     * Enrich results with additional data
     * 
     * @param array $results
     * @return array
     */
    private function enrichResults($results) {
        foreach ($results as &$result) {
            // Add score percentile
            $result['score_percentile'] = $result['similarity'] * 100;
            
            // Add confidence level
            if ($result['similarity'] >= 0.9) {
                $result['confidence'] = 'very_high';
            } elseif ($result['similarity'] >= 0.7) {
                $result['confidence'] = 'high';
            } elseif ($result['similarity'] >= 0.5) {
                $result['confidence'] = 'medium';
            } elseif ($result['similarity'] >= 0.3) {
                $result['confidence'] = 'low';
            } else {
                $result['confidence'] = 'very_low';
            }
            
            // Add timestamp if not exists
            if (!isset($result['timestamp'])) {
                $result['timestamp'] = time();
            }
        }
        
        return $results;
    }
    
    /**
     * Search by text query
     * 
     * @param string $query_text
     * @param int $k
     * @param array $options
     * @return array
     */
    public function searchByText($query_text, $k = 10, $options = []) {
        $embedding_generator = new APIMaster_EmbeddingGenerator();
        $query_vector = $embedding_generator->generate($query_text);
        
        if (!$query_vector) {
            return ['error' => 'Failed to generate embedding for query text'];
        }
        
        return $this->search($query_vector, $k, $options);
    }
    
    /**
     * Batch search
     * 
     * @param array $queries Array of query vectors or texts
     * @param int $k
     * @param array $options
     * @return array
     */
    public function batchSearch($queries, $k = 10, $options = []) {
        $results = [];
        $embedding_generator = new APIMaster_EmbeddingGenerator();
        
        foreach ($queries as $index => $query) {
            if (is_string($query)) {
                // Text query
                $vector = $embedding_generator->generate($query);
                if ($vector) {
                    $results[$index] = $this->search($vector, $k, $options);
                } else {
                    $results[$index] = ['error' => 'Failed to generate embedding'];
                }
            } elseif (is_array($query) && isset($query['vector'])) {
                // Vector query
                $results[$index] = $this->search($query['vector'], $k, $options);
            } else {
                $results[$index] = ['error' => 'Invalid query format'];
            }
        }
        
        return $results;
    }
    
    /**
     * Update vector index
     * 
     * @param string $id
     * @param array $new_vector
     * @param array $metadata
     * @return bool
     */
    public function updateVector($id, $new_vector, $metadata = null) {
        // Get existing vector
        $existing = $this->vector_store->get($id);
        
        if (!$existing) {
            return $this->indexVector($id, $new_vector, $metadata ?? []);
        }
        
        // Update vector store
        $updated = $this->vector_store->update($id, $new_vector, $metadata);
        
        if (!$updated) {
            return false;
        }
        
        // Update HNSW index
        if ($this->config['indices']['hnsw']['enabled']) {
            $this->hnsw_index->updateElement($id, $new_vector, $metadata ?? $existing['metadata']);
        }
        
        return true;
    }
    
    /**
     * Delete vector from index
     * 
     * @param string $id
     * @return bool
     */
    public function deleteVector($id) {
        // Remove from vector store
        $deleted = $this->vector_store->remove($id);
        
        if (!$deleted) {
            return false;
        }
        
        // Remove from HNSW index
        if ($this->config['indices']['hnsw']['enabled']) {
            $this->hnsw_index->removeElement($id);
        }
        
        return true;
    }
    
    /**
     * Rebuild entire index
     * 
     * @return array
     */
    public function rebuildIndex() {
        $start_time = microtime(true);
        
        // Clear existing indices
        $this->hnsw_index->clear();
        
        // Get all vectors
        $all_vectors = $this->vector_store->getAll();
        
        if (empty($all_vectors)) {
            return [
                'success' => true,
                'vectors_indexed' => 0,
                'time_taken' => microtime(true) - $start_time
            ];
        }
        
        // Bulk add to HNSW
        $elements = [];
        foreach ($all_vectors as $id => $data) {
            $elements[] = [$id, $data['vector'], $data['metadata'] ?? []];
        }
        
        $result = $this->hnsw_index->bulkAdd($elements);
        
        return [
            'success' => true,
            'vectors_indexed' => $result['success'],
            'failed' => $result['failed'],
            'time_taken' => microtime(true) - $start_time,
            'index_type' => $this->active_index
        ];
    }
    
    /**
     * Optimize index
     * 
     * @return array
     */
    public function optimizeIndex() {
        $stats_before = $this->getStats();
        $start_time = microtime(true);
        
        // Rebuild index for optimization
        $result = $this->rebuildIndex();
        
        $stats_after = $this->getStats();
        
        return [
            'success' => $result['success'],
            'time_taken' => microtime(true) - $start_time,
            'stats_before' => $stats_before,
            'stats_after' => $stats_after,
            'improvement' => $stats_after['avg_connections'] - $stats_before['avg_connections']
        ];
    }
    
    /**
     * Get index statistics
     * 
     * @return array
     */
    public function getStats() {
        $vector_stats = $this->vector_store->getStats();
        $hnsw_stats = $this->hnsw_index->getStats();
        
        $stats = [
            'total_vectors' => $vector_stats['total_vectors'],
            'active_index' => $this->active_index,
            'indices' => [
                'hnsw' => [
                    'enabled' => $this->config['indices']['hnsw']['enabled'],
                    'elements' => $hnsw_stats['total_elements'],
                    'max_level' => $hnsw_stats['max_level'],
                    'avg_connections' => $hnsw_stats['avg_connections']
                ],
                'flat' => [
                    'enabled' => $this->config['indices']['flat']['enabled']
                ]
            ],
            'performance' => [
                'search_complexity' => $this->active_index === 'hnsw' ? 'O(log N)' : 'O(N)',
                'index_size' => $this->calculateIndexSize()
            ],
            'config' => $this->config
        ];
        
        if ($this->config['statistics_enabled']) {
            $stats['cache_hit_rate'] = $this->similarity_search->getStats()['cached_searches'] ?? 0;
        }
        
        return $stats;
    }
    
    /**
     * Calculate index size in memory
     * 
     * @return int Size in bytes
     */
    private function calculateIndexSize() {
        $size = 0;
        
        // Vector store size
        $vector_data = $this->vector_store->getAll();
        $size += strlen(json_encode($vector_data));
        
        // HNSW index size if file exists
        if ($this->config['index_file'] && file_exists($this->config['index_file'])) {
            $size += filesize($this->config['index_file']);
        }
        
        return $size;
    }
    
    /**
     * Switch active index type
     * 
     * @param string $index_type
     * @return bool
     */
    public function switchIndex($index_type) {
        if (!isset($this->indices[$index_type]) || !$this->indices[$index_type]['enabled']) {
            return false;
        }
        
        $this->active_index = $index_type;
        $this->config['default_index'] = $index_type;
        
        return true;
    }
    
    /**
     * Export index to file
     * 
     * @param string $file_path
     * @return bool
     */
    public function exportIndex($file_path = null) {
        $file_path = $file_path ?: $this->config['index_file'];
        
        $export_data = [
            'version' => '1.0',
            'exported_at' => time(),
            'config' => $this->config,
            'vectors' => $this->vector_store->getAll(),
            'hnsw_index' => $this->hnsw_index->getStats()
        ];
        
        $dir = dirname($file_path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return file_put_contents($file_path, json_encode($export_data, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Import index from file
     * 
     * @param string $file_path
     * @return bool
     */
    public function importIndex($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($file_path), true);
        
        if (!$data || !isset($data['vectors'])) {
            return false;
        }
        
        // Clear existing index
        $this->clear();
        
        // Import vectors
        foreach ($data['vectors'] as $id => $vector_data) {
            $this->indexVector($id, $vector_data['vector'], $vector_data['metadata'] ?? []);
        }
        
        return true;
    }
    
    /**
     * Clear entire index
     */
    public function clear() {
        $this->vector_store->clear();
        $this->hnsw_index->clear();
    }
    
    /**
     * Get vector by ID
     * 
     * @param string $id
     * @return array|null
     */
    public function getVector($id) {
        return $this->vector_store->get($id);
    }
    
    /**
     * Check if vector exists
     * 
     * @param string $id
     * @return bool
     */
    public function exists($id) {
        return $this->vector_store->exists($id);
    }
    
    /**
     * Get all vector IDs
     * 
     * @return array
     */
    public function getAllIds() {
        return $this->vector_store->getAllIds();
    }
    
    /**
     * Get index health status
     * 
     * @return array
     */
    public function healthCheck() {
        $stats = $this->getStats();
        
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'warnings' => []
        ];
        
        // Check if index is empty
        if ($stats['total_vectors'] === 0) {
            $health['warnings'][] = 'Index is empty';
        }
        
        // Check HNSW index consistency
        if ($stats['indices']['hnsw']['enabled'] && 
            $stats['total_vectors'] !== $stats['indices']['hnsw']['elements']) {
            $health['issues'][] = 'HNSW index inconsistency detected';
            $health['status'] = 'degraded';
        }
        
        // Check index size
        if ($stats['performance']['index_size'] > 100 * 1024 * 1024) { // 100MB
            $health['warnings'][] = 'Index size exceeds 100MB, consider optimization';
        }
        
        return $health;
    }
}