<?php
/**
 * API Master Analytics Dashboard
 * 
 * @package APIMaster
 * @subpackage UI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_Analytics {
    
    private $logs;
    private $providers;
    
    public function __construct() {
        $this->loadLogs();
        $this->loadProviders();
    }
    
    private function loadLogs() {
        $logs_file = dirname(dirname(__FILE__)) . '/data/logs.json';
        if (file_exists($logs_file)) {
            $this->logs = json_decode(file_get_contents($logs_file), true);
        } else {
            $this->logs = [];
        }
    }
    
    private function loadProviders() {
        $providers_file = dirname(dirname(__FILE__)) . '/config/providers.json';
        if (file_exists($providers_file)) {
            $data = json_decode(file_get_contents($providers_file), true);
            $this->providers = $data['providers'] ?? [];
        } else {
            $this->providers = [];
        }
    }
    
    public function render() {
        $total_requests = count($this->logs);
        $successful = count(array_filter($this->logs, function($log) {
            return isset($log['response_status']) && $log['response_status'] >= 200 && $log['response_status'] < 300;
        }));
        $failed = $total_requests - $successful;
        $success_rate = $total_requests > 0 ? ($successful / $total_requests) * 100 : 0;
        
        $avg_response_time = $total_requests > 0 ? array_sum(array_column($this->logs, 'response_time')) / $total_requests : 0;
        
        // Provider statistics
        $provider_stats = [];
        foreach ($this->logs as $log) {
            $provider = $log['provider'] ?? 'unknown';
            if (!isset($provider_stats[$provider])) {
                $provider_stats[$provider] = ['total' => 0, 'success' => 0, 'total_time' => 0];
            }
            $provider_stats[$provider]['total']++;
            if (isset($log['response_status']) && $log['response_status'] >= 200 && $log['response_status'] < 300) {
                $provider_stats[$provider]['success']++;
            }
            $provider_stats[$provider]['total_time'] += $log['response_time'] ?? 0;
        }
        
        // Daily statistics
        $daily_stats = [];
        foreach ($this->logs as $log) {
            $date = substr($log['created_at'] ?? '', 0, 10);
            if (!isset($daily_stats[$date])) {
                $daily_stats[$date] = ['total' => 0, 'success' => 0, 'total_time' => 0];
            }
            $daily_stats[$date]['total']++;
            if (isset($log['response_status']) && $log['response_status'] >= 200 && $log['response_status'] < 300) {
                $daily_stats[$date]['success']++;
            }
            $daily_stats[$date]['total_time'] += $log['response_time'] ?? 0;
        }
        
        // Hourly distribution
        $hourly_stats = array_fill(0, 24, 0);
        foreach ($this->logs as $log) {
            $hour = (int)date('H', strtotime($log['created_at'] ?? 'now'));
            $hourly_stats[$hour]++;
        }
        
        // Status code distribution
        $status_stats = [];
        foreach ($this->logs as $log) {
            $status = (int)($log['response_status'] ?? 0);
            $group = floor($status / 100) * 100;
            $key = $group ? $group . 'xx' : 'unknown';
            if (!isset($status_stats[$key])) {
                $status_stats[$key] = 0;
            }
            $status_stats[$key]++;
        }
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>API Master - Analytics</title>
            <link rel="stylesheet" href="style.css">
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <style>
                .analytics-container {
                    max-width: 1400px;
                    margin: 0 auto;
                    padding: 20px;
                }
                
                .analytics-header {
                    background: white;
                    border-radius: 12px;
                    padding: 20px;
                    margin-bottom: 20px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                    gap: 20px;
                    margin-bottom: 20px;
                }
                
                .stat-card-large {
                    background: white;
                    border-radius: 12px;
                    padding: 20px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .stat-value-large {
                    font-size: 36px;
                    font-weight: 700;
                    color: #4F46E5;
                }
                
                .charts-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 20px;
                    margin-bottom: 20px;
                }
                
                .chart-card {
                    background: white;
                    border-radius: 12px;
                    padding: 20px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .chart-card h3 {
                    margin-bottom: 20px;
                    padding-bottom: 10px;
                    border-bottom: 2px solid #e5e7eb;
                }
                
                .chart-container {
                    height: 300px;
                    position: relative;
                }
                
                .providers-table {
                    background: white;
                    border-radius: 12px;
                    padding: 20px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    overflow-x: auto;
                }
                
                .providers-table table {
                    width: 100%;
                    border-collapse: collapse;
                }
                
                .providers-table th,
                .providers-table td {
                    padding: 12px;
                    text-align: left;
                    border-bottom: 1px solid #e5e7eb;
                }
                
                .providers-table th {
                    background: #f9fafb;
                    font-weight: 600;
                }
                
                .progress-bar {
                    width: 100%;
                    height: 8px;
                    background: #e5e7eb;
                    border-radius: 4px;
                    overflow: hidden;
                }
                
                .progress-fill {
                    height: 100%;
                    background: #10b981;
                    border-radius: 4px;
                    transition: width 0.3s;
                }
                
                .date-filter {
                    display: flex;
                    gap: 10px;
                    align-items: center;
                }
                
                .date-filter input {
                    padding: 8px 12px;
                    border: 1px solid #e5e7eb;
                    border-radius: 6px;
                }
                
                @media (max-width: 768px) {
                    .charts-grid {
                        grid-template-columns: 1fr;
                    }
                }
            </style>
        </head>
        <body>
            <div class="analytics-container">
                <div class="analytics-header">
                    <h1>📊 Analytics Dashboard</h1>
                    <div class="date-filter">
                        <input type="date" id="start-date" onchange="filterByDate()">
                        <input type="date" id="end-date" onchange="filterByDate()">
                        <button class="btn-back" onclick="window.location.href='dashboard.php'">← Back</button>
                    </div>
                </div>
                
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card-large">
                        <div class="stat-label">Total Requests</div>
                        <div class="stat-value-large"><?php echo number_format($total_requests); ?></div>
                        <div class="stat-trend">All time</div>
                    </div>
                    <div class="stat-card-large">
                        <div class="stat-label">Success Rate</div>
                        <div class="stat-value-large"><?php echo number_format($success_rate, 1); ?>%</div>
                        <div class="stat-trend"><?php echo $successful; ?> successful / <?php echo $failed; ?> failed</div>
                    </div>
                    <div class="stat-card-large">
                        <div class="stat-label">Avg Response Time</div>
                        <div class="stat-value-large"><?php echo number_format($avg_response_time, 2); ?>ms</div>
                        <div class="stat-trend">Average latency</div>
                    </div>
                    <div class="stat-card-large">
                        <div class="stat-label">Active Providers</div>
                        <div class="stat-value-large"><?php echo count(array_filter($this->providers, function($p) { return $p['is_active'] ?? false; })); ?></div>
                        <div class="stat-trend"><?php echo count($this->providers); ?> total providers</div>
                    </div>
                </div>
                
                <!-- Charts Grid -->
                <div class="charts-grid">
                    <div class="chart-card">
                        <h3>📈 Daily Request Volume</h3>
                        <div class="chart-container">
                            <canvas id="dailyChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-card">
                        <h3>⏱️ Response Time Trend</h3>
                        <div class="chart-container">
                            <canvas id="responseTimeChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-card">
                        <h3>🕒 Hourly Distribution</h3>
                        <div class="chart-container">
                            <canvas id="hourlyChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-card">
                        <h3>📊 Status Code Distribution</h3>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Providers Table -->
                <div class="providers-table">
                    <h3>🔌 Provider Performance</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Provider</th>
                                <th>Total Requests</th>
                                <th>Success Rate</th>
                                <th>Avg Response Time</th>
                                <th>Success Trend</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($provider_stats as $provider => $stats): ?>
                                <?php 
                                    $success_rate = $stats['total'] > 0 ? ($stats['success'] / $stats['total']) * 100 : 0;
                                    $avg_time = $stats['total'] > 0 ? $stats['total_time'] / $stats['total'] : 0;
                                ?>
                                <tr>
                                    <td>
                                        <span class="provider-icon"><?php echo $this->getProviderIcon($provider); ?></span>
                                        <?php echo htmlspecialchars($this->getProviderName($provider)); ?>
                                    </td>
                                    <td><?php echo number_format($stats['total']); ?></td>
                                    <td>
                                        <?php echo number_format($success_rate, 1); ?>%
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $success_rate; ?>%"></div>
                                        </div>
                                    </td>
                                    <td><?php echo number_format($avg_time, 2); ?>ms</td>
                                    <td>
                                        <?php if ($success_rate >= 95): ?>
                                            <span style="color: #10b981;">✅ Excellent</span>
                                        <?php elseif ($success_rate >= 80): ?>
                                            <span style="color: #f59e0b;">⚠️ Good</span>
                                        <?php else: ?>
                                            <span style="color: #ef4444;">❌ Poor</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <script>
                // Chart data from PHP
                const dailyStats = <?php echo json_encode(array_values($daily_stats)); ?>;
                const dailyLabels = <?php echo json_encode(array_keys($daily_stats)); ?>;
                const hourlyStats = <?php echo json_encode($hourly_stats); ?>;
                const statusStats = <?php echo json_encode($status_stats); ?>;
                
                // Daily Request Chart
                const dailyCtx = document.getElementById('dailyChart').getContext('2d');
                new Chart(dailyCtx, {
                    type: 'line',
                    data: {
                        labels: dailyLabels,
                        datasets: [{
                            label: 'Total Requests',
                            data: dailyStats.map(s => s.total),
                            borderColor: '#4F46E5',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            tension: 0.4,
                            fill: true
                        }, {
                            label: 'Successful Requests',
                            data: dailyStats.map(s => s.success),
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top' }
                        }
                    }
                });
                
                // Response Time Chart
                const timeCtx = document.getElementById('responseTimeChart').getContext('2d');
                new Chart(timeCtx, {
                    type: 'line',
                    data: {
                        labels: dailyLabels,
                        datasets: [{
                            label: 'Avg Response Time (ms)',
                            data: dailyStats.map(s => s.total_time / (s.total || 1)),
                            borderColor: '#F59E0B',
                            backgroundColor: 'rgba(245, 158, 11, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
                
                // Hourly Distribution Chart
                const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
                new Chart(hourlyCtx, {
                    type: 'bar',
                    data: {
                        labels: Array.from({length: 24}, (_, i) => i + ':00'),
                        datasets: [{
                            label: 'Requests',
                            data: hourlyStats,
                            backgroundColor: '#8B5CF6'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
                
                // Status Code Chart
                const statusCtx = document.getElementById('statusChart').getContext('2d');
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: Object.keys(statusStats),
                        datasets: [{
                            data: Object.values(statusStats),
                            backgroundColor: ['#10B981', '#F59E0B', '#EF4444', '#6B7280']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
                
                // Date filter
                function filterByDate() {
                    const startDate = document.getElementById('start-date').value;
                    const endDate = document.getElementById('end-date').value;
                    
                    if (startDate && endDate) {
                        window.location.href = `analytics.php?start=${startDate}&end=${endDate}`;
                    }
                }
            </script>
        </body>
        </html>
        <?php
    }
    
    private function getProviderName($slug) {
        return $this->providers[$slug]['name'] ?? ucfirst($slug);
    }
    
    private function getProviderIcon($slug) {
        $icons = [
            'openai' => '🤖',
            'anthropic' => '🧠',
            'google-ai' => '🔍',
            'cohere' => '📝',
            'default' => '🔌'
        ];
        return $icons[$slug] ?? $icons['default'];
    }
}

$analytics = new APIMaster_Analytics();
$analytics->render();