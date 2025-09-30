<?php

/**
 * Multi-Driver Model Examples
 * 
 * This file demonstrates how different models can use different database drivers
 * in Elementary framework. Each model specifies its preferred driver for optimal
 * performance and functionality.
 */

namespace App\Models;

use Elementary\Database\Model;

/**
 * User Model - Uses MySQL for relational data
 * 
 * Traditional relational data with foreign keys, joins, and ACID compliance
 */
class User extends Model 
{
    protected static string $connection = 'mysql'; // or 'default' for main MySQL
    protected static string $table = 'users';
    protected static array $fillable = ['name', 'email', 'password'];
    
    // Relationships work across drivers
    public function sessions()
    {
        return Session::where('user_id', '=', $this->id)->get();
    }
    
    public function locations()
    {
        return Location::where('user_id', '=', $this->id)->get();
    }
}

/**
 * Session Model - Uses Redis for fast access
 * 
 * Sessions need rapid read/write access with automatic expiration
 */
class Session extends Model 
{
    protected static string $connection = 'redis';
    protected static string $table = 'sessions'; // Redis key prefix
    protected static array $fillable = ['user_id', 'data', 'expires_at'];
    
    // Redis-specific optimizations
    public static function cleanup(): int
    {
        return static::query()->deleteExpired();
    }
    
    public function isExpired(): bool
    {
        return $this->expires_at < time();
    }
}

/**
 * Location Model - Uses MongoDB for geospatial data
 * 
 * Geospatial coordinates benefit from MongoDB's geospatial indexes
 */
class Location extends Model 
{
    protected static string $connection = 'mongodb';
    protected static string $table = 'locations'; // MongoDB collection
    protected static array $fillable = ['user_id', 'coordinates', 'address', 'metadata'];
    
    // MongoDB geospatial queries
    public static function findNearby(array $coordinates, int $radius): array
    {
        return static::query()
            ->near($coordinates, $radius)
            ->limit(50)
            ->get();
    }
    
    public function getLatitude(): float
    {
        return $this->coordinates[1] ?? 0.0;
    }
    
    public function getLongitude(): float
    {
        return $this->coordinates[0] ?? 0.0;
    }
}

/**
 * AnalyticsEvent Model - Uses PostgreSQL for time-series analytics
 * 
 * Analytics data benefits from PostgreSQL's JSON support and time-series features
 */
class AnalyticsEvent extends Model 
{
    protected static string $connection = 'postgresql';
    protected static string $table = 'analytics_events';
    protected static array $fillable = ['user_id', 'event_type', 'properties', 'timestamp'];
    
    // PostgreSQL-specific features
    public static function dailyActiveUsers(string $date): int
    {
        return static::query()
            ->select(['COUNT(DISTINCT user_id) as count'])
            ->where('event_type', '=', 'page_view')
            ->where('timestamp', '>=', $date . ' 00:00:00')
            ->where('timestamp', '<', $date . ' 23:59:59')
            ->first()['count'] ?? 0;
    }
}

/**
 * Cache Model - Uses Redis for application caching
 * 
 * Different Redis database/configuration than sessions
 */
class Cache extends Model 
{
    protected static string $connection = 'redis'; // Could be 'redis_cache' for different config
    protected static string $table = 'cache';
    protected static array $fillable = ['key', 'value', 'expires_at'];
    
    public static function remember(string $key, int $ttl, callable $callback)
    {
        $cached = static::where('key', '=', $key)->first();
        
        if ($cached && !$cached->isExpired()) {
            return unserialize($cached->value);
        }
        
        $value = $callback();
        
        static::create([
            'key' => $key,
            'value' => serialize($value),
            'expires_at' => time() + $ttl
        ]);
        
        return $value;
    }
    
    public function isExpired(): bool
    {
        return $this->expires_at < time();
    }
}

// ===================================
// Usage Examples
// ===================================

/*

// Create user in MySQL
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT)
]);

// Create session in Redis
$session = Session::create([
    'user_id' => $user->id,
    'data' => serialize(['cart' => [1, 2, 3]]),
    'expires_at' => time() + 3600
]);

// Store location in MongoDB
$location = Location::create([
    'user_id' => $user->id,
    'coordinates' => [-74.0060, 40.7128], // [longitude, latitude] for NYC
    'address' => '123 Main St, New York, NY',
    'metadata' => [
        'accuracy' => 'high',
        'source' => 'gps'
    ]
]);

// Log analytics event in PostgreSQL
$event = AnalyticsEvent::create([
    'user_id' => $user->id,
    'event_type' => 'page_view',
    'properties' => json_encode(['page' => '/dashboard']),
    'timestamp' => date('Y-m-d H:i:s')
]);

// Cache expensive computation in Redis
$expensiveData = Cache::remember('user_stats_' . $user->id, 3600, function() use ($user) {
    return [
        'total_sessions' => $user->sessions()->count(),
        'locations_visited' => $user->locations()->count(),
        'last_activity' => AnalyticsEvent::where('user_id', '=', $user->id)
            ->orderBy('timestamp', 'DESC')
            ->first()?->timestamp
    ];
});

// Find nearby locations using MongoDB geospatial query
$nearbyLocations = Location::findNearby([-74.0060, 40.7128], 1000); // 1km radius

// Get analytics insights using PostgreSQL
$dailyUsers = AnalyticsEvent::dailyActiveUsers('2024-01-15');

// Cross-driver relationships
$userWithData = User::find(1);
$userSessions = $userWithData->sessions(); // Queries Redis
$userLocations = $userWithData->locations(); // Queries MongoDB

*/