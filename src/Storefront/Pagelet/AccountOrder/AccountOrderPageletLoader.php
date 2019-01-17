<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountOrder;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Routing\InternalRequest;

class AccountOrderPageletLoader
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    public function __construct(EntityRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function load(InternalRequest $request, CheckoutContext $context): AccountOrderPageletStruct
    {
        $limit = (int) $request->optional('limit', 10);
        $page = (int) $request->optional('page', 1);

        $customer = $context->getCustomer();
        if ($customer === null) {
            throw new CustomerNotLoggedInException();
        }

        $criteria = $this->createCriteria($customer->getId(), $limit, $page);
        $orders = $this->orderRepository->search($criteria, $context->getContext());

        $page = new AccountOrderPageletStruct();
        $page->setOrders($orders);
        $page->setCriteria($criteria);
        $page->setCurrentPage((int) $request->optional('page', 1));
        $page->setPageCount($this->getPageCount($orders, $criteria, (int) $request->optional('page', 1)));

        return $page;
    }

    private function createCriteria(string $customerId, int $limit, int $page): Criteria
    {
        --$page;
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $customerId));
        $criteria->addSorting(new FieldSorting('order.date', FieldSorting::DESCENDING));
        $criteria->setLimit($limit);
        $criteria->setOffset($page * $limit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);

        return $criteria;
    }

    private function getPageCount(EntitySearchResult $orders, Criteria $criteria, int $currentPage): int
    {
        $pageCount = (int) floor($orders->getTotal() / $criteria->getLimit());

        if ($criteria->getTotalCountMode() !== Criteria::TOTAL_COUNT_MODE_NEXT_PAGES) {
            return max(1, $pageCount);
        }

        return $pageCount + $currentPage;
    }
}
