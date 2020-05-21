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
     * @param array<string,scalar|null> $row The row to check, associative array of column to value
     * @param string $class Class to check this condition on
     * @psalm-param class-string<DatabaseEntry> $class
     * @return bool True if the condition is satisfied, otherwise
     */
    abstract public function check(array $row, string $class): bool;
}
