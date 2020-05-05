<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable\Properties;

use GCCISWebProjects\Utilities\DatabaseTable\Condition\AllCondition;
use GCCISWebProjects\Utilities\DatabaseTable\Condition\EqualCondition;

class Scalar extends Property
{
    /**
     * @param array<string,mixed> $dbrow
     * @return int|string|bool|float|resource
     */
    public function read(array $dbrow, string $database)
    {
        $res = $dbrow[$this->getDatabaseName()];
        switch ($this->type[0]) {
            case "int":
            case "integer":
                return intval($res);
            case "bool":
                return intval($res) === 1;
            case "string":
                return strval($res);
            case "float":
            case "double":
                return floatval($res);
            case "resource":
                return $res;
        }
    }
    /**
     * @param mixed $value
     * @param array<string,mixed> $dbrow
     */
    public function write($value, array &$dbrow, string $database): ?\Closure
    {
        switch ($this->type[0]) {
            case "int":
            case "integer":
                $value = intval($value);
                break;
            case "bool":
                $value = boolval($value) ? 1 : 0;
                break;
            case "string":
                $value = strval($value);
                break;
            case "float":
            case "double":
                $value = floatval($value);
                break;
            case "resource":
                break;
        }
        $dbrow[$this->getDatabaseName()] = $value;
        return null;
    }
    /**
     * @param mixed $value
     */
    public function check($value, AllCondition $condition, string $database): void
    {
        $condition->add(new EqualCondition($this->Name, $value));
    }
    public static function isValidPropertyType(string $type1, ?string $type2, string $class, int $permissions): bool
    {
        return is_null($type2) && in_array($type1, ["int", "integer", "bool", "string", "float", "double", "resource"]);
    }
}
