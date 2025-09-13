<?php

declare(strict_types=1);

namespace Elementary\Maker\Commands;

use Elementary\Console\Command;
use Elementary\Maker\Factories\ControllerFactory;

class MakeControllerCommand extends Command
{
    public static string $signature = 'make:controller {name}';
    public static string $description = 'Create a new controller class';

    public function __construct(private ControllerFactory $factory) {}

    public function execute(array $args = []): int 
    {
        $name = $args[0] ?? null;

        if (!$name) {
            $this->error("Controller name is required.");
            return self::FAILURE;
        }

        $this->factory->make($name);
        $this->success("Controller '$name' created successfully.");

        return self::SUCCESS;
    }
}
