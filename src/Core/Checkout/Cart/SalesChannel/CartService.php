<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Enrichment;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotRemovableException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Order\OrderPersisterInterface;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CartService
{
    public const SALES_CHANNEL = 'sales-channel';

    /**
     * @var Processor
     */
    private $processor;

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
     * @var Enrichment
     */
    private $enrichment;

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
        Enrichment $enrichment,
        Processor $processor,
        CartPersisterInterface $persister,
        OrderPersisterInterface $orderPersister,
        CartRuleLoader $cartRuleLoader,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderCustomerRepository,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->processor = $processor;
        $this->persister = $persister;
        $this->orderPersister = $orderPersister;
        $this->enrichment = $enrichment;
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

        return $this->cart[$cart->getToken()] = $cart;
    }

    public function getCart(
        string $token,
        SalesChannelContext $context,
        string $name = self::SALES_CHANNEL,
        bool $caching = true
    ): Cart {
        if ($caching && isset($this->cart[$token])) {
            return $this->cart[$token];
        }

        try {
            $cart = $this->persister->load($token, $context);
        } catch (CartTokenNotFoundException $e) {
            $cart = $this->createNew($token, $name);
        }

        return $this->calculate($cart, $context);
    }

    /**
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     */
    public function add(Cart $cart, LineItem $item, SalesChannelContext $context): Cart
    {
        $cart->add($item);

        return $this->calculate($cart, $context);
    }

    /**
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     */
    public function fill(Cart $cart, LineItemCollection $lineItems, SalesChannelContext $context): Cart
    {
        $cart->addLineItems($lineItems);

        return $this->calculate($cart, $context);
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

        // quantity change should force new price finding and calculation
        $lineItem->setPrice(null);
        $lineItem->setPriceDefinition(null);

        return $this->calculate($cart, $context);
    }

    /**
     * @throws LineItemNotFoundException
     * @throws LineItemNotRemovableException
     */
    public function remove(Cart $cart, string $identifier, SalesChannelContext $context): Cart
    {
        $cart->remove($identifier);

        return $this->calculate($cart, $context);
    }

    public function order(Cart $cart, SalesChannelContext $context): string
    {
        $calculatedCart = $this->calculate($cart, $context);
        $orderId = $this->orderPersister->persist($calculatedCart, $context);

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('deliveries');
        $criteria->addAssociation('transactions');
        $criteria->addAssociation('addresses');

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

        $this->eventDispatcher->dispatch(CheckoutOrderPlacedEvent::EVENT_NAME, $orderPlacedEvent);

        $this->persister->delete($context->getToken(), $context);
        unset($this->cart[$calculatedCart->getToken()]);

        return $orderId;
    }

    public function recalculate(Cart $cart, SalesChannelContext $context): Cart
    {
        return $this->calculate($cart, $context);
    }

    private function calculate(Cart $cart, SalesChannelContext $context): Cart
    {
        $behavior = new CartBehavior();

        // enrich line items with missing data,
        // e.g products which added in the call are enriched with their prices and labels
        $cart = $this->enrichment->enrich($cart, $context, $behavior);

        // all prices are now prepared for calculation -  starts the cart calculation
        $cart = $this->processor->process($cart, $context, $behavior);

        // validate cart against the context rules
        $cart = $this->cartRuleLoader->loadByCart($context, $cart, $behavior)->getCart();

        $this->persister->save($cart, $context);

        $this->cart[$cart->getToken()] = $cart;

        return $cart;
    }

    private function fetchCustomer(string $orderId, Context $context): OrderCustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addAssociation('customer');

        return $this->orderCustomerRepository
            ->search($criteria, $context)
            ->first();
    }
}
