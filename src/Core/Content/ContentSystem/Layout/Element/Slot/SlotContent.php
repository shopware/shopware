<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\Slot;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @implements \IteratorAggregate<int, ContentElement>
 *
 * @internal
 */
#[Package('discovery')]
class SlotContent extends Struct implements \IteratorAggregate, \Countable
{
    /**
     * @param array<ContentElement> $elements
     */
    public function __construct(
        protected array $elements = []
    ) {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function first(): ?ContentElement
    {
        return $this->elements[0] ?? null;
    }

    public function last(): ?ContentElement
    {
        if ($this->elements === []) {
            return null;
        }

        return $this->elements[array_key_last($this->elements)];
    }

    public function get(int $index): ?ContentElement
    {
        $reindexed = array_values($this->elements);

        return $reindexed[$index] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->elements === [];
    }

    public function hasContent(): bool
    {
        return $this->elements !== [];
    }

    /**
     * @return array<ContentElement>
     */
    public function all(): array
    {
        return $this->elements;
    }

    public function add(ContentElement $element): self
    {
        $elements = $this->elements;
        $elements[] = $element;

        return new self($elements);
    }

    /**
     * @param callable(ContentElement): bool $predicate
     */
    public function filter(callable $predicate): self
    {
        $filtered = array_filter($this->elements, $predicate);

        return new self(array_values($filtered));
    }

    /**
     * @template T
     *
     * @param callable(ContentElement): T $mapper
     *
     * @return array<T>
     */
    public function map(callable $mapper): array
    {
        return array_map($mapper, $this->elements);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->elements);
    }

    public function count(): int
    {
        return \count($this->elements);
    }

    public function getApiAlias(): string
    {
        return 'content_element_slot_content';
    }
}
