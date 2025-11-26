<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Output;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Log\Package;

/**
 * Extracts target element with descendants from hydrated content (post-hydration operation).
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class SubTreeExtractor
{
    /**
     * @return ContentElement|null Cloned sub-tree or null if element not found
     */
    public function extract(ContentElement $root, string $targetId): ?ContentElement
    {
        $target = $this->findElement($root, $targetId);

        if ($target === null) {
            return null;
        }

        // PHP's __clone creates deep copy including all descendants for Struct objects
        return clone $target;
    }

    private function findElement(ContentElement $root, string $targetId): ?ContentElement
    {
        if ($root->getId() === $targetId) {
            return $root;
        }

        foreach ($root->allSlotElements() as $child) {
            $found = $this->findElement($child, $targetId);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
