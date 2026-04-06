<?php
/**
 * API Master Module - Twitter API
 * Twitter/X API v2 - Search tweets, user info, timeline
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Twitter implements APIMaster_APIInterface {
    
    /**
     * API base URL (v2)
     */
    private $api_url = 'https://api.twitter.com/2/';
    
    /**
     * API v1.1 URL (for trends)
     */
    private $api_v1_url = 'https://api.twitter.com/1.1/';
    
    /**
     * Bearer token
     */
    private $bearer_token;
    
    /**
     * API key (OAuth 1.0a)
     */
    private $api_key;
    
    /**
     * API secret (OAuth 1.0a)
     */
    private $api_secret;
    
    /**
     * Access token (OAuth 1.0a)
     */
    private $access_token;
    
    /**
     * Access token secret (OAuth 1.0a)
     */
    private $access_token_secret;
    
    /**
     * Current model
     */
    private $model = 'search';
    
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
        $this->bearer_token = $config['bearer_token'] ?? $config['api_key'] ?? '';
        $this->api_key = $config['api_key'] ?? '';
        $this->api_secret = $config['api_secret'] ?? '';
        $this->access_token = $config['access_token'] ?? '';
        $this->access_token_secret = $config['access_token_secret'] ?? '';
        $this->model = $config['model'] ?? 'search';
        $this->timeout = $config['timeout'] ?? 30;
    }
    
    /**
     * Set API key (bearer token)
     * 
     * @param string $api_key
     * @return self
     */
    public function setApiKey($api_key) {
        $this->bearer_token = $api_key;
        return $this;
    }
    
    /**
     * Set OAuth credentials
     * 
     * @param string $api_key
     * @param string $api_secret
     * @param string $access_token
     * @param string $access_token_secret
     * @return self
     */
    public function setOAuthCredentials($api_key, $api_secret, $access_token, $access_token_secret) {
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
        $this->access_token = $access_token;
        $this->access_token_secret = $access_token_secret;
        return $this;
    }
    
    /**
     * Set model
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
     * Complete a prompt (search tweets)
     * Required by APIMaster_APIInterface
     * 
     * @param string $prompt Search query
     * @param array $options Additional options
     * @return array|false Response or false on error
     */
    public function complete($prompt, $options = []) {
        return $this->searchTweets($prompt, $options);
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
        $result = $this->searchTweets($prompt, $options);
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
            'search' => [
                'name' => 'Search Tweets',
                'description' => 'Search recent tweets',
                'type' => 'search'
            ],
            'user' => [
                'name' => 'User Info',
                'description' => 'Get user information',
                'type' => 'user'
            ],
            'timeline' => [
                'name' => 'User Timeline',
                'description' => 'Get user timeline',
                'type' => 'timeline'
            ],
            'trends' => [
                'name' => 'Trends',
                'description' => 'Get trending topics',
                'type' => 'trends'
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
            'search_tweets' => true,
            'user_info' => true,
            'user_timeline' => true,
            'trends' => true,
            'streaming' => false,
            'requires_auth' => true,
            'rate_limited' => true
        ];
    }
    
    /**
     * Check API health
     * Required by APIMaster_APIInterface
     * 
     * @return bool
     */
    public function checkHealth() {
        if (empty($this->bearer_token)) {
            return false;
        }
        
        $result = $this->searchTweets('test', ['max_results' => 1]);
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
        $last_message = end($messages);
        if (isset($last_message['content']) && $last_message['role'] === 'user') {
            return $this->searchTweets($last_message['content'], $options);
        }
        return false;
    }
    
    /**
     * Make API request with Bearer token
     * 
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @return array|false
     */
    private function make_request($endpoint, $params = []) {
        if (empty($this->bearer_token)) {
            return false;
        }
        
        $url = $this->api_url . ltrim($endpoint, '/');
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->bearer_token
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error || $http_code !== 200) {
            return false;
        }
        
        $data = json_decode($response, true);
        
        if ($data === null || isset($data['errors'])) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * Make request to v1.1 API (for trends)
     * 
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @return array|false
     */
    private function make_v1_request($endpoint, $params = []) {
        if (empty($this->api_key) || empty($this->api_secret) || 
            empty($this->access_token) || empty($this->access_token_secret)) {
            return false;
        }
        
        $url = $this->api_v1_url . ltrim($endpoint, '/');
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $auth_header = $this->getOAuthHeader('GET', $url, $params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [$auth_header]);
        
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
     * Generate OAuth 1.0a header
     * 
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array $params Query parameters
     * @return string Authorization header
     */
    private function getOAuthHeader($method, $url, $params = []) {
        $oauth_params = [
            'oauth_consumer_key' => $this->api_key,
            'oauth_nonce' => md5(uniqid(mt_rand(), true)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_token' => $this->access_token,
            'oauth_version' => '1.0'
        ];
        
        $all_params = array_merge($oauth_params, $params);
        uksort($all_params, 'strcmp');
        
        $param_string = '';
        foreach ($all_params as $key => $value) {
            if ($param_string) $param_string .= '&';
            $param_string .= rawurlencode($key) . '=' . rawurlencode($value);
        }
        
        $base_string = $method . '&' . rawurlencode($url) . '&' . rawurlencode($param_string);
        $signing_key = rawurlencode($this->api_secret) . '&' . rawurlencode($this->access_token_secret);
        $oauth_params['oauth_signature'] = base64_encode(hash_hmac('sha1', $base_string, $signing_key, true));
        
        $auth_parts = [];
        foreach ($oauth_params as $key => $value) {
            $auth_parts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
        }
        
        return 'Authorization: OAuth ' . implode(', ', $auth_parts);
    }
    
    /**
     * Search recent tweets
     * 
     * @param string $query Search query
     * @param array $options Search options
     * @return array|false
     */
    public function searchTweets($query, $options = []) {
        $max_results = min($options['max_results'] ?? 10, 100);
        $sort_order = $options['sort_order'] ?? 'relevancy'; // relevancy, recency
        
        $params = [
            'query' => $query,
            'max_results' => $max_results,
            'sort_order' => $sort_order,
            'tweet.fields' => 'created_at,public_metrics,author_id',
            'user.fields' => 'name,username,verified'
        ];
        
        if (!empty($options['next_token'])) {
            $params['next_token'] = $options['next_token'];
        }
        
        if (!empty($options['start_time'])) {
            $params['start_time'] = $options['start_time'];
        }
        
        if (!empty($options['end_time'])) {
            $params['end_time'] = $options['end_time'];
        }
        
        $data = $this->make_request('tweets/search/recent', $params);
        
        if ($data === false) {
            return false;
        }
        
        $tweets = [];
        $users = [];
        
        // Index users
        if (isset($data['includes']['users'])) {
            foreach ($data['includes']['users'] as $user) {
                $users[$user['id']] = [
                    'name' => $user['name'],
                    'username' => $user['username'],
                    'verified' => $user['verified'] ?? false
                ];
            }
        }
        
        // Parse tweets
        if (isset($data['data'])) {
            foreach ($data['data'] as $tweet) {
                $author = $users[$tweet['author_id']] ?? null;
                $tweets[] = [
                    'id' => $tweet['id'],
                    'text' => $tweet['text'],
                    'created_at' => $tweet['created_at'],
                    'author' => $author,
                    'metrics' => $tweet['public_metrics']
                ];
            }
        }
        
        return [
            'success' => true,
            'query' => $query,
            'tweets' => $tweets,
            'next_token' => $data['meta']['next_token'] ?? null,
            'total_count' => $data['meta']['result_count'] ?? 0
        ];
    }
    
    /**
     * Get user by username
     * 
     * @param string $username Twitter username (without @)
     * @return array|false
     */
    public function getUserByUsername($username) {
        $username = ltrim($username, '@');
        
        $params = [
            'user.fields' => 'created_at,description,verified,public_metrics,profile_image_url'
        ];
        
        $data = $this->make_request("users/by/username/{$username}", $params);
        
        if ($data === false || !isset($data['data'])) {
            return false;
        }
        
        $user = $data['data'];
        
        return [
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'username' => $user['username'],
                'description' => $user['description'] ?? '',
                'created_at' => $user['created_at'] ?? null,
                'verified' => $user['verified'] ?? false,
                'profile_image_url' => $user['profile_image_url'] ?? null,
                'followers_count' => $user['public_metrics']['followers_count'] ?? 0,
                'following_count' => $user['public_metrics']['following_count'] ?? 0,
                'tweet_count' => $user['public_metrics']['tweet_count'] ?? 0
            ]
        ];
    }
    
    /**
     * Get user tweets
     * 
     * @param string $user_id User ID
     * @param array $options Options
     * @return array|false
     */
    public function getUserTweets($user_id, $options = []) {
        $max_results = min($options['max_results'] ?? 10, 100);
        
        $params = [
            'max_results' => $max_results,
            'tweet.fields' => 'created_at,public_metrics'
        ];
        
        if (!empty($options['next_token'])) {
            $params['pagination_token'] = $options['next_token'];
        }
        
        if (!empty($options['exclude'])) {
            $params['exclude'] = $options['exclude']; // retweets, replies
        }
        
        $data = $this->make_request("users/{$user_id}/tweets", $params);
        
        if ($data === false) {
            return false;
        }
        
        $tweets = [];
        if (isset($data['data'])) {
            foreach ($data['data'] as $tweet) {
                $tweets[] = [
                    'id' => $tweet['id'],
                    'text' => $tweet['text'],
                    'created_at' => $tweet['created_at'],
                    'metrics' => $tweet['public_metrics']
                ];
            }
        }
        
        return [
            'success' => true,
            'user_id' => $user_id,
            'tweets' => $tweets,
            'next_token' => $data['meta']['next_token'] ?? null
        ];
    }
    
    /**
     * Get user timeline
     * 
     * @param string $user_id User ID
     * @param array $options Options
     * @return array|false
     */
    public function getUserTimeline($user_id, $options = []) {
        $max_results = min($options['max_results'] ?? 10, 100);
        
        $params = [
            'max_results' => $max_results,
            'tweet.fields' => 'created_at,public_metrics',
            'expansions' => 'author_id'
        ];
        
        if (!empty($options['next_token'])) {
            $params['pagination_token'] = $options['next_token'];
        }
        
        $data = $this->make_request("users/{$user_id}/timelines/reverse_chronological", $params);
        
        if ($data === false) {
            return false;
        }
        
        $tweets = [];
        if (isset($data['data'])) {
            foreach ($data['data'] as $tweet) {
                $tweets[] = [
                    'id' => $tweet['id'],
                    'text' => $tweet['text'],
                    'created_at' => $tweet['created_at'],
                    'metrics' => $tweet['public_metrics']
                ];
            }
        }
        
        return [
            'success' => true,
            'user_id' => $user_id,
            'tweets' => $tweets,
            'next_token' => $data['meta']['next_token'] ?? null
        ];
    }
    
    /**
     * Get trending topics
     * 
     * @param int $woeid Yahoo Where On Earth ID (1 = worldwide, 23424969 = Turkey)
     * @return array|false
     */
    public function getTrends($woeid = 1) {
        $data = $this->make_v1_request('trends/place.json', ['id' => $woeid]);
        
        if ($data === false || empty($data[0]['trends'])) {
            return false;
        }
        
        $trends = [];
        foreach ($data[0]['trends'] as $trend) {
            $trends[] = [
                'name' => $trend['name'],
                'url' => $trend['url'],
                'tweet_volume' => $trend['tweet_volume'],
                'promoted' => $trend['promoted_content'] ?? false
            ];
        }
        
        return [
            'success' => true,
            'location' => $data[0]['locations'][0]['name'] ?? 'Worldwide',
            'woeid' => $woeid,
            'trends' => $trends
        ];
    }
    
    /**
     * Get WOEID for country (common locations)
     * 
     * @return array
     */
    public function getAvailableLocations() {
        return [
            'worldwide' => 1,
            'united_states' => 23424977,
            'turkey' => 23424969,
            'united_kingdom' => 23424975,
            'germany' => 23424829,
            'france' => 23424819,
            'japan' => 23424856,
            'india' => 23424848,
            'brazil' => 23424768,
            'canada' => 23424775,
            'australia' => 23424748,
            'russia' => 23424936,
            'china' => 23424781,
            'south_korea' => 23424868,
            'italy' => 23424853,
            'spain' => 23424950,
            'netherlands' => 23424909,
            'sweden' => 23424954,
            'norway' => 23424910,
            'denmark' => 23424796,
            'finland' => 23424812,
            'poland' => 23424923,
            'ukraine' => 23424976,
            'indonesia' => 23424846,
            'mexico' => 23424900,
            'argentina' => 23424747,
            'south_africa' => 23424942,
            'egypt' => 23424802,
            'saudi_arabia' => 23424938,
            'israel' => 23424852
        ];
    }
    
    /**
     * Extract text from response
     * 
     * @param array $response API response
     * @return string
     */
    public function extractText($response) {
        if (isset($response['tweets'])) {
            $texts = [];
            foreach ($response['tweets'] as $tweet) {
                $author = $tweet['author']['username'] ?? 'unknown';
                $texts[] = '@' . $author . ': ' . $tweet['text'] . "\n❤️ " . $tweet['metrics']['like_count'] . ' | 🔁 ' . $tweet['metrics']['retweet_count'];
            }
            return implode("\n\n", $texts);
        }
        
        if (isset($response['trends'])) {
            $texts = [];
            foreach ($response['trends'] as $trend) {
                $volume = $trend['tweet_volume'] ? number_format($trend['tweet_volume']) . ' tweets' : 'N/A';
                $texts[] = $trend['name'] . ' (' . $volume . ')';
            }
            return implode("\n", $texts);
        }
        
        return '';
    }
}