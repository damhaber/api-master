<?php
/**
 * API Master Module - Spotify API
 * Spotify Web API - Search, tracks, artists, albums, playlists
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Spotify implements APIMaster_APIInterface {
    
    /**
     * API base URL
     */
    private $api_url = 'https://api.spotify.com/v1/';
    
    /**
     * Auth URL
     */
    private $auth_url = 'https://accounts.spotify.com/api/token';
    
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
     * Token expiry time
     */
    private $token_expiry;
    
    /**
     * Current model
     */
    private $model = 'search';
    
    /**
     * Request timeout
     */
    private $timeout = 30;
    
    /**
     * Default market
     */
    private $market = 'TR';
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct(array $config = []) {
        $this->client_id = $config['client_id'] ?? $config['api_key'] ?? '';
        $this->client_secret = $config['client_secret'] ?? '';
        $this->model = $config['model'] ?? 'search';
        $this->timeout = $config['timeout'] ?? 30;
        $this->market = $config['market'] ?? 'TR';
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
     * Complete a prompt (search music)
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
            'search' => [
                'name' => 'Search Music',
                'description' => 'Search for tracks, artists, albums, playlists',
                'type' => 'search'
            ],
            'track' => [
                'name' => 'Track Details',
                'description' => 'Get track information',
                'type' => 'metadata'
            ],
            'artist' => [
                'name' => 'Artist Details',
                'description' => 'Get artist information and top tracks',
                'type' => 'metadata'
            ],
            'album' => [
                'name' => 'Album Details',
                'description' => 'Get album information',
                'type' => 'metadata'
            ],
            'playlist' => [
                'name' => 'Playlist Details',
                'description' => 'Get playlist information',
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
            'tracks' => true,
            'artists' => true,
            'albums' => true,
            'playlists' => true,
            'recommendations' => true,
            'new_releases' => true,
            'streaming' => false,
            'requires_auth' => true
        ];
    }
    
    /**
     * Check API health
     * Required by APIMaster_APIInterface
     * 
     * @return bool
     */
    public function checkHealth() {
        if (empty($this->client_id) || empty($this->client_secret)) {
            return false;
        }
        
        $token = $this->getAccessToken();
        return $token !== null;
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
            return $this->search($last_message['content'], $options);
        }
        return false;
    }
    
    /**
     * Get access token
     * 
     * @return string|null
     */
    private function getAccessToken() {
        // Check if token is still valid
        if ($this->access_token && $this->token_expiry && time() < $this->token_expiry) {
            return $this->access_token;
        }
        
        if (empty($this->client_id) || empty($this->client_secret)) {
            return null;
        }
        
        $credentials = base64_encode($this->client_id . ':' . $this->client_secret);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->auth_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            $this->access_token = $data['access_token'];
            $this->token_expiry = time() + $data['expires_in'];
            return $this->access_token;
        }
        
        return null;
    }
    
    /**
     * Make API request
     * 
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @return array|false
     */
    private function make_request($endpoint, $params = []) {
        $token = $this->getAccessToken();
        if (!$token) {
            return false;
        }
        
        $url = $this->api_url . $endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token
        ]);
        
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
     * Search Spotify
     * 
     * @param string $query Search query
     * @param array $options Search options
     * @return array|false
     */
    public function search($query, $options = []) {
        $type = $options['type'] ?? 'track,artist,album,playlist';
        $limit = min($options['limit'] ?? 20, 50);
        $offset = $options['offset'] ?? 0;
        $market = $options['market'] ?? $this->market;
        
        $params = [
            'q' => $query,
            'type' => $type,
            'limit' => $limit,
            'offset' => $offset,
            'market' => $market
        ];
        
        $data = $this->make_request('search', $params);
        
        if ($data === false) {
            return false;
        }
        
        $result = [
            'success' => true,
            'query' => $query,
            'tracks' => [],
            'artists' => [],
            'albums' => [],
            'playlists' => []
        ];
        
        if (isset($data['tracks']['items'])) {
            $result['tracks'] = $this->parseTracks($data['tracks']['items']);
            $result['tracks_total'] = $data['tracks']['total'];
        }
        
        if (isset($data['artists']['items'])) {
            $result['artists'] = $this->parseArtists($data['artists']['items']);
            $result['artists_total'] = $data['artists']['total'];
        }
        
        if (isset($data['albums']['items'])) {
            $result['albums'] = $this->parseAlbums($data['albums']['items']);
            $result['albums_total'] = $data['albums']['total'];
        }
        
        if (isset($data['playlists']['items'])) {
            $result['playlists'] = $this->parsePlaylists($data['playlists']['items']);
            $result['playlists_total'] = $data['playlists']['total'];
        }
        
        return $result;
    }
    
    /**
     * Get track details
     * 
     * @param string $track_id Track ID
     * @return array|false
     */
    public function getTrack($track_id) {
        $data = $this->make_request("tracks/{$track_id}");
        
        if ($data === false) {
            return false;
        }
        
        return [
            'success' => true,
            'track' => $this->parseTrack($data)
        ];
    }
    
    /**
     * Get artist details
     * 
     * @param string $artist_id Artist ID
     * @return array|false
     */
    public function getArtist($artist_id) {
        $data = $this->make_request("artists/{$artist_id}");
        
        if ($data === false) {
            return false;
        }
        
        return [
            'success' => true,
            'artist' => [
                'id' => $data['id'],
                'name' => $data['name'],
                'popularity' => $data['popularity'],
                'followers' => $data['followers']['total'],
                'genres' => $data['genres'],
                'images' => $data['images'],
                'url' => $data['external_urls']['spotify']
            ]
        ];
    }
    
    /**
     * Get artist's top tracks
     * 
     * @param string $artist_id Artist ID
     * @param array $options Options
     * @return array|false
     */
    public function getArtistTopTracks($artist_id, $options = []) {
        $market = $options['market'] ?? $this->market;
        
        $params = ['market' => $market];
        $data = $this->make_request("artists/{$artist_id}/top-tracks", $params);
        
        if ($data === false || !isset($data['tracks'])) {
            return false;
        }
        
        return [
            'success' => true,
            'artist_id' => $artist_id,
            'tracks' => $this->parseTracks($data['tracks'])
        ];
    }
    
    /**
     * Get album details
     * 
     * @param string $album_id Album ID
     * @return array|false
     */
    public function getAlbum($album_id) {
        $data = $this->make_request("albums/{$album_id}");
        
        if ($data === false) {
            return false;
        }
        
        return [
            'success' => true,
            'album' => [
                'id' => $data['id'],
                'name' => $data['name'],
                'album_type' => $data['album_type'],
                'total_tracks' => $data['total_tracks'],
                'release_date' => $data['release_date'],
                'artists' => $this->parseArtists($data['artists']),
                'images' => $data['images'],
                'tracks' => $this->parseTracks($data['tracks']['items']),
                'url' => $data['external_urls']['spotify']
            ]
        ];
    }
    
    /**
     * Get playlist details
     * 
     * @param string $playlist_id Playlist ID
     * @return array|false
     */
    public function getPlaylist($playlist_id) {
        $data = $this->make_request("playlists/{$playlist_id}");
        
        if ($data === false) {
            return false;
        }
        
        return [
            'success' => true,
            'playlist' => [
                'id' => $data['id'],
                'name' => $data['name'],
                'description' => $data['description'],
                'owner' => $data['owner']['display_name'],
                'followers' => $data['followers']['total'],
                'public' => $data['public'],
                'tracks_count' => $data['tracks']['total'],
                'images' => $data['images'],
                'url' => $data['external_urls']['spotify']
            ]
        ];
    }
    
    /**
     * Get playlist tracks
     * 
     * @param string $playlist_id Playlist ID
     * @param array $options Options
     * @return array|false
     */
    public function getPlaylistTracks($playlist_id, $options = []) {
        $limit = min($options['limit'] ?? 20, 100);
        $offset = $options['offset'] ?? 0;
        $market = $options['market'] ?? $this->market;
        
        $params = [
            'limit' => $limit,
            'offset' => $offset,
            'market' => $market
        ];
        
        $data = $this->make_request("playlists/{$playlist_id}/tracks", $params);
        
        if ($data === false || !isset($data['items'])) {
            return false;
        }
        
        $tracks = [];
        foreach ($data['items'] as $item) {
            if ($item['track']) {
                $tracks[] = $this->parseTrack($item['track']);
            }
        }
        
        return [
            'success' => true,
            'playlist_id' => $playlist_id,
            'total' => $data['total'],
            'tracks' => $tracks
        ];
    }
    
    /**
     * Get new releases
     * 
     * @param array $options Options
     * @return array|false
     */
    public function getNewReleases($options = []) {
        $limit = min($options['limit'] ?? 20, 50);
        $offset = $options['offset'] ?? 0;
        $country = $options['country'] ?? $this->market;
        
        $params = [
            'limit' => $limit,
            'offset' => $offset,
            'country' => $country
        ];
        
        $data = $this->make_request('browse/new-releases', $params);
        
        if ($data === false || !isset($data['albums']['items'])) {
            return false;
        }
        
        return [
            'success' => true,
            'total' => $data['albums']['total'],
            'albums' => $this->parseAlbums($data['albums']['items'])
        ];
    }
    
    /**
     * Get recommendations
     * 
     * @param array $options Recommendation options
     * @return array|false
     */
    public function getRecommendations($options = []) {
        $limit = min($options['limit'] ?? 20, 100);
        $market = $options['market'] ?? $this->market;
        
        $params = [
            'limit' => $limit,
            'market' => $market
        ];
        
        // Seed parameters (max 5 seeds total)
        if (!empty($options['seed_artists'])) {
            $seed_artists = is_array($options['seed_artists']) 
                ? implode(',', array_slice($options['seed_artists'], 0, 5))
                : $options['seed_artists'];
            $params['seed_artists'] = $seed_artists;
        }
        
        if (!empty($options['seed_tracks'])) {
            $seed_tracks = is_array($options['seed_tracks'])
                ? implode(',', array_slice($options['seed_tracks'], 0, 5))
                : $options['seed_tracks'];
            $params['seed_tracks'] = $seed_tracks;
        }
        
        if (!empty($options['seed_genres'])) {
            $seed_genres = is_array($options['seed_genres'])
                ? implode(',', array_slice($options['seed_genres'], 0, 5))
                : $options['seed_genres'];
            $params['seed_genres'] = $seed_genres;
        }
        
        // Audio features
        $features = ['danceability', 'energy', 'tempo', 'valence', 'acousticness', 'instrumentalness', 'liveness', 'speechiness'];
        foreach ($features as $feature) {
            if (isset($options["min_{$feature}"])) {
                $params["min_{$feature}"] = $options["min_{$feature}"];
            }
            if (isset($options["max_{$feature}"])) {
                $params["max_{$feature}"] = $options["max_{$feature}"];
            }
        }
        
        $data = $this->make_request('recommendations', $params);
        
        if ($data === false || !isset($data['tracks'])) {
            return false;
        }
        
        return [
            'success' => true,
            'tracks' => $this->parseTracks($data['tracks'])
        ];
    }
    
    /**
     * Parse track data
     * 
     * @param array $track Raw track data
     * @return array
     */
    private function parseTrack($track) {
        return [
            'id' => $track['id'],
            'name' => $track['name'],
            'duration_ms' => $track['duration_ms'],
            'explicit' => $track['explicit'],
            'preview_url' => $track['preview_url'],
            'artists' => $this->parseArtists($track['artists']),
            'album' => [
                'id' => $track['album']['id'],
                'name' => $track['album']['name'],
                'images' => $track['album']['images']
            ],
            'url' => $track['external_urls']['spotify']
        ];
    }
    
    /**
     * Parse tracks array
     * 
     * @param array $tracks Array of tracks
     * @return array
     */
    private function parseTracks($tracks) {
        $parsed = [];
        foreach ($tracks as $track) {
            $parsed[] = $this->parseTrack($track);
        }
        return $parsed;
    }
    
    /**
     * Parse artist data
     * 
     * @param array $artists Array of artists
     * @return array
     */
    private function parseArtists($artists) {
        $parsed = [];
        foreach ($artists as $artist) {
            $parsed[] = [
                'id' => $artist['id'],
                'name' => $artist['name'],
                'url' => $artist['external_urls']['spotify'] ?? null
            ];
        }
        return $parsed;
    }
    
    /**
     * Parse albums array
     * 
     * @param array $albums Array of albums
     * @return array
     */
    private function parseAlbums($albums) {
        $parsed = [];
        foreach ($albums as $album) {
            $parsed[] = [
                'id' => $album['id'],
                'name' => $album['name'],
                'album_type' => $album['album_type'],
                'total_tracks' => $album['total_tracks'],
                'release_date' => $album['release_date'],
                'artists' => $this->parseArtists($album['artists']),
                'images' => $album['images'],
                'url' => $album['external_urls']['spotify']
            ];
        }
        return $parsed;
    }
    
    /**
     * Parse playlists array
     * 
     * @param array $playlists Array of playlists
     * @return array
     */
    private function parsePlaylists($playlists) {
        $parsed = [];
        foreach ($playlists as $playlist) {
            $parsed[] = [
                'id' => $playlist['id'],
                'name' => $playlist['name'],
                'description' => $playlist['description'],
                'owner' => $playlist['owner']['display_name'],
                'tracks_count' => $playlist['tracks']['total'] ?? null,
                'images' => $playlist['images'],
                'url' => $playlist['external_urls']['spotify']
            ];
        }
        return $parsed;
    }
    
    /**
     * Set market
     * 
     * @param string $market Market code (TR, US, GB, etc.)
     * @return self
     */
    public function setMarket($market) {
        $this->market = strtoupper($market);
        return $this;
    }
    
    /**
     * Extract text from response
     * 
     * @param array $response API response
     * @return string
     */
    public function extractText($response) {
        if (isset($response['tracks']) && !empty($response['tracks'])) {
            $texts = [];
            foreach ($response['tracks'] as $track) {
                $artist_names = implode(', ', array_column($track['artists'], 'name'));
                $texts[] = $track['name'] . ' - ' . $artist_names;
            }
            return implode("\n", $texts);
        }
        
        if (isset($response['albums']) && !empty($response['albums'])) {
            $texts = [];
            foreach ($response['albums'] as $album) {
                $artist_names = implode(', ', array_column($album['artists'], 'name'));
                $texts[] = $album['name'] . ' - ' . $artist_names . ' (' . $album['release_date'] . ')';
            }
            return implode("\n", $texts);
        }
        
        return '';
    }
}