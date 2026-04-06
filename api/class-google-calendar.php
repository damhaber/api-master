<?php
/**
 * Google Calendar API Handler for Masal Panel
 * 
 * Calendar management, events, invitations
 * 
 * @package MasalPanel\APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_GoogleCalendar implements APIMaster_APIInterface {
    
    private $clientId;
    private $clientSecret;
    private $refreshToken;
    private $accessToken;
    private $calendarId = 'primary';
    private $model = 'v3';
    private $config;
    
    private $apiUrl = 'https://www.googleapis.com/calendar/v3';
    private $oauthUrl = 'https://oauth2.googleapis.com/token';
    
    public function __construct() {
        $this->config = $this->loadConfig();
        $this->clientId = $this->config['client_id'] ?? '';
        $this->clientSecret = $this->config['client_secret'] ?? '';
        $this->refreshToken = $this->config['refresh_token'] ?? '';
        $this->calendarId = $this->config['calendar_id'] ?? 'primary';
        
        $this->initAuth();
    }
    
    private function loadConfig() {
        $configFile = dirname(__DIR__) . '/config/google-calendar.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
    
    private function initAuth() {
        if ($this->refreshToken && $this->clientId && $this->clientSecret) {
            $this->refreshAccessToken();
        }
    }
    
    private function refreshAccessToken() {
        if (empty($this->clientId) || empty($this->clientSecret) || empty($this->refreshToken)) {
            return false;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->oauthUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token'
        ]));
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
    
    private function getHeaders() {
        return [
            'Authorization: Bearer ' . $this->accessToken,
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
            
            // Token expired, refresh and retry
            if ($httpCode === 401 && $this->refreshToken) {
                if ($this->refreshAccessToken()) {
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
    
    private function request($endpoint, $method = 'GET', $data = [], $params = []) {
        if (empty($this->accessToken)) {
            return false;
        }
        
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $requestData = null;
        if (($method === 'POST' || $method === 'PUT' || $method === 'PATCH') && !empty($data)) {
            $requestData = $data;
        }
        
        return $this->curlRequest($url, $method, $requestData);
    }
    
    private function logError($message) {
        $logFile = dirname(__DIR__) . '/logs/google-calendar-error.log';
        $date = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$date] $message" . PHP_EOL, FILE_APPEND);
    }
    
    // ========== APIInterface REQUIRED METHODS ==========
    
    public function setApiKey($apiKey) {
        // Google Calendar uses OAuth, not API key
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
        return ['error' => 'Google Calendar does not support text completion'];
    }
    
    public function stream($prompt, $callback, $params = []) {
        return false;
    }
    
    public function getModels() {
        return [
            ['id' => 'v3', 'name' => 'Google Calendar API v3', 'description' => 'Google Calendar API']
        ];
    }
    
    public function getCapabilities() {
        return [
            'events_crud' => true,
            'recurring_events' => true,
            'calendar_management' => true,
            'sharing' => true,
            'free_busy' => true,
            'import_export' => true,
            'sync' => true
        ];
    }
    
    public function checkHealth() {
        $params = ['maxResults' => 1, 'timeMin' => date('c')];
        $result = $this->request("calendars/{$this->calendarId}/events", 'GET', [], $params);
        return $result !== false;
    }
    
    public function chat($messages, $params = []) {
        return ['error' => 'Google Calendar does not support chat functionality'];
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
    
    // ========== GOOGLE CALENDAR SPECIFIC METHODS ==========
    
    public function setCredentials($clientId, $clientSecret, $refreshToken) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->refreshToken = $refreshToken;
        $this->refreshAccessToken();
        return $this;
    }
    
    public function setCalendarId($calendarId) {
        $this->calendarId = $calendarId;
        return $this;
    }
    
    public function createEvent($eventData, $calendarId = null) {
        $calendar = $calendarId ?? $this->calendarId;
        
        $requiredFields = ['summary', 'start', 'end'];
        foreach ($requiredFields as $field) {
            if (empty($eventData[$field])) {
                $this->logError('Missing required field: ' . $field);
                return false;
            }
        }
        
        $data = [
            'summary' => $eventData['summary'],
            'description' => $eventData['description'] ?? '',
            'location' => $eventData['location'] ?? '',
            'start' => $eventData['start'],
            'end' => $eventData['end'],
            'timeZone' => $eventData['timezone'] ?? 'UTC',
            'attendees' => $eventData['attendees'] ?? [],
            'reminders' => $eventData['reminders'] ?? [],
            'transparency' => $eventData['transparency'] ?? 'opaque',
            'visibility' => $eventData['visibility'] ?? 'default',
            'status' => $eventData['status'] ?? 'confirmed'
        ];
        
        if (isset($eventData['color_id'])) {
            $data['colorId'] = $eventData['color_id'];
        }
        
        if (isset($eventData['recurrence'])) {
            $data['recurrence'] = $eventData['recurrence'];
        }
        
        if (isset($eventData['attachments'])) {
            $data['attachments'] = $eventData['attachments'];
        }
        
        $data = array_filter($data, function($value) {
            return $value !== null && $value !== '';
        });
        
        return $this->request("calendars/{$calendar}/events", 'POST', $data);
    }
    
    public function getEvent($eventId, $calendarId = null) {
        $calendar = $calendarId ?? $this->calendarId;
        return $this->request("calendars/{$calendar}/events/{$eventId}", 'GET');
    }
    
    public function updateEvent($eventId, $updateData, $calendarId = null) {
        $calendar = $calendarId ?? $this->calendarId;
        return $this->request("calendars/{$calendar}/events/{$eventId}", 'PUT', $updateData);
    }
    
    public function deleteEvent($eventId, $calendarId = null) {
        $calendar = $calendarId ?? $this->calendarId;
        $result = $this->request("calendars/{$calendar}/events/{$eventId}", 'DELETE');
        return $result !== false;
    }
    
    public function listEvents($params = [], $calendarId = null) {
        $calendar = $calendarId ?? $this->calendarId;
        
        $defaultParams = [
            'maxResults' => 10,
            'orderBy' => 'startTime',
            'singleEvents' => true
        ];
        
        $params = array_merge($defaultParams, $params);
        
        if (!isset($params['timeMin'])) {
            $params['timeMin'] = date('c');
        }
        
        if (!isset($params['timeMax']) && isset($params['days'])) {
            $params['timeMax'] = date('c', strtotime('+' . $params['days'] . ' days'));
            unset($params['days']);
        }
        
        return $this->request("calendars/{$calendar}/events", 'GET', [], $params);
    }
    
    public function createRecurringEvent($eventData, $recurrenceRule, $calendarId = null) {
        $eventData['recurrence'] = $recurrenceRule;
        return $this->createEvent($eventData, $calendarId);
    }
    
    public function updateAttendees($eventId, $attendees, $calendarId = null) {
        $event = $this->getEvent($eventId, $calendarId);
        
        if (!$event) {
            return false;
        }
        
        $event['attendees'] = $attendees;
        $event['guestsCanModify'] = true;
        
        return $this->updateEvent($eventId, $event, $calendarId);
    }
    
    public function sendInvitations($eventId, $emails, $calendarId = null) {
        $attendees = array_map(function($email) {
            return ['email' => $email];
        }, $emails);
        
        $result = $this->updateAttendees($eventId, $attendees, $calendarId);
        return $result !== false;
    }
    
    public function listCalendars($includeHidden = false) {
        $params = [];
        if (!$includeHidden) {
            $params['showHidden'] = 'false';
        }
        
        return $this->request('users/me/calendarList', 'GET', [], $params);
    }
    
    public function createCalendar($calendarData) {
        if (empty($calendarData['summary'])) {
            $this->logError('Calendar summary required');
            return false;
        }
        
        $data = [
            'summary' => $calendarData['summary'],
            'description' => $calendarData['description'] ?? '',
            'timeZone' => $calendarData['timezone'] ?? 'UTC',
            'location' => $calendarData['location'] ?? ''
        ];
        
        return $this->request('calendars', 'POST', $data);
    }
    
    public function updateCalendar($calendarId, $updateData) {
        return $this->request("calendars/{$calendarId}", 'PUT', $updateData);
    }
    
    public function getFreeBusy($timeRange, $calendarIds = []) {
        if (empty($calendarIds)) {
            $calendarIds = [$this->calendarId];
        }
        
        $data = [
            'timeMin' => $timeRange['start'],
            'timeMax' => $timeRange['end'],
            'items' => array_map(function($id) {
                return ['id' => $id];
            }, $calendarIds)
        ];
        
        return $this->request('freeBusy', 'POST', $data);
    }
    
    public function shareCalendar($calendarId, $email, $role = 'reader') {
        $data = [
            'role' => $role,
            'scope' => [
                'type' => 'user',
                'value' => $email
            ]
        ];
        
        return $this->request("calendars/{$calendarId}/acl", 'POST', $data);
    }
    
    public function unshareCalendar($calendarId) {
        $result = $this->request("calendars/{$calendarId}", 'DELETE');
        return $result !== false;
    }
    
    public function importEvent($icalData, $calendarId = null) {
        $calendar = $calendarId ?? $this->calendarId;
        return $this->request("calendars/{$calendar}/events/import", 'POST', ['body' => $icalData]);
    }
    
    public function syncEvents($syncToken = null, $calendarId = null) {
        $calendar = $calendarId ?? $this->calendarId;
        $params = [];
        
        if ($syncToken) {
            $params['syncToken'] = $syncToken;
        }
        
        return $this->request("calendars/{$calendar}/events", 'GET', [], $params);
    }
}