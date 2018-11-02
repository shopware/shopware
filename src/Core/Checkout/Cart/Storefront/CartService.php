<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Storefront;

use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Enrichment;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotRemoveableException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\OrderPersisterInterface;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class CartService
{
    public const CART_NAME = 'shopware';

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
     * @var Cart|null
     */
    private $cart;

    /**
     * @var Enrichment
     */
    private $enrichment;

    /**
     * @var RepositoryInterface
     */
    private $orderRepository;

    public function __construct(
        Enrichment $enrichment,
        Processor $processor,
        CartPersisterInterface $persister,
        OrderPersisterInterface $orderPersister,
        RepositoryInterface $orderRepository
    ) {
        $this->processor = $processor;
        $this->persister = $persister;
        $this->orderPersister = $orderPersister;
        $this->enrichment = $enrichment;
        $this->orderRepository = $orderRepository;
    }

    public function setCart(Cart $cart): void
    {
        $this->cart = $cart;
    }

    public function createNew(CheckoutContext $context): Cart
    {
        $this->createNewCart($context);

        return $this->getCart($context);
    }

    public function getCart(CheckoutContext $context, bool $caching = true): Cart
    {
        if ($this->cart && $caching) {
            return $this->cart;
        }

        $cart = $this->loadOrCreateCart($context);

        return $this->calculate($cart, $context);
    }

    /**
     * @throws MixedLineItemTypeException
     */
    public function add(LineItem $item, CheckoutContext $context): Cart
    {
        $cart = $this->loadOrCreateCart($context);
        $cart->add($item);

        return $this->calculate($cart, $context);
    }

    public function fill(LineItemCollection $lineItems, CheckoutContext $context): Cart
    {
        $cart = $this->loadOrCreateCart($context);
        $cart->getLineItems()->fill($lineItems->getElements());

        return $this->calculate($cart, $context);
    }

    /**
     * @throws LineItemNotFoundException
     * @throws LineItemNotStackableException
     * @throws InvalidQuantityException
     */
    public function changeQuantity(string $identifier, int $quantity, CheckoutContext $context): Cart
    {
        $cart = $this->loadOrCreateCart($context);

        if (!$lineItem = $cart->getLineItems()->get($identifier)) {
            throw new LineItemNotFoundException($identifier);
        }

        $lineItem->setQuantity($quantity);

        return $this->calculate($cart, $context);
    }

    /**
     * @throws LineItemNotFoundException
     * @throws LineItemNotRemoveableException
     */
    public function remove(string $identifier, CheckoutContext $context): Cart
    {
        $cart = $this->loadOrCreateCart($context);
        $cart->remove($identifier);

        return $this->calculate($cart, $context);
    }

    public function order(CheckoutContext $context): string
    {
        $events = $this->orderPersister->persist(
            $this->getCart($context, false),
            $context
        );

        $this->createNewCart($context);

        $event = $events->getEventByDefinition(OrderDefinition::class);
        $ids = $event->getIds();

        return array_shift($ids);
    }

    public function getOrderByDeepLinkCode(string $orderId, string $deepLinkCode, Context $context)
    {
        if ($orderId === '' || \strlen($deepLinkCode) !== 32) {
            throw new OrderNotFoundException($orderId);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $orderId));
        $criteria->addFilter(new EqualsFilter('deepLinkCode', $deepLinkCode));

        $orders = $this->orderRepository->search($criteria, $context);
        if ($orders->getTotal() === 0) {
            throw new OrderNotFoundException($orderId);
        }

        return $orders->first();
    }

    private function loadOrCreateCart(CheckoutContext $context): Cart
    {
        if ($this->cart) {
            return $this->cart;
        }

        try {
            // try to access existing cart, identified by session token
            return $this->cart = $this->persister->load(
                $context->getToken(),
                self::CART_NAME,
                $context
            );
        } catch (\Exception $e) {
            // token not found, create new cart
            return $this->cart = $this->createNewCart($context);
        }
    }

    private function calculate(Cart $cart, CheckoutContext $context): Cart
    {
        $cart = $this->enrichment->enrich($cart, $context);
        $cart = $this->processor->process($cart, $context);
        $this->save($cart, $context);

        return $this->cart = $cart;
    }

    private function save(Cart $cart, CheckoutContext $context): void
    {
        $this->persister->save($cart, $context);
        $this->cart = $cart;
    }

    private function createNewCart(CheckoutContext $context): Cart
    {
        $this->persister->delete($context->getToken(), self::CART_NAME, $context);
        $this->cart = new Cart(self::CART_NAME, $context->getToken());

        return $this->cart;
    }
}
