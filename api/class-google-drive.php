<?php
/**
 * Google Drive API Handler for Masal Panel
 * 
 * @package MasalPanel\APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_GoogleDrive implements APIMaster_APIInterface {
    
    private $apiKey;
    private $accessToken;
    private $refreshToken;
    private $clientId;
    private $clientSecret;
    private $model = 'v3';
    private $config;
    
    private $apiUrl = 'https://www.googleapis.com/drive/v3/';
    
    public function __construct() {
        $this->config = $this->loadConfig();
        $this->clientId = $this->config['client_id'] ?? '';
        $this->clientSecret = $this->config['client_secret'] ?? '';
        $this->refreshToken = $this->config['refresh_token'] ?? '';
    }
    
    private function loadConfig() {
        $configFile = dirname(__DIR__) . '/config/google-drive.json';
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
    
    private function curlRequest($url, $method = 'GET', $data = null, $headers = [], $isMultipart = false) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        if ($data !== null) {
            if ($isMultipart) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
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
            $this->logError('HTTP error: ' . $httpCode . ' - ' . $response);
            return false;
        }
        
        if (empty($response)) {
            return true;
        }
        
        return json_decode($response, true);
    }
    
    private function refreshAccessToken() {
        if (empty($this->refreshToken) || empty($this->clientId) || empty($this->clientSecret)) {
            return false;
        }
        
        $url = 'https://oauth2.googleapis.com/token';
        
        $data = [
            'refresh_token' => $this->refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
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
    
    private function request($endpoint, $data = [], $method = 'GET', $multipart = false) {
        if (empty($this->accessToken)) {
            return false;
        }
        
        $url = $this->apiUrl . $endpoint;
        $headers = $this->getHeaders();
        
        if ($multipart) {
            $headers = array_filter($headers, function($h) {
                return strpos($h, 'Content-Type: application/json') === false;
            });
        }
        
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
            $data = null;
        }
        
        $response = $this->curlRequest($url, $method, $data, $headers, $multipart);
        
        if ($response === false && $method !== 'DELETE') {
            if ($this->refreshAccessToken()) {
                return $this->curlRequest($url, $method, $data, $this->getHeaders(), $multipart);
            }
        }
        
        return $response;
    }
    
    private function logError($message) {
        $logFile = dirname(__DIR__) . '/logs/google-drive-error.log';
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
        return ['error' => 'Google Drive does not support text completion'];
    }
    
    public function stream($prompt, $callback, $params = []) {
        return false;
    }
    
    public function getModels() {
        return [
            ['id' => 'v3', 'name' => 'Drive API v3', 'description' => 'Google Drive API version 3']
        ];
    }
    
    public function getCapabilities() {
        return [
            'list_files' => true,
            'upload_file' => true,
            'download_file' => true,
            'create_folder' => true,
            'delete_file' => true,
            'share_file' => true,
            'search' => true,
            'export' => true,
            'permissions' => true
        ];
    }
    
    public function checkHealth() {
        $result = $this->request('about', ['fields' => 'user'], 'GET');
        return $result !== false;
    }
    
    public function chat($messages, $params = []) {
        return ['error' => 'Google Drive does not support chat functionality'];
    }
    
    public function extractText($filePath) {
        if (!file_exists($filePath)) {
            return false;
        }
        
        $content = file_get_contents($filePath);
        
        // For text files, return content
        $mime = mime_content_type($filePath);
        if (strpos($mime, 'text/') === 0) {
            return $content;
        }
        
        // For Google Docs, would need export
        return 'Extraction only supported for text files';
    }
    
    // ========== GOOGLE DRIVE SPECIFIC METHODS ==========
    
    public function setAccessToken($token) {
        $this->accessToken = $token;
        return $this;
    }
    
    public function setCredentials($clientId, $clientSecret, $refreshToken = '') {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->refreshToken = $refreshToken;
        return $this;
    }
    
    public function listFiles($query = '', $pageSize = 100, $pageToken = '', $orderBy = 'name asc', $fields = []) {
        $params = [
            'pageSize' => min($pageSize, 1000),
            'orderBy' => $orderBy
        ];
        
        if ($query) {
            $params['q'] = $query;
        }
        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }
        if (!empty($fields)) {
            $params['fields'] = 'files(' . implode(',', $fields) . '), nextPageToken';
        }
        
        return $this->request('files', $params, 'GET');
    }
    
    public function getFile($fileId, $fields = []) {
        $params = [];
        if (!empty($fields)) {
            $params['fields'] = implode(',', $fields);
        }
        return $this->request("files/{$fileId}", $params, 'GET');
    }
    
    public function uploadFile($filePath, $name = '', $parentId = 'root', $metadata = []) {
        if (!file_exists($filePath)) {
            $this->logError('File not found: ' . $filePath);
            return false;
        }
        
        $name = $name ?: basename($filePath);
        $fileContent = file_get_contents($filePath);
        $mimeType = mime_content_type($filePath);
        
        $boundary = uniqid('boundary_', true);
        
        $metadataBody = array_merge([
            'name' => $name,
            'parents' => [$parentId]
        ], $metadata);
        
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= json_encode($metadataBody) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: {$mimeType}\r\n\r\n";
        $body .= $fileContent . "\r\n";
        $body .= "--{$boundary}--";
        
        $url = $this->apiUrl . 'files?uploadType=multipart';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: multipart/related; boundary=' . $boundary
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
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
    
    public function createFolder($name, $parentId = 'root') {
        return $this->request('files', [
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId]
        ], 'POST');
    }
    
    public function downloadFile($fileId, $savePath = '') {
        $fileMetadata = $this->getFile($fileId, ['name']);
        if (!$fileMetadata) {
            return false;
        }
        
        $url = "https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $this->accessToken]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            return false;
        }
        
        if (empty($savePath)) {
            $savePath = sys_get_temp_dir() . '/' . ($fileMetadata['name'] ?? 'download_' . $fileId);
        }
        
        file_put_contents($savePath, $response);
        return $savePath;
    }
    
    public function deleteFile($fileId) {
        $result = $this->request("files/{$fileId}", [], 'DELETE');
        return $result !== false;
    }
    
    public function trashFile($fileId) {
        return $this->request("files/{$fileId}", ['trashed' => true], 'PATCH');
    }
    
    public function restoreFile($fileId) {
        return $this->request("files/{$fileId}", ['trashed' => false], 'PATCH');
    }
    
    public function copyFile($fileId, $name = '', $parentId = '') {
        $metadata = [];
        if ($name) {
            $metadata['name'] = $name;
        }
        if ($parentId) {
            $metadata['parents'] = [$parentId];
        }
        return $this->request("files/{$fileId}/copy", $metadata, 'POST');
    }
    
    public function updateFileMetadata($fileId, $metadata) {
        return $this->request("files/{$fileId}", $metadata, 'PATCH');
    }
    
    public function searchFiles($query, $limit = 100) {
        return $this->listFiles($query, $limit);
    }
    
    public function getFolderContents($folderId = 'root', $limit = 100) {
        $query = "'{$folderId}' in parents and trashed = false";
        return $this->listFiles($query, $limit);
    }
    
    public function exportFile($fileId, $mimeType) {
        $url = "https://www.googleapis.com/drive/v3/files/{$fileId}/export?mimeType={$mimeType}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $this->accessToken]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            return false;
        }
        
        return $response;
    }
    
    public function createShareLink($fileId, $type = 'anyone', $role = 'reader') {
        $permission = [
            'type' => $type,
            'role' => $role
        ];
        
        if ($type === 'anyone') {
            $permission['allowFileDiscovery'] = false;
        }
        
        return $this->request("files/{$fileId}/permissions", $permission, 'POST');
    }
    
    public function getPermissions($fileId) {
        return $this->request("files/{$fileId}/permissions", [], 'GET');
    }
    
    public function deletePermission($fileId, $permissionId) {
        $result = $this->request("files/{$fileId}/permissions/{$permissionId}", [], 'DELETE');
        return $result !== false;
    }
    
    public function getAbout() {
        return $this->request('about', ['fields' => 'user,storageQuota'], 'GET');
    }
    
    public function getChanges($pageToken = '', $pageSize = 100) {
        $params = [
            'pageSize' => min($pageSize, 1000),
            'includeRemoved' => true,
            'includeItemsFromAllDrives' => true,
            'supportsAllDrives' => true
        ];
        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }
        return $this->request('changes', $params, 'GET');
    }
}