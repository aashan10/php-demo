<?php

declare(strict_types=1);

namespace Elementary\Maker\Commands;

use Elementary\Console\Command;
use Elementary\Maker\Factories\MiddlewareFactory;

class MakeMiddlewareCommand extends Command
{
    public static string $signature = 'make:middleware {name}';
    public static string $description = 'Create a new middleware class';

    public function __construct(private MiddlewareFactory $factory) {}

    public function execute(array $args = []): int 
    {
        $name = $args[0] ?? null;

        if (!$name) {
            $this->error("Middleware name is required.");
            return 1;
        }

        $this->factory->make($name);
        $this->success("Middleware '$name' created successfully.");

        return 0;
    }
}