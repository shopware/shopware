<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartFactory;
use Shopware\Core\Checkout\Cart\Event\CartChangedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @final
 */
#[Package('checkout')]
class CartService implements ResetInterface
{
    /**
     * @var Cart[]
     */
    private array $cart = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCartPersister $persister,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CartCalculator $calculator,
        private readonly AbstractCartLoadRoute $loadRoute,
        private readonly AbstractCartDeleteRoute $deleteRoute,
        private readonly AbstractCartItemAddRoute $itemAddRoute,
        private readonly AbstractCartItemUpdateRoute $itemUpdateRoute,
        private readonly AbstractCartItemRemoveRoute $itemRemoveRoute,
        private readonly AbstractCartOrderRoute $orderRoute,
        private readonly CartFactory $cartFactory,
    ) {
    }

    public function setCart(Cart $cart): void
    {
        $this->cart[$cart->getToken()] = $cart;
    }

    public function createNew(string $token): Cart
    {
        $cart = $this->cartFactory->createNew($token);

        return $this->cart[$cart->getToken()] = $cart;
    }

    public function getCart(
        string $token,
        SalesChannelContext $context,
        bool $caching = true,
        bool $taxed = false
    ): Cart {
        if ($caching && isset($this->cart[$token])) {
            return $this->cart[$token];
        }

        $request = new Request();
        $request->query->set('token', $token);
        $request->query->set('taxed', $taxed);

        $cart = $this->loadRoute->load($request, $context)->getCart();

        return $this->cart[$cart->getToken()] = $cart;
    }

    /**
     * @param LineItem|LineItem[] $items
     *
     * @throws CartException
     */
    public function add(Cart $cart, LineItem|array $items, SalesChannelContext $context): Cart
    {
        if ($items instanceof LineItem) {
            $items = [$items];
        }

        $cart = $this->itemAddRoute->add(new Request(), $cart, $context, $items)->getCart();

        return $this->cart[$cart->getToken()] = $cart;
    }

    /**
     * @throws CartException
     */
    public function changeQuantity(Cart $cart, string $identifier, int $quantity, SalesChannelContext $context): Cart
    {
        return $this->update($cart, [
            [
                'id' => $identifier,
                'quantity' => $quantity,
            ],
        ], $context);
    }

    /**
     * @param array<string|int, mixed>[] $items
     *
     * @throws CartException
     */
    public function update(Cart $cart, array $items, SalesChannelContext $context): Cart
    {
        $request = new Request();
        $request->request->set('items', $items);

        $cart = $this->itemUpdateRoute->change($request, $cart, $context)->getCart();

        return $this->cart[$cart->getToken()] = $cart;
    }

    /**
     * @throws CartException
     */
    public function remove(Cart $cart, string $identifier, SalesChannelContext $context): Cart
    {
        return $this->removeItems($cart, [$identifier], $context);
    }

    /**
     * @param string[] $ids
     *
     * @throws CartException
     */
    public function removeItems(Cart $cart, array $ids, SalesChannelContext $context): Cart
    {
        $request = new Request();
        $request->request->set('ids', $ids);

        $cart = $this->itemRemoveRoute->remove($request, $cart, $context)->getCart();

        return $this->cart[$cart->getToken()] = $cart;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function order(Cart $cart, SalesChannelContext $context, RequestDataBag $data): string
    {
        $orderId = $this->orderRoute->order($cart, $context, $data)->getOrder()->getId();

        if (isset($this->cart[$cart->getToken()])) {
            unset($this->cart[$cart->getToken()]);
        }

        $cart = $this->createNew($context->getToken());

        $this->eventDispatcher->dispatch(new CartChangedEvent($cart, $context));

        return $orderId;
    }

    public function recalculate(Cart $cart, SalesChannelContext $context): Cart
    {
        $cart = $this->calculator->calculate($cart, $context);
        $this->persister->save($cart, $context);

        return $cart;
    }

    public function deleteCart(SalesChannelContext $context): void
    {
        $this->deleteRoute->delete($context);
    }

    public function reset(): void
    {
        $this->cart = [];
    }
}
