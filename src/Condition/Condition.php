<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition;


use iggyvolz\ClassProperties\ClassProperties;
use iggyvolz\yingadb\Condition\Resolved\ResolvedCondition;

/**
 * A condition in the database
 */
abstract class Condition extends ClassProperties
{
    /**
     * Resolves a condition for a class
     * @param string $class Class to resolve this condition for
     * @psalm-param class-string $class<DatabaseEntry>
     * @return ResolvedCondition Condition resolved with respect to $class
     */
    abstract public function resolveFor(string $class): ResolvedCondition;
}
