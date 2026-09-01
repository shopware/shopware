<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output\Struct;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Read-only projection of a rendered element for skeleton output: properties stripped, universal style carried.
 *
 * The projection is what makes the skeleton the cacheable half of the PWA pattern: it is a function of
 * structure alone, so the same layout and request produce the same skeleton whatever the data resolution
 * found, and a later data response composes onto it by element id.
 *
 * Minting goes through {@see fromRendered()} rather than the constructor, so the projection rule lives in one
 * place. The decomposed format used to share this struct through a second factory; it writes its own skeletons
 * now, so the only wire shape reaching a client from here is the skeleton format's.
 *
 * @internal
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

        // Drop the inherited Struct extension bag; nested nodes bypass the encoder that strips it at the root
        unset($data['extensions']);

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
}
