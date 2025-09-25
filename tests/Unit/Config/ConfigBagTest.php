<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Elementary\Config\ConfigBag;
use Elementary\DI\Exceptions\ContainerException;
use PHPUnit\Framework\TestCase;

class ConfigBagTest extends TestCase
{
    private string $tempConfigDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempConfigDir = sys_get_temp_dir() . '/config_test_' . uniqid();
        mkdir($this->tempConfigDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temporary files
        if (is_dir($this->tempConfigDir)) {
            $files = glob($this->tempConfigDir . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->tempConfigDir);
        }
        parent::tearDown();
    }

    private function createConfigFile(string $filename, array $config): void
    {
        file_put_contents(
            $this->tempConfigDir . '/' . $filename . '.php',
            '<?php return ' . var_export($config, true) . ';'
        );
    }

    public function testConstructorThrowsExceptionForNonExistentDirectory(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Config directory not found: /nonexistent/path');
        
        new ConfigBag('/nonexistent/path');
    }

    public function testConstructorLoadsConfigFiles(): void
    {
        $this->createConfigFile('app', ['env' => 'production', 'debug' => false]);
        $this->createConfigFile('database', ['host' => 'localhost', 'port' => 3306]);
        
        $config = new ConfigBag($this->tempConfigDir);
        
        $this->assertEquals('production', $config->get('app.env'));
        $this->assertFalse($config->get('app.debug'));
        $this->assertEquals('localhost', $config->get('database.host'));
        $this->assertEquals(3306, $config->get('database.port'));
    }

    public function testGetWithDotNotation(): void
    {
        $this->createConfigFile('nested', [
            'level1' => [
                'level2' => [
                    'level3' => 'deep_value'
                ],
                'simple' => 'value'
            ]
        ]);
        
        $config = new ConfigBag($this->tempConfigDir);
        
        $this->assertEquals('deep_value', $config->get('nested.level1.level2.level3'));
        $this->assertEquals('value', $config->get('nested.level1.simple'));
    }

    public function testGetReturnsDefaultForNonExistentKeys(): void
    {
        $this->createConfigFile('app', ['env' => 'production']);
        
        $config = new ConfigBag($this->tempConfigDir);
        
        $this->assertNull($config->get('nonexistent.key'));
        $this->assertEquals('default_value', $config->get('nonexistent.key', 'default_value'));
        $this->assertEquals('fallback', $config->get('app.nonexistent', 'fallback'));
    }

    public function testGetReturnsDefaultForInvalidPath(): void
    {
        $this->createConfigFile('app', ['env' => 'production']);
        
        $config = new ConfigBag($this->tempConfigDir);
        
        // Trying to access a non-array as if it were an array
        $this->assertEquals('default', $config->get('app.env.invalid', 'default'));
    }

    public function testGetCanReturnEntireConfigGroup(): void
    {
        $appConfig = ['env' => 'development', 'debug' => true, 'name' => 'My App'];
        $this->createConfigFile('app', $appConfig);
        
        $config = new ConfigBag($this->tempConfigDir);
        
        $this->assertEquals($appConfig, $config->get('app'));
    }

    public function testGetCanReturnNestedArrays(): void
    {
        $this->createConfigFile('services', [
            'mail' => [
                'driver' => 'smtp',
                'host' => 'smtp.example.com',
                'options' => ['tls' => true, 'port' => 587]
            ]
        ]);
        
        $config = new ConfigBag($this->tempConfigDir);
        
        $mailConfig = $config->get('services.mail');
        $this->assertEquals([
            'driver' => 'smtp',
            'host' => 'smtp.example.com',
            'options' => ['tls' => true, 'port' => 587]
        ], $mailConfig);
        
        $this->assertEquals(['tls' => true, 'port' => 587], $config->get('services.mail.options'));
    }

    public function testSetWithDotNotation(): void
    {
        $this->createConfigFile('app', ['env' => 'production']);
        
        $config = new ConfigBag($this->tempConfigDir);
        
        $config->set('app.debug', true);
        $config->set('app.timezone', 'UTC');
        $config->set('new.nested.value', 'test');
        
        $this->assertTrue($config->get('app.debug'));
        $this->assertEquals('UTC', $config->get('app.timezone'));
        $this->assertEquals('test', $config->get('new.nested.value'));
    }

    public function testSetCreatesNestedStructure(): void
    {
        $config = new ConfigBag($this->tempConfigDir);
        
        $config->set('deep.nested.structure.value', 'success');
        
        $this->assertEquals('success', $config->get('deep.nested.structure.value'));
        $this->assertEquals(['value' => 'success'], $config->get('deep.nested.structure'));
    }

    public function testSetOverwritesExistingValues(): void
    {
        $this->createConfigFile('app', ['env' => 'production', 'debug' => false]);
        
        $config = new ConfigBag($this->tempConfigDir);
        
        $this->assertEquals('production', $config->get('app.env'));
        
        $config->set('app.env', 'development');
        
        $this->assertEquals('development', $config->get('app.env'));
    }

