<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Payment;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsAnyFilter;
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
            $this->loadPaymentMethodsToChangeTo($errors, $salesChannelContext)
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

    private function loadPaymentMethodsToChangeTo(ErrorCollection $errors, SalesChannelContext $salesChannelContext): PaymentMethodCollection
    {
        $request = new Request(['onlyAvailable' => true]);
        $criteria = (new Criteria([$salesChannelContext->getSalesChannel()->getPaymentMethodId()]))
            ->setLimit(1);

        $paymentMethods = $this->paymentMethodRoute->load(
            $request,
            $salesChannelContext,
            $criteria,
        )->getPaymentMethods();

        $defaultPaymentMethod = $paymentMethods->first();
        if ($defaultPaymentMethod !== null && !$this->paymentMethodIsBlocked($defaultPaymentMethod, $errors)) {
            return $paymentMethods;
        }

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria = (new Criteria())
                ->addFilter(new NotEqualsAnyFilter('name', $this->getBlockedPaymentMethodNames($errors)));
        } else {
            $criteria = (new Criteria())
                ->addFilter(new NotEqualsAnyFilter('id', $this->getBlockedPaymentMethodIds($errors)));
        }

        return $this->paymentMethodRoute->load(
            $request,
            $salesChannelContext,
            $criteria
        )->getPaymentMethods();
    }

    private function getPaymentMethodToChangeTo(
        ErrorCollection $errors,
        SalesChannelContext $salesChannelContext,
        PaymentMethodCollection $paymentMethods
    ): ?PaymentMethodEntity {
        $defaultPaymentMethodId = $salesChannelContext->getSalesChannel()->getPaymentMethodId();
        $defaultPaymentMethod = $paymentMethods->get($defaultPaymentMethodId);
        if ($defaultPaymentMethod !== null && !$this->paymentMethodIsBlocked($defaultPaymentMethod, $errors)) {
            return $defaultPaymentMethod;
        }

        foreach ($paymentMethods as $paymentMethod) {
            if (!$this->paymentMethodIsBlocked($paymentMethod, $errors)) {
                return $paymentMethod;
            }
        }

        return null;
    }

    private function paymentMethodIsBlocked(PaymentMethodEntity $paymentMethod, ErrorCollection $errors): bool
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return \in_array($paymentMethod->getName(), $this->getBlockedPaymentMethodNames($errors), true);
        }

        return \in_array($paymentMethod->getId(), $this->getBlockedPaymentMethodIds($errors), true);
    }

    /**
     * @return array<string, string>
     */
    private function getBlockedPaymentMethodIds(ErrorCollection $errors): array
    {
        return $errors->fmap(static fn (Error $error) => $error instanceof PaymentMethodBlockedError ? $error->getPaymentMethodId() : null);
    }

    /**
     * @deprecated tag:v6.8.0 - use getBlockedPaymentMethodIds instead.
     *
     * @return array<string, string>
     */
    private function getBlockedPaymentMethodNames(ErrorCollection $errors): array
    {
        return $errors->fmap(static fn (Error $error) => $error instanceof PaymentMethodBlockedError ? $error->getName() : null);
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
