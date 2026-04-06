<?php
/**
 * Clear Cache - AJAX Endpoint for Masal Panel
 * 
 * Cache dosyalarını temizler
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 */

if (!defined('ABSPATH')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

// Hata raporlamayı sessize al
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

class APIMaster_ClearCache
{
    private $moduleDir;
    private $cacheDir;
    private $logDir;
    
    private $validTypes = [
        'all' => 'Tüm cache',
        'api_response' => 'API yanıt cache',
        'embedding' => 'Embedding cache',
        'search' => 'Arama cache',
        'learning' => 'Öğrenme cache',
        'vector' => 'Vektör cache',
        'old' => 'Eski cache (30 günden eski)'
    ];
    
    public function __construct()
    {
        $this->moduleDir = defined('API_MASTER_MODULE_DIR') 
            ? API_MASTER_MODULE_DIR 
            : dirname(__DIR__);
        
        $this->cacheDir = $this->moduleDir . '/cache';
        $this->logDir = $this->moduleDir . '/logs';
    }
    
    public function execute()
    {
        try {
            $cacheType = isset($_POST['cache_type']) ? trim($_POST['cache_type']) : 'all';
            $confirm = isset($_POST['confirm']) ? (bool)$_POST['confirm'] : false;
            
            // Onay kontrolü
            if (!$confirm) {
                $this->sendResponse(false, 'Cache temizleme işlemi onaylanmadı');
                return;
            }
            
            // Geçerli tip kontrolü
            if (!isset($this->validTypes[$cacheType])) {
                $this->sendResponse(false, 'Geçersiz cache tipi: ' . $cacheType);
                return;
            }
            
            // Cache klasörü kontrolü
            if (!is_dir($this->cacheDir)) {
                $this->sendResponse(true, 'Cache klasörü bulunamadı, temizlenecek dosya yok', [
                    'deleted_count' => 0,
                    'freed_size' => 0,
                    'freed_size_human' => '0 B'
                ]);
                return;
            }
            
            $result = $this->clearCache($cacheType);
            $this->logAction($cacheType, $result);
            $this->sendResponse(true, $result['message'], $result['data']);
            
        } catch (Exception $e) {
            $this->sendResponse(false, 'Bir hata oluştu: ' . $e->getMessage());
        }
    }
    
    private function clearCache($cacheType)
    {
        $filesToDelete = $this->getFilesToDelete($cacheType);
        
        $deletedCount = 0;
        $freedSize = 0;
        $errors = [];
        
        foreach ($filesToDelete as $file) {
            if (is_file($file)) {
                $fileSize = filesize($file);
                if (unlink($file)) {
                    $deletedCount++;
                    $freedSize += $fileSize;
                } else {
                    $errors[] = basename($file);
                }
            }
        }
        
        // Son temizlenme zamanını güncelle
        $cleanLogFile = $this->cacheDir . '/last_cleaned.txt';
        file_put_contents($cleanLogFile, time());
        
        $typeName = $this->validTypes[$cacheType];
        $freedSizeHuman = $this->formatBytes($freedSize);
        
        $message = "{$typeName} temizlendi. {$deletedCount} dosya silindi, {$freedSizeHuman} alan kazanıldı.";
        
        if (!empty($errors)) {
            $message .= " (Silinemeyen dosyalar: " . implode(', ', $errors) . ")";
        }
        
        return [
            'message' => $message,
            'data' => [
                'cache_type' => $cacheType,
                'type_name' => $typeName,
                'deleted_count' => $deletedCount,
                'freed_size' => $freedSize,
                'freed_size_human' => $freedSizeHuman,
                'errors' => $errors,
                'cleaned_at' => time()
            ]
        ];
    }
    
    private function getFilesToDelete($cacheType)
    {
        switch ($cacheType) {
            case 'all':
                return glob($this->cacheDir . '/*.json');
                
            case 'api_response':
                return glob($this->cacheDir . '/api_*.json');
                
            case 'embedding':
                return glob($this->cacheDir . '/embedding_*.json');
                
            case 'search':
                return glob($this->cacheDir . '/search_*.json');
                
            case 'learning':
                return glob($this->cacheDir . '/learning_*.json');
                
            case 'vector':
                return glob($this->cacheDir . '/vector_*.json');
                
            case 'old':
                $allFiles = glob($this->cacheDir . '/*.json');
                $oldThreshold = time() - (30 * 24 * 60 * 60);
                $oldFiles = [];
                foreach ($allFiles as $file) {
                    if (filemtime($file) < $oldThreshold) {
                        $oldFiles[] = $file;
                    }
                }
                return $oldFiles;
                
            default:
                return [];
        }
    }
    
    private function logAction($cacheType, $result)
    {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        
        $logFile = $this->logDir . '/api-master.log';
        $typeName = $this->validTypes[$cacheType];
        $logEntry = '[' . date('Y-m-d H:i:s') . "] [INFO] Cache temizlendi - Tip: {$typeName}, Silinen: {$result['data']['deleted_count']} dosya, Kazanılan alan: {$result['data']['freed_size_human']}\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    private function sendResponse($success, $message, $data = [])
    {
        $response = [
            'success' => $success,
            'message' => $message
        ];
        
        if (!empty($data)) {
            $response['data'] = $data;
        }
        
        echo json_encode($response);
        exit;
    }
}

// Execute
$clearCache = new APIMaster_ClearCache();
$clearCache->execute();