<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Shipping;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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

    public function switch(
        ErrorCollection $errors,
        SalesChannelContext $salesChannelContext,
        ?ShippingMethodCollection $shippingMethods = null
    ): ShippingMethodEntity {
        $originalShippingMethod = $salesChannelContext->getShippingMethod();
        if (!$this->shippingMethodBlocked($errors)) {
            return $originalShippingMethod;
        }

        $shippingMethod = $this->getShippingMethodToChangeTo(
            $errors,
            $salesChannelContext,
            $shippingMethods ?? $this->shippingMethodRoute->load(
                new Request(['onlyAvailable' => true]),
                $salesChannelContext,
                new Criteria(),
            )->getShippingMethods(),
        );
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
     * @return array<string, int>
     */
    private function getBlockedShippingMethodLookup(ErrorCollection $errors): array
    {
        return \array_flip($errors->fmap(static fn (Error $error) => $error instanceof ShippingMethodBlockedError ? $error->getName() : null));
    }

    /**
     * @param array<string, int> $blocked
     */
    private function isBlocked(ShippingMethodEntity $shippingMethod, array $blocked): bool
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
                $error->getName(),
                $newShippingMethodName
            ));
        }
    }
}
