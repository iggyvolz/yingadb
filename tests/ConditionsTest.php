<?php

namespace iggyvolz\yingadb\tests;

use iggyvolz\yingadb\Condition\EqualToCondition;
use iggyvolz\yingadb\Condition\GreaterThanCondition;
use iggyvolz\yingadb\Condition\GreaterThanOrEqualToCondition;
use iggyvolz\yingadb\Condition\LessThanCondition;
use iggyvolz\yingadb\Condition\LessThanOrEqualToCondition;
use iggyvolz\yingadb\DatabaseEntry;
use iggyvolz\yingadb\Drivers\MemoryDatabase;
use iggyvolz\yingadb\examples\TestClass;
use PHPUnit\Framework\TestCase;

class ConditionsTest extends TestCase
{
    public function setUp(): void
    {
        $db = new MemoryDatabase();
        DatabaseEntry::setDefaultDatabase($db);
        new TestClass("foo", 4);
        new TestClass("bar", 5);
        new TestClass("far", 6);
    }

    public function testEqualString(): void
    {
        $inst = [...TestClass::getAll(new EqualToCondition("strCol", "foo"))];
        $this->assertSame(1, count($inst));
        $this->assertSame(4, $inst[0]->intCol);
    }

    public function testEqualInt(): void
    {
        $inst = [...TestClass::getAll(new EqualToCondition("intCol", 5))];
        $this->assertSame(1, count($inst));
        $this->assertSame("bar", $inst[0]->strCol);
    }

    public function testInvalidEqual(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Could not find property invalid on " . TestClass::class);
        [...TestClass::getAll(new EqualToCondition("invalid", "foo"))];
    }

    public function testGreaterThan(): void
    {
        $inst = [...TestClass::getAll(new GreaterThanCondition("intCol", 5))];
        $this->assertSame(1, count($inst));
        $this->assertSame("far", $inst[0]->strCol);
    }

    public function testInvalidGreaterThan(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Could not find property invalid on " . TestClass::class);
        [...TestClass::getAll(new GreaterThanCondition("invalid", 5))];
    }

    public function testInvalidStringGreaterThan(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Could not resolve strCol to int|float on " . TestClass::class);
        [...TestClass::getAll(new GreaterThanCondition("strCol", "x"))];
    }

    public function testGreaterThanOrEqualTo(): void
    {
        $inst = [...TestClass::getAll(
            new GreaterThanOrEqualToCondition("intCol", 5),
            null,
            null,
            0,
            ["intCol" => true]
        )];
        $this->assertSame(2, count($inst));
        $this->assertSame("bar", $inst[0]->strCol);
        $this->assertSame("far", $inst[1]->strCol);
    }

    public function testInvalidGreaterThanOrEqualTo(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Could not find property invalid on " . TestClass::class);
        [...TestClass::getAll(new GreaterThanOrEqualToCondition("invalid", 5))];
    }

    public function testInvalidStringGreaterThanOrEqualTo(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Could not resolve strCol to int|float on " . TestClass::class);
        [...TestClass::getAll(new GreaterThanOrEqualToCondition("strCol", "x"))];
    }

    public function testLessThan(): void
    {
        $inst = [...TestClass::getAll(new LessThanCondition("intCol", 5))];
        $this->assertSame(1, count($inst));
        $this->assertSame("foo", $inst[0]->strCol);
    }

    public function testInvalidLessThan(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Could not find property invalid on " . TestClass::class);
        [...TestClass::getAll(new LessThanCondition("invalid", 5))];
    }

    public function testInvalidStringLessThan(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Could not resolve strCol to int|float on " . TestClass::class);
        [...TestClass::getAll(new LessThanCondition("strCol", "x"))];
    }

    public function testLessThanOrEqualTo(): void
    {
        $inst = [...TestClass::getAll(new LessThanOrEqualToCondition("intCol", 5), null, null, 0, ["intCol" => true])];
        $this->assertSame(2, count($inst));
        $this->assertSame("foo", $inst[0]->strCol);
        $this->assertSame("bar", $inst[1]->strCol);
    }

    public function testInvalidLessThanOrEqualTo(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Could not find property invalid on " . TestClass::class);
        [...TestClass::getAll(new LessThanOrEqualToCondition("invalid", 5))];
    }

    public function testInvalidStringLessThanOrEqualTo(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Could not resolve strCol to int|float on " . TestClass::class);
        [...TestClass::getAll(new LessThanOrEqualToCondition("strCol", "x"))];
    }
}
