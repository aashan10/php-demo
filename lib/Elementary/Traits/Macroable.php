<?php

declare(strict_types=1);

namespace Elementary\Traits;

trait Macroable 
{
    private static array $macros = [];

    public static function macro(string $name, callable $function) {
        self::$macros[$name] = $function;
    }

    public function __call(string $name, array $arguments): mixed
    {
        return $this->callMacro($name, $arguments);
    }

    protected function callMacro(string $name, array $args): mixed 
    {
        if (method_exists($this, $name)) {
            return $this->{$name}(...$args);
        }

        /** @var \Closure|null $macro */
        $macro = self::$macros[$name] ?? null;

        if (!$macro) {
            throw new \Elementary\Exceptions\MethodNotDefinedException($name, $this);
        }

        $macro->bindTo($this);
        return $macro->call($this, $args);
    }

}
