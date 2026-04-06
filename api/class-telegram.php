<?php
/**
 * API Master Module - Telegram Bot API
 * Mesajlaşma, bot komutları, webhook ve dosya işlemleri
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Telegram implements APIMaster_APIInterface {
    
    /**
     * API Base URL
     * @var string
     */
    private $apiUrl = 'https://api.telegram.org/bot';
    
    /**
     * Bot Token (API Key)
     * @var string|null
     */
    private $botToken = null;
    
    /**
     * API Key (alias for bot token)
     * @var string|null
     */
    private $apiKey = null;
    
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
            $this->setApiKey($config['bot_token']);
        }
        
        if (isset($config['api_key'])) {
            $this->setApiKey($config['api_key']);
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
     * Complete method - Send message
     * 
     * @param string $prompt Message text or command
     * @param array $options Options (chat_id, parse_mode, reply_markup)
     * @return array Send result
     */
    public function complete($prompt, $options = []) {
        if (!$this->botToken) {
            return ['error' => 'Bot token is required'];
        }
        
        $chatId = $options['chat_id'] ?? null;
        if (!$chatId) {
            return ['error' => 'chat_id is required'];
        }
        
        return $this->sendMessage($chatId, $prompt, $options);
    }
    
    /**
     * Stream method (not supported - Telegram doesn't support streaming)
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
     * Get Available Models (Bot features)
     * 
     * @return array
     */
    public function getModels() {
        return [
            'send_message' => 'Send Text Message',
            'send_photo' => 'Send Photo',
            'send_document' => 'Send Document',
            'inline_keyboard' => 'Inline Keyboard',
            'webhook' => 'Webhook Management'
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
            'max_tokens' => 4096,
            'functions' => false,
            'vision' => false,
            'supported_features' => [
                'text_messages',
                'photo_messages',
                'document_messages',
                'keyboard_markup',
                'inline_keyboard',
                'webhook',
                'callback_queries',
                'chat_actions',
                'file_handling'
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
        
        $result = $this->getMe();
        
        if (isset($result['error'])) {
            return ['status' => 'error', 'message' => $result['error']];
        }
        
        if (isset($result['id']) && isset($result['is_bot'])) {
            return [
                'status' => 'healthy',
                'message' => 'Bot is working',
                'bot_name' => $result['first_name'] ?? $result['username'] ?? 'Unknown'
            ];
        }
        
        return ['status' => 'error', 'message' => 'Invalid response from API'];
    }
    
    /**
     * Chat method - Handle messages and replies
     * 
     * @param array $messages Messages array (each with role, content)
     * @param array $options Options (chat_id, parse_mode)
     * @param callable|null $callback Callback for streaming (not supported)
     * @return array Response
     */
    public function chat($messages, $options = [], $callback = null) {
        if (!$this->botToken) {
            return ['error' => 'Bot token is required'];
        }
        
        $chatId = $options['chat_id'] ?? null;
        if (!$chatId) {
            return ['error' => 'chat_id is required'];
        }
        
        // Get the last user message
        $lastMessage = end($messages);
        $text = $lastMessage['content'] ?? '';
        
        // Process command if exists
        $command = $this->getCommand($text);
        if ($command) {
            $args = $this->getCommandArgs($text);
            return $this->handleCommand($command, $args, $chatId, $options);
        }
        
        // Regular message
        return $this->sendMessage($chatId, $text, $options);
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
        
        if (isset($response['message_id']) && isset($response['text'])) {
            return $response['text'];
        }
        
        if (isset($response['result']['message_id'])) {
            return $response['result']['message_id'];
        }
        
        return json_encode($response);
    }
    
    /**
     * Make API Request
     * 
     * @param string $method API method
     * @param array $params Request parameters
     * @return array Response
     */
    private function request($method, $params = []) {
        if (!$this->botToken) {
            return ['error' => 'Bot token is empty'];
        }
        
        $url = $this->apiUrl . $this->botToken . '/' . $method;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['error' => $error, 'code' => $httpCode];
        }
        
        $data = json_decode($response, true);
        
        if ($data && isset($data['ok']) && $data['ok'] === true) {
            return $data['result'] ?? ['success' => true];
        }
        
        return [
            'error' => $data['description'] ?? 'Request failed',
            'error_code' => $data['error_code'] ?? $httpCode,
            'parameters' => $data['parameters'] ?? null
        ];
    }
    
    /**
     * Send Message
     * 
     * @param int|string $chatId Chat ID
     * @param string $text Message text
     * @param array $options Options (parse_mode, reply_markup, etc.)
     * @return array Response
     */
    public function sendMessage($chatId, $text, $options = []) {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $options['parse_mode'] ?? 'HTML'
        ];
        
        if (isset($options['disable_web_page_preview'])) {
            $params['disable_web_page_preview'] = $options['disable_web_page_preview'];
        }
        
        if (isset($options['disable_notification'])) {
            $params['disable_notification'] = $options['disable_notification'];
        }
        
        if (isset($options['reply_to_message_id'])) {
            $params['reply_to_message_id'] = $options['reply_to_message_id'];
        }
        
        if (isset($options['reply_markup'])) {
            $params['reply_markup'] = $options['reply_markup'];
        }
        
        return $this->request('sendMessage', $params);
    }
    
    /**
     * Send Photo
     * 
     * @param int|string $chatId Chat ID
     * @param string $photo Photo URL or file_id
     * @param string $caption Caption
     * @param array $options Options
     * @return array Response
     */
    public function sendPhoto($chatId, $photo, $caption = '', $options = []) {
        $params = [
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $caption,
            'parse_mode' => $options['parse_mode'] ?? 'HTML'
        ];
        
        if (isset($options['disable_notification'])) {
            $params['disable_notification'] = $options['disable_notification'];
        }
        
        if (isset($options['reply_to_message_id'])) {
            $params['reply_to_message_id'] = $options['reply_to_message_id'];
        }
        
        if (isset($options['reply_markup'])) {
            $params['reply_markup'] = $options['reply_markup'];
        }
        
        return $this->request('sendPhoto', $params);
    }
    
    /**
     * Send Document
     * 
     * @param int|string $chatId Chat ID
     * @param string $document Document URL or file_id
     * @param string $caption Caption
     * @param array $options Options
     * @return array Response
     */
    public function sendDocument($chatId, $document, $caption = '', $options = []) {
        $params = [
            'chat_id' => $chatId,
            'document' => $document,
            'caption' => $caption,
            'parse_mode' => $options['parse_mode'] ?? 'HTML'
        ];
        
        if (isset($options['disable_notification'])) {
            $params['disable_notification'] = $options['disable_notification'];
        }
        
        if (isset($options['reply_to_message_id'])) {
            $params['reply_to_message_id'] = $options['reply_to_message_id'];
        }
        
        if (isset($options['reply_markup'])) {
            $params['reply_markup'] = $options['reply_markup'];
        }
        
        return $this->request('sendDocument', $params);
    }
    
    /**
     * Create Keyboard Markup
     * 
     * @param array $buttons Keyboard buttons
     * @param bool $resizeKeyboard Resize keyboard
     * @param bool $oneTimeKeyboard One time keyboard
     * @return array Keyboard markup
     */
    public function createKeyboard($buttons, $resizeKeyboard = true, $oneTimeKeyboard = false) {
        return [
            'keyboard' => $buttons,
            'resize_keyboard' => $resizeKeyboard,
            'one_time_keyboard' => $oneTimeKeyboard
        ];
    }
    
    /**
     * Create Inline Keyboard
     * 
     * @param array $inlineButtons Inline buttons
     * @return array Inline keyboard markup
     */
    public function createInlineKeyboard($inlineButtons) {
        return [
            'inline_keyboard' => $inlineButtons
        ];
    }
    
    /**
     * Create Inline Button
     * 
     * @param string $text Button text
     * @param string|null $callbackData Callback data
     * @param string|null $url URL
     * @return array Button
     */
    public function createInlineButton($text, $callbackData = null, $url = null) {
        $button = ['text' => $text];
        
        if ($url) {
            $button['url'] = $url;
        } elseif ($callbackData) {
            $button['callback_data'] = $callbackData;
        }
        
        return $button;
    }
    
    /**
     * Get Updates (Long Polling)
     * 
     * @param int $offset Offset
     * @param int $limit Limit
     * @param int $timeout Timeout
     * @return array Updates
     */
    public function getUpdates($offset = 0, $limit = 100, $timeout = 0) {
        return $this->request('getUpdates', [
            'offset' => $offset,
            'limit' => $limit,
            'timeout' => $timeout
        ]);
    }
    
    /**
     * Set Webhook
     * 
     * @param string $url Webhook URL
     * @param array $options Options (max_connections, allowed_updates)
     * @return array Response
     */
    public function setWebhook($url, $options = []) {
        $params = ['url' => $url];
        
        if (isset($options['max_connections'])) {
            $params['max_connections'] = $options['max_connections'];
        }
        
        if (isset($options['allowed_updates'])) {
            $params['allowed_updates'] = $options['allowed_updates'];
        }
        
        return $this->request('setWebhook', $params);
    }
    
    /**
     * Delete Webhook
     * 
     * @return array Response
     */
    public function deleteWebhook() {
        return $this->request('deleteWebhook');
    }
    
    /**
     * Get Webhook Info
     * 
     * @return array Webhook info
     */
    public function getWebhookInfo() {
        return $this->request('getWebhookInfo');
    }
    
    /**
     * Get Chat Information
     * 
     * @param int|string $chatId Chat ID
     * @return array Chat info
     */
    public function getChat($chatId) {
        return $this->request('getChat', ['chat_id' => $chatId]);
    }
    
    /**
     * Get Chat Member
     * 
     * @param int|string $chatId Chat ID
     * @param int $userId User ID
     * @return array Member info
     */
    public function getChatMember($chatId, $userId) {
        return $this->request('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId
        ]);
    }
    
    /**
     * Answer Callback Query
     * 
     * @param string $callbackQueryId Callback query ID
     * @param string $text Text to show
     * @param bool $showAlert Show alert
     * @return array Response
     */
    public function answerCallbackQuery($callbackQueryId, $text = '', $showAlert = false) {
        return $this->request('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert
        ]);
    }
    
    /**
     * Send Chat Action
     * 
     * @param int|string $chatId Chat ID
     * @param string $action Action (typing, upload_photo, etc.)
     * @return array Response
     */
    public function sendChatAction($chatId, $action) {
        return $this->request('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action
        ]);
    }
    
    /**
     * Get File Info
     * 
     * @param string $fileId File ID
     * @return array File info
     */
    public function getFile($fileId) {
        return $this->request('getFile', ['file_id' => $fileId]);
    }
    
    /**
     * Get File URL
     * 
     * @param string $filePath File path
     * @return string File URL
     */
    public function getFileUrl($filePath) {
        return 'https://api.telegram.org/file/bot' . $this->botToken . '/' . $filePath;
    }
    
    /**
     * Get Bot Information
     * 
     * @return array Bot info
     */
    public function getMe() {
        return $this->request('getMe');
    }
    
    /**
     * Process Incoming Webhook
     * 
     * @return array|null Update data
     */
    public function processWebhook() {
        $input = file_get_contents('php://input');
        if (empty($input)) {
            return null;
        }
        
        return json_decode($input, true);
    }
    
    /**
     * Get Message from Update
     * 
     * @param array $update Update data
     * @return array|null Message
     */
    public function getMessageFromUpdate($update) {
        if (isset($update['message'])) {
            return $update['message'];
        }
        
        if (isset($update['callback_query']['message'])) {
            return $update['callback_query']['message'];
        }
        
        return null;
    }
    
    /**
     * Get Command from Text
     * 
     * @param string $text Message text
     * @return string|null Command name
     */
    public function getCommand($text) {
        if (preg_match('/^\/([a-zA-Z0-9_]+)/', $text, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Get Command Arguments
     * 
     * @param string $text Message text
     * @return string Arguments
     */
    public function getCommandArgs($text) {
        if (preg_match('/^\/([a-zA-Z0-9_]+)\s+(.*)/', $text, $matches)) {
            return $matches[2];
        }
        
        return '';
    }
    
    /**
     * Handle Command
     * 
     * @param string $command Command name
     * @param string $args Arguments
     * @param int|string $chatId Chat ID
     * @param array $options Options
     * @return array Response
     */
    private function handleCommand($command, $args, $chatId, $options = []) {
        switch ($command) {
            case 'start':
                return $this->sendMessage($chatId, "Welcome to API Master Bot! 🚀\n\nAvailable commands:\n/help - Show help\n/about - About this bot", $options);
            case 'help':
                return $this->sendMessage($chatId, "📖 *Available Commands*\n\n/start - Start the bot\n/help - Show this help\n/about - About this bot", $options);
            case 'about':
                return $this->sendMessage($chatId, "API Master Module\nVersion: 1.0.0\n\nTelegram Bot Integration", $options);
            default:
                return $this->sendMessage($chatId, "Unknown command: /{$command}\nType /help for available commands", $options);
        }
    }
}