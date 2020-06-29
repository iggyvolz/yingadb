<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Drivers;

use Closure;
use RuntimeException;
use InvalidArgumentException;
use iggyvolz\yingadb\DatabaseEntry;
use iggyvolz\ClassProperties\Conditions\Condition;
use iggyvolz\ClassProperties\Conditions\ConditionException;

class MemoryDatabase implements IDatabase
{
    /**
     * @var array<string, list<array<string,int|string|float|null>>>
     */
    private array $data = [];
    private array $identifiers = [];
    /**
     * @var string[] $classes
     * @psalm-var list<class-string<DatabaseEntry>> $classes
     */
    public function __construct(array $classes)
    {
        foreach($classes as $class) {
            if(!class_exists($class) || !is_subclass_of($class, DatabaseEntry::class)) {
                throw new InvalidArgumentException("Class $class is not a DatabaseEntry class");
            }
            $this->data[$class::getTableName()] = [];
            $this->identifiers[$class::getTableName()] = $class::getColumnName($class::getIdentifierName());
        }
    }
    public function create(string $table, array $data): ?int
    {
        if (!array_key_exists($table, $this->data)) {
            throw new RuntimeException("Invalid table"); // TODO better error handling
        }
        if(!array_key_exists($this->identifiers[$table], $data)) {
            throw new RuntimeException("Primary key `".$this->identifiers[$table]."` does not appear in the data set"); // TODO better error handling
        }
        // TODO unique/primary constraints
        $this->data[$table][$data[$this->identifiers[$table]]] = $data;
        // TODO auto increment
        return null;
    }

    public function read(
        string $table,
        array $condition,
        int $limit = null,
        int $offset = 0,
        array $order = [],
        bool $prefetch = false
    ): iterable {
        if (!array_key_exists($table, $this->data)) {
            throw new RuntimeException("Invalid table"); // TODO better error handling
        }
        $data = $this->data[$table];
        $data = array_values(array_filter($data, function(array $row) use($condition):bool {
            foreach($condition as $key => $c) {
                try {
                    $c->check($row[$key]);
                } catch(ConditionException) {
                    return false;
                }
            }
            return true;
        }));
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

    public function update(string $table, string|int $identifier, array $data): void
    {
        if (!array_key_exists($table, $this->data)) {
            throw new RuntimeException("Invalid table"); // TODO better error handling
        }
        if(array_key_exists($identifier, $this->data[$table])) {
            foreach ($data as $key => $value) {
                $this->data[$table][$identifier][$key] = $value;
            }
            // Check if identifier has changed
            $newidentifier = $data[$this->identifiers[$table]];
            if($identifier !== $newidentifier) {
                if(array_key_exists($newidentifier, $this->data[$table])) {
                    throw new RuntimeException("Duplicate key $newidentifier on $table, aborting update");
                }
                $this->data[$table][$newidentifier] = $this->data[$table][$identifier];
                unset($this->data[$table][$identifier]);
            }
        } else {
            throw new RuntimeException("Row $identifier not found on $table");
        }
    }

    public function delete(string $table, string|int $identifier): void
    {
        if (!array_key_exists($table, $this->data)) {
            throw new RuntimeException("Invalid table"); // TODO better error handling
        }
        if(array_key_exists($identifier, $this->data[$table])) {
            unset($this->data[$table][$identifier]);
        }
    }
}
