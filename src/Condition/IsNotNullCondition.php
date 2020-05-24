<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition;

/**
 * A condition that is true if & only if a column is not null
 */
class IsNotNullCondition extends NotEqualToCondition
{
    public function __construct(string $column)
    {
        parent::__construct($column, null);
    }
}
