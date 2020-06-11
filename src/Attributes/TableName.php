<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Attributes;

use Attribute;
use iggyvolz\ClassProperties\ClassProperties;
use iggyvolz\ClassProperties\Attributes\ReadOnlyProperty;

<<Attribute(Attribute::TARGET_CLASS)>>
/**
 * @property-read string $tableName
 */
class TableName extends ClassProperties
{
    <<ReadOnlyProperty>>
    private string $tableName;
    public function __construct(string $tableName)
    {
        // @phan-suppress-next-line PhanAccessReadOnlyMagicProperty
        $this->tableName = $tableName;
    }
}
