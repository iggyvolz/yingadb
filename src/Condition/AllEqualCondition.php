<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable\Condition;

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
        parent::__construct(...array_map(function (string $key) use ($condition): EqualCondition {
            return new EqualCondition($key, $condition[$key]);
        }, array_keys($condition)));
    }
}
