<?php

declare(strict_types=1);

namespace App\Models;

use Elementary\Database\AbstractModel;

/**
 * Represents a User in the application.
 */
final class User extends AbstractModel
{
    protected static string $tableName = 'users';

    public int    $id;
    public string $first_name;
    public string $last_name;
    public string $email;
    public string $password; 
    public string $profile_picture;
    public bool   $is_active;
    public string $created_at;
    public string $updated_at;


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
