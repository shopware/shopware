<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Storefront\Event\ListingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaginationSubscriber implements EventSubscriberInterface
{
    public const LIMIT_PARAMETER = 'limit';

    public const PAGE_PARAMETER = 'p';

    public static function getSubscribedEvents(): array
    {
        return [
            ListingEvents::CRITERIA_CREATED => 'buildCriteria',
            \Shopware\Storefront\Event\ListingEvents::LISTING_PAGELET_LOADED => 'buildPage',
            \Shopware\Storefront\Event\ListingEvents::LISTING_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(ListingPageletRequestEvent $event): void
    {
        $page = $event->getRequest()->query->getInt(self::PAGE_PARAMETER);
        if ($page <= 0) {
            $page = 1;
        }

        $listingPageletRequest = $event->getListingPageletRequest();
        $listingPageletRequest->setPage($page);
        $listingPageletRequest->setLimit(
            $event->getRequest()->query->getInt(self::LIMIT_PARAMETER, 20)
        );
    }

    public function buildCriteria(PageCriteriaCreatedEvent $event): void
    {
        $request = $event->getRequest();

        $limit = $request->getLimit();
        $page = $request->getPage();

        //pagination
        $criteria = $event->getCriteria();
        $criteria->setOffset(($page - 1) * $limit);
        $criteria->setLimit($limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);
    }

    public function buildPage(ListingPageletLoadedEvent $event): void
    {
        $page = $event->getPage();
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
