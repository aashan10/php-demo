<?php

declare(strict_types=1);

namespace App\Models;

use Elementary\Database\Connection;
use Elementary\DI\Container;
use PDO;

abstract class AbstractModel
{
    protected PDO $pdo; // No longer static
    protected static string $tableName;
    protected static string $primaryKey = 'id';

    protected static ?Container $container = null; // Static property to hold the container

    // New constructor now takes Connection
    public function __construct(Connection $connection)
    {
        $this->pdo = $connection->getInstance();
    }

    // Static method to set the container
    public static function setContainer(Container $container): void
    {
        self::$container = $container;
    }

    /**
     * Finds a record by its primary key.
     *
     * @param int $id
     * @return static|null
     */
    public static function find(int $id): ?static
    {
        // Get an instance of the model from the container
        /** @var static $instance */
        $instance = self::getContainer()->get(static::class);
        
        $stmt = $instance->getPdo()->prepare("SELECT * FROM " . static::$tableName . " WHERE " . static::$primaryKey . " = :id");
        $stmt->execute(['id' => $id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($record === false) {
            return null;
        }

        // Populate a new instance with data
        $model = self::getContainer()->get(static::class); // Get a fresh instance from container
        foreach ($record as $key => $value) {
            if (property_exists($model, $key)) {
                $model->{$key} = $value;
            }
        }
        return $model;
    }

    /**
     * Returns all records from the table.
     *
     * @return array
     */
    public static function findAll(): array
    {
        /** @var static $instance */
        $instance = self::getContainer()->get(static::class);
        $stmt = $instance->getPdo()->query("SELECT * FROM " . static::$tableName);
        return $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
    }

    /**
     * Creates a new record in the database.
     *
     * @param array $data
     * @return int The ID of the newly created record.
     */
    public static function create(array $data): int
    {
        /** @var static $instance */
        $instance = self::getContainer()->get(static::class);
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO " . static::$tableName . " ({$columns}) VALUES ({$placeholders})";
        
        $stmt = $instance->getPdo()->prepare($sql);
        $stmt->execute($data);

        return (int)$instance->getPdo()->lastInsertId();
    }

    /**
     * Updates a record by its primary key.
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function update(int $id, array $data): bool
    {
        /** @var static $instance */
        $instance = self::getContainer()->get(static::class);
        $setClauses = [];
        foreach ($data as $key => $value) {
            $setClauses[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $setClauses);

        $sql = "UPDATE " . static::$tableName . " SET {$setClause} WHERE " . static::$primaryKey . " = :id";
        
        $stmt = $instance->getPdo()->prepare($sql);
        return $stmt->execute(array_merge($data, ['id' => $id]));
    }

    /**
     * Deletes a record by its primary key.
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool
    {
        /** @var static $instance */
        $instance = self::getContainer()->get(static::class);
        $sql = "DELETE FROM " . static::$tableName . " WHERE " . static::$primaryKey . " = :id";
        $stmt = $instance->getPdo()->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * A helper to get the PDO instance.
     */
    protected function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * A helper to get the container instance.
     */
    protected static function getContainer(): Container
    {
        if (self::$container === null) {
            throw new \RuntimeException("Container not set on AbstractModel. Call AbstractModel::setContainer() first.");
        }
        return self::$container;
    }
}
