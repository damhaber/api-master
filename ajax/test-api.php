<?php
if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_TestApi
{
    private $moduleDir;
    private $statsFile;
    private $logDir;
    private $testLogFile;
    private $mainLogFile;

    public function __construct()
    {
        $this->moduleDir = dirname(__DIR__, 2);
        $this->statsFile = $this->moduleDir . '/data/stats/providers.json';
        $this->logDir = $this->moduleDir . '/logs';
        $this->testLogFile = $this->logDir . '/api-tests.log';
        $this->mainLogFile = $this->logDir . '/api-master.log';
        
        $this->ensureDirectories();
        $this->handleRequest();
    }

    private function ensureDirectories()
    {
        $dirs = [dirname($this->statsFile), $this->logDir];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    private function handleRequest()
    {
        header('Content-Type: application/json');
        
        $provider = isset($_POST['provider']) ? $this->sanitizeProviderName($_POST['provider']) : '';
        $endpoint = isset($_POST['endpoint']) ? $_POST['endpoint'] : 'chat';
        $params = isset($_POST['params']) ? json_decode($_POST['params'], true) : [];
        $testMessage = isset($_POST['test_message']) ? $_POST['test_message'] : 'Merhaba, bu bir test mesajıdır.';
        
        if (empty($provider)) {
            $this->jsonResponse(false, 'Provider belirtilmedi');
        }
        
        $startTime = microtime(true);
        
        $testParams = [
            'message' => $testMessage,
            'model' => isset($params['model']) ? $params['model'] : null,
            'temperature' => isset($params['temperature']) ? floatval($params['temperature']) : 0.7,
            'max_tokens' => isset($params['max_tokens']) ? intval($params['max_tokens']) : 150
        ];
        
        $result = $this->callApi($provider, $testParams);
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        
        if ($result['success']) {
            $this->updateProviderStats($provider, $executionTime, true);
            $this->logTestResult($provider, true, $executionTime);
            
            $this->jsonResponse(true, 'API çağrısı başarılı', [
                'provider' => $provider,
                'endpoint' => $endpoint,
                'request' => $testParams,
                'response' => $result['response'],
                'execution_time' => $executionTime . ' ms'
            ]);
        } else {
            $this->updateProviderStats($provider, $executionTime, false);
            $this->logTestResult($provider, false, $executionTime, $result['error']);
            
            $this->jsonResponse(false, 'API çağrısı başarısız: ' . $result['error'], [
                'provider' => $provider,
                'error' => $result['error'],
                'execution_time' => $executionTime . ' ms'
            ]);
        }
    }

    private function callApi($provider, $params)
    {
        $apiKeys = $this->loadApiKeys();
        
        if (!isset($apiKeys[$provider]) || empty($apiKeys[$provider]['key'])) {
            return ['success' => false, 'error' => "{$provider} için API anahtarı bulunamadı"];
        }
        
        $apiKey = $apiKeys[$provider]['key'];
        $url = $this->getApiUrl($provider);
        $headers = $this->getHeaders($provider, $apiKey);
        $body = $this->getRequestBody($provider, $params);
        
        if (!$url) {
            return ['success' => false, 'error' => "{$provider} için API URL'si bulunamadı"];
        }
        
        $response = $this->makeCurlRequest($url, $headers, $body);
        
        if ($response === false) {
            return ['success' => false, 'error' => 'cURL hatası: ' . curl_error($this->getCurlHandle())];
        }
        
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'JSON parse hatası: ' . json_last_error_msg()];
        }
        
        return $this->parseResponse($provider, $decodedResponse);
    }

    private function getApiUrl($provider)
    {
        $urls = [
            'openai' => 'https://api.openai.com/v1/chat/completions',
            'deepseek' => 'https://api.deepseek.com/v1/chat/completions',
            'gemini' => 'https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent',
            'claude' => 'https://api.anthropic.com/v1/messages',
            'anthropic' => 'https://api.anthropic.com/v1/messages',
            'cohere' => 'https://api.cohere.ai/v1/generate',
            'mistral' => 'https://api.mistral.ai/v1/chat/completions'
        ];
        
        return isset($urls[$provider]) ? $urls[$provider] : null;
    }

    private function getHeaders($provider, $apiKey)
    {
        $headers = ['Content-Type: application/json'];
        
        switch ($provider) {
            case 'openai':
            case 'deepseek':
            case 'cohere':
            case 'mistral':
                $headers[] = 'Authorization: Bearer ' . $apiKey;
                break;
            case 'gemini':
                // API key query parameter olarak gidecek
                break;
            case 'claude':
            case 'anthropic':
                $headers[] = 'x-api-key: ' . $apiKey;
                $headers[] = 'anthropic-version: 2023-06-01';
                break;
        }
        
        return $headers;
    }

    private function getRequestBody($provider, $params)
    {
        $message = isset($params['message']) ? $params['message'] : '';
        $model = isset($params['model']) ? $params['model'] : null;
        $temperature = isset($params['temperature']) ? $params['temperature'] : 0.7;
        $maxTokens = isset($params['max_tokens']) ? $params['max_tokens'] : 150;
        
        switch ($provider) {
            case 'openai':
            case 'deepseek':
            case 'mistral':
                return json_encode([
                    'model' => $model ?: ($provider === 'deepseek' ? 'deepseek-chat' : 'gpt-3.5-turbo'),
                    'messages' => [['role' => 'user', 'content' => $message]],
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens
                ]);
                
            case 'gemini':
                $apiKey = isset($params['api_key']) ? $params['api_key'] : '';
                return json_encode([
                    'contents' => [['parts' => [['text' => $message]]]]
                ]);
                
            case 'claude':
            case 'anthropic':
                return json_encode([
                    'model' => $model ?: 'claude-3-haiku-20240307',
                    'messages' => [['role' => 'user', 'content' => $message]],
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature
                ]);
                
            case 'cohere':
                return json_encode([
                    'model' => $model ?: 'command',
                    'prompt' => $message,
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature
                ]);
                
            default:
                return json_encode(['prompt' => $message]);
        }
    }

    private function makeCurlRequest($url, $headers, $body)
    {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    }

    private function parseResponse($provider, $response)
    {
        switch ($provider) {
            case 'openai':
            case 'deepseek':
            case 'mistral':
                if (isset($response['choices'][0]['message']['content'])) {
                    return [
                        'success' => true,
                        'response' => $response['choices'][0]['message']['content']
                    ];
                }
                break;
                
            case 'gemini':
                if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                    return [
                        'success' => true,
                        'response' => $response['candidates'][0]['content']['parts'][0]['text']
                    ];
                }
                break;
                
            case 'claude':
            case 'anthropic':
                if (isset($response['content'][0]['text'])) {
                    return [
                        'success' => true,
                        'response' => $response['content'][0]['text']
                    ];
                }
                break;
                
            case 'cohere':
                if (isset($response['generations'][0]['text'])) {
                    return [
                        'success' => true,
                        'response' => $response['generations'][0]['text']
                    ];
                }
                break;
        }
        
        return [
            'success' => false,
            'error' => 'Geçersiz API yanıtı: ' . json_encode($response)
        ];
    }

    private function loadApiKeys()
    {
        $keysFile = $this->moduleDir . '/config/api-keys.json';
        
        if (!file_exists($keysFile)) {
            return [];
        }
        
        $content = file_get_contents($keysFile);
        $data = json_decode($content, true);
        
        return is_array($data) ? $data : [];
    }

    private function updateProviderStats($provider, $executionTime, $success)
    {
        $stats = [];
        
        if (file_exists($this->statsFile)) {
            $content = file_get_contents($this->statsFile);
            $stats = json_decode($content, true);
            if (!is_array($stats)) {
                $stats = [];
            }
        }
        
        if (!isset($stats[$provider])) {
            $stats[$provider] = [
                'total_calls' => 0,
                'success_calls' => 0,
                'failed_calls' => 0,
                'avg_response_time' => 0,
                'last_test' => null,
                'last_success' => null
            ];
        }
        
        $stats[$provider]['total_calls']++;
        
        if ($success) {
            $stats[$provider]['success_calls']++;
            $stats[$provider]['last_success'] = date('Y-m-d H:i:s');
        } else {
            $stats[$provider]['failed_calls']++;
        }
        
        $currentAvg = $stats[$provider]['avg_response_time'];
        $totalCalls = $stats[$provider]['total_calls'];
        $stats[$provider]['avg_response_time'] = (($currentAvg * ($totalCalls - 1)) + $executionTime) / $totalCalls;
        $stats[$provider]['last_test'] = date('Y-m-d H:i:s');
        $stats[$provider]['success_rate'] = ($stats[$provider]['success_calls'] / $stats[$provider]['total_calls']) * 100;
        
        file_put_contents($this->statsFile, json_encode($stats, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function logTestResult($provider, $success, $executionTime, $error = null)
    {
        $logEntry = '[' . date('Y-m-d H:i:s') . "] Provider: {$provider} | Success: " . ($success ? 'true' : 'false') . " | Time: {$executionTime}ms";
        
        if ($error) {
            $logEntry .= " | Error: {$error}";
        }
        
        $logEntry .= " | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
        file_put_contents($this->testLogFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        $mainLogEntry = '[' . date('Y-m-d H:i:s') . "] [" . ($success ? 'INFO' : 'ERROR') . "] api_test | Provider: {$provider} | Time: {$executionTime}ms";
        
        if ($error) {
            $mainLogEntry .= " | Message: {$error}";
        }
        
        $mainLogEntry .= "\n";
        file_put_contents($this->mainLogFile, $mainLogEntry, FILE_APPEND | LOCK_EX);
    }

    private function sanitizeProviderName($name)
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower(trim($name)));
    }

    private function jsonResponse($success, $message, $data = [])
    {
        $response = [
            'success' => $success,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if (!empty($data)) {
            $response = array_merge($response, $data);
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

new APIMaster_TestApi();
?>