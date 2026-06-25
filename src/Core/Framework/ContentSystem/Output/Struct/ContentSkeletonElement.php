<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Struct;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Read-only projection of ContentElement for skeleton and decomposed output. Properties are stripped,
 * but the universal style rides the skeleton side, so it survives both the skeleton and decomposed formats.
 *
 * @final
 */
#[Package('framework')]
class ContentSkeletonElement extends Struct
{
    /**
     * @param array<string, list<self>> $slots
     */
    public function __construct(
        public string $id,
        public string $component,
        public array $slots,
        public ElementStyle $style = new ElementStyle(),
    ) {
    }

    /**
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
