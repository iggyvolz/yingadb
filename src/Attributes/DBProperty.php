<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Attributes;

use iggyvolz\yingadb\DatabaseEntry;
use iggyvolz\ClassProperties\Hooks\PreGet;
use iggyvolz\ClassProperties\Hooks\PostSet;
use iggyvolz\virtualattributes\VirtualAttribute;

// <<PhpAttribute>>
/**
 * @property-read string $columnName
 */
class DBProperty extends VirtualAttribute implements PostSet, PreGet
{
    private string $columnName;
    public function __construct(string $columnName = null)
    {
        $this->columnName = $columnName;
    }
    public function __get(string $prop):?string
    {
        if($prop === "columnName") {
            return $this->columnName;
        }
        return null;
    }
    public function runPostSetHook(ClassProperties $target, string $property, $value): void
    {
        if(!$target instanceof DatabaseEntry)
        {
            throw new \LogicException("DBProperty not allowed on non-DatabaseEntry object");
        }
        $target->runPostSetHook($property, $value);
    }
    public function runPreSetHook(ClassProperties $target, string $property, &$value): void
    {
        if(!$target instanceof DatabaseEntry)
        {
            throw new \LogicException("DBProperty not allowed on non-DatabaseEntry object");
        }
        $target->runPreSetHook();
    }
    public function runPreGetHook(ClassProperties $target, string $property): void
    {
        if(!$target instanceof DatabaseEntry)
        {
            throw new \LogicException("DBProperty not allowed on non-DatabaseEntry object");
        }
        $target->runPreGetHook($property);
    }

}