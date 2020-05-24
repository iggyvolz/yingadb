<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition\Resolved;

class ResolvedLessThanCondition extends ResolvedComparatorCondition
{
    /**
     * @param string|int|float|null $value
     */
    public function __construct(string $column, $value)
    {
        parent::__construct($column, $value, true, false);
    }
}