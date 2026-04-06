<?php
/**
 * API Master - Vektör ve Memory Örneği
 * @package APIMaster
 * @subpackage Examples
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../CORE/autoloader.php';

class VectorMemoryExample {
    private $apiMaster;
    private $vectorIndex;
    private $memoryConsolidation;
    
    public function __construct() {
        $this->apiMaster = new APIMaster_Core();
        $this->vectorIndex = new APIMaster_VectorIndex();
        $this->memoryConsolidation = new APIMaster_MemoryConsolidation();
    }
    
    // Vektör ekleme örneği
    public function addVectorsExample() {
        echo "========================================\n";
        echo "Vektör Ekleme Örneği\n";
        echo "========================================\n\n";
        
        $documents = [
            ['id' => 'doc1', 'text' => 'PHP bir web programlama dilidir', 'category' => 'programlama'],
            ['id' => 'doc2', 'text' => 'Python veri bilimi için popülerdir', 'category' => 'veri bilimi'],
            ['id' => 'doc3', 'text' => 'JavaScript frontend geliştirmede kullanılır', 'category' => 'web'],
            ['id' => 'doc4', 'text' => 'API Master çoklu API entegrasyon sağlar', 'category' => 'api'],
            ['id' => 'doc5', 'text' => 'Vektör indeksleme ile benzerlik araması yapılır', 'category' => 'vektör']
        ];
        
        echo "Dökümanlar vektörleştiriliyor...\n\n";
        
        foreach ($documents as $doc) {
            // Metni vektöre çevir
            $vector = $this->apiMaster->createEmbedding($doc['text']);
            
            // Vektörü indekse ekle
            $this->vectorIndex->addVector($doc['id'], $vector, [
                'text' => $doc['text'],
                'category' => $doc['category']
            ]);
            
            echo "   ✅ Eklendi: " . $doc['id'] . " - " . $doc['text'] . "\n";
        }
        
        echo "\nToplam vektör sayısı: " . $this->vectorIndex->getCount() . "\n\n";
    }
    
    // Benzerlik arama örneği
    public function searchExample() {
        echo "========================================\n";
        echo "Benzerlik Arama Örneği\n";
        echo "========================================\n\n";
        
        $queries = [
            'web geliştirme dili',
            'veri analizi',
            'API yönetimi'
        ];
        
        foreach ($queries as $query) {
            echo "Sorgu: " . $query . "\n";
            
            // Sorguyu vektöre çevir
            $queryVector = $this->apiMaster->createEmbedding($query);
            
            // Benzerlik ara
            $results = $this->vectorIndex->search($queryVector, 3);
            
            echo "Sonuçlar:\n";
            foreach ($results as $result) {
                $similarityPercent = $result['similarity'] * 100;
                echo "   - [" . round($similarityPercent, 2) . "%] " . $result['metadata']['text'] . "\n";
            }
            echo "\n";
        }
    }
    
    // Memory consolidation örneği
    public function memoryConsolidationExample() {
        echo "========================================\n";
        echo "Memory Consolidation Örneği\n";
        echo "========================================\n\n";
        
        // Kullanım pattern'leri ekle
        $patterns = [
            ['input' => 'openai chat request', 'output' => 'gpt-3.5-turbo', 'count' => 1500],
            ['input' => 'embedding creation', 'output' => 'text-embedding-ada-002', 'count' => 800],
            ['input' => 'high priority request', 'output' => 'gpt-4', 'count' => 200],
            ['input' => 'batch processing', 'output' => 'async queue', 'count' => 500],
            ['input' => 'vector search', 'output' => 'hnsw index', 'count' => 1200]
        ];
        
        echo "Pattern'ler ekleniyor...\n\n";
        
        foreach ($patterns as $pattern) {
            $this->memoryConsolidation->addPattern($pattern);
            echo "   ✅ Eklendi: " . $pattern['input'] . " → " . $pattern['output'] . 
                 " (kullanım: " . $pattern['count'] . ")\n";
        }
        
        echo "\nMemory consolidation çalıştırılıyor...\n";
        $consolidated = $this->memoryConsolidation->consolidate();
        
        echo "\nKonsolide edilen pattern'ler:\n";
        foreach ($consolidated as $pattern) {
            echo "   - " . $pattern['pattern'] . " (önem skoru: " . $pattern['importance'] . ")\n";
        }
        
        echo "\nHafıza durumu:\n";
        $stats = $this->memoryConsolidation->getStats();
        echo "   Toplam pattern: " . $stats['total_patterns'] . "\n";
        echo "   Konsolide pattern: " . $stats['consolidated_patterns'] . "\n";
        echo "   Hafıza kullanımı: " . $stats['memory_usage_mb'] . " MB\n";
    }
    
    // Semantik cache örneği
    public function semanticCacheExample() {
        echo "========================================\n";
        echo "Semantik Cache Örneği\n";
        echo "========================================\n\n";
        
        $queries = [
            'PHP nedir?',
            'PHP programlama dili nedir?',  // Benzer sorgu
            'Python nedir?'
        ];
        
        foreach ($queries as $query) {
            echo "Sorgu: " . $query . "\n";
            
            // Cache'te ara
            $cached = $this->apiMaster->semanticCacheGet($query);
            
            if ($cached) {
                echo "   ✅ Cache'ten alındı!\n";
                echo "   Yanıt: " . substr($cached, 0, 100) . "...\n";
            } else {
                echo "   ❌ Cache'te yok, API çağrısı yapılıyor...\n";
                
                // API çağrısı yap
                $response = $this->apiMaster->request('openai', '/chat', [
                    'prompt' => $query,
                    'max_tokens' => 100
                ]);
                
                // Cache'e kaydet
                $this->apiMaster->semanticCacheSet($query, $response['content'], 3600);
                echo "   ✅ Yanıt cache'e kaydedildi!\n";
                echo "   Yanıt: " . substr($response['content'], 0, 100) . "...\n";
            }
            echo "\n";
        }
    }
    
    // Vektör indeks istatistikleri
    public function showVectorStats() {
        echo "========================================\n";
        echo "Vektör İndeks İstatistikleri\n";
        echo "========================================\n\n";
        
        $stats = $this->vectorIndex->getStats();
        
        echo "İndeks Bilgileri:\n";
        echo "   Toplam vektör: " . number_format($stats['total_vectors']) . "\n";
        echo "   Vektör boyutu: " . $stats['dimensions'] . "\n";
        echo "   İndeks boyutu: " . $stats['index_size_mb'] . " MB\n";
        echo "   M parametresi: " . $stats['m_parameter'] . "\n";
        
        echo "\nPerformans:\n";
        echo "   Ortalama arama süresi: " . $stats['avg_search_ms'] . "ms\n";
        echo "   Recall oranı: " . $stats['recall_rate'] . "%\n";
        echo "   İndeks oluşturma süresi: " . $stats['build_time_sec'] . "sn\n";
        
        echo "\nKullanım:\n";
        echo "   Toplam sorgu: " . number_format($stats['total_queries']) . "\n";
        echo "   Ortalama sonuç: " . $stats['avg_results'] . "\n";
        echo "   Cache hit rate: " . $stats['cache_hit_rate'] . "%\n";
    }
    
    // Temizlik
    public function cleanup() {
        echo "\n========================================\n";
        echo "Temizlik Yapılıyor...\n";
        echo "========================================\n";
        
        $this->vectorIndex->optimize();
        $this->memoryConsolidation->cleanup();
        
        echo "✅ Temizlik tamamlandı!\n";
    }
}

// Örneği çalıştır
$example = new VectorMemoryExample();

// Vektör örnekleri
$example->addVectorsExample();
$example->searchExample();

// Memory örnekleri
$example->memoryConsolidationExample();

// Cache örneği
$example->semanticCacheExample();

// İstatistikler
$example->showVectorStats();

// Temizlik
$example->cleanup();

echo "\n========================================\n";
echo "Vektör ve Memory örnekleri tamamlandı!\n";
echo "========================================\n";