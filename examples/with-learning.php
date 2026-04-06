<?php
/**
 * API Master - Learning ile Akıllı Routing Örneği
 * @package APIMaster
 * @subpackage Examples
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../CORE/autoloader.php';

class LearningExample {
    private $apiMaster;
    private $learningEngine;
    private $trainingData = [];
    
    public function __construct() {
        $this->apiMaster = new APIMaster_Core();
        $this->learningEngine = new APIMaster_LearningEngine();
    }
    
    // Eğitim verisi oluştur
    public function generateTrainingData() {
        echo "========================================\n";
        echo "Eğitim Verisi Oluşturma\n";
        echo "========================================\n\n";
        
        // Farklı senaryolar için eğitim verisi
        $this->trainingData = [
            // Kod yazma senaryoları
            [
                'input' => 'PHP ile dosya nasıl okunur?',
                'expected_provider' => 'openai',
                'type' => 'code',
                'complexity' => 'medium'
            ],
            [
                'input' => 'Python list comprehension örneği ver',
                'expected_provider' => 'openai',
                'type' => 'code',
                'complexity' => 'easy'
            ],
            [
                'input' => 'React component lifecycle açıkla',
                'expected_provider' => 'openai',
                'type' => 'code',
                'complexity' => 'hard'
            ],
            
            // Yaratıcı yazma senaryoları
            [
                'input' => 'Bir şiir yaz',
                'expected_provider' => 'anthropic',
                'type' => 'creative',
                'complexity' => 'medium'
            ],
            [
                'input' => 'Kısa bir hikaye anlat',
                'expected_provider' => 'anthropic',
                'type' => 'creative',
                'complexity' => 'medium'
            ],
            
            // Analiz senaryoları
            [
                'input' => 'Bu metni analiz et: "Yapay zeka çağı"',
                'expected_provider' => 'google',
                'type' => 'analysis',
                'complexity' => 'hard'
            ],
            [
                'input' => 'Verilen datayı özetle',
                'expected_provider' => 'google',
                'type' => 'analysis',
                'complexity' => 'medium'
            ],
            
            // Hızlı yanıt senaryoları
            [
                'input' => 'Merhaba, nasılsın?',
                'expected_provider' => 'openai',
                'type' => 'chat',
                'complexity' => 'easy'
            ],
            [
                'input' => 'Bugün hava nasıl?',
                'expected_provider' => 'openai',
                'type' => 'chat',
                'complexity' => 'easy'
            ],
            
            // Uzun ve karmaşık senaryolar
            [
                'input' => 'Kuantum bilgisayarlarının geleceği hakkında detaylı bir analiz yap',
                'expected_provider' => 'anthropic',
                'type' => 'analysis',
                'complexity' => 'hard'
            ]
        ];
        
        echo "Toplam " . count($this->trainingData) . " eğitim örneği oluşturuldu.\n\n";
        
        foreach ($this->trainingData as $i => $data) {
            echo "   Örnek " . ($i + 1) . ": " . substr($data['input'], 0, 40) . "...\n";
            echo "      → Provider: " . $data['expected_provider'] . "\n";
            echo "      → Tip: " . $data['type'] . "\n";
        }
        echo "\n";
    }
    
    // Model eğitimi
    public function trainModel() {
        echo "========================================\n";
        echo "Model Eğitimi\n";
        echo "========================================\n\n";
        
        echo "Model eğitimi başlatılıyor...\n";
        
        // Özellik çıkarımı
        $features = [];
        foreach ($this->trainingData as $data) {
            $features[] = $this->extractFeatures($data['input']);
        }
        
        // Model eğitimi
        $result = $this->learningEngine->train([
            'features' => $features,
            'labels' => array_column($this->trainingData, 'expected_provider'),
            'algorithm' => 'random_forest',
            'epochs' => 100,
            'validation_split' => 0.2
        ]);
        
        if ($result['success']) {
            echo "✅ Model eğitimi tamamlandı!\n";
            echo "   Doğruluk: " . ($result['accuracy'] * 100) . "%\n";
            echo "   Kayıp: " . $result['loss'] . "\n";
            echo "   Eğitim süresi: " . $result['training_time'] . "sn\n";
            echo "   Kullanılan özellik sayısı: " . $result['features_count'] . "\n";
        } else {
            echo "❌ Model eğitimi başarısız: " . $result['error'] . "\n";
        }
        echo "\n";
    }
    
    // Özellik çıkarımı
    private function extractFeatures($text) {
        return [
            'length' => strlen($text),
            'word_count' => str_word_count($text),
            'has_code_keywords' => (int)preg_match('/\b(php|python|javascript|code|function|class)\b/i', $text),
            'has_creative_keywords' => (int)preg_match('/\b(şiir|hikaye|yaratıcı|roman|öykü)\b/i', $text),
            'has_analysis_keywords' => (int)preg_match('/\b(analiz|incele|değerlendir|karşılaştır)\b/i', $text),
            'question_mark' => (int)strpos($text, '?'),
            'exclamation_mark' => (int)strpos($text, '!'),
            'complexity_score' => $this->calculateComplexity($text)
        ];
    }
    
    private function calculateComplexity($text) {
        $score = 0;
        $score += strlen($text) > 100 ? 1 : 0;
        $score += str_word_count($text) > 20 ? 1 : 0;
        $score += preg_match('/\b(neden|nasıl|açıkla|detaylı)\b/i', $text) ? 1 : 0;
        return $score;
    }
    
    // Akıllı routing testi
    public function testSmartRouting() {
        echo "========================================\n";
        echo "Akıllı Routing Testi\n";
        echo "========================================\n\n";
        
        $testQueries = [
            'PHP ile database bağlantısı nasıl yapılır?',
            'Bir aşk şiiri yazabilir misin?',
            'Bu metnin duygu analizini yap: "Bugün çok mutluyum"',
            'Merhaba, adım Ahmet',
            'Derin öğrenme ve yapay zeka arasındaki farkları detaylı açıkla'
        ];
        
        echo str_repeat('-', 70) . "\n";
        printf("%-5s | %-30s | %-12s | %-10s | %-8s\n", "#", "Sorgu", "Tahmin", "Doğruluk", "Süre(ms)");
        echo str_repeat('-', 70) . "\n";
        
        foreach ($testQueries as $i => $query) {
            $start = microtime(true);
            
            // Akıllı routing kararı
            $decision = $this->learningEngine->predictRoute([
                'query' => $query,
                'features' => $this->extractFeatures($query)
            ]);
            
            $time = (microtime(true) - $start) * 1000;
            
            printf("%-5s | %-30s | %-12s | %-10s | %-8s\n",
                ($i + 1),
                substr($query, 0, 28) . '...',
                $decision['provider'],
                round($decision['confidence'] * 100, 1) . '%',
                round($time, 2) . 'ms'
            );
        }
        echo str_repeat('-', 70) . "\n\n";
    }
    
    // Reinforcement learning örneği
    public function reinforcementLearningExample() {
        echo "========================================\n";
        echo "Reinforcement Learning Örneği\n";
        echo "========================================\n\n";
        
        // Reinforcement learning ortamı
        $rl = new APIMaster_ReinforcementLearning([
            'actions' => ['openai', 'anthropic', 'google'],
            'states' => ['low', 'medium', 'high'],
            'learning_rate' => 0.1,
            'discount_factor' => 0.95,
            'exploration_rate' => 0.3
        ]);
        
        echo "RL Agent eğitiliyor...\n\n";
        
        // Eğitim epizodları
        $episodes = 100;
        $rewards = [];
        
        for ($episode = 1; $episode <= $episodes; $episode++) {
            $state = $this->getRandomState();
            $totalReward = 0;
            
            for ($step = 1; $step <= 10; $step++) {
                // Action seç
                $action = $rl->selectAction($state);
                
                // Reward hesapla
                $reward = $this->calculateReward($action, $state);
                $totalReward += $reward;
                
                // Yeni state
                $nextState = $this->getNextState($state);
                
                // Q-value güncelle
                $rl->updateQValue($state, $action, $reward, $nextState);
                
                $state = $nextState;
            }
            
            $rewards[] = $totalReward;
            
            // Exploration rate decay
            if ($episode % 20 == 0) {
                $rl->decayExploration(0.95);
                echo "   Episode $episode: Total Reward = $totalReward, Exploration = " . 
                     round($rl->getExplorationRate(), 3) . "\n";
            }
        }
        
        echo "\n✅ Reinforcement Learning eğitimi tamamlandı!\n";
        echo "   Ortalama reward: " . round(array_sum($rewards) / $episodes, 2) . "\n";
        echo "   En iyi reward: " . max($rewards) . "\n";
        echo "   Final exploration rate: " . round($rl->getExplorationRate(), 3) . "\n\n";
        
        // Q-table göster
        echo "Q-Table (Öğrenilen politikalar):\n";
        $qTable = $rl->getQTable();
        foreach ($qTable as $state => $actions) {
            echo "   State: " . $state . "\n";
            foreach ($actions as $action => $value) {
                echo "      → " . $action . ": " . round($value, 3) . "\n";
            }
        }
        echo "\n";
    }
    
    private function getRandomState() {
        $states = ['low', 'medium', 'high'];
        return $states[array_rand($states)];
    }
    
    private function getNextState($currentState) {
        $probabilities = [
            'low' => ['low' => 0.6, 'medium' => 0.3, 'high' => 0.1],
            'medium' => ['low' => 0.2, 'medium' => 0.6, 'high' => 0.2],
            'high' => ['low' => 0.1, 'medium' => 0.3, 'high' => 0.6]
        ];
        
        $rand = mt_rand() / mt_getrandmax();
        $cumulative = 0;
        
        foreach ($probabilities[$currentState] as $state => $prob) {
            $cumulative += $prob;
            if ($rand <= $cumulative) {
                return $state;
            }
        }
        
        return 'medium';
    }
    
    private function calculateReward($action, $state) {
        $baseRewards = [
            'openai' => ['low' => 1.0, 'medium' => 0.8, 'high' => 0.5],
            'anthropic' => ['low' => 0.7, 'medium' => 0.9, 'high' => 0.9],
            'google' => ['low' => 0.5, 'medium' => 0.7, 'high' => 1.0]
        ];
        
        return $baseRewards[$action][$state] + (mt_rand() / mt_getrandmax() * 0.2);
    }
    
    // Model performans metrikleri
    public function showModelMetrics() {
        echo "========================================\n";
        echo "Model Performans Metrikleri\n";
        echo "========================================\n\n";
        
        $metrics = $this->learningEngine->getMetrics();
        
        echo "Sınıflandırma Metrikleri:\n";
        echo "   Accuracy: " . ($metrics['accuracy'] * 100) . "%\n";
        echo "   Precision: " . ($metrics['precision'] * 100) . "%\n";
        echo "   Recall: " . ($metrics['recall'] * 100) . "%\n";
        echo "   F1-Score: " . ($metrics['f1_score'] * 100) . "%\n";
        
        echo "\nConfusion Matrix:\n";
        echo "                 Tahmin\n";
        echo "                O  A  G\n";
        echo "   Gerçek  O   " . implode('  ', $metrics['confusion_matrix']['openai']) . "\n";
        echo "           A   " . implode('  ', $metrics['confusion_matrix']['anthropic']) . "\n";
        echo "           G   " . implode('  ', $metrics['confusion_matrix']['google']) . "\n";
        
        echo "\nFeature Importance:\n";
        foreach ($metrics['feature_importance'] as $feature => $importance) {
            echo "   " . $feature . ": " . round($importance * 100, 1) . "%\n";
        }
        
        echo "\nModel Bilgileri:\n";
        echo "   Model tipi: " . $metrics['model_type'] . "\n";
        echo "   Eğitim süresi: " . $metrics['training_time'] . "sn\n";
        echo "   Son güncelleme: " . $metrics['last_updated'] . "\n";
        echo "   Toplam örnek: " . number_format($metrics['total_samples']) . "\n";
    }
    
    // Model export
    public function exportModel() {
        echo "========================================\n";
        echo "Model Export\n";
        echo "========================================\n\n";
        
        $exportPath = __DIR__ . '/../learning/models/routing_model_' . date('Ymd_His') . '.json';
        
        $result = $this->learningEngine->exportModel($exportPath);
        
        if ($result['success']) {
            echo "✅ Model export edildi: " . $exportPath . "\n";
            echo "   Dosya boyutu: " . round($result['file_size'] / 1024, 2) . " KB\n";
        } else {
            echo "❌ Model export edilemedi: " . $result['error'] . "\n";
        }
    }
    
    // Gerçek zamanlı öğrenme
    public function realTimeLearning() {
        echo "========================================\n";
        echo "Gerçek Zamanlı Öğrenme\n";
        echo "========================================\n\n";
        
        $liveQueries = [
            'Bana bir Python kodu yaz',
            'Kısa bir hikaye anlat',
            'Bu veriyi analiz et: [1,2,3,4,5]',
            'Merhaba, yardıma ihtiyacım var',
            'Karmaşık bir algoritma açıkla'
        ];
        
        echo "Gerçek zamanlı öğrenme başlatılıyor...\n\n";
        
        foreach ($liveQueries as $query) {
            echo "Sorgu: " . $query . "\n";
            
            // Prediction yap
            $prediction = $this->learningEngine->predict($query);
            echo "   Tahmin: " . $prediction['provider'] . 
                 " (güven: " . round($prediction['confidence'] * 100, 1) . "%)\n";
            
            // Gerçek sonucu simüle et
            $actualProvider = $this->getActualProvider($query);
            echo "   Gerçek: " . $actualProvider . "\n";
            
            // Modeli güncelle (gerçek zamanlı öğrenme)
            if ($prediction['provider'] !== $actualProvider) {
                $this->learningEngine->updateModel($query, $actualProvider);
                echo "   🔄 Model güncellendi (yanlış tahmin düzeltildi)\n";
            } else {
                echo "   ✅ Doğru tahmin\n";
            }
            
            echo "\n";
            usleep(500000); // 0.5 saniye bekle
        }
        
        echo "Gerçek zamanlı öğrenme tamamlandı!\n";
    }
    
    private function getActualProvider($query) {
        // Gerçek provider seçimi (simülasyon)
        if (preg_match('/\b(kod|python|php|javascript)\b/i', $query)) {
            return 'openai';
        } elseif (preg_match('/\b(hikaye|şiir|yaratıcı)\b/i', $query)) {
            return 'anthropic';
        } elseif (preg_match('/\b(analiz|veri|incele)\b/i', $query)) {
            return 'google';
        } else {
            return 'openai';
        }
    }
}

// Learning örneğini çalıştır
$example = new LearningExample();

// Eğitim verisi oluştur
$example->generateTrainingData();

// Model eğit
$example->trainModel();

// Akıllı routing test et
$example->testSmartRouting();

// Reinforcement learning
$example->reinforcementLearningExample();

// Model metrikleri
$example->showModelMetrics();

// Model export
$example->exportModel();

// Gerçek zamanlı öğrenme
$example->realTimeLearning();

echo "\n========================================\n";
echo "Learning örneği tamamlandı!\n";
echo "========================================\n";