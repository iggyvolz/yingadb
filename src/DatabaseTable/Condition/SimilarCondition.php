<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable\Condition;

use GCCISWebProjects\Utilities\ClassProperties\ClassProperty;
use GCCISWebProjects\Utilities\ClassProperties\Identifiable;
use GCCISWebProjects\Utilities\DatabaseTable\PDOMysqlDriver;
use GCCISWebProjects\Utilities\DatabaseTable\Properties\Property;

/**
 * A condition that is true if & only if a column is like a value (it matches case-insensitively anywhere in the string)
 */
class SimilarCondition extends Condition
{
    /**
     * Column to check
     *
     * @var string
     */
    private $column;
    /**
     * Value to ensure it's like
     *
     * @var string
     */
    private $value;
    /**
     * Whether to match only at the beginning of the string
     *
     * @var bool
     */
    private $strictStart;
    /**
     * Whether to match only at the end of the string
     *
     * @var bool
     */
    private $strictEnd;
    /**
     * @param int|string|Identifiable $value
     */
    public function __construct(string $column, $value, bool $strictStart = false, bool $strictEnd = false)
    {
        if ($value instanceof Identifiable) {
            $value = $value->getIdentifier();
        }
        [$this->column, $this->value, $this->strictStart, $this->strictEnd] = [$column, (string)$value, $strictStart, $strictEnd];
    }
    /**
     * Check if a row passes this condition
     *
     * @param array<string,scalar|null> $row
     */
    public function check(array $row): bool
    {
        return array_key_exists($this->column, $row) && preg_match("/" .
            ($this->strictStart ? "^" : "") .
            preg_quote($this->value, "/i") .
            ($this->strictEnd ? "$" : "") .
            "/", (string)($row[$this->column]));
    }
    
    public function getWhereClause(string $class, string &$query): \Iterator
    {
        $property = ClassProperty::getProperty($class, $this->column);
        assert($property instanceof Property);
        yield ($this->strictStart ? "^" : "") .
            preg_quote($this->value, "/i") .
            ($this->strictEnd ? "$" : "");
        $query = PDOMysqlDriver::escapeIdentifier($property->getDatabaseName()) . " REGEXP ?";
    }
}
