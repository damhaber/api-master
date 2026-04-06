<?php
/**
 * Replicate API Class for Masal Panel
 * 
 * AI model hosting, image generation, text generation, model training
 * 
 * @package MasalPanel
 * @subpackage APIMaster
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Direct access prevention
}

class APIMaster_API_Replicate implements APIMaster_APIInterface
{
    /**
     * API Token
     * @var string
     */
    private $apiToken;
    
    /**
     * Model (owner/model format)
     * @var string
     */
    private $model;
    
    /**
     * Config array
     * @var array
     */
    private $config;
    
    /**
     * API base URL
     * @var string
     */
    private $apiUrl = 'https://api.replicate.com/v1';
    
    /**
     * Default model owner
     * @var string
     */
    private $defaultOwner = 'stability-ai';
    
    /**
     * Default model name
     * @var string
     */
    private $defaultModel = 'stable-diffusion';
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->apiToken = $this->config['api_token'] ?? '';
        $this->defaultOwner = $this->config['default_owner'] ?? 'stability-ai';
        $this->defaultModel = $this->config['default_model'] ?? 'stable-diffusion';
        $this->model = $this->defaultOwner . '/' . $this->defaultModel;
    }
    
    /**
     * Load configuration from JSON file
     * 
     * @return array Configuration data
     */
    private function loadConfig()
    {
        $configFile = __DIR__ . '/config/replicate.json';
        
        if (file_exists($configFile)) {
            $content = file_get_contents($configFile);
            return json_decode($content, true) ?: [];
        }
        
        return [];
    }
    
    /**
     * Log error to file
     * 
     * @param string $message Error message
     * @param array $context Additional context
     */
    private function logError($message, $context = [])
    {
        $logDir = __DIR__ . '/logs';
        $logFile = $logDir . '/replicate-error.log';
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logEntry = date('Y-m-d H:i:s') . ' - ' . $message;
        if (!empty($context)) {
            $logEntry .= ' - ' . json_encode($context);
        }
        $logEntry .= PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Get request headers
     * 
     * @return array Headers
     */
    private function getHeaders()
    {
        return [
            'Authorization: Token ' . $this->apiToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
    }
    
    /**
     * Make curl request to Replicate API
     * 
     * @param string $url Full URL
     * @param string $method HTTP method
     * @param array|string|null $data Request data
     * @return array|false Response data
     */
    private function curlRequest($url, $method = 'GET', $data = null)
    {
        $ch = curl_init();
        
        $method = strtoupper($method);
        
        // Build URL with query parameters for GET
        if ($method === 'GET' && is_array($data) && !empty($data)) {
            $url .= '?' . http_build_query($data);
            $data = null;
        }
        
        // Set common options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // Models can take time
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());
        
        // Set method specific options
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        curl_close($ch);
        
        if ($curlError) {
            $this->logError('CURL Error', ['error' => $curlError, 'url' => $url]);
            return false;
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMsg = isset($decoded['detail']) ? $decoded['detail'] : 
                       (isset($decoded['error']) ? $decoded['error'] : 'HTTP ' . $httpCode);
            $this->logError('API Error', ['status' => $httpCode, 'error' => $errorMsg, 'url' => $url]);
            return false;
        }
        
        return $decoded;
    }
    
    /**
     * API request wrapper
     * 
     * @param string $endpoint Endpoint
     * @param string $method HTTP method
     * @param array $data Request data
     * @return array|false Response
     */
    private function request($endpoint, $method = 'GET', $data = [])
    {
        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');
        return $this->curlRequest($url, $method, $data);
    }
    
    /**
     * Wait for prediction to complete
     * 
     * @param string $predictionId Prediction ID
     * @param int $maxWait Maximum wait time in seconds
     * @param int $interval Check interval in seconds
     * @return array|false Completed prediction
     */
    private function waitForPrediction($predictionId, $maxWait = 300, $interval = 2)
    {
        $startTime = time();
        
        while (time() - $startTime < $maxWait) {
            $prediction = $this->getPrediction($predictionId);
            
            if (!$prediction) {
                $this->logError('Failed to get prediction status', ['id' => $predictionId]);
                return false;
            }
            
            $status = $prediction['status'] ?? '';
            
            if ($status === 'succeeded') {
                return $prediction;
            } elseif ($status === 'failed') {
                $this->logError('Prediction failed', [
                    'id' => $predictionId,
                    'error' => $prediction['error'] ?? 'Unknown error'
                ]);
                return false;
            } elseif ($status === 'canceled') {
                $this->logError('Prediction cancelled', ['id' => $predictionId]);
                return false;
            }
            
            sleep($interval);
        }
        
        $this->logError('Prediction timeout', ['id' => $predictionId, 'max_wait' => $maxWait]);
        return false;
    }
    
    /**
     * Set API key
     * 
     * @param string $apiKey Replicate API token
     * @return void
     */
    public function setApiKey($apiKey)
    {
        $this->apiToken = $apiKey;
    }
    
    /**
     * Set model
     * 
     * @param string $model Model name (format: owner/model)
     * @return void
     */
    public function setModel($model)
    {
        $this->model = $model;
    }
    
    /**
     * Get current model
     * 
     * @return string Current model
     */
    public function getModel()
    {
        return $this->model;
    }
    
    /**
     * Complete request (generic method)
     * 
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @return array|false Response
     */
    public function complete($endpoint, $params = [])
    {
        return $this->request($endpoint, 'GET', $params);
    }
    
    /**
     * Stream (not supported by Replicate)
     * 
     * @param string $endpoint API endpoint
     * @param callable $callback Callback function
     * @return void
     */
    public function stream($endpoint, $callback)
    {
        $this->logError('Stream method not supported by Replicate API. Use waitForPrediction() for async results.');
    }
    
    /**
     * Get available models
     * 
     * @return array|false List of models
     */
    public function getModels()
    {
        return $this->listModels();
    }
    
    /**
     * Get API capabilities
     * 
     * @return array Capabilities list
     */
    public function getCapabilities()
    {
        return [
            'image_generation' => ['create'],
            'text_generation' => ['create'],
            'image_to_image' => ['create'],
            'text_to_speech' => ['create'],
            'speech_to_text' => ['create'],
            'video_generation' => ['create'],
            'model_training' => ['create', 'read'],
            'image_upscaling' => ['create'],
            'background_removal' => ['create'],
            'predictions' => ['read', 'cancel'],
            'webhooks' => ['create']
        ];
    }
    
    /**
     * Check API health
     * 
     * @return bool Connection successful
     */
    public function checkHealth()
    {
        return $this->testConnection();
    }
    
    /**
     * Chat (text generation)
     * 
     * @param string $message Chat message
     * @param array $context Context parameters
     * @return array|false Response
     */
    public function chat($message, $context = [])
    {
        $model = $context['model'] ?? 'llama-2-70b';
        $options = $context['options'] ?? [];
        $options['prompt'] = $message;
        
        return $this->generateText($message, $model, $options);
    }
    
    /**
     * Extract text from response
     * 
     * @param array|string $response API response
     * @return string Extracted text
     */
    public function extractText($response)
    {
        if (is_string($response)) {
            return $response;
        }
        
        if (is_array($response)) {
            if (isset($response['output'])) {
                if (is_array($response['output'])) {
                    return implode('', $response['output']);
                }
                return $response['output'];
            }
            if (isset($response['text'])) {
                return $response['text'];
            }
            return json_encode($response);
        }
        
        return '';
    }
    
    // ========== REPLICATE SPECIFIC METHODS ==========
    
    /**
     * Run a model (create prediction)
     * 
     * @param string $modelOwner Model owner
     * @param string $modelName Model name
     * @param array $input Model input parameters
     * @param string|null $version Model version (optional)
     * @return array|false Prediction data
     */
    public function runModel($modelOwner, $modelName, $input, $version = null)
    {
        $modelIdentifier = $modelOwner . '/' . $modelName;
        
        if ($version) {
            $modelIdentifier .= ':' . $version;
        }
        
        $data = [
            'version' => $modelIdentifier,
            'input' => $input,
        ];
        
        return $this->request('predictions', 'POST', $data);
    }
    
    /**
     * Get prediction status
     * 
     * @param string $predictionId Prediction ID
     * @return array|false Prediction data
     */
    public function getPrediction($predictionId)
    {
        return $this->request('predictions/' . $predictionId, 'GET');
    }
    
    /**
     * Cancel prediction
     * 
     * @param string $predictionId Prediction ID
     * @return bool Success
     */
    public function cancelPrediction($predictionId)
    {
        $response = $this->request('predictions/' . $predictionId . '/cancel', 'POST');
        return ($response !== false);
    }
    
    /**
     * Generate image (Stable Diffusion)
     * 
     * @param string $prompt Image description
     * @param array $options Options
     * @return array|false Generated images
     */
    public function generateImage($prompt, $options = [])
    {
        $input = array_merge([
            'prompt' => $prompt,
            'negative_prompt' => $options['negative_prompt'] ?? '',
            'num_outputs' => $options['num_outputs'] ?? 1,
            'scheduler' => $options['scheduler'] ?? 'DPMSolverMultistep',
            'num_inference_steps' => $options['steps'] ?? 25,
            'guidance_scale' => $options['guidance_scale'] ?? 7.5,
            'width' => $options['width'] ?? 512,
            'height' => $options['height'] ?? 512,
        ], $options);
        
        $prediction = $this->runModel('stability-ai', 'stable-diffusion', $input);
        
        if ($prediction && isset($prediction['id'])) {
            return $this->waitForPrediction($prediction['id']);
        }
        
        return false;
    }
    
    /**
     * Generate image variations
     * 
     * @param string $imageUrl Source image URL
     * @param int $numOutputs Number of outputs
     * @return array|false Variations
     */
    public function generateVariations($imageUrl, $numOutputs = 4)
    {
        $input = [
            'image' => $imageUrl,
            'num_outputs' => $numOutputs,
        ];
        
        $prediction = $this->runModel('stability-ai', 'stable-diffusion-image-variations', $input);
        
        if ($prediction && isset($prediction['id'])) {
            return $this->waitForPrediction($prediction['id']);
        }
        
        return false;
    }
    
    /**
     * Image to image transformation (img2img)
     * 
     * @param string $initImage Initial image URL
     * @param string $prompt Target description
     * @param float $strength Transformation strength (0-1)
     * @return array|false Result image
     */
    public function imageToImage($initImage, $prompt, $strength = 0.8)
    {
        $input = [
            'image' => $initImage,
            'prompt' => $prompt,
            'strength' => $strength,
        ];
        
        $prediction = $this->runModel('stability-ai', 'stable-diffusion-img2img', $input);
        
        if ($prediction && isset($prediction['id'])) {
            return $this->waitForPrediction($prediction['id']);
        }
        
        return false;
    }
    
    /**
     * Generate text (LLM)
     * 
     * @param string $prompt Text prompt
     * @param string $model Model name
     * @param array $options Model options
     * @return string|false Generated text
     */
    public function generateText($prompt, $model = 'llama-2-70b', $options = [])
    {
        $modelMap = [
            'llama-2-70b' => 'meta/llama-2-70b-chat',
            'llama-2-13b' => 'meta/llama-2-13b-chat',
            'llama-2-7b' => 'meta/llama-2-7b-chat',
            'vicuna-13b' => 'replicate/vicuna-13b',
            'mistral-7b' => 'mistralai/mistral-7b-v0.1',
        ];
        
        $modelIdentifier = $modelMap[$model] ?? $model;
        $parts = explode('/', $modelIdentifier);
        
        $input = array_merge([
            'prompt' => $prompt,
            'max_tokens' => $options['max_tokens'] ?? 500,
            'temperature' => $options['temperature'] ?? 0.75,
            'top_p' => $options['top_p'] ?? 0.9,
        ], $options);
        
        $prediction = $this->runModel($parts[0], $parts[1], $input);
        
        if ($prediction && isset($prediction['id'])) {
            $result = $this->waitForPrediction($prediction['id']);
            
            if ($result && isset($result['output'])) {
                if (is_array($result['output'])) {
                    return implode('', $result['output']);
                }
                return $result['output'];
            }
        }
        
        return false;
    }
    
    /**
     * Image to text (BLIP)
     * 
     * @param string $imageUrl Image URL
     * @param string $prompt Prompt
     * @return string|false Description
     */
    public function imageToText($imageUrl, $prompt = 'Describe this image in detail')
    {
        $input = [
            'image' => $imageUrl,
            'prompt' => $prompt,
        ];
        
        $prediction = $this->runModel('salesforce', 'blip', $input);
        
        if ($prediction && isset($prediction['id'])) {
            $result = $this->waitForPrediction($prediction['id']);
            
            if ($result && isset($result['output'])) {
                return $result['output'];
            }
        }
        
        return false;
    }
    
    /**
     * List models
     * 
     * @param array $filters Filters
     * @return array|false Model list
     */
    public function listModels($filters = [])
    {
        return $this->request('models', 'GET', $filters);
    }
    
    /**
     * Get model details
     * 
     * @param string $modelOwner Model owner
     * @param string $modelName Model name
     * @return array|false Model details
     */
    public function getModel($modelOwner, $modelName)
    {
        return $this->request('models/' . $modelOwner . '/' . $modelName, 'GET');
    }
    
    /**
     * Get model versions
     * 
     * @param string $modelOwner Model owner
     * @param string $modelName Model name
     * @return array|false Versions list
     */
    public function getModelVersions($modelOwner, $modelName)
    {
        return $this->request('models/' . $modelOwner . '/' . $modelName . '/versions', 'GET');
    }
    
    /**
     * Start training
     * 
     * @param string $modelOwner Model owner
     * @param string $modelName Model name
     * @param string $destination Destination model
     * @param array $input Training input
     * @return array|false Training data
     */
    public function startTraining($modelOwner, $modelName, $destination, $input)
    {
        $data = [
            'destination' => $destination,
            'input' => $input,
        ];
        
        return $this->request('models/' . $modelOwner . '/' . $modelName . '/versions', 'POST', $data);
    }
    
    /**
     * Get training status
     * 
     * @param string $trainingId Training ID
     * @return array|false Training data
     */
    public function getTraining($trainingId)
    {
        return $this->request('trainings/' . $trainingId, 'GET');
    }
    
    /**
     * List hardware options
     * 
     * @return array|false Hardware list
     */
    public function listHardware()
    {
        return $this->request('hardware', 'GET');
    }
    
    /**
     * Create webhook
     * 
     * @param string $url Webhook URL
     * @param array $events Events
     * @return array|false Webhook data
     */
    public function createWebhook($url, $events = ['prediction.created', 'prediction.succeeded', 'prediction.failed'])
    {
        $data = [
            'url' => $url,
            'events' => $events,
        ];
        
        return $this->request('webhooks', 'POST', $data);
    }
    
    /**
     * List collections
     * 
     * @return array|false Collections
     */
    public function listCollections()
    {
        return $this->request('collections', 'GET');
    }
    
    /**
     * Get collection details
     * 
     * @param string $collectionSlug Collection slug
     * @return array|false Collection details
     */
    public function getCollection($collectionSlug)
    {
        return $this->request('collections/' . $collectionSlug, 'GET');
    }
    
    /**
     * Get account info
     * 
     * @return array|false Account info
     */
    public function getAccount()
    {
        return $this->request('account', 'GET');
    }
    
    /**
     * List deployments
     * 
     * @return array|false Deployments
     */
    public function listDeployments()
    {
        return $this->request('deployments', 'GET');
    }
    
    /**
     * Upscale image
     * 
     * @param string $imageUrl Image URL
     * @param int $scale Scale factor (2x, 4x)
     * @return array|false Upscaled image
     */
    public function upscaleImage($imageUrl, $scale = 2)
    {
        $input = [
            'image' => $imageUrl,
            'scale' => $scale,
        ];
        
        $prediction = $this->runModel('nightmareai', 'real-esrgan', $input);
        
        if ($prediction && isset($prediction['id'])) {
            return $this->waitForPrediction($prediction['id']);
        }
        
        return false;
    }
    
    /**
     * Remove background from image
     * 
     * @param string $imageUrl Image URL
     * @return array|false Image without background
     */
    public function removeBackground($imageUrl)
    {
        $input = ['image' => $imageUrl];
        
        $prediction = $this->runModel('cjwbw', 'rembg', $input);
        
        if ($prediction && isset($prediction['id'])) {
            return $this->waitForPrediction($prediction['id']);
        }
        
        return false;
    }
    
    /**
     * Text to speech
     * 
     * @param string $text Text
     * @param string $voice Voice (af, belle, etc)
     * @return string|false Audio URL
     */
    public function textToSpeech($text, $voice = 'af')
    {
        $input = [
            'text' => $text,
            'voice' => $voice,
        ];
        
        $prediction = $this->runModel('cjwbw', 'bark', $input);
        
        if ($prediction && isset($prediction['id'])) {
            $result = $this->waitForPrediction($prediction['id']);
            
            if ($result && isset($result['output'])) {
                return $result['output'];
            }
        }
        
        return false;
    }
    
    /**
     * Speech to text
     * 
     * @param string $audioUrl Audio file URL
     * @return string|false Transcript
     */
    public function speechToText($audioUrl)
    {
        $input = ['audio' => $audioUrl];
        
        $prediction = $this->runModel('vaibhavs10', 'insanely-fast-whisper', $input);
        
        if ($prediction && isset($prediction['id'])) {
            $result = $this->waitForPrediction($prediction['id']);
            
            if ($result && isset($result['output'])) {
                return $result['output'];
            }
        }
        
        return false;
    }
    
    /**
     * Generate video from text
     * 
     * @param string $prompt Video description
     * @param int $numFrames Number of frames
     * @return array|false Video URL
     */
    public function generateVideo($prompt, $numFrames = 16)
    {
        $input = [
            'prompt' => $prompt,
            'num_frames' => $numFrames,
        ];
        
        $prediction = $this->runModel('anotherjesse', 'zeroscope-v2-xl', $input);
        
        if ($prediction && isset($prediction['id'])) {
            return $this->waitForPrediction($prediction['id']);
        }
        
        return false;
    }
    
    /**
     * Test connection
     * 
     * @return bool Connection successful
     */
    public function testConnection()
    {
        $response = $this->request('account', 'GET');
        return ($response !== false && isset($response['username']));
    }
}