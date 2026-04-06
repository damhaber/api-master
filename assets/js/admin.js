/**
 * API Master - Admin Panel JavaScript
 * @package APIMaster
 * @subpackage Assets/JS
 * @version 1.0.0
 */

;(function(global) {
    'use strict';

    // ============================================
    // APIMaster Admin Class
    // ============================================
    class APIMaster_Admin {
        constructor() {
            this.apiBase = '/api/v1';
            this.token = null;
            this.currentPanel = 'dashboard';
            this.init();
        }

        // ============================================
        // Initialization
        // ============================================
        init() {
            this.cacheDOM();
            this.bindEvents();
            this.loadToken();
            this.loadDashboard();
            this.startAutoRefresh();
        }

        cacheDOM() {
            this.dom = {
                wrapper: document.querySelector('.apim-wrapper'),
                container: document.querySelector('.apim-container'),
                navItems: document.querySelectorAll('.apim-nav-item'),
                panels: document.querySelectorAll('.apim-panel'),
                statsGrid: document.querySelector('.apim-stats-grid'),
                providersTable: document.querySelector('#providers-table tbody'),
                logsContainer: document.querySelector('#logs-container'),
                learningMetrics: document.querySelector('#learning-metrics'),
                vectorStats: document.querySelector('#vector-stats'),
                refreshBtn: document.querySelector('#refresh-btn'),
                settingsForm: document.querySelector('#settings-form'),
                testApiBtn: document.querySelector('#test-api-btn')
            };
        }

        bindEvents() {
            // Navigation
            this.dom.navItems.forEach(item => {
                item.addEventListener('click', (e) => this.switchPanel(e));
            });

            // Refresh button
            if (this.dom.refreshBtn) {
                this.dom.refreshBtn.addEventListener('click', () => this.refreshAll());
            }

            // Test API button
            if (this.dom.testApiBtn) {
                this.dom.testApiBtn.addEventListener('click', () => this.testAPI());
            }

            // Settings form
            if (this.dom.settingsForm) {
                this.dom.settingsForm.addEventListener('submit', (e) => this.saveSettings(e));
            }
        }

        // ============================================
        // Navigation
        // ============================================
        switchPanel(e) {
            const target = e.currentTarget;
            const panelId = target.dataset.panel;

            if (!panelId) return;

            // Update active states
            this.dom.navItems.forEach(item => {
                item.classList.remove('active');
            });
            target.classList.add('active');

            this.dom.panels.forEach(panel => {
                panel.classList.remove('active');
            });

            const activePanel = document.getElementById(`panel-${panelId}`);
            if (activePanel) {
                activePanel.classList.add('active');
                this.currentPanel = panelId;
                
                // Load panel data
                this.loadPanelData(panelId);
            }
        }

        loadPanelData(panelId) {
            switch(panelId) {
                case 'dashboard':
                    this.loadDashboard();
                    break;
                case 'providers':
                    this.loadProviders();
                    break;
                case 'logs':
                    this.loadLogs();
                    break;
                case 'learning':
                    this.loadLearningStats();
                    break;
                case 'vector':
                    this.loadVectorStats();
                    break;
                case 'settings':
                    this.loadSettings();
                    break;
            }
        }

        // ============================================
        // API Calls
        // ============================================
        async apiCall(endpoint, options = {}) {
            const defaultOptions = {
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };

            if (this.token) {
                defaultOptions.headers['Authorization'] = `Bearer ${this.token}`;
            }

            const finalOptions = { ...defaultOptions, ...options };
            
            try {
                const response = await fetch(`${this.apiBase}${endpoint}`, finalOptions);
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || 'API request failed');
                }
                
                return data;
            } catch (error) {
                this.showNotification(error.message, 'error');
                throw error;
            }
        }

        // ============================================
        // Dashboard Methods
        // ============================================
        async loadDashboard() {
            this.showLoading('dashboard');
            
            try {
                const [stats, health, recent] = await Promise.all([
                    this.apiCall('/stats'),
                    this.apiCall('/health'),
                    this.apiCall('/recent-activity')
                ]);
                
                this.renderStats(stats);
                this.renderHealthStatus(health);
                this.renderRecentActivity(recent);
            } catch (error) {
                console.error('Failed to load dashboard:', error);
            } finally {
                this.hideLoading('dashboard');
            }
        }

        renderStats(stats) {
            if (!this.dom.statsGrid) return;
            
            const statsData = [
                { icon: '🔌', value: stats.total_providers || 0, label: 'API Providers', trend: '+12%' },
                { icon: '📊', value: stats.total_requests || 0, label: 'Total Requests', trend: '+23%' },
                { icon: '✅', value: stats.success_rate || '99.9', label: 'Success Rate', trend: '+2.1%', suffix: '%' },
                { icon: '⚡', value: stats.avg_response_time || 245, label: 'Avg Response', trend: '-15ms', suffix: 'ms' },
                { icon: '🧠', value: stats.learning_samples || 15420, label: 'Learning Samples', trend: '+1.2k' },
                { icon: '🔍', value: stats.vector_dimensions || 1536, label: 'Vector Dims', trend: 'Active' }
            ];
            
            this.dom.statsGrid.innerHTML = statsData.map(stat => `
                <div class="apim-stat-card">
                    <div class="stat-icon">${stat.icon}</div>
                    <div class="stat-value">${stat.value}${stat.suffix || ''}</div>
                    <div class="stat-label">${stat.label}</div>
                    <div class="stat-trend ${stat.trend.includes('-') ? 'trend-down' : 'trend-up'}">
                        ${stat.trend}
                    </div>
                </div>
            `).join('');
        }

        renderHealthStatus(health) {
            // Implementation for health status rendering
            const healthContainer = document.querySelector('#health-status');
            if (healthContainer) {
                healthContainer.innerHTML = `
                    <div class="apim-alert apim-alert-${health.status === 'healthy' ? 'success' : 'warning'}">
                        System Status: ${health.status.toUpperCase()}
                        <br><small>Uptime: ${health.uptime || 'N/A'}</small>
                    </div>
                `;
            }
        }

        renderRecentActivity(activities) {
            const activityContainer = document.querySelector('#recent-activity');
            if (activityContainer && activities.length) {
                activityContainer.innerHTML = activities.map(act => `
                    <div class="activity-item">
                        <span class="activity-time">${act.time}</span>
                        <span class="activity-type">${act.type}</span>
                        <span class="activity-desc">${act.description}</span>
                    </div>
                `).join('');
            }
        }

        // ============================================
        // Providers Methods
        // ============================================
        async loadProviders() {
            this.showLoading('providers');
            
            try {
                const providers = await this.apiCall('/providers');
                this.renderProviders(providers);
            } catch (error) {
                console.error('Failed to load providers:', error);
            } finally {
                this.hideLoading('providers');
            }
        }

        renderProviders(providers) {
            if (!this.dom.providersTable) return;
            
            this.dom.providersTable.innerHTML = providers.map(provider => `
                <tr data-provider-id="${provider.id}">
                    <td>
                        <span class="provider-status status-${provider.status}"></span>
                        ${provider.name}
                    </td>
                    <td>${provider.version || 'v1'}</td>
                    <td>${this.formatNumber(provider.requests_today)}</td>
                    <td>${provider.success_rate || '99.9'}%</td>
                    <td>${provider.avg_response_time || 0}ms</td>
                    <td>
                        <button class="apim-btn apim-btn-sm test-provider" data-id="${provider.id}">
                            🔍 Test
                        </button>
                        <button class="apim-btn apim-btn-sm configure-provider" data-id="${provider.id}">
                            ⚙️ Config
                        </button>
                    </td>
                </tr>
            `).join('');
            
            // Bind provider buttons
            document.querySelectorAll('.test-provider').forEach(btn => {
                btn.addEventListener('click', (e) => this.testProvider(btn.dataset.id));
            });
        }

        // ============================================
        // Logs Methods
        // ============================================
        async loadLogs(filters = {}) {
            this.showLoading('logs');
            
            try {
                const queryString = new URLSearchParams(filters).toString();
                const logs = await this.apiCall(`/logs?${queryString}`);
                this.renderLogs(logs);
            } catch (error) {
                console.error('Failed to load logs:', error);
            } finally {
                this.hideLoading('logs');
            }
        }

        renderLogs(logs) {
            if (!this.dom.logsContainer) return;
            
            this.dom.logsContainer.innerHTML = logs.map(log => `
                <div class="log-entry log-${log.level}">
                    <span class="log-time">${log.timestamp}</span>
                    <span class="log-level">[${log.level.toUpperCase()}]</span>
                    <span class="log-message">${log.message}</span>
                    <span class="log-context">${JSON.stringify(log.context || {})}</span>
                </div>
            `).join('');
        }

        // ============================================
        // Learning Methods
        // ============================================
        async loadLearningStats() {
            this.showLoading('learning');
            
            try {
                const stats = await this.apiCall('/learning/stats');
                this.renderLearningStats(stats);
            } catch (error) {
                console.error('Failed to load learning stats:', error);
            } finally {
                this.hideLoading('learning');
            }
        }

        renderLearningStats(stats) {
            if (!this.dom.learningMetrics) return;
            
            this.dom.learningMetrics.innerHTML = `
                <div class="learning-grid">
                    <div class="learning-card">
                        <h4>🧠 Memory Consolidation</h4>
                        <div class="metric-value">${stats.memory_consolidation || 'Active'}</div>
                        <div class="metric-detail">Patterns: ${stats.patterns_learned || 0}</div>
                    </div>
                    <div class="learning-card">
                        <h4>📈 Prediction Accuracy</h4>
                        <div class="metric-value">${stats.prediction_accuracy || '94.5'}%</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${stats.prediction_accuracy || 94.5}%"></div>
                        </div>
                    </div>
                    <div class="learning-card">
                        <h4>🔄 Active Learning</h4>
                        <div class="metric-value">${stats.active_learning || 'Enabled'}</div>
                        <div class="metric-detail">Samples/day: ${stats.samples_per_day || 1250}</div>
                    </div>
                </div>
            `;
        }

        // ============================================
        // Vector Methods
        // ============================================
        async loadVectorStats() {
            this.showLoading('vector');
            
            try {
                const stats = await this.apiCall('/vector/stats');
                this.renderVectorStats(stats);
            } catch (error) {
                console.error('Failed to load vector stats:', error);
            } finally {
                this.hideLoading('vector');
            }
        }

        renderVectorStats(stats) {
            if (!this.dom.vectorStats) return;
            
            this.dom.vectorStats.innerHTML = `
                <div class="vector-stats-grid">
                    <div class="stat-card">
                        <strong>HNSW Index</strong>
                        <div>Dimensions: ${stats.dimensions || 1536}</div>
                        <div>Vectors: ${this.formatNumber(stats.vector_count || 0)}</div>
                        <div>Index Size: ${stats.index_size_mb || 0} MB</div>
                    </div>
                    <div class="stat-card">
                        <strong>Performance</strong>
                        <div>Search Time: ${stats.avg_search_ms || 15}ms</div>
                        <div>Recall: ${stats.recall_rate || 98.5}%</div>
                        <div>Build Time: ${stats.build_time_sec || 12}s</div>
                    </div>
                </div>
            `;
        }

        // ============================================
        // Settings Methods
        // ============================================
        async loadSettings() {
            try {
                const settings = await this.apiCall('/settings');
                this.populateSettings(settings);
            } catch (error) {
                console.error('Failed to load settings:', error);
            }
        }

        populateSettings(settings) {
            if (!this.dom.settingsForm) return;
            
            // Populate form fields with settings
            Object.keys(settings).forEach(key => {
                const field = this.dom.settingsForm.querySelector(`[name="${key}"]`);
                if (field) {
                    field.value = settings[key];
                }
            });
        }

        async saveSettings(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const settings = Object.fromEntries(formData.entries());
            
            this.showNotification('Saving settings...', 'info');
            
            try {
                await this.apiCall('/settings', {
                    method: 'POST',
                    body: JSON.stringify(settings)
                });
                this.showNotification('Settings saved successfully!', 'success');
            } catch (error) {
                this.showNotification('Failed to save settings', 'error');
            }
        }

        // ============================================
        // Utility Methods
        // ============================================
        async testAPI() {
            this.showNotification('Testing API connection...', 'info');
            
            try {
                const result = await this.apiCall('/test');
                if (result.success) {
                    this.showNotification('API connection successful!', 'success');
                } else {
                    this.showNotification('API test failed', 'error');
                }
            } catch (error) {
                this.showNotification('API connection error', 'error');
            }
        }

        async testProvider(providerId) {
            this.showNotification(`Testing provider ${providerId}...`, 'info');
            
            try {
                const result = await this.apiCall(`/providers/${providerId}/test`);
                if (result.success) {
                    this.showNotification(`Provider ${providerId} is working!`, 'success');
                } else {
                    this.showNotification(`Provider ${providerId} test failed`, 'error');
                }
            } catch (error) {
                this.showNotification(`Provider test error`, 'error');
            }
        }

        loadToken() {
            this.token = localStorage.getItem('apimaster_token');
            if (!this.token) {
                // Try to get token from meta tag
                const metaToken = document.querySelector('meta[name="api-token"]');
                if (metaToken) {
                    this.token = metaToken.content;
                    localStorage.setItem('apimaster_token', this.token);
                }
            }
        }

        refreshAll() {
            this.loadPanelData(this.currentPanel);
            this.showNotification('Refreshed successfully!', 'success');
        }

        startAutoRefresh() {
            // Auto refresh every 30 seconds
            setInterval(() => {
                if (document.hasFocus()) {
                    this.loadPanelData(this.currentPanel);
                }
            }, 30000);
        }

        showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `apim-notification apim-notification-${type}`;
            notification.innerHTML = `
                <span class="notification-icon">${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'}</span>
                <span class="notification-message">${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('fade-out');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        showLoading(panel) {
            const panelElement = document.getElementById(`panel-${panel}`);
            if (panelElement) {
                const overlay = document.createElement('div');
                overlay.className = 'apim-loading-overlay';
                overlay.innerHTML = '<div class="apim-spinner"></div>';
                panelElement.style.position = 'relative';
                panelElement.appendChild(overlay);
            }
        }

        hideLoading(panel) {
            const panelElement = document.getElementById(`panel-${panel}`);
            if (panelElement) {
                const overlay = panelElement.querySelector('.apim-loading-overlay');
                if (overlay) overlay.remove();
                panelElement.style.position = '';
            }
        }

        formatNumber(num) {
            if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
            if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
            return num.toString();
        }
    }

    // ============================================
    // Initialize on DOM Ready
    // ============================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            global.APIMasterAdmin = new APIMaster_Admin();
        });
    } else {
        global.APIMasterAdmin = new APIMaster_Admin();
    }

})(window);

// ============================================
// CSS for Notifications (dynamic)
// ============================================
(function addNotificationStyles() {
    const style = document.createElement('style');
    style.textContent = `
        .apim-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideInRight 0.3s ease;
            border-left: 4px solid;
        }
        
        .apim-notification-success {
            border-left-color: #06d6a0;
        }
        
        .apim-notification-error {
            border-left-color: #ef476f;
        }
        
        .apim-notification-info {
            border-left-color: #4361ee;
        }
        
        .apim-notification.fade-out {
            animation: fadeOut 0.3s ease forwards;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        .activity-item {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            gap: 15px;
            font-size: 13px;
        }
        
        .activity-time {
            color: #6c757d;
            font-size: 11px;
        }
        
        .log-entry {
            padding: 8px 12px;
            border-bottom: 1px solid #dee2e6;
            font-family: monospace;
            font-size: 12px;
        }
        
        .log-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .log-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .learning-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .learning-card {
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }
        
        .progress-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: #4361ee;
            transition: width 0.3s ease;
        }
        
        .vector-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .stat-card {
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: white;
        }
    `;
    document.head.appendChild(style);
})();