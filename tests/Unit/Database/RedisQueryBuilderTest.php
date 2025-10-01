<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Elementary\Database\Drivers\RedisDriver;
use Elementary\Database\QueryBuilders\RedisQueryBuilder;
use Elementary\Database\Contracts\QueryBuilderInterface;
use Tests\Support\TestCase;

class RedisQueryBuilderTest extends TestCase
{
    private ?RedisDriver $driver = null;
    private ?RedisQueryBuilder $queryBuilder = null;

    protected function setUp(): void
    {
        parent::setUp();
        
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is not available');
        }
        
        $config = [
            'driver' => 'redis',
            'host' => 'redis',
            'port' => 6379,
            'database' => 2, // Use database 2 for testing
            'password' => null,
            'prefix' => '', // No prefix for testing to avoid scan issues
            'pool' => [
                'enabled' => false, // Disable pooling for simpler testing
            ],
        ];
        
        $this->driver = new RedisDriver($config);
        $this->queryBuilder = new RedisQueryBuilder($this->driver);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        if ($this->driver !== null) {
            try {
                $redis = $this->driver->getConnection();
                $redis->flushDb();
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
            
            $this->driver->disconnect();
        }
        parent::tearDown();
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(QueryBuilderInterface::class, $this->queryBuilder);
    }

    public function testTableSetting(): void
    {
        $result = $this->queryBuilder->table('sessions');
        $this->assertSame($this->queryBuilder, $result);
    }

    public function testModelSetting(): void
    {
        $result = $this->queryBuilder->setModel('TestModel');
        $this->assertSame($this->queryBuilder, $result);
    }

    public function testInsert(): void
    {
        $this->queryBuilder->table('users');
        
        $result = $this->queryBuilder->insert([
            'id' => 'user_123',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30
        ]);
        
        $this->assertTrue($result);
    }

    public function testFind(): void
    {
        // Insert test data
        $this->queryBuilder->table('users');
        $this->queryBuilder->insert([
            'id' => 'user_456',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'age' => 25
        ]);
        
        // Find the data
        $result = $this->queryBuilder->find('user_456');
        
        $this->assertIsArray($result);
        $this->assertEquals('Jane Doe', $result['name']);
        $this->assertEquals('jane@example.com', $result['email']);
        $this->assertEquals(25, $result['age']);
    }

    public function testGet(): void
    {
        $this->queryBuilder->table('products');
        
        // Insert multiple items
        $this->queryBuilder->insert(['id' => 'prod_1', 'name' => 'Product 1', 'price' => 100]);
        $this->queryBuilder->insert(['id' => 'prod_2', 'name' => 'Product 2', 'price' => 200]);
        $this->queryBuilder->insert(['id' => 'prod_3', 'name' => 'Product 3', 'price' => 150]);
        
        $results = $this->queryBuilder->get();
        
        $this->assertIsArray($results);
        $this->assertCount(3, $results);
    }

    public function testFirst(): void
    {
        $this->queryBuilder->table('orders');
        
        $this->queryBuilder->insert(['id' => 'order_1', 'total' => 100, 'status' => 'pending']);
        $this->queryBuilder->insert(['id' => 'order_2', 'total' => 200, 'status' => 'completed']);
        
        $result = $this->queryBuilder->first();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('total', $result);
    }

    public function testWhereWithPrimaryKey(): void
    {
        $this->queryBuilder->table('sessions');
        
        // Insert test data
        $this->queryBuilder->insert([
            'id' => 'session_abc',
            'user_id' => 789,
            'data' => 'session_data'
        ]);
        
        // Query with WHERE on primary key
        $result = $this->queryBuilder->where('id', '=', 'session_abc')->first();
        
        $this->assertIsArray($result);
        $this->assertEquals('session_abc', $result['id']);
        $this->assertEquals(789, $result['user_id']);
    }

