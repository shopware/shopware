<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Pagelet\Listing\ListingPageletLoadedEvent;
use Shopware\Storefront\Pagelet\Listing\PageCriteriaCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaginationSubscriber implements EventSubscriberInterface
{
    public const LIMIT_PARAMETER = 'limit';

    public const PAGE_PARAMETER = 'p';

    public static function getSubscribedEvents(): array
    {
        return [
            ListingEvents::CRITERIA_CREATED => 'buildCriteria',
            ListingEvents::LISTING_PAGELET_LOADED => 'buildPage',
        ];
    }

    public function buildCriteria(PageCriteriaCreatedEvent $event): void
    {
        $request = $event->getRequest();

        $limit = $request->optionalGet('limit', 25);
        $page = $request->optionalGet('page', 1);

        //pagination
        $criteria = $event->getCriteria();
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);
    }

    public function buildPage(ListingPageletLoadedEvent $event): void
    {
        $page = $event->getPage();
        if (!$page->getCriteria()) {
            return;
        }
        $criteria = $page->getCriteria();

        $currentPage = (int) (($criteria->getOffset() + $criteria->getLimit()) / $criteria->getLimit());
        $pageCount = $this->getPageCount($page->getProducts(), $criteria, $currentPage);

        $page->setCurrentPage($currentPage);
        $page->setPageCount($pageCount);
    }

    private function getPageCount(EntitySearchResult $products, Criteria $criteria, int $currentPage): int
    {
        $pageCount = (int) floor($products->getTotal() / $criteria->getLimit());

        if ($criteria->getTotalCountMode() !== Criteria::TOTAL_COUNT_MODE_NEXT_PAGES) {
            return max(1, $pageCount);
        }

        return $pageCount + $currentPage;
    }
}
