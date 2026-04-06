<?php
/**
 * Memory Consolidation - Long-term Memory Management for Vector Database
 * 
 * @package APIMaster
 * @subpackage Vector
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_MemoryConsolidation {
    
    /**
     * @var APIMaster_VectorStore $short_term_memory
     */
    private $short_term_memory;
    
    /**
     * @var APIMaster_VectorStore $long_term_memory
     */
    private $long_term_memory;
    
    /**
     * @var APIMaster_VectorIndex $vector_index
     */
    private $vector_index;
    
    /**
     * @var array $config Configuration
     */
    private $config;
    
    /**
     * @var array $consolidation_queue
     */
    private $consolidation_queue;
    
    /**
     * @var array $memory_stats
     */
    private $memory_stats;
    
    /**
     * Constructor
     * 
     * @param array $config Optional configuration
     */
    public function __construct($config = []) {
        $this->loadConfig($config);
        $this->initializeComponents();
        $this->loadConsolidationQueue();
        $this->initializeMemoryStats();
    }
    
    /**
     * Load configuration
     * 
     * @param array $config
     */
    private function loadConfig($config = []) {
        $default_config = [
            'short_term' => [
                'max_size' => 1000,           // Max vectors in short-term
                'ttl' => 3600,                 // Time to live in seconds
                'max_age' => 86400             // Max age before forced consolidation (24 hours)
            ],
            'long_term' => [
                'max_size' => 100000,          // Max vectors in long-term
                'compression_enabled' => true,  // Enable vector compression
                'compression_ratio' => 0.5      // Compression target ratio
            ],
            'consolidation' => [
                'interval' => 300,              // Check every 5 minutes
                'batch_size' => 50,             // Process 50 items per batch
                'importance_threshold' => 0.7,   // Importance score threshold
                'access_threshold' => 5,         // Minimum accesses to consolidate
                'similarity_threshold' => 0.85    // Similarity for merging
            ],
            'forgetting' => [
                'enabled' => true,
                'decay_rate' => 0.01,           // Memory decay rate per hour
                'min_importance' => 0.1,         // Minimum importance before forgetting
                'prune_interval' => 86400        // Prune every 24 hours
            ],
            'paths' => [
                'queue_file' => dirname(dirname(__FILE__)) . '/data/consolidation-queue.json',
                'stats_file' => dirname(dirname(__FILE__)) . '/data/memory-stats.json',
                'backup_dir' => dirname(dirname(__FILE__)) . '/backups/memory/'
            ]
        ];
        
        $this->config = array_merge_recursive($default_config, $config);
    }
    
    /**
     * Initialize components
     */
    private function initializeComponents() {
        $this->short_term_memory = new APIMaster_VectorStore();
        $this->long_term_memory = new APIMaster_VectorStore();
        $this->vector_index = new APIMaster_VectorIndex();
        
        // Set different storage paths for short and long term
        $this->short_term_memory->setStoragePath('short-term-memory.json');
        $this->long_term_memory->setStoragePath('long-term-memory.json');
    }
    
    /**
     * Load consolidation queue from file
     */
    private function loadConsolidationQueue() {
        $queue_file = $this->config['paths']['queue_file'];
        
        if (file_exists($queue_file)) {
            $this->consolidation_queue = json_decode(file_get_contents($queue_file), true);
        } else {
            $this->consolidation_queue = [
                'pending' => [],      // Items waiting for consolidation
                'processing' => [],   // Items currently being processed
                'completed' => [],    // Recently completed consolidations
                'failed' => []        // Failed consolidations
            ];
        }
    }
    
    /**
     * Initialize memory statistics
     */
    private function initializeMemoryStats() {
        $stats_file = $this->config['paths']['stats_file'];
        
        if (file_exists($stats_file)) {
            $this->memory_stats = json_decode(file_get_contents($stats_file), true);
        } else {
            $this->memory_stats = [
                'short_term' => [
                    'current_size' => 0,
                    'peak_size' => 0,
                    'total_accesses' => 0,
                    'avg_importance' => 0
                ],
                'long_term' => [
                    'current_size' => 0,
                    'peak_size' => 0,
                    'total_consolidations' => 0,
                    'avg_importance' => 0
                ],
                'consolidations' => [
                    'total' => 0,
                    'successful' => 0,
                    'failed' => 0,
                    'merged' => 0,
                    'forgotten' => 0
                ],
                'last_consolidation' => null,
                'last_prune' => null,
                'created_at' => time(),
                'updated_at' => time()
            ];
        }
    }
    
    /**
     * Add memory to short-term storage
     * 
     * @param string $id
     * @param array $vector
     * @param array $metadata
     * @param float $importance
     * @return bool
     */
    public function addMemory($id, $vector, $metadata = [], $importance = 0.5) {
        // Check if short-term is full
        if ($this->short_term_memory->count() >= $this->config['short_term']['max_size']) {
            $this->triggerConsolidation();
        }
        
        // Add to short-term memory
        $memory_data = [
            'vector' => $vector,
            'metadata' => array_merge($metadata, [
                'importance' => $importance,
                'access_count' => 0,
                'last_access' => time(),
                'created_at' => time(),
                'memory_type' => 'short_term'
            ])
        ];
        
        $result = $this->short_term_memory->add($id, $memory_data);
        
        if ($result) {
            // Add to consolidation queue if important enough
            if ($importance >= $this->config['consolidation']['importance_threshold']) {
                $this->addToConsolidationQueue($id, 'high_importance');
            }
            
            $this->updateMemoryStats('short_term', 'add');
        }
        
        return $result;
    }
    
    /**
     * Get memory by ID
     * 
     * @param string $id
     * @param bool $update_access
     * @return array|null
     */
    public function getMemory($id, $update_access = true) {
        // Check short-term first
        $memory = $this->short_term_memory->get($id);
        $memory_type = 'short_term';
        
        if (!$memory) {
            // Check long-term
            $memory = $this->long_term_memory->get($id);
            $memory_type = 'long_term';
        }
        
        if ($memory && $update_access) {
            // Update access statistics
            $memory['metadata']['access_count']++;
            $memory['metadata']['last_access'] = time();
            
            // Update importance based on access pattern
            $memory['metadata']['importance'] = $this->calculateImportance($memory['metadata']);
            
            // Save updated metadata
            $this->updateMemoryMetadata($id, $memory['metadata'], $memory_type);
            
            $this->updateMemoryStats($memory_type, 'access');
        }
        
        return $memory;
    }
    
    /**
     * Trigger memory consolidation
     * 
     * @param bool $force Force immediate consolidation
     * @return array
     */
    public function triggerConsolidation($force = false) {
        $start_time = microtime(true);
        
        // Check if consolidation is needed
        if (!$force && !$this->isConsolidationNeeded()) {
            return ['status' => 'skipped', 'reason' => 'Not needed'];
        }
        
        // Process consolidation queue
        $processed = 0;
        $consolidated = 0;
        $merged = 0;
        
        $batch_size = $this->config['consolidation']['batch_size'];
        $pending_items = array_slice($this->consolidation_queue['pending'], 0, $batch_size);
        
        foreach ($pending_items as $item) {
            $result = $this->consolidateMemory($item['id']);
            
            if ($result['success']) {
                $consolidated++;
                if ($result['merged']) {
                    $merged++;
                }
                $this->moveToCompleted($item['id']);
            } else {
                $this->moveToFailed($item['id'], $result['error']);
            }
            
            $processed++;
        }
        
        // Check for old memories (aging)
        $aged_memories = $this->findAgedMemories();
        foreach ($aged_memories as $aged_id) {
            $this->addToConsolidationQueue($aged_id, 'aged');
        }
        
        // Prune if needed
        if ($this->config['forgetting']['enabled']) {
            $pruned = $this->pruneOldMemories();
        } else {
            $pruned = 0;
        }
        
        $time_taken = microtime(true) - $start_time;
        
        // Update statistics
        $this->memory_stats['consolidations']['total']++;
        $this->memory_stats['consolidations']['successful'] += $consolidated;
        $this->memory_stats['consolidations']['merged'] += $merged;
        $this->memory_stats['last_consolidation'] = time();
        $this->saveMemoryStats();
        
        return [
            'status' => 'completed',
            'processed' => $processed,
            'consolidated' => $consolidated,
            'merged' => $merged,
            'pruned' => $pruned,
            'time_taken' => $time_taken,
            'queue_remaining' => count($this->consolidation_queue['pending'])
        ];
    }
    
    /**
     * Consolidate a single memory
     * 
     * @param string $id
     * @return array
     */
    private function consolidateMemory($id) {
        $memory = $this->short_term_memory->get($id);
        
        if (!$memory) {
            return ['success' => false, 'error' => 'Memory not found'];
        }
        
        // Check if memory is ready for consolidation
        if (!$this->isReadyForConsolidation($memory['metadata'])) {
            return ['success' => false, 'error' => 'Not ready for consolidation'];
        }
        
        // Check for similar memories in long-term
        $similar = $this->findSimilarMemories($memory['vector'], 0.85);
        
        if (!empty($similar)) {
            // Merge with existing memory
            return $this->mergeMemories($id, $similar[0]['id'], $memory);
        } else {
            // Move to long-term
            return $this->moveToLongTerm($id, $memory);
        }
    }
    
    /**
     * Move memory to long-term storage
     * 
     * @param string $id
     * @param array $memory
     * @return array
     */
    private function moveToLongTerm($id, $memory) {
        // Check long-term capacity
        if ($this->long_term_memory->count() >= $this->config['long_term']['max_size']) {
            $this->pruneLongTerm();
        }
        
        // Compress vector if enabled
        $vector = $memory['vector'];
        if ($this->config['long_term']['compression_enabled']) {
            $vector = $this->compressVector($vector);
        }
        
        // Update metadata for long-term
        $memory['metadata']['memory_type'] = 'long_term';
        $memory['metadata']['consolidated_at'] = time();
        $memory['metadata']['consolidation_count'] = ($memory['metadata']['consolidation_count'] ?? 0) + 1;
        
        // Add to long-term
        $result = $this->long_term_memory->add($id, [
            'vector' => $vector,
            'metadata' => $memory['metadata']
        ]);
        
        if ($result) {
            // Remove from short-term
            $this->short_term_memory->remove($id);
            
            $this->updateMemoryStats('long_term', 'add');
            $this->updateMemoryStats('short_term', 'remove');
            
            return [
                'success' => true,
                'merged' => false,
                'action' => 'moved_to_long_term'
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to move to long-term'];
    }
    
    /**
     * Merge similar memories
     * 
     * @param string $new_id
     * @param string $existing_id
     * @param array $new_memory
     * @return array
     */
    private function mergeMemories($new_id, $existing_id, $new_memory) {
        $existing = $this->long_term_memory->get($existing_id);
        
        if (!$existing) {
            // Fallback to moving to long-term
            return $this->moveToLongTerm($new_id, $new_memory);
        }
        
        // Average the vectors
        $merged_vector = [];
        $vector1 = $new_memory['vector'];
        $vector2 = $existing['vector'];
        
        for ($i = 0; $i < min(count($vector1), count($vector2)); $i++) {
            $merged_vector[] = ($vector1[$i] + $vector2[$i]) / 2;
        }
        
        // Merge metadata
        $merged_metadata = $existing['metadata'];
        $merged_metadata['importance'] = max(
            $existing['metadata']['importance'],
            $new_memory['metadata']['importance']
        );
        $merged_metadata['access_count'] += $new_memory['metadata']['access_count'];
        $merged_metadata['merged_ids'][] = $new_id;
        $merged_metadata['merged_at'] = time();
        $merged_metadata['merge_count'] = ($merged_metadata['merge_count'] ?? 0) + 1;
        
        // Update existing memory
        $this->long_term_memory->update($existing_id, $merged_vector, $merged_metadata);
        
        // Remove from short-term
        $this->short_term_memory->remove($new_id);
        
        $this->updateMemoryStats('long_term', 'merge');
        $this->updateMemoryStats('short_term', 'remove');
        
        return [
            'success' => true,
            'merged' => true,
            'merged_into' => $existing_id,
            'action' => 'merged_with_existing'
        ];
    }
    
    /**
     * Find similar memories
     * 
     * @param array $vector
     * @param float $threshold
     * @return array
     */
    private function findSimilarMemories($vector, $threshold = 0.85) {
        $similarity_search = new APIMaster_SimilaritySearch();
        $results = $similarity_search->findByVector($vector, [
            'metric' => 'cosine',
            'min_similarity' => $threshold,
            'limit' => 5
        ]);
        
        return $results;
    }
    
    /**
     * Find aged memories (old and rarely accessed)
     * 
     * @return array
     */
    private function findAgedMemories() {
        $aged = [];
        $all_memories = $this->short_term_memory->getAll();
        $max_age = $this->config['short_term']['max_age'];
        $now = time();
        
        foreach ($all_memories as $id => $data) {
            $created_at = $data['metadata']['created_at'] ?? 0;
            $access_count = $data['metadata']['access_count'] ?? 0;
            
            if (($now - $created_at) > $max_age && $access_count < 3) {
                $aged[] = $id;
            }
        }
        
        return $aged;
    }
    
    /**
     * Prune old and unimportant memories
     * 
     * @return int Number of pruned memories
     */
    private function pruneOldMemories() {
        $pruned = 0;
        $all_memories = $this->long_term_memory->getAll();
        $decay_rate = $this->config['forgetting']['decay_rate'];
        $min_importance = $this->config['forgetting']['min_importance'];
        $now = time();
        
        foreach ($all_memories as $id => $data) {
            $metadata = $data['metadata'];
            $last_access = $metadata['last_access'] ?? $metadata['created_at'];
            $hours_since_access = ($now - $last_access) / 3600;
            
            // Apply decay
            $decayed_importance = $metadata['importance'] * exp(-$decay_rate * $hours_since_access);
            
            if ($decayed_importance < $min_importance) {
                $this->long_term_memory->remove($id);
                $pruned++;
                $this->memory_stats['consolidations']['forgotten']++;
            } else {
                // Update importance with decay
                $metadata['importance'] = $decayed_importance;
                $this->long_term_memory->update($id, $data['vector'], $metadata);
            }
        }
        
        if ($pruned > 0) {
            $this->updateMemoryStats('long_term', 'prune', $pruned);
        }
        
        return $pruned;
    }
    
    /**
     * Prune long-term memory to free space
     */
    private function pruneLongTerm() {
        $all_memories = $this->long_term_memory->getAll();
        
        // Sort by importance (lowest first)
        uasort($all_memories, function($a, $b) {
            return $a['metadata']['importance'] <=> $b['metadata']['importance'];
        });
        
        // Remove 10% lowest importance memories
        $to_remove = ceil(count($all_memories) * 0.1);
        $removed = 0;
        
        foreach ($all_memories as $id => $data) {
            if ($removed >= $to_remove) break;
            $this->long_term_memory->remove($id);
            $removed++;
        }
    }
    
    /**
     * Calculate memory importance based on access patterns
     * 
     * @param array $metadata
     * @return float
     */
    private function calculateImportance($metadata) {
        $base_importance = $metadata['importance'] ?? 0.5;
        $access_count = $metadata['access_count'] ?? 0;
        $last_access = $metadata['last_access'] ?? time();
        $now = time();
        
        // Recency factor (more recent = higher importance)
        $hours_since_access = ($now - $last_access) / 3600;
        $recency_factor = exp(-$hours_since_access / 24); // Decay over 24 hours
        
        // Frequency factor
        $frequency_factor = min(1, $access_count / 10);
        
        // Combined importance
        $new_importance = ($base_importance * 0.4) + ($recency_factor * 0.3) + ($frequency_factor * 0.3);
        
        return min(1, max(0, $new_importance));
    }
    
    /**
     * Check if consolidation is needed
     * 
     * @return bool
     */
    private function isConsolidationNeeded() {
        // Check if short-term is nearly full
        $short_term_usage = $this->short_term_memory->count() / $this->config['short_term']['max_size'];
        if ($short_term_usage > 0.8) {
            return true;
        }
        
        // Check pending queue size
        if (count($this->consolidation_queue['pending']) > 10) {
            return true;
        }
        
        // Check last consolidation time
        $last_consolidation = $this->memory_stats['last_consolidation'];
        if (!$last_consolidation || (time() - $last_consolidation) > $this->config['consolidation']['interval']) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if memory is ready for consolidation
     * 
     * @param array $metadata
     * @return bool
     */
    private function isReadyForConsolidation($metadata) {
        $access_count = $metadata['access_count'] ?? 0;
        $min_accesses = $this->config['consolidation']['access_threshold'];
        
        // Need minimum accesses to be worth consolidating
        return $access_count >= $min_accesses;
    }
    
    /**
     * Add memory to consolidation queue
     * 
     * @param string $id
     * @param string $reason
     */
    private function addToConsolidationQueue($id, $reason = 'manual') {
        if (!in_array($id, array_column($this->consolidation_queue['pending'], 'id'))) {
            $this->consolidation_queue['pending'][] = [
                'id' => $id,
                'reason' => $reason,
                'added_at' => time()
            ];
            $this->saveConsolidationQueue();
        }
    }
    
    /**
     * Move memory to completed
     * 
     * @param string $id
     */
    private function moveToCompleted($id) {
        foreach ($this->consolidation_queue['pending'] as $key => $item) {
            if ($item['id'] === $id) {
                $item['completed_at'] = time();
                $this->consolidation_queue['completed'][] = $item;
                unset($this->consolidation_queue['pending'][$key]);
                break;
            }
        }
        
        // Keep only last 100 completed items
        if (count($this->consolidation_queue['completed']) > 100) {
            $this->consolidation_queue['completed'] = array_slice($this->consolidation_queue['completed'], -100);
        }
        
        $this->saveConsolidationQueue();
    }
    
    /**
     * Move memory to failed
     * 
     * @param string $id
     * @param string $error
     */
    private function moveToFailed($id, $error) {
        foreach ($this->consolidation_queue['pending'] as $key => $item) {
            if ($item['id'] === $id) {
                $item['error'] = $error;
                $item['failed_at'] = time();
                $this->consolidation_queue['failed'][] = $item;
                unset($this->consolidation_queue['pending'][$key]);
                break;
            }
        }
        
        $this->saveConsolidationQueue();
        $this->memory_stats['consolidations']['failed']++;
    }
    
    /**
     * Update memory metadata
     * 
     * @param string $id
     * @param array $metadata
     * @param string $type
     */
    private function updateMemoryMetadata($id, $metadata, $type) {
        $memory_store = $type === 'short_term' ? $this->short_term_memory : $this->long_term_memory;
        $memory = $memory_store->get($id);
        
        if ($memory) {
            $memory_store->update($id, $memory['vector'], $metadata);
        }
    }
    
    /**
     * Update memory statistics
     * 
     * @param string $type
     * @param string $action
     * @param int $count
     */
    private function updateMemoryStats($type, $action, $count = 1) {
        if ($type === 'short_term') {
            if ($action === 'add') {
                $this->memory_stats['short_term']['current_size'] += $count;
                $this->memory_stats['short_term']['peak_size'] = max(
                    $this->memory_stats['short_term']['peak_size'],
                    $this->memory_stats['short_term']['current_size']
                );
            } elseif ($action === 'remove') {
                $this->memory_stats['short_term']['current_size'] -= $count;
            } elseif ($action === 'access') {
                $this->memory_stats['short_term']['total_accesses'] += $count;
            }
        } elseif ($type === 'long_term') {
            if ($action === 'add') {
                $this->memory_stats['long_term']['current_size'] += $count;
                $this->memory_stats['long_term']['peak_size'] = max(
                    $this->memory_stats['long_term']['peak_size'],
                    $this->memory_stats['long_term']['current_size']
                );
                $this->memory_stats['long_term']['total_consolidations']++;
            } elseif ($action === 'remove' || $action === 'prune') {
                $this->memory_stats['long_term']['current_size'] -= $count;
            } elseif ($action === 'merge') {
                $this->memory_stats['long_term']['current_size']--; // One less after merge
            }
        }
        
        $this->memory_stats['updated_at'] = time();
        $this->saveMemoryStats();
    }
    
    /**
     * Compress vector for long-term storage
     * 
     * @param array $vector
     * @return array
     */
    private function compressVector($vector) {
        $ratio = $this->config['long_term']['compression_ratio'];
        $new_size = max(10, floor(count($vector) * $ratio));
        
        // Simple dimensionality reduction (average pooling)
        $compressed = [];
        $chunk_size = ceil(count($vector) / $new_size);
        
        for ($i = 0; $i < count($vector); $i += $chunk_size) {
            $chunk = array_slice($vector, $i, $chunk_size);
            $compressed[] = array_sum($chunk) / count($chunk);
        }
        
        return $compressed;
    }
    
    /**
     * Save consolidation queue to file
     */
    private function saveConsolidationQueue() {
        $queue_file = $this->config['paths']['queue_file'];
        $dir = dirname($queue_file);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($queue_file, json_encode($this->consolidation_queue, JSON_PRETTY_PRINT));
    }
    
    /**
     * Save memory statistics to file
     */
    private function saveMemoryStats() {
        $stats_file = $this->config['paths']['stats_file'];
        $dir = dirname($stats_file);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($stats_file, json_encode($this->memory_stats, JSON_PRETTY_PRINT));
    }
    
    /**
     * Get memory statistics
     * 
     * @return array
     */
    public function getMemoryStats() {
        $this->memory_stats['short_term']['current_size'] = $this->short_term_memory->count();
        $this->memory_stats['long_term']['current_size'] = $this->long_term_memory->count();
        
        return $this->memory_stats;
    }
    
    /**
     * Get consolidation queue status
     * 
     * @return array
     */
    public function getQueueStatus() {
        return [
            'pending' => count($this->consolidation_queue['pending']),
            'processing' => count($this->consolidation_queue['processing']),
            'completed_today' => count(array_filter($this->consolidation_queue['completed'], function($item) {
                return $item['completed_at'] > strtotime('today');
            })),
            'failed' => count($this->consolidation_queue['failed'])
        ];
    }
    
    /**
     * Backup memory to file
     * 
     * @return string Backup file path
     */
    public function backupMemory() {
        $backup_dir = $this->config['paths']['backup_dir'];
        
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backup_file = $backup_dir . "memory_backup_{$timestamp}.json";
        
        $backup_data = [
            'timestamp' => time(),
            'short_term' => $this->short_term_memory->getAll(),
            'long_term' => $this->long_term_memory->getAll(),
            'queue' => $this->consolidation_queue,
            'stats' => $this->memory_stats,
            'config' => $this->config
        ];
        
        file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT));
        
        return $backup_file;
    }
    
    /**
     * Restore memory from backup
     * 
     * @param string $backup_file
     * @return bool
     */
    public function restoreFromBackup($backup_file) {
        if (!file_exists($backup_file)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($backup_file), true);
        
        if (!$data) {
            return false;
        }
        
        // Clear existing memories
        $this->short_term_memory->clear();
        $this->long_term_memory->clear();
        
        // Restore short-term
        foreach ($data['short_term'] as $id => $memory) {
            $this->short_term_memory->add($id, $memory);
        }
        
        // Restore long-term
        foreach ($data['long_term'] as $id => $memory) {
            $this->long_term_memory->add($id, $memory);
        }
        
        // Restore queue and stats
        $this->consolidation_queue = $data['queue'];
        $this->memory_stats = $data['stats'];
        
        $this->saveConsolidationQueue();
        $this->saveMemoryStats();
        
        return true;
    }
    
    /**
     * Clear all memories
     */
    public function clearAllMemories() {
        $this->short_term_memory->clear();
        $this->long_term_memory->clear();
        $this->consolidation_queue = ['pending' => [], 'processing' => [], 'completed' => [], 'failed' => []];
        $this->saveConsolidationQueue();
        $this->initializeMemoryStats();
        
        return true;
    }
    
    /**
     * Run maintenance tasks
     * 
     * @return array
     */
    public function runMaintenance() {
        $results = [];
        
        // Trigger consolidation
        $results['consolidation'] = $this->triggerConsolidation(true);
        
        // Prune old memories
        if ($this->config['forgetting']['enabled']) {
            $results['pruned'] = $this->pruneOldMemories();
        }
        
        // Create backup
        $results['backup'] = $this->backupMemory();
        
        // Update stats
        $results['stats'] = $this->getMemoryStats();
        
        return $results;
    }
}