<?php

declare(strict_types=1);

namespace Elementary\Database;

use Elementary\DI\Container;

abstract class AbstractModel
{
    protected static string $tableName;
    protected static string $primaryKey = 'id';
    protected static ?Container $container = null;

    private array  $__data = [];

    public static function setContainer(Container $container): void
    {
        self::$container = $container;
    }

    public static function query(): QueryBuilder
    {
        /** @var QueryBuilder $builder */
        $builder = self::getContainer()->get(QueryBuilder::class);

        return $builder->table(static::$table)
                       ->setModel(static::class)
                       ->setPrimaryKey(static::$primaryKey);
    }

    public static function find(int $id): ?static
    {
        return static::query()->find($id);
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function create(array $data): bool
    {
        return static::query()->insert($data);
    }

    public function update(array $data): int
    {
        $primaryKey = static::$primaryKey;
        if (!isset($this->__data[$primaryKey])) {
            throw new \RuntimeException("Cannot update a model without a primary key value.");
        }

        $affectedRows = static::query()->where($primaryKey, '=', $this->$primaryKey)->update($data);

        // Refresh the current instance's properties with the new data
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }

        return $affectedRows;
    }

    public function delete(): int
    {
        $primaryKey = static::$primaryKey;
        if (!isset($this->$primaryKey)) {
            throw new \RuntimeException("Cannot delete a model without a primary key value.");
        }

        return static::query()->where($primaryKey, '=', $this->$primaryKey)->delete();
    }

    protected static function getContainer(): Container
    {
        if (self::$container === null) {
            throw new \RuntimeException("Container not set on AbstractModel. Call AbstractModel::setContainer() first.");
        }
        return self::$container;
    }

    public function __set($name, $value)
    {
        $this->__data[$name] = $value;
    }

    public function __get($name)
    {
        return $this->__data[$name] ?? null;
    }
}
