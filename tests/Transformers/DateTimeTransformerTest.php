<?php
declare(strict_types=1);
namespace iggyvolz\yingadb\tests;

use DateTime;
use PHPUnit\Framework\TestCase;
use iggyvolz\yingadb\Transformers\DateTimeTransformer;
use iggyvolz\yingadb\Exceptions\InvalidTransformerException;

class DateTimeTransformerTest extends TestCase
{
    public function setUp():void
    {
        $this->transformer = new DateTimeTransformer();
    }
    private DateTimeTransformer $transformer;
    public function testDateTimeToScalar():void
    {
        $date = DateTime::createFromFormat("!U u", "1593460864 010101");
        $newDate = $this->transformer->toScalar($date);
        $this->assertSame(1593460864, $newDate);
    }
    public function testNonDateTimeToScalar():void
    {
        $this->expectException(InvalidTransformerException::class);
        $newDate = $this->transformer->toScalar(67);
    }
    public function testDateTimeFromScalar():void
    {
        $date = DateTime::createFromFormat("!U u", "1593460864 010101");
        $newDate = $this->transformer->fromScalar(1593460864);
        $this->assertSame($date->format("U"), $newDate->format("U"));
    }
    public function testDateTimeFromNonInt():void
    {
        $this->expectException(InvalidTransformerException::class);
        $newDate = $this->transformer->fromScalar("123456");
    }
}