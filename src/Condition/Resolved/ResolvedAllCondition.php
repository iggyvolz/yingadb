<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Condition\Resolved;

use iggyvolz\ClassProperties\Attributes\ReadOnlyProperty;

/**
 * @property-read string $column
 * @property-read float|int|string|null $value
 */
class ResolvedAllCondition extends ResolvedCondition
{
    /**
     * @var ResolvedCondition[]
     */
    <<ReadOnlyProperty>>
    private array $conditions;

    public function __construct(ResolvedCondition ...$conditions)
    {
        $this->conditions = $conditions;
    }

    /**
     * @param array<string,string|int|float|null> $row The row to check, associative array of column to value
     */
    public function check(array $row): bool
    {
        foreach ($this->conditions as $condition) {
            if (!$condition->check($row)) {
                return false;
            }
        }
        return true;
    }
}
