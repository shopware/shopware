<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout;

use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyleNormalizer;
use Shopware\Core\Framework\Log\Package;

/**
 * The style pass over a whole stored forest: every element's style, at every depth, canonicalised through the
 * one {@see ElementStyleNormalizer}. The walk rebuilds each node it visits, so a slot child keeps its own
 * normalised style rather than its parent's, and the forest it was handed stays untouched.
 *
 * Style is the whole of it. Seeding a type's primitive defaults and reconciling an element's attribution are
 * deliberately outside this pass: both belong to the write alone, and a tree that is only being previewed or
 * diagnosed must not come back carrying values only a save may mint.
 *
 * @internal
 */
#[Package('framework')]
final class StoredTreeStyleNormalizer
{
    public function __construct(
        private readonly ElementStyleNormalizer $styleNormalizer,
    ) {
    }

    public function normalize(StoredTree $tree): StoredTree
    {
        return new StoredTree(array_map($this->normalizeElement(...), $tree->roots));
    }

    private function normalizeElement(StoredElement $element): StoredElement
    {
        $slots = [];

        foreach ($element->slots as $name => $children) {
            $slots[$name] = array_map($this->normalizeElement(...), $children);
        }

        return $element
            ->withStyle($this->styleNormalizer->normalize($element->style))
            ->withSlots($slots);
    }
}
