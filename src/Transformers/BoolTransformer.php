<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Transformers;

use iggyvolz\yingadb\Exceptions\InvalidTransformerException;

/**
 * Handles bool values, converts to 1/0
 */
class BoolTransformer extends Transformer
{
    /**
     * @param mixed $obj
     * @return int|string|float|null
     */
    public function toScalar($obj)
    {
        if(!is_bool($obj)) {
            throw new InvalidTransformerException();
        }
        return $obj?1:0;
    }
    /**
     * @param int|string|float|null $scalar
     * @return mixed
     */
    public function fromScalar($scalar)
    {
        if($scalar === 1) {
            return true;
        } elseif($scalar === 0) {
            return false;
        } else {
            throw new InvalidTransformerException();
        }
    }
}
