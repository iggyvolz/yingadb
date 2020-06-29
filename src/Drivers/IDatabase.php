<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Drivers;

use iggyvolz\yingadb\Transformers\Transformer;
use iggyvolz\yingadb\Exceptions\DuplicateEntry;
use iggyvolz\ClassProperties\Conditions\Condition;
use iggyvolz\yingadb\Condition\Resolved\ResolvedCondition;

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
     * The engine may, but is not required to, limit its search by its $condition
     *
     * @param string $table Table to operate on
     * @param array<string,Condition> $condition Condition to get data for
     * @param array<string,Transformer> $transformers Transformers that the rows use
     * @param null|int $limit Limit of number of rows to read
     * @param int $offset Where to start
     * @param array<string, bool> $order What column(s) to sort by
     * @param bool $prefetch Whether to fetch all entries immediately
     * @return iterable<array<string,string|int|float|null>> An iterator of rows which match the condition
     */
    public function read(
        string $table,
        array $condition,
        int $limit = null,
        int $offset = 0,
        array $order = [],
        bool $prefetch = false
    ): iterable;
    /**
     * Update a row in the database
     *
     * @param string $table Table to operate on
     * @param string|int $identifier Identifier of the row to update
     * @param array<string,string|int|float|null> $data $data Data to update for the row
     * @return void
     * @throws DuplicateEntry if the target entry already exists
     */
    public function update(string $table, string|int $identifier, array $data): void;
    /**
     * Remove rows from the database
     *
     * @param string $table Table to operate on
     * @param string|int $identifier Identifier of the row to delete
     * @return void
     */
    public function delete(string $table, string|int $id): void;
}
