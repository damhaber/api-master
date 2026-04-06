<?php
/**
 * API Master Module - Wikipedia API
 * Wikipedia articles, search, and page content
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Wikipedia implements APIMaster_APIInterface {
    
    /**
     * API base URL
     */
    private $api_url = 'https://{lang}.wikipedia.org/api/rest_v1';
    
    /**
     * MediaWiki API URL
     */
    private $mw_api_url = 'https://{lang}.wikipedia.org/w/api.php';
    
    /**
     * Language code
     */
    private $lang = 'tr';
    
    /**
     * User agent
     */
    private $user_agent = 'APIMaster Wikipedia Module/1.0';
    
    /**
     * Request timeout
     */
    private $timeout = 30;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct(array $config = []) {
        $this->lang = $config['lang'] ?? 'tr';
        $this->timeout = $config['timeout'] ?? 30;
        
        if (!empty($config['user_agent'])) {
            $this->user_agent = $config['user_agent'];
        }
    }
    
    /**
     * Set API key (not used for Wikipedia, but required by interface)
     * 
     * @param string $api_key
     * @return self
     */
    public function setApiKey($api_key) {
        // Wikipedia is free, no API key needed
        return $this;
    }
    
    /**
     * Set model (not used, but required by interface)
     * 
     * @param string $model
     * @return self
     */
    public function setModel($model) {
        return $this;
    }
    
    /**
     * Get current model
     * 
     * @return string
     */
    public function getModel() {
        return 'wikipedia';
    }
    
    /**
     * Complete a prompt (search Wikipedia)
     * Required by APIMaster_APIInterface
     * 
     * @param string $prompt Search query
     * @param array $options Additional options
     * @return array|false Response or false on error
     */
    public function complete($prompt, $options = []) {
        return $this->search($prompt, $options);
    }
    
    /**
     * Stream completion (not supported, but required by interface)
     * 
     * @param string $prompt User prompt
     * @param callable $callback Callback function
     * @param array $options Additional options
     * @return bool
     */
    public function stream($prompt, $callback, $options = []) {
        $result = $this->search($prompt, $options);
        if (is_callable($callback)) {
            call_user_func($callback, ['complete' => true, 'result' => $result]);
        }
        return $result !== false;
    }
    
    /**
     * Get available models (Wikipedia projects)
     * Required by APIMaster_APIInterface
     * 
     * @return array
     */
    public function getModels() {
        return [
            'wikipedia' => [
                'name' => 'Wikipedia',
                'description' => 'Free online encyclopedia',
                'type' => 'knowledge_base'
            ],
            'wiktionary' => [
                'name' => 'Wiktionary',
                'description' => 'Free dictionary',
                'type' => 'dictionary'
            ],
            'wikiquote' => [
                'name' => 'Wikiquote',
                'description' => 'Collection of quotations',
                'type' => 'quotes'
            ]
        ];
    }
    
    /**
     * Get API capabilities
     * Required by APIMaster_APIInterface
     * 
     * @return array
     */
    public function getCapabilities() {
        return [
            'search' => true,
            'page_content' => true,
            'random_article' => true,
            'summary' => true,
            'geo_search' => true,
            'streaming' => false,
            'requires_api_key' => false
        ];
    }
    
    /**
     * Check API health
     * Required by APIMaster_APIInterface
     * 
     * @return bool
     */
    public function checkHealth() {
        $result = $this->search('test', ['limit' => 1]);
        return $result !== false;
    }
    
    /**
     * Chat method (not supported)
     * Required by APIMaster_APIInterface
     * 
     * @param array $messages Chat messages
     * @param array $options Additional options
     * @param callable|null $callback Optional callback
     * @return array|bool
     */
    public function chat($messages, $options = [], $callback = null) {
        // Not supported - use search instead
        return false;
    }
    
    /**
     * Build URL with language
     * 
     * @param string $url URL template
     * @return string
     */
    private function buildUrl($url) {
        return str_replace('{lang}', $this->lang, $url);
    }
    
    /**
     * Make API request
     * 
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @param bool $use_rest Use REST API (true) or MediaWiki API (false)
     * @return array|false
     */
    private function make_request($endpoint, $params = [], $use_rest = true) {
        $base = $use_rest ? $this->api_url : $this->mw_api_url;
        $url = $this->buildUrl($base) . $endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $headers = [
            'User-Agent: ' . $this->user_agent,
            'Accept: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error || $http_code !== 200) {
            return false;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Search Wikipedia
     * 
     * @param string $query Search query
     * @param array $options Search options
     * @return array|false
     */
    public function search($query, $options = []) {
        $limit = $options['limit'] ?? 10;
        $offset = $options['offset'] ?? 0;
        
        $params = [
            'action' => 'query',
            'list' => 'search',
            'srsearch' => $query,
            'srlimit' => $limit,
            'sroffset' => $offset,
            'format' => 'json'
        ];
        
        $data = $this->make_request('', $params, false);
        
        if ($data === false || !isset($data['query']['search'])) {
            return false;
        }
        
        $results = [];
        foreach ($data['query']['search'] as $item) {
            $results[] = [
                'page_id' => $item['pageid'],
                'title' => $item['title'],
                'snippet' => strip_tags($item['snippet']),
                'timestamp' => $item['timestamp']
            ];
        }
        
        return [
            'success' => true,
            'query' => $query,
            'total_hits' => $data['query']['searchinfo']['totalhits'] ?? count($results),
            'results' => $results
        ];
    }
    
    /**
     * Get page content by title
     * 
     * @param string $title Page title
     * @param array $options Page options
     * @return array|false
     */
    public function getPage($title, $options = []) {
        $format = $options['format'] ?? 'html'; // html, text, json
        
        $encoded_title = urlencode(str_replace(' ', '_', $title));
        
        if ($format === 'summary') {
            return $this->getSummary($title);
        }
        
        $params = [
            'action' => 'parse',
            'page' => $title,
            'format' => 'json',
            'redirects' => true
        ];
        
        if ($format === 'text') {
            $params['prop'] = 'text';
            $params['formatversion'] = 2;
        } elseif ($format === 'json') {
            $params['prop'] = 'text|displaytitle|sections|categories|links';
        }
        
        $data = $this->make_request('', $params, false);
        
        if ($data === false || !isset($data['parse'])) {
            return false;
        }
        
        $parse = $data['parse'];
        $result = [
            'success' => true,
            'title' => $parse['title'],
            'page_id' => $parse['pageid']
        ];
        
        if ($format === 'html' && isset($parse['text']['*'])) {
            $result['html'] = $parse['text']['*'];
        } elseif ($format === 'text' && isset($parse['text'])) {
            $result['text'] = strip_tags($parse['text']);
        } elseif ($format === 'json') {
            $result['html'] = $parse['text']['*'] ?? '';
            $result['display_title'] = $parse['displaytitle'] ?? $parse['title'];
            $result['sections'] = $parse['sections'] ?? [];
            $result['categories'] = $parse['categories'] ?? [];
        }
        
        return $result;
    }
    
    /**
     * Get page summary (REST API)
     * 
     * @param string $title Page title
     * @return array|false
     */
    public function getSummary($title) {
        $encoded_title = urlencode(str_replace(' ', '_', $title));
        $data = $this->make_request('/page/summary/' . $encoded_title);
        
        if ($data === false) {
            return false;
        }
        
        return [
            'success' => true,
            'title' => $data['title'],
            'display_title' => $data['displaytitle'] ?? $data['title'],
            'extract' => $data['extract'],
            'extract_html' => $data['extract_html'] ?? null,
            'description' => $data['description'] ?? null,
            'page_id' => $data['pageid'],
            'thumbnail' => $data['thumbnail']['source'] ?? null,
            'url' => $data['content_urls']['desktop']['page'] ?? null
        ];
    }
    
    /**
     * Get random article
     * 
     * @param array $options Random options
     * @return array|false
     */
    public function getRandom($options = []) {
        $count = $options['count'] ?? 1;
        
        if ($count === 1) {
            $data = $this->make_request('/page/random/summary');
            
            if ($data === false) {
                return false;
            }
            
            return [
                'success' => true,
                'article' => [
                    'title' => $data['title'],
                    'extract' => $data['extract'],
                    'page_id' => $data['pageid'],
                    'thumbnail' => $data['thumbnail']['source'] ?? null
                ]
            ];
        }
        
        // Multiple random articles via MediaWiki API
        $params = [
            'action' => 'query',
            'list' => 'random',
            'rnlimit' => $count,
            'rnnamespace' => 0,
            'format' => 'json'
        ];
        
        $data = $this->make_request('', $params, false);
        
        if ($data === false || !isset($data['query']['random'])) {
            return false;
        }
        
        $articles = [];
        foreach ($data['query']['random'] as $item) {
            $articles[] = [
                'page_id' => $item['id'],
                'title' => $item['title']
            ];
        }
        
        return [
            'success' => true,
            'articles' => $articles
        ];
    }
    
    /**
     * Search by geographic location
     * 
     * @param float $lat Latitude
     * @param float $lon Longitude
     * @param array $options Search options
     * @return array|false
     */
    public function geoSearch($lat, $lon, $options = []) {
        $radius = $options['radius'] ?? 1000; // meters
        $limit = $options['limit'] ?? 10;
        
        $params = [
            'action' => 'query',
            'list' => 'geosearch',
            'gscoord' => $lat . '|' . $lon,
            'gsradius' => $radius,
            'gslimit' => $limit,
            'format' => 'json'
        ];
        
        $data = $this->make_request('', $params, false);
        
        if ($data === false || !isset($data['query']['geosearch'])) {
            return false;
        }
        
        $results = [];
        foreach ($data['query']['geosearch'] as $item) {
            $results[] = [
                'page_id' => $item['pageid'],
                'title' => $item['title'],
                'lat' => $item['lat'],
                'lon' => $item['lon'],
                'distance' => $item['dist']
            ];
        }
        
        return [
            'success' => true,
            'center' => ['lat' => $lat, 'lon' => $lon],
            'radius' => $radius,
            'results' => $results
        ];
    }
    
    /**
     * Set language
     * 
     * @param string $lang Language code (tr, en, de, fr, etc.)
     * @return self
     */
    public function setLanguage($lang) {
        $this->lang = $lang;
        return $this;
    }
    
    /**
     * Get current language
     * 
     * @return string
     */
    public function getLanguage() {
        return $this->lang;
    }
    
    /**
     * Extract text from page response
     * 
     * @param array $response API response
     * @return string
     */
    public function extractText($response) {
        if (isset($response['extract'])) {
            return $response['extract'];
        }
        
        if (isset($response['text'])) {
            return $response['text'];
        }
        
        if (isset($response['html'])) {
            return strip_tags($response['html']);
        }
        
        if (isset($response['results']) && !empty($response['results'])) {
            $texts = [];
            foreach ($response['results'] as $result) {
                $texts[] = $result['title'] . ': ' . ($result['snippet'] ?? '');
            }
            return implode("\n\n", $texts);
        }
        
        return '';
    }
}