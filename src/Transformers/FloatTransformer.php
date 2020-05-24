<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Transformers;

use iggyvolz\yingadb\Exceptions\InvalidTransformerException;

/**
 * Handles float values, passes them along
 */
class FloatTransformer extends Transformer
{
    /**
     * @param mixed $obj
     * @return float
     */
    public function toScalar($obj)
    {
        if(!is_float($obj)) {
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
        if(is_float($scalar)) {
            return $scalar;
        }
        throw new InvalidTransformerException();
    }
}
