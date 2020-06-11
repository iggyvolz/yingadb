<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Drivers;

use Closure;
use iggyvolz\yingadb\Condition\Resolved\ResolvedCondition;

class MemoryDatabase implements IDatabase
{
    /**
     * @var array<string, list<array<string,int|string|float|null>>>
     */
    public array $data = [];
    public function create(string $table, array $data): ?int
    {
        if (!array_key_exists($table, $this->data)) {
            $this->data[$table] = [];
        }
        // TODO unique/primary constraints
        $this->data[$table][] = $data;
        // TODO auto increment
        return null;
    }

    public function read(
        string $table,
        ResolvedCondition $condition,
        int $limit = null,
        int $offset = 0,
        array $order = [],
        bool $prefetch = false
    ): iterable {
        if (!array_key_exists($table, $this->data)) {
            return [];
        }
        $data = iterator_to_array($this->initialRead($table, $condition), false);
        usort($data, static::multiSort($order));
        return array_slice($data, $offset, $limit);
    }

    /**
    * @param array<string, bool> $order What column(s) to sort by
    * @return Closure(array<string, int|string|float|null>, array<string, int|string|float|null>):int
    */
    private function multiSort(array $order): Closure
    {
        return
        /**
        * @param array<string,int|string|float|null> $row1
        * @param array<string,int|string|float|null> $row2
        */
        function (array $row1, array $row2) use ($order): int {
            foreach ($order as $col => $asc) {
                if (array_key_exists($col, $row1) && array_key_exists($col, $row2)) {
                    $comp = $row1[$col] <=> $row2[$col];
                    if ($comp === 0) {
                        // Undefined behaviour
                        // @codeCoverageIgnoreStart
                        continue;
                        // @codeCoverageIgnoreEnd
                    }
                    if (!$asc) {
                        $comp *= -1;
                    }
                    return $comp;
                }
            }
            return 0;
        };
    }

    /**
     * @return \Generator<int, array<string, float|int|null|string>>
     * @phan-return \Generator<array<string, float|int|null|string>>
     */
    private function initialRead(
        string $table,
        ResolvedCondition $condition
    ): \Generator {
        foreach ($this->data[$table] as $row) {
            if ($condition->check($row)) {
                yield $row;
            }
        }
    }

    public function update(string $table, ResolvedCondition $condition, array $data): void
    {
        if (!array_key_exists($table, $this->data)) {
            return;
        }
        foreach ($this->data[$table] as &$row) {
            if ($condition->check($row)) {
                foreach ($data as $key => $value) {
                    $row[$key] = $value;
                }
            }
        }
    }

    public function delete(string $table, ResolvedCondition $condition): void
    {
        if (!array_key_exists($table, $this->data)) {
            return;
        }
        foreach ($this->data[$table] as $i => $row) {
            if ($condition->check($row)) {
                unset($this->data[$table][$i]);
            }
        }
    }
}
