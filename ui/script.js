/**
 * API Master Dashboard JavaScript
 * 
 * @package APIMaster
 * @subpackage UI
 * @since 1.0.0
 * 
 * IMPORTANT: Standalone JavaScript - NO WordPress dependencies!
 */

// =====================================================
// Global Variables
// =====================================================
const API_BASE_URL = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '');
let refreshInterval = null;
let charts = {};

// =====================================================
// DOM Ready
// =====================================================
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
    setupEventListeners();
    startAutoRefresh();
});

// =====================================================
// Initialization
// =====================================================
function initializeDashboard() {
    loadRecentActivity();
    initializeCharts();
    checkSystemHealth();
    loadNotifications();
}

function setupEventListeners() {
    // Global keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+R or Cmd+R refresh
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            refreshDashboard();
        }
        // Esc close modal
        if (e.key === 'Escape') {
            closeModal();
        }
    });
    
    // Window focus handler
    window.addEventListener('focus', function() {
        loadRecentActivity();
        checkSystemHealth();
    });
}

// =====================================================
// Dashboard Functions
// =====================================================
function refreshDashboard() {
    showLoading();
    Promise.all([
        loadStats(),
        loadRecentActivity(),
        updateCharts()
    ]).then(() => {
        hideLoading();
        showNotification('Dashboard refreshed successfully', 'success');
    }).catch(error => {
        hideLoading();
        showNotification('Failed to refresh dashboard', 'error');
        console.error('Refresh error:', error);
    });
}

function showLoading() {
    const dashboard = document.querySelector('.apimaster-dashboard');
    if (dashboard) {
        dashboard.classList.add('loading');
    }
}

function hideLoading() {
    const dashboard = document.querySelector('.apimaster-dashboard');
    if (dashboard) {
        dashboard.classList.remove('loading');
    }
}

