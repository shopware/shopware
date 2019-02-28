<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Finish;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Exception\InvalidUuidException;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $orderRepository
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->orderRepository = $orderRepository;
    }

    public function load(InternalRequest $request, CheckoutContext $context)
    {
        $page = new CheckoutFinishPage($context);

        $page = CheckoutFinishPage::createFrom($page);

        $page->setOrder($this->getOrder($request, $context));

        $this->eventDispatcher->dispatch(
            CheckoutFinishPageLoadedEvent::NAME,
            new CheckoutFinishPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function getOrder(InternalRequest $request, CheckoutContext $context): OrderEntity
    {
        $orderId = $request->requireGet('orderId');

        $customer = $context->getCustomer();
        if ($customer === null) {
            throw new CustomerNotLoggedInException();
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $customer->getId()));
        $criteria->addFilter(new EqualsFilter('order.id', $orderId));

        try {
            $searchResult = $this->orderRepository->search($criteria, $context->getContext());
        } catch (InvalidUuidException $e) {
            throw new OrderNotFoundException($orderId, 0, $e);
        }

        if ($searchResult->count() !== 1) {
            throw new OrderNotFoundException($orderId);
        }

        return $searchResult->first();
    }
}
