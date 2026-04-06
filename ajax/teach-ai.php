<?php
/**
 * Teach AI - AI öğretme endpoint'i
 * AJAX endpoint for teaching AI new patterns
 */

// Güvenlik kontrolü
if (!defined('ABSPATH')) {
    exit; // Doğrudan erişim engellendi
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'message' => 'Güvenlik duvarı!']));
}

// Hata raporlamayı sessize al
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

try {
    // Input kontrolü
    $question = isset($_POST['question']) ? trim($_POST['question']) : '';
    $answer = isset($_POST['answer']) ? trim($_POST['answer']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : 'general';
    
    if (empty($question) || empty($answer)) {
        echo json_encode(['success' => false, 'message' => 'Soru ve cevap alanları boş olamaz']);
        exit;
    }
    
    // Modül dizini
    $module_dir = defined('API_MASTER_MODULE_DIR') ? API_MASTER_MODULE_DIR : dirname(__DIR__);
    
    // Öğrenme verileri dosyası
    $learning_file = $module_dir . '/data/learning-data.json';
    $vector_file = $module_dir . '/data/vector-data.json';
    
    // Veri klasörünü kontrol et
    $data_dir = $module_dir . '/data';
    if (!is_dir($data_dir)) {
        mkdir($data_dir, 0755, true);
    }
    
    // Öğrenme verilerini oku
    $learning_data = [];
    if (file_exists($learning_file)) {
        $content = file_get_contents($learning_file);
        $learning_data = json_decode($content, true);
        if (!is_array($learning_data)) {
            $learning_data = [];
        }
    }
    
    // Benzersiz anahtar oluştur
    $key = create_slug($question);
    
    $now = time();
    
    if (isset($learning_data[$key])) {
        // Var olanı güncelle
        $learning_data[$key]['count']++;
        $learning_data[$key]['last_used'] = $now;
        $learning_data[$key]['answer'] = $answer;
        $learning_data[$key]['category'] = $category;
        $message = 'Mevcut bilgi güncellendi';
    } else {
        // Yeni ekle
        $learning_data[$key] = [
            'question' => $question,
            'answer' => $answer,
            'category' => $category,
            'count' => 1,
            'created' => $now,
            'last_used' => $now,
            'success_rate' => 100
        ];
        $message = 'Yeni bilgi öğretildi';
    }
    
    // Kaydet
    file_put_contents($learning_file, json_encode($learning_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Log kaydı
    $log_file = $module_dir . '/logs/api-master.log';
    $log_dir = $module_dir . '/logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $log_entry = '[' . date('Y-m-d H:i:s') . "] [INFO] Yeni bilgi öğretildi: {$question} (Kategori: {$category})\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    // Vektör hafızaya da ekle
    $vector_data = [];
    if (file_exists($vector_file)) {
        $content = file_get_contents($vector_file);
        $vector_data = json_decode($content, true);
        if (!is_array($vector_data)) {
            $vector_data = [];
        }
    }
    
    $vector_data[$key] = [
        'content' => $question . "\n" . $answer,
        'category' => $category,
        'created' => $now,
        'type' => 'learned'
    ];
    
    file_put_contents($vector_file, json_encode($vector_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'key' => $key,
            'question' => $question,
            'category' => $category,
            'total_learnings' => count($learning_data)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Bir hata oluştu: ' . $e->getMessage()
    ]);
}

/**
 * Slug oluştur - WordPress sanitize_title yerine
 * 
 * @param string $string
 * @return string
 */
function create_slug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9\s-ğüşıöçĞÜŞİÖÇ]/', '', $string);
    $string = preg_replace('/[\s-]+/', '_', $string);
    return trim($string, '_');
}
?>