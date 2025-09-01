<?php

declare(strict_types=1);

namespace Elementary\Kernel;

use Elementary\Http\Request; // Still needed for handleCli and type hinting
use Elementary\Http\Response;

interface KernelInterface
{
    public function bootstrap(): void;
    public function handle(): Response; // No Request parameter
    public function handleCli(array $argv): int;
    public function terminate(Response $response): void; // No Request parameter
    public function terminateCli(int $statusCode): void;
}