<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Drivers;

use iggyvolz\yingadb\Condition\Condition;

class MemoryDatabase implements IDatabase
{
    private array $data=[];
    public function create(string $table, array $data): ?int
    {
        if(!array_key_exists($table, $this->data)) {
            $this->data[$table] = [];
        }
        // TODO unique/primary constraints
        $this->data[$table][] = $data;
        // TODO auto increment
        return null;
    }

    public function read(string $table, Condition $condition, int $limit = null, int $offset = 0, array $order = [], bool $prefetch = false): iterable
    {
        if(!array_key_exists($table, $this->data)) {
            return;
        }
        $i=0;
        foreach($this->data[$table] as $row) {
            if(!is_null($limit) && $i >= $limit + $offset) {
                break;
            }
            if($condition->check($row)) {
                if($i >= $limit) {
                    yield $row;
                }
                $i++;
            }
        }
    }

    public function update(string $table, Condition $condition, array $data): void
    {
        if(!array_key_exists($table, $this->data)) {
            return;
        }
        foreach($this->data[$table] as &$row) {
            if($condition->check($row)) {
                foreach ($data as $key => $value) {
                    $row[$key] = $value;
                }
            }
        }
    }

    public function delete(string $table, Condition $condition): void
    {
        if(!array_key_exists($table, $this->data)) {
            return;
        }
        foreach($this->data[$table] as $i => $row) {
            if($condition->check($row)) {
                unset($this->data[$table][$i]);
            }
        }
    }
}