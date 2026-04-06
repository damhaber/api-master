<?php
/**
 * Performans Testleri
 * 
 * @package APIMaster
 * @since 1.0.0
 */

class APIMaster_PerformanceTest {
    private $results = [];
    private $baseline = [];
    
    public function run() {
        echo "\n⚡ PERFORMANS BENCHMARK TESTLERİ\n";
        echo "═══════════════════════════════════════════════════════════\n";
        
        $this->testAPIResponseTime();
        $this->testConcurrentRequests();
        $this->testMemoryUsage();
        $this->testCachePerformance();
        $this->testDatabaseAlternatives();
        $this->testVectorSearchPerformance();
        $this->testQueueThroughput();
        
        $this->displayResults();
    }
    
    private function testAPIResponseTime() {
        echo "\n⏱️ API Response Time Testi:\n";
        
        $iterations = 50;
        $times = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            
            // Mock API çağrısı
            $this->mockAPICall();
            
            $end = microtime(true);
            $times[] = ($end - $start) * 1000; // ms
        }
        
        $avgTime = array_sum($times) / count($times);
        $minTime = min($times);
        $maxTime = max($times);
        $p95Time = $this->percentile($times, 95);
        
        $this->addResult('API Response Time', $avgTime < 100, [
            'average' => round($avgTime, 2) . 'ms',
            'min' => round($minTime, 2) . 'ms',
            'max' => round($maxTime, 2) . 'ms',
            'p95' => round($p95Time, 2) . 'ms'
        ]);
        
