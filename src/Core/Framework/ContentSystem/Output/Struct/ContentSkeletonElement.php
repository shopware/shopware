<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Struct;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Read-only projection of ContentElement for skeleton and decomposed output.
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

        return new self($element->getId(), $element->getComponent(), $slots);
    }
}
