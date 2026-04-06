<?php
/**
 * API Providers Configuration
 * 
 * @package APIMaster
 * @subpackage Config
 * @since 1.0.0
 * 
 * IMPORTANT: This is a STANDALONE module - NO WordPress dependencies!
 * Provider configurations for various API services.
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    /*
    |--------------------------------------------------------------------------
    | Provider Settings
    |--------------------------------------------------------------------------
    */
    'settings' => [
        'default_provider' => 'openai',
        'fallback_enabled' => true,
        'auto_discover' => true,
        'health_check_interval' => 300, // seconds
        'circuit_breaker_enabled' => true,
        'load_balancing' => 'round_robin', // round_robin, least_connections, weighted, random
        'retry_on_failure' => true,
        'max_retries' => 3,
        'retry_delay' => 1000 // milliseconds
    ],
    
    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    */
    'ai_providers' => [
        'openai' => [
            'name' => 'OpenAI',
            'base_url' => 'https://api.openai.com/v1',
            'api_version' => '2024-02-15',
            'auth_type' => 'bearer',
            'auth_config' => [
                'header_name' => 'Authorization',
                'prefix' => 'Bearer'
            ],
            'endpoints' => [
                'chat_completions' => '/chat/completions',
                'embeddings' => '/embeddings',
                'models' => '/models',
                'completions' => '/completions',
                'moderations' => '/moderations',
                'audio_transcriptions' => '/audio/transcriptions',
                'audio_translations' => '/audio/translations',
                'images_generations' => '/images/generations',
                'images_edits' => '/images/edits',
                'images_variations' => '/images/variations',
                'fine_tuning_jobs' => '/fine_tuning/jobs'
            ],
            'models' => [
                'gpt-4',
                'gpt-4-turbo',
                'gpt-3.5-turbo',
                'text-embedding-3-small',
                'text-embedding-3-large',
                'text-embedding-ada-002'
            ],
            'rate_limits' => [
                'requests_per_minute' => 3500,
                'tokens_per_minute' => 90000,
                'concurrent_requests' => 50
            ],
            'timeout' => 60,
            'priority' => 1,
            'is_active' => true,
            'is_default' => true,
            'metadata' => [
                'documentation' => 'https://platform.openai.com/docs',
                'pricing_url' => 'https://openai.com/pricing',
                'status_url' => 'https://status.openai.com'
            ]
        ],
        
        'anthropic' => [
            'name' => 'Anthropic Claude',
            'base_url' => 'https://api.anthropic.com/v1',
            'api_version' => '2023-06-01',
            'auth_type' => 'api_key',
            'auth_config' => [
                'header_name' => 'x-api-key',
                'version_header' => 'anthropic-version'
            ],
            'endpoints' => [
                'messages' => '/messages',
                'completions' => '/complete'
            ],
            'models' => [
                'claude-3-opus-20240229',
                'claude-3-sonnet-20240229',
                'claude-3-haiku-20240307',
                'claude-2.1',
                'claude-2.0'
            ],
            'rate_limits' => [
                'requests_per_minute' => 50,
                'tokens_per_minute' => 50000,
                'concurrent_requests' => 5
            ],
            'timeout' => 60,
            'priority' => 2,
            'is_active' => true,
            'is_default' => false,
            'metadata' => [
                'documentation' => 'https://docs.anthropic.com',
                'pricing_url' => 'https://www.anthropic.com/pricing'
            ]
        ],
        
        'google_ai' => [
            'name' => 'Google AI',
            'base_url' => 'https://generativelanguage.googleapis.com/v1',
            'api_version' => 'v1',
            'auth_type' => 'api_key',
            'auth_config' => [
                'param_name' => 'key'
            ],
            'endpoints' => [
                'generate_content' => '/models/{model}:generateContent',
                'count_tokens' => '/models/{model}:countTokens',
                'embed_content' => '/models/{model}:embedContent',
                'batch_embed_contents' => '/models/{model}:batchEmbedContents'
            ],
            'models' => [
                'gemini-pro',
                'gemini-pro-vision',
                'embedding-001'
            ],
            'rate_limits' => [
                'requests_per_minute' => 60,
                'tokens_per_minute' => 120000,
                'concurrent_requests' => 10
            ],
            'timeout' => 60,
            'priority' => 3,
            'is_active' => true,
            'is_default' => false,
            'metadata' => [
                'documentation' => 'https://ai.google.dev/docs',
                'pricing_url' => 'https://ai.google.dev/pricing'
            ]
        ],
        
        'cohere' => [
            'name' => 'Cohere',
            'base_url' => 'https://api.cohere.ai/v1',
            'api_version' => 'v1',
            'auth_type' => 'bearer',
            'auth_config' => [
                'header_name' => 'Authorization',
                'prefix' => 'Bearer'
            ],
            'endpoints' => [
                'generate' => '/generate',
                'embed' => '/embed',
                'classify' => '/classify',
                'tokenize' => '/tokenize',
                'detokenize' => '/detokenize'
            ],
            'models' => [
                'command',
                'command-light',
                'embed-english-v3.0',
                'embed-multilingual-v3.0'
            ],
            'rate_limits' => [
                'requests_per_minute' => 100,
                'tokens_per_minute' => 50000
            ],
            'timeout' => 60,
            'priority' => 4,
            'is_active' => false,
            'is_default' => false
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Vector Database Providers
    |--------------------------------------------------------------------------
    */
    'vector_providers' => [
        'pinecone' => [
            'name' => 'Pinecone',
            'base_url' => 'https://{index}.svc.{environment}.pinecone.io',
            'auth_type' => 'api_key',
            'auth_config' => [
                'header_name' => 'Api-Key'
            ],
            'endpoints' => [
                'upsert' => '/vectors/upsert',
                'query' => '/query',
                'fetch' => '/vectors/fetch',
                'delete' => '/vectors/delete',
                'describe_index' => '/describe_index'
            ],
            'rate_limits' => [
                'requests_per_second' => 100,
                'vectors_per_request' => 1000
            ],
            'timeout' => 30,
            'priority' => 1,
            'is_active' => false
        ],
        
        'qdrant' => [
            'name' => 'Qdrant',
            'base_url' => 'http://localhost:6333',
            'auth_type' => 'api_key',
            'auth_config' => [
                'header_name' => 'api-key'
            ],
            'endpoints' => [
                'upsert' => '/collections/{collection}/points',
                'search' => '/collections/{collection}/points/search',
                'delete' => '/collections/{collection}/points/delete'
            ],
            'rate_limits' => [
                'requests_per_second' => 1000
            ],
            'timeout' => 30,
            'priority' => 2,
            'is_active' => false
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Custom Providers
    |--------------------------------------------------------------------------
    */
    'custom_providers' => [
        // Add custom providers here
        'example_custom' => [
            'name' => 'Custom API',
            'base_url' => 'https://api.example.com',
            'auth_type' => 'api_key',
            'auth_config' => [
                'header_name' => 'X-API-Key'
            ],
            'endpoints' => [
                'main' => '/v1/endpoint'
            ],
            'rate_limits' => [
                'requests_per_hour' => 1000
            ],
            'timeout' => 30,
            'priority' => 100,
            'is_active' => false
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Provider Groups
    |--------------------------------------------------------------------------
    */
    'groups' => [
        'all_ai' => [
            'name' => 'All AI Providers',
            'providers' => ['openai', 'anthropic', 'google_ai', 'cohere'],
            'strategy' => 'failover'
        ],
        'openai_compatible' => [
            'name' => 'OpenAI Compatible',
            'providers' => ['openai', 'local_ai'],
            'strategy' => 'load_balanced'
        ],
        'embedding_providers' => [
            'name' => 'Embedding Providers',
            'providers' => ['openai', 'cohere', 'google_ai'],
            'strategy' => 'priority'
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Provider Transformation Rules
    |--------------------------------------------------------------------------
    */
    'transformations' => [
        'request' => [
            'openai_to_anthropic' => [
                'enabled' => true,
                'rules' => [
                    'model_mapping' => [
                        'gpt-4' => 'claude-3-opus-20240229',
                        'gpt-3.5-turbo' => 'claude-3-haiku-20240307'
                    ],
                    'field_mapping' => [
                        'messages' => 'messages',
                        'temperature' => 'temperature',
                        'max_tokens' => 'max_tokens'
                    ]
                ]
            ]
        ],
        'response' => [
            'normalize' => true,
            'common_format' => [
                'text' => 'choices[0].message.content',
                'model' => 'model',
                'usage' => 'usage'
            ]
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    */
    'health_check' => [
        'enabled' => true,
        'endpoint' => '/health',
        'method' => 'GET',
        'timeout' => 5,
        'expected_status' => 200,
        'expected_response' => ['status' => 'ok'],
        'failure_threshold' => 3,
        'success_threshold' => 2,
        'check_interval' => 60 // seconds
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'enabled' => true,
        'failure_threshold' => 5,
        'timeout' => 60, // seconds
        'half_open_attempts' => 3,
        'monitor_interval' => 10 // seconds
    ]
];