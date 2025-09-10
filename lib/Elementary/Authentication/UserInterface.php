<?php

declare(strict_types=1);

namespace Elementary\Authentication;

interface UserInterface 
{
    public function getId(): int|string;
    public function getUsername(): string;
    public function getPasswordHash(): string;
}
