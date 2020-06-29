<?php
declare(strict_types=1);
namespace iggyvolz\yingadb\tests;

use PHPUnit\Framework\TestCase;
use iggyvolz\yingadb\Transformers\IntTransformer;
use iggyvolz\yingadb\Exceptions\InvalidTransformerException;

class IntTransformerTest extends TestCase
{
    public function setUp():void
    {
        $this->transformer = new IntTransformer();
    }
    private IntTransformer $transformer;
    public function testIntToScalar():void
    {
        $this->assertSame(5, $this->transformer->toScalar(5));
    }
    public function testNonIntToScalar():void
    {
        $this->expectException(InvalidTransformerException::class);
        $this->transformer->toScalar("5");
    }
    public function testIntFromScalar():void
    {
        $this->assertSame(5, $this->transformer->fromScalar(5));
    }
    public function testNonIntFromScalar():void
    {
        $this->expectException(InvalidTransformerException::class);
        $this->transformer->fromScalar("5");
    }
}