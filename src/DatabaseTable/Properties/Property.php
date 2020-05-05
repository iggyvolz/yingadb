<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable\Properties;

use GCCISWebProjects\Utilities\ClassProperties\ClassProperty;
use GCCISWebProjects\Utilities\DatabaseTable\Condition\AllCondition;

abstract class Property extends ClassProperty
{
    public function getDatabaseName(): string
    {
        $column = $this->getTag("database-column");
        if (!is_null($column)) {
            return json_decode(trim($column), false, 512, JSON_THROW_ON_ERROR);
        } else {
            return $this->Name;
        }
    }
    public const PROPERTY_CLASSES = [
        DatabaseTable::class, Identifiable::class, Scalar::class, Nullable::class, DateTime::class
        ];
    /**
     * Read a property from the database
     *
     * @param array<string,mixed> $dbrow The row returned from the database
     * @param string $database The database that this was retrieved from
     * @return mixed
     */
    abstract public function read(array $dbrow, string $database);
    /**
     * Write a property into the database
     *
     * @param mixed $value The requested value of the property
     * @param array<string,mixed> $dbrow The row to be returned from the database
     * @param string $database The database that this was retrieved from
     * @return \Closure|null If needed, a function of id $ident
     *   which will be called with the autoincrement ID after inserting the row.
     */
    abstract public function write($value, array &$dbrow, string $database): ?\Closure;
    /**
     * Check if this value can be inserted
     * @param mixed $value The requested value of the property
     * @param AllCondition $condition The condition that is applied
     * @param string $database The database that will be retrieved from
     */
    abstract public function check($value, AllCondition $condition, string $database): void;
    /**
     * Create an instance of a property
     *
     * @param string $class Class that this property is on
     * @param string $name Name of the property
     * @phan-param array{0:string,1?:string} $type Types of the property
     * @psalm-param array{0:string,1?:string} $type Types of the property
     * @param array $type Types of the property
     * @param int $permissions Read/write permissions
     * @param string $description Description of the property
     * @return Property A property object with the parameters set
     */
    public static function create(string $class, string $name, array $type, int $permissions, string $description): self
    {
        $propType = self::getPropertyType($type[0], $type[1] ?? null, $class, $permissions);
        return new $propType($class, $name, $type, $permissions, $description);
    }
    abstract public static function isValidPropertyType(string $type1, ?string $type2, string $class, int $permissions): bool;
    /**
     * @psalm-return class-string<self>
     */
    private static function getPropertyType(string $type1, ?string $type2, string $cls, int $permissions): string
    {
        if ($type1[0] === "\\") {
            $type1 = substr($type1, 1);
        }
        if ($type2 !== null && $type2[0] === "\\") {
            $type2 = substr($type2, 1);
        }
        if ($cls[0] === "\\") {
            $cls = substr($cls, 1);
        }
        foreach (self::PROPERTY_CLASSES as $class) {
            if ($class::isValidPropertyType($type1, $type2, $cls, $permissions)) {
                return $class;
            }
        }
        throw new \Exception("Invalid property for $cls, expected types " .
            (is_null($type2) ? ($type1) : ("$type1|$type2")));
    }
}
