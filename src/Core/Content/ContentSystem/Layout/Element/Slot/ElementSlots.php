<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\Slot;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @implements \IteratorAggregate<string, SlotContent>
 *
 * @internal
 */
#[Package('discovery')]
class ElementSlots extends Struct implements \IteratorAggregate, \Countable
{
    /**
     * @param array<string, SlotContent> $slots Indexed by slot name
     */
    public function __construct(
        protected array $slots = []
    ) {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function get(string $slotName): SlotContent
    {
        return $this->slots[$slotName] ?? SlotContent::empty();
    }

    public function has(string $slotName): bool
    {
        return isset($this->slots[$slotName]) && $this->slots[$slotName]->hasContent();
    }

    public function isEmpty(): bool
    {
        return empty($this->slots);
    }

    /**
     * @return array<string>
     */
    public function slotNames(): array
    {
        return array_keys($this->slots);
    }

    public function add(string $slotName, ContentElement $element): self
    {
        $slots = $this->slots;
        $currentContent = $slots[$slotName] ?? SlotContent::empty();
        $slots[$slotName] = $currentContent->add($element);

        return new self($slots);
    }

    public function set(string $slotName, SlotContent $content): self
    {
        $slots = $this->slots;
        $slots[$slotName] = $content;

        return new self($slots);
    }

    /**
     * @return \Generator<ContentElement>
     */
    public function allElements(): \Generator
    {
        foreach ($this->slots as $slotContent) {
            foreach ($slotContent as $element) {
                yield $element;
            }
        }
    }

    /**
     * @return \Traversable<string, SlotContent>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->slots);
    }

    public function count(): int
    {
        return \count($this->slots);
    }

    /**
     * Custom JSON serialization to flatten the slots structure.
     *
     * Instead of serializing to {"slots": {...}, "apiAlias": "..."},
     * this spreads the slots array at the top level to avoid double nesting
     * when ElementSlots is used as a property named "slots" in parent objects.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            ...$this->slots,
            'apiAlias' => $this->getApiAlias(),
        ];
    }

    public function getApiAlias(): string
    {
        return 'content_element_slots';
    }
}
