<?php

declare(strict_types=1);

namespace Elementary\Database\Relations;

use Elementary\Database\Contracts\QueryBuilderInterface;
use Elementary\Database\Model;


abstract class Relation
{
    public function __construct(
        protected QueryBuilderInterface $query,
        protected Model $parent,
        protected string $foreignKey,
        protected string $localKey
    ) {}

    public function getQuery(): QueryBuilderInterface
    {
        return $this->query;
    }

    public function getModel(): Model
    {
        return $this->query->getModel();
    }

    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    public function getLocalKeyName(): string
    {
        return $this->localKey;
    }

    public function __call(string $method, array $parameters)
    {
        $result = $this->query->{$method}(...$parameters);

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }
}
