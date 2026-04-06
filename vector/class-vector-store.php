<?php
/**
 * APIMaster Vector Store
 * BU BİR WORDPRESS MODÜLÜDÜR, PLUGIN DEĞİLDİR
 * 
 * Vektör tabanlı bellek depolama sistemi
 */

if (!defined('ABSPATH')) {
    // Normal PHP çalışması
}

class APIMaster_VectorStore {
    
    private $config;
    private $vectors = [];
    private $metadata = [];
    private $vector_path;
    
    public function __construct() {
        // Sabit API_MASTER_DATA_DIR kullan
        $dataDir = defined('API_MASTER_DATA_DIR') ? API_MASTER_DATA_DIR : dirname(__DIR__) . '/data';
        $this->vector_path = $dataDir . '/vectors/';
        $this->initVectorSystem();
    }
    
    private function initVectorSystem() {
        if (!file_exists($this->vector_path)) {
            mkdir($this->vector_path, 0755, true);
        }
        
        $this->loadConfig();
        $this->loadVectors();
    }
    
    private function loadConfig() {
        $config_file = $this->vector_path . 'config.json';
        
        if (file_exists($config_file)) {
            $this->config = json_decode(file_get_contents($config_file), true);
        } else {
            $this->config = $this->getDefaultConfig();
            $this->saveConfig();
        }
    }
    
    private function getDefaultConfig() {
        return [
            'dimension' => 384,
            'distance_metric' => 'cosine',
            'normalize_vectors' => true,
            'auto_index' => true,
            'max_vectors' => 100000,
            'persistence' => [
                'enabled' => true,
                'interval' => 300,
                'compression' => false
            ],
            'index_type' => 'flat'
        ];
    }
    
    private function saveConfig() {
        file_put_contents(
            $this->vector_path . 'config.json',
            json_encode($this->config, JSON_PRETTY_PRINT)
        );
    }
    
    private function loadVectors() {
        $vectors_file = $this->vector_path . 'vectors.json';
        
        if (file_exists($vectors_file)) {
            $data = json_decode(file_get_contents($vectors_file), true);
            $this->vectors = $data['vectors'] ?? [];
            $this->metadata = $data['metadata'] ?? [];
        } else {
            $this->vectors = [];
            $this->metadata = [];
            $this->saveVectors();
        }
    }
    
    private function saveVectors() {
        if (!$this->config['persistence']['enabled']) {
            return;
        }
        
        $data = [
            'vectors' => $this->vectors,
            'metadata' => $this->metadata,
            'last_updated' => time()
        ];
        
        file_put_contents(
            $this->vector_path . 'vectors.json',
            json_encode($data, JSON_PRETTY_PRINT)
        );
    }
    
    public function addVector($id, $vector, $metadata = []) {
        if (count($vector) !== $this->config['dimension']) {
            return false;
        }
        
        if ($this->config['normalize_vectors']) {
            $vector = $this->normalizeVector($vector);
        }
        
        $this->vectors[$id] = $vector;
        $this->metadata[$id] = array_merge([
            'id' => $id,
            'created_at' => time(),
            'last_accessed' => time(),
            'access_count' => 0
        ], $metadata);
        
        if (count($this->vectors) > $this->config['max_vectors']) {
            $this->evictOldVectors();
        }
        
        if ($this->config['persistence']['enabled']) {
            $this->saveVectors();
        }
        
        return true;
    }
    
    private function normalizeVector($vector) {
        $norm = sqrt(array_sum(array_map(function($v) {
            return $v * $v;
        }, $vector)));
        
        if ($norm > 0) {
            return array_map(function($v) use ($norm) {
                return $v / $norm;
            }, $vector);
        }
        
        return $vector;
    }
    
    private function evictOldVectors() {
        uasort($this->metadata, function($a, $b) {
            return $a['access_count'] <=> $b['access_count'];
        });
        
        $to_remove = array_slice(array_keys($this->metadata), 0, 1000);
        
        foreach ($to_remove as $id) {
            unset($this->vectors[$id]);
            unset($this->metadata[$id]);
        }
    }
    
