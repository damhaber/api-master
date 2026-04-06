<?php
/**
 * Google Search API Sınıfı
 * 
 * Google Custom Search JSON API
 * - Web arama
 * - Görsel arama
 * - Haber arama
 * - Video arama
 * - Siteye özel arama
 * 
 * @package APIMaster
 * @subpackage API
 * @since 1.0.0
 */

namespace APIMaster\API;

use APIMaster\Core\Logger;
use APIMaster\Core\Cache;
use APIMaster\Core\Validator;

class GoogleSearch implements APIInterface {
    
    /**
     * API endpoint
     * @var string
     */
    private $base_url = 'https://www.googleapis.com/customsearch/v1';
    
    /**
     * API anahtarı
     * @var string
     */
    private $api_key;
    
    /**
     * Search engine ID
     * @var string
     */
    private $cx;
    
    /**
     * Logger instance
     * @var Logger
     */
    private $logger;
    
    /**
     * Cache instance
     * @var Cache
     */
    private $cache;
    
    /**
     * Validator instance
     * @var Validator
     */
    private $validator;
    
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
            'cx' => '',
            'cache_ttl' => 3600,
            'timeout' => 30,
            'max_retries' => 3,
            'enable_cache' => true,
            'enable_logging' => true,
            'safe_search' => 'off', // off, medium, high
            'num_results' => 10,
            'country' => 'tr',
            'language' => 'tr'
        ], $config);
        
        $this->api_key = $this->config['api_key'];
        $this->cx = $this->config['cx'];
        $this->logger = new Logger('google-search');
        $this->cache = new Cache('google-search');
        $this->validator = new Validator();
    }
    
    /**
     * API isteği gönder
     * 
     * @param string $query Sorgu
     * @param array $params Parametreler
     * @return array API yanıtı
     */
    public function request($query, $params = []) {
        $cache_key = md5($query . json_encode($params));
        
        if ($this->config['enable_cache']) {
            $cached = $this->cache->get($cache_key);
            if ($cached !== false) {
                $this->logger->info('Google Search cache hit', ['query' => $query]);
                return $cached;
            }
        }
        
        $params = array_merge([
            'key' => $this->api_key,
            'cx' => $this->cx,
            'q' => $query,
            'num' => $this->config['num_results'],
            'safe' => $this->config['safe_search'],
            'gl' => $this->config['country'],
            'hl' => $this->config['language']
        ], $params);
        
        $url = $this->base_url . '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->logger->error('Google Search request failed', ['error' => $error]);
            return [
                'success' => false,
                'error' => $error,
                'code' => $httpCode
            ];
        }
        
        $result = $this->parseResponse($response, $httpCode);
        
        if ($this->config['enable_cache'] && $result['success']) {
            $this->cache->set($cache_key, $result, $this->config['cache_ttl']);
        }
        
        return $result;
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
            $results = [];
            
            if (isset($data['items'])) {
                foreach ($data['items'] as $item) {
                    $results[] = [
                        'title' => $item['title'],
                        'link' => $item['link'],
                        'snippet' => $item['snippet'],
                        'display_link' => $item['displayLink'] ?? '',
                        'formatted_url' => $item['formattedUrl'] ?? '',
                        'pagemap' => $this->parsePageMap($item['pagemap'] ?? [])
                    ];
                }
            }
            
            $parsed = [
                'success' => true,
                'query' => $data['queries']['request'][0]['searchTerms'] ?? '',
                'total_results' => $data['searchInformation']['totalResults'] ?? 0,
                'time' => $data['searchInformation']['searchTime'] ?? 0,
                'results' => $results,
                'spelling' => $data['spelling']['correctedQuery'] ?? null,
                'context' => $data['context'] ?? null
            ];
            
            // Pagination bilgileri
            if (isset($data['queries']['nextPage'])) {
                $parsed['next_page'] = $data['queries']['nextPage'][0]['startIndex'] ?? null;
            }
            
            if (isset($data['queries']['previousPage'])) {
                $parsed['previous_page'] = $data['queries']['previousPage'][0]['startIndex'] ?? null;
            }
            
            return $parsed;
        }
        
        $error_msg = $data['error']['message'] ?? 'Unknown error';
        $this->logger->error('Google Search API error', [
            'http_code' => $httpCode,
            'error' => $error_msg
        ]);
        
        return [
            'success' => false,
            'error' => $error_msg,
            'code' => $httpCode
        ];
    }
    
    /**
     * PageMap verilerini parse et
     * 
     * @param array $pagemap PageMap
     * @return array Parsed PageMap
     */
    private function parsePageMap($pagemap) {
        $parsed = [];
        
        // Metatag'ler
        if (isset($pagemap['metatags'][0])) {
            $parsed['metatags'] = $pagemap['metatags'][0];
        }
        
        // Görseller
        if (isset($pagemap['cse_image'])) {
            $parsed['images'] = [];
            foreach ($pagemap['cse_image'] as $image) {
                if (isset($image['src'])) {
                    $parsed['images'][] = $image['src'];
                }
            }
        }
        
        // Thumbnail
        if (isset($pagemap['cse_thumbnail'][0]['src'])) {
            $parsed['thumbnail'] = $pagemap['cse_thumbnail'][0]['src'];
        }
        
        // Video
        if (isset($pagemap['videoobject'])) {
            $parsed['videos'] = $pagemap['videoobject'];
        }
        
        // Article
        if (isset($pagemap['article'])) {
            $parsed['article'] = $pagemap['article'][0];
        }
        
        return $parsed;
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
            'num' => $this->config['num_results'],
            'start' => 1,
            'safe' => $this->config['safe_search'],
            'filter' => 1,
            'siteSearch' => null,
            'siteSearchFilter' => 'i',
            'sort' => null
        ], $options);
        
        $params = [
            'num' => min($options['num'], 10),
            'start' => $options['start'],
            'safe' => $options['safe'],
            'filter' => $options['filter']
        ];
        
        if ($options['siteSearch']) {
            $params['siteSearch'] = $options['siteSearch'];
            $params['siteSearchFilter'] = $options['siteSearchFilter'];
        }
        
        if ($options['sort']) {
            $params['sort'] = $options['sort'];
        }
        
        return $this->request($query, $params);
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
            'searchType' => 'image',
            'num' => 10,
            'imgSize' => null, // medium, large, xlarge
            'imgType' => null, // photo, clipart, lineart, face
            'imgColorType' => null, // color, gray, mono
            'imgDominantColor' => null // black, blue, brown, green, etc.
        ], $options);
        
        $params = [
            'searchType' => 'image',
            'num' => min($options['num'], 10)
        ];
        
        if ($options['imgSize']) {
            $params['imgSize'] = $options['imgSize'];
        }
        
        if ($options['imgType']) {
            $params['imgType'] = $options['imgType'];
        }
        
        if ($options['imgColorType']) {
            $params['imgColorType'] = $options['imgColorType'];
        }
        
        if ($options['imgDominantColor']) {
            $params['imgDominantColor'] = $options['imgDominantColor'];
        }
        
        $result = $this->request($query, $params);
        
        if ($result['success'] && !empty($result['results'])) {
            $images = [];
            foreach ($result['results'] as $item) {
                if (isset($item['pagemap']['images'])) {
                    $image_data = $item['pagemap']['images'][0] ?? [];
                    $images[] = [
                        'title' => $item['title'],
                        'link' => $item['link'],
                        'snippet' => $item['snippet'],
                        'image_url' => $image_data['src'] ?? '',
                        'thumbnail' => $item['pagemap']['thumbnail'] ?? '',
                        'width' => $image_data['width'] ?? null,
                        'height' => $image_data['height'] ?? null
                    ];
                }
            }
            $result['images'] = $images;
        }
        
        return $result;
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
            'videoType' => null, // any, movie, tvseries, episode, trailer
            'videoDuration' => null, // short, medium, long
            'videoLicense' => null // any, creative_commons
        ], $options);
        
        $params = [
            'searchType' => 'video'
        ];
        
        if ($options['videoType']) {
            $params['videoType'] = $options['videoType'];
        }
        
        if ($options['videoDuration']) {
            $params['videoDuration'] = $options['videoDuration'];
        }
        
        if ($options['videoLicense']) {
            $params['videoLicense'] = $options['videoLicense'];
        }
        
        $result = $this->request($query, $params);
        
        if ($result['success'] && !empty($result['results'])) {
            $videos = [];
            foreach ($result['results'] as $item) {
                if (isset($item['pagemap']['videoobject'])) {
                    $video = $item['pagemap']['videoobject'][0];
                    $videos[] = [
                        'title' => $item['title'],
                        'link' => $item['link'],
                        'snippet' => $item['snippet'],
                        'duration' => $video['duration'] ?? null,
                        'upload_date' => $video['uploadDate'] ?? null,
                        'thumbnail' => $video['thumbnailUrl'] ?? null,
                        'channel' => $video['author'] ?? null
                    ];
                }
            }
            $result['videos'] = $videos;
        }
        
        return $result;
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
            'dateRestrict' => null, // d[number], w[number], m[number], y[number]
            'sort' => 'date'
        ], $options);
        
        $params = [
            'sort' => $options['sort']
        ];
        
        if ($options['dateRestrict']) {
            $params['dateRestrict'] = $options['dateRestrict'];
        }
        
        $result = $this->request($query, $params);
        
        if ($result['success'] && !empty($result['results'])) {
            $news = [];
            foreach ($result['results'] as $item) {
                if (isset($item['pagemap']['article'])) {
                    $article = $item['pagemap']['article'][0];
                    $news[] = [
                        'title' => $item['title'],
                        'link' => $item['link'],
                        'snippet' => $item['snippet'],
                        'source' => $article['publisher'] ?? $item['displayLink'],
                        'date' => $article['datePublished'] ?? null,
                        'author' => $article['author'] ?? null
                    ];
                }
            }
            $result['news'] = $news;
        }
        
        return $result;
    }
    
    /**
     * Belirli bir sitede ara
     * 
     * @param string $site Site domaini
     * @param string $query Arama sorgusu
     * @param array $options Seçenekler
     * @return array Arama sonuçları
     */
    public function siteSearch($site, $query, $options = []) {
        $options['siteSearch'] = $site;
        return $this->search($query, $options);
    }
    
    /**
     * Dosya tipine göre ara
     * 
     * @param string $query Arama sorgusu
     * @param string $filetype Dosya tipi (pdf, doc, xls, ppt, etc.)
     * @param array $options Seçenekler
     * @return array Arama sonuçları
     */
    public function filetypeSearch($query, $filetype, $options = []) {
        $query = $query . ' filetype:' . $filetype;
        return $this->search($query, $options);
    }
    
    /**
     * Tarih aralığına göre ara
     * 
     * @param string $query Arama sorgusu
     * @param string $dateRestrict Tarih kısıtlaması (d1, w1, m1, y1, etc.)
     * @param array $options Seçenekler
     * @return array Arama sonuçları
     */
    public function dateRangeSearch($query, $dateRestrict, $options = []) {
        $options['dateRestrict'] = $dateRestrict;
        $options['sort'] = 'date';
        return $this->search($query, $options);
    }
    
    /**
     * Sıralama seçenekleriyle ara
     * 
     * @param string $query Arama sorgusu
     * @param string $sort Sıralama (date, rating, relevance)
     * @param array $options Seçenekler
     * @return array Arama sonuçları
     */
    public function sortedSearch($query, $sort, $options = []) {
        $options['sort'] = $sort;
        return $this->search($query, $options);
    }
    
    /**
     * İlgili aramaları getir
     * 
     * @param string $query Arama sorgusu
     * @return array İlgili aramalar
     */
    public function getRelatedQueries($query) {
        $result = $this->search($query, ['num' => 1]);
        
        if ($result['success'] && isset($result['context']['facets'])) {
            $related = [];
            foreach ($result['context']['facets'] as $facet) {
                foreach ($facet as $item) {
                    if (isset($item['anchor'])) {
                        $related[] = $item['anchor'];
                    }
                }
            }
            return $related;
        }
        
        return [];
    }
    
    /**
     * Arama istatistiklerini getir
     * 
     * @param string $query Arama sorgusu
     * @return array İstatistikler
     */
    public function getSearchStats($query) {
        $result = $this->search($query, ['num' => 1]);
        
        if ($result['success']) {
            return [
                'total_results' => $result['total_results'],
                'time' => $result['time'],
                'query' => $result['query'],
                'spelling' => $result['spelling']
            ];
        }
        
        return $result;
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
     * Ülke değiştir
     * 
     * @param string $country Ülke kodu
     * @return bool
     */
    public function setCountry($country) {
        $this->config['country'] = $country;
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
        $callback($result);
        return $result;
    }
    
    /**
     * APIInterface: getModels metodu
     */
    public function getAvailableModels() {
        return [
            'web_search' => 'Web Search',
            'image_search' => 'Image Search',
            'video_search' => 'Video Search',
            'news_search' => 'News Search',
            'site_search' => 'Site-specific Search'
        ];
    }
}