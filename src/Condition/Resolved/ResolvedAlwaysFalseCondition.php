<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition\Resolved;

use iggyvolz\ClassProperties\Attributes\ReadOnlyProperty;

class ResolvedAlwaysFalseCondition extends ResolvedCondition
{
    public function __construct()
    {
    }

    /**
     * @param array<string,string|int|float|null> $row The row to check, associative array of column to value
     */
    public function check(array $row): bool
    {
        return false;
    }
}
