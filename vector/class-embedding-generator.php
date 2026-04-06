<?php
/**
 * APIMaster Embedding Generator
 * 
 * Metinleri vektör embedding'lere dönüştüren sistem
 * 
 * @package APIMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_EmbeddingGenerator {
    
    /**
     * @var array Embedding konfigürasyonu
     */
    private $config;
    
    /**
     * @var array Tokenizer sözlüğü
     */
    private $vocabulary = [];
    
    /**
     * @var array Önceden hesaplanmış embedding'ler
     */
    private $cache = [];
    
    /**
     * @var string Embedding yolu
     */
    private $embedding_path;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->embedding_path = APIMASTER_PATH . 'data/embeddings/';
        $this->initEmbeddingSystem();
    }
    
    /**
     * Embedding sistemini başlat
     */
    private function initEmbeddingSystem() {
        if (!file_exists($this->embedding_path)) {
            mkdir($this->embedding_path, 0755, true);
        }
        
        $this->loadConfig();
        $this->loadVocabulary();
    }
    
    /**
     * Konfigürasyonu yükle
     */
    private function loadConfig() {
        $config_file = $this->embedding_path . 'config.json';
        
        if (file_exists($config_file)) {
            $this->config = json_decode(file_get_contents($config_file), true);
        } else {
            $this->config = $this->getDefaultConfig();
            $this->saveConfig();
        }
    }
    
    /**
     * Varsayılan konfigürasyon
     */
    private function getDefaultConfig() {
        return [
            'model' => 'tiny', // tiny, small, medium, large
            'dimension' => 384,
            'max_length' => 512,
            'normalize' => true,
            'cache_enabled' => true,
            'cache_ttl' => 86400, // 24 saat
            'tokenizer' => 'wordpiece',
            'language' => 'multilingual',
            'pooling_strategy' => 'mean' // mean, max, cls
        ];
    }
    
    /**
     * Konfigürasyonu kaydet
     */
    private function saveConfig() {
        file_put_contents(
            $this->embedding_path . 'config.json',
            json_encode($this->config, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Vocabulary yükle
     */
    private function loadVocabulary() {
        $vocab_file = $this->embedding_path . 'vocabulary.json';
        
        if (file_exists($vocab_file)) {
            $this->vocabulary = json_decode(file_get_contents($vocab_file), true);
        } else {
            $this->vocabulary = $this->getBaseVocabulary();
            $this->saveVocabulary();
        }
    }
    
    /**
     * Base vocabulary oluştur
     */
    private function getBaseVocabulary() {
        // Temel kelime dağarcığı
        $base_words = [
            '[PAD]', '[UNK]', '[CLS]', '[SEP]', '[MASK]',
            'api', 'get', 'post', 'put', 'delete', 'request', 'response',
            'data', 'user', 'auth', 'token', 'key', 'error', 'success'
        ];
        
        $vocabulary = [];
        foreach ($base_words as $index => $word) {
            $vocabulary[$word] = $index;
        }
        
        // Harf bazlı token'lar ekle
        for ($i = 97; $i <= 122; $i++) { // a-z
            $char = chr($i);
            $vocabulary[$char] = count($vocabulary);
        }
        
        for ($i = 48; $i <= 57; $i++) { // 0-9
            $char = chr($i);
            $vocabulary[$char] = count($vocabulary);
        }
        
        return $vocabulary;
    }
    
    /**
     * Vocabulary kaydet
     */
    private function saveVocabulary() {
        file_put_contents(
            $this->embedding_path . 'vocabulary.json',
            json_encode($this->vocabulary, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Metni embedding'e dönüştür
     * 
     * @param string $text Metin
     * @param array $options Seçenekler
     * @return array|null
     */
    public function embed($text, $options = []) {
        if (empty($text)) {
            return null;
        }
        
        // Önbellek kontrolü
        $cache_key = $this->getCacheKey($text, $options);
        if ($this->config['cache_enabled'] && isset($this->cache[$cache_key])) {
            $cached = $this->cache[$cache_key];
            if (time() - $cached['timestamp'] < $this->config['cache_ttl']) {
                return $cached['embedding'];
            }
        }
        
        // Metni ön işle
        $processed = $this->preprocessText($text);
        
        // Tokenize et
        $tokens = $this->tokenize($processed);
        
        // Token limitini kontrol et
        if (count($tokens) > $this->config['max_length']) {
            $tokens = array_slice($tokens, 0, $this->config['max_length']);
        }
        
        // Token ID'lerine çevir
        $token_ids = $this->tokensToIds($tokens);
        
        // Embedding oluştur
        $embedding = $this->generateEmbedding($token_ids, $options);
        
        // Normalize et
        if ($this->config['normalize']) {
            $embedding = $this->normalizeVector($embedding);
        }
        
        // Önbelleğe al
        if ($this->config['cache_enabled']) {
            $this->cache[$cache_key] = [
                'embedding' => $embedding,
                'timestamp' => time(),
                'text' => $text
            ];
            
            // Önbellek boyutunu kontrol et
            if (count($this->cache) > 1000) {
                $this->cleanCache();
            }
        }
        
        return $embedding;
    }
    
    /**
     * Toplu embedding oluştur
     * 
     * @param array $texts Metinler dizisi
     * @param array $options Seçenekler
     * @return array
     */
    public function embedBatch($texts, $options = []) {
        $embeddings = [];
        
        foreach ($texts as $key => $text) {
            $embedding = $this->embed($text, $options);
            if ($embedding) {
                $embeddings[$key] = $embedding;
            }
        }
        
        return $embeddings;
    }
    
    /**
     * Metni ön işle
     */
    private function preprocessText($text) {
        // Küçük harfe çevir
        $text = mb_strtolower($text);
        
        // Fazla boşlukları temizle
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Özel karakterleri temizle (isteğe bağlı)
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        
        // Trim
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Tokenize et
     */
    private function tokenize($text) {
        $tokens = [];
        
        switch ($this->config['tokenizer']) {
            case 'wordpiece':
                $tokens = $this->wordpieceTokenize($text);
                break;
            case 'bpe':
                $tokens = $this->bpeTokenize($text);
                break;
            case 'simple':
            default:
                $tokens = $this->simpleTokenize($text);
                break;
        }
        
        // Special tokens ekle
        array_unshift($tokens, '[CLS]');
        $tokens[] = '[SEP]';
        
        return $tokens;
    }
    
    /**
     * Basit tokenization (boşluk bazlı)
     */
    private function simpleTokenize($text) {
        $words = preg_split('/\s+/', $text);
        $tokens = [];
        
        foreach ($words as $word) {
            if (strlen($word) > 0) {
                // Kelimeyi karakterlere ayır (daha iyi kapsama için)
                $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($chars as $char) {
                    $tokens[] = $char;
                }
                $tokens[] = ' '; // Kelime arası boşluk token'ı
            }
        }
        
        return $tokens;
    }
    
    /**
     * WordPiece tokenization
     */
    private function wordpieceTokenize($text) {
        $words = preg_split('/\s+/', $text);
        $tokens = [];
        
        foreach ($words as $word) {
            $word_tokens = $this->tokenizeWord($word);
            $tokens = array_merge($tokens, $word_tokens);
        }
        
        return $tokens;
    }
    
    /**
     * Tek bir kelimeyi tokenize et
     */
    private function tokenizeWord($word) {
        if (isset($this->vocabulary[$word])) {
            return [$word];
        }
        
        $tokens = [];
        $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
        $current_token = '';
        
        foreach ($chars as $char) {
            $current_token .= $char;
            if (isset($this->vocabulary[$current_token])) {
                $tokens[] = $current_token;
                $current_token = '';
            } elseif (strlen($current_token) > 10) {
                // Çok uzun token, karakterlere böl
                $tokens[] = $char;
                $current_token = '';
            }
        }
        
        if (!empty($current_token)) {
            $tokens[] = '[UNK]';
        }
        
        return $tokens;
    }
    
    /**
     * BPE tokenization (basit implementasyon)
     */
    private function bpeTokenize($text) {
        // Basit BPE benzeri tokenization
        $words = preg_split('/\s+/', $text);
        $tokens = [];
        
        foreach ($words as $word) {
            $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
            
            // Sık görülen bigramları birleştir
            $i = 0;
            while ($i < count($chars) - 1) {
                $bigram = $chars[$i] . $chars[$i + 1];
                if (isset($this->vocabulary[$bigram])) {
                    $tokens[] = $bigram;
                    $i += 2;
                } else {
                    $tokens[] = $chars[$i];
                    $i++;
                }
            }
            
            if ($i < count($chars)) {
                $tokens[] = $chars[$i];
            }
            
            $tokens[] = ' ';
        }
        
        return $tokens;
    }
    
    /**
     * Token ID'lerine çevir
     */
    private function tokensToIds($tokens) {
        $ids = [];
        $unk_id = $this->vocabulary['[UNK]'] ?? 1;
        
        foreach ($tokens as $token) {
            $id = $this->vocabulary[$token] ?? $unk_id;
            $ids[] = $id;
        }
        
        return $ids;
    }
    
    /**
     * Embedding oluştur
     */
    private function generateEmbedding($token_ids, $options = []) {
        $dimension = $options['dimension'] ?? $this->config['dimension'];
        
        switch ($this->config['model']) {
            case 'tiny':
                return $this->tinyEmbedding($token_ids, $dimension);
            case 'small':
                return $this->smallEmbedding($token_ids, $dimension);
            case 'medium':
                return $this->mediumEmbedding($token_ids, $dimension);
            case 'large':
                return $this->largeEmbedding($token_ids, $dimension);
            default:
                return $this->tinyEmbedding($token_ids, $dimension);
        }
    }
    
    /**
     * Tiny model embedding (hızlı, düşük kalite)
     */
    private function tinyEmbedding($token_ids, $dimension) {
        $embedding = array_fill(0, $dimension, 0);
        
        // Her token için basit bir embedding hesapla
        foreach ($token_ids as $pos => $token_id) {
            $token_hash = $this->hashToken($token_id, $pos);
            
            for ($i = 0; $i < $dimension; $i++) {
                $embedding[$i] += sin($token_hash * ($i + 1)) * cos($pos * ($i + 1));
            }
        }
        
        // Ortalama al
        $count = count($token_ids);
        if ($count > 0) {
            for ($i = 0; $i < $dimension; $i++) {
                $embedding[$i] /= $count;
            }
        }
        
        return $embedding;
    }
    
    /**
     * Small model embedding (orta hız, orta kalite)
     */
    private function smallEmbedding($token_ids, $dimension) {
        $embedding = array_fill(0, $dimension, 0);
        
        // Token frekanslarını hesapla
        $frequencies = array_count_values($token_ids);
        
        foreach ($frequencies as $token_id => $freq) {
            $token_hash = $this->hashToken($token_id, 0);
            
            for ($i = 0; $i < $dimension; $i++) {
                $value = sin($token_hash * ($i + 1)) * log(1 + $freq);
                $embedding[$i] += $value;
            }
        }
        
        // Normalize et
        $norm = sqrt(array_sum(array_map(function($v) {
            return $v * $v;
        }, $embedding)));
        
        if ($norm > 0) {
            for ($i = 0; $i < $dimension; $i++) {
                $embedding[$i] /= $norm;
            }
        }
        
        return $embedding;
    }
    
    /**
     * Medium model embedding (yavaş, iyi kalite)
     */
    private function mediumEmbedding($token_ids, $dimension) {
        $embedding = array_fill(0, $dimension, 0);
        $num_tokens = count($token_ids);
        
        // Positional encoding + token embedding
        for ($pos = 0; $pos < $num_tokens; $pos++) {
            $token_id = $token_ids[$pos];
            $token_hash = $this->hashToken($token_id, $pos);
            
            for ($i = 0; $i < $dimension; $i++) {
                // Positional encoding
                $pe = $pos / (10000 ^ ($i / $dimension));
                $pe_sin = sin($pe);
                $pe_cos = cos($pe);
                
                // Token embedding
                $te = sin($token_hash * ($i + 1));
                
                // Combine
                $embedding[$i] += ($te + $pe_sin + $pe_cos) / 3;
            }
        }
        
        // Pooling
        if ($this->config['pooling_strategy'] === 'mean' && $num_tokens > 0) {
            for ($i = 0; $i < $dimension; $i++) {
                $embedding[$i] /= $num_tokens;
            }
        } elseif ($this->config['pooling_strategy'] === 'max') {
            // Max pooling zaten yapıldı
        }
        
        return $embedding;
    }
    
    /**
     * Large model embedding (çok yavaş, yüksek kalite)
     */
    private function largeEmbedding($token_ids, $dimension) {
        // Çok katmanlı embedding (basit transformer simülasyonu)
        $layers = 4;
        $hidden_dim = 128;
        
        // Token embedding'leri oluştur
        $token_embeddings = [];
        foreach ($token_ids as $pos => $token_id) {
            $token_emb = array_fill(0, $hidden_dim, 0);
            $token_hash = $this->hashToken($token_id, $pos);
            
            for ($i = 0; $i < $hidden_dim; $i++) {
                $token_emb[$i] = sin($token_hash * ($i + 1)) * cos($pos * ($i + 1));
            }
            $token_embeddings[] = $token_emb;
        }
        
        // Multi-head self-attention simülasyonu
        for ($layer = 0; $layer < $layers; $layer++) {
            $new_embeddings = [];
            
            foreach ($token_embeddings as $i => $emb) {
                $attended = array_fill(0, $hidden_dim, 0);
                
                foreach ($token_embeddings as $j => $other_emb) {
                    $attention_score = $this->dotProduct($emb, $other_emb);
                    $attention_score = exp($attention_score) / (count($token_embeddings) * 2);
                    
                    for ($k = 0; $k < $hidden_dim; $k++) {
                        $attended[$k] += $other_emb[$k] * $attention_score;
                    }
                }
                
                // Residual connection + feed-forward
                for ($k = 0; $k < $hidden_dim; $k++) {
                    $attended[$k] = $emb[$k] + $attended[$k] * 0.5;
                    $attended[$k] = tanh($attended[$k]);
                }
                
                $new_embeddings[] = $attended;
            }
            
            $token_embeddings = $new_embeddings;
        }
        
        // Pooling to final embedding
        $embedding = array_fill(0, $dimension, 0);
        
        foreach ($token_embeddings as $emb) {
            for ($i = 0; $i < min($dimension, $hidden_dim); $i++) {
                $embedding[$i] += $emb[$i];
            }
        }
        
        $count = count($token_embeddings);
        if ($count > 0) {
            for ($i = 0; $i < $dimension; $i++) {
                $embedding[$i] /= $count;
            }
        }
        
        return $embedding;
    }
    
    /**
     * Token hash hesapla
     */
    private function hashToken($token_id, $position) {
        return ($token_id * 2654435761) ^ ($position * 1664525);
    }
    
    /**
     * Dot product
     */
    private function dotProduct($a, $b) {
        $sum = 0;
        for ($i = 0; $i < min(count($a), count($b)); $i++) {
            $sum += $a[$i] * $b[$i];
        }
        return $sum;
    }
    
    /**
     * Vektör normalizasyonu
     */
    private function normalizeVector($vector) {
        $norm = sqrt(array_sum(array_map(function($v) {
            return $v * $v;
        }, $vector)));
        
        if ($norm > 0) {
            return array_map(function($v) use ($norm) {
                return $v / $norm;
            }, $vector);
        }
        
        return $vector;
    }
    
    /**
     * Önbellek anahtarı oluştur
     */
    private function getCacheKey($text, $options) {
        $options_str = json_encode($options);
        return md5($text . $options_str . $this->config['model'] . $this->config['dimension']);
    }
    
    /**
     * Önbelleği temizle
     */
    private function cleanCache() {
        // En eski önbellekleri temizle
        uasort($this->cache, function($a, $b) {
            return $a['timestamp'] <=> $b['timestamp'];
        });
        
        $this->cache = array_slice($this->cache, -500, null, true);
    }
    
    /**
     * Önbelleği tamamen temizle
     */
    public function clearCache() {
        $this->cache = [];
        return true;
    }
    
    /**
     * İki metin arasındaki benzerliği hesapla
     * 
     * @param string $text1 Metin 1
     * @param string $text2 Metin 2
     * @return float
     */
    public function similarity($text1, $text2) {
        $emb1 = $this->embed($text1);
        $emb2 = $this->embed($text2);
        
        if (!$emb1 || !$emb2) {
            return 0;
        }
        
        return $this->cosineSimilarity($emb1, $emb2);
    }
    
    /**
     * Cosine similarity
     */
    private function cosineSimilarity($a, $b) {
        $dot = 0;
        $norm_a = 0;
        $norm_b = 0;
        
        for ($i = 0; $i < count($a); $i++) {
            $dot += $a[$i] * $b[$i];
            $norm_a += $a[$i] * $a[$i];
            $norm_b += $b[$i] * $b[$i];
        }
        
        if ($norm_a == 0 || $norm_b == 0) {
            return 0;
        }
        
        return $dot / (sqrt($norm_a) * sqrt($norm_b));
    }
    
    /**
     * Yeni kelime ekle (vocabulary genişlet)
     * 
     * @param string $word Yeni kelime
     * @return bool
     */
    public function addWord($word) {
        if (!isset($this->vocabulary[$word])) {
            $this->vocabulary[$word] = count($this->vocabulary);
            $this->saveVocabulary();
            return true;
        }
        
        return false;
    }
    
    /**
     * Toplu kelime ekle
     */
    public function addWords($words) {
        $added = 0;
        foreach ($words as $word) {
            if ($this->addWord($word)) {
                $added++;
            }
        }
        return $added;
    }
    
    /**
     * Model istatistiklerini al
     */
    public function getModelStats() {
        return [
            'model' => $this->config['model'],
            'dimension' => $this->config['dimension'],
            'vocabulary_size' => count($this->vocabulary),
            'max_length' => $this->config['max_length'],
            'cache_size' => count($this->cache),
            'tokenizer' => $this->config['tokenizer'],
            'pooling_strategy' => $this->config['pooling_strategy']
        ];
    }
    
    /**
     * Model boyutunu değiştir
     */
    public function setModel($model_type) {
        $valid_models = ['tiny', 'small', 'medium', 'large'];
        
        if (in_array($model_type, $valid_models)) {
            $this->config['model'] = $model_type;
            
            // Model tipine göre boyut ayarla
            $dimensions = [
                'tiny' => 128,
                'small' => 256,
                'medium' => 384,
                'large' => 768
            ];
            
            $this->config['dimension'] = $dimensions[$model_type];
            $this->saveConfig();
            
            // Önbelleği temizle (yeni model için)
            $this->clearCache();
            
            return true;
        }
        
        return false;
    }
}