<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Attributes;

use Attribute;
use iggyvolz\yingadb\DatabaseEntry;
use iggyvolz\ClassProperties\Hooks\PreGet;
use iggyvolz\ClassProperties\Hooks\PreSet;
use iggyvolz\ClassProperties\Hooks\PostSet;
use iggyvolz\ClassProperties\ClassProperties;
use iggyvolz\ClassProperties\Attributes\ReadOnlyProperty;

<<Attribute(Attribute::TARGET_PROPERTY)>>
/**
 * @property-read string $columnName
 */
class DBProperty extends ClassProperties implements PostSet, PreGet, PreSet
{
    <<ReadOnlyProperty>>
    private string $columnName;
    public function __construct(string $columnName)
    {
        // @phan-suppress-next-line PhanAccessReadOnlyMagicProperty
        $this->columnName = $columnName;
    }
    public function runPostSetHook(ClassProperties $target, string $property, $value): void
    {
        if (!$target instanceof DatabaseEntry) {
            throw new \LogicException("DBProperty not allowed on non-DatabaseEntry object");
        }
        // @phan-suppress-next-line PhanAccessMethodInternal
        $target->runPostSetHook($property, $value);
    }

    /**
     * @param mixed $value
     */
    public function runPreSetHook(ClassProperties $target, string $property, &$value): void
    {
        if (!$target instanceof DatabaseEntry) {
            throw new \LogicException("DBProperty not allowed on non-DatabaseEntry object");
        }
        // @phan-suppress-next-line PhanAccessMethodInternal
        $target->runPreSetHook();
    }
    public function runPreGetHook(ClassProperties $target, string $property): void
    {
        if (!$target instanceof DatabaseEntry) {
            throw new \LogicException("DBProperty not allowed on non-DatabaseEntry object");
        }
        // @phan-suppress-next-line PhanAccessMethodInternal
        $target->runPreGetHook($property);
    }
}
