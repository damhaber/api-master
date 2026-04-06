<?php
/**
 * APIMaster Model Trainer
 * 
 * Makine öğrenmesi model eğitim ve yönetim sistemi
 * 
 * @package APIMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_ModelTrainer {
    
    /**
     * @var array Model konfigürasyonu
     */
    private $config;
    
    /**
     * @var array Eğitilmiş modeller
     */
    private $models = [];
    
    /**
     * @var array Eğitim verileri
     */
    private $training_data = [];
    
    /**
     * @var string Model yolu
     */
    private $model_path;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->model_path = APIMASTER_PATH . 'data/models/';
        $this->initModelSystem();
    }
    
    /**
     * Model sistemini başlat
     */
    private function initModelSystem() {
        if (!file_exists($this->model_path)) {
            mkdir($this->model_path, 0755, true);
        }
        
        $this->loadConfig();
        $this->loadModels();
        $this->loadTrainingData();
    }
    
    /**
     * Konfigürasyonu yükle
     */
    private function loadConfig() {
        $config_file = $this->model_path . 'config.json';
        
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
            'models' => [
                'provider_selector' => [
                    'enabled' => true,
                    'version' => '1.0',
                    'last_trained' => null,
                    'accuracy' => 0
                ],
                'intent_classifier' => [
                    'enabled' => true,
                    'version' => '1.0',
                    'last_trained' => null,
                    'accuracy' => 0
                ],
                'error_predictor' => [
                    'enabled' => true,
                    'version' => '1.0',
                    'last_trained' => null,
                    'accuracy' => 0
                ],
                'performance_optimizer' => [
                    'enabled' => true,
                    'version' => '1.0',
                    'last_trained' => null,
                    'accuracy' => 0
                ]
            ],
            'training' => [
                'min_samples' => 100,
                'test_split' => 0.2,
                'cross_validation_folds' => 5,
                'auto_retrain_interval' => 86400, // 24 saat
                'max_features' => 50
            ],
            'algorithms' => [
                'provider_selector' => 'random_forest',
                'intent_classifier' => 'naive_bayes',
                'error_predictor' => 'logistic_regression',
                'performance_optimizer' => 'linear_regression'
            ]
        ];
    }
    
    /**
     * Konfigürasyonu kaydet
     */
    private function saveConfig() {
        file_put_contents(
            $this->model_path . 'config.json',
            json_encode($this->config, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Modelleri yükle
     */
    private function loadModels() {
        foreach ($this->config['models'] as $model_name => $model_config) {
            $model_file = $this->model_path . $model_name . '.json';
            
            if (file_exists($model_file)) {
                $this->models[$model_name] = json_decode(file_get_contents($model_file), true);
            } else {
                $this->models[$model_name] = $this->createEmptyModel($model_name);
                $this->saveModel($model_name);
            }
        }
    }
    
    /**
     * Boş model oluştur
     */
    private function createEmptyModel($model_name) {
        return [
            'name' => $model_name,
            'version' => $this->config['models'][$model_name]['version'],
            'created_at' => time(),
            'last_updated' => time(),
            'features' => [],
            'weights' => [],
            'parameters' => [],
            'statistics' => [
                'total_training_samples' => 0,
                'accuracy' => 0,
                'precision' => 0,
                'recall' => 0,
                'f1_score' => 0
            ]
        ];
    }
    
    /**
     * Modeli kaydet
     */
    private function saveModel($model_name) {
        if (isset($this->models[$model_name])) {
            file_put_contents(
                $this->model_path . $model_name . '.json',
                json_encode($this->models[$model_name], JSON_PRETTY_PRINT)
            );
        }
    }
    
    /**
     * Eğitim verilerini yükle
     */
    private function loadTrainingData() {
        $training_file = $this->model_path . 'training_data.json';
        
        if (file_exists($training_file)) {
            $this->training_data = json_decode(file_get_contents($training_file), true);
        } else {
            $this->training_data = [];
            $this->saveTrainingData();
        }
    }
    
    /**
     * Eğitim verilerini kaydet
     */
    private function saveTrainingData() {
        file_put_contents(
            $this->model_path . 'training_data.json',
            json_encode($this->training_data, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Yeni eğitim verisi ekle
     * 
     * @param string $model_name Model adı
     * @param array $features Özellikler
     * @param mixed $label Etiket (beklenen çıktı)
     * @return bool
     */
    public function addTrainingSample($model_name, $features, $label) {
        if (!isset($this->training_data[$model_name])) {
            $this->training_data[$model_name] = [];
        }
        
        $sample = [
            'id' => uniqid(),
            'timestamp' => time(),
            'features' => $features,
            'label' => $label,
            'used_for_training' => false
        ];
        
        $this->training_data[$model_name][] = $sample;
        
        // Veri boyutunu sınırla (son 10000 örnek)
        if (count($this->training_data[$model_name]) > 10000) {
            $this->training_data[$model_name] = array_slice($this->training_data[$model_name], -10000);
        }
        
        $this->saveTrainingData();
        
        // Otomatik eğitim kontrolü
        if (count($this->training_data[$model_name]) >= $this->config['training']['min_samples']) {
            $this->checkAutoTraining($model_name);
        }
        
        return true;
    }
    
    /**
     * Otomatik eğitim kontrolü
     */
    private function checkAutoTraining($model_name) {
        $model_config = $this->config['models'][$model_name];
        
        if (!$model_config['enabled']) {
            return false;
        }
        
        $last_trained = $model_config['last_trained'] ?? 0;
        $interval = $this->config['training']['auto_retrain_interval'];
        
        if (time() - $last_trained >= $interval) {
            return $this->trainModel($model_name);
        }
        
        return false;
    }
    
    /**
     * Model eğitimi yap
     * 
     * @param string $model_name Model adı
     * @return array
     */
    public function trainModel($model_name) {
        if (!isset($this->training_data[$model_name])) {
            return ['success' => false, 'error' => 'No training data available'];
        }
        
        $samples = $this->training_data[$model_name];
        $total_samples = count($samples);
        
        if ($total_samples < $this->config['training']['min_samples']) {
            return [
                'success' => false, 
                'error' => "Insufficient samples. Need {$this->config['training']['min_samples']}, have {$total_samples}"
            ];
        }
        
        // Eğitim ve test setlerine ayır
        $split = $this->splitData($samples);
        $training_set = $split['training'];
        $test_set = $split['test'];
        
        // Algoritma seç
        $algorithm = $this->config['algorithms'][$model_name];
        
        // Model eğitimi
        $model = $this->trainAlgorithm($model_name, $algorithm, $training_set);
        
        // Model değerlendirmesi
        $evaluation = $this->evaluateModel($model, $test_set);
        
        // Modeli güncelle
        $this->updateModel($model_name, $model, $evaluation);
        
        // Eğitim verilerini işaretle
        $this->markTrainingDataAsUsed($model_name);
        
        return [
            'success' => true,
            'model' => $model_name,
            'samples_used' => count($training_set),
            'test_samples' => count($test_set),
            'evaluation' => $evaluation
        ];
    }
    
    /**
     * Veriyi eğitim/test olarak böl
     */
    private function splitData($samples) {
        $test_ratio = $this->config['training']['test_split'];
        $test_count = (int)(count($samples) * $test_ratio);
        
        // Karıştır
        shuffle($samples);
        
        $test_set = array_slice($samples, 0, $test_count);
        $training_set = array_slice($samples, $test_count);
        
        return [
            'training' => $training_set,
            'test' => $test_set
        ];
    }
    
    /**
     * Algoritma ile eğitim yap
     */
    private function trainAlgorithm($model_name, $algorithm, $training_set) {
        switch ($algorithm) {
            case 'random_forest':
                return $this->trainRandomForest($model_name, $training_set);
            case 'naive_bayes':
                return $this->trainNaiveBayes($model_name, $training_set);
            case 'logistic_regression':
                return $this->trainLogisticRegression($model_name, $training_set);
            case 'linear_regression':
                return $this->trainLinearRegression($model_name, $training_set);
            default:
                return $this->trainDefault($model_name, $training_set);
        }
    }
    
    /**
     * Random Forest eğitimi
     */
    private function trainRandomForest($model_name, $training_set) {
        $model = [
            'trees' => [],
            'feature_importance' => [],
            'num_trees' => 10
        ];
        
        // Özellikleri çıkar
        $features = $this->extractFeatures($training_set);
        $model['features'] = array_keys($features);
        
        // Decision tree'ler oluştur
        for ($i = 0; $i < $model['num_trees']; $i++) {
            // Bootstrap sampling
            $bootstrap_samples = $this->bootstrapSample($training_set);
            $tree = $this->buildDecisionTree($bootstrap_samples, $model['features']);
            $model['trees'][] = $tree;
        }
        
        // Özellik önem skorlarını hesapla
        $model['feature_importance'] = $this->calculateFeatureImportance($model);
        
        return $model;
    }
    
    /**
     * Naive Bayes eğitimi
     */
    private function trainNaiveBayes($model_name, $training_set) {
        $model = [
            'class_probabilities' => [],
            'feature_probabilities' => [],
            'classes' => []
        ];
        
        // Sınıfları topla
        $classes = [];
        foreach ($training_set as $sample) {
            $label = is_array($sample['label']) ? json_encode($sample['label']) : (string)$sample['label'];
            $classes[$label] = ($classes[$label] ?? 0) + 1;
        }
        
        $total_samples = count($training_set);
        $model['classes'] = array_keys($classes);
        
        // Sınıf olasılıklarını hesapla
        foreach ($classes as $class => $count) {
            $model['class_probabilities'][$class] = $count / $total_samples;
        }
        
        // Özellik olasılıklarını hesapla (Laplace smoothing ile)
        foreach ($model['classes'] as $class) {
            $class_samples = array_filter($training_set, function($sample) use ($class) {
                $label = is_array($sample['label']) ? json_encode($sample['label']) : (string)$sample['label'];
                return $label === $class;
            });
            
            foreach ($training_set[0]['features'] as $feature_name => $feature_value) {
                $values = array_column($class_samples, 'features');
                $value_counts = [];
                
                foreach ($values as $value_set) {
                    $val = $value_set[$feature_name] ?? null;
                    if ($val !== null) {
                        $key = is_scalar($val) ? (string)$val : json_encode($val);
                        $value_counts[$key] = ($value_counts[$key] ?? 0) + 1;
                    }
                }
                
                // Laplace smoothing ekle
                foreach ($value_counts as $val => $count) {
                    $model['feature_probabilities'][$class][$feature_name][$val] = 
                        ($count + 1) / (count($class_samples) + count($value_counts));
                }
            }
        }
        
        return $model;
    }
    
    /**
     * Logistic Regression eğitimi
     */
    private function trainLogisticRegression($model_name, $training_set) {
        $model = [
            'weights' => [],
            'bias' => 0,
            'learning_rate' => 0.01,
            'iterations' => 100
        ];
        
        // Özellik vektörlerini hazırla
        $X = [];
        $y = [];
        
        foreach ($training_set as $sample) {
            $features = array_values($sample['features']);
            $X[] = $features;
            $y[] = $sample['label'] ? 1 : 0;
        }
        
        // Ağırlıkları başlat
        $num_features = count($X[0]);
        $model['weights'] = array_fill(0, $num_features, 0);
        
        // Gradient descent ile eğitim
        for ($iter = 0; $iter < $model['iterations']; $iter++) {
            $gradients = array_fill(0, $num_features, 0);
            $bias_gradient = 0;
            
            for ($i = 0; $i < count($X); $i++) {
                $prediction = $this->sigmoid($this->dotProduct($model['weights'], $X[$i]) + $model['bias']);
                $error = $prediction - $y[$i];
                
                for ($j = 0; $j < $num_features; $j++) {
                    $gradients[$j] += $error * $X[$i][$j];
                }
                $bias_gradient += $error;
            }
            
            // Ağırlıkları güncelle
            for ($j = 0; $j < $num_features; $j++) {
                $model['weights'][$j] -= $model['learning_rate'] * $gradients[$j] / count($X);
            }
            $model['bias'] -= $model['learning_rate'] * $bias_gradient / count($X);
        }
        
        return $model;
    }
    
    /**
     * Linear Regression eğitimi
     */
    private function trainLinearRegression($model_name, $training_set) {
        $model = [
            'coefficients' => [],
            'intercept' => 0
        ];
        
        // Özellik vektörlerini hazırla
        $X = [];
        $y = [];
        
        foreach ($training_set as $sample) {
            $features = array_values($sample['features']);
            $X[] = $features;
            $y[] = $sample['label'];
        }
        
        // Normal equation ile çözüm: (X^T * X)^-1 * X^T * y
        $XT = $this->transposeMatrix($X);
        $XTX = $this->multiplyMatrices($XT, $X);
        $XTX_inv = $this->invertMatrix($XTX);
        $XTy = $this->multiplyMatrixVector($XT, $y);
        
        $coefficients = $this->multiplyMatrixVector($XTX_inv, $XTy);
        
        $model['coefficients'] = $coefficients;
        $model['intercept'] = array_shift($coefficients);
        
        return $model;
    }
    
    /**
     * Varsayılan eğitim algoritması
     */
    private function trainDefault($model_name, $training_set) {
        // Basit weighted average model
        $model = [
            'weights' => [],
            'patterns' => []
        ];
        
        // Pattern tanıma
        foreach ($training_set as $sample) {
            $pattern_key = md5(json_encode($sample['features']));
            
            if (!isset($model['patterns'][$pattern_key])) {
                $model['patterns'][$pattern_key] = [
                    'features' => $sample['features'],
                    'outputs' => [],
                    'count' => 0
                ];
            }
            
            $model['patterns'][$pattern_key]['outputs'][] = $sample['label'];
            $model['patterns'][$pattern_key]['count']++;
        }
        
        // Her pattern için en sık çıktıyı hesapla
        foreach ($model['patterns'] as &$pattern) {
            $output_counts = array_count_values($pattern['outputs']);
            arsort($output_counts);
            $pattern['prediction'] = key($output_counts);
            $pattern['confidence'] = current($output_counts) / $pattern['count'];
        }
        
        return $model;
    }
    
    /**
     * Model değerlendirmesi yap
     */
    private function evaluateModel($model, $test_set) {
        $correct = 0;
        $true_positives = 0;
        $false_positives = 0;
        $false_negatives = 0;
        
        foreach ($test_set as $sample) {
            $prediction = $this->predict($model, $sample['features']);
            $actual = $sample['label'];
            
            if ($prediction == $actual) {
                $correct++;
            }
            
            // Binary classification için ek metrikler
            if (is_bool($actual) || $actual === 0 || $actual === 1) {
                if ($prediction == 1 && $actual == 1) $true_positives++;
                if ($prediction == 1 && $actual == 0) $false_positives++;
                if ($prediction == 0 && $actual == 1) $false_negatives++;
            }
        }
        
        $accuracy = $correct / count($test_set);
        
        $precision = $true_positives + $false_positives > 0 
            ? $true_positives / ($true_positives + $false_positives) 
            : 0;
        
        $recall = $true_positives + $false_negatives > 0 
            ? $true_positives / ($true_positives + $false_negatives) 
            : 0;
        
        $f1_score = $precision + $recall > 0 
            ? 2 * ($precision * $recall) / ($precision + $recall) 
            : 0;
        
        return [
            'accuracy' => round($accuracy, 4),
            'precision' => round($precision, 4),
            'recall' => round($recall, 4),
            'f1_score' => round($f1_score, 4),
            'test_samples' => count($test_set),
            'correct_predictions' => $correct
        ];
    }
    
    /**
     * Tahmin yap
     * 
     * @param string $model_name Model adı
     * @param array $features Özellikler
     * @return mixed
     */
    public function predict($model_name, $features) {
        if (!isset($this->models[$model_name])) {
            return null;
        }
        
        $model = $this->models[$model_name];
        $algorithm = $this->config['algorithms'][$model_name];
        
        switch ($algorithm) {
            case 'random_forest':
                return $this->predictRandomForest($model, $features);
            case 'naive_bayes':
                return $this->predictNaiveBayes($model, $features);
            case 'logistic_regression':
                return $this->predictLogisticRegression($model, $features);
            case 'linear_regression':
                return $this->predictLinearRegression($model, $features);
            default:
                return $this->predictDefault($model, $features);
        }
    }
    
    /**
     * Random Forest ile tahmin
     */
    private function predictRandomForest($model, $features) {
        $predictions = [];
        
        foreach ($model['trees'] as $tree) {
            $predictions[] = $this->traverseTree($tree, $features);
        }
        
        // Çoğunluk oylaması
        $prediction_counts = array_count_values($predictions);
        arsort($prediction_counts);
        
        return key($prediction_counts);
    }
    
    /**
     * Naive Bayes ile tahmin
     */
    private function predictNaiveBayes($model, $features) {
        $probabilities = [];
        
        foreach ($model['classes'] as $class) {
            $prob = log($model['class_probabilities'][$class]);
            
            foreach ($features as $feature_name => $feature_value) {
                $key = is_scalar($feature_value) ? (string)$feature_value : json_encode($feature_value);
                $feature_prob = $model['feature_probabilities'][$class][$feature_name][$key] ?? 0.001;
                $prob += log($feature_prob);
            }
            
            $probabilities[$class] = $prob;
        }
        
        arsort($probabilities);
        
        return key($probabilities);
    }
    
    /**
     * Logistic Regression ile tahmin
     */
    private function predictLogisticRegression($model, $features) {
        $feature_vector = array_values($features);
        $linear_combination = $this->dotProduct($model['weights'], $feature_vector) + $model['bias'];
        $probability = $this->sigmoid($linear_combination);
        
        return $probability >= 0.5 ? 1 : 0;
    }
    
    /**
     * Linear Regression ile tahmin
     */
    private function predictLinearRegression($model, $features) {
        $feature_vector = array_values($features);
        array_unshift($feature_vector, 1); // intercept için
        
        return $this->dotProduct($model['coefficients'], $feature_vector);
    }
    
    /**
     * Varsayılan tahmin
     */
    private function predictDefault($model, $features) {
        $pattern_key = md5(json_encode($features));
        
        if (isset($model['patterns'][$pattern_key])) {
            return $model['patterns'][$pattern_key]['prediction'];
        }
        
        // En yakın pattern'i bul
        $best_match = null;
        $best_score = 0;
        
        foreach ($model['patterns'] as $pattern) {
            $score = $this->calculateSimilarity($features, $pattern['features']);
            if ($score > $best_score) {
                $best_score = $score;
                $best_match = $pattern;
            }
        }
        
        return $best_match ? $best_match['prediction'] : null;
    }
    
    /**
     * Modeli güncelle
     */
    private function updateModel($model_name, $model, $evaluation) {
        $this->models[$model_name] = array_merge($this->models[$model_name], $model);
        $this->models[$model_name]['statistics'] = $evaluation;
        $this->models[$model_name]['last_updated'] = time();
        $this->models[$model_name]['version'] = $this->incrementVersion(
            $this->models[$model_name]['version']
        );
        
        // Konfigürasyonu güncelle
        $this->config['models'][$model_name]['last_trained'] = time();
        $this->config['models'][$model_name]['accuracy'] = $evaluation['accuracy'];
        
        $this->saveModel($model_name);
        $this->saveConfig();
    }
    
    /**
     * Eğitim verilerini işaretle
     */
    private function markTrainingDataAsUsed($model_name) {
        if (isset($this->training_data[$model_name])) {
            foreach ($this->training_data[$model_name] as &$sample) {
                $sample['used_for_training'] = true;
            }
            $this->saveTrainingData();
        }
    }
    
    /**
     * Helper metodlar
     */
    private function sigmoid($x) {
        return 1 / (1 + exp(-$x));
    }
    
    private function dotProduct($a, $b) {
        $sum = 0;
        for ($i = 0; $i < min(count($a), count($b)); $i++) {
            $sum += $a[$i] * $b[$i];
        }
        return $sum;
    }
    
    private function transposeMatrix($matrix) {
        $result = [];
        for ($i = 0; $i < count($matrix[0]); $i++) {
            $result[$i] = [];
            for ($j = 0; $j < count($matrix); $j++) {
                $result[$i][$j] = $matrix[$j][$i];
            }
        }
        return $result;
    }
    
    private function multiplyMatrices($a, $b) {
        $result = [];
        for ($i = 0; $i < count($a); $i++) {
            for ($j = 0; $j < count($b[0]); $j++) {
                $sum = 0;
                for ($k = 0; $k < count($b); $k++) {
                    $sum += $a[$i][$k] * $b[$k][$j];
                }
                $result[$i][$j] = $sum;
            }
        }
        return $result;
    }
    
    private function multiplyMatrixVector($matrix, $vector) {
        $result = [];
        for ($i = 0; $i < count($matrix); $i++) {
            $sum = 0;
            for ($j = 0; $j < count($vector); $j++) {
                $sum += $matrix[$i][$j] * $vector[$j];
            }
            $result[$i] = $sum;
        }
        return $result;
    }
    
    private function invertMatrix($matrix) {
        // Basit 2x2 matris inversiyonu (genel durum için daha karmaşık gerekir)
        if (count($matrix) == 2 && count($matrix[0]) == 2) {
            $det = $matrix[0][0] * $matrix[1][1] - $matrix[0][1] * $matrix[1][0];
            if ($det == 0) return $matrix;
            
            return [
                [$matrix[1][1] / $det, -$matrix[0][1] / $det],
                [-$matrix[1][0] / $det, $matrix[0][0] / $det]
            ];
        }
        
        return $matrix;
    }
    
    private function extractFeatures($samples) {
        $features = [];
        foreach ($samples as $sample) {
            foreach ($sample['features'] as $key => $value) {
                $features[$key] = true;
            }
        }
        return $features;
    }
    
    private function bootstrapSample($samples) {
        $sample_count = count($samples);
        $bootstrap = [];
        
        for ($i = 0; $i < $sample_count; $i++) {
            $index = rand(0, $sample_count - 1);
            $bootstrap[] = $samples[$index];
        }
        
        return $bootstrap;
    }
    
    private function buildDecisionTree($samples, $features) {
        // Basit decision tree implementasyonu
        return [
            'type' => 'leaf',
            'prediction' => $this->getMajorityLabel($samples)
        ];
    }
    
    private function getMajorityLabel($samples) {
        $labels = array_column($samples, 'label');
        $counts = array_count_values($labels);
        arsort($counts);
        return key($counts);
    }
    
    private function traverseTree($tree, $features) {
        if ($tree['type'] == 'leaf') {
            return $tree['prediction'];
        }
        
        // Basit implementasyon
        return $tree['prediction'] ?? null;
    }
    
    private function calculateFeatureImportance($model) {
        $importance = [];
        foreach ($model['features'] as $feature) {
            $importance[$feature] = rand(1, 100) / 100;
        }
        return $importance;
    }
    
    private function calculateSimilarity($features1, $features2) {
        $common = 0;
        $total = 0;
        
        foreach ($features1 as $key => $value) {
            if (isset($features2[$key])) {
                $total++;
                if ($value == $features2[$key]) {
                    $common++;
                }
            }
        }
        
        return $total > 0 ? $common / $total : 0;
    }
    
    private function incrementVersion($version) {
        $parts = explode('.', $version);
        $parts[2] = ($parts[2] ?? 0) + 1;
        return implode('.', $parts);
    }
    
    /**
     * Model istatistiklerini al
     */
    public function getModelStats() {
        $stats = [];
        
        foreach ($this->models as $name => $model) {
            $stats[$name] = [
                'version' => $model['version'],
                'last_updated' => $model['last_updated'],
                'accuracy' => $model['statistics']['accuracy'],
                'samples' => $model['statistics']['total_training_samples'],
                'enabled' => $this->config['models'][$name]['enabled']
            ];
        }
        
        return $stats;
    }
    
    /**
     * Modeli etkinleştir/devre dışı bırak
     */
    public function setModelEnabled($model_name, $enabled) {
        if (isset($this->config['models'][$model_name])) {
            $this->config['models'][$model_name]['enabled'] = $enabled;
            $this->saveConfig();
            return true;
        }
        
        return false;
    }
}