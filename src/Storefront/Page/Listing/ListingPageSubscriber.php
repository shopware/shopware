<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Storefront\Event\ListingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ListingPageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ListingEvents::LISTING_PAGE_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(ListingPageRequestEvent $event): void
    {
        //$listingPageRequest = $event->getListingPageRequest();
        //$listingPageRequest->getListingRequest()->setxxx($event->getHttpRequest()->attributes->get('xxx'));
    }
}
