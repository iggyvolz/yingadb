<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Transformers;

use DateTime;
use iggyvolz\yingadb\Exceptions\InvalidTransformerException;

/**
 * Handles DateTime values, converts to a 64-bit Unix timestamp
 * Only use when microseconds can be dropped - otherwise use MicroDateTimeTransformer
 */
class MicroDateTimeTransformer extends Transformer
{
    /**
     * @param mixed $obj
     * @return int
     */
    public function toScalar($obj)
    {
        if (!$obj instanceof DateTime) {
            throw new InvalidTransformerException();
        }
        return intval($obj->format("U")) * 1000 * 1000 + intval($obj->format("u"));
    }
    /**
     * @param int|string|float|null $scalar
     * @return mixed
     */
    public function fromScalar($scalar)
    {
        if (is_int($scalar)) {
            $seconds = floor($scalar / (1000 * 1000));
            $microseconds = $scalar % (1000 * 1000);
            $dt = DateTime::createFromFormat("U|u", "$seconds|$microseconds");
            if (!$dt) {
                throw new \RuntimeException("Datetime converstion failure for $scalar");
            }
            return $dt;
        }
        throw new InvalidTransformerException();
    }
}
