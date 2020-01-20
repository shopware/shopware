<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Finish;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class CheckoutFinishPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var GenericPageLoader
     */
    private $genericLoader;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $orderRepository,
        GenericPageLoader $genericLoader
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->orderRepository = $orderRepository;
        $this->genericLoader = $genericLoader;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     * @throws OrderNotFoundException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): CheckoutFinishPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = CheckoutFinishPage::createFrom($page);

        $page->setOrder($this->getOrder($request, $salesChannelContext));

        $this->eventDispatcher->dispatch(
            new CheckoutFinishPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     * @throws OrderNotFoundException
     */
    private function getOrder(Request $request, SalesChannelContext $salesChannelContext): OrderEntity
    {
        $customer = $salesChannelContext->getCustomer();
        if ($customer === null) {
            throw new CustomerNotLoggedInException();
        }

        $orderId = $request->get('orderId');
        if (!$orderId) {
            throw new MissingRequestParameterException('orderId', '/orderId');
        }

        $criteria = (new Criteria([$orderId]))
            ->addFilter(new EqualsFilter('order.orderCustomer.customerId', $customer->getId()))
            ->addAssociation('lineItems.cover')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('deliveries.shippingMethod');

        try {
            $searchResult = $this->orderRepository->search($criteria, $salesChannelContext->getContext());
        } catch (InvalidUuidException $e) {
            throw new OrderNotFoundException($orderId);
        }

        /** @var OrderEntity|null $order */
        $order = $searchResult->get($orderId);

        if (!$order) {
            throw new OrderNotFoundException($orderId);
        }

        return $order;
    }
}
