<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Attributes;

use iggyvolz\virtualattributes\VirtualAttribute;

// <<PhpAttribute>>
/**
 * @property-read string $tableName
 */
class TableName extends VirtualAttribute
{
    private string $tableName;
    public function __construct(string $tableName)
    {
        $this->tableName = $tableName;
    }
    public function __get(string $prop):?string
    {
        if($prop === "tableName") {
            return $this->tableName;
        }
        return null;
    }
}