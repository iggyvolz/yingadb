<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition;

/**
 * A condition that is true if & only if a column is null
 */
class IsNullCondition extends EqualToCondition
{
    public function __construct(string $column)
    {
        parent::__construct($column, null);
    }
}
