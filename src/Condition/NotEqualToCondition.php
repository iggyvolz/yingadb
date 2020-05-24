<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition;

use iggyvolz\yingadb\Condition\Resolved\ResolvedCondition;
use iggyvolz\yingadb\Condition\Resolved\ResolvedNotEqualToCondition;

/**
 * A condition that is true if & only if a column equals a value
 */
class NotEqualToCondition extends Condition
{
    private string $property;
    /**
     * @var mixed
     */
    private $value;

    /**
     * @param mixed $value
     */
    public function __construct(string $property, $value)
    {
        $this->property = $property;
        $this->value = $value;
    }

    public function resolveFor(string $class): ResolvedCondition
    {
        $column = $class::getColumnName($this->property);
        if (is_null($column)) {
            throw new \LogicException("Could not find property " . $this->property . " on $class");
        }
        $value = $class::toScalar($this->property, $this->value);
        return new ResolvedNotEqualToCondition($column, $value);
    }
}
