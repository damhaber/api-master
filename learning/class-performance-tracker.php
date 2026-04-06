<?php
/**
 * APIMaster Performance Tracker
 * 
 * Performans takip ve analiz sistemi
 * 
 * @package APIMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_PerformanceTracker {
    
    /**
     * @var array Performans konfigürasyonu
     */
    private $config;
    
    /**
     * @var array Performans metrikleri
     */
    private $metrics = [];
    
    /**
     * @var array Alert kuralları
     */
    private $alert_rules = [];
    
    /**
     * @var string Performans yolu
     */
    private $performance_path;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->performance_path = APIMASTER_PATH . 'data/performance/';
        $this->initPerformanceSystem();
    }
    
    /**
     * Performans sistemini başlat
     */
    private function initPerformanceSystem() {
        if (!file_exists($this->performance_path)) {
            mkdir($this->performance_path, 0755, true);
        }
        
        $this->loadConfig();
        $this->loadMetrics();
        $this->loadAlertRules();
    }
    
    /**
     * Konfigürasyonu yükle
     */
    private function loadConfig() {
        $config_file = $this->performance_path . 'config.json';
        
        if (file_exists($config_file)) {
            $this->config = json_decode(file_get_contents($config_file), true);
        } else {
            $this->config = $this->getDefaultConfig();
            $this->saveConfig();
        }
    }
    
    /**
     * Varsayılan konfigürasyon
     */
    private function getDefaultConfig() {
        return [
            'retention_days' => 30,
            'aggregation_interval' => 300, // 5 dakika
            'alert_cooldown' => 3600, // 1 saat
            'metrics' => [
                'response_time' => ['enabled' => true, 'threshold' => 2000],
                'success_rate' => ['enabled' => true, 'threshold' => 95],
                'error_rate' => ['enabled' => true, 'threshold' => 5],
                'throughput' => ['enabled' => true, 'threshold' => 100],
                'cpu_usage' => ['enabled' => false, 'threshold' => 80],
                'memory_usage' => ['enabled' => false, 'threshold' => 256]
            ]
        ];
    }
    
    /**
     * Konfigürasyonu kaydet
     */
    private function saveConfig() {
        file_put_contents(
            $this->performance_path . 'config.json',
            json_encode($this->config, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Metrikleri yükle
     */
    private function loadMetrics() {
        $metrics_file = $this->performance_path . 'metrics.json';
        
        if (file_exists($metrics_file)) {
            $this->metrics = json_decode(file_get_contents($metrics_file), true);
        } else {
            $this->metrics = [];
            $this->saveMetrics();
        }
    }
    
    /**
     * Metrikleri kaydet
     */
    private function saveMetrics() {
        // Eski metrikleri temizle
        $this->cleanOldMetrics();
        
        file_put_contents(
            $this->performance_path . 'metrics.json',
            json_encode($this->metrics, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Eski metrikleri temizle
     */
    private function cleanOldMetrics() {
        $cutoff = time() - ($this->config['retention_days'] * 24 * 3600);
        
        foreach ($this->metrics as $key => $metric) {
            if ($metric['timestamp'] < $cutoff) {
                unset($this->metrics[$key]);
            }
        }
    }
    
    /**
     * Alert kurallarını yükle
     */
    private function loadAlertRules() {
        $rules_file = $this->performance_path . 'alert_rules.json';
        
        if (file_exists($rules_file)) {
            $this->alert_rules = json_decode(file_get_contents($rules_file), true);
        } else {
            $this->alert_rules = $this->getDefaultAlertRules();
            $this->saveAlertRules();
        }
    }
    
    /**
     * Varsayılan alert kuralları
     */
    private function getDefaultAlertRules() {
        return [
            'high_response_time' => [
                'metric' => 'response_time',
                'operator' => '>',
                'value' => 3000,
                'severity' => 'warning',
                'enabled' => true
            ],
            'critical_response_time' => [
                'metric' => 'response_time',
                'operator' => '>',
                'value' => 5000,
                'severity' => 'critical',
                'enabled' => true
            ],
            'low_success_rate' => [
                'metric' => 'success_rate',
                'operator' => '<',
                'value' => 90,
                'severity' => 'warning',
                'enabled' => true
            ],
            'critical_success_rate' => [
                'metric' => 'success_rate',
                'operator' => '<',
                'value' => 80,
                'severity' => 'critical',
                'enabled' => true
            ],
            'high_error_rate' => [
                'metric' => 'error_rate',
                'operator' => '>',
                'value' => 10,
                'severity' => 'warning',
                'enabled' => true
            ],
            'critical_error_rate' => [
                'metric' => 'error_rate',
                'operator' => '>',
                'value' => 20,
                'severity' => 'critical',
                'enabled' => true
            ]
        ];
    }
    
    /**
     * Alert kurallarını kaydet
     */
    private function saveAlertRules() {
        file_put_contents(
            $this->performance_path . 'alert_rules.json',
            json_encode($this->alert_rules, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Performans metriği kaydet
     * 
     * @param string $endpoint Endpoint
     * @param array $data Metrik verileri
     * @return string Metric ID
     */
    public function recordMetrics($endpoint, $data) {
        $metric_id = uniqid('perf_', true);
        
        $metric = [
            'id' => $metric_id,
            'endpoint' => $endpoint,
            'timestamp' => time(),
            'data' => $data,
            'aggregated' => false
        ];
        
        $this->metrics[$metric_id] = $metric;
        
        // Otomatik aggregasyon kontrolü
        if (count($this->metrics) >= 100) {
            $this->aggregateMetrics();
        }
        
        $this->saveMetrics();
        
        // Alert kontrolü
        $this->checkAlerts($metric);
        
        return $metric_id;
    }
    
    /**
     * API çağrısı performansını kaydet
     * 
     * @param string $endpoint Endpoint
     * @param string $provider Provider
     * @param float $response_time Yanıt süresi (ms)
     * @param bool $success Başarılı mı?
     * @param array $additional Ek veriler
     */
    public function recordAPICall($endpoint, $provider, $response_time, $success, $additional = []) {
        $data = array_merge([
            'type' => 'api_call',
            'provider' => $provider,
            'response_time' => $response_time,
            'success' => $success,
            'timestamp' => time()
        ], $additional);
        
        return $this->recordMetrics($endpoint, $data);
    }
    
    /**
     * Metrikleri aggregate et
     */
    public function aggregateMetrics() {
        $aggregated = [];
        $now = time();
        $interval = $this->config['aggregation_interval'];
        $cutoff = $now - $interval;
        
        // Son interval'deki metrikleri topla
        $recent_metrics = array_filter($this->metrics, function($metric) use ($cutoff) {
            return !$metric['aggregated'] && $metric['timestamp'] >= $cutoff;
        });
        
        if (empty($recent_metrics)) {
            return false;
        }
        
        // Endpoint bazında grupla
        $grouped = [];
        foreach ($recent_metrics as $metric) {
            $endpoint = $metric['endpoint'];
            if (!isset($grouped[$endpoint])) {
                $grouped[$endpoint] = [];
            }
            $grouped[$endpoint][] = $metric;
        }
        
        // Her endpoint için aggregate hesapla
        foreach ($grouped as $endpoint => $metrics) {
            $aggregate = $this->calculateAggregate($metrics);
            $aggregated[$endpoint] = $aggregate;
            
            // Metrikleri aggregated olarak işaretle
            foreach ($metrics as $metric) {
                $this->metrics[$metric['id']]['aggregated'] = true;
            }
        }
        
        // Aggregate metrikleri kaydet
        if (!empty($aggregated)) {
            $this->saveAggregatedMetrics($aggregated, $now);
        }
        
        $this->saveMetrics();
        
        return $aggregated;
    }
    
    /**
     * Aggregate hesapla
     */
    private function calculateAggregate($metrics) {
        $total = count($metrics);
        $success_count = 0;
        $response_times = [];
        $provider_stats = [];
        
        foreach ($metrics as $metric) {
            $data = $metric['data'];
            
            if ($data['success']) {
                $success_count++;
            }
            
            if (isset($data['response_time'])) {
                $response_times[] = $data['response_time'];
            }
            
            if (isset($data['provider'])) {
                $provider = $data['provider'];
                if (!isset($provider_stats[$provider])) {
                    $provider_stats[$provider] = [
                        'count' => 0,
                        'success' => 0,
                        'total_time' => 0
                    ];
                }
                $provider_stats[$provider]['count']++;
                if ($data['success']) {
                    $provider_stats[$provider]['success']++;
                }
                if (isset($data['response_time'])) {
                    $provider_stats[$provider]['total_time'] += $data['response_time'];
                }
            }
        }
        
        // İstatistikleri hesapla
        $success_rate = ($total > 0) ? ($success_count / $total) * 100 : 0;
        $error_rate = 100 - $success_rate;
        
        $avg_response_time = !empty($response_times) ? array_sum($response_times) / count($response_times) : 0;
        $min_response_time = !empty($response_times) ? min($response_times) : 0;
        $max_response_time = !empty($response_times) ? max($response_times) : 0;
        
        // Percentile hesapla (95th)
        sort($response_times);
        $p95_index = (int)ceil(0.95 * count($response_times)) - 1;
        $p95_response_time = isset($response_times[$p95_index]) ? $response_times[$p95_index] : 0;
        
        // Provider performansı
        $provider_performance = [];
        foreach ($provider_stats as $provider => $stats) {
            $provider_performance[$provider] = [
                'total_calls' => $stats['count'],
                'success_rate' => ($stats['count'] > 0) ? ($stats['success'] / $stats['count']) * 100 : 0,
                'avg_response_time' => ($stats['count'] > 0) ? $stats['total_time'] / $stats['count'] : 0
            ];
        }
        
        return [
            'total_calls' => $total,
            'success_rate' => round($success_rate, 2),
            'error_rate' => round($error_rate, 2),
            'avg_response_time' => round($avg_response_time, 2),
            'min_response_time' => round($min_response_time, 2),
            'max_response_time' => round($max_response_time, 2),
            'p95_response_time' => round($p95_response_time, 2),
            'provider_stats' => $provider_performance
        ];
    }
    
    /**
     * Aggregate metrikleri kaydet
     */
    private function saveAggregatedMetrics($aggregated, $timestamp) {
        $aggregated_file = $this->performance_path . 'aggregated_' . date('Y-m-d_H', $timestamp) . '.json';
        
        $existing = [];
        if (file_exists($aggregated_file)) {
            $existing = json_decode(file_get_contents($aggregated_file), true);
        }
        
        $existing[$timestamp] = $aggregated;
        
        // Son 24 saati tut
        $keys = array_keys($existing);
        if (count($keys) > 288) { // 5 dakikalık interval ile 24 saat = 288 kayıt
            $oldest = min($keys);
            unset($existing[$oldest]);
        }
        
        file_put_contents($aggregated_file, json_encode($existing, JSON_PRETTY_PRINT));
    }
    
    /**
     * Alert kontrolü yap
     */
    private function checkAlerts($metric) {
        $data = $metric['data'];
        $alerts_triggered = [];
        
        foreach ($this->alert_rules as $rule_name => $rule) {
            if (!$rule['enabled']) {
                continue;
            }
            
            $metric_value = $data[$rule['metric']] ?? null;
            
            if ($metric_value !== null) {
                $triggered = false;
                
                switch ($rule['operator']) {
                    case '>':
                        $triggered = $metric_value > $rule['value'];
                        break;
                    case '<':
                        $triggered = $metric_value < $rule['value'];
                        break;
                    case '>=':
                        $triggered = $metric_value >= $rule['value'];
                        break;
                    case '<=':
                        $triggered = $metric_value <= $rule['value'];
                        break;
                }
                
                if ($triggered) {
                    $alerts_triggered[] = [
                        'rule' => $rule_name,
                        'metric' => $rule['metric'],
                        'value' => $metric_value,
                        'threshold' => $rule['value'],
                        'severity' => $rule['severity'],
                        'timestamp' => $metric['timestamp'],
                        'endpoint' => $metric['endpoint']
                    ];
                }
            }
        }
        
        if (!empty($alerts_triggered)) {
            $this->triggerAlerts($alerts_triggered);
        }
        
        return $alerts_triggered;
    }
    
    /**
     * Alertleri tetikle
     */
    private function triggerAlerts($alerts) {
        $alerts_file = $this->performance_path . 'alerts.json';
        $all_alerts = [];
        
        if (file_exists($alerts_file)) {
            $all_alerts = json_decode(file_get_contents($alerts_file), true);
        }
        
        foreach ($alerts as $alert) {
            // Cooldown kontrolü
            $cooldown_key = $alert['rule'] . '_' . $alert['endpoint'];
            $last_alert = $all_alerts[$cooldown_key]['last_triggered'] ?? 0;
            
            if (time() - $last_alert < $this->config['alert_cooldown']) {
                continue;
            }
            
            $alert['alert_id'] = uniqid('alert_', true);
            $all_alerts[$cooldown_key] = $alert;
        }
        
        file_put_contents($alerts_file, json_encode($all_alerts, JSON_PRETTY_PRINT));
    }
    
    /**
     * Performans raporu al
     * 
     * @param string $endpoint Endpoint (opsiyonel)
     * @param int $hours Kaç saatlik rapor
     * @return array
     */
    public function getPerformanceReport($endpoint = null, $hours = 24) {
        $report = [
            'period' => "Last {$hours} hours",
            'summary' => [],
            'trends' => [],
            'providers' => [],
            'alerts' => []
        ];
        
        $cutoff = time() - ($hours * 3600);
        $relevant_metrics = array_filter($this->metrics, function($metric) use ($cutoff, $endpoint) {
            if ($metric['timestamp'] < $cutoff) {
                return false;
            }
            if ($endpoint && $metric['endpoint'] !== $endpoint) {
                return false;
            }
            return true;
        });
        
        if (empty($relevant_metrics)) {
            return $report;
        }
        
        // Genel özet
        $total_calls = count($relevant_metrics);
        $success_calls = count(array_filter($relevant_metrics, function($m) {
            return $m['data']['success'] ?? false;
        }));
        
        $response_times = array_column(array_column($relevant_metrics, 'data'), 'response_time');
        $response_times = array_filter($response_times);
        
        $report['summary'] = [
            'total_calls' => $total_calls,
            'success_rate' => $total_calls > 0 ? round(($success_calls / $total_calls) * 100, 2) : 0,
            'avg_response_time' => !empty($response_times) ? round(array_sum($response_times) / count($response_times), 2) : 0,
            'p95_response_time' => $this->calculatePercentile($response_times, 95),
            'p99_response_time' => $this->calculatePercentile($response_times, 99)
        ];
        
        // Trend analizi
        $report['trends'] = $this->analyzeTrends($relevant_metrics);
        
        // Provider performansı
        $report['providers'] = $this->analyzeProviderPerformance($relevant_metrics);
        
        // Aktif alertler
        $report['alerts'] = $this->getActiveAlerts($hours);
        
        return $report;
    }
    
    /**
     * Percentile hesapla
     */
    private function calculatePercentile($values, $percentile) {
        if (empty($values)) {
            return 0;
        }
        
        sort($values);
        $index = (int)ceil(($percentile / 100) * count($values)) - 1;
        
        return isset($values[$index]) ? round($values[$index], 2) : 0;
    }
    
    /**
     * Trend analizi yap
     */
    private function analyzeTrends($metrics) {
        $trends = [];
        
        // Zamana göre grupla
        $hourly = [];
        foreach ($metrics as $metric) {
            $hour = date('Y-m-d H:00:00', $metric['timestamp']);
            if (!isset($hourly[$hour])) {
                $hourly[$hour] = [];
            }
            $hourly[$hour][] = $metric;
        }
        
        // Her saat için performans
        foreach ($hourly as $hour => $hour_metrics) {
            $total = count($hour_metrics);
            $success = count(array_filter($hour_metrics, function($m) {
                return $m['data']['success'] ?? false;
            }));
            
            $response_times = array_column(array_column($hour_metrics, 'data'), 'response_time');
            $response_times = array_filter($response_times);
            
            $trends[$hour] = [
                'total_calls' => $total,
                'success_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
                'avg_response_time' => !empty($response_times) ? round(array_sum($response_times) / count($response_times), 2) : 0
            ];
        }
        
        return $trends;
    }
    
    /**
     * Provider performans analizi
     */
    private function analyzeProviderPerformance($metrics) {
        $provider_stats = [];
        
        foreach ($metrics as $metric) {
            $data = $metric['data'];
            if (!isset($data['provider'])) {
                continue;
            }
            
            $provider = $data['provider'];
            if (!isset($provider_stats[$provider])) {
                $provider_stats[$provider] = [
                    'total_calls' => 0,
                    'success_calls' => 0,
                    'response_times' => []
                ];
            }
            
            $provider_stats[$provider]['total_calls']++;
            if ($data['success']) {
                $provider_stats[$provider]['success_calls']++;
            }
            if (isset($data['response_time'])) {
                $provider_stats[$provider]['response_times'][] = $data['response_time'];
            }
        }
        
        // Performans skoru hesapla
        foreach ($provider_stats as $provider => &$stats) {
            $stats['success_rate'] = $stats['total_calls'] > 0 
                ? round(($stats['success_calls'] / $stats['total_calls']) * 100, 2) 
                : 0;
            $stats['avg_response_time'] = !empty($stats['response_times']) 
                ? round(array_sum($stats['response_times']) / count($stats['response_times']), 2) 
                : 0;
            $stats['performance_score'] = $this->calculatePerformanceScore(
                $stats['success_rate'],
                $stats['avg_response_time']
            );
            unset($stats['response_times']);
        }
        
        return $provider_stats;
    }
    
    /**
     * Performans skoru hesapla (0-100)
     */
    private function calculatePerformanceScore($success_rate, $avg_response_time) {
        $score = 0;
        
        // Başarı oranı skoru (maks 70 puan)
        $score += $success_rate * 0.7;
        
        // Yanıt süresi skoru (maks 30 puan)
        if ($avg_response_time > 0) {
            $time_score = max(0, 30 - ($avg_response_time / 100));
            $score += min(30, $time_score);
        }
        
        return round(min(100, $score), 2);
    }
    
    /**
     * Aktif alertleri al
     */
    private function getActiveAlerts($hours) {
        $alerts_file = $this->performance_path . 'alerts.json';
        
        if (!file_exists($alerts_file)) {
            return [];
        }
        
        $all_alerts = json_decode(file_get_contents($alerts_file), true);
        $cutoff = time() - ($hours * 3600);
        $active = [];
        
        foreach ($all_alerts as $alert) {
            if ($alert['timestamp'] >= $cutoff) {
                $active[] = $alert;
            }
        }
        
        return $active;
    }
    
    /**
     * Performans metriklerini al (canlı)
     */
    public function getLiveMetrics() {
        $last_hour = $this->getPerformanceReport(null, 1);
        
        return [
            'current' => [
                'calls_per_minute' => $this->calculateCallsPerMinute(),
                'current_response_time' => $this->getCurrentResponseTime(),
                'current_success_rate' => $this->getCurrentSuccessRate()
            ],
            'last_hour' => $last_hour['summary'],
            'timestamp' => time()
        ];
    }
    
    /**
     * Dakika başına çağrı sayısını hesapla
     */
    private function calculateCallsPerMinute() {
        $last_minute = time() - 60;
        $recent = array_filter($this->metrics, function($metric) use ($last_minute) {
            return $metric['timestamp'] >= $last_minute;
        });
        
        return count($recent);
    }
    
    /**
     * Güncel yanıt süresini al
     */
    private function getCurrentResponseTime() {
        $last_minute = time() - 60;
        $recent = array_filter($this->metrics, function($metric) use ($last_minute) {
            return $metric['timestamp'] >= $last_minute && isset($metric['data']['response_time']);
        });
        
        if (empty($recent)) {
            return 0;
        }
        
        $response_times = array_column(array_column($recent, 'data'), 'response_time');
        return !empty($response_times) ? round(array_sum($response_times) / count($response_times), 2) : 0;
    }
    
    /**
     * Güncel başarı oranını al
     */
    private function getCurrentSuccessRate() {
        $last_minute = time() - 60;
        $recent = array_filter($this->metrics, function($metric) use ($last_minute) {
            return $metric['timestamp'] >= $last_minute;
        });
        
        if (empty($recent)) {
            return 100;
        }
        
        $success = count(array_filter($recent, function($m) {
            return $m['data']['success'] ?? false;
        }));
        
        return round(($success / count($recent)) * 100, 2);
    }
    
    /**
     * Performans istatistiklerini al
     */
    public function getPerformanceStats() {
        $total_metrics = count($this->metrics);
        $aggregated_files = glob($this->performance_path . 'aggregated_*.json');
        
        return [
            'total_metrics' => $total_metrics,
            'aggregated_files' => count($aggregated_files),
            'alert_rules' => count($this->alert_rules),
            'retention_days' => $this->config['retention_days'],
            'last_aggregation' => $this->getLastAggregationTime()
        ];
    }
    
    /**
     * Son aggregasyon zamanını al
     */
    private function getLastAggregationTime() {
        $aggregated_files = glob($this->performance_path . 'aggregated_*.json');
        
        if (empty($aggregated_files)) {
            return null;
        }
        
        $latest = max($aggregated_files);
        $timestamp = strtotime(str_replace(['aggregated_', '.json'], '', basename($latest)));
        
        return $timestamp ?: null;
    }
}