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
     * @return list<mixed>
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
     *
     * tag v6.6.0 Return type will be natively typed to `static`
     *
     * @return static
     */
    #[\ReturnTypeWillChange]
    public function filterInstance(string $class)
    {
        return $this->filter(static function ($item) use ($class) {
            return $item instanceof $class;
        });
    }

    /**
     * tag v6.6.0 Return type will be natively typed to `static`
     *
     * @return static
     */
    #[\ReturnTypeWillChange]
    public function filter(\Closure $closure)
    {
        return $this->createNew(array_filter($this->elements, $closure));
    }

    /**
     * tag v6.6.0 Return type will be natively typed to `static`
     *
     * @return static
     */
    #[\ReturnTypeWillChange]
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
     * return ($this->elements is non-empty-array ? TElement : null) does not work as return type for now.
     * Possible with PHPStan 1.9.0 see https://github.com/phpstan/phpstan/issues/7110
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
     * return ($this->elements is non-empty-array ? TElement : null) does not work as return type for now.
     * Possible with PHPStan 1.9.0 see https://github.com/phpstan/phpstan/issues/7110
     */
    public function last()
    {
        return array_values($this->elements)[\count($this->elements) - 1] ?? null;
    }

    /**
     * @param array-key $key
     */
    public function remove($key): void
    {
        unset($this->elements[$key]);
    }

    /**
     * @return \Generator<TElement>
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
     *
     * tag v6.6.0 Return type will be natively typed to `static`
     *
     * @return static
     */
    #[\ReturnTypeWillChange]
    protected function createNew(iterable $elements = [])
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
            $elementClass = \get_class($element);

            throw new \InvalidArgumentException(
                sprintf('Expected collection element of type %s got %s', $expectedClass, $elementClass)
            );
        }
    }
}
