<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable\Condition;

use DateTimeInterface;
use GCCISWebProjects\Utilities\ClassProperties\ClassProperty;
use GCCISWebProjects\Utilities\ClassProperties\Identifiable;
use GCCISWebProjects\Utilities\DatabaseTable\PDOMysqlDriver;
use GCCISWebProjects\Utilities\DatabaseTable\Properties\Property;

/**
 * A condition that is true if & only if a column is greater than or equal to a value
 */
class GreaterThanOrEqualToCondition extends Condition
{
    /**
     * Column to check
     *
     * @var string
     */
    private $column;
    /**
     * Value to ensure it's greater than
     *
     * @var int|string
     */
    private $value;
    /**
     * @param int|string|bool|Identifiable|DateTimeInterface $value
     */
    public function __construct(string $column, $value)
    {
        $value = parent::transform($value);
        [$this->column, $this->value] = [$column, $value];
    }
    public function check(array $row): bool
    {
        return array_key_exists($this->column, $row) && $this->value >= $row[$this->column];
    }
    
    public function getWhereClause(string $class, string &$query): \Iterator
    {
        yield $this->value;
        $property = ClassProperty::getProperty($class, $this->column);
        assert($property instanceof Property);
        $query = PDOMysqlDriver::escapeIdentifier($property->getDatabaseName()) . ">=?";
    }
}
