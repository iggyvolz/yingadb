<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable\Condition;

use DateTimeInterface;
use iggyvolz\yingadb\Condition\Condition;
use LogicException;

/**
 * A condition that is true if & only if a column equals a value
 */
class IdentifierIsCondition extends Condition
{
    /**
     * Value to ensure it's equal to
     *
     * @var string|int
     */
    private $value;

    /**
     * @param int|string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }
    /**
     * Check if a row passes this condition
     *
     * @param array<string,scalar|null> $row
     */
    public function check(array $row): bool
    {
        $column = $class::getIdentifierName();
        return array_key_exists($column, $row) && ($this->value === $row[$column]);
    }
}
