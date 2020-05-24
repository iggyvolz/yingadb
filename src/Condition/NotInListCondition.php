<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition;

/**
 * A condition where a column must be one of many options
 */
class NotInListCondition extends AnyCondition
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
            function ($val) use ($column): NotEqualCondition {
                return new NotEqualCondition($column, $val);
            },
            $value
        ));
    }
}
