<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Event\CartMergedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextRestoredEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SalesChannelContextRestorer
{
    /**
     * @var SalesChannelContextFactory
     */
    private $factory;

    /**
     * @var SalesChannelContextPersister
     */
    private $contextPersister;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CartRuleLoader
     */
    private $cartRuleLoader;

    public function __construct(
        SalesChannelContextFactory $factory,
        SalesChannelContextPersister $contextPersister,
        CartService $cartService,
        CartRuleLoader $cartRuleLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->factory = $factory;
        $this->contextPersister = $contextPersister;
        $this->cartService = $cartService;
        $this->cartRuleLoader = $cartRuleLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function restore(string $customerId, SalesChannelContext $currentContext): SalesChannelContext
    {
        $customerPayload = $this->contextPersister->load($currentContext->getToken(), $customerId);

        if (empty($customerPayload) || $customerPayload['token'] === $currentContext->getToken()) {
            return $this->replaceContextToken($customerId, $currentContext);
        }

        $customerContext = $this->factory->create($customerPayload['token'], $currentContext->getSalesChannel()->getId(), $customerPayload);

        $guestCart = $this->cartService->getCart($currentContext->getToken(), $currentContext);
        $customerCart = $this->cartService->getCart($customerContext->getToken(), $customerContext);

        if ($guestCart->getLineItems()->count() > 0) {
            $restoredCart = $this->mergeCart($customerCart, $guestCart, $customerContext);
        } else {
            $restoredCart = $this->cartService->recalculate($customerCart, $customerContext);
        }

        $restoredCart->addErrors(...array_values($guestCart->getErrors()->getPersistent()->getElements()));

        $this->deleteGuestContext($currentContext);

        $errors = $restoredCart->getErrors();
        $result = $this->cartRuleLoader->loadByToken($customerContext, $restoredCart->getToken());

        $cartWithErrors = $result->getCart();
        $cartWithErrors->setErrors($errors);
        $this->cartService->setCart($cartWithErrors);

        $this->eventDispatcher->dispatch(new SalesChannelContextRestoredEvent($customerContext));

        return $customerContext;
    }

    private function mergeCart(Cart $customerCart, Cart $guestCart, SalesChannelContext $customerContext): Cart
    {
        $mergeableLineItems = $guestCart->getLineItems()->filter(function (LineItem $item) use ($customerCart) {
            return ($item->getQuantity() > 0 && $item->isStackable()) || !$customerCart->has($item->getId());
        });

        $mergedCart = $this->cartService->add($customerCart, $mergeableLineItems->getElements(), $customerContext);

        $this->eventDispatcher->dispatch(new CartMergedEvent($mergedCart, $customerContext));

        return $mergedCart;
    }

    private function replaceContextToken(string $customerId, SalesChannelContext $currentContext): SalesChannelContext
    {
        $newToken = $this->contextPersister->replace($currentContext->getToken(), $currentContext);

        $currentContext->assign([
            'token' => $newToken,
        ]);

        $this->contextPersister->save(
            $newToken,
            [
                'customerId' => $customerId,
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            $customerId
        );

        return $currentContext;
    }

    private function deleteGuestContext(SalesChannelContext $guestContext): void
    {
        $this->cartService->deleteCart($guestContext);
        $this->contextPersister->delete($guestContext->getToken());
    }
}
