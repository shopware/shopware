<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Checkout\Cart\Error\PaymentMethodChangedError;
use Shopware\Storefront\Checkout\Cart\Error\ShippingMethodChangedError;
use Shopware\Storefront\Checkout\Payment\BlockedPaymentMethodSwitcher;
use Shopware\Storefront\Checkout\Shipping\BlockedShippingMethodSwitcher;

class StorefrontCartFacade
{
    private CartService $cartService;

    private BlockedShippingMethodSwitcher $blockedShippingMethodSwitcher;

    private BlockedPaymentMethodSwitcher $blockedPaymentMethodSwitcher;

    private AbstractContextSwitchRoute $contextSwitchRoute;

    private CartCalculator $calculator;

    private CartPersisterInterface $cartPersister;

    public function __construct(
        CartService $cartService,
        BlockedShippingMethodSwitcher $blockedShippingMethodSwitcher,
        BlockedPaymentMethodSwitcher $blockedPaymentMethodSwitcher,
        AbstractContextSwitchRoute $contextSwitchRoute,
        CartCalculator $calculator,
        CartPersisterInterface $cartPersister
    ) {
        $this->cartService = $cartService;
        $this->blockedShippingMethodSwitcher = $blockedShippingMethodSwitcher;
        $this->blockedPaymentMethodSwitcher = $blockedPaymentMethodSwitcher;
        $this->contextSwitchRoute = $contextSwitchRoute;
        $this->calculator = $calculator;
        $this->cartPersister = $cartPersister;
    }

    public function get(
        string $token,
        SalesChannelContext $originalContext
    ): Cart {
        $originalCart = $this->cartService->getCart($token, $originalContext);
        $cartErrors = $originalCart->getErrors();
        if (!$this->cartContainsBlockedMethods($cartErrors)) {
            return $originalCart;
        }

        // Switch shipping method if blocked
        $contextShippingMethod = $this->blockedShippingMethodSwitcher->switch($cartErrors, $originalContext);

        // Switch payment method if blocked
        $contextPaymentMethod = $this->blockedPaymentMethodSwitcher->switch($cartErrors, $originalContext);

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
            $this->updateSalesChannelContext($updatedContext);

            return $newCart;
        }

        // Recalculated cart contains one or more blocked shipping/payment method, rollback changes
        $this->removeSwitchNotices($cartErrors);

        return $originalCart;
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

            $cartErrors->remove($error->getId());
        }
    }
}
