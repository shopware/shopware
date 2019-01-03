<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Search;

use Shopware\Storefront\Event\SearchEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SearchPageletSubscriber implements EventSubscriberInterface
{
    public const SEARCH_TERM_PARAMETER = 'search';

    public const ROUTE_PARAMS_PARAMETER = '_route_params';

    public static function getSubscribedEvents(): array
    {
        return [
            SearchEvents::SEARCH_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(SearchPageletRequestEvent $event): void
    {
        $searchPageletRequest = $event->getSearchPageletRequest();
        $searchPageletRequest->setSearchTerm($event->getRequest()->get(self::SEARCH_TERM_PARAMETER, ''));
    }
}
