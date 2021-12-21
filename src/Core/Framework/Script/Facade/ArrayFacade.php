<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Facade;

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
 * @implements \ArrayAccess<array-key, string|int|float|array|object|bool|null>
 * @implements \IteratorAggregate<array-key, string|int|float|array|object|bool|null>
 */
class ArrayFacade implements \IteratorAggregate, \ArrayAccess, \Countable
{
    private array $items;

    private ?\Closure $closure;

    public function __construct(array $items, ?\Closure $closure = null)
    {
        $this->items = $items;
        $this->closure = $closure;
    }

    /**
     * @param string|int                              $key
     * @param string|int|float|array|object|bool|null $value
     */
    public function set($key, $value): void
    {
        $this->items[$key] = $value;
        $this->update();
    }

    /**
     * @param string|int|float|array|object|bool|null $value
     */
    public function push($value): void
    {
        $this->items[] = $value;
        $this->update();
    }

    /**
     * @param string|int $index
     */
    public function removeBy($index): void
    {
        unset($this->items[$index]);
        $this->update();
    }

    /**
     * @param string|int|float|array|object|bool|null $value
     */
    public function remove($value): void
    {
        $index = \array_search($value, $this->items, true);

        if ($index !== false) {
            $this->removeBy($index);
            $this->update();
        }
    }

    public function reset(): void
    {
        foreach (\array_keys($this->items) as $key) {
            unset($this->items[$key]);
        }
        $this->update();
    }

    /**
     * @param array|ArrayFacade $array
     */
    public function merge($array): void
    {
        if ($array instanceof ArrayFacade) {
            $array = $array->items;
        }
        $this->items = \array_merge_recursive($this->items, $array);
        $this->update();
    }

    /**
     * @param array|ArrayFacade $array
     */
    public function replace($array): void
    {
        if ($array instanceof ArrayFacade) {
            $array = $array->items;
        }
        $this->items = \array_replace_recursive($this->items, $array);
        $this->update();
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

        $this->update();
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

    public function count(): int
    {
        return \count($this->items);
    }

    private function update(): void
    {
        if (!$this->closure) {
            return;
        }
        $closure = $this->closure;

        $closure($this->items);
    }
}
