<?php
declare(strict_types=1);
namespace iggyvolz\yingadb\tests;

use PHPUnit\Framework\TestCase;
use iggyvolz\yingadb\Transformers\IntTransformer;
use iggyvolz\yingadb\Transformers\NullTransformer;
use iggyvolz\yingadb\Exceptions\InvalidTransformerException;

class NullTransformerTest extends TestCase
{
    public function setUp():void
    {
        $this->transformer = new NullTransformer(new IntTransformer());
    }
    private NullTransformer $transformer;
    public function testIntToScalar():void
    {
        $this->assertSame(5, $this->transformer->toScalar(5));
    }
    public function testNonIntToScalar():void
    {
        $this->expectException(InvalidTransformerException::class);
        $this->transformer->toScalar("5");
    }
    public function testNullToScalar():void
    {
        $this->assertNull($this->transformer->toScalar(null));
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
    public function testNullFromScalar():void
    {
        $this->assertNull($this->transformer->fromScalar(null));
    }
}