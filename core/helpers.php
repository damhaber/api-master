<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Debug için değişken yazdır
 */
function api_master_debug($data, $die = false) {
    echo '<pre style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin: 10px 0; font-family: monospace; font-size: 12px; overflow: auto;">';
    print_r($data);
    echo '</pre>';
    
    if ($die) {
        die();
    }
}

/**
 * JSON formatında debug
 */
function api_master_debug_json($data, $die = false) {
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if ($die) {
        die();
    }
}

/**
 * Log yaz
 */
function api_master_log($message, $level = 'info', $context = []) {
    $moduleDir = dirname(__DIR__, 1);
    $logFile = $moduleDir . '/logs/api-master.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $logEntry = '[' . date('Y-m-d H:i:s') . "] [" . strtoupper($level) . "] {$message}{$contextStr}\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * URL güvenli base64 encode
 */
function api_master_base64_url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * URL güvenli base64 decode
 */
function api_master_base64_url_decode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

/**
 * Rastgele string oluştur
 */
function api_master_random_string($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * IP adresini al
 */
function api_master_get_ip() {
    $ip = '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    $ip = explode(',', $ip)[0];
    
    return trim($ip);
}

/**
 * User Agent al
 */
function api_master_get_user_agent() {
    return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
}

/**
 * Veriyi temizle (WordPress fonksiyonları olmadan)
 */
function api_master_sanitize($data, $type = 'text') {
    switch ($type) {
        case 'email':
            return filter_var(trim($data), FILTER_SANITIZE_EMAIL);
        case 'url':
            return filter_var(trim($data), FILTER_SANITIZE_URL);
        case 'int':
            return intval($data);
        case 'float':
            return floatval($data);
        case 'json':
            $decoded = json_decode($data, true);
            return is_array($decoded) ? $decoded : [];
        case 'text':
        default:
            return htmlspecialchars(strip_tags(trim((string)$data)), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Diziyi temizle
 */
function api_master_sanitize_array($data) {
    $sanitized = [];
    
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $sanitized[$key] = api_master_sanitize_array($value);
        } else {
            $sanitized[$key] = api_master_sanitize($value);
        }
    }
    
    return $sanitized;
}

/**
 * Mikro zamanı al
 */
function api_master_microtime() {
    return microtime(true);
}

/**
 * İşlem süresini hesapla
 */
function api_master_execution_time($start) {
    return (microtime(true) - $start) * 1000;
}

/**
 * Boyutu formatla
 */
function api_master_format_size($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Süreyi formatla
 */
function api_master_format_time($seconds) {
    $units = [
        'yıl' => 31536000,
        'ay' => 2592000,
        'hafta' => 604800,
        'gün' => 86400,
        'saat' => 3600,
        'dakika' => 60,
        'saniye' => 1
    ];
    
    if ($seconds < 1) {
        return '1 saniyeden az';
    }
    
    $result = [];
    
    foreach ($units as $name => $value) {
        if ($seconds >= $value) {
            $count = floor($seconds / $value);
            $result[] = $count . ' ' . $name;
            $seconds %= $value;
        }
    }
    
    return implode(' ', array_slice($result, 0, 2));
}

/**
 * Array'dan rastgele değer seç
 */
function api_master_array_random($array) {
    if (empty($array)) {
        return null;
    }
    return $array[array_rand($array)];
}

/**
 * Diziyi yüzdelere göre ağırlıklandır
 */
function api_master_weighted_random($weights) {
    $total = array_sum($weights);
    $rand = mt_rand(1, $total);
    
    foreach ($weights as $key => $weight) {
        $rand -= $weight;
        if ($rand <= 0) {
            return $key;
        }
    }
    
    $keys = array_keys($weights);
    return !empty($keys) ? $keys[0] : null;
}

/**
 * JSON yanıt gönder
 */
function api_master_json_response($data, $status = 200, $exit = true) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if ($exit) {
        exit;
    }
}

/**
 * Hata yanıtı gönder
 */
function api_master_error_response($message, $code = 400, $exit = true) {
    api_master_json_response([
        'success' => false,
        'error' => $message,
        'code' => $code
    ], $code, $exit);
}

/**
 * Başarı yanıtı gönder
 */
function api_master_success_response($data = null, $exit = true) {
    $response = ['success' => true];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    api_master_json_response($response, 200, $exit);
}

/**
 * Dosya varlığını kontrol et ve oluştur
 */
function api_master_ensure_directory($path) {
    if (!file_exists($path)) {
        return mkdir($path, 0755, true);
    }
    
    return true;
}

/**
 * .htaccess koruması ekle
 */
function api_master_protect_directory($path) {
    $htaccess = $path . '.htaccess';
    
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Order Deny,Allow\nDeny from all\n");
    }
    
    $index = $path . 'index.html';
    
    if (!file_exists($index)) {
        file_put_contents($index, "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Access Denied</h1></body></html>");
    }
}

/**
 * Environment değişkenini al
 */
function api_master_env($key, $default = null) {
    $value = getenv($key);
    
    if ($value === false) {
        return $default;
    }
    
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'empty':
        case '(empty)':
            return '';
        case 'null':
        case '(null)':
            return null;
    }
    
    return $value;
}