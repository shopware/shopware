<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Finish;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class CheckoutFinishPageLoader implements PageLoaderInterface
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
     * @var PageWithHeaderLoader|PageLoaderInterface
     */
    private $genericLoader;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $orderRepository,
        PageLoaderInterface $genericLoader
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->orderRepository = $orderRepository;
        $this->genericLoader = $genericLoader;
    }

    public function load(Request $request, SalesChannelContext $context)
    {
        $page = $this->genericLoader->load($request, $context);

        $page = CheckoutFinishPage::createFrom($page);

        $page->setOrder($this->getOrder($request, $context));

        $this->eventDispatcher->dispatch(
            CheckoutFinishPageLoadedEvent::NAME,
            new CheckoutFinishPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function getOrder(Request $request, SalesChannelContext $context): OrderEntity
    {
        $customer = $context->getCustomer();
        if ($customer === null) {
            throw new CustomerNotLoggedInException();
        }

        $orderId = $request->get('orderId');
        if (!$orderId) {
            throw new MissingRequestParameterException('orderId', '/orderId');
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $customer->getId()));
        $criteria->addFilter(new EqualsFilter('order.id', $orderId));
        $criteria->addAssociationPath('lineItems.cover');

        try {
            $searchResult = $this->orderRepository->search($criteria, $context->getContext());
        } catch (InvalidUuidException $e) {
            throw new OrderNotFoundException($orderId);
        }

        if ($searchResult->count() !== 1) {
            throw new OrderNotFoundException($orderId);
        }

        return $searchResult->first();
    }
}
