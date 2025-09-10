<?php

declare(strict_types=1);

namespace Elementary\Authentication\Traits;

trait Authenticatable 
{
    protected static string $idColumn = 'id';
    protected static string $usernameColumn = 'username';
    protected static string $passwordColumn = 'password';


    public function getId(): int|string
    {
        return $this->{self::$idColumn};
    }

    public function getUsername(): string
    {
        return $this->{self::$usernameColumn};
    }

    public function getPasswordHash(): string
    {
        return $this->{self::$passwordColumn};
    }
}


