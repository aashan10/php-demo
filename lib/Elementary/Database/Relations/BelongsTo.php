<?php

declare(strict_types=1);

namespace Elementary\Database\Relations;

use Elementary\Database\Model;

class BelongsTo extends Relation
{
    public function __construct(Model $related, Model $parent, string $foreignKey, string $ownerKey)
    {
        parent::__construct($related->query(), $parent, $foreignKey, $ownerKey);
    }
}
