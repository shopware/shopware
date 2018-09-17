<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

abstract class Collection extends Struct implements \Countable, \ArrayAccess, \Iterator
{
    /**
     * @var array
     */
    protected $elements = [];

    protected $_pointer;

    /**
     * @param array $elements
     */
    public function __construct(array $elements = [])
    {
        $this->fill($elements);
        $this->_pointer = 0;
    }

    public function fill(array $elements): void
    {
        array_map([$this, 'add'], $elements);
    }

    public function clear()
    {
        $this->elements = [];
        $this->_pointer = 0;
    }

    public function count(): int
    {
        return count($this->elements);
    }

    public function getKeys(): array
    {
        return array_keys($this->elements);
    }

    public function has($key): bool
    {
        return array_key_exists($key, $this->elements);
    }

    public function map(\Closure $closure): array
    {
        return array_map($closure, $this->elements);
    }

    public function fmap(\Closure $closure): array
    {
        return array_filter($this->map($closure));
    }

    public function sort(\Closure $closure)
    {
        uasort($this->elements, $closure);
    }

    /**
     * @param string $class
     *
     * @return Collection
     */
    public function filterInstance(string $class)
    {
        return $this->filter(function ($item) use ($class) {
            return $item instanceof $class;
        });
    }

    public function filter(\Closure $closure)
    {
        return new static(array_filter($this->elements, $closure));
    }

    public function slice(int $offset, ?int $length = null)
    {
        return new static(array_slice($this->elements, $offset, $length, true));
    }

    public function getElements(): array
    {
        return $this->elements;
    }

    public function jsonSerialize(): array
    {
        $data = get_object_vars($this);
        $data['elements'] = array_values($this->elements);
        $data['_class'] = get_class($this);

        return $data;
    }

    public function first()
    {
        return array_values($this->elements)[0] ?? null;
    }

    public function last()
    {
        return array_values($this->elements)[count($this->elements) - 1] ?? null;
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->elements);
    }

    public function offsetGet($offset)
    {
        return $this->elements[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->elements[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->elements[$offset]);
    }

    public function current()
    {
        $tmp = array_values($this->elements);

        return $tmp[$this->_pointer];
    }

    public function next()
    {
        ++$this->_pointer;
    }

    public function key()
    {
        return $this->_pointer;
    }

    public function valid()
    {
        $tmp = array_values($this->elements);

        return isset($tmp[$this->_pointer]);
    }

    public function rewind()
    {
        $this->_pointer = 0;
    }

    protected function doAdd($element): void
    {
        $this->elements[] = $element;
    }

    protected function doRemoveByKey($key): void
    {
        unset($this->elements[$key]);
    }

    protected function doMerge(self $collection)
    {
        return new static(array_merge($this->elements, $collection->getElements()));
    }
}
