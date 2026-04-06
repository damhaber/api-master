<?php
/**
 * API Master Logs Viewer
 * 
 * @package APIMaster
 * @subpackage UI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_Logs {
    
    private $logs;
    private $per_page = 50;
    private $current_page = 1;
    
    public function __construct() {
        $this->loadLogs();
        $this->current_page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
    }
    
    private function loadLogs() {
        $logs_file = dirname(dirname(__FILE__)) . '/data/logs.json';
        if (file_exists($logs_file)) {
            $this->logs = json_decode(file_get_contents($logs_file), true);
            // Sort by created_at descending
            usort($this->logs, function($a, $b) {
                return strtotime($b['created_at'] ?? '') - strtotime($a['created_at'] ?? '');
            });
        } else {
            $this->logs = [];
        }
    }
    
    public function render() {
        $total = count($this->logs);
        $total_pages = ceil($total / $this->per_page);
        $offset = ($this->current_page - 1) * $this->per_page;
        $paginated_logs = array_slice($this->logs, $offset, $this->per_page);
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>API Master - Logs</title>
            <link rel="stylesheet" href="style.css">
            <style>
                .logs-container {
                    padding: 20px;
                }
                
                .logs-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 20px;
                    flex-wrap: wrap;
                    gap: 10px;
                }
                
                .filter-bar {
                    display: flex;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                
                .filter-bar input, .filter-bar select {
                    padding: 8px 12px;
                    border: 1px solid #e5e7eb;
                    border-radius: 6px;
                    font-size: 14px;
                }
                
                .btn-clear {
                    padding: 8px 16px;
                    background: #ef4444;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                }
                
                .btn-export {
                    padding: 8px 16px;
                    background: #10b981;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                }
                
                .logs-table {
                    width: 100%;
                    border-collapse: collapse;
                    background: white;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .logs-table th,
                .logs-table td {
                    padding: 12px;
                    text-align: left;
                    border-bottom: 1px solid #e5e7eb;
                }
                
                .logs-table th {
                    background: #f9fafb;
                    font-weight: 600;
                    cursor: pointer;
                }
                
                .logs-table th:hover {
                    background: #f3f4f6;
                }
                
                .logs-table tr:hover {
                    background: #f9fafb;
                }
                
                .status-badge {
                    display: inline-block;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    font-weight: 500;
                }
                
                .status-success {
                    background: #d1fae5;
                    color: #065f46;
                }
                
                .status-error {
                    background: #fee2e2;
                    color: #991b1b;
                }
                
                .status-warning {
                    background: #fed7aa;
                    color: #92400e;
                }
                
                .log-details {
                    display: none;
                    background: #f9fafb;
                    padding: 16px;
                    margin-top: 8px;
                    border-radius: 8px;
                    font-family: monospace;
                    font-size: 12px;
                    overflow-x: auto;
                }
                
                .log-details.active {
                    display: block;
                }
                
                .pagination {
                    display: flex;
                    justify-content: center;
                    gap: 8px;
                    margin-top: 20px;
                }
                
                .pagination a, .pagination span {
                    padding: 8px 12px;
                    border: 1px solid #e5e7eb;
                    border-radius: 6px;
                    text-decoration: none;
                    color: #4F46E5;
                }
                
                .pagination .current {
                    background: #4F46E5;
                    color: white;
                    border-color: #4F46E5;
                }
                
                .expand-icon {
                    cursor: pointer;
                    display: inline-block;
                    width: 20px;
                    text-align: center;
                }
                
                .method-get { color: #10b981; font-weight: 500; }
                .method-post { color: #f59e0b; font-weight: 500; }
                .method-put { color: #3b82f6; font-weight: 500; }
                .method-delete { color: #ef4444; font-weight: 500; }
                
                @media (max-width: 768px) {
                    .logs-table {
                        display: block;
                        overflow-x: auto;
                    }
                    
                    .filter-bar {
                        flex-direction: column;
                    }
                }
            </style>
        </head>
        <body>
            <div class="logs-container">
                <div class="logs-header">
                    <h1>📝 API Request Logs</h1>
                    <div class="filter-bar">
                        <input type="text" id="search-input" placeholder="Search..." onkeyup="filterLogs()">
                        <select id="status-filter" onchange="filterLogs()">
                            <option value="">All Status</option>
                            <option value="success">Success (2xx)</option>
                            <option value="error">Error (4xx, 5xx)</option>
                        </select>
                        <select id="method-filter" onchange="filterLogs()">
                            <option value="">All Methods</option>
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                            <option value="PUT">PUT</option>
                            <option value="DELETE">DELETE</option>
                        </select>
                        <button class="btn-clear" onclick="clearLogs()">🗑️ Clear All</button>
                        <button class="btn-export" onclick="exportLogs()">📥 Export</button>
                    </div>
                </div>
                
                <table class="logs-table" id="logs-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th onclick="sortBy('created_at')">Time ⬍</th>
                            <th onclick="sortBy('method')">Method</th>
                            <th onclick="sortBy('endpoint')">Endpoint</th>
                            <th onclick="sortBy('provider')">Provider</th>
                            <th onclick="sortBy('response_status')">Status</th>
                            <th onclick="sortBy('response_time')">Time</th>
                        </tr>
                    </thead>
                    <tbody id="logs-body">
                        <?php foreach ($paginated_logs as $log): ?>
                            <tr class="log-row" data-id="<?php echo $log['request_id'] ?? uniqid(); ?>">
                                <td class="expand-icon" onclick="toggleDetails('<?php echo $log['request_id'] ?? uniqid(); ?>')">▶</td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'] ?? 'now')); ?></td>
                                <td class="method-<?php echo strtolower($log['method'] ?? 'GET'); ?>"><?php echo $log['method'] ?? 'GET'; ?></td>
                                <td><code><?php echo htmlspecialchars(substr($log['endpoint'] ?? '', 0, 50)); ?></code></td>
                                <td><?php echo htmlspecialchars($log['provider'] ?? 'unknown'); ?></td>
                                <td>
                                    <?php 
                                    $status = $log['response_status'] ?? 0;
                                    $status_class = ($status >= 200 && $status < 300) ? 'success' : (($status >= 400) ? 'error' : 'warning');
                                    ?>
                                    <span class="status-badge status-<?php echo $status_class; ?>"><?php echo $status ?: 'N/A'; ?></span>
                                </td>
                                <td><?php echo isset($log['response_time']) ? number_format($log['response_time'], 2) . 'ms' : '-'; ?></td>
                            </tr>
                            <tr id="details-<?php echo $log['request_id'] ?? uniqid(); ?>" style="display: none;">
                                <td colspan="7">
                                    <div class="log-details">
                                        <strong>Request Headers:</strong>
                                        <pre><?php echo htmlspecialchars(json_encode($log['request_headers'] ?? [], JSON_PRETTY_PRINT)); ?></pre>
                                        <strong>Request Body:</strong>
                                        <pre><?php echo htmlspecialchars(substr($log['request_body'] ?? '', 0, 1000)); ?></pre>
                                        <strong>Response Headers:</strong>
                                        <pre><?php echo htmlspecialchars(json_encode($log['response_headers'] ?? [], JSON_PRETTY_PRINT)); ?></pre>
                                        <strong>Response Body:</strong>
                                        <pre><?php echo htmlspecialchars(substr($log['response_body'] ?? '', 0, 1000)); ?></pre>
                                        <?php if ($log['error_message'] ?? false): ?>
                                            <strong>Error:</strong>
                                            <pre style="color: #ef4444;"><?php echo htmlspecialchars($log['error_message']); ?></pre>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($this->current_page > 1): ?>
                            <a href="?page_num=<?php echo $this->current_page - 1; ?>">← Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $this->current_page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page_num=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($this->current_page < $total_pages): ?>
                            <a href="?page_num=<?php echo $this->current_page + 1; ?>">Next →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <script>
                let currentSort = { column: 'created_at', direction: 'desc' };
                
                function toggleDetails(id) {
                    const detailsRow = document.getElementById(`details-${id}`);
                    const expandIcon = event.target;
                    
                    if (detailsRow.style.display === 'none') {
                        detailsRow.style.display = 'table-row';
                        expandIcon.textContent = '▼';
                    } else {
                        detailsRow.style.display = 'none';
                        expandIcon.textContent = '▶';
                    }
                }
                
                function filterLogs() {
                    const search = document.getElementById('search-input').value.toLowerCase();
                    const statusFilter = document.getElementById('status-filter').value;
                    const methodFilter = document.getElementById('method-filter').value;
                    const rows = document.querySelectorAll('.log-row');
                    
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        const statusCell = row.cells[5];
                        const statusText = statusCell.textContent;
                        const methodCell = row.cells[2];
                        const methodText = methodCell.textContent;
                        
                        let statusMatch = true;
                        if (statusFilter === 'success') {
                            statusMatch = statusText.includes('200') || statusText.includes('201') || statusText.includes('204');
                        } else if (statusFilter === 'error') {
                            statusMatch = statusText.includes('400') || statusText.includes('404') || statusText.includes('500');
                        }
                        
                        let methodMatch = true;
                        if (methodFilter) {
                            methodMatch = methodText === methodFilter;
                        }
                        
                        const searchMatch = text.includes(search);
                        
                        if (searchMatch && statusMatch && methodMatch) {
                            row.style.display = '';
                            const detailsRow = document.getElementById(`details-${row.dataset.id}`);
                            if (detailsRow) detailsRow.style.display = 'none';
                        } else {
                            row.style.display = 'none';
                            const detailsRow = document.getElementById(`details-${row.dataset.id}`);
                            if (detailsRow) detailsRow.style.display = 'none';
                        }
                    });
                }
                
                function sortBy(column) {
                    if (currentSort.column === column) {
                        currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
                    } else {
                        currentSort.column = column;
                        currentSort.direction = 'asc';
                    }
                    
                    const tbody = document.getElementById('logs-body');
                    const rows = Array.from(tbody.querySelectorAll('.log-row'));
                    
                    rows.sort((a, b) => {
                        let aVal = a.cells[getColumnIndex(column)].textContent;
                        let bVal = b.cells[getColumnIndex(column)].textContent;
                        
                        if (column === 'response_time') {
                            aVal = parseFloat(aVal) || 0;
                            bVal = parseFloat(bVal) || 0;
                        } else if (column === 'created_at') {
                            aVal = new Date(aVal);
                            bVal = new Date(bVal);
                        }
                        
                        if (aVal < bVal) return currentSort.direction === 'asc' ? -1 : 1;
                        if (aVal > bVal) return currentSort.direction === 'asc' ? 1 : -1;
                        return 0;
                    });
                    
                    rows.forEach(row => tbody.appendChild(row));
                }
                
                function getColumnIndex(column) {
                    const indexes = { created_at: 1, method: 2, endpoint: 3, provider: 4, response_status: 5, response_time: 6 };
                    return indexes[column] || 1;
                }
                
                async function clearLogs() {
                    if (confirm('Are you sure you want to clear all logs? This cannot be undone.')) {
                        const response = await fetch('ajax-handlers.php?action=clear_logs', { method: 'POST' });
                        const result = await response.json();
                        if (result.success) {
                            location.reload();
                        } else {
                            alert('Failed to clear logs: ' + result.message);
                        }
                    }
                }
                
                async function exportLogs() {
                    window.open('ajax-handlers.php?action=export_logs', '_blank');
                }
            </script>
        </body>
        </html>
        <?php
    }
}

$logs = new APIMaster_Logs();
$logs->render();