<?php
/**
 * Twilio API Handler for Masal Panel
 * 
 * SMS, WhatsApp, Voice and Video communication services
 * 
 * @package MasalPanel\APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Twilio implements APIMaster_APIInterface {
    
    private $accountSid;
    private $authToken;
    private $phoneNumber;
    private $whatsappNumber;
    private $apiKey;
    private $apiSecret;
    private $model = '2010-04-01';
    private $config;
    
    private $apiUrl = 'https://api.twilio.com/2010-04-01';
    private $videoApiUrl = 'https://video.twilio.com/v1';
    
    public function __construct() {
        $this->config = $this->loadConfig();
        $this->accountSid = $this->config['account_sid'] ?? '';
        $this->authToken = $this->config['auth_token'] ?? '';
        $this->phoneNumber = $this->config['phone_number'] ?? '';
        $this->whatsappNumber = $this->config['whatsapp_number'] ?? '';
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->apiSecret = $this->config['api_secret'] ?? '';
    }
    
    private function loadConfig() {
        $configFile = dirname(__DIR__) . '/config/twilio.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
    
    private function getAuthHeader() {
        $auth = base64_encode($this->accountSid . ':' . $this->authToken);
        return 'Basic ' . $auth;
    }
    
    private function curlRequest($url, $method = 'GET', $data = null, $isJson = false) {
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
        
        $headers = [
            'Authorization: ' . $this->getAuthHeader(),
            'Accept: application/json'
        ];
        
        if ($isJson) {
            $headers[] = 'Content-Type: application/json';
        } else {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($data !== null) {
            if ($isJson) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
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
            $this->logError('HTTP error: ' . $httpCode . ' - ' . substr($response, 0, 500));
            return false;
        }
        
        if (empty($response)) {
            return true;
        }
        
        return json_decode($response, true);
    }
    
    private function request($endpoint, $method = 'GET', $data = [], $isJson = false) {
        if (empty($this->accountSid) || empty($this->authToken)) {
            return false;
        }
        
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
            $data = null;
        }
        
        return $this->curlRequest($url, $method, $data, $isJson);
    }
    
    private function videoRequest($endpoint, $method = 'POST', $data = []) {
        if (empty($this->accountSid) || empty($this->authToken)) {
            return false;
        }
        
        $url = $this->videoApiUrl . '/' . ltrim($endpoint, '/');
        
        return $this->curlRequest($url, $method, $data, false);
    }
    
    private function logError($message) {
        $logFile = dirname(__DIR__) . '/logs/twilio-error.log';
        $date = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$date] $message" . PHP_EOL, FILE_APPEND);
    }
    
    private function validatePhoneNumber($number) {
        // Basic phone number validation
        $cleaned = preg_replace('/[^0-9+]/', '', $number);
        return strlen($cleaned) >= 10;
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
        return ['error' => 'Twilio does not support text completion'];
    }
    
    public function stream($prompt, $callback, $params = []) {
        return false;
    }
    
    public function getModels() {
        return [
            ['id' => '2010-04-01', 'name' => 'Twilio API v2010-04-01', 'description' => 'Twilio Communication API']
        ];
    }
    
    public function getCapabilities() {
        return [
            'sms' => true,
            'whatsapp' => true,
            'voice_calls' => true,
            'video_rooms' => true,
            'media' => true,
            'messaging_service' => true
        ];
    }
    
    public function checkHealth() {
        $result = $this->request('Accounts/' . $this->accountSid, 'GET');
        return $result !== false && isset($result['friendly_name']);
    }
    
    public function chat($messages, $params = []) {
        return ['error' => 'Twilio does not support chat functionality, use WhatsApp or SMS instead'];
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
    
    // ========== TWILIO SPECIFIC METHODS ==========
    
    public function setCredentials($accountSid, $authToken, $phoneNumber = '', $whatsappNumber = '') {
        $this->accountSid = $accountSid;
        $this->authToken = $authToken;
        $this->phoneNumber = $phoneNumber;
        $this->whatsappNumber = $whatsappNumber;
        return $this;
    }
    
    public function sendSMS($to, $message, $options = []) {
        if (!$this->validatePhoneNumber($to)) {
            $this->logError('Invalid phone number: ' . $to);
            return false;
        }
        
        if (strlen($message) > 1600) {
            $this->logError('SMS message too long: ' . strlen($message));
            return false;
        }
        
        $data = [
            'To' => $to,
            'From' => $options['from'] ?? $this->phoneNumber,
            'Body' => $message
        ];
        
        if (isset($options['status_callback'])) {
            $data['StatusCallback'] = $options['status_callback'];
        }
        
        if (isset($options['validity_period'])) {
            $data['ValidityPeriod'] = $options['validity_period'];
        }
        
        $data = array_filter($data);
        
        return $this->request('Accounts/' . $this->accountSid . '/Messages', 'POST', $data);
    }
    
    public function sendWhatsApp($to, $message, $options = []) {
        if (!$this->validatePhoneNumber($to)) {
            $this->logError('Invalid phone number: ' . $to);
            return false;
        }
        
        $whatsappFrom = $this->whatsappNumber ? 'whatsapp:' . $this->whatsappNumber : 'whatsapp:' . $this->phoneNumber;
        $whatsappTo = 'whatsapp:' . $to;
        
        $data = [
            'To' => $whatsappTo,
            'From' => $whatsappFrom,
            'Body' => $message
        ];
        
        if (isset($options['media_url'])) {
            $data['MediaUrl'] = $options['media_url'];
        }
        
        if (isset($options['status_callback'])) {
            $data['StatusCallback'] = $options['status_callback'];
        }
        
        $data = array_filter($data);
        
        return $this->request('Accounts/' . $this->accountSid . '/Messages', 'POST', $data);
    }
    
    public function getMessageStatus($messageSid) {
        return $this->request('Accounts/' . $this->accountSid . '/Messages/' . $messageSid, 'GET');
    }
    
    public function makeCall($to, $twimlUrl, $options = []) {
        if (!$this->validatePhoneNumber($to)) {
            $this->logError('Invalid phone number: ' . $to);
            return false;
        }
        
        $data = [
            'To' => $to,
            'From' => $options['from'] ?? $this->phoneNumber,
            'Url' => $twimlUrl
        ];
        
        if (isset($options['status_callback'])) {
            $data['StatusCallback'] = $options['status_callback'];
        }
        
        if (isset($options['timeout'])) {
            $data['Timeout'] = $options['timeout'];
        }
        
        if (isset($options['record'])) {
            $data['Record'] = $options['record'] ? 'true' : 'false';
        }
        
        if (isset($options['twiml'])) {
            $data['Twiml'] = $options['twiml'];
        }
        
        $data = array_filter($data);
        
        return $this->request('Accounts/' . $this->accountSid . '/Calls', 'POST', $data);
    }
    
    public function getCallStatus($callSid) {
        return $this->request('Accounts/' . $this->accountSid . '/Calls/' . $callSid, 'GET');
    }
    
    public function endCall($callSid) {
        return $this->request('Accounts/' . $this->accountSid . '/Calls/' . $callSid, 'POST', ['Status' => 'completed']);
    }
    
    public function createVideoRoom($roomName, $options = []) {
        $data = [
            'UniqueName' => $roomName,
            'Type' => $options['type'] ?? 'group',
            'MaxParticipants' => $options['max_participants'] ?? 10
        ];
        
        if (isset($options['record'])) {
            $data['RecordParticipantsOnConnect'] = $options['record'] ? 'true' : 'false';
        }
        
        return $this->videoRequest('Rooms', 'POST', $data);
    }
    
    public function listIncomingMessages($filters = []) {
        return $this->request('Accounts/' . $this->accountSid . '/Messages', 'GET', $filters);
    }
    
    public function createMessagingService($friendlyName) {
        return $this->request('Accounts/' . $this->accountSid . '/Messaging/Services', 'POST', ['FriendlyName' => $friendlyName]);
    }
    
    public function getMedia($mediaSid) {
        $response = $this->request('Accounts/' . $this->accountSid . '/Messages/' . $mediaSid . '/Media', 'GET');
        
        if ($response && isset($response['media_url'])) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $response['media_url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' . $this->getAuthHeader()]);
            $mediaContent = curl_exec($ch);
            curl_close($ch);
            
            return $mediaContent;
        }
        
        return false;
    }
    
    public function getUsage($filters = []) {
        return $this->request('Accounts/' . $this->accountSid . '/Usage/Records', 'GET', $filters);
    }
    
    public function getAccountInfo() {
        return $this->request('Accounts/' . $this->accountSid, 'GET');
    }
    
    public function getPhoneNumbers() {
        return $this->request('Accounts/' . $this->accountSid . '/IncomingPhoneNumbers', 'GET');
    }
    
    public function getAvailablePhoneNumbers($countryCode = 'US', $type = 'local') {
        return $this->request('Accounts/' . $this->accountSid . '/AvailablePhoneNumbers/' . $countryCode . '/' . $type, 'GET');
    }
}