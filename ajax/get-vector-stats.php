<?php
/**
 * get-vector-stats.php - AJAX Endpoint for Masal Panel
 * Get vector memory statistics from JSON storage
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);

class APIMaster_GetVectorStats
{
    private $moduleDir;
    private $vectorDir;
    private $logDir;
    private $statsFile;
    
    private $validCategories = ['general', 'api', 'conversation', 'knowledge', 'custom'];
    
    public function __construct()
    {
        $this->moduleDir = defined('API_MASTER_MODULE_DIR') 
            ? API_MASTER_MODULE_DIR 
            : dirname(__DIR__);
        
        $this->vectorDir = $this->moduleDir . '/data/vector';
        $this->logDir = $this->moduleDir . '/logs';
        $this->statsFile = $this->vectorDir . '/stats.json';
    }
    
    public function execute()
    {
        try {
            $detailed = isset($_GET['detailed']) && $_GET['detailed'] === 'true';
            $includeRecent = isset($_GET['include_recent']) && $_GET['include_recent'] === 'true';
            $recentLimit = isset($_GET['recent_limit']) ? (int)$_GET['recent_limit'] : 10;
            $recentLimit = min(max($recentLimit, 1), 50);
            
            $stats = $this->getVectorStats();
            
            if ($detailed) {
                $stats['detailed'] = $this->getDetailedAnalysis();
            }
            
            $stats['categories'] = $this->getCategoryDistribution();
            $stats['trends'] = $this->getVectorTrends(7);
            
            if ($includeRecent) {
                $stats['recent_vectors'] = $this->getRecentVectors($recentLimit);
            }
            
            $stats['generated_at'] = date('Y-m-d H:i:s');
            
            $this->sendResponse(true, 'Vector stats retrieved successfully', $stats);
            
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            $this->sendResponse(false, 'Error: ' . $e->getMessage());
        }
    }
    
    private function getVectorStats()
    {
        $stats = [
            'total_vectors' => 0,
            'total_dimensions' => 0,
            'total_size_bytes' => 0,
            'total_size_human' => '0 B',
            'avg_similarity' => 0,
            'indexed_count' => 0,
            'last_indexed' => null,
            'vector_store_enabled' => false,
            'max_vectors' => 10000,
            'usage_percent' => 0
        ];
        
        // Vector klasörü yoksa
        if (!is_dir($this->vectorDir)) {
            $stats['message'] = 'Vector directory does not exist';
            return $stats;
        }
        
        // Vector store'un aktif olup olmadığını kontrol et
        $configFile = $this->moduleDir . '/config/vector.json';
        if (file_exists($configFile)) {
            $content = @file_get_contents($configFile);
            if ($content !== false) {
                $config = json_decode($content, true);
                $stats['vector_store_enabled'] = $config['enabled'] ?? false;
                $stats['max_vectors'] = $config['max_vectors'] ?? 10000;
            }
        }
        
        // memories.json dosyasını oku
        $memoriesFile = $this->vectorDir . '/memories.json';
        if (file_exists($memoriesFile)) {
            $content = @file_get_contents($memoriesFile);
            if ($content !== false) {
                $memories = json_decode($content, true);
                if (is_array($memories)) {
                    $stats['total_vectors'] = count($memories);
                    
                    $totalSimilarity = 0;
                    $totalSize = 0;
                    $lastTime = 0;
                    $indexedCount = 0;
                    
                    foreach ($memories as $memory) {
                        // Benzerlik skoru
                        if (isset($memory['similarity'])) {
                            $totalSimilarity += (float)$memory['similarity'];
                        }
                        
                        // Vektör boyutu (eğer varsa)
                        if (isset($memory['vector']) && is_array($memory['vector'])) {
                            $dimension = count($memory['vector']);
                            if ($dimension > $stats['total_dimensions']) {
                                $stats['total_dimensions'] = $dimension;
                            }
                        }
                        
                        // İndekslenmiş mi?
                        if (isset($memory['indexed']) && $memory['indexed'] === true) {
                            $indexedCount++;
                        }
                        
                        // Son eklenme zamanı
                        $createdAt = isset($memory['created_at']) ? (int)$memory['created_at'] : 0;
                        if ($createdAt > $lastTime) {
                            $lastTime = $createdAt;
                        }
                        
                        // Tahmini boyut (her vektör için yaklaşık hesaplama)
                        $totalSize += $this->estimateVectorSize($memory);
                    }
                    
                    $stats['avg_similarity'] = $stats['total_vectors'] > 0 
                        ? round($totalSimilarity / $stats['total_vectors'], 4) 
                        : 0;
                    
                    $stats['total_size_bytes'] = $totalSize;
                    $stats['total_size_human'] = $this->formatBytes($totalSize);
                    $stats['indexed_count'] = $indexedCount;
                    $stats['last_indexed'] = $lastTime > 0 ? $lastTime : null;
                    
                    // Doluluk oranı
                    $stats['usage_percent'] = round(($stats['total_vectors'] / $stats['max_vectors']) * 100, 2);
                }
            }
        }
        
        // index_stats.json dosyasını oku (varsa)
        $indexStatsFile = $this->vectorDir . '/index_stats.json';
        if (file_exists($indexStatsFile)) {
            $content = @file_get_contents($indexStatsFile);
            if ($content !== false) {
                $indexStats = json_decode($content, true);
                if (is_array($indexStats)) {
                    if (isset($indexStats['total_vectors'])) {
                        $stats['total_vectors'] = max($stats['total_vectors'], $indexStats['total_vectors']);
                    }
                    if (isset($indexStats['dimension'])) {
                        $stats['total_dimensions'] = max($stats['total_dimensions'], $indexStats['dimension']);
                    }
                    if (isset($stats['last_indexed']) && isset($indexStats['last_updated'])) {
                        $stats['last_indexed'] = max($stats['last_indexed'], $indexStats['last_updated']);
                    }
                }
            }
        }
        
        return $stats;
    }
    
    private function getDetailedAnalysis()
    {
        $analysis = [
            'dimensions' => [],
            'similarity_distribution' => [],
            'memory_details' => [],
            'quality_score' => 0,
            'index_performance' => []
        ];
        
        $memoriesFile = $this->vectorDir . '/memories.json';
        if (!file_exists($memoriesFile)) {
            return $analysis;
        }
        
        $content = @file_get_contents($memoriesFile);
        if ($content === false) {
            return $analysis;
        }
        
        $memories = json_decode($content, true);
        if (!is_array($memories)) {
            return $analysis;
        }
        
        // Boyut dağılımı
        $dimensionCounts = [];
        $similarities = [];
        $totalQuality = 0;
        
        foreach ($memories as $memory) {
            // Boyut analizi
            if (isset($memory['vector']) && is_array($memory['vector'])) {
                $dim = count($memory['vector']);
                $dimensionCounts[$dim] = ($dimensionCounts[$dim] ?? 0) + 1;
            }
            
            // Benzerlik dağılımı
            if (isset($memory['similarity'])) {
                $sim = (float)$memory['similarity'];
                $similarities[] = $sim;
                
                // Kalite skoru: benzerlik yüksekse kalite yüksek
                $totalQuality += $sim;
            }
        }
        
        $analysis['dimensions'] = $dimensionCounts;
        
        // Benzerlik dağılımı (aralıklara göre)
        $buckets = [0, 0.2, 0.4, 0.6, 0.8, 1.0];
        $distribution = array_fill(0, count($buckets) - 1, 0);
        
        foreach ($similarities as $sim) {
            for ($i = 0; $i < count($buckets) - 1; $i++) {
                if ($sim >= $buckets[$i] && $sim < $buckets[$i + 1]) {
                    $distribution[$i]++;
                    break;
                }
            }
        }
        
        $analysis['similarity_distribution'] = [
            '0-20' => $distribution[0],
            '20-40' => $distribution[1],
            '40-60' => $distribution[2],
            '60-80' => $distribution[3],
            '80-100' => $distribution[4]
        ];
        
        // Hafıza detayları
        $analysis['memory_details'] = $this->getMemoryDetails();
        
        // Kalite skoru (0-100 arası)
        $analysis['quality_score'] = count($similarities) > 0 
            ? round(($totalQuality / count($similarities)) * 100, 2)
            : 0;
        
        // İndeks performansı
        $analysis['index_performance'] = $this->getIndexPerformance();
        
        return $analysis;
    }
    
    private function getMemoryDetails()
    {
        $details = [
            'vectors_file_size' => 0,
            'index_file_size' => 0,
            'cache_file_size' => 0,
            'total_memory_usage' => 0,
            'estimated_ram_usage' => 0
        ];
        
        // memories.json boyutu
        $memoriesFile = $this->vectorDir . '/memories.json';
        if (file_exists($memoriesFile)) {
            $details['vectors_file_size'] = @filesize($memoriesFile) ?: 0;
        }
        
        // index.bin boyutu (varsa)
        $indexFile = $this->vectorDir . '/index.bin';
        if (file_exists($indexFile)) {
            $details['index_file_size'] = @filesize($indexFile) ?: 0;
        }
        
        // cache dosyası
        $cacheFile = $this->vectorDir . '/cache.json';
        if (file_exists($cacheFile)) {
            $details['cache_file_size'] = @filesize($cacheFile) ?: 0;
        }
        
        $details['total_memory_usage'] = $details['vectors_file_size'] + 
                                          $details['index_file_size'] + 
                                          $details['cache_file_size'];
        
        // Tahmini RAM kullanımı (dosya boyutunun ~3 katı)
        $details['estimated_ram_usage'] = $details['total_memory_usage'] * 3;
        
        // Human readable format
        $details['vectors_file_size_human'] = $this->formatBytes($details['vectors_file_size']);
        $details['index_file_size_human'] = $this->formatBytes($details['index_file_size']);
        $details['total_memory_usage_human'] = $this->formatBytes($details['total_memory_usage']);
        $details['estimated_ram_usage_human'] = $this->formatBytes($details['estimated_ram_usage']);
        
        return $details;
    }
    
    private function getIndexPerformance()
    {
        $performance = [
            'index_type' => 'flat_l2',
            'search_speed_ms' => 0,
            'index_build_time_ms' => 0,
            'last_reindex' => null,
            'cache_hit_rate' => 0
        ];
        
        $indexStatsFile = $this->vectorDir . '/index_stats.json';
        if (file_exists($indexStatsFile)) {
            $content = @file_get_contents($indexStatsFile);
            if ($content !== false) {
                $stats = json_decode($content, true);
                if (is_array($stats)) {
                    $performance['index_type'] = $stats['index_type'] ?? 'flat_l2';
                    $performance['search_speed_ms'] = $stats['avg_search_time_ms'] ?? 0;
                    $performance['index_build_time_ms'] = $stats['build_time_ms'] ?? 0;
                    $performance['last_reindex'] = $stats['last_reindex'] ?? null;
                    $performance['cache_hit_rate'] = $stats['cache_hit_rate'] ?? 0;
                }
            }
        }
        
        return $performance;
    }
    
    private function getCategoryDistribution()
    {
        $categories = [];
        
        $memoriesFile = $this->vectorDir . '/memories.json';
        if (!file_exists($memoriesFile)) {
            return $categories;
        }
        
        $content = @file_get_contents($memoriesFile);
        if ($content === false) {
            return $categories;
        }
        
        $memories = json_decode($content, true);
        if (!is_array($memories)) {
            return $categories;
        }
        
        $total = 0;
        foreach ($memories as $memory) {
            $category = isset($memory['category']) ? $memory['category'] : 'general';
            if (!in_array($category, $this->validCategories)) {
                $category = 'custom';
            }
            
            if (!isset($categories[$category])) {
                $categories[$category] = [
                    'count' => 0,
                    'avg_similarity' => 0,
                    'total_similarity' => 0
                ];
            }
            
            $categories[$category]['count']++;
            $total++;
            
            if (isset($memory['similarity'])) {
                $categories[$category]['total_similarity'] += (float)$memory['similarity'];
            }
        }
        
        // Ortalama benzerlik ve yüzde hesapla
        foreach ($categories as $cat => &$data) {
            if ($data['count'] > 0) {
                $data['avg_similarity'] = round($data['total_similarity'] / $data['count'], 4);
                $data['percentage'] = round(($data['count'] / $total) * 100, 2);
            }
            unset($data['total_similarity']);
        }
        
        return $categories;
    }
    
    private function getVectorTrends($days = 7)
    {
        $trends = [];
        $dailyStatsFile = $this->vectorDir . '/daily_stats.json';
        
        $dailyStats = [];
        if (file_exists($dailyStatsFile)) {
            $content = @file_get_contents($dailyStatsFile);
            if ($content !== false) {
                $dailyStats = json_decode($content, true);
                if (!is_array($dailyStats)) {
                    $dailyStats = [];
                }
            }
        }
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            
            $trends[] = [
                'date' => $date,
                'new_vectors' => $dailyStats[$date]['new'] ?? 0,
                'searches' => $dailyStats[$date]['searches'] ?? 0,
                'avg_similarity' => $dailyStats[$date]['avg_similarity'] ?? 0
            ];
        }
        
        return $trends;
    }
    
    private function getRecentVectors($limit = 10)
    {
        $recent = [];
        
        $memoriesFile = $this->vectorDir . '/memories.json';
        if (!file_exists($memoriesFile)) {
            return $recent;
        }
        
        $content = @file_get_contents($memoriesFile);
        if ($content === false) {
            return $recent;
        }
        
        $memories = json_decode($content, true);
        if (!is_array($memories)) {
            return $recent;
        }
        
        // Tarihe göre sırala (en yeni önce)
        usort($memories, function($a, $b) {
            $timeA = $a['created_at'] ?? 0;
            $timeB = $b['created_at'] ?? 0;
            return $timeB - $timeA;
        });
        
        // Limit uygula
        $memories = array_slice($memories, 0, $limit);
        
        foreach ($memories as $memory) {
            $recent[] = [
                'id' => $memory['id'] ?? md5(json_encode($memory)),
                'content' => isset($memory['content']) ? substr($memory['content'], 0, 200) : '',
                'category' => $memory['category'] ?? 'general',
                'similarity' => $memory['similarity'] ?? 0,
                'created_at' => $memory['created_at'] ?? null,
                'metadata' => $memory['metadata'] ?? []
            ];
        }
        
        return $recent;
    }
    
    private function estimateVectorSize($memory)
    {
        $size = 0;
        
        // Content boyutu
        if (isset($memory['content'])) {
            $size += strlen($memory['content']);
        }
        
        // Vector boyutu (her float ~8 byte)
        if (isset($memory['vector']) && is_array($memory['vector'])) {
            $size += count($memory['vector']) * 8;
        }
        
        // Metadata
        if (isset($memory['metadata'])) {
            $size += strlen(json_encode($memory['metadata']));
        }
        
        return $size;
    }
    
    private function formatBytes($bytes, $precision = 2)
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen((string)$bytes) - 1) / 3);
        $factor = min($factor, count($units) - 1);
        
        $bytes /= pow(1024, $factor);
        
        return round($bytes, $precision) . ' ' . $units[$factor];
    }
    
    private function logError($message)
    {
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
        
        $logEntry = '[' . date('Y-m-d H:i:s') . '] [ERROR] [get-vector-stats] ' . $message . PHP_EOL;
        @file_put_contents($this->logDir . '/api-master.log', $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function sendResponse($success, $message, $data = [])
    {
        header('Content-Type: application/json');
        $response = [
            'success' => $success,
            'message' => $message,
            'timestamp' => time()
        ];
        
        if (!empty($data)) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$instance = new APIMaster_GetVectorStats();
$instance->execute();