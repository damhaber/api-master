<?php
/**
 * HNSW (Hierarchical Navigable Small World) Index for Fast Similarity Search
 * 
 * @package APIMaster
 * @subpackage Vector
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_HNSWIndex {
    
    /**
     * @var array $index HNSW index structure
     */
    private $index;
    
    /**
     * @var int $max_levels Maximum number of levels
     */
    private $max_levels;
    
    /**
     * @var int $ef_construction Construction parameter
     */
    private $ef_construction;
    
    /**
     * @var int $max_connections Maximum connections per element
     */
    private $max_connections;
    
    /**
     * @var float $level_multiplier Level multiplier (1/ln(M))
     */
    private $level_multiplier;
    
    /**
     * @var array $config Configuration
     */
    private $config;
    
    /**
     * @var APIMaster_SimilaritySearch $similarity_search
     */
    private $similarity_search;
    
    /**
     * Constructor
     * 
     * @param array $config Optional configuration
     */
    public function __construct($config = []) {
        $this->loadConfig($config);
        $this->similarity_search = new APIMaster_SimilaritySearch();
        $this->initializeIndex();
    }
    
    /**
     * Load configuration
     * 
     * @param array $config
     */
    private function loadConfig($config = []) {
        $default_config = [
            'max_levels' => 10,
            'ef_construction' => 200,
            'max_connections' => 16,
            'level_multiplier' => 1 / log(16),
            'ef_search' => 50,
            'index_file' => null,
            'auto_save' => true,
            'save_interval' => 100 // Save every N operations
        ];
        
        $this->config = array_merge($default_config, $config);
        $this->max_levels = $this->config['max_levels'];
        $this->ef_construction = $this->config['ef_construction'];
        $this->max_connections = $this->config['max_connections'];
        $this->level_multiplier = $this->config['level_multiplier'];
    }
    
    /**
     * Initialize index structure
     */
    private function initializeIndex() {
        $this->index = [
            'elements' => [],        // Element storage: id => [vector, metadata, level]
            'graphs' => [],          // Graph connections per level: level => [from_id => [to_ids]]
            'entry_point' => null,   // Entry point ID
            'max_level' => 0,        // Current max level
            'operation_count' => 0,  // Operation counter for auto-save
            'metadata' => [
                'created_at' => time(),
                'updated_at' => time(),
                'total_elements' => 0
            ]
        ];
        
        // Load from file if exists
        if ($this->config['index_file'] && file_exists($this->config['index_file'])) {
            $this->loadFromFile();
        }
    }
    
    /**
     * Add element to index
     * 
     * @param string $id
     * @param array $vector
     * @param array $metadata
     * @return bool
     */
    public function addElement($id, $vector, $metadata = []) {
        if (isset($this->index['elements'][$id])) {
            return $this->updateElement($id, $vector, $metadata);
        }
        
        // Normalize vector
        $normalized_vector = $this->similarity_search->normalizeVector($vector);
        if (!$normalized_vector) {
            return false;
        }
        
        // Assign random level
        $level = $this->randomLevel();
        
        // Store element
        $this->index['elements'][$id] = [
            'vector' => $normalized_vector,
            'metadata' => $metadata,
            'level' => $level,
            'created_at' => time()
        ];
        
        // Initialize connections for this element
        for ($l = 0; $l <= $level; $l++) {
            if (!isset($this->index['graphs'][$l])) {
                $this->index['graphs'][$l] = [];
            }
            $this->index['graphs'][$l][$id] = [];
        }
        
        // Find neighbors and connect
        if ($this->index['entry_point'] !== null) {
            $this->connectElement($id, $level);
        } else {
            // First element becomes entry point
            $this->index['entry_point'] = $id;
            $this->index['max_level'] = $level;
        }
        
        $this->index['metadata']['total_elements']++;
        $this->index['metadata']['updated_at'] = time();
        $this->index['operation_count']++;
        
        // Auto-save if needed
        if ($this->config['auto_save'] && 
            $this->index['operation_count'] >= $this->config['save_interval']) {
            $this->saveToFile();
        }
        
        return true;
    }
    
    /**
     * Update existing element
     * 
     * @param string $id
     * @param array $vector
     * @param array $metadata
     * @return bool
     */
    public function updateElement($id, $vector, $metadata = []) {
        if (!isset($this->index['elements'][$id])) {
            return false;
        }
        
        // Remove old connections
        $old_level = $this->index['elements'][$id]['level'];
        for ($l = 0; $l <= $old_level; $l++) {
            if (isset($this->index['graphs'][$l][$id])) {
                // Remove this element from neighbors' connection lists
                foreach ($this->index['graphs'][$l][$id] as $neighbor_id) {
                    $key = array_search($id, $this->index['graphs'][$l][$neighbor_id]);
                    if ($key !== false) {
                        array_splice($this->index['graphs'][$l][$neighbor_id], $key, 1);
                    }
                }
                unset($this->index['graphs'][$l][$id]);
            }
        }
        
        // Add with new vector
        return $this->addElement($id, $vector, $metadata);
    }
    
    /**
     * Connect element to graph
     * 
     * @param string $id
     * @param int $level
     */
    private function connectElement($id, $level) {
        $current_entry = $this->index['entry_point'];
        $current_max_level = $this->index['max_level'];
        
        // Navigate from top level down to level+1
        for ($l = $current_max_level; $l > $level; $l--) {
            $current_entry = $this->searchLayer(
                $this->index['elements'][$id]['vector'],
                $current_entry,
                1,
                $l
            )[0] ?? $current_entry;
        }
        
        // For each level from min(level, current_max_level) down to 0
        for ($l = min($level, $current_max_level); $l >= 0; $l--) {
            // Find nearest neighbors
            $neighbors = $this->searchLayer(
                $this->index['elements'][$id]['vector'],
                $current_entry,
                $this->ef_construction,
                $l
            );
            
            // Select M nearest neighbors
            $m_neighbors = array_slice($neighbors, 0, $this->max_connections);
            
            // Add connections
            $this->index['graphs'][$l][$id] = $m_neighbors;
            
            // Add reverse connections
            foreach ($m_neighbors as $neighbor_id) {
                $this->index['graphs'][$l][$neighbor_id][] = $id;
                
                // Trim neighbor's connections if exceed max
                if (count($this->index['graphs'][$l][$neighbor_id]) > $this->max_connections) {
                    // Keep only nearest connections
                    $this->trimConnections($neighbor_id, $l);
                }
            }
            
            // Set current entry for next level
            $current_entry = $m_neighbors[0] ?? $current_entry;
        }
        
        // Update max level if needed
        if ($level > $this->index['max_level']) {
            $this->index['max_level'] = $level;
            $this->index['entry_point'] = $id;
        }
    }
    
    /**
     * Search within a specific layer
     * 
     * @param array $query_vector
     * @param string $entry_point
     * @param int $ef
     * @param int $level
     * @return array
     */
    private function searchLayer($query_vector, $entry_point, $ef, $level) {
        $visited = new SplPriorityQueue();
        $candidates = new SplPriorityQueue();
        $results = new SplPriorityQueue();
        
        // Set up priority queues (higher similarity = higher priority)
        $visited->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $candidates->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $results->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        
        $entry_dist = $this->similarity_search->calculateSimilarity(
            $query_vector,
            $this->index['elements'][$entry_point]['vector']
        );
        
        $visited->insert($entry_point, -$entry_dist);
        $candidates->insert($entry_point, -$entry_dist);
        $results->insert($entry_point, -$entry_dist);
        
        while (!$candidates->isEmpty()) {
            $current = $candidates->extract();
            $current_data = $current['data'];
            $current_dist = -$current['priority'];
            
            // Get the farthest result distance
            $farthest_result = $results->top();
            $farthest_dist = -$farthest_result['priority'];
            
            if ($current_dist < $farthest_dist) {
                break;
            }
            
            // Explore neighbors
            if (isset($this->index['graphs'][$level][$current_data])) {
                foreach ($this->index['graphs'][$level][$current_data] as $neighbor_id) {
                    if (!$visited->valid() || !$this->isVisited($visited, $neighbor_id)) {
                        $visited->insert($neighbor_id, 0);
                        
                        $neighbor_dist = $this->similarity_search->calculateSimilarity(
                            $query_vector,
                            $this->index['elements'][$neighbor_id]['vector']
                        );
                        
                        if ($neighbor_dist > $farthest_dist || $results->count() < $ef) {
                            $candidates->insert($neighbor_id, -$neighbor_dist);
                            $results->insert($neighbor_id, -$neighbor_dist);
                            
                            // Trim results if exceed ef
                            if ($results->count() > $ef) {
                                $results->extract();
                            }
                        }
                    }
                }
            }
        }
        
        // Extract results
        $final_results = [];
        while (!$results->isEmpty()) {
            $result = $results->extract();
            $final_results[] = $result['data'];
        }
        
        return array_reverse($final_results);
    }
    
    /**
     * Check if node is visited
     * 
     * @param SplPriorityQueue $visited
     * @param string $id
     * @return bool
     */
    private function isVisited($visited, $id) {
        $temp = clone $visited;
        while ($temp->valid()) {
            $item = $temp->current();
            if ($item['data'] === $id) {
                return true;
            }
            $temp->next();
        }
        return false;
    }
    
    /**
     * Trim connections to keep only nearest
     * 
     * @param string $id
     * @param int $level
     */
    private function trimConnections($id, $level) {
        $connections = $this->index['graphs'][$level][$id];
        $vector = $this->index['elements'][$id]['vector'];
        
        // Calculate distances to all connections
        $distances = [];
        foreach ($connections as $neighbor_id) {
            $distances[$neighbor_id] = $this->similarity_search->calculateSimilarity(
                $vector,
                $this->index['elements'][$neighbor_id]['vector']
            );
        }
        
        // Sort by distance (keep nearest)
        arsort($distances);
        $keep = array_slice(array_keys($distances), 0, $this->max_connections);
        
        $this->index['graphs'][$level][$id] = $keep;
    }
    
    /**
     * Search for nearest neighbors
     * 
     * @param array $query_vector
     * @param int $k Number of results
     * @param int $ef_search Search parameter
     * @return array
     */
    public function search($query_vector, $k = 10, $ef_search = null) {
        if ($this->index['entry_point'] === null) {
            return [];
        }
        
        $ef_search = $ef_search ?? $this->config['ef_search'];
        
        // Normalize query vector
        $normalized_query = $this->similarity_search->normalizeVector($query_vector);
        if (!$normalized_query) {
            return [];
        }
        
        $current_entry = $this->index['entry_point'];
        
        // Navigate from top level down to level 1
        for ($l = $this->index['max_level']; $l > 0; $l--) {
            $current_entry = $this->searchLayer(
                $normalized_query,
                $current_entry,
                1,
                $l
            )[0] ?? $current_entry;
        }
        
        // Search at level 0
        $neighbors = $this->searchLayer(
            $normalized_query,
            $current_entry,
            $ef_search,
            0
        );
        
        // Get top k results
        $results = [];
        for ($i = 0; $i < min($k, count($neighbors)); $i++) {
            $id = $neighbors[$i];
            $similarity = $this->similarity_search->calculateSimilarity(
                $normalized_query,
                $this->index['elements'][$id]['vector']
            );
            
            $results[] = [
                'id' => $id,
                'similarity' => $similarity,
                'metadata' => $this->index['elements'][$id]['metadata'],
                'level' => $this->index['elements'][$id]['level']
            ];
        }
        
        return $results;
    }
    
    /**
     * Generate random level using geometric distribution
     * 
     * @return int
     */
    private function randomLevel() {
        $random = mt_rand() / mt_getrandmax();
        $level = floor(-log($random) * $this->level_multiplier);
        return min($level, $this->max_levels - 1);
    }
    
    /**
     * Remove element from index
     * 
     * @param string $id
     * @return bool
     */
    public function removeElement($id) {
        if (!isset($this->index['elements'][$id])) {
            return false;
        }
        
        $level = $this->index['elements'][$id]['level'];
        
        // Remove from all graphs
        for ($l = 0; $l <= $level; $l++) {
            if (isset($this->index['graphs'][$l][$id])) {
                // Remove this element from neighbors
                foreach ($this->index['graphs'][$l][$id] as $neighbor_id) {
                    $key = array_search($id, $this->index['graphs'][$l][$neighbor_id]);
                    if ($key !== false) {
                        array_splice($this->index['graphs'][$l][$neighbor_id], $key, 1);
                    }
                }
                unset($this->index['graphs'][$l][$id]);
            }
        }
        
        // Remove element
        unset($this->index['elements'][$id]);
        
        // Update entry point if needed
        if ($this->index['entry_point'] === $id) {
            $this->index['entry_point'] = $this->findNewEntryPoint();
        }
        
        $this->index['metadata']['total_elements']--;
        $this->index['metadata']['updated_at'] = time();
        $this->index['operation_count']++;
        
        // Auto-save if needed
        if ($this->config['auto_save'] && 
            $this->index['operation_count'] >= $this->config['save_interval']) {
            $this->saveToFile();
        }
        
        return true;
    }
    
    /**
     * Find new entry point after removal
     * 
     * @return string|null
     */
    private function findNewEntryPoint() {
        if (empty($this->index['elements'])) {
            return null;
        }
        
        // Find element with highest level
        $max_level = -1;
        $new_entry = null;
        
        foreach ($this->index['elements'] as $id => $element) {
            if ($element['level'] > $max_level) {
                $max_level = $element['level'];
                $new_entry = $id;
            }
        }
        
        return $new_entry;
    }
    
    /**
     * Get index statistics
     * 
     * @return array
     */
    public function getStats() {
        $total_connections = 0;
        foreach ($this->index['graphs'] as $level => $graph) {
            foreach ($graph as $connections) {
                $total_connections += count($connections);
            }
        }
        
        return [
            'total_elements' => $this->index['metadata']['total_elements'],
            'max_level' => $this->index['max_level'],
            'total_levels' => count($this->index['graphs']),
            'total_connections' => $total_connections,
            'avg_connections' => $this->index['metadata']['total_elements'] > 0 
                ? $total_connections / $this->index['metadata']['total_elements'] 
                : 0,
            'entry_point' => $this->index['entry_point'],
            'operation_count' => $this->index['operation_count'],
            'created_at' => $this->index['metadata']['created_at'],
            'updated_at' => $this->index['metadata']['updated_at'],
            'config' => $this->config
        ];
    }
    
    /**
     * Save index to file
     * 
     * @return bool
     */
    public function saveToFile() {
        if (!$this->config['index_file']) {
            return false;
        }
        
        $dir = dirname($this->config['index_file']);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Reset operation counter
        $this->index['operation_count'] = 0;
        
        file_put_contents(
            $this->config['index_file'],
            json_encode($this->index, JSON_PRETTY_PRINT)
        );
        
        return true;
    }
    
    /**
     * Load index from file
     * 
     * @return bool
     */
    private function loadFromFile() {
        if (!file_exists($this->config['index_file'])) {
            return false;
        }
        
        $data = json_decode(file_get_contents($this->config['index_file']), true);
        if ($data) {
            $this->index = $data;
            return true;
        }
        
        return false;
    }
    
    /**
     * Clear entire index
     */
    public function clear() {
        $this->initializeIndex();
        
        if ($this->config['index_file'] && file_exists($this->config['index_file'])) {
            unlink($this->config['index_file']);
        }
    }
    
    /**
     * Bulk add elements
     * 
     * @param array $elements Array of [id, vector, metadata]
     * @return array Success/failure counts
     */
    public function bulkAdd($elements) {
        $success = 0;
        $failed = 0;
        
        foreach ($elements as $element) {
            if ($this->addElement($element[0], $element[1], $element[2] ?? [])) {
                $success++;
            } else {
                $failed++;
            }
        }
        
        return [
            'success' => $success,
            'failed' => $failed,
            'total' => count($elements)
        ];
    }
    
    /**
     * Get element by ID
     * 
     * @param string $id
     * @return array|null
     */
    public function getElement($id) {
        return $this->index['elements'][$id] ?? null;
    }
    
    /**
     * Check if index is empty
     * 
     * @return bool
     */
    public function isEmpty() {
        return $this->index['metadata']['total_elements'] === 0;
    }
}