<?php
/**
 * API Master Module - GitHub API
 * GitHub REST API v3 - Repositories, users, issues, gists
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_GitHub implements APIMaster_APIInterface {
    
    /**
     * API base URL
     */
    private $api_url = 'https://api.github.com/';
    
    /**
     * API token (optional)
     */
    private $token;
    
    /**
     * Current model
     */
    private $model = 'repositories';
    
    /**
     * Request timeout
     */
    private $timeout = 30;
    
    /**
     * User agent
     */
    private $user_agent = 'APIMaster GitHub Module/1.0';
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct(array $config = []) {
        $this->token = $config['token'] ?? $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'repositories';
        $this->timeout = $config['timeout'] ?? 30;
        
        if (!empty($config['user_agent'])) {
            $this->user_agent = $config['user_agent'];
        }
    }
    
    /**
     * Set API key (token)
     * 
     * @param string $api_key
     * @return self
     */
    public function setApiKey($api_key) {
        $this->token = $api_key;
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
     * Complete a prompt (search repositories)
     * Required by APIMaster_APIInterface
     * 
     * @param string $prompt Search query
     * @param array $options Additional options
     * @return array|false Response or false on error
     */
    public function complete($prompt, $options = []) {
        return $this->searchRepositories($prompt, $options);
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
        $result = $this->searchRepositories($prompt, $options);
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
            'repositories' => [
                'name' => 'Search Repositories',
                'description' => 'Search GitHub repositories',
                'type' => 'search'
            ],
            'users' => [
                'name' => 'User Info',
                'description' => 'Get GitHub user information',
                'type' => 'user'
            ],
            'issues' => [
                'name' => 'Issues',
                'description' => 'Search and manage issues',
                'type' => 'issues'
            ],
            'gists' => [
                'name' => 'Gists',
                'description' => 'Create and manage gists',
                'type' => 'gists'
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
            'search_repositories' => true,
            'get_repository' => true,
            'get_user' => true,
            'get_content' => true,
            'search_issues' => true,
            'create_issue' => true,
            'create_gist' => true,
            'streaming' => false,
            'requires_api_key' => false,
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
        $result = $this->searchRepositories('test', ['per_page' => 1]);
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
            return $this->searchRepositories($last_message['content'], $options);
        }
        return false;
    }
    
    /**
     * Make API request
     * 
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @param string $method HTTP method
     * @param array $body Request body for POST/PUT
     * @return array|false
     */
    private function make_request($endpoint, $params = [], $method = 'GET', $body = null) {
        $url = $this->api_url . ltrim($endpoint, '/');
        
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $headers = [
            'User-Agent: ' . $this->user_agent,
            'Accept: application/vnd.github.v3+json'
        ];
        
        if (!empty($this->token)) {
            $headers[] = 'Authorization: token ' . $this->token;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($body) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error || ($http_code < 200 || $http_code >= 300)) {
            return false;
        }
        
        $data = json_decode($response, true);
        
        if ($data === null) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * Search repositories
     * 
     * @param string $query Search query
     * @param array $options Search options
     * @return array|false
     */
    public function searchRepositories($query, $options = []) {
        $per_page = min($options['per_page'] ?? 10, 100);
        $page = $options['page'] ?? 1;
        $sort = $options['sort'] ?? 'stars'; // stars, forks, updated
        $order = $options['order'] ?? 'desc';
        
        $params = [
            'q' => $query,
            'sort' => $sort,
            'order' => $order,
            'per_page' => $per_page,
            'page' => $page
        ];
        
        $data = $this->make_request('search/repositories', $params);
        
        if ($data === false || !isset($data['items'])) {
            return false;
        }
        
        $repositories = [];
        foreach ($data['items'] as $repo) {
            $repositories[] = $this->parseRepository($repo);
        }
        
        return [
            'success' => true,
            'query' => $query,
            'total_count' => $data['total_count'],
            'repositories' => $repositories
        ];
    }
    
    /**
     * Get single repository
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @return array|false
     */
    public function getRepository($owner, $repo) {
        $data = $this->make_request("repos/{$owner}/{$repo}");
        
        if ($data === false) {
            return false;
        }
        
        return [
            'success' => true,
            'repository' => $this->parseRepository($data)
        ];
    }
    
    /**
     * Get user information
     * 
     * @param string $username GitHub username
     * @return array|false
     */
    public function getUser($username) {
        $data = $this->make_request("users/{$username}");
        
        if ($data === false) {
            return false;
        }
        
        return [
            'success' => true,
            'user' => [
                'login' => $data['login'],
                'id' => $data['id'],
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'bio' => $data['bio'] ?? null,
                'company' => $data['company'] ?? null,
                'blog' => $data['blog'] ?? null,
                'location' => $data['location'] ?? null,
                'avatar_url' => $data['avatar_url'],
                'url' => $data['html_url'],
                'public_repos' => $data['public_repos'],
                'followers' => $data['followers'],
                'following' => $data['following'],
                'created_at' => $data['created_at']
            ]
        ];
    }
    
    /**
     * Get user repositories
     * 
     * @param string $username GitHub username
     * @param array $options Options
     * @return array|false
     */
    public function getUserRepositories($username, $options = []) {
        $per_page = min($options['per_page'] ?? 10, 100);
        $page = $options['page'] ?? 1;
        $type = $options['type'] ?? 'owner'; // owner, all, member
        
        $params = [
            'type' => $type,
            'per_page' => $per_page,
            'page' => $page
        ];
        
        $data = $this->make_request("users/{$username}/repos", $params);
        
        if ($data === false || !is_array($data)) {
            return false;
        }
        
        $repositories = [];
        foreach ($data as $repo) {
            $repositories[] = $this->parseRepository($repo);
        }
        
        return [
            'success' => true,
            'username' => $username,
            'repositories' => $repositories
        ];
    }
    
    /**
     * Get repository contents
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $path File or directory path
     * @param string|null $ref Branch or commit SHA
     * @return array|false
     */
    public function getContent($owner, $repo, $path = '', $ref = null) {
        $endpoint = "repos/{$owner}/{$repo}/contents/" . ltrim($path, '/');
        
        if ($ref) {
            $endpoint .= '?ref=' . urlencode($ref);
        }
        
        $data = $this->make_request($endpoint);
        
        if ($data === false) {
            return false;
        }
        
        // Single file or directory?
        if (isset($data['type'])) {
            // Single file
            $content = $this->parseContent($data);
            return [
                'success' => true,
                'content' => $content
            ];
        } elseif (is_array($data)) {
            // Directory
            $contents = [];
            foreach ($data as $item) {
                $contents[] = $this->parseContent($item);
            }
            return [
                'success' => true,
                'contents' => $contents
            ];
        }
        
        return false;
    }
    
    /**
     * Search issues
     * 
     * @param string $query Search query
     * @param array $options Search options
     * @return array|false
     */
    public function searchIssues($query, $options = []) {
        $per_page = min($options['per_page'] ?? 10, 100);
        $page = $options['page'] ?? 1;
        $sort = $options['sort'] ?? 'updated';
        $order = $options['order'] ?? 'desc';
        
        $params = [
            'q' => $query,
            'sort' => $sort,
            'order' => $order,
            'per_page' => $per_page,
            'page' => $page
        ];
        
        $data = $this->make_request('search/issues', $params);
        
        if ($data === false || !isset($data['items'])) {
            return false;
        }
        
        $issues = [];
        foreach ($data['items'] as $issue) {
            $issues[] = $this->parseIssue($issue);
        }
        
        return [
            'success' => true,
            'query' => $query,
            'total_count' => $data['total_count'],
            'issues' => $issues
        ];
    }
    
    /**
     * Create an issue
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $title Issue title
     * @param array $options Issue options (body, assignees, labels)
     * @return array|false
     */
    public function createIssue($owner, $repo, $title, $options = []) {
        if (empty($this->token)) {
            return false; // Requires authentication
        }
        
        $body = [
            'title' => $title,
            'body' => $options['body'] ?? ''
        ];
        
        if (!empty($options['assignees'])) {
            $body['assignees'] = (array) $options['assignees'];
        }
        
        if (!empty($options['labels'])) {
            $body['labels'] = (array) $options['labels'];
        }
        
        $data = $this->make_request("repos/{$owner}/{$repo}/issues", [], 'POST', $body);
        
        if ($data === false) {
            return false;
        }
        
        return [
            'success' => true,
            'issue' => $this->parseIssue($data)
        ];
    }
    
    /**
     * Create a gist
     * 
     * @param string $description Gist description
     * @param array $files Files ['filename.php' => ['content' => 'code']]
     * @param bool $public Whether gist is public
     * @return array|false
     */
    public function createGist($description, $files, $public = false) {
        if (empty($this->token)) {
            return false; // Requires authentication
        }
        
        $body = [
            'description' => $description,
            'public' => $public,
            'files' => []
        ];
        
        foreach ($files as $filename => $file) {
            $body['files'][$filename] = ['content' => $file['content']];
        }
        
        $data = $this->make_request('gists', [], 'POST', $body);
        
        if ($data === false) {
            return false;
        }
        
        return [
            'success' => true,
            'gist' => [
                'id' => $data['id'],
                'url' => $data['html_url'],
                'description' => $data['description'],
                'public' => $data['public'],
                'created_at' => $data['created_at']
            ]
        ];
    }
    
    /**
     * Parse repository data
     * 
     * @param array $data Repository data
     * @return array
     */
    private function parseRepository($data) {
        return [
            'id' => $data['id'],
            'name' => $data['name'],
            'full_name' => $data['full_name'],
            'description' => $data['description'] ?? null,
            'url' => $data['html_url'],
            'clone_url' => $data['clone_url'],
            'language' => $data['language'] ?? null,
            'stars' => $data['stargazers_count'],
            'forks' => $data['forks_count'],
            'open_issues' => $data['open_issues_count'],
            'created_at' => $data['created_at'],
            'updated_at' => $data['updated_at'],
            'owner' => [
                'login' => $data['owner']['login'],
                'avatar_url' => $data['owner']['avatar_url']
            ],
            'license' => $data['license']['name'] ?? null,
            'topics' => $data['topics'] ?? []
        ];
    }
    
    /**
     * Parse content data
     * 
     * @param array $data Content data
     * @return array
     */
    private function parseContent($data) {
        $content = [
            'name' => $data['name'],
            'path' => $data['path'],
            'type' => $data['type'],
            'size' => $data['size'],
            'url' => $data['html_url'],
            'download_url' => $data['download_url'] ?? null,
            'sha' => $data['sha']
        ];
        
        // Decode file content if present
        if ($data['type'] === 'file' && isset($data['content'])) {
            $content['content'] = base64_decode($data['content']);
        }
        
        return $content;
    }
    
    /**
     * Parse issue data
     * 
     * @param array $data Issue data
     * @return array
     */
    private function parseIssue($data) {
        return [
            'id' => $data['id'],
            'number' => $data['number'],
            'title' => $data['title'],
            'state' => $data['state'],
            'body' => $data['body'] ?? null,
            'user' => $data['user']['login'],
            'labels' => array_column($data['labels'], 'name'),
            'comments' => $data['comments'],
            'created_at' => $data['created_at'],
            'updated_at' => $data['updated_at'],
            'url' => $data['html_url']
        ];
    }
    
    /**
     * Extract text from response
     * 
     * @param array $response API response
     * @return string
     */
    public function extractText($response) {
        if (isset($response['repositories'])) {
            $texts = [];
            foreach ($response['repositories'] as $repo) {
                $texts[] = $repo['full_name'] . ': ' . ($repo['description'] ?? 'No description') . ' - ' . $repo['stars'] . ' stars';
            }
            return implode("\n", $texts);
        }
        
        if (isset($response['issues'])) {
            $texts = [];
            foreach ($response['issues'] as $issue) {
                $texts[] = '#' . $issue['number'] . ' - ' . $issue['title'] . ' [' . $issue['state'] . ']';
            }
            return implode("\n", $texts);
        }
        
        return '';
    }
}