<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Facade;

use Shopware\Core\Framework\Log\Package;

/**
 * The ArrayFacade acts as a wrapper around an array and allows easier manipulation of arrays inside scripts.
 * An array facade can also be accessed like a "normal" array inside twig.
 * Examples:
 * {% raw %}
 * ```twig
 * {% do array.push('test') %}
 *
 * {% do array.foo = 'bar' }
 *
 * {% do array.has('foo') }
 *
 * {% if array.foo === 'bar' %}
 *
 * {% foreach array as key => value %}
 * ```
 * {% endraw %}
 *
 * @script-service miscellaneous
 *
 * @implements \ArrayAccess<array-key, string|int|float|array|object|bool|null>
 * @implements \IteratorAggregate<array-key, string|int|float|array|object|bool|null>
 */
#[Package('core')]
class ArrayFacade implements \IteratorAggregate, \ArrayAccess, \Countable
{
    private readonly ?\Closure $closure;

    /**
     * @param array<string|int, mixed> $items
     */
    public function __construct(
        private array $items,
        ?\Closure $closure = null
    ) {
        $this->closure = $closure;
    }

    /**
     * `set()` adds a new element to the array using the given key.
     *
     * @param string|int $key The array key.
     * @param mixed $value The value that should be added.
     *
     * @example payload-cases/payload-cases.twig 5 3 Add a new element with key `test` and value 1.
     */
    public function set(string|int $key, $value): void
    {
        $this->items[$key] = $value;
        $this->update();
    }

    /**
     * `push()` adds a new value to the end of the array.
     *
     * @param mixed $value The value that should be added.
     */
    public function push($value): void
    {
        $this->items[] = $value;
        $this->update();
    }

    /**
     * `removeBy()` removes the value at the given index from the array.
     *
     * @param string|int $index The index that should be removed.
     */
    public function removeBy(string|int $index): void
    {
        unset($this->items[$index]);
        $this->update();
    }

    /**
     * `remove()` removes the given value from the array. It does nothing if the provided value does not exist in the array.
     *
     * @param mixed $value The value that should be removed.
     */
    public function remove($value): void
    {
        $index = \array_search($value, $this->items, true);

        if ($index !== false) {
            $this->removeBy($index);
            $this->update();
        }
    }

    /**
     * `reset()` removes all entries from the array.
     */
    public function reset(): void
    {
        foreach (\array_keys($this->items) as $key) {
            unset($this->items[$key]);
        }
        $this->update();
    }

    /**
     * `merge()` recursively merges the array with the given array.
     *
     * @param array<string|int, mixed>|ArrayFacade $array The array that should be merged with this array. Either a plain `array` or another `ArrayFacade`.
     *
     * @example payload-cases/payload-cases.twig 13 3 Merge two arrays.
     */
    public function merge(array|ArrayFacade $array): void
    {
        if ($array instanceof ArrayFacade) {
            $array = $array->items;
        }
        $this->items = \array_merge_recursive($this->items, $array);
        $this->update();
    }

    /**
     * `replace()` recursively replaces elements from the given array into this array.
     *
     * @param array<string|int, mixed>|ArrayFacade $array The array from which the elements should be replaced into this array. Either a plain `array` or another `ArrayFacade`.
     *
     * @example payload-cases/payload-cases.twig 17 3 Replace elements in the product payload array.
     */
    public function replace(array|ArrayFacade $array): void
    {
        if ($array instanceof ArrayFacade) {
            $array = $array->items;
        }
        $this->items = \array_replace_recursive($this->items, $array);
        $this->update();
    }

    /**
     * @internal should not be used directly, use the default twig array syntax instead
     *
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
     * @internal should not be used directly, use the default twig array syntax instead
     *
     * @param string|int $offset
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)/* :mixed */
    {
        return $this->items[$offset];
    }

    /**
     * @internal should not be used directly, use the default twig array syntax instead
     *
     * @param string|int $offset
     * @param mixed $value
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
     * @internal should not be used directly, use the default twig array syntax instead
     *
     * @param string|int $offset
     */
    public function offsetUnset($offset): void
    {
        $this->removeBy($offset);
    }

    /**
     * @internal should not be used directly, loop over an array facade directly inside twig instead
     */
    public function getIterator(): \Generator
    {
        yield from $this->items;
    }

    /**
     * `count()` returns the count of elements inside this array.
     *
     * @return int Returns the count of elements.
     */
    public function count(): int
    {
        return \count($this->items);
    }

    /**
     * `all()` function returns all elements of this array.
     *
     * @return array<string|int, mixed> Returns all elements of this array.
     */
    public function all(): array
    {
        return $this->items;
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
