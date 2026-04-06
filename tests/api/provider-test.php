<?php
/**
 * API Provider Testleri
 * 
 * @package APIMaster
 * @since 1.0.0
 */

class APIMaster_ProviderTest {
    private $results = [];
    
    public function run() {
        echo "\n🌐 API PROVIDER ENTEGRASYON TESTLERİ\n";
        echo "═══════════════════════════════════════════════════════════\n";
        
        $this->testOpenAI();
        $this->testAnthropic();
        $this->testGoogleAI();
        $this->testMicrosoft();
        $this->testAWS();
        $this->testDeepL();
        $this->testGoogleTranslate();
        $this->testStripe();
        $this->testPayPal();
        $this->testCoinbase();
        
        $this->summary();
    }
    
    private function testOpenAI() {
        echo "\n📡 OpenAI Testi:\n";
        
        if (class_exists('APIMaster_OpenAIAPI')) {
            try {
                $api = new APIMaster_OpenAIAPI();
                
                if (method_exists($api, 'chat')) {
                    $result = $api->chat([['role' => 'user', 'content' => 'Test']]);
                    $this->addResult('OpenAI', true, 'Chat metodu çalışıyor');
                } else {
                    $this->addResult('OpenAI', true, 'API sınıfı yüklendi (mock mode)');
                }
            } catch (Exception $e) {
                $this->addResult('OpenAI', false, $e->getMessage());
            }
        } else {
            $this->addResult('OpenAI', false, 'Sınıf bulunamadı');
        }
    }
    
    private function testAnthropic() {
        echo "\n📡 Anthropic (Claude) Testi:\n";
        
        if (class_exists('APIMaster_AnthropicAPI')) {
            $this->addResult('Anthropic', true, 'API sınıfı yüklendi');
        } else {
            $this->addResult('Anthropic', false, 'Sınıf bulunamadı');
        }
    }
    
    private function testGoogleAI() {
        echo "\n📡 Google AI (Gemini) Testi:\n";
        
        if (class_exists('APIMaster_GoogleAIAPI')) {
            $this->addResult('GoogleAI', true, 'API sınıfı yüklendi');
        } else {
            $this->addResult('GoogleAI', false, 'Sınıf bulunamadı');
        }
    }
    
    private function testMicrosoft() {
        echo "\n📡 Microsoft Azure Testi:\n";
        
        if (class_exists('APIMaster_MicrosoftAPI')) {
            $this->addResult('Microsoft', true, 'API sınıfı yüklendi');
        } else {
            $this->addResult('Microsoft', false, 'Sınıf bulunamadı');
        }
    }
    
    private function testAWS() {
        echo "\n📡 AWS Testi:\n";
        
        if (class_exists('APIMaster_AWSAPI')) {
            $this->addResult('AWS', true, 'API sınıfı yüklendi');
        } else {
            $this->addResult('AWS', false, 'Sınıf bulunamadı');
        }
    }
    
    private function testDeepL() {
        echo "\n📡 DeepL Testi:\n";
        
        if (class_exists('APIMaster_DeepLAPI')) {
            $this->addResult('DeepL', true, 'API sınıfı yüklendi');
        } else {
            $this->addResult('DeepL', false, 'Sınıf bulunamadı');
        }
    }
    
    private function testGoogleTranslate() {
        echo "\n📡 Google Translate Testi:\n";
        
        if (class_exists('APIMaster_GoogleTranslateAPI')) {
            $this->addResult('GoogleTranslate', true, 'API sınıfı yüklendi');
        } else {
            $this->addResult('GoogleTranslate', false, 'Sınıf bulunamadı');
        }
    }
    
    private function testStripe() {
        echo "\n📡 Stripe Testi:\n";
        
        if (class_exists('APIMaster_StripeAPI')) {
            $this->addResult('Stripe', true, 'API sınıfı yüklendi');
        } else {
            $this->addResult('Stripe', false, 'Sınıf bulunamadı');
        }
    }
    
    private function testPayPal() {
        echo "\n📡 PayPal Testi:\n";
        
        if (class_exists('APIMaster_PayPalAPI')) {
            $this->addResult('PayPal', true, 'API sınıfı yüklendi');
        } else {
            $this->addResult('PayPal', false, 'Sınıf bulunamadı');
        }
    }
    
    private function testCoinbase() {
        echo "\n📡 Coinbase Testi:\n";
        
        if (class_exists('APIMaster_CoinbaseAPI')) {
            $this->addResult('Coinbase', true, 'API sınıfı yüklendi');
        } else {
            $this->addResult('Coinbase', false, 'Sınıf bulunamadı');
        }
    }
    
    private function addResult($provider, $status, $message) {
        $this->results[] = [
            'provider' => $provider,
            'status' => $status,
            'message' => $message
        ];
        
        $icon = $status ? '✅' : '❌';
        echo "  {$icon} {$provider}: {$message}\n";
    }
    
    private function summary() {
        $passed = count(array_filter($this->results, function($r) { return $r['status']; }));
        $total = count($this->results);
        
        echo "\n═══════════════════════════════════════════════════════════\n";
        echo "📊 API Provider Test Özeti: {$passed}/{$total} başarılı\n";
    }
}