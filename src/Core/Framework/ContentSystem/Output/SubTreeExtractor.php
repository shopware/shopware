<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Output;

use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\Log\Package;

/**
 * Extracts target element with descendants from a rendered tree (post-render operation).
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class SubTreeExtractor
{
    /**
     * The found instance itself comes back, not a copy: {@see RenderedElement} is `final readonly`, so the
     * caller cannot mutate what the rest of the tree still points at.
     */
    public function extract(RenderedElement $root, string $targetId): ?RenderedElement
    {
        if ($root->id === $targetId) {
            return $root;
        }

        foreach ($root->slots as $children) {
            foreach ($children as $child) {
                $found = $this->extract($child, $targetId);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
