<?php
/**
 * API Master Module - Discord API
 * Mesajlaşma, embed, buton, select menu, dosya yükleme ve thread işlemleri
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Discord implements APIMaster_APIInterface {
    
    /**
     * API Base URL
     * @var string
     */
    private $apiUrl = 'https://discord.com/api/v10/';
    
    /**
     * Bot Token
     * @var string|null
     */
    private $botToken = null;
    
    /**
     * API Key (alias for bot token)
     * @var string|null
     */
    private $apiKey = null;
    
    /**
     * Application ID
     * @var string|null
     */
    private $applicationId = null;
    
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
        
        if (isset($config['api_key'])) {
            $this->apiKey = $config['api_key'];
            $this->botToken = $config['api_key'];
        }
        
        if (isset($config['application_id'])) {
            $this->applicationId = $config['application_id'];
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
     * @param string $prompt Message content
     * @param array $options Options (channel_id, embeds, components)
     * @return array Send result
     */
    public function complete($prompt, $options = []) {
        if (!$this->botToken) {
            return ['error' => 'Bot token is required'];
        }
        
        $channelId = $options['channel_id'] ?? null;
        if (!$channelId) {
            return ['error' => 'channel_id is required'];
        }
        
        return $this->sendMessage($channelId, $prompt, $options);
    }
    
    /**
     * Stream method (not supported)
     * 
     * @param string $prompt Message content
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
            'send_embed' => 'Send Embed Message',
            'send_file' => 'Send File',
            'button' => 'Button Components',
            'select_menu' => 'Select Menu Components'
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
            'max_tokens' => 2000,
            'functions' => false,
            'vision' => false,
            'supported_features' => [
                'text_messages',
                'embeds',
                'file_upload',
                'buttons',
                'select_menus',
                'slash_commands',
                'threads',
                'reactions',
                'modals'
            ]
        ];
    }
    
    /**
     * Check API Health
     * 
     * @return array
     */
    public function checkHealth() {
        if (!$this->botToken) {
            return ['status' => 'error', 'message' => 'Bot token not configured'];
        }
        
        $result = $this->getBotUser();
        
        if (isset($result['error'])) {
            return ['status' => 'error', 'message' => $result['error']];
        }
        
        if (isset($result['id']) && isset($result['username'])) {
            return [
                'status' => 'healthy',
                'message' => 'Bot is working',
                'bot_name' => $result['username'],
                'bot_id' => $result['id']
            ];
        }
        
        return ['status' => 'error', 'message' => 'Invalid response from API'];
    }
    
    /**
     * Chat method - Send message with context
     * 
     * @param array $messages Messages array
     * @param array $options Options (channel_id)
     * @param callable|null $callback Callback for streaming
     * @return array Response
     */
    public function chat($messages, $options = [], $callback = null) {
        if (!$this->botToken) {
            return ['error' => 'Bot token is required'];
        }
        
        $channelId = $options['channel_id'] ?? null;
        if (!$channelId) {
            return ['error' => 'channel_id is required'];
        }
        
        // Get the last message content
        $lastMessage = end($messages);
        $content = $lastMessage['content'] ?? '';
        
        return $this->sendMessage($channelId, $content, $options);
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
        
        if (isset($response['content'])) {
            return $response['content'];
        }
        
        if (isset($response['id'])) {
            return 'Message sent: ' . $response['id'];
        }
        
        return json_encode($response);
    }
    
    /**
     * Get Auth Headers
     * 
     * @return array
     */
    private function getHeaders() {
        return [
            'Authorization: Bot ' . $this->botToken,
            'Content-Type: application/json',
            'User-Agent: APIMaster Discord Bot (https://apimaster.com, v1.0.0)'
        ];
    }
    
    /**
     * Make API Request
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method
     * @param bool $multipart Is multipart request
     * @return array Response
     */
    private function request($endpoint, $data = [], $method = 'GET', $multipart = false) {
        if (!$this->botToken) {
            return ['error' => 'Bot token is empty'];
        }
        
        $url = $this->apiUrl . $endpoint;
        $headers = $this->getHeaders();
        
        if ($multipart) {
            // Remove Content-Type for multipart (curl will set it automatically with boundary)
            $headers = array_filter($headers, function($h) {
                return strpos($h, 'Content-Type:') !== 0;
            });
        }
        
        $ch = curl_init();
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                if ($multipart) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
            }
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } elseif ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['error' => $error, 'code' => $httpCode];
        }
        
        if ($httpCode >= 400) {
            $decoded = json_decode($response, true);
            return [
                'error' => $decoded['message'] ?? 'Request failed',
                'code' => $httpCode,
                'details' => $decoded
            ];
        }
        
        if (empty($response)) {
            return ['success' => true];
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Send Message to Channel
     * 
     * @param string $channelId Channel ID
     * @param string $content Message content
     * @param array $options Options (embeds, components, tts)
     * @return array Response
     */
    public function sendMessage($channelId, $content = '', $options = []) {
        $data = [];
        
        if (!empty($content)) {
            $data['content'] = $content;
        }
        
        if (isset($options['embeds'])) {
            $data['embeds'] = $options['embeds'];
        }
        
        if (isset($options['components'])) {
            $data['components'] = $options['components'];
        }
        
        if (isset($options['tts'])) {
            $data['tts'] = $options['tts'];
        }
        
        if (isset($options['nonce'])) {
            $data['nonce'] = $options['nonce'];
        }
        
        if (isset($options['message_reference'])) {
            $data['message_reference'] = $options['message_reference'];
        }
        
        if (isset($options['allowed_mentions'])) {
            $data['allowed_mentions'] = $options['allowed_mentions'];
        }
        
        return $this->request("channels/{$channelId}/messages", $data, 'POST');
    }
    
    /**
     * Send Embed Message
     * 
     * @param string $channelId Channel ID
     * @param array $embed Embed data
     * @param array $options Additional options
     * @return array Response
     */
    public function sendEmbed($channelId, $embed, $options = []) {
        $options['embeds'] = [$embed];
        return $this->sendMessage($channelId, '', $options);
    }
    
    /**
     * Create Embed
     * 
     * @param string $title Embed title
     * @param string $description Embed description
     * @param int $color Embed color (hex)
     * @param array $fields Additional fields
     * @return array Embed
     */
    public function createEmbed($title = '', $description = '', $color = 0x00ff00, $fields = []) {
        $embed = ['color' => $color];
        
        if ($title) {
            $embed['title'] = $title;
        }
        
        if ($description) {
            $embed['description'] = $description;
        }
        
        if (!empty($fields)) {
            $embed['fields'] = $fields;
        }
        
        return $embed;
    }
    
    /**
     * Create Embed Field
     * 
     * @param string $name Field name
     * @param string $value Field value
     * @param bool $inline Inline field
     * @return array Field
     */
    public function createEmbedField($name, $value, $inline = false) {
        return [
            'name' => $name,
            'value' => $value,
            'inline' => $inline
        ];
    }
    
    /**
     * Create Button Component
     * 
     * @param string $label Button label
     * @param string $customId Custom ID
     * @param int $style Button style (1=primary, 2=secondary, 3=success, 4=danger, 5=link)
     * @param string $url URL for link buttons
     * @param bool $disabled Disabled state
     * @return array Button
     */
    public function createButton($label, $customId = '', $style = 1, $url = '', $disabled = false) {
        $button = [
            'type' => 2, // Button type
            'label' => $label,
            'style' => $style,
            'disabled' => $disabled
        ];
        
        if ($style === 5 && $url) {
            $button['url'] = $url;
        } elseif ($customId) {
            $button['custom_id'] = $customId;
        }
        
        return $button;
    }
    
    /**
     * Create Select Menu
     * 
     * @param string $customId Custom ID
     * @param array $options Select options
     * @param string $placeholder Placeholder text
     * @param int $minValues Minimum values
     * @param int $maxValues Maximum values
     * @return array Select menu
     */
    public function createSelectMenu($customId, $options, $placeholder = '', $minValues = 1, $maxValues = 1) {
        $select = [
            'type' => 3, // Select menu type
            'custom_id' => $customId,
            'options' => $options,
            'min_values' => $minValues,
            'max_values' => $maxValues
        ];
        
        if ($placeholder) {
            $select['placeholder'] = $placeholder;
        }
        
        return $select;
    }
    
    /**
     * Create Select Option
     * 
     * @param string $label Option label
     * @param string $value Option value
     * @param string $description Option description
     * @param string $emoji Emoji
     * @return array Option
     */
    public function createSelectOption($label, $value, $description = '', $emoji = '') {
        $option = [
            'label' => $label,
            'value' => $value
        ];
        
        if ($description) {
            $option['description'] = $description;
        }
        
        if ($emoji) {
            $option['emoji'] = ['name' => $emoji];
        }
        
        return $option;
    }
    
    /**
     * Create Action Row
     * 
     * @param array $components Components to include
     * @return array Action row
     */
    public function createActionRow($components) {
        return [
            'type' => 1, // Action row type
            'components' => $components
        ];
    }
    
    /**
     * Edit Message
     * 
     * @param string $channelId Channel ID
     * @param string $messageId Message ID
     * @param string $content New content
     * @param array $options Options
     * @return array Response
     */
    public function editMessage($channelId, $messageId, $content = '', $options = []) {
        $data = [];
        
        if (!empty($content)) {
            $data['content'] = $content;
        }
        
        if (isset($options['embeds'])) {
            $data['embeds'] = $options['embeds'];
        }
        
        if (isset($options['components'])) {
            $data['components'] = $options['components'];
        }
        
        return $this->request("channels/{$channelId}/messages/{$messageId}", $data, 'PATCH');
    }
    
    /**
     * Delete Message
     * 
     * @param string $channelId Channel ID
     * @param string $messageId Message ID
     * @return bool
     */
    public function deleteMessage($channelId, $messageId) {
        $result = $this->request("channels/{$channelId}/messages/{$messageId}", [], 'DELETE');
        return !isset($result['error']);
    }
    
    /**
     * Add Reaction to Message
     * 
     * @param string $channelId Channel ID
     * @param string $messageId Message ID
     * @param string $emoji Emoji (URL encoded)
     * @return bool
     */
    public function addReaction($channelId, $messageId, $emoji) {
        $encodedEmoji = urlencode($emoji);
        $result = $this->request("channels/{$channelId}/messages/{$messageId}/reactions/{$encodedEmoji}/@me", [], 'PUT');
        return !isset($result['error']);
    }
    
    /**
     * Remove Reaction from Message
     * 
     * @param string $channelId Channel ID
     * @param string $messageId Message ID
     * @param string $emoji Emoji (URL encoded)
     * @param string $userId User ID (optional, @me for bot)
     * @return bool
     */
    public function removeReaction($channelId, $messageId, $emoji, $userId = '@me') {
        $encodedEmoji = urlencode($emoji);
        $result = $this->request("channels/{$channelId}/messages/{$messageId}/reactions/{$encodedEmoji}/{$userId}", [], 'DELETE');
        return !isset($result['error']);
    }
    
    /**
     * Get Channel Info
     * 
     * @param string $channelId Channel ID
     * @return array Response
     */
    public function getChannel($channelId) {
        return $this->request("channels/{$channelId}", [], 'GET');
    }
    
    /**
     * Get Guild (Server) Info
     * 
     * @param string $guildId Guild ID
     * @return array Response
     */
    public function getGuild($guildId) {
        return $this->request("guilds/{$guildId}", [], 'GET');
    }
    
    /**
     * Get Guild Channels
     * 
     * @param string $guildId Guild ID
     * @return array Response
     */
    public function getGuildChannels($guildId) {
        return $this->request("guilds/{$guildId}/channels", [], 'GET');
    }
    
    /**
     * Get Guild Members
     * 
     * @param string $guildId Guild ID
     * @param int $limit Limit (max 1000)
     * @param string $after User ID to start after
     * @return array Response
     */
    public function getGuildMembers($guildId, $limit = 1000, $after = '') {
        $params = ['limit' => $limit];
        if ($after) {
            $params['after'] = $after;
        }
        return $this->request("guilds/{$guildId}/members", $params, 'GET');
    }
    
    /**
     * Get User Info
     * 
     * @param string $userId User ID
     * @return array Response
     */
    public function getUser($userId) {
        return $this->request("users/{$userId}", [], 'GET');
    }
    
    /**
     * Get Current Bot User
     * 
     * @return array Response
     */
    public function getBotUser() {
        return $this->request("users/@me", [], 'GET');
    }
    
    /**
     * Create Invite
     * 
     * @param string $channelId Channel ID
     * @param int $maxAge Max age in seconds (0 = never)
     * @param int $maxUses Max uses (0 = unlimited)
     * @param bool $temporary Temporary membership
     * @return array Response
     */
    public function createInvite($channelId, $maxAge = 86400, $maxUses = 0, $temporary = false) {
        return $this->request("channels/{$channelId}/invites", [
            'max_age' => $maxAge,
            'max_uses' => $maxUses,
            'temporary' => $temporary
        ], 'POST');
    }
    
    /**
     * Create Thread from Message
     * 
     * @param string $channelId Channel ID
     * @param string $messageId Message ID
     * @param string $name Thread name
     * @param int $autoArchiveDuration Auto archive duration
     * @return array Response
     */
    public function createThread($channelId, $messageId, $name, $autoArchiveDuration = 1440) {
        return $this->request("channels/{$channelId}/messages/{$messageId}/threads", [
            'name' => $name,
            'auto_archive_duration' => $autoArchiveDuration
        ], 'POST');
    }
    
    /**
     * Create Interaction Response (for slash commands)
     * 
     * @param string $content Response content
     * @param array $options Options
     * @return array Response
     */
    public function createInteractionResponse($content = '', $options = []) {
        $response = ['type' => 4]; // Channel message with source
        $data = [];
        
        if (!empty($content)) {
            $data['content'] = $content;
        }
        
        if (isset($options['embeds'])) {
            $data['embeds'] = $options['embeds'];
        }
        
        if (isset($options['components'])) {
            $data['components'] = $options['components'];
        }
        
        if (isset($options['flags'])) {
            $data['flags'] = $options['flags']; // 64 = ephemeral
        }
        
        $response['data'] = $data;
        
        return $response;
    }
}