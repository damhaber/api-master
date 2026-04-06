<?php
/**
 * SendGrid API Handler for Masal Panel
 * 
 * @package MasalPanel\APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_SendGrid implements APIMaster_APIInterface {
    
    private $apiKey;
    private $model = 'v3';
    private $config;
    
    private $apiUrl = 'https://api.sendgrid.com/v3/';
    
    public function __construct() {
        $this->config = $this->loadConfig();
        $this->apiKey = $this->config['api_key'] ?? '';
    }
    
    private function loadConfig() {
        $configFile = dirname(__DIR__) . '/config/sendgrid.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
    
    private function getHeaders() {
        return [
            'Authorization: Bearer ' . $this->apiKey,
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
        $logFile = dirname(__DIR__) . '/logs/sendgrid-error.log';
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
        return ['error' => 'SendGrid does not support text completion'];
    }
    
    public function stream($prompt, $callback, $params = []) {
        return false;
    }
    
    public function getModels() {
        return [
            ['id' => 'v3', 'name' => 'SendGrid API v3', 'description' => 'SendGrid Email API']
        ];
    }
    
    public function getCapabilities() {
        return [
            'send_email' => true,
            'send_template_email' => true,
            'marketing_contacts' => true,
            'lists' => true,
            'templates' => true,
            'stats' => true,
            'suppression' => true,
            'senders' => true,
            'api_keys' => true
        ];
    }
    
    public function checkHealth() {
        $result = $this->request('user/profile', [], 'GET');
        return $result !== false;
    }
    
    public function chat($messages, $params = []) {
        return ['error' => 'SendGrid does not support chat functionality'];
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
    
    // ========== SENDGRID SPECIFIC METHODS ==========
    
    public function sendEmail($from, $to, $subject, $content, $options = []) {
        $personalization = [
            'to' => is_array($to) ? $to : [['email' => $to]]
        ];
        
        if (isset($options['cc'])) {
            $personalization['cc'] = is_array($options['cc']) ? $options['cc'] : [['email' => $options['cc']]];
        }
        
        if (isset($options['bcc'])) {
            $personalization['bcc'] = is_array($options['bcc']) ? $options['bcc'] : [['email' => $options['bcc']]];
        }
        
        if (isset($options['substitutions'])) {
            $personalization['substitutions'] = $options['substitutions'];
        }
        
        if (isset($options['dynamic_template_data'])) {
            $personalization['dynamic_template_data'] = $options['dynamic_template_data'];
        }
        
        $emailData = [
            'personalizations' => [$personalization],
            'from' => is_array($from) ? $from : ['email' => $from],
            'subject' => $subject
        ];
        
        $contentType = $options['content_type'] ?? 'html';
        
        if ($contentType === 'html') {
            $emailData['content'] = [
                ['type' => 'text/html', 'value' => $content]
            ];
        } else {
            $emailData['content'] = [
                ['type' => 'text/plain', 'value' => $content]
            ];
        }
        
        if (isset($options['reply_to'])) {
            $emailData['reply_to'] = is_array($options['reply_to']) ? $options['reply_to'] : ['email' => $options['reply_to']];
        }
        
        if (isset($options['attachments']) && !empty($options['attachments'])) {
            $emailData['attachments'] = [];
            foreach ($options['attachments'] as $attachment) {
                if (file_exists($attachment['path'])) {
                    $emailData['attachments'][] = [
                        'content' => base64_encode(file_get_contents($attachment['path'])),
                        'filename' => $attachment['filename'],
                        'type' => $attachment['type'] ?? mime_content_type($attachment['path']),
                        'disposition' => $attachment['disposition'] ?? 'attachment'
                    ];
                }
            }
        }
        
        if (isset($options['template_id'])) {
            $emailData['template_id'] = $options['template_id'];
            unset($emailData['content']);
        }
        
        if (isset($options['categories'])) {
            $emailData['categories'] = $options['categories'];
        }
        
        if (isset($options['custom_args'])) {
            $emailData['custom_args'] = $options['custom_args'];
        }
        
        if (isset($options['send_at'])) {
            $emailData['send_at'] = $options['send_at'];
        }
        
        if (isset($options['ip_pool_name'])) {
            $emailData['ip_pool_name'] = $options['ip_pool_name'];
        }
        
        return $this->request('mail/send', $emailData, 'POST');
    }
    
    public function sendTemplateEmail($templateId, $from, $to, $dynamicData = [], $options = []) {
        $options['template_id'] = $templateId;
        $options['dynamic_template_data'] = $dynamicData;
        
        return $this->sendEmail($from, $to, '', '', $options);
    }
    
    public function addContact($email, $customFields = [], $lists = []) {
        $contact = ['email' => $email];
        
        if (!empty($customFields)) {
            $contact['custom_fields'] = $customFields;
        }
        
        $data = ['contacts' => [$contact]];
        
        if (!empty($lists)) {
            $data['list_ids'] = $lists;
        }
        
        return $this->request('marketing/contacts', $data, 'PUT');
    }
    
    public function addContacts($contacts, $listIds = []) {
        $data = ['contacts' => $contacts];
        
        if (!empty($listIds)) {
            $data['list_ids'] = $listIds;
        }
        
        return $this->request('marketing/contacts', $data, 'PUT');
    }
    
    public function getContact($email) {
        $result = $this->request('marketing/contacts/search', [
            'query' => "email = '{$email}'"
        ], 'POST');
        
        if ($result && isset($result['result'][0])) {
            return $result['result'][0];
        }
        
        return false;
    }
    
    public function updateContact($email, $customFields = []) {
        $contact = $this->getContact($email);
        
        if (!$contact) {
            return false;
        }
        
        $contactId = $contact['id'];
        
        return $this->request("marketing/contacts/{$contactId}", [
            'custom_fields' => $customFields
        ], 'PATCH');
    }
    
    public function deleteContact($email) {
        $contact = $this->getContact($email);
        
        if (!$contact) {
            return false;
        }
        
        $result = $this->request("marketing/contacts?ids={$contact['id']}", [], 'DELETE');
        return $result !== false;
    }
    
    public function createList($name) {
        return $this->request('marketing/lists', ['name' => $name], 'POST');
    }
    
    public function getLists($pageSize = 50, $page = 1) {
        return $this->request('marketing/lists', [
            'page_size' => min($pageSize, 100),
            'page' => $page
        ], 'GET');
    }
    
    public function addToList($listId, $email) {
        $contact = $this->getContact($email);
        
        if (!$contact) {
            return false;
        }
        
        $result = $this->request("marketing/lists/{$listId}/contacts", [
            'contact_ids' => [$contact['id']]
        ], 'POST');
        
        return $result !== false;
    }
    
    public function removeFromList($listId, $email) {
        $contact = $this->getContact($email);
        
        if (!$contact) {
            return false;
        }
        
        $result = $this->request("marketing/lists/{$listId}/contacts", [
            'contact_ids' => [$contact['id']]
        ], 'DELETE');
        
        return $result !== false;
    }
    
    public function createTemplate($name, $subject, $htmlContent = '', $plainContent = '') {
        return $this->request('templates', ['name' => $name], 'POST');
    }
    
    public function createTemplateVersion($templateId, $name, $subject, $htmlContent = '', $plainContent = '') {
        $data = [
            'name' => $name,
            'subject' => $subject,
            'active' => 1
        ];
        
        if ($htmlContent) {
            $data['html_content'] = $htmlContent;
        }
        
        if ($plainContent) {
            $data['plain_content'] = $plainContent;
        }
        
        return $this->request("templates/{$templateId}/versions", $data, 'POST');
    }
    
    public function getTemplates($pageSize = 50, $page = 1) {
        return $this->request('templates', [
            'page_size' => min($pageSize, 100),
            'page' => $page
        ], 'GET');
    }
    
    public function getTemplate($templateId) {
        return $this->request("templates/{$templateId}", [], 'GET');
    }
    
    public function deleteTemplate($templateId) {
        $result = $this->request("templates/{$templateId}", [], 'DELETE');
        return $result !== false;
    }
    
    public function getSenders() {
        return $this->request('verified_senders', [], 'GET');
    }
    
    public function createSender($email, $name, $address = '', $city = '', $state = '', $zip = '', $country = '') {
        $data = ['email' => $email, 'name' => $name];
        
        if ($address) {
            $data['address'] = $address;
            $data['city'] = $city;
            $data['state'] = $state;
            $data['zip'] = $zip;
            $data['country'] = $country;
        }
        
        return $this->request('verified_senders', $data, 'POST');
    }
    
    public function getStats($startDate, $endDate = '', $aggregatedBy = []) {
        $params = ['start_date' => $startDate];
        
        if ($endDate) {
            $params['end_date'] = $endDate;
        }
        
        if (!empty($aggregatedBy)) {
            $params['aggregated_by'] = implode(',', $aggregatedBy);
        }
        
        return $this->request('stats', $params, 'GET');
    }
    
    public function getGlobalStats($startDate, $endDate = '') {
        $params = ['start_date' => $startDate];
        
        if ($endDate) {
            $params['end_date'] = $endDate;
        }
        
        return $this->request('stats/global', $params, 'GET');
    }
    
    public function getCategoryStats($category, $startDate, $endDate = '') {
        $params = [
            'category' => $category,
            'start_date' => $startDate
        ];
        
        if ($endDate) {
            $params['end_date'] = $endDate;
        }
        
        return $this->request('categories/stats', $params, 'GET');
    }
    
    public function getBounces($limit = 100, $offset = 0) {
        return $this->request('suppression/bounces', [
            'limit' => min($limit, 1000),
            'offset' => $offset
        ], 'GET');
    }
    
    public function removeBounce($email) {
        $result = $this->request('suppression/bounces', ['emails' => [$email]], 'DELETE');
        return $result !== false;
    }
    
    public function getBlocks($limit = 100, $offset = 0) {
        return $this->request('suppression/blocks', [
            'limit' => min($limit, 1000),
            'offset' => $offset
        ], 'GET');
    }
    
    public function removeBlock($email) {
        $result = $this->request('suppression/blocks', ['emails' => [$email]], 'DELETE');
        return $result !== false;
    }
    
    public function getSpamReports($limit = 100, $offset = 0) {
        return $this->request('suppression/spam_reports', [
            'limit' => min($limit, 1000),
            'offset' => $offset
        ], 'GET');
    }
    
    public function removeSpamReport($email) {
        $result = $this->request('suppression/spam_reports', ['emails' => [$email]], 'DELETE');
        return $result !== false;
    }
    
    public function getUnsubscribes($limit = 100, $offset = 0) {
        return $this->request('suppression/unsubscribes', [
            'limit' => min($limit, 1000),
            'offset' => $offset
        ], 'GET');
    }
    
    public function createApiKey($name, $scopes = []) {
        $data = ['name' => $name];
        
        if (!empty($scopes)) {
            $data['scopes'] = $scopes;
        }
        
        return $this->request('api_keys', $data, 'POST');
    }
    
    public function getApiKeys() {
        return $this->request('api_keys', [], 'GET');
    }
    
    public function deleteApiKey($keyId) {
        $result = $this->request("api_keys/{$keyId}", [], 'DELETE');
        return $result !== false;
    }
}