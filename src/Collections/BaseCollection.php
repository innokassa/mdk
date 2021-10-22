<?php

namespace Innokassa\Collections;

/**
 * Базовый класс коллекций
 */
class BaseCollection implements \Countable, \ArrayAccess, \Iterator
{
    //######################################################################
    // ArrayAccess

    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->objects[] = $value;
        } else {
            $this->objects[$offset] = $value;
        }
    }

    public function offsetExists($offset): bool
    {
        return isset($this->objects[$offset]);
    }

    public function offsetUnset($offset): void
    {
        unset($this->objects[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return isset($this->objects[$offset]) ? $this->objects[$offset] : null;
    }

    //######################################################################
    // Iterator

    public function current()
    {
        return $this->objects[$this->position];
    }

    public function key()
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->objects[$this->position]);
    }

    //######################################################################
    // Countable

    public function count(): int
    {
        return count($this->objects);
    }

    //######################################################################
    // PROTECTED
    //######################################################################

    protected $objects = [];
    protected $position = 0;
}
