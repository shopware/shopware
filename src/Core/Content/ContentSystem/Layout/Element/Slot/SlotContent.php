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

    public function first(): ?ContentElement
    {
        return $this->elements[0] ?? null;
    }

    public function get(int $index): ?ContentElement
    {
        $reindexed = array_values($this->elements);

        return $reindexed[$index] ?? null;
    }

    /**
     * @return array<ContentElement>
     */
    public function all(): array
    {
        return $this->elements;
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
