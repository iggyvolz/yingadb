<?php

namespace iggyvolz\yingadb\tests;

use iggyvolz\yingadb\Condition\AlwaysTrueCondition;
use iggyvolz\yingadb\DatabaseEntry;
use iggyvolz\yingadb\Drivers\MemoryDatabase;
use iggyvolz\yingadb\examples\TestClass;
use iggyvolz\yingadb\examples\TestClassWithName;
use PHPUnit\Framework\TestCase;

class DatabaseEntryTest extends TestCase
{
    public function setUp(): void
    {
        DatabaseEntry::setDefaultDatabase(null);
    }

    public function testConstructionAndRetrieval(): void
    {
        $db = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        new TestClass("val", 4, $db);
        $newInstance = TestClass::get([], $db);
        $this->assertNotNull($newInstance);
        $this->assertSame("val", $newInstance->strCol);
        $this->assertSame($db, $newInstance->database);
    }

    public function testRetrievalWithoutInstance(): void
    {
        $db = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        $this->assertNull(TestClass::get([], $db));
    }

    public function testNamedClassName(): void
    {
        $getTableName = (new \ReflectionClass(TestClassWithName::class))->getMethod("getTableName");
        $getTableName->setAccessible(true);
        $this->assertSame("tableName", $getTableName->invoke(null));
    }

    public function testUnnamedClassName(): void
    {
        $getTableName = (new \ReflectionClass(TestClass::class))->getMethod("getTableName");
        $getTableName->setAccessible(true);
        $this->assertSame(hash("sha256", TestClass::class), $getTableName->invoke(null));
    }

    public function testSettingDefaultDatabase(): void
    {
        $db = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        DatabaseEntry::setDefaultDatabase($db);
        $this->assertSame(DatabaseEntry::getDefaultDatabase(), $db);
    }

    public function testConstructionWithDefaultDatabase(): void
    {
        $db = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        DatabaseEntry::setDefaultDatabase($db);
        new TestClass("val");
        $newInstance = TestClass::get([], $db);
        $this->assertNotNull($newInstance);
        $this->assertSame("val", $newInstance->strCol);
        $this->assertSame($db, $newInstance->database);
    }

    public function testConstructionWithoutDatabase(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("No default database set");
        new TestClass("val");
    }

    public function testSyncDeleted(): void
    {
        $db = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        $inst = new TestClass("val", 4, $db);
        $inst->delete();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cannot synchronize a deleted object");
        $inst->sync();
    }

    public function testDeleteDeleted(): void
    {
        $db = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        $inst = new TestClass("val", 4, $db);
        $inst->delete();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cannot delete an already deleted object");
        $inst->delete();
    }

    public function testGetFromDeleted(): void
    {
        $db = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        $inst = new TestClass("val", 4, $db);
        $inst->delete();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cannot get a property on a deleted object");
        $inst->strCol;
    }

    public function testSetFromDeleted(): void
    {
        $db = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        $inst = new TestClass("val", 4, $db);
        $inst->delete();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Cannot set a property on a deleted object");
        $inst->strCol = "bar";
    }

    public function testGetFromIdentifier(): void
    {
        $db = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        DatabaseEntry::setDefaultDatabase($db);
        new TestClass("val");
        $newInstance = TestClass::getFromIdentifier("val");
        $this->assertNotNull($newInstance);
        $this->assertSame("val", $newInstance->strCol);
        $this->assertSame($db, $newInstance->database);
    }

    public function testGetFromIdentifierForced(): void
    {
        $db = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        DatabaseEntry::setDefaultDatabase($db);
        new TestClass("val");
        $newInstance = TestClass::getFromIdentifierForced("val");
        $this->assertNotNull($newInstance);
        $this->assertSame("val", $newInstance->strCol);
        $this->assertSame($db, $newInstance->database);
    }

    public function testGetFromIdentifiable(): void
    {
        $db = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        DatabaseEntry::setDefaultDatabase($db);
        $inst = new TestClass("val");
        $newInstance = TestClass::getFromIdentifierForced($inst);
        $this->assertNotNull($newInstance);
        $this->assertSame("val", $newInstance->strCol);
        $this->assertSame($db, $newInstance->database);
    }

