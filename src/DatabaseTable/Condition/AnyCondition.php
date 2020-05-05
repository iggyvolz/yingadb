<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable\Condition;

use GCCISWebProjects\Utilities\ArrayUtils;

/**
 * A condition that is comprised of a number of other conditions, any of which must be true
 */
class AnyCondition extends Condition
{
    /**
     * Conditions to check
     *
     * @var Condition[]
     */
    private $conditions;
    public function __construct(Condition ...$conditions)
    {
        $this->conditions = $conditions;
    }
    /**
     * Add a condition to this group
     *
     * @param Condition $condition Condition to add
     * @return void
     */
    public function add(Condition $condition): void
    {
        array_push($this->conditions, $condition);
    }
    /**
     * Check if a row passes this condition
     *
     * @param array<string,scalar|null> $row
     */
    public function check(array $row): bool
    {
        return ArrayUtils::some($this->conditions, function (Condition $c) use ($row): bool {
            return $c->check($row);
        });
    }
    public function getWhereClause(string $class, string &$query): \Iterator
    {
        $query = "(";
        $first = true;
        foreach (empty($this->conditions) ? [new AlwaysFalseCondition()] : $this->conditions as $condition) {
            if ($first) {
                $first = false;
            } else {
                $query .= " OR ";
            }
            $subquery = "";
            yield from $condition->getWhereClause($class, $subquery);
            $query .= $subquery;
        }
        $query .= ")";
    }
}
