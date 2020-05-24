<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Transformers;

use iggyvolz\yingadb\Exceptions\InvalidTransformerException;

/**
 * Handles int values, passes them along
 */
class IntTransformer extends Transformer
{
    /**
     * @param mixed $obj
     * @return int
     */
    public function toScalar($obj)
    {
        if(!is_int($obj)) {
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
        if(is_int($scalar)) {
            return $scalar;
        }
        throw new InvalidTransformerException();
    }
}
