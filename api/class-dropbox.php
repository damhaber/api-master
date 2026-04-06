<?php
/**
 * Dropbox API Handler for Masal Panel
 * 
 * @package MasalPanel\APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Dropbox implements APIMaster_APIInterface {
    
    private $apiKey;
    private $accessToken;
    private $refreshToken;
    private $appKey;
    private $appSecret;
    private $model = 'v2';
    private $config;
    
    private $apiUrl = 'https://api.dropboxapi.com/2/';
    private $contentUrl = 'https://content.dropboxapi.com/2/';
    
    public function __construct() {
        $this->config = $this->loadConfig();
        $this->appKey = $this->config['app_key'] ?? '';
        $this->appSecret = $this->config['app_secret'] ?? '';
        $this->refreshToken = $this->config['refresh_token'] ?? '';
    }
    
    private function loadConfig() {
        $configFile = dirname(__DIR__) . '/config/dropbox.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
    
    private function getHeaders() {
        return [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ];
    }
    
    private function getUploadHeaders($args) {
        return [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/octet-stream',
            'Dropbox-API-Arg: ' . json_encode($args)
        ];
    }
    
    private function getDownloadHeaders($args) {
        return [
            'Authorization: Bearer ' . $this->accessToken,
            'Dropbox-API-Arg: ' . json_encode($args)
        ];
    }
    
    private function curlRequest($url, $method = 'POST', $data = null, $headers = [], $isBinary = false) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
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
            return false;
        }
        
        if ($isBinary || empty($response)) {
            return $response;
        }
        
        return json_decode($response, true);
    }
    
    private function refreshAccessToken() {
        if (empty($this->refreshToken) || empty($this->appKey) || empty($this->appSecret)) {
            return false;
        }
        
        $url = 'https://api.dropboxapi.com/oauth2/token';
        
        $data = http_build_query([
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token',
            'client_id' => $this->appKey,
            'client_secret' => $this->appSecret
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
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
    
    private function request($endpoint, $data = [], $method = 'POST', $isContent = false) {
        if (empty($this->accessToken)) {
            return false;
        }
        
        $baseUrl = $isContent ? $this->contentUrl : $this->apiUrl;
        $url = $baseUrl . $endpoint;
        
        $response = $this->curlRequest($url, $method, json_encode($data), $this->getHeaders());
        
        if ($response === false) {
            if ($this->refreshAccessToken()) {
                return $this->curlRequest($url, $method, json_encode($data), $this->getHeaders());
            }
        }
        
        return $response;
    }
    
    private function logError($message) {
        $logFile = dirname(__DIR__) . '/logs/dropbox-error.log';
        $date = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$date] $message" . PHP_EOL, FILE_APPEND);
    }
    
    // ========== APIInterface REQUIRED METHODS ==========
    
    public function setApiKey($apiKey) {
        $this->apiKey = $apiKey;
        $this->accessToken = $apiKey;
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
        return ['error' => 'Dropbox does not support text completion'];
    }
    
    public function stream($prompt, $callback, $params = []) {
        return false;
    }
    
    public function getModels() {
        return [
            ['id' => 'v2', 'name' => 'Dropbox API v2', 'description' => 'Dropbox API version 2']
        ];
    }
    
    public function getCapabilities() {
        return [
            'list_folder' => true,
            'upload_file' => true,
            'download_file' => true,
            'create_folder' => true,
            'delete' => true,
            'move_copy' => true,
            'search' => true,
            'shared_links' => true,
            'thumbnails' => true,
            'preview' => true,
            'revisions' => true,
            'space_usage' => true
        ];
    }
    
    public function checkHealth() {
        $result = $this->getCurrentAccount();
        return $result !== false;
    }
    
    public function chat($messages, $params = []) {
        return ['error' => 'Dropbox does not support chat functionality'];
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
    
    // ========== DROPBOX SPECIFIC METHODS ==========
    
    public function setAccessToken($token) {
        $this->accessToken = $token;
        return $this;
    }
    
    public function setAppCredentials($appKey, $appSecret, $refreshToken = '') {
        $this->appKey = $appKey;
        $this->appSecret = $appSecret;
        $this->refreshToken = $refreshToken;
        return $this;
    }
    
    public function getCurrentAccount() {
        return $this->request('users/get_current_account', [], 'POST');
    }
    
    public function listFolder($path = '', $recursive = false, $includeMediaInfo = false, $includeDeleted = false, $limit = 2000) {
        return $this->request('files/list_folder', [
            'path' => $path ?: '',
            'recursive' => $recursive,
            'include_media_info' => $includeMediaInfo,
            'include_deleted' => $includeDeleted,
            'limit' => min($limit, 2000)
        ], 'POST');
    }
    
    public function listFolderContinue($cursor) {
        return $this->request('files/list_folder/continue', ['cursor' => $cursor], 'POST');
    }
    
    public function getMetadata($path, $includeMediaInfo = false, $includeDeleted = false) {
        return $this->request('files/get_metadata', [
            'path' => $path,
            'include_media_info' => $includeMediaInfo,
            'include_deleted' => $includeDeleted
        ], 'POST');
    }
    
    public function uploadFile($filePath, $dropboxPath, $mode = 'overwrite', $autorename = false, $mute = false) {
        if (!file_exists($filePath)) {
            $this->logError('File not found: ' . $filePath);
            return false;
        }
        
        $fileContent = file_get_contents($filePath);
        
        $args = [
            'path' => $dropboxPath,
            'mode' => $mode,
            'autorename' => $autorename,
            'mute' => $mute
        ];
        
        $url = $this->contentUrl . 'files/upload';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getUploadHeaders($args));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            $this->logError('Upload failed: HTTP ' . $httpCode);
            return false;
        }
        
        return json_decode($response, true);
    }
    
    public function downloadFile($dropboxPath, $savePath = '') {
        $url = $this->contentUrl . 'files/download';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getDownloadHeaders(['path' => $dropboxPath]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            $this->logError('Download failed: HTTP ' . $httpCode);
            return false;
        }
        
        if ($savePath) {
            file_put_contents($savePath, $response);
            return $savePath;
        }
        
        return $response;
    }
    
    public function createFolder($path, $autorename = false) {
        return $this->request('files/create_folder_v2', [
            'path' => $path,
            'autorename' => $autorename
        ], 'POST');
    }
    
    public function delete($path) {
        return $this->request('files/delete_v2', ['path' => $path], 'POST');
    }
    
    public function move($fromPath, $toPath, $autorename = false, $allowSharedFolder = false) {
        return $this->request('files/move_v2', [
            'from_path' => $fromPath,
            'to_path' => $toPath,
            'autorename' => $autorename,
            'allow_shared_folder' => $allowSharedFolder
        ], 'POST');
    }
    
    public function copy($fromPath, $toPath, $autorename = false, $allowSharedFolder = false) {
        return $this->request('files/copy_v2', [
            'from_path' => $fromPath,
            'to_path' => $toPath,
            'autorename' => $autorename,
            'allow_shared_folder' => $allowSharedFolder
        ], 'POST');
    }
    
    public function search($query, $path = '', $maxResults = 100, $fileStatus = ['active'], $fileExtensions = []) {
        $options = [
            'path' => $path ?: '',
            'max_results' => min($maxResults, 1000),
            'file_status' => $fileStatus
        ];
        
        if (!empty($fileExtensions)) {
            $options['file_extensions'] = $fileExtensions;
        }
        
        return $this->request('files/search_v2', [
            'query' => $query,
            'options' => $options
        ], 'POST');
    }
    
    public function getTemporaryLink($path) {
        return $this->request('files/get_temporary_link', ['path' => $path], 'POST');
    }
    
    public function createSharedLink($path, $settings = []) {
        $data = ['path' => $path];
        if (!empty($settings)) {
            $data['settings'] = $settings;
        }
        return $this->request('sharing/create_shared_link_with_settings', $data, 'POST');
    }
    
    public function listSharedLinks($path = '', $limit = 100) {
        $data = ['limit' => min($limit, 1000)];
        if ($path) {
            $data['path'] = $path;
        }
        return $this->request('sharing/list_shared_links', $data, 'POST');
    }
    
    public function revokeSharedLink($url) {
        $result = $this->request('sharing/revoke_shared_link', ['url' => $url], 'POST');
        return $result !== false;
    }
    
    public function getPreview($path, $format = 'jpeg') {
        $url = $this->contentUrl . 'files/get_preview';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getDownloadHeaders([
            'path' => $path,
            'format' => $format
        ]));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            return false;
        }
        
        return $response;
    }
    
    public function getThumbnail($path, $size = 'w64h64', $format = 'jpeg') {
        $url = $this->contentUrl . 'files/get_thumbnail';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getDownloadHeaders([
            'path' => $path,
            'size' => $size,
            'format' => $format
        ]));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            return false;
        }
        
        return $response;
    }
    
    public function getSpaceUsage() {
        return $this->request('users/get_space_usage', [], 'POST');
    }
    
    public function restoreFile($path, $rev) {
        return $this->request('files/restore', [
            'path' => $path,
            'rev' => $rev
        ], 'POST');
    }
    
    public function getRevisions($path, $limit = 10) {
        return $this->request('files/list_revisions', [
            'path' => $path,
            'limit' => min($limit, 100)
        ], 'POST');
    }
}