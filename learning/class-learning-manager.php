<?php
/**
 * APIMaster Learning Manager
 * BU BİR WORDPRESS MODÜLÜDÜR, PLUGIN DEĞİLDİR
 * 
 * Yapay zeka öğrenme ve adaptasyon yönetim sistemi
 */

if (!defined('ABSPATH')) {
    // Normal PHP çalışması
}

class APIMaster_LearningManager {
    
    private $config;
    private $modules = [];
    private $learning_path;
    
    public function __construct() {
        // Sabit API_MASTER_DATA_DIR kullan (tanımlı değilse oluştur)
        $dataDir = defined('API_MASTER_DATA_DIR') ? API_MASTER_DATA_DIR : dirname(__DIR__) . '/data';
        $this->learning_path = $dataDir . '/learning/';
        $this->initLearningSystem();
    }
    
    private function initLearningSystem() {
        if (!file_exists($this->learning_path)) {
            mkdir($this->learning_path, 0755, true);
        }
        
        $this->loadConfig();
        $this->loadModules();
    }
    
    private function loadConfig() {
        $config_file = $this->learning_path . 'config.json';
        
        if (file_exists($config_file)) {
            $this->config = json_decode(file_get_contents($config_file), true);
        } else {
            $this->config = $this->getDefaultConfig();
            $this->saveConfig();
        }
    }
    
    private function getDefaultConfig() {
        return [
            'learning_rate' => 0.7,
            'feedback_weight' => 0.8,
            'adaptation_threshold' => 0.6,
            'max_history' => 1000,
            'auto_train' => true,
            'consolidation_interval' => 3600,
            'min_confidence' => 0.75
        ];
    }
    
    private function saveConfig() {
        file_put_contents(
            $this->learning_path . 'config.json',
            json_encode($this->config, JSON_PRETTY_PRINT)
        );
    }
    
    private function loadModules() {
        $module_files = [
            'intent-analyzer' => 'APIMaster_IntentAnalyzer',
            'feedback-loop' => 'APIMaster_FeedbackLoop',
            'user-profiler' => 'APIMaster_UserProfiler',
            'performance-tracker' => 'APIMaster_PerformanceTracker',
            'model-trainer' => 'APIMaster_ModelTrainer',
            'adaptive-router' => 'APIMaster_AdaptiveRouter'
        ];
        
        foreach ($module_files as $file => $class) {
            $file_path = dirname(__DIR__) . '/learning/class-' . $file . '.php';
            
            if (file_exists($file_path)) {
                require_once $file_path;
                
                if (class_exists($class)) {
                    $this->modules[$file] = new $class();
                }
            }
        }
    }
    
    public function learnExperience($type, $data, $success = 1.0) {
        $experience = [
            'id' => uniqid(),
            'timestamp' => time(),
            'type' => $type,
            'data' => $data,
            'success' => $success,
            'learned' => false
        ];
        
        $this->saveExperience($experience);
        
        if ($success >= $this->config['min_confidence']) {
            return $this->processExperience($experience);
        }
        
        return true;
    }
    
    private function saveExperience($experience) {
        $history_file = $this->learning_path . 'experience_history.json';
        $history = [];
        
        if (file_exists($history_file)) {
            $history = json_decode(file_get_contents($history_file), true);
        }
        
        array_unshift($history, $experience);
        
        if (count($history) > $this->config['max_history']) {
            $history = array_slice($history, 0, $this->config['max_history']);
        }
        
        file_put_contents($history_file, json_encode($history, JSON_PRETTY_PRINT));
    }
    
    private function processExperience($experience) {
        $processed = false;
        
        switch ($experience['type']) {
            case 'api_call':
                $processed = $this->learnFromAPI($experience);
                break;
            case 'user_feedback':
                $processed = $this->learnFromFeedback($experience);
                break;
            case 'performance':
                $processed = $this->learnFromPerformance($experience);
                break;
            case 'error':
                $processed = $this->learnFromError($experience);
                break;
        }
        
        return $processed;
    }
    
    private function learnFromAPI($experience) {
        return true;
    }
    
    private function learnFromFeedback($experience) {
        return true;
    }
    
    private function learnFromPerformance($experience) {
        return true;
    }
    
    private function learnFromError($experience) {
        $error_pattern = $this->extractErrorPattern($experience['data']);
        
        if ($error_pattern) {
            $this->storeErrorPattern($error_pattern, $experience['data']);
        }
        
        return true;
    }
    
    private function extractErrorPattern($error_data) {
        if (!isset($error_data['message'])) {
            return null;
        }
        
        $pattern = preg_replace('/\[.*?\]/', '[VAR]', $error_data['message']);
        $pattern = preg_replace('/\d+/', '[NUM]', $pattern);
        
        return $pattern;
    }
    
    private function storeErrorPattern($pattern, $error_data) {
        $errors_file = $this->learning_path . 'error_patterns.json';
        $patterns = [];
        
        if (file_exists($errors_file)) {
            $patterns = json_decode(file_get_contents($errors_file), true);
        }
        
        if (!isset($patterns[$pattern])) {
            $patterns[$pattern] = [
                'count' => 0,
                'first_seen' => time(),
                'last_seen' => time(),
                'solutions' => []
            ];
        }
        
        $patterns[$pattern]['count']++;
        $patterns[$pattern]['last_seen'] = time();
        
        file_put_contents($errors_file, json_encode($patterns, JSON_PRETTY_PRINT));
    }
    
    public function getRecommendation($context, $params = []) {
        return [
            'context' => $context,
            'suggestions' => [],
            'confidence' => 0
        ];
    }
    
    public function getLearningStats() {
        $stats = [
            'total_experiences' => 0,
            'learned_patterns' => 0,
            'active_modules' => count($this->modules),
            'learning_rate' => $this->config['learning_rate']
        ];
        
        $history_file = $this->learning_path . 'experience_history.json';
        if (file_exists($history_file)) {
            $history = json_decode(file_get_contents($history_file), true);
            $stats['total_experiences'] = count($history);
        }
        
        $errors_file = $this->learning_path . 'error_patterns.json';
        if (file_exists($errors_file)) {
            $patterns = json_decode(file_get_contents($errors_file), true);
            $stats['learned_patterns'] = count($patterns);
        }
        
        return $stats;
    }
    
    public function cleanup() {
        $history_file = $this->learning_path . 'experience_history.json';
        if (file_exists($history_file)) {
            $history = json_decode(file_get_contents($history_file), true);
            
            $cutoff = time() - (30 * 24 * 3600);
            $history = array_filter($history, function($exp) use ($cutoff) {
                return $exp['timestamp'] > $cutoff;
            });
            
            file_put_contents($history_file, json_encode(array_values($history), JSON_PRETTY_PRINT));
        }
        
        return true;
    }
}