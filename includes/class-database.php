<?php
/**
 * API Master - JSON Database Manager
 * BU BİR WORDPRESS MODÜLÜDÜR, PLUGIN DEĞİLDİR
 * 
 * @package APIMaster
 * @subpackage Includes
 */

if (!defined('ABSPATH')) {
    // Normal PHP çalışması
}

class APIMaster_Database {
    
    private $data_dir;
    private $collections = [];
    private $memory_cache = [];
    
    public function __construct() {
        // Sabit API_MASTER_DATA_DIR kullan
        $this->data_dir = defined('API_MASTER_DATA_DIR') 
            ? API_MASTER_DATA_DIR 
            : dirname(__DIR__) . '/data/';
        
        $this->ensureDataDirectory();
        $this->initializeCollections();
    }
    
    private function ensureDataDirectory(): void {
        if (!is_dir($this->data_dir)) {
            mkdir($this->data_dir, 0755, true);
        }
        
        $subdirs = ['queue', 'metadata', 'backups', 'temp', 'stats', 'learning', 'vector'];
        foreach ($subdirs as $subdir) {
            $path = $this->data_dir . $subdir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }
    
    private function initializeCollections(): void {
        $this->collections = [
            'logs' => [
                'file' => 'logs.json',
                'primary_key' => 'id',
                'auto_increment' => true,
                'max_size' => 10000,
                'ttl' => 2592000
            ],
            'api_keys' => [
                'file' => 'api-keys.json',
                'primary_key' => 'id',
                'auto_increment' => true,
                'max_size' => 1000
            ],
            'queue' => [
                'file' => 'queue/queue-jobs.json',
                'primary_key' => 'id',
                'auto_increment' => true,
                'max_size' => 5000
            ],
            'metadata' => [
                'file' => 'metadata/system.json',
                'primary_key' => 'key',
                'auto_increment' => false
            ],
            'vectors' => [
                'file' => 'vectors.json',
                'primary_key' => 'id',
                'auto_increment' => true,
                'max_size' => 100000
            ],
            'learning' => [
                'file' => 'learning/learning-data.json',
                'primary_key' => 'id',
                'auto_increment' => true
            ]
        ];
    }
    
    private function getCollectionFile(string $collection): string {
        if (!isset($this->collections[$collection])) {
            throw new InvalidArgumentException("Collection '{$collection}' not found");
        }
        
        return $this->data_dir . $this->collections[$collection]['file'];
    }
    
    private function loadCollection(string $collection): array {
        if (isset($this->memory_cache[$collection])) {
            return $this->memory_cache[$collection];
        }
        
        $file = $this->getCollectionFile($collection);
        
        if (!file_exists($file)) {
            $this->memory_cache[$collection] = [];
            return [];
        }
        
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        if (!is_array($data)) {
            $data = [];
        }
        
        $data = $this->cleanExpired($collection, $data);
        $this->memory_cache[$collection] = $data;
        
        return $data;
    }
    
    private function saveCollection(string $collection, array $data): bool {
        $file = $this->getCollectionFile($collection);
        $dir = dirname($file);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $config = $this->collections[$collection];
        if (isset($config['max_size']) && count($data) > $config['max_size']) {
            usort($data, function($a, $b) {
                $a_time = $a['created_at'] ?? $a['timestamp'] ?? 0;
                $b_time = $b['created_at'] ?? $b['timestamp'] ?? 0;
                return $a_time <=> $b_time;
            });
            $data = array_slice($data, -$config['max_size']);
        }
        
        $this->memory_cache[$collection] = $data;
        
        return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
    
    private function cleanExpired(string $collection, array $data): array {
        $config = $this->collections[$collection];
        
        if (!isset($config['ttl'])) {
            return $data;
        }
        
        $now = time();
        $expire_time = $now - $config['ttl'];
        
        return array_filter($data, function($item) use ($expire_time) {
            $created = $item['created_at'] ?? $item['timestamp'] ?? 0;
            if (is_string($created)) {
                $created = strtotime($created);
            }
            return $created > $expire_time;
        });
    }
    
    private function generateId(string $collection) {
        $config = $this->collections[$collection];
        
        if ($config['auto_increment']) {
            $data = $this->loadCollection($collection);
            $max_id = 0;
            foreach ($data as $item) {
                $id = $item[$config['primary_key']] ?? 0;
                if (is_numeric($id) && $id > $max_id) {
                    $max_id = $id;
                }
            }
            return $max_id + 1;
        }
        
        return uniqid();
    }
    
    public function insert(string $collection, array $data) {
        $config = $this->collections[$collection];
        $primary_key = $config['primary_key'];
        
        if (!isset($data[$primary_key])) {
            $data[$primary_key] = $this->generateId($collection);
        }
        
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        $collection_data = $this->loadCollection($collection);
        
        foreach ($collection_data as $item) {
            if ($item[$primary_key] == $data[$primary_key]) {
                return false;
            }
        }
        
        $collection_data[] = $data;
        
        if ($this->saveCollection($collection, $collection_data)) {
            return $data[$primary_key];
        }
        
        return false;
    }
    
    public function find(string $collection, array $filter = [], array $options = []): array {
        $data = $this->loadCollection($collection);
        $results = [];
        
        foreach ($data as $item) {
            if ($this->matchesFilter($item, $filter)) {
                $results[] = $item;
            }
        }
        
        if (isset($options['sort'])) {
            foreach ($options['sort'] as $field => $direction) {
                usort($results, function($a, $b) use ($field, $direction) {
                    $a_val = $a[$field] ?? null;
                    $b_val = $b[$field] ?? null;
                    
                    if ($direction === 'desc') {
                        return $b_val <=> $a_val;
                    }
                    return $a_val <=> $b_val;
                });
            }
        }
        
        if (isset($options['limit'])) {
            $results = array_slice($results, 0, $options['limit']);
        }
        
        if (isset($options['offset'])) {
            $results = array_slice($results, $options['offset']);
        }
        
        return $results;
    }
    
    public function findOne(string $collection, array $filter = []): ?array {
        $results = $this->find($collection, $filter, ['limit' => 1]);
        return $results[0] ?? null;
    }
    
    public function findById(string $collection, $id): ?array {
        $config = $this->collections[$collection];
        $primary_key = $config['primary_key'];
        
        return $this->findOne($collection, [$primary_key => $id]);
    }
    
    public function update(string $collection, array $filter, array $update): int {
        $data = $this->loadCollection($collection);
        $config = $this->collections[$collection];
        $primary_key = $config['primary_key'];
        $updated = 0;
        
        foreach ($data as $index => $item) {
            if ($this->matchesFilter($item, $filter)) {
                foreach ($update as $field => $value) {
                    if ($field === $primary_key) {
                        continue;
                    }
                    $data[$index][$field] = $value;
                }
                $data[$index]['updated_at'] = date('Y-m-d H:i:s');
                $updated++;
            }
        }
        
        if ($updated > 0) {
            $this->saveCollection($collection, $data);
        }
        
        return $updated;
    }
    
    public function updateById(string $collection, $id, array $update): bool {
        $config = $this->collections[$collection];
        $primary_key = $config['primary_key'];
        
        return $this->update($collection, [$primary_key => $id], $update) > 0;
    }
    
    public function delete(string $collection, array $filter): int {
        $data = $this->loadCollection($collection);
        $deleted = 0;
        $new_data = [];
        
        foreach ($data as $item) {
            if ($this->matchesFilter($item, $filter)) {
                $deleted++;
            } else {
                $new_data[] = $item;
            }
        }
        
        if ($deleted > 0) {
            $this->saveCollection($collection, $new_data);
        }
        
        return $deleted;
    }
    
    public function deleteById(string $collection, $id): bool {
        $config = $this->collections[$collection];
        $primary_key = $config['primary_key'];
        
        return $this->delete($collection, [$primary_key => $id]) > 0;
    }
    
    public function count(string $collection, array $filter = []): int {
        if (empty($filter)) {
            $data = $this->loadCollection($collection);
            return count($data);
        }
        
        return count($this->find($collection, $filter));
    }
    
    private function matchesFilter(array $item, array $filter): bool {
        foreach ($filter as $key => $value) {
            if (!isset($item[$key])) {
                return false;
            }
            
            if (is_array($value) && isset($value['$operator'])) {
                $operator = $value['$operator'];
                $value = $value['$value'];
                
                switch ($operator) {
                    case 'gt':
                        if (!($item[$key] > $value)) return false;
                        break;
                    case 'gte':
                        if (!($item[$key] >= $value)) return false;
                        break;
                    case 'lt':
                        if (!($item[$key] < $value)) return false;
                        break;
                    case 'lte':
                        if (!($item[$key] <= $value)) return false;
                        break;
                    case 'ne':
                        if (!($item[$key] != $value)) return false;
                        break;
                    case 'in':
                        if (!in_array($item[$key], $value)) return false;
                        break;
                    case 'nin':
                        if (in_array($item[$key], $value)) return false;
                        break;
                    default:
                        if ($item[$key] != $value) return false;
                }
            } else {
                if ($item[$key] != $value) return false;
            }
        }
        
        return true;
    }
    
    public function getStats(string $collection): array {
        $file = $this->getCollectionFile($collection);
        $data = $this->loadCollection($collection);
        
        return [
            'name' => $collection,
            'count' => count($data),
            'file_size' => file_exists($file) ? filesize($file) : 0,
            'last_modified' => file_exists($file) ? filemtime($file) : null,
            'config' => $this->collections[$collection]
        ];
    }
    
    public function backup(string $collection) {
        $data = $this->loadCollection($collection);
        $backup_dir = $this->data_dir . 'backups/';
        $backup_file = $backup_dir . $collection . '_' . date('Y-m-d_H-i-s') . '.json';
        
        if (file_put_contents($backup_file, json_encode($data, JSON_PRETTY_PRINT))) {
            return $backup_file;
        }
        
        return false;
    }
    
    public function clear(string $collection): bool {
        return $this->saveCollection($collection, []);
    }
}