<?php
/**
 * Similarity Search Engine for Vector Database
 * 
 * @package APIMaster
 * @subpackage Vector
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_SimilaritySearch {
    
    /**
     * @var APIMaster_VectorStore $vector_store
     */
    private $vector_store;
    
    /**
     * @var APIMaster_EmbeddingGenerator $embedding_generator
     */
    private $embedding_generator;
    
    /**
     * @var array $config
     */
    private $config;
    
    /**
     * Constructor
     * 
     * @param APIMaster_VectorStore|null $vector_store
     * @param APIMaster_EmbeddingGenerator|null $embedding_generator
     */
    public function __construct($vector_store = null, $embedding_generator = null) {
        $this->vector_store = $vector_store ?: new APIMaster_VectorStore();
        $this->embedding_generator = $embedding_generator ?: new APIMaster_EmbeddingGenerator();
        $this->loadConfig();
    }
    
    /**
     * Load configuration
     */
    private function loadConfig() {
        $config_file = dirname(dirname(__FILE__)) . '/config/similarity-config.json';
        
        if (file_exists($config_file)) {
            $this->config = json_decode(file_get_contents($config_file), true);
        } else {
            // Default configuration
            $this->config = [
                'default_metric' => 'cosine',
                'default_limit' => 10,
                'min_similarity' => 0.3,
                'max_results' => 100,
                'metrics' => [
                    'cosine' => [
                        'enabled' => true,
                        'threshold' => 0.7
                    ],
                    'euclidean' => [
                        'enabled' => true,
                        'threshold' => 1.5
                    ],
                    'dot_product' => [
                        'enabled' => true,
                        'threshold' => 0.5
                    ]
                ],
                'caching' => [
                    'enabled' => true,
                    'ttl' => 3600,
                    'max_cache_size' => 1000
                ]
            ];
        }
    }
    
    /**
     * Find similar vectors by query text
     * 
     * @param string $query_text
     * @param array $options
     * @return array
     */
    public function findByText($query_text, $options = []) {
        // Generate embedding for query text
        $query_vector = $this->embedding_generator->generate($query_text);
        
        if (!$query_vector || empty($query_vector)) {
            return ['error' => 'Failed to generate embedding for query text'];
        }
        
        return $this->findByVector($query_vector, $options);
    }
    
    /**
     * Find similar vectors by vector
     * 
     * @param array $query_vector
     * @param array $options
     * @return array
     */
    public function findByVector($query_vector, $options = []) {
        $metric = $options['metric'] ?? $this->config['default_metric'];
        $limit = min(
            $options['limit'] ?? $this->config['default_limit'],
            $this->config['max_results']
        );
        $min_similarity = $options['min_similarity'] ?? $this->config['min_similarity'];
        
        // Check cache
        $cache_key = $this->getCacheKey($query_vector, $metric, $limit);
        if ($this->config['caching']['enabled'] && $this->isCached($cache_key)) {
            return $this->getCached($cache_key);
        }
        
        // Get all vectors from store
        $all_vectors = $this->vector_store->getAll();
        
        if (empty($all_vectors)) {
            return [];
        }
        
        // Calculate similarities
        $results = [];
        foreach ($all_vectors as $id => $vector_data) {
            $similarity = $this->calculateSimilarity($query_vector, $vector_data['vector'], $metric);
            
            if ($similarity >= $min_similarity) {
                $results[] = [
                    'id' => $id,
                    'similarity' => $similarity,
                    'metric' => $metric,
                    'metadata' => $vector_data['metadata'] ?? [],
                    'text' => $vector_data['text'] ?? '',
                    'created_at' => $vector_data['created_at'] ?? null
                ];
            }
        }
        
        // Sort by similarity (descending)
        usort($results, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        // Limit results
        $results = array_slice($results, 0, $limit);
        
        // Cache results
        if ($this->config['caching']['enabled']) {
            $this->cacheResults($cache_key, $results);
        }
        
        return $results;
    }
    
    /**
     * Find similar vectors with filters
     * 
     * @param array $query_vector
     * @param array $filters
     * @param array $options
     * @return array
     */
    public function findByVectorWithFilters($query_vector, $filters = [], $options = []) {
        $metric = $options['metric'] ?? $this->config['default_metric'];
        $limit = min(
            $options['limit'] ?? $this->config['default_limit'],
            $this->config['max_results']
        );
        $min_similarity = $options['min_similarity'] ?? $this->config['min_similarity'];
        
        // Get filtered vectors from store
        $filtered_vectors = $this->vector_store->getByMetadata($filters);
        
        if (empty($filtered_vectors)) {
            return [];
        }
        
        // Calculate similarities
        $results = [];
        foreach ($filtered_vectors as $id => $vector_data) {
            $similarity = $this->calculateSimilarity($query_vector, $vector_data['vector'], $metric);
            
            if ($similarity >= $min_similarity) {
                $results[] = [
                    'id' => $id,
                    'similarity' => $similarity,
                    'metric' => $metric,
                    'metadata' => $vector_data['metadata'] ?? [],
                    'text' => $vector_data['text'] ?? '',
                    'created_at' => $vector_data['created_at'] ?? null
                ];
            }
        }
        
        // Sort by similarity
        usort($results, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        return array_slice($results, 0, $limit);
    }
    
    /**
     * Calculate similarity between two vectors
     * 
     * @param array $vector_a
     * @param array $vector_b
     * @param string $metric
     * @return float
     */
    public function calculateSimilarity($vector_a, $vector_b, $metric = 'cosine') {
        switch ($metric) {
            case 'cosine':
                return $this->cosineSimilarity($vector_a, $vector_b);
            case 'euclidean':
                return $this->euclideanSimilarity($vector_a, $vector_b);
            case 'dot_product':
                return $this->dotProductSimilarity($vector_a, $vector_b);
            default:
                return $this->cosineSimilarity($vector_a, $vector_b);
        }
    }
    
    /**
     * Cosine similarity
     * 
     * @param array $a
     * @param array $b
     * @return float
     */
    private function cosineSimilarity($a, $b) {
        $dot_product = 0;
        $norm_a = 0;
        $norm_b = 0;
        
        foreach ($a as $i => $value_a) {
            if (isset($b[$i])) {
                $dot_product += $value_a * $b[$i];
            }
            $norm_a += $value_a * $value_a;
        }
        
        foreach ($b as $value_b) {
            $norm_b += $value_b * $value_b;
        }
        
        if ($norm_a == 0 || $norm_b == 0) {
            return 0;
        }
        
        return $dot_product / (sqrt($norm_a) * sqrt($norm_b));
    }
    
    /**
     * Euclidean similarity (converted to 0-1 range)
     * 
     * @param array $a
     * @param array $b
     * @return float
     */
    private function euclideanSimilarity($a, $b) {
        $distance = 0;
        
        foreach ($a as $i => $value_a) {
            if (isset($b[$i])) {
                $diff = $value_a - $b[$i];
                $distance += $diff * $diff;
            } else {
                $distance += $value_a * $value_a;
            }
        }
        
        foreach ($b as $i => $value_b) {
            if (!isset($a[$i])) {
                $distance += $value_b * $value_b;
            }
        }
        
        $distance = sqrt($distance);
        
        // Convert distance to similarity (1 / (1 + distance))
        return 1 / (1 + $distance);
    }
    
    /**
     * Dot product similarity (normalized)
     * 
     * @param array $a
     * @param array $b
     * @return float
     */
    private function dotProductSimilarity($a, $b) {
        $dot_product = 0;
        
        foreach ($a as $i => $value_a) {
            if (isset($b[$i])) {
                $dot_product += $value_a * $b[$i];
            }
        }
        
        // Normalize to 0-1 range using sigmoid
        return 1 / (1 + exp(-$dot_product / 100));
    }
    
    /**
     * Batch similarity search
     * 
     * @param array $query_vectors
     * @param array $options
     * @return array
     */
    public function batchSearch($query_vectors, $options = []) {
        $results = [];
        
        foreach ($query_vectors as $index => $query_vector) {
            $results[$index] = $this->findByVector($query_vector, $options);
        }
        
        return $results;
    }
    
    /**
     * Find most similar to multiple queries (ensemble)
     * 
     * @param array $query_texts
     * @param array $options
     * @return array
     */
    public function ensembleSearch($query_texts, $options = []) {
        $all_vectors = $this->vector_store->getAll();
        
        if (empty($all_vectors)) {
            return [];
        }
        
        // Generate vectors for all queries
        $query_vectors = [];
        foreach ($query_texts as $text) {
            $vector = $this->embedding_generator->generate($text);
            if ($vector) {
                $query_vectors[] = $vector;
            }
        }
        
        if (empty($query_vectors)) {
            return [];
        }
        
        // Calculate average similarity for each stored vector
        $results = [];
        foreach ($all_vectors as $id => $vector_data) {
            $total_similarity = 0;
            $count = 0;
            
            foreach ($query_vectors as $query_vector) {
                $similarity = $this->calculateSimilarity($query_vector, $vector_data['vector'], $options['metric'] ?? 'cosine');
                $total_similarity += $similarity;
                $count++;
            }
            
            $avg_similarity = $total_similarity / $count;
            
            if ($avg_similarity >= ($options['min_similarity'] ?? $this->config['min_similarity'])) {
                $results[] = [
                    'id' => $id,
                    'similarity' => $avg_similarity,
                    'metadata' => $vector_data['metadata'] ?? [],
                    'text' => $vector_data['text'] ?? ''
                ];
            }
        }
        
        // Sort by similarity
        usort($results, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        $limit = min($options['limit'] ?? $this->config['default_limit'], $this->config['max_results']);
        return array_slice($results, 0, $limit);
    }
    
    /**
     * Get cache key
     * 
     * @param array $vector
     * @param string $metric
     * @param int $limit
     * @return string
     */
    private function getCacheKey($vector, $metric, $limit) {
        $vector_hash = md5(json_encode($vector));
        return "sim_search_{$vector_hash}_{$metric}_{$limit}";
    }
    
    /**
     * Check if results are cached
     * 
     * @param string $key
     * @return bool
     */
    private function isCached($key) {
        $cache_file = $this->getCacheFilePath($key);
        
        if (!file_exists($cache_file)) {
            return false;
        }
        
        $cache_data = json_decode(file_get_contents($cache_file), true);
        if (!$cache_data || !isset($cache_data['timestamp'])) {
            return false;
        }
        
        return (time() - $cache_data['timestamp']) < $this->config['caching']['ttl'];
    }
    
    /**
     * Get cached results
     * 
     * @param string $key
     * @return array
     */
    private function getCached($key) {
        $cache_file = $this->getCacheFilePath($key);
        $cache_data = json_decode(file_get_contents($cache_file), true);
        return $cache_data['results'] ?? [];
    }
    
    /**
     * Cache results
     * 
     * @param string $key
     * @param array $results
     */
    private function cacheResults($key, $results) {
        $cache_dir = dirname(dirname(__FILE__)) . '/cache/similarity/';
        
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
        
        $cache_file = $cache_dir . $key . '.json';
        
        file_put_contents($cache_file, json_encode([
            'timestamp' => time(),
            'results' => $results
        ]));
        
        // Clean old cache files
        $this->cleanCache();
    }
    
    /**
     * Clean old cache files
     */
    private function cleanCache() {
        $cache_dir = dirname(dirname(__FILE__)) . '/cache/similarity/';
        
        if (!is_dir($cache_dir)) {
            return;
        }
        
        $files = glob($cache_dir . '*.json');
        $now = time();
        $ttl = $this->config['caching']['ttl'];
        $max_files = $this->config['caching']['max_cache_size'];
        
        // Delete expired files
        foreach ($files as $file) {
            if (filemtime($file) < ($now - $ttl)) {
                unlink($file);
            }
        }
        
        // Limit number of cache files
        $files = glob($cache_dir . '*.json');
        if (count($files) > $max_files) {
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            $to_delete = array_slice($files, 0, count($files) - $max_files);
            foreach ($to_delete as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Clear all similarity cache
     */
    public function clearCache() {
        $cache_dir = dirname(dirname(__FILE__)) . '/cache/similarity/';
        
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '*.json');
            foreach ($files as $file) {
                unlink($file);
            }
        }
        
        return true;
    }
    
    /**
     * Get similarity statistics
     * 
     * @return array
     */
    public function getStats() {
        $all_vectors = $this->vector_store->getAll();
        $total_vectors = count($all_vectors);
        
        $cache_dir = dirname(dirname(__FILE__)) . '/cache/similarity/';
        $cached_searches = is_dir($cache_dir) ? count(glob($cache_dir . '*.json')) : 0;
        
        return [
            'total_vectors' => $total_vectors,
            'cached_searches' => $cached_searches,
            'default_metric' => $this->config['default_metric'],
            'min_similarity' => $this->config['min_similarity'],
            'max_results' => $this->config['max_results'],
            'caching_enabled' => $this->config['caching']['enabled'],
            'cache_ttl' => $this->config['caching']['ttl'],
            'available_metrics' => array_keys($this->config['metrics'])
        ];
    }
    
    /**
     * Validate and normalize vector
     * 
     * @param array $vector
     * @return array|false
     */
    public function normalizeVector($vector) {
        if (empty($vector)) {
            return false;
        }
        
        // Calculate norm
        $norm = 0;
        foreach ($vector as $value) {
            $norm += $value * $value;
        }
        $norm = sqrt($norm);
        
        if ($norm == 0) {
            return false;
        }
        
        // Normalize
        $normalized = [];
        foreach ($vector as $value) {
            $normalized[] = $value / $norm;
        }
        
        return $normalized;
    }
}