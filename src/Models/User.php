<?php

declare(strict_types=1);

namespace App\Models;

use Elementary\Authentication\Traits\Authenticatable;
use Elementary\Authentication\UserInterface;
use Elementary\Database\Contracts\QueryBuilderInterface;
use Elementary\Database\Model;
use Elementary\Database\Relations\HasMany;

/**
 * Represents a User in the application.
 */
final class User extends Model implements UserInterface
{
    use Authenticatable;

    protected static string $table = 'users';
    protected static string $usernameColumn = 'email';
    protected static array $fillable = [
        'first_name',
        'last_name', 
        'email',
        'password',
        'is_active',
        'profile_picture',
        'remember_token'
    ];

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

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}
