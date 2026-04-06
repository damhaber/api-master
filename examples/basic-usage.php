<?php
/**
 * API Master - Basit Kullanım Örneği
 * @package APIMaster
 * @subpackage Examples
 */

// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Autoloader'ı dahil et
require_once __DIR__ . '/../CORE/autoloader.php';

// API Master'ı başlat
$apiMaster = new APIMaster_Core();

// Config'den API key'i al
$config = json_decode(file_get_contents(__DIR__ . '/../config/config.json'), true);
$apiKey = $config['security']['api_key'];

echo "========================================\n";
echo "API Master - Basit Kullanım Örneği\n";
echo "========================================\n\n";

// 1. Provider listesini al
echo "1. Provider Listesi:\n";
$providers = $apiMaster->getProviders();
foreach ($providers as $provider) {
    echo "   - " . $provider['name'] . " (" . $provider['status'] . ")\n";
}
echo "\n";

// 2. Basit API çağrısı (OpenAI)
echo "2. OpenAI API Çağrısı:\n";
try {
    $response = $apiMaster->request('openai', '/v1/chat/completions', [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'user', 'content' => 'Merhaba! API Master nedir?']
        ],
        'max_tokens' => 100
    ]);
    
    if (isset($response['choices'][0]['message']['content'])) {
        echo "   Yanıt: " . $response['choices'][0]['message']['content'] . "\n";
        echo "   Token kullanımı: " . $response['usage']['total_tokens'] . "\n";
    }
} catch (Exception $e) {
    echo "   Hata: " . $e->getMessage() . "\n";
}
echo "\n";

// 3. Embedding oluşturma
echo "3. Embedding Oluşturma:\n";
try {
    $text = "API Master çoklu API entegrasyon modülüdür";
    $embedding = $apiMaster->createEmbedding($text, 'openai');
    
    echo "   Metin: " . $text . "\n";
    echo "   Vektör boyutu: " . count($embedding) . "\n";
    echo "   İlk 5 değer: " . implode(', ', array_slice($embedding, 0, 5)) . "...\n";
} catch (Exception $e) {
    echo "   Hata: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Sistem durumu kontrolü
echo "4. Sistem Durumu:\n";
$health = $apiMaster->checkHealth();
echo "   Status: " . $health['status'] . "\n";
echo "   Uptime: " . ($health['uptime'] ?? 'N/A') . "\n";
foreach ($health['components'] as $component => $status) {
    echo "   - " . $component . ": " . $status . "\n";
}
echo "\n";

// 5. İstatistikler
echo "5. Sistem İstatistikleri:\n";
$stats = $apiMaster->getStats();
echo "   Toplam istek: " . number_format($stats['total_requests'] ?? 0) . "\n";
echo "   Başarı oranı: " . ($stats['success_rate'] ?? 99.9) . "%\n";
echo "   Ortalama yanıt süresi: " . ($stats['avg_response_time'] ?? 0) . "ms\n";
echo "   Öğrenilen pattern: " . number_format($stats['patterns_learned'] ?? 0) . "\n";

echo "\n========================================\n";
echo "Örnek tamamlandı!\n";
echo "========================================\n";