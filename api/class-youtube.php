<?php
/**
 * API Master Module - YouTube API
 * YouTube Data API v3 - Search, videos, channels, playlists
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_YouTube implements APIMaster_APIInterface {
    
    /**
     * API base URL
     */
    private $api_url = 'https://www.googleapis.com/youtube/v3/';
    
    /**
     * API key
     */
    private $api_key;
    
    /**
     * Current model (search type)
     */
    private $model = 'video';
    
    /**
     * Request timeout
     */
    private $timeout = 30;
    
    /**
     * Region code
     */
    private $region_code = 'TR';
    
    /**
     * Language
     */
    private $language = 'tr';
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct(array $config = []) {
        $this->api_key = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'video';
        $this->timeout = $config['timeout'] ?? 30;
        $this->region_code = $config['region_code'] ?? 'TR';
        $this->language = $config['language'] ?? 'tr';
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
     * Complete a prompt (search YouTube)
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
     * Get available models (search types)
     * Required by APIMaster_APIInterface
     * 
     * @return array
     */
    public function getModels() {
        return [
            'video' => [
                'name' => 'Video Search',
                'description' => 'Search for YouTube videos',
                'type' => 'search'
            ],
            'channel' => [
                'name' => 'Channel Search',
                'description' => 'Search for YouTube channels',
                'type' => 'search'
            ],
            'playlist' => [
                'name' => 'Playlist Search',
                'description' => 'Search for YouTube playlists',
                'type' => 'search'
            ],
            'popular' => [
                'name' => 'Popular Videos',
                'description' => 'Get popular videos by region',
                'type' => 'trending'
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
            'video_details' => true,
            'channel_details' => true,
            'playlist_details' => true,
            'comments' => true,
            'popular_videos' => true,
            'live_streams' => true,
            'streaming' => false,
            'requires_api_key' => true
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
        
        $result = $this->getPopularVideos(['max_results' => 1]);
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
        
        $params['key'] = $this->api_key;
        $url = $this->api_url . $endpoint . '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error || $http_code !== 200) {
            return false;
        }
        
        $data = json_decode($response, true);
        
        if ($data === null || isset($data['error'])) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * Search YouTube
     * 
     * @param string $query Search query
     * @param array $options Search options
     * @return array|false
     */
    public function search($query, $options = []) {
        $max_results = $options['max_results'] ?? 10;
        $order = $options['order'] ?? 'relevance'; // date, rating, viewCount, relevance
        $type = $options['type'] ?? $this->model; // video, channel, playlist
        
        $params = [
            'part' => 'snippet',
            'q' => $query,
            'maxResults' => $max_results,
            'order' => $order,
            'type' => $type,
            'regionCode' => $options['region_code'] ?? $this->region_code,
            'relevanceLanguage' => $options['language'] ?? $this->language
        ];
        
        if (!empty($options['page_token'])) {
            $params['pageToken'] = $options['page_token'];
        }
        
        if (!empty($options['video_duration']) && $options['video_duration'] !== 'any') {
            $params['videoDuration'] = $options['video_duration']; // short, medium, long
        }
        
        if (!empty($options['channel_id'])) {
            $params['channelId'] = $options['channel_id'];
        }
        
        $data = $this->make_request('search', $params);
        
        if ($data === false || !isset($data['items'])) {
            return false;
        }
        
        $results = [];
        foreach ($data['items'] as $item) {
            $snippet = $item['snippet'];
            $result = [
                'id' => $this->extractId($item['id']),
                'type' => $item['id']['kind'],
                'title' => $snippet['title'],
                'description' => $snippet['description'],
                'channel_id' => $snippet['channelId'],
                'channel_title' => $snippet['channelTitle'],
                'published_at' => $snippet['publishedAt'],
                'thumbnails' => $snippet['thumbnails']
            ];
            
            if ($item['id']['kind'] === 'youtube#video') {
                $result['video_id'] = $item['id']['videoId'];
                $result['url'] = "https://www.youtube.com/watch?v={$item['id']['videoId']}";
            } elseif ($item['id']['kind'] === 'youtube#channel') {
                $result['channel_id'] = $item['id']['channelId'];
                $result['url'] = "https://www.youtube.com/channel/{$item['id']['channelId']}";
            } elseif ($item['id']['kind'] === 'youtube#playlist') {
                $result['playlist_id'] = $item['id']['playlistId'];
                $result['url'] = "https://www.youtube.com/playlist?list={$item['id']['playlistId']}";
            }
            
            $results[] = $result;
        }
        
        return [
            'success' => true,
            'query' => $query,
            'total_results' => $data['pageInfo']['totalResults'] ?? count($results),
            'next_page_token' => $data['nextPageToken'] ?? null,
            'results' => $results
        ];
    }
    
    /**
     * Extract ID from search result
     * 
     * @param array $id_data ID data
     * @return string
     */
    private function extractId($id_data) {
        if (isset($id_data['videoId'])) {
            return $id_data['videoId'];
        }
        if (isset($id_data['channelId'])) {
            return $id_data['channelId'];
        }
        if (isset($id_data['playlistId'])) {
            return $id_data['playlistId'];
        }
        return '';
    }
    
    /**
     * Get video details
     * 
     * @param string $video_id Video ID
     * @return array|false
     */
    public function getVideoDetails($video_id) {
        $params = [
            'part' => 'snippet,contentDetails,statistics',
            'id' => $video_id
        ];
        
        $data = $this->make_request('videos', $params);
        
        if ($data === false || empty($data['items'])) {
            return false;
        }
        
        $video = $data['items'][0];
        $snippet = $video['snippet'];
        $statistics = $video['statistics'] ?? [];
        $content_details = $video['contentDetails'] ?? [];
        
        return [
            'success' => true,
            'id' => $video['id'],
            'title' => $snippet['title'],
            'description' => $snippet['description'],
            'channel_id' => $snippet['channelId'],
            'channel_title' => $snippet['channelTitle'],
            'published_at' => $snippet['publishedAt'],
            'thumbnails' => $snippet['thumbnails'],
            'tags' => $snippet['tags'] ?? [],
            'duration' => $this->parseDuration($content_details['duration'] ?? 'PT0S'),
            'view_count' => (int) ($statistics['viewCount'] ?? 0),
            'like_count' => (int) ($statistics['likeCount'] ?? 0),
            'comment_count' => (int) ($statistics['commentCount'] ?? 0),
            'url' => "https://www.youtube.com/watch?v={$video['id']}",
            'embed_url' => "https://www.youtube.com/embed/{$video['id']}"
        ];
    }
    
    /**
     * Get channel details
     * 
     * @param string $channel_id Channel ID
     * @return array|false
     */
    public function getChannelDetails($channel_id) {
        $params = [
            'part' => 'snippet,contentDetails,statistics',
            'id' => $channel_id
        ];
        
        $data = $this->make_request('channels', $params);
        
        if ($data === false || empty($data['items'])) {
            return false;
        }
        
        $channel = $data['items'][0];
        $snippet = $channel['snippet'];
        $statistics = $channel['statistics'];
        
        return [
            'success' => true,
            'id' => $channel['id'],
            'title' => $snippet['title'],
            'description' => $snippet['description'],
            'custom_url' => $snippet['customUrl'] ?? null,
            'published_at' => $snippet['publishedAt'],
            'thumbnails' => $snippet['thumbnails'],
            'country' => $snippet['country'] ?? null,
            'view_count' => (int) ($statistics['viewCount'] ?? 0),
            'subscriber_count' => (int) ($statistics['subscriberCount'] ?? 0),
            'video_count' => (int) ($statistics['videoCount'] ?? 0),
            'url' => "https://www.youtube.com/channel/{$channel['id']}"
        ];
    }
    
    /**
     * Get playlist details
     * 
     * @param string $playlist_id Playlist ID
     * @param array $options Options
     * @return array|false
     */
    public function getPlaylistItems($playlist_id, $options = []) {
        $max_results = $options['max_results'] ?? 10;
        
        $params = [
            'part' => 'snippet,contentDetails',
            'playlistId' => $playlist_id,
            'maxResults' => $max_results
        ];
        
        if (!empty($options['page_token'])) {
            $params['pageToken'] = $options['page_token'];
        }
        
        $data = $this->make_request('playlistItems', $params);
        
        if ($data === false || empty($data['items'])) {
            return false;
        }
        
        $items = [];
        foreach ($data['items'] as $item) {
            $snippet = $item['snippet'];
            $items[] = [
                'video_id' => $snippet['resourceId']['videoId'],
                'title' => $snippet['title'],
                'description' => $snippet['description'],
                'published_at' => $snippet['publishedAt'],
                'thumbnails' => $snippet['thumbnails'],
                'position' => $snippet['position'],
                'url' => "https://www.youtube.com/watch?v={$snippet['resourceId']['videoId']}"
            ];
        }
        
        return [
            'success' => true,
            'playlist_id' => $playlist_id,
            'total_items' => $data['pageInfo']['totalResults'] ?? count($items),
            'next_page_token' => $data['nextPageToken'] ?? null,
            'items' => $items
        ];
    }
    
    /**
     * Get popular videos
     * 
     * @param array $options Options
     * @return array|false
     */
    public function getPopularVideos($options = []) {
        $max_results = $options['max_results'] ?? 10;
        $region_code = $options['region_code'] ?? $this->region_code;
        
        $params = [
            'part' => 'snippet,statistics',
            'chart' => 'mostPopular',
            'maxResults' => $max_results,
            'regionCode' => $region_code
        ];
        
        if (!empty($options['video_category_id'])) {
            $params['videoCategoryId'] = $options['video_category_id'];
        }
        
        $data = $this->make_request('videos', $params);
        
        if ($data === false || empty($data['items'])) {
            return false;
        }
        
        $videos = [];
        foreach ($data['items'] as $video) {
            $snippet = $video['snippet'];
            $statistics = $video['statistics'] ?? [];
            $videos[] = [
                'id' => $video['id'],
                'title' => $snippet['title'],
                'channel_title' => $snippet['channelTitle'],
                'view_count' => (int) ($statistics['viewCount'] ?? 0),
                'like_count' => (int) ($statistics['likeCount'] ?? 0),
                'thumbnails' => $snippet['thumbnails'],
                'url' => "https://www.youtube.com/watch?v={$video['id']}"
            ];
        }
        
        return [
            'success' => true,
            'region_code' => $region_code,
            'videos' => $videos
        ];
    }
    
    /**
     * Get video comments
     * 
     * @param string $video_id Video ID
     * @param array $options Options
     * @return array|false
     */
    public function getComments($video_id, $options = []) {
        $max_results = $options['max_results'] ?? 10;
        
        $params = [
            'part' => 'snippet',
            'videoId' => $video_id,
            'maxResults' => $max_results
        ];
        
        if (!empty($options['page_token'])) {
            $params['pageToken'] = $options['page_token'];
        }
        
        $data = $this->make_request('commentThreads', $params);
        
        if ($data === false || empty($data['items'])) {
            return false;
        }
        
        $comments = [];
        foreach ($data['items'] as $item) {
            $top_comment = $item['snippet']['topLevelComment']['snippet'];
            $comments[] = [
                'id' => $item['id'],
                'author' => $top_comment['authorDisplayName'],
                'text' => $top_comment['textDisplay'],
                'like_count' => $top_comment['likeCount'],
                'published_at' => $top_comment['publishedAt'],
                'reply_count' => $item['snippet']['totalReplyCount']
            ];
        }
        
        return [
            'success' => true,
            'video_id' => $video_id,
            'total_comments' => count($comments),
            'next_page_token' => $data['nextPageToken'] ?? null,
            'comments' => $comments
        ];
    }
    
    /**
     * Parse ISO 8601 duration
     * 
     * @param string $duration ISO 8601 duration string
     * @return array
     */
    private function parseDuration($duration) {
        preg_match('/PT(\d+H)?(\d+M)?(\d+S)?/', $duration, $matches);
        
        $hours = isset($matches[1]) ? (int) rtrim($matches[1], 'H') : 0;
        $minutes = isset($matches[2]) ? (int) rtrim($matches[2], 'M') : 0;
        $seconds = isset($matches[3]) ? (int) rtrim($matches[3], 'S') : 0;
        
        $total_seconds = ($hours * 3600) + ($minutes * 60) + $seconds;
        
        return [
            'iso' => $duration,
            'seconds' => $total_seconds,
            'formatted' => sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds),
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $seconds
        ];
    }
    
    /**
     * Set region code
     * 
     * @param string $region_code Region code (TR, US, GB, etc.)
     * @return self
     */
    public function setRegionCode($region_code) {
        $this->region_code = strtoupper($region_code);
        return $this;
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
     * Extract text from response
     * 
     * @param array $response API response
     * @return string
     */
    public function extractText($response) {
        if (!isset($response['results']) && !isset($response['videos'])) {
            return '';
        }
        
        $items = $response['results'] ?? $response['videos'] ?? [];
        $texts = [];
        
        foreach ($items as $item) {
            $texts[] = $item['title'] . ' - ' . ($item['description'] ?? '');
        }
        
        return implode("\n\n", $texts);
    }
}