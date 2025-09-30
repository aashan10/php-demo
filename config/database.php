<?php

/**
 * Multi-Driver Database Configuration for Elementary Framework
 * 
 * Supports multiple database drivers with per-connection configuration.
 * Each connection can use different drivers (MySQL, Redis, MongoDB, etc.)
 * with independent connection pooling and optimization settings.
 */
return [
    // Default connection name
    'default' => 'default',
    
    // Legacy format (for backward compatibility)
    'driver' => 'mysql',
    'host' => 'mysql',
    'port' => 3306,
    'database' => 'php_demo',
    'username' => 'php_demo',
    'password' => 'php_demo',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    
    // Connection Pool Configuration
    'pool' => [
        // Connection pooling enabled by default for optimal performance
        'enabled' => true,
        
        // Minimum number of connections to maintain
        'min_connections' => 5,
        
        // Maximum number of connections allowed
        'max_connections' => 20,
        
        // Connection timeout in seconds
        'connection_timeout' => 30,
        
        // Idle timeout in seconds (connections idle longer than this may be closed)
        'idle_timeout' => 300,
    ],
    
    // Read Replica Support (for future implementation)
    'read_replicas' => [
        // Uncomment and configure for read replica support
        // [
        //     'host' => 'mysql-read-1',
        //     'port' => 3306,
        //     'weight' => 1, // Load balancing weight
        // ],
        // [
        //     'host' => 'mysql-read-2', 
        //     'port' => 3306,
        //     'weight' => 1,
        // ],
    ],
    
    // Connection Options
    'options' => [
        // PDO options for connection tuning
        'connect_timeout' => 10,
        'read_timeout' => 30,
        'write_timeout' => 30,
    ],
    
    // Session Storage (already configured)
    'session' => [
        'driver' => 'database',
        'table' => 'sessions',
        'lifetime' => 120, // minutes
        'gc_probability' => 2,
        'gc_divisor' => 100,
    ],
    
    // Query Performance Settings
    'performance' => [
        // Enable query logging for debugging (disable in production)
        'log_queries' => false,
        
        // Log slow queries (in milliseconds)
        'slow_query_threshold' => 1000,
        
        // Maximum query execution time (in seconds)
        'max_execution_time' => 30,
    ],
    
    // Cache Settings for Database Results
    'cache' => [
        // Enable query result caching
        'enabled' => false,
        
        // Default cache TTL in seconds
        'ttl' => 3600,
        
        // Cache driver (redis, file, etc.)
        'driver' => 'redis',
    ],
    
    // Multi-Connection Configuration
    'connections' => [
        'default' => [
            'driver' => 'mysql',
            'host' => 'mysql',
            'port' => 3306,
            'database' => 'php_demo',
            'username' => 'php_demo',
            'password' => 'php_demo',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'pool' => [
                'enabled' => true,
                'min_connections' => 5,
                'max_connections' => 20,
                'connection_timeout' => 30,
                'idle_timeout' => 300,
            ],
        ],
        
        'mysql' => [
            'driver' => 'mysql',
            'host' => 'mysql',
            'port' => 3306,
            'database' => 'php_demo',
            'username' => 'php_demo',
            'password' => 'php_demo',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'pool' => [
                'enabled' => true,
                'min_connections' => 5,
                'max_connections' => 20,
                'connection_timeout' => 30,
                'idle_timeout' => 300,
            ],
        ],
        
        // Example Redis connection (for sessions, cache)
        'redis' => [
            'driver' => 'redis',
            'host' => 'redis',
            'port' => 6379,
            'database' => 0,
            'password' => null,
            'pool' => [
                'enabled' => true,
                'min_connections' => 2,
                'max_connections' => 10,
                'connection_timeout' => 10,
                'idle_timeout' => 300,
            ],
        ],
        
        // Example MongoDB connection (for geospatial data)
        'mongodb' => [
            'driver' => 'mongodb',
            'host' => 'mongodb',
            'port' => 27017,
            'database' => 'geo_db',
            'username' => null,
            'password' => null,
            'options' => [
                'ssl' => false,
                'replicaSet' => null,
            ],
            'pool' => [
                'enabled' => true,
                'min_connections' => 3,
                'max_connections' => 15,
                'connection_timeout' => 20,
                'idle_timeout' => 300,
            ],
        ],
        
        // Example PostgreSQL connection
        'postgresql' => [
            'driver' => 'postgresql',
            'host' => 'postgres',
            'port' => 5432,
            'database' => 'analytics_db',
            'username' => 'postgres',
            'password' => 'postgres',
            'charset' => 'utf8',
            'schema' => 'public',
            'pool' => [
                'enabled' => true,
                'min_connections' => 5,
                'max_connections' => 25,
                'connection_timeout' => 30,
                'idle_timeout' => 300,
            ],
        ],
    ],
];
