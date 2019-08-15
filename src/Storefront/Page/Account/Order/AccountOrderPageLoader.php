<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Order;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountOrderPageLoader
{
    /**
     * @var GenericPageLoader
     */
    private $genericLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    public function __construct(
        GenericPageLoader $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $orderRepository
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountOrderPage
    {
        if (!$salesChannelContext->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountOrderPage::createFrom($page);

        $orders = $this->orderRepository->search(
            $this->createCriteria($salesChannelContext->getCustomer()->getId(), $request),
            $salesChannelContext->getContext()
        );

        $page->setOrders(StorefrontSearchResult::createFrom($orders));

        $this->eventDispatcher->dispatch(
            new AccountOrderPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    private function createCriteria(string $customerId, Request $request): Criteria
    {
        $limit = (int) $request->query->get('limit', 10);
        $page = (int) $request->query->get('p', 1);

        return (new Criteria())
            ->addFilter(new EqualsFilter('order.orderCustomer.customerId', $customerId))
            ->addSorting(new FieldSorting('order.createdAt', FieldSorting::DESCENDING))
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('deliveries.shippingMethod')
            ->setLimit($limit)
            ->setOffset(($page - 1) * $limit)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);
    }
}
