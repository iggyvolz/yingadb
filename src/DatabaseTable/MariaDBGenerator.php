<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable;

use GCCISWebProjects\Utilities\ClassProperties\ClassProperties;
use GCCISWebProjects\Utilities\ClassProperties\ClassProperty;
use GCCISWebProjects\Utilities\ClassProperties\Identifiable;
use GCCISWebProjects\Utilities\DatabaseTable\DatabaseTable;
use GCCISWebProjects\Utilities\DatabaseTable\Properties\Nullable;
use GCCISWebProjects\Utilities\DatabaseTable\Properties\Property as DTProperty;

/**
 * Generates MariaDB schema from a table definition
 */
class MariaDBGenerator
{
    /**
     * @var string[]
     * @psalm-var class-string<DatabaseTable>[]
     */
    private $classes;
    /**
     * @psalm-param class-string<DatabaseTable> ...$classes
     */
    public function __construct(string ...$classes)
    {
        $this->classes = $classes;
    }
    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->toString();
    }
    /**
     * @return string
     */
    public function toString(): string
    {
        ob_start();
        foreach ($this->classes as $class) {

        /** @var class-string<DatabaseTable> $class */
            $table = $class::getTableName();
            echo "CREATE TABLE " . PDOMysqlDriver::escapeIdentifier($table) . " (\n";
            $first = true;
            $foreignKeys = [];
            $uniqueColumns = [];
            foreach (ClassProperty::getProperties($class) as $prop) {
                if (!$prop instanceof DTProperty) {
                    continue;
                }
                $fk = null;
                $type = $this->transformType($prop, $fk);
                if (!is_null($fk)) {
                    $foreignKeys[$prop->getDatabaseName()] = $fk;
                }
                if (is_null($type)) {
                    continue;
                }
                if ($first) {
                    $first = false;
                } else {
                    echo ",\n";
                }
                echo "  ";
                echo PDOMysqlDriver::escapeIdentifier($prop->getDatabaseName());
                echo " " . $type;
                echo " ";
                if (!$prop instanceof Nullable) {
                    echo "NOT ";
                }
                echo "NULL";
                if ($prop->hasTag("default")) {
                    /** @psalm-suppress PossiblyNullArgument */
                    $defaultVal = json_decode($prop->getTag("default"));
                    if ($defaultVal === true) {
                        $defaultVal = 1;
                    }
                    if ($defaultVal === false) {
                        $defaultVal = 0;
                    }
                    echo " DEFAULT " . json_encode($defaultVal);
                }
                if ($prop->hasTag("auto-increment")) {
                    echo " AUTO_INCREMENT";
                }
                if ($prop->hasTag("unique")) {
                    echo " UNIQUE KEY";
                }
                if ($prop->hasTag("unique-with")) {
                    /** @psalm-suppress PossiblyNullArgument */
                    $matches = json_decode($prop->getTag("unique-with"));
                    array_unshift($matches, $prop->Name);
                    $uniqueColumns[] = array_map(function (string $property) use ($class): string {
                        $property = ClassProperty::getProperty($class, $property);
                        assert($property instanceof DTProperty);
                        return $property->getDatabaseName();
                    }, $matches);
                }
                if (Identifiable::getIdentifierName($class) === $prop->Name) {
                    echo " PRIMARY KEY";
                }
            }
            foreach ($uniqueColumns as $uniqueColumnGroup) {
                $def = implode(", ", array_map([PDOMysqlDriver::class, "escapeIdentifier"], $uniqueColumnGroup));
                echo ",\n  UNIQUE INDEX `" . implode("_", $uniqueColumnGroup) . "` ($def)";
            }
            foreach ($foreignKeys as $col => $arr) {
                [$foreignTable, $foreignCol] = $arr;
                [$ecol,$eforeignTable,$eforeignCol] = array_map([PDOMysqlDriver::class, "escapeIdentifier"], [$col,$foreignTable,$foreignCol]);
                echo ",\n  INDEX `FK_${table}_${col}_idx` ($ecol)";
                echo ",\n  CONSTRAINT `FK_${table}_${col}` FOREIGN KEY ($ecol) REFERENCES $eforeignTable ($eforeignCol) ON UPDATE CASCADE";
            }
            echo "\n);\n";
        }
        return ob_get_clean();
    }
    /**
     * @phan-param array{0:string,1:string}|null $fk Foreign key that this type refers to (table:database)
     * @psalm-param array{0:string,1:string}|null $fk Foreign key that this type refers to (table:database)
     * @param array|null $fk Foreign key that this type refers to (table:database)
     */
    private static function transformType(ClassProperty $prop, ?array &$fk): ?string
    {
        $types = array_values(array_filter($prop->Type, function (string $type): bool {
            return $type !== "null" && $type !== \Iterator::class;
        }));
        if (count($types) > 1) {
            throw new \Exception("Too many types");
        }
        $type = $types[0];
        // Take care of enums
        if ($prop->hasTag("one-of")) {
            /** @psalm-suppress PossiblyNullArgument */
            $matches = array_map("json_encode", json_decode($prop->getTag("one-of")));
            return "ENUM(" . implode(",", $matches) . ")";
        }
        // Take care of scalars
        if ($type === "bool") {
            return "TINYINT(1)";
        }
        if ($type === "int" || $type === "integer") {
            return self::transformIntegralType($prop);
        }
        if ($type === "string" || $type === "resource") {
            return self::transformStringType($prop);
        }
        if ($type === "float" || $type === "double") {
            if ($prop->hasTag("decimal")) {
                /** @psalm-suppress PossiblyNullArgument */
                [$precision,$scale] = explode(" ", $prop->getTag("decimal"));
                return "DECIMAL($precision,$scale)";
            } else {
                return "DOUBLE";
            }
        }
        if (is_subclass_of($type, DatabaseTable::class)) {
            $fk = [$type::getTableName(), $type::getPrimaryKey()->getDatabaseName()];
            /** @var DatabaseTable $type */
            $_ = null;
            return self::transformType($type::getPrimaryKey(), $_);
        }
        if (is_subclass_of($type, ClassProperties::class)) {
            $_ = null;
            /** @var string $type */
            /** @psalm-suppress PossiblyNullArgument */
            return self::transformType(ClassProperty::getProperty($type, ClassProperties::getIdentifierName($type)), $_);
        }
        if ($type === \DateTime::class || $type === "\\" . \DateTime::class) {
            return "BIGINT";
        }
        // Take care of arrays
        if (substr($type, -2) === "[]") {
            return null;
        }
        throw new \Exception("Could not find type for $type in " . $prop->Name);
    }
    private static function transformStringType(ClassProperty $prop): string
    {
        $binary = $prop->hasTag("binary");
        $inline = null;
        if ($prop->hasTag("inline")) {
            $inline = true;
        }
        if ($prop->hasTag("not-inline")) {
            $inline = false;
        }
        $maxLength = null;
        $minLength = 0;
        if ($prop->hasTag("length")) {
            $maxLength = $minLength = intval($prop->getTag("length"));
        }
        if ($prop->hasTag("max-length")) {
            $maxLength = min(intval($prop->getTag("max-length")), $maxLength ?? PHP_INT_MAX);
        }
        if ($prop->hasTag("min-length")) {
            $minLength = max(intval($prop->getTag("min-length")), $minLength);
        }
        if ($prop->hasTag("not-empty")) {
            $minLength = max(1, $minLength);
        }
        if ($maxLength === null) {
            throw new \Exception("Unbounded string length for " . $prop->Name);
        }
        if (is_null($inline)) {
            // Guess whether we want to inline or not
            $inline = $maxLength < 10;
            if ($inline && $binary && $minLength !== $maxLength) {
                // Don't inline a binary column of variable length
                $inline = false;
            }
        }

        if ($inline) {
            if ($binary) {
                if ($minLength !== $maxLength) {
                    throw new \Exception("Refusing to inline a column of variable length");
                } else {
                    return "BINARY($maxLength)";
                }
            } else {
                return "CHAR($maxLength)";
            }
        } else {
            if ($binary) {
                return "BLOB($maxLength)";
            } else {
                return "TEXT($maxLength)";
            }
        }
    }
    private static function transformIntegralType(ClassProperty $prop): string
    {
        $min = PHP_INT_MIN;
        $max = PHP_INT_MAX;
        $suppressUnboundedWarning = $prop->hasTag("bigint");
        if ($prop->hasTag("unsigned")) {
            $min = max(0, $min);
            if ($prop->hasTag("tinyint")) {
                $max = min(255, $max);
            }
            if ($prop->hasTag("smallint")) {
                $max = min(65535, $max);
            }
            if ($prop->hasTag("mediumint")) {
                $max = min(16777215, $max);
            }
            if ($prop->hasTag("int")) {
                $max = min(4294967295, $max);
            }
        } else {
            if ($prop->hasTag("tinyint")) {
                $min = max(-128, $min);
                $max = min(127, $max);
            }
            if ($prop->hasTag("smallint")) {
                $min = max(-32768, $min);
                $max = min(32767, $max);
            }
            if ($prop->hasTag("mediumint")) {
                $min = max(-8388608, $min);
                $max = min(8388607, $max);
            }
            if ($prop->hasTag("int")) {
                $min = max(-2147483648, $min);
                $max = min(2147483647, $max);
            }
        }
        if ($prop->hasTag("min")) {
            $min = max(intval($prop->getTag("min")), $min);
        }
        if ($prop->hasTag("max")) {
            $max = min(intval($prop->getTag("max")), $max);
        }
        if ($prop->hasTag("between")) {
            /** @psalm-suppress PossiblyNullArgument */
            $min = max(intval(explode(" ", $prop->getTag("between"))[0]), $min);
            /** @psalm-suppress PossiblyNullArgument */
            $max = min(intval(explode(" ", $prop->getTag("between"))[1]), $max);
        }
        if ($min === PHP_INT_MIN && !$suppressUnboundedWarning) {
            throw new \Exception("Unbounded minimum for property " . $prop->Name . "($min,$max)");
        }
        if ($max === PHP_INT_MAX && !$suppressUnboundedWarning) {
            throw new \Exception("Unbounded maximum for property " . $prop->Name . "($min,$max)");
        }
        return self::getSmallestIntType($min, $max);
    }
    private static function getSmallestIntType(int $min, int $max): string
    {
        if ($min >= -128 && $max <= 127) {
            return "TINYINT";
        }
        if ($min >= -32768 && $max <= 32767) {
            return "SMALLINT";
        }
        if ($min >= -8388608 && $max <= 8388607) {
            return "MEDIUMINT";
        }
        if ($min >= -2147483648 && $max <= 2147483647) {
            return "INT";
        }
        return "BIGINT";
    }
}
