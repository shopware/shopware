<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Event;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after content hydration to allow layout finalization.
 *
 * Replaces dismantling and extraction processes. Subscribers can
 * transform the hydrated elements before response serialization.
 *
 * @internal
 */
#[Package('discovery')]
class AfterContentHydrationEvent extends Event implements ShopwareEvent
{
    /**
     * @param array<ContentElement> $elements
     */
    public function __construct(
        /** @var array<ContentElement> */
        public array $elements,
        public readonly string $layoutId,
        public readonly string $layoutName,
        public readonly ?string $layoutVersionId,
        public readonly RenderingSpecification $specification,
        public readonly SalesChannelContext $salesChannelContext
    ) {
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }
}
