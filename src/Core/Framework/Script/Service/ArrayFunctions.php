<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Service;

/**
 * Used for scripting:
 *
 * {% set array = array() %}
 *
 * {% do array.push('test') %}
 *
 * {% do array.foo = 'bar' }
 *
 * {% do array.has('foo') }
 *
 * {% if array.foo === 'bar' %}
 *
 * {% foreach array as key => value %}
 */
class ArrayFunctions implements \IteratorAggregate, \ArrayAccess, \Countable
{
    private array $items;

    public function __construct(array &$items)
    {
        $this->items = &$items;
    }

    public function set($key, $value): void
    {
        $this->items[$key] = $value;
    }

    public function push($value): void
    {
        $this->items[] = $value;
    }

    public function removeBy($index): void
    {
        unset($this->items[$index]);
    }

    public function remove($value): void
    {
        $index = array_search($value, $this->items, true);

        if ($index !== false){
            $this->removeBy($index);
        }
    }

    public function reset(): void
    {
        foreach (array_keys($this->items) as $key) {
            unset($this->items[$key]);
        }
    }

    public function merge($array): void
    {
        if ($array instanceof ArrayFunctions) {
            $array = $array->items;
        }
        $this->items = array_merge_recursive($this->items, $array);
    }

    public function replace($array): void
    {
        if ($array instanceof ArrayFunctions) {
            $array = $array->items;
        }
        $this->items = array_replace_recursive($this->items, $array);
    }

    #[\ReturnTypeWillChange]
    public function offsetExists($offset)/* :bool */
    {
        return array_key_exists($offset, $this->items);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)/* :mixed */
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

    public function getIterator(): \Generator
    {
        yield from $this->items;
    }

    public function count()
    {
        return count($this->items);
    }
}
