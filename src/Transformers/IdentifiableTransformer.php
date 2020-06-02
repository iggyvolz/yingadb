<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Transformers;

use iggyvolz\ClassProperties\Identifiable;
use iggyvolz\yingadb\Exceptions\InvalidTransformerException;

/**
 * Transforms an identifiable to a scalar - needs an inner transformer to handle the identifier
 */
class IdentifiableTransformer extends Transformer
{
    private Transformer $other;
    /**
     * @psalm-var class-string<Identifiable>
     */
    private string $type;

    /**
     * @psalm-param class-string<Identifiable> $type
     */
    public function __construct(Transformer $other, string $type)
    {
        $this->other = $other;
        $this->type = $type;
    }

    /**
     * @param mixed $obj
     * @return int|string|float|null
     */
    public function toScalar($obj)
    {
        if ($obj instanceof Identifiable) {
            return $this->other->toScalar($obj->getIdentifier());
        } else {
            throw new InvalidTransformerException();
        }
    }
    /**
     * @param int|string|float|null $scalar
     * @return mixed
     */
    public function fromScalar($scalar)
    {
        /**
         * @var mixed
         */
        $identifier = $this->other->fromScalar($scalar);
        if (!is_string($identifier) && !is_int($identifier) && !($identifier instanceof Identifiable)) {
            throw new InvalidTransformerException();
        }
        return $this->type::getFromIdentifier($identifier);
    }
}
