markdown
# API Master - Geliştirici Dokümantasyonu

## 🏗️ Mimari Yapı

### Genel Bakış
API Master, WordPress'ten tamamen bağımsız, modüler bir API yönetim sistemidir. Tüm işlemler cURL üzerinden gerçekleştirilir, database kullanılmaz.

### Klasör Yapısı
api-master/
├── AJAX/ # AJAX işlemleri (17 dosya)
├── API/ # API provider'ları (65+ dosya)
├── CORE/ # Çekirdek dosyalar (9 dosya)
├── CORN/ # Cron job'lar (2 dosya)
├── includes/ # Yardımcı dosyalar (3 dosya)
├── security/ # Güvenlik katmanı (5 dosya)
├── middleware/ # Middleware'ler (2 dosya)
├── queue/ # Kuyruk sistemi (2 dosya)
├── logs/ # Log dosyaları
├── data/ # Veri depolama (JSON)
├── cache/ # Cache sistemi
├── learning/ # Yapay öğrenme (7 dosya)
├── vector/ # Vektör indeksleme (6 dosya)
├── migrations/ # Migration'lar (3 dosya)
├── config/ # Konfigürasyon (5 dosya)
├── ui/ # UI bileşenleri (8 dosya)
├── assets/ # Statik dosyalar (CSS, JS, images)
├── docs/ # Dokümantasyon
├── examples/ # Örnek kodlar
└── tests/ # Test dosyaları

text

---

## 📝 Kod Standartları

