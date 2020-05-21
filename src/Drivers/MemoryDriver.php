<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Drivers;

/**
 * A simple database driver for memory storage
 * Does not support unique columns or constraints and is incredibly memory/time-inefficient
 * Useful for unit testing purposes so that a full database instance is not needed
 */
class MemoryDriver implements IDatabase
{
    /**
     * @return array<string,array<string,array<int|string,array<string,int|string>>>>
     */
    public function getData()
    {
        return $this->data;
    }
    /**
     * Data stored in memory
     * Top level: database name
     * Second level: table name
     * Second level: row number
     * Third level: column
     *
     * @var array<string,array<string,array<int|string,array<string,int|string>>>>
     */
    private $data = [];
    /**
     * Map of tables to classes
     *
     * @var array<string, class-string>
     */
    private $tables = [];
    /**
     * Map of tables to their primary keys
     *
     * @var Property[]
     */
    private $primaryKeys = [];
    /**
     * Map of tables to their unique keys (each unique key is an array of properties)
     *
     * @var Property[][][]
     */
    private $uniqueKeys = [];
    /**
     * Map of tables to their columns
     *
     * @var string[][]
     */
    private $columns = [];
    /**
     * Map of tables to whether or not their primary keys are auto-increment
     *
     * @var bool[]
     */
    private $isAutoIncrement = [];
    /**
     * Constructs the databases
     *
     * @param string[] $databases List of databases to create
     * @param string[] $classes List of classes to create
     * @psalm-param class-string[] $classes List of classes to create
     */
    public function __construct(array $databases, array $classes)
    {
        foreach ($classes as $class) {
            if (is_subclass_of($class, DatabaseTable::class)) {
                $tableName = $class::getTableName();
                $this->tables[$tableName] = $class;
                /** @var Property $pkey */
                $pkey = $class::getPrimaryKey();
                $this->primaryKeys[$tableName] = $pkey;
                $this->uniqueKeys[$tableName] = $class::getUniqueKeys();
                $this->columns[$tableName] = array_map(function (Property $p): string {
                    return $p->Name;
                }, $class::getColumns());
                $this->isAutoIncrement[$tableName] = $pkey->hasTag("auto-increment");
            }
        }

        foreach ($databases as $database) {
            $this->data[$database] = [];
            foreach (array_keys($this->tables) as $table) {
                $this->data[$database][$table] = [];
            }
        }
    }
    /**
     * @return int|null
     */
    public function create(string $database, string $table, array $data): ?int
    {
        if (!array_key_exists($database, $this->data)) {
            throw new DatabaseNotFoundException($database);
        }
        if (!array_key_exists($table, $this->data[$database])) {
            throw new TableNotFoundException($table);
        }
        $pkey = $this->primaryKeys[$table] ?? null;
        $pkeyName = null;
        if (!is_null($pkey)) {
            $pkeyName = $pkey->Name;
            if (!isset($data[$pkeyName])) {
                // Primary key not set
                if ($this->isAutoIncrement[$table]) {
                    $data[$pkeyName] = count($this->data[$database][$table]);
                } else {
                    throw new ColumnNotSetException($pkeyName);
                }
            }
        }

        $pkeyval = $data[$pkeyName] ?? count($this->data[$database][$table]);
        // Check if any columns are not set
        foreach ($this->columns[$table] as $column) {
            if (!array_key_exists($column, $data)) {
                throw new ColumnNotSetException($column);
            }
        }
        $this->data[$database][$table][$pkeyval] = $data;
        if ($this->isAutoIncrement[$table] ?? false) {
            return is_int($pkeyval) ? $pkeyval : null;
        } else {
            return null;
        }
    }
    public function read(
        string $database,
        string $table,
        Condition $condition,
        int $limit = null,
        int $offset = 0,
        array $order = [],
        array $asc = [],
        bool $prefetch = false
    ): iterable {
        $current = 0;
        if (!array_key_exists($database, $this->data)) {
            throw new DatabaseNotFoundException($database);
        }
        if (!array_key_exists($table, $this->data[$database])) {
            throw new TableNotFoundException($table);
        }
        $data = $this->data[$database][$table];
        if (!empty($order)) {
            usort(
                $data, /**
                * @param array<string,int|string> $a
                * @param array<string,int|string> $b
                */
                function (array $a, array $b) use ($order, $asc): int {
                    foreach ($order as $i => $col) {
                        if ($sort = (strcmp(strval($a[$col]), strval($b[$col]))) !== 0) {
                            return (($asc[$i] ?? true) ? 1 : 0);
                        }
                    }
                    return 0;
                }
            );
        }
        foreach ($data as $row) {
            if ($condition->check($row)) {
                $current++;
                if ($current < $offset) {
                    continue;
                }
                if (!is_null($limit) && $current > ($offset + $limit)) {
                    break;
                }
                yield $row;
            }
        }
    }
    public function update(string $database, string $table, Condition $condition, array $data): void
    {
        foreach ($this->data[$database][$table] as $i => $row) {
            if ($condition->check($row)) {
                $this->data[$database][$table][$i] = array_merge($this->data[$database][$table][$i], $data);
            }
        }
    }
    public function delete(string $database, string $table, Condition $condition): void
    {
        foreach ($this->data[$database][$table] as $i => $row) {
            if ($condition->check($row)) {
                unset($this->data[$database][$table][$i]);
            }
        }
    }
}
