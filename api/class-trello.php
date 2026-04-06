<?php
/**
 * Trello API Class for Masal Panel
 * 
 * Project management, boards, lists, cards
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Direct access prevention
}

class APIMaster_API_Trello implements APIMaster_APIInterface
{
    /**
     * API Key
     * @var string
     */
    private $apiKey;
    
    /**
     * API Token
     * @var string
     */
    private $apiToken;
    
    /**
     * Model (default board ID)
     * @var string
     */
    private $model;
    
    /**
     * Config array
     * @var array
     */
    private $config;
    
    /**
     * API base URL
     * @var string
     */
    private $apiUrl = 'https://api.trello.com/1';
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->apiToken = $this->config['api_token'] ?? '';
        $this->model = $this->config['default_board_id'] ?? '';
    }
    
    /**
     * Load configuration from JSON file
     * 
     * @return array Configuration data
     */
    private function loadConfig()
    {
        $configFile = __DIR__ . '/config/trello.json';
        
        if (file_exists($configFile)) {
            $content = file_get_contents($configFile);
            return json_decode($content, true) ?: [];
        }
        
        return [];
    }
    
    /**
     * Log error to file
     * 
     * @param string $message Error message
     * @param array $context Additional context
     */
    private function logError($message, $context = [])
    {
        $logDir = __DIR__ . '/logs';
        $logFile = $logDir . '/trello-error.log';
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logEntry = date('Y-m-d H:i:s') . ' - ' . $message;
        if (!empty($context)) {
            $logEntry .= ' - ' . json_encode($context);
        }
        $logEntry .= PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Make curl request to Trello API
     * 
     * @param string $url Full URL
     * @param string $method HTTP method
     * @param array $data POST/PUT data
     * @return array|false Response data
     */
    private function curlRequest($url, $method = 'GET', $data = [])
    {
        $ch = curl_init();
        
        $method = strtoupper($method);
        
        // Set common options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        // Set headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json'
        ]);
        
        // Set method specific options
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        curl_close($ch);
        
        if ($curlError) {
            $this->logError('CURL Error', ['error' => $curlError, 'url' => $url]);
            return false;
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMsg = isset($decoded['message']) ? $decoded['message'] : 'HTTP ' . $httpCode;
            $this->logError('API Error', ['status' => $httpCode, 'error' => $errorMsg, 'url' => $url]);
            return false;
        }
        
        return $decoded;
    }
    
    /**
     * Build Trello API URL
     * 
     * @param string $endpoint API endpoint
     * @param array $params Additional parameters
     * @return string Full URL
     */
    private function buildUrl($endpoint, $params = [])
    {
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        
        $params['key'] = $this->apiKey;
        $params['token'] = $this->apiToken;
        
        return $url . '?' . http_build_query($params);
    }
    
    /**
     * Make API request
     * 
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $data Request data
     * @param array $params URL parameters
     * @return array|false Response data
     */
    private function request($endpoint, $method = 'GET', $data = [], $params = [])
    {
        $url = $this->buildUrl($endpoint, $params);
        return $this->curlRequest($url, $method, $data);
    }
    
    /**
     * Set API key
     * 
     * @param string $apiKey API key
     * @return void
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }
    
    /**
     * Set model (board ID)
     * 
     * @param string $model Board ID
     * @return void
     */
    public function setModel($model)
    {
        $this->model = $model;
    }
    
    /**
     * Get current model
     * 
     * @return string Current board ID
     */
    public function getModel()
    {
        return $this->model;
    }
    
    /**
     * Complete request (generic method)
     * 
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @return array|false Response
     */
    public function complete($endpoint, $params = [])
    {
        return $this->request($endpoint, 'GET', [], $params);
    }
    
    /**
     * Stream (not supported by Trello)
     * 
     * @param string $endpoint API endpoint
     * @param callable $callback Callback function
     * @return void
     */
    public function stream($endpoint, $callback)
    {
        $this->logError('Stream method not supported by Trello API');
    }
    
    /**
     * Get available models (boards)
     * 
     * @return array|false List of boards
     */
    public function getModels()
    {
        return $this->request('members/me/boards', 'GET');
    }
    
    /**
     * Get API capabilities
     * 
     * @return array Capabilities list
     */
    public function getCapabilities()
    {
        return [
            'boards' => ['create', 'read', 'update', 'delete'],
            'lists' => ['create', 'read', 'update'],
            'cards' => ['create', 'read', 'update', 'delete', 'move'],
            'labels' => ['create', 'read', 'add', 'remove'],
            'members' => ['read', 'add', 'remove'],
            'comments' => ['create', 'read'],
            'webhooks' => ['create', 'read'],
            'search' => ['read']
        ];
    }
    
    /**
     * Check API health
     * 
     * @return bool Connection successful
     */
    public function checkHealth()
    {
        $response = $this->request('members/me', 'GET');
        return ($response !== false && isset($response['id']));
    }
    
    /**
     * Chat (not supported by Trello, use addComment instead)
     * 
     * @param string $message Message content
     * @param array $context Context parameters
     * @return array|false Response
     */
    public function chat($message, $context = [])
    {
        $cardId = $context['card_id'] ?? $this->model;
        if (!$cardId) {
            $this->logError('Chat method requires card_id in context');
            return false;
        }
        
        return $this->addComment($cardId, $message);
    }
    
    /**
     * Extract text from response
     * 
     * @param array $response API response
     * @return string Extracted text
     */
    public function extractText($response)
    {
        if (!is_array($response)) {
            return '';
        }
        
        if (isset($response['name'])) {
            return $response['name'];
        }
        
        if (isset($response['desc'])) {
            return $response['desc'];
        }
        
        return json_encode($response);
    }
    
    // ========== TRELLO SPECIFIC METHODS ==========
    
    /**
     * Create board
     * 
     * @param string $name Board name
     * @param array $options Board options
     * @return array|false Board data
     */
    public function createBoard($name, $options = [])
    {
        $data = [
            'name' => $name,
            'defaultLabels' => $options['default_labels'] ?? true,
            'defaultLists' => $options['default_lists'] ?? true,
            'desc' => $options['description'] ?? '',
            'prefs_permissionLevel' => $options['permission_level'] ?? 'private',
            'prefs_voting' => $options['voting'] ?? 'disabled',
            'prefs_comments' => $options['comments'] ?? 'members',
            'prefs_invitations' => $options['invitations'] ?? 'members',
            'prefs_selfJoin' => $options['self_join'] ?? true,
            'prefs_cardCovers' => $options['card_covers'] ?? true,
            'prefs_background' => $options['background'] ?? 'blue',
        ];
        
        return $this->request('boards', 'POST', $data);
    }
    
    /**
     * Get board
     * 
     * @param string $boardId Board ID
     * @param array $fields Fields to fetch
     * @return array|false Board data
     */
    public function getBoard($boardId, $fields = [])
    {
        $params = [];
        if (!empty($fields)) {
            $params['fields'] = implode(',', $fields);
        }
        
        return $this->request('boards/' . $boardId, 'GET', [], $params);
    }
    
    /**
     * Update board
     * 
     * @param string $boardId Board ID
     * @param array $updateData Update data
     * @return array|false Updated board
     */
    public function updateBoard($boardId, $updateData)
    {
        return $this->request('boards/' . $boardId, 'PUT', $updateData);
    }
    
    /**
     * Delete board
     * 
     * @param string $boardId Board ID
     * @return bool Success
     */
    public function deleteBoard($boardId)
    {
        $response = $this->request('boards/' . $boardId, 'DELETE');
        return ($response !== false);
    }
    
    /**
     * Get board lists
     * 
     * @param string $boardId Board ID
     * @return array|false Lists
     */
    public function getBoardLists($boardId)
    {
        return $this->request('boards/' . $boardId . '/lists', 'GET');
    }
    
    /**
     * Create list
     * 
     * @param string $boardId Board ID
     * @param string $name List name
     * @param array $options List options
     * @return array|false List data
     */
    public function createList($boardId, $name, $options = [])
    {
        $data = [
            'name' => $name,
            'idBoard' => $boardId,
            'pos' => $options['position'] ?? 'bottom',
        ];
        
        return $this->request('lists', 'POST', $data);
    }
    
    /**
     * Get list
     * 
     * @param string $listId List ID
     * @return array|false List data
     */
    public function getList($listId)
    {
        return $this->request('lists/' . $listId, 'GET');
    }
    
    /**
     * Update list
     * 
     * @param string $listId List ID
     * @param array $updateData Update data
     * @return array|false Updated list
     */
    public function updateList($listId, $updateData)
    {
        return $this->request('lists/' . $listId, 'PUT', $updateData);
    }
    
    /**
     * Get list cards
     * 
     * @param string $listId List ID
     * @return array|false Cards
     */
    public function getListCards($listId)
    {
        return $this->request('lists/' . $listId . '/cards', 'GET');
    }
    
    /**
     * Create card
     * 
     * @param string $listId List ID
     * @param string $name Card name
     * @param array $options Card options
     * @return array|false Card data
     */
    public function createCard($listId, $name, $options = [])
    {
        $data = [
            'name' => $name,
            'idList' => $listId,
            'desc' => $options['description'] ?? '',
            'pos' => $options['position'] ?? 'top',
            'due' => $options['due_date'] ?? null,
            'dueComplete' => $options['due_complete'] ?? false,
        ];
        
        if (!empty($options['member_ids'])) {
            $data['idMembers'] = is_array($options['member_ids']) ? implode(',', $options['member_ids']) : $options['member_ids'];
        }
        
        if (!empty($options['label_ids'])) {
            $data['idLabels'] = is_array($options['label_ids']) ? implode(',', $options['label_ids']) : $options['label_ids'];
        }
        
        $data = array_filter($data);
        
        return $this->request('cards', 'POST', $data);
    }
    
    /**
     * Get card
     * 
     * @param string $cardId Card ID
     * @param array $fields Fields to fetch
     * @return array|false Card data
     */
    public function getCard($cardId, $fields = [])
    {
        $params = [];
        if (!empty($fields)) {
            $params['fields'] = implode(',', $fields);
        }
        
        return $this->request('cards/' . $cardId, 'GET', [], $params);
    }
    
    /**
     * Update card
     * 
     * @param string $cardId Card ID
     * @param array $updateData Update data
     * @return array|false Updated card
     */
    public function updateCard($cardId, $updateData)
    {
        return $this->request('cards/' . $cardId, 'PUT', $updateData);
    }
    
    /**
     * Delete card
     * 
     * @param string $cardId Card ID
     * @return bool Success
     */
    public function deleteCard($cardId)
    {
        $response = $this->request('cards/' . $cardId, 'DELETE');
        return ($response !== false);
    }
    
    /**
     * Move card to another list
     * 
     * @param string $cardId Card ID
     * @param string $newListId New list ID
     * @return array|false Updated card
     */
    public function moveCard($cardId, $newListId)
    {
        return $this->updateCard($cardId, ['idList' => $newListId]);
    }
    
    /**
     * Add comment to card
     * 
     * @param string $cardId Card ID
     * @param string $comment Comment text
     * @return array|false Comment data
     */
    public function addComment($cardId, $comment)
    {
        $data = ['text' => $comment];
        return $this->request('cards/' . $cardId . '/actions/comments', 'POST', $data);
    }
    
    /**
     * Get card comments
     * 
     * @param string $cardId Card ID
     * @return array|false Comments
     */
    public function getComments($cardId)
    {
        return $this->request('cards/' . $cardId . '/actions', 'GET', [], ['filter' => 'commentCard']);
    }
    
    /**
     * Add label to card
     * 
     * @param string $cardId Card ID
     * @param string $labelId Label ID
     * @return array|false Updated card
     */
    public function addLabel($cardId, $labelId)
    {
        $data = ['value' => $labelId];
        return $this->request('cards/' . $cardId . '/idLabels', 'POST', $data);
    }
    
    /**
     * Remove label from card
     * 
     * @param string $cardId Card ID
     * @param string $labelId Label ID
     * @return bool Success
     */
    public function removeLabel($cardId, $labelId)
    {
        $response = $this->request('cards/' . $cardId . '/idLabels/' . $labelId, 'DELETE');
        return ($response !== false);
    }
    
    /**
     * Add member to card
     * 
     * @param string $cardId Card ID
     * @param string $memberId Member ID
     * @return array|false Updated card
     */
    public function addMember($cardId, $memberId)
    {
        $data = ['value' => $memberId];
        return $this->request('cards/' . $cardId . '/idMembers', 'POST', $data);
    }
    
    /**
     * Remove member from card
     * 
     * @param string $cardId Card ID
     * @param string $memberId Member ID
     * @return bool Success
     */
    public function removeMember($cardId, $memberId)
    {
        $response = $this->request('cards/' . $cardId . '/idMembers/' . $memberId, 'DELETE');
        return ($response !== false);
    }
    
    /**
     * Create label
     * 
     * @param string $boardId Board ID
     * @param string $name Label name
     * @param string $color Label color
     * @return array|false Label data
     */
    public function createLabel($boardId, $name, $color = 'yellow')
    {
        $data = [
            'name' => $name,
            'color' => $color,
            'idBoard' => $boardId,
        ];
        
        return $this->request('labels', 'POST', $data);
    }
    
    /**
     * Get board members
     * 
     * @param string $boardId Board ID
     * @return array|false Members
     */
    public function getBoardMembers($boardId)
    {
        return $this->request('boards/' . $boardId . '/members', 'GET');
    }
    
    /**
     * Invite to board
     * 
     * @param string $boardId Board ID
     * @param string $email Email address
     * @param string $type Invite type (normal, admin)
     * @return array|false Invite result
     */
    public function inviteToBoard($boardId, $email, $type = 'normal')
    {
        $data = [
            'email' => $email,
            'type' => $type,
        ];
        
        return $this->request('boards/' . $boardId . '/members', 'PUT', $data);
    }
    
    /**
     * Get user's boards
     * 
     * @param string|null $username Username (optional)
     * @return array|false Boards
     */
    public function getMyBoards($username = null)
    {
        $member = $username ?? 'me';
        return $this->request('members/' . $member . '/boards', 'GET');
    }
    
    /**
     * Create webhook
     * 
     * @param string $callbackUrl Callback URL
     * @param string $modelId Model ID (board, card, etc)
     * @return array|false Webhook data
     */
    public function createWebhook($callbackUrl, $modelId)
    {
        $data = [
            'description' => 'API Master Webhook',
            'callbackURL' => $callbackUrl,
            'idModel' => $modelId,
        ];
        
        return $this->request('webhooks', 'POST', $data);
    }
    
    /**
     * Search cards
     * 
     * @param string $query Search query
     * @param array $options Search options
     * @return array|false Search results
     */
    public function search($query, $options = [])
    {
        $params = [
            'query' => $query,
            'modelTypes' => $options['model_types'] ?? 'cards',
            'board_id' => $options['board_id'] ?? null,
            'card_fields' => $options['card_fields'] ?? 'all',
        ];
        
        $params = array_filter($params);
        
        return $this->request('search', 'GET', [], $params);
    }
    
    /**
     * Test connection
     * 
     * @return bool Connection successful
     */
    public function testConnection()
    {
        $response = $this->request('members/me', 'GET');
        return ($response !== false && isset($response['id']));
    }
}