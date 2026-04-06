<?php
/**
 * API Master - API Test Console
 * 
 * @package APIMaster
 * @subpackage UI
 * @since 1.0.0
 * 
 * IMPORTANT: Standalone API testing interface - NO WordPress dependencies!
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_APITest {
    
    private $providers = [];
    private $config = [];
    
    public function __construct() {
        $this->loadProviders();
        $this->loadConfig();
    }
    
    private function loadProviders() {
        $providers_file = dirname(dirname(__FILE__)) . '/config/providers.json';
        if (file_exists($providers_file)) {
            $data = json_decode(file_get_contents($providers_file), true);
            $this->providers = $data['providers'] ?? [];
        }
    }
    
    private function loadConfig() {
        $config_file = dirname(dirname(__FILE__)) . '/config/settings.json';
        if (file_exists($config_file)) {
            $this->config = json_decode(file_get_contents($config_file), true);
        }
    }
    
    public function render() {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>API Test Console</title>
            <link rel="stylesheet" href="style.css">
            <style>
                .api-test-container {
                    padding: 20px;
                }
                
                .test-header {
                    margin-bottom: 30px;
                }
                
                .test-header h1 {
                    font-size: 24px;
                    color: #1f2937;
                    margin-bottom: 8px;
                }
                
                .test-header p {
                    color: #6b7280;
                }
                
                .test-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 24px;
                }
                
                .test-panel {
                    background: white;
                    border-radius: 12px;
                    padding: 24px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .test-panel h2 {
                    font-size: 18px;
                    margin-bottom: 20px;
                    padding-bottom: 12px;
                    border-bottom: 2px solid #e5e7eb;
                }
                
                .form-group {
                    margin-bottom: 20px;
                }
                
                .form-group label {
                    display: block;
                    margin-bottom: 8px;
                    font-weight: 500;
                    color: #374151;
                }
                
                .form-group select,
                .form-group input,
                .form-group textarea {
                    width: 100%;
                    padding: 10px 12px;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    font-size: 14px;
                    transition: all 0.3s;
                }
                
                .form-group select:focus,
                .form-group input:focus,
                .form-group textarea:focus {
                    outline: none;
                    border-color: #4F46E5;
                    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
                }
                
                .form-group textarea {
                    min-height: 150px;
                    font-family: monospace;
                    resize: vertical;
                }
                
                .param-row {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 10px;
                }
                
                .param-row input {
                    flex: 1;
                }
                
                .param-row button {
                    padding: 8px 12px;
                    background: #ef4444;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                }
                
                .btn-add-param {
                    padding: 6px 12px;
                    background: #10b981;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 12px;
                }
                
                .btn-test {
                    width: 100%;
                    padding: 12px;
                    background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
                    color: white;
                    border: none;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: 500;
                    cursor: pointer;
                    margin-top: 20px;
                }
                
                .btn-test:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
                }
                
                .btn-test:disabled {
                    opacity: 0.6;
                    cursor: not-allowed;
                    transform: none;
                }
                
                .response-panel {
                    background: #1e1e2e;
                    border-radius: 12px;
                    padding: 20px;
                    margin-top: 24px;
                }
                
                .response-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 16px;
                    padding-bottom: 12px;
                    border-bottom: 1px solid #2a2a3a;
                }
                
                .response-header h3 {
                    color: white;
                    margin: 0;
                }
                
                .response-status {
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 500;
                }
                
                .response-status.success {
                    background: #10b981;
                    color: white;
                }
                
                .response-status.error {
                    background: #ef4444;
                    color: white;
                }
                
                .response-status.pending {
                    background: #f59e0b;
                    color: white;
                }
                
                .response-body {
                    background: #0a0a0f;
                    border-radius: 8px;
                    padding: 16px;
                    overflow-x: auto;
                    max-height: 400px;
                }
                
                .response-body pre {
                    color: #e0e0e0;
                    font-family: monospace;
                    font-size: 12px;
                    margin: 0;
                    white-space: pre-wrap;
                    word-wrap: break-word;
                }
                
                .response-meta {
                    display: flex;
                    gap: 20px;
                    margin-top: 16px;
                    padding-top: 12px;
                    border-top: 1px solid #2a2a3a;
                    font-size: 12px;
                    color: #8b8b9e;
                }
                
                .preset-buttons {
                    display: flex;
                    gap: 8px;
                    margin-bottom: 16px;
                    flex-wrap: wrap;
                }
                
                .preset-btn {
                    padding: 6px 12px;
                    background: #f3f4f6;
                    border: none;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 12px;
                }
                
                .preset-btn:hover {
                    background: #e5e7eb;
                }
                
                .loading-spinner {
                    display: inline-block;
                    width: 20px;
                    height: 20px;
                    border: 2px solid rgba(255,255,255,0.3);
                    border-top-color: white;
                    border-radius: 50%;
                    animation: spin 0.8s linear infinite;
                }
                
                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
                
                @media (max-width: 1024px) {
                    .test-grid {
                        grid-template-columns: 1fr;
                    }
                }
            </style>
        </head>
        <body>
            <div class="api-test-container">
                <div class="test-header">
                    <h1>🧪 API Test Console</h1>
                    <p>Test and debug API endpoints with real-time responses</p>
                </div>
                
                <div class="test-grid">
                    <!-- Request Panel -->
                    <div class="test-panel">
                        <h2>📤 Request Configuration</h2>
                        
                        <div class="form-group">
                            <label>Provider</label>
                            <select id="provider">
                                <?php foreach ($this->providers as $slug => $provider): ?>
                                    <option value="<?php echo htmlspecialchars($slug); ?>">
                                        <?php echo htmlspecialchars($provider['name'] ?? $slug); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Endpoint</label>
                            <select id="endpoint">
                                <option value="/chat/completions">/chat/completions (Chat)</option>
                                <option value="/completions">/completions (Text)</option>
                                <option value="/embeddings">/embeddings (Embeddings)</option>
                                <option value="/models">/models (List Models)</option>
                                <option value="/images/generations">/images/generations (Image Generation)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Method</label>
                            <select id="method">
                                <option value="POST">POST</option>
                                <option value="GET">GET</option>
                                <option value="PUT">PUT</option>
                                <option value="DELETE">DELETE</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Request Body (JSON)</label>
                            <textarea id="request-body" placeholder='{
  "model": "gpt-3.5-turbo",
  "messages": [
    {"role": "user", "content": "Hello!"}
  ]
}'></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Additional Parameters</label>
                            <div id="params-container"></div>
                            <button type="button" class="btn-add-param" onclick="addParameter()">+ Add Parameter</button>
                        </div>
                        
                        <div class="preset-buttons">
                            <button class="preset-btn" onclick="loadPreset('chat')">💬 Chat</button>
                            <button class="preset-btn" onclick="loadPreset('completion')">📝 Completion</button>
                            <button class="preset-btn" onclick="loadPreset('embedding')">🔢 Embedding</button>
                            <button class="preset-btn" onclick="loadPreset('image')">🎨 Image</button>
                            <button class="preset-btn" onclick="loadPreset('models')">📋 Models</button>
                        </div>
                        
                        <button class="btn-test" onclick="sendRequest()" id="test-btn">
                            🚀 Send Request
                        </button>
                    </div>
                    
                    <!-- Response Panel -->
                    <div class="test-panel">
                        <h2>📥 Response</h2>
                        
                        <div class="response-panel">
                            <div class="response-header">
                                <h3>Response Details</h3>
                                <span class="response-status pending" id="response-status">Pending</span>
                            </div>
                            <div class="response-body" id="response-body">
                                <pre>Click "Send Request" to test the API...</pre>
                            </div>
                            <div class="response-meta" id="response-meta">
                                <span>⏱️ Time: --</span>
                                <span>📦 Size: --</span>
                                <span>🔌 Provider: --</span>
                            </div>
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <button class="btn-add-param" onclick="copyResponse()" style="margin-right: 10px;">📋 Copy Response</button>
                            <button class="btn-add-param" onclick="clearResponse()">🗑️ Clear</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
                let parameters = [];
                
                function addParameter() {
                    const container = document.getElementById('params-container');
                    const paramId = Date.now();
                    parameters.push({ id: paramId });
                    
                    const div = document.createElement('div');
                    div.className = 'param-row';
                    div.id = `param-${paramId}`;
                    div.innerHTML = `
                        <input type="text" placeholder="Parameter name" id="param-name-${paramId}">
                        <input type="text" placeholder="Value" id="param-value-${paramId}">
                        <button onclick="removeParameter(${paramId})">✕</button>
                    `;
                    container.appendChild(div);
                }
                
                function removeParameter(id) {
                    const element = document.getElementById(`param-${id}`);
                    if (element) element.remove();
                    parameters = parameters.filter(p => p.id !== id);
                }
                
                function loadPreset(type) {
                    const bodyTextarea = document.getElementById('request-body');
                    const endpointSelect = document.getElementById('endpoint');
                    
                    switch(type) {
                        case 'chat':
                            endpointSelect.value = '/chat/completions';
                            bodyTextarea.value = JSON.stringify({
                                model: 'gpt-3.5-turbo',
                                messages: [
                                    { role: 'system', content: 'You are a helpful assistant.' },
                                    { role: 'user', content: 'What is artificial intelligence?' }
                                ],
                                temperature: 0.7,
                                max_tokens: 500
                            }, null, 2);
                            break;
                            
                        case 'completion':
                            endpointSelect.value = '/completions';
                            bodyTextarea.value = JSON.stringify({
                                model: 'gpt-3.5-turbo-instruct',
                                prompt: 'Explain quantum computing in simple terms:',
                                max_tokens: 300,
                                temperature: 0.7
                            }, null, 2);
                            break;
                            
                        case 'embedding':
                            endpointSelect.value = '/embeddings';
                            bodyTextarea.value = JSON.stringify({
                                model: 'text-embedding-ada-002',
                                input: 'The quick brown fox jumps over the lazy dog'
                            }, null, 2);
                            break;
                            
                        case 'image':
                            endpointSelect.value = '/images/generations';
                            bodyTextarea.value = JSON.stringify({
                                prompt: 'A serene landscape with mountains and a lake at sunset',
                                n: 1,
                                size: '1024x1024'
                            }, null, 2);
                            break;
                            
                        case 'models':
                            endpointSelect.value = '/models';
                            bodyTextarea.value = '';
                            break;
                    }
                }
                
                async function sendRequest() {
                    const provider = document.getElementById('provider').value;
                    const endpoint = document.getElementById('endpoint').value;
                    const method = document.getElementById('method').value;
                    let requestBody = document.getElementById('request-body').value;
                    
                    // Add parameters to URL if GET request
                    let url = `ajax-handlers.php?action=test_api&provider=${provider}&endpoint=${endpoint}&method=${method}`;
                    
                    if (method === 'GET' && parameters.length > 0) {
                        parameters.forEach(param => {
                            const name = document.getElementById(`param-name-${param.id}`)?.value;
                            const value = document.getElementById(`param-value-${param.id}`)?.value;
                            if (name && value) {
                                url += `&${encodeURIComponent(name)}=${encodeURIComponent(value)}`;
                            }
                        });
                    }
                    
                    const btn = document.getElementById('test-btn');
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<span class="loading-spinner"></span> Sending...';
                    btn.disabled = true;
                    
                    const statusSpan = document.getElementById('response-status');
                    statusSpan.textContent = 'Sending...';
                    statusSpan.className = 'response-status pending';
                    
                    const startTime = performance.now();
                    
                    try {
                        const options = {
                            method: method,
                            headers: {
                                'Content-Type': 'application/json'
                            }
                        };
                        
                        if ((method === 'POST' || method === 'PUT') && requestBody) {
                            // Validate JSON
                            try {
                                JSON.parse(requestBody);
                                options.body = requestBody;
                            } catch(e) {
                                throw new Error('Invalid JSON in request body');
                            }
                        }
                        
                        const response = await fetch(url, options);
                        const endTime = performance.now();
                        const duration = (endTime - startTime).toFixed(2);
                        
                        let responseData;
                        const contentType = response.headers.get('content-type');
                        if (contentType && contentType.includes('application/json')) {
                            responseData = await response.json();
                        } else {
                            responseData = await response.text();
                        }
                        
                        const responseBody = document.getElementById('response-body');
                        const pre = responseBody.querySelector('pre');
                        
                        if (response.ok) {
                            statusSpan.textContent = `Success (${response.status})`;
                            statusSpan.className = 'response-status success';
                            pre.innerHTML = syntaxHighlight(JSON.stringify(responseData, null, 2));
                        } else {
                            statusSpan.textContent = `Error (${response.status})`;
                            statusSpan.className = 'response-status error';
                            pre.innerHTML = syntaxHighlight(JSON.stringify(responseData, null, 2));
                        }
                        
                        const responseSize = JSON.stringify(responseData).length;
                        const metaSpan = document.getElementById('response-meta');
                        metaSpan.innerHTML = `
                            <span>⏱️ Time: ${duration}ms</span>
                            <span>📦 Size: ${formatBytes(responseSize)}</span>
                            <span>🔌 Provider: ${provider}</span>
                        `;
                        
                    } catch(error) {
                        statusSpan.textContent = 'Error';
                        statusSpan.className = 'response-status error';
                        const responseBody = document.getElementById('response-body');
                        const pre = responseBody.querySelector('pre');
                        pre.innerHTML = `<span style="color: #ef4444;">Error: ${error.message}</span>`;
                    } finally {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                }
                
                function syntaxHighlight(json) {
                    json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function(match) {
                        let cls = 'number';
                        if (/^"/.test(match)) {
                            if (/:$/.test(match)) {
                                cls = 'key';
                            } else {
                                cls = 'string';
                            }
                        } else if (/true|false/.test(match)) {
                            cls = 'boolean';
                        } else if (/null/.test(match)) {
                            cls = 'null';
                        }
                        return '<span style="color: ' + getColorForClass(cls) + ';">' + match + '</span>';
                    });
                }
                
                function getColorForClass(cls) {
                    const colors = {
                        'key': '#f59e0b',
                        'string': '#10b981',
                        'number': '#3b82f6',
                        'boolean': '#8b5cf6',
                        'null': '#ef4444'
                    };
                    return colors[cls] || '#e0e0e0';
                }
                
                function formatBytes(bytes) {
                    if (bytes === 0) return '0 B';
                    const k = 1024;
                    const sizes = ['B', 'KB', 'MB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                }
                
                function copyResponse() {
                    const responseText = document.getElementById('response-body').innerText;
                    navigator.clipboard.writeText(responseText);
                    showNotification('Response copied to clipboard!', 'success');
                }
                
                function clearResponse() {
                    const responseBody = document.getElementById('response-body');
                    responseBody.querySelector('pre').innerHTML = 'Click "Send Request" to test the API...';
                    document.getElementById('response-status').textContent = 'Pending';
                    document.getElementById('response-status').className = 'response-status pending';
                    document.getElementById('response-meta').innerHTML = `
                        <span>⏱️ Time: --</span>
                        <span>📦 Size: --</span>
                        <span>🔌 Provider: --</span>
                    `;
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
                        background: ${type === 'success' ? '#10b981' : '#ef4444'};
                        color: white;
                        border-radius: 8px;
                        z-index: 1000;
                        animation: fadeIn 0.3s ease;
                    `;
                    document.body.appendChild(notification);
                    setTimeout(() => notification.remove(), 3000);
                }
                
                // Load default preset
                loadPreset('chat');
            </script>
        </body>
        </html>
        <?php
    }
}

$test = new APIMaster_APITest();
$test->render();