<?php
/**
 * HubSpot API Class for Masal Panel
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_HubSpot implements APIMaster_APIInterface
{
    private $apiKey;
    private $model;
    private $config;
    private $apiUrl = 'https://api.hubapi.com';
    private $timeout = 30;
    
    private $supportedModels = [
        'crm' => 'CRM (Contacts, Companies, Deals)',
        'marketing' => 'Marketing Email',
        'tickets' => 'Tickets',
        'blogs' => 'Blog API',
        'webhooks' => 'Webhooks'
    ];
    
    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->apiKey = $this->config['api_key'] ?? '';
        $this->model = $this->config['model'] ?? 'crm';
        
        if (isset($this->config['api_url'])) {
            $this->apiUrl = $this->config['api_url'];
        }
        
        if (isset($this->config['timeout'])) {
            $this->timeout = $this->config['timeout'];
        }
    }
    
    private function loadConfig()
    {
        $configFile = dirname(__DIR__) . '/config/hubspot.json';
        
        if (file_exists($configFile)) {
            $content = file_get_contents($configFile);
            return json_decode($content, true) ?: [];
        }
        
        return [];
    }
    
    private function logError($message, $context = [])
    {
        $logDir = dirname(__DIR__) . '/logs';
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/hubspot-error.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        
        file_put_contents(
            $logFile,
            "[{$timestamp}] {$message}{$contextStr}" . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
    
    private function curlRequest($url, $method = 'GET', $data = null, $headers = [])
    {
        $ch = curl_init();
        
        $defaultHeaders = [
            'Content-Type: application/json'
        ];
        
        if (!empty($this->apiKey)) {
            $defaultHeaders[] = 'Authorization: Bearer ' . $this->apiKey;
        }
        
        $allHeaders = array_merge($defaultHeaders, $headers);
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } elseif ($method === 'GET' && $data !== null) {
            $url .= '?' . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        curl_close($ch);
        
        if ($curlError) {
            $this->logError("CURL Error: {$curlError}", ['url' => $url]);
            return ['error' => "CURL Error: {$curlError}"];
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode < 200 || $httpCode >= 300) {
            $errorMsg = isset($decoded['message']) 
                ? $decoded['message']
                : (isset($decoded['error']['message']) 
                    ? $decoded['error']['message']
                    : "HTTP Error: {$httpCode}");
            $this->logError($errorMsg, ['url' => $url, 'response' => $response]);
            return ['error' => $errorMsg];
        }
        
        return $decoded;
    }
    
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        return true;
    }
    
    public function setModel($model)
    {
        if (isset($this->supportedModels[$model])) {
            $this->model = $model;
            return true;
        }
        return false;
    }
    
    public function getModel()
    {
        return $this->model;
    }
    
    public function complete($endpoint, $params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        $method = $params['method'] ?? 'GET';
        unset($params['method']);
        
        $response = $this->curlRequest($url, $method, !empty($params) ? $params : null);
        
        if (isset($response['error'])) {
            return $response;
        }
        
        return [
            'success' => true,
            'data' => $response,
            'endpoint' => $endpoint
        ];
    }
    
    public function stream($endpoint, $callback)
    {
        $this->logError('Streaming not supported for HubSpot');
        return false;
    }
    
    public function getModels()
    {
        $models = [];
        
        foreach ($this->supportedModels as $id => $name) {
            $models[] = [
                'id' => $id,
                'name' => $name,
                'enabled' => true
            ];
        }
        
        return $models;
    }
    
    public function getCapabilities()
    {
        return [
            'crm_contacts' => true,
            'crm_companies' => true,
            'crm_deals' => true,
            'crm_tickets' => true,
            'marketing_email' => true,
            'webhooks' => true,
            'search' => true,
            'batch_operations' => true
        ];
    }
    
    public function checkHealth()
    {
        if (empty($this->apiKey)) {
            return [
                'status' => 'error',
                'message' => 'API key not configured'
            ];
        }
        
        $startTime = microtime(true);
        $response = $this->curlRequest($this->apiUrl . '/crm/v3/objects/contacts?limit=1', 'GET');
        $responseTime = round((microtime(true) - $startTime) * 1000);
        
        if (isset($response['error'])) {
            return [
                'status' => 'error',
                'message' => $response['error'],
                'response_time_ms' => $responseTime
            ];
        }
        
        return [
            'status' => 'healthy',
            'message' => 'API is reachable',
            'response_time_ms' => $responseTime
        ];
    }
    
    public function chat($message, $context = [])
    {
        return $this->searchContacts($message, $context);
    }
    
    public function createContact($email, $firstName, $lastName, $properties = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $data = [
            'properties' => array_merge([
                'email' => $email,
                'firstname' => $firstName,
                'lastname' => $lastName
            ], $properties)
        ];
        
        $response = $this->curlRequest(
            $this->apiUrl . '/crm/v3/objects/contacts',
            'POST',
            $data
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'contact' => $response,
            'contact_id' => $response['id'] ?? ''
        ];
    }
    
    public function getContact($contactId)
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest(
            $this->apiUrl . '/crm/v3/objects/contacts/' . urlencode($contactId),
            'GET'
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'contact' => $response
        ];
    }
    
    public function updateContact($contactId, $properties)
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $data = [
            'properties' => $properties
        ];
        
        $response = $this->curlRequest(
            $this->apiUrl . '/crm/v3/objects/contacts/' . urlencode($contactId),
            'PATCH',
            $data
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'contact' => $response
        ];
    }
    
    public function deleteContact($contactId)
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest(
            $this->apiUrl . '/crm/v3/objects/contacts/' . urlencode($contactId),
            'DELETE'
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'message' => 'Contact deleted successfully',
            'contact_id' => $contactId
        ];
    }
    
    public function searchContacts($query, $params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $data = [
            'filterGroups' => [
                [
                    'filters' => [
                        [
                            'propertyName' => 'email',
                            'operator' => 'CONTAINS_TOKEN',
                            'value' => $query
                        ]
                    ]
                ]
            ],
            'properties' => $params['properties'] ?? ['email', 'firstname', 'lastname', 'phone'],
            'limit' => $params['limit'] ?? 100
        ];
        
        if (!empty($query) && strpos($query, '@') === false) {
            $data['filterGroups'][0]['filters'][] = [
                'propertyName' => 'firstname',
                'operator' => 'CONTAINS_TOKEN',
                'value' => $query
            ];
        }
        
        $response = $this->curlRequest(
            $this->apiUrl . '/crm/v3/objects/contacts/search',
            'POST',
            $data
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'results' => $response['results'] ?? [],
            'count' => $response['total'] ?? 0,
            'query' => $query
        ];
    }
    
    public function getAllContacts($params = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $response = $this->curlRequest(
            $this->apiUrl . '/crm/v3/objects/contacts',
            'GET',
            $params
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'contacts' => $response['results'] ?? [],
            'count' => count($response['results'] ?? []),
            'total' => $response['total'] ?? 0
        ];
    }
    
    public function createCompany($name, $domain, $properties = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $data = [
            'properties' => array_merge([
                'name' => $name,
                'domain' => $domain
            ], $properties)
        ];
        
        $response = $this->curlRequest(
            $this->apiUrl . '/crm/v3/objects/companies',
            'POST',
            $data
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'company' => $response,
            'company_id' => $response['id'] ?? ''
        ];
    }
    
    public function createDeal($dealName, $amount, $stage, $properties = [])
    {
        if (empty($this->apiKey)) {
            return ['error' => 'API key not configured'];
        }
        
        $data = [
            'properties' => array_merge([
                'dealname' => $dealName,
                'amount' => (string)$amount,
                'dealstage' => $stage
            ], $properties)
        ];
        
        $response = $this->curlRequest(
            $this->apiUrl . '/crm/v3/objects/deals',
            'POST',
            $data
        );
        
        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }
        
        return [
            'success' => true,
            'deal' => $response,
            'deal_id' => $response['id'] ?? ''
        ];
    }
    
    public function extractText($response)
    {
        if (is_array($response)) {
            if (isset($response['contact'])) {
                return 'Contact: ' . ($response['contact']['properties']['email'] ?? $response['contact']['id'] ?? '');
            }
            
            if (isset($response['contacts'])) {
                return 'Found ' . $response['count'] . ' contacts';
            }
            
            if (isset($response['company'])) {
                return 'Company created: ' . ($response['company']['properties']['name'] ?? $response['company']['id'] ?? '');
            }
            
            if (isset($response['deal'])) {
                return 'Deal created: ' . ($response['deal']['properties']['dealname'] ?? $response['deal']['id'] ?? '');
            }
            
            if (isset($response['message'])) {
                return $response['message'];
            }
            
            return json_encode($response);
        }
        
        if (is_string($response)) {
            return $response;
        }
        
        return '';
    }
}