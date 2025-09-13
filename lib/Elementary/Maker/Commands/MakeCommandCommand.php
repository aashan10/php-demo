<?php

declare(strict_types=1);

namespace Elementary\Maker\Commands;

use Elementary\Console\Command;
use Elementary\Maker\Factories\CommandFactory;

class MakeCommandCommand extends Command
{
    public static string $signature = 'make:command {name} {--signature=} {--description=}';
    public static string $description = 'Create a new console command class. Usage: elementary make:command ProcessData --signature=app:process-data --description="Process some data"';

    public function __construct(private CommandFactory $factory) {}

    public function execute(array $args = []): int 
    {
        $name = $args[0] ?? null;

        if (!$name) {
            $this->error("Command class name is required.");
            return 1;
        }

        $signature = $this->parseOption($args, 'signature');
        if (!$signature) {
            $this->warning("No --signature provided. Using default 'command:name'.");
            $signature = 'command:name';
        }

        $commandDescription = $this->parseOption($args, 'description');
        if (!$commandDescription) {
            $this->warning("No --description provided. Using empty description.");
            $commandDescription = '';
        }

        $this->factory->make($name, $signature, $commandDescription);
        $this->success("Command '$name' created successfully.");

        return 0;
    }

    private function parseOption(array $args, string $optionName): ?string
    {
        $prefix = "--{$optionName}=";
        foreach ($args as $arg) {
            if (str_starts_with($arg, $prefix)) {
                return substr($arg, strlen($prefix));
            }
        }
        return null;
    }
}