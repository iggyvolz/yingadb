<?php

namespace iggyvolz\yingadb\examples;

use iggyvolz\ClassProperties\Attributes\Identifier;
use iggyvolz\ClassProperties\Attributes\Property;
use iggyvolz\yingadb\Attributes\DBProperty;
use iggyvolz\yingadb\Attributes\TableName;
use iggyvolz\yingadb\DatabaseEntry;
use iggyvolz\yingadb\Drivers\IDatabase;

/**
 * @property string $strCol
 */
// <<TableName("tableName")>>
class TestClassWithName extends DatabaseEntry
{
    // <<DBProperty("stringColumn")>>
    // <<Property>>
    // <<Identifier>>
    private string $strCol = "";
    public function __construct(string $strCol = "", ?IDatabase $database = null)
    {
        $this->strCol = $strCol;
        parent::__construct($database);
    }
}
(new TableName("tableName"))->addToClass(TestClassWithName::class);
(new DBProperty("stringColumn"))->addToProperty(TestClass::class, "strCol");
(new Property())->addToProperty(TestClass::class, "strCol");
(new Identifier())->addToProperty(TestClass::class, "strCol");
