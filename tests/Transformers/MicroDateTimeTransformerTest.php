<?php
declare(strict_types=1);
namespace iggyvolz\yingadb\tests;

use DateTime;
use PHPUnit\Framework\TestCase;
use iggyvolz\yingadb\Transformers\MicroDateTimeTransformer;
use iggyvolz\yingadb\Exceptions\InvalidTransformerException;
class MicroDateTimeTransformerTest extends TestCase
{
    public function setUp():void
    {
        $this->transformer = new MicroDateTimeTransformer();
    }
    private MicroDateTimeTransformer $transformer;
    public function testDateTimeToScalar():void
    {
        $date = DateTime::createFromFormat("!U u", "1593460864 010101");
        $newDate = $this->transformer->fromScalar($this->transformer->toScalar($date));
        $this->assertSame($date->format("U"), $newDate->format("U"));
        $this->assertSame($date->format("u"), $newDate->format("u"));
    }
    public function testNonDateTimeToScalar():void
    {
        $this->expectException(InvalidTransformerException::class);
        $newDate = $this->transformer->toScalar(67);
    }
    public function testDateTimeFromNonInt():void
    {
        $this->expectException(InvalidTransformerException::class);
        $newDate = $this->transformer->fromScalar("12345");
    }
}