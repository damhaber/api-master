<?php
/**
 * Zoom API Handler for Masal Panel
 * 
 * Video conferencing, webinar, meeting management
 * 
 * @package MasalPanel\APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Zoom implements APIMaster_APIInterface {
    
    private $apiKey;
    private $apiSecret;
    private $accountId;
    private $clientId;
    private $clientSecret;
    private $accessToken;
    private $model = 'v2';
    private $config;
    
    private $apiUrl = 'https://api.zoom.us/v2';
    private $oauthUrl = 'https://zoom.us/oauth/token';
    
    public function __construct() {
        $this->config = $this->loadConfig();
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->apiSecret = $this->config['api_secret'] ?? '';
        $this->accountId = $this->config['account_id'] ?? '';
        $this->clientId = $this->config['client_id'] ?? '';
        $this->clientSecret = $this->config['client_secret'] ?? '';
        
        $this->initAuth();
    }
    
    private function loadConfig() {
        $configFile = dirname(__DIR__) . '/config/zoom.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
    
    private function initAuth() {
        if ($this->clientId && $this->clientSecret && $this->accountId) {
            $this->getOAuthToken();
        }
    }
    
    private function getOAuthToken() {
        if (empty($this->clientId) || empty($this->clientSecret) || empty($this->accountId)) {
            return false;
        }
        
        $auth = base64_encode($this->clientId . ':' . $this->clientSecret);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->oauthUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'account_credentials',
            'account_id' => $this->accountId
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $body = json_decode($response, true);
            if (isset($body['access_token'])) {
                $this->accessToken = $body['access_token'];
                return true;
            }
        }
        
        return false;
    }
    
    private function getHeaders() {
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        
        if ($this->accessToken) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }
        
        return $headers;
    }
    
    private function curlRequest($url, $method = 'GET', $data = null) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());
        
        if ($data !== null && ($method === 'POST' || $method === 'PATCH' || $method === 'PUT')) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->logError('cURL error: ' . $error);
            return false;
        }
        
        if ($httpCode >= 400) {
            $this->logError('HTTP error: ' . $httpCode . ' - ' . substr($response, 0, 500));
            
            // Token expired, try to refresh
            if ($httpCode === 401 && $this->accessToken) {
                if ($this->getOAuthToken()) {
                    return $this->curlRequest($url, $method, $data);
                }
            }
            
            return false;
        }
        
        if (empty($response)) {
            return true;
        }
        
        return json_decode($response, true);
    }
    
    private function request($endpoint, $method = 'GET', $data = []) {
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
            $data = null;
        } elseif ($method === 'DELETE' && !empty($data)) {
            $url .= '?' . http_build_query($data);
            $data = null;
        }
        
        return $this->curlRequest($url, $method, $data);
    }
    
    private function logError($message) {
        $logFile = dirname(__DIR__) . '/logs/zoom-error.log';
        $date = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$date] $message" . PHP_EOL, FILE_APPEND);
    }
    
    // ========== APIInterface REQUIRED METHODS ==========
    
    public function setApiKey($apiKey) {
        $this->apiKey = $apiKey;
        return $this;
    }
    
    public function setModel($model) {
        $this->model = $model;
        return $this;
    }
    
    public function getModel() {
        return $this->model;
    }
    
    public function complete($prompt, $params = []) {
        return ['error' => 'Zoom does not support text completion'];
    }
    
    public function stream($prompt, $callback, $params = []) {
        return false;
    }
    
    public function getModels() {
        return [
            ['id' => 'v2', 'name' => 'Zoom API v2', 'description' => 'Zoom Video Conferencing API']
        ];
    }
    
    public function getCapabilities() {
        return [
            'meetings' => true,
            'webinars' => true,
            'recordings' => true,
            'users' => true,
            'reports' => true,
            'registrations' => true
        ];
    }
    
    public function checkHealth() {
        $result = $this->request('users/me', 'GET');
        return $result !== false && isset($result['id']);
    }
    
    public function chat($messages, $params = []) {
        return ['error' => 'Zoom does not support chat functionality, use meetings instead'];
    }
    
    public function extractText($filePath) {
        if (!file_exists($filePath)) {
            return false;
        }
        
        $content = file_get_contents($filePath);
        $mime = mime_content_type($filePath);
        
        if (strpos($mime, 'text/') === 0) {
            return $content;
        }
        
        return 'Extraction only supported for text files';
    }
    
    // ========== ZOOM SPECIFIC METHODS ==========
    
    public function setCredentials($clientId, $clientSecret, $accountId) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->accountId = $accountId;
        $this->getOAuthToken();
        return $this;
    }
    
    public function createMeeting($meetingData) {
        $requiredFields = ['topic', 'start_time', 'duration'];
        
        foreach ($requiredFields as $field) {
            if (empty($meetingData[$field])) {
                $this->logError('Missing required field: ' . $field);
                return false;
            }
        }
        
        $data = [
            'topic' => $meetingData['topic'],
            'type' => $meetingData['type'] ?? 2,
            'start_time' => $meetingData['start_time'],
            'duration' => $meetingData['duration'],
            'timezone' => $meetingData['timezone'] ?? 'UTC',
            'agenda' => $meetingData['agenda'] ?? '',
            'settings' => [
                'host_video' => $meetingData['host_video'] ?? true,
                'participant_video' => $meetingData['participant_video'] ?? true,
                'join_before_host' => $meetingData['join_before_host'] ?? false,
                'mute_upon_entry' => $meetingData['mute_upon_entry'] ?? false,
                'auto_recording' => $meetingData['auto_recording'] ?? 'none',
                'alternative_hosts' => $meetingData['alternative_hosts'] ?? ''
            ]
        ];
        
        if (isset($meetingData['password'])) {
            $data['password'] = $meetingData['password'];
        }
        
        $data = array_filter($data);
        $data['settings'] = array_filter($data['settings']);
        
        return $this->request('users/me/meetings', 'POST', $data);
    }
    
    public function getMeeting($meetingId) {
        return $this->request('meetings/' . $meetingId, 'GET');
    }
    
    public function updateMeeting($meetingId, $updateData) {
        $result = $this->request('meetings/' . $meetingId, 'PATCH', $updateData);
        return $result !== false;
    }
    
    public function deleteMeeting($meetingId, $scheduleForDeletion = false) {
        $params = [];
        if ($scheduleForDeletion) {
            $params['schedule_for_deletion'] = 'true';
        }
        
        $result = $this->request('meetings/' . $meetingId, 'DELETE', $params);
        return $result !== false;
    }
    
    public function createWebinar($webinarData) {
        $requiredFields = ['topic', 'start_time', 'duration'];
        
        foreach ($requiredFields as $field) {
            if (empty($webinarData[$field])) {
                $this->logError('Missing required field: ' . $field);
                return false;
            }
        }
        
        $data = [
            'topic' => $webinarData['topic'],
            'type' => $webinarData['type'] ?? 5,
            'start_time' => $webinarData['start_time'],
            'duration' => $webinarData['duration'],
            'timezone' => $webinarData['timezone'] ?? 'UTC',
            'agenda' => $webinarData['agenda'] ?? '',
            'settings' => [
                'host_video' => $webinarData['host_video'] ?? true,
                'panelists_video' => $webinarData['panelists_video'] ?? true,
                'practice_session' => $webinarData['practice_session'] ?? false,
                'auto_recording' => $webinarData['auto_recording'] ?? 'none'
            ]
        ];
        
        if (isset($webinarData['password'])) {
            $data['password'] = $webinarData['password'];
        }
        
        $data = array_filter($data);
        $data['settings'] = array_filter($data['settings']);
        
        return $this->request('users/me/webinars', 'POST', $data);
    }
    
    public function addWebinarRegistrant($webinarId, $registrantData) {
        $requiredFields = ['email', 'first_name', 'last_name'];
        
        foreach ($requiredFields as $field) {
            if (empty($registrantData[$field])) {
                $this->logError('Missing required field: ' . $field);
                return false;
            }
        }
        
        $data = [
            'email' => $registrantData['email'],
            'first_name' => $registrantData['first_name'],
            'last_name' => $registrantData['last_name']
        ];
        
        if (isset($registrantData['address'])) {
            $data['address'] = $registrantData['address'];
        }
        if (isset($registrantData['city'])) {
            $data['city'] = $registrantData['city'];
        }
        if (isset($registrantData['country'])) {
            $data['country'] = $registrantData['country'];
        }
        if (isset($registrantData['phone'])) {
            $data['phone'] = $registrantData['phone'];
        }
        if (isset($registrantData['comments'])) {
            $data['comments'] = $registrantData['comments'];
        }
        
        return $this->request('webinars/' . $webinarId . '/registrants', 'POST', $data);
    }
    
    public function createUser($userData) {
        $requiredFields = ['email', 'first_name', 'last_name'];
        
        foreach ($requiredFields as $field) {
            if (empty($userData[$field])) {
                $this->logError('Missing required field: ' . $field);
                return false;
            }
        }
        
        $data = [
            'action' => 'create',
            'user_info' => [
                'email' => $userData['email'],
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'type' => $userData['type'] ?? 1,
                'timezone' => $userData['timezone'] ?? 'UTC'
            ]
        ];
        
        if (isset($userData['password'])) {
            $data['user_info']['password'] = $userData['password'];
        }
        
        if (isset($userData['phone_number'])) {
            $data['user_info']['phone_number'] = $userData['phone_number'];
        }
        
        return $this->request('users', 'POST', $data);
    }
    
    public function listUsers($filters = []) {
        return $this->request('users', 'GET', $filters);
    }
    
    public function listRecordings($meetingId) {
        return $this->request('meetings/' . $meetingId . '/recordings', 'GET');
    }
    
    public function getReport($type, $params = []) {
        $endpoints = [
            'meetings' => 'report/meetings',
            'users' => 'report/users',
            'webinars' => 'report/webinars'
        ];
        
        $endpoint = $endpoints[$type] ?? 'report/meetings';
        
        return $this->request($endpoint, 'GET', $params);
    }
    
    public function getJoinLink($meetingId, $password = null) {
        $link = 'https://zoom.us/j/' . $meetingId;
        
        if ($password) {
            $link .= '?pwd=' . urlencode($password);
        }
        
        return $link;
    }
    
    public function getCurrentUser() {
        return $this->request('users/me', 'GET');
    }
}