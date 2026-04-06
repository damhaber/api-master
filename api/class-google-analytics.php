<?php
/**
 * Google Analytics API Class for Masal Panel
 * 
 * Analytics data, reports, real-time tracking, goals
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Direct access prevention
}

class APIMaster_API_GoogleAnalytics implements APIMaster_APIInterface
{
    /**
     * Service account JSON key data
     * @var array
     */
    private $serviceAccount;
    
    /**
     * View ID (Universal Analytics)
     * @var string
     */
    private $viewId;
    
    /**
     * Property ID (GA4)
     * @var string
     */
    private $propertyId;
    
    /**
     * Model (view_id or property_id)
     * @var string
     */
    private $model;
    
    /**
     * Config array
     * @var array
     */
    private $config;
    
    /**
     * Access token
     * @var string|null
     */
    private $accessToken;
    
    /**
     * Token expiry time
     * @var int
     */
    private $tokenExpiry = 0;
    
    /**
     * API base URL (Universal Analytics)
     * @var string
     */
    private $apiUrl = 'https://analyticsreporting.googleapis.com/v4';
    
    /**
     * GA4 API base URL
     * @var string
     */
    private $ga4ApiUrl = 'https://analyticsdata.googleapis.com/v1beta';
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->config = $this->loadConfig();
        
        $serviceJson = $this->config['service_account_json'] ?? '';
        if (is_string($serviceJson) && !empty($serviceJson)) {
            $this->serviceAccount = json_decode($serviceJson, true);
        } elseif (is_array($serviceJson)) {
            $this->serviceAccount = $serviceJson;
        }
        
        $this->viewId = $this->config['view_id'] ?? '';
        $this->propertyId = $this->config['property_id'] ?? '';
        $this->model = $this->propertyId ?: $this->viewId;
    }
    
    /**
     * Load configuration from JSON file
     * 
     * @return array Configuration data
     */
    private function loadConfig()
    {
        $configFile = __DIR__ . '/config/google-analytics.json';
        
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
        $logFile = $logDir . '/google-analytics-error.log';
        
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
     * Create JWT token for service account authentication
     * 
     * @return string JWT token
     */
    private function createJWT()
    {
        if (!$this->serviceAccount || !isset($this->serviceAccount['client_email'], $this->serviceAccount['private_key'])) {
            $this->logError('Service account configuration missing');
            return '';
        }
        
        $header = json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT'
        ]);
        
        $now = time();
        $claim = json_encode([
            'iss' => $this->serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Claim = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($claim));
        
        $signatureInput = $base64Header . '.' . $base64Claim;
        
        $privateKey = openssl_get_privatekey($this->serviceAccount['private_key']);
        if (!$privateKey) {
            $this->logError('Invalid private key');
            return '';
        }
        
        openssl_sign($signatureInput, $signature, $privateKey, 'SHA256');
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $signatureInput . '.' . $base64Signature;
    }
    
    /**
     * Refresh access token using JWT
     * 
     * @return bool Success
     */
    private function refreshAccessToken()
    {
        // Check if token is still valid
        if ($this->accessToken && $this->tokenExpiry > time()) {
            return true;
        }
        
        $jwt = $this->createJWT();
        if (empty($jwt)) {
            return false;
        }
        
        $url = 'https://oauth2.googleapis.com/token';
        $postFields = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($postFields)
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            $this->logError('Token request CURL error', ['error' => $curlError]);
            return false;
        }
        
        if ($httpCode >= 400) {
            $this->logError('Token request failed', ['status' => $httpCode]);
            return false;
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['access_token'])) {
            $this->accessToken = $data['access_token'];
            $expiresIn = $data['expires_in'] ?? 3600;
            $this->tokenExpiry = time() + $expiresIn - 300; // Refresh 5 minutes early
            return true;
        }
        
        $this->logError('Token response missing access_token', ['response' => substr($response, 0, 500)]);
        return false;
    }
    
    /**
     * Get authentication headers
     * 
     * @return array Headers
     */
    private function getHeaders()
    {
        return [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
    }
    
    /**
     * Make curl request to Google Analytics API
     * 
     * @param string $url Full URL
     * @param string $method HTTP method
     * @param array|string|null $data Request data
     * @param array $headers Additional headers
     * @return array|false Response data
     */
    private function curlRequest($url, $method = 'GET', $data = null, $headers = [])
    {
        // Ensure we have a valid token
        if (!$this->refreshAccessToken()) {
            return false;
        }
        
        $ch = curl_init();
        
        $method = strtoupper($method);
        
        // Merge with default headers
        $defaultHeaders = $this->getHeaders();
        $allHeaders = array_merge($defaultHeaders, $headers);
        
        // Set common options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        
        // Set method specific options
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                }
                break;
            case 'GET':
                // GET is default
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
            $errorMsg = isset($decoded['error']['message']) ? $decoded['error']['message'] : 'HTTP ' . $httpCode;
            $this->logError('API Error', ['status' => $httpCode, 'error' => $errorMsg, 'url' => $url]);
            return false;
        }
        
        return $decoded;
    }
    
    /**
     * API request wrapper (Universal Analytics)
     * 
     * @param string $endpoint Endpoint
     * @param array $data Request data
     * @return array|false Response
     */
    private function request($endpoint, $data = [])
    {
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        $body = !empty($data) ? json_encode($data) : null;
        
        return $this->curlRequest($url, 'POST', $body);
    }
    
    /**
     * API request wrapper for GA4
     * 
     * @param string $endpoint Endpoint
     * @param array $data Request data
     * @return array|false Response
     */
    private function requestGA4($endpoint, $data = [])
    {
        $url = $this->ga4ApiUrl . '/' . ltrim($endpoint, '/');
        $body = !empty($data) ? json_encode($data) : null;
        
        return $this->curlRequest($url, 'POST', $body);
    }
    
    /**
     * Simple GET request
     * 
     * @param string $url Full URL
     * @return array|false Response
     */
    private function getRequest($url)
    {
        return $this->curlRequest($url, 'GET');
    }
    
    /**
     * Set API key (service account JSON)
     * 
     * @param string $apiKey Service account JSON string or file path
     * @return void
     */
    public function setApiKey($apiKey)
    {
        if (is_string($apiKey)) {
            if (file_exists($apiKey)) {
                $content = file_get_contents($apiKey);
                $this->serviceAccount = json_decode($content, true);
            } else {
                $this->serviceAccount = json_decode($apiKey, true);
            }
        } elseif (is_array($apiKey)) {
            $this->serviceAccount = $apiKey;
        }
        
        // Reset token to force refresh
        $this->accessToken = null;
        $this->tokenExpiry = 0;
    }
    
    /**
     * Set model (view_id or property_id)
     * 
     * @param string $model View ID or Property ID
     * @return void
     */
    public function setModel($model)
    {
        $this->model = $model;
        
        // Determine if it's a view_id or property_id
        if (strpos($model, 'properties/') === 0) {
            $this->propertyId = str_replace('properties/', '', $model);
        } elseif (is_numeric($model)) {
            if (strlen($model) === 8) {
                $this->viewId = $model;
            } else {
                $this->propertyId = $model;
            }
        }
    }
    
    /**
     * Get current model
     * 
     * @return string Current view_id or property_id
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
        if (strpos($endpoint, 'ga4') === 0) {
            return $this->requestGA4(substr($endpoint, 4), $params);
        }
        return $this->request($endpoint, $params);
    }
    
    /**
     * Stream (not supported by Google Analytics)
     * 
     * @param string $endpoint API endpoint
     * @param callable $callback Callback function
     * @return void
     */
    public function stream($endpoint, $callback)
    {
        $this->logError('Stream method not supported by Google Analytics API');
    }
    
    /**
     * Get available models (views/properties)
     * 
     * @return array|false List of accounts/properties
     */
    public function getModels()
    {
        // Get first account
        $accounts = $this->getRequest('https://analytics.googleapis.com/v3/management/accounts');
        if (!$accounts || empty($accounts['items'])) {
            return false;
        }
        
        $accountId = $accounts['items'][0]['id'];
        
        // Get properties for account
        $properties = $this->getRequest("https://analytics.googleapis.com/v3/management/accounts/{$accountId}/webproperties");
        if (!$properties || empty($properties['items'])) {
            return false;
        }
        
        $result = [];
        foreach ($properties['items'] as $property) {
            $result[] = [
                'id' => $property['id'],
                'name' => $property['name'],
                'type' => 'universal'
            ];
        }
        
        return $result;
    }
    
    /**
     * Get API capabilities
     * 
     * @return array Capabilities list
     */
    public function getCapabilities()
    {
        return [
            'reports' => ['read'],
            'realtime' => ['read'],
            'ga4_reports' => ['read'],
            'goals' => ['read'],
            'segments' => ['read'],
            'cohort_analysis' => ['read'],
            'user_activity' => ['read'],
            'ecommerce' => ['read'],
            'conversions' => ['read'],
            'traffic_sources' => ['read'],
            'geo_reports' => ['read'],
            'technology_reports' => ['read']
        ];
    }
    
    /**
     * Check API health
     * 
     * @return bool Connection successful
     */
    public function checkHealth()
    {
        return $this->testConnection();
    }
    
    /**
     * Chat (not supported by Google Analytics)
     * 
     * @param string $message Message content
     * @param array $context Context parameters
     * @return array|false Response
     */
    public function chat($message, $context = [])
    {
        $this->logError('Chat method not supported by Google Analytics API');
        return false;
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
        
        if (isset($response['reports'])) {
            $texts = [];
            foreach ($response['reports'] as $report) {
                if (isset($report['data']['rows'])) {
                    foreach ($report['data']['rows'] as $row) {
                        if (isset($row['metrics'][0]['values'])) {
                            $texts[] = implode(', ', $row['metrics'][0]['values']);
                        }
                    }
                }
            }
            return implode('; ', $texts);
        }
        
        if (isset($response['rows'])) {
            $texts = [];
            foreach ($response['rows'] as $row) {
                if (isset($row[0])) {
                    $texts[] = implode(', ', $row);
                }
            }
            return implode('; ', $texts);
        }
        
        return json_encode($response);
    }
    
    // ========== GOOGLE ANALYTICS SPECIFIC METHODS ==========
    
    /**
     * Get report (Universal Analytics)
     * 
     * @param array $metrics Metrics
     * @param array $dimensions Dimensions
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param array $filters Filters
     * @return array|false Report data
     */
    public function getReport($metrics, $dimensions = [], $startDate = '30daysAgo', $endDate = 'today', $filters = [])
    {
        $reportRequest = [
            'viewId' => $this->viewId,
            'dateRanges' => [
                ['startDate' => $startDate, 'endDate' => $endDate]
            ],
            'metrics' => array_map(function($metric) {
                return ['expression' => $metric];
            }, $metrics),
            'dimensions' => array_map(function($dimension) {
                return ['name' => $dimension];
            }, $dimensions),
        ];
        
        if (!empty($filters)) {
            $reportRequest['dimensionFilterClauses'] = [['filters' => $filters]];
        }
        
        $data = ['reportRequests' => [$reportRequest]];
        
        return $this->request('reports:batchGet', $data);
    }
    
    /**
     * Get realtime report (Universal Analytics)
     * 
     * @param array $metrics Metrics
     * @param array $dimensions Dimensions
     * @return array|false Realtime data
     */
    public function getRealtimeReport($metrics = ['rt:activeUsers'], $dimensions = [])
    {
        $params = [
            'ids' => 'ga:' . $this->viewId,
            'metrics' => implode(',', $metrics),
        ];
        
        if (!empty($dimensions)) {
            $params['dimensions'] = implode(',', $dimensions);
        }
        
        $url = 'https://analytics.googleapis.com/v3/data/realtime?' . http_build_query($params);
        
        return $this->getRequest($url);
    }
    
    /**
     * Get GA4 report
     * 
     * @param array $metrics Metrics
     * @param array $dimensions Dimensions
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param array $filters Filters
     * @return array|false Report data
     */
    public function getGA4Report($metrics, $dimensions = [], $startDate = '30daysAgo', $endDate = 'today', $filters = [])
    {
        $data = [
            'property' => 'properties/' . $this->propertyId,
            'dateRanges' => [
                ['startDate' => $startDate, 'endDate' => $endDate]
            ],
            'metrics' => array_map(function($metric) {
                return ['name' => $metric];
            }, $metrics),
            'dimensions' => array_map(function($dimension) {
                return ['name' => $dimension];
            }, $dimensions),
        ];
        
        if (!empty($filters)) {
            $data['dimensionFilter'] = ['filter' => $filters];
        }
        
        return $this->requestGA4('properties/' . $this->propertyId . ':runReport', $data);
    }
    
    /**
     * Get goals
     * 
     * @return array|false Goals
     */
    public function getGoals()
    {
        $url = 'https://analytics.googleapis.com/v3/management/accounts/~all/webproperties/~all/profiles/' . $this->viewId . '/goals';
        
        return $this->getRequest($url);
    }
    
    /**
     * Get segment report
     * 
     * @param array $metrics Metrics
     * @param array $dimensions Dimensions
     * @param string $segmentId Segment ID
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array|false Report data
     */
    public function getSegmentReport($metrics, $dimensions = [], $segmentId, $startDate = '30daysAgo', $endDate = 'today')
    {
        $reportRequest = [
            'viewId' => $this->viewId,
            'dateRanges' => [
                ['startDate' => $startDate, 'endDate' => $endDate]
            ],
            'metrics' => array_map(function($metric) {
                return ['expression' => $metric];
            }, $metrics),
            'dimensions' => array_map(function($dimension) {
                return ['name' => $dimension];
            }, $dimensions),
            'segments' => [['segmentId' => $segmentId]]
        ];
        
        $data = ['reportRequests' => [$reportRequest]];
        
        return $this->request('reports:batchGet', $data);
    }
    
    /**
     * Get user activity
     * 
     * @param string $userId User ID
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array|false Activity data
     */
    public function getUserActivity($userId, $startDate = '30daysAgo', $endDate = 'today')
    {
        $data = [
            'viewId' => $this->viewId,
            'user' => [
                'type' => 'USER_ID',
                'userId' => $userId
            ],
            'dateRange' => [
                'startDate' => $startDate,
                'endDate' => $endDate
            ],
            'activityTypes' => ['PAGEVIEW', 'EVENT', 'TRANSACTION']
        ];
        
        return $this->request('userActivity:search', $data);
    }
    
    /**
     * Get page analytics
     * 
     * @param string $pagePath Page path
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array|false Page analytics
     */
    public function getPageAnalytics($pagePath, $startDate = '30daysAgo', $endDate = 'today')
    {
        $metrics = ['ga:pageviews', 'ga:uniquePageviews', 'ga:avgTimeOnPage', 'ga:bounceRate', 'ga:entrances', 'ga:exits'];
        $dimensions = ['ga:date', 'ga:pageTitle'];
        $filters = [
            [
                'dimensionName' => 'ga:pagePath',
                'operator' => 'EXACT',
                'expressions' => [$pagePath]
            ]
        ];
        
        return $this->getReport($metrics, $dimensions, $startDate, $endDate, $filters);
    }
    
    /**
     * Get traffic sources report
     * 
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array|false Traffic data
     */
    public function getTrafficSources($startDate = '30daysAgo', $endDate = 'today')
    {
        $metrics = ['ga:sessions', 'ga:users', 'ga:pageviews', 'ga:bounceRate', 'ga:avgSessionDuration'];
        $dimensions = ['ga:source', 'ga:medium', 'ga:campaign'];
        
        return $this->getReport($metrics, $dimensions, $startDate, $endDate);
    }
    
    /**
     * Get geo report
     * 
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param string $dimension Geo dimension (country, city, region)
     * @return array|false Geo data
     */
    public function getGeoReport($startDate = '30daysAgo', $endDate = 'today', $dimension = 'country')
    {
        $metrics = ['ga:sessions', 'ga:users', 'ga:pageviews', 'ga:bounceRate'];
        $dimensions = ["ga:{$dimension}"];
        
        return $this->getReport($metrics, $dimensions, $startDate, $endDate);
    }
    
    /**
     * Get technology report
     * 
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param string $type Report type (browser, os, device)
     * @return array|false Technology data
     */
    public function getTechnologyReport($startDate = '30daysAgo', $endDate = 'today', $type = 'browser')
    {
        $metrics = ['ga:sessions', 'ga:users', 'ga:pageviews', 'ga:bounceRate'];
        
        $dimensionMap = [
            'browser' => 'ga:browser',
            'os' => 'ga:operatingSystem',
            'device' => 'ga:deviceCategory'
        ];
        
        $dimensions = [$dimensionMap[$type] ?? 'ga:browser'];
        
        return $this->getReport($metrics, $dimensions, $startDate, $endDate);
    }
    
    /**
     * Get ecommerce report
     * 
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array|false Ecommerce data
     */
    public function getEcommerceReport($startDate = '30daysAgo', $endDate = 'today')
    {
        $metrics = [
            'ga:transactions',
            'ga:transactionRevenue',
            'ga:avgOrderValue',
            'ga:uniquePurchases',
            'ga:productAddsToCart',
            'ga:cartToDetailRate'
        ];
        
        $dimensions = ['ga:date', 'ga:productName'];
        
        return $this->getReport($metrics, $dimensions, $startDate, $endDate);
    }
    
    /**
     * Get event report
     * 
     * @param string|null $eventCategory Event category
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array|false Event data
     */
    public function getEventReport($eventCategory = null, $startDate = '30daysAgo', $endDate = 'today')
    {
        $metrics = ['ga:totalEvents', 'ga:uniqueEvents', 'ga:eventValue'];
        $dimensions = ['ga:eventCategory', 'ga:eventAction', 'ga:eventLabel'];
        
        $filters = [];
        if ($eventCategory) {
            $filters = [
                [
                    'dimensionName' => 'ga:eventCategory',
                    'operator' => 'EXACT',
                    'expressions' => [$eventCategory]
                ]
            ];
        }
        
        return $this->getReport($metrics, $dimensions, $startDate, $endDate, $filters);
    }
    
    /**
     * Get conversion report
     * 
     * @param string|null $goalId Goal ID
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array|false Conversion data
     */
    public function getConversionReport($goalId = null, $startDate = '30daysAgo', $endDate = 'today')
    {
        $metrics = ['ga:goalCompletionsAll', 'ga:goalConversionRateAll'];
        
        if ($goalId) {
            $metrics = ["ga:goal{$goalId}Completions", "ga:goal{$goalId}ConversionRate"];
        }
        
        $dimensions = ['ga:date', 'ga:source', 'ga:medium'];
        
        return $this->getReport($metrics, $dimensions, $startDate, $endDate);
    }
    
    /**
     * Get custom report
     * 
     * @param array $customMetrics Custom metrics
     * @param array $customDimensions Custom dimensions
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array|false Custom report
     */
    public function getCustomReport($customMetrics, $customDimensions = [], $startDate = '30daysAgo', $endDate = 'today')
    {
        $metrics = array_map(function($metric) {
            return "ga:{$metric}";
        }, $customMetrics);
        
        $dimensions = array_map(function($dimension) {
            return "ga:{$dimension}";
        }, $customDimensions);
        
        return $this->getReport($metrics, $dimensions, $startDate, $endDate);
    }
    
    /**
     * Test connection
     * 
     * @return bool Connection successful
     */
    public function testConnection()
    {
        $result = $this->getReport(['ga:sessions'], [], 'today', 'today');
        return ($result !== false);
    }
}