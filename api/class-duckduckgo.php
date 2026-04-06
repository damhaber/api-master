<?php
/**
 * API Master Module - DuckDuckGo API
 * DuckDuckGo Instant Answer API
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_DuckDuckGo implements APIMaster_APIInterface {
    
    /**
     * API endpoint
     * @var string
     */
    private $base_url = 'https://api.duckduckgo.com/';
    
    /**
     * Yapılandırma
     * @var array
     */
    private $config = [];
    
    /**
     * Constructor
     * 
     * @param array $config Yapılandırma ayarları
     */
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'user_agent' => 'APIMaster DuckDuckGo Module/1.0',
            'timeout' => 30,
            'max_retries' => 3,
            'safe_search' => 1, // -1: off, 1: moderate, 2: strict
            'format' => 'json'
        ], $config);
    }
    
    /**
     * API isteği gönder
     * 
     * @param string $query Sorgu
     * @param array $params Parametreler
     * @return array API yanıtı
     */
    public function request($query, $params = []) {
        $params = array_merge([
            'q' => $query,
            'format' => $this->config['format'],
            'no_html' => 1,
            'skip_disambig' => 1,
            't' => $this->config['user_agent']
        ], $params);
        
        if ($this->config['safe_search'] !== -1) {
            $params['kp'] = $this->config['safe_search'];
        }
        
        $url = $this->base_url . '?' . http_build_query($params);
        
        $headers = [
            'User-Agent: ' . $this->config['user_agent']
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => $error,
                'code' => $httpCode
            ];
        }
        
        return $this->parseResponse($response, $httpCode);
    }
    
    /**
     * Yanıtı parse et
     * 
     * @param string $response Ham yanıt
     * @param int $httpCode HTTP kodu
     * @return array Parse edilmiş yanıt
     */
    private function parseResponse($response, $httpCode) {
        $data = json_decode($response, true);
        
        if ($httpCode === 200 && $data !== null) {
            $parsed = [
                'success' => true,
                'query' => $data['Heading'] ?? '',
                'abstract' => $data['Abstract'] ?? '',
                'abstract_text' => $data['AbstractText'] ?? '',
                'abstract_url' => $data['AbstractURL'] ?? '',
                'answer' => $data['Answer'] ?? '',
                'answer_type' => $data['AnswerType'] ?? '',
                'definition' => $data['Definition'] ?? '',
                'definition_source' => $data['DefinitionSource'] ?? '',
                'image' => $data['Image'] ?? '',
                'infobox' => $this->parseInfobox($data),
                'related_topics' => $this->parseRelatedTopics($data),
                'results' => $this->parseResults($data),
                'type' => $data['Type'] ?? '',
                'redirect' => $data['Redirect'] ?? ''
            ];
            
            return $parsed;
        }
        
        return [
            'success' => false,
            'error' => 'Invalid response',
            'code' => $httpCode
        ];
    }
    
    /**
     * Infobox verilerini parse et
     * 
     * @param array $data Ham veri
     * @return array Infobox
     */
    private function parseInfobox($data) {
        $infobox = [];
        
        if (isset($data['Infobox'])) {
            foreach ($data['Infobox']['content'] ?? [] as $item) {
                if (isset($item['label']) && isset($item['value'])) {
                    $infobox[$item['label']] = strip_tags($item['value']);
                }
            }
        }
        
        return $infobox;
    }
    
    /**
     * İlgili konuları parse et
     * 
     * @param array $data Ham veri
     * @return array İlgili konular
     */
    private function parseRelatedTopics($data) {
        $topics = [];
        
        if (isset($data['RelatedTopics'])) {
            foreach ($data['RelatedTopics'] as $topic) {
                if (isset($topic['Text'])) {
                    $topics[] = [
                        'text' => $topic['Text'],
                        'url' => $topic['FirstURL'] ?? '',
                        'icon' => $topic['Icon']['URL'] ?? ''
                    ];
                } elseif (isset($topic['Topics'])) {
                    foreach ($topic['Topics'] as $subtopic) {
                        $topics[] = [
                            'text' => $subtopic['Text'],
                            'url' => $subtopic['FirstURL'] ?? '',
                            'icon' => $subtopic['Icon']['URL'] ?? ''
                        ];
                    }
                }
            }
        }
        
        return $topics;
    }
    
    /**
     * Arama sonuçlarını parse et
     * 
     * @param array $data Ham veri
     * @return array Arama sonuçları
     */
    private function parseResults($data) {
        $results = [];
        
        if (isset($data['Results'])) {
            foreach ($data['Results'] as $result) {
                $results[] = [
                    'title' => $result['Text'] ?? '',
                    'url' => $result['FirstURL'] ?? '',
                    'icon' => $result['Icon']['URL'] ?? ''
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * DuckDuckGo'da arama yap
     * 
     * @param string $query Arama sorgusu
     * @param array $options Seçenekler
     * @return array Arama sonuçları
     */
    public function search($query, $options = []) {
        $options = array_merge([
            'no_html' => 1,
            'skip_disambig' => 1,
            'safe_search' => $this->config['safe_search']
        ], $options);
        
        $params = [
            'no_html' => $options['no_html'],
            'skip_disambig' => $options['skip_disambig']
        ];
        
        if ($options['safe_search'] !== -1) {
            $params['kp'] = $options['safe_search'];
        }
        
        $result = $this->request($query, $params);
        
        // Bang komutlarını kontrol et
        if (preg_match('/^!(\w+)/', $query, $matches)) {
            $bang = $matches[1];
            $result['bang'] = $bang;
            $result['bang_redirect'] = $this->getBangRedirect($bang, $query);
        }
        
        return $result;
    }
    
    /**
     * Anlık cevap getir
     * 
     * @param string $query Sorgu
     * @return array Anlık cevap
     */
    public function getInstantAnswer($query) {
        $result = $this->search($query);
        
        if ($result['success']) {
            $answer = [
                'query' => $result['query'],
                'answer' => $result['answer'],
                'abstract' => $result['abstract_text'],
                'definition' => $result['definition'],
                'type' => $result['answer_type']
            ];
            
            // Hesaplama sonuçlarını kontrol et
            if ($result['answer_type'] === 'calc') {
                $answer['calculation'] = $result['answer'];
            }
            
            // Hava durumu
            if (strpos($query, 'weather') !== false || strpos($query, 'hava durumu') !== false) {
                $weather = $this->getWeather($query);
                if ($weather) {
                    $answer['weather'] = $weather;
                }
            }
            
            // Tanım
            if ($result['definition']) {
                $answer['definition'] = [
                    'text' => $result['definition'],
                    'source' => $result['definition_source']
                ];
            }
            
            return $answer;
        }
        
        return $result;
    }
    
    /**
     * Hava durumu bilgisi getir
     * 
     * @param string $query Sorgu
     * @return array|null Hava durumu
     */
    private function getWeather($query) {
        $result = $this->search($query . ' weather');
        
        if ($result['success'] && $result['answer']) {
            preg_match('/(\d+)[°C]?\s*,\s*(.+)/', $result['answer'], $matches);
            
            if ($matches) {
                return [
                    'temperature' => $matches[1],
                    'condition' => $matches[2],
                    'full_text' => $result['answer']
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Bang komutunu işle
     * 
     * @param string $bang Bang komutu
     * @param string $query Tam sorgu
     * @return string|null Redirect URL
     */
    private function getBangRedirect($bang, $query) {
        $bang_urls = [
            'g' => 'https://www.google.com/search?q=',
            'yt' => 'https://www.youtube.com/results?search_query=',
            'w' => 'https://en.wikipedia.org/wiki/Special:Search/',
            'trw' => 'https://tr.wikipedia.org/wiki/Özel:Ara/',
            'gh' => 'https://github.com/search?q=',
            'so' => 'https://stackoverflow.com/search?q=',
            'am' => 'https://www.amazon.com/s?k=',
            'tr' => 'https://translate.google.com/?sl=auto&tl=tr&text=',
            'imdb' => 'https://www.imdb.com/find?q=',
            'reddit' => 'https://www.reddit.com/search/?q=',
            'twitter' => 'https://twitter.com/search?q=',
            'fb' => 'https://www.facebook.com/search/top?q=',
            'li' => 'https://www.linkedin.com/search/results/all/?keywords=',
            'maps' => 'https://www.google.com/maps/search/',
            'news' => 'https://news.google.com/search?q=',
            'ytm' => 'https://music.youtube.com/search?q=',
            'spotify' => 'https://open.spotify.com/search/',
            'netflix' => 'https://www.netflix.com/search?q=',
            'aliexpress' => 'https://www.aliexpress.com/wholesale?SearchText=',
            'ebay' => 'https://www.ebay.com/sch/i.html?_nkw=',
            'trendyol' => 'https://www.trendyol.com/search?q=',
            'hepsiburada' => 'https://www.hepsiburada.com/ara?q=',
            'n11' => 'https://www.n11.com/arama?q=',
            'dict' => 'https://dictionary.cambridge.org/search/english/direct/?q=',
            'tdk' => 'https://sozluk.gov.tr/?q='
        ];
        
        $search_query = preg_replace('/^!\w+\s*/', '', $query);
        $encoded_query = urlencode($search_query);
        
        if (isset($bang_urls[$bang])) {
            return $bang_urls[$bang] . $encoded_query;
        }
        
        return null;
    }
    
    /**
     * Sorgu tipini belirle
     * 
     * @param string $query Sorgu
     * @return string Sorgu tipi
     */
    public function detectQueryType($query) {
        $query_lower = strtolower($query);
        
        // Hesaplama
        if (preg_match('/[0-9+\-*/%^()]+/', $query_lower)) {
            return 'calculation';
        }
        
        // Hava durumu
        if (strpos($query_lower, 'weather') !== false || strpos($query_lower, 'hava') !== false) {
            return 'weather';
        }
        
        // Tanım
        if (strpos($query_lower, 'define') === 0 || strpos($query_lower, 'definition') === 0) {
            return 'definition';
        }
        
        // Çeviri
        if (strpos($query_lower, 'translate') !== false) {
            return 'translation';
        }
        
        // Konum
        if (strpos($query_lower, 'where is') !== false) {
            return 'location';
        }
        
        // Zaman
        if (strpos($query_lower, 'time in') !== false || strpos($query_lower, 'saat') !== false) {
            return 'time';
        }
        
        // Para birimi
        if (preg_match('/\d+\s*(usd|eur|gbp|try|tl)\s+to\s+(usd|eur|gbp|try|tl)/i', $query_lower)) {
            return 'currency';
        }
        
        return 'general';
    }
    
    /**
     * Görsel arama
     * 
     * @param string $query Arama sorgusu
     * @param array $options Seçenekler
     * @return array Görsel sonuçları
     */
    public function imageSearch($query, $options = []) {
        $options = array_merge([
            'limit' => 10,
            'safe_search' => $this->config['safe_search']
        ], $options);
        
        $result = $this->search($query . ' images');
        
        $images = [];
        if ($result['success'] && !empty($result['related_topics'])) {
            foreach ($result['related_topics'] as $topic) {
                if (!empty($topic['icon']) && count($images) < $options['limit']) {
                    $images[] = [
                        'title' => $topic['text'],
                        'url' => $topic['url'],
                        'image' => $topic['icon']
                    ];
                }
            }
        }
        
        return [
            'success' => true,
            'query' => $query,
            'images' => $images,
            'total' => count($images)
        ];
    }
    
    /**
     * Haber arama
     * 
     * @param string $query Arama sorgusu
     * @return array Haber sonuçları
     */
    public function newsSearch($query) {
        return $this->search($query . ' news');
    }
    
    /**
     * Video arama
     * 
     * @param string $query Arama sorgusu
     * @return array Video sonuçları
     */
    public function videoSearch($query) {
        return $this->search($query . ' videos');
    }
    
    /**
     * Harita arama
     * 
     * @param string $query Arama sorgusu (konum)
     * @return array Harita sonuçları
     */
    public function mapSearch($query) {
        $result = $this->search($query . ' map');
        
        if ($result['success'] && $result['infobox']) {
            $map_data = [];
            
            if (isset($result['infobox']['Coordinates'])) {
                $map_data['coordinates'] = $result['infobox']['Coordinates'];
            }
            
            if (isset($result['infobox']['Location'])) {
                $map_data['location'] = $result['infobox']['Location'];
            }
            
            if ($result['image']) {
                $map_data['image'] = $result['image'];
            }
            
            return [
                'success' => true,
                'query' => $query,
                'map_data' => $map_data,
                'abstract' => $result['abstract_text']
            ];
        }
        
        return $result;
    }
    
    /**
     * Bang komutlarının listesini getir
     * 
     * @return array Bang komutları
     */
    public function getBangCommands() {
        return [
            '!g' => 'Google Search',
            '!yt' => 'YouTube',
            '!w' => 'Wikipedia (English)',
            '!trw' => 'Wikipedia (Turkish)',
            '!gh' => 'GitHub',
            '!so' => 'Stack Overflow',
            '!am' => 'Amazon',
            '!tr' => 'Google Translate',
            '!imdb' => 'IMDb',
            '!reddit' => 'Reddit',
            '!twitter' => 'Twitter/X',
            '!maps' => 'Google Maps',
            '!news' => 'Google News',
            '!spotify' => 'Spotify',
            '!trendyol' => 'Trendyol',
            '!hepsiburada' => 'Hepsiburada',
            '!n11' => 'N11',
            '!tdk' => 'TDK Sözlük'
        ];
    }
    
    /**
     * APIInterface: complete metodu
     */
    public function complete($prompt, $options = []) {
        return $this->search($prompt, $options);
    }
    
    /**
     * APIInterface: stream metodu
     */
    public function stream($prompt, $callback, $options = []) {
        $result = $this->search($prompt, $options);
        if (is_callable($callback)) {
            $callback($result);
        }
        return $result;
    }
    
    /**
     * APIInterface: getModels metodu
     */
    public function getModels() {
        return [
            'instant_answer' => 'Instant Answer API',
            'search' => 'Web Search',
            'image_search' => 'Image Search',
            'news_search' => 'News Search',
            'video_search' => 'Video Search',
            'map_search' => 'Map Search'
        ];
    }
    
    /**
     * APIInterface: getCapabilities metodu
     */
    public function getCapabilities() {
        return [
            'search',
            'instant_answer',
            'image_search',
            'news_search',
            'video_search',
            'map_search',
            'query_detection'
        ];
    }
    
    /**
     * APIInterface: checkHealth metodu
     */
    public function checkHealth() {
        $result = $this->search('test');
        return $result['success'] ?? false;
    }
    
    /**
     * APIInterface: setApiKey metodu
     */
    public function setApiKey($api_key) {
        // DuckDuckGo API key gerektirmez
        return true;
    }
    
    /**
     * APIInterface: setModel metodu
     */
    public function setModel($model) {
        // DuckDuckGo için model ayarı yok
        return true;
    }
    
    /**
     * APIInterface: getModel metodu
     */
    public function getModel() {
        return 'duckduckgo_instant_answer_v1';
    }
    
    /**
     * APIInterface: chat metodu
     */
    public function chat($messages, $options = []) {
        $last_message = end($messages);
        $prompt = $last_message['content'] ?? '';
        return $this->search($prompt, $options);
    }
}