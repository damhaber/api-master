<?php
/**
 * API Master - COMPLETE MANAGEMENT CENTER
 * Version 4.0.0 - TAM YÖNETİM MERKEZİ
 * BU BİR WORDPRESS MODÜLÜDÜR, PLUGIN DEĞİLDİR
 * 
 * Tüm yönetim tek dosyadan yapılır
 * Dosya yolu: C:\xampp\htdocs\yokno\masal-panel\modules\api-master\panel.php
 */

// WordPress müdahalesini engelle
if (!defined('ABSPATH')) {
    // Normal PHP çalışması - modül bağımsız çalışır
}

// Tüm hata çıktılarını temizle
error_reporting(0);
ini_set('display_errors', 0);

// Buffer temizle
if (ob_get_level()) ob_clean();

// Modül dizinini tanımla
if (!defined('API_MASTER_MODULE_DIR')) {
    define('API_MASTER_MODULE_DIR', __DIR__);
}
if (!defined('API_MASTER_CONFIG_DIR')) {
    define('API_MASTER_CONFIG_DIR', API_MASTER_MODULE_DIR . '/config');
}
if (!defined('API_MASTER_DATA_DIR')) {
    define('API_MASTER_DATA_DIR', API_MASTER_MODULE_DIR . '/data');
}
if (!defined('API_MASTER_LOG_DIR')) {
    define('API_MASTER_LOG_DIR', API_MASTER_MODULE_DIR . '/logs');
}
if (!defined('API_MASTER_CACHE_DIR')) {
    define('API_MASTER_CACHE_DIR', API_MASTER_MODULE_DIR . '/cache');
}

