<?php
/**
 * APIMaster User Profiler
 * 
 * Kullanıcı profilleme ve davranış analiz sistemi
 * 
 * @package APIMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_UserProfiler {
    
    /**
     * @var array Kullanıcı profilleri
     */
    private $profiles = [];
    
    /**
     * @var array Davranış pattern'leri
     */
    private $behavior_patterns = [];
    
    /**
     * @var string Profil yolu
     */
    private $profiler_path;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->profiler_path = APIMASTER_PATH . 'data/profiles/';
        $this->initProfilerSystem();
    }
    
    /**
     * Profil sistemini başlat
     */
    private function initProfilerSystem() {
        if (!file_exists($this->profiler_path)) {
            mkdir($this->profiler_path, 0755, true);
        }
        
        $this->loadProfiles();
        $this->loadBehaviorPatterns();
    }
    
    /**
     * Profilleri yükle
     */
    private function loadProfiles() {
        $profiles_file = $this->profiler_path . 'profiles.json';
        
        if (file_exists($profiles_file)) {
            $this->profiles = json_decode(file_get_contents($profiles_file), true);
        } else {
            $this->profiles = [];
            $this->saveProfiles();
        }
    }
    
    /**
     * Profilleri kaydet
     */
    private function saveProfiles() {
        file_put_contents(
            $this->profiler_path . 'profiles.json',
            json_encode($this->profiles, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Davranış pattern'lerini yükle
     */
    private function loadBehaviorPatterns() {
        $patterns_file = $this->profiler_path . 'behavior_patterns.json';
        
        if (file_exists($patterns_file)) {
            $this->behavior_patterns = json_decode(file_get_contents($patterns_file), true);
        } else {
            $this->behavior_patterns = $this->getDefaultPatterns();
            $this->saveBehaviorPatterns();
        }
    }
    
    /**
     * Varsayılan davranış pattern'leri
     */
    private function getDefaultPatterns() {
        return [
            'power_user' => [
                'indicators' => [
                    'request_frequency' => 'high',
                    'api_variety' => 'high',
                    'session_duration' => 'long'
                ],
                'thresholds' => [
                    'requests_per_minute' => 30,
                    'unique_endpoints' => 20,
                    'avg_session_time' => 3600
                ]
            ],
            'casual_user' => [
                'indicators' => [
                    'request_frequency' => 'low',
                    'api_variety' => 'medium',
                    'session_duration' => 'medium'
                ],
                'thresholds' => [
                    'requests_per_minute' => 5,
                    'unique_endpoints' => 10,
                    'avg_session_time' => 900
                ]
            ],
            'developer' => [
                'indicators' => [
                    'request_frequency' => 'high',
                    'api_variety' => 'very_high',
                    'debug_mode' => true
                ],
                'thresholds' => [
                    'requests_per_minute' => 50,
                    'unique_endpoints' => 50,
                    'error_check_rate' => 0.3
                ]
            ],
            'bot' => [
                'indicators' => [
                    'request_frequency' => 'very_high',
                    'pattern_repetition' => 'high',
                    'user_agent' => 'automated'
                ],
                'thresholds' => [
                    'requests_per_second' => 10,
                    'repetition_rate' => 0.9,
                    'no_interaction' => true
                ]
            ]
        ];
    }
    
    /**
     * Davranış pattern'lerini kaydet
     */
    private function saveBehaviorPatterns() {
        file_put_contents(
            $this->profiler_path . 'behavior_patterns.json',
            json_encode($this->behavior_patterns, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Kullanıcı profili oluştur veya güncelle
     * 
     * @param string $user_id Kullanıcı ID
     * @param array $user_data Kullanıcı verileri
     * @return array
     */
    public function updateProfile($user_id, $user_data = []) {
        if (!isset($this->profiles[$user_id])) {
            $this->profiles[$user_id] = $this->createNewProfile($user_id);
        }
        
        $profile = &$this->profiles[$user_id];
        
        // Temel bilgileri güncelle
        if (!empty($user_data)) {
            $profile['user_data'] = array_merge($profile['user_data'], $user_data);
        }
        
        // Son aktivite zamanını güncelle
        $profile['last_active'] = time();
        $profile['total_activities']++;
        
        // Oturum süresini hesapla
        if ($profile['session_start'] === null) {
            $profile['session_start'] = time();
        }
        
        $profile['session_duration'] = time() - $profile['session_start'];
        
        // Oturum yenileme (1 saatten uzun sürdüyse)
        if ($profile['session_duration'] > 3600) {
            $profile['session_start'] = time();
            $profile['session_count']++;
        }
        
        $this->saveProfiles();
        
        return $profile;
    }
    
    /**
     * Yeni profil oluştur
     */
    private function createNewProfile($user_id) {
        return [
            'user_id' => $user_id,
            'user_data' => [],
            'created_at' => time(),
            'last_active' => time(),
            'profile_type' => 'unknown',
            'confidence' => 0,
            'stats' => [
                'total_requests' => 0,
                'successful_requests' => 0,
                'failed_requests' => 0,
                'unique_endpoints' => [],
                'preferred_providers' => [],
                'avg_response_time' => 0,
                'peak_hours' => []
            ],
            'behavior' => [
                'request_frequency' => [],
                'endpoint_patterns' => [],
                'error_patterns' => [],
                'preferred_times' => []
            ],
            'preferences' => [
                'default_provider' => null,
                'preferred_format' => 'json',
                'caching_enabled' => true,
                'retry_count' => 3
            ],
            'session_start' => time(),
            'session_count' => 1,
            'total_activities' => 0,
            'session_duration' => 0
        ];
    }
    
    /**
     * API isteğini kaydet ve profili güncelle
     * 
     * @param string $user_id Kullanıcı ID
     * @param array $request İstek verileri
     * @param array $response Yanıt verileri
     */
    public function recordRequest($user_id, $request, $response) {
        $profile = $this->updateProfile($user_id);
        
        // İstatistikleri güncelle
        $profile['stats']['total_requests']++;
        
        if (isset($response['success']) && $response['success']) {
            $profile['stats']['successful_requests']++;
        } else {
            $profile['stats']['failed_requests']++;
        }
        
        // Benzersiz endpoint'leri kaydet
        $endpoint = $request['endpoint'] ?? 'unknown';
        if (!in_array($endpoint, $profile['stats']['unique_endpoints'])) {
            $profile['stats']['unique_endpoints'][] = $endpoint;
        }
        
        // Provider tercihlerini güncelle
        $provider = $request['provider'] ?? 'default';
        if (!isset($profile['stats']['preferred_providers'][$provider])) {
            $profile['stats']['preferred_providers'][$provider] = 0;
        }
        $profile['stats']['preferred_providers'][$provider]++;
        
        // Ortalama yanıt süresini güncelle
        if (isset($response['response_time'])) {
            $current_avg = $profile['stats']['avg_response_time'];
            $total = $profile['stats']['total_requests'];
            $profile['stats']['avg_response_time'] = 
                ($current_avg * ($total - 1) + $response['response_time']) / $total;
        }
        
        // Davranış verilerini güncelle
        $hour = (int)date('H');
        if (!isset($profile['stats']['peak_hours'][$hour])) {
            $profile['stats']['peak_hours'][$hour] = 0;
        }
        $profile['stats']['peak_hours'][$hour]++;
        
        // İstek frekansını kaydet
        $timestamp = time();
        $profile['behavior']['request_frequency'][] = $timestamp;
        
        // Son 100 isteği tut
        if (count($profile['behavior']['request_frequency']) > 100) {
            $profile['behavior']['request_frequency'] = array_slice(
                $profile['behavior']['request_frequency'], 
                -100
            );
        }
        
        // Endpoint pattern'lerini güncelle
        $pattern = $this->extractEndpointPattern($endpoint);
        if (!isset($profile['behavior']['endpoint_patterns'][$pattern])) {
            $profile['behavior']['endpoint_patterns'][$pattern] = 0;
        }
        $profile['behavior']['endpoint_patterns'][$pattern]++;
        
        // Profil tipini güncelle
        $this->updateProfileType($user_id);
        
        $this->saveProfiles();
        
        return $profile;
    }
    
    /**
     * Endpoint pattern'i çıkar
     */
    private function extractEndpointPattern($endpoint) {
        // ID'leri ve sayısal değerleri placeholder ile değiştir
        $pattern = preg_replace('/\/\d+/', '/{id}', $endpoint);
        $pattern = preg_replace('/\/[a-f0-9]{32}/', '/{hash}', $pattern);
        $pattern = preg_replace('/\/[a-f0-9-]{36}/', '/{uuid}', $pattern);
        
        return $pattern;
    }
    
    /**
     * Profil tipini güncelle
     */
    private function updateProfileType($user_id) {
        if (!isset($this->profiles[$user_id])) {
            return;
        }
        
        $profile = &$this->profiles[$user_id];
        $best_match = 'unknown';
        $best_score = 0;
        
        foreach ($this->behavior_patterns as $type => $pattern) {
            $score = $this->calculateProfileMatch($profile, $pattern);
            
            if ($score > $best_score) {
                $best_score = $score;
                $best_match = $type;
            }
        }
        
        $profile['profile_type'] = $best_match;
        $profile['confidence'] = $best_score;
    }
    
    /**
     * Profil uyum skorunu hesapla
     */
    private function calculateProfileMatch($profile, $pattern) {
        $score = 0;
        $total_checks = 0;
        
        // İstek frekansı kontrolü
        if (isset($pattern['thresholds']['requests_per_minute'])) {
            $total_checks++;
            $frequency = $this->calculateRequestFrequency($profile);
            
            if ($frequency >= $pattern['thresholds']['requests_per_minute']) {
                $score += 1;
            } else {
                $score += $frequency / $pattern['thresholds']['requests_per_minute'];
            }
        }
        
        // Benzersiz endpoint kontrolü
        if (isset($pattern['thresholds']['unique_endpoints'])) {
            $total_checks++;
            $unique_count = count($profile['stats']['unique_endpoints']);
            
            if ($unique_count >= $pattern['thresholds']['unique_endpoints']) {
                $score += 1;
            } else {
                $score += $unique_count / $pattern['thresholds']['unique_endpoints'];
            }
        }
        
        // Oturum süresi kontrolü
        if (isset($pattern['thresholds']['avg_session_time'])) {
            $total_checks++;
            $avg_session = $profile['session_duration'];
            
            if ($avg_session >= $pattern['thresholds']['avg_session_time']) {
                $score += 1;
            } else {
                $score += $avg_session / $pattern['thresholds']['avg_session_time'];
            }
        }
        
        return $total_checks > 0 ? $score / $total_checks : 0;
    }
    
    /**
     * İstek frekansını hesapla (dakika başına)
     */
    private function calculateRequestFrequency($profile) {
        $frequencies = $profile['behavior']['request_frequency'];
        
        if (empty($frequencies)) {
            return 0;
        }
        
        // Son 5 dakikadaki istekleri al
        $cutoff = time() - 300;
        $recent = array_filter($frequencies, function($timestamp) use ($cutoff) {
            return $timestamp >= $cutoff;
        });
        
        return count($recent) / 5; // Dakika başına istek
    }
    
    /**
     * Kullanıcı profilini al
     * 
     * @param string $user_id Kullanıcı ID
     * @return array|null
     */
    public function getProfile($user_id) {
        return $this->profiles[$user_id] ?? null;
    }
    
    /**
     * Kullanıcı tercihlerini al
     * 
     * @param string $user_id Kullanıcı ID
     * @return array
     */
    public function getUserPreferences($user_id) {
        $profile = $this->getProfile($user_id);
        
        if (!$profile) {
            return $this->getDefaultPreferences();
        }
        
        return $profile['preferences'];
    }
    
    /**
     * Varsayılan tercihler
     */
    private function getDefaultPreferences() {
        return [
            'default_provider' => null,
            'preferred_format' => 'json',
            'caching_enabled' => true,
            'retry_count' => 3
        ];
    }
    
    /**
     * Kullanıcı tercihlerini güncelle
     * 
     * @param string $user_id Kullanıcı ID
     * @param array $preferences Yeni tercihler
     */
    public function updatePreferences($user_id, $preferences) {
        $profile = $this->updateProfile($user_id);
        
        foreach ($preferences as $key => $value) {
            if (isset($profile['preferences'][$key])) {
                $profile['preferences'][$key] = $value;
            }
        }
        
        $this->saveProfiles();
        
        return $profile['preferences'];
    }
    
    /**
     * Önerilen provider'ı al
     * 
     * @param string $user_id Kullanıcı ID
     * @param string $endpoint Endpoint
     * @return string|null
     */
    public function getRecommendedProvider($user_id, $endpoint) {
        $profile = $this->getProfile($user_id);
        
        if (!$profile || empty($profile['stats']['preferred_providers'])) {
            return null;
        }
        
        // En çok kullanılan provider'ı bul
        arsort($profile['stats']['preferred_providers']);
        $top_provider = key($profile['stats']['preferred_providers']);
        
        // Özel tercih varsa onu kullan
        if ($profile['preferences']['default_provider']) {
            return $profile['preferences']['default_provider'];
        }
        
        return $top_provider;
    }
    
    /**
     * Anormal aktivite tespiti
     * 
     * @param string $user_id Kullanıcı ID
     * @return array
     */
    public function detectAnomaly($user_id) {
        $profile = $this->getProfile($user_id);
        
        if (!$profile) {
            return ['is_anomaly' => false, 'reasons' => []];
        }
        
        $anomalies = [];
        
        // Anormal yüksek istek frekansı
        $frequency = $this->calculateRequestFrequency($profile);
        if ($frequency > 60) { // Dakikada 60+ istek
            $anomalies[] = 'extremely_high_frequency';
        } elseif ($frequency > 30) {
            $anomalies[] = 'high_frequency';
        }
        
        // Yüksek hata oranı
        $total = $profile['stats']['total_requests'];
        if ($total > 10) {
            $error_rate = $profile['stats']['failed_requests'] / $total;
            if ($error_rate > 0.5) {
                $anomalies[] = 'high_error_rate';
            }
        }
        
        // Bot pattern kontrolü
        if ($profile['profile_type'] === 'bot' && $profile['confidence'] > 0.8) {
            $anomalies[] = 'bot_detected';
        }
        
        return [
            'is_anomaly' => !empty($anomalies),
            'reasons' => $anomalies,
            'severity' => count($anomalies) >= 2 ? 'high' : 'medium'
        ];
    }
    
    /**
     * Profil istatistiklerini al
     */
    public function getProfilerStats() {
        $stats = [
            'total_profiles' => count($this->profiles),
            'active_today' => 0,
            'active_this_hour' => 0,
            'profile_types' => []
        ];
        
        $today_start = strtotime('today');
        $hour_ago = time() - 3600;
        
        foreach ($this->profiles as $profile) {
            // Profil tipleri
            $type = $profile['profile_type'];
            if (!isset($stats['profile_types'][$type])) {
                $stats['profile_types'][$type] = 0;
            }
            $stats['profile_types'][$type]++;
            
            // Bugün aktif
            if ($profile['last_active'] >= $today_start) {
                $stats['active_today']++;
            }
            
            // Son saat aktif
            if ($profile['last_active'] >= $hour_ago) {
                $stats['active_this_hour']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Eski profilleri temizle (30 günden eski)
     */
    public function cleanupOldProfiles() {
        $cutoff = time() - (30 * 24 * 3600);
        $cleaned = 0;
        
        foreach ($this->profiles as $user_id => $profile) {
            if ($profile['last_active'] < $cutoff) {
                unset($this->profiles[$user_id]);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            $this->saveProfiles();
        }
        
        return $cleaned;
    }
}