### Class İsimlendirme
```php
// Tüm class'lar APIMaster_ prefix'i ile başlar
class APIMaster_ProviderManager {}
class APIMaster_VectorIndex {}
class APIMaster_LearningEngine {}
Metod İsimlendirme
php
// camelCase kullanılır
public function getProviderList() {}
public function calculateSimilarity() {}
public function saveToMemory() {}
Dosya İsimlendirme
php
// Snake case kullanılır
provider_manager.php
vector_index.php
learning_engine.php
Constants
php
// Büyük harf ve underscore
define('APIM_VERSION', '1.0.0');
define('APIM_MAX_RETRIES', 3);
define('APIM_CACHE_TTL', 3600);
🔧 Geliştirme Kuralları
1. WordPress Yasakları
php
// ❌ YASAK - WordPress fonksiyonları
add_action();
add_filter();
get_option();
wp_remote_get();
$wpdb;

// ✅ İZİN VERİLEN - Sadece cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_exec($ch);
2. Database Yasakları
php
// ❌ YASAK - Database sorguları
mysqli_query();
PDO::query();
$wpdb->get_results();

// ✅ İZİN VERİLEN - JSON dosyaları
$data = json_decode(file_get_contents('data/file.json'), true);
file_put_contents('data/file.json', json_encode($data));
3. Config Kullanımı
php
// ✅ Config JSON tabanlı
$config = json_decode(file_get_contents('config/config.json'), true);
$api_key = $config['api_keys']['openai'];
🚀 Yeni Provider Ekleme
Adım 1: Provider Class'ı Oluştur
php
<?php
// API/ProviderName.php
class APIMaster_ProviderName extends APIMaster_BaseProvider {
    protected $provider_name = 'provider_name';
    protected $base_url = 'https://api.provider.com/v1';
    
    public function __construct($config) {
        parent::__construct($config);
        $this->endpoints = [
            'chat' => '/chat/completions',
            'embed' => '/embeddings'
        ];
    }
    
    protected function getHeaders() {
        return [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        ];
    }
}
Adım 2: Config'e Ekle
json
{
    "providers": {
        "provider_name": {
            "enabled": true,
            "api_key": "your-api-key",
            "timeout": 30,
            "retries": 3
        }
    }
}
Adım 3: Test Et
bash
curl -X POST /api/v1/providers/provider_name/test
🧠 Learning Sistemi
Memory Consolidation
php
// Pattern tanıma ve hafıza konsolidasyonu
class APIMaster_MemoryConsolidation {
    public function consolidate($patterns) {
        // Sık kullanılan pattern'leri birleştir
        // Önemli bilgileri kalıcı hafızaya taşı
        // Eski pattern'leri temizle
    }
}
Active Learning
php
// Aktif öğrenme sistemi
class APIMaster_ActiveLearning {
    public function learn($input, $output) {
        // Yeni örnekten öğren
        // Pattern çıkar
        // Model güncelle
    }
}
📊 Vektör İndeksleme (HNSW)
HNSW Yapılandırması
php
$config = [
    'dimensions' => 1536,      // Vektör boyutu
    'max_elements' => 1000000, // Maksimum eleman
    'M' => 16,                 // Bağlantı sayısı
    'ef_construction' => 200,  // İnşa kalitesi
    'ef_search' => 50          // Arama kalitesi
];
Vektör Ekleme
php
$vector_index->addItem($embedding, $metadata, $id);
Benzerlik Arama
php
$results = $vector_index->search($query_vector, $k = 10);
🔄 Queue Sistemi
Job Ekleme
php
$queue->push('process_api_request', [
    'provider' => 'openai',
    'endpoint' => '/chat',
    'data' => $data
]);
Job İşleme
php
$queue->process(function($job) {
    $result = $this->processRequest($job->data);
    return $result;
});
🗄️ Cache Mekanizması
Cache Driver'ları
php
// File Cache
$cache = new APIMaster_Cache_File('/path/to/cache');

// Redis Cache
$cache = new APIMaster_Cache_Redis('127.0.0.1', 6379);

// Memcached
$cache = new APIMaster_Cache_Memcached('127.0.0.1', 11211);
Kullanım
php
// Set cache
$cache->set('key', $data, 3600);

// Get cache
$data = $cache->get('key');

// Delete cache
$cache->delete('key');
🛡️ Güvenlik Katmanı
Input Validation
php
$validator = new APIMaster_Security_Validator();
$clean_input = $validator->sanitize($_POST['input']);
CSRF Koruması
php
$token = APIMaster_Security_CSRF::generateToken();
// Token'ı form'a ekle
// Token'ı doğrula
XSS Koruması
php
$safe_output = APIMaster_Security_XSS::clean($user_input);
📝 Loglama Sistemi
Log Seviyeleri
php
APIMaster_Logger::debug('Detaylı bilgi');
APIMaster_Logger::info('Bilgi mesajı');
APIMaster_Logger::warning('Uyarı');
APIMaster_Logger::error('Hata');
APIMaster_Logger::critical('Kritik hata');
Log Formatı
json
{
    "timestamp": "2024-01-15T10:30:00Z",
    "level": "INFO",
    "message": "API request completed",
    "context": {
        "provider": "openai",
        "response_time": 245
    }
}
🧪 Test Yazma
Unit Test
php
class ProviderManagerTest extends PHPUnit_Framework_TestCase {
    public function testGetProvider() {
        $manager = new APIMaster_ProviderManager();
        $provider = $manager->getProvider('openai');
        $this->assertInstanceOf('APIMaster_Provider_OpenAI', $provider);
    }
}
Integration Test
php
class APIEndpointTest extends PHPUnit_Framework_TestCase {
    public function testProvidersEndpoint() {
        $response = $this->get('/api/v1/providers');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $response->getData());
    }
}
🚢 Deployment
Gereksinimler
bash
PHP 7.4+
cURL extension
JSON extension
OpenSSL extension
MBString extension
Kurulum
bash
# 1. Dosyaları kopyala
cp -r api-master /var/www/html/

# 2. İzinleri ayarla
chmod 755 /var/www/html/api-master
chmod 777 /var/www/html/api-master/logs
chmod 777 /var/www/html/api-master/cache
chmod 777 /var/www/html/api-master/data

# 3. Config'i düzenle
vi /var/www/html/api-master/config/config.json

# 4. Cron job'ları ayarla
crontab -e
*/5 * * * * php /var/www/html/api-master/CORN/cron_runner.php
📚 API Referansı
Provider Manager
php
$manager->getAllProviders();
$manager->getProvider($name);
$manager->enableProvider($name);
$manager->disableProvider($name);
$manager->testProvider($name);
Vector Index
php
$index->addVector($id, $vector, $metadata);
$index->search($query_vector, $k);
$index->deleteVector($id);
$index->getStats();
Learning Engine
php
$engine->train($samples);
$engine->predict($input);
$engine->getAccuracy();
$engine->exportModel();
🐛 Debugging
Debug Modu
php
define('APIM_DEBUG', true);

// Debug logları
APIMaster_Debug::log($variable);
APIMaster_Debug::dump($array);
Performance Profiling
php
$profiler = new APIMaster_Profiler();
$profiler->start('api_call');
// ... işlemler
$profiler->end('api_call');
$profiler->report();
🤝 Katkıda Bulunma
Fork the repository

Create feature branch (git checkout -b feature/amazing-feature)

Commit changes (git commit -m 'Add amazing feature')

Push to branch (git push origin feature/amazing-feature)

Open Pull Request

📞 İletişim
Proje Sahibi: API Master Team

Email: dev@apimaster.com

GitHub: https://github.com/apimaster/api-master