<?php
/**
 * API Master Module - Bing Search API
 * Microsoft Bing Search API
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Bing implements APIMaster_APIInterface {
    
    /**
     * API endpoint'leri
     * @var array
     */
    private $endpoints = [
        'search' => 'https://api.bing.microsoft.com/v7.0/search',
        'images' => 'https://api.bing.microsoft.com/v7.0/images/search',
        'videos' => 'https://api.bing.microsoft.com/v7.0/videos/search',
        'news' => 'https://api.bing.microsoft.com/v7.0/news/search',
        'autosuggest' => 'https://api.bing.microsoft.com/v7.0/suggestions',
        'visual_search' => 'https://api.bing.microsoft.com/v7.0/images/visualsearch',
        'entity_search' => 'https://api.bing.microsoft.com/v7.0/entities'
    ];
    
    /**
     * API anahtarı
     * @var string
     */
    private $api_key;
    
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
            'api_key' => '',
            'timeout' => 30,
            'max_retries' => 3,
            'safe_search' => 'Moderate', // Off, Moderate, Strict
            'market' => 'tr-TR',
            'language' => 'tr',
            'count' => 10,
            'country_code' => 'TR'
        ], $config);
        
        $this->api_key = $this->config['api_key'];
    }
    
    /**
     * API isteği gönder
     * 
     * @param string $endpoint Endpoint tipi
     * @param string $query Sorgu
     * @param array $params Parametreler
     * @return array API yanıtı
     */
    public function request($endpoint, $query, $params = []) {
        $params = array_merge([
            'q' => $query,
            'mkt' => $this->config['market'],
            'count' => $this->config['count'],
            'safeSearch' => $this->config['safe_search']
        ], $params);
        
        $url = $this->endpoints[$endpoint] . '?' . http_build_query($params);
        
        $headers = [
            'Ocp-Apim-Subscription-Key: ' . $this->api_key,
            'Accept-Language: ' . $this->config['language']
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
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
        
        return $this->parseResponse($response, $httpCode, $endpoint);
    }
    
    /**
     * Yanıtı parse et
     * 
     * @param string $response Ham yanıt
     * @param int $httpCode HTTP kodu
     * @param string $endpoint Endpoint tipi
     * @return array Parse edilmiş yanıt
     */
    private function parseResponse($response, $httpCode, $endpoint) {
        $data = json_decode($response, true);
        
        if ($httpCode === 200 && $data !== null) {
            $parsed = ['success' => true];
            
            switch ($endpoint) {
                case 'search':
                    $parsed = array_merge($parsed, $this->parseWebSearch($data));
                    break;
                case 'images':
                    $parsed = array_merge($parsed, $this->parseImageSearch($data));
                    break;
                case 'videos':
                    $parsed = array_merge($parsed, $this->parseVideoSearch($data));
                    break;
                case 'news':
                    $parsed = array_merge($parsed, $this->parseNewsSearch($data));
                    break;
                case 'autosuggest':
                    $parsed = array_merge($parsed, $this->parseAutosuggest($data));
                    break;
                case 'entity_search':
                    $parsed = array_merge($parsed, $this->parseEntitySearch($data));
                    break;
                default:
                    $parsed['data'] = $data;
            }
            
            return $parsed;
        }
        
        $error_msg = $data['error']['message'] ?? 'Unknown error';
        
        return [
            'success' => false,
            'error' => $error_msg,
            'code' => $httpCode
        ];
    }
    
    /**
     * Web arama sonuçlarını parse et
     * 
     * @param array $data Ham veri
     * @return array Parse edilmiş veri
     */
    private function parseWebSearch($data) {
        $results = [];
        
        if (isset($data['webPages']['value'])) {
            foreach ($data['webPages']['value'] as $item) {
                $results[] = [
                    'title' => $item['name'],
                    'url' => $item['url'],
                    'display_url' => $item['displayUrl'],
                    'snippet' => $item['snippet'],
                    'date_last_crawled' => $item['dateLastCrawled'] ?? null
                ];
            }
        }
        
        $parsed = [
            'query' => $data['queryContext']['originalQuery'] ?? '',
            'total_results' => $data['webPages']['totalEstimatedMatches'] ?? 0,
            'results' => $results
        ];
        
        // Ranking bilgileri
        if (isset($data['rankingResponse'])) {
            $parsed['ranking'] = $data['rankingResponse'];
        }
        
        // İlgili aramalar
        if (isset($data['relatedSearches']['value'])) {
            $parsed['related_searches'] = [];
            foreach ($data['relatedSearches']['value'] as $item) {
                $parsed['related_searches'][] = [
                    'text' => $item['text'],
                    'display_text' => $item['displayText'],
                    'web_search_url' => $item['webSearchUrl']
                ];
            }
        }
        
        return $parsed;
    }
    
    /**
     * Görsel arama sonuçlarını parse et
     * 
     * @param array $data Ham veri
     * @return array Parse edilmiş veri
     */
    private function parseImageSearch($data) {
        $images = [];
        
        if (isset($data['value'])) {
            foreach ($data['value'] as $image) {
                $images[] = [
                    'name' => $image['name'],
                    'content_url' => $image['contentUrl'],
                    'thumbnail_url' => $image['thumbnailUrl'],
                    'host_page_url' => $image['hostPageUrl'],
                    'width' => $image['width'],
                    'height' => $image['height'],
                    'size' => $image['contentSize'] ?? null,
                    'encoding_format' => $image['encodingFormat'] ?? null
                ];
            }
        }
        
        return [
            'query' => $data['queryContext']['originalQuery'] ?? '',
            'total_results' => $data['totalEstimatedMatches'] ?? 0,
            'images' => $images,
            'next_offset' => $data['nextOffset'] ?? null
        ];
    }
    
    /**
     * Video arama sonuçlarını parse et
     * 
     * @param array $data Ham veri
     * @return array Parse edilmiş veri
     */
    private function parseVideoSearch($data) {
        $videos = [];
        
        if (isset($data['value'])) {
            foreach ($data['value'] as $video) {
                $videos[] = [
                    'name' => $video['name'],
                    'content_url' => $video['contentUrl'],
                    'thumbnail_url' => $video['thumbnailUrl'],
                    'host_page_url' => $video['hostPageUrl'],
                    'duration' => $video['duration'] ?? null,
                    'view_count' => $video['viewCount'] ?? null,
                    'publisher' => $video['publisher'][0]['name'] ?? null,
                    'date_published' => $video['datePublished'] ?? null
                ];
            }
        }
        
        return [
            'query' => $data['queryContext']['originalQuery'] ?? '',
            'total_results' => $data['totalEstimatedMatches'] ?? 0,
            'videos' => $videos
        ];
    }
    
    /**
     * Haber arama sonuçlarını parse et
     * 
     * @param array $data Ham veri
     * @return array Parse edilmiş veri
     */
    private function parseNewsSearch($data) {
        $news = [];
        
        if (isset($data['value'])) {
            foreach ($data['value'] as $item) {
                $news[] = [
                    'title' => $item['name'],
                    'url' => $item['url'],
                    'description' => $item['description'],
                    'provider' => $item['provider'][0]['name'] ?? null,
                    'date_published' => $item['datePublished'] ?? null,
                    'category' => $item['category'] ?? null,
                    'image' => $item['image']['thumbnail']['contentUrl'] ?? null
                ];
            }
        }
        
        return [
            'query' => $data['queryContext']['originalQuery'] ?? '',
            'total_results' => $data['totalEstimatedMatches'] ?? 0,
            'news' => $news
        ];
    }
    
    /**
     * Otomatik tamamlama sonuçlarını parse et
     * 
     * @param array $data Ham veri
     * @return array Parse edilmiş veri
     */
    private function parseAutosuggest($data) {
        $suggestions = [];
        
        if (isset($data['suggestionGroups'][0]['searchSuggestions'])) {
            foreach ($data['suggestionGroups'][0]['searchSuggestions'] as $item) {
                $suggestions[] = [
                    'text' => $item['displayText'],
                    'query' => $item['query'],
                    'url' => $item['url']
                ];
            }
        }
        
        return [
            'query' => $data['queryContext']['originalQuery'] ?? '',
            'suggestions' => $suggestions
        ];
    }
    
    /**
     * Entity arama sonuçlarını parse et
     * 
     * @param array $data Ham veri
     * @return array Parse edilmiş veri
     */
    private function parseEntitySearch($data) {
        $entities = [];
        
        if (isset($data['entities']['value'])) {
            foreach ($data['entities']['value'] as $entity) {
                $entities[] = [
                    'name' => $entity['name'],
                    'description' => $entity['description'],
                    'url' => $entity['url'],
                    'image' => $entity['image']['thumbnailUrl'] ?? null,
                    'type' => $entity['entityPresentationInfo']['entityTypeDisplayHint'] ?? null,
                    'contractual_rules' => $entity['contractualRules'] ?? []
                ];
            }
        }
        
        return [
            'query' => $data['queryContext']['originalQuery'] ?? '',
            'entities' => $entities
        ];
    }
    
    /**
     * Web arama yap
     * 
     * @param string $query Arama sorgusu
     * @param array $options Seçenekler
     * @return array Arama sonuçları
     */
    public function search($query, $options = []) {
        $options = array_merge([
            'count' => $this->config['count'],
            'offset' => 0,
            'freshness' => null,
            'answer_count' => 1
        ], $options);
        
        $params = [
            'count' => $options['count'],
            'offset' => $options['offset']
        ];
        
        if ($options['freshness']) {
            $params['freshness'] = $options['freshness'];
        }
        
        if ($options['answer_count']) {
            $params['answerCount'] = $options['answer_count'];
        }
        
        return $this->request('search', $query, $params);
    }
    
    /**
     * Görsel arama yap
     * 
     * @param string $query Arama sorgusu
     * @param array $options Seçenekler
     * @return array Görsel sonuçları
     */
    public function imageSearch($query, $options = []) {
        $options = array_merge([
            'count' => $this->config['count'],
            'offset' => 0,
            'aspect' => null,
            'color' => null,
            'size' => null,
            'license' => null
        ], $options);
        
        $params = [
            'count' => $options['count'],
            'offset' => $options['offset']
        ];
        
        if ($options['aspect']) $params['aspect'] = $options['aspect'];
        if ($options['color']) $params['color'] = $options['color'];
        if ($options['size']) $params['size'] = $options['size'];
        if ($options['license']) $params['license'] = $options['license'];
        
        return $this->request('images', $query, $params);
    }
    
    /**
     * Video arama yap
     * 
     * @param string $query Arama sorgusu
     * @param array $options Seçenekler
     * @return array Video sonuçları
     */
    public function videoSearch($query, $options = []) {
        $options = array_merge([
            'count' => $this->config['count'],
            'offset' => 0,
            'pricing' => null,
            'resolution' => null,
            'video_length' => null
        ], $options);
        
        $params = [
            'count' => $options['count'],
            'offset' => $options['offset']
        ];
        
        if ($options['pricing']) $params['pricing'] = $options['pricing'];
        if ($options['resolution']) $params['resolution'] = $options['resolution'];
        if ($options['video_length']) $params['videoLength'] = $options['video_length'];
        
        return $this->request('videos', $query, $params);
    }
    
    /**
     * Haber arama yap
     * 
     * @param string $query Arama sorgusu
     * @param array $options Seçenekler
     * @return array Haber sonuçları
     */
    public function newsSearch($query, $options = []) {
        $options = array_merge([
            'count' => $this->config['count'],
            'offset' => 0,
            'category' => null,
            'freshness' => null
        ], $options);
        
        $params = [
            'count' => $options['count'],
            'offset' => $options['offset']
        ];
        
        if ($options['category']) $params['category'] = $options['category'];
        if ($options['freshness']) $params['freshness'] = $options['freshness'];
        
        return $this->request('news', $query, $params);
    }
    
    /**
     * Otomatik tamamlama önerileri getir
     * 
     * @param string $query Arama sorgusu
     * @return array Öneriler
     */
    public function autosuggest($query) {
        return $this->request('autosuggest', $query);
    }
    
    /**
     * Entity arama yap
     * 
     * @param string $query Arama sorgusu
     * @return array Entity sonuçları
     */
    public function entitySearch($query) {
        return $this->request('entity_search', $query);
    }
    
    /**
     * Görsel ile arama yap
     * 
     * @param string $image_path Görsel yolu veya base64
     * @param array $options Seçenekler
     * @return array Arama sonuçları
     */
    public function visualSearch($image_path, $options = []) {
        $options = array_merge([
            'count' => 10,
            'market' => $this->config['market']
        ], $options);
        
        $image_content = $this->getImageContent($image_path);
        
        $url = $this->endpoints['visual_search'] . '?' . http_build_query([
            'count' => $options['count'],
            'mkt' => $options['market']
        ]);
        
        $boundary = uniqid('', true);
        $body = $this->addMultipartField('image', $image_content, $boundary, 'image.jpg', 'image/jpeg');
        $body .= "--{$boundary}--\r\n";
        
        $headers = [
            'Ocp-Apim-Subscription-Key: ' . $this->api_key,
            'Content-Type: multipart/form-data; boundary=' . $boundary
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $this->parseResponse($response, $httpCode, 'images');
    }
    
    /**
     * Multipart form field ekle
     * 
     * @param string $name Field adı
     * @param string $content Dosya içeriği
     * @param string $boundary Boundary
     * @param string $filename Dosya adı
     * @param string $content_type Content type
     * @return string Field content
     */
    private function addMultipartField($name, $content, $boundary, $filename = '', $content_type = 'application/octet-stream') {
        $field = "--{$boundary}\r\n";
        $field .= "Content-Disposition: form-data; name=\"{$name}\"";
        
        if ($filename) {
            $field .= "; filename=\"{$filename}\"";
        }
        
        $field .= "\r\n";
        $field .= "Content-Type: {$content_type}\r\n\r\n";
        $field .= $content . "\r\n";
        
        return $field;
    }
    
    /**
     * Görsel içeriğini al
     * 
     * @param string $image_path Görsel yolu veya base64
     * @return string Görsel içeriği
     */
    private function getImageContent($image_path) {
        if (file_exists($image_path)) {
            return file_get_contents($image_path);
        }
        
        if (preg_match('/^data:image\/\w+;base64,/', $image_path)) {
            return base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $image_path));
        }
        
        return $image_path;
    }
    
    /**
     * Trend aramaları getir
     * 
     * @param array $options Seçenekler
     * @return array Trend aramalar
     */
    public function getTrending($options = []) {
        $options = array_merge([
            'category' => 'All'
        ], $options);
        
        $url = "https://api.bing.microsoft.com/v7.0/news/trendingtopics?mkt={$this->config['market']}";
        
        if ($options['category'] !== 'All') {
            $url .= "&category={$options['category']}";
        }
        
        $headers = [
            'Ocp-Apim-Subscription-Key: ' . $this->api_key
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if ($httpCode === 200 && isset($data['value'])) {
            $trending = [];
            foreach ($data['value'] as $item) {
                $trending[] = [
                    'title' => $item['name'],
                    'query' => $item['query']['text'],
                    'web_search_url' => $item['webSearchUrl'],
                    'image' => $item['image']['url'] ?? null,
                    'news_count' => $item['newsCount'] ?? null
                ];
            }
            
            return [
                'success' => true,
                'trending' => $trending
            ];
        }
        
        return [
            'success' => false,
            'error' => $data['error']['message'] ?? 'Unknown error'
        ];
    }
    
    /**
     * Pazar (market) değiştir
     * 
     * @param string $market Pazar kodu
     * @return bool
     */
    public function setMarket($market) {
        $this->config['market'] = $market;
        return true;
    }
    
    /**
     * Dil değiştir
     * 
     * @param string $lang Dil kodu
     * @return bool
     */
    public function setLanguage($lang) {
        $this->config['language'] = $lang;
        return true;
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
            'web_search' => 'Web Search',
            'image_search' => 'Image Search',
            'video_search' => 'Video Search',
            'news_search' => 'News Search',
            'visual_search' => 'Visual Search',
            'entity_search' => 'Entity Search',
            'autosuggest' => 'Auto Suggest'
        ];
    }
    
    /**
     * APIInterface: getCapabilities metodu
     */
    public function getCapabilities() {
        return [
            'search',
            'image_search',
            'video_search',
            'news_search',
            'visual_search',
            'entity_search',
            'autosuggest'
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
        $this->api_key = $api_key;
        $this->config['api_key'] = $api_key;
    }
    
    /**
     * APIInterface: setModel metodu
     */
    public function setModel($model) {
        // Bing için model ayarı yok
        return true;
    }
    
    /**
     * APIInterface: getModel metodu
     */
    public function getModel() {
        return 'bing_search_v7';
    }
    
    /**
     * APIInterface: chat metodu
     */
    public function chat($messages, $options = []) {
        // Son mesajı al
        $last_message = end($messages);
        $prompt = $last_message['content'] ?? '';
        return $this->search($prompt, $options);
    }
}