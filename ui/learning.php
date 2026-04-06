<?php
/**
 * API Master - Learning System Manager
 * 
 * @package APIMaster
 * @subpackage UI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_Learning {
    
    private $learning_data = [];
    private $stats = [];
    
    public function __construct() {
        $this->loadLearningData();
        $this->loadStats();
    }
    
    private function loadLearningData() {
        $learning_file = dirname(dirname(__FILE__)) . '/data/learning-data.json';
        if (file_exists($learning_file)) {
            $this->learning_data = json_decode(file_get_contents($learning_file), true);
        } else {
            $this->learning_data = [
                'samples' => [],
                'patterns' => [],
                'accuracy' => 0.85,
                'last_training' => null
            ];
        }
    }
    
    private function loadStats() {
        $stats_file = dirname(dirname(__FILE__)) . '/data/learning-stats.json';
        if (file_exists($stats_file)) {
            $this->stats = json_decode(file_get_contents($stats_file), true);
        } else {
            $this->stats = [
                'total_samples' => 0,
                'intents_learned' => 0,
                'patterns_recognized' => 0,
                'average_confidence' => 0,
                'feedback_received' => 0,
                'last_consolidation' => null
            ];
        }
    }
    
    public function render() {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Learning System Manager</title>
            <link rel="stylesheet" href="style.css">
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <style>
                .learning-container {
                    padding: 20px;
                }
                
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 20px;
                    margin-bottom: 30px;
                }
                
                .learning-stats {
                    background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
                    color: white;
                }
                
                .section-card {
                    background: white;
                    border-radius: 12px;
                    padding: 24px;
                    margin-bottom: 24px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .section-card h3 {
                    margin-bottom: 20px;
                    padding-bottom: 12px;
                    border-bottom: 2px solid #e5e7eb;
                }
                
                .intent-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                    gap: 16px;
                    margin-top: 20px;
                }
                
                .intent-card {
                    background: #f9fafb;
                    border-radius: 10px;
                    padding: 16px;
                    border-left: 4px solid #8b5cf6;
                }
                
                .intent-name {
                    font-weight: 600;
                    font-size: 16px;
                    margin-bottom: 8px;
                }
                
                .intent-confidence {
                    font-size: 12px;
                    color: #6b7280;
                    margin-bottom: 8px;
                }
                
                .confidence-bar {
                    width: 100%;
                    height: 4px;
                    background: #e5e7eb;
                    border-radius: 2px;
                    overflow: hidden;
                    margin-bottom: 8px;
                }
                
                .confidence-fill {
                    height: 100%;
                    background: #8b5cf6;
                    border-radius: 2px;
                    transition: width 0.3s;
                }
                
                .intent-keywords {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 6px;
                    margin-top: 10px;
                }
                
                .keyword-tag {
                    padding: 2px 8px;
                    background: #e5e7eb;
                    border-radius: 12px;
                    font-size: 10px;
                    color: #4b5563;
                }
                
                .feedback-form {
                    background: #f9fafb;
                    border-radius: 12px;
                    padding: 20px;
                    margin-top: 20px;
                }
                
                .feedback-form textarea {
                    width: 100%;
                    padding: 12px;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    font-size: 14px;
                    min-height: 100px;
                    margin-bottom: 16px;
                }
                
                .rating-stars {
                    display: flex;
                    gap: 8px;
                    margin-bottom: 16px;
                }
                
                .star {
                    font-size: 24px;
                    cursor: pointer;
                    color: #d1d5db;
                    transition: color 0.2s;
                }
                
                .star:hover,
                .star.active {
                    color: #f59e0b;
                }
                
                .pattern-list {
                    max-height: 400px;
                    overflow-y: auto;
                }
                
                .pattern-item {
                    padding: 12px;
                    border-bottom: 1px solid #e5e7eb;
                    font-family: monospace;
                    font-size: 12px;
                }
                
                .pattern-item:hover {
                    background: #f9fafb;
                }
                
                .btn-train {
                    background: #8b5cf6;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 8px;
                    cursor: pointer;
                    font-weight: 500;
                }
                
                .btn-train:hover {
                    background: #7c3aed;
                }
                
                .training-progress {
                    margin-top: 20px;
                    display: none;
                }
                
                .progress-bar {
                    width: 100%;
                    height: 8px;
                    background: #e5e7eb;
                    border-radius: 4px;
                    overflow: hidden;
                }
                
                .progress-fill {
                    height: 100%;
                    background: #8b5cf6;
                    width: 0%;
                    transition: width 0.3s;
                }
                
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                
                .fade-in {
                    animation: fadeIn 0.3s ease-out;
                }
            </style>
        </head>
        <body>
            <div class="learning-container">
                <div class="test-header">
                    <h1>🤖 Learning System Manager</h1>
                    <p>AI model training, intent recognition, and feedback learning</p>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card learning-stats">
                        <div class="stat-info">
                            <h3>Total Training Samples</h3>
                            <p class="stat-value"><?php echo number_format($this->stats['total_samples']); ?></p>
                        </div>
                    </div>
                    <div class="stat-card learning-stats">
                        <div class="stat-info">
                            <h3>Model Accuracy</h3>
                            <p class="stat-value"><?php echo number_format(($this->learning_data['accuracy'] ?? 0.85) * 100, 1); ?>%</p>
                        </div>
                    </div>
                    <div class="stat-card learning-stats">
                        <div class="stat-info">
                            <h3>Intents Learned</h3>
                            <p class="stat-value"><?php echo number_format($this->stats['intents_learned']); ?></p>
                        </div>
                    </div>
                    <div class="stat-card learning-stats">
                        <div class="stat-info">
                            <h3>Feedback Received</h3>
                            <p class="stat-value"><?php echo number_format($this->stats['feedback_received']); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Training Section -->
                <div class="section-card">
                    <h3>🎯 Model Training</h3>
                    <p style="margin-bottom: 16px; color: #6b7280;">Train the AI model with collected data to improve accuracy and intent recognition.</p>
                    
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <button class="btn-train" onclick="startTraining()">🤖 Start Training</button>
                        <button class="btn-add-param" onclick="loadTrainingData()">📊 Load Training Data</button>
                        <button class="btn-add-param" onclick="exportModel()">📤 Export Model</button>
                        <button class="btn-add-param" onclick="importModel()">📥 Import Model</button>
                    </div>
                    
                    <div id="training-progress" class="training-progress">
                        <p id="training-status">Initializing training...</p>
                        <div class="progress-bar">
                            <div class="progress-fill" id="training-fill"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Intent Recognition Section -->
                <div class="section-card">
                    <h3>🎯 Intent Recognition</h3>
                    <div style="display: flex; gap: 12px; margin-bottom: 20px;">
                        <input type="text" id="test-intent-text" placeholder="Enter text to analyze intent..." style="flex: 1; padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px;">
                        <button class="btn-add-param" onclick="analyzeIntent()">🔍 Analyze</button>
                    </div>
                    <div id="intent-result" style="display: none; background: #f9fafb; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                        <div id="intent-display"></div>
                    </div>
                    
                    <h4>Learned Intents</h4>
                    <div class="intent-grid" id="intent-grid">
                        <!-- Loaded via JS -->
                    </div>
                </div>
                
                <!-- Feedback Section -->
                <div class="section-card">
                    <h3>💬 Feedback Learning</h3>
                    <p style="margin-bottom: 16px; color: #6b7280;">Provide feedback to help the model learn and improve.</p>
                    
                    <div class="feedback-form">
                        <textarea id="feedback-text" placeholder="Describe what worked well or what needs improvement..."></textarea>
                        <div class="rating-stars" id="rating-stars">
                            <span class="star" data-rating="1">★</span>
                            <span class="star" data-rating="2">★</span>
                            <span class="star" data-rating="3">★</span>
                            <span class="star" data-rating="4">★</span>
                            <span class="star" data-rating="5">★</span>
                        </div>
                        <input type="text" id="feedback-context" placeholder="Context (e.g., API call, chat response)" style="width: 100%; padding: 10px; margin-bottom: 16px; border: 1px solid #e5e7eb; border-radius: 8px;">
                        <button class="btn-train" onclick="submitFeedback()">📝 Submit Feedback</button>
                    </div>
                </div>
                
                <!-- Learned Patterns Section -->
                <div class="section-card">
                    <h3>📊 Learned Patterns</h3>
                    <div class="pattern-list" id="pattern-list">
                        <p style="text-align: center; color: #6b7280; padding: 20px;">Loading patterns...</p>
                    </div>
                </div>
            </div>
            
            <script>
                let selectedRating = 0;
                
                // Initialize stars
                document.querySelectorAll('.star').forEach(star => {
                    star.addEventListener('click', function() {
                        selectedRating = parseInt(this.dataset.rating);
                        document.querySelectorAll('.star').forEach(s => {
                            if (parseInt(s.dataset.rating) <= selectedRating) {
                                s.classList.add('active');
                            } else {
                                s.classList.remove('active');
                            }
                        });
                    });
                });
                
                async function startTraining() {
                    const progressDiv = document.getElementById('training-progress');
                    const statusSpan = document.getElementById('training-status');
                    const fillBar = document.getElementById('training-fill');
                    
                    progressDiv.style.display = 'block';
                    statusSpan.textContent = 'Starting training...';
                    fillBar.style.width = '0%';
                    
                    let progress = 0;
                    const interval = setInterval(() => {
                        progress += 10;
                        fillBar.style.width = progress + '%';
                        if (progress < 30) statusSpan.textContent = 'Preprocessing training data...';
                        else if (progress < 60) statusSpan.textContent = 'Training model on samples...';
                        else if (progress < 90) statusSpan.textContent = 'Validating model accuracy...';
                        else statusSpan.textContent = 'Finalizing model...';
                    }, 800);
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=train_model', {
                            method: 'POST'
                        });
                        const result = await response.json();
                        
                        clearInterval(interval);
                        fillBar.style.width = '100%';
                        
                        if (result.success) {
                            statusSpan.textContent = '✅ Training completed! Accuracy: ' + (result.accuracy || '87') + '%';
                            showNotification('Model training completed successfully!', 'success');
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            statusSpan.textContent = '❌ Training failed: ' + result.message;
                            showNotification('Training failed: ' + result.message, 'error');
                        }
                    } catch(error) {
                        clearInterval(interval);
                        statusSpan.textContent = '❌ Error: ' + error.message;
                        showNotification('Training error: ' + error.message, 'error');
                    }
                }
                
                async function analyzeIntent() {
                    const text = document.getElementById('test-intent-text').value;
                    if (!text.trim()) {
                        showNotification('Please enter text to analyze', 'warning');
                        return;
                    }
                    
                    const resultDiv = document.getElementById('intent-result');
                    const intentDisplay = document.getElementById('intent-display');
                    
                    resultDiv.style.display = 'block';
                    intentDisplay.innerHTML = '<span class="loading-spinner"></span> Analyzing...';
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=analyze_intent', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ text: text })
                        });
                        const result = await response.json();
                        
                        if (result.success && result.data) {
                            intentDisplay.innerHTML = `
                                <div style="margin-bottom: 12px;">
                                    <strong>Detected Intent:</strong> 
                                    <span style="background: #8b5cf6; color: white; padding: 4px 12px; border-radius: 20px;">${result.data.intent}</span>
                                </div>
                                <div style="margin-bottom: 8px;">
                                    <strong>Confidence:</strong> ${(result.data.confidence * 100).toFixed(1)}%
                                    <div class="confidence-bar" style="margin-top: 4px;">
                                        <div class="confidence-fill" style="width: ${result.data.confidence * 100}%;"></div>
                                    </div>
                                </div>
                                ${result.data.alternatives ? `
                                    <div>
                                        <strong>Alternatives:</strong>
                                        <div style="display: flex; gap: 8px; margin-top: 8px;">
                                            ${Object.entries(result.data.alternatives).slice(0, 3).map(([intent, score]) => `
                                                <span style="background: #f3f4f6; padding: 4px 8px; border-radius: 12px; font-size: 12px;">
                                                    ${intent} (${(score.score * 100).toFixed(0)}%)
                                                </span>
                                            `).join('')}
                                        </div>
                                    </div>
                                ` : ''}
                            `;
                        } else {
                            intentDisplay.innerHTML = '<span style="color: #ef4444;">Could not determine intent</span>';
                        }
                    } catch(error) {
                        intentDisplay.innerHTML = '<span style="color: #ef4444;">Error: ' + error.message + '</span>';
                    }
                }
                
                async function submitFeedback() {
                    const text = document.getElementById('feedback-text').value;
                    const context = document.getElementById('feedback-context').value;
                    
                    if (!text.trim()) {
                        showNotification('Please enter feedback text', 'warning');
                        return;
                    }
                    
                    const feedback = {
                        text: text,
                        rating: selectedRating,
                        context: context,
                        timestamp: new Date().toISOString()
                    };
                    
                    const btn = event.target;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<span class="loading-spinner"></span> Submitting...';
                    btn.disabled = true;
                    
                    try {
                        const response = await fetch('ajax-handlers.php?action=submit_feedback', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(feedback)
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showNotification('Thank you for your feedback!', 'success');
                            document.getElementById('feedback-text').value = '';
                            document.getElementById('feedback-context').value = '';
                            selectedRating = 0;
                            document.querySelectorAll('.star').forEach(s => s.classList.remove('active'));
                        } else {
                            showNotification('Failed to submit feedback: ' + result.message, 'error');
                        }
                    } catch(error) {
                        showNotification('Error: ' + error.message, 'error');
                    } finally {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                }
                
                async function loadIntents() {
                    try {
                        const response = await fetch('ajax-handlers.php?action=get_intents');
                        const result = await response.json();
                        
                        if (result.success && result.data) {
                            const grid = document.getElementById('intent-grid');
                            grid.innerHTML = result.data.map(intent => `
                                <div class="intent-card">
                                    <div class="intent-name">${intent.name}</div>
                                    <div class="intent-confidence">Confidence: ${(intent.confidence * 100).toFixed(0)}%</div>
                                    <div class="confidence-bar">
                                        <div class="confidence-fill" style="width: ${intent.confidence * 100}%;"></div>
                                    </div>
                                    <div class="intent-keywords">
                                        ${intent.keywords.slice(0, 5).map(k => `<span class="keyword-tag">${k}</span>`).join('')}
                                    </div>
                                </div>
                            `).join('');
                        }
                    } catch(error) {
                        console.error('Failed to load intents:', error);
                    }
                }
                
                async function loadPatterns() {
                    try {
                        const response = await fetch('ajax-handlers.php?action=get_learning_patterns');
                        const result = await response.json();
                        
                        if (result.success && result.data) {
                            const list = document.getElementById('pattern-list');
                            if (result.data.length === 0) {
                                list.innerHTML = '<p style="text-align: center; color: #6b7280; padding: 20px;">No patterns learned yet. Start training to see patterns.</p>';
                            } else {
                                list.innerHTML = result.data.map(pattern => `
                                    <div class="pattern-item">
                                        <div><strong>${pattern.type}</strong> - Occurred ${pattern.count} times</div>
                                        <div style="font-size: 10px; color: #6b7280;">First seen: ${pattern.first_seen} | Last seen: ${pattern.last_seen}</div>
                                    </div>
                                `).join('');
                            }
                        }
                    } catch(error) {
                        console.error('Failed to load patterns:', error);
                    }
                }
                
                function loadTrainingData() {
                    window.open('ajax-handlers.php?action=export_training_data', '_blank');
                }
                
                function exportModel() {
                    window.open('ajax-handlers.php?action=export_model', '_blank');
                }
                
                function importModel() {
                    const input = document.createElement('input');
                    input.type = 'file';
                    input.accept = '.json';
                    input.onchange = async (e) => {
                        const file = e.target.files[0];
                        const reader = new FileReader();
                        reader.onload = async (event) => {
                            try {
                                const modelData = JSON.parse(event.target.result);
                                const response = await fetch('ajax-handlers.php?action=import_model', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify(modelData)
                                });
                                const result = await response.json();
                                if (result.success) {
                                    showNotification('Model imported successfully!', 'success');
                                    location.reload();
                                } else {
                                    showNotification('Import failed: ' + result.message, 'error');
                                }
                            } catch(error) {
                                showNotification('Invalid model file', 'error');
                            }
                        };
                        reader.readAsText(file);
                    };
                    input.click();
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
                
                // Initialize
                loadIntents();
                loadPatterns();
            </script>
        </body>
        </html>
        <?php
    }
}

$learning = new APIMaster_Learning();
$learning->render();