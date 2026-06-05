<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Checkout\Cart\Error\PaymentMethodChangedError;
use Shopware\Storefront\Checkout\Cart\Error\ShippingMethodChangedError;
use Shopware\Storefront\Checkout\Payment\BlockedPaymentMethodSwitcher;
use Shopware\Storefront\Checkout\Shipping\BlockedShippingMethodSwitcher;
use Symfony\Component\HttpFoundation\Request;

#[Package('checkout')]
class StorefrontCartFacade
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CartService $cartService,
        private readonly BlockedShippingMethodSwitcher $blockedShippingMethodSwitcher,
        private readonly BlockedPaymentMethodSwitcher $blockedPaymentMethodSwitcher,
        private readonly AbstractContextSwitchRoute $contextSwitchRoute,
        private readonly CartCalculator $calculator,
        private readonly AbstractCartPersister $cartPersister,
        private readonly AbstractCheckoutGatewayRoute $checkoutGatewayRoute
    ) {
    }

    public function get(
        string $token,
        SalesChannelContext $originalContext,
        bool $caching = true,
        bool $taxed = false
    ): Cart {
        return $this->getCartResult($token, $originalContext, $caching, $taxed)['cart'];
    }

    public function getWithCheckoutGateway(
        Request $request,
        string $token,
        SalesChannelContext $context,
        bool $caching = true,
        bool $taxed = false
    ): StorefrontCartGatewayResult {
        $cartResult = $this->getCartResult($token, $context, $caching, $taxed);
        $cart = $cartResult['cart'];
        $context = $cartResult['context'];
        $gatewayResponse = $this->checkoutGatewayRoute->load($request, $cart, $context);

        if ($this->cartContainsBlockedMethods($gatewayResponse->getErrors())) {
            $cartResult = $this->resolveBlockedMethodsFromGatewayResponse($cart, $context, $gatewayResponse);
            $cart = $cartResult['cart'];
            $gatewayResponse->setErrors($cart->getErrors());
        }

        return new StorefrontCartGatewayResult($cart, $gatewayResponse);
    }

    /**
     * @return array{cart: Cart, context: SalesChannelContext}
     */
    private function getCartResult(
        string $token,
        SalesChannelContext $originalContext,
        bool $caching = true,
        bool $taxed = false
    ): array {
        $originalCart = $this->cartService->getCart($token, $originalContext, $caching, $taxed);
        $cartErrors = $originalCart->getErrors();
        if (!$this->cartContainsBlockedMethods($cartErrors)) {
            return ['cart' => $originalCart, 'context' => $originalContext];
        }

        // Switch shipping method if blocked
        $contextShippingMethod = $this->blockedShippingMethodSwitcher->switch($cartErrors, $originalContext);

        // Switch payment method if blocked
        $contextPaymentMethod = $this->blockedPaymentMethodSwitcher->switch($cartErrors, $originalContext);

        return $this->switchCartMethods($originalCart, $originalContext, $contextShippingMethod, $contextPaymentMethod);
    }

    /**
     * @return array{cart: Cart, context: SalesChannelContext}
     */
    private function switchCartMethods(
        Cart $originalCart,
        SalesChannelContext $originalContext,
        ShippingMethodEntity $contextShippingMethod,
        PaymentMethodEntity $contextPaymentMethod
    ): array {
        if ($contextShippingMethod->getId() === $originalContext->getShippingMethod()->getId()
            && $contextPaymentMethod->getId() === $originalContext->getPaymentMethod()->getId()
        ) {
            return ['cart' => $originalCart, 'context' => $originalContext];
        }

        $updatedContext = clone $originalContext;
        $updatedContext->assign([
            'shippingMethod' => $contextShippingMethod,
            'paymentMethod' => $contextPaymentMethod,
        ]);

        $newCart = $this->calculator->calculate($originalCart, $updatedContext);

        // Recalculated cart successfully unblocked
        if (!$this->cartContainsBlockedMethods($newCart->getErrors())) {
            $this->cartPersister->save($newCart, $updatedContext);
            $this->updateSalesChannelContext($updatedContext);

            return ['cart' => $newCart, 'context' => $updatedContext];
        }

        // Recalculated cart contains one or more blocked shipping/payment method, rollback changes
        $this->removeSwitchNotices($originalCart->getErrors());

        return ['cart' => $originalCart, 'context' => $originalContext];
    }

    /**
     * @return array{cart: Cart, context: SalesChannelContext}
     */
    private function resolveBlockedMethodsFromGatewayResponse(Cart $cart, SalesChannelContext $context, CheckoutGatewayRouteResponse $gatewayResponse): array
    {
        $cartErrors = $gatewayResponse->getErrors();

        $contextShippingMethod = $this->blockedShippingMethodSwitcher->switch(
            $cartErrors,
            $context,
            $gatewayResponse->getShippingMethods()
        );
        $contextPaymentMethod = $this->blockedPaymentMethodSwitcher->switch(
            $cartErrors,
            $context,
            $gatewayResponse->getPaymentMethods()
        );

        return $this->switchCartMethods($cart, $context, $contextShippingMethod, $contextPaymentMethod);
    }

    private function cartContainsBlockedMethods(ErrorCollection $errors): bool
    {
        foreach ($errors as $error) {
            if ($error instanceof ShippingMethodBlockedError || $error instanceof PaymentMethodBlockedError) {
                return true;
            }
        }

        return false;
    }

    private function updateSalesChannelContext(SalesChannelContext $salesChannelContext): void
    {
        $this->contextSwitchRoute->switchContext(
            new RequestDataBag([
                SalesChannelContextService::SHIPPING_METHOD_ID => $salesChannelContext->getShippingMethod()->getId(),
                SalesChannelContextService::PAYMENT_METHOD_ID => $salesChannelContext->getPaymentMethod()->getId(),
            ]),
            $salesChannelContext
        );
    }

    /**
     * Remove all PaymentMethodChangedErrors and ShippingMethodChangedErrors from cart
     */
    private function removeSwitchNotices(ErrorCollection $cartErrors): void
    {
        foreach ($cartErrors as $error) {
            if (!$error instanceof ShippingMethodChangedError && !$error instanceof PaymentMethodChangedError) {
                continue;
            }

            if ($error instanceof ShippingMethodChangedError) {
                $cartErrors->add(new ShippingMethodBlockedError($error->getOldShippingMethodName()));
            }

            if ($error instanceof PaymentMethodChangedError) {
                $cartErrors->add(new PaymentMethodBlockedError($error->getOldPaymentMethodName()));
            }

            $cartErrors->remove($error->getId());
        }
    }
}
