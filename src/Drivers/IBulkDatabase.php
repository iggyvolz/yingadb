<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Drivers;

use iggyvolz\yingadb\Transformers\Transformer;
use iggyvolz\yingadb\Exceptions\DuplicateEntry;
use iggyvolz\ClassProperties\Conditions\Condition;
use iggyvolz\yingadb\Condition\Resolved\ResolvedCondition;

/**
 * A database which attempts to make operations in bulk
 */
interface IBulkDatabase extends IDatabase
{
    /**
     * Update a row in the database
     *
     * @param string $table Table to operate on
     * @param array<string,Condition> $condition Condition to get data for
     * @param array<string,Transformer> $transformers Transformers that the rows use
     * @param array<string,string|int|float|null> $data $data Data to update for the row
     * @return bool True if the operation completed successfully, false if a manual update is needed
     * @throws DuplicateEntry if the target entry already exists
     */
    public function bulkUpdate(string $table, array $condition, array $transformers, array $data): bool;
    /**
     * Remove rows from the database
     *
     * @param string $table Table to operate on
     * @param array<string,Condition> $condition Condition to get data for
     * @param array<string,Transformer> $transformers Transformers that the rows use
     * @return bool True if the operation completed successfully, false if a manual update is needed
     */
    public function bulkDelete(string $table, array $condition, array $transformers): bool;
}