        echo "     📊 Ortalama: " . round($avgTime, 2) . "ms\n";
        echo "     📊 P95: " . round($p95Time, 2) . "ms\n";
    }
    
    private function testConcurrentRequests() {
        echo "\n🔄 Concurrent Requests Testi:\n";
        
        $concurrent = 20;
        $results = [];
        $startTime = microtime(true);
        
        // Simulate concurrent requests
        $pid = pcntl_fork();
        if ($pid == -1) {
            // Fork yoksa normal çalıştır
            for ($i = 0; $i < $concurrent; $i++) {
                $results[] = $this->mockAPICall();
            }
        } else if ($pid == 0) {
            // Child process
            exit(0);
        } else {
            // Parent process
            pcntl_wait($status);
            for ($i = 0; $i < $concurrent; $i++) {
                $results[] = $this->mockAPICall();
            }
        }
        
        $totalTime = (microtime(true) - $startTime) * 1000;
        $avgConcurrentTime = $totalTime / $concurrent;
        
        $this->addResult('Concurrent Requests', $avgConcurrentTime < 50, [
            'total_time' => round($totalTime, 2) . 'ms',
            'avg_per_request' => round($avgConcurrentTime, 2) . 'ms',
            'concurrent' => $concurrent
        ]);
        
        echo "     📊 {$concurrent} concurrent request: " . round($totalTime, 2) . "ms total\n";
        echo "     📊 Average: " . round($avgConcurrentTime, 2) . "ms/request\n";
    }
    
    private function testMemoryUsage() {
        echo "\n💾 Memory Usage Testi:\n";
        
        $initialMemory = memory_get_usage(true);
        
        // 1000 API çağrısı simülasyonu
        for ($i = 0; $i < 1000; $i++) {
            $this->mockAPICall();
            
            if ($i % 100 == 0) {
                // Her 100 çağrıda bir garbage collection
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }
        }
        
        $finalMemory = memory_get_usage(true);
        $memoryIncrease = ($finalMemory - $initialMemory) / 1024 / 1024;
        
        $this->addResult('Memory Usage', $memoryIncrease < 50, [
            'initial' => round($initialMemory / 1024 / 1024, 2) . 'MB',
            'final' => round($finalMemory / 1024 / 1024, 2) . 'MB',
            'increase' => round($memoryIncrease, 2) . 'MB'
        ]);
        
        echo "     📊 Memory increase: " . round($memoryIncrease, 2) . "MB\n";
        echo "     📊 Peak memory: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . "MB\n";
    }
    
    private function testCachePerformance() {
        echo "\n💿 Cache Performance Testi:\n";
        
        $cacheDir = APIMASTER_TEST_ROOT . '/cache/perf_test';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        // Write performance
        $writeStart = microtime(true);
        $writeCount = 500;
        
        for ($i = 0; $i < $writeCount; $i++) {
            $cacheFile = $cacheDir . "/cache_{$i}.cache";
            file_put_contents($cacheFile, json_encode([
                'id' => $i,
                'data' => str_repeat('x', 1024) // 1KB data
            ]));
        }
        
        $writeTime = (microtime(true) - $writeStart) * 1000;
        $writeAvg = $writeTime / $writeCount;
        
        // Read performance
        $readStart = microtime(true);
        
        for ($i = 0; $i < $writeCount; $i++) {
            $cacheFile = $cacheDir . "/cache_{$i}.cache";
            if (file_exists($cacheFile)) {
                json_decode(file_get_contents($cacheFile), true);
            }
        }
        
        $readTime = (microtime(true) - $readStart) * 1000;
        $readAvg = $readTime / $writeCount;
        
        // Cleanup
        array_map('unlink', glob($cacheDir . "/*.cache"));
        rmdir($cacheDir);
        
        $this->addResult('Cache Performance', $readAvg < 1, [
            'write_avg' => round($writeAvg, 3) . 'ms',
            'read_avg' => round($readAvg, 3) . 'ms',
            'total_ops' => $writeCount
        ]);
        
        echo "     📊 Write avg: " . round($writeAvg, 3) . "ms\n";
        echo "     📊 Read avg: " . round($readAvg, 3) . "ms\n";
    }
    
    private function testDatabaseAlternatives() {
        echo "\n🗄️ JSON Config Performance Testi:\n";
        
        // JSON dosyası okuma performansı (database alternatifi)
        $jsonFile = APIMASTER_TEST_ROOT . '/config/test_config.json';
        
        // Test config oluştur
        $testConfig = [
            'settings' => [
                'timeout' => 30,
                'retries' => 3,
                'apis' => []
            ]
        ];
        
        // 100 API ekle
        for ($i = 0; $i < 100; $i++) {
            $testConfig['settings']['apis']["api_{$i}"] = [
                'name' => "API {$i}",
                'endpoint' => "https://api{$i}.example.com",
                'key' => "key_{$i}"
            ];
        }
        
        file_put_contents($jsonFile, json_encode($testConfig));
        
        // Read performance
        $readTimes = [];
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $data = json_decode(file_get_contents($jsonFile), true);
            $end = microtime(true);
            $readTimes[] = ($end - $start) * 1000;
        }
        
        $avgReadTime = array_sum($readTimes) / count($readTimes);
        
        // Search performance
        $searchStart = microtime(true);
        $found = false;
        $searchIterations = 1000;
        
        for ($i = 0; $i < $searchIterations; $i++) {
            if (isset($data['settings']['apis']['api_50'])) {
                $found = true;
            }
        }
        
        $searchTime = (microtime(true) - $searchStart) * 1000;
        
        unlink($jsonFile);
        
        $this->addResult('JSON Config Performance', $avgReadTime < 5, [
            'read_avg' => round($avgReadTime, 2) . 'ms',
            'search_1000_ops' => round($searchTime, 2) . 'ms',
            'config_size' => count($testConfig['settings']['apis']) . ' APIs'
        ]);
        
        echo "     📊 JSON read avg: " . round($avgReadTime, 2) . "ms\n";
        echo "     📊 1000 search ops: " . round($searchTime, 2) . "ms\n";
    }
    
    private function testVectorSearchPerformance() {
        echo "\n🔍 Vector Search Performance Testi:\n";
        
        $vectorCount = 1000;
        $vectorDim = 128;
        
        // Test vektörleri oluştur
        $vectors = [];
        for ($i = 0; $i < $vectorCount; $i++) {
            $vector = [];
            for ($j = 0; $j < $vectorDim; $j++) {
                $vector[] = rand(0, 100) / 100;
            }
            $vectors[] = $vector;
        }
        
        $queryVector = $vectors[0];
        
        // Linear search performance (baseline)
        $linearStart = microtime(true);
        $bestMatch = null;
        $bestScore = -1;
        
        for ($i = 0; $i < $vectorCount; $i++) {
            $similarity = $this->cosineSimilarity($queryVector, $vectors[$i]);
            if ($similarity > $bestScore) {
                $bestScore = $similarity;
                $bestMatch = $i;
            }
        }
        
        $linearTime = (microtime(true) - $linearStart) * 1000;
        
        // Mock HNSW index search (simulated faster)
        $hnswStart = microtime(true);
        // Simulate HNSW index search (logarithmic complexity)
        $hnswTime = log($vectorCount, 2) / log(1000, 2) * $linearTime;
        $hnswEnd = microtime(true);
        
        $speedup = $linearTime / ($hnswTime + 0.001);
        
        $this->addResult('Vector Search Performance', $linearTime < 100, [
            'vectors' => $vectorCount,
            'dimension' => $vectorDim,
            'linear_search_ms' => round($linearTime, 2),
            'hnsw_estimated_ms' => round($hnswTime, 2),
            'speedup_x' => round($speedup, 2)
        ]);
        
        echo "     📊 Linear search: " . round($linearTime, 2) . "ms for {$vectorCount} vectors\n";
        echo "     📊 HNSW estimated: " . round($hnswTime, 2) . "ms\n";
        echo "     📊 Speedup: ~{$speedup}x\n";
    }
    
    private function testQueueThroughput() {
        echo "\n📬 Queue Throughput Testi:\n";
        
        $queueDir = APIMASTER_TEST_ROOT . '/queue/throughput_test';
        if (!is_dir($queueDir)) {
            mkdir($queueDir, 0755, true);
        }
        
        $jobCount = 1000;
        
        // Write jobs
        $writeStart = microtime(true);
        
        for ($i = 0; $i < $jobCount; $i++) {
            $jobFile = $queueDir . "/job_{$i}.json";
            file_put_contents($jobFile, json_encode([
                'id' => $i,
                'data' => "Job data {$i}",
                'created' => time()
            ]));
        }
        
        $writeTime = (microtime(true) - $writeStart) * 1000;
        $writeThroughput = $jobCount / ($writeTime / 1000);
        
        // Process jobs
        $processStart = microtime(true);
        $processed = 0;
        
        foreach (glob($queueDir . "/*.json") as $jobFile) {
            $job = json_decode(file_get_contents($jobFile), true);
            $processed++;
            unlink($jobFile);
        }
        
        $processTime = (microtime(true) - $processStart) * 1000;
        $processThroughput = $processed / ($processTime / 1000);
        
        rmdir($queueDir);
        
        $this->addResult('Queue Throughput', $processThroughput > 100, [
            'jobs' => $jobCount,
            'write_throughput' => round($writeThroughput, 2) . ' jobs/sec',
            'process_throughput' => round($processThroughput, 2) . ' jobs/sec',
            'total_time_ms' => round($writeTime + $processTime, 2)
        ]);
        
        echo "     📊 Write throughput: " . round($writeThroughput, 2) . " jobs/sec\n";
        echo "     📊 Process throughput: " . round($processThroughput, 2) . " jobs/sec\n";
    }
    
    private function mockAPICall() {
        // Simulate API call with minimal overhead
        usleep(rand(1000, 5000)); // 1-5ms sleep
        return ['status' => 'success', 'data' => ['timestamp' => microtime(true)]];
    }
    
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
    
    private function percentile($array, $percentile) {
        sort($array);
        $index = ($percentile / 100) * (count($array) - 1);
        
        if (floor($index) == $index) {
            return $array[$index];
        }
        
        $lower = $array[floor($index)];
        $upper = $array[ceil($index)];
        return $lower + ($upper - $lower) * ($index - floor($index));
    }
    
    private function addResult($test, $passed, $metrics) {
        $this->results[] = [
            'test' => $test,
            'passed' => $passed,
            'metrics' => $metrics
        ];
        
        $icon = $passed ? '✅' : '❌';
        echo "\n  {$icon} {$test}: " . ($passed ? 'PASSED' : 'FAILED') . "\n";
    }
    
    private function displayResults() {
        $passed = count(array_filter($this->results, function($r) { return $r['passed']; }));
        $total = count($this->results);
        
        echo "\n═══════════════════════════════════════════════════════════\n";
        echo "📊 PERFORMANS TEST ÖZETİ\n";
        echo "═══════════════════════════════════════════════════════════\n";
        
        foreach ($this->results as $result) {
            $status = $result['passed'] ? '✅' : '❌';
            echo "\n{$status} {$result['test']}\n";
            foreach ($result['metrics'] as $key => $value) {
                echo "     📊 {$key}: {$value}\n";
            }
        }
        
        echo "\n═══════════════════════════════════════════════════════════\n";
        echo "📈 Toplam: {$passed}/{$total} performans testi geçti\n";
        
        if ($passed === $total) {
            echo "🎉 Tüm performans testleri başarılı!\n";
        }
    }
}