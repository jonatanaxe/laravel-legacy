<?php

namespace App\Legacy\Billing;

/**
 * The invoice line items. Written before the project used Laravel
 * Collections; still here because the invoice is serialized on the send queue.
 */
class ItemCollection implements \ArrayAccess, \Countable, \IteratorAggregate, \Serializable
{
    private $items = array();

    public function offsetExists($offset)
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->items[$offset];
    }

    public function offsetSet($offset, $value)
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset($offset)
    {
        unset($this->items[$offset]);
    }

    public function count()
    {
        return count($this->items);
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->items);
    }

    public function serialize()
    {
        return serialize($this->items);
    }

    public function unserialize($data)
    {
        $this->items = unserialize($data);
    }
}