    public function getVector($id) {
        if (isset($this->vectors[$id])) {
            $this->metadata[$id]['last_accessed'] = time();
            $this->metadata[$id]['access_count']++;
            
            return [
                'vector' => $this->vectors[$id],
                'metadata' => $this->metadata[$id]
            ];
        }
        
        return null;
    }
    
    public function similaritySearch($query_vector, $top_k = 10, $filter = []) {
        if (empty($this->vectors)) {
            return [];
        }
        
        if (count($query_vector) !== $this->config['dimension']) {
            return [];
        }
        
        if ($this->config['normalize_vectors']) {
            $query_vector = $this->normalizeVector($query_vector);
        }
        
        $similarities = [];
        
        foreach ($this->vectors as $id => $vector) {
            if (!$this->passesFilter($id, $filter)) {
                continue;
            }
            
            $similarity = $this->calculateSimilarity($query_vector, $vector);
            $similarities[$id] = $similarity;
        }
        
        arsort($similarities);
        
        $top_results = array_slice($similarities, 0, $top_k, true);
        
        $results = [];
        foreach ($top_results as $id => $score) {
            $results[] = [
                'id' => $id,
                'similarity' => $score,
                'vector' => $this->vectors[$id],
                'metadata' => $this->metadata[$id]
            ];
        }
        
        return $results;
    }
    
    private function passesFilter($id, $filter) {
        if (empty($filter)) {
            return true;
        }
        
        $metadata = $this->metadata[$id];
        
        foreach ($filter as $key => $value) {
            if (!isset($metadata[$key]) || $metadata[$key] !== $value) {
                return false;
            }
        }
        
        return true;
    }
    
    private function calculateSimilarity($vector_a, $vector_b) {
        switch ($this->config['distance_metric']) {
            case 'cosine':
                return $this->cosineSimilarity($vector_a, $vector_b);
            case 'euclidean':
                return $this->euclideanSimilarity($vector_a, $vector_b);
            case 'dot':
                return $this->dotProduct($vector_a, $vector_b);
            default:
                return $this->cosineSimilarity($vector_a, $vector_b);
        }
    }
    
    private function cosineSimilarity($a, $b) {
        $dot = 0;
        $norm_a = 0;
        $norm_b = 0;
        
        for ($i = 0; $i < count($a); $i++) {
            $dot += $a[$i] * $b[$i];
            $norm_a += $a[$i] * $a[$i];
            $norm_b += $b[$i] * $b[$i];
        }
        
        if ($norm_a == 0 || $norm_b == 0) {
            return 0;
        }
        
        return $dot / (sqrt($norm_a) * sqrt($norm_b));
    }
    
    private function euclideanSimilarity($a, $b) {
        $distance = 0;
        
        for ($i = 0; $i < count($a); $i++) {
            $diff = $a[$i] - $b[$i];
            $distance += $diff * $diff;
        }
        
        $distance = sqrt($distance);
        
        return 1 / (1 + $distance);
    }
    
    private function dotProduct($a, $b) {
        $dot = 0;
        
        for ($i = 0; $i < count($a); $i++) {
            $dot += $a[$i] * $b[$i];
        }
        
        return ($dot + 1) / 2;
    }
    
    public function getVectorStats() {
        return [
            'total_vectors' => count($this->vectors),
            'dimension' => $this->config['dimension'],
            'distance_metric' => $this->config['distance_metric'],
            'index_type' => $this->config['index_type'],
            'memory_usage' => $this->estimateMemoryUsage(),
            'last_updated' => time()
        ];
    }
    
    private function estimateMemoryUsage() {
        $memory = 0;
        
        foreach ($this->vectors as $vector) {
            $memory += count($vector) * 8;
        }
        
        foreach ($this->metadata as $meta) {
            $memory += strlen(json_encode($meta));
        }
        
        return round($memory / 1024 / 1024, 2);
    }
    
    public function clearVectors() {
        $this->vectors = [];
        $this->metadata = [];
        $this->saveVectors();
        
        return true;
    }
    
    public function count() {
        return count($this->vectors);
    }
    
    public function getAllIds() {
        return array_keys($this->vectors);
    }
    
    public function exists($id) {
        return isset($this->vectors[$id]);
    }
}