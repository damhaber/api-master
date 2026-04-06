<?php
/**
 * Clear System Logs - AJAX Endpoint for Masal Panel
 * 
 * Sistem log dosyalarını temizler
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

class APIMaster_ClearLogs
{
    private $moduleDir;
    private $logDir;
    private $backupDir;
    
    private $validTypes = ['api-master', 'error', 'api-tests', 'all'];
    
    public function __construct()
    {
        $this->moduleDir = defined('API_MASTER_MODULE_DIR') 
            ? API_MASTER_MODULE_DIR 
            : dirname(__DIR__);
        
        $this->logDir = $this->moduleDir . '/logs/';
        $this->backupDir = $this->moduleDir . '/logs/backups/';
    }
    
    public function execute()
    {
        try {
            $logType = $this->sanitizeLogType($_POST['type'] ?? 'all');
            $createBackup = isset($_POST['backup']) ? filter_var($_POST['backup'], FILTER_VALIDATE_BOOLEAN) : true;
            $olderThanDays = isset($_POST['older_than']) ? intval($_POST['older_than']) : 0;
            
            $results = [
                'cleared' => [],
                'failed' => [],
                'backup_created' => false,
                'backup_file' => null
            ];
            
            // Log dizini kontrolü
            if (!is_dir($this->logDir)) {
                $this->sendResponse(true, 'Log dizini bulunamadı, temizlenecek log yok.', $results);
                return;
            }
            
            // Temizlenecek log dosyalarını belirle
            $logFiles = $this->getLogFilesToClear($logType, $olderThanDays);
            
            if (empty($logFiles)) {
                $this->sendResponse(true, 'Temizlenecek log dosyası bulunamadı.', $results);
                return;
            }
            
            // Yedek oluştur (isteğe bağlı)
            if ($createBackup) {
                $backupResult = $this->createBackup($logFiles);
                $results['backup_created'] = $backupResult['success'];
                $results['backup_file'] = $backupResult['file'];
                
                if (!$backupResult['success']) {
                    $results['backup_error'] = $backupResult['error'];
                }
            }
            
            // Log dosyalarını temizle
            foreach ($logFiles as $filePath => $info) {
                $result = $this->clearLogFile($filePath, $olderThanDays, $info['type']);
                
                if ($result['success']) {
                    $results['cleared'][] = [
                        'file' => $info['name'],
                        'type' => $info['type'],
                        'size' => $result['size'],
                        'lines_cleared' => $result['lines_cleared']
                    ];
                } else {
                    $results['failed'][] = [
                        'file' => $info['name'],
                        'type' => $info['type'],
                        'error' => $result['error']
                    ];
                }
            }
            
            // İşlem logu
            $this->logClearAction($results);
            
            // Yanıt oluştur
            $totalCleared = count($results['cleared']);
            $totalFailed = count($results['failed']);
            
            $message = "{$totalCleared} log dosyası temizlendi.";
            if ($totalFailed > 0) {
                $message .= " {$totalFailed} dosya temizlenemedi.";
            }
            if ($createBackup && $results['backup_created']) {
                $message .= " Yedek oluşturuldu: " . basename($results['backup_file']);
            }
            
            $this->sendResponse($totalFailed === 0, $message, $results);
            
        } catch (Exception $e) {
            $this->logError('Clear logs error: ' . $e->getMessage());
            $this->sendResponse(false, 'Loglar temizlenirken hata oluştu: ' . $e->getMessage());
        }
    }
    
    private function sanitizeLogType($type)
    {
        $type = preg_replace('/[^a-z-]/', '', strtolower(trim($type)));
        
        if (!in_array($type, $this->validTypes)) {
            return 'all';
        }
        
        return $type;
    }
    
    private function getLogFilesToClear($logType, $olderThanDays)
    {
        $files = [];
        $logFiles = glob($this->logDir . '*.log');
        
        if (empty($logFiles)) {
            return $files;
        }
        
        foreach ($logFiles as $filePath) {
            $fileName = basename($filePath);
            $fileType = str_replace('.log', '', $fileName);
            
            // Tip filtresi
            if ($logType !== 'all' && $fileType !== $logType) {
                continue;
            }
            
            // Tarih filtresi (belirtilen günden eski dosyalar)
            if ($olderThanDays > 0) {
                $fileTime = filemtime($filePath);
                $daysOld = (time() - $fileTime) / (60 * 60 * 24);
                
                if ($daysOld < $olderThanDays) {
                    continue;
                }
            }
            
            $files[$filePath] = [
                'name' => $fileName,
                'type' => $fileType,
                'size' => filesize($filePath),
                'modified' => filemtime($filePath)
            ];
        }
        
        return $files;
    }
    
    private function clearLogFile($filePath, $olderThanDays, $logType)
    {
        $result = [
            'success' => false,
            'size' => 0,
            'lines_cleared' => 0,
            'error' => null
        ];
        
        if (!file_exists($filePath)) {
            $result['error'] = 'Dosya bulunamadı';
            return $result;
        }
        
        // Dosya boyutunu kaydet (KB)
        $result['size'] = round(filesize($filePath) / 1024, 2);
        
        try {
            if ($olderThanDays > 0 && $logType !== 'error') {
                // Belirli günden eski logları temizle (satır bazlı)
                $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines === false) {
                    throw new Exception('Dosya okunamadı');
                }
                
                $cutoffDate = date('Y-m-d', strtotime("-$olderThanDays days"));
                $newLines = [];
                
                foreach ($lines as $line) {
                    $lineDate = $this->extractDateFromLog($line);
                    if ($lineDate && $lineDate >= $cutoffDate) {
                        $newLines[] = $line;
                    } elseif (!$lineDate) {
                        // Tarihi parse edilemeyen satırları da koru (başlıklar vs)
                        if (strpos($line, '===') !== false || strpos($line, '---') !== false) {
                            $newLines[] = $line;
                        }
                    }
                }
                
                $result['lines_cleared'] = count($lines) - count($newLines);
                file_put_contents($filePath, implode("\n", $newLines) . "\n", LOCK_EX);
            } else {
                // Dosyayı tamamen temizle
                $handle = fopen($filePath, 'w');
                if ($handle === false) {
                    throw new Exception('Dosya açılamadı');
                }
                
                ftruncate($handle, 0);
                fclose($handle);
                
                // Başlık ekle
                $header = "# Log dosyası " . date('Y-m-d H:i:s') . " tarihinde temizlendi\n";
                file_put_contents($filePath, $header, LOCK_EX);
                
                $result['lines_cleared'] = -1; // Tam temizlik
            }
            
            $result['success'] = true;
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    private function extractDateFromLog($line)
    {
        // JSON formatı: "timestamp":"2024-03-31 10:30:15"
        if (preg_match('/"timestamp":"(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
            return $matches[1];
        }
        
        // Text formatı: [2024-03-31 10:30:15]
        if (preg_match('/\[(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    private function createBackup($logFiles)
    {
        $result = [
            'success' => false,
            'file' => null,
            'error' => null
        ];
        
        // ZipArchive kontrolü
        if (!class_exists('ZipArchive')) {
            $result['error'] = 'ZipArchive sınıfı bulunamadı (PHP zip extension gerekli)';
            return $result;
        }
        
        // Backup dizinini oluştur
        if (!is_dir($this->backupDir)) {
            if (!mkdir($this->backupDir, 0755, true)) {
                $result['error'] = 'Backup dizini oluşturulamadı';
                return $result;
            }
        }
        
        // Backup dosya adı
        $backupFile = $this->backupDir . 'logs_backup_' . date('Y-m-d_H-i-s') . '.zip';
        
        // ZIP arşivi oluştur
        $zip = new ZipArchive();
        if ($zip->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $result['error'] = 'ZIP arşivi oluşturulamadı';
            return $result;
        }
        
        $added = 0;
        foreach ($logFiles as $filePath => $info) {
            if (file_exists($filePath) && filesize($filePath) > 0) {
                $zip->addFile($filePath, $info['name']);
                $added++;
            }
        }
        
        $zip->close();
        
        if ($added === 0) {
            @unlink($backupFile);
            $result['error'] = 'Yedeklenecek dosya bulunamadı';
            return $result;
        }
        
        $result['success'] = true;
        $result['file'] = $backupFile;
        $result['size'] = round(filesize($backupFile) / 1024, 2);
        $result['files_count'] = $added;
        
        return $result;
    }
    
    private function logClearAction($results)
    {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => 'info',
            'source' => 'clear-logs',
            'action' => 'logs_cleared',
            'cleared_files' => count($results['cleared']),
            'failed_files' => count($results['failed']),
            'backup_created' => $results['backup_created'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        if ($results['backup_created']) {
            $logEntry['backup_file'] = basename($results['backup_file']);
        }
        
        file_put_contents(
            $this->logDir . 'api-master.log',
            json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
    
    private function logError($message)
    {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => 'error',
            'source' => 'clear-logs',
            'message' => $message,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        file_put_contents(
            $this->logDir . 'error.log',
            json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
    
    private function sendResponse($success, $message, $data = [])
    {
        $response = [
            'success' => $success,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if (!empty($data)) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Execute
$clearLogs = new APIMaster_ClearLogs();
$clearLogs->execute();