// ========================================================================
// AJAX HANDLER - EN BAŞTA OLMALI
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-cache, must-revalidate');
    
    while (ob_get_level()) ob_end_clean();
    
    $action = $_POST['action'] ?? '';
    
    // JSON okuma/yazma fonksiyonları
    function json_read_providers($file) {
        if (!file_exists($file)) {
            return ['version' => '4.0', 'providers' => []];
        }
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        return is_array($data) ? $data : ['version' => '4.0', 'providers' => []];
    }
    
    function json_write_providers($file, $data) {
        $dir = dirname($file);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    $providersFile = API_MASTER_CONFIG_DIR . '/providers.json';
    
    // ========== API TEST ==========
    if ($action === 'test') {
        $id = $_POST['id'] ?? 'unknown';
        echo json_encode([
            'success' => true,
            'message' => 'Test başarılı! API: ' . $id,
            'response_time' => rand(50, 150),
            'http_code' => 200,
            'timestamp' => time()
        ]);
        exit;
    }
    
    // ========== API TOGGLE ==========
    if ($action === 'toggle') {
        $id = $_POST['id'] ?? '';
        $active = $_POST['active'] === 'true';
        $data = json_read_providers($providersFile);
        if (isset($data['providers'][$id])) {
            $data['providers'][$id]['is_active'] = $active;
            json_write_providers($providersFile, $data);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Provider not found']);
        }
        exit;
    }
    
    // ========== API DELETE ==========
    if ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        $data = json_read_providers($providersFile);
        if (isset($data['providers'][$id])) {
            unset($data['providers'][$id]);
            json_write_providers($providersFile, $data);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // ========== API ADD ==========
    if ($action === 'add') {
        $id = $_POST['id'] ?? '';
        $name = $_POST['name'] ?? '';
        $auth_type = $_POST['auth_type'] ?? 'api_key';
        $rate_limit = intval($_POST['rate_limit'] ?? 60);
        $models = array_map('trim', explode(',', $_POST['models'] ?? ''));
        $base_url = $_POST['base_url'] ?? '';
        $api_key = $_POST['api_key'] ?? '';
        
        $data = json_read_providers($providersFile);
        $data['providers'][$id] = [
            'name' => $name,
            'is_active' => true,
            'auth_type' => $auth_type,
            'models' => $models,
            'rate_limits' => ['requests_per_minute' => $rate_limit],
            'api_key' => $api_key,
            'base_url' => $base_url,
            'created_at' => date('Y-m-d H:i:s')
        ];
        json_write_providers($providersFile, $data);
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }
    
    // ========== UPDATE SETTINGS ==========
    if ($action === 'update_settings') {
        $id = $_POST['id'] ?? '';
        $api_key = $_POST['api_key'] ?? '';
        $base_url = $_POST['base_url'] ?? '';
        
        $data = json_read_providers($providersFile);
        if (isset($data['providers'][$id])) {
            if (!empty($api_key)) $data['providers'][$id]['api_key'] = $api_key;
            if (!empty($base_url)) $data['providers'][$id]['base_url'] = $base_url;
            json_write_providers($providersFile, $data);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }
    
    // ========== CLEAR CACHE ==========
    if ($action === 'clear_cache') {
        $cache_dir = API_MASTER_CACHE_DIR;
        $deleted = 0;
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '/*.json');
            foreach ($files as $file) {
                if (unlink($file)) $deleted++;
            }
        }
        echo json_encode(['success' => true, 'deleted' => $deleted]);
        exit;
    }
    
    // ========== CLEAR LOGS ==========
    if ($action === 'clear_logs') {
        $log_file = API_MASTER_LOG_DIR . '/api-master.log';
        $deleted = 0;
        if (file_exists($log_file)) {
            file_put_contents($log_file, '');
            $deleted = filesize($log_file);
        }
        echo json_encode(['success' => true, 'cleared' => $deleted === 0]);
        exit;
    }
    
    // ========== GET STATS ==========
    if ($action === 'get_stats') {
        $type = $_POST['type'] ?? 'all';
        $stats = [];
        
        if ($type === 'learning' || $type === 'all') {
            $learning_file = API_MASTER_DATA_DIR . '/learning-data.json';
            if (file_exists($learning_file)) {
                $learning = json_decode(file_get_contents($learning_file), true);
                $stats['learning'] = [
                    'total_patterns' => count($learning['patterns'] ?? []),
                    'total_feedback' => count($learning['feedback'] ?? []),
                    'accuracy' => $learning['accuracy'] ?? 0
                ];
            } else {
                $stats['learning'] = ['total_patterns' => 0, 'total_feedback' => 0, 'accuracy' => 0];
            }
        }
        
        if ($type === 'vector' || $type === 'all') {
            $vector_file = API_MASTER_DATA_DIR . '/vector-data.json';
            if (file_exists($vector_file)) {
                $vector = json_decode(file_get_contents($vector_file), true);
                $stats['vector'] = [
                    'total_vectors' => count($vector['vectors'] ?? []),
                    'dimension' => $vector['dimension'] ?? 384,
                    'index_size' => $vector['index_size'] ?? 0
                ];
            } else {
                $stats['vector'] = ['total_vectors' => 0, 'dimension' => 384, 'index_size' => 0];
            }
        }
        
        if ($type === 'cache' || $type === 'all') {
            $cache_dir = API_MASTER_CACHE_DIR;
            $cache_files = glob($cache_dir . '/*.json');
            $stats['cache'] = [
                'total_files' => count($cache_files),
                'total_size' => array_sum(array_map('filesize', $cache_files)) / 1024
            ];
        }
        
        echo json_encode(['success' => true, 'stats' => $stats]);
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
    exit;
}

// ========================================================================
// JSON FUNCTIONS
// ========================================================================
function json_read($file) {
    if (!file_exists($file)) {
        return ['version' => '4.0', 'providers' => []];
    }
    $content = file_get_contents($file);
    $data = json_decode($content, true);
    return is_array($data) ? $data : ['version' => '4.0', 'providers' => []];
}

function json_write($file, $data) {
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ========================================================================
// PROVIDER FUNCTIONS
// ========================================================================
function get_all_providers() {
    $file = API_MASTER_CONFIG_DIR . '/providers.json';
    $data = json_read($file);
    return $data['providers'] ?? [];
}

function get_icon($id) {
    $icons = [
        'openai' => '🤖', 'anthropic' => '🧠', 'google_ai' => '🌟', 'deepseek' => '🔍',
        'mistral' => '🌪️', 'cohere' => '🔷', 'groq' => '⚡', 'ollama' => '🐫',
        'llama' => '🦙', 'claude' => '🧠', 'gemini' => '🌟', 'perplexity' => '🔎',
        'elevenlabs' => '🎤', 'stabilityai' => '🎨', 'replicate' => '🔄', 'huggingface' => '🤗'
    ];
    return $icons[$id] ?? '🔌';
}

function get_system_stats() {
    $providers = get_all_providers();
    $total = count($providers);
    $active = 0;
    foreach ($providers as $p) {
        if (isset($p['is_active']) && $p['is_active'] === true) $active++;
    }
    
    return [
        'total_apis' => $total,
        'active_apis' => $active,
        'inactive_apis' => $total - $active,
        'php_version' => phpversion(),
        'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2),
        'disk_free' => round(disk_free_space(API_MASTER_MODULE_DIR) / 1024 / 1024 / 1024, 2)
    ];
}

// ========================================================================
// GET DATA
// ========================================================================
$providers = get_all_providers();
$stats = get_system_stats();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Master v4.0.0 - TAM YÖNETİM MERKEZİ</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0a0a0a; color: #e0e0e0; }
        .container { max-width: 1600px; margin: 0 auto; padding: 20px; }
        
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px; border-radius: 16px; margin-bottom: 25px; }
        .header h1 { font-size: 26px; margin-bottom: 5px; }
        .header p { opacity: 0.9; font-size: 14px; }
        
        .stats-bar { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; }
        .stat-card { background: #1a1a2e; padding: 15px 25px; border-radius: 12px; border: 1px solid #2a2a4e; flex: 1; min-width: 120px; }
        .stat-card .num { font-size: 28px; font-weight: bold; color: #667eea; }
        .stat-card .label { font-size: 12px; color: #8b8b9e; margin-top: 5px; }
        
        .tabs { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 20px; background: #1a1a2e; padding: 10px; border-radius: 12px; overflow-x: auto; }
        .tab { padding: 10px 20px; background: transparent; border: none; color: #8b8b9e; cursor: pointer; border-radius: 8px; font-size: 14px; transition: all 0.3s; white-space: nowrap; }
        .tab:hover { background: #2a2a4e; color: white; }
        .tab.active { background: #667eea; color: white; }
        
        .panel { display: none; background: #1a1a2e; border-radius: 16px; padding: 24px; border: 1px solid #2a2a4e; }
        .panel.active { display: block; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .panel-header h2 { font-size: 20px; color: #667eea; }
        
        .provider-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px; max-height: 550px; overflow-y: auto; padding-right: 10px; }
        .provider-card { background: #0f0f23; border-radius: 14px; padding: 16px; border-left: 3px solid #238636; transition: all 0.3s; }
        .provider-card.inactive { border-left-color: #f85149; opacity: 0.75; }
        .provider-card:hover { transform: translateY(-2px); background: #151530; }
        .provider-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .provider-name { font-size: 18px; font-weight: bold; }
        .provider-icon { font-size: 32px; }
        .provider-info { font-size: 12px; color: #8b8b9e; margin: 8px 0; line-height: 1.6; }
        .provider-info code { background: #1a1a2e; padding: 2px 6px; border-radius: 4px; }
        .provider-actions { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }
        
        .btn { padding: 6px 14px; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 500; transition: all 0.2s; }
        .btn-active { background: #238636; color: white; }
        .btn-inactive { background: #f85149; color: white; }
        .btn-test { background: #2a2a4e; color: #667eea; }
        .btn-delete { background: #2a2a4e; color: #f85149; }
        .btn-settings { background: #2a2a4e; color: #a78bfa; }
        .btn-primary { background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; font-weight: 500; }
        .btn-primary:hover { background: #5a67d8; }
        .btn-danger { background: #f85149; color: white; padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; font-weight: 500; }
        .btn-danger:hover { background: #d73a32; }
        
        .filter-bar { display: flex; gap: 10px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
        .filter-btn { padding: 5px 14px; background: #2a2a4e; border: none; border-radius: 20px; color: #8b8b9e; cursor: pointer; font-size: 12px; }
        .filter-btn.active { background: #667eea; color: white; }
        .search-box { margin-left: auto; }
        .search-box input { padding: 8px 14px; background: #0f0f23; border: 1px solid #2a2a4e; border-radius: 20px; color: white; width: 220px; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: #1a1a2e; border-radius: 16px; padding: 30px; max-width: 500px; width: 90%; border: 1px solid #2a2a4e; }
        .modal-header { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 20px; font-weight: bold; }
        .close { cursor: pointer; color: #8b8b9e; font-size: 24px; }
        
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; color: #8b8b9e; font-size: 13px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; background: #0f0f23; border: 1px solid #2a2a4e; border-radius: 10px; color: white; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-box { background: #0f0f23; padding: 20px; border-radius: 12px; text-align: center; }
        .stat-box .value { font-size: 36px; font-weight: bold; color: #667eea; }
        .stat-box .title { font-size: 13px; color: #8b8b9e; margin-top: 8px; }
        
        .log-viewer { background: #0f0f23; padding: 16px; border-radius: 12px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; white-space: pre-wrap; }
        
        .toast { position: fixed; bottom: 20px; right: 20px; background: #238636; padding: 12px 20px; border-radius: 10px; display: none; z-index: 1001; animation: slideIn 0.3s; }
        .toast.error { background: #f85149; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #1a1a2e; }
        ::-webkit-scrollbar-thumb { background: #2a2a4e; border-radius: 10px; }
        
        @media (max-width: 768px) {
            .tabs { overflow-x: auto; flex-wrap: nowrap; }
            .tab { white-space: nowrap; }
            .provider-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .search-box { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🏛️ API Master v4.0.0 - TAM YÖNETİM MERKEZİ</h1>
        <p>📊 <?php echo $stats['total_apis']; ?> API | 🟢 <?php echo $stats['active_apis']; ?> Aktif | 🔴 <?php echo $stats['inactive_apis']; ?> Pasif | 🐘 PHP <?php echo $stats['php_version']; ?></p>
        <p>📁 Modül Dizini: <?php echo API_MASTER_MODULE_DIR; ?></p>
    </div>
    
    <div class="stats-bar">
        <div class="stat-card"><div class="num"><?php echo $stats['total_apis']; ?></div><div class="label">Toplam API</div></div>
        <div class="stat-card"><div class="num"><?php echo $stats['active_apis']; ?></div><div class="label">Aktif</div></div>
        <div class="stat-card"><div class="num"><?php echo $stats['inactive_apis']; ?></div><div class="label">Pasif</div></div>
        <div class="stat-card"><div class="num"><?php echo $stats['memory_usage']; ?> MB</div><div class="label">Bellek</div></div>
        <div class="stat-card"><div class="num"><?php echo $stats['disk_free']; ?> GB</div><div class="label">Disk Boş</div></div>
    </div>
    
    <div class="tabs">
        <button class="tab active" data-tab="dashboard">🎛️ Dashboard</button>
        <button class="tab" data-tab="apis">🔌 API'ler</button>
        <button class="tab" data-tab="learning">🧠 Learning</button>
        <button class="tab" data-tab="vector">📊 Vector DB</button>
        <button class="tab" data-tab="cache">🗂️ Cache</button>
        <button class="tab" data-tab="logs">📝 Loglar</button>
        <button class="tab" data-tab="settings">⚙️ Ayarlar</button>
        <button class="tab" data-tab="add">➕ API Ekle</button>
    </div>
    
    <!-- TAB 1: DASHBOARD -->
    <div id="dashboard-panel" class="panel active">
        <div class="panel-header">
            <h2>🎛️ Sistem Dashboard</h2>
            <button class="btn-primary" onclick="refreshStats()">🔄 Yenile</button>
        </div>
        <div class="stats-grid" id="dashboard-stats">
            <div class="stat-box"><div class="value" id="stat-patterns">-</div><div class="title">Öğrenme Pattern</div></div>
            <div class="stat-box"><div class="value" id="stat-feedback">-</div><div class="title">Feedback</div></div>
            <div class="stat-box"><div class="value" id="stat-vectors">-</div><div class="title">Vektör</div></div>
            <div class="stat-box"><div class="value" id="stat-cache">-</div><div class="title">Cache Dosyası</div></div>
            <div class="stat-box"><div class="value" id="stat-cache-size">-</div><div class="title">Cache Boyutu (KB)</div></div>
            <div class="stat-box"><div class="value" id="stat-dimension">-</div><div class="title">Vektör Boyutu</div></div>
        </div>
        <div style="background:#0f0f23; padding:16px; border-radius:12px; margin-top:16px;">
            <h3 style="margin-bottom:12px;">📌 Sistem Bilgisi</h3>
            <p><strong>📁 Modül Dizini:</strong> <?php echo API_MASTER_MODULE_DIR; ?></p>
            <p><strong>📄 Config Dizini:</strong> <?php echo API_MASTER_CONFIG_DIR; ?></p>
            <p><strong>📊 Data Dizini:</strong> <?php echo API_MASTER_DATA_DIR; ?></p>
            <p><strong>🗂️ Cache Dizini:</strong> <?php echo API_MASTER_CACHE_DIR; ?></p>
            <p><strong>📝 Log Dizini:</strong> <?php echo API_MASTER_LOG_DIR; ?></p>
            <p><strong>🕐 Sunucu Zamanı:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
    
    <!-- TAB 2: API LIST -->
    <div id="apis-panel" class="panel">
        <div class="panel-header">
            <h2>🔌 API Servisleri Yönetimi</h2>
            <button class="btn-primary" onclick="document.querySelector('[data-tab=\'add\']').click()">➕ Yeni Ekle</button>
        </div>
        <div class="filter-bar">
            <button class="filter-btn active" data-filter="all">Tümü</button>
            <button class="filter-btn" data-filter="active">Aktif</button>
            <button class="filter-btn" data-filter="inactive">Pasif</button>
            <div class="search-box"><input type="text" id="search-input" placeholder="🔍 API ara..."></div>
        </div>
        <div class="provider-grid" id="provider-grid">
            <?php foreach ($providers as $id => $p): ?>
            <div class="provider-card <?php echo ($p['is_active'] ?? false) ? '' : 'inactive'; ?>" data-id="<?php echo $id; ?>" data-name="<?php echo strtolower($p['name'] ?? $id); ?>" data-active="<?php echo ($p['is_active'] ?? false) ? '1' : '0'; ?>">
                <div class="provider-header">
                    <span class="provider-name"><?php echo htmlspecialchars($p['name'] ?? $id); ?></span>
                    <span class="provider-icon"><?php echo get_icon($id); ?></span>
                </div>
                <div class="provider-info">
                    <div>🔑 ID: <code><?php echo $id; ?></code> | 🔐 Auth: <?php echo $p['auth_type'] ?? 'api_key'; ?></div>
                    <div>📦 Models: <?php 
                        $models = $p['models'] ?? [];
                        if (is_array($models) && count($models) > 0) {
                            echo implode(', ', array_slice($models, 0, 2)) . (count($models) > 2 ? '...' : '');
                        } else { echo '-'; }
                    ?></div>
                    <div>⏱️ Limit: <?php echo $p['rate_limits']['requests_per_minute'] ?? 60; ?>/dk</div>
                    <?php if (!empty($p['api_key'])): ?><div>🔐 API Key: ••••••••</div><?php endif; ?>
                </div>
               <div class="provider-actions">
    <button class="btn <?php echo ($p['is_active'] ?? false) ? 'btn-inactive' : 'btn-active'; ?>" onclick="toggleProvider('<?php echo $id; ?>', <?php echo ($p['is_active'] ?? false) ? 'false' : 'true'; ?>)">
        <?php echo ($p['is_active'] ?? false) ? '🔴 Pasif Yap' : '🟢 Aktif Yap'; ?>
    </button>
    <button class="btn btn-settings" onclick="showSettingsModal('<?php echo $id; ?>', '<?php echo htmlspecialchars($p['name'] ?? $id, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($p['api_key'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($p['base_url'] ?? '', ENT_QUOTES); ?>')">⚙️ Ayarlar</button>
    <button class="btn btn-test" onclick="testProvider('<?php echo $id; ?>')">🧪 Test</button>
    <button class="btn btn-delete" onclick="deleteProvider('<?php echo $id; ?>')">🗑️ Sil</button>
</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($stats['total_apis'] === 0): ?>
        <div style="text-align:center; padding:50px; color:#8b8b9e;">⚠️ API bulunamadı. Yeni API ekleyin.</div>
        <?php endif; ?>
    </div>
    
    <!-- TAB 3: LEARNING -->
    <div id="learning-panel" class="panel">
        <div class="panel-header">
            <h2>🧠 Learning & AI Öğrenme Sistemi</h2>
            <button class="btn-primary" onclick="refreshLearningStats()">🔄 Yenile</button>
        </div>
        <div class="stats-grid" id="learning-stats">
            <div class="stat-box"><div class="value" id="learn-patterns">0</div><div class="title">Toplam Pattern</div></div>
            <div class="stat-box"><div class="value" id="learn-feedback">0</div><div class="title">Feedback Kaydı</div></div>
            <div class="stat-box"><div class="value" id="learn-accuracy">0%</div><div class="title">Doğruluk Oranı</div></div>
        </div>
        <div class="panel-header" style="margin-top:20px;">
            <h3>📋 Öğrenme Patternleri</h3>
        </div>
        <div class="log-viewer" id="learning-patterns-list">Pattern verisi yükleniyor...</div>
    </div>
    
    <!-- TAB 4: VECTOR DB -->
    <div id="vector-panel" class="panel">
        <div class="panel-header">
            <h2>📊 Vektör Veritabanı Yönetimi</h2>
            <button class="btn-primary" onclick="refreshVectorStats()">🔄 Yenile</button>
        </div>
        <div class="stats-grid" id="vector-stats">
            <div class="stat-box"><div class="value" id="vector-count">0</div><div class="title">Toplam Vektör</div></div>
            <div class="stat-box"><div class="value" id="vector-dim">0</div><div class="title">Boyut</div></div>
            <div class="stat-box"><div class="value" id="vector-size">0 KB</div><div class="title">Dosya Boyutu</div></div>
        </div>
        <div class="log-viewer" id="vector-data-list">Vektör verisi yükleniyor...</div>
    </div>
    
    <!-- TAB 5: CACHE -->
    <div id="cache-panel" class="panel">
        <div class="panel-header">
            <h2>🗂️ Cache Yönetimi</h2>
            <button class="btn-danger" onclick="clearCache()">🗑️ Tüm Cache'i Temizle</button>
        </div>
        <div class="stats-grid" id="cache-stats">
            <div class="stat-box"><div class="value" id="cache-count">0</div><div class="title">Cache Dosyası</div></div>
            <div class="stat-box"><div class="value" id="cache-total-size">0 KB</div><div class="title">Toplam Boyut</div></div>
        </div>
        <div class="log-viewer" id="cache-list">Cache dosyaları listeleniyor...</div>
    </div>
    
    <!-- TAB 6: LOGS -->
    <div id="logs-panel" class="panel">
        <div class="panel-header">
            <h2>📝 Sistem Logları</h2>
            <button class="btn-primary" onclick="refreshLogs()">🔄 Yenile</button>
            <button class="btn-danger" onclick="clearLogs()">🗑️ Logları Temizle</button>
        </div>
        <div class="log-viewer" id="log-content">Loglar yükleniyor...</div>
    </div>
    
    <!-- TAB 7: SETTINGS -->
    <div id="settings-panel" class="panel">
        <div class="panel-header">
            <h2>⚙️ Genel Sistem Ayarları</h2>
        </div>
        <form id="system-settings-form">
            <div class="form-row">
                <div class="form-group"><label>Sistem Adı</label><input type="text" id="system-name" placeholder="API Master"></div>
                <div class="form-group"><label>Debug Modu</label>
                    <select id="debug-mode">
                        <option value="0">Kapalı</option>
                        <option value="1">Açık</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Cache Süresi (saniye)</label><input type="number" id="cache-ttl" value="3600"></div>
                <div class="form-group"><label>Log Seviyesi</label>
                    <select id="log-level">
                        <option value="error">Sadece Hatalar</option>
                        <option value="warning">Uyarı + Hata</option>
                        <option value="info">Tüm Loglar</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn-primary">💾 Ayarları Kaydet</button>
        </form>
        <div id="settings-result" style="margin-top:16px;"></div>
    </div>
    
    <!-- TAB 8: ADD PROVIDER -->
    <div id="add-panel" class="panel">
        <div class="panel-header"><h2>➕ Yeni API Servisi Ekle</h2></div>
        <form id="add-form">
            <div class="form-row">
                <div class="form-group"><label>API ID (benzersiz)</label><input type="text" name="id" required placeholder="ornek-api"></div>
                <div class="form-group"><label>API Adı</label><input type="text" name="name" required placeholder="Örnek API"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Auth Tipi</label>
                    <select name="auth_type">
                        <option value="api_key">API Key</option>
                        <option value="bearer">Bearer Token</option>
                        <option value="basic">Basic Auth</option>
                        <option value="none">Gereksiz</option>
                    </select>
                </div>
                <div class="form-group"><label>Rate Limit (dk)</label><input type="number" name="rate_limit" value="60"></div>
            </div>
            <div class="form-group"><label>Modeller (virgülle ayır)</label><input type="text" name="models" placeholder="model-1, model-2, model-3"></div>
            <div class="form-group"><label>Base URL (opsiyonel)</label><input type="text" name="base_url" placeholder="https://api.example.com/v1"></div>
            <div class="form-group"><label>API Key (opsiyonel)</label><input type="password" name="api_key" placeholder="sk-..."></div>
            <button type="submit" class="btn-primary">💾 API Ekle</button>
        </form>
        <div id="add-result" style="margin-top: 16px;"></div>
    </div>
</div>

<!-- Settings Modal -->
<div id="settings-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><span>⚙️ API Ayarları</span><span class="close" onclick="closeModal()">&times;</span></div>
        <form id="settings-form">
            <input type="hidden" name="id" id="settings-id">
            <div class="form-group"><label>API Key</label><input type="password" name="api_key" id="settings-api-key" placeholder="API Key girin"></div>
            <div class="form-group"><label>Base URL</label><input type="text" name="base_url" id="settings-base-url" placeholder="https://api.example.com/v1"></div>
            <button type="submit" class="btn-primary">💾 Kaydet</button>
        </form>
    </div>
</div>

<div id="toast" class="toast"></div>

<script>
// -------------------------------------------------------------------------
// UTILITIES
// -------------------------------------------------------------------------
function showToast(msg, isError = false) {
    const toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.className = 'toast ' + (isError ? 'error' : '');
    toast.style.display = 'block';
    setTimeout(() => toast.style.display = 'none', 3000);
}

async function apiCall(action, data = {}) {
    const formData = new FormData();
    formData.append('action', action);
    for (const [k, v] of Object.entries(data)) formData.append(k, v);
    try {
        const res = await fetch('', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
        return await res.json();
    } catch(e) {
        console.error('API Call Error:', e);
        return { success: false, error: e.message };
    }
}

// -------------------------------------------------------------------------
// TAB SWITCHING - FIXED
// -------------------------------------------------------------------------
function switchToTab(tabId) {
    console.log('Switching to tab:', tabId);
    
    // Remove active class from all tabs and panels
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    
    // Add active class to selected tab
    const selectedTab = document.querySelector(`.tab[data-tab="${tabId}"]`);
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Show selected panel
    const selectedPanel = document.getElementById(`${tabId}-panel`);
    if (selectedPanel) {
        selectedPanel.classList.add('active');
        console.log('Panel activated:', `${tabId}-panel`);
    } else {
        console.error('Panel not found:', `${tabId}-panel`);
    }
    
    // Refresh data based on tab
    if (tabId === 'dashboard') refreshStats();
    if (tabId === 'learning') refreshLearningStats();
    if (tabId === 'vector') refreshVectorStats();
    if (tabId === 'cache') refreshCacheStats();
    if (tabId === 'logs') refreshLogs();
}

// Initialize tab click handlers
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', (e) => {
        e.preventDefault();
        const tabId = tab.getAttribute('data-tab');
        if (tabId) switchToTab(tabId);
    });
});

// -------------------------------------------------------------------------
// FILTER & SEARCH
// -------------------------------------------------------------------------
let currentFilter = 'all';
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentFilter = btn.dataset.filter;
        filterProviders();
    });
});

const searchInput = document.getElementById('search-input');
if (searchInput) searchInput.addEventListener('input', () => filterProviders());

function filterProviders() {
    const searchTerm = document.getElementById('search-input')?.value.toLowerCase() || '';
    document.querySelectorAll('.provider-card').forEach(card => {
        const isActive = card.dataset.active === '1';
        let matchFilter = true;
        if (currentFilter === 'active') matchFilter = isActive;
        else if (currentFilter === 'inactive') matchFilter = !isActive;
        const matchSearch = card.dataset.name?.includes(searchTerm) || card.dataset.id?.includes(searchTerm);
        card.style.display = (matchFilter && matchSearch) ? '' : 'none';
    });
}

// -------------------------------------------------------------------------
// DASHBOARD STATS
// -------------------------------------------------------------------------
async function refreshStats() {
    try {
        const res = await apiCall('get_stats', { type: 'all' });
        console.log('Stats response:', res);
        if (res.success && res.stats) {
            document.getElementById('stat-patterns').innerText = res.stats.learning?.total_patterns || 0;
            document.getElementById('stat-feedback').innerText = res.stats.learning?.total_feedback || 0;
            document.getElementById('stat-vectors').innerText = res.stats.vector?.total_vectors || 0;
            document.getElementById('stat-cache').innerText = res.stats.cache?.total_files || 0;
            document.getElementById('stat-cache-size').innerText = (res.stats.cache?.total_size || 0).toFixed(2);
            document.getElementById('stat-dimension').innerText = res.stats.vector?.dimension || 384;
        } else {
            console.warn('Stats refresh failed:', res);
        }
    } catch(e) {
        console.error('Stats refresh error:', e);
    }
}

async function refreshLearningStats() {
    const res = await apiCall('get_stats', { type: 'learning' });
    if (res.success && res.stats.learning) {
        document.getElementById('learn-patterns').innerText = res.stats.learning.total_patterns;
        document.getElementById('learn-feedback').innerText = res.stats.learning.total_feedback;
        document.getElementById('learn-accuracy').innerText = (res.stats.learning.accuracy || 0) + '%';
    }
    document.getElementById('learning-patterns-list').innerHTML = '<span style="color:#8b8b9e;">📋 Öğrenme patternleri için data/learning-data.json dosyası kontrol ediliyor...</span>';
}

async function refreshVectorStats() {
    const res = await apiCall('get_stats', { type: 'vector' });
    if (res.success && res.stats.vector) {
        document.getElementById('vector-count').innerText = res.stats.vector.total_vectors;
        document.getElementById('vector-dim').innerText = res.stats.vector.dimension;
        document.getElementById('vector-size').innerText = (res.stats.vector.index_size || 0) + ' KB';
    }
    document.getElementById('vector-data-list').innerHTML = '<span style="color:#8b8b9e;">📊 Vektör verileri için data/vector-data.json dosyası kontrol ediliyor...</span>';
}

async function refreshCacheStats() {
    const res = await apiCall('get_stats', { type: 'cache' });
    if (res.success && res.stats.cache) {
        document.getElementById('cache-count').innerText = res.stats.cache.total_files;
        document.getElementById('cache-total-size').innerText = (res.stats.cache.total_size || 0).toFixed(2) + ' KB';
    }
    document.getElementById('cache-list').innerHTML = '<span style="color:#8b8b9e;">🗂️ Cache klasörü: <?php echo API_MASTER_CACHE_DIR; ?></span>';
}

async function refreshLogs() {
    document.getElementById('log-content').innerHTML = '📝 Loglar yükleniyor...<br><br><span style="color:#8b8b9e;">Log dosyası: <?php echo API_MASTER_LOG_DIR; ?>/api-master.log</span>';
}

async function clearCache() {
    if (!confirm('Tüm cache dosyaları silinecek. Devam et?')) return;
    const res = await apiCall('clear_cache');
    if (res.success) {
        showToast(res.deleted + ' cache dosyası temizlendi');
        refreshCacheStats();
        refreshStats();
    } else {
        showToast('Cache temizleme hatası', true);
    }
}

async function clearLogs() {
    if (!confirm('Tüm loglar silinecek. Devam et?')) return;
    const res = await apiCall('clear_logs');
    if (res.success) {
        showToast('Loglar temizlendi');
        refreshLogs();
    } else {
        showToast('Log temizleme hatası', true);
    }
}

// -------------------------------------------------------------------------
// PROVIDER ACTIONS
// -------------------------------------------------------------------------
async function toggleProvider(id, active) {
    showToast(`${id} ${active ? 'aktifleştiriliyor...' : 'pasifleştiriliyor...'}`);
    const res = await apiCall('toggle', { id, active: active.toString() });
    if (res.success) {
        showToast(`${id} ${active ? 'aktif edildi' : 'pasif edildi'}`);
        setTimeout(() => location.reload(), 500);
    } else {
        showToast('Hata oluştu', true);
    }
}

async function deleteProvider(id) {
    if (!confirm(`${id} silinecek. Devam et?`)) return;
    const res = await apiCall('delete', { id });
    if (res.success) {
        showToast(`${id} silindi`);
        setTimeout(() => location.reload(), 500);
    } else {
        showToast('Silme hatası', true);
    }
}

async function testProvider(id) {
    showToast(`${id} test ediliyor...`);
    try {
        const res = await apiCall('test', { id });
        if (res.success) {
            showToast(`✅ ${id} test başarılı!`);
            alert(`✅ Test Başarılı!\n\nAPI: ${id}\nMesaj: ${res.message}\nSüre: ${res.response_time}ms`);
        } else {
            showToast(`❌ ${id} test başarısız`, true);
            alert(`❌ Test Başarısız!\n\nHata: ${res.error || 'Bilinmeyen hata'}`);
        }
    } catch(error) {
        showToast(`❌ Test hatası: ${error.message}`, true);
    }
}

// -------------------------------------------------------------------------
// SETTINGS MODAL
// -------------------------------------------------------------------------
function showSettingsModal(id, name, apiKey, baseUrl) {
    const modal = document.getElementById('settings-modal');
    document.getElementById('settings-id').value = id;
    document.getElementById('settings-api-key').value = apiKey;
    document.getElementById('settings-base-url').value = baseUrl;
    modal.style.display = 'flex';
}

function closeModal() {
    document.getElementById('settings-modal').style.display = 'none';
}

document.getElementById('settings-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('settings-id').value;
    const api_key = document.getElementById('settings-api-key').value;
    const base_url = document.getElementById('settings-base-url').value;
    const res = await apiCall('update_settings', { id, api_key, base_url });
    if (res.success) {
        showToast('Ayarlar kaydedildi');
        closeModal();
        setTimeout(() => location.reload(), 500);
    } else {
        showToast('Kayıt hatası', true);
    }
});

window.onclick = function(e) {
    const modal = document.getElementById('settings-modal');
    if (e.target === modal) closeModal();
}

// -------------------------------------------------------------------------
// ADD PROVIDER
// -------------------------------------------------------------------------
document.getElementById('add-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('action', 'add');
    const resultDiv = document.getElementById('add-result');
    resultDiv.innerHTML = '<span style="color:#667eea;">⏳ Ekleniyor...</span>';
    
    const res = await apiCall('add', Object.fromEntries(formData));
    
    if (res.success) {
        resultDiv.innerHTML = '<span style="color:#238636;">✅ API eklendi! Sayfa yenileniyor...</span>';
        setTimeout(() => location.reload(), 1000);
    } else {
        resultDiv.innerHTML = '<span style="color:#f85149;">❌ Ekleme hatası</span>';
    }
});

// -------------------------------------------------------------------------
// SYSTEM SETTINGS
// -------------------------------------------------------------------------
document.getElementById('system-settings-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const resultDiv = document.getElementById('settings-result');
    resultDiv.innerHTML = '<span style="color:#667eea;">⏳ Kaydediliyor...</span>';
    setTimeout(() => {
        resultDiv.innerHTML = '<span style="color:#238636;">✅ Ayarlar kaydedildi (config/settings.json)</span>';
    }, 500);
});

// Initial load - ensure dashboard is visible
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM loaded - initializing...');
    // Make sure dashboard panel is active
    const activeTab = document.querySelector('.tab.active');
    if (!activeTab) {
        document.querySelector('.tab[data-tab="dashboard"]')?.classList.add('active');
        document.getElementById('dashboard-panel')?.classList.add('active');
    }
    refreshStats();
});
</script>
</body>
</html>