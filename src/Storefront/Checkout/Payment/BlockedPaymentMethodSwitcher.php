<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Payment;

use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Checkout\Cart\Error\PaymentMethodChangedError;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal Only to be used by the Storefront
 */
#[Package('checkout')]
class BlockedPaymentMethodSwitcher
{
    public function __construct(private readonly AbstractPaymentMethodRoute $paymentMethodRoute)
    {
    }

    public function switch(ErrorCollection $errors, SalesChannelContext $salesChannelContext): PaymentMethodEntity
    {
        $originalPaymentMethod = $salesChannelContext->getPaymentMethod();
        if (!$this->paymentMethodBlocked($errors)) {
            return $originalPaymentMethod;
        }

        $paymentMethod = $this->getPaymentMethodToChangeTo(
            $errors,
            $salesChannelContext,
            $this->loadPaymentMethodsToChangeTo($salesChannelContext)
        );
        if ($paymentMethod === null) {
            return $originalPaymentMethod;
        }

        $this->addNoticeToCart($errors, $paymentMethod);

        return $paymentMethod;
    }

    public function switchFromPaymentMethods(
        ErrorCollection $errors,
        SalesChannelContext $salesChannelContext,
        PaymentMethodCollection $paymentMethods
    ): PaymentMethodEntity {
        $originalPaymentMethod = $salesChannelContext->getPaymentMethod();
        if (!$this->paymentMethodBlocked($errors)) {
            return $originalPaymentMethod;
        }

        $paymentMethod = $this->getPaymentMethodToChangeTo($errors, $salesChannelContext, $paymentMethods);
        if ($paymentMethod === null) {
            return $originalPaymentMethod;
        }

        $this->addNoticeToCart($errors, $paymentMethod);

        return $paymentMethod;
    }

    private function paymentMethodBlocked(ErrorCollection $errors): bool
    {
        foreach ($errors as $error) {
            if ($error instanceof PaymentMethodBlockedError) {
                return true;
            }
        }

        return false;
    }

    private function loadPaymentMethodsToChangeTo(SalesChannelContext $salesChannelContext): PaymentMethodCollection
    {
        return $this->paymentMethodRoute->load(
            new Request(['onlyAvailable' => true]),
            $salesChannelContext,
            new Criteria(),
        )->getPaymentMethods();
    }

    private function getPaymentMethodToChangeTo(
        ErrorCollection $errors,
        SalesChannelContext $salesChannelContext,
        PaymentMethodCollection $paymentMethods
    ): ?PaymentMethodEntity {
        $blocked = $this->getBlockedPaymentMethodLookup($errors);

        $defaultPaymentMethod = $paymentMethods->get($salesChannelContext->getSalesChannel()->getPaymentMethodId());
        if ($defaultPaymentMethod !== null && !$this->isBlocked($defaultPaymentMethod, $blocked)) {
            return $defaultPaymentMethod;
        }

        foreach ($paymentMethods as $paymentMethod) {
            if (!$this->isBlocked($paymentMethod, $blocked)) {
                return $paymentMethod;
            }
        }

        return null;
    }

    /**
     * @return array<string, true>
     */
    private function getBlockedPaymentMethodLookup(ErrorCollection $errors): array
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return $this->getBlockedPaymentMethodNameLookup($errors);
        }

        $lookup = [];
        foreach ($errors as $error) {
            if (!$error instanceof PaymentMethodBlockedError) {
                continue;
            }
            $id = $error->getPaymentMethodId();
            if ($id === null) {
                continue;
            }
            $lookup[$id] = true;
        }

        return $lookup;
    }

    /**
     * @param array<string, true> $blocked
     */
    private function isBlocked(PaymentMethodEntity $paymentMethod, array $blocked): bool
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return $this->isBlockedByName($paymentMethod, $blocked);
        }

        return isset($blocked[$paymentMethod->getId()]);
    }

    /**
     * @deprecated tag:v6.8.0 - remove together with the legacy branch in getBlockedPaymentMethodLookup()
     *
     * @return array<string, true>
     */
    private function getBlockedPaymentMethodNameLookup(ErrorCollection $errors): array
    {
        $lookup = [];
        foreach ($errors as $error) {
            if (!$error instanceof PaymentMethodBlockedError) {
                continue;
            }
            $lookup[$error->getName()] = true;
        }

        return $lookup;
    }

    /**
     * @deprecated tag:v6.8.0 - remove together with the legacy branch in isBlocked()
     *
     * @param array<string, true> $blocked
     */
    private function isBlockedByName(PaymentMethodEntity $paymentMethod, array $blocked): bool
    {
        $name = $paymentMethod->getName();

        return $name !== null && isset($blocked[$name]);
    }

    private function addNoticeToCart(ErrorCollection $cartErrors, PaymentMethodEntity $paymentMethod): void
    {
        $newPaymentMethodName = $paymentMethod->getTranslation('name');
        if ($newPaymentMethodName === null) {
            return;
        }

        foreach ($cartErrors as $error) {
            if (!$error instanceof PaymentMethodBlockedError) {
                continue;
            }

            // Exchange cart blocked warning with notice
            $cartErrors->remove($error->getId());
            $cartErrors->add(new PaymentMethodChangedError(
                oldPaymentMethodId: $error->getPaymentMethodId(),
                oldPaymentMethodName: $error->getName(),
                newPaymentMethodId: $paymentMethod->getId(),
                newPaymentMethodName: $newPaymentMethodName,
                reason: $error->getReason(),
            ));
        }
    }
}
