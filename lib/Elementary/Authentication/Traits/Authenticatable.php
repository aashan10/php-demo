<?php

declare(strict_types=1);

namespace Elementary\Authentication\Traits;

trait Authenticatable 
{

    public function getId(): int|string
    {
        return $this->{$this->getIdColumn()};
    }

    public function getUsername(): string
    {
        return $this->{$this->getUsernameColumn()};
    }

    public function getPasswordHash(): string
    {
        return $this->{$this->getPasswordColumn()};
    }

    public function getUsernameColumn(): string
    {
        return self::$usernameColumn ?? 'username';
    }

    public function getIdColumn(): string
    {
        return self::$idColumn ?? 'id';
    }

    public function getPasswordColumn(): string
    {
        return self::$passwordColumn ?? 'password';
    }
}


