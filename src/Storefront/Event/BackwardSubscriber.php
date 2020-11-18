<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Core\Content\Product\Events\ProductCrossSellingIdsCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductCrossSellingStreamCriteriaEvent;
use Shopware\Storefront\Page\Product\CrossSelling\CrossSellingProductListCriteriaEvent;
use Shopware\Storefront\Page\Product\CrossSelling\CrossSellingProductStreamCriteriaEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BackwardSubscriber implements EventSubscriberInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public static function getSubscribedEvents()
    {
        return [
            ProductCrossSellingIdsCriteriaEvent::class => 'crossSellingIdEvent',
            ProductCrossSellingStreamCriteriaEvent::class => 'crossSellingStreamEvent',
        ];
    }

    /**
     * @deprecated tag:v6.4.0 - CrossSellingProductListCriteriaEvent event will be removed
     */
    public function crossSellingIdEvent(ProductCrossSellingIdsCriteriaEvent $event): void
    {
        $this->eventDispatcher->dispatch(
            new CrossSellingProductListCriteriaEvent($event->getCrossSelling(), $event->getCriteria(), $event->getSalesChannelContext())
        );
    }

    /**
     * @deprecated tag:v6.4.0 - CrossSellingProductStreamCriteriaEvent event will be removed
     */
    public function crossSellingStreamEvent(ProductCrossSellingStreamCriteriaEvent $event): void
    {
        $this->eventDispatcher->dispatch(
            new CrossSellingProductStreamCriteriaEvent($event->getCrossSelling(), $event->getCriteria(), $event->getSalesChannelContext())
        );
    }
}
