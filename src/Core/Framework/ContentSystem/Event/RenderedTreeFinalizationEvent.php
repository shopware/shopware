<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Event;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Dispatched near the tail of `ContentPipeline::load()`, over the finished rendered forest.
 *
 * Listeners see the tree the response is built from: the render step has run and both finishing steps are
 * behind it, so the virtual root is unwrapped and a partial render is already reduced to its target subtree.
 * The dispatch position is the same in both rendering modes, and every step that shapes the tree runs
 * before it. What runs after the dispatch is the duplicate-element-id check over the forest a listener
 * handed back, and the assembly of the result; neither changes the tree. So a listener at any priority
 * sees the same tree — core claims no priority band.
 *
 * A listener's structural output must not depend on the rendering mode, which is why this event exposes no
 * mode at all. Property values are the part of a rendered element that does differ between the two: SKELETON
 * mints structure only and leaves them empty, FULL populates them. Deriving structure or style from a
 * property value therefore yields a tree that differs by mode, and that is barred — the skeleton a client
 * caches would no longer match the full render it is filled from.
 *
 * A listener replaces the forest rather than editing it: a rendered element is immutable, so an edit
 * produces new instances that only `replaceTree()` can put back.
 *
 * @final
 */
#[Package('framework')]
class RenderedTreeFinalizationEvent implements ShopwareSalesChannelEvent
{
    /**
     * @param list<RenderedElement> $tree
     */
    public function __construct(
        private array $tree,
        public readonly LayoutReference $layout,
        public readonly RenderingSpecification $specification,
        public readonly SalesChannelContext $salesChannelContext,
        public readonly RenderingCacheContext $cacheContext,
    ) {
    }

    /**
     * @return list<RenderedElement>
     */
    public function tree(): array
    {
        return $this->tree;
    }

    /**
     * @param list<RenderedElement> $tree
     */
    public function replaceTree(array $tree): void
    {
        $this->tree = $tree;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
