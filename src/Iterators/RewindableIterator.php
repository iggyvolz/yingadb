<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\Iterators;

use Closure;
use Iterator;

/**
 * Iterator that caches values as they are read to give out later
 * Instances which are constructed with the same iterator share a cache
 */
class RewindableIterator implements Iterator
{
    /**
     * @var int
     */
    private $position = 0;
    /**
     * @var Closure
     */
    private $validAt;
    /**
     * @var Closure
     */
    private $keyAt;
    /**
     * @var Closure
     */
    private $currentAt;
    public function __construct(Closure $validAt, Closure $keyAt, Closure $currentAt)
    {
        [$this->validAt, $this->keyAt, $this->currentAt] = [$validAt, $keyAt, $currentAt];
    }
    private function validAt(int $index): bool
    {
        return ($this->validAt)($index);
    }
    /**
     * @return scalar
     */
    private function keyAt(int $index)
    {
        return ($this->keyAt)($index);
    }
    /**
     * @return mixed
     */
    private function currentAt(int $index)
    {
        return ($this->currentAt)($index);
    }
    /**
     * @return mixed
     */
    public function current()
    {
        return $this->currentAt($this->position);
    }
    /**
     * @return scalar
     */
    public function key()
    {
        return $this->keyAt($this->position);
    }
    public function valid(): bool
    {
        return $this->validAt($this->position);
    }
    public function next(): void
    {
        $this->position++;
    }
    public function rewind(): void
    {
        $this->position = 0;
    }
}
