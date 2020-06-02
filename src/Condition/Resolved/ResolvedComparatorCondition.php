<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition\Resolved;

use iggyvolz\ClassProperties\Attributes\ReadOnlyProperty;

/**
 * @property-read string $column
 * @property-read float|int|string|null $value
 */
abstract class ResolvedComparatorCondition extends ResolvedCondition
{
    // <<ReadOnlyProperty>>
    private string $column;
    /**
     * @var float|int|string|null
     */
    // <<ReadOnlyProperty>>
    private $value;
    private bool $lessThan;
    private bool $allowEqual;

    /**
     * @param string|int|float|null $value
     */
    public function __construct(string $column, $value, bool $lessThan, bool $allowEqual)
    {
        // @phan-suppress-next-line PhanAccessReadOnlyMagicProperty
        $this->column = $column;
        // @phan-suppress-next-line PhanAccessReadOnlyMagicProperty
        $this->value = $value;
        $this->lessThan = $lessThan;
        $this->allowEqual = $allowEqual;
    }

    /**
     * @param array<string,string|int|float|null> $row The row to check, associative array of column to value
     */
    public function check(array $row): bool
    {
        $val = $row[$this->column] ?? null;
        if (!is_int($val) && !is_float($val)) {
            return false;
        }
        if ($val === $this->value) {
            return $this->allowEqual;
        }
        return ($val < $this->value) === $this->lessThan;
    }
}
(new ReadOnlyProperty())->addToProperty(ResolvedEqualToCondition::class, "column");
(new ReadOnlyProperty())->addToProperty(ResolvedEqualToCondition::class, "value");
