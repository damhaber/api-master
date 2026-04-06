<?php
/**
 * get-learning-stats.php - AJAX Endpoint for Masal Panel
 * Get learning system statistics from local JSON storage
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);

class APIMaster_GetLearningStats
{
    private $moduleDir;
    private $learningDir;
    private $logDir;
    private $statsFile;
    
    private $validCategories = ['general', 'api_pattern', 'error_pattern', 'success_pattern', 'custom'];
    
    public function __construct()
    {
        $this->moduleDir = defined('API_MASTER_MODULE_DIR') 
            ? API_MASTER_MODULE_DIR 
            : dirname(__DIR__);
        
        $this->learningDir = $this->moduleDir . '/data/learning';
        $this->logDir = $this->moduleDir . '/logs';
        $this->statsFile = $this->learningDir . '/stats.json';
    }
    
    public function execute()
    {
        try {
            $includePatterns = isset($_GET['include_patterns']) && $_GET['include_patterns'] === 'true';
            $patternLimit = isset($_GET['pattern_limit']) ? (int)$_GET['pattern_limit'] : 50;
            $patternLimit = min(max($patternLimit, 1), 200); // 1-200 arası
            
            $stats = $this->getLearningStats();
            
            if ($includePatterns) {
                $stats['patterns'] = $this->getPatternList($patternLimit);
            }
            
            $stats['categories'] = $this->getCategoryStats();
            $stats['trends'] = $this->getLearningTrends(7);
            $stats['generated_at'] = date('Y-m-d H:i:s');
            
            $this->sendResponse(true, 'Learning stats retrieved successfully', $stats);
            
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            $this->sendResponse(false, 'Error: ' . $e->getMessage());
        }
    }
    
    private function getLearningStats()
    {
        $stats = [
            'total_learnings' => 0,
            'active_patterns' => 0,
            'total_interactions' => 0,
            'success_rate' => 0,
            'avg_confidence' => 0,
            'avg_response_time' => 0,
            'category_count' => [],
            'last_update' => null,
            'learning_enabled' => false
        ];
        
        // Learning klasörü yoksa
        if (!is_dir($this->learningDir)) {
            $stats['message'] = 'Learning directory does not exist';
            return $stats;
        }
        
        // Öğrenme sisteminin aktif olup olmadığını kontrol et
        $configFile = $this->moduleDir . '/config/learning.json';
        if (file_exists($configFile)) {
            $content = @file_get_contents($configFile);
            if ($content !== false) {
                $config = json_decode($content, true);
                $stats['learning_enabled'] = $config['enabled'] ?? false;
            }
        }
        
        // Tüm learning JSON dosyalarını tara
        $files = @glob($this->learningDir . '/*.json');
        
        if ($files === false || empty($files)) {
            return $stats;
        }
        
        $totalSuccessRate = 0;
        $totalConfidence = 0;
        $totalResponseTime = 0;
        $validEntries = 0;
        $lastUpdate = 0;
        
        foreach ($files as $file) {
            // learning_ ve pattern_ dosyalarını filtrele
            $filename = basename($file);
            if (strpos($filename, 'learning_') !== 0 && 
                strpos($filename, 'pattern_') !== 0 &&
                $filename !== 'stats.json') {
                continue;
            }
            
            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }
            
            $data = json_decode($content, true);
            if (!is_array($data)) {
                continue;
            }
            
            $stats['total_learnings']++;
            
            // Kullanım sayısı
            $count = isset($data['count']) ? (int)$data['count'] : 1;
            if ($count > 0) {
                $stats['active_patterns']++;
            }
            
            $stats['total_interactions'] += $count;
            
            // Başarı oranı
            $successRate = isset($data['success_rate']) ? (float)$data['success_rate'] : 100;
            $totalSuccessRate += $successRate;
            
            // Güven seviyesi
            $confidence = isset($data['confidence']) ? (float)$data['confidence'] : 50;
            $totalConfidence += $confidence;
            
            // Yanıt süresi
            $responseTime = isset($data['avg_response_time']) ? (float)$data['avg_response_time'] : 0;
            $totalResponseTime += $responseTime;
            
            // Kategori
            $category = isset($data['category']) ? $data['category'] : 'general';
            if (!in_array($category, $this->validCategories)) {
                $category = 'custom';
            }
            $stats['category_count'][$category] = ($stats['category_count'][$category] ?? 0) + 1;
            
            // Son güncelleme
            $lastUsed = isset($data['last_used']) ? (int)$data['last_used'] : 0;
            if ($lastUsed > $lastUpdate) {
                $lastUpdate = $lastUsed;
            }
            
            $validEntries++;
        }
        
        // Ortalamalar
        if ($validEntries > 0) {
            $stats['success_rate'] = round($totalSuccessRate / $validEntries, 2);
            $stats['avg_confidence'] = round($totalConfidence / $validEntries, 2);
            $stats['avg_response_time'] = round($totalResponseTime / $validEntries, 2);
        }
        
        $stats['last_update'] = $lastUpdate > 0 ? $lastUpdate : null;
        
        // Kategorileri sırala
        arsort($stats['category_count']);
        
        return $stats;
    }
    
    private function getPatternList($limit = 50)
    {
        $patterns = [];
        
        if (!is_dir($this->learningDir)) {
            return $patterns;
        }
        
        $files = @glob($this->learningDir . '/pattern_*.json');
        
        if ($files === false || empty($files)) {
            // Eski format: learning_ dosyaları
            $files = @glob($this->learningDir . '/learning_*.json');
        }
        
        if ($files === false || empty($files)) {
            return $patterns;
        }
        
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }
            
            $data = json_decode($content, true);
            if (!is_array($data)) {
                continue;
            }
            
            $patternName = $data['pattern'] ?? $data['question'] ?? basename($file, '.json');
            
            $patterns[] = [
                'id' => md5($file),
                'pattern' => substr($patternName, 0, 200),
                'count' => $data['count'] ?? 1,
                'success_rate' => $data['success_rate'] ?? 100,
                'confidence' => $data['confidence'] ?? 50,
                'category' => $data['category'] ?? 'general',
                'last_used' => $data['last_used'] ?? null,
                'response_time' => $data['avg_response_time'] ?? 0
            ];
        }
        
        // Kullanım sayısına göre sırala (en çok kullanılan önce)
        usort($patterns, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        return array_slice($patterns, 0, $limit);
    }
    
    private function getCategoryStats()
    {
        $categories = [];
        
        if (!is_dir($this->learningDir)) {
            return $categories;
        }
        
        // Her kategori için ayrı JSON dosyası
        foreach ($this->validCategories as $category) {
            $categoryFile = $this->learningDir . '/category_' . $category . '.json';
            
            if (file_exists($categoryFile)) {
                $content = @file_get_contents($categoryFile);
                if ($content !== false) {
                    $data = json_decode($content, true);
                    if (is_array($data)) {
                        $categories[$category] = [
                            'total_patterns' => $data['total_patterns'] ?? 0,
                            'total_interactions' => $data['total_interactions'] ?? 0,
                            'avg_success_rate' => $data['avg_success_rate'] ?? 0,
                            'avg_confidence' => $data['avg_confidence'] ?? 0
                        ];
                        continue;
                    }
                }
            }
            
            // Varsayılan değerler
            $categories[$category] = [
                'total_patterns' => 0,
                'total_interactions' => 0,
                'avg_success_rate' => 0,
                'avg_confidence' => 0
            ];
        }
        
        return $categories;
    }
    
    private function getLearningTrends($days = 7)
    {
        $trends = [];
        $trendsFile = $this->learningDir . '/daily_trends.json';
        
        if (file_exists($trendsFile)) {
            $content = @file_get_contents($trendsFile);
            if ($content !== false) {
                $savedTrends = json_decode($content, true);
                if (is_array($savedTrends)) {
                    // Kaydedilmiş trendleri filtrele
                    $startDate = date('Y-m-d', strtotime("-$days days"));
                    foreach ($savedTrends as $trend) {
                        if (isset($trend['date']) && $trend['date'] >= $startDate) {
                            $trends[] = $trend;
                        }
                    }
                }
            }
        }
        
        // Eksik günleri doldur
        $trends = $this->fillMissingTrendDays($trends, $days);
        
        // Tarihe göre sırala
        usort($trends, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        
        return $trends;
    }
    
    private function fillMissingTrendDays($trends, $days)
    {
        $existingDates = array_column($trends, 'date');
        $filled = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            
            if (in_array($date, $existingDates)) {
                $index = array_search($date, $existingDates);
                $filled[] = $trends[$index];
            } else {
                $filled[] = [
                    'date' => $date,
                    'interactions' => 0,
                    'new_patterns' => 0,
                    'success_rate' => 0,
                    'avg_confidence' => 0
                ];
            }
        }
        
        return $filled;
    }
    
    private function logError($message)
    {
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
        
        $logEntry = '[' . date('Y-m-d H:i:s') . '] [ERROR] [get-learning-stats] ' . $message . PHP_EOL;
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

$instance = new APIMaster_GetLearningStats();
$instance->execute();