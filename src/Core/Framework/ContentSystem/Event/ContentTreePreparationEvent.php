<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Event;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Dispatched at the head of `ContentPipeline::load()`, over the stored forest as it was loaded.
 *
 * Listeners see raw author content: no placeholder has been resolved and no structural step has run.
 * The dispatch position is the same in both rendering modes, and every pipeline step runs after it, so
 * a listener at any priority sees the same tree — core claims no priority band.
 *
 * A listener replaces the forest rather than editing it: a stored element is immutable, so an edit
 * produces new instances that only `replaceTree()` can put back.
 *
 * @final
 */
#[Package('framework')]
class ContentTreePreparationEvent implements ShopwareSalesChannelEvent
{
    /**
     * @param list<StoredElement> $tree
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
     * @return list<StoredElement>
     */
    public function tree(): array
    {
        return $this->tree;
    }

    /**
     * @param list<StoredElement> $tree
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
