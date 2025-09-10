<?php

declare(strict_types=1);

namespace Elementary\Model;

use Elementary\Authentication\Traits\Authenticatable;
use Elementary\Authentication\UserInterface;
use Elementary\Database\AbstractModel;

class User extends AbstractModel implements  UserInterface
{
    use Authenticatable;

    protected static string $table = 'users';
    protected static string $primaryKey = 'id';


    public function findByUsername(string $username): ?static
    {
        return static::query()->where(static::$usernameColumn, '=', $username)->first();
    }

}
