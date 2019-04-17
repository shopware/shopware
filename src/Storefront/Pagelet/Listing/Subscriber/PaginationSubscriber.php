<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Pagelet\Listing\ListingPageletCriteriaCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaginationSubscriber implements EventSubscriberInterface
{
    public const LIMIT_PARAMETER = 'limit';

    public const PAGE_PARAMETER = 'p';

    public static function getSubscribedEvents(): array
    {
        return [
            ListingEvents::LISTING_PAGELET_CRITERIA_CREATED_EVENT => 'buildCriteria',
        ];
    }

    public function buildCriteria(ListingPageletCriteriaCreatedEvent $event): void
    {
        $request = $event->getRequest();

        $limit = $request->query->get('limit', 25);
        $page = $request->query->get('p', 1);

        //pagination
        $criteria = $event->getCriteria();
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit((int) $limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);
    }
}
