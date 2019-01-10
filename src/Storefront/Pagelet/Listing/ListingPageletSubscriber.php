<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ListingPageletSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            \Shopware\Storefront\Event\ListingEvents::LISTING_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(ListingPageletRequestEvent $event): void
    {
        //$listingPageletRequest = $event->getListingPageletRequest();
    }
}
