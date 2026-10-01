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
        $originalCart = $this->cartService->getCart($token, $originalContext, $caching, $taxed);
        $cartErrors = $originalCart->getErrors();
        if (!$this->cartContainsBlockedMethods($cartErrors)) {
            return $originalCart;
        }

        // Switch shipping method if blocked
        $contextShippingMethod = $this->blockedShippingMethodSwitcher->switch($cartErrors, $originalContext);

        // Switch payment method if blocked
        $contextPaymentMethod = $this->blockedPaymentMethodSwitcher->switch($cartErrors, $originalContext);

        return $this->switchCartMethods($originalCart, $originalContext, $contextShippingMethod, $contextPaymentMethod);
    }

    public function getWithCheckoutGateway(
        Request $request,
        string $token,
        SalesChannelContext $context,
        bool $caching = true,
        bool $taxed = false
    ): StorefrontCartGatewayResult {
        $cart = $this->get($token, $context, $caching, $taxed);
        $gatewayResponse = $this->checkoutGatewayRoute->load($request, $cart, $context);

        if ($this->cartContainsBlockedMethods($gatewayResponse->getErrors())) {
            $cart = $this->resolveBlockedMethodsFromGatewayResponse($cart, $context, $gatewayResponse);
            $gatewayResponse->setErrors($cart->getErrors());
        }

        return new StorefrontCartGatewayResult($cart, $gatewayResponse);
    }

    private function switchCartMethods(
        Cart $originalCart,
        SalesChannelContext $originalContext,
        ShippingMethodEntity $contextShippingMethod,
        PaymentMethodEntity $contextPaymentMethod
    ): Cart {
        if ($contextShippingMethod->getId() === $originalContext->getShippingMethod()->getId()
            && $contextPaymentMethod->getId() === $originalContext->getPaymentMethod()->getId()
        ) {
            return $originalCart;
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
            $this->updateSalesChannelContext($updatedContext, $originalContext);

            return $newCart;
        }

        // Recalculated cart contains one or more blocked shipping/payment method, rollback changes
        $this->removeSwitchNotices($originalCart->getErrors());

        return $originalCart;
    }

    private function resolveBlockedMethodsFromGatewayResponse(Cart $cart, SalesChannelContext $context, CheckoutGatewayRouteResponse $gatewayResponse): Cart
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

    private function updateSalesChannelContext(SalesChannelContext $updatedContext, SalesChannelContext $originalContext): void
    {
        $this->contextSwitchRoute->switchContext(
            new RequestDataBag([
                SalesChannelContextService::SHIPPING_METHOD_ID => $updatedContext->getShippingMethod()->getId(),
                SalesChannelContextService::PAYMENT_METHOD_ID => $updatedContext->getPaymentMethod()->getId(),
            ]),
            $updatedContext,
        );

        $originalContext->assign([
            'shippingMethod' => $updatedContext->getShippingMethod(),
            'paymentMethod' => $updatedContext->getPaymentMethod(),
        ]);

        // inherit rule changes done by CartRuleLoader
        $originalContext->setRuleIds($updatedContext->getRuleIds());
        $originalContext->setAreaRuleIds($updatedContext->getAreaRuleIds());
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
                $cartErrors->add(new ShippingMethodBlockedError(
                    id: $error->getOldShippingMethodId(),
                    name: $error->getOldShippingMethodName(),
                    reason: $error->getReason(),
                ));
            }

            if ($error instanceof PaymentMethodChangedError) {
                $cartErrors->add(new PaymentMethodBlockedError(
                    id: $error->getOldPaymentMethodId(),
                    name: $error->getOldPaymentMethodName(),
                    reason: $error->getReason(),
                ));
            }

            $cartErrors->remove($error->getId());
        }
    }
}
