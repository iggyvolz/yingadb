<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Drivers;

use iggyvolz\yingadb\Condition\Resolved\ResolvedCondition;
use iggyvolz\yingadb\Exceptions\DuplicateEntry;

interface IDatabase
{
    /**
     * Create a row
     *
     * @param string $table Table to operate on
     * @param array<string,string|int|float|null> $data Associative array of data to insert
     * @return null|int If the row contains an auto-increment column, return that row id, else null
     * @throws DuplicateEntry if the entry already exists
     */
    public function create(string $table, array $data): ?int;
    /**
     * Read a row from the database
     *
     * @param string $table Table to operate on
     * @param ResolvedCondition $condition Condition to get data for
     * @param null|int $limit Limit of number of rows to read
     * @param int $offset Where to start
     * @param array<string, bool> $order What column(s) to sort by
     * @param bool $prefetch Whether to fetch all entries immediately
     * @return iterable<array<string,string|int|float|null>> An iterator of rows which match the condition
     */
    public function read(
        string $table,
        ResolvedCondition $condition,
        int $limit = null,
        int $offset = 0,
        array $order = [],
        bool $prefetch = false
    ): iterable;
    /**
     * Update rows in the database
     *
     * @param string $table Table to operate on
     * @param ResolvedCondition $condition Condition to get data for
     * @param array<string,string|int|float|null> $data $data Data to insert into the database
     * @return void
     * @throws DuplicateEntry if the target entry already exists
     */
    public function update(string $table, ResolvedCondition $condition, array $data): void;
    /**
     * Remove rows from the database
     *
     * @param string $table Table to operate on
     * @param ResolvedCondition $condition Condition to remove data for
     * @return void
     */
    public function delete(string $table, ResolvedCondition $condition): void;
}
