<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable\Properties;

use Exception;
use GCCISWebProjects\Utilities\ClassProperties\Identifiable as RealIdentifiable;
use GCCISWebProjects\Utilities\DatabaseTable\Condition\AllCondition;
use GCCISWebProjects\Utilities\DatabaseTable\Condition\EqualCondition;
use GCCISWebProjects\Utilities\DatabaseTable\DatabaseTable as RealDatabaseTable;
use GCCISWebProjects\Utilities\DatabaseTable\Exceptions\StateException;
use GCCISWebProjects\Utilities\DatabaseTable\Properties\Identifiable;

class DatabaseTable extends Identifiable
{
    /**
     * @param array<string,mixed> $dbrow
     */
    public function read(array $dbrow, string $database): RealDatabaseTable
    {
        $res = parent::read($dbrow, $database);
        /** @var RealDatabaseTable $res */
        $res->setCurrentState(RealDatabaseTable::STATE_PARTIAL_FRESH);
        return $res;
    }
    /**
     * @param mixed $value
     * @param array<string,mixed> $dbrow
     */
    public function write($value, array &$dbrow, string $database): ?\Closure
    {
        if ($value !== null) {
            if (is_int($value) || (is_string($value) && $value !== "" )) {
                $type = $this->type[0];
                if ($type === "null") {
                    $type = $this->type[1];
                }
                /** @psalm-var class-string<RealDatabaseTable> $type */
                $identifier = RealIdentifiable::getIdentifierName($type);
                $ovalue = $value;
                $value = $type::get(new EqualCondition($identifier, $value));
                if ($value === null) {
                    throw new Exception("Object with identifier '$ovalue' not found for $type");
                }
            }
            if (!$value instanceof RealDatabaseTable) {
                throw new Exception("Invalid value (type " . get_class($value) . ") passed for database table");
            }
            switch ($value->getCurrentState()) {
                case RealDatabaseTable::STATE_INITIALIZED:
                    $value->insert();
                    break;
                case RealDatabaseTable::STATE_FRESH:
                case RealDatabaseTable::STATE_PARTIAL_FRESH:
                    break;
                case RealDatabaseTable::STATE_MODIFIED:
                    $value->update();
                    break;
                case RealDatabaseTable::STATE_DISCARDED:
                    throw new StateException("Attempt to write a database table with a discarded object");
            }
        }
        return parent::Write($value, $dbrow, $database);
    }
    public function check($value, AllCondition $condition, string $database): void
    {
        parent::Check($value, $condition, $database);
    }

    public static function isValidPropertyType(string $type1, ?string $type2, string $class, int $permissions): bool
    {
        return is_subclass_of($type1, RealDatabaseTable::class) && is_null($type2);
    }
}
