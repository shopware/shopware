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
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextRestoredEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
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
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * This function restores the context by the given token. If a context with this token doesn't exist, the context will
     * create with the customer id in the payload, but not in the main customerId table column.
     * So, the context is not directly referenced to the customer and will not be loaded, if the normal restore-function is used.
     *
     * @internal
     */
    public function restoreByToken(string $token, string $customerId, SalesChannelContext $currentContext): SalesChannelContext
    {
        $customerPayload = $this->contextPersister->load(
            $token,
            $currentContext->getSalesChannel()->getId(),
        );

        if (empty($customerPayload) || !empty($customerPayload['permissions'])) {
            return $this->replaceContextToken($customerId, $currentContext, $token);
        }

        $customerContext = $this->factory->create($customerPayload['token'], $currentContext->getSalesChannel()->getId(), $customerPayload);
        if ($customerPayload['expired'] ?? false) {
            $customerContext = $this->replaceContextToken($customerId, $customerContext, $token);
        }

        return $this->enrichCustomerContext($customerContext, $currentContext, $token, $customerId);
    }

    /**
     * This function restores the context by the given customer id. If a context with this customer id doesn't exist, the context will
     * create with the customer id in the main customerId table column.
     * So, the context is directly referenced to the customer.
     */
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

        return $this->enrichCustomerContext($customerContext, $currentContext, $currentContext->getToken(), $customerId);
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

    private function replaceContextToken(?string $customerId, SalesChannelContext $currentContext, ?string $newToken = null): SalesChannelContext
    {
        $originalToken = $newToken;
        if ($newToken === null) {
            $newToken = $this->contextPersister->replace($currentContext->getToken(), $currentContext);
        }

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
            ($originalToken === null) ? $customerId : null,
        );

        $this->updateImpersonation($currentContext);

        return $currentContext;
    }

    private function deleteGuestContext(SalesChannelContext $guestContext, string $customerId): void
    {
        $this->cartService->deleteCart($guestContext);
        $this->contextPersister->delete($guestContext->getToken(), $guestContext->getSalesChannelId(), $customerId);
    }

    private function updateImpersonation(SalesChannelContext $context): void
    {
        $request = $this->requestStack->getMainRequest();

        if (!$request?->hasSession()) {
            return;
        }

        $session = $request->getSession();

        if (!$context->getImitatingUserId()) {
            $session->remove(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID);
        } else {
            $session->set(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID, $context->getImitatingUserId());
        }
    }

    private function enrichCustomerContext(
        SalesChannelContext $customerContext,
        SalesChannelContext $currentContext,
        string $token,
        string $customerId
    ): SalesChannelContext {
        if (!$customerContext->getDomainId()) {
            $customerContext->setDomainId($currentContext->getDomainId());
        }

        $guestCart = $this->cartService->getCart($token, $currentContext);
        $customerCart = $this->cartService->getCart($customerContext->getToken(), $customerContext);

        if ($guestCart->getLineItems()->count() > 0) {
            $restoredCart = $this->mergeCart($customerCart, $guestCart, $customerContext);
        } else {
            $restoredCart = $this->cartService->recalculate($customerCart, $customerContext);
        }

        $restoredCart->addErrors(...array_values($guestCart->getErrors()->getPersistent()->getElements()));

        $this->deleteGuestContext($currentContext, $customerId);

        if ($currentContext->getImitatingUserId() !== $customerContext->getImitatingUserId()) {
            $customerContext->setImitatingUserId($currentContext->getImitatingUserId());
            $this->updateImpersonation($customerContext);
        }

        $errors = $restoredCart->getErrors();
        $result = $this->cartRuleLoader->loadByToken($customerContext, $restoredCart->getToken());

        $cartWithErrors = $result->getCart();
        $cartWithErrors->setErrors($errors);
        $this->cartService->setCart($cartWithErrors);

        $this->eventDispatcher->dispatch(new SalesChannelContextRestoredEvent($customerContext, $currentContext));

        return $customerContext;
    }
}
