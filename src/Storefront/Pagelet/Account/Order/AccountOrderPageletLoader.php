<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Account\Order;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountOrderPageletLoader implements PageLoaderInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(EntityRepositoryInterface $orderRepository, EventDispatcherInterface $eventDispatcher)
    {
        $this->orderRepository = $orderRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(Request $request, SalesChannelContext $context): StorefrontSearchResult
    {
        $customer = $context->getCustomer();
        if ($customer === null) {
            throw new CustomerNotLoggedInException();
        }

        $criteria = $this->createCriteria($customer->getId(), $request);

        $orders = $this->orderRepository->search($criteria, $context->getContext());

        $pagelet = StorefrontSearchResult::createFrom($orders);

        $this->eventDispatcher->dispatch(
            AccountOrderPageletLoadedEvent::NAME,
            new AccountOrderPageletLoadedEvent($pagelet, $context, $request)
        );

        return $pagelet;
    }

    private function createCriteria(string $customerId, Request $request): Criteria
    {
        $limit = (int) $request->query->get('limit', 10);
        $page = (int) $request->query->get('p', 1);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $customerId));
        $criteria->addSorting(new FieldSorting('order.createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit($limit);
        $criteria->setOffset(($page - 1) * $limit);

        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);

        return $criteria;
    }
}
