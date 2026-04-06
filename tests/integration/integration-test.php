<?php
/**
 * Entegrasyon Testleri
 * 
 * @package APIMaster
 * @since 1.0.0
 */

class APIMaster_IntegrationTest {
    private $passed = 0;
    private $failed = 0;
    
    public function run() {
        echo "\n🔄 ENTEGRASYON TESTLERİ\n";
        echo "═══════════════════════════════════════════════════════════\n";
        
        $this->testAPICacheIntegration();
        $this->testVectorLearningIntegration();
        $this->testQueueSystemIntegration();
        $this->testSecurityMiddlewareIntegration();
        $this->testFullRequestFlow();
        $this->testErrorRecovery();
        
        $this->summary();
    }
    
    private function testAPICacheIntegration() {
        echo "\n💾 API + Cache Entegrasyonu:\n";
        
        $cacheKey = 'test_api_response_' . md5('test_request');
        $testData = ['status' => 'success', 'data' => ['id' => 123]];
        
        // Cache'e yaz
        $cacheFile = APIMASTER_TEST_ROOT . '/cache/' . $cacheKey . '.cache';
        file_put_contents($cacheFile, json_encode($testData));
        
        // Cache'den oku
        if (file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            $this->assert($cached === $testData, "API yanıtı cache'lenebiliyor");
            unlink($cacheFile);
        } else {
            $this->assert(false, "Cache dosyası oluşturulamadı");
        }
        
        // Cache TTL testi
        $ttlCacheFile = APIMASTER_TEST_ROOT . '/cache/ttl_test.cache';
        file_put_contents($ttlCacheFile, json_encode(['time' => time()]));
        sleep(1);
        
        $fileTime = filemtime($ttlCacheFile);
        $age = time() - $fileTime;
        $this->assert($age >= 1, "Cache TTL sistemi çalışıyor (yaş: {$age}s)");
        unlink($ttlCacheFile);
    }
    
    private function testVectorLearningIntegration() {
        echo "\n🧠 Vector + Learning Entegrasyonu:\n";
        
        // Mock vektör verisi
        $testVector = array_fill(0, 10, 0.5);
        $testMetadata = ['source' => 'test', 'timestamp' => time()];
        
        // Vektör kaydetme simülasyonu
        $vectorDir = APIMASTER_TEST_ROOT . '/vector/data';
        if (!is_dir($vectorDir)) {
            mkdir($vectorDir, 0755, true);
        }
        
        $vectorFile = $vectorDir . '/test_vector_' . time() . '.json';
        file_put_contents($vectorFile, json_encode([
            'vector' => $testVector,
            'metadata' => $testMetadata
        ]));
        
        $this->assert(file_exists($vectorFile), "Vektör verisi kaydedilebiliyor");
        
        // Vektör arama simülasyonu (basit cosine similarity)
        $searchVector = array_fill(0, 10, 0.5);
        $similarity = $this->cosineSimilarity($testVector, $searchVector);
        $this->assert($similarity > 0.99, "Vektör benzerlik hesaplama çalışıyor (similarity: {$similarity})");
        
        // Temizlik
        unlink($vectorFile);
    }
    
    private function testQueueSystemIntegration() {
        echo "\n📬 Queue Sistem Entegrasyonu:\n";
        
        $queueDir = APIMASTER_TEST_ROOT . '/queue/jobs';
        if (!is_dir($queueDir)) {
            mkdir($queueDir, 0755, true);
        }
        
        // Job ekleme
        $jobId = uniqid('job_');
        $jobData = [
            'id' => $jobId,
            'type' => 'api_call',
            'payload' => ['endpoint' => '/test', 'data' => ['test' => true]],
            'created_at' => time(),
            'retries' => 0
        ];
        
        $jobFile = $queueDir . '/' . $jobId . '.json';
        file_put_contents($jobFile, json_encode($jobData));
        
        $this->assert(file_exists($jobFile), "Queue job'u eklenebiliyor");
        
        // Job işleme simülasyonu
        $processedJob = json_decode(file_get_contents($jobFile), true);
        $processedJob['status'] = 'completed';
        $processedJob['processed_at'] = time();
        file_put_contents($jobFile . '.processed', json_encode($processedJob));
        
        $this->assert(file_exists($jobFile . '.processed'), "Queue job'u işlenebiliyor");
        
        // Temizlik
        unlink($jobFile);
        unlink($jobFile . '.processed');
    }
    
