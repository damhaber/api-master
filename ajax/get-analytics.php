<?php
/**
 * get-analytics.php - AJAX Endpoint for Masal Panel
 * Get API usage analytics and statistics
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);

class APIMaster_GetAnalytics
{
    private $moduleDir;
    private $statsDir;
    private $logDir;
    private $configDir;
    
    private $validPeriods = ['24h', '7d', '30d', '90d', 'all'];
    
    public function __construct()
    {
        $this->moduleDir = defined('API_MASTER_MODULE_DIR') 
            ? API_MASTER_MODULE_DIR 
            : dirname(__DIR__);
        
        $this->statsDir = $this->moduleDir . '/data/stats';
        $this->logDir = $this->moduleDir . '/logs';
        $this->configDir = $this->moduleDir . '/config';
    }
    
    public function execute()
    {
        try {
            $period = $this->sanitizePeriod($_GET['period'] ?? '7d');
            $includeDetailed = ($_GET['detailed'] ?? 'false') === 'true';
            $includeChartData = ($_GET['chart_data'] ?? 'true') === 'true';
            
            $analytics = [];
            
            // Temel istatistikler
            $analytics['basic'] = $this->getBasicStats($period);
            
            // Provider istatistikleri
            $analytics['providers'] = $this->getProviderStats($period);
            
            // Günlük istatistikler (grafik için)
            if ($includeChartData) {
                $analytics['daily'] = $this->getDailyStats($period);
            }
            
            // Başarı oranı ve trendler
            $analytics['success_rate'] = $this->calculateSuccessRate($analytics['basic']);
            $analytics['trends'] = $this->calculateTrends($analytics['daily'] ?? []);
            
            // Detaylı analiz (isteğe bağlı)
            if ($includeDetailed) {
                $analytics['detailed'] = $this->getDetailedAnalytics($period);
            }
            
            // Özet bilgiler
            $analytics['summary'] = $this->generateSummary($analytics);
            $analytics['period'] = $period;
            $analytics['generated_at'] = date('Y-m-d H:i:s');
            
            $this->sendResponse(true, 'Analytics retrieved successfully', $analytics);
            
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            $this->sendResponse(false, 'Analytics error: ' . $e->getMessage());
        }
    }
    
    private function getBasicStats($period)
    {
        $stats = [
            'total_calls' => 0,
            'successful_calls' => 0,
            'failed_calls' => 0,
            'avg_response_time' => 0,
            'total_tokens' => 0,
            'estimated_cost' => 0
        ];
        
        $analyticsFile = $this->statsDir . '/analytics.json';
        
        if (file_exists($analyticsFile)) {
            $content = @file_get_contents($analyticsFile);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $filtered = $this->filterByPeriod($data, $period);
                    
                    foreach ($filtered as $record) {
                        $stats['total_calls'] += $record['calls'] ?? 0;
                        $stats['successful_calls'] += $record['success'] ?? 0;
                        $stats['failed_calls'] += $record['failed'] ?? 0;
                        $stats['total_tokens'] += $record['tokens'] ?? 0;
                        $stats['estimated_cost'] += $record['cost'] ?? 0;
                    }
                    
                    // Ortalama yanıt süresi hesapla
                    $totalTime = 0;
                    $count = 0;
                    foreach ($filtered as $record) {
                        if (isset($record['avg_time']) && isset($record['calls'])) {
                            $totalTime += $record['avg_time'] * $record['calls'];
                            $count += $record['calls'];
                        }
                    }
                    $stats['avg_response_time'] = $count > 0 ? round($totalTime / $count, 2) : 0;
                }
            }
        }
        
        return $stats;
    }
    
    private function getProviderStats($period)
    {
        $providers = [];
        $providersFile = $this->statsDir . '/providers.json';
        
        if (file_exists($providersFile)) {
            $content = @file_get_contents($providersFile);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    foreach ($data as $provider => $stats) {
                        $providers[$provider] = [
                            'total_calls' => $stats['total_calls'] ?? 0,
                            'success_calls' => $stats['success_calls'] ?? 0,
                            'failed_calls' => $stats['failed_calls'] ?? 0,
                            'avg_response_time' => isset($stats['avg_response_time']) ? round($stats['avg_response_time'], 2) : 0,
                            'success_rate' => isset($stats['success_rate']) ? round($stats['success_rate'], 2) : 100,
                            'last_used' => $stats['last_test'] ?? null
                        ];
                    }
                }
            }
        }
        
        // Provider listesi yoksa config'den oku
        if (empty($providers)) {
            $providers = $this->getProvidersFromConfig();
        }
        
        return $providers;
    }
    
    private function getProvidersFromConfig()
    {
        $providers = [];
        $configPattern = $this->configDir . '/*.json';
        
        $configFiles = glob($configPattern);
        foreach ($configFiles as $file) {
            $provider = basename($file, '.json');
            if ($provider !== 'api-master') {
                $content = @file_get_contents($file);
                if ($content !== false) {
                    $config = json_decode($content, true);
                    $providers[$provider] = [
                        'total_calls' => 0,
                        'success_calls' => 0,
                        'failed_calls' => 0,
                        'avg_response_time' => 0,
                        'success_rate' => 100,
                        'active' => $config['active'] ?? false,
                        'last_used' => null
                    ];
                }
            }
        }
        
        return $providers;
    }
    
    private function getDailyStats($period)
    {
        $daily = [];
        $days = $this->getDaysFromPeriod($period);
        $startDate = date('Y-m-d', strtotime("-$days days"));
        
        $dailyFile = $this->statsDir . '/daily.json';
        
        if (file_exists($dailyFile)) {
            $content = @file_get_contents($dailyFile);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    foreach ($data as $date => $stats) {
                        if ($date >= $startDate) {
                            $daily[$date] = [
                                'calls' => $stats['calls'] ?? 0,
                                'success' => $stats['success'] ?? 0,
                                'failed' => $stats['failed'] ?? 0,
                                'avg_time' => isset($stats['avg_time']) ? round($stats['avg_time'], 2) : 0,
                                'tokens' => $stats['tokens'] ?? 0
                            ];
                        }
                    }
                }
            }
        }
        
        // Eksik günleri doldur
        $daily = $this->fillMissingDays($daily, $days);
        ksort($daily);
        
        return $daily;
    }
    
    private function fillMissingDays($daily, $days)
    {
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            if (!isset($daily[$date])) {
                $daily[$date] = [
                    'calls' => 0,
                    'success' => 0,
                    'failed' => 0,
                    'avg_time' => 0,
                    'tokens' => 0
                ];
            }
        }
        return $daily;
    }
    
    private function calculateSuccessRate($basic)
    {
        $rate = 100;
        if (($basic['total_calls'] ?? 0) > 0) {
            $rate = round(($basic['successful_calls'] / $basic['total_calls']) * 100, 2);
        }
        
        return [
            'rate' => $rate,
            'total' => $basic['total_calls'] ?? 0,
            'successful' => $basic['successful_calls'] ?? 0,
            'failed' => $basic['failed_calls'] ?? 0
        ];
    }
    
    private function calculateTrends($daily)
    {
        $trends = [];
        
        if (empty($daily)) {
            return $trends;
        }
        
        $dates = array_keys($daily);
        $calls = array_column($daily, 'calls');
        
        // Trend hesapla
        $half = floor(count($calls) / 2);
        $firstHalf = array_slice($calls, 0, $half);
        $secondHalf = array_slice($calls, $half);
        
        $avgFirst = count($firstHalf) > 0 ? array_sum($firstHalf) / count($firstHalf) : 0;
        $avgSecond = count($secondHalf) > 0 ? array_sum($secondHalf) / count($secondHalf) : 0;
        
        $trends['call_trend'] = $avgFirst > 0 ? round((($avgSecond - $avgFirst) / $avgFirst) * 100, 2) : 0;
        
        // En yüksek/en düşük
        $maxCalls = max($calls);
        $minCalls = min($calls);
        
        $trends['peak_day'] = $maxCalls > 0 ? $dates[array_search($maxCalls, $calls)] : null;
        $trends['lowest_day'] = $minCalls > 0 ? $dates[array_search($minCalls, $calls)] : null;
        $trends['peak_calls'] = $maxCalls;
        $trends['lowest_calls'] = $minCalls;
        
        return $trends;
    }
    
    private function getDetailedAnalytics($period)
    {
        $detailed = [];
        
        $hourlyFile = $this->statsDir . '/hourly.json';
        if (file_exists($hourlyFile)) {
            $content = @file_get_contents($hourlyFile);
            if ($content !== false) {
                $detailed['hourly'] = json_decode($content, true);
            }
        }
        
        $tokenFile = $this->statsDir . '/tokens.json';
        if (file_exists($tokenFile)) {
            $content = @file_get_contents($tokenFile);
            if ($content !== false) {
                $detailed['tokens'] = json_decode($content, true);
            }
        }
        
        $errorFile = $this->statsDir . '/errors.json';
        if (file_exists($errorFile)) {
            $content = @file_get_contents($errorFile);
            if ($content !== false) {
                $detailed['errors'] = json_decode($content, true);
            }
        }
        
        return $detailed;
    }
    
    private function generateSummary($analytics)
    {
        $summary = [];
        
        // En çok kullanılan provider
        $maxCalls = 0;
        $topProvider = null;
        
        foreach ($analytics['providers'] as $name => $data) {
            if (($data['total_calls'] ?? 0) > $maxCalls) {
                $maxCalls = $data['total_calls'];
                $topProvider = $name;
            }
        }
        
        $summary['top_provider'] = $topProvider;
        $summary['top_provider_calls'] = $maxCalls;
        
        // En hızlı provider
        $minTime = PHP_FLOAT_MAX;
        $fastestProvider = null;
        
        foreach ($analytics['providers'] as $name => $data) {
            $time = $data['avg_response_time'] ?? 0;
            if ($time > 0 && $time < $minTime) {
                $minTime = $time;
                $fastestProvider = $name;
            }
        }
        
        $summary['fastest_provider'] = $fastestProvider;
        $summary['fastest_time'] = $minTime !== PHP_FLOAT_MAX ? $minTime : 0;
        
        // Tahmini maliyet
        $summary['estimated_cost'] = isset($analytics['basic']['estimated_cost']) ? 
            round($analytics['basic']['estimated_cost'], 4) : 0;
        
        return $summary;
    }
    
    private function filterByPeriod($data, $period)
    {
        $days = $this->getDaysFromPeriod($period);
        $startDate = date('Y-m-d', strtotime("-$days days"));
        
        $filtered = [];
        foreach ($data as $date => $record) {
            if ($date >= $startDate) {
                $filtered[$date] = $record;
            }
        }
        
        return $filtered;
    }
    
    private function getDaysFromPeriod($period)
    {
        switch ($period) {
            case '24h': return 1;
            case '7d': return 7;
            case '30d': return 30;
            case '90d': return 90;
            case 'all': return 3650;
            default: return 7;
        }
    }
    
    private function sanitizePeriod($period)
    {
        $period = strtolower(trim($period));
        
        // Sadece alfanumeric karakterler
        $period = preg_replace('/[^a-z0-9]/', '', $period);
        
        if (!in_array($period, $this->validPeriods)) {
            return '7d';
        }
        
        return $period;
    }
    
    private function logError($message)
    {
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
        
        $logEntry = '[' . date('Y-m-d H:i:s') . '] [ERROR] [get-analytics] ' . $message . PHP_EOL;
        @file_put_contents($this->logDir . '/api-master.log', $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function sendResponse($success, $message, $data = [])
    {
        header('Content-Type: application/json');
        $response = ['success' => $success, 'message' => $message];
        if (!empty($data)) {
            $response['data'] = $data;
        }
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$instance = new APIMaster_GetAnalytics();
$instance->execute();