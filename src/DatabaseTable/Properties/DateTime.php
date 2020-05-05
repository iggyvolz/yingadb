<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable\Properties;

use DateTimeZone;
use GCCISWebProjects\Utilities\DatabaseTable\Condition\AllCondition;
use GCCISWebProjects\Utilities\DatabaseTable\Condition\EqualCondition;
use GCCISWebProjects\Utilities\DatabaseTable\Condition\NullCondition;

class DateTime extends Property
{
    /**
     * @param array<string,mixed> $dbrow
     */
    public function read(array $dbrow, string $database)
    {
        $result = $dbrow[$this->getDatabaseName()];
        if (is_null($result)) {
            return null;
        }
        $dt = \DateTime::createFromFormat("U", $dbrow[$this->getDatabaseName()]);
        $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return $dt;
    }
    /**
     * @param mixed $value
     * @param array<string,mixed> $dbrow
     */
    public function write($value, array &$dbrow, string $database): ?\Closure
    {
        /** @var \DateTime $value */
        if (is_null($value)) {
            $dbrow[$this->getDatabaseName()] = null;
        } else {
            $dbrow[$this->getDatabaseName()] = $value->format("U");
        }
        return null;
    }
    /**
     * @param mixed $value
     */
    public function check($value, AllCondition $condition, string $database): void
    {
        /** @var \DateTime $value */
        if (is_null($value)) {
            $condition->add(new NullCondition($this->Name));
        } else {
            $condition->add(new EqualCondition($this->Name, $value->format("U")));
        }
    }
    public static function isValidPropertyType(string $type1, ?string $type2, string $class, int $permissions): bool
    {
        return ($type1 === \DateTime::class || $type1 === "\\" . \DateTime::class) && is_null($type2);
    }
}
