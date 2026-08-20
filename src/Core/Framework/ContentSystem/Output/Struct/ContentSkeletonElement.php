<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Struct;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Read-only projection of a rendered element for skeleton and decomposed output: properties stripped,
 * universal style carried.
 *
 * The projection is what makes the skeleton the cacheable half of the PWA pattern: it is a function of
 * structure alone, so the same layout and request produce the same skeleton whatever the data resolution
 * found, and a later data response composes onto it by element id.
 *
 * Minting goes through a factory rather than the constructor, so the projection rule lives in one place.
 * {@see fromRendered()} is that place; {@see fromElements()} is the pre-split path, still feeding the
 * decomposed format until its own encoder lands, and it dies with `ContentElement`.
 *
 * @final
 */
#[Package('framework')]
class ContentSkeletonElement extends Struct
{
    /**
     * @param array<string, list<self>> $slots
     */
    private function __construct(
        public string $id,
        public string $component,
        public array $slots,
        public ElementStyle $style = new ElementStyle(),
    ) {
    }

    /**
     * @param list<RenderedElement> $elements
     *
     * @return list<self>
     */
    public static function fromRendered(array $elements): array
    {
        return array_map(self::fromRenderedElement(...), $elements);
    }

    /**
     * TRANSITIONAL, deleted with `ContentElement` by the model-swap commit: only the decomposed format still
     * projects from the bridged model, and its own encoder replaces this path first.
     *
     * @param iterable<ContentElement> $elements
     *
     * @return list<self>
     */
    public static function fromElements(iterable $elements): array
    {
        $result = [];
        foreach ($elements as $element) {
            $result[] = self::fromElement($element);
        }

        return $result;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getApiAlias(): string
    {
        return 'content_skeleton_element';
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        // Re-emit style in wire shape; structural and omitted when empty, so it never serializes as an empty {} / []
        unset($data['style']);

        if (!$this->style->isEmpty()) {
            $data['style'] = $this->style->toArray();
        }

        return $data;
    }

    private static function fromRenderedElement(RenderedElement $element): self
    {
        $slots = [];
        foreach ($element->slots as $slotName => $children) {
            $slots[$slotName] = array_map(self::fromRenderedElement(...), $children);
        }

        return new self($element->id, $element->component, $slots, $element->style);
    }

    private static function fromElement(ContentElement $element): self
    {
        $slots = [];
        foreach ($element->getSlots() as $slotName => $slotContent) {
            $children = [];
            foreach ($slotContent as $child) {
                $children[] = self::fromElement($child);
            }
            $slots[$slotName] = $children;
        }

        return new self($element->getId(), $element->getComponent(), $slots, $element->getStyle());
    }
}
