<?php

declare(strict_types=1);

namespace App\Models;

use Elementary\Database\AbstractModel;

/**
 * Represents a User in the application.
 */
final class User extends \Elementary\Model\User 
{
    protected static string $usernameColumn = 'email';



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
