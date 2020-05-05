<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable\Properties;

// @phan-suppress-next-line PhanUnreferencedUseNormal
use GCCISWebProjects\Utilities\ClassProperties\Identifiable as RealIdentifiable;
use GCCISWebProjects\Utilities\DatabaseTable\Condition\AllCondition;
use GCCISWebProjects\Utilities\DatabaseTable\Condition\EqualCondition;

class Identifiable extends Property
{
    /**
     * @param array<string,mixed> $dbrow
     */
    public function read(array $dbrow, string $database)
    {
        $identifier = $dbrow[$this->getDatabaseName()];
        /** @psalm-var class-string<RealIdentifiable> */
        $type = $this->Type[0];
        return $type::getFromIdentifier($identifier);
    }
    /**
     * @param mixed $value
     * @param array<string,mixed> $dbrow
     */
    public function write($value, array &$dbrow, string $database): ?\Closure
    {
        if ($value !== null) {
            if ($value instanceof \GCCISWebProjects\Utilities\ClassProperties\Identifiable) {
                $dbrow[$this->getDatabaseName()] = $value->getIdentifier();
            } else {
                $dbrow[$this->getDatabaseName()] = $value;
            }
        } else {
            $dbrow[$this->getDatabaseName()] = null;
        }
        return null;
    }
    /**
     * @param mixed $value
     */
    public function check($value, AllCondition $condition, string $database): void
    {
        if ($value instanceof \GCCISWebProjects\Utilities\ClassProperties\Identifiable) {
            $value = $value->getIdentifier();
        }
        $condition->add(new EqualCondition($this->Name, $value));
    }

    public static function isValidPropertyType(string $type1, ?string $type2, string $class, int $permissions): bool
    {
        foreach ([$type1, $type2] as $type) {
            if (
                $type !== null
                && $type !== "null"
                && (!is_subclass_of($type1, \GCCISWebProjects\Utilities\ClassProperties\Identifiable::class)
                || is_subclass_of($type1, \GCCISWebProjects\Utilities\DatabaseTable\DatabaseTable::class))
            ) {
                return false;
            }
        }
        return true;
    }
}
