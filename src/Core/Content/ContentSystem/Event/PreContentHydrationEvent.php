<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Event;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\RenderingCacheContext;
use Shopware\Core\Content\ContentSystem\RenderingMode;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before content hydration to allow layout preparation.
 *
 * Replaces scaffolding and refinery processes. Subscribers can modify
 * the elements array before data loading begins.
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
 * - >= 5000: Structure (e.g. scaffolding, wrapping)
 * - >= 3000: Transform (e.g. overrides, placeholders)
 * - >= 1000: Pruning (e.g. filtering, partial render)
 *
 * @final
 */
#[Package('discovery')]
class PreContentHydrationEvent extends Event implements ShopwareEvent
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
}
