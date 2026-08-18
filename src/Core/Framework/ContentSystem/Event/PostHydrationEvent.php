<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Event;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Dispatched after content hydration to allow layout finalization.
 *
 * Listeners see the finished tree: hydration has run (FULL mode) and `ContentPipeline` has already
 * applied its finishing steps (virtual-root unwrap, partial extract). They can transform the
 * hydrated elements before response serialization.
 *
 * Core claims no priority band on this event: the pipeline owns its steps directly, so a listener
 * at any priority runs after all of them.
 *
 * @final
 */
#[Package('framework')]
class PostHydrationEvent implements ShopwareSalesChannelEvent
{
    /**
     * @param list<ContentElement> $elements
     */
    public function __construct(
        public array $elements,
        public readonly LayoutReference $layout,
        public readonly RenderingSpecification $specification,
        public readonly RenderingMode $mode,
        public readonly SalesChannelContext $salesChannelContext,
        public readonly RenderingCacheContext $cacheContext,
    ) {
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
