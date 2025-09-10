<?php

declare(strict_types=1);

namespace App\Models;

use Elementary\Authentication\Traits\Authenticatable;
use Elementary\Authentication\UserInterface;
use Elementary\Database\Model;

/**
 * Represents a User in the application.
 */
final class User extends Model implements UserInterface
{
    use Authenticatable;

    protected static string $table = 'users';
    protected static string $usernameColumn = 'email';

    /**
     * Finds a user by their email address (username).
     *
     * @param string $email
     * @return static|null
     */
    public static function findByEmail(string $email): ?static
    {
        return static::query()->where('email', '=', $email)->first();
    }
}