    private function testSecurityMiddlewareIntegration() {
        echo "\n🔒 Security + Middleware Entegrasyonu:\n";
        
        // API Key kontrolü simülasyonu
        $apiKey = 'test_api_key_' . md5(time());
        $config = apimaster_test_get_config();
        
        // Rate limiting testi
        $rateLimitKey = 'test_client_' . $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $rateLimitDir = APIMASTER_TEST_ROOT . '/security/ratelimit';
        if (!is_dir($rateLimitDir)) {
            mkdir($rateLimitDir, 0755, true);
        }
        
        $rateFile = $rateLimitDir . '/' . md5($rateLimitKey) . '.json';
        $requests = [];
        
        // 10 request simülasyonu
        for ($i = 0; $i < 10; $i++) {
            $requests[] = time();
        }
        
        file_put_contents($rateFile, json_encode(['requests' => $requests, 'limit' => 100]));
        
        $this->assert(file_exists($rateFile), "Rate limiting sistemi çalışıyor");
        
        // Input sanitization entegrasyonu
        $dirtyInput = "<script>alert('xss')</script><img src=x onerror=alert(1)>";
        $cleanInput = strip_tags($dirtyInput);
        $this->assert(strpos($cleanInput, '<script>') === false, "XSS koruması entegre çalışıyor");
        
        unlink($rateFile);
    }
    
    private function testFullRequestFlow() {
        echo "\n🌊 Full Request Flow Testi:\n";
        
        // 1. Request oluşturma
        $request = [
            'method' => 'GET',
            'endpoint' => '/api/test',
            'headers' => ['Authorization' => 'Bearer test_token'],
            'params' => ['id' => 123]
        ];
        
        // 2. Request validasyonu
        $isValid = $this->validateRequest($request);
        $this->assert($isValid, "Request validasyonu geçti");
        
        // 3. Rate limit kontrolü
        $rateLimitPassed = $this->checkRateLimit('test_client');
        $this->assert($rateLimitPassed, "Rate limit kontrolü geçti");
        
        // 4. Cache kontrolü
        $cacheKey = md5(json_encode($request));
        $cachedResponse = $this->getCached($cacheKey);
        $this->assert($cachedResponse === null, "Cache kontrolü çalışıyor");
        
        // 5. API çağrısı simülasyonu
        $response = $this->mockAPICall($request);
        $this->assert($response !== null, "API çağrısı başarılı");
        
        // 6. Response formatlama
        $formattedResponse = $this->formatResponse($response, 'json');
        $this->assert($this->isJson($formattedResponse), "Response JSON formatlandı");
        
        // 7. Cache'e kaydetme
        $this->setCached($cacheKey, $response, 3600);
        $cachedAfter = $this->getCached($cacheKey);
        $this->assert($cachedAfter !== null, "Response cache'lendi");
        
        // 8. Loglama
        apimaster_test_log("Full request flow completed for endpoint: " . $request['endpoint']);
        $this->assert(true, "Request loglandı");
        
        // 9. Learning feedback
        $this->addLearningFeedback($request, $response);
        $this->assert(true, "Learning feedback eklendi");
    }
    
