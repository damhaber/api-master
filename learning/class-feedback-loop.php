<?php
/**
 * APIMaster Feedback Loop
 * 
 * Geri bildirim döngüsü yönetim sistemi
 * 
 * @package APIMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_FeedbackLoop {
    
    /**
     * @var array Geri bildirim konfigürasyonu
     */
    private $config;
    
    /**
     * @var array Geri bildirim havuzu
     */
    private $feedback_pool = [];
    
    /**
     * @var string Geri bildirim yolu
     */
    private $feedback_path;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->feedback_path = APIMASTER_PATH . 'data/feedback/';
        $this->initFeedbackSystem();
    }
    
    /**
     * Geri bildirim sistemini başlat
     */
    private function initFeedbackSystem() {
        if (!file_exists($this->feedback_path)) {
            mkdir($this->feedback_path, 0755, true);
        }
        
        $this->loadConfig();
        $this->loadFeedbackPool();
    }
    
    /**
     * Konfigürasyonu yükle
     */
    private function loadConfig() {
        $config_file = $this->feedback_path . 'config.json';
        
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
            'batch_size' => 50,
            'processing_interval' => 300, // 5 dakika
            'positive_threshold' => 0.7,
            'negative_threshold' => 0.3,
            'max_queue_size' => 1000,
            'auto_learn' => true,
            'feedback_weights' => [
                'user' => 0.8,
                'system' => 0.6,
                'auto' => 0.4
            ]
        ];
    }
    
    /**
     * Konfigürasyonu kaydet
     */
    private function saveConfig() {
        file_put_contents(
            $this->feedback_path . 'config.json',
            json_encode($this->config, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Geri bildirim havuzunu yükle
     */
    private function loadFeedbackPool() {
        $pool_file = $this->feedback_path . 'pool.json';
        
        if (file_exists($pool_file)) {
            $this->feedback_pool = json_decode(file_get_contents($pool_file), true);
        } else {
            $this->feedback_pool = [];
            $this->saveFeedbackPool();
        }
    }
    
    /**
     * Geri bildirim havuzunu kaydet
     */
    private function saveFeedbackPool() {
        file_put_contents(
            $this->feedback_path . 'pool.json',
            json_encode($this->feedback_pool, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Yeni geri bildirim ekle
     * 
     * @param array $feedback Geri bildirim verisi
     * @return string Feedback ID
     */
    public function addFeedback($feedback) {
        $feedback_id = uniqid('fb_', true);
        
        $feedback_entry = [
            'id' => $feedback_id,
            'timestamp' => time(),
            'processed' => false,
            'data' => $feedback
        ];
        
        $this->feedback_pool[$feedback_id] = $feedback_entry;
        
        // Queue limit kontrolü
        if (count($this->feedback_pool) > $this->config['max_queue_size']) {
            $this->cleanOldFeedback();
        }
        
        $this->saveFeedbackPool();
        
        // Otomatik işleme
        if (count($this->feedback_pool) >= $this->config['batch_size']) {
            $this->processBatch();
        }
        
        return $feedback_id;
    }
    
    /**
     * Eski geri bildirimleri temizle
     */
    private function cleanOldFeedback() {
        // 7 günden eski ve işlenmemiş feedback'leri temizle
        $cutoff = time() - (7 * 24 * 3600);
        
        foreach ($this->feedback_pool as $id => $feedback) {
            if (!$feedback['processed'] && $feedback['timestamp'] < $cutoff) {
                unset($this->feedback_pool[$id]);
            }
        }
        
        // Hala çok fazlaysa, en eskileri temizle
        if (count($this->feedback_pool) > $this->config['max_queue_size']) {
            uasort($this->feedback_pool, function($a, $b) {
                return $a['timestamp'] <=> $b['timestamp'];
            });
            
            $this->feedback_pool = array_slice($this->feedback_pool, -$this->config['max_queue_size'], null, true);
        }
    }
    
    /**
     * Geri bildirim batch'ini işle
     */
    public function processBatch() {
        $unprocessed = array_filter($this->feedback_pool, function($fb) {
            return !$fb['processed'];
        });
        
        if (empty($unprocessed)) {
            return false;
        }
        
        // Batch boyutuna kadar işle
        $to_process = array_slice($unprocessed, 0, $this->config['batch_size'], true);
        $results = [];
        
        foreach ($to_process as $id => $feedback) {
            $result = $this->processFeedback($feedback['data']);
            $results[$id] = $result;
            
            if ($result['success']) {
                $this->feedback_pool[$id]['processed'] = true;
                $this->feedback_pool[$id]['processed_at'] = time();
                $this->feedback_pool[$id]['result'] = $result;
            }
        }
        
        $this->saveFeedbackPool();
        
        return [
            'processed' => count($to_process),
            'successful' => count(array_filter($results, function($r) { return $r['success']; })),
            'results' => $results
        ];
    }
    
    /**
     * Geri bildirimi işle
     * 
     * @param array $feedback Geri bildirim verisi
     * @return array
     */
    public function processFeedback($feedback) {
        $result = [
            'success' => false,
            'action' => null,
            'confidence' => 0,
            'learnings' => []
        ];
        
        // Feedback tipine göre işle
        $type = $feedback['type'] ?? 'general';
        
        switch ($type) {
            case 'api_response':
                $result = $this->processAPIResponseFeedback($feedback);
                break;
                
            case 'user_rating':
                $result = $this->processUserRating($feedback);
                break;
                
            case 'error_report':
                $result = $this->processErrorReport($feedback);
                break;
                
            case 'performance':
                $result = $this->processPerformanceFeedback($feedback);
                break;
                
            case 'suggestion':
                $result = $this->processSuggestion($feedback);
                break;
                
            default:
                $result = $this->processGeneralFeedback($feedback);
        }
        
        // Otomatik öğrenme
        if ($this->config['auto_learn'] && $result['success']) {
            $this->applyLearning($feedback, $result);
        }
        
        return $result;
    }
    
    /**
     * API yanıt feedback'ini işle
     */
    private function processAPIResponseFeedback($feedback) {
        $result = [
            'success' => true,
            'action' => 'analyze_response',
            'confidence' => 0,
            'learnings' => []
        ];
        
        $response_data = $feedback['data'] ?? [];
        $expected = $feedback['expected'] ?? null;
        $actual = $response_data['response'] ?? null;
        
        if ($expected && $actual) {
            // Yanıt doğruluğunu hesapla
            $accuracy = $this->calculateResponseAccuracy($expected, $actual);
            $result['confidence'] = $accuracy;
            
            if ($accuracy < $this->config['negative_threshold']) {
                $result['action'] = 'retrain_model';
                $result['learnings'][] = [
                    'type' => 'inaccurate_response',
                    'severity' => 'high',
                    'accuracy' => $accuracy
                ];
            } elseif ($accuracy > $this->config['positive_threshold']) {
                $result['action'] = 'reinforce_pattern';
                $result['learnings'][] = [
                    'type' => 'accurate_response',
                    'severity' => 'low',
                    'accuracy' => $accuracy
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Yanıt doğruluğunu hesapla
     */
    private function calculateResponseAccuracy($expected, $actual) {
        if (!is_array($expected) || !is_array($actual)) {
            return $expected == $actual ? 1.0 : 0.0;
        }
        
        $expected_json = json_encode($expected);
        $actual_json = json_encode($actual);
        
        similar_text($expected_json, $actual_json, $percent);
        
        return $percent / 100;
    }
    
    /**
     * Kullanıcı derecelendirmesini işle
     */
    private function processUserRating($feedback) {
        $rating = $feedback['rating'] ?? 0; // 1-5 arası
        $comment = $feedback['comment'] ?? '';
        
        $normalized_score = $rating / 5; // 0-1 arasına çevir
        
        $result = [
            'success' => true,
            'action' => $normalized_score > 0.7 ? 'positive_reinforcement' : 'negative_correction',
            'confidence' => $normalized_score,
            'learnings' => []
        ];
        
        // Yorum analizi
        if (!empty($comment)) {
            $sentiment = $this->analyzeSentiment($comment);
            $result['learnings'][] = [
                'type' => 'sentiment_analysis',
                'sentiment' => $sentiment,
                'rating' => $rating
            ];
        }
        
        // Düşük puanlar için özel işlem
        if ($rating <= 2) {
            $result['action'] = 'urgent_review';
            $result['learnings'][] = [
                'type' => 'low_rating',
                'severity' => 'critical',
                'rating' => $rating
            ];
        }
        
        return $result;
    }
    
    /**
     * Sentiment analizi
     */
    private function analyzeSentiment($text) {
        $positive_keywords = ['good', 'great', 'excellent', 'perfect', 'fast', 'easy', 'helpful'];
        $negative_keywords = ['bad', 'poor', 'slow', 'error', 'bug', 'issue', 'problem', 'fail'];
        
        $text_lower = strtolower($text);
        $score = 0;
        
        foreach ($positive_keywords as $keyword) {
            if (strpos($text_lower, $keyword) !== false) {
                $score += 0.2;
            }
        }
        
        foreach ($negative_keywords as $keyword) {
            if (strpos($text_lower, $keyword) !== false) {
                $score -= 0.2;
            }
        }
        
        // Normalize et -1 ile 1 arası
        return max(-1, min(1, $score));
    }
    
    /**
     * Hata raporunu işle
     */
    private function processErrorReport($feedback) {
        $error = $feedback['error'] ?? [];
        $context = $feedback['context'] ?? [];
        
        $result = [
            'success' => true,
            'action' => 'log_error_pattern',
            'confidence' => 0.9,
            'learnings' => []
        ];
        
        // Hata pattern'ini çıkar
        $error_pattern = [
            'code' => $error['code'] ?? 'unknown',
            'message' => $error['message'] ?? '',
            'type' => $error['type'] ?? 'general'
        ];
        
        $result['learnings'][] = [
            'type' => 'error_pattern',
            'pattern' => $error_pattern,
            'context' => $context,
            'timestamp' => time()
        ];
        
        // Kritik hatalar
        if (isset($error['critical']) && $error['critical'] === true) {
            $result['action'] = 'critical_error_alert';
            $result['learnings'][] = [
                'type' => 'critical_error',
                'severity' => 'critical'
            ];
        }
        
        return $result;
    }
    
    /**
     * Performans feedback'ini işle
     */
    private function processPerformanceFeedback($feedback) {
        $metrics = $feedback['metrics'] ?? [];
        
        $result = [
            'success' => true,
            'action' => 'update_performance_profile',
            'confidence' => 0,
            'learnings' => []
        ];
        
        // Performans metriklerini analiz et
        if (isset($metrics['response_time'])) {
            if ($metrics['response_time'] > 2000) { // 2 saniye üzeri
                $result['learnings'][] = [
                    'type' => 'slow_response',
                    'threshold' => 'high',
                    'value' => $metrics['response_time']
                ];
                $result['action'] = 'optimize_endpoint';
            }
        }
        
        if (isset($metrics['success_rate'])) {
            $result['confidence'] = $metrics['success_rate'];
            
            if ($metrics['success_rate'] < 0.8) {
                $result['learnings'][] = [
                    'type' => 'low_success_rate',
                    'rate' => $metrics['success_rate']
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Öneriyi işle
     */
    private function processSuggestion($feedback) {
        $suggestion = $feedback['suggestion'] ?? '';
        $category = $feedback['category'] ?? 'general';
        
        $result = [
            'success' => true,
            'action' => 'evaluate_suggestion',
            'confidence' => 0.5,
            'learnings' => []
        ];
        
        // Öneriyi kaydet
        $suggestions_file = $this->feedback_path . 'suggestions.json';
        $suggestions = [];
        
        if (file_exists($suggestions_file)) {
            $suggestions = json_decode(file_get_contents($suggestions_file), true);
        }
        
        $suggestions[] = [
            'id' => uniqid(),
            'timestamp' => time(),
            'category' => $category,
            'suggestion' => $suggestion,
            'status' => 'pending'
        ];
        
        file_put_contents($suggestions_file, json_encode($suggestions, JSON_PRETTY_PRINT));
        
        $result['learnings'][] = [
            'type' => 'user_suggestion',
            'category' => $category
        ];
        
        return $result;
    }
    
    /**
     * Genel feedback'i işle
     */
    private function processGeneralFeedback($feedback) {
        return [
            'success' => true,
            'action' => 'log_feedback',
            'confidence' => 0.5,
            'learnings' => [
                [
                    'type' => 'general_feedback',
                    'data' => $feedback
                ]
            ]
        ];
    }
    
    /**
     * Öğrenmeyi uygula
     */
    private function applyLearning($feedback, $result) {
        $learning_file = $this->feedback_path . 'learnings.json';
        $learnings = [];
        
        if (file_exists($learning_file)) {
            $learnings = json_decode(file_get_contents($learning_file), true);
        }
        
        foreach ($result['learnings'] as $learning) {
            $learning['feedback_id'] = $feedback['id'] ?? uniqid();
            $learning['applied_at'] = time();
            $learnings[] = $learning;
        }
        
        // Son 1000 öğrenmeyi tut
        if (count($learnings) > 1000) {
            $learnings = array_slice($learnings, -1000);
        }
        
        file_put_contents($learning_file, json_encode($learnings, JSON_PRETTY_PRINT));
    }
    
    /**
     * Geri bildirim istatistiklerini al
     */
    public function getFeedbackStats() {
        $stats = [
            'total_feedback' => count($this->feedback_pool),
            'processed' => count(array_filter($this->feedback_pool, function($fb) {
                return $fb['processed'];
            })),
            'pending' => count(array_filter($this->feedback_pool, function($fb) {
                return !$fb['processed'];
            })),
            'by_type' => []
        ];
        
        // Tip bazında istatistik
        foreach ($this->feedback_pool as $feedback) {
            $type = $feedback['data']['type'] ?? 'general';
            
            if (!isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = 0;
            }
            
            $stats['by_type'][$type]++;
        }
        
        return $stats;
    }
    
    /**
     * Geri bildirimleri temizle
     */
    public function clearProcessed() {
        foreach ($this->feedback_pool as $id => $feedback) {
            if ($feedback['processed']) {
                unset($this->feedback_pool[$id]);
            }
        }
        
        $this->saveFeedbackPool();
        
        return true;
    }
    
    /**
     * Feedback geçmişini al
     * 
     * @param int $limit Limit
     * @param bool $only_processed Sadece işlenmişler
     */
    public function getFeedbackHistory($limit = 100, $only_processed = false) {
        $history = $this->feedback_pool;
        
        if ($only_processed) {
            $history = array_filter($history, function($fb) {
                return $fb['processed'];
            });
        }
        
        // Zamana göre sırala (en yeni önce)
        uasort($history, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });
        
        return array_slice($history, 0, $limit);
    }
}