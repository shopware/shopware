<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AccountOrder;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Storefront\Page\AccountOrder\CustomerOrderPageletStruct;

class AccountOrderPageletLoader
{
    /**
     * @var RepositoryInterface
     */
    private $orderRepository;

    public function __construct(RepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param AccountOrderPageletRequest $request
     * @param CheckoutContext            $context
     *
     * @throws CustomerNotLoggedInException
     *
     * @return \Shopware\Storefront\Page\AccountOrder\CustomerOrderPageletStruct
     */
    public function load(AccountOrderPageletRequest $request, CheckoutContext $context): CustomerOrderPageletStruct
    {
        $limit = $request->getLimit();
        $page = $request->getPage();

        $customer = $context->getCustomer();
        if ($customer === null) {
            throw new CustomerNotLoggedInException();
        }

        $criteria = $this->createCriteria($customer->getId(), $limit, $page);
        $orders = $this->orderRepository->search($criteria, $context->getContext());

        return new CustomerOrderPageletStruct(
            $orders,
            $criteria,
            $page,
            $this->getPageCount($orders, $criteria, $page)
        );
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
