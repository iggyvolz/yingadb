<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable\Condition;

use DateTimeInterface;
use GCCISWebProjects\Utilities\ClassProperties\Identifiable;

/**
 * A condition where a column must not be one of many options
 */
class NotInListCondition extends AllCondition
{
    /**
     * @param int|string|bool|Identifiable|DateTimeInterface ...$value
     */
    public function __construct(string $column, ...$value)
    {
        parent::__construct(...array_map(
            /**
            * @param int|string|bool|Identifiable|DateTimeInterface $val
            */
            function ($val) use ($column): NotEqualCondition {
                return new NotEqualCondition($column, $val);
            },
            $value
        ));
    }
}
