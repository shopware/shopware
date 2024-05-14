<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @template TElement
 *
 * @implements \IteratorAggregate<array-key, TElement>
 */
#[Package('core')]
abstract class Collection extends Struct implements \IteratorAggregate, \Countable
{
    /**
     * @var array<array-key, TElement>
     */
    protected $elements = [];

    /**
     * @param iterable<TElement> $elements
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
     * @param array-key|null $key
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
     * @param array-key $key
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

    /**
     * @return list<array-key>
     */
    public function getKeys(): array
    {
        return array_keys($this->elements);
    }

    /**
     * @param array-key $key
     */
    public function has($key): bool
    {
        return \array_key_exists($key, $this->elements);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function map(\Closure $closure): array
    {
        return array_map($closure, $this->elements);
    }

    /**
     * @param  mixed|null        $initial
     *
     * @return mixed|null
     */
    public function reduce(\Closure $closure, $initial = null)
    {
        return array_reduce($this->elements, $closure, $initial);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function fmap(\Closure $closure): array
    {
        return array_filter($this->map($closure));
    }

    public function sort(\Closure $closure): void
    {
        uasort($this->elements, $closure);
    }

    /**
     * @param class-string $class
     */
    public function filterInstance(string $class): static
    {
        return $this->filter(static function ($item) use ($class) {
            return $item instanceof $class;
        });
    }

    public function filter(\Closure $closure): static
    {
        return $this->createNew(array_filter($this->elements, $closure));
    }

    public function slice(int $offset, ?int $length = null): static
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

    public function jsonSerialize(): array
    {
        return array_values($this->elements);
    }

    /**
     * @return TElement|null
     */
    public function first()
    {
        return $this->elements[array_key_first($this->elements)] ?? null;
    }

    /**
     * @return TElement|null
     */
    public function firstWhere(\Closure $closure)
    {
        foreach ($this->elements as $element) {
            if ($closure($element)) {
                return $element;
            }
        }

        return null;
    }

    /**
     * @return TElement|null
     */
    public function getAt(int $position)
    {
        return array_values($this->elements)[$position] ?? null;
    }

    /**
     * @return TElement|null
     */
    public function last()
    {
        return $this->elements[array_key_last($this->elements)] ?? null;
    }

    /**
     * @param array-key $key
     */
    public function remove($key): void
    {
        unset($this->elements[$key]);
    }

    /**
     * @return \Traversable<TElement>
     */
    public function getIterator(): \Traversable
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
     * @param iterable<TElement> $elements
     */
    protected function createNew(iterable $elements = []): static
    {
        return new static($elements);
    }

    /**
     * @param TElement $element
     */
    protected function validateType($element): void
    {
        $expectedClass = $this->getExpectedClass();
        if ($expectedClass === null) {
            return;
        }

        if (!$element instanceof $expectedClass) {
            $elementClass = $element::class;

            throw new \InvalidArgumentException(
                sprintf('Expected collection element of type %s got %s', $expectedClass, $elementClass)
            );
        }
    }
}
