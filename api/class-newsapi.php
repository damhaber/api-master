<?php
/**
 * API Master Module - NewsAPI
 * NewsAPI.org - News search and headlines
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_NewsAPI implements APIMaster_APIInterface {
    
    /**
     * API base URL
     */
    private $api_url = 'https://newsapi.org/v2/';
    
    /**
     * API key
     */
    private $api_key;
    
    /**
     * Current model (search type)
     */
    private $model = 'everything';
    
    /**
     * Request timeout
     */
    private $timeout = 30;
    
    /**
     * Default language
     */
    private $language = 'tr';
    
    /**
     * Default country
     */
    private $country = 'tr';
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct(array $config = []) {
        $this->api_key = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'everything';
        $this->timeout = $config['timeout'] ?? 30;
        $this->language = $config['language'] ?? 'tr';
        $this->country = $config['country'] ?? 'tr';
    }
    
    /**
     * Set API key
     * 
     * @param string $api_key
     * @return self
     */
    public function setApiKey($api_key) {
        $this->api_key = $api_key;
        return $this;
    }
    
    /**
     * Set model (search type)
     * 
     * @param string $model
     * @return self
     */
    public function setModel($model) {
        $this->model = $model;
        return $this;
    }
    
    /**
     * Get current model
     * 
     * @return string
     */
    public function getModel() {
        return $this->model;
    }
    
    /**
     * Complete a prompt (search news)
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
     * Stream completion (not supported)
     * Required by APIMaster_APIInterface
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
     * Get available models
     * Required by APIMaster_APIInterface
     * 
     * @return array
     */
    public function getModels() {
        return [
            'everything' => [
                'name' => 'Everything Search',
                'description' => 'Search all news articles',
                'type' => 'search'
            ],
            'top-headlines' => [
                'name' => 'Top Headlines',
                'description' => 'Get top headlines by country or category',
                'type' => 'headlines'
            ],
            'sources' => [
                'name' => 'News Sources',
                'description' => 'Get list of news sources',
                'type' => 'metadata'
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
            'headlines' => true,
            'sources' => true,
            'categories' => true,
            'streaming' => false,
            'requires_api_key' => true,
            'max_results' => 100
        ];
    }
    
    /**
     * Check API health
     * Required by APIMaster_APIInterface
     * 
     * @return bool
     */
    public function checkHealth() {
        if (empty($this->api_key)) {
            return false;
        }
        
        $result = $this->getTopHeadlines(['page_size' => 1]);
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
        // Extract last user message as search query
        $last_message = end($messages);
        if (isset($last_message['content']) && $last_message['role'] === 'user') {
            return $this->search($last_message['content'], $options);
        }
        return false;
    }
    
    /**
     * Make API request
     * 
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @return array|false
     */
    private function make_request($endpoint, $params = []) {
        if (empty($this->api_key)) {
            return false;
        }
        
        $params['apiKey'] = $this->api_key;
        $url = $this->api_url . $endpoint . '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error || $http_code !== 200) {
            return false;
        }
        
        $data = json_decode($response, true);
        
        if ($data === null || !isset($data['status']) || $data['status'] !== 'ok') {
            return false;
        }
        
        return $data;
    }
    
    /**
     * Search news
     * 
     * @param string $query Search query
     * @param array $options Search options
     * @return array|false
     */
    public function search($query, $options = []) {
        $page_size = min($options['page_size'] ?? 20, 100);
        $page = $options['page'] ?? 1;
        $sort_by = $options['sort_by'] ?? 'relevancy'; // relevancy, popularity, publishedAt
        
        $params = [
            'q' => $query,
            'pageSize' => $page_size,
            'page' => $page,
            'language' => $options['language'] ?? $this->language,
            'sortBy' => $sort_by
        ];
        
        if (!empty($options['from'])) {
            $params['from'] = $options['from'];
        }
        
        if (!empty($options['to'])) {
            $params['to'] = $options['to'];
        }
        
        if (!empty($options['domains'])) {
            $params['domains'] = is_array($options['domains']) 
                ? implode(',', $options['domains']) 
                : $options['domains'];
        }
        
        if (!empty($options['exclude_domains'])) {
            $params['excludeDomains'] = is_array($options['exclude_domains']) 
                ? implode(',', $options['exclude_domains']) 
                : $options['exclude_domains'];
        }
        
        if (!empty($options['sources'])) {
            $params['sources'] = is_array($options['sources']) 
                ? implode(',', $options['sources']) 
                : $options['sources'];
        }
        
        $data = $this->make_request('everything', $params);
        
        if ($data === false) {
            return false;
        }
        
        return [
            'success' => true,
            'query' => $query,
            'total_results' => $data['totalResults'] ?? 0,
            'articles' => $this->parseArticles($data['articles'] ?? [])
        ];
    }
    
    /**
     * Get top headlines
     * 
     * @param array $options Headline options
     * @return array|false
     */
    public function getTopHeadlines($options = []) {
        $page_size = min($options['page_size'] ?? 20, 100);
        $page = $options['page'] ?? 1;
        
        $params = [
            'pageSize' => $page_size,
            'page' => $page
        ];
        
        if (!empty($options['country'])) {
            $params['country'] = $options['country'];
        } elseif (!empty($this->country)) {
            $params['country'] = $this->country;
        }
        
        if (!empty($options['category'])) {
            $params['category'] = $options['category'];
        }
        
        if (!empty($options['sources'])) {
            $params['sources'] = is_array($options['sources']) 
                ? implode(',', $options['sources']) 
                : $options['sources'];
        }
        
        if (!empty($options['q'])) {
            $params['q'] = $options['q'];
        }
        
        $data = $this->make_request('top-headlines', $params);
        
        if ($data === false) {
            return false;
        }
        
        return [
            'success' => true,
            'total_results' => $data['totalResults'] ?? 0,
            'articles' => $this->parseArticles($data['articles'] ?? [])
        ];
    }
    
    /**
     * Get news sources
     * 
     * @param array $options Source options
     * @return array|false
     */
    public function getSources($options = []) {
        $params = [];
        
        if (!empty($options['category'])) {
            $params['category'] = $options['category'];
        }
        
        if (!empty($options['language'])) {
            $params['language'] = $options['language'];
        }
        
        if (!empty($options['country'])) {
            $params['country'] = $options['country'];
        }
        
        $data = $this->make_request('sources', $params);
        
        if ($data === false) {
            return false;
        }
        
        $sources = [];
        foreach ($data['sources'] ?? [] as $source) {
            $sources[] = [
                'id' => $source['id'],
                'name' => $source['name'],
                'description' => $source['description'],
                'url' => $source['url'],
                'category' => $source['category'],
                'language' => $source['language'],
                'country' => $source['country']
            ];
        }
        
        return [
            'success' => true,
            'total_sources' => count($sources),
            'sources' => $sources
        ];
    }
    
    /**
     * Get news by category
     * 
     * @param string $category Category name
     * @param array $options Additional options
     * @return array|false
     */
    public function getNewsByCategory($category, $options = []) {
        $valid_categories = ['business', 'entertainment', 'general', 'health', 'science', 'sports', 'technology'];
        
        if (!in_array($category, $valid_categories)) {
            return false;
        }
        
        $options['category'] = $category;
        return $this->getTopHeadlines($options);
    }
    
    /**
     * Get news by country
     * 
     * @param string $country Country code
     * @param array $options Additional options
     * @return array|false
     */
    public function getNewsByCountry($country, $options = []) {
        $options['country'] = strtolower($country);
        return $this->getTopHeadlines($options);
    }
    
    /**
     * Parse articles from API response
     * 
     * @param array $articles Raw articles
     * @return array
     */
    private function parseArticles($articles) {
        $parsed = [];
        
        foreach ($articles as $article) {
            $parsed[] = [
                'source' => [
                    'id' => $article['source']['id'] ?? null,
                    'name' => $article['source']['name'] ?? null
                ],
                'author' => $article['author'] ?? null,
                'title' => $article['title'] ?? '',
                'description' => $article['description'] ?? null,
                'content' => $article['content'] ?? null,
                'url' => $article['url'] ?? '',
                'image_url' => $article['urlToImage'] ?? null,
                'published_at' => $article['publishedAt'] ?? null
            ];
        }
        
        return $parsed;
    }
    
    /**
     * Get available categories
     * 
     * @return array
     */
    public function getCategories() {
        return [
            'business' => 'Business',
            'entertainment' => 'Entertainment',
            'general' => 'General',
            'health' => 'Health',
            'science' => 'Science',
            'sports' => 'Sports',
            'technology' => 'Technology'
        ];
    }
    
    /**
     * Get available countries
     * 
     * @return array
     */
    public function getCountries() {
        return [
            'ae' => 'United Arab Emirates',
            'ar' => 'Argentina',
            'at' => 'Austria',
            'au' => 'Australia',
            'be' => 'Belgium',
            'bg' => 'Bulgaria',
            'br' => 'Brazil',
            'ca' => 'Canada',
            'ch' => 'Switzerland',
            'cn' => 'China',
            'co' => 'Colombia',
            'cz' => 'Czech Republic',
            'de' => 'Germany',
            'eg' => 'Egypt',
            'fr' => 'France',
            'gb' => 'United Kingdom',
            'gr' => 'Greece',
            'hk' => 'Hong Kong',
            'hu' => 'Hungary',
            'id' => 'Indonesia',
            'ie' => 'Ireland',
            'il' => 'Israel',
            'in' => 'India',
            'it' => 'Italy',
            'jp' => 'Japan',
            'kr' => 'South Korea',
            'lt' => 'Lithuania',
            'lv' => 'Latvia',
            'ma' => 'Morocco',
            'mx' => 'Mexico',
            'my' => 'Malaysia',
            'ng' => 'Nigeria',
            'nl' => 'Netherlands',
            'no' => 'Norway',
            'nz' => 'New Zealand',
            'ph' => 'Philippines',
            'pl' => 'Poland',
            'pt' => 'Portugal',
            'ro' => 'Romania',
            'rs' => 'Serbia',
            'ru' => 'Russia',
            'sa' => 'Saudi Arabia',
            'se' => 'Sweden',
            'sg' => 'Singapore',
            'si' => 'Slovenia',
            'sk' => 'Slovakia',
            'th' => 'Thailand',
            'tr' => 'Turkey',
            'tw' => 'Taiwan',
            'ua' => 'Ukraine',
            'us' => 'United States',
            've' => 'Venezuela',
            'za' => 'South Africa'
        ];
    }
    
    /**
     * Set language
     * 
     * @param string $language Language code
     * @return self
     */
    public function setLanguage($language) {
        $this->language = $language;
        return $this;
    }
    
    /**
     * Set country
     * 
     * @param string $country Country code
     * @return self
     */
    public function setCountry($country) {
        $this->country = strtolower($country);
        return $this;
    }
    
    /**
     * Extract text from response
     * 
     * @param array $response API response
     * @return string
     */
    public function extractText($response) {
        if (!isset($response['articles'])) {
            return '';
        }
        
        $texts = [];
        foreach ($response['articles'] as $article) {
            $texts[] = $article['title'] . "\n" . ($article['description'] ?? '') . "\n" . $article['url'];
        }
        
        return implode("\n\n", $texts);
    }
}