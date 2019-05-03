<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\InvalidCartException;
use Shopware\Core\Checkout\Cart\Order\Event\OrderPlacedEvent;
use Shopware\Core\Checkout\Order\Exception\DeliveryWithoutAddressException;
use Shopware\Core\Checkout\Order\Exception\EmptyCartException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\BusinessEventDispatcher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class OrderPersister implements OrderPersisterInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var OrderConverter
     */
    private $converter;

    /**
     * @var BusinessEventDispatcher
     */
    private $eventDispatcher;

    public function __construct(
        EntityRepositoryInterface $repository,
        OrderConverter $converter,
        BusinessEventDispatcher $eventDispatcher)
    {
        $this->orderRepository = $repository;
        $this->converter = $converter;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws DeliveryWithoutAddressException
     * @throws EmptyCartException
     * @throws InvalidCartException
     * @throws InconsistentCriteriaIdsException
     */
    public function persist(Cart $cart, SalesChannelContext $context): string
    {
        if ($cart->getErrors()->blockOrder()) {
            throw new InvalidCartException($cart->getErrors());
        }

        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }
        if ($cart->getLineItems()->count() <= 0) {
            throw new EmptyCartException();
        }

        $order = $this->converter->convertToOrder($cart, $context, new OrderConversionContext());
        $this->orderRepository->create([$order], $context->getContext());

        $criteria = new Criteria([$order['id']]);
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('deliveries');
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('addresses');

        $orderEntity = $this->orderRepository->search($criteria, $context->getContext())->first();
        $orderPlacedEvent = new OrderPlacedEvent($context->getContext(), $orderEntity, $context->getSalesChannel()->getId());

        $this->eventDispatcher->dispatch(OrderPlacedEvent::EVENT_NAME, $orderPlacedEvent);

        return $order['id'];
    }
}
