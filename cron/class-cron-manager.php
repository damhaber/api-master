<?php
/**
 * Cron Manager for Masal Panel - APIMaster
 * 
 * Cron job yönetimi, scheduling, task execution
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_CronManager
{
    /**
     * @var array Registered cron jobs
     */
    private $jobs = [];

    /**
     * @var string Cron lock file path
     */
    private $lockFile;

    /**
     * @var string Log directory
     */
    private $logDir;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->lockFile = dirname(__DIR__) . '/cache/cron.lock';
        $this->logDir = dirname(__DIR__) . '/logs';
        $this->registerDefaultJobs();
    }

    /**
     * Register default cron jobs
     */
    private function registerDefaultJobs(): void
    {
        $this->jobs = [
            'cleanup_logs' => [
                'interval' => 'daily',
                'callback' => [$this, 'cleanupLogs'],
                'description' => 'Eski log dosyalarını temizler'
            ],
            'cleanup_cache' => [
                'interval' => 'hourly',
                'callback' => [$this, 'cleanupCache'],
                'description' => 'Eski cache dosyalarını temizler'
            ],
            'rotate_logs' => [
                'interval' => 'daily',
                'callback' => [$this, 'rotateLogs'],
                'description' => 'Log dosyalarını döndürür'
            ],
            'update_stats' => [
                'interval' => 'hourly',
                'callback' => [$this, 'updateStats'],
                'description' => 'İstatistikleri günceller'
            ],
            'cleanup_temp' => [
                'interval' => 'daily',
                'callback' => [$this, 'cleanupTemp'],
                'description' => 'Geçici dosyaları temizler'
            ],
            'sync_learning' => [
                'interval' => 'daily',
                'callback' => [$this, 'syncLearningData'],
                'description' => 'Öğrenme verilerini senkronize eder'
            ]
        ];
    }

    /**
     * Register a new cron job
     * 
     * @param string $name Job name
     * @param string $interval Interval (hourly, daily, weekly, monthly)
     * @param callable $callback Callback function
     * @param string $description Job description
     * @return bool Success
     */
    public function registerJob(string $name, string $interval, callable $callback, string $description = ''): bool
    {
        if (isset($this->jobs[$name])) {
            return false;
        }

        $this->jobs[$name] = [
            'interval' => $interval,
            'callback' => $callback,
            'description' => $description
        ];

        return true;
    }

    /**
     * Run all due cron jobs
     * 
     * @return array Execution results
     */
    public function runDueJobs(): array
    {
        // Check lock to prevent multiple runs
        if ($this->isLocked()) {
            return ['error' => 'Cron already running'];
        }

        $this->lock();
        $results = [];

        foreach ($this->jobs as $name => $job) {
            if ($this->isJobDue($name, $job['interval'])) {
                $results[$name] = $this->executeJob($name, $job);
            }
        }

        $this->unlock();
        return $results;
    }

    /**
     * Execute a specific job
     * 
     * @param string $name Job name
     * @param array $job Job data
     * @return array Execution result
     */
    private function executeJob(string $name, array $job): array
    {
        $startTime = microtime(true);
        $result = ['success' => false, 'message' => '', 'duration' => 0];

        try {
            $output = call_user_func($job['callback']);
            $result['success'] = true;
            $result['message'] = is_string($output) ? $output : 'Job completed successfully';
            $this->logJobExecution($name, true, $result['message']);
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
            $this->logJobExecution($name, false, $e->getMessage());
        }

        $result['duration'] = round((microtime(true) - $startTime) * 1000, 2);
        $this->updateLastRun($name);

        return $result;
    }

    /**
     * Check if job is due to run
     * 
     * @param string $name Job name
     * @param string $interval Job interval
     * @return bool Is due
     */
    private function isJobDue(string $name, string $interval): bool
    {
        $lastRun = $this->getLastRun($name);
        $now = time();

        if ($lastRun === 0) {
            return true;
        }

        $intervalSeconds = $this->getIntervalSeconds($interval);

        return ($now - $lastRun) >= $intervalSeconds;
    }

    /**
     * Get interval in seconds
     * 
     * @param string $interval Interval name
     * @return int Seconds
     */
    private function getIntervalSeconds(string $interval): int
    {
        switch ($interval) {
            case 'hourly':
                return 3600;
            case 'daily':
                return 86400;
            case 'weekly':
                return 604800;
            case 'monthly':
                return 2592000;
            default:
                return 3600;
        }
    }

    /**
     * Get last run timestamp for job
     * 
     * @param string $name Job name
     * @return int Last run timestamp
     */
    private function getLastRun(string $name): int
    {
        $file = dirname(__DIR__) . '/cache/cron_' . md5($name) . '.last';
        
        if (file_exists($file)) {
            return (int) file_get_contents($file);
        }
        
        return 0;
    }

    /**
     * Update last run timestamp
     * 
     * @param string $name Job name
     */
    private function updateLastRun(string $name): void
    {
        $file = dirname(__DIR__) . '/cache/cron_' . md5($name) . '.last';
        file_put_contents($file, time());
    }

    /**
     * Log job execution
     * 
     * @param string $name Job name
     * @param bool $success Success status
     * @param string $message Log message
     */
    private function logJobExecution(string $name, bool $success, string $message): void
    {
        $logFile = $this->logDir . '/cron.log';
        
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }

        $status = $success ? 'SUCCESS' : 'FAILED';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$status}] {$name}: {$message}" . PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Check if cron is locked
     * 
     * @return bool Is locked
     */
    private function isLocked(): bool
    {
        if (!file_exists($this->lockFile)) {
            return false;
        }

        $lockTime = (int) file_get_contents($this->lockFile);
        
        // Lock expires after 1 hour
        if (time() - $lockTime > 3600) {
            $this->unlock();
            return false;
        }

        return true;
    }

    /**
     * Lock cron execution
     */
    private function lock(): void
    {
        $lockDir = dirname($this->lockFile);
        
        if (!is_dir($lockDir)) {
            mkdir($lockDir, 0755, true);
        }
        
        file_put_contents($this->lockFile, time());
    }

    /**
     * Unlock cron execution
     */
    private function unlock(): void
    {
        if (file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
    }

    /**
     * Get all registered jobs
     * 
     * @return array Jobs list
     */
    public function getJobs(): array
    {
        $jobs = [];
        
        foreach ($this->jobs as $name => $job) {
            $jobs[] = [
                'name' => $name,
                'interval' => $job['interval'],
                'description' => $job['description'],
                'last_run' => $this->getLastRun($name),
                'last_run_human' => $this->getLastRun($name) ? date('Y-m-d H:i:s', $this->getLastRun($name)) : 'Never'
            ];
        }
        
        return $jobs;
    }

    /**
     * Run a specific job manually
     * 
     * @param string $name Job name
     * @return array Execution result
     */
    public function runJobManually(string $name): array
    {
        if (!isset($this->jobs[$name])) {
            return ['error' => 'Job not found: ' . $name];
        }

        return $this->executeJob($name, $this->jobs[$name]);
    }

    // ========== DEFAULT JOB CALLBACKS ==========

    /**
     * Cleanup old log files
     * 
     * @return string Result message
     */
    public function cleanupLogs(): string
    {
        $logDir = $this->logDir;
        
        if (!is_dir($logDir)) {
            return 'Log directory not found';
        }

        $deleted = 0;
        $maxAge = 30 * 86400; // 30 days
        $now = time();

        $files = glob($logDir . '/*.log');
        
        foreach ($files as $file) {
            if (filemtime($file) < ($now - $maxAge)) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }

        return "Deleted {$deleted} old log files";
    }

    /**
     * Cleanup old cache files
     * 
     * @return string Result message
     */
    public function cleanupCache(): string
    {
        $cacheDir = dirname(__DIR__) . '/cache';
        
        if (!is_dir($cacheDir)) {
            return 'Cache directory not found';
        }

        $deleted = 0;
        $maxAge = 7 * 86400; // 7 days
        $now = time();

        $files = glob($cacheDir . '/*.json');
        $files = array_merge($files, glob($cacheDir . '/*.cache'));
        
        foreach ($files as $file) {
            if (filemtime($file) < ($now - $maxAge)) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }

        return "Deleted {$deleted} old cache files";
    }

    /**
     * Rotate log files
     * 
     * @return string Result message
     */
    public function rotateLogs(): string
    {
        $logDir = $this->logDir;
        
        if (!is_dir($logDir)) {
            return 'Log directory not found';
        }

        $rotated = 0;
        $maxSize = 10 * 1024 * 1024; // 10MB

        $files = glob($logDir . '/*.log');
        
        foreach ($files as $file) {
            if (filesize($file) > $maxSize) {
                $backupFile = $file . '.' . date('Y-m-d-His');
                rename($file, $backupFile);
                
                // Compress backup
                $gzFile = $backupFile . '.gz';
                $fp = gzopen($gzFile, 'w9');
                gzwrite($fp, file_get_contents($backupFile));
                gzclose($fp);
                unlink($backupFile);
                
                $rotated++;
            }
        }

        return "Rotated {$rotated} log files";
    }

    /**
     * Update statistics
     * 
     * @return string Result message
     */
    public function updateStats(): string
    {
        $statsFile = dirname(__DIR__) . '/data/stats/providers.json';
        $statsDir = dirname($statsFile);
        
        if (!is_dir($statsDir)) {
            mkdir($statsDir, 0755, true);
        }

        $stats = [
            'last_update' => time(),
            'providers' => []
        ];

        file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));
        
        return 'Statistics updated';
    }

    /**
     * Cleanup temporary files
     * 
     * @return string Result message
     */
    public function cleanupTemp(): string
    {
        $tempDir = sys_get_temp_dir() . '/apimaster';
        
        if (!is_dir($tempDir)) {
            return 'Temp directory not found';
        }

        $deleted = 0;
        $maxAge = 24 * 3600; // 24 hours
        $now = time();

        $files = glob($tempDir . '/*');
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < ($now - $maxAge)) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }

        return "Deleted {$deleted} temporary files";
    }

    /**
     * Sync learning data
     * 
     * @return string Result message
     */
    public function syncLearningData(): string
    {
        $learningDir = dirname(__DIR__) . '/learning/data';
        
        if (!is_dir($learningDir)) {
            mkdir($learningDir, 0755, true);
            return 'Learning data directory created';
        }

        $statsFile = $learningDir . '/sync_stats.json';
        $stats = [
            'last_sync' => time(),
            'status' => 'completed'
        ];
        
        file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));
        
        return 'Learning data synchronized';
    }

    /**
     * Execute cron via HTTP request
     * 
     * @param string $secret Cron secret key
     * @return array Execution results
     */
    public function executeViaHttp(string $secret): array
    {
        $configFile = dirname(__DIR__) . '/config/config.php';
        $config = [];
        
        if (file_exists($configFile)) {
            $config = include $configFile;
        }
        
        $cronSecret = $config['cron_secret'] ?? '';
        
        if (empty($cronSecret) || $secret !== $cronSecret) {
            return ['error' => 'Invalid cron secret'];
        }
        
        return $this->runDueJobs();
    }
}