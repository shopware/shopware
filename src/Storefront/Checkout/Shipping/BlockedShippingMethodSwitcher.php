<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Shipping;

use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
            $this->loadShippingMethodsToChangeTo($salesChannelContext)
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

    private function loadShippingMethodsToChangeTo(SalesChannelContext $salesChannelContext): ShippingMethodCollection
    {
        return $this->shippingMethodRoute->load(
            new Request(['onlyAvailable' => true]),
            $salesChannelContext,
            new Criteria(),
        )->getShippingMethods();
    }

    private function getShippingMethodToChangeTo(
        ErrorCollection $errors,
        SalesChannelContext $salesChannelContext,
        ShippingMethodCollection $shippingMethods
    ): ?ShippingMethodEntity {
        $blocked = $this->getBlockedShippingMethodLookup($errors);

        $defaultShippingMethod = $shippingMethods->get($salesChannelContext->getSalesChannel()->getShippingMethodId());
        if ($defaultShippingMethod !== null && !$this->isBlocked($defaultShippingMethod, $blocked)) {
            return $defaultShippingMethod;
        }

        foreach ($shippingMethods as $shippingMethod) {
            if (!$this->isBlocked($shippingMethod, $blocked)) {
                return $shippingMethod;
            }
        }

        return null;
    }

    /**
     * @return array<string, true>
     */
    private function getBlockedShippingMethodLookup(ErrorCollection $errors): array
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return $this->getBlockedShippingMethodNameLookup($errors);
        }

        $lookup = [];
        foreach ($errors as $error) {
            if (!$error instanceof ShippingMethodBlockedError) {
                continue;
            }
            $id = $error->getShippingMethodId();
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
    private function isBlocked(ShippingMethodEntity $shippingMethod, array $blocked): bool
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return $this->isBlockedByName($shippingMethod, $blocked);
        }

        return isset($blocked[$shippingMethod->getId()]);
    }

    /**
     * @deprecated tag:v6.8.0 - remove together with the legacy branch in getBlockedShippingMethodLookup()
     *
     * @return array<string, true>
     */
    private function getBlockedShippingMethodNameLookup(ErrorCollection $errors): array
    {
        $lookup = [];
        foreach ($errors as $error) {
            if (!$error instanceof ShippingMethodBlockedError) {
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
    private function isBlockedByName(ShippingMethodEntity $shippingMethod, array $blocked): bool
    {
        $name = $shippingMethod->getName();

        return $name !== null && isset($blocked[$name]);
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
