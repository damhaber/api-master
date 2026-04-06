<?php
/**
 * API Master - Backup & Restore Manager
 * 
 * @package APIMaster
 * @subpackage UI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_Backup {
    
    private $backups = [];
    private $backup_dir;
    
    public function __construct() {
        $this->backup_dir = dirname(dirname(__FILE__)) . '/backups/';
        $this->ensureBackupDir();
        $this->loadBackups();
    }
    
    private function ensureBackupDir() {
        if (!is_dir($this->backup_dir)) {
            mkdir($this->backup_dir, 0755, true);
        }
    }
    
    private function loadBackups() {
        $files = glob($this->backup_dir . '*.zip');
        $this->backups = [];
        
        foreach ($files as $file) {
            $this->backups[] = [
                'name' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'created' => filemtime($file),
                'date' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        
        // Sort by date (newest first)
        usort($this->backups, fn($a, $b) => $b['created'] - $a['created']);
    }
    
    public function render() {
        $total_size = array_sum(array_column($this->backups, 'size'));
        $total_backups = count($this->backups);
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Backup & Restore Manager</title>
            <link rel="stylesheet" href="style.css">
            <style>
                .backup-container {
                    padding: 20px;
                }
                
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 20px;
                    margin-bottom: 30px;
                }
                
                .backup-stats {
                    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                    color: white;
                }
                
                .action-cards {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 24px;
                    margin-bottom: 30px;
                }
                
                .action-card {
                    background: white;
                    border-radius: 12px;
                    padding: 24px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .action-card h3 {
                    margin-bottom: 16px;
                }
                
                .backup-list {
                    background: white;
                    border-radius: 12px;
                    padding: 24px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .backup-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 16px;
                    border-bottom: 1px solid #e5e7eb;
                }
                
                .backup-info {
                    flex: 1;
                }
                
                .backup-name {
                    font-family: monospace;
                    font-weight: 500;
                    margin-bottom: 4px;
                }
                
                .backup-meta {
                    font-size: 12px;
                    color: #6b7280;
                }
                
                .backup-actions {
                    display: flex;
                    gap: 8px;
                }
                
                .btn-restore {
                    background: #10b981;
                    color: white;
                }
                
                .btn-download {
                    background: #3b82f6;
                    color: white;
                }
                
                .btn-delete {
                    background: #ef4444;
                    color: white;
                }
                
                .schedule-settings {
                    margin-top: 16px;
                    padding-top: 16px;
                    border-top: 1px solid #e5e7eb;
                }
                
                .danger-zone {
                    margin-top: 24px;
                    padding: 20px;
                    border: 2px solid #ef4444;
                    border-radius: 12px;
                    background: #fef2f2;
                }
                
                .danger-zone h4 {
                    color: #ef4444;
                    margin-bottom: 12px;
                }
                
                .progress-bar {
                    width: 100%;
                    height: 8px;
                    background: #e5e7eb;
                    border-radius: 4px;
                    overflow: hidden;
                    margin-top: 16px;
                    display: none;
                }
                
                .progress-fill {
                    height: 100%;
                    background: #10b981;
                    width: 0%;
                    transition: width 0.3s;
                }
            </style>
        </head>
        <body>
            <div class="backup-container">
                <div class="test-header">
                    <h1>💾 Backup & Restore Manager</h1>
                    <p>Create, manage, and restore backups of all system data</p>
                </div>
                
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card backup-stats">
                        <div class="stat-info">
                            <h3>Total Backups</h3>
                            <p class="stat-value"><?php echo $total_backups; ?></p>
                        </div>
                    </div>
                    <div class="stat-card backup-stats">
                        <div class="stat-info">
                            <h3>Total Size</h3>
                            <p class="stat-value"><?php echo $this->formatSize($total_size); ?></p>
                        </div>
                    </div>
                    <div class="stat-card backup-stats">
                        <div class="stat-info">
                            <h3>Latest Backup</h3>
                            <p class="stat-value" style="font-size: 16px;"><?php echo $this->backups[0]['date'] ?? 'Never'; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Action Cards -->
                <div class="action-cards">
                    <div class="action-card">
                        <h3>📦 Create Backup</h3>
                        <p style="color: #6b7280; margin-bottom: 16px;">Create a full backup of all configuration, API keys, logs, and vector data.</p>
                        <div class="form-group">
                            <label>Backup Name (optional)</label>
                            <input type="text" id="backup-name" placeholder="auto-<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Include</label>
                            <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                                <label><input type="checkbox" value="config" checked> Configuration</label>
                                <label><input type="checkbox" value="data" checked> Data</label>
                                <label><input type="checkbox" value="logs" checked> Logs</label>
                                <label><input type="checkbox" value="vectors" checked> Vectors</label>
                                <label><input type="checkbox" value="learning" checked> Learning Data</label>
                            </div>
                        </div>
                        <button class="btn-train" onclick="createBackup()" style="width: 100%;">🚀 Create Backup Now</button>
                        <div id="backup-progress" class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                    </div>
                    
                    <div class="action-card">
                        <h3>⏰ Scheduled Backups</h3>
                        <p style="color: #6b7280; margin-bottom: 16px;">Configure automatic backups on a schedule.</p>
                        <div class="form-group">
                            <label>Schedule</label>
                            <select id="schedule">
                                <option value="disabled">Disabled</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Retention (days)</label>
                            <input type="number" id="retention" value="30" min="1" max="365">
                        </div>
                        <button class="btn-add-param" onclick="saveSchedule()" style="width: 100%;">💾 Save Schedule</button>
                    </div>
                </div>
                
                <!-- Backup List -->
                <div class="backup-list">
                    <h3>📋 Available Backups</h3>
                    <?php if (empty($this->backups)): ?>
                        <p style="text-align: center; color: #6b7280; padding: 40px;">No backups found. Create your first backup above.</p>
                    <?php else: ?>
                        <?php foreach ($this->backups as $backup): ?>
                            <div class="backup-item">
                                <div class="backup-info">
                                    <div class="backup-name"><?php echo htmlspecialchars($backup['name']); ?></div>
                                    <div class="backup-meta">
                                        Size: <?php echo $this->formatSize($backup['size']); ?> | 
                                        Created: <?php echo $backup['date']; ?>
                                    </div>
                                </div>
                                <div class="backup-actions">
                                    <button class="btn-add-param btn-restore" onclick="restoreBackup('<?php echo $backup['name']; ?>')">🔄 Restore</button>
                                    <button class="btn-add-param btn-download" onclick="downloadBackup('<?php echo $backup['name']; ?>')">📥 Download</button>
                                    <button class="btn-add-param btn-delete" onclick="deleteBackup('<?php echo $backup['name']; ?>')">🗑️ Delete</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Danger Zone -->
                <div class="danger-zone">
                    <h4>⚠️ Danger Zone</h4>
                    <p style="margin-bottom: 16px;">These actions cannot be undone. Proceed with caution.</p>
                    <button class="btn-danger" onclick="clearAllBackups()">🗑️ Delete All Backups</button>
                    <button class="btn-danger" onclick="factoryReset()" style="margin-left: 10px;">🔄 Factory Reset (Delete All Data)</button>
                </div>
            </div>
            
            <script>
                async function createBackup() {
                    const name = document.getElementById('backup-name').value;
                    const include = Array.from(document.querySelectorAll('#backup-name').closest('.action-card').querySelectorAll('input[type="checkbox"]:checked'))
                        .map(cb => cb.value);
                    
                    const btn = event.target;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<span class="loading-spinner"></span> Creating...';
                    btn.disabled = true;
                    
                    const progressBar = document.getElementById('backup-progress');
                    const progressFill = progressBar.querySelector('.progress-fill');
                    progressBar.style.display = 'block';
                    
                    let progress = 0;
                    const interval = setInterval(() => {
                        progress += 10;
                        progressFill.style.width = progress + '%';
                        if (progress >= 100) clearInterval(interval);
                    }, 500);
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=create_backup', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ name: name, include: include })
                        });
                        const result = await response.json();
                        
                        clearInterval(interval);
                        progressFill.style.width = '100%';
                        
                        if (result.success) {
                            showNotification('Backup created successfully!', 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showNotification('Backup failed: ' + result.message, 'error');
                            progressBar.style.display = 'none';
                        }
                    } catch(error) {
                        clearInterval(interval);
                        showNotification('Error: ' + error.message, 'error');
                        progressBar.style.display = 'none';
                    } finally {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                }
                
                async function restoreBackup(backupName) {
                    if (!confirm(`Restore from "${backupName}"? This will overwrite current data.`)) return;
                    if (!confirm('LAST WARNING: This action cannot be undone. Continue?')) return;
                    
                    const btn = event.target;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<span class="loading-spinner"></span> Restoring...';
                    btn.disabled = true;
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=restore_backup', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ backup: backupName })
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showNotification('Backup restored! Page will reload.', 'success');
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            showNotification('Restore failed: ' + result.message, 'error');
                        }
                    } catch(error) {
                        showNotification('Error: ' + error.message, 'error');
                    } finally {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                }
                
                function downloadBackup(backupName) {
                    window.open(`ajax-handlers.php?action=download_backup&file=${encodeURIComponent(backupName)}`, '_blank');
                }
                
                async function deleteBackup(backupName) {
                    if (!confirm(`Delete backup "${backupName}"?`)) return;
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=delete_backup', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ backup: backupName })
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showNotification('Backup deleted', 'success');
                            location.reload();
                        } else {
                            showNotification('Delete failed: ' + result.message, 'error');
                        }
                    } catch(error) {
                        showNotification('Error: ' + error.message, 'error');
                    }
                }
                
                async function clearAllBackups() {
                    if (!confirm('Delete ALL backups? This cannot be undone.')) return;
                    if (!confirm('ARE YOU ABSOLUTELY SURE?')) return;
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=clear_backups', {
                            method: 'POST'
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showNotification('All backups deleted', 'success');
                            location.reload();
                        } else {
                            showNotification('Failed: ' + result.message, 'error');
                        }
                    } catch(error) {
                        showNotification('Error: ' + error.message, 'error');
                    }
                }
                
                async function factoryReset() {
                    if (!confirm('⚠️ FACTORY RESET: This will delete ALL data! This cannot be undone.')) return;
                    if (!confirm('LAST WARNING: Are you ABSOLUTELY sure?')) return;
                    if (!confirm('Type "RESET" to confirm:')) return;
                    
                    const confirmText = prompt('Type "RESET" to confirm factory reset:');
                    if (confirmText !== 'RESET') {
                        showNotification('Factory reset cancelled', 'info');
                        return;
                    }
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=factory_reset', {
                            method: 'POST'
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showNotification('Factory reset completed! Page will reload.', 'success');
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            showNotification('Reset failed: ' + result.message, 'error');
                        }
                    } catch(error) {
                        showNotification('Error: ' + error.message, 'error');
                    }
                }
                
                async function saveSchedule() {
                    const schedule = document.getElementById('schedule').value;
                    const retention = document.getElementById('retention').value;
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=save_backup_schedule', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ schedule: schedule, retention: retention })
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showNotification('Schedule saved!', 'success');
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
            </script>
        </body>
        </html>
        <?php
    }
    
    private function formatSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

$backup = new APIMaster_Backup();
$backup->render();