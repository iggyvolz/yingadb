<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable\Properties;

use GCCISWebProjects\Utilities\DatabaseTable\Condition\AllCondition;
use GCCISWebProjects\Utilities\DatabaseTable\Condition\NullCondition;

class Nullable extends Property
{
    /**
     * Property that is the non-nullable of this type
     *
     * @var Property
     */
    private $proxyProperty;
    /**
     * @phan-param array{0:string,1:string} $type
     * @psalm-param array{0:string,1:string} $type
     * @param array $type
     */
    public function __construct(string $class, string $name, array $type, int $permissions, string $description)
    {
        parent::__construct($class, $name, $type, $permissions, $description);
        if ($this->type[0] === "null") {
            $realtype = $this->type[1];
        } elseif ($this->type[1] === "null") {
            $realtype = $this->type[0];
        } else {
            throw new \Exception("Nullable instantiated on non-nullable");
        }
        $proxyProperty = null;
        foreach (Property::PROPERTY_CLASSES as $cls) {
            /** @var Property $cls */
            if ($cls::isValidPropertyType($realtype, null, $this->Class, $permissions)) {
                $proxyProperty = new $cls($class, $name, [$realtype], $permissions, $description);
                break;
            }
        }
        if (is_null($proxyProperty)) {
            throw new \Exception("Nullable instantiated on non-nullable $class $name");
        }
        $this->proxyProperty = $proxyProperty;
    }
    /**
     * @param array<string,mixed> $dbrow
     */
    public function read(array $dbrow, string $database)
    {
        if (is_null($dbrow[$this->getDatabaseName()])) {
            return null;
        }
        return $this->proxyProperty->read($dbrow, $database);
    }
    /**
     * @param mixed $value
     * @param array<string,mixed> $dbrow
     */
    public function write($value, array &$dbrow, string $database): ?\Closure
    {
        if (is_null($value)) {
            $dbrow[$this->getDatabaseName()] = null;
            return null;
        } else {
            return $this->proxyProperty->write($value, $dbrow, $database);
        }
    }
    /**
     * @param mixed $value
     */
    public function check($value, AllCondition $condition, string $database): void
    {
        if (is_null($value)) {
            $condition->add(new NullCondition($this->getDatabaseName()));
        } else {
            $this->proxyProperty->check($value, $condition, $database);
        }
    }
    public static function isValidPropertyType(string $type1, ?string $type2, string $class, int $permissions): bool
    {
        if ($type1 === "null") {
            if (is_null($type2)) {
                return false; // we specified @property null $foo
            } else {
                $type = $type2;
            }
        } elseif ($type2 === "null") {
            $type = $type1;
        } else {
            return false;
        }
        foreach (Property::PROPERTY_CLASSES as $cls) {
            /** @var Property $class */
            if ($cls::isValidPropertyType($type, null, $class, $permissions)) {
                return true;
            }
        }
        return false;
    }
}