    public function testSetCanOverwriteWithDifferentDataTypes(): void
    {
        $config = new ConfigBag($this->tempConfigDir);
        
        $config->set('test.value', 'string');
        $this->assertEquals('string', $config->get('test.value'));
        
        $config->set('test.value', 42);
        $this->assertEquals(42, $config->get('test.value'));
        
        $config->set('test.value', ['array', 'data']);
        $this->assertEquals(['array', 'data'], $config->get('test.value'));
    }

    public function testSetCanReplaceScalarWithArray(): void
    {
        $config = new ConfigBag($this->tempConfigDir);
        
        $config->set('item', 'scalar_value');
        $this->assertEquals('scalar_value', $config->get('item'));
        
        $config->set('item.nested', 'nested_value');
        $this->assertEquals('nested_value', $config->get('item.nested'));
        $this->assertEquals(['nested' => 'nested_value'], $config->get('item'));
    }

    public function testAllReturnsAllConfigData(): void
    {
        $appConfig = ['env' => 'production', 'debug' => false];
        $dbConfig = ['host' => 'localhost', 'port' => 5432];
        
        $this->createConfigFile('app', $appConfig);
        $this->createConfigFile('database', $dbConfig);
        
        $config = new ConfigBag($this->tempConfigDir);
        
        $expected = [
            'app' => $appConfig,
            'database' => $dbConfig
        ];
        
        $this->assertEquals($expected, $config->all());
    }

    public function testAllReflectsSetChanges(): void
    {
        $this->createConfigFile('app', ['env' => 'production']);
        
        $config = new ConfigBag($this->tempConfigDir);
        $config->set('app.debug', true);
        $config->set('new.config', 'value');
        
        $all = $config->all();
        
        $this->assertEquals([
            'app' => ['env' => 'production', 'debug' => true],
            'new' => ['config' => 'value']
        ], $all);
    }

    public function testEmptyConfigDirectory(): void
    {
        $config = new ConfigBag($this->tempConfigDir);
        
        $this->assertEquals([], $config->all());
        $this->assertNull($config->get('any.key'));
    }

    public function testConfigFileWithComplexStructure(): void
    {
        $complexConfig = [
                'database' => [
                    'connections' => [
                        'mysql' => ['host' => 'localhost', 'port' => 3306],
                        'postgresql' => ['host' => 'pg.example.com', 'port' => 5432]
                    ],
                    'default' => 'mysql'
                ],
                'cache' => [
                    'stores' => [
                        'redis' => ['host' => 'redis.example.com'],
                        'file' => ['path' => '/tmp/cache']
                    ]
                ]
        ];
        
        $this->createConfigFile('services', $complexConfig);
        
        $config = new ConfigBag($this->tempConfigDir);
        
        $this->assertEquals('localhost', $config->get('services.database.connections.mysql.host'));
        $this->assertEquals(5432, $config->get('services.database.connections.postgresql.port'));
        $this->assertEquals('mysql', $config->get('services.database.default'));
        $this->assertEquals('/tmp/cache', $config->get('services.cache.stores.file.path'));
    }

    public function testConfigWithVariousDataTypes(): void
    {
        $config = [
            'string' => 'hello',
            'integer' => 42,
            'float' => 3.14,
            'boolean_true' => true,
            'boolean_false' => false,
            'null_value' => null,
            'array' => ['a', 'b', 'c'],
            'associative' => ['key' => 'value']
        ];
        
        $this->createConfigFile('types', $config);
        
        $configBag = new ConfigBag($this->tempConfigDir);
        
        $this->assertEquals('hello', $configBag->get('types.string'));
        $this->assertEquals(42, $configBag->get('types.integer'));
        $this->assertEquals(3.14, $configBag->get('types.float'));
        $this->assertTrue($configBag->get('types.boolean_true'));
        $this->assertFalse($configBag->get('types.boolean_false'));
        $this->assertNull($configBag->get('types.null_value'));
        $this->assertEquals(['a', 'b', 'c'], $configBag->get('types.array'));
        $this->assertEquals(['key' => 'value'], $configBag->get('types.associative'));
    }

    public function testIgnoresNonPhpFiles(): void
    {
        $this->createConfigFile('app', ['env' => 'production']);
        file_put_contents($this->tempConfigDir . '/readme.txt', 'This is not a config file');
        file_put_contents($this->tempConfigDir . '/config.json', '{"json": "data"}');
        
        $config = new ConfigBag($this->tempConfigDir);
        
        $all = $config->all();
        $this->assertArrayHasKey('app', $all);
        $this->assertArrayNotHasKey('readme', $all);
        $this->assertArrayNotHasKey('config', $all);
    }
}
