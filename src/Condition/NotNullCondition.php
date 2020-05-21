<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable\Condition;

use EmptyIterator;
use GCCISWebProjects\Utilities\ClassProperties\ClassProperty;
use GCCISWebProjects\Utilities\DatabaseTable\PDOMysqlDriver;
use GCCISWebProjects\Utilities\DatabaseTable\Properties\Property;

/**
 * A condition that is true if & only if a column is null
 */
class NotNullCondition extends Condition
{
    /**
     * Column to check
     *
     * @var string
     */
    private $column;
    public function __construct(string $column)
    {
        $this->column = $column;
    }
    /**
     * Check if a row passes this condition
     *
     * @param array<string,scalar|null> $row
     */
    public function check(array $row): bool
    {
        return !is_null($row[$this->column]);
    }
    
    public function getWhereClause(string $class, string &$query): \Iterator
    {
        $property = ClassProperty::getProperty($class, $this->column);
        assert($property instanceof Property);
        $query = PDOMysqlDriver::escapeIdentifier($property->getDatabaseName()) . " IS NOT NULL";
        return new EmptyIterator();
    }
}
