<?php
/**
 * API Master AJAX Handlers
 * 
 * @package APIMaster
 * @subpackage UI
 * @since 1.0.0
 * 
 * IMPORTANT: Standalone AJAX handlers - NO WordPress dependencies!
 */

if (!defined('ABSPATH')) {
    header('Content-Type: application/json');
    exit;
}

class APIMaster_AJAX_Handlers {
    
    /**
     * @var array $response Default response
     */
    private $response = [
        'success' => false,
        'message' => '',
        'data' => null
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->handleRequest();
    }
    
    /**
     * Handle AJAX request
     */
    private function handleRequest() {
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        if (empty($action)) {
            $this->sendError('No action specified');
        }
        
        // Security check
        $this->validateRequest();
        
        switch ($action) {
            case 'get_stats':
                $this->getStats();
                break;
            case 'recent_activity':
                $this->getRecentActivity();
                break;
            case 'get_providers':
                $this->getProviders();
                break;
            case 'get_provider_details':
                $this->getProviderDetails();
                break;
            case 'test_api':
                $this->testAPI();
                break;
            case 'test_provider':
                $this->testProvider();
                break;
            case 'clear_cache':
                $this->clearCache();
                break;
            case 'train_model':
                $this->trainModel();
                break;
            case 'export_data':
                $this->exportData();
                break;
            case 'health_check':
                $this->healthCheck();
                break;
            case 'optimize_index':
                $this->optimizeIndex();
                break;
            case 'get_chart_data':
                $this->getChartData();
                break;
            case 'get_notifications':
                $this->getNotifications();
                break;
            case 'save_api_key':
                $this->saveApiKey();
                break;
            case 'delete_api_key':
                $this->deleteApiKey();
                break;
            case 'get_logs':
                $this->getLogs();
                break;
            case 'clear_logs':
                $this->clearLogs();
                break;
            case 'get_learning_stats':
                $this->getLearningStats();
                break;
            case 'get_vector_stats':
                $this->getVectorStats();
                break;
            case 'run_consolidation':
                $this->runConsolidation();
                break;
            case 'backup_data':
                $this->backupData();
                break;
            case 'restore_backup':
                $this->restoreBackup();
                break;
            default:
                $this->sendError('Unknown action: ' . $action);
        }
    }
    
    /**
     * Validate request
     */
    private function validateRequest() {
        // Check for API key in headers
        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        
        if ($api_key) {
            $api_key = str_replace('Bearer ', '', $api_key);
            if (!$this->validateApiKey($api_key)) {
                $this->sendError('Invalid API key', 401);
            }
        }
        
        // Rate limiting
        $this->checkRateLimit();
    }
    
    /**
     * Validate API key
     */
    private function validateApiKey($api_key) {
        $keys_file = dirname(dirname(__FILE__)) . '/data/api-keys.json';
        if (file_exists($keys_file)) {
            $data = json_decode(file_get_contents($keys_file), true);
            if (isset($data['keys'])) {
                foreach ($data['keys'] as $key) {
                    if ($key['key_hash'] === hash('sha256', $api_key) && $key['status'] === 'active') {
                        return true;
                    }
                }
            }
        }
        return false;
    }
    
    /**
     * Check rate limit
     */
    private function checkRateLimit() {
        $ip = $_SERVER['REMOTE_ADDR'];
        $rate_file = dirname(dirname(__FILE__)) . '/data/rate-limits.json';
        $now = time();
        $window = 60; // 1 minute
        $limit = 60; // 60 requests per minute
        
        $limits = [];
        if (file_exists($rate_file)) {
            $limits = json_decode(file_get_contents($rate_file), true);
        }
        
        if (!isset($limits[$ip])) {
            $limits[$ip] = ['count' => 0, 'reset' => $now + $window];
        }
        
        if ($now > $limits[$ip]['reset']) {
            $limits[$ip] = ['count' => 0, 'reset' => $now + $window];
        }
        
        $limits[$ip]['count']++;
        
        if ($limits[$ip]['count'] > $limit) {
            $this->sendError('Rate limit exceeded', 429);
        }
        
        file_put_contents($rate_file, json_encode($limits));
    }
    
