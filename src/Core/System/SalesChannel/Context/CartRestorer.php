<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Event\BeforeCartMergeEvent;
use Shopware\Core\Checkout\Cart\Event\CartMergedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextRestoredEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @phpstan-import-type SalesChannelContextFactoryOptions from AbstractSalesChannelContextFactory
 */
#[Package('framework')]
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
        private readonly AbstractCartPersister $cartPersister,
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
    public function restoreByToken(string $contextToken, string $customerId, SalesChannelContext $currentContext): SalesChannelContext
    {
        $customerPayload = $this->contextPersister->load(
            $contextToken,
            $currentContext->getSalesChannelId(),
        );

        if (Feature::isActive('v6.8.0.0') || Feature::isActive('MULTI_CONTEXT_TOKENS')) {
            if (($customerPayload['token'] ?? null) !== $contextToken) {
                // Save the new token without a direct customerId link to prevent default loading of this token and it's additional payload
                // Add the customerId to the additional payload to still load the customer correctly
                $this->contextPersister->save(
                    $contextToken,
                    ['additional' => [SalesChannelContextService::CUSTOMER_ID => $customerId]],
                    $currentContext->getSalesChannelId(),
                );

                $customerPayload[SalesChannelContextService::CUSTOMER_ID] = $customerId;
            }

            return $this->createCustomerContext($contextToken, $currentContext, $customerPayload);
        }

        if ($customerPayload === [] || ($customerPayload[SalesChannelContextService::PERMISSIONS] ?? []) !== []) {
            return $this->replaceContextToken($customerId, $currentContext, $contextToken);
        }

        $customerContext = $this->factory->create($customerPayload['token'], $currentContext->getSalesChannelId(), $customerPayload);
        if ($customerPayload['expired'] ?? false) {
            $customerContext = $this->replaceContextToken($customerId, $customerContext, $contextToken);
        }

        return $this->enrichCustomerContext($customerContext, $currentContext);
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
            $currentContext->getSalesChannelId(),
            $customerId
        );

        if (Feature::isActive('v6.8.0.0') || Feature::isActive('MULTI_CONTEXT_TOKENS')) {
            $token = ($customerPayload['expired'] ?? false) ? SalesChannelContextService::getNewToken() : $currentContext->getToken();

            if (($customerPayload['token'] ?? null) !== $token) {
                // Link the new token with the customerId
                $this->contextPersister->create(
                    $token,
                    $currentContext->getSalesChannelId(),
                    $customerId,
                );
            }

            return $this->createCustomerContext($token, $currentContext, $customerPayload);
        }

        if ($customerPayload === [] || ($customerPayload[SalesChannelContextService::PERMISSIONS] ?? []) !== [] || !($customerPayload['expired'] ?? false) && $customerPayload['token'] === $currentContext->getToken()) {
            return $this->replaceContextToken($customerId, $currentContext);
        }

        $customerContext = $this->factory->create($customerPayload['token'], $currentContext->getSalesChannelId(), $customerPayload);
        if ($customerPayload['expired'] ?? false) {
            $customerContext = $this->replaceContextToken($customerId, $customerContext);
        }

        return $this->enrichCustomerContext($customerContext, $currentContext);
    }

    /**
     * @param SalesChannelContextFactoryOptions $customerPayload
     */
    private function createCustomerContext(string $contextToken, SalesChannelContext $currentContext, array $customerPayload): SalesChannelContext
    {
        // We should not expire the new token again
        $customerPayload['expired'] = false;

        $customerContext = $this->factory->create($contextToken, $currentContext->getSalesChannelId(), $customerPayload);

        // Check if the imitatingUserId has changed and persist the new value if it does
        if ($currentContext->getImitatingUserId() !== $customerContext->getImitatingUserId()) {
            $customerContext->setImitatingUserId($currentContext->getImitatingUserId());

            $this->contextPersister->save(
                $customerContext->getToken(),
                ['additional' => [SalesChannelContextService::IMITATING_USER_ID => $customerContext->getImitatingUserId()]],
                $currentContext->getSalesChannelId(),
                $customerContext->getCustomerId(),
            );
        }

        // If we already loaded the correct context we directly return it, otherwise enrich the current context with customer data
        if (($customerPayload['token'] ?? null) === $contextToken) {
            return $customerContext;
        }

        return $this->enrichCustomerContext($customerContext, $currentContext);
    }

    private function enrichCustomerContext(SalesChannelContext $customerContext, SalesChannelContext $currentContext): SalesChannelContext
    {
        if (!$customerContext->getDomainId()) {
            $customerContext->setDomainId($currentContext->getDomainId());
        }

        $guestCart = $this->cartService->getCart($currentContext->getCartToken(), $currentContext);
        $customerCart = $this->cartService->getCart($customerContext->getCartToken(), $customerContext);

        if ($guestCart->getLineItems()->count() > 0 && $guestCart->getToken() !== $customerCart->getToken()) {
            $restoredCart = $this->mergeCart($customerCart, $guestCart, $customerContext);
        } else {
            $restoredCart = $this->cartService->recalculate($customerCart, $customerContext);
        }

        $restoredCart->addErrors(...array_values($guestCart->getErrors()->getPersistent()->getElements()));

        $this->cartService->deleteCart($currentContext);

        if (!Feature::isActive('v6.8.0.0') && !Feature::isActive('MULTI_CONTEXT_TOKENS')) {
            $this->contextPersister->delete($currentContext->getToken());

            if ($currentContext->getImitatingUserId() !== $customerContext->getImitatingUserId()) {
                $customerContext->setImitatingUserId($currentContext->getImitatingUserId());
            }

            $this->updateRequestState($customerContext);
        }

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
        $mergeableLineItems = $guestCart->getLineItems()->filter(static fn (LineItem $item) => ($item->getQuantity() > 0 && $item->isStackable()) || !$customerCart->has($item->getId()));

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

    private function replaceContextToken(?string $customerId, SalesChannelContext $currentContext, ?string $newContextToken = null): SalesChannelContext
    {
        $originalToken = $newContextToken;
        if ($newContextToken === null) {
            $newContextToken = $this->contextPersister->replace($currentContext->getToken(), $currentContext);
        } else {
            // Prevent duplicate key RDBMS errors in case the new token exists and has permissions attached.
            $this->cartPersister->delete($newContextToken, $currentContext);
            $this->cartPersister->replace($currentContext->getCartToken(), $newContextToken, $currentContext);

            $currentContext->assign([
                'token' => $newContextToken,
            ]);
        }

        $this->contextPersister->create(
            $newContextToken,
            $currentContext->getSalesChannelId(),
            ($originalToken === null) ? $customerId : null,
        );

        $this->updateRequestState($currentContext);

        return $currentContext;
    }

    private function updateRequestState(SalesChannelContext $context): void
    {
        $request = $this->requestStack->getMainRequest();

        if ($request === null) {
            return;
        }

        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context->getContext());
        $request->attributes->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $context->getToken());

        // Impersonation no longer stored in session with multi context tokens
        if (!Feature::isActive('v6.8.0.0') && !Feature::isActive('MULTI_CONTEXT_TOKENS')) {
            if (!$request->hasSession()) {
                return;
            }

            $session = $request->getSession();

            if (!$context->getImitatingUserId()) {
                $session->remove(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID);
            } else {
                $session->set(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID, $context->getImitatingUserId());
            }
        }
    }
}
