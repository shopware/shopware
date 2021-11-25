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
 *
 * @phpstan
 */
class ArrayFunctions implements \IteratorAggregate, \ArrayAccess, \Countable
{
    private array $items;

    public function __construct(array &$items)
    {
        $this->items = &$items;
    }

    /**
     * @param string|int                              $key
     * @param string|int|float|array|object|bool|null $value
     */
    public function set($key, $value): void
    {
        $this->items[$key] = $value;
    }

    /**
     * @param string|int|float|array|object|bool|null $value
     */
    public function push($value): void
    {
        $this->items[] = $value;
    }

    /**
     * @param string|int $index
     */
    public function removeBy($index): void
    {
        unset($this->items[$index]);
    }

    /**
     * @param string|int|float|array|object|bool|null $value
     */
    public function remove($value): void
    {
        $index = array_search($value, $this->items, true);

        if ($index !== false) {
            $this->removeBy($index);
        }
    }

    public function reset(): void
    {
        foreach (array_keys($this->items) as $key) {
            unset($this->items[$key]);
        }
    }

    /**
     * @param array|ArrayFunctions $array
     */
    public function merge($array): void
    {
        if ($array instanceof ArrayFunctions) {
            $array = $array->items;
        }
        $this->items = array_merge_recursive($this->items, $array);
    }

    /**
     * @param array|ArrayFunctions $array
     */
    public function replace($array): void
    {
        if ($array instanceof ArrayFunctions) {
            $array = $array->items;
        }
        $this->items = array_replace_recursive($this->items, $array);
    }

    /**
     * @param string|int $offset
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)/* :bool */
    {
        return \array_key_exists($offset, $this->items);
    }

    /**
     * @param string|int $offset
     *
     * @return string|int|float|array|object|bool|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)/* :mixed */
    {
        return $this->items[$offset];
    }

    /**
     * @param string|int                              $offset
     * @param string|int|float|array|object|bool|null $value
     */
    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * @param string|int $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }

    public function getIterator(): \Generator
    {
        yield from $this->items;
    }

    public function count()
    {
        return \count($this->items);
    }
}