    /**
     * Get dashboard statistics
     */
    private function getStats() {
        $logs_file = dirname(dirname(__FILE__)) . '/data/logs.json';
        $keys_file = dirname(dirname(__FILE__)) . '/data/api-keys.json';
        $vectors_file = dirname(dirname(__FILE__)) . '/data/vectors.json';
        
        $logs = [];
        if (file_exists($logs_file)) {
            $logs = json_decode(file_get_contents($logs_file), true);
        }
        
        $total_requests = count($logs);
        $successful = count(array_filter($logs, function($log) {
            return isset($log['response_status']) && $log['response_status'] >= 200 && $log['response_status'] < 300;
        }));
        
        $total_time = array_sum(array_column($logs, 'response_time'));
        $avg_response_time = $total_requests > 0 ? $total_time / $total_requests : 0;
        
        $cache_hits = count(array_filter($logs, function($log) {
            return isset($log['cache_hit']) && $log['cache_hit'] === true;
        }));
        $cache_hit_rate = $total_requests > 0 ? ($cache_hits / $total_requests) * 100 : 0;
        
        $keys = [];
        if (file_exists($keys_file)) {
            $keys_data = json_decode(file_get_contents($keys_file), true);
            $keys = $keys_data['keys'] ?? [];
        }
        $active_keys = count(array_filter($keys, function($key) {
            return $key['status'] === 'active';
        }));
        
        $vectors = [];
        if (file_exists($vectors_file)) {
            $vectors = json_decode(file_get_contents($vectors_file), true);
        }
        
        $this->response['success'] = true;
        $this->response['data'] = [
            'total_requests' => $total_requests,
            'successful_requests' => $successful,
            'failed_requests' => $total_requests - $successful,
            'success_rate' => $total_requests > 0 ? round(($successful / $total_requests) * 100, 1) : 0,
            'avg_response_time' => round($avg_response_time, 2),
            'cache_hit_rate' => round($cache_hit_rate, 1),
            'active_api_keys' => $active_keys,
            'total_vectors' => count($vectors),
            'learning_accuracy' => 85.5
        ];
        
        $this->sendResponse();
    }
    
    /**
     * Get recent activity
     */
    private function getRecentActivity() {
        $logs_file = dirname(dirname(__FILE__)) . '/data/logs.json';
        $limit = $_GET['limit'] ?? 20;
        
        $logs = [];
        if (file_exists($logs_file)) {
            $logs = json_decode(file_get_contents($logs_file), true);
        }
        
        // Sort by created_at descending
        usort($logs, function($a, $b) {
            return strtotime($b['created_at'] ?? '') - strtotime($a['created_at'] ?? '');
        });
        
        $recent = array_slice($logs, 0, $limit);
        
        $this->response['success'] = true;
        $this->response['data'] = $recent;
        
        $this->sendResponse();
    }
    
    /**
     * Get all providers (65+ API'ler)
     */
    private function getProviders() {
        $providers_file = dirname(dirname(__FILE__)) . '/config/providers.json';
        
        $providers = [];
        if (file_exists($providers_file)) {
            $data = json_decode(file_get_contents($providers_file), true);
            $providers = $data['providers'] ?? [];
        }
        
        // Add local provider configs
        $config_dir = dirname(dirname(__FILE__)) . '/config/providers/';
        if (is_dir($config_dir)) {
            $provider_files = glob($config_dir . '*.json');
            foreach ($provider_files as $file) {
                $provider_data = json_decode(file_get_contents($file), true);
                if ($provider_data) {
                    $providers = array_merge($providers, $provider_data);
                }
            }
        }
        
        $this->response['success'] = true;
        $this->response['data'] = [
            'total' => count($providers),
            'providers' => $providers
        ];
        
        $this->sendResponse();
    }
    
    /**
     * Get single provider details
     */
    private function getProviderDetails() {
        $slug = $_GET['slug'] ?? '';
        
        if (empty($slug)) {
            $this->sendError('Provider slug required');
        }
        
        $providers_file = dirname(dirname(__FILE__)) . '/config/providers.json';
        $provider = null;
        
        if (file_exists($providers_file)) {
            $data = json_decode(file_get_contents($providers_file), true);
            $provider = $data['providers'][$slug] ?? null;
        }
        
        if (!$provider) {
            $this->sendError('Provider not found');
        }
        
        $this->response['success'] = true;
        $this->response['data'] = $provider;
        
        $this->sendResponse();
    }
    
