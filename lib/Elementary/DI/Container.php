<?php

declare(strict_types=1);

namespace Elementary\DI;

use Elementary\DI\Exceptions\ContainerException;
use ReflectionClass;
use ReflectionParameter;

class Container
{
    private array $bindings = [];
    private array $instances = [];

    public function get(string $id)
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $instance = $this->resolve($id);
        $this->instances[$id] = $instance;

        return $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    public function make(string $id) 
    {
        return $this->resolve($id);
    }

    public function bind(string $abstract, callable|string|null $concrete = null): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }
        $this->bindings[$abstract] = $concrete;
    }

    private function resolve(string $id)
    {
        $concrete = $this->bindings[$id] ?? $id;

        if ($concrete instanceof \Closure) {
            return $concrete($this);
        }

        try {
            $reflectionClass = new ReflectionClass($concrete);
        } catch (\ReflectionException $e) {
            throw new ContainerException("Class {$concrete} does not exist.", 0, $e);
        }

        if (!$reflectionClass->isInstantiable()) {
            throw new ContainerException("Class {$id} is not instantiable.");
        }

        $constructor = $reflectionClass->getConstructor();

        if (!$constructor) {
            return new $concrete();
        }

        $parameters = $constructor->getParameters();

        if (empty($parameters)) {
            return new $concrete();
        }

        $dependencies = array_map(
            fn(ReflectionParameter $param) => $this->resolveDependency($param),
            $parameters
        );

        return $reflectionClass->newInstanceArgs($dependencies);
    }

    private function resolveDependency(ReflectionParameter $param)
    {
        $type = $param->getType();

        if (!$type) {
            throw new ContainerException("Cannot resolve class dependency because of missing type hint for parameter $\"{$param->getName()}\" in {$param->getDeclaringClass()->getName()}");
        }

        if ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) {
            throw new ContainerException("Cannot resolve union or intersection types for parameter $\"{$param->getName()}\" in {$param->getDeclaringClass()->getName()}");
        }

        if ($type->isBuiltin()) {
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            throw new ContainerException("Cannot resolve built-in parameter $\"{$param->getName()}\" in class {$param->getDeclaringClass()->getName()}");
        }

        return $this->get($type->getName());
    }
}
