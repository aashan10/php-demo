<?php

declare(strict_types=1);

namespace App\Models;

use PDO;


/**
 * Represents a User in the application.
 */
final class User extends AbstractModel
{
    protected static string $tableName = 'users';

    public int $id;
    public string $FirstName;
    public string $LastName;
    public string $Address;
    public string $username; // This is the email
    public string $password; // This should be a hashed value

    /**
     * Finds a user by their email address (username).
     *
     * @param string $email
     * @return static|null
     */
    public static function findByEmail(string $email): ?static
    {
        /** @var static $instance */
        $instance = self::getContainer()->get(static::class);

        $stmt = $instance->getPdo()->prepare("SELECT * FROM " . static::$tableName . " WHERE username = :email");
        $stmt->execute(['email' => $email]);
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
}