<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Event;

use Shopware\Core\Content\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\RenderingMode;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Dispatched after content hydration to allow layout finalization.
 *
 * Replaces dismantling and extraction processes. Subscribers can
 * transform the hydrated elements before response serialization.
 *
 * ## Priority Ranges
 *
 * Subscriber priorities determine execution order (higher = earlier).
 *
 * **Extensions:**
 * - >= 6000: Run BEFORE core processing
 * - < 1000 and >= 0: Run AFTER core processing
 * - < 0: Absolute last (use sparingly)
 *
 * **Core (RESERVED - do not use in extensions):**
 * - >= 5000: Restoration (e.g. unwrapping scaffolding)
 * - >= 3000: Enrichment (e.g. computed data)
 * - >= 1000: Extraction (e.g. partial render, output)
 *
 * @final
 */
#[Package('discovery')]
class PostHydrationEvent implements ShopwareSalesChannelEvent
{
    /**
     * @param list<ContentElement> $elements
     */
    public function __construct(
        public array $elements,
        public readonly string $layoutId,
        public readonly string $layoutName,
        public readonly ?string $layoutVersionId,
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
