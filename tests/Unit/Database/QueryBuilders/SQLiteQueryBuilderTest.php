<?php

declare(strict_types=1);

namespace Tests\Unit\Database\QueryBuilders;

use Elementary\Database\Drivers\SQLiteDriver;
use Elementary\Database\QueryBuilders\SQLiteQueryBuilder;
use Elementary\Database\Contracts\QueryBuilderInterface;
use PHPUnit\Framework\TestCase;

class SQLiteQueryBuilderTest extends TestCase
{
    private SQLiteDriver $driver;
    private SQLiteQueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite extension not loaded');
        }
        
        $config = [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ];
        
        $this->driver = new SQLiteDriver($config);
        $this->queryBuilder = new SQLiteQueryBuilder($this->driver);
        
        // Create test table
        $this->createTestTable();
    }

    private function createTestTable(): void
    {
        $connection = $this->driver->getConnection();
        $connection->exec('
            CREATE TABLE test_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE,
                age INTEGER,
                active INTEGER DEFAULT 1,
                created_at DATETIME
            )
        ');
        
        // Insert some test data
        $connection->exec("
            INSERT INTO test_users (name, email, age, active, created_at) VALUES 
            ('John Doe', 'john@example.com', 30, 1, '2023-01-01 10:00:00'),
            ('Jane Smith', 'jane@example.com', 25, 1, '2023-01-02 10:00:00'),
            ('Bob Wilson', 'bob@example.com', 35, 0, '2023-01-03 10:00:00'),
            ('Alice Brown', 'alice@example.com', 28, 1, '2023-01-04 10:00:00')
        ");
    }

    public function testImplementsQueryBuilderInterface(): void
    {
        $this->assertInstanceOf(QueryBuilderInterface::class, $this->queryBuilder);
    }

    public function testTable(): void
    {
        $result = $this->queryBuilder->table('test_users');
        
        $this->assertSame($this->queryBuilder, $result);
    }

    public function testSelect(): void
    {
        $result = $this->queryBuilder->select(['name', 'email']);
        
        $this->assertSame($this->queryBuilder, $result);
    }

    public function testGet(): void
    {
        $results = $this->queryBuilder->table('test_users')->get();
        
        $this->assertIsArray($results);
        $this->assertCount(4, $results);
        $this->assertArrayHasKey('name', $results[0]);
        $this->assertSame('John Doe', $results[0]['name']);
    }

    public function testGetWithSelect(): void
    {
        $results = $this->queryBuilder
            ->table('test_users')
            ->select(['name', 'email'])
            ->get();
        
        $this->assertIsArray($results);
        $this->assertCount(4, $results);
        $this->assertArrayHasKey('name', $results[0]);
        $this->assertArrayHasKey('email', $results[0]);
        $this->assertArrayNotHasKey('age', $results[0]);
    }

    public function testFirst(): void
    {
        $result = $this->queryBuilder->table('test_users')->first();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertSame('John Doe', $result['name']);
    }

    public function testFirstReturnsNullWhenNoResults(): void
    {
        $result = $this->queryBuilder
            ->table('test_users')
            ->where('name', '=', 'Nonexistent User')
            ->first();
        
        $this->assertNull($result);
    }

    public function testFind(): void
    {
        $result = $this->queryBuilder->table('test_users')->find(1);
        
        $this->assertIsArray($result);
        $this->assertSame(1, (int) $result['id']);
        $this->assertSame('John Doe', $result['name']);
    }

    public function testWhere(): void
    {
        $results = $this->queryBuilder
            ->table('test_users')
            ->where('active', '=', 1)
            ->get();
        
        $this->assertCount(3, $results);
        foreach ($results as $result) {
            $this->assertSame(1, (int) $result['active']);
        }
    }

    public function testWhereMultiple(): void
    {
        $results = $this->queryBuilder
            ->table('test_users')
            ->where('active', '=', 1)
            ->where('age', '>', 27)
            ->get();
        
        $this->assertCount(2, $results);
    }

    public function testWhereIn(): void
    {
        $results = $this->queryBuilder
            ->table('test_users')
            ->whereIn('name', ['John Doe', 'Jane Smith'])
            ->get();
        
        $this->assertCount(2, $results);
        $names = array_column($results, 'name');
        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
    }

    public function testWhereNotIn(): void
    {
        $results = $this->queryBuilder
            ->table('test_users')
            ->whereNotIn('name', ['John Doe', 'Jane Smith'])
            ->get();
        
        $this->assertCount(2, $results);
        $names = array_column($results, 'name');
        $this->assertNotContains('John Doe', $names);
        $this->assertNotContains('Jane Smith', $names);
    }

    public function testWhereNull(): void
    {
        // Insert a record with null email
        $connection = $this->driver->getConnection();
        $connection->exec("INSERT INTO test_users (name, email, age) VALUES ('Test User', NULL, 20)");
        
        $results = $this->queryBuilder
            ->table('test_users')
            ->whereNull('email')
            ->get();
        
        $this->assertCount(1, $results);
        $this->assertNull($results[0]['email']);
    }

    public function testWhereNotNull(): void
    {
        $results = $this->queryBuilder
            ->table('test_users')
            ->whereNotNull('email')
            ->get();
        
        $this->assertCount(4, $results);
        foreach ($results as $result) {
            $this->assertNotNull($result['email']);
        }
    }

    public function testOrderBy(): void
    {
        $results = $this->queryBuilder
            ->table('test_users')
            ->orderBy('age', 'DESC')
            ->get();
        
        $ages = array_column($results, 'age');
        $this->assertSame([35, 30, 28, 25], array_map('intval', $ages));
    }

    public function testOrderByAscending(): void
    {
        $results = $this->queryBuilder
            ->table('test_users')
            ->orderBy('age', 'ASC')
            ->get();
        
        $ages = array_column($results, 'age');
        $this->assertSame([25, 28, 30, 35], array_map('intval', $ages));
    }

    public function testLimit(): void
    {
        $results = $this->queryBuilder
            ->table('test_users')
            ->limit(2)
            ->get();
        
        $this->assertCount(2, $results);
    }

    public function testOffset(): void
    {
        $results = $this->queryBuilder
            ->table('test_users')
            ->limit(10) // SQLite requires LIMIT when using OFFSET
            ->offset(2)
            ->get();
        
        $this->assertCount(2, $results);
        $this->assertSame('Bob Wilson', $results[0]['name']);
    }

    public function testLimitWithOffset(): void
    {
        $results = $this->queryBuilder
            ->table('test_users')
            ->limit(2)
            ->offset(1)
            ->get();
        
        $this->assertCount(2, $results);
        $this->assertSame('Jane Smith', $results[0]['name']);
        $this->assertSame('Bob Wilson', $results[1]['name']);
    }

    public function testDistinct(): void
    {
        // Insert duplicate active values
        $connection = $this->driver->getConnection();
        $connection->exec("INSERT INTO test_users (name, email, age, active) VALUES ('Test User 1', 'test1@example.com', 20, 1)");
        $connection->exec("INSERT INTO test_users (name, email, age, active) VALUES ('Test User 2', 'test2@example.com', 21, 0)");
        
        $qb = new SQLiteQueryBuilder($this->driver);
        $results = $qb->table('test_users')
            ->select(['active'])
            ->distinct()
            ->get();
        
        $this->assertCount(2, $results);
        $activeValues = array_column($results, 'active');
        $this->assertContains(1, $activeValues); // Check for integer values
        $this->assertContains(0, $activeValues);
    }

    public function testCount(): void
    {
        $qb = new SQLiteQueryBuilder($this->driver);
        $count = $qb->table('test_users')->count();
        
        $this->assertSame(4, $count);
    }

    public function testCountWithWhere(): void
    {
        $qb = new SQLiteQueryBuilder($this->driver);
        $count = $qb->table('test_users')
            ->where('active', '=', 1)
            ->count();
        
        $this->assertSame(3, $count);
    }

    public function testExists(): void
    {
        $qb = new SQLiteQueryBuilder($this->driver);
        $exists = $qb->table('test_users')
            ->where('name', '=', 'John Doe')
            ->exists();
        
        $this->assertTrue($exists);
    }

    public function testExistsReturnsFalse(): void
    {
        $exists = $this->queryBuilder
            ->table('test_users')
            ->where('name', '=', 'Nonexistent User')
            ->exists();
        
        $this->assertFalse($exists);
    }

    public function testInsert(): void
    {
        $qb = new SQLiteQueryBuilder($this->driver);
        $result = $qb->table('test_users')
            ->insert([
                'name' => 'New User',
                'email' => 'new@example.com',
                'age' => 25,
                'active' => 1
            ]);
        
        $this->assertTrue($result);
        
        // Verify insertion with fresh query builder
        $countQb = new SQLiteQueryBuilder($this->driver);
        $count = $countQb->table('test_users')->count();
        $this->assertSame(5, $count);
    }

    public function testInsertEmptyDataReturnsFalse(): void
    {
        $result = $this->queryBuilder
            ->table('test_users')
            ->insert([]);
        
        $this->assertFalse($result);
    }

    public function testUpdate(): void
    {
        $affectedRows = $this->queryBuilder
            ->table('test_users')
            ->where('name', '=', 'John Doe')
            ->update(['age' => 31]);
        
        $this->assertSame(1, $affectedRows);
        
        // Verify update
        $user = $this->queryBuilder
            ->table('test_users')
            ->where('name', '=', 'John Doe')
            ->first();
        
        $this->assertSame(31, (int) $user['age']);
    }

    public function testUpdateEmptyDataReturnsZero(): void
    {
        $affectedRows = $this->queryBuilder
            ->table('test_users')
            ->where('name', '=', 'John Doe')
            ->update([]);
        
        $this->assertSame(0, $affectedRows);
    }

    public function testDelete(): void
    {
        $qb = new SQLiteQueryBuilder($this->driver);
        $affectedRows = $qb->table('test_users')
            ->where('name', '=', 'John Doe')
            ->delete();
        
        $this->assertSame(1, $affectedRows);
        
        // Verify deletion with fresh query builder
        $countQb = new SQLiteQueryBuilder($this->driver);
        $count = $countQb->table('test_users')->count();
        $this->assertSame(3, $count);
    }

    public function testDeleteWithMultipleWhere(): void
    {
        $qb = new SQLiteQueryBuilder($this->driver);
        $affectedRows = $qb->table('test_users')
            ->where('active', '=', 1)
            ->where('age', '>', 27)
            ->delete();
        
        $this->assertSame(2, $affectedRows);
        
        // Verify deletion with fresh query builder
        $countQb = new SQLiteQueryBuilder($this->driver);
        $count = $countQb->table('test_users')->count();
        $this->assertSame(2, $count);
    }

    public function testPaginate(): void
    {
        $result = $this->queryBuilder
            ->table('test_users')
            ->paginate(1, 2);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('current_page', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('total_pages', $result);
        $this->assertArrayHasKey('has_next', $result);
        $this->assertArrayHasKey('has_prev', $result);
        
        $this->assertCount(2, $result['data']);
        $this->assertSame(1, $result['current_page']);
        $this->assertSame(2, $result['per_page']);
        $this->assertSame(4, $result['total']);
        $this->assertSame(2, (int) $result['total_pages']);
        $this->assertTrue($result['has_next']);
        $this->assertFalse($result['has_prev']);
    }

    public function testPaginateSecondPage(): void
    {
        $result = $this->queryBuilder
            ->table('test_users')
            ->paginate(2, 2);
        
        $this->assertCount(2, $result['data']);
        $this->assertSame(2, $result['current_page']);
        $this->assertFalse($result['has_next']);
        $this->assertTrue($result['has_prev']);
    }

    public function testToDebugSql(): void
    {
        $debug = $this->queryBuilder
            ->table('test_users')
            ->where('name', '=', 'John Doe')
            ->where('age', '>', 25)
            ->toDebugSql();
        
        $this->assertIsArray($debug);
        $this->assertArrayHasKey('sql', $debug);
        $this->assertArrayHasKey('bindings', $debug);
        
        $this->assertStringContainsString('SELECT', $debug['sql']);
        $this->assertStringContainsString('WHERE', $debug['sql']);
        $this->assertStringContainsString('"test_users"', $debug['sql']);
        
        $this->assertSame(['John Doe', 25], $debug['bindings']);
    }

    public function testComplexQuery(): void
    {
        $results = $this->queryBuilder
            ->table('test_users')
            ->select(['name', 'email', 'age'])
            ->where('active', '=', 1)
            ->whereIn('age', [25, 30])
            ->orderBy('age', 'DESC')
            ->limit(5)
            ->get();
        
        $this->assertCount(2, $results);
        $this->assertSame('John Doe', $results[0]['name']);
        $this->assertSame('Jane Smith', $results[1]['name']);
    }

    public function testNewQueryReturnsNewInstance(): void
    {
        $newQuery = $this->queryBuilder->newQuery();
        
        $this->assertInstanceOf(SQLiteQueryBuilder::class, $newQuery);
        $this->assertNotSame($this->queryBuilder, $newQuery);
    }

    public function testWithMethod(): void
    {
        // Test single relation
        $result = $this->queryBuilder->with('profile');
        $this->assertSame($this->queryBuilder, $result);
        
        // Test multiple relations as array
        $result = $this->queryBuilder->with(['profile', 'posts']);
        $this->assertSame($this->queryBuilder, $result);
        
        // Test multiple relations as arguments
        $result = $this->queryBuilder->with('profile', 'posts', 'comments');
        $this->assertSame($this->queryBuilder, $result);
    }

    public function testSetModelMethod(): void
    {
        $result = $this->queryBuilder->setModel('App\\Models\\User');
        
        $this->assertSame($this->queryBuilder, $result);
    }

    public function testSqlEscaping(): void
    {
        // Test that SQL injection attempts are properly escaped
        $qb = new SQLiteQueryBuilder($this->driver);
        $results = $qb->table('test_users')
            ->where('name', '=', "'; DROP TABLE test_users; --")
            ->get();
        
        // Should return empty array (no matching records) and not drop the table
        $this->assertIsArray($results);
        $this->assertCount(0, $results);
        
        // Verify table still exists with fresh query builder
        $countQb = new SQLiteQueryBuilder($this->driver);
        $count = $countQb->table('test_users')->count();
        $this->assertSame(4, $count);
    }
}