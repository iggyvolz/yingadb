<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable\Condition;

/**
 * A condition that is true if & only if the row is active
 */
class ActiveCondition extends EqualCondition
{
    public function __construct()
    {
        parent::__construct("IsActive", true);
    }
}
