<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Review;

use Shopware\Core\Content\Product\SalesChannel\Review\Event\ProductReviewsLoadedEvent as CoreProductReviewsLoadedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.6.0 - reason:remove-subscriber - will be removed without replacement, since \Shopware\Storefront\Page\Product\Review\Event\ProductReviewsLoadedEvent will be removed
 */
#[Package('storefront')]
class ProductReviewsLoadedCompatibilitySubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly EventDispatcher $eventDispatcher)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            CoreProductReviewsLoadedEvent::class => 'onCoreProductReviewsLoaded',
        ];
    }

    public function onCoreProductReviewsLoaded(CoreProductReviewsLoadedEvent $event): void
    {
        $this->eventDispatcher->dispatch(new ProductReviewsLoadedEvent(
            StorefrontSearchResult::createFrom($event->getSearchResult()),
            $event->getSalesChannelContext(),
            $event->getRequest()
        ));
    }
}
