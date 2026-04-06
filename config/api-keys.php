<?php
/**
 * API Keys Configuration
 * 
 * @package APIMaster
 * @subpackage Config
 * @since 1.0.0
 * 
 * IMPORTANT: This is a STANDALONE module - NO WordPress dependencies!
 * All API keys are stored in JSON files, encrypted for security.
 */

if (!defined('ABSPATH')) {
    exit;
}

return [
    /*
    |--------------------------------------------------------------------------
    | API Key Settings
    |--------------------------------------------------------------------------
    */
    'settings' => [
        'key_length' => 32,
        'prefix_length' => 8,
        'prefix_separator' => '_',
        'hash_algorithm' => 'sha256',
        'encryption_enabled' => true,
        'encryption_cipher' => 'AES-256-GCM',
        'default_rate_limit' => 1000,
        'default_rate_period' => 3600, // seconds
        'key_expiry_enabled' => true,
        'default_expiry_days' => 365,
        'allow_multiple_per_user' => true,
        'max_keys_per_user' => 10
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Permission Levels
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'levels' => [
            'read_only' => [
                'description' => 'Can only read/get data',
                'permissions' => ['read', 'get', 'list', 'search']
            ],
            'read_write' => [
                'description' => 'Can read and write data',
                'permissions' => ['read', 'get', 'list', 'search', 'create', 'update', 'delete']
            ],
            'admin' => [
                'description' => 'Full access including management',
                'permissions' => ['*']
            ]
        ],
        'custom_actions' => [
            'vector_search',
            'training',
            'analytics',
            'webhook_management',
            'provider_management'
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Rate Limit Profiles
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'profiles' => [
            'free' => [
                'requests_per_hour' => 100,
                'requests_per_day' => 1000,
                'concurrent_requests' => 5,
                'burst_limit' => 10
            ],
            'basic' => [
                'requests_per_hour' => 1000,
                'requests_per_day' => 10000,
                'concurrent_requests' => 20,
                'burst_limit' => 50
            ],
            'premium' => [
                'requests_per_hour' => 10000,
                'requests_per_day' => 100000,
                'concurrent_requests' => 100,
                'burst_limit' => 200
            ],
            'enterprise' => [
                'requests_per_hour' => 100000,
                'requests_per_day' => 1000000,
                'concurrent_requests' => 500,
                'burst_limit' => 1000
            ]
        ],
        'default_profile' => 'free'
    ],
    
    /*
    |--------------------------------------------------------------------------
    | IP Restrictions
    |--------------------------------------------------------------------------
    */
    'ip_restrictions' => [
        'enabled' => true,
        'allow_private_ips' => true,
        'allow_loopback' => true,
        'ipv6_support' => true,
        'cidr_support' => true,
        'max_ips_per_key' => 50,
        'whitelist_mode' => true // true = whitelist, false = blacklist
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Domain Restrictions
    |--------------------------------------------------------------------------
    */
    'domain_restrictions' => [
        'enabled' => true,
        'allow_subdomains' => true,
        'allow_wildcards' => true,
        'validate_referer' => true,
        'validate_origin' => true,
        'max_domains_per_key' => 20
    ],
    
    /*
    |--------------------------------------------------------------------------
    | API Key Generation
    |--------------------------------------------------------------------------
    */
    'generation' => [
        'format' => 'apimaster_{prefix}_{random}',
        'random_chars' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
        'entropy' => 256, // bits
        'uniqueness_check' => true,
        'blacklist_patterns' => [
            '/^test/i',
            '/^demo/i',
            '/^example/i',
            '/^123/i',
            '/^admin/i'
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'name' => [
            'required' => true,
            'min_length' => 3,
            'max_length' => 100,
            'pattern' => '/^[a-zA-Z0-9\s\-_]+$/'
        ],
        'description' => [
            'required' => false,
            'max_length' => 500
        ],
        'allowed_ips' => [
            'validate_format' => true,
            'validate_network' => true
        ],
        'allowed_domains' => [
            'validate_format' => true,
            'validate_dns' => false // Set to true for DNS validation (slower)
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    */
    'security_headers' => [
        'api_key_header' => 'X-API-Key',
        'api_key_param' => 'api_key',
        'bearer_prefix' => 'Bearer',
        'custom_headers' => [
            'X-API-Key-ID',
            'X-API-Key-Name'
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Monitoring & Alerts
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'log_usage' => true,
        'track_endpoints' => true,
        'alert_on_abuse' => true,
        'abuse_threshold' => [
            'failed_attempts' => 10, // within 5 minutes
            'rate_limit_exceeded' => 5, // within 1 hour
            'suspicious_activity' => 3 // within 1 hour
        ],
        'notifications' => [
            'email' => false,
            'webhook' => false,
            'slack' => false
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Key Rotation
    |--------------------------------------------------------------------------
    */
    'rotation' => [
        'enabled' => true,
        'auto_rotate_days' => 90,
        'grace_period_days' => 7, // Both keys work during grace period
        'notify_before_days' => 14,
        'max_old_keys' => 2 // Keep old keys for rollback
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Default API Keys (For development only)
    |--------------------------------------------------------------------------
    */
    'default_keys' => [
        'development' => [
            'name' => 'Development Key',
            'description' => 'Auto-generated development key',
            'permissions' => 'admin',
            'rate_limit_profile' => 'enterprise',
            'expires_in_days' => 30
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'enabled' => true,
        'log_actions' => [
            'create' => true,
            'update' => true,
            'delete' => true,
            'regenerate' => true,
            'revoke' => true,
            'suspend' => true
        ],
        'retention_days' => 90,
        'include_ip' => true,
        'include_user_agent' => true
    ]
];