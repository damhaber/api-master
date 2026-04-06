<?php
/**
 * API Master - Constants Definition
 * 
 * @package APIMaster
 * @subpackage Includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class APIMaster_Constants
 * 
 * Sistem sabitlerini tanımlar
 */
class APIMaster_Constants {
    
    /**
     * Plugin version
     */
    const VERSION = '2.0.0';
    
    /**
     * Plugin prefix for options and transients
     */
    const PREFIX = 'api_master_';
    
    /**
     * API configuration constants
     */
    const DEFAULT_TIMEOUT = 30;
    const DEFAULT_RETRY_COUNT = 3;
    const DEFAULT_RETRY_DELAY = 1; // seconds
    
    /**
     * Cache constants
     */
    const CACHE_TTL_SHORT = 300;      // 5 minutes
    const CACHE_TTL_MEDIUM = 1800;    // 30 minutes
    const CACHE_TTL_LONG = 86400;     // 24 hours
    const CACHE_TTL_VERY_LONG = 604800; // 7 days
    
    /**
     * Rate limiting constants
     */
    const RATE_LIMIT_WINDOW = 60;      // 1 minute window
    const RATE_LIMIT_MAX_REQUESTS = 60; // 60 requests per minute
    const RATE_LIMIT_BLOCK_TTL = 300;   // 5 minutes block
    
    /**
     * Queue constants
     */
    const QUEUE_MAX_RETRIES = 5;
    const QUEUE_RETRY_DELAY = 60;       // seconds
    const QUEUE_BATCH_SIZE = 20;
    const QUEUE_WORKER_SLEEP = 5;       // seconds
    
    /**
     * Learning system constants
     */
    const LEARNING_SAMPLE_SIZE = 1000;
    const LEARNING_UPDATE_INTERVAL = 3600; // 1 hour
    const LEARNING_DECAY_FACTOR = 0.95;
    
    /**
     * Vector memory constants
     */
    const VECTOR_DIMENSION = 1536;       // OpenAI embedding dimension
    const VECTOR_INDEX_HNSW_M = 16;
    const VECTOR_INDEX_EF_CONSTRUCTION = 200;
    const VECTOR_MAX_ELEMENTS = 100000;
    
    /**
     * Logging constants
     */
    const LOG_LEVEL_ERROR = 1;
    const LOG_LEVEL_WARNING = 2;
    const LOG_LEVEL_INFO = 3;
    const LOG_LEVEL_DEBUG = 4;
    const LOG_MAX_FILES = 30;
    const LOG_MAX_SIZE = 10485760; // 10MB
    
    /**
     * API provider endpoints
     */
    const PROVIDER_OPENAI = 'openai';
    const PROVIDER_ANTHROPIC = 'anthropic';
    const PROVIDER_GOOGLE = 'google';
    const PROVIDER_COHERE = 'cohere';
    const PROVIDER_HUGGINGFACE = 'huggingface';
    const PROVIDER_REPLICATE = 'replicate';
    const PROVIDER_STABILITY = 'stability';
    const PROVIDER_MISTRAL = 'mistral';
    const PROVIDER_GROQ = 'groq';
    const PROVIDER_TOGETHER = 'together';
    const PROVIDER_FIREWORKS = 'fireworks';
    const PROVIDER_DEEPSEEK = 'deepseek';
    
    /**
     * API endpoint paths
     */
    const ENDPOINT_CHAT_COMPLETIONS = '/v1/chat/completions';
    const ENDPOINT_EMBEDDINGS = '/v1/embeddings';
    const ENDPOINT_COMPLETIONS = '/v1/completions';
    const ENDPOINT_MODELS = '/v1/models';
    const ENDPOINT_IMAGES_GENERATIONS = '/v1/images/generations';
    const ENDPOINT_AUDIO_TRANSCRIPTIONS = '/v1/audio/transcriptions';
    
