<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition\Resolved;

use iggyvolz\ClassProperties\Attributes\ReadOnlyProperty;

/**
 * @property-read string $column
 * @property-read float|int|string|null $value
 */
class ResolvedMatchesRegexCondition extends ResolvedCondition
{
    // <<ReadOnlyProperty>>
    private string $column;
    // <<ReadOnlyProperty>>
    private string $regex;

    public function __construct(string $column, string $regex)
    {
        $this->column = $column;
        $this->regex = $regex;
    }

    /**
     * @param array<string,string|int|float|null> $row The row to check, associative array of column to value
     */
    public function check(array $row): bool
    {
        $val = $row[$this->column]??null;
        return is_string($val) && preg_match($this->regex, $val) === 1;
    }
}
(new ReadOnlyProperty)->addToProperty(ResolvedEqualToCondition::class, "column");
(new ReadOnlyProperty)->addToProperty(ResolvedEqualToCondition::class, "regex");