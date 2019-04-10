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
use Shopware\Core\Checkout\Cart\Order\OrderPersisterInterface;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

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

    public function __construct(
        Enrichment $enrichment,
        Processor $processor,
        CartPersisterInterface $persister,
        OrderPersisterInterface $orderPersister,
        CartRuleLoader $cartRuleLoader
    ) {
        $this->processor = $processor;
        $this->persister = $persister;
        $this->orderPersister = $orderPersister;
        $this->enrichment = $enrichment;
        $this->cartRuleLoader = $cartRuleLoader;
    }

    public function setCart(Cart $cart): void
    {
        $this->cart[$cart->getToken()] = $cart;
    }

    /**
     * @throws CartTokenNotFoundException
     */
    public function createNew(string $token, string $name = self::SALES_CHANNEL): Cart
    {
        $cart = new Cart($name, $token);

        return $this->cart[$cart->getToken()] = $cart;
    }

    /**
     * @throws CartTokenNotFoundException
     */
    public function getCart(string $token, SalesChannelContext $context, string $name = self::SALES_CHANNEL, bool $caching = true): Cart
    {
        if (isset($this->cart[$token]) && $caching) {
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
     * @throws MixedLineItemTypeException
     */
    public function add(Cart $cart, LineItem $item, SalesChannelContext $context): Cart
    {
        $cart->add($item);

        return $this->calculate($cart, $context);
    }

    public function fill(Cart $cart, LineItemCollection $lineItems, SalesChannelContext $context): Cart
    {
        foreach ($lineItems as $lineItem) {
            $cart->getLineItems()->add($lineItem);
        }

        return $this->calculate($cart, $context);
    }

    /**
     * @throws LineItemNotFoundException
     * @throws LineItemNotStackableException
     * @throws InvalidQuantityException
     * @throws CartTokenNotFoundException
     */
    public function changeQuantity(Cart $cart, string $identifier, int $quantity, SalesChannelContext $context): Cart
    {
        if (!$lineItem = $cart->getLineItems()->get($identifier)) {
            throw new LineItemNotFoundException($identifier);
        }

        $lineItem->setQuantity($quantity);

        return $this->calculate($cart, $context);
    }

    /**
     * @throws LineItemNotFoundException
     * @throws LineItemNotRemovableException
     * @throws CartTokenNotFoundException
     */
    public function remove(Cart $cart, string $identifier, SalesChannelContext $context): Cart
    {
        $cart->remove($identifier);

        return $this->calculate($cart, $context);
    }

    public function order(Cart $cart, SalesChannelContext $context): string
    {
        $calculatedCart = $this->calculate($cart, $context);
        $events = $this->orderPersister->persist($calculatedCart, $context);

        $this->persister->delete($context->getToken(), $context);
        unset($this->cart[$calculatedCart->getToken()]);

        $event = $events->getEventByDefinition(OrderDefinition::class);
        $ids = $event->getIds();

        return array_shift($ids);
    }

    public function recalculate(Cart $cart, SalesChannelContext $context): Cart
    {
        return $this->calculate($cart, $context);
    }

    private function calculate(Cart $cart, SalesChannelContext $context): Cart
    {
        $behavior = new CartBehavior();

        // enrich line items with missing data, e.g products which added in the call are enriched with their prices and labels
        $cart = $this->enrichment->enrich($cart, $context, $behavior);

        // all prices are now prepared for calculation -  starts the cart calculation
        $cart = $this->processor->process($cart, $context, $behavior);

        // validate cart against the context rules
        $validated = $this->cartRuleLoader->loadByCart($context, $cart, $behavior);

        $cart = $validated->getCart();

        $this->persister->save($cart, $context);

        $this->cart[$cart->getToken()] = $cart;

        return $cart;
    }
}
