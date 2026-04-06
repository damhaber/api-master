<?php
/**
 * API Master Module - Slack API
 * Mesajlaşma, kanal yönetimi, Block Kit ve webhook işlemleri
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Slack implements APIMaster_APIInterface {
    
    /**
     * API Base URL
     * @var string
     */
    private $apiUrl = 'https://slack.com/api/';
    
    /**
     * Bot Token (xoxb-)
     * @var string|null
     */
    private $botToken = null;
    
    /**
     * User Token (xoxp-)
     * @var string|null
     */
    private $userToken = null;
    
    /**
     * API Key (alias for bot token)
     * @var string|null
     */
    private $apiKey = null;
    
    /**
     * Signing Secret for verification
     * @var string|null
     */
    private $signingSecret = null;
    
    /**
     * Current model (for interface compatibility)
     * @var string|null
     */
    private $model = null;
    
    /**
     * Timeout in seconds
     * @var int
     */
    private $timeout = 30;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct(array $config = []) {
        if (isset($config['bot_token'])) {
            $this->botToken = $config['bot_token'];
            $this->apiKey = $config['bot_token'];
        }
        
        if (isset($config['user_token'])) {
            $this->userToken = $config['user_token'];
        }
        
        if (isset($config['api_key'])) {
            $this->apiKey = $config['api_key'];
            $this->botToken = $config['api_key'];
        }
        
        if (isset($config['signing_secret'])) {
            $this->signingSecret = $config['signing_secret'];
        }
        
        if (isset($config['timeout'])) {
            $this->timeout = (int) $config['timeout'];
        }
    }
    
    /**
     * Set API Key (Bot Token)
     * 
     * @param string $apiKey
     * @return self
     */
    public function setApiKey($apiKey) {
        $this->apiKey = $apiKey;
        $this->botToken = $apiKey;
        return $this;
    }
    
    /**
     * Set Model (for interface compatibility)
     * 
     * @param string $model
     * @return self
     */
    public function setModel($model) {
        $this->model = $model;
        return $this;
    }
    
    /**
     * Get Current Model
     * 
     * @return string|null
     */
    public function getModel() {
        return $this->model;
    }
    
    /**
     * Complete method - Send message to channel
     * 
     * @param string $prompt Message text
     * @param array $options Options (channel, blocks, attachments)
     * @return array Send result
     */
    public function complete($prompt, $options = []) {
        if (!$this->botToken && !$this->userToken) {
            return ['error' => 'Bot token or user token is required'];
        }
        
        $channel = $options['channel'] ?? null;
        if (!$channel) {
            return ['error' => 'channel is required'];
        }
        
        return $this->sendMessage($channel, $prompt, $options);
    }
    
    /**
     * Stream method (not supported)
     * 
     * @param string $prompt Message text
     * @param callable $callback Callback function
     * @param array $options Options
     * @return void
     */
    public function stream($prompt, $callback, $options = []) {
        $result = $this->complete($prompt, $options);
        if (is_callable($callback)) {
            $callback(json_encode($result));
        }
    }
    
    /**
     * Get Available Models (Features)
     * 
     * @return array
     */
    public function getModels() {
        return [
            'send_message' => 'Send Text Message',
            'block_message' => 'Block Kit Message',
            'ephemeral' => 'Ephemeral Message',
            'update_message' => 'Update Message',
            'delete_message' => 'Delete Message'
        ];
    }
    
    /**
     * Get Provider Capabilities
     * 
     * @return array
     */
    public function getCapabilities() {
        return [
            'streaming' => false,
            'chat' => true,
            'completion' => true,
            'models' => true,
            'max_tokens' => 4000,
            'functions' => false,
            'vision' => false,
            'supported_features' => [
                'text_messages',
                'block_kit',
                'ephemeral_messages',
                'threads',
                'reactions',
                'modals',
                'slash_commands',
                'interactive_buttons',
                'select_menus',
                'file_upload',
                'webhook_verification'
            ]
        ];
    }
    
    /**
     * Check API Health
     * 
     * @return array
     */
    public function checkHealth() {
        if (!$this->botToken && !$this->userToken) {
            return ['status' => 'error', 'message' => 'Token not configured'];
        }
        
        $result = $this->request('auth.test', [], 'GET');
        
        if (isset($result['error'])) {
            return ['status' => 'error', 'message' => $result['error']];
        }
        
        if (isset($result['ok']) && $result['ok'] === true) {
            return [
                'status' => 'healthy',
                'message' => 'API is working',
                'team' => $result['team'] ?? 'Unknown',
                'user' => $result['user'] ?? 'Unknown'
            ];
        }
        
        return ['status' => 'error', 'message' => 'Invalid response from API'];
    }
    
    /**
     * Chat method - Send message with context
     * 
     * @param array $messages Messages array
     * @param array $options Options (channel, thread_ts)
     * @param callable|null $callback Callback for streaming
     * @return array Response
     */
    public function chat($messages, $options = [], $callback = null) {
        if (!$this->botToken && !$this->userToken) {
            return ['error' => 'Token is required'];
        }
        
        $channel = $options['channel'] ?? null;
        if (!$channel) {
            return ['error' => 'channel is required'];
        }
        
        // Get the last message content
        $lastMessage = end($messages);
        $text = $lastMessage['content'] ?? '';
        
        return $this->sendMessage($channel, $text, $options);
    }
    
    /**
     * Extract Text from Response
     * 
     * @param array $response API response
     * @return string
     */
    public function extractText($response) {
        if (isset($response['error'])) {
            return $response['error'];
        }
        
        if (isset($response['message']['text'])) {
            return $response['message']['text'];
        }
        
        if (isset($response['ts'])) {
            return 'Message sent at: ' . $response['ts'];
        }
        
        return json_encode($response);
    }
    
    /**
     * Get Auth Headers
     * 
     * @return array
     */
    private function getHeaders() {
        $token = $this->botToken ?: $this->userToken;
        return [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json; charset=utf-8'
        ];
    }
    
    /**
     * Get Active Token
     * 
     * @return string|null
     */
    private function getToken() {
        return $this->botToken ?: $this->userToken;
    }
    
    /**
     * Make API Request
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method
     * @return array Response
     */
    private function request($endpoint, $data = [], $method = 'POST') {
        $token = $this->getToken();
        if (!$token) {
            return ['error' => 'Token is empty'];
        }
        
        $url = $this->apiUrl . $endpoint;
        
        $ch = curl_init();
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['error' => $error, 'code' => $httpCode];
        }
        
        $result = json_decode($response, true);
        
        if ($result && isset($result['ok']) && $result['ok'] === true) {
            return $result;
        }
        
        return [
            'error' => $result['error'] ?? 'Request failed',
            'ok' => false,
            'code' => $httpCode
        ];
    }
    
    /**
     * Send Message to Channel
     * 
     * @param string $channel Channel ID or name
     * @param string $text Message text
     * @param array $options Options (blocks, attachments, thread_ts)
     * @return array Response
     */
    public function sendMessage($channel, $text, $options = []) {
        $data = [
            'channel' => $channel,
            'text' => $text
        ];
        
        if (isset($options['blocks'])) {
            $data['blocks'] = $options['blocks'];
        }
        
        if (isset($options['attachments'])) {
            $data['attachments'] = $options['attachments'];
        }
        
        if (isset($options['thread_ts'])) {
            $data['thread_ts'] = $options['thread_ts'];
        }
        
        if (isset($options['reply_broadcast'])) {
            $data['reply_broadcast'] = $options['reply_broadcast'];
        }
        
        if (isset($options['parse'])) {
            $data['parse'] = $options['parse'];
        }
        
        if (isset($options['link_names'])) {
            $data['link_names'] = $options['link_names'];
        }
        
        if (isset($options['unfurl_links'])) {
            $data['unfurl_links'] = $options['unfurl_links'];
        }
        
        if (isset($options['unfurl_media'])) {
            $data['unfurl_media'] = $options['unfurl_media'];
        }
        
        if (isset($options['mrkdwn'])) {
            $data['mrkdwn'] = $options['mrkdwn'];
        }
        
        return $this->request('chat.postMessage', $data);
    }
    
    /**
     * Update Existing Message
     * 
     * @param string $channel Channel ID
     * @param string $ts Message timestamp
     * @param string $text New message text
     * @param array $options Options
     * @return array Response
     */
    public function updateMessage($channel, $ts, $text, $options = []) {
        $data = [
            'channel' => $channel,
            'ts' => $ts,
            'text' => $text
        ];
        
        if (isset($options['blocks'])) {
            $data['blocks'] = $options['blocks'];
        }
        
        if (isset($options['attachments'])) {
            $data['attachments'] = $options['attachments'];
        }
        
        if (isset($options['parse'])) {
            $data['parse'] = $options['parse'];
        }
        
        if (isset($options['link_names'])) {
            $data['link_names'] = $options['link_names'];
        }
        
        return $this->request('chat.update', $data);
    }
    
    /**
     * Delete Message
     * 
     * @param string $channel Channel ID
     * @param string $ts Message timestamp
     * @return array Response
     */
    public function deleteMessage($channel, $ts) {
        return $this->request('chat.delete', [
            'channel' => $channel,
            'ts' => $ts
        ]);
    }
    
    /**
     * Send Ephemeral Message (visible only to specific user)
     * 
     * @param string $channel Channel ID
     * @param string $userId User ID
     * @param string $text Message text
     * @param array $options Options
     * @return array Response
     */
    public function sendEphemeral($channel, $userId, $text, $options = []) {
        $data = [
            'channel' => $channel,
            'user' => $userId,
            'text' => $text
        ];
        
        if (isset($options['blocks'])) {
            $data['blocks'] = $options['blocks'];
        }
        
        if (isset($options['attachments'])) {
            $data['attachments'] = $options['attachments'];
        }
        
        if (isset($options['parse'])) {
            $data['parse'] = $options['parse'];
        }
        
        if (isset($options['link_names'])) {
            $data['link_names'] = $options['link_names'];
        }
        
        return $this->request('chat.postEphemeral', $data);
    }
    
    /**
     * Send Block Kit Message
     * 
     * @param string $channel Channel ID
     * @param array $blocks Block Kit blocks
     * @param array $options Options
     * @return array Response
     */
    public function sendBlockMessage($channel, $blocks, $options = []) {
        $options['blocks'] = $blocks;
        return $this->sendMessage($channel, '', $options);
    }
    
    /**
     * Create Button Element
     * 
     * @param string $text Button text
     * @param string $actionId Action ID
     * @param string $value Button value
     * @param string $style Button style (primary, danger)
     * @return array Button element
     */
    public function createButton($text, $actionId, $value = '', $style = '') {
        $button = [
            'type' => 'button',
            'text' => [
                'type' => 'plain_text',
                'text' => $text
            ],
            'action_id' => $actionId,
            'value' => $value
        ];
        
        if ($style && in_array($style, ['primary', 'danger'])) {
            $button['style'] = $style;
        }
        
        return $button;
    }
    
    /**
     * Create Select Menu
     * 
     * @param string $placeholder Placeholder text
     * @param string $actionId Action ID
     * @param array $options Select options (value => text)
     * @return array Select menu element
     */
    public function createSelectMenu($placeholder, $actionId, $options) {
        $selectOptions = [];
        foreach ($options as $value => $text) {
            $selectOptions[] = [
                'text' => [
                    'type' => 'plain_text',
                    'text' => $text
                ],
                'value' => $value
            ];
        }
        
        return [
            'type' => 'static_select',
            'placeholder' => [
                'type' => 'plain_text',
                'text' => $placeholder
            ],
            'action_id' => $actionId,
            'options' => $selectOptions
        ];
    }
    
    /**
     * Create Section Block
     * 
     * @param string $text Section text
     * @param array $fields Optional fields
     * @param array|null $accessory Optional accessory
     * @return array Section block
     */
    public function createSection($text, $fields = [], $accessory = null) {
        $section = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => $text
            ]
        ];
        
        if (!empty($fields)) {
            $section['fields'] = [];
            foreach ($fields as $field) {
                $section['fields'][] = [
                    'type' => 'mrkdwn',
                    'text' => $field
                ];
            }
        }
        
        if ($accessory) {
            $section['accessory'] = $accessory;
        }
        
        return $section;
    }
    
    /**
     * Create Divider Block
     * 
     * @return array Divider block
     */
    public function createDivider() {
        return ['type' => 'divider'];
    }
    
    /**
     * Create Image Block
     * 
     * @param string $imageUrl Image URL
     * @param string $altText Alt text
     * @param string $title Optional title
     * @return array Image block
     */
    public function createImageBlock($imageUrl, $altText, $title = '') {
        $block = [
            'type' => 'image',
            'image_url' => $imageUrl,
            'alt_text' => $altText
        ];
        
        if ($title) {
            $block['title'] = [
                'type' => 'plain_text',
                'text' => $title
            ];
        }
        
        return $block;
    }
    
    /**
     * Get Channel List
     * 
     * @param bool $excludeArchived Exclude archived channels
     * @param int $limit Limit results
     * @return array Response
     */
    public function getChannels($excludeArchived = true, $limit = 100) {
        return $this->request('conversations.list', [
            'exclude_archived' => $excludeArchived,
            'limit' => $limit,
            'types' => 'public_channel'
        ], 'GET');
    }
    
    /**
     * Get Private Channels
     * 
     * @param bool $excludeArchived Exclude archived
     * @param int $limit Limit results
     * @return array Response
     */
    public function getPrivateChannels($excludeArchived = true, $limit = 100) {
        return $this->request('conversations.list', [
            'exclude_archived' => $excludeArchived,
            'limit' => $limit,
            'types' => 'private_channel'
        ], 'GET');
    }
    
    /**
     * Get Users List
     * 
     * @param int $limit Limit results
     * @return array Response
     */
    public function getUsers($limit = 200) {
        return $this->request('users.list', ['limit' => $limit], 'GET');
    }
    
    /**
     * Get User Info
     * 
     * @param string $userId User ID
     * @return array Response
     */
    public function getUserInfo($userId) {
        return $this->request('users.info', ['user' => $userId], 'GET');
    }
    
    /**
     * Add Reaction to Message
     * 
     * @param string $channel Channel ID
     * @param string $timestamp Message timestamp
     * @param string $reaction Reaction name
     * @return array Response
     */
    public function addReaction($channel, $timestamp, $reaction) {
        return $this->request('reactions.add', [
            'channel' => $channel,
            'timestamp' => $timestamp,
            'name' => $reaction
        ]);
    }
    
    /**
     * Remove Reaction from Message
     * 
     * @param string $channel Channel ID
     * @param string $timestamp Message timestamp
     * @param string $reaction Reaction name
     * @return array Response
     */
    public function removeReaction($channel, $timestamp, $reaction) {
        return $this->request('reactions.remove', [
            'channel' => $channel,
            'timestamp' => $timestamp,
            'name' => $reaction
        ]);
    }
    
    /**
     * Get Conversation History
     * 
     * @param string $channel Channel ID
     * @param int $limit Limit messages
     * @param string $oldest Oldest timestamp
     * @param string $latest Latest timestamp
     * @return array Response
     */
    public function getConversationHistory($channel, $limit = 100, $oldest = '', $latest = '') {
        $params = [
            'channel' => $channel,
            'limit' => $limit
        ];
        
        if ($oldest) {
            $params['oldest'] = $oldest;
        }
        
        if ($latest) {
            $params['latest'] = $latest;
        }
        
        return $this->request('conversations.history', $params, 'GET');
    }
    
    /**
     * Join Channel
     * 
     * @param string $channel Channel ID or name
     * @return array Response
     */
    public function joinChannel($channel) {
        return $this->request('conversations.join', ['channel' => $channel]);
    }
    
    /**
     * Leave Channel
     * 
     * @param string $channel Channel ID
     * @return array Response
     */
    public function leaveChannel($channel) {
        return $this->request('conversations.leave', ['channel' => $channel]);
    }
    
    /**
     * Open Modal View
     * 
     * @param string $triggerId Trigger ID
     * @param array $view View definition
     * @return array Response
     */
    public function openModal($triggerId, $view) {
        return $this->request('views.open', [
            'trigger_id' => $triggerId,
            'view' => $view
        ]);
    }
    
    /**
     * Verify Slack Request Signature
     * 
     * @param string $signature Slack signature header
     * @param string $timestamp Request timestamp
     * @param string $body Request body
     * @return bool
     */
    public function verifyRequest($signature, $timestamp, $body) {
        if (empty($this->signingSecret)) {
            return false;
        }
        
        // Check timestamp freshness (5 minutes)
        if (abs(time() - $timestamp) > 300) {
            return false;
        }
        
        $sigBasestring = 'v0:' . $timestamp . ':' . $body;
        $computedSignature = 'v0=' . hash_hmac('sha256', $sigBasestring, $this->signingSecret);
        
        return hash_equals($computedSignature, $signature);
    }
    
    /**
     * Parse Incoming Event
     * 
     * @param array $payload Request payload
     * @return array|null Parsed event or challenge response
     */
    public function parseEvent($payload) {
        if (isset($payload['type']) && $payload['type'] === 'url_verification') {
            return ['challenge' => $payload['challenge']];
        }
        
        if (isset($payload['event'])) {
            return $payload['event'];
        }
        
        return null;
    }
}