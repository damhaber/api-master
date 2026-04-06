<?php
/**
 * API Master - Vector Database Manager
 * 
 * @package APIMaster
 * @subpackage UI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_VectorDB {
    
    private $vectors = [];
    private $stats = [];
    
    public function __construct() {
        $this->loadVectors();
        $this->loadStats();
    }
    
    private function loadVectors() {
        $vectors_file = dirname(dirname(__FILE__)) . '/data/vectors.json';
        if (file_exists($vectors_file)) {
            $this->vectors = json_decode(file_get_contents($vectors_file), true);
        }
    }
    
    private function loadStats() {
        $stats_file = dirname(dirname(__FILE__)) . '/data/vector-stats.json';
        if (file_exists($stats_file)) {
            $this->stats = json_decode(file_get_contents($stats_file), true);
        } else {
            $this->stats = [
                'total_vectors' => 0,
                'short_term' => 0,
                'long_term' => 0,
                'avg_dimension' => 1536,
                'index_type' => 'hnsw',
                'last_optimized' => null
            ];
        }
    }
    
    public function render() {
        $short_term = count(array_filter($this->vectors, function($v) {
            return ($v['memory_type'] ?? 'short_term') === 'short_term';
        }));
        $long_term = count(array_filter($this->vectors, function($v) {
            return ($v['memory_type'] ?? '') === 'long_term';
        }));
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Vector Database Manager</title>
            <link rel="stylesheet" href="style.css">
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <style>
                .vector-container {
                    padding: 20px;
                }
                
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 20px;
                    margin-bottom: 30px;
                }
                
                .vector-stats {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                }
                
                .search-section {
                    background: white;
                    border-radius: 12px;
                    padding: 24px;
                    margin-bottom: 24px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .search-section h3 {
                    margin-bottom: 20px;
                }
                
                .search-box {
                    display: flex;
                    gap: 12px;
                    margin-bottom: 20px;
                }
                
                .search-box input {
                    flex: 1;
                    padding: 12px;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    font-size: 14px;
                }
                
                .search-box button {
                    padding: 12px 24px;
                    background: #4F46E5;
                    color: white;
                    border: none;
                    border-radius: 8px;
                    cursor: pointer;
                }
                
                .results-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                
                .results-table th,
                .results-table td {
                    padding: 12px;
                    text-align: left;
                    border-bottom: 1px solid #e5e7eb;
                }
                
                .results-table th {
                    background: #f9fafb;
                    font-weight: 600;
                }
                
                .similarity-bar {
                    width: 100%;
                    height: 6px;
                    background: #e5e7eb;
                    border-radius: 3px;
                    overflow: hidden;
                }
                
                .similarity-fill {
                    height: 100%;
                    background: #10b981;
                    border-radius: 3px;
                    transition: width 0.3s;
                }
                
                .vector-list {
                    background: white;
                    border-radius: 12px;
                    padding: 24px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .vector-item {
                    padding: 16px;
                    border-bottom: 1px solid #e5e7eb;
                    cursor: pointer;
                }
                
                .vector-item:hover {
                    background: #f9fafb;
                }
                
                .vector-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 8px;
                }
                
                .vector-id {
                    font-family: monospace;
                    font-size: 12px;
                    color: #6b7280;
                }
                
                .vector-type {
                    padding: 2px 8px;
                    border-radius: 12px;
                    font-size: 11px;
                    font-weight: 500;
                }
                
                .type-short {
                    background: #dbeafe;
                    color: #1e40af;
                }
                
                .type-long {
                    background: #d1fae5;
                    color: #065f46;
                }
                
                .vector-preview {
                    font-size: 12px;
                    color: #6b7280;
                    font-family: monospace;
                }
                
                .similarity-score {
                    font-weight: 600;
                    color: #10b981;
                }
                
                @keyframes pulse {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.5; }
                }
                
                .searching {
                    animation: pulse 1s ease-in-out infinite;
                }
            </style>
        </head>
        <body>
            <div class="vector-container">
                <div class="test-header">
                    <h1>🧠 Vector Database Manager</h1>
                    <p>Manage vector embeddings, similarity search, and memory consolidation</p>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card vector-stats">
                        <div class="stat-info">
                            <h3>Total Vectors</h3>
                            <p class="stat-value"><?php echo number_format(count($this->vectors)); ?></p>
                        </div>
                    </div>
                    <div class="stat-card vector-stats">
                        <div class="stat-info">
                            <h3>Short Term Memory</h3>
                            <p class="stat-value"><?php echo number_format($short_term); ?></p>
                        </div>
                    </div>
                    <div class="stat-card vector-stats">
                        <div class="stat-info">
                            <h3>Long Term Memory</h3>
                            <p class="stat-value"><?php echo number_format($long_term); ?></p>
                        </div>
                    </div>
                    <div class="stat-card vector-stats">
                        <div class="stat-info">
                            <h3>Index Type</h3>
                            <p class="stat-value" style="font-size: 20px;"><?php echo strtoupper($this->stats['index_type'] ?? 'HNSW'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Similarity Search Section -->
                <div class="search-section">
                    <h3>🔍 Similarity Search</h3>
                    <div class="search-box">
                        <input type="text" id="search-text" placeholder="Enter text to find similar vectors..." onkeypress="if(event.key==='Enter') searchSimilar()">
                        <select id="search-limit" style="width: auto; padding: 12px;">
                            <option value="5">Top 5</option>
                            <option value="10" selected>Top 10</option>
                            <option value="20">Top 20</option>
                            <option value="50">Top 50</option>
                        </select>
                        <button onclick="searchSimilar()">🔍 Search</button>
                    </div>
                    <div id="search-results" style="display: none;">
                        <h4>Results:</h4>
                        <table class="results-table">
                            <thead>
                                <tr><th>ID</th><th>Similarity</th><th>Type</th><th>Preview</th><th>Created</th></tr>
                            </thead>
                            <tbody id="results-body"></tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Vector List -->
                <div class="vector-list">
                    <h3>📊 Vector Memory Store</h3>
                    <div style="margin-bottom: 16px; display: flex; gap: 10px;">
                        <button class="btn-add-param" onclick="filterVectors('all')">All</button>
                        <button class="btn-add-param" onclick="filterVectors('short_term')">Short Term</button>
                        <button class="btn-add-param" onclick="filterVectors('long_term')">Long Term</button>
                        <button class="btn-add-param" onclick="runConsolidation()">🔄 Run Consolidation</button>
                        <button class="btn-add-param" onclick="optimizeIndex()">⚡ Optimize Index</button>
                        <button class="btn-add-param" onclick="exportVectors()">📤 Export</button>
                    </div>
                    
                    <div id="vector-list-container">
                        <?php if (empty($this->vectors)): ?>
                            <p style="text-align: center; color: #6b7280; padding: 40px;">No vectors found. Add vectors through API calls or learning system.</p>
                        <?php else: ?>
                            <?php foreach (array_slice($this->vectors, 0, 20) as $id => $vector): ?>
                                <div class="vector-item" onclick="viewVectorDetails('<?php echo $id; ?>')">
                                    <div class="vector-header">
                                        <span class="vector-id"><?php echo htmlspecialchars(substr($id, 0, 16)); ?>...</span>
                                        <span class="vector-type <?php echo ($vector['memory_type'] ?? 'short_term') === 'short_term' ? 'type-short' : 'type-long'; ?>">
                                            <?php echo ($vector['memory_type'] ?? 'short_term') === 'short_term' ? 'Short Term' : 'Long Term'; ?>
                                        </span>
                                    </div>
                                    <div class="vector-preview">
                                        <?php 
                                        $preview = $vector['text'] ?? $vector['metadata']['text'] ?? '';
                                        echo htmlspecialchars(substr($preview, 0, 100)) . (strlen($preview) > 100 ? '...' : '');
                                        ?>
                                    </div>
                                    <div style="font-size: 11px; color: #9ca3af; margin-top: 8px;">
                                        Created: <?php echo $vector['created_at'] ?? date('Y-m-d H:i:s'); ?>
                                        <?php if (isset($vector['metadata']['access_count'])): ?>
                                            | Accessed: <?php echo $vector['metadata']['access_count']; ?> times
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <script>
                let currentFilter = 'all';
                
                async function searchSimilar() {
                    const searchText = document.getElementById('search-text').value;
                    const limit = document.getElementById('search-limit').value;
                    
                    if (!searchText.trim()) {
                        showNotification('Please enter search text', 'warning');
                        return;
                    }
                    
                    const resultsDiv = document.getElementById('search-results');
                    const resultsBody = document.getElementById('results-body');
                    const searchBtn = event.target;
                    
                    searchBtn.innerHTML = '<span class="loading-spinner"></span> Searching...';
                    searchBtn.disabled = true;
                    
                    resultsDiv.style.display = 'block';
                    resultsBody.innerHTML = '<tr><td colspan="5" style="text-align: center;">Searching...</td></tr>';
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=search_vector', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ text: searchText, limit: limit })
                        });
                        const result = await response.json();
                        
                        if (result.success && result.data.length > 0) {
                            resultsBody.innerHTML = result.data.map(item => `
                                <tr onclick="viewVectorDetails('${item.id}')" style="cursor: pointer;">
                                    <td><code>${item.id.substring(0, 16)}...</code></td>
                                    <td>
                                        <div class="similarity-bar">
                                            <div class="similarity-fill" style="width: ${(item.similarity * 100)}%"></div>
                                        </div>
                                        <span class="similarity-score">${(item.similarity * 100).toFixed(1)}%</span>
                                    </td>
                                    <td><span class="vector-type ${item.memory_type === 'short_term' ? 'type-short' : 'type-long'}">${item.memory_type || 'short_term'}</span></td>
                                    <td>${item.text ? item.text.substring(0, 50) + '...' : 'No preview'}</td>
                                    <td>${item.created_at || 'N/A'}</td>
                                </tr>
                            `).join('');
                        } else {
                            resultsBody.innerHTML = '<tr><td colspan="5" style="text-align: center;">No similar vectors found</td></tr>';
                        }
                    } catch(error) {
                        resultsBody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: #ef4444;">Search failed: ' + error.message + '</td></tr>';
                    } finally {
                        searchBtn.innerHTML = '🔍 Search';
                        searchBtn.disabled = false;
                    }
                }
                
                function filterVectors(type) {
                    currentFilter = type;
                    const container = document.getElementById('vector-list-container');
                    
                    // In a real implementation, this would fetch filtered vectors from server
                    // For now, just show/hide based on filter
                    const items = document.querySelectorAll('.vector-item');
                    items.forEach(item => {
                        const typeSpan = item.querySelector('.vector-type');
                        if (type === 'all') {
                            item.style.display = 'block';
                        } else if (type === 'short_term' && typeSpan.textContent.includes('Short')) {
                            item.style.display = 'block';
                        } else if (type === 'long_term' && typeSpan.textContent.includes('Long')) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                    
                    // Update button styles
                    document.querySelectorAll('.btn-add-param').forEach(btn => {
                        btn.style.opacity = '0.7';
                    });
                    event.target.style.opacity = '1';
                }
                
                async function runConsolidation() {
                    if (!confirm('Run memory consolidation? This will move old vectors to long-term storage.')) return;
                    
                    const btn = event.target;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<span class="loading-spinner"></span> Consolidating...';
                    btn.disabled = true;
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=run_consolidation', {
                            method: 'POST'
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showNotification(result.message, 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showNotification('Consolidation failed: ' + result.message, 'error');
                        }
                    } catch(error) {
                        showNotification('Error: ' + error.message, 'error');
                    } finally {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                }
                
                async function optimizeIndex() {
                    if (!confirm('Optimize vector index? This may take a few minutes.')) return;
                    
                    const btn = event.target;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<span class="loading-spinner"></span> Optimizing...';
                    btn.disabled = true;
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=optimize_index', {
                            method: 'POST'
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showNotification(result.message, 'success');
                        } else {
                            showNotification('Optimization failed: ' + result.message, 'error');
                        }
                    } catch(error) {
                        showNotification('Error: ' + error.message, 'error');
                    } finally {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                }
                
                function exportVectors() {
                    window.open('ajax-handlers.php?action=export_vectors', '_blank');
                }
                
                function viewVectorDetails(id) {
                    // Show modal with vector details
                    showModal(`Vector ID: ${id}`, 'info');
                }
                
                function showNotification(message, type) {
                    const notification = document.createElement('div');
                    notification.className = `notification notification-${type}`;
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
                
                function showModal(title, content) {
                    const modal = document.createElement('div');
                    modal.className = 'modal active';
                    modal.innerHTML = `
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2>${title}</h2>
                                <span class="modal-close">&times;</span>
                            </div>
                            <div class="modal-body">
                                <p>${content}</p>
                            </div>
                            <div class="modal-footer">
                                <button onclick="this.closest('.modal').remove()">Close</button>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(modal);
                    modal.querySelector('.modal-close').onclick = () => modal.remove();
                }
            </script>
        </body>
        </html>
        <?php
    }
}

$vector_db = new APIMaster_VectorDB();
$vector_db->render();