    public function testWhereWithNonPrimaryKey(): void
    {
        $this->queryBuilder->table('customers');
        
        // Insert test data
        $this->queryBuilder->insert(['id' => 'cust_1', 'status' => 'active', 'country' => 'US']);
        $this->queryBuilder->insert(['id' => 'cust_2', 'status' => 'inactive', 'country' => 'US']);
        $this->queryBuilder->insert(['id' => 'cust_3', 'status' => 'active', 'country' => 'CA']);
        
        // Query with WHERE on non-primary key
        $results = $this->queryBuilder->where('status', '=', 'active')->get();
        
        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        
        foreach ($results as $result) {
            $this->assertEquals('active', $result['status']);
        }
    }

    public function testUpdate(): void
    {
        $this->queryBuilder->table('profiles');
        
        // Insert test data
        $this->queryBuilder->insert([
            'id' => 'profile_123',
            'name' => 'Original Name',
            'bio' => 'Original Bio'
        ]);
        
        // Update the data
        $updated = $this->queryBuilder->where('id', '=', 'profile_123')
            ->update(['name' => 'Updated Name', 'bio' => 'Updated Bio']);
        
        $this->assertEquals(1, $updated);
        
        // Verify the update
        $result = $this->queryBuilder->find('profile_123');
        $this->assertEquals('Updated Name', $result['name']);
        $this->assertEquals('Updated Bio', $result['bio']);
    }

    public function testDelete(): void
    {
        $this->queryBuilder->table('temp_data');
        
        // Insert test data
        $this->queryBuilder->insert(['id' => 'temp_1', 'value' => 'delete_me']);
        $this->queryBuilder->insert(['id' => 'temp_2', 'value' => 'keep_me']);
        
        // Delete specific item
        $deleted = $this->queryBuilder->where('id', '=', 'temp_1')->delete();
        
        $this->assertEquals(1, $deleted);
        
        // Verify deletion
        $result = $this->queryBuilder->find('temp_1');
        $this->assertNull($result);
        
        // Verify other item still exists
        $result = $this->queryBuilder->find('temp_2');
        $this->assertIsArray($result);
        $this->assertEquals('keep_me', $result['value']);
    }

    public function testCount(): void
    {
        $this->queryBuilder->table('inventory');
        
        // Insert test data
        $this->queryBuilder->insert(['id' => 'item_1', 'category' => 'electronics']);
        $this->queryBuilder->insert(['id' => 'item_2', 'category' => 'electronics']);
        $this->queryBuilder->insert(['id' => 'item_3', 'category' => 'books']);
        
        // Count all items
        $totalCount = $this->queryBuilder->count();
        $this->assertEquals(3, $totalCount);
        
        // Count with WHERE clause
        $electronicsCount = $this->queryBuilder->where('category', '=', 'electronics')->count();
        $this->assertEquals(2, $electronicsCount);
    }

    public function testExists(): void
    {
        $this->queryBuilder->table('settings');
        
        // Test exists when no data
        $this->assertFalse($this->queryBuilder->exists());
        
        // Insert data
        $this->queryBuilder->insert(['id' => 'setting_1', 'key' => 'theme', 'value' => 'dark']);
        
        // Test exists when data present
        $this->assertTrue($this->queryBuilder->exists());
        
        // Test exists with WHERE clause
        $this->assertTrue($this->queryBuilder->where('key', '=', 'theme')->exists());
        $this->assertFalse($this->queryBuilder->where('key', '=', 'nonexistent')->exists());
    }

    public function testLimit(): void
    {
        $this->queryBuilder->table('logs');
        
        // Insert multiple items
        for ($i = 1; $i <= 10; $i++) {
            $this->queryBuilder->insert(['id' => "log_$i", 'message' => "Message $i"]);
        }
        
        // Test limit
        $results = $this->queryBuilder->limit(3)->get();
        $this->assertCount(3, $results);
    }

