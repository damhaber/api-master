<?php
/**
 * API Master Module - Stripe API
 * Ödeme işlemleri, abonelikler, müşteri yönetimi ve webhook işlemleri
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_Stripe implements APIMaster_APIInterface {
    
    /**
     * API Key (Secret Key)
     * @var string|null
     */
    private $apiKey = null;
    
    /**
     * Publishable Key
     * @var string|null
     */
    private $publishableKey = null;
    
    /**
     * API Version
     * @var string
     */
    private $apiVersion = '2023-10-16';
    
    /**
     * Test mode flag
     * @var bool
     */
    private $testMode = true;
    
    /**
     * Test API keys
     * @var string|null
     */
    private $testSecretKey = null;
    private $testPublicKey = null;
    
    /**
     * Live API keys
     * @var string|null
     */
    private $liveSecretKey = null;
    private $livePublicKey = null;
    
    /**
     * Webhook secret
     * @var string|null
     */
    private $webhookSecret = null;
    
    /**
     * API URL
     * @var string
     */
    private $apiUrl = 'https://api.stripe.com/v1';
    
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
        // Set API keys from config
        $this->testSecretKey = $config['test_secret_key'] ?? $config['test_secret'] ?? null;
        $this->testPublicKey = $config['test_public_key'] ?? $config['test_publishable_key'] ?? null;
        $this->liveSecretKey = $config['live_secret_key'] ?? $config['live_secret'] ?? null;
        $this->livePublicKey = $config['live_public_key'] ?? $config['live_publishable_key'] ?? null;
        $this->webhookSecret = $config['webhook_secret'] ?? null;
        $this->testMode = $config['test_mode'] ?? $config['testMode'] ?? true;
        
        if (isset($config['api_key'])) {
            $this->setApiKey($config['api_key']);
        } elseif ($this->testMode && $this->testSecretKey) {
            $this->setApiKey($this->testSecretKey);
        } elseif (!$this->testMode && $this->liveSecretKey) {
            $this->setApiKey($this->liveSecretKey);
        }
        
        if (isset($config['api_version'])) {
            $this->apiVersion = $config['api_version'];
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
        return $this;
    }
    
    /**
     * Get Publishable Key
     * 
     * @return string|null
     */
    public function getPublishableKey() {
        if ($this->testMode) {
            return $this->testPublicKey;
        }
        return $this->livePublicKey;
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
     * Complete method - Create payment intent
     * 
     * @param string $prompt Amount or description
     * @param array $options Options (amount, currency, description)
     * @return array Payment intent result
     */
    public function complete($prompt, $options = []) {
        if (!$this->apiKey) {
            return ['error' => 'API key is required'];
        }
        
        $amount = is_numeric($prompt) ? (int) ($prompt * 100) : 0;
        $description = is_numeric($prompt) ? ($options['description'] ?? 'Payment') : $prompt;
        
        if ($amount <= 0 && isset($options['amount'])) {
            $amount = (int) ($options['amount'] * 100);
        }
        
        if ($amount <= 0) {
            return ['error' => 'Valid amount is required'];
        }
        
        $currency = $options['currency'] ?? 'usd';
        
        return $this->createPaymentIntent([
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'metadata' => $options['metadata'] ?? []
        ]);
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
            'payment_intent' => 'Create Payment Intent',
            'customer' => 'Create Customer',
            'subscription' => 'Create Subscription',
            'product' => 'Create Product',
            'price' => 'Create Price'
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
                'customers',
                'products',
                'prices',
                'refunds',
                'webhooks',
                'payment_methods'
            ]
        ];
    }
    
    /**
     * Check API Health
     * 
     * @return array
     */
    public function checkHealth() {
        if (!$this->apiKey) {
            return ['status' => 'error', 'message' => 'API key not configured'];
        }
        
        $result = $this->request('balance', 'GET');
        
        if (isset($result['error'])) {
            return ['status' => 'error', 'message' => $result['error']];
        }
        
        if (isset($result['available'])) {
            return ['status' => 'healthy', 'message' => 'API is working'];
        }
        
        return ['status' => 'error', 'message' => 'API returned unexpected response'];
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
            'error' => 'Chat method is not supported by Stripe API',
            'supported_methods' => ['complete', 'createPaymentIntent', 'createCustomer', 'createSubscription']
        ];
    }
    
    /**
     * Extract Text from Response
     * 
     * @param array $response Payment intent response
     * @return string
     */
    public function extractText($response) {
        if (isset($response['error'])) {
            return $response['error'];
        }
        
        if (isset($response['id']) && isset($response['amount'])) {
            return sprintf(
                "Payment Intent: %s - Amount: %.2f %s - Status: %s",
                $response['id'],
                $response['amount'] / 100,
                strtoupper($response['currency']),
                $response['status']
            );
        }
        
        if (isset($response['object']) && $response['object'] === 'customer') {
            return sprintf(
                "Customer: %s - Email: %s",
                $response['id'],
                $response['email'] ?? 'N/A'
            );
        }
        
        return json_encode($response);
    }
    
    /**
     * Make API Request
     * 
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $data Request data
     * @return array Response
     */
    private function request($endpoint, $method = 'GET', $data = []) {
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Stripe-Version: ' . $this->apiVersion,
            'Content-Type: application/x-www-form-urlencoded'
        ];
        
        $ch = curl_init();
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        } elseif ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
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
        
        $data = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return $data;
        }
        
        return [
            'error' => $data['error']['message'] ?? 'Request failed',
            'type' => $data['error']['type'] ?? null,
            'code' => $httpCode
        ];
    }
    
    /**
     * Create Customer
     * 
     * @param array $customerData Customer data (email, name, etc.)
     * @return array Customer data
     */
    public function createCustomer($customerData) {
        if (empty($customerData['email'])) {
            return ['error' => 'Email is required'];
        }
        
        $data = [
            'email' => $customerData['email'],
            'name' => $customerData['name'] ?? '',
            'description' => $customerData['description'] ?? '',
            'phone' => $customerData['phone'] ?? '',
            'metadata' => $customerData['metadata'] ?? []
        ];
        
        if (isset($customerData['payment_method'])) {
            $data['payment_method'] = $customerData['payment_method'];
        }
        
        return $this->request('customers', 'POST', $data);
    }
    
    /**
     * Get Customer
     * 
     * @param string $customerId Customer ID
     * @return array Customer data
     */
    public function getCustomer($customerId) {
        return $this->request('customers/' . $customerId, 'GET');
    }
    
    /**
     * Update Customer
     * 
     * @param string $customerId Customer ID
     * @param array $updateData Update data
     * @return array Updated customer
     */
    public function updateCustomer($customerId, $updateData) {
        return $this->request('customers/' . $customerId, 'POST', $updateData);
    }
    
    /**
     * Delete Customer
     * 
     * @param string $customerId Customer ID
     * @return array Delete result
     */
    public function deleteCustomer($customerId) {
        return $this->request('customers/' . $customerId, 'DELETE');
    }
    
    /**
     * Create Payment Intent
     * 
     * @param array $paymentData Payment data (amount, currency, description)
     * @return array Payment intent
     */
    public function createPaymentIntent($paymentData) {
        if (empty($paymentData['amount'])) {
            return ['error' => 'Amount is required'];
        }
        
        if (empty($paymentData['currency'])) {
            return ['error' => 'Currency is required'];
        }
        
        $data = [
            'amount' => $paymentData['amount'],
            'currency' => strtolower($paymentData['currency']),
            'payment_method_types' => $paymentData['payment_method_types'] ?? ['card'],
            'description' => $paymentData['description'] ?? '',
            'metadata' => $paymentData['metadata'] ?? []
        ];
        
        if (isset($paymentData['customer'])) {
            $data['customer'] = $paymentData['customer'];
        }
        
        if (isset($paymentData['payment_method'])) {
            $data['payment_method'] = $paymentData['payment_method'];
        }
        
        if (isset($paymentData['confirm'])) {
            $data['confirm'] = $paymentData['confirm'] ? 'true' : 'false';
        }
        
        if (isset($paymentData['return_url'])) {
            $data['return_url'] = $paymentData['return_url'];
        }
        
        return $this->request('payment_intents', 'POST', $data);
    }
    
    /**
     * Get Payment Intent
     * 
     * @param string $intentId Payment intent ID
     * @return array Payment intent
     */
    public function getPaymentIntent($intentId) {
        return $this->request('payment_intents/' . $intentId, 'GET');
    }
    
    /**
     * Update Payment Intent
     * 
     * @param string $intentId Payment intent ID
     * @param array $updateData Update data
     * @return array Updated payment intent
     */
    public function updatePaymentIntent($intentId, $updateData) {
        return $this->request('payment_intents/' . $intentId, 'POST', $updateData);
    }
    
    /**
     * Confirm Payment Intent
     * 
     * @param string $intentId Payment intent ID
     * @param array $confirmationData Confirmation data
     * @return array Confirmed payment intent
     */
    public function confirmPaymentIntent($intentId, $confirmationData = []) {
        return $this->request('payment_intents/' . $intentId . '/confirm', 'POST', $confirmationData);
    }
    
    /**
     * Create Subscription
     * 
     * @param array $subscriptionData Subscription data (customer, items)
     * @return array Subscription
     */
    public function createSubscription($subscriptionData) {
        if (empty($subscriptionData['customer'])) {
            return ['error' => 'Customer ID is required'];
        }
        
        if (empty($subscriptionData['items'])) {
            return ['error' => 'Items are required'];
        }
        
        $data = [
            'customer' => $subscriptionData['customer'],
            'items' => $subscriptionData['items'],
            'metadata' => $subscriptionData['metadata'] ?? []
        ];
        
        if (isset($subscriptionData['default_payment_method'])) {
            $data['default_payment_method'] = $subscriptionData['default_payment_method'];
        }
        
        if (isset($subscriptionData['coupon'])) {
            $data['coupon'] = $subscriptionData['coupon'];
        }
        
        if (isset($subscriptionData['trial_period_days'])) {
            $data['trial_period_days'] = $subscriptionData['trial_period_days'];
        }
        
        return $this->request('subscriptions', 'POST', $data);
    }
    
    /**
     * Get Subscription
     * 
     * @param string $subscriptionId Subscription ID
     * @return array Subscription
     */
    public function getSubscription($subscriptionId) {
        return $this->request('subscriptions/' . $subscriptionId, 'GET');
    }
    
    /**
     * Cancel Subscription
     * 
     * @param string $subscriptionId Subscription ID
     * @param bool $atPeriodEnd Cancel at period end
     * @return array Cancelled subscription
     */
    public function cancelSubscription($subscriptionId, $atPeriodEnd = true) {
        $data = [];
        if ($atPeriodEnd) {
            $data['cancel_at_period_end'] = 'true';
        }
        return $this->request('subscriptions/' . $subscriptionId, 'DELETE', $data);
    }
    
    /**
     * Create Product
     * 
     * @param array $productData Product data (name)
     * @return array Product
     */
    public function createProduct($productData) {
        if (empty($productData['name'])) {
            return ['error' => 'Product name is required'];
        }
        
        $data = [
            'name' => $productData['name'],
            'description' => $productData['description'] ?? '',
            'metadata' => $productData['metadata'] ?? []
        ];
        
        if (isset($productData['images'])) {
            $data['images'] = $productData['images'];
        }
        
        return $this->request('products', 'POST', $data);
    }
    
    /**
     * Create Price
     * 
     * @param array $priceData Price data (product, unit_amount, currency)
     * @return array Price
     */
    public function createPrice($priceData) {
        if (empty($priceData['product'])) {
            return ['error' => 'Product ID is required'];
        }
        
        if (empty($priceData['unit_amount'])) {
            return ['error' => 'Unit amount is required'];
        }
        
        if (empty($priceData['currency'])) {
            return ['error' => 'Currency is required'];
        }
        
        $data = [
            'product' => $priceData['product'],
            'unit_amount' => $priceData['unit_amount'],
            'currency' => strtolower($priceData['currency']),
            'metadata' => $priceData['metadata'] ?? []
        ];
        
        if (isset($priceData['recurring'])) {
            $data['recurring'] = $priceData['recurring'];
        }
        
        if (isset($priceData['nickname'])) {
            $data['nickname'] = $priceData['nickname'];
        }
        
        return $this->request('prices', 'POST', $data);
    }
    
    /**
     * Create Refund
     * 
     * @param string $paymentIntentId Payment intent ID
     * @param int|null $amount Refund amount (null = full refund)
     * @return array Refund
     */
    public function createRefund($paymentIntentId, $amount = null) {
        $data = ['payment_intent' => $paymentIntentId];
        
        if ($amount !== null) {
            $data['amount'] = $amount;
        }
        
        return $this->request('refunds', 'POST', $data);
    }
    
    /**
     * Get Balance
     * 
     * @return array Balance data
     */
    public function getBalance() {
        return $this->request('balance', 'GET');
    }
    
    /**
     * List Charges
     * 
     * @param array $filters Filter parameters
     * @return array Charges list
     */
    public function listCharges($filters = []) {
        return $this->request('charges', 'GET', $filters);
    }
    
    /**
     * Attach Payment Method to Customer
     * 
     * @param string $customerId Customer ID
     * @param string $paymentMethodId Payment method ID
     * @return array Attached payment method
     */
    public function attachPaymentMethod($customerId, $paymentMethodId) {
        $data = ['customer' => $customerId];
        return $this->request('payment_methods/' . $paymentMethodId . '/attach', 'POST', $data);
    }
    
    /**
     * List Customer Payment Methods
     * 
     * @param string $customerId Customer ID
     * @param string $type Payment method type
     * @return array Payment methods
     */
    public function listPaymentMethods($customerId, $type = 'card') {
        return $this->request('payment_methods', 'GET', [
            'customer' => $customerId,
            'type' => $type
        ]);
    }
    
    /**
     * Create Webhook Endpoint
     * 
     * @param string $url Webhook URL
     * @param array $events Event types
     * @return array Webhook endpoint
     */
    public function createWebhookEndpoint($url, $events = ['*']) {
        $data = [
            'url' => $url,
            'enabled_events' => $events,
            'api_version' => $this->apiVersion
        ];
        
        return $this->request('webhook_endpoints', 'POST', $data);
    }
    
    /**
     * Verify Webhook Signature
     * 
     * @param string $payload Webhook payload
     * @param string $signature Stripe signature header
     * @param string $timestamp Timestamp header
     * @return array|false Verified event or false
     */
    public function verifyWebhook($payload, $signature, $timestamp = null) {
        if (!$this->webhookSecret) {
            return ['error' => 'Webhook secret not configured'];
        }
        
        $timestamp = $timestamp ?? time();
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);
        
        if (!hash_equals($expectedSignature, $signature)) {
            return ['error' => 'Invalid webhook signature'];
        }
        
        return json_decode($payload, true);
    }
}