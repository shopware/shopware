<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountOrder;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

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

    /**
     * @param AccountOrderPageletRequest $request
     * @param CheckoutContext            $context
     *
     * @throws CustomerNotLoggedInException
     *
     * @return \Shopware\Storefront\Pagelet\AccountOrder\AccountOrderPageletStruct
     */
    public function load(AccountOrderPageletRequest $request, CheckoutContext $context): AccountOrderPageletStruct
    {
        $limit = $request->getLimit();
        $pageNumber = $request->getPage();

        $customer = $context->getCustomer();
        if ($customer === null) {
            throw new CustomerNotLoggedInException();
        }

        $criteria = $this->createCriteria($customer->getId(), $limit, $pageNumber);
        $orders = $this->orderRepository->search($criteria, $context->getContext());

        $page = new AccountOrderPageletStruct();
        $page->setOrders($orders);
        $page->setCriteria($criteria);
        $page->setCurrentPage($pageNumber);
        $page->setPageCount($this->getPageCount($orders, $criteria, $pageNumber));

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
