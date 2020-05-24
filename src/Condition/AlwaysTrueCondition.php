<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition;


use iggyvolz\yingadb\Condition\Resolved\ResolvedAlwaysTrueCondition;
use iggyvolz\yingadb\Condition\Resolved\ResolvedCondition;

/**
 * A condition that is always false
 */
class AlwaysTrueCondition extends Condition
{
    public function __construct()
    {
    }

    public function resolveFor(string $class): ResolvedCondition
    {
        return new ResolvedAlwaysTrueCondition();
    }
}
