<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition;


use iggyvolz\yingadb\Condition\Resolved\ResolvedAlwaysFalseCondition;
use iggyvolz\yingadb\Condition\Resolved\ResolvedCondition;

/**
 * A condition that is always false
 */
class AlwaysFalseCondition extends Condition
{
    public function __construct()
    {
    }

    public function resolveFor(string $class): ResolvedCondition
    {
        return new ResolvedAlwaysFalseCondition();
    }
}
