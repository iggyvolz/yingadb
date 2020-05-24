<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Transformers;

abstract class Transformer
{
    /**
     * @param mixed $obj
     * @return int|string|float|null
     */
    public abstract function toScalar($obj);
    /**
     * @param int|string|float|null $scalar
     * @return mixed
     */
    public abstract function fromScalar($scalar);
}
