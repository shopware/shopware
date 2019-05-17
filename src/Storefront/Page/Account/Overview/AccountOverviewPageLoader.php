<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Overview;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountOverviewPageLoader
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
    private $repository;

    public function __construct(
        GenericPageLoader $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $repository
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->genericLoader = $genericLoader;
        $this->repository = $repository;
    }

    public function load(Request $request, SalesChannelContext $context): AccountOverviewPage
    {
        if (!($customer = $context->getCustomer()) instanceof CustomerEntity) {
            throw new CustomerNotLoggedInException();
        }

        $page = $this->genericLoader->load($request, $context);

        $page = AccountOverviewPage::createFrom($page);

        $orderOrNull = $this->loadNewestOrder($context, $customer);

        if ($orderOrNull instanceof OrderEntity) {
            $page->setNewestOrder($orderOrNull);
        }

        $this->eventDispatcher->dispatch(
            new AccountOverviewPageLoadedEvent($page, $context, $request),
            AccountOverviewPageLoadedEvent::NAME
        );

        return $page;
    }

    private function loadNewestOrder(SalesChannelContext $context, CustomerEntity $customer): ?OrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $customer->getId()))
            ->addSorting(new FieldSorting('orderDate', FieldSorting::DESCENDING))
            ->addAssociationPath('transactions.paymentMethod')
            ->addAssociationPath('deliveries.shippingMethod')
            ->setLimit(1)
            ->addAssociation('orderCustomer');

        /** @var OrderEntity|null $order */
        $order = $this->repository->search($criteria, $context->getContext())->first();

        if (!$order instanceof OrderEntity) {
            return null;
        }

        return $order;
    }
}
