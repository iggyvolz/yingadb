<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable\Condition;

use DateTimeInterface;
use GCCISWebProjects\Utilities\ClassProperties\ClassProperty;
use GCCISWebProjects\Utilities\ClassProperties\Identifiable;
use GCCISWebProjects\Utilities\DatabaseTable\PDOMysqlDriver;
use GCCISWebProjects\Utilities\DatabaseTable\Properties\Property;
use LogicException;

/**
 * A condition that is true if & only if a column equals a value
 */
class EqualCondition extends Condition
{
    /**
     * Column to check
     *
     * @var string
     */
    private $column;
    /**
     * Value to ensure it's equal to
     *
     * @var string|int
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
    /**
     * Check if a row passes this condition
     *
     * @param array<string,scalar|null> $row
     */
    public function check(array $row): bool
    {
        return array_key_exists($this->column, $row) && ($this->value === $row[$this->column]);
    }
    
    public function getWhereClause(string $class, string &$query): \Iterator
    {
        yield $this->value;
        $property = ClassProperty::getProperty($class, $this->column);
        if (!$property instanceof Property) {
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            throw new LogicException("Invalid property " . $this->column . " on $class");
        }
        $query = PDOMysqlDriver::escapeIdentifier($property->getDatabaseName()) . "=?";
    }
}
