<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\Iterators;

use Closure;
use Generator;
use Iterator;
use IteratorAggregate;

/**
 * Factory for rewindable iterators
 * A rewindable iterator caches all values gained until all references to the RewindableIteratorFactory are lost
 * Each iterator can be independently rewound and replayed without affecting other iterators
 * The inner iterator will only be played once, which means generators or Traversables can be used
 * @template T
 */
class RewindableIteratorFactory implements IteratorAggregate
{
    /**
     * Iterator that is used
     *
     * @var Iterator
     */
    private $it;
    /**
     * Keys that were gathered from the iterator
     *
     * @var scalar[]
     */
    private $keys = [];
    /**
     * Values that were gathered from the iterator
     *
     * @var T[]
     */
    private $values = [];
    /**
     * @phan-param iterable<T> $it
     * @psalm-param iterable<T> $it
     */
    public function __construct(iterable $it)
    {
        $this->it = (
            function () use ($it): Generator {
                foreach ($it as $key => $value) {
                    yield $key => $value;
                }
            })();
    }
    /**
     * @param int $index
     * @return null|scalar
     */
    protected function keyAt(int $index)
    {
        $this->advanceTo($index);
        return $this->keys[$index] ?? null;
    }
    /**
     * @param int $index
     * @return T
     */
    protected function currentAt(int $index)
    {
        $this->advanceTo($index);
        return $this->values[$index] ?? null;
    }
    /**
     * @param int $index
     * @return bool
     */
    protected function validAt(int $index): bool
    {
        return $this->advanceTo($index);
    }
    /**
     * @param int $index
     * @return bool
     */
    private function advanceTo(int $index): bool
    {
        while (count($this->keys) <= $index) {
            if (!$this->it->valid()) {
                return false;
            }
            array_push($this->keys, $this->it->key());
            array_push($this->values, $this->it->current());
            $this->it->next();
        }
        return true;
    }
    /**
     * @phan-return Iterator<T>
     * @psalm-return Iterator<T>
     */
    public function getIterator(): Iterator
    {
        return new RewindableIterator(Closure::fromCallable([$this, "validAt"]), Closure::fromCallable([$this, "keyAt"]), Closure::fromCallable([$this, "currentAt"]));
    }
}
