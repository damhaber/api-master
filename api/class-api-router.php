<?php
/**
 * API Master Module - API Router
 * API isteklerini yönlendirme ve işleme
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_APIRouter {
    
    /**
     * @var array Route tanımları
     */
    private $routes = [];
    
    /**
     * @var array Middleware'ler
     */
    private $middlewares = [];
    
    /**
     * @var array İstek verisi
     */
    private $request;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->registerDefaultRoutes();
        $this->registerDefaultMiddlewares();
    }
    
    /**
     * Varsayılan route'ları kaydet
     */
    private function registerDefaultRoutes(): void {
        // Chat routes
        $this->post('/api/v1/chat', 'handleChat');
        $this->post('/api/v1/chat/stream', 'handleChatStream');
        
        // Completion routes
        $this->post('/api/v1/completion', 'handleCompletion');
        
        // Image routes
        $this->post('/api/v1/images/generate', 'handleImageGeneration');
        
        // Embedding routes
        $this->post('/api/v1/embeddings', 'handleEmbeddings');
        
        // Provider routes
        $this->get('/api/v1/providers', 'getProviders');
        $this->get('/api/v1/providers/{provider}', 'getProvider');
        $this->get('/api/v1/providers/{provider}/models', 'getProviderModels');
        
        // Health routes
        $this->get('/api/v1/health', 'healthCheck');
        $this->get('/api/v1/health/{provider}', 'healthCheckProvider');
        
        // Analytics routes
        $this->get('/api/v1/analytics', 'getAnalytics');
        $this->get('/api/v1/analytics/usage', 'getUsageAnalytics');
        
        // System routes
        $this->get('/api/v1/system/info', 'getSystemInfo');
        $this->get('/api/v1/system/status', 'getSystemStatus');
    }
    
    /**
     * Varsayılan middleware'leri kaydet
     */
    private function registerDefaultMiddlewares(): void {
        $this->middleware('cors', function($request, $next) {
            // CORS headers
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
            
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                exit(0);
            }
            
            return $next($request);
        });
        
        $this->middleware('auth', function($request, $next) {
            // API key kontrolü
            $apiKey = $request['headers']['X-API-Key'] ?? $request['headers']['Authorization'] ?? null;
            
            if (!$this->validateApiKey($apiKey)) {
                return $this->errorResponse('Geçersiz API anahtarı', 401);
            }
            
            return $next($request);
        });
    }
    
    /**
     * GET route kaydet
     * 
     * @param string $path
     * @param string|callable $handler
     */
    public function get(string $path, $handler): void {
        $this->addRoute('GET', $path, $handler);
    }
    
    /**
     * POST route kaydet
     * 
     * @param string $path
     * @param string|callable $handler
     */
    public function post(string $path, $handler): void {
        $this->addRoute('POST', $path, $handler);
    }
    
    /**
     * PUT route kaydet
     * 
     * @param string $path
     * @param string|callable $handler
     */
    public function put(string $path, $handler): void {
        $this->addRoute('PUT', $path, $handler);
    }
    
    /**
     * DELETE route kaydet
     * 
     * @param string $path
     * @param string|callable $handler
     */
    public function delete(string $path, $handler): void {
        $this->addRoute('DELETE', $path, $handler);
    }
    
    /**
     * Route ekle
     * 
     * @param string $method
     * @param string $path
     * @param string|callable $handler
     */
    private function addRoute(string $method, string $path, $handler): void {
        // Path'teki parametreleri bul
        $pattern = preg_replace('/\{([a-z]+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';
        
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler
        ];
    }
    
    /**
     * Middleware ekle
     * 
     * @param string $name
     * @param callable $callback
     */
    public function middleware(string $name, callable $callback): void {
        $this->middlewares[$name] = $callback;
    }
    
    /**
     * İsteği işle
     * 
     * @param array|null $request
     * @return array
     */
    public function handle(?array $request = null): array {
        $this->request = $request ?? $this->parseRequest();
        
        try {
            // Route'u bul
            $route = $this->matchRoute($this->request['method'], $this->request['path']);
            
            if (!$route) {
                return $this->errorResponse('Route bulunamadı', 404);
            }
            
            // Middleware'leri çalıştır
            $handler = $this->runMiddlewares($route['handler']);
            
            // Handler'ı çağır
            $response = $this->callHandler($handler, $route);
            
            return $this->formatResponse($response);
            
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }
    
    /**
     * İsteği parse et
     * 
     * @return array
     */
    private function parseRequest(): array {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Body verisini al
        $body = file_get_contents('php://input');
        $data = json_decode($body, true) ?: [];
        
        // Query parametreleri
        $query = $_GET;
        
        // Headers
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerKey = str_replace('_', '-', substr($key, 5));
                $headerKey = ucwords(strtolower($headerKey), '-');
                $headers[$headerKey] = $value;
            }
        }
        
        return [
            'method' => $method,
            'path' => $path,
            'headers' => $headers,
            'data' => $data,
            'query' => $query,
            'client_id' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => time()
        ];
    }
    
    /**
     * Route'u eşleştir
     * 
     * @param string $method
     * @param string $path
     * @return array|null
     */
    private function matchRoute(string $method, string $path): ?array {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            if (preg_match($route['pattern'], $path, $matches)) {
                // Parametreleri çıkar
                $params = [];
                foreach ($matches as $key => $value) {
                    if (!is_numeric($key)) {
                        $params[$key] = $value;
                    }
                }
                
                return [
                    'handler' => $route['handler'],
                    'params' => $params,
                    'path' => $route['path']
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Middleware'leri çalıştır
     * 
     * @param mixed $handler
     * @return callable
     */
    private function runMiddlewares($handler): callable {
        $middlewareChain = array_reverse($this->middlewares);
        
        $next = function($request) use ($handler) {
            return $this->callHandler($handler, $request);
        };
        
        foreach ($middlewareChain as $middleware) {
            $next = function($request) use ($middleware, $next) {
                return $middleware($request, $next);
            };
        }
        
        return $next;
    }
    
    /**
     * Handler'ı çağır
     * 
     * @param mixed $handler
     * @param array $route
     * @return mixed
     */
    private function callHandler($handler, array $route) {
        if (is_callable($handler)) {
            return $handler($this->request, $route['params']);
        }
        
        if (is_string($handler) && method_exists($this, $handler)) {
            return $this->$handler($this->request, $route['params']);
        }
        
        throw new RuntimeException("Geçersiz handler: " . print_r($handler, true));
    }
    
    /**
     * Chat isteğini işle
     */
    private function handleChat(array $request, array $params): array {
        $data = $request['data'];
        
        // Validasyon
        if (empty($data['messages'])) {
            return $this->errorResponse('messages alanı zorunludur', 400);
        }
        
        if (!is_array($data['messages'])) {
            return $this->errorResponse('messages alanı dizi olmalıdır', 400);
        }
        
        // Provider seçimi
        $providerName = $data['provider'] ?? null;
        
        if (!$providerName) {
            $providerName = APIMaster_APIFactory::selectBestProvider('chat', [
                'max_tokens' => $data['max_tokens'] ?? 1000
            ]);
        }
        
        if (!$providerName) {
            return $this->errorResponse('Uygun provider bulunamadı', 503);
        }
        
        // Provider instance'ı oluştur
        try {
            $provider = APIMaster_APIFactory::create($providerName);
            
            // Model ayarla
            if (isset($data['model']) && method_exists($provider, 'setModel')) {
                $provider->setModel($data['model']);
            }
            
            // Sıcaklık ayarla
            if (isset($data['temperature']) && method_exists($provider, 'setTemperature')) {
                $provider->setTemperature($data['temperature']);
            }
            
            // Max tokens ayarla
            if (isset($data['max_tokens']) && method_exists($provider, 'setMaxTokens')) {
                $provider->setMaxTokens($data['max_tokens']);
            }
            
            // Chat isteği gönder
            $startTime = microtime(true);
            $response = $provider->chat($data['messages'], $data);
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'success' => true,
                'data' => $response,
                'provider' => $providerName,
                'response_time' => round($responseTime, 2)
            ];
            
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Stream chat isteğini işle
     */
    private function handleChatStream(array $request, array $params): void {
        // Stream için özel header'lar
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        
        $data = $request['data'];
        
        // Validasyon
        if (empty($data['messages'])) {
            echo $this->formatStreamError('messages alanı zorunludur');
            return;
        }
        
        // Provider seçimi
        $providerName = $data['provider'] ?? APIMaster_APIFactory::selectBestProvider('chat');
        
        if (!$providerName) {
            echo $this->formatStreamError('Uygun provider bulunamadı');
            return;
        }
        
        try {
            $provider = APIMaster_APIFactory::create($providerName);
            
            // Stream handler
            $streamHandler = function($chunk) {
                echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };
            
            // Stream isteği gönder
            $provider->chat($data['messages'], array_merge($data, [
                'stream' => true,
                'stream_handler' => $streamHandler
            ]));
            
            echo "data: " . json_encode(['done' => true]) . "\n\n";
            
        } catch (Exception $e) {
            echo $this->formatStreamError($e->getMessage());
        }
    }
    
    /**
     * Completion isteğini işle
     */
    private function handleCompletion(array $request, array $params): array {
        $data = $request['data'];
        
        if (empty($data['prompt'])) {
            return $this->errorResponse('prompt alanı zorunludur', 400);
        }
        
        $providerName = $data['provider'] ?? APIMaster_APIFactory::selectBestProvider('chat');
        
        if (!$providerName) {
            return $this->errorResponse('Uygun provider bulunamadı', 503);
        }
        
        try {
            $provider = APIMaster_APIFactory::create($providerName);
            
            if (method_exists($provider, 'complete')) {
                $response = $provider->complete($data['prompt'], $data);
            } else {
                // Fallback: chat metodunu kullan
                $messages = [['role' => 'user', 'content' => $data['prompt']]];
                $response = $provider->chat($messages, $data);
            }
            
            return [
                'success' => true,
                'data' => $response,
                'provider' => $providerName
            ];
            
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Image generation isteğini işle
     */
    private function handleImageGeneration(array $request, array $params): array {
        $data = $request['data'];
        
        if (empty($data['prompt'])) {
            return $this->errorResponse('prompt alanı zorunludur', 400);
        }
        
        $providerName = $data['provider'] ?? APIMaster_APIFactory::selectBestProvider('image');
        
        if (!$providerName) {
            return $this->errorResponse('Uygun provider bulunamadı', 503);
        }
        
        try {
            $provider = APIMaster_APIFactory::create($providerName);
            
            if (!method_exists($provider, 'generateImage')) {
                return $this->errorResponse('Bu provider görsel üretimini desteklemiyor', 400);
            }
            
            $response = $provider->generateImage($data['prompt'], $data);
            
            return [
                'success' => true,
                'data' => $response,
                'provider' => $providerName
            ];
            
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Embedding isteğini işle
     */
    private function handleEmbeddings(array $request, array $params): array {
        $data = $request['data'];
        
        if (empty($data['input'])) {
            return $this->errorResponse('input alanı zorunludur', 400);
        }
        
        $providerName = $data['provider'] ?? APIMaster_APIFactory::selectBestProvider('embedding');
        
        if (!$providerName) {
            return $this->errorResponse('Uygun provider bulunamadı', 503);
        }
        
        try {
            $provider = APIMaster_APIFactory::create($providerName);
            
            if (!method_exists($provider, 'getEmbedding')) {
                return $this->errorResponse('Bu provider embedding üretimini desteklemiyor', 400);
            }
            
            $response = $provider->getEmbedding($data['input'], $data);
            
            return [
                'success' => true,
                'data' => $response,
                'provider' => $providerName
            ];
            
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Provider'ları getir
     */
    private function getProviders(array $request, array $params): array {
        $providers = APIMaster_APIFactory::getAllProviders();
        
        return [
            'success' => true,
            'data' => $providers,
            'total' => count($providers)
        ];
    }
    
    /**
     * Tek provider getir
     */
    private function getProvider(array $request, array $params): array {
        $providerName = $params['provider'] ?? null;
        
        if (!$providerName) {
            return $this->errorResponse('Provider adı gerekli', 400);
        }
        
        $providers = APIMaster_APIFactory::getAllProviders();
        
        if (!isset($providers[$providerName])) {
            return $this->errorResponse('Provider bulunamadı', 404);
        }
        
        return [
            'success' => true,
            'data' => $providers[$providerName]
        ];
    }
    
    /**
     * Provider modellerini getir
     */
    private function getProviderModels(array $request, array $params): array {
        $providerName = $params['provider'] ?? null;
        
        if (!$providerName) {
            return $this->errorResponse('Provider adı gerekli', 400);
        }
        
        try {
            $provider = APIMaster_APIFactory::create($providerName);
            
            $models = [];
            if (method_exists($provider, 'getModels')) {
                $models = $provider->getModels();
            } elseif (method_exists($provider, 'getModel')) {
                $models = [$provider->getModel()];
            }
            
            return [
                'success' => true,
                'data' => $models,
                'provider' => $providerName
            ];
            
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
    
    /**
     * Health check
     */
    private function healthCheck(array $request, array $params): array {
        $health = APIMaster_APIFactory::healthCheck();
        
        $overallHealth = true;
        foreach ($health as $provider) {
            if (!$provider['status']) {
                $overallHealth = false;
                break;
            }
        }
        
        return [
            'success' => true,
            'status' => $overallHealth ? 'healthy' : 'degraded',
            'providers' => $health,
            'timestamp' => time()
        ];
    }
    
    /**
     * Tek provider health check
     */
    private function healthCheckProvider(array $request, array $params): array {
        $providerName = $params['provider'] ?? null;
        
        if (!$providerName) {
            return $this->errorResponse('Provider adı gerekli', 400);
        }
        
        $health = APIMaster_APIFactory::healthCheck([$providerName]);
        
        return [
            'success' => true,
            'data' => $health[$providerName] ?? null,
            'provider' => $providerName
        ];
    }
    
    /**
     * Analytics getir
     */
    private function getAnalytics(array $request, array $params): array {
        // TODO: Analytics dosyasından veri oku
        return [
            'success' => true,
            'data' => [
                'total_requests' => 0,
                'providers_usage' => [],
                'response_times' => []
            ]
        ];
    }
    
    /**
     * Usage analytics getir
     */
    private function getUsageAnalytics(array $request, array $params): array {
        // TODO: Kullanım istatistiklerini oku
        return [
            'success' => true,
            'data' => [
                'daily' => [],
                'weekly' => [],
                'monthly' => []
            ]
        ];
    }
    
    /**
     * Sistem bilgisi getir
     */
    private function getSystemInfo(array $request, array $params): array {
        return [
            'success' => true,
            'data' => [
                'version' => '1.0.0',
                'php_version' => PHP_VERSION,
                'module_dir' => API_MASTER_MODULE_DIR,
                'providers_count' => count(APIMaster_APIFactory::getAllProviders())
            ]
        ];
    }
    
    /**
     * Sistem durumu getir
     */
    private function getSystemStatus(array $request, array $params): array {
        return [
            'success' => true,
            'data' => [
                'status' => 'running',
                'uptime' => time() - ($this->getStartTime() ?? time()),
                'memory_usage' => memory_get_usage(true),
                'timestamp' => time()
            ]
        ];
    }
    
    /**
     * Başlangıç zamanını getir
     */
    private function getStartTime(): ?int {
        $status_file = API_MASTER_MODULE_DIR . 'cache/status.json';
        if (file_exists($status_file)) {
            $status = json_decode(file_get_contents($status_file), true);
            return $status['start_time'] ?? null;
        }
        return null;
    }
    
    /**
     * API key doğrula
     * 
     * @param string|null $apiKey
     * @return bool
     */
    private function validateApiKey(?string $apiKey): bool {
        if (empty($apiKey)) {
            return false;
        }
        
        // Bearer token formatını temizle
        if (strpos($apiKey, 'Bearer ') === 0) {
            $apiKey = substr($apiKey, 7);
        }
        
        // API key'leri config dosyasından oku
        $api_keys_file = API_MASTER_MODULE_DIR . 'config/api-keys.php';
        if (file_exists($api_keys_file)) {
            $apiKeys = include $api_keys_file;
            if (is_array($apiKeys) && isset($apiKeys[$apiKey])) {
                return $apiKeys[$apiKey]['enabled'] ?? true;
            }
        }
        
        return false;
    }
    
    /**
     * Başarılı yanıt formatla
     * 
     * @param mixed $data
     * @return array
     */
    private function formatResponse($data): array {
        if (is_array($data) && isset($data['success'])) {
            return $data;
        }
        
        return [
            'success' => true,
            'data' => $data,
            'timestamp' => time()
        ];
    }
    
    /**
     * Hata yanıtı formatla
     * 
     * @param string $message
     * @param int $code
     * @return array
     */
    private function errorResponse(string $message, int $code = 400): array {
        http_response_code($code);
        
        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'timestamp' => time()
            ]
        ];
    }
    
    /**
     * Stream hatası formatla
     * 
     * @param string $message
     * @return string
     */
    private function formatStreamError(string $message): string {
        return "event: error\ndata: " . json_encode(['message' => $message]) . "\n\n";
    }
}