<?php
/**
 * API Master - Çoklu Provider Kullanım Örneği
 * @package APIMaster
 * @subpackage Examples
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../CORE/autoloader.php';

class MultiProviderExample {
    private $apiMaster;
    private $providers = ['openai', 'anthropic', 'google'];
    
    public function __construct() {
        $this->apiMaster = new APIMaster_Core();
    }
    
    // Provider'ları test et
    public function testProviders() {
        echo "========================================\n";
        echo "Provider Testleri\n";
        echo "========================================\n\n";
        
        foreach ($this->providers as $provider) {
            echo "Test ediliyor: " . strtoupper($provider) . "\n";
            $result = $this->apiMaster->testProvider($provider);
            
            if ($result['success']) {
                echo "   ✅ " . $provider . " çalışıyor!\n";
                echo "   Yanıt süresi: " . $result['response_time'] . "ms\n";
            } else {
                echo "   ❌ " . $provider . " çalışmıyor: " . $result['error'] . "\n";
            }
            echo "\n";
        }
    }
    
    // Load balancing ile istek gönder
    public function loadBalancingExample() {
        echo "========================================\n";
        echo "Load Balancing Örneği\n";
        echo "========================================\n\n";
        
        $prompt = "API nedir? Kısa açıkla.";
        
        for ($i = 1; $i <= 5; $i++) {
            echo "İstek #$i:\n";
            
            $response = $this->apiMaster->requestWithLoadBalance([
                'prompt' => $prompt,
                'providers' => $this->providers,
                'strategy' => 'least_busy'  // round_robin, least_busy, fastest
            ]);
            
            echo "   Kullanılan provider: " . $response['provider_used'] . "\n";
            echo "   Yanıt süresi: " . $response['response_time'] . "ms\n";
            echo "   Yanıt: " . substr($response['content'], 0, 100) . "...\n\n";
            
            sleep(1);
        }
    }
    
    // Failover ile istek gönder
    public function failoverExample() {
        echo "========================================\n";
        echo "Failover Örneği\n";
        echo "========================================\n\n";
        
        // Bilerek hatalı bir provider ile başla
        $providers = ['invalid_provider', 'openai', 'anthropic'];
        
        echo "Deneniyor: " . implode(' → ', $providers) . "\n\n";
        
        $response = $this->apiMaster->requestWithFailover([
            'prompt' => 'Merhaba, nasılsın?',
            'providers' => $providers,
            'max_retries' => 3
        ]);
        
        if ($response['success']) {
            echo "✅ Başarılı!\n";
            echo "   Kullanılan provider: " . $response['provider_used'] . "\n";
            echo "   Denenen providerlar: " . implode(', ', $response['attempted_providers']) . "\n";
            echo "   Yanıt: " . $response['content'] . "\n";
        } else {
            echo "❌ Tüm provider'lar başarısız!\n";
        }
    }
    
    // Provider karşılaştırma
    public function compareProviders() {
        echo "========================================\n";
        echo "Provider Karşılaştırma\n";
        echo "========================================\n\n";
        
        $testPrompt = "Bugün hava çok güzel!";
        $results = [];
        
        foreach ($this->providers as $provider) {
            echo $provider . " test ediliyor...\n";
            
            $start = microtime(true);
            $response = $this->apiMaster->request($provider, '/chat', [
                'prompt' => $testPrompt,
                'max_tokens' => 50
            ]);
            $time = (microtime(true) - $start) * 1000;
            
            $results[$provider] = [
                'success' => isset($response['content']),
                'response_time' => round($time, 2),
                'content' => $response['content'] ?? 'Hata'
            ];
        }
        
        echo "\nKarşılaştırma Sonuçları:\n";
        echo str_repeat('-', 60) . "\n";
        printf("%-12s | %-10s | %-30s\n", "Provider", "Süre(ms)", "Yanıt");
        echo str_repeat('-', 60) . "\n";
        
        foreach ($results as $provider => $data) {
            $status = $data['success'] ? '✅' : '❌';
            $shortContent = substr($data['content'], 0, 27) . '...';
            printf("%-12s | %-10s | %-30s\n", 
                $provider . ' ' . $status, 
                $data['response_time'],
                $shortContent
            );
        }
        echo str_repeat('-', 60) . "\n";
    }
    
    // Akıllı routing örneği
    public function smartRoutingExample() {
        echo "========================================\n";
        echo "Akıllı Routing Örneği\n";
        echo "========================================\n\n";
        
        $queries = [
            'code' => 'PHP ile nasıl dosya okunur?',
            'creative' => 'Bir şiir yaz',
            'analysis' => 'Bu metni analiz et: "Yapay zeka..."'
        ];
        
        foreach ($queries as $type => $query) {
            echo "Sorgu tipi: " . strtoupper($type) . "\n";
            echo "Sorgu: " . $query . "\n";
            
            // Learning engine routing kararı verir
            $decision = $this->apiMaster->smartRoute([
                'query' => $query,
                'query_type' => $type,
                'min_confidence' => 0.7
            ]);
            
            echo "   Seçilen provider: " . $decision['provider'] . "\n";
            echo "   Güven skoru: " . ($decision['confidence'] * 100) . "%\n";
            echo "   Sebep: " . $decision['reason'] . "\n\n";
        }
    }
}

// Örneği çalıştır
$example = new MultiProviderExample();

// Testleri çalıştır
$example->testProviders();
$example->loadBalancingExample();
$example->failoverExample();
$example->compareProviders();
$example->smartRoutingExample();

echo "\n========================================\n";
echo "Tüm örnekler tamamlandı!\n";
echo "========================================\n";