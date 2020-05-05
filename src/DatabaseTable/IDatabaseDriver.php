<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable;

use GCCISWebProjects\Utilities\DatabaseTable\Condition\Condition;

interface IDatabaseDriver
{
    /**
     * Create a row
     *
     * @param string $database Database to connect to
     * @param string $table Table to operate on
     * @psalm-param class-string $table Table to operate on
     * @param array<string,string|int> $data Associative array of data to insert
     * @return null|int If the row contains an auto-increment column, return that row id, else null
     */
    public function create(string $database, string $table, array $data): ?int;
    /**
     * Read a row from the database
     *
     * @param string $database Database to connect to
     * @param string $class Class to operate on
     * @psalm-param class-string $class Class to operate on
     * @param Condition $condition Condition to get data for
     * @param null|int $limit Limit of number of rows to read
     * @param int $offset Where to start
     * @param string[] $order What column(s) to sort by
     * @param bool[] $asc Whether to sort ascending or descending
     * @param bool $prefetch Whether to fetch all entries immediately
     * @return iterable An iterator of rows which match the condition
     */
    public function read(
        string $database,
        string $class,
        Condition $condition,
        int $limit = null,
        int $offset = 0,
        array $order = [],
        array $asc = [],
        bool $prefetch = false
    ): iterable;
    /**
     * Update rows in the database
     *
     * @param string $database Database to connect to
     * @param string $class Class to operate on
     * @psalm-param class-string $class Class to operate on
     * @param Condition $condition Condition to get data for
     * @param array<string,string|int> $data $data Data to insert into the database
     * @return void
     */
    public function update(string $database, string $class, Condition $condition, array $data): void;
    /**
     * Remove rows from the database
     *
     * @param string $database Database to connect to
     * @param string $class Class to operate on
     * @psalm-param class-string $class Class to operate on
     * @param Condition $condition Condition to remove data for
     * @return void
     */
    public function delete(string $database, string $class, Condition $condition): void;
}
