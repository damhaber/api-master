<?php
/**
 * Mailchimp API Handler for Masal Panel
 * 
 * @package MasalPanel\APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Mailchimp implements APIMaster_APIInterface {
    
    private $apiKey;
    private $dc;
    private $defaultListId;
    private $model = '3.0';
    private $config;
    
    private $apiUrl = 'https://{dc}.api.mailchimp.com/3.0/';
    
    public function __construct() {
        $this->config = $this->loadConfig();
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->defaultListId = $this->config['default_list_id'] ?? '';
        
        if (!empty($this->apiKey)) {
            $parts = explode('-', $this->apiKey);
            $this->dc = end($parts);
            $this->apiUrl = str_replace('{dc}', $this->dc, $this->apiUrl);
        }
    }
    
    private function loadConfig() {
        $configFile = dirname(__DIR__) . '/config/mailchimp.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
    
    private function getHeaders() {
        return [
            'Authorization: apikey ' . $this->apiKey,
            'Content-Type: application/json'
        ];
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
        
        if ($data !== null && ($method === 'POST' || $method === 'PUT' || $method === 'PATCH')) {
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
            return false;
        }
        
        if (empty($response)) {
            return true;
        }
        
        return json_decode($response, true);
    }
    
    private function request($endpoint, $data = [], $method = 'GET') {
        if (empty($this->apiKey)) {
            return false;
        }
        
        $url = $this->apiUrl . ltrim($endpoint, '/');
        
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
            $data = null;
        }
        
        return $this->curlRequest($url, $method, $data);
    }
    
    private function logError($message) {
        $logFile = dirname(__DIR__) . '/logs/mailchimp-error.log';
        $date = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$date] $message" . PHP_EOL, FILE_APPEND);
    }
    
    // ========== APIInterface REQUIRED METHODS ==========
    
    public function setApiKey($apiKey) {
        $this->apiKey = $apiKey;
        
        $parts = explode('-', $apiKey);
        if (count($parts) > 1) {
            $this->dc = end($parts);
            $this->apiUrl = str_replace('{dc}', $this->dc, 'https://{dc}.api.mailchimp.com/3.0/');
        }
        
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
        return ['error' => 'Mailchimp does not support text completion'];
    }
    
    public function stream($prompt, $callback, $params = []) {
        return false;
    }
    
    public function getModels() {
        return [
            ['id' => '3.0', 'name' => 'Mailchimp API v3.0', 'description' => 'Mailchimp Marketing API']
        ];
    }
    
    public function getCapabilities() {
        return [
            'lists' => true,
            'subscribers' => true,
            'campaigns' => true,
            'templates' => true,
            'segments' => true,
            'tags' => true,
            'reports' => true
        ];
    }
    
    public function checkHealth() {
        $result = $this->getAccountInfo();
        return $result !== false;
    }
    
    public function chat($messages, $params = []) {
        return ['error' => 'Mailchimp does not support chat functionality'];
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
    
    // ========== MAILCHIMP SPECIFIC METHODS ==========
    
    public function setDefaultListId($listId) {
        $this->defaultListId = $listId;
        return $this;
    }
    
    public function getAccountInfo() {
        return $this->request('');
    }
    
    public function getLists($count = 100, $offset = 0, $fields = []) {
        $params = [
            'count' => min($count, 1000),
            'offset' => $offset
        ];
        
        if (!empty($fields)) {
            $params['fields'] = implode(',', $fields);
        }
        
        return $this->request('lists', $params, 'GET');
    }
    
    public function getList($listId = '') {
        $listId = $listId ?: $this->defaultListId;
        
        if (empty($listId)) {
            return false;
        }
        
        return $this->request("lists/{$listId}", [], 'GET');
    }
    
    public function createList($name, $company, $address1, $city, $state, $zip, $country, $permissions = []) {
        $data = [
            'name' => $name,
            'contact' => [
                'company' => $company,
                'address1' => $address1,
                'city' => $city,
                'state' => $state,
                'zip' => $zip,
                'country' => $country
            ],
            'permission_reminder' => $permissions['reminder'] ?? 'You signed up for updates',
            'campaign_defaults' => [
                'from_name' => $permissions['from_name'] ?? $company,
                'from_email' => $permissions['from_email'] ?? 'noreply@' . strtolower(str_replace(' ', '', $company)) . '.com',
                'subject' => $permissions['subject'] ?? 'Updates',
                'language' => $permissions['language'] ?? 'en'
            ],
            'email_type_option' => $permissions['email_type_option'] ?? true
        ];
        
        return $this->request('lists', $data, 'POST');
    }
    
    public function updateList($listId, $data) {
        $listId = $listId ?: $this->defaultListId;
        
        if (empty($listId)) {
            return false;
        }
        
        return $this->request("lists/{$listId}", $data, 'PATCH');
    }
    
    public function deleteList($listId = '') {
        $listId = $listId ?: $this->defaultListId;
        
        if (empty($listId)) {
            return false;
        }
        
        $result = $this->request("lists/{$listId}", [], 'DELETE');
        return $result !== false;
    }
    
    public function addSubscriber($email, $mergeFields = [], $tags = [], $status = 'subscribed', $listId = '') {
        $listId = $listId ?: $this->defaultListId;
        
        if (empty($listId)) {
            return false;
        }
        
        $subscriberHash = md5(strtolower($email));
        
        $data = [
            'email_address' => $email,
            'status' => $status,
            'status_if_new' => $status
        ];
        
        if (!empty($mergeFields)) {
            $data['merge_fields'] = $mergeFields;
        }
        
        if (!empty($tags)) {
            $data['tags'] = $tags;
        }
        
        return $this->request("lists/{$listId}/members/{$subscriberHash}", $data, 'PUT');
    }
    
    public function getSubscriber($email, $listId = '') {
        $listId = $listId ?: $this->defaultListId;
        
        if (empty($listId)) {
            return false;
        }
        
        $subscriberHash = md5(strtolower($email));
        return $this->request("lists/{$listId}/members/{$subscriberHash}", [], 'GET');
    }
    
    public function updateSubscriber($email, $data, $listId = '') {
        $listId = $listId ?: $this->defaultListId;
        
        if (empty($listId)) {
            return false;
        }
        
        $subscriberHash = md5(strtolower($email));
        return $this->request("lists/{$listId}/members/{$subscriberHash}", $data, 'PATCH');
    }
    
    public function deleteSubscriber($email, $listId = '') {
        $listId = $listId ?: $this->defaultListId;
        
        if (empty($listId)) {
            return false;
        }
        
        $subscriberHash = md5(strtolower($email));
        $result = $this->request("lists/{$listId}/members/{$subscriberHash}", [], 'DELETE');
        return $result !== false;
    }
    
    public function getListMembers($count = 100, $offset = 0, $status = '', $listId = '') {
        $listId = $listId ?: $this->defaultListId;
        
        if (empty($listId)) {
            return false;
        }
        
        $params = [
            'count' => min($count, 1000),
            'offset' => $offset
        ];
        
        if ($status) {
            $params['status'] = $status;
        }
        
        return $this->request("lists/{$listId}/members", $params, 'GET');
    }
    
    public function addTagsToSubscriber($email, $tags, $listId = '') {
        $listId = $listId ?: $this->defaultListId;
        
        if (empty($listId)) {
            return false;
        }
        
        $subscriberHash = md5(strtolower($email));
        
        $data = [
            'tags' => array_map(function($tag) {
                return ['name' => $tag, 'status' => 'active'];
            }, $tags)
        ];
        
        $result = $this->request("lists/{$listId}/members/{$subscriberHash}/tags", $data, 'POST');
        return $result !== false;
    }
    
    public function removeTagsFromSubscriber($email, $tags, $listId = '') {
        $listId = $listId ?: $this->defaultListId;
        
        if (empty($listId)) {
            return false;
        }
        
        $subscriberHash = md5(strtolower($email));
        
        $data = [
            'tags' => array_map(function($tag) {
                return ['name' => $tag, 'status' => 'inactive'];
            }, $tags)
        ];
        
        $result = $this->request("lists/{$listId}/members/{$subscriberHash}/tags", $data, 'POST');
        return $result !== false;
    }
    
    public function createCampaign($type, $recipients, $settings, $tracking = []) {
        $data = [
            'type' => $type,
            'recipients' => $recipients,
            'settings' => $settings
        ];
        
        if (!empty($tracking)) {
            $data['tracking'] = $tracking;
        }
        
        return $this->request('campaigns', $data, 'POST');
    }
    
    public function getCampaigns($count = 100, $offset = 0, $status = '') {
        $params = [
            'count' => min($count, 1000),
            'offset' => $offset
        ];
        
        if ($status) {
            $params['status'] = $status;
        }
        
        return $this->request('campaigns', $params, 'GET');
    }
    
    public function sendCampaign($campaignId) {
        $result = $this->request("campaigns/{$campaignId}/actions/send", [], 'POST');
        return $result !== false;
    }
    
    public function scheduleCampaign($campaignId, $scheduleTime) {
        $result = $this->request("campaigns/{$campaignId}/actions/schedule", [
            'schedule_time' => $scheduleTime
        ], 'POST');
        return $result !== false;
    }
    
    public function pauseCampaign($campaignId) {
        $result = $this->request("campaigns/{$campaignId}/actions/pause", [], 'POST');
        return $result !== false;
    }
    
    public function resumeCampaign($campaignId) {
        $result = $this->request("campaigns/{$campaignId}/actions/resume", [], 'POST');
        return $result !== false;
    }
    
    public function cancelCampaign($campaignId) {
        $result = $this->request("campaigns/{$campaignId}/actions/cancel", [], 'POST');
        return $result !== false;
    }
    
    public function getCampaignContent($campaignId) {
        return $this->request("campaigns/{$campaignId}/content", [], 'GET');
    }
    
    public function updateCampaignContent($campaignId, $content) {
        return $this->request("campaigns/{$campaignId}/content", $content, 'PUT');
    }
    
    public function getCampaignReport($campaignId) {
        return $this->request("reports/{$campaignId}", [], 'GET');
    }
    
    public function createTemplate($name, $html, $options = []) {
        $data = ['name' => $name, 'html' => $html];
        
        if (!empty($options)) {
            $data = array_merge($data, $options);
        }
        
        return $this->request('templates', $data, 'POST');
    }
    
    public function getTemplates($count = 100, $offset = 0) {
        $params = [
            'count' => min($count, 1000),
            'offset' => $offset
        ];
        
        return $this->request('templates', $params, 'GET');
    }
    
    public function deleteTemplate($templateId) {
        $result = $this->request("templates/{$templateId}", [], 'DELETE');
        return $result !== false;
    }
    
    public function getSegments($listId = '') {
        $listId = $listId ?: $this->defaultListId;
        
        if (empty($listId)) {
            return false;
        }
        
        return $this->request("lists/{$listId}/segments", [], 'GET');
    }
    
    public function createSegment($name, $conditions, $listId = '') {
        $listId = $listId ?: $this->defaultListId;
        
        if (empty($listId)) {
            return false;
        }
        
        $data = [
            'name' => $name,
            'options' => [
                'match' => 'any',
                'conditions' => $conditions
            ]
        ];
        
        return $this->request("lists/{$listId}/segments", $data, 'POST');
    }
    
    public function addToSegment($email, $segmentId, $listId = '') {
        $listId = $listId ?: $this->defaultListId;
        
        if (empty($listId)) {
            return false;
        }
        
        $subscriberHash = md5(strtolower($email));
        $result = $this->request("lists/{$listId}/segments/{$segmentId}/members/{$subscriberHash}", [], 'POST');
        return $result !== false;
    }
    
    public function removeFromSegment($email, $segmentId, $listId = '') {
        $listId = $listId ?: $this->defaultListId;
        
        if (empty($listId)) {
            return false;
        }
        
        $subscriberHash = md5(strtolower($email));
        $result = $this->request("lists/{$listId}/segments/{$segmentId}/members/{$subscriberHash}", [], 'DELETE');
        return $result !== false;
    }
}