    /**
     * Error codes
     */
    const ERROR_AUTH_FAILED = 'auth_failed';
    const ERROR_RATE_LIMIT = 'rate_limit';
    const ERROR_INVALID_REQUEST = 'invalid_request';
    const ERROR_PROVIDER_ERROR = 'provider_error';
    const ERROR_NETWORK_ERROR = 'network_error';
    const ERROR_TIMEOUT = 'timeout';
    const ERROR_QUOTA_EXCEEDED = 'quota_exceeded';
    const ERROR_MODEL_NOT_FOUND = 'model_not_found';
    const ERROR_INVALID_RESPONSE = 'invalid_response';
    
    /**
     * HTTP status codes
     */
    const HTTP_OK = 200;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_FORBIDDEN = 403;
    const HTTP_NOT_FOUND = 404;
    const HTTP_TOO_MANY_REQUESTS = 429;
    const HTTP_INTERNAL_ERROR = 500;
    const HTTP_SERVICE_UNAVAILABLE = 503;
    
    /**
     * Get all constants as array
     * 
     * @return array
     */
    public static function getAll() {
        return [
            'version' => self::VERSION,
            'prefix' => self::PREFIX,
            'timeout' => self::DEFAULT_TIMEOUT,
            'retry_count' => self::DEFAULT_RETRY_COUNT,
            'retry_delay' => self::DEFAULT_RETRY_DELAY,
            'cache' => [
                'short' => self::CACHE_TTL_SHORT,
                'medium' => self::CACHE_TTL_MEDIUM,
                'long' => self::CACHE_TTL_LONG,
                'very_long' => self::CACHE_TTL_VERY_LONG
            ],
            'rate_limit' => [
                'window' => self::RATE_LIMIT_WINDOW,
                'max_requests' => self::RATE_LIMIT_MAX_REQUESTS,
                'block_ttl' => self::RATE_LIMIT_BLOCK_TTL
            ],
            'providers' => self::getProvidersList(),
            'error_codes' => self::getErrorCodes(),
            'http_status' => self::getHttpStatusCodes()
        ];
    }
    
    /**
     * Get providers list
     * 
     * @return array
     */
    public static function getProvidersList() {
        return [
            self::PROVIDER_OPENAI,
            self::PROVIDER_ANTHROPIC,
            self::PROVIDER_GOOGLE,
            self::PROVIDER_COHERE,
            self::PROVIDER_HUGGINGFACE,
            self::PROVIDER_REPLICATE,
            self::PROVIDER_STABILITY,
            self::PROVIDER_MISTRAL,
            self::PROVIDER_GROQ,
            self::PROVIDER_TOGETHER,
            self::PROVIDER_FIREWORKS,
            self::PROVIDER_DEEPSEEK
        ];
    }
    
    /**
     * Get error codes list
     * 
     * @return array
     */
    public static function getErrorCodes() {
        return [
            self::ERROR_AUTH_FAILED,
            self::ERROR_RATE_LIMIT,
            self::ERROR_INVALID_REQUEST,
            self::ERROR_PROVIDER_ERROR,
            self::ERROR_NETWORK_ERROR,
            self::ERROR_TIMEOUT,
            self::ERROR_QUOTA_EXCEEDED,
            self::ERROR_MODEL_NOT_FOUND,
            self::ERROR_INVALID_RESPONSE
        ];
    }
    
    /**
     * Get HTTP status codes
     * 
     * @return array
     */
    public static function getHttpStatusCodes() {
        return [
            self::HTTP_OK,
            self::HTTP_BAD_REQUEST,
            self::HTTP_UNAUTHORIZED,
            self::HTTP_FORBIDDEN,
            self::HTTP_NOT_FOUND,
            self::HTTP_TOO_MANY_REQUESTS,
            self::HTTP_INTERNAL_ERROR,
            self::HTTP_SERVICE_UNAVAILABLE
        ];
    }
    
    /**
     * Check if constant exists
     * 
     * @param string $name
     * @return bool
     */
    public static function has($name) {
        return defined('self::' . $name);
    }
    
    /**
     * Get constant value
     * 
     * @param string $name
     * @return mixed|null
     */
    public static function get($name) {
        $constant = 'self::' . $name;
        return defined($constant) ? constant($constant) : null;
    }
}