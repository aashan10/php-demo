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
        return static::query()->where('username', '=', $email)->first();
    }
}