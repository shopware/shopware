<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Shipping;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsAnyFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Checkout\Cart\Error\ShippingMethodChangedError;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal Only to be used by the Storefront
 */
#[Package('checkout')]
class BlockedShippingMethodSwitcher
{
    public function __construct(private readonly AbstractShippingMethodRoute $shippingMethodRoute)
    {
    }

    public function switch(ErrorCollection $errors, SalesChannelContext $salesChannelContext): ShippingMethodEntity
    {
        $originalShippingMethod = $salesChannelContext->getShippingMethod();
        if (!$this->shippingMethodBlocked($errors)) {
            return $originalShippingMethod;
        }

        $shippingMethod = $this->getShippingMethodToChangeTo(
            $errors,
            $salesChannelContext,
            $this->loadShippingMethodsToChangeTo($errors, $salesChannelContext)
        );
        if ($shippingMethod === null) {
            return $originalShippingMethod;
        }

        $this->addNoticeToCart($errors, $shippingMethod);

        return $shippingMethod;
    }

    public function switchFromShippingMethods(
        ErrorCollection $errors,
        SalesChannelContext $salesChannelContext,
        ShippingMethodCollection $shippingMethods
    ): ShippingMethodEntity {
        $originalShippingMethod = $salesChannelContext->getShippingMethod();
        if (!$this->shippingMethodBlocked($errors)) {
            return $originalShippingMethod;
        }

        $shippingMethod = $this->getShippingMethodToChangeTo($errors, $salesChannelContext, $shippingMethods);
        if ($shippingMethod === null) {
            return $originalShippingMethod;
        }

        $this->addNoticeToCart($errors, $shippingMethod);

        return $shippingMethod;
    }

    private function shippingMethodBlocked(ErrorCollection $cartErrors): bool
    {
        foreach ($cartErrors as $error) {
            if ($error instanceof ShippingMethodBlockedError) {
                return true;
            }
        }

        return false;
    }

    private function loadShippingMethodsToChangeTo(ErrorCollection $errors, SalesChannelContext $salesChannelContext): ShippingMethodCollection
    {
        $request = new Request(['onlyAvailable' => true]);
        $criteria = (new Criteria([$salesChannelContext->getSalesChannel()->getShippingMethodId()]))
            ->setLimit(1);

        $shippingMethods = $this->shippingMethodRoute->load(
            $request,
            $salesChannelContext,
            $criteria,
        )->getShippingMethods();

        $defaultShippingMethod = $shippingMethods->first();
        if ($defaultShippingMethod !== null && !$this->shippingMethodIsBlocked($defaultShippingMethod, $errors)) {
            return $shippingMethods;
        }

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria = (new Criteria())
                ->addFilter(new NotEqualsAnyFilter('name', $this->getBlockedShippingMethodNames($errors)));
        } else {
            $criteria = (new Criteria())
                ->addFilter(new NotEqualsAnyFilter('id', $this->getBlockedShippingMethodIds($errors)));
        }

        return $this->shippingMethodRoute->load(
            $request,
            $salesChannelContext,
            $criteria
        )->getShippingMethods();
    }

    private function getShippingMethodToChangeTo(
        ErrorCollection $errors,
        SalesChannelContext $salesChannelContext,
        ShippingMethodCollection $shippingMethods
    ): ?ShippingMethodEntity {
        $defaultShippingMethodId = $salesChannelContext->getSalesChannel()->getShippingMethodId();
        $defaultShippingMethod = $shippingMethods->get($defaultShippingMethodId);
        if ($defaultShippingMethod !== null && !$this->shippingMethodIsBlocked($defaultShippingMethod, $errors)) {
            return $defaultShippingMethod;
        }

        foreach ($shippingMethods as $shippingMethod) {
            if (!$this->shippingMethodIsBlocked($shippingMethod, $errors)) {
                return $shippingMethod;
            }
        }

        return null;
    }

    private function shippingMethodIsBlocked(ShippingMethodEntity $shippingMethod, ErrorCollection $errors): bool
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return \in_array($shippingMethod->getName(), $this->getBlockedShippingMethodNames($errors), true);
        }

        return \in_array($shippingMethod->getId(), $this->getBlockedShippingMethodIds($errors), true);
    }

    /**
     * @return array<string, string>
     */
    private function getBlockedShippingMethodIds(ErrorCollection $errors): array
    {
        return $errors->fmap(static fn (Error $error) => $error instanceof ShippingMethodBlockedError ? $error->getShippingMethodId() : null);
    }

    /**
     * @deprecated tag:v6.8.0 - use getBlockedShippingMethodIds instead.
     *
     * @return array<string, string>
     */
    private function getBlockedShippingMethodNames(ErrorCollection $errors): array
    {
        return $errors->fmap(static fn (Error $error) => $error instanceof ShippingMethodBlockedError ? $error->getName() : null);
    }

    private function addNoticeToCart(ErrorCollection $cartErrors, ShippingMethodEntity $shippingMethod): void
    {
        $newShippingMethodName = $shippingMethod->getTranslation('name');
        if ($newShippingMethodName === null) {
            return;
        }

        foreach ($cartErrors as $error) {
            if (!$error instanceof ShippingMethodBlockedError) {
                continue;
            }

            // Exchange cart blocked warning with notice
            $cartErrors->remove($error->getId());
            $cartErrors->add(new ShippingMethodChangedError(
                oldShippingMethodId: $error->getShippingMethodId(),
                oldShippingMethodName: $error->getName(),
                newShippingMethodId: $shippingMethod->getId(),
                newShippingMethodName: $newShippingMethodName,
                reason: $error->getReason(),
            ));
        }
    }
}
