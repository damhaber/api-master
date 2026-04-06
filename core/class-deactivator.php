<?php
if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_Deactivator
{
    private $moduleDir;
    private $cleanData;
    private $settingsFile;

    public function __construct($cleanData = false)
    {
        $this->moduleDir = dirname(__DIR__, 1);
        $this->settingsFile = $this->moduleDir . '/config/settings.json';
        $this->cleanData = $cleanData;
    }

    public function deactivate()
    {
        $this->writeLog('API Master modülü deaktifleştiriliyor...');
        
        $this->beforeDeactivation();
        $this->stopScheduledJobs();
        $this->clearWebhooks();
        $this->cleanTempFiles();
        $this->clearCache();
        
        if ($this->cleanData) {
            $this->cleanAllData();
        }
        
        $this->afterDeactivation();
        
        $this->writeLog('API Master modülü başarıyla deaktifleştirildi');
        
        return true;
    }

    private function beforeDeactivation()
    {
        $this->processPendingRequests();
        $this->saveFinalMetrics();
        $this->backupFinalState();
        
        $this->writeLog('Ön deaktivasyon işlemleri tamamlandı');
    }

    private function processPendingRequests()
    {
        $pendingFile = $this->moduleDir . '/data/pending-requests.json';
        
        if (file_exists($pendingFile)) {
            $content = file_get_contents($pendingFile);
            $pending = json_decode($content, true);
            
            if (!empty($pending)) {
                $logFile = $this->moduleDir . '/logs/pending-requests-backup.log';
                $backupData = [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'requests' => $pending
                ];
                file_put_contents($logFile, json_encode($backupData, JSON_PRETTY_PRINT), FILE_APPEND | LOCK_EX);
                
                $this->writeLog('Bekleyen istekler yedeklendi', ['count' => count($pending)]);
            }
        }
    }

    private function saveFinalMetrics()
    {
        $metrics = [
            'deactivation_time' => time(),
            'total_requests' => $this->getTotalRequests(),
            'total_api_calls' => $this->getTotalApiCalls()
        ];
        
        $metricsFile = $this->moduleDir . '/data/final-metrics.json';
        file_put_contents($metricsFile, json_encode($metrics, JSON_PRETTY_PRINT), LOCK_EX);
        
        $this->writeLog('Son metrikler kaydedildi', $metrics);
    }

    private function getTotalRequests()
    {
        $logDir = $this->moduleDir . '/logs';
        $total = 0;
        
        if (is_dir($logDir)) {
            $files = glob($logDir . '/api-master.log');
            foreach ($files as $file) {
                $lines = file($file, FILE_IGNORE_NEW_LINES);
                $total += count($lines);
            }
        }
        
        return $total;
    }

    private function getTotalApiCalls()
    {
        $callsFile = $this->moduleDir . '/data/api-calls-count.txt';
        
        if (file_exists($callsFile)) {
            return (int)file_get_contents($callsFile);
        }
        
        return 0;
    }

    private function backupFinalState()
    {
        $backupDir = $this->moduleDir . '/backups';
        
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $backupFile = $backupDir . '/pre-deactivation-backup-' . date('Y-m-d-H-i-s') . '.json';
        
        $state = [
            'timestamp' => date('Y-m-d H:i:s'),
            'metrics' => [
                'total_requests' => $this->getTotalRequests(),
                'total_api_calls' => $this->getTotalApiCalls()
            ],
            'directories' => $this->checkDirectories(),
            'active_providers' => $this->getActiveProviders()
        ];
        
        file_put_contents($backupFile, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
        
        $this->writeLog('Son durum yedeği alındı', ['backup_file' => $backupFile]);
    }

    private function getActiveProviders()
    {
        $providersFile = $this->moduleDir . '/config/providers.json';
        
        if (file_exists($providersFile)) {
            $content = file_get_contents($providersFile);
            $providers = json_decode($content, true);
            
            if (is_array($providers)) {
                $active = [];
                foreach ($providers as $name => $data) {
                    if (isset($data['active']) && $data['active'] === true) {
                        $active[] = $name;
                    }
                }
                return $active;
            }
        }
        
        return [];
    }

    private function stopScheduledJobs()
    {
        $cronFile = $this->moduleDir . '/config/cron-jobs.json';
        
        if (file_exists($cronFile)) {
            $backupFile = $this->moduleDir . '/backups/cron-jobs-' . date('Y-m-d-H-i-s') . '.json';
            copy($cronFile, $backupFile);
            
            $content = file_get_contents($cronFile);
            $jobs = json_decode($content, true);
            $disabledJobs = [];
            
            if (is_array($jobs)) {
                foreach ($jobs as $name => $schedule) {
                    $disabledJobs[$name] = [
                        'schedule' => $schedule,
                        'enabled' => false,
                        'disabled_at' => date('Y-m-d H:i:s')
                    ];
                }
            }
            
            file_put_contents($cronFile, json_encode($disabledJobs, JSON_PRETTY_PRINT), LOCK_EX);
            
            $this->writeLog('Zamanlanmış görevler durduruldu');
        }
    }

    private function clearWebhooks()
    {
        $webhookFile = $this->moduleDir . '/config/webhooks.json';
        
        if (file_exists($webhookFile)) {
            $backupFile = $this->moduleDir . '/backups/webhooks-' . date('Y-m-d-H-i-s') . '.json';
            copy($webhookFile, $backupFile);
            
            $content = file_get_contents($webhookFile);
            $webhooks = json_decode($content, true);
            
            if (is_array($webhooks)) {
                foreach ($webhooks as &$webhook) {
                    $webhook['active'] = false;
                    $webhook['deactivated_at'] = date('Y-m-d H:i:s');
                }
                file_put_contents($webhookFile, json_encode($webhooks, JSON_PRETTY_PRINT), LOCK_EX);
            }
            
            $this->writeLog('Webhook\'lar temizlendi');
        }
    }

    private function cleanTempFiles()
    {
        $tempDir = $this->moduleDir . '/temp';
        
        if (is_dir($tempDir)) {
            $files = glob($tempDir . '/*');
            $deletedCount = 0;
            
            foreach ($files as $file) {
                if (is_file($file) && unlink($file)) {
                    $deletedCount++;
                }
            }
            
            $this->writeLog('Geçici dosyalar temizlendi', ['deleted' => $deletedCount]);
        }
    }

    private function clearCache()
    {
        $cacheDir = $this->moduleDir . '/cache';
        
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            $deletedCount = 0;
            
            foreach ($files as $file) {
                $basename = basename($file);
                if (is_file($file) && $basename !== '.htaccess' && $basename !== 'index.html') {
                    if (unlink($file)) {
                        $deletedCount++;
                    }
                }
            }
            
            $this->writeLog('Önbellek temizlendi', ['deleted' => $deletedCount]);
        }
    }

    private function cleanAllData()
    {
        $this->writeLog('Tüm veriler temizleniyor...');
        
        $this->cleanLogFiles();
        $this->resetConfigurations();
        $this->cleanDataDirectory();
        $this->clearActivationRecord();
        
        $this->writeLog('Tüm veriler temizlendi');
    }

    private function cleanLogFiles()
    {
        $logDir = $this->moduleDir . '/logs';
        
        if (is_dir($logDir)) {
            $files = glob($logDir . '/*.log');
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            
            $this->writeLog('Log dosyaları temizlendi');
        }
    }

    private function resetConfigurations()
    {
        $configDir = $this->moduleDir . '/config';
        
        if (is_dir($configDir)) {
            $files = glob($configDir . '/*.json');
            
            foreach ($files as $file) {
                $basename = basename($file);
                if ($basename !== 'settings.json') {
                    unlink($file);
                }
            }
            
            if (file_exists($this->settingsFile)) {
                $content = file_get_contents($this->settingsFile);
                $settings = json_decode($content, true);
                
                if (!is_array($settings)) {
                    $settings = [];
                }
                
                unset($settings['api_keys']);
                
                $resetSettings = [
                    'version' => isset($settings['version']) ? $settings['version'] : '1.0.0',
                    'reset_at' => date('Y-m-d H:i:s'),
                    'reset_reason' => 'deactivation_clean'
                ];
                
                file_put_contents($this->settingsFile, json_encode($resetSettings, JSON_PRETTY_PRINT), LOCK_EX);
            }
        }
    }

    private function cleanDataDirectory()
    {
        $dataDir = $this->moduleDir . '/data';
        
        if (is_dir($dataDir)) {
            $exclude = ['activation-time.txt'];
            $files = glob($dataDir . '/*');
            
            foreach ($files as $file) {
                $basename = basename($file);
                if (is_file($file) && !in_array($basename, $exclude)) {
                    unlink($file);
                }
            }
            
            $this->writeLog('Veri dizini temizlendi');
        }
    }

    private function clearActivationRecord()
    {
        $activationFile = $this->moduleDir . '/data/activation-time.txt';
        
        if (file_exists($activationFile)) {
            unlink($activationFile);
        }
    }

    private function afterDeactivation()
    {
        $deactivationTime = time();
        file_put_contents($this->moduleDir . '/data/deactivation-time.txt', $deactivationTime, LOCK_EX);
        
        $statusFile = $this->moduleDir . '/data/module-status.json';
        file_put_contents($statusFile, json_encode([
            'status' => 'deactivated',
            'deactivated_at' => date('Y-m-d H:i:s'),
            'clean_data' => $this->cleanData
        ], JSON_PRETTY_PRINT), LOCK_EX);
        
        $this->writeLog('Deaktivasyon sonrası işlemler tamamlandı');
    }

    private function checkDirectories()
    {
        $directories = [
            'logs' => $this->moduleDir . '/logs',
            'cache' => $this->moduleDir . '/cache',
            'data' => $this->moduleDir . '/data',
            'config' => $this->moduleDir . '/config'
        ];
        
        $status = [];
        foreach ($directories as $name => $path) {
            $status[$name] = [
                'exists' => is_dir($path),
                'size' => $this->getDirectorySize($path)
            ];
        }
        
        return $status;
    }

    private function getDirectorySize($dir)
    {
        $size = 0;
        
        if (is_dir($dir)) {
            $files = glob($dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $size += filesize($file);
                } elseif (is_dir($file)) {
                    $size += $this->getDirectorySize($file);
                }
            }
        }
        
        return $size;
    }

    private function writeLog($message, $context = [])
    {
        $logFile = $this->moduleDir . '/logs/api-master.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = '[' . date('Y-m-d H:i:s') . "] [INFO] {$message}{$contextStr}\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public static function isDeactivated()
    {
        $moduleDir = dirname(__DIR__, 1);
        $statusFile = $moduleDir . '/data/module-status.json';
        
        if (!file_exists($statusFile)) {
            return false;
        }
        
        $content = file_get_contents($statusFile);
        $status = json_decode($content, true);
        
        return isset($status['status']) && $status['status'] === 'deactivated';
    }

    public function getDeactivationInfo()
    {
        $info = [
            'deactivated' => self::isDeactivated(),
            'clean_data' => $this->cleanData,
            'deactivation_time' => null,
            'backups_available' => $this->checkBackups()
        ];
        
        $statusFile = $this->moduleDir . '/data/module-status.json';
        if (file_exists($statusFile)) {
            $content = file_get_contents($statusFile);
            $status = json_decode($content, true);
            $info['deactivation_time'] = isset($status['deactivated_at']) ? $status['deactivated_at'] : null;
        }
        
        return $info;
    }

    private function checkBackups()
    {
        $backupDir = $this->moduleDir . '/backups';
        
        if (!is_dir($backupDir)) {
            return ['available' => false, 'count' => 0];
        }
        
        $files = glob($backupDir . '/*');
        $size = $this->getDirectorySize($backupDir);
        
        return [
            'available' => count($files) > 0,
            'count' => count($files),
            'size' => $size,
            'size_human' => $this->formatBytes($size)
        ];
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}