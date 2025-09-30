<?php

declare(strict_types=1);

namespace Elementary\Database\Relations;

use Elementary\Database\Model;

class HasOne extends Relation
{
    public function __construct(Model $related, Model $parent, string $foreignKey, string $localKey)
    {
        parent::__construct($related->query(), $parent, $foreignKey, $localKey);
    }

    public function getResults()
    {
        return $this->query->first();
    }
}
