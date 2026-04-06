<?php
/**
 * API Master - Queue Manager
 * 
 * @package APIMaster
 * @subpackage UI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_Queue {
    
    private $queue = [];
    
    public function __construct() {
        $this->loadQueue();
    }
    
    private function loadQueue() {
        $queue_file = dirname(dirname(__FILE__)) . '/data/queue-jobs.json';
        if (file_exists($queue_file)) {
            $this->queue = json_decode(file_get_contents($queue_file), true);
        } else {
            $this->queue = [];
        }
    }
    
    private function saveQueue() {
        $queue_file = dirname(dirname(__FILE__)) . '/data/queue-jobs.json';
        file_put_contents($queue_file, json_encode($this->queue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    public function render() {
        $pending = count(array_filter($this->queue, fn($j) => $j['status'] === 'pending'));
        $processing = count(array_filter($this->queue, fn($j) => $j['status'] === 'processing'));
        $completed = count(array_filter($this->queue, fn($j) => $j['status'] === 'completed'));
        $failed = count(array_filter($this->queue, fn($j) => $j['status'] === 'failed'));
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Queue Manager</title>
            <link rel="stylesheet" href="style.css">
            <style>
                .queue-container {
                    padding: 20px;
                }
                
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                    gap: 20px;
                    margin-bottom: 30px;
                }
                
                .queue-stats {
                    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
                    color: white;
                }
                
                .queue-controls {
                    background: white;
                    border-radius: 12px;
                    padding: 20px;
                    margin-bottom: 24px;
                    display: flex;
                    gap: 12px;
                    flex-wrap: wrap;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .job-list {
                    background: white;
                    border-radius: 12px;
                    padding: 24px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .job-item {
                    padding: 16px;
                    border: 1px solid #e5e7eb;
                    border-radius: 10px;
                    margin-bottom: 12px;
                    transition: all 0.3s;
                }
                
                .job-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 12px;
                }
                
                .job-type {
                    font-weight: 600;
                    font-family: monospace;
                }
                
                .job-status {
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 500;
                }
                
                .status-pending { background: #fef3c7; color: #92400e; }
                .status-processing { background: #dbeafe; color: #1e40af; }
                .status-completed { background: #d1fae5; color: #065f46; }
                .status-failed { background: #fee2e2; color: #991b1b; }
                
                .job-details {
                    font-size: 12px;
                    color: #6b7280;
                    margin-top: 8px;
                }
                
                .job-progress {
                    margin-top: 12px;
                }
                
                .progress-bar {
                    width: 100%;
                    height: 4px;
                    background: #e5e7eb;
                    border-radius: 2px;
                    overflow: hidden;
                }
                
                .progress-fill {
                    height: 100%;
                    background: #f59e0b;
                    width: 0%;
                    transition: width 0.3s;
                }
                
                .add-job-form {
                    background: #f9fafb;
                    border-radius: 10px;
                    padding: 16px;
                    margin-top: 16px;
                }
                
                .btn-retry {
                    background: #f59e0b;
                    color: white;
                }
            </style>
        </head>
        <body>
            <div class="queue-container">
                <div class="test-header">
                    <h1>📬 Queue Manager</h1>
                    <p>Manage asynchronous job processing and task queues</p>
                </div>
                
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card queue-stats">
                        <div class="stat-info">
                            <h3>Pending</h3>
                            <p class="stat-value"><?php echo $pending; ?></p>
                        </div>
                    </div>
                    <div class="stat-card queue-stats">
                        <div class="stat-info">
                            <h3>Processing</h3>
                            <p class="stat-value"><?php echo $processing; ?></p>
                        </div>
                    </div>
                    <div class="stat-card queue-stats">
                        <div class="stat-info">
                            <h3>Completed</h3>
                            <p class="stat-value"><?php echo number_format($completed); ?></p>
                        </div>
                    </div>
                    <div class="stat-card queue-stats">
                        <div class="stat-info">
                            <h3>Failed</h3>
                            <p class="stat-value"><?php echo $failed; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Controls -->
                <div class="queue-controls">
                    <button class="btn-train" onclick="processQueue()">▶️ Process Queue</button>
                    <button class="btn-add-param" onclick="retryFailed()">🔄 Retry Failed</button>
                    <button class="btn-add-param" onclick="clearCompleted()">🗑️ Clear Completed</button>
                    <button class="btn-add-param" onclick="showAddJobForm()">➕ Add Test Job</button>
                </div>
                
                <!-- Job List -->
                <div class="job-list">
                    <h3>📋 Queue Jobs</h3>
                    <div id="job-list-container">
                        <?php if (empty($this->queue)): ?>
                            <p style="text-align: center; color: #6b7280; padding: 40px;">No jobs in queue.</p>
                        <?php else: ?>
                            <?php foreach (array_slice($this->queue, 0, 50) as $job): ?>
                                <div class="job-item" data-id="<?php echo $job['id']; ?>">
                                    <div class="job-header">
                                        <span class="job-type"><?php echo htmlspecialchars($job['type']); ?></span>
                                        <span class="job-status status-<?php echo $job['status']; ?>"><?php echo ucfirst($job['status']); ?></span>
                                    </div>
                                    <div class="job-details">
                                        <div>Created: <?php echo $job['created_at']; ?></div>
                                        <?php if ($job['status'] === 'processing'): ?>
                                            <div>Started: <?php echo $job['started_at'] ?? 'N/A'; ?></div>
                                        <?php endif; ?>
                                        <?php if ($job['status'] === 'completed'): ?>
                                            <div>Completed: <?php echo $job['completed_at'] ?? 'N/A'; ?></div>
                                        <?php endif; ?>
                                        <?php if ($job['status'] === 'failed' && isset($job['error'])): ?>
                                            <div style="color: #ef4444;">Error: <?php echo htmlspecialchars($job['error']); ?></div>
                                            <div>Retries: <?php echo $job['retries']; ?>/<?php echo $job['max_retries']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($job['status'] === 'processing'): ?>
                                        <div class="job-progress">
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo $job['progress'] ?? 0; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($job['status'] === 'failed'): ?>
                                        <div style="margin-top: 12px;">
                                            <button class="btn-add-param btn-retry" onclick="retryJob('<?php echo $job['id']; ?>')">🔄 Retry</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Add Job Form (hidden by default) -->
                    <div id="add-job-form" class="add-job-form" style="display: none;">
                        <h4>Add New Job</h4>
                        <div class="form-group">
                            <label>Job Type</label>
                            <select id="job-type">
                                <option value="api_call">API Call</option>
                                <option value="email">Send Email</option>
                                <option value="webhook">Webhook Delivery</option>
                                <option value="cleanup">Cleanup Task</option>
                                <option value="backup">Backup Task</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Job Data (JSON)</label>
                            <textarea id="job-data" rows="4" placeholder='{"key": "value"}' style="width: 100%;"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Priority</label>
                            <select id="job-priority">
                                <option value="low">Low</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button class="btn-train" onclick="addJob()">Add Job</button>
                            <button class="btn-add-param" onclick="hideAddJobForm()">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
                let refreshInterval = null;
                
                function startAutoRefresh() {
                    if (refreshInterval) clearInterval(refreshInterval);
                    refreshInterval = setInterval(() => {
                        location.reload();
                    }, 5000);
                }
                
                function stopAutoRefresh() {
                    if (refreshInterval) {
                        clearInterval(refreshInterval);
                        refreshInterval = null;
                    }
                }
                
                async function processQueue() {
                    const btn = event.target;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<span class="loading-spinner"></span> Processing...';
                    btn.disabled = true;
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=process_queue', {
                            method: 'POST'
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showNotification(`Processed ${result.processed} jobs`, 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showNotification('Processing failed: ' + result.message, 'error');
                        }
                    } catch(error) {
                        showNotification('Error: ' + error.message, 'error');
                    } finally {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                }
                
                async function retryFailed() {
                    if (!confirm('Retry all failed jobs?')) return;
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=retry_failed', {
                            method: 'POST'
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showNotification(`Retried ${result.retried} jobs`, 'success');
                            location.reload();
                        } else {
                            showNotification('Failed: ' + result.message, 'error');
                        }
                    } catch(error) {
                        showNotification('Error: ' + error.message, 'error');
                    }
                }
                
                async function retryJob(jobId) {
                    try {
                        const response = await fetch('ajax-handlers.php?action=retry_job', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: jobId })
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showNotification('Job retried', 'success');
                            location.reload();
                        } else {
                            showNotification('Failed: ' + result.message, 'error');
                        }
                    } catch(error) {
                        showNotification('Error: ' + error.message, 'error');
                    }
                }
                
                async function clearCompleted() {
                    if (!confirm('Clear all completed jobs?')) return;
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=clear_completed', {
                            method: 'POST'
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showNotification(`Cleared ${result.cleared} completed jobs`, 'success');
                            location.reload();
                        } else {
                            showNotification('Failed: ' + result.message, 'error');
                        }
                    } catch(error) {
                        showNotification('Error: ' + error.message, 'error');
                    }
                }
                
                function showAddJobForm() {
                    document.getElementById('add-job-form').style.display = 'block';
                }
                
                function hideAddJobForm() {
                    document.getElementById('add-job-form').style.display = 'none';
                }
                
                async function addJob() {
                    const type = document.getElementById('job-type').value;
                    let data = document.getElementById('job-data').value;
                    const priority = document.getElementById('job-priority').value;
                    
                    try {
                        if (data) {
                            JSON.parse(data); // Validate JSON
                        } else {
                            data = '{}';
                        }
                    } catch(e) {
                        showNotification('Invalid JSON data', 'error');
                        return;
                    }
                    
                    const job = { type, data: JSON.parse(data), priority };
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=add_job', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(job)
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showNotification('Job added to queue', 'success');
                            hideAddJobForm();
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showNotification('Failed: ' + result.message, 'error');
                        }
                    } catch(error) {
                        showNotification('Error: ' + error.message, 'error');
                    }
                }
                
                function showNotification(message, type) {
                    const notification = document.createElement('div');
                    notification.textContent = message;
                    notification.style.cssText = `
                        position: fixed;
                        bottom: 20px;
                        right: 20px;
                        padding: 12px 20px;
                        background: ${type === 'success' ? '#10b981' : (type === 'error' ? '#ef4444' : '#f59e0b')};
                        color: white;
                        border-radius: 8px;
                        z-index: 1000;
                        animation: fadeIn 0.3s ease;
                    `;
                    document.body.appendChild(notification);
                    setTimeout(() => notification.remove(), 3000);
                }
                
                // Auto-refresh if there are pending/processing jobs
                <?php if ($pending > 0 || $processing > 0): ?>
                startAutoRefresh();
                <?php endif; ?>
            </script>
        </body>
        </html>
        <?php
    }
}

$queue = new APIMaster_Queue();
$queue->render();