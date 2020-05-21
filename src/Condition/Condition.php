<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition;


/**
 * A condition in the database
 */
abstract class Condition
{
    /**
     * Checks if a row satisfies the condition
     *
     * @param array<string,string|int|float|null> $row The row to check, associative array of column to value
     * @return bool True if the condition is satisfied, otherwise
     */
    abstract public function check(array $row): bool;
}