    public function testOffset(): void
    {
        $this->queryBuilder->table('pages');
        
        // Insert test data
        $this->queryBuilder->insert(['id' => 'page_1', 'title' => 'Page 1']);
        $this->queryBuilder->insert(['id' => 'page_2', 'title' => 'Page 2']);
        $this->queryBuilder->insert(['id' => 'page_3', 'title' => 'Page 3']);
        
        // Test offset
        $results = $this->queryBuilder->offset(1)->limit(1)->get();
        $this->assertCount(1, $results);
        
        // Should get the second item (offset 1)
        // Note: Redis doesn't guarantee order, so we just check we got something
        $this->assertIsArray($results[0]);
    }

    public function testPaginate(): void
    {
        $this->queryBuilder->table('articles');
        
        // Insert test data
        for ($i = 1; $i <= 25; $i++) {
            $this->queryBuilder->insert(['id' => "article_$i", 'title' => "Article $i"]);
        }
        
        // Test pagination
        $page1 = $this->queryBuilder->paginate(1, 10);
        
        $this->assertIsArray($page1);
        $this->assertArrayHasKey('data', $page1);
        $this->assertArrayHasKey('current_page', $page1);
        $this->assertArrayHasKey('per_page', $page1);
        $this->assertArrayHasKey('total', $page1);
        $this->assertArrayHasKey('last_page', $page1);
        
        $this->assertEquals(1, $page1['current_page']);
        $this->assertEquals(10, $page1['per_page']);
        $this->assertEquals(25, $page1['total']);
        $this->assertEquals(3, $page1['last_page']);
        $this->assertCount(10, $page1['data']);
    }

    public function testDebugSql(): void
    {
        $this->queryBuilder->table('debug_test');
        $this->queryBuilder->where('status', '=', 'active');
        
        $debug = $this->queryBuilder->toDebugSql();
        
        $this->assertIsArray($debug);
        $this->assertArrayHasKey('operation', $debug);
        $this->assertArrayHasKey('table', $debug);
        $this->assertArrayHasKey('wheres', $debug);
        
        $this->assertEquals('Redis Key Operations', $debug['operation']);
        $this->assertEquals('debug_test', $debug['table']);
    }

    public function testInsertWithTTL(): void
    {
        $this->queryBuilder->table('cache');
        
        $result = $this->queryBuilder->insert([
            'id' => 'cache_key',
            'value' => 'cached_value',
            '_ttl' => 60 // 60 seconds TTL
        ]);
        
        $this->assertTrue($result);
        
        // Verify data exists
        $cached = $this->queryBuilder->find('cache_key');
        $this->assertIsArray($cached);
        $this->assertEquals('cached_value', $cached['value']);
    }

    public function testWhereIn(): void
    {
        $this->queryBuilder->table('tags');
        
        // Insert test data
        $this->queryBuilder->insert(['id' => 'tag_1', 'name' => 'php']);
        $this->queryBuilder->insert(['id' => 'tag_2', 'name' => 'javascript']);
        $this->queryBuilder->insert(['id' => 'tag_3', 'name' => 'python']);
        
        // Test whereIn with primary key
        $results = $this->queryBuilder->whereIn('id', ['tag_1', 'tag_3'])->get();
        
        $this->assertIsArray($results);
        $this->assertCount(2, $results);
    }

    public function testMultipleWhereConditions(): void
    {
        $this->queryBuilder->table('employees');
        
        // Insert test data
        $this->queryBuilder->insert(['id' => 'emp_1', 'department' => 'IT', 'status' => 'active', 'salary' => 50000]);
        $this->queryBuilder->insert(['id' => 'emp_2', 'department' => 'IT', 'status' => 'inactive', 'salary' => 60000]);
        $this->queryBuilder->insert(['id' => 'emp_3', 'department' => 'HR', 'status' => 'active', 'salary' => 45000]);
        
        // Test multiple WHERE conditions
        $results = $this->queryBuilder
            ->where('department', '=', 'IT')
            ->where('status', '=', 'active')
            ->get();
        
        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals('emp_1', $results[0]['id']);
    }
}