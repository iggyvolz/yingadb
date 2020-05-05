<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\Iterators;

use Iterator;

/**
 * Iterator which reverses a given iterator
 *
 */
class ReverseIterator implements Iterator
{
    /** @var array<int, array{0:mixed, 1:mixed}> */
    private $arr = [];
    /** @var int */
    private $index = 0;
    public function __construct(iterable $it)
    {
        foreach ($it as $key => $value) {
            $this->arr[] = [$key, $value];
        }
        array_reverse($this->arr);
    }
    public function current()
    {
        return $this->arr[$this->index][1];
    }
    public function key()
    {
        return $this->arr[$this->index][0];
    }
    public function next()
    {
        $this->index++;
    }
    public function rewind()
    {
        $this->index = 0;
    }
    public function valid()
    {
        return array_key_exists($this->index, $this->arr);
    }
}
