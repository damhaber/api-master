<?php
/**
 * API Master - Webhook Kullanım Örneği
 * @package APIMaster
 * @subpackage Examples
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../CORE/autoloader.php';

class WebhookExample {
    private $apiMaster;
    private $webhookUrl = 'https://your-server.com/webhook-endpoint';
    private $webhookSecret = 'your-webhook-secret-key';
    
    public function __construct() {
        $this->apiMaster = new APIMaster_Core();
    }
    
    // Webhook endpoint'i oluştur (basit HTTP sunucusu)
    public function createWebhookServer() {
        echo "========================================\n";
        echo "Webhook Sunucusu Başlatılıyor\n";
        echo "========================================\n\n";
        
        // Örnek webhook endpoint'i (PHP built-in server için)
        $webhookHandler = __DIR__ . '/webhook_handler.php';
        
        $handlerCode = '<?php
// webhook_handler.php - Webhook endpoint handler
$input = json_decode(file_get_contents("php://input"), true);
$headers = getallheaders();

// Webhook secret doğrulama
$signature = $headers["X-Webhook-Signature"] ?? "";
$payload = file_get_contents("php://input");
$expectedSignature = hash_hmac("sha256", $payload, getenv("WEBHOOK_SECRET"));

if ($signature !== $expectedSignature) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid signature"]);
    exit;
}

// Webhook işleme
$event = $input["event"] ?? "";
$data = $input["data"] ?? [];

// Logla
$logEntry = [
    "timestamp" => date("Y-m-d H:i:s"),
    "event" => $event,
    "data" => $data,
    "ip" => $_SERVER["REMOTE_ADDR"]
];
file_put_contents("webhook_logs.json", json_encode($logEntry) . PHP_EOL, FILE_APPEND);

// Event tipine göre işlem yap
switch ($event) {
    case "api.request.completed":
        handleApiRequestCompleted($data);
        break;
    case "provider.status.changed":
        handleProviderStatusChanged($data);
        break;
    case "learning.model.updated":
        handleModelUpdated($data);
        break;
    case "vector.index.built":
        handleIndexBuilt($data);
        break;
    default:
        echo json_encode(["message" => "Event received but not processed"]);
}

function handleApiRequestCompleted($data) {
    echo json_encode([
        "status" => "processed",
        "event" => "api.request.completed",
        "request_id" => $data["request_id"] ?? null
    ]);
}

function handleProviderStatusChanged($data) {
    echo json_encode([
        "status" => "processed",
        "event" => "provider.status.changed",
        "provider" => $data["provider"] ?? null,
        "new_status" => $data["status"] ?? null
    ]);
}

function handleModelUpdated($data) {
    echo json_encode([
        "status" => "processed",
        "event" => "learning.model.updated",
        "accuracy" => $data["accuracy"] ?? null
    ]);
}

function handleIndexBuilt($data) {
    echo json_encode([
        "status" => "processed",
        "event" => "vector.index.built",
        "vector_count" => $data["vector_count"] ?? null
    ]);
}
';
        
        file_put_contents($webhookHandler, $handlerCode);
        echo "✅ Webhook handler oluşturuldu: " . $webhookHandler . "\n";
        echo "📍 Webhook URL: http://localhost:8080/webhook_handler.php\n\n";
    }
    
    // Webhook kaydetme
    public function registerWebhook() {
        echo "========================================\n";
        echo "Webhook Kaydı\n";
        echo "========================================\n\n";
        
        $webhooks = [
            [
                'url' => $this->webhookUrl . '/api-requests',
                'events' => ['api.request.completed', 'api.request.failed'],
                'secret' => $this->webhookSecret,
                'active' => true
            ],
            [
                'url' => $this->webhookUrl . '/provider-status',
                'events' => ['provider.status.changed', 'provider.added', 'provider.removed'],
                'secret' => $this->webhookSecret,
                'active' => true
            ],
            [
                'url' => $this->webhookUrl . '/learning',
                'events' => ['learning.model.updated', 'learning.pattern.discovered'],
                'secret' => $this->webhookSecret,
                'active' => true
            ],
            [
                'url' => $this->webhookUrl . '/vector',
                'events' => ['vector.index.built', 'vector.search.completed'],
                'secret' => $this->webhookSecret,
                'active' => true
            ],
            [
                'url' => $this->webhookUrl . '/system',
                'events' => ['system.health.check', 'system.error', 'system.warning'],
                'secret' => $this->webhookSecret,
                'active' => true
            ]
        ];
        
        foreach ($webhooks as $webhook) {
            $result = $this->apiMaster->registerWebhook($webhook);
            if ($result['success']) {
                echo "✅ Webhook kaydedildi: " . $webhook['url'] . "\n";
                echo "   Events: " . implode(', ', $webhook['events']) . "\n\n";
            } else {
                echo "❌ Webhook kaydedilemedi: " . $result['error'] . "\n\n";
            }
        }
    }
    
    // Webhook tetikleme örneği
    public function triggerWebhooks() {
        echo "========================================\n";
        echo "Webhook Tetikleme Örnekleri\n";
        echo "========================================\n\n";
        
        // 1. API isteği tamamlandı event'i
        echo "1. API İsteği Tamamlandı Event'i:\n";
        $this->triggerApiRequestEvent();
        
        // 2. Provider durumu değişti event'i
        echo "\n2. Provider Durumu Değişti Event'i:\n";
        $this->triggerProviderStatusEvent();
        
        // 3. Learning model güncellendi event'i
        echo "\n3. Learning Model Güncellendi Event'i:\n";
        $this->triggerLearningEvent();
        
        // 4. Vektör indeks oluşturuldu event'i
        echo "\n4. Vektör İndeks Oluşturuldu Event'i:\n";
        $this->triggerVectorEvent();
        
        // 5. Sistem hatası event'i
        echo "\n5. Sistem Hatası Event'i:\n";
        $this->triggerSystemErrorEvent();
    }
    
    private function triggerApiRequestEvent() {
        $eventData = [
            'event' => 'api.request.completed',
            'data' => [
                'request_id' => uniqid('req_'),
                'provider' => 'openai',
                'endpoint' => '/v1/chat/completions',
                'response_time_ms' => 245,
                'status_code' => 200,
                'tokens_used' => 150,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
        $result = $this->apiMaster->triggerWebhook('api.request.completed', $eventData);
        echo "   Webhook tetiklendi: " . ($result['success'] ? '✅' : '❌') . "\n";
        if ($result['success']) {
            echo "   " . $result['delivered_to'] . " endpoint'e gönderildi\n";
        }
    }
    
    private function triggerProviderStatusEvent() {
        $eventData = [
            'event' => 'provider.status.changed',
            'data' => [
                'provider' => 'anthropic',
                'old_status' => 'active',
                'new_status' => 'maintenance',
                'reason' => 'Scheduled maintenance',
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
        $result = $this->apiMaster->triggerWebhook('provider.status.changed', $eventData);
        echo "   Webhook tetiklendi: " . ($result['success'] ? '✅' : '❌') . "\n";
    }
    
    private function triggerLearningEvent() {
        $eventData = [
            'event' => 'learning.model.updated',
            'data' => [
                'model_name' => 'routing_model_v3',
                'old_accuracy' => 92.5,
                'new_accuracy' => 94.8,
                'samples_trained' => 15000,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
        $result = $this->apiMaster->triggerWebhook('learning.model.updated', $eventData);
        echo "   Webhook tetiklendi: " . ($result['success'] ? '✅' : '❌') . "\n";
    }
    
    private function triggerVectorEvent() {
        $eventData = [
            'event' => 'vector.index.built',
            'data' => [
                'index_name' => 'hnsw_main',
                'vector_count' => 125000,
                'dimensions' => 1536,
                'build_time_sec' => 45,
                'index_size_mb' => 256,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
        $result = $this->apiMaster->triggerWebhook('vector.index.built', $eventData);
        echo "   Webhook tetiklendi: " . ($result['success'] ? '✅' : '❌') . "\n";
    }
    
    private function triggerSystemErrorEvent() {
        $eventData = [
            'event' => 'system.error',
            'data' => [
                'error_code' => 'ERR_RATE_LIMIT',
                'error_message' => 'Rate limit exceeded for provider openai',
                'severity' => 'high',
                'provider' => 'openai',
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
        $result = $this->apiMaster->triggerWebhook('system.error', $eventData);
        echo "   Webhook tetiklendi: " . ($result['success'] ? '✅' : '❌') . "\n";
    }
    
    // Webhook loglarını görüntüleme
    public function showWebhookLogs() {
        echo "========================================\n";
        echo "Webhook Logları\n";
        echo "========================================\n\n";
        
        $logs = $this->apiMaster->getWebhookLogs(['limit' => 10]);
        
        if (empty($logs)) {
            echo "Henüz webhook logu yok.\n";
            return;
        }
        
        echo str_repeat('-', 80) . "\n";
        printf("%-20s | %-25s | %-10s | %-15s\n", "Tarih", "Event", "Durum", "Endpoint");
        echo str_repeat('-', 80) . "\n";
        
        foreach ($logs as $log) {
            $status = $log['success'] ? '✅ Başarılı' : '❌ Başarısız';
            printf("%-20s | %-25s | %-10s | %-15s\n",
                substr($log['timestamp'], 0, 19),
                substr($log['event'], 0, 25),
                $status,
                substr($log['endpoint'], 0, 15) . '...'
            );
        }
        echo str_repeat('-', 80) . "\n";
    }
    
    // Webhook silme
    public function deleteWebhook($webhookId) {
        echo "========================================\n";
        echo "Webhook Silme\n";
        echo "========================================\n\n";
        
        $result = $this->apiMaster->deleteWebhook($webhookId);
        
        if ($result['success']) {
            echo "✅ Webhook silindi: " . $webhookId . "\n";
        } else {
            echo "❌ Webhook silinemedi: " . $result['error'] . "\n";
        }
    }
    
    // Webhook istatistikleri
    public function showWebhookStats() {
        echo "========================================\n";
        echo "Webhook İstatistikleri\n";
        echo "========================================\n\n";
        
        $stats = $this->apiMaster->getWebhookStats();
        
        echo "Genel İstatistikler:\n";
        echo "   Toplam webhook: " . ($stats['total_webhooks'] ?? 0) . "\n";
        echo "   Aktif webhook: " . ($stats['active_webhooks'] ?? 0) . "\n";
        echo "   Toplam tetikleme: " . number_format($stats['total_triggers'] ?? 0) . "\n";
        echo "   Başarılı tetikleme: " . number_format($stats['successful_triggers'] ?? 0) . "\n";
        echo "   Başarısız tetikleme: " . number_format($stats['failed_triggers'] ?? 0) . "\n";
        echo "   Başarı oranı: " . ($stats['success_rate'] ?? 0) . "%\n";
        
        echo "\nEvent Bazlı İstatistikler:\n";
        foreach ($stats['events'] ?? [] as $event => $count) {
            echo "   - " . $event . ": " . number_format($count) . "\n";
        }
        
        echo "\nOrtalama Yanıt Süreleri:\n";
        foreach ($stats['response_times'] ?? [] as $endpoint => $time) {
            echo "   - " . $endpoint . ": " . $time . "ms\n";
        }
    }
}

// Webhook örneğini çalıştır
$example = new WebhookExample();

// Webhook sunucusu oluştur
$example->createWebhookServer();

// Webhook kaydet
$example->registerWebhook();

// Webhook tetikle
$example->triggerWebhooks();

// Webhook loglarını göster
$example->showWebhookLogs();

// Webhook istatistikleri
$example->showWebhookStats();

// Webhook silme örneği (isteğe bağlı)
// $example->deleteWebhook('webhook_id_here');

echo "\n========================================\n";
echo "Webhook örneği tamamlandı!\n";
echo "========================================\n";
echo "\n💡 İpucu: Webhook sunucusunu başlatmak için:\n";
echo "   export WEBHOOK_SECRET='your-webhook-secret-key'\n";
echo "   php -S localhost:8080 " . __DIR__ . "/webhook_handler.php\n";