<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\Iterators;

use Generator;
use Iterator;

/**
 * Unique iterator with identifiables - will not yield the same value multiple times
 */
class UniqueIdentifiableIterator implements Iterator
{
    /**
     * Iterator that is used
     *
     * @var Iterator
     */
    private $it;
    /**
     * Values that were gathered from the iterator
     *
     * @var mixed[]
     */
    private $values = [];
    public function __construct(iterable $it)
    {
        $this->it = (
            function () use ($it): Generator {
                foreach ($it as $key => $value) {
                    yield $key => $value;
                }
            }
        )();
        if ($this->it->valid()) {
            $this->values[] = $this->current()->getIdentifier();
        }
    }
    public function current()
    {
        return $this->it->current();
    }
    public function key()
    {
        return $this->it->key();
    }
    public function rewind(): void
    {
        $this->it->rewind();
    }
    public function valid(): bool
    {
        return $this->it->valid();
    }
    public function next(): void
    {
        while ($this->valid() && in_array($this->current()->getIdentifier(), $this->values)) {
            $this->it->next();
        }
        if ($this->valid()) {
            $this->values[] = $this->current()->getIdentifier();
        }
    }
}
