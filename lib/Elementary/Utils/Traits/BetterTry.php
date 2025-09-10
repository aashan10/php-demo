<?php

declare(strict_types=1);

namespace Elementary\Utils\Traits;

trait BetterTry 
{
    /**
    * Attempts to execute a callable and captures any exceptions.
    * @template T
    *
    * @param callable $function The function to execute.
    * @return array{0:T|null, 1: \Throwable|null } An array containing the result and the exception (if any).
    */
    protected function try(callable $function): array 
    {
        try {
            $result = $function();
            return [$result, null];
        } catch (\Throwable $e) {
            return [null, $e];
        }
    }

}
