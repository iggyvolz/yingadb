<?php
declare(strict_types=1);
namespace iggyvolz\yingadb\tests;

use PHPUnit\Framework\TestCase;
use iggyvolz\yingadb\DatabaseEntry;
use iggyvolz\ClassProperties\Identifiable;
use iggyvolz\ClassProperties\ClassProperties;
use iggyvolz\ClassProperties\Attributes\Property;
use iggyvolz\yingadb\Transformers\IntTransformer;
use iggyvolz\ClassProperties\Attributes\Identifier;
use iggyvolz\yingadb\Transformers\FloatTransformer;
use iggyvolz\yingadb\Transformers\IdentifiableTransformer;
use iggyvolz\yingadb\Exceptions\InvalidTransformerException;

class IdentifiableTransformerTest extends TestCase
{
    public function setUp():void
    {
        $this->testObject = new class extends Identifiable {
            <<Property>>
            <<Identifier>>
            private int $intCol = 4;
            public static function getFromIdentifier($identifier): ?self
            {
                static::init();
                $self = new self();
                $self->intCol = $identifier;
                return $self;
            }

        };
        $this->transformer = new IdentifiableTransformer(new IntTransformer, get_class($this->testObject));
    }
    private IdentifiableTransformer $transformer;
    private Identifiable $testObject;
    public function testIdentifiableToScalar():void
    {
        $this->assertSame(4, $this->transformer->toScalar($this->testObject));
    }
    public function testNonIdentifiableToScalar():void
    {
        $this->expectException(InvalidTransformerException::class);
        $this->transformer->toScalar(4);
    }
    public function testIdentifiableFromScalar():void
    {
        $this->assertSame(4, $this->transformer->fromScalar(4)->intCol);
    }
    public function testNonIdentifiableFromInvalidType():void
    {
        $this->expectException(InvalidTransformerException::class);
        $this->transformer->fromScalar("4");
    }
    public function testNonIdentifiableFromNonIdentifierType():void
    {
        $this->expectException(InvalidTransformerException::class);
        $this->transformer->fromScalar([]);
    }
    public function testNonidentifiableFromBadTransformerType():void
    {
        $this->expectException(InvalidTransformerException::class);
        $transformer = new IdentifiableTransformer(new FloatTransformer, get_class($this->testObject));
        $transformer->fromScalar(2.5);
    }
}