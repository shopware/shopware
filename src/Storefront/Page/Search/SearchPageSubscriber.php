<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Storefront\Event\SearchEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SearchPageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            SearchEvents::SEARCH_PAGE_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(SearchPageRequestEvent $event): void
    {
        //$searchPageRequest = $event->getSearchPageRequest();
        //$searchPageRequest->getSearchRequest()->setxxx($event->getHttpRequest()->attributes->get('xxx'));
    }
}
