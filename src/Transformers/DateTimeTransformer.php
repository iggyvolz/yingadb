<?php

declare(strict_types=1);

namespace iggyvolz\yingadb\Transformers;

use DateTime;
use iggyvolz\yingadb\Exceptions\InvalidTransformerException;

/**
 * Handles DateTime values, converts to a 64-bit Unix timestamp
 * Only use when microseconds can be dropped - otherwise use MicroDateTimeTransformer
 */
class DateTimeTransformer extends Transformer
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
        return intval($obj->format("U"));
    }
    /**
     * @param int|string|float|null $scalar
     * @return mixed
     */
    public function fromScalar($scalar)
    {
        if (is_int($scalar)) {
            $dt = DateTime::createFromFormat("U", strval($scalar));
            if (!$dt) {
                throw new \RuntimeException("Datetime converstion failure for $scalar");
            }
            return $dt;
        }
        throw new InvalidTransformerException();
    }
}
