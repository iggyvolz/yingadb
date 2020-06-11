<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition\Resolved;

use iggyvolz\ClassProperties\Attributes\ReadOnlyProperty;

/**
 * @property-read string $column
 * @property-read float|int|string|null $value
 */
class ResolvedNotEqualToCondition extends ResolvedCondition
{
    <<ReadOnlyProperty>>
    private string $column;
    /**
     * @var float|int|string|null
     */
    <<ReadOnlyProperty>>
    private $value;

    /**
     * @param string|int|float|null $value
     */
    public function __construct(string $column, $value)
    {
        // @phan-suppress-next-line PhanAccessReadOnlyMagicProperty
        $this->column = $column;
        // @phan-suppress-next-line PhanAccessReadOnlyMagicProperty
        $this->value = $value;
    }

    /**
     * @param array<string,string|int|float|null> $row The row to check, associative array of column to value
     */
    public function check(array $row): bool
    {
        return ($row[$this->column] ?? null) !== $this->value;
    }
}
