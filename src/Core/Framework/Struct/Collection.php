<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;

/**
 * @template TElement
 * @template TKey of array-key = array-key
 *
 * @implements \IteratorAggregate<TKey, TElement>
 */
#[Package('framework')]
abstract class Collection extends Struct implements \IteratorAggregate, \Countable
{
    /**
     * @var array<TKey, TElement>
     */
    protected array $elements = [];

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
     * @param TKey|null $key
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
     * @param TKey $key
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

    /**
     * @phpstan-impure
     */
    public function count(): int
    {
        return \count($this->elements);
    }

    /**
     * @return list<TKey>
     */
    public function getKeys(): array
    {
        return array_keys($this->elements);
    }

    /**
     * @param TKey $key
     */
    public function has($key): bool
    {
        return \array_key_exists($key, $this->elements);
    }

    /**
     * @template T
     *
     * @param \Closure(TElement): T $closure
     *
     * @return array<TKey, T>
     */
    public function map(\Closure $closure): array
    {
        return array_map($closure, $this->elements);
    }

    /**
     * @template T
     *
     * @param \Closure(T, TElement): T $closure
     * @param T $initial
     *
     * @return T
     */
    public function reduce(\Closure $closure, $initial = null)
    {
        return array_reduce($this->elements, $closure, $initial);
    }

    /**
     * @template T
     *
     * @param \Closure(TElement): (T|false|null) $closure
     *
     * @return array<TKey, T>
     */
    public function fmap(\Closure $closure): array
    {
        return array_filter($this->map($closure));
    }

    /**
     * @template T
     *
     * @param \Closure(TElement): (T|iterable<*, T|null>|null) $closure
     *
     * @return array<TKey, T>
     */
    public function flatMap(\Closure $closure): array
    {
        return \array_merge(...$this->fmap(static fn ($value) => (array) $closure($value)));
    }

    /**
     * @param \Closure(TElement, TElement): int $closure
     */
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

    /**
     * @param \Closure(TElement): bool $closure
     */
    public function filter(\Closure $closure): static
    {
        return $this->createNew(array_filter($this->elements, $closure));
    }

    public function slice(int $offset, ?int $length = null): static
    {
        return $this->createNew(\array_slice($this->elements, $offset, $length, true));
    }

    /**
     * @return array<TKey, TElement>
     */
    public function getElements(): array
    {
        return $this->elements;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_values($this->elements);
    }

    /**
     * @return TElement|null
     */
    public function first()
    {
        return array_first($this->elements);
    }

    /**
     * @template T of TElement
     *
     * @param \Closure(T): bool $closure
     *
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
        return array_last($this->elements);
    }

    /**
     * @param TKey $key
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

    public function assignRecursive(array $options): static
    {
        $baseObject = null;
        if ($expectedClass = $this->getExpectedClass()) {
            $baseObject = (new \ReflectionClass($expectedClass))->newInstanceWithoutConstructor();
        }

        $hasNecessaryInterface = $baseObject instanceof AssignArrayInterface;

        foreach ($options as $value) {
            if ($hasNecessaryInterface && \is_array($value)) {
                $value = (clone $baseObject)->assignRecursive($value);
            }

            try {
                $this->add($value);
            } catch (\Throwable) {
                // Try to add, ignore if the type is not the expected one.
            }
        }

        return $this;
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
            throw FrameworkException::collectionElementInvalidType($expectedClass, $element::class);
        }
    }
}
