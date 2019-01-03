<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SearchPageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            \Shopware\Storefront\Event\SearchEvents::SEARCH_PAGE_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(SearchPageRequestEvent $event): void
    {
        $searchPageRequest = $event->getSearchPageRequest();
    }
}
