<?php
/**
 * Cleanup Job for Masal Panel - APIMaster
 * 
 * Tek başına çalıştırılabilir cleanup script
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load required classes
require_once dirname(__DIR__) . '/corn/class-cron-manager.php';

/**
 * Run cleanup job
 * 
 * @return array Cleanup results
 */
function run_cleanup_job(): array
{
    $cron = new APIMaster_CronManager();
    
    $results = [];
    $results['cleanup_logs'] = $cron->cleanupLogs();
    $results['cleanup_cache'] = $cron->cleanupCache();
    $results['rotate_logs'] = $cron->rotateLogs();
    $results['cleanup_temp'] = $cron->cleanupTemp();
    
    return $results;
}

// CLI mode check
if (php_sapi_name() === 'cli') {
    $results = run_cleanup_job();
    echo "Cleanup Job Results:\n";
    echo "====================\n";
    
    foreach ($results as $job => $result) {
        echo "{$job}: {$result}\n";
    }
}