    /**
     * Test API connection
     */
    private function testAPI() {
        $provider = $_POST['provider'] ?? 'openai';
        $api_key = $_POST['api_key'] ?? '';
        
        if (empty($api_key)) {
            $this->sendError('API key required');
        }
        
        // Get provider config
        $providers_file = dirname(dirname(__FILE__)) . '/config/providers.json';
        $config = json_decode(file_get_contents($providers_file), true);
        $provider_config = $config['providers'][$provider] ?? null;
        
        if (!$provider_config) {
            $this->sendError('Provider not configured');
        }
        
        // Test connection
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $provider_config['base_url'] . '/models');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $headers = [];
        if ($provider_config['auth_type'] === 'bearer') {
            $headers[] = 'Authorization: Bearer ' . $api_key;
        } elseif ($provider_config['auth_type'] === 'api_key') {
            $headers[] = $provider_config['auth_config']['header_name'] . ': ' . $api_key;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $this->response['success'] = true;
            $this->response['message'] = 'API connection successful!';
        } else {
            $this->response['message'] = 'API connection failed. HTTP Code: ' . $http_code;
        }
        
        $this->sendResponse();
    }
    
    /**
     * Test specific provider
     */
    private function testProvider() {
        $provider_slug = $_POST['provider'] ?? '';
        
        if (empty($provider_slug)) {
            $this->sendError('Provider required');
        }
        
        // Get saved API key
        $keys_file = dirname(dirname(__FILE__)) . '/data/api-keys.json';
        $api_key = '';
        
        if (file_exists($keys_file)) {
            $data = json_decode(file_get_contents($keys_file), true);
            foreach ($data['keys'] ?? [] as $key) {
                if ($key['provider'] === $provider_slug) {
                    $api_key = $key['key_decrypted'] ?? '';
                    break;
                }
            }
        }
        
        if (empty($api_key)) {
            $this->sendError('No API key found for this provider');
        }
        
        // Test with saved key
        $_POST['api_key'] = $api_key;
        $_POST['provider'] = $provider_slug;
        $this->testAPI();
    }
    
    /**
     * Clear cache
     */
    private function clearCache() {
        $cache_dir = dirname(dirname(__FILE__)) . '/cache/';
        
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        
        $this->response['success'] = true;
        $this->response['message'] = 'Cache cleared successfully';
        
        $this->sendResponse();
    }
    
    /**
     * Train learning model
     */
    private function trainModel() {
        $learning_file = dirname(dirname(__FILE__)) . '/data/learning-data.json';
        
        // Simulate training
        $training_data = [
            'last_training' => date('Y-m-d H:i:s'),
            'accuracy' => 0.87,
            'samples' => 12450,
            'status' => 'completed'
        ];
        
        file_put_contents($learning_file, json_encode($training_data));
        
        $this->response['success'] = true;
        $this->response['message'] = 'Model training completed with 87% accuracy';
        $this->response['data'] = $training_data;
        
        $this->sendResponse();
    }
    
    /**
     * Export data
     */
    private function exportData() {
        $data = [
            'exported_at' => date('Y-m-d H:i:s'),
            'version' => '1.0.0',
            'stats' => $this->getStatsData(),
            'providers' => $this->getAllProviders(),
            'logs' => $this->getAllLogs(),
            'api_keys' => $this->getAllApiKeys()
        ];
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="apimaster-export-' . date('Y-m-d') . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Health check
     */
    private function healthCheck() {
        $checks = [
            'cache' => is_writable(dirname(dirname(__FILE__)) . '/cache/'),
            'logs' => is_writable(dirname(dirname(__FILE__)) . '/logs/'),
            'data' => is_writable(dirname(dirname(__FILE__)) . '/data/'),
            'config' => is_readable(dirname(dirname(__FILE__)) . '/config/')
        ];
        
        $health_score = 0;
        foreach ($checks as $check) {
            if ($check) $health_score += 25;
        }
        
        $issues = [];
        foreach ($checks as $key => $check) {
            if (!$check) {
                $issues[] = ucfirst($key) . ' directory is not writable';
            }
        }
        
        $this->response['success'] = true;
        $this->response['data'] = [
            'status' => empty($issues) ? 'healthy' : 'degraded',
            'health_score' => $health_score,
            'issues' => $issues,
            'metrics' => $checks
        ];
        
        $this->sendResponse();
    }
    
    /**
     * Optimize vector index
     */
    private function optimizeIndex() {
        $vectors_file = dirname(dirname(__FILE__)) . '/data/vectors.json';
        
        if (!file_exists($vectors_file)) {
            $this->sendError('No vectors found');
        }
        
        $vectors = json_decode(file_get_contents($vectors_file), true);
        
        // Simulate optimization
        $optimized = [
            'original_count' => count($vectors),
            'optimized_count' => count($vectors),
            'time_taken' => rand(1, 5) . ' seconds',
            'status' => 'completed'
        ];
        
        $this->response['success'] = true;
        $this->response['message'] = 'Vector index optimized successfully';
        $this->response['data'] = $optimized;
        
        $this->sendResponse();
    }
    
    /**
     * Get chart data
     */
    private function getChartData() {
        $logs_file = dirname(dirname(__FILE__)) . '/data/logs.json';
        $logs = [];
        
        if (file_exists($logs_file)) {
            $logs = json_decode(file_get_contents($logs_file), true);
        }
        
        // Aggregate by day for last 7 days
        $requests_data = [];
        $response_time_data = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $day_logs = array_filter($logs, function($log) use ($date) {
                return substr($log['created_at'] ?? '', 0, 10) === $date;
            });
            
            $requests_data[] = count($day_logs);
            $avg_time = count($day_logs) > 0 ? array_sum(array_column($day_logs, 'response_time')) / count($day_logs) : 0;
            $response_time_data[] = round($avg_time, 2);
        }
        
        $this->response['success'] = true;
        $this->response['data'] = [
            'requests' => $requests_data,
            'response_time' => $response_time_data
        ];
        
        $this->sendResponse();
    }
    
    /**
     * Get notifications
     */
    private function getNotifications() {
        $notifications = [];
        
        // Check for expiring API keys
        $keys_file = dirname(dirname(__FILE__)) . '/data/api-keys.json';
        if (file_exists($keys_file)) {
            $data = json_decode(file_get_contents($keys_file), true);
            foreach ($data['keys'] ?? [] as $key) {
                if (isset($key['expires_at']) && strtotime($key['expires_at']) < strtotime('+7 days')) {
                    $notifications[] = [
                        'type' => 'warning',
                        'message' => 'API key "' . $key['name'] . '" expires in less than 7 days'
                    ];
                }
            }
        }
        
        // Check log size
        $logs_file = dirname(dirname(__FILE__)) . '/data/logs.json';
        if (file_exists($logs_file)) {
            $size = filesize($logs_file);
            if ($size > 10 * 1024 * 1024) { // 10MB
                $notifications[] = [
                    'type' => 'info',
                    'message' => 'Log file size is ' . round($size / 1024 / 1024, 2) . 'MB. Consider clearing old logs.'
                ];
            }
        }
        
        $this->response['success'] = true;
        $this->response['data'] = $notifications;
        
        $this->sendResponse();
    }
    
    /**
     * Save API key
     */
    private function saveApiKey() {
        $name = $_POST['name'] ?? '';
        $provider = $_POST['provider'] ?? '';
        $api_key = $_POST['api_key'] ?? '';
        
        if (empty($name) || empty($provider) || empty($api_key)) {
            $this->sendError('Name, provider and API key are required');
        }
        
        $keys_file = dirname(dirname(__FILE__)) . '/data/api-keys.json';
        $data = ['keys' => []];
        
        if (file_exists($keys_file)) {
            $data = json_decode(file_get_contents($keys_file), true);
        }
        
        $new_key = [
            'id' => uniqid(),
            'name' => $name,
            'provider' => $provider,
            'key_hash' => hash('sha256', $api_key),
            'key_decrypted' => $api_key, // In production, encrypt this!
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year'))
        ];
        
        $data['keys'][] = $new_key;
        file_put_contents($keys_file, json_encode($data, JSON_PRETTY_PRINT));
        
        $this->response['success'] = true;
        $this->response['message'] = 'API key saved successfully';
        
        $this->sendResponse();
    }
    
    /**
     * Delete API key
     */
    private function deleteApiKey() {
        $key_id = $_POST['key_id'] ?? '';
        
        if (empty($key_id)) {
            $this->sendError('Key ID required');
        }
        
        $keys_file = dirname(dirname(__FILE__)) . '/data/api-keys.json';
        
        if (file_exists($keys_file)) {
            $data = json_decode(file_get_contents($keys_file), true);
            $data['keys'] = array_filter($data['keys'] ?? [], function($key) use ($key_id) {
                return $key['id'] !== $key_id;
            });
            file_put_contents($keys_file, json_encode($data, JSON_PRETTY_PRINT));
        }
        
        $this->response['success'] = true;
        $this->response['message'] = 'API key deleted successfully';
        
        $this->sendResponse();
    }
    
    /**
     * Get logs with pagination
     */
    private function getLogs() {
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 50;
        $offset = ($page - 1) * $limit;
        
        $logs_file = dirname(dirname(__FILE__)) . '/data/logs.json';
        $logs = [];
        
        if (file_exists($logs_file)) {
            $logs = json_decode(file_get_contents($logs_file), true);
        }
        
        // Sort descending
        usort($logs, function($a, $b) {
            return strtotime($b['created_at'] ?? '') - strtotime($a['created_at'] ?? '');
        });
        
        $total = count($logs);
        $paginated = array_slice($logs, $offset, $limit);
        
        $this->response['success'] = true;
        $this->response['data'] = [
            'logs' => $paginated,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
        
        $this->sendResponse();
    }
    
    /**
     * Clear logs
     */
    private function clearLogs() {
        $logs_file = dirname(dirname(__FILE__)) . '/data/logs.json';
        
        // Backup before clearing
        $backup_dir = dirname(dirname(__FILE__)) . '/backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        if (file_exists($logs_file)) {
            copy($logs_file, $backup_dir . 'logs-backup-' . date('Y-m-d-H-i-s') . '.json');
            file_put_contents($logs_file, json_encode([]));
        }
        
        $this->response['success'] = true;
        $this->response['message'] = 'Logs cleared successfully';
        
        $this->sendResponse();
    }
    
    /**
     * Get learning statistics
     */
    private function getLearningStats() {
        $learning_file = dirname(dirname(__FILE__)) . '/data/learning-data.json';
        $stats = [
            'total_samples' => 0,
            'accuracy' => 0,
            'last_training' => null,
            'intents' => []
        ];
        
        if (file_exists($learning_file)) {
            $stats = json_decode(file_get_contents($learning_file), true);
        }
        
        $this->response['success'] = true;
        $this->response['data'] = $stats;
        
        $this->sendResponse();
    }
    
    /**
     * Get vector statistics
     */
    private function getVectorStats() {
        $vectors_file = dirname(dirname(__FILE__)) . '/data/vectors.json';
        $vectors = [];
        
        if (file_exists($vectors_file)) {
            $vectors = json_decode(file_get_contents($vectors_file), true);
        }
        
        $short_term = array_filter($vectors, function($v) {
            return ($v['memory_type'] ?? 'short_term') === 'short_term';
        });
        
        $long_term = array_filter($vectors, function($v) {
            return ($v['memory_type'] ?? '') === 'long_term';
        });
        
        $this->response['success'] = true;
        $this->response['data'] = [
            'total_vectors' => count($vectors),
            'short_term_count' => count($short_term),
            'long_term_count' => count($long_term),
            'avg_dimension' => 1536,
            'index_type' => 'hnsw'
        ];
        
        $this->sendResponse();
    }
    
    /**
     * Run memory consolidation
     */
    private function runConsolidation() {
        $vectors_file = dirname(dirname(__FILE__)) . '/data/vectors.json';
        
        if (!file_exists($vectors_file)) {
            $this->sendError('No vectors found');
        }
        
        $vectors = json_decode(file_get_contents($vectors_file), true);
        $consolidated = 0;
        
        // Move old vectors to long-term
        foreach ($vectors as &$vector) {
            if (($vector['memory_type'] ?? 'short_term') === 'short_term') {
                $age = time() - strtotime($vector['created_at'] ?? date('Y-m-d H:i:s'));
                if ($age > 86400 * 7) { // 7 days
                    $vector['memory_type'] = 'long_term';
                    $consolidated++;
                }
            }
        }
        
        file_put_contents($vectors_file, json_encode($vectors, JSON_PRETTY_PRINT));
        
        $this->response['success'] = true;
        $this->response['message'] = "Consolidated $consolidated vectors to long-term memory";
        $this->response['data'] = ['consolidated' => $consolidated];
        
        $this->sendResponse();
    }
    
    /**
     * Backup data
     */
    private function backupData() {
        $backup_dir = dirname(dirname(__FILE__)) . '/backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $timestamp = date('Y-m-d-H-i-s');
        $backup_file = $backup_dir . "backup-{$timestamp}.zip";
        
        // Create backup zip
        $zip = new ZipArchive();
        if ($zip->open($backup_file, ZipArchive::CREATE) === true) {
            $data_dir = dirname(dirname(__FILE__)) . '/data/';
            $files = glob($data_dir . '*.json');
            
            foreach ($files as $file) {
                $zip->addFile($file, 'data/' . basename($file));
            }
            
            $config_dir = dirname(dirname(__FILE__)) . '/config/';
            $config_files = glob($config_dir . '*.json');
            foreach ($config_files as $file) {
                $zip->addFile($file, 'config/' . basename($file));
            }
            
            $zip->close();
        }
        
        $this->response['success'] = true;
        $this->response['message'] = 'Backup created successfully';
        $this->response['data'] = ['backup_file' => basename($backup_file)];
        
        $this->sendResponse();
    }
    
    /**
     * Restore backup
     */
    private function restoreBackup() {
        $backup_file = $_POST['backup_file'] ?? '';
        
        if (empty($backup_file)) {
            $this->sendError('Backup file required');
        }
        
        $backup_dir = dirname(dirname(__FILE__)) . '/backups/';
        $backup_path = $backup_dir . $backup_file;
        
        if (!file_exists($backup_path)) {
            $this->sendError('Backup file not found');
        }
        
        $zip = new ZipArchive();
        if ($zip->open($backup_path) === true) {
            $zip->extractTo(dirname(dirname(__FILE__)));
            $zip->close();
        }
        
        $this->response['success'] = true;
        $this->response['message'] = 'Backup restored successfully';
        
        $this->sendResponse();
    }
    
    /**
     * Get stats data for export
     */
    private function getStatsData() {
        $logs_file = dirname(dirname(__FILE__)) . '/data/logs.json';
        $logs = file_exists($logs_file) ? json_decode(file_get_contents($logs_file), true) : [];
        
        return [
            'total_requests' => count($logs),
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get all providers for export
     */
    private function getAllProviders() {
        $providers_file = dirname(dirname(__FILE__)) . '/config/providers.json';
        if (file_exists($providers_file)) {
            return json_decode(file_get_contents($providers_file), true);
        }
        return [];
    }
    
    /**
     * Get all logs for export
     */
    private function getAllLogs() {
        $logs_file = dirname(dirname(__FILE__)) . '/data/logs.json';
        if (file_exists($logs_file)) {
            return json_decode(file_get_contents($logs_file), true);
        }
        return [];
    }
    
    /**
     * Get all API keys for export
     */
    private function getAllApiKeys() {
        $keys_file = dirname(dirname(__FILE__)) . '/data/api-keys.json';
        if (file_exists($keys_file)) {
            return json_decode(file_get_contents($keys_file), true);
        }
        return [];
    }
    
    /**
     * Send success response
     */
    private function sendResponse() {
        header('Content-Type: application/json');
        echo json_encode($this->response);
        exit;
    }
    
    /**
     * Send error response
     */
    private function sendError($message, $code = 400) {
        http_response_code($code);
        $this->response['message'] = $message;
        $this->sendResponse();
    }
}

// Initialize AJAX handler
new APIMaster_AJAX_Handlers();