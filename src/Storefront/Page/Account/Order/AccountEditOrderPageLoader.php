<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Order;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AccountEditOrderPageLoader
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

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $paymentMethodRepository;

    public function __construct(
        GenericPageLoader $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $orderRepository,
        SalesChannelRepositoryInterface $paymentMethodRepository
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->orderRepository = $orderRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountEditOrderPage
    {
        if (!$salesChannelContext->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountEditOrderPage::createFrom($page);

        $orders = $this->orderRepository->search(
            $this->createCriteria($salesChannelContext->getCustomer()->getId(), $request),
            $salesChannelContext->getContext()
        );

        $page->setOrder($orders->first());

        $page->setPaymentMethods($this->getPaymentMethods($salesChannelContext));

        $this->eventDispatcher->dispatch(
            new AccountEditOrderPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    private function createCriteria(string $customerId, Request $request): Criteria
    {
        return (new Criteria([$request->get('orderId')]))
            ->addFilter(new EqualsFilter('order.orderCustomer.customerId', $customerId))
            ->addAssociation('lineItems.cover')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('deliveries.shippingMethod');
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getPaymentMethods(SalesChannelContext $salesChannelContext): PaymentMethodCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addAssociation('media');

        /** @var PaymentMethodCollection $paymentMethods */
        $paymentMethods = $this->paymentMethodRepository->search($criteria, $salesChannelContext)->getEntities();

        $paymentMethods->sort(function (PaymentMethodEntity $a, PaymentMethodEntity $b) {
            return $a->getPosition() <=> $b->getPosition();
        });

        return $paymentMethods->filterByActiveRules($salesChannelContext);
    }
}
