<?php
/**
 * API Master Module - Stable Diffusion API
 * Text-to-image and image-to-image generation
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_StableDiffusion implements APIMaster_APIInterface {
    
    /**
     * API base URL (Stability AI default)
     */
    private $api_url = 'https://api.stability.ai/v1';
    
    /**
     * API key
     */
    private $api_key;
    
    /**
     * Current model
     */
    private $model = 'sdxl';
    
    /**
     * Request timeout
     */
    private $timeout = 120;
    
    /**
     * Available models mapping
     */
    private $models = [
        'sd3' => 'stable-image-ultra-v1',
        'sd3-medium' => 'stable-image-core-v1',
        'sdxl' => 'stable-diffusion-xl-1024-v1-0',
        'sd21' => 'stable-diffusion-512-v2-1',
        'sd16' => 'stable-diffusion-v1-6'
    ];
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct(array $config = []) {
        $this->api_key = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'sdxl';
        $this->timeout = $config['timeout'] ?? 120;
        
        if (!empty($config['api_url'])) {
            $this->api_url = rtrim($config['api_url'], '/');
        }
    }
    
    /**
     * Set API key
     * 
     * @param string $api_key
     * @return self
     */
    public function setApiKey($api_key) {
        $this->api_key = $api_key;
        return $this;
    }
    
    /**
     * Set model
     * 
     * @param string $model
     * @return self
     */
    public function setModel($model) {
        if (isset($this->models[$model])) {
            $this->model = $model;
        }
        return $this;
    }
    
    /**
     * Get current model
     * 
     * @return string
     */
    public function getModel() {
        return $this->model;
    }
    
    /**
     * Generate image from text prompt
     * 
     * @param string $prompt Text prompt
     * @param array $options Generation options
     * @return array|false Response or false on error
     */
    public function generateImage($prompt, $options = []) {
        if (empty($this->api_key)) {
            return false;
        }
        
        $params = array_merge([
            'text_prompts' => [
                ['text' => $prompt, 'weight' => 1.0]
            ],
            'cfg_scale' => 7.0,
            'steps' => 30,
            'width' => 1024,
            'height' => 1024,
            'samples' => 1
        ], $options);
        
        // Add negative prompt if provided
        if (!empty($options['negative_prompt'])) {
            $params['text_prompts'][] = [
                'text' => $options['negative_prompt'],
                'weight' => -1.0
            ];
        }
        
        // Add seed if provided
        if (!empty($options['seed'])) {
            $params['seed'] = (int) $options['seed'];
        }
        
        // Add style preset if provided
        if (!empty($options['style_preset']) && $options['style_preset'] !== 'none') {
            $params['style_preset'] = $options['style_preset'];
        }
        
        $model_id = $this->models[$this->model] ?? $this->models['sdxl'];
        $endpoint = '/generation/' . $model_id . '/text-to-image';
        
        return $this->make_request($endpoint, $params);
    }
    
    /**
     * Image-to-image transformation
     * 
     * @param string $image_path Path to image or base64 data
     * @param string $prompt Text prompt
     * @param array $options Transformation options
     * @return array|false Response or false on error
     */
    public function imageToImage($image_path, $prompt, $options = []) {
        if (empty($this->api_key)) {
            return false;
        }
        
        $params = array_merge([
            'text_prompts' => [
                ['text' => $prompt, 'weight' => 1.0]
            ],
            'image_strength' => 0.75,
            'cfg_scale' => 7.0,
            'steps' => 30,
            'samples' => 1
        ], $options);
        
        // Add negative prompt if provided
        if (!empty($options['negative_prompt'])) {
            $params['text_prompts'][] = [
                'text' => $options['negative_prompt'],
                'weight' => -1.0
            ];
        }
        
        // Encode image
        $params['init_image'] = $this->encodeImage($image_path);
        
        $model_id = $this->models[$this->model] ?? $this->models['sdxl'];
        $endpoint = '/generation/' . $model_id . '/image-to-image';
        
        return $this->make_request($endpoint, $params);
    }
    
    /**
     * Upscale image
     * 
     * @param string $image_path Path to image or base64 data
     * @param array $options Upscale options
     * @return array|false Response or false on error
     */
    public function upscaleImage($image_path, $options = []) {
        if (empty($this->api_key)) {
            return false;
        }
        
        $params = [
            'image' => $this->encodeImage($image_path)
        ];
        
        if (!empty($options['width'])) {
            $params['width'] = $options['width'];
        }
        
        if (!empty($options['height'])) {
            $params['height'] = $options['height'];
        }
        
        $model_id = $this->models[$this->model] ?? $this->models['sdxl'];
        $endpoint = '/generation/' . $model_id . '/image-to-image/upscale';
        
        return $this->make_request($endpoint, $params);
    }
    
    /**
     * Encode image to base64
     * 
     * @param string $image_path Path or base64 data
     * @return string
     */
    private function encodeImage($image_path) {
        // Already base64
        if (preg_match('/^data:image\/\w+;base64,/', $image_path)) {
            return $image_path;
        }
        
        // File path
        if (file_exists($image_path)) {
            $image_data = file_get_contents($image_path);
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $image_path);
            finfo_close($finfo);
            return 'data:' . $mime_type . ';base64,' . base64_encode($image_data);
        }
        
        // Assume raw base64
        return 'data:image/png;base64,' . $image_path;
    }
    
    /**
     * Make HTTP request to Stability AI API
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array|false
     */
    private function make_request($endpoint, $data) {
        $url = $this->api_url . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            return false;
        }
        
        $decoded = json_decode($response, true);
        
        if ($http_code !== 200) {
            return false;
        }
        
        // Extract images from response
        if (isset($decoded['artifacts'])) {
            $decoded['images'] = $decoded['artifacts'];
        }
        
        return $decoded;
    }
    
    /**
     * Complete a prompt (generate image)
     * Required by APIMaster_APIInterface
     * 
     * @param string $prompt User prompt
     * @param array $options Additional options
     * @return array|false Response or false on error
     */
    public function complete($prompt, $options = []) {
        return $this->generateImage($prompt, $options);
    }
    
    /**
     * Stream completion (not supported for image generation)
     * Required by APIMaster_APIInterface
     * 
     * @param string $prompt User prompt
     * @param callable $callback Callback function
     * @param array $options Additional options
     * @return bool
     */
    public function stream($prompt, $callback, $options = []) {
        // Image generation doesn't support streaming
        $result = $this->complete($prompt, $options);
        if ($result && is_callable($callback)) {
            call_user_func($callback, ['complete' => true, 'result' => $result]);
        }
        return $result !== false;
    }
    
    /**
     * Get available models
     * Required by APIMaster_APIInterface
     * 
     * @return array
     */
    public function getModels() {
        return [
            'sd3' => [
                'name' => 'Stable Image Ultra v1',
                'max_resolution' => '1024x1024',
                'supports_upscale' => true
            ],
            'sd3-medium' => [
                'name' => 'Stable Image Core v1',
                'max_resolution' => '1024x1024',
                'supports_upscale' => true
            ],
            'sdxl' => [
                'name' => 'Stable Diffusion XL 1.0',
                'max_resolution' => '1024x1024',
                'supports_upscale' => true
            ],
            'sd21' => [
                'name' => 'Stable Diffusion 2.1',
                'max_resolution' => '768x768',
                'supports_upscale' => false
            ],
            'sd16' => [
                'name' => 'Stable Diffusion 1.6',
                'max_resolution' => '512x512',
                'supports_upscale' => false
            ]
        ];
    }
    
    /**
     * Get API capabilities
     * Required by APIMaster_APIInterface
     * 
     * @return array
     */
    public function getCapabilities() {
        return [
            'text_to_image' => true,
            'image_to_image' => true,
            'upscale' => true,
            'streaming' => false,
            'max_width' => 1024,
            'max_height' => 1024,
            'supported_formats' => ['png', 'jpeg', 'webp']
        ];
    }
    
    /**
     * Check API health
     * Required by APIMaster_APIInterface
     * 
     * @return bool
     */
    public function checkHealth() {
        if (empty($this->api_key)) {
            return false;
        }
        
        // Simple test request with minimal parameters
        $result = $this->generateImage('test', [
            'steps' => 1,
            'samples' => 1
        ]);
        
        return $result !== false;
    }
    
    /**
     * Chat method (not supported)
     * Required by APIMaster_APIInterface
     * 
     * @param array $messages Chat messages
     * @param array $options Additional options
     * @param callable|null $callback Optional callback
     * @return array|bool
     */
    public function chat($messages, $options = [], $callback = null) {
        // Not supported for image generation
        return false;
    }
    
    /**
     * Extract images from response
     * 
     * @param array $response API response
     * @return array
     */
    public function extractImages($response) {
        if (isset($response['images'])) {
            return $response['images'];
        }
        
        if (isset($response['artifacts'])) {
            return $response['artifacts'];
        }
        
        return [];
    }
    
    /**
     * Save generated image to file
     * 
     * @param array $image Image data (base64)
     * @param string $output_path Output file path
     * @return bool
     */
    public function saveImage($image, $output_path) {
        $base64 = $image['base64'] ?? '';
        
        if (empty($base64)) {
            return false;
        }
        
        // Remove data URL prefix if present
        $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
        
        $image_data = base64_decode($base64);
        if ($image_data === false) {
            return false;
        }
        
        return file_put_contents($output_path, $image_data) !== false;
    }
}