    public function testGettingFromOneDatabaseOrdered(): void
    {
        $db = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        DatabaseEntry::setDefaultDatabase($db);
        new TestClass("val1");
        new TestClass("val2");
        $instances = [...(TestClass::getAll(
            [],
            null,
            null,
            0,
            ["strCol" => true]
        ))];
        $this->assertSame(2, count($instances));
        [$ninst1, $ninst2] = $instances;
        $this->assertSame("val1", $ninst1->strCol);
        $this->assertSame("val2", $ninst2->strCol);
    }

    public function testGettingFromOneDatabaseReverseOrdered(): void
    {
        $db = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        DatabaseEntry::setDefaultDatabase($db);
        new TestClass("val1");
        new TestClass("val2");
        $instances = [...(TestClass::getAll(
            [],
            null,
            null,
            0,
            ["strCol" => false]
        ))];
        $this->assertSame(2, count($instances));
        [$ninst2, $ninst1] = $instances;
        $this->assertSame("val1", $ninst1->strCol);
        $this->assertSame("val2", $ninst2->strCol);
    }

    public function testGettingFromMultipleDatabases(): void
    {
        $db1 = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        $db2 = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        new TestClass("val1", 4, $db1);
        new TestClass("val2", 4, $db2);
        $instances = [...(TestClass::getAll(
            [],
            [$db1, $db2],
            null,
            0,
            ["strCol" => true]
        ))];
        $this->assertSame(2, count($instances));
        [$ninst1, $ninst2] = $instances;
        $this->assertSame("val1", $ninst1->strCol);
        $this->assertSame($db1, $ninst1->database);
        $this->assertSame("val2", $ninst2->strCol);
        $this->assertSame($db2, $ninst2->database);
    }

    public function testGettingFromMultipleDatabasesReversed(): void
    {
        $db1 = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        $db2 = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        new TestClass("val1", 4, $db1);
        new TestClass("val2", 4, $db2);
        $instances = [...(TestClass::getAll(
            [],
            [$db1, $db2],
            null,
            0,
            ["strCol" => false]
        ))];
        $this->assertSame(2, count($instances));
        [$ninst2, $ninst1] = $instances;
        $this->assertSame("val1", $ninst1->strCol);
        $this->assertSame($db1, $ninst1->database);
        $this->assertSame("val2", $ninst2->strCol);
        $this->assertSame($db2, $ninst2->database);
    }

    public function testGettingFromMultipleDatabasesUnordered(): void
    {
        $db1 = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        $db2 = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        new TestClass("val1", 4, $db1);
        new TestClass("val2", 4, $db2);
        $instances = [...(TestClass::getAll([], [$db1, $db2]))];
        $this->assertSame(2, count($instances));
        $values = array_map(function (TestClass $t): string {
            return $t->strCol;
        }, $instances);
        $this->assertTrue(in_array("val1", $values));
        $this->assertTrue(in_array("val2", $values));
    }

    public function testGettingAllFromNoDatabase(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("No default database set");
        [...(TestClass::getAll())];
    }

    public function testSortingInvalid(): void
    {
        $db1 = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        new TestClass("val1", 4, $db1);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid property name invalid for sorting");
        [...(TestClass::getAll([], $db1, null, 0, ["invalid" => true]))];
    }

    public function testDeletingManyWithoutDatabase(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("No default database set");
        TestClass::deleteMany([]);
    }

    public function testDeletingMany(): void
    {
        $db1 = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        DatabaseEntry::setDefaultDatabase($db1);
        new TestClass("val1");
        new TestClass("val2");
        TestClass::deleteMany([]);
        $this->assertSame(0, count([...(TestClass::getAll())]));
    }

    // TODO tests to update the identifier
    public function testUpdatingMany(): void
    {
        $db1 = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        DatabaseEntry::setDefaultDatabase($db1);
        new TestClass("val1");
        new TestClass("val2");
        TestClass::updateMany(["intCol" => 4], []);
        $data = [...(TestClass::getAll())];
        $this->assertSame(2, count($data));
        $this->assertSame(4, $data[0]->intCol);
        $this->assertSame(4, $data[1]->intCol);
    }

    public function testUpdatingManyWithoutDatabase(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("No default database set");
        TestClass::updateMany(["strCol" => "bing"], []);
    }

    public function testUpdatingManyWithInvalidColumn(): void
    {
        $db1 = new MemoryDatabase([TestClass::class, TestClassWithName::class]);
        DatabaseEntry::setDefaultDatabase($db1);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid property name invalidColumn for updating");
        TestClass::updateMany(["invalidColumn" => "bing"], []);
    }
}
