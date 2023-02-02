<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

/**
 * @template TElement
 */
abstract class Collection extends Struct implements \IteratorAggregate, \Countable
{
    /**
     * @var array<TElement>
     */
    protected $elements = [];

    /**
     * @param array<TElement> $elements
     */
    public function __construct(iterable $elements = [])
    {
        foreach ($elements as $key => $element) {
            $this->set($key, $element);
        }
    }

    /**
     * @param TElement $element
     */
    public function add($element): void
    {
        $this->validateType($element);

        $this->elements[] = $element;
    }

    /**
     * @param TElement $element
     */
    public function set($key, $element): void
    {
        $this->validateType($element);

        if ($key === null) {
            $this->elements[] = $element;
        } else {
            $this->elements[$key] = $element;
        }
    }

    /**
     * @param mixed|null $key
     *
     * @return TElement|null
     */
    public function get($key)
    {
        if ($this->has($key)) {
            return $this->elements[$key];
        }

        return null;
    }

    public function clear(): void
    {
        $this->elements = [];
    }

    public function count(): int
    {
        return \count($this->elements);
    }

    public function getKeys(): array
    {
        return array_keys($this->elements);
    }

    public function has($key): bool
    {
        return \array_key_exists($key, $this->elements);
    }

    public function map(\Closure $closure): array
    {
        return array_map($closure, $this->elements);
    }

    public function reduce(\Closure $closure, $initial = null)
    {
        return array_reduce($this->elements, $closure, $initial);
    }

    public function fmap(\Closure $closure): array
    {
        return array_filter($this->map($closure));
    }

    public function sort(\Closure $closure): void
    {
        uasort($this->elements, $closure);
    }

    /**
     * @return static
     */
    public function filterInstance(string $class)
    {
        return $this->filter(static function ($item) use ($class) {
            return $item instanceof $class;
        });
    }

    /**
     * @return static
     */
    public function filter(\Closure $closure)
    {
        return $this->createNew(array_filter($this->elements, $closure));
    }

    /**
     * @return static
     */
    public function slice(int $offset, ?int $length = null)
    {
        return $this->createNew(\array_slice($this->elements, $offset, $length, true));
    }

    /**
     * @return array<TElement>
     */
    public function getElements(): array
    {
        return $this->elements;
    }

    /**
     * @return list<TElement>
     */
    public function jsonSerialize(): array
    {
        return array_values($this->elements);
    }

    /**
     * @return ($this->elements is non-empty-array ? TElement : null)
     */
    public function first()
    {
        return array_values($this->elements)[0] ?? null;
    }

    /**
     * @return TElement|null
     */
    public function getAt(int $position)
    {
        return array_values($this->elements)[$position] ?? null;
    }

    /**
     * @return ($this->elements is non-empty-array ? TElement : null)
     */
    public function last()
    {
        return array_values($this->elements)[\count($this->elements) - 1] ?? null;
    }

    /**
     * @param int|string $key
     */
    public function remove($key): void
    {
        unset($this->elements[$key]);
    }

    /**
     * @deprecated tag:v6.5.0 - reason:return-type-change - Return type will be changed to \Traversable
     *
     * @return \Generator<TElement>
     */
    #[\ReturnTypeWillChange]
    public function getIterator(): \Generator/* :\Traversable */
    {
        yield from $this->elements;
    }

    /**
     * @return class-string<TElement>|null
     */
    protected function getExpectedClass(): ?string
    {
        return null;
    }

    /**
     * @return static
     */
    protected function createNew(iterable $elements = [])
    {
        return new static($elements);
    }

    protected function validateType($element): void
    {
        $expectedClass = $this->getExpectedClass();
        if ($expectedClass === null) {
            return;
        }

        if (!$element instanceof $expectedClass) {
            $elementClass = \get_class($element);

            throw new \InvalidArgumentException(
                sprintf('Expected collection element of type %s got %s', $expectedClass, $elementClass)
            );
        }
    }
}
