<?php
declare(strict_types=1);
namespace iggyvolz\yingadb\tests;

use PHPUnit\Framework\TestCase;
use iggyvolz\yingadb\Transformers\BoolTransformer;
use iggyvolz\yingadb\Exceptions\InvalidTransformerException;

class BoolTransformerTest extends TestCase
{
    public function setUp():void
    {
        $this->transformer = new BoolTransformer();
    }
    private BoolTransformer $transformer;
    public function testTrueToScalar():void
    {
        $this->assertSame(1, $this->transformer->toScalar(true));
    }
    public function testFalseToScalar():void
    {
        $this->assertSame(0, $this->transformer->toScalar(false));
    }
    public function testNonBoolToScalar():void
    {
        $this->expectException(InvalidTransformerException::class);
        $this->transformer->toScalar("5");
    }
    public function testTrueFromScalar():void
    {
        $this->assertSame(true, $this->transformer->fromScalar(1));
    }
    public function testFalseFromScalar():void
    {
        $this->assertSame(false, $this->transformer->fromScalar(0));
    }
    public function testNonBoolFromScalar():void
    {
        $this->expectException(InvalidTransformerException::class);
        $this->transformer->fromScalar("5");
    }
}