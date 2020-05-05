<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable\Condition;

use EmptyIterator;

/**
 * A condition that is always true
 */
class AlwaysTrueCondition extends Condition
{
    public function __construct()
    {
    }
    /**
     * Check if a row passes this condition
     *
     * @param array<string,scalar|null> $row
     */
    public function check(array $row): bool
    {
        return true;
    }
    
    public function getWhereClause(string $class, string &$query): \Iterator
    {
        $query = "1=1";
        return new EmptyIterator();
    }
}
