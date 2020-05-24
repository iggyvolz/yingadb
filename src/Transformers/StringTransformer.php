<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Transformers;

use iggyvolz\yingadb\Exceptions\InvalidTransformerException;

/**
 * Handles string values, passes them along
 */
class StringTransformer extends Transformer
{
    /**
     * @param mixed $obj
     * @return string
     */
    public function toScalar($obj)
    {
        if (!is_string($obj)) {
            throw new InvalidTransformerException();
        }
        return $obj;
    }
    /**
     * @param int|string|float|null $scalar
     * @return mixed
     */
    public function fromScalar($scalar)
    {
        if (is_string($scalar)) {
            return $scalar;
        }
        throw new InvalidTransformerException();
    }
}
