<?php declare(strict_types=1);

namespace Shopware\Storefront\Subscriber;

use Shopware\Core\Content\Product\Struct\ProductSearchResult;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Event\ListingPageLoadedEvent;
use Shopware\Storefront\Event\ListingPageRequestEvent;
use Shopware\Storefront\Event\PageCriteriaCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaginationSubscriber implements EventSubscriberInterface
{
    public const LIMIT_PARAMETER = 'limit';

    public const PAGE_PARAMETER = 'p';

    public static function getSubscribedEvents()
    {
        return [
            ListingEvents::CRITERIA_CREATED => 'buildCriteria',
            ListingEvents::LOADED => 'buildPage',
            ListingEvents::REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(ListingPageRequestEvent $event)
    {
        $page = $event->getRequest()->query->getInt(self::PAGE_PARAMETER);
        if ($page <= 0) {
            $page = 1;
        }
        $event->getListingPageRequest()->setPage($page);

        $event->getListingPageRequest()->setLimit(
            $event->getRequest()->query->getInt(self::LIMIT_PARAMETER, 20)
        );
    }

    public function buildCriteria(PageCriteriaCreatedEvent $event): void
    {
        $request = $event->getRequest();

        $limit = $request->getLimit();
        $page = $request->getPage();

        //pagination
        $event->getCriteria()->setOffset(($page - 1) * $limit);
        $event->getCriteria()->setLimit($limit);
        $event->getCriteria()->setFetchCount(Criteria::FETCH_COUNT_NEXT_PAGES);
    }

    public function buildPage(ListingPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $criteria = $page->getCriteria();

        $currentPage = (int) ($criteria->getOffset() + $criteria->getLimit()) / $criteria->getLimit();
        $pageCount = $this->getPageCount($page->getProducts(), $criteria, $currentPage);

        $page->setCurrentPage($currentPage);
        $page->setPageCount($pageCount);
    }

    private function getPageCount(ProductSearchResult $products, Criteria $criteria, int $currentPage): int
    {
        $pageCount = (int) floor($products->getTotal() / $criteria->getLimit());

        if ($criteria->fetchCount() !== Criteria::FETCH_COUNT_NEXT_PAGES) {
            return max(1, $pageCount);
        }

        return $pageCount + $currentPage;
    }
}
