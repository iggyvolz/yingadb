<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition\Resolved;

use iggyvolz\ClassProperties\Attributes\ReadOnlyProperty;

/**
 * @property-read string $column
 * @property-read float|int|string|null $value
 */
class ResolvedEqualToCondition extends ResolvedCondition
{
    // <<ReadOnlyProperty>>
    private string $column;
    /**
     * @var float|int|string|null
     */
    // <<ReadOnlyProperty>>
    private $value;

    /**
     * @param string|int|float|null $value
     */
    public function __construct(string $column, $value)
    {
        $this->column = $column;
        $this->value = $value;
    }

    /**
     * @param array<string,string|int|float|null> $row The row to check, associative array of column to value
     */
    public function check(array $row): bool
    {
        return ($row[$this->column] ?? null) === $this->value;
    }
}
(new ReadOnlyProperty())->addToProperty(ResolvedEqualToCondition::class, "column");
(new ReadOnlyProperty())->addToProperty(ResolvedEqualToCondition::class, "value");
