<?php
/**
 * APIMaster Test Bootstrap
 * 
 * @package APIMaster
 * @since 1.0.0
 */

// Hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Zaman limiti yok (testler için)
set_time_limit(0);

// Sabit tanımlamalar
define('APIMASTER_TEST_ROOT', dirname(__DIR__));
define('APIMASTER_TEST_DATA', APIMASTER_TEST_ROOT . '/tests/_data');
define('APIMASTER_TEST_OUTPUT', APIMASTER_TEST_ROOT . '/tests/_output');

// Test veritabanı (geçici)
define('APIMASTER_TEST_CONFIG', APIMASTER_TEST_ROOT . '/config/config.json');

// Mock cURL sınıfı (WordPress yok!)
class APIMaster_MockCurl {
    private $handles = [];
    private $responses = [];
    private $options = [];
    
    public function init($url = null) {
        $handle = curl_init($url);
        $this->handles[] = $handle;
        return $handle;
    }
    
    public function setOpt($handle, $option, $value) {
        $this->options[spl_object_id($handle)][$option] = $value;
        return true;
    }
    
    public function exec($handle) {
        // Mock response
        $id = spl_object_id($handle);
        if (isset($this->responses[$id])) {
            return $this->responses[$id];
        }
        return json_encode(['status' => 'mock_success', 'data' => []]);
    }
    
    public function getInfo($handle, $opt = null) {
        return $opt === CURLINFO_HTTP_CODE ? 200 : 0;
    }
    
    public function error($handle) {
        return '';
    }
    
    public function errno($handle) {
        return 0;
    }
    
    public function close($handle) {
        // Mock close
    }
    
    public function setMockResponse($handle, $response) {
        $this->responses[spl_object_id($handle)] = $response;
    }
}

// Test helper fonksiyonları
function apimaster_test_get_config() {
    $configFile = APIMASTER_TEST_CONFIG;
    if (!file_exists($configFile)) {
        return [
            'apis' => [],
            'cache' => ['enabled' => false, 'ttl' => 3600],
            'logging' => ['enabled' => true, 'level' => 'debug']
        ];
    }
    return json_decode(file_get_contents($configFile), true);
}

function apimaster_test_log($message, $type = 'info') {
    $logFile = APIMASTER_TEST_OUTPUT . '/test.log';
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] [{$type}] {$message}\n", FILE_APPEND);
}

// Otomatik yükleyici
spl_autoload_register(function($className) {
    $prefix = 'APIMaster_';
    if (strpos($className, $prefix) !== 0) {
        return;
    }
    
    $className = substr($className, strlen($prefix));
    $classFile = APIMASTER_TEST_ROOT . '/core/class-' . strtolower(str_replace('_', '-', $className)) . '.php';
    
    // Alternatif yollar
    $altPaths = [
        APIMASTER_TEST_ROOT . '/api/class-' . strtolower(str_replace('_', '-', $className)) . '.php',
        APIMASTER_TEST_ROOT . '/includes/class-' . strtolower(str_replace('_', '-', $className)) . '.php',
        APIMASTER_TEST_ROOT . '/security/class-' . strtolower(str_replace('_', '-', $className)) . '.php',
        APIMASTER_TEST_ROOT . '/cache/class-' . strtolower(str_replace('_', '-', $className)) . '.php',
        APIMASTER_TEST_ROOT . '/vector/class-' . strtolower(str_replace('_', '-', $className)) . '.php',
        APIMASTER_TEST_ROOT . '/learning/class-' . strtolower(str_replace('_', '-', $className)) . '.php',
    ];
    
    if (file_exists($classFile)) {
        require_once $classFile;
    } else {
        foreach ($altPaths as $path) {
            if (file_exists($path)) {
                require_once $path;
                return;
            }
        }
    }
});

// Test başlangıcı
apimaster_test_log("=== APIMaster Test Suite Başlatıldı ===");
apimaster_test_log("Test Root: " . APIMASTER_TEST_ROOT);

echo "\n🚀 APIMaster Test Suite\n";
echo "======================\n";
echo "✅ Bootstrap yüklendi\n";
echo "📁 Test Root: " . APIMASTER_TEST_ROOT . "\n\n";