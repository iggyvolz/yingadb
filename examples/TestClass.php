<?php

namespace iggyvolz\yingadb\examples;

use iggyvolz\ClassProperties\Attributes\Identifier;
use iggyvolz\ClassProperties\Attributes\Property;
use iggyvolz\yingadb\Attributes\DBProperty;
use iggyvolz\yingadb\DatabaseEntry;
use iggyvolz\yingadb\Drivers\IDatabase;

/**
 * @property string $strCol
 * @property int $intCol
 */
class TestClass extends DatabaseEntry
{
    <<DBProperty("stringColumn")>>
    <<Property>>
    <<Identifier>>
    private string $strCol = "";
    <<DBProperty("integerColumn")>>
    <<Property>>
    private int $intCol = 4;
    public function __construct(string $strCol = "", int $intCol = 4, ?IDatabase $database = null)
    {
        $this->strCol = $strCol;
        $this->intCol = $intCol;
        parent::__construct($database);
    }
}
