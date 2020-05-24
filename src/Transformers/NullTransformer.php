<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Transformers;

/**
 * Useful for null|??? - checks for null values, otherwise passes to other transformer
 */
class NullTransformer extends Transformer
{
    /**
     * @var Transformer
     */
    private Transformer $other;

    public function __construct(Transformer $other)
    {
        $this->other = $other;
    }

    /**
     * @param mixed $obj
     * @return int|string|float|null
     */
    public function toScalar($obj)
    {
        if(is_null($obj)) {
            return null;
        } else {
            return $this->other->toScalar($obj);
        }
    }
    /**
     * @param int|string|float|null $scalar
     * @return mixed
     */
    public function fromScalar($scalar)
    {
        if(is_null($scalar)) {
            return null;
        } else {
            return $this->other->fromScalar($scalar);
        }
    }
}
