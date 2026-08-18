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
 * Dispatched before content hydration to allow layout preparation.
 *
 * Listeners see the layout exactly as it was loaded, before `ContentPipeline` runs any of its
 * preparation steps (virtual-root wrap, placeholder resolution, redistribute expansion, partial
 * prune), and can modify the elements array before those steps and data loading run.
 *
 * Core claims no priority band on this event: the pipeline owns its steps directly, so a listener
 * at any priority runs before all of them.
 *
 * @final
 */
#[Package('framework')]
class PreContentHydrationEvent implements ShopwareSalesChannelEvent
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
