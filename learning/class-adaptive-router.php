<?php
/**
 * APIMaster Adaptive Router
 * 
 * Adaptif yönlendirme ve provider seçim sistemi
 * 
 * @package APIMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_AdaptiveRouter {
    
    /**
     * @var array Router konfigürasyonu
     */
    private $config;
    
    /**
     * @var array Provider performans skorları
     */
    private $provider_scores = [];
    
    /**
     * @var array Routing kuralları
     */
    private $routing_rules = [];
    
    /**
     * @var array Yük dengeleyici durumu
     */
    private $load_balancer = [];
    
    /**
     * @var string Router yolu
     */
    private $router_path;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->router_path = APIMASTER_PATH . 'data/router/';
        $this->initRouterSystem();
    }
    
    /**
     * Router sistemini başlat
     */
    private function initRouterSystem() {
        if (!file_exists($this->router_path)) {
            mkdir($this->router_path, 0755, true);
        }
        
        $this->loadConfig();
        $this->loadProviderScores();
        $this->loadRoutingRules();
        $this->initLoadBalancer();
    }
    
    /**
     * Konfigürasyonu yükle
     */
    private function loadConfig() {
        $config_file = $this->router_path . 'config.json';
        
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
            'routing_strategy' => 'adaptive', // adaptive, round_robin, weighted, latency_based
            'health_check_interval' => 60, // saniye
            'failover_enabled' => true,
            'max_retries' => 3,
            'circuit_breaker' => [
                'enabled' => true,
                'failure_threshold' => 5,
                'timeout' => 30, // saniye
                'half_open_requests' => 3
            ],
            'load_balancing' => [
                'enabled' => true,
                'algorithm' => 'least_connections', // least_connections, round_robin, random
                'max_connections_per_provider' => 100
            ],
            'latency_weight' => 0.4,
            'success_rate_weight' => 0.4,
            'cost_weight' => 0.2
        ];
    }
    
    /**
     * Konfigürasyonu kaydet
     */
    private function saveConfig() {
        file_put_contents(
            $this->router_path . 'config.json',
            json_encode($this->config, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Provider skorlarını yükle
     */
    private function loadProviderScores() {
        $scores_file = $this->router_path . 'provider_scores.json';
        
        if (file_exists($scores_file)) {
            $this->provider_scores = json_decode(file_get_contents($scores_file), true);
        } else {
            $this->provider_scores = [];
            $this->saveProviderScores();
        }
    }
    
    /**
     * Provider skorlarını kaydet
     */
    private function saveProviderScores() {
        file_put_contents(
            $this->router_path . 'provider_scores.json',
            json_encode($this->provider_scores, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Routing kurallarını yükle
     */
    private function loadRoutingRules() {
        $rules_file = $this->router_path . 'routing_rules.json';
        
        if (file_exists($rules_file)) {
            $this->routing_rules = json_decode(file_get_contents($rules_file), true);
        } else {
            $this->routing_rules = $this->getDefaultRoutingRules();
            $this->saveRoutingRules();
        }
    }
    
    /**
     * Varsayılan routing kuralları
     */
    private function getDefaultRoutingRules() {
        return [
            [
                'name' => 'high_priority_apis',
                'conditions' => [
                    'endpoint_pattern' => '/\/api\/v\d+\/(users|orders|payments)/',
                    'min_success_rate' => 95
                ],
                'action' => 'use_best_provider',
                'priority' => 1
            ],
            [
                'name' => 'cost_optimized',
                'conditions' => [
                    'endpoint_pattern' => '/\/api\/v\d+\/(search|list|get)/',
                    'max_latency' => 1000
                ],
                'action' => 'use_cheapest_provider',
                'priority' => 2
            ],
            [
                'name' => 'fallback_rule',
                'conditions' => [],
                'action' => 'load_balanced',
                'priority' => 100
            ]
        ];
    }
    
    /**
     * Routing kurallarını kaydet
     */
    private function saveRoutingRules() {
        file_put_contents(
            $this->router_path . 'routing_rules.json',
            json_encode($this->routing_rules, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Load balancer'ı başlat
     */
    private function initLoadBalancer() {
        $lb_file = $this->router_path . 'load_balancer.json';
        
        if (file_exists($lb_file)) {
            $this->load_balancer = json_decode(file_get_contents($lb_file), true);
        } else {
            $this->load_balancer = [
                'connections' => [],
                'last_updated' => time(),
                'round_robin_index' => 0
            ];
            $this->saveLoadBalancer();
        }
    }
    
    /**
     * Load balancer'ı kaydet
     */
    private function saveLoadBalancer() {
        file_put_contents(
            $this->router_path . 'load_balancer.json',
            json_encode($this->load_balancer, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * En iyi provider'ı seç
     * 
     * @param string $endpoint API endpoint
     * @param array $context Bağlam bilgileri
     * @return array|null
     */
    public function selectBestProvider($endpoint, $context = []) {
        // Mevcut provider'ları al
        $available_providers = $this->getAvailableProviders();
        
        if (empty($available_providers)) {
            return null;
        }
        
        // Routing kurallarını uygula
        $rule = $this->matchRoutingRule($endpoint, $context);
        
        if ($rule) {
            $provider = $this->applyRoutingRule($rule, $available_providers, $endpoint, $context);
            if ($provider) {
                return $this->prepareProviderResponse($provider, $rule);
            }
        }
        
        // Stratejiye göre seçim yap
        switch ($this->config['routing_strategy']) {
            case 'round_robin':
                $provider = $this->roundRobinSelect($available_providers);
                break;
            case 'weighted':
                $provider = $this->weightedSelect($available_providers);
                break;
            case 'latency_based':
                $provider = $this->latencyBasedSelect($available_providers);
                break;
            case 'adaptive':
            default:
                $provider = $this->adaptiveSelect($available_providers, $endpoint);
                break;
        }
        
        return $this->prepareProviderResponse($provider, null);
    }
    
    /**
     * Mevcut provider'ları al
     */
    private function getAvailableProviders() {
        $providers = [];
        
        // Provider konfigürasyonunu yükle
        $config_file = APIMASTER_PATH . 'config/providers.php';
        if (file_exists($config_file)) {
            $provider_config = include $config_file;
            
            foreach ($provider_config as $provider_name => $config) {
                if ($this->isProviderAvailable($provider_name)) {
                    $providers[$provider_name] = $config;
                }
            }
        }
        
        return $providers;
    }
    
    /**
     * Provider'ın müsait olup olmadığını kontrol et
     */
    private function isProviderAvailable($provider_name) {
        // Circuit breaker kontrolü
        if ($this->config['circuit_breaker']['enabled']) {
            $circuit = $this->getCircuitBreakerState($provider_name);
            if ($circuit['state'] === 'open') {
                return false;
            }
        }
        
        // Connection limit kontrolü
        if ($this->config['load_balancing']['enabled']) {
            $current_connections = $this->load_balancer['connections'][$provider_name] ?? 0;
            if ($current_connections >= $this->config['load_balancing']['max_connections_per_provider']) {
                return false;
            }
        }
        
        // Health check
        $score = $this->getProviderScore($provider_name);
        if ($score['success_rate'] < 50) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Circuit breaker durumunu al
     */
    private function getCircuitBreakerState($provider_name) {
        $circuit_file = $this->router_path . 'circuit_breaker_' . $provider_name . '.json';
        
        if (file_exists($circuit_file)) {
            $circuit = json_decode(file_get_contents($circuit_file), true);
            
            // Timeout kontrolü
            if ($circuit['state'] === 'open') {
                if (time() - $circuit['opened_at'] > $this->config['circuit_breaker']['timeout']) {
                    $circuit['state'] = 'half_open';
                    $this->saveCircuitBreakerState($provider_name, $circuit);
                }
            }
            
            return $circuit;
        }
        
        return [
            'state' => 'closed',
            'failure_count' => 0,
            'opened_at' => null
        ];
    }
    
    /**
     * Circuit breaker durumunu kaydet
     */
    private function saveCircuitBreakerState($provider_name, $circuit) {
        file_put_contents(
            $this->router_path . 'circuit_breaker_' . $provider_name . '.json',
            json_encode($circuit, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Routing kuralı eşleştir
     */
    private function matchRoutingRule($endpoint, $context) {
        $matched_rule = null;
        $highest_priority = PHP_INT_MAX;
        
        foreach ($this->routing_rules as $rule) {
            if ($this->matchesRule($rule, $endpoint, $context)) {
                if ($rule['priority'] < $highest_priority) {
                    $highest_priority = $rule['priority'];
                    $matched_rule = $rule;
                }
            }
        }
        
        return $matched_rule;
    }
    
    /**
     * Kuralın eşleşip eşleşmediğini kontrol et
     */
    private function matchesRule($rule, $endpoint, $context) {
        if (empty($rule['conditions'])) {
            return true;
        }
        
        foreach ($rule['conditions'] as $condition => $value) {
            switch ($condition) {
                case 'endpoint_pattern':
                    if (!preg_match($value, $endpoint)) {
                        return false;
                    }
                    break;
                case 'min_success_rate':
                    // Provider başarı oranı kontrolü yapılacak
                    break;
                case 'max_latency':
                    // Latency kontrolü yapılacak
                    break;
            }
        }
        
        return true;
    }
    
    /**
     * Routing kuralını uygula
     */
    private function applyRoutingRule($rule, $providers, $endpoint, $context) {
        switch ($rule['action']) {
            case 'use_best_provider':
                return $this->adaptiveSelect($providers, $endpoint);
            case 'use_cheapest_provider':
                return $this->selectCheapestProvider($providers);
            case 'load_balanced':
                return $this->roundRobinSelect($providers);
            default:
                return null;
        }
    }
    
    /**
     * Round Robin seçimi
     */
    private function roundRobinSelect($providers) {
        $provider_names = array_keys($providers);
        
        if (empty($provider_names)) {
            return null;
        }
        
        $index = $this->load_balancer['round_robin_index'] % count($provider_names);
        $selected = $provider_names[$index];
        
        $this->load_balancer['round_robin_index']++;
        $this->saveLoadBalancer();
        
        return $selected;
    }
    
    /**
     * Weighted seçim
     */
    private function weightedSelect($providers) {
        $weights = [];
        $total_weight = 0;
        
        foreach ($providers as $name => $config) {
            $score = $this->getProviderScore($name);
            $weight = $score['overall_score'] * 100;
            $weights[$name] = $weight;
            $total_weight += $weight;
        }
        
        if ($total_weight == 0) {
            return $this->roundRobinSelect($providers);
        }
        
        $random = rand(1, $total_weight);
        $current = 0;
        
        foreach ($weights as $name => $weight) {
            $current += $weight;
            if ($random <= $current) {
                return $name;
            }
        }
        
        return key($providers);
    }
    
    /**
     * Latency bazlı seçim
     */
    private function latencyBasedSelect($providers) {
        $best_provider = null;
        $lowest_latency = PHP_INT_MAX;
        
        foreach ($providers as $name => $config) {
            $score = $this->getProviderScore($name);
            $latency = $score['avg_latency'] ?? PHP_INT_MAX;
            
            if ($latency < $lowest_latency) {
                $lowest_latency = $latency;
                $best_provider = $name;
            }
        }
        
        return $best_provider;
    }
    
    /**
     * Adaptif seçim (akıllı routing)
     */
    private function adaptiveSelect($providers, $endpoint) {
        $scores = [];
        
        foreach ($providers as $name => $config) {
            $score = $this->getProviderScore($name);
            
            // Genel skor
            $overall_score = $score['overall_score'];
            
            // Endpoint bazlı performans
            $endpoint_score = $this->getEndpointProviderScore($endpoint, $name);
            
            // Son skor
            $final_score = ($overall_score * 0.7) + ($endpoint_score * 0.3);
            
            $scores[$name] = $final_score;
        }
        
        // En yüksek skoru seç
        arsort($scores);
        
        return key($scores);
    }
    
    /**
     * En ucuz provider'ı seç
     */
    private function selectCheapestProvider($providers) {
        $cheapest = null;
        $lowest_cost = PHP_INT_MAX;
        
        foreach ($providers as $name => $config) {
            $cost = $config['cost_per_request'] ?? 0;
            if ($cost < $lowest_cost) {
                $lowest_cost = $cost;
                $cheapest = $name;
            }
        }
        
        return $cheapest;
    }
    
    /**
     * Provider skorunu al
     */
    private function getProviderScore($provider_name) {
        if (isset($this->provider_scores[$provider_name])) {
            $score = $this->provider_scores[$provider_name];
            
            // Skorların güncelliğini kontrol et (5 dakikadan eskiyse yenile)
            if (time() - $score['last_updated'] > 300) {
                return $this->calculateProviderScore($provider_name);
            }
            
            return $score;
        }
        
        return $this->calculateProviderScore($provider_name);
    }
    
    /**
     * Provider skorunu hesapla
     */
    private function calculateProviderScore($provider_name) {
        // Performans tracker'dan verileri al
        $performance_file = APIMASTER_PATH . 'data/performance/metrics.json';
        $provider_metrics = [
            'success_rate' => 95,
            'avg_latency' => 500,
            'error_rate' => 5
        ];
        
        if (file_exists($performance_file)) {
            $metrics = json_decode(file_get_contents($performance_file), true);
            
            // Provider bazlı metrikleri topla
            $provider_calls = array_filter($metrics, function($m) use ($provider_name) {
                return isset($m['data']['provider']) && $m['data']['provider'] === $provider_name;
            });
            
            if (!empty($provider_calls)) {
                $total = count($provider_calls);
                $success = count(array_filter($provider_calls, function($m) {
                    return $m['data']['success'] ?? false;
                }));
                
                $latencies = array_column(array_column($provider_calls, 'data'), 'response_time');
                $latencies = array_filter($latencies);
                
                $provider_metrics['success_rate'] = ($success / $total) * 100;
                $provider_metrics['avg_latency'] = !empty($latencies) ? array_sum($latencies) / count($latencies) : 500;
                $provider_metrics['error_rate'] = 100 - $provider_metrics['success_rate'];
            }
        }
        
        // Normalize et (0-100 arası)
        $latency_score = max(0, 100 - ($provider_metrics['avg_latency'] / 20));
        $success_score = $provider_metrics['success_rate'];
        $error_score = 100 - $provider_metrics['error_rate'];
        
        // Weighted skor
        $overall_score = (
            $latency_score * $this->config['latency_weight'] +
            $success_score * $this->config['success_rate_weight'] +
            $error_score * $this->config['cost_weight']
        );
        
        $score = [
            'provider' => $provider_name,
            'overall_score' => round($overall_score, 2),
            'success_rate' => round($provider_metrics['success_rate'], 2),
            'avg_latency' => round($provider_metrics['avg_latency'], 2),
            'error_rate' => round($provider_metrics['error_rate'], 2),
            'last_updated' => time()
        ];
        
        $this->provider_scores[$provider_name] = $score;
        $this->saveProviderScores();
        
        return $score;
    }
    
    /**
     * Endpoint-provider skorunu al
     */
    private function getEndpointProviderScore($endpoint, $provider_name) {
        $endpoint_file = $this->router_path . 'endpoint_scores.json';
        
        if (file_exists($endpoint_file)) {
            $scores = json_decode(file_get_contents($endpoint_file), true);
            $key = md5($endpoint . '_' . $provider_name);
            
            if (isset($scores[$key])) {
                $score = $scores[$key];
                
                // 1 saatten eskiyse güncelle
                if (time() - $score['last_updated'] < 3600) {
                    return $score['score'];
                }
            }
        }
        
        return 50; // Varsayılan skor
    }
    
    /**
     * Provider performansını güncelle
     * 
     * @param string $provider_name Provider adı
     * @param array $result Sonuç bilgileri
     */
    public function updateProviderPerformance($provider_name, $result) {
        $score = $this->getProviderScore($provider_name);
        
        // Başarı oranını güncelle
        if (isset($result['success'])) {
            $current_success = $score['success_rate'];
            $current_total = $score['total_requests'] ?? 0;
            $new_total = $current_total + 1;
            
            $new_success_rate = (($current_success * $current_total) + ($result['success'] ? 100 : 0)) / $new_total;
            $score['success_rate'] = round($new_success_rate, 2);
            $score['total_requests'] = $new_total;
        }
        
        // Latency güncelle
        if (isset($result['response_time'])) {
            $current_latency = $score['avg_latency'];
            $current_total = $score['total_requests'] ?? 0;
            
            $new_latency = (($current_latency * $current_total) + $result['response_time']) / ($current_total + 1);
            $score['avg_latency'] = round($new_latency, 2);
        }
        
        // Error rate güncelle
        $score['error_rate'] = 100 - $score['success_rate'];
        
        // Overall score yeniden hesapla
        $latency_score = max(0, 100 - ($score['avg_latency'] / 20));
        $success_score = $score['success_rate'];
        $error_score = 100 - $score['error_rate'];
        
        $score['overall_score'] = round(
            $latency_score * $this->config['latency_weight'] +
            $success_score * $this->config['success_rate_weight'] +
            $error_score * $this->config['cost_weight'],
            2
        );
        
        $score['last_updated'] = time();
        
        $this->provider_scores[$provider_name] = $score;
        $this->saveProviderScores();
        
        // Circuit breaker kontrolü
        if ($this->config['circuit_breaker']['enabled']) {
            $this->updateCircuitBreaker($provider_name, $result['success'] ?? false);
        }
        
        // Connection count güncelle
        if ($this->config['load_balancing']['enabled']) {
            $this->updateConnectionCount($provider_name, $result['finished'] ?? true);
        }
        
        return $score;
    }
    
    /**
     * Circuit breaker güncelle
     */
    private function updateCircuitBreaker($provider_name, $success) {
        $circuit = $this->getCircuitBreakerState($provider_name);
        
        if ($circuit['state'] === 'closed') {
            if (!$success) {
                $circuit['failure_count']++;
                
                if ($circuit['failure_count'] >= $this->config['circuit_breaker']['failure_threshold']) {
                    $circuit['state'] = 'open';
                    $circuit['opened_at'] = time();
                }
            } else {
                $circuit['failure_count'] = max(0, $circuit['failure_count'] - 1);
            }
        } elseif ($circuit['state'] === 'half_open') {
            if ($success) {
                $circuit['state'] = 'closed';
                $circuit['failure_count'] = 0;
            } else {
                $circuit['state'] = 'open';
                $circuit['opened_at'] = time();
            }
        }
        
        $this->saveCircuitBreakerState($provider_name, $circuit);
    }
    
    /**
     * Connection count güncelle
     */
    private function updateConnectionCount($provider_name, $finished) {
        if ($finished) {
            $this->load_balancer['connections'][$provider_name] = max(0, 
                ($this->load_balancer['connections'][$provider_name] ?? 0) - 1
            );
        } else {
            $this->load_balancer['connections'][$provider_name] = 
                ($this->load_balancer['connections'][$provider_name] ?? 0) + 1;
        }
        
        $this->saveLoadBalancer();
    }
    
    /**
     * Yanıt hazırla
     */
    private function prepareProviderResponse($provider_name, $rule) {
        $score = $this->getProviderScore($provider_name);
        
        return [
            'provider' => $provider_name,
            'score' => $score['overall_score'],
            'success_rate' => $score['success_rate'],
            'avg_latency' => $score['avg_latency'],
            'rule_applied' => $rule['name'] ?? 'default',
            'timestamp' => time()
        ];
    }
    
    /**
     * Health check yap
     */
    public function performHealthCheck() {
        $providers = $this->getAvailableProviders();
        $results = [];
        
        foreach ($providers as $name => $config) {
            $health = [
                'provider' => $name,
                'status' => 'healthy',
                'response_time' => 0,
                'error' => null
            ];
            
            // Basit health check (provider'ın base endpoint'ine istek at)
            if (isset($config['base_url'])) {
                $start_time = microtime(true);
                $ch = curl_init($config['base_url']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                
                curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $response_time = (microtime(true) - $start_time) * 1000;
                
                if ($http_code > 0 && $http_code < 500) {
                    $health['response_time'] = round($response_time, 2);
                } else {
                    $health['status'] = 'unhealthy';
                    $health['error'] = "HTTP {$http_code}";
                }
                
                curl_close($ch);
            }
            
            $results[$name] = $health;
        }
        
        // Health check sonuçlarını kaydet
        file_put_contents(
            $this->router_path . 'health_check.json',
            json_encode([
                'timestamp' => time(),
                'results' => $results
            ], JSON_PRETTY_PRINT)
        );
        
        return $results;
    }
    
    /**
     * Router istatistiklerini al
     */
    public function getRouterStats() {
        $stats = [
            'strategy' => $this->config['routing_strategy'],
            'total_providers' => count($this->provider_scores),
            'circuit_breaker_enabled' => $this->config['circuit_breaker']['enabled'],
            'load_balancing_enabled' => $this->config['load_balancing']['enabled'],
            'provider_performance' => []
        ];
        
        foreach ($this->provider_scores as $name => $score) {
            $stats['provider_performance'][$name] = [
                'overall_score' => $score['overall_score'],
                'success_rate' => $score['success_rate'],
                'avg_latency' => $score['avg_latency']
            ];
        }
        
        return $stats;
    }
    
    /**
     * Routing stratejisini değiştir
     */
    public function setRoutingStrategy($strategy) {
        $valid_strategies = ['adaptive', 'round_robin', 'weighted', 'latency_based'];
        
        if (in_array($strategy, $valid_strategies)) {
            $this->config['routing_strategy'] = $strategy;
            $this->saveConfig();
            return true;
        }
        
        return false;
    }
    
    /**
     * Yeni routing kuralı ekle
     */
    public function addRoutingRule($rule) {
        $rule['priority'] = $rule['priority'] ?? (count($this->routing_rules) + 1);
        $this->routing_rules[] = $rule;
        $this->saveRoutingRules();
        
        return true;
    }
}