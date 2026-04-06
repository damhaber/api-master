<?php
/**
 * API Master Admin Menu
 * 
 * @package APIMaster
 * @subpackage UI
 * @since 1.0.0
 * 
 * IMPORTANT: Standalone admin menu - NO WordPress dependencies!
 * Pure HTML/CSS/JS sidebar navigation
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_AdminMenu {
    
    private $current_page;
    private $menu_items = [];
    
    public function __construct() {
        $this->current_page = $_GET['page'] ?? 'dashboard';
        $this->initializeMenu();
    }
    
    private function initializeMenu() {
        $this->menu_items = [
            'dashboard' => [
                'icon' => '📊',
                'title' => 'Dashboard',
                'file' => 'dashboard.php',
                'description' => 'Main overview and statistics'
            ],
            'api-test' => [
                'icon' => '🧪',
                'title' => 'API Test',
                'file' => 'api-test.php',
                'description' => 'Test API endpoints'
            ],
            'api-settings' => [
                'icon' => '⚙️',
                'title' => 'API Settings',
                'file' => 'api-settings.php',
                'description' => 'Configure API providers'
            ],
            'analytics' => [
                'icon' => '📈',
                'title' => 'Analytics',
                'file' => 'analytics.php',
                'description' => 'View usage statistics'
            ],
            'logs' => [
                'icon' => '📝',
                'title' => 'Logs',
                'file' => 'logs.php',
                'description' => 'View API request logs'
            ],
            'vector-db' => [
                'icon' => '🧠',
                'title' => 'Vector DB',
                'file' => 'vector-db.php',
                'description' => 'Manage vector database'
            ],
            'learning' => [
                'icon' => '🤖',
                'title' => 'Learning',
                'file' => 'learning.php',
                'description' => 'ML training and feedback'
            ],
            'webhooks' => [
                'icon' => '🔗',
                'title' => 'Webhooks',
                'file' => 'webhooks.php',
                'description' => 'Configure webhook endpoints'
            ],
            'queue' => [
                'icon' => '📬',
                'title' => 'Queue',
                'file' => 'queue.php',
                'description' => 'Manage job queue'
            ],
            'backup' => [
                'icon' => '💾',
                'title' => 'Backup',
                'file' => 'backup.php',
                'description' => 'Backup and restore'
            ]
        ];
    }
    
    public function render() {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>API Master - <?php echo $this->menu_items[$this->current_page]['title'] ?? 'Admin'; ?></title>
            <link rel="stylesheet" href="style.css">
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                }
                
                .apimaster-wrapper {
                    display: flex;
                    min-height: 100vh;
                }
                
                /* Sidebar */
                .apimaster-sidebar {
                    width: 280px;
                    background: linear-gradient(180deg, #1e1e2f 0%, #1a1a2e 100%);
                    color: white;
                    display: flex;
                    flex-direction: column;
                    position: fixed;
                    height: 100vh;
                    overflow-y: auto;
                    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
                }
                
                .sidebar-header {
                    padding: 24px;
                    border-bottom: 1px solid rgba(255,255,255,0.1);
                    text-align: center;
                }
                
                .sidebar-header h2 {
                    font-size: 20px;
                    margin-bottom: 4px;
                }
                
                .sidebar-header .version {
                    font-size: 12px;
                    color: rgba(255,255,255,0.6);
                }
                
                .sidebar-menu {
                    flex: 1;
                    padding: 20px 0;
                }
                
                .menu-item {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    padding: 12px 24px;
                    color: rgba(255,255,255,0.8);
                    text-decoration: none;
                    transition: all 0.3s;
                    cursor: pointer;
                }
                
                .menu-item:hover {
                    background: rgba(255,255,255,0.1);
                    color: white;
                }
                
                .menu-item.active {
                    background: linear-gradient(90deg, #4F46E5 0%, #7C3AED 100%);
                    color: white;
                    border-left: 3px solid white;
                }
                
                .menu-icon {
                    font-size: 20px;
                    width: 32px;
                }
                
                .menu-title {
                    flex: 1;
                    font-size: 14px;
                    font-weight: 500;
                }
                
                .menu-badge {
                    background: #ef4444;
                    color: white;
                    font-size: 10px;
                    padding: 2px 6px;
                    border-radius: 10px;
                }
                
                .sidebar-footer {
                    padding: 20px 24px;
                    border-top: 1px solid rgba(255,255,255,0.1);
                    font-size: 12px;
                    color: rgba(255,255,255,0.6);
                }
                
                /* Main Content */
                .apimaster-main {
                    flex: 1;
                    margin-left: 280px;
                    padding: 20px;
                }
                
                /* Top Bar */
                .top-bar {
                    background: white;
                    border-radius: 12px;
                    padding: 16px 24px;
                    margin-bottom: 20px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .page-title h1 {
                    font-size: 24px;
                    color: #1f2937;
                    margin-bottom: 4px;
                }
                
                .page-title p {
                    font-size: 14px;
                    color: #6b7280;
                }
                
                .user-info {
                    display: flex;
                    align-items: center;
                    gap: 16px;
                }
                
                .time-display {
                    font-size: 14px;
                    color: #6b7280;
                }
                
                .refresh-btn {
                    padding: 8px 16px;
                    background: #4F46E5;
                    color: white;
                    border: none;
                    border-radius: 8px;
                    cursor: pointer;
                    font-size: 14px;
                }
                
                .refresh-btn:hover {
                    background: #4338CA;
                }
                
                /* Content Area */
                .content-area {
                    background: white;
                    border-radius: 12px;
                    padding: 24px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    min-height: calc(100vh - 140px);
                }
                
                .iframe-container {
                    width: 100%;
                    height: calc(100vh - 180px);
                    border: none;
                }
                
                /* Mobile Responsive */
                @media (max-width: 768px) {
                    .apimaster-sidebar {
                        transform: translateX(-100%);
                        transition: transform 0.3s;
                        z-index: 1000;
                    }
                    
                    .apimaster-sidebar.open {
                        transform: translateX(0);
                    }
                    
                    .apimaster-main {
                        margin-left: 0;
                    }
                    
                    .menu-toggle {
                        display: block;
                        position: fixed;
                        top: 20px;
                        left: 20px;
                        z-index: 1001;
                        background: #4F46E5;
                        color: white;
                        border: none;
                        padding: 10px;
                        border-radius: 8px;
                        cursor: pointer;
                    }
                    
                    .top-bar {
                        margin-top: 50px;
                    }
                }
                
                @media (min-width: 769px) {
                    .menu-toggle {
                        display: none;
                    }
                }
                
                /* Scrollbar */
                ::-webkit-scrollbar {
                    width: 8px;
                }
                
                ::-webkit-scrollbar-track {
                    background: #f1f1f1;
                }
                
                ::-webkit-scrollbar-thumb {
                    background: #888;
                    border-radius: 4px;
                }
                
                ::-webkit-scrollbar-thumb:hover {
                    background: #555;
                }
            </style>
        </head>
        <body>
            <button class="menu-toggle" onclick="toggleSidebar()">☰ Menu</button>
            
            <div class="apimaster-wrapper">
                <!-- Sidebar -->
                <div class="apimaster-sidebar" id="sidebar">
                    <div class="sidebar-header">
                        <h2>🚀 API Master</h2>
                        <div class="version">Version 1.1.0</div>
                    </div>
                    
                    <div class="sidebar-menu">
                        <?php foreach ($this->menu_items as $slug => $item): ?>
                            <a href="?page=<?php echo $slug; ?>" class="menu-item <?php echo $this->current_page === $slug ? 'active' : ''; ?>" data-page="<?php echo $slug; ?>">
                                <span class="menu-icon"><?php echo $item['icon']; ?></span>
                                <span class="menu-title"><?php echo $item['title']; ?></span>
                                <?php if ($this->getBadgeCount($slug) > 0): ?>
                                    <span class="menu-badge"><?php echo $this->getBadgeCount($slug); ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="sidebar-footer">
                        <div>© 2024 API Master</div>
                        <div>Standalone Module</div>
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="apimaster-main">
                    <div class="top-bar">
                        <div class="page-title">
                            <h1><?php echo $this->menu_items[$this->current_page]['title'] ?? 'Dashboard'; ?></h1>
                            <p><?php echo $this->menu_items[$this->current_page]['description'] ?? 'Welcome to API Master'; ?></p>
                        </div>
                        <div class="user-info">
                            <div class="time-display" id="current-time"></div>
                            <button class="refresh-btn" onclick="refreshContent()">🔄 Refresh</button>
                        </div>
                    </div>
                    
                    <div class="content-area">
                        <iframe id="content-frame" class="iframe-container" src="<?php echo $this->getPageFile(); ?>" frameborder="0"></iframe>
                    </div>
                </div>
            </div>
            
            <script>
                // Update time display
                function updateTime() {
                    const now = new Date();
                    const timeString = now.toLocaleString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                    const timeElement = document.getElementById('current-time');
                    if (timeElement) {
                        timeElement.textContent = timeString;
                    }
                }
                
                // Toggle sidebar on mobile
                function toggleSidebar() {
                    const sidebar = document.getElementById('sidebar');
                    sidebar.classList.toggle('open');
                }
                
                // Refresh content
                function refreshContent() {
                    const iframe = document.getElementById('content-frame');
                    if (iframe) {
                        iframe.src = iframe.src;
                    }
                }
                
                // Handle menu clicks
                document.querySelectorAll('.menu-item').forEach(item => {
                    item.addEventListener('click', function(e) {
                        const page = this.dataset.page;
                        if (page) {
                            // Close sidebar on mobile after click
                            if (window.innerWidth <= 768) {
                                document.getElementById('sidebar').classList.remove('open');
                            }
                        }
                    });
                });
                
                // Auto refresh every 60 seconds
                setInterval(() => {
                    refreshContent();
                }, 60000);
                
                // Update time every second
                setInterval(updateTime, 1000);
                updateTime();
                
                // Handle iframe load
                document.getElementById('content-frame').addEventListener('load', function() {
                    // Update page title from iframe
                    try {
                        const iframeTitle = this.contentDocument.title;
                        if (iframeTitle && iframeTitle !== 'API Master - Admin') {
                            document.title = iframeTitle;
                        }
                    } catch(e) {
                        // Cross-origin error, ignore
                    }
                });
                
                // Keyboard shortcuts
                document.addEventListener('keydown', function(e) {
                    // Ctrl+R refresh
                    if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                        e.preventDefault();
                        refreshContent();
                    }
                    // Escape close sidebar
                    if (e.key === 'Escape') {
                        document.getElementById('sidebar').classList.remove('open');
                    }
                });
            </script>
        </body>
        </html>
        <?php
    }
    
    private function getPageFile() {
        $page = $this->current_page;
        $file = $this->menu_items[$page]['file'] ?? 'dashboard.php';
        return $file;
    }
    
    private function getBadgeCount($slug) {
        // Return notification count for menu badges
        switch ($slug) {
            case 'logs':
                $logs_file = dirname(dirname(__FILE__)) . '/data/logs.json';
                if (file_exists($logs_file)) {
                    $logs = json_decode(file_get_contents($logs_file), true);
                    $errors = count(array_filter($logs, function($log) {
                        return isset($log['response_status']) && $log['response_status'] >= 400;
                    }));
                    return $errors > 0 ? $errors : 0;
                }
                break;
            case 'queue':
                $queue_file = dirname(dirname(__FILE__)) . '/data/queue-jobs.json';
                if (file_exists($queue_file)) {
                    $queue = json_decode(file_get_contents($queue_file), true);
                    return count($queue);
                }
                break;
        }
        return 0;
    }
}

$menu = new APIMaster_AdminMenu();
$menu->render();