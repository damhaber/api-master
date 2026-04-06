<?php
/**
 * API Master Module - Reddit API
 * Reddit API - Posts, comments, subreddits, user info
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Reddit implements APIMaster_APIInterface {
    
    /**
     * API base URL (public)
     */
    private $base_url = 'https://www.reddit.com/';
    
    /**
     * OAuth API URL
     */
    private $oauth_url = 'https://oauth.reddit.com/';
    
    /**
     * Client ID
     */
    private $client_id;
    
    /**
     * Client secret
     */
    private $client_secret;
    
    /**
     * Access token
     */
    private $access_token;
    
    /**
     * User agent
     */
    private $user_agent;
    
    /**
     * Current model
     */
    private $model = 'posts';
    
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
        $this->client_id = $config['client_id'] ?? $config['api_key'] ?? '';
        $this->client_secret = $config['client_secret'] ?? '';
        $this->user_agent = $config['user_agent'] ?? 'APIMaster Reddit Module/1.0';
        $this->model = $config['model'] ?? 'posts';
        $this->timeout = $config['timeout'] ?? 30;
    }
    
    /**
     * Set API key (client ID)
     * 
     * @param string $api_key
     * @return self
     */
    public function setApiKey($api_key) {
        $this->client_id = $api_key;
        return $this;
    }
    
    /**
     * Set client secret
     * 
     * @param string $client_secret
     * @return self
     */
    public function setClientSecret($client_secret) {
        $this->client_secret = $client_secret;
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
     * Complete a prompt (search posts)
     * Required by APIMaster_APIInterface
     * 
     * @param string $prompt Search query
     * @param array $options Additional options
     * @return array|false Response or false on error
     */
    public function complete($prompt, $options = []) {
        return $this->searchPosts($prompt, $options);
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
        $result = $this->searchPosts($prompt, $options);
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
            'posts' => [
                'name' => 'Subreddit Posts',
                'description' => 'Get posts from a subreddit',
                'type' => 'content'
            ],
            'search' => [
                'name' => 'Search Posts',
                'description' => 'Search Reddit posts',
                'type' => 'search'
            ],
            'subreddit' => [
                'name' => 'Subreddit Info',
                'description' => 'Get subreddit information',
                'type' => 'info'
            ],
            'user' => [
                'name' => 'User Info',
                'description' => 'Get Reddit user information',
                'type' => 'info'
            ],
            'popular' => [
                'name' => 'Popular Posts',
                'description' => 'Get popular Reddit posts',
                'type' => 'content'
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
            'get_posts' => true,
            'get_comments' => true,
            'search' => true,
            'get_user_info' => true,
            'get_subreddit_info' => true,
            'streaming' => false,
            'requires_auth' => false,
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
        $result = $this->getPosts('all', ['limit' => 1]);
        return $result !== false;
    }
    
    /**
     * Chat method (not fully supported)
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
            return $this->searchPosts($last_message['content'], $options);
        }
        return false;
    }
    
    /**
     * Get access token (OAuth)
     * 
     * @return string|null
     */
    private function getAccessToken() {
        if (empty($this->client_id) || empty($this->client_secret)) {
            return null;
        }
        
        $credentials = base64_encode($this->client_id . ':' . $this->client_secret);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url . 'api/v1/access_token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $credentials,
            'User-Agent: ' . $this->user_agent
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            $this->access_token = $data['access_token'];
            return $this->access_token;
        }
        
        return null;
    }
    
    /**
     * Make API request
     * 
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @param bool $use_oauth Use OAuth
     * @return array|false
     */
    private function make_request($endpoint, $params = [], $use_oauth = false) {
        $base = ($use_oauth && $this->access_token) ? $this->oauth_url : $this->base_url;
        $url = $base . ltrim($endpoint, '/');
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $headers = [
            'User-Agent: ' . $this->user_agent
        ];
        
        if ($use_oauth && $this->access_token) {
            $headers[] = 'Authorization: Bearer ' . $this->access_token;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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
        
        if ($data === null) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * Get posts from a subreddit
     * 
     * @param string $subreddit Subreddit name
     * @param array $options Post options
     * @return array|false
     */
    public function getPosts($subreddit, $options = []) {
        $sort = $options['sort'] ?? 'hot'; // hot, new, top, rising
        $limit = min($options['limit'] ?? 25, 100);
        
        $params = ['limit' => $limit];
        
        if (!empty($options['after'])) {
            $params['after'] = $options['after'];
        }
        
        if (!empty($options['before'])) {
            $params['before'] = $options['before'];
        }
        
        // Time filter for top sort
        if ($sort === 'top' && !empty($options['time'])) {
            $params['t'] = $options['time']; // hour, day, week, month, year, all
        }
        
        $endpoint = "r/{$subreddit}/{$sort}.json";
        $data = $this->make_request($endpoint, $params);
        
        if ($data === false || !isset($data['data']['children'])) {
            return false;
        }
        
        $posts = [];
        foreach ($data['data']['children'] as $child) {
            $posts[] = $this->parsePost($child['data']);
        }
        
        return [
            'success' => true,
            'subreddit' => $subreddit,
            'sort' => $sort,
            'after' => $data['data']['after'] ?? null,
            'before' => $data['data']['before'] ?? null,
            'posts' => $posts
        ];
    }
    
    /**
     * Get post details with comments
     * 
     * @param string $subreddit Subreddit name
     * @param string $post_id Post ID
     * @return array|false
     */
    public function getPostDetails($subreddit, $post_id) {
        $endpoint = "r/{$subreddit}/comments/{$post_id}.json";
        $data = $this->make_request($endpoint);
        
        if ($data === false || !isset($data[0]['data']['children'][0])) {
            return false;
        }
        
        $post_data = $data[0]['data']['children'][0]['data'];
        $post = $this->parsePost($post_data);
        
        $comments = [];
        if (isset($data[1]['data']['children'])) {
            foreach ($data[1]['data']['children'] as $child) {
                if ($child['kind'] === 't1') {
                    $comments[] = $this->parseComment($child['data']);
                }
            }
        }
        
        return [
            'success' => true,
            'post' => $post,
            'comments' => $comments
        ];
    }
    
    /**
     * Search posts
     * 
     * @param string $query Search query
     * @param array $options Search options
     * @return array|false
     */
    public function searchPosts($query, $options = []) {
        $limit = min($options['limit'] ?? 25, 100);
        $sort = $options['sort'] ?? 'relevance'; // relevance, hot, top, new, comments
        $time = $options['time'] ?? 'all'; // hour, day, week, month, year, all
        
        $params = [
            'q' => $query,
            'sort' => $sort,
            'limit' => $limit,
            't' => $time
        ];
        
        if (!empty($options['after'])) {
            $params['after'] = $options['after'];
        }
        
        if (!empty($options['subreddit'])) {
            $params['restrict_sr'] = true;
            $endpoint = "r/{$options['subreddit']}/search.json";
        } else {
            $endpoint = 'search.json';
        }
        
        $data = $this->make_request($endpoint, $params);
        
        if ($data === false || !isset($data['data']['children'])) {
            return false;
        }
        
        $posts = [];
        foreach ($data['data']['children'] as $child) {
            $posts[] = $this->parsePost($child['data']);
        }
        
        return [
            'success' => true,
            'query' => $query,
            'after' => $data['data']['after'] ?? null,
            'posts' => $posts
        ];
    }
    
    /**
     * Get subreddit information
     * 
     * @param string $subreddit Subreddit name
     * @return array|false
     */
    public function getSubredditInfo($subreddit) {
        $endpoint = "r/{$subreddit}/about.json";
        $data = $this->make_request($endpoint);
        
        if ($data === false || !isset($data['data'])) {
            return false;
        }
        
        $info = $data['data'];
        
        return [
            'success' => true,
            'subreddit' => [
                'id' => $info['id'],
                'name' => $info['display_name'],
                'title' => $info['title'],
                'description' => $info['public_description'],
                'subscribers' => $info['subscribers'],
                'active_users' => $info['active_user_count'] ?? 0,
                'created_utc' => $info['created_utc'],
                'over_18' => $info['over18'],
                'icon_img' => $info['icon_img'],
                'url' => 'https://reddit.com' . $info['url']
            ]
        ];
    }
    
    /**
     * Get user information
     * 
     * @param string $username Username
     * @return array|false
     */
    public function getUserInfo($username) {
        $endpoint = "user/{$username}/about.json";
        $data = $this->make_request($endpoint);
        
        if ($data === false || !isset($data['data'])) {
            return false;
        }
        
        $info = $data['data'];
        
        return [
            'success' => true,
            'user' => [
                'id' => $info['id'],
                'name' => $info['name'],
                'created_utc' => $info['created_utc'],
                'link_karma' => $info['link_karma'],
                'comment_karma' => $info['comment_karma'],
                'total_karma' => $info['total_karma'],
                'icon_img' => $info['icon_img']
            ]
        ];
    }
    
    /**
     * Get user posts
     * 
     * @param string $username Username
     * @param array $options Options
     * @return array|false
     */
    public function getUserPosts($username, $options = []) {
        $limit = min($options['limit'] ?? 25, 100);
        
        $params = ['limit' => $limit];
        
        if (!empty($options['after'])) {
            $params['after'] = $options['after'];
        }
        
        $endpoint = "user/{$username}/submitted.json";
        $data = $this->make_request($endpoint, $params);
        
        if ($data === false || !isset($data['data']['children'])) {
            return false;
        }
        
        $posts = [];
        foreach ($data['data']['children'] as $child) {
            $posts[] = $this->parsePost($child['data']);
        }
        
        return [
            'success' => true,
            'username' => $username,
            'after' => $data['data']['after'] ?? null,
            'posts' => $posts
        ];
    }
    
    /**
     * Get popular posts
     * 
     * @param array $options Options
     * @return array|false
     */
    public function getPopularPosts($options = []) {
        $sort = $options['sort'] ?? 'hot';
        $limit = min($options['limit'] ?? 25, 100);
        
        $params = ['limit' => $limit];
        
        if (!empty($options['after'])) {
            $params['after'] = $options['after'];
        }
        
        $endpoint = "r/popular/{$sort}.json";
        $data = $this->make_request($endpoint, $params);
        
        if ($data === false || !isset($data['data']['children'])) {
            return false;
        }
        
        $posts = [];
        foreach ($data['data']['children'] as $child) {
            $posts[] = $this->parsePost($child['data']);
        }
        
        return [
            'success' => true,
            'after' => $data['data']['after'] ?? null,
            'posts' => $posts
        ];
    }
    
    /**
     * Parse post data
     * 
     * @param array $data Raw post data
     * @return array
     */
    private function parsePost($data) {
        return [
            'id' => $data['id'],
            'title' => $data['title'],
            'text' => $data['selftext'] ?? '',
            'author' => $data['author'],
            'created_utc' => $data['created_utc'],
            'score' => $data['score'],
            'upvote_ratio' => $data['upvote_ratio'],
            'num_comments' => $data['num_comments'],
            'url' => $data['url'],
            'permalink' => 'https://reddit.com' . $data['permalink'],
            'subreddit' => $data['subreddit'],
            'is_self' => $data['is_self'],
            'is_video' => $data['is_video'],
            'spoiler' => $data['spoiler'],
            'over_18' => $data['over_18'],
            'thumbnail' => $data['thumbnail'] ?? null
        ];
    }
    
    /**
     * Parse comment data
     * 
     * @param array $data Raw comment data
     * @return array
     */
    private function parseComment($data) {
        return [
            'id' => $data['id'],
            'author' => $data['author'],
            'body' => $data['body'],
            'score' => $data['score'],
            'created_utc' => $data['created_utc'],
            'permalink' => 'https://reddit.com' . $data['permalink'],
            'parent_id' => $data['parent_id']
        ];
    }
    
    /**
     * Extract text from response
     * 
     * @param array $response API response
     * @return string
     */
    public function extractText($response) {
        if (isset($response['posts'])) {
            $texts = [];
            foreach ($response['posts'] as $post) {
                $texts[] = $post['title'] . "\n" . ($post['text'] ?: '') . "\n↑ " . $post['score'] . ' | 💬 ' . $post['num_comments'];
            }
            return implode("\n\n", $texts);
        }
        
        if (isset($response['comments'])) {
            $texts = [];
            foreach ($response['comments'] as $comment) {
                $texts[] = $comment['author'] . ': ' . $comment['body'];
            }
            return implode("\n", $texts);
        }
        
        return '';
    }
}