<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition;

use iggyvolz\yingadb\Condition\Resolved\ResolvedAnyCondition;
use \iggyvolz\yingadb\Condition\Resolved\ResolvedCondition;

/**
 * A condition that is comprised of a number of other conditions, all of which must be true
 */
class AnyCondition extends Condition
{
    /**
     * Conditions to check
     *
     * @var Condition[]
     */
    private array $conditions;
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

    public function resolveFor(string $class): ResolvedCondition
    {
        return new ResolvedAnyCondition(...array_map(function(Condition $c) use ($class):ResolvedCondition{
            return $c->resolveFor($class);
        }, $this->conditions));
    }
}
