<?php
/**
 * API Master Module - PayPal API
 * Ödeme işlemleri, abonelikler, faturalar ve webhook yönetimi
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_PayPal implements APIMaster_APIInterface {
    
    /**
     * API Endpoints
     * @var array
     */
    private $endpoints = [
        'live' => 'https://api.paypal.com/v1/',
        'sandbox' => 'https://api.sandbox.paypal.com/v1/'
    ];
    
    /**
     * Client ID
     * @var string|null
     */
    private $clientId = null;
    
    /**
     * Client Secret
     * @var string|null
     */
    private $clientSecret = null;
    
    /**
     * API Key (alias for client ID)
     * @var string|null
     */
    private $apiKey = null;
    
    /**
     * Access token
     * @var string|null
     */
    private $accessToken = null;
    
    /**
     * Token expiry time
     * @var int|null
     */
    private $tokenExpiry = null;
    
    /**
     * Mode (live/sandbox)
     * @var string
     */
    private $mode = 'sandbox';
    
    /**
     * Default currency
     * @var string
     */
    private $currency = 'USD';
    
    /**
     * Current model (for interface compatibility)
     * @var string|null
     */
    private $model = null;
    
    /**
     * Timeout in seconds
     * @var int
     */
    private $timeout = 60;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct(array $config = []) {
        if (isset($config['client_id'])) {
            $this->clientId = $config['client_id'];
            $this->apiKey = $config['client_id'];
        }
        
        if (isset($config['client_secret'])) {
            $this->clientSecret = $config['client_secret'];
        }
        
        if (isset($config['api_key'])) {
            $this->apiKey = $config['api_key'];
            $this->clientId = $config['api_key'];
        }
        
        if (isset($config['mode'])) {
            $this->mode = $config['mode'] === 'live' ? 'live' : 'sandbox';
        }
        
        if (isset($config['currency'])) {
            $this->currency = $config['currency'];
        }
        
        if (isset($config['timeout'])) {
            $this->timeout = (int) $config['timeout'];
        }
    }
    
    /**
     * Set API Key
     * 
     * @param string $apiKey
     * @return self
     */
    public function setApiKey($apiKey) {
        $this->apiKey = $apiKey;
        $this->clientId = $apiKey;
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
     * Complete method - Create payment
     * 
     * @param string $prompt Amount (numeric) or description
     * @param array $options Options (return_url, cancel_url, currency, description)
     * @return array Payment result
     */
    public function complete($prompt, $options = []) {
        if (!$this->clientId || !$this->clientSecret) {
            return ['error' => 'Client ID and Client Secret are required'];
        }
        
        $amount = is_numeric($prompt) ? (float) $prompt : 0;
        $description = is_numeric($prompt) ? ($options['description'] ?? 'Payment') : $prompt;
        
        if ($amount <= 0 && isset($options['amount'])) {
            $amount = (float) $options['amount'];
        }
        
        if ($amount <= 0) {
            return ['error' => 'Valid amount is required'];
        }
        
        $currency = $options['currency'] ?? $this->currency;
        $returnUrl = $options['return_url'] ?? '';
        $cancelUrl = $options['cancel_url'] ?? '';
        
        if (empty($returnUrl) || empty($cancelUrl)) {
            return ['error' => 'return_url and cancel_url are required'];
        }
        
        return $this->createPayment($amount, $currency, $returnUrl, $cancelUrl, $options);
    }
    
    /**
     * Stream method (not supported)
     * 
     * @param string $prompt Amount or description
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
     * Get Available Models
     * 
     * @return array
     */
    public function getModels() {
        return [
            'payment' => 'Create Payment',
            'subscription' => 'Create Subscription',
            'invoice' => 'Create Invoice',
            'refund' => 'Create Refund'
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
            'chat' => false,
            'completion' => true,
            'models' => true,
            'max_tokens' => null,
            'functions' => false,
            'vision' => false,
            'supported_features' => [
                'payments',
                'subscriptions',
                'invoices',
                'refunds',
                'webhooks'
            ]
        ];
    }
    
    /**
     * Check API Health
     * 
     * @return array
     */
    public function checkHealth() {
        if (!$this->clientId || !$this->clientSecret) {
            return ['status' => 'error', 'message' => 'Client ID and Secret not configured'];
        }
        
        $token = $this->getAccessToken();
        
        if ($token) {
            return ['status' => 'healthy', 'message' => 'API is working'];
        }
        
        return ['status' => 'error', 'message' => 'Failed to get access token'];
    }
    
    /**
     * Chat method (not supported)
     * 
     * @param array $messages Messages array
     * @param array $options Options
     * @param callable|null $callback Callback for streaming
     * @return array
     */
    public function chat($messages, $options = [], $callback = null) {
        return [
            'error' => 'Chat method is not supported by PayPal API',
            'supported_methods' => ['complete', 'createPayment', 'createSubscription']
        ];
    }
    
    /**
     * Extract Text from Response
     * 
     * @param array $response Payment response
     * @return string
     */
    public function extractText($response) {
        if (isset($response['error'])) {
            return $response['error'];
        }
        
        if (isset($response['data']['id'])) {
            return sprintf(
                "Payment created: %s - Status: %s",
                $response['data']['id'],
                $response['data']['status'] ?? 'CREATED'
            );
        }
        
        if (isset($response['payment_id'])) {
            return "Payment ID: " . $response['payment_id'];
        }
        
        return json_encode($response);
    }
    
    /**
     * Get Base URL
     * 
     * @return string
     */
    private function getBaseUrl() {
        return $this->endpoints[$this->mode];
    }
    
    /**
     * Get Access Token
     * 
     * @return string|null
     */
    private function getAccessToken() {
        // Check if token is still valid
        if ($this->accessToken && $this->tokenExpiry && time() < $this->tokenExpiry) {
            return $this->accessToken;
        }
        
        $credentials = base64_encode($this->clientId . ':' . $this->clientSecret);
        $url = $this->getBaseUrl() . 'oauth2/token';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $this->accessToken = $data['access_token'];
            $this->tokenExpiry = time() + ($data['expires_in'] ?? 3600);
            return $this->accessToken;
        }
        
        return null;
    }
    
    /**
     * Make API Request
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method
     * @return array Response
     */
    private function makeRequest($endpoint, $data = [], $method = 'GET') {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['error' => 'Failed to get access token'];
        }
        
        $url = $this->getBaseUrl() . ltrim($endpoint, '/');
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            default:
                if (!empty($data)) {
                    $url .= '?' . http_build_query($data);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['error' => $error, 'code' => $httpCode];
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $responseData,
                'code' => $httpCode
            ];
        }
        
        return [
            'error' => $responseData['message'] ?? 'Request failed',
            'name' => $responseData['name'] ?? null,
            'details' => $responseData['details'] ?? null,
            'code' => $httpCode
        ];
    }
    
    /**
     * Create Payment
     * 
     * @param float $amount Amount
     * @param string $currency Currency code
     * @param string $returnUrl Return URL after success
     * @param string $cancelUrl Cancel URL
     * @param array $options Additional options
     * @return array Payment result
     */
    public function createPayment($amount, $currency, $returnUrl, $cancelUrl, $options = []) {
        $intent = $options['intent'] ?? 'CAPTURE';
        $description = $options['description'] ?? 'Payment';
        $referenceId = $options['reference_id'] ?? null;
        
        $data = [
            'intent' => $intent,
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amount, 2, '.', '')
                    ],
                    'description' => $description
                ]
            ],
            'application_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'brand_name' => $options['brand_name'] ?? 'APIMaster',
                'locale' => $options['locale'] ?? 'tr-TR',
                'landing_page' => $options['landing_page'] ?? 'NO_PREFERENCE',
                'shipping_preference' => $options['shipping_preference'] ?? 'NO_SHIPPING',
                'user_action' => $options['user_action'] ?? 'PAY_NOW'
            ]
        ];
        
        if ($referenceId) {
            $data['purchase_units'][0]['reference_id'] = $referenceId;
        }
        
        $result = $this->makeRequest('checkout/orders', $data, 'POST');
        
        if (isset($result['success']) && $result['success'] && isset($result['data']['id'])) {
            $result['payment_id'] = $result['data']['id'];
            $result['approval_url'] = $this->getApprovalUrl($result['data']);
        }
        
        return $result;
    }
    
    /**
     * Get Approval URL from response
     * 
     * @param array $data Response data
     * @return string|null
     */
    private function getApprovalUrl($data) {
        if (isset($data['links'])) {
            foreach ($data['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    return $link['href'];
                }
            }
        }
        return null;
    }
    
    /**
     * Capture Payment
     * 
     * @param string $orderId Order ID
     * @return array Capture result
     */
    public function capturePayment($orderId) {
        return $this->makeRequest("checkout/orders/{$orderId}/capture", [], 'POST');
    }
    
    /**
     * Authorize Payment
     * 
     * @param string $orderId Order ID
     * @return array Authorization result
     */
    public function authorizePayment($orderId) {
        return $this->makeRequest("checkout/orders/{$orderId}/authorize", [], 'POST');
    }
    
    /**
     * Get Payment Details
     * 
     * @param string $orderId Order ID
     * @return array Payment details
     */
    public function getPaymentDetails($orderId) {
        return $this->makeRequest("checkout/orders/{$orderId}", [], 'GET');
    }
    
    /**
     * Cancel Payment
     * 
     * @param string $orderId Order ID
     * @return array Cancel result
     */
    public function cancelPayment($orderId) {
        return $this->makeRequest("checkout/orders/{$orderId}/cancel", [], 'POST');
    }
    
    /**
     * Create Refund
     * 
     * @param string $captureId Capture ID
     * @param float $amount Refund amount
     * @param string $currency Currency code
     * @param string $reason Refund reason
     * @return array Refund result
     */
    public function createRefund($captureId, $amount, $currency, $reason = '') {
        $data = [
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
                'currency_code' => $currency
            ],
            'note' => $reason,
            'invoice_id' => 'REF-' . strtoupper(uniqid())
        ];
        
        return $this->makeRequest("payments/captures/{$captureId}/refund", $data, 'POST');
    }
    
    /**
     * Get Refund Details
     * 
     * @param string $refundId Refund ID
     * @return array Refund details
     */
    public function getRefundDetails($refundId) {
        return $this->makeRequest("payments/refunds/{$refundId}", [], 'GET');
    }
    
    /**
     * Create Subscription Plan
     * 
     * @param string $name Plan name
     * @param string $description Plan description
     * @param float $amount Amount
     * @param string $currency Currency
     * @param string $interval Interval (DAY, WEEK, MONTH, YEAR)
     * @param int $frequency Frequency
     * @return array Plan result
     */
    public function createPlan($name, $description, $amount, $currency, $interval, $frequency = 1) {
        $data = [
            'product_id' => $this->getOrCreateProduct($name),
            'name' => $name,
            'description' => $description,
            'status' => 'ACTIVE',
            'billing_cycles' => [
                [
                    'frequency' => [
                        'interval_unit' => $interval,
                        'interval_count' => $frequency
                    ],
                    'tenure_type' => 'REGULAR',
                    'sequence' => 1,
                    'total_cycles' => 0,
                    'pricing_scheme' => [
                        'fixed_price' => [
                            'value' => number_format($amount, 2, '.', ''),
                            'currency_code' => $currency
                        ]
                    ]
                ]
            ],
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'setup_fee' => [
                    'value' => '0',
                    'currency_code' => $currency
                ],
                'setup_fee_failure_action' => 'CONTINUE',
                'payment_failure_threshold' => 3
            ]
        ];
        
        return $this->makeRequest('billing/plans', $data, 'POST');
    }
    
    /**
     * Get or Create Product
     * 
     * @param string $name Product name
     * @return string|null Product ID
     */
    private function getOrCreateProduct($name) {
        $data = [
            'name' => $name,
            'type' => 'SERVICE',
            'category' => 'SOFTWARE'
        ];
        
        $result = $this->makeRequest('catalogs/products', $data, 'POST');
        
        if (isset($result['success']) && $result['success'] && isset($result['data']['id'])) {
            return $result['data']['id'];
        }
        
        return null;
    }
    
    /**
     * Create Subscription
     * 
     * @param string $planId Plan ID
     * @param string $subscriberEmail Subscriber email
     * @param string $returnUrl Return URL
     * @param string $cancelUrl Cancel URL
     * @return array Subscription result
     */
    public function createSubscription($planId, $subscriberEmail, $returnUrl, $cancelUrl) {
        $data = [
            'plan_id' => $planId,
            'subscriber' => [
                'email_address' => $subscriberEmail
            ],
            'application_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'brand_name' => 'APIMaster',
                'locale' => 'tr-TR',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'SUBSCRIBE_NOW'
            ]
        ];
        
        $result = $this->makeRequest('billing/subscriptions', $data, 'POST');
        
        if (isset($result['success']) && $result['success'] && isset($result['data']['id'])) {
            $result['subscription_id'] = $result['data']['id'];
            $result['approval_url'] = $this->getApprovalUrl($result['data']);
        }
        
        return $result;
    }
    
    /**
     * Get Subscription Details
     * 
     * @param string $subscriptionId Subscription ID
     * @return array Subscription details
     */
    public function getSubscriptionDetails($subscriptionId) {
        return $this->makeRequest("billing/subscriptions/{$subscriptionId}", [], 'GET');
    }
    
    /**
     * Cancel Subscription
     * 
     * @param string $subscriptionId Subscription ID
     * @param string $reason Cancellation reason
     * @return array Cancel result
     */
    public function cancelSubscription($subscriptionId, $reason) {
        return $this->makeRequest("billing/subscriptions/{$subscriptionId}/cancel", ['reason' => $reason], 'POST');
    }
    
    /**
     * Create Webhook
     * 
     * @param string $url Webhook URL
     * @param array $eventTypes Event types
     * @return array Webhook result
     */
    public function createWebhook($url, $eventTypes = []) {
        $data = [
            'url' => $url,
            'event_types' => []
        ];
        
        if (empty($eventTypes)) {
            $eventTypes = ['*'];
        }
        
        foreach ($eventTypes as $type) {
            $data['event_types'][] = ['name' => $type];
        }
        
        return $this->makeRequest('notifications/webhooks', $data, 'POST');
    }
    
    /**
     * List Webhooks
     * 
     * @return array Webhook list
     */
    public function listWebhooks() {
        return $this->makeRequest('notifications/webhooks', [], 'GET');
    }
    
    /**
     * Delete Webhook
     * 
     * @param string $webhookId Webhook ID
     * @return array Delete result
     */
    public function deleteWebhook($webhookId) {
        return $this->makeRequest("notifications/webhooks/{$webhookId}", [], 'DELETE');
    }
}