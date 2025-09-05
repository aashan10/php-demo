<?php

declare(strict_types=1);

namespace App\Models;

use Elementary\Database\QueryBuilder;
use Elementary\DI\Container;

abstract class AbstractModel
{
    protected static string $tableName;
    protected static ?Container $container = null;

    public static function setContainer(Container $container): void
    {
        self::$container = $container;
    }

    public static function query(): QueryBuilder
    {
        /** @var QueryBuilder $builder */
        $builder = self::getContainer()->get(QueryBuilder::class);

        return $builder->table(static::$tableName)
                       ->setModel(static::class);
    }

    public static function find(int $id): ?static
    {
        return static::query()->find($id);
    }

    protected static function getContainer(): Container
    {
        if (self::$container === null) {
            throw new \RuntimeException("Container not set on AbstractModel. Call AbstractModel::setContainer() first.");
        }
        return self::$container;
    }
}
