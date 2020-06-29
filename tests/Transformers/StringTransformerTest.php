<?php
declare(strict_types=1);
namespace iggyvolz\yingadb\tests;

use PHPUnit\Framework\TestCase;
use iggyvolz\yingadb\Transformers\StringTransformer;
use iggyvolz\yingadb\Exceptions\InvalidTransformerException;

class StringTransformerTest extends TestCase
{
    public function setUp():void
    {
        $this->transformer = new StringTransformer();
    }
    private StringTransformer $transformer;
    public function testStringToScalar():void
    {
        $this->assertSame("foo", $this->transformer->toScalar("foo"));
    }
    public function testNonStringToScalar():void
    {
        $this->expectException(InvalidTransformerException::class);
        $this->transformer->toScalar(6);
    }
    public function testStringFromScalar():void
    {
        $this->assertSame("foo", $this->transformer->fromScalar("foo"));
    }
    public function testNonStringFromScalar():void
    {
        $this->expectException(InvalidTransformerException::class);
        $this->transformer->fromScalar(6);
    }
}