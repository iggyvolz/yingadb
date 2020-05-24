<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition\Resolved;

use iggyvolz\ClassProperties\ClassProperties;

abstract class ResolvedCondition extends ClassProperties
{
    /**
     * Checks if a row satisfies the condition
     *
     * @param array<string,string|int|float|null> $row The row to check, associative array of column to value
     * @return bool True if the condition is satisfied, otherwise
     */
    abstract public function check(array $row): bool;
}