// =====================================================
// API Calls
// =====================================================
async function apiCall(endpoint, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    if (data && (method === 'POST' || method === 'PUT')) {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(`${API_BASE_URL}/ajax-handlers.php?action=${endpoint}`, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'API call failed');
        }
        
        return result;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// =====================================================
// Data Loading Functions
// =====================================================
async function loadStats() {
    try {
        const stats = await apiCall('get_stats');
        updateStatsUI(stats);
    } catch (error) {
        console.error('Failed to load stats:', error);
    }
}

async function loadRecentActivity() {
    try {
        const activities = await apiCall('recent_activity');
        updateActivityTable(activities);
    } catch (error) {
        console.error('Failed to load activity:', error);
    }
}

async function checkSystemHealth() {
    try {
        const health = await apiCall('health_check');
        updateHealthStatus(health);
    } catch (error) {
        console.error('Health check failed:', error);
    }
}

async function loadNotifications() {
    try {
        const notifications = await apiCall('get_notifications');
        displayNotifications(notifications);
    } catch (error) {
        console.error('Failed to load notifications:', error);
    }
}

// =====================================================
// UI Update Functions
// =====================================================
function updateStatsUI(stats) {
    const statCards = document.querySelectorAll('.stat-value');
    if (statCards.length >= 8) {
        statCards[0].textContent = formatNumber(stats.total_requests);
        statCards[1].textContent = stats.success_rate + '%';
        statCards[2].textContent = stats.avg_response_time + 'ms';
        statCards[3].textContent = stats.cache_hit_rate + '%';
        statCards[4].textContent = formatNumber(stats.active_api_keys);
        statCards[5].textContent = formatNumber(stats.total_vectors);
        statCards[6].textContent = stats.learning_accuracy + '%';
    }
}

function updateActivityTable(activities) {
    const tbody = document.getElementById('recentActivity');
    if (!tbody) return;
    
    if (activities.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">No recent activity</td></tr>';
        return;
    }
    
    tbody.innerHTML = activities.map(activity => `
        <tr onclick="viewActivityDetails('${activity.id}')">
            <td>${formatTime(activity.created_at)}</td>
            <td><code>${activity.endpoint}</code></td>
            <td>${activity.provider || 'N/A'}</td>
            <td><span class="status-${getStatusClass(activity.status)}">${activity.status}</span></td>
            <td>${activity.response_time}ms</td>
        </tr>
    `).join('');
}

function updateHealthStatus(health) {
    const healthCard = document.querySelector('.stat-card:last-child .stat-value');
    if (healthCard) {
        healthCard.textContent = health.health_score + '%';
        const trendSpan = healthCard.nextElementSibling;
        if (trendSpan) {
            trendSpan.textContent = health.status;
            trendSpan.className = `stat-trend ${health.status === 'healthy' ? 'positive' : 'negative'}`;
        }
    }
}

// =====================================================
// Chart Functions
// =====================================================
function initializeCharts() {
    // Requests Chart
    const requestsCtx = document.getElementById('requestsChart')?.getContext('2d');
    if (requestsCtx) {
        charts.requests = new Chart(requestsCtx, {
            type: 'line',
            data: {
                labels: getLast7Days(),
                datasets: [{
                    label: 'Total Requests',
                    data: [120, 135, 148, 170, 190, 210, 245],
                    borderColor: '#4F46E5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: getChartOptions('Request Volume')
        });
    }
    
    // Response Time Chart
    const timeCtx = document.getElementById('responseTimeChart')?.getContext('2d');
    if (timeCtx) {
        charts.responseTime = new Chart(timeCtx, {
            type: 'line',
            data: {
                labels: getLast7Days(),
                datasets: [{
                    label: 'Response Time (ms)',
                    data: [145, 138, 142, 135, 128, 125, 118],
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: getChartOptions('Response Time')
        });
    }
    
    // Provider Chart
    const providerCtx = document.getElementById('providerChart')?.getContext('2d');
    if (providerCtx) {
        charts.provider = new Chart(providerCtx, {
            type: 'doughnut',
            data: {
                labels: ['OpenAI', 'Anthropic', 'Google AI', 'Other'],
                datasets: [{
                    data: [65, 20, 10, 5],
                    backgroundColor: ['#4F46E5', '#10B981', '#F59E0B', '#EF4444']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Cache Chart
    const cacheCtx = document.getElementById('cacheChart')?.getContext('2d');
    if (cacheCtx) {
        charts.cache = new Chart(cacheCtx, {
            type: 'bar',
            data: {
                labels: ['Hits', 'Misses'],
                datasets: [{
                    label: 'Cache Performance',
                    data: [75, 25],
                    backgroundColor: ['#10B981', '#EF4444']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

async function updateCharts() {
    try {
        const chartData = await apiCall('get_chart_data');
        
        if (charts.requests && chartData.requests) {
            charts.requests.data.datasets[0].data = chartData.requests;
            charts.requests.update();
        }
        
        if (charts.responseTime && chartData.response_time) {
            charts.responseTime.data.datasets[0].data = chartData.response_time;
            charts.responseTime.update();
        }
    } catch (error) {
        console.error('Failed to update charts:', error);
    }
}

function getChartOptions(title) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    drawBorder: false
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    };
}

// =====================================================
// Action Functions
// =====================================================
async function testAPI() {
    try {
        showModal('Testing API Connection...', 'loading');
        const result = await apiCall('test_api', 'POST');
        closeModal();
        showNotification(result.message, result.success ? 'success' : 'error');
    } catch (error) {
        closeModal();
        showNotification('API test failed: ' + error.message, 'error');
    }
}

async function clearCache() {
    if (!confirm('Are you sure you want to clear all cache? This action cannot be undone.')) {
        return;
    }
    
    try {
        showModal('Clearing cache...', 'loading');
        const result = await apiCall('clear_cache', 'POST');
        closeModal();
        showNotification(result.message, 'success');
        refreshDashboard();
    } catch (error) {
        closeModal();
        showNotification('Failed to clear cache: ' + error.message, 'error');
    }
}

async function trainModel() {
    try {
        showModal('Starting model training...', 'loading');
        const result = await apiCall('train_model', 'POST');
        closeModal();
        showNotification(result.message, 'success');
        
        // Show training progress modal
        showTrainingProgress();
    } catch (error) {
        closeModal();
        showNotification('Training failed: ' + error.message, 'error');
    }
}

function exportData() {
    window.open(`${API_BASE_URL}/ajax-handlers.php?action=export_data`, '_blank');
}

async function runHealthCheck() {
    try {
        const health = await apiCall('health_check');
        showHealthReport(health);
    } catch (error) {
        showNotification('Health check failed: ' + error.message, 'error');
    }
}

async function optimizeIndex() {
    if (!confirm('Optimizing vector index may take some time. Continue?')) {
        return;
    }
    
    try {
        showModal('Optimizing vector index...', 'loading');
        const result = await apiCall('optimize_index', 'POST');
        closeModal();
        showNotification(result.message, 'success');
        refreshDashboard();
    } catch (error) {
        closeModal();
        showNotification('Optimization failed: ' + error.message, 'error');
    }
}

// =====================================================
// Modal Functions
// =====================================================
let currentModal = null;

function showModal(content, type = 'info') {
    closeModal();
    
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>${type === 'loading' ? 'Processing' : 'Information'}</h2>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <p>${content}</p>
                ${type === 'loading' ? '<div class="loading-spinner"></div>' : ''}
            </div>
            ${type !== 'loading' ? '<div class="modal-footer"><button onclick="closeModal()">Close</button></div>' : ''}
        </div>
    `;
    
    document.body.appendChild(modal);
    currentModal = modal;
    
    // Close button handler
    modal.querySelector('.modal-close')?.addEventListener('click', closeModal);
}

function closeModal() {
    if (currentModal) {
        currentModal.remove();
        currentModal = null;
    }
}

function showTrainingProgress() {
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>Model Training Progress</h2>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%"></div>
                </div>
                <p id="training-status">Initializing training...</p>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Simulate progress (replace with actual WebSocket/SSE)
    let progress = 0;
    const interval = setInterval(() => {
        progress += 10;
        const fill = modal.querySelector('.progress-fill');
        const status = modal.getElementById('training-status');
        
        if (fill) fill.style.width = progress + '%';
        if (status) status.textContent = `Training in progress... ${progress}%`;
        
        if (progress >= 100) {
            clearInterval(interval);
            if (status) status.textContent = 'Training completed!';
            setTimeout(() => modal.remove(), 2000);
        }
    }, 500);
    
    modal.querySelector('.modal-close')?.addEventListener('click', () => {
        clearInterval(interval);
        modal.remove();
    });
}

function showHealthReport(health) {
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>System Health Report</h2>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="health-score">
                    <h3>Overall Health: ${health.health_score}%</h3>
                    <div class="progress-bar">
                        <div class="progress-fill ${getHealthColor(health.health_score)}" style="width: ${health.health_score}%"></div>
                    </div>
                </div>
                <div class="health-metrics">
                    ${Object.entries(health.metrics || {}).map(([key, value]) => `
                        <div class="metric-item">
                            <span class="metric-label">${key}:</span>
                            <span class="metric-value ${value.status}">${value.value}</span>
                        </div>
                    `).join('')}
                </div>
                ${health.issues && health.issues.length ? `
                    <div class="health-issues">
                        <h4>Issues Found:</h4>
                        <ul>
                            ${health.issues.map(issue => `<li class="issue">${issue}</li>`).join('')}
                        </ul>
                    </div>
                ` : '<p class="no-issues">✓ No issues detected</p>'}
            </div>
            <div class="modal-footer">
                <button onclick="closeModal()">Close</button>
                <button onclick="runHealthCheck()" class="btn-primary">Refresh</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    currentModal = modal;
    
    modal.querySelector('.modal-close')?.addEventListener('click', closeModal);
}

// =====================================================
// Notification System
// =====================================================
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type} fade-in`;
    notification.innerHTML = `
        <span class="notification-icon">${getNotificationIcon(type)}</span>
        <span class="notification-message">${message}</span>
        <button class="notification-close">&times;</button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
    
    // Close button handler
    notification.querySelector('.notification-close')?.addEventListener('click', () => {
        notification.remove();
    });
}

function displayNotifications(notifications) {
    if (!notifications || notifications.length === 0) return;
    
    notifications.forEach(notification => {
        showNotification(notification.message, notification.type);
    });
}

// =====================================================
// Helper Functions
// =====================================================
function formatNumber(num) {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) return 'Just now';
    if (diff < 3600000) return Math.floor(diff / 60000) + ' minutes ago';
    if (diff < 86400000) return Math.floor(diff / 3600000) + ' hours ago';
    
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}

function getStatusClass(status) {
    if (status >= 200 && status < 300) return 'success';
    if (status >= 400 && status < 500) return 'warning';
    if (status >= 500) return 'error';
    return 'info';
}

function getHealthColor(score) {
    if (score >= 90) return 'healthy';
    if (score >= 70) return 'warning';
    return 'critical';
}

function getNotificationIcon(type) {
    switch(type) {
        case 'success': return '✅';
        case 'error': return '❌';
        case 'warning': return '⚠️';
        default: return 'ℹ️';
    }
}

function getLast7Days() {
    const dates = [];
    for (let i = 6; i >= 0; i--) {
        const date = new Date();
        date.setDate(date.getDate() - i);
        dates.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
    }
    return dates;
}

function startAutoRefresh() {
    if (refreshInterval) clearInterval(refreshInterval);
    refreshInterval = setInterval(() => {
        loadRecentActivity();
        updateCharts();
    }, 30000);
}

function stopAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
}

function viewActivityDetails(activityId) {
    window.location.href = `?page=activity-details&id=${activityId}`;
}

function openSettings() {
    window.location.href = '?page=api-settings';
}

// =====================================================
// Export for global access
// =====================================================
window.refreshDashboard = refreshDashboard;
window.testAPI = testAPI;
window.clearCache = clearCache;
window.trainModel = trainModel;
window.exportData = exportData;
window.runHealthCheck = runHealthCheck;
window.optimizeIndex = optimizeIndex;
window.openSettings = openSettings;
window.closeModal = closeModal;
window.viewActivityDetails = viewActivityDetails;