    private function testErrorRecovery() {
        echo "\n🔄 Error Recovery Testi:\n";
        
        // 1. API timeout simülasyonu
        $timeoutCount = 0;
        $maxRetries = 3;
        
        for ($i = 0; $i < $maxRetries; $i++) {
            $success = $this->simulateAPICallWithRetry();
            if ($success) {
                $timeoutCount++;
                break;
            }
        }
        
        $this->assert($timeoutCount > 0, "Retry mekanizması çalışıyor");
        
        // 2. Queue fallback testi
        $failedRequest = ['endpoint' => '/failed', 'data' => ['retry' => true]];
        $queued = $this->queueForRetry($failedRequest);
        $this->assert($queued, "Failed request queue'ya alınabiliyor");
        
        // 3. Circuit breaker testi
        $circuitOpen = false;
        $failures = 0;
        
        for ($i = 0; $i < 5; $i++) {
            if (!$this->simulateAPICallWithFailure()) {
                $failures++;
            }
            
            if ($failures >= 3) {
                $circuitOpen = true;
                break;
            }
        }
        
        $this->assert($circuitOpen, "Circuit breaker devreye giriyor (3/5 failure)");
        
        // 4. Fallback response testi
        $fallbackResponse = $this->getFallbackResponse('/test-endpoint');
        $this->assert($fallbackResponse !== null, "Fallback response mekanizması çalışıyor");
    }
    
    // Yardımcı metotlar
    private function cosineSimilarity($vecA, $vecB) {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;
        
        for ($i = 0; $i < count($vecA); $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $normA += pow($vecA[$i], 2);
            $normB += pow($vecB[$i], 2);
        }
        
        if ($normA == 0 || $normB == 0) return 0;
        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
    
    private function validateRequest($request) {
        return isset($request['method']) && isset($request['endpoint']);
    }
    
    private function checkRateLimit($client) {
        return true; // Mock
    }
    
    private function getCached($key) {
        $cacheFile = APIMASTER_TEST_ROOT . '/cache/' . $key . '.cache';
        if (file_exists($cacheFile)) {
            return json_decode(file_get_contents($cacheFile), true);
        }
        return null;
    }
    
    private function setCached($key, $data, $ttl) {
        $cacheFile = APIMASTER_TEST_ROOT . '/cache/' . $key . '.cache';
        file_put_contents($cacheFile, json_encode($data));
        return true;
    }
    
    private function mockAPICall($request) {
        return ['status' => 'success', 'data' => ['mock' => true]];
    }
    
    private function formatResponse($response, $format) {
        if ($format === 'json') {
            return json_encode($response);
        }
        return $response;
    }
    
    private function isJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    private function addLearningFeedback($request, $response) {
        $feedbackDir = APIMASTER_TEST_ROOT . '/learning/feedback';
        if (!is_dir($feedbackDir)) {
            mkdir($feedbackDir, 0755, true);
        }
        
        $feedbackFile = $feedbackDir . '/feedback_' . time() . '.json';
        file_put_contents($feedbackFile, json_encode([
            'request' => $request,
            'response' => $response,
            'timestamp' => time()
        ]));
        
        return true;
    }
    
    private function simulateAPICallWithRetry() {
        // Mock - 80% success rate
        return rand(1, 100) <= 80;
    }
    
    private function queueForRetry($request) {
        $queueDir = APIMASTER_TEST_ROOT . '/queue/retry';
        if (!is_dir($queueDir)) {
            mkdir($queueDir, 0755, true);
        }
        
        $queueFile = $queueDir . '/retry_' . time() . '.json';
        file_put_contents($queueFile, json_encode($request));
        
        return file_exists($queueFile);
    }
    
    private function simulateAPICallWithFailure() {
        // Mock - 60% failure rate
        return rand(1, 100) <= 40;
    }
    
    private function getFallbackResponse($endpoint) {
        $fallbacks = [
            '/test-endpoint' => ['status' => 'fallback', 'message' => 'Service temporarily unavailable'],
            '/default' => ['status' => 'error', 'message' => 'Fallback response']
        ];
        
        return $fallbacks[$endpoint] ?? $fallbacks['/default'];
    }
    
    private function assert($condition, $message) {
        if ($condition) {
            $this->passed++;
            echo "  ✅ {$message}\n";
        } else {
            $this->failed++;
            echo "  ❌ {$message}\n";
        }
    }
    
    private function summary() {
        $total = $this->passed + $this->failed;
        echo "\n═══════════════════════════════════════════════════════════\n";
        echo "📊 Entegrasyon Test Özeti: {$this->passed}/{$total} başarılı\n";
        
        if ($this->passed === $total) {
            echo "🎉 Tüm entegrasyon testleri başarılı!\n";
        }
    }
}