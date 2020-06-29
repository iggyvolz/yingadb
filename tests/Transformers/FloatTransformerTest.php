<?php
declare(strict_types=1);
namespace iggyvolz\yingadb\tests;

use PHPUnit\Framework\TestCase;
use iggyvolz\yingadb\Transformers\FloatTransformer;
use iggyvolz\yingadb\Exceptions\InvalidTransformerException;

class FloatTransformerTest extends TestCase
{
    public function setUp():void
    {
        $this->transformer = new FloatTransformer();
    }
    private FloatTransformer $transformer;
    public function testFloatToScalar():void
    {
        $this->assertSame(5.0, $this->transformer->toScalar(5.0));
    }
    public function testNonFloatToScalar():void
    {
        $this->expectException(InvalidTransformerException::class);
        $this->transformer->toScalar("5.0");
    }
    public function testFloatFromScalar():void
    {
        $this->assertSame(5.0, $this->transformer->fromScalar(5.0));
    }
    public function testNonFloatFromScalar():void
    {
        $this->expectException(InvalidTransformerException::class);
        $this->transformer->fromScalar("5.0");
    }
}