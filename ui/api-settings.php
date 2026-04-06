<?php
/**
 * API Master Settings Page
 * 
 * @package APIMaster
 * @subpackage UI
 * @since 1.0.0
 * 
 * IMPORTANT: Standalone settings UI - NO WordPress dependencies!
 * Supports 65+ API providers dynamically from config
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_Settings {
    
    /**
     * @var array $config Configuration
     */
    private $config;
    
    /**
     * @var array $providers All providers (65+)
     */
    private $providers;
    
    /**
     * @var array $api_keys Saved API keys
     */
    private $api_keys;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->loadConfig();
        $this->loadProviders();
        $this->loadApiKeys();
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
     * Load all providers from config (65+ API'ler)
     */
    private function loadProviders() {
        $providers_file = dirname(dirname(__FILE__)) . '/config/providers.json';
        if (file_exists($providers_file)) {
            $data = json_decode(file_get_contents($providers_file), true);
            $this->providers = $data['providers'] ?? [];
        } else {
            $this->providers = [];
        }
        
        // Also load from individual provider config files
        $providers_dir = dirname(dirname(__FILE__)) . '/config/providers/';
        if (is_dir($providers_dir)) {
            $files = glob($providers_dir . '*.json');
            foreach ($files as $file) {
                $provider_data = json_decode(file_get_contents($file), true);
                if ($provider_data && isset($provider_data['slug'])) {
                    $this->providers[$provider_data['slug']] = $provider_data;
                }
            }
        }
    }
    
    /**
     * Load saved API keys
     */
    private function loadApiKeys() {
        $keys_file = dirname(dirname(__FILE__)) . '/data/api-keys.json';
        if (file_exists($keys_file)) {
            $data = json_decode(file_get_contents($keys_file), true);
            $this->api_keys = $data['keys'] ?? [];
        } else {
            $this->api_keys = [];
        }
    }
    
    /**
     * Render settings page
     */
    public function render() {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>API Master - Settings</title>
            <link rel="stylesheet" href="style.css">
        </head>
        <body>
            <div class="apimaster-settings">
                <!-- Header -->
                <div class="settings-header">
                    <h1>⚙️ API Master Settings</h1>
                    <div class="header-actions">
                        <button class="btn-back" onclick="window.location.href='dashboard.php'">← Back to Dashboard</button>
                        <button class="btn-save-all" onclick="saveAllSettings()">💾 Save All Settings</button>
                    </div>
                </div>
                
                <!-- Settings Tabs -->
                <div class="settings-tabs">
                    <button class="tab-btn active" data-tab="providers">🔌 API Providers (<?php echo count($this->providers); ?>)</button>
                    <button class="tab-btn" data-tab="general">⚙️ General</button>
                    <button class="tab-btn" data-tab="security">🔒 Security</button>
                    <button class="tab-btn" data-tab="performance">🚀 Performance</button>
                    <button class="tab-btn" data-tab="learning">🧠 Learning</button>
                    <button class="tab-btn" data-tab="vector">📊 Vector DB</button>
                    <button class="tab-btn" data-tab="backup">💾 Backup & Restore</button>
                </div>
                
                <!-- Providers Tab (65+ API'ler burada listelenecek) -->
                <div id="tab-providers" class="tab-content active">
                    <div class="settings-section">
                        <div class="section-header">
                            <h2>🔌 API Providers</h2>
                            <p>Configure API keys for <?php echo count($this->providers); ?>+ providers</p>
                            <div class="search-box">
                                <input type="text" id="provider-search" placeholder="Search providers..." onkeyup="filterProviders()">
                                <span class="search-icon">🔍</span>
                            </div>
                        </div>
                        
                        <div class="providers-grid" id="providers-grid">
                            <?php foreach ($this->providers as $slug => $provider): ?>
                                <div class="provider-card" data-provider="<?php echo htmlspecialchars($slug); ?>">
                                    <div class="provider-header">
                                        <div class="provider-icon">
                                            <?php echo $this->getProviderIcon($slug); ?>
                                        </div>
                                        <div class="provider-info">
                                            <h3><?php echo htmlspecialchars($provider['name']); ?></h3>
                                            <span class="provider-slug"><?php echo htmlspecialchars($slug); ?></span>
                                        </div>
                                        <div class="provider-status">
                                            <span class="status-badge <?php echo $this->isProviderActive($slug) ? 'active' : 'inactive'; ?>">
                                                <?php echo $this->isProviderActive($slug) ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="provider-body">
                                        <div class="form-group">
                                            <label>API Key</label>
                                            <div class="api-key-input">
                                                <input type="password" 
                                                       id="key-<?php echo htmlspecialchars($slug); ?>" 
                                                       value="<?php echo htmlspecialchars($this->getApiKey($slug)); ?>"
                                                       placeholder="Enter your API key">
                                                <button class="btn-toggle-visibility" onclick="toggleKeyVisibility('<?php echo $slug; ?>')">👁️</button>
                                                <button class="btn-test" onclick="testProvider('<?php echo $slug; ?>')">Test</button>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Base URL</label>
                                            <input type="text" 
                                                   id="url-<?php echo htmlspecialchars($slug); ?>" 
                                                   value="<?php echo htmlspecialchars($provider['base_url'] ?? ''); ?>"
                                                   placeholder="API Base URL">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Rate Limit (requests/min)</label>
                                            <input type="number" 
                                                   id="rate-<?php echo htmlspecialchars($slug); ?>" 
                                                   value="<?php echo $provider['rate_limits']['requests_per_minute'] ?? 60; ?>">
                                        </div>
                                        
                                        <div class="provider-models">
                                            <label>Available Models</label>
                                            <div class="models-list">
                                                <?php if (isset($provider['models']) && is_array($provider['models'])): ?>
                                                    <?php foreach (array_slice($provider['models'], 0, 5) as $model): ?>
                                                        <span class="model-tag"><?php echo htmlspecialchars($model); ?></span>
                                                    <?php endforeach; ?>
                                                    <?php if (count($provider['models']) > 5): ?>
                                                        <span class="model-tag more">+<?php echo count($provider['models']) - 5; ?> more</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="provider-actions">
                                            <label class="switch">
                                                <input type="checkbox" 
                                                       id="active-<?php echo htmlspecialchars($slug); ?>" 
                                                       <?php echo $this->isProviderActive($slug) ? 'checked' : ''; ?>>
                                                <span class="slider round"></span>
                                                <span class="switch-label">Enable Provider</span>
                                            </label>
                                            <button class="btn-save-provider" onclick="saveProvider('<?php echo $slug; ?>')">Save</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (empty($this->providers)): ?>
                            <div class="no-providers">
                                <p>No providers found. Please check config/providers.json</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- General Settings Tab -->
                <div id="tab-general" class="tab-content">
                    <div class="settings-section">
                        <h2>⚙️ General Settings</h2>
                        
                        <div class="form-group">
                            <label>Default Provider</label>
                            <select id="default-provider">
                                <?php foreach ($this->providers as $slug => $provider): ?>
                                    <option value="<?php echo htmlspecialchars($slug); ?>" 
                                        <?php echo ($this->config['api']['default_provider'] ?? 'openai') === $slug ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($provider['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Default Model</label>
                            <input type="text" id="default-model" value="<?php echo $this->config['api']['default_model'] ?? 'gpt-3.5-turbo'; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Request Timeout (seconds)</label>
                            <input type="number" id="timeout" value="<?php echo $this->config['api']['timeout'] ?? 30; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Max Retry Count</label>
                            <input type="number" id="retry-count" value="<?php echo $this->config['api']['retry_count'] ?? 3; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Debug Mode</label>
                            <label class="switch">
                                <input type="checkbox" id="debug-mode" <?php echo ($this->config['debug']['mode'] ?? false) ? 'checked' : ''; ?>>
                                <span class="slider round"></span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>Log Level</label>
                            <select id="log-level">
                                <option value="debug" <?php echo ($this->config['debug']['level'] ?? 'info') === 'debug' ? 'selected' : ''; ?>>Debug</option>
                                <option value="info" <?php echo ($this->config['debug']['level'] ?? 'info') === 'info' ? 'selected' : ''; ?>>Info</option>
                                <option value="warning" <?php echo ($this->config['debug']['level'] ?? 'info') === 'warning' ? 'selected' : ''; ?>>Warning</option>
                                <option value="error" <?php echo ($this->config['debug']['level'] ?? 'info') === 'error' ? 'selected' : ''; ?>>Error</option>
                            </select>
                        </div>
                        
                        <button class="btn-save" onclick="saveGeneralSettings()">Save General Settings</button>
                    </div>
                </div>
                
                <!-- Security Tab -->
                <div id="tab-security" class="tab-content">
                    <div class="settings-section">
                        <h2>🔒 Security Settings</h2>
                        
                        <div class="form-group">
                            <label>API Key Encryption</label>
                            <label class="switch">
                                <input type="checkbox" id="encryption-enabled" <?php echo ($this->config['security']['encryption_enabled'] ?? true) ? 'checked' : ''; ?>>
                                <span class="slider round"></span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>Hash Algorithm</label>
                            <select id="hash-algo">
                                <option value="sha256" <?php echo ($this->config['security']['hash_algorithm'] ?? 'sha256') === 'sha256' ? 'selected' : ''; ?>>SHA-256</option>
                                <option value="sha512" <?php echo ($this->config['security']['hash_algorithm'] ?? 'sha256') === 'sha512' ? 'selected' : ''; ?>>SHA-512</option>
                                <option value="md5" <?php echo ($this->config['security']['hash_algorithm'] ?? 'sha256') === 'md5' ? 'selected' : ''; ?>>MD5 (Not Recommended)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>API Key Length (characters)</label>
                            <input type="number" id="key-length" value="<?php echo $this->config['security']['api_key_length'] ?? 32; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>JWT Authentication</label>
                            <label class="switch">
                                <input type="checkbox" id="jwt-enabled" <?php echo ($this->config['security']['jwt_enabled'] ?? false) ? 'checked' : ''; ?>>
                                <span class="slider round"></span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>Allowed IPs (comma separated)</label>
                            <textarea id="allowed-ips" rows="3" placeholder="192.168.1.1, 10.0.0.0/24"><?php echo implode(', ', $this->config['security']['allowed_ips'] ?? []); ?></textarea>
                        </div>
                        
                        <button class="btn-save" onclick="saveSecuritySettings()">Save Security Settings</button>
                    </div>
                </div>
                
                <!-- Performance Tab -->
                <div id="tab-performance" class="tab-content">
                    <div class="settings-section">
                        <h2>🚀 Performance Settings</h2>
                        
                        <div class="form-group">
                            <label>Cache Enabled</label>
                            <label class="switch">
                                <input type="checkbox" id="cache-enabled" <?php echo ($this->config['cache']['enabled'] ?? true) ? 'checked' : ''; ?>>
                                <span class="slider round"></span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>Cache TTL (seconds)</label>
                            <input type="number" id="cache-ttl" value="<?php echo $this->config['cache']['ttl'] ?? 3600; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Cache Driver</label>
                            <select id="cache-driver">
                                <option value="file" <?php echo ($this->config['cache']['driver'] ?? 'file') === 'file' ? 'selected' : ''; ?>>File</option>
                                <option value="redis" <?php echo ($this->config['cache']['driver'] ?? 'file') === 'redis' ? 'selected' : ''; ?>>Redis</option>
                                <option value="memcached" <?php echo ($this->config['cache']['driver'] ?? 'file') === 'memcached' ? 'selected' : ''; ?>>Memcached</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Rate Limiting Enabled</label>
                            <label class="switch">
                                <input type="checkbox" id="rate-limit-enabled" <?php echo ($this->config['rate_limiting']['enabled'] ?? true) ? 'checked' : ''; ?>>
                                <span class="slider round"></span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>Rate Limit (requests per minute)</label>
                            <input type="number" id="rate-limit" value="<?php echo $this->config['rate_limiting']['per_minute'] ?? 60; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Compression Enabled</label>
                            <label class="switch">
                                <input type="checkbox" id="compression-enabled" <?php echo ($this->config['cache']['compression'] ?? true) ? 'checked' : ''; ?>>
                                <span class="slider round"></span>
                            </label>
                        </div>
                        
                        <button class="btn-save" onclick="savePerformanceSettings()">Save Performance Settings</button>
                    </div>
                </div>
                
                <!-- Learning Tab -->
                <div id="tab-learning" class="tab-content">
                    <div class="settings-section">
                        <h2>🧠 Learning System Settings</h2>
                        
                        <div class="stats-preview">
                            <div class="stat-item">
                                <span class="stat-label">Training Samples:</span>
                                <span class="stat-value" id="training-samples">12,450</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Model Accuracy:</span>
                                <span class="stat-value" id="model-accuracy">87.3%</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Last Training:</span>
                                <span class="stat-value" id="last-training">2024-01-15 10:30:00</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Learning Enabled</label>
                            <label class="switch">
                                <input type="checkbox" id="learning-enabled" <?php echo ($this->config['learning']['enabled'] ?? true) ? 'checked' : ''; ?>>
                                <span class="slider round"></span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>Auto Learn</label>
                            <label class="switch">
                                <input type="checkbox" id="auto-learn" <?php echo ($this->config['learning']['auto_learn'] ?? true) ? 'checked' : ''; ?>>
                                <span class="slider round"></span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>Confidence Threshold</label>
                            <input type="range" id="confidence-threshold" min="0" max="1" step="0.05" 
                                   value="<?php echo $this->config['learning']['confidence_threshold'] ?? 0.7; ?>">
                            <span id="confidence-value"><?php echo $this->config['learning']['confidence_threshold'] ?? 0.7; ?></span>
                        </div>
                        
                        <div class="form-group">
                            <label>Model Type</label>
                            <select id="model-type">
                                <option value="lightgbm" <?php echo ($this->config['learning']['model_type'] ?? 'lightgbm') === 'lightgbm' ? 'selected' : ''; ?>>LightGBM</option>
                                <option value="neural" <?php echo ($this->config['learning']['model_type'] ?? 'lightgbm') === 'neural' ? 'selected' : ''; ?>>Neural Network</option>
                                <option value="logistic" <?php echo ($this->config['learning']['model_type'] ?? 'lightgbm') === 'logistic' ? 'selected' : ''; ?>>Logistic Regression</option>
                            </select>
                        </div>
                        
                        <button class="btn-save" onclick="saveLearningSettings()">Save Learning Settings</button>
                        <button class="btn-train" onclick="trainModelNow()">🤖 Train Model Now</button>
                    </div>
                </div>
                
                <!-- Vector DB Tab -->
                <div id="tab-vector" class="tab-content">
                    <div class="settings-section">
                        <h2>📊 Vector Database Settings</h2>
                        
                        <div class="stats-preview">
                            <div class="stat-item">
                                <span class="stat-label">Total Vectors:</span>
                                <span class="stat-value" id="total-vectors">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Index Type:</span>
                                <span class="stat-value" id="index-type">HNSW</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Dimension:</span>
                                <span class="stat-value" id="vector-dim">1536</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Vector DB Enabled</label>
                            <label class="switch">
                                <input type="checkbox" id="vector-enabled" <?php echo ($this->config['vector_db']['enabled'] ?? true) ? 'checked' : ''; ?>>
                                <span class="slider round"></span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label>Index Type</label>
                            <select id="index-type-select">
                                <option value="hnsw" <?php echo ($this->config['vector_db']['index_type'] ?? 'hnsw') === 'hnsw' ? 'selected' : ''; ?>>HNSW (Fast)</option>
                                <option value="flat" <?php echo ($this->config['vector_db']['index_type'] ?? 'hnsw') === 'flat' ? 'selected' : ''; ?>>Flat (Exact)</option>
                                <option value="ivf" <?php echo ($this->config['vector_db']['index_type'] ?? 'hnsw') === 'ivf' ? 'selected' : ''; ?>>IVF (Balanced)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Similarity Metric</label>
                            <select id="similarity-metric">
                                <option value="cosine" <?php echo ($this->config['vector_db']['similarity_metric'] ?? 'cosine') === 'cosine' ? 'selected' : ''; ?>>Cosine Similarity</option>
                                <option value="euclidean" <?php echo ($this->config['vector_db']['similarity_metric'] ?? 'cosine') === 'euclidean' ? 'selected' : ''; ?>>Euclidean Distance</option>
                                <option value="dot_product" <?php echo ($this->config['vector_db']['similarity_metric'] ?? 'cosine') === 'dot_product' ? 'selected' : ''; ?>>Dot Product</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Short Term Memory Limit</label>
                            <input type="number" id="short-term-limit" value="<?php echo $this->config['vector_db']['memory']['short_term_limit'] ?? 1000; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Long Term Memory Limit</label>
                            <input type="number" id="long-term-limit" value="<?php echo $this->config['vector_db']['memory']['long_term_limit'] ?? 100000; ?>">
                        </div>
                        
                        <button class="btn-save" onclick="saveVectorSettings()">Save Vector Settings</button>
                        <button class="btn-optimize" onclick="optimizeVectorIndex()">⚡ Optimize Index</button>
                        <button class="btn-consolidate" onclick="runConsolidation()">🔄 Run Consolidation</button>
                    </div>
                </div>
                
                <!-- Backup Tab -->
                <div id="tab-backup" class="tab-content">
                    <div class="settings-section">
                        <h2>💾 Backup & Restore</h2>
                        
                        <div class="backup-info">
                            <p>Create a full backup of all settings, API keys, and vector data.</p>
                            <button class="btn-backup" onclick="createBackup()">📦 Create Backup</button>
                        </div>
                        
                        <div class="restore-section">
                            <h3>Restore from Backup</h3>
                            <select id="backup-list" class="backup-select">
                                <option value="">Select a backup file...</option>
                            </select>
                            <button class="btn-restore" onclick="restoreBackup()">🔄 Restore Selected</button>
                        </div>
                        
                        <div class="export-section">
                            <h3>Export Data</h3>
                            <button class="btn-export" onclick="exportAllData()">📤 Export All Data (JSON)</button>
                            <button class="btn-export" onclick="exportLogs()">📋 Export Logs Only</button>
                            <button class="btn-export" onclick="exportVectors()">📊 Export Vectors Only</button>
                        </div>
                        
                        <div class="danger-zone">
                            <h3 class="danger">⚠️ Danger Zone</h3>
                            <p>These actions cannot be undone.</p>
                            <button class="btn-danger" onclick="clearAllData()">🗑️ Clear All Data</button>
                            <button class="btn-danger" onclick="resetSettings()">🔄 Reset All Settings</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <style>
                /* Settings specific styles */
                .apimaster-settings {
                    max-width: 1400px;
                    margin: 0 auto;
                    padding: 20px;
                }
                
                .settings-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 30px;
                    padding: 20px;
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .settings-tabs {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 30px;
                    flex-wrap: wrap;
                }
                
                .tab-btn {
                    padding: 12px 24px;
                    border: none;
                    background: #f3f4f6;
                    cursor: pointer;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 500;
                    transition: all 0.3s;
                }
                
                .tab-btn.active {
                    background: #4F46E5;
                    color: white;
                }
                
                .tab-content {
                    display: none;
                }
                
                .tab-content.active {
                    display: block;
                }
                
                .settings-section {
                    background: white;
                    border-radius: 12px;
                    padding: 24px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .providers-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
                    gap: 20px;
                    margin-top: 20px;
                }
                
                .provider-card {
                    border: 1px solid #e5e7eb;
                    border-radius: 12px;
                    padding: 20px;
                    transition: all 0.3s;
                }
                
                .provider-card:hover {
                    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                    transform: translateY(-2px);
                }
                
                .provider-header {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 1px solid #e5e7eb;
                }
                
                .provider-icon {
                    width: 48px;
                    height: 48px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 24px;
                }
                
                .provider-info h3 {
                    margin: 0;
                    font-size: 18px;
                }
                
                .provider-slug {
                    font-size: 12px;
                    color: #6b7280;
                    font-family: monospace;
                }
                
                .status-badge {
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 500;
                }
                
                .status-badge.active {
                    background: #10b981;
                    color: white;
                }
                
                .status-badge.inactive {
                    background: #ef4444;
                    color: white;
                }
                
                .api-key-input {
                    display: flex;
                    gap: 10px;
                }
                
                .api-key-input input {
                    flex: 1;
                }
                
                .btn-test, .btn-save-provider {
                    padding: 8px 16px;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 12px;
                }
                
                .btn-test {
                    background: #3b82f6;
                    color: white;
                }
                
                .btn-save-provider {
                    background: #10b981;
                    color: white;
                }
                
                .models-list {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    margin-top: 8px;
                }
                
                .model-tag {
                    padding: 4px 8px;
                    background: #f3f4f6;
                    border-radius: 6px;
                    font-size: 11px;
                    font-family: monospace;
                }
                
                .model-tag.more {
                    background: #e5e7eb;
                    color: #4b5563;
                }
                
                .provider-actions {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-top: 20px;
                    padding-top: 15px;
                    border-top: 1px solid #e5e7eb;
                }
                
                .switch {
                    position: relative;
                    display: inline-block;
                    width: 50px;
                    height: 24px;
                }
                
                .switch input {
                    opacity: 0;
                    width: 0;
                    height: 0;
                }
                
                .slider {
                    position: absolute;
                    cursor: pointer;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: #ccc;
                    transition: 0.4s;
                    border-radius: 24px;
                }
                
                .slider:before {
                    position: absolute;
                    content: "";
                    height: 18px;
                    width: 18px;
                    left: 3px;
                    bottom: 3px;
                    background-color: white;
                    transition: 0.4s;
                    border-radius: 50%;
                }
                
                input:checked + .slider {
                    background-color: #4F46E5;
                }
                
                input:checked + .slider:before {
                    transform: translateX(26px);
                }
                
                .search-box {
                    position: relative;
                    margin-bottom: 20px;
                }
                
                .search-box input {
                    width: 100%;
                    padding: 12px 40px 12px 16px;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    font-size: 14px;
                }
                
                .search-icon {
                    position: absolute;
                    right: 12px;
                    top: 50%;
                    transform: translateY(-50%);
                }
                
                .stats-preview {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 20px;
                    margin-bottom: 30px;
                    padding: 20px;
                    background: #f9fafb;
                    border-radius: 12px;
                }
                
                .stat-item {
                    text-align: center;
                }
                
                .stat-label {
                    display: block;
                    font-size: 12px;
                    color: #6b7280;
                    margin-bottom: 8px;
                }
                
                .stat-value {
                    font-size: 24px;
                    font-weight: 600;
                    color: #1f2937;
                }
                
                .btn-save, .btn-train, .btn-optimize, .btn-consolidate, .btn-backup, .btn-restore, .btn-export {
                    padding: 10px 20px;
                    border: none;
                    border-radius: 8px;
                    cursor: pointer;
                    font-size: 14px;
                    margin-top: 20px;
                    margin-right: 10px;
                }
                
                .btn-save {
                    background: #4F46E5;
                    color: white;
                }
                
                .btn-train {
                    background: #8b5cf6;
                    color: white;
                }
                
                .btn-optimize, .btn-consolidate {
                    background: #f59e0b;
                    color: white;
                }
                
                .btn-backup, .btn-restore, .btn-export {
                    background: #10b981;
                    color: white;
                }
                
                .danger-zone {
                    margin-top: 30px;
                    padding: 20px;
                    border: 2px solid #ef4444;
                    border-radius: 12px;
                    background: #fef2f2;
                }
                
                .danger-zone h3.danger {
                    color: #ef4444;
                    margin-top: 0;
                }
                
                .btn-danger {
                    background: #ef4444;
                    color: white;
                    padding: 10px 20px;
                    border: none;
                    border-radius: 8px;
                    cursor: pointer;
                    margin-right: 10px;
                }
                
                .backup-select {
                    width: 100%;
                    padding: 10px;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    margin: 10px 0;
                }
                
                @media (max-width: 768px) {
                    .providers-grid {
                        grid-template-columns: 1fr;
                    }
                    
                    .stats-preview {
                        grid-template-columns: 1fr;
                    }
                    
                    .settings-tabs {
                        flex-direction: column;
                    }
                }
            </style>
            
            <script>
                // Tab switching
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const tabId = btn.dataset.tab;
                        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
                        btn.classList.add('active');
                        document.getElementById(`tab-${tabId}`).classList.add('active');
                    });
                });
                
                // Filter providers
                function filterProviders() {
                    const search = document.getElementById('provider-search').value.toLowerCase();
                    const cards = document.querySelectorAll('.provider-card');
                    
                    cards.forEach(card => {
                        const provider = card.dataset.provider;
                        const name = card.querySelector('h3').textContent.toLowerCase();
                        if (name.includes(search) || provider.includes(search)) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                }
                
                // Toggle API key visibility
                function toggleKeyVisibility(slug) {
                    const input = document.getElementById(`key-${slug}`);
                    input.type = input.type === 'password' ? 'text' : 'password';
                }
                
                // Test provider connection
                async function testProvider(slug) {
                    const apiKey = document.getElementById(`key-${slug}`).value;
                    const button = event.target;
                    const originalText = button.textContent;
                    
                    button.textContent = 'Testing...';
                    button.disabled = true;
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=test_provider', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ provider: slug, api_key: apiKey })
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            alert(`✅ ${slug} connection successful!`);
                        } else {
                            alert(`❌ Connection failed: ${result.message}`);
                        }
                    } catch (error) {
                        alert(`❌ Error: ${error.message}`);
                    } finally {
                        button.textContent = originalText;
                        button.disabled = false;
                    }
                }
                
                // Save provider settings
                async function saveProvider(slug) {
                    const apiKey = document.getElementById(`key-${slug}`).value;
                    const baseUrl = document.getElementById(`url-${slug}`).value;
                    const rateLimit = document.getElementById(`rate-${slug}`).value;
                    const isActive = document.getElementById(`active-${slug}`).checked;
                    const button = event.target;
                    const originalText = button.textContent;
                    
                    button.textContent = 'Saving...';
                    button.disabled = true;
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=save_provider', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                slug: slug,
                                api_key: apiKey,
                                base_url: baseUrl,
                                rate_limit: rateLimit,
                                is_active: isActive
                            })
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            alert(`✅ ${slug} settings saved!`);
                            location.reload();
                        } else {
                            alert(`❌ Save failed: ${result.message}`);
                        }
                    } catch (error) {
                        alert(`❌ Error: ${error.message}`);
                    } finally {
                        button.textContent = originalText;
                        button.disabled = false;
                    }
                }
                
                // Save general settings
                async function saveGeneralSettings() {
                    const settings = {
                        default_provider: document.getElementById('default-provider').value,
                        default_model: document.getElementById('default-model').value,
                        timeout: document.getElementById('timeout').value,
                        retry_count: document.getElementById('retry-count').value,
                        debug_mode: document.getElementById('debug-mode').checked,
                        log_level: document.getElementById('log-level').value
                    };
                    
                    const response = await fetch('ajax-handlers.php?action=save_general_settings', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(settings)
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('✅ General settings saved!');
                    } else {
                        alert('❌ Save failed: ' + result.message);
                    }
                }
                
                // Save security settings
                async function saveSecuritySettings() {
                    const settings = {
                        encryption_enabled: document.getElementById('encryption-enabled').checked,
                        hash_algorithm: document.getElementById('hash-algo').value,
                        api_key_length: document.getElementById('key-length').value,
                        jwt_enabled: document.getElementById('jwt-enabled').checked,
                        allowed_ips: document.getElementById('allowed-ips').value
                    };
                    
                    const response = await fetch('ajax-handlers.php?action=save_security_settings', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(settings)
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('✅ Security settings saved!');
                    } else {
                        alert('❌ Save failed: ' + result.message);
                    }
                }
                
                // Save performance settings
                async function savePerformanceSettings() {
                    const settings = {
                        cache_enabled: document.getElementById('cache-enabled').checked,
                        cache_ttl: document.getElementById('cache-ttl').value,
                        cache_driver: document.getElementById('cache-driver').value,
                        rate_limit_enabled: document.getElementById('rate-limit-enabled').checked,
                        rate_limit: document.getElementById('rate-limit').value,
                        compression_enabled: document.getElementById('compression-enabled').checked
                    };
                    
                    const response = await fetch('ajax-handlers.php?action=save_performance_settings', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(settings)
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('✅ Performance settings saved!');
                    } else {
                        alert('❌ Save failed: ' + result.message);
                    }
                }
                
                // Save learning settings
                async function saveLearningSettings() {
                    const settings = {
                        learning_enabled: document.getElementById('learning-enabled').checked,
                        auto_learn: document.getElementById('auto-learn').checked,
                        confidence_threshold: document.getElementById('confidence-threshold').value,
                        model_type: document.getElementById('model-type').value
                    };
                    
                    const response = await fetch('ajax-handlers.php?action=save_learning_settings', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(settings)
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('✅ Learning settings saved!');
                    } else {
                        alert('❌ Save failed: ' + result.message);
                    }
                }
                
                // Train model now
                async function trainModelNow() {
                    const button = event.target;
                    button.textContent = 'Training...';
                    button.disabled = true;
                    
                    const response = await fetch('ajax-handlers.php?action=train_model', {
                        method: 'POST'
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('✅ Model training started!');
                    } else {
                        alert('❌ Training failed: ' + result.message);
                    }
                    
                    button.textContent = '🤖 Train Model Now';
                    button.disabled = false;
                }
                
                // Save vector settings
                async function saveVectorSettings() {
                    const settings = {
                        vector_enabled: document.getElementById('vector-enabled').checked,
                        index_type: document.getElementById('index-type-select').value,
                        similarity_metric: document.getElementById('similarity-metric').value,
                        short_term_limit: document.getElementById('short-term-limit').value,
                        long_term_limit: document.getElementById('long-term-limit').value
                    };
                    
                    const response = await fetch('ajax-handlers.php?action=save_vector_settings', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(settings)
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('✅ Vector settings saved!');
                    } else {
                        alert('❌ Save failed: ' + result.message);
                    }
                }
                
                // Optimize vector index
                async function optimizeVectorIndex() {
                    if (!confirm('Optimizing vector index may take some time. Continue?')) return;
                    
                    const button = event.target;
                    button.textContent = 'Optimizing...';
                    button.disabled = true;
                    
                    const response = await fetch('ajax-handlers.php?action=optimize_index', {
                        method: 'POST'
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('✅ Vector index optimized!');
                    } else {
                        alert('❌ Optimization failed: ' + result.message);
                    }
                    
                    button.textContent = '⚡ Optimize Index';
                    button.disabled = false;
                }
                
                // Run consolidation
                async function runConsolidation() {
                    const button = event.target;
                    button.textContent = 'Consolidating...';
                    button.disabled = true;
                    
                    const response = await fetch('ajax-handlers.php?action=run_consolidation', {
                        method: 'POST'
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('✅ Memory consolidation completed!');
                    } else {
                        alert('❌ Consolidation failed: ' + result.message);
                    }
                    
                    button.textContent = '🔄 Run Consolidation';
                    button.disabled = false;
                }
                
                // Create backup
                async function createBackup() {
                    const button = event.target;
                    button.textContent = 'Creating...';
                    button.disabled = true;
                    
                    const response = await fetch('ajax-handlers.php?action=backup_data', {
                        method: 'POST'
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('✅ Backup created: ' + result.data.backup_file);
                        loadBackupList();
                    } else {
                        alert('❌ Backup failed: ' + result.message);
                    }
                    
                    button.textContent = '📦 Create Backup';
                    button.disabled = false;
                }
                
                // Load backup list
                async function loadBackupList() {
                    const response = await fetch('ajax-handlers.php?action=list_backups');
                    const result = await response.json();
                    
                    if (result.success && result.data.backups) {
                        const select = document.getElementById('backup-list');
                        select.innerHTML = '<option value="">Select a backup file...</option>';
                        result.data.backups.forEach(backup => {
                            select.innerHTML += `<option value="${backup}">${backup}</option>`;
                        });
                    }
                }
                
                // Restore backup
                async function restoreBackup() {
                    const backupFile = document.getElementById('backup-list').value;
                    if (!backupFile) {
                        alert('Please select a backup file');
                        return;
                    }
                    
                    if (!confirm(`Restore from ${backupFile}? This will overwrite current data.`)) return;
                    
                    const response = await fetch('ajax-handlers.php?action=restore_backup', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ backup_file: backupFile })
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('✅ Backup restored! Page will reload.');
                        location.reload();
                    } else {
                        alert('❌ Restore failed: ' + result.message);
                    }
                }
                
                // Export all data
                function exportAllData() {
                    window.open('ajax-handlers.php?action=export_data&type=all', '_blank');
                }
                
                // Export logs
                function exportLogs() {
                    window.open('ajax-handlers.php?action=export_data&type=logs', '_blank');
                }
                
                // Export vectors
                function exportVectors() {
                    window.open('ajax-handlers.php?action=export_data&type=vectors', '_blank');
                }
                
                // Clear all data
                async function clearAllData() {
                    if (!confirm('⚠️ DANGER: This will delete ALL data! This cannot be undone. Continue?')) return;
                    if (!confirm('LAST WARNING: Are you ABSOLUTELY sure?')) return;
                    
                    const response = await fetch('ajax-handlers.php?action=clear_all_data', {
                        method: 'POST'
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('✅ All data cleared. Page will reload.');
                        location.reload();
                    } else {
                        alert('❌ Failed: ' + result.message);
                    }
                }
                
                // Reset settings
                async function resetSettings() {
                    if (!confirm('Reset all settings to default?')) return;
                    
                    const response = await fetch('ajax-handlers.php?action=reset_settings', {
                        method: 'POST'
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('✅ Settings reset. Page will reload.');
                        location.reload();
                    } else {
                        alert('❌ Reset failed: ' + result.message);
                    }
                }
                
                // Save all settings
                async function saveAllSettings() {
                    await saveGeneralSettings();
                    await saveSecuritySettings();
                    await savePerformanceSettings();
                    await saveLearningSettings();
                    await saveVectorSettings();
                    alert('✅ All settings saved!');
                }
                
                // Confidence threshold slider
                const confidenceSlider = document.getElementById('confidence-threshold');
                if (confidenceSlider) {
                    confidenceSlider.addEventListener('input', function() {
                        document.getElementById('confidence-value').textContent = this.value;
                    });
                }
                
                // Load vector stats on page load
                async function loadVectorStats() {
                    const response = await fetch('ajax-handlers.php?action=get_vector_stats');
                    const result = await response.json();
                    
                    if (result.success) {
                        document.getElementById('total-vectors').textContent = result.data.total_vectors.toLocaleString();
                        document.getElementById('index-type').textContent = result.data.index_type.toUpperCase();
                        document.getElementById('vector-dim').textContent = result.data.avg_dimension;
                    }
                }
                
                // Load learning stats
                async function loadLearningStats() {
                    const response = await fetch('ajax-handlers.php?action=get_learning_stats');
                    const result = await response.json();
                    
                    if (result.success) {
                        document.getElementById('training-samples').textContent = result.data.total_samples?.toLocaleString() || '0';
                        document.getElementById('model-accuracy').textContent = (result.data.accuracy * 100).toFixed(1) + '%';
                        document.getElementById('last-training').textContent = result.data.last_training || 'Never';
                    }
                }
                
                // Load backup list
                loadBackupList();
                loadVectorStats();
                loadLearningStats();
            </script>
        </body>
        </html>
        <?php
    }
    
    /**
     * Get provider icon
     */
    private function getProviderIcon($slug) {
        $icons = [
            'openai' => '🤖',
            'anthropic' => '🧠',
            'google-ai' => '🔍',
            'cohere' => '📝',
            'pinecone' => '🌲',
            'qdrant' => '🎯',
            'default' => '🔌'
        ];
        return $icons[$slug] ?? $icons['default'];
    }
    
    /**
     * Check if provider is active
     */
    private function isProviderActive($slug) {
        foreach ($this->api_keys as $key) {
            if ($key['provider'] === $slug && $key['status'] === 'active') {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get API key for provider
     */
    private function getApiKey($slug) {
        foreach ($this->api_keys as $key) {
            if ($key['provider'] === $slug) {
                return $key['key_decrypted'] ?? '';
            }
        }
        return '';
    }
}

// Render settings page
$settings = new APIMaster_Settings();
$settings->render();