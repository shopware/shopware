<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Event\CartCreatedEvent;
use Shopware\Core\Checkout\Cart\Event\CartDeletedEvent;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Event\LineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\LineItemQuantityChangedEvent;
use Shopware\Core\Checkout\Cart\Event\LineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotRemovableException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderPersisterInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CartService
{
    public const SALES_CHANNEL = 'sales-channel';

    /**
     * @var CartPersisterInterface
     */
    private $persister;

    /**
     * @var OrderPersisterInterface
     */
    private $orderPersister;

    /**
     * @var Cart[]
     */
    private $cart = [];

    /**
     * @var CartRuleLoader
     */
    private $cartRuleLoader;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderCustomerRepository;

    public function __construct(
        CartPersisterInterface $persister,
        OrderPersisterInterface $orderPersister,
        CartRuleLoader $cartRuleLoader,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderCustomerRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->persister = $persister;
        $this->orderPersister = $orderPersister;
        $this->cartRuleLoader = $cartRuleLoader;
        $this->orderRepository = $orderRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->orderCustomerRepository = $orderCustomerRepository;
    }

    public function setCart(Cart $cart): void
    {
        $this->cart[$cart->getToken()] = $cart;
    }

    public function createNew(string $token, string $name = self::SALES_CHANNEL): Cart
    {
        $cart = new Cart($name, $token);

        $this->eventDispatcher->dispatch(new CartCreatedEvent($cart));

        return $this->cart[$cart->getToken()] = $cart;
    }

    public function getCart(
        string $token,
        SalesChannelContext $salesChannelContext,
        string $name = self::SALES_CHANNEL,
        bool $caching = true
    ): Cart {
        if ($caching && isset($this->cart[$token])) {
            return $this->cart[$token];
        }

        try {
            $cart = $this->persister->load($token, $salesChannelContext);
        } catch (CartTokenNotFoundException $e) {
            $cart = $this->createNew($token, $name);
        }

        return $this->calculate($cart, $salesChannelContext);
    }

    /**
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     */
    public function add(Cart $cart, LineItem $item, SalesChannelContext $context): Cart
    {
        $cart->add($item);

        $cart->markModified();
        $item->markModified();

        $this->eventDispatcher->dispatch(new LineItemAddedEvent($item, $cart, $context));

        return $this->calculate($cart, $context, true);
    }

    /**
     * @throws LineItemNotFoundException
     * @throws LineItemNotStackableException
     * @throws InvalidQuantityException
     */
    public function changeQuantity(Cart $cart, string $identifier, int $quantity, SalesChannelContext $context): Cart
    {
        if (!$lineItem = $cart->getLineItems()->get($identifier)) {
            throw new LineItemNotFoundException($identifier);
        }

        $lineItem->setQuantity($quantity);
        $lineItem->markModified();

        $cart->markModified();

        $this->eventDispatcher->dispatch(new LineItemQuantityChangedEvent($lineItem, $cart, $context));

        return $this->calculate($cart, $context, true);
    }

    /**
     * @throws LineItemNotFoundException
     * @throws LineItemNotRemovableException
     */
    public function remove(Cart $cart, string $identifier, SalesChannelContext $context): Cart
    {
        $lineItem = $cart->get($identifier);

        if (!$lineItem) {
            throw new LineItemNotFoundException($identifier);
        }

        $cart->remove($identifier);
        $cart->markModified();

        $this->eventDispatcher->dispatch(new LineItemRemovedEvent($lineItem, $cart, $context));

        return $this->calculate($cart, $context, true);
    }

    /**
     * @throws InvalidOrderException
     * @throws InconsistentCriteriaIdsException
     */
    public function order(Cart $cart, SalesChannelContext $context): string
    {
        $calculatedCart = $this->calculate($cart, $context);
        $orderId = $this->orderPersister->persist($calculatedCart, $context);

        $criteria = new Criteria([$orderId]);
        $criteria
            ->addAssociation('lineItems.payload')
            ->addAssociation('deliveries.shippingCosts')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('cartPrice.calculatedTaxes')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('currency')
            ->addAssociation('addresses.country');

        /** @var OrderEntity|null $orderEntity */
        $orderEntity = $this->orderRepository->search($criteria, $context->getContext())->first();

        if (!$orderEntity) {
            throw new InvalidOrderException($orderId);
        }

        $orderEntity->setOrderCustomer(
            $this->fetchCustomer($orderEntity->getId(), $context->getContext())
        );

        $orderPlacedEvent = new CheckoutOrderPlacedEvent(
            $context->getContext(),
            $orderEntity,
            $context->getSalesChannel()->getId()
        );

        $this->eventDispatcher->dispatch($orderPlacedEvent);

        $this->persister->delete($context->getToken(), $context);
        unset($this->cart[$calculatedCart->getToken()]);

        return $orderId;
    }

    public function recalculate(Cart $cart, SalesChannelContext $context): Cart
    {
        return $this->calculate($cart, $context, true);
    }

    public function deleteCart(SalesChannelContext $context): void
    {
        $this->persister->delete($context->getToken(), $context);

        $cartDeleteEvent = new CartDeletedEvent($context);
        $this->eventDispatcher->dispatch($cartDeleteEvent);
    }

    private function calculate(Cart $cart, SalesChannelContext $context, bool $persist = false): Cart
    {
        // validate cart against the context rules
        $cart = $this->cartRuleLoader
            ->loadByCart($context, $cart, new CartBehavior($context->getPermissions()))
            ->getCart();

        if ($persist) {
            $this->persister->save($cart, $context);
        }

        $this->cart[$cart->getToken()] = $cart;

        $cart->markUnmodified();
        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            $lineItem->markUnmodified();
        }

        return $cart;
    }

    private function fetchCustomer(string $orderId, Context $context): OrderCustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addAssociation('customer');
        $criteria->addAssociation('salutation');

        return $this->orderCustomerRepository
            ->search($criteria, $context)
            ->first();
    }
}
