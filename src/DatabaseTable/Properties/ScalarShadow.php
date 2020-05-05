<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable\Properties;

/**
 * Publicly-constructible shadow of the Scalar class
 */
class ScalarShadow extends Scalar
{
    /**
     * @param string[] $type
     */
    public function __construct(string $class, string $name, array $type, int $permissions, string $description)
    {
        parent::__construct($class, $name, $type, $permissions, $description);
    }
}
