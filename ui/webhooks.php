<?php
/**
 * API Master - Webhook Manager
 * 
 * @package APIMaster
 * @subpackage UI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_Webhooks {
    
    private $webhooks = [];
    
    public function __construct() {
        $this->loadWebhooks();
    }
    
    private function loadWebhooks() {
        $webhooks_file = dirname(dirname(__FILE__)) . '/config/webhooks.json';
        if (file_exists($webhooks_file)) {
            $this->webhooks = json_decode(file_get_contents($webhooks_file), true);
        } else {
            $this->webhooks = [];
        }
    }
    
    private function saveWebhooks() {
        $webhooks_file = dirname(dirname(__FILE__)) . '/config/webhooks.json';
        file_put_contents($webhooks_file, json_encode($this->webhooks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    public function render() {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Webhook Manager</title>
            <link rel="stylesheet" href="style.css">
            <style>
                .webhook-container {
                    padding: 20px;
                }
                
                .webhook-form {
                    background: white;
                    border-radius: 12px;
                    padding: 24px;
                    margin-bottom: 24px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .form-row {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 16px;
                    margin-bottom: 16px;
                }
                
                .webhook-list {
                    background: white;
                    border-radius: 12px;
                    padding: 24px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .webhook-item {
                    padding: 16px;
                    border: 1px solid #e5e7eb;
                    border-radius: 10px;
                    margin-bottom: 12px;
                    transition: all 0.3s;
                }
                
                .webhook-item:hover {
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                }
                
                .webhook-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 12px;
                }
                
                .webhook-name {
                    font-weight: 600;
                    font-size: 16px;
                }
                
                .webhook-url {
                    font-family: monospace;
                    font-size: 12px;
                    color: #6b7280;
                    margin-bottom: 8px;
                    word-break: break-all;
                }
                
                .webhook-events {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    margin-top: 8px;
                }
                
                .event-tag {
                    padding: 2px 8px;
                    background: #f3f4f6;
                    border-radius: 12px;
                    font-size: 11px;
                }
                
                .webhook-actions {
                    display: flex;
                    gap: 8px;
                    margin-top: 12px;
                }
                
                .status-badge {
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 500;
                }
                
                .status-active {
                    background: #d1fae5;
                    color: #065f46;
                }
                
                .status-inactive {
                    background: #fee2e2;
                    color: #991b1b;
                }
                
                .btn-test {
                    background: #3b82f6;
                    color: white;
                }
                
                .test-result {
                    margin-top: 8px;
                    padding: 8px;
                    background: #f9fafb;
                    border-radius: 6px;
                    font-size: 12px;
                    display: none;
                }
                
                .test-result.success {
                    background: #d1fae5;
                    color: #065f46;
                }
                
                .test-result.error {
                    background: #fee2e2;
                    color: #991b1b;
                }
            </style>
        </head>
        <body>
            <div class="webhook-container">
                <div class="test-header">
                    <h1>🔗 Webhook Manager</h1>
                    <p>Configure webhooks for real-time event notifications</p>
                </div>
                
                <!-- Add Webhook Form -->
                <div class="webhook-form">
                    <h3>➕ Add New Webhook</h3>
                    <form id="webhook-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Webhook Name</label>
                                <input type="text" id="webhook-name" placeholder="e.g., Slack Notifications" required>
                            </div>
                            <div class="form-group">
                                <label>Endpoint URL</label>
                                <input type="url" id="webhook-url" placeholder="https://your-server.com/webhook" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Secret (for verification)</label>
                                <input type="text" id="webhook-secret" placeholder="Optional signing secret">
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select id="webhook-status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Events to Trigger</label>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; margin-top: 8px;">
                                <label><input type="checkbox" value="api_call"> API Call</label>
                                <label><input type="checkbox" value="api_error"> API Error</label>
                                <label><input type="checkbox" value="rate_limit"> Rate Limit</label>
                                <label><input type="checkbox" value="model_trained"> Model Trained</label>
                                <label><input type="checkbox" value="vector_added"> Vector Added</label>
                                <label><input type="checkbox" value="backup_created"> Backup Created</label>
                                <label><input type="checkbox" value="system_health"> System Health</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-train">➕ Create Webhook</button>
                    </form>
                </div>
                
                <!-- Webhooks List -->
                <div class="webhook-list">
                    <h3>📡 Active Webhooks</h3>
                    <div id="webhooks-list">
                        <?php if (empty($this->webhooks)): ?>
                            <p style="text-align: center; color: #6b7280; padding: 40px;">No webhooks configured. Create your first webhook above.</p>
                        <?php else: ?>
                            <?php foreach ($this->webhooks as $index => $webhook): ?>
                                <div class="webhook-item" data-id="<?php echo $index; ?>">
                                    <div class="webhook-header">
                                        <span class="webhook-name"><?php echo htmlspecialchars($webhook['name']); ?></span>
                                        <span class="status-badge status-<?php echo $webhook['status']; ?>">
                                            <?php echo ucfirst($webhook['status']); ?>
                                        </span>
                                    </div>
                                    <div class="webhook-url"><?php echo htmlspecialchars($webhook['url']); ?></div>
                                    <div class="webhook-events">
                                        <?php foreach ($webhook['events'] as $event): ?>
                                            <span class="event-tag"><?php echo htmlspecialchars($event); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="webhook-actions">
                                        <button class="btn-add-param" onclick="testWebhook(<?php echo $index; ?>)">🧪 Test</button>
                                        <button class="btn-add-param" onclick="editWebhook(<?php echo $index; ?>)">✏️ Edit</button>
                                        <button class="btn-add-param" onclick="deleteWebhook(<?php echo $index; ?>)" style="background: #ef4444; color: white;">🗑️ Delete</button>
                                    </div>
                                    <div id="test-result-<?php echo $index; ?>" class="test-result"></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Webhook Logs -->
                <div class="webhook-list" style="margin-top: 24px;">
                    <h3>📋 Recent Deliveries</h3>
                    <div id="delivery-logs">
                        <p style="text-align: center; color: #6b7280; padding: 20px;">Loading delivery logs...</p>
                    </div>
                </div>
            </div>
            
            <script>
                // Handle form submission
                document.getElementById('webhook-form').addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    const name = document.getElementById('webhook-name').value;
                    const url = document.getElementById('webhook-url').value;
                    const secret = document.getElementById('webhook-secret').value;
                    const status = document.getElementById('webhook-status').value;
                    
                    const events = Array.from(document.querySelectorAll('input[type="checkbox"]:checked'))
                        .map(cb => cb.value);
                    
                    const webhook = { name, url, secret, status, events };
                    
                    const btn = e.target.querySelector('button[type="submit"]');
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<span class="loading-spinner"></span> Creating...';
                    btn.disabled = true;
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=create_webhook', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(webhook)
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showNotification('Webhook created successfully!', 'success');
                            location.reload();
                        } else {
                            showNotification('Failed: ' + result.message, 'error');
                        }
                    } catch(error) {
                        showNotification('Error: ' + error.message, 'error');
                    } finally {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                });
                
                async function testWebhook(id) {
                    const resultDiv = document.getElementById(`test-result-${id}`);
                    resultDiv.style.display = 'block';
                    resultDiv.innerHTML = '<span class="loading-spinner"></span> Testing...';
                    resultDiv.className = 'test-result';
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=test_webhook', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: id })
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            resultDiv.innerHTML = '✅ Test successful! Webhook endpoint is reachable.';
                            resultDiv.className = 'test-result success';
                        } else {
                            resultDiv.innerHTML = '❌ Test failed: ' + result.message;
                            resultDiv.className = 'test-result error';
                        }
                    } catch(error) {
                        resultDiv.innerHTML = '❌ Error: ' + error.message;
                        resultDiv.className = 'test-result error';
                    }
                    
                    setTimeout(() => {
                        resultDiv.style.display = 'none';
                    }, 5000);
                }
                
                async function deleteWebhook(id) {
                    if (!confirm('Are you sure you want to delete this webhook?')) return;
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=delete_webhook', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: id })
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showNotification('Webhook deleted', 'success');
                            location.reload();
                        } else {
                            showNotification('Delete failed: ' + result.message, 'error');
                        }
                    } catch(error) {
                        showNotification('Error: ' + error.message, 'error');
                    }
                }
                
                function editWebhook(id) {
                    // Load webhook data into form
                    const webhookItem = document.querySelector(`.webhook-item[data-id="${id}"]`);
                    const name = webhookItem.querySelector('.webhook-name').textContent;
                    const url = webhookItem.querySelector('.webhook-url').textContent;
                    
                    document.getElementById('webhook-name').value = name;
                    document.getElementById('webhook-url').value = url;
                    
                    // Scroll to form
                    document.querySelector('.webhook-form').scrollIntoView({ behavior: 'smooth' });
                    showNotification('Load webhook data into form for editing', 'info');
                }
                
                async function loadDeliveryLogs() {
                    try {
                        const response = await fetch('ajax-handlers.php?action=get_webhook_logs');
                        const result = await response.json();
                        const container = document.getElementById('delivery-logs');
                        
                        if (result.success && result.data.length > 0) {
                            container.innerHTML = `
                                <table class="results-table">
                                    <thead><tr><th>Time</th><th>Webhook</th><th>Event</th><th>Status</th><th>Duration</th></tr></thead>
                                    <tbody>
                                        ${result.data.map(log => `
                                            <tr>
                                                <td>${log.timestamp}</td>
                                                <td>${log.webhook_name}</td>
                                                <td>${log.event}</td>
                                                <td><span class="status-badge ${log.success ? 'status-active' : 'status-inactive'}">${log.success ? 'Success' : 'Failed'}</span></td>
                                                <td>${log.duration}ms</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            `;
                        } else {
                            container.innerHTML = '<p style="text-align: center; color: #6b7280; padding: 20px;">No delivery logs yet.</p>';
                        }
                    } catch(error) {
                        console.error('Failed to load logs:', error);
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
                
                loadDeliveryLogs();
            </script>
        </body>
        </html>
        <?php
    }
}

$webhooks = new APIMaster_Webhooks();
$webhooks->render();