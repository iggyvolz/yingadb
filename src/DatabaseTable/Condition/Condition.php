<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable\Condition;

use DateTimeInterface;
use GCCISWebProjects\Utilities\ClassProperties\Identifiable;
use Iterator;

/**
 * A condition in the database
 */
abstract class Condition
{
    /**
     * Checks if a row satisfies the condition
     *
     * @param array<string,scalar|null> $row The row to check, associative array of column to value
     * @return bool True if the condition is satisfied, otherwise
     */
    abstract public function check(array $row): bool;
    /**
     * Get a where clause to satisfy this condition
     *
     * @param string $class The class that this condition applies to
     * @psalm-param class-string $class The class that this condition applies to
     * @param string $query The query string that should be used
     *  Must be populated once the iterator has run out
     * @return Iterator An iterator of data to be pushed into the query string
     */
    abstract public function getWhereClause(string $class, string &$query): Iterator;
    /**
     * Transform a value to a string or int
     * @param int|string|bool|DateTimeInterface|Identifiable $value
     * @return int|string
     */
    public static function transform($value)
    {
        if ($value instanceof Identifiable) {
            return $value->getIdentifier();
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format("U");
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        return $value;
    }
}
