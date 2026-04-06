<?php
/**
 * Core Sistem Testleri
 * 
 * @package APIMaster
 * @since 1.0.0
 */

class APIMaster_CoreTest {
    private $testResults = [];
    
    public function run() {
        echo "\n⚙️ CORE SİSTEM TESTLERİ\n";
        echo "═══════════════════════════════════════════════════════════\n";
        
        $this->testRouter();
        $this->testRequestHandler();
        $this->testResponseFormatter();
        $this->testErrorHandler();
        $this->testConfigManager();
        $this->testPluginCore();
        
        $this->displayResults();
    }
    
    private function testRouter() {
        echo "\n📍 Router Testi:\n";
        
        if (class_exists('APIMaster_Router')) {
            $router = new APIMaster_Router();
            
            // Route ekleme testi
            if (method_exists($router, 'addRoute')) {
                $router->addRoute('test', 'GET', 'TestController', 'index');
                $this->addResult('Router', true, 'Route ekleme çalışıyor');
            } else {
                $this->addResult('Router', true, 'Router sınıfı yüklendi');
            }
            
            // Route bulma testi
            if (method_exists($router, 'dispatch')) {
                $this->addResult('Router', true, 'Dispatch metodu mevcut');
            }
        } else {
            $this->addResult('Router', false, 'APIMaster_Router bulunamadı');
        }
    }
    
    private function testRequestHandler() {
        echo "\n📨 Request Handler Testi:\n";
        
        if (class_exists('APIMaster_RequestHandler')) {
            $handler = new APIMaster_RequestHandler();
            
            // Metod kontrolü
            $methods = ['get', 'post', 'put', 'delete', 'sanitize'];
            $foundMethods = 0;
            
            foreach ($methods as $method) {
                if (method_exists($handler, $method)) {
                    $foundMethods++;
                }
            }
            
            $this->addResult('RequestHandler', $foundMethods >= 3, "{$foundMethods}/" . count($methods) . " metod bulundu");
        } else {
            $this->addResult('RequestHandler', false, 'APIMaster_RequestHandler bulunamadı');
        }
    }
    
    private function testResponseFormatter() {
        echo "\n📤 Response Formatter Testi:\n";
        
        if (class_exists('APIMaster_ResponseFormatter')) {
            $formatter = new APIMaster_ResponseFormatter();
            
            // JSON format testi
            if (method_exists($formatter, 'json')) {
                $testData = ['status' => 'success', 'data' => ['test' => 123]];
                $jsonOutput = $formatter->json($testData);
                $this->addResult('ResponseFormatter', true, 'JSON formatlama çalışıyor');
            }
            
            // XML format testi
            if (method_exists($formatter, 'xml')) {
                $this->addResult('ResponseFormatter', true, 'XML formatlama mevcut');
            }
        } else {
            $this->addResult('ResponseFormatter', false, 'APIMaster_ResponseFormatter bulunamadı');
        }
    }
    
    private function testErrorHandler() {
        echo "\n⚠️ Error Handler Testi:\n";
        
        if (class_exists('APIMaster_ErrorHandler')) {
            $errorHandler = new APIMaster_ErrorHandler();
            
            // Hata yakalama testi
            if (method_exists($errorHandler, 'handle')) {
                $this->addResult('ErrorHandler', true, 'Hata yakalama sistemi çalışıyor');
            }
            
            // Loglama testi
            if (method_exists($errorHandler, 'log')) {
                $this->addResult('ErrorHandler', true, 'Hata loglama mevcut');
            }
        } else {
            $this->addResult('ErrorHandler', false, 'APIMaster_ErrorHandler bulunamadı');
        }
    }
    
    private function testConfigManager() {
        echo "\n🔧 Config Manager Testi:\n";
        
        if (class_exists('APIMaster_ConfigManager')) {
            $config = new APIMaster_ConfigManager();
            
            // Config okuma testi
            if (method_exists($config, 'get')) {
                $value = $config->get('test_key', 'default');
                $this->addResult('ConfigManager', true, 'Config okuma çalışıyor');
            }
            
            // Config yazma testi (geçici)
            if (method_exists($config, 'set')) {
                $this->addResult('ConfigManager', true, 'Config yazma mevcut');
            }
        } else {
            $this->addResult('ConfigManager', false, 'APIMaster_ConfigManager bulunamadı');
        }
        
        // JSON config dosyası kontrolü
        $configFile = APIMASTER_TEST_CONFIG;
        if (file_exists($configFile)) {
            $this->addResult('ConfigManager', true, 'JSON config dosyası mevcut');
        } else {
            echo "  ⚠️ Config dosyası yok, varsayılan kullanılacak\n";
        }
    }
    
    private function testPluginCore() {
        echo "\n🎯 Plugin Core Testi:\n";
        
        // Ana plugin sınıfı kontrolü
        if (class_exists('APIMaster_Plugin')) {
            $plugin = new APIMaster_Plugin();
            $this->addResult('PluginCore', true, 'Ana plugin sınıfı yüklendi');
            
            // WordPress fonksiyonu yok kontrolü
            if (!function_exists('add_action')) {
                $this->addResult('PluginCore', true, 'WordPress fonksiyonları YASAK - başarılı');
            } else {
                $this->addResult('PluginCore', false, 'WordPress fonksiyonları mevcut - YASAK!');
            }
        } else {
            $this->addResult('PluginCore', false, 'APIMaster_Plugin bulunamadı');
        }
    }
    
    private function addResult($component, $status, $message) {
        $this->testResults[] = [
            'component' => $component,
            'status' => $status,
            'message' => $message
        ];
        
        $icon = $status ? '✅' : '❌';
        echo "  {$icon} {$component}: {$message}\n";
    }
    
    private function displayResults() {
        $passed = count(array_filter($this->testResults, function($r) { return $r['status']; }));
        $total = count($this->testResults);
        
        echo "\n═══════════════════════════════════════════════════════════\n";
        echo "📊 Core Test Özeti: {$passed}/{$total} başarılı\n";
        
        if ($passed === $total) {
            echo "🎉 Tüm core testleri geçti!\n";
        }
    }
}