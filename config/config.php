<?php
/**
 * API Master - Main Configuration File
 * 
 * @package APIMaster
 * @subpackage Config
 * @since 1.0.0
 * 
 * IMPORTANT: This is a STANDALONE module, NOT a WordPress plugin!
 * No WordPress functions are used here.
 */

if (!defined('ABSPATH')) {
    exit; // Only for file security, not WordPress dependency
}

return [
    /*
    |--------------------------------------------------------------------------
    | API Master Configuration
    |--------------------------------------------------------------------------
    | This file contains the main configuration for the API Master module.
    | All settings are JSON-based, no database required.
    */
    
    /*
    |--------------------------------------------------------------------------
    | Module Information
    |--------------------------------------------------------------------------
    */
    'module' => [
        'name' => 'API Master',
        'version' => '1.1.0',
        'author' => 'API Master Team',
        'license' => 'MIT',
        'description' => 'Advanced API Management Module with AI Learning',
        'is_standalone' => true,
        'wordpress_independent' => true
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Path Configuration
    |--------------------------------------------------------------------------
    */
    'paths' => [
        'root' => dirname(__DIR__),
        'config' => dirname(__DIR__) . '/config',
        'data' => dirname(__DIR__) . '/data',
        'cache' => dirname(__DIR__) . '/cache',
        'logs' => dirname(__DIR__) . '/logs',
        'backups' => dirname(__DIR__) . '/backups',
        'temp' => dirname(__DIR__) . '/temp',
        'vector_data' => dirname(__DIR__) . '/data/vectors',
        'learning_data' => dirname(__DIR__) . '/data/learning'
    ],
    
    /*
    |--------------------------------------------------------------------------
    | API Default Settings
    |--------------------------------------------------------------------------
    */
    'api' => [
        'default_timeout' => 30,
        'max_timeout' => 120,
        'default_retry_count' => 3,
        'max_retry_count' => 5,
        'retry_delay' => 1000, // milliseconds
        'retry_backoff' => true,
        'user_agent' => 'API-Master/1.0',
        'verify_ssl' => true,
        'allow_redirects' => true,
        'max_redirects' => 5,
        'compression' => true,
        'keep_alive' => true
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        'enabled' => true,
        'storage' => 'file', // file, redis, memcached
        'default_limit' => 1000,
        'default_period' => 3600, // seconds
        'algorithm' => 'token_bucket', // token_bucket, sliding_window, fixed_window
        'headers_enabled' => true,
        'headers' => [
            'limit' => 'X-RateLimit-Limit',
            'remaining' => 'X-RateLimit-Remaining',
            'reset' => 'X-RateLimit-Reset'
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'driver' => 'file', // file, redis, memcached, apcu
        'default_ttl' => 3600, // seconds
        'max_ttl' => 86400, // 24 hours
        'prefix' => 'apimaster_cache_',
        'compression' => true,
        'serialization' => 'json', // json, php, igbinary
        'cleanup_probability' => 0.01, // 1% chance on each write
        'max_size' => 100 * 1024 * 1024 // 100MB
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => true,
        'driver' => 'file', // file, syslog, rotating_file
        'level' => 'info', // debug, info, warning, error, critical
        'file_path' => dirname(__DIR__) . '/logs/api-master.log',
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'max_files' => 30,
        'log_requests' => true,
        'log_responses' => false,
        'log_headers' => false,
        'sanitize_logs' => true,
        'format' => 'json' // json, line, custom
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Vector Database Configuration
    |--------------------------------------------------------------------------
    */
    'vector_db' => [
        'enabled' => true,
        'dimension' => 1536, // OpenAI embedding dimension
        'index_type' => 'hnsw', // hnsw, flat, ivf
        'similarity_metric' => 'cosine', // cosine, euclidean, dot_product
        'hnsw' => [
            'max_levels' => 10,
            'ef_construction' => 200,
            'ef_search' => 50,
            'max_connections' => 16
        ],
        'quantization' => [
            'enabled' => false,
            'type' => 'scalar', // scalar, product, binary
            'bits' => 8
        ],
        'memory' => [
            'short_term_limit' => 1000,
            'long_term_limit' => 100000,
            'consolidation_threshold' => 0.7
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Learning System Configuration
    |--------------------------------------------------------------------------
    */
    'learning' => [
        'enabled' => true,
        'auto_learn' => true,
        'confidence_threshold' => 0.7,
        'feedback_required' => true,
        'model' => [
            'type' => 'lightgbm', // lightgbm, neural, logistic
            'max_depth' => 10,
            'learning_rate' => 0.01,
            'n_estimators' => 100
        ],
        'training' => [
            'batch_size' => 32,
            'epochs' => 10,
            'validation_split' => 0.2,
            'early_stopping' => true,
            'patience' => 3
        ],
        'features' => [
            'intent_recognition' => true,
            'entity_extraction' => true,
            'sentiment_analysis' => true,
            'language_detection' => true
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'default' => 'openai',
        'fallback_enabled' => true,
        'load_balancing' => 'round_robin', // round_robin, least_connections, random, weighted
        'health_check' => [
            'enabled' => true,
            'interval' => 300, // seconds
            'timeout' => 5,
            'endpoint' => '/health'
        ],
        'circuit_breaker' => [
            'enabled' => true,
            'failure_threshold' => 5,
            'timeout' => 60, // seconds
            'half_open_attempts' => 3
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */
    'security' => [
        'api_key_encryption' => true,
        'encryption_algo' => 'AES-256-GCM',
        'hash_algo' => 'sha256',
        'key_rotation_days' => 90,
        'rate_limit_by_ip' => true,
        'blocked_ips' => [],
        'allowed_ips' => [],
        'jwt_enabled' => false,
        'jwt_ttl' => 3600,
        'cors_enabled' => true,
        'cors_origins' => ['*'],
        'cors_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'cors_headers' => ['Content-Type', 'Authorization', 'X-API-Key']
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'driver' => 'file', // file, redis, beanstalkd, sqs
        'default_queue' => 'default',
        'retry_after' => 90,
        'max_attempts' => 3,
        'timeout' => 60,
        'batch_size' => 50,
        'worker_sleep' => 3, // seconds
        'failed_queue' => 'failed'
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'enabled' => true,
        'max_retries' => 5,
        'retry_delays' => [0, 60, 300, 900, 3600], // seconds
        'timeout' => 10,
        'concurrent_limit' => 10,
        'signature_header' => 'X-Webhook-Signature',
        'signature_algo' => 'sha256',
        'payload_version' => '1.0'
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'enabled' => true,
        'metrics_enabled' => true,
        'health_check_enabled' => true,
        'alerting' => [
            'enabled' => false,
            'channels' => ['email', 'slack'],
            'thresholds' => [
                'error_rate' => 0.05, // 5%
                'response_time' => 5000, // milliseconds
                'availability' => 0.99 // 99%
            ]
        ],
        'statistics' => [
            'retention_days' => 90,
            'aggregation_interval' => 3600 // seconds
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Performance Optimization
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'async_processing' => true,
        'parallel_requests' => true,
        'max_parallel' => 5,
        'connection_pooling' => true,
        'pool_size' => 10,
        'prefork_enabled' => false,
        'opcache_enabled' => true,
        'gzip_compression' => true
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'vector_search' => true,
        'machine_learning' => true,
        'auto_scaling' => false,
        'distributed_mode' => false,
        'realtime_streaming' => true,
        'batch_processing' => true,
        'multi_tenant' => false
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Environment Settings
    |--------------------------------------------------------------------------
    */
    'environment' => [
        'mode' => 'production', // development, staging, production
        'debug' => false,
        'display_errors' => false,
        'log_errors' => true,
        'error_reporting' => E_ALL & ~E_DEPRECATED & ~E_STRICT,
        'timezone' => 'UTC',
        'locale' => 'en_US'
    ]
];