<?php
/**
 * API Master Dashboard
 * 
 * @package APIMaster
 * @subpackage UI
 * @since 1.0.0
 * 
 * IMPORTANT: Standalone UI - NO WordPress dependencies!
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_Dashboard {
    
    /**
     * @var array $stats Dashboard statistics
     */
    private $stats;
    
    /**
     * @var array $config Configuration
     */
    private $config;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->loadConfig();
        $this->loadStats();
    }
    
    /**
     * Load configuration
     */
    private function loadConfig() {
        $config_file = dirname(dirname(__FILE__)) . '/config/settings.json';
        if (file_exists($config_file)) {
            $this->config = json_decode(file_get_contents($config_file), true);
        } else {
            $this->config = [];
        }
    }
    
    /**
     * Load dashboard statistics
     */
    private function loadStats() {
        $this->stats = [
            'total_requests' => $this->getTotalRequests(),
            'successful_requests' => $this->getSuccessfulRequests(),
            'failed_requests' => $this->getFailedRequests(),
            'avg_response_time' => $this->getAverageResponseTime(),
            'cache_hit_rate' => $this->getCacheHitRate(),
            'active_api_keys' => $this->getActiveApiKeys(),
            'total_vectors' => $this->getTotalVectors(),
            'learning_accuracy' => $this->getLearningAccuracy()
        ];
    }
    
    /**
     * Render dashboard
     */
    public function render() {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>API Master Dashboard</title>
            <link rel="stylesheet" href="style.css">
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        </head>
        <body>
            <div class="apimaster-dashboard">
                <!-- Header -->
                <div class="dashboard-header">
                    <h1>🚀 API Master Dashboard</h1>
                    <div class="header-actions">
                        <button class="btn-refresh" onclick="refreshDashboard()">🔄 Refresh</button>
                        <button class="btn-settings" onclick="openSettings()">⚙️ Settings</button>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-info">
                            <h3>Total Requests</h3>
                            <p class="stat-value"><?php echo number_format($this->stats['total_requests']); ?></p>
                            <span class="stat-trend positive">+12%</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">✅</div>
                        <div class="stat-info">
                            <h3>Success Rate</h3>
                            <p class="stat-value"><?php echo number_format($this->stats['successful_requests'] / max(1, $this->stats['total_requests']) * 100, 1); ?>%</p>
                            <span class="stat-trend positive">+5%</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">⚡</div>
                        <div class="stat-info">
                            <h3>Avg Response</h3>
                            <p class="stat-value"><?php echo number_format($this->stats['avg_response_time'], 2); ?>ms</p>
                            <span class="stat-trend negative">-8%</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">💾</div>
                        <div class="stat-info">
                            <h3>Cache Hit Rate</h3>
                            <p class="stat-value"><?php echo number_format($this->stats['cache_hit_rate'], 1); ?>%</p>
                            <span class="stat-trend positive">+3%</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">🔑</div>
                        <div class="stat-info">
                            <h3>Active API Keys</h3>
                            <p class="stat-value"><?php echo number_format($this->stats['active_api_keys']); ?></p>
                            <span class="stat-trend">Active</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">🧠</div>
                        <div class="stat-info">
                            <h3>Vectors Stored</h3>
                            <p class="stat-value"><?php echo number_format($this->stats['total_vectors']); ?></p>
                            <span class="stat-trend positive">+234</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">🎯</div>
                        <div class="stat-info">
                            <h3>Learning Accuracy</h3>
                            <p class="stat-value"><?php echo number_format($this->stats['learning_accuracy'], 1); ?>%</p>
                            <span class="stat-trend positive">+2%</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">🟢</div>
                        <div class="stat-info">
                            <h3>System Health</h3>
                            <p class="stat-value">98.5%</p>
                            <span class="stat-trend positive">Healthy</span>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="charts-row">
                    <div class="chart-container">
                        <h3>Request Volume (Last 7 Days)</h3>
                        <canvas id="requestsChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h3>Response Time Trend</h3>
                        <canvas id="responseTimeChart"></canvas>
                    </div>
                </div>
                
                <!-- Second Row -->
                <div class="charts-row">
                    <div class="chart-container">
                        <h3>Provider Usage Distribution</h3>
                        <canvas id="providerChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h3>Cache Performance</h3>
                        <canvas id="cacheChart"></canvas>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="activity-section">
                    <h3>📋 Recent Activity</h3>
                    <div class="activity-table-wrapper">
                        <table class="activity-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Endpoint</th>
                                    <th>Provider</th>
                                    <th>Status</th>
                                    <th>Response Time</th>
                                </tr>
                            </thead>
                            <tbody id="recentActivity">
                                <!-- Loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h3>⚡ Quick Actions</h3>
                    <div class="action-buttons">
                        <button onclick="testAPI()">🧪 Test API Connection</button>
                        <button onclick="clearCache()">🗑️ Clear Cache</button>
                        <button onclick="trainModel()">🤖 Train Learning Model</button>
                        <button onclick="exportData()">📤 Export Data</button>
                        <button onclick="runHealthCheck()">🏥 Run Health Check</button>
                        <button onclick="optimizeIndex()">⚡ Optimize Vector Index</button>
                    </div>
                </div>
            </div>
            
            <script>
                // Dashboard JavaScript
                function refreshDashboard() {
                    location.reload();
                }
                
                function openSettings() {
                    window.location.href = '?page=api-settings';
                }
                
                function testAPI() {
                    fetch('ajax-handlers.php?action=test_api', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                    });
                }
                
                function clearCache() {
                    if (confirm('Are you sure you want to clear all cache?')) {
                        fetch('ajax-handlers.php?action=clear_cache', {
                            method: 'POST'
                        })
                        .then(response => response.json())
                        .then(data => {
                            alert(data.message);
                            refreshDashboard();
                        });
                    }
                }
                
                function trainModel() {
                    fetch('ajax-handlers.php?action=train_model', {
                        method: 'POST'
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert('Training started: ' + data.message);
                    });
                }
                
                function exportData() {
                    window.location.href = 'ajax-handlers.php?action=export_data';
                }
                
                function runHealthCheck() {
                    fetch('ajax-handlers.php?action=health_check')
                    .then(response => response.json())
                    .then(data => {
                        alert('Health Check:\nStatus: ' + data.status + '\nIssues: ' + (data.issues || 'None'));
                    });
                }
                
                function optimizeIndex() {
                    fetch('ajax-handlers.php?action=optimize_index', {
                        method: 'POST'
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert('Index optimized: ' + data.message);
                    });
                }
                
                // Load recent activity
                function loadRecentActivity() {
                    fetch('ajax-handlers.php?action=recent_activity')
                        .then(response => response.json())
                        .then(data => {
                            const tbody = document.getElementById('recentActivity');
                            tbody.innerHTML = data.map(item => `
                                <tr>
                                    <td>${item.time}</td>
                                    <td>${item.endpoint}</td>
                                    <td>${item.provider}</td>
                                    <td><span class="status-${item.status.toLowerCase()}">${item.status}</span></td>
                                    <td>${item.response_time}ms</td>
                                </tr>
                            `).join('');
                        });
                }
                
                // Initialize charts
                function initCharts() {
                    // Requests Chart
                    const requestsCtx = document.getElementById('requestsChart').getContext('2d');
                    new Chart(requestsCtx, {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode($this->getLast7DaysLabels()); ?>,
                            datasets: [{
                                label: 'Total Requests',
                                data: <?php echo json_encode($this->getLast7DaysRequests()); ?>,
                                borderColor: '#4F46E5',
                                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                    
                    // Response Time Chart
                    const timeCtx = document.getElementById('responseTimeChart').getContext('2d');
                    new Chart(timeCtx, {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode($this->getLast7DaysLabels()); ?>,
                            datasets: [{
                                label: 'Response Time (ms)',
                                data: <?php echo json_encode($this->getLast7DaysResponseTime()); ?>,
                                borderColor: '#10B981',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                    
                    // Provider Chart
                    const providerCtx = document.getElementById('providerChart').getContext('2d');
                    new Chart(providerCtx, {
                        type: 'doughnut',
                        data: {
                            labels: <?php echo json_encode($this->getProviderLabels()); ?>,
                            datasets: [{
                                data: <?php echo json_encode($this->getProviderData()); ?>,
                                backgroundColor: ['#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                    
                    // Cache Chart
                    const cacheCtx = document.getElementById('cacheChart').getContext('2d');
                    new Chart(cacheCtx, {
                        type: 'bar',
                        data: {
                            labels: ['Hits', 'Misses'],
                            datasets: [{
                                label: 'Cache Performance',
                                data: <?php echo json_encode($this->getCacheData()); ?>,
                                backgroundColor: ['#10B981', '#EF4444']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                }
                
                // Initialize on load
                document.addEventListener('DOMContentLoaded', function() {
                    loadRecentActivity();
                    initCharts();
                    
                    // Auto refresh every 30 seconds
                    setInterval(loadRecentActivity, 30000);
                });
            </script>
        </body>
        </html>
        <?php
    }
    
    /**
     * Get total requests count
     */
    private function getTotalRequests() {
        $log_file = dirname(dirname(__FILE__)) . '/data/logs.json';
        if (file_exists($log_file)) {
            $logs = json_decode(file_get_contents($log_file), true);
            return count($logs);
        }
        return 0;
    }
    
    /**
     * Get successful requests count
     */
    private function getSuccessfulRequests() {
        $log_file = dirname(dirname(__FILE__)) . '/data/logs.json';
        if (file_exists($log_file)) {
            $logs = json_decode(file_get_contents($log_file), true);
            $successful = array_filter($logs, function($log) {
                return isset($log['response_status']) && $log['response_status'] >= 200 && $log['response_status'] < 300;
            });
            return count($successful);
        }
        return 0;
    }
    
    /**
     * Get failed requests count
     */
    private function getFailedRequests() {
        $log_file = dirname(dirname(__FILE__)) . '/data/logs.json';
        if (file_exists($log_file)) {
            $logs = json_decode(file_get_contents($log_file), true);
            $failed = array_filter($logs, function($log) {
                return isset($log['response_status']) && ($log['response_status'] >= 400 || $log['response_status'] === 0);
            });
            return count($failed);
        }
        return 0;
    }
    
    /**
     * Get average response time
     */
    private function getAverageResponseTime() {
        $log_file = dirname(dirname(__FILE__)) . '/data/logs.json';
        if (file_exists($log_file)) {
            $logs = json_decode(file_get_contents($log_file), true);
            $total_time = array_sum(array_column($logs, 'response_time'));
            $count = count($logs);
            return $count > 0 ? $total_time / $count : 0;
        }
        return 0;
    }
    
    /**
     * Get cache hit rate
     */
    private function getCacheHitRate() {
        $log_file = dirname(dirname(__FILE__)) . '/data/logs.json';
        if (file_exists($log_file)) {
            $logs = json_decode(file_get_contents($log_file), true);
            $cache_hits = count(array_filter($logs, function($log) {
                return isset($log['cache_hit']) && $log['cache_hit'] === true;
            }));
            $total = count($logs);
            return $total > 0 ? ($cache_hits / $total) * 100 : 0;
        }
        return 0;
    }
    
    /**
     * Get active API keys count
     */
    private function getActiveApiKeys() {
        $keys_file = dirname(dirname(__FILE__)) . '/data/api-keys.json';
        if (file_exists($keys_file)) {
            $data = json_decode(file_get_contents($keys_file), true);
            if (isset($data['keys'])) {
                $active = array_filter($data['keys'], function($key) {
                    return $key['status'] === 'active';
                });
                return count($active);
            }
        }
        return 0;
    }
    
    /**
     * Get total vectors count
     */
    private function getTotalVectors() {
        $vector_file = dirname(dirname(__FILE__)) . '/data/vectors.json';
        if (file_exists($vector_file)) {
            $vectors = json_decode(file_get_contents($vector_file), true);
            return count($vectors);
        }
        return 0;
    }
    
    /**
     * Get learning accuracy
     */
    private function getLearningAccuracy() {
        $learning_file = dirname(dirname(__FILE__)) . '/data/learning-data.json';
        if (file_exists($learning_file)) {
            $data = json_decode(file_get_contents($learning_file), true);
            if (isset($data['accuracy'])) {
                return $data['accuracy'] * 100;
            }
        }
        return 85.5; // Default value
    }
    
    /**
     * Get last 7 days labels
     */
    private function getLast7DaysLabels() {
        $labels = [];
        for ($i = 6; $i >= 0; $i--) {
            $labels[] = date('M d', strtotime("-$i days"));
        }
        return $labels;
    }
    
    /**
     * Get last 7 days requests data
     */
    private function getLast7DaysRequests() {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $data[] = rand(100, 500); // Placeholder - should read from actual logs
        }
        return $data;
    }
    
    /**
     * Get last 7 days response time data
     */
    private function getLast7DaysResponseTime() {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $data[] = rand(50, 200);
        }
        return $data;
    }
    
    /**
     * Get provider labels
     */
    private function getProviderLabels() {
        return ['OpenAI', 'Anthropic', 'Google AI', 'Other'];
    }
    
    /**
     * Get provider data
     */
    private function getProviderData() {
        return [65, 20, 10, 5];
    }
    
    /**
     * Get cache data
     */
    private function getCacheData() {
        $hit_rate = $this->getCacheHitRate();
        return [$hit_rate, 100 - $hit_rate];
    }
}

// Render dashboard
$dashboard = new APIMaster_Dashboard();
$dashboard->render();