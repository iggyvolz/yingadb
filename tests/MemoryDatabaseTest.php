<?php

namespace iggyvolz\yingadb\tests;

use iggyvolz\yingadb\Condition\AlwaysFalseCondition;
use iggyvolz\yingadb\Condition\AlwaysTrueCondition;
use iggyvolz\yingadb\Condition\Resolved\ResolvedCondition;
use iggyvolz\yingadb\Drivers\MemoryDatabase;
use PHPUnit\Framework\TestCase;

class MemoryDatabaseTest extends TestCase
{
    private static function getTrue(): ResolvedCondition
    {
        return (new AlwaysTrueCondition())->resolveFor(self::class);
    }
    private static function getFalse(): ResolvedCondition
    {
        return (new AlwaysFalseCondition())->resolveFor(self::class);
    }
    private static function iterableToArray(iterable $it): array
    {
        return iterator_to_array((function () use ($it): \Generator {
            yield from $it;
        })());
    }
    public function testBasicUsage(): void
    {
        $instance = new MemoryDatabase();
        $testData = ["a" => "foo", "b" => "bar"];
        $instance->create("test_table", $testData);
        $outData = self::iterableToArray($instance->read("test_table", self::getTrue()));
        $this->assertSame([$testData], $outData);
    }
    public function testFalseCondition(): void
    {
        $instance = new MemoryDatabase();
        $testData = ["a" => "foo", "b" => "bar"];
        $instance->create("test_table", $testData);
        $outData = self::iterableToArray($instance->read("test_table", self::getFalse()));
        $this->assertSame([], $outData);
    }
    public function testReadFromNonExistentTable(): void
    {
        $instance = new MemoryDatabase();
        $this->assertEmpty(self::iterableToArray($instance->read("fake_table", self::getTrue())));
    }
    public function testLimit(): void
    {
        $db = new MemoryDatabase();
        $testData = [
            ["a" => "foo1", "b" => "bar1"],
            ["a" => "foo2", "b" => "bar2"],
            ["a" => "foo3", "b" => "bar3"],
        ];
        foreach ($testData as $row) {
            $db->create("table", $row);
        }
        $data = self::iterableToArray($db->read("table", self::getTrue(), 2));
        $this->assertSame(array_slice($testData, 0, 2), $data);
    }
    public function testLimitOffset(): void
    {
        $db = new MemoryDatabase();
        $testData = [
            ["a" => "foo1", "b" => "bar1"],
            ["a" => "foo2", "b" => "bar2"],
            ["a" => "foo3", "b" => "bar3"],
        ];
        foreach ($testData as $row) {
            $db->create("table", $row);
        }
        $data = self::iterableToArray($db->read("table", self::getTrue(), 1, 1));
        $this->assertSame(array_slice($testData, 1, 1), $data);
    }
    public function testUpdate(): void
    {
        $db = new MemoryDatabase();
        $testData = [
            ["a" => "foo", "b" => "bar"],
        ];
        foreach ($testData as $row) {
            $db->create("table", $row);
        }
        $db->update("table", self::getTrue(), ["a" => "bing"]);
        $newData = [
            ["a" => "bing", "b" => "bar"],
        ];
        $this->assertSame($newData, self::iterableToArray($db->read("table", self::getTrue())));
    }
    public function testUpdateNonExistentTable(): void
    {
        $instance = new MemoryDatabase();
        $instance->update("fake_table", self::getTrue(), ["bin" => "bang"]);
        $this->assertEmpty(self::iterableToArray($instance->read("fake_table", self::getTrue())));
    }
    public function testDelete(): void
    {
        $db = new MemoryDatabase();
        $testData = [
            ["a" => "foo", "b" => "bar"],
        ];
        foreach ($testData as $row) {
            $db->create("table", $row);
        }
        $db->delete("table", self::getTrue());
        $this->assertEmpty(self::iterableToArray($db->read("table", self::getTrue())));
    }
    public function testDeleteNonExistentTable(): void
    {
        $instance = new MemoryDatabase();
        $instance->delete("fake_table", self::getTrue());
        $this->assertEmpty(self::iterableToArray($instance->read("fake_table", self::getTrue())));
    }
}
