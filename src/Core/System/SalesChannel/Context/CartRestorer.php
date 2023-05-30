<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Event\BeforeCartMergeEvent;
use Shopware\Core\Checkout\Cart\Event\CartMergedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextRestoredEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('core')]
class CartRestorer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractSalesChannelContextFactory $factory,
        private readonly SalesChannelContextPersister $contextPersister,
        private readonly CartService $cartService,
        private readonly CartRuleLoader $cartRuleLoader,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function restore(string $customerId, SalesChannelContext $currentContext): SalesChannelContext
    {
        $customerPayload = $this->contextPersister->load(
            $currentContext->getToken(),
            $currentContext->getSalesChannel()->getId(),
            $customerId
        );

        if (empty($customerPayload) || !empty($customerPayload['permissions']) || !($customerPayload['expired'] ?? false) && $customerPayload['token'] === $currentContext->getToken()) {
            return $this->replaceContextToken($customerId, $currentContext);
        }

        $customerContext = $this->factory->create($customerPayload['token'], $currentContext->getSalesChannel()->getId(), $customerPayload);
        if ($customerPayload['expired'] ?? false) {
            $customerContext = $this->replaceContextToken($customerId, $customerContext);
        }

        if (!$customerContext->getDomainId()) {
            $customerContext->setDomainId($currentContext->getDomainId());
        }

        $guestCart = $this->cartService->getCart($currentContext->getToken(), $currentContext);
        $customerCart = $this->cartService->getCart($customerContext->getToken(), $customerContext);

        if ($guestCart->getLineItems()->count() > 0) {
            $restoredCart = $this->mergeCart($customerCart, $guestCart, $customerContext);
        } else {
            $restoredCart = $this->cartService->recalculate($customerCart, $customerContext);
        }

        $restoredCart->addErrors(...array_values($guestCart->getErrors()->getPersistent()->getElements()));

        $this->deleteGuestContext($currentContext, $customerId);

        $errors = $restoredCart->getErrors();
        $result = $this->cartRuleLoader->loadByToken($customerContext, $restoredCart->getToken());

        $cartWithErrors = $result->getCart();
        $cartWithErrors->setErrors($errors);
        $this->cartService->setCart($cartWithErrors);

        $this->eventDispatcher->dispatch(new SalesChannelContextRestoredEvent($customerContext, $currentContext));

        return $customerContext;
    }

    private function mergeCart(Cart $customerCart, Cart $guestCart, SalesChannelContext $customerContext): Cart
    {
        $mergeableLineItems = $guestCart->getLineItems()->filter(fn (LineItem $item) => ($item->getQuantity() > 0 && $item->isStackable()) || !$customerCart->has($item->getId()));

        $this->eventDispatcher->dispatch(new BeforeCartMergeEvent(
            $customerCart,
            $guestCart,
            $mergeableLineItems,
            $customerContext
        ));

        $errors = $customerCart->getErrors();
        $customerCart->setErrors(new ErrorCollection());

        $customerCartClone = clone $customerCart;
        $customerCart->setErrors($errors);
        $customerCartClone->setErrors($errors);

        $mergedCart = $this->cartService->add($customerCart, $mergeableLineItems->getElements(), $customerContext);

        $this->eventDispatcher->dispatch(new CartMergedEvent($mergedCart, $customerContext, $customerCartClone));

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
                'permissions' => [],
            ],
            $currentContext->getSalesChannel()->getId(),
            $customerId
        );

        return $currentContext;
    }

    private function deleteGuestContext(SalesChannelContext $guestContext, string $customerId): void
    {
        $this->cartService->deleteCart($guestContext);
        $this->contextPersister->delete($guestContext->getToken(), $guestContext->getSalesChannelId(), $customerId);
    }
}
