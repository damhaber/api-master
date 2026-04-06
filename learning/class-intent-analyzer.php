<?php
/**
 * APIMaster Intent Analyzer
 * 
 * API çağrılarının niyetini analiz eden ve sınıflandıran sistem
 * 
 * @package APIMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_IntentAnalyzer {
    
    /**
     * @var array Niyet pattern'leri
     */
    private $intent_patterns = [];
    
    /**
     * @var array Öğrenilmiş niyetler
     */
    private $learned_intents = [];
    
    /**
     * @var string Niyet veri yolu
     */
    private $intent_path;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->intent_path = APIMASTER_PATH . 'data/intents/';
        $this->initIntentSystem();
    }
    
    /**
     * Niyet sistemini başlat
     */
    private function initIntentSystem() {
        if (!file_exists($this->intent_path)) {
            mkdir($this->intent_path, 0755, true);
        }
        
        $this->loadIntentPatterns();
        $this->loadLearnedIntents();
    }
    
    /**
     * Niyet pattern'lerini yükle
     */
    private function loadIntentPatterns() {
        $patterns_file = $this->intent_path . 'patterns.json';
        
        if (file_exists($patterns_file)) {
            $this->intent_patterns = json_decode(file_get_contents($patterns_file), true);
        } else {
            $this->intent_patterns = $this->getDefaultPatterns();
            $this->saveIntentPatterns();
        }
    }
    
    /**
     * Varsayılan niyet pattern'leri
     */
    private function getDefaultPatterns() {
        return [
            'data_fetch' => [
                'keywords' => ['get', 'fetch', 'retrieve', 'list', 'show', 'display'],
                'confidence' => 0.8,
                'priority' => 1
            ],
            'data_create' => [
                'keywords' => ['create', 'add', 'insert', 'new', 'post', 'save'],
                'confidence' => 0.8,
                'priority' => 2
            ],
            'data_update' => [
                'keywords' => ['update', 'edit', 'modify', 'change', 'put', 'patch'],
                'confidence' => 0.8,
                'priority' => 2
            ],
            'data_delete' => [
                'keywords' => ['delete', 'remove', 'destroy', 'erase', 'clear'],
                'confidence' => 0.9,
                'priority' => 3
            ],
            'search' => [
                'keywords' => ['search', 'find', 'lookup', 'query', 'seek', 'locate'],
                'confidence' => 0.7,
                'priority' => 1
            ],
            'auth' => [
                'keywords' => ['login', 'authenticate', 'verify', 'token', 'session', 'auth'],
                'confidence' => 0.9,
                'priority' => 4
            ],
            'upload' => [
                'keywords' => ['upload', 'file', 'image', 'document', 'attachment'],
                'confidence' => 0.8,
                'priority' => 3
            ],
            'export' => [
                'keywords' => ['export', 'download', 'extract', 'backup', 'dump'],
                'confidence' => 0.8,
                'priority' => 2
            ],
            'analytics' => [
                'keywords' => ['analyze', 'report', 'stats', 'metrics', 'insights', 'analytics'],
                'confidence' => 0.7,
                'priority' => 1
            ],
            'config' => [
                'keywords' => ['configure', 'setup', 'settings', 'options', 'preferences'],
                'confidence' => 0.8,
                'priority' => 4
            ]
        ];
    }
    
    /**
     * Niyet pattern'lerini kaydet
     */
    private function saveIntentPatterns() {
        file_put_contents(
            $this->intent_path . 'patterns.json',
            json_encode($this->intent_patterns, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Öğrenilmiş niyetleri yükle
     */
    private function loadLearnedIntents() {
        $learned_file = $this->intent_path . 'learned.json';
        
        if (file_exists($learned_file)) {
            $this->learned_intents = json_decode(file_get_contents($learned_file), true);
        } else {
            $this->learned_intents = [];
            $this->saveLearnedIntents();
        }
    }
    
    /**
     * Öğrenilmiş niyetleri kaydet
     */
    private function saveLearnedIntents() {
        file_put_contents(
            $this->intent_path . 'learned.json',
            json_encode($this->learned_intents, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * API isteğinin niyetini analiz et
     * 
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $data İstek verileri
     * @return array
     */
    public function analyzeIntent($endpoint, $method, $data = []) {
        $analysis = [
            'intent' => 'unknown',
            'confidence' => 0,
            'alternatives' => [],
            'features' => []
        ];
        
        // 1. HTTP method'a göre analiz
        $method_intent = $this->analyzeByMethod($method);
        
        // 2. Endpoint'e göre analiz
        $endpoint_intent = $this->analyzeByEndpoint($endpoint);
        
        // 3. Keyword'lere göre analiz
        $keyword_intent = $this->analyzeByKeywords($endpoint, $data);
        
        // 4. Veri yapısına göre analiz
        $data_intent = $this->analyzeByDataStructure($data);
        
        // Tüm analizleri birleştir
        $combined = $this->combineAnalysis([
            $method_intent,
            $endpoint_intent,
            $keyword_intent,
            $data_intent
        ]);
        
        // En yüksek skorlu niyeti seç
        if (!empty($combined)) {
            uasort($combined, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });
            
            $top_intent = key($combined);
            $analysis['intent'] = $top_intent;
            $analysis['confidence'] = $combined[$top_intent]['score'];
            $analysis['features'] = $combined[$top_intent]['features'] ?? [];
            
            // Alternatifleri ekle
            $analysis['alternatives'] = array_slice($combined, 1, 3, true);
        }
        
        // Öğrenilmiş niyetleri kontrol et
        $learned = $this->checkLearnedIntents($endpoint, $method, $data);
        if ($learned && $learned['confidence'] > $analysis['confidence']) {
            $analysis['intent'] = $learned['intent'];
            $analysis['confidence'] = $learned['confidence'];
            $analysis['learned_match'] = true;
        }
        
        return $analysis;
    }
    
    /**
     * HTTP method'a göre analiz
     */
    private function analyzeByMethod($method) {
        $method_map = [
            'GET' => ['data_fetch' => 0.9, 'search' => 0.7],
            'POST' => ['data_create' => 0.9, 'upload' => 0.7],
            'PUT' => ['data_update' => 0.9, 'config' => 0.6],
            'PATCH' => ['data_update' => 0.8, 'config' => 0.5],
            'DELETE' => ['data_delete' => 0.95],
            'OPTIONS' => ['config' => 0.5],
            'HEAD' => ['data_fetch' => 0.5]
        ];
        
        $results = [];
        if (isset($method_map[$method])) {
            foreach ($method_map[$method] as $intent => $score) {
                $results[$intent] = ['score' => $score, 'source' => 'method'];
            }
        }
        
        return $results;
    }
    
    /**
     * Endpoint'e göre analiz
     */
    private function analyzeByEndpoint($endpoint) {
        $results = [];
        
        // Endpoint parçaları
        $parts = explode('/', trim($endpoint, '/'));
        
        foreach ($parts as $part) {
            $part_lower = strtolower($part);
            
            foreach ($this->intent_patterns as $intent => $pattern) {
                foreach ($pattern['keywords'] as $keyword) {
                    if (strpos($part_lower, $keyword) !== false) {
                        $score = $pattern['confidence'] * (1 / (count($parts) + 1));
                        
                        if (!isset($results[$intent]) || $results[$intent]['score'] < $score) {
                            $results[$intent] = [
                                'score' => $score,
                                'source' => 'endpoint',
                                'matched' => $keyword
                            ];
                        }
                    }
                }
            }
        }
        
        // Özel endpoint pattern'leri
        $special_patterns = $this->getSpecialEndpointPatterns();
        foreach ($special_patterns as $pattern => $intent) {
            if (preg_match($pattern, $endpoint)) {
                $results[$intent] = [
                    'score' => 0.95,
                    'source' => 'special_pattern',
                    'matched' => $pattern
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Özel endpoint pattern'leri
     */
    private function getSpecialEndpointPatterns() {
        return [
            '/\/api\/v\d+\/users\/\d+\/profile/' => 'data_fetch',
            '/\/auth\/token/' => 'auth',
            '/\/upload\/file/' => 'upload',
            '/\/export\/csv/' => 'export',
            '/\/search\/.*/' => 'search'
        ];
    }
    
    /**
     * Keyword'lere göre analiz
     */
    private function analyzeByKeywords($endpoint, $data) {
        $results = [];
        $text_to_analyze = $endpoint . ' ' . json_encode($data);
        $text_lower = strtolower($text_to_analyze);
        
        foreach ($this->intent_patterns as $intent => $pattern) {
            $score = 0;
            $matched = [];
            
            foreach ($pattern['keywords'] as $keyword) {
                if (strpos($text_lower, $keyword) !== false) {
                    $score += $pattern['confidence'] * 0.3;
                    $matched[] = $keyword;
                }
            }
            
            if ($score > 0) {
                $score = min(0.9, $score);
                $results[$intent] = [
                    'score' => $score,
                    'source' => 'keywords',
                    'matched' => $matched
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Veri yapısına göre analiz
     */
    private function analyzeByDataStructure($data) {
        $results = [];
        
        if (empty($data)) {
            return $results;
        }
        
        // Veri yapısını analiz et
        $has_file = false;
        $has_binary = false;
        $has_nested = false;
        $array_count = 0;
        
        array_walk_recursive($data, function($value, $key) use (&$has_file, &$has_binary, &$has_nested, &$array_count) {
            if (is_array($value)) {
                $has_nested = true;
                $array_count++;
            }
            
            if (is_string($value) && (strpos($value, 'data:') === 0 || strpos($value, 'base64') !== false)) {
                $has_binary = true;
            }
            
            if (strpos($key, 'file') !== false || strpos($key, 'image') !== false) {
                $has_file = true;
            }
        });
        
        if ($has_file || $has_binary) {
            $results['upload'] = ['score' => 0.85, 'source' => 'data_structure'];
        }
        
        if ($has_nested && $array_count > 2) {
            $results['data_create'] = ['score' => 0.7, 'source' => 'data_structure'];
        }
        
        if (isset($data['query']) || isset($data['search'])) {
            $results['search'] = ['score' => 0.8, 'source' => 'data_structure'];
        }
        
        if (isset($data['username']) && isset($data['password'])) {
            $results['auth'] = ['score' => 0.9, 'source' => 'data_structure'];
        }
        
        return $results;
    }
    
    /**
     * Analizleri birleştir
     */
    private function combineAnalysis($analyses) {
        $combined = [];
        
        foreach ($analyses as $analysis) {
            foreach ($analysis as $intent => $info) {
                if (!isset($combined[$intent])) {
                    $combined[$intent] = [
                        'score' => 0,
                        'sources' => [],
                        'features' => []
                    ];
                }
                
                $combined[$intent]['score'] += $info['score'];
                $combined[$intent]['sources'][] = $info['source'];
                
                if (isset($info['matched'])) {
                    $combined[$intent]['features'][] = $info['matched'];
                }
            }
        }
        
        // Normalize et (maksimum skor 1.0)
        foreach ($combined as $intent => &$info) {
            $info['score'] = min(1.0, $info['score']);
            $info['sources'] = array_unique($info['sources']);
        }
        
        return $combined;
    }
    
    /**
     * Öğrenilmiş niyetleri kontrol et
     */
    private function checkLearnedIntents($endpoint, $method, $data) {
        $key = md5($method . ':' . $endpoint);
        
        if (isset($this->learned_intents[$key])) {
            $learned = $this->learned_intents[$key];
            
            // Zaman aşımı kontrolü (30 gün)
            if (time() - $learned['last_seen'] < 30 * 24 * 3600) {
                return [
                    'intent' => $learned['intent'],
                    'confidence' => $learned['confidence'] * 0.9
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Niyet öğren (feedback ile)
     * 
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $data İstek verileri
     * @param string $correct_intent Doğru niyet
     */
    public function learnIntent($endpoint, $method, $data, $correct_intent) {
        $key = md5($method . ':' . $endpoint);
        
        if (!isset($this->learned_intents[$key])) {
            $this->learned_intents[$key] = [
                'intent' => $correct_intent,
                'confidence' => 0.5,
                'first_seen' => time(),
                'count' => 0
            ];
        }
        
        $this->learned_intents[$key]['last_seen'] = time();
        $this->learned_intents[$key]['count']++;
        
        // Güven skorunu güncelle
        $this->learned_intents[$key]['confidence'] = min(
            0.95,
            $this->learned_intents[$key]['confidence'] + 0.1
        );
        
        $this->saveLearnedIntents();
        
        // Pattern'leri de güncelle
        $this->updatePatterns($endpoint, $data, $correct_intent);
    }
    
    /**
     * Pattern'leri güncelle
     */
    private function updatePatterns($endpoint, $data, $intent) {
        // Endpoint'ten yeni keyword'ler çıkar
        $parts = explode('/', trim($endpoint, '/'));
        
        foreach ($parts as $part) {
            if (strlen($part) > 3 && !is_numeric($part)) {
                $this->addKeywordToIntent($intent, $part);
            }
        }
        
        // Data'dan yeni keyword'ler çıkar
        if (is_array($data)) {
            $keys = array_keys($data);
            foreach ($keys as $key) {
                if (strlen($key) > 3) {
                    $this->addKeywordToIntent($intent, $key);
                }
            }
        }
    }
    
    /**
     * Keyword'ü niyete ekle
     */
    private function addKeywordToIntent($intent, $keyword) {
        $keyword = strtolower($keyword);
        
        if (!isset($this->intent_patterns[$intent])) {
            return;
        }
        
        if (!in_array($keyword, $this->intent_patterns[$intent]['keywords'])) {
            $this->intent_patterns[$intent]['keywords'][] = $keyword;
            $this->saveIntentPatterns();
        }
    }
    
    /**
     * Niyet istatistiklerini al
     */
    public function getIntentStats() {
        $stats = [
            'total_intents' => count($this->intent_patterns),
            'learned_patterns' => count($this->learned_intents),
            'intent_distribution' => []
        ];
        
        // Niyet dağılımını hesapla
        foreach ($this->learned_intents as $learned) {
            $intent = $learned['intent'];
            if (!isset($stats['intent_distribution'][$intent])) {
                $stats['intent_distribution'][$intent] = 0;
            }
            $stats['intent_distribution'][$intent]++;
        }
        
        return $stats;
    }
    
    /**
     * Yeni niyet pattern'i ekle
     * 
     * @param string $intent Niyet adı
     * @param array $keywords Keyword listesi
     * @param float $confidence Güven skoru
     */
    public function addIntentPattern($intent, $keywords, $confidence = 0.8) {
        $this->intent_patterns[$intent] = [
            'keywords' => $keywords,
            'confidence' => $confidence,
            'priority' => count($this->intent_patterns) + 1
        ];
        
        $this->saveIntentPatterns();
    }
}