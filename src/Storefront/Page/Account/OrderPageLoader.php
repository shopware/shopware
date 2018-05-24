<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use Shopware\Application\Context\Struct\StorefrontContext;
use Shopware\Checkout\Order\OrderRepository;
use Shopware\Checkout\Order\Struct\OrderSearchResult;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\Query\TermQuery;
use Shopware\Framework\ORM\Search\Sorting\FieldSorting;
use Symfony\Component\HttpFoundation\Request;

class OrderPageLoader
{
    const LIMIT_PARAMETER = 'limit';

    const PAGE_PARAMETER = 'page';
    /**
     * @var \Shopware\Checkout\Order\OrderRepository
     */
    private $orderRepository;

    public function __construct(OrderRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function load(Request $request, StorefrontContext $context): OrderPageStruct
    {
        $limit = $request->query->getInt(self::LIMIT_PARAMETER, 10);
        $page = $request->query->getInt(self::PAGE_PARAMETER, 1);

        $criteria = $this->createCriteria($context->getCustomer()->getId(), $limit, $page);
        $orders = $this->orderRepository->search($criteria, $context->getApplicationContext());

        return new OrderPageStruct(
            $orders,
            $criteria,
            $page,
            $this->getPageCount($orders, $criteria, $page)
        );
    }

    private function createCriteria(string $customerId, int $limit, int $page): Criteria
    {
        $page = $page - 1;
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('order.customerId', $customerId));
        $criteria->addSorting(new FieldSorting('order.date', FieldSorting::DESCENDING));
        $criteria->setLimit($limit);
        $criteria->setOffset($page * $limit);
        $criteria->setFetchCount(Criteria::FETCH_COUNT_NEXT_PAGES);

        return $criteria;
    }

    private function getPageCount(OrderSearchResult $orders, Criteria $criteria, int $currentPage): int
    {
        $pageCount = (int) floor($orders->getTotal() / $criteria->getLimit());

        if ($criteria->fetchCount() !== Criteria::FETCH_COUNT_NEXT_PAGES) {
            return max(1, $pageCount);
        }

        return $pageCount + $currentPage;
    }
}
