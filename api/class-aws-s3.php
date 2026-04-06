<?php
/**
 * AWS S3 API Handler for Masal Panel
 * 
 * @package MasalPanel\APIMaster
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_AWS_S3 implements APIMaster_APIInterface {
    
    private $apiKey;
    private $accessKey;
    private $secretKey;
    private $region;
    private $bucket;
    private $usePathStyle = false;
    private $endpoint;
    private $model = 's3';
    private $config;
    
    public function __construct() {
        $this->config = $this->loadConfig();
        $this->accessKey = $this->config['access_key'] ?? '';
        $this->secretKey = $this->config['secret_key'] ?? '';
        $this->region = $this->config['region'] ?? 'us-east-1';
        $this->bucket = $this->config['bucket'] ?? '';
        $this->usePathStyle = $this->config['use_path_style'] ?? false;
        $this->endpoint = $this->config['endpoint'] ?? '';
    }
    
    private function loadConfig() {
        $configFile = dirname(__DIR__) . '/config/aws-s3.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true);
        }
        return [];
    }
    
    private function getEndpoint() {
        if ($this->endpoint) {
            return $this->endpoint;
        }
        
        if ($this->usePathStyle) {
            return "https://s3.{$this->region}.amazonaws.com/{$this->bucket}";
        }
        
        return "https://{$this->bucket}.s3.{$this->region}.amazonaws.com";
    }
    
    private function getSignature($method, $uri, $headers = [], $params = [], $contentHash = '', $date = '') {
        if (empty($date)) {
            $date = gmdate('Ymd\THis\Z');
        }
        
        $amzDate = $date;
        $dateStamp = substr($date, 0, 8);
        
        $canonicalUri = $uri;
        $canonicalQueryString = http_build_query($params);
        $canonicalHeaders = '';
        $signedHeaders = [];
        
        $headers['host'] = parse_url($this->getEndpoint(), PHP_URL_HOST);
        $headers['x-amz-date'] = $amzDate;
        
        if ($contentHash) {
            $headers['x-amz-content-sha256'] = $contentHash;
        }
        
        ksort($headers);
        
        foreach ($headers as $key => $value) {
            $canonicalHeaders .= strtolower($key) . ':' . trim($value) . "\n";
            $signedHeaders[] = strtolower($key);
        }
        
        $signedHeadersStr = implode(';', $signedHeaders);
        
        $canonicalRequest = $method . "\n" .
            $canonicalUri . "\n" .
            $canonicalQueryString . "\n" .
            $canonicalHeaders . "\n" .
            $signedHeadersStr . "\n" .
            ($contentHash ?: hash('sha256', ''));
        
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = $dateStamp . '/' . $this->region . '/s3/aws4_request';
        $stringToSign = $algorithm . "\n" .
            $amzDate . "\n" .
            $credentialScope . "\n" .
            hash('sha256', $canonicalRequest);
        
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        
        return $algorithm . ' Credential=' . $this->accessKey . '/' . $credentialScope .
            ', SignedHeaders=' . $signedHeadersStr .
            ', Signature=' . $signature;
    }
    
    private function curlRequest($method, $url, $headers = [], $body = '') {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        if ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }
        
        if (!empty($headers)) {
            $headerArray = [];
            foreach ($headers as $key => $value) {
                $headerArray[] = $key . ': ' . $value;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        }
        
        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->logError('cURL error: ' . $error);
            return false;
        }
        
        if ($httpCode >= 400) {
            $this->logError('HTTP error: ' . $httpCode . ' - ' . substr($response, 0, 500));
            return false;
        }
        
        return $response;
    }
    
    private function request($method, $uri, $headers = [], $params = [], $body = '') {
        if (empty($this->accessKey) || empty($this->secretKey)) {
            return false;
        }
        
        $url = $this->getEndpoint() . $uri;
        
        $contentHash = $body ? hash('sha256', $body) : hash('sha256', '');
        $date = gmdate('Ymd\THis\Z');
        $signature = $this->getSignature($method, $uri, $headers, $params, $contentHash, $date);
        
        $headers['Authorization'] = $signature;
        $headers['x-amz-date'] = $date;
        
        if ($body && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/octet-stream';
        }
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $response = $this->curlRequest($method, $url, $headers, $body);
        
        if ($response === false) {
            return false;
        }
        
        // Parse XML response
        if (!empty($response) && strpos($response, '<?xml') !== false) {
            return $this->parseXmlResponse($response);
        }
        
        return $response;
    }
    
    private function parseXmlResponse($xml) {
        $simpleXml = simplexml_load_string($xml);
        if ($simpleXml) {
            return json_decode(json_encode($simpleXml), true);
        }
        return [];
    }
    
    private function logError($message) {
        $logFile = dirname(__DIR__) . '/logs/aws-s3-error.log';
        $date = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$date] $message" . PHP_EOL, FILE_APPEND);
    }
    
    // ========== APIInterface REQUIRED METHODS ==========
    
    public function setApiKey($apiKey) {
        $this->apiKey = $apiKey;
        $this->accessKey = $apiKey;
        return $this;
    }
    
    public function setModel($model) {
        $this->model = $model;
        return $this;
    }
    
    public function getModel() {
        return $this->model;
    }
    
    public function complete($prompt, $params = []) {
        return ['error' => 'AWS S3 does not support text completion'];
    }
    
    public function stream($prompt, $callback, $params = []) {
        return false;
    }
    
    public function getModels() {
        return [
            ['id' => 's3', 'name' => 'S3 Standard', 'description' => 'AWS S3 Object Storage']
        ];
    }
    
    public function getCapabilities() {
        return [
            'list_objects' => true,
            'upload_object' => true,
            'get_object' => true,
            'delete_object' => true,
            'copy_object' => true,
            'presigned_urls' => true,
            'bucket_management' => true
        ];
    }
    
    public function checkHealth() {
        $result = $this->listObjects('', '/', 1);
        return $result !== false;
    }
    
    public function chat($messages, $params = []) {
        return ['error' => 'AWS S3 does not support chat functionality'];
    }
    
    public function extractText($filePath) {
        if (!file_exists($filePath)) {
            return false;
        }
        
        $content = file_get_contents($filePath);
        $mime = mime_content_type($filePath);
        
        if (strpos($mime, 'text/') === 0) {
            return $content;
        }
        
        return 'Extraction only supported for text files';
    }
    
    // ========== AWS S3 SPECIFIC METHODS ==========
    
    public function setCredentials($accessKey, $secretKey, $region = 'us-east-1') {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->region = $region;
        return $this;
    }
    
    public function setBucket($bucket) {
        $this->bucket = $bucket;
        return $this;
    }
    
    public function listObjects($prefix = '', $delimiter = '', $maxKeys = 1000, $marker = '') {
        $params = ['max-keys' => min($maxKeys, 1000)];
        
        if ($prefix) {
            $params['prefix'] = $prefix;
        }
        if ($delimiter) {
            $params['delimiter'] = $delimiter;
        }
        if ($marker) {
            $params['marker'] = $marker;
        }
        
        $result = $this->request('GET', '/', [], $params);
        
        if ($result && isset($result['Contents'])) {
            if (isset($result['Contents']['Key'])) {
                $result['Contents'] = [$result['Contents']];
            }
        }
        
        return $result;
    }
    
    public function uploadObject($key, $content, $contentType = 'application/octet-stream', $metadata = []) {
        if (is_string($content) && file_exists($content)) {
            $content = file_get_contents($content);
            if ($content === false) {
                return false;
            }
        }
        
        $headers = [
            'Content-Type' => $contentType,
            'Content-Length' => strlen($content)
        ];
        
        foreach ($metadata as $metaKey => $value) {
            $headers['x-amz-meta-' . $metaKey] = $value;
        }
        
        $result = $this->request('PUT', '/' . ltrim($key, '/'), $headers, [], $content);
        
        return $result !== false;
    }
    
    public function uploadFile($key, $localPath, $contentType = '') {
        if (!file_exists($localPath)) {
            $this->logError('File not found: ' . $localPath);
            return false;
        }
        
        if (empty($contentType)) {
            $contentType = mime_content_type($localPath) ?: 'application/octet-stream';
        }
        
        return $this->uploadObject($key, $localPath, $contentType);
    }
    
    public function getObject($key) {
        return $this->request('GET', '/' . ltrim($key, '/'));
    }
    
    public function downloadObject($key, $localPath) {
        $content = $this->getObject($key);
        
        if ($content === false) {
            return false;
        }
        
        $bytesWritten = file_put_contents($localPath, $content);
        
        if ($bytesWritten === false) {
            return false;
        }
        
        return true;
    }
    
    public function deleteObject($key) {
        $result = $this->request('DELETE', '/' . ltrim($key, '/'));
        return $result !== false;
    }
    
    public function deleteObjects($keys) {
        if (empty($keys)) {
            return true;
        }
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Delete>';
        foreach ($keys as $key) {
            $xml .= '<Object><Key>' . htmlspecialchars($key) . '</Key></Object>';
        }
        $xml .= '</Delete>';
        
        $headers = [
            'Content-Type' => 'application/xml',
            'Content-MD5' => base64_encode(md5($xml, true))
        ];
        
        $result = $this->request('POST', '/?delete', $headers, [], $xml);
        
        return $result !== false;
    }
    
    public function headObject($key) {
        $url = $this->getEndpoint() . '/' . ltrim($key, '/');
        
        $date = gmdate('Ymd\THis\Z');
        $headers = [];
        $signature = $this->getSignature('HEAD', '/' . ltrim($key, '/'), $headers, [], '', $date);
        $headers['Authorization'] = $signature;
        $headers['x-amz-date'] = $date;
        
        $responseHeaders = [];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$responseHeaders) {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) == 2) {
                $responseHeaders[trim($header[0])] = trim($header[1]);
            }
            return $len;
        });
        
        $headerArray = [];
        foreach ($headers as $k => $v) {
            $headerArray[] = $k . ': ' . $v;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            return false;
        }
        
        return $responseHeaders;
    }
    
    public function copyObject($sourceKey, $destinationKey, $sourceBucket = '') {
        $sourceBucket = $sourceBucket ?: $this->bucket;
        $sourcePath = "/{$sourceBucket}/" . ltrim($sourceKey, '/');
        
        $headers = ['x-amz-copy-source' => $sourcePath];
        $result = $this->request('PUT', '/' . ltrim($destinationKey, '/'), $headers);
        
        return $result !== false;
    }
    
    public function createBucket($bucketName = '', $region = '') {
        $bucket = $bucketName ?: $this->bucket;
        
        if (empty($bucket)) {
            return false;
        }
        
        $headers = [];
        $body = '';
        $targetRegion = $region ?: $this->region;
        
        if ($targetRegion !== 'us-east-1') {
            $body = '<?xml version="1.0" encoding="UTF-8"?>';
            $body .= '<CreateBucketConfiguration xmlns="http://s3.amazonaws.com/doc/2006-03-01/">';
            $body .= '<LocationConstraint>' . $targetRegion . '</LocationConstraint>';
            $body .= '</CreateBucketConfiguration>';
            $headers['Content-Type'] = 'application/xml';
        }
        
        $oldEndpoint = $this->endpoint;
        $this->endpoint = "https://s3.{$targetRegion}.amazonaws.com";
        
        $result = $this->request('PUT', '/' . $bucket, $headers, [], $body);
        
        $this->endpoint = $oldEndpoint;
        
        return $result !== false;
    }
    
    public function deleteBucket($bucketName = '') {
        $bucket = $bucketName ?: $this->bucket;
        
        if (empty($bucket)) {
            return false;
        }
        
        $result = $this->request('DELETE', '/' . $bucket);
        return $result !== false;
    }
    
    public function generatePresignedUrl($key, $expires = 3600, $method = 'GET') {
        if (empty($this->accessKey) || empty($this->secretKey)) {
            return false;
        }
        
        $expiration = time() + $expires;
        $date = gmdate('Ymd\THis\Z', $expiration);
        $dateStamp = gmdate('Ymd', $expiration);
        
        $uri = '/' . ltrim($key, '/');
        
        $params = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->accessKey . '/' . $dateStamp . '/' . $this->region . '/s3/aws4_request',
            'X-Amz-Date' => $date,
            'X-Amz-Expires' => $expires,
            'X-Amz-SignedHeaders' => 'host'
        ];
        
        $canonicalUri = $uri;
        $canonicalQueryString = http_build_query($params);
        $canonicalHeaders = "host:" . parse_url($this->getEndpoint(), PHP_URL_HOST) . "\n";
        $signedHeaders = 'host';
        
        $canonicalRequest = $method . "\n" .
            $canonicalUri . "\n" .
            $canonicalQueryString . "\n" .
            $canonicalHeaders . "\n" .
            $signedHeaders . "\n" .
            hash('sha256', '');
        
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = $dateStamp . '/' . $this->region . '/s3/aws4_request';
        $stringToSign = $algorithm . "\n" .
            $date . "\n" .
            $credentialScope . "\n" .
            hash('sha256', $canonicalRequest);
        
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        
        $params['X-Amz-Signature'] = $signature;
        
        return $this->getEndpoint() . $uri . '?' . http_build_query($params);
    }
    
    public function getBucketLocation($bucketName = '') {
        $bucket = $bucketName ?: $this->bucket;
        
        if (empty($bucket)) {
            return false;
        }
        
        $result = $this->request('GET', '/' . $bucket . '?location');
        
        if ($result && isset($result['LocationConstraint'])) {
            return $result['LocationConstraint'];
        }
        
        return 'us-east-1';
    }
}