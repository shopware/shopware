<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class DeliveryValidator implements CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $blockedShippingMethods = [];
        $blockedDeliveries = [];

        foreach ($cart->getDeliveries() as $delivery) {
            $shippingMethod = $delivery->getShippingMethod();
            $ruleId = $shippingMethod->getAvailabilityRuleId();

            $ruleIds = !Feature::isActive('v6.8.0.0')
                ? $context->getRuleIds()
                : $delivery->getRuleIds() ?? $context->getRuleIds();

            $matches = $ruleId === null || \in_array($ruleId, $ruleIds, true);

            if ($matches && $shippingMethod->getActive()) {
                continue;
            }

            $blockedShippingMethods[$shippingMethod->getId()] = $shippingMethod;
            $blockedDeliveries[$shippingMethod->getId()][] = $delivery;
        }

        foreach ($blockedShippingMethods as $shippingMethodId => $shippingMethod) {
            $errors->add(
                ShippingMethodBlockedError::forDeliveries($shippingMethod, 'rule not matching or inactive', $blockedDeliveries[$shippingMethodId])
            );
        }
    }
}
