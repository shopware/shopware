<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Payment;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
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

        $paymentMethod = $this->getPaymentMethodToChangeTo($errors, $salesChannelContext);
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

    private function getPaymentMethodToChangeTo(ErrorCollection $errors, SalesChannelContext $salesChannelContext): ?PaymentMethodEntity
    {
        $request = new Request(['onlyAvailable' => true]);
        $criteria = (new Criteria([$salesChannelContext->getSalesChannel()->getPaymentMethodId()]))
            ->setLimit(1);

        if (Feature::isActive('v6.8.0.0')) {
            $blockedPaymentMethodIds = $errors->fmap(static fn (Error $error) => $error instanceof PaymentMethodBlockedError ? $error->getPaymentMethodId() : null);

            $defaultPaymentMethod = $this->paymentMethodRoute->load(
                $request,
                $salesChannelContext,
                $criteria,
            )->getPaymentMethods()->first();

            if ($defaultPaymentMethod !== null && !\in_array($defaultPaymentMethod->getId(), $blockedPaymentMethodIds, true)) {
                return $defaultPaymentMethod;
            }

            $criteria = (new Criteria())
                ->addFilter(new NotEqualsAnyFilter('id', $blockedPaymentMethodIds))
                ->setLimit(1);
        } else {
            $blockedPaymentMethodNames = $errors->fmap(static fn (Error $error) => $error instanceof PaymentMethodBlockedError ? $error->getName() : null);

            $defaultPaymentMethod = $this->paymentMethodRoute->load(
                $request,
                $salesChannelContext,
                $criteria,
            )->getPaymentMethods()->first();

            if ($defaultPaymentMethod !== null && !\in_array($defaultPaymentMethod->getName(), $blockedPaymentMethodNames, true)) {
                return $defaultPaymentMethod;
            }

            $criteria = (new Criteria())
                ->addFilter(new NotEqualsAnyFilter('name', $blockedPaymentMethodNames))
                ->setLimit(1);
        }

        return $this->paymentMethodRoute->load(
            $request,
            $salesChannelContext,
            $criteria
        )->getPaymentMethods()->first();
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
