<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition;


use DateTimeInterface;

/**
 * A condition where a column must be one of many options
 */
class InListCondition extends AnyCondition
{
    /**
     * @param mixed ...$value
     */
    public function __construct(string $column, ...$value)
    {
        parent::__construct(...array_map(
            /**
            * @param mixed $val
            */
            function ($val) use ($column): EqualToCondition {
                return new EqualToCondition($column, $val);
            },
            $value
        ));
    }
}
