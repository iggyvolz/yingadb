<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition;

/**
 * A condition that is passed an array and acts as an AllCondition of EqualCondition(key, value)
 */
class AllEqualCondition extends AllCondition
{
    /**
     * @param array<string,mixed> $condition
     */
    public function __construct(array $condition)
    {
        parent::__construct(...array_map(function (string $key) use ($condition): EqualToCondition {
            return new EqualToCondition($key, $condition[$key]);
        }, array_keys($condition)));
    }
}
