<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition;

use iggyvolz\yingadb\Condition\Resolved\ResolvedCondition;
use iggyvolz\yingadb\Condition\Resolved\ResolvedMatchesRegexCondition;

/**
 * A condition that is true if & only if a column matches a regex
 */
class MatchesRegexCondition extends Condition
{
    private string $property;
    private string $regex;
    public function __construct(string $property, string $regex)
    {
        $this->property = $property;
        $this->regex = $regex;
    }

    public function resolveFor(string $class): ResolvedCondition
    {
        $column = $class::getColumnName($this->property);
        if (is_null($column)) {
            throw new \LogicException("Could not find property " . $this->property . " on $class");
        }
        return new ResolvedMatchesRegexCondition($column, $this->regex);
    }
}
